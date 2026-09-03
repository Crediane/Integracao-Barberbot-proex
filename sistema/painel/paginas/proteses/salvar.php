<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once(__DIR__ . '/../../verificar.php');
require_once(__DIR__ . '/../../../conexao.php');

$tabela = 'proteses';

$id = isset($_POST['id']) ? trim($_POST['id']) : '';
$cliente = isset($_POST['cliente']) ? trim($_POST['cliente']) : '';
$modelo = isset($_POST['modelo']) ? trim($_POST['modelo']) : '';
$cor = isset($_POST['cor']) ? trim($_POST['cor']) : '';
$densidade = isset($_POST['densidade']) ? trim($_POST['densidade']) : '';
$tamanho = isset($_POST['tamanho']) ? trim($_POST['tamanho']) : '';
$fornecedor = isset($_POST['fornecedor']) ? trim($_POST['fornecedor']) : '';
$observacoes = isset($_POST['observacoes']) ? trim($_POST['observacoes']) : '';

if ($cliente == '') {
	echo 'Selecione um Cliente';
	exit();
}

if ($modelo == '') {
	echo 'Informe o Modelo';
	exit();
}

if ($fornecedor == '') {
	echo 'Selecione um Fornecedor';
	exit();
}

// validar registro duplicado
$query = $pdo->prepare("SELECT * FROM $tabela WHERE cliente = :cliente AND modelo = :modelo");
$query->bindValue(':cliente', $cliente);
$query->bindValue(':modelo', $modelo);
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);

if (count($res) > 0 && $id != $res[0]['id']) {
	echo 'Registro já Cadastrado, escolha outro!!';
	exit();
}

if ($id == '') {
	$query = $pdo->prepare("INSERT INTO $tabela SET 
		cliente = :cliente,
		modelo = :modelo,
		cor = :cor,
		densidade = :densidade,
		tamanho = :tamanho,
		fornecedor = :fornecedor,
		observacoes = :observacoes,
		data_cad = curDate()");
} else {
	$query = $pdo->prepare("UPDATE $tabela SET 
		cliente = :cliente,
		modelo = :modelo,
		cor = :cor,
		densidade = :densidade,
		tamanho = :tamanho,
		fornecedor = :fornecedor,
		observacoes = :observacoes
		WHERE id = :id");
	$query->bindValue(':id', $id);
}

$query->bindValue(':cliente', $cliente);
$query->bindValue(':modelo', $modelo);
$query->bindValue(':cor', $cor);
$query->bindValue(':densidade', $densidade);
$query->bindValue(':tamanho', $tamanho);
$query->bindValue(':fornecedor', $fornecedor);
$query->bindValue(':observacoes', $observacoes);
$query->execute();

echo 'Salvo com Sucesso';
?>