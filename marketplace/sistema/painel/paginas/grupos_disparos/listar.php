<?php
require_once __DIR__ . '/../_context_bootstrap.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'grupos_disparos';

// Remover filtro empresa (coluna não existe)
$query = $pdo->query("SELECT * from $tabela order by nome asc");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$linhas = @count($res);

if($linhas > 0){
echo <<<HTML
<small>
    <table class="table table-striped table-hover table-bordered text-nowrap border-bottom dt-responsive" id="tabela">
    <thead>
    <tr>
        <th align="center" width="5%" class="text-center">Selecionar</th>
        <th width="70%">Nome</th>
        <th>Clientes</th>
        <th>Ações</th>
    </tr>
    </thead>
    <tbody>
HTML;

for($i=0; $i<$linhas; $i++){
    $id = $res[$i]['id'];
    $nome = $res[$i]['nome'];
    $ativo = $res[$i]['ativo'] ?? 'Sim'; // Evita erro caso não exista a coluna

    // Font Awesome 4.x icons
    if($ativo == 'Sim'){
        $icone = 'fa-check-square';
        $titulo_link = 'Desativar Grupo';
        $acao = 'Não';
        $classe_ativo = '';
    }else{
        $icone = 'fa-square-o';
        $titulo_link = 'Ativar Grupo';
        $acao = 'Sim';
        $classe_ativo = 'color:#c4c4c4;';
    }

    // Clientes do grupo
    $query2 = $pdo->query("SELECT * from grupos_clientes where grupo = '$id'");
    $res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
    $clientes = @count($res2);

echo <<<HTML
<tr style="{$classe_ativo}">
<td align="center">
    <div class="custom-checkbox custom-control">
        <input type="checkbox" class="custom-control-input" id="seletor-{$id}" onchange="selecionar('{$id}')">
        <label for="seletor-{$id}" class="custom-control-label mt-1 text-dark"></label>
    </div>
</td>
<td style="{$classe_ativo}">{$nome}</td>
<td style="{$classe_ativo}">{$clientes}</td>
<td>
    <big>
        <a class="btn btn-info btn-sm" href="javascript:void(0)" onclick="editar('{$id}','{$nome}')" title="Editar Dados">
            <i class="fa fa-edit"></i>
        </a>
    </big>
    <big>
        <a class="btn btn-danger-light btn-sm" href="javascript:void(0)" onclick="excluir('{$id}')" title="Excluir">
            <i class="fa fa-trash"></i>
        </a>
    </big>
    <big>
        <a class="btn btn-success btn-sm" href="javascript:void(0)" onclick="ativar('{$id}', '{$acao}')" title="{$titulo_link}">
            <i class="fa {$icone}"></i>
        </a>
    </big>
    <big>
        <a class="btn btn-primary btn-sm" href="javascript:void(0)" onclick="cliente('{$id}', '{$nome}')" title="Adicionar Cliente">
            <i class="fa fa-plus"></i>
        </a>
    </big>
</td>
</tr>
HTML;

}
}else{
    echo 'Não possui nenhum Registro!';
}

echo <<<HTML
</tbody>
<small><div align="center" id="mensagem-excluir"></div></small>
</table>
HTML;
?>

<script type="text/javascript">
    $(document).ready( function () {
        $('#tabela').DataTable({
            "language" : {
                //"url" : '//cdn.datatables.net/plug-ins/1.13.2/i18n/pt-BR.json'
            },
            "ordering": false,
            "stateSave": true
        });
    } );

    function editar(id, nome){
        $('#mensagem').text('');
        $('#titulo_inserir').text('Editar Registro');
        $('#id').val(id);
        $('#nome').val(nome);
        $('#modalForm').modal('show');
    }

    function limparCampos(){
        $('#id').val('');
        $('#nome').val('');
        $('#ids').val('');
        $('#btn-deletar').hide();
    }

    function selecionar(id){
        var ids = $('#ids').val();
        if($('#seletor-'+id).is(":checked")){
            var novo_id = ids + id + '-';
            $('#ids').val(novo_id);
        }else{
            var retirar = ids.replace(id + '-', '');
            $('#ids').val(retirar);
        }
        var ids_final = $('#ids').val();
        if(ids_final == ""){
            $('#btn-deletar').hide();
        }else{
            $('#btn-deletar').show();
        }
    }

    function deletarSel(){
        var ids = $('#ids').val();
        var id = ids.split("-");
        for(i=0; i<id.length-1; i++){
            excluirMultiplos(id[i]);
        }
        setTimeout(() => {
            listar();
        }, 1000);
        limparCampos();
    }

    function cliente(id, nome){
        $('#titulo_add').text(nome);
        $('#id_add').val(id);
        listarClientes();
        listarClientesGrupos();
        $('#modalAdd').modal('show');
    }
</script>
