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
if (session_status() === PHP_SESSION_NONE) session_start();
function _strip_www($h){ return strtolower(preg_replace('/^www\./i','',trim(preg_replace('/:\d+$/','',$h)))); }
function _env_load($f){ $o=[]; if(!is_readable($f))return $o; foreach(file($f, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $ln){
  $ln=trim($ln); if($ln===''||$ln[0]==='#')continue; [$k,$v]=array_pad(explode('=',$ln,2),2,''); $o[$k]=trim($v); } return $o; }
$env = _env_load(dirname(__DIR__).'/.env');
$dom = _strip_www($env['LICENCA_DOMINIO'] ?? '');
$exp = $env['LICENCA_EXPIRA'] ?? '';
$grc = (int)($env['LIC_GRACE_DAYS'] ?? 0);
$host= _strip_www($_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '');
$hoje = new DateTimeImmutable('today');
$expDt = ($exp ? DateTimeImmutable::createFromFormat('Y-m-d',$exp) : null);
$dias = $expDt ? (int)$hoje->diff($expDt)->format('%r%a') : null;
header('Content-Type: text/plain; charset=UTF-8');
echo "Host atual........: $host\n";
echo "Domínio licenciado: $dom\n";
echo "Expira em.........: ".($exp?:'vitalícia')."\n";
echo "Carência (dias)...: $grc\n";
echo "Diferença (dias)..: ".($dias===null?'—':$dias)."\n";
$ok_dom  = ($dom!=='' && $host === $dom);
$ok_data = ($dias===null) ? true : ($dias > -$grc);
echo "Válido agora?.....: ".(($ok_dom && $ok_data)?'SIM':'NÃO')."\n";
