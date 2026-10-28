<?php
// ============================================================================
// Arquivo: /sistema/painel/paginas/agendamentos/funcoes-assinatura-combo.php
// Finalidade:
// - regras de combo/pacote sem alterar a lógica financeira
// - leitura do saldo do combo
// - baixa de 1 sessão no agendamento
// - registro da venda do combo quando o combo é pago pelo painel de agendamentos
// Marketplace / multi-tenant:
// - este arquivo sempre opera no banco já selecionado pela conexão atual
// ============================================================================

if (!function_exists('bb_combo_table_exists')) {
	function bb_combo_table_exists($pdo, $tabela){
		try{
			$st = $pdo->prepare("SHOW TABLES LIKE :tabela");
			$st->bindValue(':tabela', $tabela, PDO::PARAM_STR);
			$st->execute();
			return (bool)$st->fetchColumn();
		}catch(Throwable $e){
			return false;
		}
	}
}

if (!function_exists('bb_combo_col_exists')) {
	function bb_combo_col_exists($pdo, $tabela, $coluna){
		try{
			$st = $pdo->prepare("SHOW COLUMNS FROM `{$tabela}` LIKE :coluna");
			$st->bindValue(':coluna', $coluna, PDO::PARAM_STR);
			$st->execute();
			return (bool)$st->fetch(PDO::FETCH_ASSOC);
		}catch(Throwable $e){
			return false;
		}
	}
}

if (!function_exists('bb_combo_data_vencimento_expr')) {
	function bb_combo_data_vencimento_expr($pdo){
		if (bb_combo_col_exists($pdo, 'assinaturas', 'data_vencimento')) {
			return "COALESCE(a.data_vencimento, a.vencimento)";
		}
		return "a.vencimento";
	}
}

if (!function_exists('bb_combo_data_vencimento_final')) {
	function bb_combo_data_vencimento_final($dataBase, $diasValidade){
		$dataBase = trim((string)$dataBase);
		$diasValidade = (int)$diasValidade;

		if($dataBase === '' || $dataBase === '0000-00-00'){
			$dataBase = date('Y-m-d');
		}

		if($diasValidade <= 0){
			return $dataBase;
		}

		return date('Y-m-d', strtotime('+' . $diasValidade . ' days', strtotime($dataBase)));
	}
}

if (!function_exists('comboJaConsumidoNoAgendamento')) {
	function comboJaConsumidoNoAgendamento($pdo, $assinatura, $agendamento){
		if (!bb_combo_table_exists($pdo, 'assinaturas_consumos')) {
			return false;
		}

		$query = $pdo->prepare("SELECT id
			FROM assinaturas_consumos
			WHERE assinatura = :assinatura
			AND agendamento = :agendamento
			LIMIT 1");

		$query->bindValue(":assinatura", (int)$assinatura, PDO::PARAM_INT);
		$query->bindValue(":agendamento", (int)$agendamento, PDO::PARAM_INT);
		$query->execute();

		return (bool)$query->fetch(PDO::FETCH_ASSOC);
	}
}

if (!function_exists('buscarAssinaturaComboAtiva')) {
	function buscarAssinaturaComboAtiva($pdo, $cliente, $servico){
		if (!bb_combo_table_exists($pdo, 'assinaturas') || !bb_combo_table_exists($pdo, 'itens_assinaturas') || !bb_combo_table_exists($pdo, 'servicos')) {
			return false;
		}

		$exprVencimento = bb_combo_data_vencimento_expr($pdo);
		$dataHojeTenant = date('Y-m-d');

		$query = $pdo->prepare("SELECT
				a.*,
				ia.id as item_id,
				ia.nome as item_nome,
				ia.servico as id_servico_item,
				s.combo_qtd_sessoes,
				s.combo_validade_dias,
				s.nome as nome_servico
			FROM assinaturas a
			INNER JOIN itens_assinaturas ia ON a.item = ia.id
			INNER JOIN servicos s ON ia.servico = s.id
			WHERE a.cliente = :cliente
			AND ia.servico = :servico
			AND a.ativo = 'Sim'
			AND a.pago = 'Sim'
			AND (a.cancelado IS NULL OR a.cancelado != 'Sim')
			AND ({$exprVencimento} IS NULL OR {$exprVencimento} >= :data_hoje)
			AND s.combo_ativo = 'Sim'
			ORDER BY a.id DESC
			LIMIT 1");

		$query->bindValue(":cliente", (int)$cliente, PDO::PARAM_INT);
		$query->bindValue(":servico", (int)$servico, PDO::PARAM_INT);
		$query->bindValue(":data_hoje", $dataHojeTenant, PDO::PARAM_STR);
		$query->execute();

		return $query->fetch(PDO::FETCH_ASSOC);
	}
}

if (!function_exists('buscarAssinaturaComboPorId')) {
	function buscarAssinaturaComboPorId($pdo, $assinatura, $cliente, $servico){
		if (!bb_combo_table_exists($pdo, 'assinaturas') || !bb_combo_table_exists($pdo, 'itens_assinaturas') || !bb_combo_table_exists($pdo, 'servicos')) {
			return false;
		}

		$exprVencimento = bb_combo_data_vencimento_expr($pdo);
		$dataHojeTenant = date('Y-m-d');

		$query = $pdo->prepare("SELECT
				a.*,
				ia.id as item_id,
				ia.nome as item_nome,
				ia.servico as id_servico_item,
				s.combo_qtd_sessoes,
				s.combo_validade_dias,
				s.nome as nome_servico
			FROM assinaturas a
			INNER JOIN itens_assinaturas ia ON a.item = ia.id
			INNER JOIN servicos s ON ia.servico = s.id
			WHERE a.id = :assinatura
			AND a.cliente = :cliente
			AND ia.servico = :servico
			AND a.ativo = 'Sim'
			AND a.pago = 'Sim'
			AND (a.cancelado IS NULL OR a.cancelado != 'Sim')
			AND ({$exprVencimento} IS NULL OR {$exprVencimento} >= :data_hoje)
			AND s.combo_ativo = 'Sim'
			LIMIT 1");

		$query->bindValue(":assinatura", (int)$assinatura, PDO::PARAM_INT);
		$query->bindValue(":cliente", (int)$cliente, PDO::PARAM_INT);
		$query->bindValue(":servico", (int)$servico, PDO::PARAM_INT);
		$query->bindValue(":data_hoje", $dataHojeTenant, PDO::PARAM_STR);
		$query->execute();

		return $query->fetch(PDO::FETCH_ASSOC);
	}
}

if (!function_exists('saldoAssinaturaCombo')) {
	function saldoAssinaturaCombo($pdo, $assinatura, $total_sessoes){
		if (!bb_combo_table_exists($pdo, 'assinaturas_consumos')) {
			return array(
				'usadas' => 0,
				'restantes' => (int)$total_sessoes
			);
		}

		$query = $pdo->prepare("SELECT COUNT(*) as total
			FROM assinaturas_consumos
			WHERE assinatura = :assinatura");

		$query->bindValue(":assinatura", (int)$assinatura, PDO::PARAM_INT);
		$query->execute();

		$res = $query->fetch(PDO::FETCH_ASSOC);

		$usadas = isset($res['total']) ? (int)$res['total'] : 0;
		$restantes = (int)$total_sessoes - $usadas;

		if($restantes < 0){
			$restantes = 0;
		}

		return array(
			'usadas' => $usadas,
			'restantes' => $restantes
		);
	}
}

if (!function_exists('resumoAssinaturaComboAtiva')) {
	function resumoAssinaturaComboAtiva($pdo, $cliente, $servico){
		$assinatura = buscarAssinaturaComboAtiva($pdo, $cliente, $servico);
		if(!$assinatura){
			return false;
		}
		$saldo = saldoAssinaturaCombo($pdo, (int)$assinatura['id'], (int)$assinatura['combo_qtd_sessoes']);
		$assinatura['usadas'] = (int)$saldo['usadas'];
		$assinatura['restantes'] = (int)$saldo['restantes'];
		return $assinatura;
	}
}

if (!function_exists('baixarAssinaturaCombo')) {
	function baixarAssinaturaCombo($pdo, $assinatura, $cliente, $servico, $agendamento, $usuario, $obs = ''){
		$assinatura = (int)$assinatura;
		$cliente = (int)$cliente;
		$servico = (int)$servico;
		$agendamento = (int)$agendamento;
		$usuario = (int)$usuario;
		$obs = trim((string)$obs);

		if ($assinatura <= 0 || $cliente <= 0 || $servico <= 0 || $agendamento <= 0) {
			return false;
		}

		if (!bb_combo_table_exists($pdo, 'assinaturas_consumos')) {
			return false;
		}

		if (comboJaConsumidoNoAgendamento($pdo, $assinatura, $agendamento)) {
			return true;
		}

		$query = $pdo->prepare("INSERT INTO assinaturas_consumos SET
			assinatura = :assinatura,
			cliente = :cliente,
			servico = :servico,
			agendamento = :agendamento,
			data_baixa = :data_baixa,
			usuario = :usuario,
			obs = :obs");

		$query->bindValue(":assinatura", $assinatura, PDO::PARAM_INT);
		$query->bindValue(":cliente", $cliente, PDO::PARAM_INT);
		$query->bindValue(":servico", $servico, PDO::PARAM_INT);
		$query->bindValue(":agendamento", $agendamento, PDO::PARAM_INT);
		$query->bindValue(":data_baixa", date('Y-m-d H:i:s'), PDO::PARAM_STR);
		$query->bindValue(":usuario", $usuario, PDO::PARAM_INT);
		$query->bindValue(":obs", $obs, PDO::PARAM_STR);

		return $query->execute();
	}
}

if (!function_exists('bb_combo_buscar_grupo_padrao')) {
	function bb_combo_buscar_grupo_padrao($pdo){
		if (!bb_combo_table_exists($pdo, 'grupo_assinaturas')) {
			return 0;
		}

		try{
			$st = $pdo->query("SELECT id FROM grupo_assinaturas ORDER BY id ASC LIMIT 1");
			return (int)($st->fetchColumn() ?: 0);
		}catch(Throwable $e){
			return 0;
		}
	}
}

if (!function_exists('bb_combo_garantir_item_assinatura')) {
	function bb_combo_garantir_item_assinatura($pdo, $dadosServico){
		if (!bb_combo_table_exists($pdo, 'itens_assinaturas')) {
			return false;
		}

		$idServico = (int)($dadosServico['id'] ?? 0);
		if($idServico <= 0){
			return false;
		}

		$stBusca = $pdo->prepare("SELECT * FROM itens_assinaturas WHERE servico = :servico ORDER BY id DESC LIMIT 1");
		$stBusca->bindValue(':servico', $idServico, PDO::PARAM_INT);
		$stBusca->execute();
		$item = $stBusca->fetch(PDO::FETCH_ASSOC);
		if($item){
			return $item;
		}

		$campos = array('nome', 'servico');
		$placeholders = array(':nome', ':servico');
		$params = array(
			':nome' => (string)($dadosServico['nome'] ?? 'Combo'),
			':servico' => $idServico
		);

		if (bb_combo_col_exists($pdo, 'itens_assinaturas', 'grupo')) {
			$campos[] = 'grupo';
			$placeholders[] = ':grupo';
			$params[':grupo'] = bb_combo_buscar_grupo_padrao($pdo);
		}

		if (bb_combo_col_exists($pdo, 'itens_assinaturas', 'valor')) {
			$campos[] = 'valor';
			$placeholders[] = ':valor';
			$params[':valor'] = (float)($dadosServico['valor'] ?? 0);
		}

		if (bb_combo_col_exists($pdo, 'itens_assinaturas', 'ativo')) {
			$campos[] = 'ativo';
			$placeholders[] = ':ativo';
			$params[':ativo'] = 'Sim';
		}

		for($i = 1; $i <= 12; $i++){
			$coluna = 'c' . $i;
			if (bb_combo_col_exists($pdo, 'itens_assinaturas', $coluna)) {
				$campos[] = $coluna;
				$placeholders[] = ':' . $coluna;
				$params[':' . $coluna] = ($i === 1) ? 1 : 0;
			}
		}

		$sql = "INSERT INTO itens_assinaturas (" . implode(', ', $campos) . ")
			VALUES (" . implode(', ', $placeholders) . ")";
		$stIns = $pdo->prepare($sql);
		foreach($params as $chave => $valor){
			$stIns->bindValue($chave, $valor);
		}
		$stIns->execute();

		$novoId = (int)$pdo->lastInsertId();
		if($novoId <= 0){
			return false;
		}

		$stNovo = $pdo->prepare("SELECT * FROM itens_assinaturas WHERE id = :id LIMIT 1");
		$stNovo->bindValue(':id', $novoId, PDO::PARAM_INT);
		$stNovo->execute();

		return $stNovo->fetch(PDO::FETCH_ASSOC);
	}
}

if (!function_exists('registrarVendaAssinaturaCombo')) {
	function registrarVendaAssinaturaCombo($pdo, $cliente, $servico, $valorTotal, $dataVenda, $pagoCombo = 'Não'){
		$cliente = (int)$cliente;
		$servico = (int)$servico;
		$valorTotal = (float)$valorTotal;
		$dataVenda = trim((string)$dataVenda);
		$pagoCombo = trim((string)$pagoCombo);

		if($cliente <= 0 || $servico <= 0){
			return false;
		}

		if (!bb_combo_table_exists($pdo, 'assinaturas') || !bb_combo_table_exists($pdo, 'itens_assinaturas') || !bb_combo_table_exists($pdo, 'servicos')) {
			return false;
		}

		$stServico = $pdo->prepare("SELECT * FROM servicos WHERE id = :id LIMIT 1");
		$stServico->bindValue(':id', $servico, PDO::PARAM_INT);
		$stServico->execute();
		$dadosServico = $stServico->fetch(PDO::FETCH_ASSOC);

		if(!$dadosServico){
			return false;
		}

		if((string)($dadosServico['combo_ativo'] ?? 'Não') !== 'Sim'){
			return true;
		}

		$item = bb_combo_garantir_item_assinatura($pdo, $dadosServico);
		if(!$item){
			return false;
		}

		$idItem = (int)($item['id'] ?? 0);
		$idGrupo = isset($item['grupo']) ? (int)$item['grupo'] : 0;
		$diasValidade = (int)($dadosServico['combo_validade_dias'] ?? 0);
		$dataVencimento = bb_combo_data_vencimento_final($dataVenda, $diasValidade);

		$temAtivo = bb_combo_col_exists($pdo, 'assinaturas', 'ativo');
		$temCancelado = bb_combo_col_exists($pdo, 'assinaturas', 'cancelado');
		$temDataVencimento = bb_combo_col_exists($pdo, 'assinaturas', 'data_vencimento');
		$temFrequencia = bb_combo_col_exists($pdo, 'assinaturas', 'frequencia');

		$sqlBusca = "SELECT id FROM assinaturas
			WHERE cliente = :cliente
			AND item = :item
			AND data = :data";
		if($temCancelado){
			$sqlBusca .= " AND (cancelado IS NULL OR cancelado != 'Sim')";
		}
		$sqlBusca .= " ORDER BY id DESC LIMIT 1";

		$stBusca = $pdo->prepare($sqlBusca);
		$stBusca->bindValue(':cliente', $cliente, PDO::PARAM_INT);
		$stBusca->bindValue(':item', $idItem, PDO::PARAM_INT);
		$stBusca->bindValue(':data', $dataVenda, PDO::PARAM_STR);
		$stBusca->execute();
		$idExistente = (int)($stBusca->fetchColumn() ?: 0);

		if($idExistente > 0){
			$sqlUpd = "UPDATE assinaturas SET
				cliente = :cliente,
				grupo = :grupo,
				item = :item,
				valor = :valor,
				pago = :pago,
				vencimento = :vencimento";
			if($temDataVencimento){
				$sqlUpd .= ", data_vencimento = :data_vencimento";
			}
			if($temFrequencia){
				$sqlUpd .= ", frequencia = :frequencia";
			}
			if($temAtivo){
				$sqlUpd .= ", ativo = 'Sim'";
			}
			$sqlUpd .= " WHERE id = :id";

			$stUpd = $pdo->prepare($sqlUpd);
			$stUpd->bindValue(':id', $idExistente, PDO::PARAM_INT);
			$stUpd->bindValue(':cliente', $cliente, PDO::PARAM_INT);
			$stUpd->bindValue(':grupo', $idGrupo, PDO::PARAM_INT);
			$stUpd->bindValue(':item', $idItem, PDO::PARAM_INT);
			$stUpd->bindValue(':valor', $valorTotal);
			$stUpd->bindValue(':pago', $pagoCombo, PDO::PARAM_STR);
			$stUpd->bindValue(':vencimento', $dataVencimento, PDO::PARAM_STR);
			if($temDataVencimento){
				$stUpd->bindValue(':data_vencimento', $dataVencimento, PDO::PARAM_STR);
			}
			if($temFrequencia){
				$stUpd->bindValue(':frequencia', $diasValidade, PDO::PARAM_INT);
			}
			return $stUpd->execute();
		}

		$campos = "cliente, data, pago, grupo, item, valor, vencimento";
		$valores = ":cliente, :data, :pago, :grupo, :item, :valor, :vencimento";

		if($temFrequencia){
			$campos .= ", frequencia";
			$valores .= ", :frequencia";
		}
		if($temDataVencimento){
			$campos .= ", data_vencimento";
			$valores .= ", :data_vencimento";
		}
		if($temAtivo){
			$campos .= ", ativo";
			$valores .= ", 'Sim'";
		}

		$sqlIns = "INSERT INTO assinaturas ({$campos}) VALUES ({$valores})";
		$stIns = $pdo->prepare($sqlIns);
		$stIns->bindValue(':cliente', $cliente, PDO::PARAM_INT);
		$stIns->bindValue(':data', $dataVenda, PDO::PARAM_STR);
		$stIns->bindValue(':pago', $pagoCombo, PDO::PARAM_STR);
		$stIns->bindValue(':grupo', $idGrupo, PDO::PARAM_INT);
		$stIns->bindValue(':item', $idItem, PDO::PARAM_INT);
		$stIns->bindValue(':valor', $valorTotal);
		$stIns->bindValue(':vencimento', $dataVencimento, PDO::PARAM_STR);
		if($temFrequencia){
			$stIns->bindValue(':frequencia', $diasValidade, PDO::PARAM_INT);
		}
		if($temDataVencimento){
			$stIns->bindValue(':data_vencimento', $dataVencimento, PDO::PARAM_STR);
		}

		return $stIns->execute();
	}
}
?>