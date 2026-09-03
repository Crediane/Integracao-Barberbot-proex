<?php
// ======================================================================
// CONTEXTO CENTRAL DAS PÁGINAS DO PAINEL (MASTER x TENANT)
// - Não usa HTTP_REFERER para escolher sessão, slug ou banco.
// - /loja/{slug}/, ?loja={slug}, subdomínio e domínio próprio => TENANT.
// - /sistema/ sem contexto de loja => MASTER.
// - Confirma o banco realmente conectado antes de liberar a página.
// ======================================================================

if (defined('BB_PAGINAS_CONTEXT_BOOTSTRAP_LOADED')) {
    return;
}
define('BB_PAGINAS_CONTEXT_BOOTSTRAP_LOADED', true);

if (!function_exists('bb_pages_clean_slug')) {
    function bb_pages_clean_slug($valor): string
    {
        $valor = strtolower(trim((string)$valor));
        return (string)preg_replace('/[^a-z0-9_-]/', '', $valor);
    }
}

// Compatibilidade com os arquivos antigos que já chamam essa função.
if (!function_exists('bb_sess_clean_slug')) {
    function bb_sess_clean_slug($valor): string
    {
        return bb_pages_clean_slug($valor);
    }
}

if (!function_exists('bb_pages_clean_host')) {
    function bb_pages_clean_host($host): string
    {
        $host = strtolower(trim((string)$host));
        if ($host === '') {
            return '';
        }
        $host = (string)preg_replace('~^https?://~i', '', $host);
        $host = (string)preg_replace('~/.*$~', '', $host);
        $host = (string)preg_replace('~:\d+$~', '', $host);
        return trim($host, ". \t\n\r\0\x0B");
    }
}

if (!function_exists('bb_pages_is_ajax')) {
    function bb_pages_is_ajax(): bool
    {
        $reqPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));

        if ($requestedWith === 'xmlhttprequest') {
            return true;
        }
        if (strpos($accept, 'application/json') !== false || strpos($accept, 'text/plain') !== false) {
            return true;
        }
        if (strpos($reqPath, '/paginas/') !== false || strpos($reqPath, '/ajax/') !== false || strpos($reqPath, '/api/') !== false) {
            return true;
        }

        return false;
    }
}

if (!function_exists('bb_pages_abort')) {
    function bb_pages_abort(string $mensagem, int $codigo = 403): void
    {
        http_response_code($codigo);

        if (!headers_sent()) {
            header('Content-Type: ' . (bb_pages_is_ajax() ? 'text/plain' : 'text/html') . '; charset=utf-8');
        }

        if (bb_pages_is_ajax()) {
            echo $mensagem;
            exit;
        }

        echo '<div class="alert alert-danger" role="alert">'
            . htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8')
            . '</div>';
        exit;
    }
}

$__bb_reqPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$__bb_scriptPath = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$__bb_systemPos = stripos($__bb_scriptPath, '/sistema/');
$__bb_webBase = ($__bb_systemPos !== false)
    ? rtrim(substr($__bb_scriptPath, 0, $__bb_systemPos), '/')
    : '';
$__bb_routePath = $__bb_reqPath;
if ($__bb_webBase !== '' && stripos($__bb_routePath, $__bb_webBase) === 0) {
    $__bb_routePath = substr($__bb_routePath, strlen($__bb_webBase));
    if ($__bb_routePath === '') {
        $__bb_routePath = '/';
    }
}
$__bb_refPath = ''; // Deliberadamente vazio: referer não define contexto.

$__bb_slugGet = isset($_GET['loja']) ? bb_pages_clean_slug($_GET['loja']) : '';
$__bb_slugPost = isset($_POST['loja']) ? bb_pages_clean_slug($_POST['loja']) : '';

if ($__bb_slugGet !== '' && $__bb_slugPost !== '' && $__bb_slugGet !== $__bb_slugPost) {
    bb_pages_abort('Contexto de loja inválido: os slugs informados não coincidem.', 400);
}

$__bb_reqSlug = $__bb_slugPost !== '' ? $__bb_slugPost : $__bb_slugGet;
if ($__bb_reqSlug === '' && preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_routePath, $__bbMatchSlug)) {
    $__bb_reqSlug = bb_pages_clean_slug($__bbMatchSlug[1]);
}

if ($__bb_reqSlug !== '') {
    $_GET['loja'] = $__bb_reqSlug;
    $_REQUEST['loja'] = $__bb_reqSlug;
}

$__bb_configFile = __DIR__ . '/../../../config/marketplace.php';
$__bb_pagesCfg = [];
if (is_file($__bb_configFile)) {
    $__bbLoadedCfg = require $__bb_configFile;
    if (is_array($__bbLoadedCfg)) {
        $__bb_pagesCfg = $__bbLoadedCfg;
    }
}

$__bb_hostAtual = bb_pages_clean_host($_SERVER['HTTP_HOST'] ?? '');
$__bb_mainHosts = ['localhost', '127.0.0.1'];
$__bb_baseUrl = '';
if (!empty($__bb_pagesCfg['base_url'])) {
    $__bb_baseUrl = (string)$__bb_pagesCfg['base_url'];
} elseif (!empty($__bb_pagesCfg['app']['base_url'])) {
    $__bb_baseUrl = (string)$__bb_pagesCfg['app']['base_url'];
}
$__bb_baseHost = bb_pages_clean_host($__bb_baseUrl);
if ($__bb_baseHost !== '') {
    $__bb_mainHosts[] = $__bb_baseHost;
    $__bb_mainHosts[] = 'www.' . preg_replace('~^www\.~', '', $__bb_baseHost);
}
$__bb_mainHosts = array_values(array_unique(array_filter(array_map('bb_pages_clean_host', $__bb_mainHosts))));
$__bb_isMainHost = $__bb_hostAtual === '' || in_array($__bb_hostAtual, $__bb_mainHosts, true);
$__bb_isTenantHost = !$__bb_isMainHost && $__bb_hostAtual !== '';

$__bb_tenantCookies = [];
foreach ($_COOKIE as $__bbCookieName => $__bbCookieValue) {
    if ($__bbCookieName === 'BB_TENANT' || strpos((string)$__bbCookieName, 'BB_TENANT_') === 0) {
        $__bb_tenantCookies[] = (string)$__bbCookieName;
    }
}
$__bb_tenantCookies = array_values(array_unique($__bb_tenantCookies));
$__bb_oneTenantCookie = count($__bb_tenantCookies) === 1 ? $__bb_tenantCookies[0] : '';
$__bb_hasMasterCookie = !empty($_COOKIE['BB_MASTER']);

$__bb_ctx = strtolower(trim((string)($_GET['ctx'] ?? ($_COOKIE['BB_CTX'] ?? ''))));
if (!in_array($__bb_ctx, ['tenant', 'master'], true)) {
    $__bb_ctx = '';
}

$__bb_lojaByPath = (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_routePath);
$__bb_hasLoja = $__bb_reqSlug !== '' || $__bb_lojaByPath || $__bb_isTenantHost;
$__bb_isSistema = strpos($__bb_routePath, '/sistema') === 0;
$__bb_isDirectPagesEndpoint = strpos($__bb_routePath, '/sistema/painel/paginas/') === 0;
$__bb_activeSessionName = session_status() === PHP_SESSION_ACTIVE ? (string)session_name() : '';

$__bb_tenantSessionName = 'BB_TENANT';
if ($__bb_reqSlug !== '') {
    $__bb_tenantSessionName = 'BB_TENANT_' . strtoupper(substr(sha1($__bb_reqSlug), 0, 12));
}

$__bb_desiredSession = 'BB_SITE';

if ($__bb_reqSlug !== '') {
    $__bb_desiredSession = $__bb_tenantSessionName;
} elseif (strpos($__bb_activeSessionName, 'BB_TENANT') === 0) {
    // Página incluída pelo painel da loja: preserva a sessão já confirmada.
    $__bb_desiredSession = $__bb_activeSessionName;
} elseif ($__bb_isTenantHost) {
    // Em domínio próprio/subdomínio, o conexao.php confirma o slug no Banco Global.
    $__bb_desiredSession = $__bb_oneTenantCookie !== '' ? $__bb_oneTenantCookie : 'BB_TENANT';
} elseif ($__bb_isDirectPagesEndpoint && $__bb_ctx === 'tenant') {
    if (count($__bb_tenantCookies) > 1) {
        bb_pages_abort('Sessão de loja ambígua. Envie o parâmetro ?loja=slug nesta requisição.', 401);
    }
    $__bb_desiredSession = $__bb_oneTenantCookie !== '' ? $__bb_oneTenantCookie : 'BB_TENANT';
} elseif ($__bb_isDirectPagesEndpoint && $__bb_oneTenantCookie !== '' && !$__bb_hasMasterCookie) {
    // Compatibilidade com chamadas antigas do painel da loja que ainda não enviam ?loja=.
    $__bb_desiredSession = $__bb_oneTenantCookie;
} elseif ($__bb_isSistema) {
    $__bb_desiredSession = 'BB_MASTER';
} elseif ($__bb_activeSessionName === 'BB_MASTER') {
    $__bb_desiredSession = 'BB_MASTER';
}

$__bb_forceMaster = $__bb_desiredSession === 'BB_MASTER';

if (session_status() === PHP_SESSION_ACTIVE && session_name() !== $__bb_desiredSession) {
    session_write_close();
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_name($__bb_desiredSession);
    if (!empty($_COOKIE[$__bb_desiredSession])) {
        @session_id((string)$_COOKIE[$__bb_desiredSession]);
    }
    @session_start();
}

if ((string)session_name() !== $__bb_desiredSession) {
    bb_pages_abort('Não foi possível iniciar a sessão correta para este contexto.', 500);
}

if (!headers_sent()) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    $ctxValue = strpos((string)session_name(), 'BB_TENANT') === 0 ? 'tenant' : ((string)session_name() === 'BB_MASTER' ? 'master' : '');
    if ($ctxValue !== '') {
        @setcookie('BB_CTX', $ctxValue, [
            'expires' => time() + 60 * 60 * 24 * 30,
            'path' => '/',
            'secure' => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }
}

$__bb_conexaoFile = __DIR__ . '/../../conexao.php';
if (!is_file($__bb_conexaoFile)) {
    bb_pages_abort('Arquivo de conexão do sistema não encontrado.', 500);
}
require_once $__bb_conexaoFile;

if (!isset($pdo) || !($pdo instanceof PDO)) {
    bb_pages_abort('Não foi possível estabelecer a conexão com o banco deste contexto.', 500);
}

try {
    $__bb_connectedDatabase = trim((string)$pdo->query('SELECT DATABASE()')->fetchColumn());
} catch (Throwable $e) {
    $__bb_connectedDatabase = '';
}

$__bb_sessionNameNow = (string)session_name();
if (strpos($__bb_sessionNameNow, 'BB_TENANT') === 0) {
    $__bb_sessionSlug = bb_pages_clean_slug($_SESSION['tenant_slug'] ?? '');
    $__bb_sessionDb = trim((string)($_SESSION['tenant_db'] ?? ''));

    if ($__bb_sessionSlug === '' || $__bb_sessionDb === '') {
        bb_pages_abort('A loja não foi mapeada para um Banco Individual válido.', 404);
    }
    if ($__bb_reqSlug !== '' && $__bb_sessionSlug !== $__bb_reqSlug) {
        bb_pages_abort('A sessão ativa não pertence ao slug informado.', 403);
    }
    if ($__bb_connectedDatabase === '' || $__bb_connectedDatabase !== $__bb_sessionDb) {
        bb_pages_abort('Falha de isolamento: o Banco Individual da loja não foi confirmado.', 500);
    }

    $_GET['loja'] = $__bb_sessionSlug;
    $_REQUEST['loja'] = $__bb_sessionSlug;
    $__bb_reqSlug = $__bb_sessionSlug;
    $__bb_hasLoja = true;
} elseif ($__bb_sessionNameNow === 'BB_MASTER') {
    $__bb_masterDatabases = [];
    if (!empty($__bb_pagesCfg['master']['db'])) {
        $__bb_masterDatabases[] = preg_replace('/[^a-z0-9_]/i', '', (string)$__bb_pagesCfg['master']['db']);
    }
    if (!empty($__bb_pagesCfg['auth_master']['db'])) {
        $__bb_masterDatabases[] = preg_replace('/[^a-z0-9_]/i', '', (string)$__bb_pagesCfg['auth_master']['db']);
    }
    $__bb_masterDatabases = array_values(array_unique(array_filter($__bb_masterDatabases)));

    if (!$__bb_masterDatabases) {
        bb_pages_abort('O Banco Global/Administrativo não está configurado.', 500);
    }
    if ($__bb_connectedDatabase === '' || !in_array($__bb_connectedDatabase, $__bb_masterDatabases, true)) {
        bb_pages_abort('Falha de isolamento: a conexão Master não corresponde ao Banco Global/Administrativo.', 500);
    }
} else {
    bb_pages_abort('Contexto inválido. Acesse pelo Link Master ou pela URL da loja.', 403);
}

$bb_tenant_slug_ajax = strpos($__bb_sessionNameNow, 'BB_TENANT') === 0
    ? bb_pages_clean_slug($_SESSION['tenant_slug'] ?? '')
    : '';
$bb_loja_query_ajax = $bb_tenant_slug_ajax !== ''
    ? 'loja=' . rawurlencode($bb_tenant_slug_ajax)
    : '';
