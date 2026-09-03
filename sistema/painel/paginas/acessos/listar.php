<?php  
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'acessos';

// Função mínima para não quebrar onclick quando tiver aspas
function esc_js($str){
	$str = (string)$str;
	return str_replace(
		["\\",   "'",   "\r",  "\n"],
		["\\\\","\\'", "\\r", "\\n"],
		$str
	);
}

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
	<th class="esc">Chave</th> 	
	<th class="esc">Grupo</th> 		
	<th>Ações</th>
	</tr> 
	</thead> 
	<tbody>	
HTML;

for($i=0; $i < $total_reg; $i++){
	foreach ($res[$i] as $key => $value){}

	$id = $res[$i]['id'];
	$nome = $res[$i]['nome'];	
	$chave = $res[$i]['chave'];
	$grupo = $res[$i]['grupo'];	

	$query2 = $pdo->query("SELECT * FROM grupo_acessos where id = '$grupo'");
	$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
	$total_reg2 = @count($res2);
	if($total_reg2 > 0){
		$nome_cat = $res2[0]['nome'];
	}else{
		$nome_cat = 'Nenhum!';
	}

	// escapes (JS + HTML)
	$nome_js  = esc_js($nome);
	$chave_js = esc_js($chave);
	$grupo_js = esc_js($grupo);

	$nome_html = htmlspecialchars((string)$nome, ENT_QUOTES, 'UTF-8');
	$chave_html = htmlspecialchars((string)$chave, ENT_QUOTES, 'UTF-8');
	$nome_cat_html = htmlspecialchars((string)$nome_cat, ENT_QUOTES, 'UTF-8');

echo <<<HTML
<tr class="">
<td>{$nome_html}</td>
<td class="esc">{$chave_html}</td>
<td class="esc">{$nome_cat_html}</td>
<td>
	<big>
		<a href="javascript:void(0)" onclick="editar('{$id}','{$nome_js}', '{$chave_js}', '{$grupo_js}'); return false;" title="Editar Dados">
			<i class="fa fa-edit text-primary"></i>
		</a>
	</big>

	<li class="dropdown head-dpdn2" style="display: inline-block;">
		<a href="javascript:void(0)" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
			<big><i class="fa fa-trash-o text-danger"></i></big>
		</a>

		<ul class="dropdown-menu" style="margin-left:-230px;">
			<li>
				<div class="notification_desc2">
					<p>Confirmar Exclusão?
						<a href="javascript:void(0)" onclick="excluir('{$id}'); return false;">
							<span class="text-danger">Sim</span>
						</a>
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
	function editar(id, nome, chave, grupo){
		$('#id').val(id);
		$('#nome').val(nome);
		$('#chave').val(chave);
		$('#grupo').val(grupo).change();

		$('#titulo_inserir').text('Editar Registro');
		$('#modalForm').modal('show');
	}

	function limparCampos(){
		$('#id').val('');
		$('#nome').val('');
		$('#chave').val('');
	}
</script>
