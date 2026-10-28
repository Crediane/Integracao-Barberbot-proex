<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'produtos';

$query = $pdo->query("SELECT * FROM $tabela ORDER BY id desc");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);
if($total_reg > 0){

echo <<<HTML
	<small>
	<table class="table table-hover" id="tabela">
	<thead> 
	<tr> 
	<th>Nome</th>	
	<th class="esc">Categoria</th> 	
	<th class="esc">Valor Compra</th> 	
	<th class="esc">Valor Venda</th> 
	<th class="esc">Estoque</th>	
	<th>Ações</th>
	</tr> 
	</thead> 
	<tbody>	
HTML;

for($i=0; $i < $total_reg; $i++){
	foreach ($res[$i] as $key => $value){}
	$id = $res[$i]['id'];
	$nome = $res[$i]['nome'];	
	$descricao = $res[$i]['descricao'];
	$categoria = $res[$i]['categoria'];
	$valor_compra = $res[$i]['valor_compra'];
    $valor_compra = number_format($valor_compra, 2, ',', '.');
	$valor_venda = $res[$i]['valor_venda'];
    $valor_venda = number_format($valor_venda, 2, ',', '.');
	$foto = $res[$i]['foto'];
	$estoque = $res[$i]['estoque'];
	$nivel_estoque = $res[$i]['nivel_estoque'];

		$query2 = $pdo->query("SELECT * FROM cat_produtos where id = '$categoria'");
		$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
		$total_reg2 = @count($res2);
		if($total_reg2 > 0){
			$nome_cat = $res2[0]['nome'];
		}else{
			$nome_cat = 'Sem Referência!';
		}

		// Segurança: evita quebrar HTML/JS e impedir clique redirecionar
		$nome_html = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
		$nome_js = addslashes(str_replace(array("\r", "\n"), ' ', $nome));
		$descricao_js = addslashes(str_replace(array("\r", "\n"), ' ', $descricao));
		$foto_js = addslashes($foto);
		$nome_cat_html = htmlspecialchars($nome_cat, ENT_QUOTES, 'UTF-8');
		$nome_cat_js = addslashes(str_replace(array("\r", "\n"), ' ', $nome_cat));



		if($nivel_estoque >= $estoque){
			$alerta_estoque = 'text-danger';
		}else{
			$alerta_estoque = '';
		}
		


echo <<<HTML
<tr class="{$alerta_estoque}">
<td>
<img src="img/produtos/{$foto}" width="27px" class="mr-2">
{$nome_html}
</td>
<td class="esc">{$nome_cat_html}</td>
<td class="esc">R$ {$valor_compra}</td>
<td class="esc">R$ {$valor_venda}</td>
<td class="esc">{$estoque}</td>

<td>
		<big><a href="javascript:void(0)" onclick="editar('{$id}','{$nome_js}', '{$categoria}', '{$descricao_js}', '{$valor_compra}', '{$valor_venda}', '{$foto_js}', '{$nivel_estoque}'); return false;" title="Editar Dados"><i class="fa fa-edit text-primary"></i></a></big>

		<big><a href="javascript:void(0)" onclick="mostrar('{$nome_js}', '{$nome_cat_js}', '{$descricao_js}', '{$valor_compra}',  '{$valor_venda}', '{$estoque}', '{$foto_js}', '{$nivel_estoque}'); return false;" title="Ver Dados"><i class="fa fa-info-circle text-secondary"></i></a></big>



		<li class="dropdown head-dpdn2" style="display: inline-block;">
		<a href="javascript:void(0)" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false" onclick="return false;"><big><i class="fa fa-trash-o text-danger"></i></big></a>

		<ul class="dropdown-menu" style="margin-left:-230px;">
		<li>
		<div class="notification_desc2">
		<p>Confirmar Exclusão? <a href="javascript:void(0)" onclick="excluir('{$id}'); return false;"><span class="text-danger">Sim</span></a></p>
		</div>
		</li>										
		</ul>
		</li>


		<big><a href="javascript:void(0)" onclick="saida('{$id}','{$nome_js}', '{$estoque}'); return false;" title="Saída de Produto"><i class="fa fa-sign-out text-danger"></i></a></big>

		<big><a href="javascript:void(0)" onclick="entrada('{$id}','{$nome_js}', '{$estoque}'); return false;" title="Entrada de Produto"><i class="fa fa-sign-in verde"></i></a></big>

	
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
	function editar(id, nome, categoria, descricao, valor_compra, valor_venda, foto, nivel_estoque){
		$('#id').val(id);
		$('#nome').val(nome);
		$('#valor_venda').val(valor_venda);
		$('#valor_compra').val(valor_compra);
		$('#categoria').val(categoria).change();
		$('#descricao').val(descricao);
		$('#nivel_estoque').val(nivel_estoque);
						
		$('#titulo_inserir').text('Editar Registro');
		$('#modalForm').modal('show');
		$('#foto').val('');
		$('#target').attr('src','img/produtos/' + foto);
	}

	function limparCampos(){
		$('#id').val('');
		$('#nome').val('');
		$('#valor_compra').val('');
		$('#valor_venda').val('');
		$('#descricao').val('');		
		$('#foto').val('');
		$('#target').attr('src','img/produtos/sem-foto.jpg');
	}
</script>



<script type="text/javascript">
	function mostrar(nome, categoria, descricao, valor_compra, valor_venda, estoque, foto, nivel_estoque){

		$('#nome_dados').text(nome);
		$('#valor_compra_dados').text(valor_compra);
		$('#categoria_dados').text(categoria);
		$('#valor_venda_dados').text(valor_venda);
		$('#descricao_dados').text(descricao);
		$('#estoque_dados').text(estoque);
		$('#nivel_estoque_dados').text(nivel_estoque);
		
		$('#target_mostrar').attr('src','img/produtos/' + foto);

		$('#modalDados').modal('show');
	}
</script>




<script type="text/javascript">
	function saida(id, nome, estoque){

		$('#nome_saida').text(nome);
		$('#estoque_saida').val(estoque);
		$('#id_saida').val(id);		

		$('#modalSaida').modal('show');
	}
</script>


<script type="text/javascript">
	function entrada(id, nome, estoque){

		$('#nome_entrada').text(nome);
		$('#estoque_entrada').val(estoque);
		$('#id_entrada').val(id);		

		$('#modalEntrada').modal('show');
	}
</script>