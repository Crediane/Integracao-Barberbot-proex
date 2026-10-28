<?php
require_once __DIR__ . '/../_context_bootstrap.php';
// ============================================================================
// Arquivo: /public_html/sistema/painel/paginas/agendamentos/inserir-servico.php (AJAX)
// PADRÃO "BLINDADO MARKETPLACE" (SEM MUDAR A LÓGICA):
// - Detecta SLUG (POST/GET/URI/REFERER) ANTES do conexao.php
// - Passa $_GET['loja'] para o bootstrap escolher o TENANT correto
// - Quando usar combo, baixa exatamente 1 sessão em assinaturas_consumos
// - Quando vender um serviço combo pago no próprio atendimento, cria a assinatura e baixa automaticamente a 1ª sessão
// ============================================================================
@chdir(__DIR__);

if (!function_exists('bb_clean_slug')) {
	function bb_clean_slug($v) {
		$v = strtolower(trim((string)$v));
		return preg_replace('/[^a-z0-9_-]/', '', $v);
	}
}

$__bb_reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$__bb_refPath = '';
$__bb_slug = '';
if (!empty($_POST['loja'])) {
	$__bb_slug = bb_clean_slug($_POST['loja']);
} elseif (!empty($_GET['loja'])) {
	$__bb_slug = bb_clean_slug($_GET['loja']);
} elseif (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath, $m1)) {
	$__bb_slug = bb_clean_slug($m1[1]);
} elseif ($__bb_refPath && preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_refPath, $m2)) {
	$__bb_slug = bb_clean_slug($m2[1]);
}

if ($__bb_slug !== '') {
	$_GET['loja'] = $__bb_slug;
}

require_once __DIR__ . '/../../../conexao.php';
require_once __DIR__ . '/funcoes-assinatura-combo.php';

if (!function_exists('bb_validar_data_mysql')) {
	function bb_validar_data_mysql($data) {
		$data = trim((string)$data);
		if ($data === '' || $data === '0000-00-00') {
			return false;
		}
		$dt = DateTime::createFromFormat('Y-m-d', $data);
		return ($dt && $dt->format('Y-m-d') === $data);
	}
}

if (!function_exists('bb_tabela_existe_agd')) {
	function bb_tabela_existe_agd(PDO $pdo, $tabela) {
		static $cache = array();
		$tabela = (string)$tabela;

		if (isset($cache[$tabela])) {
			return $cache[$tabela];
		}

		try {
			$st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabela");
			$st->bindValue(':tabela', $tabela, PDO::PARAM_STR);
			$st->execute();
			$cache[$tabela] = ((int)$st->fetchColumn() > 0);
		} catch (Throwable $e) {
			$cache[$tabela] = false;
		}

		return $cache[$tabela];
	}
}

if (!function_exists('bb_coluna_existe_agd')) {
	function bb_coluna_existe_agd(PDO $pdo, $tabela, $coluna) {
		static $cache = array();
		$chave = (string)$tabela . '.' . (string)$coluna;

		if (isset($cache[$chave])) {
			return $cache[$chave];
		}

		try {
			$st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :tabela AND COLUMN_NAME = :coluna");
			$st->bindValue(':tabela', (string)$tabela, PDO::PARAM_STR);
			$st->bindValue(':coluna', (string)$coluna, PDO::PARAM_STR);
			$st->execute();
			$cache[$chave] = ((int)$st->fetchColumn() > 0);
		} catch (Throwable $e) {
			$cache[$chave] = false;
		}

		return $cache[$chave];
	}
}

if (!function_exists('bb_normalizar_texto_agd')) {
	function bb_normalizar_texto_agd($texto) {
		$texto = trim((string)$texto);
		if ($texto === '') {
			return '';
		}

		if (function_exists('iconv')) {
			$conv = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $texto);
			if ($conv !== false) {
				$texto = $conv;
			}
		}

		$texto = strtolower($texto);
		$texto = preg_replace('/[^a-z0-9\s_-]/', '', $texto);
		return preg_replace('/\s+/', ' ', $texto);
	}
}

if (!function_exists('bb_servico_eh_manutencao_protese')) {
	function bb_servico_eh_manutencao_protese($nome_servico) {
		$nome_servico = bb_normalizar_texto_agd($nome_servico);

		if ($nome_servico === '') {
			return false;
		}

		if (strpos($nome_servico, 'manutenc') === false) {
			return false;
		}

		if (strpos($nome_servico, 'protes') === false) {
			return false;
		}

		return true;
	}
}


if (!function_exists('bb_calcular_proxima_manutencao_protese_agd')) {
	function bb_calcular_proxima_manutencao_protese_agd($data_manutencao) {
		$data_manutencao = trim((string)$data_manutencao);

		if (!bb_validar_data_mysql($data_manutencao)) {
			$data_manutencao = date('Y-m-d');
		}

		try {
			$data_base = DateTime::createFromFormat('Y-m-d', $data_manutencao);
			if (!$data_base) {
				return date('Y-m-d', strtotime('+20 days'));
			}

			$data_base->modify('+20 days');
			return $data_base->format('Y-m-d');
		} catch (Throwable $e) {
			return date('Y-m-d', strtotime('+20 days'));
		}
	}
}

if (!function_exists('bb_registrar_manutencao_protese_automatica')) {
	function bb_registrar_manutencao_protese_automatica(PDO $pdo, array $dados) {
		try {
			$cliente = isset($dados['cliente']) ? (int)$dados['cliente'] : 0;
			$id_agd = isset($dados['id_agd']) ? (int)$dados['id_agd'] : 0;
			$nome_servico = isset($dados['nome_servico']) ? trim((string)$dados['nome_servico']) : '';
			$data_manutencao = isset($dados['data_manutencao']) ? trim((string)$dados['data_manutencao']) : '';
			$data_retorno = isset($dados['data_retorno']) ? trim((string)$dados['data_retorno']) : '';
			$obs = isset($dados['obs']) ? trim((string)$dados['obs']) : '';
			$usuario = isset($dados['usuario']) ? (int)$dados['usuario'] : 0;

			if ($cliente <= 0 || $id_agd <= 0 || $nome_servico === '') {
				return 'invalido';
			}

			if (!bb_servico_eh_manutencao_protese($nome_servico)) {
				return 'nao_manutencao';
			}

			if (!bb_tabela_existe_agd($pdo, 'manutencoes_protese') || !bb_tabela_existe_agd($pdo, 'proteses')) {
				return 'sem_tabelas';
			}

			if (!bb_validar_data_mysql($data_manutencao)) {
				$data_manutencao = date('Y-m-d');
			}

			$stProtese = $pdo->prepare("SELECT * FROM proteses WHERE cliente = :cliente ORDER BY id DESC LIMIT 1");
			$stProtese->bindValue(':cliente', $cliente, PDO::PARAM_INT);
			$stProtese->execute();
			$protese = $stProtese->fetch(PDO::FETCH_ASSOC);

			if (!$protese || empty($protese['id'])) {
				return 'sem_protese';
			}

			$id_protese = (int)$protese['id'];

			$stDup = $pdo->prepare("SELECT id FROM manutencoes_protese WHERE cliente = :cliente AND id_protese = :id_protese AND data_manutencao = :data_manutencao LIMIT 1");
			$stDup->bindValue(':cliente', $cliente, PDO::PARAM_INT);
			$stDup->bindValue(':id_protese', $id_protese, PDO::PARAM_INT);
			$stDup->bindValue(':data_manutencao', $data_manutencao, PDO::PARAM_STR);
			$stDup->execute();
			if ($stDup->fetchColumn()) {
				return 'duplicado';
			}

			$stUltima = $pdo->prepare("SELECT * FROM manutencoes_protese WHERE cliente = :cliente AND id_protese = :id_protese ORDER BY id DESC LIMIT 1");
			$stUltima->bindValue(':cliente', $cliente, PDO::PARAM_INT);
			$stUltima->bindValue(':id_protese', $id_protese, PDO::PARAM_INT);
			$stUltima->execute();
			$ultima = $stUltima->fetch(PDO::FETCH_ASSOC);

			$modelo_protese = isset($protese['modelo']) ? trim((string)$protese['modelo']) : '';
			$tipo = '';
			if (!empty($ultima['tipo'])) {
				$tipo = trim((string)$ultima['tipo']);
			} elseif ($modelo_protese !== '') {
				$tipo = $modelo_protese;
			} else {
				$tipo = $nome_servico;
			}

			$produtos_utilizados = !empty($ultima['produtos_utilizados']) ? trim((string)$ultima['produtos_utilizados']) : '';
			$tipo_pele = !empty($ultima['tipo_pele']) ? trim((string)$ultima['tipo_pele']) : '';
			$nivel_sudorese = !empty($ultima['nivel_sudorese']) ? trim((string)$ultima['nivel_sudorese']) : '';
			$clima = !empty($ultima['clima']) ? trim((string)$ultima['clima']) : '';
			$tipo_adesivo = !empty($ultima['tipo_adesivo']) ? trim((string)$ultima['tipo_adesivo']) : '';
			$fornecedor = '';

			if (bb_coluna_existe_agd($pdo, 'manutencoes_protese', 'fornecedor')) {
				if (!empty($ultima['fornecedor'])) {
					$fornecedor = trim((string)$ultima['fornecedor']);
				} elseif (isset($protese['fornecedor']) && trim((string)$protese['fornecedor']) !== '') {
					$fornecedor = trim((string)$protese['fornecedor']);
				}
			}

			$observacoes = 'Gerado automaticamente pelo agendamento #' . $id_agd . ' concluído a partir do serviço "' . $nome_servico . '".';
			if ($obs !== '') {
				$observacoes .= ' Obs do atendimento: ' . $obs;
			}

			// Regra da manutenção automática de prótese:
			// ao concluir o agendamento, a próxima manutenção deve ficar para 20 dias após a data do atendimento.
			$proxima_manutencao_protese = bb_calcular_proxima_manutencao_protese_agd($data_manutencao);

			$campos = array(
				'cliente' => $cliente,
				'id_protese' => $id_protese,
				'data_manutencao' => $data_manutencao,
				'tipo' => $tipo,
				'produtos_utilizados' => $produtos_utilizados,
				'observacoes' => $observacoes,
				'proxima_manutencao' => $proxima_manutencao_protese,
				'tipo_pele' => $tipo_pele,
				'nivel_sudorese' => $nivel_sudorese,
				'clima' => $clima,
				'tipo_adesivo' => $tipo_adesivo
			);

			if (bb_coluna_existe_agd($pdo, 'manutencoes_protese', 'usuario')) {
				$campos['usuario'] = ($usuario > 0 ? $usuario : 0);
			}

			if (bb_coluna_existe_agd($pdo, 'manutencoes_protese', 'fornecedor')) {
				$campos['fornecedor'] = $fornecedor;
			}

			$colunas_insert = array();
			$binds_insert = array();
			foreach ($campos as $coluna => $valor) {
				if (!bb_coluna_existe_agd($pdo, 'manutencoes_protese', $coluna)) {
					continue;
				}
				$colunas_insert[] = $coluna . ' = :' . $coluna;
				$binds_insert[$coluna] = $valor;
			}

			if (empty($colunas_insert)) {
				return 'sem_colunas';
			}

			$sqlInsert = "INSERT INTO manutencoes_protese SET " . implode(', ', $colunas_insert);
			$stInsert = $pdo->prepare($sqlInsert);

			foreach ($binds_insert as $coluna => $valor) {
				if (is_int($valor)) {
					$stInsert->bindValue(':' . $coluna, $valor, PDO::PARAM_INT);
				} elseif ($valor === null) {
					$stInsert->bindValue(':' . $coluna, null, PDO::PARAM_NULL);
				} else {
					$stInsert->bindValue(':' . $coluna, $valor, PDO::PARAM_STR);
				}
			}

			$stInsert->execute();
			return 'inserido';
		} catch (Throwable $e) {
			return 'erro';
		}
	}
}



$tabela = 'receber';
$data_atual = date('Y-m-d');

$usuario_logado = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
$id_usuario = $usuario_logado;

$cliente = isset($_POST['cliente_agd']) ? (int)$_POST['cliente_agd'] : 0;
$data_pgto = isset($_POST['data_pgto']) ? trim((string)$_POST['data_pgto']) : $data_atual;
$id_agd = isset($_POST['id_agd']) ? (int)$_POST['id_agd'] : 0;
$valor_serv = isset($_POST['valor_serv_agd']) ? (string)$_POST['valor_serv_agd'] : '0';
$descricao = isset($_POST['descricao_serv_agd']) ? trim((string)$_POST['descricao_serv_agd']) : '';
$funcionario = isset($_POST['funcionario_agd']) ? (int)$_POST['funcionario_agd'] : 0;
$servico = isset($_POST['servico_agd']) ? (int)$_POST['servico_agd'] : 0;
$obs = isset($_POST['obs']) ? trim((string)$_POST['obs']) : '';
$pgto = isset($_POST['pgto']) ? trim((string)$_POST['pgto']) : '';
$acao_combo = isset($_POST['acao_combo']) ? trim((string)$_POST['acao_combo']) : '';
$acao_combo = in_array($acao_combo, array('normal', 'usar_combo', 'vender_combo'), true) ? $acao_combo : '';
$usar_combo = (isset($_POST['usar_combo']) && in_array((string)$_POST['usar_combo'], array('Sim','on','1'), true)) ? 'Sim' : 'Não';
$assinatura_combo_id = isset($_POST['assinatura_combo_id']) ? (int)$_POST['assinatura_combo_id'] : 0;

$valor_serv_original = $valor_serv;

if (strpos($valor_serv, ',') !== false) {
	$valor_serv = str_replace('.', '', $valor_serv);
	$valor_serv = str_replace(',', '.', $valor_serv);
}
$valor_serv = (float)$valor_serv;

$valor_serv_restante = isset($_POST['valor_serv_agd_restante']) ? $_POST['valor_serv_agd_restante'] : '';
$valor_serv_restante = str_replace(',', '.', preg_replace('/\./', '', (string)$valor_serv_restante));
$valor_serv_restante = ($valor_serv_restante === '') ? 0 : (float)$valor_serv_restante;

$pgto_restante = isset($_POST['pgto_restante']) ? trim((string)$_POST['pgto_restante']) : '';
$data_pgto_restante = isset($_POST['data_pgto_restante']) ? trim((string)$_POST['data_pgto_restante']) : '';

if($cliente <= 0 || $funcionario <= 0 || $servico <= 0 || $id_agd <= 0){
	echo 'Dados do atendimento inválidos!';
	exit();
}

try {
	$stAgdConta = $pdo->prepare("SELECT * FROM receber WHERE referencia = :referencia");
	$stAgdConta->bindValue(':referencia', $id_agd, PDO::PARAM_INT);
	$stAgdConta->execute();
	$res = $stAgdConta->fetchAll(PDO::FETCH_ASSOC);
	$agendamento_conta = @count($res);
	$valor_recebido = isset($res[0]['valor']) ? (float)$res[0]['valor'] : 0;

	$novo_valor_servico = $valor_recebido + $valor_serv;
	$valor_total_servico = $valor_serv + $valor_serv_restante + $valor_recebido;
	$valor_total_servico_base = $valor_total_servico;

	$stServico = $pdo->prepare("SELECT * FROM servicos WHERE id = :id LIMIT 1");
	$stServico->bindValue(':id', $servico, PDO::PARAM_INT);
	$stServico->execute();
	$dadosServico = $stServico->fetch(PDO::FETCH_ASSOC);

	if(!$dadosServico){
		echo 'Serviço não encontrado!';
		exit();
	}

	$stAgd = $pdo->prepare("SELECT * FROM agendamentos WHERE id = :id LIMIT 1");
	$stAgd->bindValue(':id', $id_agd, PDO::PARAM_INT);
	$stAgd->execute();
	$dadosAgendamento = $stAgd->fetch(PDO::FETCH_ASSOC);
	$data_agendamento_servico = isset($dadosAgendamento['data']) ? trim((string)$dadosAgendamento['data']) : $data_pgto;
	if(!bb_validar_data_mysql($data_agendamento_servico)){
		$data_agendamento_servico = $data_pgto;
	}

	$valor = (float)($dadosServico['valor'] ?? 0);
	$comissao = (float)($dadosServico['comissao'] ?? 0);
	$descricao = (string)($dadosServico['nome'] ?? $descricao);
	$descricao2 = 'Comissão - '.$descricao;
	$nome_servico = $descricao;
	$dias_retorno = (int)($dadosServico['dias_retorno'] ?? 0);
	$combo_ativo_servico = (string)($dadosServico['combo_ativo'] ?? 'Não');

	$stFunc = $pdo->prepare("SELECT * FROM usuarios WHERE id = :id LIMIT 1");
	$stFunc->bindValue(':id', $funcionario, PDO::PARAM_INT);
	$stFunc->execute();
	$dadosFunc = $stFunc->fetch(PDO::FETCH_ASSOC);

	if($dadosFunc && isset($dadosFunc['comissao']) && (float)$dadosFunc['comissao'] > 0){
		$comissao = (float)$dadosFunc['comissao'];
	}

	if($tipo_comissao == 'Porcentagem'){
		$valor_comissao = ($comissao * $valor_total_servico) / 100;
	}else{
		$valor_comissao = $comissao;
	}

	$combo_atendimento = false;
	$combo_venda_atendimento = false;
	$assinatura_combo = null;
	$saldo_combo = null;

	if($combo_ativo_servico === 'Sim' && $acao_combo === ''){
		$acao_combo = ($usar_combo === 'Sim') ? 'usar_combo' : 'vender_combo';
	}
	if($combo_ativo_servico !== 'Sim'){
		$acao_combo = 'normal';
	}

	if($acao_combo === 'usar_combo'){
		if($combo_ativo_servico !== 'Sim'){
			echo 'Este serviço não está configurado como combo!';
			exit();
		}

		if($assinatura_combo_id > 0){
			$assinatura_combo = buscarAssinaturaComboPorId($pdo, $assinatura_combo_id, $cliente, $servico);
		}else{
			$assinatura_combo = buscarAssinaturaComboAtiva($pdo, $cliente, $servico);
		}
		if(!$assinatura_combo){
			echo 'Cliente sem assinatura combo ativa para este serviço!';
			exit();
		}

		$saldo_combo = saldoAssinaturaCombo($pdo, (int)$assinatura_combo['id'], (int)$assinatura_combo['combo_qtd_sessoes']);
		if((int)$saldo_combo['restantes'] <= 0){
			echo 'Saldo do combo esgotado para este cliente!';
			exit();
		}

		$combo_atendimento = true;
		$valor_serv = 0;
		$valor_serv_original = 0;
		$valor_serv_restante = 0;
		$valor_total_servico = 0;
		$valor_comissao = 0;
	}

	if($acao_combo === 'vender_combo'){
		$combo_venda_atendimento = true;
	}

	if(!$combo_atendimento){
		if((float)$valor_serv > 0 && trim((string)$pgto) === ''){
			echo 'Selecione a forma de pagamento principal!';
			exit();
		}

		if((float)$valor_serv_restante > 0 && trim((string)$pgto_restante) === ''){
			echo 'Selecione a forma de pagamento restante!';
			exit();
		}

		$stPgto = $pdo->prepare("SELECT * FROM formas_pgto WHERE nome = :nome LIMIT 1");
		$stPgto->bindValue(':nome', $pgto, PDO::PARAM_STR);
		$stPgto->execute();
		$dadosPgto = $stPgto->fetch(PDO::FETCH_ASSOC);
		$valor_taxa = isset($dadosPgto['taxa']) ? (float)$dadosPgto['taxa'] : 0;

		if($valor_taxa > 0 && strtotime($data_pgto) <= strtotime($data_atual)){
			if($taxa_sistema == 'Cliente'){
				$valor_serv = $valor_serv + $valor_serv * ($valor_taxa / 100);
			}else{
				$valor_serv = $valor_serv - $valor_serv * ($valor_taxa / 100);
			}
		}

		$stPgtoRest = $pdo->prepare("SELECT * FROM formas_pgto WHERE nome = :nome LIMIT 1");
		$stPgtoRest->bindValue(':nome', $pgto_restante, PDO::PARAM_STR);
		$stPgtoRest->execute();
		$dadosPgtoRest = $stPgtoRest->fetch(PDO::FETCH_ASSOC);
		$valor_taxa_rest = isset($dadosPgtoRest['taxa']) ? (float)$dadosPgtoRest['taxa'] : 0;

		if($valor_taxa_rest > 0 && $data_pgto_restante !== '' && strtotime($data_pgto_restante) <= strtotime($data_atual)){
			if($taxa_sistema == 'Cliente'){
				$valor_serv_restante = $valor_serv_restante + $valor_serv_restante * ($valor_taxa_rest / 100);
			}else{
				$valor_serv_restante = $valor_serv_restante - $valor_serv_restante * ($valor_taxa_rest / 100);
			}
		}
	}

	$stCaixa = $pdo->prepare("SELECT * FROM caixas WHERE operador = :operador AND data_fechamento IS NULL ORDER BY id DESC LIMIT 1");
	$stCaixa->bindValue(':operador', $id_usuario, PDO::PARAM_INT);
	$stCaixa->execute();
	$res1 = $stCaixa->fetchAll(PDO::FETCH_ASSOC);
	$id_caixa = (@count($res1) > 0) ? (int)$res1[0]['id'] : 0;

	$ultimo_id = 0;

	$stCliente = $pdo->prepare("SELECT * FROM clientes WHERE id = :id LIMIT 1");
	$stCliente->bindValue(':id', $cliente, PDO::PARAM_INT);
	$stCliente->execute();
	$dadosCliente = $stCliente->fetch(PDO::FETCH_ASSOC);

	if(!$dadosCliente){
		echo 'Cliente não encontrado!';
		exit();
	}

	$total_cartoes = (int)($dadosCliente['cartoes'] ?? 0);
	$telefone = (string)($dadosCliente['telefone'] ?? '');
	$nome_cliente = (string)($dadosCliente['nome'] ?? '');

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
	}

	if($total_cartoes >= $quantidade_cartoes){
		$cartoes = 0;
	}else{
		$cartoes = $total_cartoes + 1;
	}

	$data_retorno = date('Y-m-d', strtotime("+$dias_retorno days",strtotime($data_atual)));

	$pdo->beginTransaction();

	if(!$combo_atendimento){
		if(strtotime($data_pgto) <= strtotime($data_atual)){
			$pago = 'Sim';
			$data_pgto2 = $data_pgto;
			$usuario_baixa = $usuario_logado;

			$pdo->query("INSERT INTO pagar SET descricao = '$descricao2', tipo = 'Comissão', valor = '$valor_comissao', data_lanc = '$data_pgto', data_venc = '$data_pgto', usuario_lanc = '$usuario_logado', foto = 'sem-foto.jpg', pago = 'Não', funcionario = '$funcionario', servico = '$servico', cliente = '$cliente', caixa = '$id_caixa', hora = curTime(), hora_alerta = '$hora_random'");
			$ultima_conta = $pdo->lastInsertId();
		}else{
			$pago = 'Não';
			$data_pgto2 = '';
			$usuario_baixa = 0;

			if($lanc_comissao == 'Sempre'){
				$pdo->query("INSERT INTO pagar SET descricao = '$descricao2', tipo = 'Comissão', valor = '$valor_comissao', data_lanc = '$data_pgto', data_venc = '$data_pgto', usuario_lanc = '$usuario_logado', foto = 'sem-foto.jpg', pago = 'Não', funcionario = '$funcionario', servico = '$servico', cliente = '$cliente', caixa = '$id_caixa', hora = curTime(), hora_alerta = '$hora_random'");
				$ultima_conta = $pdo->lastInsertId();
			}
		}

		if($valor_serv_restante > 0){
			if(strtotime($data_pgto_restante) <= strtotime($data_atual)){
				$pago_restante = 'Sim';
				$data_pgto2_restante = $data_pgto;
				$usuario_baixa_restante = $usuario_logado;
			}else{
				$pago_restante = 'Não';
				$data_pgto2_restante = '';
				$usuario_baixa_restante = 0;
			}

			$pdo->query("INSERT INTO $tabela SET descricao = '$descricao', tipo = 'Serviço', valor = '$valor_serv_restante', data_lanc = curDate(), data_venc = '$data_pgto_restante', data_pgto = '$data_pgto2_restante', usuario_lanc = '$usuario_logado', usuario_baixa = '$usuario_baixa', foto = 'sem-foto.jpg', pessoa = '$cliente', pago = '$pago_restante', servico = '$servico', funcionario = '$funcionario', obs = '$obs', pgto = '$pgto_restante', caixa = '$id_caixa', hora = curTime(), hora_alerta = '$hora_random'");
		}

		if((float)$valor_serv_original != 0){
			if($agendamento_conta == 0){
				$pdo->query("INSERT INTO $tabela SET descricao = '$descricao', tipo = 'Serviço', valor = '$valor_serv', data_lanc = curDate(), data_venc = '$data_pgto', data_pgto = '$data_pgto2', usuario_lanc = '$usuario_logado', usuario_baixa = '$usuario_baixa', foto = 'sem-foto.jpg', pessoa = '$cliente', pago = '$pago', servico = '$servico', funcionario = '$funcionario', obs = '$obs', pgto = '$pgto', caixa = '$id_caixa', hora = curTime(), hora_alerta = '$hora_random'");
				$ultimo_id = (int)$pdo->lastInsertId();
			}else{
				$pdo->query("UPDATE $tabela SET valor = '$novo_valor_servico', data_pgto = curDate(), usuario_baixa = '$usuario_baixa', foto = 'sem-foto.jpg', pgto = '$pgto', caixa = '$id_caixa', hora = curTime() WHERE referencia = '$id_agd'");
				$ultimo_id = $id_agd;
			}
		}
	}

	$pago_restante = isset($pago_restante) ? $pago_restante : 'Não';

	if(!$combo_atendimento && $combo_ativo_servico === 'Sim' && $combo_venda_atendimento){
		$pago_combo_venda = 'Sim';
		$baixar_primeira_sessao_combo = false;

		if(!isset($pago) || $pago !== 'Sim'){
			$pago_combo_venda = 'Não';
		}

		if((float)$valor_serv_restante > 0 && $pago_restante !== 'Sim'){
			$pago_combo_venda = 'Não';
		}

		if($pago_combo_venda === 'Sim'){
			$baixar_primeira_sessao_combo = true;
		}

		$okVendaCombo = registrarVendaAssinaturaCombo(
			$pdo,
			$cliente,
			$servico,
			(float)$valor_total_servico,
			$data_atual,
			$pago_combo_venda
		);

		if(!$okVendaCombo){
			$pdo->rollBack();
			echo 'Não foi possível registrar a venda do combo neste atendimento!';
			exit();
		}

		if($baixar_primeira_sessao_combo){
			$assinatura_combo_venda = buscarAssinaturaComboAtiva($pdo, $cliente, $servico);
			if(!$assinatura_combo_venda){
				$pdo->rollBack();
				echo 'Combo vendido, mas não foi possível localizar a assinatura ativa para baixar a 1ª sessão!';
				exit();
			}

			$obs_baixa_venda_combo = trim('1ª sessão baixada automaticamente na venda do combo. ' . $obs);
			$okBaixaVendaCombo = baixarAssinaturaCombo(
				$pdo,
				(int)$assinatura_combo_venda['id'],
				$cliente,
				$servico,
				$id_agd,
				$usuario_logado,
				$obs_baixa_venda_combo
			);

			if(!$okBaixaVendaCombo){
				$pdo->rollBack();
				echo 'Não foi possível baixar a 1ª sessão do combo vendido neste atendimento!';
				exit();
			}
		}
	}

	if($combo_atendimento){
		$okBaixa = baixarAssinaturaCombo($pdo, (int)$assinatura_combo['id'], $cliente, $servico, $id_agd, $usuario_logado, $obs);
		if(!$okBaixa){
			$pdo->rollBack();
			echo 'Não foi possível baixar 1 sessão do combo!';
			exit();
		}
	}

	$stUpdAgd = $pdo->prepare("UPDATE agendamentos SET status = 'Concluído' WHERE id = :id");
	$stUpdAgd->bindValue(':id', $id_agd, PDO::PARAM_INT);
	$stUpdAgd->execute();

	$stUpdCli = $pdo->prepare("UPDATE clientes SET cartoes = :cartoes, data_retorno = :data_retorno, ultimo_servico = :servico, alertado = 'Não' WHERE id = :cliente");
	$stUpdCli->bindValue(':cartoes', $cartoes, PDO::PARAM_INT);
	$stUpdCli->bindValue(':data_retorno', $data_retorno, PDO::PARAM_STR);
	$stUpdCli->bindValue(':servico', $servico, PDO::PARAM_INT);
	$stUpdCli->bindValue(':cliente', $cliente, PDO::PARAM_INT);
	$stUpdCli->execute();

	$resultado_manutencao_automatica = bb_registrar_manutencao_protese_automatica($pdo, array(
		'cliente' => $cliente,
		'id_agd' => $id_agd,
		'nome_servico' => $nome_servico,
		'data_manutencao' => $data_agendamento_servico,
		'data_retorno' => $data_retorno,
		'obs' => $obs,
		'usuario' => $usuario_logado
	));

	$pdo->commit();

	$mensagem_sucesso_extra = '';
	if (isset($resultado_manutencao_automatica)) {
		if ($resultado_manutencao_automatica === 'inserido') {
			$mensagem_sucesso_extra = 'Atendimento concluído e manutenção registrada.';
		} elseif ($resultado_manutencao_automatica === 'duplicado') {
			$mensagem_sucesso_extra = 'Atendimento concluído. Manutenção já estava registrada para esta data.';
		} elseif ($resultado_manutencao_automatica === 'sem_protese') {
			$mensagem_sucesso_extra = 'Atendimento concluído. Manutenção automática não foi criada porque o cliente não possui prótese cadastrada.';
		} elseif ($resultado_manutencao_automatica === 'erro') {
			$mensagem_sucesso_extra = 'Atendimento concluído. Houve falha ao registrar a manutenção automática.';
		} elseif ($resultado_manutencao_automatica === 'sem_tabelas') {
			$mensagem_sucesso_extra = 'Atendimento concluído. As tabelas necessárias da manutenção automática não foram encontradas.';
		} elseif ($resultado_manutencao_automatica === 'sem_colunas') {
			$mensagem_sucesso_extra = 'Atendimento concluído. As colunas necessárias da manutenção automática não foram encontradas.';
		}
	}

	echo 'Salvo com Sucesso*'.$ultimo_id.'*'.$mensagem_sucesso_extra;

	$telefone = '55'.preg_replace('/[ ()-]+/' , '' , $telefone);
	if($msg_agendamento == 'Api'){

		$mensagem  = 'Olá *'.$nome_cliente.'*! Tudo bem?%0A%0A';
		$mensagem .= 'Aqui é o *'.$nome_sistema.'*. Queremos saber como foi sua experiência conosco — especialmente com o serviço de *'.$nome_servico.'*.%0A%0A';
		$mensagem .= 'A sua opinião é muito importante para nós e nos ajuda a manter a qualidade e melhorar cada vez mais.%0A%0A';
		$mensagem .= 'Se você gostou do atendimento, você poderia nos avaliar com *5 estrelas no Google*? ⭐⭐⭐⭐⭐%0A';
		$mensagem .= '*Clique no link e deixe sua avaliação:*%0A'.$link_avaliacao_google.'%0A%0A';
		$mensagem .= 'Desde já, muito obrigado pelo carinho e confiança.%0A';
		$mensagem .= 'Será um prazer te receber novamente!%0A%0A';
		$mensagem .= '— *'.$nome_sistema.'*';
		$data_mensagem = $data_retorno.' 09:00';
		require __DIR__ . '/../../../../ajax/api-agendar.php';
	}
} catch (Throwable $e) {
	if (isset($pdo) && $pdo instanceof PDO && $pdo->inTransaction()) {
		$pdo->rollBack();
	}
	echo 'Erro ao concluir o atendimento!';
}
?>
