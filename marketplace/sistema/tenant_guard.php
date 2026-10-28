<?php
/**
 * BarberBot - Tenant Guard (MASTER -> TENANT)
 * - Detecta SLUG
 * - Descobre (sem chutar) tabela/colunas no MASTER
 * - Valida posse do SLUG (owner_id/user_id) quando existir
 * - Conecta dinamicamente no banco da loja por SLUG
 */

if (!function_exists('bb_clean_slug')) {
  function bb_clean_slug($v) {
    $v = strtolower(trim((string)$v));
    return preg_replace('/[^a-z0-9_-]/', '', $v);
  }
}

if (!function_exists('bb_detect_slug')) {
  function bb_detect_slug() {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $ref  = '';
    if (!empty($_SERVER['HTTP_REFERER'])) {
      $ref = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: '';
    }

    // prioridade: GET/POST -> /loja/<slug>/ -> referer
    if (!empty($_GET['loja']))  return bb_clean_slug($_GET['loja']);
    if (!empty($_POST['loja'])) return bb_clean_slug($_POST['loja']);

    if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $path, $m)) return bb_clean_slug($m[1]);
    if ($ref && preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $ref, $m2)) return bb_clean_slug($m2[1]);

    return '';
  }
}

if (!function_exists('bb_pick_key')) {
  function bb_pick_key(array $row, array $keys) {
    foreach ($keys as $k) {
      if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') return $k;
    }
    return '';
  }
}

if (!function_exists('bb_pick_val')) {
  function bb_pick_val(array $row, array $keys, $default = '') {
    $k = bb_pick_key($row, $keys);
    return $k !== '' ? (string)$row[$k] : (string)$default;
  }
}

if (!function_exists('bb_pick_int')) {
  function bb_pick_int(array $row, array $keys, $default = 0) {
    $k = bb_pick_key($row, $keys);
    return $k !== '' ? (int)$row[$k] : (int)$default;
  }
}

/**
 * Descobre (sem chutar) em qual tabela do MASTER estão:
 * - slug
 * - database_name / db_name / banco
 * - owner_id / user_id (se existir)
 * - host/user/pass (se existir)
 *
 * Cache em sessão para não ficar pesado.
 */
if (!function_exists('bb_master_detect_tenant_meta')) {
  function bb_master_detect_tenant_meta(PDO $pdoMaster) {

    // cache 30 min
    if (!empty($_SESSION['BB_TENANT_META']) && is_array($_SESSION['BB_TENANT_META'])) {
      $meta = $_SESSION['BB_TENANT_META'];
      if (!empty($meta['_ts']) && (time() - (int)$meta['_ts']) < 1800) return $meta;
    }

    // override manual (caso você queira travar por config)
    if (defined('BB_TENANT_TABLE') && BB_TENANT_TABLE) {
      $meta = [
        '_ts'          => time(),
        'table'        => (string)BB_TENANT_TABLE,
        'col_slug'     => defined('BB_TENANT_COL_SLUG') ? (string)BB_TENANT_COL_SLUG : 'slug',
        'col_dbname'   => defined('BB_TENANT_COL_DBNAME') ? (string)BB_TENANT_COL_DBNAME : 'database_name',
        'col_owner'    => defined('BB_TENANT_COL_OWNER') ? (string)BB_TENANT_COL_OWNER : '',
        'col_host'     => defined('BB_TENANT_COL_HOST') ? (string)BB_TENANT_COL_HOST : '',
        'col_user'     => defined('BB_TENANT_COL_USER') ? (string)BB_TENANT_COL_USER : '',
        'col_pass'     => defined('BB_TENANT_COL_PASS') ? (string)BB_TENANT_COL_PASS : '',
      ];
      $_SESSION['BB_TENANT_META'] = $meta;
      return $meta;
    }

    $colsWanted = [
      'slug','tenant_slug','loja','loja_slug',
      'database_name','db_name','dbname','banco','base','database','db',
      'owner_id','user_id','id_user','id_usuario','master_id','id_master',
      'db_host','host','servidor',
      'db_user','usuario','user',
      'db_pass','senha','pass',
    ];

    // lê colunas do schema atual (MASTER)
    $in = implode("','", array_map('addslashes', $colsWanted));

    $sql = "
      SELECT TABLE_NAME, COLUMN_NAME
      FROM INFORMATION_SCHEMA.COLUMNS
      WHERE TABLE_SCHEMA = DATABASE()
      AND COLUMN_NAME IN ('$in')
    ";
    $st = $pdoMaster->query($sql);
    $rows = $st ? $st->fetchAll(PDO::FETCH_ASSOC) : [];

    $tables = [];
    foreach ($rows as $r) {
      $t = (string)$r['TABLE_NAME'];
      $c = (string)$r['COLUMN_NAME'];
      if (!isset($tables[$t])) $tables[$t] = [];
      $tables[$t][$c] = true;
    }

    // pontua tabelas candidatas
    $best = ['score' => -1, 'table' => '', 'cols' => []];
    foreach ($tables as $t => $cols) {

      // precisa ter algum tipo de slug
      $hasSlug = isset($cols['slug']) || isset($cols['tenant_slug']) || isset($cols['loja']) || isset($cols['loja_slug']);
      if (!$hasSlug) continue;

      $score = 0;
      $score += 10; // tem slug

      $hasDb = isset($cols['database_name']) || isset($cols['db_name']) || isset($cols['dbname']) || isset($cols['banco']) || isset($cols['base']) || isset($cols['database']) || isset($cols['db']);
      if ($hasDb) $score += 10;

      $hasOwner = isset($cols['owner_id']) || isset($cols['user_id']) || isset($cols['id_user']) || isset($cols['id_usuario']) || isset($cols['master_id']) || isset($cols['id_master']);
      if ($hasOwner) $score += 5;

      $hasHost = isset($cols['db_host']) || isset($cols['host']) || isset($cols['servidor']);
      if ($hasHost) $score += 2;

      $hasUser = isset($cols['db_user']) || isset($cols['usuario']) || isset($cols['user']);
      if ($hasUser) $score += 2;

      $hasPass = isset($cols['db_pass']) || isset($cols['senha']) || isset($cols['pass']);
      if ($hasPass) $score += 2;

      if ($score > $best['score']) {
        $best = ['score' => $score, 'table' => $t, 'cols' => $cols];
      }
    }

    if ($best['table'] === '') {
      // não achou nada: mantém vazio (não quebra o sistema)
      $meta = ['_ts'=>time(),'table'=>'','col_slug'=>'','col_dbname'=>'','col_owner'=>'','col_host'=>'','col_user'=>'','col_pass'=>''];
      $_SESSION['BB_TENANT_META'] = $meta;
      return $meta;
    }

    $cols = $best['cols'];

    // decide colunas com prioridade
    $colSlug = isset($cols['slug']) ? 'slug' : (isset($cols['tenant_slug']) ? 'tenant_slug' : (isset($cols['loja_slug']) ? 'loja_slug' : 'loja'));

    $colDb   = isset($cols['database_name']) ? 'database_name' :
               (isset($cols['db_name']) ? 'db_name' :
               (isset($cols['dbname']) ? 'dbname' :
               (isset($cols['banco']) ? 'banco' :
               (isset($cols['base']) ? 'base' :
               (isset($cols['database']) ? 'database' : 'db')))));

    $colOwner = isset($cols['owner_id']) ? 'owner_id' :
                (isset($cols['user_id']) ? 'user_id' :
                (isset($cols['id_user']) ? 'id_user' :
                (isset($cols['id_usuario']) ? 'id_usuario' :
                (isset($cols['master_id']) ? 'master_id' :
                (isset($cols['id_master']) ? 'id_master' : '')))));

    $colHost = isset($cols['db_host']) ? 'db_host' : (isset($cols['host']) ? 'host' : (isset($cols['servidor']) ? 'servidor' : ''));
    $colUser = isset($cols['db_user']) ? 'db_user' : (isset($cols['usuario']) ? 'usuario' : (isset($cols['user']) ? 'user' : ''));
    $colPass = isset($cols['db_pass']) ? 'db_pass' : (isset($cols['senha']) ? 'senha' : (isset($cols['pass']) ? 'pass' : ''));

    $meta = [
      '_ts'        => time(),
      'table'      => $best['table'],
      'col_slug'   => $colSlug,
      'col_dbname' => $colDb,
      'col_owner'  => $colOwner,
      'col_host'   => $colHost,
      'col_user'   => $colUser,
      'col_pass'   => $colPass,
    ];

    $_SESSION['BB_TENANT_META'] = $meta;
    return $meta;
  }
}

if (!function_exists('bb_master_get_tenant_by_slug')) {
  function bb_master_get_tenant_by_slug(PDO $pdoMaster, $slug) {
    $slug = bb_clean_slug($slug);
    if ($slug === '') return null;

    $meta = bb_master_detect_tenant_meta($pdoMaster);
    if (empty($meta['table'])) return null;

    $table = $meta['table'];
    $colSlug = $meta['col_slug'];

    // proteção extra: nomes de tabela/coluna vindos do schema (sem input do usuário)
    $sql = "SELECT * FROM `$table` WHERE `$colSlug` = :slug LIMIT 1";
    $st = $pdoMaster->prepare($sql);
    $st->execute([':slug' => $slug]);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    return is_array($row) ? $row : null;
  }
}

if (!function_exists('bb_force_logout')) {
  function bb_force_logout($redirectUrl) {

    // limpa sessão
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
      // remove cookie da sessão atual
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

    // limpa BB_CTX e todos BB_TENANT_*
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    foreach ($_COOKIE as $k => $v) {
      if ($k === 'BB_CTX' || $k === 'BB_TENANT' || strpos($k, 'BB_TENANT_') === 0) {
        if (PHP_VERSION_ID >= 70300) {
          @setcookie($k, '', [
            'expires'  => time() - 3600,
            'path'     => '/',
            'secure'   => $secure,
            'httponly' => true,
            'samesite' => 'Lax',
          ]);
        } else {
          @setcookie($k, '', time() - 3600, '/', '', $secure, true);
        }
      }
    }

    if (!headers_sent()) {
      header("Location: ".$redirectUrl);
    } else {
      echo "<script>window.location='".addslashes($redirectUrl)."'</script>";
    }
    exit();
  }
}

if (!function_exists('bb_validar_posse_slug')) {
  function bb_validar_posse_slug(array $tenantRow, $slug) {

    // Admin MASTER passa livre
    if (!empty($_SESSION['BARBER_ADMIN']['id'])) return true;
    if (session_name() === 'BB_MASTER') return true;

    $userId = 0;
    if (!empty($_SESSION['BARBER_LOJA']['id'])) $userId = (int)$_SESSION['BARBER_LOJA']['id'];
    elseif (!empty($_SESSION['id'])) $userId = (int)$_SESSION['id'];

    // Se nem logado está, não valida posse aqui (o verificar.php já controla login)
    if ($userId <= 0) return true;

    // Se tiver auth_tenant_slug, já impede troca de loja “no navegador”
    // (isso mantém compatibilidade com seu legado)
    if (!empty($_SESSION['auth_tenant_slug']) && $_SESSION['auth_tenant_slug'] !== $slug) {
      $redir = "/loja/".rawurlencode($slug)."/sistema/?erro=permissao";
      bb_force_logout($redir);
    }

    // valida posse se existir coluna de owner detectada
    global $pdo_master;
    if (!($pdo_master instanceof PDO)) return true; // não quebra

    $meta = bb_master_detect_tenant_meta($pdo_master);
    $colOwner = $meta['col_owner'] ?? '';

    if ($colOwner === '' || !array_key_exists($colOwner, $tenantRow)) {
      // sem coluna de owner no schema -> não quebra (mas também não “chuta”)
      return true;
    }

    $ownerId = (int)$tenantRow[$colOwner];
    if ($ownerId > 0 && $ownerId !== $userId) {
      $redir = "/loja/".rawurlencode($slug)."/sistema/?erro=permissao";
      bb_force_logout($redir);
    }

    return true;
  }
}

/**
 * Função pedida: conectarBancoPorSlug($slug)
 * - Usa $pdo_master (MASTER) para buscar dados do tenant
 * - Valida posse do slug
 * - Abre o PDO no banco do tenant e devolve
 */
if (!function_exists('conectarBancoPorSlug')) {
  function conectarBancoPorSlug($slug) {
    global $pdo_master;

    $slug = bb_clean_slug($slug);
    if ($slug === '') return null;

    if (!($pdo_master instanceof PDO)) {
      // sem master não tem como resolver (não vamos chutar)
      return null;
    }

    $tenant = bb_master_get_tenant_by_slug($pdo_master, $slug);
    if (!$tenant) return null;

    bb_validar_posse_slug($tenant, $slug);

    // detecta colunas de conexão (se não existirem, reaproveita DSN/user/pass do master)
    $meta = bb_master_detect_tenant_meta($pdo_master);

    $dbName = '';
    if (!empty($meta['col_dbname']) && isset($tenant[$meta['col_dbname']])) {
      $dbName = (string)$tenant[$meta['col_dbname']];
    }
    $dbName = trim($dbName);

    if ($dbName === '') return null;

    // fallback seguro: se não existir host/user/pass no tenant, usa o mesmo do master
    $masterAttrs = $pdo_master->getAttribute(PDO::ATTR_CONNECTION_STATUS); // só pra manter compatibilidade (não usamos para senha)

    // IMPORTANTE:
    // Para não chutar senha/usuário do master aqui, o padrão é:
    // - Se seu sistema usa mesmo usuário/senha pra todas lojas, defina constants abaixo.
    // - Se o tenantRow tiver col_user/col_pass/col_host, ele usa.
    $host = '';
    $user = '';
    $pass = '';

    if (!empty($meta['col_host']) && isset($tenant[$meta['col_host']])) $host = (string)$tenant[$meta['col_host']];
    if (!empty($meta['col_user']) && isset($tenant[$meta['col_user']])) $user = (string)$tenant[$meta['col_user']];
    if (!empty($meta['col_pass']) && isset($tenant[$meta['col_pass']])) $pass = (string)$tenant[$meta['col_pass']];

    $host = trim($host);

    // Se não veio do banco, usa constants (profissional e explícito, sem chute)
    if ($host === '' && defined('BB_TENANT_DB_HOST')) $host = (string)BB_TENANT_DB_HOST;
    if ($user === '' && defined('BB_TENANT_DB_USER')) $user = (string)BB_TENANT_DB_USER;
    if ($pass === '' && defined('BB_TENANT_DB_PASS')) $pass = (string)BB_TENANT_DB_PASS;

    // Se ainda não tiver user (não dá pra conectar sem chutar)
    if ($user === '' && !defined('BB_TENANT_DB_USER')) {
      return null;
    }

    if ($host === '') $host = 'localhost'; // padrão safe (host vazio geralmente é localhost)

    $dsn = "mysql:host={$host};dbname={$dbName};charset=utf8mb4";
    $pdoTenant = new PDO($dsn, $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    // grava sessão padronizada
    if (!isset($_SESSION['BARBER_LOJA']) || !is_array($_SESSION['BARBER_LOJA'])) $_SESSION['BARBER_LOJA'] = [];
    $_SESSION['BARBER_LOJA']['database_name'] = $dbName;
    $_SESSION['tenant_db'] = $dbName;
    $_SESSION['tenant_slug'] = $slug;

    return $pdoTenant;
  }
}
