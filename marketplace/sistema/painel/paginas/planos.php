<?php 
require_once __DIR__ . '/_context_bootstrap.php';
/**
 * Retorna o prefixo da instalação (caso esteja em subpasta).
 * Ex.: "/barberbot" quando instalado em "/barberbot/sistema/..."
 */
function bb_obter_prefixo_app(): string
{
	// Detecta prefixo quando o sistema está instalado em subpasta.
	// Ex.: "/barberbot" quando instalado em "/barberbot/loja/<slug>/painel/..."
	$req = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
	$script = $_SERVER['SCRIPT_NAME'] ?? '';

	$paths = [$req, $script];

	foreach ($paths as $p) {
		// ordem importa: primeiro /loja/, depois /sistema/, depois /painel/
		foreach (['/loja/', '/sistema/', '/painel/'] as $marker) {
			$pos = stripos($p, $marker);
			if ($pos !== false) {
				return rtrim(substr($p, 0, $pos), '/');
			}
		}
	}

	return '';
}

/**
 * Retorna o SLUG atual quando estiver em /loja/<slug>/...
 */
function bb_obter_slug_loja(): string
{
	$req = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
	if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $req, $m)) {
		return strtolower($m[1]);
	}
	// fallback: parametro loja (ajax/links antigos)
	if (!empty($_GET['loja'])) return strtolower(preg_replace('/[^a-z0-9_-]/i', '', (string)$_GET['loja']));
	if (!empty($_POST['loja'])) return strtolower(preg_replace('/[^a-z0-9_-]/i', '', (string)$_POST['loja']));
	if (!empty($_SESSION['tenant_slug'])) return strtolower(preg_replace('/[^a-z0-9_-]/i', '', (string)$_SESSION['tenant_slug']));
	return '';
}

/**
 * Monta URL base do Painel respeitando o contexto:
 * - Admin do sistema: /sistema/painel
 * - Dono da barbearia (tenant): /loja/<slug>/painel
 *
 * IMPORTANTE: isso evita que ações AJAX percam o slug e caiam no banco errado.
 */
function bb_url_painel(string $path = ''): string
{
	$prefixo = bb_obter_prefixo_app();
	$slug = bb_obter_slug_loja();

	// Se está dentro de /loja/<slug>/... usa painel do tenant
	if ($slug !== '') {
		$base = rtrim($prefixo . '/loja/' . rawurlencode($slug) . '/painel', '/');
	} else {
		// Caso contrário, mantém padrão do sistema
		$base = rtrim($prefixo . '/sistema/painel', '/');
	}

	$path = ltrim($path, '/');
	return $path === '' ? $base : $base . '/' . $path;
}

$bb_base_painel = bb_url_painel();

require_once __DIR__ . '/../verificar.php';
require_once __DIR__ . '/../../conexao.php';

$data_hoje = date('Y-m-d');

//verificar se ele tem a permissão de estar nessa página
if(@$planos == 'ocultar'){
    echo "<script>window.location='<?php echo $bb_painel_public_url; ?>/'</script>";
    exit();
}

$pag = 'planos';

?>


<div class="row">   
<div class="col-md-6">
	<a class="btn btn-primary" onclick="inserir()" class="btn btn-primary btn-flat btn-pri"><i class="fa fa-plus" aria-hidden="true"></i> Novo Plano / Assinatura</a>
</div>   
<div class="col-md-6" align="right">
	<a  class="btn btn-success btn-flat btn-pri" href="<?php echo htmlspecialchars($bb_base_painel . '/rel/assinaturas_class.php', ENT_QUOTES, 'UTF-8'); ?>" target="_blank"><i class="fa fa-file-pdf-o" aria-hidden="true"></i> Relatório</a>
</div>   
	
</div>

<div class="bs-example widget-shadow" style="padding:15px" id="listar">
	
</div>






<!-- Modal Inserir-->
<div class="modal fade" id="modalForm" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title"><span id="titulo_inserir"></span></h4>
				<button id="btn-fechar" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
					<span aria-hidden="true" >&times;</span>
				</button>
			</div>
			<form id="form">
			<div class="modal-body">

					<div class="row">

						<div class="col-md-6">
							
							<div class="form-group">
								<label for="exampleInputEmail1">Assinaturas</label>
								<select onchange="listarPlanos()" class="form-control sel2" id="grupo" name="grupo" style="width:100%;" >							
									<?php 
									$query = $pdo->query("SELECT * FROM grupo_assinaturas ORDER BY id asc");
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
								<label for="exampleInputEmail1">Planos</label>
								 <div id="listar_planos">
								 	
								 </div>
							</div> 	
						</div>

					</div>


					<div class="row">
						<div class="col-md-8">
							<div class="form-group">
								<label for="exampleInputEmail1">Cliente</label>
								<select class="form-control sel2" id="cliente" name="cliente" style="width:100%;" required>	
									<option value="">Selecionar Cliente</option>						
									<?php 
									$query = $pdo->query("SELECT * FROM clientes ORDER BY id desc");
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
								<label for="exampleInputEmail1">Valor</label>
								<input type="text" class="form-control" id="valor" name="valor" placeholder="Valor" >    
							</div> 	
						</div>

					</div>




					<div class="row">
						<div class="col-md-8">
							<div class="form-group">
								<label for="exampleInputEmail1">Frequência</label>
								<select class="form-control sel2" id="frequencia" name="frequencia" style="width:100%;" required>	
														
									<?php 
									$query = $pdo->query("SELECT * FROM frequencias ORDER BY id asc");
									$res = $query->fetchAll(PDO::FETCH_ASSOC);
									$total_reg = @count($res);
									if($total_reg > 0){
										for($i=0; $i < $total_reg; $i++){
										foreach ($res[$i] as $key => $value){}
										echo '<option value="'.$res[$i]['dias'].'">'.$res[$i]['frequencia'].'</option>';
										}
									}
									 ?>
									

								</select>
							</div> 	
						</div>
						<div class="col-md-4">

							<div class="form-group">
								<label for="exampleInputEmail1">Data</label>
								<input type="date" class="form-control" id="vencimento" name="vencimento" placeholder="Vencimento" value="<?php echo date('Y-m-d') ?>">    
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






<!-- Modal Baixar-->
<div class="modal fade" id="modalBaixar" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title"><span id="">Baixar Plano</span></h4>
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
								<input type="text" class="form-control" id="valor_baixar" name="valor" placeholder="Valor" required>    
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




<script type="text/javascript">var pag = "<?=$pag?>"; window.bbBasePainel = "<?php echo htmlspecialchars($bb_base_painel, ENT_QUOTES, 'UTF-8'); ?>";</script>
<script src="<?php echo htmlspecialchars($bb_base_painel . '/js/ajax.js', ENT_QUOTES, 'UTF-8'); ?>"></script>


<script type="text/javascript">
	$(document).ready(function() {
	listarPlanos();
		setTimeout(function() {
		  alterarValor();
		}, 500)
    $('.sel2').select2({
    	dropdownParent: $('#modalForm')
    });
});


function listarPlanos(){
	var grupo = $('#grupo').val();

	 $.ajax({
        url: (window.bbBasePainel || '') + '/paginas/' + pag + '/listar_planos.php',
        method: 'POST',
        data: {grupo},
        dataType: "html",

        success:function(result){
            $("#listar_planos").html(result);            
        }
    });
}

function alterarValor(){
	var item = $('#item').val();

	 $.ajax({
        url: (window.bbBasePainel || '') + '/paginas/' + pag + '/valor.php',
        method: 'POST',
        data: {item},
        dataType: "html",

        success:function(result){        	
            $("#valor").val(result);            
        }
    });
}
</script>





<script type="text/javascript">

	$("#form-baixar").submit(function () {

		$('#btn_baixar').hide();
		$('#mensagem-baixar').text('Baixando!!');

    event.preventDefault();
    var formData = new FormData(this);

    $.ajax({
        url: (window.bbBasePainel || '') + '/paginas/' + pag + '/baixar.php',
        type: 'POST',
        data: formData,

        success: function (mensagem) {
            $('#mensagem-baixar').text('');
            $('#mensagem-baixar').removeClass()
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
        processData: false,

    });

});


</script>