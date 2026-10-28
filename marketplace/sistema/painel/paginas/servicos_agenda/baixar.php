<?php
require_once __DIR__ . '/../_context_bootstrap.php';
// ============================================================================
// BarberBot Software - Módulo Serviços da Agenda - Baixar Conta a Receber
// Arquivo: /public_html/sistema/painel/paginas/servicos_agenda/baixar.php
// Ajustes: compatível com Admin do Sistema e Dono do Estabelecimento (bancos separados),
//          evita Warnings PHP 8+ (Undefined array key) e mantém a lógica original.
// Segurança: sanitização e prepared statements (PDO).
// ============================================================================
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'receber';

// ----------------------------------------------------------------------------
// Identificação do usuário logado (compatível com diferentes estruturas de sessão)
// ----------------------------------------------------------------------------
$id_usuario = 0;

if (isset($_SESSION['id']) && is_numeric($_SESSION['id'])) {
	$id_usuario = (int)$_SESSION['id'];
} elseif (isset($_SESSION['id_usuario']) && is_numeric($_SESSION['id_usuario'])) {
	$id_usuario = (int)$_SESSION['id_usuario'];
} elseif (isset($_SESSION['usuario_id']) && is_numeric($_SESSION['usuario_id'])) {
	$id_usuario = (int)$_SESSION['usuario_id'];
} elseif (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
	$id_usuario = (int)$_SESSION['user_id'];
} elseif (isset($_SESSION['usuario']) && is_array($_SESSION['usuario']) && isset($_SESSION['usuario']['id']) && is_numeric($_SESSION['usuario']['id'])) {
	$id_usuario = (int)$_SESSION['usuario']['id'];
} elseif (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id']) && is_numeric($_SESSION['user']['id'])) {
	$id_usuario = (int)$_SESSION['user']['id'];
}

if ($id_usuario <= 0) {
	echo 'Sessão expirada. Faça login novamente.';
	exit();
}

// ----------------------------------------------------------------------------
// POST (evita warnings PHP 8+)
// ----------------------------------------------------------------------------
$data_atual = date('Y-m-d');

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
	echo 'ID inválido!';
	exit();
}

$valor = isset($_POST['valor']) ? (string)$_POST['valor'] : '0';
$valor = str_replace('.', '', $valor);
$valor = str_replace(',', '.', $valor);

$data_pgto = isset($_POST['data_pgto']) ? (string)$_POST['data_pgto'] : '';
$forma_pgto = isset($_POST['pgto']) ? (string)$_POST['pgto'] : '';

// ----------------------------------------------------------------------------
// Buscar conta a receber (mantém lógica, evita $res[0])
// ----------------------------------------------------------------------------
$stConta = $pdo->prepare("SELECT * FROM {$tabela} WHERE id = :id LIMIT 1");
$stConta->execute([':id' => $id]);
$conta = $stConta->fetch(PDO::FETCH_ASSOC);

if (!$conta) {
	echo 'Registro não encontrado!';
	exit();
}

$funcionario  = isset($conta['funcionario']) ? (int)$conta['funcionario'] : 0;
$servico      = isset($conta['servico']) ? (int)$conta['servico'] : 0;
$cliente      = isset($conta['pessoa']) ? (int)$conta['pessoa'] : 0;
$descricao    = 'Comissão - ' . (isset($conta['descricao']) ? (string)$conta['descricao'] : '');
$valor_conta  = isset($conta['valor']) ? (float)$conta['valor'] : 0;
$pgto_reg     = isset($conta['pgto']) ? (string)$conta['pgto'] : '';

// ----------------------------------------------------------------------------
// Buscar serviço (mantém lógica)
// ----------------------------------------------------------------------------
$valor_serv = 0;
$comissao = 0;
$dias_retorno = 0;
$nome_servico = '';

if ($servico > 0) {
	$stServ = $pdo->prepare("SELECT * FROM servicos WHERE id = :id LIMIT 1");
	$stServ->execute([':id' => $servico]);
	$serv = $stServ->fetch(PDO::FETCH_ASSOC);

	if ($serv) {
		$valor_serv = isset($serv['valor']) ? (float)$serv['valor'] : 0;
		$comissao = isset($serv['comissao']) ? (float)$serv['comissao'] : 0;
		$dias_retorno = isset($serv['dias_retorno']) ? (int)$serv['dias_retorno'] : 0;
		$nome_servico = isset($serv['nome']) ? (string)$serv['nome'] : '';
	}
}

$data_retorno = date('Y-m-d', strtotime("+{$dias_retorno} days", strtotime($data_atual)));

// ----------------------------------------------------------------------------
// Buscar comissão do funcionário (mantém lógica)
// ----------------------------------------------------------------------------
$comissao_func = 0;
if ($funcionario > 0) {
	$stUser = $pdo->prepare("SELECT comissao FROM usuarios WHERE id = :id LIMIT 1");
	$stUser->execute([':id' => $funcionario]);
	$user = $stUser->fetch(PDO::FETCH_ASSOC);

	if ($user && isset($user['comissao'])) {
		$comissao_func = (float)$user['comissao'];
	}
}

if ($comissao_func > 0) {
	$comissao = $comissao_func;
}

// ----------------------------------------------------------------------------
// Calcular valor da comissão (mantém regra original)
// ----------------------------------------------------------------------------
if (isset($tipo_comissao) && $tipo_comissao == 'Porcentagem') {
	$valor_comissao = ($comissao * $valor_conta) / 100;
} else {
	$valor_comissao = $comissao;
}

// ----------------------------------------------------------------------------
// Taxa da forma de pagamento (mantém lógica original com $pgto do registro)
// ----------------------------------------------------------------------------
$valor_taxa = 0;
if ($pgto_reg != '') {
	$stForma = $pdo->prepare("SELECT taxa FROM formas_pgto WHERE nome = :nome LIMIT 1");
	$stForma->execute([':nome' => $pgto_reg]);
	$forma = $stForma->fetch(PDO::FETCH_ASSOC);

	if ($forma && isset($forma['taxa'])) {
		$valor_taxa = (float)$forma['taxa'];
	}
}

if ($valor_taxa > 0) {
	if (isset($taxa_sistema) && $taxa_sistema == 'Cliente') {
		$valor_serv = $valor_serv + $valor_serv * ($valor_taxa / 100);
	} else {
		$valor_serv = $valor_serv - $valor_serv * ($valor_taxa / 100);
	}
}

// ----------------------------------------------------------------------------
// Verificar caixa aberto (mantém lógica)
// ----------------------------------------------------------------------------
$id_caixa = 0;
$stCaixa = $pdo->prepare("SELECT id FROM caixas WHERE operador = :op AND data_fechamento IS NULL ORDER BY id DESC LIMIT 1");
$stCaixa->execute([':op' => $id_usuario]);
$cx = $stCaixa->fetch(PDO::FETCH_ASSOC);
if ($cx && isset($cx['id'])) {
	$id_caixa = (int)$cx['id'];
}

// ----------------------------------------------------------------------------
// Dar baixa na conta a receber (mantém lógica)
// ----------------------------------------------------------------------------
$stUp = $pdo->prepare("UPDATE {$tabela}
	SET pago = 'Sim',
		pgto = :pgto,
		valor = :valor,
		usuario_baixa = :usuario_baixa,
		data_pgto = :data_pgto,
		caixa = :caixa,
		hora = curTime()
	WHERE id = :id");

$stUp->execute([
	':pgto' => $forma_pgto,
	':valor' => $valor,
	':usuario_baixa' => $id_usuario,
	':data_pgto' => $data_pgto,
	':caixa' => $id_caixa,
	':id' => $id
]);

// ----------------------------------------------------------------------------
// Lançar comissão em pagar (mantém regra original)
// ----------------------------------------------------------------------------
if (!isset($hora_random) || $hora_random == '') {
	$hora_random = date('H:i:s');
}

if (!isset($lanc_comissao) || $lanc_comissao != 'Sempre') {
	$stIns = $pdo->prepare("INSERT INTO pagar
		SET descricao = :descricao,
			tipo = 'Comissão',
			valor = :valor,
			data_lanc = curDate(),
			data_venc = curDate(),
			usuario_lanc = :usuario_lanc,
			foto = 'sem-foto.jpg',
			pago = 'Não',
			funcionario = :funcionario,
			servico = :servico,
			cliente = :cliente,
			caixa = :caixa,
			hora = curTime(),
			hora_alerta = :hora_alerta");

	$stIns->execute([
		':descricao' => $descricao,
		':valor' => $valor_comissao,
		':usuario_lanc' => $id_usuario,
		':funcionario' => $funcionario,
		':servico' => $servico,
		':cliente' => $cliente,
		':caixa' => $id_caixa,
		':hora_alerta' => $hora_random
	]);
}

echo 'Baixado com Sucesso';

// ----------------------------------------------------------------------------
// Dados do cliente (mantém lógica original)
// ----------------------------------------------------------------------------
$telefone = '';
$nome_cliente = '';
$total_cartoes = 0;

if ($cliente > 0) {
	$stCli = $pdo->prepare("SELECT * FROM clientes WHERE id = :id ORDER BY id DESC LIMIT 1");
	$stCli->execute([':id' => $cliente]);
	$cli = $stCli->fetch(PDO::FETCH_ASSOC);

	if ($cli) {
		$total_cartoes = isset($cli['cartoes']) ? (int)$cli['cartoes'] : 0;
		$telefone = isset($cli['telefone']) ? (string)$cli['telefone'] : '';
		$nome_cliente = isset($cli['nome']) ? (string)$cli['nome'] : '';
	}
}

// ====== BUSCAR LINK DE AVALIAÇÃO (GOOGLE) NO BANCO (mantido) ======
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
	// Mantém fallback
}

// Cancelar cobranças da API para agendamentos do cliente (mantido)
if ($cliente > 0) {
	$stAg = $pdo->prepare("SELECT hash FROM agendamentos WHERE cliente = :cliente");
	$stAg->execute([':cliente' => $cliente]);
	$ags = $stAg->fetchAll(PDO::FETCH_ASSOC);

	if ($ags) {
		for ($i = 0; $i < count($ags); $i++) {
			$hash = isset($ags[$i]['hash']) ? (string)$ags[$i]['hash'] : '';
			if ($hash != "") {
				require __DIR__ . '/../../../../ajax/api-excluir.php';
			}
		}
	}
}

$telefone = '55' . preg_replace('/[ ()-]+/', '', $telefone);

// Agendar mensagem de retorno (mantido)
if (isset($msg_agendamento) && $msg_agendamento == 'Api') {

	$mensagem  = 'Olá *' . $nome_cliente . '*! Tudo bem?%0A%0A';
	$mensagem .= 'Aqui é o *' . $nome_sistema . '*. Queremos saber como foi sua experiência conosco — especialmente com o serviço de *' . $nome_servico . '*.%0A%0A';
	$mensagem .= 'A sua opinião é muito importante para nós e nos ajuda a manter a qualidade e melhorar cada vez mais.%0A%0A';
	$mensagem .= 'Se você gostou do atendimento, você poderia nos avaliar com *5 estrelas no Google*? ⭐⭐⭐⭐⭐%0A';
	$mensagem .= '*Clique no link e deixe sua avaliação:*%0A' . $link_avaliacao_google . '%0A%0A';
	$mensagem .= 'Desde já, muito obrigado pelo carinho e confiança.%0A';
	$mensagem .= 'Será um prazer te receber novamente!%0A%0A';
	$mensagem .= '— *' . $nome_sistema . '*';

	$data_mensagem = $data_retorno . ' 09:00';
	require __DIR__ . '/../../../../ajax/api-agendar.php';
}
?>