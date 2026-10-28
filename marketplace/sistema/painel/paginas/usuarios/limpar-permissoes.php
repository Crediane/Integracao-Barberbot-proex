<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';

$raw = (string)($_POST['id'] ?? '');
$raw = preg_replace('/[^0-9]/', '', $raw);
$id_usuario = (int)$raw;

if ($id_usuario <= 0) {
  exit;
}

$pdo->prepare("DELETE FROM usuarios_permissoes WHERE usuario = :u")->execute([':u' => $id_usuario]);
