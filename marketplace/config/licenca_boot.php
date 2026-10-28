<?php

/**
 * config/licenca_boot.php
 * - Valida domínio, expiração e (opcional) assinatura RSA
 * - Pop-up diário nos últimos N dias
 * - BLOQUEIA no dia do vencimento (GRACE=0) ou após exp+GRACE
 * PHP 7.4+ / 8.x
 */

if (defined('LICENCA_BOOT_INCLUDED')) return;
define('LICENCA_BOOT_INCLUDED', true);

/* ===== DEBUG (anti "página branca") =====
   Acrescente ?lic_debug=1 à URL para ver fatals. */
$__DBG = isset($_GET['lic_debug']);
if ($__DBG) {
  ini_set('display_errors','1');
  ini_set('display_startup_errors','1');
  error_reporting(E_ALL);
  register_shutdown_function(function(){
    $e = error_get_last();
    if ($e && in_array($e['type'], [E_ERROR,E_PARSE,E_CORE_ERROR,E_COMPILE_ERROR])) {
      header('Content-Type:text/plain; charset=UTF-8');
      echo "FATAL: {$e['message']} in {$e['file']}:{$e['line']}\n";
    }
  });
}

/* ===== Acesso direto? Só mensagem. ===== */
if (($_SERVER['SCRIPT_FILENAME'] ?? '') !== '' && basename($_SERVER['SCRIPT_FILENAME']) === basename(__FILE__)) {
  header('Content-Type:text/plain; charset=UTF-8');
  echo "OK: licenca_boot.php carregado.\nEste arquivo deve ser INCLUÍDO nas páginas do sistema.\n";
  echo "Status: /tools/licenca_status.php\n";
  exit;
}

/* ===== Marca / contato ===== */
$BRAND = [
  'nome'       => 'Jacy Cordeiro — Assessor de Marketing',
  'site'       => 'https://jacycordeiro.com.br/',
  'whats'      => '(45) 99958-0058',
  'whats_link' => 'https://wa.me/5545999580058',
];

/* ===== (Opcional) RSA. Se não configurar, é permissivo. ===== */
const LICENSE_PUBLIC_KEY = ''; // cole o PEM (BEGIN/END) se for usar assinatura

/* ===== Utils ===== */
function _strip_www(string $h): string {
  $h = trim($h);
  if ($h === '') return '';
  $h = preg_replace('/:\d+$/', '', $h); // remove porta
  return strtolower(preg_replace('/^www\./i', '', $h));
}
function _env_load(string $file): array {
  $out = [];
  if (!is_readable($file)) return $out;
  foreach (file($file, FILE_IGNORE_NEW_LINES|FILE_SKIP_EMPTY_LINES) as $ln) {
    $ln = trim($ln);
    if ($ln === '' || $ln[0] === '#') continue;
    [$k,$v] = array_pad(explode('=', $ln, 2), 2, '');
    $k = trim($k);
    $v = trim($v, " \t\n\r\0\x0B\"'");
    if ($k !== '') $out[$k] = $v;
  }
  return $out;
}
function _verify_rsa(string $data, string $b64sig): bool {
  $pem = trim(LICENSE_PUBLIC_KEY);
  if ($pem === '' || strpos($pem, 'BEGIN PUBLIC KEY') === false) return true;
  if (!function_exists('openssl_verify') || !function_exists('openssl_pkey_get_public')) return true;
  $pub = @openssl_pkey_get_public($pem);
  if ($pub === false) return true;
  $sig = base64_decode($b64sig, true);
  if ($sig === false) { if (PHP_VERSION_ID < 80000 && is_resource($pub)) @openssl_free_key($pub); return false; }
  $ok = openssl_verify($data, $sig, $pub, OPENSSL_ALGO_SHA256);
  if (PHP_VERSION_ID < 80000 && is_resource($pub)) @openssl_free_key($pub);
  return $ok === 1;
}

/* ===== .env ===== */
$ENV = _env_load(dirname(__DIR__) . '/.env');

$LIC_DOM     = _strip_www($ENV['LICENCA_DOMINIO'] ?? '');
$LIC_EXPIRA  = trim($ENV['LICENCA_EXPIRA']  ?? '');   // YYYY-MM-DD
$LIC_CHAVE   = trim($ENV['LICENCA_CHAVE']   ?? '');   // base64 opcional
$ALERT_RAW   = trim($ENV['LIC_ALERT_DAYS']  ?? '7');  // "7" ou "7,3,1"
$LIC_GRACE_DAYS = (int)($ENV['LIC_GRACE_DAYS'] ?? 0);

/* ===== domínio atual ===== */
$hostHdr   = $_SERVER['HTTP_X_FORWARDED_HOST'] ?? $_SERVER['HTTP_HOST'] ?? '';
$hostAtual = _strip_www($hostHdr);

/* ===== regras ===== */
$ok_dom = ($LIC_DOM !== '' && $hostAtual === $LIC_DOM);

$hoje      = new DateTimeImmutable('today');
$expDt     = ($LIC_EXPIRA !== '' ? DateTimeImmutable::createFromFormat('Y-m-d', $LIC_EXPIRA) : null);
$ok_data   = true;
$dias_rest = null;

if ($expDt instanceof DateTimeImmutable) {
  // diff = expiração - hoje (em dias). exp=hoje => diff=0
  $diff      = (int)$hoje->diff($expDt)->format('%r%a');
  $dias_rest = $diff;

  // ===== BLOQUEIA NO DIA DO VENCIMENTO =====
  // válido se hoje < (expiração + carência)  =>  diff > -GRACE
  // GRACE=0 => exige diff>0 (hoje já bloqueia)
  $ok_data   = ($diff > -$LIC_GRACE_DAYS);
}

$ok_chave = true;
if ($LIC_CHAVE !== '' && $LIC_EXPIRA !== '') {
  $ok_chave = _verify_rsa($LIC_DOM.'|'.$LIC_EXPIRA, $LIC_CHAVE);
}

/* ===== link do ativador: checa no filesystem e escolhe o que EXISTE ===== */
$root = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
$candidatos = [
  '/tools/licenca_ativar.php',
  '/sistema/tools/licenca_ativar.php',
  '/admin/tools/licenca_ativar.php',
];
$ativar_href = '/tools/licenca_ativar.php';
foreach ($candidatos as $c) {
  if ($root && is_file($root.$c)) { $ativar_href = $c; break; }
}

/* ===== BLOQUEIO ===== */
if (!$ok_dom || !$ok_data || !$ok_chave) {
  http_response_code(403);
  $mot = [];
  if (!$ok_dom)   $mot[] = 'Domínio não autorizado';
  if (!$ok_data)  $mot[] = 'Licença expirada';
  if (!$ok_chave) $mot[] = 'Assinatura inválida';
  $msg = implode(' • ', $mot);
  $exp_text = ($LIC_EXPIRA ?: 'vitalícia');
  ?>
  <!doctype html>
  <html lang="pt-br"><head>
  <meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="keywords" content="licença,.env,ativação,whatsapp,Jacy Cordeiro">
  <meta name="description" content="Gerador de licença (.env) por domínio com envio via WhatsApp.">
  <meta name="author" content="Jacy Cordeiro">
  <meta name="theme-color" content="#000000">
  <link rel="shortcut icon" href="../images/favicon.png " type="image/x-icon">
  <title>Licença inválida</title>
  <style>
    body{margin:0;background:#0b1220;color:#e2e8f0;font:16px/1.5 system-ui,Segoe UI,Roboto}
    .box{max-width:720px;margin:10vh auto;padding:28px;border:1px solid #1f2a44;border-radius:14px;
         background:linear-gradient(180deg, rgba(31,41,72,.6), rgba(15,23,42,.95));box-shadow:0 10px 30px rgba(0,0,0,.35)}
    h1{margin:0 0 10px;font-size:22px}
    .muted{color:#94a3b8}
    .btn{display:inline-block;padding:10px 14px;border-radius:10px;background:#06b6d4;color:#001b24;text-decoration:none;font-weight:700}
    .pill{display:inline-block;padding:4px 10px;border-radius:999px;background:#dc2626;color:#fff;font-size:12px;margin-left:8px}
    .space{height:10px}
    a{color:#7dd3fc}
    .btn-alt{background:#1e293b;color:#e2e8f0;border:1px solid #334155;text-decoration:none}
  </style>
  </head><body>
  <div class="box">
    <h1>Licença inválida <span class="pill"><?= htmlspecialchars($msg) ?></span></h1>
    <div class="muted">Domínio atual: <b><?= htmlspecialchars($hostAtual) ?></b></div>
    <div class="muted">Domínio licenciado: <b><?= htmlspecialchars($LIC_DOM ?: '—') ?></b></div>
    <div class="muted">Expira em: <b><?= htmlspecialchars($exp_text) ?></b></div>
    <div class="space"></div>
    <a class="btn btn-alt" href="<?= htmlspecialchars($ativar_href) ?>">Já pagou? Ativar licença</a>
    <a class="btn" href="<?= htmlspecialchars($BRAND['whats_link']) ?>" target="_blank" rel="noopener">Falar no WhatsApp</a>
    <span class="muted" style="margin-left:10px">ou <a href="<?= htmlspecialchars($BRAND['site']) ?>" target="_blank" rel="noopener">jacycordeiro.com.br</a></span>
  </div>
  </body></html>
  <?php
  exit;
}

/* ===== POP-UP diário nos últimos N dias ===== */
if ($expDt instanceof DateTimeImmutable) {
  $nums = array_map('intval', array_filter(array_map('trim', explode(',', $ALERT_RAW)), 'strlen'));
  $janelaDias = $nums ? max($nums) : 0;
  if ($dias_rest !== null && $dias_rest >= 0 && $dias_rest <= $janelaDias) {
    $cookieName = 'lic_aviso_' . date('Ymd'); // 1x por dia
    if (empty($_COOKIE[$cookieName])) {
      setcookie($cookieName, '1', time() + 86400, '/', '', false, true);
      $quando  = $expDt->format('d/m/Y');
      $contato = htmlspecialchars($BRAND['whats_link']);
      echo <<<HTML
<script>
document.addEventListener('DOMContentLoaded', function(){
  if (document.getElementById('lic-pop')) return;
  var o = document.createElement('div');
  o.id = 'lic-pop';
  o.innerHTML =
    '<div class="lic-back"></div>'+
    '<div class="lic-modal">'+
      '<h3>Sua licença expirará em <b>{$quando}</b></h3>'+
      '<p>Para evitar interrupções, regularize até a data informada.</p>'+
      '<div class="lic-actions">'+
        '<a class="lic-btn lic-ok" href="#" id="licOk">Entendi</a>'+
        '<a class="lic-btn lic-cta" target="_blank" rel="noopener" href="{$contato}">Falar no WhatsApp</a>'+
      '</div>'+
    '</div>';
  document.body.appendChild(o);
  document.getElementById('licOk').onclick = function(e){ e.preventDefault(); o.remove(); };
  var css = document.createElement('style');
  css.textContent = `
  #lic-pop{position:fixed;inset:0;z-index:999999;display:grid;place-items:center}
  .lic-back{position:absolute;inset:0;background:rgba(9,14,24,.7);backdrop-filter:blur(2px)}
  .lic-modal{position:relative;z-index:2;width:min(520px,94%);background:#0f172a;color:#e2e8f0;
             border:1px solid #1f2a44;border-radius:14px;padding:22px;box-shadow:0 10px 30px rgba(0,0,0,.35)}
  .lic-modal h3{margin:0 0 8px;font:600 18px/1.3 system-ui,Segoe UI,Roboto}
  .lic-modal p{margin:0 0 14px;color:#94a3b8}
  .lic-actions{display:flex;gap:10px;flex-wrap:wrap}
  .lic-btn{display:inline-block;padding:10px 14px;border-radius:10px;text-decoration:none;font-weight:700}
  .lic-ok{background:#1e293b;color:#e2e8f0;border:1px solid #334155}
  .lic-cta{background:#06b6d4;color:#001b24}
  `;
  document.head.appendChild(css);
});
</script>
HTML;
    }
  }
}
