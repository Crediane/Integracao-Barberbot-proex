<?php
// /cadastrar.php
declare(strict_types=1);

// O contexto é definido somente pela requisição atual.
// HTTP_REFERER e cookies antigos não podem escolher a barbearia.
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

$__bb_hasLoja = ($__bb_reqSlug !== '');
$__bb_isSistema = (strpos($__bb_reqPath, '/sistema') === 0);
$__bb_desiredSession = ($__bb_isSistema && !$__bb_hasLoja)
  ? 'BB_MASTER'
  : ($__bb_hasLoja ? 'BB_TENANT_' . strtoupper(substr(sha1($__bb_reqSlug), 0, 12)) : 'BB_SITE');

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

if ($__bb_reqSlug !== '' && empty($_GET['loja'])) {
  $_GET['loja'] = $__bb_reqSlug;
}

header('Content-Type: text/plain; charset=UTF-8');
date_default_timezone_set('America/Sao_Paulo');

if (!function_exists('bb_cadastro_post_valor')) {
  function bb_cadastro_post_valor(array $dados, array $chaves): string {
    foreach ($chaves as $chave) {
      if (isset($dados[$chave]) && trim((string)$dados[$chave]) !== '') {
        return trim((string)$dados[$chave]);
      }
    }
    return '';
  }
}

if (!function_exists('bb_cadastro_qcol')) {
  function bb_cadastro_qcol(string $coluna): string {
    return '`' . str_replace('`', '``', $coluna) . '`';
  }
}

if (!function_exists('bb_cadastro_coluna_existente')) {
  function bb_cadastro_coluna_existente(array $colunas, array $candidatas): string {
    foreach ($candidatas as $coluna) {
      $chave = strtolower((string)$coluna);
      if (isset($colunas[$chave])) {
        return (string)$colunas[$chave];
      }
    }
    return '';
  }
}

if (!function_exists('bb_cadastro_normalizar_whatsapp_unico')) {
  function bb_cadastro_normalizar_whatsapp_unico(string $telefone): string {
    $digits = preg_replace('/\D+/', '', $telefone) ?: '';
    if ((strlen($digits) === 12 || strlen($digits) === 13) && substr($digits, 0, 2) === '55') {
      $digits = substr($digits, 2);
    }
    return $digits;
  }
}

if (!function_exists('bb_cadastro_ler_entrada')) {
  function bb_cadastro_ler_entrada(): array {
    $entrada = $_POST;
    if (!empty($entrada)) {
      return $entrada;
    }

    $raw = (string)file_get_contents('php://input');
    if ($raw === '') {
      return [];
    }

    $json = json_decode($raw, true);
    if (is_array($json)) {
      return $json;
    }

    parse_str($raw, $parseado);
    return is_array($parseado) ? $parseado : [];
  }
}

if (!function_exists('bb_cadastro_ler_marketplace_config')) {
  function bb_cadastro_ler_marketplace_config(): array {
    $arquivo = __DIR__ . '/config/marketplace.php';
    if (!is_file($arquivo)) {
      return [];
    }

    $cfg = require $arquivo;
    return is_array($cfg) ? $cfg : [];
  }
}

if (!function_exists('bb_cadastro_conectar_auth_master')) {
  function bb_cadastro_conectar_auth_master(array $cfg): ?PDO {
    if (empty($cfg['auth_master']) || !is_array($cfg['auth_master'])) {
      return null;
    }

    $auth = $cfg['auth_master'];
    $host = trim((string)($auth['host'] ?? 'localhost'));
    $db   = preg_replace('/[^a-z0-9_]/i', '', (string)($auth['db'] ?? 'jctecbr_master'));
    $user = trim((string)($auth['user'] ?? ''));
    $pass = (string)($auth['pass'] ?? '');

    if ($host === '' || $db === '' || $user === '') {
      return null;
    }

    try {
      return new PDO(
        'mysql:host=' . $host . ';dbname=' . $db . ';charset=utf8mb4',
        $user,
        $pass,
        [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
          PDO::ATTR_EMULATE_PREPARES => false,
        ]
      );
    } catch (Throwable $e) {
      return null;
    }
  }
}

if (!function_exists('bb_cadastro_banco_atual')) {
  function bb_cadastro_banco_atual(PDO $pdoCadastro): string {
    try {
      return trim((string)$pdoCadastro->query('SELECT DATABASE()')->fetchColumn());
    } catch (Throwable $e) {
      return '';
    }
  }
}

if (!function_exists('bb_cadastro_carregar_config')) {
  function bb_cadastro_carregar_config(PDO $pdoCadastro): array {
    try {
      $st = $pdoCadastro->query('SELECT * FROM `config` ORDER BY id DESC LIMIT 1');
      $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : false;
      return is_array($row) ? $row : [];
    } catch (Throwable $e) {
      return [];
    }
  }
}

if (!function_exists('bb_cadastro_listar_colunas')) {
  function bb_cadastro_listar_colunas(PDO $pdoCadastro, string $tabela): array {
    $colunas = [];
    $tabelaSegura = preg_replace('/[^a-z0-9_]/i', '', $tabela);
    if ($tabelaSegura === '') {
      return $colunas;
    }

    try {
      $stCols = $pdoCadastro->query('SHOW COLUMNS FROM `' . $tabelaSegura . '`');
      $cols = $stCols ? $stCols->fetchAll(PDO::FETCH_ASSOC) : [];
      foreach ($cols as $col) {
        if (!empty($col['Field'])) {
          $colunas[strtolower((string)$col['Field'])] = (string)$col['Field'];
        }
      }
    } catch (Throwable $e) {
      return [];
    }

    return $colunas;
  }
}

try {
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    // mantém 200 para o front não exibir erro de rede
    echo 'Método não permitido';
    exit;
  }

  $entrada = bb_cadastro_ler_entrada();

  // Campos
  $nome = bb_cadastro_post_valor($entrada, ['nome', 'nome_completo', 'cliente', 'nome_cliente']);
  $telefoneF = bb_cadastro_post_valor($entrada, ['telefone', 'telefone_whatsapp', 'whatsapp', 'celular', 'fone']);

  if ($nome === '' || $telefoneF === '') {
    echo 'Preencha nome e telefone';
    exit;
  }

  $cfgMarketplace = bb_cadastro_ler_marketplace_config();

  /*
    Cadastro da HOME PRINCIPAL:
    - Não depende do banco conectado pelo /sistema/conexao.php.
    - Usa diretamente config/marketplace.php > auth_master.
    - Salva no banco master do sistema: jctecbr_master.clientes.
    Cadastro em /loja/{slug}/:
    - Mantém o fluxo existente do tenant, usando /sistema/conexao.php.
  */
  $pdoCadastro = null;
  $configCadastro = [];
  $url_sistema = '';
  $nome_sistema = 'BarberBot Software';
  $api = '';
  $token = '';
  $instancia = '';

  if ($__bb_reqSlug === '') {
    $pdoCadastro = bb_cadastro_conectar_auth_master($cfgMarketplace);

    if (!$pdoCadastro || !($pdoCadastro instanceof PDO)) {
      echo 'Erro ao conectar no banco de dados';
      exit;
    }

    $dbMasterEsperado = preg_replace('/[^a-z0-9_]/i', '', (string)($cfgMarketplace['auth_master']['db'] ?? ''));
    $dbMasterAtual = bb_cadastro_banco_atual($pdoCadastro);
    if ($dbMasterEsperado === '' || $dbMasterAtual === '' || strcasecmp($dbMasterAtual, $dbMasterEsperado) !== 0) {
      echo 'Falha de isolamento do banco administrativo';
      exit;
    }

    $configCadastro = bb_cadastro_carregar_config($pdoCadastro);
    if (!empty($configCadastro)) {
      $nome_sistema = trim((string)($configCadastro['nome'] ?? $nome_sistema));
      $api = trim((string)($configCadastro['api'] ?? $api));
      $token = trim((string)($configCadastro['token'] ?? $token));
      $instancia = trim((string)($configCadastro['instancia'] ?? $instancia));
    }

    if (!empty($cfgMarketplace['app']['url_sistema'])) {
      $url_sistema = rtrim((string)$cfgMarketplace['app']['url_sistema'], '/');
    } elseif (!empty($cfgMarketplace['base_url'])) {
      $url_sistema = rtrim((string)$cfgMarketplace['base_url'], '/');
    }
  } else {
    require_once __DIR__ . '/sistema/conexao.php';

    if (!isset($pdo) || !($pdo instanceof PDO)) {
      echo 'Erro ao conectar no banco de dados';
      exit;
    }

    $slugSessao = bb_sess_clean_slug($_SESSION['tenant_slug'] ?? '');
    $dbTenantEsperado = trim((string)($_SESSION['tenant_db'] ?? ''));
    $dbTenantAtual = bb_cadastro_banco_atual($pdo);

    if (
      strpos((string)session_name(), 'BB_TENANT') !== 0
      || $slugSessao === ''
      || $slugSessao !== $__bb_reqSlug
      || $dbTenantEsperado === ''
      || $dbTenantAtual === ''
      || strcasecmp($dbTenantAtual, $dbTenantEsperado) !== 0
    ) {
      echo 'Falha de isolamento da barbearia';
      exit;
    }

    $pdoCadastro = $pdo;

    $nome_sistema = (string)($GLOBALS['nome_sistema'] ?? $nome_sistema);
    $url_sistema = (string)($GLOBALS['url_sistema'] ?? '');
    $api = (string)($GLOBALS['api'] ?? '');
    $token = (string)($GLOBALS['token'] ?? '');
    $instancia = (string)($GLOBALS['instancia'] ?? '');
  }

  /* ================= reCAPTCHA v3 (soft) =================
     - Se existir config e a verificação falhar E você marcar como obrigatória,
       bloqueia. Caso contrário, segue o fluxo.                         */
  $rc_token  = (string)($entrada['recaptcha_token']  ?? '');
  $rc_action = (string)($entrada['recaptcha_action'] ?? 'cadastro');

  $recaptcha_ok = true; // padrão: seguir cadastro
  $recaptcha_required = false; // mude para true para tornar obrigatório

  $recaptchaCfg = __DIR__ . '/config/recaptcha.php';
  if (is_file($recaptchaCfg)) {
    require_once $recaptchaCfg; // pode definir recaptcha_verify()
    if (function_exists('recaptcha_verify')) {
      $rc = recaptcha_verify($rc_token, $rc_action); // ['ok'=>bool,'score'=>float,...]
      $recaptcha_ok = !empty($rc['ok']);
    }
    // se no seu config existir uma constante que force obrigatoriedade, respeita
    if (defined('RECAPTCHA_REQUIRED') && RECAPTCHA_REQUIRED === true) {
      $recaptcha_required = true;
    }
  }

  if ($recaptcha_required && !$recaptcha_ok) {
    echo 'Falha no reCAPTCHA';
    exit;
  }
  /* ================= fim reCAPTCHA ================= */

  $tabelaClientes = 'clientes';
  $colunasClientes = bb_cadastro_listar_colunas($pdoCadastro, $tabelaClientes);

  if (empty($colunasClientes)) {
    echo 'Tabela clientes não encontrada';
    exit;
  }

  $colNome = bb_cadastro_coluna_existente($colunasClientes, ['nome', 'nome_completo', 'cliente', 'nome_cliente']);
  $colTelefone = bb_cadastro_coluna_existente($colunasClientes, ['telefone', 'telefone_whatsapp', 'whatsapp', 'celular', 'fone']);

  if ($colNome === '' || $colTelefone === '') {
    echo 'Tabela clientes sem coluna de nome ou telefone';
    exit;
  }

  // Normalizações
  $telefoneDigits = preg_replace('/\D+/', '', $telefoneF) ?: ''; // só dígitos para comparar
  $telefoneBanco  = $telefoneF;                                  // salva como veio

  // WhatsApp único: ignora máscara e também prefixo 55, para não repetir o mesmo número
  $telefoneDigitsUnico = bb_cadastro_normalizar_whatsapp_unico($telefoneF);

  if ($telefoneDigitsUnico === '') {
    echo 'Preencha nome e telefone';
    exit;
  }

  // ===== Link de acesso no padrão do sistema / marketplace =====
  // Loja individual no marketplace: /loja/{slug}/sistema/acesso
  // Master / banco individual:      /sistema/acesso
  $httpsOn = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') ||
    (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
  );

  $hostAtual = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
  $baseUrl = rtrim((string)$url_sistema, '/');

  if ($hostAtual !== '') {
    $baseUrl = ($httpsOn ? 'https://' : 'http://') . $hostAtual;
  }

  if ($__bb_reqSlug !== '') {
    $linkAcesso = rtrim($baseUrl, '/') . '/loja/' . $__bb_reqSlug . '/sistema/acesso';
  } else {
    $linkAcesso = rtrim($baseUrl, '/') . '/sistema/acesso';
  }

  // Verifica duplicidade do WhatsApp ignorando máscara e prefixo 55
  $telColSql = bb_cadastro_qcol($colTelefone);
  $sqlTelefoneNormalizado = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$telColSql}, ''),'(',''),')',''),'-',''),' ',''),'+',''),'.',''),'/','')";

  $sqlDup = "
    SELECT id
    FROM `{$tabelaClientes}`
    WHERE (
      CASE
        WHEN LEFT({$sqlTelefoneNormalizado}, 2) = '55' AND CHAR_LENGTH({$sqlTelefoneNormalizado}) IN (12, 13)
          THEN SUBSTRING({$sqlTelefoneNormalizado}, 3)
        ELSE {$sqlTelefoneNormalizado}
      END
    ) = :tel
    LIMIT 1
  ";
  $st = $pdoCadastro->prepare($sqlDup);
  $st->bindValue(':tel', $telefoneDigitsUnico);
  $st->execute();

  if ($st->fetch(PDO::FETCH_ASSOC)) {
    echo 'Você já está Cadastrado';
    exit;
  }

  // Inserir novo cliente respeitando as colunas existentes no banco atual.
  $senha      = '123';
  $senha_crip = password_hash($senha, PASSWORD_DEFAULT);

  $campos = [];
  $valores = [];
  $params = [];

  $addCampo = function(string $coluna, string $placeholder, $valor) use (&$campos, &$valores, &$params) {
    if ($coluna === '') {
      return;
    }

    $campos[] = bb_cadastro_qcol($coluna);
    $valores[] = $placeholder;
    $params[$placeholder] = $valor;
  };

  $addCampo($colNome, ':nome', $nome);
  $addCampo($colTelefone, ':telefone', $telefoneBanco);

  $colDataCad = bb_cadastro_coluna_existente($colunasClientes, ['data_cad', 'data_cadastro', 'dt_cadastro', 'criado_em']);
  if ($colDataCad !== '') {
    $campos[] = bb_cadastro_qcol($colDataCad);
    $valores[] = (strtolower($colDataCad) === 'criado_em') ? 'NOW()' : 'CURDATE()';
  }

  $colDataNasc = bb_cadastro_coluna_existente($colunasClientes, ['data_nasc']);
  if ($colDataNasc !== '') {
    $addCampo($colDataNasc, ':data_nasc', '0000-00-00');
  }

  $colCartoes = bb_cadastro_coluna_existente($colunasClientes, ['cartoes']);
  if ($colCartoes !== '') {
    $addCampo($colCartoes, ':cartoes', '0');
  }

  $colUltimoServico = bb_cadastro_coluna_existente($colunasClientes, ['ultimo_servico']);
  if ($colUltimoServico !== '') {
    $addCampo($colUltimoServico, ':ultimo_servico', '0');
  }

  $colAlertado = bb_cadastro_coluna_existente($colunasClientes, ['alertado']);
  if ($colAlertado !== '') {
    $addCampo($colAlertado, ':alertado', 'Não');
  }

  $colMarketing = bb_cadastro_coluna_existente($colunasClientes, ['marketing']);
  if ($colMarketing !== '') {
    $addCampo($colMarketing, ':marketing', 'Sim');
  }

  $colSenhaCrip = bb_cadastro_coluna_existente($colunasClientes, ['senha_crip', 'senha_hash', 'senha_criptografada']);
  if ($colSenhaCrip !== '') {
    $addCampo($colSenhaCrip, ':senha_crip', $senha_crip);
  }

  $colSenha = bb_cadastro_coluna_existente($colunasClientes, ['senha']);
  if ($colSenha !== '') {
    $addCampo($colSenha, ':senha', $senha);
  }

  $sqlInsert = 'INSERT INTO `' . $tabelaClientes . '` (' . implode(', ', $campos) . ') VALUES (' . implode(', ', $valores) . ')';
  $ins = $pdoCadastro->prepare($sqlInsert);
  foreach ($params as $placeholder => $valor) {
    $ins->bindValue($placeholder, $valor);
  }
  $ins->execute();

  /* ===== Envio de WhatsApp (usa ajax/api-texto.php) ===== */
  $telefone = $telefoneDigits;
  if ($telefone !== '' && strlen($telefone) <= 11) {
    $telefone = '55' . $telefone;
  }

  $ns = $nome_sistema !== '' ? $nome_sistema : 'BarberBot Software';
  $mensagem  = "👋 *Olá {$nome}*, seja bem-vindo(a) ao *{$ns}*!\n\n";
  $mensagem .= "🔒 *Use seu WhatsApp e senha de acesso:* 123\n\n";
  $mensagem .= "🌐 *Clique abaixo para acessar seu painel:*\n";
  $mensagem .= $linkAcesso;

  $sender = $_SERVER['DOCUMENT_ROOT'] . '/ajax/api-texto.php';
  if (is_file($sender)) {
    // esse arquivo geralmente utiliza $telefone, $mensagem, $api, $token e $instancia existentes no escopo
    require $sender;
  }
  /* ===== fim envio ===== */

  echo 'Cadastrado com Sucesso';
  exit;

} catch (Throwable $e) {
  // Não expõe detalhes internos do banco para o visitante.
  echo 'Erro ao cadastrar. Tente novamente.';
  exit;
}
