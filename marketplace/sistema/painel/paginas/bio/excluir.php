<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';

$tabela = 'bio';
$id = intval($_POST['id'] ?? 0);
if($id<=0){ echo 'ID inválido'; exit; }

try{
  // apaga imagem se for local
  $q = $pdo->prepare("SELECT imagem FROM {$tabela} WHERE id=:id");
  $q->execute([':id'=>$id]);
  $img = $q->fetchColumn();
  if($img && $img[0] !== '/'){
    $fs = realpath(__DIR__ . '/../../../') . '/img/bio/'.$img;
    if(is_file($fs)) @unlink($fs);
  }

  $pdo->prepare("DELETE FROM {$tabela} WHERE id=:id")->execute([':id'=>$id]);
  echo "Excluído com Sucesso";
}catch(Throwable $e){
  echo "Erro: ".$e->getMessage();
}
