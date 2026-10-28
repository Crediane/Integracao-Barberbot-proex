<?php
// /public_html/app/tenant_context.php
// =====================================================================
// CONTEXTO EXCLUSIVO DO APP PROFISSIONAL
// - Aceita /loja/{slug}/prof/, ?loja={slug}, subdomínio ou domínio próprio
//   já resolvido pelo /sistema/conexao.php.
// - Bloqueia o app quando não houver TENANT válido.
// - Confirma que o PDO está conectado exatamente ao banco individual
//   registrado para a barbearia.
// - Não utiliza HTTP_REFERER para decidir sessão, slug ou banco.
// =====================================================================

if (!function_exists('bb_app_clean_slug')) {
    function bb_app_clean_slug($value): string
    {
        $value = strtolower(trim((string)$value));
        return (string)preg_replace('/[^a-z0-9_-]/', '', $value);
    }
}

if (!function_exists('bb_app_is_ajax_request')) {
    function bb_app_is_ajax_request(): bool
    {
        $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));

        return $requestedWith === 'xmlhttprequest'
            || strpos($accept, 'application/json') !== false;
    }
}

if (!function_exists('bb_app_tenant_error')) {
    function bb_app_tenant_error(string $message, int $statusCode = 404): void
    {
        http_response_code($statusCode);

        if (bb_app_is_ajax_request()) {
            if (!headers_sent()) {
                header('Content-Type: text/plain; charset=utf-8');
            }
            echo $message;
            exit;
        }

        if (!headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
        }

        $safeMessage = htmlspecialchars($message, ENT_QUOTES, 'UTF-8');

        echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Aplicativo indisponível</title>
  <style>
    body{margin:0;background:#0f172a;color:#111827;font-family:Arial,Helvetica,sans-serif}
    .bb-app-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px;box-sizing:border-box}
    .bb-app-card{width:100%;max-width:560px;background:#fff;border-radius:18px;box-shadow:0 20px 55px rgba(0,0,0,.28);overflow:hidden}
    .bb-app-head{padding:20px 24px;background:#166534;color:#fff;font-size:20px;font-weight:700}
    .bb-app-body{padding:24px;line-height:1.6}
    .bb-app-body p{margin:0}
  </style>
</head>
<body>
  <div class="bb-app-wrap">
    <div class="bb-app-card">
      <div class="bb-app-head">BarberBot Profissional</div>
      <div class="bb-app-body"><p>' . $safeMessage . '</p></div>
    </div>
  </div>
</body>
</html>';
        exit;
    }
}

$bb_app_request_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$bb_app_explicit_slug = '';

if (!empty($_GET['loja'])) {
    $bb_app_explicit_slug = bb_app_clean_slug($_GET['loja']);
} elseif (!empty($_POST['loja'])) {
    $bb_app_explicit_slug = bb_app_clean_slug($_POST['loja']);
} elseif (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $bb_app_request_path, $bb_app_slug_match)) {
    $bb_app_explicit_slug = bb_app_clean_slug($bb_app_slug_match[1]);
}

// Entrega o slug explícito ao bootstrap central antes da conexão.
if ($bb_app_explicit_slug !== '') {
    $_GET['loja'] = $bb_app_explicit_slug;
}

if (session_status() === PHP_SESSION_NONE && !headers_sent()) {
    $secureCookie = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    @session_set_cookie_params([
        'path' => '/',
        'secure' => $secureCookie,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

require_once __DIR__ . '/../sistema/conexao.php';

$bb_app_slug = bb_app_clean_slug($_SESSION['tenant_slug'] ?? $bb_app_explicit_slug);
$bb_app_tenant_db = trim((string)($_SESSION['tenant_db'] ?? ''));
$bb_app_is_tenant_session = strpos((string)session_name(), 'BB_TENANT') === 0;

if (!$bb_app_is_tenant_session || $bb_app_slug === '' || $bb_app_tenant_db === '' || !($pdo instanceof PDO)) {
    bb_app_tenant_error('Acesse o aplicativo pelo link exclusivo da sua barbearia.', 404);
}

try {
    $bb_app_connected_db = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
} catch (Throwable $e) {
    bb_app_tenant_error('Não foi possível confirmar o banco individual da barbearia.', 503);
}

if ($bb_app_connected_db === '' || $bb_app_connected_db !== $bb_app_tenant_db) {
    bb_app_tenant_error('O acesso foi interrompido porque o banco individual da barbearia não foi confirmado.', 409);
}

$_GET['loja'] = $bb_app_slug;
$_SESSION['tenant_slug'] = $bb_app_slug;
$_SESSION['tenant_db'] = $bb_app_tenant_db;

// Base pública do APP. Preserva o padrão já usado pelo ecossistema.
$bb_app_base_path = '';
if (preg_match('~^(/loja/[a-z0-9_-]+/prof)(?:/|$)~i', $bb_app_request_path, $bb_app_base_match)) {
    $bb_app_base_path = rtrim((string)$bb_app_base_match[1], '/');
} elseif (preg_match('~^(.*?/prof)(?:/|$)~i', $bb_app_request_path, $bb_app_prof_match)) {
    $bb_app_base_path = rtrim((string)$bb_app_prof_match[1], '/');
} elseif (preg_match('~^/app(?:/|$)~i', $bb_app_request_path)) {
    $bb_app_base_path = '/loja/' . rawurlencode($bb_app_slug) . '/prof';
} else {
    $requestDirectory = rtrim(str_replace('\\', '/', dirname($bb_app_request_path)), '/');
    $bb_app_base_path = ($requestDirectory === '' || $requestDirectory === '.')
        ? '/loja/' . rawurlencode($bb_app_slug) . '/prof'
        : $requestDirectory;
}

$bb_app_login_url = $bb_app_base_path . '/';
$bb_app_panel_url = $bb_app_base_path . '/painel/';
$bb_app_storage_suffix = strtoupper(substr(sha1($bb_app_slug), 0, 12));
$bb_app_storage_email_key = 'BB_PROF_EMAIL_' . $bb_app_storage_suffix;
$bb_app_storage_page_key = 'BB_PROF_PAGE_' . $bb_app_storage_suffix;
