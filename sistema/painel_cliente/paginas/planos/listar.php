<?php
// ARQUIVO: /sistema/painel_cliente/paginas/planos/listar.php
// Correção no padrão do sistema:
// - usa o banco já resolvido por /sistema/conexao.php
// - garante filtro pelo cliente logado
// - mantém o link "Pagar" com URL limpa
// - adiciona histórico com Tipo (Combo/Plano), Serviço Vinculado, Pagamento e Situação

require_once __DIR__ . '/../../verificar.php';

$id_cliente = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
if ($id_cliente <= 0) {
    echo '<small>Cliente não autenticado!</small>';
    return;
}

function bb_pc_planos_coluna_existe(PDO $pdo, $tabela, $coluna)
{
    static $cache = array();
    $chave = $tabela . '.' . $coluna;
    if (array_key_exists($chave, $cache)) {
        return $cache[$chave];
    }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :tabela
              AND COLUMN_NAME = :coluna");
        $stmt->execute(array(
            ':tabela' => $tabela,
            ':coluna' => $coluna
        ));
        $cache[$chave] = ((int)$stmt->fetchColumn() > 0);
    } catch (Throwable $e) {
        $cache[$chave] = false;
    }

    return $cache[$chave];
}

function bb_pc_planos_slug_atual()
{
    return bb_pc_limpar_slug($_SESSION['tenant_slug'] ?? '');
}

$slug_loja = bb_pc_planos_slug_atual();
$data_atual = date('Y-m-d');
$tem_ativo = bb_pc_planos_coluna_existe($pdo, 'assinaturas', 'ativo');
$tem_cancelado = bb_pc_planos_coluna_existe($pdo, 'assinaturas', 'cancelado');
$tem_ref_pix = bb_pc_planos_coluna_existe($pdo, 'assinaturas', 'ref_pix');
$tem_frequencia = bb_pc_planos_coluna_existe($pdo, 'assinaturas', 'frequencia');
$tem_data_vencimento = bb_pc_planos_coluna_existe($pdo, 'assinaturas', 'data_vencimento');
$tem_item_servico = bb_pc_planos_coluna_existe($pdo, 'itens_assinaturas', 'servico');
$tem_servico_combo = bb_pc_planos_coluna_existe($pdo, 'servicos', 'combo_ativo');

$expr_vencimento = $tem_data_vencimento ? "COALESCE(a.data_vencimento, a.vencimento)" : "a.vencimento";
$campo_ativo = $tem_ativo ? "a.ativo" : "'Sim' AS ativo";
$campo_cancelado = $tem_cancelado ? "a.cancelado" : "NULL AS cancelado";
$campo_ref_pix = $tem_ref_pix ? "a.ref_pix" : "NULL AS ref_pix";
$campo_frequencia = $tem_frequencia ? "a.frequencia" : "0 AS frequencia";
$campo_id_servico = $tem_item_servico ? "ia.servico AS id_servico" : "0 AS id_servico";
$campo_nome_servico = $tem_item_servico ? "COALESCE(s.nome, 'Não Vinculado') AS nome_servico" : "'Não Vinculado' AS nome_servico";
$campo_combo_ativo = ($tem_item_servico && $tem_servico_combo) ? "COALESCE(s.combo_ativo, 'Não') AS combo_ativo" : "'Não' AS combo_ativo";

$sql = "SELECT
            a.id,
            a.cliente,
            a.data,
            a.grupo,
            a.item,
            a.pago,
            a.valor,
            {$campo_ref_pix},
            {$campo_frequencia},
            {$expr_vencimento} AS vencimento_exibir,
            {$campo_cancelado},
            {$campo_ativo},
            COALESCE(ga.nome, 'Nenhum!') AS nome_grupo,
            COALESCE(ia.nome, 'Nenhum!') AS nome_item,
            {$campo_id_servico},
            {$campo_nome_servico},
            {$campo_combo_ativo}
        FROM assinaturas a
        LEFT JOIN grupo_assinaturas ga ON a.grupo = ga.id
        LEFT JOIN itens_assinaturas ia ON a.item = ia.id";

if ($tem_item_servico) {
    $sql .= " LEFT JOIN servicos s ON ia.servico = s.id";
}

$sql .= " WHERE a.cliente = :cliente
          ORDER BY a.id DESC";

$query = $pdo->prepare($sql);
$query->bindValue(':cliente', $id_cliente, PDO::PARAM_INT);
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = count($res);

if ($total_reg <= 0) {
    echo '<small>Não possui nenhum registro Cadastrado!</small>';
    return;
}

echo '<small>';
echo '<table class="table table-hover" id="tabela">';
echo '<thead>';
echo '<tr>';
echo '<th>Assinatura</th>';
echo '<th class="esc">Plano</th>';
echo '<th class="esc">Serviço</th>';
echo '<th class="esc">Tipo</th>';
echo '<th class="esc">Valor</th>';
echo '<th class="esc">Data</th>';
echo '<th class="esc">Vencimento</th>';
echo '<th class="esc">Pagamento</th>';
echo '<th>Pagar</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

for ($i = 0; $i < $total_reg; $i++) {
    $id = (int)$res[$i]['id'];
    $nome_grupo = htmlspecialchars((string)$res[$i]['nome_grupo'], ENT_QUOTES, 'UTF-8');
    $nome_item = htmlspecialchars((string)$res[$i]['nome_item'], ENT_QUOTES, 'UTF-8');
    $nome_servico = htmlspecialchars((string)$res[$i]['nome_servico'], ENT_QUOTES, 'UTF-8');

    $pago = (string)$res[$i]['pago'];
    $valor = (float)$res[$i]['valor'];
    $valorF = number_format($valor, 2, ',', '.');

    $data = (string)($res[$i]['data'] ?? '');
    $dataF = ($data && $data !== '0000-00-00') ? implode('/', array_reverse(explode('-', $data))) : '-';

    $vencimento = (string)($res[$i]['vencimento_exibir'] ?? '');
    $vencimentoF = ($vencimento && $vencimento !== '0000-00-00') ? implode('/', array_reverse(explode('-', $vencimento))) : '-';

    $ref_pix = (string)($res[$i]['ref_pix'] ?? '');
    $cancelado = (string)($res[$i]['cancelado'] ?? '');
    $ativo = (string)($res[$i]['ativo'] ?? 'Sim');
    $combo_ativo = (string)($res[$i]['combo_ativo'] ?? 'Não');

    if ($pago !== 'Sim') {
        $classe_alerta = 'text-danger';
        $visivel_pagar = '';
        $badge_pagamento = '<span class="badge badge-warning">Pendente</span>';
    } else {
        $classe_alerta = 'verde';
        $visivel_pagar = 'ocultar';
        $badge_pagamento = '<span class="badge badge-success">Pago</span>';
    }

    if ($cancelado === 'Sim') {
        $classe_alerta = 'text-warning';
        $badge_pagamento = '<span class="badge badge-danger">Cancelado</span>';
        $visivel_pagar = 'ocultar';
    } elseif ($ativo !== 'Sim') {
        $classe_alerta = 'text-muted';
        $badge_pagamento = '<span class="badge badge-secondary">Inativo</span>';
        $visivel_pagar = 'ocultar';
    }

    $badge_tipo = ($combo_ativo === 'Sim')
        ? '<span class="badge badge-primary">Combo</span>'
        : '<span class="badge badge-info">Plano</span>';

    if ($pago === 'Não' && $ref_pix !== '') {
        require_once __DIR__ . '/../../../../pagamentos/consultar_pagamento.php';
        if (isset($status_api) && $status_api === 'approved') {
            $id_usuario = $id_cliente;
            $forma_pgto = 'MP';
            $data_pgto = $data_atual;
            require_once __DIR__ . '/aprovar_plano.php';
            $badge_pagamento = '<span class="badge badge-success">Pago</span>';
            $visivel_pagar = 'ocultar';
            $classe_alerta = 'verde';
        }
    }

    if ($slug_loja !== '') {
        $link_pagar = '/loja/' . rawurlencode($slug_loja) . '/plano/' . rawurlencode((string)$id) . '/';
    } else {
        $link_pagar = '/plano/' . rawurlencode((string)$id) . '/';
    }

    echo '<tr>';
    echo '<td><i class="fa fa-square ' . $classe_alerta . '"></i> ' . $nome_grupo . '</td>';
    echo '<td class="esc">' . $nome_item . '</td>';
    echo '<td class="esc">' . $nome_servico . '</td>';
    echo '<td class="esc">' . $badge_tipo . '</td>';
    echo '<td class="esc">' . $valorF . '</td>';
    echo '<td class="esc">' . $dataF . '</td>';
    echo '<td class="esc">' . $vencimentoF . '</td>';
    echo '<td class="esc">' . $badge_pagamento . '</td>';
    echo '<td>';
    echo '<big><a class="' . $visivel_pagar . '" href="' . $link_pagar . '" target="_blank" title="Efetuar Pagamento"><i class="fa fa-usd verde"></i></a></big>';
    echo '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</small>';
?>
<script type="text/javascript">
$(document).ready(function () {
    $('#tabela').DataTable({
        "ordering": false,
        "stateSave": true
    });
    $('#tabela_filter label input').focus();
});
</script>
