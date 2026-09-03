<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once(__DIR__ . "/../../../conexao.php");
$tabela = 'simulacoes_protese';

$busca = '%' . @$_POST['busca'] . '%';

if(@$_POST['pagina'] == ""){
    @$_POST['pagina'] = 0;
}

$pagina = intval(@$_POST['pagina']);
$limite = $pagina * $itens_pag;

$query = $pdo->prepare("SELECT s.*, c.nome as nome_cliente, p.modelo as nome_protese 
    FROM $tabela s 
    INNER JOIN clientes c ON s.cliente = c.id
    INNER JOIN proteses p ON s.id_protese = p.id
    WHERE c.nome LIKE :busca or p.modelo LIKE :busca
    ORDER BY s.id desc LIMIT $limite, $itens_pag");
$query->bindValue(":busca", $busca);
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);

$query2 = $pdo->prepare("SELECT COUNT(*) as total FROM $tabela s 
	INNER JOIN clientes c ON s.cliente = c.id
	INNER JOIN proteses p ON s.id_protese = p.id
	WHERE c.nome LIKE :busca or p.modelo LIKE :busca");
$query2->bindValue(":busca", $busca);
$query2->execute();
$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
$total_reg2 = $res2[0]['total'];
$num_paginas = ceil($total_reg2 / $itens_pag);
$ultimo_reg = $num_paginas - 1;

if($total_reg > 0){

echo <<<HTML
<small>
<table class="table table-hover">
<thead> 
<tr> 
<th>Cliente</th>	
<th class="esc">Prótese</th> 
<th class="esc">Data</th> 
<th style="width:170px">Ações</th>
</tr> 
</thead> 
<tbody>	
HTML;

for($i=0; $i < $total_reg; $i++){
	$id = $res[$i]['id'];
	$cliente = $res[$i]['cliente'];
	$id_protese = $res[$i]['id_protese'];
	$nome_cliente = $res[$i]['nome_cliente'];
	$nome_protese = $res[$i]['nome_protese'];
	$foto_original = $res[$i]['foto_original'];
	$imagem_simulada = $res[$i]['imagem_simulada'];
	$observacoes = $res[$i]['observacoes'];
	$data_simulacao = $res[$i]['data_simulacao'];

	$data_simulacaoF = $data_simulacao ? implode('/', array_reverse(explode('-', $data_simulacao))) : '';

	$cor_icone_imagem = 'text-secondary';
	$titulo_icone_imagem = 'Imagem Simulada não gerada';

	if($imagem_simulada != ""){
		$cor_icone_imagem = 'text-info';
		$titulo_icone_imagem = 'Ver Imagem Simulada';
	}

echo <<<HTML
<tr>
<td>{$nome_cliente}</td>
<td class="esc">{$nome_protese}</td>
<td class="esc">{$data_simulacaoF}</td>
<td style="white-space:nowrap;">
	<div style="display:flex; align-items:center; gap:10px; flex-wrap:nowrap;">

		<a href="javascript:void(0)" onclick="editar('{$id}','{$cliente}','{$id_protese}','{$observacoes}','{$data_simulacao}','{$foto_original}','{$imagem_simulada}')" title="Editar Dados" style="display:inline-block;">
			<i class="fa fa-edit text-primary"></i>
		</a>

		<a href="javascript:void(0)" onclick="mostrar('{$nome_cliente}','{$nome_protese}','{$observacoes}','{$data_simulacaoF}','{$foto_original}','{$imagem_simulada}')" title="Ver Dados" style="display:inline-block;">
			<i class="fa fa-info-circle text-secondary"></i>
		</a>

		<a href="javascript:void(0)" id="btn-ia-{$id}" onclick="gerarIA('{$id}')" title="Gerar com IA" style="display:inline-block;">
			<i id="icon-ia-{$id}" class="fa fa-magic text-warning"></i>
		</a>

		<a href="javascript:void(0)" onclick="abrirCanvasRegistro('{$id}')" title="{$titulo_icone_imagem}" style="display:inline-block;">
			<i class="fa fa-image {$cor_icone_imagem}"></i>
		</a>

		<li class="dropdown head-dpdn2" style="display:inline-block; list-style:none; margin:0;">
			<a href="javascript:void(0)" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false" title="Excluir">
				<i class="fa fa-trash-o text-danger"></i>
			</a>

			<ul class="dropdown-menu" style="margin-left:-230px;">
				<li>
					<div class="notification_desc2">
						<p>Confirmar Exclusão? <a href="javascript:void(0)" onclick="excluir('{$id}')"><span class="text-danger">Sim</span></a></p>
					</div>
				</li>										
			</ul>
		</li>

	</div>
</td>
</tr>
HTML;

}

echo <<<HTML
</tbody>
<small><div align="center" id="mensagem-excluir"></div></small>
</table>
</small>

<hr>
<div class="row" align="center">
	<nav aria-label="Page navigation example">
		<ul class="pagination">
			<li class="page-item">
				<a onclick="listarSimulacoes(0)" class="paginador" href="javascript:void(0)" aria-label="Previous">
					<span aria-hidden="true">&laquo;</span>
					<span class="sr-only">Previous</span>
				</a>
			</li>
HTML;

for($i=0;$i<$num_paginas;$i++){
	$estilo = "";
	if($pagina >= ($i - 2) and $pagina <= ($i + 2)){
		if($pagina == $i){
			$estilo = "active";
		}

		$pag = $i + 1;

echo <<<HTML
<li class="page-item {$estilo}">
	<a onclick="listarSimulacoes({$i})" class="paginador" href="javascript:void(0)">{$pag}</a>
</li>
HTML;

	} 
}

echo <<<HTML
			<li class="page-item">
				<a onclick="listarSimulacoes({$ultimo_reg})" class="paginador" href="javascript:void(0)" aria-label="Next">
					<span aria-hidden="true">&raquo;</span>
					<span class="sr-only">Next</span>
				</a>
			</li>
		</ul>
	</nav>
</div>
HTML;

}else{
	echo '<small>Não possui nenhum registro Cadastrado!</small>';
}
?>

<script type="text/javascript">
function editar(id, cliente, id_protese, observacoes, data_simulacao, foto_original, imagem_simulada){
	$('#id').val(id);
	$('#cliente').val(cliente).change();
	$('#id_protese').val(id_protese).change();
	$('#observacoes').val(observacoes);
	$('#data_simulacao').val(data_simulacao);
	$('#foto_original_atual').val(foto_original);
	$('#imagem_simulada_atual').val(imagem_simulada);

	$('#foto_original').val('');
	$('#imagem_simulada').val('');

	$('#mensagem').text('');
	$('#mensagem_ia').text('');
	$('#mensagem').removeClass();
	$('#mensagem_ia').removeClass();

	$('#titulo_inserir').text('Editar Registro');

	if(typeof mostrarNomeArquivosAtuais === 'function'){
		mostrarNomeArquivosAtuais();
	}

	if(typeof mostrarPreviewAtual === 'function'){
		mostrarPreviewAtual();
	}

	$('#modalForm').modal('show');
}

function limparCampos(){
	$('#id').val('');
	$('#cliente').val('').change();
	$('#id_protese').val('').change();
	$('#observacoes').val('');
	$('#data_simulacao').val('');
	$('#foto_original_atual').val('');
	$('#imagem_simulada_atual').val('');
	$('#foto_original').val('');
	$('#imagem_simulada').val('');
	$('#mensagem').text('');
	$('#mensagem_ia').text('');
	$('#mensagem').removeClass();
	$('#mensagem_ia').removeClass();

	if($('#nome_foto_original_atual').length){
		$('#nome_foto_original_atual').text('');
	}

	if($('#nome_imagem_simulada_atual').length){
		$('#nome_imagem_simulada_atual').text('');
	}

	if(typeof mostrarPreviewAtual === 'function'){
		mostrarPreviewAtual();
	}
}
</script>

<script type="text/javascript">
function mostrar(nome_cliente, nome_protese, observacoes, data_simulacao, foto_original, imagem_simulada){
	$('#nome_dados').text(nome_cliente);
	$('#cliente_dados').text(nome_cliente);
	$('#protese_dados').text(nome_protese);
	$('#observacoes_dados').text(observacoes);
	$('#data_simulacao_dados').text(data_simulacao);

	if(foto_original != ""){
		$('#foto_original_dados').attr('src', 'images/simulacoes/' + foto_original + '?v=' + new Date().getTime());
	}else{
		$('#foto_original_dados').attr('src', '');
	}

	if(imagem_simulada != ""){
		$('#imagem_simulada_dados').attr('src', 'images/simulacoes/' + imagem_simulada + '?v=' + new Date().getTime());
	}else{
		$('#imagem_simulada_dados').attr('src', '');
	}

	$('#modalDados').modal('show');
}
</script>

<script type="text/javascript">
function gerarIA(id){
	var botao = $('#btn-ia-' + id);
	var icone = $('#icon-ia-' + id);

	if(botao.data('processando') == true){
		return;
	}

	botao.data('processando', true);
	botao.css('pointer-events', 'none');
	botao.attr('title', 'Gerando simulação...');
	icone.removeClass();
	icone.addClass('fa fa-spinner fa-spin text-secondary');

	$.ajax({
		url: (typeof montarUrlPainel === 'function' ? montarUrlPainel('paginas/simulador/gerar_ia.php') : 'paginas/simulador/gerar_ia.php'),
		method: 'POST',
		data: {id},
		dataType: "text",

		success: function (mensagem) {
			if (mensagem.trim() == "Salvo com Sucesso") {
				var pagina = $("#pagina").val();
				listarSimulacoes(pagina);
			} else {
				alert(mensagem);

				botao.data('processando', false);
				botao.css('pointer-events', 'auto');
				botao.attr('title', 'Gerar com IA');
				icone.removeClass();
				icone.addClass('fa fa-magic text-warning');
			}
		},

		error: function () {
			alert('Erro ao processar a simulação com IA');

			botao.data('processando', false);
			botao.css('pointer-events', 'auto');
			botao.attr('title', 'Gerar com IA');
			icone.removeClass();
			icone.addClass('fa fa-magic text-warning');
		}
	});
}
</script>