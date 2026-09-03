<?php
require_once __DIR__ . '/_context_bootstrap.php';
require_once __DIR__ . '/../verificar.php';
require_once __DIR__ . '/../../conexao.php';

$pag = 'anotacoes';

if(@$pag == 'ocultar'){
    echo "<script>window.location='<?php echo $bb_painel_public_url; ?>/'</script>";
    exit();
}
?>

<!-- BOTÃO -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
	<div class="d-flex align-items-center gap-2">
		<a class="btn btn-primary" onclick="inserir()">
			<i class="fa fa-plus"></i> Nova Anotação
		</a>
	</div>
</div>

<div class="bs-example widget-shadow" style="padding:15px" id="listar"></div>

<!-- Modal Inserir -->
<div class="modal fade" id="modalForm" tabindex="-1" role="dialog">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title"><span id="titulo_inserir"></span></h4>
				<button id="btn-fechar" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			
			<form id="form">
			<div class="modal-body">
				<div class="row">
					<div class="col-md-4">
						<div class="form-group">
							<label>Título</label>
							<input type="text" class="form-control" id="titulo" name="titulo" placeholder="Digite o Título" required>    
						</div> 	
					</div>
					<div class="col-md-3">
						<div class="form-group">
							<label>Mostrar no Dashboard</label>
							<select class="form-control" id="mostrar_home" name="mostrar_home"> 
								<option value="Não">Não</option>
								<option value="Sim">Sim</option>
							</select>
						</div>
					</div>
					<div class="col-md-3">
						<div class="form-group">
							<label>Privado?</label>
							<select class="form-control" name="privado" id="privado">
								<option value="Não">Não</option>
								<option value="Sim">Sim</option>
							</select>
						</div>
					</div>
					<div class="col-md-2" style="margin-top: 20px;">
						<button type="submit" class="btn btn-primary" id="btn_salvar">Salvar</button>
						<button class="btn btn-secondary" type="button" id="btn_carregando" style="display:none;" disabled>Salvando...</button>
					</div>
				</div>

				<!-- LINHA CLIENTE / PRODUTO / SERVIÇO / DATA / BAIXA -->
				<div class="row">
					<div class="col-md-3">
						<div class="form-group">
							<label>Cliente</label>
							<select name="id_cliente" id="id_cliente" class="form-control select2">
								<option value="">Selecione</option>
								<?php
								$query = $pdo->query("SELECT id, nome FROM clientes ORDER BY nome ASC");
								$res = $query->fetchAll(PDO::FETCH_ASSOC);
								foreach($res as $cliente){
									echo '<option value="'.$cliente['id'].'">'.$cliente['nome'].'</option>';
								}
								?>
							</select>
						</div>
					</div>

					<div class="col-md-3">
						<div class="form-group">
							<label>Produto</label>
							<select name="id_produto" id="id_produto" class="form-control select2">
								<option value="">Selecione</option>
								<?php
								$query = $pdo->query("SELECT id, nome FROM produtos ORDER BY nome ASC");
								$res = $query->fetchAll(PDO::FETCH_ASSOC);
								foreach($res as $produto){
									echo '<option value="'.$produto['id'].'">'.$produto['nome'].'</option>';
								}
								?>
							</select>
						</div>
					</div>

					<div class="col-md-3">
						<div class="form-group">
							<label>Serviço</label>
							<select name="id_servico" id="id_servico" class="form-control select2" required>
								<option value="">Selecione</option>
								<?php
								$query = $pdo->query("SELECT id, nome FROM servicos ORDER BY nome ASC");
								$res = $query->fetchAll(PDO::FETCH_ASSOC);
								foreach($res as $servico){
									echo '<option value="'.$servico['id'].'">'.$servico['nome'].'</option>';
								}
								?>
							</select>
						</div>
					</div>

					<div class="col-md-3">
						<div class="form-group">
							<label>Data</label>
							<input type="date" class="form-control" name="data" id="data">
						</div>
					</div>
				</div>

				<!-- LINHA BAIXA / ACERTO -->
				<div class="row">
					<div class="col-md-3">
						<div class="form-group">
							<label>Baixa / Acerto com Cliente</label>
							<select name="status_acerto" id="status_acerto" class="form-control">
								<option value="Pendente">Pendente</option>
								<option value="Baixado">Baixado</option>
							</select>
						</div>
					</div>
				</div>

				<div class="form-group mt-3">
					<label>Mensagem</label>
					<textarea name="msg" id="msg" class="form-control" placeholder="Digite o Conteúdo" rows="6"></textarea>
				</div>

				<input type="hidden" name="id" id="id">
				<br>
				<small><div id="mensagem" align="center"></div></small>
			</div>
			</form>
		</div>
	</div>
</div>

<!-- Modal Visualizar Detalhes -->
<div class="modal fade" id="modalDados" tabindex="-1" role="dialog">
	<div class="modal-dialog" role="document">
		<div class="modal-content p-3">
			<div class="modal-header">
				<h4 class="modal-title">Detalhes da Anotação</h4>
				<button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
			</div>
			<div class="modal-body">
				<p><strong>Título:</strong> <span id="titulo_dados"></span></p>
				<p><strong>Mensagem:</strong> <span id="msg_dados"></span></p>
				<p><strong>Cliente:</strong> <span id="cliente_dados"></span></p>
				<p><strong>Produto:</strong> <span id="produto_dados"></span></p>
				<p><strong>Serviço:</strong> <span id="servico_dados"></span></p>
				<p><strong>Data:</strong> <span id="data_dados"></span></p>
				<p><strong>Baixa / Acerto:</strong> <span id="status_acerto_dados"></span></p>
				<p><strong>Privado:</strong> <span id="privado_dados"></span></p>
				<p><strong>Mostrar no Dashboard:</strong> <span id="mostrar_home_dados"></span></p>
			</div>
		</div>
	</div>
</div>

<!-- Modal de Confirmação de Exclusão -->
<div class="modal fade" id="modalConfirmarExclusao" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-sm modal-dialog-centered" role="document">
    <div class="modal-content">
      <div class="modal-header bg-danger text-white py-2">
        <h5 class="modal-title">Confirmar Exclusão</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body text-center">
        <p>Deseja realmente excluir esta anotação?</p>
        <button type="button" class="btn btn-danger btn-sm" id="btnExcluirConfirmado">Sim, Excluir</button>
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
      </div>
    </div>
  </div>
</div>


<!-- TinyMCE + Select2 -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/tinymce/6.7.0/tinymce.min.js" referrerpolicy="origin"></script>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<script>
let idExcluir = null;

$(document).on('click', '.btnExcluir', function() {
  idExcluir = $(this).data('id');
  $('#modalConfirmarExclusao').modal('show');
});

$('#btnExcluirConfirmado').click(function() {
  $.ajax({
    url: 'paginas/anotacoes/excluir.php',
    method: 'POST',
    data: { id: idExcluir },
    success: function(response) {
      if (response.trim() === "Excluído com Sucesso") {
        if (typeof listar === "function") listar();
      } else {
        alert('Erro ao excluir: ' + response);
      }
      $('#modalConfirmarExclusao').modal('hide');
    }
  });
});
</script>

<script>
$(document).ready(function(){
	$('.select2').select2({
		dropdownParent: $('#modalForm'),
		width: '100%'
	});

	$('#modalForm').on('shown.bs.modal', function () {
		if (!tinymce.get("msg")) {
			tinymce.init({
				selector: '#msg',
				height: 250,
				menubar: false,
				plugins: 'lists link image code',
				toolbar: 'undo redo | bold italic underline | alignleft aligncenter alignright | bullist numlist | link image | code',
				branding: false
			});
		}
	});

	$('#modalForm').on('hidden.bs.modal', function () {
		if (tinymce.get("msg")) {
			tinymce.get("msg").remove();
		}
	});
});

$('#form').submit(function (event) {
	event.preventDefault();
	tinymce.triggerSave();
	var formData = new FormData(this);
	$('#btn_salvar').hide();
	$('#btn_carregando').show();

	$.ajax({
		url: 'paginas/' + pag + "/salvar.php",
		type: 'POST',
		data: formData,
		success: function (mensagem) {
			$('#mensagem').text('').removeClass();
			if (mensagem.trim() == "Salvo com Sucesso") {
				alert(mensagem);
				listar();
				$('#modalForm').modal('hide');
			} else {
				$('#mensagem').addClass('text-danger').text(mensagem);
			}
			$('#btn_salvar').show();
			$('#btn_carregando').hide();
		},
		error: function () {
			$('#mensagem').addClass('text-danger').text('Erro ao enviar os dados.');
			$('#btn_salvar').show();
			$('#btn_carregando').hide();
		},
		cache: false,
		contentType: false,
		processData: false,
	});
});
</script>

<script type="text/javascript">
var pag = "<?=$pag?>";

function ver(id, titulo, msg, cliente, produto, servico, data, privado, mostrar_home, status_acerto){
	document.getElementById('titulo_dados').textContent = titulo;
	document.getElementById('msg_dados').innerHTML = msg;
	document.getElementById('cliente_dados').textContent = cliente;
	document.getElementById('produto_dados').textContent = produto;
	document.getElementById('servico_dados').textContent = servico;
	document.getElementById('data_dados').textContent = data;
	document.getElementById('privado_dados').textContent = privado;
	document.getElementById('mostrar_home_dados').textContent = mostrar_home;
	document.getElementById('status_acerto_dados').textContent = status_acerto;
	$('#modalDados').modal('show');
}
</script>

<script>
$(function(){
  // clique do botão laranja “IMPORTAR AVALIAÇÕES DO GOOGLE”
  $(document).on('click', '#btnImportarGoogle', function(){
    var place = $('#place_id_google').val().trim();
    var key   = $('#api_key_google').val().trim();

    if(!place || !key){
      alert('Informe Place ID e API Key.');
      return;
    }

    var $btn = $(this);
    var original = $btn.html();
    $btn.prop('disabled', true).html('Importando do Google...');

    $.post('paginas/avaliacoes/importar_google.php', {place_id:place, api_key:key}, function(r){
      try{
        var j = (typeof r === 'string') ? JSON.parse(r) : r;
        alert(j.msg || 'Importação finalizada.');
      }catch(e){
        alert('Importação finalizada.');
      }
      // reload lista
      listar();
    }).fail(function(xhr){
      alert(xhr.responseText || 'Erro ao importar.');
    }).always(function(){
      $btn.prop('disabled', false).html(original);
    });
  });
});
</script>

<script>
function inserir(){
  $('#id').val('');
  $('#titulo').val('');
  $('#mostrar_home').val('Não');
  $('#privado').val('Não');
  $('#id_cliente').val('').trigger('change');
  $('#id_produto').val('').trigger('change');
  $('#id_servico').val('').trigger('change');
  $('#status_acerto').val('Pendente');
  $('#data').val('');
  $('#status_acerto').val('Pendente');
  if (tinymce.get("msg")) tinymce.get("msg").setContent(''); else $('#msg').val('');
  $('#titulo_inserir').text('Nova Anotação');
  $('#modalForm').modal('show');
}

function editar(id, titulo, mostrar_home, privado, id_cliente, id_produto, id_servico, dataISO, msgHTML, status_acerto){
  $('#id').val(id);
  $('#titulo').val(titulo);
  $('#mostrar_home').val(mostrar_home);
  $('#privado').val(privado);
  $('#id_cliente').val(id_cliente).trigger('change');
  $('#id_produto').val(id_produto).trigger('change');
  $('#id_servico').val(id_servico).trigger('change');
  $('#data').val(dataISO || '');
  $('#status_acerto').val(status_acerto || 'Pendente');

  // garante tinymce preenchido
  if (tinymce.get("msg")) tinymce.get("msg").setContent(msgHTML || '');
  else $('#msg').val(msgHTML || '');

  $('#titulo_inserir').text('Editar Anotação');
  $('#modalForm').modal('show');
}

// carrega a lista padrão (ajax.js chama listar() -> listar.php)
if (typeof listar === 'function') { listar(); }
</script>

<script src="js/ajax.js"></script>
