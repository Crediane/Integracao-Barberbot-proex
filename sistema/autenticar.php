<?php
// /public_html/sistema/autenticar.php

// ===== Sessao isolada por contexto (MASTER x TENANT) =====
// Mantem a logica existente, apenas evita que a sessao do tenant "vaze" para /sistema (MASTER) e vice-versa.
// Regra segura (SEM usar HTTP_REFERER para decidir):
// - /sistema (sem /loja/ e sem ?loja=) => BB_MASTER
// - /loja/<slug>/... OU ?loja=...      => sessao TENANT isolada por SLUG (BB_TENANT_<hash>)

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

if (!function_exists('bb_auth_url')) {
  function bb_auth_url(string $path = ''): string {
    global $__bb_webBase;
    return rtrim((string)$__bb_webBase, '/') . '/' . ltrim($path, '/');
  }
}

// Detecta SLUG cedo (somente por URI/GET/POST)
if (!function_exists('bb_sess_clean_slug')) {
  function bb_sess_clean_slug($v) {
    $v = strtolower(trim((string)$v));
    return preg_replace('/[^a-z0-9_-]/', '', $v);
  }
}

$__bb_slug = '';
if (!empty($_GET['loja'])) {
  $__bb_slug = bb_sess_clean_slug($_GET['loja']);
} elseif (!empty($_POST['loja'])) {
  $__bb_slug = bb_sess_clean_slug($_POST['loja']);
} else {
  if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_routePath, $mSlug)) {
    $__bb_slug = bb_sess_clean_slug($mSlug[1]);
  }
}

// Nome da sessao TENANT por SLUG (cookie diferente por loja)
$__bb_tenantSessionName = 'BB_TENANT';
if ($__bb_slug !== '') {
  $__bb_tenantSessionName = 'BB_TENANT_' . strtoupper(substr(sha1($__bb_slug), 0, 12));
}

// Contexto loja: via URL /loja/<slug>/..., ou ?loja=..., ou POST loja
$__bb_hasLoja = (
  $__bb_slug !== '' ||
  (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_routePath) ||
  !empty($_GET['loja']) ||
  !empty($_POST['loja'])
);

$__bb_isSistema = (strpos($__bb_routePath, '/sistema') === 0);

// O endpoint só aceita dois contextos:
// - com slug explícito: TENANT;
// - sem slug explícito: MASTER.
// Cookies antigos e BB_CTX não podem alterar essa decisão.
$__bb_forceMaster = ($__bb_isSistema && !$__bb_hasLoja);
$__bb_desiredSession = $__bb_hasLoja ? $__bb_tenantSessionName : 'BB_MASTER';

// Inicia/ajusta a sessao corretamente (usa o cookie do nome escolhido)
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

// ✅ Atualiza contexto ativo (BB_CTX) para o navegador saber qual sessao priorizar quando existir MASTER e TENANT
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

header('Content-Type: text/html; charset=utf-8');
/*
  LOGICA (SEM BUGAR):
  - Se tiver SLUG (/loja/<slug>/sistema/):
      1) resolve TENANT pelo marketplace (tenants)
      2) autentica PRIMEIRO no TENANT
      3) se NAO existir usuario no tenant -> autentica no MASTER e sincroniza pro tenant
  - Se NAO tiver SLUG (/sistema/):
      autentica no MASTER (como ja era)
*/

// ============================
// SLUG helpers (mantem logica)
// ============================
function clean_slug(string $v): string {
  $v = strtolower(trim($v));
  $v = preg_replace('/[^a-z0-9_-]/', '', $v);
  return (string)$v;
}

function detect_slug(): string {

  // 1) GET/POST explicito
  if (!empty($_GET['loja'])) {
    $s = clean_slug((string)$_GET['loja']);
    if ($s !== '') return $s;
  }

  if (!empty($_POST['loja'])) {
    $s = clean_slug((string)$_POST['loja']);
    if ($s !== '') return $s;
  }

  // 2) URI /loja/<slug>/
  global $__bb_routePath;
  $uri = (string)$__bb_routePath;
  if ($uri !== '' && preg_match('~^/loja/([a-z0-9_-]+)/~i', $uri, $m)) {
    $s = clean_slug((string)$m[1]);
    if ($s !== '') return $s;
  }

  // A sessão só pode completar o slug quando a própria URL já está em /loja/{slug}/.
  $uriPath = (string)$__bb_routePath;
  $isLojaCtx = (is_string($uriPath) && strpos($uriPath, '/loja/') === 0);

  if ($isLojaCtx && !empty($_SESSION['tenant_slug'])) {
    $s = clean_slug((string)$_SESSION['tenant_slug']);
    if ($s !== '') return $s;
  }

  return '';
}

function redirect_erro(string $tipo, string $slug = ''): void {
  if ($slug !== '') {
    header('Location: ' . bb_auth_url('loja/' . rawurlencode($slug) . "/sistema/?erro={$tipo}"));
  } else {
    header('Location: ' . bb_auth_url("sistema/?erro={$tipo}"));
  }
  exit();
}

// ============================
// Config + PDO helpers
// ============================
function bb_cfg(): ?array {
  $f = __DIR__ . '/../config/marketplace.php';
  if (!is_file($f)) return null;
  $cfg = require $f;
  return is_array($cfg) ? $cfg : null;
}

function bb_pdo_master_from_cfg(array $cfg): ?PDO {
  // MASTER real: usa exclusivamente auth_master.
  $auth = (isset($cfg['auth_master']) && is_array($cfg['auth_master']))
    ? $cfg['auth_master']
    : [];

  $host = trim((string)($auth['host'] ?? ''));
  $db   = preg_replace('/[^a-z0-9_]/i', '', (string)($auth['db'] ?? ''));
  $user = trim((string)($auth['user'] ?? ''));
  $pass = (string)($auth['pass'] ?? '');

  if ($host === '' || $db === '' || $user === '') {
    return null;
  }

  try {
    return new PDO(
      "mysql:host={$host};dbname={$db};charset=utf8mb4",
      $user,
      $pass,
      [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
      ]
    );
  } catch (Throwable $e) {
    error_log("PDO MASTER FAIL: " . $e->getMessage());
    return null;
  }
}

function bb_pdo_market_from_cfg(array $cfg): ?PDO {
  // MARKET: (tabela tenants)
  $host = (string)($cfg['master']['host'] ?? 'localhost');
  $db   = (string)($cfg['master']['db'] ?? '');
  $user = (string)($cfg['master']['user'] ?? '');
  $pass = (string)($cfg['master']['pass'] ?? '');

  if ($db === '' || $user === '') return null;

  try {
    return new PDO(
      "mysql:host={$host};dbname={$db};charset=utf8mb4",
      $user,
      $pass,
      [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
      ]
    );
  } catch (Throwable $e) {
    error_log("PDO MARKET FAIL: " . $e->getMessage());
    return null;
  }
}

function bb_table_cols(PDO $pdo, string $table): array {
  try {
    $st = $pdo->query("SHOW COLUMNS FROM `{$table}`");
    $cols = [];
    foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
      if (!empty($r['Field'])) $cols[] = $r['Field'];
    }
    return $cols;
  } catch (Throwable $e) {
    return [];
  }
}

function bb_tenant_where_ativo(PDO $pdoMarket, string $dbName, string $table): string {
  try {
    $st = $pdoMarket->prepare("
      SELECT COLUMN_NAME
        FROM INFORMATION_SCHEMA.COLUMNS
       WHERE TABLE_SCHEMA = :db
         AND TABLE_NAME   = :t
         AND COLUMN_NAME IN ('status','ativo')
    ");
    $st->execute([':db' => $dbName, ':t' => $table]);
    $cols = $st->fetchAll(PDO::FETCH_COLUMN);

    if (in_array('ativo', $cols, true)) return "`ativo`=1";
    if (in_array('status', $cols, true)) return "`status`='active'";
  } catch (Throwable $e) {
    // ignora
  }
  return "1=1";
}

function bb_is_active_row(array $row): bool {
  $ativo = $row['ativo'] ?? '';
  return ($ativo === 'Sim' || $ativo === 'sim' || $ativo === 1 || $ativo === '1' || $ativo === true);
}

function bb_verify_user_password(string $senhaInput, string $senhaDb, string $hash): bool {
  $ok = false;

  // 1) bcrypt
  if ($hash !== '' && isset($hash[0]) && $hash[0] === '$' && password_verify($senhaInput, $hash)) {
    $ok = true;
  }

  // 2) md5 legado (32 chars)
  if (!$ok && $senhaDb !== '' && strlen($senhaDb) === 32 && md5($senhaInput) === $senhaDb) {
    $ok = true;
  }

  // 3) texto puro legado
  if (!$ok && $senhaDb !== '' && hash_equals($senhaDb, $senhaInput)) {
    $ok = true;
  }

  return $ok;
}

function bb_fetch_user(PDO $pdo, string $emailOrCpf): ?array {
  $input = trim((string)$emailOrCpf);

  // CPF (se vier CPF no lugar do email)
  $cpf_in = preg_replace('/\D+/', '', $input);

  // Helpers (sem depender de mbstring)
  $toLower = function(string $s): string {
    return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
  };
  $toUpper = function(string $s): string {
    return function_exists('mb_strtoupper') ? mb_strtoupper($s, 'UTF-8') : strtoupper($s);
  };

  // Remove whitespaces invisiveis do INPUT (nao mexe no banco!)
  $inputNoWs = str_replace(["\r", "\n", "\t"], "", $input);
  $inputNoWs = preg_replace('/\s+/u', '', $inputNoWs);

  $emails = [];

  // Se parece email, cria variacoes exatas (para banco case-sensitive/binary)
  if ($inputNoWs !== '' && strpos($inputNoWs, '@') !== false) {
    $emails[] = $inputNoWs;
    $emails[] = $toLower($inputNoWs);
    $emails[] = $toUpper($inputNoWs);

    $parts = explode('@', $inputNoWs, 2);
    $local = $parts[0] ?? '';
    $domain = $parts[1] ?? '';

    if ($local !== '' && $domain !== '') {
      $domainLower = $toLower($domain);

      // dominio tudo minusculo
      $emails[] = $local . '@' . $domainLower;

      // dominio com primeira letra maiuscula (caso: jacy@Jc.tec.br)
      $emails[] = $local . '@' . ucfirst($domainLower);

      // local minusculo + dominio minusculo
      $emails[] = $toLower($local) . '@' . $domainLower;
    }

    // limpa duplicados/vazios
    $emails = array_values(array_unique(array_filter($emails)));
  }

  // Monta WHERE de emails (OR) + CPF
  $params = [':cpf' => $cpf_in];
  $whereEmailParts = [];

  foreach ($emails as $i => $e) {
    $k = ':e' . $i;
    $whereEmailParts[] = "email = {$k}";
    $params[$k] = $e;
  }

  $whereEmail = $whereEmailParts ? '(' . implode(' OR ', $whereEmailParts) . ')' : '(0=1)';
  $whereCpf   = "REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ','') = :cpf";

  $sql = "SELECT *
            FROM usuarios
           WHERE {$whereEmail}
              OR {$whereCpf}
           LIMIT 1";

  $st = $pdo->prepare($sql);
  $st->execute($params);
  $u = $st->fetch(PDO::FETCH_ASSOC);

  return $u ? $u : null;
}

// ============================
// Detecta slug e fixa sessao
// ============================
$slug = detect_slug();

if ($slug !== '') {
  $_GET['loja'] = $slug;
  $_SESSION['tenant_slug'] = $slug;
} else {
  /**
   * ✅ FIX CRITICO:
   * Se esta logando no MASTER (/sistema/), limpa contexto do tenant.
   * (isso evita o /sistema/ carregar usando tenant_db antigo)
   */
  unset(
    $_SESSION['tenant_id'],
    $_SESSION['tenant_slug'],
    $_SESSION['tenant_db'],
    $_SESSION['tenant_host'],
    $_SESSION['tenant_user'],
    $_SESSION['tenant_pass']
  );
}

// ============================
// Inputs
// ============================
$email = trim((string)($_POST['email'] ?? ''));
$senha = (string)($_POST['senha'] ?? '');

if ($email === '' || $senha === '') {
  redirect_erro('vazio', $slug);
}

// ============================
// Config
// ============================
$cfg = bb_cfg();
if (!$cfg) {
  redirect_erro('sistema', $slug);
}

// =====================================================
// ✅ reCAPTCHA (compatível v2 + v3)
// - Não muda a lógica do login; só bloqueia se RECAPTCHA_REQUIRED=true
// - Se o token existir, valida; se falhar e REQUIRED=true => erro=captcha
// =====================================================
$__bb_recaptcha_cfg = __DIR__ . '/../config/recaptcha.php';
if (is_file($__bb_recaptcha_cfg)) {
  require_once $__bb_recaptcha_cfg;

  $rcToken = trim((string)($_POST['g-recaptcha-response'] ?? ($_POST['recaptcha_token'] ?? '')));

  // Se for obrigatório e token vazio -> bloqueia
  if (defined('RECAPTCHA_REQUIRED') && RECAPTCHA_REQUIRED && $rcToken === '') {
    redirect_erro('captcha', $slug);
  }

  if ($rcToken !== '' && function_exists('recaptcha_verify') && defined('RECAPTCHA_SECRET_KEY')) {
    $rc = recaptcha_verify($rcToken, 'login');

    if (empty($rc['ok'])) {
      // obrigatório? bloqueia. senão, só registra e continua.
      if (defined('RECAPTCHA_REQUIRED') && RECAPTCHA_REQUIRED) {
        redirect_erro('captcha', $slug);
      } else {
        error_log('reCAPTCHA (soft) falhou: ' . json_encode($rc));
      }
    }
  }
}// =====================================================
// SE TEM SLUG -> RESOLVE TENANT E AUTENTICA NO TENANT 1º
// =====================================================
$tenant = null;
$pdoT = null;

if ($slug !== '') {

  require_once __DIR__ . '/tenant_crypto.php';

  $pdoMarket = bb_pdo_market_from_cfg($cfg);
  if (!$pdoMarket) {
    redirect_erro('sistema', $slug);
  }

  $tenantsTable = (string)($cfg['master']['table'] ?? 'tenants');
  $tenantsTable = preg_replace('/[^a-z0-9_]/i', '', $tenantsTable);
  $whereAtivo = bb_tenant_where_ativo($pdoMarket, (string)($cfg['master']['db'] ?? ''), $tenantsTable);

  try {
    $st = $pdoMarket->prepare("SELECT * FROM `{$tenantsTable}` WHERE slug = :s AND {$whereAtivo} LIMIT 1");
    $st->execute([':s' => $slug]);
    $tenant = $st->fetch(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    $tenant = null;
  }

  if (!$tenant) {
    redirect_erro('sistema', $slug);
  }

  $dbTenant = (string)($tenant['db_name'] ?? '');
  if ($dbTenant === '') {
    redirect_erro('sistema', $slug);
  }

  $hostTenant = !empty($tenant['db_host'])
    ? (string)$tenant['db_host']
    : (string)($cfg['tenant']['host'] ?? 'localhost');

  $userTenant = !empty($tenant['db_user'])
    ? (string)$tenant['db_user']
    : (string)($cfg['tenant']['user'] ?? '');

  $rawPass = !empty($tenant['db_pass'])
    ? (string)$tenant['db_pass']
    : (string)($cfg['tenant']['pass'] ?? '');

  $tenantPass = $rawPass;
  $secret = (string)($cfg['security']['tenant_secret'] ?? '');

  if ($rawPass !== '' && substr($rawPass, 0, 4) === 'enc:') {
    $dec = '';
    if ($secret !== '' && function_exists('bb_decrypt_str')) {
      $dec = bb_decrypt_str($rawPass, $secret);
    }
    if ($dec !== '') $tenantPass = $dec;
    else $tenantPass = (string)($cfg['tenant']['pass'] ?? '');
  }

  // salva sessao tenant
  $_SESSION['tenant_id']   = (int)($tenant['id'] ?? 0);
  $_SESSION['tenant_slug'] = (string)($tenant['slug'] ?? $slug);
  $_SESSION['tenant_db']   = $dbTenant;
  $_SESSION['tenant_host'] = $hostTenant;
  $_SESSION['tenant_user'] = $userTenant;
  $_SESSION['tenant_pass'] = $tenantPass;

  // conecta no DB da LOJA
  try {
    $pdoT = new PDO(
      "mysql:host={$hostTenant};dbname={$dbTenant};charset=utf8mb4",
      $userTenant,
      $tenantPass,
      [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
      ]
    );
  } catch (Throwable $e) {
    error_log("PDO TENANT FAIL: " . $e->getMessage());
    redirect_erro('sistema', $slug);
  }

  // 1) tenta autenticar direto no TENANT
  try {
    $userT = bb_fetch_user($pdoT, $email);

    if ($userT) {
      if (!bb_is_active_row($userT)) {
        redirect_erro('desativado', $slug);
      }

      $hashT    = (string)($userT['senha_crip'] ?? '');
      $senhaDbT = (string)($userT['senha'] ?? '');

      if (!bb_verify_user_password($senha, $senhaDbT, $hashT)) {
        redirect_erro('senha', $slug);
      }

      $_SESSION['id']            = (int)($userT['id'] ?? 0);
      $_SESSION['nivel']         = (string)($userT['nivel'] ?? '');
      $_SESSION['nivel_usuario'] = (string)($userT['nivel'] ?? '');
      $_SESSION['nome']          = (string)($userT['nome'] ?? '');
      $_SESSION['email']         = (string)($userT['email'] ?? '');


      // ✅ Blindagem: trava o login neste SLUG (evita vazar para outra loja)
      $_SESSION['auth_tenant_slug'] = (string)$slug;

      // ✅ Namespace por perfil (não quebra o legado)
      $_SESSION['BARBER_LOJA'] = [
        'id'            => (int)($_SESSION['id'] ?? 0),
        'nivel'         => (string)($_SESSION['nivel'] ?? ''),
        'nome'          => (string)($_SESSION['nome'] ?? ''),
        'email'         => (string)($_SESSION['email'] ?? ''),
        'slug'          => (string)($_SESSION['tenant_slug'] ?? $slug),
        'tenant_id'     => (int)($_SESSION['tenant_id'] ?? 0),
        'database_name' => (string)($_SESSION['tenant_db'] ?? ''),
      ];

      $_SESSION['aut_token_1010'] = "fdsfdsafda8855555";
      $_SESSION['master_id'] = (int)($_SESSION['master_id'] ?? 0);

      session_write_close();
      header('Location: ' . bb_auth_url('loja/' . rawurlencode($slug) . '/painel/home/'));
      exit();
    }

  } catch (Throwable $e) {
    error_log("LOGIN TENANT ERR: " . $e->getMessage());
    redirect_erro('sistema', $slug);
  }
}

// ============================
// FLUXO MASTER (como ja era)
// ============================
$pdoM = bb_pdo_master_from_cfg($cfg);
if (!$pdoM) {
  redirect_erro('sistema', $slug);
}

try {
  $userM = bb_fetch_user($pdoM, $email);

  if (!$userM) {
    redirect_erro('inexistente', $slug);
  }

  if (!bb_is_active_row($userM)) {
    redirect_erro('desativado', $slug);
  }

  $hashM    = (string)($userM['senha_crip'] ?? '');
  $senhaDbM = (string)($userM['senha'] ?? '');

  if (!bb_verify_user_password($senha, $senhaDbM, $hashM)) {
    redirect_erro('senha', $slug);
  }

} catch (Throwable $e) {
  error_log("LOGIN MASTER ERR: " . $e->getMessage());
  redirect_erro('sistema', $slug);
}

// ============================
// Se tem slug: sincroniza pro tenant
// ============================
if ($slug !== '') {

  if (!$tenant || !$pdoT) {
    redirect_erro('sistema', $slug);
  }

  $emailM = (string)($userM['email'] ?? '');
  $cpfM   = preg_replace('/\D+/', '', (string)($userM['cpf'] ?? ''));

  try {
    $qt = $pdoT->prepare("
      SELECT *
        FROM usuarios
       WHERE LOWER(TRIM(email)) = LOWER(TRIM(:email))
          OR REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ','') = :cpf
       LIMIT 1
    ");
    $qt->bindValue(':email', $emailM);
    $qt->bindValue(':cpf', $cpfM);
    $qt->execute();
    $userT = $qt->fetch(PDO::FETCH_ASSOC);

    if (!$userT) {
      $cols = bb_table_cols($pdoT, 'usuarios');
      if (!$cols) redirect_erro('sistema', $slug);

      $fields = [];
      $params = [];
      $binds  = [];

      if (in_array('nome', $cols, true))       { $fields[]='`nome`';       $params[]=':nome';  $binds[':nome']  = [(string)($userM['nome'] ?? ''), PDO::PARAM_STR]; }
      if (in_array('email', $cols, true))      { $fields[]='`email`';      $params[]=':email'; $binds[':email'] = [$emailM, PDO::PARAM_STR]; }
      if (in_array('cpf', $cols, true))        { $fields[]='`cpf`';        $params[]=':cpf';   $binds[':cpf']   = [(string)($userM['cpf'] ?? ''), PDO::PARAM_STR]; }
      if (in_array('telefone', $cols, true))   { $fields[]='`telefone`';   $params[]=':tel';   $binds[':tel']   = [(string)($userM['telefone'] ?? ''), PDO::PARAM_STR]; }
      if (in_array('nivel', $cols, true))      { $fields[]='`nivel`';      $params[]=':nivel'; $binds[':nivel'] = [(string)($userM['nivel'] ?? ''), PDO::PARAM_STR]; }
      if (in_array('ativo', $cols, true))      { $fields[]='`ativo`';      $params[]=':ativo'; $binds[':ativo'] = ['Sim', PDO::PARAM_STR]; }
      if (in_array('senha', $cols, true))      { $fields[]='`senha`';      $params[]=':senha'; $binds[':senha'] = [(string)($userM['senha'] ?? ''), PDO::PARAM_STR]; }
      if (in_array('senha_crip', $cols, true)) { $fields[]='`senha_crip`'; $params[]=':hash';  $binds[':hash']  = [(string)($userM['senha_crip'] ?? ''), PDO::PARAM_STR]; }

      if (in_array('data', $cols, true)) {
        $fields[]='`data`'; $params[]=':data';
        $binds[':data']=[date('Y-m-d'), PDO::PARAM_STR];
      }

      if (!$fields) redirect_erro('sistema', $slug);

      $ins = $pdoT->prepare("INSERT INTO usuarios (" . implode(',', $fields) . ") VALUES (" . implode(',', $params) . ")");
      foreach ($binds as $k => $v) $ins->bindValue($k, $v[0], $v[1]);
      $ins->execute();

      $qt2 = $pdoT->prepare("
        SELECT *
          FROM usuarios
         WHERE LOWER(TRIM(email)) = LOWER(TRIM(:email))
            OR REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),' ','') = :cpf
         LIMIT 1
      ");
      $qt2->bindValue(':email', $emailM);
      $qt2->bindValue(':cpf', $cpfM);
      $qt2->execute();
      $userT = $qt2->fetch(PDO::FETCH_ASSOC);

      if (!$userT) redirect_erro('sistema', $slug);
    }

    $_SESSION['id']            = (int)($userT['id'] ?? 0);
    $_SESSION['nivel']         = (string)($userT['nivel'] ?? ($userM['nivel'] ?? ''));
    $_SESSION['nivel_usuario'] = (string)($userT['nivel'] ?? ($userM['nivel'] ?? ''));
    $_SESSION['nome']          = (string)($userT['nome'] ?? ($userM['nome'] ?? ''));
    $_SESSION['email']         = (string)($userT['email'] ?? $emailM);

  } catch (Throwable $e) {
    error_log("SYNC TENANT ERR: " . $e->getMessage());
    redirect_erro('sistema', $slug);
  }

} else {
  // sem slug: sessao do MASTER
  $_SESSION['id']            = (int)($userM['id'] ?? 0);
  $_SESSION['nivel']         = (string)($userM['nivel'] ?? '');
  $_SESSION['nivel_usuario'] = (string)($userM['nivel'] ?? '');
  $_SESSION['nome']          = (string)($userM['nome'] ?? '');
  $_SESSION['email']         = (string)($userM['email'] ?? '');

  // ✅ IMPORTANTE: login do DONO DO SISTEMA (sem /loja/) NAO pode herdar tenant antigo
  unset(
    $_SESSION['tenant_id'],
    $_SESSION['tenant_slug'],
    $_SESSION['tenant_db'],
    $_SESSION['tenant_host'],
    $_SESSION['tenant_user'],
    $_SESSION['tenant_pass']
  );
}

$_SESSION['aut_token_1010'] = "fdsfdsafda8855555";
$_SESSION['master_id'] = (int)($userM['id'] ?? 0);


// ✅ Namespace por perfil (não quebra o legado) + trava do SLUG autenticado
if ($slug !== '') {
  $_SESSION['auth_tenant_slug'] = (string)$slug;

  if (!isset($_SESSION['BARBER_LOJA']) || !is_array($_SESSION['BARBER_LOJA'])) {
    $_SESSION['BARBER_LOJA'] = [];
  }

  $_SESSION['BARBER_LOJA']['id']            = (int)($_SESSION['id'] ?? 0);
  $_SESSION['BARBER_LOJA']['nivel']         = (string)($_SESSION['nivel'] ?? '');
  $_SESSION['BARBER_LOJA']['nome']          = (string)($_SESSION['nome'] ?? '');
  $_SESSION['BARBER_LOJA']['email']         = (string)($_SESSION['email'] ?? '');
  $_SESSION['BARBER_LOJA']['slug']          = (string)($_SESSION['tenant_slug'] ?? $slug);
  $_SESSION['BARBER_LOJA']['tenant_id']     = (int)($_SESSION['tenant_id'] ?? 0);
  $_SESSION['BARBER_LOJA']['database_name'] = (string)($_SESSION['tenant_db'] ?? '');

} else {
  // login MASTER (admin do sistema)
  if (isset($_SESSION['auth_tenant_slug'])) unset($_SESSION['auth_tenant_slug']);

  $_SESSION['BARBER_ADMIN'] = [
    'id'    => (int)($_SESSION['id'] ?? 0),
    'nivel' => (string)($_SESSION['nivel'] ?? ''),
    'nome'  => (string)($_SESSION['nome'] ?? ''),
    'email' => (string)($_SESSION['email'] ?? ''),
  ];
}

if ($slug !== '' && empty($_SESSION['tenant_slug'])) {
  $_SESSION['tenant_slug'] = $slug;
}

session_write_close();

if ($slug !== '') {
  header('Location: ' . bb_auth_url('loja/' . rawurlencode($slug) . '/painel/home/'));
} else {
  header('Location: ' . bb_auth_url('sistema/painel/?pag=home'));
}
exit();
