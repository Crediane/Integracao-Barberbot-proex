<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'assinaturas';

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$id = $id ? (int)$id : 0;

$cliente = filter_input(INPUT_POST, 'cliente', FILTER_VALIDATE_INT);
$cliente = $cliente ? (int)$cliente : 0;

$item = filter_input(INPUT_POST, 'item', FILTER_VALIDATE_INT);
$item = $item ? (int)$item : 0;

$grupo = filter_input(INPUT_POST, 'grupo', FILTER_VALIDATE_INT);
$grupo = $grupo ? (int)$grupo : 0;

$valor = filter_input(INPUT_POST, 'valor', FILTER_SANITIZE_SPECIAL_CHARS);
$valor = trim((string)$valor);

$frequencia = filter_input(INPUT_POST, 'frequencia', FILTER_VALIDATE_INT);
$frequencia = $frequencia ? (int)$frequencia : 0;

$vencimento = filter_input(INPUT_POST, 'vencimento', FILTER_SANITIZE_SPECIAL_CHARS);
$vencimento = trim((string)$vencimento);

// Mantém a regra: se id vazio, insere; senão, atualiza
if ($id <= 0) {
	$stmt = $pdo->prepare("INSERT INTO $tabela (cliente, data, pago, grupo, item, valor, frequencia, vencimento)
		VALUES (:cliente, curDate(), 'Não', :grupo, :item, :valor, :frequencia, :vencimento)");
} else {
	$stmt = $pdo->prepare("UPDATE $tabela
		SET cliente = :cliente, grupo = :grupo, item = :item, valor = :valor, frequencia = :frequencia, vencimento = :vencimento
		WHERE id = :id");
	$stmt->bindValue(':id', $id, PDO::PARAM_INT);
}

$stmt->bindValue(':cliente', $cliente, PDO::PARAM_INT);
$stmt->bindValue(':grupo', $grupo, PDO::PARAM_INT);
$stmt->bindValue(':item', $item, PDO::PARAM_INT);
$stmt->bindValue(':valor', $valor);
$stmt->bindValue(':frequencia', $frequencia, PDO::PARAM_INT);
$stmt->bindValue(':vencimento', $vencimento);

$stmt->execute();

echo 'Salvo com Sucesso';
?>
