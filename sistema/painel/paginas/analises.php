<?php 
require_once __DIR__ . '/_context_bootstrap.php';
require_once __DIR__ . '/../verificar.php';
require_once __DIR__ . '/../../conexao.php';

$pag = 'analises';

/*
  CONTEXTO MASTER x TENANT
  - mantém o padrão do sistema
  - suporta /loja/<slug>/...
  - suporta acesso master sem loja
*/
$__uriPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

$tenant = '';
if (!empty($_GET['loja'])) {
	$tenant = preg_replace('/[^a-z0-9_-]/i', '', (string)$_GET['loja']);
} elseif (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__uriPath, $__m)) {
	$tenant = preg_replace('/[^a-z0-9_-]/i', '', (string)$__m[1]);
} elseif (session_status() === PHP_SESSION_ACTIVE) {
	if (!empty($_SESSION['loja'])) {
		$tenant = preg_replace('/[^a-z0-9_-]/i', '', (string)$_SESSION['loja']);
	} elseif (!empty($_SESSION['tenant_slug'])) {
		$tenant = preg_replace('/[^a-z0-9_-]/i', '', (string)$_SESSION['tenant_slug']);
	}
}

if ($tenant !== '' && empty($_GET['loja'])) {
	$_GET['loja'] = $tenant;
}

$qs_loja = ($tenant !== '') ? ('?loja=' . rawurlencode($tenant)) : '';

$bb_proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
	$bb_proto = ($_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : $bb_proto;
}
$bb_host = (string)($_SERVER['HTTP_HOST'] ?? '');
$bb_base = ($bb_host !== '') ? ($bb_proto . '://' . $bb_host) : '';
$url_painel_assets = $bb_base . '/sistema/painel';

// verificar se ele tem a permissão de estar nessa página
if (@$analises == 'ocultar') {
	$__bb_painel_public_url = ($tenant !== '')
		? ($bb_base . '/loja/' . $tenant . '/sistema/painel')
		: ($bb_base . '/sistema/painel');

	echo "<script>window.location='" . $__bb_painel_public_url . "'</script>";
	exit();
}
?>

<style type="text/css">
	.card-apoio-analise{
		background: #f8fbff;
		border: 1px solid #d9ecff;
		border-radius: 8px;
		padding: 14px 16px;
		margin-bottom: 15px;
		font-size: 14px;
		line-height: 22px;
		color: #35516b;
	}

	.card-apoio-analise strong{
		color: #1f4e79;
	}

	.bloco-preview-analise{
		background: #fbfbfb;
		border: 1px solid #e5e5e5;
		border-radius: 8px;
		padding: 12px;
		min-height: 245px;
	}

	.titulo-preview{
		font-size: 14px;
		font-weight: 600;
		color: #444;
		margin-bottom: 8px;
	}

	.preview-box{
		background: #fff;
		border: 1px solid #ddd;
		border-radius: 8px;
		padding: 10px;
		text-align: center;
		min-height: 190px;
		display: flex;
		align-items: center;
		justify-content: center;
	}

	.preview-box img{
		max-width: 100%;
		max-height: 170px;
		border-radius: 4px;
	}

	.box-resultado-analise{
		background: #fcfcfc;
		border: 1px solid #ececec;
		border-radius: 8px;
		padding: 12px;
		margin-top: 10px;
	}

	.box-resultado-analise .titulo-box{
		font-size: 14px;
		font-weight: 600;
		color: #555;
		margin-bottom: 10px;
	}

	.linha-fluxo-analise{
		background: #fff8e8;
		border: 1px solid #ffe3a6;
		border-radius: 8px;
		padding: 10px 14px;
		margin-top: 10px;
		font-size: 13px;
		color: #7a5a16;
		line-height: 21px;
	}

	.badge-status-analise{
		display: inline-block;
		padding: 4px 10px;
		border-radius: 20px;
		font-size: 12px;
		font-weight: 600;
		background: #eef5ff;
		color: #2f6fad;
		margin-top: 6px;
	}

	.modal-ficha-ia .bloco-foto-ia{
		background:#fbfbfb;
		border:1px solid #e8e8e8;
		border-radius:10px;
		padding:14px;
		text-align:center;
		height:100%;
	}

	.modal-ficha-ia .bloco-foto-ia img{
		max-width:100%;
		max-height:320px;
		border-radius:8px;
		border:1px solid #ddd;
		padding:3px;
		background:#fff;
	}

	.modal-ficha-ia .titulo-secao-ia{
		font-size:15px;
		font-weight:700;
		color:#334e68;
		margin-bottom:12px;
	}

	.modal-ficha-ia .painel-ia{
		background:#f8fbff;
		border:1px solid #d9ecff;
		border-radius:10px;
		padding:14px;
		margin-bottom:12px;
	}

	.modal-ficha-ia .painel-ia-topo{
		font-size:14px;
		font-weight:700;
		color:#1f4e79;
		margin-bottom:10px;
	}

	.modal-ficha-ia .grid-ia{
		display:grid;
		grid-template-columns: repeat(2, minmax(180px, 1fr));
		gap:10px;
	}

	.modal-ficha-ia .card-ia{
		background:#fff;
		border:1px solid #e9eef3;
		border-radius:8px;
		padding:12px;
	}

	.modal-ficha-ia .card-ia .rotulo{
		display:block;
		font-size:12px;
		font-weight:700;
		color:#7a8b99;
		text-transform:uppercase;
		letter-spacing:.3px;
		margin-bottom:5px;
	}

	.modal-ficha-ia .card-ia .valor{
		display:block;
		font-size:22px;
		font-weight:700;
		color:#2d3436;
		line-height:1.2;
	}

	.modal-ficha-ia .resumo-ia{
		background:#fff8e8;
		border:1px solid #ffe3a6;
		border-radius:10px;
		padding:14px;
		color:#7a5a16;
		font-size:14px;
		line-height:22px;
	}

	.modal-ficha-ia .bloco-obs{
		background:#fcfcfc;
		border:1px solid #ececec;
		border-radius:10px;
		padding:12px;
		margin-top:12px;
		font-size:14px;
		color:#555;
	}

	.modal-ficha-ia .badge-data-ia{
		display:inline-block;
		background:#eef5ff;
		color:#2f6fad;
		padding:5px 12px;
		border-radius:20px;
		font-size:12px;
		font-weight:700;
	}

	@media (max-width: 768px){
		.bloco-preview-analise{
			min-height: auto;
			margin-bottom: 15px;
		}

		.preview-box{
			min-height: 180px;
		}

		.card-apoio-analise{
			font-size: 13px;
			line-height: 21px;
		}

		.modal-ficha-ia .grid-ia{
			grid-template-columns: 1fr;
		}

		.modal-ficha-ia .card-ia .valor{
			font-size:18px;
		}
	}
</style>

<div class="row top-50">
	<div class="col-md-8 float-esq">	
		<a class="btn btn-primary btn-flat btn-pri" onclick="inserir()">
			<i class="fa fa-plus" aria-hidden="true"></i> <span class="esc">Nova Análise</span>
		</a>
	</div>
	<div class="col-md-3 float-esq">
		<input onkeyup="listarAnalises()" class="form-control" type="text" name="buscar" id="buscar" placeholder="Buscar por Cliente ou Sugestão" style="border-radius: 5px">
		<input type="hidden" id="pagina">
	</div>
	<div class="col-md-1 float-esq">
		<button onclick="listarAnalises()" id="btn-buscar" class="btn btn-primary"><i class="fa fa-search"></i></button>
	</div>
</div>

<div class="bs-example widget-shadow" style="padding:15px" id="listar"></div>

<div class="modal fade" id="modalForm" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-xl" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title"><span id="titulo_inserir"></span></h4>
				<button id="btn-fechar" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>

			<form id="form_analise" method="post" enctype="multipart/form-data">
				<div class="modal-body">

					<div class="card-apoio-analise">
						<strong>Análise Técnica Capilar</strong><br>
						Este módulo funciona como <strong>apoio técnico ao profissional</strong>. A imagem é analisada para auxiliar na identificação de cor capilar, percentual de fios brancos, densidade e grau de falha.
						<br><br>
						O resultado da análise <strong>não substitui a decisão do profissional</strong>. Ele serve como base para escolha da prótese, que deve ser confirmada no cadastro de prótese e depois apresentada ao cliente na simulação visual.
						<br>
						<span class="badge-status-analise">Fluxo: análise técnica → definição da prótese → simulação visual</span>
					</div>

					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label>Cliente</label>
								<select class="form-control sel2" name="cliente" id="cliente" style="width:100%" required>
									<option value="">Selecione um Cliente</option>
									<?php 
									$query = $pdo->query("SELECT * FROM clientes ORDER BY nome ASC");
									$res = $query->fetchAll(PDO::FETCH_ASSOC);
									$linhas = @count($res);
									if ($linhas > 0) {
										for ($i = 0; $i < $linhas; $i++) {
											$id_cliente = $res[$i]['id'];
											$nome_cliente = htmlspecialchars($res[$i]['nome'], ENT_QUOTES, 'UTF-8');
									?>
									<option value="<?php echo $id_cliente ?>"><?php echo $nome_cliente ?></option>
									<?php 
										}
									} 
									?>
								</select>
							</div> 	
						</div>

						<div class="col-md-6">
							<div class="form-group">
								<label>Data da Análise</label>
								<input type="date" class="form-control" id="data_analise" name="data_analise" value="<?php echo date('Y-m-d') ?>" required>    
							</div> 	
						</div>
					</div>

					<div class="row">
						<div class="col-md-6">
							<div class="bloco-preview-analise">
								<div class="titulo-preview">Imagem Original do Cliente</div>
								<div class="form-group">
									<label>Foto</label>
									<input type="file" class="form-control" id="foto" name="foto" onchange="carregarPreviewFoto()">
								</div>

								<div class="preview-box">
									<img src="images/analises/sem-foto.jpg" id="target">
								</div>
							</div>
						</div>

						<div class="col-md-6">
							<div class="bloco-preview-analise">
								<div class="titulo-preview">Painel de Resultado Técnico</div>

								<div class="box-resultado-analise">
									<div class="titulo-box">Dados identificados na análise</div>

									<div class="row">
										<div class="col-md-6">
											<div class="form-group">
												<label>Cor Detectada</label>
												<input type="text" class="form-control" id="cor_detectada" name="cor_detectada" placeholder="Ex: #1B30">    
											</div> 	
										</div>

										<div class="col-md-6">
											<div class="form-group">
												<label>Densidade</label>
												<input type="text" class="form-control" id="densidade_detectada" name="densidade_detectada" placeholder="Ex: 80%">    
											</div> 	
										</div>
									</div>

									<div class="row">
										<div class="col-md-6">
											<div class="form-group">
												<label>Grau de Falha</label>
												<input type="text" class="form-control" id="grau_falha" name="grau_falha" placeholder="Ex: 15 X 24">    
											</div> 	
										</div>

										<div class="col-md-6">
											<div class="form-group">
												<label>Sugestão de Prótese</label>
												<input type="text" class="form-control" id="sugestao_protese" name="sugestao_protese" placeholder="Ex: Micropele 0.08">    
											</div> 	
										</div>
									</div>

									<div class="linha-fluxo-analise">
										Após validar estes dados, o profissional deve confirmar a prótese no módulo apropriado e utilizar o simulador para mostrar o resultado final ao cliente.
									</div>
								</div>
							</div>
						</div>
					</div>

					<hr>

					<div class="row">
						<div class="col-md-12">
							<button id="btnProcessarAnalise" type="button" class="btn btn-warning btn-block" onclick="processarAnalise()">
								<i id="iconProcessarAnalise" class="fa fa-cogs"></i> Analisar Imagem
							</button>
						</div>
					</div>

					<br>
					<small><div id="mensagem_ia" align="center"></div></small>

					<div class="row">
						<div class="col-md-12">
							<div class="form-group">
								<label>Observações</label>
								<input type="text" class="form-control" id="observacoes" name="observacoes" placeholder="Observações">    
							</div> 	
						</div>
					</div>

					<input type="hidden" name="id" id="id">
					<input type="hidden" name="foto_atual" id="foto_atual">

					<br>
					<small><div id="mensagem" align="center"></div></small>
				</div>

				<div class="modal-footer">      
					<button type="submit" class="btn btn-primary">Salvar</button>
				</div>
			</form>
			
		</div>
	</div>
</div>

<div class="modal fade modal-ficha-ia" id="modalDados" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-xl" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title" id="exampleModalLabel"><span id="nome_dados"></span></h4>
				<button id="btn-fechar-perfil" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			
			<div class="modal-body">

				<div class="row">
					<div class="col-md-5">
						<div class="bloco-foto-ia">
							<div class="titulo-secao-ia">Imagem Analisada</div>
							<img id="foto_dados" src="">
						</div>
					</div>

					<div class="col-md-7">
						<div class="painel-ia">
							<div class="painel-ia-topo">Resultado Técnico Gerado pela IA</div>

							<div class="row" style="margin-bottom:10px">
								<div class="col-md-7">
									<div><strong>Cliente:</strong> <span id="cliente_dados"></span></div>
								</div>
								<div class="col-md-5" align="right">
									<span class="badge-data-ia">Análise: <span id="data_analise_dados"></span></span>
								</div>
							</div>

							<div class="grid-ia">
								<div class="card-ia">
									<span class="rotulo">Cor Detectada</span>
									<span class="valor" id="cor_dados"></span>
								</div>

								<div class="card-ia">
									<span class="rotulo">Densidade</span>
									<span class="valor" id="densidade_dados"></span>
								</div>

								<div class="card-ia">
									<span class="rotulo">Grau de Falha</span>
									<span class="valor" id="grau_dados"></span>
								</div>

								<div class="card-ia">
									<span class="rotulo">Sugestão de Prótese</span>
									<span class="valor" id="sugestao_dados"></span>
								</div>
							</div>
						</div>

						<div class="resumo-ia">
							<strong>Leitura rápida para atendimento:</strong><br>
							A IA identificou a cor <strong id="cor_resumo"></strong>, com densidade estimada em <strong id="densidade_resumo"></strong>, grau de falha <strong id="grau_resumo"></strong> e sugestão técnica de prótese <strong id="sugestao_resumo"></strong>.
						</div>

						<div class="bloco-obs">
							<strong>Observações:</strong><br>
							<span id="observacoes_dados"></span>
						</div>
					</div>
				</div>

			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
var pag = "<?= $pag ?>";
var qs_loja = "<?= $qs_loja ?>";
</script>

<script src="<?= $url_painel_assets ?>/js/ajax.js"></script>

<script type="text/javascript">
$(document).ready(function () {
	listarAnalises();

	$('.sel2').select2({
		dropdownParent: $('#modalForm')
	});

	$('#cliente').on('change', function(){
		buscarDadosProtese();
	});
});
</script>

<script type="text/javascript">
function listarAnalises(pagina){
	$("#pagina").val(pagina);

	var busca = $("#buscar").val();

	$.ajax({
		url: 'paginas/' + pag + '/listar.php' + qs_loja,
		method: 'POST',
		data: {busca: busca, pagina: pagina},
		dataType: "html",
		success:function(result){
			$("#listar").html(result);
		}
	});
}
</script>

<script type="text/javascript">
function limparCampos(){
	$('#id').val('');
	$('#cliente').val('').change();
	$('#data_analise').val('<?php echo date('Y-m-d') ?>');
	$('#foto').val('');
	$('#foto_atual').val('');
	$('#cor_detectada').val('');
	$('#densidade_detectada').val('');
	$('#grau_falha').val('');
	$('#sugestao_protese').val('');
	$('#observacoes').val('');
	$('#mensagem').text('');
	$('#mensagem').removeClass();
	$('#mensagem_ia').text('');
	$('#mensagem_ia').removeClass();
	$('#target').attr('src', 'images/analises/sem-foto.jpg');

	$('#btnProcessarAnalise').data('processando', false);
	$('#btnProcessarAnalise').prop('disabled', false);
	$('#iconProcessarAnalise').removeClass();
	$('#iconProcessarAnalise').addClass('fa fa-cogs');
}
</script>

<script type="text/javascript">
function inserir(){
	limparCampos();
	$('#titulo_inserir').text('Análise Técnica Capilar');
	$('#mensagem_ia').text('Salve o registro para habilitar a análise técnica da imagem.');
	$('#mensagem_ia').removeClass();
	$('#mensagem_ia').addClass('text-danger');
	$('#modalForm').modal('show');
}
</script>

<script type="text/javascript">
function buscarDadosProtese(){
	var cliente = $('#cliente').val();

	if(cliente == ""){
		$('#cor_detectada').val('');
		$('#densidade_detectada').val('');
		$('#grau_falha').val('');
		$('#sugestao_protese').val('');
		$('#observacoes').val('');
		return;
	}

	$.ajax({
		url: 'paginas/' + pag + '/buscar_protese.php' + qs_loja,
		method: 'POST',
		data: {cliente: cliente},
		dataType: "json",
		success: function(dados){
			if(dados.status == 'Sucesso'){
				$('#cor_detectada').val(dados.cor);
				$('#densidade_detectada').val(dados.densidade);
				$('#grau_falha').val(dados.tamanho);
				$('#sugestao_protese').val(dados.modelo);
				$('#observacoes').val(dados.observacoes);
			}else{
				$('#cor_detectada').val('');
				$('#densidade_detectada').val('');
				$('#grau_falha').val('');
				$('#sugestao_protese').val('');
				$('#observacoes').val('');
			}
		}
	});
}
</script>

<script type="text/javascript">
$("#form_analise").submit(function (e) {
	e.preventDefault();

	var formData = new FormData(this);

	$.ajax({
		url: 'paginas/' + pag + '/salvar.php' + qs_loja,
		type: 'POST',
		data: formData,
		success: function (mensagem) {
			$('#mensagem').text('');
			$('#mensagem').removeClass();

			if (mensagem.trim() == "Salvo com Sucesso") {
				$('#btn-fechar').click();

				var pagina = $("#pagina").val();
				listarAnalises(pagina);
			} else {
				$('#mensagem').addClass('text-danger');
				$('#mensagem').text(mensagem);
			}
		},
		cache: false,
		contentType: false,
		processData: false
	});
});
</script>

<script type="text/javascript">
function excluir(id){
	$.ajax({
		url: 'paginas/' + pag + '/excluir.php' + qs_loja,
		method: 'POST',
		data: {id: id},
		dataType: "text",
		success: function (mensagem) {            
			if (mensagem.trim() == "Excluído com Sucesso") {                
				var pagina = $("#pagina").val();
				listarAnalises(pagina);
			} else {
				$('#mensagem-excluir').addClass('text-danger');
				$('#mensagem-excluir').text(mensagem);
			}
		}
	});
}
</script>

<script type="text/javascript">
function carregarPreviewFoto(){
	const file = document.getElementById('foto').files[0];

	if(file){
		const reader = new FileReader();
		reader.onload = function(e){
			$('#target').attr('src', e.target.result);
		};
		reader.readAsDataURL(file);
	}else{
		$('#target').attr('src', 'images/analises/sem-foto.jpg');
	}
}
</script>

<script type="text/javascript">
function processarAnalise(){
	var id = $('#id').val();
	var botao = $('#btnProcessarAnalise');
	var icone = $('#iconProcessarAnalise');

	$('#mensagem_ia').text('');
	$('#mensagem_ia').removeClass();

	if(id == ""){
		$('#mensagem_ia').addClass('text-danger');
		$('#mensagem_ia').text('Salve o registro para habilitar a análise técnica da imagem.');
		return;
	}

	if(botao.data('processando') == true){
		return;
	}

	botao.data('processando', true);
	botao.prop('disabled', true);

	icone.removeClass();
	icone.addClass('fa fa-spinner fa-spin');

	$('#mensagem_ia').addClass('text-info');
	$('#mensagem_ia').text('Processando análise técnica.');

	$.ajax({
		url: 'paginas/' + pag + '/processar.php' + qs_loja,
		method: 'POST',
		data: {id: id},
		dataType: "json",
		success: function (dados) {
			botao.data('processando', false);
			botao.prop('disabled', false);

			icone.removeClass();
			icone.addClass('fa fa-cogs');

			$('#mensagem_ia').removeClass();

			if(dados.status == 'Sucesso'){
				$('#cor_detectada').val(dados.cor_detectada);
				$('#densidade_detectada').val(dados.densidade_detectada);
				$('#grau_falha').val(dados.grau_falha);
				$('#sugestao_protese').val(dados.sugestao_protese);

				$('#mensagem_ia').addClass('text-success');
				$('#mensagem_ia').text('Análise técnica processada com sucesso');
			}else{
				$('#mensagem_ia').addClass('text-danger');
				$('#mensagem_ia').text(dados.mensagem);
			}
		},
		error: function () {
			botao.data('processando', false);
			botao.prop('disabled', false);

			icone.removeClass();
			icone.addClass('fa fa-cogs');

			$('#mensagem_ia').removeClass();
			$('#mensagem_ia').addClass('text-danger');
			$('#mensagem_ia').text('Erro ao processar análise');
		}
	});
}
</script>

<script type="text/javascript">
function mostrar(nome_cliente, data_analise, foto, foto_ajustada, cor_detectada, densidade_detectada, grau_falha, sugestao_protese, observacoes){
	$('#nome_dados').text('Ficha Técnica da Análise');
	$('#cliente_dados').text(nome_cliente);
	$('#data_analise_dados').text(data_analise);
	$('#cor_dados').text(cor_detectada);
	$('#densidade_dados').text(densidade_detectada);
	$('#grau_dados').text(grau_falha);
	$('#sugestao_dados').text(sugestao_protese);

	$('#cor_resumo').text(cor_detectada);
	$('#densidade_resumo').text(densidade_detectada);
	$('#grau_resumo').text(grau_falha);
	$('#sugestao_resumo').text(sugestao_protese);

	if(observacoes == ""){
		observacoes = 'Sem observações informadas.';
	}

	$('#observacoes_dados').text(observacoes);

	if(foto_ajustada != ""){
		$('#foto_dados').attr('src', 'images/analises/' + foto_ajustada + '?v=' + new Date().getTime());
	}else if(foto != ""){
		$('#foto_dados').attr('src', 'images/analises/' + foto + '?v=' + new Date().getTime());
	}else{
		$('#foto_dados').attr('src', 'images/analises/sem-foto.jpg');
	}

	$('#modalDados').modal('show');
}
</script>