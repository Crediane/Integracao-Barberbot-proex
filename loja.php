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
require_once __DIR__ . "/sistema/conexao.php";

// pega slug pela URL /loja/slug
$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$seg = array_values(array_filter(explode('/', trim($uri,'/'))));
$slug = (isset($seg[0]) && $seg[0] === 'loja' && !empty($seg[1])) ? $seg[1] : ($_SESSION['tenant_slug'] ?? '');

if ($slug == '') {
  header("Location: /loja");
  exit;
}

// carrega o conexao.php do sistema (isso vai setar sessão e conectar no DB do tenant)
require_once __DIR__ . "/sistema/conexao.php";

function bb_digits($v){ return preg_replace('/\D+/', '', (string)$v); }

$w = bb_digits($whatsapp_sistema ?? '');
$wa_link = $w ? ("https://wa.me/55".$w) : "#";

$logo = $logo_sistema ?? 'logo.png';

// URLs corretas (padrão do sistema)
$base = rtrim($url_sistema ?? '/', '/');
$ag_url     = $base . "/agendamentos?loja=" . rawurlencode($slug);
$painel_url = $base . "/sistema/?loja=" . rawurlencode($slug);
$logo_url   = $base . "/sistema/img/" . rawurlencode($logo);
?>
<!doctype html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $nome_sistema; ?> - Gesto para Barbearias e Sales</title>
    <link rel="shortcut icon" href="<?php echo $caminho_icone; ?>" type="image/x-icon">
  <style>
    body{font-family:Arial,Helvetica,sans-serif;margin:0;background:#0b0b0b;color:#fff}
    .wrap{max-width:900px;margin:0 auto;padding:24px}
    .card{border:1px solid rgba(255,255,255,.12);border-radius:16px;padding:18px;background:#0f0f0f}
    .row{display:flex;gap:14px;align-items:center;flex-wrap:wrap}
    img{width:74px;height:74px;border-radius:18px;object-fit:cover;border:1px solid rgba(255,255,255,.12)}
    .btn{display:inline-block;padding:12px 14px;border-radius:12px;background:#fff;color:#000;text-decoration:none;font-weight:800;margin-right:10px;margin-top:10px}
    .muted{opacity:.85}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <div class="row">
        <img src="<?=$logo_url?>" alt="">
        <div>
          <h2 style="margin:0"><?=htmlspecialchars($nome_sistema ?? '')?></h2>
          <div class="muted"><?=htmlspecialchars($endereco_sistema ?? '')?></div>
          <div class="muted" style="margin-top:4px;">Loja: <b><?=htmlspecialchars($slug)?></b></div>
        </div>
      </div>

      <div style="margin-top:12px">
        <a class="btn" href="<?=$ag_url?>">Agendar agora</a>
        <a class="btn" href="<?=$painel_url?>">Entrar no painel</a>
        <?php if ($w): ?>
          <a class="btn" href="<?=$wa_link?>" target="_blank" rel="noopener">WhatsApp</a>
        <?php endif; ?>
      </div>
    </div>
  </div>
</body>
</html>
