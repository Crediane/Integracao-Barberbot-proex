<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'receber';

$id = $_POST['id'];

$pdo->query("DELETE from $tabela where id = '$id'");
$pdo->query("DELETE from pagar where id_ref = '$id'");

 ?>