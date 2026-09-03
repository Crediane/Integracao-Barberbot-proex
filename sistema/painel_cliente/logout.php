<?php
// /public_html/sistema/painel_cliente/logout.php
// Encerra somente a sessão TENANT do Painel do Cliente.

require_once __DIR__ . '/../conexao.php';

$bb_loja_slug = strtolower((string)preg_replace('/[^a-z0-9_-]/', '', (string)($_SESSION['tenant_slug'] ?? '')));
$bb_sessao_tenant = strpos((string)session_name(), 'BB_TENANT') === 0;

if (!$bb_sessao_tenant || session_name() === 'BB_MASTER') {
    if (!headers_sent()) {
        header('Location: /sistema/');
    } else {
        echo '<script>window.location="/sistema/";</script>';
    }
    exit();
}

$_SESSION = array();

if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params['path'] ?? '/',
        $params['domain'] ?? '',
        !empty($params['secure']),
        !empty($params['httponly'])
    );
}

@session_destroy();

if (!headers_sent()) {
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    setcookie('BB_CTX', '', [
        'expires' => time() - 3600,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}

$destino = $bb_loja_slug !== ''
    ? '/loja/' . rawurlencode($bb_loja_slug) . '/sistema/acesso/'
    : '/sistema/acesso.php';

if (!headers_sent()) {
    header('Location: ' . $destino);
} else {
    echo '<script>window.location=' . json_encode($destino, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';</script>';
}
exit();
