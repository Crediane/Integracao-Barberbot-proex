<?php  
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'grupos_clientes';

$id_grupo = $_POST['id_grupo'];

// REMOVER empresa do WHERE
$query = $pdo->query("SELECT * from $tabela where grupo = '$id_grupo' order by id desc");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$linhas = @count($res);

if($linhas > 0){
    echo '<ul class="list-group" style="max-width:600px;margin:auto;">';
    for($i=0; $i<$linhas; $i++){
        $id = $res[$i]['id'];
        $cliente = $res[$i]['cliente'];

        $query2 = $pdo->query("SELECT * from clientes where id = '$cliente'");
        $res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
        $nome_cliente = @$res2[0]['nome'];

        echo <<<HTML
        <li class="list-group-item d-flex justify-content-between align-items-center" style="padding:7px 10px;">
            <span>
                <i class="fa fa-check text-success"></i>
                $nome_cliente
            </span>
            <a href="javascript:void(0)" onclick="excluirCliente('$id')" title="Excluir Cliente" class="btn btn-xs btn-danger">
                <i class="fa fa-trash"></i>
            </a>
        </li>
HTML;
    }
    echo '</ul>';
} else {
    echo '<div class="alert alert-warning text-center">No possui nenhum Registro!</div>';
}
?>

<script type="text/javascript">
function excluirCliente(id) {
    $.ajax({
        url: 'paginas/' + pag + "/excluir_cliente.php",
        method: 'POST',
        data: { id: id },
        dataType: "html",
        success: function (result) {
            listarClientes();
            listarClientesGrupos();
        }
    });
}
</script>
