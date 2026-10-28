<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'itens_assinaturas';

$id = $_POST['id'];
$acao = $_POST['acao'];

$pdo->query("UPDATE $tabela SET ativo = '$acao' where id = '$id'");
echo 'Alterado com Sucesso';
 ?>