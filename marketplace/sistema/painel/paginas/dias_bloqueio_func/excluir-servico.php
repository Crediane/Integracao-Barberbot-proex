<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'dias_bloqueio';

$id = $_POST['id'];


$pdo->query("DELETE from $tabela where id = '$id'");
echo 'Excluído com Sucesso';
 ?>