<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'itens_assinaturas';

$id_grupo = $_POST['id'];

$query = $pdo->query("SELECT * FROM $tabela where grupo = '$id_grupo' ORDER BY id desc");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);
if($total_reg > 0){

echo <<<HTML
	<small>
	<table class="table table-hover" id="">
	<thead> 
	<tr> 
	<th>Nome</th>	
	<th>Valor</th>
	<th>Características</th>
	<th class="esc">Serviço Vinculado</th>
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
	$valor = $res[$i]['valor'];	
	$ativo = $res[$i]['ativo'];
	$c1 = $res[$i]['c1'];
	$c2 = $res[$i]['c2'];
	$c3 = $res[$i]['c3'];
	$c4 = $res[$i]['c4'];
	$c5 = $res[$i]['c5'];
	$c6 = $res[$i]['c6'];
	$c7 = $res[$i]['c7'];
	$c8 = $res[$i]['c8'];
	$c9 = $res[$i]['c9'];
	$c10 = $res[$i]['c10'];
	$c11 = $res[$i]['c11'];
	$c12 = $res[$i]['c12'];
	$servico = isset($res[$i]['servico']) ? (int)$res[$i]['servico'] : 0;
	$nome_servico = 'Não Vinculado';
	if($servico > 0){
		try{
			$stServ = $pdo->prepare("SELECT nome FROM servicos WHERE id = :id LIMIT 1");
			$stServ->bindValue(':id', $servico, PDO::PARAM_INT);
			$stServ->execute();
			$nomeServTmp = $stServ->fetchColumn();
			if($nomeServTmp){ $nome_servico = $nomeServTmp; }
		}catch(Throwable $e){}
	}

	
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
		
		$valorF = number_format($valor, 2, ',', '.');

		$carac = $c1.' / '.$c2.' / '.$c3.' / '.$c4.' / '.$c5.' / '.$c6.' / '.$c7.' / '.$c8.' / '.$c9.' / '.$c10.' / '.$c11.' / '.$c12;

		 $caracF = mb_strimwidth($carac, 0, 70, "...");




echo <<<HTML
<input type="hidden" id="c1_{$id}" value="{$c1}">
<input type="hidden" id="c2_{$id}" value="{$c2}">
<input type="hidden" id="c3_{$id}" value="{$c3}">
<input type="hidden" id="c4_{$id}" value="{$c4}">
<input type="hidden" id="c5_{$id}" value="{$c5}">
<input type="hidden" id="c6_{$id}" value="{$c6}">
<input type="hidden" id="c7_{$id}" value="{$c7}">
<input type="hidden" id="c8_{$id}" value="{$c8}">
<input type="hidden" id="c9_{$id}" value="{$c9}">
<input type="hidden" id="c10_{$id}" value="{$c10}">
<input type="hidden" id="c11_{$id}" value="{$c11}">
<input type="hidden" id="c12_{$id}" value="{$c12}">
<input type="hidden" id="servico_{$id}" value="{$servico}">
<tr class="{$classe_linha}">
<td>{$nome_html}</td>
<td class="esc">{$valorF}</td>
<td class="esc">{$caracF}</td>
<td class="esc">{$nome_servico}</td>
<td>
		<big><a href="javascript:void(0)" onclick="editarItens('{$id}','{$nome_js}','{$valor}','{$servico}'); return false;" title="Editar Dados"><i class="fa fa-edit text-primary"></i></a></big>

		<li class="dropdown head-dpdn2" style="display: inline-block;">
		<a href="javascript:void(0)" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><big><i class="fa fa-trash-o text-danger"></i></big></a>

		<ul class="dropdown-menu" style="margin-left:-230px;">
		<li>
		<div class="notification_desc2">
		<p>Confirmar Exclusão? <a href="javascript:void(0)" onclick="excluirItens('{$id}'); return false;"><span class="text-danger">Sim</span></a></p>
		</div>
		</li>										
		</ul>
		</li>


		<big><a href="javascript:void(0)" onclick="ativarItens('{$id}', '{$acao}'); return false;" title="{$titulo_link}"><i class="fa {$icone} text-success"></i></a></big>

		</td>
</tr>
HTML;

}

echo <<<HTML
</tbody>
<small><div align="center" id="mensagem-excluir-itens"></div></small>
</table>
</small>
HTML;


}else{
	echo '<small>Não possui nenhum registro Cadastrado!</small>';
}

?>




<script type="text/javascript">
	function editarItens(id, nome, valor, servico){

		var c1 = $('#c1_'+id).val();
		var c2 = $('#c2_'+id).val();
		var c3 = $('#c3_'+id).val();
		var c4 = $('#c4_'+id).val();
		var c5 = $('#c5_'+id).val();
		var c6 = $('#c6_'+id).val();
		var c7 = $('#c7_'+id).val();
		var c8 = $('#c8_'+id).val();
		var c9 = $('#c9_'+id).val();
		var c10 = $('#c10_'+id).val();
		var c11 = $('#c11_'+id).val();
		var c12 = $('#c12_'+id).val();

		$('#id_do_item').val(id);
		$('#nome_item').val(nome);
		$('#valor').val(valor);
		$('#servico_vinculado').val(servico).change();
		$('#c1').val(c1);
		$('#c2').val(c2);
		$('#c3').val(c3);
		$('#c4').val(c4);
		$('#c5').val(c5);
		$('#c6').val(c6);
		$('#c7').val(c7);
		$('#c8').val(c8);
		$('#c9').val(c9);
		$('#c10').val(c10);
		$('#c11').val(c11);
		$('#c12').val(c12);
		var servicoHidden = $('#servico_' + id).val() || 0;
		$('#servico_vinculado').val(servicoHidden).change();		
		
		
	}



function excluirItens(id){
    $.ajax({
        url: 'paginas/' + pag + "/excluir-itens.php",
        method: 'POST',
        data: {id},
        dataType: "text",

        success: function (mensagem) {   
             
            if (mensagem.trim() == "Excluído com Sucesso") {                
                listar();    
                listarItens();                
            } else {
                $('#mensagem-excluir-itens').addClass('text-danger')
                $('#mensagem-excluir-itens').text(mensagem)
            }

        },      

    });
}


function ativarItens(id, acao){
    $.ajax({
        url: 'paginas/' + pag + "/mudar-status-itens.php",
        method: 'POST',
        data: {id, acao},
        dataType: "text",

        success: function (mensagem) {            
            if (mensagem.trim() == "Alterado com Sucesso") {                
                listar(); 
                listarItens();               
            } else {
                $('#mensagem-excluir-itens').addClass('text-danger')
                $('#mensagem-excluir-itens').text(mensagem)
            }

        },      

    });
}
</script>


