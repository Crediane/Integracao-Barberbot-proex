<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$id = $_POST['id'] ?? 0;

$query = $pdo->prepare("SELECT * FROM faq WHERE id = :id LIMIT 1");
$query->bindValue(":id", $id, PDO::PARAM_INT);
$query->execute();

echo json_encode($query->fetch(PDO::FETCH_ASSOC));
