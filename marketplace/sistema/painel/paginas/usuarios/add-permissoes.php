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

$st = $pdo->query("SELECT id FROM acessos ORDER BY id ASC");
$perms = $st->fetchAll(PDO::FETCH_COLUMN);

if ($perms) {
  $ins = $pdo->prepare("INSERT INTO usuarios_permissoes (permissao, usuario) VALUES (:p, :u)");
  foreach ($perms as $pid) {
    $pid = (int)$pid;
    if ($pid > 0) {
      $ins->execute([':p' => $pid, ':u' => $id_usuario]);
    }
  }
}
