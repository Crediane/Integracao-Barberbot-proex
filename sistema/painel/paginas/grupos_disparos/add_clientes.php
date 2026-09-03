<?php  
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'grupos_clientes';

$grupo = $_POST['grupo'];
$id = @$_POST['id'];

// Verifica se o cliente já foi adicionado ao grupo
$query = $pdo->query("SELECT * FROM $tabela WHERE grupo = '$grupo' AND cliente = '$id'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);

if(@count($res) > 0){
	echo 'Cliente já Adicionado!';
	exit();
}

// Adiciona o cliente ao grupo
$query = $pdo->prepare("INSERT INTO $tabela SET grupo = :grupo, cliente = :cliente");
$query->bindValue(":grupo", $grupo);
$query->bindValue(":cliente", $id);
$query->execute();

echo 'Adicionado com Sucesso';
?>
