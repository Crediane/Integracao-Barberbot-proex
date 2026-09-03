<?php
require_once __DIR__ . '/../_context_bootstrap.php';
// ============================================================================
// Arquivo: /sistema/painel/paginas/servicos_agenda/salvar.php
// Ajuste pontual:
// - mantém a lógica financeira original do serviço_agenda em "receber"
// - quando o serviço vendido for combo, espelha a venda em "assinaturas"
//   para aparecer no painel do cliente /painel_cliente/combos
// - usa o conexao.php central e respeita /loja/{slug}/
// ============================================================================

$__bb_reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$__bb_refPath = '';
$__bb_reqSlug = '';
if (!empty($_POST['loja'])) {
	$__bb_reqSlug = preg_replace('/[^a-z0-9_-]/i', '', (string)$_POST['loja']);
} elseif (!empty($_GET['loja'])) {
	$__bb_reqSlug = preg_replace('/[^a-z0-9_-]/i', '', (string)$_GET['loja']);
} elseif (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath, $m)) {
	$__bb_reqSlug = strtolower($m[1]);
} elseif ($__bb_refPath && preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_refPath, $m2)) {
	$__bb_reqSlug = strtolower($m2[1]);
}

$__bb_tenantSessionName = 'BB_TENANT';
if ($__bb_reqSlug !== '') {
	$__bb_tenantSessionName = 'BB_TENANT_' . strtoupper(substr(sha1($__bb_reqSlug), 0, 12));
}

$__bb_lojaByPath = (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath)
				|| (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_refPath);

$__bb_hasLoja = $__bb_lojaByPath || !empty($_GET['loja']) || !empty($_POST['loja']);
$__bb_isSistema = (strpos($__bb_reqPath, '/sistema') === 0) || (strpos($__bb_refPath, '/sistema') === 0);
$__bb_forceMaster = ($__bb_isSistema && !$__bb_lojaByPath && empty($_GET['loja']) && empty($_POST['loja']));
$__bb_desiredSession = $__bb_forceMaster ? 'BB_MASTER' : ($__bb_hasLoja ? $__bb_tenantSessionName : 'BB_SITE');

if (session_status() === PHP_SESSION_ACTIVE) {
	if (session_name() !== $__bb_desiredSession) {
		session_write_close();
		@session_name($__bb_desiredSession);
		if (!empty($_COOKIE[$__bb_desiredSession])) {
			@session_id((string)$_COOKIE[$__bb_desiredSession]);
		}
		@session_start();
	}
} else {
	@session_name($__bb_desiredSession);
	if (!empty($_COOKIE[$__bb_desiredSession])) {
		@session_id((string)$_COOKIE[$__bb_desiredSession]);
	}
	@session_start();
}

require_once __DIR__ . '/../../../conexao.php';

$tabela = 'receber';
$data_atual = date('Y-m-d');
$hora_atual = date('H:i:s');
$usuario_logado = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;

// -----------------------------------------------------------------------------
// Helpers
// -----------------------------------------------------------------------------
if (!function_exists('bb_srv_ag_moeda_banco')) {
	function bb_srv_ag_moeda_banco($valor)
	{
		$valor = trim((string)$valor);
		if ($valor === '') {
			return 0.0;
		}
		$valor = str_replace('.', '', $valor);
		$valor = str_replace(',', '.', $valor);
		return (float)$valor;
	}
}

if (!function_exists('bb_srv_ag_coluna_existe')) {
	function bb_srv_ag_coluna_existe(PDO $pdo, $tabela, $coluna)
	{
		try {
			$stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
				WHERE TABLE_SCHEMA = DATABASE()
				AND TABLE_NAME = :tabela
				AND COLUMN_NAME = :coluna");
			$stmt->execute([
				':tabela' => $tabela,
				':coluna' => $coluna
			]);
			return ((int)$stmt->fetchColumn() > 0);
		} catch (Throwable $e) {
			return false;
		}
	}
}

if (!function_exists('bb_srv_ag_tabela_existe')) {
	function bb_srv_ag_tabela_existe(PDO $pdo, $tabela)
	{
		try {
			$stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
				WHERE TABLE_SCHEMA = DATABASE()
				AND TABLE_NAME = :tabela");
			$stmt->execute([':tabela' => $tabela]);
			return ((int)$stmt->fetchColumn() > 0);
		} catch (Throwable $e) {
			return false;
		}
	}
}

if (!function_exists('bb_srv_ag_data_combo')) {
	function bb_srv_ag_data_combo($dataBase, $diasValidade)
	{
		$dataBase = trim((string)$dataBase);
		$diasValidade = (int)$diasValidade;

		if ($dataBase === '' || $dataBase === '0000-00-00') {
			$dataBase = date('Y-m-d');
		}

		if ($diasValidade <= 0) {
			return $dataBase;
		}

		return date('Y-m-d', strtotime('+' . $diasValidade . ' days', strtotime($dataBase)));
	}
}

if (!function_exists('bb_srv_ag_resolver_item_combo')) {
	function bb_srv_ag_resolver_item_combo(PDO $pdo, $idServico)
	{
		if (!bb_srv_ag_tabela_existe($pdo, 'itens_assinaturas')) {
			return null;
		}

		$stmt = $pdo->prepare("SELECT * FROM itens_assinaturas WHERE servico = :servico ORDER BY id DESC LIMIT 1");
		$stmt->bindValue(':servico', (int)$idServico, PDO::PARAM_INT);
		$stmt->execute();
		$item = $stmt->fetch(PDO::FETCH_ASSOC);

		return $item ?: null;
	}
}

if (!function_exists('bb_srv_ag_espelhar_combo_assinatura')) {
	function bb_srv_ag_espelhar_combo_assinatura(PDO $pdo, array $dados)
	{
		if (!bb_srv_ag_tabela_existe($pdo, 'assinaturas')) {
			return true;
		}

		$idCliente = (int)($dados['cliente'] ?? 0);
		$idServico = (int)($dados['servico'] ?? 0);
		$valorTotal = (float)($dados['valor_total'] ?? 0);
		$dataVenda = trim((string)($dados['data_venda'] ?? date('Y-m-d')));
		$pagoCombo = trim((string)($dados['pago'] ?? 'Não'));

		if ($idCliente <= 0 || $idServico <= 0) {
			return true;
		}

		$stmtServico = $pdo->prepare("SELECT * FROM servicos WHERE id = :id LIMIT 1");
		$stmtServico->bindValue(':id', $idServico, PDO::PARAM_INT);
		$stmtServico->execute();
		$servico = $stmtServico->fetch(PDO::FETCH_ASSOC);

		if (!$servico) {
			return true;
		}

		$comboAtivo = isset($servico['combo_ativo']) ? (string)$servico['combo_ativo'] : 'Não';
		if ($comboAtivo !== 'Sim') {
			return true;
		}

		$itemCombo = bb_srv_ag_resolver_item_combo($pdo, $idServico);
		if (!$itemCombo) {
			return true;
		}

		$idItem = (int)($itemCombo['id'] ?? 0);
		if ($idItem <= 0) {
			return true;
		}

		$idGrupo = isset($itemCombo['grupo']) ? (int)$itemCombo['grupo'] : 0;
		$diasValidade = isset($servico['combo_validade_dias']) ? (int)$servico['combo_validade_dias'] : 0;
		$dataVencimentoCombo = bb_srv_ag_data_combo($dataVenda, $diasValidade);

		$temAtivo = bb_srv_ag_coluna_existe($pdo, 'assinaturas', 'ativo');
		$temCancelado = bb_srv_ag_coluna_existe($pdo, 'assinaturas', 'cancelado');
		$temDataVencimento = bb_srv_ag_coluna_existe($pdo, 'assinaturas', 'data_vencimento');
		$temFrequencia = bb_srv_ag_coluna_existe($pdo, 'assinaturas', 'frequencia');

		$sqlBusca = "SELECT id FROM assinaturas
			WHERE cliente = :cliente
			AND item = :item
			AND data = :data";

		if ($temCancelado) {
			$sqlBusca .= " AND (cancelado IS NULL OR cancelado != 'Sim')";
		}

		$sqlBusca .= " ORDER BY id DESC LIMIT 1";

		$stmtBusca = $pdo->prepare($sqlBusca);
		$stmtBusca->bindValue(':cliente', $idCliente, PDO::PARAM_INT);
		$stmtBusca->bindValue(':item', $idItem, PDO::PARAM_INT);
		$stmtBusca->bindValue(':data', $dataVenda, PDO::PARAM_STR);
		$stmtBusca->execute();
		$idAssinaturaExistente = (int)($stmtBusca->fetchColumn() ?: 0);

		if ($idAssinaturaExistente > 0) {
			$sqlUpd = "UPDATE assinaturas SET
				cliente = :cliente,
				grupo = :grupo,
				item = :item,
				valor = :valor,
				pago = :pago,
				vencimento = :vencimento";

			if ($temDataVencimento) {
				$sqlUpd .= ", data_vencimento = :data_vencimento";
			}
			if ($temFrequencia) {
				$sqlUpd .= ", frequencia = :frequencia";
			}
			if ($temAtivo) {
				$sqlUpd .= ", ativo = 'Sim'";
			}

			$sqlUpd .= " WHERE id = :id";

			$stmtUpd = $pdo->prepare($sqlUpd);
			$stmtUpd->bindValue(':id', $idAssinaturaExistente, PDO::PARAM_INT);
			$stmtUpd->bindValue(':cliente', $idCliente, PDO::PARAM_INT);
			$stmtUpd->bindValue(':grupo', $idGrupo, PDO::PARAM_INT);
			$stmtUpd->bindValue(':item', $idItem, PDO::PARAM_INT);
			$stmtUpd->bindValue(':valor', $valorTotal);
			$stmtUpd->bindValue(':pago', $pagoCombo, PDO::PARAM_STR);
			$stmtUpd->bindValue(':vencimento', $dataVencimentoCombo, PDO::PARAM_STR);
			if ($temDataVencimento) {
				$stmtUpd->bindValue(':data_vencimento', $dataVencimentoCombo, PDO::PARAM_STR);
			}
			if ($temFrequencia) {
				$stmtUpd->bindValue(':frequencia', $diasValidade, PDO::PARAM_INT);
			}
			$stmtUpd->execute();

			return true;
		}

		$campos = "cliente, data, pago, grupo, item, valor, vencimento";
		$valores = ":cliente, :data, :pago, :grupo, :item, :valor, :vencimento";

		if ($temFrequencia) {
			$campos .= ", frequencia";
			$valores .= ", :frequencia";
		}
		if ($temDataVencimento) {
			$campos .= ", data_vencimento";
			$valores .= ", :data_vencimento";
		}
		if ($temAtivo) {
			$campos .= ", ativo";
			$valores .= ", 'Sim'";
		}

		$sqlIns = "INSERT INTO assinaturas ({$campos}) VALUES ({$valores})";
		$stmtIns = $pdo->prepare($sqlIns);
		$stmtIns->bindValue(':cliente', $idCliente, PDO::PARAM_INT);
		$stmtIns->bindValue(':data', $dataVenda, PDO::PARAM_STR);
		$stmtIns->bindValue(':pago', $pagoCombo, PDO::PARAM_STR);
		$stmtIns->bindValue(':grupo', $idGrupo, PDO::PARAM_INT);
		$stmtIns->bindValue(':item', $idItem, PDO::PARAM_INT);
		$stmtIns->bindValue(':valor', $valorTotal);
		$stmtIns->bindValue(':vencimento', $dataVencimentoCombo, PDO::PARAM_STR);
		if ($temFrequencia) {
			$stmtIns->bindValue(':frequencia', $diasValidade, PDO::PARAM_INT);
		}
		if ($temDataVencimento) {
			$stmtIns->bindValue(':data_vencimento', $dataVencimentoCombo, PDO::PARAM_STR);
		}
		$stmtIns->execute();

		return true;
	}
}

// -----------------------------------------------------------------------------
// Entrada
// -----------------------------------------------------------------------------
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$cliente = isset($_POST['cliente']) ? (int)$_POST['cliente'] : 0;
$data_pgto = isset($_POST['data_pgto']) ? trim((string)$_POST['data_pgto']) : $data_atual;
$valor_serv = bb_srv_ag_moeda_banco($_POST['valor_serv'] ?? '0');
$funcionario = isset($_POST['funcionario']) ? (int)$_POST['funcionario'] : 0;
$servico = isset($_POST['servico']) ? (int)$_POST['servico'] : 0;
$obs = trim((string)($_POST['obs'] ?? ''));
$pgto = trim((string)($_POST['pgto'] ?? ''));
$forma_pgto = $pgto;

$valor_serv_restante = bb_srv_ag_moeda_banco($_POST['valor_serv_agd_restante'] ?? '0');
$pgto_restante = trim((string)($_POST['pgto_restante'] ?? ''));
$data_pgto_restante = trim((string)($_POST['data_pgto_restante'] ?? ''));

if ($cliente <= 0 || $funcionario <= 0 || $servico <= 0) {
	echo 'Dados do serviço inválidos!';
	exit();
}

if ($valor_serv_restante < 0) {
	$valor_serv_restante = 0;
}

$valor_total_servico = (float)$valor_serv + (float)$valor_serv_restante;

// -----------------------------------------------------------------------------
// Busca dados do serviço / cliente / funcionário (mantido)
// -----------------------------------------------------------------------------
$query = $pdo->prepare("SELECT * FROM servicos WHERE id = :id LIMIT 1");
$query->bindValue(':id', $servico, PDO::PARAM_INT);
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);
if (@count($res) <= 0) {
	echo 'Serviço não encontrado!';
	exit();
}

$valor = (float)$res[0]['valor'];
$comissao = (float)$res[0]['comissao'];
$descricao = $res[0]['nome'];
$descricao2 = 'Comissão - ' . $res[0]['nome'];
$dias_retorno = (int)$res[0]['dias_retorno'];
$data_retorno = date('Y-m-d', strtotime('+' . $dias_retorno . ' days', strtotime($data_atual)));
$nome_servico = $res[0]['nome'];
$combo_ativo_servico = isset($res[0]['combo_ativo']) ? (string)$res[0]['combo_ativo'] : 'Não';

$query2 = $pdo->prepare("SELECT * FROM clientes WHERE id = :id ORDER BY id DESC LIMIT 1");
$query2->bindValue(':id', $cliente, PDO::PARAM_INT);
$query2->execute();
$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
if (@count($res2) <= 0) {
	echo 'Cliente não encontrado!';
	exit();
}
$telefone = $res2[0]['telefone'];
$nome_cliente = $res2[0]['nome'];

$link_avaliacao_google = 'https://g.page/r/CW2oJnbXKDzREAE/review';
try {
	$stLink = $pdo->query("SELECT link_avaliacao_google FROM avaliacoes_site WHERE is_config = 1 ORDER BY id DESC LIMIT 1");
	if ($stLink) {
		$rLink = $stLink->fetch(PDO::FETCH_ASSOC);
		if ($rLink && !empty($rLink['link_avaliacao_google'])) {
			$link_avaliacao_google = trim($rLink['link_avaliacao_google']);
		}
	}
} catch (Throwable $e) {
	// mantém fallback
}

$query = $pdo->prepare("SELECT * FROM usuarios WHERE id = :id LIMIT 1");
$query->bindValue(':id', $funcionario, PDO::PARAM_INT);
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);
if (@count($res) > 0 && (float)$res[0]['comissao'] > 0) {
	$comissao_func = (float)$res[0]['comissao'];
	$comissao = $comissao_func;
}

if ($tipo_comissao == 'Porcentagem') {
	$valor_comissao = ($comissao * $valor_total_servico) / 100;
} else {
	$valor_comissao = $comissao;
}

$stCaixa = $pdo->prepare("SELECT * FROM caixas WHERE operador = :operador AND data_fechamento IS NULL ORDER BY id DESC LIMIT 1");
$stCaixa->bindValue(':operador', $usuario_logado, PDO::PARAM_INT);
$stCaixa->execute();
$resCaixa = $stCaixa->fetchAll(PDO::FETCH_ASSOC);
$id_caixa = (@count($resCaixa) > 0) ? (int)$resCaixa[0]['id'] : 0;

$hora_random = date('H:i:s', strtotime('+' . rand(0, 59) . ' seconds'));

if (strtotime($data_pgto) <= strtotime($data_atual)) {
	$pago = 'Sim';
	$data_pgto2 = $data_pgto;
	$usuario_baixa = $usuario_logado;
} else {
	$pago = 'Não';
	$data_pgto2 = '';
	$usuario_baixa = 0;
}

$pdo->beginTransaction();

try {
	// -------------------------------------------------------------------------
	// 1) Insere a primeira parte (mantido)
	// -------------------------------------------------------------------------
	$stmtIns1 = $pdo->prepare("INSERT INTO {$tabela}
		SET descricao = :descricao, tipo = 'Serviço', valor = :valor,
			data_lanc = :data_lanc, data_venc = :data_venc, data_pgto = :data_pgto,
			usuario_lanc = :usuario_lanc, usuario_baixa = :usuario_baixa,
			foto = 'sem-foto.jpg', pessoa = :pessoa, pago = :pago,
			servico = :servico, funcionario = :funcionario, obs = :obs,
			pgto = :pgto, caixa = :caixa, hora = :hora_lanc, hora_alerta = :hora_alerta");
	$stmtIns1->bindValue(':descricao', $nome_servico, PDO::PARAM_STR);
	$stmtIns1->bindValue(':valor', $valor_serv);
	$stmtIns1->bindValue(':data_lanc', $data_atual, PDO::PARAM_STR);
	$stmtIns1->bindValue(':data_venc', $data_pgto, PDO::PARAM_STR);
	$stmtIns1->bindValue(':data_pgto', $data_pgto2, PDO::PARAM_STR);
	$stmtIns1->bindValue(':usuario_lanc', $usuario_logado, PDO::PARAM_INT);
	$stmtIns1->bindValue(':usuario_baixa', $usuario_baixa, PDO::PARAM_INT);
	$stmtIns1->bindValue(':pessoa', $cliente, PDO::PARAM_INT);
	$stmtIns1->bindValue(':pago', $pago, PDO::PARAM_STR);
	$stmtIns1->bindValue(':servico', $servico, PDO::PARAM_INT);
	$stmtIns1->bindValue(':funcionario', $funcionario, PDO::PARAM_INT);
	$stmtIns1->bindValue(':obs', $obs, PDO::PARAM_STR);
	$stmtIns1->bindValue(':pgto', $pgto, PDO::PARAM_STR);
	$stmtIns1->bindValue(':caixa', $id_caixa, PDO::PARAM_INT);
	$stmtIns1->bindValue(':hora_lanc', $hora_atual, PDO::PARAM_STR);
	$stmtIns1->bindValue(':hora_alerta', $hora_random, PDO::PARAM_STR);
	$stmtIns1->execute();

	$ultimo_id = (int)$pdo->lastInsertId();

	$stmtUpdGrupo = $pdo->prepare("UPDATE {$tabela} SET grupo = :grupo WHERE id = :id");
	$stmtUpdGrupo->bindValue(':grupo', $ultimo_id, PDO::PARAM_INT);
	$stmtUpdGrupo->bindValue(':id', $ultimo_id, PDO::PARAM_INT);
	$stmtUpdGrupo->execute();

	// -------------------------------------------------------------------------
	// 2) Se houver restante, insere vinculando no mesmo grupo (mantido)
	// -------------------------------------------------------------------------
	if ($valor_serv_restante > 0) {
		if ($data_pgto_restante !== '' && strtotime($data_pgto_restante) <= strtotime($data_atual)) {
			$pago_restante = 'Sim';
			$data_pgto2_restante = $data_pgto_restante;
			$usuario_baixa_restante = $usuario_logado;
		} else {
			$pago_restante = 'Não';
			$data_pgto2_restante = '';
			$usuario_baixa_restante = 0;
		}

		$stmtIns2 = $pdo->prepare("INSERT INTO {$tabela}
			SET descricao = :descricao, tipo = 'Serviço', valor = :valor,
				data_lanc = :data_lanc, data_venc = :data_venc, data_pgto = :data_pgto,
				usuario_lanc = :usuario_lanc, usuario_baixa = :usuario_baixa,
				foto = 'sem-foto.jpg', pessoa = :pessoa, pago = :pago,
				servico = :servico, funcionario = :funcionario, obs = :obs,
				pgto = :pgto, caixa = :caixa, hora = :hora_lanc,
				hora_alerta = :hora_alerta, grupo = :grupo");
		$stmtIns2->bindValue(':descricao', $nome_servico, PDO::PARAM_STR);
		$stmtIns2->bindValue(':valor', $valor_serv_restante);
		$stmtIns2->bindValue(':data_lanc', $data_atual, PDO::PARAM_STR);
		$stmtIns2->bindValue(':data_venc', $data_pgto_restante, PDO::PARAM_STR);
		$stmtIns2->bindValue(':data_pgto', $data_pgto2_restante, PDO::PARAM_STR);
		$stmtIns2->bindValue(':usuario_lanc', $usuario_logado, PDO::PARAM_INT);
		$stmtIns2->bindValue(':usuario_baixa', $usuario_baixa_restante, PDO::PARAM_INT);
		$stmtIns2->bindValue(':pessoa', $cliente, PDO::PARAM_INT);
		$stmtIns2->bindValue(':pago', $pago_restante, PDO::PARAM_STR);
		$stmtIns2->bindValue(':servico', $servico, PDO::PARAM_INT);
		$stmtIns2->bindValue(':funcionario', $funcionario, PDO::PARAM_INT);
		$stmtIns2->bindValue(':obs', $obs, PDO::PARAM_STR);
		$stmtIns2->bindValue(':pgto', $pgto_restante, PDO::PARAM_STR);
		$stmtIns2->bindValue(':caixa', $id_caixa, PDO::PARAM_INT);
		$stmtIns2->bindValue(':hora_lanc', $hora_atual, PDO::PARAM_STR);
		$stmtIns2->bindValue(':hora_alerta', $hora_random, PDO::PARAM_STR);
		$stmtIns2->bindValue(':grupo', $ultimo_id, PDO::PARAM_INT);
		$stmtIns2->execute();
	}

	// -------------------------------------------------------------------------
	// 3) Atualiza fidelidade do cliente (mantido)
	// -------------------------------------------------------------------------
	$total_cartoes = (int)$res2[0]['cartoes'];
	$cartoes = $total_cartoes + 1;

	$stmtUpdCli = $pdo->prepare("UPDATE clientes
		SET cartoes = :cartoes, data_retorno = :data_retorno,
			ultimo_servico = :servico, alertado = 'Não'
		WHERE id = :id");
	$stmtUpdCli->bindValue(':cartoes', $cartoes, PDO::PARAM_INT);
	$stmtUpdCli->bindValue(':data_retorno', $data_retorno, PDO::PARAM_STR);
	$stmtUpdCli->bindValue(':servico', $servico, PDO::PARAM_INT);
	$stmtUpdCli->bindValue(':id', $cliente, PDO::PARAM_INT);
	$stmtUpdCli->execute();

	// -------------------------------------------------------------------------
	// 4) Espelha combo em assinaturas sem alterar a lógica financeira
	// -------------------------------------------------------------------------
	if ($combo_ativo_servico === 'Sim') {
		$pago_combo = ($pago === 'Sim' && $valor_serv_restante <= 0) ? 'Sim' : 'Não';

		bb_srv_ag_espelhar_combo_assinatura($pdo, [
			'cliente' => $cliente,
			'servico' => $servico,
			'valor_total' => $valor_total_servico,
			'data_venda' => $data_atual,
			'pago' => $pago_combo
		]);
	}

	$pdo->commit();

	// Mantém retorno atual para impressão/comprovante
	echo 'Salvo com Sucesso*' . $ultimo_id;

} catch (Throwable $e) {
	if ($pdo->inTransaction()) {
		$pdo->rollBack();
	}
	echo 'Erro ao salvar serviço!';
}
?>