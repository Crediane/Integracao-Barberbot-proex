<?php
if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

if (!function_exists('__bb_resolver_loja_slug')) {
  function __bb_resolver_loja_slug($valor) {
    if (is_array($valor) || is_object($valor)) {
      return '';
    }

    $valor = trim((string)$valor);
    if ($valor === '') {
      return '';
    }

    if (preg_match('~/loja/([a-zA-Z0-9_-]+)~', $valor, $m)) {
      return $m[1];
    }

    if (preg_match('~^[a-zA-Z0-9_-]+$~', $valor)) {
      return $valor;
    }

    return '';
  }
}

$__bb_loja_slug = '';

$__bb_fontes_loja = [
  $_GET['loja'] ?? '',
  $_POST['loja'] ?? '',
  $_REQUEST['loja'] ?? '',
  $_SERVER['REQUEST_URI'] ?? '',
  $_SERVER['HTTP_REFERER'] ?? '',
  $_SESSION['tenant_slug'] ?? '',
  $_SESSION['loja_slug'] ?? '',
  $_SESSION['loja'] ?? '',
  $_COOKIE['tenant_slug'] ?? '',
  $_COOKIE['loja_slug'] ?? '',
  $_COOKIE['loja'] ?? '',
];

foreach ($__bb_fontes_loja as $__bb_fonte_loja) {
  $__bb_slug_tmp = __bb_resolver_loja_slug($__bb_fonte_loja);
  if ($__bb_slug_tmp !== '') {
    $__bb_loja_slug = $__bb_slug_tmp;
    break;
  }
}

if ($__bb_loja_slug !== '') {
  $_GET['loja'] = $__bb_loja_slug;
  $_POST['loja'] = $__bb_loja_slug;
  $_REQUEST['loja'] = $__bb_loja_slug;
  $_SESSION['tenant_slug'] = $__bb_loja_slug;
  $_SESSION['loja_slug'] = $__bb_loja_slug;
}

if (!defined('RECIBO_EMBUTIDO') && !headers_sent()) {
  header('Content-Type: text/html; charset=UTF-8');
}
require_once __DIR__ . '/../../conexao.php';

// utils
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('moeda')) {
  function moeda($v){ return number_format((float)$v, 2, ',', '.'); }
}

// aceita id por POST (form) ou GET
$id = isset($_POST['id']) ? (int)$_POST['id'] : (int)($_GET['id'] ?? 0);
if ($id <= 0) { die('Recibo inválido'); }

// ===== registro principal =====
$sql = $pdo->prepare("SELECT * FROM receber WHERE id = :id LIMIT 1");
$sql->execute([':id' => $id]);
$rec = $sql->fetch(PDO::FETCH_ASSOC);
if (!$rec) { die('Conta não encontrada'); }

$pessoa      = (int)($rec['pessoa'] ?? 0);
$servico_id  = (int)($rec['servico'] ?? 0);
$descricao   = (string)($rec['descricao'] ?? '');
$data_pgto   = (string)($rec['data_pgto'] ?? '');   // yyyy-mm-dd (pode vir vazio)
$hora        = (string)($rec['hora'] ?? '');
$valor_linha = (float)($rec['valor'] ?? 0);
$pgto_linha  = (string)($rec['pgto'] ?? '');

// dados do cliente
$cliStmt = $pdo->prepare("SELECT nome, telefone FROM clientes WHERE id = :id LIMIT 1");
$cliStmt->execute([':id' => $pessoa]);
$cli = $cliStmt->fetch(PDO::FETCH_ASSOC);
$nome_cliente = (string)($cli['nome'] ?? 'Cliente');
$tel_cliente  = (string)($cli['telefone'] ?? '');

// dados do serviço (se não achar, usa a descrição da conta)
$svStmt = $pdo->prepare("SELECT nome FROM servicos WHERE id = :id LIMIT 1");
$svStmt->execute([':id' => $servico_id]);
$sv = $svStmt->fetch(PDO::FETCH_ASSOC);
$nome_servico = (string)($sv['nome'] ?? $descricao);

// ===== monta lista de itens agregados =====
// regra: mesmo cliente, mesmo serviço e MESMA data_pgto (pago = 'Sim').
// inclui o próprio id.
$itens = [];
$itens[] = [
  'desc'  => $nome_servico,
  'pgto'  => $pgto_linha,
  'valor' => $valor_linha,
];

// irmãos na mesma data_pgto
if ($data_pgto !== '') {
  $sqlIrmaos = $pdo->prepare("
    SELECT descricao, pgto, valor
      FROM receber
     WHERE id <> :id
       AND pessoa = :pessoa
       AND servico = :servico
       AND data_pgto = :dpgto
       AND pago = 'Sim'
     ORDER BY id
  ");
  $sqlIrmaos->execute([
    ':id'      => $id,
    ':pessoa'  => $pessoa,
    ':servico' => $servico_id,
    ':dpgto'   => $data_pgto,
  ]);
  foreach ($sqlIrmaos->fetchAll(PDO::FETCH_ASSOC) as $r) {
    $itens[] = [
      'desc'  => $nome_servico,                 // mantém o mesmo nome do serviço
      'pgto'  => (string)($r['pgto'] ?? ''),
      'valor' => (float)($r['valor'] ?? 0),
    ];
  }
}

// fallback opcional: se tiver campos “restante” na mesma linha
$valor_rest = isset($rec['valor_restante']) ? (float)$rec['valor_restante'] : 0;
if ($valor_rest > 0) {
  $itens[] = [
    'desc'  => $nome_servico,
    'pgto'  => (string)($rec['pgto_restante'] ?? 'Restante'),
    'valor' => $valor_rest,
  ];
}

// total
$total = 0.0;
foreach ($itens as $it) {
  $total += (float)($it['valor'] ?? 0);
}

// datas
$data_pgtoF = '';
if ($data_pgto !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_pgto)) {
  $data_pgtoF = implode('/', array_reverse(explode('-', $data_pgto)));
}
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Recibo</title>
<style>
  /* ===== imprime tudo em UMA página A4, com margens curtas ===== */
  @page { size: A4 portrait; margin: 12mm; }
  html, body { width:100%; height:auto; margin:0; padding:0; }
  *{
    box-sizing:border-box; font-family: Arial, Helvetica, sans-serif;
    -webkit-print-color-adjust: exact; print-color-adjust: exact;
  }

  /* ===== layout maior e mais elegante ===== */
  .ticket{
    width: 100%;
    max-width: 760px;
    margin: 0 auto;
    page-break-inside: avoid;
  }
  h1{
    font-size: 20px;
    text-align: center;
    margin: 6px 0 2px;
    letter-spacing: .3px;
  }
  .linha{ border-bottom:1px dashed #666; margin: 8px 0; }
  .miudo{ font-size:12px; text-align:center; color:#333; margin-top:4px; }
  .th{
    font-weight:700; text-transform:uppercase; text-align:center;
    padding: 8px 0; font-size:14px; letter-spacing:.2px;
  }

  .row{ display:flex; justify-content:space-between; align-items:center; width:100%; }
  .item{ padding:6px 0; font-size:14px; }
  .desc{ flex: 1 1 auto; padding-right: 12px; }
  .desc small{ color:#555; font-size:12px; }
  .val{ flex: 0 0 160px; text-align:right; white-space:nowrap; }

  @media screen {
    body{ background:#f7f7f7; }
    .ticket{
      background:#fff; padding: 10px 16px; border-radius: 6px;
      box-shadow: 0 2px 10px rgba(0,0,0,.06);
    }
  }

  .total-line{
    display:flex; align-items:center; font-weight:700; font-size:15px; padding:6px 0;
    max-width: 420px;
    margin: 0 auto;
  }
  .total-line .label{ white-space:nowrap; }
  .total-line .amount{ white-space:nowrap; }
  .total-line .dots{ flex:1; border-bottom:1px dashed #666; margin:0 10px; height:0; }
</style>
</head>
<body>
  <div class="ticket">
    <h1><?= h($nome_sistema ?? '') ?></h1>
    <div class="linha"></div>

    <div class="miudo">
      <?= h($endereco_sistema ?? '') ?><br>
      Contato: <?= h($telefone_fixo_sistema ?? '') ?>
      <?php if (!empty($cnpj_sistema)) { echo ' / CNPJ '.h($cnpj_sistema); } ?>
    </div>

    <div class="linha"></div>

    <div class="miudo">
      Cliente <?= h($nome_cliente) ?>
      <?php if ($tel_cliente !== '') { echo ' • Tel: '.h($tel_cliente); } ?>
    </div>

    <div class="linha"></div>

    <div class="th">Recibo de Pagamento</div>
    <div class="linha"></div>

    <?php foreach ($itens as $it): ?>
      <div class="item row">
        <div class="desc">
          <?= h($it['desc'] ?? '') ?>
          <?php if (!empty($it['pgto'])): ?>
            <small> — <?= h($it['pgto']) ?></small>
          <?php endif; ?>
        </div>
        <div class="val">R$ <?= moeda($it['valor'] ?? 0) ?></div>
      </div>
    <?php endforeach; ?>
    <div class="linha"></div>

    <div class="total-line">
      <span class="label">SubTotal / Total Pago</span>
      <span class="dots"></span>
      <span class="amount">R$ <?= moeda($total) ?></span>
    </div>

    <div class="linha"></div>

    <div class="miudo">
      Data Pagamento: <b><?= h($data_pgtoF) ?></b>
      <?php if ($hora !== '') { echo ' / Hora: <b>'.h($hora).'</b>'; } ?>
    </div>
  </div>
</body>
</html>
