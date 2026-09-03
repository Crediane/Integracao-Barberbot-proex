<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';

$pergunta = $_POST['pergunta'] ?? '';
$resposta = $_POST['resposta'] ?? '';
$id = $_POST['id'] ?? '';

if ($id == "") {
	$query = $pdo->prepare("INSERT INTO faq (pergunta, resposta, criado_em) VALUES (:pergunta, :resposta, curDate())");
} else {
	$query = $pdo->prepare("UPDATE faq SET pergunta = :pergunta, resposta = :resposta WHERE id = :id");
	$query->bindValue(":id", $id);
}

$query->bindValue(":pergunta", $pergunta);
$query->bindValue(":resposta", $resposta);

try {
	$query->execute();
	echo "Salvo com Sucesso";
} catch (Exception $e) {
	echo "Erro ao salvar: " . $e->getMessage();
}
