<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once(__DIR__ . '/../../verificar.php');
require_once(__DIR__ . '/../../../conexao.php');

$tabela = 'manutencoes_protese';

$id = isset($_POST['id']) ? trim($_POST['id']) : '';

if ($id == '') {
	echo 'ID inválido';
	exit();
}

$query = $pdo->prepare("DELETE FROM $tabela WHERE id = :id");
$query->bindValue(":id", $id);
$query->execute();

echo 'Excluído com Sucesso';
?>