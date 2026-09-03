<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/_global_connection.php';

/* ✅ SOMENTE DONO DO SISTEMA PODE EXCLUIR TUTORIAIS */
$admin_email   = 'jacy@jc.tec.br';
$email_usuario = $_SESSION['email'] ?? '';

if ((string)session_name() !== 'BB_MASTER') {
  http_response_code(403);
  echo 'Acesso negado: esta ação pertence ao painel Master.';
  exit();
}

if (strtolower(trim((string)$email_usuario)) !== strtolower(trim((string)$admin_email))) {
  http_response_code(403);
  echo "Acesso negado.";
  exit();
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
  echo "ID inválido.";
  exit();
}

$stmt = $pdoDuvidasGlobal->prepare('DELETE FROM tutoriais WHERE id = :id');
$stmt->execute([':id' => $id]);
echo 'Excluído com Sucesso';
?>

