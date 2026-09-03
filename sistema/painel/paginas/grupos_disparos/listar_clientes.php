<?php
require_once __DIR__ . '/../_context_bootstrap.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'clientes';

$id_grupo = $_POST['id_grupo'];

// Busca todos os clientes, ordenados
$query = $pdo->query("SELECT * from $tabela order by nome asc");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$linhas = @count($res);

if($linhas > 0){
echo <<<HTML
<small>
	<table class="table table-sm table-bordered table-striped table-hover text-nowrap dt-responsive" id="tabela_cliente" style="font-size: 13px;">
	<thead>
	<tr>
	<th>Nome</th>
	<th>Adicionar</th>
	</tr>
	</thead>
	<tbody>
HTML;

for($i=0; $i<$linhas; $i++){
	$id = $res[$i]['id'];
	$nome = $res[$i]['nome'];

	// Verifica se já está no grupo (não exibe se já está)
	$query2 = $pdo->query("SELECT * from grupos_clientes where cliente = '$id' and grupo = '$id_grupo'");
	$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
	if(@count($res2) > 0){
		continue;
	}

echo <<<HTML
<tr>
<td>{$nome}</td>
<td>
	<a class="btn btn-info btn-sm" href="javascript:void(0)" onclick="addCliente('{$id}','{$id_grupo}')" title="Adicionar Cliente"><i class="fa fa-plus"></i></a>
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
</small>
HTML;
?>

<script type="text/javascript">
$(document).ready(function () {
  $('#tabela_cliente').DataTable({
    language: {
      "sEmptyTable": "Nenhum registro encontrado",
      "sInfo": "Mostrando de _START_ até _END_ de _TOTAL_ registros",
      "sInfoEmpty": "Mostrando 0 até 0 de 0 registros",
      "sInfoFiltered": "(Filtrados de _MAX_ registros)",
      "sInfoPostFix": "",
      "sInfoThousands": ".",
      "sLengthMenu": "_MENU_ resultados por página",
      "sLoadingRecords": "Carregando...",
      "sProcessing": "Processando...",
      "sZeroRecords": "Nenhum registro encontrado",
      "sSearch": "Buscar:",
      "oPaginate": {
          "sNext": "Próximo",
          "sPrevious": "Anterior",
          "sFirst": "Primeiro",
          "sLast": "Último"
      },
      "oAria": {
          "sSortAscending": ": Ordenar colunas de forma ascendente",
          "sSortDescending": ": Ordenar colunas de forma descendente"
      }
    },
    ordering: false,
    lengthChange: false,
    stateSave: true,
    pageLength: 5,
    dom: '<"top d-flex align-items-center justify-content-start mb-2"f>rt<"bottom"ip>',
    initComplete: function () {
      var $input = $('.dataTables_filter input');
      $input
        .addClass('form-control input-sm border border-dark')
        .attr('placeholder', 'Buscar...')
        .css({
          width: '220px',
          display: 'inline-block',
        });
      $('.dataTables_filter label').css({
        fontWeight: 'normal',
        marginRight: '10px',
      });
    }
  });
});

function addCliente(id, grupo){
	$.ajax({
		url: 'paginas/grupos_disparos/add_clientes.php',
		method: 'POST',
		data: { id: id, grupo: grupo },
		success: function (result) {
			if(result.trim() == 'Adicionado com Sucesso'){
				listarClientes();
				listarClientesGrupos();  
			}else{
				alert(result);
			}
		}
	});
}
</script>

<style>
  #tabela_cliente td, #tabela_cliente th {
    padding: 4px 8px !important;
    vertical-align: middle;
  }
  #tabela_cliente .btn-sm {
    padding: 2px 6px;
    font-size: 12px;
  }
  .dataTables_wrapper .dataTables_filter,
  .dataTables_wrapper .dataTables_info {
    font-size: 12px;
  }
</style>
