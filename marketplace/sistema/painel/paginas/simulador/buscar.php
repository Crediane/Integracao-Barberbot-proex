<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once(__DIR__ . "/../../../conexao.php");

$tabela = 'simulacoes_protese';

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if($id <= 0){
	echo json_encode(array());
	exit();
}

$query = $pdo->prepare("SELECT * FROM $tabela WHERE id = :id");
$query->bindValue(":id", $id);
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);

if(@count($res) == 0){
	echo json_encode(array());
	exit();
}

echo json_encode(array(
	'foto_original' => $res[0]['foto_original'],
	'imagem_simulada' => $res[0]['imagem_simulada']
));
?>