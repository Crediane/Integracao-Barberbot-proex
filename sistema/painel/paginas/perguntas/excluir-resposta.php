<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'respostas';

$id = $_POST['id'];

$query = $pdo->query("SELECT * FROM respostas where id = '$id'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$pergunta = $res[0]['pergunta'];

$pdo->query("DELETE FROM $tabela where id = '$id'");

echo 'Excluído com Sucesso';

$query = $pdo->query("UPDATE perguntas SET respondida = 'Sim' where id = '$pergunta'");

?>