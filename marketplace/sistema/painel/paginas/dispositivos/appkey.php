<?php
require_once __DIR__ . '/../_context_bootstrap.php';
// /public_html/sistema/painel/paginas/dispositivos/appkey.php
// Gera/valida AppKey do dispositivo e salva na tabela config.token (padrão do sistema)
// ✅ Compatível: MASTER x TENANT (banco individual) via /sistema/conexao.php
header('Content-Type: application/json; charset=utf-8');

$__uriPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

// Detecta tenant (se existir)
$tenant = '';
if (!empty($_GET['loja'])) {
	$tenant = preg_replace('/[^a-z0-9_-]/i', '', (string)$_GET['loja']);
} elseif (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__uriPath, $__m)) {
	$tenant = preg_replace('/[^a-z0-9_-]/i', '', (string)$__m[1]);
} elseif (session_status() === PHP_SESSION_ACTIVE) {
	if (!empty($_SESSION['loja'])) {
		$tenant = preg_replace('/[^a-z0-9_-]/i', '', (string)$_SESSION['loja']);
	} elseif (!empty($_SESSION['tenant_slug'])) {
		$tenant = preg_replace('/[^a-z0-9_-]/i', '', (string)$_SESSION['tenant_slug']);
	}
}

// força para a conexão enxergar o tenant
if ($tenant !== '' && empty($_GET['loja'])) {
	$_GET['loja'] = $tenant;
}

require_once __DIR__ . '/../../../conexao.php';

if (!isset($pdo) || !$pdo) {
	echo json_encode(['status' => 500, 'message' => 'Conexão não inicializada'], JSON_UNESCAPED_UNICODE);
	exit();
}

// Entrada
$appkey = isset($_POST['appkey']) ? trim((string)$_POST['appkey']) : '';
$appkey = preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $appkey);

$status = 500;

try {

	if ($appkey === '') {

		// Reaproveita um dispositivo pendente (status_api NULL)
		$st = $pdo->prepare("SELECT * FROM dispositivos WHERE status_api IS NULL LIMIT 1");
		$st->execute();
		$dispositivo = $st->fetch(PDO::FETCH_ASSOC);

		if (!$dispositivo) {

			// gera appkey único (mantém ideia do sistema)
			$appkey = uniqid('appkey_', true);

			$ins = $pdo->prepare("INSERT INTO dispositivos (appkey, status) VALUES (:appkey, :status)");
			$ins->execute([':appkey' => $appkey, ':status' => '']);

			// salva no config (token) - atualiza primeira linha
			if (bb_table_exists($pdo, 'config')) {
				$up = $pdo->prepare("UPDATE config SET token = :token ORDER BY id ASC LIMIT 1");
				$up->execute([':token' => $appkey]);
			}

			$status = 200;

		} else {
			$appkey = (string)($dispositivo['appkey'] ?? '');
			$status = 200;
		}

	} else {

		// valida se existe
		$st = $pdo->prepare("SELECT 1 FROM dispositivos WHERE appkey = :appkey LIMIT 1");
		$st->execute([':appkey' => $appkey]);
		$exists = (bool)$st->fetchColumn();

		$status = $exists ? 200 : 404;
	}

} catch (Throwable $e) {
	$status = 500;
}

echo json_encode(['appkey' => $appkey, 'status' => $status], JSON_UNESCAPED_UNICODE);
