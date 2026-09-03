<?php
// /public_html/sistema/painel_cliente/editar-perfil.php
// Atualiza exclusivamente o perfil do cliente autenticado no banco individual da loja atual.

require_once __DIR__ . '/verificar.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo 'Método não permitido.';
    exit();
}

$id_cliente = (int)($_SESSION['id'] ?? 0);
if ($id_cliente <= 0) {
    http_response_code(403);
    echo 'Acesso negado.';
    exit();
}

$nome       = trim((string)filter_input(INPUT_POST, 'nome', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$telefone   = trim((string)filter_input(INPUT_POST, 'telefone', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$cpf        = trim((string)filter_input(INPUT_POST, 'cpf', FILTER_SANITIZE_FULL_SPECIAL_CHARS));
$senha      = trim((string)filter_input(INPUT_POST, 'senha', FILTER_UNSAFE_RAW));
$conf_senha = trim((string)filter_input(INPUT_POST, 'conf_senha', FILTER_UNSAFE_RAW));
$endereco   = trim((string)filter_input(INPUT_POST, 'endereco', FILTER_SANITIZE_FULL_SPECIAL_CHARS));

if ($nome === '') {
    echo 'Informe o nome.';
    exit();
}

if ($senha === '') {
    echo 'Informe a senha.';
    exit();
}

if ($senha !== $conf_senha) {
    echo 'As senhas são diferentes!';
    exit();
}

$senha_crip = password_hash($senha, PASSWORD_DEFAULT);

try {
    $query = $pdo->prepare(
        "UPDATE clientes
            SET nome = :nome,
                telefone = :telefone,
                cpf = :cpf,
                senha_crip = :senha_crip,
                endereco = :endereco
          WHERE id = :id
          LIMIT 1"
    );

    $query->bindValue(':nome', $nome, PDO::PARAM_STR);
    $query->bindValue(':telefone', $telefone, PDO::PARAM_STR);
    $query->bindValue(':cpf', $cpf, PDO::PARAM_STR);
    $query->bindValue(':senha_crip', $senha_crip, PDO::PARAM_STR);
    $query->bindValue(':endereco', $endereco, PDO::PARAM_STR);
    $query->bindValue(':id', $id_cliente, PDO::PARAM_INT);
    $query->execute();

    echo 'Editado com Sucesso';
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Erro ao atualizar perfil.';
}
