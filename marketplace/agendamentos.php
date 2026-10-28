<?php
// ===== Sessão isolada por contexto (MASTER x TENANT) =====
// Evita que o cookie padrão (PHPSESSID) misture MASTER x TENANT.
// Regra:
// - /sistema (sem /loja/ e sem ?loja=) => BB_MASTER
// - /loja/<slug>/... OU ?loja=...      => sessão TENANT isolada por SLUG (BB_TENANT_<hash>)
// - demais                              => BB_SITE
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
} else {
  if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath, $mSlug)) {
    $__bb_reqSlug = bb_sess_clean_slug($mSlug[1]);
  }
}

$__bb_lojaByPath = (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath);

$__bb_hasLoja = $__bb_lojaByPath || !empty($_GET['loja']) || !empty($_POST['loja']);
$__bb_isSistema = (strpos($__bb_reqPath, '/sistema') === 0);

// Força MASTER em /sistema quando NÃO é loja por PATH e NÃO tem ?loja=
$__bb_forceMaster = ($__bb_isSistema && !$__bb_lojaByPath && empty($_GET['loja']) && empty($_POST['loja']));

// Sessão TENANT isolada por SLUG (cookie diferente por loja)
$__bb_tenantSessionName = 'BB_TENANT';
if ($__bb_reqSlug !== '') {
  $__bb_tenantSessionName = 'BB_TENANT_' . strtoupper(substr(sha1($__bb_reqSlug), 0, 12));
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

header('Content-Type: text/html; charset=utf-8');

/**
 * MULTI-TENANT: garante slug antes do conexao.php
 * (mantém lógica, só completa detecção por URI /loja/<slug>/...)
 */
if (!function_exists('mt_slug')) {
  function mt_slug(string $v): string {
    $v = strtolower(trim($v));
    return (string)preg_replace('/[^a-z0-9_-]/', '', $v);
  }
}

/**
 * MULTI-TENANT (master x loja):
 * - /loja/<slug>/...  => usa o tenant da URL
 * - ?loja=<slug>      => usa o tenant do parâmetro
 * - /agendamentos     => é o dono do sistema (master)
 *   (não pode herdar tenant_slug antigo de outra loja)
 */
$__uri = (string)($_SERVER['REQUEST_URI'] ?? '');
$__in_loja_path = ($__uri !== '' && preg_match('~^/loja/[a-z0-9_-]+/~i', $__uri));
if (empty($_GET['loja']) && !$__in_loja_path) {
  unset($_SESSION['tenant_slug']);
}



$loja = '';
if (!empty($_GET['loja'])) {
  $loja = mt_slug((string)$_GET['loja']);
} elseif (!empty($_SESSION['tenant_slug'])) {
  $loja = mt_slug((string)$_SESSION['tenant_slug']);
} else {
  $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
  if ($uri && preg_match('~^/loja/([a-z0-9_-]+)/~i', $uri, $m)) {
    $loja = mt_slug((string)$m[1]);
  }
}

if ($loja !== '') {
  $_SESSION['tenant_slug'] = $loja;
  $_GET['loja'] = $loja;
}

require_once __DIR__ . '/sistema/conexao.php';

// Base absoluta para assets estáticos (CSS/JS/imagens) e base dinâmica para rotas TENANT.
// Assets ficam na raiz pública. Rotas/AJAX respeitam /loja/{slug}/ quando a URL atual estiver no contexto da loja.
$__bb_script_dir = str_replace('\\', '/', dirname((string)($_SERVER['SCRIPT_NAME'] ?? '/')));
$BASE = ($__bb_script_dir === '/' || $__bb_script_dir === '.' || $__bb_script_dir === '')
  ? '/'
  : rtrim($__bb_script_dir, '/') . '/';
$__bb_useTenantPath = ($loja !== '' && $__bb_lojaByPath);
$BB_ROUTE_BASE = $__bb_useTenantPath ? ($BASE . 'loja/' . rawurlencode($loja) . '/') : $BASE;
$QS_LOJA = ($loja !== '' && !$__bb_useTenantPath) ? ('?loja=' . rawurlencode($loja)) : '';

if (!function_exists('bb_agendamento_public_asset')) {
  function bb_agendamento_public_asset(array $candidatos, string $fallback): string {
    $docroot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');

    foreach ($candidatos as $cand) {
      $cand = '/' . ltrim((string)$cand, '/');
      if ($docroot !== '' && is_file($docroot . $cand)) {
        return $cand;
      }
    }

    return '/' . ltrim($fallback, '/');
  }
}

$BB_ICON_192 = bb_agendamento_public_asset([
  '/images/favicon_192.png',
  '/images/favicon.png',
  '/sistema/img/icone.png'
], '/sistema/img/icone.png');

$BB_ICON_512 = bb_agendamento_public_asset([
  '/images/favicon_512.png',
  '/images/favicon.png',
  '/sistema/img/icone.png'
], $BB_ICON_192);

// Controle específico do pop-up promocional da tela de agendamento.
// Regra isolada: libera somente para o tenant PH.
// A identificação principal é o slug do tenant. Banco e domínio dedicado entram apenas
// como compatibilidade quando o acesso chega por domínio próprio, sem /loja/<slug>/ na URL.
$bb_popup_promocao_slug_permitido = 'ph-cabeleireiros-barbearia';
$bb_popup_promocao_db_permitido   = 'jctecbr_ph-cabeleireiros-barbearia';
$bb_popup_promocao_host_permitido = 'ph.jc.tec.br';
$bb_popup_promocao_agendamento_imagem = $BASE . 'images/divulgacao.jpg';

$bb_slug_popup_agendamento = '';
if ($loja !== '') {
  $bb_slug_popup_agendamento = mt_slug($loja);
} elseif (!empty($_SESSION['tenant_slug'])) {
  $bb_slug_popup_agendamento = mt_slug((string)$_SESSION['tenant_slug']);
} elseif (!empty($_SESSION['BARBER_LOJA']['slug'])) {
  $bb_slug_popup_agendamento = mt_slug((string)$_SESSION['BARBER_LOJA']['slug']);
} elseif (!empty($_SESSION['LOJA']['slug'])) {
  $bb_slug_popup_agendamento = mt_slug((string)$_SESSION['LOJA']['slug']);
}

$bb_db_popup_agendamento = '';
if (!empty($_SESSION['tenant_db'])) {
  $bb_db_popup_agendamento = strtolower(trim((string)$_SESSION['tenant_db']));
} elseif (!empty($_SESSION['BARBER_LOJA']['database_name'])) {
  $bb_db_popup_agendamento = strtolower(trim((string)$_SESSION['BARBER_LOJA']['database_name']));
} elseif (!empty($_SESSION['BARBER_LOJA']['db_name'])) {
  $bb_db_popup_agendamento = strtolower(trim((string)$_SESSION['BARBER_LOJA']['db_name']));
} elseif (!empty($_SESSION['LOJA']['db_name'])) {
  $bb_db_popup_agendamento = strtolower(trim((string)$_SESSION['LOJA']['db_name']));
}
$bb_db_popup_agendamento = (string)preg_replace('/[^a-z0-9_-]/', '', $bb_db_popup_agendamento);

$bb_host_popup_agendamento = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
$bb_host_popup_agendamento = preg_replace('/:\d+$/', '', $bb_host_popup_agendamento);

$bb_popup_promocao_agendamento_permitido = (
  $bb_slug_popup_agendamento === $bb_popup_promocao_slug_permitido
  || $bb_db_popup_agendamento === $bb_popup_promocao_db_permitido
  || $bb_host_popup_agendamento === $bb_popup_promocao_host_permitido
);

// Corrige fallback do logo sem alterar a lógica do marketplace
$bb_base = isset($bb_base) && $bb_base !== ''
  ? rtrim((string)$bb_base, '/')
  : (((!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') || ((int)($_SERVER['SERVER_PORT'] ?? 0) === 443)) ? 'https' : 'http') . '://' . (string)($_SERVER['HTTP_HOST'] ?? '');

$caminho_logo = (isset($caminho_logo) && (string)$caminho_logo !== '')
  ? '/' . ltrim((string)$caminho_logo, '/')
  : $BASE . 'sistema/img/icone.png';

// Garantir que $sec_paths seja um array
$sec_paths = $sec_paths ?? [];
foreach ($sec_paths as $sec_path) {
  if (file_exists($sec_path)) {
    require_once $sec_path;
    break;
  }
}

// Timezone da loja já vem do bootstrap/conexao.php.
// Não fixar São Paulo aqui, para não quebrar lojas de outro fuso.
$bb_timezone_agendamento = '';
if (!empty($_SESSION['tenant_timezone'])) {
  $bb_timezone_agendamento = (string)$_SESSION['tenant_timezone'];
} elseif (!empty($_SESSION['BARBER_LOJA']['timezone'])) {
  $bb_timezone_agendamento = (string)$_SESSION['BARBER_LOJA']['timezone'];
} else {
  $bb_timezone_agendamento = date_default_timezone_get();
}

if ($bb_timezone_agendamento === '' || !in_array($bb_timezone_agendamento, DateTimeZone::listIdentifiers(), true)) {
  $bb_timezone_agendamento = 'America/Sao_Paulo';
}

date_default_timezone_set($bb_timezone_agendamento);
$data_atual = date('Y-m-d');

unset($_SESSION['usuario_logado_pagina']);
$_SESSION['usuario_logado_pagina'] = true;

// VERIFICA SE EXISTE DADOS DE EDIÇÃO
$dados = $_SESSION['editar_agendamento'] ?? [];

$id          = isset($dados['id']) ? (int)$dados['id'] : 0;
$servico     = $dados['servico'] ?? '';
$funcionario = $dados['funcionario'] ?? '';
$data        = $dados['data'] ?? $data_atual;
$hora        = $dados['hora'] ?? '';

/// EXCLUI AGENDAMENTO ANTIGO QUANDO ENTRAR EM MODO DE EDIÇÃO
if ($id > 0) {
  $stmt = $pdo->prepare("DELETE FROM agendamentos WHERE id = :id");
  $stmt->bindValue(':id', $id, PDO::PARAM_INT);
  $stmt->execute();
}

// Guarda telefone na sessão (sanitizado) na primeira vez
if (!isset($_SESSION['telefone'])) {
  $tel_post = filter_input(INPUT_POST, 'telefone', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
  $_SESSION['telefone'] = $tel_post !== null ? $tel_post : '';
}

$tel_agd = $_SESSION['telefone'] ?? '';
$tem_agendamento = false;

if ($tel_agd !== '') {
  $query_agd = $pdo->prepare("SELECT id FROM clientes WHERE telefone = :tel");
  $query_agd->bindValue(":tel", $tel_agd, PDO::PARAM_STR);
  $query_agd->execute();
  $res_cli = $query_agd->fetch(PDO::FETCH_ASSOC);

  if ($res_cli) {
    $id_cliente = (int)$res_cli['id'];

    $query_agd2 = $pdo->prepare("
      SELECT id
        FROM agendamentos
       WHERE cliente = :id
         AND status = 'Agendado'
    ");
    $query_agd2->bindValue(":id", $id_cliente, PDO::PARAM_INT);
    $query_agd2->execute();

    if ($query_agd2->rowCount() > 0) {
      $tem_agendamento = true;
    }
  }
}

$nome_sistema = $nome_sistema ?? '';
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, viewport-fit=cover" />
<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
<meta name="theme-color" content="#0e1720">
<meta name="description" content="<?php echo htmlspecialchars($nome_sistema); ?>: Agendamento online para barbearias, salões de beleza, estéticas e manicures. Transforme seu visual com profissionais qualificados e atendimento personalizado via BarberBot Software." />

<meta name="keywords" content="agendamento online, barbearia, salão de beleza, estética, manicure, corte de cabelo, design de barba, <?php echo htmlspecialchars($nome_sistema); ?>, sistema para barbeiros, gestão de salão">

<meta property="og:title" content="<?php echo htmlspecialchars($nome_sistema); ?> - Agendamento Online">
<meta property="og:description" content="Reserve seu horário de forma rápida e prática no <?php echo htmlspecialchars($nome_sistema); ?>.">
<meta property="og:image" content="<?php echo $bb_base . $caminho_logo; ?>">
<meta property="og:type" content="website">
<meta name="author" content="Jacy Cordeiro" />
<title><?php echo htmlspecialchars($nome_sistema, ENT_QUOTES, 'UTF-8'); ?></title>

<link rel="manifest" href="<?php echo $BASE; ?>_manifest.json">
<link rel="apple-touch-icon" sizes="180x180" href="<?php echo htmlspecialchars($BB_ICON_192, ENT_QUOTES, 'UTF-8'); ?>">
<link rel="icon" type="image/png" href="<?php echo htmlspecialchars($BB_ICON_192, ENT_QUOTES, 'UTF-8'); ?>" sizes="32x32">

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/5.15.4/css/all.min.css">

<!-- FIX: paths absolutos (CSS quebrava no /loja/<slug>/...) -->
<link rel="stylesheet" type="text/css" href="<?php echo $BASE; ?>css/agendamento.css">
<link rel="stylesheet" type="text/css" href="<?php echo $BASE; ?>app/css/style.css">
<link rel="stylesheet" type="text/css" href="<?php echo $BASE; ?>app/css/bootstrap.css">

<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
<link rel="preconnect" href="https://fonts.gstatic.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800&family=Roboto:wght@400;500;700&display=swap" rel="stylesheet">
<link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,900" rel="stylesheet">

<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
  window.BB_POPUP_PROMOCAO_AGENDAMENTO_PERMITIDO = <?php echo $bb_popup_promocao_agendamento_permitido ? 'true' : 'false'; ?>;
  window.BB_POPUP_PROMOCAO_TENANT_SLUG = <?php echo json_encode($bb_slug_popup_agendamento, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  window.BB_POPUP_PROMOCAO_AGENDAMENTO_IMAGEM = <?php echo json_encode($bb_popup_promocao_agendamento_imagem, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  window.BB_POPUP_PROMOCAO_AGENDAMENTO_TITULO = 'Agende seu horário';
</script>

<!-- ===== Ajustes visuais para remover espaço e aplicar dark padrão ===== -->
<style>
  :root {
    --bg-dark: #0e1720;
    --panel-dark: #1b2733;
    --input-dark: #0f2233;
    --input-border: #254055;
  }

  html, body { height: 100%; }

  body {
    margin: 0;
    background: var(--bg-dark);
  }

  #filtro-servicos-container{
    position: fixed !important;
    top: 0;
    left: 0;
    right: 0;
    padding: 10px 16px;
    z-index: 1090;
    background: rgba(14,23,32,0.75);
    backdrop-filter: saturate(130%) blur(4px);
  }

  #filtro-servicos{
    background-color: var(--input-dark) !important;
    border: 1px solid var(--input-border) !important;
    color: #e8f0fe !important;
  }

  #filtro-servicos::placeholder{
    color:#9bb4c9;
  }

  #page-content{
    padding-top: 0px;
  }

  .footer_section{
    background: transparent !important;
    margin-top: 0 !important;
  }

  .container,
  .row {
    margin-top: 0 !important;
  }
</style>

</head>

<body>

<!-- SPLASH -->
<div id="splash" style="
  position:fixed;
  inset:0;
  display:flex;
  align-items:center;
  justify-content:center;
  background:#0e1720;
  z-index:99999;
">
  <div class="wrap" style="text-align:center;">
    <img class="logo" src="<?php echo $BASE; ?>sistema/img/icone.png" alt="Logo" width="412" height="412">
    <div class="title"><?php echo htmlspecialchars($nome_sistema, ENT_QUOTES, 'UTF-8'); ?></div>
    <div class="bar"></div>
    <div class="tip">Carregando…</div>
  </div>
</div>

<!-- Filtro flutuante (fixo por cima) -->
<div id="filtro-servicos-container" class="w-100 d-flex justify-content-center">
  <div class="position-relative" style="width: 320px;">
    <input
      type="text"
      id="filtro-servicos"
      class="form-control shadow"
      placeholder="Buscar serviço..."
      autocomplete="off"
      spellcheck="false"
      style="padding-right: 40px; border-radius: 12px;"
    >
    <i class="fas fa-search text-primary" style="position: absolute; right: 15px; top: 50%; transform: translateY(-50%); pointer-events: none;"></i>
  </div>
</div>

<!-- Wrapper do conteúdo para compensar a barra fixa -->
<div id="page-content">

<div class="footer_section" style="background: transparent;">
  <div class="container">
    <div class="row justify-content-center">
      <div class="col-md-10">
        <div class="card-header text-center mb-4">
          <p class="text-white-50">Siga as etapas abaixo para agendar seu atendimento</p>
        </div>

        <!-- Indicadores de Etapa -->
        <div class="step-indicator">
          <div class="step active" id="step-1">
            1
            <span class="step-title">Serviço</span>
          </div>
          <div class="step-line"></div>
          <div class="step" id="step-2">
            2
            <span class="step-title">Profissional</span>
          </div>
          <div class="step-line"></div>
          <div class="step" id="step-3">
            3
            <span class="step-title">Data e Hora</span>
          </div>
          <div class="step-line"></div>
          <div class="step" id="step-4">
            4
            <span class="step-title">Seus Dados</span>
          </div>
        </div>

        <form class="form_agendamento" id="form-agenda" method="post">
          <div class="footer_form footer-col">

            <!-- Etapa 1: Escolha do Serviço -->
            <div class="form-step active" id="step-1-content">
              <div class="step-content">
                <h4 class="text-white mb-4 text-center">Escolha o Serviço Desejado</h4>

                <!-- Cards de serviços -->
                <div class="service-cards" id="service-cards-container">
                  <div class="text-center w-100 p-5">
                    <div class="spinner-border text-info" role="status"></div>
                    <p class="mt-3 text-white-50">Carregando serviços disponíveis...</p>
                  </div>
                </div>

                <!-- Campo oculto para armazenar o serviço selecionado -->
                <select id="servico" name="servico" style="display: none;" required>
                  <option value="">Selecione um Serviço</option>
                  <?php
                  $query = $pdo->query("SELECT * FROM servicos WHERE ativo = 'Sim' ORDER BY nome ASC");
                  $res = $query->fetchAll(PDO::FETCH_ASSOC);
                  $total_reg = @count($res);
                  if ($total_reg > 0) {
                    for ($i=0; $i < $total_reg; $i++) {
                      foreach ($res[$i] as $key => $value) {}
                      $valor = $res[$i]['valor'];
                      $valorF = number_format($valor, 2, ',', '.');
                      echo '<option value="'.$res[$i]['id'].'" data-foto="'.$res[$i]['foto'].'" data-tempo="'.$res[$i]['tempo'].'">'.$res[$i]['nome'].' - R$ '.$valorF.'</option>';
                    }
                  }
                  ?>
                </select>
              </div>

              <div class="step-buttons">
                <div></div>
                <button type="button" class="botao-azul next-step" data-step="1">
                  Próximo <i class="fas fa-arrow-right ml-2"></i>
                </button>
              </div>
            </div>

            <!-- Etapa 2: Escolha do Profissional -->
            <div class="form-step" id="step-2-content">
              <div class="step-content">
                <h4 class="text-white mb-4 text-center">Escolha o Profissional</h4>

                <div class="professional-cards" id="professional-cards-container">
                  <div class="text-center w-100 p-5">
                    <div class="spinner-border text-info" role="status"></div>
                    <p class="mt-3 text-white-50">Selecione um serviço primeiro...</p>
                  </div>
                </div>

                <input type="hidden" id="funcionario" name="funcionario" required>
              </div>

              <div class="step-buttons">
                <button type="button" class="botao-azul prev-step" data-step="2">
                  <i class="fas fa-arrow-left mr-2"></i> Anterior
                </button>
                <button type="button" class="botao-azul next-step" data-step="2">
                  Próximo <i class="fas fa-arrow-right ml-2"></i>
                </button>
              </div>
            </div>

            <!-- Etapa 3: Escolha da Data e Hora -->
            <div class="form-step" id="step-3-content">
              <div class="step-content">
                <h4 class="text-white mb-4 text-center">Escolha a Data e Horário</h4>

                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="data" class="text-white-50 mb-2">Data do Agendamento</label>
                      <div class="input-group">
                        <div class="input-group-prepend">
                          <span class="input-group-text bg-transparent border-0">
                            <i class="fas fa-calendar text-info"></i>
                          </span>
                        </div>
                        <input onchange="mudarFuncionario()" class="inputs_agenda" type="date" name="data" id="data" value="<?php echo $data_atual ?>" required />
                      </div>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="form-group">
                      <label class="text-white-50 mb-2">Resumo da Reserva</label>
                      <div class="p-3 rounded" style="background: rgba(255,255,255,0.05);">
                        <div class="d-flex align-items-center mb-2">
                          <i class="fas fa-cut text-info mr-2"></i>
                          <span class="text-white" id="resumo-servico">Nenhum serviço selecionado</span>
                        </div>
                        <div class="d-flex align-items-center mb-2">
                          <i class="fas fa-user text-info mr-2"></i>
                          <span class="text-white" id="resumo-profissional">Nenhum profissional selecionado</span>
                        </div>
                        <div class="d-flex align-items-center">
                          <i class="fas fa-clock text-info mr-2"></i>
                          <span class="text-white" id="resumo-horario">Nenhum horário selecionado</span>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="form-group">
                  <label class="text-white-50 mb-2">Horários Disponíveis</label>
                  <div id="listar-horarios" class="p-2 rounded" style="background: rgba(0,0,0,0.2); min-height: 100px;">
                    <div class="text-center p-4 text-white-50">
                      <i class="fas fa-clock fa-2x mb-3"></i>
                      <p>Os horários disponíveis serão exibidos aqui</p>
                    </div>
                  </div>
                </div>
              </div>

              <div class="step-buttons">
                <button type="button" class="botao-azul prev-step" data-step="3">
                  <i class="fas fa-arrow-left mr-2"></i> Anterior
                </button>
                <button type="button" class="botao-azul next-step" data-step="3" id="btn-proximo-horario">
                  Próximo <i class="fas fa-arrow-right ml-2"></i>
                </button>
              </div>
            </div>

            <!-- Etapa 4: Dados Pessoais -->
            <div class="form-step" id="step-4-content">
              <div class="step-content">
                <h4 class="text-white mb-4 text-center">Seus Dados</h4>

                <div class="row">
                  <div class="col-md-6">
                    <div class="form-group">
                      <label for="telefone" class="text-white-50 mb-2">Telefone com DDD</label>
                      <div class="input-group">
                        <div class="input-group-prepend">
                          <span class="input-group-text bg-transparent border-0">
                            <i class="fas fa-phone text-info"></i>
                          </span>
                        </div>
                        <input onkeyup="buscarNome()" class="inputs_agenda" type="text" name="telefone" id="telefone" placeholder="Seu telefone" required />
                      </div>
                    </div>

                    <div class="form-group">
                      <label for="nome" class="text-white-50 mb-2">Nome Completo</label>
                      <div class="input-group">
                        <div class="input-group-prepend">
                          <span class="input-group-text bg-transparent border-0">
                            <i class="fas fa-user text-info"></i>
                          </span>
                        </div>
                        <input onclick="buscarNome()" class="inputs_agenda" type="text" name="nome" id="nome" placeholder="Seu nome" required />
                      </div>
                    </div>

                    <div class="form-group">
                      <label for="obs" class="text-white-50 mb-2">Observações (opcional)</label>
                      <div class="input-group">
                        <div class="input-group-prepend">
                          <span class="input-group-text bg-transparent border-0">
                            <i class="fas fa-comment text-info"></i>
                          </span>
                        </div>
                        <input maxlength="100" type="text" class="inputs_agenda" name="obs" id="obs" placeholder="Observações caso exista alguma.">
                      </div>
                    </div>
                  </div>

                  <div class="col-md-6">
                    <div class="p-4 rounded" style="background: rgba(255,255,255,0.05);">
                      <h5 class="text-white mb-3">Resumo do Agendamento</h5>

                      <div class="d-flex align-items-center mb-3">
                        <div class="mr-3">
                          <div class="bg-info rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="fas fa-cut text-white"></i>
                          </div>
                        </div>
                        <div>
                          <div class="text-white-50">Serviço</div>
                          <div class="text-white" id="final-servico">Nenhum serviço selecionado</div>
                        </div>
                      </div>

                      <div class="d-flex align-items-center mb-3">
                        <div class="mr-3">
                          <div class="bg-info rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="fas fa-user text-white"></i>
                          </div>
                        </div>
                        <div>
                          <div class="text-white-50">Profissional</div>
                          <div class="text-white" id="final-profissional">Nenhum profissional selecionado</div>
                        </div>
                      </div>

                      <div class="d-flex align-items-center mb-3">
                        <div class="mr-3">
                          <div class="bg-info rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="fas fa-calendar-day text-white"></i>
                          </div>
                        </div>
                        <div>
                          <div class="text-white-50">Data</div>
                          <div class="text-white" id="final-data">Nenhuma data selecionada</div>
                        </div>
                      </div>

                      <div class="d-flex align-items-center">
                        <div class="mr-3">
                          <div class="bg-info rounded-circle d-flex align-items-center justify-content-center" style="width: 40px; height: 40px;">
                            <i class="fas fa-clock text-white"></i>
                          </div>
                        </div>
                        <div>
                          <div class="text-white-50">Horário</div>
                          <div class="text-white" id="final-horario">Nenhum horário selecionado</div>
                        </div>
                      </div>
                    </div>
                  </div>
                </div>
              </div>

              <div class="step-buttons">
                <button type="button" class="botao-azul prev-step" data-step="4">
                  <i class="fas fa-arrow-left mr-2"></i> Anterior
                </button>
                <button onclick="salvar()" class="botao-verde" type="submit" style="width:100%;" id="btn_agendar">
                  <i class="fas fa-calendar-check mr-2"></i>
                  <span id='botao_salvar'>Confirmar Agendamento</span>
                </button>
              </div>
            </div>

            <!-- Mensagem de retorno -->
            <div class="mt-3">
              <div id="mensagem" align="center"></div>
            </div>

            <!-- Campos ocultos -->
            <input type="text" id="data_oculta" style="display: none">
            <?php $ag_editar = @$_SESSION['editar_agendamento']; ?>
            <input type="hidden" name="id_edit" value="<?= @$ag_editar['id'] ?>">
            <input type="hidden" id="hora_rec" name="hora_rec" value="<?= @$ag_editar['hora'] ?>">
            <input type="hidden" id="data_rec" name="data_rec" value="<?= @$ag_editar['data'] ?>">
            <input type="hidden" id="nome_func" name="nome_func">
            <input type="hidden" id="nome_serv" name="nome_serv">
          </div>

          <div class="text-center mt-4">
            <!-- FIX: link absoluto com ?loja pra não quebrar no tenant -->
            <a href="<?php echo $BB_ROUTE_BASE; ?>meus-agendamentos.php<?php echo $QS_LOJA; ?>" class="botao-azul" id='botao_editar' style="display: none;">
              <i class="fas fa-list-alt mr-2"></i>
              Ver Meus Agendamentos
            </a>
          </div>
        </form>

        <div id="listar-cartoes" style="margin-top: 20px;">
          <!-- Cartões serão carregados aqui -->
        </div>

        <div class="text-center mt-4">
          <!-- FIX: repetido no original, mantém, só corrige o href -->
          <a href="<?php echo $BB_ROUTE_BASE; ?>meus-agendamentos.php<?php echo $QS_LOJA; ?>" class="botao-azul" id='botao_editar' style="display: none;">
            <i class="fas fa-list-alt mr-2"></i>
            Ver Meus Agendamentos
          </a>
        </div>

      </div>
    </div>
  </div>
</div>
</div> <!-- /#page-content -->

<!-- Modal de Exclusão -->
<div class="modal fade" id="modalExcluir" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">
          <i class="fas fa-trash-alt text-danger mr-2"></i>
          Excluir Agendamento
        </h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px" id="btn-fechar-excluir">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <form id="form-excluir">
        <div class="modal-body">
          <div class="alert alert-warning">
            <i class="fas fa-exclamation-triangle mr-2"></i>
            <span id="msg-excluir"></span>
          </div>

          <input type="hidden" name="id" id="id_excluir">

          <small><div id="mensagem-excluir" align="center"></div></small>
        </div>

        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal">
            <i class="fas fa-times mr-2"></i>
            Cancelar
          </button>
          <button type="submit" class="btn btn-danger">
            <i class="fas fa-trash-alt mr-2"></i>
            Confirmar Exclusão
          </button>
        </div>
      </form>
    </div>
  </div>
</div>

<button id="botao-instalar" style="display: none; position: fixed; bottom: 20px; left: 50%; transform: translateX(-50%); padding: 12px 20px; background: #007bff; color: #fff; border: none; border-radius: 8px; z-index: 9999;">Instalar App</button>

<!-- FIX: scripts absolutos -->
<script src="<?php echo $BASE; ?>js/jquery-3.4.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.0/dist/umd/popper.min.js" integrity="sha384-Q6E9RHvbIyZFJoft+2mJbHaEWldlvI9IOYy5n3zV9zzTtmI3UksdQRVvoxMfooAo" crossorigin="anonymous"></script>
<script src="<?php echo $BASE; ?>js/bootstrap.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js"></script>
<script src="<?php echo $BASE; ?>js/custom.js"></script>
<script>window.myMap = window.myMap || function(){};</script>
<script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyCh39n5U-4IoWpsVGUHWdqB6puEkhRLdmI&callback=myMap"></script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js"></script>

<script>
let deferredPrompt;

window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  deferredPrompt = e;
  document.getElementById('botao-instalar').style.display = 'block';

  document.getElementById('botao-instalar').addEventListener('click', async () => {
    deferredPrompt.prompt();
    const { outcome } = await deferredPrompt.userChoice;
    console.log('Resultado da instalação:', outcome);
    deferredPrompt = null;
  });
});

window.addEventListener('appinstalled', () => {
  console.log('✅ App instalado!');
});
</script>

<script type="text/javascript">
$(document).ready(function() {

  const agendamentoEditar = <?= json_encode($_SESSION['editar_agendamento'] ?? []) ?>;
  const existeEdicaoAgendamento = Object.keys(agendamentoEditar).length > 0;

  var nome_cl = localStorage.nome_cli_del;
  var tel_cl = localStorage.telefone_cli_del;
  $('#telefone').val(tel_cl);
  $('#nome').val(nome_cl);

  $("#botao_editar").hide();

  carregarServicosCards();

  if (existeEdicaoAgendamento) {
    aplicarEdicaoAgendamento(agendamentoEditar);
  }

  $(document).on('input', '#filtro-servicos', function () {
    var termo = $(this).val().toLowerCase();
    $('.service-card').each(function () {
      var nome = $(this).find('.card-title').text().toLowerCase();
      if (nome.includes(termo)) { $(this).show(); } else { $(this).hide(); }
    });
  });

  function aplicarMascaraTelefoneAgendamento() {
    if (!$.fn.mask) {
      return;
    }

    var comportamento = function(val) {
      var digitos = (val || '').toString().replace(/\D+/g, '');
      return digitos.length > 10 ? '(00) 00000-0000' : '(00) 0000-00009';
    };

    var opcoes = {
      onKeyPress: function(val, e, field, options) {
        $(field).mask(comportamento(val), options);
      }
    };

    if (typeof $('#telefone').unmask === 'function') {
      $('#telefone').unmask();
    }

    $('#telefone').mask(comportamento($('#telefone').val()), opcoes);
  }

  aplicarMascaraTelefoneAgendamento();

  $('.inputs_agenda').focus(function() {
    $(this).css('border-bottom', '2px solid #2ecc71 !important');
  }).blur(function() {
    $(this).css('border-bottom', '2px solid #3498db !important');
  });

  $('.next-step').click(function() {
    var currentStep = parseInt($(this).data('step'));
    var nextStep = currentStep + 1;

    if (currentStep === 1) {
      if ($('#servico').val() === '') {
        if(typeof Swal !== 'undefined') {
          Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Por favor, selecione um serviço para continuar.', confirmButtonColor: '#3498db' });
        } else { alert('Por favor, selecione um serviço para continuar.'); }
        return;
      }
      carregarProfissionaisCards();
    }

    if (currentStep === 2) {
      if ($('#funcionario').val() === '') {
        if(typeof Swal !== 'undefined') {
          Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Por favor, selecione um profissional para continuar.', confirmButtonColor: '#3498db' });
        } else { alert('Por favor, selecione um profissional para continuar.'); }
        return;
      }
      mudarFuncionario();
    }

    if (currentStep === 3) {
      var horaSelecionada = $('#hora_rec').val();
      if (!horaSelecionada || horaSelecionada === '') {
        if(typeof Swal !== 'undefined') {
          Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Por favor, selecione um horário para continuar.', confirmButtonColor: '#3498db' });
        } else { alert('Por favor, selecione um horário para continuar.'); }
        return;
      }
      atualizarResumoFinal();
    }

    $('.form-step').removeClass('active');
    $('#step-' + nextStep + '-content').addClass('active');

    $('.step').removeClass('active');
    $('#step-' + nextStep).addClass('active');

    for (var i = 1; i < nextStep; i++) { $('#step-' + i).addClass('completed'); }

    setTimeout(function() {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }, 100);
  });

  $('.prev-step').click(function() {
    var currentStep = parseInt($(this).data('step'));
    var prevStep = currentStep - 1;

    $('.form-step').removeClass('active');
    $('#step-' + prevStep + '-content').addClass('active');

    $('.step').removeClass('active');
    $('#step-' + prevStep).addClass('active');

    for (var i = 1; i < prevStep; i++) { $('#step-' + i).addClass('completed'); }
  });

  $('#data').change(function() {
    var partes = $(this).val().split('-');
    var selectedDate = new Date(partes[0], partes[1] - 1, partes[2]);
    var today = new Date(); today.setHours(0,0,0,0);

    if (selectedDate < today) {
      if(typeof Swal !== 'undefined') {
        Swal.fire({ icon: 'error', title: 'Data Inválida', text: 'Por favor, selecione uma data igual ou posterior a hoje.', confirmButtonColor: '#3498db' });
      } else { alert('Selecione uma data igual ou maior que hoje!'); }

      var dt = new Date();
      var dia = String(dt.getDate()).padStart(2, '0');
      var mes = String(dt.getMonth() + 1).padStart(2, '0');
      var ano = dt.getFullYear();
      dataAtual = ano + '-' + mes + '-' + dia;
      $('#data').val(dataAtual);
    }

    mudarFuncionario();
    atualizarResumo();
  });
});

// Função para carregar os serviços em cards
function carregarServicosCards() {
  var html = '';
  $('#servico option').each(function() {
    if ($(this).val() !== '') {
      var id = $(this).val();
      var texto = $(this).text();
      var partes = texto.split(' - R$ ');
      var nome = partes[0];
      // evita quebrar o JS caso o nome tenha aspas/apóstrofo
      var nomeJs = (nome || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
      var valor = partes.length > 1 ? partes[1] : '0,00';
      var foto = $(this).data('foto');
      var tempo = $(this).data('tempo');

      html += '<div class="service-card" data-id="' + id + '" onclick="selecionarServico(' + id + ', \''
        + nomeJs + '\', \''
        + valor + '\')">';
      html += '<div class="card-img">';
      html += '<img src="<?php echo $BASE; ?>sistema/painel/img/servicos/' + foto + '" alt="' + nome + '" onerror="this.src=\'<?php echo $BASE; ?>sistema/painel/img/servicos/sem-foto.jpg\'">';
      html += '</div>';
      html += '<div class="card-body">';
      html += '<h5 class="card-title">' + nome + '</h5>';
      html += '<p class="card-text">'+tempo+' Minutos</p>';
      html += '<div class="card-price">R$ ' + valor + '</div>';
      html += '</div>';
      html += '</div>';
    }
  });

  if (html === '') {
    html = '<div class="text-center w-100 p-5"><i class="fas fa-exclamation-circle fa-3x text-warning mb-3"></i><p class="text-white">Nenhum serviço disponível no momento.</p></div>';
  }

  $('#service-cards-container').html(html);
  marcarServicoAtual();
}


function marcarServicoAtual() {
  var servicoAtual = $('#servico').val();

  $('.service-card').removeClass('selected');

  if (!servicoAtual) {
    return;
  }

  $('.service-card').each(function() {
    if (String($(this).data('id')) === String(servicoAtual)) {
      $(this).addClass('selected');
    }
  });

  var textoServico = $('#servico option:selected').text();
  if (textoServico) {
    $('#nome_serv').val(textoServico);
    $('#resumo-servico').text(textoServico);
    $('#final-servico').text(textoServico);
  }
}

function marcarProfissionalAtual() {
  var funcionarioAtual = $('#funcionario').val();

  $('.professional-card').removeClass('selected');

  if (!funcionarioAtual) {
    return;
  }

  $('.professional-card').each(function() {
    if (String($(this).data('id')) === String(funcionarioAtual)) {
      $(this).addClass('selected');
      var nomeProfissional = $(this).find('.card-title-func').text();
      if (nomeProfissional) {
        $('#nome_func').val(nomeProfissional);
        $('#resumo-profissional').text(nomeProfissional);
        $('#final-profissional').text(nomeProfissional);
      }
    }
  });
}

function aplicarEdicaoAgendamento(agendamentoEditar) {
  var servicoEditar = agendamentoEditar && agendamentoEditar.servico ? String(agendamentoEditar.servico) : '';
  var funcionarioEditar = agendamentoEditar && agendamentoEditar.funcionario ? String(agendamentoEditar.funcionario) : '';
  var dataEditar = agendamentoEditar && agendamentoEditar.data ? String(agendamentoEditar.data) : '';
  var horaEditar = agendamentoEditar && agendamentoEditar.hora ? String(agendamentoEditar.hora) : '';

  if (servicoEditar !== '') {
    $('#servico').val(servicoEditar);
    marcarServicoAtual();
  }

  if (funcionarioEditar !== '') {
    $('#funcionario').val(funcionarioEditar);
  }

  if (dataEditar !== '') {
    $('#data').val(dataEditar);
  }

  if (horaEditar !== '') {
    $('#hora_rec').val(horaEditar);
    $('#resumo-horario').text(horaEditar);
    $('#final-horario').text(horaEditar);
  }

  carregarProfissionaisCards(function() {
    if (funcionarioEditar !== '') {
      $('#funcionario').val(funcionarioEditar);
      marcarProfissionalAtual();
    }

    atualizarResumoFinal();
    mudarFuncionario();
  });
}

function selecionarServico(id, nome, valor) {
  $('.service-card').removeClass('selected');
  $('.service-card[data-id="' + id + '"]').addClass('selected');
  $('#servico').val(id);
  $('#nome_serv').val(nome + ' - R$ ' + valor);
  $('#resumo-servico').text(nome + ' - R$ ' + valor);
  $('#final-servico').text(nome + ' - R$ ' + valor);
  mudarServico();
  irParaProximaEtapa(1)
}

function carregarProfissionaisCards(callback) {
  $("#professional-cards-container").html('<div class="text-center w-100 p-5"><div class="spinner-border text-info" role="status"></div><p class="mt-3 text-white-50">Carregando profissionais...</p></div>');
  var serv = $("#servico").val();

  $.ajax({
    url: "<?php echo $BB_ROUTE_BASE; ?>ajax/listar-funcionarios.php<?php echo $QS_LOJA; ?>",
    method: 'POST',
    data: {serv},
    dataType: "text",
    success: function(result) {
      var tempDiv = document.createElement('div');
      tempDiv.innerHTML = result;
      var options = tempDiv.querySelectorAll('option');
      var html = '';

      options.forEach(function(option) {
        if (option.value !== '') {
          var id = option.value;
          var nome = option.textContent;
          // evita quebrar o JS caso o nome tenha aspas/apóstrofo
          var nomeJs = (nome || '').replace(/\\/g, '\\\\').replace(/'/g, "\\'");
          var foto = option.dataset.foto;

          html += '<div class="professional-card" data-id="' + id + '" onclick="selecionarProfissional(' + id + ', \'' + nomeJs + '\')">';
          html += '<div class="card-img">';
          html += '<img src="<?php echo $BASE; ?>sistema/painel/img/perfil/' + foto + '" alt="' + nome + '" onerror="this.src=\'<?php echo $BASE; ?>sistema/painel/img/perfil/sem-foto.jpg\'">';
          html += '</div>';
          html += '<div class="card-body">';
          html += '<h5 class="card-title-func">' + nome + '</h5>';
          html += '<div class="card-specialty">Profissional</div>';
          html += '<div class="card-rating mt-2">';
          for (var j = 0; j < 5; j++) { html += '<i class="fas fa-star"></i>'; }
          html += '</div></div></div>';
        }
      });

      if (html === '') {
        html = '<div class="text-center w-100 p-5"><i class="fas fa-exclamation-circle fa-3x text-warning mb-3"></i><p class="text-white">Nenhum profissional disponível para este serviço.</p></div>';
      }
      $('#professional-cards-container').html(html);
      marcarProfissionalAtual();
      if (typeof callback === 'function') { callback(); }
    },
    error: function() {
      $('#professional-cards-container').html('<div class="alert alert-danger">Erro ao carregar profissionais. Tente novamente.</div>');
    }
  });
}

function selecionarProfissional(id, nome) {
  $('.professional-card').removeClass('selected');
  $('.professional-card[data-id="' + id + '"]').addClass('selected');
  $('#funcionario').val(id);
  $('#nome_func').val(nome);
  $('#resumo-profissional').text(nome);
  $('#final-profissional').text(nome);
  listarFuncionario();
  irParaProximaEtapa(2)
}

function atualizarResumo() {
  var servico = $('#nome_serv').val() || 'Nenhum serviço selecionado';
  var profissional = $('#nome_func').val() || 'Nenhum profissional selecionado';
  var data = $('#data').val();
  var horario = $('#hora_rec').val() || 'Nenhum horário selecionado';

  if (data) {
    var dataObj = new Date(data);
    var dia = String(dataObj.getDate()).padStart(2, '0');
    var mes = String(dataObj.getMonth() + 1).padStart(2, '0');
    var ano = dataObj.getFullYear();
    var dataFormatada = dia + '/' + mes + '/' + ano;
    $('#resumo-data').text(dataFormatada);
    $('#data_rec').val(dataFormatada);
  } else {
    $('#resumo-data').text('Nenhuma data selecionada');
  }

  $('#resumo-servico').text(servico);
  $('#resumo-profissional').text(profissional);
  $('#resumo-horario').text(horario);
}

function atualizarResumoFinal() {
  var servico = $('#nome_serv').val() || 'Nenhum serviço selecionado';
  var profissional = $('#nome_func').val() || 'Nenhum profissional selecionado';
  var data = $('#data').val();
  var horario = $('#hora_rec').val() || 'Nenhum horário selecionado';

  if (data) {
    var partes = data.split('-');
    var ano = parseInt(partes[0], 10);
    var mes = parseInt(partes[1], 10) - 1;
    var dia = parseInt(partes[2], 10);
    var dataObj = new Date(ano, mes, dia);
    var diaStr = String(dataObj.getDate()).padStart(2, '0');
    var mesStr = String(dataObj.getMonth() + 1).padStart(2, '0');
    var anoStr = dataObj.getFullYear();
    var dataFormatada = diaStr + '/' + mesStr + '/' + anoStr;
    $('#final-data').text(dataFormatada);
    $('#data_rec').val(dataFormatada);
  } else {
    $('#final-data').text('Nenhuma data selecionada');
  }

  $('#final-servico').text(servico);
  $('#final-profissional').text(profissional);
  $('#final-horario').text(horario);
}

function mudarFuncionario(){
  var funcionario = $('#funcionario').val();
  var data = $('#data').val();
  var hora = $('#hora_rec').val();

  $("#listar-horarios").html('<div class="text-center p-3"><div class="spinner-border text-info" role="status"></div><p class="mt-2 text-white-50">Carregando horários...</p></div>');
  listarHorarios(funcionario, data, hora);
  listarFuncionario();
}

function listarHorarios(funcionario, data, hora){
  var servico = $("#servico").val();
  $.ajax({
    url: "<?php echo $BB_ROUTE_BASE; ?>ajax/listar-horarios.php<?php echo $QS_LOJA; ?>",
    method: 'POST',
    data: {funcionario, data, hora, servico},
    dataType: "text",
    success:function(result){
      if(result.trim() === '000'){
        if(typeof Swal !== 'undefined') {
          Swal.fire({ icon: 'warning', title: 'Atenção', text: 'Selecione uma data igual ou maior que hoje!', confirmButtonColor: '#3498db' });
        } else { alert('Selecione uma data igual ou maior que hoje!'); }

        var dt = new Date();
        var dia = String(dt.getDate()).padStart(2, '0');
        var mes = String(dt.getMonth() + 1).padStart(2, '0');
        var ano = dt.getFullYear();
        dataAtual = ano + '-' + mes + '-' + dia;
        $('#data').val(dataAtual);
        return;
      } else {
        $("#listar-horarios").html(result);

        $('.btn-horario').off('click').on('click', function() {
          $('.btn-horario').removeClass('btn-info').addClass('btn-outline-info');
          $(this).removeClass('btn-outline-info').addClass('btn-info');
          var horarioSelecionado = $(this).text().trim();
          $('#hora_rec').val(horarioSelecionado);
          $('#resumo-horario').text(horarioSelecionado);
          $('#final-horario').text(horarioSelecionado);
        });

        if(hora && hora !== '') {
          $('.btn-horario').each(function() {
            if($(this).text().trim() === hora) { $(this).click(); }
          });
        }
      }
    },
    error: function() {
      $("#listar-horarios").html('<div class="alert alert-danger">Erro ao carregar horários. Tente novamente.</div>');
    }
  });
}

function buscarNome(){
  var tel = $('#telefone').val();
  if(tel.length < 8) return;

  listarCartoes(tel);

  $.ajax({
    url: "<?php echo $BB_ROUTE_BASE; ?>ajax/listar-nome.php<?php echo $QS_LOJA; ?>",
    method: 'POST',
    data: {tel},
    dataType: "text",
    success:function(result){
      var split = result.split("*");
      console.log(split[3]);

      if(split[2] != "" && split[2] != undefined){
        $("#funcionario").val(parseInt(split[2]));
        $('.professional-card').removeClass('selected');
        $('.professional-card[data-id="' + parseInt(split[2]) + '"]').addClass('selected');
      }

      if(split[5] != "" && split[5] != undefined){
        $("#servico").val(parseInt(split[5]));
        $('.service-card').removeClass('selected');
        $('.service-card[data-id="' + parseInt(split[5]) + '"]').addClass('selected');
        $("#botao_editar").show();
        $("#botao_salvar").text('Novo Agendamento');
      }else{
        $("#botao_editar").hide();
      }

      $("#nome").val(split[0]);
      $("#msg-excluir").text('Deseja Realmente excluir esse agendamento feito para o dia ' + split[7] + ' às ' + split[4]);
      mudarFuncionario();
    }
  });
}

function salvar(){ $('#id').val(''); }

function listarCartoes(tel){
  $.ajax({
    url: "<?php echo $BB_ROUTE_BASE; ?>ajax/listar-cartoes.php<?php echo $QS_LOJA; ?>",
    method: 'POST',
    data: {tel},
    dataType: "text",
    success:function(result){ $("#listar-cartoes").html(result); }
  });
}

function listarFuncionario(){
  var func = $("#funcionario").val();
  $.ajax({
    url: "<?php echo $BB_ROUTE_BASE; ?>ajax/listar-funcionario.php<?php echo $QS_LOJA; ?>",
    method: 'POST',
    data: {func},
    dataType: "text",
    success:function(result){
      $("#nome_func").val(result);
      $('#resumo-profissional').text(result);
      $('#final-profissional').text(result);
    }
  });
}

function mudarServico(){
  listarFuncionarios();
  var serv = $("#servico").val();
  $.ajax({
    url: "<?php echo $BB_ROUTE_BASE; ?>ajax/listar-servico.php<?php echo $QS_LOJA; ?>",
    method: 'POST',
    data: {serv},
    dataType: "text",
    success:function(result){
      $("#nome_serv").val(result);
      $('#resumo-servico').text(result);
      $('#final-servico').text(result);
    }
  });
}

function listarFuncionarios(){
  var serv = $("#servico").val();
  $.ajax({
    url: "<?php echo $BB_ROUTE_BASE; ?>ajax/listar-funcionarios.php<?php echo $QS_LOJA; ?>",
    method: 'POST',
    data: {serv},
    dataType: "text",
    success:function(result){
      var tempDiv = document.createElement('div');
      tempDiv.innerHTML = result;
      var options = tempDiv.querySelectorAll('option');
      $('#funcionario').val('');
    }
  });
}

// Submissão
$("#form-agenda").submit(function () {
  event.preventDefault();

  var nome = $('#nome').val();
  var telefone = $('#telefone').val();
  localStorage.setItem('nome_cli_del', nome);
  localStorage.setItem('telefone_cli_del', telefone);

  $('#btn_agendar').prop('disabled', true);
  $('#mensagem').html('<div class="text-center"><div class="spinner-border text-light" role="status"></div><p class="mt-2 text-white">Processando agendamento...</p></div>');

  var formData = new FormData(this);

  $.ajax({
    url: "<?php echo $BB_ROUTE_BASE; ?>ajax/agendar_temp.php<?php echo $QS_LOJA; ?>",
    type: 'POST',
    data: formData,
    success: function (mensagem) {
      var msg = mensagem.split('*');
      var id_agd = msg[1];

      $('#mensagem').text('');
      $('#mensagem').removeClass();

      if (msg[0].trim() == "Pré Agendado") {
        $('#mensagem').html('<div class="alert alert-success">Agendamento realizado com sucesso!</div>');
        buscarNome();

        if(typeof Swal !== 'undefined') {
          Swal.fire({
            icon: 'success',
            title: 'Agendamento Realizado!',
            text: 'Você será redirecionado para a página de pagamento.',
            timer: 2000,
            timerProgressBar: true,
            showConfirmButton: false
          }).then(() => {
            // mantém relativo (pra cair em /loja/<slug>/pagamento/...)
            window.location="pagamento/"+id_agd+"/100";
          });
        } else {
          $('#mensagem').text(msg[0]);
          window.location="pagamento/"+id_agd+"/100";
        }
      } else {
        $('#mensagem').html('<div class="alert alert-danger">' + msg[0] + '</div>');
      }

      $('#btn_agendar').prop('disabled', false);
    },
    error: function() {
      $('#mensagem').html('<div class="alert alert-danger">Erro ao processar agendamento. Tente novamente.</div>');
      $('#btn_agendar').prop('disabled', false);
    },
    cache: false,
    contentType: false,
    processData: false,
  });
});

function irParaProximaEtapa(etapaAtual) {
  $('.next-step[data-step="' + etapaAtual + '"]').click();
}
</script>

<script>
// Registra o SW (mantém)
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('<?php echo $BASE; ?>sw.js')
  .then(reg => console.log('✅ SW registrado:', reg.scope))
  .catch(err => console.error('❌ Erro ao registrar SW:', err));
}

// Gerencia instalação (mantém)
let deferredPrompt2;
window.addEventListener('beforeinstallprompt', (e) => {
  e.preventDefault();
  deferredPrompt2 = e;
  const btn = document.getElementById('botao-instalar');
  btn.style.display = 'block';

  btn.addEventListener('click', () => {
    btn.style.display = 'none';
    deferredPrompt2.prompt();
    deferredPrompt2.userChoice.then((result) => {
      console.log('Resultado da instalação:', result.outcome);
      deferredPrompt2 = null;
    });
  });
});

window.addEventListener('appinstalled', () => {
  console.log('✅ App instalado!');
});
</script>

<script>
// Mantém segundo register, só corrige o caminho (pra não tentar /loja/<slug>/sw.js)
if ('serviceWorker' in navigator) {
  navigator.serviceWorker.register('<?php echo $BASE; ?>sw.js')
  .then(reg => { console.log('✅ Service Worker registrado:', reg.scope); })
  .catch(err => { console.error('❌ Erro ao registrar Service Worker:', err); });
}
</script>

<script>
  window.onscroll = function () {
    const btn = document.getElementById("btnTopo");
    if (!btn) return;
    if (document.body.scrollTop > 100 || document.documentElement.scrollTop > 100) {
      btn.style.display = "flex";
    } else {
      btn.style.display = "none";
    }
  };

  var bt = document.getElementById("btnTopo");
  if (bt) {
    bt.onclick = function () { window.scrollTo({ top: 0, behavior: 'smooth' }); };
  }
</script>

<script>
  window.addEventListener('load', function () {
    var splash = document.getElementById('splash');
    var app    = document.getElementById('app');

    if (splash) { splash.style.display = 'none'; }
    if (app) { app.style.display = 'block'; }
  });
</script>

<script>
(function() {
  var popupPermitido = (window.BB_POPUP_PROMOCAO_AGENDAMENTO_PERMITIDO === true);
  var popupPromocionalSolicitado = false;

  function textoSeguro(elemento) {
    return elemento && elemento.textContent ? elemento.textContent.toLowerCase().trim() : '';
  }

  function atributoSeguro(elemento, atributo) {
    if (!elemento || typeof elemento.getAttribute !== 'function') {
      return '';
    }

    var valor = elemento.getAttribute(atributo);
    return valor ? String(valor).toLowerCase().trim() : '';
  }

  function escaparAtributo(valor) {
    return String(valor || '')
      .replace(/&/g, '&amp;')
      .replace(/"/g, '&quot;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;');
  }

  function fecharPopupPromocionalNaoPermitido() {
    if (popupPermitido || typeof Swal === 'undefined' || typeof Swal.isVisible !== 'function' || !Swal.isVisible()) {
      return;
    }

    var titulo = textoSeguro(document.querySelector('.swal2-title'));
    var html = textoSeguro(document.querySelector('.swal2-html-container'));
    var container = textoSeguro(document.querySelector('.swal2-popup'));
    var imagem = atributoSeguro(document.querySelector('.swal2-html-container img, .swal2-popup img'), 'src');

    var conteudo = titulo + ' ' + html + ' ' + container + ' ' + imagem;
    var parecePopupPromocional = (
      conteudo.indexOf('agende seu horário') !== -1
      || conteudo.indexOf('promoção') !== -1
      || conteudo.indexOf('promocao') !== -1
      || conteudo.indexOf('imperdível') !== -1
      || conteudo.indexOf('imperdivel') !== -1
      || conteudo.indexOf('images/divulgacao.jpg') !== -1
      || conteudo.indexOf('/images/divulgacao.jpg') !== -1
    );

    if (parecePopupPromocional && typeof Swal.close === 'function') {
      Swal.close();
    }
  }

  function abrirPopupPromocionalPermitido() {
    if (!popupPermitido || popupPromocionalSolicitado || typeof Swal === 'undefined') {
      return;
    }

    if (typeof Swal.isVisible === 'function' && Swal.isVisible()) {
      return;
    }

    popupPromocionalSolicitado = true;
    window.BB_POPUP_PROMOCAO_AGENDAMENTO_EXIBIDO = true;

    var imagemPromocao = window.BB_POPUP_PROMOCAO_AGENDAMENTO_IMAGEM || '/images/divulgacao.jpg';
    var tituloPromocao = window.BB_POPUP_PROMOCAO_AGENDAMENTO_TITULO || 'Agende seu horário';

    Swal.fire({
      title: tituloPromocao,
      html: '<div style="text-align:center;">'
        + '<img src="' + escaparAtributo(imagemPromocao) + '" alt="Divulgação" '
        + 'style="display:block;max-width:100%;height:auto;margin:0 auto;border:0;">'
        + '</div>',
      showCancelButton: true,
      confirmButtonText: 'Agendar',
      cancelButtonText: 'Fechar',
      confirmButtonColor: '#2f76ed',
      cancelButtonColor: '#eeeeee',
      reverseButtons: false,
      width: 420,
      padding: '16px',
      allowOutsideClick: true,
      allowEscapeKey: true
    });
  }

  document.addEventListener('DOMContentLoaded', function() {
    if (popupPermitido) {
      setTimeout(abrirPopupPromocionalPermitido, 250);
      return;
    }

    // Fecha apenas o pop-up promocional inicial caso algum script global tente abri-lo.
    // Não interfere nos alertas normais do formulário depois do carregamento.
    setTimeout(fecharPopupPromocionalNaoPermitido, 100);
    setTimeout(fecharPopupPromocionalNaoPermitido, 350);
    setTimeout(fecharPopupPromocionalNaoPermitido, 900);
    setTimeout(fecharPopupPromocionalNaoPermitido, 1500);
  });

  window.addEventListener('load', function() {
    if (popupPermitido) {
      setTimeout(abrirPopupPromocionalPermitido, 350);
      return;
    }

    setTimeout(fecharPopupPromocionalNaoPermitido, 300);
  });
})();
</script>

<script src="<?php echo $BASE; ?>app/js/custom.js"></script>
</body>
</html>