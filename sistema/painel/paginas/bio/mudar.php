<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';

$id    = (int)($_POST['id']    ?? 0);
$valor = (int)($_POST['valor'] ?? 0);

if($id<=0){ echo 'ID inválido'; exit; }

try{
  $st = $pdo->prepare("UPDATE bio SET ativo=:v WHERE id=:id");
  $st->execute([':v'=>$valor, ':id'=>$id]);
  echo "Alterado com Sucesso";
}catch(Throwable $e){
  echo "Erro: ".$e->getMessage();
}
