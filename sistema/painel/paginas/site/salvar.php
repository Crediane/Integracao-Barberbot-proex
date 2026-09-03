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

/* ====== SALVAR BANNER ====== */
if($tipo === 'banner'){
  $tb = tblExists($pdo,'site_banners_data') ? 'site_banners_data'
      : (tblExists($pdo,'site_banners')     ? 'site_banners'     : 'arquivos');

  $nome = $_POST['nome'] ?? '';
  $subtitulo = $_POST['subtitulo'] ?? '';
  $descricao = $_POST['descricao'] ?? '';
  $ordem = (int)($_POST['ordem'] ?? 0);
  $ativo = (int)($_POST['ativo'] ?? 1);
  $registro = $_POST['registro'] ?? 'banner_home';
  $arquivo_url = $_POST['arquivo_url'] ?? '';

  // upload de arquivo
  $arquivo = '';
  if(isset($_FILES['arquivo']) && $_FILES['arquivo']['error'] === UPLOAD_ERR_OK){
    $ext = strtolower(pathinfo($_FILES['arquivo']['name'], PATHINFO_EXTENSION));
    $permitidos = ['jpg','jpeg','png','webp','gif','mp4','webm','ogg'];
    if(!in_array($ext, $permitidos)){
      echo "Formato de arquivo não permitido"; exit;
    }
    $nome_arquivo = uniqid().'_'.time().'.'.$ext;
    $pasta_upload = $_SERVER['DOCUMENT_ROOT'].'/sistema/painel/img/banners/';
    if(!is_dir($pasta_upload)){ mkdir($pasta_upload, 0755, true); }
    $caminho = $pasta_upload.$nome_arquivo;
    if(move_uploaded_file($_FILES['arquivo']['tmp_name'], $caminho)){
      $arquivo = 'img/banners/'.$nome_arquivo;
    }
  }elseif($arquivo_url){
    $arquivo = $arquivo_url;
  }

  try{
    if($id > 0){
      // EDITAR
      $sql = "UPDATE `$tb` SET nome=:nome";
      $params = [':nome'=>$nome, ':id'=>$id];
      
      if(colExists($pdo,$tb,'subtitulo')){ $sql.=", subtitulo=:sub"; $params[':sub']=$subtitulo; }
      if(colExists($pdo,$tb,'descricao')){ $sql.=", descricao=:desc"; $params[':desc']=$descricao; }
      elseif(colExists($pdo,$tb,'texto')){ $sql.=", texto=:desc"; $params[':desc']=$descricao; }
      
      if($arquivo){
        $sql.=", arquivo=:arq"; $params[':arq']=$arquivo;
      }
      if(colExists($pdo,$tb,'ordem')){ $sql.=", ordem=:ord"; $params[':ord']=$ordem; }
      if(colExists($pdo,$tb,'ativo')){ $sql.=", ativo=:ativ"; $params[':ativ']=$ativo; }
      if(colExists($pdo,$tb,'registro')){ $sql.=", registro=:reg"; $params[':reg']=$registro; }
      
      $sql.=" WHERE id=:id";
      $st = $pdo->prepare($sql);
      $st->execute($params);
    }else{
      // INSERIR
      $cols = ['nome'];
      $vals = [':nome'];
      $params = [':nome'=>$nome];

      if(colExists($pdo,$tb,'subtitulo')){ $cols[]='subtitulo'; $vals[]=':sub'; $params[':sub']=$subtitulo; }
      if(colExists($pdo,$tb,'descricao')){ $cols[]='descricao'; $vals[]=':desc'; $params[':desc']=$descricao; }
      elseif(colExists($pdo,$tb,'texto')){ $cols[]='texto'; $vals[]=':desc'; $params[':desc']=$descricao; }
      
      if($arquivo){ $cols[]='arquivo'; $vals[]=':arq'; $params[':arq']=$arquivo; }
      if(colExists($pdo,$tb,'ordem')){ $cols[]='ordem'; $vals[]=':ord'; $params[':ord']=$ordem; }
      if(colExists($pdo,$tb,'ativo')){ $cols[]='ativo'; $vals[]=':ativ'; $params[':ativ']=$ativo; }
      if(colExists($pdo,$tb,'registro')){ $cols[]='registro'; $vals[]=':reg'; $params[':reg']=$registro; }

      $sql = "INSERT INTO `$tb` (".implode(',',$cols).") VALUES (".implode(',',$vals).")";
      $st = $pdo->prepare($sql);
      $st->execute($params);
    }
    echo "Salvo com Sucesso";
  }catch(Exception $e){
    echo "Erro: ".$e->getMessage();
  }
  exit;
}

/* ====== SALVAR BLOCO ====== */
if($tipo === 'bloco'){
  $tb = tblExists($pdo,'home_blocos') ? 'home_blocos' : 'textos_index';

  $slug = $_POST['slug'] ?? '';
  $titulo = $_POST['titulo'] ?? '';
  $subtitulo = $_POST['subtitulo'] ?? '';
  $texto = $_POST['texto'] ?? '';
  $status = (int)($_POST['status'] ?? 1);
  $video_url = $_POST['video_url'] ?? '';

  // upload de imagem
  $img = '';
  if(isset($_FILES['img']) && $_FILES['img']['error'] === UPLOAD_ERR_OK){
    $ext = strtolower(pathinfo($_FILES['img']['name'], PATHINFO_EXTENSION));
    $permitidos = ['jpg','jpeg','png','webp','gif'];
    if(!in_array($ext, $permitidos)){
      echo "Formato de imagem não permitido"; exit;
    }
    $nome_img = uniqid().'_'.time().'.'.$ext;
    $pasta_upload = $_SERVER['DOCUMENT_ROOT'].'/sistema/painel/img/blocos/';
    if(!is_dir($pasta_upload)){ mkdir($pasta_upload, 0755, true); }
    $caminho = $pasta_upload.$nome_img;
    if(move_uploaded_file($_FILES['img']['tmp_name'], $caminho)){
      $img = 'img/blocos/'.$nome_img;
    }
  }

  // upload de vídeo
  $video = $video_url;
  if(isset($_FILES['video']) && $_FILES['video']['error'] === UPLOAD_ERR_OK){
    $ext = strtolower(pathinfo($_FILES['video']['name'], PATHINFO_EXTENSION));
    $permitidos = ['mp4','webm','ogg','avi','mov'];
    if(!in_array($ext, $permitidos)){
      echo "Formato de vídeo não permitido"; exit;
    }
    $nome_video = uniqid().'_'.time().'.'.$ext;
    $pasta_upload = $_SERVER['DOCUMENT_ROOT'].'/sistema/painel/img/blocos/';
    if(!is_dir($pasta_upload)){ mkdir($pasta_upload, 0755, true); }
    $caminho = $pasta_upload.$nome_video;
    if(move_uploaded_file($_FILES['video']['tmp_name'], $caminho)){
      $video = 'img/blocos/'.$nome_video;
    }
  }

  // detecta as colunas disponíveis
  $slugCol = colExists($pdo,$tb,'categoria') ? 'categoria' : (colExists($pdo,$tb,'slug') ? 'slug' : null);
  $titCol = colExists($pdo,$tb,'titulo') ? 'titulo' : (colExists($pdo,$tb,'nome') ? 'nome' : null);
  $subtCol = colExists($pdo,$tb,'subtitulo') ? 'subtitulo' : (colExists($pdo,$tb,'sub_titulo') ? 'sub_titulo' : null);
  $txtCol = colExists($pdo,$tb,'texto') ? 'texto' : (colExists($pdo,$tb,'descricao') ? 'descricao' : (colExists($pdo,$tb,'conteudo') ? 'conteudo' : null));
  $imgCol = colExists($pdo,$tb,'img') ? 'img' : (colExists($pdo,$tb,'imagem') ? 'imagem' : (colExists($pdo,$tb,'capa') ? 'capa' : null));
  $vidCol = colExists($pdo,$tb,'video') ? 'video' : (colExists($pdo,$tb,'video_url') ? 'video_url' : null);
  $statCol = colExists($pdo,$tb,'ativo') ? 'ativo' : (colExists($pdo,$tb,'status') ? 'status' : null);

  try{
    if($id > 0){
      // EDITAR
      $sql = "UPDATE `$tb` SET ";
      $updates = [];
      $params = [':id'=>$id];

      if($slugCol){ $updates[]="$slugCol=:slug"; $params[':slug']=$slug; }
      if($titCol){ $updates[]="$titCol=:tit"; $params[':tit']=$titulo; }
      if($subtCol){ $updates[]="$subtCol=:sub"; $params[':sub']=$subtitulo; }
      if($txtCol){ $updates[]="$txtCol=:txt"; $params[':txt']=$texto; }
      if($img && $imgCol){ $updates[]="$imgCol=:img"; $params[':img']=$img; }
      if($video && $vidCol){ $updates[]="$vidCol=:vid"; $params[':vid']=$video; }
      if($statCol){ $updates[]="$statCol=:stat"; $params[':stat']=$status; }

      $sql .= implode(', ', $updates)." WHERE id=:id";
      $st = $pdo->prepare($sql);
      $st->execute($params);
    }else{
      // INSERIR
      $cols = [];
      $vals = [];
      $params = [];

      if($slugCol){ $cols[]=$slugCol; $vals[]=':slug'; $params[':slug']=$slug; }
      if($titCol){ $cols[]=$titCol; $vals[]=':tit'; $params[':tit']=$titulo; }
      if($subtCol){ $cols[]=$subtCol; $vals[]=':sub'; $params[':sub']=$subtitulo; }
      if($txtCol){ $cols[]=$txtCol; $vals[]=':txt'; $params[':txt']=$texto; }
      if($img && $imgCol){ $cols[]=$imgCol; $vals[]=':img'; $params[':img']=$img; }
      if($video && $vidCol){ $cols[]=$vidCol; $vals[]=':vid'; $params[':vid']=$video; }
      if($statCol){ $cols[]=$statCol; $vals[]=':stat'; $params[':stat']=$status; }

      $sql = "INSERT INTO `$tb` (".implode(',',$cols).") VALUES (".implode(',',$vals).")";
      $st = $pdo->prepare($sql);
      $st->execute($params);
    }
    echo "Salvo com Sucesso";
  }catch(Exception $e){
    echo "Erro: ".$e->getMessage();
  }
  exit;
}

/* ====== SALVAR CARROSSEL ====== */
if($tipo === 'carrossel'){
  // cria a tabela se não existir
  $pdo->exec("CREATE TABLE IF NOT EXISTS site_carrossel (
    id INT AUTO_INCREMENT PRIMARY KEY,
    titulo VARCHAR(120) NOT NULL,
    subtitulo VARCHAR(255) NULL,
    video_url VARCHAR(255) NULL,
    thumb VARCHAR(255) NULL,
    ordem INT DEFAULT 0,
    ativo TINYINT(1) DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  $titulo = $_POST['titulo'] ?? '';
  $subtitulo = $_POST['subtitulo'] ?? '';
  $video_url = $_POST['video_url'] ?? '';
  $ordem = (int)($_POST['ordem'] ?? 0);
  $ativo = (int)($_POST['ativo'] ?? 1);

  // extrair ID do youtube se for URL completa
  if(preg_match('/(?:youtube\.com\/watch\?v=|youtu\.be\/)([a-zA-Z0-9_-]+)/', $video_url, $m)){
    $video_url = $m[1];
  }

  // upload de thumbnail
  $thumb = '';
  if(isset($_FILES['thumb']) && $_FILES['thumb']['error'] === UPLOAD_ERR_OK){
    $ext = strtolower(pathinfo($_FILES['thumb']['name'], PATHINFO_EXTENSION));
    $permitidos = ['jpg','jpeg','png','webp'];
    if(!in_array($ext, $permitidos)){
      echo "Formato de imagem não permitido"; exit;
    }
    $nome_thumb = uniqid().'_'.time().'.'.$ext;
    $pasta_upload = $_SERVER['DOCUMENT_ROOT'].'/sistema/painel/img/carrossel/';
    if(!is_dir($pasta_upload)){ mkdir($pasta_upload, 0755, true); }
    $caminho = $pasta_upload+$nome_thumb;
    $caminho = $pasta_upload.$nome_thumb;
    if(move_uploaded_file($_FILES['thumb']['tmp_name'], $caminho)){
      $thumb = 'img/carrossel/'.$nome_thumb;
    }
  }

  try{
    if($id > 0){
      // EDITAR
      $sql = "UPDATE site_carrossel SET titulo=:tit, subtitulo=:sub, video_url=:vid, ordem=:ord, ativo=:ativ";
      $params = [':tit'=>$titulo, ':sub'=>$subtitulo, ':vid'=>$video_url, ':ord'=>$ordem, ':ativ'=>$ativo, ':id'=>$id];
      
      if($thumb){ $sql.=", thumb=:thumb"; $params[':thumb']=$thumb; }
      
      $sql.=" WHERE id=:id";
      $st = $pdo->prepare($sql);
      $st->execute($params);
    }else{
      // INSERIR
      $cols = ['titulo','subtitulo','video_url','ordem','ativo'];
      $vals = [':tit',':sub',':vid',':ord',':ativ'];
      $params = [':tit'=>$titulo, ':sub'=>$subtitulo, ':vid'=>$video_url, ':ord'=>$ordem, ':ativ'=>$ativo];

      if($thumb){ $cols[]='thumb'; $vals[]=':thumb'; $params[':thumb']=$thumb; }

      $sql = "INSERT INTO site_carrossel (".implode(',',$cols).") VALUES (".implode(',',$vals).")";
      $st = $pdo->prepare($sql);
      $st->execute($params);
    }
    echo "Salvo com Sucesso";
  }catch(Exception $e){
    echo "Erro: ".$e->getMessage();
  }
  exit;
}

/* ====== SALVAR CATEGORIA ====== */
if($tipo === 'categoria'){
  // cria a tabela se não existir (usa categoria_site para não conflitar com 'categorias')
  $pdo->exec("CREATE TABLE IF NOT EXISTS categoria_site (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    slug VARCHAR(100) NOT NULL UNIQUE,
    descricao TEXT NULL,
    icone VARCHAR(255) NULL,
    ordem INT DEFAULT 0,
    ativo TINYINT(1) DEFAULT 1,
    criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
  ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

  $nome = $_POST['nome'] ?? '';
  $slug = $_POST['slug'] ?? '';
  $descricao = $_POST['descricao'] ?? '';
  $ordem = (int)($_POST['ordem'] ?? 0);
  $ativo = (int)($_POST['ativo'] ?? 1);

  // limpa o slug
  $slug = strtolower(trim($slug));
  $slug = preg_replace('/[^a-z0-9-_]/', '-', $slug);
  $slug = preg_replace('/-+/', '-', $slug);
  $slug = trim($slug, '-');

  // upload de ícone
  $icone = '';
  if(isset($_FILES['icone']) && $_FILES['icone']['error'] === UPLOAD_ERR_OK){
    $ext = strtolower(pathinfo($_FILES['icone']['name'], PATHINFO_EXTENSION));
    $permitidos = ['jpg','jpeg','png','webp','svg','gif'];
    if(!in_array($ext, $permitidos)){
      echo "Formato de ícone não permitido"; exit;
    }
    $nome_icone = uniqid().'_'.time().'.'.$ext;
    $pasta_upload = $_SERVER['DOCUMENT_ROOT'].'/sistema/painel/img/categorias/';
    if(!is_dir($pasta_upload)){ mkdir($pasta_upload, 0755, true); }
    $caminho = $pasta_upload.$nome_icone;
    if(move_uploaded_file($_FILES['icone']['tmp_name'], $caminho)){
      $icone = 'img/categorias/'.$nome_icone;
    }
  }

  try{
    if($id > 0){
      // EDITAR
      $sql = "UPDATE categoria_site SET nome=:nome, slug=:slug, descricao=:desc, ordem=:ord, ativo=:ativ";
      $params = [':nome'=>$nome, ':slug'=>$slug, ':desc'=>$descricao, ':ord'=>$ordem, ':ativ'=>$ativo, ':id'=>$id];
      
      if($icone){ $sql.=", icone=:icone"; $params[':icone']=$icone; }
      
      $sql.=" WHERE id=:id";
      $st = $pdo->prepare($sql);
      $st->execute($params);
    }else{
      // INSERIR
      $cols = ['nome','slug','descricao','ordem','ativo'];
      $vals = [':nome',':slug',':desc',':ord',':ativ'];
      $params = [':nome'=>$nome, ':slug'=>$slug, ':desc'=>$descricao, ':ord'=>$ordem, ':ativ'=>$ativo];

      if($icone){ $cols[]='icone'; $vals[]=':icone'; $params[':icone']=$icone; }

      $sql = "INSERT INTO categoria_site (".implode(',',$cols).") VALUES (".implode(',',$vals).")";
      $st = $pdo->prepare($sql);
      $st->execute($params);
    }
    echo "Salvo com Sucesso";
  }catch(Exception $e){
    if(strpos($e->getMessage(), 'Duplicate entry') !== false && strpos($e->getMessage(), 'slug') !== false){
      echo "Erro: Este slug já está em uso. Escolha outro.";
    }else{
      echo "Erro: ".$e->getMessage();
    }
  }
  exit;
}

echo "Tipo inválido";
