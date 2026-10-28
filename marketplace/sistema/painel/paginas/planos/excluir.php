<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'assinaturas';

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$id = $id ? (int)$id : 0;

if ($id <= 0) {
	http_response_code(400);
	echo 'Parâmetro inválido';
	exit;
}

$stmt = $pdo->prepare("DELETE FROM $tabela WHERE id = :id");
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->execute();

echo 'Excluído com Sucesso';
?>
