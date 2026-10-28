<?php
declare(strict_types=1);

// ============================================================================
// /public_html/pagamentos/webhook_mp_marketplace.php
// Ajuste aplicado SEM mudar a lógica:
// - quando aprovar: baixa a fatura do mês,
//   atualiza tenant, gera/atualiza a próxima fatura,
//   envia recibo no WhatsApp
// - evita duplicidade: primeiro procura pela própria mp_payment_id
// ============================================================================

require_once __DIR__ . '/timezone_pagamentos.php';
bb_pagamentos_aplicar_timezone();
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

function bb_market_cfg(): array {
    $paths = [
        __DIR__ . '/../config/marketplace.php',
        __DIR__ . '/config/marketplace.php',
    ];

    foreach ($paths as $cfgFile) {
        if (is_file($cfgFile)) {
            $cfg = require $cfgFile;
            if (is_array($cfg) && !empty($cfg['master']) && is_array($cfg['master'])) {
                return $cfg;
            }
        }
    }

    http_response_code(500);
    exit("config ausente\n");
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

$tokenRecebido = (string)($_GET['token'] ?? '');
$tokenEsperado = (string)($cfg['webhook_token'] ?? ($cfg['app']['webhook_token'] ?? ''));
if ($tokenEsperado !== '' && !hash_equals($tokenEsperado, $tokenRecebido)) {
    http_response_code(403);
    exit("forbidden\n");
}

$pdo = bb_market_pdo($cfg);
$accessToken = bb_market_get_cfg_value($cfg, 'access_token_mp');
if ($accessToken === '') {
    http_response_code(500);
    exit("access_token_mp ausente\n");
}

$raw = file_get_contents('php://input') ?: '';
$js  = json_decode($raw, true);
if (!is_array($js)) {
    $js = [];
}

$paymentId = $_GET['data_id'] ?? ($_GET['id'] ?? ($_GET['payment_id'] ?? ($_GET['data.id'] ?? null)));

if (!$paymentId && isset($_POST['id'])) {
    $paymentId = $_POST['id'];
}
if (!$paymentId && isset($_POST['data_id'])) {
    $paymentId = $_POST['data_id'];
}
if (!$paymentId && is_array($js)) {
    $paymentId = $js['data']['id'] ?? ($js['id'] ?? null);
}

if (!$paymentId) {
    $resource = (string)($_GET['resource'] ?? ($_POST['resource'] ?? ($js['resource'] ?? '')));
    if ($resource !== '' && preg_match('~/payments/(\d+)~', $resource, $mResource)) {
        $paymentId = (string)$mResource[1];
    }
}

$paymentId = preg_replace('/\D+/', '', (string)$paymentId);
if ($paymentId === '') {
    http_response_code(200);
    exit("OK\n");
}

$ch = curl_init("https://api.mercadopago.com/v1/payments/{$paymentId}");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer {$accessToken}",
        "Content-Type: application/json"
    ],
    CURLOPT_TIMEOUT => 30,
]);
$resp = curl_exec($ch);
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resp === false || $http < 200 || $http >= 300) {
    http_response_code(200);
    exit("OK\n");
}

$data = json_decode((string)$resp, true);
if (!is_array($data)) {
    http_response_code(200);
    exit("OK\n");
}

$status = (string)($data['status'] ?? '');
$external = (string)($data['external_reference'] ?? '');
if ($status !== 'approved' || $external === '') {
    http_response_code(200);
    exit("OK\n");
}

$tenantId = 0;
if (preg_match('/(\d+)/', $external, $m)) {
    $tenantId = (int)$m[1];
}
if ($tenantId <= 0) {
    http_response_code(200);
    exit("OK\n");
}

$faturaId = 0;

try {
    $stByMp = $pdo->prepare("
        SELECT id
          FROM marketplace_faturas
         WHERE tenant_id = :tenant_id
           AND mp_payment_id = :pid
         ORDER BY id DESC
         LIMIT 1
    ");
    $stByMp->execute([
        ':tenant_id' => $tenantId,
        ':pid'       => $paymentId
    ]);
    $rowMp = $stByMp->fetch(PDO::FETCH_ASSOC);

    if ($rowMp && !empty($rowMp['id'])) {
        $faturaId = (int)$rowMp['id'];
    } else {
        $stPend = $pdo->prepare("
            SELECT id
              FROM marketplace_faturas
             WHERE tenant_id = :tenant_id
               AND status = 'pending'
             ORDER BY vencimento ASC, id ASC
             LIMIT 1
        ");
        $stPend->execute([':tenant_id' => $tenantId]);
        $rowPend = $stPend->fetch(PDO::FETCH_ASSOC);

        if ($rowPend && !empty($rowPend['id'])) {
            $faturaId = (int)$rowPend['id'];
        } else {
            $stPago = $pdo->prepare("
                SELECT id
                  FROM marketplace_faturas
                 WHERE tenant_id = :tenant_id
                   AND status = 'paid'
                 ORDER BY vencimento DESC, id DESC
                 LIMIT 1
            ");
            $stPago->execute([':tenant_id' => $tenantId]);
            $rowPago = $stPago->fetch(PDO::FETCH_ASSOC);

            if ($rowPago && !empty($rowPago['id'])) {
                $faturaId = (int)$rowPago['id'];
            }
        }
    }

    if ($faturaId > 0) {
        bb_market_baixar_fatura_recorrente($pdo, $cfg, $tenantId, $faturaId, (string)$paymentId);
    }
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
}

http_response_code(200);
echo "OK\n";
