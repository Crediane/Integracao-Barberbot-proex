<?php
require_once __DIR__ . '/master_bootstrap.php';

if (!headers_sent()) {
  header('Content-Type: application/json; charset=utf-8');
}

if (!function_exists('bb_market_json_exit')) {
  function bb_market_json_exit(array $payload): void {
    if (ob_get_level() > 0) {
      while (ob_get_level() > 0) {
        @ob_end_clean();
      }
    }
    if (!headers_sent()) {
      header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }
}

if (!function_exists('bb_market_table_exists')) {
  function bb_market_table_exists(PDO $pdo, string $table): bool {
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
}

if (!function_exists('bb_market_column_exists')) {
  function bb_market_column_exists(PDO $pdo, string $table, string $column): bool {
    try {
      $st = $pdo->prepare("
        SELECT 1
          FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :t
           AND COLUMN_NAME = :c
         LIMIT 1
      ");
      $st->execute([':t' => $table, ':c' => $column]);
      return (bool)$st->fetchColumn();
    } catch (Throwable $e) {
      return false;
    }
  }
}


if (!function_exists('bb_market_colunas_tabela')) {
  function bb_market_colunas_tabela(PDO $pdo, string $table): array {
    try {
      $st = $pdo->prepare("
        SELECT COLUMN_NAME
          FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :t
      ");
      $st->execute([':t' => $table]);
      $cols = $st->fetchAll(PDO::FETCH_COLUMN);
    } catch (Throwable $e) {
      $cols = [];
    }

    $map = [];
    if (is_array($cols)) {
      foreach ($cols as $col) {
        $map[(string)$col] = true;
      }
    }

    return $map;
  }
}


if (!function_exists('bb_market_param_coluna')) {
  function bb_market_param_coluna(string $prefixo, string $coluna): string {
    $nome = preg_replace('/[^a-z0-9_]/i', '_', $prefixo . '_' . $coluna);
    return ':' . $nome;
  }
}

if (!function_exists('bb_market_add_insert_col')) {
  function bb_market_add_insert_col(array $colsMap, array &$colsInsert, array &$valsInsert, array &$paramsInsert, array &$adicionados, string $coluna, string $prefixo, $valor): void {
    if (!isset($colsMap[$coluna]) || isset($adicionados[$coluna])) {
      return;
    }

    $param = bb_market_param_coluna($prefixo, $coluna);
    $colsInsert[] = "`{$coluna}`";
    $valsInsert[] = $param;
    $paramsInsert[$param] = $valor;
    $adicionados[$coluna] = true;
  }
}

if (!function_exists('bb_market_add_update_col')) {
  function bb_market_add_update_col(array $colsMap, array &$setsUpdate, array &$paramsUpdate, string $coluna, string $prefixo, $valor): void {
    if (!isset($colsMap[$coluna])) {
      return;
    }

    $param = bb_market_param_coluna($prefixo, $coluna);
    $setsUpdate[] = "`{$coluna}` = {$param}";
    $paramsUpdate[$param] = $valor;
  }
}

if (!function_exists('bb_market_add_insert_defaults_obrigatorios')) {
  function bb_market_add_insert_defaults_obrigatorios(PDO $pdo, string $table, array $colsMap, array &$colsInsert, array &$valsInsert, array &$paramsInsert, array &$adicionados): void {
    try {
      $st = $pdo->prepare("
        SELECT COLUMN_NAME, DATA_TYPE, COLUMN_TYPE, IS_NULLABLE, COLUMN_DEFAULT, EXTRA
          FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE()
           AND TABLE_NAME = :t
      ");
      $st->execute([':t' => $table]);
      $linhas = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
      $linhas = [];
    }

    if (!is_array($linhas)) {
      return;
    }

    $agora = date('Y-m-d H:i:s');

    foreach ($linhas as $linha) {
      $coluna = (string)($linha['COLUMN_NAME'] ?? '');
      if ($coluna === '' || isset($adicionados[$coluna])) {
        continue;
      }

      if (strtolower($coluna) === 'id') {
        continue;
      }

      $extra = strtolower((string)($linha['EXTRA'] ?? ''));
      if (strpos($extra, 'auto_increment') !== false) {
        continue;
      }

      $nullable = strtoupper((string)($linha['IS_NULLABLE'] ?? 'YES'));
      $defaultExiste = array_key_exists('COLUMN_DEFAULT', $linha) && $linha['COLUMN_DEFAULT'] !== null;

      if ($nullable === 'YES' || $defaultExiste) {
        continue;
      }

      $tipo = strtolower((string)($linha['DATA_TYPE'] ?? ''));
      $tipoCompleto = (string)($linha['COLUMN_TYPE'] ?? '');
      $valor = '';

      if (in_array($tipo, ['tinyint','smallint','mediumint','int','integer','bigint','decimal','float','double','real','bit'], true)) {
        $valor = 0;
      } elseif ($tipo === 'date') {
        $valor = date('Y-m-d');
      } elseif (in_array($tipo, ['datetime','timestamp'], true)) {
        $valor = $agora;
      } elseif ($tipo === 'time') {
        $valor = date('H:i:s');
      } elseif ($tipo === 'year') {
        $valor = date('Y');
      } elseif ($tipo === 'enum' && preg_match("~^enum\\('([^']*)'~", $tipoCompleto, $m)) {
        $valor = (string)$m[1];
      } else {
        $valor = '';
      }

      bb_market_add_insert_col($colsMap, $colsInsert, $valsInsert, $paramsInsert, $adicionados, $coluna, 'auto', $valor);
    }
  }
}

if (!function_exists('bb_market_sql_fatura_pendente')) {
  function bb_market_sql_fatura_pendente(array $colsFatura): string {
    $where = ["tenant_id = :tenant_id"];

    if (isset($colsFatura['status'])) {
      $where[] = "status IN ('pending','open','overdue')";
    }

    $order = [];
    if (isset($colsFatura['vencimento'])) {
      $order[] = "vencimento DESC";
    }
    $order[] = "id DESC";

    return "
      SELECT id
        FROM marketplace_faturas
       WHERE " . implode(' AND ', $where) . "
       ORDER BY " . implode(', ', $order) . "
       LIMIT 1
    ";
  }
}

if (!function_exists('bb_market_clean_host')) {
  function bb_market_clean_host(string $host): string {
    $host = strtolower(trim($host));
    if ($host === '') return '';
    $host = preg_replace('~^https?://~i', '', $host);
    $host = preg_replace('~/.*$~', '', $host);
    $host = preg_replace('~:\d+$~', '', $host);
    return trim($host, ". \t\n\r\0\x0B");
  }
}

if (!function_exists('bb_market_only_digits')) {
  function bb_market_only_digits(string $valor): string {
    return (string)preg_replace('/\D+/', '', $valor);
  }
}

if (!function_exists('bb_market_whatsapp_br')) {
  function bb_market_whatsapp_br(string $telefone): string {
    $telefone = bb_market_only_digits($telefone);
    if ($telefone === '') {
      return '';
    }

    if (substr($telefone, 0, 2) !== '55' && strlen($telefone) <= 11) {
      $telefone = '55' . $telefone;
    }

    if (substr($telefone, 0, 2) === '55' && strlen($telefone) > 13) {
      $telefone = substr($telefone, 0, 13);
    }

    return $telefone;
  }
}

if (!function_exists('bb_market_responder_sem_curl')) {
  function bb_market_responder_sem_curl(): void {
    bb_market_json_exit([
      'ok'  => false,
      'msg' => 'A extensão cURL do PHP não está habilitada no servidor.'
    ]);
  }
}


if (!function_exists('bb_market_proximo_vencimento_mes')) {
  function bb_market_proximo_vencimento_mes(string $vencimentoBase): string {
    $vencimentoBase = trim($vencimentoBase);
    if ($vencimentoBase === '' || $vencimentoBase === '0000-00-00') {
      $vencimentoBase = date('Y-m-d');
    }

    try {
      $dt = new DateTime($vencimentoBase);
    } catch (Throwable $e) {
      $dt = new DateTime('today');
    }

    return $dt->modify('+1 month')->format('Y-m-d');
  }
}

if (!function_exists('bb_market_sincronizar_pos_pagamento')) {
  function bb_market_sincronizar_pos_pagamento(PDO $pdoM, string $tenantsTable, int $tenantId, array &$tenant, array $colsFatura): void {
    if ($tenantId <= 0 || !isset($colsFatura['tenant_id']) || !isset($colsFatura['status'])) {
      return;
    }

    $agora = date('Y-m-d H:i:s');
    $pendente = null;
    $paga = null;

    try {
      $stPend = $pdoM->prepare("
        SELECT *
          FROM marketplace_faturas
         WHERE tenant_id = :tenant_id
           AND status IN ('pending','open','overdue')
         ORDER BY vencimento DESC, id DESC
         LIMIT 1
      ");
      $stPend->execute([':tenant_id' => $tenantId]);
      $rowPend = $stPend->fetch(PDO::FETCH_ASSOC);
      if (is_array($rowPend)) {
        $pendente = $rowPend;
      }
    } catch (Throwable $e) {
      $pendente = null;
    }

    if (!$pendente) {
      try {
        $stPago = $pdoM->prepare("
          SELECT *
            FROM marketplace_faturas
           WHERE tenant_id = :tenant_id
             AND status = 'paid'
           ORDER BY vencimento DESC, id DESC
           LIMIT 1
        ");
        $stPago->execute([':tenant_id' => $tenantId]);
        $rowPago = $stPago->fetch(PDO::FETCH_ASSOC);
        if (is_array($rowPago)) {
          $paga = $rowPago;
        }
      } catch (Throwable $e) {
        $paga = null;
      }
    }

    if (!$pendente && !$paga) {
      return;
    }

    $vencimentoNovo = '';
    if ($pendente && isset($pendente['vencimento'])) {
      $vencimentoNovo = trim((string)$pendente['vencimento']);
    }

    if ($vencimentoNovo === '' || $vencimentoNovo === '0000-00-00') {
      $basePagamento = '';
      if ($paga && isset($paga['vencimento'])) {
        $basePagamento = trim((string)$paga['vencimento']);
      }
      if ($basePagamento === '' || $basePagamento === '0000-00-00') {
        $basePagamento = trim((string)($tenant['vencimento'] ?? ''));
      }
      $vencimentoNovo = bb_market_proximo_vencimento_mes($basePagamento);
    }

    $valorNovo = (float)($tenant['valor_mensal'] ?? 0);
    if ($valorNovo <= 0 && $paga && isset($paga['valor'])) {
      $valorNovo = (float)$paga['valor'];
    }

    try {
      $pdoM->beginTransaction();

      $setsTenant = [];
      $paramsTenant = [':id' => $tenantId];

      if (bb_market_column_exists($pdoM, $tenantsTable, 'status')) {
        $setsTenant[] = "`status` = 'active'";
        $tenant['status'] = 'active';
      }
      if (bb_market_column_exists($pdoM, $tenantsTable, 'ativo')) {
        $setsTenant[] = "`ativo` = 1";
        $tenant['ativo'] = 1;
      }
      if (bb_market_column_exists($pdoM, $tenantsTable, 'blocked_at')) {
        $setsTenant[] = "`blocked_at` = NULL";
        $tenant['blocked_at'] = null;
      }
      if (bb_market_column_exists($pdoM, $tenantsTable, 'vencimento')) {
        $setsTenant[] = "`vencimento` = :vencimento";
        $paramsTenant[':vencimento'] = $vencimentoNovo;
        $tenant['vencimento'] = $vencimentoNovo;
      }

      if (!empty($setsTenant)) {
        $stTenant = $pdoM->prepare("
          UPDATE `{$tenantsTable}`
             SET " . implode(', ', $setsTenant) . "
           WHERE id = :id
           LIMIT 1
        ");
        $stTenant->execute($paramsTenant);
      }

      if (!$pendente && $paga) {
        $colsInsert = [];
        $valsInsert = [];
        $paramsInsert = [];

        $adicionados = [];

        bb_market_add_insert_col($colsFatura, $colsInsert, $valsInsert, $paramsInsert, $adicionados, 'tenant_id', 'tenant', $tenantId);
        bb_market_add_insert_col($colsFatura, $colsInsert, $valsInsert, $paramsInsert, $adicionados, 'vencimento', 'vencimento', $vencimentoNovo);
        bb_market_add_insert_col($colsFatura, $colsInsert, $valsInsert, $paramsInsert, $adicionados, 'valor', 'valor', number_format($valorNovo, 2, '.', ''));
        bb_market_add_insert_col($colsFatura, $colsInsert, $valsInsert, $paramsInsert, $adicionados, 'status', 'status', 'pending');
        bb_market_add_insert_col($colsFatura, $colsInsert, $valsInsert, $paramsInsert, $adicionados, 'descricao', 'descricao', 'Plano Assinatura');
        bb_market_add_insert_col($colsFatura, $colsInsert, $valsInsert, $paramsInsert, $adicionados, 'tipo', 'tipo', 'Assinatura');
        bb_market_add_insert_col($colsFatura, $colsInsert, $valsInsert, $paramsInsert, $adicionados, 'referencia', 'referencia', 'Marketplace');
        bb_market_add_insert_col($colsFatura, $colsInsert, $valsInsert, $paramsInsert, $adicionados, 'id_ref', 'id_ref', $tenantId);
        bb_market_add_insert_col($colsFatura, $colsInsert, $valsInsert, $paramsInsert, $adicionados, 'criado_em', 'criado', $agora);
        bb_market_add_insert_col($colsFatura, $colsInsert, $valsInsert, $paramsInsert, $adicionados, 'created_at', 'created', $agora);
        bb_market_add_insert_col($colsFatura, $colsInsert, $valsInsert, $paramsInsert, $adicionados, 'updated_at', 'updated', $agora);

        bb_market_add_insert_defaults_obrigatorios($pdoM, 'marketplace_faturas', $colsFatura, $colsInsert, $valsInsert, $paramsInsert, $adicionados);

        if (!empty($colsInsert)) {
          $stIns = $pdoM->prepare("
            INSERT INTO marketplace_faturas
              (" . implode(', ', $colsInsert) . ")
            VALUES
              (" . implode(', ', $valsInsert) . ")
          ");
          $stIns->execute($paramsInsert);
        }
      }

      $pdoM->commit();
    } catch (Throwable $e) {
      if ($pdoM->inTransaction()) {
        $pdoM->rollBack();
      }
    }
  }
}

$arquivoConexao = __DIR__ . '/../../../conexao.php';
if (is_file($arquivoConexao)) {
  require_once $arquivoConexao;
}

$globalConfigPdos = [$pdoM];
if (isset($pdo) && $pdo instanceof PDO) {
  try {
    $dbConexaoGlobal = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    $dbsPermitidos = [];
    if (!empty($cfg['master']['db'])) {
      $dbsPermitidos[] = (string)$cfg['master']['db'];
    }
    if (!empty($cfg['auth_master']['db'])) {
      $dbsPermitidos[] = (string)$cfg['auth_master']['db'];
    }
    if (in_array($dbConexaoGlobal, array_values(array_unique($dbsPermitidos)), true)) {
      $globalConfigPdos[] = $pdo;
    }
  } catch (Throwable $e) {
  }
}

$acao = isset($_POST['acao']) ? trim((string)$_POST['acao']) : '';
$id   = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
  bb_market_json_exit(['ok' => false, 'msg' => 'ID inválido.']);
}

if ($acao !== 'gerar' && $acao !== 'enviar') {
  bb_market_json_exit(['ok' => false, 'msg' => 'Ação inválida.']);
}

$mpToken = isset($access_token_mp) ? trim((string)$access_token_mp) : '';

if ($mpToken === '') {
  foreach ($globalConfigPdos as $pdoConfigGlobal) {
    try {
      if (bb_market_table_exists($pdoConfigGlobal, 'config') && bb_market_column_exists($pdoConfigGlobal, 'config', 'access_token_mp')) {
        $row = $pdoConfigGlobal->query("SELECT access_token_mp FROM config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($row && !empty($row['access_token_mp'])) {
          $mpToken = trim((string)$row['access_token_mp']);
          break;
        }
      }
    } catch (Throwable $e) {
    }
  }
}

if ($mpToken === '') {
  bb_market_json_exit([
    'ok' => false,
    'msg' => 'Access Token do Mercado Pago não configurado no contexto Master.'
  ]);
}

$tenantsTable = preg_replace('/[^a-z0-9_]/i', '', (string)($cfg['master']['table'] ?? 'tenants'));
if ($tenantsTable === '') {
  $tenantsTable = 'tenants';
}

$st = $pdoM->prepare("SELECT * FROM `{$tenantsTable}` WHERE id = ? LIMIT 1");
$st->execute([$id]);
$t = $st->fetch(PDO::FETCH_ASSOC);

if (!$t) {
  bb_market_json_exit(['ok' => false, 'msg' => 'Barbearia não encontrada.']);
}

$dominioPersonalizado = '';
if (bb_market_table_exists($pdoM, 'tenant_domains')) {
  try {
    $sqlDom = "
      SELECT host
        FROM tenant_domains
       WHERE tenant_id = :tenant_id
    ";

    if (bb_market_column_exists($pdoM, 'tenant_domains', 'ativo')) {
      $sqlDom .= " AND (ativo = 1 OR ativo IS NULL)";
    }
    if (bb_market_column_exists($pdoM, 'tenant_domains', 'principal')) {
      $sqlDom .= " AND (principal = 1 OR principal IS NULL)";
    }

    $sqlDom .= " ORDER BY id DESC LIMIT 1";

    $stDom = $pdoM->prepare($sqlDom);
    $stDom->execute([':tenant_id' => $id]);
    $dominioPersonalizado = bb_market_clean_host((string)($stDom->fetchColumn() ?: ''));
  } catch (Throwable $e) {
    $dominioPersonalizado = '';
  }
}

$valor = (float)($t['valor_mensal'] ?? 0);
if ($valor <= 0) {
  bb_market_json_exit(['ok' => false, 'msg' => 'Valor mensal inválido (cadastre um valor no tenant).']);
}

$slug = (string)($t['slug'] ?? '');
$nome = (string)($t['nome'] ?? $slug);

$emailPayer = (string)($t['email'] ?? '');
if ($emailPayer === '') {
  $emailPayer = (string)($t['dono_email'] ?? '');
}
if ($emailPayer === '') {
  $hostTmp = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $emailPayer = "pagamentos@" . $hostTmp;
}

$baseUrl = '';
if (isset($url_sistema) && trim((string)$url_sistema) !== '') {
  $tmp = rtrim((string)$url_sistema, '/');
  $tmp = preg_replace('~/sistema$~i', '', $tmp);
  $baseUrl = $tmp;
} else {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
  if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $scheme = ($_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : $scheme;
  }
  $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
  $baseUrl = $scheme . '://' . $host;
}

$webhookToken = '';
if (!empty($cfg['webhook_token'])) {
  $webhookToken = (string)$cfg['webhook_token'];
} elseif (!empty($cfg['app']['webhook_token'])) {
  $webhookToken = (string)$cfg['app']['webhook_token'];
}

$notifyUrl = $baseUrl . "/pagamentos/webhook_mp_marketplace.php";
$qs = [];
if ($webhookToken !== '') $qs[] = 'token=' . rawurlencode($webhookToken);
if ($slug !== '') $qs[] = 'loja=' . rawurlencode($slug);
if (!empty($qs)) $notifyUrl .= '?' . implode('&', $qs);

$backBase = $baseUrl . "/sistema/painel/index.php?pagina=marketplace";

$backUrls = [
  "success" => $backBase . "&mp=success&tenant=" . $id,
  "pending" => $backBase . "&mp=pending&tenant=" . $id,
  "failure" => $backBase . "&mp=failure&tenant=" . $id,
];

$hoje = date('Y-m-d');
$tenantFoiBloqueado = false;
if (!empty($t['vencimento']) && $t['vencimento'] < $hoje && (string)($t['status'] ?? 'active') !== 'blocked') {
  try {
    $camposUpdate = ["status = 'blocked'"];
    if (bb_market_column_exists($pdoM, $tenantsTable, 'ativo')) {
      $camposUpdate[] = "ativo = 0";
    }
    if (bb_market_column_exists($pdoM, $tenantsTable, 'blocked_at')) {
      $camposUpdate[] = "blocked_at = :blocked_at";
    }

    $upTenant = $pdoM->prepare("
      UPDATE `{$tenantsTable}`
         SET " . implode(', ', $camposUpdate) . "
       WHERE id = :id
       LIMIT 1
    ");
    $upTenant->execute([':id' => $id, ':blocked_at' => date('Y-m-d H:i:s')]);
    $tenantFoiBloqueado = true;
  } catch (Throwable $e) {
  }
}

if (!function_exists('curl_init')) {
  bb_market_responder_sem_curl();
}

$body = [
  "external_reference" => (string)$id,
  "notification_url"   => $notifyUrl,
  "back_urls"          => $backUrls,
  "auto_return"        => "approved",
  "items" => [
    [
      "title"      => "Assinatura BarberBot - " . $nome . " (" . $slug . ")",
      "quantity"   => 1,
      "unit_price" => $valor
    ]
  ],
  "payer" => [
    "email" => $emailPayer
  ]
];

$ch = curl_init("https://api.mercadopago.com/checkout/preferences");
curl_setopt_array($ch, [
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_HTTPHEADER => [
    "Authorization: Bearer " . $mpToken,
    "Content-Type: application/json"
  ],
  CURLOPT_POST => true,
  CURLOPT_POSTFIELDS => json_encode($body),
  CURLOPT_TIMEOUT => 30
]);

$resp = curl_exec($ch);
$http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err  = curl_error($ch);
curl_close($ch);

if (!$resp) {
  bb_market_json_exit([
    'ok' => false,
    'msg' => 'Erro ao criar cobrança no Mercado Pago (sem resposta). ' . $err,
    'reload' => $tenantFoiBloqueado
  ]);
}

$r = json_decode($resp, true);

if ($http < 200 || $http >= 300) {
  $msgApi = '';
  if (is_array($r)) {
    $msgApi = (string)($r['message'] ?? '');
    if (isset($r['cause']) && is_array($r['cause']) && isset($r['cause'][0]['description'])) {
      $msgApi .= " - " . $r['cause'][0]['description'];
    }
  }
  if ($msgApi === '') {
    $msgApi = 'HTTP ' . $http;
  }

  bb_market_json_exit([
    'ok' => false,
    'msg' => 'Erro ao criar cobrança no Mercado Pago (' . $msgApi . ').',
    'reload' => $tenantFoiBloqueado
  ]);
}

$linkMp = (string)($r['init_point'] ?? '');
$prefId = (string)($r['id'] ?? '');

if ($linkMp === '') {
  bb_market_json_exit([
    'ok' => false,
    'msg' => 'Mercado Pago não retornou init_point.',
    'reload' => $tenantFoiBloqueado
  ]);
}

if (!bb_market_table_exists($pdoM, 'marketplace_faturas')) {
  bb_market_json_exit([
    'ok' => false,
    'msg' => 'Tabela marketplace_faturas não encontrada.',
    'reload' => $tenantFoiBloqueado
  ]);
}

$colsFatura = bb_market_colunas_tabela($pdoM, 'marketplace_faturas');

bb_market_sincronizar_pos_pagamento($pdoM, $tenantsTable, $id, $t, $colsFatura);

$vencimento = (string)($t['vencimento'] ?? '');
if ($vencimento === '' || $vencimento === '0000-00-00') {
  $vencimento = date('Y-m-d');
}

$faturaId = 0;

try {
  if (!isset($colsFatura['tenant_id'])) {
    throw new Exception('Coluna tenant_id não encontrada em marketplace_faturas.');
  }

  $pdoM->beginTransaction();

  $stF = $pdoM->prepare(bb_market_sql_fatura_pendente($colsFatura));
  $stF->execute([':tenant_id' => $id]);
  $fat = $stF->fetch(PDO::FETCH_ASSOC);

  if ($fat && !empty($fat['id'])) {
    $faturaId = (int)$fat['id'];

    $setsFatura = [];
    $paramsFatura = [':id' => $faturaId];

    bb_market_add_update_col($colsFatura, $setsFatura, $paramsFatura, 'vencimento', 'vencimento', $vencimento);
    bb_market_add_update_col($colsFatura, $setsFatura, $paramsFatura, 'valor', 'valor', number_format($valor, 2, '.', ''));
    bb_market_add_update_col($colsFatura, $setsFatura, $paramsFatura, 'descricao', 'descricao', 'Plano Assinatura');
    bb_market_add_update_col($colsFatura, $setsFatura, $paramsFatura, 'tipo', 'tipo', 'Assinatura');
    bb_market_add_update_col($colsFatura, $setsFatura, $paramsFatura, 'referencia', 'referencia', 'Marketplace');
    bb_market_add_update_col($colsFatura, $setsFatura, $paramsFatura, 'id_ref', 'id_ref', $id);
    bb_market_add_update_col($colsFatura, $setsFatura, $paramsFatura, 'mp_preference_id', 'pref', $prefId);
    bb_market_add_update_col($colsFatura, $setsFatura, $paramsFatura, 'preference_id', 'pref', $prefId);
    bb_market_add_update_col($colsFatura, $setsFatura, $paramsFatura, 'mp_init_point', 'link', $linkMp);
    bb_market_add_update_col($colsFatura, $setsFatura, $paramsFatura, 'init_point', 'link', $linkMp);
    bb_market_add_update_col($colsFatura, $setsFatura, $paramsFatura, 'link_pagamento', 'link', $linkMp);
    bb_market_add_update_col($colsFatura, $setsFatura, $paramsFatura, 'url_pagamento', 'link', $linkMp);
    bb_market_add_update_col($colsFatura, $setsFatura, $paramsFatura, 'payment_url', 'link', $linkMp);
    bb_market_add_update_col($colsFatura, $setsFatura, $paramsFatura, 'updated_at', 'updated', date('Y-m-d H:i:s'));
    bb_market_add_update_col($colsFatura, $setsFatura, $paramsFatura, 'alterado_em', 'updated', date('Y-m-d H:i:s'));

    if (isset($colsFatura['status'])) {
      $setsFatura[] = "`status` = 'pending'";
    }

    if (!empty($setsFatura)) {
      $upF = $pdoM->prepare("
        UPDATE marketplace_faturas
           SET " . implode(', ', $setsFatura) . "
         WHERE id = :id
         LIMIT 1
      ");
      $upF->execute($paramsFatura);
    }
  } else {
    $colsInsert = [];
    $valsInsert = [];
    $paramsInsert = [];
    $adicionados = [];
    $agora = date('Y-m-d H:i:s');

    bb_market_add_insert_col($colsFatura, $colsInsert, $valsInsert, $paramsInsert, $adicionados, 'tenant_id', 'tenant', $id);
    bb_market_add_insert_col($colsFatura, $colsInsert, $valsInsert, $paramsInsert, $adicionados, 'vencimento', 'vencimento', $vencimento);
    bb_market_add_insert_col($colsFatura, $colsInsert, $valsInsert, $paramsInsert, $adicionados, 'valor', 'valor', number_format($valor, 2, '.', ''));
    bb_market_add_insert_col($colsFatura, $colsInsert, $valsInsert, $paramsInsert, $adicionados, 'status', 'status', 'pending');
    bb_market_add_insert_col($colsFatura, $colsInsert, $valsInsert, $paramsInsert, $adicionados, 'descricao', 'descricao', 'Plano Assinatura');
    bb_market_add_insert_col($colsFatura, $colsInsert, $valsInsert, $paramsInsert, $adicionados, 'tipo', 'tipo', 'Assinatura');
    bb_market_add_insert_col($colsFatura, $colsInsert, $valsInsert, $paramsInsert, $adicionados, 'referencia', 'referencia', 'Marketplace');
    bb_market_add_insert_col($colsFatura, $colsInsert, $valsInsert, $paramsInsert, $adicionados, 'id_ref', 'id_ref', $id);
    bb_market_add_insert_col($colsFatura, $colsInsert, $valsInsert, $paramsInsert, $adicionados, 'mp_preference_id', 'pref', $prefId);
    bb_market_add_insert_col($colsFatura, $colsInsert, $valsInsert, $paramsInsert, $adicionados, 'preference_id', 'pref', $prefId);
    bb_market_add_insert_col($colsFatura, $colsInsert, $valsInsert, $paramsInsert, $adicionados, 'mp_init_point', 'link', $linkMp);
    bb_market_add_insert_col($colsFatura, $colsInsert, $valsInsert, $paramsInsert, $adicionados, 'init_point', 'link', $linkMp);
    bb_market_add_insert_col($colsFatura, $colsInsert, $valsInsert, $paramsInsert, $adicionados, 'link_pagamento', 'link', $linkMp);
    bb_market_add_insert_col($colsFatura, $colsInsert, $valsInsert, $paramsInsert, $adicionados, 'url_pagamento', 'link', $linkMp);
    bb_market_add_insert_col($colsFatura, $colsInsert, $valsInsert, $paramsInsert, $adicionados, 'payment_url', 'link', $linkMp);
    bb_market_add_insert_col($colsFatura, $colsInsert, $valsInsert, $paramsInsert, $adicionados, 'criado_em', 'criado', $agora);
    bb_market_add_insert_col($colsFatura, $colsInsert, $valsInsert, $paramsInsert, $adicionados, 'created_at', 'created', $agora);
    bb_market_add_insert_col($colsFatura, $colsInsert, $valsInsert, $paramsInsert, $adicionados, 'updated_at', 'updated', $agora);

    bb_market_add_insert_defaults_obrigatorios($pdoM, 'marketplace_faturas', $colsFatura, $colsInsert, $valsInsert, $paramsInsert, $adicionados);

    if (empty($colsInsert)) {
      throw new Exception('Nenhuma coluna compatível encontrada em marketplace_faturas.');
    }

    $insF = $pdoM->prepare("
      INSERT INTO marketplace_faturas
        (" . implode(', ', $colsInsert) . ")
      VALUES
        (" . implode(', ', $valsInsert) . ")
    ");
    $insF->execute($paramsInsert);

    $faturaId = (int)$pdoM->lastInsertId();
  }

  $pdoM->commit();
} catch (Throwable $e) {
  if ($pdoM->inTransaction()) {
    $pdoM->rollBack();
  }
  error_log('Marketplace cobrança - erro ao salvar fatura: ' . $e->getMessage());
  bb_market_json_exit([
    'ok' => false,
    'msg' => 'Erro ao salvar cobrança no marketplace: ' . $e->getMessage(),
    'reload' => $tenantFoiBloqueado
  ]);
}

if ($faturaId <= 0) {
  bb_market_json_exit([
    'ok' => false,
    'msg' => 'Fatura do marketplace não encontrada.',
    'reload' => $tenantFoiBloqueado
  ]);
}

$linkLocal = $baseUrl . '/pagamentos/pagamento_marketplace.php?fatura=' . $faturaId;

// ======================================================
// GERAÇÃO DA COBRANÇA
// - aqui encerra o fluxo do botão "Gerar cobrança"
// - não entra mais no bloco de WhatsApp
// ======================================================
if ($acao === 'gerar') {
  $msgSaida = 'Cobrança gerada com sucesso.';
  if ($tenantFoiBloqueado) {
    $msgSaida .= ' Tenant bloqueado por vencimento.';
  }

  bb_market_json_exit([
    'ok' => true,
    'msg' => $msgSaida,
    'url' => $linkLocal,
    'url_mp' => $linkMp,
    'fatura_id' => $faturaId,
    'whats_sent' => false,
    'whats_msg' => '',
    'blocked' => $tenantFoiBloqueado,
    'reload' => $tenantFoiBloqueado
  ]);
}

// ======================================================
// ENVIO NO WHATSAPP
// - somente para ação "enviar"
// ======================================================
$whatsSent = false;
$whatsMsg = '';
$msgSaida = '';

$tel = '';
if (!empty($t['telefone_whatsapp'])) {
  $tel = (string)$t['telefone_whatsapp'];
} elseif (!empty($t['whatsapp'])) {
  $tel = (string)$t['whatsapp'];
}

$telefone = bb_market_whatsapp_br($tel);
if ($telefone === '') {
  bb_market_json_exit([
    'ok' => false,
    'msg' => 'WhatsApp do tenant está vazio. Cadastre no Marketplace.',
    'reload' => false,
    'url' => $linkLocal
  ]);
}

$apiW = '';
$tokenW = '';
$instanciaW = '';

foreach ($globalConfigPdos as $pdoConfigGlobal) {
  try {
    if (!bb_market_table_exists($pdoConfigGlobal, 'config')) {
      continue;
    }

    $colsConfigGlobal = bb_market_colunas_tabela($pdoConfigGlobal, 'config');
    if (!isset($colsConfigGlobal['api'], $colsConfigGlobal['token'], $colsConfigGlobal['instancia'])) {
      continue;
    }

    $c = $pdoConfigGlobal->query("SELECT api, token, instancia FROM config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if ($c) {
      $apiW = trim((string)($c['api'] ?? ''));
      $tokenW = trim((string)($c['token'] ?? ''));
      $instanciaW = trim((string)($c['instancia'] ?? ''));
      if ($apiW !== '' && $tokenW !== '' && $instanciaW !== '') {
        break;
      }
    }
  } catch (Throwable $e) {
  }
}

if ($apiW === '' || $tokenW === '' || $instanciaW === '') {
  bb_market_json_exit([
    'ok' => false,
    'msg' => 'Config de WhatsApp (api/token/instancia) não encontrada na tabela config.',
    'reload' => false,
    'url' => $linkLocal
  ]);
}

$valorF = number_format($valor, 2, ',', '.');
$vencF = $vencimento ? date('d/m/Y', strtotime($vencimento)) : '-';

$mensagem  = "💳 *Assinatura BarberBot*%0A";
$mensagem .= "🏪 Barbearia: *" . $nome . "*%0A";
if ($slug !== '') $mensagem .= "🔖 Slug: " . $slug . "%0A";
if ($dominioPersonalizado !== '') $mensagem .= "🌐 Domínio: " . $dominioPersonalizado . "%0A";
$mensagem .= "💰 Valor: R$ " . $valorF . "%0A";
$mensagem .= "📅 Vencimento: " . $vencF . "%0A%0A";
$mensagem .= "👉 Clique para pagar:%0A";
$mensagem .= $linkLocal;

$api = $apiW;
$token = $tokenW;
$instancia = $instanciaW;

$apiPaths = [
  __DIR__ . "/../../../ajax/api-texto.php",
  __DIR__ . "/../../../../ajax/api-texto.php",
  __DIR__ . "/../../../../pagamentos/api-texto.php",
  __DIR__ . "/../../../../webhook/api-texto.php",
];

$apiFile = '';
foreach ($apiPaths as $ap) {
  if (is_file($ap)) {
    $apiFile = $ap;
    break;
  }
}

if ($apiFile === '') {
  bb_market_json_exit([
    'ok' => false,
    'msg' => 'Arquivo api-texto.php não encontrado.',
    'reload' => false,
    'url' => $linkLocal
  ]);
}

try {
  ob_start();
  include $apiFile;
  $apiOut = trim((string)ob_get_clean());

  $whatsSent = true;
  $whatsMsg = $apiOut;
  $msgSaida = 'Cobrança enviada com sucesso no WhatsApp.';
  if ($tenantFoiBloqueado) {
    $msgSaida .= ' Tenant bloqueado por vencimento.';
  }
} catch (Throwable $e) {
  if (ob_get_level() > 0) {
    @ob_end_clean();
  }

  bb_market_json_exit([
    'ok' => false,
    'msg' => 'Falha ao enviar cobrança no WhatsApp.',
    'reload' => false,
    'url' => $linkLocal
  ]);
}

bb_market_json_exit([
  'ok' => true,
  'msg' => $msgSaida,
  'url' => $linkLocal,
  'url_mp' => $linkMp,
  'fatura_id' => $faturaId,
  'whats_sent' => $whatsSent,
  'whats_msg' => $whatsMsg,
  'blocked' => $tenantFoiBloqueado,
  'reload' => $tenantFoiBloqueado
]);
