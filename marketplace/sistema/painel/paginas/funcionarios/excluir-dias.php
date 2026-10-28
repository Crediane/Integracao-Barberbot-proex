<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'dias';

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
	echo 'ID inválido!';
	exit;
}

try {
	// Mesma lógica: deletar pelo id, porém seguro com prepared statement
	$stmt = $pdo->prepare("DELETE FROM {$tabela} WHERE id = :id");
	$stmt->bindValue(':id', $id, PDO::PARAM_INT);
	$stmt->execute();

	echo 'Excluído com Sucesso';
} catch (Throwable $e) {
	echo 'Erro ao Excluir!';
}
?>
