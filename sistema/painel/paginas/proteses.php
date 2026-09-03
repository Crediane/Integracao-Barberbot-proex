<?php 
require_once __DIR__ . '/_context_bootstrap.php';
require_once __DIR__ . '/../verificar.php';
require_once __DIR__ . '/../../conexao.php';

$pag = 'proteses';

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
$bb_proteses_web_prefix = rtrim((string)$__bb_webBase, '/');
$bb_proteses_painel_assets = $bb_base . $bb_proteses_web_prefix . '/sistema/painel';

// verificar se ele tem a permissão de estar nessa página
if (@$proteses == 'ocultar') {
	$__bb_painel_public_url = ($tenant !== '')
			? ($bb_base . $bb_proteses_web_prefix . '/loja/' . $tenant . '/sistema/painel')
			: ($bb_base . $bb_proteses_web_prefix . '/sistema/painel');

	echo "<script>window.location='" . $__bb_painel_public_url . "'</script>";
	exit();
}
?>

<div class="row top-50">
	<div class="col-md-8 float-esq">	
		<a class="btn btn-primary btn-flat btn-pri" onclick="inserir()">
			<i class="fa fa-plus" aria-hidden="true"></i> <span class="esc">Nova Prótese</span>
		</a>
	</div>
	<div class="col-md-3 float-esq">
		<input onkeyup="listarProteses()" class="form-control" type="text" name="buscar" id="buscar" placeholder="Buscar por Cliente, Modelo ou Cor" style="border-radius: 5px">
		<input type="hidden" id="pagina">
	</div>
	<div class="col-md-1 float-esq">
		<button onclick="listarProteses()" id="btn-buscar" class="btn btn-primary"><i class="fa fa-search"></i></button>
	</div>
</div>

<div class="bs-example widget-shadow" style="padding:15px" id="listar"></div>

<div class="modal fade" id="modalForm" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title"><span id="titulo_inserir"></span></h4>
				<button id="btn-fechar" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>

			<form id="form_protese">
				<div class="modal-body">

					<div class="row">
						<div class="col-md-12">
							<div class="form-group">
								<label>Cliente</label>
								<select class="form-control sel2" name="cliente" id="cliente" style="width:100%" required>
									<option value="">Selecione um Cliente</option>
									<?php 
									$query = $pdo->query("SELECT * FROM clientes ORDER BY nome ASC");
									$res = $query->fetchAll(PDO::FETCH_ASSOC);
									$linhas = @count($res);
									if($linhas > 0){
										for($i=0; $i<$linhas; $i++){
											$id_cliente = $res[$i]['id'];
											$nome_cliente = htmlspecialchars($res[$i]['nome'], ENT_QUOTES, 'UTF-8');
									?>
									<option value="<?php echo $id_cliente ?>"><?php echo $nome_cliente ?></option>
									<?php } } ?>
								</select>
							</div> 	
						</div>
					</div>

					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label>Modelo</label>
								<input type="text" class="form-control" id="modelo" name="modelo" placeholder="Modelo" required>    
							</div> 	
						</div>

						<div class="col-md-6">
							<div class="form-group">
								<label>Cor</label>
								<input type="text" class="form-control" id="cor" name="cor" placeholder="Cor">    
							</div> 	
						</div>
					</div>

					<div class="row">
						<div class="col-md-4">
							<div class="form-group">
								<label>Densidade</label>
								<input type="text" class="form-control" id="densidade" name="densidade" placeholder="Densidade">    
							</div> 	
						</div>

						<div class="col-md-4">
							<div class="form-group">
								<label>Tamanho</label>
								<input type="text" class="form-control" id="tamanho" name="tamanho" placeholder="Tamanho">    
							</div> 	
						</div>

						<div class="col-md-4">
							<div class="form-group">
								<label>Fornecedor</label>
								<select class="form-control sel2" name="fornecedor" id="fornecedor" style="width:100%" required>
									<option value="">Selecione um Fornecedor</option>
									<?php 
									$query = $pdo->query("SELECT id, nome FROM fornecedores ORDER BY nome ASC");
									$res = $query->fetchAll(PDO::FETCH_ASSOC);
									$linhas = @count($res);
									if($linhas > 0){
										for($i=0; $i<$linhas; $i++){
											$id_fornecedor = $res[$i]['id'];
											$nome_fornecedor = htmlspecialchars($res[$i]['nome'], ENT_QUOTES, 'UTF-8');
									?>
									<option value="<?php echo $id_fornecedor ?>"><?php echo $nome_fornecedor ?></option>
									<?php } } ?>
								</select>
							</div> 	
						</div>
					</div>

					<div class="row">
						<div class="col-md-12">
							<div class="form-group">
								<label>Observações</label>
								<input type="text" class="form-control" id="observacoes" name="observacoes" placeholder="Observações">    
							</div> 	
						</div>
					</div>

					<input type="hidden" name="id" id="id">

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

<div class="modal fade" id="modalDados" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title" id="exampleModalLabel"><span id="nome_dados"></span></h4>
				<button id="btn-fechar-perfil" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			
			<div class="modal-body">

				<div class="row" style="border-bottom: 1px solid #cac7c7;">
					<div class="col-md-6">							
						<span><b>Modelo: </b></span>
						<span id="modelo_dados"></span>
					</div>	

					<div class="col-md-6">							
						<span><b>Cor: </b></span>
						<span id="cor_dados"></span>							
					</div>
				</div>

				<div class="row" style="border-bottom: 1px solid #cac7c7;">
					<div class="col-md-4">							
						<span><b>Densidade: </b></span>
						<span id="densidade_dados"></span>							
					</div>
					<div class="col-md-4">							
						<span><b>Tamanho: </b></span>
						<span id="tamanho_dados"></span>
					</div>
					<div class="col-md-4">							
						<span><b>Fornecedor: </b></span>
						<span id="fornecedor_dados"></span>
					</div>
				</div>

				<div class="row" style="border-bottom: 1px solid #cac7c7;">
					<div class="col-md-6">							
						<span><b>Cadastro: </b></span>
						<span id="data_cad_dados"></span>							
					</div>
					<div class="col-md-6">							
						<span><b>Cliente: </b></span>
						<span id="cliente_dados"></span>
					</div>					
				</div>

				<div class="row" style="border-bottom: 1px solid #cac7c7;">
					<div class="col-md-12">							
						<span><b>Observações: </b></span>
						<span id="observacoes_dados"></span>
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

<script src="<?= htmlspecialchars($bb_proteses_painel_assets, ENT_QUOTES, 'UTF-8') ?>/js/ajax.js"></script>

<script type="text/javascript">
$(document).ready(function () {
	listarProteses();

	$('.sel2').select2({
		dropdownParent: $('#modalForm')
	});
});
</script>

<script type="text/javascript">
function listarProteses(pagina){
	$("#pagina").val(pagina);

	var busca = $("#buscar").val();
	$.ajax({
		url: bb_url('paginas/' + pag + "/listar.php") + qs_loja,
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
$("#form_protese").submit(function (e) {
	e.preventDefault();

	var formData = new FormData(this);

	$.ajax({
		url: bb_url('paginas/' + pag + "/salvar.php") + qs_loja,
		type: 'POST',
		data: formData,
		success: function (mensagem) {
			$('#mensagem').text('');
			$('#mensagem').removeClass();

			if (mensagem.trim() == "Salvo com Sucesso") {
				$('#btn-fechar').click();

				var pagina = $("#pagina").val();
				listarProteses(pagina);
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
		url: bb_url('paginas/' + pag + "/excluir.php") + qs_loja,
		method: 'POST',
		data: {id: id},
		dataType: "text",
		success: function (mensagem) {
			if (mensagem.trim() == "Excluído com Sucesso") {
				var pagina = $("#pagina").val();
				listarProteses(pagina);
			} else {
				$('#mensagem-excluir').addClass('text-danger');
				$('#mensagem-excluir').text(mensagem);
			}
		}
	});
}
</script>

<script type="text/javascript">
function limparCampos(){
	$('#id').val('');
	$('#cliente').val('').change();
	$('#modelo').val('');
	$('#cor').val('');
	$('#densidade').val('');
	$('#tamanho').val('');
	$('#fornecedor').val('').change();
	$('#observacoes').val('');
	$('#mensagem').text('');
	$('#mensagem').removeClass();
}

function inserir(){
	limparCampos();
	$('#titulo_inserir').text('Nova Prótese');
	$('#modalForm').modal('show');
}
</script>
