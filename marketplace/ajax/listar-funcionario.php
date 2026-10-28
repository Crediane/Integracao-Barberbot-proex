<?php
$bb_ajax_context_mode = 'tenant';
$bb_ajax_context_response = 'plain';
require_once __DIR__ . '/_context_bootstrap.php';

// =========================
// Entrada / Consulta
// =========================
$funcionario = filter_input(INPUT_POST, 'func', FILTER_VALIDATE_INT);
if (!$funcionario) {
  echo '';
  exit();
}

$stmt = $pdo->prepare("SELECT nome FROM usuarios WHERE id = :id LIMIT 1");
$stmt->bindValue(':id', $funcionario, PDO::PARAM_INT);
$stmt->execute();
$nome = $stmt->fetchColumn();

echo $nome ? $nome : '';
?>