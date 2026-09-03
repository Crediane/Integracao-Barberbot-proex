<?php
require_once __DIR__ . '/../_context_bootstrap.php';
// ============================================================================
// BarberBot Software - Módulo Comissões - Baixar Comissão (Conta a Pagar)
// Arquivo: /public_html/sistema/painel/paginas/comissoes/baixar.php
// Ajustes: compatível com Admin do Sistema e Dono do Estabelecimento (bancos separados),
//          evita Warnings PHP 8+ (Undefined array key) e mantém a lógica original.
// Segurança: sanitização e prepared statements (PDO).
// Regra de negócio preservada:
//  - Verifica caixa aberto do operador
//  - Atualiza registro em pagar para pago='Sim', grava usuario_baixa, data_pgto, caixa e hora
// ============================================================================
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'pagar';

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
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
	echo 'ID inválido!';
	exit();
}

$valor = isset($_POST['valor']) ? (string)$_POST['valor'] : '0';
// normaliza valor: "1.234,56" -> "1234.56"
$valor = str_replace('.', '', $valor);
$valor = str_replace(',', '.', $valor);

$data_pgto = isset($_POST['data_pgto']) ? trim((string)$_POST['data_pgto']) : '';
$pgto      = isset($_POST['pgto']) ? trim((string)$_POST['pgto']) : '';

// Mantém o padrão: se não informar data, usa a data atual
if ($data_pgto === '') {
	$data_pgto = date('Y-m-d');
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
// Baixa a comissão (mantém lógica) - prepared statement
// ----------------------------------------------------------------------------
$stUp = $pdo->prepare("UPDATE {$tabela}
	SET pgto = :pgto,
		valor = :valor,
		pago = 'Sim',
		usuario_baixa = :usuario_baixa,
		data_pgto = :data_pgto,
		caixa = :caixa,
		hora = :hora_pgto
	WHERE id = :id");

$stUp->execute([
	':pgto' => $pgto,
	':valor' => $valor,
	':usuario_baixa' => $id_usuario,
	':data_pgto' => $data_pgto,
	':caixa' => $id_caixa,
	':hora_pgto' => date('H:i:s'),
	':id' => $id
]);

echo 'Baixado com Sucesso';
?>