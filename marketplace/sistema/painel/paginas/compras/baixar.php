<?php
require_once __DIR__ . '/../_context_bootstrap.php';
/*
  Arquivo: /public_html/sistema/painel/paginas/compras/baixar.php

  Correção (PHP 8+ / Perfis diferentes):
  - Evita Warning "Undefined array key 'id'"
  - Compatível com sessão do dono do sistema e dono do estabelecimento
  - NÃO altera a regra de negócio: apenas faz a baixa marcando como paga e registrando usuário/caixa quando disponível

  Observação importante:
  Alguns ambientes armazenam o ID do usuário em chaves diferentes dentro da $_SESSION.
  Aqui buscamos o ID de forma segura (inclusive dentro de arrays), sem quebrar o fluxo.
*/
require_once __DIR__ . '/../../../conexao.php';

$tabela = 'pagar';

/* ===========================
   Funções auxiliares (seguras)
   =========================== */

/**
 * Busca recursiva por um ID numérico dentro da sessão.
 * - Prioriza chaves que contenham "id" e valores numéricos.
 * - Percorre arrays aninhados (até profundidade limitada para performance).
 */
function bb_buscar_id_sessao($arr, int $profundidade = 0): int
{
	if (!is_array($arr)) {
		return 0;
	}

	if ($profundidade > 3) {
		return 0;
	}

	// 1) Prioridade: chaves com "id"
	foreach ($arr as $k => $v) {
		if (is_scalar($v) && is_numeric($v)) {
			$kl = strtolower((string)$k);
			if ($kl === 'id' || strpos($kl, 'id_') !== false || strpos($kl, '_id') !== false || strpos($kl, 'usuario') !== false || strpos($kl, 'user') !== false) {
				$valor = (int)$v;
				if ($valor > 0) {
					return $valor;
				}
			}
		}
	}

	// 2) Busca dentro de arrays aninhados
	foreach ($arr as $v) {
		if (is_array($v)) {
			$achou = bb_buscar_id_sessao($v, $profundidade + 1);
			if ($achou > 0) {
				return $achou;
			}
		}
	}

	return 0;
}

/* ===========================
   Recuperar ID do usuário logado
   =========================== */
$id_usuario = 0;

// Mantém o padrão do sistema, mas cobre chaves alternativas
if (isset($_SESSION['id']) && is_numeric($_SESSION['id'])) {
	$id_usuario = (int)$_SESSION['id'];
} elseif (isset($_SESSION['id_usuario']) && is_numeric($_SESSION['id_usuario'])) {
	$id_usuario = (int)$_SESSION['id_usuario'];
} elseif (isset($_SESSION['usuario_id']) && is_numeric($_SESSION['usuario_id'])) {
	$id_usuario = (int)$_SESSION['usuario_id'];
} elseif (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
	$id_usuario = (int)$_SESSION['user_id'];
} else {
	// Busca genérica (inclusive dentro de arrays)
	$id_usuario = bb_buscar_id_sessao($_SESSION);
}

// IMPORTANTE: não bloqueia a baixa se o ID não for encontrado (mantém o comportamento original).
// Apenas deixa usuario_baixa = 0 e caixa = 0, evitando mensagem "sessão expirada".
if ($id_usuario < 0) {
	$id_usuario = 0;
}

/* ===========================
   Receber e sanitizar POST
   =========================== */
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

$valor = $_POST['valor'] ?? '0';
$valor = str_replace('.', '', $valor);
$valor = str_replace(',', '.', $valor);
$valor = is_numeric($valor) ? $valor : '0';

$data_pgto = $_POST['data_pgto'] ?? '';
$pgto = $_POST['pgto'] ?? '';

if ($id <= 0) {
	echo 'ID inválido!';
	exit();
}

/* ===========================
   Verificar caixa aberto (mesma regra)
   =========================== */
$id_caixa = 0;

if ($id_usuario > 0) {
	$stCx = $pdo->prepare("SELECT id FROM caixas WHERE operador = :op AND data_fechamento IS NULL ORDER BY id DESC LIMIT 1");
	$stCx->execute([':op' => $id_usuario]);
	$resCx = $stCx->fetch(PDO::FETCH_ASSOC);

	if ($resCx && isset($resCx['id'])) {
		$id_caixa = (int)$resCx['id'];
	}
}

/* ===========================
   Baixar conta (UPDATE) - mesma regra
   =========================== */
$stUp = $pdo->prepare("UPDATE $tabela 
	SET pgto = :pgto,
		valor = :valor,
		pago = 'Sim',
		usuario_baixa = :usuario_baixa,
		data_pgto = :data_pgto,
		caixa = :caixa,
		hora = curTime()
	WHERE id = :id");

$stUp->execute([
	':pgto' => $pgto,
	':valor' => $valor,
	':usuario_baixa' => $id_usuario,
	':data_pgto' => $data_pgto,
	':caixa' => $id_caixa,
	':id' => $id
]);

echo 'Baixado com Sucesso';
?>
