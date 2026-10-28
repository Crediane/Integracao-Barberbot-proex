<?php
// /public_html/sistema/cpanel_uapi.php
declare(strict_types=1);

function bb_cpanel_cfg(): ?array {
  // Primeiro tenta o config que você mostrou (cpanel_config.php)
  $f1 = __DIR__ . '/../config/cpanel_config.php';
  if (is_file($f1)) {
    $cfg = @include $f1;
    if (is_array($cfg)) {
      $user  = trim((string)($cfg['cpanel_user'] ?? ''));
      $token = trim((string)($cfg['cpanel_token'] ?? ''));
      $dom   = trim((string)($cfg['cpanel_domain'] ?? ''));
      $port  = (int)($cfg['cpanel_port'] ?? 2083);

      if ($user !== '' && $token !== '' && $dom !== '') {
        $host = 'https://' . $dom . ':' . $port; // ex: https://jc.tec.br:2083
        return ['host' => rtrim($host, '/'), 'user' => $user, 'token' => $token];
      }
    }
  }

  // Compatibilidade: se existir /config/cpanel.php (host/user/token)
  $f2 = __DIR__ . '/../config/cpanel.php';
  if (is_file($f2)) {
    $cfg = @include $f2;
    if (is_array($cfg)) {
      $host  = trim((string)($cfg['host'] ?? ''));
      $user  = trim((string)($cfg['user'] ?? ''));
      $token = trim((string)($cfg['token'] ?? ''));
      if ($host !== '' && $user !== '' && $token !== '') {
        return ['host' => rtrim($host, '/'), 'user' => $user, 'token' => $token];
      }
    }
  }

  return null;
}

function bb_cpanel_uapi(string $module, string $func, array $params = []): array {
  $cp = bb_cpanel_cfg();
  if (!$cp) return [false, 'Config do cPanel ausente/ inválido.', null];

  $url = $cp['host'] . '/execute/' . rawurlencode($module) . '/' . rawurlencode($func);
  if (!empty($params)) $url .= '?' . http_build_query($params);

  $ch = curl_init($url);
  curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
  curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
  curl_setopt($ch, CURLOPT_TIMEOUT, 30);
  curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Authorization: cpanel ' . $cp['user'] . ':' . $cp['token'],
  ]);

  $resp = curl_exec($ch);
  $err  = curl_error($ch);
  $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  if ($resp === false) return [false, 'Falha cURL: ' . $err, null];

  $json = json_decode($resp, true);
  if (!is_array($json)) return [false, 'Resposta inválida do cPanel (HTTP ' . $code . ').', $resp];

  $status = (int)($json['status'] ?? 0);
  if ($status !== 1) {
    $errors = $json['errors'] ?? null;
    $msg = 'Erro UAPI';
    if (is_array($errors) && !empty($errors)) $msg = (string)$errors[0];
    return [false, $msg, $json];
  }

  return [true, 'OK', $json];
}

function bb_cpanel_mysql_create_database(string $dbName): array {
  return bb_cpanel_uapi('Mysql', 'create_database', ['name' => $dbName]);
}

function bb_cpanel_mysql_create_user(string $dbUser, string $dbPass): array {
  return bb_cpanel_uapi('Mysql', 'create_user', ['name' => $dbUser, 'password' => $dbPass]);
}

function bb_cpanel_mysql_set_privileges(string $dbUser, string $dbName): array {
  return bb_cpanel_uapi('Mysql', 'set_privileges_on_database', [
    'user' => $dbUser,
    'database' => $dbName,
    'privileges' => 'ALL PRIVILEGES'
  ]);
}
