<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'anotacoes';

$id = $_POST['id'] ?? null;

if ($id && is_numeric($id)) {
    try {
        $stmt = $pdo->prepare("DELETE FROM $tabela WHERE id = :id");
        $stmt->bindValue(":id", $id, PDO::PARAM_INT);
        $stmt->execute();
        echo 'Excluído com Sucesso';
    } catch (Exception $e) {
        echo 'Erro ao excluir: ' . $e->getMessage();
    }
} else {
    echo 'ID inválido';
}
?>
