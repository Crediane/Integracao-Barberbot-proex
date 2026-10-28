<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'usuarios';

$raw = (string)($_POST['id'] ?? '');
$raw = preg_replace('/[^0-9]/', '', $raw);
$id = (int)$raw;

$acao = (string)($_POST['acao'] ?? '');
$acao = trim($acao);

if ($id <= 0) {
  echo 'ID inválido';
  exit;
}
if ($acao !== 'Sim' && $acao !== 'Não') {
  echo 'Ação inválida';
  exit;
}

$up = $pdo->prepare("UPDATE {$tabela} SET ativo = :acao WHERE id = :id");
$up->execute([':acao' => $acao, ':id' => $id]);

echo 'Alterado com Sucesso';
