<?php 
require_once __DIR__ . '/_context_bootstrap.php';
require_once __DIR__ . '/../verificar.php';
require_once __DIR__ . '/../../conexao.php';

//verificar se ele tem a permissão de estar nessa página
if(@$assinaturas == 'ocultar'){
    echo "<script>window.location='<?php echo $bb_painel_public_url; ?>/'</script>";
    exit();
}

$pag = 'assinaturas';
?>

<div class="">      
	<a class="btn btn-primary" onclick="inserir()" class="btn btn-primary btn-flat btn-pri"><i class="fa fa-plus" aria-hidden="true"></i> Novo Grupo Assinatura</a>
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
						<div class="col-md-12">
							<div class="form-group">
								<label for="exampleInputEmail1">Descrição</label>
								<input type="text" class="form-control" id="nome" name="nome" placeholder="Nome" required>    
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




<!-- Modal Itens-->
<div class="modal fade" id="modalItens" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title"><span id="nome_itens"></span></h4>
				<button id="btn-fechar-itens" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
					<span aria-hidden="true" >&times;</span>
				</button>
			</div>
			<form id="form_itens">
			<div class="modal-body">

					<div class="row">
						<div class="col-md-8">
							<div class="form-group">
								<label for="exampleInputEmail1">Nome</label>
								<input type="text" class="form-control" id="nome_item" name="nome" placeholder="Nome" required>    
							</div> 	
						</div>

						<div class="col-md-4">
							<div class="form-group">
								<label for="exampleInputEmail1">Valor Mensal</label>
								<input type="text" class="form-control" id="valor" name="valor" placeholder="Valor Plano Mensal" required>    
							</div> 	
						</div>
						
					</div>

					<div class="row">
						<div class="col-md-12">
							<div class="form-group">
								<label for="exampleInputEmail1">Serviço Vinculado</label>
								<select class="form-control" id="servico_vinculado" name="servico">
									<option value="0">Selecionar Serviço</option>
									<?php
									try{
										$stServ = $pdo->query("SELECT id, nome FROM servicos ORDER BY nome ASC");
										$resServ = $stServ->fetchAll(PDO::FETCH_ASSOC);
										for($is=0; $is < @count($resServ); $is++){
											echo '<option value="'.$resServ[$is]['id'].'">'.$resServ[$is]['nome'].'</option>';
										}
									}catch(Throwable $e){}
									?>
								</select>
							</div>
						</div>
					</div>


					<div class="row">
						<div class="col-md-4">
							<div class="form-group">
								<label for="exampleInputEmail1">Característica 1</label>
								<input type="text" class="form-control" id="c1" name="c1" placeholder="Item 1" >    
							</div> 	
						</div>

						<div class="col-md-4">
							<div class="form-group">
								<label for="exampleInputEmail1">Característica 2</label>
								<input type="text" class="form-control" id="c2" name="c2" placeholder="Item 2" >    
							</div> 	
						</div>

						<div class="col-md-4">
							<div class="form-group">
								<label for="exampleInputEmail1">Característica 3</label>
								<input type="text" class="form-control" id="c3" name="c3" placeholder="Item 3" >    
							</div> 	
						</div>
					</div>



					<div class="row">
						<div class="col-md-4">
							<div class="form-group">
								<label for="exampleInputEmail1">Característica 4</label>
								<input type="text" class="form-control" id="c4" name="c4" placeholder="Item 4" >    
							</div> 	
						</div>

						<div class="col-md-4">
							<div class="form-group">
								<label for="exampleInputEmail1">Característica 5</label>
								<input type="text" class="form-control" id="c5" name="c5" placeholder="Item 5" >    
							</div> 	
						</div>

						<div class="col-md-4">
							<div class="form-group">
								<label for="exampleInputEmail1">Característica 6</label>
								<input type="text" class="form-control" id="c6" name="c6" placeholder="Item 6" >    
							</div> 	
						</div>
					</div>


					<div class="row">
						<div class="col-md-4">
							<div class="form-group">
								<label for="exampleInputEmail1">Característica 7</label>
								<input type="text" class="form-control" id="c7" name="c7" placeholder="Item 7" >    
							</div> 	
						</div>

						<div class="col-md-4">
							<div class="form-group">
								<label for="exampleInputEmail1">Característica 8</label>
								<input type="text" class="form-control" id="c8" name="c8" placeholder="Item 8" >    
							</div> 	
						</div>

						<div class="col-md-4">
							<div class="form-group">
								<label for="exampleInputEmail1">Característica 9</label>
								<input type="text" class="form-control" id="c9" name="c9" placeholder="Item 9" >    
							</div> 	
						</div>
					</div>


					<div class="row">
						<div class="col-md-4">
							<div class="form-group">
								<label for="exampleInputEmail1">Característica 10</label>
								<input type="text" class="form-control" id="c10" name="c10" placeholder="Item 10" >    
							</div> 	
						</div>

						<div class="col-md-4">
							<div class="form-group">
								<label for="exampleInputEmail1">Característica 11</label>
								<input type="text" class="form-control" id="c11" name="c11" placeholder="Item 11" >    
							</div> 	
						</div>

						<div class="col-md-4">
							<div class="form-group">
								<label for="exampleInputEmail1">Característica 12</label>
								<input type="text" class="form-control" id="c12" name="c12" placeholder="Item 12" >    
							</div> 	
						</div>
					</div>

					
					
						<input type="hidden" name="id" id="id_itens">
						<input type="hidden" name="id_item" id="id_do_item">

					<br>
					<small><div id="mensagem_itens" align="center"></div></small>

					<div id="listar_itens">
						
					</div>

				</div>

				<div class="modal-footer">      
					<button type="submit" class="btn btn-primary">Salvar</button>
				</div>
			</form>

			
		</div>
	</div>
</div>



<script type="text/javascript">var pag = "<?=$pag?>"</script>
<script src="js/ajax.js"></script>


<script type="text/javascript">
	$(document).ready(function() {
    $('.sel2').select2({
    	dropdownParent: $('#modalForm')
    });
});
</script>


<script type="text/javascript">
	function listarItens(){
		var id =  $('#id_itens').val();
		$("#listar_itens").html('');
		$.ajax({
			url: 'paginas/' + pag + "/listar_itens.php",
			method: 'POST',
			data: {id},
			dataType: "text",

			success:function(result){
				$("#listar_itens").html(result);
			}
		});
	}
</script>


<script type="text/javascript">
	$("#form_itens").submit(function () {

    event.preventDefault();
    var formData = new FormData(this);

    $.ajax({
        url: 'paginas/' + pag + "/salvar_itens.php",
        type: 'POST',
        data: formData,

        success: function (mensagem) {
            $('#mensagem_itens').text('');
            $('#mensagem_itens').removeClass()
            if (mensagem.trim() == "Salvo com Sucesso") {

                $('#btn-fechar-itens').click();
                listar();    
                listarItens();
                limparCamposItens()        

            } else {

                $('#mensagem_itens').addClass('text-danger')
                $('#mensagem_itens').text(mensagem)
            }


        },

        cache: false,
        contentType: false,
        processData: false,

    });

});


function limparCampos(){
		$('#id').val('');
		$('#nome').val('');
		$('#id_itens').val('');	
		
	}


function limparCamposItens(){	
		$('#id_do_item').val('');
		$('#nome_item').val('');
		$('#valor').val('');
		$('#c1').val('');
		$('#c2').val('');
		$('#c3').val('');
		$('#c4').val('');
		$('#c5').val('');
		$('#c6').val('');
		$('#c7').val('');
		$('#c8').val('');
		$('#c9').val('');
		$('#c10').val('');
		$('#c11').val('');
		$('#c12').val('');
		
	}

</script>