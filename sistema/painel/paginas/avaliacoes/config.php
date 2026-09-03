<?php
require_once __DIR__ . '/../_context_bootstrap.php';
// ===== Contexto e sessão isolada por acesso (MASTER x TENANT) =====
// Regra aplicada neste módulo:
// - /sistema (sem /loja/ e sem ?loja=) => MASTER
// - /loja/<slug>/... OU ?loja=...      => TENANT
// - demais                              => SITE

if (!function_exists('bb_avaliacoes_clean_slug')) {
  function bb_avaliacoes_clean_slug($v) {
    $v = strtolower(trim((string)$v));
    return preg_replace('/[^a-z0-9_-]/', '', $v);
  }
}

if (!function_exists('bb_avaliacoes_path')) {
  function bb_avaliacoes_path($url) {
    return parse_url((string)$url, PHP_URL_PATH) ?: '';
  }
}

if (!function_exists('bb_avaliacoes_slug_from_path')) {
  function bb_avaliacoes_slug_from_path($path) {
    if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', (string)$path, $m)) {
      return bb_avaliacoes_clean_slug($m[1]);
    }
    return '';
  }
}

$__bb_reqPath = bb_avaliacoes_path($_SERVER['REQUEST_URI'] ?? '/');
$__bb_refPath = ''; // Referer não define contexto.

$__bb_reqSlug = '';
if (!empty($_POST['loja'])) {
  $__bb_reqSlug = bb_avaliacoes_clean_slug($_POST['loja']);
} elseif (!empty($_GET['loja'])) {
  $__bb_reqSlug = bb_avaliacoes_clean_slug($_GET['loja']);
} else {
  $__bb_reqSlug = bb_avaliacoes_slug_from_path($__bb_reqPath);
  if ($__bb_reqSlug === '' && $__bb_refPath !== '') {
    $__bb_reqSlug = bb_avaliacoes_slug_from_path($__bb_refPath);
  }
}

$__bb_lojaByPath = (bb_avaliacoes_slug_from_path($__bb_reqPath) !== '') || (bb_avaliacoes_slug_from_path($__bb_refPath) !== '');
$__bb_hasLoja    = ($__bb_reqSlug !== '') || $__bb_lojaByPath;
$__bb_isSistema  = (strpos($__bb_reqPath, '/sistema') === 0) || (strpos($__bb_refPath, '/sistema') === 0);
$__bb_forceMaster = ($__bb_isSistema && !$__bb_hasLoja);

$bb_avaliacoes_contexto = $__bb_forceMaster ? 'master' : ($__bb_hasLoja ? 'tenant' : 'site');
$bb_avaliacoes_loja_slug = $__bb_reqSlug;

if (!defined('BB_AVALIACOES_CONTEXTO')) {
  define('BB_AVALIACOES_CONTEXTO', $bb_avaliacoes_contexto);
}
if (!defined('BB_AVALIACOES_LOJA_SLUG')) {
  define('BB_AVALIACOES_LOJA_SLUG', $bb_avaliacoes_loja_slug);
}
if (!defined('BB_AVALIACOES_MASTER')) {
  define('BB_AVALIACOES_MASTER', $bb_avaliacoes_contexto === 'master');
}
if (!defined('BB_AVALIACOES_TENANT')) {
  define('BB_AVALIACOES_TENANT', $bb_avaliacoes_contexto === 'tenant');
}

// Variáveis globais deixadas disponíveis para o conexao.php, caso o ecossistema já use contexto dinâmico.
$GLOBALS['bb_avaliacoes_contexto'] = $bb_avaliacoes_contexto;
$GLOBALS['bb_avaliacoes_loja_slug'] = $bb_avaliacoes_loja_slug;

$__bb_tenantSessionName = 'BB_TENANT';
if ($bb_avaliacoes_loja_slug !== '') {
  $__bb_tenantSessionName = 'BB_TENANT_' . strtoupper(substr(sha1($bb_avaliacoes_loja_slug), 0, 12));
}

$__bb_desiredSession = ($bb_avaliacoes_contexto === 'master') ? 'BB_MASTER' : (($bb_avaliacoes_contexto === 'tenant') ? $__bb_tenantSessionName : 'BB_SITE');

// Inicia/ajusta a sessão corretamente antes de carregar a conexão.
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

require_once __DIR__ . '/../../../conexao.php';

/*
 Tabela de configs simples:
 CREATE TABLE IF NOT EXISTS config_site (
   id INT AUTO_INCREMENT PRIMARY KEY,
   chave VARCHAR(60) UNIQUE,
   valor TEXT
 ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
*/

if (!function_exists('bb_avaliacoes_config_site_ensure')) {
  function bb_avaliacoes_config_site_ensure($pdo) {
    static $ok = false;
    if ($ok) {
      return;
    }

    $pdo->exec("CREATE TABLE IF NOT EXISTS config_site (
      id INT AUTO_INCREMENT PRIMARY KEY,
      chave VARCHAR(60) NOT NULL UNIQUE,
      valor TEXT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

    $ok = true;
  }
}

if (!function_exists('getVal')) {
  function getVal($pdo, $k) {
    bb_avaliacoes_config_site_ensure($pdo);
    $st = $pdo->prepare("SELECT valor FROM config_site WHERE chave = :k");
    $st->execute([':k' => $k]);
    $r = $st->fetch(PDO::FETCH_ASSOC);
    return $r ? $r['valor'] : '';
  }
}

if (!function_exists('setVal')) {
  function setVal($pdo, $k, $v) {
    bb_avaliacoes_config_site_ensure($pdo);
    $st = $pdo->prepare("INSERT INTO config_site (chave, valor) VALUES (:k, :v)
                         ON DUPLICATE KEY UPDATE valor = VALUES(valor)");
    $st->execute([':k' => $k, ':v' => $v]);
  }
}

// Só responde ações quando este arquivo for chamado diretamente.
// Quando for incluído por salvar.php/listar.php/salvar_link.php, não pode imprimir "Ação inválida".
$__bb_configDireto = isset($_SERVER['SCRIPT_FILENAME']) && realpath((string)$_SERVER['SCRIPT_FILENAME']) === realpath(__FILE__);
if ($__bb_configDireto) {
  $acao = $_POST['acao'] ?? '';

  if ($acao === 'get') {
    header('Content-Type: application/json; charset=UTF-8');
    $out = [
      'google_link' => getVal($pdo, 'google_review_link'),
      'place_id'    => getVal($pdo, 'google_place_id'),
      'api_key'     => getVal($pdo, 'google_api_key'),
    ];
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
    exit;
  }

  if ($acao === 'set') {
    $link = trim($_POST['google_link'] ?? '');
    $pid  = trim($_POST['place_id'] ?? '');
    $key  = trim($_POST['api_key'] ?? '');
    try {
      setVal($pdo, 'google_review_link', $link);
      setVal($pdo, 'google_place_id', $pid);
      setVal($pdo, 'google_api_key', $key);
      echo 'OK';
    } catch (Throwable $e) {
      error_log('avaliacoes/config.php erro: ' . $e->getMessage());
      echo 'Erro ao salvar configuração';
    }
    exit;
  }

  echo 'Ação inválida';
  exit;
}
