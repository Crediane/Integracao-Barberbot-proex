<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'cobrancas';

$id = $_POST['id'];
$acao = $_POST['acao'];




if ($acao == 'Sim') {
	$frequencia = 'Mensal';
}else{
	$frequencia = 'Nenhuma';
}



if ($acao == 'Sim') {


	$pdo->query("UPDATE $tabela SET ativo = '$acao', frequencia = '$frequencia'  where id = '$id'");
}else{


	$pdo->query("UPDATE $tabela SET ativo = '$acao', frequencia = '$frequencia' where id = '$id'");
}


echo 'Alterado com Sucesso';
 ?>