<?php 
require_once __DIR__ . '/_context_bootstrap.php';
require_once __DIR__ . '/../verificar.php';
require_once __DIR__ . '/../../conexao.php';
$pag = 'agenda';
$data_atual = date('Y-m-d');


?>

<style>
/* Layout padronizado dos agendamentos */
#listar{
	display:flex;
	flex-wrap:wrap;
	align-items:stretch;
	gap:14px;
	padding:14px !important;
	min-height:120px;
}
#listar > .cardTarefas{
	float:none;
	width:calc(33.333% - 10px);
	max-width:calc(33.333% - 10px);
	flex:0 0 calc(33.333% - 10px);
	padding:0 !important;
	margin:0;
}
#listar > .cardTarefas .r3_counter_box{
	height:100%;
	min-height:150px;
	margin-bottom:0;
	padding:14px 14px 12px;
	border-radius:12px;
	background:#fff;
	box-shadow:0 2px 10px rgba(0,0,0,.08);
	border:1px solid #ececec;
}
#listar > .cardTarefas .agenda-card__header{
	display:flex;
	align-items:flex-start;
	gap:10px;
	margin-bottom:8px;
}
#listar > .cardTarefas .agenda-card__icon-col{
	flex:0 0 52px;
	max-width:52px;
}
#listar > .cardTarefas .agenda-card__info-col{
	flex:1 1 auto;
	max-width:calc(100% - 52px);
	padding:0;
}
#listar > .cardTarefas .agenda-card__hora{
	margin:2px 0 0;
	font-size:34px;
	line-height:1;
	font-weight:700;
	color:#111;
}
#listar > .cardTarefas .agenda-card__hora small{
	font-size:14px;
	font-weight:600;
}
#listar > .cardTarefas hr{
	margin:8px 0 10px !important;
	border-top:1px solid #ededed;
}
#listar > .cardTarefas .stats{
	min-height:44px;
	display:flex;
	align-items:center;
	justify-content:center;
	text-align:center;
	word-break:break-word;
}
#listar > .cardTarefas .stats small{
	line-height:1.45;
	display:block;
}
#listar > .cardTarefas .close{
	margin-top:0 !important;
}
#listar .agenda-card__empty{
	width:100%;
	padding:18px 12px;
	text-align:center;
	font-size:15px;
	color:#666;
}
@media (max-width: 1199px){
	#listar > .cardTarefas{
		width:calc(50% - 7px);
		max-width:calc(50% - 7px);
		flex-basis:calc(50% - 7px);
	}
}
@media (max-width: 767px){
	#listar{
		padding:10px !important;
		gap:10px;
	}
	#listar > .cardTarefas{
		width:100%;
		max-width:100%;
		flex-basis:100%;
	}
	#listar > .cardTarefas .r3_counter_box{
		min-height:132px;
		padding:12px;
	}
	#listar > .cardTarefas .agenda-card__hora{
		font-size:30px;
	}
}
</style>

<div class="row">
	<div class="col-md-3">
		<button style="margin-bottom:10px" onclick="inserir()" type="button" class="btn btn-primary btn-flat btn-pri"><i class="fa fa-plus" aria-hidden="true"></i> Novo Agendamento</button>
	</div>

	
</div>
<input type="hidden" name="data_agenda" id="data_agenda" value="<?php echo date('Y-m-d') ?>"> 

<div class="row" style="margin-top: 15px">

	<div class="col-md-4 agile-calendar">
		<div class="calendar-widget">

			<!-- grids -->
			<div class="agile-calendar-grid">
				<div class="page">

					<div class="w3l-calendar-left">
						<div class="calendar-heading">

						</div>
						<div class="monthly" id="mycalendar"></div>
					</div>

					<div class="clearfix"> </div>
				</div>
			</div>
		</div>
	</div>


	<div class="col-xs-12 col-md-8 bs-example widget-shadow" style="padding:10px 5px; margin-top: 0px;" id="listar">

	</div>
</div>


<!-- Modal -->
<div class="modal fade" id="modalForm" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title" id="titulo_inserir"></h4>
				<button id="btn-fechar" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<form method="post" id="form-text">
				<div class="modal-body">

					<div class="row">
						<div class="col-md-5">						
							<div class="form-group"> 
								<label>Cliente</label> 
								<select class="form-control sel3" id="cliente" name="cliente" style="width:100%;" required> 

									<?php 
									$query = $pdo->query("SELECT * FROM clientes ORDER BY nome asc");
									$res = $query->fetchAll(PDO::FETCH_ASSOC);
									$total_reg = @count($res);
									if($total_reg > 0){
										for($i=0; $i < $total_reg; $i++){
											foreach ($res[$i] as $key => $value){}
												echo '<option value="'.$res[$i]['id'].'">'.$res[$i]['nome'].'</option>';
										}
									}
									?>


								</select>    
							</div>						
						</div>


						<div class="col-md-4">						
						<div class="form-group"> 
							<label>Serviço</label> 
							<select class="form-control sel3" id="servico" name="servico" style="width:100%;" required> 

									<?php 
									$query = $pdo->query("SELECT * FROM servicos_func where funcionario = '$id_usuario' ");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
if(@count($res) > 0){
	for($i=0; $i < @count($res); $i++){
		$serv = $res[$i]['servico'];

		$query2 = $pdo->query("SELECT * FROM servicos where id = '$serv' and ativo = 'Sim' ");
		$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);	
		$nome_func = $res2[0]['nome'];

		echo '<option value="'.$serv.'">'.$nome_func.'</option>';
	}		
}else{
	echo '<option value="">Nenhum Serviço</option>';
}
									?>


								</select>    
						</div>						
					</div>

						<div class="col-md-3" id="nasc">						
							<div class="form-group"> 
								<label>Data </label> 
								<input type="date" class="form-control" name="data" id="data-modal" onchange="mudarData()"> 
							</div>						
						</div>
					

					</div>


					<hr>
					<div class="row">

							<div class="col-md-12" id="nasc">						
							<div class="form-group"> 								
								<div id="listar-horarios">
									
								</div>
							</div>						
						</div>					

				</div>
				<hr>

					<div class="col-md-12">						
						<div class="form-group"> 
							<label>OBS <small>(Máx 100 Caracteres)</small></label> 
							<input maxlength="100" type="text" class="form-control" name="obs" id="obs">
						</div>						
					</div>


					<br>
					<input type="hidden" name="id" id="id">
					<input type="hidden" name="id_funcionario" id="id_funcionario"> 
					<small><div id="mensagem" align="center" class="mt-3"></div></small>					

				</div>


				<div class="modal-footer">
					<button type="submit" class="btn btn-primary">Salvar</button>
				</div>



			</form>

		</div>
	</div>
</div>



<!-- Modal -->
<div class="modal fade" id="modalServico" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title">Serviço: <span id="titulo_servico"></span></h4>
				<button id="btn-fechar-servico" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<form method="post" id="form-servico">
				<div class="modal-body">

					<div class="row">
						<div class="col-md-12">						
							<div class="form-group"> 
								<label>Funcionário</label> 
								<select class="form-control sel4" id="funcionario_agd" name="funcionario_agd" style="width:100%;" required> 

									<?php 
									$query = $pdo->query("SELECT * FROM usuarios where atendimento = 'Sim' ORDER BY nome asc");
									$res = $query->fetchAll(PDO::FETCH_ASSOC);
									$total_reg = @count($res);
									if($total_reg > 0){
										for($i=0; $i < $total_reg; $i++){
											foreach ($res[$i] as $key => $value){}
												echo '<option value="'.$res[$i]['id'].'">'.$res[$i]['nome'].'</option>';
										}
									}
									?>


								</select>    
							</div>						
						</div>					


					</div>

					<div class="row">
						<div class="col-md-4" id="nasc">						
							<div class="form-group"> 
								<label>Valor (Falta Pagar) </label> 
								<input type="text" class="form-control" name="valor_serv_agd" id="valor_serv_agd"> 
							</div>						
						</div>


						<div class="col-md-4" id="nasc">						
							<div class="form-group"> 
								<label>Data PGTO</label> 
								<input type="date" class="form-control" name="data_pgto" id="data_pgto" value="<?php echo $data_atual ?>"> 
							</div>						
						</div>

						<div class="col-md-4">						
							<div class="form-group"> 
								<label>Forma PGTO</label> 
								<select class="form-control" id="pgto" name="pgto" style="width:100%;" required> 

									<?php 
									$query = $pdo->query("SELECT * FROM formas_pgto");
									$res = $query->fetchAll(PDO::FETCH_ASSOC);
									$total_reg = @count($res);
									if($total_reg > 0){
										for($i=0; $i < $total_reg; $i++){
											foreach ($res[$i] as $key => $value){}
												echo '<option value="'.$res[$i]['nome'].'">'.$res[$i]['nome'].'</option>';
										}
									}
									?>


								</select>    
							</div>						
						</div>	
					</div>


						<div class="row">
						<div class="col-md-4" id="">						
							<div class="form-group"> 
								<label>Valor Restante </label> 
								<input type="text" class="form-control" name="valor_serv_agd_restante" id="valor_serv_agd_restante"> 
							</div>						
						</div>


						<div class="col-md-4" id="">						
							<div class="form-group"> 
								<label>Data PGTO Restante</label> 
								<input type="date" class="form-control" name="data_pgto_restante" id="data_pgto_restante" value="" onkeyup="mascara_valor('data_pgto_restante')"> 
							</div>						
						</div>

						<div class="col-md-4">						
							<div class="form-group"> 
								<label>Forma PGTO Restante</label> 
								<select class="form-control" id="pgto_restante" name="pgto_restante" style="width:100%;" > 
									<option value="">Selecionar Pgto</option>
									<?php 
									$query = $pdo->query("SELECT * FROM formas_pgto");
									$res = $query->fetchAll(PDO::FETCH_ASSOC);
									$total_reg = @count($res);
									if($total_reg > 0){
										for($i=0; $i < $total_reg; $i++){
											foreach ($res[$i] as $key => $value){}
												echo '<option value="'.$res[$i]['nome'].'">'.$res[$i]['nome'].'</option>';
										}
									}
									?>


								</select>    
							</div>						
						</div>	
					</div>

					<div class="row">

						

						<div class="col-md-12">						
							<div class="form-group"> 
								<label>Observações </label> 
								<input maxlength="1000" type="text" class="form-control" name="obs" id="obs2"> 
							</div>						
						</div>

					</div>



					<br>
					
					<input type="hidden" name="id_agd" id="id_agd"> 
					<input type="hidden" name="cliente_agd" id="cliente_agd"> 
					<input type="hidden" name="servico_agd" id="servico_agd">
					<input type="hidden" name="descricao_serv_agd" id="descricao_serv_agd">
					
					<small><div id="mensagem-servico" align="center" class="mt-3"></div></small>					

				</div>


				<div class="modal-footer">
					<button type="submit" class="btn btn-primary">Salvar...</button>
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

<script src="//js.nicedit.com/nicEdit-latest.js" type="text/javascript"></script>
<script type="text/javascript">bkLib.onDomLoaded(nicEditors.allTextAreas);</script>


<!-- calendar -->
<script type="text/javascript" src="js/monthly.js"></script>
<script type="text/javascript">
	$(window).load( function() {

		$('#mycalendar').monthly({
			mode: 'event',

		});

		$('#mycalendar2').monthly({
			mode: 'picker',
			target: '#mytarget',
			setWidth: '250px',
			startHidden: true,
			showTrigger: '#mytarget',
			stylePast: true,
			disablePast: true
		});

		switch(window.location.protocol) {
			case 'http:':
			case 'https:':
		// running on a server, should be good.
		break;
		case 'file:':
		alert('Just a heads-up, events will not work when run locally.');
	}

});
</script>
<!-- //calendar -->

<script type="text/javascript">
	$(document).ready(function() {

		listarHorarios();
		
		$('.sel3').select2({
			dropdownParent: $('#modalForm')
		});
	});
</script>


<script type="text/javascript">
	$(document).ready(function() {
		$('.sel2').select2({
			
		});
	});
</script>


<script type="text/javascript">
	$(document).ready(function() {
		
		$('.sel4').select2({
			dropdownParent: $('#modalServico')
		});
	});
</script>


<script>

	$("#form-text").submit(function () {
		$('#mensagem').text('Carregando...');
		event.preventDefault();
		
		var formData = new FormData(this);

		$.ajax({
			url: 'paginas/' + pag +  "/inserir.php",
			type: 'POST',
			data: formData,

			success: function (mensagem) {				
				$('#mensagem').text('');
				$('#mensagem').removeClass()
				if (mensagem.trim() == "Salvo com Sucesso") {                   
					$('#btn-fechar').click();
					listar();
					listarHorarios();
				} else {
					$('#mensagem').addClass('text-danger')
					$('#mensagem').text(mensagem)
				}

			},

			cache: false,
			contentType: false,
			processData: false,

		});

	});

</script>


<script type="text/javascript">
	function listar(){

		var funcionario = $('#funcionario').val();

		var data = $("#data_agenda").val();	
		$("#data-modal").val(data);


		$.ajax({
			url: 'paginas/' + pag + "/listar.php",
			method: 'POST',
			data: {data, funcionario},
			dataType: "text",

			success:function(result){
				$("#listar").html(result);
			}
		});
	}
</script>


<script type="text/javascript">
	
	function limparCampos(){
		$('#id').val('');		
		$('#obs').val('');
		$('#hora').val('');				
		$('#data').val('<?=$data_atual?>');	

	}
</script>


<script type="text/javascript">
	
	function mudarFuncionario(){
		var funcionario = $('#funcionario').val();
		$('#id_funcionario').val(funcionario);				
		listar();	
		listarHorarios();				
	}
</script>


<script type="text/javascript">
	
	function mudarData(){
		var data = $('#data-modal').val();			
		$('#data_agenda').val(data).change();

		listar();	
		listarHorarios();

	}
</script>


<script type="text/javascript">
	function listarHorarios(){

		var funcionario = $('#funcionario').val();	
		var data = $('#data_agenda').val();	

		
		$.ajax({
			url: 'paginas/' + pag + "/listar-horarios.php",
			method: 'POST',
			data: {funcionario, data},
			dataType: "text",

			success:function(result){
				$("#listar-horarios").html(result);
			}
		});
	}
</script>


<script>
	function bb_agenda_slug_atual() {
		try {
			var path = (window.location && window.location.pathname) ? window.location.pathname : '';
			var m = path.match(/^\/loja\/([^\/]+)(?:\/|$)/i);
			var slug = m && m[1] ? String(m[1]).toLowerCase() : '';
			return slug.replace(/[^a-z0-9_-]/g, '');
		} catch (e) {
			return '';
		}
	}

	function bb_agenda_injetar_loja(formData) {
		try {
			var slug = bb_agenda_slug_atual();
			if (!slug || !formData) return formData;

			if (typeof formData.has !== 'function' || !formData.has('loja')) {
				formData.append('loja', slug);
			}
		} catch (e) {}
		return formData;
	}

	$("#form-servico").submit(function (event) {
		event.preventDefault();

		var formData = new FormData(this);
		bb_agenda_injetar_loja(formData);

		$.ajax({
			url: 'paginas/' + pag +  "/inserir-servico.php",
			type: 'POST',
			data: formData,

			success: function (mensagem) {
				mensagem = (mensagem == null ? '' : String(mensagem)).trim();
				var partes = mensagem.split('|');
				var retorno = (partes[0] || '').trim();

				$('#mensagem-servico').text('');
				$('#mensagem-servico').removeClass();

				if (retorno == "Salvo com Sucesso" || mensagem.indexOf("Salvo com Sucesso") !== -1) {
					$('#btn-fechar-servico').click();
					listar();

					if (partes.length > 1 && $.trim(partes[1]) !== '' && $('#id_conta_recibo').length) {
						$('#id_conta_recibo').val($.trim(partes[1]));
						$("#btn_form_recibo").click();
					}
				} else {
					$('#mensagem-servico').addClass('text-danger');
					$('#mensagem-servico').text(mensagem || 'Erro ao finalizar serviço.');
				}

			},

			error: function (xhr) {
				var resposta = xhr && xhr.responseText ? $.trim(xhr.responseText) : 'Erro ao finalizar serviço.';
				$('#mensagem-servico').removeClass().addClass('text-danger').text(resposta);
			},

			cache: false,
			contentType: false,
			processData: false,

		});

	});

</script>