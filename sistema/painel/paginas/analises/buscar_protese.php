<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once(__DIR__ . '/../../verificar.php');
require_once(__DIR__ . '/../../../conexao.php');

header('Content-Type: application/json; charset=UTF-8');

$cliente = isset($_POST['cliente']) ? trim($_POST['cliente']) : '';

if ($cliente === '') {
	echo json_encode(array(
		'status' => 'Erro',
		'mensagem' => 'Cliente n00o informado'
	), JSON_UNESCAPED_UNICODE);
	exit();
}

$query = $pdo->prepare("SELECT modelo, cor, densidade, tamanho, observacoes 
	FROM proteses 
	WHERE cliente = :cliente 
	ORDER BY id DESC 
	LIMIT 1");
$query->bindValue(":cliente", $cliente);
$query->execute();

$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = count($res);

if ($total_reg > 0) {

	$modelo = $res[0]['modelo'];
	$cor = $res[0]['cor'];
	$densidade = $res[0]['densidade'];
	$tamanho = $res[0]['tamanho'];
	$observacoes = $res[0]['observacoes'];

	echo json_encode(array(
		'status' => 'Sucesso',
		'modelo' => $modelo,
		'cor' => $cor,
		'densidade' => $densidade,
		'tamanho' => $tamanho,
		'observacoes' => $observacoes
	), JSON_UNESCAPED_UNICODE);

} else {

	echo json_encode(array(
		'status' => 'Vazio',
		'mensagem' => 'Nenhuma prtese encontrada para este cliente'
	), JSON_UNESCAPED_UNICODE);
}
?>