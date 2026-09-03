<?php
// /public_html/api/tenant.php
declare(strict_types=1);

/**
 * Guard absoluto: mesmo que incluam este arquivo por caminhos diferentes,
 * ele não executa 2x e não redeclara nada.
 */
if (defined('BB_TENANT_HELPER_V2')) {
    return;
}
define('BB_TENANT_HELPER_V2', 1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    // ===== Sessão isolada por contexto (MASTER x TENANT) =====
    // Evita que o cookie padrão (PHPSESSID) misture MASTER x TENANT.
    // Regra:
    // - /sistema (sem /loja/ e sem ?loja=) => BB_MASTER
    // - /loja/<slug>/... OU ?loja=...      => BB_TENANT
    // - demais                            => BB_SITE
    $__bb_reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $__bb_refPath = '';

    if (!empty($_SERVER['HTTP_REFERER'])) {
        $__bb_refPath = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: '';
    }

    // ===== Sessão TENANT por SLUG (evita misturar lojas no mesmo navegador) =====
    if (!function_exists('bb_sess_clean_slug')) {
        function bb_sess_clean_slug($v) {
            $v = strtolower(trim((string)$v));
            return preg_replace('/[^a-z0-9_-]/', '', $v);
        }
    }

    // garante $__bb_refPath quando o arquivo não define
    if (!isset($__bb_refPath)) {
        $__bb_refPath = '';
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $__bb_refPath = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: '';
        }
    }

    $__bb_reqSlug = '';
    if (!empty($_GET['loja'])) {
        $__bb_reqSlug = bb_sess_clean_slug($_GET['loja']);
    } elseif (!empty($_POST['loja'])) {
        $__bb_reqSlug = bb_sess_clean_slug($_POST['loja']);
    } else {
        if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath, $mSlug)) {
            $__bb_reqSlug = bb_sess_clean_slug($mSlug[1]);
        } elseif (!empty($__bb_refPath) && preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_refPath, $mSlug2)) {
            $__bb_reqSlug = bb_sess_clean_slug($mSlug2[1]);
        }
    }

    $__bb_tenantSessionName = 'BB_TENANT';
    if ($__bb_reqSlug !== '') {
        $__bb_tenantSessionName = 'BB_TENANT_' . strtoupper(substr(sha1($__bb_reqSlug), 0, 12));
    }

    $__bb_lojaByPath = (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath)
                    || (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_refPath);

    $__bb_hasLoja = $__bb_lojaByPath || !empty($_GET['loja']) || !empty($_POST['loja']);
    $__bb_isSistema = (strpos($__bb_reqPath, '/sistema') === 0) || (strpos($__bb_refPath, '/sistema') === 0);

    // ✅ Contexto ativo (resolve conflito quando o mesmo navegador tem BB_MASTER e BB_TENANT)
    $__bb_hasTenantCookie = false;
    foreach ($_COOKIE as $__bb_ck => $__bb_cv) {
        if ($__bb_ck === 'BB_TENANT' || strpos($__bb_ck, 'BB_TENANT_') === 0) {
            $__bb_hasTenantCookie = true;
            break;
        }
    }

    $__bb_hasMasterCookie = !empty($_COOKIE['BB_MASTER']);

    $__bb_ctx = '';
    if (!empty($_GET['ctx'])) {
        $__bb_ctx = strtolower(trim((string)$_GET['ctx']));
    } elseif (!empty($_COOKIE['BB_CTX'])) {
        $__bb_ctx = strtolower(trim((string)$_COOKIE['BB_CTX']));
    }

    if (!in_array($__bb_ctx, ['tenant', 'master'], true)) {
        $__bb_ctx = '';
    }

    // Força MASTER em /sistema quando NÃO é loja por PATH e NÃO tem ?loja=
    $__bb_forceMaster = ($__bb_isSistema && !$__bb_lojaByPath && empty($_GET['loja']) && empty($_POST['loja']) &&
        (
            ($__bb_ctx === 'master' && $__bb_hasMasterCookie) ||
            ($__bb_ctx !== 'tenant' && !($__bb_hasTenantCookie && !$__bb_hasMasterCookie))
        )
    );

    // Se é /sistema, não forçou master e existe cookie tenant => tratamos como TENANT
    if (!$__bb_hasLoja && $__bb_isSistema && !$__bb_forceMaster && $__bb_hasTenantCookie) {
        $__bb_hasLoja = true;
    }

    $__bb_desiredSession = $__bb_forceMaster ? 'BB_MASTER' : ($__bb_hasLoja ? $__bb_tenantSessionName : 'BB_SITE');

    // Inicia/ajusta a sessão corretamente (usa o cookie do nome escolhido)
    if (session_status() === PHP_SESSION_ACTIVE) {
        if (session_name() !== $__bb_desiredSession) {
            session_write_close();
            @session_name($__bb_desiredSession);
            if (!empty($_COOKIE[$__bb_desiredSession])) {
                @session_id((string)$_COOKIE[$__bb_desiredSession]);
            }
            @session_start();
        }
    } else {
        @session_name($__bb_desiredSession);
        if (!empty($_COOKIE[$__bb_desiredSession])) {
            @session_id((string)$_COOKIE[$__bb_desiredSession]);
        }
        @session_start();
    }
}

/**
 * Sanitiza slug do tenant (loja)
 * (NÃO usa/define bb_clean_slug para não conflitar com seu conexao.php)
 */
if (!function_exists('bb_clean_tenant_slug')) {
    function bb_clean_tenant_slug(string $v): string {
        $v = strtolower(trim($v));
        $v = preg_replace('/[^a-z0-9_-]/', '', $v);
        return $v ?: '';
    }
}

/**
 * Pega o host atual (sem porta)
 */
if (!function_exists('bb_host')) {
    function bb_host(): string {
        $h = (string)($_SERVER['HTTP_HOST'] ?? '');
        $h = strtolower(trim($h));
        $h = preg_replace('~^https?://~i', '', $h);
        $h = preg_replace('~/.*$~', '', $h);
        $h = preg_replace('/:\d+$/', '', $h);
        return trim($h, ". \t\n\r\0\x0B");
    }
}

/**
 * Limpa host arbitrário
 */
if (!function_exists('bb_clean_host')) {
    function bb_clean_host(string $host): string {
        $host = strtolower(trim($host));
        if ($host === '') {
            return '';
        }

        $host = preg_replace('~^https?://~i', '', $host);
        $host = preg_replace('~/.*$~', '', $host);
        $host = preg_replace('~:\d+$~', '', $host);

        return trim($host, ". \t\n\r\0\x0B");
    }
}

/**
 * Detecta tenant pela URL /loja/<slug>/...
 */
if (!function_exists('bb_tenant_from_uri')) {
    function bb_tenant_from_uri(): string {
        $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
        if ($uri === '') {
            return '';
        }

        if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $uri, $m)) {
            return bb_clean_tenant_slug((string)$m[1]);
        }

        return '';
    }
}

/**
 * Detecta tenant por header (opcional)
 */
if (!function_exists('bb_tenant_from_header')) {
    function bb_tenant_from_header(): string {
        $h = (string)($_SERVER['HTTP_X_TENANT'] ?? '');
        return bb_clean_tenant_slug($h);
    }
}

/**
 * Detecta tenant por subdomínio (opcional)
 */
if (!function_exists('bb_tenant_from_subdomain')) {
    function bb_tenant_from_subdomain(string $baseDomain = ''): string {
        $host = bb_host();
        if ($host === '') {
            return '';
        }

        if ($baseDomain !== '') {
            $baseDomain = strtolower(trim($baseDomain));
            if ($baseDomain !== '' && str_ends_with($host, $baseDomain)) {
                $sub = trim(str_replace($baseDomain, '', $host), '.');
                $parts = explode('.', $sub);
                $candidate = $parts[0] ?? '';
                return bb_clean_tenant_slug($candidate);
            }
        }

        $parts = explode('.', $host);
        $candidate = $parts[0] ?? '';
        if (in_array($candidate, ['www', 'api', 'sistema', 'painel', 'config'], true)) {
            return '';
        }

        return bb_clean_tenant_slug($candidate);
    }
}

/**
 * Configuração do marketplace
 */
if (!function_exists('bb_marketplace_cfg')) {
    function bb_marketplace_cfg(): array {
        static $cfg = null;

        if (is_array($cfg)) {
            return $cfg;
        }

        $cfg = [];

        $cfgApi = $GLOBALS['BB_API_CONFIG'] ?? null;
        if (is_array($cfgApi)) {
            if (!empty($cfgApi['marketplace']) && is_array($cfgApi['marketplace'])) {
                $cfg = $cfgApi['marketplace'];
            } elseif (!empty($cfgApi['master']) && is_array($cfgApi['master'])) {
                $cfg = $cfgApi;
            }
        }

        if (empty($cfg)) {
            $arquivoMarketplace = dirname(__DIR__) . '/config/marketplace.php';
            if (is_file($arquivoMarketplace)) {
                $tmp = require $arquivoMarketplace;
                if (is_array($tmp)) {
                    $cfg = $tmp;
                }
            }
        }

        if (empty($cfg['master']) || !is_array($cfg['master'])) {
            $cfg['master'] = [
                'host'  => '',
                'db'    => '',
                'user'  => '',
                'pass'  => '',
                'table' => 'tenants',
            ];
        }

        if (empty($cfg['security']) || !is_array($cfg['security'])) {
            $cfg['security'] = [
                'tenant_secret' => '',
            ];
        }

        if (empty($cfg['master']['table'])) {
            $cfg['master']['table'] = 'tenants';
        }

        return $cfg;
    }
}

/**
 * Conexão PDO simples
 */
if (!function_exists('bb_api_pdo')) {
    function bb_api_pdo(string $host, string $db, string $user, string $pass): ?PDO {
        $host = trim($host);
        $db = trim($db);
        $user = trim($user);

        if ($host === '' || $db === '' || $user === '') {
            return null;
        }

        try {
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
        } catch (Throwable $e) {
            return null;
        }
    }
}

/**
 * Verifica existência da tabela
 */
if (!function_exists('bb_api_table_exists')) {
    function bb_api_table_exists(PDO $pdo, string $tabela): bool {
        try {
            $st = $pdo->prepare("
                SELECT 1
                  FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :t
                 LIMIT 1
            ");
            $st->execute([':t' => $tabela]);
            return (bool)$st->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}

/**
 * Verifica existência da coluna
 */
if (!function_exists('bb_api_col_exists')) {
    function bb_api_col_exists(PDO $pdo, string $tabela, string $coluna): bool {
        try {
            $st = $pdo->prepare("
                SELECT 1
                  FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :t
                   AND COLUMN_NAME = :c
                 LIMIT 1
            ");
            $st->execute([':t' => $tabela, ':c' => $coluna]);
            return (bool)$st->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}

/**
 * Mapa de colunas
 */
if (!function_exists('bb_api_columns')) {
    function bb_api_columns(PDO $pdo, string $tabela): array {
        try {
            $st = $pdo->prepare("
                SELECT COLUMN_NAME
                  FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :t
            ");
            $st->execute([':t' => $tabela]);

            $res = $st->fetchAll(PDO::FETCH_COLUMN);
            $mapa = [];

            if (is_array($res)) {
                foreach ($res as $coluna) {
                    $mapa[(string)$coluna] = true;
                }
            }

            return $mapa;
        } catch (Throwable $e) {
            return [];
        }
    }
}

/**
 * Conexão do master
 */
if (!function_exists('bb_api_pdo_master')) {
    function bb_api_pdo_master(): ?PDO {
        static $pdoMaster = false;

        if ($pdoMaster instanceof PDO) {
            return $pdoMaster;
        }

        if ($pdoMaster === null) {
            return null;
        }

        $cfg = bb_marketplace_cfg();
        $master = is_array($cfg['master'] ?? null) ? $cfg['master'] : [];

        $pdoMaster = bb_api_pdo(
            (string)($master['host'] ?? ''),
            (string)($master['db'] ?? ''),
            (string)($master['user'] ?? ''),
            (string)($master['pass'] ?? '')
        );

        if (!$pdoMaster instanceof PDO) {
            $pdoMaster = null;
            return null;
        }

        return $pdoMaster;
    }
}

/**
 * Nome sanitizado da tabela de tenants
 */
if (!function_exists('bb_api_tenants_table')) {
    function bb_api_tenants_table(): string {
        $cfg = bb_marketplace_cfg();
        $tabela = preg_replace('/[^a-z0-9_]/i', '', (string)($cfg['master']['table'] ?? 'tenants'));
        return $tabela !== '' ? $tabela : 'tenants';
    }
}

/**
 * Variantes do host atual para resolver www e sem www
 */
if (!function_exists('bb_api_host_variants')) {
    function bb_api_host_variants(string $host): array {
        $host = bb_clean_host($host);
        if ($host === '') {
            return [];
        }

        $variantes = [$host];

        if (strpos($host, 'www.') === 0) {
            $semWww = substr($host, 4);
            if ($semWww !== '') {
                $variantes[] = $semWww;
            }
        } else {
            $variantes[] = 'www.' . $host;
        }

        return array_values(array_unique(array_filter($variantes)));
    }
}

/**
 * Descriptografa senha do tenant, quando necessário
 */
if (!function_exists('bb_api_try_decrypt_pass')) {
    function bb_api_try_decrypt_pass(string $senhaRaw, string $secret): string {
        if ($senhaRaw === '') {
            return '';
        }

        if ($secret === '') {
            return $senhaRaw;
        }

        if (strpos($senhaRaw, 'enc:') !== 0) {
            return $senhaRaw;
        }

        $arquivoCrypto = dirname(__DIR__) . '/sistema/tenant_crypto.php';
        if (is_file($arquivoCrypto)) {
            require_once $arquivoCrypto;
        }

        if (function_exists('bb_decrypt_str')) {
            $dec = (string)bb_decrypt_str($senhaRaw, $secret);
            if ($dec !== '') {
                return $dec;
            }
        }

        return $senhaRaw;
    }
}

/**
 * Resolve slug cedo pelo host atual usando tenant_domains
 */
if (!function_exists('bb_api_slug_from_host_early')) {
    function bb_api_slug_from_host_early(): string {
        static $slugCache = null;

        if ($slugCache !== null) {
            return $slugCache;
        }

        $slugCache = '';
        $hostAtual = bb_host();
        if ($hostAtual === '') {
            return $slugCache;
        }

        $pdoMaster = bb_api_pdo_master();
        if (!$pdoMaster instanceof PDO) {
            return $slugCache;
        }

        if (!bb_api_table_exists($pdoMaster, 'tenant_domains')) {
            return $slugCache;
        }

        $tenantsTable = bb_api_tenants_table();
        $colsDom = bb_api_columns($pdoMaster, 'tenant_domains');

        $hosts = bb_api_host_variants($hostAtual);
        if (empty($hosts)) {
            return $slugCache;
        }

        $placeholders = [];
        $params = [];

        foreach ($hosts as $i => $host) {
            $key = ':host_' . $i;
            $placeholders[] = "LOWER(TRIM(d.host)) = LOWER(TRIM({$key}))";
            $params[$key] = $host;
        }

        $where = ['(' . implode(' OR ', $placeholders) . ')'];

        if (!empty($colsDom['ativo'])) {
            $where[] = '(d.ativo = 1 OR d.ativo IS NULL)';
        }

        if (!empty($colsDom['principal'])) {
            $where[] = '(d.principal = 1 OR d.principal IS NULL)';
        }

        if (!empty($colsDom['tipo'])) {
            $where[] = "(d.tipo = 'dominio' OR d.tipo IS NULL OR d.tipo = '')";
        }

        $sql = "
            SELECT t.slug
              FROM tenant_domains d
              INNER JOIN `{$tenantsTable}` t ON t.id = d.tenant_id
             WHERE " . implode(' AND ', $where) . "
             ORDER BY d.id DESC
             LIMIT 1
        ";

        try {
            $st = $pdoMaster->prepare($sql);
            $st->execute($params);
            $slug = (string)$st->fetchColumn();
            $slugCache = bb_clean_tenant_slug($slug);
        } catch (Throwable $e) {
            $slugCache = '';
        }

        return $slugCache;
    }
}

/**
 * Detecta tenant por domínio personalizado salvo no marketplace
 */
if (!function_exists('bb_tenant_from_custom_domain')) {
    function bb_tenant_from_custom_domain(): string {
        return bb_clean_tenant_slug(bb_api_slug_from_host_early());
    }
}

/**
 * Retorna o tenant slug:
 * 1) GET ?loja=
 * 2) SESSION tenant_slug
 * 3) URI /loja/<slug>/
 * 4) Header X-Tenant
 * 5) Domínio personalizado
 * 6) Subdomínio (opcional)
 */
if (!function_exists('tenant_slug')) {
    function tenant_slug(array $opts = []): string {
        $baseDomain = (string)($opts['base_domain'] ?? '');

        // ✅ Guard: em /sistema (dono) ou rotas de marketplace, NÃO tenta resolver tenant automaticamente
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        if (strpos($path, '/sistema') === 0 && strpos($path, '/loja/') !== 0) {
            return '';
        }
        if (strpos($path, '/marketplace') === 0 || strpos($path, '/marketplace/') !== false) {
            return '';
        }

        if (!empty($_GET['loja'])) {
            $slug = bb_clean_tenant_slug((string)$_GET['loja']);
            if ($slug !== '') {
                $_SESSION['tenant_slug'] = $slug;
                $_GET['loja'] = $slug;
                return $slug;
            }
        }

        if (!empty($_SESSION['tenant_slug'])) {
            $slug = bb_clean_tenant_slug((string)$_SESSION['tenant_slug']);
            if ($slug !== '') {
                $_GET['loja'] = $slug;
                return $slug;
            }
        }

        $slug = bb_tenant_from_uri();
        if ($slug !== '') {
            $_SESSION['tenant_slug'] = $slug;
            $_GET['loja'] = $slug;
            return $slug;
        }

        $slug = bb_tenant_from_header();
        if ($slug !== '') {
            $_SESSION['tenant_slug'] = $slug;
            $_GET['loja'] = $slug;
            return $slug;
        }

        $slug = bb_tenant_from_custom_domain();
        if ($slug !== '') {
            $_SESSION['tenant_slug'] = $slug;
            $_GET['loja'] = $slug;
            return $slug;
        }

        $slug = bb_tenant_from_subdomain($baseDomain);
        if ($slug !== '') {
            $_SESSION['tenant_slug'] = $slug;
            $_GET['loja'] = $slug;
            return $slug;
        }

        return '';
    }
}

/**
 * Busca tenant completo no master pelo slug
 */
if (!function_exists('bb_api_fetch_tenant_by_slug')) {
    function bb_api_fetch_tenant_by_slug(string $slug): ?array {
        $slug = bb_clean_tenant_slug($slug);
        if ($slug === '') {
            return null;
        }

        $pdoMaster = bb_api_pdo_master();
        if (!$pdoMaster instanceof PDO) {
            return null;
        }

        $tenantsTable = bb_api_tenants_table();

        try {
            $st = $pdoMaster->prepare("SELECT * FROM `{$tenantsTable}` WHERE slug = :slug LIMIT 1");
            $st->execute([':slug' => $slug]);
            $tenant = $st->fetch(PDO::FETCH_ASSOC);
            return is_array($tenant) ? $tenant : null;
        } catch (Throwable $e) {
            return null;
        }
    }
}

/**
 * Tenant vencido?
 */
if (!function_exists('bb_api_tenant_expirado')) {
    function bb_api_tenant_expirado(array $tenant): bool {
        $vencimento = trim((string)($tenant['vencimento'] ?? ''));
        if ($vencimento === '' || $vencimento === '0000-00-00') {
            return false;
        }

        return substr($vencimento, 0, 10) < date('Y-m-d');
    }
}

/**
 * Tenant bloqueado?
 */
if (!function_exists('bb_api_tenant_bloqueado')) {
    function bb_api_tenant_bloqueado(array $tenant): bool {
        $status = strtolower(trim((string)($tenant['status'] ?? '')));
        if ($status === 'blocked') {
            return true;
        }

        if (isset($tenant['ativo']) && (string)$tenant['ativo'] !== '' && (int)$tenant['ativo'] !== 1) {
            return true;
        }

        return false;
    }
}

/**
 * Sincroniza bloqueio no master
 */
if (!function_exists('bb_api_sync_block_master')) {
    function bb_api_sync_block_master(array $tenant): void {
        $tenantId = (int)($tenant['id'] ?? 0);
        if ($tenantId <= 0) {
            return;
        }

        $pdoMaster = bb_api_pdo_master();
        if (!$pdoMaster instanceof PDO) {
            return;
        }

        $tenantsTable = bb_api_tenants_table();
        $cols = bb_api_columns($pdoMaster, $tenantsTable);
        $sets = [];

        if (!empty($cols['status'])) {
            $sets[] = "`status` = 'blocked'";
        }

        if (!empty($cols['ativo'])) {
            $sets[] = "`ativo` = 0";
        }

        if (!empty($cols['blocked_at'])) {
            $sets[] = "`blocked_at` = NOW()";
        }

        if (empty($sets)) {
            return;
        }

        try {
            $st = $pdoMaster->prepare("UPDATE `{$tenantsTable}` SET " . implode(', ', $sets) . " WHERE id = :id LIMIT 1");
            $st->execute([':id' => $tenantId]);
        } catch (Throwable $e) {
        }
    }
}

/**
 * Sincroniza config.ativo='Não' no banco da loja
 */
if (!function_exists('bb_api_sync_block_tenant_db')) {
    function bb_api_sync_block_tenant_db(array $tenant): void {
        $cfg = bb_marketplace_cfg();
        $secret = trim((string)($cfg['security']['tenant_secret'] ?? ''));

        $dbHost = trim((string)($tenant['db_host'] ?? 'localhost'));
        $dbName = trim((string)($tenant['db_name'] ?? ''));
        $dbUser = trim((string)($tenant['db_user'] ?? ''));
        $dbPass = (string)($tenant['db_pass'] ?? '');

        if ($dbHost === '' || $dbName === '' || $dbUser === '') {
            return;
        }

        $dbPass = bb_api_try_decrypt_pass($dbPass, $secret);
        $pdoTenant = bb_api_pdo($dbHost, $dbName, $dbUser, $dbPass);
        if (!$pdoTenant instanceof PDO) {
            return;
        }

        if (!bb_api_table_exists($pdoTenant, 'config')) {
            return;
        }

        if (!bb_api_col_exists($pdoTenant, 'config', 'ativo')) {
            return;
        }

        try {
            $pdoTenant->exec("UPDATE config SET ativo = 'Não'");
        } catch (Throwable $e) {
        }
    }
}

/**
 * Mensagem padrão de bloqueio
 */
if (!function_exists('bb_api_block_message')) {
    function bb_api_block_message(array $tenant, bool $estaVencido): string {
        $vencimento = trim((string)($tenant['vencimento'] ?? ''));

        if ($estaVencido && $vencimento !== '' && $vencimento !== '0000-00-00') {
            return 'Acesso bloqueado para esta barbearia. A assinatura venceu em ' . $vencimento . '.';
        }

        if ($vencimento !== '' && $vencimento !== '0000-00-00') {
            return 'Acesso bloqueado para esta barbearia. Vencimento registrado: ' . $vencimento . '.';
        }

        return 'Acesso bloqueado para esta barbearia.';
    }
}

/**
 * Raiz do projeto (disk) -> /public_html
 */
if (!function_exists('project_root_dir')) {
    function project_root_dir(): string {
        $root = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        if ($root !== '') {
            return $root;
        }

        $root = realpath(__DIR__ . '/..'); // /public_html
        if ($root) {
            return rtrim($root, '/');
        }

        return rtrim(dirname(__DIR__), '/');
    }
}

/**
 * Resolve tenant: se vazio, tenta tenant_slug()
 */
if (!function_exists('tenant_resolve')) {
    function tenant_resolve(string $tenant = ''): string {
        $tenant = bb_clean_tenant_slug($tenant);
        if ($tenant !== '') {
            return $tenant;
        }

        $t = '';
        if (function_exists('tenant_slug')) {
            $t = bb_clean_tenant_slug(tenant_slug());
        }

        return $t ?: '';
    }
}

/**
 * Base do tenant (disk)
 */
if (!function_exists('tenant_base_dir')) {
    function tenant_base_dir(string $tenant = ''): string {
        $tenant = tenant_resolve($tenant);
        if ($tenant === '') {
            return project_root_dir() . "/sistema";
        }

        return project_root_dir() . "/sistema/tenants/{$tenant}";
    }
}

/**
 * Garante pastas do tenant
 */
if (!function_exists('tenant_ensure_dirs')) {
    function tenant_ensure_dirs(string $tenant): void {
        $tenant = tenant_resolve($tenant);
        if ($tenant === '') {
            return;
        }

        $base = tenant_base_dir($tenant);
        $img = $base . "/img";
        $images = $base . "/images";
        $painel = $base . "/painel_img";

        if (!is_dir($base)) {
            @mkdir($base, 0755, true);
        }
        if (!is_dir($img)) {
            @mkdir($img, 0755, true);
        }
        if (!is_dir($images)) {
            @mkdir($images, 0755, true);
        }
        if (!is_dir($painel)) {
            @mkdir($painel, 0755, true);
        }
    }
}

/**
 * img (disk): global -> /public_html/sistema/img
 */
if (!function_exists('tenant_img_dir')) {
    function tenant_img_dir(string $tenant = ''): string {
        $tenant = tenant_resolve($tenant);

        if ($tenant === '') {
            return project_root_dir() . "/sistema/img";
        }

        tenant_ensure_dirs($tenant);
        return tenant_base_dir($tenant) . "/img";
    }
}

/**
 * images (disk): global -> /public_html/images  (IMPORTANTE!)
 */
if (!function_exists('tenant_images_dir')) {
    function tenant_images_dir(string $tenant = ''): string {
        $tenant = tenant_resolve($tenant);

        if ($tenant === '') {
            return project_root_dir() . "/images";
        }

        tenant_ensure_dirs($tenant);
        return tenant_base_dir($tenant) . "/images";
    }
}

/**
 * (opcional) painel_img
 */
if (!function_exists('tenant_painel_img_dir')) {
    function tenant_painel_img_dir(string $tenant = ''): string {
        $tenant = tenant_resolve($tenant);

        if ($tenant === '') {
            return project_root_dir() . "/sistema/painel_img";
        }

        tenant_ensure_dirs($tenant);
        return tenant_base_dir($tenant) . "/painel_img";
    }
}

/**
 * Monta URL pública do arquivo do tenant.
 * subdir: img|images
 */
if (!function_exists('tenant_file_url')) {
    function tenant_file_url(string $tenant, string $subdir, string $file, string $fallback = ''): string {
        $tenant = tenant_resolve($tenant);
        $file = trim((string)$file);

        if ($tenant === '' || $file === '') {
            return $fallback;
        }

        $subdir = ($subdir === 'img') ? 'img' : 'images';
        return "/sistema/tenants/{$tenant}/{$subdir}/" . rawurlencode($file);
    }
}

/**
 * Apaga arquivo só do tenant
 */
if (!function_exists('tenant_unlink')) {
    function tenant_unlink(string $tenant, string $subdir, string $file): bool {
        $tenant = tenant_resolve($tenant);
        $file = basename((string)$file);

        if ($tenant === '' || $file === '') {
            return false;
        }

        $dir = ($subdir === 'img')
            ? (tenant_base_dir($tenant) . "/img")
            : (tenant_base_dir($tenant) . "/images");

        $path = rtrim($dir, '/\\') . '/' . $file;

        if (is_file($path)) {
            @unlink($path);
            return true;
        }

        return false;
    }
}

/**
 * Compat: cria pastas do tenant atual
 */
if (!function_exists('tenant_mkdirs')) {
    function tenant_mkdirs(): void {
        $slug = function_exists('tenant_slug') ? tenant_slug() : '';
        $slug = bb_clean_tenant_slug($slug);
        if ($slug === '') {
            return;
        }

        tenant_ensure_dirs($slug);
    }
}

/**
 * Inicializa contexto do tenant para a API
 * - resolve slug
 * - consulta tenant no master
 * - valida bloqueio por vencimento/status/ativo
 * - abre conexão no banco da loja
 * - publica globais esperadas pelo bootstrap
 */
$GLOBALS['TENANT'] = null;
$GLOBALS['PDO_TENANT'] = null;
$GLOBALS['TENANT_STORAGE'] = null;
$GLOBALS['TENANT_BLOCKED'] = false;
$GLOBALS['TENANT_BLOCK_REASON'] = '';
$GLOBALS['TENANT_BLOCK_HTTP'] = 403;

$__bb_api_cfg = $GLOBALS['BB_API_CONFIG'] ?? [];
$__bb_api_baseDomain = '';
if (is_array($__bb_api_cfg) && !empty($__bb_api_cfg['base_domain'])) {
    $__bb_api_baseDomain = (string)$__bb_api_cfg['base_domain'];
}

$__bb_tenant_slug = tenant_slug([
    'base_domain' => $__bb_api_baseDomain,
]);

if ($__bb_tenant_slug === '') {
    return;
}

$__bb_tenant = bb_api_fetch_tenant_by_slug($__bb_tenant_slug);
if (!is_array($__bb_tenant)) {
    return;
}

$__bb_estaVencido = bb_api_tenant_expirado($__bb_tenant);
$__bb_estaBloqueado = bb_api_tenant_bloqueado($__bb_tenant);

if ($__bb_estaVencido) {
    bb_api_sync_block_master($__bb_tenant);
    bb_api_sync_block_tenant_db($__bb_tenant);
    $__bb_tenant['status'] = 'blocked';
    $__bb_tenant['ativo'] = 0;
    $__bb_estaBloqueado = true;
}

if ($__bb_estaBloqueado) {
    $GLOBALS['TENANT'] = $__bb_tenant;
    $GLOBALS['TENANT_BLOCKED'] = true;
    $GLOBALS['TENANT_BLOCK_REASON'] = bb_api_block_message($__bb_tenant, $__bb_estaVencido);
    $GLOBALS['TENANT_BLOCK_HTTP'] = 403;
    return;
}

$__bb_cfg_market = bb_marketplace_cfg();
$__bb_tenant_secret = trim((string)($__bb_cfg_market['security']['tenant_secret'] ?? ''));

$__bb_dbHost = trim((string)($__bb_tenant['db_host'] ?? 'localhost'));
$__bb_dbName = trim((string)($__bb_tenant['db_name'] ?? ''));
$__bb_dbUser = trim((string)($__bb_tenant['db_user'] ?? ''));
$__bb_dbPass = (string)($__bb_tenant['db_pass'] ?? '');

if ($__bb_dbHost === '' || $__bb_dbName === '' || $__bb_dbUser === '') {
    return;
}

$__bb_dbPass = bb_api_try_decrypt_pass($__bb_dbPass, $__bb_tenant_secret);
$__bb_pdo_tenant = bb_api_pdo($__bb_dbHost, $__bb_dbName, $__bb_dbUser, $__bb_dbPass);

if (!$__bb_pdo_tenant instanceof PDO) {
    return;
}

tenant_ensure_dirs($__bb_tenant_slug);

$GLOBALS['TENANT'] = $__bb_tenant;
$GLOBALS['PDO_TENANT'] = $__bb_pdo_tenant;
$GLOBALS['TENANT_STORAGE'] = [
    'slug' => $__bb_tenant_slug,
    'base_dir' => tenant_base_dir($__bb_tenant_slug),
    'img_dir' => tenant_img_dir($__bb_tenant_slug),
    'images_dir' => tenant_images_dir($__bb_tenant_slug),
    'painel_img_dir' => tenant_painel_img_dir($__bb_tenant_slug),
];

$_SESSION['tenant_slug'] = $__bb_tenant_slug;
