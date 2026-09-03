<?php
require_once __DIR__ . '/tenant_context.php';

if (!headers_sent()) {
    header('Content-Type: text/plain; charset=utf-8');
}

$email = trim((string)($_POST['email'] ?? ''));
if ($email === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo 'Informe um email válido!';
    exit;
}

$query = $pdo->prepare('SELECT id, email, telefone FROM usuarios WHERE email = :email LIMIT 1');
$query->execute([':email' => $email]);
$usuarioRow = $query->fetch(PDO::FETCH_ASSOC);

if (!$usuarioRow) {
    echo 'Esse email não está Cadastrado!';
    exit;
}

try {
    $tokenUsuario = bin2hex(random_bytes(32));
} catch (Throwable $e) {
    $tokenUsuario = hash('sha256', uniqid((string)mt_rand(), true));
}

$update = $pdo->prepare('UPDATE usuarios SET token = :token WHERE id = :id');
$update->execute([
    ':token' => $tokenUsuario,
    ':id' => (int)$usuarioRow['id'],
]);

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
    $scheme = 'https';
}
$host = (string)($_SERVER['HTTP_HOST'] ?? '');
$resetLink = $scheme . '://' . $host . $bb_app_base_path . '/resetar-senha.php'
    . '?email=' . rawurlencode($email)
    . '&token=' . rawurlencode($tokenUsuario);

$destinatario = $email;
$assunto = utf8_decode($nome_sistema . ' - Recuperação de Senha');
$mensagem = utf8_decode('Clique no link para atualizar sua senha: ' . $resetLink);
$cabecalhos = 'From: ' . $email_sistema;
@mail($destinatario, $assunto, $mensagem, $cabecalhos);

// Mantém o envio por WhatsApp já existente no sistema, sem alterar sua API.
$telefone = (string)($usuarioRow['telefone'] ?? '');
if ($telefone !== '' && $token !== '' && $instancia !== '') {
    $telefone_envio = '55' . preg_replace('/\D+/', '', $telefone);
    $mensagem = '*' . $nome_sistema . '*%0A%0A';
    $mensagem .= '🤩 _Link para Recuperação de Senha_ %0A%0A';
    $mensagem .= $resetLink;

    require '../painel/apis/texto.php';
}

echo 'Recuperado com Sucesso';
