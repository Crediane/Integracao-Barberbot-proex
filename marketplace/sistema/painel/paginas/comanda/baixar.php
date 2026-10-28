<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'receber';
$id_usuario = $_SESSION['id'];

$data_atual = date('Y-m-d');

$id = $_POST['id'];

$query = $pdo->query("SELECT * FROM $tabela where id = '$id'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$funcionario = $res[0]['funcionario'];
$servico = $res[0]['servico'];
$cliente = $res[0]['pessoa'];
$descricao = 'Comissão - '.$res[0]['descricao'];
$valor_conta = $res[0]['valor'];
$pgto = $res[0]['pgto'];

$query = $pdo->query("SELECT * FROM servicos where id = '$servico'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$valor = $res[0]['valor'];
$valorF = number_format($valor, 2, ',', '.');
$comissao = $res[0]['comissao'];
$dias_retorno = $res[0]['dias_retorno'];
$nome_servico = $res[0]['nome'];

$data_retorno = date('Y-m-d', strtotime("+$dias_retorno days",strtotime($data_atual)));

$query = $pdo->query("SELECT * FROM usuarios where id = '$funcionario'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$comissao_func = $res[0]['comissao'];

if($comissao_func > 0){
	$comissao = $comissao_func;
}

if($tipo_comissao == 'Porcentagem'){
	$valor_comissao = ($comissao * $valor_conta) / 100;
}else{
	$valor_comissao = $comissao;
}


$query = $pdo->query("SELECT * FROM formas_pgto where nome = '$pgto'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$valor_taxa = @$res[0]['taxa'];

if($valor_taxa > 0){
	if($taxa_sistema == 'Cliente'){
		$valor_serv = $valor_serv + $valor_serv * ($valor_taxa / 100);
	}else{
		$valor_serv = $valor_serv - $valor_serv * ($valor_taxa / 100);
	}
	
}


//verificar caixa aberto
$query1 = $pdo->query("SELECT * from caixas where operador = '$id_usuario' and data_fechamento is null order by id desc limit 1");
$res1 = $query1->fetchAll(PDO::FETCH_ASSOC);
if(@count($res1) > 0){
	$id_caixa = @$res1[0]['id'];
}else{
	$id_caixa = 0;
}


$pdo->query("UPDATE $tabela SET pago = 'Sim', usuario_baixa = '$id_usuario', data_pgto = curDate(), caixa = '$id_caixa', hora = curTime() where id = '$id'");

if($lanc_comissao != 'Sempre'){
//lançar a conta a pagar para a comissão do funcionário
$pdo->query("INSERT INTO pagar SET descricao = '$descricao', tipo = 'Comissão', valor = '$valor_comissao', data_lanc = curDate(), data_venc = curDate(), usuario_lanc = '$id_usuario', foto = 'sem-foto.jpg', pago = 'Não', funcionario = '$funcionario', servico = '$servico', cliente = '$cliente', caixa = '$id_caixa', hora = curTime(), hora_alerta = '$hora_random'");
}

echo 'Baixado com Sucesso';




//dados do cliente
$query2 = $pdo->query("SELECT * FROM clientes where id = '$cliente' order by id desc limit 2");
$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
$total_cartoes = $res2[0]['cartoes'];
$telefone = $res2[0]['telefone'];
$nome_cliente = $res2[0]['nome'];

$query = $pdo->query("SELECT * FROM agendamentos where cliente = '$cliente'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);
if($total_reg > 0){
for($i=0; $i < $total_reg; $i++){
$hash = $res[$i]['hash'];
if($hash != ""){
	require __DIR__ . '/../../../../ajax/api-excluir.php';
}
}
}

$telefone = '55'.preg_replace('/[ ()-]+/' , '' , $telefone);
if($msg_agendamento == 'Api'){
//agendar mensagem de retorno
	$mensagem = 'Olá *'.$nome_cliente.'*! Esperamos que você esteja bem!%0A O '.$nome_sistema.', estamos ansiosos para saber o que você achou da sua última experiência conosco, especialmente do serviço de *'.$nome_servico.'*%0A%0A';
	$mensagem .= '*Sua opinião é extremamente valiosa para nós*, e adoraríamos receber suas sugestões para tornar sua próxima visita ainda mais especial!%0A Por favor! Se você teve uma boa experiência conosco, ficaremos muito felizes se puder nos avaliar com 5 estrelas no Google!%A *Clique no link e deixe sua avaliação:* https://g.page/r/CW2oJnbXKDzREAE/review%0A%0A  Estamos ansiosos para recebê-lo novamente em breve! Nós *'.$nome_sistema.'*, agradecemos de coração pelo seu apoio!';
	$data_mensagem = $data_retorno.' 08:00:00';
	require __DIR__ . '/../../../../ajax/api-agendar.php';
	
}
 ?>