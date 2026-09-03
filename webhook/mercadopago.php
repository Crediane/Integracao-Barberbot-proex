<?php
// /public_html/webhook/mercadopago.php
declare(strict_types=1);

require_once __DIR__ . '/../lib/saas_bootstrap.php';

$cfg = bb_load_marketplace_cfg();

$cfgMarket = (isset($cfg['master']) && is_array($cfg['master'])) ? $cfg['master'] : [];
$cfgAdmin  = (isset($cfg['auth_master']) && is_array($cfg['auth_master'])) ? $cfg['auth_master'] : [];

if (
  empty($cfgMarket['host']) || empty($cfgMarket['db']) || empty($cfgMarket['user'])
  || empty($cfgAdmin['host']) || empty($cfgAdmin['db']) || empty($cfgAdmin['user'])
) {
  http_response_code(500);
  exit('configuração global inválida');
}

$pdoMarket = bb_pdo(
  (string)$cfgMarket['host'],
  (string)$cfgMarket['db'],
  (string)$cfgMarket['user'],
  (string)($cfgMarket['pass'] ?? '')
);

$tenantsTable = preg_replace('/[^a-z0-9_]/i', '', (string)($cfgMarket['table'] ?? 'tenants'));
if ($tenantsTable === '') {
  $tenantsTable = 'tenants';
}

$pdoAdmin = bb_pdo(
  (string)$cfgAdmin['host'],
  (string)$cfgAdmin['db'],
  (string)$cfgAdmin['user'],
  (string)($cfgAdmin['pass'] ?? '')
);

$marketDbAtual = (string)$pdoMarket->query('SELECT DATABASE()')->fetchColumn();
$adminDbAtual  = (string)$pdoAdmin->query('SELECT DATABASE()')->fetchColumn();

if (
  strcasecmp($marketDbAtual, (string)$cfgMarket['db']) !== 0
  || strcasecmp($adminDbAtual, (string)$cfgAdmin['db']) !== 0
) {
  http_response_code(500);
  exit('isolamento global não confirmado');
}

$ownerCfg = bb_get_owner_config($pdoAdmin);
$mpAccessToken = trim((string)($ownerCfg['access_token_mp'] ?? ''));
if ($mpAccessToken === '') {
  http_response_code(500);
  exit('access_token_mp vazio');
}

// MP geralmente envia topic/type=payment&id=... :contentReference[oaicite:5]{index=5}
$topic = (string)($_GET['topic'] ?? $_GET['type'] ?? '');
$id    = (string)($_GET['id'] ?? $_GET['data_id'] ?? '');

if ($topic !== 'payment' || $id === '') {
  // Ignora outros tópicos (ou implemente depois)
  http_response_code(200);
  exit('ignored');
}

try {
  $pay = bb_mp_get_payment($mpAccessToken, $id);

  $status = (string)($pay['status'] ?? '');
  $extRef = (string)($pay['external_reference'] ?? '');
  $paidAt = (string)($pay['date_approved'] ?? '');

  // só libera quando aprovado
  if ($status !== 'approved') {
    http_response_code(200);
    exit('not approved');
  }

  // Esperamos external_reference = SaaS|inv:ID|tenant:ID :contentReference[oaicite:6]{index=6}
  if (!preg_match('/inv:(\d+)/', $extRef, $m)) {
    http_response_code(200);
    exit('no invoice');
  }
  $invoiceId = (int)$m[1];

  // carrega invoice
  $st = $pdoMarket->prepare("SELECT * FROM billing_invoices WHERE id=? LIMIT 1");
  $st->execute([$invoiceId]);
  $invoice = $st->fetch();

  if (!$invoice) {
    http_response_code(200);
    exit('invoice not found');
  }

  // se já está paga, ok
  if ((string)$invoice['status'] === 'paid') {
    http_response_code(200);
    exit('already paid');
  }

  // marca invoice como paga
  $pdoMarket->prepare("
    UPDATE billing_invoices
       SET status='paid',
           mp_payment_id=?,
           paid_at=NOW(),
           updated_at=NOW()
     WHERE id=?
  ")->execute([$id, $invoiceId]);

  $tenantId = (int)$invoice['tenant_id'];

  // atualiza vencimento do tenant para +1 mês (base: due_date)
  $due = DateTimeImmutable::createFromFormat('Y-m-d', (string)$invoice['due_date']) ?: new DateTimeImmutable('today');
  $nextDue = $due->modify('+1 month')->format('Y-m-d');

  // desbloqueia
  $pdoMarket->prepare("
    UPDATE {$tenantsTable}
       SET status='active',
           ativo=1,
           blocked_at=NULL,
           vencimento=?
     WHERE id=?
  ")->execute([$nextDue, $tenantId]);

  http_response_code(200);
  echo 'ok';

} catch (Throwable $e) {
  error_log('Webhook Mercado Pago Marketplace: ' . $e->getMessage());
  http_response_code(500);
  echo 'erro';
}
