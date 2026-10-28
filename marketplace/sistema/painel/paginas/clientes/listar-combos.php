<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';

if (!function_exists('bb_h_combo_admin')) {
	function bb_h_combo_admin($valor){
		return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
	}
}

if (!function_exists('bb_coluna_existe_combo_admin')) {
	function bb_coluna_existe_combo_admin(PDO $pdo, $tabela, $coluna)
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

if (!function_exists('bb_tabela_existe_combo_admin')) {
	function bb_tabela_existe_combo_admin(PDO $pdo, $tabela)
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

$id_cliente = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$id_cliente = $id_cliente ? (int)$id_cliente : 0;

if ($id_cliente <= 0) {
	echo '<small>Cliente inválido!</small>';
	return;
}

$tem_modulo_combo =
	bb_tabela_existe_combo_admin($pdo, 'assinaturas') &&
	bb_tabela_existe_combo_admin($pdo, 'itens_assinaturas') &&
	bb_tabela_existe_combo_admin($pdo, 'servicos') &&
	bb_tabela_existe_combo_admin($pdo, 'assinaturas_consumos') &&
	bb_coluna_existe_combo_admin($pdo, 'itens_assinaturas', 'servico') &&
	bb_coluna_existe_combo_admin($pdo, 'servicos', 'combo_ativo') &&
	bb_coluna_existe_combo_admin($pdo, 'servicos', 'combo_qtd_sessoes');

if (!$tem_modulo_combo) {
	echo '<small>Módulo de combos não disponível nesta loja.</small>';
	return;
}

$tem_ativo_ass = bb_coluna_existe_combo_admin($pdo, 'assinaturas', 'ativo');
$tem_cancelado_ass = bb_coluna_existe_combo_admin($pdo, 'assinaturas', 'cancelado');
$tem_data_vencimento_ass = bb_coluna_existe_combo_admin($pdo, 'assinaturas', 'data_vencimento');
$expr_vencimento = $tem_data_vencimento_ass ? "COALESCE(a.data_vencimento, a.vencimento)" : "a.vencimento";

$sql = "SELECT
			a.id,
			a.data,
			{$expr_vencimento} AS vencimento_combo,
			ia.nome AS nome_item,
			ia.servico AS id_servico,
			s.nome AS nome_servico,
			s.combo_qtd_sessoes
		FROM assinaturas a
		INNER JOIN itens_assinaturas ia ON a.item = ia.id
		INNER JOIN servicos s ON ia.servico = s.id
		WHERE a.cliente = :cliente
		AND a.pago = 'Sim'
		AND s.combo_ativo = 'Sim'";

if ($tem_ativo_ass) {
	$sql .= " AND a.ativo = 'Sim'";
}
if ($tem_cancelado_ass) {
	$sql .= " AND (a.cancelado IS NULL OR a.cancelado != 'Sim')";
}

$sql .= " ORDER BY a.id DESC";

$query = $pdo->prepare($sql);
$query->bindValue(':cliente', $id_cliente, PDO::PARAM_INT);
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = count($res);

if ($total_reg <= 0) {
	echo '<small>Este cliente não possui combos vinculados.</small>';
	return;
}

$total_usadas = 0;
$total_restantes = 0;
$total_combos = 0;
$historico = array();

foreach ($res as $row) {
	$id_assinatura = (int)$row['id'];
	$total_sessoes = (int)($row['combo_qtd_sessoes'] ?? 0);

	$qUsadas = $pdo->prepare("SELECT COUNT(*) FROM assinaturas_consumos WHERE assinatura = :assinatura AND cliente = :cliente");
	$qUsadas->bindValue(':assinatura', $id_assinatura, PDO::PARAM_INT);
	$qUsadas->bindValue(':cliente', $id_cliente, PDO::PARAM_INT);
	$qUsadas->execute();
	$usadas = (int)$qUsadas->fetchColumn();

	$restantes = $total_sessoes - $usadas;
	if ($restantes < 0) {
		$restantes = 0;
	}

	$total_combos++;
	$total_usadas += $usadas;
	$total_restantes += $restantes;
}

echo '<div class="row" style="margin-bottom:12px">';
echo '<div class="col-md-4"><strong>Combos:</strong> ' . $total_combos . '</div>';
echo '<div class="col-md-4"><strong>Sessões usadas:</strong> ' . $total_usadas . '</div>';
echo '<div class="col-md-4"><strong>Saldo restante:</strong> ' . $total_restantes . '</div>';
echo '</div>';

echo '<small>';
echo '<table class="table table-hover" id="tabela-combos-admin">';
echo '<thead>';
echo '<tr>';
echo '<th>Combo</th>';
echo '<th class="esc">Serviço</th>';
echo '<th class="esc">Sessões</th>';
echo '<th class="esc">Usadas</th>';
echo '<th class="esc">Restantes</th>';
echo '<th class="esc">Vencimento</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

foreach ($res as $row) {
	$id_assinatura = (int)$row['id'];
	$total_sessoes = (int)($row['combo_qtd_sessoes'] ?? 0);

	$qUsadas = $pdo->prepare("SELECT COUNT(*) FROM assinaturas_consumos WHERE assinatura = :assinatura AND cliente = :cliente");
	$qUsadas->bindValue(':assinatura', $id_assinatura, PDO::PARAM_INT);
	$qUsadas->bindValue(':cliente', $id_cliente, PDO::PARAM_INT);
	$qUsadas->execute();
	$usadas = (int)$qUsadas->fetchColumn();

	$restantes = $total_sessoes - $usadas;
	if ($restantes < 0) {
		$restantes = 0;
	}

	$vencimento = (string)($row['vencimento_combo'] ?? '');
	$vencimentoF = '-';
	if ($vencimento !== '' && $vencimento !== '0000-00-00') {
		$vencimentoF = implode('/', array_reverse(explode('-', $vencimento)));
	}

	echo '<tr>';
	echo '<td>' . bb_h_combo_admin($row['nome_item']) . '</td>';
	echo '<td class="esc">' . bb_h_combo_admin($row['nome_servico']) . '</td>';
	echo '<td class="esc">' . $total_sessoes . '</td>';
	echo '<td class="esc">' . $usadas . '</td>';
	echo '<td class="esc"><strong class="verde">' . $restantes . '</strong></td>';
	echo '<td class="esc">' . bb_h_combo_admin($vencimentoF) . '</td>';
	echo '</tr>';

	$qHist = $pdo->prepare("SELECT ac.data_baixa, ac.obs, ac.agendamento, s.nome AS nome_servico
		FROM assinaturas_consumos ac
		LEFT JOIN servicos s ON ac.servico = s.id
		WHERE ac.assinatura = :assinatura AND ac.cliente = :cliente
		ORDER BY ac.id DESC");
	$qHist->bindValue(':assinatura', $id_assinatura, PDO::PARAM_INT);
	$qHist->bindValue(':cliente', $id_cliente, PDO::PARAM_INT);
	$qHist->execute();
	$historico_ass = $qHist->fetchAll(PDO::FETCH_ASSOC);

	foreach ($historico_ass as $hist) {
		$historico[] = $hist;
	}
}

echo '</tbody>';
echo '</table>';
echo '</small>';

if (count($historico) > 0) {
	echo '<hr>';
	echo '<small>';
	echo '<table class="table table-hover" id="tabela-historico-combos-admin">';
	echo '<thead><tr><th>Data</th><th>Serviço</th><th>Agendamento</th><th>Obs</th></tr></thead><tbody>';

	foreach ($historico as $hist) {
		$data_baixa = (string)($hist['data_baixa'] ?? '');
		$data_baixaF = $data_baixa ? date('d/m/Y H:i', strtotime($data_baixa)) : '-';
		$nome_servico_hist = bb_h_combo_admin($hist['nome_servico'] ?? 'Sem Serviço');
		$agendamento = (int)($hist['agendamento'] ?? 0);
		$obs = bb_h_combo_admin($hist['obs'] ?? '');

		echo '<tr>';
		echo '<td>' . $data_baixaF . '</td>';
		echo '<td>' . $nome_servico_hist . '</td>';
		echo '<td>#' . $agendamento . '</td>';
		echo '<td>' . $obs . '</td>';
		echo '</tr>';
	}

	echo '</tbody></table></small>';
}
?>