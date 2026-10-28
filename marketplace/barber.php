<?php 
// ===== Sessão isolada por contexto (MASTER x TENANT) =====
// Evita que o cookie padrão (PHPSESSID) misture MASTER x TENANT.
// Regra:
// - /sistema (sem /loja/ e sem ?loja=) => BB_MASTER
// - /loja/<slug>/... OU ?loja=...      => BB_TENANT
// - demais                              => BB_SITE
$__bb_reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$__bb_refPath = '';
if (!empty($_SERVER['HTTP_REFERER'])) {
  $__bb_refPath = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: '';
}

$__bb_lojaByPath = (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath)
                || (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_refPath);

$__bb_hasLoja = $__bb_lojaByPath || !empty($_GET['loja']) || !empty($_POST['loja']);
$__bb_isSistema = (strpos($__bb_reqPath, '/sistema') === 0) || (strpos($__bb_refPath, '/sistema') === 0);

// Força MASTER em /sistema quando NÃO é loja por PATH e NÃO tem ?loja=
$__bb_forceMaster = ($__bb_isSistema && !$__bb_lojaByPath && empty($_GET['loja']) && empty($_POST['loja']));

$__bb_desiredSession = $__bb_forceMaster ? 'BB_MASTER' : ($__bb_hasLoja ? 'BB_TENANT' : 'BB_SITE');

// Se já iniciou com nome errado, reabre mantendo o mesmo ID
if (session_status() === PHP_SESSION_ACTIVE) {
  if (session_name() !== $__bb_desiredSession) {
    $__bb_oldId = session_id();
    session_write_close();
    @session_name($__bb_desiredSession);
    @session_id($__bb_oldId);
    @session_start();
  }
} else {
  @session_name($__bb_desiredSession);
  @session_start();
}

header('Content-Type: text/html; charset=UTF-8');

/* ========= CONEXÃO ========= */
$pdo = null;
try { require_once $_SERVER['DOCUMENT_ROOT'].'/sistema/conexao.php'; } catch (Throwable $e) { $pdo = null; }

/* ========= HELPERS ========= */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function only_digits($s){ return preg_replace('/\D+/', '', (string)$s); }

/* DETECÇÃO DE MÍDIA */
function is_video($p){ $path = parse_url((string)$p, PHP_URL_PATH) ?: (string)$p; return (bool)preg_match('/\.(mp4|webm|ogg)$/i', $path); }
function is_image($p){ $path = parse_url((string)$p, PHP_URL_PATH) ?: (string)$p; return (bool)preg_match('/\.(jpe?g|png|gif|webp|svg)$/i', $path); }
function is_media_file($p){ return is_image($p) || is_video($p); }
function is_http_url($u){ return (bool)preg_match('#^(https?:)?//#i', trim((string)$u)); }

/* Vídeo embed */
function is_youtube_url($u){ return (bool)preg_match('#(youtu\.be/|youtube\.com/(watch|shorts|embed))#i',$u); }
function yt_to_embed($u){
  $u = trim($u);
  if(preg_match('#youtu\.be/([A-Za-z0-9_-]{6,})#i',$u,$m)) return 'https://www.youtube.com/embed/'.$m[1];
  if(preg_match('#youtube\.com/(watch\?v=|shorts/)([A-Za-z0-9_-]{6,})#i',$u,$m)) return 'https://www.youtube.com/embed/'.$m[2];
  if(preg_match('#youtube\.com/embed/([A-Za-z0-9_-]{6,})#i',$u,$m)) return 'https://www.youtube.com/embed/'.$m[1];
  return $u;
}
function is_vimeo_url($u){ return (bool)preg_match('#(vimeo\.com/\d+|player\.vimeo\.com/video/\d+)#i',$u); }
function vimeo_to_embed($u){
  if(preg_match('#vimeo\.com/(\d+)#i',$u,$m)) return 'https://player.vimeo.com/video/'.$m[1];
  if(preg_match('#player\.vimeo\.com/video/(\d+)#i',$u,$m)) return 'https://player.vimeo.com/video/'.$m[1];
  return $u;
}
function is_embed_url($u){ return is_youtube_url($u) || is_vimeo_url($u); }
function to_embed_url($u){ if(is_youtube_url($u)) return yt_to_embed($u); if(is_vimeo_url($u)) return vimeo_to_embed($u); return $u; }

/* Schema helpers – compatível com PHP 5.3+ */
function table_exists(?PDO $pdo, $name){
  if(!$pdo) return false;
  try{ $st=$pdo->prepare('SHOW TABLES LIKE :t'); $st->execute([':t'=>$name]); return (bool)$st->fetch(PDO::FETCH_NUM); }
  catch(Throwable $e){ return false; }
}
function col_exists(PDO $pdo,$table,$col){
  try{ $st=$pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :c"); $st->execute([':c'=>$col]); return (bool)$st->fetch(PDO::FETCH_ASSOC); }
  catch(Throwable $e){ return false; }
}
function any_col_exists(PDO $pdo,$table,array $cols){ 
  foreach($cols as $c){ if(col_exists($pdo,$table,$c)) return true; } 
  return false; 
}
function coalesce_expr(PDO $pdo, $table, $cands, $alias){
  // compatível com PHP antigo (sem arrow function)
  $ok = array_values(array_filter($cands, function($c) use ($pdo,$table){ 
    return col_exists($pdo,$table,$c); 
  }));
  if($ok){ 
    $cols = implode(',', array_map(function($c){ return "`{$c}`"; }, $ok)); 
    return "COALESCE({$cols}) AS {$alias}"; 
  }
  return "NULL AS {$alias}";
}

/* CTA para href válido */
function canonical_link($u){
  $u = trim((string)$u);
  if($u === '' || $u === '#') return '';
  if(preg_match('#^(https?:)?//#i',$u)) return $u;
  if($u[0] === '/') return $u;
  if(preg_match('/^[\w.-]+\.[a-z]{2,}(\/.*)?$/i',$u)) return 'https://'.$u;
  return '/'.ltrim($u,'/');
}

/* ========= SETTINGS ========= */
function loadSettings(?PDO $pdo){
  // >>> AQUI ajustamos a base real dos banners <<<
  $out = [
    'agenda_url'  => 'https://jacycabeleireiro.com/agendamentos',
    'whatsapp'    => '5545999882100',
    'endereco'    => 'Rio Grande do Sul, 2151',
    // caminho real onde ficam os banners:
    'uploads_base'=> '/sistema/painel/paginas/site/uploads_banners/',
    'logo_png'    => '/sistema/img/logo.png',
    'logo_webp'   => '/sistema/img/logo.webp',
  ];
  if(!$pdo) return $out;

  $kvTables = ['settings','config','configs','parametros','opcoes','site_config','configuracoes'];
  foreach($kvTables as $t){
    if(!table_exists($pdo,$t)) continue;
    if(!any_col_exists($pdo,$t,['chave','key','nome']) || !any_col_exists($pdo,$t,['valor','value','conteudo','texto'])) continue;
    try{
      $keyExpr = coalesce_expr($pdo,$t,['chave','key','nome','slug'],'k');
      $valExpr = coalesce_expr($pdo,$t,['valor','value','conteudo','texto','dados'],'v');
      $rows = $pdo->query("SELECT {$keyExpr}, {$valExpr} FROM `{$t}`")->fetchAll(PDO::FETCH_ASSOC);
      foreach($rows as $r){
        $k = strtolower(trim((string)($r['k']??''))); if($k==='') continue;
        $v = trim((string)($r['v']??''));
        switch($k){
          case 'agenda': case 'agenda_url': $out['agenda_url']=$v; break;
          case 'wpp': case 'whatsapp': $out['whatsapp']=$v; break;
          case 'endereco': case 'endereço': case 'address': $out['endereco']=$v; break;
          case 'uploads': case 'uploads_base': case 'upload_dir': 
            // se tiver na base, substitui (senão, fica o caminho real acima)
            if($v!=='') $out['uploads_base']=$v; 
            break;
          case 'logo_png': case 'logo': $out['logo_png']=$v; break;
          case 'logo_webp': $out['logo_webp']=$v; break;
        }
      }
      return $out;
    }catch(Throwable $e){}
  }

  $singleTables = ['site_config','configuracoes','empresa','institucional','config_site'];
  foreach($singleTables as $t){
    if(!table_exists($pdo,$t)) continue;
    try{
      $agenda = coalesce_expr($pdo,$t,['agenda','agenda_url','link_agenda'],'agenda_url');
      $wpp    = coalesce_expr($pdo,$t,['whatsapp','wpp','telefone'],'whatsapp');
      $end    = coalesce_expr($pdo,$t,['endereco','endereço','address'],'endereco');
      $up     = coalesce_expr($pdo,$t,['uploads_base','uploads','upload_dir'],'uploads_base');
      $lpng   = coalesce_expr($pdo,$t,['logo_png','logo'],'logo_png');
      $lwebp  = coalesce_expr($pdo,$t,['logo_webp'],'logo_webp');
      $r = $pdo->query("SELECT {$agenda},{$wpp},{$end},{$up},{$lpng},{$lwebp} FROM `{$t}` LIMIT 1")->fetch(PDO::FETCH_ASSOC);
      if($r){ foreach($r as $k=>$v){ if($v!==null && $v!=='') $out[$k]=$v; } return $out; }
    }catch(Throwable $e){}
  }
  return $out;
}

function wpp_url_from($raw){
  $num = only_digits($raw);
  if($num==='') return 'https://wa.me/5545999882100';
  if(strpos($num,'55')!==0) $num = '55'.$num;
  return 'https://wa.me/'.$num;
}

/* Caminhos e versionamento */
function normalize_src($s){
  $s = trim((string)$s);
  if($s==='') return '';
  if(preg_match('#^(https?:)?//#',$s)) return $s;
  if($s[0]==='/'){ return $s; } // agora aceitamos absoluto como veio
  // quando vier só o nome do arquivo (caso da tabela), prefixa com UP
  $base = rtrim((string)UP,'/');
  return $base.'/'.$s;
}
function media_with_ver($url){
  $u = normalize_src($url);
  if($u==='' || is_http_url($u)) return $u;
  $fs = $_SERVER['DOCUMENT_ROOT'].$u;
  if(is_file($fs)){ $v = (string)@filemtime($fs); return $u.($v ? ('?v='.$v) : ''); }
  return $u;
}
function video_mime($u){
  $path = parse_url((string)$u, PHP_URL_PATH) ?: (string)$u;
  $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
  if($ext==='webm') return 'video/webm';
  if($ext==='ogg' || $ext==='ogv') return 'video/ogg';
  return 'video/mp4';
}

/* ========= SETTINGS + CONST ========= */
$SET = loadSettings($pdo);
$uploadsBase = rtrim((string)$SET['uploads_base'],'/').'/';
$agendaUrl   = (string)$SET['agenda_url'];
$wppUrl      = wpp_url_from($SET['whatsapp']);
$logoPngUrl  = (string)$SET['logo_png'];
$logoWebpUrl = (string)$SET['logo_webp'];
$enderecoStr = (string)$SET['endereco'];

if(!defined('UP'))     define('UP', $uploadsBase);
if(!defined('AGENDA')) define('AGENDA', $agendaUrl);
if(!defined('WPP'))    define('WPP', $wppUrl);

// Defaults de conteúdo
$nome_sistema = isset($nome_sistema) ? $nome_sistema : 'Jacy Cabeleireiro';
$icone_site = isset($icone_site) ? $icone_site : 'favicon.ico';
$texto_rodape = isset($texto_rodape) ? $texto_rodape : 'Atendimento profissional com técnica e dedicação.';
$imagem_sobre = isset($imagem_sobre) ? $imagem_sobre : 'sobre.jpg';
$texto_sobre = isset($texto_sobre) ? $texto_sobre : 'Com mais de 25 anos de experiência, oferecemos os melhores serviços de cabeleireiro.';
$url_video = isset($url_video) ? $url_video : '';
$posicao_video = isset($posicao_video) ? $posicao_video : '';

/* ========= SLIDES ========= */
function loadSlides(?PDO $pdo){
  $fallback = [[
    'img'=>UP.'banner-3.jpg','video'=>'',
    'titulo'=>'CORTES PROFISSIONAIS',
    'texto'=>'Você merece o melhor. Conte com especialistas.',
    'link'=>''
  ]];
  if(!$pdo) return $fallback;

  $table = table_exists($pdo,'site_banners_data') ? 'site_banners_data'
         : (table_exists($pdo,'site_banners')     ? 'site_banners'
         : (table_exists($pdo,'arquivos')         ? 'arquivos'    : null));
  if(!$table) return $fallback;

  try{
    $isData = ($table === 'site_banners_data');
    $midia  = $isData
      ? coalesce_expr($pdo,$table,['arquivo','src','caminho','midia'],'src')
      : coalesce_expr($pdo,$table,['arquivo','src','caminho','link','url'],'src');
    $titulo = coalesce_expr($pdo,$table,['titulo','nome','legenda'],'titulo');
    $texto  = coalesce_expr($pdo,$table,['texto','descricao','conteudo','caption'],'texto');
    $cta    = $isData
      ? coalesce_expr($pdo,$table,['btn_url','cta_url','link_url','href','url','link','arquivo_url'],'cta')
      : coalesce_expr($pdo,$table,['btn_url','cta_url','link_url','href','arquivo_url','url','link'],'cta');

    $ordem  = col_exists($pdo,$table,'ordem') ? 'ordem' : (col_exists($pdo,$table,'posicao')?'posicao':'id');
    $wheres = [];
    if(col_exists($pdo,$table,'ativo'))    $wheres[] = 'COALESCE(ativo,1)=1';
    if(col_exists($pdo,$table,'registro')) $wheres[] = "registro IN ('home','site','index','banner_home')";
    $whereSql = $wheres ? (' WHERE '.implode(' AND ',$wheres)) : '';

    $rows = $pdo->query("SELECT {$titulo}, {$texto}, {$midia}, {$cta}
              FROM `{$table}`{$whereSql} ORDER BY {$ordem} ASC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);

    $out = [];
    foreach($rows as $r){
      $src = normalize_src($r['src'] ?? ''); if($src==='') continue;

      $ctaRaw = trim((string)($r['cta'] ?? '')); $ctaUse = '';
      if($ctaRaw !== '' && $ctaRaw !== '#'){
        $norm = canonical_link($ctaRaw);
        if($norm !== '' && !is_media_file($norm)) $ctaUse = $norm;
      }

      $item = [
        'img'=>'','video'=>'',
        'titulo'=> trim((string)($r['titulo']??'')),
        'texto' => trim((string)($r['texto']??'')),
        'link'  => $ctaUse,
      ];
      if(is_video($src)) $item['video']=$src; else $item['img']=$src;
      $out[] = $item;
    }
    return $out ?: $fallback;
  }catch(Throwable $e){ return $fallback; }
}

/* ========= BLOCOS ========= */
function loadBlock(?PDO $pdo, $slug, $def){
  if(!$pdo) return $def;
  $table = table_exists($pdo,'home_blocos') ? 'home_blocos' : (table_exists($pdo,'textos_index') ? 'textos_index' : null);
  if(!$table) return $def;

  try{
    $titulo = coalesce_expr($pdo,$table,['titulo','nome'],'titulo');
    $texto  = coalesce_expr($pdo,$table,['texto','descricao','conteudo'],'texto');
    $img    = coalesce_expr($pdo,$table,['img','imagem','capa','thumb'],'img');
    $video  = coalesce_expr($pdo,$table,['video','video_url','midia'],'video');

    $statusExpr = col_exists($pdo,$table,'ativo') ? 'COALESCE(ativo,1)=1'
                 : (col_exists($pdo,$table,'status') ? 'COALESCE(status,1)=1' : '1=1');
    $slugCol = col_exists($pdo,$table,'categoria') ? 'categoria' : (col_exists($pdo,$table,'slug') ? 'slug' : null);
    if(!$slugCol) return $def;

    $st=$pdo->prepare("SELECT {$titulo},{$texto},{$img},{$video} FROM `{$table}` WHERE {$statusExpr} AND {$slugCol}=:s LIMIT 1");
    $st->execute([':s'=>$slug]); $r=$st->fetch(PDO::FETCH_ASSOC);
    if(!$r) return $def;

    return [
      'titulo'=> $r['titulo'] ?: $def['titulo'],
      'texto' => $r['texto']  ?: $def['texto'],
      'img'   => normalize_src($r['img']   ?: $def['img']),
      'video' => normalize_src($r['video'] ?: $def['video']),
    ];
  }catch(Throwable $e){ return $def; }
}

/* ========= DADOS ========= */
$slides = loadSlides($pdo);
$preloadHero = (!empty($slides[0]['img'])) ? media_with_ver($slides[0]['img']) : '';

$blocos = [
  'corte_masculino' => loadBlock($pdo,'corte_masculino',[
    'titulo'=>'Corte Masculino','texto'=>'Cortes alinhados ao seu estilo, com acabamento impecável.',
    'img'=>UP.'corte-masculino.jpg','video'=>UP.'corte-masculino.mp4'
  ]),
  'corte_feminino' => loadBlock($pdo,'corte_feminino',[
    'titulo'=>'Corte Feminino','texto'=>'Técnica e cuidado para valorizar seus traços.',
    'img'=>UP.'corte-feminino.jpg','video'=>UP.'corte-feminino.mp4'
  ]),
  'mechas' => loadBlock($pdo,'mechas',[
    'titulo'=>'Mechas','texto'=>'Luzes e balayage com preservação de fibra e brilho intenso.',
    'img'=>UP.'mechas.jpg','video'=>UP.'mechas.mp4'
  ]),
  'escova_progressiva' => loadBlock($pdo,'escova_progressiva',[
    'titulo'=>'Escova Progressiva','texto'=>'Redução de volume e alinhamento, respeitando a saúde do fio.',
    'img'=>UP.'escova-progressiva.jpg','video'=>UP.'escova-progressiva.mp4'
  ]),
  'protese_capilar' => loadBlock($pdo,'protese_capilar',[
    'titulo'=>'Prótese Capilar','texto'=>'Aparência natural, fixação segura e manutenção planejada.',
    'img'=>UP.'protese.jpg','video'=>UP.'protese.mp4'
  ]),
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <link rel="shortcut icon" href="images/<?php echo $icone_site ?>" type="image/x-icon">
  <title><?php echo $nome_sistema ?></title>
  <meta name="description" content="Cortes, prótese capilar, mechas e escovas com técnica, higiene e acabamento profissional.">
  <meta name="author" content="Jacy Cordeiro">
  <meta name="theme-color" content="#000000">

  <link rel="dns-prefetch" href="//cdn.jsdelivr.net">
  <link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
  <link rel="dns-prefetch" href="//cdnjs.cloudflare.com">
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>

  <?php if ($preloadHero && is_image($slides[0]['img'])): ?>
  <link rel="preload" as="image" href="<?=h($preloadHero)?>" fetchpriority="high">
  <?php endif; ?>

  <link rel="preload" as="style" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" onload="this.onload=null;this.rel='stylesheet'">
  <noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"></noscript>

  <style>
    :root{ --bg:#0a0a0a; --bg2:#101010; --text:#e9e9e9; --muted:#a3a6ad; --brand:#2d86ff }
    html,body{background:var(--bg); color:var(--text)}
    body{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,"Helvetica Neue",Arial,"Noto Sans",sans-serif}
    a{color:var(--text)} a:hover{opacity:.9}
    .navbar{background:rgba(0,0,0,.85)!important; backdrop-filter:blur(6px); border-bottom:1px solid rgba(255,255,255,.06)}
    .navbar .nav-link{font-weight:600}
    .navbar{
      --bs-navbar-color:#fff; --bs-navbar-hover-color:#fff; --bs-navbar-active-color:#fff;
      --bs-navbar-brand-color:#fff; --bs-navbar-brand-hover-color:#fff;
      --bs-navbar-toggler-border-color:rgba(255,255,255,.2);
      --bs-navbar-toggler-icon-bg:url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba(255,255,255, 0.9)' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
    }
    .navbar .nav-link,.navbar .navbar-brand{color:#fff!important}
    html, body { margin: 0; overflow-x: hidden; }
    .hero.hero-full{ width:100vw; max-width:100vw; border-radius:0!important }
    .hero.hero-full .carousel-item{ aspect-ratio:16/9; max-height:720px }
    .hero img,.hero video{ width:100%; height:100%; object-fit:cover; display:block }
    .carousel-caption{
      text-align:left; left:10%; right:auto; bottom:8%;
      background:linear-gradient(90deg,rgba(0,0,0,.55),rgba(0,0,0,.15));
      padding:18px 22px; border-radius:12px; z-index:5; pointer-events:auto;
    }
    .btn-brand{background:var(--brand); border:0; font-weight:700}
    .section{padding:48px 0; content-visibility:auto; contain-intrinsic-size:1px 800px}
    .media-wrap{position:relative; border-radius:18px; overflow:hidden; background:#000; aspect-ratio:16/9}
    .media-wrap img,.media-wrap video{width:100%;height:100%;object-fit:cover;display:none}
    .media-wrap iframe{width:100%;height:100%;display:none;border:0}
    .media-wrap .on{display:block}
    .switcher .btn{border-radius:999px}
    .switcher .btn:disabled{opacity:.45; cursor:not-allowed}
    .media-meta{ position:absolute;left:10px;bottom:10px; background:rgba(0,0,0,.55);backdrop-filter:blur(3px);
      border:1px solid rgba(255,255,255,.08); padding:6px 10px;border-radius:999px;font-size:12px;color:#eee }
    .footer{background:var(--bg2); border-top:1px solid rgba(255,255,255,.06); content-visibility:auto; contain-intrinsic-size:1px 500px}
    .footer .muted{color:var(--muted)}
    .footer .form-control{background:#0f0f10; border:1px solid #1a1a1b; color:var(--text)}
    .bottom-bar{border-top:1px solid rgba(255,255,255,.06); color:#c9c9c9}
    .carousel-control-prev, .carousel-control-next{ width:8%; }
    #hero .carousel-indicators{margin:0 0 10px 0}
  </style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark sticky-top">
  <div class="container-xxl">
    <a class="navbar-brand d-flex align-items-center gap-2" href="/">
      <picture>
        <?php if(!empty($logoWebpUrl)): ?><source type="image/webp" srcset="<?=h($logoWebpUrl)?>?v=1"><?php endif; ?>
        <img src="<?=h($logoPngUrl)?>?v=1" alt="Jacy Cabeleireiro" width="100" height="42" class="rounded" loading="eager" fetchpriority="high">
      </picture>
      <span class="fw-bold"><?php echo $nome_sistema ?></span>
    </a>

    <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-label="Abrir menu">
      <span class="navbar-toggler-icon"></span>
    </button>

    <div class="collapse navbar-collapse" id="nav">
      <ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
        <li class="nav-item"><a class="nav-link" href="/agendamentos"><span aria-hidden="true">📅</span> Agendamentos</a></li>
        <li class="nav-item"><a class="nav-link" href="/assinatura"><span aria-hidden="true">✍️</span> Assinaturas</a></li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown"><span aria-hidden="true">✂️</span> Serviços</a>
          <ul class="dropdown-menu dropdown-menu-dark">
            <li><a class="dropdown-item" href="/servicos" target="_blank" rel="noopener">Serviços</a></li>
            <li><hr class="dropdown-divider"></li>
            <li><a class="dropdown-item" href="/barbearia" target="_blank" rel="noopener">Barbeiro</a></li>
            <li><a class="dropdown-item" href="/protese-capilar" target="_blank" rel="noopener">Protese-capilar</a></li>
            <li><a class="dropdown-item" href="/corte-cabelo" target="_blank" rel="noopener">Corte-cabelo</a></li>
          </ul>
        </li>
        <li class="nav-item"><a class="nav-link" href="/produtos"><span aria-hidden="true">🛍️</span> Produtos</a></li>
        <li class="nav-item"><a class="nav-link" href="<?php echo function_exists('bb_url_acesso') ? bb_url_acesso() : '/sistema/acesso'; ?>" target="_blank" rel="noopener"><span aria-hidden="true">🔒</span> Acessar</a></li>
        <li class="nav-item ms-lg-2"><a class="btn btn-brand btn-sm" href="<?=h(AGENDA)?>">Agendar</a></li>
      </ul>
    </div>
  </div>
</nav>

<div class="container-fluid px-0">
  <div id="hero" class="carousel slide hero hero-full overflow-hidden" data-bs-ride="carousel">
    <div class="carousel-inner">
      <?php foreach($slides as $i=>$s): $isFirst = ($i===0); ?>
        <div class="carousel-item <?= $isFirst?'active':'' ?>">
          <?php if(!empty($s['video'])): ?>
            <video
              src="<?=h(media_with_ver($s['video']))?>"
              <?= !empty($s['img']) ? 'poster="'.h(media_with_ver($s['img'])).'"':'' ?>
              preload="<?= $isFirst ? 'metadata' : 'none' ?>" muted playsinline loop data-autoplay="1" width="1920" height="1080"></video>
          <?php else: ?>
            <img
              src="<?=h(media_with_ver($s['img']))?>"
              alt="<?=h($s['titulo']?:'Banner')?>"
              decoding="async" <?= $isFirst ? 'loading="eager" fetchpriority="high"' : 'loading="lazy" fetchpriority="low"' ?>
              width="1920" height="1080" />
          <?php endif; ?>

          <?php if(($s['titulo']??'')||($s['texto']??'')||(!empty($s['link']))): ?>
            <div class="carousel-caption">
              <?php if($s['titulo']): ?><h3 class="fw-bold mb-1"><?=h($s['titulo'])?></h3><?php endif; ?>
              <?php if($s['texto']): ?><p class="mb-2"><?=h($s['texto'])?></p><?php endif; ?>
              <?php if(!empty($s['link'])): ?>
                <a class="btn btn-brand btn-sm" href="<?=h($s['link'])?>" target="_blank" rel="noopener">Saber mais +</a>
              <?php endif; ?>
            </div>
          <?php endif; ?>
        </div>
      <?php endforeach; ?>
    </div>

    <button class="carousel-control-prev" type="button" data-bs-target="#hero" data-bs-slide="prev" aria-label="Anterior">
      <span class="carousel-control-prev-icon"></span><span class="visually-hidden">Anterior</span>
    </button>
    <button class="carousel-control-next" type="button" data-bs-target="#hero" data-bs-slide="next" aria-label="Próximo">
      <span class="carousel-control-next-icon"></span><span class="visually-hidden">Próximo</span>
    </button>

    <div class="carousel-indicators">
      <?php foreach($slides as $i=>$s): ?>
        <button type="button" data-bs-target="#hero" data-bs-slide-to="<?=$i?>" class="<?=$i===0?'active':''?>" aria-label="Slide <?=$i+1?>"></button>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<?php
function renderBlock($id, $blk, $reverse=false){
  $imgRaw = trim((string)($blk['img'] ?? ''));
  $vidRaw = trim((string)($blk['video'] ?? ''));

  $imgUrl = $imgRaw ? media_with_ver($imgRaw) : '';
  $hasImg = $imgUrl !== '' && is_image($imgRaw);

  $isEmbed  = $vidRaw !== '' && is_embed_url($vidRaw);
  $isFile   = $vidRaw !== '' && is_video($vidRaw);
  $videoUrl = $isEmbed ? to_embed_url($vidRaw) : ($isFile ? media_with_ver($vidRaw) : '');
  $hasVideo = ($videoUrl !== '');
  $videoMime = $hasVideo && !$isEmbed ? video_mime($videoUrl) : '';
  ?>
  <section class="section">
    <div class="container-xxl">
      <div class="row g-4 align-items-center <?=$reverse?'flex-lg-row-reverse':''?>">
        <div class="col-lg-6">
          <div class="media-wrap" data-block="<?=$id?>">
            <?php if($hasImg): ?>
              <img class="media-img" src="<?=h($imgUrl)?>" alt="<?=h($blk['titulo'] ?? '')?> (imagem)" loading="lazy" decoding="async" width="1280" height="720">
            <?php endif; ?>

            <?php if($hasVideo && $isEmbed): ?>
              <iframe class="media-iframe"
                      src="<?=h($videoUrl)?>?rel=0&modestbranding=1"
                      title="<?=h($blk['titulo'] ?? '')?> (vídeo)"
                      allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                      allowfullscreen loading="lazy"></iframe>
            <?php elseif($hasVideo): ?>
              <video class="media-video" controls playsinline preload="metadata" <?= $hasImg ? 'poster="'.h($imgUrl).'"' : '' ?>>
                <source src="<?=h($videoUrl)?>" type="<?=h($videoMime)?>">
                Seu navegador não suporta vídeo HTML5.
              </video>
            <?php endif; ?>

            <div class="media-meta" aria-live="polite">Carregando mídia…</div>
          </div>

          <div class="mt-2 d-flex gap-2 switcher">
            <button class="btn btn-sm btn-warning fw-bold" data-action="show-image" <?=$hasImg?'':'disabled'?>>Imagem</button>
            <button class="btn btn-sm btn-outline-secondary" data-action="show-video" <?=$hasVideo?'':'disabled'?>>Vídeo</button>
          </div>
        </div>

        <div class="col-lg-6">
          <h2 class="fw-bold"><?=h($blk['titulo'] ?? '')?></h2>
          <p class="text-secondary"><?=h($blk['texto'] ?? '')?></p>
          <div class="d-flex gap-2">
            <a class="btn btn-brand fw-bold" href="<?=h(AGENDA)?>">Agendar</a>
            <a class="btn btn-outline-secondary" href="<?=h(WPP)?>" target="_blank" rel="noopener">WhatsApp</a>
          </div>
        </div>
      </div>
    </div>
  </section>
  <?php
}
?>
<?php renderBlock('corte_masculino',    $blocos['corte_masculino'],    false); ?>
<?php renderBlock('corte_feminino',     $blocos['corte_feminino'],     true ); ?>
<?php renderBlock('mechas',             $blocos['mechas'],             false); ?>
<?php renderBlock('escova_progressiva', $blocos['escova_progressiva'], true ); ?>
<?php renderBlock('protese_capilar',    $blocos['protese_capilar'],    true ); ?>

<section class="about_section">
  <div class="container-fluid">
    <div class="row">
      <div class="col-md-6 px-0">
        <div class="img-box">
          <?php
          $videoEmCima  = ($url_video !== '' && trim($posicao_video) === 'sobre');
          $videoEmBaixo = ($url_video !== '' && trim($posicao_video) === 'abaixo');
          if ($videoEmCima) {
            echo '<iframe width="100%" height="350" src="'.htmlspecialchars($url_video).'" title="YouTube video player" frameborder="0" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>';
          } else {
            $imgPath  = "images/{$imagem_sobre}";
            $webpPath = preg_replace('/\.(jpe?g|png)$/i', '.webp', $imgPath);
          ?>
          <picture>
            <?php if (file_exists($webpPath)) : ?>
            <source type="image/webp" srcset="<?= htmlspecialchars($webpPath) ?>">
            <?php endif; ?>
            <img src="<?= htmlspecialchars($imgPath) ?>" alt="Sobre nós" width="600" height="600" loading="lazy" decoding="async" style="width:100%;height:auto;display:block">
          </picture>
          <?php } ?>
        </div>
      </div>
      <div class="col-md-5">
        <div class="detail-box">
          <div class="heading_container">
            <h2 class="" style="color:#FFF">Sobre Nós</h2>
            <h5 class="" style="color:#FFF">✂️ Mais de 25 anos entregando resultado de verdade...</h5>
          </div>
          <p class="detail_p_mt"><?= htmlspecialchars($texto_sobre) ?></p>
          <button type="button" class="btn btn-outline-secondary rounded-pill px-3" data-bs-toggle="modal" data-bs-target="#empresa">Mais Informações</button>
        </div>
      </div>
    </div>
  </div>
</section>

<?php if ($videoEmBaixo) : ?>
<div>
  <iframe class="video_mobile" width="100%" src="<?= htmlspecialchars($url_video) ?>" title="YouTube video player" frameborder="0" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
</div>
<?php endif; ?>

<footer class="footer pt-5 pb-4 mt-4">
  <div class="container-xxl">
    <div class="row g-4">
      <div class="col-lg-5">
        <div class="d-flex align-items-center gap-2 mb-3">
          <picture>
            <?php if(!empty($logoWebpUrl)): ?><source type="image/webp" srcset="<?=h($logoWebpUrl)?>?v=1"><?php endif; ?>
            <img src="<?=h($logoPngUrl)?>?v=1" alt="Jacy Cabeleireiro" width="100" height="42" class="rounded" loading="eager" fetchpriority="high">
          </picture>
          <h5 class="m-0 fw-bold"><?php echo $nome_sistema ?></h5>
        </div>
        <p class="muted mb-0"><?= $texto_rodape ?></p>
      </div>
      <div class="col-lg-4">
        <h6 class="fw-bold mb-3">Links contatos</h6>
        <ul class="list-unstyled small">
          <li class="mb-2"><span aria-hidden="true">📍</span> <?=h($enderecoStr)?></li>
          <li class="mb-2"><span aria-hidden="true">✍️</span> <a href="/assinatura">Assinatura</a></li>
          <li class="mb-2"><span aria-hidden="true">✂️</span> <a href="/servicos" target="_blank" rel="noopener">Serviços</a></li>
          <li class="mb-2"><span aria-hidden="true">👔</span> <a href="/barbearia" target="_blank" rel="noopener">Barbeiro</a></li>
          <li class="mb-2"><span aria-hidden="true">💇‍♀️</span> <a href="/protese-capilar" target="_blank" rel="noopener">Protese</a></li>
        </ul>
      </div>
      <div class="col-lg-3">
        <h6 class="fw-bold mb-3">Cadastre-se</h6>
        <p class="muted small">Solicite uma avaliação gratuita</p>
        <form method="post" action="/sistema/painel/paginas/leads/salvar.php" class="needs-validation" novalidate>
          <div class="mb-2"><input type="tel"  class="form-control form-control-sm" name="whatsapp" placeholder="WhatsApp" required></div>
          <div class="mb-2"><input type="text" class="form-control form-control-sm" name="nome"     placeholder="Nome Completo" required></div>
          <button class="btn btn-warning w-100 btn-sm fw-bold" type="submit">Cadastrar</button>
        </form>
      </div>
    </div>
    <div class="bottom-bar text-center pt-3 mt-4 small">
      © <?=date('Y')?> <?php echo $nome_sistema ?> — (45) 99988-2100 —
      <a class="text-decoration-underline" href="<?=h(AGENDA)?>" target="_blank" rel="noopener">Agendar Online</a>
      · <a class="text-decoration-underline" href="/politica-privacidade">Política de privacidade</a>
      · <a class="text-decoration-underline" href="/termos">Termos de uso</a>
      · <a class="text-decoration-underline" href="/app" target="_blank" rel="noopener">App</a> |
      · <a class="text-decoration-underline" href="<?php echo function_exists('bb_url_sistema') ? bb_url_sistema() : '/sistema/'; ?>" target="_blank" rel="noopener">Admin</a>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>

<script>
document.addEventListener('DOMContentLoaded', function(){
  document.querySelectorAll('#hero .carousel-caption a').forEach(function(a){
    a.addEventListener('click', function(e){ e.stopPropagation(); });
  });
});

(function(){
  const prefersReduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const saveData = navigator.connection && navigator.connection.saveData;
  const hero = document.getElementById('hero'); if(!hero) return;

  function playVisibleVideo(){
    const active = hero.querySelector('.carousel-item.active video[data-autoplay]');
    hero.querySelectorAll('video[data-autoplay]').forEach(function(v){ if(v!==active){ try{v.pause();}catch(_){}} });
    if(active && !prefersReduced && !saveData){
      const p = active.play(); if(p && p.catch) p.catch(function(){});
    }
  }
  hero.addEventListener('slid.bs.carousel', playVisibleVideo, {passive:true});

  const start = function(){ playVisibleVideo(); };
  if('requestIdleCallback' in window){
    requestIdleCallback(function(){
      if('IntersectionObserver' in window){
        const io = new IntersectionObserver(function(es){ 
          es.forEach(function(e){ 
            if(e.isIntersecting){ start(); io.disconnect(); } 
          }); 
        },{threshold:.2});
        io.observe(hero);
      }else{ start(); }
    });
  }else{ window.addEventListener('load', function(){ setTimeout(start, 300); }, {once:true}); }
})();

(function(){
  const boot = function(){
    function fmtRes(w,h){ return (w&&h)? (w+'×'+h+'px') : '—'; }
    function fmtDur(s){ if(!s || !isFinite(s)) return ''; var m=Math.floor(s/60), ss=Math.round(s%60).toString().padStart(2,'0'); return m+':'+ss; }
    document.querySelectorAll('.media-wrap').forEach(function(box){
      var img  = box.querySelector('.media-img');
      var vid  = box.querySelector('.media-video');
      var ifr  = box.querySelector('.media-iframe');
      var meta = box.querySelector('.media-meta');

      function show(which){
        if(which==='image' && img){ img.classList.add('on'); if(vid) vid.classList.remove('on'); if(ifr) ifr.classList.remove('on'); }
        else if(which==='video'){
          if(ifr){ ifr.classList.add('on'); if(vid) vid.classList.remove('on'); }
          else if(vid){ vid.classList.add('on'); }
          if(img) img.classList.remove('on');
        }
        updateMeta();
      }
      function updateMeta(){
        if(ifr && ifr.classList.contains('on')){ meta.textContent='Vídeo (embed)'; }
        else if(vid && vid.classList.contains('on')){ meta.textContent='Vídeo • '+fmtRes(vid.videoWidth,vid.videoHeight)+(fmtDur(vid.duration)?(' • '+fmtDur(vid.duration)):''); }
        else if(img && img.classList.contains('on')){ meta.textContent='Imagem • '+fmtRes(img.naturalWidth,img.naturalHeight); }
        else{ meta.textContent='Mídia indisponível'; }
      }
      if(img){ img.addEventListener('load', updateMeta, {passive:true}); }
      if(vid){ vid.addEventListener('loadedmetadata', updateMeta, {passive:true}); }
      if(ifr){ ifr.addEventListener('load', updateMeta, {passive:true}); }

      if(ifr){ ifr.classList.add('on'); } else if(vid){ vid.classList.add('on'); } else if(img){ img.classList.add('on'); }
      updateMeta();

      var wrap = box.parentElement;
      wrap.querySelectorAll('[data-action]').forEach(function(btn){
        btn.addEventListener('click', function(){
          if(this.disabled) return;
          show(this.getAttribute('data-action')==='show-image' ? 'image' : 'video');
          wrap.querySelectorAll('[data-action]').forEach(function(b){
            if(b===btn){ b.classList.add('btn-warning','fw-bold'); b.classList.remove('btn-outline-secondary'); }
            else{ b.classList.remove('btn-warning','fw-bold'); b.classList.add('btn-outline-secondary'); }
          });
        }, {passive:true});
      });
    });
  };
  if('requestIdleCallback' in window){ requestIdleCallback(boot); } else { window.addEventListener('load', boot, {once:true}); }
})();

(function(){
  const MODAL_ID = 'leadModal';
  const KEY = 'leadPopupLast';
  const HOURS = 12;
  const isBotLike = /Lighthouse|PageSpeed|Headless|bot|crawler/i.test(navigator.userAgent);
  const saveData  = navigator.connection && navigator.connection.saveData;

  function openModal(){
    const el = document.getElementById(MODAL_ID);
    if(!el || !window.bootstrap || !bootstrap.Modal) return;
    bootstrap.Modal.getOrCreateInstance(el).show();
    localStorage.setItem(KEY, String(Date.now()));
  }

  const last = parseInt(localStorage.getItem(KEY)||'0',10);
  const canOpen = !isBotLike && !saveData && (!last || (Date.now() - last) > HOURS*3600*1000);
  if(!canOpen) return;

  let armed = false;
  function armOnce(){
    if(armed) return; armed = true;
    const schedule = function(){ 
      if('requestIdleCallback' in window){ 
        requestIdleCallback(function(){ setTimeout(openModal, 3000); }); 
      } else { 
        setTimeout(openModal, 8000); 
      }
    };
    if(document.visibilityState === 'visible') schedule();
    else document.addEventListener('visibilitychange', function h(){ if(document.visibilityState==='visible'){ document.removeEventListener('visibilitychange', h); schedule(); }}, {once:true});
  }
  ['pointerdown','keydown','touchstart','scroll'].forEach(function(ev){ 
    window.addEventListener(ev, armOnce, {passive:true, once:true}); 
  });
})();
</script>

<!-- Modal lead -->
<div class="modal fade" id="leadModal" tabindex="-1" aria-hidden="true">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="border-radius:16px">
      <div class="modal-header">
        <h5 class="modal-title fw-bold">Receba Avaliação Gratuita</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <form id="leadForm" autocomplete="off">
        <div class="modal-body">
          <div class="rounded-3 p-3 mb-3" style="background:#f6f7fb; border:1px solid #e6e8ef;">
            <div class="d-flex">
              <div style="font-size:22px; line-height:1; margin-right:10px;">💬</div>
              <div class="small" style="color:#3b3f4a;">
                <strong>Converse com um especialista sem custo e sem compromisso.</strong><br>
                Em até alguns minutos respondemos no WhatsApp com <em>horários disponíveis</em> e <em>orientação rápida</em>.
                <ul class="mb-0 mt-2 ps-3"><li>Atendimento rápido e discreto</li><li>Cancelamento a qualquer momento</li><li>Usamos seus dados apenas para contato do salão</li></ul>
              </div>
            </div>
          </div>
          <div class="mb-3">
            <label class="form-label">Nome completo</label>
            <input type="text" class="form-control" name="nome" id="leadNome" maxlength="80" required placeholder="Seu nome completo">
          </div>
          <div class="mb-1">
            <label class="form-label">WhatsApp</label>
            <input type="tel" class="form-control" name="whatsapp" id="leadZap" inputmode="numeric" autocomplete="tel" placeholder="(45) 99999-0000" required>
          </div>
          <div class="form-text">Não cadastramos números repetidos.</div>
          <div id="leadMsg" class="small mt-2 text-center"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Fechar</button>
          <button type="submit" class="btn btn-brand fw-bold" id="leadBtn">Cadastrar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Empresa -->
<div class="modal fade" id="empresa" tabindex="-1" aria-labelledby="empresaLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-centered">
    <div class="modal-content rounded-3 border-0 shadow">
      <div class="modal-header border-0 pb-0">
        <div>
          <h1 class="h4 fw-bold mb-1" id="empresaLabel">Jacy Cabeleireiro</h1>
          <p class="m-0 text-muted small">Formação, técnica e atendimento de excelência em Cascavel e região.</p>
        </div>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Fechar"></button>
      </div>
      <div class="modal-body pt-3">
        <p class="text-secondary">Nossa missão é oferecer os melhores serviços e atendimentos...</p>
        <div class="d-flex flex-wrap gap-2 mb-3">
          <span class="badge rounded-pill text-bg-light">Qualidade de ensino</span>
          <span class="badge rounded-pill text-bg-light">Didática prática</span>
          <span class="badge rounded-pill text-bg-light">Mercado de trabalho</span>
          <span class="badge rounded-pill text-bg-light">Atualização técnica</span>
        </div>
        <div class="row g-4 align-items-start">
          <div class="col-lg-6">
            <div class="p-3 rounded-3" style="background:#f8f9fb;border:1px solid #eef0f4;">
              <p class="small text-secondary mb-2">Buscamos atender com <strong>qualidade</strong> e <strong>agilidade</strong>...</p>
              <p class="small text-secondary mb-2"><em>"Eu não tenho dúvida de que você consegue aprender..."</em></p>
              <ul class="small text-secondary ps-3 mb-0"><li>Metodologia voltada ao mercado</li><li>Conteúdo técnico atualizado</li><li>Acompanhamento próximo</li><li>Preços justos e condições acessíveis</li></ul>
            </div>
          </div>
          <div class="col-lg-6">
            <div class="ratio ratio-16x9 rounded-3 overflow-hidden">
              <iframe src="https://www.youtube-nocookie.com/embed/E_tBbvQJ1mA?rel=0&modestbranding=1"
                title="Apresentação — Jacy Cabeleireiro" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share"
                allowfullscreen loading="lazy"></iframe>
            </div>
            <p class="mt-2 small text-muted">Assista para entender como trabalhamos... <strong>Dados:</strong> Jacy Cordeiro 01702950956 · CNPJ 36.896.614/0001-40 · NIRE 41 8 099789-3</p>
          </div>
        </div>
        <figure class="text-center my-3">
          <blockquote class="blockquote mb-1"><p class="fst-italic mb-0" style="font-size:1.05rem; color:#212529;">"As muitas águas não podem apagar este amor..."</p></blockquote>
          <figcaption class="blockquote-footer mb-0 text-secondary">Cantares 8:7</figcaption>
        </figure>
      </div>
      <div class="modal-footer border-0 pt-0">
        <a href="/agendamentos" class="btn btn-primary rounded-pill px-4">Agendar atendimento</a>
        <a href="https://api.whatsapp.com/send?phone=5545999882100" target="_blank" rel="noopener" class="btn btn-outline-secondary rounded-pill px-4">Falar no WhatsApp</a>
        <button type="button" class="btn btn-light rounded-pill px-4" data-bs-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>
</body>
</html>
