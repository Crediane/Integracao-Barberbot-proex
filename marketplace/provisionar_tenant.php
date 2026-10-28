<?php
// /public_html/provisionar_tenant.php
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

// Endpoint exclusivo do fluxo MASTER/Marketplace.
// Mantém a sessão global para preservar os dados temporários usados em
// finalizar_tenant.php e nunca reaproveita sessão de uma barbearia.
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

require_once __DIR__ . '/sistema/tenant_crypto.php';

function go(string $to): void {
	header('Location: ' . $to);
	exit;
}

function clean_slug(string $v): string {
	$v = strtolower(trim($v));
	$v = preg_replace('/[^a-z0-9_-]/', '', $v);
	return (string)$v;
}

function clean_text(string $v): string {
	$v = trim($v);
	$v = preg_replace('/\s+/', ' ', $v);
	return (string)$v;
}

function clean_host(string $v): string {
	$v = strtolower(trim($v));
	if ($v === '') return '';
	$v = preg_replace('~^https?://~i', '', $v);
	$v = preg_replace('~/.*$~', '', $v);
	$v = preg_replace('~:\\d+$~', '', $v);
	$v = trim($v, ". \t\n\r\0\x0B");
	return (string)$v;
}

function valid_host(string $host): bool {
	$host = clean_host($host);
	if ($host === '' || strlen($host) > 253) return false;
	if (strpos($host, '.') === false) return false;
	if (!preg_match('/^[a-z0-9.-]+$/', $host)) return false;

	$parts = explode('.', $host);
	if (count($parts) < 2) return false;

	foreach ($parts as $p) {
		if ($p === '' || strlen($p) > 63) return false;
		if ($p[0] === '-' || substr($p, -1) === '-') return false;
	}

	$tld = end($parts);
	if ($tld === false || strlen($tld) < 2) return false;

	return true;
}

function table_exists_any(PDO $pdo, string $table): bool {
	try {
		$st = $pdo->prepare("
			SELECT 1
			  FROM INFORMATION_SCHEMA.TABLES
			 WHERE TABLE_SCHEMA = DATABASE()
			   AND TABLE_NAME = :t
			 LIMIT 1
		");
		$st->execute([':t' => $table]);
		return (bool)$st->fetchColumn();
	} catch (Throwable $e) {
		return false;
	}
}

function table_cols_any(PDO $pdo, string $table): array {
	try {
		$st = $pdo->prepare("
			SELECT COLUMN_NAME
			  FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = DATABASE()
			   AND TABLE_NAME = :t
		");
		$st->execute([':t' => $table]);
		$cols = $st->fetchAll(PDO::FETCH_COLUMN);
		return is_array($cols) ? array_values($cols) : [];
	} catch (Throwable $e) {
		return [];
	}
}

function insert_tenant_domain(PDO $pdoMaster, int $tenantId, string $host): void {
	$host = clean_host($host);
	if ($tenantId <= 0 || $host === '') return;

	if (!table_exists_any($pdoMaster, 'tenant_domains')) {
		throw new RuntimeException('Tabela tenant_domains não encontrada no banco master.');
	}

	$cols = table_cols_any($pdoMaster, 'tenant_domains');
	$has = function(string $c) use ($cols): bool {
		return in_array($c, $cols, true);
	};

	$stDup = $pdoMaster->prepare("SELECT id FROM tenant_domains WHERE LOWER(TRIM(host)) = LOWER(TRIM(?)) LIMIT 1");
	$stDup->execute([$host]);
	$dupId = (int)($stDup->fetchColumn() ?: 0);
	if ($dupId > 0) {
		throw new RuntimeException('Este domínio próprio já está cadastrado em outro tenant.');
	}

	$fields = [];
	$values = [];
	$params = [];

	if ($has('tenant_id')) { $fields[] = 'tenant_id'; $values[] = ':tenant_id'; $params[':tenant_id'] = $tenantId; }
	if ($has('host'))      { $fields[] = 'host';      $values[] = ':host';      $params[':host'] = $host; }
	if ($has('tipo'))      { $fields[] = 'tipo';      $values[] = ':tipo';      $params[':tipo'] = 'dominio'; }
	if ($has('principal')) { $fields[] = 'principal'; $values[] = ':principal'; $params[':principal'] = 1; }
	if ($has('ativo'))     { $fields[] = 'ativo';     $values[] = ':ativo';     $params[':ativo'] = 1; }
	if ($has('created_at')) { $fields[] = 'created_at'; $values[] = 'NOW()'; }
	if ($has('updated_at')) { $fields[] = 'updated_at'; $values[] = 'NOW()'; }

	if (empty($fields) || !in_array('tenant_id', $fields, true) || !in_array('host', $fields, true)) {
		throw new RuntimeException('Estrutura da tabela tenant_domains incompatível.');
	}

	$sql = "INSERT INTO tenant_domains (" . implode(',', $fields) . ") VALUES (" . implode(',', $values) . ")";
	$st = $pdoMaster->prepare($sql);
	$st->execute($params);
}

function only_digits(string $v): string {
	return (string)preg_replace('/\D+/', '', $v);
}

function validar_whatsapp_br(string $v): bool {
	$d = only_digits($v);

	if ($d === '') {
		return false;
	}

	// com DDI 55
	if (strpos($d, '55') === 0 && (strlen($d) === 12 || strlen($d) === 13)) {
		$d = substr($d, 2);
	}

	return strlen($d) === 10 || strlen($d) === 11;
}

function pdo_connect(string $host, string $db, string $user, string $pass): PDO {
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

function encrypt_or_plain(string $value, string $secret): string {
	if ($value === '') return '';
	if ($secret === '') return $value;
	if (!function_exists('bb_encrypt_str')) return $value;
	$enc = bb_encrypt_str($value, $secret);
	return ($enc !== '') ? $enc : $value;
}

/* =========================
   DISPARO WHATSAPP (SAFE)
========================= */

function format_phone55(string $raw): string {
	$d = only_digits($raw);
	if ($d === '') return '';
	if (strpos($d, '55') === 0) return $d;
	if (strlen($d) === 10 || strlen($d) === 11) return '55' . $d;
	return $d;
}

function format_phone_br(string $raw): string {
	$d = only_digits($raw);
	if ($d === '') return '';

	if (strpos($d, '55') === 0 && (strlen($d) === 12 || strlen($d) === 13)) {
		$d = substr($d, 2);
	}

	if (strlen($d) === 11) {
		return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 5) . '-' . substr($d, 7, 4);
	}

	if (strlen($d) === 10) {
		return '(' . substr($d, 0, 2) . ') ' . substr($d, 2, 4) . '-' . substr($d, 6, 4);
	}

	return $d;
}

function normalizar_base_url(string $baseUrl): string {
	$baseUrl = trim($baseUrl);
	if ($baseUrl === '') {
		$proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
		if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
			$proto = $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ? 'https' : $proto;
		}
		$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
		$baseUrl = $proto . '://' . $host;
	}
	return rtrim($baseUrl, '/');
}

function montar_link_loja(string $baseUrl, string $slug, string $rota = ''): string {
	$baseUrl = normalizar_base_url($baseUrl);
	$slug = clean_slug($slug);
	$rota = trim($rota, '/');

	$link = $baseUrl . '/loja/' . rawurlencode($slug);
	if ($rota !== '') {
		$link .= '/' . $rota;
	}
	return rtrim($link, '/') . '/';
}

function montar_link_master(string $baseUrl, string $rota = ''): string {
	$baseUrl = normalizar_base_url($baseUrl);
	$rota = trim($rota, '/');

	// Link Master/Admin isolado: /sistema/ conecta no banco global/administrativo.
	$link = $baseUrl . '/sistema';
	if ($rota !== '' && $rota !== 'sistema') {
		$link .= '/' . $rota;
	}
	return rtrim($link, '/') . '/';
}

function montar_link_dominio(string $host, string $rota = ''): string {
	$host = clean_host($host);
	if ($host === '') {
		return '';
	}

	$rota = trim($rota, '/');
	$link = 'https://' . $host;

	if ($rota !== '') {
		$link .= '/' . $rota;
	}

	return $link;
}

function detect_admin_db(array $cfg, string $mkDb): string {
	if (isset($cfg['auth_master']['db']) && is_string($cfg['auth_master']['db']) && $cfg['auth_master']['db'] !== '') {
		return $cfg['auth_master']['db'];
	}
	if (isset($cfg['admin']['db']) && is_string($cfg['admin']['db']) && $cfg['admin']['db'] !== '') {
		return $cfg['admin']['db'];
	}
	if (isset($cfg['master_admin_db']) && is_string($cfg['master_admin_db']) && $cfg['master_admin_db'] !== '') {
		return $cfg['master_admin_db'];
	}
	if (substr($mkDb, -7) === '_market') {
		return substr($mkDb, 0, -7) . '_master';
	}
	return $mkDb;
}

function admin_fetch_notify_config(PDO $pdoAdmin): array {
	$out = ['api' => '', 'token' => '', 'instancia' => ''];

	try {
		$st = $pdoAdmin->query("SHOW TABLES LIKE 'config'");
		$has = (bool)$st->fetchColumn();
		if (!$has) return $out;

		$row = $pdoAdmin->query("SELECT * FROM config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
		if (!$row) return $out;

		$out['api']       = (string)($row['api'] ?? '');
		$out['token']     = (string)($row['token'] ?? '');
		$out['instancia'] = (string)($row['instancia'] ?? '');

		if ($out['api'] === '') {
			$out['api'] = (string)($row['api_whatsapp'] ?? '');
		}
		if ($out['api'] === '') {
			$out['api'] = 'menuia';
		}

		if ($out['token'] === '') {
			$out['token'] = (string)($row['token_whatsapp'] ?? '');
		}
		if ($out['token'] === '') {
			$out['token'] = (string)($row['appkey'] ?? ($row['instance'] ?? ''));
		}

		if ($out['instancia'] === '') {
			$out['instancia'] = (string)($row['instancia_whatsapp'] ?? '');
		}
		if ($out['instancia'] === '') {
			$out['instancia'] = (string)($row['authkey'] ?? '');
		}

		return $out;
	} catch (Throwable $e) {
		return $out;
	}
}

function admin_fetch_phone(PDO $pdoAdmin): string {
	try {
		$st = $pdoAdmin->query("SHOW TABLES LIKE 'usuarios'");
		$has = (bool)$st->fetchColumn();
		if ($has) {
			$sql = "SELECT telefone FROM usuarios
					WHERE ativo='Sim' AND (nivel='Administrador' OR nivel LIKE '%Admin%')
					ORDER BY id ASC LIMIT 1";
			$tel = (string)($pdoAdmin->query($sql)->fetchColumn() ?: '');

			if ($tel === '') {
				$tel = (string)($pdoAdmin->query("SELECT telefone FROM usuarios ORDER BY id ASC LIMIT 1")->fetchColumn() ?: '');
			}

			$tel = format_phone55($tel);
			if ($tel !== '') return $tel;
		}
	} catch (Throwable $e) {
	}

	try {
		$st = $pdoAdmin->query("SHOW TABLES LIKE 'config'");
		$has = (bool)$st->fetchColumn();
		if (!$has) return '';

		$row = $pdoAdmin->query("SELECT telefone_whatsapp, telefone_fixo FROM config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
		if (!$row) return '';

		$tel = (string)($row['telefone_whatsapp'] ?? '');
		if ($tel === '') {
			$tel = (string)($row['telefone_fixo'] ?? '');
		}

		return format_phone55($tel);
	} catch (Throwable $e) {
		return '';
	}
}

function send_whatsapp_gateway(string $api, string $token, string $instancia, string $to55, string $msg): void {
	$api = strtolower(trim((string)$api));
	$token = trim((string)$token);
	$instancia = trim((string)$instancia);

	$telefone = preg_replace('/\D+/', '', (string)$to55);
	if ($telefone === '') return;
	if (substr($telefone, 0, 2) !== '55') $telefone = '55' . $telefone;

	if ($token === '' || $instancia === '') return;
	if ($api === '') $api = 'menuia';

	$msgN = (string)$msg;
	$msgN = str_replace(["\r\n", "\r"], "\n", $msgN);
	$msgN = str_replace("%0A", "\n", $msgN);
	$msgN = trim($msgN);

	if ($api === 'menuia') {
		if (!function_exists('curl_init')) {
			@error_log('[BarberBot WhatsApp] cURL indisponível para Menuia.');
			return;
		}

		$curl = curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_URL => 'https://chatbot.menuia.com/api/create-message',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 20,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => array(
				'appkey'   => $token,
				'authkey'  => $instancia,
				'to'       => $telefone,
				'message'  => $msgN,
				'licence'  => 'monielsistemas',
				'sandbox'  => 'false'
			),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);
		$http = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);

		if ($err) {
			@error_log('[BarberBot WhatsApp] Menuia erro: ' . $err);
		} elseif ($http >= 400) {
			@error_log('[BarberBot WhatsApp] Menuia HTTP ' . $http . ' - Resp: ' . (string)$response);
		}
		return;
	}

	if ($api === 'wm') {
		$url = "http://api.wordmensagens.com.br/send-text";
		$data = array(
			'instance' => $instancia,
			'to'       => $telefone,
			'token'    => $token,
			'message'  => $msgN
		);

		$options = array(
			'http' => array(
				'method'  => 'POST',
				'header'  => "Content-Type: application/x-www-form-urlencoded\r\n",
				'content' => http_build_query($data),
				'timeout' => 20
			)
		);

		$ctx = stream_context_create($options);
		$result = @file_get_contents($url, false, $ctx);

		if ($result === false) {
			@error_log('[BarberBot WhatsApp] WM falhou ao enviar (file_get_contents).');
		}
		return;
	}

	if ($api === 'newtek') {
		if (!function_exists('curl_init')) {
			@error_log('[BarberBot WhatsApp] cURL indisponível para Newtek.');
			return;
		}

		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL => 'https://webapi.newteksoft.com.br/enviar-texto',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING => '',
			CURLOPT_MAXREDIRS => 10,
			CURLOPT_TIMEOUT => 20,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST => 'POST',
			CURLOPT_POSTFIELDS => json_encode(array(
				"instancia" => $instancia,
				"token"     => $token,
				"mensagem"  => $msgN,
				"para"      => array($telefone),
				"delay"     => "1"
			)),
			CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
		));

		$response = curl_exec($curl);
		$err = curl_error($curl);
		$http = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);

		if ($err) {
			@error_log('[BarberBot WhatsApp] Newtek erro: ' . $err);
		} elseif ($http >= 400) {
			@error_log('[BarberBot WhatsApp] Newtek HTTP ' . $http . ' - Resp: ' . (string)$response);
		}
		return;
	}

	$mensagem = str_replace(["\r\n", "\r", "\n"], "%0A", (string)$msgN);

	$candidatos = [
		__DIR__ . '/ajax/api-texto.php',
		__DIR__ . '/webhook/api-texto.phpp',
		__DIR__ . '/pagamentos/api-texto.php',
		dirname(__DIR__) . '/ajax/api-texto.php',
	];

	$file = '';
	foreach ($candidatos as $c) {
		if (is_file($c)) {
			$file = $c;
			break;
		}
	}

	if ($file === '') {
		@error_log('[BarberBot WhatsApp] api-texto.php não encontrado (fallback).');
		return;
	}

	$api = $api;
	$token = $token;
	$instancia = $instancia;
	$telefone = $telefone;
	$response = '';
	$http = 0;

	require $file;
}

function cpanel_try_create_db_and_grant(string $dbName, string $dbUser): array {
	$cfg1 = __DIR__ . '/config/cpanel_config.php';
	$cfg2 = __DIR__ . '/config/cpanel.php';

	$cp = null;

	if (is_file($cfg1)) {
		$tmp = @include $cfg1;
		if (is_array($tmp) && !empty($tmp['cpanel_user']) && !empty($tmp['cpanel_token']) && !empty($tmp['cpanel_domain'])) {
			$cp = [
				'user' => (string)$tmp['cpanel_user'],
				'token' => (string)$tmp['cpanel_token'],
				'host' => 'https://' . (string)$tmp['cpanel_domain'] . ':2083',
			];
		}
	}

	if (!$cp && is_file($cfg2)) {
		$tmp = @include $cfg2;
		if (is_array($tmp) && !empty($tmp['user']) && !empty($tmp['token']) && !empty($tmp['host'])) {
			$cp = [
				'user' => (string)$tmp['user'],
				'token' => (string)$tmp['token'],
				'host' => rtrim((string)$tmp['host'], '/'),
			];
		}
	}

	if (!$cp) {
		return ['ok' => false, 'msg' => 'Sem config de cPanel (não tentei criar DB automaticamente).'];
	}

	$auth = 'Authorization: cpanel ' . $cp['user'] . ':' . $cp['token'];

	$uapi = function(string $module, string $func, array $params) use ($cp, $auth): array {
		$qs = http_build_query($params);
		$url = $cp['host'] . '/execute/' . rawurlencode($module) . '/' . rawurlencode($func) . '?' . $qs;

		$ch = curl_init($url);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, [$auth]);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, 20);

		$body = curl_exec($ch);
		$err  = curl_error($ch);
		$code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		curl_close($ch);

		if ($body === false) return ['ok' => false, 'code' => $code, 'error' => $err ?: 'Erro cURL'];
		$json = json_decode($body, true);
		return ['ok' => ($code >= 200 && $code < 300), 'code' => $code, 'json' => $json, 'raw' => $body];
	};

	$r1 = $uapi('Mysql', 'create_database', ['name' => $dbName]);

	$r2 = $uapi('Mysql', 'set_privileges_on_database', [
		'user' => $dbUser,
		'database' => $dbName,
		'privileges' => 'ALL PRIVILEGES',
	]);

	$ok = ($r2['ok'] === true);
	$msg = $ok ? 'DB ok (criado e com privilégios).' : 'Falha ao dar privilégios no DB via cPanel.';

	return ['ok' => $ok, 'msg' => $msg, 'r1' => $r1, 'r2' => $r2];
}

function table_cols(PDO $pdo, string $table): array {
	try {
		$st = $pdo->query("SHOW COLUMNS FROM `{$table}`");
		$cols = [];
		foreach ($st->fetchAll(PDO::FETCH_ASSOC) as $r) {
			if (!empty($r['Field'])) $cols[] = (string)$r['Field'];
		}
		return $cols;
	} catch (Throwable $e) {
		return [];
	}
}

function has_col(array $cols, string $c): bool {
	return in_array($c, $cols, true);
}

function upsert_master_user(PDO $pdoAdmin, string $nome, string $email, string $telefone, string $senhaPlain, string $senhaHash): void {
	try {
		$st = $pdoAdmin->query("SHOW TABLES LIKE 'usuarios'");
		$has = (bool)$st->fetchColumn();
		if (!$has) return;
	} catch (Throwable $e) {
		return;
	}

	$cols = table_cols($pdoAdmin, 'usuarios');
	if (!$cols) return;

	$sqlFind = "SELECT id FROM usuarios WHERE LOWER(TRIM(email)) = LOWER(TRIM(:email)) LIMIT 1";
	$q = $pdoAdmin->prepare($sqlFind);
	$q->execute([':email' => $email]);
	$id = (int)($q->fetchColumn() ?: 0);

	$nivel = 'Administrador';
	$ativo = 'Sim';
	$data  = date('Y-m-d');

	if ($id > 0) {
		$sets = [];
		$p = [':id' => $id, ':email' => $email];

		if (has_col($cols, 'nome'))       { $sets[] = "nome=:nome"; $p[':nome'] = $nome; }
		if (has_col($cols, 'telefone'))   { $sets[] = "telefone=:tel"; $p[':tel'] = $telefone; }
		if (has_col($cols, 'nivel'))      { $sets[] = "nivel=:nivel"; $p[':nivel'] = $nivel; }
		if (has_col($cols, 'ativo'))      { $sets[] = "ativo=:ativo"; $p[':ativo'] = $ativo; }
		if (has_col($cols, 'senha'))      { $sets[] = "senha=:senha"; $p[':senha'] = $senhaPlain; }
		if (has_col($cols, 'senha_crip')) { $sets[] = "senha_crip=:hash"; $p[':hash'] = $senhaHash; }

		if (!$sets) return;

		$sqlUp = "UPDATE usuarios SET " . implode(',', $sets) . " WHERE id=:id";
		$u = $pdoAdmin->prepare($sqlUp);
		$u->execute($p);
		return;
	}

	$fields = [];
	$vals   = [];
	$p      = [];

	if (has_col($cols, 'nome'))       { $fields[]='nome';       $vals[]=':nome';  $p[':nome']=$nome; }
	if (has_col($cols, 'email'))      { $fields[]='email';      $vals[]=':email'; $p[':email']=$email; }
	if (has_col($cols, 'telefone'))   { $fields[]='telefone';   $vals[]=':tel';   $p[':tel']=$telefone; }
	if (has_col($cols, 'nivel'))      { $fields[]='nivel';      $vals[]=':nivel'; $p[':nivel']=$nivel; }
	if (has_col($cols, 'ativo'))      { $fields[]='ativo';      $vals[]=':ativo'; $p[':ativo']=$ativo; }
	if (has_col($cols, 'senha'))      { $fields[]='senha';      $vals[]=':senha'; $p[':senha']=$senhaPlain; }
	if (has_col($cols, 'senha_crip')) { $fields[]='senha_crip'; $vals[]=':hash';  $p[':hash']=$senhaHash; }
	if (has_col($cols, 'data'))       { $fields[]='data';       $vals[]=':data';  $p[':data']=$data; }

	if (!$fields) return;

	$sqlIns = "INSERT INTO usuarios (" . implode(',', $fields) . ") VALUES (" . implode(',', $vals) . ")";
	$i = $pdoAdmin->prepare($sqlIns);
	$i->execute($p);
}

// ---------------------
// 1) Só POST
// ---------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
	go('/criar-barbearia.php');
}

// ---------------------
// 2) Carrega config marketplace
// ---------------------
$cfg = @include __DIR__ . '/config/marketplace.php';
if (!is_array($cfg) || empty($cfg['master']) || empty($cfg['tenant'])) {
	http_response_code(500);
	exit('Config do marketplace ausente.');
}

$mkHost = (string)($cfg['master']['host'] ?? 'localhost');
$mkDb   = (string)($cfg['master']['db'] ?? '');
$mkUser = (string)($cfg['master']['user'] ?? '');
$mkPass = (string)($cfg['master']['pass'] ?? '');
$tenantsTable = (string)($cfg['master']['table'] ?? 'tenants');

$authHost = (string)($cfg['auth_master']['host'] ?? $mkHost);
$authDb   = (string)($cfg['auth_master']['db'] ?? '');
$authUser = (string)($cfg['auth_master']['user'] ?? $mkUser);
$authPass = (string)($cfg['auth_master']['pass'] ?? $mkPass);

$tenantHost = (string)($cfg['tenant']['db_host'] ?? ($cfg['tenant']['host'] ?? 'localhost'));
$tenantUser = (string)($cfg['tenant']['user'] ?? '');
$tenantPass = (string)($cfg['tenant']['pass'] ?? '');

$cpPrefix   = (string)($cfg['tenant']['cpanel_prefix'] ?? '');
$dbPrefix   = (string)($cfg['tenant']['db_prefix'] ?? '');

$secret = (string)($cfg['security']['tenant_secret'] ?? '');

$sync_master_user = false;
if (isset($cfg['compat']) && is_array($cfg['compat']) && array_key_exists('sync_master_user', $cfg['compat'])) {
	$sync_master_user = (bool)$cfg['compat']['sync_master_user'];
}

$tenantsTable = preg_replace('/[^a-z0-9_]/i', '', $tenantsTable);
if ($tenantsTable === '') {
	$tenantsTable = 'tenants';
}

if ($mkDb === '' || $mkUser === '' || $tenantUser === '') {
	http_response_code(500);
	exit('Config incompleto (master/tenant).');
}

// ---------------------
// 3) Dados do formulário
// ---------------------
$slug    = clean_slug((string)($_POST['slug'] ?? ''));
$nome    = clean_text((string)($_POST['nome'] ?? ''));
$cidade  = clean_text((string)($_POST['cidade'] ?? ''));
$estado  = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', (string)($_POST['estado'] ?? '')), 0, 2));

$whatsRaw  = trim((string)($_POST['whatsapp'] ?? ''));
$whatsMask = format_phone_br($whatsRaw);
$whatsNorm = format_phone55($whatsRaw);
$whats = $whatsMask;

$dominioPersonalizado = clean_host((string)($_POST['dominio_personalizado'] ?? ''));
$planoMarketplace = ($dominioPersonalizado !== '') ? 'business' : 'profissional';

$dono_nome  = clean_text((string)($_POST['dono_nome'] ?? ''));
if ($dono_nome === '') $dono_nome = 'Administrador';

$dono_email  = strtolower(trim((string)($_POST['dono_email'] ?? '')));
$dono_senha  = (string)($_POST['dono_senha'] ?? '');
$dono_senha2 = (string)($_POST['dono_senha2'] ?? '');

// validações
if ($slug === '' || $nome === '') exit('Slug e Nome são obrigatórios.');
if (!preg_match('/^[a-z0-9][a-z0-9_-]{1,78}[a-z0-9]$/', $slug)) exit('Slug inválido.');

if ($dono_email === '') exit('Email do dono é obrigatório.');
if (!filter_var($dono_email, FILTER_VALIDATE_EMAIL)) exit('E-mail do dono inválido.');

if ($whatsRaw === '') exit('WhatsApp é obrigatório.');
if (!validar_whatsapp_br($whatsRaw)) exit('WhatsApp inválido. Informe DDD + número.');
if ($dominioPersonalizado !== '' && !valid_host($dominioPersonalizado)) exit('Domínio próprio inválido. Informe somente o host, ex: darosbarber.com.br');

if ($dono_senha === '') exit('Senha do dono é obrigatória.');
if (strlen($dono_senha) < 6) exit('Senha do dono muito curta (mínimo 6).');
if ($dono_senha !== $dono_senha2) exit('As senhas não conferem.');

// monta nome do DB do tenant
$dbName = $cpPrefix . $dbPrefix . $slug;

if (strlen($dbName) > 64) {
	exit('Nome do banco muito longo. Reduza o slug ou os prefixos.');
}

$senhaHash = password_hash($dono_senha, PASSWORD_DEFAULT);

// ---------------------
// 4) Conecta MASTER (market)
// ---------------------
try {
	$pdoMaster = pdo_connect($mkHost, $mkDb, $mkUser, $mkPass);
} catch (Throwable $e) {
	error_log('[MASTER CONNECT] ' . $e->getMessage());
	http_response_code(500);
	exit('Erro: sem acesso ao banco MASTER (market).');
}

// evita slug duplicado
$st = $pdoMaster->prepare("SELECT id FROM `{$tenantsTable}` WHERE slug = ? LIMIT 1");
$st->execute([$slug]);
if ($st->fetch()) exit('Este slug já existe. Escolha outro.');

// evita email duplicado no master tenants quando existir coluna dono_email
try {
	$colsSt = $pdoMaster->prepare("
		SELECT COLUMN_NAME
		FROM INFORMATION_SCHEMA.COLUMNS
		WHERE TABLE_SCHEMA = DATABASE()
		  AND TABLE_NAME = :t
	");
	$colsSt->execute([':t' => $tenantsTable]);
	$cols = $colsSt->fetchAll(PDO::FETCH_COLUMN);
	$cols = is_array($cols) ? $cols : [];
} catch (Throwable $e) {
	$cols = [];
}

$has = function(string $c) use ($cols): bool {
	return in_array($c, $cols, true);
};

if ($has('dono_email')) {
	$stEmail = $pdoMaster->prepare("SELECT id FROM `{$tenantsTable}` WHERE LOWER(TRIM(dono_email)) = LOWER(TRIM(?)) LIMIT 1");
	$stEmail->execute([$dono_email]);
	if ($stEmail->fetch()) {
		exit('Já existe cadastro com este email.');
	}
}

if ($has('whatsapp_norm') && $whatsNorm !== '') {
	$stWpp = $pdoMaster->prepare("SELECT id FROM `{$tenantsTable}` WHERE whatsapp_norm = ? LIMIT 1");
	$stWpp->execute([$whatsNorm]);
	if ($stWpp->fetch()) {
		exit('Já existe cadastro com este WhatsApp.');
	}
} elseif ($has('whatsapp') && $whatsMask !== '') {
	$stWpp = $pdoMaster->prepare("SELECT id FROM `{$tenantsTable}` WHERE whatsapp = ? LIMIT 1");
	$stWpp->execute([$whatsMask]);
	if ($stWpp->fetch()) {
		exit('Já existe cadastro com este WhatsApp.');
	}
}

if ($dominioPersonalizado !== '') {
	if (!table_exists_any($pdoMaster, 'tenant_domains')) {
		exit('A tabela tenant_domains não existe no banco master. Rode o SQL do marketplace antes de cadastrar domínio próprio.');
	}

	$stDom = $pdoMaster->prepare("SELECT id FROM tenant_domains WHERE LOWER(TRIM(host)) = LOWER(TRIM(?)) LIMIT 1");
	$stDom->execute([$dominioPersonalizado]);
	if ($stDom->fetch()) {
		exit('Este domínio próprio já está em uso.');
	}
}

$col_nome    = $has('nome') ? 'nome' : ($has('name') ? 'name' : 'nome');
$col_created = $has('criado_em') ? 'criado_em' : ($has('created_at') ? 'created_at' : 'criado_em');
$col_ativo   = $has('ativo') ? 'ativo' : ($has('status') ? 'status' : 'ativo');
$ativo_val   = ($col_ativo === 'status') ? 'active' : 1;

// ---------------------
// 5) Insere tenant no master
// ---------------------
$dbPassStore = encrypt_or_plain($tenantPass, $secret);

$pdoMaster->beginTransaction();

try {
	$fields = ['slug', $col_nome, 'db_name'];
	$values = [':slug', ':nome', ':db_name'];
	$params = [
		':slug' => $slug,
		':nome' => $nome,
		':db_name' => $dbName,
	];

	if ($has('db_user')) { $fields[]='db_user'; $values[]=':db_user'; $params[':db_user']=$tenantUser; }
	if ($has('db_pass')) { $fields[]='db_pass'; $values[]=':db_pass'; $params[':db_pass']=$dbPassStore; }
	if ($has('db_host')) { $fields[]='db_host'; $values[]=':db_host'; $params[':db_host']=$tenantHost; }

	if ($col_ativo === 'status') {
		$fields[]='status'; $values[]=':status'; $params[':status']=$ativo_val;
	} else {
		$fields[]='ativo'; $values[]=':ativo'; $params[':ativo']=(int)$ativo_val;
	}

	if ($col_created === 'created_at') {
		$fields[]='created_at'; $values[]='NOW()';
	} else {
		$fields[]='criado_em'; $values[]='NOW()';
	}

	if ($has('cidade'))   { $fields[]='cidade';   $values[]=':cidade';   $params[':cidade']=($cidade!==''?$cidade:null); }
	if ($has('estado'))   { $fields[]='estado';   $values[]=':estado';   $params[':estado']=($estado!==''?$estado:null); }
	if ($has('whatsapp')) { $fields[]='whatsapp'; $values[]=':whatsapp'; $params[':whatsapp']=($whatsMask!==''?$whatsMask:null); }
	if ($has('whatsapp_norm')) { $fields[]='whatsapp_norm'; $values[]=':whatsapp_norm'; $params[':whatsapp_norm']=($whatsNorm!==''?$whatsNorm:null); }

	if ($has('dono_nome')) { $fields[]='dono_nome'; $values[]=':dono_nome'; $params[':dono_nome']=$dono_nome; }
	if ($has('dono_email')) { $fields[]='dono_email'; $values[]=':dono_email'; $params[':dono_email']=$dono_email; }

	if ($has('dono_senha_hash')) { $fields[]='dono_senha_hash'; $values[]=':dono_senha_hash'; $params[':dono_senha_hash']=$senhaHash; }
	if ($has('plano_marketplace')) { $fields[]='plano_marketplace'; $values[]=':plano_marketplace'; $params[':plano_marketplace']=$planoMarketplace; }

	$sql = "INSERT INTO `{$tenantsTable}` (" . implode(',', $fields) . ") VALUES (" . implode(',', $values) . ")";
	$st = $pdoMaster->prepare($sql);
	$st->execute($params);

	$tenantId = (int)$pdoMaster->lastInsertId();

	if ($dominioPersonalizado !== '') {
		insert_tenant_domain($pdoMaster, $tenantId, $dominioPersonalizado);
	}

	$pdoMaster->commit();

	try {
		cpanel_try_create_db_and_grant($dbName, $tenantUser);
	} catch (Throwable $e) {
	}

	$_SESSION['novo_dono'] = [
		'tenant_id'      => $tenantId,
		'slug'           => $slug,
		'nome'           => $dono_nome,
		'email'          => $dono_email,
		'senha_hash'     => $senhaHash,
		'senha_plain'    => $dono_senha,
		'whatsapp'       => $whatsMask,
		'whatsapp_norm'  => $whatsNorm,
		'db_name'        => $dbName,
		'dominio_personalizado' => $dominioPersonalizado,
		'plano_marketplace' => $planoMarketplace
	];

	try {
		$adminDb = detect_admin_db($cfg, $mkDb);
		$pdoAdmin = null;

		try {
			$pdoAdmin = pdo_connect($authHost, ($authDb !== '' ? $authDb : $adminDb), $authUser, $authPass);
		} catch (Throwable $e) {
			$pdoAdmin = null;
		}

		if ($sync_master_user) {
			if ($pdoAdmin instanceof PDO) {
				$telMaster = format_phone55($whats);
				upsert_master_user($pdoAdmin, $dono_nome, $dono_email, $telMaster, $dono_senha, $senhaHash);
			}
		} else {
			// padrão: dono fica no tenant
		}
	} catch (Throwable $e) {
	}

	try {
		$baseUrlCfg = (string)($cfg['app']['base_url'] ?? ($cfg['base_url'] ?? ''));
		$baseUrl = normalizar_base_url($baseUrlCfg);

		$linkLoja       = montar_link_loja($baseUrl, $slug);
		$linkAg         = montar_link_loja($baseUrl, $slug, 'agendamentos');
		$linkPainelLoja = montar_link_loja($baseUrl, $slug, 'sistema');
		$linkMaster     = montar_link_master($baseUrl);
		$linkPainelMaster = montar_link_master($baseUrl, 'sistema');
		$linkDominio = montar_link_dominio($dominioPersonalizado);
		$linkDominioAg = montar_link_dominio($dominioPersonalizado, 'agendamentos');
		$linkDominioSistema = montar_link_dominio($dominioPersonalizado, 'sistema');

		if ($linkDominio !== '') {
			$linkLojaMensagem = $linkDominio;
			$linkAgMensagem = ($linkDominioAg !== '') ? $linkDominioAg : $linkAg;
			$linkPainelMensagem = ($linkDominioSistema !== '') ? $linkDominioSistema : $linkPainelLoja;
		} else {
			$linkLojaMensagem = $linkLoja;
			$linkAgMensagem = $linkAg;
			$linkPainelMensagem = $linkPainelLoja;
		}

		$adminDb = detect_admin_db($cfg, $mkDb);
		$pdoAdmin = null;

		try {
			$pdoAdmin = pdo_connect($authHost, ($authDb !== '' ? $authDb : $adminDb), $authUser, $authPass);
		} catch (Throwable $e) {
			$pdoAdmin = null;
		}

		if ($pdoAdmin instanceof PDO) {
			$notifyCfg = admin_fetch_notify_config($pdoAdmin);
			$apiNotify = $notifyCfg['api'] !== '' ? $notifyCfg['api'] : 'menuia';
			$tokenNotify = $notifyCfg['token'] ?? '';
			$instNotify  = $notifyCfg['instancia'] ?? '';

			$toDono = format_phone55($whats);
			if ($toDono !== '') {
				$msgDono  = "✅ *Seu cadastro foi criado com sucesso!* \n\n";
				$msgDono .= "*Loja:* {$nome}\n";
				$msgDono .= "*Plano:* " . ($planoMarketplace === 'business' ? 'Business' : 'Profissional') . "\n";
				$msgDono .= "*Link da Loja:* {$linkLoja}\n";
				if ($linkDominio !== '') {
					$msgDono .= "*Domínio Próprio:* {$linkDominio}\n";
				}
				$msgDono .= "\n";
				$msgDono .= "📅 *Agendamentos:* {$linkAg}\n";
				$msgDono .= "🔐 *Painel da Loja:* {$linkPainelLoja}\n\n";
				$msgDono .= "*Acesso do Administrador da Loja:*\n";
				$msgDono .= "Email: {$dono_email}\n";
				$msgDono .= "Senha: {$dono_senha}\n\n";
				if ($linkDominio !== '') {
					$msgDono .= "⚠️ Seu domínio próprio foi cadastrado. Após DNS/SSL apontados, ele também acessará sua loja.\n\n";
				}
				$msgDono .= "Se precisar, responda esta mensagem.";

				send_whatsapp_gateway($apiNotify, $tokenNotify, $instNotify, $toDono, $msgDono);
			}

			$adminPhone = admin_fetch_phone($pdoAdmin);
			if ($adminPhone !== '') {
				$msgAdmin  = "📌 *Novo cadastro no BarberBot*\n\n";
				$msgAdmin .= "*Tenant:* {$nome}\n";
				$msgAdmin .= "*Slug:* {$slug}\n";
				$msgAdmin .= "*DB:* {$dbName}\n";
				$msgAdmin .= "*Plano:* " . ($planoMarketplace === 'business' ? 'Business' : 'Profissional') . "\n";
				if ($dominioPersonalizado !== '') {
					$msgAdmin .= "*Domínio Próprio:* {$dominioPersonalizado}\n";
				}
				if ($cidade !== '' || $estado !== '') $msgAdmin .= "*Cidade/UF:* {$cidade} {$estado}\n";
				if ($whats !== '') $msgAdmin .= "*WhatsApp Loja:* " . format_phone55($whats) . "\n";
				$msgAdmin .= "\n*Dono:* {$dono_nome}\n";
				$msgAdmin .= "*Email:* {$dono_email}\n";
				$msgAdmin .= "\nLinks:\n";
				$msgAdmin .= $linkLoja . "\n";
				if ($linkDominio !== '') {
					$msgAdmin .= $linkDominio . "\n";
				}
				$msgAdmin .= $linkMaster . "\n";
				$msgAdmin .= $linkPainelMaster . "\n";
				$msgAdmin .= $linkPainelLoja . "\n";
				$msgAdmin .= $linkAg . "\n";

				send_whatsapp_gateway($apiNotify, $tokenNotify, $instNotify, $adminPhone, $msgAdmin);
			}
		}
	} catch (Throwable $e) {
	}

	go('/finalizar_tenant.php?slug=' . urlencode($slug));

} catch (Throwable $e) {
	try {
		$pdoMaster->rollBack();
	} catch (Throwable $e2) {
	}
	error_log('[PROVISIONAR_TENANT] ' . $e->getMessage());
	http_response_code(500);
	exit('Erro ao provisionar tenant.');
}