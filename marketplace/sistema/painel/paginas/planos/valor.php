<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';

$item = filter_input(INPUT_POST, 'item', FILTER_VALIDATE_INT);
$item = $item ? (int)$item : 0;

if ($item <= 0) {
	echo '0';
	exit;
}

$stmt = $pdo->prepare("SELECT valor FROM itens_assinaturas WHERE id = :id LIMIT 1");
$stmt->bindValue(':id', $item, PDO::PARAM_INT);
$stmt->execute();
$res = $stmt->fetch(PDO::FETCH_ASSOC);

echo $res ? $res['valor'] : '0';
