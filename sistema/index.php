<?php
// ARQUIVO: public_html/sistema/index.php
// ======================================================================
// BOOTSTRAP DE SESSÃO (MASTER x TENANT) + TENANT POR SLUG (BB_TENANT_<hash>)
// - Mantém a lógica do sistema (apenas evita mistura de lojas no mesmo navegador)
// ======================================================================

$__bb_reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$__bb_scriptPath = str_replace('\\', '/', (string)($_SERVER['SCRIPT_NAME'] ?? ''));
$__bb_systemPos = stripos($__bb_scriptPath, '/sistema/');
$__bb_webBase = ($__bb_systemPos !== false)
  ? rtrim(substr($__bb_scriptPath, 0, $__bb_systemPos), '/')
  : '';
$__bb_routePath = $__bb_reqPath;
if ($__bb_webBase !== '' && stripos($__bb_routePath, $__bb_webBase) === 0) {
  $__bb_routePath = substr($__bb_routePath, strlen($__bb_webBase));
  if ($__bb_routePath === '') $__bb_routePath = '/';
}
$__bb_hostLocal = strtolower(preg_replace('/:\\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
$__bb_isLocal = in_array($__bb_hostLocal, ['localhost', '127.0.0.1', '::1'], true)
  || substr($__bb_hostLocal, -5) === '.test';

if (!function_exists('bb_sess_clean_slug')) {
  function bb_sess_clean_slug($v) {
    $v = strtolower(trim((string)$v));
    return preg_replace('/[^a-z0-9_-]/', '', $v);
  }
}

// Detecta o tenant somente pela requisição atual.
// HTTP_REFERER, BB_CTX e cookies antigos nunca transformam o Link Master em loja.
$__bb_reqSlug = '';
if (!empty($_GET['loja'])) {
  $__bb_reqSlug = bb_sess_clean_slug($_GET['loja']);
} elseif (!empty($_POST['loja'])) {
  $__bb_reqSlug = bb_sess_clean_slug($_POST['loja']);
} elseif (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_routePath, $mSlug)) {
  $__bb_reqSlug = bb_sess_clean_slug($mSlug[1]);
}

$__bb_lojaByPath = (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_routePath);
$__bb_hasLoja = ($__bb_reqSlug !== '') || $__bb_lojaByPath;
$__bb_isSistema = (strpos($__bb_routePath, '/sistema') === 0);

// /sistema sem slug explícito é sempre MASTER.
// Em domínio próprio, conexao.php confirma o host e troca para a sessão do tenant.
$__bb_forceMaster = ($__bb_isSistema && !$__bb_hasLoja);

$__bb_tenantSessionName = 'BB_TENANT';
if ($__bb_reqSlug !== '') {
  $__bb_tenantSessionName = 'BB_TENANT_' . strtoupper(substr(sha1($__bb_reqSlug), 0, 12));
}

$__bb_desiredSession = $__bb_forceMaster
  ? 'BB_MASTER'
  : ($__bb_hasLoja ? $__bb_tenantSessionName : 'BB_SITE');

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

if (!headers_sent()) {
  $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  $ctxVal = (strpos(session_name(), 'BB_TENANT') === 0)
    ? 'tenant'
    : ((session_name() === 'BB_MASTER') ? 'master' : '');

  if ($ctxVal !== '') {
    @setcookie('BB_CTX', $ctxVal, [
      'expires'  => time() + 60*60*24*30,
      'path'     => '/',
      'secure'   => $secure,
      'httponly' => true,
      'samesite' => 'Lax',
    ]);
  }
}
header('Content-Type: text/html; charset=utf-8');

require_once __DIR__ . "/conexao.php";

// slug atual (prioridade: GET -> URL /loja/slug -> sessão)
$lojaSlug = '';
if (!empty($_GET['loja'])) {
  $lojaSlug = (string)$_GET['loja'];
} else {
  // tenta detectar pela URL /loja/slug/sistema/...
  $uri = $__bb_routePath;
  $seg = array_values(array_filter(explode('/', trim($uri, '/'))));
  if (isset($seg[0], $seg[1]) && $seg[0] === 'loja' && $seg[1] !== '') {
    $lojaSlug = (string)$seg[1];
  } elseif (!empty($_SESSION['tenant_slug'])) {
    $lojaSlug = (string)$_SESSION['tenant_slug'];
  }
}

$lojaSlug = strtolower(preg_replace('/[^a-z0-9_-]/', '', $lojaSlug));
$lojaQS = ($lojaSlug !== '') ? ('?loja=' . urlencode($lojaSlug)) : '';

// se não conectou, mostra erro
if (!isset($pdo) || !($pdo instanceof PDO)) {
  $loja = $lojaSlug ?: 'Desconhecida';
  ?>
  <!DOCTYPE html>
  <html lang="pt-BR">
  <head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erro de Conexão</title>
    <style>
      body { font-family: Arial, sans-serif; background:#f4f6f9; display:flex; align-items:center; justify-content:center; height:100vh; margin:0; }
      .box { background:#fff; padding:28px; border-radius:12px; max-width:560px; box-shadow:0 10px 25px rgba(0,0,0,.12); border-top:5px solid #dc3545; }
      h1 { margin:0 0 10px; color:#dc3545; font-size:20px; }
      p { color:#444; line-height:1.5; }
      .btn { display:inline-block; margin-top:12px; padding:10px 16px; background:#0d6efd; color:#fff; text-decoration:none; border-radius:8px; }
    </style>
  </head>
  <body>
    <div class="box">
      <h1>Sistema não conectado</h1>
      <p>Não foi possível acessar o banco da loja: <b><?=htmlspecialchars($loja, ENT_QUOTES, 'UTF-8')?></b></p>
      <p>Verifique se o banco foi criado e se o usuário possui as permissões necessárias.</p>
      <a class="btn" href="<?php echo htmlspecialchars($__bb_webBase . '/marketplace.php', ENT_QUOTES, 'UTF-8'); ?>">Voltar ao Marketplace</a>
    </div>
  </body>
  </html>
  <?php
  exit();
}

// não cria administrador padrão aqui (isso deve acontecer no finalizar_tenant.php)
$bodyStyle = '';
if (!empty($fundo_login) && $fundo_login !== "sem-foto.png") {
  $bodyStyle = "background: url('img/" . rawurlencode((string)$fundo_login) . "') no-repeat center center fixed; background-size: cover;";
}

// logo/fav dinâmicos (mantém compatibilidade: se não existir, cai em fallback que EXISTE)
$logoFile = !empty($logo_sistema) ? (string)$logo_sistema : 'logo.png';

// Prioridade do favicon:
// 1) $icone_sistema (se existir e se o arquivo realmente existir em /img)
// 2) favicon.ico / favicon.png (se existir)
// 3) usa o próprio logo como ícone (evita 404 / "ícone bugado")
$favCandidates = [];
if (!empty($icone_sistema)) {
  $favCandidates[] = basename((string)$icone_sistema);
}
$favCandidates = array_merge($favCandidates, ['favicon.ico', 'favicon.png', 'logo.png']);

if (!file_exists(__DIR__ . "/img/" . $logoFile)) {
  $logoFile = "logo.png";
}

// resolve favicon real (sem chute: só usa se o arquivo existir)
$favFile = '';
foreach ($favCandidates as $cand) {
  $cand = basename((string)$cand);
  if ($cand === '') continue;

  if (file_exists(__DIR__ . "/img/" . $cand)) {
    $favFile = $cand;
    break;
  }
}

// se não achou nenhum, usa o logo (último fallback)
if ($favFile === '') {
  $favFile = $logoFile;
}

// define o mime type correto do favicon
$favExt = strtolower(pathinfo($favFile, PATHINFO_EXTENSION));
$favType = 'image/png';
if ($favExt === 'ico') $favType = 'image/x-icon';
elseif ($favExt === 'svg') $favType = 'image/svg+xml';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($nome_sistema ?? 'Login', ENT_QUOTES, 'UTF-8'); ?></title>

  <link href="//netdna.bootstrapcdn.com/bootstrap/3.0.3/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" type="text/css" href="css/estilo-login.css">

  <link rel="icon" type="<?php echo htmlspecialchars($favType, ENT_QUOTES, 'UTF-8'); ?>" href="img/<?php echo htmlspecialchars($favFile, ENT_QUOTES, 'UTF-8'); ?>">
  <link rel="shortcut icon" type="<?php echo htmlspecialchars($favType, ENT_QUOTES, 'UTF-8'); ?>" href="img/<?php echo htmlspecialchars($favFile, ENT_QUOTES, 'UTF-8'); ?>">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.6/dist/sweetalert2.min.css">
</head>
<body style="<?php echo htmlspecialchars($bodyStyle, ENT_QUOTES, 'UTF-8'); ?>">

<div class="container">
  <div class="row vertical-offset-100">
    <div class="col-md-4 col-md-offset-4">
      <div class="panel panel-default form-login" style="opacity:0.95; border-radius: 20px; padding-bottom:20px;">

        <div class="panel-heading" align="center" style="border-top-right-radius: 20px; border-top-left-radius: 20px">
          <?php if(file_exists(__DIR__ . "/img/" . $logoFile)): ?>
            <img src="img/<?php echo htmlspecialchars($logoFile, ENT_QUOTES, 'UTF-8'); ?>" width="150px">
          <?php else: ?>
            <h3><?php echo htmlspecialchars($nome_sistema ?? 'Login', ENT_QUOTES, 'UTF-8'); ?></h3>
          <?php endif; ?>
        </div>

        <p class="recuperar" style="font-weight:bold; margin-top:10px;">Acesso ao Painel</p>

        <div class="panel-body">
          <form accept-charset="UTF-8" role="form" action="<?php echo htmlspecialchars($__bb_webBase . '/sistema/autenticar.php' . $lojaQS, ENT_QUOTES, 'UTF-8'); ?>" method="post">
          <input type="hidden" name="loja" value="<?php echo htmlspecialchars($lojaSlug ?? '', ENT_QUOTES, 'UTF-8'); ?>">


            <fieldset>
              <div class="form-group">
                <input class="form-control" placeholder="E-mail ou CPF" name="email" type="text" required>
              </div>
              <div class="form-group">
                <input class="form-control" placeholder="Senha" name="senha" type="password" required>
              </div>

              <?php if (!$__bb_isLocal): ?>
              <div class="g-recaptcha" data-sitekey="6LdltT0fAAAAAGA1M26pB2eoBeJSdVW3XWMcxbS-"></div>
              <br>
              <?php endif; ?>

              <input class="btn btn-lg btn-primary btn-block" type="submit" value="Login">
            </fieldset>

            <p class="recuperar" style="margin-top:15px;">
              <a href="#" data-toggle="modal" data-target="#modalRecuperar">Esqueci minha senha</a>
            </p>
            <p class="recuperar">
              <a href="acesso<?php echo $lojaQS; ?>" target="_blank">[Acessar Painel do Cliente]</a>
            </p>
          </form>
        </div>

      </div>
    </div>
  </div>
</div>

<div class="modal fade" id="modalRecuperar" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        <h4 class="modal-title">Recuperar Senha</h4>
      </div>
      <div class="modal-body">
        <form method="post" id="form-recuperar">
          <input placeholder="Digite seu email" class="form-control" type="email" name="email" id="email-recuperar" required>
          <br>
          <button type="submit" class="btn btn-primary">Recuperar</button>
        </form>
      </div>
    </div>
  </div>
</div>

<script src="//code.jquery.com/jquery-1.11.1.min.js"></script>
<script src="//netdna.bootstrapcdn.com/bootstrap/3.0.3/js/bootstrap.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.6/dist/sweetalert2.all.min.js"></script>
<?php if (!$__bb_isLocal): ?>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>
<?php endif; ?>

<script>
$(document).ready(function(){
  <?php if(isset($_GET['erro'])):
    $msg = '';
    if($_GET['erro'] == 'senha') $msg = 'Senha incorreta!';
    if($_GET['erro'] == 'inexistente') $msg = 'Usuário não encontrado!';
    if($_GET['erro'] == 'desativado') $msg = 'Usuário desativado!';
    if($_GET['erro'] == 'sistema') $msg = 'Erro interno ou falha de conexão!';
    if($_GET['erro'] == 'vazio') $msg = 'Preencha todos os campos.';
    if($_GET['erro'] == 'captcha') $msg = 'Confirme o reCAPTCHA para continuar.';

    if($msg): ?>
      Swal.fire({ icon:'error', title:'Atenção', text:'<?php echo $msg; ?>', confirmButtonColor:'#d33' });
  <?php endif; endif; ?>
});
</script>

</body>
</html>
