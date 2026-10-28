<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';

/*
    /public_html/sistema/painel/paginas/funcionarios/mudar-status.php

    Mantém lógica do sistema:
    - campo 'ativo' recebe 'Sim' ou 'Não'
    - respostas: "ID inválido!", "Alterado com Sucesso", "Erro ao Alterar!"
*/

$tabela = 'usuarios';

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$acao = isset($_POST['acao']) ? (string)$_POST['acao'] : '';

if ($id <= 0) {
  echo 'ID inválido!';
  exit;
}

if ($acao !== 'Sim' && $acao !== 'Não') {
  echo 'Ação inválida!';
  exit;
}

try {

  // valida registro
  $stmt = $pdo->prepare("SELECT id FROM {$tabela} WHERE id = :id LIMIT 1");
  $stmt->bindValue(':id', $id, PDO::PARAM_INT);
  $stmt->execute();

  if (!$stmt->fetchColumn()) {
    echo 'ID inválido!';
    exit;
  }

  // atualiza status
  $stmt = $pdo->prepare("UPDATE {$tabela} SET ativo = :acao WHERE id = :id");
  $stmt->bindValue(':acao', $acao);
  $stmt->bindValue(':id', $id, PDO::PARAM_INT);
  $stmt->execute();

  echo 'Alterado com Sucesso';

} catch (Throwable $e) {
  echo 'Erro ao Alterar!';
}
?>