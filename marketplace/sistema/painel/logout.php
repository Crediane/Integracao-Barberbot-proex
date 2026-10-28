<?php
// ======================================================================
// ARQUIVO: sistema/painel/logout.php
// OBJETIVO (SEM MUDAR A LÓGICA DO LOGOUT):
// - Encerrar a sessão do contexto atual (MASTER ou TENANT)
// - Suportar TENANT por SLUG: BB_TENANT_<hash>
// - Remover BB_CTX para evitar seleção errada de contexto no próximo acesso
// ======================================================================

if (!function_exists('bb_sess_clean_slug')) {
  function bb_sess_clean_slug($v) {
    $v = strtolower(trim((string)$v));
    return preg_replace('/[^a-z0-9_-]/', '', $v);
  }
}

$__bb_reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$__bb_refPath = '';
if (!empty($_SERVER['HTTP_REFERER'])) {
  $__bb_refPath = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: '';
}

// ===============================
// 1) Detecta SLUG cedo (GET/POST -> /loja/<slug>/ -> REFERER)
// ===============================
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

// ===============================
// 2) Cookies TENANT existentes (BB_TENANT e BB_TENANT_*)
// ===============================
$__bb_tenantCookies = [];
foreach ($_COOKIE as $__bb_ck => $__bb_cv) {
  if ($__bb_ck === 'BB_TENANT' || strpos($__bb_ck, 'BB_TENANT_') === 0) {
    $__bb_tenantCookies[] = (string)$__bb_ck;
  }
}
$__bb_hasTenantCookie = (count($__bb_tenantCookies) > 0);
$__bb_oneTenantCookie = (count($__bb_tenantCookies) === 1) ? (string)$__bb_tenantCookies[0] : '';
$__bb_hasMasterCookie = !empty($_COOKIE['BB_MASTER']);

// ===============================
// 3) Decide contexto MASTER x TENANT (mesma regra do sistema)
// ===============================
$__bb_lojaByPath = (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath);
$__bb_hasLojaParam = (!empty($_GET['loja']) || !empty($_POST['loja']));
$__bb_isSistemaRoot = (strpos($__bb_reqPath, '/sistema') === 0);

$__bb_forceMaster = ($__bb_isSistemaRoot && !$__bb_lojaByPath && !$__bb_hasLojaParam);

// Contexto ativo (quando MASTER e TENANT coexistem no mesmo navegador)
$__bb_ctx = '';
if (!empty($_GET['ctx'])) {
  $__bb_ctx = strtolower(trim((string)$_GET['ctx']));
} elseif (!empty($_COOKIE['BB_CTX'])) {
  $__bb_ctx = strtolower(trim((string)$_COOKIE['BB_CTX']));
}
if (!in_array($__bb_ctx, ['tenant','master'], true)) $__bb_ctx = '';

// Ajuste fino do marketplace
if ($__bb_forceMaster) {
  if ($__bb_ctx === 'tenant' && $__bb_hasTenantCookie) {
    $__bb_forceMaster = false;
  } elseif ($__bb_ctx === 'master' && $__bb_hasMasterCookie) {
    $__bb_forceMaster = true;
  } elseif ($__bb_hasTenantCookie && !$__bb_hasMasterCookie) {
    $__bb_forceMaster = false;
  }
}

// Se não forçou master e existe tenant cookie, tratamos como TENANT (para rotas internas sem /loja/)
$__bb_hasLojaContext = $__bb_lojaByPath || $__bb_hasLojaParam;
if (!$__bb_forceMaster && !$__bb_hasLojaContext && $__bb_hasTenantCookie) {
  $__bb_hasLojaContext = true;
}

// Sessão TENANT por SLUG
$__bb_tenantSessionName = 'BB_TENANT';
if ($__bb_reqSlug !== '') {
  $__bb_tenantSessionName = 'BB_TENANT_' . strtoupper(substr(sha1($__bb_reqSlug), 0, 12));
}

// Sessão desejada
$__bb_desiredSession = 'BB_SITE';
if ($__bb_forceMaster) {
  $__bb_desiredSession = 'BB_MASTER';
} elseif ($__bb_hasLojaContext) {
  if ($__bb_reqSlug !== '') {
    $__bb_desiredSession = $__bb_tenantSessionName;
  } elseif ($__bb_oneTenantCookie !== '') {
    $__bb_desiredSession = $__bb_oneTenantCookie;
  } else {
    $__bb_desiredSession = 'BB_TENANT';
  }
}

// ===============================
// 4) Inicia a sessão correta para conseguir ler tenant_slug e redirecionar
// ===============================
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

// ===============================
// 5) Preserva a loja/tenant para redirecionar corretamente após sair
//    (mantém lógica original)
// ===============================
$loja = '';
if (isset($_GET['loja']) && $_GET['loja'] !== '') {
  $loja = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$_GET['loja']);
} elseif (isset($_SESSION['tenant_slug']) && $_SESSION['tenant_slug'] !== '') {
  $loja = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$_SESSION['tenant_slug']);
} elseif (isset($_SESSION['loja']) && $_SESSION['loja'] !== '') {
  $loja = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$_SESSION['loja']);
}

// ===============================
// 6) Logout (mantém lógica): limpa sessão e destrói
// ===============================
$_SESSION = array();

// Remove cookie da sessão atual (nome dinâmico: BB_MASTER, BB_TENANT, BB_TENANT_<hash>...)
if (ini_get('session.use_cookies')) {
  $params = session_get_cookie_params();

  // PHP 7.3+ (array options)
  if (PHP_VERSION_ID >= 70300) {
    @setcookie(session_name(), '', [
      'expires'  => time() - 42000,
      'path'     => $params['path'] ?? '/',
      'domain'   => $params['domain'] ?? '',
      'secure'   => (bool)($params['secure'] ?? false),
      'httponly' => (bool)($params['httponly'] ?? true),
      'samesite' => 'Lax',
    ]);
  } else {
    // compat antigo
    @setcookie(session_name(), '', time() - 42000, $params['path'] ?? '/', $params['domain'] ?? '', (bool)($params['secure'] ?? false), (bool)($params['httponly'] ?? true));
  }
}

// Remove BB_CTX (evita selecionar contexto errado depois)
$secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
if (PHP_VERSION_ID >= 70300) {
  @setcookie('BB_CTX', '', [
    'expires'  => time() - 3600,
    'path'     => '/',
    'secure'   => $secure,
    'httponly' => true,
    'samesite' => 'Lax',
  ]);
} else {
  @setcookie('BB_CTX', '', time() - 3600, '/', '', $secure, true);
}

@session_destroy();

// ===============================
// 7) Redireciona mantendo ?loja= quando existir (mesma lógica)
// ===============================
$destino = "../index.php";
if ($loja !== '') {
  $destino .= (strpos($destino, '?') !== false ? '&' : '?') . "loja=" . urlencode($loja);
}

echo "<script>window.location='{$destino}'</script>";
