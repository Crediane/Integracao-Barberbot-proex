<?php 

$id_conta = @$_GET['id_agd'];


$loja = isset($_GET['loja']) ? preg_replace('/[^a-z0-9_-]/i', '', $_GET['loja']) : '';
if ($loja !== '') {
    $_SESSION['tenant_slug'] = $loja;
    $_GET['loja'] = $loja;
}
$REDIR_MEUS = ($loja !== '') ? ('/loja/' . $loja . '/meus-agendamentos') : '../meus-agendamentos.php';

if($id_conta != ""){
	  if(@$porc_servico > 0){ 
	  	echo 'Faça o pagamento antes de ir para o agendamento';
	  	exit();
	  }
	 require("../sistema/conexao.php");
	 $valor_pago = '0';
	 $query = $pdo->query("SELECT * FROM agendamentos_temp where id = '$id_conta'");
}else{
	 $query = $pdo->query("SELECT * FROM agendamentos_temp where ref_pix = '$ref_pix'");
}
	$res = $query->fetchAll(PDO::FETCH_ASSOC);
	$total_reg = @count($res);
	$cliente = $res[0]['cliente'];
	$servico = $res[0]['servico'];
	$funcionario = $res[0]['funcionario'];
	$data = $res[0]['data'];
	$hora = $res[0]['hora'];
	$obs = $res[0]['obs'];
	$data_lanc = $res[0]['data_lanc'];
	$usuario = $res[0]['usuario'];
	$status = $res[0]['status'];	
	$hash = $res[0]['hash'];
	$ref_pix = $res[0]['ref_pix'];
	$data_agd = $res[0]['data'];
	$hora_do_agd = $res[0]['hora'];
	$valor = $res[0]['valor_pago'];

	if($valor > 0){
		$valor_pago = $valor;
	}

	if(@$forma_pgto == "pix"){
		$forma_pgto = "Pix";
	}else{
		$forma_pgto = "Cartão de Crédito";
	}

	$query = $pdo->query("SELECT * FROM servicos where id = '$servico' ");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$nome_serv = @$res[0]['nome'];
$tempo = @$res[0]['tempo'];

$servico_conc = $nome_serv." (Site)";

       



        $query = $pdo->query("INSERT INTO agendamentos SET funcionario = '$funcionario', cliente = '$cliente', hora = '$hora', data = '$data', usuario = '0', status = 'Agendado', obs = '$obs', data_lanc = curDate(), servico = '$servico', hash = '$hash', ref_pix = '$ref_pix', valor_pago = '$valor_pago'");

        $ult_id = $pdo->lastInsertId();

        if($id_conta == ""){
         $pdo->query("INSERT INTO receber SET descricao = '$servico_conc', tipo = 'Serviço', valor = '$valor_pago', data_lanc = curDate(), data_venc = curDate(), data_pgto = curDate(), usuario_lanc = '0', usuario_baixa = '0', foto = 'sem-foto.jpg', pessoa = '$cliente', pago = 'Sim', servico = '$servico', funcionario = '$funcionario', obs = '', pgto = '$forma_pgto', referencia = '$ult_id', hora = curTime(), hora_alerta = '$hora_random'");  
     	}




$dataF = implode('/', array_reverse(explode('-', $data)));
$horaF = date("H:i", @strtotime($hora));       



$query = $pdo->query("SELECT * FROM usuarios where id = '$funcionario'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$intervalo = @$res[0]['intervalo'];
$nome_func = @$res[0]['nome'];
$tel_func = @$res[0]['telefone'];

$hora_minutos = @strtotime("+$tempo minutes", @strtotime($hora));			
$hora_final_servico = date('H:i:s', $hora_minutos);


if($msg_agendamento == 'Api'){


$query = $pdo->query("SELECT * FROM clientes where id = '$cliente' ");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$nome = $res[0]['nome'];
$telefone = $res[0]['telefone'];
$tel_cli = $res[0]['telefone'];




$dataF = implode('/', array_reverse(explode('-', $data)));
$horaF = date("H:i", @strtotime($hora));

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

require('api-texto.php');

if($tel_func != $whatsapp_sistema){
	$telefone = '55'.preg_replace('/[ ()-]+/' , '' , $tel_func);
	require('api-texto.php');
}


$telefone = '55'.preg_replace('/[ ()-]+/' , '' , $tel_cli);
//agendar o alerta de confirmação
$hora_atual = date('H:i:s');
$data_atual = date('Y-m-d');
$hora_minutos = @strtotime("-$minutos_aviso hours", @strtotime($hora));
$nova_hora = date('H:i:s', $hora_minutos);



if(@strtotime($hora_atual) < @strtotime($nova_hora) or @strtotime($data_atual) != @strtotime($data_agd)){

		$mensagem = '*Confirmação de Agendamento* ';
		$mensagem .= '                              Profissional: *'.$nome_func.'*';
		$mensagem .= '                                         Serviço: *'.$nome_serv.'*';
		$mensagem .= '                                               	       Data: *'.$dataF.'*';
		$mensagem .= '                                               	       Hora: *'.$horaF.'*';
		$mensagem .= '                                                             ';
		$mensagem .= '                                 _(Digite o número com a opção desejada)_';
		$mensagem .= '                                 1.  Digite 1️⃣ para confirmar ✅';		
		$mensagem .= '                                 2.  Digite 2️⃣ para Cancelar ❌';
		
		$id_envio = $ult_id;
		$data_envio = $data_agd.' '.$hora_do_agd;
		
		if($minutos_aviso > 0){
			require("confirmacao.php");
			$id_hash = $id;		
			$pdo->query("UPDATE agendamentos SET hash = '$id_hash' WHERE id = '$ult_id'");		
		}
	
}


}



while (@strtotime($hora) < @strtotime($hora_final_servico)){
		
		$hora_minutos = @strtotime("+$intervalo minutes", @strtotime($hora));			
		$hora = date('H:i:s', $hora_minutos);

		if(@strtotime($hora) < @strtotime($hora_final_servico)){
			$query = $pdo->query("INSERT INTO horarios_agd SET agendamento = '$ult_id', horario = '$hora', funcionario = '$funcionario', data = '$data_agd'");
		}
	

}


if($id_conta != ""){
	$query = $pdo->query("DELETE FROM agendamentos_temp where id = '$id_conta'");
	echo "<script>window.location='{$REDIR_MEUS}'</script>";
}else{
	$query = $pdo->query("DELETE FROM agendamentos_temp where ref_pix = '$ref_pix'");
}


 ?>