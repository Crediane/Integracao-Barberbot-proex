<?php   
require_once __DIR__ . '/_context_bootstrap.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../conexao.php';

$pag = 'foto_admin';
?>
<div class="mb-3">      
  <a class="btn btn-primary" onclick="inserir()">
    <i class="fa fa-plus" aria-hidden="true"></i> Nova Foto
  </a>
</div>

<div class="bs-example widget-shadow bg-white p-3 rounded" id="listar">
  <!-- conteúdo será carregado via JS -->
</div>

<!-- Modal Form -->
<div class="modal fade" id="modalForm" tabindex="-1" role="dialog" aria-labelledby="tituloModal" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content bg-light">
      <div class="modal-header">
        <h4 class="modal-title" id="tituloModal">Nova Foto</h4>
        <button id="btn-fechar" type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <form id="form-foto-admin">
        <div class="modal-body">

          <div class="form-group">
            <label for="titulo">Título</label>
            <input type="text" class="form-control" name="titulo" id="titulo" required>
          </div>

          <div class="form-group">
            <label for="imagem">Imagem</label>
            <input type="file" class="form-control" name="imagem" id="imagem" onchange="previewImagem()">
            <img id="preview" src="#" style="display:none; width: 100%; height: auto; margin-top: 10px; border-radius: 5px;" alt="Pré-visualização" />
          </div>

          <input type="hidden" name="id" id="id">
          <small><div id="mensagem" class="mt-2 text-danger"></div></small>
        </div>
        <div class="modal-footer">
          <button type="submit" id="btn-salvar" class="btn btn-primary">Salvar</button>
        </div>
      </form>

    </div>
  </div>
</div>

<!-- CSS e JS do modal de confirmação -->
<link rel="stylesheet" href="/sistema/painel/js/sweetalert1.min.css">
<script src="/sistema/painel/js/sweetalert2.all.min.js"></script>
<link rel="stylesheet" href="/sistema/painel/js/font-awesome.css">




<script>
function listar() {
  $.ajax({
    url: 'paginas/foto_admin/listar.php',
    method: 'POST',
    success: function(data) {
      $('#listar').html(data);
    },
    error: function(){
      $('#listar').html('<div class="alert alert-danger">Falha ao carregar a lista.</div>');
    }
  });
}

function inserir() {
  $('#id').val('');
  $('#titulo').val('');
  $('#imagem').val('');
  $('#preview').hide().attr('src', '#');
  $('#mensagem').text('');
  $('#tituloModal').text('Nova Foto');
  $('#modalForm').modal('show');
}

function editar(id, titulo) {
  $('#id').val(id);
  $('#titulo').val(titulo);
  $('#imagem').val('');
  $('#preview').hide().attr('src', '#');
  $('#mensagem').text('');
  $('#tituloModal').text('Editar Foto');
  $('#modalForm').modal('show');
}

function previewImagem() {
  const file = document.getElementById('imagem').files[0];
  const preview = document.getElementById('preview');
  if (file) {
    preview.src = URL.createObjectURL(file);
    preview.style.display = 'block';
  } else {
    preview.src = '#';
    preview.style.display = 'none';
  }
}

$(document).ready(function() {
  // Previna binds duplicados vindos de outros scripts globais
  $(document).off('submit.fotoadmin', '#form-foto-admin');

  $(document).on('submit.fotoadmin', '#form-foto-admin', function (event) {
    event.preventDefault();

    // trava anti-duplo-clique
    const $btn = $('#btn-salvar');
    if ($btn.prop('disabled')) return;
    $btn.prop('disabled', true);

    var formData = new FormData(this);

    $.ajax({
      url: 'paginas/foto_admin/salvar.php',
      type: 'POST',
      data: formData,
      cache: false,
      contentType: false,
      processData: false
    }).done(function (mensagem) {
      $('#mensagem').removeClass().text('');
      // mantém a mesma checagem de sucesso usada no restante do sistema
      if (mensagem && mensagem.toLowerCase().indexOf("salvo com sucesso") !== -1) {
        listar();
        $('#modalForm').modal('hide');
      } else {
        $('#mensagem').addClass('text-danger').text(mensagem || 'Erro inesperado.');
      }
    }).fail(function () {
      $('#mensagem').addClass('text-danger').text('Falha ao comunicar com o servidor.');
    }).always(function () {
      $btn.prop('disabled', false);
    });
  });

  listar();
});

/**
 * Modal de confirmação antes de excluir.
 * Mantém a lógica original: só chama excluir(id) se o usuário confirmar.
 */
function excluirFoto(id) {
  if (typeof Swal !== 'undefined' && Swal && typeof Swal.fire === 'function') {
    Swal.fire({
      title: 'Excluir foto?',
      text: 'Esta ação não pode ser desfeita.',
      icon: 'warning',
      showCancelButton: true,
      confirmButtonText: 'Excluir',
      cancelButtonText: 'Cancelar',
      reverseButtons: true,
      focusCancel: true
    }).then(function(result) {
      if (result.isConfirmed) {
        excluir(id); // função já existente no js/ajax.js
      }
    });
  } else {
    if (confirm('Tem certeza que deseja excluir esta foto?')) {
      excluir(id);
    }
  }
}
</script>

<script type="text/javascript">var pag = "foto_admin";</script>
<script src="js/ajax.js"></script>

