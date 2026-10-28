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

/* ===== entrada ===== */
$aba = $_POST['aba'] ?? 'banners'; // 'banners' | 'blocos' | 'carrossel' | 'categorias'

/* ====== BANNERS ====== */
if($aba === 'banners'){
  $tb = tblExists($pdo,'site_banners_data') ? 'site_banners_data'
      : (tblExists($pdo,'site_banners')     ? 'site_banners'     : 'arquivos');

  $orderExpr = colExists($pdo,$tb,'ordem') ? 'COALESCE(ordem,id)' : 'id';
  $descSel   = colExists($pdo,$tb,'descricao') ? 'descricao' : (colExists($pdo,$tb,'texto')?'texto':"'' AS descricao");
  $subtSel   = colExists($pdo,$tb,'subtitulo') ? 'subtitulo' : "'' AS subtitulo";
  $registro  = colExists($pdo,$tb,'registro')  ? 'registro'  : "'' AS registro";
  $ativoSel  = colExists($pdo,$tb,'ativo')     ? 'COALESCE(ativo,1) AS ativo' : '1 AS ativo';

  // sem busca/paginação — apenas filtros internos
  $wheres = [];
  if(colExists($pdo,$tb,'ativo'))    $wheres[] = 'COALESCE(ativo,1)=1';
  if(colExists($pdo,$tb,'registro')) $wheres[] = "registro IN ('home','site','index','banner_home')";
  $whereSql = $wheres ? 'WHERE '.implode(' AND ',$wheres) : '';

  $sql = "SELECT id, nome, $subtSel, $descSel, arquivo, $ativoSel, $orderExpr AS ordem, $registro
          FROM `$tb`
          $whereSql
          ORDER BY $orderExpr ASC";
  $res = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

  if(!$res){ echo '<small>Não possui nenhum registro Cadastrado!</small>'; exit; }

  echo <<<HTML
<div style="margin:0!important;padding:0!important;">
<small>
<table class="table table-hover" style="margin:0!important;">
<thead>
<tr>
  <th>Nome</th>
  <th class="esc">Descrição</th>
  <th class="esc">Mídia</th>
  <th class="esc">Ordem</th>
  <th class="esc">Ativo</th>
  <th>Ações</th>
</tr>
</thead>
<tbody>
HTML;

  foreach($res as $r){
    $id  = $r['id'];
    $nome = $r['nome'] ?? '';
    $subtitulo = $r['subtitulo'] ?? '';
    $descricao = $r['descricao'] ?? '';
    $arq  = $r['arquivo'] ?? '';
    $ativo = (int)($r['ativo'] ?? 1);
    $ordem = (int)($r['ordem'] ?? 0);
    $registro = $r['registro'] ?? '';

    $badge = $arq
      ? (preg_match('/\.(mp4|webm|ogg)$/i',$arq) ? '<span class="badge badge-secondary">Vídeo</span>' : '<span class="badge badge-secondary">Imagem</span>')
      : '-';
    $iconAtivo = $ativo ? 'fa-check text-success' : 'fa-close text-danger';
    $titleAtv  = $ativo ? 'Desativar' : 'Ativar';

    $nomeEsc = htmlspecialchars($nome,ENT_QUOTES,'UTF-8');
    $subtEsc = htmlspecialchars($subtitulo,ENT_QUOTES,'UTF-8');
    $descEsc = htmlspecialchars($descricao,ENT_QUOTES,'UTF-8');
    $arqEsc  = htmlspecialchars($arq,ENT_QUOTES,'UTF-8');
    $regEsc  = htmlspecialchars($registro,ENT_QUOTES,'UTF-8');

    echo <<<HTML
<tr>
  <td>{$nomeEsc}</td>
  <td class="esc">{$descEsc}</td>
  <td class="esc">{$badge}</td>
  <td class="esc">{$ordem}</td>
  <td class="esc">{$ativo}</td>
  <td>
    <big><a href="#" onclick="editarBanner('{$id}','{$nomeEsc}','{$subtEsc}','{$descEsc}','{$arqEsc}','{$ordem}','{$ativo}','{$regEsc}')" title="Editar"><i class="fa fa-edit text-primary"></i></a></big>

    <li class="dropdown head-dpdn2" style="display:inline-block;">
      <a href="#" class="dropdown-toggle" data-toggle="dropdown"><big><i class="fa fa-trash-o text-danger"></i></big></a>
      <ul class="dropdown-menu" style="margin-left:-180px;">
        <li><div class="notification_desc2">
          <p>Confirmar Exclusão? <a href="#" onclick="excluirItem('banner','{$id}')"><span class="text-danger">Sim</span></a></p>
        </div></li>
      </ul>
    </li>

    <big><a href="#" onclick="mudarAtivo('banner','{$id}',".($ativo?0:1).")" title="{$titleAtv}"><i class="fa {$iconAtivo}"></i></a></big>
  </td>
</tr>
HTML;
  }

  echo '</tbody></table></small></div>';
  exit;
}

/* ====== CARROSSEL ====== */
if($aba === 'carrossel'){
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

  $st = $pdo->query("SELECT id,titulo,IFNULL(subtitulo,'') subtitulo,IFNULL(video_url,'') video_url,
                            IFNULL(thumb,'') thumb, COALESCE(ordem,id) ordem, COALESCE(ativo,1) ativo
                     FROM site_carrossel
                     ORDER BY ordem ASC");
  $res = $st->fetchAll(PDO::FETCH_ASSOC);

  if(!$res){ echo '<small>Não possui nenhum registro Cadastrado!</small>'; exit; }

  echo '<div style="margin:0!important;padding:0!important;"><small><table class="table table-hover" style="margin:0!important;"><thead><tr>
    <th>Título</th><th class="esc">Subtítulo</th><th class="esc">Thumb</th><th class="esc">Vídeo</th>
    <th class="esc">Ordem</th><th class="esc">Ativo</th><th>Ações</th></tr></thead><tbody>';

  foreach($res as $r){
    $id=$r['id']; $t=$r['titulo']; $s=$r['subtitulo']; $v=$r['video_url']; $th=$r['thumb']; $o=$r['ordem']; $a=(int)$r['ativo'];

    $titEsc=htmlspecialchars($t,ENT_QUOTES,'UTF-8');
    $subEsc=htmlspecialchars($s,ENT_QUOTES,'UTF-8');
    $vidEsc=htmlspecialchars($v,ENT_QUOTES,'UTF-8');
    $thEsc =htmlspecialchars($th,ENT_QUOTES,'UTF-8');

    $badgeT = $th ? '<span class="badge badge-secondary">Imagem</span>' : '-';
    $iconAtv = $a ? 'fa-check text-success' : 'fa-close text-danger';
    $titleAtv= $a ? 'Desativar' : 'Ativar';

    echo "<tr>
      <td>{$titEsc}</td>
      <td class=\"esc\">{$subEsc}</td>
      <td class=\"esc\">{$badgeT}</td>
      <td class=\"esc\">".($v ? 'YouTube' : '-')."</td>
      <td class=\"esc\">{$o}</td>
      <td class=\"esc\">{$a}</td>
      <td>
        <big><a href=\"#\" onclick=\"editarCarrossel('{$id}','{$titEsc}','{$subEsc}','{$vidEsc}','{$thEsc}','{$o}','{$a}')\" title=\"Editar\"><i class=\"fa fa-edit text-primary\"></i></a></big>

        <li class=\"dropdown head-dpdn2\" style=\"display:inline-block;\">
          <a href=\"#\" class=\"dropdown-toggle\" data-toggle=\"dropdown\"><big><i class=\"fa fa-trash-o text-danger\"></i></big></a>
          <ul class=\"dropdown-menu\" style=\"margin-left:-180px;\">
            <li><div class=\"notification_desc2\">
              <p>Confirmar Exclusão? <a href=\"#\" onclick=\"excluirItem('carrossel','{$id}')\"><span class=\"text-danger\">Sim</span></a></p>
            </div></li>
          </ul>
        </li>

        <big><a href=\"#\" onclick=\"mudarAtivo('carrossel','{$id}',".($a?0:1).")\" title=\"{$titleAtv}\"><i class=\"fa {$iconAtv}\"></i></a></big>
      </td>
    </tr>";
  }

  echo '</tbody></table></small></div>';
  exit;
}

/* ====== CATEGORIAS (categoria_site) ====== */
if($aba === 'categorias'){
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

  $st = $pdo->query("SELECT id, nome, slug, IFNULL(descricao,'') descricao, IFNULL(icone,'') icone,
                            COALESCE(ordem,id) ordem, COALESCE(ativo,1) ativo
                     FROM categoria_site
                     ORDER BY ordem ASC");
  $res = $st->fetchAll(PDO::FETCH_ASSOC);

  if(!$res){ echo '<small>Não possui nenhum registro Cadastrado!</small>'; exit; }

  echo '<div style="margin:0!important;padding:0!important;"><small><table class="table table-hover" style="margin:0!important;"><thead><tr>
    <th>Nome</th><th class="esc">Slug</th><th class="esc">Descrição</th><th class="esc">Ícone</th>
    <th class="esc">Ordem</th><th class="esc">Ativo</th><th>Ações</th></tr></thead><tbody>';

  foreach($res as $r){
    $id=$r['id']; $nome=$r['nome']; $slug=$r['slug']; $desc=$r['descricao']; $icone=$r['icone']; $o=$r['ordem']; $a=(int)$r['ativo'];

    $nomeEsc=htmlspecialchars($nome,ENT_QUOTES,'UTF-8');
    $slugEsc=htmlspecialchars($slug,ENT_QUOTES,'UTF-8');
    $descEsc=htmlspecialchars($desc,ENT_QUOTES,'UTF-8');
    $iconeEsc=htmlspecialchars($icone,ENT_QUOTES,'UTF-8');

    $badgeIcon = $icone ? '<span class="badge badge-secondary">Imagem</span>' : '-';
    $iconAtv = $a ? 'fa-check text-success' : 'fa-close text-danger';
    $titleAtv= $a ? 'Desativar' : 'Ativar';
    $descResumida = mb_strlen($desc) > 50 ? mb_substr($desc,0,50).'...' : $desc;

    echo "<tr>
      <td>{$nomeEsc}</td>
      <td class=\"esc\"><code>{$slugEsc}</code></td>
      <td class=\"esc\">{$descResumida}</td>
      <td class=\"esc\">{$badgeIcon}</td>
      <td class=\"esc\">{$o}</td>
      <td class=\"esc\">{$a}</td>
      <td>
        <big><a href=\"#\" onclick=\"editarCategoria('{$id}','{$nomeEsc}','{$slugEsc}','{$descEsc}','{$iconeEsc}','{$o}','{$a}')\" title=\"Editar\"><i class=\"fa fa-edit text-primary\"></i></a></big>

        <li class=\"dropdown head-dpdn2\" style=\"display:inline-block;\">
          <a href=\"#\" class=\"dropdown-toggle\" data-toggle=\"dropdown\"><big><i class=\"fa fa-trash-o text-danger\"></i></big></a>
          <ul class=\"dropdown-menu\" style=\"margin-left:-180px;\">
            <li><div class=\"notification_desc2\">
              <p>Confirmar Exclusão? <a href=\"#\" onclick=\"excluirItem('categoria','{$id}')\"><span class=\"text-danger\">Sim</span></a></p>
            </div></li>
          </ul>
        </li>

        <big><a href=\"#\" onclick=\"mudarAtivo('categoria','{$id}',".($a?0:1).")\" title=\"{$titleAtv}\"><i class=\"fa {$iconAtv}\"></i></a></big>
      </td>
    </tr>";
  }

  echo '</tbody></table></small></div>';
  exit;
}

/* ====== BLOCOS ====== */
$tb = tblExists($pdo,'home_blocos') ? 'home_blocos' : 'textos_index';

$hasSlug   = colExists($pdo,$tb,'categoria') || colExists($pdo,$tb,'slug');
$slugCol   = colExists($pdo,$tb,'categoria') ? 'categoria' : (colExists($pdo,$tb,'slug') ? 'slug' : null);
$titCol    = colExists($pdo,$tb,'titulo') ? 'titulo' : (colExists($pdo,$tb,'nome') ? 'nome' : null);
$subtCol   = colExists($pdo,$tb,'subtitulo') ? 'subtitulo' : (colExists($pdo,$tb,'sub_titulo') ? 'sub_titulo' : null);

$txtCol  = colExists($pdo,$tb,'texto') ? 'texto' : (colExists($pdo,$tb,'descricao') ? 'descricao' : (colExists($pdo,$tb,'conteudo') ? 'conteudo' : null));
$imgCol  = colExists($pdo,$tb,'img') ? 'img' : (colExists($pdo,$tb,'imagem') ? 'imagem' : (colExists($pdo,$tb,'capa') ? 'capa' : (colExists($pdo,$tb,'thumb') ? 'thumb' : null)));
$vidCol  = colExists($pdo,$tb,'video') ? 'video' : (colExists($pdo,$tb,'video_url') ? 'video_url' : (colExists($pdo,$tb,'midia') ? 'midia' : null));
$statCol = colExists($pdo,$tb,'ativo') ? 'ativo' : (colExists($pdo,$tb,'status') ? 'status' : null);
$ordExpr = colExists($pdo,$tb,'ordem') ? 'COALESCE(ordem,id)' : 'id';

$slugSel = $slugCol ? "$slugCol AS slug" : "'' AS slug";
$titSel  = $titCol  ? "`$titCol` AS titulo" : "'' AS titulo";
$subtSel = $subtCol ? "`$subtCol` AS subtitulo" : "'' AS subtitulo";
$txtSel  = $txtCol  ? "`$txtCol` AS texto"  : "'' AS texto";
$imgSel  = $imgCol  ? "`$imgCol` AS img"    : "'' AS img";
$vidSel  = $vidCol  ? "`$vidCol` AS video"  : "'' AS video";
$statSel = $statCol ? "COALESCE($statCol,1) AS status" : "1 AS status";

$sql = "SELECT id, $slugSel, $titSel, $subtSel, $txtSel, $imgSel, $vidSel, $statSel, $ordExpr AS ordem
        FROM `$tb`
        ORDER BY $ordExpr ASC";
$res = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

if(!$res){ echo '<small>Não possui nenhum registro Cadastrado!</small>'; exit; }

echo <<<HTML
<div style="margin:0!important;padding:0!important;">
<small>
<table class="table table-hover" style="margin:0!important;">
<thead>
<tr>
  <th>Categoria</th>
  <th class="esc">Título</th>
  <th class="esc">Imagem</th>
  <th class="esc">Vídeo</th>
  <th class="esc">Status</th>
  <th>Ações</th>
</tr>
</thead>
<tbody>
HTML;

foreach($res as $r){
  $id=$r['id']; $slug=$r['slug']; $titulo=$r['titulo']; $subtitulo=$r['subtitulo']; $texto=$r['texto']; $img=$r['img']; $video=$r['video']; $status=(int)$r['status'];

  $imgBadge = $img   ? '<span class="badge badge-secondary">Imagem</span>' : '-';
  $vidBadge = $video ? '<span class="badge badge-secondary">Vídeo</span>'  : '-';
  $iconAtivo = $status ? 'fa-check text-success' : 'fa-close text-danger';
  $titleAtv  = $status ? 'Desativar' : 'Ativar';

  $slugEsc = htmlspecialchars($slug ?? '',ENT_QUOTES,'UTF-8');
  $titEsc  = htmlspecialchars($titulo ?? '',ENT_QUOTES,'UTF-8');
  $subtEsc = htmlspecialchars($subtitulo ?? '',ENT_QUOTES,'UTF-8');
  $txtEsc  = htmlspecialchars($texto ?? '',ENT_QUOTES,'UTF-8');
  $imgEsc  = htmlspecialchars($img ?? '',ENT_QUOTES,'UTF-8');
  $vidEsc  = htmlspecialchars($video ?? '',ENT_QUOTES,'UTF-8');

  echo <<<HTML
<tr>
  <td><code>{$slugEsc}</code></td>
  <td class="esc">{$titEsc}</td>
  <td class="esc">{$imgBadge}</td>
  <td class="esc">{$vidBadge}</td>
  <td class="esc">{$status}</td>
  <td>
    <big><a href="#" onclick="editarBloco('{$id}','{$slugEsc}','{$titEsc}','{$subtEsc}','{$txtEsc}','{$imgEsc}','{$vidEsc}','{$status}')" title="Editar"><i class="fa fa-edit text-primary"></i></a></big>

    <li class="dropdown head-dpdn2" style="display:inline-block;">
      <a href="#" class="dropdown-toggle" data-toggle="dropdown"><big><i class="fa fa-trash-o text-danger"></i></big></a>
      <ul class="dropdown-menu" style="margin-left:-180px;">
        <li><div class="notification_desc2">
          <p>Confirmar Exclusão? <a href="#" onclick="excluirItem('bloco','{$id}')"><span class="text-danger">Sim</span></a></p>
        </div></li>
      </ul>
    </li>

    <big><a href="#" onclick="mudarAtivo('bloco','{$id}',".($status?0:1).")" title="{$titleAtv}"><i class="fa {$iconAtivo}\"></i></a></big>
  </td>
</tr>
HTML;
}

echo '</tbody></table></small></div>';
