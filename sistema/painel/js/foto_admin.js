// foto_admin.js - AJAX + JavaScript completo
function listarFotos() {
  $.ajax({
    url: 'paginas/foto_admin/listar.php',
    method: 'POST',
    success: function(data) {
      $('#listar-fotos').html(data);
    }
  });
}

$(document).ready(function() {
  listarFotos();

  $('#form-foto').on('submit', function(e) {
    e.preventDefault();
    var formData = new FormData(this);

    $.ajax({
      url: 'paginas/foto_admin/salvar.php',
      type: 'POST',
      data: formData,
      success: function(resp) {
        $('#mensagem').text('');
        if (resp.includes('Salvo')) {
          $('#modalFoto').modal('hide');
          listarFotos();
          $('#form-foto')[0].reset();
          $('#preview').hide();
        } else {
          $('#mensagem').text(resp).addClass('text-danger');
        }
      },
      cache: false,
      contentType: false,
      processData: false
    });
  });
});

function editar(id, titulo) {
  $('#id').val(id);
  $('#titulo').val(titulo);
  $('#imagem').val('');
  $('#preview').hide();
  $('#tituloModal').text('Editar Foto');
  $('#modalFoto').modal('show');
}

function excluirFoto(id) {
  if (confirm("Deseja excluir a foto?")) {
    $.post('paginas/foto_admin/excluir.php', { id: id }, function(resp) {
      alert(resp);
      listarFotos();
    });
  }
}

function previewImagem() {
  var input = document.getElementById('imagem');
  var preview = document.getElementById('preview');
  const file = input.files[0];
  if (file) {
    preview.src = URL.createObjectURL(file);
    preview.style.display = 'block';
  } else {
    preview.src = '';
    preview.style.display = 'none';
  }

}

document.querySelectorAll('.card-header').forEach(header => {
  header.addEventListener('click', function() {
    const card = this.parentElement;
    
    // Alternar a classe "active"
    card.classList.toggle('active');
    
    const body = card.querySelector('.card-body');
    if (body.style.display === "block") {
      body.style.display = "none";
    } else {
      body.style.display = "block";
    }
    
    const icon = this.querySelector('.toggle-icon');
    icon.textContent = icon.textContent === '+' ? '-' : '+';
  });
});