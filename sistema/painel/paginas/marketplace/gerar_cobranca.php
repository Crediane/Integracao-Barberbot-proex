<?php
require_once __DIR__ . '/master_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if (!function_exists('bb_market_json_saida')) {
	function bb_market_json_saida(array $dados, int $status = 200): void
	{
		while (ob_get_level() > 0) {
			@ob_end_clean();
		}
		http_response_code($status);
		echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		exit;
	}
}

if (!function_exists('bb_market_incluir_primeiro_arquivo')) {
	function bb_market_incluir_primeiro_arquivo(array $caminhos): string
	{
		foreach ($caminhos as $caminho) {
			if (is_file($caminho)) {
				return $caminho;
			}
		}
		return '';
	}
}

if (!function_exists('bb_market_only_digits')) {
	function bb_market_only_digits(string $valor): string
	{
		return (string)preg_replace('/\D+/', '', $valor);
	}
}

if (!function_exists('bb_market_whatsapp_br')) {
	function bb_market_whatsapp_br(string $telefone): string
	{
		$telefone = bb_market_only_digits($telefone);
		if ($telefone === '') {
			return '';
		}

		if (substr($telefone, 0, 2) !== '55' && strlen($telefone) <= 11) {
			$telefone = '55' . $telefone;
		}

		if (substr($telefone, 0, 2) === '55' && strlen($telefone) > 13) {
			$telefone = substr($telefone, 0, 13);
		}

		return $telefone;
	}
}

if (!function_exists('bb_market_clean_slug')) {
	function bb_market_clean_slug(string $valor): string
	{
		$valor = strtolower(trim($valor));
		return (string)preg_replace('/[^a-z0-9_-]/', '', $valor);
	}
}

if (!function_exists('bb_market_clean_host')) {
	function bb_market_clean_host(string $valor): string
	{
		$valor = strtolower(trim($valor));
		if ($valor === '') {
			return '';
		}

		$valor = preg_replace('~^https?://~i', '', $valor);
		$valor = preg_replace('~/.*$~', '', $valor);
		$valor = preg_replace('~:\d+$~', '', $valor);

		return trim($valor, ". \t\n\r\0\x0B");
	}
}

if (!function_exists('bb_market_normalizar_base_url')) {
	function bb_market_normalizar_base_url(string $baseUrl = ''): string
	{
		$baseUrl = trim($baseUrl);
		if ($baseUrl !== '') {
			return rtrim($baseUrl, '/');
		}

		$proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
		if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
			$proto = 'https';
		}

		$host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
		return $proto . '://' . $host;
	}
}

if (!function_exists('bb_market_table_exists')) {
	function bb_market_table_exists(PDO $pdo, string $tabela): bool
	{
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

if (!function_exists('bb_market_column_exists')) {
	function bb_market_column_exists(PDO $pdo, string $tabela, string $coluna): bool
	{
		try {
			$st = $pdo->prepare("
				SELECT 1
				  FROM INFORMATION_SCHEMA.COLUMNS
				 WHERE TABLE_SCHEMA = DATABASE()
				   AND TABLE_NAME = :t
				   AND COLUMN_NAME = :c
				 LIMIT 1
			");
			$st->execute([':t' => $tabela, ':c' => $coluna]);
			return (bool)$st->fetchColumn();
		} catch (Throwable $e) {
			return false;
		}
	}
}

if (!function_exists('bb_market_fetch_dominio_personalizado')) {
	function bb_market_fetch_dominio_personalizado(PDO $pdoMaster, int $tenantId): string
	{
		if ($tenantId <= 0 || !bb_market_table_exists($pdoMaster, 'tenant_domains')) {
			return '';
		}

		$whereAtivo = bb_market_column_exists($pdoMaster, 'tenant_domains', 'ativo') ? "AND (ativo = 1 OR ativo IS NULL)" : "";
		$wherePrincipal = bb_market_column_exists($pdoMaster, 'tenant_domains', 'principal') ? "AND (principal = 1 OR principal IS NULL)" : "";
		$whereTipo = bb_market_column_exists($pdoMaster, 'tenant_domains', 'tipo') ? "AND (tipo = 'dominio' OR tipo IS NULL OR tipo = '')" : "";

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
			$st = $pdoMaster->prepare($sql);
			$st->execute([':tenant_id' => $tenantId]);
			return bb_market_clean_host((string)($st->fetchColumn() ?: ''));
		} catch (Throwable $e) {
			return '';
		}
	}
}

if (!function_exists('bb_market_resolver_token_mp')) {
	function bb_market_resolver_token_mp(array $cfg): string
	{
		global $access_token_mp, $pdoM, $pdo;

		$mpToken = trim((string)($access_token_mp ?? ''));
		$pdosGlobais = [];

		if (isset($pdoM) && $pdoM instanceof PDO) {
			$pdosGlobais[] = $pdoM;
		}

		if (isset($pdo) && $pdo instanceof PDO) {
			try {
				$dbAtual = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
				$dbsPermitidos = [];
				if (!empty($cfg['master']['db'])) {
					$dbsPermitidos[] = (string)$cfg['master']['db'];
				}
				if (!empty($cfg['auth_master']['db'])) {
					$dbsPermitidos[] = (string)$cfg['auth_master']['db'];
				}
				if (in_array($dbAtual, array_values(array_unique($dbsPermitidos)), true)) {
					$pdosGlobais[] = $pdo;
				}
			} catch (Throwable $e) {
			}
		}

		if ($mpToken === '') {
			foreach ($pdosGlobais as $pdoGlobal) {
				try {
					if (!bb_market_table_exists($pdoGlobal, 'config') || !bb_market_column_exists($pdoGlobal, 'config', 'access_token_mp')) {
						continue;
					}
					$q = $pdoGlobal->query("SELECT access_token_mp FROM config LIMIT 1");
					$conf = $q ? $q->fetch(PDO::FETCH_ASSOC) : [];
					$mpToken = trim((string)($conf['access_token_mp'] ?? ''));
					if ($mpToken !== '') {
						break;
					}
				} catch (Throwable $e) {
				}
			}
		}

		return $mpToken;
	}
}

if (!function_exists('bb_market_resolver_cfg_whatsapp')) {
	function bb_market_resolver_cfg_whatsapp(array $cfg): array
	{
		global $api, $token_whatsapp, $instancia_whatsapp, $token, $instancia, $tel_whatsapp, $whatsapp_sistema, $pdoM, $pdo;

		$retorno = [
			'api' => '',
			'token' => '',
			'instancia' => '',
			'telefone_admin' => '',
		];

		$pdosGlobais = [];
		if (isset($pdoM) && $pdoM instanceof PDO) {
			$pdosGlobais[] = $pdoM;
		}
		if (isset($pdo) && $pdo instanceof PDO) {
			try {
				$dbAtual = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
				$dbsPermitidos = [];
				if (!empty($cfg['master']['db'])) {
					$dbsPermitidos[] = (string)$cfg['master']['db'];
				}
				if (!empty($cfg['auth_master']['db'])) {
					$dbsPermitidos[] = (string)$cfg['auth_master']['db'];
				}
				if (in_array($dbAtual, array_values(array_unique($dbsPermitidos)), true)) {
					$pdosGlobais[] = $pdo;
				}
			} catch (Throwable $e) {
			}
		}

		foreach ($pdosGlobais as $pdoGlobal) {
			try {
				if (!bb_market_table_exists($pdoGlobal, 'config')) {
					continue;
				}
				$cols = [
					'api' => bb_market_column_exists($pdoGlobal, 'config', 'api'),
					'token' => bb_market_column_exists($pdoGlobal, 'config', 'token'),
					'instancia' => bb_market_column_exists($pdoGlobal, 'config', 'instancia'),
					'telefone_whatsapp' => bb_market_column_exists($pdoGlobal, 'config', 'telefone_whatsapp'),
				];
				if (!$cols['api'] || !$cols['token'] || !$cols['instancia']) {
					continue;
				}
				$select = 'api, token, instancia';
				if ($cols['telefone_whatsapp']) {
					$select .= ', telefone_whatsapp';
				}
				$q = $pdoGlobal->query("SELECT {$select} FROM config LIMIT 1");
				$conf = $q ? $q->fetch(PDO::FETCH_ASSOC) : [];
				if ($conf) {
					$retorno['api'] = trim((string)($conf['api'] ?? ''));
					$retorno['token'] = trim((string)($conf['token'] ?? ''));
					$retorno['instancia'] = trim((string)($conf['instancia'] ?? ''));
					$retorno['telefone_admin'] = trim((string)($conf['telefone_whatsapp'] ?? ''));
					if ($retorno['api'] !== '' && $retorno['token'] !== '' && $retorno['instancia'] !== '') {
						break;
					}
				}
			} catch (Throwable $e) {
			}
		}

		if ($retorno['api'] === '' && !empty($api)) {
			$retorno['api'] = (string)$api;
		}
		if ($retorno['token'] === '' && !empty($token_whatsapp)) {
			$retorno['token'] = (string)$token_whatsapp;
		}
		if ($retorno['token'] === '' && !empty($token)) {
			$retorno['token'] = (string)$token;
		}
		if ($retorno['instancia'] === '' && !empty($instancia_whatsapp)) {
			$retorno['instancia'] = (string)$instancia_whatsapp;
		}
		if ($retorno['instancia'] === '' && !empty($instancia)) {
			$retorno['instancia'] = (string)$instancia;
		}
		if ($retorno['telefone_admin'] === '' && !empty($tel_whatsapp)) {
			$retorno['telefone_admin'] = (string)$tel_whatsapp;
		}
		if ($retorno['telefone_admin'] === '' && !empty($whatsapp_sistema)) {
			$retorno['telefone_admin'] = (string)$whatsapp_sistema;
		}

		$retorno['telefone_admin'] = bb_market_whatsapp_br((string)$retorno['telefone_admin']);
		return $retorno;
	}
}

if (!function_exists('bb_market_enviar_whatsapp')) {
	function bb_market_enviar_whatsapp(string $api, string $token, string $instancia, string $telefone, string $mensagem): bool
	{
		$telefone = bb_market_whatsapp_br($telefone);
		$api = trim($api);
		$token = trim($token);
		$instancia = trim($instancia);
		$mensagem = trim($mensagem);

		if ($telefone === '' || $token === '' || $instancia === '' || $mensagem === '') {
			return false;
		}

		$arquivoApiTexto = __DIR__ . '/../../../../webhook/api-texto.php';
		if (!is_file($arquivoApiTexto)) {
			return false;
		}

		$apiLocal = ($api !== '') ? $api : 'menuia';
		$tokenLocal = $token;
		$instanciaLocal = $instancia;
		$telefoneLocal = $telefone;
		$mensagemLocal = str_replace(["\r\n", "\r", "\n"], "%0A", $mensagem);

		try {
			ob_start();

			$api = $apiLocal;
			$token = $tokenLocal;
			$instancia = $instanciaLocal;
			$telefone = $telefoneLocal;
			$mensagem = $mensagemLocal;

			require $arquivoApiTexto;
			@ob_end_clean();
			return true;
		} catch (Throwable $e) {
			if (ob_get_level() > 0) {
				@ob_end_clean();
			}
			return false;
		}
	}
}

if (!function_exists('bb_market_criar_preference_mp')) {
	function bb_market_criar_preference_mp(string $mpToken, array $tenant, array $cfg, int $tenantId): array
	{
		$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
		$host = (string)($_SERVER['HTTP_HOST'] ?? 'localhost');
		$baseUrlAtual = $scheme . '://' . $host;

		$baseUrlCfg = bb_market_normalizar_base_url((string)($cfg['app']['base_url'] ?? ($cfg['base_url'] ?? '')));
		$baseUrlRetorno = $baseUrlAtual;

		$adminSlug = '';
		$uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
		$seg = array_values(array_filter(explode('/', trim($uri, '/'))));
		if (isset($seg[0]) && $seg[0] === 'loja' && !empty($seg[1])) {
			$adminSlug = bb_market_clean_slug((string)$seg[1]);
		}

		$returnBase = ($adminSlug !== '')
			? ($baseUrlAtual . "/loja/" . rawurlencode($adminSlug) . "/painel/marketplace.php")
			: ($baseUrlAtual . "/sistema/painel/index.php?pag=marketplace");

		$backUrls = [
			"success" => $returnBase . (strpos($returnBase, '?') !== false ? '&' : '?') . "mp=success&id=" . $tenantId,
			"failure" => $returnBase . (strpos($returnBase, '?') !== false ? '&' : '?') . "mp=failure&id=" . $tenantId,
			"pending" => $returnBase . (strpos($returnBase, '?') !== false ? '&' : '?') . "mp=pending&id=" . $tenantId,
		];

		$notifyUrl = $baseUrlCfg . "/pagamentos/webhook_mp_marketplace.php";

		$nome = (string)($tenant['nome'] ?? $tenant['slug'] ?? 'Barbearia');
		$slug = (string)($tenant['slug'] ?? '');
		$email = trim((string)($tenant['email'] ?? ''));
		if ($email === '') {
			$email = trim((string)($tenant['dono_email'] ?? ''));
		}
		if ($email === '') {
			$email = 'pagamentos@' . $host;
		}

		$valor = (float)($tenant['valor_mensal'] ?? 0);

		$body = [
			"external_reference" => (string)$tenantId,
			"notification_url"   => $notifyUrl,
			"auto_return"        => "approved",
			"back_urls"          => $backUrls,
			"items" => [
				[
					"title"      => "Assinatura BarberBot - " . $nome . ($slug !== '' ? " ({$slug})" : ''),
					"quantity"   => 1,
					"unit_price" => $valor
				]
			],
			"payer" => [
				"email" => $email
			]
		];

		$ch = curl_init("https://api.mercadopago.com/checkout/preferences");
		curl_setopt_array($ch, [
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_HTTPHEADER => [
				"Authorization: Bearer " . $mpToken,
				"Content-Type: application/json"
			],
			CURLOPT_POST => true,
			CURLOPT_POSTFIELDS => json_encode($body),
			CURLOPT_TIMEOUT => 30
		]);

		$resp = curl_exec($ch);
		$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
		$curlErro = curl_error($ch);
		curl_close($ch);

		if (!$resp || $http < 200 || $http >= 300) {
			$msg = 'Erro ao criar cobrança no Mercado Pago.';
			$j = json_decode((string)$resp, true);

			if (is_array($j) && !empty($j['message'])) {
				$msg .= ' (' . $j['message'] . ')';
			} elseif ($curlErro !== '') {
				$msg .= ' (' . $curlErro . ')';
			}

			return [
				'ok' => false,
				'msg' => $msg,
				'http' => $http,
			];
		}

		$r = json_decode((string)$resp, true);
		$link = trim((string)($r['init_point'] ?? ''));
		$prefId = trim((string)($r['id'] ?? ''));

		if ($link === '') {
			return [
				'ok' => false,
				'msg' => 'Mercado Pago não retornou init_point.',
			];
		}

		return [
			'ok' => true,
			'url' => $link,
			'link' => $link,
			'preference_id' => $prefId,
			'raw' => $r,
		];
	}
}

if (!function_exists('bb_market_fetch_fatura_pendente')) {
	function bb_market_fetch_fatura_pendente(PDO $pdoMaster, int $tenantId): ?array
	{
		if ($tenantId <= 0 || !bb_market_table_exists($pdoMaster, 'marketplace_faturas')) {
			return null;
		}

		try {
			$st = $pdoMaster->prepare("
				SELECT *
				  FROM marketplace_faturas
				 WHERE tenant_id = :tenant_id
				   AND status IN ('pending', 'open', 'overdue')
				 ORDER BY id DESC
				 LIMIT 1
			");
			$st->execute([':tenant_id' => $tenantId]);
			$res = $st->fetch(PDO::FETCH_ASSOC);

			return is_array($res) ? $res : null;
		} catch (Throwable $e) {
			return null;
		}
	}
}

if (!function_exists('bb_market_salvar_fatura_mp')) {
	function bb_market_salvar_fatura_mp(PDO $pdoMaster, int $tenantId, array $tenant, array $preferencia): void
	{
		if ($tenantId <= 0 || !bb_market_table_exists($pdoMaster, 'marketplace_faturas')) {
			return;
		}

		$faturaAtual = bb_market_fetch_fatura_pendente($pdoMaster, $tenantId);
		$vencimento = trim((string)($tenant['vencimento'] ?? ''));
		if ($vencimento === '' || $vencimento === '0000-00-00') {
			$vencimento = date('Y-m-d');
		}

		$valor = (float)($tenant['valor_mensal'] ?? 0);
		$prefId = (string)($preferencia['preference_id'] ?? '');
		$link = (string)($preferencia['url'] ?? '');

		try {
			if (is_array($faturaAtual) && !empty($faturaAtual['id'])) {
				$st = $pdoMaster->prepare("
					UPDATE marketplace_faturas
					   SET vencimento = :vencimento,
					       valor = :valor,
					       status = 'pending',
					       mp_preference_id = :pref,
					       mp_init_point = :link
					 WHERE id = :id
					 LIMIT 1
				");
				$st->execute([
					':vencimento' => $vencimento,
					':valor' => $valor,
					':pref' => $prefId,
					':link' => $link,
					':id' => (int)$faturaAtual['id'],
				]);
				return;
			}

			$st = $pdoMaster->prepare("
				INSERT INTO marketplace_faturas
					(tenant_id, vencimento, valor, status, mp_preference_id, mp_init_point, criado_em)
				VALUES
					(:tenant_id, :vencimento, :valor, 'pending', :pref, :link, :criado_em)
			");
			$st->execute([
				':tenant_id' => $tenantId,
				':vencimento' => $vencimento,
				':valor' => $valor,
				':pref' => $prefId,
				':link' => $link,
				':criado_em' => date('Y-m-d H:i:s'),
			]);
		} catch (Throwable $e) {
		}
	}
}

ob_start();

$arquivoConexao = bb_market_incluir_primeiro_arquivo([
	__DIR__ . '/../../../conexao.php',
	__DIR__ . '/../../conexao.php',
]);

if ($arquivoConexao === '') {
	bb_market_json_saida(['ok' => false, 'msg' => 'Arquivo conexao.php não encontrado.']);
}

require_once $arquivoConexao;

$acao = strtolower(trim((string)($_POST['acao'] ?? 'gerar')));
if (!in_array($acao, ['gerar', 'enviar'], true)) {
	$acao = 'gerar';
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
	bb_market_json_saida(['ok' => false, 'msg' => 'ID inválido.']);
}

$cfgFile = __DIR__ . "/../../../../config/marketplace.php";
if (!is_file($cfgFile)) {
	bb_market_json_saida(['ok' => false, 'msg' => 'config/marketplace.php não encontrado.']);
}

$cfg = require $cfgFile;
if (!is_array($cfg) || empty($cfg['master'])) {
	bb_market_json_saida(['ok' => false, 'msg' => 'Config marketplace inválida.']);
}

try {
	$pdoM = new PDO(
		"mysql:host=" . $cfg['master']['host'] . ";dbname=" . $cfg['master']['db'] . ";charset=utf8mb4",
		$cfg['master']['user'],
		$cfg['master']['pass'],
		[
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
			PDO::ATTR_EMULATE_PREPARES => false,
		]
	);
} catch (Throwable $e) {
	bb_market_json_saida(['ok' => false, 'msg' => 'Falha ao conectar no banco master.']);
}

$tenantsTable = preg_replace('/[^a-z0-9_]/i', '', (string)($cfg['master']['table'] ?? 'tenants'));
if ($tenantsTable === '') {
	$tenantsTable = 'tenants';
}

$st = $pdoM->prepare("
	SELECT id, slug, nome, email, dono_email, whatsapp, telefone_whatsapp, valor_mensal, vencimento, status, cidade, estado
	  FROM `{$tenantsTable}`
	 WHERE id = ?
	 LIMIT 1
");
$st->execute([$id]);
$tenant = $st->fetch(PDO::FETCH_ASSOC);

if (!$tenant) {
	bb_market_json_saida(['ok' => false, 'msg' => 'Barbearia não encontrada.']);
}

$valor = (float)($tenant['valor_mensal'] ?? 0);
if ($valor <= 0) {
	bb_market_json_saida(['ok' => false, 'msg' => 'Valor mensal inválido (defina no cadastro do tenant).']);
}

$mpToken = bb_market_resolver_token_mp($cfg);
if ($mpToken === '') {
	bb_market_json_saida(['ok' => false, 'msg' => 'config.access_token_mp não configurado.']);
}

$preferencia = null;
$faturaAtual = bb_market_fetch_fatura_pendente($pdoM, $id);

if ($acao === 'enviar' && is_array($faturaAtual) && !empty($faturaAtual['mp_init_point'])) {
	$preferencia = [
		'ok' => true,
		'url' => (string)$faturaAtual['mp_init_point'],
		'link' => (string)$faturaAtual['mp_init_point'],
		'preference_id' => (string)($faturaAtual['mp_preference_id'] ?? ''),
	];
} else {
	$preferencia = bb_market_criar_preference_mp($mpToken, $tenant, $cfg, $id);
	if (empty($preferencia['ok'])) {
		bb_market_json_saida([
			'ok' => false,
			'msg' => (string)($preferencia['msg'] ?? 'Erro ao gerar cobrança.'),
			'http' => (int)($preferencia['http'] ?? 0),
		]);
	}

	bb_market_salvar_fatura_mp($pdoM, $id, $tenant, $preferencia);
}

if ($acao === 'gerar') {
	bb_market_json_saida([
		'ok' => true,
		'url' => (string)$preferencia['url'],
		'link' => (string)$preferencia['link'],
		'reload' => false,
		'whats_sent' => false,
	]);
}

$linkCobranca = trim((string)($preferencia['url'] ?? ''));
if ($linkCobranca == '') {
	bb_market_json_saida(['ok' => false, 'msg' => 'Link da cobrança não encontrado.']);
}

$telefoneDono = trim((string)($tenant['telefone_whatsapp'] ?? ''));
if ($telefoneDono === '') {
	$telefoneDono = trim((string)($tenant['whatsapp'] ?? ''));
}
$telefoneDono = bb_market_whatsapp_br($telefoneDono);
if ($telefoneDono === '') {
	bb_market_json_saida(['ok' => false, 'msg' => 'WhatsApp do cliente não cadastrado.', 'reload' => false]);
}

$cfgWhats = bb_market_resolver_cfg_whatsapp($cfg);
if ($cfgWhats['token'] === '' || $cfgWhats['instancia'] === '') {
	bb_market_json_saida(['ok' => false, 'msg' => 'Token/instância do WhatsApp não configurados.', 'reload' => false]);
}

$dominioPersonalizado = bb_market_fetch_dominio_personalizado($pdoM, $id);
$nomeLoja = (string)($tenant['nome'] ?? $tenant['slug'] ?? 'Barbearia');
$slugLoja = (string)($tenant['slug'] ?? '');
$vencimento = trim((string)($tenant['vencimento'] ?? ''));
$valorFmt = number_format((float)($tenant['valor_mensal'] ?? 0), 2, ',', '.');

$baseUrlCfg = bb_market_normalizar_base_url((string)($cfg['app']['base_url'] ?? ($cfg['base_url'] ?? '')));
$linkLoja = $baseUrlCfg . '/loja/' . rawurlencode($slugLoja) . '/';
$linkAgendamentos = $baseUrlCfg . '/loja/' . rawurlencode($slugLoja) . '/agendamentos/';
$linkPainel = $baseUrlCfg . '/loja/' . rawurlencode($slugLoja) . '/sistema/';

$mensagem = "Olá! 👋\n\n";
$mensagem .= "Segue sua cobrança da assinatura do *BarberBot Marketplace*.\n\n";
$mensagem .= "🏷️ Loja: *{$nomeLoja}*\n";
if ($slugLoja !== '') {
	$mensagem .= "🔗 Slug: {$slugLoja}\n";
}
if ($dominioPersonalizado !== '') {
	$mensagem .= "🌐 Domínio próprio: https://{$dominioPersonalizado}/\n";
}
if ($vencimento !== '' && $vencimento !== '0000-00-00') {
	$mensagem .= "📅 Vencimento: " . date('d/m/Y', strtotime($vencimento)) . "\n";
}
$mensagem .= "💰 Valor: R$ {$valorFmt}\n\n";
$mensagem .= "💳 *Pagar agora:*\n{$linkCobranca}\n\n";
$mensagem .= "🌐 Links úteis:\n";
$mensagem .= "• Loja: {$linkLoja}\n";
$mensagem .= "• Agendamentos: {$linkAgendamentos}\n";
$mensagem .= "• Sistema: {$linkPainel}\n";

$okEnvio = bb_market_enviar_whatsapp(
	(string)$cfgWhats['api'],
	(string)$cfgWhats['token'],
	(string)$cfgWhats['instancia'],
	$telefoneDono,
	$mensagem
);

if (!$okEnvio) {
	bb_market_json_saida([
		'ok' => false,
		'msg' => 'Falha ao enviar cobrança no WhatsApp.',
		'reload' => false,
		'url' => $linkCobranca,
	]);
}

bb_market_json_saida([
	'ok' => true,
	'msg' => 'Cobrança enviada com sucesso no WhatsApp.',
	'reload' => false,
	'url' => $linkCobranca,
	'telefone' => $telefoneDono,
]);
