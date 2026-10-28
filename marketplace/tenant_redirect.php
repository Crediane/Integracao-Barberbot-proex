<?php
// /public_html/tenant_redirect.php
declare(strict_types=1);

if (session_status() !== PHP_SESSION_ACTIVE) {
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
} elseif (!empty($_GET['slug'])) {
  $__bb_reqSlug = bb_sess_clean_slug($_GET['slug']);
} elseif (!empty($_POST['slug'])) {
  $__bb_reqSlug = bb_sess_clean_slug($_POST['slug']);
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

$__bb_hasLoja = $__bb_lojaByPath || !empty($_GET['loja']) || !empty($_POST['loja']) || !empty($_GET['slug']) || !empty($_POST['slug']);
$__bb_isSistema = (strpos($__bb_reqPath, '/sistema') === 0) || (strpos($__bb_refPath, '/sistema') === 0);

// ✅ Contexto ativo (resolve conflito quando o mesmo navegador tem BB_MASTER e BB_TENANT)
$__bb_hasTenantCookie = false;
foreach ($_COOKIE as $__bb_ck => $__bb_cv) {
  if ($__bb_ck === 'BB_TENANT' || strpos($__bb_ck, 'BB_TENANT_') === 0) { $__bb_hasTenantCookie = true; break; }
}
$__bb_hasMasterCookie = !empty($_COOKIE['BB_MASTER']);

$__bb_ctx = '';
if (!empty($_GET['ctx'])) {
  $__bb_ctx = strtolower(trim((string)$_GET['ctx']));
} elseif (!empty($_COOKIE['BB_CTX'])) {
  $__bb_ctx = strtolower(trim((string)$_COOKIE['BB_CTX']));
}
if (!in_array($__bb_ctx, ['tenant','master'], true)) $__bb_ctx = '';

// Força MASTER em /sistema quando NÃO é loja por PATH e NÃO tem ?loja=
$__bb_forceMaster = ($__bb_isSistema && !$__bb_lojaByPath && empty($_GET['loja']) && empty($_POST['loja']) && empty($_GET['slug']) && empty($_POST['slug']) &&
  (
    ($__bb_ctx === 'master' && $__bb_hasMasterCookie) ||
    ($__bb_ctx !== 'tenant' && !($__bb_hasTenantCookie && !$__bb_hasMasterCookie))
  )
);

// Se é /sistema, não forçou master e existe cookie tenant => tratamos como TENANT
if (!$__bb_hasLoja && $__bb_isSistema && !$__bb_forceMaster && $__bb_hasTenantCookie) {
  $__bb_hasLoja = true;
}

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
}

require_once __DIR__ . '/sistema/tenant_crypto.php';

function bb_clean_slug(string $v): string {
  $v = strtolower(trim($v));
  $v = preg_replace('/[^a-z0-9_-]/', '', $v);
  return trim((string)$v, '-');
}

$slug = bb_clean_slug((string)($_GET['slug'] ?? ''));
$go   = strtolower(trim((string)($_GET['go'] ?? 'painel')));

if ($slug === '') {
  header('Location: /loja');
  exit;
}

$map = [
  'agendamentos' => '/agendamentos',
  'painel'       => '/painel/',
  'sistema'      => '/sistema/',
  'produtos'     => '/produtos',
  'servicos'     => '/servicos',
  'assinatura'   => '/assinatura',
];
$destino = $map[$go] ?? '/sistema/painel/';


// destino final: quando há slug, todo acesso da loja deve permanecer em /loja/<slug>/...
$destino = (string)$destino;
if (strpos($destino, '/loja/') !== 0) {
  $destino = '/loja/' . rawurlencode($slug) . '/' . ltrim($destino, '/');
}
$cfg = @include __DIR__ . '/config/marketplace.php';
if (!is_array($cfg) || empty($cfg['master'])) {
  http_response_code(500);
  exit('Config do marketplace ausente.');
}

$host = (string)($cfg['master']['host'] ?? 'localhost');
$db   = (string)($cfg['master']['db']   ?? '');
$user = (string)($cfg['master']['user'] ?? '');
$pass = (string)($cfg['master']['pass'] ?? '');
$table= (string)($cfg['master']['table'] ?? 'tenants');


// segurança: evita nome de tabela inválido
$table = preg_replace('/[^a-z0-9_]/i', '', $table);
if ($table === '') { $table = 'tenants'; }
if ($db === '' || $user === '' || $pass === '') {
  http_response_code(500);
  exit('Config master inválida (db/user/pass).');
}

try {
  $pdo = new PDO(
    "mysql:host={$host};dbname={$db};charset=utf8mb4",
    $user,
    $pass,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]
  );
} catch (Throwable $e) {
  error_log('[TENANT_REDIRECT][PDO] ' . $e->getMessage());
  http_response_code(500);
  exit('Erro ao conectar no master.');
}

$st = $pdo->prepare("SELECT * FROM `{$table}` WHERE slug = :s LIMIT 1");
$st->execute([':s' => $slug]);
$row = $st->fetch();

$statusTenant = strtolower(trim((string)($row['status'] ?? '')));
$ativoTenant = array_key_exists('ativo', (array)$row) ? (int)($row['ativo'] ?? 0) : 1;

if (!$row || $ativoTenant !== 1 || $statusTenant === 'blocked' || empty($row['db_name'])) {
  http_response_code(404);
  exit('Barbearia não encontrada ou inativa.');
}

// se trocar de tenant, evita misturar dados
if (!empty($_SESSION['tenant_slug']) && $_SESSION['tenant_slug'] !== $slug) {
  $_SESSION = [];
  session_regenerate_id(true);
}

$secret = (string)($cfg['security']['tenant_secret'] ?? '');
$dbPass = (string)($row['db_pass'] ?? '');

if ($secret !== '' && function_exists('bb_decrypt_str')) {
  $dec = bb_decrypt_str($dbPass, $secret);
  if (is_string($dec) && $dec !== '' && $dec !== $dbPass) {
    $dbPass = $dec;
  }
}

$_SESSION['tenant_id']   = (int)($row['id'] ?? 0);
$_SESSION['tenant_slug'] = $slug;
$_SESSION['tenant_db']   = (string)$row['db_name'];
$_SESSION['tenant_host'] = !empty($row['db_host']) ? (string)$row['db_host'] : 'localhost';
$_SESSION['tenant_user'] = !empty($row['db_user']) ? (string)$row['db_user'] : '';
$_SESSION['tenant_pass'] = (string)$dbPass;

header("Location: {$destino}");
exit;
