<?php
@session_start();

/* ===================== DEBUG (opcional) ===================== */
$debug = isset($_GET['debug']) && $_GET['debug'] == '1';
if ($debug) {
  ini_set('display_errors', '1');
  ini_set('display_startup_errors', '1');
  error_reporting(E_ALL);
} else {
  ini_set('display_errors', '0');
  error_reporting(0);
}

/* ===================== HEADERS DE SEGURANÇA ===================== */
if (!headers_sent()) {
  header('Content-Type: text/html; charset=UTF-8');
  header('X-Content-Type-Options: nosniff');
  header('X-Frame-Options: SAMEORIGIN');
  header('Referrer-Policy: strict-origin-when-cross-origin');
  header("Permissions-Policy: geolocation=(), camera=(), microphone=(), payment=()");
}

/* ===================== HELPERS ===================== */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }

/* ===================== FALLBACKS ===================== */
$nome_sistema = 'BarberBot';
$icone_site   = 'favicon.ico';

/* ===================== BUSCAR CONFIG (SEM QUEBRAR) ===================== */
$cfg_err = '';
try {
  $conPath = __DIR__ . '/sistema/conexao.php'; // caminho correto: /sistema/conexao.php
  if (is_file($conPath)) {
    require_once($conPath);

    if (isset($pdo) && $pdo instanceof PDO) {
      $cfg = $pdo->query("SELECT * FROM config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
      if ($cfg) {
        if (!empty($cfg['nome_sistema'])) $nome_sistema = $cfg['nome_sistema'];

        // tenta chaves comuns do BarberBot
        if (!empty($cfg['icone_site']))      $icone_site = $cfg['icone_site'];
        elseif (!empty($cfg['icone']))       $icone_site = $cfg['icone'];
        elseif (!empty($cfg['favicon']))     $icone_site = $cfg['favicon'];
        elseif (!empty($cfg['logo']))        $icone_site = $cfg['logo'];
      }
    }
  }
} catch (Throwable $e) {
  $cfg_err = $e->getMessage();
}

/* ===================== IP REAL (PROXY/CDN) ===================== */
$ip = '';
$origem = 'N/A';

if (!empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
  $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
  $origem = 'Cloudflare';
} elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
  $ip = $_SERVER['HTTP_X_REAL_IP'];
  $origem = 'Proxy Real IP';
} elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
  $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
  $origem = 'X-Forwarded-For';
} elseif (!empty($_SERVER['REMOTE_ADDR'])) {
  $ip = $_SERVER['REMOTE_ADDR'];
  $origem = 'REMOTE_ADDR';
} else {
  $ip = 'Não detectado';
  $origem = 'N/A';
}

$ip = trim((string)$ip);
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? 'N/A';
$dataHora  = date('d/m/Y H:i:s');

/* ===================== META ===================== */
$meta_keywords = "Salão, Barbearia, BarberBot, segurança, whitelist, IP, painel, sistema";
$meta_desc     = "Meu IP (BarberBot): copie seu IP e cole na whitelist de segurança do sistema.";
$meta_author   = $nome_sistema;

/* ===================== FAVICON URL ===================== */
$faviconHref = $icone_site;
if (!preg_match('#^https?://#i', $faviconHref) && substr($faviconHref, 0, 1) !== '/') {
  // padrão do seu site: images/<icone>
  $faviconHref = 'images/' . $faviconHref;
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">

  <meta name="keywords" content="<?php echo h($meta_keywords); ?>">
  <meta name="description" content="<?php echo h($meta_desc); ?>">
  <meta name="author" content="<?php echo h($meta_author); ?>">
  <meta name="theme-color" content="#000000">

  <link rel="shortcut icon" href="<?php echo h($faviconHref); ?>" type="image/x-icon">
  <title><?php echo h($nome_sistema); ?> — Meu IP</title>

  <style>
    :root{
      --bg:#070708;
      --card:#0f0f10;
      --border:#2a2a2c;
      --text:#ffffff;
      --muted:#b0b0b0;
      --brand:#0d6efd;
      --ok:#34a853;
      --warn:#fbbc04;
    }
    *{box-sizing:border-box}
    body{
      margin:0;
      font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      background: radial-gradient(1200px 600px at 20% 0%, rgba(13,110,253,.25), transparent 60%),
                  radial-gradient(900px 500px at 80% 20%, rgba(52,168,83,.18), transparent 60%),
                  var(--bg);
      color:var(--text);
      min-height:100vh;
      display:flex;
      align-items:center;
      justify-content:center;
      padding:28px;
    }
    .wrap{width:100%;max-width:920px}
    .top{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:14px;
      margin-bottom:16px;
    }
    .brand{
      display:flex;
      align-items:center;
      gap:10px;
      font-weight:800;
      letter-spacing:.2px;
    }
    .pill{
      font-size:12px;
      color:#cfe2ff;
      background:rgba(13,110,253,.15);
      border:1px solid rgba(13,110,253,.35);
      padding:6px 10px;
      border-radius:999px;
      white-space:nowrap;
    }
    .card{
      background:rgba(15,15,16,.92);
      border:1px solid var(--border);
      border-radius:16px;
      padding:22px;
      box-shadow: 0 20px 60px rgba(0,0,0,.35);
      overflow:hidden;
    }
    .title{
      font-size:22px;
      font-weight:800;
      margin:0 0 6px 0;
    }
    .subtitle{
      margin:0 0 18px 0;
      color:var(--muted);
      line-height:1.45;
    }
    .grid{
      display:grid;
      grid-template-columns: 1fr 1fr;
      gap:14px;
    }
    @media(max-width:720px){
      .grid{grid-template-columns:1fr}
      .top{flex-direction:column;align-items:flex-start}
    }
    .item{
      background:rgba(255,255,255,.03);
      border:1px solid rgba(255,255,255,.08);
      border-radius:14px;
      padding:14px;
      min-height:76px;
    }
    .label{
      font-size:12px;
      color:var(--muted);
      text-transform:uppercase;
      letter-spacing:.12em;
      margin-bottom:6px;
    }
    .value{
      font-size:16px;
      font-weight:700;
      word-break:break-word;
    }
    .actions{
      display:flex;
      gap:10px;
      flex-wrap:wrap;
      margin-top:16px;
      align-items:center;
    }
    .btn{
      border:0;
      border-radius:12px;
      padding:10px 14px;
      font-weight:800;
      cursor:pointer;
      transition:.15s;
      display:inline-flex;
      align-items:center;
      gap:10px;
      user-select:none;
    }
    .btn-primary{background:var(--brand); color:#fff;}
    .btn-primary:hover{filter:brightness(1.06);}
    .btn-ghost{
      background:rgba(255,255,255,.06);
      color:#fff;
      border:1px solid rgba(255,255,255,.12);
    }
    .btn-ghost:hover{background:rgba(255,255,255,.09);}
    .hint{
      margin-top:14px;
      padding:12px 14px;
      border-radius:14px;
      background:rgba(52,168,83,.10);
      border:1px solid rgba(52,168,83,.35);
      color:#dff7e6;
      line-height:1.45;
    }
    code{
      background:rgba(0,0,0,.25);
      border:1px solid rgba(255,255,255,.08);
      padding:2px 8px;
      border-radius:10px;
      font-family: ui-monospace, SFMono-Regular, Menlo, Monaco, Consolas, "Liberation Mono", monospace;
      font-size:13px;
      color:#fff;
    }
    .msg{
      margin-left:auto;
      color:var(--muted);
      font-size:13px;
    }
    .debug{
      margin-top:12px;
      padding:12px 14px;
      border-radius:14px;
      background:rgba(251,188,4,.10);
      border:1px solid rgba(251,188,4,.35);
      color:#fff3cd;
      line-height:1.45;
      word-break:break-word;
    }
  </style>
</head>
<body>
  <div class="wrap">
    <div class="top">
      <div class="brand">
        <span style="font-size:20px">🤖</span>
        <span><?php echo h($nome_sistema); ?> — Meu IP</span>
      </div>
      <span class="pill">Suporte / Whitelist de Segurança</span>
    </div>

    <div class="card">
      <h1 class="title">Seu IP atual</h1>
      <p class="subtitle">
        Copie o IP abaixo e cole no <strong>security.php</strong> para liberar o acesso (whitelist).
      </p>

      <div class="grid">
        <div class="item">
          <div class="label">IP</div>
          <div class="value" id="ipValue"><?php echo h($ip); ?></div>
        </div>

        <div class="item">
          <div class="label">Origem</div>
          <div class="value"><?php echo h($origem); ?></div>
        </div>

        <div class="item">
          <div class="label">User-Agent</div>
          <div class="value"><?php echo h($userAgent); ?></div>
        </div>

        <div class="item">
          <div class="label">Data / Hora</div>
          <div class="value"><?php echo h($dataHora); ?></div>
        </div>
      </div>

      <div class="actions">
        <button class="btn btn-primary" type="button" id="btnCopiar">📋 Copiar IP</button>
        <button class="btn btn-ghost" type="button" id="btnCopiarLinha">✅ Copiar linha do array</button>
        <span class="msg" id="msg"></span>
      </div>

      <div class="hint">
        Cole este IP no array <code>$SEC_WHITELIST_IPS</code> do arquivo <code>security.php</code>.
      </div>

      <?php if ($debug && $cfg_err !== ''): ?>
      <div class="debug">
        <strong>DEBUG:</strong> erro ao carregar config/conexão: <br>
        <code><?php echo h($cfg_err); ?></code>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <script>
    (function(){
      var ip = <?php echo json_encode($ip, JSON_UNESCAPED_UNICODE); ?>;
      var msg = document.getElementById('msg');

      function toast(t){
        msg.textContent = t;
        setTimeout(function(){ msg.textContent=''; }, 2500);
      }

      function copiar(texto){
        if (navigator.clipboard && window.isSecureContext) {
          return navigator.clipboard.writeText(texto);
        }
        return new Promise(function(resolve, reject){
          try{
            var ta = document.createElement('textarea');
            ta.value = texto;
            ta.style.position='fixed';
            ta.style.left='-9999px';
            document.body.appendChild(ta);
            ta.focus();
            ta.select();
            var ok = document.execCommand('copy');
            document.body.removeChild(ta);
            ok ? resolve() : reject();
          }catch(e){ reject(e); }
        });
      }

      document.getElementById('btnCopiar').addEventListener('click', function(){
        copiar(ip).then(function(){ toast('IP copiado com sucesso!'); })
                 .catch(function(){ toast('Não foi possível copiar automaticamente.'); });
      });

      document.getElementById('btnCopiarLinha').addEventListener('click', function(){
        var linha = "  '" + ip + "',";
        copiar(linha).then(function(){ toast('Linha do array copiada!'); })
                    .catch(function(){ toast('Não foi possível copiar automaticamente.'); });
      });
    })();
  </script>
</body>
</html>
