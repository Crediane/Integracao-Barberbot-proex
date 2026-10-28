<?php
/**
 * BarberBot Software - Criar Novo Estabelecimento
 * Inclusão de domínio próprio opcional sem mudar a lógica atual:
 * - se preencher domínio próprio, provisiona como Business
 * - se deixar em branco, segue subdomínio padrão (Profissional)
 */
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

// Página MASTER: criação de tenant deve usar somente o banco global/administrativo.
if (session_status() === PHP_SESSION_ACTIVE) {
  if (session_name() !== 'BB_MASTER') {
    session_write_close();
    @session_name('BB_MASTER');
    if (!empty($_COOKIE['BB_MASTER'])) {
      @session_id((string)$_COOKIE['BB_MASTER']);
    }
    @session_start();
  }
} else {
  @session_name('BB_MASTER');
  if (!empty($_COOKIE['BB_MASTER'])) {
    @session_id((string)$_COOKIE['BB_MASTER']);
  }
  @session_start();
}

unset($_GET['loja']);
unset(
  $_SESSION['loja'],
  $_SESSION['tenant_slug'],
  $_SESSION['tenant_id'],
  $_SESSION['tenant_db'],
  $_SESSION['tenant_host'],
  $_SESSION['tenant_user'],
  $_SESSION['tenant_pass']
);

$bb_proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
  $bb_proto = ($_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : $bb_proto;
}
$bb_host = $_SERVER['HTTP_HOST'] ?? '';
$bb_base = ($bb_host !== '') ? ($bb_proto . '://' . $bb_host) : '';

$baseConn = __DIR__ . "/sistema/conexao.php";
if (file_exists($baseConn)) {
  require_once($baseConn);
} else {
  $nome_sistema  = "BarberBot";
  $logo_sistema  = "logo.png";
  $icone_sistema = "favicon.ico";
}

$nome_sistema  = $nome_sistema  ?? 'BarberBot';
$logo_sistema  = $logo_sistema  ?? 'logo.png';
$icone_sistema = $icone_sistema ?? 'favicon.ico';

$caminho_logo  = "/sistema/img/" . ($logo_sistema !== '' ? $logo_sistema : "logo.png");
$caminho_icone = "/sistema/img/" . ($icone_sistema !== '' ? $icone_sistema : "favicon.ico");

$link_marketplace = "/marketplace.php";
$action_provisionar = "/provisionar_tenant.php";


if (!function_exists('bb_criar_cfg')) {
  function bb_criar_cfg(array $dados, array $chaves, string $fallback = ''): string {
    foreach ($chaves as $chave) {
      if (isset($dados[$chave]) && trim((string)$dados[$chave]) !== '') {
        return trim((string)$dados[$chave]);
      }
    }
    return $fallback;
  }
}

if (!function_exists('bb_criar_id')) {
  function bb_criar_id(string $valor, string $tipo): string {
    $valor = strtoupper(trim($valor));

    if ($tipo === 'ga4') {
      if (preg_match('/G-[A-Z0-9]+/i', $valor, $m)) return strtoupper($m[0]);
      return preg_match('/^G-[A-Z0-9]+$/', $valor) ? $valor : '';
    }

    if ($tipo === 'gtm') {
      if (preg_match('/GTM-[A-Z0-9]+/i', $valor, $m)) return strtoupper($m[0]);
      return preg_match('/^GTM-[A-Z0-9]+$/', $valor) ? $valor : '';
    }

    if ($tipo === 'ads') {
      if (preg_match('/AW-[0-9]+/i', $valor, $m)) return strtoupper($m[0]);
      return preg_match('/^AW-[0-9]+$/', $valor) ? $valor : '';
    }

    if ($tipo === 'pixel') {
      if (preg_match('/[0-9]{6,}/', $valor, $m)) return (string)$m[0];
      return '';
    }

    return '';
  }
}

if (!function_exists('bb_criar_meta_content')) {
  function bb_criar_meta_content(string $valor): string {
    $valor = trim($valor);
    if ($valor !== '' && stripos($valor, 'google-site-verification') !== false && preg_match('/content=["\']([^"\']+)["\']/i', $valor, $m)) {
      return trim((string)$m[1]);
    }
    return trim(str_replace(['<', '>', '"', "'"], '', $valor));
  }
}

$bb_criar_config = [];
if (isset($pdo) && $pdo instanceof PDO) {
  try {
    $stCriarConfig = $pdo->query("SELECT * FROM config ORDER BY id ASC LIMIT 1");
    $rowCriarConfig = $stCriarConfig ? $stCriarConfig->fetch(PDO::FETCH_ASSOC) : false;
    if (is_array($rowCriarConfig)) {
      $bb_criar_config = $rowCriarConfig;
    }
  } catch (Throwable $e) {
    $bb_criar_config = [];
  }
}

$bb_criar_google_verification = bb_criar_meta_content(bb_criar_cfg($bb_criar_config, ['seo_google_verification', 'google_site_verification'], ''));
$bb_criar_google_analytics = bb_criar_id(bb_criar_cfg($bb_criar_config, ['seo_google_analytics', 'google_analytics_ga4'], ''), 'ga4');
$bb_criar_google_tag_manager = bb_criar_id(bb_criar_cfg($bb_criar_config, ['seo_google_tag_manager', 'google_tag_manager_id'], ''), 'gtm');

$bb_criar_gtag_loader = $bb_criar_google_analytics;
if (!function_exists('bb_domain_normalize')) {
  function bb_domain_normalize(string $host): string {
    $host = trim(strtolower($host));

    if ($host === '') {
      return '';
    }

    $host = preg_replace('~^https?://~i', '', $host);
    $host = preg_replace('~/.*$~', '', $host);
    $host = preg_replace('~:\d+$~', '', $host);
    $host = trim($host, ". \t\n\r\0\x0B");

    return $host;
  }
}

if (!function_exists('bb_domain_is_valid')) {
  function bb_domain_is_valid(string $host): bool {
    $host = bb_domain_normalize($host);

    if ($host === '' || strlen($host) > 253) {
      return false;
    }

    if (strpos($host, '.') === false) {
      return false;
    }

    if (!preg_match('/^[a-z0-9.-]+$/', $host)) {
      return false;
    }

    $parts = explode('.', $host);
    if (count($parts) < 2) {
      return false;
    }

    foreach ($parts as $p) {
      if ($p === '' || strlen($p) > 63) {
        return false;
      }
      if ($p[0] === '-' || substr($p, -1) === '-') {
        return false;
      }
    }

    $tld = end($parts);
    if ($tld === false || strlen($tld) < 2) {
      return false;
    }

    return true;
  }
}

if (isset($_GET['acao']) && $_GET['acao'] === 'verificar_cadastro') {
  header('Content-Type: application/json; charset=utf-8');

  $nome_estab = trim((string)($_POST['nome'] ?? ''));
  $email      = strtolower(trim((string)($_POST['dono_email'] ?? '')));
  $whatsapp   = trim((string)($_POST['whatsapp'] ?? ''));
  $dominio    = bb_domain_normalize((string)($_POST['dominio_personalizado'] ?? ''));

  $ret = ['ok' => true, 'msg' => ''];

  if ($email === '') {
    $ret['ok'] = false;
    $ret['msg'] = 'O email é obrigatório.';
    echo json_encode($ret);
    exit();
  }

  if ($whatsapp === '') {
    $ret['ok'] = false;
    $ret['msg'] = 'O WhatsApp é obrigatório.';
    echo json_encode($ret);
    exit();
  }

  if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $ret['ok'] = false;
    $ret['msg'] = 'Email inválido. Verifique e tente novamente.';
    echo json_encode($ret);
    exit();
  }

  if ($dominio !== '' && !bb_domain_is_valid($dominio)) {
    $ret['ok'] = false;
    $ret['msg'] = 'Domínio próprio inválido. Informe somente o host, ex: seunome.com.br';
    echo json_encode($ret);
    exit();
  }

  $wpp_digits = preg_replace('/\D+/', '', $whatsapp);
  if ($wpp_digits === '' || (strlen($wpp_digits) !== 10 && strlen($wpp_digits) !== 11)) {
    $ret['ok'] = false;
    $ret['msg'] = 'WhatsApp inválido. Informe DDD + número.';
    echo json_encode($ret);
    exit();
  }

  if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo json_encode($ret);
    exit();
  }

  if (!function_exists('bb_tabela_existe')) {
    function bb_tabela_existe(PDO $pdo, string $tabela): bool {
      $sql = "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema = DATABASE() AND table_name = :tabela";
      $st = $pdo->prepare($sql);
      $st->execute([':tabela' => $tabela]);
      return (int)$st->fetchColumn() > 0;
    }
  }

  if (!function_exists('bb_coluna_existe')) {
    function bb_coluna_existe(PDO $pdo, string $tabela, string $coluna): bool {
      $sql = "SELECT COUNT(*) FROM information_schema.columns WHERE table_schema = DATABASE() AND table_name = :tabela AND column_name = :coluna";
      $st = $pdo->prepare($sql);
      $st->execute([':tabela' => $tabela, ':coluna' => $coluna]);
      return (int)$st->fetchColumn() > 0;
    }
  }

  if (!function_exists('bb_ident_ok')) {
    function bb_ident_ok(string $ident): bool {
      return (bool)preg_match('/^[a-zA-Z0-9_]+$/', $ident);
    }
  }

  if (!function_exists('bb_lower')) {
    function bb_lower(string $texto): string {
      if (function_exists('mb_strtolower')) {
        return mb_strtolower($texto, 'UTF-8');
      }
      return strtolower($texto);
    }
  }

  $tabela_check = null;
  $col_nome  = null;
  $col_email = null;
  $col_wpp   = null;

  if (bb_tabela_existe($pdo, 'clientes')) {
    if (bb_coluna_existe($pdo, 'clientes', 'nome')) {
      $col_nome = 'nome';
    }

    if (bb_coluna_existe($pdo, 'clientes', 'email')) {
      $col_email = 'email';
    } elseif (bb_coluna_existe($pdo, 'clientes', 'usuario')) {
      $col_email = 'usuario';
    } elseif (bb_coluna_existe($pdo, 'clientes', 'login')) {
      $col_email = 'login';
    }

    if (bb_coluna_existe($pdo, 'clientes', 'whatsapp')) {
      $col_wpp = 'whatsapp';
    } elseif (bb_coluna_existe($pdo, 'clientes', 'telefone')) {
      $col_wpp = 'telefone';
    } elseif (bb_coluna_existe($pdo, 'clientes', 'celular')) {
      $col_wpp = 'celular';
    }

    if ($col_nome || $col_email || $col_wpp) {
      $tabela_check = 'clientes';
    }
  }

  if ($tabela_check === null) {
    $cols_email = ['email', 'usuario', 'login'];
    $cols_nome  = ['nome', 'name', 'dono_nome'];
    $cols_wpp   = ['whatsapp', 'telefone', 'celular', 'fone', 'phone'];

    $lista_cols = array_unique(array_merge($cols_email, $cols_nome, $cols_wpp, ['senha', 'password']));
    $placeholders = implode(',', array_fill(0, count($lista_cols), '?'));

    $sql = "SELECT table_name, column_name
            FROM information_schema.columns
            WHERE table_schema = DATABASE() AND column_name IN ($placeholders)";
    $st = $pdo->prepare($sql);
    $st->execute($lista_cols);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);

    $map = [];
    foreach ($rows as $r) {
      $t = $r['table_name'];
      $c = $r['column_name'];
      if (!isset($map[$t])) {
        $map[$t] = [];
      }
      $map[$t][$c] = true;
    }

    $bestTable = null;
    $bestScore = -1;
    $bestCols  = ['nome' => null, 'email' => null, 'wpp' => null];

    foreach ($map as $t => $cols) {
      $emailCol = null;
      foreach ($cols_email as $c) {
        if (isset($cols[$c])) {
          $emailCol = $c;
          break;
        }
      }
      if ($emailCol === null) {
        continue;
      }

      $nomeCol = null;
      foreach ($cols_nome as $c) {
        if (isset($cols[$c])) {
          $nomeCol = $c;
          break;
        }
      }

      $wppCol = null;
      foreach ($cols_wpp as $c) {
        if (isset($cols[$c])) {
          $wppCol = $c;
          break;
        }
      }

      $score = 0;
      $score += 4;
      if ($nomeCol !== null) {
        $score += 2;
      }
      if ($wppCol !== null) {
        $score += 3;
      }
      if (isset($cols['senha']) || isset($cols['password'])) {
        $score += 2;
      }

      if ($score > $bestScore) {
        $bestScore = $score;
        $bestTable = $t;
        $bestCols  = ['nome' => $nomeCol, 'email' => $emailCol, 'wpp' => $wppCol];
      }
    }

    if ($bestTable !== null) {
      $tabela_check = $bestTable;
      $col_nome     = $bestCols['nome'];
      $col_email    = $bestCols['email'];
      $col_wpp      = $bestCols['wpp'];
    }
  }

  if ($tabela_check === null || !bb_ident_ok($tabela_check)) {
    if ($dominio !== '' && bb_tabela_existe($pdo, 'tenant_domains')) {
      $stDom = $pdo->prepare("SELECT id FROM tenant_domains WHERE LOWER(TRIM(host)) = LOWER(TRIM(:host)) LIMIT 1");
      $stDom->execute([':host' => $dominio]);
      if ($stDom->fetch()) {
        $ret['ok'] = false;
        $ret['msg'] = 'Este domínio próprio já está em uso.';
      }
    }
    echo json_encode($ret);
    exit();
  }

  if ($col_nome !== null && !bb_ident_ok($col_nome)) {
    $col_nome = null;
  }
  if ($col_email !== null && !bb_ident_ok($col_email)) {
    $col_email = null;
  }
  if ($col_wpp !== null && !bb_ident_ok($col_wpp)) {
    $col_wpp = null;
  }

  $where  = [];
  $params = [];

  if ($col_email !== null) {
    $where[] = "LOWER(`{$col_email}`) = :email";
    $params[':email'] = $email;
  }

  if ($col_wpp !== null) {
    $wpp_expr = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`{$col_wpp}`,'+',''),'(',''),')',''),'-',''),' ',''),'.','')";
    $where[] = "(`{$col_wpp}` = :wpp_raw OR {$wpp_expr} = :wpp_digits)";
    $params[':wpp_raw'] = $whatsapp;
    $params[':wpp_digits'] = $wpp_digits;
  }

  if ($col_nome !== null && $nome_estab !== '') {
    $where[] = "LOWER(`{$col_nome}`) = :nome";
    $params[':nome'] = bb_lower($nome_estab);
  }

  if (count($where) > 0) {
    $sql = "SELECT * FROM `{$tabela_check}` WHERE " . implode(" OR ", $where) . " LIMIT 1";
    $st = $pdo->prepare($sql);
    $st->execute($params);
    $row = $st->fetch(PDO::FETCH_ASSOC);

    if ($row) {
      $duplicados = [];

      if ($col_email !== null && isset($row[$col_email])) {
        $dbEmail = strtolower(trim((string)$row[$col_email]));
        if ($dbEmail === $email) {
          $duplicados[] = "email";
        }
      }

      if ($col_wpp !== null && isset($row[$col_wpp])) {
        $dbWppRaw = trim((string)$row[$col_wpp]);
        $dbWppDig = preg_replace('/\D+/', '', $dbWppRaw);
        if ($dbWppRaw === $whatsapp || $dbWppDig === $wpp_digits) {
          $duplicados[] = "whatsapp";
        }
      }

      if ($col_nome !== null && $nome_estab !== '' && isset($row[$col_nome])) {
        $dbNome = bb_lower(trim((string)$row[$col_nome]));
        if ($dbNome === bb_lower($nome_estab)) {
          $duplicados[] = "nome";
        }
      }

      if (count($duplicados) > 0) {
        $ret['ok'] = false;

        $msgs = [];
        if (in_array('nome', $duplicados, true)) {
          $msgs[] = "Já existe um cadastro com este nome.";
        }
        if (in_array('email', $duplicados, true)) {
          $msgs[] = "Já existe um cadastro com este email.";
        }
        if (in_array('whatsapp', $duplicados, true)) {
          $msgs[] = "Já existe um cadastro com este WhatsApp.";
        }

        $ret['msg'] = implode(" ", $msgs) . " Não é permitido repetir.";
        echo json_encode($ret);
        exit();
      }
    }
  }

  if ($dominio !== '' && bb_tabela_existe($pdo, 'tenant_domains')) {
    $stDom = $pdo->prepare("SELECT id FROM tenant_domains WHERE LOWER(TRIM(host)) = LOWER(TRIM(:host)) LIMIT 1");
    $stDom->execute([':host' => $dominio]);
    if ($stDom->fetch()) {
      $ret['ok'] = false;
      $ret['msg'] = 'Este domínio próprio já está em uso.';
      echo json_encode($ret);
      exit();
    }
  }

  echo json_encode($ret);
  exit();
}
?>
<!doctype html>
<html lang="pt-br">
<head>

  <?php if ($bb_criar_google_verification !== '') : ?>
    <meta name="google-site-verification" content="<?php echo htmlspecialchars($bb_criar_google_verification, ENT_QUOTES, 'UTF-8'); ?>">
  <?php endif; ?>

  <?php if ($bb_criar_google_tag_manager !== '') : ?>
    <script>
      (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?php echo htmlspecialchars($bb_criar_google_tag_manager, ENT_QUOTES, 'UTF-8'); ?>');
    </script>
  <?php endif; ?>

  <?php if ($bb_criar_gtag_loader !== '') : ?>
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo htmlspecialchars($bb_criar_gtag_loader, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      <?php if ($bb_criar_google_analytics !== '') : ?>
        gtag('config', '<?php echo htmlspecialchars($bb_criar_google_analytics, ENT_QUOTES, 'UTF-8'); ?>');
      <?php endif; ?>
    </script>
  <?php endif; ?>

<meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?php echo htmlspecialchars($nome_sistema, ENT_QUOTES, 'UTF-8'); ?> - Criar Loja</title>
  <link rel="shortcut icon" href="<?php echo htmlspecialchars($caminho_icone, ENT_QUOTES, 'UTF-8'); ?>" type="image/x-icon">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
  <style>
    :root {
      --primary-blue: #3b82f6;
      --primary-hover: #2563eb;
      --bg-dark: #020617;
      --card-bg: #1e293b;
      --input-bg: #0f172a;
      --border-color: #334155;
      --text-white: #f8fafc;
      --text-gray: #94a3b8;
      --text-info: #93c5fd;
    }
    * { margin: 0; padding: 0; box-sizing: border-box; font-family: 'Inter', sans-serif; }
    body {
      background-color: var(--bg-dark);
      color: var(--text-white);
      min-height: 100vh;
      display: flex;
      align-items: center;
      justify-content: center;
      padding: 20px;
    }
    .wrap { width: 100%; max-width: 650px; animation: fadeIn 0.5s ease-in-out; }
    .header-title { text-align: center; margin-bottom: 30px; }
    .brand-logo { height: 70px; width: auto; display: inline-block; margin-bottom: 14px; filter: drop-shadow(0 10px 25px rgba(0,0,0,.35)); }
    .header-title h2 { font-size: 1.8rem; font-weight: 700; color: var(--text-white); }
    .header-title p { color: var(--text-gray); font-size: 0.95rem; margin-top: 5px; }
    .card { background: var(--card-bg); border: 1px solid var(--border-color); border-radius: 16px; padding: 30px; box-shadow: 0 10px 30px rgba(0,0,0,0.3); }
    .form-group { margin-bottom: 15px; }
    label { display: block; margin-bottom: 8px; font-size: 0.85rem; font-weight: 600; color: #cbd5e1; }
    input { width: 100%; padding: 12px 16px; border-radius: 8px; border: 1px solid var(--border-color); background: var(--input-bg); color: var(--text-white); font-size: 0.95rem; transition: 0.3s; }
    input:focus { border-color: var(--primary-blue); outline: none; box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.2); }
    .row { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; margin-bottom: 5px; }
    @media (max-width: 600px) { .row { grid-template-columns: 1fr; gap: 0; } }
    .field-help {
      display: block;
      margin-top: 6px;
      color: var(--text-info);
      font-size: 0.78rem;
      line-height: 1.4;
    }
    .divider { margin: 25px 0; border-top: 1px solid var(--border-color); position: relative; }
    .divider span { position: absolute; top: -10px; left: 50%; transform: translateX(-50%); background: var(--card-bg); padding: 0 10px; color: var(--text-gray); font-size: 0.85rem; font-weight: 600; text-transform: uppercase; letter-spacing: 1px; }
    .terms-group {
      display: flex;
      align-items: flex-start;
      gap: 10px;
      margin-top: 20px;
      background: rgba(15, 23, 42, 0.5);
      padding: 15px;
      border-radius: 10px;
      border: 1px dashed var(--border-color);
    }
    .terms-group input[type="checkbox"] { width: 18px; height: 18px; cursor: pointer; margin-top: 2px; }
    .terms-label { font-size: 0.85rem; color: var(--text-gray); line-height: 1.4; }
    .terms-link { color: var(--primary-blue); text-decoration: none; font-weight: 600; cursor: pointer; }
    .terms-link:hover { text-decoration: underline; }
    .btn { display: block; width: 100%; padding: 14px; border-radius: 50px; background: var(--primary-blue); color: white; font-weight: 700; border: none; cursor: pointer; font-size: 1rem; transition: 0.3s; text-transform: uppercase; margin-top: 20px; }
    .btn:hover { background: var(--primary-hover); transform: translateY(-2px); box-shadow: 0 5px 15px rgba(37, 99, 235, 0.3); }
    .modal { display: none; position: fixed; z-index: 999; left: 0; top: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.8); backdrop-filter: blur(4px); align-items: center; justify-content: center; }
    .modal-content { background: var(--card-bg); width: 90%; max-width: 550px; border-radius: 16px; border: 1px solid var(--border-color); display: flex; flex-direction: column; max-height: 80vh; }
    .modal-header { padding: 20px; border-bottom: 1px solid var(--border-color); display: flex; justify-content: space-between; align-items: center; }
    .modal-body { padding: 20px; overflow-y: auto; font-size: 0.9rem; color: #cbd5e1; line-height: 1.6; }
    .modal-footer { padding: 15px; border-top: 1px solid var(--border-color); text-align: right; }
    .close-modal { color: var(--text-gray); font-size: 1.5rem; cursor: pointer; }
    @keyframes fadeIn { from { opacity: 0; transform: translateY(10px); } to { opacity: 1; transform: translateY(0); } }
    .back-link {
      text-align: center;
      margin-top: 30px;
      padding-bottom: 20px;
    }
    .back-link a {
      color: var(--text-gray);
      font-size: 0.95rem;
      text-decoration: none;
      transition: all 0.3s ease;
      display: inline-flex;
      align-items: center;
      gap: 10px;
      padding: 10px 20px;
      border-radius: 50px;
      background: rgba(30, 41, 59, 0.5);
      border: 1px solid var(--border-color);
    }
    .back-link a:hover {
      color: var(--text-white);
      background: var(--card-bg);
      border-color: var(--primary-blue);
      transform: translateX(-5px);
    }
  </style>
</head>
<body>
  <?php if ($bb_criar_google_tag_manager !== '') : ?>
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo htmlspecialchars($bb_criar_google_tag_manager, ENT_QUOTES, 'UTF-8'); ?>" height="0" width="0" style="display:none;visibility:hidden" title="Google Tag Manager"></iframe></noscript>
  <?php endif; ?>
<div class="wrap">
    <div class="header-title">
      <img src="<?php echo htmlspecialchars($caminho_logo, ENT_QUOTES, 'UTF-8'); ?>" class="brand-logo">
      <h2>Criar novo estabelecimento</h2>
      <p>Barbearias, Cabeleireiros, Manicures e Estéticas</p>
    </div>

    <div class="card">
      <form method="post" action="<?php echo htmlspecialchars($action_provisionar, ENT_QUOTES, 'UTF-8'); ?>" id="formCriar">
        <div class="row">
          <div class="form-group">
            <label><i class="fa-solid fa-shop"></i> Nome do Estabelecimento</label>
            <input type="text" name="nome" id="nome" placeholder="Ex: Studio de Beleza" required>
          </div>
          <div class="form-group">
            <label><i class="fa-solid fa-link"></i> Link (Slug)</label>
            <input type="text" name="slug" id="slug" placeholder="ex: meu-studio" required>
          </div>
        </div>

        <div class="form-group">
          <label><i class="fa-solid fa-globe"></i> Domínio Próprio (opcional)</label>
          <input type="text" name="dominio_personalizado" id="dominio_personalizado" placeholder="Ex: seunome.com.br">
          <small class="field-help">Preencha apenas se já tiver domínio próprio. Deixe em branco para usar o subdomínio padrão do sistema.</small>
        </div>

        <div class="row">
          <div class="form-group">
            <label>Cidade</label>
            <input type="text" name="cidade" id="cidade" placeholder="Ex: Cascavel">
          </div>
          <div class="form-group">
            <label>Estado</label>
            <input type="text" name="estado" id="estado" placeholder="Ex: PR" maxlength="2">
          </div>
        </div>

        <div class="form-group">
          <label><i class="fa-brands fa-whatsapp"></i> WhatsApp</label>
          <input type="text" name="whatsapp" id="whatsapp" placeholder="(45) 99999-9999" inputmode="numeric" autocomplete="tel" required>
        </div>

        <div class="divider"><span>Dados de Acesso</span></div>

        <div class="row">
          <div class="form-group">
            <label><i class="fa-solid fa-user"></i> Nome do Dono</label>
            <input type="text" name="dono_nome" id="dono_nome" required>
          </div>
          <div class="form-group">
            <label><i class="fa-solid fa-envelope"></i> Email de Login</label>
            <input type="email" name="dono_email" id="dono_email" autocomplete="email" required>
          </div>
        </div>

        <div class="row">
          <div class="form-group">
            <label><i class="fa-solid fa-lock"></i> Senha</label>
            <input type="password" name="dono_senha" id="dono_senha" minlength="6" required>
          </div>
          <div class="form-group">
            <label><i class="fa-solid fa-check-double"></i> Confirmar</label>
            <input type="password" name="dono_senha2" id="dono_senha2" minlength="6" required>
          </div>
        </div>

        <div class="terms-group">
          <input type="checkbox" id="aceite_termos" name="aceite_termos" required>
          <div class="terms-label">
            <i class="fa-solid fa-shield-halved" style="color: var(--primary-blue); margin-right: 5px;"></i>
            Eu li e concordo com os <span class="terms-link" onclick="openModal()">Termos de Uso e Política de Privacidade</span> do sistema <?php echo htmlspecialchars($nome_sistema, ENT_QUOTES, 'UTF-8'); ?>.
          </div>
        </div>

        <button class="btn" type="submit" id="btnCriar">
          <i class="fa-solid fa-rocket"></i> Criar Agora
        </button>
      </form>
    </div>

    <div class="back-link">
      <a href="<?php echo htmlspecialchars($link_marketplace, ENT_QUOTES, 'UTF-8'); ?>">
        <i class="fa-solid fa-circle-arrow-left"></i>
        Voltar ao marketplace e login
      </a>
    </div>
  </div>

  <div id="termsModal" class="modal">
    <div class="modal-content">
      <div class="modal-header">
        <h3 style="font-size: 1.1rem;"><i class="fa-solid fa-file-contract"></i> Termos de Uso</h3>
        <span class="close-modal" onclick="closeModal()">&times;</span>
      </div>
      <div class="modal-body">
        <strong>1. Aceitação dos Termos</strong><br>
        Ao utilizar o sistema <?php echo htmlspecialchars($nome_sistema, ENT_QUOTES, 'UTF-8'); ?>, você concorda com o gerenciamento de dados de agendamento e clientes conforme as leis vigentes (LGPD).<br><br>
        <strong>2. Responsabilidade</strong><br>
        O usuário é responsável pela veracidade das informações cadastradas e pela segurança de sua senha de acesso.<br><br>
        <strong>3. Serviços</strong><br>
        O software oferece ferramentas de gestão para Barbearias, Cabeleireiros e Estéticas, incluindo agendamento online e controle financeiro.
        <br><br>
        <em>* Este é um resumo, os termos completos podem ser configurados no painel administrativo.</em>
      </div>
      <div class="modal-footer">
        <button class="btn" style="width: auto; padding: 8px 25px; margin: 0;" onclick="closeModal()">Entendido</button>
      </div>
    </div>
  </div>

  <script>
    function openModal() {
      document.getElementById('termsModal').style.display = 'flex';
    }

    function closeModal() {
      document.getElementById('termsModal').style.display = 'none';
    }

    window.onclick = function(event) {
      if (event.target == document.getElementById('termsModal')) {
        closeModal();
      }
    };

    document.addEventListener('DOMContentLoaded', function() {
      var nome = document.getElementById('nome');
      var slug = document.getElementById('slug');
      var wpp = document.getElementById('whatsapp');
      var email = document.getElementById('dono_email');
      var dominio = document.getElementById('dominio_personalizado');
      var form = document.getElementById('formCriar');
      var __bb_validado = false;

      function bbSomenteDigitos(v) {
        return (v || '').toString().replace(/\D+/g, '');
      }

      function bbFormatarWhatsApp(v) {
        var dig = bbSomenteDigitos(v);

        if (dig.length > 11) {
          dig = dig.substring(0, 11);
        }

        if (dig.length === 0) {
          return '';
        }

        var ddd = dig.substring(0, 2);
        var num = dig.substring(2);
        var saida = '';

        if (ddd.length < 2) {
          saida = '(' + ddd;
          return saida;
        }

        saida = '(' + ddd + ') ';

        if (num.length === 0) {
          return saida;
        }

        if (num.length <= 4) {
          saida += num;
          return saida;
        }

        if (num.length <= 8) {
          saida += num.substring(0, 4) + '-' + num.substring(4);
          return saida;
        }

        saida += num.substring(0, 5) + '-' + num.substring(5, 9);
        return saida;
      }

      function bbAplicarMascaraWhatsApp(el) {
        if (!el) {
          return;
        }

        el.addEventListener('input', function() {
          el.value = bbFormatarWhatsApp(el.value);
        });

        el.addEventListener('paste', function() {
          setTimeout(function() {
            el.value = bbFormatarWhatsApp(el.value);
          }, 0);
        });

        el.addEventListener('blur', function() {
          el.value = bbFormatarWhatsApp(el.value);
        });

        el.value = bbFormatarWhatsApp(el.value);
      }

      function bbValidarEmail(valor) {
        var v = (valor || '').trim();
        if (v === '') {
          return false;
        }
        return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v);
      }

      function bbValidarWhatsApp(valor) {
        var dig = bbSomenteDigitos(valor);
        return dig.length === 10 || dig.length === 11;
      }

      function bbNormalizarDominio(valor) {
        var v = (valor || '').trim().toLowerCase();
        v = v.replace(/^https?:\/\//i, '');
        v = v.replace(/\/.*$/g, '');
        v = v.replace(/:\d+$/g, '');
        v = v.replace(/^\.+|\.+$/g, '');
        return v;
      }

      function bbValidarDominio(valor) {
        if (!valor) {
          return true;
        }

        if (valor.indexOf('.') === -1) {
          return false;
        }

        if (!/^[a-z0-9.-]+$/i.test(valor)) {
          return false;
        }

        var partes = valor.split('.');
        if (partes.length < 2) {
          return false;
        }

        for (var i = 0; i < partes.length; i++) {
          var p = partes[i];
          if (!p || p.length > 63) {
            return false;
          }
          if (p.charAt(0) === '-' || p.charAt(p.length - 1) === '-') {
            return false;
          }
        }

        return partes[partes.length - 1].length >= 2;
      }

      bbAplicarMascaraWhatsApp(wpp);

      if (dominio) {
        dominio.addEventListener('blur', function() {
          dominio.value = bbNormalizarDominio(dominio.value);
        });
      }

      nome.addEventListener('input', function() {
        slug.value = nome.value.toLowerCase().trim()
          .normalize('NFD').replace(/[\u0300-\u036f]/g, '')
          .replace(/[^a-z0-9\s-]/g, '')
          .replace(/\s+/g, '-');
      });

      form.addEventListener('submit', function(e) {
        if (__bb_validado) {
          return;
        }

        var p1 = document.getElementById('dono_senha').value;
        var p2 = document.getElementById('dono_senha2').value;
        var emailValor = email ? email.value.trim() : '';
        var wppDig = wpp ? bbSomenteDigitos(wpp.value) : '';
        var dominioValor = dominio ? bbNormalizarDominio(dominio.value) : '';

        if (p1 !== p2) {
          e.preventDefault();
          alert('As senhas não conferem!');
          return;
        }

        if (emailValor === '') {
          e.preventDefault();
          alert('O email é obrigatório.');
          return;
        }

        if (!bbValidarEmail(emailValor)) {
          e.preventDefault();
          alert('Informe um email válido.');
          return;
        }

        if (wppDig === '') {
          e.preventDefault();
          alert('O WhatsApp é obrigatório.');
          return;
        }

        if (!bbValidarWhatsApp(wppDig)) {
          e.preventDefault();
          alert('Informe um WhatsApp válido com DDD.');
          return;
        }

        if (dominioValor !== '' && !bbValidarDominio(dominioValor)) {
          e.preventDefault();
          alert('Informe um domínio próprio válido. Ex: darosbarber.com.br');
          return;
        }

        e.preventDefault();

        var fd = new FormData(form);
        fd.set('whatsapp', wppDig);
        fd.set('dono_email', emailValor);
        fd.set('dominio_personalizado', dominioValor);

        var urlCheck = '<?php echo htmlspecialchars($_SERVER["PHP_SELF"], ENT_QUOTES, "UTF-8"); ?>?acao=verificar_cadastro';

        fetch(urlCheck, {
          method: 'POST',
          body: fd,
          credentials: 'same-origin'
        })
        .then(function(resp) {
          return resp.json();
        })
        .then(function(json) {
          if (json && json.ok) {
            __bb_validado = true;

            if (wpp) {
              wpp.value = wppDig;
            }

            if (email) {
              email.value = emailValor;
            }

            if (dominio) {
              dominio.value = dominioValor;
            }

            form.submit();
            return;
          }

          var msg = (json && json.msg) ? json.msg : 'Não foi possível validar o cadastro. Tente novamente.';
          alert(msg);
        })
        .catch(function() {
          alert('Erro ao validar cadastro. Verifique sua conexão e tente novamente.');
        });
      });
    });
  </script>
</body>
</html>
