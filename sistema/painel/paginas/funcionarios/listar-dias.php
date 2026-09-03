<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'dias';

$id_func = isset($_POST['func']) ? (int)$_POST['func'] : 0;

if ($id_func <= 0) {
	echo '<small>Funcionário inválido!</small>';
	exit;
}

// Função mínima para não quebrar onclick por aspas
function esc_js($str){
	$str = (string)$str;
	return str_replace(["\\","'","\r","\n"], ["\\\\","\\'","\\r","\\n"], $str);
}

// Mesma lógica, só prepared
$stmt = $pdo->prepare("SELECT * FROM $tabela where funcionario = :func ORDER BY id asc");
$stmt->bindValue(':func', $id_func, PDO::PARAM_INT);
$stmt->execute();

$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);

if($total_reg > 0){

echo <<<HTML
	<small><small>
	<table class="table table-hover">
	<thead> 
	<tr> 
	<th>Dia</th>	
	<th>Jornada</th>	
	<th>Almoço</th>		
	<th>Excluir</th>
	</tr> 
	</thead> 
	<tbody>	
HTML;

for($i=0; $i < $total_reg; $i++){
	foreach ($res[$i] as $key => $value){}

	$id = (int)$res[$i]['id'];
	$dia = (string)$res[$i]['dia'];
	$inicio = (string)$res[$i]['inicio'];
	$final = (string)$res[$i]['final'];

	$inicio_almoco_raw = $res[$i]['inicio_almoco'] ?? '00:00:00';
	$final_almoco_raw  = $res[$i]['final_almoco'] ?? '00:00:00';

	$inicio_almoco_raw = is_null($inicio_almoco_raw) ? '00:00:00' : (string)$inicio_almoco_raw;
	$final_almoco_raw  = is_null($final_almoco_raw)  ? '00:00:00' : (string)$final_almoco_raw;

	$inicio_almoco = $inicio_almoco_raw;
	$final_almoco  = $final_almoco_raw;

	if($inicio_almoco == '00:00:00'){
		$inicio_almoco = 'Não Lançado';
	}
	if($final_almoco == '00:00:00'){
		$final_almoco = 'Não Lançado';
	}

	$inicio_almoco_edit = ($inicio_almoco_raw == '00:00:00') ? '' : $inicio_almoco_raw;
	$final_almoco_edit  = ($final_almoco_raw  == '00:00:00') ? '' : $final_almoco_raw;

	// Escapa para JS
	$dia_js = esc_js($dia);
	$inicio_js = esc_js($inicio);
	$final_js = esc_js($final);
	$inicio_almoco_js = esc_js($inicio_almoco_edit);
	$final_almoco_js  = esc_js($final_almoco_edit);

	// Escapa para HTML (não muda lógica, só evita quebrar tabela)
	$dia_html = htmlspecialchars($dia, ENT_QUOTES, 'UTF-8');
	$inicio_html = htmlspecialchars($inicio, ENT_QUOTES, 'UTF-8');
	$final_html = htmlspecialchars($final, ENT_QUOTES, 'UTF-8');
	$inicio_almoco_html = htmlspecialchars($inicio_almoco, ENT_QUOTES, 'UTF-8');
	$final_almoco_html = htmlspecialchars($final_almoco, ENT_QUOTES, 'UTF-8');

echo <<<HTML
<tr class="">
<td class="">{$dia_html}</td>
<td class="">{$inicio_html} / {$final_html}</td>
<td class="">{$inicio_almoco_html} / {$final_almoco_html}</td>

<td>
	<li class="dropdown head-dpdn2" style="display: inline-block;">
		<a href="javascript:void(0)" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
			<big><i class="fa fa-trash-o text-danger"></i></big>
		</a>

		<ul class="dropdown-menu" style="margin-left:-230px;">
			<li>
				<div class="notification_desc2">
					<p>Confirmar Exclusão?
						<a href="javascript:void(0)" onclick="excluirDias('{$id}'); return false;">
							<span class="text-danger">Sim</span>
						</a>
					</p>
				</div>
			</li>										
		</ul>
	</li>

	<big>
		<a href="javascript:void(0)" onclick="editarDias('{$id}','{$dia_js}', '{$inicio_js}', '{$final_js}', '{$inicio_almoco_js}', '{$final_almoco_js}'); return false;" title="Editar Dados">
			<i class="fa fa-edit text-primary"></i>
		</a>
	</big>

</td>
</tr>
HTML;

}

echo <<<HTML
</tbody>
<small><div align="center" id="mensagem-dias-excluir"></div></small>
</table>
</small></small>
HTML;

}else{
	echo '<small>Não possui nenhum Dia Cadastrado!</small>';
}
?>

<script type="text/javascript">
	function excluirDias(id){
		$.ajax({
			url: 'paginas/' + pag + "/excluir-dias.php",
			method: 'POST',
			data: {id},
			dataType: "text",
			success: function (mensagem) {            
				if (mensagem.trim() == "Excluído com Sucesso") {   
					var func = $("#id_dias").val();             
					listarDias(func);                
				} else {
					$('#mensagem-dias-excluir').addClass('text-danger');
					$('#mensagem-dias-excluir').text(mensagem);
				}
			}
		});
	}

	function _timeHHMM(v){
		if(!v) return '';
		v = String(v);
		if(v === 'Não Lançado') return '';
		return (v.length >= 5) ? v.substring(0,5) : v;
	}

	function editarDias(id, dia, inicio, final, inicio_almoco, final_almoco){
		$('#id_d').val(id);
		$('#dias').val(dia).change();
		$('#inicio').val(_timeHHMM(inicio));
		$('#final').val(_timeHHMM(final));
		$('#inicio_almoco').val(_timeHHMM(inicio_almoco));
		$('#final_almoco').val(_timeHHMM(final_almoco));	
	}

	function limparCampos(){
		$('#id_d').val('');
	}
</script>
