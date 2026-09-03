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
require_once __DIR__ . '/security.php';

/* ========= HELPERS ========= */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function only_digits($s){ return preg_replace('/\D+/', '', (string)$s); }
function is_http_url($u){ return (bool)preg_match('#^(https?:)?//#i', trim((string)$u)); }
function is_video($p){ $path = parse_url((string)$p, PHP_URL_PATH) ?: (string)$p; return (bool)preg_match('/\.(mp4|webm|ogg)$/i', $path); }
function is_image($p){ $path = parse_url((string)$p, PHP_URL_PATH) ?: (string)$p; return (bool)preg_match('/\.(jpe?g|png|gif|webp|svg)$/i', $path); }
function is_media_file($p){ return is_image($p) || is_video($p); }

/* ========= ⚡ NOVA FUNÇÃO (PageSpeed) ========= */
/**
 * Tenta encontrar uma versão "-mobile" de uma imagem.
 * Ex: 'banner.jpg' -> 'banner-mobile.jpg'
 * Retorna o caminho original se a versão mobile não for encontrada.
 */
function get_mobile_version($path) {
  $path = (string)$path;
  if($path === '' || is_http_url($path)) return $path;

  $info = pathinfo($path);
  $ext = $info['extension'] ?? '';
  $dir = $info['dirname'] ?? '.';
  $name = $info['filename'] ?? '';

  if ($ext === '' || $name === '') return $path; // Caminho inválido

  $mobile_name = $name . '-mobile';
  $mobile_path = ($dir === '.' ? '' : $dir . '/') . $mobile_name . '.' . $ext;

  // Verifica se o arquivo físico existe no servidor
  if(is_file($_SERVER['DOCUMENT_ROOT'] . $mobile_path)) {
    return $mobile_path;
  }

  return $path; // Retorna o original se o mobile não existir
}

/* ========= EMBEDS ========= */
function is_youtube_url($u){ return (bool)preg_match('#(youtu\.be/|youtube\.com/(watch|shorts|embed))#i',$u); }
function yt_to_embed($u){
  $u = trim($u);
  if(preg_match('#youtu\.be/([A-Za-z0-9_-]{6,})#i',$u,$m)) return 'https://www.youtube-nocookie.com/embed/'.$m[1];
  if(preg_match('#youtube\.com/(watch\?v=|shorts/)([A-Za-z0-9_-]{6,})#i',$u,$m)) return 'https://www.youtube-nocookie.com/embed/'.$m[2];
  if(preg_match('#youtube\.com/embed/([A-Za-z0-9_-]{6,})#i',$u,$m)) return 'https://www.youtube-nocookie.com/embed/'.$m[1];
  return $u;
}
function is_vimeo_url($u){ return (bool)preg_match('#(vimeo\.com/\d+|player\.vimeo\.com/video/\d+)#i',$u); }
function vimeo_to_embed($u){
  if(preg_match('#vimeo\.com/(\d+)#i',$u,$m)) return 'https://player.vimeo.com/video/'.$m[1];
  if(preg_match('#player\.vimeo\.com/video/(\d+)#i',$u,$m)) return 'https://player.vimeo.com/video/'.$m[1];
  return $u;
}
function to_embed_url($u){ if(is_youtube_url($u)) return yt_to_embed($u); if(is_vimeo_url($u)) return vimeo_to_embed($u); return $u; }
function video_mime($u){
  $p = parse_url((string)$u, PHP_URL_PATH) ?: (string)$u; $e = strtolower(pathinfo($p, PATHINFO_EXTENSION));
  return $e==='webm' ? 'video/webm' : (($e==='ogg'||$e==='ogv') ? 'video/ogg' : 'video/mp4');
}

/* ========= SCHEMA/SQL HELPERS ========= */
function table_exists(?PDO $pdo, $name){ if(!$pdo) return false; try{$st=$pdo->prepare('SHOW TABLES LIKE :t');$st->execute([':t'=>$name]);return (bool)$st->fetch(PDO::FETCH_NUM);}catch(Throwable $e){return false;}}
function col_exists(PDO $pdo,$table,$col){ try{$st=$pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :c");$st->execute([':c'=>$col]);return (bool)$st->fetch(PDO::FETCH_ASSOC);}catch(Throwable $e){return false;} }
function any_col_exists(PDO $pdo,$table,array $cols){ foreach($cols as $c){ if(col_exists($pdo,$table,$c)) return true; } return false; }
function coalesce_expr(PDO $pdo, $table, $cands, $alias){
  $ok = array_values(array_filter($cands, function($c) use ($pdo,$table){ return col_exists($pdo,$table,$c); }));
  if($ok){ $cols = implode(',', array_map(function($c){ return "`{$c}`"; }, $ok)); return "COALESCE({$cols}) AS {$alias}"; }
  return "NULL AS {$alias}";
}

/* ========= URLS ========= */
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
  $out = [
    'agenda_url'  => 'https://jacycabeleireiro.com/agendamentos',
    'whatsapp'    => '5545999882100',
    'endereco'    => 'Rio Grande do Sul, 2151',
    'uploads_base'=> '/sistema/painel/paginas/site/uploads_banners/',
    'logo_png'    => '/sistema/img/logo.png',
    'logo_webp'   => '/sistema/img/logo.webp',
  ];
  if(!$pdo) return $out;

  $kv = ['settings','config','configs','parametros','opcoes','site_config','configuracoes'];
  foreach($kv as $t){
    if(!table_exists($pdo,$t)) continue;
    if(!any_col_exists($pdo,$t,['chave','key','nome']) || !any_col_exists($pdo,$t,['valor','value','conteudo','texto'])) continue;
    try{
      $k = coalesce_expr($pdo,$t,['chave','key','nome','slug'],'k');
      $v = coalesce_expr($pdo,$t,['valor','value','conteudo','texto','dados'],'v');
      $rows = $pdo->query("SELECT {$k},{$v} FROM `$t`")->fetchAll(PDO::FETCH_ASSOC);
      foreach($rows as $r){
        $key = strtolower(trim((string)($r['k']??''))); if($key==='') continue;
        $val = trim((string)($r['v']??''));
        if($key==='agenda' || $key==='agenda_url') $out['agenda_url']=$val;
        elseif($key==='wpp' || $key==='whatsapp') $out['whatsapp']=$val;
        elseif($key==='endereco' || $key==='endereço' || $key==='address') $out['endereco']=$val;
        elseif($key==='uploads' || $key==='uploads_base' || $key==='upload_dir') if($val!=='') $out['uploads_base']=$val;
        elseif($key==='logo_png' || $key==='logo') $out['logo_png']=$val;
        elseif($key==='logo_webp') $out['logo_webp']=$val;
      }
      return $out;
    }catch(Throwable $e){}
  }
  $single=['site_config','configuracoes','empresa','institucional','config_site'];
  foreach($single as $t){
    if(!table_exists($pdo,$t)) continue;
    try{
      $agenda = coalesce_expr($pdo,$t,['agenda','agenda_url','link_agenda'],'agenda_url');
      $wpp    = coalesce_expr($pdo,$t,['whatsapp','wpp','telefone'],'whatsapp');
      $end    = coalesce_expr($pdo,$t,['endereco','endereço','address'],'endereco');
      $up     = coalesce_expr($pdo,$t,['uploads_base','uploads','upload_dir'],'uploads_base');
      $lpng   = coalesce_expr($pdo,$t,['logo_png','logo'],'logo_png');
      $lwebp  = coalesce_expr($pdo,$t,['logo_webp'],'logo_webp');
      $r = $pdo->query("SELECT {$agenda},{$wpp},{$end},{$up},{$lpng},{$lwebp} FROM `$t` LIMIT 1")->fetch(PDO::FETCH_ASSOC);
      if($r){ foreach($r as $k=>$v){ if($v!==null && $v!=='') $out[$k]=$v; } return $out; }
    }catch(Throwable $e){}
  }
  return $out;
}
function wpp_url_from($raw){ $n=only_digits($raw); if($n==='') return 'https://wa.me/5545999882100'; if(strpos($n,'55')!==0) $n='55'.$n; return 'https://wa.me/'.$n; }

/* ========= SETTINGS + CONST ========= */
$SET = loadSettings($pdo);
$uploadsBase = rtrim((string)$SET['uploads_base'],'/').'/';
$agendaUrl   = (string)$SET['agenda_url'];
$wppUrl      = wpp_url_from($SET['whatsapp']);
$logoPngUrl  = (string)$SET['logo_png'];
$logoWebpUrl = (string)$SET['logo_webp'];
$enderecoStr = (string)$SET['endereco'];
if(!defined('UP'))      define('UP', $uploadsBase);
if(!defined('SUP'))     define('SUP', '/sistema/painel/paginas/site/uploads_blocos');
if(!defined('AGENDA')) define('AGENDA', $agendaUrl);
if(!defined('WPP'))    define('WPP', $wppUrl);

/* ========= PATH HELPERS ========= */
function resolve_media($s){
  $s = trim((string)$s); if($s==='') return '';
  if(preg_match('#^(https?:)?//#',$s) || $s[0]==='/') return $s;
  $sup = rtrim((string)SUP,'/').'/'.$s; $supFs=$_SERVER['DOCUMENT_ROOT'].$sup;
  if(is_file($supFs)) return $sup;
  $up  = rtrim((string)UP,'/').'/'.$s;  $upFs =$_SERVER['DOCUMENT_ROOT'].$up;
  return is_file($upFs) ? $up : $sup;
}
function media_with_ver($url){
  $u=(string)$url; if(is_http_url($u)) return $u;
  if($u!=='' && $u[0]!=='/') $u=resolve_media($u);
  if($u==='') return $u; $fs=$_SERVER['DOCUMENT_ROOT'].$u; $v=@filemtime($fs);
  return $u.($v ? ('?v='.$v) : '');
}

/* ========= CONTEÚDO PADRÃO ========= */
$nome_sistema = isset($nome_sistema) ? $nome_sistema : 'Jacy Cabeleireiro';
$icone_site   = isset($icone_site) ? $icone_site : 'favicon.ico';
$texto_rodape = isset($texto_rodape) ? $texto_rodape : 'Atendimento profissional com técnica e dedicação.';
$imagem_sobre = isset($imagem_sobre) ? $imagem_sobre : 'sobre.jpg';
$texto_sobre  = isset($texto_sobre) ? $texto_sobre : 'Com mais de 25 anos de experiência, oferecemos os melhores serviços de cabeleireiro.';
$url_video    = isset($url_video) ? $url_video : '';
$posicao_video= isset($posicao_video) ? $posicao_video : '';

/* ========= SLIDES ========= */
function loadSlides(?PDO $pdo){
  $fallback = [[ 'img'=>UP.'banner-3.jpg', 'video'=>'', 'titulo'=>'CORTES PROFISSIONAIS', 'texto'=>'Você merece o melhor. Conte com especialistas.', 'link'=>'' ]];
  if(!$pdo) return $fallback;
  $table = table_exists($pdo,'site_banners_data') ? 'site_banners_data'
         : (table_exists($pdo,'site_banners') ? 'site_banners'
         : (table_exists($pdo,'arquivos') ? 'arquivos' : null));
  if(!$table) return $fallback;
  try{
    $isData = ($table==='site_banners_data');
    $midia  = $isData ? coalesce_expr($pdo,$table,['arquivo','src','caminho','midia'],'src')
                       : coalesce_expr($pdo,$table,['arquivo','src','caminho','link','url'],'src');
    $titulo = coalesce_expr($pdo,$table,['titulo','nome','legenda'],'titulo');
    $texto  = coalesce_expr($pdo,$table,['texto','descricao','conteudo','caption'],'texto');
    $cta    = $isData ? coalesce_expr($pdo,$table,['btn_url','cta_url','link_url','href','url','link','arquivo_url'],'cta')
                       : coalesce_expr($pdo,$table,['btn_url','cta_url','link_url','href','arquivo_url','url','link'],'cta');
    $ordem  = col_exists($pdo,$table,'ordem')?'ordem':(col_exists($pdo,$table,'posicao')?'posicao':'id');
    $wheres = [];
    if(col_exists($pdo,$table,'ativo'))    $wheres[]='COALESCE(ativo,1)=1';
    if(col_exists($pdo,$table,'registro')) $wheres[]="registro IN ('home','site','index','banner_home')";
    $whereSql = $wheres?(' WHERE '.implode(' AND ',$wheres)):''; 
    $rows = $pdo->query("SELECT {$titulo},{$texto},{$midia},{$cta} FROM `{$table}`{$whereSql} ORDER BY {$ordem} ASC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC);
    $out=[];
    foreach($rows as $r){
      $src = resolve_media($r['src']??''); if($src==='') continue;
      $ctaRaw = trim((string)($r['cta']??'')); $ctaUse='';
      if($ctaRaw!=='' && $ctaRaw!=='#'){ $norm=canonical_link($ctaRaw); if($norm!=='' && !is_media_file($norm)) $ctaUse=$norm; }
      $item=['img'=>'','video'=>'','titulo'=>trim((string)($r['titulo']??'')),'texto'=>trim((string)($r['texto']??'')),'link'=>$ctaUse];
      if(is_video($src)) $item['video']=$src; else $item['img']=$src;
      $out[]=$item;
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
    $status = col_exists($pdo,$table,'ativo') ? 'COALESCE(ativo,1)=1' : (col_exists($pdo,$table,'status')?'COALESCE(status,1)=1':'1=1');
    $slugCol= col_exists($pdo,$table,'categoria') ? 'categoria' : (col_exists($pdo,$table,'slug') ? 'slug' : null);
    if(!$slugCol) return $def;

    $st=$pdo->prepare("SELECT {$titulo},{$texto},{$img},{$video} FROM `$table` WHERE {$status} AND {$slugCol}=:s LIMIT 1");
    $st->execute([':s'=>$slug]); $r=$st->fetch(PDO::FETCH_ASSOC); if(!$r) return $def;

    $imgDb   = trim((string)($r['img']??''));   $img = $imgDb!=='' ? $imgDb : $def['img'];
    $vidDb   = trim((string)($r['video']??'')); $vid = $vidDb!=='' ? $vidDb : $def['video'];

    if($img!=='' && !is_http_url($img) && $img[0]!=='/')  $img = resolve_media($img);
    if($vid!==''){
      if(!(is_youtube_url($vid)||is_vimeo_url($vid)) && !is_http_url($vid) && $vid[0]!=='/') $vid = resolve_media($vid);
    }

    return ['titulo'=>($r['titulo']?:$def['titulo']),'texto'=>($r['texto']?:$def['texto']),'img'=>$img,'video'=>$vid];
  }catch(Throwable $e){ return $def; }
}

/* ========= DADOS ========= */
$slides = loadSlides($pdo);

/* ========= ⚡ MUDANÇA 1 (PageSpeed) - Buscar imagens mobile p/ preload ========= */
$preloadHero_Desktop = (!empty($slides[0]['img'])) ? media_with_ver($slides[0]['img']) : '';
$preloadHero_Mobile = $preloadHero_Desktop ? media_with_ver(get_mobile_version($slides[0]['img'])) : '';
// Fallback: se mobile for igual a desktop, não usamos no srcset
if ($preloadHero_Mobile === $preloadHero_Desktop) {
    $preloadHero_Mobile = '';
}

$blocos = [
  'corte_masculino' => loadBlock($pdo,'corte_masculino',['titulo'=>'Corte Masculino','texto'=>'Cortes alinhados ao seu estilo, com acabamento impecável.','img'=>'corte-masculino.jpg','video'=>'corte-masculino.mp4']),
  'corte_feminino'  => loadBlock($pdo,'corte_feminino', ['titulo'=>'Corte Feminino','texto'=>'Técnica e cuidado para valorizar seus traços.','img'=>'corte-feminino.jpg','video'=>'corte-feminino.mp4']),
  'mechas'          => loadBlock($pdo,'mechas',         ['titulo'=>'Mechas','texto'=>'Luzes e balayage com preservação de fibra e brilho intenso.','img'=>'mechas.jpg','video'=>'mechas.mp4']),
  'escova_progressiva'=>loadBlock($pdo,'escova_progressiva',['titulo'=>'Escova Progressiva','texto'=>'Redução de volume e alinhamento, respeitando a saúde do fio.','img'=>'escova-progressiva.jpg','video'=>'escova-progressiva.mp4']),
  'protese_capilar' => loadBlock($pdo,'protese_capilar',['titulo'=>'Prótese Capilar','texto'=>'Aparência natural, fixação segura e manutenção planejada.','img'=>'protese.jpg','video'=>'protese.mp4']),
];
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<link rel="shortcut icon" href="images/<?=h($icone_site)?>" type="image/x-icon">
<title><?=h($nome_sistema)?> — Corte de Cabelo Profissional | Cascavel-PR</title>
<meta name="description" content="Cortes profissionais, prótese capilar, mechas e progressivas. +25 anos de experiência. Técnica, higiene e acabamento impecável em Cascavel-PR.">
<meta name="author" content="Jacy Cordeiro">
<meta name="theme-color" content="#000000">

<link rel="preconnect" href="https://cdn.jsdelivr.net" crossorigin>
<link rel="preconnect" href="https://www.google.com" crossorigin>

<?php /* ========= ⚡ MUDANÇA 2 (PageSpeed) - Preload LCP com srcset ========= */ ?>
<?php if ($preloadHero_Desktop && is_image($slides[0]['img'])): ?>
<link rel="preload" as="image" 
      href="<?=h($preloadHero_Desktop)?>" 
      fetchpriority="high" 
      <?php if ($preloadHero_Mobile): ?>
      imagesrcset="<?=h($preloadHero_Mobile)?> 800w, <?=h($preloadHero_Desktop)?> 1920w" 
      <?php else: ?>
      imagesrcset="<?=h($preloadHero_Desktop)?> 1920w" 
      <?php endif; ?>
      imagesizes="100vw">
<?php endif; ?>

<style><?php echo ':root{--bg:#0d0d0d;--bg2:#1a1a1a;--bg3:#262626;--txt:#fff;--txt2:#e0e0e0;--muted:#a0a0a0;--brand:#28a745;--brand2:#32cb52}*{box-sizing:border-box;margin:0;padding:0}html,body{background:var(--bg);color:var(--txt);overflow-x:hidden;font-family:system-ui,-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,sans-serif;line-height:1.6;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale}a{color:var(--txt);text-decoration:none;transition:color .2s}a:hover{color:var(--brand2)}img,video{max-width:100%;height:auto;display:block;image-rendering:-webkit-optimize-contrast}';echo'.navbar{background:rgba(13,13,13,.95)!important;backdrop-filter:blur(8px);border-bottom:1px solid rgba(255,255,255,.1);position:sticky;top:0;z-index:1000;will-change:transform}.navbar .nav-link,.navbar .navbar-brand{color:#fff!important;font-weight:600}.navbar .nav-link:hover{color:var(--brand2)!important}.btn-brand{background:var(--brand);border:0;font-weight:700;color:#fff;padding:8px 20px;border-radius:6px;display:inline-block;transition:all .3s;will-change:transform}.btn-brand:hover{background:var(--brand2);transform:translateY(-2px)}';echo'.hero{width:100vw;max-width:100vw;position:relative;contain:layout}.carousel-item{aspect-ratio:16/9;max-height:720px;position:relative;contain:paint}.carousel-item img,.carousel-item video{width:100%;height:100%;object-fit:cover}.section{padding:48px 16px;background:var(--bg);contain:layout}.container-xxl{max-width:1400px;margin:0 auto;padding:0 16px}h1,h2,h3,h4,h5,h6{color:var(--txt);font-weight:700;line-height:1.2}p{color:var(--txt2);line-height:1.7}.carousel-caption{text-align:left;left:10%;right:auto;bottom:8%;background:linear-gradient(90deg,rgba(13,13,13,.85),rgba(13,13,13,.3));padding:18px 22px;border-radius:12px;z-index:5;border:1px solid rgba(255,255,255,.1);backdrop-filter:blur(6px)}.carousel-caption h3{color:#fff;text-shadow:0 2px 4px rgba(0,0,0,.3);font-size:clamp(1.2rem,3vw,2rem)}.carousel-caption p{color:#e0e0e0;font-size:clamp(.9rem,2vw,1.1rem)}'; ?></style>

<link rel="preload" as="style" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" onload="this.onload=null;this.rel='stylesheet'">
<noscript><link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css"></noscript>

<style>
.media-wrap{position:relative;border-radius:18px;overflow:hidden;background:#000;aspect-ratio:16/9;border:1px solid rgba(255,255,255,.1);contain:paint}.media-wrap img,.media-wrap video{width:100%;height:100%;object-fit:cover;display:none}.media-wrap iframe{width:100%;height:100%;display:none;border:0}.media-wrap .on{display:block!important}.media-meta{position:absolute;left:10px;bottom:10px;background:rgba(13,13,13,.85);backdrop-filter:blur(6px);border:1px solid rgba(255,255,255,.15);padding:6px 12px;border-radius:999px;font-size:12px;color:#fff;font-weight:500}.footer{background:var(--bg2);border-top:1px solid rgba(255,255,255,.1)}.footer .muted{color:var(--muted)}.footer h5,.footer h6{color:#fff}.footer a{color:var(--txt2);transition:color .3s}.footer a:hover{color:var(--brand)}#hero .carousel-control-prev,#hero .carousel-control-next{width:8%;will-change:opacity}#hero .carousel-indicators{margin:0 0 10px 0}#hero .carousel-indicators button{background:rgba(255,255,255,.5);border:1px solid rgba(255,255,255,.3);transition:all .3s}#hero .carousel-indicators button.active{background:var(--brand);border-color:var(--brand)}.btn-outline-secondary{color:#fff;border-color:rgba(255,255,255,.3);background:transparent;transition:all .3s}.btn-outline-secondary:hover{background:rgba(255,255,255,.1);border-color:rgba(255,255,255,.5);color:#fff}.btn-warning{background:#ffc107;border:0;color:#000;font-weight:600;transition:all .3s}.btn-warning:hover{background:#ffcd39;color:#000}.card,.modal-content{background:var(--bg2);border:1px solid rgba(255,255,255,.1);color:var(--txt)}.modal-header{border-bottom:1px solid rgba(255,255,255,.1)}.modal-footer{border-top:1px solid rgba(255,255,255,.1)}.form-control,.form-select{background:var(--bg3);border:1px solid rgba(255,255,255,.2);color:#fff;transition:all .2s}.form-control:focus,.form-select:focus{background:var(--bg3);border-color:var(--brand);color:#fff;box-shadow:0 0 0 .25rem rgba(40,167,69,.25)}.form-control::placeholder{color:var(--muted)}.dropdown-menu{background:var(--bg2);border:1px solid rgba(255,255,255,.15)}.dropdown-item{color:var(--txt2);transition:all .3s}.dropdown-item:hover{background:rgba(40,167,69,.15);color:var(--brand)}.dropdown-divider{border-color:rgba(255,255,255,.1)}.text-secondary{color:var(--txt2)!important}.text-muted{color:var(--muted)!important}.small{color:var(--txt2)}.badge{font-weight:500}.text-bg-light{background:rgba(255,255,255,.15)!important;color:#fff!important}.navbar-toggler{border-color:rgba(255,255,255,.3)}.navbar-toggler:focus{box-shadow:0 0 0 .25rem rgba(255,255,255,.1)}.grecaptcha-badge{z-index:2147483647;opacity:.8;transition:opacity .3s}.grecaptcha-badge:hover{opacity:1}
</style>

<?php
  $___rec_file = $_SERVER['DOCUMENT_ROOT'].'/config/recaptcha.php';
  if (is_file($___rec_file)) { require_once $___rec_file; }
  $___site_key = defined('RECAPTCHA_SITE_KEY') ? RECAPTCHA_SITE_KEY : '';
?>
<script>window.RECAPTCHA_SITE_KEY="<?= h($___site_key) ?>";</script>
<script src="https://www.google.com/recaptcha/api.js?render=<?= h($___site_key) ?>" async></script>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark">
<div class="container-xxl">
<a class="navbar-brand d-flex align-items-center gap-2" href="/" aria-label="<?=h($nome_sistema)?>">
<picture>
<?php if(!empty($logoWebpUrl)): ?><source type="image/webp" srcset="<?=h($logoWebpUrl)?>?v=1"><?php endif; ?>
<img src="<?=h($logoPngUrl)?>?v=1" alt="<?=h($nome_sistema)?>" width="100" height="42" class="rounded" loading="eager" fetchpriority="high" decoding="async">
</picture>
<span class="fw-bold"><?=h($nome_sistema)?></span>
</a>
<button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#nav" aria-label="Menu de navegação" aria-expanded="false"><span class="navbar-toggler-icon"></span></button>
<div class="collapse navbar-collapse" id="nav">
<ul class="navbar-nav ms-auto align-items-lg-center gap-lg-2">
<li class="nav-item"><a class="nav-link" href="/agendamentos">📅 Agendamentos</a></li>
<li class="nav-item"><a class="nav-link" href="/assinatura">✍️ Assinaturas</a></li>
<li class="nav-item dropdown">
<a class="nav-link dropdown-toggle" href="#" role="button" data-bs-toggle="dropdown" aria-expanded="false">✂️ Serviços</a>
<ul class="dropdown-menu dropdown-menu-dark">
<li><a class="dropdown-item" href="#">Serviços</a></li><li><hr class="dropdown-divider"></li>
<li><a class="dropdown-item" href="#" target="_blank" rel="noopener">Barbeiro</a></li>
<li><a class="dropdown-item" href="/protese" target="_blank" rel="noopener">Prótese Capilar</a></li>
<li><a class="dropdown-item" href="/corte-cabelo" target="_blank" rel="noopener">Corte Cabelo</a></li>
</ul>
</li>
<li class="nav-item"><a class="nav-link" href="/produtos">🛍️ Produtos</a></li>
<li class="nav-item"><a class="nav-link" href="<?php echo function_exists('bb_url_acesso') ? bb_url_acesso() : '/sistema/acesso'; ?>" target="_blank" rel="noopener">🔒 Acessar</a></li>
<li class="nav-item ms-lg-2"><a class="btn btn-brand btn-sm" href="<?=h(AGENDA)?>">Agendar</a></li>
</ul>
</div>
</div>
</nav>

<div class="container-fluid px-0">
<div id="hero" class="carousel slide hero overflow-hidden" data-bs-ride="false" data-bs-interval="6000">
<div class="carousel-inner">
<?php foreach($slides as $i=>$s):
  $first=($i===0);

  /* ========= ⚡ MUDANÇA 3 (PageSpeed) - Buscar img mobile e desktop p/ <img> ========= */
  $img_desktop = $s['img'] ? media_with_ver($s['img']) : '';
  $img_mobile  = $img_desktop ? media_with_ver(get_mobile_version($s['img'])) : '';
  $vid         = $s['video'] ? media_with_ver($s['video']) : '';

  // Fallback: se mobile for igual a desktop, não usamos
  if ($img_mobile === $img_desktop) {
      $img_mobile = '';
  }
?>
<div class="carousel-item <?=$first?'active':''?>">
<?php if($vid): ?>
<video src="<?=h($vid)?>" <?= $img_desktop ? 'poster="'.h($img_desktop).'"' : '' ?> preload="none" muted playsinline loop data-autoplay="1" width="1920" height="1080" <?= $first ? '' : 'loading="lazy"' ?>></video>
<?php elseif($img_desktop): ?>
<img 
  src="<?=h($img_desktop)?>" 
  <?php if ($img_mobile): ?>
  srcset="<?=h($img_mobile)?> 800w, <?=h($img_desktop)?> 1920w" 
  <?php else: ?>
  srcset="<?=h($img_desktop)?> 1920w"
  <?php endif; ?>
  sizes="100vw"
  alt="<?=h($s['titulo']?:'Banner')?>" 
  decoding="async" 
  <?= $first ? 'fetchpriority="high"' : 'loading="lazy"' ?> 
  width="1920" 
  height="1080">
<?php endif; ?>

<?php if(($s['titulo']??'')||($s['texto']??'')||(!empty($s['link']))): ?>
<div class="carousel-caption">
<?php if($s['titulo']): ?><h3 class="fw-bold mb-1"><?=h($s['titulo'])?></h3><?php endif; ?>
<?php if($s['texto']):  ?><p class="mb-2"><?=h($s['texto'])?></p><?php endif; ?>
<?php if(!empty($s['link'])): ?><a class="btn btn-brand btn-sm" href="<?=h($s['link'])?>" rel="noopener">Saber mais</a><?php endif; ?>
</div>
<?php endif; ?>
</div>
<?php endforeach; ?>
</div>
<?php if(count($slides) > 1): ?>
<button class="carousel-control-prev" type="button" data-bs-target="#hero" data-bs-slide="prev" aria-label="Slide anterior"><span class="carousel-control-prev-icon" aria-hidden="true"></span></button>
<button class="carousel-control-next" type="button" data-bs-target="#hero" data-bs-slide="next" aria-label="Próximo slide"><span class="carousel-control-next-icon" aria-hidden="true"></span></button>
<div class="carousel-indicators">
<?php foreach($slides as $i=>$s): ?><button type="button" data-bs-target="#hero" data-bs-slide-to="<?=$i?>" class="<?=$i===0?'active':''?>" aria-label="Slide <?=$i+1?>" <?=$i===0?'aria-current="true"':''?>></button><?php endforeach; ?>
</div>
<?php endif; ?>
</div>
</div>

<?php
/* ======== RENDER DE BLOCO OTIMIZADO ======== */
function renderBlock($id,$blk,$reverse=false){
  // Otimização de srcset/sizes para os blocos (passo secundário, mas importante)
  $imgRaw=trim((string)($blk['img']??''));
  $imgRaw_m = $imgRaw ? get_mobile_version($imgRaw) : '';

  $imgUrl=$imgRaw?media_with_ver($imgRaw):'';
  $imgUrl_m = $imgRaw_m ? media_with_ver($imgRaw_m) : '';
  if($imgUrl_m === $imgUrl) $imgUrl_m = ''; // Não usa se for igual

  $hasImg=$imgUrl!=='';

  $vidRaw=trim((string)($blk['video']??''));
  $isEmb=(is_youtube_url($vidRaw)||is_vimeo_url($vidRaw));
  $isFile=$vidRaw!=='' && is_video($vidRaw);
  $videoUrl=$isEmb?to_embed_url($vidRaw):($isFile?media_with_ver($vidRaw):'');
  $hasVideo=($videoUrl!=='');
  $mime=$hasVideo&&$isFile?video_mime($videoUrl):'';

  $showImageFirst= $hasImg && !$hasVideo;
  $showVideoFirst= $hasVideo;
?>
<section class="section" id="<?=$id?>">
<div class="container-xxl">
<div class="row g-4 align-items-center <?=$reverse?'flex-lg-row-reverse':''?>">
<div class="col-lg-6">
<div class="media-wrap" data-block="<?=$id?>">
<?php if($hasImg): ?>
<img class="media-img <?=$showImageFirst?'on':''?>" 
  src="<?=h($imgUrl)?>" 
  <?php if ($imgUrl_m): ?>
  srcset="<?=h($imgUrl_m)?> 800w, <?=h($imgUrl)?> 1280w"
  <?php else: ?>
  srcset="<?=h($imgUrl)?> 1280w"
  <?php endif; ?>
  sizes="(max-width: 991px) 100vw, 50vw"
  alt="<?=h($blk['titulo']??'')?>" 
  loading="lazy" 
  decoding="async" 
  width="1280" 
  height="720">
<?php endif; ?>
<?php if($hasVideo && $isEmb): ?>
<iframe class="media-iframe <?=$showVideoFirst?'on':''?>" data-src="<?=h($videoUrl)?>?rel=0&modestbranding=1" title="<?=h($blk['titulo']??'')?>" allow="accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture;web-share" allowfullscreen loading="lazy"></iframe>
<?php elseif($hasVideo): ?>
<video class="media-video <?=$showVideoFirst?'on':''?>" controls playsinline preload="none" <?= $hasImg ? 'poster="'.h($imgUrl).'"' : '' ?> loading="lazy" width="1280" height="720"><source data-src="<?=h($videoUrl)?>" type="<?=h($mime)?>"></video>
<?php endif; ?>
<div class="media-meta" aria-live="polite">Mídia</div>
</div>
<div class="mt-2 d-flex gap-2">
<button class="btn btn-sm <?=$showImageFirst?'btn-warning':'btn-outline-secondary'?>" data-action="show-image" <?=$hasImg?'':'disabled'?> aria-label="Mostrar imagem">Imagem</button>
<button class="btn btn-sm <?=$showVideoFirst?'btn-warning':'btn-outline-secondary'?>" data-action="show-video" <?=$hasVideo?'':'disabled'?> aria-label="Mostrar vídeo">Vídeo</button>
</div>
</div>
<div class="col-lg-6">
<h2 class="fw-bold mb-3"><?=h($blk['titulo']??'')?></h2>
<p class="text-secondary mb-3"><?=h($blk['texto']??'')?></p>
<div class="d-flex gap-2 flex-wrap">
<a class="btn btn-brand fw-bold" href="<?=h(AGENDA)?>">Agendar Agora</a>
<a class="btn btn-outline-secondary" href="<?=h(WPP)?>" target="_blank" rel="noopener">WhatsApp</a>
</div>
</div>
</div>
</div>
</section>
<?php } ?>

<?php renderBlock('corte_masculino',$blocos['corte_masculino'],false); ?>
<?php renderBlock('corte_feminino',$blocos['corte_feminino'],true); ?>
<?php renderBlock('mechas',$blocos['mechas'],false); ?>
<?php renderBlock('escova_progressiva',$blocos['escova_progressiva'],true); ?>
<?php renderBlock('protese_capilar',$blocos['protese_capilar'],false); ?>

<section class="section" id="sobre">
<div class="container-xxl">
<div class="row g-4 align-items-center">
<div class="col-md-6">
<?php
  $videoEmCima  = ($url_video !== '' && trim($posicao_video) === 'sobre');
  $videoEmBaixo = ($url_video !== '' && trim($posicao_video) === 'abaixo');
  if ($videoEmCima) {
    $embed = to_embed_url($url_video);
    echo '<div class="ratio ratio-16x9 rounded-3 overflow-hidden"><iframe data-src="'.h($embed).'?rel=0&modestbranding=1" title="Vídeo institucional" allow="accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture;web-share" loading="lazy" allowfullscreen></iframe></div>';
  } else {
    $imgPath   = "images/{$imagem_sobre}";
    $webpPath = preg_replace('/\.(jpe?g|png)$/i', '.webp', $imgPath);
?>
<picture class="d-block rounded-3 overflow-hidden">
<?php if (file_exists($webpPath)) : ?><source type="image/webp" srcset="<?=h($webpPath)?>"><?php endif; ?>
<img src="<?=h($imgPath)?>" alt="Sobre Jacy Cabeleireiro" width="1200" height="675" loading="lazy" decoding="async" class="img-fluid">
</picture>
<?php } ?>
</div>
<div class="col-md-6">
<h2 class="fw-bold mb-3">Sobre Nós</h2>
<h5 class="mb-3">✂️ Mais de 25 anos entregando resultado de verdade…</h5>
<p class="text-secondary mb-4"><?= nl2br(h($texto_sobre)) ?></p>
<button type="button" class="btn btn-outline-secondary rounded-pill px-4" data-bs-toggle="modal" data-bs-target="#empresa">Mais Informações</button>
</div>
</div>
</div>
</section>
<?php if ($videoEmBaixo): ?>
<div class="container-xxl mb-4"><div class="ratio ratio-16x9 rounded-3 overflow-hidden">
<iframe data-src="<?=h(to_embed_url($url_video))?>?rel=0&modestbranding=1" title="Vídeo institucional" loading="lazy" allow="accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture;web-share" allowfullscreen></iframe>
</div></div>
<?php endif; ?>

<?php
if (!function_exists('h2')) { function h2($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('digits')) { function digits($s){ return preg_replace('/\D+/', '', (string)$s); } }

$FOOT = [
  'nome'           => $nome_sistema       ?? 'Jacy Cabeleireiro',
  'endereco'       => $enderecoStr        ?? 'Rio Grande do Sul, 2151',
  'texto_rodape' => $texto_rodape       ?? '',
  'instagram'      => $instagram_sistema  ?? '',
  'wpp_raw'        => $tel_whatsapp       ?? '45 99988-2100',
  'agenda_url'     => $agendaUrl          ?? '/agendamentos',
  'logo_png'       => $logoPngUrl         ?? '/sistema/img/logo.png',
  'logo_webp'      => $logoWebpUrl        ?? '/sistema/img/logo.webp',
];

try {
  if (isset($pdo) && $pdo instanceof PDO) {
    $stmt = $pdo->query("SELECT nome, endereco, telefone_whatsapp, instagram, texto_rodape FROM config LIMIT 1");
    $row  = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    if ($row) {
      if (!empty($row['nome']))                $FOOT['nome']           = $row['nome'];
      if (!empty($row['endereco']))            $FOOT['endereco']       = $row['endereco'];
      if (!empty($row['instagram']))           $FOOT['instagram']      = $row['instagram'];
      if (!empty($row['texto_rodape']))        $FOOT['texto_rodape'] = $row['texto_rodape'];
      if (!empty($row['telefone_whatsapp']))   $FOOT['wpp_raw']        = $row['telefone_whatsapp'];
    }
  }
} catch (Throwable $e) {}

// Usar o WPP_LINK já definido no topo (WPP)
$WPP_LINK = defined('WPP') ? WPP : wpp_url_from($FOOT['wpp_raw']); 

$webpFs  = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/').$FOOT['logo_webp'];
$logoTag = (is_file($webpFs))
  ? '<picture><source type="image/webp" srcset="'.h($FOOT['logo_webp']).'?v=1"><img src="'.h($FOOT['logo_png']).'?v=1" alt="Logo" width="184" height="88" style="height:auto;" loading="lazy" decoding="async"></picture>'
  : '<img src="'.h($FOOT['logo_png']).'?v=1" alt="Logo" width="184" height="88" style="height:auto;" loading="lazy" decoding="async">';

if (empty($_SESSION['csrf'])) { $_SESSION['csrf'] = bin2hex(random_bytes(16)); }
$csrf = $_SESSION['csrf'];
?>

<footer style="background:#0b0b0b;color:#fff;">
<div class="container" style="max-width:1280px;margin:0 auto;padding:26px 16px;">
<div style="display:flex;gap:24px;flex-wrap:wrap;align-items:flex-start">
<div style="flex:1 1 300px;min-width:280px;">
<div style="display:flex;align-items:center;gap:10px;margin-bottom:10px">
<?= $logoTag ?>
<h3 style="margin:0;font-size:22px;letter-spacing:.5px;"><?= h($FOOT['nome']) ?></h3>
</div>
<?php if($FOOT['texto_rodape']!==''): ?>
<p style="margin:0;color:#d9d9d9;line-height:1.55;font-size:14px;"><?= nl2br(h($FOOT['texto_rodape'])) ?></p>
<?php endif; ?>
</div>

<div style="flex:1 1 260px;min-width:240px;">
<h3 style="margin:0 0 10px 0;font-size:22px;">Links & contatos</h3>
<ul style="list-style:none;padding:0;margin:0;color:#dcdcdc;font-size:15px;line-height:1.9">
<?php if($FOOT['endereco']!==''): ?><li>📍 <?= h($FOOT['endereco']) ?></li><?php endif; ?>
<li>✍️ <a href="/assinatura" style="color:#fff;text-decoration:none">Assinatura</a></li>
<li>✂️ <a href="/servicos" style="color:#fff;text-decoration:none">Serviços</a></li>
<li>👔 <a href="/barbeiro" style="color:#fff;text-decoration:none">Barbeiro</a></li>
<li>💇‍♀️ <a href="/protese" style="color:#fff;text-decoration:none">Prótese</a></li>
</ul>
</div>

<div style="flex:1 1 320px;min-width:260px;">
<h3 style="margin:0 0 10px 0;font-size:22px;">Cadastre-se</h3>
<p style="margin:0 0 10px 0;color:#dcdcdc;font-size:14px;">Solicite uma avaliação gratuita</p>

<form id="form_cadastro" method="post" action="/cadastrar.php" style="max-width:340px" novalidate>
<input type="hidden" name="csrf" value="<?= h($csrf) ?>">
<input type="hidden" name="recaptcha_token"   value="">
<input type="hidden" name="recaptcha_action" value="cadastro">
<input type="text" name="website" autocomplete="off" tabindex="-1" style="position:absolute;left:-10000px;top:auto;width:1px;height:1px;overflow:hidden" aria-hidden="true">

<div style="margin-bottom:10px;">
<label for="telefone_rodape" class="visually-hidden">WhatsApp</label>
<input type="tel" name="telefone" id="telefone_rodape" placeholder="WhatsApp" inputmode="numeric" autocomplete="tel" style="width:100%;padding:12px 14px;border:0;background:#eaecef;border-radius:6px;font-size:15px;">
</div>
<div style="margin-bottom:10px;">
<label for="nome_rodape" class="visualmente-hidden">Nome Completo</label>
<input type="text" name="nome" id="nome_rodape" placeholder="Nome Completo" autocomplete="name" style="width:100%;padding:12px 14px;border:0;background:#eaecef;border-radius:6px;font-size:15px;">
</div>
<button type="submit" style="width:100%;padding:12px 14px;border:0;border-radius:6px;background:var(--brand);color:#fff;font-weight:700;cursor:pointer;transition:all .3s">Cadastrar</button>
</form>

<br>
<small><div id="mensagem-rodape" aria-live="polite"></div></small>
</div>
</div>
</div>

<div style="background:#1f2229;color:#e6e6e6;">
<div class="container" style="max-width:1280px;margin:0 auto;padding:10px 16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
<small>© <?= date('Y') ?> <?= h($FOOT['nome']) ?></small>
<a data-toggle="modal" data-target="#privacidade" href="#privacidade" style="color:#e6e6e6;">Política de privacidade</a>
<a data-toggle="modal" data-target="#modalTermos" href="#modalTermos" style="color:#e6e6e6;">Termos de uso</a>
<small style="margin-left:auto;">
<?= h($FOOT['nome']) ?> <?= h($FOOT['wpp_raw']) ?> |
<a href="<?= h($WPP_LINK) ?>" target="_blank" rel="noopener" style="color:#e6e6e6;text-decoration:none">📱 WhatsApp</a>
</small>
</div>
</div>
</footer>

<div class="modal fade" id="empresa" tabindex="-1" aria-labelledby="empresaTitle" aria-hidden="true">
<div class="modal-dialog modal-lg modal-dialog-centered">
<div class="modal-content rounded-3 border-0 shadow">
<div class="modal-header border-0 pb-0">
<div>
<h1 id="empresaTitle" class="h4 fw-bold mb-1">Jacy Cabeleireiro</h1>
<p class="m-0 text-muted small">Formação, técnica e excelência em Cascavel.</p>
</div>
<button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Fechar modal"></button>
</div>
<div class="modal-body pt-3">
<p class="text-secondary">Nossa missão é oferecer os melhores serviços de beleza com excelência e dedicação.</p>
<div class="d-flex flex-wrap gap-2 mb-3">
<span class="badge rounded-pill text-bg-light">Qualidade</span>
<span class="badge rounded-pill text-bg-light">Didática prática</span>
<span class="badge rounded-pill text-bg-light">Mercado</span>
</div>
<div class="ratio ratio-16x9 rounded-3 overflow-hidden">
<iframe data-src="https://www.youtube-nocookie.com/embed/E_tBbvQJ1mA?rel=0" title="Apresentação Jacy Cabeleireiro" allow="accelerometer;autoplay;clipboard-write;encrypted-media;gyroscope;picture-in-picture" allowfullscreen loading="lazy"></iframe>
</div>
<p class="mt-2 small text-muted">CNPJ 36.896.614/0001-40</p>
</div>
<div class="modal-footer border-0">
<a href="/agendamentos" class="btn btn-primary rounded-pill">Agendar</a>
<a href="<?=h(WPP)?>" class="btn btn-outline-secondary rounded-pill" target="_blank" rel="noopener">WhatsApp</a>
</div>
</div>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" defer></script>

<script>
(function(){"use strict";function e(){const e=document.querySelectorAll("iframe[data-src]");if("IntersectionObserver"in window){const t=new IntersectionObserver(((e,s)=>{e.forEach((e=>{if(e.isIntersecting){const s=e.target;s.src=s.dataset.src,delete s.dataset.src,t.unobserve(s)}}))}),{rootMargin:"100px"});e.forEach((e=>t.observe(e)))}else e.forEach((e=>e.src=e.dataset.src));const t=document.getElementById("hero");if(t){const e=()=>{const e=t.querySelector(".carousel-item.active video[data-autoplay]");e&&!navigator.connection?.saveData&&e.play().catch((()=>{}));};t.addEventListener("slid.bs.carousel",e),setTimeout(e,500)}document.querySelectorAll(".media-wrap").forEach((e=>{const t=e.closest(".section");t?.querySelectorAll("[data-action]").forEach((s=>{s.addEventListener("click",(function(){if(this.disabled)return;const a=e.querySelector(".media-img"),o=e.querySelector(".media-video"),i=e.querySelector(".media-iframe");a?.classList.remove("on"),o?.classList.remove("on"),i?.classList.remove("on"),"show-image"===this.dataset.action?a?.classList.add("on"):(i||o)?.classList.add("on"),i&&i.dataset.src&&(i.src=i.dataset.src,delete i.dataset.src),o&&o.querySelector("source[data-src]")?.(e=>{e.src=e.dataset.src,delete e.dataset.src,o.load()}),t.querySelectorAll("[data-action]").forEach((e=>{const t=e===this;e.classList.toggle("btn-warning",t),e.classList.toggle("fw-bold",t),e.classList.toggle("btn-outline-secondary",!t)}))}))}))}))}function t(e){var t=e.target.value.replace(/\D/g,"").slice(0,11),s=t;t.length>2&&(s="("+t.slice(0,2)+") "+t.slice(2)),t.length>=11?s="("+t.slice(0,2)+") "+t.slice(2,7)+"-"+t.slice(7):t.length>=7&&(s="("+t.slice(0,2)+") "+t.slice(2,6)+"-"+t.slice(6)),e.target.value=s}function s(){const e=document.getElementById("telefone_rodape");e&&e.addEventListener("input",t);const s=document.getElementById("form_cadastro");s&&s.addEventListener("submit",(function(e){e.preventDefault();const t=(s.querySelector('[name="nome"]').value||"").trim(),a=(s.querySelector('[name="telefone"]').value||"").replace(/\D/g,"");if(!t||a.length<10)return void(window.Swal?Swal.fire({icon:"error",title:"❌ Dados inválidos",text:"Preencha corretamente nome e telefone.",confirmButtonColor:"#DAA520"}):alert("Preencha corretamente nome e telefone."));const o=s.querySelector('input[name="recaptcha_token"]'),i=s.querySelector('input[name="recaptcha_action"]');function n(){const e=new XMLHttpRequest;e.open("POST","/cadastrar.php",!0),e.setRequestHeader("X-Requested-With","XMLHttpRequest"),e.onload=function(){if(e.status<200||e.status>=300)return void(window.Swal?Swal.fire({icon:"error",title:"❌ Erro",text:"HTTP "+e.status,confirmButtonColor:"#00c585"}):alert("Erro HTTP "+e.status));const t=(e.responseText||"").trim();0===t.indexOf("Cadastrado com Sucesso")?window.Swal?Swal.fire({icon:"success",title:"✅ Sucesso!",text:"Cadastro realizado.",confirmButtonColor:"#00c585"}).then((()=>location.href="<?php echo function_exists('bb_url_acesso') ? bb_url_acesso() : '/sistema/acesso'; ?>")):(alert("Cadastro realizado."),location.href="<?php echo function_exists('bb_url_acesso') ? bb_url_acesso() : '/sistema/acesso'; ?>"):0===t.indexOf("Você já está Cadastrado")?window.Swal?Swal.fire({icon:"warning",title:"⚠️ Já cadastrado",text:"Você já tem cadastro.",confirmButtonColor:"#00c585"}).then((()=>location.href="<?php echo function_exists('bb_url_acesso') ? bb_url_acesso() : '/sistema/acesso'; ?>")):(alert("Você já tem cadastro."),location.href="<?php echo function_exists('bb_url_acesso') ? bb_url_acesso() : '/sistema/acesso'; ?>"):0===t.indexOf("Preencha nome e telefone")?window.Swal?Swal.fire({icon:"error",title:"❌ Dados inválidos",text:"Preencha corretamente.",confirmButtonColor:"#00c585"}):alert("Preencha corretamente."):window.Swal?Swal.fire({icon:"error",title:"❌ Erro",html:"<pre>"+t+"</pre>",confirmButtonColor:"#00c585"}):alert("Erro: "+t)},e.onerror=function(){window.Swal?Swal.fire({icon:"error",title:"❌ Falha de rede",text:"Não foi possível enviar.",confirmButtonColor:"#00c585"}):alert("Falha de rede.")},e.send(new FormData(s))}i&&(i.value="cadastro"),window.grecaptcha&&window.RECAPTCHA_SITE_KEY?grecaptcha.ready((()=>{grecaptcha.execute(window.RECAPTCHA_SITE_KEY,{action:"cadastro"}).then((e=>{o&&(o.value=e),n()})).catch((()=>n()))})):n()}))}"loading"===document.readyState?document.addEventListener("DOMContentLoaded",(function(){e(),s()})):(e(),s())})();
</script>

<script type="application/ld+json">
{
  "@context":"https://schema.org",
  "@type":"HairSalon",
  "name":"<?=h($nome_sistema)?>",
  "description":"Cortes profissionais, prótese capilar, mechas e progressivas com +25 anos de experiência",
  "address":{
    "@type":"PostalAddress",
    "addressLocality":"Cascavel",
    "addressRegion":"PR",
    "addressCountry":"BR",
    "streetAddress":"<?=h($enderecoStr)?>"
  },
  "url":"https://jacycabeleireiro.com",
  "telephone":"<?=h($FOOT['wpp_raw'])?>",
  "priceRange":"$$",
  "openingHours":"Mo-Fr 09:00-19:00,Sa 09:00-17:00"
}
</script>
</body>
</html>
