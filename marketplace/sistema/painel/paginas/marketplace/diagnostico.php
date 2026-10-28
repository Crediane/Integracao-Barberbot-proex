<?php
require_once __DIR__ . '/master_bootstrap.php';

if (!function_exists('bb_market_diag_json')) {
    function bb_market_diag_json(array $payload, int $status = 200): void
    {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
        http_response_code($status);
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('bb_market_diag_decrypt')) {
    function bb_market_diag_decrypt(string $rawPass, string $secret): string
    {
        if ($rawPass === '') {
            return '';
        }
        if (strpos($rawPass, 'enc:') !== 0) {
            return $rawPass;
        }
        if ($secret === '') {
            return '';
        }

        $cryptoFile = __DIR__ . '/../../../tenant_crypto.php';
        if (is_file($cryptoFile)) {
            require_once $cryptoFile;
        }

        if (!function_exists('bb_decrypt_str')) {
            return '';
        }

        $dec = (string)bb_decrypt_str($rawPass, $secret);
        return $dec !== '' ? $dec : '';
    }
}

$acao = strtolower(trim((string)($_POST['acao'] ?? 'listar')));

if ($acao === 'testar') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id <= 0) {
        bb_market_diag_json(['ok' => false, 'msg' => 'ID inválido.'], 400);
    }

    if (!bb_market_master_table_exists($pdoM, $tenantsTable)) {
        bb_market_diag_json(['ok' => false, 'msg' => 'Tabela de barbearias não encontrada no Banco Global/Administrativo.'], 500);
    }

    try {
        $st = $pdoM->prepare("SELECT * FROM `{$tenantsTable}` WHERE id = :id LIMIT 1");
        $st->execute([':id' => $id]);
        $tenant = $st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        $tenant = false;
    }

    if (!$tenant) {
        bb_market_diag_json(['ok' => false, 'msg' => 'Barbearia não encontrada no Banco Global/Administrativo.'], 404);
    }

    $slug = strtolower(trim((string)($tenant['slug'] ?? '')));
    $slug = (string)preg_replace('/[^a-z0-9_-]/', '', $slug);
    $dbName = trim((string)($tenant['db_name'] ?? ''));
    if ($dbName !== '' && !preg_match('/^[a-z0-9_-]+$/i', $dbName)) {
        bb_market_diag_json([
            'ok' => false,
            'msg' => 'O nome do Banco Individual possui formato inválido no cadastro global.'
        ], 422);
    }

    if ($slug === '' || $dbName === '') {
        bb_market_diag_json([
            'ok' => false,
            'msg' => 'Mapeamento incompleto: o tenant precisa possuir slug e db_name.'
        ], 422);
    }

    $cfgTenant = (!empty($cfg['tenant']) && is_array($cfg['tenant'])) ? $cfg['tenant'] : [];
    $host = trim((string)($tenant['db_host'] ?? ''));
    if ($host === '') {
        $host = trim((string)($cfgTenant['host'] ?? 'localhost'));
    }

    $user = trim((string)($tenant['db_user'] ?? ''));
    if ($user === '') {
        $user = trim((string)($cfgTenant['user'] ?? ''));
    }

    $rawPass = (string)($tenant['db_pass'] ?? '');
    if ($rawPass === '') {
        $rawPass = (string)($cfgTenant['pass'] ?? '');
    }

    $secret = (string)($cfg['security']['tenant_secret'] ?? '');
    $pass = bb_market_diag_decrypt($rawPass, $secret);

    if ($host === '' || $user === '') {
        bb_market_diag_json([
            'ok' => false,
            'msg' => 'Credenciais do Banco Individual incompletas para este tenant.'
        ], 422);
    }

    if (strpos($rawPass, 'enc:') === 0 && $pass === '') {
        bb_market_diag_json([
            'ok' => false,
            'msg' => 'A senha criptografada do Banco Individual não pôde ser validada.'
        ], 422);
    }

    try {
        $pdoTenant = new PDO(
            "mysql:host={$host};dbname={$dbName};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        $dbSelecionado = (string)$pdoTenant->query('SELECT DATABASE()')->fetchColumn();
        if ($dbSelecionado !== $dbName) {
            bb_market_diag_json([
                'ok' => false,
                'msg' => 'A conexão abriu um banco diferente do banco individual mapeado.'
            ], 409);
        }

        bb_market_diag_json([
            'ok' => true,
            'msg' => 'Conexão isolada confirmada para /loja/' . $slug . '/.',
            'slug' => $slug,
            'database' => $dbSelecionado,
        ]);
    } catch (Throwable $e) {
        bb_market_diag_json([
            'ok' => false,
            'msg' => 'Não foi possível conectar ao Banco Individual mapeado para esta barbearia.'
        ], 500);
    }
}

$busca = trim((string)($_POST['busca'] ?? ''));
$tenantFiltro = (int)($_POST['tenant'] ?? 0);

if (!bb_market_master_table_exists($pdoM, $tenantsTable)) {
    echo '<div class="alert alert-danger">A tabela de barbearias não foi encontrada no Banco Global/Administrativo.</div>';
    exit;
}

$cols = bb_market_master_columns($pdoM, $tenantsTable);
$has = static function (string $column) use ($cols): bool {
    return isset($cols[$column]);
};

if (!$has('id') || !$has('slug')) {
    echo '<div class="alert alert-danger">A tabela de barbearias não possui as colunas obrigatórias id e slug.</div>';
    exit;
}

$domainsCfg = (!empty($cfg['domains']) && is_array($cfg['domains'])) ? $cfg['domains'] : [];
$domainsTable = preg_replace('/[^a-z0-9_]/i', '', (string)($domainsCfg['table'] ?? 'tenant_domains'));
if ($domainsTable === '') {
    $domainsTable = 'tenant_domains';
}
$temDomains = bb_market_master_table_exists($pdoM, $domainsTable);
$domainCols = $temDomains ? bb_market_master_columns($pdoM, $domainsTable) : [];

$exprNome = $has('nome') ? "t.`nome`" : ($has('dono_nome') ? "t.`dono_nome`" : "''");
$exprStatus = $has('status') ? "t.`status`" : ($has('ativo') ? "CASE WHEN t.`ativo` = 1 THEN 'active' ELSE 'blocked' END" : "'active'");
$exprDbName = $has('db_name') ? "t.`db_name`" : "''";
$exprDbHost = $has('db_host') ? "t.`db_host`" : "''";
$exprDbUser = $has('db_user') ? "t.`db_user`" : "''";
$exprVencimento = $has('vencimento') ? "t.`vencimento`" : "''";

$exprDominio = "''";
if ($temDomains && isset($domainCols['tenant_id'], $domainCols['host'])) {
    $whereDom = ['d.tenant_id = t.id'];
    if (isset($domainCols['ativo'])) {
        $whereDom[] = '(d.ativo = 1 OR d.ativo IS NULL)';
    }
    if (isset($domainCols['principal'])) {
        $whereDom[] = '(d.principal = 1 OR d.principal IS NULL)';
    }

    $orderDom = [];
    if (isset($domainCols['principal'])) {
        $orderDom[] = 'd.principal DESC';
    }
    if (isset($domainCols['id'])) {
        $orderDom[] = 'd.id DESC';
    }
    if (!$orderDom) {
        $orderDom[] = 'd.host ASC';
    }

    $exprDominio = "(
        SELECT d.host
          FROM `{$domainsTable}` d
         WHERE " . implode(' AND ', $whereDom) . "
         ORDER BY " . implode(', ', $orderDom) . "
         LIMIT 1
    )";
}

$sql = "
    SELECT
        t.id,
        t.slug,
        {$exprNome} AS nome,
        {$exprStatus} AS status,
        {$exprDbName} AS db_name,
        {$exprDbHost} AS db_host,
        {$exprDbUser} AS db_user,
        {$exprVencimento} AS vencimento,
        {$exprDominio} AS dominio_personalizado
    FROM `{$tenantsTable}` t
    WHERE 1 = 1
";
$params = [];

if ($tenantFiltro > 0) {
    $sql .= ' AND t.id = :tenant_id';
    $params[':tenant_id'] = $tenantFiltro;
}

if ($busca !== '') {
    $parts = ['t.slug LIKE :busca'];
    if ($has('nome')) {
        $parts[] = 't.nome LIKE :busca';
    } elseif ($has('dono_nome')) {
        $parts[] = 't.dono_nome LIKE :busca';
    }
    if ($has('db_name')) {
        $parts[] = 't.db_name LIKE :busca';
    }
    if ($temDomains) {
        $parts[] = $exprDominio . ' LIKE :busca';
    }
    $sql .= ' AND (' . implode(' OR ', $parts) . ')';
    $params[':busca'] = '%' . $busca . '%';
}

$sql .= ' ORDER BY t.id DESC';

try {
    $st = $pdoM->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    echo '<div class="alert alert-danger">Não foi possível carregar o diagnóstico do Banco Global/Administrativo.</div>';
    exit;
}

$total = count($rows);
$mapeados = 0;
$pendentes = 0;
$ativos = 0;
foreach ($rows as $row) {
    $slug = trim((string)($row['slug'] ?? ''));
    $dbName = trim((string)($row['db_name'] ?? ''));
    if ($slug !== '' && $dbName !== '') {
        $mapeados++;
    } else {
        $pendentes++;
    }
    if (strtolower(trim((string)($row['status'] ?? 'active'))) !== 'blocked') {
        $ativos++;
    }
}

$baseUrl = bb_market_master_base_url($cfg);
?>

<div class="bb-diag-summary">
  <div class="bb-diag-card"><span>Total analisado</span><strong><?php echo $total; ?></strong></div>
  <div class="bb-diag-card bb-diag-card-ok"><span>Mapeados</span><strong><?php echo $mapeados; ?></strong></div>
  <div class="bb-diag-card bb-diag-card-warn"><span>Mapeamento pendente</span><strong><?php echo $pendentes; ?></strong></div>
  <div class="bb-diag-card bb-diag-card-info"><span>Ativos</span><strong><?php echo $ativos; ?></strong></div>
</div>

<div class="bb-diag-table-card">
  <div class="bb-diag-table-head">
    <div>
      <strong>Validação de isolamento por tenant</strong>
      <small>O teste abre somente o banco individual informado no registro global.</small>
    </div>
    <span class="bb-diag-master-badge"><i class="fa fa-shield"></i> Master confirmado</span>
  </div>

  <?php if ($rows) { ?>
    <div class="table-responsive">
      <table class="table table-hover bb-diag-table">
        <thead>
          <tr>
            <th>Barbearia</th>
            <th>URL resolvida</th>
            <th>Banco individual</th>
            <th>Credenciais</th>
            <th>Status</th>
            <th>Ação</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            $slug = trim((string)($row['slug'] ?? ''));
            $nome = trim((string)($row['nome'] ?? ''));
            if ($nome === '') {
                $nome = $slug !== '' ? $slug : 'Sem nome';
            }
            $dbName = trim((string)($row['db_name'] ?? ''));
            $dbHost = trim((string)($row['db_host'] ?? ''));
            $dbUser = trim((string)($row['db_user'] ?? ''));
            $dominio = bb_market_master_clean_host((string)($row['dominio_personalizado'] ?? ''));
            $status = strtolower(trim((string)($row['status'] ?? 'active')));
            $mapeado = $slug !== '' && $dbName !== '';

            if ($dominio !== '') {
                $urlPublica = 'https://' . $dominio . '/';
            } elseif ($baseUrl !== '' && $slug !== '') {
                $urlPublica = $baseUrl . '/loja/' . rawurlencode($slug) . '/';
            } else {
                $urlPublica = '/loja/' . rawurlencode($slug) . '/';
            }

            $cfgTenantPadrao = (!empty($cfg['tenant']) && is_array($cfg['tenant'])) ? $cfg['tenant'] : [];
            $hostConfigurado = $dbHost !== '' || !empty($cfgTenantPadrao['host']);
            $userConfigurado = $dbUser !== '' || !empty($cfgTenantPadrao['user']);
            $credenciaisOk = $hostConfigurado && $userConfigurado;
        ?>
          <tr>
            <td>
              <strong><?php echo htmlspecialchars($nome, ENT_QUOTES, 'UTF-8'); ?></strong><br>
              <small>#<?php echo $id; ?> · <?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?></small>
            </td>
            <td>
              <a href="<?php echo htmlspecialchars($urlPublica, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                <?php echo htmlspecialchars($urlPublica, ENT_QUOTES, 'UTF-8'); ?>
              </a>
            </td>
            <td>
              <?php if ($mapeado) { ?>
                <span class="bb-diag-status bb-diag-ok"><i class="fa fa-check-circle"></i> Mapeado</span>
                <code><?php echo htmlspecialchars($dbName, ENT_QUOTES, 'UTF-8'); ?></code>
              <?php } else { ?>
                <span class="bb-diag-status bb-diag-error"><i class="fa fa-times-circle"></i> Incompleto</span>
                <small>Slug ou db_name ausente</small>
              <?php } ?>
            </td>
            <td>
              <?php if ($credenciaisOk) { ?>
                <span class="bb-diag-status bb-diag-ok"><i class="fa fa-key"></i> Configuradas</span>
              <?php } else { ?>
                <span class="bb-diag-status bb-diag-error"><i class="fa fa-key"></i> Incompletas</span>
              <?php } ?>
            </td>
            <td>
              <?php if ($status === 'blocked') { ?>
                <span class="bb-diag-status bb-diag-error"><i class="fa fa-lock"></i> Bloqueado</span>
              <?php } else { ?>
                <span class="bb-diag-status bb-diag-ok"><i class="fa fa-unlock"></i> Ativo</span>
              <?php } ?>
            </td>
            <td>
              <button type="button" class="btn btn-sm btn-outline-primary bb-btn-testar-tenant" data-id="<?php echo $id; ?>" <?php echo !$mapeado || !$credenciaisOk ? 'disabled' : ''; ?>>
                <i class="fa fa-plug"></i> Testar conexão
              </button>
            </td>
          </tr>
        <?php } ?>
        </tbody>
      </table>
    </div>
  <?php } else { ?>
    <div class="bb-diag-empty">Nenhuma barbearia encontrada para o diagnóstico informado.</div>
  <?php } ?>
</div>
