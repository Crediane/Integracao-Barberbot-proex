<?php 
require_once __DIR__ . '/_context_bootstrap.php';
require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/../verificar.php';
$pag = 'perguntas';

if(@$_SESSION['nivel'] != 'Administrador' and @$_SESSION['nivel'] != 'Professor'){
	echo "<script>window.location='<?php echo $bb_painel_public_url; ?>/'</script>";
	exit();
}

?>

<div class="row-one widgettable">
			<div class="col-md-12 content-top-2 card" style="padding-top: 5px">
				<h4 style="margin-top: 15px">Aguardando Respostas</h4>
				<hr>
				<div class="row" id="listar-cursos">


					</div>
				</div>
			</div>






<!-- Modal Perguntas -->
<div class="modal fade" id="modalPerguntas" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" data-backdrop="static" style="overflow: scroll; height:600px">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title"><span id="nome_curso_titulo"> </span> </h4>
				<button id="btn-fechar-aula" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
				
				<div class="modal-body">
					
							<div id="listar-perguntas">

							</div>

							<input type="hidden" name="id_curso" id="id_do_curso">
						
					</div>			


						

		</div>
	</div>
</div>





<!-- Modal Resposta -->
<div class="modal fade" id="modalResposta" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title" id="exampleModalLabel"><span id="pergunta_resposta"></span> <span class="neutra ocultar-mobile"> </span>  </h4>

				<button type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -25px" id="btn-fechar-resposta">
					<span class="neutra" aria-hidden="true">&times;</span>
				</button>
			</div>
			
			<div class="modal-body">
				<form method="post" id="form-respostas">
				
					<div id="listar-respostas">fsdfdsfsdfsd</div>
					
					<hr>

						<div class="col-md-12">						
							<div class="form-group"> 
								<label>Resposta<small>(Max 500 Caracteres)</small></label> 
								<textarea maxlength="500" class="form-control" name="resposta" id="resposta"></textarea> 
							</div>						
						</div>

						

						<div class="col-md-12" align="right" style="margin-top: 15px">						
							<button type="submit" class="btn btn-primary">Salvar</button>					
						</div>

							

					<br>
					<input type="hidden" name="id_pergunta" id="id_pergunta_resposta"> 
					<input type="hidden" name="id_curso" id="id_curso_resposta"> 
					<small><div id="mensagem-pergunta" align="center" class="mt-3"></div></small>


					<hr>
				<div  class="modal-footer">
					
				</div>
					

				


			</form>
				
				
			</div>
			
		</div>
	</div>
</div>




<script type="text/javascript">var pag = "<?=$pag?>"</script>
<script src="js/ajax.js"></script>



<script type="text/javascript">
	$(document).ready(function() {			
		listarCursos()		
});
</script>



<script type="text/javascript">	 
	function listarCursos(){
    $.ajax({
        url: 'paginas/' + pag + "/listar-cursos.php",
        method: 'POST',
        data: {},
        dataType: "html",

        success:function(result){
            $("#listar-cursos").html(result);

           
        }
    });
}

</script>


<script type="text/javascript">
	function modalPerguntas(id, nome){		
		$('#nome_curso_titulo').text(nome);
		$('#id_do_curso').val(id);		
		$('#modalPerguntas').modal('show');	
		listarPerguntas(id);

	}
</script>



<script type="text/javascript">	 
	function listarPerguntas(id){
    $.ajax({
        url: 'paginas/' + pag + "/listar-perguntas.php",
        method: 'POST',
        data: {id},
        dataType: "html",

        success:function(result){
            $("#listar-perguntas").html(result);

           
        }
    });
}

</script>



<script type="text/javascript">
function excluirPergunta(id){
	var id_curso = $('#id_do_curso').val();
    $.ajax({
        url: 'paginas/' + pag + "/excluir-pergunta.php",
        method: 'POST',
        data: {id},
        dataType: "text",

        success: function (mensagem) {            
            if (mensagem.trim() == "Excluído com Sucesso") {                
                listarPerguntas(id_curso);
                listarCursos();                
            } else {
                    $('#mensagem-excluir').addClass('text-danger')
                    $('#mensagem-excluir').text(mensagem)
                }

        },      

    });
}

</script>



<script type="text/javascript">
	function modalResposta(id_pergunta, pergunta){
		$('#pergunta_resposta').text(pergunta);
		$('#id_pergunta_resposta').val(id_pergunta);		
		$('#modalResposta').modal('show');
		listarRespostas(id_pergunta);
	}
</script>





<script type="text/javascript">
	
$("#form-respostas").submit(function () {
	var id_pergunta = $('#id_pergunta_resposta').val();
	var id_curso = $('#id_do_curso').val();
	event.preventDefault();
	var formData = new FormData(this);

	$.ajax({
		url: 'paginas/' + pag + "/inserir-resposta.php",
		type: 'POST',
		data: formData,

		success: function (mensagem) {
            $('#mensagem-resposta').text('');
            $('#mensagem-resposta').removeClass()
            if (mensagem.trim() == "Salvo com Sucesso") { 
            		 $('#resposta').val('')            		                 
                     $('#btn-fechar-resposta').click();
                     listarRespostas(id_pergunta)
                     listarPerguntas(id_curso)
                     listarCursos();
                    
                } else {
                	$('#mensagem-resposta').addClass('text-danger')
                    $('#mensagem-resposta').text(mensagem)
                }

            },

            cache: false,
            contentType: false,
            processData: false,
            
        });

});
</script>




<script type="text/javascript">	 
	function listarRespostas(id){
    $.ajax({
        url: 'paginas/' + pag + "/listar-respostas.php",
        method: 'POST',
        data: {id},
        dataType: "html",

        success:function(result){
            $("#listar-respostas").html(result);
           
        }
    });
}

</script>




<script type="text/javascript">
	

function excluirResposta(id){
	var id_pergunta = $('#id_pergunta_resposta').val();
	var id_curso = $('#id_do_curso').val();
    $.ajax({
        url: 'paginas/' + pag + "/excluir-resposta.php",
        method: 'POST',
        data: {id},
        dataType: "text",

        success: function (mensagem) {            
            if (mensagem.trim() == "Excluído com Sucesso") {                
                listarRespostas(id_pergunta);
                listarPerguntas(id_curso); 
                listarCursos()              
            } else {
                    $('#mensagem-resposta').addClass('text-danger')
                    $('#mensagem-resposta').text(mensagem)
                }

        },      

    });
}

</script>





<script type="text/javascript">
function respondida(id){
	var id_curso = $('#id_do_curso').val();
    $.ajax({
        url: 'paginas/' + pag + "/respondida.php",
        method: 'POST',
        data: {id},
        dataType: "text",

        success: function (mensagem) {            
            if (mensagem.trim() == "Respondida com Sucesso") {                
                listarPerguntas(id_curso);
                listarCursos();                
            } else {
                    $('#mensagem-excluir').addClass('text-danger')
                    $('#mensagem-excluir').text(mensagem)
                }

        },      

    });
}

</script>
