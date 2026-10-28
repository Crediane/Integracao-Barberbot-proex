<?php
// ===== Sessão isolada por contexto (MASTER x TENANT) =====
// Evita que o cookie padrão (PHPSESSID) misture MASTER x TENANT.
// ✅ Também isola TENANT por SLUG: BB_TENANT_<hash> (evita misturar lojas no mesmo navegador)

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
$__bb_refPath = '';
if (!empty($_SERVER['HTTP_REFERER'])) {
  $__bb_refPath = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: '';
}
$__bb_refRoutePath = $__bb_refPath;
if ($__bb_webBase !== '' && stripos($__bb_refRoutePath, $__bb_webBase) === 0) {
  $__bb_refRoutePath = substr($__bb_refRoutePath, strlen($__bb_webBase));
  if ($__bb_refRoutePath === '') $__bb_refRoutePath = '/';
}

if (!function_exists('bb_sess_clean_slug')) {
  function bb_sess_clean_slug($v) {
    $v = strtolower(trim((string)$v));
    return preg_replace('/[^a-z0-9_-]/', '', $v);
  }
}

// Detecta slug cedo (GET/POST/URI/REFERER)
$__bb_reqSlug = '';
if (!empty($_GET['loja'])) {
  $__bb_reqSlug = bb_sess_clean_slug($_GET['loja']);
} elseif (!empty($_POST['loja'])) {
  $__bb_reqSlug = bb_sess_clean_slug($_POST['loja']);
} else {
  if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_routePath, $mSlug)) {
    $__bb_reqSlug = bb_sess_clean_slug($mSlug[1]);
  } elseif (!empty($__bb_refRoutePath) && preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_refRoutePath, $mSlug2)) {
    $__bb_reqSlug = bb_sess_clean_slug($mSlug2[1]);
  }
}

// Sessão TENANT por SLUG
$__bb_tenantSessionName = 'BB_TENANT';
if ($__bb_reqSlug !== '') {
  $__bb_tenantSessionName = 'BB_TENANT_' . strtoupper(substr(sha1($__bb_reqSlug), 0, 12));
}

// Cookies TENANT (BB_TENANT / BB_TENANT_*)
$__bb_tenantCookies = [];
foreach ($_COOKIE as $__bb_ck => $__bb_cv) {
  if ($__bb_ck === 'BB_TENANT' || strpos($__bb_ck, 'BB_TENANT_') === 0) {
    $__bb_tenantCookies[] = (string)$__bb_ck;
  }
}
$__bb_hasTenantCookie = (count($__bb_tenantCookies) > 0);
$__bb_oneTenantCookie = (count($__bb_tenantCookies) === 1) ? (string)$__bb_tenantCookies[0] : '';
$__bb_hasMasterCookie = !empty($_COOKIE['BB_MASTER']);

$__bb_ctx = '';
if (!empty($_GET['ctx'])) {
  $__bb_ctx = strtolower(trim((string)$_GET['ctx']));
} elseif (!empty($_COOKIE['BB_CTX'])) {
  $__bb_ctx = strtolower(trim((string)$_COOKIE['BB_CTX']));
}
if (!in_array($__bb_ctx, ['tenant','master'], true)) $__bb_ctx = '';

$__bb_lojaByPath = (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_routePath)
                || (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_refRoutePath);

$__bb_hasLoja = $__bb_lojaByPath || !empty($_GET['loja']) || !empty($_POST['loja']);
$__bb_isSistema = (strpos($__bb_routePath, '/sistema') === 0) || (strpos($__bb_refRoutePath, '/sistema') === 0);

// /sistema no host principal, sem slug ou parâmetro de loja, é sempre MASTER.
// Cookies antigos de tenant não podem alterar a rota administrativa explícita.
$__bb_forceMaster = (
  $__bb_isSistema
  && !$__bb_lojaByPath
  && empty($_GET['loja'])
  && empty($_POST['loja'])
);

// Sessão desejada (tenant por slug; se não tiver slug, tenta resolver pelo único cookie BB_TENANT_*)
$__bb_desiredSession = $__bb_forceMaster ? 'BB_MASTER' : ($__bb_hasLoja ? ($__bb_reqSlug !== '' ? $__bb_tenantSessionName : ($__bb_oneTenantCookie !== '' ? $__bb_oneTenantCookie : 'BB_TENANT')) : 'BB_SITE');

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

// Atualiza contexto ativo (BB_CTX)
if (!headers_sent()) {
  $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
  $ctxVal = (strpos(session_name(), 'BB_TENANT') === 0) ? 'tenant' : ((session_name() === 'BB_MASTER') ? 'master' : '');
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
/*
  ✅ CONTEXTO (MASTER x TENANT) ANTES DE QUALQUER CONEXÃO
  - TENANT: quando a URL é /loja/<slug>/... OU quando veio ?loja=
  - MASTER: quando é /sistema/... (sem /loja/), NÃO herda tenant antigo da sessão
*/

$__uriPath = $__bb_routePath;

/* Detecta slug na URL /loja/<slug>/... */
$__tenantFromUri = '';
if (preg_match('~^/loja/([a-z0-9_-]+)/~i', $__uriPath, $__m)) {
	$__tenantFromUri = preg_replace('/[^a-z0-9_-]/i', '', (string)$__m[1]);
}

$__isLojaCtx = ($__tenantFromUri !== '') || (!empty($_GET['loja']) && is_string($_GET['loja']) && $_GET['loja'] !== '');

/*
  🔒 Regra de contexto:
  - Se NÃO estiver em /loja/..., sempre limpa contexto tenant (mesmo que alguém passe ?loja=)
  - Se estiver em /loja/..., força $_GET['loja'] pelo slug da URL (prioridade)
*/
if ($__tenantFromUri !== '') {
	$_GET['loja'] = $__tenantFromUri;
} else {
	// ✅ Só limpa contexto tenant quando o contexto ativo for MASTER.
	// Em sessão BB_TENANT, manter o contexto evita gravar config no admin por engano.
	if (session_name() === 'BB_MASTER') {
		unset(
			$_GET['loja'],
			$_SESSION['loja'],
			$_SESSION['tenant_slug'],
			$_SESSION['tenant_id'],
			$_SESSION['tenant_db'],
			$_SESSION['tenant_host'],
			$_SESSION['tenant_user'],
			$_SESSION['tenant_pass']
		);
	}
}
/* Se estiver em loja e não veio por URL, tenta pela sessão (compatibilidade) */
if ($__tenantFromUri === '' && !empty($_GET['loja'])) {
	$_GET['loja'] = preg_replace('/[^a-z0-9_-]/i', '', (string)$_GET['loja']);
}
if ($__tenantFromUri === '' && empty($_GET['loja']) && !empty($_SESSION['tenant_slug'])) {
	$_GET['loja'] = preg_replace('/[^a-z0-9_-]/i', '', (string)$_SESSION['tenant_slug']);
}

require_once __DIR__ . '/verificar.php';
require_once __DIR__ . '/../conexao.php';

$pag_inicial = 'home';

/* ✅ defaults (evita warnings) */
$nome_usuario = '';
$email_usuario = '';
$cpf_usuario = '';
$senha_usuario = '';
$nivel_usuario = '';
$telefone_usuario = '';
$endereco_usuario = '';
$foto_usuario = 'sem-foto.png';
$atendimento = 'Não';
$intervalo_horarios = 0;

/* ✅ id seguro */
$id_usuario = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
if ($id_usuario <= 0) {
	session_destroy();
	echo "<script>window.location='../index.php'</script>";
	exit();
}

/* ✅ se não conectou no banco, para aqui */
if (!($pdo instanceof PDO)) {
	session_destroy();
	echo "<script>window.location='../index.php'</script>";
	exit();
}

/* ✅ busca usuário (prepared statement) */
$stmt = $pdo->prepare("SELECT * FROM usuarios WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id_usuario]);
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);

if ($total_reg > 0) {
	$nome_usuario = $res[0]['nome'] ?? '';
	$email_usuario = $res[0]['email'] ?? '';
	$cpf_usuario = $res[0]['cpf'] ?? '';
	$senha_usuario = $res[0]['senha'] ?? '';
	$nivel_usuario = $res[0]['nivel'] ?? '';
	$telefone_usuario = $res[0]['telefone'] ?? '';
	$endereco_usuario = $res[0]['endereco'] ?? '';
	$foto_usuario = $res[0]['foto'] ?? 'sem-foto.png';
	$atendimento = $res[0]['atendimento'] ?? 'Não';
	$intervalo_horarios = (int)($res[0]['intervalo'] ?? 0);

	// ✅ Normalização para evitar espaços/valores inconsistentes vindos do banco
	$nivel_usuario = trim((string)$nivel_usuario);
	$email_usuario = trim((string)$email_usuario);

	if ($foto_usuario === '') $foto_usuario = 'sem-foto.png';
} else {
	session_destroy();
	echo "<script>window.location='../index.php'</script>";
	exit();
}

/* ✅ SOMENTE AQUI, depois de carregar email e nível */
$mostrar_marketplace = (
	session_name() === 'BB_MASTER'
	&& !$__isLojaCtx
	&& $nivel_usuario === 'Administrador'
	&& strtolower(trim($email_usuario)) === 'jacy@jc.tec.br'
);

// A entrada do painel abre sempre a Home.
// O Marketplace continua disponível no menu administrativo próprio.
$pag_inicial = 'home';

if (@$_SESSION['nivel'] != 'Administrador') {
	require_once __DIR__ . '/verificar-permissoes.php';
}

// ===== Paginação (compatível com rotas reescritas /painel/<pag>) =====
$pag = '';
if (isset($_GET['pag'])) {
	$pag = trim((string)$_GET['pag']);
} elseif (isset($_GET['pagina'])) {
	// Compatibilidade com links administrativos já existentes.
	$pag = trim((string)$_GET['pagina']);
}

// ✅ Fallback: quando o rewrite não injeta ?pag= (ex.: /loja/<slug>/painel/produtos)
if ($pag === '') {
	$__bb_uri_path_pag = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');

	// tenta extrair o primeiro segmento após /painel/
	if (preg_match('~/(?:sistema/)?painel/([^/?#]+)~i', $__bb_uri_path_pag, $__mPag)) {
		$pag = trim((string)$__mPag[1]);
	}
}

// default
if ($pag === '') {
	$pag = $pag_inicial;
}

// Sanitização (mantém padrão do sistema)
$pag = preg_replace('/[^a-z0-9_-]/i', '', $pag);

// Estado visual do menu Master do Marketplace.
$bb_marketplace_menu_active = ($mostrar_marketplace && in_array($pag, ['marketplace', 'marketplace_diagnostico'], true)) ? 'active' : '';
$bb_marketplace_inicio_active = ($mostrar_marketplace && $pag === 'marketplace') ? 'active' : '';
$bb_marketplace_diagnostico_active = ($mostrar_marketplace && $pag === 'marketplace_diagnostico') ? 'active' : '';

$data_atual = date('Y-m-d');
$mes_atual = date('m');
$ano_atual = date('Y');
$data_mes = $ano_atual . "-" . $mes_atual . "-01";
$data_ano = $ano_atual . "-01-01";

$partesInicial = explode('-', $data_atual);
$dataDiaInicial = $partesInicial[2] ?? '';
$dataMesInicial = $partesInicial[1] ?? '';

/*
  ✅ BASE DINÂMICA (ADMIN e LOJA/TENANT)
  - Corrige links/paths em:
    /sistema/painel/...
    /loja/<slug>/sistema/painel/...
    /loja/<slug>/painel/... (quando houver rewrite)
*/
$bb_proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
	$bb_proto = ($_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : $bb_proto;
}
$bb_host = $_SERVER['HTTP_HOST'] ?? '';
$bb_base = ($bb_host != '') ? ($bb_proto . '://' . $bb_host) : '';

$bb_uri_path = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
$bb_script   = (string)($_SERVER['SCRIPT_NAME'] ?? '');

$bb_path_painel = '';

// 1) se o script real já contém /sistema/painel
if ($bb_script != '' && stripos($bb_script, '/sistema/painel') !== false) {
	$bb_path_painel = rtrim(dirname($bb_script), '/');
}
// 2) se a URL contém /sistema/painel
else if ($bb_uri_path != '' && stripos($bb_uri_path, '/sistema/painel') !== false) {
	$bb_path_painel = rtrim(dirname($bb_uri_path), '/');
}
// 3) se a URL está como /.../painel (sem /sistema) -> injeta /sistema
else if ($bb_uri_path != '' && preg_match('~^(.*?)/painel(?:/.*)?$~i', $bb_uri_path, $m)) {
	$prefix = rtrim($m[1], '/'); // ex: /loja/slug
	$bb_path_painel = $prefix . '/sistema/painel';
}
// 4) fallback admin padrão
else {
	$bb_path_painel = '/sistema/painel';
}

$bb_path_sistema = rtrim(dirname($bb_path_painel), '/'); // ex: /sistema OU /loja/slug/sistema

$url_painel  = $bb_base . $bb_path_painel;
$url_sistema = $bb_base . $bb_path_sistema;

// ✅ BASE PÚBLICA DO PAINEL (para navegação: /loja/<slug>/painel)
$bb_public_painel_path = $bb_path_painel;

if ($bb_uri_path !== '') {

	// se a URL pública for /loja/<slug>/painel/...
	if (preg_match('~^(/loja/[a-z0-9_-]+/painel)(?:/|$)~i', $bb_uri_path, $m)) {
		$bb_public_painel_path = rtrim($m[1], '/');
	}

	// se a URL pública for /sistema/painel/...
	if (preg_match('~^(/sistema/painel)(?:/|$)~i', $bb_uri_path, $m2)) {
		$bb_public_painel_path = rtrim($m2[1], '/');
	}
}

$url_painel_public = $bb_base . $bb_public_painel_path;

// ✅ BASE REAL DOS ASSETS (onde o código realmente está)
$bb_assets_painel_path = $bb_path_painel;                 // ex: /sistema/painel
$url_painel_assets     = $bb_base . $bb_assets_painel_path;

// ✅ BASE REAL DO /sistema (para /dist etc)
$bb_assets_sistema_path = rtrim(dirname($bb_assets_painel_path), '/'); // ex: /sistema
$url_sistema_assets     = $bb_base . $bb_assets_sistema_path;

// ✅ SLUG TENANT PARA AJAX EM ROTAS REAIS DO /sistema/painel
$bb_tenant_slug_ajax = '';
if (!empty($__tenantFromUri)) {
	$bb_tenant_slug_ajax = preg_replace('/[^a-z0-9_-]/i', '', (string)$__tenantFromUri);
} elseif (!empty($_GET['loja'])) {
	$bb_tenant_slug_ajax = preg_replace('/[^a-z0-9_-]/i', '', (string)$_GET['loja']);
} elseif (!empty($_SESSION['tenant_slug'])) {
	$bb_tenant_slug_ajax = preg_replace('/[^a-z0-9_-]/i', '', (string)$_SESSION['tenant_slug']);
}
$bb_loja_query_ajax = ($bb_tenant_slug_ajax !== '') ? ('loja=' . rawurlencode($bb_tenant_slug_ajax)) : '';

/*
  ✅ LOGO NO PAINEL (busca do CONFIG)
  - TENANT: /sistema/tenants/<slug>/img/<logo>
  - MASTER: /sistema/img/<logo>
  - fallback: /sistema/painel/img/barbertot.png (como já era)
*/
$bb_logo_nav_src = $url_painel_assets . "/img/barbertot.png";

try {
	$bbRoot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
	$bbTenant = '';

	if ($__tenantFromUri !== '') $bbTenant = $__tenantFromUri;
	else if (!empty($_SESSION['tenant_slug'])) $bbTenant = preg_replace('/[^a-z0-9_-]/i', '', (string)$_SESSION['tenant_slug']);
	else if (!empty($_GET['loja'])) $bbTenant = preg_replace('/[^a-z0-9_-]/i', '', (string)$_GET['loja']);

	$bbLogoFile = isset($logo) ? trim((string)$logo) : '';
	if ($bbLogoFile !== '' && $bbRoot !== '') {

		// tenta tenant
		if ($bbTenant !== '') {
			$diskTenant = $bbRoot . "/sistema/tenants/{$bbTenant}/img/" . $bbLogoFile;
			if (is_file($diskTenant)) {
				$bb_logo_nav_src = "/sistema/tenants/{$bbTenant}/img/" . rawurlencode($bbLogoFile);
			}
		}

		// tenta global antigo
		if ($bb_logo_nav_src === ($url_painel_assets . "/img/barbertot.png")) {
			$diskGlobal = $bbRoot . "/sistema/img/" . $bbLogoFile;
			if (is_file($diskGlobal)) {
				$bb_logo_nav_src = "/sistema/img/" . rawurlencode($bbLogoFile);
			}
		}
	}
} catch (Throwable $e) {
	// mantém fallback
}

?>

<!DOCTYPE HTML>
<html>
<head>
  <meta charset="utf-8">
  <title><?php echo $nome_sistema ?></title>

  <!-- ✅ FIX: navegação sempre em /loja/<slug>/painel -->
  <base href="<?php echo $url_painel_public; ?>/">

  <link rel="icon" type="image/png" href="<?php echo $url_sistema; ?>/img/favicon.png">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta name="keywords" content="" />
  <script type="application/x-javascript">
    addEventListener("load", function() { setTimeout(hideURLbar, 0); }, false);
    function hideURLbar(){ window.scrollTo(0,1); }
  </script>

  <!-- ✅ FIX: assets SEMPRE pelo caminho real -->
  <link href="<?php echo $url_painel_assets; ?>/css/bootstrap.css" rel="stylesheet" type="text/css" />
  <link href="<?php echo $url_painel_assets; ?>/css/style.css" rel="stylesheet" type="text/css" />
  <link href="<?php echo $url_painel_assets; ?>/css/font-awesome.css" rel="stylesheet">
  <link href="<?php echo $url_painel_assets; ?>/css/SidebarNav.min.css" media="all" rel="stylesheet" type="text/css"/>
  <link rel="stylesheet" href="<?php echo $url_painel_assets; ?>/css/monthly.css">

  <script src="<?php echo $url_painel_assets; ?>/js/jquery-1.11.1.min.js"></script>
  <script src="<?php echo $url_painel_assets; ?>/js/modernizr.custom.js"></script>

  <link href="//fonts.googleapis.com/css?family=PT+Sans:400,400i,700,700i&amp;subset=cyrillic,cyrillic-ext,latin-ext" rel="stylesheet">

  <script src="<?php echo $url_painel_assets; ?>/js/Chart.js"></script>
  <script src="<?php echo $url_painel_assets; ?>/js/metisMenu.min.js"></script>
  <script src="<?php echo $url_painel_assets; ?>/js/custom.js"></script>
  <link href="<?php echo $url_painel_assets; ?>/css/custom.css" rel="stylesheet">

  <style>
    #chartdiv { width: 100%; height: 295px; }
  </style>

  <script src="<?php echo $url_painel_assets; ?>/js/pie-chart.js" type="text/javascript"></script>
  <script type="text/javascript">
    $(document).ready(function () {
      $('#demo-pie-1').pieChart({
        barColor: '#2dde98',
        trackColor: '#eee',
        lineCap: 'round',
        lineWidth: 8,
        onStep: function (from, to, percent) {
          $(this.element).find('.pie-value').text(Math.round(percent) + '%');
        }
      });

      $('#demo-pie-2').pieChart({
        barColor: '#8e43e7',
        trackColor: '#eee',
        lineCap: 'butt',
        lineWidth: 8,
        onStep: function (from, to, percent) {
          $(this.element).find('.pie-value').text(Math.round(percent) + '%');
        }
      });

      $('#demo-pie-3').pieChart({
        barColor: '#e32424',
        trackColor: '#eee',
        lineCap: 'square',
        lineWidth: 8,
        onStep: function (from, to, percent) {
          $(this.element).find('.pie-value').text(Math.round(percent) + '%');
        }
      });
    });
  </script>

  <link rel="stylesheet" type="text/css" href="<?php echo $url_painel_assets; ?>/DataTables/datatables.min.css"/>
  <script type="text/javascript" src="<?php echo $url_painel_assets; ?>/DataTables/datatables.min.js"></script>

  <!-- Trumbowyg -->
  <link rel="stylesheet" href="<?php echo $url_sistema_assets; ?>/dist/ui/trumbowyg.min.css">
  <link rel="stylesheet" href="<?php echo $url_sistema_assets; ?>/dist/plugins/emoji/ui/trumbowyg.emoji.min.css">
  <link rel="stylesheet" href="<?php echo $url_sistema_assets; ?>/dist/plugins/colors/ui/trumbowyg.colors.min.css">
</head>

<body class="cbp-spmenu-push">
  <div class="main-content">
    <div class="cbp-spmenu cbp-spmenu-vertical cbp-spmenu-left" id="cbp-spmenu-s1">
      <aside class="sidebar-left" style="overflow: scroll; height:100%; scrollbar-width: thin;">
        <nav class="navbar navbar-inverse" >
          <div class="navbar-header">
            <button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target=".collapse" aria-expanded="false" id="showLeftPush2">
              <span class="sr-only">Toggle navigation</span>
              <span class="icon-bar"></span>
              <span class="icon-bar"></span>
              <span class="icon-bar"></span>
            </button>

            <!-- ✅ FIX: imagem real -->
 <a class="navbar-brand" href="./">
  <img src="<?php echo $bb_logo_nav_src; ?>" width="100px" style="margin-right: 0px">
</a>

          </div>

          <div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
            <ul class="sidebar-menu">
              <li class="header">MENU DE NAVEGAÇÃO</li>

              <li class="treeview <?php echo @$home ?>">
                <a href="./">
                  <i class="fa fa-dashboard"></i> <span>Home</span>
                </a>
              </li>

              <?php if($mostrar_marketplace){ ?>
                <li class="treeview <?php echo $bb_marketplace_menu_active; ?>">
                  <a href="#">
                    <i class="fa fa-building"></i>
                    <span>Marketplace</span>
                    <i class="fa fa-angle-left pull-right"></i>
                  </a>
                  <ul class="treeview-menu">
                    <li class="<?php echo $bb_marketplace_inicio_active; ?>">
                      <a href="<?php echo $url_painel_public; ?>/marketplace">
                        <i class="fa fa-angle-right"></i> Gestão das Barbearias
                      </a>
                    </li>
                    <li class="<?php echo $bb_marketplace_diagnostico_active; ?>">
                      <a href="<?php echo $url_painel_public; ?>/marketplace_diagnostico">
                        <i class="fa fa-angle-right"></i> Diagnóstico de Isolamento
                      </a>
                    </li>
                  </ul>
                </li>
              <?php } ?>

              <li class="treeview">
                <a href="#">
                  <i class="fa fa-dashboard"></i>
                  <span>Configurações</span>
                  <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                  <li class="treeview <?= @$dispositivos ?>"><a href="dispositivos"><i class="fa fa-mobile"></i>Conectar Dispositivo</a></li>
                  <li class="<?php echo @$duvidas ?>"><a href="duvidas"><i class="fa fa-question"></i>Dúvidas Frequentes</a></li>
                  <li class="<?php echo @$doc ?>"><a href="https://doc.barberbot.com.br/" target="_blank"><i class="fa fa-question"></i>Documentos</a></li>
                </ul>
              </li>

              <li class="treeview <?php echo @$comanda ?>">
                <a href="comanda">
                  <i class="fa fa-file-o"></i> <span>Nova Comanda</span>
                </a>
              </li>

              <li class="treeview <?php echo $menu_pessoas ?>">
                <a href="#">
                  <i class="fa fa-users"></i>
                  <span>Pessoas</span>
                  <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                  <li class="<?php echo @$usuarios ?>"><a href="usuarios"><i class="fa fa-angle-right"></i>Usuários</a></li>
                  <li class="<?php echo @$funcionarios ?>"><a href="funcionarios"><i class="fa fa-angle-right"></i>Funcionários</a></li>
                  <li class="<?php echo @$clientes ?>"><a href="clientes"><i class="fa fa-angle-right"></i>Clientes</a></li>
                  <li class="<?php echo @$clientes_retorno ?>"><a href="clientes_retorno"><i class="fa fa-angle-right"></i>Clientes Retornos</a></li>
                  <li class="<?php echo @$fornecedores ?>"><a href="fornecedores"><i class="fa fa-angle-right"></i>Fornecedores</a></li>
                </ul>
              </li>

              <li class="treeview <?php echo @$menu_cadastros ?>" >
                <a href="#">
                  <i class="fa fa-plus"></i>
                  <span>Cadastros</span>
                  <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                  <li class="<?php echo @$servicos ?>"><a href="servicos"><i class="fa fa-angle-right"></i>Serviços</a></li>
                  <li class="<?php echo @$cargos ?>"><a href="cargos"><i class="fa fa-angle-right"></i>Cargos</a></li>
                  <li class="<?php echo @$cat_servicos ?>"><a href="cat_servicos"><i class="fa fa-angle-right"></i>Categoria Serviços</a></li>
                  <li class="<?php echo @$grupos ?>"><a href="grupos"><i class="fa fa-angle-right"></i>Grupo Acessos</a></li>
                  <li class="<?php echo @$acessos ?>"><a href="acessos"><i class="fa fa-angle-right"></i>Acessos</a></li>
                  <li class="<?php echo @$pgto ?>"><a href="pgto"><i class="fa fa-angle-right"></i>Formas de Pagamento</a></li>
                  <li class="<?php echo @$dias_bloqueio ?>"><a href="dias_bloqueio"><i class="fa fa-angle-right"></i>Bloqueio de Dias</a></li>
                  <li class="<?php echo @$assinaturas ?>"><a href="assinaturas"><i class="fa fa-angle-right"></i>Assinaturas</a></li>
                  <li class="<?php echo @$frequencias ?>"><a href="frequencias"><i class="fa fa-angle-right"></i>Frequências</a></li>
                </ul>
              </li>


							<li class="treeview <?php echo $menu_proteses ?>">
								<a href="#">
									<i class="fa fa-user"></i>
									<span>Prótese Capilar</span>
									<i class="fa fa-angle-left pull-right"></i></a>
								<ul class="treeview-menu">
									<li class="<?php echo @$proteses ?>"><a href="proteses"><i class="fa fa-angle-right"></i>Próteses</a></li>
									<li class="<?php echo @$simulador ?>"><a href="simulador"><i class="fa fa-angle-right"></i>Simulador</a></li>
									<li class="<?php echo @$manutencoes ?>"><a href="manutencoes"><i class="fa fa-angle-right"></i>Manutenção</a></li>
									<li class="<?php echo @$analises ?>"><a href="analises"><i class="fa fa-angle-right"></i>Analises</a></li>
								
								</ul>
							</li>

              <li class="treeview <?php echo $menu_produtos ?>">
                <a href="#">
                  <i class="fa fa-plus"></i>
                  <span>Produtos</span>
                  <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                  <li class="<?php echo @$produtos ?>"><a href="produtos"><i class="fa fa-angle-right"></i>Produtos</a></li>
                  <li class="<?php echo @$cat_produtos ?>"><a href="cat_produtos"><i class="fa fa-angle-right"></i>Categorias</a></li>
                  <li class="<?php echo @$estoque ?>"><a href="estoque"><i class="fa fa-angle-right"></i>Estoque Baixo</a></li>
                  <li class="<?php echo @$saidas ?>"><a href="saidas"><i class="fa fa-angle-right"></i>Saídas</a></li>
                  <li class="<?php echo @$entradas ?>"><a href="entradas"><i class="fa fa-angle-right"></i>Entradas</a></li>
                </ul>
              </li>

              <li class="treeview <?php echo @$menu_financeiro ?>" >
                <a href="#">
                  <i class="fa fa-usd"></i>
                  <span>Financeiro</span>
                  <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                  <li class="<?php echo @$vendas ?>"><a href="vendas"><i class="fa fa-angle-right"></i>Vendas</a></li>
                  <li class="<?php echo @$compras ?>"><a href="compras"><i class="fa fa-angle-right"></i>Compras</a></li>
                  <li class="<?php echo @$pagar ?>"><a href="pagar"><i class="fa fa-angle-right"></i>Contas à Pagar</a></li>
                  <li class="<?php echo @$receber ?>"><a href="receber"><i class="fa fa-angle-right"></i>Contas à Receber</a></li>
                  <li class="<?php echo @$receber_vencidas ?>"><a href="receber_vencidas"><i class="fa fa-angle-right"></i>Recebimentos Vencidos</a></li>
                  <li class="<?php echo @$comissoes ?>"><a href="comissoes"><i class="fa fa-angle-right"></i>Comissões</a></li>
                </ul>
              </li>

              <li class="treeview">
                <a href="#">
                  <i class="fa fa-credit-card-alt"></i>
                  <span>Assinaturas</span>
                  <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                  <li class="treeview <?php echo @$planos ?>"><a href="planos"><i class="fa fa-angle-right"></i>Planos</a></li>
                  <li class="<?php echo @$receber ?>"><a href="receber"><i class="fa fa-angle-right"></i>Receber</a></li>
                  <li class="treeview <?php echo @$cobrancas_pagas ?>"><a href="pagas"><i class="fa fa-angle-right"></i>Cobranças Pagas</a></li>
                </ul>
              </li>

              <li class="treeview <?php echo $menu_agendamentos ?>">
                <a href="#">
                  <i class="fa fa-calendar-o"></i>
                  <span>Agendamento / Serviço</span>
                  <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                  <li class="<?php echo @$agendamentos ?>"><a href="agendamentos"><i class="fa fa-angle-right"></i>Agendamentos</a></li>
                  <li class="<?php echo @$servicos_agenda ?>"><a href="servicos_agenda"><i class="fa fa-angle-right"></i>Serviços</a></li>
                </ul>
              </li>

              <li class="treeview <?php echo @$menu_relatorio ?>" >
                <a href="#">
                  <i class="fa fa-file-pdf-o"></i>
                  <span>Relatórios</span>
                  <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                  <!-- ✅ FIX: rel é arquivo físico -->
                  <li class="<?php echo @$rel_produtos ?>"><a href="<?php echo $url_painel_assets; ?>/rel/rel_produtos_class.php" target="_blank"><i class="fa fa-angle-right"></i>Relatório de Produtos</a></li>

                  <li class="<?php echo @$rel_entradas ?>"><a href="#" data-toggle="modal" data-target="#RelEntradas"><i class="fa fa-angle-right"></i>Entradas / Ganhos</a></li>
                  <li class="<?php echo @$rel_saidas ?>"><a href="#" data-toggle="modal" data-target="#RelSaidas"><i class="fa fa-angle-right"></i>Saídas / Despesas</a></li>
                  <li class="<?php echo @$rel_comissoes ?>"><a href="#" data-toggle="modal" data-target="#RelComissoes"><i class="fa fa-angle-right"></i>Relatório de Comissões</a></li>
                  <li class="<?php echo @$rel_contas ?>"><a href="#" data-toggle="modal" data-target="#RelCon"><i class="fa fa-angle-right"></i>Relatório de Contas</a></li>
                  <li class="<?php echo @$rel_servicos ?>"><a href="#" data-toggle="modal" data-target="#RelServicos"><i class="fa fa-angle-right"></i>Relatório de Serviços</a></li>
                  <li class="<?php echo @$rel_aniv ?>"><a href="#" data-toggle="modal" data-target="#RelAniv"><i class="fa fa-angle-right"></i>Relatório de Aniversáriantes</a></li>
                  <li class="<?php echo @$rel_lucro ?>"><a href="#" data-toggle="modal" data-target="#RelLucro"><i class="fa fa-angle-right"></i>Demonstrativo de Lucro</a></li>

                  <!-- ✅ FIX: rel é arquivo físico -->
                  <li class="<?php echo @$rel_ina ?>" ><a target="_blank" href="<?php echo $url_painel_assets; ?>/rel/sintetico_inadimplentes_class.php" ><i class="fa fa-angle-right"></i>Relatório de Inadimplentes</a></li>
                </ul>
              </li>

              <li class="treeview <?php echo @$marketing ?>" >
                <a href="#">
                  <i class="fa fa-whatsapp"></i>
                  <span> Marketing</span>
                  <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                  <li class="treeview <?php echo @$marketing ?>">
                    <a href="marketing">
                      <i class="fa fa-angle-right"></i> <span>Campanha Marketing</span>
                    </a>
                  </li>
                  <li class="<?php echo @$grupos_disparos ?>"><a href="grupos_disparos" target="_blank"><i class="fa fa-angle-right"></i>Grupos Disparos</a></li>
                </ul>
              </li>

              <li class="treeview <?php echo @$calendario ?>">
                <a href="calendario">
                  <i class="fa fa-calendar-o"></i> <span>Calendário</span>
                </a>
              </li>

              <li class="treeview <?php echo $menu_site ?>" >
                <a href="#">
                  <i class="fa fa-globe"></i>
                  <span>Dados do Site</span>
                  <i class="fa fa-angle-left pull-right"></i>
                </a>
                <ul class="treeview-menu">
                  <li class="<?php echo @$avaliacoes ?>"><a href="avaliacoes"><i class="fa fa-angle-right"></i>Avaliações</a></li>
                  <li class="<?php echo @$textos_index ?>"><a href="textos_index"><i class="fa fa-angle-right"></i>Textos Index</a></li>
                  <li class="<?php echo @$seo ?>"><a href="seo"><i class="fa fa-angle-right"></i>SEO / Google / Meta</a></li>
                  <li class="<?php echo @$comentarios ?>"><a href="comentarios"><i class="fa fa-angle-right"></i>Comentários</a></li>
                  <li class="<?php echo @$faq ?>"><a href="faq"><i class="fa fa-angle-right"></i> Dúvidas Frequentes</a></li>
                  <li class="<?php echo @$foto_admin ?>"><a href="foto_admin"><i class="fa fa-angle-right"></i> Fotos home</a></li>
                  <li class="<?php echo @$site ?>"><a href="site"><i class="fa fa-angle-right"></i>Portfolio</a></li>
                  <li class="<?php echo @$bio ?>"><a href="bio"><i class="fa fa-angle-right"></i>Bio</a></li>
                </ul>
              </li>

              <li class="treeview <?php echo @$caixas ?>" >
                <a href="caixas">
                  <i class="fa fa-server"></i> <span>Caixas</span>
                </a>
              </li>

              <li class="treeview <?php echo @$anotacoes ?>">
                <a href="anotacoes">
                  <i class="fa fa-sticky-note"></i> <span>Anotações</span>
                </a>
              </li>

              <?php if(@$atendimento == 'Sim'){ ?>
                <li class="treeview">
                  <a href="agenda">
                    <i class="fa fa-calendar-o"></i> <span>Minha Agenda</span>
                  </a>
                </li>

                <li class="treeview">
                  <a href="meus_servicos">
                    <i class="fa fa-server"></i> <span>Meus Serviços</span>
                  </a>
                </li>

                <li class="treeview">
                  <a href="minhas_comissoes">
                    <i class="fa fa-server"></i> <span>Minhas Comissões</span>
                  </a>
                </li>

                <li class="treeview">
                  <a href="#">
                    <i class="fa fa-usd"></i>
                    <span>Meus Horário / Dias</span>
                    <i class="fa fa-clock-o pull-right"></i>
                  </a>
                  <ul class="treeview-menu">
                    <li><a href="dias"><i class="fa fa-angle-right"></i>Horários / Dias</a></li>
                    <li><a href="servicos_func"><i class="fa fa-angle-right"></i>Lançar Serviços</a></li>
                    <li><a href="dias_bloqueio"><i class="fa fa-angle-right"></i>Bloqueio de Dias</a></li>
                  </ul>
                </li>
              <?php } ?>

              <li class="treeview <?php echo @$verificar_pgtos ?>">
                <a href="#" onclick="verificarPg()">
                  <i class="fa fa-spinner"></i> <span>Verificar Pagamentos</span>
                </a>
              </li>

            </ul>
          </div>
        </nav>
      </aside>
    </div>

    <!-- header-starts -->
    <div class="sticky-header header-section ">
      <div class="header-left">
        <button id="showLeftPush" data-toggle="collapse" data-target=".collapse"><i class="fa fa-bars"></i></button>

        <div class="profile_details_left">
          <ul class="nofitications-dropdown">
            <?php if($atendimento == 'Sim'){ 
              $query = $pdo->query("SELECT * FROM agendamentos where data = curDate() and funcionario = '$id_usuario' and status = 'Agendado'");
              $res = $query->fetchAll(PDO::FETCH_ASSOC);
              $total_agendamentos_hoje_usuario_pendentes = @count($res);
            ?>
              <li class="dropdown head-dpdn">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-bell"></i><span class="badge text-danger"><?php echo $total_agendamentos_hoje_usuario_pendentes ?></span></a>
                <ul class="dropdown-menu">
                  <li>
                    <div class="notification_header" align="center">
                      <h3><?php echo $total_agendamentos_hoje_usuario_pendentes ?> Agendamento Pendente Hoje</h3>
                    </div>
                  </li>

                  <?php 
                  for($i=0; $i < @count($res); $i++){
                    $id = $res[$i]['id'];								
                    $cliente = $res[$i]['cliente'];
                    $hora = $res[$i]['hora'];
                    $servico = $res[$i]['servico'];
                    $horaF = date("H:i", strtotime($hora));

                    $query2 = $pdo->query("SELECT * FROM servicos where id = '$servico'");
                    $res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
                    if(@count($res2) > 0){
                      $nome_serv = $res2[0]['nome'];
                      $valor_serv = $res2[0]['valor'];
                    }else{
                      $nome_serv = 'Não Lançado';
                      $valor_serv = '';
                    }

                    $query2 = $pdo->query("SELECT * FROM clientes where id = '$cliente'");
                    $res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
                    if(@count($res2) > 0){
                      $nome_cliente = $res2[0]['nome'];
                    }else{
                      $nome_cliente = 'Sem Cliente';
                    }
                  ?>
                    <li>
                      <div class="notification_desc">
                        <p><b><?php echo $horaF ?> </b> - <?php echo $nome_cliente ?> / <?php echo $nome_serv ?></p>
                        <p><span></span></p>
                      </div>
                      <div class="clearfix"></div>	
                    </li>
                  <?php } ?>

                  <li>
                    <div class="notification_bottom" style="background: #ffe8e6">
                      <a href="agenda">Ver Agendamentos</a>
                    </div> 
                  </li>
                </ul>
              </li>
            <?php } ?>

            <?php if(@$rel_aniv == ''){ 
              $query = $pdo->query("SELECT * FROM clientes where month(data_nasc) = '$dataMesInicial' and day(data_nasc) = '$dataDiaInicial' order by data_nasc asc, id asc");
              $res = $query->fetchAll(PDO::FETCH_ASSOC);
              $total_aniversariantes_hoje = @count($res);
            ?>
              <li class="dropdown head-dpdn">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-birthday-cake" style="color:#FFF"></i><span class="badge" style="background: #2b6b39"><?php echo $total_aniversariantes_hoje ?></span></a>
                <ul class="dropdown-menu">
                  <li>
                    <div class="notification_header" align="center">
                      <h3><?php echo $total_aniversariantes_hoje ?> Aniversariando Hoje</h3>
                    </div>
                  </li>

                  <?php 
                  for($i=0; $i < @count($res); $i++){
                    $nome = $res[$i]['nome'];	
                    $telefone = $res[$i]['telefone'];
                  ?>
                    <li>									
                      <div class="notification_desc">
                        <p><b><?php echo $nome ?> </b> - <?php echo $telefone ?> </p>
                        <p><span></span></p>
                      </div>
                      <div class="clearfix"></div>	
                    </li>
                  <?php } ?>

                  <li>
                    <div class="notification_bottom" style="background: #d9ffe1">
                      <a href="#" data-toggle="modal" data-target="#RelAniv">Relatório Aniversáriantes</a>
                    </div> 
                  </li>
                </ul>
              </li>
            <?php } ?>

            <?php if(@$clientes_retorno == ''){ 
              $query = $pdo->query("SELECT * FROM clientes where alertado != 'Sim' and data_retorno < curDate() ORDER BY data_retorno asc");
              $res = $query->fetchAll(PDO::FETCH_ASSOC);
              $total_clientes_retorno = @count($res);
            ?>
              <li class="dropdown head-dpdn">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-users" style="color:#FFF"></i><span class="badge" style="background: #c93504"><?php echo $total_clientes_retorno ?></span></a>
                <ul class="dropdown-menu">
                  <li>
                    <div class="notification_header" align="center">
                      <h3><?php echo $total_clientes_retorno ?> Cliente com Retorno Pendente</h3>
                    </div>
                  </li>

                  <?php 
                  for($i=0; $i < @count($res); $i++){
                    $nome = $res[$i]['nome'];	
                    $telefone = $res[$i]['telefone'];
                  ?>
                    <li>									
                      <div class="notification_desc">
                        <p><b><?php echo $nome ?> </b> - <?php echo $telefone ?> </p>
                        <p><span></span></p>
                      </div>
                      <div class="clearfix"></div>	
                    </li>
                  <?php } ?>

                  <li>
                    <div class="notification_bottom" style="background: #ffcdbd">
                      <a href="clientes_retorno">Ver Clientes</a>
                    </div> 
                  </li>
                </ul>
              </li>
            <?php } ?>

            <?php if(@$comentarios == ''){ 
              $query = $pdo->query("SELECT * FROM comentarios where ativo != 'Sim'");
              $res = $query->fetchAll(PDO::FETCH_ASSOC);
              $total_comentarios = @count($res);
            ?>
              <li class="dropdown head-dpdn">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><i class="fa fa-comment" style="color:#FFF"></i><span class="badge" style="background: #22168a"><?php echo $total_comentarios ?></span></a>
                <ul class="dropdown-menu">
                  <li>
                    <div class="notification_header" align="center">
                      <h3><?php echo $total_comentarios ?> Depoimentos Pendente</h3>
                    </div>
                  </li>

                  <?php 
                  for($i=0; $i < @count($res); $i++){
                    $nome = $res[$i]['nome'];
                  ?>
                    <li>									
                      <div class="notification_desc">
                        <p><b>Cliente: <?php echo $nome ?> </b> </p>
                        <p><span></span></p>
                      </div>
                      <div class="clearfix"></div>	
                    </li>
                  <?php } ?>

                  <li>
                    <div class="notification_bottom" style="background: #d8d4fc">
                      <a href="comentarios">Ver Depoimentos</a>
                    </div> 
                  </li>
                </ul>
              </li>
            <?php } ?>
          </ul>
          <div class="clearfix"> </div>
        </div>
      </div>

      <div class="header-right">
        <div class="profile_details">
          <ul>
            <li class="dropdown profile_details_drop">
              <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
                <div class="profile_img">
                  <span class="prfil-img">
                    <img src="<?php echo $url_painel_assets; ?>/img/perfil/<?php echo $foto_usuario ?>" alt="" width="50" height="50">
                  </span>
                  <div class="user-name esc">
                    <p><?php echo $nome_usuario ?></p>
                    <span><?php echo $nivel_usuario ?></span>
                  </div>
                  <i class="fa fa-angle-down lnr"></i>
                  <i class="fa fa-angle-up lnr"></i>
                  <div class="clearfix"></div>
                </div>
              </a>

              <ul class="dropdown-menu drp-mnu">
                <?php if(@$configuracoes == ''){ ?>
                  <li><a href="configuracoes"><i class="fa fa-cog"></i> Configurações</a></li>
                <?php } ?>

                <li><a href="" data-toggle="modal" data-target="#modalPerfil"><i class="fa fa-suitcase"></i> Editar Perfil</a></li>

                <!-- ✅ FIX: logout é arquivo físico -->
                <li><a href="<?php echo $url_painel_assets; ?>/logout.php"><i class="fa fa-sign-out"></i> Sair</a></li>
              </ul>
            </li>
          </ul>
        </div>
        <div class="clearfix"> </div>
      </div>
      <div class="clearfix"> </div>
    </div>
    <!-- //header-ends -->

    <!-- main content start-->
       <!-- main content start-->
    <div id="page-wrapper">
      <?php
      $pag = isset($pag) ? trim((string)$pag) : '';
      $pag = preg_replace('/[^a-z0-9_-]/i', '', $pag);

      if ($pag === '') {
        $pag = $pag_inicial;
      }

      $arquivo_pagina = __DIR__ . '/paginas/' . $pag . '.php';
      $arquivo_home   = __DIR__ . '/paginas/' . $pag_inicial . '.php';

      if (is_file($arquivo_pagina)) {
        require_once $arquivo_pagina;
      } elseif ($mostrar_marketplace) {
        // Nunca faz fallback do Master para home.php, pois ela é exclusiva do Banco Individual da loja.
        echo '<div class="alert alert-danger" style="margin:15px;">Página administrativa global não encontrada.</div>';
      } elseif (is_file($arquivo_home)) {
        require_once $arquivo_home;
      } else {
        echo '<div class="alert alert-danger" style="margin:15px;">Página não encontrada.</div>';
      }
      ?>
    </div>

    <!--footer-->
    <div class="footer">
      <p> <a href="https://jc.tec.br" target="_blank">BarberBot</a></p>
    </div>
    <!--//footer-->
  </div>

  <!-- Classie -->
  <script src="<?php echo $url_painel_assets; ?>/js/classie.js"></script>
  <script>
    var menuLeft = document.getElementById('cbp-spmenu-s1'),
      showLeftPush = document.getElementById('showLeftPush'),
      body = document.body;

    showLeftPush.onclick = function() {
      classie.toggle(this, 'active');
      classie.toggle(body, 'cbp-spmenu-push-toright');
      classie.toggle(menuLeft, 'cbp-spmenu-open');
      disableOther('showLeftPush');
    };

    function disableOther(button) {
      if (button !== 'showLeftPush') {
        classie.toggle(showLeftPush, 'disabled');
      }
    }

    showLeftPush2 = document.getElementById('showLeftPush2'),

    showLeftPush2.onclick = function() {
      classie.toggle(this, 'active');
      classie.toggle(body, 'cbp-spmenu-push-toright');
      classie.toggle(menuLeft, 'cbp-spmenu-open');
      disableOther2('showLeftPush2');
    };

    function disableOther2(button) {
      if (button !== 'showLeftPush2') {
        classie.toggle(showLeftPush2, 'disabled');
      }
    }
  </script>

  <script src="<?php echo $url_painel_assets; ?>/js/jquery.nicescroll.js"></script>
  <script src="<?php echo $url_painel_assets; ?>/js/scripts.js"></script>

  <script src="<?php echo $url_painel_assets; ?>/js/SidebarNav.min.js" type="text/javascript"></script>
  <script>
    $('.sidebar-menu').SidebarNav()
  </script>

  <script src="<?php echo $url_painel_assets; ?>/js/bootstrap.js"></script>
</body>
</html>

<!-- SweetAlert JS -->
<script src="<?php echo $url_painel_assets; ?>/js/sweetalert2.all.min.js"></script>
<link rel="stylesheet" href="<?php echo $url_painel_assets; ?>/js/sweetalert1.min.css">
<script src="<?php echo $url_painel_assets; ?>/js/alertas.js"></script>

<!-- Mascaras JS -->
<script type="text/javascript" src="<?php echo $url_painel_assets; ?>/js/mascaras.js"></script>

<!-- Ajax para funcionar Mascaras JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.11/jquery.mask.min.js"></script>

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style type="text/css">
  .select2-selection__rendered {
    line-height: 36px !important;
    font-size:16px !important;
    color:#666666 !important;
  }
  .select2-selection {
    height: 36px !important;
    font-size:16px !important;
    color:#666666 !important;
  }
</style>

<?php
/*
  Modal de Dúvidas pós-login (1 vez)
  - NÃO altera lógica do painel
  - só lê uma flag de sessão e abre a modal
*/

// Usa somente o contexto TENANT já confirmado pelo painel.
// O MASTER permanece sem slug e usa apenas /duvidas.php.
$bb_slug = '';
$bb_sessao_tenant = strpos((string)session_name(), 'BB_TENANT') === 0;

if ($bb_sessao_tenant && !empty($bb_tenant_slug_ajax)) {
  $bb_slug = preg_replace('/[^a-z0-9_-]/i', '', (string)$bb_tenant_slug_ajax);
}

$bb_link_duvidas = '/duvidas.php';
$bb_link_comecar = '/comecar.php';
if ($bb_slug !== '') {
  $bb_slug_url = rawurlencode($bb_slug);
  $bb_link_duvidas = '/loja/' . $bb_slug_url . '/duvidas.php';
  $bb_link_comecar = '/loja/' . $bb_slug_url . '/comecar.php';
}

// A modal é destinada ao profissional da barbearia após o primeiro acesso.
$bb_show = ($bb_sessao_tenant && $bb_slug !== '' && !empty($_SESSION['bb_show_duvidas_modal'])) ? 1 : 0;
if ($bb_show) {
  unset($_SESSION['bb_show_duvidas_modal']);
}
?>

<!-- MODAL: DÚVIDAS pós-login -->
<div class="modal fade" id="modalDuvidasLogin" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document" style="max-width:520px;">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title">
          <i class="fa fa-question-circle"></i> Tutoriais e Dúvidas
        </h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">
        <p style="margin:0 0 10px;">
          Você mesmo pode fazer o cadastro e a configuração inicial do BarberBot.
        </p>

        <p style="margin:0 0 15px;">
          Acesse os vídeos para aprender a cadastrar profissionais, serviços, horários e as demais informações da sua barbearia.
        </p>

        <?php if ($bb_slug != '') { ?>
          <p class="text-muted" style="margin:0 0 15px;">
            Unidade: <strong><?php echo htmlspecialchars($bb_slug); ?></strong>
          </p>
        <?php } ?>

        <a href="<?php echo htmlspecialchars($bb_link_duvidas, ENT_QUOTES); ?>"
           target="_blank"
           class="btn btn-primary btn-block">
          <i class="fa fa-play"></i> Abrir Vídeos e Dúvidas
        </a>

        <a href="<?php echo htmlspecialchars($bb_link_comecar, ENT_QUOTES); ?>"
           target="_blank"
           class="btn btn-success btn-block">
          <i class="fa fa-user-plus"></i> Começar Cadastro dos Profissionais
        </a>

        <button type="button" class="btn btn-default btn-block" onclick="(function(){
          var el = document.getElementById('bbDuvidasLinkCopy');
          if(!el) return;
          el.select();
          try{ document.execCommand('copy'); }catch(e){}
        })()">
          <i class="fa fa-copy"></i> Copiar link
        </button>

        <input type="text"
               id="bbDuvidasLinkCopy"
               readonly
               value="<?php echo htmlspecialchars($bb_link_duvidas, ENT_QUOTES); ?>"
               style="position:absolute; left:-9999px; top:-9999px;">
      </div>

      <div class="modal-footer">
        <button type="button" class="btn btn-default" data-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<script type="text/javascript">
  (function(){
    var show = <?php echo $bb_show ? 'true' : 'false'; ?>;
    if(!show) return;

    $(document).ready(function(){
      $('#modalDuvidasLogin').modal('show');
    });
  })();
</script>

<!-- Modal Perfil-->
<div class="modal fade" id="modalPerfil" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="exampleModalLabel">Editar Perfil</h4>
        <button id="btn-fechar-perfil" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
          <span aria-hidden="true" >&times;</span>
        </button>
      </div>
      <form method="post" id="form-perfil">
        <?php
          // Mantém o padrão: em TENANT, sempre enviar o slug para o backend via POST (evita salvar no MASTER)
          $bb_slug_form = $_SESSION['tenant_slug'] ?? $_SESSION['loja'] ?? ($_GET['loja'] ?? '');
          $bb_slug_form = preg_replace('/[^a-z0-9_-]/i', '', (string)$bb_slug_form);
        ?>
        <input type="hidden" name="loja" value="<?php echo htmlspecialchars((string)$bb_slug_form, ENT_QUOTES, 'UTF-8'); ?>">

        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label for="exampleInputEmail1">Nome</label>
                <input type="text" class="form-control" id="nome-perfil" name="nome" placeholder="Nome" value="<?php echo $nome_usuario ?>" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label for="exampleInputEmail1">Email</label>
                <input type="email" class="form-control" id="email-perfil" name="email" placeholder="Email" value="<?php echo $email_usuario ?>" required>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label for="exampleInputEmail1">Telefone</label>
                <input type="text" class="form-control" id="telefone-perfil" name="telefone" placeholder="Telefone" value="<?php echo $telefone_usuario ?>" >
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label for="exampleInputEmail1">CPF</label>
                <input type="text" class="form-control" id="cpf-perfil" name="cpf" placeholder="CPF" value="<?php echo $cpf_usuario ?>">
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label for="exampleInputEmail1">Senha</label>
                <input type="password" class="form-control" id="senha-perfil" name="senha" placeholder="Senha" value="<?php echo $senha_usuario ?>" required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label for="exampleInputEmail1">Confirmar Senha</label>
                <input type="password" class="form-control" id="conf-senha-perfil" name="conf_senha" placeholder="Confirmar Senha" required>
              </div>
            </div>

            <div class="col-md-4">
              <div class="form-group">
                <label for="exampleInputEmail1">Atendimento</label>
                <select class="form-control" name="atendimento" id="atendimento-perfil">
                  <option <?php if($atendimento == 'Sim'){ ?> selected <?php } ?> value="Sim">Sim</option>
                  <option <?php if($atendimento == 'Não'){ ?> selected <?php } ?> value="Não">Não</option>
                </select>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-8">
              <div class="form-group">
                <label for="exampleInputEmail1">Endereço</label>
                <input type="text" class="form-control" id="endereco-perfil" name="endereco" placeholder="Rua X Número 1 Bairro xxx" value="<?php echo $endereco_usuario ?>" >
              </div>
            </div>

            <div class="col-md-4">
              <div class="form-group">
                <label for="exampleInputEmail1">Intervalo Minutos</label>
                <input type="number" class="form-control" id="intervalo_perfil" name="intervalo" placeholder="Intervalo Horários" value="<?php echo $intervalo_horarios ?>" required>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-8">
              <div class="form-group">
                <label>Foto</label>
                <input class="form-control" type="file" name="foto" onChange="carregarImgPerfil();" id="foto-usu">
              </div>
            </div>
            <div class="col-md-4">
              <div id="divImg">
                <img src="<?php echo $url_painel_assets; ?>/img/perfil/<?php echo $foto_usuario ?>" width="80px" id="target-usu">
              </div>
            </div>
          </div>

          <input type="hidden" name="id" value="<?php echo $id_usuario ?>">

          <br>
          <small><div id="mensagem-perfil" align="center"></div></small>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Editar Perfil</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Config-->
<div class="modal fade" id="modalConfig" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="exampleModalLabel">Editar Configurações</h4>
        <button id="btn-fechar-config" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
          <span aria-hidden="true" >&times;</span>
        </button>
      </div>
    </div>
  </div>
</div>

<!-- Modal Rel Entradas / Ganhos -->
<div class="modal fade" id="RelEntradas" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="exampleModalLabel">Relatório de Ganhos
          <small>(
            <a href="#" onclick="datas('1980-01-01', 'tudo-Ent', 'Ent')"><span style="color:#000" id="tudo-Ent">Tudo</span></a> /
            <a href="#" onclick="datas('<?php echo $data_atual ?>', 'hoje-Ent', 'Ent')"><span id="hoje-Ent">Hoje</span></a> /
            <a href="#" onclick="datas('<?php echo $data_mes ?>', 'mes-Ent', 'Ent')"><span style="color:#000" id="mes-Ent">Mês</span></a> /
            <a href="#" onclick="datas('<?php echo $data_ano ?>', 'ano-Ent', 'Ent')"><span style="color:#000" id="ano-Ent">Ano</span></a>
          )</small>
        </h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <!-- ✅ FIX: action é arquivo físico -->
      <form method="post" action="<?php echo $url_painel_assets; ?>/rel/rel_entradas_class.php" target="_blank">
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Data Inicial</label>
                <input type="date" class="form-control" name="dataInicial" id="dataInicialRel-Ent" value="<?php echo date('Y-m-d') ?>" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Data Final</label>
                <input type="date" class="form-control" name="dataFinal" id="dataFinalRel-Ent" value="<?php echo date('Y-m-d') ?>" required>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-group">
                <label>Entradas / Ganhos</label>
                <select class="form-control sel13" name="filtro" style="width:100%;">
                  <option value="">Todas</option>
                  <option value="Venda">Vendas</option>
                  <option value="Serviço">Serviços</option>
                  <option value="Conta">Demais Ganhos</option>
                </select>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-group">
                <label>Selecionar Cliente</label>
                <select class="form-control selcli" name="cliente" style="width:100%;">
                  <option value="">Todos</option>
                  <?php 
                  $query = $pdo->query("SELECT * FROM clientes");
                  $res = $query->fetchAll(PDO::FETCH_ASSOC);
                  $total_reg = @count($res);
                  if($total_reg > 0){
                    for($i=0; $i < $total_reg; $i++){
                      echo '<option value="'.$res[$i]['id'].'">'.$res[$i]['nome'].'</option>';
                    }
                  }
                  ?>
                </select>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Gerar Relatório</button>
        </div>
      </form>

    </div>
  </div>
</div>

<!-- Modal Rel Saidas / Despesas -->
<div class="modal fade" id="RelSaidas" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="exampleModalLabel">Relatório de Saídas
          <small>(
            <a href="#" onclick="datas('1980-01-01', 'tudo-Saida', 'Saida')"><span style="color:#000" id="tudo-Saida">Tudo</span></a> /
            <a href="#" onclick="datas('<?php echo $data_atual ?>', 'hoje-Saida', 'Saida')"><span id="hoje-Saida">Hoje</span></a> /
            <a href="#" onclick="datas('<?php echo $data_mes ?>', 'mes-Saida', 'Saida')"><span style="color:#000" id="mes-Saida">Mês</span></a> /
            <a href="#" onclick="datas('<?php echo $data_ano ?>', 'ano-Saida', 'Saida')"><span style="color:#000" id="ano-Saida">Ano</span></a>
          )</small>
        </h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <!-- ✅ FIX: action é arquivo físico -->
      <form method="post" action="<?php echo $url_painel_assets; ?>/rel/rel_saidas_class.php" target="_blank">
        <div class="modal-body">
          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label>Data Inicial</label>
                <input type="date" class="form-control" name="dataInicial" id="dataInicialRel-Saida" value="<?php echo date('Y-m-d') ?>" required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Data Final</label>
                <input type="date" class="form-control" name="dataFinal" id="dataFinalRel-Saida" value="<?php echo date('Y-m-d') ?>" required>
              </div>
            </div>

            <div class="col-md-4">
              <div class="form-group">
                <label>Saídas / Despesas</label>
                <select class="form-control sel13" name="filtro" style="width:100%;">
                  <option value="">Todas</option>
                  <option value="Conta">Despesas</option>
                  <option value="Comissão">Comissões</option>
                  <option value="Compra">Compras</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Gerar Relatório</button>
        </div>
      </form>

    </div>
  </div>
</div>

<!-- Modal Rel Comissoes -->
<div class="modal fade" id="RelComissoes" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="exampleModalLabel">Relatório de Comissões
          <small>(
            <a href="#" onclick="datas('1980-01-01', 'tudo-Com', 'Com')"><span style="color:#000" id="tudo-Com">Tudo</span></a> /
            <a href="#" onclick="datas('<?php echo $data_atual ?>', 'hoje-Com', 'Com')"><span id="hoje-Com">Hoje</span></a> /
            <a href="#" onclick="datas('<?php echo $data_mes ?>', 'mes-Com', 'Com')"><span style="color:#000" id="mes-Com">Mês</span></a> /
            <a href="#" onclick="datas('<?php echo $data_ano ?>', 'ano-Com', 'Com')"><span style="color:#000" id="ano-Com">Ano</span></a>
          )</small>
        </h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <!-- ✅ FIX: action é arquivo físico -->
      <form method="post" action="<?php echo $url_painel_assets; ?>/rel/rel_comissoes_class.php" target="_blank">
        <div class="modal-body">
          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label>Data Inicial</label>
                <input type="date" class="form-control" name="dataInicial" id="dataInicialRel-Com" value="<?php echo date('Y-m-d') ?>" required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Data Final</label>
                <input type="date" class="form-control" name="dataFinal" id="dataFinalRel-Com" value="<?php echo date('Y-m-d') ?>" required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Pago</label>
                <select class="form-control" name="pago" style="width:100%;">
                  <option value="">Todas</option>
                  <option value="Sim">Somente Pagas</option>
                  <option value="Não">Pendentes</option>
                </select>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-12">
              <div class="form-group">
                <label>Funcionário</label>
                <select class="form-control sel15" name="funcionario" style="width:100%;">
                  <option value="">Todos</option>
                  <?php 
                  $query = $pdo->query("SELECT * FROM usuarios where atendimento = 'Sim' ORDER BY id desc");
                  $res = $query->fetchAll(PDO::FETCH_ASSOC);
                  $total_reg = @count($res);
                  if($total_reg > 0){
                    for($i=0; $i < $total_reg; $i++){
                      echo '<option value="'.$res[$i]['id'].'">'.$res[$i]['nome'].'</option>';
                    }
                  }?>
                </select>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Gerar Relatório</button>
        </div>
      </form>

    </div>
  </div>
</div>

<!-- Modal Rel Contas -->
<div class="modal fade" id="RelCon" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="exampleModalLabel">Relatório de Contas
          <small>(
            <a href="#" onclick="datas('1980-01-01', 'tudo-Con', 'Con')"><span style="color:#000" id="tudo-Con">Tudo</span></a> /
            <a href="#" onclick="datas('<?php echo $data_atual ?>', 'hoje-Con', 'Con')"><span id="hoje-Con">Hoje</span></a> /
            <a href="#" onclick="datas('<?php echo $data_mes ?>', 'mes-Con', 'Con')"><span style="color:#000" id="mes-Con">Mês</span></a> /
            <a href="#" onclick="datas('<?php echo $data_ano ?>', 'ano-Con', 'Con')"><span style="color:#000" id="ano-Con">Ano</span></a>
          )</small>
        </h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <!-- ✅ FIX: action é arquivo físico -->
      <form method="post" action="<?php echo $url_painel_assets; ?>/rel/rel_contas_class.php" target="_blank">
        <div class="modal-body">
          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label>Data Inicial</label>
                <input type="date" class="form-control" name="dataInicial" id="dataInicialRel-Con" value="<?php echo date('Y-m-d') ?>" required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Data Final</label>
                <input type="date" class="form-control" name="dataFinal" id="dataFinalRel-Con" value="<?php echo date('Y-m-d') ?>" required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Pago</label>
                <select class="form-control" name="pago" style="width:100%;">
                  <option value="">Todas</option>
                  <option value="Sim">Somente Pagas</option>
                  <option value="Não">Pendentes</option>
                </select>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Pagar / Receber</label>
                <select class="form-control sel13" name="tabela" style="width:100%;">
                  <option value="pagar">Contas à Pagar</option>
                  <option value="receber">Contas à Receber</option>
                </select>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Consultar Por</label>
                <select class="form-control sel13" name="busca" style="width:100%;">
                  <option value="data_venc">Data de Vencimento</option>
                  <option value="data_pgto">Data de Pagamento</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Gerar Relatório</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Rel Lucro -->
<div class="modal fade" id="RelLucro" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="exampleModalLabel">Demonstrativo de Lucro
          <small>(
            <a href="#" onclick="datas('1980-01-01', 'tudo-Lucro', 'Lucro')"><span style="color:#000" id="tudo-Lucro">Tudo</span></a> /
            <a href="#" onclick="datas('<?php echo $data_atual ?>', 'hoje-Lucro', 'Lucro')"><span id="hoje-Lucro">Hoje</span></a> /
            <a href="#" onclick="datas('<?php echo $data_mes ?>', 'mes-Lucro', 'Lucro')"><span style="color:#000" id="mes-Lucro">Mês</span></a> /
            <a href="#" onclick="datas('<?php echo $data_ano ?>', 'ano-Lucro', 'Lucro')"><span style="color:#000" id="ano-Lucro">Ano</span></a>
          )</small>
        </h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <!-- ✅ FIX: action é arquivo físico -->
      <form method="post" action="<?php echo $url_painel_assets; ?>/rel/rel_lucro_class.php" target="_blank">
        <div class="modal-body">
          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label>Data Inicial</label>
                <input type="date" class="form-control" name="dataInicial" id="dataInicialRel-Lucro" value="<?php echo date('Y-m-d') ?>" required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Data Final</label>
                <input type="date" class="form-control" name="dataFinal" id="dataFinalRel-Lucro" value="<?php echo date('Y-m-d') ?>" required>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Gerar Relatório</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Rel Anivesariantes -->
<div class="modal fade" id="RelAniv" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="exampleModalLabel">Relatório de Aniversáriantes
          <small>(
            <a href="#" onclick="datas('1980-01-01', 'tudo-Aniv', 'Aniv')"><span style="color:#000" id="tudo-Aniv">Tudo</span></a> /
            <a href="#" onclick="datas('<?php echo $data_atual ?>', 'hoje-Aniv', 'Aniv')"><span id="hoje-Aniv">Hoje</span></a> /
            <a href="#" onclick="datas('<?php echo $data_mes ?>', 'mes-Aniv', 'Aniv')"><span style="color:#000" id="mes-Aniv">Mês</span></a> /
            <a href="#" onclick="datas('<?php echo $data_ano ?>', 'ano-Aniv', 'Aniv')"><span style="color:#000" id="ano-Aniv">Ano</span></a>
          )</small>
        </h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <!-- ✅ FIX: action é arquivo físico -->
      <form method="post" action="<?php echo $url_painel_assets; ?>/rel/rel_aniv_class.php" target="_blank">
        <div class="modal-body">
          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label>Data Inicial</label>
                <input type="date" class="form-control" name="dataInicial" id="dataInicialRel-Aniv" value="<?php echo date('Y-m-d') ?>" required>
              </div>
            </div>
            <div class="col-md-4">
              <div class="form-group">
                <label>Data Final</label>
                <input type="date" class="form-control" name="dataFinal" id="dataFinalRel-Aniv" value="<?php echo date('Y-m-d') ?>" required>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Gerar Relatório</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Rel Serviços -->
<div class="modal fade" id="RelServicos" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="exampleModalLabel">Relatório de Serviços
          <small>(
            <a href="#" onclick="datas('1980-01-01', 'tudo-Ser', 'Ser')"><span style="color:#000" id="tudo-Ser">Tudo</span></a> /
            <a href="#" onclick="datas('<?php echo $data_atual ?>', 'hoje-Ser', 'Ser')"><span id="hoje-Ser">Hoje</span></a> /
            <a href="#" onclick="datas('<?php echo $data_mes ?>', 'mes-Ser', 'Ser')"><span style="color:#000" id="mes-Ser">Mês</span></a> /
            <a href="#" onclick="datas('<?php echo $data_ano ?>', 'ano-Ser', 'Ser')"><span style="color:#000" id="ano-Ser">Ano</span></a>
          )</small>
        </h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <!-- ✅ FIX: action é arquivo físico -->
      <form method="post" action="<?php echo $url_painel_assets; ?>/rel/rel_servicos_class.php" target="_blank">
        <div class="modal-body">
          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Data Inicial</label>
                <input type="date" class="form-control" name="dataInicial" id="dataInicialRel-Ser" value="<?php echo date('Y-m-d') ?>" required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>Data Final</label>
                <input type="date" class="form-control" name="dataFinal" id="dataFinalRel-Ser" value="<?php echo date('Y-m-d') ?>" required>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Forma de Pagamento</label>
                <select class="form-control" name="pgto" style="width:100%;">
                  <option value="">Selecionar Pagamento</option>
                  <?php 
                  $query = $pdo->query("SELECT * FROM formas_pgto");
                  $res = $query->fetchAll(PDO::FETCH_ASSOC);
                  $total_reg = @count($res);
                  if($total_reg > 0){
                    for($i=0; $i < $total_reg; $i++){
                      echo '<option value="'.$res[$i]['nome'].'">'.$res[$i]['nome'].'</option>';
                    }
                  }
                  ?>
                </select>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-group">
                <label>Selecionar Serviço</label>
                <select class="form-control" name="servico" style="width:100%;">
                  <option value="">Selecionar Serviço</option>
                  <?php 
                  $query = $pdo->query("SELECT * FROM servicos");
                  $res = $query->fetchAll(PDO::FETCH_ASSOC);
                  $total_reg = @count($res);
                  if($total_reg > 0){
                    for($i=0; $i < $total_reg; $i++){
                      echo '<option value="'.$res[$i]['id'].'">'.$res[$i]['nome'].'</option>';
                    }
                  }
                  ?>
                </select>
              </div>
            </div>
          </div>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Gerar Relatório</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Verificar pgtos pendentes -->
<div class="modal fade" id="modalVerificar" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="exampleModalLabel">Verificando Pagamentos</h4>
        <button id="btn-fechar-pgtos" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -25px">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <div class="modal-body">
        <div id="verificar_pagamentos">
          <div align="center" id="loading_img">
            <img src="<?php echo $url_painel_assets; ?>/images/loading.gif">
          </div>
          <div align="center" id="textos_verificar" style="display:none"></div>
        </div>
      </div>

    </div>
  </div>
</div>

<script type="text/javascript">
  $(document).ready(function() {
    $('.sel15').select2({ dropdownParent: $('#RelComissoes') });
    $('.selcli').select2({ dropdownParent: $('#RelEntradas') });
  });
</script>

<script type="text/javascript">
  $("#form-perfil").submit(function (event) {
    event.preventDefault();
    var formData = new FormData(this);

    $.ajax({
      url: "<?php echo $url_painel_assets; ?>/editar-perfil.php",
      type: 'POST',
      data: formData,
      success: function (mensagem) {
        $('#mensagem-perfil').text('');
        $('#mensagem-perfil').removeClass()
        if (mensagem.trim() == "Editado com Sucesso") {
          $('#btn-fechar-perfil').click();
          location.reload();
        } else {
          $('#mensagem-perfil').addClass('text-danger')
          $('#mensagem-perfil').text(mensagem)
        }
      },
      cache: false,
      contentType: false,
      processData: false,
    });
  });
</script>

<script type="text/javascript">
  function carregarImgPerfil() {
    var target = document.getElementById('target-usu');
    var file = document.querySelector("#foto-usu").files[0];

    var reader = new FileReader();
    reader.onloadend = function () { target.src = reader.result; };

    if (file) reader.readAsDataURL(file);
    else target.src = "";
  }
</script>

<script type="text/javascript">
  $("#form-config").submit(function () {
    event.preventDefault();
    var formData = new FormData(this);

    $.ajax({
      url: "<?php echo $url_painel_assets; ?>/editar-config.php",
      type: 'POST',
      data: formData,
      success: function (mensagem) {
        $('#mensagem-config').text('');
        $('#mensagem-config').removeClass()
        if (mensagem.trim() == "Editado com Sucesso") {
          $('#btn-fechar-config').click();
          location.reload();
        } else {
          $('#mensagem-config').addClass('text-danger')
          $('#mensagem-config').text(mensagem)
        }
      },
      cache: false,
      contentType: false,
      processData: false,
    });
  });
</script>

<script type="text/javascript">
  function datas(data, id, campo){
    var data_atual = "<?=$data_atual?>";
    var separarData = data_atual.split("-");
    var mes = separarData[1];
    var ano = separarData[0];

    var separarId = id.split("-");
    if(separarId[0] == 'tudo'){ data_atual = '2100-12-31'; }
    if(separarId[0] == 'ano'){ data_atual = ano + '-12-31'; }

    if(separarId[0] == 'mes'){
      if(mes == 1 || mes == 3 || mes == 5 || mes == 7 || mes == 8 || mes == 10 || mes == 12){
        data_atual = ano + '-'+ mes + '-31';
      }else if (mes == 4 || mes == 6 || mes == 9 || mes == 11){
        data_atual = ano + '-'+ mes + '-30';
      }else{
        data_atual = ano + '-'+ mes + '-28';
      }
    }

    $('#dataInicialRel-'+campo).val(data);
    $('#dataFinalRel-'+campo).val(data_atual);

    document.getElementById('hoje-'+campo).style.color = "#000";
    document.getElementById('mes-'+campo).style.color = "#000";
    document.getElementById(id).style.color = "blue";
    document.getElementById('tudo-'+campo).style.color = "#000";
    document.getElementById('ano-'+campo).style.color = "#000";
    document.getElementById(id).style.color = "blue";
  }
</script>

<script type="text/javascript">
  function verificarPg(){
    $('#modalVerificar').modal('show');
    $('#loading_img').show();
    $('#textos_verificar').hide();

    $.ajax({
      url: "<?php echo $url_painel_assets; ?>/verificar_pgtos.php<?php echo ($bb_loja_query_ajax !== '') ? ('?' . $bb_loja_query_ajax) : ''; ?>",
      method: 'POST',
      data: {},
      dataType: "html",
      success:function(mensagem){
        $('#loading_img').hide();
        $('#textos_verificar').html(mensagem);
        $('#textos_verificar').show();
      }
    });
  }
</script>

<!-- Ajax para carregamento dinâmico -->
<script type="text/javascript">
  function bbLimparPaginaPainel(pagina) {
    pagina = String(pagina || '').trim();
    pagina = pagina.replace(/[^a-zA-Z0-9_-]/g, '');
    return pagina;
  }

  function carregarPag(pagina) {
    var paginaLimpa = bbLimparPaginaPainel(pagina);
    var lojaQuery = "<?php echo $bb_loja_query_ajax; ?>";
    var urlAjax = "<?php echo $url_painel_assets; ?>/paginas/" + encodeURIComponent(paginaLimpa) + ".php";
    var urlPublica = "<?php echo $url_painel_public; ?>/" + encodeURIComponent(paginaLimpa);

    if (paginaLimpa === '') {
      $('#page-wrapper').html('<div class="alert alert-danger" style="margin:15px;">Página inválida.</div>');
      return;
    }

    if (lojaQuery !== '') {
      urlAjax += '?' + lojaQuery;
    }

    $.ajax({
      url: urlAjax,
      method: 'GET',
      cache: false,
      beforeSend: function() {
        $('#page-wrapper').html('<div class="text-center" style="padding:25px;"><i class="fa fa-spinner fa-spin"></i> Carregando...</div>');
      },
      success: function(dados) {
        if (String(dados || '').toLowerCase().indexOf('out system') !== -1) {
          window.location.href = "<?php echo $url_sistema; ?>/";
          return;
        }

        $('#page-wrapper').html(dados);

        if (window.history && window.history.pushState) {
          history.pushState(null, null, urlPublica);
        }
      },
      error: function(xhr) {
        var codigo = xhr && xhr.status ? ' Código: ' + xhr.status : '';
        $('#page-wrapper').html('<div class="alert alert-danger" style="margin:15px;">Erro ao carregar a página.' + codigo + '</div>');
      }
    });
  }
</script>

<!-- ✅ FIX GLOBAL: impede que <a href=""> / <a href="#"> volte pra HOME por causa do <base href> -->
<script type="text/javascript">
(function () {
  if (window.__bb_fix_links_vazios) return;
  window.__bb_fix_links_vazios = true;

  document.addEventListener('click', function (event) {
    var el = event.target;
    if (!el) return;

    // acha o <a> mais próximo (sem depender de closest)
    while (el && el !== document && !(el.tagName && el.tagName.toLowerCase() === 'a')) {
      el = el.parentNode;
    }
    if (!el || !el.tagName || el.tagName.toLowerCase() !== 'a') return;

    var href = (el.getAttribute('href') || '').trim();

    // Funciona com ou sem javascript:void(0)
    if (href === '' || href === '#') {
      event.preventDefault(); // não navega/recarrega
      return;
    }

    // Compatibilidade: se já existir javascript:void(0), mantém ok
    if (/^javascript:\s*void\(0\)\s*;?$/i.test(href)) {
      event.preventDefault();
      return;
    }
  }, true);
})();
</script>
