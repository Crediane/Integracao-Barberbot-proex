<?php
require_once __DIR__ . '/../_context_bootstrap.php';
$tabela = 'clientes';
require_once __DIR__ . '/../../../conexao.php';

$id = $_POST['id'];

$pdo->query("UPDATE $tabela SET marketing = 'Não' WHERE id = '$id' ");
echo 'Excluído com Sucesso';
?>