<?php
/**
 * =====================================================================
 * BarberBot - Envio de Mensagem WhatsApp (Menuia / Enviame)
 * Arquivo: /ajax/api-texto.php
 *
 * AJUSTE FECHADO NO PONTO CERTO (SEM MUDAR A LÓGICA DE NEGÓCIO):
 * - Mantém o fluxo atual de inclusão por require/include.
 * - Não inicia/renomeia sessão aqui.
 * - Normaliza telefone para Brasil e internacional sem quebrar o padrão BR.
 * - Resolve api/token/instancia pelo banco do contexto atual:
 *      /loja/{slug}/...  => banco do TENANT
 *      /sistema/...      => banco MASTER
 * - Respeita o /sistema/conexao.php já existente no marketplace.
 * - Faz fallback de envio Menuia em dois formatos de "to":
 *      1) somente dígitos
 *      2) +<ddi><numero>
 *   Isso evita quebra porque o projeto possui trechos antigos e novos usando ambos.
 * - Log mínimo sem quebrar o fluxo.
 *
 * Variáveis esperadas pelo sistema:
 *  - $api
 *  - $instancia
 *  - $token
 *  - $telefone
 *  - $mensagem
 * =====================================================================
 */

// -----------------------------------------------------------
// Defaults (evita warnings quando este arquivo é acessado direto)
// -----------------------------------------------------------
$api = isset($api) ? (string)$api : '';
$instancia = isset($instancia) ? (string)$instancia : '';
$token = isset($token) ? (string)$token : '';
$telefone = isset($telefone) ? (string)$telefone : '';
$mensagem = isset($mensagem) ? (string)$mensagem : '';

$response = '';
$http = 0;
$errno = 0;
$err = '';
$status_mensagem = isset($status_mensagem) ? (string)$status_mensagem : '';


// -----------------------------------------------------------
// Helpers
// -----------------------------------------------------------
if (!function_exists('bb_whats_log')) {
	function bb_whats_log(string $msg): void {
		@file_put_contents(__DIR__ . '/error_log', '[' . date('d-M-Y H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
		@error_log('BarberBot WhatsApp: ' . $msg);
	}
}

if (!function_exists('bb_whats_tem_prefixo_internacional')) {
	/**
	 * Identifica quando o número já veio explicitamente internacional,
	 * por exemplo:
	 * - +244 923 000 000
	 * - +1 (305) 555-1212
	 * - 00351 912 345 678
	 */
	function bb_whats_tem_prefixo_internacional(string $telefone): bool {
		$raw = trim((string)$telefone);
		return (bool)preg_match('/^\s*\+/', $raw) || (bool)preg_match('/^\s*00/', $raw);
	}
}

if (!function_exists('bb_whats_digits')) {
	/**
	 * Retorna somente dígitos.
	 * Regras:
	 * - remove tudo que não for número
	 * - se começar com 00, remove o prefixo internacional 00
	 * - se o número já veio com +DDI/00, preserva como internacional
	 * - se tiver 10/11 dígitos SEM prefixo internacional, assume BR sem DDI e prefixa 55
	 * - demais tamanhos permanecem como informados (sem chute)
	 */
	function bb_whats_digits(string $telefone): string {
		$raw = trim((string)$telefone);
		$digits = preg_replace('/\D+/', '', $raw);
		$digits = trim((string)$digits);

		if ($digits === '') {
			return '';
		}

		$temPrefixoInternacional = bb_whats_tem_prefixo_internacional($raw);

		// Formato internacional com 00 na frente -> remove apenas o prefixo 00
		if (strpos($digits, '00') === 0) {
			$digits = substr($digits, 2);
		}

		// Se o usuário/sistema informou explicitamente +DDI ou 00DDI,
		// não força 55 mesmo quando o total de dígitos for 10/11.
		if ($temPrefixoInternacional) {
			return $digits;
		}

		// Brasil sem DDI
		if (strlen($digits) === 10 || strlen($digits) === 11) {
			$digits = '55' . $digits;
		}

		return $digits;
	}
}

if (!function_exists('bb_whats_menuia_to_digits')) {
	function bb_whats_menuia_to_digits(string $telefone): string {
		return bb_whats_digits($telefone);
	}
}

if (!function_exists('bb_whats_menuia_to_plus')) {
	function bb_whats_menuia_to_plus(string $telefone): string {
		$digits = bb_whats_digits($telefone);
		if ($digits === '') {
			return '';
		}
		return '+' . $digits;
	}
}

if (!function_exists('bb_whats_db_name')) {
	function bb_whats_db_name($pdo): string {
		try {
			if ($pdo instanceof PDO) {
				$st = $pdo->query("SELECT DATABASE()");
				$db = $st ? $st->fetchColumn() : '';
				return $db ? (string)$db : '';
			}
		} catch (Throwable $e) {
		}
		return '';
	}
}

if (!function_exists('bb_whats_fill_config_from_current_db')) {
	/**
	 * Usa o PDO já carregado do contexto atual (MASTER ou TENANT)
	 * para preencher api/token/instancia sem trocar a lógica do sistema.
	 */
	function bb_whats_fill_config_from_current_db(): void {
		global $pdo, $api, $token, $instancia;

		if (!($pdo instanceof PDO)) {
			return;
		}

		if (trim((string)$api) !== '' && trim((string)$token) !== '' && trim((string)$instancia) !== '') {
			return;
		}

		try {
			$st = $pdo->query("SELECT api, token, instancia FROM config LIMIT 1");
			$row = $st ? $st->fetch(PDO::FETCH_ASSOC) : false;

			if (is_array($row)) {
				if (trim((string)$api) === '' && isset($row['api'])) {
					$api = (string)$row['api'];
				}
				if (trim((string)$token) === '' && isset($row['token'])) {
					$token = (string)$row['token'];
				}
				if (trim((string)$instancia) === '' && isset($row['instancia'])) {
					$instancia = (string)$row['instancia'];
				}
			}
		} catch (Throwable $e) {
			bb_whats_log('Falha ao ler config do banco atual: ' . $e->getMessage());
		}
	}
}

if (!function_exists('bb_whats_bootstrap_context')) {
	/**
	 * Se as credenciais não vieram do arquivo chamador,
	 * tenta usar /sistema/conexao.php, que no marketplace já resolve:
	 * - /loja/{slug}/... => banco do tenant
	 * - /sistema/...     => banco master
	 */
	function bb_whats_bootstrap_context(): void {
		global $pdo, $api, $token, $instancia;

		if (trim((string)$api) !== '' && trim((string)$token) !== '' && trim((string)$instancia) !== '') {
			return;
		}

		if (!($pdo instanceof PDO)) {
			$conexaoFile = dirname(__DIR__) . '/sistema/conexao.php';
			if (is_file($conexaoFile)) {
				require_once $conexaoFile;
				$bbTimezoneTenant = dirname(__DIR__) . '/sistema/timezone_tenant.php';
				if (is_file($bbTimezoneTenant)) {
					require_once $bbTimezoneTenant;
				}
			}
		}

		bb_whats_fill_config_from_current_db();
	}
}

if (!function_exists('bb_whats_menuia_send_once')) {
	function bb_whats_menuia_send_once(string $token, string $instancia, string $to, string $mensagem): array {
		$payloadArr = array(
			'appkey'  => (string)$token,
			'authkey' => (string)$instancia,
			'to'      => (string)$to,
			'message' => (string)$mensagem,
			'licence' => 'hugocursos',
		);

		$payload = json_encode($payloadArr, JSON_UNESCAPED_UNICODE);

		if ($payload === false) {
			return array(
				'ok' => false,
				'http' => 0,
				'errno' => 0,
				'err' => 'json_encode payload falhou',
				'response' => '',
			);
		}

		$curl = curl_init();
		curl_setopt_array($curl, array(
			CURLOPT_URL            => 'https://chatbot.menuia.com/api/create-message',
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING       => '',
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST  => 'POST',
			CURLOPT_POSTFIELDS     => $payload,
			CURLOPT_HTTPHEADER     => array(
				'Content-Type: application/json',
				'Accept: application/json',
			),
		));

		$response = curl_exec($curl);
		$errno = curl_errno($curl);
		$err = curl_error($curl);
		$http = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
		curl_close($curl);

		$ok = true;

		if ($response === false || $errno != 0 || $http < 200 || $http >= 300) {
			$ok = false;
		}

		$respJson = json_decode((string)$response, true);
		if ($ok && is_array($respJson) && isset($respJson['status']) && (int)$respJson['status'] !== 200) {
			$ok = false;
		}

		return array(
			'ok' => $ok,
			'http' => $http,
			'errno' => $errno,
			'err' => $err,
			'response' => (string)$response,
		);
	}
}

if (!function_exists('bb_whats_context_label')) {
	function bb_whats_context_label(): string {
		$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

		$ctx = 'site';
		if (strpos($reqPath, '/loja/') === 0 || !empty($_GET['loja']) || !empty($_POST['loja']) || !empty($_SESSION['tenant_slug'])) {
			$ctx = 'tenant';
		} elseif (strpos($reqPath, '/sistema') === 0) {
			$ctx = 'master';
		}

		return $ctx;
	}
}


// -----------------------------------------------------------
// Entradas
// -----------------------------------------------------------
$telefone = trim((string)$telefone);
$mensagem = (string)$mensagem;

if ($telefone === '' || $mensagem === '') {
	return;
}

// no sistema pode vir %0A
$mensagem = str_replace("%0A", "\n", (string)$mensagem);

// resolve credenciais pelo contexto atual do marketplace
bb_whats_bootstrap_context();

$api = strtolower(trim((string)$api));

// Compatibilidade com telas antigas do sistema:
// algumas rotinas gravam/mandam "Api Paga" no campo de API,
// mas o provedor real configurado é Menuia.
$apiCheck = preg_replace('/[^a-z0-9]+/', '', $api);
if ($apiCheck === 'apipaga' || $apiCheck === 'menuia') {
	$api = 'menuia';
}

$token = trim((string)$token);
$instancia = trim((string)$instancia);

$ctx = bb_whats_context_label();
$dbAtual = '';
if (isset($pdo) && $pdo instanceof PDO) {
	$dbAtual = bb_whats_db_name($pdo);
}

if ($api === '') {
	$api = 'menuia';
}

if ($token === '' || $instancia === '') {
	bb_whats_log(
		'Credenciais ausentes | ctx=' . $ctx .
		' | db=' . $dbAtual .
		' | api=' . $api .
		' | token=' . ($token !== '' ? 'ok' : 'vazio') .
		' | instancia=' . ($instancia !== '' ? 'ok' : 'vazio')
	);
	return;
}


// -----------------------------------------------------------
// MENU IA
// -----------------------------------------------------------
if ($api === 'menuia') {

	$toDigits = bb_whats_menuia_to_digits((string)$telefone);
	$toPlus = bb_whats_menuia_to_plus((string)$telefone);

	if ($toDigits === '') {
		bb_whats_log(
			'Menuia Falha | telefone vazio/invalid | ctx=' . $ctx .
			' | db=' . $dbAtual .
			' | raw=' . substr((string)$telefone, 0, 60)
		);
		return;
	}

	// 1) tenta no formato sem "+"
	$resDigits = bb_whats_menuia_send_once($token, $instancia, $toDigits, $mensagem);
	if (!empty($resDigits['ok'])) {
		$status_mensagem = 'Mensagem enviada com sucesso.';
		return;
	}

	// 2) fallback com "+"
	// só tenta novamente se realmente mudou o valor
	if ($toPlus !== '' && $toPlus !== $toDigits) {
		$resPlus = bb_whats_menuia_send_once($token, $instancia, $toPlus, $mensagem);
		if (!empty($resPlus['ok'])) {
			$status_mensagem = 'Mensagem enviada com sucesso.';
			return;
		}

		bb_whats_log(
			'Menuia Falha | ctx=' . $ctx .
			' | db=' . $dbAtual .
			' | to1=' . $toDigits .
			' | http1=' . (int)($resDigits['http'] ?? 0) .
			' | errno1=' . (int)($resDigits['errno'] ?? 0) .
			' | err1=' . (string)($resDigits['err'] ?? '') .
			' | resp1=' . substr((string)($resDigits['response'] ?? ''), 0, 300) .
			' | to2=' . $toPlus .
			' | http2=' . (int)($resPlus['http'] ?? 0) .
			' | errno2=' . (int)($resPlus['errno'] ?? 0) .
			' | err2=' . (string)($resPlus['err'] ?? '') .
			' | resp2=' . substr((string)($resPlus['response'] ?? ''), 0, 300)
		);
		return;
	}

	bb_whats_log(
		'Menuia Falha | ctx=' . $ctx .
		' | db=' . $dbAtual .
		' | to=' . $toDigits .
		' | http=' . (int)($resDigits['http'] ?? 0) .
		' | errno=' . (int)($resDigits['errno'] ?? 0) .
		' | err=' . (string)($resDigits['err'] ?? '') .
		' | resp=' . substr((string)($resDigits['response'] ?? ''), 0, 300)
	);
	return;
}


// -----------------------------------------------------------
// ENVIAME / OUTROS
// -----------------------------------------------------------
$paraDigits = bb_whats_digits((string)$telefone);

if ($paraDigits === '') {
	bb_whats_log(
		'Enviame Falha | telefone vazio/invalid | ctx=' . $ctx .
		' | db=' . $dbAtual .
		' | raw=' . substr((string)$telefone, 0, 60)
	);
	return;
}

$payloadArr = array(
	'instancia' => (string)$instancia,
	'token'     => (string)$token,
	'para'      => (string)$paraDigits,
	'mensagem'  => (string)$mensagem,
);

$payload = json_encode($payloadArr, JSON_UNESCAPED_UNICODE);
if ($payload === false) {
	bb_whats_log(
		'Enviame Falha | json_encode payload | ctx=' . $ctx .
		' | db=' . $dbAtual .
		' | para=' . $paraDigits
	);
	return;
}

$curl = curl_init();
curl_setopt_array($curl, array(
	CURLOPT_URL            => 'https://api.enviame.com.br/whatsapp/enviar/texto',
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_ENCODING       => '',
	CURLOPT_MAXREDIRS      => 10,
	CURLOPT_TIMEOUT        => 30,
	CURLOPT_CONNECTTIMEOUT => 10,
	CURLOPT_FOLLOWLOCATION => true,
	CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
	CURLOPT_CUSTOMREQUEST  => 'POST',
	CURLOPT_POSTFIELDS     => $payload,
	CURLOPT_HTTPHEADER     => array(
		'Content-Type: application/json',
		'Accept: application/json',
	),
));

$response = curl_exec($curl);
$errno = curl_errno($curl);
$err = curl_error($curl);
$http = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

if ($response === false || $errno != 0 || $http < 200 || $http >= 300) {
	bb_whats_log(
		'Enviame Falha | ctx=' . $ctx .
		' | db=' . $dbAtual .
		' | para=' . $paraDigits .
		' | http=' . $http .
		' | errno=' . $errno .
		' | err=' . $err .
		' | resp=' . substr((string)$response, 0, 300)
	);
	return;
}

$status_mensagem = 'Mensagem enviada com sucesso.';

return;
?>