<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'usuarios';
if($modo_teste == 'Sim'){
    echo 'Esse recurso não é possível alterar no modo de testes!';
    exit();
}


$id = $_POST['id'];
$acao = $_POST['acao'];

$pdo->query("UPDATE $tabela SET ativo = '$acao' where id = '$id'");
echo 'Alterado com Sucesso';
 ?>