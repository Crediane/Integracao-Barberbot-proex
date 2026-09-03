<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
$tabela = 'receber';
require_once __DIR__ . '/../../../conexao.php';
$data_atual = date('Y-m-d');

if (@$_POST['id_usuario'] != "") {
	$usuario_logado = (int)$_POST['id_usuario'];
} else {
$usuario_logado = (int)@$_SESSION['id'];
}

$id_usuario = $usuario_logado;

/*
|------------------------------------------------------------
| Helper: normalizar valores monetários para o banco
| Aceita:
|  - 2,00
|  - 2.00
|  - 1.234,56
|  - 1234.56
| Retorna string numérica com ponto decimal.
|------------------------------------------------------------
*/
function valorParaBanco($valor): string {
	$valor = trim((string)$valor);
	if ($valor === '') return '0';

	// remove caracteres não numéricos (mantém . , e -)
	$valor = preg_replace('/[^0-9\-,\.]/', '', $valor);

	// Se tiver ponto e vírgula, assume formato pt-BR (1.234,56)
	if (strpos($valor, ',') !== false && strpos($valor, '.') !== false) {
		$valor = str_replace('.', '', $valor);
		$valor = str_replace(',', '.', $valor);
	} elseif (strpos($valor, ',') !== false) {
		// Apenas vírgula -> decimal
		$valor = str_replace(',', '.', $valor);
	} 
	// Apenas ponto -> já é decimal (en-US) ou inteiro com ponto (pouco provável)

	// evita múltiplos pontos
	if (substr_count($valor, '.') > 1) {
		$partes = explode('.', $valor);
		$ultimo = array_pop($partes);
		$valor = implode('', $partes) . '.' . $ultimo;
	}

	return $valor;
}

// ------ ENTRADAS ------
$cliente = isset($_POST['cliente']) ? (int)$_POST['cliente'] : 0;

$data_pgto = isset($_POST['data_pgto']) ? $_POST['data_pgto'] : '';
$valor_serv = isset($_POST['valor_serv']) ? $_POST['valor_serv'] : 0;
$valor_serv = valorParaBanco($valor_serv);

$funcionario = $usuario_logado;
$servico = isset($_POST['servico']) ? (int)$_POST['servico'] : 0;
$pgto = isset($_POST['pgto']) ? $_POST['pgto'] : '';
$obs = isset($_POST['obs']) ? $_POST['obs'] : '';

$valor_serv_restante = isset($_POST['valor_serv_agd_restante']) ? $_POST['valor_serv_agd_restante'] : '';
$valor_serv_restante = valorParaBanco($valor_serv_restante);

$pgto_restante = isset($_POST['pgto_restante']) ? $_POST['pgto_restante'] : '';
$data_pgto_restante = isset($_POST['data_pgto_restante']) ? $_POST['data_pgto_restante'] : '';

// se não veio nada, considera zero (mesma lógica)
if ($valor_serv_restante === '' || $valor_serv_restante === null) {
	$valor_serv_restante = '0';
}

$valor_total_servico = (float)$valor_serv + (float)$valor_serv_restante;

if ($cliente <= 0) {
	echo 'Selecione um Cliente!';
	exit();
}

if ($servico <= 0) {
	echo 'Selecione um Serviço!';
	exit();
}

if ($data_pgto == "") {
	echo 'Informe a Data PGTO!';
	exit();
}

// evita warning caso não venha da config
if (!isset($hora_random) || $hora_random == "") {
	$hora_random = date('H:i:s');
}

// ------ DADOS DO SERVIÇO ------
try {
	$stServ = $pdo->prepare("SELECT valor, nome, comissao, dias_retorno FROM servicos WHERE id = :id LIMIT 1");
	$stServ->execute([':id' => $servico]);
	$dados_serv = $stServ->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	$dados_serv = false;
}

if (!$dados_serv) {
	echo 'Serviço não encontrado!';
	exit();
}

$valor = $dados_serv['valor'];
$nome_servico = $dados_serv['nome'];
$comissao = $dados_serv['comissao'];
$descricao = $dados_serv['nome'];
$descricao2 = 'Comissão - ' . $dados_serv['nome'];
$dias_retorno = $dados_serv['dias_retorno'];
$data_retorno = date('Y-m-d', strtotime("+$dias_retorno days", strtotime($data_atual)));

// ------ DADOS DO CLIENTE ------
try {
	$stCli = $pdo->prepare("SELECT telefone, nome, cartoes FROM clientes WHERE id = :id LIMIT 1");
	$stCli->execute([':id' => $cliente]);
	$dados_cliente = $stCli->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	$dados_cliente = false;
}

if (!$dados_cliente) {
	echo 'Cliente não encontrado!';
	exit();
}

$telefone = $dados_cliente['telefone'];
$nome_cliente = $dados_cliente['nome'];

// ====== BUSCAR LINK DE AVALIAÇÃO (GOOGLE) NO BANCO ======
$link_avaliacao_google = 'https://g.page/r/CW2oJnbXKDzREAE/review'; // fallback se não tiver salvo
try {
	$stLink = $pdo->query("SELECT link_avaliacao_google FROM avaliacoes_site WHERE is_config = 1 ORDER BY id DESC LIMIT 1");
	if ($stLink) {
		$rLink = $stLink->fetch(PDO::FETCH_ASSOC);
		if ($rLink && !empty($rLink['link_avaliacao_google'])) {
			$link_avaliacao_google = trim($rLink['link_avaliacao_google']);
		}
	}
} catch (Throwable $e) {
	// usa fallback
}

// ------ COMISSÃO (FUNCIONÁRIO) ------
$comissao_func = 0;
try {
	$stUser = $pdo->prepare("SELECT comissao FROM usuarios WHERE id = :id LIMIT 1");
	$stUser->execute([':id' => $funcionario]);
	$dados_func = $stUser->fetch(PDO::FETCH_ASSOC);
	if ($dados_func && isset($dados_func['comissao'])) {
		$comissao_func = $dados_func['comissao'];
	}
} catch (Throwable $e) {
	$comissao_func = 0;
}

if ($comissao_func > 0) {
	$comissao = $comissao_func;
}

if (@$tipo_comissao == 'Porcentagem') {
	$valor_comissao = ($comissao * $valor_total_servico) / 100;
} else {
	$valor_comissao = $comissao;
}

// ------ TAXAS (FORMA PGTO) ------
$valor_taxa = 0;
if ($pgto != "") {
	try {
		$stPg = $pdo->prepare("SELECT taxa FROM formas_pgto WHERE nome = :nome LIMIT 1");
		$stPg->execute([':nome' => $pgto]);
		$dados_pg = $stPg->fetch(PDO::FETCH_ASSOC);
		if ($dados_pg && isset($dados_pg['taxa'])) {
			$valor_taxa = (float)$dados_pg['taxa'];
		}
	} catch (Throwable $e) {
		$valor_taxa = 0;
	}
}

if ($valor_taxa > 0 && strtotime($data_pgto) <= strtotime($data_atual)) {
	if (@$taxa_sistema == 'Cliente') {
		$valor_serv = (float)$valor_serv + ((float)$valor_serv * ($valor_taxa / 100));
	} else {
		$valor_serv = (float)$valor_serv - ((float)$valor_serv * ($valor_taxa / 100));
	}
}

// taxa do restante (se houver)
$valor_taxa_rest = 0;
if ($pgto_restante != "") {
	try {
		$stPg2 = $pdo->prepare("SELECT taxa FROM formas_pgto WHERE nome = :nome LIMIT 1");
		$stPg2->execute([':nome' => $pgto_restante]);
		$dados_pg2 = $stPg2->fetch(PDO::FETCH_ASSOC);
		if ($dados_pg2 && isset($dados_pg2['taxa'])) {
			$valor_taxa_rest = (float)$dados_pg2['taxa'];
		}
	} catch (Throwable $e) {
		$valor_taxa_rest = 0;
	}
}

if ($valor_taxa_rest > 0 && $data_pgto_restante != "" && strtotime($data_pgto_restante) <= strtotime($data_atual)) {
	if (@$taxa_sistema == 'Cliente') {
		$valor_serv_restante = (float)$valor_serv_restante + ((float)$valor_serv_restante * ($valor_taxa_rest / 100));
	} else {
		$valor_serv_restante = (float)$valor_serv_restante - ((float)$valor_serv_restante * ($valor_taxa_rest / 100));
	}
}

// ------ CAIXA ABERTO ------
$id_caixa = 0;
try {
	$stCx = $pdo->prepare("SELECT id FROM caixas WHERE operador = :op AND data_fechamento IS NULL ORDER BY id DESC LIMIT 1");
	$stCx->execute([':op' => $id_usuario]);
	$rCx = $stCx->fetch(PDO::FETCH_ASSOC);
	if ($rCx) $id_caixa = (int)$rCx['id'];
} catch (Throwable $e) {
	$id_caixa = 0;
}

// ------ PAGAMENTO / COMISSÃO / AGENDAMENTO ------
if (strtotime($data_pgto) <= strtotime($data_atual)) {
	$pago = 'Sim';
	$data_pgto2 = $data_pgto;
	$usuario_baixa = $usuario_logado;

	// lançar a conta a pagar para a comissão do funcionário
	try {
		$stPagar = $pdo->prepare("INSERT INTO pagar 
			SET descricao = :descricao, tipo = 'Comissão', valor = :valor, data_lanc = :data_lanc, data_venc = :data_venc,
				usuario_lanc = :usuario_lanc, foto = 'sem-foto.jpg', pago = 'Não', funcionario = :funcionario,
				servico = :servico, cliente = :cliente, caixa = :caixa, hora = :hora_lanc, hora_alerta = :hora_alerta");
		$stPagar->execute([
			':descricao' => $descricao2,
			':valor' => $valor_comissao,
			':data_lanc' => $data_pgto,
			':data_venc' => $data_pgto,
			':usuario_lanc' => $usuario_logado,
			':funcionario' => $funcionario,
			':servico' => $servico,
			':cliente' => $cliente,
			':caixa' => $id_caixa,
			':hora_lanc' => date('H:i:s'),
			':hora_alerta' => $hora_random,
		]);
	} catch (Throwable $e) {
		// mantém fluxo
	}

	$telefone = '55' . preg_replace('/[ ()-]+/', '', $telefone);
	if (@$msg_agendamento == 'Api') {
		// agendar mensagem de retorno
		$mensagem  = 'Olá *' . $nome_cliente . '*! Tudo bem?%0A%0A';
		$mensagem .= 'Aqui é o *' . @$nome_sistema . '*. Queremos saber como foi sua experiência conosco — especialmente com o serviço de *' . $nome_servico . '*. %0A%0A';
		$mensagem .= 'A sua opinião é muito importante para nós e nos ajuda a manter a qualidade e melhorar cada vez mais.%0A%0A';
		$mensagem .= 'Se você gostou do atendimento, você poderia nos avaliar com *5 estrelas no Google*? ⭐⭐⭐⭐⭐%0A';
		$mensagem .= '*Clique no link e deixe sua avaliação:*%0A' . $link_avaliacao_google . '%0A%0A';
		$mensagem .= 'Desde já, muito obrigado pelo carinho e confiança.%0A';
		$mensagem .= 'Será um prazer te receber novamente!%0A%0A';
		$mensagem .= '— *' . @$nome_sistema . '*';
		$data_mensagem = $data_retorno . ' 09:00';
		require __DIR__ . '/../../../../ajax/api-agendar.php';
	}

} else {
	$pago = 'Não';
	$data_pgto2 = '';
	$usuario_baixa = 0;

	if (@$lanc_comissao == 'Sempre') {
		// lançar a conta a pagar para a comissão do funcionário
		try {
			$stPagar = $pdo->prepare("INSERT INTO pagar 
				SET descricao = :descricao, tipo = 'Comissão', valor = :valor, data_lanc = :data_lanc, data_venc = :data_venc,
					usuario_lanc = :usuario_lanc, foto = 'sem-foto.jpg', pago = 'Não', funcionario = :funcionario,
					servico = :servico, cliente = :cliente, caixa = :caixa, hora = :hora_lanc, hora_alerta = :hora_alerta");
			$stPagar->execute([
				':descricao' => $descricao2,
				':valor' => $valor_comissao,
				':data_lanc' => $data_pgto,
				':data_venc' => $data_pgto,
				':usuario_lanc' => $usuario_logado,
				':funcionario' => $funcionario,
				':servico' => $servico,
				':cliente' => $cliente,
				':caixa' => $id_caixa,
				':hora_lanc' => date('H:i:s'),
				':hora_alerta' => $hora_random,
			]);
		} catch (Throwable $e) {
			// mantém fluxo
		}
	}
}

// ------ RESTANTE (SE HOUVER) ------
if ((float)$valor_serv_restante > 0) {
	if ($data_pgto_restante == "") {
		// mantém mesma lógica: se tem restante, precisa data
		echo 'Informe a Data PGTO Restante!';
		exit();
	}

	if (strtotime($data_pgto_restante) <= strtotime($data_atual)) {
		$pago_restante = 'Sim';
		$data_pgto2_restante = $data_pgto; // mantém comportamento existente
		$usuario_baixa_restante = $usuario_logado;
	} else {
		$pago_restante = 'Não';
		$data_pgto2_restante = '';
		$usuario_baixa_restante = 0;
	}

	// lançar o restante
	try {
		$stRest = $pdo->prepare("INSERT INTO $tabela 
			SET descricao = :descricao, tipo = 'Serviço', valor = :valor, data_lanc = :data_lanc, data_venc = :data_venc,
				data_pgto = :data_pgto, usuario_lanc = :usuario_lanc, usuario_baixa = :usuario_baixa,
				foto = 'sem-foto.jpg', pessoa = :pessoa, pago = :pago, servico = :servico, funcionario = :funcionario,
				obs = :obs, pgto = :pgto, caixa = :caixa, hora = :hora_lanc, hora_alerta = :hora_alerta");
		$stRest->execute([
			':descricao' => $descricao,
			':valor' => $valor_serv_restante,
			':data_lanc' => $data_atual,
			':data_venc' => $data_pgto_restante,
			':data_pgto' => $data_pgto2_restante,
			':usuario_lanc' => $usuario_logado,
			':usuario_baixa' => $usuario_baixa, // mantém comportamento existente
			':pessoa' => $cliente,
			':pago' => $pago_restante,
			':servico' => $servico,
			':funcionario' => $funcionario,
			':obs' => $obs,
			':pgto' => $pgto_restante,
			':caixa' => $id_caixa,
			':hora_lanc' => date('H:i:s'),
			':hora_alerta' => $hora_random,
		]);
	} catch (Throwable $e) {
		// mantém fluxo
	}
}

// ------ RECARREGAR DADOS PARA GARANTIR ------
try {
	$stServ2 = $pdo->prepare("SELECT dias_retorno, nome FROM servicos WHERE id = :id LIMIT 1");
	$stServ2->execute([':id' => $servico]);
	$rServ2 = $stServ2->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	$rServ2 = false;
}

if ($rServ2) {
	$dias_retorno = $rServ2['dias_retorno'];
	$nome_servico = $rServ2['nome'];
}

try {
	$stCli2 = $pdo->prepare("SELECT cartoes FROM clientes WHERE id = :id LIMIT 1");
	$stCli2->execute([':id' => $cliente]);
	$rCli2 = $stCli2->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
	$rCli2 = false;
}

$total_cartoes = $rCli2 ? (int)$rCli2['cartoes'] : 0;
$cartoes = $total_cartoes + 1;
$data_retorno = date('Y-m-d', strtotime("+$dias_retorno days", strtotime($data_atual)));

// ------ LANÇAR RECEBIMENTO PRINCIPAL ------
try {
	$stRec = $pdo->prepare("INSERT INTO $tabela 
		SET descricao = :descricao, tipo = 'Serviço', valor = :valor, data_lanc = :data_lanc, data_venc = :data_venc,
			data_pgto = :data_pgto, usuario_lanc = :usuario_lanc, usuario_baixa = :usuario_baixa,
			foto = 'sem-foto.jpg', pessoa = :pessoa, pago = :pago, servico = :servico, funcionario = :funcionario,
			pgto = :pgto, obs = :obs, caixa = :caixa, hora = :hora_lanc, hora_alerta = :hora_alerta");
	$stRec->execute([
		':descricao' => $nome_servico,
		':valor' => $valor_serv,
		':data_lanc' => $data_atual,
		':data_venc' => $data_pgto,
		':data_pgto' => $data_pgto2,
		':usuario_lanc' => $usuario_logado,
		':usuario_baixa' => $usuario_baixa,
		':pessoa' => $cliente,
		':pago' => $pago,
		':servico' => $servico,
		':funcionario' => $funcionario,
		':pgto' => $pgto,
		':obs' => $obs,
		':caixa' => $id_caixa,
		':hora_lanc' => date('H:i:s'),
		':hora_alerta' => $hora_random,
	]);
} catch (Throwable $e) {
	echo 'Erro ao salvar!';
	exit();
}

// ------ ATUALIZAR CLIENTE ------
try {
	$stUp = $pdo->prepare("UPDATE clientes 
		SET cartoes = :cartoes, data_retorno = :data_retorno, ultimo_servico = :servico, alertado = 'Não'
		WHERE id = :id");
	$stUp->execute([
		':cartoes' => $cartoes,
		':data_retorno' => $data_retorno,
		':servico' => $servico,
		':id' => $cliente,
	]);
} catch (Throwable $e) {
	// não interrompe
}

echo 'Salvo com Sucesso'; 
?>
