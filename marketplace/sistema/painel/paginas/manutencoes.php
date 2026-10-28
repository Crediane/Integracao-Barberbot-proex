<?php 
require_once __DIR__ . '/_context_bootstrap.php';
require_once __DIR__ . '/../verificar.php';
require_once __DIR__ . '/../../conexao.php';

$pag = 'manutencoes';

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

//verificar se ele tem a permissão de estar nessa página
if (@$manutencoes == 'ocultar') {
	$__bb_painel_public_url = ($tenant !== '')
		? ($bb_base . '/loja/' . $tenant . '/sistema/painel')
		: ($bb_base . '/sistema/painel');

	echo "<script>window.location='" . $__bb_painel_public_url . "'</script>";
	exit();
}
?>

<div class="row top-50">
	<div class="col-md-8 float-esq">	
		<a class="btn btn-primary" onclick="inserir()" class="btn btn-primary btn-flat btn-pri"><i class="fa fa-plus" aria-hidden="true"></i> <span class="esc">Nova Manutenção</span></a>
	</div>
	<div class="col-md-3 float-esq">
		<input onkeyup="listarManutencoes()" class="form-control" type="text" name="buscar" id="buscar" placeholder="Buscar por Cliente, Prótese ou Tipo" style="border-radius: 5px">
		<input type="hidden" id="pagina">
	</div>
	<div class="col-md-1 float-esq">
		<button onclick="listarManutencoes()" id="btn-buscar" class="btn btn-primary"><i class="fa fa-search"></i></button>
	</div>
</div>

<div class="bs-example widget-shadow" style="padding:15px" id="listar">
	
</div>

<!-- Modal Inserir-->
<div class="modal fade" id="modalForm" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title"><span id="titulo_inserir"></span></h4>
				<button id="btn-fechar" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>

			<form id="form_manutencao">
				<div class="modal-body">

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

						<div class="col-md-6">
							<div class="form-group">
								<label>Prótese</label>
								<select class="form-control sel2" name="id_protese" id="id_protese" style="width:100%" required>
									<option value="">Selecione uma Prótese</option>
									<?php 
									$query = $pdo->query("SELECT p.*, c.nome as nome_cliente FROM proteses p INNER JOIN clientes c ON p.cliente = c.id ORDER BY p.id DESC");
									$res = $query->fetchAll(PDO::FETCH_ASSOC);
									$linhas = @count($res);
									if($linhas > 0){
										for($i=0; $i<$linhas; $i++){
											$descricao = $res[$i]['nome_cliente'].' - '.$res[$i]['modelo'];
											$id_protese = $res[$i]['id'];
											$descricao_html = htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8');
									?>
									<option value="<?php echo $id_protese ?>"><?php echo $descricao_html ?></option>
									<?php } } ?>
								</select>
							</div> 	
						</div>
					</div>

					<div class="row">
						<div class="col-md-4">
							<div class="form-group">
								<label>Data da Manutenção</label>
								<input type="date" class="form-control" id="data_manutencao" name="data_manutencao" required>    
							</div> 	
						</div>

						<div class="col-md-4">
							<div class="form-group">
								<label>Tipo</label>
								<input type="text" class="form-control" id="tipo" name="tipo" placeholder="Tipo" required>    
							</div> 	
						</div>

						<div class="col-md-4">
							<div class="form-group">
								<label>Próxima Manutenção</label>
								<input type="date" class="form-control" id="proxima_manutencao" name="proxima_manutencao">    
							</div> 	
						</div>
					</div>

					<div class="row">
						<div class="col-md-3">
							<div class="form-group">
								<label>Tipo de Pele</label>
								<select class="form-control" name="tipo_pele" id="tipo_pele">
									<option value="">Selecione</option>
									<option value="Oleosa">Oleosa</option>
									<option value="Mista">Mista</option>
									<option value="Seca">Seca</option>
								</select>
							</div> 	
						</div>

						<div class="col-md-3">
							<div class="form-group">
								<label>Nível de Sudorese</label>
								<select class="form-control" name="nivel_sudorese" id="nivel_sudorese">
									<option value="">Selecione</option>
									<option value="1">1 - Transpira pouco</option>
									<option value="2">2 - Transpiração leve</option>
									<option value="3">3 - Transpiração moderada</option>
									<option value="4">4 - Transpira bastante</option>
									<option value="5">5 - Transpira muito</option>
								</select>
							</div> 	
						</div>

						<div class="col-md-3">
							<div class="form-group">
								<label>Clima Regional</label>
								<select class="form-control" name="clima" id="clima">
									<option value="">Selecione</option>
									<option value="Frio">Frio</option>
									<option value="Ameno">Ameno</option>
									<option value="Quente">Quente</option>
								</select>
							</div> 	
						</div>

						<div class="col-md-3">
							<div class="form-group">
								<label>Tipo de Adesivo</label>
								<select class="form-control" name="tipo_adesivo" id="tipo_adesivo">
									<option value="">Selecione</option>
									<option value="Ultra Hold">Ultra Hold</option>
									<option value="Gold / Amarela">Gold / Amarela</option>
									<option value="No-Shine">No-Shine</option>
									<option value="Fita Branca">Fita Branca</option>
									<option value="Cola Acrílica">Cola Acrílica</option>
								</select>
							</div> 	
						</div>
					</div>

					<div class="row">
	<div class="col-md-6">
		<div class="form-group">
			<label>Produtos Utilizados</label>
			<select class="form-control sel2" name="produtos_utilizados" id="produtos_utilizados" style="width:100%">
				<option value="">Selecione um Produto</option>
				<?php 
				$query = $pdo->query("SELECT * FROM produtos ORDER BY nome ASC");
				$res = $query->fetchAll(PDO::FETCH_ASSOC);
				$linhas = @count($res);
				if($linhas > 0){
					for($i=0; $i<$linhas; $i++){
						$nome_produto = htmlspecialchars($res[$i]['nome'], ENT_QUOTES, 'UTF-8');
				?>
				<option value="<?php echo $nome_produto ?>"><?php echo $nome_produto ?></option>
				<?php } } ?>
			</select>
		</div> 	
	</div>

	<div class="col-md-6">
		<div class="form-group">
			<label>Fornecedor</label>
			<select class="form-control sel2" name="fornecedor" id="fornecedor" style="width:100%">
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


<!-- Modal Dados-->
<div class="modal fade" id="modalDados" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title" id="exampleModalLabel"><span id="nome_dados"></span></h4>
				<button id="btn-fechar-perfil" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
					<span aria-hidden="true" >&times;</span>
				</button>
			</div>
			
			<div class="modal-body">

				<div class="row" style="border-bottom: 1px solid #cac7c7;">
					<div class="col-md-6">							
						<span><b>Cliente: </b></span>
						<span id="cliente_dados"></span>
					</div>	

					<div class="col-md-6">							
						<span><b>Prótese: </b></span>
						<span id="protese_dados"></span>							
					</div>
				</div>

				<div class="row" style="border-bottom: 1px solid #cac7c7;">
					<div class="col-md-4">							
						<span><b>Data: </b></span>
						<span id="data_manutencao_dados"></span>							
					</div>
					<div class="col-md-4">							
						<span><b>Tipo: </b></span>
						<span id="tipo_dados"></span>
					</div>
					<div class="col-md-4">							
						<span><b>Próxima: </b></span>
						<span id="proxima_manutencao_dados"></span>
					</div>
				</div>

				<div class="row" style="border-bottom: 1px solid #cac7c7;">
					<div class="col-md-3">							
						<span><b>Tipo de Pele: </b></span>
						<span id="tipo_pele_dados"></span>
					</div>
					<div class="col-md-3">							
						<span><b>Sudorese: </b></span>
						<span id="nivel_sudorese_dados"></span>
					</div>
					<div class="col-md-3">							
						<span><b>Clima: </b></span>
						<span id="clima_dados"></span>
					</div>
					<div class="col-md-3">							
						<span><b>Adesivo: </b></span>
						<span id="tipo_adesivo_dados"></span>
					</div>
				</div>

				<div class="row" style="border-bottom: 1px solid #cac7c7;">
					<div class="col-md-12">							
						<span><b>Produtos Utilizados: </b></span>
						<span id="produtos_dados"></span>							
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

<script src="<?= $url_painel_assets ?>/js/ajax.js"></script>

<script type="text/javascript">
$(document).ready(function () {
	listarManutencoes();

	$('.sel2').select2({
		dropdownParent: $('#modalForm')
	});
});
</script>

<script type="text/javascript">
function listarManutencoes(pagina){
	$("#pagina").val(pagina);

	var busca = $("#buscar").val();
	$.ajax({
		url: 'paginas/' + pag + "/listar.php" + qs_loja,
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
$("#form_manutencao").submit(function (e) {
	e.preventDefault();

	var formData = new FormData(this);

	$.ajax({
		url: 'paginas/' + pag + "/salvar.php" + qs_loja,
		type: 'POST',
		data: formData,

		success: function (mensagem) {
			$('#mensagem').text('');
			$('#mensagem').removeClass();

			if (mensagem.trim() == "Salvo com Sucesso") {
				$('#btn-fechar').click();

				var pagina = $("#pagina").val();
				listarManutencoes(pagina);
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
		url: 'paginas/' + pag + "/excluir.php" + qs_loja,
		method: 'POST',
		data: {id: id},
		dataType: "text",

		success: function (mensagem) {            
			if (mensagem.trim() == "Excluído com Sucesso") {                
				var pagina = $("#pagina").val();
				listarManutencoes(pagina);
			} else {
				$('#mensagem-excluir').addClass('text-danger');
				$('#mensagem-excluir').text(mensagem);
			}
		}
	});
}
</script>