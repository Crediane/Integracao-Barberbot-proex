<?php
require_once __DIR__ . '/../_context_bootstrap.php';
/*
  Arquivo: paginas/receber/gerar_cobranca.php

  Correção:
  - Mantém a lógica do sistema.
  - Link multi-tenant correto:
      loja  => /loja/<slug>/conta/<id>
      master => /conta/<id>
  - Busca o nome do cliente pela tabela receber -> campo pessoa -> tabela clientes.
  - Não usa rawurlencode nem %0A.
  - Mantém envio pelo api-texto.php.
*/

$tabela = 'receber';
require_once __DIR__ . '/../../../conexao.php';

$data_atual = date('Y-m-d');

// ===== Receber dados do POST =====
$id_conta  = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$valor_in  = $_POST['valor'] ?? '';
$data      = $_POST['data'] ?? '';
$telefone  = $_POST['telefone'] ?? '';
$descricao = $_POST['descricao'] ?? '';

// ===== Validações mínimas =====
if ($id_conta <= 0) {
	echo 'ID da conta inválido!';
	exit();
}

if (trim($telefone) === '') {
	echo 'Telefone inválido!';
	exit();
}

if (trim($data) === '') {
	echo 'Data inválida!';
	exit();
}

if (trim($descricao) === '') {
	echo 'Descrição inválida!';
	exit();
}

// ===== Sanitizar telefone: manter padrão 55 + DDD + número =====
$tel_digitos = preg_replace('/\D+/', '', (string)$telefone);
if ($tel_digitos === '') {
	echo 'Telefone inválido!';
	exit();
}

if (strpos($tel_digitos, '55') !== 0) {
	$telefone = '55' . $tel_digitos;
} else {
	$telefone = $tel_digitos;
}

// ===== Normalizar valor =====
$valor_calc = $valor_in;
if (!is_numeric($valor_calc)) {
	$valor_calc = str_replace('.', '', (string)$valor_calc);
	$valor_calc = str_replace(',', '.', (string)$valor_calc);
}
$valor_calc = (float)$valor_calc;

// ===== Formatações =====
$valorF = number_format($valor_calc, 2, '.', '');
$valorF = number_format($valor_calc, 2, ',', '.');

if (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$data)) {
	$dataF = implode('/', array_reverse(explode('-', (string)$data)));
} else {
	$dataF = (string)$data;
}

// ===== Definir título conforme vencimento =====
if (strtotime((string)$data) < strtotime($data_atual)) {
	$titulo_mensagem = 'Sua Parcela Venceu!';
} else {
	$titulo_mensagem = 'Lembrete de Pagamento!';
}

// ===== Buscar nome do cliente pela conta (receber.pessoa -> clientes.nome) =====
$nome_cliente = 'Cliente';

try {
	$stmtConta = $pdo->prepare("SELECT pessoa FROM $tabela WHERE id = :id LIMIT 1");
	$stmtConta->bindValue(':id', $id_conta, PDO::PARAM_INT);
	$stmtConta->execute();
	$dadosConta = $stmtConta->fetch(PDO::FETCH_ASSOC);

	$id_pessoa = isset($dadosConta['pessoa']) ? (int)$dadosConta['pessoa'] : 0;

	if ($id_pessoa > 0) {
		$stmtCliente = $pdo->prepare("SELECT nome FROM clientes WHERE id = :id LIMIT 1");
		$stmtCliente->bindValue(':id', $id_pessoa, PDO::PARAM_INT);
		$stmtCliente->execute();
		$dadosCliente = $stmtCliente->fetch(PDO::FETCH_ASSOC);

		if (!empty($dadosCliente['nome'])) {
			$nome_cliente = trim((string)$dadosCliente['nome']);
		}
	}
} catch (Exception $e) {
	$nome_cliente = 'Cliente';
}

// ===== Link de pagamento (corrigido multi-tenant) =====
// Regra:
// - Se estiver em /loja/<slug>/... => link deve carregar o banco da loja: /loja/<slug>/conta/<id>
// - Se estiver no MASTER (/sistema) => mantém: /conta/<id>
$link_base = isset($url_sistema) ? (string)$url_sistema : '';
$link_base = rtrim($link_base, '/') . '/';

// Capturar o slug da loja pela URL (preferência) ou por parâmetro (fallback)
$slug_loja = '';
if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath, $__m)) {
	$slug_loja = $__m[1];
} elseif (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_refPath, $__m)) {
	$slug_loja = $__m[1];
} elseif (!empty($_GET['loja'])) {
	$slug_loja = (string)$_GET['loja'];
} elseif (!empty($_POST['loja'])) {
	$slug_loja = (string)$_POST['loja'];
}

// Sanitiza slug
$slug_loja = preg_replace('/[^a-z0-9_-]/i', '', (string)$slug_loja);

if ($slug_loja !== '') {
	$partes = parse_url($link_base);

	$scheme = $partes['scheme'] ?? 'https';
	$host   = $partes['host'] ?? ($_SERVER['HTTP_HOST'] ?? '');
	$port   = isset($partes['port']) ? ':' . (int)$partes['port'] : '';

	$link_base = $scheme . '://' . $host . $port . '/loja/' . $slug_loja . '/';
}

$link_pgto = $link_base . 'conta/' . $id_conta;

// ===== Montagem da mensagem =====
/*
  IMPORTANTE:
  - Não usar rawurlencode e nem %0A aqui.
  - A quebra de linha deve ser \n para o WhatsApp exibir corretamente.
*/
$mensagem  = '💰 Olá *' . $nome_cliente . "*\n";
$mensagem .= '_' . $titulo_mensagem . "_\n";
$mensagem .= 'Conta: ' . $descricao . "\n";
$mensagem .= 'Valor: *R$ ' . $valorF . "*\n";
$mensagem .= 'Vencimento: *' . $dataF . "*\n\n";
$mensagem .= '*⬇️ Clique para pagar sua assinatura ⬇️*' . "\n";
$mensagem .= $link_pgto;

// ===== Envio via API (mantido) =====
require __DIR__ . '/../../../../ajax/api-texto.php';
?>