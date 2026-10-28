<?php
declare(strict_types=1);

// ============================================================================
// BarberBot Marketplace - Processamento de Pagamento
// Arquivo: /public_html/pagamentos/process_payment_marketplace.php
// Ajuste aplicado SEM mudar a lógica de negócio:
// - remove campos internos antes de enviar ao MP
// - ao aprovar: baixa a fatura atual, atualiza vencimento do tenant,
//   gera/atualiza a próxima fatura do próximo mês e envia recibo no WhatsApp
// ============================================================================

require_once __DIR__ . '/timezone_pagamentos.php';
bb_pagamentos_aplicar_timezone();
header('Content-Type: application/json; charset=utf-8');

function bb_json_saida(array $dados): void {
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function bb_market_cfg(): array {
    $cfgFile = __DIR__ . '/../config/marketplace.php';
    if (!is_file($cfgFile)) {
        bb_json_saida(['status' => false, 'message' => 'Config do marketplace não encontrada.']);
    }

    $cfg = require $cfgFile;
    if (!is_array($cfg) || empty($cfg['master']) || !is_array($cfg['master'])) {
        bb_json_saida(['status' => false, 'message' => 'Config do marketplace inválida.']);
    }

    return $cfg;
}

function bb_market_pdo(array $cfg): PDO {
    return new PDO(
        "mysql:host={$cfg['master']['host']};dbname={$cfg['master']['db']};charset=utf8mb4",
        $cfg['master']['user'],
        (string)($cfg['master']['pass'] ?? ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

function bb_market_cfg_db_pdo(array $dbCfg, array $fallbackCfg = []): ?PDO {
    $host = trim((string)($dbCfg['host'] ?? ($fallbackCfg['host'] ?? '')));
    $db = preg_replace('/[^a-z0-9_]/i', '', (string)($dbCfg['db'] ?? ''));
    $user = trim((string)($dbCfg['user'] ?? ($fallbackCfg['user'] ?? '')));
    $pass = array_key_exists('pass', $dbCfg)
        ? (string)$dbCfg['pass']
        : (string)($fallbackCfg['pass'] ?? '');

    if ($host === '' || $db === '' || $user === '') {
        return null;
    }

    try {
        $pdoCfg = new PDO(
            "mysql:host={$host};dbname={$db};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        $dbAtual = (string)$pdoCfg->query('SELECT DATABASE()')->fetchColumn();
        if ($dbAtual !== $db) {
            return null;
        }

        return $pdoCfg;
    } catch (Throwable $e) {
        return null;
    }
}

function bb_market_global_config_pdos(array $cfg): array {
    $masterCfg = isset($cfg['master']) && is_array($cfg['master']) ? $cfg['master'] : [];
    $configuracoes = [$masterCfg];

    if (!empty($cfg['auth_master']) && is_array($cfg['auth_master'])) {
        $configuracoes[] = $cfg['auth_master'];
    }

    $pdos = [];
    $adicionados = [];

    foreach ($configuracoes as $dbCfg) {
        if (!is_array($dbCfg)) {
            continue;
        }

        $pdoCfg = bb_market_cfg_db_pdo($dbCfg, $masterCfg);
        if (!($pdoCfg instanceof PDO)) {
            continue;
        }

        try {
            $chave = (string)$pdoCfg->query('SELECT DATABASE()')->fetchColumn();
        } catch (Throwable $e) {
            continue;
        }

        if ($chave === '' || isset($adicionados[$chave])) {
            continue;
        }

        $adicionados[$chave] = true;
        $pdos[] = $pdoCfg;
    }

    return $pdos;
}

function bb_market_cfg_column_exists(PDO $pdo, string $table, string $column): bool {
    try {
        $st = $pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1'
        );
        $st->execute([':t' => $table, ':c' => $column]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function bb_market_get_cfg_value(array $cfg, string $campo): string {
    $campo = preg_replace('/[^a-z0-9_]/i', '', $campo);
    if ($campo === '') {
        return '';
    }

    $fontes = [$cfg];
    if (!empty($cfg['app']) && is_array($cfg['app'])) {
        $fontes[] = $cfg['app'];
    }
    if (!empty($cfg['billing']) && is_array($cfg['billing'])) {
        $fontes[] = $cfg['billing'];
    }

    foreach ($fontes as $fonte) {
        $valor = trim((string)($fonte[$campo] ?? ''));
        if ($valor !== '') {
            return $valor;
        }

        if ($campo === 'access_token_mp') {
            $valorAlternativo = trim((string)($fonte['mp_access_token'] ?? ''));
            if ($valorAlternativo !== '') {
                return $valorAlternativo;
            }
        }
    }

    foreach (bb_market_global_config_pdos($cfg) as $pdoCfg) {
        if (!bb_market_cfg_column_exists($pdoCfg, 'config', $campo)) {
            continue;
        }

        try {
            $row = $pdoCfg->query("SELECT `{$campo}` FROM config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $valor = trim((string)($row[$campo] ?? ''));
            if ($valor !== '') {
                return $valor;
            }
        } catch (Throwable $e) {
            // Continua somente entre bancos globais permitidos.
        }
    }

    return '';
}

function bb_digits($v): string {
    return preg_replace('/\D+/', '', (string)$v);
}

function bb_market_fatura_esta_paga(PDO $pdo, int $faturaId, string $paymentId = ''): bool {
    try {
        if ($faturaId > 0) {
            $st = $pdo->prepare("
                SELECT status
                  FROM marketplace_faturas
                 WHERE id = :id
                 LIMIT 1
            ");
            $st->execute([':id' => $faturaId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row && (string)($row['status'] ?? '') === 'paid') {
                return true;
            }
        }

        $paymentId = bb_digits($paymentId);
        if ($paymentId !== '') {
            $st = $pdo->prepare("
                SELECT status
                  FROM marketplace_faturas
                 WHERE mp_payment_id = :pid
                 ORDER BY id DESC
                 LIMIT 1
            ");
            $st->execute([':pid' => $paymentId]);
            $row = $st->fetch(PDO::FETCH_ASSOC);
            if ($row && (string)($row['status'] ?? '') === 'paid') {
                return true;
            }
        }
    } catch (Throwable $e) {
        // consulta local não pode travar a tela de pagamento
    }

    return false;
}

function bb_market_tenant_id_da_fatura(PDO $pdo, int $faturaId): int {
    if ($faturaId <= 0) {
        return 0;
    }

    try {
        $st = $pdo->prepare("
            SELECT tenant_id
              FROM marketplace_faturas
             WHERE id = :id
             LIMIT 1
        ");
        $st->execute([':id' => $faturaId]);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        return (int)($row['tenant_id'] ?? 0);
    } catch (Throwable $e) {
        return 0;
    }
}

function bb_market_colunas_tabela(PDO $pdo, string $tabela): array {
    try {
        $st = $pdo->prepare("
            SELECT COLUMN_NAME
              FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :t
        ");
        $st->execute([':t' => $tabela]);
        $cols = $st->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
        $cols = [];
    }

    $map = [];
    if (is_array($cols)) {
        foreach ($cols as $col) {
            $map[(string)$col] = true;
        }
    }

    return $map;
}

function bb_market_localizar_api_texto(): string {
    $candidatos = [
        __DIR__ . '/../ajax/api-texto.php',
        __DIR__ . '/../sistema/ajax/api-texto.php',
        __DIR__ . '/../pagamentos/api-texto.php',
        __DIR__ . '/api-texto.php',
    ];

    foreach ($candidatos as $arquivo) {
        if (is_file($arquivo)) {
            return $arquivo;
        }
    }

    return '';
}

function bb_market_ler_config_whatsapp_seguro(PDO $pdo): array {
    $api = '';
    $token = '';
    $instancia = '';

    try {
        $colunas = [];
        $stmtColunas = $pdo->query("SHOW COLUMNS FROM config");
        foreach ($stmtColunas->fetchAll(PDO::FETCH_ASSOC) as $coluna) {
            if (!empty($coluna['Field'])) {
                $colunas[(string)$coluna['Field']] = true;
            }
        }

        $selecionar = [];
        foreach (['api', 'token', 'instancia'] as $campo) {
            if (isset($colunas[$campo])) {
                $selecionar[] = "`{$campo}`";
            }
        }

        if (empty($selecionar)) {
            return ['api' => '', 'token' => '', 'instancia' => ''];
        }

        $row = $pdo->query("SELECT " . implode(', ', $selecionar) . " FROM config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $api = trim((string)($row['api'] ?? ''));
            $token = trim((string)($row['token'] ?? ''));
            $instancia = trim((string)($row['instancia'] ?? ''));
        }

        if ($api === '' && $token !== '' && $instancia !== '') {
            $api = 'menuia';
        }
    } catch (Throwable $e) {
        return ['api' => '', 'token' => '', 'instancia' => ''];
    }

    return ['api' => $api, 'token' => $token, 'instancia' => $instancia];
}

function bb_market_buscar_cfg_whatsapp(array $cfg): array {
    foreach (bb_market_global_config_pdos($cfg) as $pdoGlobal) {
        $cfgWhats = bb_market_ler_config_whatsapp_seguro($pdoGlobal);
        $api = trim((string)($cfgWhats['api'] ?? ''));
        $token = trim((string)($cfgWhats['token'] ?? ''));
        $instancia = trim((string)($cfgWhats['instancia'] ?? ''));

        if ($api !== '' && $token !== '' && $instancia !== '') {
            return [
                'api' => $api,
                'token' => $token,
                'instancia' => $instancia,
            ];
        }
    }

    return ['api' => '', 'token' => '', 'instancia' => ''];
}

function bb_market_proximo_vencimento(string $vencimentoBase): string {
    $hoje = new DateTime('today');
    $venc = null;

    if ($vencimentoBase !== '' && $vencimentoBase !== '0000-00-00') {
        try {
            $venc = new DateTime($vencimentoBase);
        } catch (Throwable $e) {
            $venc = null;
        }
    }

    if ($venc === null || $venc < $hoje) {
        return (new DateTime('today'))->modify('+30 days')->format('Y-m-d');
    }

    return $venc->modify('+30 days')->format('Y-m-d');
}

function bb_market_enviar_recibo(array $cfg, array $tenant, float $valorPago, string $proximoVencimento): void {
    $telefoneRaw = '';
    foreach (['telefone_whatsapp', 'whatsapp', 'dono_whatsapp', 'telefone', 'tel'] as $coluna) {
        if (!empty($tenant[$coluna])) {
            $telefoneRaw = (string)$tenant[$coluna];
            break;
        }
    }

    $telefone = preg_replace('/\D+/', '', $telefoneRaw);
    if ($telefone === '') {
        return;
    }

    if (strlen($telefone) <= 11) {
        $telefone = '55' . $telefone;
    }

    $cfgWhats = bb_market_buscar_cfg_whatsapp($cfg);
    if ($cfgWhats['api'] === '' || $cfgWhats['token'] === '' || $cfgWhats['instancia'] === '') {
        return;
    }

    $arquivoApi = bb_market_localizar_api_texto();
    if ($arquivoApi === '') {
        return;
    }

    $nome = (string)($tenant['nome'] ?? $tenant['slug'] ?? 'Barbearia');
    $slug = (string)($tenant['slug'] ?? '');
    $valorF = number_format($valorPago, 2, ',', '.');
    $proxVencF = date('d/m/Y', strtotime($proximoVencimento));
    $dataPgtoF = date('d/m/Y');

    $mensagem  = "💳 *BarberBot*\n";
    $mensagem .= "✅ _Pagamento Confirmado_\n";
    $mensagem .= "-------------------------------\n";
    $mensagem .= "*Recibo de Assinatura*\n";
    $mensagem .= "-------------------------------\n";
    $mensagem .= "*Barbearia:* {$nome}\n";
    if ($slug !== '') {
        $mensagem .= "*Slug:* {$slug}\n";
    }
    $mensagem .= "*Valor Pago:* R$ {$valorF}\n";
    $mensagem .= "*Data do Pagamento:* {$dataPgtoF}\n";
    $mensagem .= "*Próximo Vencimento:* {$proxVencF}\n";
    $mensagem .= "-------------------------------\n";
    $mensagem .= "Sua próxima mensalidade já foi gerada.";

    $api = $cfgWhats['api'];
    $token = $cfgWhats['token'];
    $instancia = $cfgWhats['instancia'];

    ob_start();
    include $arquivoApi;
    ob_end_clean();
}

function bb_market_baixar_fatura_recorrente(PDO $pdo, array $cfg, int $tenantId, int $faturaId, string $paymentId): void {
    if ($tenantId <= 0 || $faturaId <= 0) {
        return;
    }

    $stAtual = $pdo->prepare("
        SELECT f.*, t.slug, t.nome, t.vencimento AS tenant_vencimento, t.valor_mensal,
               t.telefone_whatsapp, t.whatsapp, t.email, t.dono_email
          FROM marketplace_faturas f
          INNER JOIN tenants t ON t.id = f.tenant_id
         WHERE f.id = :id
         LIMIT 1
    ");
    $stAtual->execute([':id' => $faturaId]);
    $atual = $stAtual->fetch(PDO::FETCH_ASSOC);

    if (!$atual) {
        return;
    }

    $jaEstavaPago = ((string)($atual['status'] ?? '') === 'paid');

    $colsFatura = bb_market_colunas_tabela($pdo, 'marketplace_faturas');
    $colsTenant = bb_market_colunas_tabela($pdo, 'tenants');

    $valor = (float)($atual['valor'] ?? $atual['valor_mensal'] ?? 0);
    $vencBase = (string)($atual['vencimento'] ?? '');
    if ($vencBase === '' || $vencBase === '0000-00-00') {
        $vencBase = (string)($atual['tenant_vencimento'] ?? '');
    }

    $novoVencimento = bb_market_proximo_vencimento($vencBase);
    $agoraSistema = date('Y-m-d H:i:s');
    $dataSistema = date('Y-m-d');

    $pdo->beginTransaction();

    $setsFatura = [];
    $paramsFatura = [':id' => $faturaId];

    if (isset($colsFatura['status'])) {
        $setsFatura[] = "`status` = 'paid'";
    }
    if (isset($colsFatura['mp_payment_id'])) {
        $setsFatura[] = "`mp_payment_id` = :pid";
        $paramsFatura[':pid'] = $paymentId;
    }
    foreach (['pago_em', 'paid_at', 'data_pagamento', 'data_pgto'] as $colPagamento) {
        if (isset($colsFatura[$colPagamento])) {
            $setsFatura[] = "`{$colPagamento}` = :data_pagamento";
            $paramsFatura[':data_pagamento'] = in_array($colPagamento, ['data_pagamento', 'data_pgto'], true) ? $dataSistema : $agoraSistema;
            break;
        }
    }
    if (isset($colsFatura['updated_at'])) {
        $setsFatura[] = "`updated_at` = :updated_at";
        $paramsFatura[':updated_at'] = $agoraSistema;
    }

    if (!$jaEstavaPago && !empty($setsFatura)) {
        $upFatura = $pdo->prepare("
            UPDATE marketplace_faturas
               SET " . implode(', ', $setsFatura) . "
             WHERE id = :id
             LIMIT 1
        ");
        $upFatura->execute($paramsFatura);
    }

    $setsTenant = [];
    $paramsTenant = [':id' => $tenantId];

    if (isset($colsTenant['status'])) {
        $setsTenant[] = "`status` = 'active'";
    }
    if (isset($colsTenant['ativo'])) {
        $setsTenant[] = "`ativo` = 1";
    }
    if (isset($colsTenant['blocked_at'])) {
        $setsTenant[] = "`blocked_at` = NULL";
    }
    if (isset($colsTenant['vencimento'])) {
        $setsTenant[] = "`vencimento` = :venc";
        $paramsTenant[':venc'] = $novoVencimento;
    }
    if (isset($colsTenant['ultimo_pagamento'])) {
        $setsTenant[] = "`ultimo_pagamento` = :ultimo_pagamento";
        $paramsTenant[':ultimo_pagamento'] = $agoraSistema;
    }

    if (!empty($setsTenant)) {
        $upTenant = $pdo->prepare("
            UPDATE tenants
               SET " . implode(', ', $setsTenant) . "
             WHERE id = :id
             LIMIT 1
        ");
        $upTenant->execute($paramsTenant);
    }

    $stPend = $pdo->prepare("
        SELECT id
          FROM marketplace_faturas
         WHERE tenant_id = :tenant_id
           AND status = 'pending'
         ORDER BY vencimento DESC, id DESC
         LIMIT 1
    ");
    $stPend->execute([':tenant_id' => $tenantId]);
    $pend = $stPend->fetch(PDO::FETCH_ASSOC);

    if ($pend && !empty($pend['id'])) {
        $setsPend = [];
        $paramsPend = [':id' => (int)$pend['id']];

        if (isset($colsFatura['vencimento'])) {
            $setsPend[] = "`vencimento` = :vencimento";
            $paramsPend[':vencimento'] = $novoVencimento;
        }
        if (isset($colsFatura['valor'])) {
            $setsPend[] = "`valor` = :valor";
            $paramsPend[':valor'] = $valor;
        }
        if (isset($colsFatura['status'])) {
            $setsPend[] = "`status` = 'pending'";
        }
        foreach (['mp_payment_id', 'mp_preference_id', 'mp_init_point'] as $colLimpar) {
            if (isset($colsFatura[$colLimpar])) {
                $setsPend[] = "`{$colLimpar}` = NULL";
            }
        }
        foreach (['pago_em', 'paid_at', 'data_pagamento', 'data_pgto'] as $colLimparData) {
            if (isset($colsFatura[$colLimparData])) {
                $setsPend[] = "`{$colLimparData}` = NULL";
            }
        }
        if (isset($colsFatura['updated_at'])) {
            $setsPend[] = "`updated_at` = :updated_at";
            $paramsPend[':updated_at'] = $agoraSistema;
        }

        if (!empty($setsPend)) {
            $upPend = $pdo->prepare("
                UPDATE marketplace_faturas
                   SET " . implode(', ', $setsPend) . "
                 WHERE id = :id
                 LIMIT 1
            ");
            $upPend->execute($paramsPend);
        }
    } else {
        $colsInsert = [];
        $valsInsert = [];
        $paramsInsert = [];

        if (isset($colsFatura['tenant_id'])) {
            $colsInsert[] = '`tenant_id`';
            $valsInsert[] = ':tenant_id';
            $paramsInsert[':tenant_id'] = $tenantId;
        }
        if (isset($colsFatura['vencimento'])) {
            $colsInsert[] = '`vencimento`';
            $valsInsert[] = ':vencimento';
            $paramsInsert[':vencimento'] = $novoVencimento;
        }
        if (isset($colsFatura['valor'])) {
            $colsInsert[] = '`valor`';
            $valsInsert[] = ':valor';
            $paramsInsert[':valor'] = $valor;
        }
        if (isset($colsFatura['status'])) {
            $colsInsert[] = '`status`';
            $valsInsert[] = ':status';
            $paramsInsert[':status'] = 'pending';
        }
        foreach (['criado_em', 'created_at'] as $colCriacao) {
            if (isset($colsFatura[$colCriacao])) {
                $colsInsert[] = "`{$colCriacao}`";
                $valsInsert[] = ':criado_em';
                $paramsInsert[':criado_em'] = $agoraSistema;
                break;
            }
        }

        if (!empty($colsInsert)) {
            $insPend = $pdo->prepare("
                INSERT INTO marketplace_faturas
                    (" . implode(', ', $colsInsert) . ")
                VALUES
                    (" . implode(', ', $valsInsert) . ")
            ");
            $insPend->execute($paramsInsert);
        }
    }

    $pdo->commit();

    if (!$jaEstavaPago) {
        bb_market_enviar_recibo($cfg, $atual, $valor, $novoVencimento);
    }
}

$cfg = bb_market_cfg();
$pdo = bb_market_pdo($cfg);
$accessToken = bb_market_get_cfg_value($cfg, 'access_token_mp');

if ($accessToken === '') {
    bb_json_saida(['status' => false, 'message' => 'Access Token do Mercado Pago não configurado.']);
}

/* ===========================================================
 * BLOCO 1 – CONSULTAR STATUS (acc=check)
 * =========================================================== */
if (isset($_GET['acc']) && $_GET['acc'] === 'check') {

    $paymentId = bb_digits($_GET['id'] ?? '');
    $faturaId  = (int)($_GET['fatura'] ?? 0);

    if ($paymentId === '') {
        bb_json_saida(['status' => 'erro', 'message' => 'Pagamento não informado']);
    }

    if (bb_market_fatura_esta_paga($pdo, $faturaId, $paymentId)) {
        $tenantIdLocal = bb_market_tenant_id_da_fatura($pdo, $faturaId);
        if ($tenantIdLocal > 0 && $faturaId > 0) {
            try {
                bb_market_baixar_fatura_recorrente($pdo, $cfg, $tenantIdLocal, $faturaId, $paymentId);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            }
        }
        bb_json_saida(['status' => 'pago', 'origem' => 'local']);
    }

    $curl = curl_init();
    curl_setopt_array($curl, [
        CURLOPT_URL => 'https://api.mercadopago.com/v1/payments/' . $paymentId,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ],
    ]);

    $responseOriginal = curl_exec($curl);
    $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    $response = json_decode((string)$responseOriginal, true);
    if (!is_array($response) || $httpCode < 200 || $httpCode >= 300) {
        if (bb_market_fatura_esta_paga($pdo, $faturaId, $paymentId)) {
            $tenantIdLocal = bb_market_tenant_id_da_fatura($pdo, $faturaId);
            if ($tenantIdLocal > 0 && $faturaId > 0) {
                try {
                    bb_market_baixar_fatura_recorrente($pdo, $cfg, $tenantIdLocal, $faturaId, $paymentId);
                } catch (Throwable $e) {
                    if ($pdo->inTransaction()) {
                        $pdo->rollBack();
                    }
                }
            }
            bb_json_saida(['status' => 'pago', 'origem' => 'local']);
        }
        bb_json_saida(['status' => 'erro', 'message' => 'Retorno inválido do Mercado Pago']);
    }

    $status = (string)($response['status'] ?? '');
    $tenantId = (int)preg_replace('/\D+/', '', (string)($response['external_reference'] ?? ''));
    if ($tenantId <= 0 && $faturaId > 0) {
        $tenantId = bb_market_tenant_id_da_fatura($pdo, $faturaId);
    }
    $paymentIdMp = (string)($response['id'] ?? $paymentId);

    if ($faturaId > 0 && $paymentIdMp !== '') {
        try {
            $up = $pdo->prepare("
                UPDATE marketplace_faturas
                   SET mp_payment_id = :pid
                 WHERE id = :id
                 LIMIT 1
            ");
            $up->execute([
                ':pid' => $paymentIdMp,
                ':id'  => $faturaId
            ]);
        } catch (Throwable $e) {
            // não quebra
        }
    }

    if ($status === 'approved') {
        try {
            if ($faturaId > 0 && $tenantId > 0) {
                bb_market_baixar_fatura_recorrente($pdo, $cfg, $tenantId, $faturaId, $paymentIdMp);
            }
        } catch (Throwable $e) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
        }

        bb_json_saida(['status' => 'pago']);
    }

    if (bb_market_fatura_esta_paga($pdo, $faturaId, $paymentIdMp)) {
        $tenantIdLocal = $tenantId > 0 ? $tenantId : bb_market_tenant_id_da_fatura($pdo, $faturaId);
        if ($tenantIdLocal > 0 && $faturaId > 0) {
            try {
                bb_market_baixar_fatura_recorrente($pdo, $cfg, $tenantIdLocal, $faturaId, $paymentIdMp);
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
            }
        }
        bb_json_saida(['status' => 'pago', 'origem' => 'local']);
    }

    bb_json_saida(['status' => $status !== '' ? $status : 'pending']);
}

/* ===========================================================
 * BLOCO 2 – GERAR PAGAMENTO
 * =========================================================== */
$parsedBody = json_decode((string)file_get_contents('php://input'), true);
if (!is_array($parsedBody)) {
    bb_json_saida(['status' => false, 'message' => 'Body inválido']);
}

$faturaId = (int)($parsedBody['fatura'] ?? 0);
if ($faturaId <= 0) {
    bb_json_saida(['status' => false, 'message' => 'Fatura inválida']);
}

$sql = "
    SELECT
        f.id,
        f.tenant_id,
        f.vencimento,
        f.valor,
        f.status,
        t.slug,
        t.nome,
        t.email,
        t.dono_email
    FROM marketplace_faturas f
    INNER JOIN tenants t ON t.id = f.tenant_id
    WHERE f.id = :id
    LIMIT 1
";
$st = $pdo->prepare($sql);
$st->execute([':id' => $faturaId]);
$fatura = $st->fetch(PDO::FETCH_ASSOC);

if (!$fatura) {
    bb_json_saida(['status' => false, 'message' => 'Fatura não encontrada']);
}

if ((string)($fatura['status'] ?? '') === 'paid') {
    bb_json_saida(['status' => false, 'message' => 'Esta cobrança já foi paga.']);
}

$tenantId = (int)($fatura['tenant_id'] ?? 0);
$slug = (string)($fatura['slug'] ?? '');
$nome = (string)($fatura['nome'] ?? $slug);
$descricao = trim((string)($parsedBody['descricao_local'] ?? 'Plano Assinatura'));

$emailPayer = trim((string)($parsedBody['payer']['email'] ?? ''));
if ($emailPayer === '') {
    $emailPayer = trim((string)($fatura['email'] ?? ''));
}
if ($emailPayer === '') {
    $emailPayer = trim((string)($fatura['dono_email'] ?? ''));
}
if ($emailPayer === '') {
    $hostTmp = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $emailPayer = 'pagamentos@' . $hostTmp;
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $scheme = ($_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : $scheme;
}
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $scheme . '://' . $host;

$webhookToken = '';
if (!empty($cfg['webhook_token'])) {
    $webhookToken = (string)$cfg['webhook_token'];
} elseif (!empty($cfg['app']['webhook_token'])) {
    $webhookToken = (string)$cfg['app']['webhook_token'];
}

$notificationUrl = $baseUrl . '/pagamentos/webhook_mp_marketplace.php';
$qs = [];
if ($webhookToken !== '') {
    $qs[] = 'token=' . rawurlencode($webhookToken);
}
if ($slug !== '') {
    $qs[] = 'loja=' . rawurlencode($slug);
}
if ($qs) {
    $notificationUrl .= '?' . implode('&', $qs);
}

unset($parsedBody['fatura'], $parsedBody['descricao_local']);

$parsedBody['notification_url'] = $notificationUrl;
$parsedBody['capture'] = true;
$parsedBody['external_reference'] = (string)$tenantId;
$parsedBody['description'] = 'Assinatura BarberBot - ' . $nome . ($slug !== '' ? ' (' . $slug . ')' : '');

if (!isset($parsedBody['payer']) || !is_array($parsedBody['payer'])) {
    $parsedBody['payer'] = [];
}
$parsedBody['payer']['email'] = $emailPayer;

$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => 'https://api.mercadopago.com/v1/payments',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode($parsedBody),
    CURLOPT_HTTPHEADER => [
        'X-Idempotency-Key: ' . date('Y-m-d-H:i:s-') . rand(0, 1500),
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json',
    ],
]);

$response = curl_exec($curl);
curl_close($curl);

$payment = json_decode((string)$response);

if (!isset($payment->id) || $payment->id === null) {
    $errorMessage = 'Erro ao realizar o pagamento, contacte o suporte.';
    if (!empty($payment->message)) {
        $errorMessage = (string)$payment->message;
    }
    if ($errorMessage === 'Invalid transaction_amount') {
        $errorMessage = 'Valor de pagamento inválido';
    }

    bb_json_saida([
        'status' => false,
        'message' => $errorMessage
    ]);
}

$idMercadoPago = (string)$payment->id;
$statusPagamento = (string)($payment->status ?? '');
$statusDetail = (string)($payment->status_detail ?? '');
$tipoPagamento = (string)($payment->payment_method_id ?? '');

try {
    $up = $pdo->prepare("
        UPDATE marketplace_faturas
           SET mp_payment_id = :pid
         WHERE id = :id
         LIMIT 1
    ");
    $up->execute([
        ':pid' => $idMercadoPago,
        ':id'  => $faturaId
    ]);
} catch (Throwable $e) {
    // não quebra
}

if ($statusPagamento === 'approved') {
    try {
        bb_market_baixar_fatura_recorrente($pdo, $cfg, $tenantId, $faturaId, $idMercadoPago);
    } catch (Throwable $e) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
    }
}

$statusPagMotivo = [
    'approved' => [
        'accredited' => 'Pronto, seu pagamento foi aprovado!'
    ],
    'in_process' => [
        'pending_contingency' => 'Estamos processando o pagamento. Em breve confirmaremos.',
        'pending_review_manual' => 'Estamos processando seu pagamento. Em breve confirmaremos.'
    ],
    'rejected' => [
        'cc_rejected_bad_filled_card_number' => 'Revise o número do cartão.',
        'cc_rejected_bad_filled_date' => 'Revise a data de vencimento.',
        'cc_rejected_bad_filled_other' => 'Revise os dados.',
        'cc_rejected_bad_filled_security_code' => 'Revise o código de segurança do cartão.',
        'cc_rejected_blacklist' => 'Não pudemos processar seu pagamento.',
        'cc_rejected_call_for_authorize' => 'Você deve autorizar o pagamento com a operadora do cartão.',
        'cc_rejected_card_disabled' => 'Ligue para a operadora do cartão para ativá-lo.',
        'cc_rejected_card_error' => 'Não conseguimos processar seu pagamento.',
        'cc_rejected_duplicated_payment' => 'Você já efetuou um pagamento com esse valor.',
        'cc_rejected_high_risk' => 'Seu pagamento foi recusado. Escolha outra forma de pagamento.',
        'cc_rejected_insufficient_amount' => 'O cartão possui saldo insuficiente.',
        'cc_rejected_invalid_installments' => 'O cartão não processa esse parcelamento.',
        'cc_rejected_max_attempts' => 'Você atingiu o limite de tentativas permitido.',
        'cc_rejected_other_reason' => 'O cartão não processa o pagamento.'
    ]
];

$mensagemDetalhe = '';
if (isset($statusPagMotivo[$statusPagamento]) && isset($statusPagMotivo[$statusPagamento][$statusDetail])) {
    $mensagemDetalhe = $statusPagMotivo[$statusPagamento][$statusDetail];
}

bb_json_saida([
    'id'      => $idMercadoPago,
    'status'  => true,
    'tipo'    => $tipoPagamento,
    'message' => $mensagemDetalhe
]);
