<?php
require_once __DIR__ . '/../_context_bootstrap.php';
$tabela = 'duvidas';
require_once __DIR__ . '/_global_connection.php';

/* 73 SOMENTE DONO DO SISTEMA PODE SALVAR TUTORIAIS */
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

$titulo     = $_POST['titulo'] ?? '';
$descricao  = $_POST['descricao'] ?? '';
$link_video = $_POST['link_video'] ?? '';
$data       = date('Y-m-d');

if ($titulo == '' || $descricao == '') {
  echo "Preencha todos os campos obrigatrios.";
  exit();
}

try {
  $stmt = $pdoDuvidasGlobal->prepare("INSERT INTO tutoriais SET titulo = :titulo, descricao = :descricao, link_video = :link_video, data = :data");
  $stmt->bindParam(':titulo', $titulo);
  $stmt->bindParam(':descricao', $descricao);
  $stmt->bindParam(':link_video', $link_video);
  $stmt->bindParam(':data', $data);
  $stmt->execute();
  echo "Salvo com Sucesso";
} catch (Exception $e) {
  echo "Erro ao salvar: " . $e->getMessage();
}
