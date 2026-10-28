<?php
// /public_html/sistema/conexao.php
// ======================================================================
// BOOTSTRAP DE SESSÃO (SEM MUDAR A LÓGICA DO SISTEMA)
// - Isola MASTER x TENANT
// - Isola TENANT por SLUG: BB_TENANT_<hash>
// - Mantém compatibilidade com BB_TENANT (legado) quando não há slug
// ======================================================================
//
// Regras seguras (SEM usar HTTP_REFERER para decidir /sistema):
// - /sistema no host principal/master (sem /loja/ e sem ?loja=) => BB_MASTER
// - /loja/<slug>/... OU ?loja=...                              => BB_TENANT_<hash>
// - domínio próprio cadastrado em tenant_domains                => BB_TENANT_<hash>
// - /ajax e /api (fora de /sistema) com cookie TENANT           => TENANT (chamadas internas do painel)
// - demais                                                      => BB_SITE
//
// Observação importante:
// - Em chamadas internas sem slug explícito (ex: /ajax/*), se existir apenas 1 cookie BB_TENANT_*,
//   ele é usado. Se houver mais de 1, o ideal é a chamada vir com /loja/<slug>/... (padrão do painel).

$__bb_reqPathOriginal = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$__bb_documentRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? '')) ?: '';
$__bb_projectRoot = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
$__bb_webBase = '';
if ($__bb_documentRoot !== '') {
  $__bb_documentRoot = str_replace('\\', '/', rtrim($__bb_documentRoot, '/\\'));
  $__bb_projectRoot = str_replace('\\', '/', rtrim($__bb_projectRoot, '/\\'));
  if (stripos($__bb_projectRoot, $__bb_documentRoot) === 0) {
    $__bb_webBase = '/' . trim(substr($__bb_projectRoot, strlen($__bb_documentRoot)), '/');
    if ($__bb_webBase === '/') $__bb_webBase = '';
  }
}
$__bb_reqPath = $__bb_reqPathOriginal;
if ($__bb_webBase !== '' && stripos($__bb_reqPath, $__bb_webBase) === 0) {
  $__bb_reqPath = substr($__bb_reqPath, strlen($__bb_webBase));
  if ($__bb_reqPath === '') $__bb_reqPath = '/';
}

if (!function_exists('bb_sess_clean_slug')) {
  function bb_sess_clean_slug($v) {
    $v = strtolower(trim((string)$v));
    return preg_replace('/[^a-z0-9_-]/', '', $v);
  }
}

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

if (!function_exists('bb_clean_host')) {
  function bb_clean_host($host) {
    $host = strtolower(trim((string)$host));
    if ($host === '') return '';
    $host = preg_replace('~^https?://~i', '', $host);
    $host = preg_replace('~/.*$~', '', $host);
    $host = preg_replace('~:\d+$~', '', $host);
    return trim($host, ". \t\n\r\0\x0B");
  }
}


if (!function_exists('bb_is_marketplace_main_host')) {
  function bb_is_marketplace_main_host(string $hostLimpo, array $cfg = []): bool {
    $hostLimpo = bb_clean_host($hostLimpo);
    if ($hostLimpo === '') return true;

    $baseUrl = '';
    if (!empty($cfg['base_url'])) {
      $baseUrl = bb_clean_host((string)$cfg['base_url']);
    } elseif (!empty($cfg['app']['base_url'])) {
      $baseUrl = bb_clean_host((string)$cfg['app']['base_url']);
    }

    $hosts = ['jc.tec.br', 'www.jc.tec.br', 'localhost'];
    if ($baseUrl !== '') {
      $hosts[] = $baseUrl;
      $hosts[] = 'www.' . preg_replace('~^www\.~', '', $baseUrl);
    }

    $hosts = array_values(array_unique(array_filter(array_map('bb_clean_host', $hosts))));
    return in_array($hostLimpo, $hosts, true);
  }
}

if (!function_exists('bb_marketplace_subdomain_slug')) {
  function bb_marketplace_subdomain_slug(string $hostLimpo, array $cfg = []): string {
    $hostLimpo = bb_clean_host($hostLimpo);
    if ($hostLimpo === '' || $hostLimpo === 'localhost') return '';

    $bases = ['jc.tec.br'];
    if (!empty($cfg['base_url'])) {
      $bases[] = bb_clean_host((string)$cfg['base_url']);
    } elseif (!empty($cfg['app']['base_url'])) {
      $bases[] = bb_clean_host((string)$cfg['app']['base_url']);
    }

    $bases = array_values(array_unique(array_filter(array_map(function ($base) {
      return preg_replace('~^www\.~i', '', bb_clean_host($base));
    }, $bases))));

    foreach ($bases as $base) {
      $sufixo = '.' . $base;
      if (strlen($hostLimpo) <= strlen($sufixo) || substr($hostLimpo, -strlen($sufixo)) !== $sufixo) {
        continue;
      }

      $subdominio = substr($hostLimpo, 0, -strlen($sufixo));
      if ($subdominio === '' || strpos($subdominio, '.') !== false) {
        continue;
      }

      $slug = bb_sess_clean_slug($subdominio);
      if ($slug === '' || in_array($slug, ['www','app','api','sistema','painel','admin','master'], true)) {
        return '';
      }

      return $slug;
    }

    return '';
  }
}

if (!function_exists('bb_find_tenant_by_host_market')) {
  function bb_find_tenant_by_host_market(string $hostLimpo, array $cfgMaster): ?array {
    if ($hostLimpo === '') return null;
    if (empty($cfgMaster['host']) || empty($cfgMaster['db']) || empty($cfgMaster['user'])) return null;

    try {
      $pdoMarket = new PDO(
        "mysql:host={$cfgMaster['host']};dbname={$cfgMaster['db']};charset=utf8mb4",
        $cfgMaster['user'],
        $cfgMaster['pass'] ?? '',
        [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
      );

      $tenantsTable = $cfgMaster['table'] ?? 'tenants';
      $tenantsTable = preg_replace('/[^a-z0-9_]/i', '', (string)$tenantsTable);
      if ($tenantsTable === '') $tenantsTable = 'tenants';

      $stTd = $pdoMarket->query("SHOW TABLES LIKE 'tenant_domains'");
      $hasTenantDomains = (bool)$stTd->fetchColumn();
      if (!$hasTenantDomains) return null;

      $stCols = $pdoMarket->query("SHOW COLUMNS FROM tenant_domains");
      $tdColsRows = $stCols ? $stCols->fetchAll(PDO::FETCH_ASSOC) : [];
      $tdCols = [];
      foreach ($tdColsRows as $r) {
        if (!empty($r['Field'])) $tdCols[(string)$r['Field']] = true;
      }

      $where = ["LOWER(TRIM(d.host)) = LOWER(TRIM(:host))"];
      if (isset($tdCols['ativo'])) {
        $where[] = "(d.ativo = 1 OR d.ativo IS NULL)";
      }
      if (isset($tdCols['principal'])) {
        $where[] = "(d.principal = 1 OR d.principal IS NULL)";
      }

      $sql = "
        SELECT t.*, d.host AS dominio_personalizado
          FROM tenant_domains d
          INNER JOIN `{$tenantsTable}` t ON t.id = d.tenant_id
         WHERE " . implode(" AND ", $where) . "
         ORDER BY d.id DESC
         LIMIT 1
      ";

      $st = $pdoMarket->prepare($sql);
      $st->execute([':host' => $hostLimpo]);
      $tenant = $st->fetch(PDO::FETCH_ASSOC);

      return $tenant ?: null;
    } catch (Throwable $e) {
      return null;
    }
  }
}

// ===============================
// 1) Detecta slug cedo (GET/POST/URI)
// ===============================
$__bb_slug = '';
if (!empty($_GET['loja'])) {
  $__bb_slug = bb_sess_clean_slug($_GET['loja']);
} elseif (!empty($_POST['loja'])) {
  $__bb_slug = bb_sess_clean_slug($_POST['loja']);
} else {
  if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath, $mSlug)) {
    $__bb_slug = bb_sess_clean_slug($mSlug[1]);
  }
}

// Nome da sessão TENANT por SLUG (cookie diferente por loja)
$__bb_tenantSessionName = 'BB_TENANT';
if ($__bb_slug !== '') {
  $__bb_tenantSessionName = 'BB_TENANT_' . strtoupper(substr(sha1($__bb_slug), 0, 12));
}

// ===============================
// 2) Detecta cookies TENANT (BB_TENANT / BB_TENANT_*)
// ===============================
$__bb_tenantCookies = [];
foreach ($_COOKIE as $__bb_ck => $__bb_cv) {
  if ($__bb_ck === 'BB_TENANT' || strpos($__bb_ck, 'BB_TENANT_') === 0) {
    $__bb_tenantCookies[] = (string)$__bb_ck;
  }
}
$__bb_hasTenantCookie = (count($__bb_tenantCookies) > 0);
$__bb_oneTenantCookie = (count($__bb_tenantCookies) === 1) ? (string)$__bb_tenantCookies[0] : '';

// ===============================
// 3) Define contexto MASTER x TENANT
// ===============================
$__bb_lojaByReqPath  = bb_path_has_loja($__bb_reqPath);
$__bb_hasSistemaReq  = bb_path_has_sistema($__bb_reqPath);
$__bb_hasLojaParam   = (!empty($_GET['loja']) || !empty($_POST['loja']));

// ✅ Força MASTER em /sistema quando NÃO é loja pelo PATH e NÃO tem ?loja= (ignora referer).
// Se o host for domínio próprio cadastrado no MARKET, a etapa 4.2 troca para TENANT.
$__bb_hasMasterCookie = !empty($_COOKIE['BB_MASTER']);

// MASTER ADMIN: /sistema no Link Master deve usar sessão BB_MASTER, mesmo se existir cookie antigo de loja.
$__bb_forceMaster = ($__bb_hasSistemaReq && !$__bb_lojaByReqPath && !$__bb_hasLojaParam);

// Ajax/API fora de /sistema podem precisar do TENANT pelo cookie (painel interno)
$__bb_isAjaxApi = (strpos($__bb_reqPath, '/ajax/') !== false) || (strpos($__bb_reqPath, '/api/') !== false);

$__bb_hasLoja = $__bb_lojaByReqPath || $__bb_hasLojaParam;
if (!$__bb_hasLoja && !$__bb_hasSistemaReq && $__bb_isAjaxApi && $__bb_hasTenantCookie) {
  $__bb_hasLoja = true;
}

// ⚠️ Proteção anti-cruzamento:
// Se for contexto de LOJA (ex.: AJAX/API) e NÃO há slug explícito,
// e o navegador possui múltiplos cookies de tenant, não é seguro "chutar" qual loja.
// Nesse caso, bloqueia e exige envio do slug (?loja=slug ou /loja/slug/).
if ($__bb_hasLoja && $__bb_slug === '' && count($__bb_tenantCookies) > 1) {
  http_response_code(401);
  echo 'Sessão TENANT ambígua (múltiplas lojas no mesmo navegador). Informe o parâmetro ?loja=slug na requisição.';
  exit;
}

// Sessão desejada
$__bb_desiredSession = 'BB_SITE';
if ($__bb_forceMaster) {
  $__bb_desiredSession = 'BB_MASTER';
} elseif ($__bb_hasLoja) {
  // se tem slug, usa sessão por slug; se não, tenta resolver pelo único cookie tenant
  if ($__bb_slug !== '') {
    $__bb_desiredSession = $__bb_tenantSessionName;
  } elseif ($__bb_oneTenantCookie !== '') {
    $__bb_desiredSession = $__bb_oneTenantCookie;
  } else {
    // compat legado
    $__bb_desiredSession = 'BB_TENANT';
  }
}

// ===============================
// 4) Inicia/ajusta a sessão corretamente (usa cookie do nome escolhido)
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
// 4.1) Carrega CONFIG do Marketplace ANTES das validações de SLUG
// - Evita variáveis indefinidas ($cfgMaster/$cfgTenant/$cfgSecurity)
// - Necessário para bb_guard_validate_tenant_slug()
// ===============================
require_once __DIR__ . '/tenant_crypto.php';

$__bb_configFile = __DIR__ . '/../config/marketplace.php';
$__bb_cfg = null;

if (is_file($__bb_configFile)) {
  $__bb_cfg = require $__bb_configFile;
  if (!is_array($__bb_cfg)) $__bb_cfg = null;
}

// Sempre garante arrays (nunca null)
$cfgMaster   = (is_array($__bb_cfg) && isset($__bb_cfg['master'])   && is_array($__bb_cfg['master']))   ? $__bb_cfg['master']   : [];
$cfgTenant   = (is_array($__bb_cfg) && isset($__bb_cfg['tenant'])   && is_array($__bb_cfg['tenant']))   ? $__bb_cfg['tenant']   : [];
$cfgSecurity = (is_array($__bb_cfg) && isset($__bb_cfg['security']) && is_array($__bb_cfg['security'])) ? $__bb_cfg['security'] : [];

// ===============================
// 4.2) Resolve domínio próprio cedo (tenant_domains)
// - Necessário para /agendamentos, /home e /sistema em domínio próprio
// - Não depende de /loja/{slug}
// ===============================
$__bb_hostAtual = bb_clean_host($_SERVER['HTTP_HOST'] ?? '');
$__bb_tenantByHost = null;
$__bb_contextoDominioTenant = false;
$__bb_hostPrincipalMarketplace = bb_is_marketplace_main_host($__bb_hostAtual, is_array($__bb_cfg) ? $__bb_cfg : []);

if ($__bb_slug === '' && $__bb_hostAtual !== '' && !$__bb_hostPrincipalMarketplace && !bb_path_has_loja($__bb_reqPath) && !$__bb_hasLojaParam) {
  $__bb_tenantByHost = bb_find_tenant_by_host_market($__bb_hostAtual, $cfgMaster);

  if (is_array($__bb_tenantByHost) && !empty($__bb_tenantByHost['slug'])) {
    $__bb_slug = bb_sess_clean_slug((string)$__bb_tenantByHost['slug']);

    if ($__bb_slug !== '') {
      $__bb_tenantSessionName = 'BB_TENANT_' . strtoupper(substr(sha1($__bb_slug), 0, 12));

      if (session_name() !== $__bb_tenantSessionName) {
        if (session_status() === PHP_SESSION_ACTIVE) {
          session_write_close();
        }

        @session_name($__bb_tenantSessionName);
        if (!empty($_COOKIE[$__bb_tenantSessionName])) {
          @session_id((string)$_COOKIE[$__bb_tenantSessionName]);
        }
        @session_start();
      }

      $_SESSION['tenant_id'] = (int)($__bb_tenantByHost['id'] ?? 0);
      $_SESSION['tenant_slug'] = (string)$__bb_slug;
      $_SESSION['tenant_db'] = (string)($__bb_tenantByHost['db_name'] ?? '');
      $_SESSION['tenant_host'] = !empty($__bb_tenantByHost['db_host']) ? (string)$__bb_tenantByHost['db_host'] : (string)($cfgTenant['host'] ?? 'localhost');
      $_SESSION['tenant_user'] = !empty($__bb_tenantByHost['db_user']) ? (string)$__bb_tenantByHost['db_user'] : (string)($cfgTenant['user'] ?? '');

      $rawPassHost = !empty($__bb_tenantByHost['db_pass']) ? (string)$__bb_tenantByHost['db_pass'] : (string)($cfgTenant['pass'] ?? '');
      $secretHost = (string)($cfgSecurity['tenant_secret'] ?? '');
      $tenantPassHost = $rawPassHost;

      if ($rawPassHost !== '' && substr($rawPassHost, 0, 4) === 'enc:' && $secretHost !== '' && function_exists('bb_decrypt_str')) {
        $decHost = bb_decrypt_str($rawPassHost, $secretHost);
        if ($decHost !== '') {
          $tenantPassHost = $decHost;
        } else {
          $tenantPassHost = (string)($cfgTenant['pass'] ?? '');
        }
      }

      $_SESSION['tenant_pass'] = $tenantPassHost;
      $__bb_contextoDominioTenant = true;
      $__bb_hasLoja = true;
      $__bb_forceMaster = false;
    }
  } else {
    // Subdomínio aceito pelo ecossistema: slug.dominio-principal.com.br
    // Apenas identifica o slug aqui; o banco será confirmado no GLOBAL/MARKET mais abaixo.
    $__bb_slugSubdominio = bb_marketplace_subdomain_slug($__bb_hostAtual, is_array($__bb_cfg) ? $__bb_cfg : []);

    if ($__bb_slugSubdominio !== '') {
      $__bb_slug = $__bb_slugSubdominio;
      $__bb_tenantSessionName = 'BB_TENANT_' . strtoupper(substr(sha1($__bb_slug), 0, 12));

      if (session_name() !== $__bb_tenantSessionName) {
        if (session_status() === PHP_SESSION_ACTIVE) {
          session_write_close();
        }

        @session_name($__bb_tenantSessionName);
        if (!empty($_COOKIE[$__bb_tenantSessionName])) {
          @session_id((string)$_COOKIE[$__bb_tenantSessionName]);
        }
        @session_start();
      }

      $_SESSION['tenant_slug'] = (string)$__bb_slug;
      $__bb_contextoDominioTenant = true;
      $__bb_hasLoja = true;
      $__bb_forceMaster = false;
    }
  }
}

// ===============================
// 5) Marca contexto ativo (BB_CTX) - ajuda quando existir MASTER e TENANT no navegador
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
// 6) Namespace de sessão por perfil (compatível com o sistema atual)
// - NÃO remove $_SESSION['id'] etc (para não quebrar telas antigas)
// - Apenas espelha para:
//   Admin: $_SESSION['BARBER_ADMIN']['id']
//   Loja : $_SESSION['BARBER_LOJA']['id'] + database_name
// ===============================
if (!function_exists('bb_apply_session_namespace')) {
  function bb_apply_session_namespace(string $slugAtual = ''): void {

    $isTenant = (strpos(session_name(), 'BB_TENANT') === 0);
    $isMaster = (session_name() === 'BB_MASTER');

    if ($isMaster) {
      if (!isset($_SESSION['BARBER_ADMIN']) || !is_array($_SESSION['BARBER_ADMIN'])) {
        $_SESSION['BARBER_ADMIN'] = [];
      }

      $_SESSION['BARBER_ADMIN']['id']    = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
      $_SESSION['BARBER_ADMIN']['nivel'] = (string)($_SESSION['nivel'] ?? '');
      $_SESSION['BARBER_ADMIN']['nome']  = (string)($_SESSION['nome'] ?? '');
      $_SESSION['BARBER_ADMIN']['email'] = (string)($_SESSION['email'] ?? '');

      // em contexto master, evita "grudar" auth de tenant
      if (isset($_SESSION['auth_tenant_slug'])) {
        unset($_SESSION['auth_tenant_slug']);
      }
    }

    if ($isTenant) {
      if (!isset($_SESSION['BARBER_LOJA']) || !is_array($_SESSION['BARBER_LOJA'])) {
        $_SESSION['BARBER_LOJA'] = [];
      }

      $_SESSION['BARBER_LOJA']['id']    = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
      $_SESSION['BARBER_LOJA']['nivel'] = (string)($_SESSION['nivel'] ?? '');
      $_SESSION['BARBER_LOJA']['nome']  = (string)($_SESSION['nome'] ?? '');
      $_SESSION['BARBER_LOJA']['email'] = (string)($_SESSION['email'] ?? '');

      // tenant atual (pedido)
      $tslug = (string)($_SESSION['tenant_slug'] ?? $slugAtual);
      $_SESSION['BARBER_LOJA']['slug']          = $tslug;
      $_SESSION['BARBER_LOJA']['tenant_id']     = (int)($_SESSION['tenant_id'] ?? 0);
      $_SESSION['BARBER_LOJA']['database_name'] = (string)($_SESSION['tenant_db'] ?? '');
      $_SESSION['BARBER_LOJA']['timezone']      = (string)($_SESSION['tenant_timezone'] ?? 'America/Sao_Paulo');
    }
  }
}
bb_apply_session_namespace($__bb_slug);
// ===============================
// 6.2) Validação obrigatória do TENANT em rotas /loja/{slug}/
// - Garante que o slug existe e está ativo no MARKET (tabela tenants)
// - Grava tenant_id/tenant_db na sessão (evita "grudar" banco errado)
// - Se estiver logado e tentar trocar slug, força re-login (anti-vazamento)
// ===============================

// ===============================
// 6.1) Timezone padrão e por loja (precisa existir ANTES do guard do tenant)
// ===============================
// Regra segura para marketplace multi-tenant:
// - O código continua único para todas as lojas.
// - O fuso vem SOMENTE do cadastro do tenant, coluna `timezone`.
// - Se a coluna não existir ou estiver vazia/inválida, mantém America/Sao_Paulo.
// - Não calcula por UF/estado e não altera regras de agenda/cobrança.
if (!defined('BB_TIMEZONE_PADRAO')) {
  define('BB_TIMEZONE_PADRAO', 'America/Sao_Paulo');
}

date_default_timezone_set(BB_TIMEZONE_PADRAO);

if (!function_exists('bb_timezone_padrao')) {
  function bb_timezone_padrao(): string {
    return BB_TIMEZONE_PADRAO;
  }
}

if (!function_exists('bb_normalizar_timezone_tenant')) {
  function bb_normalizar_timezone_tenant($timezone): string {
    $timezone = trim((string)$timezone);

    if ($timezone === '') {
      return bb_timezone_padrao();
    }

    // Sanitização defensiva: timezone deve ser texto simples no padrão IANA.
    $timezone = str_replace(["\0", "\r", "\n", "\t", "\\"], '', $timezone);
    $timezone = trim($timezone);

    if ($timezone === '' || strlen($timezone) > 64) {
      return bb_timezone_padrao();
    }

    // Lista principal usada no Brasil. Campo Grande/MS deve usar America/Campo_Grande.
    $timezonesPermitidosBrasil = [
      'America/Sao_Paulo',
      'America/Campo_Grande',
      'America/Cuiaba',
      'America/Manaus',
      'America/Porto_Velho',
      'America/Boa_Vista',
      'America/Rio_Branco',
      'America/Eirunepe',
      'America/Noronha',
      'America/Fortaleza',
      'America/Recife',
      'America/Bahia',
      'America/Belem',
      'America/Santarem',
      'America/Maceio',
      'America/Araguaina',
    ];

    if (in_array($timezone, $timezonesPermitidosBrasil, true)) {
      return $timezone;
    }

    // Permite outros fusos IANA válidos, caso o marketplace atenda fora do Brasil.
    try {
      new DateTimeZone($timezone);
      return $timezone;
    } catch (Throwable $e) {
      return bb_timezone_padrao();
    }
  }
}

if (!function_exists('bb_timezone_by_uf')) {
  function bb_timezone_by_uf($uf): string {
    // Mantido apenas por compatibilidade caso algum arquivo antigo chame essa função.
    // O conexao.php NÃO usa UF para decidir timezone automaticamente.
    $uf = strtoupper(substr(preg_replace('/[^A-Z]/', '', (string)$uf), 0, 2));

    switch ($uf) {
      case 'AC': return 'America/Rio_Branco';
      case 'AM': return 'America/Manaus';
      case 'RR': return 'America/Boa_Vista';
      case 'RO': return 'America/Porto_Velho';
      case 'MT': return 'America/Cuiaba';
      case 'MS': return 'America/Campo_Grande';
      case 'PA': return 'America/Belem';
      case 'AP': return 'America/Belem';
      default:   return bb_timezone_padrao();
    }
  }
}

if (!function_exists('bb_apply_tenant_timezone')) {
  function bb_apply_tenant_timezone($tenantRow): string {
    $tz = bb_timezone_padrao();

    if (is_array($tenantRow) && array_key_exists('timezone', $tenantRow)) {
      $tz = bb_normalizar_timezone_tenant($tenantRow['timezone']);
    }

    if (!date_default_timezone_set($tz)) {
      $tz = bb_timezone_padrao();
      date_default_timezone_set($tz);
    }

    if (session_status() === PHP_SESSION_ACTIVE) {
      $_SESSION['tenant_timezone'] = $tz;

      if (!isset($_SESSION['BARBER_LOJA']) || !is_array($_SESSION['BARBER_LOJA'])) {
        $_SESSION['BARBER_LOJA'] = [];
      }

      $_SESSION['BARBER_LOJA']['timezone'] = $tz;

      if (!isset($_SESSION['LOJA']) || !is_array($_SESSION['LOJA'])) {
        $_SESSION['LOJA'] = [];
      }

      $_SESSION['LOJA']['timezone'] = $tz;
    }

    return $tz;
  }
}

if (!function_exists('bb_tenant_limpar_contexto_sessao')) {
  function bb_tenant_limpar_contexto_sessao(): void {
    foreach (['tenant_id','tenant_slug','tenant_db','tenant_host','tenant_user','tenant_pass','tenant_timezone','auth_tenant_slug'] as $k) {
      if (isset($_SESSION[$k])) unset($_SESSION[$k]);
    }

    if (isset($_SESSION['BARBER_LOJA']) && is_array($_SESSION['BARBER_LOJA'])) {
      unset($_SESSION['BARBER_LOJA']['tenant_id'], $_SESSION['BARBER_LOJA']['slug'], $_SESSION['BARBER_LOJA']['database_name'], $_SESSION['BARBER_LOJA']['timezone']);
    }
  }
}

if (!function_exists('bb_tenant_data_br')) {
  function bb_tenant_data_br(string $data): string {
    $data = trim($data);
    if ($data === '' || $data === '0000-00-00') return '';
    $pedacos = explode('-', substr($data, 0, 10));
    if (count($pedacos) !== 3) return $data;
    return $pedacos[2] . '/' . $pedacos[1] . '/' . $pedacos[0];
  }
}

if (!function_exists('bb_tenant_requisicao_ajax')) {
  function bb_tenant_requisicao_ajax(): bool {
    $reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
    $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));

    if (PHP_SAPI === 'cli') return false;
    if (strpos($reqPath, '/ajax/') !== false || strpos($reqPath, '/api/') !== false) return true;
    if ($requestedWith === 'xmlhttprequest') return true;
    if (strpos($accept, 'application/json') !== false) return true;

    return false;
  }
}


if (!function_exists('bb_tenant_saida_erro_mapeamento')) {
  function bb_tenant_saida_erro_mapeamento(string $mensagem, int $codigo = 404): void {
    http_response_code($codigo);

    if (bb_tenant_requisicao_ajax()) {
      if (!headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
      }
      echo $mensagem;
      exit();
    }

    if (!headers_sent()) {
      header('Content-Type: text/html; charset=utf-8');
    }

    echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>Loja não encontrada</title>
  <style>
    body{margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827}
    .bb-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
    .bb-card{width:100%;max-width:680px;background:#fff;border:1px solid #e5e7eb;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.08);overflow:hidden}
    .bb-head{padding:18px 22px;background:#991b1b;color:#fff}
    .bb-body{padding:22px;line-height:1.6}
    .bb-btn{display:inline-block;margin-top:10px;padding:11px 16px;border-radius:10px;background:#991b1b;color:#fff;text-decoration:none;font-weight:700}
  </style>
</head>
<body>
  <div class="bb-wrap">
    <div class="bb-card">
      <div class="bb-head"><strong>Loja não encontrada</strong></div>
      <div class="bb-body">
        <p>' . nl2br(htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8')) . '</p>
        <a class="bb-btn" href="javascript:history.back()">Voltar</a>
      </div>
    </div>
  </div>
</body>
</html>';
    exit();
  }
}

if (!function_exists('bb_tenant_saida_bloqueio')) {
  function bb_tenant_saida_bloqueio(string $titulo, string $mensagem): void {
    bb_tenant_limpar_contexto_sessao();
    $_SESSION['tenant_block_title'] = $titulo;
    $_SESSION['tenant_block_message'] = $mensagem;

    http_response_code(403);

    if (bb_tenant_requisicao_ajax()) {
      if (!headers_sent()) {
        header('Content-Type: text/plain; charset=utf-8');
      }
      echo $titulo . ': ' . $mensagem;
      exit();
    }

    if (!headers_sent()) {
      header('Content-Type: text/html; charset=utf-8');
    }

    $tituloHtml = htmlspecialchars($titulo, ENT_QUOTES, 'UTF-8');
    $mensagemHtml = nl2br(htmlspecialchars($mensagem, ENT_QUOTES, 'UTF-8'));
    $alertaJs = json_encode($titulo . "\n\n" . $mensagem, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    echo '<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <title>' . $tituloHtml . '</title>
  <style>
    body{margin:0;padding:0;background:#f3f4f6;font-family:Arial,Helvetica,sans-serif;color:#111827}
    .bb-wrap{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:24px}
    .bb-card{width:100%;max-width:680px;background:#fff;border:1px solid #e5e7eb;border-radius:16px;box-shadow:0 10px 30px rgba(0,0,0,.08);overflow:hidden}
    .bb-head{padding:18px 22px;background:#1d4ed8;color:#fff}
    .bb-body{padding:22px}
    .bb-body p{margin:0 0 12px 0;line-height:1.6}
    .bb-badge{display:inline-block;margin-bottom:14px;padding:6px 10px;border-radius:999px;background:#dbeafe;color:#1d4ed8;font-size:12px;font-weight:700;letter-spacing:.02em;text-transform:uppercase}
    .bb-btn{display:inline-block;padding:11px 16px;border-radius:10px;background:#1d4ed8;color:#fff;text-decoration:none;font-weight:700}
  </style>
</head>
<body>
  <div class="bb-wrap">
    <div class="bb-card">
      <div class="bb-head"><strong>' . $tituloHtml . '</strong></div>
      <div class="bb-body">
        <span class="bb-badge">BarberBot Marketplace</span>
        <p>' . $mensagemHtml . '</p>
        <p>Regularize a assinatura para liberar o acesso novamente.</p>
        <a class="bb-btn" href="javascript:history.back()">Voltar</a>
      </div>
    </div>
  </div>
  <script>
    try {
      if (' . $alertaJs . ') {
        alert(' . $alertaJs . ');
      }
    } catch (e) {}
  </script>
</body>
</html>';
    exit();
  }
}

if (!function_exists('bb_tenant_marcar_bloqueado_market')) {
  function bb_tenant_marcar_bloqueado_market(PDO $pdoMarket, string $tenantsTable, array &$tenant): void {
    $id = isset($tenant['id']) ? (int)$tenant['id'] : 0;
    if ($id <= 0) return;

    $updates = [];
    $params = [':id' => $id];

    if (array_key_exists('status', $tenant)) {
      $updates[] = "`status` = 'blocked'";
      $tenant['status'] = 'blocked';
    }

    if (array_key_exists('ativo', $tenant)) {
      $updates[] = "`ativo` = 0";
      $tenant['ativo'] = 0;
    }

    if (array_key_exists('blocked_at', $tenant)) {
      $params[':blocked_at'] = date('Y-m-d H:i:s');
      $updates[] = "`blocked_at` = :blocked_at";
      $tenant['blocked_at'] = $params[':blocked_at'];
    }

    if (!$updates) return;

    $tenantsTable = preg_replace('/[^a-z0-9_]/i', '', $tenantsTable);
    if ($tenantsTable === '') $tenantsTable = 'tenants';

    try {
      $sql = "UPDATE `{$tenantsTable}` SET " . implode(', ', $updates) . " WHERE id = :id";
      $st = $pdoMarket->prepare($sql);
      $st->execute($params);
    } catch (Throwable $e) {
      // mantém fluxo sem fatal
    }
  }
}

if (!function_exists('bb_tenant_guard_bloqueio')) {
  function bb_tenant_guard_bloqueio(array &$tenant, PDO $pdoMarket, string $tenantsTable): void {
    $status = strtolower(trim((string)($tenant['status'] ?? '')));
    $ativoRaw = $tenant['ativo'] ?? null;
    $ativo = null;
    if ($ativoRaw !== null && $ativoRaw !== '') {
      $ativo = (int)$ativoRaw;
    }

    $vencimento = trim((string)($tenant['vencimento'] ?? ''));
    $hoje = date('Y-m-d');
    $vencido = false;

    if ($vencimento !== '' && $vencimento !== '0000-00-00') {
      $vencData = substr($vencimento, 0, 10);
      if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $vencData) && $vencData < $hoje) {
        $vencido = true;
      }
    }

    if ($vencido) {
      bb_tenant_marcar_bloqueado_market($pdoMarket, $tenantsTable, $tenant);

      $titulo = 'Assinatura vencida';
      $mensagem = 'Seu acesso foi bloqueado automaticamente porque a assinatura venceu em ' . bb_tenant_data_br($vencimento) . '.';
      $mensagem .= "\n\n";
      $mensagem .= 'Regularize o pagamento para desbloquear a loja.';
      bb_tenant_saida_bloqueio($titulo, $mensagem);
    }

    if ($status === 'blocked' || ($ativo !== null && $ativo === 0)) {
      $titulo = 'Acesso bloqueado';
      $mensagem = 'Sua loja está bloqueada no momento.';
      if ($vencimento !== '' && $vencimento !== '0000-00-00') {
        $mensagem = 'Sua loja está bloqueada. O vencimento registrado é ' . bb_tenant_data_br($vencimento) . '.';
      }
      $mensagem .= "\n\n";
      $mensagem .= 'Regularize a assinatura para liberar o acesso novamente.';
      bb_tenant_saida_bloqueio($titulo, $mensagem);
    }
  }
}


if (!function_exists('bb_guard_validate_tenant_host')) {
  function bb_guard_validate_tenant_host(string $hostAtual, array $cfgMaster, array $cfgTenant, array $cfgSecurity): void {
    $reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $hostAtual = bb_clean_host($hostAtual);

    if ($hostAtual === '') return;
    if (strpos($reqPath, '/sistema') === 0) return;
    if (strpos($reqPath, '/marketplace') !== false) return;
    if (strpos($reqPath, '/criar-barbearia.php') !== false) return;
    if (strpos($reqPath, '/provisionar_tenant.php') !== false) return;
    if (strpos($reqPath, '/finalizar_tenant.php') !== false) return;
    if (strpos($reqPath, '/loja/') === 0) return;

    if (empty($cfgMaster['host']) || empty($cfgMaster['db']) || empty($cfgMaster['user'])) {
      return;
    }

    try {
      $pdoMarket = new PDO(
        "mysql:host={$cfgMaster['host']};dbname={$cfgMaster['db']};charset=utf8mb4",
        $cfgMaster['user'],
        $cfgMaster['pass'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
      );
    } catch (Throwable $e) {
      return;
    }

    $tenantsTable = $cfgMaster['table'] ?? 'tenants';
    $tenantsTable = preg_replace('/[^a-z0-9_]/i', '', (string)$tenantsTable);
    if ($tenantsTable === '') $tenantsTable = 'tenants';

    $tenant = bb_find_tenant_by_host_market($hostAtual, $cfgMaster);
    if (!$tenant || empty($tenant['slug'])) {
      return;
    }

    bb_apply_tenant_timezone($tenant);
    bb_tenant_guard_bloqueio($tenant, $pdoMarket, $tenantsTable);

    $_SESSION['tenant_id'] = (int)($tenant['id'] ?? 0);
    $_SESSION['tenant_slug'] = (string)($tenant['slug'] ?? '');
    $_SESSION['tenant_db'] = (string)($tenant['db_name'] ?? '');

    if (!isset($_SESSION['BARBER_LOJA']) || !is_array($_SESSION['BARBER_LOJA'])) {
      $_SESSION['BARBER_LOJA'] = [];
    }
    $_SESSION['BARBER_LOJA']['tenant_id'] = (int)($_SESSION['tenant_id'] ?? 0);
    $_SESSION['BARBER_LOJA']['slug'] = (string)($_SESSION['tenant_slug'] ?? '');
    $_SESSION['BARBER_LOJA']['database_name'] = (string)($_SESSION['tenant_db'] ?? '');

    if (function_exists('bb_validate_slug_owner_or_logout')) {
      bb_validate_slug_owner_or_logout($tenant, (string)$tenant['slug'], $pdoMarket, $cfgSecurity, $tenantsTable);
    }
  }
}

if (!function_exists('bb_guard_validate_tenant_slug')) {
  function bb_guard_validate_tenant_slug(string $slugAtual, array $cfgMaster, array $cfgTenant, array $cfgSecurity): void {

    $reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $isLojaPath = (strpos($reqPath, '/loja/') === 0);

    // Se é rota /loja/ e não conseguiu slug, não deixa cair em fallback
    if ($isLojaPath && $slugAtual === '') {
      http_response_code(400);
      echo 'Loja não informada na URL.';
      exit();
    }

    // Só valida em contexto loja com slug
    if (!$isLojaPath || $slugAtual === '') return;

    // Precisa de credenciais do MARKET
    if (empty($cfgMaster['host']) || empty($cfgMaster['db']) || empty($cfgMaster['user'])) {
      http_response_code(500);
      echo 'Config do MARKET ausente.';
      exit();
    }

    // Conecta no MARKET (banco que contém a tabela tenants)
    try {
      $pdoMarket = new PDO(
        "mysql:host={$cfgMaster['host']};dbname={$cfgMaster['db']};charset=utf8mb4",
        $cfgMaster['user'],
        $cfgMaster['pass'] ?? '',
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
      );
    } catch (Throwable $e) {
      http_response_code(500);
      echo 'Erro ao conectar no MARKET.';
      exit();
    }

    $tenantsTable = $cfgMaster['table'] ?? 'tenants';
    $tenantsTable = preg_replace('/[^a-z0-9_]/i', '', (string)$tenantsTable);
    if ($tenantsTable === '') $tenantsTable = 'tenants';

    // Busca tenant pelo slug (sem filtrar ativo/status, para exibir o motivo correto do bloqueio)
    $st = $pdoMarket->prepare("SELECT * FROM `{$tenantsTable}` WHERE slug = :s LIMIT 1");
    $st->execute([':s' => $slugAtual]);
    $tenant = $st->fetch(PDO::FETCH_ASSOC);

    if (!$tenant) {
      http_response_code(404);
      echo 'Loja não encontrada.';
      exit();
    }

    // ✅ Aplica timezone do tenant antes de qualquer comparação por data/hora
    bb_apply_tenant_timezone($tenant);

    // ✅ Bloqueia automaticamente quando o tenant estiver vencido/bloqueado
    bb_tenant_guard_bloqueio($tenant, $pdoMarket, $tenantsTable);


    // Valida posse do slug (quando aplicável / sem chute)
    if (function_exists('bb_validate_slug_owner_or_logout')) {
      bb_validate_slug_owner_or_logout($tenant, $slugAtual, $pdoMarket, $cfgSecurity, $tenantsTable);
    }

    // Grava infos do tenant na sessão
    $_SESSION['tenant_id']   = (int)($tenant['id'] ?? 0);
    $_SESSION['tenant_slug'] = (string)($tenant['slug'] ?? $slugAtual);
    $_SESSION['tenant_db']   = (string)($tenant['db_name'] ?? '');

    if (!isset($_SESSION['BARBER_LOJA']) || !is_array($_SESSION['BARBER_LOJA'])) {
      $_SESSION['BARBER_LOJA'] = [];
    }
    $_SESSION['BARBER_LOJA']['tenant_id']     = (int)($_SESSION['tenant_id'] ?? 0);
    $_SESSION['BARBER_LOJA']['slug']          = (string)($_SESSION['tenant_slug'] ?? $slugAtual);
    $_SESSION['BARBER_LOJA']['database_name'] = (string)($_SESSION['tenant_db'] ?? '');

    // Trava anti-troca de slug com usuário logado
    $uid = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
    if ($uid > 0) {
      if (empty($_SESSION['auth_tenant_slug'])) {
        $_SESSION['auth_tenant_slug'] = $slugAtual;
      }
      if (!empty($_SESSION['auth_tenant_slug']) && $_SESSION['auth_tenant_slug'] !== $slugAtual) {
        foreach (['id','nome','nivel','nivel_usuario','email','aut_token_1010','aut_token_505052022','aut_token_portalapp','master_id'] as $k) {
          if (isset($_SESSION[$k])) unset($_SESSION[$k]);
        }
        unset($_SESSION['auth_tenant_slug']);
        $redir = '/loja/' . rawurlencode($slugAtual) . '/sistema/';
        if (!headers_sent()) header('Location: ' . $redir);
        else echo "<script>window.location='" . addslashes($redir) . "'</script>";
        exit();
      }
    }
  }
}
bb_guard_validate_tenant_slug($__bb_slug, $cfgMaster, $cfgTenant, $cfgSecurity);
bb_guard_validate_tenant_host($__bb_hostAtual ?? '', $cfgMaster, $cfgTenant, $cfgSecurity);


// ===============================
// 7) Filtro de segurança do slug (SEM CHUTE DE OWNER_ID)
// - Se o usuário está autenticado em um tenant (auth_tenant_slug) e tenta navegar
//   para outro slug dentro do mesmo cookie, força re-login (zera tokens).
// - Isso impede "vazamento" de login entre lojas.
// ===============================
if (strpos(session_name(), 'BB_TENANT') === 0 && $__bb_slug !== '') {
  $authSlug = (string)($_SESSION['auth_tenant_slug'] ?? '');
  if ($authSlug !== '' && $authSlug !== $__bb_slug) {

    // limpa somente autenticação (não derruba dados de tenant)
    foreach ([
      'id','nome','nivel','nivel_usuario','email',
      'aut_token_1010','aut_token_505052022','aut_token_portalapp',
      'master_id'
    ] as $k) {
      if (isset($_SESSION[$k])) unset($_SESSION[$k]);
    }
    unset($_SESSION['auth_tenant_slug']);
    // o verificar.php vai redirecionar para login quando necessário
  }
}


/**
 * ==========================================================
 * CONTEXTO DE ROTA (mais robusto que só SCRIPT_NAME)
 * ==========================================================
 */
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// Para headers, é melhor olhar a rota requisitada (REQUEST_URI), porque com rewrite o SCRIPT_NAME pode ser /index.php
if (!headers_sent() && strpos($reqPath, '/ajax/') === false && strpos($reqPath, '/api/') === false) {
  header('Content-Type: text/html; charset=utf-8');
}

/**
 * ==========================================================
 * CONTEXTO MARKETPLACE (NÃO GRUDAR EM TENANT DA SESSÃO)
 * ==========================================================
 */
$baseScript = basename($scriptName);

// ✅ Se está em /loja/<slug>/... NUNCA é marketplace (mesmo que o script seja home.php por rewrite)
// Em /sistema sem loja explícita é MASTER; não deixa $_SESSION['tenant_slug'] antigo transformar o contexto em loja.
// Em jc.tec.br (host principal do marketplace), a raiz e páginas públicas NÃO podem herdar tenant antigo da sessão.
$__bb_mainHostMarketplaceRequest = $__bb_hostPrincipalMarketplace
  && strpos($reqPath, '/loja/') !== 0
  && strpos($reqPath, '/sistema') !== 0
  && !$__bb_isAjaxApi;

$__bb_contextoDominioTenant = !empty($__bb_contextoDominioTenant)
  || (!empty($_SESSION['tenant_slug']) && !$__bb_hostPrincipalMarketplace && !bb_path_has_loja($reqPath) && !$__bb_hasSistemaReq);

$isLojaContext = (strpos($reqPath, '/loja/') === 0)
  || !empty($_GET['loja'])
  || !empty($_POST['loja'])
  || ($__bb_contextoDominioTenant && !empty($_SESSION['tenant_slug']))
  || (!$__bb_hasSistemaReq && $__bb_isAjaxApi && (strpos(session_name(), 'BB_TENANT') === 0 || $__bb_hasTenantCookie));

$isMarketplaceContext = (!$isLojaContext) && (
  $__bb_mainHostMarketplaceRequest ||
  strpos($reqPath, '/marketplace/') !== false ||
  strpos($scriptName, '/marketplace/') !== false ||
  in_array($baseScript, ['criar-barbearia.php','provisionar_tenant.php','finalizar_tenant.php','marketplace.php','home.php','index.php'], true)
);

/**
 * ==========================================================
 * CONTEXTO SISTEMA (DONO) -> SEMPRE MASTER
 * ==========================================================
 * Regra: se está em /sistema (e NÃO em /loja/...), nunca deve usar o DB do tenant.
 */
$isSystemContext = (strpos($reqPath, '/sistema') === 0) && (strpos($reqPath, '/loja/') !== 0) && empty($_GET['loja']) && empty($_POST['loja']) && empty($__bb_contextoDominioTenant);
/**
 * ==========================================================
 * AUTH CONTEXT: autenticar.php SEMPRE no jctecbr_master
 * ==========================================================
 */
$isAuthContext = !$isLojaContext && (strpos($reqPath, '/sistema/autenticar.php') !== false || strpos($scriptName, '/sistema/autenticar.php') !== false);
// ===============================
// PADRÕES (evita warnings)
// ===============================
$nome_sistema = 'BarberBot System';
$email_sistema = '';
$whatsapp_sistema = '';
$telefone_fixo_sistema = '';
$endereco_sistema = '';
$instagram_sistema = '';
$cnpj_sistema = '';
$cidade_sistema = '';

$tipo_rel = 'PDF';
$tipo_comissao = 'Porcentagem';
$api = 'menuia';
$entrada = 'Site';

$texto_rodape = '';
$texto_sobre = '';
$mapa = '';
$quantidade_cartoes = 0;
$texto_fidelidade = '';
$texto_agendamento = '';
$msg_agendamento = 'Sim';
$agendamento_dias = 30;
$itens_pag = 10;
$minutos_aviso = 0;

$token = '';
$instancia = '';
$url_video = '';
$posicao_video = 'sobre';

$taxa_sistema = 'Cliente';
$lanc_comissao = 'Sempre';
$porc_servico = 0;
$pgto_api = 'Não';
$opcao_pagar = 'Não';

$nome_bot = 'Barberbot';
$barberbot_ativo = 'Não';
$barberbot_boas_vindas = '';
$openai_key = '';
$openai_prompt = '';

$api_pagamento = 'Mercado Pago';
$public_key_mp = '';
$access_token_mp = '';
$asaas = '';

$logo_sistema = 'logo.png';
$icone_sistema = 'favicon.png';
$logo_rel = 'logo_rel.jpg';

$icone_site = 'favicon.png';
$favicon_192 = 'favicon_192.png';
$favicon_512 = 'favicon_512.png';

$img_banner_index = 'hero-bg.jpg';
$imagem_sobre = '';
$fundo_login = 'sem-foto.png';

$ativo_sistema = 'Sim';

/**
 * ✅ NÃO EXISTE "modo_teste" NO BANCO
 * - mantém variável fixa só pra compatibilidade com outros arquivos
 */
$modo_teste = 'Não';

$tel_whatsapp = '';
$pdo = null;

// ===============================
// MULTI-TENANT (MARKETPLACE)
// ===============================
require_once __DIR__ . '/tenant_crypto.php';

$configFile = __DIR__ . '/../config/marketplace.php';
$cfg = null;

if (is_file($configFile)) {
  $cfg = require $configFile;
  if (!is_array($cfg)) $cfg = null;
}

$cfgMaster   = (is_array($cfg) && isset($cfg['master'])   && is_array($cfg['master']))   ? $cfg['master']   : [];
$cfgTenant   = (is_array($cfg) && isset($cfg['tenant'])   && is_array($cfg['tenant']))   ? $cfg['tenant']   : [];
$cfgSecurity = (is_array($cfg) && isset($cfg['security']) && is_array($cfg['security'])) ? $cfg['security'] : [];
/**
 * ==========================================================
 * BLINDAGEM DE POSSE DO SLUG + FUNÇÃO conectarBancoPorSlug($slug)
 * - NÃO altera fluxo de agendamento / regras do sistema
 * - Só age quando:
 *   a) existe coluna de owner na tabela tenants do MARKET
 *   b) existe usuário logado no painel do dono (aut_token_1010 / portalapp)
 * ==========================================================
 */
if (!function_exists('bb_hard_logout_redirect')) {
  function bb_hard_logout_redirect(string $url): void {
    // limpa sessão atual
    $_SESSION = [];

    if (session_status() === PHP_SESSION_ACTIVE) {
      if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');

        if (PHP_VERSION_ID >= 70300) {
          @setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'] ?? '/',
            'domain'   => $params['domain'] ?? '',
            'secure'   => (bool)($params['secure'] ?? $secure),
            'httponly' => (bool)($params['httponly'] ?? true),
            'samesite' => 'Lax',
          ]);
        } else {
          @setcookie(session_name(), '', time() - 42000, $params['path'] ?? '/', $params['domain'] ?? '', $secure, true);
        }
      }
      @session_destroy();
    }

    // remove contexto (evita “voltar” pro contexto errado)
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    if (PHP_VERSION_ID >= 70300) {
      @setcookie('BB_CTX', '', [
        'expires'  => time() - 3600,
        'path'     => '/',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
      ]);
    } else {
      @setcookie('BB_CTX', '', time() - 3600, '/', '', $secure, true);
    }

    if (!headers_sent()) {
      header('Location: ' . $url);
    } else {
      echo "<script>window.location='" . addslashes($url) . "'</script>";
    }
    exit();
  }
}

if (!function_exists('bb_should_enforce_owner')) {
  function bb_should_enforce_owner(): bool {
    // Painel cliente NÃO entra nessa validação
    if (!empty($_SESSION['aut_token_505052022'])) return false;

    // Só valida posse quando está autenticado no painel do dono / portalapp
    if (!empty($_SESSION['aut_token_1010']) || !empty($_SESSION['aut_token_portalapp'])) {
      return !empty($_SESSION['id']);
    }

    return false;
  }
}

if (!function_exists('bb_find_owner_column')) {
  function bb_find_owner_column(PDO $pdoMaster, string $table, array $candidates): string {
    $table = preg_replace('/[^a-z0-9_]/i', '', $table);
    if ($table === '') return '';

    // normaliza candidates
    $cands = [];
    foreach ($candidates as $c) {
      $c = preg_replace('/[^a-z0-9_]/i', '', (string)$c);
      if ($c !== '') $cands[] = $c;
    }
    if (!$cands) return '';

    $in = "'" . implode("','", array_map('addslashes', $cands)) . "'";
    try {
      $st = $pdoMaster->query("
        SELECT COLUMN_NAME
          FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = '{$table}'
           AND COLUMN_NAME IN ({$in})
         LIMIT 1
      ");
      $col = $st ? $st->fetchColumn() : '';
      return $col ? (string)$col : '';
    } catch (Throwable $e) {
      return '';
    }
  }
}

if (!function_exists('bb_validate_slug_owner_or_logout')) {
  function bb_validate_slug_owner_or_logout(array $tenantRow, string $slug, PDO $pdoMaster, array $cfgSecurity, string $tenantsTable): void {

    // chave do config (padrão: true)
    $enforce = true;
    if (isset($cfgSecurity['enforce_slug_owner'])) {
      $enforce = (bool)$cfgSecurity['enforce_slug_owner'];
    }
    if (!$enforce) return;

    if (!bb_should_enforce_owner()) return;

    // candidatos de coluna (config ou default)
    $cands = isset($cfgSecurity['owner_columns']) && is_array($cfgSecurity['owner_columns'])
      ? $cfgSecurity['owner_columns']
      : ['owner_id','user_id','lojista_id','id_lojista','id_usuario','id_user','master_id','id_master'];

    $ownerCol = bb_find_owner_column($pdoMaster, $tenantsTable, $cands);
    if ($ownerCol === '') return; // sem coluna -> não chuta

    $ownerId = isset($tenantRow[$ownerCol]) ? (int)$tenantRow[$ownerCol] : 0;
    $userId  = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;

    if ($ownerId > 0 && $userId > 0 && $ownerId !== $userId) {
      // Bloqueia e força relogar
      $redir = '/loja/' . rawurlencode($slug) . '/sistema/?erro=permissao';
      bb_hard_logout_redirect($redir);
    }
  }
}

/**
 * Função pedida no plano:
 * conectarBancoPorSlug($slug)
 * - resolve tenant por slug no banco MARKET (tabela tenants)
 * - valida posse do slug (quando aplicável)
 * - retorna PDO do banco do tenant
 */
if (!function_exists('conectarBancoPorSlug')) {
  function conectarBancoPorSlug(string $slug): ?PDO {
    // usa as configs já carregadas no conexao.php
    global $cfgMaster, $cfgTenant, $cfgSecurity;

    $slug = bb_clean_slug($slug);
    if ($slug === '') return null;

    // 1) Reutiliza credenciais somente quando o mapeamento foi validado
    // nesta mesma requisição contra o banco GLOBAL/MARKET.
    // Nunca confia apenas em tenant_db/tenant_slug persistidos na sessão.
    $mapaValidado = $GLOBALS['__bb_tenant_mapping_validated'] ?? null;
    $sessSlug = isset($_SESSION['tenant_slug']) ? (string)$_SESSION['tenant_slug'] : '';
    $sessDb = isset($_SESSION['tenant_db']) ? (string)$_SESSION['tenant_db'] : '';

    if (
      is_array($mapaValidado)
      && (string)($mapaValidado['slug'] ?? '') === $slug
      && (string)($mapaValidado['db_name'] ?? '') !== ''
      && (string)($mapaValidado['db_name'] ?? '') === $sessDb
      && $sessSlug === $slug
      && !empty($_SESSION['tenant_host'])
      && !empty($_SESSION['tenant_user'])
    ) {
      try {
        $h = (string)$_SESSION['tenant_host'];
        $db = $sessDb;
        $u = (string)$_SESSION['tenant_user'];
        $p = (string)($_SESSION['tenant_pass'] ?? '');

        return new PDO("mysql:host={$h};dbname={$db};charset=utf8mb4", $u, $p, [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]);
      } catch (Throwable $e) {
        return null;
      }
    }

    // 2) Conecta no banco do MARKET (master do marketplace)
    if (empty($cfgMaster['host']) || empty($cfgMaster['db']) || empty($cfgMaster['user'])) {
      return null; // sem credencial -> não chuta
    }

    try {
      $pdoMaster = new PDO(
        "mysql:host={$cfgMaster['host']};dbname={$cfgMaster['db']};charset=utf8mb4",
        $cfgMaster['user'],
        $cfgMaster['pass'] ?? '',
        [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
      );

      $tenantsTable = $cfgMaster['table'] ?? 'tenants';
      $tenantsTable = preg_replace('/[^a-z0-9_]/i', '', (string)$tenantsTable);

      $st = $pdoMaster->prepare("SELECT * FROM `{$tenantsTable}` WHERE slug = :s LIMIT 1");
      $st->execute([':s' => $slug]);
      $tenant = $st->fetch(PDO::FETCH_ASSOC);

      if (!$tenant) return null;

      // 2.1) ✅ aplica timezone do tenant antes de regras sensíveis por data/hora
      bb_apply_tenant_timezone($tenant);

      // 2.1.1) ✅ bloqueia quando estiver vencido/bloqueado
      bb_tenant_guard_bloqueio($tenant, $pdoMaster, $tenantsTable);

      // 2.2) ✅ valida posse do slug (quando aplicável)
      bb_validate_slug_owner_or_logout($tenant, $slug, $pdoMaster, $cfgSecurity, $tenantsTable);

      // 3) Prepara credenciais do tenant (igual ao seu fluxo atual)
      $secret = (string)($cfgSecurity['tenant_secret'] ?? '');

      $_SESSION['tenant_id']   = (int)($tenant['id'] ?? 0);
      $_SESSION['tenant_slug'] = (string)($tenant['slug'] ?? $slug);
      $_SESSION['tenant_db']   = (string)($tenant['db_name'] ?? '');

      $_SESSION['tenant_host'] = !empty($tenant['db_host'])
        ? (string)$tenant['db_host']
        : (string)($cfgTenant['host'] ?? 'localhost');

      $_SESSION['tenant_user'] = !empty($tenant['db_user'])
        ? (string)$tenant['db_user']
        : (string)($cfgTenant['user'] ?? '');

      $rawPass = !empty($tenant['db_pass'])
        ? (string)$tenant['db_pass']
        : (string)($cfgTenant['pass'] ?? '');

      $tenantPass = $rawPass;
      $dec = '';

      if ($rawPass !== '' && substr($rawPass, 0, 4) === 'enc:') {
        if ($secret !== '' && function_exists('bb_decrypt_str')) {
          $dec = bb_decrypt_str($rawPass, $secret);
        }
        $tenantPass = ($dec !== '') ? $dec : (string)($cfgTenant['pass'] ?? '');
      }

      $_SESSION['tenant_pass'] = $tenantPass;

      // 4) Conecta no banco do tenant e retorna
      $h = $_SESSION['tenant_host'] ?? ($cfgTenant['host'] ?? 'localhost');
      $db = $_SESSION['tenant_db'] ?? '';
      $u = $_SESSION['tenant_user'] ?? '';
      $p = $_SESSION['tenant_pass'] ?? '';

      if ($db === '' || $u === '') return null;

      $pdoT = new PDO("mysql:host={$h};dbname={$db};charset=utf8mb4", (string)$u, (string)$p, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
      ]);

      return $pdoT;

    } catch (Throwable $e) {
      return null;
    }
  }
}

/**
 * ✅ FIX: evita fatal "Cannot redeclare bb_clean_slug()"
 */
if (!function_exists('bb_clean_slug')) {
  function bb_clean_slug($v) {
    $v = strtolower(trim((string)$v));
    return preg_replace('/[^a-z0-9_-]/', '', $v);
  }
}

// Se está no /sistema (MASTER), remove qualquer “grude” de tenant
// ✅ Marketplace: só limpa tenant quando a sessão atual for MASTER.
if ($isSystemContext && session_name() === 'BB_MASTER') {
  unset(
    $_SESSION['tenant_id'],
    $_SESSION['tenant_slug'],
    $_SESSION['tenant_db'],
    $_SESSION['tenant_host'],
    $_SESSION['tenant_user'],
    $_SESSION['tenant_pass'],
    $_SESSION['tenant_timezone']
  );
}

// Detecta slug
$slug = null;
$__bb_slugResolvedFromMapping = false;
$__bb_tenantMapRequired = false;
$GLOBALS['__bb_tenant_mapping_validated'] = null;

/**
 * ==========================================================
 * Se for contexto marketplace, NÃO detecta slug e NÃO usa sessão
 * Se for contexto sistema (/sistema), também NÃO detecta slug
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
  // Não aplica no host principal do marketplace (ex.: jc.tec.br), para não tratar "jc" como slug.
  if (!$slug && !$__bb_hostPrincipalMarketplace) {
    $host = $_SERVER['HTTP_HOST'] ?? '';
    $host = preg_replace('/:\d+$/', '', $host);
    $parts = explode('.', $host);
    if (count($parts) >= 3) {
      $sub = bb_clean_slug($parts[0]);
      $ignore = ['www','app','api','sistema','painel'];
      if ($sub !== '' && !in_array($sub, $ignore, true)) {
        $slug = $sub;
      }
    }
  }

  // domínio próprio
  if (!$slug) {
    $bbHostAtual2 = bb_clean_host($_SERVER['HTTP_HOST'] ?? '');
    if ($bbHostAtual2 !== '') {
      $tenantHostRow2 = bb_find_tenant_by_host_market($bbHostAtual2, $cfgMaster);
      if (is_array($tenantHostRow2) && !empty($tenantHostRow2['slug'])) {
        $slug = bb_clean_slug((string)$tenantHostRow2['slug']);
      }
    }
  }

  // sessão
  // Só reaproveita tenant_slug da sessão em contexto real de TENANT.
  // No host principal do marketplace, a página pública não deve virar loja por cookie antigo.
  if (!$slug && !empty($_SESSION['tenant_slug']) && (!$__bb_hostPrincipalMarketplace || $__bb_isAjaxApi || strpos(session_name(), 'BB_TENANT') === 0)) {
    $slug = bb_clean_slug((string)$_SESSION['tenant_slug']);
  }
}

$__bb_tenantMapRequired = (!$isMarketplaceContext && !$isSystemContext && !empty($slug));

// Se tem slug, sempre confirma o mapeamento no banco GLOBAL/MARKET.
// A sessão pode guardar autenticação, mas nunca decide sozinha qual banco será aberto.
if ($slug) {

  if (!empty($cfgMaster['host']) && !empty($cfgMaster['db']) && !empty($cfgMaster['user'])) {
    try {
      $pdoMaster = new PDO(
        "mysql:host={$cfgMaster['host']};dbname={$cfgMaster['db']};charset=utf8mb4",
        $cfgMaster['user'],
        $cfgMaster['pass'] ?? '',
        [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
      );

      $tenantsTable = $cfgMaster['table'] ?? 'tenants';
      $tenantsTable = preg_replace('/[^a-z0-9_]/i', '', (string)$tenantsTable);

      $st = $pdoMaster->prepare("SELECT * FROM `{$tenantsTable}` WHERE slug = :s LIMIT 1");
      $st->execute([':s' => $slug]);
      $tenant = $st->fetch();

      if ($tenant) {
        bb_apply_tenant_timezone($tenant);
        bb_tenant_guard_bloqueio($tenant, $pdoMaster, $tenantsTable);
        bb_validate_slug_owner_or_logout($tenant, (string)$slug, $pdoMaster, $cfgSecurity, $tenantsTable);

        $secret = (string)($cfgSecurity['tenant_secret'] ?? '');

        $_SESSION['tenant_id']   = (int)($tenant['id'] ?? 0);
        $_SESSION['tenant_slug'] = (string)($tenant['slug'] ?? $slug);
        $_SESSION['tenant_db']   = (string)($tenant['db_name'] ?? '');

        $_SESSION['tenant_host'] = !empty($tenant['db_host'])
          ? (string)$tenant['db_host']
          : (string)($cfgTenant['host'] ?? 'localhost');

        $_SESSION['tenant_user'] = !empty($tenant['db_user'])
          ? (string)$tenant['db_user']
          : (string)($cfgTenant['user'] ?? '');

        $rawPass = !empty($tenant['db_pass'])
          ? (string)$tenant['db_pass']
          : (string)($cfgTenant['pass'] ?? '');

        $tenantPass = $rawPass;
        $dec = '';

        if ($rawPass !== '' && substr($rawPass, 0, 4) === 'enc:') {
          if ($secret !== '' && function_exists('bb_decrypt_str')) {
            $dec = bb_decrypt_str($rawPass, $secret);
          }

          if ($dec !== '') {
            $tenantPass = $dec;
          } else {
            $tenantPass = (string)($cfgTenant['pass'] ?? '');
          }
        }

        $_SESSION['tenant_pass'] = $tenantPass;
        $__bb_slugResolvedFromMapping = true;
        $GLOBALS['__bb_tenant_mapping_validated'] = [
          'slug' => (string)$_SESSION['tenant_slug'],
          'db_name' => (string)$_SESSION['tenant_db'],
        ];

      } else {
        unset(
          $_SESSION['tenant_id'],
          $_SESSION['tenant_slug'],
          $_SESSION['tenant_db'],
          $_SESSION['tenant_host'],
          $_SESSION['tenant_user'],
          $_SESSION['tenant_pass'],
          $_SESSION['tenant_timezone']
        );
        $GLOBALS['__bb_tenant_mapping_validated'] = null;
      }
    } catch (Throwable $e) {
      unset(
        $_SESSION['tenant_id'],
        $_SESSION['tenant_slug'],
        $_SESSION['tenant_db'],
        $_SESSION['tenant_host'],
        $_SESSION['tenant_user'],
        $_SESSION['tenant_pass'],
        $_SESSION['tenant_timezone']
      );
      $GLOBALS['__bb_tenant_mapping_validated'] = null;
    }
  }
}


/**
 * ==========================================================
 * FALLBACK BANCO TENANT (somente se cfgSecurity['allow_slug_database_fallback'] = true)
 * - Tenta padrão: jctecbr_<slug> e jctecbr_<slug_com_underscore>
 * - Por padrão fica desligado para não conectar em banco por chute de nome
 * - Só aplica em contexto TENANT (não marketplace e não sistema)
 * ==========================================================
 */
$__bb_allowSlugDatabaseFallback = !empty($cfgSecurity['allow_slug_database_fallback']);
if ($__bb_allowSlugDatabaseFallback && !$isMarketplaceContext && !$isSystemContext && !empty($slug)) {
  $bb_host = $cfgTenant['host'] ?? 'localhost';
  $bb_user = $cfgTenant['user'] ?? null;
  $bb_pass = $cfgTenant['pass'] ?? '';

  // se ainda não definiu DB na sessão, tenta por padrão
  if ((empty($_SESSION['tenant_db']) || (string)($_SESSION['tenant_slug'] ?? '') !== (string)$slug) && $bb_user !== null) {

    $bb_cand1 = 'jctecbr_' . $slug;
    $bb_cand2 = 'jctecbr_' . str_replace('-', '_', $slug);

    foreach ([$bb_cand1, $bb_cand2] as $bb_dbCand) {
      try {
        $bb_test = new PDO(
          "mysql:host={$bb_host};dbname={$bb_dbCand};charset=utf8mb4",
          (string)$bb_user,
          (string)$bb_pass,
          [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
          ]
        );

        // conectou => grava sessão tenant
        $_SESSION['tenant_slug'] = $slug;
        $_SESSION['tenant_db']   = $bb_dbCand;
        $_SESSION['tenant_host'] = $bb_host;
        $_SESSION['tenant_user'] = $bb_user;
        $_SESSION['tenant_pass'] = $bb_pass;
        $__bb_slugResolvedFromMapping = true;

        $bb_test = null;
        break;
      } catch (Throwable $e) {
        // tenta próximo candidato
      }
    }
  }
}

// Segurança multi-tenant: quando existe slug em contexto TENANT, não conecta no default_db.
// O slug precisa estar mapeado no MARKET ou, se ativado no config, resolvido pelo fallback explícito.
if ($__bb_tenantMapRequired && (!$__bb_slugResolvedFromMapping || empty($_SESSION['tenant_db']) || (string)($_SESSION['tenant_slug'] ?? '') !== (string)$slug)) {
  bb_tenant_limpar_contexto_sessao();
  bb_tenant_saida_erro_mapeamento('Loja não encontrada ou banco individual não configurado para este slug.', 404);
}

// ===============================
// CONEXÃO COM BANCO (SEM MUDAR FLUXO)
// ===============================
$cfgAuth = (is_array($cfg) && isset($cfg['auth_master']) && is_array($cfg['auth_master'])) ? $cfg['auth_master'] : [];
$isWebhookContext = (strpos($reqPath, '/webhook/') !== false) || (strpos($scriptName, '/webhook/') !== false);

if ($isAuthContext || $isSystemContext) {
  // MASTER/ADMIN: usa exclusivamente credenciais globais.
  // Nunca cai em host, usuário, senha ou default_db de TENANT.
  $servidor = $cfgAuth['host'] ?? ($cfgMaster['host'] ?? null);
  $banco    = $cfgAuth['db']   ?? ($cfgMaster['db'] ?? null);
  $usuario  = $cfgAuth['user'] ?? ($cfgMaster['user'] ?? null);
  $senha    = $cfgAuth['pass'] ?? ($cfgMaster['pass'] ?? '');

} elseif ($isWebhookContext) {
  // Webhook sem slug: inicia no GLOBAL/MARKET para localizar o tenant pelo número/instância.
  // Após identificar a loja, o próprio webhook troca para o banco individual correspondente.
  $servidor = $cfgMaster['host'] ?? ($cfgAuth['host'] ?? null);
  $banco    = $cfgMaster['db'] ?? ($cfgAuth['db'] ?? null);
  $usuario  = $cfgMaster['user'] ?? ($cfgAuth['user'] ?? null);
  $senha    = $cfgMaster['pass'] ?? ($cfgAuth['pass'] ?? '');

} elseif ($isMarketplaceContext || !$isLojaContext) {
  // Marketplace, site público e chamadas do Master sem slug usam o banco global/administrativo.
  $servidor = $cfgAuth['host'] ?? ($cfgMaster['host'] ?? null);
  $banco    = $cfgAuth['db'] ?? ($cfgMaster['db'] ?? null);
  $usuario  = $cfgAuth['user'] ?? ($cfgMaster['user'] ?? null);
  $senha    = $cfgAuth['pass'] ?? ($cfgMaster['pass'] ?? '');

} else {
  // TENANT: somente o banco individual previamente confirmado pelo slug/domínio.
  // Não existe fallback para default_db ou credenciais globais.
  $servidor = $_SESSION['tenant_host'] ?? null;
  $banco    = $_SESSION['tenant_db'] ?? null;
  $usuario  = $_SESSION['tenant_user'] ?? null;
  $senha    = $_SESSION['tenant_pass'] ?? '';
}

if ($banco !== null) {
  $banco = preg_replace('/[^a-z0-9_]/i', '', (string)$banco);
  if ($banco === '') {
    $banco = null;
  }
}

// ===============================
// ✅ Força reconexão dinâmica por SLUG (quando for TENANT)
// - Se o slug existir, tenta conectar diretamente via conectarBancoPorSlug($slug)
// - Mantém fallback do fluxo atual caso não consiga
// ===============================
$__bb_pdo_by_slug = false;
if (!$isAuthContext && !$isSystemContext && !$isMarketplaceContext) {
  $slugConn = '';
  if (!empty($slug)) $slugConn = (string)$slug;
  elseif (!empty($_SESSION['tenant_slug'])) $slugConn = (string)$_SESSION['tenant_slug'];

  if ($slugConn !== '') {
    $pdoTmp = conectarBancoPorSlug($slugConn);
    if ($pdoTmp instanceof PDO) {
      $pdo = $pdoTmp;
      $__bb_pdo_by_slug = true;

      // mantém variáveis também (compatibilidade com código legado)
      $servidor = $_SESSION['tenant_host'] ?? $servidor;
      $banco    = $_SESSION['tenant_db']   ?? $banco;
      $usuario  = $_SESSION['tenant_user'] ?? $usuario;
      $senha    = $_SESSION['tenant_pass'] ?? $senha;
    }
  }
}

if (($__bb_pdo_by_slug && $pdo instanceof PDO) || ($banco && $usuario !== null)) {
  try {
    if (!$__bb_pdo_by_slug) {
      $pdo = new PDO("mysql:dbname={$banco};host={$servidor};charset=utf8mb4", (string)$usuario, (string)$senha);
    }
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Carrega config do banco conectado
    try {
      $query = $pdo->query("SELECT * FROM config LIMIT 1");
      $d = $query->fetch(PDO::FETCH_ASSOC);

      if ($d) {
        $nome_sistema = $d['nome'] ?? $nome_sistema;
        $email_sistema = $d['email'] ?? $email_sistema;
        $whatsapp_sistema = $d['telefone_whatsapp'] ?? $whatsapp_sistema;
        $telefone_fixo_sistema = $d['telefone_fixo'] ?? $telefone_fixo_sistema;
        $endereco_sistema = $d['endereco'] ?? $endereco_sistema;

        $tipo_rel = $d['tipo_rel'] ?? $tipo_rel;
        $instagram_sistema = $d['instagram'] ?? $instagram_sistema;
        $tipo_comissao = $d['tipo_comissao'] ?? $tipo_comissao;

        $texto_rodape = $d['texto_rodape'] ?? $texto_rodape;
        $texto_sobre = $d['texto_sobre'] ?? $texto_sobre;
        $mapa = $d['mapa'] ?? $mapa;

        $quantidade_cartoes = $d['quantidade_cartoes'] ?? $quantidade_cartoes;
        $texto_fidelidade = $d['texto_fidelidade'] ?? $texto_fidelidade;
        $texto_agendamento = $d['texto_agendamento'] ?? $texto_agendamento;
        $msg_agendamento = $d['msg_agendamento'] ?? $msg_agendamento;

        $cnpj_sistema = $d['cnpj'] ?? $cnpj_sistema;
        $cidade_sistema = $d['cidade'] ?? $cidade_sistema;

        $agendamento_dias = $d['agendamento_dias'] ?? $agendamento_dias;
        $itens_pag = $d['itens_pag'] ?? $itens_pag;

        $token = $d['token'] ?? $token;
        $minutos_aviso = $d['minutos_aviso'] ?? $minutos_aviso;
        $instancia = $d['instancia'] ?? $instancia;

        $url_video = $d['url_video'] ?? $url_video;
        $posicao_video = $d['posicao_video'] ?? $posicao_video;

        $taxa_sistema = $d['taxa_sistema'] ?? $taxa_sistema;
        $lanc_comissao = $d['lanc_comissao'] ?? $lanc_comissao;
        $porc_servico = $d['porc_servico'] ?? $porc_servico;

        $pgto_api = $d['pgto_api'] ?? $pgto_api;
        $api_pagamento = $d['api_pagamento'] ?? $api_pagamento;
        $public_key_mp = $d['public_key_mp'] ?? $public_key_mp;
        $access_token_mp = $d['access_token_mp'] ?? $access_token_mp;
        $asaas = $d['asaas'] ?? $asaas;

        $api = $d['api'] ?? $api;
        $entrada = $d['entrada'] ?? $entrada;
        $opcao_pagar = $d['opcao_pagar'] ?? $opcao_pagar;

        $nome_bot = $d['nome_bot'] ?? $nome_bot;
        $barberbot_ativo = $d['barberbot_ativo'] ?? $barberbot_ativo;
        $barberbot_boas_vindas = $d['barberbot_boas_vindas'] ?? $barberbot_boas_vindas;
        $openai_key = $d['openai_key'] ?? $openai_key;
        $openai_prompt = $d['openai_prompt'] ?? $openai_prompt;

        $logo_sistema = $d['logo'] ?? $logo_sistema;
        $icone_sistema = $d['icone'] ?? $icone_sistema;
        $logo_rel = $d['logo_rel'] ?? $logo_rel;

        $icone_site = $d['icone_site'] ?? $icone_site;
        $favicon_192 = $d['favicon_192'] ?? $favicon_192;
        $favicon_512 = $d['favicon_512'] ?? $favicon_512;

        $img_banner_index = $d['img_banner_index'] ?? $img_banner_index;
        $imagem_sobre = $d['imagem_sobre'] ?? $imagem_sobre;
        $fundo_login = $d['fundo_login'] ?? $fundo_login;

        $ativo_sistema = $d['ativo'] ?? $ativo_sistema;

        if ($fundo_login === '') $fundo_login = 'sem-foto.png';
      }
    } catch (Throwable $e) {
      // usa padrões
    }
  } catch (Throwable $e) {
    $pdo = null;
  }
}

if (!empty($whatsapp_sistema)) {
  $tel_whatsapp = '55' . preg_replace('/[ ()-]+/', '', $whatsapp_sistema);
}

$url_sistema = "https://" . ($_SERVER['HTTP_HOST'] ?? 'localhost') . "/";

$h = rand(8, 10);
$m = rand(0, 59);
$hora_random = str_pad((string)$h, 2, '0', STR_PAD_LEFT) . ':' . str_pad((string)$m, 2, '0', STR_PAD_LEFT) . ':00';

$__bb_runtimeTenant = (!$isMarketplaceContext && !$isSystemContext && (!empty($slug) || !empty($_SESSION['tenant_slug']) || !empty($_GET['loja']) || !empty($_POST['loja']) || !empty($__bb_contextoDominioTenant)));
$__bb_ativoNormalizado = trim((string)$ativo_sistema);

if (!$__bb_runtimeTenant && $__bb_ativoNormalizado !== 'Sim' && $__bb_ativoNormalizado !== '') {
  if (strpos($_SERVER['PHP_SELF'], 'bloqueio.php') === false) {
    echo "<script>window.location='bloqueio.php'</script>";
    exit;
  }
}
