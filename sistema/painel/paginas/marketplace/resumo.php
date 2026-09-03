<?php
require_once __DIR__ . '/master_bootstrap.php';

function bb_market_resumo_table_exists(PDO $pdo, string $table): bool {
  try {
    $st = $pdo->prepare("
      SELECT 1
        FROM INFORMATION_SCHEMA.TABLES
       WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = :t
       LIMIT 1
    ");
    $st->execute([':t' => $table]);
    return (bool)$st->fetchColumn();
  } catch (Throwable $e) {
    return false;
  }
}

function bb_market_resumo_col_exists(PDO $pdo, string $table, string $col): bool {
  try {
    $st = $pdo->prepare("
      SELECT 1
        FROM INFORMATION_SCHEMA.COLUMNS
       WHERE TABLE_SCHEMA = DATABASE()
         AND TABLE_NAME = :t
         AND COLUMN_NAME = :c
       LIMIT 1
    ");
    $st->execute([':t' => $table, ':c' => $col]);
    return (bool)$st->fetchColumn();
  } catch (Throwable $e) {
    return false;
  }
}

function bb_market_resumo_money($valor): string {
  return 'R$ ' . number_format((float)$valor, 2, ',', '.');
}

$tenantsTable = preg_replace('/[^a-z0-9_]/i', '', (string)($cfg['master']['table'] ?? 'tenants'));
if ($tenantsTable === '') {
  $tenantsTable = 'tenants';
}

$assinantes = 0;
$saldo = 0.00;
$vencidos = 0;
$pagos = 0.00;
$mapeados = 0;

$hoje = date('Y-m-d');

if (bb_market_resumo_table_exists($pdoM, $tenantsTable)) {
  $hasStatus = bb_market_resumo_col_exists($pdoM, $tenantsTable, 'status');
  $hasAtivo = bb_market_resumo_col_exists($pdoM, $tenantsTable, 'ativo');
  $whereAssinantes = [];
  if ($hasAtivo) {
    $whereAssinantes[] = "ativo = 1";
  }
  if ($hasStatus) {
    $whereAssinantes[] = "status = 'active'";
  }
  if (empty($whereAssinantes)) {
    $whereAssinantes[] = "1 = 1";
  }

  try {
    $qAss = $pdoM->query("SELECT COUNT(*) FROM `{$tenantsTable}` WHERE " . implode(' AND ', $whereAssinantes));
    $assinantes = (int)$qAss->fetchColumn();
  } catch (Throwable $e) {
    $assinantes = 0;
  }

  if (bb_market_resumo_col_exists($pdoM, $tenantsTable, 'slug') && bb_market_resumo_col_exists($pdoM, $tenantsTable, 'db_name')) {
    try {
      $qMap = $pdoM->query("SELECT COUNT(*) FROM `{$tenantsTable}` WHERE TRIM(COALESCE(slug,'')) <> '' AND TRIM(COALESCE(db_name,'')) <> ''");
      $mapeados = (int)$qMap->fetchColumn();
    } catch (Throwable $e) {
      $mapeados = 0;
    }
  }

  if (!bb_market_resumo_table_exists($pdoM, 'marketplace_faturas') && bb_market_resumo_col_exists($pdoM, $tenantsTable, 'valor_mensal')) {
    try {
      $qSaldo = $pdoM->query("SELECT COALESCE(SUM(valor_mensal),0) FROM `{$tenantsTable}` WHERE " . implode(' AND ', $whereAssinantes));
      $saldo = (float)$qSaldo->fetchColumn();
    } catch (Throwable $e) {
      $saldo = 0.00;
    }

    if ($hasStatus) {
      try {
        $qVenc = $pdoM->query("SELECT COUNT(*) FROM `{$tenantsTable}` WHERE status = 'blocked'");
        $vencidos = (int)$qVenc->fetchColumn();
      } catch (Throwable $e) {
        $vencidos = 0;
      }
    }
  }
}

if (bb_market_resumo_table_exists($pdoM, 'marketplace_faturas')) {
  try {
    $qSaldo = $pdoM->query("
      SELECT COALESCE(SUM(valor),0)
        FROM marketplace_faturas
       WHERE status IN ('pending','open','overdue')
    ");
    $saldo = (float)$qSaldo->fetchColumn();
  } catch (Throwable $e) {
    $saldo = 0.00;
  }

  try {
    $qVencidos = $pdoM->prepare("
      SELECT COUNT(*)
        FROM marketplace_faturas
       WHERE status IN ('pending','open','overdue')
         AND vencimento < :hoje
    ");
    $qVencidos->execute([':hoje' => $hoje]);
    $vencidos = (int)$qVencidos->fetchColumn();
  } catch (Throwable $e) {
    $vencidos = 0;
  }

  try {
    $qPagos = $pdoM->query("
      SELECT COALESCE(SUM(valor),0)
        FROM marketplace_faturas
       WHERE status = 'paid'
    ");
    $pagos = (float)$qPagos->fetchColumn();
  } catch (Throwable $e) {
    $pagos = 0.00;
  }
}

?>
<div class="marketplace-resumo-grid">
  <div class="marketplace-card">
    <div class="marketplace-card-topo">
      <div>
        <h6 class="marketplace-card-titulo">Assinantes</h6>
        <p class="marketplace-card-valor"><?php echo (int)$assinantes; ?></p>
      </div>
      <div class="marketplace-card-icone">
        <i class="fa fa-users"></i>
      </div>
    </div>
    <div class="marketplace-card-rodape">Tenants ativos no marketplace.</div>
  </div>

  <div class="marketplace-card marketplace-card-saldo">
    <div class="marketplace-card-topo">
      <div>
        <h6 class="marketplace-card-titulo">Saldo</h6>
        <p class="marketplace-card-valor"><?php echo bb_market_resumo_money($saldo); ?></p>
      </div>
      <div class="marketplace-card-icone">
        <i class="fa fa-line-chart"></i>
      </div>
    </div>
    <div class="marketplace-card-rodape">Total pendente em cobranças do marketplace.</div>
  </div>

  <div class="marketplace-card marketplace-card-vencido">
    <div class="marketplace-card-topo">
      <div>
        <h6 class="marketplace-card-titulo">Vencido</h6>
        <p class="marketplace-card-valor"><?php echo (int)$vencidos; ?></p>
      </div>
      <div class="marketplace-card-icone">
        <i class="fa fa-exclamation-triangle"></i>
      </div>
    </div>
    <div class="marketplace-card-rodape">Cobranças pendentes com vencimento atrasado.</div>
  </div>

  <div class="marketplace-card marketplace-card-pago">
    <div class="marketplace-card-topo">
      <div>
        <h6 class="marketplace-card-titulo">Pago</h6>
        <p class="marketplace-card-valor"><?php echo bb_market_resumo_money($pagos); ?></p>
      </div>
      <div class="marketplace-card-icone">
        <i class="fa fa-check-circle"></i>
      </div>
    </div>
    <div class="marketplace-card-rodape">Total já liquidado em faturas pagas.</div>
  </div>

  <div class="marketplace-card marketplace-card-mapeado">
    <div class="marketplace-card-topo">
      <div>
        <h6 class="marketplace-card-titulo">Bancos mapeados</h6>
        <p class="marketplace-card-valor"><?php echo (int)$mapeados; ?></p>
      </div>
      <div class="marketplace-card-icone">
        <i class="fa fa-database"></i>
      </div>
    </div>
    <div class="marketplace-card-rodape">Tenants com slug e banco individual configurados.</div>
  </div>
</div>
