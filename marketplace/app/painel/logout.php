<?php
require_once __DIR__ . '/../tenant_context.php';

unset(
    $_SESSION['id'],
    $_SESSION['nome'],
    $_SESSION['nivel'],
    $_SESSION['visualizar'],
    $_SESSION['aut_token_portalapp'],
    $_SESSION['auth_tenant_slug']
);
$_SESSION['msg'] = '';
@session_regenerate_id(true);

if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}

$loginUrlJson = json_encode($bb_app_login_url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Saindo...</title></head><body>';
echo '<script>';
echo "try{localStorage.removeItem('id_usu');localStorage.removeItem('senha_usu');}catch(e){}";
echo 'window.location=' . $loginUrlJson . ';';
echo '</script>';
echo '</body></html>';
exit;
