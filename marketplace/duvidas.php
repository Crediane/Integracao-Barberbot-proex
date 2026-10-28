<?php
// Define a sessão apenas pelo endereço atual. O referenciador do navegador
// não é uma fonte confiável para escolher a barbearia ou o banco.
$__bb_reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (!function_exists('bb_sess_clean_slug')) {
  function bb_sess_clean_slug($v) {
    $v = strtolower(trim((string)$v));
    return preg_replace('/[^a-z0-9_-]/', '', $v);
  }
}

$__bb_reqSlug = '';
if (!empty($_GET['loja'])) {
  $__bb_reqSlug = bb_sess_clean_slug($_GET['loja']);
} elseif (!empty($_POST['loja'])) {
  $__bb_reqSlug = bb_sess_clean_slug($_POST['loja']);
} elseif (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath, $mSlug)) {
  $__bb_reqSlug = bb_sess_clean_slug($mSlug[1]);
}

$__bb_isSistema = (strpos($__bb_reqPath, '/sistema') === 0);
$__bb_hasMasterCookie = !empty($_COOKIE['BB_MASTER']);

if ($__bb_reqSlug !== '') {
  $__bb_desiredSession = 'BB_TENANT_' . strtoupper(substr(sha1($__bb_reqSlug), 0, 12));
} elseif ($__bb_isSistema || $__bb_hasMasterCookie) {
  // Sem slug, a página global pode reabrir somente a sessão Master já existente.
  // Nunca escolhe um cookie TENANT para acessar o conteúdo administrativo.
  $__bb_desiredSession = 'BB_MASTER';
} else {
  $__bb_desiredSession = 'BB_SITE';
}

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

/*
  /public_html/duvidas.php
  - Tutoriais: MASTER (/sistema/painel/)
  - Perguntas: POR LOJA (tenant)
  Mantém a lógica, só corrige:
  - admin aparecer (sessão não tinha email)
  - evita include duplicado do conexao.php (quando já existe $pdo)
*/

function bb_sanitize_slug($s) {
  $s = strtolower(trim((string)$s));
  $s = preg_replace('/[^a-z0-9\-]/', '', $s);
  return $s;
}

function bb_detect_slug() {
  if (!empty($_GET['loja'])) {
    $slug = bb_sanitize_slug($_GET['loja']);
    if ($slug !== '') return $slug;
  }

  if (!empty($_POST['loja'])) {
    $slug = bb_sanitize_slug($_POST['loja']);
    if ($slug !== '') return $slug;
  }

  $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
  if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $path, $m)) {
    return bb_sanitize_slug($m[1]);
  }

  return '';
}

$slug = bb_detect_slug();

// Converte o link legado /duvidas.php?loja={slug} para a rota pública dinâmica.
// Aplica somente em GET no domínio principal, sem interferir em POST, subdomínio ou domínio próprio.
$bb_duvidas_host = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
if (
  ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
  && $slug !== ''
  && $__bb_reqPath === '/duvidas.php'
  && isset($_GET['loja'])
  && in_array($bb_duvidas_host, ['jc.tec.br', 'www.jc.tec.br'], true)
) {
  header('Location: /loja/' . rawurlencode($slug) . '/duvidas.php', true, 302);
  exit();
}

/**
 * IMPORTANTE:
 * - Tutoriais SEMPRE no MASTER => endpoints relativos "paginas/duvidas/..." vão para /sistema/painel/ via <base>
 * - Perguntas por loja => endpoints de pergunta/resposta apontam para /loja/<slug>/sistema/painel/
 */
$BASE_PAINEL_MASTER = "/sistema/painel/";
$BASE_PAINEL_LOJA   = $slug ? ("/loja/" . rawurlencode($slug) . "/sistema/painel/") : "/sistema/painel/";
$URL_LOGIN          = $slug ? ("/loja/" . rawurlencode($slug) . "/sistema/") : "/sistema/";
$URL_COMECAR        = $slug ? ("/loja/" . rawurlencode($slug) . "/comecar.php") : "/comecar.php";

// Canonical atual
$PROTO = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$HOST  = $_SERVER['HTTP_HOST'] ?? 'jc.tec.br';
$CANONICAL_PATH = $slug ? ("/loja/" . rawurlencode($slug) . "/duvidas.php") : "/duvidas.php";
$CANONICAL = "{$PROTO}://{$HOST}{$CANONICAL_PATH}";

// ===================================
// Conexão do TENANT (PERGUNTAS por loja)
// ===================================
if (!isset($pdo) || !($pdo instanceof PDO)) {
  $__conexao_paths = [];

  if ($slug) {
    $__conexao_paths[] = __DIR__ . "/loja/{$slug}/sistema/conexao.php";
    $__conexao_paths[] = __DIR__ . "/loja/{$slug}/sistema/painel/conexao.php";
  }

  // fallback global
  $__conexao_paths[] = __DIR__ . "/sistema/conexao.php";
  $__conexao_paths[] = __DIR__ . "/sistema/painel/conexao.php";
  $__conexao_paths[] = __DIR__ . "/conexao.php";

  $__ok = false;
  foreach ($__conexao_paths as $__p) {
    if (file_exists($__p)) {
      require_once($__p);
      $__ok = true;
      break;
    }
  }

  if (!$__ok || !isset($pdo) || !($pdo instanceof PDO)) {
    http_response_code(500);
    echo 'Erro: conexao.php não encontrado.';
    exit();
  }
}

// Confirma o banco individual quando a página está no contexto da loja.
// Em domínio próprio ou subdomínio, o conexao.php pode resolver o slug apenas
// neste ponto; por isso o contexto é atualizado depois da conexão.
$bbDuvidasSessaoTenant = strpos((string)session_name(), 'BB_TENANT') === 0;
$bbDuvidasSlugSessao = bb_sanitize_slug($_SESSION['tenant_slug'] ?? '');
$bbDuvidasDbEsperado = trim((string)($_SESSION['tenant_db'] ?? ''));
$bbDuvidasDbAtual = '';

try {
  $bbDuvidasDbAtual = trim((string)$pdo->query('SELECT DATABASE()')->fetchColumn());
} catch (Throwable $e) {
  $bbDuvidasDbAtual = '';
}

if ($bbDuvidasSessaoTenant) {
  if (
    $bbDuvidasSlugSessao === ''
    || ($slug !== '' && $slug !== $bbDuvidasSlugSessao)
    || $bbDuvidasDbEsperado === ''
    || $bbDuvidasDbAtual === ''
    || strcasecmp($bbDuvidasDbAtual, $bbDuvidasDbEsperado) !== 0
  ) {
    http_response_code(403);
    exit('Falha de isolamento da barbearia.');
  }

  $slug = $bbDuvidasSlugSessao;
} elseif ($slug !== '') {
  http_response_code(403);
  exit('Barbearia não confirmada.');
}

$BASE_PAINEL_LOJA = $slug ? ('/loja/' . rawurlencode($slug) . '/sistema/painel/') : '/sistema/painel/';
$URL_LOGIN = $slug ? ('/loja/' . rawurlencode($slug) . '/sistema/') : '/sistema/';
$URL_COMECAR = $slug ? ('/loja/' . rawurlencode($slug) . '/comecar.php') : '/comecar.php';
$CANONICAL_PATH = $slug ? ('/loja/' . rawurlencode($slug) . '/duvidas.php') : '/duvidas.php';
$CANONICAL = $PROTO . '://' . $HOST . $CANONICAL_PATH;

if ($slug === '' && session_name() !== 'BB_MASTER' && empty($_SESSION['nivel'])) {
  header('Location: /marketplace.php');
  exit();
}

// ===================================
// Sessão do usuário (corrige admin)
// ===================================
$id_usuario    = $_SESSION['id'] ?? '';
$nivel_usuario = $_SESSION['nivel'] ?? '';
$email_usuario = $_SESSION['email'] ?? '';

// Se o sistema não grava email na sessão, busca no MASTER (jctecbr_master) só para identificar o admin
if (session_name() === 'BB_MASTER' && $slug === '' && $email_usuario === '' && !empty($id_usuario)) {
  $cfgFile = __DIR__ . '/config/marketplace.php';
  if (is_file($cfgFile)) {
    $cfg = require $cfgFile;

    $authMaster = (isset($cfg['auth_master']) && is_array($cfg['auth_master'])) ? $cfg['auth_master'] : [];
    $host = $authMaster['host'] ?? '';
    $user = $authMaster['user'] ?? null;
    $pass = $authMaster['pass'] ?? '';
    $db   = $authMaster['db'] ?? '';

    if ($host !== '' && $db !== '' && $user) {
      try {
        $pdoMasterUser = new PDO(
          "mysql:host={$host};dbname={$db};charset=utf8mb4",
          (string)$user,
          (string)$pass,
          [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
          ]
        );

        $st = $pdoMasterUser->prepare("SELECT email, nivel FROM usuarios WHERE id = :id LIMIT 1");
        $st->execute([':id' => (int)$id_usuario]);
        $u = $st->fetch();

        if ($u && !empty($u['email'])) {
          $_SESSION['email'] = (string)$u['email'];
          $email_usuario = (string)$u['email'];

          if (empty($_SESSION['nivel']) && !empty($u['nivel'])) {
            $_SESSION['nivel'] = (string)$u['nivel'];
            $nivel_usuario = (string)$u['nivel'];
          }
        }
      } catch (Throwable $e) {
        // não quebra
      }
    }
  }
}

$ADMIN_EMAIL   = 'jacy@jc.tec.br';
$isMasterAdmin = (session_name() === 'BB_MASTER' && $slug === '' && strtolower((string)$email_usuario) === strtolower($ADMIN_EMAIL));
?>
<!doctype html>
<html lang="pt-br">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">

  <title>Tutoriais e Dúvidas - BarberBot</title>
  <meta name="description" content="Tutoriais e dúvidas do BarberBot. Aprenda a usar o sistema e envie sua pergunta para suporte.">
  <meta name="robots" content="index,follow">
  <link rel="canonical" href="<?php echo htmlspecialchars($CANONICAL); ?>">
  <meta name="author" content="Jacy Cordeiro">

  <meta name="theme-color" content="#111827">
  <link rel="apple-touch-icon" href="/images/favicon.png">
  <link rel="shortcut icon" href="/images/favicon.png" type="image/x-icon">

  <!-- CRÍTICO: Tutoriais no MASTER -->
  <base href="<?php echo htmlspecialchars($BASE_PAINEL_MASTER); ?>">

  <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">

  <style>
    body{background:#f5f5f5; padding-top:20px;}
    .main-page{margin:0 auto;}
    .mb-4{margin-bottom:20px;}
    .mb-0{margin:0;}
    .panel{box-shadow:0 1px 2px rgba(0,0,0,.06);}
    .embed-responsive-16by9 { padding-bottom:56.25%; position:relative; display:block; height:0; overflow:hidden; }
    .embed-responsive-item { position:absolute; top:0; bottom:0; left:0; width:100%; height:100%; border:0; }
    .alert-info ol{margin:10px 0 0 18px;}
    .alert-info li{margin:4px 0;}
    .bb-top-actions .btn{margin-left:6px; margin-bottom:6px;}
    .bb-assist-call{margin-top:12px; padding:12px 14px; border:1px solid #bce8f1; border-radius:4px; background:#eef8fc; color:#245269;}
    .bb-assist-call strong{display:block; margin-bottom:4px;}
    @media (max-width:767px){
      .bb-top-actions{text-align:left; margin-top:12px;}
      .bb-top-actions .btn{margin-left:0; margin-right:6px;}
    }
  </style>
</head>
<body>

<div class="container main-page">
  <div class="row mb-4">
    <div class="col-xs-12 col-sm-4">
      <h4 class="mb-0"><i class="fa fa-question-circle"></i> Tutoriais e Dúvidas</h4>
      <?php if ($slug) { ?>
        <small class="text-muted">Unidade: <?php echo htmlspecialchars($slug); ?></small>
      <?php } ?>
    </div>

    <div class="col-xs-12 col-sm-8 text-right bb-top-actions">
      <a href="/sistema/painel/pdf/enviar-usuario.pdf"
         class="btn btn-success btn-sm"
         target="_blank"
         rel="noopener"
         style="margin-left:6px;">
        <i class="fa fa-download"></i> Baixar PDF
      </a>

      <a href="/sistema/painel/pdf/enviar-usuario.docx"
         class="btn btn-primary btn-sm"
         target="_blank"
         rel="noopener"
         style="margin-left:6px;">
        <i class="fa fa-file-word-o"></i> Baixar DOCX
      </a>

      <a href="<?php echo htmlspecialchars($URL_COMECAR, ENT_QUOTES, 'UTF-8'); ?>"
         class="btn btn-warning btn-sm"
         target="_blank"
         rel="noopener"
         style="margin-left:6px;">
        <i class="fa fa-magic"></i> Começar Configuração
      </a>

      <?php if ($isMasterAdmin) { ?>
        <button class="btn btn-success btn-sm"
                data-toggle="modal"
                data-target="#modalCadastro"
                onclick="novoTutorial()"
                style="margin-left:6px;">
          <i class="fa fa-plus"></i> Novo
        </button>

        <button class="btn btn-default btn-sm"
                data-toggle="modal"
                data-target="#modalDuvidasRecebidas"
                style="margin-left:6px;">
          <i class="fa fa-comments"></i> Dúvidas
        </button>
      <?php } else { ?>
        <button class="btn btn-info btn-sm"
                data-toggle="modal"
                data-target="#modalPergunta"
                style="margin-left:6px;">
          <i class="fa fa-comment"></i> Perguntar
        </button>
      <?php } ?>
    </div>
  </div>

  <div class="alert alert-info" role="alert" style="margin-bottom:15px;">
    <div style="display:flex; gap:10px;">
      <div style="min-width:18px;">
        <i class="fa fa-info-circle" style="font-size:18px;"></i>
      </div>
      <div>
        <strong>Configuração assistida BarberBot</strong><br>
        Preencha o arquivo com os dados da sua barbearia para que a equipe do BarberBot configure o sistema com as suas informações.

        <div class="bb-assist-call">
          <strong>Quer que a equipe do BarberBot deixe o sistema com a cara do seu estabelecimento?</strong>
          Clique em <b>Começar Configuração</b> para enviar os dados do seu negócio, profissionais, serviços, horários e imagens. Assim a equipe recebe tudo organizado para configurar o BarberBot corretamente.
        </div>

        <ol>
          <li><b>Baixe</b> o arquivo em <b>PDF</b> ou <b>DOCX</b> usando os botões acima.</li>
          <li><b>Preencha</b> com as informações de <b>Serviços</b>, <b>Profissionais</b> e <b>Horários</b>.</li>
          <li>Ou clique em <b>Começar Configuração</b> para preencher as informações online.</li>
          <li>Se preferir, siga o passo a passo nos <b>vídeos</b> abaixo.</li>
        </ol>
      </div>
    </div>
  </div>

  <div id="listar"></div>
</div>

<!-- MODAL: Perguntar (POR LOJA) -->
<div class="modal fade" id="modalPergunta" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form id="form-pergunta">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal">&times;</button>
          <h4 class="modal-title">Fazer uma Pergunta</h4>
        </div>

        <div class="modal-body">
          <div class="form-group">
            <label>Digite sua pergunta:</label>
            <textarea class="form-control" name="pergunta" id="pergunta" rows="4" required></textarea>
          </div>
          <small><div id="mensagem-pergunta" class="text-center"></div></small>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Enviar</button>
          <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<?php if ($isMasterAdmin) { ?>

<!-- MODAL: Cadastro Tutorial (MASTER) -->
<div class="modal fade" id="modalCadastro" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <form id="form-tutorial">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal">&times;</button>
          <h4 class="modal-title" id="tituloModal">Novo Tutorial</h4>
        </div>

        <div class="modal-body">
          <input type="hidden" name="id" id="id">

          <div class="form-group">
            <label>Título</label>
            <input type="text" class="form-control" name="titulo" id="titulo" required>
          </div>

          <div class="form-group">
            <label>Descrição</label>
            <textarea class="form-control" name="descricao" id="descricao" rows="5" required></textarea>
          </div>

          <div class="form-group">
            <label>Link do Vídeo</label>
            <input type="text" class="form-control" name="link_video" id="link_video">
          </div>

          <small><div id="mensagem-tutorial" class="text-center"></div></small>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Salvar</button>
          <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL: Responder (POR LOJA) -->
<div class="modal fade" id="modalResposta" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form id="form-resposta">
        <div class="modal-header">
          <button type="button" class="close" data-dismiss="modal">&times;</button>
          <h4 class="modal-title">Responder Dúvida</h4>
        </div>

        <div class="modal-body">
          <input type="hidden" name="id_pergunta" id="id_pergunta">

          <div class="form-group">
            <label>Pergunta:</label>
            <p id="texto_pergunta" class="text-muted" style="font-style: italic;"></p>
          </div>

          <div class="form-group">
            <label>Resposta:</label>
            <textarea name="resposta" id="resposta" class="form-control" rows="4" required></textarea>
          </div>

          <div id="mensagem-resposta" class="text-center small text-danger"></div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Salvar</button>
          <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- MODAL: Dúvidas Recebidas (POR LOJA) -->
<div class="modal fade" id="modalDuvidasRecebidas" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">

      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title"><i class="fa fa-comments"></i> Dúvidas Recebidas</h4>
      </div>

      <div class="modal-body">
        <?php
        try {
          $stmt = $pdo->query("SELECT * FROM perguntas ORDER BY id DESC");
          $perguntas = $stmt->fetchAll(PDO::FETCH_ASSOC);

          if (count($perguntas) == 0) {
            echo '<p class="text-muted text-center">Nenhuma dúvida enviada ainda.</p>';
          }

          foreach ($perguntas as $linha) {
            $pid = $linha['id'];
            $pergunta = $linha['pergunta'];
            $resposta = $linha['resposta'];
        ?>
            <div class="panel panel-default">
              <div class="panel-body">
                <p><strong>Pergunta:</strong> <?php echo $pergunta; ?></p>

                <?php if (!empty($resposta)) { ?>
                  <p><strong>Resposta:</strong> <?php echo $resposta; ?></p>
                <?php } else { ?>
                  <button class="btn btn-sm btn-info"
                          onclick="abrirResposta('<?php echo $pid; ?>', '<?php echo addslashes($pergunta); ?>')">
                    <i class="fa fa-reply"></i> Responder
                  </button>
                <?php } ?>
              </div>
            </div>
        <?php
          }
        } catch (Exception $e) {
          echo '<p class="text-danger">Erro ao carregar dúvidas.</p>';
        }
        ?>
      </div>

    </div>
  </div>
</div>

<?php } ?>

<!-- MODAL: Vídeo -->
<div class="modal fade" id="modalVideo" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">

      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal">&times;</button>
        <h4 class="modal-title">Visualizar Vídeo</h4>
      </div>

      <div class="modal-body">
        <div class="embed-responsive-16by9">
          <iframe class="embed-responsive-item" id="videoFrame" src="" allowfullscreen></iframe>
        </div>
      </div>

    </div>
  </div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/3.7.1/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.4.1/js/bootstrap.min.js"></script>

<script type="text/javascript">
var URL_LOGIN = "<?php echo $URL_LOGIN; ?>";
var BASE_PERG = "<?php echo $BASE_PAINEL_LOJA; ?>";   // perguntas por loja
var SLUG_LOJA = <?php echo json_encode((string)$slug, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
var IS_LOGADO = <?php echo $id_usuario ? 'true' : 'false'; ?>;

$(document).ready(function () {
  listar(0);

  $('#modalPergunta').on('show.bs.modal', function () {
    if (!IS_LOGADO) {
      window.location.href = URL_LOGIN;
      return false;
    }
  });

  // PERGUNTA (POR LOJA)
  $('#form-pergunta').submit(function (event) {
    event.preventDefault();
    var formData = new FormData(this);

    $.ajax({
      url: BASE_PERG + 'paginas/duvidas/salvar_pergunta.php',
      type: 'POST',
      data: formData,
      cache: false,
      contentType: false,
      processData: false,
      success: function (resp) {
        $('#mensagem-pergunta').removeClass().text(resp);
        var r = String(resp).toLowerCase();

        if (r.includes('precisa estar logado') || r.includes('campos obrigatórios')) {
          setTimeout(function(){ window.location.href = URL_LOGIN; }, 800);
          return;
        }

        if (r.includes('sucesso')) {
          $('#form-pergunta')[0].reset();
          listar(0);
          setTimeout(function () {
            $('#modalPergunta').modal('hide');
            $('#mensagem-pergunta').text('');
          }, 1200);
        }
      },
      error: function () {
        $('#mensagem-pergunta').addClass('text-danger').text('Erro ao enviar.');
      }
    });
  });

  // TUTORIAIS (MASTER) -> endpoint relativo por causa do <base>
  $('#form-tutorial').submit(function (event) {
    event.preventDefault();
    var formData = new FormData(this);

    $.ajax({
      url: 'paginas/duvidas/salvar.php',
      type: 'POST',
      data: formData,
      cache: false,
      contentType: false,
      processData: false,
      success: function (resp) {
        $('#mensagem-tutorial').removeClass().text(resp);
        if (String(resp).toLowerCase().includes('sucesso')) {
          listar(0);
          setTimeout(function () {
            $('#modalCadastro').modal('hide');
            $('#mensagem-tutorial').text('');
          }, 1200);
        }
      },
      error: function () {
        $('#mensagem-tutorial').addClass('text-danger').text('Erro ao salvar.');
      }
    });
  });

  // RESPONDER (POR LOJA)
  $('#form-resposta').submit(function (event) {
    event.preventDefault();

    $.post(BASE_PERG + 'paginas/duvidas/responder.php', $(this).serialize(), function (retorno) {
      $('#mensagem-resposta').text(retorno);

      if (String(retorno).toLowerCase().includes('sucesso')) {
        $('#modalResposta').modal('hide');
        $('#mensagem-resposta').text('');
        listar(0);
      }
    });
  });

  $(document).on('hidden.bs.modal', '#modalVideo', function () {
    $('#videoFrame').attr('src', '');
  });
});

// TUTORIAIS (MASTER)
function listar(pagina) {
  pagina = pagina || 0;
  $.post('paginas/duvidas/listar.php', { pagina: pagina, loja: SLUG_LOJA }, function (result) {
    $('#listar').html(result);
  });
}

function abrirVideo(link) {
  try {
    if (typeof event !== 'undefined' && event) {
      if (event.preventDefault) event.preventDefault();
      if (event.stopPropagation) event.stopPropagation();
      event.returnValue = false;
    }

    var url = String(link || '').trim();
    if (!url) return false;

    if (!/^https?:\/\//i.test(url)) {
      url = 'https://' + url.replace(/^\/+/, '');
    }

    if (url.indexOf('drive.google.com') !== -1) {
      var idMatch = url.match(/[-\w]{25,}/);
      if (idMatch && idMatch[0]) {
        url = 'https://drive.google.com/file/d/' + idMatch[0] + '/preview';
      }
    }

    if (url.indexOf('youtube.com') !== -1 || url.indexOf('youtu.be') !== -1) {
      var vid = '';

      if (url.indexOf('youtu.be/') !== -1) {
        vid = url.split('youtu.be/')[1].split(/[?&]/)[0];
      } else if (url.indexOf('v=') !== -1) {
        vid = url.split('v=')[1].split('&')[0];
      } else if (url.indexOf('/embed/') !== -1) {
        vid = url.split('/embed/')[1].split(/[?&]/)[0];
      }

      if (vid) {
        url = 'https://www.youtube.com/embed/' + vid;
      }
    }

    if (typeof jQuery === 'undefined' || !jQuery.fn || typeof jQuery.fn.modal !== 'function') {
      window.open(url, '_blank');
      return false;
    }

    $('#videoFrame').attr('src', url);
    $('#modalVideo').modal('show');

    return false;
  } catch (e) {
    try { window.open(link, '_blank'); } catch (e2) {}
    return false;
  }
}

function novoTutorial() {
  $('#id').val('');
  $('#titulo').val('');
  $('#descricao').val('');
  $('#link_video').val('');
  $('#mensagem-tutorial').text('');
  $('#tituloModal').text('Novo Tutorial');
}

function editar(id, titulo, descricao, link) {
  if (typeof event !== 'undefined' && event) {
    if (event.preventDefault) event.preventDefault();
    event.returnValue = false;
  }

  $('#id').val(id || '');
  $('#titulo').val(titulo || '');
  $('#descricao').val(descricao || '');
  $('#link_video').val(link || '');
  $('#mensagem-tutorial').text('');
  $('#tituloModal').text('Editar Tutorial');
  $('#modalCadastro').modal('show');

  return false;
}

function excluir(id) {
  if (typeof event !== 'undefined' && event) {
    if (event.preventDefault) event.preventDefault();
    event.returnValue = false;
  }

  if (confirm('Deseja realmente excluir este tutorial?')) {
    $.post('paginas/duvidas/excluir.php', { id: id }, function (retorno) {
      if (String(retorno).trim() === 'Excluído com Sucesso') {
        listar(0);
      } else {
        alert(retorno);
      }
    });
  }

  return false;
}

function abrirResposta(id, pergunta) {
  if (typeof event !== 'undefined' && event) {
    if (event.preventDefault) event.preventDefault();
    event.returnValue = false;
  }

  $('#id_pergunta').val(id);
  $('#texto_pergunta').text(pergunta || '');
  $('#resposta').val('');
  $('#mensagem-resposta').text('');
  $('#modalResposta').modal('show');

  return false;
}
</script>

</body>
</html>
