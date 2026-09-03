<?php
// /api/admin_create_tenant.php
require __DIR__ . '/bootstrap.php';

// Só aceita POST
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
  json_error('Método inválido. Use POST.', 405);
}

// Lê POST normal e também JSON (caso você envie via fetch com JSON)
$input = $_POST;
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if (stripos($contentType, 'application/json') !== false) {
  $raw = file_get_contents('php://input');
  $json = json_decode($raw, true);
  if (is_array($json)) $input = $json;
}

// Segurança (token)
$token = (string)($input['token'] ?? '');
$cfgToken = (string)($config['admin']['token'] ?? '');

if ($cfgToken === '') {
  json_error('Não autorizado.', 401);
}

// Suporta token em texto puro OU hash bcrypt (password_hash)
$info = password_get_info($cfgToken);
$ok = false;
if (!empty($info['algo'])) {
  $ok = password_verify($token, $cfgToken);
} else {
  $ok = hash_equals($cfgToken, $token);
}

if (!$ok) {
  json_error('Não autorizado.', 401);
}

// Campos
$name   = trim((string)($input['name'] ?? ''));
$slug   = trim((string)($input['slug'] ?? ''));
$dbName = trim((string)($input['db_name'] ?? ''));
$dbUser = trim((string)($input['db_user'] ?? ''));
$dbPass = (string)($input['db_pass'] ?? '');
$dbHost = trim((string)($input['db_host'] ?? 'localhost'));
$host   = trim((string)($input['host'] ?? '')); // domínio custom opcional

if ($name === '' || $slug === '' || $dbName === '' || $dbUser === '' || $dbPass === '') {
  json_error('Campos obrigatórios: name, slug, db_name, db_user, db_pass.', 400);
}

// Validações básicas do slug
if (!preg_match('/^[a-z0-9][a-z0-9\-]{1,78}[a-z0-9]$/', $slug)) {
  json_error('Slug inválido. Use apenas letras minúsculas, números e hífen (2 a 80 chars).', 400);
}

try {
  // Conecta no banco MASTER
  $pdoMaster = db_pdo($config['master_db']);

  // Evita duplicado por slug/host
  $st = $pdoMaster->prepare("SELECT id FROM tenants WHERE slug = :slug LIMIT 1");
  $st->execute([':slug' => $slug]);
  if ($st->fetch()) json_error('Slug já existe.', 409);

  if ($host !== '') {
    $st = $pdoMaster->prepare("SELECT id FROM tenants WHERE host = :host LIMIT 1");
    $st->execute([':host' => $host]);
    if ($st->fetch()) json_error('Host (domínio custom) já existe.', 409);
  }

  // Insere tenant
  $st = $pdoMaster->prepare("
    INSERT INTO tenants (slug, name, host, status, db_host, db_name, db_user, db_pass)
    VALUES (:slug, :name, :host, 'active', :db_host, :db_name, :db_user, :db_pass)
  ");

  $st->execute([
    ':slug'    => $slug,
    ':name'    => $name,
    ':host'    => ($host !== '' ? $host : null),
    ':db_host' => $dbHost,
    ':db_name' => $dbName,
    ':db_user' => $dbUser,
    ':db_pass' => $dbPass,
  ]);

  json_response([
    'ok' => true,
    'tenant_id' => (int)$pdoMaster->lastInsertId(),
    'slug' => $slug,
    'name' => $name
  ]);

} catch (Throwable $e) {
  json_error('Erro ao criar tenant.', 500);
}
