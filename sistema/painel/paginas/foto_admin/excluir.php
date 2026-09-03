<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';

// valida id
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    echo "ID inválido";
    exit();
}

try {
    // busca a imagem atual
    $stmt = $pdo->prepare("SELECT imagem FROM foto_admin WHERE id = :id LIMIT 1");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo "Registro não encontrado";
        exit();
    }

    $img = $row['imagem'] ?? '';
    $path = "../../img/foto_admin/" . basename($img);

    // apaga o registro
    $del = $pdo->prepare("DELETE FROM foto_admin WHERE id = :id");
    $del->bindValue(':id', $id, PDO::PARAM_INT);
    $del->execute();

    // tenta remover o arquivo (silencioso)
    if ($img && file_exists($path)) {
        @unlink($path);
    }

    // mantém a mesma mensagem esperada pelo seu JS
    echo "Excluído com Sucesso";
} catch (Throwable $e) {
    echo "Erro ao excluir";
}
