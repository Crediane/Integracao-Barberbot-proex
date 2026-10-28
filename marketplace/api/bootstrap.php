<?php
// /api/bootstrap.php

$config = require __DIR__ . '/config.php';
$GLOBALS['BB_API_CONFIG'] = is_array($config) ? $config : [];

require __DIR__ . '/lib/db.php';
require __DIR__ . '/lib/response.php';
require __DIR__ . '/tenant.php';

if (!function_exists('bb_api_emit_json_error')) {
  function bb_api_emit_json_error(string $mensagem, int $http = 403): void {
    $http = (int)$http;
    if ($http <= 0) {
      $http = 403;
    }

    if (function_exists('json_error')) {
      json_error($mensagem, $http);
    }

    http_response_code($http);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
      'ok' => false,
      'erro' => $mensagem,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }
}

if (!function_exists('bb_api_clean_host')) {
  function bb_api_clean_host(string $host): string {
    $host = strtolower(trim($host));
    if ($host === '') {
      return '';
    }

    $host = preg_replace('~^https?://~i', '', $host);
    $host = preg_replace('~/.*$~', '', $host);
    $host = preg_replace('~:\d+$~', '', $host);

    return trim($host, ". \t\n\r\0\x0B");
  }
}

if (!function_exists('bb_api_hosts_busca')) {
  function bb_api_hosts_busca(string $hostAtual): array {
    $hostAtual = bb_api_clean_host($hostAtual);
    if ($hostAtual === '') {
      return [];
    }

    $hosts = [$hostAtual];

    if (strpos($hostAtual, 'www.') === 0) {
      $hosts[] = substr($hostAtual, 4);
    } else {
      $hosts[] = 'www.' . $hostAtual;
    }

    $hosts = array_values(array_unique(array_filter(array_map('bb_api_clean_host', $hosts))));
    return $hosts;
  }
}

if (!function_exists('bb_api_pdo_connect')) {
  function bb_api_pdo_connect(string $host, string $db, string $user, string $pass): ?PDO {
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
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
          PDO::ATTR_EMULATE_PREPARES => false,
        ]
      );
    } catch (Throwable $e) {
      return null;
    }
  }
}

if (!function_exists('bb_api_table_exists')) {
  function bb_api_table_exists(PDO $pdo, string $tabela): bool {
    try {
      $st = $pdo->prepare("
        SELECT 1
          FROM INFORMATION_SCHEMA.TABLES
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :t
         LIMIT 1
      ");
      $st->execute([':t' => $tabela]);
      return (bool)$st->fetchColumn();
    } catch (Throwable $e) {
      return false;
    }
  }
}

if (!function_exists('bb_api_columns')) {
  function bb_api_columns(PDO $pdo, string $tabela): array {
    try {
      $st = $pdo->prepare("
        SELECT COLUMN_NAME
          FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :t
      ");
      $st->execute([':t' => $tabela]);

      $colunas = $st->fetchAll(PDO::FETCH_COLUMN);
      $mapa = [];

      if (is_array($colunas)) {
        foreach ($colunas as $coluna) {
          $mapa[(string)$coluna] = true;
        }
      }

      return $mapa;
    } catch (Throwable $e) {
      return [];
    }
  }
}

if (!function_exists('bb_api_marketplace_cfg')) {
  function bb_api_marketplace_cfg(): array {
    static $cfgCache = null;

    if (is_array($cfgCache)) {
      return $cfgCache;
    }

    $cfgArquivo = dirname(__DIR__) . '/config/marketplace.php';
    if (!is_file($cfgArquivo)) {
      $cfgCache = [];
      return $cfgCache;
    }

    $cfg = require $cfgArquivo;
    $cfgCache = is_array($cfg) ? $cfg : [];
    return $cfgCache;
  }
}

if (!function_exists('bb_api_try_decrypt_pass')) {
  function bb_api_try_decrypt_pass(string $senhaRaw, string $secret): string {
    if ($senhaRaw === '') {
      return '';
    }

    if ($secret === '') {
      return $senhaRaw;
    }

    if (strpos($senhaRaw, 'enc:') !== 0) {
      return $senhaRaw;
    }

    $arquivoCrypto = dirname(__DIR__) . '/sistema/tenant_crypto.php';
    if (is_file($arquivoCrypto)) {
      require_once $arquivoCrypto;
    }

    if (function_exists('bb_decrypt_str')) {
      $dec = (string)bb_decrypt_str($senhaRaw, $secret);
      if ($dec !== '') {
        return $dec;
      }
    }

    return $senhaRaw;
  }
}

if (!function_exists('bb_api_fetch_tenant_by_host')) {
  function bb_api_fetch_tenant_by_host(PDO $pdoMaster, string $hostAtual, string $tenantsTable): ?array {
    if (!bb_api_table_exists($pdoMaster, 'tenant_domains')) {
      return null;
    }

    $colsDom = bb_api_columns($pdoMaster, 'tenant_domains');
    $where = ["LOWER(TRIM(d.host)) = LOWER(TRIM(:host))"];

    if (!empty($colsDom['ativo'])) {
      $where[] = "(d.ativo = 1 OR d.ativo IS NULL)";
    }

    if (!empty($colsDom['principal'])) {
      $where[] = "(d.principal = 1 OR d.principal IS NULL)";
    }

    if (!empty($colsDom['tipo'])) {
      $where[] = "(d.tipo = 'dominio' OR d.tipo IS NULL OR d.tipo = '')";
    }

    $sql = "
      SELECT t.*, d.host AS dominio_personalizado
        FROM tenant_domains d
        INNER JOIN `{$tenantsTable}` t ON t.id = d.tenant_id
       WHERE " . implode(' AND ', $where) . "
       ORDER BY d.id DESC
       LIMIT 1
    ";

    $hostsBusca = bb_api_hosts_busca($hostAtual);
    if (empty($hostsBusca)) {
      return null;
    }

    try {
      $st = $pdoMaster->prepare($sql);

      foreach ($hostsBusca as $hostBusca) {
        $st->execute([':host' => $hostBusca]);
        $tenant = $st->fetch(PDO::FETCH_ASSOC);

        if (is_array($tenant)) {
          return $tenant;
        }
      }
    } catch (Throwable $e) {
      return null;
    }

    return null;
  }
}

if (!function_exists('bb_api_tenant_expirado')) {
  function bb_api_tenant_expirado(array $tenant): bool {
    $vencimento = trim((string)($tenant['vencimento'] ?? ''));
    if ($vencimento === '' || $vencimento === '0000-00-00') {
      return false;
    }

    return substr($vencimento, 0, 10) < date('Y-m-d');
  }
}

if (!function_exists('bb_api_tenant_bloqueado')) {
  function bb_api_tenant_bloqueado(array $tenant): bool {
    $status = strtolower(trim((string)($tenant['status'] ?? '')));
    if ($status === 'blocked') {
      return true;
    }

    if (isset($tenant['ativo']) && (string)$tenant['ativo'] !== '' && (int)$tenant['ativo'] !== 1) {
      return true;
    }

    return false;
  }
}

if (!function_exists('bb_api_sync_block_master')) {
  function bb_api_sync_block_master(PDO $pdoMaster, string $tenantsTable, array $tenant): void {
    $tenantId = (int)($tenant['id'] ?? 0);
    if ($tenantId <= 0) {
      return;
    }

    $cols = bb_api_columns($pdoMaster, $tenantsTable);
    $sets = [];

    if (!empty($cols['status'])) {
      $sets[] = "`status`='blocked'";
    }

    if (!empty($cols['ativo'])) {
      $sets[] = "`ativo`=0";
    }

    if (!empty($cols['blocked_at'])) {
      $sets[] = "`blocked_at`=NOW()";
    }

    if (empty($sets)) {
      return;
    }

    $sql = "UPDATE `{$tenantsTable}` SET " . implode(', ', $sets) . " WHERE id = :id LIMIT 1";

    try {
      $st = $pdoMaster->prepare($sql);
      $st->execute([':id' => $tenantId]);
    } catch (Throwable $e) {
    }
  }
}

if (!function_exists('bb_api_sync_block_tenant_db')) {
  function bb_api_sync_block_tenant_db(array $tenant, string $tenantSecret, string $hostDefault = 'localhost'): void {
    $dbHost = trim((string)($tenant['db_host'] ?? $hostDefault));
    $dbName = trim((string)($tenant['db_name'] ?? ''));
    $dbUser = trim((string)($tenant['db_user'] ?? ''));
    $dbPass = (string)($tenant['db_pass'] ?? '');

    if ($dbHost === '' || $dbName === '' || $dbUser === '') {
      return;
    }

    $dbPass = bb_api_try_decrypt_pass($dbPass, $tenantSecret);
    $pdoTenant = bb_api_pdo_connect($dbHost, $dbName, $dbUser, $dbPass);
    if (!$pdoTenant instanceof PDO) {
      return;
    }

    if (!bb_api_table_exists($pdoTenant, 'config')) {
      return;
    }

    $colsConfig = bb_api_columns($pdoTenant, 'config');
    if (empty($colsConfig['ativo'])) {
      return;
    }

    try {
      $pdoTenant->exec("UPDATE config SET ativo='Não'");
    } catch (Throwable $e) {
    }
  }
}

if (!function_exists('bb_api_mensagem_bloqueio')) {
  function bb_api_mensagem_bloqueio(array $tenant, bool $estaVencido): string {
    $vencimento = trim((string)($tenant['vencimento'] ?? ''));

    if ($estaVencido && $vencimento !== '' && $vencimento !== '0000-00-00') {
      $partes = explode('-', substr($vencimento, 0, 10));
      if (count($partes) === 3) {
        $vencimento = $partes[2] . '/' . $partes[1] . '/' . $partes[0];
      }

      return 'A assinatura venceu em ' . $vencimento . '. Regularize o pagamento para liberar novamente o acesso.';
    }

    if ($vencimento !== '' && $vencimento !== '0000-00-00') {
      $partes = explode('-', substr($vencimento, 0, 10));
      if (count($partes) === 3) {
        $vencimento = $partes[2] . '/' . $partes[1] . '/' . $partes[0];
      }

      return 'A loja está bloqueada. O vencimento registrado é ' . $vencimento . '. Regularize a assinatura para liberar novamente o acesso.';
    }

    return 'Acesso bloqueado para esta barbearia. Regularize a assinatura para liberar novamente o acesso.';
  }
}

if (!function_exists('bb_api_apply_marketplace_block')) {
  function bb_api_apply_marketplace_block(): void {
    if (PHP_SAPI === 'cli') {
      return;
    }

    if (!empty($GLOBALS['TENANT_BLOCKED'])) {
      return;
    }

    $cfg = bb_api_marketplace_cfg();
    $master = is_array($cfg['master'] ?? null) ? $cfg['master'] : [];
    $security = is_array($cfg['security'] ?? null) ? $cfg['security'] : [];

    if (empty($master['host']) || empty($master['db']) || empty($master['user'])) {
      return;
    }

    $hostAtual = bb_api_clean_host((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($hostAtual === '') {
      return;
    }

    $tenantsTable = preg_replace('/[^a-z0-9_]/i', '', (string)($master['table'] ?? 'tenants'));
    if ($tenantsTable === '') {
      $tenantsTable = 'tenants';
    }

    $pdoMaster = bb_api_pdo_connect(
      trim((string)($master['host'] ?? '')),
      trim((string)($master['db'] ?? '')),
      trim((string)($master['user'] ?? '')),
      (string)($master['pass'] ?? '')
    );

    if (!$pdoMaster instanceof PDO) {
      return;
    }

    $tenant = bb_api_fetch_tenant_by_host($pdoMaster, $hostAtual, $tenantsTable);
    if (!$tenant) {
      return;
    }

    $estaVencido = bb_api_tenant_expirado($tenant);
    $estaBloqueado = bb_api_tenant_bloqueado($tenant);

    if ($estaVencido) {
      bb_api_sync_block_master($pdoMaster, $tenantsTable, $tenant);
      bb_api_sync_block_tenant_db(
        $tenant,
        trim((string)($security['tenant_secret'] ?? '')),
        trim((string)($master['host'] ?? 'localhost'))
      );

      $tenant['status'] = 'blocked';
      $tenant['ativo'] = 0;
      $estaBloqueado = true;
    }

    if (!$estaBloqueado) {
      return;
    }

    $GLOBALS['TENANT_BLOCKED'] = true;
    $GLOBALS['TENANT_BLOCK_HTTP'] = 403;
    $GLOBALS['TENANT_BLOCK_REASON'] = bb_api_mensagem_bloqueio($tenant, $estaVencido);

    if (empty($GLOBALS['TENANT']) || !is_array($GLOBALS['TENANT'])) {
      $GLOBALS['TENANT'] = $tenant;
    }
  }
}

if (!function_exists('bb_api_abort_if_blocked')) {
  function bb_api_abort_if_blocked(): void {
    if (empty($GLOBALS['TENANT_BLOCKED'])) {
      return;
    }

    $mensagem = trim((string)($GLOBALS['TENANT_BLOCK_REASON'] ?? 'Acesso bloqueado para esta barbearia.'));
    $http = (int)($GLOBALS['TENANT_BLOCK_HTTP'] ?? 403);
    if ($http <= 0) {
      $http = 403;
    }

    bb_api_emit_json_error($mensagem, $http);
  }
}

// Bloqueio pró-ativo no bootstrap.
// Isso garante que a API do domínio próprio também pare,
// mesmo quando o endpoint esquecer de chamar require_tenant().
bb_api_apply_marketplace_block();
bb_api_abort_if_blocked();

// Atalhos
$TENANT = $GLOBALS['TENANT'] ?? null;
$pdo = $GLOBALS['PDO_TENANT'] ?? null;
$TENANT_STORAGE = $GLOBALS['TENANT_STORAGE'] ?? null;

// Se o endpoint exigir tenant, valide aqui:
// (para endpoints do marketplace, você permite $pdo == null)
function require_tenant(): void {
  bb_api_abort_if_blocked();

  if (!isset($GLOBALS['PDO_TENANT']) || !$GLOBALS['PDO_TENANT']) {
    bb_api_emit_json_error('Este endpoint exige uma barbearia (subdomínio) válida.', 400);
  }
}
