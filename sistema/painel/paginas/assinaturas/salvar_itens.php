<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'itens_assinaturas';

$id = $_POST['id_item'];
$nome = $_POST['nome'];
$valor = $_POST['valor'];
$grupo = $_POST['id'];
$servico = isset($_POST['servico']) ? (int)$_POST['servico'] : 0;

$valor = str_replace(',', '.', $valor);

$c1 = $_POST['c1'];
$c2 = $_POST['c2'];
$c3 = $_POST['c3'];
$c4 = $_POST['c4'];
$c5 = $_POST['c5'];
$c6 = $_POST['c6'];
$c7 = $_POST['c7'];
$c8 = $_POST['c8'];
$c9 = $_POST['c9'];
$c10 = $_POST['c10'];
$c11 = $_POST['c11'];
$c12 = $_POST['c12'];

//validar nome
$query = $pdo->query("SELECT * from $tabela where nome = '$nome' and grupo = '$grupo'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
if(@count($res) > 0 and $id != $res[0]['id']){
	echo 'Nome do item já Cadastrado, escolha outro!!';
	exit();
}


if($id == ""){
	$query = $pdo->prepare("INSERT INTO $tabela SET nome = :nome, ativo = 'Sim', valor = :valor, servico = :servico, c1 = '$c1', c2 = '$c2', c3 = '$c3', c4 = '$c4', c5 = '$c5', c6 = '$c6', c7 = '$c7', c8 = '$c8', c9 = '$c9', c10 = '$c10', c11 = '$c11', c12 = '$c12', grupo = '$grupo'");
}else{
	$query = $pdo->prepare("UPDATE $tabela SET nome = :nome, valor = :valor, servico = :servico, c1 = '$c1', c2 = '$c2', c3 = '$c3', c4 = '$c4', c5 = '$c5', c6 = '$c6', c7 = '$c7', c8 = '$c8', c9 = '$c9', c10 = '$c10', c11 = '$c11', c12 = '$c12' WHERE id = '$id'");
}

$query->bindValue(":nome", "$nome");
$query->bindValue(":valor", "$valor");
$query->bindValue(":servico", $servico, PDO::PARAM_INT);
$query->execute();

echo 'Salvo com Sucesso';
 ?>