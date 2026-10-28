<?php
require_once __DIR__ . '/../_context_bootstrap.php';
// ============================================================================
// Arquivo: /sistema/painel/paginas/servicos_agenda/sincronizar-combos.php
// Finalidade:
// - sincronizar vendas de combos já lançadas em "receber" para "assinaturas"
// - sem alterar a lógica financeira existente
// - respeita o banco já resolvido pelo conexao.php (tenant /loja/{slug}/)
// Uso:
// - subir o arquivo
// - acessar autenticado no painel da loja
// - ele processa e mostra resumo
// ============================================================================

@session_start();

if (!isset($pdo) || !($pdo instanceof PDO)) {
	require_once __DIR__ . '/../../../conexao.php';
}

if (!function_exists('bb_sync_coluna_existe')) {
	function bb_sync_coluna_existe(PDO $pdo, $tabela, $coluna)
	{
		try {
			$st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
				WHERE TABLE_SCHEMA = DATABASE()
				AND TABLE_NAME = :tabela
				AND COLUMN_NAME = :coluna");
			$st->execute([
				':tabela' => $tabela,
				':coluna' => $coluna
			]);
			return ((int)$st->fetchColumn() > 0);
		} catch (Throwable $e) {
			return false;
		}
	}
}

if (!function_exists('bb_sync_tabela_existe')) {
	function bb_sync_tabela_existe(PDO $pdo, $tabela)
	{
		try {
			$st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
				WHERE TABLE_SCHEMA = DATABASE()
				AND TABLE_NAME = :tabela");
			$st->execute([':tabela' => $tabela]);
			return ((int)$st->fetchColumn() > 0);
		} catch (Throwable $e) {
			return false;
		}
	}
}

if (!function_exists('bb_sync_data_combo')) {
	function bb_sync_data_combo($dataBase, $diasValidade)
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

if (!bb_sync_tabela_existe($pdo, 'receber') ||
	!bb_sync_tabela_existe($pdo, 'servicos') ||
	!bb_sync_tabela_existe($pdo, 'itens_assinaturas') ||
	!bb_sync_tabela_existe($pdo, 'assinaturas')) {
	echo 'Estrutura do módulo não encontrada!';
	exit();
}

$tem_ativo_assinaturas = bb_sync_coluna_existe($pdo, 'assinaturas', 'ativo');
$tem_cancelado_assinaturas = bb_sync_coluna_existe($pdo, 'assinaturas', 'cancelado');
$tem_data_vencimento = bb_sync_coluna_existe($pdo, 'assinaturas', 'data_vencimento');
$tem_frequencia = bb_sync_coluna_existe($pdo, 'assinaturas', 'frequencia');
$tem_combo_ativo = bb_sync_coluna_existe($pdo, 'servicos', 'combo_ativo');
$tem_combo_validade = bb_sync_coluna_existe($pdo, 'servicos', 'combo_validade_dias');

if (!$tem_combo_ativo) {
	echo 'Coluna combo_ativo não encontrada em servicos!';
	exit();
}

$sql = "SELECT
			r.id,
			r.pessoa AS cliente,
			r.servico,
			r.valor,
			r.data_lanc,
			r.data_pgto,
			r.data_venc,
			r.pago,
			r.descricao,
			s.combo_validade_dias,
			s.nome AS nome_servico,
			ia.id AS item_id,
			ia.grupo AS grupo_id,
			ia.nome AS nome_item
		FROM receber r
		INNER JOIN servicos s ON r.servico = s.id
		LEFT JOIN itens_assinaturas ia ON ia.servico = s.id
		WHERE r.tipo = 'Serviço'
		AND r.pago = 'Sim'
		AND s.combo_ativo = 'Sim'
		ORDER BY r.id ASC";

$query = $pdo->query($sql);
$res = $query->fetchAll(PDO::FETCH_ASSOC);

$total_lidos = count($res);
$total_inseridos = 0;
$total_atualizados = 0;
$total_pulados = 0;
$linhas = [];

foreach ($res as $row) {
	$id_receber = (int)$row['id'];
	$id_cliente = (int)$row['cliente'];
	$id_servico = (int)$row['servico'];
	$id_item = (int)($row['item_id'] ?? 0);
	$id_grupo = (int)($row['grupo_id'] ?? 0);
	$valor = (float)$row['valor'];
	$data_base = trim((string)($row['data_pgto'] ?: $row['data_lanc']));
	$dias_validade = (int)($row['combo_validade_dias'] ?? 0);
	$vencimento_combo = bb_sync_data_combo($data_base, $dias_validade);
	$nome_servico = (string)($row['nome_servico'] ?? 'Combo');
	$nome_item = (string)($row['nome_item'] ?? '');

	if ($id_cliente <= 0 || $id_servico <= 0 || $id_item <= 0) {
		$total_pulados++;
		$linhas[] = [
			'id_receber' => $id_receber,
			'status' => 'Pulado',
			'detalhe' => 'Item de assinatura não vinculado ao serviço combo.'
		];
		continue;
	}

	$sqlBusca = "SELECT id FROM assinaturas
		WHERE cliente = :cliente
		AND item = :item
		AND data = :data";

	if ($tem_cancelado_assinaturas) {
		$sqlBusca .= " AND (cancelado IS NULL OR cancelado != 'Sim')";
	}

	$sqlBusca .= " ORDER BY id DESC LIMIT 1";

	$stBusca = $pdo->prepare($sqlBusca);
	$stBusca->bindValue(':cliente', $id_cliente, PDO::PARAM_INT);
	$stBusca->bindValue(':item', $id_item, PDO::PARAM_INT);
	$stBusca->bindValue(':data', $data_base, PDO::PARAM_STR);
	$stBusca->execute();
	$id_ass_existente = (int)($stBusca->fetchColumn() ?: 0);

	if ($id_ass_existente > 0) {
		$sqlUpd = "UPDATE assinaturas SET
			cliente = :cliente,
			grupo = :grupo,
			item = :item,
			valor = :valor,
			pago = 'Sim',
			vencimento = :vencimento";

		if ($tem_data_vencimento) {
			$sqlUpd .= ", data_vencimento = :data_vencimento";
		}
		if ($tem_frequencia) {
			$sqlUpd .= ", frequencia = :frequencia";
		}
		if ($tem_ativo_assinaturas) {
			$sqlUpd .= ", ativo = 'Sim'";
		}

		$sqlUpd .= " WHERE id = :id";

		$stUpd = $pdo->prepare($sqlUpd);
		$stUpd->bindValue(':id', $id_ass_existente, PDO::PARAM_INT);
		$stUpd->bindValue(':cliente', $id_cliente, PDO::PARAM_INT);
		$stUpd->bindValue(':grupo', $id_grupo, PDO::PARAM_INT);
		$stUpd->bindValue(':item', $id_item, PDO::PARAM_INT);
		$stUpd->bindValue(':valor', $valor);
		$stUpd->bindValue(':vencimento', $vencimento_combo, PDO::PARAM_STR);
		if ($tem_data_vencimento) {
			$stUpd->bindValue(':data_vencimento', $vencimento_combo, PDO::PARAM_STR);
		}
		if ($tem_frequencia) {
			$stUpd->bindValue(':frequencia', $dias_validade, PDO::PARAM_INT);
		}
		$stUpd->execute();

		$total_atualizados++;
		$linhas[] = [
			'id_receber' => $id_receber,
			'status' => 'Atualizado',
			'detalhe' => 'Assinatura #' . $id_ass_existente . ' sincronizada para ' . $nome_servico
		];
		continue;
	}

	$campos = "cliente, data, pago, grupo, item, valor, vencimento";
	$valores = ":cliente, :data, 'Sim', :grupo, :item, :valor, :vencimento";

	if ($tem_frequencia) {
		$campos .= ", frequencia";
		$valores .= ", :frequencia";
	}
	if ($tem_data_vencimento) {
		$campos .= ", data_vencimento";
		$valores .= ", :data_vencimento";
	}
	if ($tem_ativo_assinaturas) {
		$campos .= ", ativo";
		$valores .= ", 'Sim'";
	}

	$sqlIns = "INSERT INTO assinaturas ({$campos}) VALUES ({$valores})";
	$stIns = $pdo->prepare($sqlIns);
	$stIns->bindValue(':cliente', $id_cliente, PDO::PARAM_INT);
	$stIns->bindValue(':data', $data_base, PDO::PARAM_STR);
	$stIns->bindValue(':grupo', $id_grupo, PDO::PARAM_INT);
	$stIns->bindValue(':item', $id_item, PDO::PARAM_INT);
	$stIns->bindValue(':valor', $valor);
	$stIns->bindValue(':vencimento', $vencimento_combo, PDO::PARAM_STR);
	if ($tem_frequencia) {
		$stIns->bindValue(':frequencia', $dias_validade, PDO::PARAM_INT);
	}
	if ($tem_data_vencimento) {
		$stIns->bindValue(':data_vencimento', $vencimento_combo, PDO::PARAM_STR);
	}
	$stIns->execute();

	$total_inseridos++;
	$novo_id_ass = (int)$pdo->lastInsertId();
	$linhas[] = [
		'id_receber' => $id_receber,
		'status' => 'Inserido',
		'detalhe' => 'Assinatura #' . $novo_id_ass . ' criada para ' . $nome_servico
	];
}

echo '<div class="bs-example widget-shadow" style="padding:15px">';
echo '<h4>Sincronização de Combos</h4>';
echo '<p><strong>Lidos:</strong> ' . $total_lidos . '</p>';
echo '<p><strong>Inseridos:</strong> ' . $total_inseridos . '</p>';
echo '<p><strong>Atualizados:</strong> ' . $total_atualizados . '</p>';
echo '<p><strong>Pulados:</strong> ' . $total_pulados . '</p>';

if (!empty($linhas)) {
	echo '<table class="table table-hover">';
	echo '<thead><tr><th>Receber</th><th>Status</th><th>Detalhe</th></tr></thead><tbody>';
	foreach ($linhas as $linha) {
		echo '<tr>';
		echo '<td>#' . (int)$linha['id_receber'] . '</td>';
		echo '<td>' . htmlspecialchars($linha['status'], ENT_QUOTES, 'UTF-8') . '</td>';
		echo '<td>' . htmlspecialchars($linha['detalhe'], ENT_QUOTES, 'UTF-8') . '</td>';
		echo '</tr>';
	}
	echo '</tbody></table>';
}

echo '</div>';
?>