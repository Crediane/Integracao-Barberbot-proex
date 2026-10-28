<?php 
require_once __DIR__ . '/_context_bootstrap.php';
require_once __DIR__ . '/../verificar.php';
require_once __DIR__ . '/../../conexao.php';

$pag = 'clientes';

//verificar se ele tem a permissão de estar nessa página
if(@$clientes == 'ocultar'){
    echo "<script>window.location='<?php echo $bb_painel_public_url; ?>/'</script>";
    exit();
}

?>

 <div class="row top-50">
<div class="col-md-8 float-esq" >	
  <a class="btn btn-primary" onclick="inserir()" class="btn btn-primary btn-flat btn-pri"><i class="fa fa-plus" aria-hidden="true"></i> <span class="esc">Novo Cliente</span></a>
</div>
<div class="col-md-3 float-esq" >
	<input onkeyup="listarClientes(0)" class="form-control" type="text" name="buscar" id="buscar" placeholder="Buscar por CPF, Nome ou Telefone" style="border-radius: 5px">
	<input type="hidden" id="pagina">
</div>
<div class="col-md-1 float-esq" >
   <button type="button" onclick="listarClientes(0); return false;" id="btn-buscar" class="btn btn-primary"><i class="fa fa-search"></i></button>
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
			<form id="form_cli">
			<div class="modal-body">

					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label for="exampleInputEmail1">Nome</label>
								<input type="text" class="form-control" id="nome" name="nome" placeholder="Nome" required>    
							</div> 	
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label for="exampleInputEmail1">Telefone</label>
								<select class="form-control" id="telefone_pais" name="telefone_pais" style="width: 100%; margin-bottom: 6px;">
									<option value="br">Brasil (+55)</option>
								</select>
								<input type="hidden" id="telefone_ddi" name="telefone_ddi" value="55">
								<input type="hidden" id="telefone_pais_nome" name="telefone_pais_nome" value="Brasil">
								<input type="text" class="form-control" id="telefone" name="telefone" placeholder="Telefone" inputmode="tel" autocomplete="off">
								</div> 	
						</div>
					
					</div>

					<div class="row">

						<div class="col-md-7">
							<div class="form-group">
								<label for="exampleInputEmail1">Cpf</label>
								<input type="text" class="form-control" id="cpf" name="cpf" placeholder="CPF" >    
							</div> 	
						</div>

							<div class="col-md-5">
							<div class="form-group">
								<label for="exampleInputEmail1">Cartões</label>
								<input type="number" class="form-control" id="cartao" name="cartao"  value="0">    
							</div> 	
						</div>
					</div>

							

					<div class="row">
						<div class="col-md-8">
							<div class="form-group">
								<label for="exampleInputEmail1">Endereço</label>
								<input type="text" class="form-control" id="endereco" name="endereco" placeholder="Rua X Número 1 Bairro xxx" >    
							</div> 	
						</div>


						<div class="col-md-4">
							<div class="form-group">
								<label for="exampleInputEmail1">Nascimento</label>
								<input type="date" class="form-control" id="data_nasc" name="data_nasc" >    
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


<!-- Modal Cobranca -->
<div class="modal fade" id="modalCobranca" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title" id="exampleModalLabel"><span id="titulo_cob"></span></h4>
				<button id="btn-fechar-cob" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -25px">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<form id="form_cob">
			<div class="modal-body">

				<div class="row">
						<div class="col-md-6">							
								<label>Produtos Venda</label>
								<select class="form-control sel2" name="pro" id="pro" onchange="alterar()" style="width: 100%">	
								 <option value="">Selecione Um Produto</option>							
								<?php 
									$query = $pdo->query("SELECT * from produtos  order by id asc");
									$res = $query->fetchAll(PDO::FETCH_ASSOC);
									$linhas = @count($res);
									if($linhas > 0){
									for($i=0; $i<$linhas; $i++){
									
								 ?>

								  <option value="<?php echo $res[$i]['valor_venda'] ?>"><?php echo $res[$i]['nome'] ?></option>

								<?php } } ?>
									
								</select>								
						</div>

						<div class="col-md-6">							
								<label>Serviços</label>
								<select class="form-control sel6 " name="ser" id="ser" onchange="alterar2()" style="width: 100%">	
								 <option value="">Selecione Um Serviço</option>							
								<?php 
									$query = $pdo->query("SELECT * from servicos  order by id asc");
									$res = $query->fetchAll(PDO::FETCH_ASSOC);
									$linhas = @count($res);
									if($linhas > 0){
									for($i=0; $i<$linhas; $i++){
									
								 ?>

								  <option value="<?php echo $res[$i]['valor'] ?>"><?php echo $res[$i]['nome'] ?></option>

								<?php } } ?>
									
								</select>								
						</div>

						
					</div>
				

					<div class="row">
						<div class="col-md-3">							
								<label>Valor</label>
								<input type="text" class="form-control" id="valor_cob" name="valor" placeholder="Valor" onkeyup="mascara_valor('valor_cob')" required>							
						</div>

						<div class="col-md-5">							
								<label>Parcelas <small><small>(Não Obrigatória)</small></small></label>
								<input type="number" class="form-control" id="parcelas_cob" name="parcelas" placeholder="Parcelas" value="1">							
						</div>

						<div class="col-md-4">							
								<label>Vencimento</label>
								<input type="date" class="form-control" id="data_venc_cob" name="data_venc" placeholder="Data de Vencimento" value="<?php echo $data_atual ?>">							
						</div>

						
					</div>
				

					<div class="row">

						<div class="col-md-4">						
								<label>Frequência</label>
								<select class="form-control" name="frequencia" id="frequencia_cob">								
								<?php 
									$query = $pdo->query("SELECT * from frequencias  order by id asc");
									$res = $query->fetchAll(PDO::FETCH_ASSOC);
									$linhas = @count($res);
									if($linhas > 0){
									for($i=0; $i<$linhas; $i++){
								 ?>
								  <option value="<?php echo $res[$i]['dias'] ?>"><?php echo $res[$i]['frequencia'] ?></option>

								<?php } } ?>
									
								</select>	
						</div>

						<div class="col-md-8">							
								<label>Observações</label>
								<input type="text" class="form-control" id="obs_cob" name="obs" placeholder="Observações" >							
						</div>

						
					</div>
					<input type="hidden" class="form-control" id="id_cob" name="id">

				<br>
				<small><div id="mensagem_cob" align="center"></div></small>
			</div>
			<div class="modal-footer">       
				<button id="btn_cobranca" type="submit" class="btn btn-primary">Salvar</button>
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
					<div class="col-md-6">							
						<span><b>Telefone: </b></span>
						<span id="telefone_dados"></span>
					</div>	

					<div class="col-md-6">							
						<span><b>Cartões: </b></span>
						<span id="cartoes_dados"></span>							
					</div>
									

				</div>


				<div class="row" style="border-bottom: 1px solid #cac7c7;">
					<div class="col-md-6">							
						<span><b>Cadastro: </b></span>
						<span id="data_cad_dados"></span>							
					</div>
					<div class="col-md-6">							
						<span><b>Nascimento: </b></span>
						<span id="data_nasc_dados"></span>
					</div>					

				</div>


				<div class="row" style="border-bottom: 1px solid #cac7c7;">
					<div class="col-md-6">							
						<span><b>Data Retorno: </b></span>
						<span id="retorno_dados"></span>							
					</div>
					<div class="col-md-6">							
						<span><b>Último Serviço: </b></span>
						<span id="servico_dados"></span>
					</div>					

				</div>


				<div class="row" style="border-bottom: 1px solid #cac7c7;">
					
					<div class="col-md-12">							
						<span><b>Endereço: </b></span>
						<span id="endereco_dados"></span>
					</div>					

				</div>


				<br>

				<small><table class="table table-hover">
	<thead> 
	<tr> 
	<th>Último Serviço</th>	
	<th class="esc">Data</th> 
	<th class="esc">Valor</th> 	
	<th class="esc">OBS</th> 	
	
	</tr> 
	</thead> 
	<tbody>
	<td><span id="servico_dados_tab"></span></td>
	<td><span id="data_dados_tab"></span></td>
	<td><span id="valor_dados_tab"></span></td>
	<td><span id="obs_dados_tab"></span></td>
	</tbody>
	</table></small>

	<hr>

	<div id="listar-debitos">

	</div>

	<div class="row" style="overflow: scroll; height:200px; scrollbar-width: thin;">
					<div style="border-bottom: 1px solid #000"><small>Cobranças do Cliente</small></div>

					<small><div id="listar_cobrancas">
						
					</div></small>
				</div>
			

			</div>

			
		</div>
	</div>
</div>

<!-- Modal Parcelas -->
<div class="modal fade" id="modalParcelas" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">	
		<div class="modal-header">
				<h4 class="modal-title" id="exampleModalLabel"><span id="titulo_baixar">Parcelas</span></h4>
				<button id="btn-fechar-parcelas" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -25px">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>			
			<div class="modal-body">
						
				<small><div id="listar_parcelas" style="overflow: scroll; height:380px; scrollbar-width: thin;"></div></small>

				<input type="hidden" id="id_emprestimo">
				<input type="hidden" id="id_cobranca">						
			</div>
			
		</div>
	</div>
</div>

<!-- Modal Contrato-->
<div class="modal fade" id="modalContrato" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title" id="exampleModalLabel"><span id="titulo_contrato"></span></h4>
				<button id="btn-fechar-conta" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -25px">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>	
			<form id="form-contrato">	
			<div class="modal-body">

					<div>
						<textarea name="contrato" id="contrato" class="textareag"> </textarea>
					</div>
					<input type="hidden" name="id" id="id_contrato">

					<small><div id="mensagem-contrato" align="center"></div></small>
					
			</div>
			<div class="modal-footer">       
				<button type="submit" class="btn btn-primary">Gerar Relatório</button>
			</div>	
			</form>		

				

		</div>
	</div>
</div>


<!-- Modal Baixar -->
<div class="modal fade" id="modalBaixar" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title" id="exampleModalLabel"><span id="titulo_baixar">Baixar Parcela</span></h4>
				<button id="btn-fechar-baixar" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -25px">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<form id="form_baixar">
			<div class="modal-body">
				

					<div class="row">
						<div class="col-md-4">							
								<label>Valor</label>
								<input type="text" class="form-control" id="valor_baixar" name="valor_baixar" placeholder="Valor" readonly="">							
						</div>						
					

							<div class="col-md-4">							
								<label>Total Juros</label>
								<input type="text" class="form-control" id="juros_baixar" name="juros_baixar" placeholder="Júros se Houver" onkeyup="calcular()">							
						</div>

						<div class="col-md-4">							
								<label>Multa R$</label>
								<input type="text" class="form-control" id="multa_baixar" name="multa_baixar" placeholder="Multa se Houver" onkeyup="calcular()" >							
						</div>


						
					</div>

				
					<div class="row">

						<div class="col-md-4">							
								<label>Data Pgto</label>
								<input type="date" class="form-control" id="data_baixa" name="data_baixa" placeholder="Data de Pagamento" value="<?php echo $data_atual ?>">							
						</div>

						<div class="col-md-4">							
								<label>Forma Pgto..</label>
								<select name="forma_pgto" id="forma_pgto" class="form-control" onchange="calcular()" >
									<option value="">Selecionar Forma PGTO </option>
									<?php 
										$query = $pdo->query("SELECT * from formas_pgto order by id asc");
						$res = $query->fetchAll(PDO::FETCH_ASSOC);
						$linhas = @count($res);
						for($i=0; $i<$linhas; $i++){
							echo '<option value="'.$res[$i]['nome'].'">'.$res[$i]['nome'].'</option>';		
						}

									 ?>
								</select>
															
						</div>

						<div class="col-md-4">							
								<label>Valor Final</label>
								<input type="text" class="form-control" id="valor_final" name="valor_final" placeholder="Total Pago" onkeyup="mascara_valor('valor_final')" required>							
						</div>	

						
					</div>


					<div class="row" align="right">
						<div class="col-md-12">		
                            <input type="checkbox" class="form-checkbox" id="residuo" name="residuo" value="Sim" style="display:inline-block;">
                            <label style="display:inline-block;"><small>Resíduo Próxima Parcela</small></label>
                        </div>


						
					</div>

					<input type="hidden" class="form-control" id="id_baixar" name="id">					

				<br>
				<small><div id="mensagem_baixar" align="center"></div></small>
			</div>
			<div class="modal-footer">       
				<button id="btn_salvar_baixar" type="submit" class="btn btn-primary">Baixar</button>
			</div>
			</form>
		</div>
	</div>
</div>


<form action="rel/recibo_class.php" method="post" style="display:none" target="_blank">
    <input type="hidden" name="id" value="<?=$id_conta;?>" id="id_conta_recibo">
    <input type="hidden" name="enviar" value="Sim">
    <button id="btn_form" type="submit"></button>
</form>




<script type="text/javascript">
  var pag = "<?=$pag?>";
  window.pag = pag;
</script>
<script src="js/ajax.js"></script>
<script type="text/javascript">
  // Evita redirecionar para Home ao clicar nos ícones de ação (links com onclick)
  $(document).on('click', '#listar a', function(e){
    var hasOnclick = !!this.getAttribute('onclick');
    if(!hasOnclick) return;

    var href = (this.getAttribute('href') || '').trim();
    var target = (this.getAttribute('target') || '').trim();

    // não bloqueia links externos / nova aba
    if(target !== '' || href.startsWith('http') || href.startsWith('https') || href.startsWith('mailto:') || href.startsWith('tel:')) return;

    // se tiver onclick, não deixa o href navegar
    if(href === '' || href === '#' || !href.startsWith('javascript:')){
      e.preventDefault();
      e.stopPropagation();
      return false;
    }
  });
</script>


<script src="//js.nicedit.com/nicEdit-latest.js" type="text/javascript"></script>
<script type="text/javascript">bkLib.onDomLoaded(nicEditors.allTextAreas);</script>

<script type="text/javascript">
	$(document).ready( function () {
		listarClientes()
		 $('.sel2').select2({
		 	dropdownParent: $('#form_cob')
	      
	    });

		  $('.sel6').select2({
		 	dropdownParent: $('#form_cob')
	      
	    });
	} );

</script>


<script type="text/javascript">

function bbClienteObterSlugAtual(){
    var match = window.location.pathname.match(/\/loja\/([a-z0-9_-]+)(?:\/|$)/i);
    if (match && match[1]) {
        return match[1];
    }

    return '';
}

function bbClienteMontarDadosAjax(dados){
    dados = dados || {};

    var slugAtual = bbClienteObterSlugAtual();
    if (slugAtual !== '' && typeof dados.loja === 'undefined') {
        dados.loja = slugAtual;
    }

    return dados;
}

function listarClientes(pagina){
    var paginaAtual = 0;

    if (typeof pagina !== 'undefined' && pagina !== null && pagina !== '') {
        paginaAtual = parseInt(pagina, 10);
        if (isNaN(paginaAtual) || paginaAtual < 0) {
            paginaAtual = 0;
        }
    }

    $("#pagina").val(paginaAtual);

    var busca = ($("#buscar").val() || '').toString();

    $.ajax({
        url: 'paginas/' + pag + "/listar.php",
        method: 'POST',
        data: bbClienteMontarDadosAjax({busca: busca, pagina: paginaAtual}),
        dataType: "html",
        success:function(result){
            $("#listar").html(result);
        }
    });
}

function listarDebitos(id){
    $.ajax({
        url: 'paginas/' + pag + "/listar-debitos.php",
        method: 'POST',
        data: bbClienteMontarDadosAjax({id: id}),
        dataType: "html",
        success:function(result){
            $("#listar-debitos").html(result);
        }
    });
}
</script>




<script type="text/javascript">

$("#form_baixar").submit(function (event) {

	var id_emp = $('#id_emprestimo').val();
	var id_cob = $('#id_cobranca').val();
    event.preventDefault();
    var formData = new FormData(this);

    $('#mensagem_baixar').text('Carregando!!');
      $('#btn_salvar_baixar').hide();
  

    $.ajax({
        url: 'paginas/' + pag + "/baixar.php",
        type: 'POST',
        data: formData,

        success: function (mensagem) {
        	var split = mensagem.split("*");
            $('#mensagem_baixar').text('');
            $('#mensagem_baixar').removeClass()
            if (split[0].trim() == "Salvo com Sucesso") {
            	
                $('#btn-fechar-baixar').click();
                if(id_emp != ""){
                	 mostrarParcelasEmp(id_emp)
                }

                if(id_cob != ""){
                	 mostrarParcelas(id_cob);
                }

                $('#id_conta_recibo').val(split[1]);
                $("#btn_form").click();
                
               limparCampos();
                   	   

            } else {

                $('#mensagem_baixar').addClass('text-danger')
                $('#mensagem_baixar').text(split[0])
            }

              $('#btn_salvar_baixar').show();


        },

        cache: false,
        contentType: false,
        processData: false,

    });

});
</script>




<script type="text/javascript">
function bbClienteSomenteDigitos(valor){
	return (valor || '').toString().replace(/\D+/g, '');
}

function bbClienteNormalizarTextoBusca(valor){
	valor = (valor || '').toString().toLowerCase();
	if (valor.normalize) {
		valor = valor.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
	}
	return valor;
}

function bbClienteTemPrefixoInternacional(valor){
	valor = (valor || '').toString();
	return /^\s*\+/.test(valor) || /^\s*00/.test(valor);
}

function bbClienteEhTelefoneInternacional(valor){
	valor = (valor || '').toString();

	if (bbClienteTemPrefixoInternacional(valor)) {
		return true;
	}

	return bbClienteSomenteDigitos(valor).length > 11;
}

function bbClienteSanitizarTelefoneInternacional(valor){
	valor = (valor || '').toString();

	valor = valor.replace(/[^\d\+\s\-\(\)]/g, '');
	valor = valor.replace(/^\s*00/, '+');
	valor = valor.replace(/(?!^)\+/g, '');
	valor = valor.replace(/\s{2,}/g, ' ');

	return valor.trim();
}

function bbClienteNormalizarTelefoneInternacionalVisual(valor){
	valor = bbClienteSanitizarTelefoneInternacional(valor);

	if (valor === '') {
		return '';
	}

	if (/^\+/.test(valor)) {
		return valor;
	}

	if (/^\s*00/.test(valor)) {
		return valor.replace(/^\s*00/, '+');
	}

	var digitos = bbClienteSomenteDigitos(valor);

	if (digitos.length > 11) {
		return '+' + digitos;
	}

	return valor;
}

function bbClienteFormatarTelefoneBrasil(valor){
	var digitos = bbClienteSomenteDigitos(valor);

	if (digitos.length === 0) {
		return '';
	}

	if (digitos.length <= 2) {
		return '(' + digitos;
	}

	if (digitos.length <= 6) {
		return '(' + digitos.substring(0, 2) + ') ' + digitos.substring(2);
	}

	if (digitos.length <= 10) {
		return '(' + digitos.substring(0, 2) + ') ' + digitos.substring(2, 6) + '-' + digitos.substring(6);
	}

	return '(' + digitos.substring(0, 2) + ') ' + digitos.substring(2, 7) + '-' + digitos.substring(7, 11);
}

function bbClienteObterListaPaisesTelefone(){
	return [
    {"chave": "af", "pais": "Afeganistão", "ddi": "93", "formato": "+93 número local"},
    {"chave": "za", "pais": "África do Sul", "ddi": "27", "formato": "+27 número local"},
    {"chave": "al", "pais": "Albânia", "ddi": "355", "formato": "+355 número local"},
    {"chave": "de", "pais": "Alemanha", "ddi": "49", "formato": "+49 número local"},
    {"chave": "ao", "pais": "Angola", "ddi": "244", "formato": "+244 número local"},
    {"chave": "ai", "pais": "Anguila", "ddi": "1264", "formato": "+1264 número local"},
    {"chave": "ag", "pais": "Antígua e Barbuda", "ddi": "1268", "formato": "+1268 número local"},
    {"chave": "sa", "pais": "Arábia Saudita", "ddi": "966", "formato": "+966 número local"},
    {"chave": "dz", "pais": "Argélia", "ddi": "213", "formato": "+213 número local"},
    {"chave": "ar", "pais": "Argentina", "ddi": "54", "formato": "+54 9 11 1234 5678"},
    {"chave": "am", "pais": "Armênia", "ddi": "374", "formato": "+374 número local"},
    {"chave": "aw", "pais": "Aruba", "ddi": "297", "formato": "+297 número local"},
    {"chave": "au", "pais": "Austrália", "ddi": "61", "formato": "+61 número local"},
    {"chave": "at", "pais": "Áustria", "ddi": "43", "formato": "+43 número local"},
    {"chave": "az", "pais": "Azerbaijão", "ddi": "994", "formato": "+994 número local"},
    {"chave": "bs", "pais": "Bahamas", "ddi": "1242", "formato": "+1242 número local"},
    {"chave": "bd", "pais": "Bangladesh", "ddi": "880", "formato": "+880 número local"},
    {"chave": "bb", "pais": "Barbados", "ddi": "1246", "formato": "+1246 número local"},
    {"chave": "bh", "pais": "Barein", "ddi": "973", "formato": "+973 número local"},
    {"chave": "be", "pais": "Bélgica", "ddi": "32", "formato": "+32 número local"},
    {"chave": "bz", "pais": "Belize", "ddi": "501", "formato": "+501 número local"},
    {"chave": "bj", "pais": "Benin", "ddi": "229", "formato": "+229 número local"},
    {"chave": "bm", "pais": "Bermudas", "ddi": "1441", "formato": "+1441 número local"},
    {"chave": "by", "pais": "Bielorrússia", "ddi": "375", "formato": "+375 número local"},
    {"chave": "bo", "pais": "Bolívia", "ddi": "591", "formato": "+591 número local"},
    {"chave": "ba", "pais": "Bósnia e Herzegovina", "ddi": "387", "formato": "+387 número local"},
    {"chave": "bw", "pais": "Botsuana", "ddi": "267", "formato": "+267 número local"},
    {"chave": "br", "pais": "Brasil", "ddi": "55", "formato": "+55 (XX) XXXXX-XXXX"},
    {"chave": "bn", "pais": "Brunei", "ddi": "673", "formato": "+673 número local"},
    {"chave": "bg", "pais": "Bulgária", "ddi": "359", "formato": "+359 número local"},
    {"chave": "bf", "pais": "Burquina Faso", "ddi": "226", "formato": "+226 número local"},
    {"chave": "bi", "pais": "Burundi", "ddi": "257", "formato": "+257 número local"},
    {"chave": "bt", "pais": "Butão", "ddi": "975", "formato": "+975 número local"},
    {"chave": "cv", "pais": "Cabo Verde", "ddi": "238", "formato": "+238 número local"},
    {"chave": "cm", "pais": "Camarões", "ddi": "237", "formato": "+237 número local"},
    {"chave": "kh", "pais": "Camboja", "ddi": "855", "formato": "+855 número local"},
    {"chave": "ca", "pais": "Canadá", "ddi": "1", "formato": "+1 número local"},
    {"chave": "qa", "pais": "Catar", "ddi": "974", "formato": "+974 número local"},
    {"chave": "kz", "pais": "Cazaquistão", "ddi": "76", "formato": "+76 número local"},
    {"chave": "td", "pais": "Chade", "ddi": "235", "formato": "+235 número local"},
    {"chave": "cl", "pais": "Chile", "ddi": "56", "formato": "+56 número local"},
    {"chave": "cn", "pais": "China", "ddi": "86", "formato": "+86 136 1199 6578"},
    {"chave": "cy", "pais": "Chipre", "ddi": "357", "formato": "+357 número local"},
    {"chave": "co", "pais": "Colômbia", "ddi": "57", "formato": "+57 número local"},
    {"chave": "km", "pais": "Comores", "ddi": "269", "formato": "+269 número local"},
    {"chave": "cd", "pais": "Congo - Kinshasa", "ddi": "243", "formato": "+243 número local"},
    {"chave": "kp", "pais": "Coreia do Norte", "ddi": "850", "formato": "+850 número local"},
    {"chave": "kr", "pais": "Coreia do Sul", "ddi": "82", "formato": "+82 número local"},
    {"chave": "ci", "pais": "Costa do Marfim", "ddi": "225", "formato": "+225 número local"},
    {"chave": "cr", "pais": "Costa Rica", "ddi": "506", "formato": "+506 número local"},
    {"chave": "hr", "pais": "Croácia", "ddi": "385", "formato": "+385 número local"},
    {"chave": "cu", "pais": "Cuba", "ddi": "53", "formato": "+53 número local"},
    {"chave": "dk", "pais": "Dinamarca", "ddi": "45", "formato": "+45 número local"},
    {"chave": "dj", "pais": "Djibuti", "ddi": "253", "formato": "+253 número local"},
    {"chave": "dm", "pais": "Dominica", "ddi": "1767", "formato": "+1767 número local"},
    {"chave": "eg", "pais": "Egito", "ddi": "20", "formato": "+20 número local"},
    {"chave": "sv", "pais": "El Salvador", "ddi": "503", "formato": "+503 número local"},
    {"chave": "ae", "pais": "Emirados Árabes Unidos", "ddi": "971", "formato": "+971 número local"},
    {"chave": "ec", "pais": "Equador", "ddi": "593", "formato": "+593 número local"},
    {"chave": "er", "pais": "Eritreia", "ddi": "291", "formato": "+291 número local"},
    {"chave": "sk", "pais": "Eslováquia", "ddi": "421", "formato": "+421 número local"},
    {"chave": "si", "pais": "Eslovênia", "ddi": "386", "formato": "+386 número local"},
    {"chave": "es", "pais": "Espanha", "ddi": "34", "formato": "+34 número local"},
    {"chave": "sz", "pais": "Essuatíni", "ddi": "268", "formato": "+268 número local"},
    {"chave": "us", "pais": "Estados Unidos", "ddi": "1", "formato": "+1 (XXX) XXX-XXXX"},
    {"chave": "ee", "pais": "Estônia", "ddi": "372", "formato": "+372 número local"},
    {"chave": "et", "pais": "Etiópia", "ddi": "251", "formato": "+251 número local"},
    {"chave": "fj", "pais": "Fiji", "ddi": "679", "formato": "+679 número local"},
    {"chave": "ph", "pais": "Filipinas", "ddi": "63", "formato": "+63 número local"},
    {"chave": "fi", "pais": "Finlândia", "ddi": "358", "formato": "+358 número local"},
    {"chave": "fr", "pais": "França", "ddi": "33", "formato": "+33 número local"},
    {"chave": "ga", "pais": "Gabão", "ddi": "241", "formato": "+241 número local"},
    {"chave": "gm", "pais": "Gâmbia", "ddi": "220", "formato": "+220 número local"},
    {"chave": "gh", "pais": "Gana", "ddi": "233", "formato": "+233 número local"},
    {"chave": "ge", "pais": "Geórgia", "ddi": "995", "formato": "+995 número local"},
    {"chave": "gi", "pais": "Gibraltar", "ddi": "350", "formato": "+350 número local"},
    {"chave": "gd", "pais": "Granada", "ddi": "1473", "formato": "+1473 número local"},
    {"chave": "gr", "pais": "Grécia", "ddi": "30", "formato": "+30 número local"},
    {"chave": "gl", "pais": "Groenlândia", "ddi": "299", "formato": "+299 número local"},
    {"chave": "gp", "pais": "Guadalupe", "ddi": "590", "formato": "+590 número local"},
    {"chave": "gu", "pais": "Guam", "ddi": "1671", "formato": "+1671 número local"},
    {"chave": "gt", "pais": "Guatemala", "ddi": "502", "formato": "+502 número local"},
    {"chave": "gg", "pais": "Guernsey", "ddi": "44", "formato": "+44 número local"},
    {"chave": "gy", "pais": "Guiana", "ddi": "592", "formato": "+592 número local"},
    {"chave": "gf", "pais": "Guiana Francesa", "ddi": "594", "formato": "+594 número local"},
    {"chave": "gn", "pais": "Guiné", "ddi": "224", "formato": "+224 número local"},
    {"chave": "gq", "pais": "Guiné Equatorial", "ddi": "240", "formato": "+240 número local"},
    {"chave": "gw", "pais": "Guiné-Bissau", "ddi": "245", "formato": "+245 número local"},
    {"chave": "ht", "pais": "Haiti", "ddi": "509", "formato": "+509 número local"},
    {"chave": "hn", "pais": "Honduras", "ddi": "504", "formato": "+504 número local"},
    {"chave": "hk", "pais": "Hong Kong, RAE da China", "ddi": "852", "formato": "+852 número local"},
    {"chave": "hu", "pais": "Hungria", "ddi": "36", "formato": "+36 número local"},
    {"chave": "ye", "pais": "Iêmen", "ddi": "967", "formato": "+967 número local"},
    {"chave": "cx", "pais": "Ilha Christmas", "ddi": "61", "formato": "+61 número local"},
    {"chave": "im", "pais": "Ilha de Man", "ddi": "44", "formato": "+44 número local"},
    {"chave": "nf", "pais": "Ilha Norfolk", "ddi": "672", "formato": "+672 número local"},
    {"chave": "ky", "pais": "Ilhas Cayman", "ddi": "1345", "formato": "+1345 número local"},
    {"chave": "cc", "pais": "Ilhas Cocos (Keeling)", "ddi": "61", "formato": "+61 número local"},
    {"chave": "ck", "pais": "Ilhas Cook", "ddi": "682", "formato": "+682 número local"},
    {"chave": "fo", "pais": "Ilhas Faroé", "ddi": "298", "formato": "+298 número local"},
    {"chave": "gs", "pais": "Ilhas Geórgia do Sul e Sandwich do Sul", "ddi": "500", "formato": "+500 número local"},
    {"chave": "fk", "pais": "Ilhas Malvinas", "ddi": "500", "formato": "+500 número local"},
    {"chave": "mp", "pais": "Ilhas Marianas do Norte", "ddi": "1670", "formato": "+1670 número local"},
    {"chave": "mh", "pais": "Ilhas Marshall", "ddi": "692", "formato": "+692 número local"},
    {"chave": "pn", "pais": "Ilhas Pitcairn", "ddi": "64", "formato": "+64 número local"},
    {"chave": "sb", "pais": "Ilhas Salomão", "ddi": "677", "formato": "+677 número local"},
    {"chave": "in", "pais": "Índia", "ddi": "91", "formato": "+91 número local"},
    {"chave": "id", "pais": "Indonésia", "ddi": "62", "formato": "+62 número local"},
    {"chave": "ir", "pais": "Irã", "ddi": "98", "formato": "+98 número local"},
    {"chave": "iq", "pais": "Iraque", "ddi": "964", "formato": "+964 número local"},
    {"chave": "ie", "pais": "Irlanda", "ddi": "353", "formato": "+353 número local"},
    {"chave": "is", "pais": "Islândia", "ddi": "354", "formato": "+354 número local"},
    {"chave": "il", "pais": "Israel", "ddi": "972", "formato": "+972 número local"},
    {"chave": "it", "pais": "Itália", "ddi": "39", "formato": "+39 número local"},
    {"chave": "jm", "pais": "Jamaica", "ddi": "1876", "formato": "+1876 número local"},
    {"chave": "jp", "pais": "Japão", "ddi": "81", "formato": "+81 número local"},
    {"chave": "je", "pais": "Jersey", "ddi": "44", "formato": "+44 número local"},
    {"chave": "jo", "pais": "Jordânia", "ddi": "962", "formato": "+962 número local"},
    {"chave": "kw", "pais": "Kuwait", "ddi": "965", "formato": "+965 número local"},
    {"chave": "la", "pais": "Laos", "ddi": "856", "formato": "+856 número local"},
    {"chave": "ls", "pais": "Lesoto", "ddi": "266", "formato": "+266 número local"},
    {"chave": "lv", "pais": "Letônia", "ddi": "371", "formato": "+371 número local"},
    {"chave": "lb", "pais": "Líbano", "ddi": "961", "formato": "+961 número local"},
    {"chave": "lr", "pais": "Libéria", "ddi": "231", "formato": "+231 número local"},
    {"chave": "ly", "pais": "Líbia", "ddi": "218", "formato": "+218 número local"},
    {"chave": "li", "pais": "Liechtenstein", "ddi": "423", "formato": "+423 número local"},
    {"chave": "lt", "pais": "Lituânia", "ddi": "370", "formato": "+370 número local"},
    {"chave": "lu", "pais": "Luxemburgo", "ddi": "352", "formato": "+352 número local"},
    {"chave": "mo", "pais": "Macau, RAE da China", "ddi": "853", "formato": "+853 número local"},
    {"chave": "mk", "pais": "Macedônia do Norte", "ddi": "389", "formato": "+389 número local"},
    {"chave": "mg", "pais": "Madagascar", "ddi": "261", "formato": "+261 número local"},
    {"chave": "my", "pais": "Malásia", "ddi": "60", "formato": "+60 número local"},
    {"chave": "mw", "pais": "Malaui", "ddi": "265", "formato": "+265 número local"},
    {"chave": "mv", "pais": "Maldivas", "ddi": "960", "formato": "+960 número local"},
    {"chave": "ml", "pais": "Mali", "ddi": "223", "formato": "+223 número local"},
    {"chave": "mt", "pais": "Malta", "ddi": "356", "formato": "+356 número local"},
    {"chave": "ma", "pais": "Marrocos", "ddi": "212", "formato": "+212 número local"},
    {"chave": "mq", "pais": "Martinica", "ddi": "596", "formato": "+596 número local"},
    {"chave": "mu", "pais": "Maurício", "ddi": "230", "formato": "+230 número local"},
    {"chave": "mr", "pais": "Mauritânia", "ddi": "222", "formato": "+222 número local"},
    {"chave": "yt", "pais": "Mayotte", "ddi": "262", "formato": "+262 número local"},
    {"chave": "mx", "pais": "México", "ddi": "52", "formato": "+52 número local"},
    {"chave": "fm", "pais": "Micronésia", "ddi": "691", "formato": "+691 número local"},
    {"chave": "mz", "pais": "Moçambique", "ddi": "258", "formato": "+258 número local"},
    {"chave": "md", "pais": "Moldávia", "ddi": "373", "formato": "+373 número local"},
    {"chave": "mc", "pais": "Mônaco", "ddi": "377", "formato": "+377 número local"},
    {"chave": "mn", "pais": "Mongólia", "ddi": "976", "formato": "+976 número local"},
    {"chave": "ms", "pais": "Montserrat", "ddi": "1664", "formato": "+1664 número local"},
    {"chave": "na", "pais": "Namíbia", "ddi": "264", "formato": "+264 número local"},
    {"chave": "nr", "pais": "Nauru", "ddi": "674", "formato": "+674 número local"},
    {"chave": "np", "pais": "Nepal", "ddi": "977", "formato": "+977 número local"},
    {"chave": "ni", "pais": "Nicarágua", "ddi": "505", "formato": "+505 número local"},
    {"chave": "ne", "pais": "Níger", "ddi": "227", "formato": "+227 número local"},
    {"chave": "ng", "pais": "Nigéria", "ddi": "234", "formato": "+234 número local"},
    {"chave": "nu", "pais": "Niue", "ddi": "683", "formato": "+683 número local"},
    {"chave": "no", "pais": "Noruega", "ddi": "47", "formato": "+47 número local"},
    {"chave": "nc", "pais": "Nova Caledônia", "ddi": "687", "formato": "+687 número local"},
    {"chave": "nz", "pais": "Nova Zelândia", "ddi": "64", "formato": "+64 número local"},
    {"chave": "om", "pais": "Omã", "ddi": "968", "formato": "+968 número local"},
    {"chave": "nl", "pais": "Países Baixos", "ddi": "31", "formato": "+31 número local"},
    {"chave": "pw", "pais": "Palau", "ddi": "680", "formato": "+680 número local"},
    {"chave": "pa", "pais": "Panamá", "ddi": "507", "formato": "+507 número local"},
    {"chave": "pg", "pais": "Papua-Nova Guiné", "ddi": "675", "formato": "+675 número local"},
    {"chave": "pk", "pais": "Paquistão", "ddi": "92", "formato": "+92 número local"},
    {"chave": "py", "pais": "Paraguai", "ddi": "595", "formato": "+595 X XX XXXXXX"},
    {"chave": "pe", "pais": "Peru", "ddi": "51", "formato": "+51 número local"},
    {"chave": "pf", "pais": "Polinésia Francesa", "ddi": "689", "formato": "+689 número local"},
    {"chave": "pl", "pais": "Polônia", "ddi": "48", "formato": "+48 número local"},
    {"chave": "pr", "pais": "Porto Rico", "ddi": "1787", "formato": "+1787 número local"},
    {"chave": "pt", "pais": "Portugal", "ddi": "351", "formato": "+351 XXX XXX XXX"},
    {"chave": "ke", "pais": "Quênia", "ddi": "254", "formato": "+254 número local"},
    {"chave": "kg", "pais": "Quirguistão", "ddi": "996", "formato": "+996 número local"},
    {"chave": "ki", "pais": "Quiribati", "ddi": "686", "formato": "+686 número local"},
    {"chave": "gb", "pais": "Reino Unido", "ddi": "44", "formato": "+44 número local"},
    {"chave": "cf", "pais": "República Centro-Africana", "ddi": "236", "formato": "+236 número local"},
    {"chave": "cg", "pais": "República do Congo", "ddi": "242", "formato": "+242 número local"},
    {"chave": "do", "pais": "República Dominicana", "ddi": "1809", "formato": "+1809 número local"},
    {"chave": "re", "pais": "Reunião", "ddi": "262", "formato": "+262 número local"},
    {"chave": "ro", "pais": "Romênia", "ddi": "40", "formato": "+40 número local"},
    {"chave": "rw", "pais": "Ruanda", "ddi": "250", "formato": "+250 número local"},
    {"chave": "ru", "pais": "Rússia", "ddi": "7", "formato": "+7 número local"},
    {"chave": "eh", "pais": "Saara Ocidental", "ddi": "212", "formato": "+212 número local"},
    {"chave": "ws", "pais": "Samoa", "ddi": "685", "formato": "+685 número local"},
    {"chave": "as", "pais": "Samoa Americana", "ddi": "1684", "formato": "+1684 número local"},
    {"chave": "sm", "pais": "San Marino", "ddi": "378", "formato": "+378 número local"},
    {"chave": "sh", "pais": "Santa Helena", "ddi": "290", "formato": "+290 número local"},
    {"chave": "lc", "pais": "Santa Lúcia", "ddi": "1758", "formato": "+1758 número local"},
    {"chave": "kn", "pais": "São Cristóvão e Névis", "ddi": "1869", "formato": "+1869 número local"},
    {"chave": "pm", "pais": "São Pedro e Miquelão", "ddi": "508", "formato": "+508 número local"},
    {"chave": "st", "pais": "São Tomé e Príncipe", "ddi": "239", "formato": "+239 número local"},
    {"chave": "vc", "pais": "São Vicente e Granadinas", "ddi": "1784", "formato": "+1784 número local"},
    {"chave": "sc", "pais": "Seicheles", "ddi": "248", "formato": "+248 número local"},
    {"chave": "sn", "pais": "Senegal", "ddi": "221", "formato": "+221 número local"},
    {"chave": "sl", "pais": "Serra Leoa", "ddi": "232", "formato": "+232 número local"},
    {"chave": "rs", "pais": "Sérvia", "ddi": "381", "formato": "+381 número local"},
    {"chave": "sg", "pais": "Singapura", "ddi": "65", "formato": "+65 número local"},
    {"chave": "sy", "pais": "Síria", "ddi": "963", "formato": "+963 número local"},
    {"chave": "so", "pais": "Somália", "ddi": "252", "formato": "+252 número local"},
    {"chave": "lk", "pais": "Sri Lanka", "ddi": "94", "formato": "+94 número local"},
    {"chave": "sd", "pais": "Sudão", "ddi": "249", "formato": "+249 número local"},
    {"chave": "ss", "pais": "Sudão do Sul", "ddi": "211", "formato": "+211 número local"},
    {"chave": "se", "pais": "Suécia", "ddi": "46", "formato": "+46 número local"},
    {"chave": "ch", "pais": "Suíça", "ddi": "41", "formato": "+41 número local"},
    {"chave": "sr", "pais": "Suriname", "ddi": "597", "formato": "+597 número local"},
    {"chave": "sj", "pais": "Svalbard e Jan Mayen", "ddi": "4779", "formato": "+4779 número local"},
    {"chave": "tj", "pais": "Tadjiquistão", "ddi": "992", "formato": "+992 número local"},
    {"chave": "th", "pais": "Tailândia", "ddi": "66", "formato": "+66 número local"},
    {"chave": "tw", "pais": "Taiwan", "ddi": "886", "formato": "+886 número local"},
    {"chave": "tz", "pais": "Tanzânia", "ddi": "255", "formato": "+255 número local"},
    {"chave": "cz", "pais": "Tchéquia", "ddi": "420", "formato": "+420 número local"},
    {"chave": "io", "pais": "Território Britânico do Oceano Índico", "ddi": "246", "formato": "+246 número local"},
    {"chave": "tl", "pais": "Timor-Leste", "ddi": "670", "formato": "+670 número local"},
    {"chave": "tg", "pais": "Togo", "ddi": "228", "formato": "+228 número local"},
    {"chave": "tk", "pais": "Tokelau", "ddi": "690", "formato": "+690 número local"},
    {"chave": "to", "pais": "Tonga", "ddi": "676", "formato": "+676 número local"},
    {"chave": "tt", "pais": "Trinidad e Tobago", "ddi": "1868", "formato": "+1868 número local"},
    {"chave": "tn", "pais": "Tunísia", "ddi": "216", "formato": "+216 número local"},
    {"chave": "tm", "pais": "Turcomenistão", "ddi": "993", "formato": "+993 número local"},
    {"chave": "tr", "pais": "Turquia", "ddi": "90", "formato": "+90 número local"},
    {"chave": "tv", "pais": "Tuvalu", "ddi": "688", "formato": "+688 número local"},
    {"chave": "ua", "pais": "Ucrânia", "ddi": "380", "formato": "+380 número local"},
    {"chave": "ug", "pais": "Uganda", "ddi": "256", "formato": "+256 número local"},
    {"chave": "uy", "pais": "Uruguai", "ddi": "598", "formato": "+598 número local"},
    {"chave": "uz", "pais": "Uzbequistão", "ddi": "998", "formato": "+998 número local"},
    {"chave": "vu", "pais": "Vanuatu", "ddi": "678", "formato": "+678 número local"},
    {"chave": "ve", "pais": "Venezuela", "ddi": "58", "formato": "+58 número local"},
    {"chave": "vn", "pais": "Vietnã", "ddi": "84", "formato": "+84 número local"},
    {"chave": "wf", "pais": "Wallis e Futuna", "ddi": "681", "formato": "+681 número local"},
    {"chave": "zm", "pais": "Zâmbia", "ddi": "260", "formato": "+260 número local"},
    {"chave": "zw", "pais": "Zimbábue", "ddi": "263", "formato": "+263 número local"},
    {"chave": "outro", "pais": "Outro país (manual)", "ddi": "", "formato": "+DDI número local"}
];
}

function bbClienteObterMapaPaisesTelefone(){
	var mapa = {};
	var lista = bbClienteObterListaPaisesTelefone();

	for (var i = 0; i < lista.length; i++) {
		mapa[lista[i].chave] = lista[i];
	}

	return mapa;
}

function bbClienteObterInfoPaisTelefone(chave){
	var mapa = bbClienteObterMapaPaisesTelefone();
	if (mapa[chave]) {
		return mapa[chave];
	}

	return mapa.outro;
}

function bbClienteObterPaisSelecionadoTelefone(){
	var $select = $('#telefone_pais');
	if (!$select.length) {
		return 'br';
	}

	var valor = ($select.val() || 'br').toString();
	return valor !== '' ? valor : 'br';
}

function bbClienteMontarTextoOpcaoPaisTelefone(item){
	if (item.ddi === '') {
		return item.pais;
	}

	return item.pais + ' (+' + item.ddi + ')';
}

function bbClienteSelectPaisTemBuscaInterna(){
	return typeof $.fn.select2 === 'function';
}

function bbClienteAtualizarUiSelectPaisTelefone(){
	var $select = $('#telefone_pais');
	if (!$select.length) {
		return;
	}

	bbClienteAtualizarHiddenPaisTelefone();

	if (bbClienteSelectPaisTemBuscaInterna() && $select.hasClass('select2-hidden-accessible')) {
		$select.trigger('change.select2');
	}
}

function bbClienteAtualizarHiddenPaisTelefone(){
	var $select = $('#telefone_pais');
	var $ddi = $('#telefone_ddi');
	var $paisNome = $('#telefone_pais_nome');

	if (!$select.length) {
		return;
	}

	var chave = ($select.val() || 'br').toString();
	var info = bbClienteObterInfoPaisTelefone(chave);

	if ($ddi.length) {
		$ddi.val(info.ddi || '');
	}

	if ($paisNome.length) {
		$paisNome.val(info.pais || '');
	}
}

function bbClienteIniciarSelectPaisTelefone(){
	var $select = $('#telefone_pais');
	if (!$select.length) {
		return;
	}

	if (!bbClienteSelectPaisTemBuscaInterna()) {
		bbClienteAtualizarHiddenPaisTelefone();
		return;
	}

	if ($select.hasClass('select2-hidden-accessible')) {
		$select.select2('destroy');
	}

	$select.select2({
		dropdownParent: $('#modalForm'),
		width: '100%',
		placeholder: 'Buscar país ou DDI',
		allowClear: false,
		language: {
			noResults: function(){
				return 'Nenhum país encontrado';
			}
		}
	});

	bbClienteAtualizarHiddenPaisTelefone();
	$select.trigger('change.select2');
}

function bbClientePreencherSelectPaisesTelefone(filtro, valorSelecionado){
	var $select = $('#telefone_pais');
	if (!$select.length) {
		return;
	}

	var lista = bbClienteObterListaPaisesTelefone();
	var html = '';

	for (var i = 0; i < lista.length; i++) {
		var item = lista[i];
		html += '<option value="' + item.chave + '" data-ddi="' + item.ddi + '" data-pais="' + item.pais.replace(/"/g, '&quot;') + '">' + bbClienteMontarTextoOpcaoPaisTelefone(item) + '</option>';
	}

	$select.html(html);

	if (valorSelecionado && $select.find('option[value="' + valorSelecionado + '"]').length) {
		$select.val(valorSelecionado);
	} else if ($select.find('option[value="br"]').length) {
		$select.val('br');
	} else {
		$select.val($select.find('option:first').val() || '');
	}

	bbClienteAtualizarUiSelectPaisTelefone();
}

function bbClienteGarantirPaisSelecionadoNoSelect(valorSelecionado){
	bbClientePreencherSelectPaisesTelefone('', valorSelecionado);

	if (valorSelecionado && !$('#telefone_pais').find('option[value="' + valorSelecionado + '"]').length) {
		bbClientePreencherSelectPaisesTelefone('', valorSelecionado);
	}
}

function bbClienteDetectarPaisTelefonePorValor(valor){
	valor = (valor || '').toString();
	var digitos = bbClienteSomenteDigitos(valor);

	if (digitos === '') {
		return bbClienteObterPaisSelecionadoTelefone();
	}

	if (!bbClienteEhTelefoneInternacional(valor)) {
		return 'br';
	}

	var lista = bbClienteObterListaPaisesTelefone()
		.filter(function(item){ return item.ddi !== ''; })
		.sort(function(a, b){ return b.ddi.length - a.ddi.length; });

	for (var i = 0; i < lista.length; i++) {
		if (digitos.indexOf(lista[i].ddi) === 0) {
			return lista[i].chave;
		}
	}

	return 'outro';
}

function bbClienteSincronizarSelectPaisTelefone(valor){
	var pais = bbClienteDetectarPaisTelefonePorValor(valor);
	bbClienteGarantirPaisSelecionadoNoSelect(pais);
	bbClienteAtualizarUiSelectPaisTelefone();
}

function bbClienteAtualizarAjudaTelefone(modo){
	var $ajuda = $('#telefone_ajuda');
	if (!$ajuda.length) {
		return;
	}

	var pais = bbClienteObterPaisSelecionadoTelefone();
	var info = bbClienteObterInfoPaisTelefone(pais);

	if (pais === 'outro') {
		$ajuda.text('Outro país: pesquise pelo nome ou DDI e, se não encontrar, digite manualmente começando com +DDI.');
		return;
	}

	if (modo === 'internacional' || pais !== 'br') {
		$ajuda.text(info.pais + ': formato sugerido ' + info.formato + '.');
		return;
	}

	$ajuda.text('Brasil: digite normalmente. Formato internacional sugerido: ' + info.formato + '.');
}

function bbClienteAtualizarMascaraTelefone(forcar){
	var $telefone = $('#telefone');
	if (!$telefone.length) {
		return;
	}

	if (typeof $telefone.unmask === 'function') {
		$telefone.unmask();
	}

	var valorAtual = ($telefone.val() || '').toString();
	var modoManual = ($telefone.data('bb-modo-manual') || '').toString();
	var modo = bbClienteEhTelefoneInternacional(valorAtual) ? 'internacional' : 'br';
	var infoPais = bbClienteObterInfoPaisTelefone(bbClienteObterPaisSelecionadoTelefone());

	if (modoManual === 'internacional' || modoManual === 'br') {
		modo = modoManual;
	}

	if (forcar === 'internacional' || forcar === 'br') {
		modo = forcar;
	}

	if (forcar === 'reset') {
		if (valorAtual.trim() === '') {
			$telefone.removeData('bb-modo-manual');
			modo = (infoPais.chave && infoPais.chave !== 'br') ? 'internacional' : 'br';
		} else if (bbClienteEhTelefoneInternacional(valorAtual)) {
			$telefone.data('bb-modo-manual', 'internacional');
			modo = 'internacional';
		} else {
			$telefone.removeData('bb-modo-manual');
			modo = 'br';
		}
	}

	$telefone.data('bb-modo-tel', modo);

	if (modo === 'internacional') {
		$telefone.attr('placeholder', infoPais.formato || 'Ex: +DDI número local');
		$telefone.val(bbClienteNormalizarTelefoneInternacionalVisual(valorAtual));
	} else {
		$telefone.attr('placeholder', 'Ex: (11) 99999-9999');
		$telefone.val(bbClienteFormatarTelefoneBrasil(valorAtual));
	}

	bbClienteSincronizarSelectPaisTelefone($telefone.val());
	bbClienteAtualizarAjudaTelefone(modo);
}

function bbClienteAplicarPaisSelecionadoTelefone(){
	var $telefone = $('#telefone');
	var pais = bbClienteObterPaisSelecionadoTelefone();
	var info = bbClienteObterInfoPaisTelefone(pais);

	if (!$telefone.length) {
		return;
	}

	var valorAtual = ($telefone.val() || '').toString();
	var digitos = bbClienteSomenteDigitos(valorAtual);

	if (pais === 'br') {
		if (bbClienteEhTelefoneInternacional(valorAtual) && digitos.indexOf('55') === 0 && (digitos.length === 12 || digitos.length === 13)) {
			digitos = digitos.substring(2);
			$telefone.val(digitos);
		}

		$telefone.data('bb-modo-manual', 'br');
		bbClienteAtualizarMascaraTelefone('br');
		return;
	}

	$telefone.data('bb-modo-manual', 'internacional');

	if (pais === 'outro') {
		if (valorAtual.trim() === '') {
			$telefone.val('+');
		} else {
			$telefone.val(bbClienteNormalizarTelefoneInternacionalVisual(valorAtual));
			if (!/^\+/.test(($telefone.val() || '').toString()) && bbClienteSomenteDigitos($telefone.val()).length > 0) {
				$telefone.val('+' + bbClienteSomenteDigitos($telefone.val()));
			}
		}

		bbClienteAtualizarMascaraTelefone('internacional');
		return;
	}

	if (digitos === '') {
		$telefone.val('+' + info.ddi + ' ');
	} else if (!bbClienteEhTelefoneInternacional(valorAtual)) {
		$telefone.val('+' + info.ddi + ' ' + digitos);
	} else {
		var paisAtual = bbClienteDetectarPaisTelefonePorValor(valorAtual);
		var infoAtual = bbClienteObterInfoPaisTelefone(paisAtual);

		if (infoAtual.ddi !== '' && digitos.indexOf(infoAtual.ddi) === 0) {
			digitos = digitos.substring(infoAtual.ddi.length);
		}

		$telefone.val('+' + info.ddi + (digitos !== '' ? ' ' + digitos : ' '));
	}

	$telefone.val(bbClienteNormalizarTelefoneInternacionalVisual($telefone.val()));
	bbClienteAtualizarMascaraTelefone('internacional');
}

function bbClientePrepararCamposAntesSalvar(){
	var $telefone = $('#telefone');
	if (!$telefone.length) {
		return;
	}

	var valorAtual = ($telefone.val() || '').toString();
	var pais = bbClienteObterPaisSelecionadoTelefone();
	var info = bbClienteObterInfoPaisTelefone(pais);
	var digitos = bbClienteSomenteDigitos(valorAtual);

	if (pais === 'br') {
		if (bbClienteEhTelefoneInternacional(valorAtual) || $telefone.data('bb-modo-manual') === 'internacional') {
			$telefone.val(bbClienteNormalizarTelefoneInternacionalVisual(valorAtual));
		} else {
			$telefone.val(bbClienteFormatarTelefoneBrasil(valorAtual));
		}
		return;
	}

	if (pais !== 'outro' && info.ddi !== '') {
		if (digitos === '') {
			$telefone.val('+' + info.ddi + ' ');
			return;
		}

		if (bbClienteEhTelefoneInternacional(valorAtual)) {
			var paisAtual = bbClienteDetectarPaisTelefonePorValor(valorAtual);
			var infoAtual = bbClienteObterInfoPaisTelefone(paisAtual);

			if (infoAtual.ddi !== '' && digitos.indexOf(infoAtual.ddi) === 0) {
				digitos = digitos.substring(infoAtual.ddi.length);
			}
		}

		$telefone.val('+' + info.ddi + (digitos !== '' ? ' ' + digitos : ''));
		$telefone.val(bbClienteNormalizarTelefoneInternacionalVisual($telefone.val()));
		return;
	}

	if (pais === 'outro') {
		if (digitos === '') {
			$telefone.val('+');
			return;
		}

		if (!/^\s*\+/.test(valorAtual) && !/^\s*00/.test(valorAtual)) {
			$telefone.val('+' + digitos);
		} else {
			$telefone.val(bbClienteNormalizarTelefoneInternacionalVisual(valorAtual));
		}
		return;
	}

	if (bbClienteEhTelefoneInternacional(valorAtual) || $telefone.data('bb-modo-manual') === 'internacional') {
		$telefone.val(bbClienteNormalizarTelefoneInternacionalVisual(valorAtual));
	} else {
		$telefone.val(bbClienteFormatarTelefoneBrasil(valorAtual));
	}
}

function bbClienteAtivarModoInternacionalNoCampo(campo){
	var $campo = $(campo);
	if (!$campo.length) {
		return;
	}

	var valorAtual = ($campo.val() || '').toString();

	$campo.data('bb-modo-manual', 'internacional');

	if (bbClienteEhTelefoneInternacional(valorAtual)) {
		$campo.val(bbClienteNormalizarTelefoneInternacionalVisual(valorAtual));
	} else {
		var pais = bbClienteObterPaisSelecionadoTelefone();
		var info = bbClienteObterInfoPaisTelefone(pais);

		if (info.ddi !== '') {
			$campo.val('+' + info.ddi + ' ');
		} else {
			$campo.val('+');
		}
	}

	bbClienteAtualizarMascaraTelefone('internacional');

	var el = $campo.get(0);
	if (el && typeof el.setSelectionRange === 'function') {
		var pos = $campo.val().length;
		el.setSelectionRange(pos, pos);
	}
}

$(document).ready(function(){
	bbClientePreencherSelectPaisesTelefone('', 'br');
	bbClienteIniciarSelectPaisTelefone();
	bbClienteAtualizarMascaraTelefone('reset');

	$(document).on('keydown', '#telefone', function(e){
		var tecla = e.key || '';
		var $campo = $(this);
		var valorAtual = ($campo.val() || '').toString();

		if (tecla === '+') {
			e.preventDefault();
			bbClienteAtivarModoInternacionalNoCampo(this);
			return;
		}

		if (valorAtual.trim() === '' && (tecla === '(' || /^\d$/.test(tecla))) {
			$campo.data('bb-modo-manual', 'br');
			bbClienteGarantirPaisSelecionadoNoSelect('br');
		}
	});

	$(document).on('paste', '#telefone', function(e){
		var evento = e.originalEvent || e;
		var texto = '';

		if (evento.clipboardData && evento.clipboardData.getData) {
			texto = evento.clipboardData.getData('text');
		}

		if (bbClienteTemPrefixoInternacional(texto) || bbClienteSomenteDigitos(texto).length > 11) {
			$(this).data('bb-modo-manual', 'internacional');
		} else if (bbClienteSomenteDigitos(texto).length > 0) {
			$(this).data('bb-modo-manual', 'br');
		}

		var campo = this;
		setTimeout(function(){
			bbClienteAtualizarMascaraTelefone();
			var $campo = $(campo);
			if (($campo.data('bb-modo-manual') || '') === 'internacional') {
				$campo.val(bbClienteNormalizarTelefoneInternacionalVisual($campo.val()));
			}
		}, 0);
	});

	$(document).on('input keyup blur', '#telefone', function(){
		var $campo = $(this);
		var valorAtual = ($campo.val() || '').toString();
		var modoManual = ($campo.data('bb-modo-manual') || '').toString();

		if (modoManual === 'internacional') {
			$campo.val(bbClienteNormalizarTelefoneInternacionalVisual(valorAtual));
		} else if (bbClienteTemPrefixoInternacional(valorAtual) || bbClienteSomenteDigitos(valorAtual).length > 11) {
			$campo.data('bb-modo-manual', 'internacional');
			$campo.val(bbClienteNormalizarTelefoneInternacionalVisual(valorAtual));
		} else if (valorAtual.trim() === '') {
			$campo.removeData('bb-modo-manual');
		}

		bbClienteAtualizarMascaraTelefone();
	});

	$(document).on('change', '#telefone_pais', function(){
		bbClienteAtualizarHiddenPaisTelefone();
		bbClienteAplicarPaisSelecionadoTelefone();
	});

	$('#modalForm').on('shown.bs.modal', function(){
		bbClientePreencherSelectPaisesTelefone('', bbClienteDetectarPaisTelefonePorValor($('#telefone').val()));
		bbClienteIniciarSelectPaisTelefone();
		bbClienteSincronizarSelectPaisTelefone($('#telefone').val());
		bbClienteAtualizarMascaraTelefone('reset');
		bbClienteAtualizarUiSelectPaisTelefone();
	});

	$('#modalForm').on('hidden.bs.modal', function(){
		$('#telefone').removeData('bb-modo-manual');
		bbClientePreencherSelectPaisesTelefone('', 'br');
		bbClienteIniciarSelectPaisTelefone();
		bbClienteAtualizarAjudaTelefone('br');
		bbClienteAtualizarUiSelectPaisTelefone();
	});
});
</script>

<script type="text/javascript">
	
$("#form_cli").submit(function (event) {

    bbClientePrepararCamposAntesSalvar();
    bbClienteAtualizarHiddenPaisTelefone();
    event.preventDefault();
    var formData = new FormData(this);

    if (!formData.get('telefone_pais')) {
    	formData.set('telefone_pais', ($('#telefone_pais').val() || 'br').toString());
    }

    if (!formData.get('telefone_ddi')) {
    	formData.set('telefone_ddi', ($('#telefone_ddi').val() || '').toString());
    }

    if (!formData.get('telefone_pais_nome')) {
    	formData.set('telefone_pais_nome', ($('#telefone_pais_nome').val() || '').toString());
    }

    $.ajax({
        url: 'paginas/' + pag + "/salvar.php",
        type: 'POST',
        data: formData,

        success: function (mensagem) {
            $('#mensagem').text('');
            $('#mensagem').removeClass()
            if (mensagem.trim() == "Salvo com Sucesso") {

                $('#btn-fechar').click();

                var pagina = $("#pagina").val();
                listarClientes(pagina)          

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





function excluir(id){
    $.ajax({
        url: 'paginas/' + pag + "/excluir.php",
        method: 'POST',
        data: {id},
        dataType: "text",

        success: function (mensagem) {            
            if (mensagem.trim() == "Excluído com Sucesso") {                
                 var pagina = $("#pagina").val();
                listarClientes(pagina)              
            } else {
                $('#mensagem-excluir').addClass('text-danger')
                $('#mensagem-excluir').text(mensagem)
            }

        },      

    });
}



	function listarTextoContrato(id){
	
    $.ajax({
        url: 'paginas/' + pag + "/texto-contrato.php",
        method: 'POST',
        data: {id},
        dataType: "html",

        success:function(result){            
            nicEditors.findEditor("contrato").setContent(result);	          
        }
    });
}


function alterar(){
	

	 var produt = $("#pro").val();

	 let valorFormatado = produt.toString().replace('.', ',');

	 $("#valor_cob").val(valorFormatado);
	 $("#pro").val('');


}

function alterar2(){
	

	 var ser = $("#ser").val();

	 let valorFormatado = ser.toString().replace('.', ',');

	 $("#valor_cob").val(valorFormatado);
	 $("#pro").val('');

}




$("#form-contrato").submit(function (event) {
	var id_emp = $('#id_contrato').val();
    event.preventDefault();
    nicEditors.findEditor('contrato').saveContent();
    var formData = new FormData(this);

    $.ajax({
        url: 'paginas/' + pag + "/salvar-contrato.php",
        type: 'POST',
        data: formData,

        success: function (mensagem) {
            $('#mensagem-contrato').text('');
            $('#mensagem-contrato').removeClass()
            if (mensagem.trim() == "Salvo com Sucesso") {                
                   
                let a= document.createElement('a');
                a.target= '_blank';
                a.href= 'rel/contrato_servico_class.php?id=' + id_emp;
                a.click();  	 

            } else {

                $('#mensagem-contrato').addClass('text-danger')
                $('#mensagem-contrato').text(mensagem)
            }


        },

        cache: false,
        contentType: false,
        processData: false,

    });

});

</script>



<script type="text/javascript">
	function baixar(id, cliente){
    $.ajax({
        url: 'paginas/receber/baixar.php',
        method: 'POST',
        data: {id},
        dataType: "text",

        success: function (mensagem) {            
            if (mensagem.trim() == "Baixado com Sucesso") {                
                listarDebitos(cliente);                
            } else {
                    $('#mensagem-excluir-baixar').addClass('text-danger')
                    $('#mensagem-excluir-baixar').text(mensagem)
                }

        },      

    });
}

</script>

<script type="text/javascript">
	function listarCobrancas(id){	

		 $.ajax({
	        url: 'paginas/' + pag + "/listar_cobrancas.php",
	        method: 'POST',
	        data: {id},
	        dataType: "html",

	        success:function(result){
	            $("#listar_cobrancas").html(result);
	           
	        }
	    });

	}
</script>



<script type="text/javascript">
	

$("#form_cob").submit(function (event) {

	$('#mensagem_cob').text('Carregando');
	$('#btn_cobranca').hide();

    event.preventDefault();
    var formData = new FormData(this);

    $.ajax({
        url: 'paginas/' + pag + "/recorrencia.php",
        type: 'POST',
        data: formData,

        success: function (mensagem) {
            $('#mensagem_cob').text('');
            $('#mensagem_cob').removeClass()
            if (mensagem.trim() == "Salvo com Sucesso") {
            	
                $('#btn-fechar-cob').click();
                limparCampos();
                      

            } else {

                $('#mensagem_cob').addClass('text-danger')
                $('#mensagem_cob').text(mensagem)
            }

            $('#btn_cobranca').show();

        },

        cache: false,
        contentType: false,
        processData: false,

    });

});
</script>


<script type="text/javascript">
	function calcular(){			
	var valor = $('#valor_baixar').val();
	var multa = $('#multa_baixar').val();
	var juros = $('#juros_baixar').val();
	var forma_pgto = $('#forma_pgto').val();	

	 $.ajax({
	        url: 'paginas/' + pag + "/calcular.php",
	        method: 'POST',
	        data: {valor, multa, juros, forma_pgto},
	        dataType: "html",

	        success:function(result){
	             $('#valor_final').val(result);
	        }
	    });

	

	}
</script>
<script type="text/javascript">

	$("#form-baixar").submit(function (event) {

		var cliente = $('#cliente_baixar').val()

		$('#btn_baixar').hide();
		$('#mensagem-baixar').text('Baixando!!');

    event.preventDefault();
    var formData = new FormData(this);

    $.ajax({
        url: 'paginas/receber/baixar.php',
        type: 'POST',
        data: formData,

        success: function (mensagem) {
            $('#mensagem-baixar').text('');
            $('#mensagem-baixar').removeClass()
            if (mensagem.trim() == "Baixado com Sucesso") {

                $('#btn-fechar-baixar').click();
                listarDebitos(cliente);          

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

<!-- Modal Sessões do Combo (Admin Clientes) -->
<div class="modal fade" id="modalCombosCliente" tabindex="-1" role="dialog" aria-labelledby="modalCombosClienteLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title" id="modalCombosClienteLabel">Sessões do Combo - <span id="nome_combo_cliente"></span></h4>
				<button type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
				<div id="listar-combos-cliente"><small>Carregando...</small></div>
				<small><div align="center" id="mensagem-combos-cliente"></div></small>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
/*
 * Funções específicas da tela de Clientes.
 *
 * Ponto corrigido:
 * O arquivo js/ajax.js também possui funções globais inserir(), editar(),
 * mostrar() e outras. Como esta tela passa parâmetros próprios no ícone
 * de edição, não podemos manter a condição "if (typeof window.editar...)".
 * Se a função genérica já existir, ela recebe os parâmetros errados e abre
 * a modal sem preencher os dados do cliente.
 *
 * Mantém a lógica do sistema:
 * - Não altera SQL.
 * - Não altera regra de cadastro.
 * - Não altera salvamento.
 * - Apenas garante que a tela de clientes use as funções corretas dela.
 */
function bbClienteValorCampo(valor){
	if (typeof valor === 'undefined' || valor === null) {
		return '';
	}

	return valor;
}

function bbClienteValorDataInput(valor){
	valor = bbClienteValorCampo(valor).toString();

	if (valor === '0000-00-00' || valor === '00/00/0000') {
		return '';
	}

	return valor;
}

window.inserir = function(){
	$('#titulo_inserir').text('Inserir Registro');
	$('#id').val('');
	$('#nome').val('');
	$('#telefone').val('');
	$('#cpf').val('');
	$('#cartao').val('0');
	$('#endereco').val('');
	$('#data_nasc').val('');
	$('#mensagem').html('');

	if (typeof bbClientePreencherSelectPaisesTelefone === 'function') {
		bbClientePreencherSelectPaisesTelefone('', 'br');
	}
	if (typeof bbClienteIniciarSelectPaisTelefone === 'function') {
		bbClienteIniciarSelectPaisTelefone();
	}
	if (typeof bbClienteSincronizarSelectPaisTelefone === 'function') {
		bbClienteSincronizarSelectPaisTelefone('');
	}
	if (typeof bbClienteAtualizarMascaraTelefone === 'function') {
		bbClienteAtualizarMascaraTelefone('reset');
	}
	if (typeof bbClienteAtualizarUiSelectPaisTelefone === 'function') {
		bbClienteAtualizarUiSelectPaisTelefone();
	}

	$('#modalForm').modal('show');
	return false;
};

window.editar = function(id, nome, telefone, endereco, data_nasc, cartoes, cpf){
	$('#titulo_inserir').text('Editar Registro');
	$('#id').val(bbClienteValorCampo(id));
	$('#nome').val(bbClienteValorCampo(nome));
	$('#telefone').val(bbClienteValorCampo(telefone));
	$('#cpf').val(bbClienteValorCampo(cpf));
	$('#cartao').val(bbClienteValorCampo(cartoes));
	$('#endereco').val(bbClienteValorCampo(endereco));
	$('#data_nasc').val(bbClienteValorDataInput(data_nasc));
	$('#mensagem').html('');

	if (typeof bbClienteSincronizarSelectPaisTelefone === 'function') {
		bbClienteSincronizarSelectPaisTelefone(bbClienteValorCampo(telefone));
	}
	if (typeof bbClienteAtualizarMascaraTelefone === 'function') {
		bbClienteAtualizarMascaraTelefone();
	}
	if (typeof bbClienteAtualizarUiSelectPaisTelefone === 'function') {
		bbClienteAtualizarUiSelectPaisTelefone();
	}

	$('#modalForm').modal('show');
	return false;
};

window.mostrar = function(id, nome, telefone, cartoes, data_cad, data_nasc, endereco, retorno, servico, obs, valor, data){
	$('#nome_dados').text(bbClienteValorCampo(nome));
	$('#telefone_dados').text(bbClienteValorCampo(telefone));
	$('#cartoes_dados').text(bbClienteValorCampo(cartoes));
	$('#data_cad_dados').text(bbClienteValorCampo(data_cad));
	$('#data_nasc_dados').text(bbClienteValorCampo(data_nasc));
	$('#retorno_dados').text(bbClienteValorCampo(retorno));
	$('#servico_dados').text(bbClienteValorCampo(servico));
	$('#endereco_dados').text(bbClienteValorCampo(endereco));
	$('#servico_dados_tab').text(bbClienteValorCampo(servico));
	$('#data_dados_tab').text(bbClienteValorCampo(data));
	$('#valor_dados_tab').text(bbClienteValorCampo(valor));
	$('#obs_dados_tab').text(bbClienteValorCampo(obs));

	if (typeof listarDebitos === 'function') {
		listarDebitos(id);
	}
	if (typeof listarCobrancas === 'function') {
		listarCobrancas(id);
	}

	$('#modalDados').modal('show');
	return false;
};

window.cobranca = function(id, nome){
	$('#titulo_cob').text('Cobrança Recorrente - ' + bbClienteValorCampo(nome));
	$('#id_cob').val(bbClienteValorCampo(id));
	$('#valor_cob').val('');
	$('#parcelas_cob').val('1');
	$('#data_venc_cob').val('<?= $data_atual ?>');
	$('#frequencia_cob').val($('#frequencia_cob option:first').val());
	$('#obs_cob').val('');
	$('#pro').val('').change();
	$('#ser').val('').change();
	$('#mensagem_cob').html('');
	$('#modalCobranca').modal('show');
	return false;
};

window.contrato = function(id, nome){
	$('#titulo_contrato').text('Contrato de Serviço - ' + bbClienteValorCampo(nome));
	$('#id_contrato').val(bbClienteValorCampo(id));
	$('#mensagem-contrato').html('');

	if (typeof listarTextoContrato === 'function') {
		listarTextoContrato(id);
	}

	$('#modalContrato').modal('show');
	return false;
};

window.excluirMarketing = function(id){
	$.ajax({
		url: 'paginas/' + pag + '/excluir-marketing.php',
		method: 'POST',
		data: bbClienteMontarDadosAjax({id: id}),
		dataType: 'text',
		success: function(mensagem){
			if ((mensagem || '').trim() === 'Excluído com Sucesso' || (mensagem || '').trim() === 'Removido com Sucesso') {
				var pagina = $("#pagina").val();
				listarClientes(pagina);
			} else {
				$('#mensagem-excluir').addClass('text-danger').text(mensagem);
			}
		},
		error: function(xhr){
			$('#mensagem-excluir').addClass('text-danger').text(xhr.responseText || 'Erro ao remover do marketing!');
		}
	});
	return false;
};

window.abrirCombosClienteAdmin = function(el){
	var id = $(el).data('id');
	var nome = $(el).data('nome') || '';

	$('#nome_combo_cliente').text(nome);
	$('#mensagem-combos-cliente').removeClass('text-danger').html('');
	$('#listar-combos-cliente').html('<small>Carregando...</small>');

	$.ajax({
		url: 'paginas/' + pag + '/listar-combos.php',
		method: 'POST',
		data: bbClienteMontarDadosAjax({id: id}),
		dataType: 'html',
		success: function(result){
			$('#listar-combos-cliente').html(result);
			$('#modalCombosCliente').modal('show');
		},
		error: function(xhr){
			$('#mensagem-combos-cliente').addClass('text-danger').html(xhr.responseText || 'Erro ao carregar sessões do combo!');
			$('#modalCombosCliente').modal('show');
		}
	});

	return false;
};
</script>
