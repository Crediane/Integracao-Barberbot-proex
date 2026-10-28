<?php
header('Content-Type: text/plain; charset=utf-8');
set_time_limit(600);
require_once __DIR__ . '/cron_guard.php';
require_once __DIR__ . '/timezone_cron.php';
bb_cron_aplicar_timezone();

// ===== Parâmetro opcional ?loja=slug ou CLI --loja=slug =====
$__bb_loja_cli = '';
if (PHP_SAPI === 'cli' && isset($argv) && is_array($argv)) {
    foreach ($argv as $__a) {
        if (strpos($__a, '--loja=') === 0) { $__bb_loja_cli = substr($__a, 7); break; }
        if (strpos($__a, 'loja=') === 0)   { $__bb_loja_cli = substr($__a, 5); break; }
    }
}
$__bb_loja = '';
if (!empty($_GET['loja'])) { $__bb_loja = (string)$_GET['loja']; }
elseif (!empty($_POST['loja'])) { $__bb_loja = (string)$_POST['loja']; }
elseif ($__bb_loja_cli !== '') { $__bb_loja = (string)$__bb_loja_cli; }
$__bb_loja = preg_replace('/[^a-z0-9_-]/i', '', $__bb_loja);
if ($__bb_loja !== '') {
    $_GET['loja'] = $__bb_loja;
}

// ===== Configuração do marketplace / tenant =====
// Mantém o padrão do sistema, mas deixa o cron autossuficiente para:
// - abrir o banco do marketplace
// - descriptografar db_pass quando vier em enc:...
// - cair na senha padrão do tenant quando a chave não estiver disponível
$cfgMaster   = [];
$cfgTenant   = [];
$cfgSecurity = [];

$__bb_market_cfg_file = __DIR__ . '/../config/marketplace.php';
if (is_file($__bb_market_cfg_file)) {
    $__bb_market_cfg = include $__bb_market_cfg_file;
    if (is_array($__bb_market_cfg)) {
        $cfgMaster   = (isset($__bb_market_cfg['master']) && is_array($__bb_market_cfg['master'])) ? $__bb_market_cfg['master'] : [];
        $cfgTenant   = (isset($__bb_market_cfg['tenant']) && is_array($__bb_market_cfg['tenant'])) ? $__bb_market_cfg['tenant'] : [];
        $cfgSecurity = (isset($__bb_market_cfg['security']) && is_array($__bb_market_cfg['security'])) ? $__bb_market_cfg['security'] : [];
    }
}

$__bb_tenant_crypto_file = __DIR__ . '/../sistema/tenant_crypto.php';
if (is_file($__bb_tenant_crypto_file)) {
    require_once $__bb_tenant_crypto_file;
}

if ($__bb_loja !== '') {
    require_once __DIR__ . '/../sistema/conexao.php';
}

function bbm_log(string $msg): void {
    error_log('[cron_marketing] ' . $msg);
}

function bbm_only_digits(string $s): string {
    return preg_replace('/\D+/', '', $s);
}

function bbm_set_utf8(PDO $pdo): void {
    $pdo->exec("SET NAMES utf8mb4");
    $pdo->exec("SET CHARACTER SET utf8mb4");
    $pdo->exec("SET SESSION collation_connection = 'utf8mb4_unicode_ci'");
}

function bbm_columns(PDO $pdo, string $table): array {
    static $cache = [];
    $table = preg_replace('/[^a-z0-9_]/i', '', $table);
    if ($table === '') return [];
    $db = '';
    try {
        $db = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    } catch (Throwable $e) {
        $db = '';
    }
    $key = $db . '|' . $table;
    if (isset($cache[$key])) return $cache[$key];

    $cols = [];
    try {
        $st = $pdo->query("SHOW COLUMNS FROM `{$table}`");
        if ($st) {
            while ($r = $st->fetch(PDO::FETCH_ASSOC)) {
                if (!empty($r['Field'])) $cols[] = (string)$r['Field'];
            }
        }
    } catch (Throwable $e) {
        $cols = [];
    }
    $cache[$key] = $cols;
    return $cols;
}

function bbm_has_table(PDO $pdo, string $table): bool {
    return count(bbm_columns($pdo, $table)) > 0;
}

function bbm_has_column(PDO $pdo, string $table, string $column): bool {
    return in_array($column, bbm_columns($pdo, $table), true);
}

function bbm_is_send_status_success(?string $status): bool {
    $status = trim((string)$status);
    if ($status === '') return false;
    if ($status === 'Mensagem enviada com sucesso.' || $status === 'O número não existe' || $status === 'OK') {
        return true;
    }
    $statusLower = function_exists('mb_strtolower') ? mb_strtolower($status, 'UTF-8') : strtolower($status);
    return (strpos($statusLower, 'sucesso') !== false);
}

function bbm_extract_status_from_output(string $raw): string {
    $raw = trim($raw);
    if ($raw === '') return '';

    $json = json_decode($raw, true);
    if (is_array($json)) {
        if (!empty($json['message'])) return (string)$json['message'];
        if (!empty($json['status'])) return (string)$json['status'];
        if (array_key_exists('erro', $json)) {
            return empty($json['erro']) ? 'OK' : (string)($json['message'] ?? 'Erro no envio');
        }
    }
    return $raw;
}

function bbm_public_host(): string {
    global $url_sistema;
    if (!empty($_SERVER['HTTP_HOST'])) return (string)$_SERVER['HTTP_HOST'];
    if (!empty($url_sistema)) {
        $h = parse_url((string)$url_sistema, PHP_URL_HOST);
        if (!empty($h) && $h !== 'localhost') return (string)$h;
    }
    return 'jc.tec.br';
}

function bbm_root_url(): string {
    return 'https://' . bbm_public_host() . '/';
}

function bbm_base_painel(string $slug = ''): string {
    $root = bbm_root_url();
    if ($slug !== '') {
        return $root . 'loja/' . rawurlencode($slug) . '/painel/';
    }
    return $root . 'sistema/painel/';
}

function bbm_texto_path(): string {
    $candidates = [
        __DIR__ . '/texto.php',
        __DIR__ . '/../sistema/painel/apis/texto.php',
    ];
    foreach ($candidates as $p) {
        if (is_file($p)) return $p;
    }
    throw new RuntimeException('Arquivo texto.php não encontrado.');
}

function bbm_marketing_file_path(): string {
    $p = __DIR__ . '/marketing_file.php';
    if (!is_file($p)) {
        throw new RuntimeException('Arquivo marketing_file.php não encontrado.');
    }
    return $p;
}

function bbm_send_text(array $ctx): string {
    $status_mensagem = '';
    extract($ctx, EXTR_SKIP);
    ob_start();
    require bbm_texto_path();
    $raw = ob_get_clean();
    if (trim((string)$status_mensagem) !== '') {
        return (string)$status_mensagem;
    }
    return bbm_extract_status_from_output((string)$raw);
}

function bbm_send_media(array $ctx): string {
    $status_mensagem = '';
    extract($ctx, EXTR_SKIP);
    ob_start();
    require bbm_marketing_file_path();
    $raw = ob_get_clean();
    if (trim((string)$status_mensagem) !== '') {
        return (string)$status_mensagem;
    }
    return bbm_extract_status_from_output((string)$raw);
}

function bbm_safe_value(array $row, string $key, $default = '') {
    return array_key_exists($key, $row) ? $row[$key] : $default;
}

function bbm_connect_master(): ?PDO {
    global $cfgMaster;
    if (empty($cfgMaster['host']) || empty($cfgMaster['db']) || empty($cfgMaster['user'])) {
        return null;
    }
    try {
        $pdo = new PDO(
            "mysql:host={$cfgMaster['host']};dbname={$cfgMaster['db']};charset=utf8mb4",
            (string)$cfgMaster['user'],
            (string)($cfgMaster['pass'] ?? ''),
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        bbm_set_utf8($pdo);
        if (!bb_cron_guard_database_matches($pdo, (string)$cfgMaster['db'])) {
            throw new RuntimeException('Banco global do Marketplace não confirmado.');
        }
        return $pdo;
    } catch (Throwable $e) {
        bbm_log('Falha ao conectar no master: ' . $e->getMessage());
        return null;
    }
}

function bbm_find_active_tenants(PDO $pdoMaster): array {
    global $cfgMaster;
    $table = preg_replace('/[^a-z0-9_]/i', '', (string)($cfgMaster['table'] ?? 'tenants'));
    if ($table === '') $table = 'tenants';
    if (!bbm_has_table($pdoMaster, $table)) return [];

    $cols = bbm_columns($pdoMaster, $table);
    if (!in_array('slug', $cols, true) || !in_array('db_name', $cols, true)) {
        return [];
    }

    $where = '1=1';
    if (in_array('ativo', $cols, true)) {
        $where = '`ativo` = 1';
    } elseif (in_array('status', $cols, true)) {
        $where = "`status` = 'active'";
    }

    try {
        $st = $pdoMaster->query("SELECT * FROM `{$table}` WHERE {$where} ORDER BY id ASC");
        $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];
        return is_array($rows) ? $rows : [];
    } catch (Throwable $e) {
        bbm_log('Falha ao listar lojas ativas: ' . $e->getMessage());
        return [];
    }
}

function bbm_tenant_fallback_user(array $tenant): string {
    global $cfgTenant;
    if (!empty($tenant['db_user'])) {
        return (string)$tenant['db_user'];
    }
    if (!empty($cfgTenant['db_user_default'])) {
        return (string)$cfgTenant['db_user_default'];
    }
    if (!empty($cfgTenant['user'])) {
        return (string)$cfgTenant['user'];
    }
    return '';
}

function bbm_tenant_fallback_pass(): string {
    global $cfgTenant;
    if (!empty($cfgTenant['db_pass_default'])) {
        return (string)$cfgTenant['db_pass_default'];
    }
    if (!empty($cfgTenant['pass'])) {
        return (string)$cfgTenant['pass'];
    }
    return '';
}

function bbm_tenant_resolve_pass(array $tenant): string {
    global $cfgSecurity;

    $pass = !empty($tenant['db_pass']) ? (string)$tenant['db_pass'] : '';
    $passFallback = bbm_tenant_fallback_pass();

    if ($pass === '') {
        return $passFallback;
    }

    if (strpos($pass, 'enc:') !== 0) {
        return $pass;
    }

    $secret = (string)($cfgSecurity['tenant_secret'] ?? '');
    if ($secret !== '' && function_exists('bb_decrypt_str')) {
        try {
            $dec = (string)bb_decrypt_str($pass, $secret);
            if ($dec !== '') {
                return $dec;
            }
        } catch (Throwable $e) {
            // segue para fallback abaixo
        }
    }

    return $passFallback;
}

function bbm_connect_tenant_from_row(array $tenant): ?PDO {
    global $cfgTenant;

    $host = !empty($tenant['db_host']) ? (string)$tenant['db_host'] : (string)($cfgTenant['db_host'] ?? ($cfgTenant['host'] ?? 'localhost'));
    $db   = !empty($tenant['db_name']) ? (string)$tenant['db_name'] : '';
    $user = bbm_tenant_fallback_user($tenant);
    $pass = bbm_tenant_resolve_pass($tenant);

    if ($db === '' || $user === '') return null;

    try {
        $pdo = new PDO(
            "mysql:host={$host};dbname={$db};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
        bbm_set_utf8($pdo);
        if (!bb_cron_guard_database_matches($pdo, $db)) {
            throw new RuntimeException('Banco individual diferente do tenant mapeado.');
        }
        return $pdo;
    } catch (Throwable $e) {
        $slug = (string)($tenant['slug'] ?? $db);
        bbm_log("Falha ao conectar loja [{$slug}]: " . $e->getMessage());
        return null;
    }
}

function bbm_current_db_name(PDO $pdo): string {
    try {
        return (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    } catch (Throwable $e) {
        return '';
    }
}

function bbm_process_store(PDO $pdoStore, string $slug = '', string $label = 'master/local'): int {
    global $api, $token, $instancia;

    try {
        bbm_set_utf8($pdoStore);
    } catch (Throwable $e) {
        // segue mesmo assim
    }

    if (!bbm_has_table($pdoStore, 'disparos') || !bbm_has_table($pdoStore, 'marketing')) {
        return 0;
    }

    $dispCols = bbm_columns($pdoStore, 'disparos');
    if (!in_array('id', $dispCols, true) || !in_array('campanha', $dispCols, true) || !in_array('telefone', $dispCols, true)
        || !in_array('hora', $dispCols, true) || !in_array('data_disparo', $dispCols, true)) {
        bbm_log("Tabela disparos incompatível [{$label}].");
        return 0;
    }

    $hasStatus = in_array('status', $dispCols, true);

    $cfgRow = [];
    if (bbm_has_table($pdoStore, 'config')) {
        try {
            $stCfg = $pdoStore->query('SELECT * FROM config LIMIT 1');
            $cfgRow = $stCfg ? (array)$stCfg->fetch(PDO::FETCH_ASSOC) : [];
            if (!empty($cfgRow['timezone'])) {
                bb_cron_aplicar_timezone($cfgRow['timezone']);
            }
        } catch (Throwable $e) {
            $cfgRow = [];
        }
    }

    $today = bb_cron_data_hoje();
    $nowTime = bb_cron_hora_agora();

    $rows = [];
    try {
        if ($hasStatus) {
            $pdoStore->beginTransaction();

            $sql = "SELECT id FROM disparos WHERE data_disparo = :d AND hora <= :h AND status = 'Pendente' ORDER BY hora ASC, id ASC LIMIT 200";
            $st = $pdoStore->prepare($sql);
            $st->execute([':d' => $today, ':h' => $nowTime]);
            $ids = $st->fetchAll(PDO::FETCH_COLUMN);

            if (!$ids) {
                $pdoStore->commit();
                return 0;
            }

            $in = implode(',', array_fill(0, count($ids), '?'));
            $up = $pdoStore->prepare("UPDATE disparos SET status = 'Processando' WHERE id IN ({$in}) AND status = 'Pendente'");
            $up->execute($ids);

            $sel = $pdoStore->prepare("SELECT * FROM disparos WHERE id IN ({$in}) AND status = 'Processando'");
            $sel->execute($ids);
            $rows = $sel->fetchAll(PDO::FETCH_ASSOC);

            $pdoStore->commit();
        } else {
            $sql = "SELECT * FROM disparos WHERE data_disparo = :d AND hora <= :h ORDER BY hora ASC, id ASC LIMIT 200";
            $st = $pdoStore->prepare($sql);
            $st->execute([':d' => $today, ':h' => $nowTime]);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        }
    } catch (Throwable $e) {
        if ($hasStatus && $pdoStore->inTransaction()) {
            $pdoStore->rollBack();
        }
        bbm_log("Falha ao selecionar lote [{$label}]: " . $e->getMessage());
        return 0;
    }

    if (!$rows) {
        return 0;
    }

    $apiLocal       = (string)($cfgRow['api'] ?? $api ?? '');
    $tokenLocal     = (string)($cfgRow['token'] ?? $token ?? '');
    $instanciaLocal = (string)($cfgRow['instancia'] ?? $instancia ?? '');

    $campaignCols = bbm_columns($pdoStore, 'marketing');
    $campaignCache = [];
    $idsToDelete = [];
    $idsToReset = [];
    $sendsPerCampaign = [];
    $basePainel = bbm_base_painel($slug);

    foreach ($rows as $row) {
        $disparoId = (int)($row['id'] ?? 0);
        if ($disparoId <= 0) continue;

        $campaignId = (int)($row['campanha'] ?? 0);
        $nomeCliente = (string)($row['nome'] ?? '');
        $telefoneCliente = (string)($row['telefone'] ?? '');

        $tel = bbm_only_digits($telefoneCliente);
        if ($tel === '' || strlen($tel) < 10) {
            $idsToDelete[] = $disparoId;
            continue;
        }
        if (strpos($tel, '55') !== 0) {
            $tel = '55' . $tel;
        }

        if ($apiLocal === 'Não' || $apiLocal === '') {
            if ($hasStatus) $idsToReset[] = $disparoId;
            continue;
        }

        if (!isset($campaignCache[$campaignId])) {
            try {
                $st = $pdoStore->prepare('SELECT * FROM marketing WHERE id = :id LIMIT 1');
                $st->execute([':id' => $campaignId]);
                $campaign = $st->fetch(PDO::FETCH_ASSOC);
            } catch (Throwable $e) {
                $campaign = false;
            }

            if (!$campaign) {
                $idsToDelete[] = $disparoId;
                continue;
            }
            $campaignCache[$campaignId] = $campaign;
        }
        $campaign = $campaignCache[$campaignId];

        $msg1 = trim((string)bbm_safe_value($campaign, 'mensagem', ''));
        $msg2 = trim((string)bbm_safe_value($campaign, 'mensagem2', ''));
        $mensagemDisparo = ($msg1 !== '' && $msg2 !== '') ? (rand(0, 1) ? $msg1 : $msg2) : ($msg1 !== '' ? $msg1 : $msg2);

        $isSuccess = false;
        $statusMsg = '';

        try {
            if ($mensagemDisparo !== '') {
                $statusMsg = bbm_send_text([
                    'api'        => $apiLocal,
                    'token'      => $tokenLocal,
                    'instancia'  => $instanciaLocal,
                    'telefone'   => $tel,
                    'mensagem'   => $mensagemDisparo,
                    'nomeCliente'=> $nomeCliente,
                ]);
                if (bbm_is_send_status_success($statusMsg)) {
                    $isSuccess = true;
                }
            }

            foreach (['arquivo', 'audio', 'documento'] as $fileCol) {
                if (!in_array($fileCol, $campaignCols, true)) continue;
                $file = trim((string)bbm_safe_value($campaign, $fileCol, ''));
                if ($file === '' || $file === 'sem-foto.png') continue;

                $statusFile = bbm_send_media([
                    'api'        => $apiLocal,
                    'token'      => $tokenLocal,
                    'instancia'  => $instanciaLocal,
                    'telefone'   => $tel,
                    'mensagem'   => pathinfo($file, PATHINFO_BASENAME),
                    'url_envio'  => $basePainel . 'images/marketing/' . rawurlencode($file),
                    'nomeCliente'=> $nomeCliente,
                ]);
                if (bbm_is_send_status_success($statusFile)) {
                    $isSuccess = true;
                }
            }
        } catch (Throwable $e) {
            bbm_log("Falha no envio [{$label}] disparo {$disparoId}: " . $e->getMessage());
        }

        $deveRemover = false;
        if ($apiLocal === 'menuia') {
            $deveRemover = $isSuccess;
        } else {
            $deveRemover = true;
        }

        if ($deveRemover) {
            $idsToDelete[] = $disparoId;
            if (!isset($sendsPerCampaign[$campaignId])) $sendsPerCampaign[$campaignId] = 0;
            $sendsPerCampaign[$campaignId]++;
        } else {
            if ($hasStatus) $idsToReset[] = $disparoId;
        }

        usleep(150000);
    }

    if (!empty($idsToDelete)) {
        try {
            $in = implode(',', array_fill(0, count($idsToDelete), '?'));
            $del = $pdoStore->prepare("DELETE FROM disparos WHERE id IN ({$in})");
            $del->execute(array_values(array_unique($idsToDelete)));
        } catch (Throwable $e) {
            bbm_log("Falha ao excluir disparos [{$label}]: " . $e->getMessage());
        }
    }

    if ($hasStatus && !empty($idsToReset)) {
        $idsToReset = array_values(array_diff(array_unique($idsToReset), array_unique($idsToDelete)));
        if (!empty($idsToReset)) {
            try {
                $in = implode(',', array_fill(0, count($idsToReset), '?'));
                $rst = $pdoStore->prepare("UPDATE disparos SET status = 'Pendente' WHERE id IN ({$in})");
                $rst->execute($idsToReset);
            } catch (Throwable $e) {
                bbm_log("Falha ao restaurar status [{$label}]: " . $e->getMessage());
            }
        }
    }

    if (!empty($sendsPerCampaign)) {
        $hasEnvios = in_array('envios', $campaignCols, true);
        $hasUltimoEnvio = in_array('ultimo_envio', $campaignCols, true);

        if ($hasEnvios || $hasUltimoEnvio) {
            foreach ($sendsPerCampaign as $campaignId => $count) {
                try {
                    if ($hasEnvios && $hasUltimoEnvio) {
                        $st = $pdoStore->prepare('UPDATE marketing SET envios = COALESCE(envios,0) + ?, ultimo_envio = ? WHERE id = ?');
                        $st->execute([$count, bb_cron_data_hora_agora(), $campaignId]);
                    } elseif ($hasEnvios) {
                        $st = $pdoStore->prepare('UPDATE marketing SET envios = COALESCE(envios,0) + ? WHERE id = ?');
                        $st->execute([$count, $campaignId]);
                    } else {
                        $st = $pdoStore->prepare('UPDATE marketing SET ultimo_envio = ? WHERE id = ?');
                        $st->execute([bb_cron_data_hora_agora(), $campaignId]);
                    }
                } catch (Throwable $e) {
                    bbm_log("Falha ao atualizar campanha {$campaignId} [{$label}]: " . $e->getMessage());
                }
            }
        }
    }

    return count($rows);
}

$total = 0;
$processedDbNames = [];

try {
    // ===== Loja específica =====
    if ($__bb_loja !== '') {
        $pdoTarget = null;

        if (function_exists('conectarBancoPorSlug')) {
            try {
                $pdoTarget = conectarBancoPorSlug($__bb_loja);
            } catch (Throwable $e) {
                $pdoTarget = null;
            }
        }

        $tenantDbEsperado = trim((string)($_SESSION['tenant_db'] ?? ''));
        if ($pdoTarget instanceof PDO && !bb_cron_guard_tenant_context($pdoTarget, $__bb_loja, $tenantDbEsperado)) {
            $pdoTarget = null;
        }

        if ($pdoTarget instanceof PDO) {
            $total += bbm_process_store($pdoTarget, $__bb_loja, $__bb_loja);
        }

        echo (string)$total;
        exit;
    }

    // O banco global apenas localiza tenants. Nunca processa disparos como se fosse uma loja.

    // ===== Processa todas as lojas ativas =====
    $pdoMaster = bbm_connect_master();
    if ($pdoMaster instanceof PDO) {
        $tenants = bbm_find_active_tenants($pdoMaster);
        foreach ($tenants as $tenant) {
            $slug = preg_replace('/[^a-z0-9_-]/i', '', (string)($tenant['slug'] ?? ''));
            if ($slug === '') continue;

            bb_cron_aplicar_timezone(bb_cron_timezone_de_linha($tenant));

            $pdoTenant = bbm_connect_tenant_from_row($tenant);
            if (!$pdoTenant) continue;

            $dbTenant = bbm_current_db_name($pdoTenant);
            if ($dbTenant !== '' && in_array($dbTenant, $processedDbNames, true)) {
                continue;
            }
            if ($dbTenant !== '') $processedDbNames[] = $dbTenant;

            $total += bbm_process_store($pdoTenant, $slug, $slug);
        }
    }

    echo (string)$total;
} catch (Throwable $e) {
    bbm_log('Falha geral: ' . $e->getMessage());
    echo (string)$total;
}
