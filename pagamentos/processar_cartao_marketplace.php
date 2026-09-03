<?php
declare(strict_types=1);

require_once __DIR__ . '/timezone_pagamentos.php';
bb_pagamentos_aplicar_timezone();
header('Content-Type: application/json; charset=utf-8');

function bb_json_saida(array $dados): void {
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function bb_market_cfg_proc(): array {
    $cfgFile = __DIR__ . '/../config/marketplace.php';
    if (!is_file($cfgFile)) {
        bb_json_saida(['ok' => false, 'msg' => 'Config do marketplace não encontrada.']);
    }

    $cfg = require $cfgFile;
    if (!is_array($cfg) || empty($cfg['master']) || !is_array($cfg['master'])) {
        bb_json_saida(['ok' => false, 'msg' => 'Config do marketplace inválida.']);
    }

    return $cfg;
}

function bb_market_pdo_proc(array $cfg): PDO {
    return new PDO(
        "mysql:host={$cfg['master']['host']};dbname={$cfg['master']['db']};charset=utf8mb4",
        $cfg['master']['user'],
        (string)($cfg['master']['pass'] ?? ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
}

function bb_market_cfg_db_pdo_proc(array $dbCfg, array $fallbackCfg = []): ?PDO {
    $host = trim((string)($dbCfg['host'] ?? ($fallbackCfg['host'] ?? '')));
    $db = preg_replace('/[^a-z0-9_]/i', '', (string)($dbCfg['db'] ?? ''));
    $user = trim((string)($dbCfg['user'] ?? ($fallbackCfg['user'] ?? '')));
    $pass = array_key_exists('pass', $dbCfg)
        ? (string)$dbCfg['pass']
        : (string)($fallbackCfg['pass'] ?? '');

    if ($host === '' || $db === '' || $user === '') {
        return null;
    }

    try {
        $pdoCfg = new PDO(
            "mysql:host={$host};dbname={$db};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        $dbAtual = (string)$pdoCfg->query('SELECT DATABASE()')->fetchColumn();
        if ($dbAtual !== $db) {
            return null;
        }

        return $pdoCfg;
    } catch (Throwable $e) {
        return null;
    }
}

function bb_market_global_config_pdos_proc(array $cfg): array {
    $masterCfg = isset($cfg['master']) && is_array($cfg['master']) ? $cfg['master'] : [];
    $configuracoes = [$masterCfg];

    if (!empty($cfg['auth_master']) && is_array($cfg['auth_master'])) {
        $configuracoes[] = $cfg['auth_master'];
    }

    $pdos = [];
    $adicionados = [];

    foreach ($configuracoes as $dbCfg) {
        if (!is_array($dbCfg)) {
            continue;
        }

        $pdoCfg = bb_market_cfg_db_pdo_proc($dbCfg, $masterCfg);
        if (!($pdoCfg instanceof PDO)) {
            continue;
        }

        try {
            $chave = (string)$pdoCfg->query('SELECT DATABASE()')->fetchColumn();
        } catch (Throwable $e) {
            continue;
        }

        if ($chave === '' || isset($adicionados[$chave])) {
            continue;
        }

        $adicionados[$chave] = true;
        $pdos[] = $pdoCfg;
    }

    return $pdos;
}

function bb_market_cfg_column_exists_proc(PDO $pdo, string $table, string $column): bool {
    try {
        $st = $pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1'
        );
        $st->execute([':t' => $table, ':c' => $column]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function bb_market_get_cfg_value_proc(array $cfg, string $campo): string {
    $campo = preg_replace('/[^a-z0-9_]/i', '', $campo);
    if ($campo === '') {
        return '';
    }

    $fontes = [$cfg];
    if (!empty($cfg['app']) && is_array($cfg['app'])) {
        $fontes[] = $cfg['app'];
    }
    if (!empty($cfg['billing']) && is_array($cfg['billing'])) {
        $fontes[] = $cfg['billing'];
    }

    foreach ($fontes as $fonte) {
        $valor = trim((string)($fonte[$campo] ?? ''));
        if ($valor !== '') {
            return $valor;
        }

        if ($campo === 'access_token_mp') {
            $valorAlternativo = trim((string)($fonte['mp_access_token'] ?? ''));
            if ($valorAlternativo !== '') {
                return $valorAlternativo;
            }
        }
    }

    foreach (bb_market_global_config_pdos_proc($cfg) as $pdoCfg) {
        if (!bb_market_cfg_column_exists_proc($pdoCfg, 'config', $campo)) {
            continue;
        }

        try {
            $row = $pdoCfg->query("SELECT `{$campo}` FROM config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $valor = trim((string)($row[$campo] ?? ''));
            if ($valor !== '') {
                return $valor;
            }
        } catch (Throwable $e) {
            // Continua somente entre bancos globais permitidos.
        }
    }

    return '';
}

$cfg = bb_market_cfg_proc();
$pdo = bb_market_pdo_proc($cfg);

$raw = file_get_contents('php://input') ?: '';
$dados = json_decode($raw, true);

if (!is_array($dados)) {
    bb_json_saida(['ok' => false, 'msg' => 'Payload inválido.']);
}

$faturaId = (int)($dados['fatura'] ?? 0);
if ($faturaId <= 0) {
    bb_json_saida(['ok' => false, 'msg' => 'Fatura inválida.']);
}

$sql = "
    SELECT
        f.id,
        f.tenant_id,
        f.vencimento,
        f.valor,
        f.status,
        t.slug,
        t.nome,
        t.email,
        t.dono_email
    FROM marketplace_faturas f
    INNER JOIN tenants t ON t.id = f.tenant_id
    WHERE f.id = :id
    LIMIT 1
";
$st = $pdo->prepare($sql);
$st->execute([':id' => $faturaId]);
$fatura = $st->fetch(PDO::FETCH_ASSOC);

if (!$fatura) {
    bb_json_saida(['ok' => false, 'msg' => 'Fatura não encontrada.']);
}

if ((string)($fatura['status'] ?? '') === 'paid') {
    bb_json_saida(['ok' => false, 'msg' => 'Esta cobrança já foi paga.']);
}

$mpToken = bb_market_get_cfg_value_proc($cfg, 'access_token_mp');
if ($mpToken === '') {
    bb_json_saida(['ok' => false, 'msg' => 'Access Token do Mercado Pago não configurado.']);
}

$emailPayer = trim((string)($dados['payer']['email'] ?? ''));
if ($emailPayer === '') $emailPayer = trim((string)($fatura['email'] ?? ''));
if ($emailPayer === '') $emailPayer = trim((string)($fatura['dono_email'] ?? ''));
if ($emailPayer === '') {
    $hostTmp = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $emailPayer = 'pagamentos@' . $hostTmp;
}

$tokenCartao = trim((string)($dados['token'] ?? ''));
$paymentMethodId = trim((string)($dados['payment_method_id'] ?? ''));
$issuerId = trim((string)($dados['issuer_id'] ?? ''));
$installments = (int)($dados['installments'] ?? 1);
$transactionAmount = (float)($dados['transaction_amount'] ?? 0);
$identificationType = trim((string)($dados['payer']['identification']['type'] ?? ''));
$identificationNumber = trim((string)($dados['payer']['identification']['number'] ?? ''));

if ($tokenCartao === '' || $paymentMethodId === '' || $transactionAmount <= 0) {
    bb_json_saida(['ok' => false, 'msg' => 'Dados do cartão incompletos.']);
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $scheme = ($_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : $scheme;
}
$host = $_SERVER['HTTP_HOST'] ?? 'localhost';
$baseUrl = $scheme . '://' . $host;

$webhookToken = '';
if (!empty($cfg['webhook_token'])) {
    $webhookToken = (string)$cfg['webhook_token'];
} elseif (!empty($cfg['app']['webhook_token'])) {
    $webhookToken = (string)$cfg['app']['webhook_token'];
}

$slug = (string)($fatura['slug'] ?? '');
$notifyUrl = $baseUrl . '/pagamentos/webhook_mp_marketplace.php';
$qs = [];
if ($webhookToken !== '') $qs[] = 'token=' . rawurlencode($webhookToken);
if ($slug !== '') $qs[] = 'loja=' . rawurlencode($slug);
if ($qs) $notifyUrl .= '?' . implode('&', $qs);

$body = [
    'transaction_amount' => $transactionAmount,
    'token' => $tokenCartao,
    'description' => 'Assinatura BarberBot - ' . ((string)($fatura['nome'] ?? $fatura['slug'] ?? 'Barbearia')),
    'installments' => $installments > 0 ? $installments : 1,
    'payment_method_id' => $paymentMethodId,
    'issuer_id' => $issuerId !== '' ? (int)$issuerId : null,
    'notification_url' => $notifyUrl,
    'external_reference' => (string)((int)($fatura['tenant_id'] ?? 0)),
    'payer' => [
        'email' => $emailPayer,
        'identification' => [
            'type' => $identificationType,
            'number' => $identificationNumber
        ]
    ]
];

$ch = curl_init('https://api.mercadopago.com/v1/payments');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'Authorization: Bearer ' . $mpToken,
        'Content-Type: application/json'
    ],
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($body),
    CURLOPT_TIMEOUT => 30
]);

$resp = curl_exec($ch);
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

$js = is_string($resp) ? json_decode($resp, true) : null;

if (!$resp || $http < 200 || $http >= 300 || !is_array($js)) {
    $msg = 'Erro ao processar pagamento com cartão.' . ($err !== '' ? ' ' . $err : '');
    bb_json_saida(['ok' => false, 'msg' => $msg]);
}

$status = (string)($js['status'] ?? '');
$paymentId = (string)($js['id'] ?? '');

if ($paymentId !== '') {
    try {
        $up = $pdo->prepare("
            UPDATE marketplace_faturas
               SET mp_payment_id = :pid
             WHERE id = :id
             LIMIT 1
        ");
        $up->execute([
            ':pid' => $paymentId,
            ':id'  => $faturaId
        ]);
    } catch (Throwable $e) {}
}

if ($status === 'approved') {
    bb_json_saida([
        'ok' => true,
        'msg' => 'Pagamento aprovado com sucesso.',
        'redirect' => 'pagamento_marketplace.php?fatura=' . $faturaId . '&ok=1'
    ]);
}

if ($status === 'in_process' || $status === 'pending') {
    bb_json_saida([
        'ok' => true,
        'msg' => 'Pagamento enviado. Aguarde a confirmação.',
        'redirect' => 'pagamento_marketplace.php?fatura=' . $faturaId . '&ok=1'
    ]);
}

$detalhe = trim((string)($js['status_detail'] ?? ''));
if ($detalhe === '') $detalhe = 'Pagamento não autorizado.';

bb_json_saida(['ok' => false, 'msg' => $detalhe]);
