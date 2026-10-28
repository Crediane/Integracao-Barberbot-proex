<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'grupo_assinaturas';

$id = $_POST['id'];
$nome = $_POST['nome'];



//validar nome
$query = $pdo->query("SELECT * from $tabela where nome = '$nome'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
if(@count($res) > 0 and $id != $res[0]['id']){
	echo 'Descrição do grupo já Cadastrado, escolha outro!!';
	exit();
}


if($id == ""){
	$query = $pdo->prepare("INSERT INTO $tabela SET nome = :nome, ativo = 'Sim'");
}else{
	$query = $pdo->prepare("UPDATE $tabela SET nome = :nome WHERE id = '$id'");
}

$query->bindValue(":nome", "$nome");
$query->execute();

echo 'Salvo com Sucesso';
 ?>