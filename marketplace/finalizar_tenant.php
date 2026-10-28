<?php
// /public_html/finalizar_tenant.php
declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

// A finalização começa obrigatoriamente na sessão MASTER, onde
// provisionar_tenant.php armazenou os dados temporários do novo dono.
// A troca para BB_TENANT_<hash> continua sendo feita somente depois que o
// tenant foi localizado e o banco individual foi confirmado.
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

function h(string $s): string {
	return htmlspecialchars($s, ENT_QUOTES, 'UTF-8');
}

function go(string $to): void {
	while (ob_get_level() > 0) {
		@ob_end_clean();
	}

	if (!headers_sent()) {
		header('Location: ' . $to, true, 302);
		exit;
	}

	$toJs = htmlspecialchars($to, ENT_QUOTES, 'UTF-8');
	echo '<meta http-equiv="refresh" content="0;url=' . $toJs . '">';
	echo '<script>window.location.href=' . json_encode($to) . ';</script>';
	exit;
}

function clean_slug(string $v): string {
	$v = strtolower(trim($v));
	$v = preg_replace('/[^a-z0-9_-]/', '', $v);
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


function bb_switch_to_tenant_session(string $slug): void {
	$slug = clean_slug($slug);
	if ($slug === '') {
		return;
	}

	$sessionName = 'BB_TENANT_' . strtoupper(substr(sha1($slug), 0, 12));

	if (session_name() === $sessionName && session_status() === PHP_SESSION_ACTIVE) {
		return;
	}

	if (session_status() === PHP_SESSION_ACTIVE) {
		session_write_close();
	}

	@session_name($sessionName);
	if (!empty($_COOKIE[$sessionName])) {
		@session_id((string)$_COOKIE[$sessionName]);
	}
	@session_start();
}

function pdo_connect(string $host, string $db, string $user, string $pass, int $fetchMode = PDO::FETCH_ASSOC): PDO {
	return new PDO(
		"mysql:host={$host};dbname={$db};charset=utf8mb4",
		$user,
		$pass,
		[
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => $fetchMode,
			PDO::ATTR_EMULATE_PREPARES => false,
		]
	);
}

function col_exists(PDO $pdo, string $table, string $col): bool {
	try {
		$st = $pdo->prepare("
			SELECT 1
			  FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = DATABASE()
			   AND TABLE_NAME = :t
			   AND COLUMN_NAME = :c
			 LIMIT 1
		");
		$st->execute([':t' => $table, ':c' => $col]);
		return (bool)$st->fetchColumn();
	} catch (Throwable $e) {
		return false;
	}
}

function table_exists(PDO $pdo, string $table): bool {
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

function table_count(PDO $pdo, string $table): int {
	try {
		$q = $pdo->query("SELECT COUNT(*) FROM `{$table}`");
		return (int)$q->fetchColumn();
	} catch (Throwable $e) {
		return 0;
	}
}

function active_where(PDO $pdo, string $table): string {
	if (col_exists($pdo, $table, 'ativo')) {
		return "`ativo`=1";
	}
	if (col_exists($pdo, $table, 'status')) {
		return "`status`='active'";
	}
	return "1=1";
}

function decrypt_or_plain(string $value, string $secret): string {
	if ($value === '') {
		return '';
	}
	if ($secret === '') {
		return $value;
	}
	if (!function_exists('bb_decrypt_str')) {
		return $value;
	}

	$dec = bb_decrypt_str($value, $secret);
	return ($dec !== '') ? $dec : $value;
}

function table_cols_fast(PDO $pdo, string $table): array {
	try {
		$st = $pdo->query("SHOW COLUMNS FROM `{$table}`");
		$rows = $st->fetchAll(PDO::FETCH_ASSOC);
		$cols = [];
		foreach ($rows as $r) {
			if (!empty($r['Field'])) {
				$cols[] = (string)$r['Field'];
			}
		}
		return $cols;
	} catch (Throwable $e) {
		return [];
	}
}

function normalizar_base_url(string $baseUrl = ''): string {
	$baseUrl = trim($baseUrl);

	if ($baseUrl === '') {
		$proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
		if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
			$proto = ($_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : $proto;
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
	return $link . '/';
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

function montar_link_dominio(string $host): string {
	$host = clean_host($host);
	if ($host === '') {
		return '';
	}
	return 'https://' . $host . '/';
}

function montar_link_tenant_preferencial(string $baseUrl, string $slug, string $dominioPersonalizado = '', string $rota = ''): string {
	$dominioPersonalizado = clean_host($dominioPersonalizado);
	$rota = trim($rota, '/');

	if ($dominioPersonalizado !== '') {
		$link = 'https://' . $dominioPersonalizado;
		if ($rota !== '') {
			$link .= '/' . $rota;
		}
		return rtrim($link, '/') . '/';
	}

	return montar_link_loja($baseUrl, $slug, $rota);
}

function fetch_custom_domain(PDO $pdoMk, int $tenantId): string {
	if ($tenantId <= 0 || !table_exists($pdoMk, 'tenant_domains')) {
		return '';
	}

	$whereAtivo = col_exists($pdoMk, 'tenant_domains', 'ativo') ? "AND ativo = 1" : "";
	$wherePrincipal = col_exists($pdoMk, 'tenant_domains', 'principal') ? "AND principal = 1" : "";
	$whereTipo = col_exists($pdoMk, 'tenant_domains', 'tipo') ? "AND tipo = 'dominio'" : "";

	$sql = "
		SELECT host
		  FROM tenant_domains
		 WHERE tenant_id = :tenant_id
		   {$whereTipo}
		   {$whereAtivo}
		   {$wherePrincipal}
		 ORDER BY id DESC
		 LIMIT 1
	";
	try {
		$st = $pdoMk->prepare($sql);
		$st->execute([':tenant_id' => $tenantId]);
		$host = (string)($st->fetchColumn() ?: '');
		return clean_host($host);
	} catch (Throwable $e) {
		return '';
	}
}

/**
 * Novo tenant não pode herdar dados individuais do MASTER.
 * Sobrescreve apenas dados individuais do tenant no config.
 */
function apply_tenant_config_overrides(PDO $pdoTenant, array $tenantRow, array $novoDono, string $slug): void {
	$cols = table_cols_fast($pdoTenant, 'config');
	if (empty($cols)) {
		return;
	}

	$has = function(string $c) use ($cols): bool {
		return in_array($c, $cols, true);
	};

	$cfgId = 0;
	try {
		$cfgId = (int)$pdoTenant->query("SELECT id FROM config LIMIT 1")->fetchColumn();
	} catch (Throwable $e) {
		$cfgId = 0;
	}

	if ($cfgId <= 0) {
		return;
	}

	$nomeLoja = (string)($tenantRow['nome'] ?? ($tenantRow['name'] ?? ''));
	if ($nomeLoja === '') {
		$nomeLoja = $slug;
	}

	$cidade = (string)($tenantRow['cidade'] ?? '');
	$estado = (string)($tenantRow['estado'] ?? '');

	$whats = (string)($tenantRow['whatsapp'] ?? '');
	if ($whats === '' && !empty($novoDono['whatsapp'])) {
		$whats = (string)$novoDono['whatsapp'];
	}

	$email = '';
	if (!empty($novoDono['email'])) {
		$email = (string)$novoDono['email'];
	}
	if ($email === '' && !empty($tenantRow['dono_email'])) {
		$email = (string)$tenantRow['dono_email'];
	}

	$sets = [];
	$params = [':id' => $cfgId];

	$add = function(string $col, $val) use (&$sets, &$params, $has) {
		if (!$has($col)) {
			return;
		}
		$sets[] = "`{$col}` = :{$col}";
		$params[":{$col}"] = $val;
	};

	$add('nome', $nomeLoja);
	if ($email !== '') {
		$add('email', $email);
	}
	if ($whats !== '') {
		$add('telefone_whatsapp', $whats);
	}
	if ($cidade !== '') {
		$add('cidade', $cidade);
	}
	$add('estado', $estado);

	$add('token', '');
	$add('instancia', '');
	$add('email_menuia', '');
	$add('senha_menuia', '');

	$add('openai_key', '');
	$add('openai_prompt', '');

	$add('public_key_mp', '');
	$add('access_token_mp', '');
	$add('asaas', '');

	if ($has('ativo')) {
		$add('ativo', 'Sim');
	}

	if (empty($sets)) {
		return;
	}

	$sql = "UPDATE config SET " . implode(', ', $sets) . " WHERE id = :id";
	$st = $pdoTenant->prepare($sql);
	$st->execute($params);
}

function create_admin_in_tenant(PDO $pdoTenant, string $nome, string $email, string $senhaPlain, string $senhaHash): void {
	if (!table_exists($pdoTenant, 'usuarios')) {
		return;
	}

	$st = $pdoTenant->prepare("SELECT id FROM usuarios WHERE LOWER(TRIM(email)) = LOWER(TRIM(?)) LIMIT 1");
	$st->execute([$email]);
	if ($st->fetch()) {
		return;
	}

	if (table_exists($pdoTenant, 'cargos')) {
		try {
			$pdoTenant->exec("INSERT INTO cargos (nome) SELECT 'Administrador' FROM DUAL
				WHERE NOT EXISTS (SELECT 1 FROM cargos WHERE nome='Administrador')");
		} catch (Throwable $e) {
		}
	}

	$fields = [];
	$params = [];

	$add = function(string $col, $val) use (&$fields, &$params, $pdoTenant) {
		if (col_exists($pdoTenant, 'usuarios', $col)) {
			$fields[] = $col;
			$params[":" . $col] = $val;
		}
	};

	$add('nome', $nome);
	$add('email', $email);

	if ($senhaPlain !== '') {
		$add('senha', $senhaPlain);
	}

	$add('senha_crip', $senhaHash);
	$add('nivel', 'Administrador');
	$add('ativo', 'Sim');
	$add('atendimento', 'Sim');
	$add('foto', 'sem-foto.jpg');

	if (col_exists($pdoTenant, 'usuarios', 'data')) {
		$fields[] = 'data';
		$params[':data'] = date('Y-m-d');
	}

	if (empty($fields)) {
		return;
	}

	$cols = implode(',', array_map(function($c) {
		return "`{$c}`";
	}, $fields));
	$vals = implode(',', array_map(function($c) {
		return ":" . $c;
	}, $fields));

	$sql = "INSERT INTO usuarios ({$cols}) VALUES ({$vals})";
	$st2 = $pdoTenant->prepare($sql);
	$st2->execute($params);
}

function auto_login_admin(PDO $pdoTenant, string $email): bool {
	if (!table_exists($pdoTenant, 'usuarios')) {
		return false;
	}

	$st = $pdoTenant->prepare("
		SELECT id, nome, nivel, ativo, email
		  FROM usuarios
		 WHERE LOWER(TRIM(email)) = LOWER(TRIM(?))
		 LIMIT 1
	");
	$st->execute([$email]);
	$u = $st->fetch(PDO::FETCH_ASSOC);

	if (!$u) {
		return false;
	}
	if ((string)($u['ativo'] ?? '') !== 'Sim') {
		return false;
	}

	$_SESSION['id'] = (int)($u['id'] ?? 0);
	$_SESSION['nivel'] = (string)($u['nivel'] ?? '');
	$_SESSION['nivel_usuario'] = (string)($u['nivel'] ?? '');
	$_SESSION['nome'] = (string)($u['nome'] ?? '');
	$_SESSION['email'] = (string)($u['email'] ?? $email);
	$_SESSION['aut_token_1010'] = "fdsfdsafda8855555";

	return ($_SESSION['id'] > 0);
}

// ===============================
// WHATSAPP (BEST EFFORT - NÃO QUEBRA)
// ===============================
function bb_only_digits(string $v): string {
	return (string)preg_replace('/\D+/', '', $v);
}

function bb_wpp_br(string $v): string {
	$d = bb_only_digits($v);
	if ($d === '') {
		return '';
	}
	if (substr($d, 0, 2) !== '55' && strlen($d) <= 11) {
		$d = '55' . $d;
	}
	if (substr($d, 0, 2) === '55' && strlen($d) > 13) {
		$d = substr($d, 0, 13);
	}
	return $d;
}

function bb_send_wpp(string $api, string $token, string $instancia, string $telefone, string $mensagem): void {
	$telefone = bb_wpp_br($telefone);
	if ($telefone === '') {
		return;
	}
	if ($mensagem === '') {
		return;
	}
	if ($token === '' || $instancia === '') {
		return;
	}

	$api = $api;
	$token = $token;
	$instancia = $instancia;
	$telefone = $telefone;
	$mensagem = str_replace(["\r\n", "\r", "\n"], "%0A", $mensagem);

	$file = __DIR__ . '/webhook/api-texto.php';
	if (is_file($file)) {
		try {
			ob_start();
			require $file;
			ob_end_clean();
		} catch (Throwable $e) {
			if (ob_get_level() > 0) {
				@ob_end_clean();
			}
		}
	}
}

// -------------------- INÍCIO --------------------

$slug = clean_slug((string)($_GET['slug'] ?? ''));
if ($slug === '') {
	go('/marketplace.php');
}

$cfg = @include __DIR__ . '/config/marketplace.php';
if (!is_array($cfg)) {
	http_response_code(500);
	exit('Config inválido.');
}

$mkHost = (string)($cfg['master']['host'] ?? 'localhost');
$mkDb = (string)($cfg['master']['db'] ?? '');
$mkUser = (string)($cfg['master']['user'] ?? '');
$mkPass = (string)($cfg['master']['pass'] ?? '');
$tenantsTable = preg_replace('/[^a-z0-9_]/i', '', (string)($cfg['master']['table'] ?? 'tenants'));

if ($tenantsTable === '') {
	$tenantsTable = 'tenants';
}

$sysHost = (string)($cfg['tenant']['template_host'] ?? ($cfg['tenant']['host'] ?? ($cfg['tenant']['db_host'] ?? 'localhost')));
$tplDb = (string)($cfg['tenant']['template_db'] ?? ($cfg['tenant']['schema_db'] ?? ($cfg['tenant']['default_db'] ?? ($cfg['auth_master']['db'] ?? ''))));
$sysUser = (string)($cfg['tenant']['template_user'] ?? ($cfg['tenant']['user'] ?? ''));
$sysPass = (string)($cfg['tenant']['template_pass'] ?? ($cfg['tenant']['pass'] ?? ''));
$secret = (string)($cfg['security']['tenant_secret'] ?? '');
$baseUrlCfg = (string)($cfg['app']['base_url'] ?? ($cfg['base_url'] ?? ''));

if ($mkDb === '' || $mkUser === '' || $tplDb === '' || $sysUser === '') {
	http_response_code(500);
	exit('Config incompleto.');
}

$pdoMk = pdo_connect($mkHost, $mkDb, $mkUser, $mkPass);

$where = active_where($pdoMk, $tenantsTable);

$st = $pdoMk->prepare("SELECT * FROM `{$tenantsTable}` WHERE slug = :s AND {$where} LIMIT 1");
$st->execute([':s' => $slug]);
$t = $st->fetch(PDO::FETCH_ASSOC);

if (!$t) {
	http_response_code(404);
	exit('Tenant não encontrado/inativo.');
}

$tenantId = (int)($t['id'] ?? 0);
$dominioPersonalizado = fetch_custom_domain($pdoMk, $tenantId);
if ($dominioPersonalizado === '' && !empty($_SESSION['novo_dono']['dominio_personalizado'])) {
	$dominioPersonalizado = clean_host((string)$_SESSION['novo_dono']['dominio_personalizado']);
}
$dbName = (string)($t['db_name'] ?? '');
$dbHost = (string)($t['db_host'] ?? 'localhost');
$dbUser = (string)($t['db_user'] ?? '');
$dbPass = decrypt_or_plain((string)($t['db_pass'] ?? ''), $secret);

if ($dbName === '' || $dbUser === '') {
	http_response_code(500);
	exit('Tenant sem db_name/db_user.');
}

try {
	$pdoTenant = pdo_connect($dbHost, $dbName, $dbUser, $dbPass);
} catch (Throwable $e) {
	?>
	<h3>O banco ainda não está acessível</h3>
	<pre>DB: <?=h($dbName)?> | USER: <?=h($dbUser)?> | HOST: <?=h($dbHost)?></pre>
	<p><b>Ação:</b> Crie o DB no cPanel e dê <b>ALL PRIVILEGES</b> para o usuário acima. Depois recarregue esta página.</p>
	<?php
	exit;
}

$pdoTpl = pdo_connect($sysHost, $tplDb, $sysUser, $sysPass, PDO::FETCH_NUM);

// 1) CLONA SCHEMA
try {
	$pdoTenant->exec("SET FOREIGN_KEY_CHECKS=0");
} catch (Throwable $e) {
}

$q = $pdoTpl->query("SHOW FULL TABLES WHERE Table_type = 'BASE TABLE'");
$tables = $q->fetchAll();

foreach ($tables as $row) {
	$table = (string)($row[0] ?? '');
	if ($table === '') {
		continue;
	}
	try {
		$pdoTenant->exec("CREATE TABLE IF NOT EXISTS `{$table}` LIKE `{$tplDb}`.`{$table}`");
	} catch (Throwable $e) {
		error_log("[FINALIZAR_TENANT][SCHEMA] {$table}: " . $e->getMessage());
	}
}

try {
	$pdoTenant->exec("SET FOREIGN_KEY_CHECKS=1");
} catch (Throwable $e) {
}

// 2) SEED base
$seedTables = ['config', 'cargos', 'dias', 'horarios', 'formas_pgto', 'configuracoes'];

try {
	$pdoTenant->exec("SET FOREIGN_KEY_CHECKS=0");
} catch (Throwable $e) {
}

foreach ($seedTables as $tb) {
	if (!table_exists($pdoTenant, $tb)) {
		continue;
	}
	if (!table_exists($pdoTpl, $tb)) {
		continue;
	}
	if (table_count($pdoTenant, $tb) > 0) {
		continue;
	}
	try {
		$pdoTenant->exec("INSERT INTO `{$tb}` SELECT * FROM `{$tplDb}`.`{$tb}`");
	} catch (Throwable $e) {
		error_log("[FINALIZAR_TENANT][SEED] {$tb}: " . $e->getMessage());
	}
}

try {
	$pdoTenant->exec("SET FOREIGN_KEY_CHECKS=1");
} catch (Throwable $e) {
}

// 2.1) Sobrescreve dados individuais no config do TENANT
$novoDonoSess = [];
if (!empty($_SESSION['novo_dono']) && is_array($_SESSION['novo_dono'])) {
	$novoDonoSess = $_SESSION['novo_dono'];
	if ((int)($novoDonoSess['tenant_id'] ?? 0) !== $tenantId || (string)($novoDonoSess['slug'] ?? '') !== $slug) {
		$novoDonoSess = [];
	}
}

try {
	apply_tenant_config_overrides($pdoTenant, $t, $novoDonoSess, $slug);
} catch (Throwable $e) {
}

// 3) Admin + Auto-login
$adminNome = '';
$adminEmail = '';
$adminHash = '';
$adminPlain = '';
$podeAutoLogar = false;

if (!empty($_SESSION['novo_dono']) && is_array($_SESSION['novo_dono'])) {
	$d = $_SESSION['novo_dono'];
	if ((int)($d['tenant_id'] ?? 0) === $tenantId && (string)($d['slug'] ?? '') === $slug) {
		$adminNome = trim((string)($d['nome'] ?? ''));
		$adminEmail = trim((string)($d['email'] ?? ''));
		$adminHash = (string)($d['senha_hash'] ?? '');
		$adminPlain = (string)($d['senha_plain'] ?? '');
		$podeAutoLogar = true;
	}
}

if ($adminEmail === '' && !empty($t['dono_email']) && !empty($t['dono_senha_hash'])) {
	$adminNome = !empty($t['dono_nome']) ? (string)$t['dono_nome'] : 'Administrador';
	$adminEmail = (string)$t['dono_email'];
	$adminHash = (string)$t['dono_senha_hash'];
	$adminPlain = '';
}

if ($adminNome !== '' && $adminEmail !== '' && $adminHash !== '') {
	try {
		create_admin_in_tenant($pdoTenant, $adminNome, $adminEmail, $adminPlain, $adminHash);
	} catch (Throwable $e) {
	}
}

// limpa o dado temporário da sessão de origem e troca para a sessão isolada da loja
if (!empty($_SESSION['novo_dono'])) {
	unset($_SESSION['novo_dono']);
}

bb_switch_to_tenant_session($slug);

// seta sessão do tenant
$_SESSION['tenant_id'] = $tenantId;
$_SESSION['tenant_slug'] = $slug;
$_SESSION['tenant_db'] = $dbName;
$_SESSION['tenant_host'] = $dbHost;
$_SESSION['tenant_user'] = $dbUser;
$_SESSION['tenant_pass'] = $dbPass;
$_SESSION['aut_token_1010'] = "fdsfdsafda8855555";

$autoLogou = false;
if ($podeAutoLogar && $adminEmail !== '') {
	try {
		$autoLogou = auto_login_admin($pdoTenant, $adminEmail);
	} catch (Throwable $e) {
		$autoLogou = false;
	}
}

// ===============================
// ENVIO WHATSAPP (NOVO CLIENTE)
// ===============================
if ($podeAutoLogar) {
	try {
		$baseUrl = normalizar_base_url($baseUrlCfg);

		$link_loja = montar_link_tenant_preferencial($baseUrl, $slug, $dominioPersonalizado);
		$link_agenda = montar_link_tenant_preferencial($baseUrl, $slug, $dominioPersonalizado, 'agendamentos');
		$link_sistema = montar_link_tenant_preferencial($baseUrl, $slug, $dominioPersonalizado, 'sistema');
		$link_master = montar_link_master($baseUrl, 'sistema');
		$link_dominio = montar_link_dominio($dominioPersonalizado);
		$usaDominioProprio = ($link_dominio !== '');

		$api_envio = 'menuia';
		$token_envio = '';
		$instancia_envio = '';
		$telefone_admin_sistema = '';

		$pdoTplAssoc = pdo_connect($sysHost, $tplDb, $sysUser, $sysPass, PDO::FETCH_ASSOC);

		if (table_exists($pdoTplAssoc, 'config')) {
			$cfgRow = $pdoTplAssoc->query("SELECT * FROM config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
			if (is_array($cfgRow)) {
				$api_envio = (string)($cfgRow['api'] ?? $api_envio);
				$token_envio = (string)($cfgRow['token'] ?? $token_envio);
				$instancia_envio = (string)($cfgRow['instancia'] ?? $instancia_envio);
				$telefone_admin_sistema = (string)($cfgRow['telefone_whatsapp'] ?? $telefone_admin_sistema);
			}
		}

		if ($telefone_admin_sistema === '' && table_exists($pdoTplAssoc, 'usuarios')) {
			$stAdm = $pdoTplAssoc->query("
				SELECT telefone
				  FROM usuarios
				 WHERE nivel = 'Administrador'
				   AND ativo = 'Sim'
				   AND telefone IS NOT NULL
				   AND telefone <> ''
				 ORDER BY id ASC
				 LIMIT 1
			");
			$telAdm = $stAdm ? $stAdm->fetchColumn() : '';
			if ($telAdm) {
				$telefone_admin_sistema = (string)$telAdm;
			}
		}

		$telefone_dono = (string)($t['whatsapp'] ?? '');
		if ($telefone_dono === '' && !empty($novoDonoSess['whatsapp'])) {
			$telefone_dono = (string)$novoDonoSess['whatsapp'];
		}

		$telefone_dono = bb_wpp_br($telefone_dono);
		$telefone_admin_sistema = bb_wpp_br($telefone_admin_sistema);

		$nome_loja = (string)($t['nome'] ?? ($t['name'] ?? $slug));

		if ($telefone_dono !== '' && $adminEmail !== '' && $adminPlain !== '') {
			$msgDono  = "👋 Olá *{$adminNome}*!\n\n";
			$msgDono .= "✅ Sua barbearia *{$nome_loja}* foi criada com sucesso.\n\n";
			$msgDono .= "🔐 *Dados de acesso:*\n";
			$msgDono .= "• Email: {$adminEmail}\n";
			$msgDono .= "• Senha: {$adminPlain}\n\n";
			$msgDono .= "🌐 *Links importantes:*\n";
			$msgDono .= "• Loja: {$link_loja}\n";
			$msgDono .= "• Agendamentos (clientes): {$link_agenda}\n";
			$msgDono .= "• Sistema / Painel: {$link_sistema}\n";

			bb_send_wpp($api_envio, $token_envio, $instancia_envio, $telefone_dono, $msgDono);
		}

		if ($telefone_admin_sistema !== '') {
			$cidade = (string)($t['cidade'] ?? '');
			$estado = (string)($t['estado'] ?? '');
			$local = trim($cidade . ($estado !== '' ? " - {$estado}" : ''));

			$msgAdm  = "🆕 *Novo cadastro de barbearia*\n\n";
			$msgAdm .= "🏷️ Nome: *{$nome_loja}*\n";
			$msgAdm .= "🔗 Slug: *{$slug}*\n";
			if ($local !== '') {
				$msgAdm .= "📍 Cidade: {$local}\n";
			}
			if ($telefone_dono !== '') {
				$msgAdm .= "📲 WhatsApp dono: {$telefone_dono}\n";
			}
			if ($adminEmail !== '') {
				$msgAdm .= "📧 Email dono: {$adminEmail}\n";
			}
			if ($adminPlain !== '') {
				$msgAdm .= "🔑 Senha (temp): {$adminPlain}\n";
			}
			$msgAdm .= "\n🌐 Links:\n";
			$msgAdm .= "• Loja: {$link_loja}\n";
			$msgAdm .= "• Agendamentos: {$link_agenda}\n";
			$msgAdm .= "• Sistema da Loja: {$link_sistema}\n";
			$msgAdm .= "• Master: {$link_master}\n";

			bb_send_wpp($api_envio, $token_envio, $instancia_envio, $telefone_admin_sistema, $msgAdm);
		}

	} catch (Throwable $e) {
		// NÃO quebra o sistema
	}
}

if (!empty($_SESSION['novo_dono'])) {
	unset($_SESSION['novo_dono']);
}

// redireciona no padrão da loja
if ($autoLogou) {
	go('/loja/' . rawurlencode($slug) . '/sistema/');
}

go('/loja/' . rawurlencode($slug) . '/sistema/');
