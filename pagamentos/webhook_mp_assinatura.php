<?php
declare(strict_types=1);

/**
 * /public_html/pagamentos/webhook_mp_assinatura.php
 * Webhook global de assinatura do Marketplace.
 * Não usa sessão de loja nem HTTP_REFERER.
 */

require_once __DIR__ . '/timezone_pagamentos.php';
bb_pagamentos_aplicar_timezone();
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

// ------------------------- CONFIG MARKETPLACE -------------------------
function bb_localizar_arquivo(array $candidatos): string {
    foreach ($candidatos as $caminho) {
        if (is_string($caminho) && $caminho !== '' && is_file($caminho)) {
            return $caminho;
        }
    }
    return '';
}

// Tenta os caminhos reais sem alterar regra (evita "Config não encontrada" por path diferente)
$cfgFile = bb_localizar_arquivo([
    __DIR__ . '/config/marketplace.php',      // quando o webhook está na raiz do public_html
    __DIR__ . '/../config/marketplace.php',   // quando o webhook está em /pagamentos/
]);

if ($cfgFile === '') {
    http_response_code(500);
    exit("Config não encontrada.\n");
}

$cfg = require $cfgFile;

if (!is_array($cfg) || empty($cfg['master'])) {
    http_response_code(500);
    exit("Config inválida.\n");
}

function pdo_connect(string $host, string $db, string $user, string $pass): PDO {
    return new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]);
}

// ------------------------- CONEXÃO MASTER (MARKETPLACE) -------------------------
$pdoMaster = pdo_connect(
    (string)$cfg['master']['host'],
    (string)$cfg['master']['db'],
    (string)$cfg['master']['user'],
    (string)($cfg['master']['pass'] ?? '')
);

$tenantsTable = preg_replace('/[^a-z0-9_]/i', '', (string)($cfg['master']['table'] ?? 'tenants'));
if ($tenantsTable === '') {
    $tenantsTable = 'tenants';
}

// ------------------------- TOKEN MERCADO PAGO GLOBAL -------------------------
$mpToken = '';
$billing = $cfg['billing'] ?? [];
if (is_array($billing)) {
    $mpToken = (string)($billing['mp_access_token'] ?? $billing['access_token_mp'] ?? '');
}
if ($mpToken === '' && isset($cfg['app']) && is_array($cfg['app'])) {
    $mpToken = (string)($cfg['app']['mp_access_token'] ?? $cfg['app']['access_token_mp'] ?? '');
}

// Token global do Mercado Pago: primeiro Banco Administrativo (auth_master),
// depois Banco Marketplace (master). Nunca usa banco individual/default_db de tenant.
$mpToken = trim((string)($mpToken ?? ''));

if ($mpToken === '') {
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
                $mpToken = trim((string)$rowTokenGlobal['access_token_mp']);
                break;
            }
        } catch (Throwable $e) {
            // Webhook global permanece silencioso.
        }
    }
}

$mpToken = trim($mpToken);
if ($mpToken === '') {
    http_response_code(500);
    exit("mp_access_token ausente.\n");
}

/* 1) Pega payment_id do POST/GET de forma “tolerante” */
$raw = file_get_contents('php://input') ?: '';
$js = json_decode($raw, true);

$paymentId = null;

// casos comuns
if (isset($_GET['data_id'])) $paymentId = $_GET['data_id'];
if (!$paymentId && isset($_GET['id'])) $paymentId = $_GET['id'];
if (!$paymentId && is_array($js)) {
    if (isset($js['data']['id'])) $paymentId = $js['data']['id'];
    if (!$paymentId && isset($js['id'])) $paymentId = $js['id'];
}

$paymentId = $paymentId ? preg_replace('/\D+/', '', (string)$paymentId) : '';
if ($paymentId === '') {
    http_response_code(200);
    exit("OK (sem payment_id)\n");
}

/* 2) Consulta pagamento no Mercado Pago */
$ch = curl_init("https://api.mercadopago.com/v1/payments/{$paymentId}");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer {$mpToken}",
        "Content-Type: application/json"
    ],
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT => 30,
]);

$resp = curl_exec($ch);
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if ($resp === false || $http < 200 || $http >= 300) {
    http_response_code(200);
    exit("OK (não consegui buscar payment)\n");
}

$data = json_decode((string)$resp, true);
if (!is_array($data)) {
    http_response_code(200);
    exit("OK (json inválido)\n");
}

$status = (string)($data['status'] ?? '');
$external = (string)($data['external_reference'] ?? ''); // aqui colocamos tenantId no cron

if ($status !== 'approved' || $external === '') {
    http_response_code(200);
    exit("OK (status={$status})\n");
}

// Mantém a regra: external_reference representa o tenantId (tolerante para aceitar "tenant_123")
$tenantId = (int)preg_replace('/\D+/', '', $external);
if ($tenantId <= 0) {
    http_response_code(200);
    exit("OK (external inválido)\n");
}

/* 3) Renova vencimento + desbloqueia (MESMA LÓGICA ORIGINAL) */
$novoVencimentoTenant = (new DateTime('today'))->modify('+30 days')->format('Y-m-d');
$st = $pdoMaster->prepare("
    UPDATE `{$tenantsTable}`
       SET status='active',
           ativo=1,
           blocked_at=NULL,
           vencimento = ?
     WHERE id=?
");
$st->execute([$novoVencimentoTenant, $tenantId]);

http_response_code(200);
echo "OK (tenant {$tenantId} liberado)\n";
