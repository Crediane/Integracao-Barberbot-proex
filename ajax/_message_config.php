<?php
/**
 * Resolve o banco de configuração usado pelos envios de mensagem.
 *
 * - TENANT confirmado: usa o próprio banco individual já aberto.
 * - MASTER/SITE global: usa exclusivamente auth_master.
 * - Nunca consulta config no banco do Marketplace nem em default_db de tenant.
 * - Não troca nem inicia sessão.
 */

if (!function_exists('bb_message_db_name')) {
    function bb_message_db_name($pdo): string
    {
        try {
            if ($pdo instanceof PDO) {
                $stmt = $pdo->query('SELECT DATABASE()');
                $db = $stmt ? $stmt->fetchColumn() : '';
                return trim((string)$db);
            }
        } catch (Throwable $e) {
        }

        return '';
    }
}

if (!function_exists('bb_message_marketplace_config')) {
    function bb_message_marketplace_config(): array
    {
        $arquivo = __DIR__ . '/../config/marketplace.php';
        if (!is_file($arquivo)) {
            return [];
        }

        $config = require $arquivo;
        return is_array($config) ? $config : [];
    }
}

if (!function_exists('bb_message_config_pdo')) {
    function bb_message_config_pdo($pdoAtual = null): ?PDO
    {
        $config = bb_message_marketplace_config();
        $auth = isset($config['auth_master']) && is_array($config['auth_master'])
            ? $config['auth_master']
            : [];

        $authDb = trim((string)($auth['db'] ?? ''));
        $dbAtual = bb_message_db_name($pdoAtual);

        $sessionName = (string)session_name();
        $tenantDb = trim((string)($_SESSION['tenant_db'] ?? ''));
        $tenantSlug = trim((string)($_SESSION['tenant_slug'] ?? ''));

        $tenantConfirmado = (
            $pdoAtual instanceof PDO
            && strpos($sessionName, 'BB_TENANT') === 0
            && $tenantSlug !== ''
            && $tenantDb !== ''
            && $dbAtual !== ''
            && hash_equals($tenantDb, $dbAtual)
        );

        if ($tenantConfirmado) {
            return $pdoAtual;
        }

        if ($pdoAtual instanceof PDO && $authDb !== '' && $dbAtual !== '' && hash_equals($authDb, $dbAtual)) {
            return $pdoAtual;
        }

        $host = trim((string)($auth['host'] ?? ''));
        $user = (string)($auth['user'] ?? '');
        $pass = (string)($auth['pass'] ?? '');

        if ($host === '' || $authDb === '' || $user === '') {
            return null;
        }

        try {
            $pdoGlobal = new PDO(
                'mysql:host=' . $host . ';dbname=' . $authDb . ';charset=utf8mb4',
                $user,
                $pass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            $dbConfirmado = bb_message_db_name($pdoGlobal);
            if ($dbConfirmado === '' || !hash_equals($authDb, $dbConfirmado)) {
                return null;
            }

            return $pdoGlobal;
        } catch (Throwable $e) {
            return null;
        }
    }
}

if (!function_exists('bb_message_context_label')) {
    function bb_message_context_label($pdoAtual = null): string
    {
        $sessionName = (string)session_name();
        $dbAtual = bb_message_db_name($pdoAtual);

        if (strpos($sessionName, 'BB_TENANT') === 0) {
            $tenantDb = trim((string)($_SESSION['tenant_db'] ?? ''));
            if ($tenantDb !== '' && $dbAtual !== '' && hash_equals($tenantDb, $dbAtual)) {
                return 'tenant';
            }
        }

        $config = bb_message_marketplace_config();
        $authDb = trim((string)($config['auth_master']['db'] ?? ''));
        if ($authDb !== '' && $dbAtual !== '' && hash_equals($authDb, $dbAtual)) {
            return $sessionName === 'BB_MASTER' ? 'master' : 'site';
        }

        return 'nao_confirmado';
    }
}
