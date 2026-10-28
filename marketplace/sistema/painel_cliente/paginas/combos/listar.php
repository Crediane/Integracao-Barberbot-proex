<?php
// ARQUIVO: /sistema/painel_cliente/paginas/combos/listar.php
// Correção no padrão do sistema:
// - sem sessão duplicada
// - usa conexao.php central
// - quando NÃO recebe POST[id], lista combos ativos do cliente logado
// - quando recebe POST[id], mostra histórico do combo
// - mantém a lógica do módulo novo: assinaturas + itens_assinaturas + servicos + assinaturas_consumos

require_once __DIR__ . '/../../verificar.php';

$id_cliente = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;
if ($id_cliente <= 0) {
    echo '<small>Cliente não autenticado!</small>';
    return;
}

function bb_combo_listar_coluna_existe(PDO $pdo, $tabela, $coluna)
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

function bb_combo_listar_tabela_existe(PDO $pdo, $tabela)
{
    static $cache = array();
    if (array_key_exists($tabela, $cache)) {
        return $cache[$tabela];
    }

    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = :tabela");
        $stmt->execute(array(':tabela' => $tabela));
        $cache[$tabela] = ((int)$stmt->fetchColumn() > 0);
    } catch (Throwable $e) {
        $cache[$tabela] = false;
    }

    return $cache[$tabela];
}

function bb_combo_slug_atual()
{
    return bb_pc_limpar_slug($_SESSION['tenant_slug'] ?? '');
}

function bb_combo_expr_vencimento(PDO $pdo)
{
    if (bb_combo_listar_coluna_existe($pdo, 'assinaturas', 'data_vencimento')) {
        return 'COALESCE(a.data_vencimento, a.vencimento)';
    }
    return 'a.vencimento';
}

if (!bb_combo_listar_tabela_existe($pdo, 'assinaturas')
    || !bb_combo_listar_tabela_existe($pdo, 'itens_assinaturas')
    || !bb_combo_listar_tabela_existe($pdo, 'servicos')
    || !bb_combo_listar_tabela_existe($pdo, 'assinaturas_consumos')) {
    echo '<small>O módulo de combos ainda não está disponível nesta loja.</small>';
    return;
}

$id_assinatura = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$id_assinatura = $id_assinatura ? (int)$id_assinatura : 0;

$tem_ativo_ass = bb_combo_listar_coluna_existe($pdo, 'assinaturas', 'ativo');
$tem_cancelado_ass = bb_combo_listar_coluna_existe($pdo, 'assinaturas', 'cancelado');
$tem_combo_ativo = bb_combo_listar_coluna_existe($pdo, 'servicos', 'combo_ativo');
$tem_qtd_sessoes = bb_combo_listar_coluna_existe($pdo, 'servicos', 'combo_qtd_sessoes');
$tem_validade = bb_combo_listar_coluna_existe($pdo, 'servicos', 'combo_validade_dias');
$tem_item_servico = bb_combo_listar_coluna_existe($pdo, 'itens_assinaturas', 'servico');

if (!$tem_combo_ativo || !$tem_qtd_sessoes || !$tem_validade || !$tem_item_servico) {
    echo '<small>O módulo de combos ainda não está disponível nesta loja.</small>';
    return;
}

$expr_vencimento = bb_combo_expr_vencimento($pdo);
$slug_loja = bb_combo_slug_atual();
$link_agendar = ($slug_loja !== '')
    ? '/loja/' . rawurlencode($slug_loja) . '/painel_cliente/agendamentos'
    : '/painel_cliente/agendamentos';

/* --------------------------------------------------------------------------
   MODO 1: histórico do combo
-------------------------------------------------------------------------- */
if ($id_assinatura > 0) {
    $sqlCombo = "SELECT
                    a.id,
                    {$expr_vencimento} AS vencimento_combo,
                    ia.nome AS nome_item,
                    ia.servico AS id_servico,
                    s.nome AS nome_servico,
                    s.combo_qtd_sessoes
                FROM assinaturas a
                INNER JOIN itens_assinaturas ia ON a.item = ia.id
                INNER JOIN servicos s ON ia.servico = s.id
                WHERE a.id = :assinatura
                AND a.cliente = :cliente
                AND a.pago = 'Sim'
                AND s.combo_ativo = 'Sim'";
    if ($tem_ativo_ass) {
        $sqlCombo .= " AND a.ativo = 'Sim'";
    }
    if ($tem_cancelado_ass) {
        $sqlCombo .= " AND (a.cancelado IS NULL OR a.cancelado != 'Sim')";
    }
    $sqlCombo .= " LIMIT 1";

    $queryCombo = $pdo->prepare($sqlCombo);
    $queryCombo->bindValue(':assinatura', $id_assinatura, PDO::PARAM_INT);
    $queryCombo->bindValue(':cliente', $id_cliente, PDO::PARAM_INT);
    $queryCombo->execute();
    $combo = $queryCombo->fetch(PDO::FETCH_ASSOC);

    if (!$combo) {
        echo '<small>Nenhum histórico encontrado!</small>';
        return;
    }

    $queryTotal = $pdo->prepare("SELECT COUNT(*) FROM assinaturas_consumos WHERE assinatura = :assinatura AND cliente = :cliente");
    $queryTotal->bindValue(':assinatura', $id_assinatura, PDO::PARAM_INT);
    $queryTotal->bindValue(':cliente', $id_cliente, PDO::PARAM_INT);
    $queryTotal->execute();
    $usadas = (int)$queryTotal->fetchColumn();

    $total_sessoes = isset($combo['combo_qtd_sessoes']) ? (int)$combo['combo_qtd_sessoes'] : 0;
    $restantes = $total_sessoes - $usadas;
    if ($restantes < 0) {
        $restantes = 0;
    }

    $nome_item = htmlspecialchars((string)$combo['nome_item'], ENT_QUOTES, 'UTF-8');
    $nome_servico = htmlspecialchars((string)$combo['nome_servico'], ENT_QUOTES, 'UTF-8');
    $vencimento = isset($combo['vencimento_combo']) ? (string)$combo['vencimento_combo'] : '';
    $vencimentoF = '-';
    if ($vencimento !== '' && $vencimento !== '0000-00-00') {
        $vencimentoF = implode('/', array_reverse(explode('-', $vencimento)));
    }

    echo '<div class="row" style="margin-bottom:10px">';
    echo '<div class="col-md-3"><strong>Combo:</strong> ' . $nome_item . '</div>';
    echo '<div class="col-md-3"><strong>Serviço:</strong> ' . $nome_servico . '</div>';
    echo '<div class="col-md-2"><strong>Usadas:</strong> ' . $usadas . '</div>';
    echo '<div class="col-md-2"><strong>Restantes:</strong> ' . $restantes . '</div>';
    echo '<div class="col-md-2"><strong>Vence em:</strong> ' . $vencimentoF . '</div>';
    echo '</div>';

    $queryHist = $pdo->prepare("SELECT
                                    ac.id,
                                    ac.servico,
                                    ac.agendamento,
                                    ac.data_baixa,
                                    ac.obs,
                                    s.nome AS nome_servico
                                FROM assinaturas_consumos ac
                                LEFT JOIN servicos s ON ac.servico = s.id
                                WHERE ac.assinatura = :assinatura
                                AND ac.cliente = :cliente
                                ORDER BY ac.id DESC");
    $queryHist->bindValue(':assinatura', $id_assinatura, PDO::PARAM_INT);
    $queryHist->bindValue(':cliente', $id_cliente, PDO::PARAM_INT);
    $queryHist->execute();
    $resHist = $queryHist->fetchAll(PDO::FETCH_ASSOC);
    $total_hist = count($resHist);

    if ($total_hist <= 0) {
        echo '<small>Nenhuma baixa encontrada para este combo.</small>';
        return;
    }

    echo '<small>';
    echo '<table class="table table-hover" id="tabela-historico-combo">';
    echo '<thead><tr><th>Data</th><th>Serviço</th><th>Agendamento</th><th>Obs</th></tr></thead>';
    echo '<tbody>';

    for ($i = 0; $i < $total_hist; $i++) {
        $agendamento = (int)($resHist[$i]['agendamento'] ?? 0);
        $obs = htmlspecialchars((string)($resHist[$i]['obs'] ?? ''), ENT_QUOTES, 'UTF-8');
        $data_baixa = (string)($resHist[$i]['data_baixa'] ?? '');
        $data_baixaF = $data_baixa ? date('d/m/Y H:i', strtotime($data_baixa)) : '-';
        $nome_servico_baixa = htmlspecialchars((string)($resHist[$i]['nome_servico'] ?? 'Sem Serviço'), ENT_QUOTES, 'UTF-8');

        echo '<tr>';
        echo '<td>' . $data_baixaF . '</td>';
        echo '<td>' . $nome_servico_baixa . '</td>';
        echo '<td>#' . $agendamento . '</td>';
        echo '<td>' . $obs . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table></small>';
    ?>
    <script type="text/javascript">
    $(document).ready(function () {
        if ($('#tabela-historico-combo').length) {
            $('#tabela-historico-combo').DataTable({
                "ordering": false,
                "stateSave": true,
                "pageLength": 10
            });
        }
    });
    </script>
    <?php
    return;
}

/* --------------------------------------------------------------------------
   MODO 2: listagem principal dos combos
-------------------------------------------------------------------------- */
$sql = "SELECT
            a.id,
            a.data,
            {$expr_vencimento} AS vencimento_combo,
            a.item,
            a.valor,
            ia.nome AS nome_item,
            ia.servico AS id_servico,
            s.nome AS nome_servico,
            s.combo_qtd_sessoes,
            s.combo_validade_dias
        FROM assinaturas a
        INNER JOIN itens_assinaturas ia ON a.item = ia.id
        INNER JOIN servicos s ON ia.servico = s.id
        WHERE a.cliente = :cliente
        AND a.pago = 'Sim'
        AND s.combo_ativo = 'Sim'";
if ($tem_ativo_ass) {
    $sql .= " AND a.ativo = 'Sim'";
}
if ($tem_cancelado_ass) {
    $sql .= " AND (a.cancelado IS NULL OR a.cancelado != 'Sim')";
}
$sql .= " AND ({$expr_vencimento} IS NULL OR {$expr_vencimento} = '0000-00-00' OR {$expr_vencimento} >= :data_hoje)
        ORDER BY a.id DESC";

$query = $pdo->prepare($sql);
$query->bindValue(':cliente', $id_cliente, PDO::PARAM_INT);
$query->bindValue(':data_hoje', date('Y-m-d'), PDO::PARAM_STR);
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = count($res);

$total_usadas = 0;
$total_restantes = 0;
$total_combos = 0;

if ($total_reg <= 0) {
    echo '<small>Você não possui combos ativos no momento.</small>';
    return;
}

echo '<small>';
echo '<table class="table table-hover" id="tabela-combos">';
echo '<thead>';
echo '<tr>';
echo '<th>Combo</th>';
echo '<th class="esc">Serviço</th>';
echo '<th class="esc">Sessões</th>';
echo '<th class="esc">Usadas</th>';
echo '<th class="esc">Restantes</th>';
echo '<th class="esc">Vencimento</th>';
echo '<th>Ações</th>';
echo '</tr>';
echo '</thead>';
echo '<tbody>';

$possui_combo_ativo = false;

for ($i = 0; $i < $total_reg; $i++) {
    $id_assinatura = (int)$res[$i]['id'];
    $total_sessoes = (int)$res[$i]['combo_qtd_sessoes'];

    $query2 = $pdo->prepare("SELECT COUNT(*) FROM assinaturas_consumos WHERE assinatura = :assinatura AND cliente = :cliente");
    $query2->bindValue(':assinatura', $id_assinatura, PDO::PARAM_INT);
    $query2->bindValue(':cliente', $id_cliente, PDO::PARAM_INT);
    $query2->execute();
    $usadas = (int)$query2->fetchColumn();

    $restantes = $total_sessoes - $usadas;
    if ($restantes < 0) {
        $restantes = 0;
    }

    if ($restantes <= 0) {
        continue;
    }

    $possui_combo_ativo = true;
    $total_usadas += $usadas;
    $total_restantes += $restantes;
    $total_combos++;

    $vencimento = isset($res[$i]['vencimento_combo']) ? (string)$res[$i]['vencimento_combo'] : '';
    $vencimentoF = '-';
    if ($vencimento !== '' && $vencimento !== '0000-00-00') {
        $vencimentoF = implode('/', array_reverse(explode('-', $vencimento)));
    }

    $nome_item = htmlspecialchars((string)$res[$i]['nome_item'], ENT_QUOTES, 'UTF-8');
    $nome_servico = htmlspecialchars((string)$res[$i]['nome_servico'], ENT_QUOTES, 'UTF-8');

    echo '<tr>';
    echo '<td>' . $nome_item . '</td>';
    echo '<td class="esc">' . $nome_servico . '</td>';
    echo '<td class="esc">' . $total_sessoes . '</td>';
    echo '<td class="esc">' . $usadas . '</td>';
    echo '<td class="esc"><span class="verde"><strong>' . $restantes . '</strong></span></td>';
    echo '<td class="esc">' . $vencimentoF . '</td>';
    echo '<td>';
    echo '<a href="javascript:void(0)" onclick="verHistoricoCombo(' . $id_assinatura . ')" title="Ver histórico" style="margin-right:8px"><i class="fa fa-list-alt text-primary"></i></a>';
    echo '<a href="' . $link_agendar . '" title="Agendar sessão do combo"><i class="fa fa-calendar-plus-o text-success"></i></a>';
    echo '</td>';
    echo '</tr>';
}

echo '</tbody>';
echo '</table>';
echo '</small>';

if ($possui_combo_ativo) {
    echo '<hr>';
    echo '<div class="row">';
    echo '<div class="col-md-4"><strong>Combos ativos:</strong> ' . $total_combos . '</div>';
    echo '<div class="col-md-4"><strong>Sessões usadas:</strong> ' . $total_usadas . '</div>';
    echo '<div class="col-md-4"><strong>Saldo restante:</strong> ' . $total_restantes . '</div>';
    echo '</div>';
} else {
    echo '<small>Você não possui combos ativos no momento.</small>';
}
?>
<script type="text/javascript">
$(document).ready(function () {
    if ($('#tabela-combos').length) {
        $('#tabela-combos').DataTable({
            "ordering": false,
            "stateSave": true
        });
        $('#tabela-combos_filter label input').focus();
    }
});
</script>
