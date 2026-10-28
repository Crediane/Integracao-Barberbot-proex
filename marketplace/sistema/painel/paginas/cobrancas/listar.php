<?php  
require_once __DIR__ . '/../_context_bootstrap.php';
$tabela = 'cobrancas';
require_once __DIR__ . '/../../../conexao.php';
$data_atual = date('Y-m-d');

$dataInicial = $_POST['p1'] ?? '';
$dataFinal = $_POST['p2'] ?? '';
$id_usu = $_SESSION['id'] ?? '';

$consultas = [];
$parametros = [];

// Verificação dos parâmetros
if ($dataFinal == "" || $dataInicial == "") {
    $queryText = "SELECT * FROM $tabela ORDER BY id DESC";
} else {
    $queryText = "SELECT * FROM $tabela WHERE data >= :dataInicial AND data <= :dataFinal";
    $parametros = [':dataInicial' => $dataInicial, ':dataFinal' => $dataFinal];

    if ($_SESSION['nivel'] !== 'Administrador') {
        $queryText .= " AND usuario = :usuario";
        $parametros[':usuario'] = $id_usu;
    }
    $queryText .= " ORDER BY id DESC";
}

$query = $pdo->prepare($queryText);
$query->execute($parametros);
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$linhas = count($res);

if ($linhas > 0) {
    echo '<small><table class="table table-hover" id="tabela"><thead><tr>';
    echo '<th>Cliente</th><th class="esc">Valor</th><th class="esc">Parcelas</th>';
    echo '<th class="esc">Data</th><th class="esc">Dia Venc</th><th>Ações</th>';
    echo '</tr></thead><tbody>';

    foreach ($res as $row) {
        $id = $row['id'];
        $valor = $row['valor'];
        $parcelas = $row['parcelas'];
        $data_venc = $row['data_venc'];
        $data = $row['data'];
        $cliente = $row['cliente'];
        $juros = $row['juros'];
        $multa = $row['multa'];
        $usuario = $row['usuario'];
        $obs = $row['obs'];
        $frequencia = $row['frequencia'];
        $pago = $row['pago']; // Mudança aqui

         $ativo = $row['ativo'];

        // Verificar se a cobrança já foi paga
        if ($pago == 'Sim') {
            continue; // Ignorar cobrança paga
        }

        // Corrigir a comparação da frequência
        switch ($frequencia) {
            case 'Mensal':
                $frequencia_dias = '30';
                break;
            case 'Diária':
                $frequencia_dias = '1';
                break;
            case 'Semanal':
                $frequencia_dias = '7';
                break;
            case 'Trimestral':
                $frequencia_dias = '90';
                break;
            case 'Semestral':
                $frequencia_dias = '180';
                break;
            case 'Anual':
                $frequencia_dias = '360';
                break;
            default:
                $frequencia_dias = '';
        }

        // Formatação
        $data_vencF = date('d', strtotime($data_venc));
        $dataF = date('d/m/Y', strtotime($data));
        $valorF = number_format($valor, 2, ',', '.');
        $jurosF = number_format($juros, 2, ',', '.');
        $multaF = number_format($multa, 2, ',', '.');

        // Buscar nome do cliente
        $queryCliente = $pdo->prepare("SELECT nome FROM clientes WHERE id = :cliente");
        $queryCliente->execute([':cliente' => $cliente]);
        $resCliente = $queryCliente->fetch(PDO::FETCH_ASSOC);
        $nome_cliente = $resCliente['nome'];

        // Buscar nome do usuário
        $queryUsuario = $pdo->prepare("SELECT nome FROM usuarios WHERE id = :usuario");
        $queryUsuario->execute([':usuario' => $usuario]);
        $resUsuario = $queryUsuario->fetch(PDO::FETCH_ASSOC);
        $nome_usuario = @$resUsuario['nome'];

        // Verificar se todas as parcelas foram pagas
        $queryPagas = $pdo->prepare("SELECT COUNT(*) as total FROM receber WHERE referencia = 'Cobrança' AND id_ref = :id AND pago = 'Sim'");
        $queryPagas->execute([':id' => $id]);
        $parcelas_pagas = $queryPagas->fetchColumn();

        // Verificar o total de parcelas para essa cobrança
        $queryTotalParcelas = $pdo->prepare("SELECT COUNT(*) as total FROM receber WHERE id_ref = :id");
        $queryTotalParcelas->execute([':id' => $id]);
        $total_parcelas = $queryTotalParcelas->fetchColumn();

        // Se o número de parcelas pagas for igual ao total de parcelas, essa cobrança já está paga
        if ($parcelas_pagas == $total_parcelas) {
            continue; // Ignorar cobrança completamente paga
        }

        // Exibir a quantidade de parcelas
        $parcelas_nome = "$parcelas_pagas / $total_parcelas"; // Mostrando o número de parcelas pagas sobre o total




            if($ativo == 'Sim'){
            $icone = 'fa-check-square';
            $titulo_link = 'Desativar Item';
            $acao = 'Não';
            $classe_linha = '';
        }else{
            $icone = 'fa-square-o';
            $titulo_link = 'Ativar Item';
            $acao = 'Sim';
            $classe_linha = 'text-muted';
        }

        echo <<<HTML
<tr>
    <td class="{$classe_linha}">
        <input type="checkbox" id="seletor-$id" class="form-check-input" onchange="selecionar('$id')">
        $nome_cliente
    </td>
    <td class="esc">R$ $valorF</td>
    <td class="esc">$parcelas_nome</td> <!-- Aqui estamos imprimindo a quantidade de parcelas -->
    <td class="esc">$dataF</td>
    <td class="esc">$data_vencF</td>
    <td>
        <big><a href="#" onclick="editar('$cliente','$id','$parcelas','$valor','$data_venc','$obs','$frequencia_dias')" title="Editar Dados"><i class="fa fa-edit text-primary"></i></a></big>

        <li class="dropdown head-dpdn2" style="display: inline-block;">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><big><i class="fa fa-trash-o text-danger"></i></big></a>
            <ul class="dropdown-menu" style="margin-left:-230px;">
                <li>
                    <div class="notification_desc2">
                        <p>Confirmar Exclusão? <a href="#" onclick="excluir1('$id')"><span class="text-danger">Sim</span></a></p>
                    </div>
                </li>
            </ul>
        </li>

        <li class="dropdown head-dpdn2" style="display: inline-block;">
            <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><big><i class="fa fa-info-circle text-primary"></i></big></a>
            <ul class="dropdown-menu" style="margin-left:-230px;">
                <li>
                    <div class="notification_desc2">
                        <p>
                            <span><b>Multa por Atraso:</b> R$ $multaF</span><br>
                            <span><b>Júros dia Atraso:</b> $jurosF%</span><br>
                            <span><b>Efetuado Por:</b> $nome_usuario</span><br>
                            <span><b>Frequência Pgto:</b> $frequencia_dias</span><br>
                            <span><b>OBS:</b> $obs</span><br>
                        </p>
                    </div>
                </li>
            </ul>
        </li>

        <big><a href="#" onclick="arquivo('$id','$nome_cliente')" title="Inserir / Ver Arquivos"><i class="fa fa-file-archive-o" style="color:#3d1002"></i></a></big>
        <big><a href="#" onclick="mostrarParcelas('$id')" title="Mostrar Parcelas"><i class="fa fa-money verde"></i></a></big>

        <big><a href="#" onclick="ativar('{$id}', '{$acao}')" title="{$titulo_link}"><i class="fa {$icone} text-success"></i></a></big>

    </td>
</tr>
HTML;
    }

    echo '</tbody><small><div align="center" id="mensagem-excluir"></div></small></table>';
} else {
    echo '<small>Nenhum Registro Encontrado!</small>';
}

?>


<script type="text/javascript">
$(document).ready(function() {
    $('#tabela').DataTable({
        "language": {
            //"url": '//cdn.datatables.net/plug-ins/1.13.2/i18n/pt-BR.json'
        },
        "ordering": false,
        "stateSave": true
    });
});
</script>

<script type="text/javascript">
function editar(cliente, id, parcelas, valor, data_venc, obs, frequencia) {
    $('#mensagem').text('');
    $('#titulo_inserir').text('Editar Registro');

    $('#id_cob').val(id);
    $('#id_cob2').val(cliente);
    $('#parcelas_cob').val(parcelas);
    $('#valor_cob').val(valor);
    mascara_valor('valor_cob');
    
    
    $('#data_venc_cob').val(data_venc);
    $('#obs_cob').val(obs);

    $('#frequencia_cob').val(frequencia).change();

    $('#modalCobranca').modal('show');
}








function ativar(id, acao){
    

    $.ajax({
        url: 'paginas/' + pag + "/mudar-status.php",
        method: 'POST',
        data: {id, acao},
        dataType: "text",

        success: function (mensagem) {   
         
            if (mensagem.trim() == "Alterado com Sucesso") {                
                listar();                
            } else {
                $('#mensagem-excluir').addClass('text-danger')
                $('#mensagem-excluir').text(mensagem)
            }

        },      

    });
}






function selecionar(id) {
    var ids = $('#ids').val();

    if ($('#seletor-' + id).is(":checked")) {
        var novo_id = ids + id + '-';
        $('#ids').val(novo_id);
    } else {
        var retirar = ids.replace(id + '-', '');
        $('#ids').val(retirar);
    }

    var ids_final = $('#ids').val();
    $('#btn-deletar').toggle(ids_final !== "");
}

function deletarSel() {
    var ids = $('#ids').val();
    var idList = ids.split("-");

    idList.slice(0, -1).forEach(function(id) {
        excluir(id);
    });

    limparCampos();
}

function arquivo(id, nome) {
    $('#nome_arquivo').text(nome);
    $('#id_arquivo').val(id);
    $('#mensagem_arquivo').text('');

    listarArquivos();
    $('#modalArquivos').modal('show');
}

function mostrarParcelas(id_emp) {
    var mostrar = 'cobranca';

    $.ajax({
        url: 'paginas/clientes/mostar_parcelas.php',
        method: 'POST',
        data: {id_emp, mostrar},
        dataType: "text",
        success: function(mensagem) {
            $("#listar_parcelas").html(mensagem);
        },
    });

    $('#id_emprestimo').val(id_emp);
    $('#modalParcelas').modal('show');
}

function excluir1(id) {
    $.ajax({
        url: 'paginas/' + pag + "/excluir.php",
        method: 'POST',
        data: {id},
        dataType: "text",
        success: function(mensagem) {
            if (mensagem.trim() == "Excluído com Sucesso") {
                buscar();
            } else {
                $('#mensagem-excluir').addClass('text-danger').text(mensagem);
            }
        },
    });
}
</script>