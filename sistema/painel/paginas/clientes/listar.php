<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';

$tabela = 'clientes';
$data_atual = date('Y-m-d');

if (!function_exists('bb_h')) {
	function bb_h($valor){
		return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
	}
}

if (!function_exists('bb_js')) {
	function bb_js($valor){
		return json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
	}
}

if (!function_exists('bb_attr_js')) {
	function bb_attr_js($valor){
		return htmlspecialchars(
			json_encode($valor, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
			ENT_QUOTES,
			'UTF-8'
		);
	}
}

if (!function_exists('bb_digits')) {
	function bb_digits($valor){
		return preg_replace('/\D+/', '', (string)$valor);
	}
}

if (!function_exists('bb_whats_destino')) {
	function bb_whats_destino($telefone){
		$raw = trim((string)$telefone);
		$digitos = bb_digits($raw);

		if ($digitos === '') {
			return '';
		}

		if (preg_match('/^\s*\+/', $raw) || preg_match('/^\s*00/', $raw) || strlen($digitos) > 11) {
			return $digitos;
		}

		if (strlen($digitos) === 10 || strlen($digitos) === 11) {
			return '55' . $digitos;
		}

		return $digitos;
	}
}

if (!function_exists('bb_coluna_existe_cli')) {
	function bb_coluna_existe_cli(PDO $pdo, $tabela, $coluna)
	{
		static $cache = array();
		$chave = $tabela . '.' . $coluna;
		if (array_key_exists($chave, $cache)) {
			return $cache[$chave];
		}

		try {
			$stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
				WHERE TABLE_SCHEMA = DATABASE()
				AND TABLE_NAME = :tabela
				AND COLUMN_NAME = :coluna");
			$stmt->execute(array(
				':tabela' => $tabela,
				':coluna' => $coluna
			));
			$cache[$chave] = ((int)$stmt->fetchColumn() > 0);
		} catch (Throwable $e) {
			$cache[$chave] = false;
		}

		return $cache[$chave];
	}
}

if (!function_exists('bb_tabela_existe_cli')) {
	function bb_tabela_existe_cli(PDO $pdo, $tabela)
	{
		static $cache = array();
		if (array_key_exists($tabela, $cache)) {
			return $cache[$tabela];
		}

		try {
			$stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
				WHERE TABLE_SCHEMA = DATABASE()
				AND TABLE_NAME = :tabela");
			$stmt->execute(array(':tabela' => $tabela));
			$cache[$tabela] = ((int)$stmt->fetchColumn() > 0);
		} catch (Throwable $e) {
			$cache[$tabela] = false;
		}

		return $cache[$tabela];
	}
}

$busca_raw = isset($_POST['busca']) ? trim((string)$_POST['busca']) : '';
$busca_like = '%' . $busca_raw . '%';
$busca_digits_limpo = bb_digits($busca_raw);
$busca_digits = '%' . $busca_digits_limpo . '%';

if (!isset($_POST['pagina']) || $_POST['pagina'] === '') {
	$_POST['pagina'] = 0;
}

$pagina = max(0, intval($_POST['pagina']));
$limite = $pagina * $itens_pag;

$sql_telefone_normalizado = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(telefone, ''), '(', ''), ')', ''), '-', ''), ' ', ''), '+', ''), '.', ''), '/', '')";

$sql_condicoes = array();
$sql_condicoes[] = "nome LIKE :busca_nome";
$sql_condicoes[] = "telefone LIKE :busca_telefone";
$sql_condicoes[] = "cpf LIKE :busca_cpf";

if ($busca_digits_limpo !== '') {
	$sql_condicoes[] = "{$sql_telefone_normalizado} LIKE :busca_telefone_digits";
}

$sql_where = " WHERE " . implode(" OR ", $sql_condicoes);

$query = $pdo->prepare("SELECT * FROM {$tabela} {$sql_where} ORDER BY id DESC LIMIT {$limite}, {$itens_pag}");
$query->bindValue(':busca_nome', $busca_like);
$query->bindValue(':busca_telefone', $busca_like);
$query->bindValue(':busca_cpf', $busca_like);
if ($busca_digits_limpo !== '') {
	$query->bindValue(':busca_telefone_digits', $busca_digits);
}
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = count($res);

$query_total = $pdo->prepare("SELECT COUNT(*) as total FROM {$tabela} {$sql_where}");
$query_total->bindValue(':busca_nome', $busca_like);
$query_total->bindValue(':busca_telefone', $busca_like);
$query_total->bindValue(':busca_cpf', $busca_like);
if ($busca_digits_limpo !== '') {
	$query_total->bindValue(':busca_telefone_digits', $busca_digits);
}
$query_total->execute();
$total_reg2 = (int)($query_total->fetch(PDO::FETCH_ASSOC)['total'] ?? 0);
$num_paginas = $total_reg2 > 0 ? (int)ceil($total_reg2 / $itens_pag) : 0;
$ultimo_reg = $num_paginas > 0 ? ($num_paginas - 1) : 0;

if ($total_reg <= 0) {
	echo '<small>Não possui nenhum registro Cadastrado!</small>';
	return;
}

$tem_modulo_combo =
	bb_tabela_existe_cli($pdo, 'assinaturas') &&
	bb_tabela_existe_cli($pdo, 'itens_assinaturas') &&
	bb_tabela_existe_cli($pdo, 'servicos') &&
	bb_tabela_existe_cli($pdo, 'assinaturas_consumos') &&
	bb_coluna_existe_cli($pdo, 'itens_assinaturas', 'servico') &&
	bb_coluna_existe_cli($pdo, 'servicos', 'combo_ativo') &&
	bb_coluna_existe_cli($pdo, 'servicos', 'combo_qtd_sessoes');

$tem_ativo_ass = bb_coluna_existe_cli($pdo, 'assinaturas', 'ativo');
$tem_cancelado_ass = bb_coluna_existe_cli($pdo, 'assinaturas', 'cancelado');
$tem_data_vencimento_ass = bb_coluna_existe_cli($pdo, 'assinaturas', 'data_vencimento');
$expr_vencimento = $tem_data_vencimento_ass ? "COALESCE(a.data_vencimento, a.vencimento)" : "a.vencimento";

$stmtServico = $pdo->prepare("SELECT nome FROM servicos WHERE id = :id LIMIT 1");
$stmtUltimoReceber = $pdo->prepare("SELECT obs, valor, data_lanc FROM receber WHERE pessoa = :pessoa ORDER BY id DESC LIMIT 1");

$stmtCombosAtivos = null;
if ($tem_modulo_combo) {
	$sqlCombosAtivos = "SELECT COUNT(DISTINCT a.id)
		FROM assinaturas a
		INNER JOIN itens_assinaturas ia ON a.item = ia.id
		INNER JOIN servicos s ON ia.servico = s.id
		WHERE a.cliente = :cliente
		AND a.pago = 'Sim'
		AND s.combo_ativo = 'Sim'
		AND ({$expr_vencimento} IS NULL OR {$expr_vencimento} = '0000-00-00' OR {$expr_vencimento} >= :data_hoje)";
	if ($tem_ativo_ass) {
		$sqlCombosAtivos .= " AND a.ativo = 'Sim'";
	}
	if ($tem_cancelado_ass) {
		$sqlCombosAtivos .= " AND (a.cancelado IS NULL OR a.cancelado != 'Sim')";
	}
	$stmtCombosAtivos = $pdo->prepare($sqlCombosAtivos);
}

echo '<style type="text/css">';
echo 'td.acoes-clientes-cell{white-space:nowrap;}';
echo '.acoes-clientes-wrap{display:inline-flex;align-items:center;gap:6px;flex-wrap:nowrap;white-space:nowrap;}';
echo '.acoes-clientes-wrap a,.acoes-clientes-wrap span{display:inline-flex;align-items:center;justify-content:center;line-height:1;}';
echo '.icone-combo-cliente{min-width:12px;font-size:12px;opacity:.9;margin:0 1px;}';
echo '</style>';

echo '<small>';
echo '<table class="table table-hover">';
echo '<thead>';
echo '<tr>';
echo '<th>Nome</th>';
echo '<th class="esc">Telefone</th>';
echo '<th class="esc">CPF</th>';
echo '<th class="esc">Cadastro</th>';
echo '<th class="esc">Nascimento</th>';
echo '<th class="esc">Retorno</th>';
echo '<th class="esc">Cartões</th>';
echo '<th>Ações</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

for($i = 0; $i < $total_reg; $i++){
	$id = (int)$res[$i]['id'];
	$nome = (string)$res[$i]['nome'];
	$data_nasc = (string)$res[$i]['data_nasc'];
	$data_cad = (string)$res[$i]['data_cad'];
	$telefone = (string)$res[$i]['telefone'];
	$endereco = (string)$res[$i]['endereco'];
	$cartoes = (int)$res[$i]['cartoes'];
	$data_retorno = (string)$res[$i]['data_retorno'];
	$ultimo_servico = (int)$res[$i]['ultimo_servico'];
	$cpf = (string)$res[$i]['cpf'];
	$marketing = (string)$res[$i]['marketing'];

	$ocultar_mark = ($marketing === 'Não') ? 'ocultar' : '';

	$data_cadF = ($data_cad && $data_cad !== '0000-00-00') ? implode('/', array_reverse(explode('-', $data_cad))) : '';
	$data_nascF = ($data_nasc && $data_nasc !== '0000-00-00') ? implode('/', array_reverse(explode('-', $data_nasc))) : '';
	$data_retornoF = ($data_retorno && $data_retorno !== '0000-00-00') ? implode('/', array_reverse(explode('-', $data_retorno))) : '';
	if ($data_nascF === '' || $data_nascF === '00/00/0000') {
		$data_nascF = 'Sem Lançamento';
	}

	$whats = bb_whats_destino($telefone);

	$nome_servico = 'Nenhum!';
	if ($ultimo_servico > 0) {
		$stmtServico->bindValue(':id', $ultimo_servico, PDO::PARAM_INT);
		$stmtServico->execute();
		$tmpServico = $stmtServico->fetchColumn();
		if ($tmpServico !== false) {
			$nome_servico = (string)$tmpServico;
		}
	}

	$stmtUltimoReceber->bindValue(':pessoa', $id, PDO::PARAM_INT);
	$stmtUltimoReceber->execute();
	$ultimoReceber = $stmtUltimoReceber->fetch(PDO::FETCH_ASSOC);
	if ($ultimoReceber) {
		$obs_servico = (string)$ultimoReceber['obs'];
		$valor_servico = number_format((float)$ultimoReceber['valor'], 2, ',', '.');
		$data_servico = (string)$ultimoReceber['data_lanc'];
		$data_servico = ($data_servico && $data_servico !== '0000-00-00') ? implode('/', array_reverse(explode('-', $data_servico))) : '';
	} else {
		$obs_servico = '';
		$valor_servico = '';
		$data_servico = '';
	}

	$classe_retorno = '';
	if ($data_retorno !== '' && $data_retorno !== '0000-00-00' && strtotime($data_retorno) < strtotime($data_atual)) {
		$classe_retorno = 'text-danger';
	}

	$total_combos_ativos = 0;
	if ($stmtCombosAtivos) {
		$stmtCombosAtivos->bindValue(':cliente', $id, PDO::PARAM_INT);
		$stmtCombosAtivos->bindValue(':data_hoje', date('Y-m-d'), PDO::PARAM_STR);
		$stmtCombosAtivos->execute();
		$total_combos_ativos = (int)$stmtCombosAtivos->fetchColumn();
	}

	$titulo_combo = $total_combos_ativos > 0
		? 'Ver sessões do combo (' . $total_combos_ativos . ' ativo(s))'
		: 'Ver sessões do combo';

	$icone_combo_class = $total_combos_ativos > 0 ? 'text-info' : 'text-muted';
	$icone_combo_style = $total_combos_ativos > 0 ? 'font-weight:bold;' : '';

	echo '<tr>';
	echo '<td>' . bb_h($nome) . '</td>';
	echo '<td class="esc">' . bb_h($telefone) . '</td>';
	echo '<td class="esc">' . bb_h($cpf) . '</td>';
	echo '<td class="esc">' . bb_h($data_cadF) . '</td>';
	echo '<td class="esc">' . bb_h($data_nascF) . '</td>';
	echo '<td class="esc ' . $classe_retorno . '">' . bb_h($data_retornoF) . '</td>';
	echo '<td class="esc">' . $cartoes . '</td>';
	echo '<td class="acoes-clientes-cell"><span class="acoes-clientes-wrap">';

	echo '<a href="javascript:void(0)" onclick="editar('
		. bb_attr_js((string)$id) . ','
		. bb_attr_js($nome) . ','
		. bb_attr_js($telefone) . ','
		. bb_attr_js($endereco) . ','
		. bb_attr_js($data_nasc) . ','
		. bb_attr_js((string)$cartoes) . ','
		. bb_attr_js($cpf)
		. '); return false;" title="Editar Dados"><i class="fa fa-edit text-primary"></i></a>';

	echo '<a href="javascript:void(0)" onclick="mostrar('
		. bb_attr_js((string)$id) . ','
		. bb_attr_js($nome) . ','
		. bb_attr_js($telefone) . ','
		. bb_attr_js((string)$cartoes) . ','
		. bb_attr_js($data_cadF) . ','
		. bb_attr_js($data_nascF) . ','
		. bb_attr_js($endereco) . ','
		. bb_attr_js($data_retornoF) . ','
		. bb_attr_js($nome_servico) . ','
		. bb_attr_js($obs_servico) . ','
		. bb_attr_js($valor_servico) . ','
		. bb_attr_js($data_servico)
		. '); return false;" title="Ver Dados"><i class="fa fa-info-circle text-secondary"></i></a>';

	echo '<a href="javascript:void(0)" onclick="cobranca('
		. bb_attr_js((string)$id) . ','
		. bb_attr_js($nome)
		. '); return false;" title="Cobrança Recorrente"><i class="fa fa-money" style="color:green"></i></a>';

	echo '<a href="javascript:void(0)" class="icone-combo-cliente" data-id="' . $id . '" data-nome="' . bb_h($nome) . '" onclick="return abrirCombosClienteAdmin(this);" title="' . bb_h($titulo_combo) . '"><i class="fa fa-gift ' . $icone_combo_class . '" style="' . $icone_combo_style . '"></i></a>';

	echo '<span class="dropdown head-dpdn2" style="display:inline-flex;">';
	echo '<a href="javascript:void(0)" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false" title="Excluir Cliente"><i class="fa fa-trash-o text-danger"></i></a>';
	echo '<ul class="dropdown-menu" style="margin-left:-230px;">';
	echo '<li><div class="notification_desc2">';
	echo '<p>Confirmar Exclusão? <a href="javascript:void(0)" onclick="excluir(' . bb_attr_js((string)$id) . '); return false;"><span class="text-danger">Sim</span></a></p>';
	echo '</div></li>';
	echo '</ul>';
	echo '</span>';

	if ($whats !== '') {
		echo '<a href="http://api.whatsapp.com/send?1=pt_BR&phone=' . bb_h($whats) . '&text=" target="_blank" title="Abrir Whatsapp"><i class="fa fa-whatsapp verde"></i></a>';
	}

	echo '<a href="javascript:void(0)" onclick="contrato('
		. bb_attr_js((string)$id) . ','
		. bb_attr_js($nome)
		. '); return false;" title="Contrato de Serviço"><i class="fa fa-file-pdf-o text-primary"></i></a>';

	echo '<a href="rel/rel_servicos_clientes_class.php?id=' . $id . '" target="_blank" title="Últimos Serviços"><i class="fa fa-file-pdf-o text-danger"></i></a>';

	echo '<a class="' . $ocultar_mark . '" href="javascript:void(0)" onclick="excluirMarketing(' . bb_attr_js((string)$id) . '); return false;" title="Remover do Marketing Whatsapp"><i class="fa fa-ban text-danger"></i></a>';

	echo '</span></td>';
	echo '</tr>';
}

echo '</tbody>';
echo '<small><div align="center" id="mensagem-excluir"></div></small>';
echo '</table>';
echo '</small>';

echo '<hr>';
echo '<div class="row" align="center">';
echo '<nav aria-label="Page navigation example">';
echo '<ul class="pagination">';
echo '<li class="page-item"><a onclick="listarClientes(0); return false;" class="paginador" href="javascript:void(0)" aria-label="Previous"><span aria-hidden="true">&laquo;</span><span class="sr-only">Previous</span></a></li>';

for($i = 0; $i < $num_paginas; $i++){
	$estilo = '';
	if ($pagina >= ($i - 2) && $pagina <= ($i + 2)) {
		if ($pagina == $i) {
			$estilo = 'active';
		}
		$pag_atual = $i + 1;
		echo '<li class="page-item ' . $estilo . '"><a onclick="listarClientes(' . $i . '); return false;" class="paginador" href="javascript:void(0)">' . $pag_atual . '</a></li>';
	}
}

echo '<li class="page-item"><a onclick="listarClientes(' . $ultimo_reg . '); return false;" class="paginador" href="javascript:void(0)" aria-label="Next"><span aria-hidden="true">&raquo;</span><span class="sr-only">Next</span></a></li>';
echo '</ul>';
echo '</nav>';
echo '</div>';
?>