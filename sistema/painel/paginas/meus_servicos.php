<?php 
require_once __DIR__ . '/_context_bootstrap.php';
require_once __DIR__ . '/../verificar.php';
require_once __DIR__ . '/../../conexao.php';

$pag = 'meus_servicos';

$data_hoje = date('Y-m-d');
$data_ontem = date('Y-m-d', strtotime("-1 days",strtotime($data_hoje)));

$mes_atual = Date('m');
$ano_atual = Date('Y');
$data_inicio_mes = $ano_atual."-".$mes_atual."-01";

if($mes_atual == '4' || $mes_atual == '6' || $mes_atual == '9' || $mes_atual == '11'){
	$dia_final_mes = '30';
}else if($mes_atual == '2'){
	$dia_final_mes = '28';
}else{
	$dia_final_mes = '31';
}
$data_final_mes = $ano_atual."-".$mes_atual."-".$dia_final_mes;
?>

<div class="">      
	<a class="btn btn-primary" onclick="inserir()"><i class="fa fa-plus" aria-hidden="true"></i> Novo Serviço</a>
</div>

<div class="bs-example widget-shadow" style="padding:15px">
	<div class="row">
		<div class="col-md-5" style="margin-bottom:5px;">
			<div style="float:left; margin-right:10px"><span><small><i title="Data Inicial" class="fa fa-calendar-o"></i></small></span></div>
			<div style="float:left; margin-right:20px">
				<input type="date" class="form-control" id="data-inicial-caixa" value="<?php echo $data_hoje ?>" required>
			</div>
			<div style="float:left; margin-right:10px"><span><small><i title="Data Final" class="fa fa-calendar-o"></i></small></span></div>
			<div style="float:left; margin-right:30px">
				<input type="date" class="form-control" id="data-final-caixa" value="<?php echo $data_hoje ?>" required>
			</div>
		</div>

		<div class="col-md-2" style="margin-top:5px;" align="center">	
			<small>
				<a class="text-muted" href="javascript:void(0)" onclick="valorData('<?php echo $data_ontem ?>','<?php echo $data_ontem ?>')">Ontem</a> /
				<a class="text-muted" href="javascript:void(0)" onclick="valorData('<?php echo $data_hoje ?>','<?php echo $data_hoje ?>')">Hoje</a> /
				<a class="text-muted" href="javascript:void(0)" onclick="valorData('<?php echo $data_inicio_mes ?>','<?php echo $data_final_mes ?>')">Mês</a>
			</small>
		</div>

		<div class="col-md-3" style="margin-top:5px;" align="center">	
			<small>
				<a class="text-muted" href="javascript:void(0)" onclick="buscarContas('')">Todos</a> /
				<a class="text-muted" href="javascript:void(0)" onclick="buscarContas('Não')">Pendentes</a> /
				<a class="text-muted" href="javascript:void(0)" onclick="buscarContas('Sim')">Pagos</a>
			</small>
		</div>

		<input type="hidden" id="buscar-contas" value="">
	</div>

	<hr>
	<div id="listar"></div>
</div>


<!-- Modal Inserir / Editar -->
<div class="modal fade" id="modalForm" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title"><span id="titulo_inserir"></span></h4>
				<button id="btn-fechar" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top:-20px">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>

			<form id="form_servico_agenda">
				<div class="modal-body">
					<div class="row">
						<div class="col-md-6">						
							<div class="form-group"> 
								<label>Clientes</label> 
								<select class="form-control sel2" id="cliente" name="cliente" style="width:100%;" required> 
									<?php 
									$query = $pdo->query("SELECT * FROM clientes ORDER BY nome asc");
									$res = $query->fetchAll(PDO::FETCH_ASSOC);
									for($i=0; $i < @count($res); $i++){
										echo '<option value="'.$res[$i]['id'].'">'.$res[$i]['nome'].'</option>';
									}
									?>
								</select>    
							</div>						
						</div>

						<div class="col-md-6">						
							<div class="form-group"> 
								<label>Serviço</label> 
								<select class="form-control sel2" id="servico" name="servico" style="width:100%;" required> 
									<?php 
									$query = $pdo->query("SELECT * FROM servicos ORDER BY nome asc");
									$res = $query->fetchAll(PDO::FETCH_ASSOC);
									for($i=0; $i < @count($res); $i++){
										echo '<option value="'.$res[$i]['id'].'">'.$res[$i]['nome'].'</option>';
									}
									?>
								</select>    
							</div>						
						</div>
					</div>

					<div class="row">
						<div class="col-md-3">						
							<div class="form-group"> 
								<label>Valor</label> 
								<input type="text" class="form-control" name="valor_serv" id="valor_serv" onkeyup="mascara_valor('valor_serv')" required>
							</div>						
						</div>

						<div class="col-md-4">						
							<div class="form-group"> 
								<label>Data PGTO</label> 
								<input type="date" class="form-control" name="data_pgto" id="data_pgto" value="<?php echo date('Y-m-d') ?>"> 
							</div>						
						</div>	

						<div class="col-md-5">						
							<div class="form-group"> 
								<label>Forma PGTO</label> 
								<select class="form-control" id="pgto" name="pgto" style="width:100%;" required> 
									<?php 
									$query = $pdo->query("SELECT * FROM formas_pgto");
									$res = $query->fetchAll(PDO::FETCH_ASSOC);
									for($i=0; $i < @count($res); $i++){
										echo '<option value="'.$res[$i]['nome'].'">'.$res[$i]['nome'].'</option>';
									}
									?>
								</select>    
							</div>						
						</div>
					</div>

					<div class="row">
						<div class="col-md-3">						
							<div class="form-group"> 
								<label>Valor Restante</label> 
								<input type="text" class="form-control" name="valor_serv_agd_restante" id="valor_serv_agd_restante" onkeyup="mascara_valor('valor_serv_agd_restante')">
							</div>						
						</div>

						<div class="col-md-4">						
							<div class="form-group"> 
								<label>Data PGTO Restante</label> 
								<input type="date" class="form-control" name="data_pgto_restante" id="data_pgto_restante">
							</div>						
						</div>

						<div class="col-md-5">						
							<div class="form-group"> 
								<label>Forma PGTO Restante</label> 
								<select class="form-control" id="pgto_restante" name="pgto_restante" style="width:100%;"> 
									<option value="">Selecionar Pgto</option>
									<?php 
									$query = $pdo->query("SELECT * FROM formas_pgto");
									$res = $query->fetchAll(PDO::FETCH_ASSOC);
									for($i=0; $i < @count($res); $i++){
										echo '<option value="'.$res[$i]['nome'].'">'.$res[$i]['nome'].'</option>';
									}
									?>
								</select>    
							</div>						
						</div>
					</div>

					<div class="col-md-12">						
						<div class="form-group"> 
							<label>Observações</label> 
							<input maxlength="1000" type="text" class="form-control" name="obs" id="obs2"> 
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


<!-- ✅ Modal Dados (NECESSÁRIO PARA "VER DADOS") -->
<div class="modal fade" id="modalDados" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title"><span id="nome_dados"></span></h4>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top:-20px">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>

			<div class="modal-body">

				<div class="row" style="border-bottom:1px solid #cac7c7; padding-bottom:8px; margin-bottom:8px;">
					<div class="col-md-6"><b>Valor:</b> <span id="valor_dados"></span></div>
					<div class="col-md-6"><b>Data Serviço:</b> <span id="data_lanc_dados"></span></div>
				</div>

				<div class="row" style="border-bottom:1px solid #cac7c7; padding-bottom:8px; margin-bottom:8px;">
					<div class="col-md-6"><b>Vencimento:</b> <span id="data_venc_dados"></span></div>
					<div class="col-md-6"><b>Pago Em:</b> <span id="data_pgto_dados"></span></div>
				</div>

				<div class="row" style="border-bottom:1px solid #cac7c7; padding-bottom:8px; margin-bottom:8px;">
					<div class="col-md-6"><b>Usuário Lanc:</b> <span id="usuario_lanc_dados"></span></div>
					<div class="col-md-6"><b>Usuário Baixa:</b> <span id="usuario_baixa_dados"></span></div>
				</div>

				<div class="row" style="border-bottom:1px solid #cac7c7; padding-bottom:8px; margin-bottom:8px;">
					<div class="col-md-6"><b>Cliente:</b> <span id="pessoa_dados"></span></div>
					<div class="col-md-6"><b>Telefone:</b> <span id="telefone_dados"></span></div>
				</div>

				<div class="row">
					<div class="col-md-12"><b>OBS:</b> <span id="obs_dados"></span></div>
				</div>

			</div>
		</div>
	</div>
</div>


<!-- Modal Baixar (igual seu padrão atual) -->
<div class="modal fade" id="modalBaixar" tabindex="-1" role="dialog" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title"><span id="titulo_baixar"></span></h4>
				<button id="btn-fechar-baixar" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top:-20px">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>

			<form id="form-baixar">
				<div class="modal-body">						
					<div class="row">
						<div class="col-md-4">
							<div class="form-group">
								<label>Valor</label>
								<input type="text" class="form-control" id="valor_baixar" name="valor" onkeyup="mascara_valor('valor_baixar')" required>
							</div> 	
						</div>					

						<div class="col-md-4">
							<div class="form-group">
								<label>Forma de Pagamento</label>
								<select class="form-control" id="pgto_baixar" name="pgto" required>
									<?php 
									$query = $pdo->query("SELECT * FROM formas_pgto");
									$res = $query->fetchAll(PDO::FETCH_ASSOC);
									for($i=0; $i < @count($res); $i++){
										echo '<option value="'.$res[$i]['nome'].'">'.$res[$i]['nome'].'</option>';
									}
									?>
								</select>       
							</div> 	
						</div>	

						<div class="col-md-4">
							<div class="form-group">
								<label>Pago Em</label>
								<input type="date" class="form-control" id="data_pgto_baixar" name="data_pgto" value="<?php echo $data_hoje ?>">
							</div> 	
						</div>
					</div>

					<input type="hidden" name="id" id="id_baixar">
					<br>
					<small><div id="mensagem-baixar" align="center"></div></small>
				</div>

				<div class="modal-footer">      
					<button id="btn_baixar" type="submit" class="btn btn-success">Baixar</button>
				</div>
			</form>

		</div>
	</div>
</div>


<form action="rel/recibo_class.php" method="post" style="display:none" target="_blank">
    <input type="hidden" name="id" id="id_conta_recibo">
    <input type="hidden" name="enviar" value="Sim">
    <button id="btn_form_recibo" type="submit"></button>
</form>

<script type="text/javascript">var pag = "<?=$pag?>"</script>
<script src="js/ajax.js"></script>

<script type="text/javascript">
	$(document).ready(function() {
		$('.sel2').select2({
			dropdownParent: $('#modalForm')
		});
		listar();
	});

	function valorData(dataInicio, dataFinal){
		$('#data-inicial-caixa').val(dataInicio);
		$('#data-final-caixa').val(dataFinal);	
		listar();
	}

	$('#data-inicial-caixa').change(function(){ listar(); });
	$('#data-final-caixa').change(function(){ listar(); });

	function buscarContas(status){
		$('#buscar-contas').val(status);
		listar();
	}

	function listar(){
		var dataInicial = $('#data-inicial-caixa').val();
		var dataFinal = $('#data-final-caixa').val();	
		var status = $('#buscar-contas').val();	
		
		$.ajax({
			url: 'paginas/' + pag + "/listar.php",
			method: 'POST',
			data: {dataInicial, dataFinal, status},
			dataType: "html",
			success:function(result){
				$("#listar").html(result);
				$('#mensagem-excluir').text('');
			}
		});
	}

	// abrir modal baixar (padrão)
	function baixar(id){
		$('#mensagem-baixar').text('').removeClass();
		$('#id_baixar').val(id);
		$('#titulo_baixar').text('Baixar Conta');
		$('#modalBaixar').modal('show');
		return false;
	}

	$("#form-baixar").submit(function (event) {
		event.preventDefault();

		$('#btn_baixar').hide();
		$('#mensagem-baixar').removeClass().text('Baixando...');

		var formData = new FormData(this);

		$.ajax({
			url: 'paginas/' + pag + "/baixar.php",
			type: 'POST',
			data: formData,
			success: function (mensagem) {
				$('#mensagem-baixar').text('').removeClass();

				if (mensagem.trim() == "Baixado com Sucesso") {
					$('#btn-fechar-baixar').click();
					listar();
				} else {
					$('#mensagem-baixar').addClass('text-danger').text(mensagem);
				}
				$('#btn_baixar').show();
			},
			cache: false,
			contentType: false,
			processData: false
		});
	});

	// submit salvar serviço (mantém seu padrão)
	let enviandoServico = false;

	$("#form_servico_agenda").on("submit", function (event) {
		event.preventDefault();
		if (enviandoServico) return;

		enviandoServico = true;
		const $form = $(this);
		const $btn  = $form.find('button[type=submit]');
		const textoOriginal = $btn.text();
		$btn.prop('disabled', true).text('Salvando...');

		var formData = new FormData(this);

		$.ajax({
			url: 'paginas/' + pag + "/salvar.php",
			type: 'POST',
			data: formData,
			success: function (mensagem) {
				var split = mensagem.split('*');
				$('#mensagem').text('').removeClass();

				if (split[0].trim() == "Salvo com Sucesso") {
					$('#btn-fechar').click();
					listar();

					if(split[1]){
						$('#id_conta_recibo').val(split[1].trim());
						$("#btn_form_recibo").click();
					}
				} else {
					$('#mensagem').addClass('text-danger').text(split[0].trim());
				}
			},
			cache: false,
			contentType: false,
			processData: false,
			complete: function () {
				enviandoServico = false;
				$btn.prop('disabled', false).text(textoOriginal);
			}
		});
	});
</script>
