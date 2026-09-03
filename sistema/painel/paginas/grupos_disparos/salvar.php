<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'grupos_disparos';

$nome = $_POST['nome'];
$id = @$_POST['id'];

// Validação nome (agora só pelo nome, não por empresa)
$query = $pdo->query("SELECT * from $tabela where nome = '$nome'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$id_reg = @$res[0]['id'];
if(@count($res) > 0 and $id != $id_reg){
	echo 'Grupo já Cadastrado!';
	exit();
}

if($id == ""){
    // Removido empresa, pois não existe esta coluna!
    $query = $pdo->prepare("INSERT INTO $tabela SET nome = :nome, ativo = 'Sim'");
}else{
    $query = $pdo->prepare("UPDATE $tabela SET nome = :nome where id = '$id'");
}
$query->bindValue(":nome", $nome);
$query->execute();

echo 'Salvo com Sucesso';
?>
