<?php
// Bootstrap exclusivo do painel MASTER do Marketplace.
// Não usa HTTP_REFERER e não aceita contexto de loja/tenant.

if (defined('BB_MARKET_MASTER_BOOTSTRAP_LOADED')) {
    return;
}
define('BB_MARKET_MASTER_BOOTSTRAP_LOADED', true);

if (!function_exists('bb_market_master_abort')) {
    function bb_market_master_abort(string $mensagem, int $codigo = 403): void
    {
        http_response_code($codigo);

        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $isAjax = $requestedWith === 'xmlhttprequest'
            || strpos($accept, 'application/json') !== false
            || strpos($accept, 'text/plain') !== false
            || strpos((string)($_SERVER['REQUEST_URI'] ?? ''), '/paginas/marketplace/') !== false;

        if (!headers_sent()) {
            header('Content-Type: ' . ($isAjax ? 'text/plain' : 'text/html') . '; charset=utf-8');
        }

        if ($isAjax) {
            echo $mensagem;
            exit;
        }

        echo '<div class="alert alert-danger" role="alert">'
            . htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8')
            . '</div>';
        exit;
    }
}

$__bbMarketReqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$__bbMarketHasLojaParam = !empty($_GET['loja']) || !empty($_POST['loja']);
$__bbMarketIsLojaPath = (bool)preg_match('~(?:^|/)loja/([a-z0-9_-]+)(?:/|$)~i', $__bbMarketReqPath);

if ($__bbMarketHasLojaParam || $__bbMarketIsLojaPath) {
    bb_market_master_abort('Acesso negado: esta área pertence exclusivamente ao painel Master.', 403);
}

// O painel Master usa exclusivamente a sessão BB_MASTER.
if (session_status() === PHP_SESSION_ACTIVE && session_name() !== 'BB_MASTER') {
    session_write_close();
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_name('BB_MASTER');
    if (!empty($_COOKIE['BB_MASTER'])) {
        @session_id((string)$_COOKIE['BB_MASTER']);
    }
    @session_start();
}

if (session_name() !== 'BB_MASTER') {
    bb_market_master_abort('Sessão Master inválida. Atualize a página e faça login novamente.', 403);
}

$__bbMarketVerificar = __DIR__ . '/../../verificar.php';
if (!is_file($__bbMarketVerificar)) {
    bb_market_master_abort('Arquivo de verificação do painel não encontrado.', 500);
}
require_once $__bbMarketVerificar;

$cfgFile = __DIR__ . '/../../../../config/marketplace.php';
if (!is_file($cfgFile)) {
    bb_market_master_abort('Arquivo config/marketplace.php não encontrado.', 500);
}

$cfg = require $cfgFile;
if (!is_array($cfg) || empty($cfg['master']) || !is_array($cfg['master'])) {
    bb_market_master_abort('Configuração do Banco Global/Administrativo inválida.', 500);
}

$masterHost = trim((string)($cfg['master']['host'] ?? ''));
$masterDb = preg_replace('/[^a-z0-9_]/i', '', (string)($cfg['master']['db'] ?? ''));
$masterUser = trim((string)($cfg['master']['user'] ?? ''));
$masterPass = (string)($cfg['master']['pass'] ?? '');

if ($masterHost === '' || $masterDb === '' || $masterUser === '') {
    bb_market_master_abort('Credenciais do Banco Global/Administrativo incompletas.', 500);
}

try {
    $pdoM = new PDO(
        "mysql:host={$masterHost};dbname={$masterDb};charset=utf8mb4",
        $masterUser,
        $masterPass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    $databaseAtual = (string)$pdoM->query('SELECT DATABASE()')->fetchColumn();
    if ($databaseAtual !== $masterDb) {
        bb_market_master_abort('A conexão ativa não corresponde ao Banco Global/Administrativo configurado.', 500);
    }
} catch (Throwable $e) {
    bb_market_master_abort('Falha ao conectar no Banco Global/Administrativo.', 500);
}

$tenantsTable = preg_replace('/[^a-z0-9_]/i', '', (string)($cfg['master']['table'] ?? 'tenants'));
if ($tenantsTable === '') {
    $tenantsTable = 'tenants';
}

if (!function_exists('bb_market_master_table_exists')) {
    function bb_market_master_table_exists(PDO $pdo, string $table): bool
    {
        try {
            $st = $pdo->prepare(
                'SELECT 1 FROM INFORMATION_SCHEMA.TABLES '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t LIMIT 1'
            );
            $st->execute([':t' => $table]);
            return (bool)$st->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('bb_market_master_columns')) {
    function bb_market_master_columns(PDO $pdo, string $table): array
    {
        try {
            $st = $pdo->prepare(
                'SELECT COLUMN_NAME FROM INFORMATION_SCHEMA.COLUMNS '
                . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t'
            );
            $st->execute([':t' => $table]);
            $rows = $st->fetchAll(PDO::FETCH_COLUMN);
        } catch (Throwable $e) {
            $rows = [];
        }

        $map = [];
        foreach ((array)$rows as $column) {
            $map[(string)$column] = true;
        }
        return $map;
    }
}

if (!function_exists('bb_market_master_clean_host')) {
    function bb_market_master_clean_host(string $host): string
    {
        $host = strtolower(trim($host));
        $host = preg_replace('~^https?://~i', '', $host);
        $host = preg_replace('~/.*$~', '', $host);
        $host = preg_replace('~:\d+$~', '', $host);
        return trim((string)$host, ". \t\n\r\0\x0B");
    }
}

if (!function_exists('bb_market_master_base_url')) {
    function bb_market_master_base_url(array $config): string
    {
        $base = '';
        if (!empty($config['base_url'])) {
            $base = (string)$config['base_url'];
        } elseif (!empty($config['app']['base_url'])) {
            $base = (string)$config['app']['base_url'];
        }

        if ($base === '') {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
                $scheme = 'https';
            }
            $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
            if ($host !== '') {
                $base = $scheme . '://' . $host;
            }
        }

        return rtrim($base, '/');
    }
}
