<?php
// /public_html/pagamentos/webhook_marketplace.php
declare(strict_types=1);

// Webhook global: não usa sessão de loja nem HTTP_REFERER.
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

// 1) carrega config do marketplace (caminho correto)
$cfgFile = __DIR__ . '/../config/marketplace.php';
$cfg = is_file($cfgFile) ? require $cfgFile : null;
if (!is_array($cfg) || empty($cfg['master'])) {
	http_response_code(500);
	exit('config ausente');
}

// 2) segurança por token (SEM mudar lógica: continua exigindo token)
$WEBHOOK_TOKEN = '';
if (!empty($cfg['webhook_token'])) {
	$WEBHOOK_TOKEN = (string)$cfg['webhook_token'];
} elseif (!empty($cfg['app']['webhook_token'])) {
	$WEBHOOK_TOKEN = (string)$cfg['app']['webhook_token'];
}

if ($WEBHOOK_TOKEN === '') {
	// fallback (mantém compatibilidade com setups antigos)
	$WEBHOOK_TOKEN = 'COLOQUE_UM_TOKEN_FORTE_AQUI';
}

// valida token
$token = isset($_GET['token']) ? (string)$_GET['token'] : '';
if (!hash_equals($WEBHOOK_TOKEN, $token)) {
	http_response_code(403);
	exit('forbidden');
}

// 3) conecta no MASTER (marketplace)
try {
	$pdo = new PDO(
		"mysql:host={$cfg['master']['host']};dbname={$cfg['master']['db']};charset=utf8mb4",
		$cfg['master']['user'],
		$cfg['master']['pass'] ?? '',
		[
			PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
			PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
		]
	);
} catch (Throwable $e) {
	http_response_code(500);
	exit('falha master');
}

// 4) Access Token global do Mercado Pago.
$accessToken = '';
if (!empty($cfg['access_token_mp'])) {
    $accessToken = (string)$cfg['access_token_mp'];
} elseif (!empty($cfg['mp_access_token'])) {
    $accessToken = (string)$cfg['mp_access_token'];
} elseif (!empty($cfg['app']['access_token_mp'])) {
    $accessToken = (string)$cfg['app']['access_token_mp'];
} elseif (!empty($cfg['app']['mp_access_token'])) {
    $accessToken = (string)$cfg['app']['mp_access_token'];
} elseif (!empty($cfg['app']['access_token'])) {
    $accessToken = (string)$cfg['app']['access_token'];
}

// Token global do Mercado Pago: primeiro Banco Administrativo (auth_master),
// depois Banco Marketplace (master). Nunca usa banco individual/default_db de tenant.
$accessToken = trim((string)($accessToken ?? ''));

if ($accessToken === '') {
    $fontesGlobais = [];
    if (!empty($cfg['auth_master']) && is_array($cfg['auth_master'])) {
        $fontesGlobais[] = $cfg['auth_master'];
    }
    if (!empty($cfg['master']) && is_array($cfg['master'])) {
        $fontesGlobais[] = $cfg['master'];
    }

    foreach ($fontesGlobais as $fonteGlobal) {
        $gHost = trim((string)($fonteGlobal['host'] ?? ''));
        $gDb   = trim((string)($fonteGlobal['db'] ?? ''));
        $gUser = trim((string)($fonteGlobal['user'] ?? ''));
        $gPass = (string)($fonteGlobal['pass'] ?? '');

        if ($gHost === '' || $gDb === '' || $gUser === '') {
            continue;
        }

        try {
            $pdoGlobalConfig = new PDO(
                "mysql:host={$gHost};dbname={$gDb};charset=utf8mb4",
                $gUser,
                $gPass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            $bancoGlobalAtual = trim((string)$pdoGlobalConfig->query('SELECT DATABASE()')->fetchColumn());
            if (strcasecmp($bancoGlobalAtual, $gDb) !== 0) {
                continue;
            }

            $rowTokenGlobal = $pdoGlobalConfig->query("SELECT access_token_mp FROM config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($rowTokenGlobal && !empty($rowTokenGlobal['access_token_mp'])) {
                $accessToken = trim((string)$rowTokenGlobal['access_token_mp']);
                break;
            }
        } catch (Throwable $e) {
            // Webhook global permanece silencioso.
        }
    }
}

if ($accessToken === '') {
    http_response_code(500);
    exit('sem access token mp');
}

// 5) lê payload do Mercado Pago
$raw = file_get_contents('php://input');
$payload = json_decode((string)$raw, true) ?: [];

// Mercado Pago costuma mandar data.id (ou id)
$paymentId = $payload['data']['id'] ?? ($payload['id'] ?? null);

// fallback por GET (compatibilidade)
if (!$paymentId && isset($_GET['id'])) {
	$paymentId = $_GET['id'];
}

$paymentId = preg_replace('/\D+/', '', (string)$paymentId);
if (!$paymentId) {
	http_response_code(200);
	exit('ok');
}

// 6) busca detalhes do pagamento no MP
$ch = curl_init("https://api.mercadopago.com/v1/payments/" . urlencode((string)$paymentId));
curl_setopt_array($ch, [
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_HTTPHEADER => [
		"Authorization: Bearer {$accessToken}",
		"Content-Type: application/json"
	],
	CURLOPT_CONNECTTIMEOUT => 10,
	CURLOPT_TIMEOUT => 20,
]);
$res = curl_exec($ch);
curl_close($ch);

$pay = json_decode((string)$res, true);
if (!is_array($pay)) {
	http_response_code(200);
	exit('ok');
}

// 7) só aprova quando status = approved
$status = (string)($pay['status'] ?? '');
$externalRef = (string)($pay['external_reference'] ?? '');

if ($status !== 'approved' || $externalRef === '') {
	http_response_code(200);
	exit('ok');
}

// 8) external_reference vai ser "cobranca_{ID}"
if (!preg_match('/^cobranca_(\d+)$/', $externalRef, $m)) {
	http_response_code(200);
	exit('ok');
}
$cobrancaId = (int)$m[1];

// 9) marca cobrança como paga + renova vencimento do tenant + desbloqueia (mesma lógica)
try {
	$agoraSistema = date('Y-m-d H:i:s');
	$pdo->beginTransaction();

	$st = $pdo->prepare("SELECT * FROM cobrancas_mp WHERE id = :id LIMIT 1");
	$st->execute([':id' => $cobrancaId]);
	$c = $st->fetch();

	if (!$c) {
		$pdo->rollBack();
		http_response_code(200);
		exit('ok');
	}

	if (($c['status'] ?? '') !== 'paid') {

		$up = $pdo->prepare("
			UPDATE cobrancas_mp
			   SET status='paid',
			       mp_payment_id = :pid,
			       paid_at = :agora
			 WHERE id = :id
		");
		$up->execute([':pid' => (string)$paymentId, ':agora' => $agoraSistema, ':id' => $cobrancaId]);

		// 10) renova vencimento do tenant + desbloqueia
		$tenantId = (int)($c['tenant_id'] ?? 0);

		if ($tenantId > 0) {

			// regra: se vencimento já passou, renova a partir de hoje; senão soma 30 dias
			$t = $pdo->prepare("SELECT vencimento FROM tenants WHERE id = :id LIMIT 1");
			$t->execute([':id' => $tenantId]);
			$v = $t->fetchColumn();

			$vStr = is_string($v) ? trim((string)$v) : '';
			$venc = null;
			if ($vStr !== '' && $vStr !== '0000-00-00') {
				try {
					$venc = new DateTime($vStr);
				} catch (Throwable $e) {
					$venc = null;
				}
			}

			$hoje = new DateTime('today');

			if (!$venc || $venc < $hoje) {
				$novo = (new DateTime('today'))->modify('+30 days')->format('Y-m-d');
			} else {
				$novo = $venc->modify('+30 days')->format('Y-m-d');
			}

			$ut = $pdo->prepare("
				UPDATE tenants
				   SET status='active',
				       ativo=1,
				       blocked_at=NULL,
				       vencimento=:v,
				       ultimo_pagamento=:agora
				 WHERE id=:id
			");
			$ut->execute([':v' => $novo, ':agora' => $agoraSistema, ':id' => $tenantId]);
		}
	}

	$pdo->commit();
} catch (Throwable $e) {
	if ($pdo->inTransaction()) {
		$pdo->rollBack();
	}
	// webhook deve responder 200 para o MP (evita flood)
	http_response_code(200);
	exit('ok');
}

http_response_code(200);
echo "ok";
