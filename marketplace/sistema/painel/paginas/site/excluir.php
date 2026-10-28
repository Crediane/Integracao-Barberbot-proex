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

if($id <= 0){
  echo "ID inválido"; exit;
}

/* ====== EXCLUIR BANNER ====== */
if($tipo === 'banner'){
  $tb = tblExists($pdo,'site_banners_data') ? 'site_banners_data'
      : (tblExists($pdo,'site_banners')     ? 'site_banners'     : 'arquivos');

  try{
    // busca o arquivo para deletar fisicamente
    $st = $pdo->prepare("SELECT arquivo FROM `$tb` WHERE id=:id");
    $st->execute([':id'=>$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    
    if($row && $row['arquivo']){
      $arquivo = $_SERVER['DOCUMENT_ROOT'].'/sistema/painel/'.$row['arquivo'];
      if(file_exists($arquivo) && is_file($arquivo)){
        @unlink($arquivo);
      }
    }

    // deleta do banco
    $st = $pdo->prepare("DELETE FROM `$tb` WHERE id=:id");
    $st->execute([':id'=>$id]);
    echo "Excluído com Sucesso";
  }catch(Exception $e){
    echo "Erro: ".$e->getMessage();
  }
  exit;
}

/* ====== EXCLUIR BLOCO ====== */
if($tipo === 'bloco'){
  $tb = tblExists($pdo,'home_blocos') ? 'home_blocos' : 'textos_index';

  $imgCol = colExists($pdo,$tb,'img') ? 'img' : (colExists($pdo,$tb,'imagem') ? 'imagem' : (colExists($pdo,$tb,'capa') ? 'capa' : null));
  $vidCol = colExists($pdo,$tb,'video') ? 'video' : (colExists($pdo,$tb,'video_url') ? 'video_url' : null);

  try{
    // busca os arquivos para deletar fisicamente
    $cols = ['id'];
    if($imgCol) $cols[] = $imgCol.' AS img';
    if($vidCol) $cols[] = $vidCol.' AS video';
    
    $st = $pdo->prepare("SELECT ".implode(',',$cols)." FROM `$tb` WHERE id=:id");
    $st->execute([':id'=>$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    
    if($row){
      // deleta imagem
      if(isset($row['img']) && $row['img']){
        $arquivo = $_SERVER['DOCUMENT_ROOT'].'/sistema/painel/'.$row['img'];
        if(file_exists($arquivo) && is_file($arquivo)){
          @unlink($arquivo);
        }
      }
      // deleta vídeo
      if(isset($row['video']) && $row['video']){
        $arquivo = $_SERVER['DOCUMENT_ROOT'].'/sistema/painel/'.$row['video'];
        if(file_exists($arquivo) && is_file($arquivo) && !filter_var($row['video'], FILTER_VALIDATE_URL)){
          @unlink($arquivo);
        }
      }
    }

    // deleta do banco
    $st = $pdo->prepare("DELETE FROM `$tb` WHERE id=:id");
    $st->execute([':id'=>$id]);
    echo "Excluído com Sucesso";
  }catch(Exception $e){
    echo "Erro: ".$e->getMessage();
  }
  exit;
}

/* ====== EXCLUIR CARROSSEL ====== */
if($tipo === 'carrossel'){
  try{
    // busca a thumbnail para deletar fisicamente
    $st = $pdo->prepare("SELECT thumb FROM site_carrossel WHERE id=:id");
    $st->execute([':id'=>$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    
    if($row && $row['thumb']){
      $arquivo = $_SERVER['DOCUMENT_ROOT'].'/sistema/painel/'.$row['thumb'];
      if(file_exists($arquivo) && is_file($arquivo)){
        @unlink($arquivo);
      }
    }

    // deleta do banco
    $st = $pdo->prepare("DELETE FROM site_carrossel WHERE id=:id");
    $st->execute([':id'=>$id]);
    echo "Excluído com Sucesso";
  }catch(Exception $e){
    echo "Erro: ".$e->getMessage();
  }
  exit;
}

/* ====== EXCLUIR CATEGORIA ====== */
if($tipo === 'categoria'){
  try{
    // busca o ícone para deletar fisicamente
    $st = $pdo->prepare("SELECT icone FROM categorias WHERE id=:id");
    $st->execute([':id'=>$id]);
    $row = $st->fetch(PDO::FETCH_ASSOC);
    
    if($row && $row['icone']){
      $arquivo = $_SERVER['DOCUMENT_ROOT'].'/sistema/painel/'.$row['icone'];
      if(file_exists($arquivo) && is_file($arquivo)){
        @unlink($arquivo);
      }
    }

    // deleta do banco
    $st = $pdo->prepare("DELETE FROM categorias WHERE id=:id");
    $st->execute([':id'=>$id]);
    echo "Excluído com Sucesso";
  }catch(Exception $e){
    echo "Erro: ".$e->getMessage();
  }
  exit;
}

echo "Tipo inválido";