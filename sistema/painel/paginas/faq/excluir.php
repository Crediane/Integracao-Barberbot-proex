<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../conexao.php';

// ID recebido
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
    echo 'ID inválido.';
    exit;
}

try {
    // Opcional: verificar se existe
    $chk = $pdo->prepare("SELECT 1 FROM faq WHERE id = :id");
    $chk->bindValue(':id', $id, PDO::PARAM_INT);
    $chk->execute();
    if (!$chk->fetchColumn()) {
        echo 'Registro não encontrado.';
        exit;
    }

    // Excluir
    $stmt = $pdo->prepare("DELETE FROM faq WHERE id = :id LIMIT 1");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->rowCount() > 0) {
        echo 'Excluído com Sucesso';
    } else {
        echo 'Nada foi excluído.';
    }
} catch (PDOException $e) {
    // Erro de chave estrangeira (se houver relacionamentos)
    if ($e->getCode() === '23000') {
        echo 'Não é possível excluir: registro possui vínculos.';
    } else {
        echo 'Erro ao excluir: ' . $e->getMessage();
    }
}
