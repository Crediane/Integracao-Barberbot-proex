<?php
require_once __DIR__ . '/../_context_bootstrap.php';
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

header('Content-Type: application/json; charset=utf-8');

$id_agd = isset($_POST['id_agd']) ? (int)$_POST['id_agd'] : 0;
if($id_agd <= 0){
	echo json_encode(array('status' => 'erro', 'mensagem' => 'Agendamento inválido!'));
	exit();
}

try{
	$stAgd = $pdo->prepare("SELECT * FROM agendamentos WHERE id = :id LIMIT 1");
	$stAgd->bindValue(':id', $id_agd, PDO::PARAM_INT);
	$stAgd->execute();
	$agendamento = $stAgd->fetch(PDO::FETCH_ASSOC);

	if(!$agendamento){
		echo json_encode(array('status' => 'erro', 'mensagem' => 'Agendamento não encontrado!'));
		exit();
	}

	$servico = isset($agendamento['servico']) ? (int)$agendamento['servico'] : 0;
	$cliente = isset($agendamento['cliente']) ? (int)$agendamento['cliente'] : 0;
	$funcionario = isset($agendamento['funcionario']) ? (int)$agendamento['funcionario'] : 0;
	$data_agendamento = isset($agendamento['data']) ? (string)$agendamento['data'] : date('Y-m-d');

	$dadosServico = false;
	if($servico > 0){
		$stServico = $pdo->prepare("SELECT * FROM servicos WHERE id = :id LIMIT 1");
		$stServico->bindValue(':id', $servico, PDO::PARAM_INT);
		$stServico->execute();
		$dadosServico = $stServico->fetch(PDO::FETCH_ASSOC);
	}

	$nome_servico = 'Não Lançado';
	$valor_servico = '0,00';
	$combo_ativo = 'Não';
	$combo_qtd_sessoes = 0;
	$combo_validade_dias = 0;
	$resumo_html = '';
	$assinatura_combo_id = 0;

	if($dadosServico){
		$nome_servico = (string)($dadosServico['nome'] ?? 'Não Lançado');
		$valor_servico = number_format((float)($dadosServico['valor'] ?? 0), 2, ',', '.');
		$combo_ativo = (string)($dadosServico['combo_ativo'] ?? 'Não');
		$combo_qtd_sessoes = (int)($dadosServico['combo_qtd_sessoes'] ?? 0);
		$combo_validade_dias = (int)($dadosServico['combo_validade_dias'] ?? 0);

		if($combo_ativo === 'Sim'){
			$resumo = resumoAssinaturaComboAtiva($pdo, $cliente, $servico);
			if($resumo){
				$assinatura_combo_id = (int)$resumo['id'];
				$venc = trim((string)($resumo['vencimento'] ?? ''));
				$vencF = '';
				if($venc !== '' && $venc !== '0000-00-00'){
					$vencF = implode('/', array_reverse(explode('-', $venc)));
				}

				$resumo_html .= '<div style="background:#eef8ee; border:1px solid #cce5cc; padding:10px; border-radius:6px;">';
				$resumo_html .= '<div style="margin-bottom:6px;"><strong>Status do cliente:</strong> combo ativo encontrado para este serviço.</div>';
				$resumo_html .= '<div><strong>Pacote:</strong> ' . htmlspecialchars((string)($resumo['item_nome'] ?? ''), ENT_QUOTES, 'UTF-8') . '</div>';
				$resumo_html .= '<div><strong>Assinatura:</strong> #' . (int)$resumo['id'] . '</div>';
				$resumo_html .= '<div><strong>Sessões:</strong> ' . (int)($resumo['combo_qtd_sessoes'] ?? 0) . ' | <strong>Usadas:</strong> ' . (int)($resumo['usadas'] ?? 0) . ' | <strong>Restantes:</strong> ' . (int)($resumo['restantes'] ?? 0) . '</div>';
				if($vencF !== ''){
					$resumo_html .= '<div><strong>Vencimento:</strong> ' . $vencF . '</div>';
				}
				$resumo_html .= '<div style="margin-top:6px;"><strong>Ação recomendada:</strong> usar combo ativo do cliente.</div>';
				$resumo_html .= '<small style="display:block; margin-top:6px; color:#4f81bd;">Ao escolher usar combo, o sistema baixa 1 sessão agora sem exigir forma de pagamento deste atendimento.</small>';
				$resumo_html .= '</div>';
			}else{
				$resumo_html .= '<div style="background:#fff7e6; border:1px solid #f0d9a6; padding:10px; border-radius:6px;">';
				$resumo_html .= '<div style="margin-bottom:4px;"><strong>Status do cliente:</strong> sem combo ativo para este serviço.</div>';
				$resumo_html .= '<div><strong>Ação recomendada:</strong> vender combo agora neste atendimento.</div>';
				$resumo_html .= '<small style="display:block; margin-top:6px; color:#8a6d3b;">Conclua com pagamento normal para registrar a venda do combo e baixar automaticamente a 1ª sessão.</small>';
				$resumo_html .= '</div>';
			}
		}
	}

	echo json_encode(array(
		'status' => 'ok',
		'id_agd' => $id_agd,
		'cliente' => $cliente,
		'servico' => $servico,
		'funcionario' => $funcionario,
		'data_pgto' => $data_agendamento,
		'nome_servico' => $nome_servico,
		'valor_servico' => $valor_servico,
		'combo_ativo' => $combo_ativo,
		'combo_qtd_sessoes' => $combo_qtd_sessoes,
		'combo_validade_dias' => $combo_validade_dias,
		'assinatura_combo_id' => $assinatura_combo_id,
		'resumo_html' => $resumo_html
	), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){
	echo json_encode(array('status' => 'erro', 'mensagem' => 'Não foi possível carregar os dados do atendimento!'), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
