<?php
/**
 * =====================================================================
 * BarberBot - Agendamento de Mensagem WhatsApp (Menuia / Enviame)
 * Arquivo: /ajax/api-agendar.php
 *
 * AJUSTE FECHADO NO PONTO CERTO:
 * - Mantém o fluxo atual por require/include.
 * - Não cria tabela e não altera conexão manualmente.
 * - Resolve api/token/instancia pelo banco do contexto atual:
 *      /loja/{slug}/...  => Banco Individual do TENANT
 *      /sistema/...      => Banco Global/Administrativo MASTER
 * - Usa /sistema/conexao.php quando o arquivo chamador ainda não carregou PDO.
 * - Normaliza telefone sem quebrar padrão BR.
 * - Remove timeout infinito do cURL para evitar mensagens presas como
 *   "Aguardando mensagem".
 * - Mantém as variáveis de retorno já usadas pelo sistema:
 *      $response, $hash, $status_mensagem
 *
 * Variáveis esperadas pelo sistema:
 *  - $api
 *  - $instancia
 *  - $token
 *  - $telefone
 *  - $mensagem
 *  - $data_mensagem
 * =====================================================================
 */

// -----------------------------------------------------------
// Defaults
// -----------------------------------------------------------
$api = isset($api) ? (string)$api : '';
$instancia = isset($instancia) ? (string)$instancia : '';
$token = isset($token) ? (string)$token : '';
$telefone = isset($telefone) ? (string)$telefone : '';
$mensagem = isset($mensagem) ? (string)$mensagem : '';
$data_mensagem = isset($data_mensagem) ? (string)$data_mensagem : '';

$response = '';
$hash = isset($hash) ? (string)$hash : '';
$status_mensagem = isset($status_mensagem) ? (string)$status_mensagem : '';


// -----------------------------------------------------------
// Helpers
// -----------------------------------------------------------
if (!function_exists('bb_agendar_log')) {
	function bb_agendar_log(string $msg): void {
		@file_put_contents(__DIR__ . '/error_log', '[' . date('d-M-Y H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
		@error_log('BarberBot WhatsApp Agendar: ' . $msg);
	}
}

if (!function_exists('bb_agendar_tem_prefixo_internacional')) {
	function bb_agendar_tem_prefixo_internacional(string $telefone): bool {
		$raw = trim((string)$telefone);
		return (bool)preg_match('/^\s*\+/', $raw) || (bool)preg_match('/^\s*00/', $raw);
	}
}

if (!function_exists('bb_agendar_digits')) {
	function bb_agendar_digits(string $telefone): string {
		$raw = trim((string)$telefone);
		$digits = preg_replace('/\D+/', '', $raw);
		$digits = trim((string)$digits);

		if ($digits === '') {
			return '';
		}

		$temPrefixoInternacional = bb_agendar_tem_prefixo_internacional($raw);

		if (strpos($digits, '00') === 0) {
			$digits = substr($digits, 2);
		}

		if ($temPrefixoInternacional) {
			return $digits;
		}

		if (strlen($digits) === 10 || strlen($digits) === 11) {
			$digits = '55' . $digits;
		}

		return $digits;
	}
}

if (!function_exists('bb_agendar_db_name')) {
	function bb_agendar_db_name($pdo): string {
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

if (!function_exists('bb_agendar_fill_config_from_current_db')) {
	function bb_agendar_fill_config_from_current_db(): void {
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
			bb_agendar_log('Falha ao ler config do banco atual: ' . $e->getMessage());
		}
	}
}

if (!function_exists('bb_agendar_bootstrap_context')) {
	function bb_agendar_bootstrap_context(): void {
		global $pdo, $api, $token, $instancia;

		if (trim((string)$api) !== '' && trim((string)$token) !== '' && trim((string)$instancia) !== '') {
			return;
		}

		if (!($pdo instanceof PDO)) {
			$conexaoFile = dirname(__DIR__) . '/sistema/conexao.php';
			if (is_file($conexaoFile)) {
				require_once $conexaoFile;

				$timezoneFile = __DIR__ . '/timezone_tenant.php';
				if (is_file($timezoneFile)) {
					require_once $timezoneFile;
				}
			}
		}

		bb_agendar_fill_config_from_current_db();
	}
}

if (!function_exists('bb_agendar_context_label')) {
	function bb_agendar_context_label(): string {
		$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

		if (strpos($reqPath, '/loja/') === 0 || !empty($_GET['loja']) || !empty($_POST['loja']) || !empty($_SESSION['tenant_slug'])) {
			return 'tenant';
		}

		if (strpos($reqPath, '/sistema') === 0) {
			return 'master';
		}

		return 'site';
	}
}

if (!function_exists('bb_agendar_curl_post')) {
	function bb_agendar_curl_post(string $url, $postfields, array $headers = array()): array {
		$curl = curl_init();

		curl_setopt_array($curl, array(
			CURLOPT_URL            => $url,
			CURLOPT_RETURNTRANSFER => true,
			CURLOPT_ENCODING       => '',
			CURLOPT_MAXREDIRS      => 10,
			CURLOPT_TIMEOUT        => 30,
			CURLOPT_CONNECTTIMEOUT => 10,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
			CURLOPT_CUSTOMREQUEST  => 'POST',
			CURLOPT_POSTFIELDS     => $postfields,
			CURLOPT_HTTPHEADER     => $headers,
		));

		$resposta = curl_exec($curl);
		$errno = curl_errno($curl);
		$err = curl_error($curl);
		$http = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);

		curl_close($curl);

		return array(
			'response' => (string)$resposta,
			'http' => $http,
			'errno' => $errno,
			'err' => $err,
			'ok' => ($resposta !== false && $errno === 0 && $http >= 200 && $http < 300),
		);
	}
}


// -----------------------------------------------------------
// Entradas
// -----------------------------------------------------------
$telefone = trim((string)$telefone);
$mensagem = str_replace("%0A", "\n", (string)$mensagem);
$data_mensagem = trim((string)$data_mensagem);

if ($telefone === '' || $mensagem === '' || $data_mensagem === '') {
	return;
}

// Resolve credenciais pelo contexto atual do marketplace
bb_agendar_bootstrap_context();

$api = strtolower(trim((string)$api));
$token = trim((string)$token);
$instancia = trim((string)$instancia);

$ctx = bb_agendar_context_label();
$dbAtual = '';
if (isset($pdo) && $pdo instanceof PDO) {
	$dbAtual = bb_agendar_db_name($pdo);
}

if ($api === '') {
	$api = 'menuia';
}

if ($token === '' || $instancia === '') {
	bb_agendar_log(
		'Credenciais ausentes | ctx=' . $ctx .
		' | db=' . $dbAtual .
		' | api=' . $api .
		' | token=' . ($token !== '' ? 'ok' : 'vazio') .
		' | instancia=' . ($instancia !== '' ? 'ok' : 'vazio')
	);
	return;
}

$telefoneNormalizado = bb_agendar_digits($telefone);

if ($telefoneNormalizado === '') {
	bb_agendar_log(
		'Telefone vazio/invalid | ctx=' . $ctx .
		' | db=' . $dbAtual .
		' | raw=' . substr($telefone, 0, 60)
	);
	return;
}


// -----------------------------------------------------------
// MENU IA
// -----------------------------------------------------------
if ($api === 'menuia') {
	$postfields = array(
		'appkey'      => $token,
		'authkey'     => $instancia,
		'to'          => $telefoneNormalizado,
		'message'     => $mensagem,
		'licence'     => 'hugocursos',
		'agendamento' => $data_mensagem,
		'file'        => '',
		'nomearquivo' => '',
	);

	$result = bb_agendar_curl_post(
		'https://chatbot.menuia.com/api/create-message',
		$postfields,
		array('Accept: application/json')
	);

	$response = (string)$result['response'];
	$responseData = json_decode($response, true);
	$hash = isset($responseData['id']) ? (string)$responseData['id'] : '';

	if (!empty($result['ok']) && $hash !== '') {
		$status_mensagem = 'Mensagem agendada com sucesso.';
		return;
	}

	bb_agendar_log(
		'Menuia Falha | ctx=' . $ctx .
		' | db=' . $dbAtual .
		' | to=' . $telefoneNormalizado .
		' | http=' . (int)$result['http'] .
		' | errno=' . (int)$result['errno'] .
		' | err=' . (string)$result['err'] .
		' | resp=' . substr($response, 0, 300)
	);
	return;
}


// -----------------------------------------------------------
// ENVIAME / OUTROS
// -----------------------------------------------------------
$payloadArr = array(
	'instancia' => $instancia,
	'token'     => $token,
	'para'      => $telefoneNormalizado,
	'mensagem'  => $mensagem,
	'data'      => $data_mensagem,
);

$payload = json_encode($payloadArr, JSON_UNESCAPED_UNICODE);

if ($payload === false) {
	bb_agendar_log(
		'Enviame Falha | json_encode payload | ctx=' . $ctx .
		' | db=' . $dbAtual .
		' | para=' . $telefoneNormalizado
	);
	return;
}

$result = bb_agendar_curl_post(
	'https://api.enviame.com.br/whatsapp/agendar/texto',
	$payload,
	array(
		'Content-Type: application/json',
		'Accept: application/json',
	)
);

$response = (string)$result['response'];
$res = json_decode($response, true);
$hash = isset($res['codigo']) ? (string)$res['codigo'] : '';

if (!empty($result['ok']) && $hash !== '') {
	$status_mensagem = 'Mensagem agendada com sucesso.';
	return;
}

bb_agendar_log(
	'Enviame Falha | ctx=' . $ctx .
	' | db=' . $dbAtual .
	' | para=' . $telefoneNormalizado .
	' | http=' . (int)$result['http'] .
	' | errno=' . (int)$result['errno'] .
	' | err=' . (string)$result['err'] .
	' | resp=' . substr($response, 0, 300)
);

return;
?>