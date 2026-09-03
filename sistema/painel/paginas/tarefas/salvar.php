<?php
require_once __DIR__ . '/../_context_bootstrap.php';
$id_usuario = @$_SESSION['id']; 
$tabela = 'tarefas';
require_once __DIR__ . '/../../../conexao.php';

$data_atual = 'Y-m-d';
$hora_atual = date("H:i:s");

$descricao = $_POST['descricao'];
$data = $_POST['data'];
$hora = $_POST['hora'];
$hora_alerta = $_POST['hora_alerta'];
$usuario_tarefa = $_POST['usuario_tarefa'];
$prioridade = $_POST['prioridade'];
$id = $_POST['id'];

$dataF = implode('/', array_reverse(@explode('-', $data)));
$horaF = date("H:i", @strtotime($hora));


//validacao hora
$query = $pdo->query("SELECT * from $tabela where data = '$data' and hora = '$hora' and usuario = '$usuario_tarefa'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$id_reg = @$res[0]['id'];
if(@count($res) > 0 and $id != $id_reg){
	echo 'Horário já Agendado!';
	exit();
}


if(strtotime($hora_alerta) >= strtotime($hora)){
	echo 'A hora do alerta tem que ser menor que a hora do agendamento da tarefa!';
	exit();
}


$query = $pdo->query("SELECT * from usuarios where id = '$usuario_tarefa'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$telefone_usuario = @$res[0]['telefone'];
$nome_usuario = @$res[0]['nome'];




if($id == ""){
$query = $pdo->prepare("INSERT INTO $tabela SET usuario = '$usuario_tarefa', usuario_lanc = '$id_usuario', data = '$data', hora = '$hora', hora_mensagem = '$hora_alerta', descricao = :descricao, status = 'Agendada', prioridade = '$prioridade' ");
	
}else{
$query = $pdo->prepare("UPDATE $tabela SET data = '$data', hora = '$hora', hora_mensagem = '$hora_alerta', descricao = :descricao, prioridade = '$prioridade' where id = '$id'");
}
$query->bindValue(":descricao", "$descricao");

$query->execute();
$ultimo_id = $pdo->lastInsertId();


// extrair o primiero nome
$primeiroNome = explode(" ", $nome_usuario);


$hora = date('H');

if ($hora < 12 && $hora >= 6)
   $saudacao = "Bom Dia";

if ($hora >= 12 && $hora < 18)
   $saudacao = "Boa Tarde";

if ($hora >= 18 && $hora <= 23)
    $saudacao = "Boa Noite";
if ($hora < 6 && $hora >= 0)
   $saudacao = "Boa madrugada";


$emoji = json_decode('"\uD83D\uDE00"');



//recuperar hash
$query = $pdo->query("SELECT * FROM $tabela where id = '$id'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$hash = @$res[0]['hash'];

if($hash != ""){
	require __DIR__ . '/../../apis/cancelar_agendamento.php';
}


	//enviar whatsapp
if($api_whatsapp != 'Não' and $telefone_usuario != '' and $hora_alerta != ''){


	$telefone_envio = '55'.preg_replace('/[ ()-]+/' , '' , $telefone_usuario);
	

	$mensagem = $saudacao.' *'.$primeiroNome[0].' '.$emoji.'*%0A%0A';

	$mensagem .= '*'.$nome_sistema.'*%0A%0A';

	

	$mensagem .= '_Lembrete de Tarefa Agendada_ %0A';
	$mensagem .= '*Hora:* '.$horaF.' %0A';
	$mensagem .= '*Data:* '.$dataF.' %0A';
	$mensagem .= '*Descrição:* '.$descricao.' %0A';
		
	$data_agd = $data.' '.$hora_alerta;
	require __DIR__ . '/../../apis/agendar.php';

	$pdo->query("UPDATE $tabela SET hash = '$hash' where id = '$ultimo_id'");
	
}

echo 'Salvo com Sucesso';
 ?>