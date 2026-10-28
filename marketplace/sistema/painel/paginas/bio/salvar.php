<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';

$tabela = 'bio';

$id         = intval($_POST['id'] ?? 0);
$nome       = trim($_POST['nome'] ?? '');
$chave      = trim($_POST['chave'] ?? '');
$descricao  = trim($_POST['descricao'] ?? '');
$icone      = trim($_POST['icone'] ?? '');
$grupo      = intval($_POST['grupo'] ?? 0);
$ordem      = intval($_POST['ordem'] ?? 0);
$ativo      = intval($_POST['ativo'] ?? 1);
$img_antiga = trim($_POST['imagem_antiga'] ?? '');

if($nome===''){
  echo 'Informe o título.';
  exit;
}

/* upload */
$dirFs  = realpath(__DIR__ . '/../../../') . '/img/bio';
$dirUrl = '/sistema/img/bio';
if(!is_dir($dirFs)) @mkdir($dirFs, 0775, true);

$img_final = $img_antiga;
if(!empty($_FILES['imagem']['name'])){
  $ext = strtolower(pathinfo($_FILES['imagem']['name'], PATHINFO_EXTENSION));
  $permit = ['jpg','jpeg','png','webp'];
  if(!in_array($ext, $permit)){ echo 'Extensão de imagem inválida!'; exit; }
  if($_FILES['imagem']['size'] > 4*1024*1024){ echo 'Imagem maior que 4MB!'; exit; }

  $nome_arq = 'bio-'.date('Ymd-His').'-'.rand(100,999).'.'.$ext;
  $fs = rtrim($dirFs,'/').'/'.$nome_arq;
  if(move_uploaded_file($_FILES['imagem']['tmp_name'], $fs)){
    $img_final = $nome_arq;
    // remove antiga se for arquivo local e diferente
    if($img_antiga && $img_antiga[0]!=='/'){
      $old = rtrim($dirFs,'/').'/'.$img_antiga;
      if(is_file($old)) @unlink($old);
    }
  }
}

try{
  if($id > 0){
    $sql = "UPDATE {$tabela} SET
              nome=:n, chave=:c, descricao=:d, icone=:i, grupo=:g, ordem=:o, ativo=:a, imagem=:m
            WHERE id=:id";
    $st = $pdo->prepare($sql);
    $st->execute([
      ':n'=>$nome, ':c'=>$chave, ':d'=>$descricao, ':i'=>$icone,
      ':g'=>$grupo ?: null, ':o'=>$ordem, ':a'=>$ativo, ':m'=>$img_final, ':id'=>$id
    ]);
  }else{
    $sql = "INSERT INTO {$tabela}
              (nome, chave, descricao, icone, grupo, ordem, ativo, imagem, created_at)
            VALUES
              (:n,:c,:d,:i,:g,:o,:a,:m, :created_at)";
    $st = $pdo->prepare($sql);
    $st->execute([
      ':n'=>$nome, ':c'=>$chave, ':d'=>$descricao, ':i'=>$icone,
      ':g'=>$grupo ?: null, ':o'=>$ordem, ':a'=>$ativo, ':m'=>$img_final,
      ':created_at'=>date('Y-m-d H:i:s')
    ]);
  }
  echo "Salvo com Sucesso";
}catch(Throwable $e){
  echo "Erro: ".$e->getMessage();
}
