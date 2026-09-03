<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'horarios';

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
	echo "ID inválido!";
	exit;
}

try {
	// Antes: DELETE com variável direto (SQL Injection)
	// Agora: Prepared Statement (mesma lógica)
	$stmt = $pdo->prepare("DELETE FROM {$tabela} WHERE id = :id");
	$stmt->bindValue(':id', $id, PDO::PARAM_INT);
	$stmt->execute();

	echo 'Excluído com Sucesso';
} catch (Exception $e) {
	echo 'Erro ao Excluir!';
}
?>
