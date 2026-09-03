<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'horarios';

$id_func = isset($_POST['func']) ? (int)$_POST['func'] : 0;

if ($id_func <= 0) {
	echo '<small>Funcionário inválido!</small>';
	exit;
}

$stmt = $pdo->prepare("SELECT * FROM {$tabela} WHERE funcionario = :func ORDER BY horario ASC");
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
	<th>Horário</th>		
	<th>Excluir</th>
	</tr> 
	</thead> 
	<tbody>	
HTML;

for($i=0; $i < $total_reg; $i++){
	foreach ($res[$i] as $key => $value){}
	$id = (int)$res[$i]['id'];
	$horario = (string)$res[$i]['horario'];
	$horarioF = date("H:i", strtotime($horario));

	$data = $res[$i]['data'] ?? '';
	$data = is_null($data) ? '' : (string)$data;

	$temp = '';
	if($data != ""){
		$partes = explode('-', $data);
		if(count($partes) === 3){
			$dataF = implode('/', array_reverse($partes));
			$temp = '<span class="text-danger"><small>(Temporário Data: '.$dataF.'</small></span>';
		}
	}

echo <<<HTML
<tr class="">
<td class="">{$horarioF} {$temp}</td>
<td>
		<li class="dropdown head-dpdn2" style="display: inline-block;">
		<a href="javascript:void(0)" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><big><i class="fa fa-trash-o text-danger"></i></big></a>

		<ul class="dropdown-menu" style="margin-left:-230px;">
		<li>
		<div class="notification_desc2">
		<p>Confirmar Exclusão? <a href="javascript:void(0)" onclick="excluirHorarios('{$id}'); return false;"><span class="text-danger">Sim</span></a></p>
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
<small><div align="center" id="mensagem-horario-excluir"></div></small>
</table>
</small></small>
HTML;

}else{
	echo '<small>Não possui nenhum Horário Cadastrado!</small>';
}
?>

<script type="text/javascript">
	function excluirHorarios(id){
		$.ajax({
			url: 'paginas/' + pag + "/excluir-horarios.php",
			method: 'POST',
			data: {id},
			dataType: "text",
			success: function (mensagem) {            
				if (mensagem.trim() == "Excluído com Sucesso") {   
					var func = $("#id_horarios").val();             
					listarHorarios(func);                
				} else {
					$('#mensagem-horario-excluir').addClass('text-danger')
					$('#mensagem-horario-excluir').text(mensagem)
				}
			}
		});
	}
</script>
