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

$cliente = isset($_POST['cliente']) ? (int)$_POST['cliente'] : 0;
$servico = isset($_POST['servico']) ? (int)$_POST['servico'] : 0;

if($cliente <= 0 || $servico <= 0){
	echo '<input type="hidden" id="bb_combo_assinatura_id" value="">';
	echo '<small style="color:#7f8c8d;">Selecione um atendimento válido para consultar o combo.</small>';
	exit();
}

$stServico = $pdo->prepare("SELECT id, nome, combo_ativo, combo_qtd_sessoes, combo_validade_dias FROM servicos WHERE id = :id LIMIT 1");
$stServico->bindValue(':id', $servico, PDO::PARAM_INT);
$stServico->execute();
$dadosServico = $stServico->fetch(PDO::FETCH_ASSOC);

if(!$dadosServico){
	echo '<input type="hidden" id="bb_combo_assinatura_id" value="">';
	echo '<small class="text-danger">Serviço não encontrado para consultar combo.</small>';
	exit();
}

if((string)($dadosServico['combo_ativo'] ?? 'Não') !== 'Sim'){
	echo '<input type="hidden" id="bb_combo_assinatura_id" value="">';
	echo '<small style="color:#7f8c8d;">Este serviço não é um combo. O atendimento segue com cobrança normal.</small>';
	exit();
}

$resumo = resumoAssinaturaComboAtiva($pdo, $cliente, $servico);

if(!$resumo){
	echo '<input type="hidden" id="bb_combo_assinatura_id" value="">';
	echo '<div style="background:#fff7e6; border:1px solid #f0d9a6; padding:10px; border-radius:6px;">';
	echo '<div style="margin-bottom:4px;"><strong>Status do cliente:</strong> sem combo ativo para este serviço.</div>';
	echo '<div><strong>Ação recomendada:</strong> vender combo agora neste atendimento.</div>';
	echo '<small style="display:block; margin-top:6px; color:#8a6d3b;">Conclua com pagamento normal para registrar a venda do combo e baixar automaticamente a 1ª sessão.</small>';
	echo '</div>';
	exit();
}

$vencimento = '';
if (!empty($resumo['data_vencimento'])) {
	$vencimento = $resumo['data_vencimento'];
} elseif (!empty($resumo['vencimento'])) {
	$vencimento = $resumo['vencimento'];
}
$vencimentoF = '';
if($vencimento && $vencimento !== '0000-00-00'){
	$vencimentoF = implode('/', array_reverse(explode('-', $vencimento)));
}

$itemNome = !empty($resumo['item_nome']) ? $resumo['item_nome'] : (string)$dadosServico['nome'];

echo '<input type="hidden" id="bb_combo_assinatura_id" value="' . (int)$resumo['id'] . '">';
echo '<div style="background:#eef8ee; border:1px solid #cce5cc; padding:10px; border-radius:6px;">';
echo '<div style="margin-bottom:6px;"><strong>Status do cliente:</strong> combo ativo encontrado para este serviço.</div>';
echo '<div><strong>Pacote:</strong> ' . htmlspecialchars($itemNome, ENT_QUOTES, 'UTF-8') . '</div>';
echo '<div><strong>Assinatura:</strong> #' . (int)$resumo['id'] . '</div>';
echo '<div><strong>Sessões:</strong> ' . (int)$resumo['combo_qtd_sessoes'] . ' | <strong>Usadas:</strong> ' . (int)$resumo['usadas'] . ' | <strong>Restantes:</strong> ' . (int)$resumo['restantes'] . '</div>';
if($vencimentoF !== ''){
	echo '<div><strong>Vencimento:</strong> ' . htmlspecialchars($vencimentoF, ENT_QUOTES, 'UTF-8') . '</div>';
}
echo '<div style="margin-top:6px;"><strong>Ação recomendada:</strong> usar combo ativo do cliente.</div>';
echo '<small style="display:block; margin-top:6px; color:#4f81bd;">Ao escolher usar combo, o sistema baixa 1 sessão agora sem exigir forma de pagamento deste atendimento.</small>';
echo '</div>';
?>