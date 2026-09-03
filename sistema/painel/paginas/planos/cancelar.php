<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'receber';

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$id = $id ? (int)$id : 0;

$id_plano = filter_input(INPUT_POST, 'id_plano', FILTER_VALIDATE_INT);
$id_plano = $id_plano ? (int)$id_plano : 0;

if ($id <= 0 || $id_plano <= 0) {
	http_response_code(400);
	echo 'Parâmetros inválidos';
	exit;
}

$stmt = $pdo->prepare("DELETE FROM $tabela WHERE id = :id");
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->execute();

$stmt2 = $pdo->prepare("UPDATE assinaturas SET cancelado = 'Sim' WHERE id = :id");
$stmt2->bindValue(':id', $id_plano, PDO::PARAM_INT);
$stmt2->execute();

echo 'Excluído com Sucesso';
?>
