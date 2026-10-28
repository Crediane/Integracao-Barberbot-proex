<?php
declare(strict_types=1);

/**
 * /public_html/cron/cobranca_diaria.php
 *
 * Ajuste aplicado sem mudar a lógica:
 * - continua alertando antes do vencimento
 * - continua enviando cobrança no dia do vencimento
 * - continua bloqueando após vencer + dias de graça
 *
 * Correções no ponto certo:
 * - respeita config.dias_alerta_vencimento de cada tenant, quando existir
 * - mantém fallback para config/marketplace.php em billing.dias_antes_lembrete
 * - usa credenciais de WhatsApp da config do tenant quando existirem
 * - faz fallback para config do master do sistema quando marketplace.php não tiver whatsapp/api/url_sistema
 * - reutiliza a conexão do tenant quando já aberta, evitando abrir duas vezes no mesmo loop
 * - mantém marketplace_faturas + link local do sistema
 * - não usa tenant_cobrancas / init_point antigo
 * - descriptografa db_pass do tenant quando vier "enc:"
 * - evita reenviar bloqueio em loop quando já estiver bloqueado
 * - envia aviso de atraso durante a carência, antes do bloqueio
 *
 * Correção adicional desta revisão:
 * - aviso antes do vencimento também gera/reutiliza fatura pendente e envia link de pagamento
 * - bloqueio também envia link de pagamento na mensagem
 * - usa tenants.dias_aviso quando a coluna existir, mantendo fallback da config
 * - normaliza telefone do usuário para Menuia com DDI 55 somente quando vier BR sem DDI
 */

require_once __DIR__ . '/timezone_cron.php';
bb_cron_aplicar_timezone();
require_once __DIR__ . '/cron_guard.php';

$cfgFile = realpath(__DIR__ . '/../config/marketplace.php');
if (!$cfgFile || !is_file($cfgFile)) {
    exit("Config marketplace.php não encontrada\n");
}
$cfg = require $cfgFile;

if (php_sapi_name() !== 'cli') {
    $token = (string)($_GET['token'] ?? '');
    if ($token === '' || $token !== (string)($cfg['cron']['token'] ?? '')) {
        http_response_code(403);
        exit("403\n");
    }
}

function bb_pdo_connect(string $host, string $db, string $user, string $pass): PDO {
    return new PDO(
        "mysql:host={$host};dbname={$db};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

function bb_clean_phone(string $v): string {
    $raw = trim((string)$v);
    $digits = preg_replace('/\D+/', '', $raw);
    $digits = trim((string)$digits);

    if ($digits === '') {
        return '';
    }

    $temPrefixoInternacional = (bool)preg_match('/^\s*\+/', $raw) || (bool)preg_match('/^\s*00/', $raw);

    // Formato internacional com 00 na frente: remove somente o prefixo 00.
    if (strpos($digits, '00') === 0) {
        $digits = substr($digits, 2);
    }

    // Se veio com DDI de outro país, respeita o número informado.
    if ($temPrefixoInternacional && substr($digits, 0, 2) !== '55') {
        return $digits;
    }

    // Brasil sem DDI e celular antigo com 8 dígitos depois do DDD.
    // Ex.: 4599976024 => 5545999976024
    if (!$temPrefixoInternacional && strlen($digits) === 10 && substr($digits, 0, 2) !== '55') {
        $digits = substr($digits, 0, 2) . '9' . substr($digits, 2);
    }

    // Brasil sem DDI: DDD + número.
    // Ex.: 45999760249 => 5545999760249
    if (!$temPrefixoInternacional && (strlen($digits) === 10 || strlen($digits) === 11) && substr($digits, 0, 2) !== '55') {
        return '55' . $digits;
    }

    // Brasil já com DDI 55 e celular antigo com 8 dígitos depois do DDD.
    // Ex.: 554599976024 => 5545999976024
    if (substr($digits, 0, 2) === '55' && strlen($digits) === 12) {
        return substr($digits, 0, 4) . '9' . substr($digits, 4);
    }

    // Se já veio com 55 completo ou outro DDI, não mexe.
    return $digits;
}


function bb_normalizar_api_whatsapp(string $api): string {
    $api = strtolower(trim($api));
    $api = str_replace(['_', '-'], ' ', $api);
    $api = preg_replace('/\s+/', ' ', $api);

    // No painel antigo, "Api Paga" pode representar Menuia quando appkey/authkey estão preenchidos.
    if ($api === '' || $api === 'menuia' || $api === 'menu ia' || $api === 'api paga') {
        return 'menuia';
    }

    return $api;
}

function bb_montar_link_pagamento_marketplace(string $baseUrl, int $faturaId): string {
    $baseUrl = rtrim(trim($baseUrl), '/');
    if ($baseUrl === '' || $faturaId <= 0) {
        return '';
    }

    return $baseUrl . '/pagamentos/pagamento_marketplace.php?fatura=' . $faturaId;
}

function bb_montar_mensagem_assinatura_link(
    string $titulo,
    string $nome,
    string $slug,
    string $vencimento,
    float $valor,
    string $link,
    string $textoExtra = ''
): string {
    $mensagem  = $titulo . "\n";
    $mensagem .= "🏪 Barbearia: {$nome}\n";

    if ($slug !== '') {
        $mensagem .= "🔖 Slug: {$slug}\n";
    }

    $mensagem .= "📅 Vencimento: " . date('d/m/Y', strtotime($vencimento)) . "\n";
    $mensagem .= "💰 Valor: R$ " . number_format($valor, 2, ',', '.') . "\n";

    if ($textoExtra !== '') {
        $mensagem .= $textoExtra;
        if (substr($mensagem, -1) !== "\n") {
            $mensagem .= "\n";
        }
    }

    if ($link !== '') {
        $mensagem .= "\n👉 Clique para pagar:\n{$link}";
    }

    return $mensagem;
}

function bb_localizar_api_texto(): string {
    $candidatos = [
        // Mantém primeiro o padrão antigo do sistema, quando existir.
        __DIR__ . '/../ajax/api-texto.php',
        __DIR__ . '/../pagamentos/api-texto.php',
        __DIR__ . '/../sistema/ajax/api-texto.php',
        __DIR__ . '/../sistema/painel/apis/texto.php',
        __DIR__ . '/../painel/apis/texto.php',
        __DIR__ . '/../apis/texto.php',
        // Fallback específico do pacote de cron enviado junto com este ajuste.
        __DIR__ . '/texto.php',
    ];

    foreach ($candidatos as $arquivo) {
        if (is_file($arquivo)) {
            return $arquivo;
        }
    }

    return '';
}

function bb_whatsapp_status_ok(string $status, string $retorno = ''): bool {
    $texto = trim($status . "\n" . $retorno);

    if ($texto === '') {
        // Alguns arquivos antigos de API não retornam texto, mas não lançam erro.
        return true;
    }

    $textoLower = function_exists('mb_strtolower') ? mb_strtolower($texto, 'UTF-8') : strtolower($texto);

    if ($status === 'Mensagem enviada com sucesso.' || $status === 'OK') {
        return true;
    }

    return (
        strpos($textoLower, 'sucesso') !== false ||
        strpos($textoLower, 'enviad') !== false ||
        strpos($textoLower, 'sent') !== false ||
        strpos($textoLower, 'success') !== false ||
        strpos($textoLower, 'ok') === 0
    );
}

function bb_send_whatsapp(string $telefone, string $mensagem, string $api, string $token, string $instancia): bool {
    $api = bb_normalizar_api_whatsapp($api);

    if ($telefone === '' || $api === '' || $token === '' || $instancia === '') {
        return false;
    }

    $arquivoApi = bb_localizar_api_texto();
    if ($arquivoApi === '') {
        return false;
    }

    $telefone = bb_clean_phone($telefone);
    if ($telefone === '') {
        return false;
    }

    // Variáveis esperadas pelo /ajax/api-texto.php no padrão do sistema.
    $status_mensagem = '';
    $response = '';
    $http = 0;
    $errno = 0;
    $err = '';

    ob_start();
    include $arquivoApi;
    $saidaApi = (string)ob_get_clean();

    if (bb_whatsapp_status_ok((string)$status_mensagem, (string)$response . "\n" . $saidaApi)) {
        return true;
    }

    // Segurança extra para arquivos antigos que ainda retornam JSON bruto.
    if (isset($response) && is_string($response) && $response !== '') {
        $json = json_decode($response, true);
        if (is_array($json)) {
            if ((isset($json['status']) && (int)$json['status'] === 200) || !empty($json['success']) || !empty($json['ok'])) {
                return true;
            }
        }
    }

    return false;
}

function bb_col_exists(PDO $pdo, string $dbName, string $table, string $col): bool {
    try {
        $st = $pdo->prepare("
            SELECT 1
              FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = :db
               AND TABLE_NAME = :tb
               AND COLUMN_NAME = :col
             LIMIT 1
        ");
        $st->execute([
            ':db'  => $dbName,
            ':tb'  => $table,
            ':col' => $col
        ]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function bb_try_decrypt_pass(string $rawPass, string $secret): string {
    if ($rawPass === '') {
        return '';
    }
    if ($secret === '') {
        return $rawPass;
    }
    if (substr($rawPass, 0, 4) !== 'enc:') {
        return $rawPass;
    }

    $cryptoFile = __DIR__ . '/../sistema/tenant_crypto.php';
    if (is_file($cryptoFile)) {
        require_once $cryptoFile;
    }

    if (function_exists('bb_decrypt_str')) {
        $dec = (string)bb_decrypt_str($rawPass, $secret);
        if ($dec !== '') {
            return $dec;
        }
    }

    return $rawPass;
}

function bb_market_get_or_create_pending_fatura(PDO $pdoMaster, int $tenantId, string $vencimento, float $valor): int {
    if ($tenantId <= 0 || $vencimento === '' || $valor <= 0) {
        return 0;
    }

    // 1) Primeiro respeita a chave única tenant/vencimento.
    // Se já existir qualquer fatura para aquele vencimento, reutiliza.
    try {
        $stMesmoVencimento = $pdoMaster->prepare("
            SELECT id, status
              FROM marketplace_faturas
             WHERE tenant_id = :tenant_id
               AND vencimento = :vencimento
             ORDER BY id DESC
             LIMIT 1
        ");
        $stMesmoVencimento->execute([
            ':tenant_id'  => $tenantId,
            ':vencimento' => $vencimento,
        ]);
        $existente = $stMesmoVencimento->fetch(PDO::FETCH_ASSOC);

        if ($existente && !empty($existente['id'])) {
            $faturaId = (int)$existente['id'];
            $statusExistente = strtolower(trim((string)($existente['status'] ?? '')));

            // Mantém a lógica: se está pendente, apenas atualiza valor.
            // Se já estiver paga/cancelada, não reabre e não muda status.
            if ($statusExistente === 'pending') {
                $upMesmoVenc = $pdoMaster->prepare("
                    UPDATE marketplace_faturas
                       SET valor = :valor
                     WHERE id = :id
                     LIMIT 1
                ");
                $upMesmoVenc->execute([
                    ':valor' => $valor,
                    ':id'    => $faturaId,
                ]);
            }

            return $faturaId;
        }
    } catch (Throwable $e) {
        // segue para o fluxo antigo com proteção
    }

    // 2) Mantém o comportamento anterior: se existir fatura pending do tenant,
    // reaproveita e aponta para o vencimento atual.
    try {
        $stPend = $pdoMaster->prepare("
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
            $faturaId = (int)$pend['id'];

            try {
                $upF = $pdoMaster->prepare("
                    UPDATE marketplace_faturas
                       SET vencimento = :vencimento,
                           valor = :valor,
                           status = 'pending'
                     WHERE id = :id
                     LIMIT 1
                ");
                $upF->execute([
                    ':vencimento' => $vencimento,
                    ':valor'      => $valor,
                    ':id'         => $faturaId,
                ]);

                return $faturaId;
            } catch (PDOException $e) {
                // Se a chave única já existir para outro registro, busca e reutiliza.
                if ((string)$e->getCode() === '23000') {
                    $stDup = $pdoMaster->prepare("
                        SELECT id
                          FROM marketplace_faturas
                         WHERE tenant_id = :tenant_id
                           AND vencimento = :vencimento
                         ORDER BY id DESC
                         LIMIT 1
                    ");
                    $stDup->execute([
                        ':tenant_id'  => $tenantId,
                        ':vencimento' => $vencimento,
                    ]);
                    $dupId = (int)$stDup->fetchColumn();
                    return $dupId > 0 ? $dupId : 0;
                }

                throw $e;
            }
        }

        $insF = $pdoMaster->prepare("
            INSERT INTO marketplace_faturas
                (tenant_id, vencimento, valor, status, criado_em)
            VALUES
                (:tenant_id, :vencimento, :valor, 'pending', :criado_em)
        ");
        $insF->execute([
            ':tenant_id'  => $tenantId,
            ':vencimento' => $vencimento,
            ':valor'      => $valor,
            ':criado_em'  => bb_cron_data_hora_agora(),
        ]);

        return (int)$pdoMaster->lastInsertId();
    } catch (PDOException $e) {
        if ((string)$e->getCode() === '23000') {
            try {
                $stDup = $pdoMaster->prepare("
                    SELECT id
                      FROM marketplace_faturas
                     WHERE tenant_id = :tenant_id
                       AND vencimento = :vencimento
                     ORDER BY id DESC
                     LIMIT 1
                ");
                $stDup->execute([
                    ':tenant_id'  => $tenantId,
                    ':vencimento' => $vencimento,
                ]);
                $dupId = (int)$stDup->fetchColumn();
                return $dupId > 0 ? $dupId : 0;
            } catch (Throwable $e2) {
                return 0;
            }
        }

        return 0;
    } catch (Throwable $e) {
        return 0;
    }
}

function bb_open_tenant_from_row(array $tenant, string $mkHost, string $secret): ?PDO {
    $tenantHost = (string)($tenant['db_host'] ?? $mkHost);
    $tenantDb   = (string)($tenant['db_name'] ?? '');
    $tenantUser = (string)($tenant['db_user'] ?? '');
    $tenantPass = bb_try_decrypt_pass((string)($tenant['db_pass'] ?? ''), $secret);

    if ($tenantDb === '' || $tenantUser === '') {
        return null;
    }

    try {
        $pdoTenant = bb_pdo_connect($tenantHost, $tenantDb, $tenantUser, $tenantPass);
        return bb_cron_guard_database_matches($pdoTenant, $tenantDb) ? $pdoTenant : null;
    } catch (Throwable $e) {
        return null;
    }
}

function bb_fetch_one_config(PDO $pdo): array {
    try {
        $row = $pdo->query("SELECT * FROM config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        return is_array($row) ? $row : [];
    } catch (Throwable $e) {
        return [];
    }
}

function bb_open_auth_master(array $cfg): ?PDO {
    $auth = $cfg['auth_master'] ?? [];
    $host = (string)($auth['host'] ?? 'localhost');
    $db   = (string)($auth['db'] ?? '');
    $user = (string)($auth['user'] ?? '');
    $pass = (string)($auth['pass'] ?? '');

    if ($db === '' || $user === '') {
        return null;
    }

    try {
        $pdoAuth = bb_pdo_connect($host, $db, $user, $pass);
        return bb_cron_guard_database_matches($pdoAuth, $db) ? $pdoAuth : null;
    } catch (Throwable $e) {
        return null;
    }
}

function bb_resolver_base_url(array $cfg, array $cfgMasterSistema): string {
    $baseUrl = '';

    if (!empty($cfg['app']['url_sistema'])) {
        $baseUrl = rtrim((string)$cfg['app']['url_sistema'], '/');
        $baseUrl = preg_replace('~/sistema$~i', '', $baseUrl);
        return (string)$baseUrl;
    }

    if (!empty($cfgMasterSistema['url_sistema'])) {
        $baseUrl = rtrim((string)$cfgMasterSistema['url_sistema'], '/');
        $baseUrl = preg_replace('~/sistema$~i', '', $baseUrl);
        return (string)$baseUrl;
    }

    if (!empty($cfg['base_url'])) {
        return rtrim((string)$cfg['base_url'], '/');
    }

    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $scheme = ($_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : $scheme;
    }
    $host = $_SERVER['HTTP_HOST'] ?? '';

    if ($host !== '') {
        return $scheme . '://' . $host;
    }

    return '';
}

function bb_resolver_whatsapp_global(array $cfg, array $cfgMasterSistema): array {
    $api       = bb_normalizar_api_whatsapp((string)($cfg['whatsapp']['api'] ?? ''));
    $tokenWpp  = trim((string)($cfg['whatsapp']['token'] ?? ''));
    $instancia = trim((string)($cfg['whatsapp']['instancia'] ?? ''));

    if ($api !== '' && $tokenWpp !== '' && $instancia !== '') {
        return [
            'api'       => $api,
            'token'     => $tokenWpp,
            'instancia' => $instancia,
        ];
    }

    return [
        'api'       => bb_normalizar_api_whatsapp((string)($cfgMasterSistema['api'] ?? '')),
        'token'     => trim((string)($cfgMasterSistema['token'] ?? '')),
        'instancia' => trim((string)($cfgMasterSistema['instancia'] ?? '')),
    ];
}


function bb_config_dias_positivo($valor): int {
    if (is_int($valor) || is_float($valor)) {
        $dias = (int)$valor;
    } else {
        $texto = trim((string)$valor);
        if ($texto === '') {
            return 0;
        }

        // Aceita tanto valor puro "3" quanto texto salvo como "3 dias antes".
        if (!preg_match('/(\d+)/', $texto, $m)) {
            return 0;
        }
        $dias = (int)$m[1];
    }

    // Evita configuração inválida derrubar o fluxo de cobrança.
    if ($dias <= 0 || $dias > 365) {
        return 0;
    }

    return $dias;
}

function bb_resolver_dias_aviso_admin(array $cfg, array $cfgMasterSistema, int $fallback): array {
    $fallback = $fallback > 0 ? $fallback : 3;

    // Primeiro respeita o painel do administrador/dono do sistema.
    // No sistema antigo esse campo pode aparecer com nomes diferentes.
    $camposConfigMaster = [
        'dias_alerta_vencimento',
        'alerta_vencimento_dias',
        'alerta_vencimento',
        'dias_aviso',
        'dias_antes_lembrete',
        'minutos_aviso',
    ];

    foreach ($camposConfigMaster as $campo) {
        if (array_key_exists($campo, $cfgMasterSistema)) {
            $dias = bb_config_dias_positivo($cfgMasterSistema[$campo]);
            if ($dias > 0) {
                return [
                    'dias'  => $dias,
                    'admin' => true,
                    'fonte' => 'config.' . $campo,
                ];
            }
        }
    }

    // Fallback do arquivo config/marketplace.php para manter compatibilidade.
    $fontesArquivo = [
        $cfg['billing']['dias_antes_lembrete'] ?? null,
        $cfg['billing']['dias_alerta_vencimento'] ?? null,
        $cfg['marketplace']['dias_aviso'] ?? null,
        $cfg['cron']['dias_alerta_vencimento'] ?? null,
        $cfg['dias_alerta_vencimento'] ?? null,
    ];

    foreach ($fontesArquivo as $valor) {
        $dias = bb_config_dias_positivo($valor);
        if ($dias > 0) {
            return [
                'dias'  => $dias,
                'admin' => false,
                'fonte' => 'config/marketplace.php',
            ];
        }
    }

    return [
        'dias'  => $fallback,
        'admin' => false,
        'fonte' => 'fallback',
    ];
}

$mk = $cfg['master'] ?? [];
$mkHost = (string)($mk['host'] ?? 'localhost');
$mkDb   = (string)($mk['db'] ?? '');
$mkUser = (string)($mk['user'] ?? '');
$mkPass = (string)($mk['pass'] ?? '');

if ($mkDb === '' || $mkUser === '') {
    exit("Config master incompleta\n");
}

$tenantsTable = preg_replace('/[^a-z0-9_]/i', '', (string)($mk['table'] ?? 'tenants'));
if ($tenantsTable === '') {
    $tenantsTable = 'tenants';
}

$secret = (string)($cfg['security']['tenant_secret'] ?? '');
$diasAntesPadrao = (int)($cfg['billing']['dias_antes_lembrete'] ?? 3);
if ($diasAntesPadrao <= 0) {
    $diasAntesPadrao = 3;
}
$diasGraca = (int)($cfg['billing']['dias_graca'] ?? 2);
if ($diasGraca < 0) {
    $diasGraca = 0;
}

try {
    $pdoMaster = bb_pdo_connect($mkHost, $mkDb, $mkUser, $mkPass);
    if (!bb_cron_guard_database_matches($pdoMaster, $mkDb)) {
        throw new RuntimeException('Banco global do Marketplace não confirmado.');
    }
} catch (Throwable $e) {
    exit("Falha ao conectar no master\n");
}

$cfgMasterSistema = [];
$pdoAuthMaster = bb_open_auth_master($cfg);
if ($pdoAuthMaster instanceof PDO) {
    $cfgMasterSistema = bb_fetch_one_config($pdoAuthMaster);
}

$diasAvisoAdminCfg = bb_resolver_dias_aviso_admin($cfg, $cfgMasterSistema, $diasAntesPadrao);
$diasAntesPadrao = (int)$diasAvisoAdminCfg['dias'];
$diasAvisoVemDoAdmin = (bool)$diasAvisoAdminCfg['admin'];

$whatsGlobal = bb_resolver_whatsapp_global($cfg, $cfgMasterSistema);
$apiGlobal = $whatsGlobal['api'];
$tokenGlobal = $whatsGlobal['token'];
$instanciaGlobal = $whatsGlobal['instancia'];

$baseUrl = bb_resolver_base_url($cfg, $cfgMasterSistema);

$hasUltimoLembrete = bb_col_exists($pdoMaster, $mkDb, $tenantsTable, 'ultimo_lembrete');
$hasWhatsapp       = bb_col_exists($pdoMaster, $mkDb, $tenantsTable, 'whatsapp');
$hasTelefone       = bb_col_exists($pdoMaster, $mkDb, $tenantsTable, 'telefone_whatsapp');
$hasBlockedAt      = bb_col_exists($pdoMaster, $mkDb, $tenantsTable, 'blocked_at');
$hasAtivo          = bb_col_exists($pdoMaster, $mkDb, $tenantsTable, 'ativo');
$hasStatus         = bb_col_exists($pdoMaster, $mkDb, $tenantsTable, 'status');
$hasTimezone       = bb_col_exists($pdoMaster, $mkDb, $tenantsTable, 'timezone');
$hasDiasAviso      = bb_col_exists($pdoMaster, $mkDb, $tenantsTable, 'dias_aviso');

$colTelefoneExpr = "'' AS telefone_alerta";
if ($hasTelefone && $hasWhatsapp) {
    $colTelefoneExpr = "COALESCE(NULLIF(`telefone_whatsapp`, ''), NULLIF(`whatsapp`, ''), '') AS telefone_alerta";
} elseif ($hasTelefone) {
    $colTelefoneExpr = "`telefone_whatsapp` AS telefone_alerta";
} elseif ($hasWhatsapp) {
    $colTelefoneExpr = "`whatsapp` AS telefone_alerta";
}

$colUltExpr = $hasUltimoLembrete ? "`ultimo_lembrete`" : "NULL";
$colBlockedExpr = $hasBlockedAt ? "`blocked_at`" : "NULL";
$colTimezoneExpr = $hasTimezone ? "`timezone`" : "NULL";
$colDiasAvisoExpr = $hasDiasAviso ? "`dias_aviso`" : "NULL";

$sql = "
SELECT
    id,
    slug,
    nome,
    vencimento,
    valor_mensal,
    db_host,
    db_name,
    db_user,
    db_pass,
    {$colTelefoneExpr},
    email,
    {$colUltExpr} AS ultimo_lembrete,
    {$colBlockedExpr} AS blocked_at,
    {$colTimezoneExpr} AS timezone_tenant,
    {$colDiasAvisoExpr} AS dias_aviso_tenant
FROM `{$tenantsTable}`
WHERE vencimento IS NOT NULL
  AND valor_mensal > 0
";
$tenants = $pdoMaster->query($sql)->fetchAll();

foreach ($tenants as $t) {
    $tenantId = (int)($t['id'] ?? 0);
    if ($tenantId <= 0) {
        continue;
    }

    $slug = preg_replace('/[^a-z0-9_-]/i', '', (string)($t['slug'] ?? ''));
    $nome = (string)(($t['nome'] ?? '') !== '' ? $t['nome'] : $slug);
    $vencimento = (string)($t['vencimento'] ?? '');
    $valor = (float)($t['valor_mensal'] ?? 0);
    $telefone = bb_clean_phone((string)($t['telefone_alerta'] ?? ''));

    bb_cron_aplicar_timezone((string)($t['timezone_tenant'] ?? ''));

    if ($valor <= 0 || $vencimento === '') {
        continue;
    }

    $hojeTenant = bb_cron_data_hoje();
    $dias = (int)floor((strtotime($vencimento) - strtotime($hojeTenant)) / 86400);

    $pdoTenant = null;
    $cfgTenant = [];
    $diasAntesTenant = $diasAntesPadrao;
    $diasAvisoTenantTabela = (int)($t['dias_aviso_tenant'] ?? 0);

    // Quando o administrador define "Alerta Vencimento (dias)" no painel master,
    // essa configuração é global para o marketplace e não deve ser sobrescrita
    // pelo banco individual da loja nem por valor antigo em tenants.dias_aviso.
    if (!$diasAvisoVemDoAdmin && $diasAvisoTenantTabela > 0) {
        $diasAntesTenant = $diasAvisoTenantTabela;
    }

    $apiEnvio = $apiGlobal;
    $tokenEnvio = $tokenGlobal;
    $instanciaEnvio = $instanciaGlobal;

    if (!empty($t['db_name']) && !empty($t['db_user'])) {
        $pdoTenant = bb_open_tenant_from_row($t, $mkHost, $secret);
        if ($pdoTenant instanceof PDO) {
            $cfgTenant = bb_fetch_one_config($pdoTenant);
            if (!empty($cfgTenant['timezone'])) {
                bb_cron_aplicar_timezone($cfgTenant['timezone']);
                $hojeTenant = bb_cron_data_hoje();
                $dias = (int)floor((strtotime($vencimento) - strtotime($hojeTenant)) / 86400);
            }

            if (!$diasAvisoVemDoAdmin && isset($cfgTenant['dias_alerta_vencimento'])) {
                $diasCfg = bb_config_dias_positivo($cfgTenant['dias_alerta_vencimento']);
                if ($diasCfg > 0) {
                    $diasAntesTenant = $diasCfg;
                }
            }

            // Marketplace: a cobrança deve sair pela configuração do dono do sistema/master.
            // Só usa credenciais do tenant como fallback quando a configuração master estiver incompleta.
            if ($apiEnvio === '' && !empty($cfgTenant['api'])) {
                $apiEnvio = bb_normalizar_api_whatsapp((string)$cfgTenant['api']);
            }
            if ($tokenEnvio === '' && !empty($cfgTenant['token'])) {
                $tokenEnvio = trim((string)$cfgTenant['token']);
            }
            if ($instanciaEnvio === '' && !empty($cfgTenant['instancia'])) {
                $instanciaEnvio = trim((string)$cfgTenant['instancia']);
            }
        }
    }

    if ($dias === $diasAntesTenant) {
        $jaEnviouHoje = false;

        if ($hasUltimoLembrete) {
            $ultimo = trim((string)($t['ultimo_lembrete'] ?? ''));
            $jaEnviouHoje = ($ultimo === $hojeTenant);
        }

        if (!$jaEnviouHoje) {
            $faturaId = bb_market_get_or_create_pending_fatura($pdoMaster, $tenantId, $vencimento, $valor);
            $link = bb_montar_link_pagamento_marketplace($baseUrl, $faturaId);

            $mensagem = bb_montar_mensagem_assinatura_link(
                "🔔 *Lembrete de Assinatura BarberBot*",
                $nome,
                $slug,
                $vencimento,
                $valor,
                $link,
                "⚠️ Sua assinatura vence em {$diasAntesTenant} dia(s).\n"
            );

            $enviado = bb_send_whatsapp($telefone, $mensagem, $apiEnvio, $tokenEnvio, $instanciaEnvio);

            if ($enviado && $hasUltimoLembrete) {
                $up = $pdoMaster->prepare("UPDATE `{$tenantsTable}` SET ultimo_lembrete = ? WHERE id = ?");
                $up->execute([$hojeTenant, $tenantId]);
            }
        }
    }

    if ($dias === 0) {
        $faturaId = bb_market_get_or_create_pending_fatura($pdoMaster, $tenantId, $vencimento, $valor);

        if ($faturaId > 0 && $telefone !== '') {
            $link = bb_montar_link_pagamento_marketplace($baseUrl, $faturaId);

            $mensagem = bb_montar_mensagem_assinatura_link(
                "💳 *Assinatura BarberBot*",
                $nome,
                $slug,
                $vencimento,
                $valor,
                $link
            );

            bb_send_whatsapp($telefone, $mensagem, $apiEnvio, $tokenEnvio, $instanciaEnvio);
        }
    }

    if ($dias < 0 && abs($dias) <= $diasGraca) {
        $jaEnviouHoje = false;

        if ($hasUltimoLembrete) {
            $ultimo = trim((string)($t['ultimo_lembrete'] ?? ''));
            $jaEnviouHoje = ($ultimo === $hojeTenant);
        }

        if (!$jaEnviouHoje) {
            $faturaId = bb_market_get_or_create_pending_fatura($pdoMaster, $tenantId, $vencimento, $valor);
            $link = '';

            if ($faturaId > 0 && $baseUrl !== '') {
                $link = bb_montar_link_pagamento_marketplace($baseUrl, $faturaId);
            }

            $diasAtraso = abs($dias);
            $faltamBloqueio = ($diasGraca + 1) - $diasAtraso;
            if ($faltamBloqueio < 0) {
                $faltamBloqueio = 0;
            }

            $mensagem  = "⚠️ *Assinatura BarberBot em Atraso*\n";
            $mensagem .= "🏪 Barbearia: {$nome}\n";
            if ($slug !== '') {
                $mensagem .= "🔖 Slug: {$slug}\n";
            }
            $mensagem .= "📅 Vencimento: " . date('d/m/Y', strtotime($vencimento)) . "\n";
            $mensagem .= "💰 Valor: R$ " . number_format($valor, 2, ',', '.') . "\n";
            $mensagem .= "⏰ Atraso: {$diasAtraso} dia(s)\n";
            $mensagem .= "🚫 Faltam {$faltamBloqueio} dia(s) para o bloqueio automático.\n";

            if ($link !== '') {
                $mensagem .= "\n👉 Clique para pagar:\n{$link}";
            }

            $enviado = bb_send_whatsapp($telefone, $mensagem, $apiEnvio, $tokenEnvio, $instanciaEnvio);

            if ($enviado && $hasUltimoLembrete) {
                $up = $pdoMaster->prepare("UPDATE `{$tenantsTable}` SET ultimo_lembrete = ? WHERE id = ?");
                $up->execute([$hojeTenant, $tenantId]);
            }
        }
    }

    if ($dias < 0 && abs($dias) > $diasGraca) {
        $jaBloqueado = false;

        if ($hasStatus) {
            $st = $pdoMaster->prepare("SELECT `status` FROM `{$tenantsTable}` WHERE id = ? LIMIT 1");
            $st->execute([$tenantId]);
            $statusAtual = strtolower(trim((string)$st->fetchColumn()));
            $jaBloqueado = ($statusAtual === 'blocked');
        } elseif ($hasAtivo) {
            $st = $pdoMaster->prepare("SELECT `ativo` FROM `{$tenantsTable}` WHERE id = ? LIMIT 1");
            $st->execute([$tenantId]);
            $ativoAtual = (string)$st->fetchColumn();
            $jaBloqueado = ($ativoAtual === '0');
        }

        $sets = [];
        $paramsBloqueio = [];
        if ($hasStatus) {
            $sets[] = "`status`='blocked'";
        }
        if ($hasAtivo) {
            $sets[] = "`ativo`=0";
        }
        if ($hasBlockedAt) {
            $sets[] = "`blocked_at`=?";
            $paramsBloqueio[] = bb_cron_data_hora_agora();
        }

        if (!empty($sets)) {
            $paramsBloqueio[] = $tenantId;
            $up = $pdoMaster->prepare("
                UPDATE `{$tenantsTable}`
                   SET " . implode(",\n                       ", $sets) . "
                 WHERE id = ?
            ");
            $up->execute($paramsBloqueio);
        }

        if (!($pdoTenant instanceof PDO) && !empty($t['db_name']) && !empty($t['db_user'])) {
            $pdoTenant = bb_open_tenant_from_row($t, $mkHost, $secret);
        }

        if ($pdoTenant instanceof PDO) {
            try {
                $tenantDb = (string)($t['db_name'] ?? '');
                if ($tenantDb !== '' && bb_col_exists($pdoTenant, $tenantDb, 'config', 'ativo')) {
                    $pdoTenant->exec("UPDATE config SET ativo='Não'");
                }
            } catch (Throwable $e) {
                // não quebra
            }
        }

        $jaEnviouHojeBloqueio = false;
        if ($hasUltimoLembrete) {
            $ultimo = trim((string)($t['ultimo_lembrete'] ?? ''));
            $jaEnviouHojeBloqueio = ($ultimo === $hojeTenant);
        }

        // Mesmo quando o tenant já está bloqueado, mantém o envio diário do link de pagamento
        // enquanto a assinatura continuar vencida. Isso não altera a regra de bloqueio/liberação:
        // apenas garante que o cliente receba o link para regularizar.
        if (!$jaEnviouHojeBloqueio && $telefone !== '') {
            $faturaId = bb_market_get_or_create_pending_fatura($pdoMaster, $tenantId, $vencimento, $valor);
            $link = bb_montar_link_pagamento_marketplace($baseUrl, $faturaId);

            $tituloMensagem = $jaBloqueado
                ? "⛔ *Assinatura BarberBot Bloqueada*"
                : "⛔ *Assinatura BarberBot Bloqueada*";

            $mensagem = bb_montar_mensagem_assinatura_link(
                $tituloMensagem,
                $nome,
                $slug,
                $vencimento,
                $valor,
                $link,
                "⚠️ Sua assinatura está vencida há mais de {$diasGraca} dia(s).\n" .
                "O acesso fica bloqueado até a regularização. Ao efetuar o pagamento, o sistema libera automaticamente.\n"
            );

            $enviado = bb_send_whatsapp($telefone, $mensagem, $apiEnvio, $tokenEnvio, $instanciaEnvio);

            if ($enviado && $hasUltimoLembrete) {
                $up = $pdoMaster->prepare("UPDATE `{$tenantsTable}` SET ultimo_lembrete = ? WHERE id = ?");
                $up->execute([$hojeTenant, $tenantId]);
            }
        }
    }
}

echo "OK\n";
