<?php
$bb_ajax_context_mode = 'tenant';
$bb_ajax_context_response = 'plain';
require_once __DIR__ . '/_context_bootstrap.php';

$data_hoje_tenant = date('Y-m-d');
$hora_atual_tenant = date('H:i:s');
$telefone = $_POST['telefone'];
$nome = $_POST['nome'];
$funcionario = $_POST['funcionario'];
$hora = @$_POST['hora'];
$servico = $_POST['servico'];
$obs = $_POST['obs'];
$data = @$_POST['data'];
$data_agd = @$_POST['data'];
$hora_do_agd = @$_POST['hora'];
$id = @$_POST['id'];

$hash = "";

if($telefone == $whatsapp_sistema){
	echo 'Insira seu Telefone!';
	exit();
}


$tel_cli = $_POST['telefone'];

$query = $pdo->query("SELECT * FROM usuarios where id = '$funcionario'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$intervalo = $res[0]['intervalo'];

$query = $pdo->query("SELECT * FROM servicos where id = '$servico'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$tempo = $res[0]['tempo'];


$hora_minutos = strtotime("+$tempo minutes", strtotime($hora));			
$hora_final_servico = date('H:i:s', $hora_minutos);

$nova_hora = $hora;


$diasemana = array("Domingo", "Segunda-Feira", "Terça-Feira", "Quarta-Feira", "Quinta-Feira", "Sexta-Feira", "Sabado");
$diasemana_numero = date('w', strtotime($data));
$dia_procurado = $diasemana[$diasemana_numero];

//percorrer os dias da semana que ele trabalha
$query = $pdo->query("SELECT * FROM dias where funcionario = '$funcionario' and dia = '$dia_procurado'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
if(@count($res) == 0){
		echo 'Este Profissional não trabalha neste Dia!!!.';
	exit();
}else{
	$inicio = $res[0]['inicio'];
	$final = $res[0]['final'];
	$inicio_almoco = $res[0]['inicio_almoco'];
	$final_almoco = $res[0]['final_almoco'];
}

// BLOQUEIO DO FUNCIONÁRIO PRIMEIRO
$query = $pdo->query("SELECT * FROM dias_bloqueio where funcionario = '$funcionario' and data = '$data_agd'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
if(@count($res) > 0){
	echo 'Este Profissional não irá trabalhar nesta Data, selecione outra data ou escolha outro Profissional!';
	exit();
}

// DEPOIS VERIFICA BLOQUEIO GERAL
$query = $pdo->query("SELECT * FROM dias_bloqueio where funcionario = '0' and data = '$data_agd'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
if(@count($res) > 0){
	echo 'Não estaremos funcionando nesta Data!!!';
	exit();
}


while (strtotime($nova_hora) < strtotime($hora_final_servico)){
		
		$hora_minutos = strtotime("+$intervalo minutes", strtotime($nova_hora));			
		$nova_hora = date('H:i:s', $hora_minutos);		
		
//VERIFICAR NA TABELA HORARIOS AGD SE TEM O HORARIO NESSA DATA
		$query_agd = $pdo->query("SELECT * FROM horarios_agd where data = '$data' and funcionario = '$funcionario' and horario = '$nova_hora'");
		$res_agd = $query_agd->fetchAll(PDO::FETCH_ASSOC);
		if(@count($res_agd) > 0){
			echo 'Este serviço demora cerca de '.$tempo.' minutos, precisa escolher outro horário, pois neste horários não temos disponibilidade devido a outros agendamentos!';
			exit();
		}



//VERIFICAR NA TABELA AGENDAMENTOS SE TEM O HORARIO NESSA DATA e se tem um intervalo entre o horario marcado e o proximo agendado nessa tabela
		$query_agd = $pdo->query("SELECT * FROM agendamentos where data = '$data' and funcionario = '$funcionario' and hora = '$nova_hora'");
		$res_agd = $query_agd->fetchAll(PDO::FETCH_ASSOC);
		if(@count($res_agd) > 0){
			if($tempo <= $intervalo){

			}else{
				if($hora_final_servico == $res_agd[0]['hora']){
					
				}else{
					echo 'Este serviço demora cerca de '.$tempo.' minutos, precisa escolher outro horário, pois neste horários não temos disponibilidade devido a outros agendamentos!';
						exit();
				}
				
			}
			
		}


		if(strtotime($nova_hora) > strtotime($inicio_almoco) and strtotime($nova_hora) < strtotime($final_almoco)){
		echo 'Este serviço demora cerca de '.$tempo.' minutos, precisa escolher outro horário, pois neste horários não temos disponibilidade devido ao horário de almoço!';
			exit();
	}

}



@$_SESSION['telefone'] = $telefone;

if($hora == ""){
	echo 'Escolha um Horário para Agendar!';
	exit();
}

if($data < $data_hoje_tenant){
	echo 'Escolha uma data igual ou maior que Hoje!';
	exit();
}

//validar horario
$query = $pdo->query("SELECT * FROM agendamentos where data = '$data' and hora = '$hora' and funcionario = '$funcionario'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);
if($total_reg > 0 and $res[0]['id'] != $id){
	echo 'Este horário não está disponível!';
	exit();
}

//Cadastrar o cliente caso não tenha cadastro
$query = $pdo->query("SELECT * FROM clientes where telefone LIKE '$telefone' ");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
if(@count($res) == 0){
	$query = $pdo->prepare("INSERT INTO clientes SET nome = :nome, telefone = :telefone, data_cad = :data_cad, cartoes = '0', alertado = 'Não'");

	$query->bindValue(":nome", "$nome");
	$query->bindValue(":telefone", "$telefone");
	$query->bindValue(":data_cad", $data_hoje_tenant);
	$query->execute();
	$id_cliente = $pdo->lastInsertId();

}else{
	$id_cliente = $res[0]['id'];
}






$dataF = implode('/', array_reverse(explode('-', $data)));
$horaF = date("H:i", strtotime($hora));

if($not_sistema == 'Sim'){
	$mensagem_not = $nome;
	$titulo_not = 'Novo Agendamento '.$dataF.' - '.$horaF;
	$id_usu = $funcionario;
	require __DIR__ . '/../api/notid.php';
} 

$query = $pdo->prepare("SELECT * FROM receber where cliente = :id_cliente and pago = 'Não' and data_venc < :data_hoje");
$query->bindValue(":id_cliente", $id_cliente, PDO::PARAM_INT);
$query->bindValue(":data_hoje", $data_hoje_tenant);
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);

if(@count($res) > 0){

echo 'SUA MENSALIDADE ESTÁ EM ATRASO. POR FAVOR, REALIZE O PAGAMENTO PARA LIBERAR NOSSOS SERVIÇOS!';
exit();
}else{

if($msg_agendamento == 'Api'){

$query = $pdo->query("SELECT * FROM usuarios where id = '$funcionario' ");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$nome_func = $res[0]['nome'];
$tel_func = $res[0]['telefone'];

$query = $pdo->query("SELECT * FROM servicos where id = '$servico' ");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$nome_serv = $res[0]['nome'];

$dataF = implode('/', array_reverse(explode('-', $data)));
$horaF = date("H:i", strtotime($hora));

$mensagem = '_Novo Agendamento_ %0A';
$mensagem .= 'Profissional: *'.$nome_func.'* %0A';
$mensagem .= 'Serviço: *'.$nome_serv.'* %0A';
$mensagem .= 'Data: *'.$dataF.'* %0A';
$mensagem .= 'Hora: *'.$horaF.'* %0A';
$mensagem .= 'Cliente: *'.$nome.'* %0A';
if($obs != ""){
	$mensagem .= 'Obs: *'.$obs.'* %0A';
}

$telefone = '55'.preg_replace('/[ ()-]+/' , '' , $telefone);

require __DIR__ . '/../sistema/painel/apis/texto.php';

if($tel_func != $whatsapp_sistema){
	$telefone = '55'.preg_replace('/[ ()-]+/' , '' , $tel_func);
	require __DIR__ . '/../sistema/painel/apis/texto.php';	
}


$telefone = '55'.preg_replace('/[ ()-]+/' , '' , $tel_cli);
//agendar o alerta de confirmação
$hora_atual = $hora_atual_tenant;
$data_atual = $data_hoje_tenant;
$hora_minutos = strtotime("-$minutos_aviso hours", strtotime($hora));
$nova_hora = date('H:i:s', $hora_minutos);


}
}


$query = $pdo->prepare("SELECT * FROM receber where cliente = :id_cliente and pago = 'Não' and data_venc < :data_hoje");
$query->bindValue(":id_cliente", $id_cliente, PDO::PARAM_INT);
$query->bindValue(":data_hoje", $data_hoje_tenant);
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);

if(@count($res) > 0){

echo 'CLIENTE COM DÉBITO PENDENTE, CONFIRA COM O PROFISSINAL';
exit();

}else{


//marcar o agendamento
$query = $pdo->prepare("INSERT INTO agendamentos SET funcionario = '$funcionario', cliente = '$id_cliente', hora = '$hora', data = '$data_agd', usuario = '0', status = 'Agendado', obs = :obs, data_lanc = :data_lanc, servico = '$servico', hash = '$hash'");

echo 'Agendado com Sucesso';
}	

$query->bindValue(":obs", "$obs");
$query->bindValue(":data_lanc", $data_hoje_tenant);
$query->execute();

$ult_id = $pdo->lastInsertId();
if($msg_agendamento == 'Api'){
if(strtotime($hora_atual) < strtotime($nova_hora) or strtotime($data_atual) != strtotime($data_agd)){

		$mensagem = "*Confirmação de Agendamento*\n";
		$mensagem .= "1. 1️⃣ para confirmar ✅\n";		
		$mensagem .= "2. 2️⃣ para Cancelar ❌\n";		
		$id_envio = $ult_id;
		$data_envio = $data_agd . $nova_hora;
		
		if($minutos_aviso > 0){
			require __DIR__ . '/confirmacao.php';
			$id_hash = $id;		
			$pdo->query("UPDATE agendamentos SET hash = '$id_hash' WHERE id = '$ult_id'");		
		}
	
}
}

while (strtotime($hora) < strtotime($hora_final_servico)){
		
		$hora_minutos = strtotime("+$intervalo minutes", strtotime($hora));			
		$hora = date('H:i:s', $hora_minutos);

		if(strtotime($hora) < strtotime($hora_final_servico)){
			$query = $pdo->query("INSERT INTO horarios_agd SET agendamento = '$ult_id', horario = '$hora', funcionario = '$funcionario', data = '$data_agd'");
		}
	

}

?>