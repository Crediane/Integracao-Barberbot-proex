<?php
// ======================================================================
// ARQUIVO: sistema/painel/verificar.php
// OBJETIVO (SEM MUDAR A LÓGICA DO SISTEMA):
// 1) Isolar sessão MASTER x TENANT
// 2) Isolar sessão TENANT por SLUG: BB_TENANT_<hash> (evita misturar lojas)
// 3) Manter compatibilidade com legado (BB_TENANT) quando não houver slug
// 4) Blindar troca de SLUG (auth_tenant_slug) para impedir vazamento de login
// ======================================================================

if (!function_exists('bb_sess_clean_slug')) {
  function bb_sess_clean_slug($v) {
    $v = strtolower(trim((string)$v));
    return preg_replace('/[^a-z0-9_-]/', '', $v);
  }
}

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
// ===============================
// 1) Detecta SLUG o mais cedo possível
//    Prioridade: GET/POST -> /loja/<slug>/ -> REFERER (fallback)
// ===============================
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

// ===============================
// 2) Lista cookies TENANT existentes (BB_TENANT e BB_TENANT_*)
//    Isso permite conviver com múltiplas lojas no mesmo navegador.
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
// 3) Define contexto (MASTER x TENANT)
//    Regra segura: /sistema (raiz) SEM /loja/ e SEM ?loja= => MASTER
// ===============================
$__bb_lojaByPath = (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_routePath)
                || (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_refRoutePath);
$__bb_hasLojaParam = (!empty($_GET['loja']) || !empty($_POST['loja']));
$__bb_isSistemaRoot = (strpos($__bb_routePath, '/sistema') === 0); // raiz do sistema (admin)

$__bb_forceMaster = ($__bb_isSistemaRoot && !$__bb_lojaByPath && !$__bb_hasLojaParam);

// Contexto ativo (quando MASTER e TENANT coexistem no mesmo navegador)
$__bb_ctx = '';
if (!empty($_GET['ctx'])) {
  $__bb_ctx = strtolower(trim((string)$_GET['ctx']));
} elseif (!empty($_COOKIE['BB_CTX'])) {
  $__bb_ctx = strtolower(trim((string)$_COOKIE['BB_CTX']));
}
if (!in_array($__bb_ctx, ['tenant','master'], true)) $__bb_ctx = '';

// Ajuste fino do marketplace:
// - se forçou master, mas o navegador só tem TENANT (e não tem MASTER), então não força master.
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

// ===============================
// 4) Nome da sessão TENANT por SLUG (BB_TENANT_<hash>)
// ===============================
$__bb_tenantSessionName = 'BB_TENANT';
if ($__bb_reqSlug !== '') {
  $__bb_tenantSessionName = 'BB_TENANT_' . strtoupper(substr(sha1($__bb_reqSlug), 0, 12));
}

// ===============================
// 5) Sessão desejada
//    - MASTER: BB_MASTER
//    - TENANT: por SLUG (se tiver) OU único cookie TENANT (se houver) OU legado BB_TENANT
// ===============================
$__bb_desiredSession = 'BB_SITE';
if ($__bb_forceMaster) {
  $__bb_desiredSession = 'BB_MASTER';
} elseif ($__bb_hasLojaContext) {
  if ($__bb_reqSlug !== '') {
    $__bb_desiredSession = $__bb_tenantSessionName;
  } elseif ($__bb_oneTenantCookie !== '') {
    $__bb_desiredSession = $__bb_oneTenantCookie;
  } else {
    $__bb_desiredSession = 'BB_TENANT'; // legado
  }
}

// ===============================
// 6) Inicia/ajusta a sessão corretamente (usa o cookie do nome escolhido)
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
// 7) Atualiza contexto ativo (BB_CTX)
// ===============================
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

// ===============================
// 8) Blindagem: se o login está travado em um SLUG e tentarem trocar, limpa autenticação
// ===============================
if (strpos(session_name(), 'BB_TENANT') === 0 && $__bb_reqSlug !== '') {
  $authSlug = (string)($_SESSION['auth_tenant_slug'] ?? '');
  if ($authSlug !== '' && $authSlug !== $__bb_reqSlug) {
    foreach (['id','nome','nivel','nivel_usuario','email','aut_token_1010','aut_token_505052022','aut_token_portalapp','master_id'] as $k) {
      if (isset($_SESSION[$k])) unset($_SESSION[$k]);
    }
    unset($_SESSION['auth_tenant_slug']);
  }
}

header('Content-Type: text/html; charset=utf-8');

// ============================
// DETECTA SLUG (TENANT)
// - regra importante: /sistema/* não pode "grudar" no tenant_slug da sessão
// ============================
$path = $__bb_routePath;
$refPath = $__bb_refRoutePath;
$isSystemRoot = (strpos($path, '/sistema/') === 0);

$slug = '';
if (!empty($_GET['loja'])) {
  $slug = strtolower(trim((string)$_GET['loja']));
} else {

  // /loja/<slug>/...
  if ($path && preg_match('~^/loja/([a-z0-9_-]+)/~i', $path, $m)) {
    $slug = strtolower(trim((string)$m[1]));
  } elseif ($refPath && preg_match('~^/loja/([a-z0-9_-]+)/~i', $refPath, $mRef)) {
    $slug = strtolower(trim((string)$mRef[1]));
  } elseif (!$isSystemRoot && !empty($_SESSION['tenant_slug'])) {
    // mantém sessão só fora do /sistema/
    $slug = strtolower(trim((string)$_SESSION['tenant_slug']));
  }
}

// sanitiza
$slug = preg_replace('/[^a-z0-9_-]/', '', (string)$slug);

// se estiver acessando /sistema/* SEM loja -> limpa binding do tenant (evita bug no admin do sistema)
if ($isSystemRoot && $slug === '') {
  unset($_SESSION['tenant_id'], $_SESSION['tenant_slug'], $_SESSION['tenant_db'], $_SESSION['tenant_host'], $_SESSION['tenant_user'], $_SESSION['tenant_pass']);
  unset($_GET['loja']);
}

// mantém slug na sessão e no GET para o conexao.php
if ($slug !== '') {
  $_SESSION['tenant_slug'] = $slug;
  $_GET['loja'] = $slug;
}

// trava o slug autenticado (anti-vazamento entre lojas)
if ($slug !== '') {
  $_SESSION['auth_tenant_slug'] = $slug;
  if (!isset($_SESSION['BARBER_LOJA']) || !is_array($_SESSION['BARBER_LOJA'])) {
    $_SESSION['BARBER_LOJA'] = [];
  }
  $_SESSION['BARBER_LOJA']['slug'] = $slug;
}

// url do login correto
$login = rtrim($__bb_webBase, '/')
  . (($slug !== '') ? ("/loja/" . rawurlencode($slug) . "/sistema/") : "/sistema/");

// mesmas regras do seu sistema (não muda lógica)
if (empty($_SESSION['id'])) {
  if (!headers_sent()) {
    header("Location: " . $login);
  } else {
    echo "<script>window.location='{$login}'</script>";
  }
  exit();
}

if ((string)($_SESSION['aut_token_1010'] ?? '') !== "fdsfdsafda8855555") {
  if (!headers_sent()) {
    header("Location: " . $login);
  } else {
    echo "<script>window.location='{$login}'</script>";
  }
  exit();
}

// ============================
// ✅ CARREGA CONEXÃO + CONFIG
// (evita erro: configuracoes não existe)
// ============================
require_once(__DIR__ . '/../conexao.php');

// ✅ NÃO carrega ../verificar.php aqui.
// Este arquivo já validou a sessão do painel.
// Incluir /sistema/verificar.php neste ponto executa o verificador antigo
// e pode disparar alert('out system') mesmo com a loja correta.
// ✅ abre a modal 1x por sessão depois do login (auto-consumível)
// - 1ª requisição após login: seta bb_show_duvidas_modal
// - 2ª requisição: remove bb_show_duvidas_modal automaticamente
if (!empty($_SESSION['bb_show_duvidas_modal_once'])) {
  unset($_SESSION['bb_show_duvidas_modal']);
  unset($_SESSION['bb_show_duvidas_modal_once']);
} elseif (empty($_SESSION['bb_duvidas_shown'])) {
  $_SESSION['bb_show_duvidas_modal'] = 1;      // sinal para o index.php abrir
  $_SESSION['bb_show_duvidas_modal_once'] = 1; // marca para limpar na próxima requisição
  $_SESSION['bb_duvidas_shown'] = 1;           // trava para não repetir
}

// fallback seguro se a conexão falhar
if (!isset($pdo) || !$pdo) {
  // não quebra o painel inteiro com fatal
  $pdo = null;
}

// checa se tabela existe
function bb_table_exists($pdo, string $table): bool {
  if (!$pdo) return false;
  try {
    $st = $pdo->prepare("SHOW TABLES LIKE :t");
    $st->execute([':t' => $table]);
    return (bool)$st->fetchColumn();
  } catch (Throwable $e) {
    return false;
  }
}

// carrega 1 linha de config
function bb_get_config_row($pdo): array {
  if (!$pdo) return [];

  // prioridade: config (padrão do seu sistema)
  if (bb_table_exists($pdo, 'config')) {
    $q = $pdo->query("SELECT * FROM config LIMIT 1");
    $r = $q->fetch(PDO::FETCH_ASSOC);
    if ($r) return $r;

    // se não tiver linha, cria uma base SEM ASSUMIR COLUNAS (evita bug de modo_teste inexistente)
    try {
      $cols = $pdo->query("SHOW COLUMNS FROM `config`")->fetchAll(PDO::FETCH_ASSOC);

      $insertCols = [];
      $placeholders = [];
      $params = [];

      foreach ($cols as $c) {
        $field = $c['Field'];
        $fieldLower = strtolower($field);
        $null = $c['Null'] ?? 'YES';
        $default = $c['Default'] ?? null;
        $extra = $c['Extra'] ?? '';
        $type = $c['Type'] ?? '';

        // pula auto_increment
        if (stripos($extra, 'auto_increment') !== false) {
          continue;
        }

        // preenche somente colunas obrigatórias sem default
        if ($null === 'NO' && $default === null) {
          $val = '';

          // valores neutros por tipo
          if (preg_match('/int|decimal|float|double/i', $type)) {
            $val = 0;
          } elseif (preg_match('/datetime|timestamp/i', $type)) {
            $val = date('Y-m-d H:i:s');
          } elseif (preg_match('/date/i', $type)) {
            $val = date('Y-m-d');
          }

          // defaults seguros (padrão do sistema)
          if ($fieldLower === 'ativo') $val = 'Sim';
          if ($fieldLower === 'modo_teste') $val = 'Não';
          if ($fieldLower === 'tipo_rel') $val = 'PDF';

          $insertCols[] = "`$field`";
          $ph = ':c' . count($insertCols);
          $placeholders[] = $ph;
          $params[$ph] = $val;
        }
      }

      if (count($insertCols) > 0) {
        $sql = "INSERT INTO `config` (" . implode(',', $insertCols) . ") VALUES (" . implode(',', $placeholders) . ")";
        $st = $pdo->prepare($sql);
        $st->execute($params);
      } else {
        // fallback (se não houver colunas obrigatórias)
        $pdo->exec("INSERT INTO `config` () VALUES ()");
      }
    } catch (Throwable $e) {
      // não trava (apenas mantém retorno vazio)
    }

    $q2 = $pdo->query("SELECT * FROM config LIMIT 1");
    $r2 = $q2->fetch(PDO::FETCH_ASSOC);
    return $r2 ?: [];
  }

  // fallback: configuracoes (sistema antigo)
  if (bb_table_exists($pdo, 'configuracoes')) {
    $q = $pdo->query("SELECT * FROM configuracoes LIMIT 1");
    $r = $q->fetch(PDO::FETCH_ASSOC);
    return $r ?: [];
  }

  return [];
}

$bb_cfg = bb_get_config_row($pdo);

// ============================
// MAPEIA VARIÁVEIS DO PAINEL
// ============================

// empresa / textos
$nome_sistema            = $bb_cfg['nome'] ?? ($bb_cfg['nome_sistema'] ?? '');
$email_sistema           = $bb_cfg['email'] ?? ($bb_cfg['email_sistema'] ?? '');
$whatsapp_sistema        = $bb_cfg['telefone_whatsapp'] ?? ($bb_cfg['whatsapp_sistema'] ?? '');
$telefone_fixo_sistema   = $bb_cfg['telefone_fixo'] ?? ($bb_cfg['telefone_fixo_sistema'] ?? '');
$endereco_sistema        = $bb_cfg['endereco'] ?? ($bb_cfg['endereco_sistema'] ?? '');
$tipo_rel                = $bb_cfg['tipo_rel'] ?? 'PDF';
$instagram_sistema       = $bb_cfg['instagram'] ?? ($bb_cfg['instagram_sistema'] ?? '');
$mapa                    = $bb_cfg['mapa'] ?? '';
$texto_rodape            = $bb_cfg['texto_rodape'] ?? '';
$texto_sobre             = $bb_cfg['texto_sobre'] ?? '';
$texto_fidelidade        = $bb_cfg['texto_fidelidade'] ?? '';
$quantidade_cartoes      = $bb_cfg['quantidade_cartoes'] ?? 0;
$texto_agendamento       = $bb_cfg['texto_agendamento'] ?? '';
$msg_agendamento         = $bb_cfg['msg_agendamento'] ?? 'Sim';

// docs/contrato
$cnpj_sistema            = $bb_cfg['cnpj'] ?? ($bb_cfg['cnpj_sistema'] ?? '');
$cidade_sistema          = $bb_cfg['cidade'] ?? ($bb_cfg['cidade_sistema'] ?? '');

// sistema
$agendamento_dias        = $bb_cfg['agendamento_dias'] ?? 30;
$itens_pag               = $bb_cfg['itens_pag'] ?? 10;
$minutos_aviso           = $bb_cfg['minutos_aviso'] ?? 0;

// api
$api                     = $bb_cfg['api'] ?? 'menuia';
$token                   = $bb_cfg['token'] ?? '';
$instancia               = $bb_cfg['instancia'] ?? '';

// imagens (nomes)
$logo_sistema            = $bb_cfg['logo'] ?? ($bb_cfg['logo_sistema'] ?? 'logo.png');
$icone_sistema           = $bb_cfg['icone'] ?? ($bb_cfg['icone_sistema'] ?? 'favicon.png');
$logo_rel                = $bb_cfg['logo_rel'] ?? 'logo_rel.jpg';

$icone_site              = $bb_cfg['icone_site'] ?? 'favicon.png';
$favicon_192             = $bb_cfg['favicon_192'] ?? 'favicon_192.png';
$favicon_512             = $bb_cfg['favicon_512'] ?? 'favicon_512.png';

$imagem_sobre            = $bb_cfg['imagem_sobre'] ?? '';
$img_banner_index         = $bb_cfg['img_banner_index'] ?? '';
$fundo_login             = $bb_cfg['fundo_login'] ?? 'sem-foto.png';

// modo teste (mantém padrão)
$modo_teste              = $bb_cfg['modo_teste'] ?? ($modo_teste ?? 'Não');
?>
