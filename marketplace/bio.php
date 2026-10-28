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


// ===== Sessão TENANT por SLUG (evita misturar lojas no mesmo navegador) =====
if (!function_exists('bb_sess_clean_slug')) {
  function bb_sess_clean_slug($v) {
    $v = strtolower(trim((string)$v));
    return preg_replace('/[^a-z0-9_-]/', '', $v);
  }
}

// garante $__bb_refPath quando o arquivo não define
if (!isset($__bb_refPath)) {
  $__bb_refPath = '';
  if (!empty($_SERVER['HTTP_REFERER'])) {
    $__bb_refPath = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: '';
  }
}

$__bb_reqSlug = '';
if (!empty($_GET['loja'])) {
  $__bb_reqSlug = bb_sess_clean_slug($_GET['loja']);
} elseif (!empty($_POST['loja'])) {
  $__bb_reqSlug = bb_sess_clean_slug($_POST['loja']);
} else {
  if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath, $mSlug)) {
    $__bb_reqSlug = bb_sess_clean_slug($mSlug[1]);
  } elseif (!empty($__bb_refPath) && preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_refPath, $mSlug2)) {
    $__bb_reqSlug = bb_sess_clean_slug($mSlug2[1]);
  }
}

$__bb_tenantSessionName = 'BB_TENANT';
if ($__bb_reqSlug !== '') {
  $__bb_tenantSessionName = 'BB_TENANT_' . strtoupper(substr(sha1($__bb_reqSlug), 0, 12));
}

$__bb_lojaByPath = (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath)
                || (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_refPath);

$__bb_hasLoja = $__bb_lojaByPath || !empty($_GET['loja']) || !empty($_POST['loja']);
$__bb_isSistema = (strpos($__bb_reqPath, '/sistema') === 0) || (strpos($__bb_refPath, '/sistema') === 0);

// Força MASTER em /sistema quando NÃO é loja por PATH e NÃO tem ?loja=
$__bb_forceMaster = ($__bb_isSistema && !$__bb_lojaByPath && empty($_GET['loja']) && empty($_POST['loja']));

$__bb_desiredSession = $__bb_forceMaster ? 'BB_MASTER' : ($__bb_hasLoja ? $__bb_tenantSessionName : 'BB_SITE');

// Inicia/ajusta a sessão corretamente (usa o cookie do nome escolhido)
if (session_status() === PHP_SESSION_ACTIVE) {
  if (session_name() !== $__bb_desiredSession) {
    session_write_close();
    @session_name($__bb_desiredSession);
    if (!empty($_COOKIE[$__bb_desiredSession])) {
      @session_id((string)$_COOKIE[$__bb_desiredSession]);
    }
    @session_start();
  }
} else {
  @session_name($__bb_desiredSession);
  if (!empty($_COOKIE[$__bb_desiredSession])) {
    @session_id((string)$_COOKIE[$__bb_desiredSession]);
  }
  @session_start();
}
header('Content-Type: text/html; charset=UTF-8');

/* ====== Conexão tolerante ====== */
$pdo = null;
try { require_once $_SERVER['DOCUMENT_ROOT'].'/sistema/conexao.php'; } catch(Throwable $e){ $pdo = null; }

/* ====== HELPERS ====== */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }

/* cache-busting suave p/ arquivos locais */
function web_file($path){
  $path = (string)$path;
  if($path==='' || $path[0] !== '/') return $path;
  $fs = $_SERVER['DOCUMENT_ROOT'].$path;
  if(is_file($fs)){ $v=(string)@filemtime($fs); return $path.($v?('?v='.$v):''); }
  return $path;
}

/* normaliza link digitado no painel */
function canon_url($u){
  $u = trim((string)$u);
  if($u==='' || $u==='#') return '#';
  if(preg_match('#^(https?:)?//#i',$u)) return $u;
  if($u[0]==='/') return $u;
  if(preg_match('/^[\w.-]+\.[a-z]{2,}(\/.*)?$/i',$u)) return 'https://'.$u;
  return '/'.ltrim($u,'/');
}

/* decide caminho da imagem da bio */
function bio_img_url($img){
  $img = trim((string)$img);
  if($img==='') return '';
  if($img[0]==='/') return $img;
  return '/sistema/img/bio/'.$img;
}

/* se o admin não informar ícone, tenta sugerir */
function guess_icon($title, $group){
  $t = mb_strtolower($title.' '.$group);
  if(strpos($t,'agenda')!==false) return 'fa-regular fa-calendar-check';
  if(strpos($t,'barb')!==false || strpos($t,'corte')!==false) return 'fa-solid fa-scissors';
  if(strpos($t,'site')!==false || strpos($t,'portf')!==false) return 'fa-solid fa-globe';
  if(strpos($t,'prótes')!==false || strpos($t,'protese')!==false) return 'fa-solid fa-user';
  if(strpos($t,'salão')!==false || strpos($t,'salao')!==false || strpos($t,'cabeleireir')!==false) return 'fa-solid fa-house';
  return 'fa-solid fa-link';
}

/* ====== Identidade ====== */
$logo_png  = '/sistema/img/logo.png';
$logo_webp = '/sistema/img/logo.webp';
$title_site = isset($nome_sistema) && $nome_sistema ? ($nome_sistema.' — Bio') : 'Jacy Cabeleireiro — Bio';

/* ====== Carrega itens da tabela bio ====== */
$items = [];
if($pdo){
  try{
    $sql = "SELECT b.id,b.nome,b.chave,b.descricao,b.icone,b.imagem,b.grupo,b.ordem,b.ativo,
                   ga.nome AS grupo_nome
            FROM bio b
            LEFT JOIN grupo_acessos ga ON ga.id=b.grupo
            WHERE COALESCE(b.ativo,1)=1
            ORDER BY COALESCE(b.ordem,0) ASC, b.id DESC";
    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    foreach($rows as $r){
      $titulo = (string)$r['nome'];
      $grupo  = (string)($r['grupo_nome'] ?? '');
      $desc   = trim((string)($r['descricao'] ?? ''));
      $icone  = trim((string)($r['icone'] ?? ''));
      if($icone==='') $icone = guess_icon($titulo, $grupo);
      $items[] = [
        'titulo'    => $titulo,
        'categoria' => $grupo,              // chip se existir
        'descricao' => $desc,               // subtítulo
        'href'      => canon_url($r['chave'] ?? ''),
        'icone'     => $icone,
        'img'       => bio_img_url($r['imagem'] ?? ''),
      ];
    }
  }catch(Throwable $e){ /* fallback abaixo se der erro */ }
}

/* ====== Fallback se não houver nada no BD ====== */
if(!$items){
  $items = [
    ['titulo'=>'Jacy Cordeiro','categoria'=>'Site pessoal / Cursos','descricao'=>'Portfólio e conteúdos','href'=>canon_url('https://jacycordeiro.com.br/'),'icone'=>'fa-solid fa-globe','img'=>'/sistema/public/uploads/site/bio-jacycordeiro.jpg'],
    ['titulo'=>'Jacy Barbeiro','categoria'=>'Barbearia e serviços masculinos','descricao'=>'Cortes, barba e acabamento','href'=>canon_url('https://jacycabeleireiro.com/barbearia'),'icone'=>'fa-solid fa-scissors','img'=>''],
    ['titulo'=>'Jacy Cabeleireiro','categoria'=>'Página inicial do salão','descricao'=>'Serviços, fotos e contatos','href'=>canon_url('https://jacycabeleireiro.com/'),'icone'=>'fa-solid fa-house','img'=>'/sistema/public/uploads/site/bio-cabeleireiro.jpg'],
    ['titulo'=>'Prótese Capilar','categoria'=>'Especialidade','descricao'=>'Aparência natural e manutenção','href'=>canon_url('https://jacycabeleireiro.com/protese-capilar'),'icone'=>'fa-solid fa-user','img'=>'/sistema/public/uploads/site/bio-protese.jpg'],
    ['titulo'=>'Corte de Cabelo','categoria'=>'Serviço','descricao'=>'Feminino e masculino','href'=>canon_url('https://jacycabeleireiro.com/corte-cabelo'),'icone'=>'fa-solid fa-scissors','img'=>''],
    ['titulo'=>'Confira a agenda','categoria'=>'Agendamento','descricao'=>'Escolha seu horário online','href'=>canon_url('https://jacycabeleireiro.com/agendamentos'),'icone'=>'fa-regular fa-calendar-check','img'=>'/sistema/public/uploads/site/bio-agenda.jpg'],
  ];
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title><?=h($title_site)?></title>
  <meta name="author" content="Jacy Cordeiro" />
  <link rel="shortcut icon" href="images/<?php echo $icone_site ?>" type="image/x-icon">
  <meta name="description" content="Links oficiais — portfólio, serviços, prótese capilar e agendamentos.">
  <meta name="theme-color" content="#0a0a0a">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" rel="stylesheet">
  <style>
    :root{
      --bg:#0a0a0a; --text:#f1f1f1; --muted:#a3a6ad; --brand:#2d86ff;
      --card-radius:18px;
      --card-bg: linear-gradient(135deg,#15181d 0%, #0e0f12 60%);
    }
    html,body{ background:var(--bg); color:var(--text); }
    a{ color:inherit; text-decoration:none; }
    .page{ min-height:100dvh; display:flex; align-items:center; justify-content:center; padding:32px 16px; }
    .wrap{ width:100%; max-width:720px; }

    .avatar{ width:86px; height:86px; border-radius:50%; overflow:hidden; background:#111;
             display:grid; place-items:center; border:1px solid rgba(255,255,255,.08); box-shadow:0 6px 20px rgba(0,0,0,.35); }
    .avatar img{ width:100%; height:100%; object-fit:cover; }
    .headline small{ color:var(--muted); }

    .card-link{ position:relative; border-radius:var(--card-radius); overflow:hidden; display:block;
                background:var(--card-bg); border:1px solid rgba(255,255,255,.06);
                transition:transform .18s ease, box-shadow .18s ease, border-color .18s ease; }
    .card-link:hover{ transform:translateY(-2px); box-shadow:0 10px 30px rgba(0,0,0,.35); border-color:rgba(255,255,255,.18); }

    .card-body{ position:relative; z-index:2; padding:16px 18px; display:flex; align-items:center; gap:14px; }

    /* THUMB: usa imagem se existir; senão, ícone */
    .thumb{ width:64px; height:64px; border-radius:16px; overflow:hidden; flex:0 0 auto;
            background:#101216; border:1px solid rgba(255,255,255,.10);
            box-shadow:0 6px 16px rgba(0,0,0,.28); display:grid; place-items:center; }
    .thumb img{ width:100%; height:100%; object-fit:cover; display:block; }
    .thumb i{ font-size:26px; color:#cfe1ff; }

    .chip{ display:inline-block; font-size:12px; color:#fff; padding:3px 9px; border-radius:999px;
           background:rgba(255,255,255,.06); border:1px solid rgba(255,255,255,.15); margin-bottom:4px; }
    .title{ font-weight:800; letter-spacing:.2px; line-height:1.2; }
    .sub{ color:#c9ced8; font-size:.93rem; line-height:1.25; margin-top:2px; }
    .cta{ color:#dbe7ff; font-size:.92rem; margin-top:6px; }
  </style>
</head>
<body>
  <main class="page">
    <div class="wrap">

      <!-- Header -->
      <div class="d-flex align-items-center gap-3 mb-3">
        <div class="avatar">
          <picture>
            <?php if(is_file($_SERVER['DOCUMENT_ROOT'].$logo_webp)): ?>
              <source type="image/webp" srcset="<?=h(web_file($logo_webp))?>">
            <?php endif; ?>
            <img src="<?=h(web_file($logo_png))?>" alt="Logo">
          </picture>
        </div>
        <div class="headline">
          <h1 class="h4 fw-bold m-0"><?= isset($nome_sistema) && $nome_sistema ? h($nome_sistema) : 'Jacy Cabeleireiro' ?></h1>
          <small>Links oficiais · escolha uma opção</small>
        </div>
      </div>

      <!-- Links -->
      <div class="d-grid gap-3">
        <?php foreach($items as $it):
          $img = (string)$it['img'];
          $hasImg = ($img !== '') && is_file($_SERVER['DOCUMENT_ROOT'].$img);
          $chip = trim((string)$it['categoria']);
          $sub  = trim((string)$it['descricao']);
        ?>
        <a class="card-link" href="<?= h($it['href']) ?>" target="_blank" rel="noopener">
          <div class="card-body">
            <div class="thumb">
              <?php if($hasImg): ?>
                <img src="<?= h(web_file($img)) ?>" alt="<?= h($it['titulo']) ?>">
              <?php else: ?>
                <i class="<?= h($it['icone']) ?>"></i>
              <?php endif; ?>
            </div>
            <div class="flex-grow-1">
              <?php if($chip!==''): ?><div class="chip"><?= h($chip) ?></div><?php endif; ?>
              <div class="title"><?= h($it['titulo']) ?></div>
              <?php if($sub!==''): ?><div class="sub"><?= h($sub) ?></div><?php endif; ?>
              <div class="cta"><i class="fa-solid fa-arrow-up-right-from-square me-1"></i> Abrir</div>
            </div>
          </div>
        </a>
        <?php endforeach; ?>
      </div>

      <div class="text-center mt-4" style="color:#a3a6ad;font-size:.9rem">
        © <?=date('Y')?> <?= isset($nome_sistema) && $nome_sistema ? h($nome_sistema) : '<?php echo $nome_sistema ?>' ?>
      </div>
    </div>
  </main>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
