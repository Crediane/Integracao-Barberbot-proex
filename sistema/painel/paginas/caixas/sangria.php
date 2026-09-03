<?php 
require_once __DIR__ . '/../_context_bootstrap.php';

$tabela = 'sangrias';

require_once __DIR__ . '/../../../conexao.php';
$id_usuario = $_SESSION['id'];

$data_atual = 'Y-m-d';

$id = $_POST['id'];
$valor_sangria = $_POST['valor_sangria'];


$pdo->query("INSERT $tabela SET usuario = '$id_usuario', valor = '$valor_sangria', data = curDate(), hora = curTime(), caixa = '$id'");


echo 'Salvo com Sucesso';



?>



