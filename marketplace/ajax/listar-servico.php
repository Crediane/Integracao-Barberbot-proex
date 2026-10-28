<?php
$bb_ajax_context_mode = 'tenant';
$bb_ajax_context_response = 'plain';
require_once __DIR__ . '/_context_bootstrap.php';

// =========================
// Entrada / Consulta
// =========================
$servico = filter_input(INPUT_POST, 'serv', FILTER_VALIDATE_INT);
if (!$servico) {
  echo '';
  exit();
}

$stmt = $pdo->prepare("SELECT nome FROM servicos WHERE id = :id LIMIT 1");
$stmt->bindValue(':id', $servico, PDO::PARAM_INT);
$stmt->execute();
$nome = $stmt->fetchColumn();

echo $nome ? $nome : '';
?>