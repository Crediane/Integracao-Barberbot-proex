<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'grupo_assinaturas';

$id = $_POST['id'];

$pdo->query("DELETE from $tabela where id = '$id'");
$pdo->query("DELETE from itens_assinaturas where grupo = '$id'");

echo 'Excluído com Sucesso';
 ?>