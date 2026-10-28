<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'anotacoes';

$id = $_POST['id'] ?? '';

if($id == ''){
    echo 'ID inválido';
    exit;
}

try {
    $query = $pdo->prepare("UPDATE $tabela SET status_acerto = 'Baixado' WHERE id = :id");
    $query->bindValue(':id', $id, PDO::PARAM_INT);
    $query->execute();

    echo 'Baixa registrada com sucesso';
} catch (Exception $e) {
    echo 'Erro ao registrar baixa: ' . $e->getMessage();
}
