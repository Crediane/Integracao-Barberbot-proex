<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'grupo_assinaturas';

$query = $pdo->query("SELECT * FROM $tabela ORDER BY id desc");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);
if($total_reg > 0){

echo <<<HTML
	<small>
	<table class="table table-hover" id="tabela">
	<thead> 
	<tr> 
	<th>Descrição</th>	
	<th>Itens</th>	
	<th>Ações</th>
	</tr> 
	</thead> 
	<tbody>	
HTML;

for($i=0; $i < $total_reg; $i++){
	foreach ($res[$i] as $key => $value){}
	$id = $res[$i]['id'];
	$nome = $res[$i]['nome'];	
	$nome_html = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
	$nome_js = str_replace(array("\r", "\n"), ' ', $nome);
	$nome_js = addslashes($nome_js);
	$ativo = $res[$i]['ativo'];
	
	
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


		$query2 = $pdo->query("SELECT * FROM itens_assinaturas where grupo = '$id'");
	$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
	$total_itens = @count($res2);
		


echo <<<HTML
<tr class="{$classe_linha}">
<td>{$nome_html}</td>
<td class="esc">{$total_itens}</td>
<td>
		<big><a href="javascript:void(0)" onclick="editar('{$id}','{$nome_js}'); return false;" title="Editar Dados"><i class="fa fa-edit text-primary"></i></a></big>

		<li class="dropdown head-dpdn2" style="display: inline-block;">
		<a href="javascript:void(0)" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><big><i class="fa fa-trash-o text-danger"></i></big></a>

		<ul class="dropdown-menu" style="margin-left:-230px;">
		<li>
		<div class="notification_desc2">
		<p>Confirmar Exclusão? <a href="javascript:void(0)" onclick="excluir('{$id}'); return false;"><span class="text-danger">Sim</span></a></p>
		</div>
		</li>										
		</ul>
		</li>


		<big><a href="javascript:void(0)" onclick="ativar('{$id}', '{$acao}'); return false;" title="{$titulo_link}"><i class="fa {$icone} text-success"></i></a></big>

			<big><a href="javascript:void(0)" onclick="itens('{$id}', '{$nome_js}'); return false;" title="Adicionar Itens"><i class="fa fa-plus verde"></i></a></big>

		</td>
</tr>
HTML;

}

echo <<<HTML
</tbody>
<small><div align="center" id="mensagem-excluir"></div></small>
</table>
</small>
HTML;


}else{
	echo '<small>Não possui nenhum registro Cadastrado!</small>';
}

?>

<script type="text/javascript">
	$(document).ready( function () {
    $('#tabela').DataTable({
    		"ordering": false,
			"stateSave": true
    	});
    $('#tabela_filter label input').focus();
} );
</script>


<script type="text/javascript">
	function editar(id, nome){
		$('#id').val(id);
		$('#nome').val(nome);
			
		$('#titulo_inserir').text('Editar Registro');
		$('#modalForm').modal('show');
		
	}



	function itens(id, nome){
		
		$('#id_itens').val(id);
		$('#nome_itens').text(nome);			
		
		$('#modalItens').modal('show');

		setTimeout(function() {
		  listarItens();
		}, 500)
		
		limparCamposItens()

		
	}
</script>


