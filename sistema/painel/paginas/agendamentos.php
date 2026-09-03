<?php 
require_once __DIR__ . '/_context_bootstrap.php';
require_once __DIR__ . '/../verificar.php';
require_once __DIR__ . '/../../conexao.php';
$pag = 'agendamentos';
$data_atual = date('Y-m-d');

$slug_loja_atual = '';
$__bb_uri_ag = $_SERVER['REQUEST_URI'] ?? '';
if (!empty($_GET['loja'])) {
	$slug_loja_atual = preg_replace('/[^a-z0-9_-]/i', '', (string)$_GET['loja']);
} elseif (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_uri_ag, $__m_loja_ag)) {
	$slug_loja_atual = preg_replace('/[^a-z0-9_-]/i', '', (string)$__m_loja_ag[1]);
}


//verificar se ele tem a permissão de estar nessa página
if(@$agendamentos == 'ocultar'){
	echo "<script>window.location='../index.php'</script>";
	exit();
}


// Otimização de performance:
// A verificação de pagamentos via API (PIX) foi movida para execução em segundo plano via AJAX,
// para não travar o carregamento desta tela. A lógica de aprovação permanece a mesma.

?>

<div id="aviso-sucesso-agendamento" class="alert alert-success" style="display:none; margin-bottom:15px;">
	<span id="texto-aviso-sucesso-agendamento"></span>
</div>

<div class="row">
	<div class="col-md-3">
		<button style="margin-bottom:10px" onclick="inserir()" type="button" class="btn btn-primary btn-flat btn-pri"><i class="fa fa-plus" aria-hidden="true"></i> Novo Agendamento</button>
	</div>

	<div class="col-md-3">
		<div class="form-group">			
			<select class="form-control sel2" id="funcionario" name="funcionario" style="width:100%;" onchange="mudarFuncionario()"> 
				<option value="">Selecione um Profissional</option>
				<?php 
				$query = $pdo->query("SELECT * FROM usuarios where atendimento = 'Sim' ORDER BY id desc");
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
<div class="modal fade" id="modalForm" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" data-backdrop="static">
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
						<div class="col-md-6">						
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


						<div class="col-md-6">
							<div class="form-group">
							<label>Funcionário </label> 			
								<select class="form-control sel2" id="funcionario_modal" name="funcionario" style="width:100%;" onchange="mudarFuncionarioModal()"> 
									<option value="">Selecione um Funcionário</option>
									<?php 
									$query = $pdo->query("SELECT * FROM usuarios where atendimento = 'Sim' ORDER BY id desc");
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
						<div class="col-md-8">						
							<div class="form-group"> 
								<label>Serviço</label> 
								<select class="form-control sel3" id="servico" name="servico" style="width:100%;" required> 									

								</select>    
							</div>						
						</div>

						<div class="col-md-4" id="nasc">						
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
									<small>Selecionar Funcionário</small>
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


<!-- Modal agenda-->
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
								<input type="text" class="form-control" name="valor_serv_agd" id="valor_serv_agd" onkeyup="mascara_valor('valor_serv_agd')"> 
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
								<label>Forma PGTO.</label> 
								<select class="form-control" id="pgto" name="pgto" style="width:100%;" required> 
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
						<div class="col-md-4" id="">						
							<div class="form-group"> 
								<label>Valor Restante </label> 
								<input type="text" class="form-control" name="valor_serv_agd_restante" id="valor_serv_agd_restante" placeholder="Mais de uma forma PGTO" onkeyup="mascara_valor('valor_serv_agd_restante')">  
							</div>						
						</div>


						<div class="col-md-4" id="">						
							<div class="form-group"> 
								<label>Data PGTO Restante</label> 
								<input type="date" class="form-control" name="data_pgto_restante" id="data_pgto_restante" value=""> 
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


					<div class="row" id="linha_combo_atendimento" style="display:none;">
						<div class="col-md-12">
							<div class="form-group" style="border:1px solid #dcdcdc; padding:12px; border-radius:6px; margin-top:5px;">
								<label style="display:block; margin-bottom:8px;">Seção do Combo / Pacote</label>

								<div id="combo_resumo_servico" style="display:none; margin-bottom:10px; background:#f7fbff; border:1px solid #d6eaf8; padding:10px; border-radius:6px;">
									<div><strong>Serviço Combo:</strong> <span id="combo_servico_nome"></span></div>
									<div><strong>Sessões do Pacote:</strong> <span id="combo_servico_qtd">0</span> | <strong>Validade:</strong> <span id="combo_servico_validade">0 dias</span></div>
								</div>

								<div id="combo_status_cliente" style="margin-bottom:10px;">
									<small style="color:#7f8c8d;">Carregando dados do combo...</small>
								</div>

								<div id="combo_acoes_box" style="display:none; margin-bottom:10px; background:#fbfbfb; border:1px solid #ececec; padding:10px; border-radius:6px;">
									<div style="margin-bottom:8px;"><strong>Ação deste atendimento</strong></div>

									<label style="display:block; font-weight:normal; margin-bottom:8px;">
										<input type="radio" name="acao_combo_visual" id="acao_combo_usar" value="usar_combo">
										Usar combo ativo do cliente
										<small style="display:block; color:#4f81bd; margin-left:18px;">Baixa 1 sessão agora sem gerar novo recebimento.</small>
									</label>

									<label style="display:block; font-weight:normal; margin-bottom:0;">
										<input type="radio" name="acao_combo_visual" id="acao_combo_vender" value="vender_combo">
										Vender combo agora neste atendimento
										<small style="display:block; color:#8a6d3b; margin-left:18px;">Exige pagamento normal e baixa automaticamente a 1ª sessão.</small>
									</label>
								</div>

								<div id="combo_acao_resumo">
									<small style="color:#7f8c8d;">Defina a ação do combo para este atendimento.</small>
								</div>

								<small id="texto_combo_agendamento" style="display:block; color:#7f8c8d; margin-top:6px;">
									Se o cliente já possui combo ativo, use o combo. Se está vendendo agora, conclua com pagamento normal.
								</small>
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
					<input type="hidden" name="assinatura_combo_id" id="assinatura_combo_id" value="0">
					<input type="hidden" name="acao_combo" id="acao_combo" value="normal">
					<input type="hidden" name="usar_combo" id="usar_combo" value="Não">
					<input type="hidden" name="loja" id="loja_agd" value="<?php echo htmlspecialchars($slug_loja_atual, ENT_QUOTES); ?>">
					
					<small><div id="mensagem-servico" align="center" class="mt-3"></div></small>					

				</div>


				<div class="modal-footer">
					<button type="submit" class="btn btn-primary">Salvar</button>
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

		var funcionario = $('#funcionario_modal').val();

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
		$('#funcionario_modal').val(funcionario).change();

		listar();	
		listarHorarios();
		listarServicos(funcionario);

	}
</script>



<script type="text/javascript">
	
	function mudarFuncionarioModal(){	
		var func = $('#funcionario_modal').val();	
		listar();	
		listarHorarios();
		listarServicos(func);
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

		var funcionario = $('#funcionario_modal').val();	
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



<script type="text/javascript">
	var bbAgdServicoModalCache = {
		valor_servico: '',
		data_pgto: '',
		funcionario: '',
		servico_nome: ''
	};

	function bbAgdResetModalServico(){
		$('#id_agd').val('');
		$('#cliente_agd').val('');
		$('#servico_agd').val('');
		$('#descricao_serv_agd').val('');
		$('#assinatura_combo_id').val('0');
		$('#acao_combo').val('normal');
		$('#usar_combo').val('Não');

		$('#titulo_servico').text('');
		$('#valor_serv_agd').val('');
		$('#data_pgto').val('<?php echo $data_atual ?>');
		$('#funcionario_agd').val('').change();
		$('#obs2').val('');
		$('#valor_serv_agd_restante').val('');
		$('#data_pgto_restante').val('');
		$('#pgto').val('').change();
		$('#pgto_restante').val('').change();

		$('#linha_combo_atendimento').hide();
		$('#combo_resumo_servico').hide();
		$('#combo_acoes_box').hide();
		$('#combo_status_cliente').html('<small style="color:#7f8c8d;">Carregando dados do combo...</small>');
		$('#combo_servico_nome').text('');
		$('#combo_servico_qtd').text('0');
		$('#combo_servico_validade').text('0 dias');
		$('#combo_acao_resumo').html('<small style="color:#7f8c8d;">Defina a ação do combo para este atendimento.</small>');
		$('#texto_combo_agendamento').css('color', '#7f8c8d').text('Se o cliente já possui combo ativo, use o combo. Se está vendendo agora, conclua com pagamento normal.');

		$('#acao_combo_usar').prop('checked', false);
		$('#acao_combo_vender').prop('checked', false);

		bbAgdToggleCamposPagamento(false);
		bbAgdServicoModalCache = {
			valor_servico: '',
			data_pgto: '',
			funcionario: '',
			servico_nome: ''
		};
	}

	function bbAgdToggleCamposPagamento(desabilitar){
		desabilitar = !!desabilitar;

		$('#valor_serv_agd').prop('readonly', desabilitar);
		$('#valor_serv_agd_restante').prop('readonly', desabilitar);
		$('#data_pgto').prop('readonly', desabilitar);
		$('#data_pgto_restante').prop('readonly', desabilitar);
		$('#pgto').prop('disabled', desabilitar);
		$('#pgto_restante').prop('disabled', desabilitar);

		if(desabilitar){
			$('#pgto').prop('required', false);
			$('#pgto').val('').change();
			$('#pgto_restante').val('').change();
			$('#valor_serv_agd').val('0,00');
			$('#valor_serv_agd_restante').val('');
			$('#data_pgto_restante').val('');
		}else{
			$('#pgto').prop('required', true);
			$('#valor_serv_agd').val(bbAgdServicoModalCache.valor_servico);
			$('#data_pgto').val(bbAgdServicoModalCache.data_pgto);
			$('#funcionario_agd').val(bbAgdServicoModalCache.funcionario).change();
		}
	}

	function bbAgdDefinirAcaoCombo(acao){
		var assinaturaId = parseInt($('#assinatura_combo_id').val() || '0', 10);
		var ehCombo = $('#linha_combo_atendimento').is(':visible');

		if(!ehCombo){
			acao = 'normal';
		}

		if(acao === 'usar_combo' && assinaturaId <= 0){
			acao = 'vender_combo';
		}

		$('#acao_combo').val(acao);
		$('#usar_combo').val(acao === 'usar_combo' ? 'Sim' : 'Não');

		$('#acao_combo_usar').prop('checked', acao === 'usar_combo');
		$('#acao_combo_vender').prop('checked', acao === 'vender_combo');

		if(acao === 'usar_combo'){
			bbAgdToggleCamposPagamento(true);
			$('#combo_acao_resumo').html('<small style="color:#4f81bd;"><strong>Ação no salvar:</strong> baixar 1 sessão do combo ativo sem exigir forma de pagamento.</small>');
			$('#texto_combo_agendamento').css('color', '#4f81bd').text('Modo profissional ativo: este atendimento vai consumir saldo do combo já existente do cliente.');
			return;
		}

		if(acao === 'vender_combo'){
			bbAgdToggleCamposPagamento(false);
			$('#combo_acao_resumo').html('<small style="color:#8a6d3b;"><strong>Ação no salvar:</strong> receber este atendimento, registrar a venda do combo e baixar automaticamente a 1ª sessão.</small>');
			$('#texto_combo_agendamento').css('color', '#8a6d3b').text('Modo profissional ativo: conclua com pagamento normal para vender o combo agora e já consumir a 1ª sessão.');
			return;
		}

		bbAgdToggleCamposPagamento(false);
		$('#combo_acao_resumo').html('<small style="color:#7f8c8d;"><strong>Ação no salvar:</strong> atendimento normal.</small>');
		$('#texto_combo_agendamento').css('color', '#7f8c8d').text('Atendimento sem ação de combo.');
	}

	function bbAgdAplicarDadosModal(dados){
		bbAgdResetModalServico();

		$('#id_agd').val(dados.id_agd || '');
		$('#cliente_agd').val(dados.cliente || '');
		$('#servico_agd').val(dados.servico || '');
		$('#descricao_serv_agd').val(dados.nome_servico || '');
		$('#titulo_servico').text(dados.nome_servico || 'Não Lançado');
		$('#funcionario_agd').val(dados.funcionario || '').change();
		$('#valor_serv_agd').val(dados.valor_servico || '0,00');
		$('#data_pgto').val(dados.data_pgto || '<?php echo $data_atual ?>');

		bbAgdServicoModalCache = {
			valor_servico: dados.valor_servico || '0,00',
			data_pgto: dados.data_pgto || '<?php echo $data_atual ?>',
			funcionario: dados.funcionario || '',
			servico_nome: dados.nome_servico || ''
		};

		if((dados.combo_ativo || 'Não') === 'Sim'){
			$('#linha_combo_atendimento').show();
			$('#combo_resumo_servico').show();
			$('#combo_servico_nome').text(dados.nome_servico || '');
			$('#combo_servico_qtd').text(dados.combo_qtd_sessoes || 0);
			$('#combo_servico_validade').text((dados.combo_validade_dias || 0) + ' dias');
			$('#combo_status_cliente').html(dados.resumo_html || '<small style="color:#7f8c8d;">Cliente sem combo ativo para este serviço.</small>');
			$('#assinatura_combo_id').val(dados.assinatura_combo_id || 0);
			$('#combo_acoes_box').show();

			if((parseInt(dados.assinatura_combo_id || 0, 10)) > 0){
				bbAgdDefinirAcaoCombo('usar_combo');
			}else{
				bbAgdDefinirAcaoCombo('vender_combo');
			}
		}else{
			$('#linha_combo_atendimento').hide();
			$('#assinatura_combo_id').val('0');
			bbAgdDefinirAcaoCombo('normal');
		}

		$('#modalServico').modal('show');
	}

	$(document).on('change', 'input[name="acao_combo_visual"]', function(){
		var acao = $(this).val() || 'normal';
		bbAgdDefinirAcaoCombo(acao);
	});

	function fecharServico(idAgendamento){
		bbAgdResetModalServico();

		$.ajax({
			url: 'paginas/' + pag + '/dados-servico.php',
			method: 'POST',
			dataType: 'json',
			data: {
				id_agd: idAgendamento,
				loja: $('#loja_agd').val()
			},
			success: function(resp){
				if(resp && resp.status === 'ok'){
					bbAgdAplicarDadosModal(resp);
				}else{
					var msg = (resp && resp.mensagem) ? resp.mensagem : 'Não foi possível carregar os dados do atendimento!';
					$('#mensagem-servico').addClass('text-danger').text(msg);
					$('#modalServico').modal('show');
				}
			},
			error: function(xhr){
				var msg = 'Não foi possível carregar os dados do atendimento!';
				if(xhr && xhr.responseText){
					msg = xhr.responseText;
				}
				$('#mensagem-servico').addClass('text-danger').text(msg);
				$('#modalServico').modal('show');
			}
		});

		return false;
	}

	$('#modalServico').on('hidden.bs.modal', function(){
		bbAgdResetModalServico();
		$('#mensagem-servico').removeClass('text-danger').text('');
	});
</script>

<script>
function bbAgdUrlPaginaPainel(nomePagina){
	nomePagina = (nomePagina || '').toString().replace(/[^a-z0-9_-]/ig, '');
	if(nomePagina === ''){
		nomePagina = 'manutencoes';
	}

	var pathname = (window.location && window.location.pathname) ? window.location.pathname : '';
	var search = (window.location && window.location.search) ? window.location.search : '';
	var loja = ($('#loja_agd').length ? $('#loja_agd').val() : '') || '';

	if (/^\/loja\/[^\/]+\/painel(?:\/[^\/?#]+)?\/?$/i.test(pathname)) {
		if (/\/painel\/[^\/?#]+\/?$/i.test(pathname)) {
			return pathname.replace(/\/[^\/?#]+\/?$/, '/' + nomePagina);
		}

		if (/\/painel\/?$/i.test(pathname)) {
			return pathname.replace(/\/?$/, '/' + nomePagina);
		}
	}

	if (pathname.indexOf('/sistema/painel') !== -1) {
		if (/\/index\.php$/i.test(pathname)) {
			if (search.indexOf('pag=') !== -1) {
				return pathname + search.replace(/([?&])pag=[^&]*/i, '$1pag=' + encodeURIComponent(nomePagina));
			}
			return pathname + '?pag=' + encodeURIComponent(nomePagina);
		}

		if (/\/sistema\/painel\/[^\/?#]+\/?$/i.test(pathname)) {
			return pathname.replace(/\/[^\/?#]+\/?$/, '/' + nomePagina);
		}

		return '/sistema/painel/index.php?pag=' + encodeURIComponent(nomePagina);
	}

	if (loja !== '') {
		return '/loja/' + encodeURIComponent(loja) + '/painel/' + nomePagina;
	}

	if (search.indexOf('pag=') !== -1) {
		return pathname + search.replace(/([?&])pag=[^&]*/i, '$1pag=' + encodeURIComponent(nomePagina));
	}

	return nomePagina;
}

	function bbAgdGerarManutencaoProteseAposConclusao(idAgendamento, callback){
		idAgendamento = parseInt(idAgendamento, 10);

		var retornoChamado = false;
		var continuar = function(avisoManutencao){
			if(retornoChamado){
				return;
			}
			retornoChamado = true;

			if(typeof callback === 'function'){
				callback(avisoManutencao || '');
			}
		};

		if(!idAgendamento || isNaN(idAgendamento)){
			continuar('');
			return;
		}

		$.ajax({
			url: 'paginas/' + pag + '/gerar-manutencao-protese.php',
			method: 'POST',
			dataType: 'json',
			timeout: 6000,
			data: {
				id_agd: idAgendamento,
				loja: $('#loja_agd').val(),
				cliente: $('#cliente_agd').val(),
				servico: $('#servico_agd').val(),
				servico_nome: $('#descricao_serv_agd').val(),
				data_pgto: $('#data_pgto').val()
			},
			success: function(resp){
				var aviso = '';

				if(resp && resp.criado){
					aviso = 'Manutenção de prótese gerada para aviso da próxima manutenção.';
				} else if(resp && resp.existente){
					aviso = 'Manutenção de prótese já estava gerada para este agendamento.';
				}

				continuar(aviso);
			},
			error: function(){
				// Não bloqueia a conclusão do atendimento.
				// Se houver falha, o agendamento segue salvo e o técnico pode conferir em Manutenções.
				continuar('');
			}
		});
	}

	$("#form-servico").submit(function () {
		event.preventDefault();

		var formData = new FormData(this);

		$.ajax({
			url: 'paginas/' + pag +  "/inserir-servico.php",
			type: 'POST',
			data: formData,

			success: function (mensagem) {
				var split = mensagem.split('*');
				$('#mensagem-servico').text('');
				$('#mensagem-servico').removeClass()
				if (split[0].trim() == "Salvo com Sucesso") {
					var idRecibo = (split[1] || '').trim();
					var avisoSucesso = (split[2] || '').trim();
					var paginaDestino = (split[3] || '').trim();
					var idAgendamentoAtual = ($('#id_agd').val() || '').toString().replace(/[^0-9]/g, '');

					var continuarAposManutencao = function(avisoManutencao){
						$('#btn-fechar-servico').click();
						listar();

						if(idRecibo !== ''){
							$('#id_conta_recibo').val(idRecibo);
							$("#btn_form_recibo").click();
						}

						var avisos = [];
						if(avisoSucesso !== ''){
							avisos.push(avisoSucesso);
						}
						if((avisoManutencao || '') !== ''){
							avisos.push(avisoManutencao);
						}

						if(avisos.length > 0){
							bbAgdMostrarAvisoSucesso(avisos.join(' '));
						}

						if(paginaDestino !== ''){
							setTimeout(function(){
								window.location = bbAgdUrlPaginaPainel(paginaDestino);
							}, 600);
						}
					};

					bbAgdGerarManutencaoProteseAposConclusao(idAgendamentoAtual, continuarAposManutencao);
				} else {
					$('#mensagem-servico').addClass('text-danger')
					$('#mensagem-servico').text(split[0])
				}

			},

			cache: false,
			contentType: false,
			processData: false,

		});

	});

</script>


<script type="text/javascript">
	function listarServicos(func){	
		var serv = $("#servico").val();
		
		$.ajax({
			url: 'paginas/' + pag +  "/listar-servicos.php",
			method: 'POST',
			data: {func},
			dataType: "text",

			success:function(result){
				$("#servico").html(result);
			}
		});
	}
</script>


<!-- FIX: Exclusão de agendamento (não depende de scripts quebrados) -->
<script type="text/javascript">
(function(){
    // Detecta slug em /loja/{slug}/... (compatível com Marketplace)
    function bbGetSlug(){
        var p = (window.location && window.location.pathname) ? window.location.pathname : '';
        var m = p.match(/^\/loja\/([^\/]+)\//i);
        return m ? m[1] : '';
    }

    // Excluir agendamento (chamado pelo listar.php)
    window.excluir = function(id, hora){
        try{
            id = parseInt(id, 10);
        }catch(e){
            id = 0;
        }

        if(!id || isNaN(id)){
            alert('ID inválido!');
            return false;
        }

        // segue o padrão do sistema (mesmo diretório dos outros AJAX)
        var url = 'paginas/' + pag + '/excluir.php';

        // envia também loja (slug) e id_agd (compatibilidade)
        var slug = bbGetSlug();

        $.ajax({
            url: url,
            method: 'POST',
            data: { id: id, id_agd: id, loja: slug },
            dataType: 'text',
            success: function(resp){
                resp = (resp || '').toString().trim();

                // padrões aceitos (alguns arquivos retornam OK, outros "Excluido com Sucesso")
                var ok = (resp === 'OK') ||
                         (resp.toLowerCase().indexOf('sucesso') !== -1) ||
                         (resp.toLowerCase().indexOf('exclu') !== -1);

                if(ok){
                    // Atualiza lista e horários sem recarregar
                    try{ listar(); }catch(e){}
                    try{ listarHorarios(); }catch(e){}
                    return;
                }

                alert(resp || 'Erro ao excluir!');
            },
            error: function(){
                alert('Erro ao excluir! Verifique sua conexão.');
            }
        });

        return false;
    };
})();
</script>
