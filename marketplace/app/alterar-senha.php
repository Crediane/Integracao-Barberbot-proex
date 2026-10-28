<?php
require_once __DIR__ . '/tenant_context.php';

if (!headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
}

$senha = (string)($_POST['senha'] ?? '');
$reSenha = (string)($_POST['re_senha'] ?? '');
$tokenReset = trim((string)($_POST['token'] ?? ''));
$emailReset = trim((string)($_POST['email'] ?? ''));

if ($senha === '' || $reSenha === '' || $tokenReset === '' || $emailReset === '') {
    echo 'Dados de recuperação inválidos!';
    exit;
}

if ($senha !== $reSenha) {
    echo 'As senhas são diferentes!!';
    exit;
}

$sessionEmail = trim((string)($_SESSION['temp_reset_email'] ?? ''));
$sessionToken = trim((string)($_SESSION['temp_reset_token'] ?? ''));

if ($sessionEmail === '' || $sessionToken === '' || !hash_equals($sessionEmail, $emailReset) || !hash_equals($sessionToken, $tokenReset)) {
    echo 'Dados de recuperação inválidos!';
    exit;
}

$validate = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email AND token = :token LIMIT 1');
$validate->execute([
    ':email' => $emailReset,
    ':token' => $tokenReset,
]);
$usuarioRow = $validate->fetch(PDO::FETCH_ASSOC);

if (!$usuarioRow) {
    echo 'Dados de recuperação inválidos!';
    exit;
}

$senhaCriptografada = password_hash($senha, PASSWORD_DEFAULT);

$update = $pdo->prepare('UPDATE usuarios SET senha_crip = :senha, token = :novo_token WHERE id = :id AND token = :token_atual');
$update->execute([
    ':senha' => $senhaCriptografada,
    ':novo_token' => '',
    ':id' => (int)$usuarioRow['id'],
    ':token_atual' => $tokenReset,
]);

if ($update->rowCount() < 1) {
    echo 'Não foi possível alterar a senha!';
    exit;
}

unset($_SESSION['temp_reset_email'], $_SESSION['temp_reset_token']);

echo 'Senha alterada com Sucesso';
