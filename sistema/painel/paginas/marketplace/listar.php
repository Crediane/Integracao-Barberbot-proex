<?php
require_once __DIR__ . '/master_bootstrap.php';

$busca = trim((string)($_POST['busca'] ?? ''));
$filtroStatus = strtolower(trim((string)($_POST['status'] ?? '')));
$filtroPlano = strtolower(trim((string)($_POST['plano'] ?? '')));
$paginaAtual = max(1, (int)($_POST['pagina'] ?? 1));
$porPaginaSolicitado = (int)($_POST['por_pagina'] ?? 10);
$opcoesPorPagina = [10, 25, 50];
$porPagina = in_array($porPaginaSolicitado, $opcoesPorPagina, true) ? $porPaginaSolicitado : 10;

if (!bb_market_master_table_exists($pdoM, $tenantsTable)) {
    echo '<div class="alert alert-danger">A tabela de barbearias não foi encontrada no Banco Global/Administrativo.</div>';
    exit;
}

$colsMap = bb_market_master_columns($pdoM, $tenantsTable);
$has = static function (string $column) use ($colsMap): bool {
    return isset($colsMap[$column]);
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
$temTenantDomains = bb_market_master_table_exists($pdoM, $domainsTable);
$domainCols = $temTenantDomains ? bb_market_master_columns($pdoM, $domainsTable) : [];

$exprNome = $has('nome') ? "t.`nome`" : ($has('dono_nome') ? "t.`dono_nome`" : "''");
$exprStatus = $has('status') ? "t.`status`" : ($has('ativo') ? "CASE WHEN t.`ativo` = 1 THEN 'active' ELSE 'blocked' END" : "'active'");
$exprVencimento = $has('vencimento') ? "t.`vencimento`" : "''";
$exprValor = $has('valor_mensal') ? "t.`valor_mensal`" : "0.00";
$exprTelefone = $has('telefone_whatsapp') ? "t.`telefone_whatsapp`" : ($has('whatsapp') ? "t.`whatsapp`" : "''");
$exprEmail = $has('email') ? "t.`email`" : ($has('dono_email') ? "t.`dono_email`" : "''");
$exprPlano = $has('plano_marketplace') ? "t.`plano_marketplace`" : "'profissional'";
$exprDbName = $has('db_name') ? "t.`db_name`" : "''";

$exprDominio = "''";
if ($temTenantDomains && isset($domainCols['tenant_id'], $domainCols['host'])) {
    $whereDominio = ['d.tenant_id = t.id'];
    if (isset($domainCols['ativo'])) {
        $whereDominio[] = '(d.ativo = 1 OR d.ativo IS NULL)';
    }
    if (isset($domainCols['principal'])) {
        $whereDominio[] = '(d.principal = 1 OR d.principal IS NULL)';
    }

    $orderDominio = [];
    if (isset($domainCols['principal'])) {
        $orderDominio[] = 'd.principal DESC';
    }
    if (isset($domainCols['id'])) {
        $orderDominio[] = 'd.id DESC';
    }
    if (!$orderDominio) {
        $orderDominio[] = 'd.host ASC';
    }

    $exprDominio = "(
        SELECT d.host
          FROM `{$domainsTable}` d
         WHERE " . implode(' AND ', $whereDominio) . "
         ORDER BY " . implode(', ', $orderDominio) . "
         LIMIT 1
    )";
}

$whereSql = ' WHERE 1 = 1';
$params = [];

if ($busca !== '') {
    $searchParts = [];
    if ($has('nome')) {
        $searchParts[] = "t.`nome` LIKE :busca";
    } elseif ($has('dono_nome')) {
        $searchParts[] = "t.`dono_nome` LIKE :busca";
    }
    $searchParts[] = "t.`slug` LIKE :busca";
    if ($has('db_name')) {
        $searchParts[] = "t.`db_name` LIKE :busca";
    }
    if ($temTenantDomains) {
        $searchParts[] = "{$exprDominio} LIKE :busca";
    }

    $whereSql .= ' AND (' . implode(' OR ', $searchParts) . ')';
    $params[':busca'] = '%' . $busca . '%';
}

if (in_array($filtroStatus, ['active', 'blocked'], true)) {
    if ($has('status')) {
        $whereSql .= ' AND t.`status` = :status';
        $params[':status'] = $filtroStatus;
    } elseif ($has('ativo')) {
        $whereSql .= ' AND t.`ativo` = :ativo';
        $params[':ativo'] = $filtroStatus === 'active' ? 1 : 0;
    }
}

if (in_array($filtroPlano, ['profissional', 'business'], true) && $has('plano_marketplace')) {
    $whereSql .= ' AND t.`plano_marketplace` = :plano';
    $params[':plano'] = $filtroPlano;
}

try {
    $stTotal = $pdoM->prepare("SELECT COUNT(*) FROM `{$tenantsTable}` t{$whereSql}");
    $stTotal->execute($params);
    $totalRegistros = (int)$stTotal->fetchColumn();
} catch (Throwable $e) {
    echo '<div class="alert alert-danger">Não foi possível contar as barbearias no Banco Global/Administrativo.</div>';
    exit;
}

$totalPaginas = max(1, (int)ceil($totalRegistros / $porPagina));
if ($paginaAtual > $totalPaginas) {
    $paginaAtual = $totalPaginas;
}
$offset = ($paginaAtual - 1) * $porPagina;

$sql = "
    SELECT
        t.`id`,
        t.`slug`,
        {$exprNome} AS nome,
        {$exprStatus} AS status,
        {$exprVencimento} AS vencimento,
        {$exprValor} AS valor_mensal,
        {$exprTelefone} AS telefone_whatsapp,
        {$exprEmail} AS email,
        {$exprPlano} AS plano_marketplace,
        {$exprDbName} AS db_name,
        {$exprDominio} AS dominio_personalizado
    FROM `{$tenantsTable}` t
    {$whereSql}
    ORDER BY t.`id` DESC
    LIMIT {$porPagina} OFFSET {$offset}
";

try {
    $st = $pdoM->prepare($sql);
    $st->execute($params);
    $rows = $st->fetchAll(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    echo '<div class="alert alert-danger">Não foi possível listar as barbearias no Banco Global/Administrativo.</div>';
    exit;
}

$baseUrl = bb_market_master_base_url($cfg);
$hoje = date('Y-m-d');
$primeiroRegistro = $totalRegistros > 0 ? ($offset + 1) : 0;
$ultimoRegistro = $totalRegistros > 0 ? min($offset + count($rows), $totalRegistros) : 0;

$paginasVisiveis = [];
if ($totalPaginas <= 7) {
    for ($p = 1; $p <= $totalPaginas; $p++) {
        $paginasVisiveis[] = $p;
    }
} else {
    $paginasVisiveis[] = 1;
    $inicioMiolo = max(2, $paginaAtual - 1);
    $fimMiolo = min($totalPaginas - 1, $paginaAtual + 1);
    if ($inicioMiolo > 2) {
        $paginasVisiveis[] = '...';
    }
    for ($p = $inicioMiolo; $p <= $fimMiolo; $p++) {
        $paginasVisiveis[] = $p;
    }
    if ($fimMiolo < $totalPaginas - 1) {
        $paginasVisiveis[] = '...';
    }
    $paginasVisiveis[] = $totalPaginas;
}
?>

<style>
  .marketplace-list-wrap{width:100%;}
  .marketplace-table-card{background:#fff;border:1px solid #e8edf3;border-radius:14px;box-shadow:0 8px 22px rgba(15,23,42,.05);overflow:hidden;}
  .marketplace-table-head{display:flex;align-items:center;justify-content:space-between;gap:14px;padding:13px 16px;border-bottom:1px solid #eef2f7;background:#f8fafc;flex-wrap:wrap;}
  .marketplace-table-head strong{color:#0f172a;font-size:15px;}
  .marketplace-table-head small{color:#64748b;}
  .marketplace-table-tools{display:flex;align-items:center;gap:10px;flex-wrap:wrap;}
  .marketplace-table-tools label{margin:0;color:#64748b;font-size:12px;font-weight:600;display:flex;align-items:center;gap:7px;}
  .marketplace-table-tools select{height:32px;min-width:70px;padding:4px 8px;font-size:12px;}
  .marketplace-table-wrap{width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch;}
  .marketplace-table{width:100%;min-width:900px;margin-bottom:0;table-layout:fixed;}
  .marketplace-table thead th{white-space:nowrap;vertical-align:middle!important;background:#fff;border-top:0;color:#475569;font-size:11px;text-transform:uppercase;letter-spacing:.035em;padding:11px 12px;position:sticky;top:0;z-index:2;}
  .marketplace-table tbody td{vertical-align:middle!important;word-break:break-word;color:#334155;padding:11px 12px;border-color:#eef2f7;}
  .marketplace-table tbody tr:hover{background:#f8fafc;}
  .marketplace-col-barbearia{width:27%;}
  .marketplace-col-acesso{width:24%;}
  .marketplace-col-situacao{width:23%;}
  .marketplace-col-contato{width:20%;}
  .marketplace-col-acoes{width:6%;text-align:center;}
  .marketplace-name{font-weight:700;color:#0f172a;display:block;font-size:14px;line-height:1.35;}
  .marketplace-slug{display:block;margin-top:3px;font-size:11px;color:#64748b;line-height:1.45;}
  .marketplace-meta-line{display:flex;align-items:center;gap:6px;flex-wrap:wrap;margin-top:5px;}
  .marketplace-meta-text{display:block;margin-top:5px;font-size:11px;color:#64748b;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:100%;}
  .marketplace-badge{display:inline-flex;align-items:center;gap:5px;padding:4px 8px;border-radius:999px;font-size:10px;font-weight:700;white-space:nowrap;}
  .marketplace-badge-ok{background:#dcfce7;color:#166534;}
  .marketplace-badge-danger{background:#fee2e2;color:#991b1b;}
  .marketplace-badge-warning{background:#fef3c7;color:#92400e;}
  .marketplace-badge-info{background:#e0f2fe;color:#075985;}
  .marketplace-financeiro{font-size:12px;line-height:1.65;color:#475569;}
  .marketplace-financeiro strong{color:#0f172a;}
  .marketplace-contact{font-size:11px;line-height:1.65;min-width:0;}
  .marketplace-contact div{display:flex;align-items:flex-start;gap:5px;min-width:0;}
  .marketplace-contact i{width:14px;flex:0 0 14px;color:#64748b;margin-top:2px;}
  .marketplace-contact span{display:block;min-width:0;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
  .marketplace-acoes-dropdown .btn{min-width:36px;height:34px;display:inline-flex;align-items:center;justify-content:center;border-radius:8px;}
  .marketplace-acoes-dropdown .dropdown-menu{min-width:220px;padding:6px;right:0;left:auto;box-shadow:0 12px 30px rgba(15,23,42,.16);border:1px solid #e5e7eb;border-radius:10px;}
  .marketplace-acoes-dropdown .dropdown-menu li a{display:flex;align-items:center;gap:9px;padding:8px 10px;border-radius:7px;color:#334155;font-size:12px;text-decoration:none;}
  .marketplace-acoes-dropdown .dropdown-menu li a:hover{background:#f1f5f9;color:#0f172a;}
  .marketplace-acoes-dropdown .dropdown-menu li a i{width:16px;text-align:center;}
  .marketplace-acoes-dropdown .dropdown-divider{height:1px;margin:5px 0;background:#e5e7eb;}
  .marketplace-pagination-wrap{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:13px 16px;border-top:1px solid #eef2f7;background:#fff;flex-wrap:wrap;}
  .marketplace-pagination-info{font-size:12px;color:#64748b;}
  .marketplace-pagination{display:flex;align-items:center;gap:4px;flex-wrap:wrap;margin:0;}
  .marketplace-pagination .btn{min-width:34px;height:32px;padding:5px 9px;font-size:12px;border-radius:7px;}
  .marketplace-pagination .btn.active{background:#0d6efd;border-color:#0d6efd;color:#fff;pointer-events:none;}
  .marketplace-pagination .btn.disabled{opacity:.45;pointer-events:none;}
  .marketplace-pagination-ellipsis{padding:0 4px;color:#94a3b8;}
  .marketplace-vazio{padding:34px 20px;text-align:center;color:#64748b;}

  @media(max-width:991.98px){
    .marketplace-table{min-width:820px;}
  }

  @media(max-width:767.98px){
    .marketplace-table-card{background:transparent;border:0;box-shadow:none;overflow:visible;}
    .marketplace-table-head{background:#fff;border:1px solid #e8edf3;border-radius:12px;margin-bottom:12px;padding:12px;}
    .marketplace-table-tools{width:100%;justify-content:space-between;}
    .marketplace-table-wrap{overflow:visible;}
    .marketplace-table{min-width:100%;border:0;table-layout:auto;}
    .marketplace-table thead{display:none;}
    .marketplace-table,.marketplace-table tbody,.marketplace-table tr,.marketplace-table td{display:block;width:100%;}
    .marketplace-table tbody tr{background:#fff;border:1px solid #e5e7eb;border-radius:13px;padding:0;margin-bottom:12px;box-shadow:0 4px 14px rgba(15,23,42,.06);overflow:visible;}
    .marketplace-table tbody td{border:0!important;padding:11px 13px!important;position:relative;text-align:left!important;width:100%!important;}
    .marketplace-table tbody td+td{border-top:1px solid #f1f5f9!important;}
    .marketplace-table tbody td:before{content:attr(data-label);display:block;margin-bottom:5px;font-size:10px;font-weight:700;color:#94a3b8;text-transform:uppercase;letter-spacing:.04em;}
    .marketplace-table tbody td.marketplace-col-barbearia{padding:14px 13px!important;background:#f8fafc;border-radius:13px 13px 0 0;}
    .marketplace-table tbody td.marketplace-col-barbearia:before{display:none;}
    .marketplace-table tbody td.marketplace-col-acoes{padding:10px 13px!important;}
    .marketplace-table tbody td.marketplace-col-acoes:before{display:none;}
    .marketplace-acoes-dropdown{width:100%;}
    .marketplace-acoes-dropdown .btn{width:100%;height:40px;gap:8px;}
    .marketplace-acoes-dropdown .btn:after{margin-left:auto;}
    .marketplace-acoes-dropdown .btn .marketplace-acoes-texto{display:inline;}
    .marketplace-acoes-dropdown .dropdown-menu{position:static;float:none;width:100%;min-width:100%;margin-top:6px;box-shadow:none;border-radius:9px;}
    .marketplace-contact span,.marketplace-meta-text{white-space:normal;overflow:visible;text-overflow:clip;word-break:break-word;}
    .marketplace-pagination-wrap{padding:12px;border:1px solid #e8edf3;border-radius:12px;margin-top:12px;justify-content:center;text-align:center;}
    .marketplace-pagination-info{width:100%;}
    .marketplace-pagination{justify-content:center;width:100%;}
  }

  @media(min-width:768px){
    .marketplace-acoes-texto{display:none;}
  }
</style>

<div class="marketplace-list-wrap"
     data-marketplace-pagina="<?php echo $paginaAtual; ?>"
     data-marketplace-por-pagina="<?php echo $porPagina; ?>"
     data-marketplace-total="<?php echo $totalRegistros; ?>">
  <div class="marketplace-table-card">
    <div class="marketplace-table-head">
      <div>
        <strong>Barbearias cadastradas</strong><br>
        <small>Exibindo <?php echo $primeiroRegistro; ?>–<?php echo $ultimoRegistro; ?> de <?php echo $totalRegistros; ?> registro(s)</small>
      </div>
      <div class="marketplace-table-tools">
        <span class="marketplace-badge marketplace-badge-info"><i class="fa fa-database"></i> Banco Global</span>
        <label>
          Exibir
          <select class="form-control" onchange="alterarMarketplacePorPagina(this.value)">
            <?php foreach ($opcoesPorPagina as $opcao) { ?>
              <option value="<?php echo $opcao; ?>" <?php echo $porPagina === $opcao ? 'selected' : ''; ?>><?php echo $opcao; ?></option>
            <?php } ?>
          </select>
        </label>
      </div>
    </div>

    <?php if ($rows) { ?>
      <div class="marketplace-table-wrap">
        <table class="table marketplace-table">
          <thead>
            <tr>
              <th class="marketplace-col-barbearia">Barbearia</th>
              <th class="marketplace-col-acesso">Plano e acesso</th>
              <th class="marketplace-col-situacao">Situação</th>
              <th class="marketplace-col-contato">Contato</th>
              <th class="marketplace-col-acoes">Ações</th>
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

              $status = strtolower(trim((string)($row['status'] ?? 'active')));
              $status = $status === 'blocked' ? 'blocked' : 'active';
              $vencimento = trim((string)($row['vencimento'] ?? ''));
              $valorMensal = (float)($row['valor_mensal'] ?? 0);
              $telefone = trim((string)($row['telefone_whatsapp'] ?? ''));
              $email = trim((string)($row['email'] ?? ''));
              $plano = strtolower(trim((string)($row['plano_marketplace'] ?? 'profissional')));
              $plano = $plano === 'business' ? 'business' : 'profissional';
              $dbName = trim((string)($row['db_name'] ?? ''));
              $dominio = bb_market_master_clean_host((string)($row['dominio_personalizado'] ?? ''));

              $mapeado = $slug !== '' && $dbName !== '';
              $vencido = false;
              if ($vencimento !== '' && $vencimento !== '0000-00-00' && preg_match('/^\d{4}-\d{2}-\d{2}$/', substr($vencimento, 0, 10))) {
                  $vencido = substr($vencimento, 0, 10) < $hoje;
              }

              $vencimentoFmt = '-';
              if ($vencimento !== '' && $vencimento !== '0000-00-00') {
                  $timestamp = strtotime($vencimento);
                  if ($timestamp !== false) {
                      $vencimentoFmt = date('d/m/Y', $timestamp);
                  }
              }

              if ($dominio !== '') {
                  $linkSistema = 'https://' . $dominio . '/sistema/';
              } elseif ($baseUrl !== '' && $slug !== '') {
                  $linkSistema = $baseUrl . '/loja/' . rawurlencode($slug) . '/sistema/';
              } else {
                  $linkSistema = '/loja/' . rawurlencode($slug) . '/sistema/';
              }

              $editArgs = htmlspecialchars(json_encode([
                  $id,
                  $slug,
                  $vencimento,
                  number_format($valorMensal, 2, '.', ''),
                  $telefone,
                  $email,
                  $status,
                  $plano,
                  $dominio,
              ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');

              $baixaArgs = htmlspecialchars(json_encode([
                  $id,
                  $slug,
                  $nome,
                  $telefone,
                  'R$ ' . number_format($valorMensal, 2, ',', '.'),
                  $vencimentoFmt,
              ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
          ?>
            <tr>
              <td class="marketplace-col-barbearia" data-label="Barbearia">
                <span class="marketplace-name"><?php echo htmlspecialchars($nome, ENT_QUOTES, 'UTF-8'); ?></span>
                <span class="marketplace-slug">/loja/<?php echo htmlspecialchars($slug, ENT_QUOTES, 'UTF-8'); ?>/</span>
                <div class="marketplace-meta-line">
                  <?php if ($mapeado) { ?>
                    <span class="marketplace-badge marketplace-badge-ok"><i class="fa fa-check-circle"></i> Mapeado</span>
                  <?php } else { ?>
                    <span class="marketplace-badge marketplace-badge-danger"><i class="fa fa-times-circle"></i> Mapeamento incompleto</span>
                  <?php } ?>
                </div>
                <span class="marketplace-meta-text" title="<?php echo htmlspecialchars($dbName, ENT_QUOTES, 'UTF-8'); ?>">
                  <?php echo $dbName !== '' ? htmlspecialchars($dbName, ENT_QUOTES, 'UTF-8') : 'Banco individual não informado'; ?>
                </span>
              </td>

              <td class="marketplace-col-acesso" data-label="Plano e acesso">
                <div class="marketplace-meta-line" style="margin-top:0;">
                  <span class="marketplace-badge marketplace-badge-info"><?php echo $plano === 'business' ? 'Business' : 'Profissional'; ?></span>
                </div>
                <span class="marketplace-meta-text" title="<?php echo htmlspecialchars($dominio, ENT_QUOTES, 'UTF-8'); ?>">
                  <i class="fa fa-globe"></i>
                  <?php echo $dominio !== '' ? htmlspecialchars($dominio, ENT_QUOTES, 'UTF-8') : 'URL dinâmica pelo slug'; ?>
                </span>
              </td>

              <td class="marketplace-col-situacao" data-label="Situação">
                <div class="marketplace-meta-line" style="margin-top:0;">
                  <?php if ($status === 'blocked') { ?>
                    <span class="marketplace-badge marketplace-badge-danger"><i class="fa fa-lock"></i> Bloqueado</span>
                  <?php } else { ?>
                    <span class="marketplace-badge marketplace-badge-ok"><i class="fa fa-unlock"></i> Ativo</span>
                  <?php } ?>
                  <span class="marketplace-badge <?php echo $vencido ? 'marketplace-badge-danger' : 'marketplace-badge-warning'; ?>">
                    <i class="fa fa-calendar"></i> <?php echo htmlspecialchars($vencimentoFmt, ENT_QUOTES, 'UTF-8'); ?>
                  </span>
                </div>
                <div class="marketplace-financeiro">
                  Mensalidade: <strong>R$ <?php echo number_format($valorMensal, 2, ',', '.'); ?></strong>
                </div>
              </td>

              <td class="marketplace-col-contato" data-label="Contato">
                <div class="marketplace-contact">
                  <div><i class="fa fa-whatsapp"></i><span><?php echo $telefone !== '' ? htmlspecialchars($telefone, ENT_QUOTES, 'UTF-8') : '-'; ?></span></div>
                  <div><i class="fa fa-envelope-o"></i><span title="<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>"><?php echo $email !== '' ? htmlspecialchars($email, ENT_QUOTES, 'UTF-8') : '-'; ?></span></div>
                </div>
              </td>

              <td class="marketplace-col-acoes" data-label="Ações">
                <div class="dropdown marketplace-acoes-dropdown">
                  <button type="button" class="btn btn-outline-primary btn-sm dropdown-toggle" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false" title="Abrir ações">
                    <i class="fa fa-ellipsis-v"></i><span class="marketplace-acoes-texto"> Ações</span>
                  </button>
                  <ul class="dropdown-menu dropdown-menu-right">
                    <li>
                      <a href="#" data-args="<?php echo $editArgs; ?>" onclick="abrirTenant.apply(null, JSON.parse(this.dataset.args)); return false;">
                        <i class="fa fa-pencil text-primary"></i> Editar barbearia
                      </a>
                    </li>
                    <li>
                      <?php if ($status === 'blocked') { ?>
                        <a href="#" onclick="toggleStatus(<?php echo $id; ?>, 'active'); return false;">
                          <i class="fa fa-unlock text-success"></i> Desbloquear acesso
                        </a>
                      <?php } else { ?>
                        <a href="#" onclick="toggleStatus(<?php echo $id; ?>, 'blocked'); return false;">
                          <i class="fa fa-lock text-danger"></i> Bloquear acesso
                        </a>
                      <?php } ?>
                    </li>
                    <li class="dropdown-divider"></li>
                    <li>
                      <a href="#" onclick="gerarCobranca(<?php echo $id; ?>); return false;">
                        <i class="fa fa-credit-card text-warning"></i> Gerar cobrança
                      </a>
                    </li>
                    <li>
                      <a href="#" data-args="<?php echo $baixaArgs; ?>" onclick="abrirBaixaMarketplace.apply(null, JSON.parse(this.dataset.args)); return false;">
                        <i class="fa fa-check-square-o text-info"></i> Baixar pagamento
                      </a>
                    </li>
                    <li>
                      <a href="#" onclick="return enviarCobrancaWhatsapp(this, <?php echo $id; ?>);">
                        <i class="fa fa-whatsapp text-success"></i> Enviar cobrança
                      </a>
                    </li>
                    <li class="dropdown-divider"></li>
                    <li>
                      <a href="?pagina=marketplace_diagnostico&amp;tenant=<?php echo $id; ?>">
                        <i class="fa fa-stethoscope"></i> Diagnóstico
                      </a>
                    </li>
                    <?php if ($mapeado) { ?>
                      <li>
                        <a href="<?php echo htmlspecialchars($linkSistema, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                          <i class="fa fa-external-link"></i> Abrir sistema
                        </a>
                      </li>
                    <?php } ?>
                  </ul>
                </div>
              </td>
            </tr>
          <?php } ?>
          </tbody>
        </table>
      </div>

      <div class="marketplace-pagination-wrap">
        <div class="marketplace-pagination-info">
          Página <?php echo $paginaAtual; ?> de <?php echo $totalPaginas; ?> · <?php echo $totalRegistros; ?> registro(s)
        </div>

        <div class="marketplace-pagination" aria-label="Paginação das barbearias">
          <button type="button" class="btn btn-default <?php echo $paginaAtual <= 1 ? 'disabled' : ''; ?>" onclick="listarMarketplace(<?php echo max(1, $paginaAtual - 1); ?>)" <?php echo $paginaAtual <= 1 ? 'disabled' : ''; ?> title="Página anterior">
            <i class="fa fa-angle-left"></i>
          </button>

          <?php foreach ($paginasVisiveis as $paginaItem) {
              if ($paginaItem === '...') { ?>
                <span class="marketplace-pagination-ellipsis">…</span>
              <?php } else {
                  $paginaItemInt = (int)$paginaItem; ?>
                <button type="button" class="btn btn-default <?php echo $paginaItemInt === $paginaAtual ? 'active' : ''; ?>" onclick="listarMarketplace(<?php echo $paginaItemInt; ?>)">
                  <?php echo $paginaItemInt; ?>
                </button>
              <?php }
          } ?>

          <button type="button" class="btn btn-default <?php echo $paginaAtual >= $totalPaginas ? 'disabled' : ''; ?>" onclick="listarMarketplace(<?php echo min($totalPaginas, $paginaAtual + 1); ?>)" <?php echo $paginaAtual >= $totalPaginas ? 'disabled' : ''; ?> title="Próxima página">
            <i class="fa fa-angle-right"></i>
          </button>
        </div>
      </div>
    <?php } else { ?>
      <div class="marketplace-vazio">
        <i class="fa fa-search" style="font-size:24px;margin-bottom:8px;"></i><br>
        Nenhuma barbearia encontrada com os filtros informados.
      </div>
    <?php } ?>
  </div>
</div>
