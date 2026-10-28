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

if (!defined('COMPROVANTE_EMBUTIDO') && !headers_sent()) {
  header('Content-Type: text/html; charset=UTF-8');
}
require_once __DIR__ . '/../../conexao.php';
include __DIR__ . '/data_formatada.php';

/* ==== helpers ==== */
if (!function_exists('h')) {
  function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
}
if (!function_exists('moeda')) {
  function moeda($v){ return number_format((float)$v, 2, ',', '.'); }
}
/* ==== id ==== */
$id = isset($_POST['id']) ? (int)$_POST['id'] : (int)($_GET['id'] ?? 0);
if ($id <= 0) { die('Comprovante inválido'); }

/* ==== conta base ==== */
$q = $pdo->query("SELECT * FROM receber WHERE id = '$id' ");
$res = $q->fetchAll(PDO::FETCH_ASSOC);
if (count($res) == 0) { die('Registro não encontrado'); }

$id_conta     = (int)$res[0]['id'];
$cliente_id   = (int)$res[0]['pessoa'];
$valor        = (float)$res[0]['valor'];
$descricao    = $res[0]['descricao'] ?? '';
$data_pgto    = $res[0]['data_pgto'] ?? '';  // yyyy-mm-dd
$servico_id   = (int)$res[0]['servico'];
$funcionario  = (int)$res[0]['funcionario'];
$obs          = $res[0]['obs'] ?? '';
$pgto_base    = trim($res[0]['pgto'] ?? '');
$comanda      = (float)($res[0]['comanda'] ?? 0);
$valor2       = (float)($res[0]['valor2'] ?? 0);
$hora         = $res[0]['hora'] ?? '';

if ($comanda > 0) { $valor = $valor2; }
$dataF = $data_pgto ? implode('/', array_reverse(explode('-', $data_pgto))) : '';

/* ==== cliente ==== */
$cli = $pdo->query("SELECT nome, telefone FROM clientes WHERE id = '$cliente_id'")->fetch(PDO::FETCH_ASSOC);
$nome_cliente     = $cli['nome'] ?? '';
$telefone_cliente = $cli['telefone'] ?? '';

/* ==== itens do dia (mesmo cliente, mesma data, tipo Serviço) ==== */
$sqlItens = $pdo->query("
  SELECT r.id, r.servico, r.valor, r.pgto, r.comanda, r.valor2, s.nome AS nome_serv
    FROM receber r
    LEFT JOIN servicos s ON s.id = r.servico
   WHERE r.data_pgto = '$data_pgto'
     AND r.pessoa    = '$cliente_id'
     AND r.tipo      = 'Serviço'
   ORDER BY r.id ASC
");
$linhas = $sqlItens->fetchAll(PDO::FETCH_ASSOC);

$itens       = [];
$formas_pgto = []; // somatório por forma
$sub_tot     = 0.0;

foreach($linhas as $row){
  $v = (float)$row['valor'];
  if ((float)$row['comanda'] > 0) { $v = (float)$row['valor2']; }

  $nome_serv = $row['nome_serv'] ?? '';
  $pgto_item = trim($row['pgto'] ?? '');

  $sub_tot += $v;

  $itens[] = [
    'desc'  => $nome_serv,
    'valor' => $v,
    'pgto'  => $pgto_item
  ];

  if ($pgto_item !== '') {
    if (!isset($formas_pgto[$pgto_item])) $formas_pgto[$pgto_item] = 0.0;
    $formas_pgto[$pgto_item] += $v;
  }
}

$sub_totF = moeda($sub_tot);
?>
<!doctype html>
<html lang="pt-br">
<head>
<meta charset="utf-8">
<title>Comprovante</title>
<style>
  /* ===== mesma base do RECIBO ===== */
  @page { size: A4 portrait; margin: 12mm; }
  html, body { width:100%; height:auto; margin:0; padding:0; }
  *{
    box-sizing:border-box; font-family: Arial, Helvetica, sans-serif;
    -webkit-print-color-adjust: exact; print-color-adjust: exact;
  }

  .ticket{
    width: 100%;
    max-width: 760px;          /* mesma largura do recibo */
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

  /* total em uma linha (centralizado), igual ao recibo */
  .total-line{
    display:flex; align-items:center; font-weight:700; font-size:15px; padding:6px 0;
    max-width: 420px; margin: 0 auto;
  }
  .total-line .label{ white-space:nowrap; }
  .total-line .amount{ white-space:nowrap; }
  .total-line .dots{ flex:1; border-bottom:1px dashed #666; margin:0 10px; height:0; }

  /* lista de formas (opcional, quando houver mais de uma) */
  .pay-list { max-width: 540px; margin: 0 auto; }
  .pay-row { display:flex; justify-content:space-between; padding:4px 0; font-size:13px; }
  .pay-row .left { color:#444; }
  .pay-row .right { font-weight:600; }

  @media screen {
    body{ background:#f7f7f7; }
    .ticket{
      background:#fff; padding: 10px 16px; border-radius: 6px;
      box-shadow: 0 2px 10px rgba(0,0,0,.06);
    }
  }
</style>
</head>
<body>
  <div class="ticket">
    <h1><?= h($nome_sistema ?? '') ?></h1>
    <div class="linha"></div>

    <div class="miudo">
      <?= h($endereco_sistema ?? '') ?><br>
      Contato: <?= h($telefone_fixo_sistema ?? '') ?>
      <?php if(!empty($cnpj_sistema)){ echo ' / CNPJ '.h($cnpj_sistema); } ?>
    </div>

    <div class="linha"></div>

    <div class="miudo">
      Cliente <?= h($nome_cliente) ?>
      <?php if(!empty($telefone_cliente)){ echo ' • Tel: '.h($telefone_cliente); } ?><br>
      Serviço/ID: <b><?= h($id_conta) ?></b>  —  Data: <?= h($dataF) ?>
    </div>

    <div class="linha"></div>

    <div class="th">Comprovante de Serviço</div>
    <div class="miudo">CUPOM NÃO FISCAL</div>

    <div class="linha"></div>

    <!-- Itens (mesmo visual do recibo) -->
    <?php foreach ($itens as $it): ?>
      <div class="item row">
        <div class="desc">
          <?= h($it['desc']) ?>
          <?php if(!empty($it['pgto'])): ?>
            <small> — <?= h($it['pgto']) ?></small>
          <?php endif; ?>
        </div>
        <div class="val">R$ <?= moeda($it['valor']) ?></div>
      </div>
    <?php endforeach; ?>

    <div class="linha"></div>

    <!-- Total (idêntico ao recibo) -->
    <div class="total-line">
      <span class="label">SubTotal / Total Pago</span>
      <span class="dots"></span>
      <span class="amount">R$ <?= h($sub_totF) ?></span>
    </div>

    <?php if(!empty($formas_pgto)): ?>
      <div class="pay-list">
        <?php foreach($formas_pgto as $nome => $valorMetodo): ?>
          <div class="pay-row">
            <span class="left"><?= h($nome) ?></span>
            <span class="right">R$ <?= moeda($valorMetodo) ?></span>
          </div>
        <?php endforeach; ?>
      </div>
    <?php elseif(!empty($pgto_base)): ?>
      <div class="pay-list">
        <div class="pay-row">
          <span class="left">Forma de Pagamento</span>
          <span class="right"><?= h($pgto_base) ?></span>
        </div>
      </div>
    <?php endif; ?>

    <div class="linha"></div>

    <?php if(!empty($obs)): ?>
      <div class="miudo"><b>Observações</b></div>
      <div class="miudo"><?= nl2br(h($obs)) ?></div>
      <div class="linha"></div>
    <?php endif; ?>

    <div class="miudo">
      Data do Pagamento: <b><?= h($dataF) ?></b>
      <?php if(!empty($hora)){ echo ' / Hora: <b>'.h($hora).'</b>'; } ?>
    </div>
  </div>
</body>
</html>
