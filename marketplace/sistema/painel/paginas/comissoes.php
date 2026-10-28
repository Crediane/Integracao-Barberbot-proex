<?php 
require_once __DIR__ . '/_context_bootstrap.php';
require_once __DIR__ . '/../verificar.php';
require_once __DIR__ . '/../../conexao.php';

$pag = 'comissoes';

//verificar se ele tem a permissão de estar nessa página
if(@$comissoes == 'ocultar'){
    // Redireciona respeitando o contexto (MASTER x TENANT)
    // - Se o painel estiver em /loja/<slug>/..., mantém o caminho relativo
    // - Se estiver em /sistema com contexto de loja (via ?loja= ou ref), preserva o ?loja=
    $bb_redirect = '../index.php';
    $bb_reqHasLoja = (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath);

    if (!empty($__bb_reqSlug) && !$bb_reqHasLoja) {
        $bb_redirect .= '?loja=' . urlencode($__bb_reqSlug);
    }

    echo "<script>window.location='{$bb_redirect}'</script>";
    exit();
}

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

<div class="bs-example widget-shadow" style="padding:15px">

	<div class="row">
		<div class="col-md-5" style="margin-bottom:5px;">
			<div style="float:left; margin-right:10px"><span><small><i title="Data de Vencimento Inicial" class="fa fa-calendar-o"></i></small></span></div>
			<div  style="float:left; margin-right:20px">
				<input type="date" class="form-control " name="data-inicial"  id="data-inicial-caixa" value="<?php echo $data_hoje ?>" required>
			</div>

			<div style="float:left; margin-right:10px"><span><small><i title="Data de Vencimento Final" class="fa fa-calendar-o"></i></small></span></div>
			<div  style="float:left; margin-right:30px">
				<input type="date" class="form-control " name="data-final"  id="data-final-caixa" value="<?php echo $data_hoje ?>" required>
			</div>
		</div>


		<div class="col-md-3">	
			<div class="form-group">			
				<select class="form-control sel2" id="funcionario" name="funcionario" style="width:100%;" onchange="listar()"> 
					<option value="">Filtrar Funcionário</option>
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

		<div class="col-md-3">
			<button onclick="baixarTudo()" type="button" class="btn btn-success"> Baixar Comissões</button>
		</div>
		
		<input type="hidden" id="buscar-contas">
	</div>

	<div class="row">
		<div class="col-md-2" align="center">	
			<div> 
				<small>
					<a title="Conta de Ontem" class="text-muted" href="#" onclick="valorData('<?php echo $data_ontem ?>', '<?php echo $data_ontem ?>')"><span>Ontem</span></a> / 
					<a title="Conta de Hoje" class="text-muted" href="#" onclick="valorData('<?php echo $data_hoje ?>', '<?php echo $data_hoje ?>')"><span>Hoje</span></a> / 
					<a title="Conta do Mês" class="text-muted" href="#" onclick="valorData('<?php echo $data_inicio_mes ?>', '<?php echo $data_final_mes ?>')"><span>Mês</span></a>
				</small>
			</div>
		</div>

		<div class="col-md-3" align="center">	
			<div> 
				<small>
					<a title="Todos os Serviços" class="text-muted" href="#" onclick="buscarContas('')"><span>Todos</span></a> / 
					<a title="Pendentes" class="text-muted" href="#" onclick="buscarContas('Não')"><span>Pendentes</span></a> / 
					<a title="Pagos" class="text-muted" href="#" onclick="buscarContas('Sim')"><span>Pagos</span></a>
				</small>
			</div>
		</div>
	</div>

	<hr>
	<div id="listar"></div>
	
</div>


<!-- Modal BaixarTudo-->
<div class="modal fade" id="modalBaixarTudo" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title">Pagar Comissões : <span id="titulo_inserir"></span></h4>
				<button id="btn-fechar" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
					<span aria-hidden="true" >&times;</span>
				</button>
			</div>
			
			<form id="form-excluir">
				<div class="modal-body">
					<div class="row">
						<div class="col-md-12">
							<div class="form-group">								
								<p>Você confirma o pagamento de R$ <b><span id="total_pgto"></span></b> reais num total de <span id="total_comissoes"></span> comissões Pendentes.</p>
							</div> 	
						</div>
					</div>

					<div class="row">
						<div class="col-md-8">
							<div class="form-group">
								<label for="exampleInputEmail1">Forma de Pagamento</label>
								<select class="form-control" id="pgto_baixar_todas" name="pgto" style="width:100%;" required> 
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

						<div class="col-md-4" style="margin-top: 20px">
							<button type="submit" class="btn btn-primary">Confirmar</button>
						</div>
					</div>
					
					<input type="hidden" name="id_funcionario" id="id_funcionario">
					<input type="hidden" name="data_inicial" id="data_inicial">
					<input type="hidden" name="data_final" id="data_final">

					<br>
					<small><div id="mensagem" align="center"></div></small>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- Modal Dados-->
<div class="modal fade" id="modalDados" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title" id="exampleModalLabel"><span id="nome_dados"></span></h4>
				<button id="btn-fechar-perfil" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
					<span aria-hidden="true" >&times;</span>
				</button>
			</div>
			
			<div class="modal-body">

				<div class="row" style="border-bottom: 1px solid #cac7c7;">
					<div class="col-md-6"><span><b>Valor : </b></span> <span id="valor_dados"></span></div>	
					<div class="col-md-6"><span><b>Data Lançamento: </b></span> <span id="data_lanc_dados"></span></div>
				</div>

				<div class="row" style="border-bottom: 1px solid #cac7c7;">
					<div class="col-md-6"><span><b>Data Vencimento: </b></span> <span id="data_venc_dados"></span></div>
					<div class="col-md-6"><span><b>Data PGTO: </b></span> <span id="data_pgto_dados"></span></div>
				</div>

				<div class="row" style="border-bottom: 1px solid #cac7c7;">
					<div class="col-md-6"><span><b>Usuário Lanc: </b></span> <span id="usuario_lanc_dados"></span></div>
					<div class="col-md-6"><span><b>Usuário Baixa: </b></span> <span id="usuario_baixa_dados"></span></div>
				</div>

				<div class="row" style="border-bottom: 1px solid #cac7c7;">		
					<div class="col-md-12"><span><b>Funcionário: </b></span> <span id="nome_func_dados"></span></div>
				</div>
				
				<div class="row" style="border-bottom: 1px solid #cac7c7;">
					<div class="col-md-6"><span><b>Tipo Chave: </b></span> <span id="tipo_chave_dados"></span></div>	
					<div class="col-md-6"><span><b>Chave Pix: </b></span> <span id="chave_pix_dados"></span></div>				
				</div>

				<div class="row" style="border-bottom: 1px solid #cac7c7;">
					<div class="col-md-12"><span><b>Forma de Pagamento: </b></span> <span id="pgto_dados"></span></div>
				</div>

				<div class="row">
					<div class="col-md-12" align="center">	
						<a id="link_mostrar" target="_blank" title="Clique para abrir o arquivo!">
							<img width="250px" id="target_mostrar">
						</a>	
					</div>					
				</div>

			</div>
		</div>
	</div>
</div>


<!-- Modal Baixar-->
<div class="modal fade" id="modalBaixar" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title"><span id="titulo_baixar"></span></h4>
				<button id="btn-fechar-baixar" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
					<span aria-hidden="true" >&times;</span>
				</button>
			</div>
			<form id="form-baixar">
				<div class="modal-body">						
					<div class="row">
						<div class="col-md-4">
							<div class="form-group">
								<label for="exampleInputEmail1">Valor</label>
								<input type="text" class="form-control" id="valor_baixar" name="valor" placeholder="Valor" required onkeyup="mascara_valor('valor_baixar')">    
							</div> 	
						</div>				
						
						<div class="col-md-4">
							<div class="form-group">
								<label for="exampleInputEmail1">Forma de Pagamento</label>
								<select class="form-control" id="pgto_baixar" name="pgto" style="width:100%;" required> 
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

						<div class="col-md-4">
							<div class="form-group">
								<label for="exampleInputEmail1">Pago Em</label>
								<input type="date" class="form-control" id="data_pgto_baixar" name="data_pgto"  value="<?php echo $data_hoje ?>">    
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

<script type="text/javascript">var pag = "<?=$pag?>"</script>
<script src="js/ajax.js"></script>

<script type="text/javascript">
	$(document).ready(function() {		
		$('.sel2').select2({});
		listar(); // carrega a listagem ao abrir a página
	});
</script>

<script type="text/javascript">
	function carregarImg() {
		var target = document.getElementById('target');
		var file = document.querySelector("#foto").files[0];

		var arquivo = file['name'];
		resultado = arquivo.split(".", 2);

		if(resultado[1] === 'pdf'){
			$('#target').attr('src', "img/pdf.png");
			return;
		}

		if(resultado[1] === 'rar' || resultado[1] === 'zip'){
			$('#target').attr('src', "img/rar.png");
			return;
		}

		var reader = new FileReader();
		reader.onloadend = function () { target.src = reader.result; };
		if (file) { reader.readAsDataURL(file); } else { target.src = ""; }
	}
</script>

<script type="text/javascript">
	function valorData(dataInicio, dataFinal){
		$('#data-inicial-caixa').val(dataInicio);
		$('#data-final-caixa').val(dataFinal);	
		listar();
	}
</script>

<script type="text/javascript">
	$('#data-inicial-caixa').change(function(){ listar(); });
	$('#data-final-caixa').change(function(){ listar(); });
</script>

<script type="text/javascript">
	function listar(){
		var dataInicial = $('#data-inicial-caixa').val();
		var dataFinal   = $('#data-final-caixa').val();	
		var status      = $('#buscar-contas').val();	
		var funcionario = $('#funcionario').val();
	
		$.ajax({
			url: 'paginas/' + pag + "/listar.php",
			method: 'POST',
			data: {dataInicial, dataFinal, status, funcionario},
			dataType: "html",
			success:function(result){
				$("#listar").html(result);
				$('#mensagem-excluir').text('');
			}
		});
	}
</script>

<script type="text/javascript">
	function buscarContas(status){
		$('#buscar-contas').val(status);
		listar();
	}
</script>

<script type="text/javascript">
	$("#form-baixar").submit(function () {
		$('#btn_baixar').hide();
		$('#mensagem-baixar').text('Baixando!!');

		event.preventDefault();
		var formData = new FormData(this);

		$.ajax({
			url: 'paginas/' + pag + "/baixar.php",
			type: 'POST',
			data: formData,
			success: function (mensagem) {
				$('#mensagem-baixar').text('');
				$('#mensagem-baixar').removeClass();
				if (mensagem.trim() == "Baixado com Sucesso") {
					$('#btn-fechar-baixar').click();
					listar();
				} else {
					$('#mensagem-baixar').addClass('text-danger')
					$('#mensagem-baixar').text(mensagem)
				}
				$('#btn_baixar').show();
			},
			cache: false,
			contentType: false,
			processData: false
		});
	});
</script>

<script type="text/javascript">
	function baixarTudo(){
		var funcionario = $('#funcionario').val();
		if(funcionario === '' || funcionario === null){
			alert('Selecione um Funcionário');
			return;
		}

		// Preenche os hidden com os filtros atuais (mesma lógica, só “amarra” o que já existe)
		$('#id_funcionario').val(funcionario);
		$('#data_inicial').val($('#data-inicial-caixa').val());
		$('#data_final').val($('#data-final-caixa').val());

		$('#mensagem').text('');    
		$('#modalBaixarTudo').modal('show');
		// se existir na tua base um limparCampos global, mantém; senão não atrapalha
		if (typeof limparCampos === 'function') { limparCampos(); }
	}
</script>

<script type="text/javascript">
	$("#form-excluir").submit(function () {
		event.preventDefault();
		var formData = new FormData(this);

		$.ajax({
			url: 'paginas/' + pag + "/baixar-todas.php",
			type: 'POST',
			data: formData,
			success: function (mensagem) {
				$('#mensagem').text('');
				$('#mensagem').removeClass();
				if (mensagem.trim() == "Baixado com Sucesso") {
					$('#btn-fechar').click();
					listar();
				} else {
					$('#mensagem').addClass('text-danger')
					$('#mensagem').text(mensagem)
				}
			},
			cache: false,
			contentType: false,
			processData: false
		});
	});
</script>
