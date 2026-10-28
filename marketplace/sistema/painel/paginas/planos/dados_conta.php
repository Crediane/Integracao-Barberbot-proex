<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';

header('Content-Type: application/json; charset=UTF-8');

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$id = $id ? (int)$id : 0;

if ($id <= 0) {
	echo json_encode([]);
	exit;
}

$stmt = $pdo->prepare("SELECT descricao, data_venc, pessoa FROM receber WHERE id = :id LIMIT 1");
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$res = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$res) {
	echo json_encode([]);
	exit;
}

$telefone = '';
$descricao = (string)$res['descricao'];
$data = (string)$res['data_venc'];

// Busca telefone do cliente (mantém regra original)
if (!empty($res['pessoa'])) {
	$id_cliente = (int)$res['pessoa'];
	$stmt2 = $pdo->prepare("SELECT telefone FROM clientes WHERE id = :id LIMIT 1");
	$stmt2->bindValue(':id', $id_cliente, PDO::PARAM_INT);
	$stmt2->execute();
	$r = $stmt2->fetch(PDO::FETCH_ASSOC);
	if ($r && isset($r['telefone'])) {
		$telefone = (string)$r['telefone'];
	}
}

echo json_encode([
	'telefone' => $telefone,
	'descricao' => $descricao,
	'data' => $data
]);
