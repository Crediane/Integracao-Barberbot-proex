<?php
// /public_html/sistema/conexao_sistema_fix_referer.php
// ===== Sessao isolada por contexto (MASTER x TENANT) =====
// Evita que o login do TENANT "vaze" para /sistema (MASTER) e vice-versa.
// Regras seguras (SEM usar HTTP_REFERER para decidir /sistema):
// - /sistema (sem /loja/ e sem ?loja=) => BB_MASTER
// - /loja/<slug>/... OU ?loja=...      => BB_TENANT
// - /ajax e /api (fora de /sistema) com cookie BB_TENANT => BB_TENANT (chamadas internas do painel)
// - demais                              => BB_SITE

// ===============================
// 1) SESSÃO ISOLADA POR CONTEXTO
// ===============================
if (session_status() !== PHP_SESSION_ACTIVE) {

  $__bb_reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

  if (!function_exists('bb_path_has_loja')) {
    function bb_path_has_loja(string $p): bool {
      return (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $p);
    }
  }
  if (!function_exists('bb_path_has_sistema')) {
    function bb_path_has_sistema(string $p): bool {
      return (strpos($p, '/sistema') === 0);
    }
  }

  $__bb_lojaByReqPath  = bb_path_has_loja($__bb_reqPath);
  $__bb_hasSistemaReq  = bb_path_has_sistema($__bb_reqPath);
  $__bb_hasLojaParam   = (!empty($_GET['loja']) || !empty($_POST['loja']));

  // ✅ Forca MASTER em /sistema quando NAO e loja pelo PATH e NAO tem ?loja= (ignora referer)
  $__bb_forceMaster = ($__bb_hasSistemaReq && !$__bb_lojaByReqPath && !$__bb_hasLojaParam);

  // Ajax/API fora de /sistema podem precisar do TENANT pelo cookie (painel interno)
  $__bb_isAjaxApi = (strpos($__bb_reqPath, '/ajax/') !== false) || (strpos($__bb_reqPath, '/api/') !== false);
  $__bb_hasTenantCookie = !empty($_COOKIE['BB_TENANT']);

  $__bb_hasLoja = $__bb_lojaByReqPath || $__bb_hasLojaParam;
  if (!$__bb_hasLoja && !$__bb_hasSistemaReq && $__bb_isAjaxApi && $__bb_hasTenantCookie) {
    $__bb_hasLoja = true;
  }

  $__bb_desiredSession = $__bb_forceMaster ? 'BB_MASTER' : ($__bb_hasLoja ? 'BB_TENANT' : 'BB_SITE');

  // Reabre com o cookie correto do "namespace" (BB_MASTER/BB_TENANT/BB_SITE)
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

require_once __DIR__ . '/timezone_tenant.php';


// ✅ EVITA VAZAMENTO DE TENANT PARA O SITE/MASTER
// Se a requisição NÃO está no contexto TENANT (sem /loja/ e sem ?loja=),
// então NÃO pode manter DB do tenant na sessão atual.
if (!isset($__bb_hasLoja)) { $__bb_hasLoja = false; }
if (!$__bb_hasLoja) {
  unset(
    $_SESSION['tenant_id'],
    $_SESSION['tenant_slug'],
    $_SESSION['tenant_db'],
    $_SESSION['tenant_host'],
    $_SESSION['tenant_user'],
    $_SESSION['tenant_pass']
  );
}

/*
  ==========================================================
  A PARTIR DAQUI: MANTÉM SEU PADRÃO ORIGINAL
  ==========================================================
*/

$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$reqPath    = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// ==========================================================
// MARKETPLACE / TENANT CONFIG
// ==========================================================
$cfgFile = __DIR__ . '/../config/marketplace.php';
$cfg = [];
if (is_file($cfgFile)) {
  $cfg = @include $cfgFile;
}
$cfgMaster = $cfg['master'] ?? [];
$cfgTenant = $cfg['tenant'] ?? [];
$cfgSec    = $cfg['security'] ?? [];

$baseScript = basename($scriptName);

// ✅ Loja por URL (PATH ou query) manda sempre. Sessão é apenas fallback em chamadas internas.
$isLojaContext = (strpos($reqPath, '/loja/') === 0) || !empty($_GET['loja']) || !empty($_POST['loja']);

// Marketplace só quando NÃO é loja por URL.
$isMarketplaceContext = (!$isLojaContext) && (
  strpos($reqPath, '/marketplace/') !== false ||
  strpos($scriptName, '/marketplace/') !== false ||
  in_array($baseScript, ['criar-barbearia.php','provisionar_tenant.php','finalizar_tenant.php','marketplace.php','home.php'], true)
);
/**
 * ==========================================================
 * CONTEXTO SISTEMA (DONO) -> SEMPRE MASTER
 * Regra: se está em /sistema (e NÃO em /loja/...), nunca deve usar o DB do tenant.
 */
$isSystemContext = (strpos($reqPath, '/sistema') === 0) && (strpos($reqPath, '/loja/') !== 0) && ($__bb_forceMaster);
/**
 * ==========================================================
 * AUTH CONTEXT: autenticar.php SEMPRE no jctecbr_master
 * ==========================================================
 */
$isAuthContext = (strpos($reqPath, '/sistema/autenticar.php') !== false || strpos($scriptName, '/sistema/autenticar.php') !== false);

// ==========================
// HELPERS
// ==========================
if (!function_exists('bb_clean_slug')) {
  function bb_clean_slug(string $v): string {
    $v = strtolower(trim($v));
    $v = preg_replace('/[^a-z0-9_-]/', '', $v);
    return trim((string)$v, '-');
  }
}

if (!function_exists('bb_pdo')) {
  function bb_pdo(string $host, string $db, string $user, string $pass): PDO {
    return new PDO(
      "mysql:host={$host};dbname={$db};charset=utf8mb4",
      $user,
      $pass,
      [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
      ]
    );
  }
}

if (!function_exists('bb_decrypt_str')) {
  // pode ser sobrescrito/definido por tenant_crypto.php
  @include __DIR__ . '/tenant_crypto.php';
}

if (!function_exists('bb_try_decrypt')) {
  function bb_try_decrypt(string $value, string $secret): string {
    if ($value === '') return '';
    if ($secret === '') return $value;
    if (!function_exists('bb_decrypt_str')) return $value;
    $dec = bb_decrypt_str($value, $secret);
    return ($dec !== '') ? $dec : $value;
  }
}

// Se está no /sistema (dono), remove qualquer “grude” de tenant
if ($isSystemContext) {
  unset(
    $_SESSION['tenant_id'],
    $_SESSION['tenant_slug'],
    $_SESSION['tenant_db'],
    $_SESSION['tenant_host'],
    $_SESSION['tenant_user'],
    $_SESSION['tenant_pass']
  );
}

// Detecta slug
$slug = null;

/**
 * ==========================================================
 * Detecta slug apenas quando NÃO é marketplace e NÃO é /sistema
 * ==========================================================
 */
if (!$isMarketplaceContext && !$isSystemContext) {

  // GET ?loja=
  if (isset($_GET['loja'])) {
    $slug = bb_clean_slug($_GET['loja']);
  }

  // /loja/slug
  if (!$slug) {
    $seg = array_values(array_filter(explode('/', trim($reqPath, '/'))));
    if (isset($seg[0]) && $seg[0] === 'loja' && !empty($seg[1])) {
      $slug = bb_clean_slug($seg[1]);
    }
  }

  // subdomínio (opcional)
  if (!$slug) {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $host = preg_replace('/:\d+$/', '', $host);
    $ignore = ['www','jc','jc.tec.br','localhost'];
    if ($host && strpos($host, '.') !== false) {
      $sub = explode('.', $host)[0] ?? '';
      $sub = bb_clean_slug((string)$sub);
      if ($sub && !in_array($sub, $ignore, true)) {
        $slug = $sub;
      }
    }
  }

  // referer (ajuda em chamadas internas sem ?loja=, sem afetar /sistema)
  if (!$slug && !empty($_SERVER['HTTP_REFERER'])) {
    $ref = (string)$_SERVER['HTTP_REFERER'];
    $refPath = parse_url($ref, PHP_URL_PATH) ?: '';
    $refQs   = parse_url($ref, PHP_URL_QUERY) ?: '';

    if ($refQs !== '') {
      parse_str($refQs, $refParams);
      if (!empty($refParams['loja'])) {
        $slug = bb_clean_slug((string)$refParams['loja']);
      }
    }

    if (!$slug && preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $refPath, $m)) {
      $slug = bb_clean_slug((string)$m[1]);
    }
  }

  // sessão (apenas fallback quando está em contexto TENANT)
  if (!$slug && $__bb_hasLoja && session_name() === 'BB_TENANT' && !empty($_SESSION['tenant_slug'])) {
    $slug = bb_clean_slug((string)$_SESSION['tenant_slug']);
  }
}

// Se tem slug e sessão não bate, busca no MASTER (market db)
if ($slug && (empty($_SESSION['tenant_db']) || (string)($_SESSION['tenant_slug'] ?? '') !== $slug)) {

  if (!empty($cfgMaster['host']) && !empty($cfgMaster['db']) && !empty($cfgMaster['user'])) {

    try {
      $pdoMaster = bb_pdo(
        (string)($cfgMaster['host'] ?? 'localhost'),
        (string)($cfgMaster['db'] ?? ''),
        (string)($cfgMaster['user'] ?? ''),
        (string)($cfgMaster['pass'] ?? '')
      );

      $table = preg_replace('/[^a-z0-9_]/i', '', (string)($cfgMaster['table'] ?? 'tenants'));
      if ($table === '') $table = 'tenants';

      $st = $pdoMaster->prepare("SELECT * FROM `{$table}` WHERE slug = :s AND ativo = 1 LIMIT 1");
      $st->execute([':s' => $slug]);
      $tenant = $st->fetch(PDO::FETCH_ASSOC);

      if ($tenant && !empty($tenant['db_name']) && !empty($tenant['db_user'])) {

        $secret = (string)($cfgSec['tenant_secret'] ?? '');
        $dbPass = (string)($tenant['db_pass'] ?? '');
        $dbPass = bb_try_decrypt($dbPass, $secret);

        $_SESSION['tenant_id']   = (int)($tenant['id'] ?? 0);
        $_SESSION['tenant_slug'] = (string)($tenant['slug'] ?? $slug);
        $_SESSION['tenant_db']   = (string)($tenant['db_name'] ?? '');
        $_SESSION['tenant_host'] = (string)($tenant['db_host'] ?? 'localhost');
        $_SESSION['tenant_user'] = (string)($tenant['db_user'] ?? '');
        $_SESSION['tenant_pass'] = (string)($dbPass ?? '');

      } else {
        unset(
          $_SESSION['tenant_id'],
          $_SESSION['tenant_slug'],
          $_SESSION['tenant_db'],
          $_SESSION['tenant_host'],
          $_SESSION['tenant_user'],
          $_SESSION['tenant_pass']
        );
      }

    } catch (Throwable $e) {
      // não quebra o sistema
    }
  }
}

// ==========================
// BANCO: MASTER X TENANT
// ==========================
if ($isSystemContext || $isAuthContext) {
  // ✅ MASTER (dono do sistema) sempre usa as credenciais do MASTER
  $servidor = $cfgMaster['host'] ?? 'localhost';
  $banco    = $cfgMaster['db']   ?? null;
  $usuario  = $cfgMaster['user'] ?? null;
  $senha    = $cfgMaster['pass'] ?? null;

} elseif (!empty($_SESSION['tenant_db'])) {
  // ✅ TENANT (salão) pela sessão
  $servidor = $_SESSION['tenant_host'] ?? ($cfgTenant['host'] ?? 'localhost');
  $banco    = $_SESSION['tenant_db']   ?? ($cfgTenant['default_db'] ?? null);
  $usuario  = $_SESSION['tenant_user'] ?? ($cfgTenant['user'] ?? null);
  $senha    = $_SESSION['tenant_pass'] ?? ($cfgTenant['pass'] ?? null);

} elseif ($isMarketplaceContext) {
  // MARKETPLACE usa o banco padrão do sistema (MASTER/TEMPLATE, conforme config)
  $servidor = $cfgTenant['host'] ?? 'localhost';
  $banco    = $cfgTenant['default_db'] ?? null;
  $usuario  = $cfgTenant['user'] ?? null;
  $senha    = $cfgTenant['pass'] ?? null;

} else {
  // SITE default
  $servidor = $cfgTenant['host'] ?? 'localhost';
  $banco    = $cfgTenant['default_db'] ?? null;
  $usuario  = $cfgTenant['user'] ?? null;
  $senha    = $cfgTenant['pass'] ?? null;
}

// ==========================
// CONEXÃO PDO (PADRÃO DO SISTEMA)
// ==========================
try {
  $pdo = new PDO(
    "mysql:host={$servidor};dbname={$banco};charset=utf8mb4",
    $usuario,
    $senha,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]
  );
} catch (Exception $e) {
  echo 'Erro ao conectar no banco de dados!';
  exit;
}

// ==========================
// CONFIG PADRÃO (MANTÉM O SEU FLUXO)
// ==========================
$query = $pdo->query("SELECT * FROM config");
$res = $query->fetchAll(PDO::FETCH_ASSOC);

if (@count($res) > 0) {
  $nome_sistema = $res[0]['nome'] ?? '';
  $email_sistema = $res[0]['email'] ?? '';
  $telefone_sistema = $res[0]['telefone_whatsapp'] ?? '';
  $telefone_fixo_sistema = $res[0]['telefone_fixo'] ?? '';
  $endereco_sistema = $res[0]['endereco'] ?? '';
  $tipo_relatorio = $res[0]['tipo_relatorio'] ?? 'PDF';
  $instagram_sistema = $res[0]['instagram'] ?? '';
  $mapa_sistema = $res[0]['mapa'] ?? '';
  $texto_rodape = $res[0]['texto_rodape'] ?? '';
  $texto_sobre = $res[0]['texto_sobre'] ?? '';
  $texto_cartao = $res[0]['texto_cartao'] ?? '';
  $cartoes_troca = $res[0]['cartoes_troca'] ?? '';
  $texto_agendamento = $res[0]['texto_agendamento'] ?? '';
  $msg_api = $res[0]['msg_api'] ?? '';
  $api = $res[0]['api'] ?? '';
  $token = $res[0]['token'] ?? '';
  $instancia = $res[0]['instancia'] ?? '';
  $horas_confirmacao = $res[0]['horas_confirmacao'] ?? '';
  $cnpj_sistema = $res[0]['cnpj'] ?? '';
  $cidade_sistema = $res[0]['cidade'] ?? '';
  $manter_agendamento_dias = $res[0]['manter_agendamento_dias'] ?? '';
  $itens_paginacao = $res[0]['itens_paginacao'] ?? '';
  $dias_antecipados = $res[0]['dias_antecipados'] ?? '';
  $valor_agendamento = $res[0]['valor_agendamento'] ?? '';
  $pgto_agendamento = $res[0]['pgto_agendamento'] ?? '';
  $texto_rodape_site = $res[0]['texto_rodape_site'] ?? '';
  $texto_rodape_site2 = $res[0]['texto_rodape_site2'] ?? '';
  $texto_rodape_site3 = $res[0]['texto_rodape_site3'] ?? '';
  $texto_rodape_site4 = $res[0]['texto_rodape_site4'] ?? '';
  $texto_rodape_site5 = $res[0]['texto_rodape_site5'] ?? '';
  $texto_rodape_site6 = $res[0]['texto_rodape_site6'] ?? '';
  $texto_rodape_site7 = $res[0]['texto_rodape_site7'] ?? '';
  $texto_rodape_site8 = $res[0]['texto_rodape_site8'] ?? '';
  $texto_rodape_site9 = $res[0]['texto_rodape_site9'] ?? '';
  $texto_rodape_site10 = $res[0]['texto_rodape_site10'] ?? '';
  $texto_rodape_site11 = $res[0]['texto_rodape_site11'] ?? '';
  $texto_rodape_site12 = $res[0]['texto_rodape_site12'] ?? '';
  $texto_rodape_site13 = $res[0]['texto_rodape_site13'] ?? '';
  $texto_rodape_site14 = $res[0]['texto_rodape_site14'] ?? '';
  $texto_rodape_site15 = $res[0]['texto_rodape_site15'] ?? '';
  $logo = $res[0]['logo'] ?? '';
  $logo_rel = $res[0]['logo_rel'] ?? '';
  $icone = $res[0]['icone'] ?? '';
  $icone_site = $res[0]['icone_site'] ?? '';
  $favicon_192 = $res[0]['favicon_192'] ?? '';
  $favicon_512 = $res[0]['favicon_512'] ?? '';
  $img_sobre = $res[0]['img_sobre'] ?? '';
  $img_banner = $res[0]['img_banner'] ?? '';
  $openai_key = $res[0]['openai_key'] ?? '';
  $openai_prompt = $res[0]['openai_prompt'] ?? '';
  $bot_ativo = $res[0]['bot_ativo'] ?? 'Nao';
  $nome_bot = $res[0]['nome_bot'] ?? '';
  $boas_vindas_bot = $res[0]['boas_vindas_bot'] ?? '';
  $asaas = $res[0]['asaas'] ?? '';
  $public_key_mp = $res[0]['public_key_mp'] ?? '';
  $access_token_mp = $res[0]['access_token_mp'] ?? '';
  $ativo_sistema = $res[0]['ativo'] ?? 'Sim';

  $abertura = $res[0]['abertura'] ?? '';
  $fechamento = $res[0]['fechamento'] ?? '';
  $abertura_sab = $res[0]['abertura_sab'] ?? '';
  $fechamento_sab = $res[0]['fechamento_sab'] ?? '';
  $abertura_dom = $res[0]['abertura_dom'] ?? '';
  $fechamento_dom = $res[0]['fechamento_dom'] ?? '';
}

if ($ativo_sistema !== 'Sim' && $ativo_sistema !== '') {
  if (strpos($_SERVER['PHP_SELF'], 'bloqueio.php') === false) {
    echo "<script>window.location='bloqueio.php'</script>";
    exit;
  }
}
