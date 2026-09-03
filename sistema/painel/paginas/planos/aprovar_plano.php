<?php 
$valorF = number_format($valor, 2, ',', '.');
$data_atual = date('Y-m-d');

$pdo->query("UPDATE $tabela SET pago = 'Sim' where id = '$id'");

//buscar dados assinatura
$query2 = $pdo->query("SELECT * FROM assinaturas where id = '$id'");
$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
$cliente = $res2[0]['cliente'];
$grupo = $res2[0]['grupo'];
$item = $res2[0]['item'];
$frequencia = $res2[0]['frequencia'];
$vencimento = $res2[0]['vencimento'];
$dias_frequencia = $res2[0]['frequencia'];
$data_venc = $res2[0]['vencimento'];

$query2 = $pdo->query("SELECT * FROM clientes where id = '$cliente'");
$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
$telefone_cliente = $res2[0]['telefone'];
$nome_cliente = $res2[0]['nome'];

$query2 = $pdo->query("SELECT * FROM grupo_assinaturas where id = '$grupo'");
$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
$nome_assinatura = $res2[0]['nome'];

$query2 = $pdo->query("SELECT * FROM itens_assinaturas where id = '$item'");
$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
$nome_item = $res2[0]['nome'];

//verificar caixa aberto
$query1 = $pdo->query("SELECT * from caixas where operador = '$id_usuario' and data_fechamento is null order by id desc limit 1");
$res1 = $query1->fetchAll(PDO::FETCH_ASSOC);
if(@count($res1) > 0){
	$id_caixa = @$res1[0]['id'];
}else{
	$id_caixa = 0;
}

//lançar o valor recebido
$query = $pdo->prepare("INSERT INTO receber SET descricao = 'Plano Assinatura', tipo = 'Assinatura', valor = :valor, data_lanc = curDate(), data_venc = curDate(), usuario_lanc = '$id_usuario', usuario_baixa = '$id_usuario', foto = 'sem-foto.jpg', pessoa = '$cliente', pago = 'Sim', data_pgto = '$data_pgto', caixa = '$id_caixa', hora = curTime(), pgto = '$forma_pgto', frequencia = '$frequencia', hora_alerta = '$hora_random'");
$query->bindValue(":valor", "$valor");
$query->execute();



if($dias_frequencia == 30 || $dias_frequencia == 31){			
			$novo_vencimento = date('Y-m-d', @strtotime("+1 month",@strtotime($data_venc)));
		}else if($dias_frequencia == 90){			
			$novo_vencimento = date('Y-m-d', @strtotime("+3 month",@strtotime($data_venc)));
		}else if($dias_frequencia == 180){ 
			$novo_vencimento = date('Y-m-d', @strtotime("6 month",@strtotime($data_venc)));
		}else if($dias_frequencia == 360 || $dias_frequencia == 365){ 			
			$novo_vencimento = date('Y-m-d', @strtotime("+12 month",@strtotime($data_venc)));

		}else{			
			$novo_vencimento = date('Y-m-d', @strtotime("+$dias_frequencia days",@strtotime($data_venc)));
		}

		$novo_vencimentoF = implode('/', array_reverse(@explode('-', $novo_vencimento)));

//gerar a cobrança automatizada (proximo módulo)
$query = $pdo->prepare("INSERT INTO receber SET descricao = 'Plano Assinatura', tipo = 'Assinatura', valor = :valor, data_lanc = curDate(), data_venc = '$novo_vencimento', usuario_lanc = '$id_usuario', foto = 'sem-foto.jpg', pessoa = '$cliente', pago = 'Não', caixa = '$id_caixa', hora = curTime(), frequencia = '$frequencia', hora_alerta = '$hora_random', referencia = '$id'");
$query->bindValue(":valor", "$valor");
$query->execute();
$ultima_conta = $pdo->lastInsertId();



//disparar a mensagem via whatsapp
if($msg_agendamento == 'Api'){
	$telefone = '55'.preg_replace('/[ ()-]+/' , '' , $telefone_cliente);
	$mensagem = '✅*PLANO DE ASSINATURA ADQUIRIDO* %0A';
	$mensagem .= '*'.$nome_assinatura.'* %0A';
	$mensagem .= 'Plano: *'.$nome_item.'* %0A';
	$mensagem .= 'Cliente: *'.$nome_cliente.'* %0A';
	$mensagem .= 'Valor: '.$valorF.' %0A';
	require __DIR__ . '/../../../../ajax/api-texto.php';
}


 ?>