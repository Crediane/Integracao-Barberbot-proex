<?php
/**
 * /public_html/cron/cron_guard.php
 *
 * Proteção comum das rotinas cron:
 * - execução por CLI permanece liberada;
 * - execução pelo navegador exige config/marketplace.php -> cron.token;
 * - fornece validação explícita do banco aberto antes de qualquer operação.
 */

if (!function_exists('bb_cron_guard_config')) {
    function bb_cron_guard_config(): array
    {
        static $config = null;

        if (is_array($config)) {
            return $config;
        }

        $arquivo = __DIR__ . '/../config/marketplace.php';
        if (!is_file($arquivo)) {
            $config = [];
            return $config;
        }

        $carregado = include $arquivo;
        $config = is_array($carregado) ? $carregado : [];

        return $config;
    }
}

if (!function_exists('bb_cron_guard_http_access')) {
    function bb_cron_guard_http_access(): void
    {
        if (PHP_SAPI === 'cli') {
            return;
        }

        $config = bb_cron_guard_config();
        $esperado = trim((string)($config['cron']['token'] ?? ''));

        $informado = '';
        if (isset($_GET['token'])) {
            $informado = trim((string)$_GET['token']);
        } elseif (isset($_POST['token'])) {
            $informado = trim((string)$_POST['token']);
        } elseif (!empty($_SERVER['HTTP_X_CRON_TOKEN'])) {
            $informado = trim((string)$_SERVER['HTTP_X_CRON_TOKEN']);
        }

        if ($esperado === '' || $informado === '' || !hash_equals($esperado, $informado)) {
            http_response_code(403);
            exit("403\n");
        }
    }
}

if (!function_exists('bb_cron_guard_database_name')) {
    function bb_cron_guard_database_name(PDO $pdo): string
    {
        try {
            return trim((string)$pdo->query('SELECT DATABASE()')->fetchColumn());
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('bb_cron_guard_database_matches')) {
    function bb_cron_guard_database_matches(PDO $pdo, string $expectedDatabase): bool
    {
        $expectedDatabase = trim($expectedDatabase);
        if ($expectedDatabase === '') {
            return false;
        }

        $actualDatabase = bb_cron_guard_database_name($pdo);
        return $actualDatabase !== '' && strcasecmp($actualDatabase, $expectedDatabase) === 0;
    }
}

if (!function_exists('bb_cron_guard_tenant_context')) {
    function bb_cron_guard_tenant_context(PDO $pdo, string $slug, string $expectedDatabase = ''): bool
    {
        $slug = strtolower(trim($slug));
        $slug = (string)preg_replace('/[^a-z0-9_-]/', '', $slug);

        if ($slug === '') {
            return false;
        }

        $sessionSlug = strtolower(trim((string)($_SESSION['tenant_slug'] ?? '')));
        $sessionSlug = (string)preg_replace('/[^a-z0-9_-]/', '', $sessionSlug);

        if ($sessionSlug !== '' && $sessionSlug !== $slug) {
            return false;
        }

        if ($expectedDatabase === '') {
            $expectedDatabase = trim((string)($_SESSION['tenant_db'] ?? ''));
        }

        return bb_cron_guard_database_matches($pdo, $expectedDatabase);
    }
}

bb_cron_guard_http_access();
