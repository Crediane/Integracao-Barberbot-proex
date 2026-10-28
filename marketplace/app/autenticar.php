<?php
require_once __DIR__ . '/tenant_context.php';

if (!function_exists('bb_app_redirect')) {
    function bb_app_redirect(string $url): void
    {
        if (!headers_sent()) {
            header('Location: ' . $url, true, 302);
        } else {
            echo '<script>window.location=' . json_encode($url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . ';</script>';
        }
        exit;
    }
}

if (!function_exists('bb_app_login_error')) {
    function bb_app_login_error(string $message, string $url): void
    {
        $_SESSION['msg'] = $message;
        bb_app_redirect($url);
    }
}

$usuario = trim((string)($_POST['usuario'] ?? ''));
$senha = (string)($_POST['senha'] ?? '');
$salvar = trim((string)($_POST['salvar'] ?? ''));

if ($usuario === '' || $senha === '') {
    bb_app_login_error('Dados Incorretos!', $bb_app_login_url);
}

$query = $pdo->prepare('SELECT * FROM usuarios WHERE email = :email ORDER BY id ASC LIMIT 1');
$query->execute([':email' => $usuario]);
$usuarioRow = $query->fetch(PDO::FETCH_ASSOC);

if (!$usuarioRow || empty($usuarioRow['senha_crip']) || !password_verify($senha, (string)$usuarioRow['senha_crip'])) {
    bb_app_login_error('Dados Incorretos!', $bb_app_login_url);
}

if ((string)($usuarioRow['ativo'] ?? '') !== 'Sim') {
    bb_app_login_error('Seu Acesso foi desativado!', $bb_app_login_url);
}

if ((string)($usuarioRow['atendimento'] ?? '') !== 'Sim') {
    bb_app_login_error('Esse aplicativo é exclusivo para profissionais que realizam atendimento.', $bb_app_login_url);
}

@session_regenerate_id(true);

$_SESSION['nome'] = (string)($usuarioRow['nome'] ?? '');
$_SESSION['id'] = (int)($usuarioRow['id'] ?? 0);
$_SESSION['nivel'] = (string)($usuarioRow['nivel'] ?? '');
$_SESSION['visualizar'] = (string)($usuarioRow['visualizar'] ?? '');
$_SESSION['aut_token_portalapp'] = 'portalapp2024';
$_SESSION['auth_tenant_slug'] = $bb_app_slug;

$storageKeyJson = json_encode($bb_app_storage_email_key, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$emailJson = json_encode($usuario, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$panelUrlJson = json_encode($bb_app_panel_url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$saveEmail = ($salvar === 'Sim');

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Entrando...</title></head><body>';
echo '<script>';
echo 'try {';
if ($saveEmail) {
    echo 'localStorage.setItem(' . $storageKeyJson . ', ' . $emailJson . ');';
} else {
    echo 'localStorage.removeItem(' . $storageKeyJson . ');';
}
echo "localStorage.removeItem('senha_usu');";
echo "localStorage.removeItem('id_usu');";
echo '} catch (e) {}';
echo 'window.location=' . $panelUrlJson . ';';
echo '</script>';
echo '</body></html>';
exit;
