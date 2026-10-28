<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
$tabela = 'cobrancas';
require_once __DIR__ . '/../../../conexao.php';

// Receber as datas enviadas pelo formulário (se existirem)
$dataInicial = isset($_POST['p1']) ? $_POST['p1'] : "1900-01-01"; // Data muito antiga para garantir que todos os registros sejam retornados
$dataFinal = isset($_POST['p2']) ? $_POST['p2'] : date('Y-m-d'); // Data atual como padrão
$id_usu = isset($_SESSION['id']) ? $_SESSION['id'] : null;

// Verifica se o usuário é administrador
if (isset($_SESSION['nivel']) && $_SESSION['nivel'] == 'Administrador') {
    // Buscar todas as cobranças pagas dentro do intervalo de data
    $stmt = $pdo->prepare("SELECT * FROM receber WHERE referencia = 'Cobrança' AND pago = 'Sim' AND data_venc BETWEEN :dataInicial AND :dataFinal ORDER BY data_venc DESC");
    $stmt->bindParam(':dataInicial', $dataInicial);
    $stmt->bindParam(':dataFinal', $dataFinal);
} else {
    // Usuário comum vê apenas as cobranças que ele lançou dentro do intervalo de data
    $stmt = $pdo->prepare("SELECT * FROM receber WHERE referencia = 'Cobrança' AND pago = 'Sim' AND usuario_lanc = :id_usu AND data_venc BETWEEN :dataInicial AND :dataFinal ORDER BY data_venc DESC");
    $stmt->bindParam(':id_usu', $id_usu);
    $stmt->bindParam(':dataInicial', $dataInicial);
    $stmt->bindParam(':dataFinal', $dataFinal);
}

$stmt->execute();
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
$linhas = count($res);

if ($linhas > 0) {
    echo <<<HTML
    <small>
        <table class="table table-hover" id="tabela">
        <thead> 
        <tr> 
        <th>Cliente</th>    
        <th class="esc">Valor</th>    
        <th class="esc">Parcela</th>
        <th class="esc">Data</th>    
        <th class="esc">Dia Venc</th>        
        <th>Ações</th>
        </tr> 
        </thead> 
        <tbody>    
    HTML;

    foreach ($res as $row) {
        $id = htmlspecialchars($row['id'] ?? '');
        $valor = htmlspecialchars($row['valor'] ?? '');
        $parcela = htmlspecialchars($row['parcela'] ?? '');
        $data_venc = htmlspecialchars($row['data_venc'] ?? '');
        $data = htmlspecialchars($row['data_lanc'] ?? '');
        $cliente = htmlspecialchars($row['cliente'] ?? '');
        $usuario = htmlspecialchars($row['usuario_lanc'] ?? '');
        $obs = htmlspecialchars($row['obs'] ?? '');
        $frequencia = htmlspecialchars($row['frequencia'] ?? '');

        $data_vencF = date('d/m/Y', strtotime($data_venc));
        $dataF = date('d/m/Y', strtotime($data));
        $valorF = number_format($valor, 2, ',', '.');

        // Nome do cliente
        $query2 = $pdo->prepare("SELECT nome FROM clientes WHERE id = :cliente");
        $query2->bindParam(':cliente', $cliente);
        $query2->execute();
        $nome_cliente = $query2->fetchColumn();

        // Nome do usuário que efetuou o lançamento
        $query2 = $pdo->prepare("SELECT nome FROM usuarios WHERE id = :usuario");
        $query2->bindParam(':usuario', $usuario);
        $query2->execute();
        $nome_usuario = $query2->fetchColumn();

        echo <<<HTML
        <tr>
        <td>{$nome_cliente}</td>
        <td class="esc">R$ {$valorF}</td>
        <td class="esc">{$parcela}</td>
        <td class="esc">{$dataF}</td>
        <td class="esc">{$data_vencF}</td>
        <td>
            <li class="dropdown head-dpdn2" style="display: inline-block;">
                <a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><big><i class="fa fa-info-circle text-primary"></i></big></a>
                <ul class="dropdown-menu" style="margin-left:-230px;">
                <li>
                <div class="notification_desc2">
                <p>
                <span><b>Efetuado Por:</b> {$nome_usuario}</span><br>
                <span><b>Frequência Pgto:</b> {$frequencia}</span><br>
                <span><b>OBS:</b> {$obs}</span><br>
                </p>
                </div>
                </li>                                        
                </ul>
            </li>
        </td>
        </tr>
        HTML;
    }

    echo <<<HTML
    </tbody>
    <small><div align="center" id="mensagem-excluir"></div></small>
    </table>
    HTML;
} else {
    echo '<small>Nenhum Registro Encontrado!</small>';
}
?>

<script type="text/javascript">
    $(document).ready(function () {        
        $('#tabela').DataTable({
            "language": {
                //"url" : '//cdn.datatables.net/plug-ins/1.13.2/i18n/pt-BR.json'
            },
            "ordering": false,
            "stateSave": true
        });
    });
</script>

<script type="text/javascript">
    function selecionar(id) {
        var ids = $('#ids').val();

        if ($('#seletor-' + id).is(":checked") == true) {
            var novo_id = ids + id + '-';
            $('#ids').val(novo_id);
        } else {
            var retirar = ids.replace(id + '-', '');
            $('#ids').val(retirar);
        }

        var ids_final = $('#ids').val();
        if (ids_final == "") {
            $('#btn-deletar').hide();
        } else {
            $('#btn-deletar').show();
        }
    }

    function deletarSel() {
        var ids = $('#ids').val();
        var idArray = ids.split("-");
        
        for (let i = 0; i < idArray.length - 1; i++) {
            excluir(idArray[i]);            
        }

        limparCampos();
    }

    function arquivo(id, nome) {                
        $('#nome_arquivo').text(nome);      
        $('#id_arquivo').val(id);           
        $('#mensagem_arquivo').text(''); 

        listarArquivos();
        $('#modalArquivos').modal('show');
    }
</script>

<script type="text/javascript">
    function mostrarParcelas(id_emp) {  
        var mostrar = 'cobranca';
    
        $.ajax({
            url: 'paginas/clientes/mostar_parcelas.php',
            method: 'POST',
            data: {id_emp: id_emp, mostrar: mostrar},
            dataType: "text",
            success: function (mensagem) {           
               $("#listar_parcelas").html(mensagem);
            },      
        });

        $('#id_emprestimo').val(id_emp);
        $('#modalParcelas').modal('show');
    }
</script>

