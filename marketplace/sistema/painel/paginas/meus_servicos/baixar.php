<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'receber';

$id_usuario = $_SESSION['id'] ?? 0;

$data_atual = date('Y-m-d');

$id = $_POST['id'] ?? '';
$valor = $_POST['valor'] ?? '';
$data_pgto = $_POST['data_pgto'] ?? date('Y-m-d');
$forma_pgto = $_POST['pgto'] ?? '';

if ($id == '') {
	echo 'ID inválido!';
	exit();
}

// normaliza valor: "1.234,56" -> "1234.56"
$valor = str_replace('.', '', $valor);
$valor = str_replace(',', '.', $valor);
if ($valor === '' || !is_numeric($valor)) $valor = 0;

$hora_random = date('H:i:s');

// buscar conta
$query = $pdo->prepare("SELECT * FROM $tabela WHERE id = :id LIMIT 1");
$query->execute([':id' => $id]);
$res = $query->fetch(PDO::FETCH_ASSOC);

if(!$res){
	echo 'Registro não encontrado!';
	exit();
}

$funcionario = $res['funcionario'] ?? 0;
$servico     = $res['servico'] ?? 0;
$cliente     = $res['pessoa'] ?? 0;
$descricao   = 'Comissão - '.($res['descricao'] ?? '');
$valor_conta = $res['valor'] ?? 0;
$pgto_atual  = $res['pgto'] ?? '';

if($forma_pgto == ''){
	$forma_pgto = $pgto_atual;
}

// se não veio valor, usa o da conta
if($valor <= 0){
	$valor = $valor_conta;
	if($valor <= 0 && isset($res['valor2']) && $res['valor2'] > 0){
		$valor = $res['valor2'];
	}
}

// buscar serviço
$query = $pdo->prepare("SELECT * FROM servicos WHERE id = :id LIMIT 1");
$query->execute([':id' => $servico]);
$resServ = $query->fetch(PDO::FETCH_ASSOC);

$valor_serv   = $resServ['valor'] ?? 0;
$comissao     = $resServ['comissao'] ?? 0;
$dias_retorno = $resServ['dias_retorno'] ?? 0;
$nome_servico = $resServ['nome'] ?? '';

$data_retorno = date('Y-m-d', strtotime("+$dias_retorno days", strtotime($data_atual)));

// comissão do funcionário (se existir sobrescreve)
$query = $pdo->prepare("SELECT * FROM usuarios WHERE id = :id LIMIT 1");
$query->execute([':id' => $funcionario]);
$resFunc = $query->fetch(PDO::FETCH_ASSOC);
$comissao_func = $resFunc['comissao'] ?? 0;

if($comissao_func > 0){
	$comissao = $comissao_func;
}

if(isset($tipo_comissao) && $tipo_comissao == 'Porcentagem'){
	$valor_comissao = ($comissao * $valor_conta) / 100;
}else{
	$valor_comissao = $comissao;
}

// taxa forma pgto
$query = $pdo->prepare("SELECT * FROM formas_pgto WHERE nome = :nome LIMIT 1");
$query->execute([':nome' => $pgto_atual]);
$resPg = $query->fetch(PDO::FETCH_ASSOC);
$valor_taxa = $resPg['taxa'] ?? 0;

if($valor_taxa > 0){
	if(isset($taxa_sistema) && $taxa_sistema == 'Cliente'){
		$valor_serv = $valor_serv + ($valor_serv * ($valor_taxa / 100));
	}else{
		$valor_serv = $valor_serv - ($valor_serv * ($valor_taxa / 100));
	}
}

// verificar caixa aberto
$query1 = $pdo->prepare("SELECT id FROM caixas WHERE operador = :op AND data_fechamento IS NULL ORDER BY id DESC LIMIT 1");
$query1->execute([':op' => $id_usuario]);
$res1 = $query1->fetch(PDO::FETCH_ASSOC);
$id_caixa = $res1 ? $res1['id'] : 0;

// baixa a conta
$up = $pdo->prepare("UPDATE $tabela 
	SET pago = 'Sim',
		pgto = :pgto,
		valor = :valor,
		usuario_baixa = :usuario,
		data_pgto = :data_pgto,
		caixa = :caixa,
		hora = :hora_pgto
	WHERE id = :id");
$up->execute([
	':pgto' => $forma_pgto,
	':valor' => $valor,
	':usuario' => $id_usuario,
	':data_pgto' => $data_pgto,
	':caixa' => $id_caixa,
	':hora_pgto' => date('H:i:s'),
	':id' => $id
]);

// lançar comissão (mantém lógica do sistema)
if(isset($lanc_comissao) && $lanc_comissao != 'Sempre'){
	$ins = $pdo->prepare("INSERT INTO pagar 
		SET descricao = :descricao,
			tipo = 'Comissão',
			valor = :valor,
			data_lanc = :data_lanc,
			data_venc = :data_venc,
			usuario_lanc = :usuario,
			foto = 'sem-foto.jpg',
			pago = 'Não',
			funcionario = :funcionario,
			servico = :servico,
			cliente = :cliente,
			caixa = :caixa,
			hora = :hora_lanc,
			hora_alerta = :hora_alerta");
	$ins->execute([
		':descricao' => $descricao,
		':valor' => $valor_comissao,
		':data_lanc' => date('Y-m-d'),
		':data_venc' => date('Y-m-d'),
		':usuario' => $id_usuario,
		':funcionario' => $funcionario,
		':servico' => $servico,
		':cliente' => $cliente,
		':caixa' => $id_caixa,
		':hora_lanc' => date('H:i:s'),
		':hora_alerta' => $hora_random
	]);
}

echo 'Baixado com Sucesso';

// =================== (resto do fluxo original: cliente / api / avaliação) ===================
// Se seu sistema usa as variáveis abaixo, mantém como estava no padrão.
// Se não usar, pode remover esta parte.

$query2 = $pdo->prepare("SELECT * FROM clientes WHERE id = :id ORDER BY id DESC LIMIT 1");
$query2->execute([':id' => $cliente]);
$res2 = $query2->fetch(PDO::FETCH_ASSOC);

$total_cartoes = $res2['cartoes'] ?? 0;
$telefone = $res2['telefone'] ?? '';
$nome_cliente = $res2['nome'] ?? '';

$link_avaliacao_google = 'https://g.page/r/CW2oJnbXKDzREAE/review';
try {
	$stLink = $pdo->query("SELECT link_avaliacao_google FROM avaliacoes_site WHERE is_config = 1 ORDER BY id DESC LIMIT 1");
	if ($stLink) {
		$rLink = $stLink->fetch(PDO::FETCH_ASSOC);
		if ($rLink && !empty($rLink['link_avaliacao_google'])) {
			$link_avaliacao_google = trim($rLink['link_avaliacao_google']);
		}
	}
} catch (Throwable $e) {}

$query = $pdo->prepare("SELECT * FROM agendamentos WHERE cliente = :cliente");
$query->execute([':cliente' => $cliente]);
$resAg = $query->fetchAll(PDO::FETCH_ASSOC);
for($i=0; $i < @count($resAg); $i++){
	$hash = $resAg[$i]['hash'] ?? '';
	if($hash != ""){
		require __DIR__ . '/../../../../ajax/api-excluir.php';
	}
}

$telefone = '55'.preg_replace('/[ ()-]+/' , '' , $telefone);

if(isset($msg_agendamento) && $msg_agendamento == 'Api'){
	$mensagem  = 'Olá *'.$nome_cliente.'*! Tudo bem?%0A%0A';
	$mensagem .= 'Aqui é o *'.$nome_sistema.'*. Queremos saber como foi sua experiência conosco — especialmente com o serviço de *'.$nome_servico.'*.%0A%0A';
	$mensagem .= 'Se você gostou do atendimento, você poderia nos avaliar com *5 estrelas no Google*? ⭐⭐⭐⭐⭐%0A';
	$mensagem .= '*Clique no link e deixe sua avaliação:*%0A'.$link_avaliacao_google.'%0A%0A';
	$mensagem .= '— *'.$nome_sistema.'*';
	$data_mensagem = $data_retorno.' 09:00';
	require __DIR__ . '/../../../../ajax/api-agendar.php';
}
?>
