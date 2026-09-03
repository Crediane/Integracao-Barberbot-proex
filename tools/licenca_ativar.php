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
// tools/licenca_ativar.php — ativação com máscaras PRO + PRG
if (session_status() === PHP_SESSION_NONE) session_start();

/* anti-cache */
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache'); header('Expires: 0');

$envPath = dirname(__DIR__) . '/.env';
$ok = $erro = '';

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function env_write_kv(string $path, array $pairs): bool {
  $lines = is_readable($path) ? file($path, FILE_IGNORE_NEW_LINES) : [];
  $map = [];
  foreach ($lines as $ln) {
    if ($ln === '' || $ln[0] === '#') { $map[] = $ln; continue; }
    [$k,$v] = array_pad(explode('=', $ln, 2), 2, '');
    $map[$k] = $v;
  }
  foreach ($pairs as $k => $v) {
    $k = trim($k);
    $v = str_replace(["\r","\n"], '', (string)$v);
    $map[$k] = $v;
  }
  $out = [];
  foreach ($map as $k => $v) {
    if (is_int($k)) { $out[] = $v; continue; }
    $out[] = $k.'='.$v;
  }
  if (is_file($path)) @copy($path, $path.'.bak_'.date('Ymd_His'));
  $ok = (bool)@file_put_contents($path, implode(PHP_EOL, $out).PHP_EOL, LOCK_EX);
  if (function_exists('opcache_reset')) @opcache_reset();
  return $ok;
}

/* sanitizadores */
function norm_domain(string $d): string {
  $d = trim($d);
  $d = preg_replace('#^https?://#i', '', $d);
  $d = preg_replace('#/.*$#', '', $d);
  $d = preg_replace('/:\d+$/', '', $d);
  $d = preg_replace('/^www\./i', '', $d);
  $d = strtolower(preg_replace('/[^a-z0-9.\-]/i', '', $d));
  $d = preg_replace('/\.+/','.', $d); $d = rtrim($d,'.');
  return $d;
}
function is_base64_relaxed(string $s): bool {
  return $s === '' || preg_match('#^[A-Za-z0-9+/=\r\n]+$#', $s) === 1;
}

/* POST */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
  $dom   = norm_domain($_POST['dominio'] ?? '');
  $exp   = trim($_POST['expira'] ?? '');
  $grace = trim($_POST['grace']  ?? '');
  $chave = trim($_POST['assinatura'] ?? '');

  // valida domínio robusto
  $domOk = false;
  if ($dom !== '' && strlen($dom) <= 253) {
    $parts = explode('.', $dom);
    if (count($parts) >= 2) {
      $labelsOk = true;
      foreach ($parts as $p) {
        if ($p === '' || strlen($p) > 63) { $labelsOk=false; break; }
        if ($p[0] === '-' || substr($p,-1) === '-') { $labelsOk=false; break; }
        if (!preg_match('/^[a-z0-9-]+$/', $p)) { $labelsOk=false; break; }
      }
      $tld = end($parts);
      if (!preg_match('/^[a-z]{2,63}$/', $tld)) $labelsOk=false;
      $domOk = $labelsOk;
    }
  }

  if (!$domOk || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $exp)) {
    $erro = 'Preencha domínio válido e data no formato YYYY-MM-DD.';
  } elseif (!is_base64_relaxed($chave)) {
    $erro = 'A assinatura deve conter apenas caracteres base64.';
  } else {
    $pairs = [
      'LICENCA_DOMINIO' => $dom,
      'LICENCA_EXPIRA'  => $exp,
      'LICENCA_CHAVE'   => $chave, // pode ficar vazio se não usar RSA
    ];
    if ($grace !== '') {
      $g = (int)preg_replace('/\D+/', '', $grace);
      if ($g < 0) $g = 0;
      if ($g > 3650) $g = 3650;
      $pairs['LIC_GRACE_DAYS'] = (string)$g;
    }

    $ok = env_write_kv($envPath, $pairs)
        ? 'Licença atualizada com sucesso.'
        : 'Falha ao gravar .env. Verifique permissões.';

    setcookie('lic_aviso_'.date('Ymd'), '', time()-3600, '/'); // limpa popup do dia
    $_SESSION['flash_ok']  = $ok;
    $_SESSION['flash_err'] = '';
    header('Location: '.$_SERVER['REQUEST_URI']);
    exit;
  }

  $_SESSION['flash_ok']  = '';
  $_SESSION['flash_err'] = $erro;
  header('Location: '.$_SERVER['REQUEST_URI']);
  exit;
}

/* GET */
$ok   = $_SESSION['flash_ok']  ?? '';
$erro = $_SESSION['flash_err'] ?? '';
unset($_SESSION['flash_ok'], $_SESSION['flash_err']);

$hostSugerido = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '';
$hostSugerido = norm_domain($hostSugerido);
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="keywords" content="licença,.env,ativação,whatsapp,Jacy Cordeiro">
  <meta name="description" content="Gerador de licença (.env) por domínio com envio via WhatsApp.">
  <meta name="author" content="Jacy Cordeiro">
  <meta name="theme-color" content="#000000">
  <link rel="shortcut icon" href="../images/favicon.png " type="image/x-icon">
<title>Ativar Licença</title>
<style>
  :root{
    --bg:#0b1220; --panel:#0f172a; --b1:#1f2a44; --b2:#334155; --tx:#e2e8f0; --mut:#94a3b8;
    --cta:#06b6d4; --cta-tx:#001b24; --ok-bg:#052e2b; --ok-b:#1c7c74; --ok-t:#c3fff5;
    --er-bg:#3a0d12; --er-b:#7f1d1d; --er-t:#fecaca;
  }
  *{box-sizing:border-box}
  body{margin:0;background:var(--bg);color:var(--tx);font:16px/1.5 system-ui,Segoe UI,Roboto}
  .box{max-width:760px;margin:6vh auto;padding:24px;border:1px solid var(--b1);
       border-radius:14px;background:var(--panel);box-shadow:0 10px 30px rgba(0,0,0,.35)}
  h2{margin:0 0 12px;font-size:22px}
  p.muted{color:var(--mut);margin-top:6px}
  label{display:block;margin:14px 0 6px;color:#cbd5e1}
  input,textarea{width:100%;padding:12px;border-radius:10px;border:1px solid var(--b2);
                 background:#0b1220;color:var(--tx);outline:none}
  input:focus,textarea:focus{border-color:#475569;box-shadow:0 0 0 3px rgba(71,85,105,.25)}
  .row{display:grid;grid-template-columns:1fr 180px;gap:10px}
  .actions{display:flex;gap:10px;flex-wrap:wrap;margin-top:16px}
  .btn{display:inline-flex;align-items:center;gap:8px;padding:10px 16px;border-radius:10px;border:0;
       background:var(--cta);color:var(--cta-tx);font-weight:700;cursor:pointer;text-decoration:none}
  .btn[disabled]{opacity:.6;cursor:not-allowed}
  .btn-sec{background:#1e293b;color:#e2e8f0;border:1px solid var(--b2)}
  .msg-ok{background:var(--ok-bg);border:1px solid var(--ok-b);color:var(--ok-t);padding:10px;border-radius:8px;margin:10px 0}
  .msg-err{background:var(--er-bg);border:1px solid var(--er-b);color:var(--er-t);padding:10px;border-radius:8px;margin:10px 0}
  small.help{color:var(--mut);display:block;margin-top:6px}
  .top-links{display:flex;gap:8px;flex-wrap:wrap;margin-bottom:10px}
  a.link{color:#7dd3fc;text-decoration:none}
</style>
</head>
<body>
<div class="box">
  <h2>Ativar / Atualizar Licença</h2>
  <div class="top-links">
    <a class="link" href="/" rel="noopener">← Voltar ao sistema</a>
    <a class="link" href="/tools/licenca_status.php" rel="noopener">Ver status da licença</a>
  </div>
  <p class="muted">Cole os dados enviados após o pagamento. Se não usa assinatura RSA, deixe a assinatura em branco.</p>

  <?php if ($ok):   ?><div class="msg-ok"><?=h($ok)?></div><?php endif; ?>
  <?php if ($erro): ?><div class="msg-err"><?=h($erro)?></div><?php endif; ?>

  <form id="licForm" method="post" autocomplete="off" onsubmit="return handleSubmit(this)">
    <label>Domínio licenciado (sem http/https e sem www)</label>
    <input id="dominio" name="dominio" required inputmode="url" placeholder="meudominio.com.br"
           value="<?=($ok||$erro)?'':h($hostSugerido)?>"
           pattern="^(?=.{1,253}$)(?!-)(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$"
           maxlength="253" aria-describedby="domHelp domErr">
    <small id="domHelp" class="help">Ex.: <code>empresa.com.br</code> ou <code>app.empresa.com.br</code></small>
    <small id="domErr" class="msg-err" style="display:none;margin-top:6px"></small>

    <div class="row">
      <div>
        <label>Expira em (YYYY-MM-DD)</label>
        <input id="expira" name="expira" required placeholder="2025-12-31"
               pattern="^\d{4}-\d{2}-\d{2}$" inputmode="numeric" maxlength="10" autocomplete="off"
               aria-describedby="expHelp expErr">
        <small id="expHelp" class="help">Aceito 31122025 / 31/12/2025 / 2025-12-31 — normalizo pra <b>YYYY-MM-DD</b>.</small>
        <small id="expErr" class="msg-err" style="display:none;margin-top:6px"></small>
      </div>
      <div>
        <label>Carência (dias) — opcional</label>
        <input id="grace" name="grace" type="number" min="0" max="3650" placeholder="0" inputmode="numeric">
        <small class="help">0 a 3650 (10 anos).</small>
      </div>
    </div>

    <label>Assinatura (base64) — opcional</label>
    <textarea id="assinatura" name="assinatura" rows="5" placeholder="Cole aqui a assinatura base64 se usar RSA"
              aria-describedby="sigHelp sigErr"></textarea>
    <small id="sigHelp" class="help">Somente caracteres base64 (A-Z, a-z, 0-9, +, / e =). Quebras/ espaços são removidos.</small>
    <small id="sigErr" class="msg-err" style="display:none;margin-top:6px"></small>

    <div class="actions">
      <button class="btn" id="btnSalvar" type="submit"><span id="btnTxt">Salvar licença</span></button>
      <button class="btn btn-sec" type="button" onclick="limparForm()">Limpar</button>
      <button class="btn btn-sec" type="button" onclick="preencherHoje()">Hoje</button>
      <button class="btn btn-sec" type="button" onclick="maisUmAno()">+1 ano</button>
    </div>
  </form>
</div>

<script>
/* ===== Máscaras PRO ===== */
function sanitizeDomain(v){
  v = (v||'').toLowerCase();
  v = v.replace(/^https?:\/\//,'').replace(/\/.*$/,'').replace(/:\d+$/,'').replace(/^www\./,'').replace(/[^a-z0-9.\-]/g,'');
  v = v.replace(/\.+/g,'.').replace(/\.$/,'');
  return v;
}
function labelsOk(host){
  if (!host) return false;
  if (host.length < 4 || host.length > 253) return false;
  const parts = host.split('.'); if (parts.length < 2) return false;
  for (const p of parts){
    if (!p || p.length>63) return false;
    if (p.startsWith('-') || p.endsWith('-')) return false;
    if (!/^[a-z0-9-]+$/.test(p)) return false;
  }
  const tld = parts[parts.length-1]; if (!/^[a-z]{2,63}$/.test(tld)) return false;
  return true;
}
function pad2(n){ return (n<10?'0':'')+n; }
function daysInMonth(y,m){ return new Date(y,m,0).getDate(); }
function parseDateSmart(s){
  s = (s||'').trim(); if (!s) return '';
  const d = s.replace(/\D/g,'').slice(0,8); if (d.length < 6) return '';
  let y,m,day;
  if (/^(19|20)\d{2}/.test(d)){ y=+d.slice(0,4); m=+d.slice(4,6); day=+d.slice(6,8); }
  else { day=+d.slice(0,2); m=+d.slice(2,4); y=+d.slice(4,8); }
  if (!y||!m||!day) return '';
  if (m<1) m=1; if (m>12) m=12; const dim=daysInMonth(y,m);
  if (day<1) day=1; if (day>dim) day=dim;
  return `${y}-${pad2(m)}-${pad2(day)}`;
}

/* Elements */
const $dom=document.getElementById('dominio'), $domErr=document.getElementById('domErr');
const $exp=document.getElementById('expira'), $expErr=document.getElementById('expErr');
const $grc=document.getElementById('grace');
const $sig=document.getElementById('assinatura'), $sigErr=document.getElementById('sigErr');
const $btn=document.getElementById('btnSalvar'), $btnTxt=document.getElementById('btnTxt');

function showErr(el,msg){ el.textContent=msg; el.style.display='block'; }
function hideErr(el){ el.textContent=''; el.style.display='none'; }

function validateDomain(){
  $dom.value = sanitizeDomain($dom.value);
  if (!$dom.value || !labelsOk($dom.value)){ showErr($domErr,'Domínio inválido. Ex.: empresa.com.br'); return false; }
  hideErr($domErr); return true;
}
function validateDate(){
  const norm = parseDateSmart($exp.value) || $exp.value;
  if (norm && /^\d{4}-\d{2}-\d{2}$/.test(norm)){ $exp.value=norm; hideErr($expErr); return true; }
  showErr($expErr,'Data inválida. Use YYYY-MM-DD (aceito 31122025 / 31/12/2025).'); return false;
}
function normalizeGrace(){
  if ($grc.value==='') return true;
  let n = parseInt(String($grc.value).replace(/\D/g,''),10); if (isNaN(n)) n=0;
  if (n<0) n=0; if (n>3650) n=3650; $grc.value=String(n); return true;
}
function tidyBase64(){
  let v = $sig.value.replace(/\s+/g,''); $sig.value=v;
  if (!/^[A-Za-z0-9+/=]*$/.test(v)){ showErr($sigErr,'Use apenas caracteres base64.'); return false; }
  hideErr($sigErr); return true;
}

/* Eventos */
$dom.addEventListener('input', validateDomain); $dom.addEventListener('blur', validateDomain);
$exp.addEventListener('input', ()=>{ const n=parseDateSmart($exp.value); if(n) $exp.value=n; validateDate(); });
$exp.addEventListener('blur', validateDate);
$grc.addEventListener('input', normalizeGrace);
$sig.addEventListener('input', tidyBase64);

function limparForm(){ $dom.value=''; $exp.value=''; $grc.value=''; $sig.value=''; hideErr($domErr); hideErr($expErr); hideErr($sigErr); $dom.focus(); }
function preencherHoje(){ const d=new Date(); $exp.value=`${d.getFullYear()}-${pad2(d.getMonth()+1)}-${pad2(d.getDate())}`; hideErr($expErr); }
function maisUmAno(){ const d=new Date(); d.setFullYear(d.getFullYear()+1); $exp.value=`${d.getFullYear()}-${pad2(d.getMonth()+1)}-${pad2(d.getDate())}`; hideErr($expErr); }

function handleSubmit(){
  const okDom=validateDomain(), okDt=validateDate(), okGr=normalizeGrace(), okSig=tidyBase64();
  if (!(okDom && okDt && okGr && okSig)) return false;
  $btn.disabled=true; $btnTxt.textContent='Salvando…'; return true;
}
</script>
</body>
</html>
