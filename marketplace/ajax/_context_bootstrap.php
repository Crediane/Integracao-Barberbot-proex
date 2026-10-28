<?php
/**
 * /public_html/ajax/_context_bootstrap.php
 *
 * Bootstrap centralizado para endpoints AJAX.
 *
 * Regras:
 * - Não usa HTTP_REFERER para escolher loja.
 * - Não renomeia nem reutiliza sessão manualmente.
 * - Delega toda a resolução de /loja/{slug}/, parâmetro, subdomínio e
 *   domínio próprio ao /sistema/conexao.php.
 * - Confirma o banco efetivamente aberto com SELECT DATABASE().
 * - Quando o endpoint é tenant-only, bloqueia Master/Site antes de qualquer
 *   consulta operacional da barbearia.
 *
 * Opções antes do require:
 *   $bb_ajax_context_mode = 'tenant' | 'global' | 'any';
 *   $bb_ajax_context_response = 'plain' | 'json';
 */

if (!function_exists('bb_ajax_ctx_clean_slug')) {
    function bb_ajax_ctx_clean_slug($valor): string
    {
        $valor = strtolower(trim((string)$valor));
        return (string)preg_replace('/[^a-z0-9_-]/', '', $valor);
    }
}

if (!function_exists('bb_ajax_ctx_fail')) {
    function bb_ajax_ctx_fail(string $mensagem, int $status = 403, string $tipo = 'plain'): void
    {
        http_response_code($status);

        if ($tipo === 'json') {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'status' => 'erro',
                'mensagem' => $mensagem,
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        } else {
            header('Content-Type: text/plain; charset=utf-8');
            echo $mensagem;
        }

        exit;
    }
}

$bb_ajax_context_mode = isset($bb_ajax_context_mode)
    ? strtolower(trim((string)$bb_ajax_context_mode))
    : 'tenant';

if (!in_array($bb_ajax_context_mode, ['tenant', 'global', 'any'], true)) {
    $bb_ajax_context_mode = 'tenant';
}

$bb_ajax_context_response = isset($bb_ajax_context_response)
    ? strtolower(trim((string)$bb_ajax_context_response))
    : 'plain';

if (!in_array($bb_ajax_context_response, ['plain', 'json'], true)) {
    $bb_ajax_context_response = 'plain';
}

$bb_ajax_req_path = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$bb_ajax_slug_sources = [];

if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $bb_ajax_req_path, $bb_ajax_match)) {
    $bb_ajax_slug_sources['url'] = bb_ajax_ctx_clean_slug($bb_ajax_match[1]);
}

foreach ([
    'get_loja' => $_GET['loja'] ?? '',
    'post_loja' => $_POST['loja'] ?? '',
    'get_tenant_slug' => $_GET['tenant_slug'] ?? '',
    'post_tenant_slug' => $_POST['tenant_slug'] ?? '',
] as $bb_ajax_source => $bb_ajax_raw_slug) {
    if (is_array($bb_ajax_raw_slug)) {
        bb_ajax_ctx_fail('Parâmetro de loja inválido.', 400, $bb_ajax_context_response);
    }

    $bb_ajax_raw_slug = trim((string)$bb_ajax_raw_slug);
    if ($bb_ajax_raw_slug === '') {
        continue;
    }

    $bb_ajax_clean_slug = bb_ajax_ctx_clean_slug($bb_ajax_raw_slug);
    if ($bb_ajax_clean_slug === '') {
        bb_ajax_ctx_fail('Parâmetro de loja inválido.', 400, $bb_ajax_context_response);
    }

    $bb_ajax_slug_sources[$bb_ajax_source] = $bb_ajax_clean_slug;
}

$bb_ajax_unique_slugs = array_values(array_unique(array_filter($bb_ajax_slug_sources)));
if (count($bb_ajax_unique_slugs) > 1) {
    bb_ajax_ctx_fail('Conflito entre os identificadores da loja informados na requisição.', 400, $bb_ajax_context_response);
}

$bb_ajax_explicit_slug = $bb_ajax_unique_slugs[0] ?? '';
if ($bb_ajax_explicit_slug !== '') {
    // O conexao.php é a única camada que resolve e abre o banco.
    $_GET['loja'] = $bb_ajax_explicit_slug;
    $_REQUEST['loja'] = $bb_ajax_explicit_slug;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    require_once __DIR__ . '/../sistema/conexao.php';
}

require_once __DIR__ . '/timezone_tenant.php';

if (!isset($pdo) || !($pdo instanceof PDO)) {
    bb_ajax_ctx_fail('Conexão com o banco não encontrada.', 500, $bb_ajax_context_response);
}

try {
    $bb_ajax_connected_db = trim((string)$pdo->query('SELECT DATABASE()')->fetchColumn());
} catch (Throwable $e) {
    $bb_ajax_connected_db = '';
}

if ($bb_ajax_connected_db === '') {
    bb_ajax_ctx_fail('Não foi possível confirmar o banco conectado.', 500, $bb_ajax_context_response);
}

$bb_ajax_session_name = (string)session_name();
$bb_ajax_tenant_slug = bb_ajax_ctx_clean_slug($_SESSION['tenant_slug'] ?? '');
$bb_ajax_tenant_db = trim((string)($_SESSION['tenant_db'] ?? ''));

$bb_ajax_is_tenant = (
    strpos($bb_ajax_session_name, 'BB_TENANT') === 0
    && $bb_ajax_tenant_slug !== ''
    && $bb_ajax_tenant_db !== ''
    && hash_equals($bb_ajax_tenant_db, $bb_ajax_connected_db)
);

if ($bb_ajax_explicit_slug !== '') {
    if (!$bb_ajax_is_tenant || !hash_equals($bb_ajax_explicit_slug, $bb_ajax_tenant_slug)) {
        bb_ajax_ctx_fail('Falha de isolamento: a loja solicitada não corresponde ao banco conectado.', 403, $bb_ajax_context_response);
    }
}

$bb_ajax_marketplace_cfg = [];
$bb_ajax_marketplace_file = __DIR__ . '/../config/marketplace.php';
if (is_file($bb_ajax_marketplace_file)) {
    $bb_ajax_marketplace_cfg = require $bb_ajax_marketplace_file;
    if (!is_array($bb_ajax_marketplace_cfg)) {
        $bb_ajax_marketplace_cfg = [];
    }
}

$bb_ajax_auth_cfg = isset($bb_ajax_marketplace_cfg['auth_master']) && is_array($bb_ajax_marketplace_cfg['auth_master'])
    ? $bb_ajax_marketplace_cfg['auth_master']
    : [];

$bb_ajax_global_db = trim((string)($bb_ajax_auth_cfg['db'] ?? ''));
$bb_ajax_is_master = (
    $bb_ajax_session_name === 'BB_MASTER'
    && $bb_ajax_global_db !== ''
    && hash_equals($bb_ajax_global_db, $bb_ajax_connected_db)
);

$bb_ajax_is_site_global = (
    $bb_ajax_session_name === 'BB_SITE'
    && $bb_ajax_global_db !== ''
    && hash_equals($bb_ajax_global_db, $bb_ajax_connected_db)
);

if ($bb_ajax_context_mode === 'tenant' && !$bb_ajax_is_tenant) {
    bb_ajax_ctx_fail('Acesso negado: este endpoint pertence exclusivamente ao banco individual da loja.', 403, $bb_ajax_context_response);
}

if ($bb_ajax_context_mode === 'global' && !($bb_ajax_is_master || $bb_ajax_is_site_global)) {
    bb_ajax_ctx_fail('Acesso negado: este endpoint pertence exclusivamente ao banco global/administrativo.', 403, $bb_ajax_context_response);
}

if ($bb_ajax_context_mode === 'any' && !($bb_ajax_is_tenant || $bb_ajax_is_master || $bb_ajax_is_site_global)) {
    bb_ajax_ctx_fail('Falha de isolamento: o contexto da requisição não foi confirmado.', 403, $bb_ajax_context_response);
}

$bb_ajax_context = [
    'type' => $bb_ajax_is_tenant ? 'tenant' : ($bb_ajax_is_master ? 'master' : 'site'),
    'slug' => $bb_ajax_is_tenant ? $bb_ajax_tenant_slug : '',
    'db' => $bb_ajax_connected_db,
    'session_name' => $bb_ajax_session_name,
    'explicit_slug' => $bb_ajax_explicit_slug,
];

// Compatibilidade pontual com arquivos antigos que já usam estes nomes.
$__bb_reqSlug = $bb_ajax_context['slug'];
$__bb_slug = $bb_ajax_context['slug'];
$tenant = $bb_ajax_context['slug'];
