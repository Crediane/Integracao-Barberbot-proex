<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
$tabela = 'receber';

// -----------------------------------------------------------------------------
// Bootstrap mínimo de sessão/tenant ANTES da conexão.
// Ponto do bug:
//   este endpoint é chamado por AJAX em /sistema/..., mas a tela está em
//   /loja/{slug}/prof/painel/. Se a conexão for carregada antes do contexto da
//   loja, a baixa pode cair no contexto errado ou sem sessão do profissional.
// -----------------------------------------------------------------------------

if (!function_exists('bb_agenda_limpar_slug')) {
	function bb_agenda_limpar_slug($valor) {
		$valor = strtolower(trim((string) $valor));
		return preg_replace('/[^a-z0-9_-]/', '', $valor);
	}
}

$__bb_reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$__bb_refPath = '';
$__bb_reqSlug = '';
if (!empty($_GET['loja'])) {
	$__bb_reqSlug = bb_agenda_limpar_slug($_GET['loja']);
} elseif (!empty($_POST['loja'])) {
	$__bb_reqSlug = bb_agenda_limpar_slug($_POST['loja']);
} elseif (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath, $mSlug)) {
	$__bb_reqSlug = bb_agenda_limpar_slug($mSlug[1]);
} elseif (!empty($__bb_refPath) && preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_refPath, $mSlugRef)) {
	$__bb_reqSlug = bb_agenda_limpar_slug($mSlugRef[1]);
}

$__bb_tenantSessionName = 'BB_TENANT';
if ($__bb_reqSlug !== '') {
	$__bb_tenantSessionName = 'BB_TENANT_' . strtoupper(substr(sha1($__bb_reqSlug), 0, 12));

	// Deixa o slug disponível para a conexão dinâmica do tenant.
	$_GET['loja'] = $__bb_reqSlug;
	$_POST['loja'] = $__bb_reqSlug;
	$_REQUEST['loja'] = $__bb_reqSlug;
}

$__bb_lojaByPath = (bool) preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath)
				|| (bool) preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_refPath);
$__bb_hasLoja = $__bb_lojaByPath || $__bb_reqSlug !== '';
$__bb_isSistema = (strpos($__bb_reqPath, '/sistema') === 0) || (strpos($__bb_refPath, '/sistema') === 0);
$__bb_forceMaster = ($__bb_isSistema && !$__bb_hasLoja);

$__bb_desiredSession = $__bb_forceMaster ? 'BB_MASTER' : ($__bb_hasLoja ? $__bb_tenantSessionName : 'BB_SITE');

if (session_status() === PHP_SESSION_ACTIVE) {
	if (session_name() !== $__bb_desiredSession) {
		session_write_close();
		@session_name($__bb_desiredSession);
		if (!empty($_COOKIE[$__bb_desiredSession])) {
			@session_id((string) $_COOKIE[$__bb_desiredSession]);
		}
		@session_start();
	}
} else {
	@session_name($__bb_desiredSession);
	if (!empty($_COOKIE[$__bb_desiredSession])) {
		@session_id((string) $_COOKIE[$__bb_desiredSession]);
	}
	@session_start();
}

if ($__bb_reqSlug !== '') {
	$_SESSION['tenant_slug'] = $__bb_reqSlug;
}

require_once __DIR__ . '/../../../conexao.php';

if (!headers_sent()) {
	header('Content-Type: text/plain; charset=UTF-8');
}

$data_atual = date('Y-m-d');

$usuario_sessao = isset($_SESSION['id']) ? (int) preg_replace('/\D+/', '', (string) $_SESSION['id']) : 0;
$usuario_post = isset($_POST['id_usuario']) ? (int) preg_replace('/\D+/', '', (string) $_POST['id_usuario']) : 0;

// Mantém a compatibilidade com o POST antigo, mas dá prioridade à sessão correta.
$usuario_logado = $usuario_sessao > 0 ? $usuario_sessao : $usuario_post;
$id_usuario = $usuario_logado;

// -----------------------------------------------------------------------------
// Correção pontual:
// - Evita "Undefined array key 0" quando serviço/funcionário/forma de pagamento
//   não existe no banco do tenant atual.
// - Mantém a regra de negócio existente de baixa, comissão, receber e retorno.
// - Apenas valida entrada e troca consultas sensíveis por prepared statements.
// -----------------------------------------------------------------------------

if (!function_exists('bb_agenda_post')) {
	function bb_agenda_post($campo, $padrao = '') {
		return isset($_POST[$campo]) ? $_POST[$campo] : $padrao;
	}
}

if (!function_exists('bb_agenda_int')) {
	function bb_agenda_int($valor) {
		return (int) preg_replace('/\D+/', '', (string) $valor);
	}
}

if (!function_exists('bb_agenda_texto')) {
	function bb_agenda_texto($valor, $limite = 1000) {
		$valor = trim(strip_tags((string) $valor));
		if ($limite > 0 && function_exists('mb_substr')) {
			return mb_substr($valor, 0, $limite, 'UTF-8');
		}
		if ($limite > 0) {
			return substr($valor, 0, $limite);
		}
		return $valor;
	}
}

if (!function_exists('bb_agenda_decimal')) {
	function bb_agenda_decimal($valor) {
		$valor = trim((string) $valor);
		if ($valor === '') {
			return 0;
		}

		$valor = str_replace(array('R$', ' ', "\xc2\xa0"), '', $valor);

		if (strpos($valor, ',') !== false && strpos($valor, '.') !== false) {
			$valor = str_replace('.', '', $valor);
			$valor = str_replace(',', '.', $valor);
		} else {
			$valor = str_replace(',', '.', $valor);
		}

		return is_numeric($valor) ? (float) $valor : 0;
	}
}

if (!function_exists('bb_agenda_data')) {
	function bb_agenda_data($valor, $padrao = '') {
		$valor = trim((string) $valor);
		if ($valor === '') {
			return $padrao;
		}

		$data = DateTime::createFromFormat('Y-m-d', $valor);
		if ($data && $data->format('Y-m-d') === $valor) {
			return $valor;
		}

		return $padrao;
	}
}

if (!function_exists('bb_agenda_taxa_forma_pgto')) {
	function bb_agenda_taxa_forma_pgto($pdo, $nome_pgto) {
		$nome_pgto = trim((string) $nome_pgto);
		if ($nome_pgto === '') {
			return 0;
		}

		$query = $pdo->prepare("SELECT taxa FROM formas_pgto WHERE nome = :nome LIMIT 1");
		$query->bindValue(':nome', $nome_pgto);
		$query->execute();
		$forma = $query->fetch(PDO::FETCH_ASSOC);

		return ($forma && isset($forma['taxa'])) ? (float) $forma['taxa'] : 0;
	}
}

try {
	$usuario_logado = bb_agenda_int($usuario_logado);
	$id_usuario = $usuario_logado;

	if ($usuario_logado <= 0) {
		echo 'Sessão expirada. Entre novamente no app e tente baixar o agendamento.';
		exit();
	}

	$cliente = bb_agenda_int(bb_agenda_post('cliente_agd'));
	$data_pgto = bb_agenda_data(bb_agenda_post('data_pgto'), $data_atual);
	$id_agd = bb_agenda_int(bb_agenda_post('id_agd'));
	$valor_serv = bb_agenda_decimal(bb_agenda_post('valor_serv_agd'));
	$descricao = bb_agenda_texto(bb_agenda_post('descricao_serv_agd'), 255);
	$funcionario = bb_agenda_int(bb_agenda_post('funcionario_agd'));
	$servico = bb_agenda_int(bb_agenda_post('servico_agd'));
	$obs = bb_agenda_texto(bb_agenda_post('obs'), 1000);
	$pgto = bb_agenda_texto(bb_agenda_post('pgto'), 100);

	$valor_serv_restante = bb_agenda_decimal(bb_agenda_post('valor_serv_agd_restante'));
	$pgto_restante = bb_agenda_texto(bb_agenda_post('pgto_restante'), 100);
	$data_pgto_restante = bb_agenda_data(bb_agenda_post('data_pgto_restante'), '');

	$valor_serv_original = $valor_serv;

	if ($id_agd <= 0) {
		echo 'Agendamento inválido. Atualize a página e tente novamente.';
		exit();
	}

	if ($cliente <= 0) {
		echo 'Cliente não informado para este agendamento.';
		exit();
	}

	if ($servico <= 0) {
		echo 'Serviço não informado para este agendamento.';
		exit();
	}

	if ($funcionario <= 0) {
		echo 'Funcionário não informado para este agendamento.';
		exit();
	}

	if ($pgto === '') {
		echo 'Informe a forma de pagamento.';
		exit();
	}

	// Defaults seguros para evitar notices quando alguma configuração ainda não existe.
	$tipo_comissao = isset($tipo_comissao) ? $tipo_comissao : 'Porcentagem';
	$taxa_sistema = isset($taxa_sistema) ? $taxa_sistema : '';
	$lanc_comissao = isset($lanc_comissao) ? $lanc_comissao : '';
	$quantidade_cartoes = isset($quantidade_cartoes) ? (int) $quantidade_cartoes : 0;
	$hora_random = isset($hora_random) ? $hora_random : '';
	$msg_agendamento = isset($msg_agendamento) ? $msg_agendamento : '';
	$nome_sistema = isset($nome_sistema) ? $nome_sistema : '';

	$query = $pdo->prepare("SELECT valor FROM receber WHERE referencia = :referencia");
	$query->bindValue(':referencia', $id_agd, PDO::PARAM_INT);
	$query->execute();
	$res = $query->fetchAll(PDO::FETCH_ASSOC);
	$agendamento_conta = count($res);
	$valor_recebido = (!empty($res) && isset($res[0]['valor'])) ? (float) $res[0]['valor'] : 0;

	$novo_valor_servico = $valor_recebido + $valor_serv;
	$valor_total_servico = $valor_serv + $valor_serv_restante + $valor_recebido;

	$query = $pdo->prepare("SELECT id, valor, comissao, nome, dias_retorno FROM servicos WHERE id = :id LIMIT 1");
	$query->bindValue(':id', $servico, PDO::PARAM_INT);
	$query->execute();
	$dados_servico = $query->fetch(PDO::FETCH_ASSOC);

	if (!$dados_servico) {
		echo 'Serviço não encontrado nesta loja. Atualize a agenda e tente novamente.';
		exit();
	}

	$valor = isset($dados_servico['valor']) ? (float) $dados_servico['valor'] : 0;
	$comissao = isset($dados_servico['comissao']) ? (float) $dados_servico['comissao'] : 0;
	$descricao = isset($dados_servico['nome']) ? $dados_servico['nome'] : $descricao;
	$descricao2 = 'Comissão - '.$descricao;
	$nome_servico = $descricao;

	$query = $pdo->prepare("SELECT id, comissao FROM usuarios WHERE id = :id LIMIT 1");
	$query->bindValue(':id', $funcionario, PDO::PARAM_INT);
	$query->execute();
	$dados_funcionario = $query->fetch(PDO::FETCH_ASSOC);

	if (!$dados_funcionario) {
		echo 'Funcionário não encontrado nesta loja. Atualize a agenda e tente novamente.';
		exit();
	}

	$comissao_func = isset($dados_funcionario['comissao']) ? (float) $dados_funcionario['comissao'] : 0;

	if ($comissao_func > 0) {
		$comissao = $comissao_func;
	}

	if ($tipo_comissao == 'Porcentagem') {
		$valor_comissao = ($comissao * $valor_total_servico) / 100;
	} else {
		$valor_comissao = $comissao;
	}

	$valor_taxa = bb_agenda_taxa_forma_pgto($pdo, $pgto);

	if ($valor_taxa > 0 && strtotime($data_pgto) <= strtotime($data_atual)) {
		if ($taxa_sistema == 'Cliente') {
			$valor_serv = $valor_serv + $valor_serv * ($valor_taxa / 100);
		} else {
			$valor_serv = $valor_serv - $valor_serv * ($valor_taxa / 100);
		}
	}

	$valor_taxa = bb_agenda_taxa_forma_pgto($pdo, $pgto_restante);

	if ($valor_taxa > 0 && strtotime($data_pgto_restante) <= strtotime($data_atual)) {
		if ($taxa_sistema == 'Cliente') {
			$valor_serv_restante = $valor_serv_restante + $valor_serv_restante * ($valor_taxa / 100);
		} else {
			$valor_serv_restante = $valor_serv_restante - $valor_serv_restante * ($valor_taxa / 100);
		}
	}

	// verificar caixa aberto
	$query1 = $pdo->prepare("SELECT id FROM caixas WHERE operador = :operador AND data_fechamento IS NULL ORDER BY id DESC LIMIT 1");
	$query1->bindValue(':operador', $id_usuario, PDO::PARAM_INT);
	$query1->execute();
	$res1 = $query1->fetchAll(PDO::FETCH_ASSOC);

	if (count($res1) > 0) {
		$id_caixa = isset($res1[0]['id']) ? (int) $res1[0]['id'] : 0;
	} else {
		$id_caixa = 0;
	}

	if (strtotime($data_pgto) <= strtotime($data_atual)) {
		$pago = 'Sim';
		$data_pgto2 = $data_pgto;
		$usuario_baixa = $usuario_logado;

		// lançar a conta a pagar para a comissão do funcionário
		$queryPagar = $pdo->prepare("INSERT INTO pagar SET descricao = :descricao, tipo = 'Comissão', valor = :valor, data_lanc = :data_lanc, data_venc = :data_venc, usuario_lanc = :usuario_lanc, foto = 'sem-foto.jpg', pago = 'Não', funcionario = :funcionario, servico = :servico, cliente = :cliente");
		$queryPagar->bindValue(':descricao', $descricao2);
		$queryPagar->bindValue(':valor', $valor_comissao);
		$queryPagar->bindValue(':data_lanc', $data_pgto);
		$queryPagar->bindValue(':data_venc', $data_pgto);
		$queryPagar->bindValue(':usuario_lanc', $usuario_logado, PDO::PARAM_INT);
		$queryPagar->bindValue(':funcionario', $funcionario, PDO::PARAM_INT);
		$queryPagar->bindValue(':servico', $servico, PDO::PARAM_INT);
		$queryPagar->bindValue(':cliente', $cliente, PDO::PARAM_INT);
		$queryPagar->execute();

	} else {
		$pago = 'Não';
		$data_pgto2 = '';
		$usuario_baixa = 0;

		if ($lanc_comissao == 'Sempre') {
			// lançar a conta a pagar para a comissão do funcionário
			$queryPagar = $pdo->prepare("INSERT INTO pagar SET descricao = :descricao, tipo = 'Comissão', valor = :valor, data_lanc = :data_lanc, data_venc = :data_venc, usuario_lanc = :usuario_lanc, foto = 'sem-foto.jpg', pago = 'Não', funcionario = :funcionario, servico = :servico, cliente = :cliente, caixa = :caixa, hora = curTime()");
			$queryPagar->bindValue(':descricao', $descricao2);
			$queryPagar->bindValue(':valor', $valor_comissao);
			$queryPagar->bindValue(':data_lanc', $data_pgto);
			$queryPagar->bindValue(':data_venc', $data_pgto);
			$queryPagar->bindValue(':usuario_lanc', $usuario_logado, PDO::PARAM_INT);
			$queryPagar->bindValue(':funcionario', $funcionario, PDO::PARAM_INT);
			$queryPagar->bindValue(':servico', $servico, PDO::PARAM_INT);
			$queryPagar->bindValue(':cliente', $cliente, PDO::PARAM_INT);
			$queryPagar->bindValue(':caixa', $id_caixa, PDO::PARAM_INT);
			$queryPagar->execute();
		}
	}

	if ($valor_serv_restante > 0) {
		if (strtotime($data_pgto_restante) <= strtotime($data_atual)) {
			$pago_restante = 'Sim';
			$data_pgto2_restante = $data_pgto;
			$usuario_baixa_restante = $usuario_logado;
		} else {
			$pago_restante = 'Não';
			$data_pgto2_restante = '';
			$usuario_baixa_restante = 0;
		}

		// lançar o restante
		$queryRestante = $pdo->prepare("INSERT INTO receber SET descricao = :descricao, tipo = 'Serviço', valor = :valor, data_lanc = curDate(), data_venc = :data_venc, data_pgto = :data_pgto, usuario_lanc = :usuario_lanc, usuario_baixa = :usuario_baixa, foto = 'sem-foto.jpg', pessoa = :pessoa, pago = :pago, servico = :servico, funcionario = :funcionario, obs = :obs, pgto = :pgto, caixa = :caixa, hora = curTime(), hora_alerta = :hora_alerta");
		$queryRestante->bindValue(':descricao', $descricao);
		$queryRestante->bindValue(':valor', $valor_serv_restante);
		$queryRestante->bindValue(':data_venc', $data_pgto_restante);
		$queryRestante->bindValue(':data_pgto', $data_pgto2_restante);
		$queryRestante->bindValue(':usuario_lanc', $usuario_logado, PDO::PARAM_INT);
		// Mantido igual à lógica original: usa $usuario_baixa neste lançamento.
		$queryRestante->bindValue(':usuario_baixa', $usuario_baixa, PDO::PARAM_INT);
		$queryRestante->bindValue(':pessoa', $cliente, PDO::PARAM_INT);
		$queryRestante->bindValue(':pago', $pago_restante);
		$queryRestante->bindValue(':servico', $servico, PDO::PARAM_INT);
		$queryRestante->bindValue(':funcionario', $funcionario, PDO::PARAM_INT);
		$queryRestante->bindValue(':obs', $obs);
		$queryRestante->bindValue(':pgto', $pgto_restante);
		$queryRestante->bindValue(':caixa', $id_caixa, PDO::PARAM_INT);
		$queryRestante->bindValue(':hora_alerta', $hora_random);
		$queryRestante->execute();
	}

	$dias_retorno = 0;
	if (isset($dados_servico['dias_retorno']) && $dados_servico['dias_retorno'] !== null && $dados_servico['dias_retorno'] !== '') {
		$dias_retorno = (int) $dados_servico['dias_retorno'];
	}

	// dados do cliente
	$query2 = $pdo->prepare("SELECT id, cartoes, telefone, nome FROM clientes WHERE id = :id LIMIT 1");
	$query2->bindValue(':id', $cliente, PDO::PARAM_INT);
	$query2->execute();
	$dados_cliente = $query2->fetch(PDO::FETCH_ASSOC);

	if (!$dados_cliente) {
		echo 'Cliente não encontrado nesta loja. Atualize a agenda e tente novamente.';
		exit();
	}

	$total_cartoes = isset($dados_cliente['cartoes']) ? (int) $dados_cliente['cartoes'] : 0;
	$telefone = isset($dados_cliente['telefone']) ? (string) $dados_cliente['telefone'] : '';
	$nome_cliente = isset($dados_cliente['nome']) ? (string) $dados_cliente['nome'] : '';

	// ====== BUSCAR LINK DE AVALIAÇÃO (GOOGLE) NO BANCO ======
	$link_avaliacao_google = 'https://g.page/r/CW2oJnbXKDzREAE/review'; // fallback se não tiver salvo

	try {
		$stLink = $pdo->query("SELECT link_avaliacao_google 
		                       FROM avaliacoes_site 
		                       WHERE is_config = 1 
		                       ORDER BY id DESC 
		                       LIMIT 1");

		if ($stLink) {
			$rLink = $stLink->fetch(PDO::FETCH_ASSOC);
			if ($rLink && !empty($rLink['link_avaliacao_google'])) {
				$link_avaliacao_google = trim($rLink['link_avaliacao_google']);
			}
		}
	} catch (Throwable $e) {
		// Se der erro ou não existir coluna ainda, usa fallback.
	}

	if ($total_cartoes >= $quantidade_cartoes) {
		$cartoes = 0;
	} else {
		$cartoes = $total_cartoes + 1;
	}

	$data_retorno = date('Y-m-d', strtotime("+$dias_retorno days", strtotime($data_atual)));

	if ($valor_serv_original != 0) {
		if ($agendamento_conta == 0) {
			$queryReceber = $pdo->prepare("INSERT INTO receber SET descricao = :descricao, tipo = 'Serviço', valor = :valor, data_lanc = curDate(), data_venc = :data_venc, data_pgto = :data_pgto, usuario_lanc = :usuario_lanc, usuario_baixa = :usuario_baixa, foto = 'sem-foto.jpg', pessoa = :pessoa, pago = :pago, servico = :servico, funcionario = :funcionario, obs = :obs, pgto = :pgto, caixa = :caixa, hora = curTime(), hora_alerta = :hora_alerta");
			$queryReceber->bindValue(':descricao', $descricao);
			$queryReceber->bindValue(':valor', $valor_serv);
			$queryReceber->bindValue(':data_venc', $data_pgto);
			$queryReceber->bindValue(':data_pgto', $data_pgto2);
			$queryReceber->bindValue(':usuario_lanc', $usuario_logado, PDO::PARAM_INT);
			$queryReceber->bindValue(':usuario_baixa', $usuario_baixa, PDO::PARAM_INT);
			$queryReceber->bindValue(':pessoa', $cliente, PDO::PARAM_INT);
			$queryReceber->bindValue(':pago', $pago);
			$queryReceber->bindValue(':servico', $servico, PDO::PARAM_INT);
			$queryReceber->bindValue(':funcionario', $funcionario, PDO::PARAM_INT);
			$queryReceber->bindValue(':obs', $obs);
			$queryReceber->bindValue(':pgto', $pgto);
			$queryReceber->bindValue(':caixa', $id_caixa, PDO::PARAM_INT);
			$queryReceber->bindValue(':hora_alerta', $hora_random);
			$queryReceber->execute();
		} else {
			$queryReceber = $pdo->prepare("UPDATE receber SET valor = :valor, data_pgto = curDate(), usuario_baixa = :usuario_baixa, foto = 'sem-foto.jpg', pgto = :pgto WHERE referencia = :referencia");
			$queryReceber->bindValue(':valor', $novo_valor_servico);
			$queryReceber->bindValue(':usuario_baixa', $usuario_baixa, PDO::PARAM_INT);
			$queryReceber->bindValue(':pgto', $pgto);
			$queryReceber->bindValue(':referencia', $id_agd, PDO::PARAM_INT);
			$queryReceber->execute();
		}
	}

	$queryAgendamento = $pdo->prepare("UPDATE agendamentos SET status = 'Concluído' WHERE id = :id");
	$queryAgendamento->bindValue(':id', $id_agd, PDO::PARAM_INT);
	$queryAgendamento->execute();

	$queryCliente = $pdo->prepare("UPDATE clientes SET cartoes = :cartoes, data_retorno = :data_retorno, ultimo_servico = :ultimo_servico, alertado = 'Não' WHERE id = :id");
	$queryCliente->bindValue(':cartoes', $cartoes, PDO::PARAM_INT);
	$queryCliente->bindValue(':data_retorno', $data_retorno);
	$queryCliente->bindValue(':ultimo_servico', $servico, PDO::PARAM_INT);
	$queryCliente->bindValue(':id', $cliente, PDO::PARAM_INT);
	$queryCliente->execute();

	// Resposta da baixa:
	// O banco já foi atualizado neste ponto. A chamada da API de mensagem é mantida,
	// porém não pode prender o AJAX da modal nem sujar o retorno "Salvo com Sucesso".
	$bb_retorno_ajax = 'Salvo com Sucesso';
	$bb_enviar_api = ($msg_agendamento == 'Api');

	$telefone = '55'.preg_replace('/[ ()-]+/' , '' , (string) $telefone);

	if ($bb_enviar_api) {
		// agendar mensagem de retorno
		$mensagem  = 'Olá *'.$nome_cliente.'*! Tudo bem?%0A%0A';
		$mensagem .= 'Aqui é o *'.$nome_sistema.'*. Queremos saber como foi sua experiência conosco — especialmente com o serviço de *'.$nome_servico.'*.%0A%0A';
		$mensagem .= 'A sua opinião é muito importante para nós e nos ajuda a manter a qualidade e melhorar cada vez mais.%0A%0A';
		$mensagem .= 'Se você gostou do atendimento, você poderia nos avaliar com *5 estrelas no Google*? ⭐⭐⭐⭐⭐%0A';
		$mensagem .= '*Clique no link e deixe sua avaliação:*%0A'.$link_avaliacao_google.'%0A%0A';
		$mensagem .= 'Desde já, muito obrigado pelo carinho e confiança.%0A';
		$mensagem .= 'Será um prazer te receber novamente!%0A%0A';
		$mensagem .= '— *'.$nome_sistema.'*';
		$data_mensagem = $data_retorno.' 09:00';
	}

	if ($bb_enviar_api && function_exists('fastcgi_finish_request')) {
		echo $bb_retorno_ajax;
		if (function_exists('session_write_close')) {
			@session_write_close();
		}
		@fastcgi_finish_request();

		try {
			@ignore_user_abort(true);
			@ini_set('default_socket_timeout', '8');
			@set_time_limit(12);
			ob_start();
			require __DIR__ . '/../../../../ajax/api-agendar.php';
			if (ob_get_level() > 0) {
				@ob_end_clean();
			}
		} catch (Throwable $eApi) {
			if (ob_get_level() > 0) {
				@ob_end_clean();
			}
			error_log('[BarberBot][agenda/inserir-servico][api-agendar] '.$eApi->getMessage());
		}
		exit();
	}

	if ($bb_enviar_api) {
		try {
			@ini_set('default_socket_timeout', '8');
			@set_time_limit(12);
			ob_start();
			require __DIR__ . '/../../../../ajax/api-agendar.php';
			if (ob_get_level() > 0) {
				@ob_end_clean();
			}
		} catch (Throwable $eApi) {
			if (ob_get_level() > 0) {
				@ob_end_clean();
			}
			error_log('[BarberBot][agenda/inserir-servico][api-agendar] '.$eApi->getMessage());
		}
	}

	echo $bb_retorno_ajax;

} catch (Throwable $e) {
	error_log('[BarberBot][agenda/inserir-servico] '.$e->getMessage());
	echo 'Erro ao finalizar serviço. Verifique os dados e tente novamente.';
}
?>
