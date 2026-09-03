<?php
require_once __DIR__ . '/../_context_bootstrap.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../../conexao.php';

/* ===== helpers ===== */
function tblExists(PDO $pdo,$name){
  $st=$pdo->prepare("SHOW TABLES LIKE :t"); $st->execute([':t'=>$name]);
  return (bool)$st->fetch(PDO::FETCH_NUM);
}
function colExists(PDO $pdo,$table,$col){
  try{
    $st=$pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :c");
    $st->execute([':c'=>$col]);
    return (bool)$st->fetch(PDO::FETCH_ASSOC);
  }catch(Throwable $e){ return false; }
}

$tipo = $_POST['tipo'] ?? '';
$id = (int)($_POST['id'] ?? 0);
$valor = (int)($_POST['valor'] ?? 0);

if($id <= 0){
  echo "ID inválido"; exit;
}

/* ====== MUDAR BANNER ====== */
if($tipo === 'banner'){
  $tb = tblExists($pdo,'site_banners_data') ? 'site_banners_data'
      : (tblExists($pdo,'site_banners')     ? 'site_banners'     : 'arquivos');

  if(!colExists($pdo,$tb,'ativo')){
    echo "Coluna 'ativo' não existe"; exit;
  }

  try{
    $st = $pdo->prepare("UPDATE `$tb` SET ativo=:valor WHERE id=:id");
    $st->execute([':valor'=>$valor, ':id'=>$id]);
    echo "Alterado com Sucesso";
  }catch(Exception $e){
    echo "Erro: ".$e->getMessage();
  }
  exit;
}

/* ====== MUDAR BLOCO ====== */
if($tipo === 'bloco'){
  $tb = tblExists($pdo,'home_blocos') ? 'home_blocos' : 'textos_index';

  $statCol = colExists($pdo,$tb,'ativo') ? 'ativo' : (colExists($pdo,$tb,'status') ? 'status' : null);

  if(!$statCol){
    echo "Coluna de status não existe"; exit;
  }

  try{
    $st = $pdo->prepare("UPDATE `$tb` SET $statCol=:valor WHERE id=:id");
    $st->execute([':valor'=>$valor, ':id'=>$id]);
    echo "Alterado com Sucesso";
  }catch(Exception $e){
    echo "Erro: ".$e->getMessage();
  }
  exit;
}

/* ====== MUDAR CARROSSEL ====== */
if($tipo === 'carrossel'){
  try{
    $st = $pdo->prepare("UPDATE site_carrossel SET ativo=:valor WHERE id=:id");
    $st->execute([':valor'=>$valor, ':id'=>$id]);
    echo "Alterado com Sucesso";
  }catch(Exception $e){
    echo "Erro: ".$e->getMessage();
  }
  exit;
}

/* ====== MUDAR CATEGORIA ====== */
if($tipo === 'categoria'){
  try{
    $st = $pdo->prepare("UPDATE categorias SET ativo=:valor WHERE id=:id");
    $st->execute([':valor'=>$valor, ':id'=>$id]);
    echo "Alterado com Sucesso";
  }catch(Exception $e){
    echo "Erro: ".$e->getMessage();
  }
  exit;
}

echo "Tipo inválido";