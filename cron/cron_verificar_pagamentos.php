<?php
/**
 * /public_html/cron/cron_verificar_pagamentos.php
 *
 * Regra preservada:
 * - consulta contas em receber não pagas que possuem ref_pix;
 * - consulta o status no Mercado Pago;
 * - quando approved, executa o arquivo oficial baixar_conta.php.
 *
 * Isolamento:
 * - loja=<slug> processa somente o Banco Individual mapeado para a barbearia;
 * - all=1 lista os tenants no Banco Global do Marketplace e abre cada Banco Individual;
 * - sem slug e sem all=1 não consulta tabelas operacionais no Banco Master.
 */

require_once __DIR__ . '/cron_guard.php';
if (PHP_SAPI !== 'cli') {
    @ob_start();
}
require_once __DIR__ . '/timezone_cron.php';
bb_cron_aplicar_timezone();

@set_time_limit(0);

$tabela = 'receber';
$id_usuario = 0;
$arquivo_baixar = __DIR__ . '/../pagamentos/baixar_conta.php';

if (!is_file($arquivo_baixar)) {
    http_response_code(500);
    echo "Erro: baixar_conta.php não encontrado.\n";
    exit;
}

if (!function_exists('bb_cron_clean_slug')) {
    function bb_cron_clean_slug($value): string
    {
        $value = strtolower(trim((string)$value));
        return (string)preg_replace('/[^a-z0-9_-]/', '', $value);
    }
}

if (!function_exists('bb_cron_cli_arg')) {
    function bb_cron_cli_arg(string $key): ?string
    {
        if (PHP_SAPI !== 'cli' || empty($_SERVER['argv']) || !is_array($_SERVER['argv'])) {
            return null;
        }

        foreach ($_SERVER['argv'] as $arg) {
            $arg = (string)$arg;
            if (strpos($arg, '--' . $key . '=') === 0) {
                return substr($arg, strlen($key) + 3);
            }
            if (strpos($arg, $key . '=') === 0) {
                return substr($arg, strlen($key) + 1);
            }
        }

        return null;
    }
}

if (!function_exists('bb_cron_detect_slug')) {
    function bb_cron_detect_slug(): string
    {
        $slug = '';

        if (!empty($_GET['loja'])) {
            $slug = (string)$_GET['loja'];
        } elseif (!empty($_POST['loja'])) {
            $slug = (string)$_POST['loja'];
        } else {
            $cliLoja = bb_cron_cli_arg('loja');
            $cliSlug = bb_cron_cli_arg('slug');

            if ($cliLoja !== null && $cliLoja !== '') {
                $slug = $cliLoja;
            } elseif ($cliSlug !== null && $cliSlug !== '') {
                $slug = $cliSlug;
            } else {
                $reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
                if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $reqPath, $m)) {
                    $slug = (string)$m[1];
                }
            }
        }

        return bb_cron_clean_slug($slug);
    }
}

if (!function_exists('bb_cron_detect_all')) {
    function bb_cron_detect_all(): bool
    {
        $all = null;

        if (isset($_GET['all'])) {
            $all = $_GET['all'];
        } elseif (isset($_POST['all'])) {
            $all = $_POST['all'];
        } else {
            $all = bb_cron_cli_arg('all');
        }

        if ($all === null) {
            return false;
        }

        return in_array(strtolower(trim((string)$all)), array('1', 'true', 'on', 'yes', 'sim'), true);
    }
}

if (!function_exists('bb_cron_switch_tenant_session')) {
    function bb_cron_switch_tenant_session(string $slug): bool
    {
        $slug = bb_cron_clean_slug($slug);
        if ($slug === '') {
            return false;
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            @session_write_close();
        }

        $sessionName = 'BB_TENANT_' . strtoupper(substr(sha1($slug), 0, 12));
        @session_name($sessionName);

        // Cron não reutiliza cookie de navegador nem sessão anterior de outra loja.
        @ini_set('session.use_cookies', '0');
        @ini_set('session.use_only_cookies', '0');
        @session_id('CRON' . strtoupper(substr(sha1($slug . microtime(true) . mt_rand()), 0, 24)));

        if (!@session_start()) {
            return false;
        }

        $_SESSION = array();
        $_GET['loja'] = $slug;

        return true;
    }
}

if (!function_exists('bb_cron_list_active_slugs')) {
    function bb_cron_list_active_slugs(array $cfgMaster): array
    {
        if (empty($cfgMaster['host']) || empty($cfgMaster['db']) || empty($cfgMaster['user'])) {
            return array();
        }

        try {
            $pdoMaster = new PDO(
                'mysql:host=' . $cfgMaster['host'] . ';dbname=' . $cfgMaster['db'] . ';charset=utf8mb4',
                (string)$cfgMaster['user'],
                (string)($cfgMaster['pass'] ?? ''),
                array(
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                )
            );

            if (!bb_cron_guard_database_matches($pdoMaster, (string)$cfgMaster['db'])) {
                return array();
            }

            $table = preg_replace('/[^a-z0-9_]/i', '', (string)($cfgMaster['table'] ?? 'tenants'));
            if ($table === '') {
                $table = 'tenants';
            }

            $where = '1=1';
            $stCols = $pdoMaster->prepare(
                "SELECT COLUMN_NAME
                   FROM INFORMATION_SCHEMA.COLUMNS
                  WHERE TABLE_SCHEMA = DATABASE()
                    AND TABLE_NAME = :t
                    AND COLUMN_NAME IN ('status','ativo')"
            );
            $stCols->execute(array(':t' => $table));
            $cols = $stCols->fetchAll(PDO::FETCH_COLUMN);

            if (in_array('ativo', $cols, true)) {
                $where = '`ativo` = 1';
            } elseif (in_array('status', $cols, true)) {
                $where = "`status` = 'active'";
            }

            $rows = $pdoMaster->query(
                "SELECT slug FROM `{$table}` WHERE slug IS NOT NULL AND slug <> '' AND {$where} ORDER BY id ASC"
            )->fetchAll(PDO::FETCH_ASSOC);

            $slugs = array();
            foreach ($rows as $row) {
                $tenantSlug = bb_cron_clean_slug($row['slug'] ?? '');
                if ($tenantSlug !== '') {
                    $slugs[] = $tenantSlug;
                }
            }

            return array_values(array_unique($slugs));
        } catch (Throwable $e) {
            return array();
        }
    }
}

if (!function_exists('bb_cron_mp_status')) {
    function bb_cron_mp_status(string $refPix, string $accessToken): string
    {
        $refPix = preg_replace('/\D+/', '', trim($refPix));
        $accessToken = trim($accessToken);

        if ($refPix === '' || $accessToken === '') {
            return '';
        }

        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://api.mercadopago.com/v1/payments/' . $refPix,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => 'GET',
            CURLOPT_HTTPHEADER => array(
                'accept: application/json',
                'content-type: application/json',
                'Authorization: Bearer ' . $accessToken,
            ),
            CURLOPT_CONNECTTIMEOUT => 10,
            CURLOPT_TIMEOUT => 20,
        ));

        $response = curl_exec($curl);
        $curlError = curl_error($curl);
        $httpCode = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
        curl_close($curl);

        if ($response === false || $curlError !== '' || $httpCode < 200 || $httpCode >= 300) {
            return '';
        }

        $resultado = json_decode((string)$response, true);
        return is_array($resultado) && isset($resultado['status'])
            ? trim((string)$resultado['status'])
            : '';
    }
}

if (!function_exists('bb_cron_prepare_payment_globals')) {
    function bb_cron_prepare_payment_globals(PDO $pdoTenant, string $slug, array $cfg): void
    {
        $GLOBALS['pdo'] = $pdoTenant;
        $GLOBALS['bb_pagamentos_slug'] = $slug;
        $GLOBALS['__bb_reqSlug'] = $slug;
        $GLOBALS['__bb_loja'] = $slug;
        $GLOBALS['bb_loja'] = $slug;
        $GLOBALS['loja'] = $slug;

        $GLOBALS['nome_sistema'] = (string)($cfg['nome'] ?? ($GLOBALS['nome_sistema'] ?? 'BarberBot software'));
        $GLOBALS['url_sistema'] = (string)($cfg['url_sistema'] ?? ($GLOBALS['url_sistema'] ?? ''));
        $GLOBALS['api'] = (string)($cfg['api'] ?? '');
        $GLOBALS['token'] = (string)($cfg['token'] ?? '');
        $GLOBALS['instancia'] = (string)($cfg['instancia'] ?? '');
        $GLOBALS['access_token_mp'] = (string)($cfg['access_token_mp'] ?? '');
        $GLOBALS['public_key_mp'] = (string)($cfg['public_key_mp'] ?? '');
        $GLOBALS['access_token'] = $GLOBALS['access_token_mp'];
        $GLOBALS['public_key'] = $GLOBALS['public_key_mp'];

        $_GET['loja'] = $slug;
        $_SESSION['loja'] = $slug;
    }
}

if (!function_exists('bb_cron_process_tenant_payments')) {
    function bb_cron_process_tenant_payments(string $slug, string $arquivoBaixar): array
    {
        global $pdo, $tabela, $id_usuario;
        global $bb_pagamentos_slug, $__bb_reqSlug, $__bb_loja, $bb_loja, $loja;
        global $nome_sistema, $url_sistema, $api, $token, $instancia;
        global $access_token_mp, $public_key_mp, $access_token, $public_key;

        $result = array(
            'slug' => $slug,
            'analisadas' => 0,
            'aprovadas' => 0,
            'erro' => '',
        );

        if (!bb_cron_switch_tenant_session($slug)) {
            $result['erro'] = 'Não foi possível iniciar a sessão isolada do tenant.';
            return $result;
        }

        if (!function_exists('conectarBancoPorSlug')) {
            $result['erro'] = 'Resolver de tenant indisponível.';
            return $result;
        }

        try {
            $pdoTenant = conectarBancoPorSlug($slug);
        } catch (Throwable $e) {
            $pdoTenant = null;
        }

        $expectedDb = trim((string)($_SESSION['tenant_db'] ?? ''));
        if (!($pdoTenant instanceof PDO) || !bb_cron_guard_tenant_context($pdoTenant, $slug, $expectedDb)) {
            $result['erro'] = 'Banco individual não confirmado.';
            return $result;
        }

        try {
            $cfg = $pdoTenant->query('SELECT * FROM config LIMIT 1')->fetch(PDO::FETCH_ASSOC);
            $cfg = is_array($cfg) ? $cfg : array();
        } catch (Throwable $e) {
            $cfg = array();
        }

        if (!empty($cfg['timezone'])) {
            bb_cron_aplicar_timezone((string)$cfg['timezone']);
        }

        $accessToken = trim((string)($cfg['access_token_mp'] ?? ''));
        if ($accessToken === '') {
            $result['erro'] = 'Access token do Mercado Pago não configurado.';
            return $result;
        }

        bb_cron_prepare_payment_globals($pdoTenant, $slug, $cfg);
        $pdo = $pdoTenant;
        $tabela = 'receber';
        $id_usuario = 0;

        try {
            $stmt = $pdoTenant->prepare(
                "SELECT id, ref_pix
                   FROM receber
                  WHERE pago != 'Sim'
                    AND pago IS NOT NULL
                    AND ref_pix IS NOT NULL
                    AND ref_pix <> ''"
            );
            $stmt->execute();
            $contas = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (Throwable $e) {
            $result['erro'] = 'Falha ao consultar contas da barbearia.';
            return $result;
        }

        $result['analisadas'] = is_array($contas) ? count($contas) : 0;

        foreach ($contas as $conta) {
            $id = (int)($conta['id'] ?? 0);
            $ref_pix = trim((string)($conta['ref_pix'] ?? ''));

            if ($id <= 0 || $ref_pix === '') {
                continue;
            }

            $status_api = bb_cron_mp_status($ref_pix, $accessToken);
            if ($status_api !== 'approved') {
                continue;
            }

            // Reafirma o contexto antes da baixa.
            bb_cron_prepare_payment_globals($pdoTenant, $slug, $cfg);
            $pdo = $pdoTenant;
            require $arquivoBaixar;
            $result['aprovadas']++;
        }

        return $result;
    }
}

$slug = bb_cron_detect_slug();
$processarTodos = bb_cron_detect_all();

if ($slug === '' && !$processarTodos) {
    echo "Informe loja=<slug> ou all=1. Nenhum banco de barbearia foi selecionado.\n";
    exit;
}

// Carrega apenas o resolver central. O banco operacional sempre será confirmado por slug.
if ($slug !== '') {
    $_GET['loja'] = $slug;
}
require_once __DIR__ . '/../sistema/conexao.php';

$slugs = $slug !== ''
    ? array($slug)
    : bb_cron_list_active_slugs(isset($cfgMaster) && is_array($cfgMaster) ? $cfgMaster : array());

if (empty($slugs)) {
    echo "Nenhum tenant ativo encontrado.\n";
    exit;
}

$saidas = array();
foreach ($slugs as $tenantSlug) {
    $saidas[] = bb_cron_process_tenant_payments($tenantSlug, $arquivo_baixar);
}

foreach ($saidas as $saida) {
    echo '[tenant:' . $saida['slug'] . "]\n";
    if ($saida['erro'] !== '') {
        echo 'Erro: ' . $saida['erro'] . "\n\n";
        continue;
    }
    echo 'Total contas analisadas: ' . (int)$saida['analisadas'] . "\n";
    echo 'Total contas aprovadas: ' . (int)$saida['aprovadas'] . "\n\n";
}
