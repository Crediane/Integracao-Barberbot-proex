<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';

$id   = $_POST['id']   ?? '';
$ativo = $_POST['ativo'] ?? '';

if ($id === '' || ($ativo !== 'Sim' && $ativo !== 'Não')) {
  http_response_code(400);
  echo 'Parâmetros inválidos';
  exit;
}

try {
  $stmt = $pdo->prepare("UPDATE assinaturas SET ativo = :ativo WHERE id = :id");
  $stmt->bindValue(':ativo', $ativo);
  $stmt->bindValue(':id', $id, PDO::PARAM_INT);
  $stmt->execute();
  echo 'OK';
} catch (Exception $e) {
  http_response_code(500);
  echo 'Erro ao atualizar: ' . $e->getMessage();
}
