<?php
require_once __DIR__ . '/../_context_bootstrap.php';
// ============================================================================
// BarberBot Software - Módulo Comissões - Baixar Comissões (Lote)
// Arquivo: /public_html/sistema/painel/paginas/comissoes/baixar-todas.php
// Ajustes: compatível com Admin do Sistema e Dono do Estabelecimento (bancos separados),
//          evita Warnings PHP 8+ (Undefined array key) e mantém a lógica original.
// Segurança: sanitização e prepared statements (PDO).
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
// ENTRADAS (mantendo padrão)
// ----------------------------------------------------------------------------
$dataInicial = isset($_POST['data_inicial']) ? trim((string)$_POST['data_inicial']) : '';
$dataFinal   = isset($_POST['data_final']) ? trim((string)$_POST['data_final']) : '';
$funcionario = isset($_POST['id_funcionario']) ? (int)$_POST['id_funcionario'] : 0;
$pgto        = isset($_POST['pgto']) ? trim((string)$_POST['pgto']) : '';

// Defaults seguros (mesmo dia) se vierem vazios
if ($dataInicial === '') $dataInicial = date('Y-m-d');
if ($dataFinal === '')   $dataFinal   = date('Y-m-d');

// Se não tiver funcionário, não faz nada (mesmo comportamento da tela)
if ($funcionario <= 0) {
	echo 'Selecione um Funcionário';
	exit();
}

// ----------------------------------------------------------------------------
// CAIXA ABERTO (mantém lógica)
// ----------------------------------------------------------------------------
$id_caixa = 0;

$stCaixa = $pdo->prepare("SELECT id FROM caixas WHERE operador = :op AND data_fechamento IS NULL ORDER BY id DESC LIMIT 1");
$stCaixa->execute([':op' => $id_usuario]);
$resCaixa = $stCaixa->fetch(PDO::FETCH_ASSOC);

if ($resCaixa && isset($resCaixa['id'])) {
	$id_caixa = (int)$resCaixa['id'];
}

// ----------------------------------------------------------------------------
// BAIXA EM LOTE (mantém regra original)
// Observação: alguns bancos podem ter 'Nao/Comissao' (sem acento).
// Sem alterar a lógica, aceitamos as duas grafias para evitar falha de filtro.
// ----------------------------------------------------------------------------
$sql = "
	UPDATE {$tabela}
	   SET pago          = 'Sim',
		   usuario_baixa = :usuario_baixa,
		   data_pgto     = :data_pgto,
		   caixa         = :caixa,
		   hora          = :hora_pgto,
		   pgto          = :pgto
	 WHERE data_lanc BETWEEN :ini AND :fim
	   AND pago IN ('Não','Nao')
	   AND funcionario = :func
	   AND tipo IN ('Comissão','Comissao')
";

$stmt = $pdo->prepare($sql);

$stmt->bindValue(':usuario_baixa', $id_usuario, PDO::PARAM_INT);
$stmt->bindValue(':data_pgto', date('Y-m-d'), PDO::PARAM_STR);
$stmt->bindValue(':caixa', $id_caixa, PDO::PARAM_INT);
$stmt->bindValue(':hora_pgto', date('H:i:s'), PDO::PARAM_STR);
$stmt->bindValue(':pgto', $pgto);
$stmt->bindValue(':ini', $dataInicial);
$stmt->bindValue(':fim', $dataFinal);
$stmt->bindValue(':func', $funcionario, PDO::PARAM_INT);

try {
	$stmt->execute();
	echo 'Baixado com Sucesso';
} catch (Throwable $e) {
	http_response_code(500);
	echo 'Erro ao baixar comissões';
}
?>