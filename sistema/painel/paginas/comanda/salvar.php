<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
$tabela = 'comandas';
require_once __DIR__ . '/../../../conexao.php';
$data_atual = date('Y-m-d');
$usuario_logado = @$_SESSION['id'];
$id_usuario = @$_SESSION['id'];

$cliente = $_POST['cliente'];
$valor = $_POST['valor_serv'];
$id = @$_POST['id'];
$obs = @$_POST['obs'];


if($valor == "" or $valor <= 0){
	echo 'Insira serviço ou produto';
	exit();
}

if($id == ""){
	$query = $pdo->prepare("INSERT INTO $tabela SET cliente = :cliente, valor = :valor, data = curDate(), funcionario = '$usuario_logado', status = 'Aberta', obs = :obs");
}else{
	$query = $pdo->prepare("UPDATE $tabela SET cliente = :cliente, valor = :valor, obs = :obs WHERE id = '$id'");
}


//verificar caixa aberto
$query1 = $pdo->query("SELECT * from caixas where operador = '$id_usuario' and data_fechamento is null order by id desc limit 1");
$res1 = $query1->fetchAll(PDO::FETCH_ASSOC);
if(@count($res1) > 0){
	$id_caixa = @$res1[0]['id'];
}else{
	$id_caixa = 0;
}


$query->bindValue(":cliente", "$cliente");
$query->bindValue(":valor", "$valor");
$query->bindValue(":obs", "$obs");
$query->execute();
$ult_id = $pdo->lastInsertId();

$pdo->query("UPDATE receber SET comanda = '$ult_id', caixa = '$id_caixa', hora = curTime() WHERE func_comanda = '$usuario_logado' and comanda = 0 ");

echo 'Salvo com Sucesso*'.$ult_id; 

?>

