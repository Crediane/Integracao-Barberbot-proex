<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
$tabela = 'grupos_clientes';
require_once __DIR__ . '/../../../conexao.php';

$id = $_POST['id'];

$pdo->query("DELETE FROM $tabela WHERE id = '$id' ");
echo 'Excluído com Sucesso';
?>