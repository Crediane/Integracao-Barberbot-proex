<?php
require_once __DIR__ . '/avaliacoes/config.php';
require_once __DIR__ . '/../verificar.php';

$pag = 'avaliacoes';
$bb_avaliacoes_loja_slug = defined('BB_AVALIACOES_LOJA_SLUG') ? BB_AVALIACOES_LOJA_SLUG : '';
$bb_avaliacoes_contexto = defined('BB_AVALIACOES_CONTEXTO') ? BB_AVALIACOES_CONTEXTO : '';

if (@$pag == 'ocultar') {
  echo "<script>window.location='<?php echo $bb_painel_public_url; ?>/'</script>";
  exit();
}

/*
  =========================================================
  Carrega o link salvo na tabela avaliacoes_site
  (registro interno com is_config = 1)
  =========================================================
*/
$link_avaliacao_google = '';
try {
  $r = $pdo->query("SELECT link_avaliacao_google FROM avaliacoes_site WHERE is_config = 1 ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
  if ($r && isset($r['link_avaliacao_google'])) {
    $link_avaliacao_google = trim($r['link_avaliacao_google']);
  }
} catch (Throwable $e) {
  $link_avaliacao_google = '';
}

$google_place_id_salvo = '';
$google_api_key_salva = '';
try {
  if (function_exists('getVal')) {
    $google_place_id_salvo = trim((string)getVal($pdo, 'google_place_id'));
    $google_api_key_salva = trim((string)getVal($pdo, 'google_api_key'));
  }
} catch (Throwable $e) {
  $google_place_id_salvo = '';
  $google_api_key_salva = '';
}
?>

<!-- BOTÃO E IMPORTAÇÃO GOOGLE -->
<div class="d-flex flex-wrap align-items-center justify-content-between mb-3">
  <div class="d-flex align-items-center gap-2">
    <a class="btn btn-primary" onclick="inserir()">
      <i class="fa fa-plus"></i> Nova Avaliação
    </a>

    <button class="btn btn-warning" data-toggle="modal" data-target="#modalImportarGoogle">
      <i class="fa fa-google"></i> Importar do Google
    </button>

    <button class="btn btn-info" data-toggle="modal" data-target="#modalLinkAvaliacao">
      <i class="fa fa-link"></i> Link de Avaliação
    </button>
  </div>
</div>

<div class="bs-example widget-shadow" style="padding:15px" id="listar"></div>

<!-- Modal Inserir/Editar -->
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
            <div class="col-md-5">
              <div class="form-group">
                <label>Nome do Cliente *</label>
                <input type="text" class="form-control" id="nome" name="nome" placeholder="Digite o nome" required>
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-group">
                <label>Nota *</label>
                <select class="form-control" id="nota" name="nota" required>
                  <option value="5">⭐⭐⭐⭐⭐ (5)</option>
                  <option value="4">⭐⭐⭐⭐ (4)</option>
                  <option value="3">⭐⭐⭐ (3)</option>
                  <option value="2">⭐⭐ (2)</option>
                  <option value="1">⭐ (1)</option>
                </select>
              </div>
            </div>
            <div class="col-md-3">
              <div class="form-group">
                <label>Origem</label>
                <select class="form-control" id="origem" name="origem">
                  <option value="Site">Site</option>
                  <option value="Google">Google</option>
                  <option value="Instagram">Instagram</option>
                  <option value="Facebook">Facebook</option>
                  <option value="Outro">Outro</option>
                </select>
              </div>
            </div>
            <div class="col-md-2">
              <div class="form-group">
                <label>Status</label>
                <select class="form-control" id="status" name="status">
                  <option value="1">Publicado</option>
                  <option value="0">Rascunho</option>
                </select>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-4">
              <div class="form-group">
                <label>Data da Avaliação</label>
                <input type="date" class="form-control" name="data_avaliacao" id="data_avaliacao">
              </div>
            </div>
            <div class="col-md-8">
              <div class="form-group">
                <label>URL da Foto (opcional)</label>
                <input type="url" class="form-control" name="foto" id="foto" placeholder="https://exemplo.com/foto.jpg">
              </div>
            </div>
          </div>

          <div class="form-group mt-3">
            <label>Comentário</label>
            <textarea name="comentario" id="comentario" class="form-control" placeholder="Digite o comentário do cliente" rows="6"></textarea>
          </div>

          <div class="text-right mt-3">
            <button type="submit" class="btn btn-primary" id="btn_salvar">Salvar Avaliação</button>
            <button class="btn btn-secondary" type="button" id="btn_carregando" style="display:none;" disabled>Salvando...</button>
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
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content p-3">
      <div class="modal-header">
        <h4 class="modal-title">Detalhes da Avaliação</h4>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <div class="row">
          <div class="col-md-8">
            <p><strong>Cliente:</strong> <span id="nome_dados"></span></p>
            <p><strong>Nota:</strong> <span id="nota_dados"></span></p>
            <p><strong>Comentário:</strong></p>
            <div id="comentario_dados" style="background:#f8f9fa; padding:15px; border-radius:5px; white-space:pre-wrap;"></div>
          </div>
          <div class="col-md-4">
            <p><strong>Origem:</strong> <span id="origem_dados"></span></p>
            <p><strong>Data:</strong> <span id="data_dados"></span></p>
            <p><strong>Status:</strong> <span id="status_dados"></span></p>
            <div id="foto_dados_container" style="display:none;">
              <p><strong>Foto:</strong></p>
              <img id="foto_dados" src="" style="max-width:100%; border-radius:8px;">
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Importar Google -->
<div class="modal fade" id="modalImportarGoogle" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-warning">
        <h5 class="modal-title"><i class="fa fa-google"></i> Importar Avaliações do Google</h5>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <div class="form-group">
          <label>Place ID do Google *</label>
          <input type="text" class="form-control" id="place_id_google" placeholder="Ex: ChIJN1t_tDeuEmsRUsoyG83frY4" value="<?= htmlspecialchars($google_place_id_salvo, ENT_QUOTES, 'UTF-8') ?>">
          <small class="form-text text-muted">Cole o Place ID do estabelecimento. Não cole a API Key neste campo.</small>
        </div>
        <div class="form-group">
          <label>API Key do Google *</label>
          <input type="text" class="form-control" id="api_key_google" placeholder="Sua chave da API do Google" value="<?= htmlspecialchars($google_api_key_salva, ENT_QUOTES, 'UTF-8') ?>">
          <small class="form-text text-muted">Cole a API Key do Google Cloud. Ela não é igual ao Place ID.</small>
        </div>
        <button type="button" class="btn btn-warning btn-block" id="btnImportarGoogle">
          <i class="fa fa-download"></i> Importar Avaliações
        </button>
        <div id="msg_importacao" class="mt-3"></div>
      </div>
    </div>
  </div>
</div>

<!-- Modal Link de Avaliação -->
<div class="modal fade" id="modalLinkAvaliacao" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header bg-info text-white">
        <h5 class="modal-title"><i class="fa fa-link"></i> Link das Avaliações (Google)</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>

      <div class="modal-body">
        <div class="form-group">
          <label>Cole aqui o link de avaliação *</label>
          <input
            type="url"
            class="form-control"
            id="link_avaliacao_google"
            placeholder="Ex: https://g.page/r/XXXX/review"
            value="<?= htmlspecialchars($link_avaliacao_google, ENT_QUOTES, 'UTF-8') ?>"
          >
          <small class="form-text text-muted">
            Esse link será usado nas mensagens automáticas de retorno.
          </small>
        </div>

        <div class="d-flex gap-2">
          <button type="button" class="btn btn-info" id="btnSalvarLinkAvaliacao">
            <i class="fa fa-save"></i> Salvar
          </button>

          <button type="button" class="btn btn-secondary" id="btnCopiarLinkAvaliacao">
            <i class="fa fa-copy"></i> Copiar
          </button>

          <a href="#" target="_blank" class="btn btn-outline-info" id="btnAbrirLinkAvaliacao" style="display:none;">
            <i class="fa fa-external-link"></i> Abrir
          </a>
        </div>

        <div id="msg_link_avaliacao" class="mt-3"></div>
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
        <p>Deseja realmente excluir esta avaliação?</p>
        <button type="button" class="btn btn-danger btn-sm" id="btnExcluirConfirmado">Sim, Excluir</button>
        <button type="button" class="btn btn-secondary btn-sm" data-dismiss="modal">Cancelar</button>
      </div>
    </div>
  </div>
</div>

<script>
let idExcluir = null;
var pag = "<?=$pag?>";
var bbLojaSlug = <?= json_encode($bb_avaliacoes_loja_slug, JSON_UNESCAPED_UNICODE) ?>;
var bbContexto = <?= json_encode($bb_avaliacoes_contexto, JSON_UNESCAPED_UNICODE) ?>;

function bbAjaxData(extra){
  var dados = extra || {};
  if (bbLojaSlug) { dados.loja = bbLojaSlug; }
  return dados;
}

function bbAjaxUrl(url){
  if (!bbLojaSlug) { return url; }
  return url + (url.indexOf('?') === -1 ? '?' : '&') + 'loja=' + encodeURIComponent(bbLojaSlug);
}

function listarAvaliacoes(){
  $('#listar').load(bbAjaxUrl('paginas/' + pag + "/listar.php"));
}

// Confirmar exclusão
$(document).on('click', '.btnExcluir', function() {
  idExcluir = $(this).data('id');
  $('#modalConfirmarExclusao').modal('show');
});

$('#btnExcluirConfirmado').click(function() {
  $.ajax({
    url: bbAjaxUrl('paginas/' + pag + '/excluir.php'),
    method: 'POST',
    data: bbAjaxData({ id: idExcluir }),
    success: function(response) {
      if (response.trim() === "Excluído com Sucesso") {
        listarAvaliacoes();
      } else {
        alert('Erro ao excluir: ' + response);
      }
      $('#modalConfirmarExclusao').modal('hide');
    }
  });
});

// Importar do Google
$(document).on('click', '#btnImportarGoogle', function(){
  var place = $('#place_id_google').val().trim();
  var key   = $('#api_key_google').val().trim();

  if (!place || !key) {
    $('#msg_importacao').html('<div class="alert alert-warning">Informe Place ID e API Key.</div>');
    return;
  }

  if (/^AIza[0-9A-Za-z_\-]{10,}$/.test(place)) {
    $('#msg_importacao').html('<div class="alert alert-warning">O campo "Place ID" recebeu uma API Key. Cole o Place ID do estabelecimento.</div>');
    return;
  }

  if (place === key) {
    $('#msg_importacao').html('<div class="alert alert-warning">Place ID e API Key não podem ser iguais. No print, o mesmo código foi colocado nos dois campos.</div>');
    return;
  }

  if (/^ChIJ/i.test(key)) {
    $('#msg_importacao').html('<div class="alert alert-warning">O campo "API Key" recebeu um Place ID. Cole a chave criada no Google Cloud.</div>');
    return;
  }

  var $btn = $(this);
  var original = $btn.html();
  $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Importando...');
  $('#msg_importacao').html('<div class="alert alert-info">Importando avaliações...</div>');

  $.post(bbAjaxUrl('paginas/' + pag + '/importar_google.php'), bbAjaxData({ place_id: place, api_key: key }), function(r){
    var j = r;
    try { j = (typeof r === 'string') ? JSON.parse(r) : r; } catch(e) {}

    if (j && j.status === 'error') {
      $('#msg_importacao').html('<div class="alert alert-danger">'+ (j.msg || 'Erro ao importar.') +'</div>');
    } else {
      $('#msg_importacao').html('<div class="alert alert-success">'+ (j && j.msg ? j.msg : 'Importação finalizada.') +'</div>');
      listarAvaliacoes();
      setTimeout(function(){ $('#modalImportarGoogle').modal('hide'); }, 2000);
    }
  }).fail(function(xhr){
    var msg = (xhr.responseJSON && xhr.responseJSON.msg) ? xhr.responseJSON.msg : (xhr.responseText || 'Erro ao importar.');
    $('#msg_importacao').html('<div class="alert alert-danger">'+ msg +'</div>');
  }).always(function(){
    $btn.prop('disabled', false).html(original);
  });
});

// ===== Link de Avaliação - salvar / copiar / abrir =====
function atualizarBotaoAbrirLink(){
  var link = $('#link_avaliacao_google').val().trim();
  if (link) {
    $('#btnAbrirLinkAvaliacao').attr('href', link).show();
  } else {
    $('#btnAbrirLinkAvaliacao').hide();
  }
}

$('#modalLinkAvaliacao').on('shown.bs.modal', function () {
  $('#msg_link_avaliacao').html('');
  atualizarBotaoAbrirLink();
  $('#link_avaliacao_google').trigger('focus');
});

$(document).on('input', '#link_avaliacao_google', function(){
  atualizarBotaoAbrirLink();
});

$(document).on('click', '#btnSalvarLinkAvaliacao', function(){
  var link = $('#link_avaliacao_google').val().trim();

  if (link && !/^https?:\/\/.+/i.test(link)) {
    $('#msg_link_avaliacao').html('<div class="alert alert-warning">A URL deve começar com http:// ou https://</div>');
    return;
  }

  var $btn = $(this);
  var original = $btn.html();
  $btn.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Salvando...');

  $.post(bbAjaxUrl('paginas/' + pag + '/salvar_link.php'), bbAjaxData({ link: link }), function(resp){
    if (resp.trim() === 'Salvo com Sucesso') {
      $('#msg_link_avaliacao').html('<div class="alert alert-success">Link salvo com sucesso!</div>');
      atualizarBotaoAbrirLink();
    } else {
      $('#msg_link_avaliacao').html('<div class="alert alert-danger">'+ resp +'</div>');
    }
  }).fail(function(xhr){
    var msg = (xhr && xhr.responseText) ? xhr.responseText : 'Erro ao salvar o link.';
    $('#msg_link_avaliacao').html('<div class="alert alert-danger">'+ msg +'</div>');
  }).always(function(){
    $btn.prop('disabled', false).html(original);
  });
});

function copiarParaClipboard(texto){
  if (navigator.clipboard && window.isSecureContext) {
    return navigator.clipboard.writeText(texto);
  }
  return new Promise(function(resolve, reject){
    try {
      var $temp = $('<input>');
      $('body').append($temp);
      $temp.val(texto).select();
      document.execCommand('copy');
      $temp.remove();
      resolve();
    } catch (e) {
      reject(e);
    }
  });
}

$(document).on('click', '#btnCopiarLinkAvaliacao', function(){
  var link = $('#link_avaliacao_google').val().trim();
  if (!link) {
    $('#msg_link_avaliacao').html('<div class="alert alert-warning">Cole um link primeiro.</div>');
    return;
  }

  copiarParaClipboard(link).then(function(){
    $('#msg_link_avaliacao').html('<div class="alert alert-success">Link copiado!</div>');
  }).catch(function(){
    $('#msg_link_avaliacao').html('<div class="alert alert-danger">Não foi possível copiar automaticamente. Copie manualmente.</div>');
  });
});

// Submit do formulário
$('#form').submit(function (event) {
  event.preventDefault();
  var formData = new FormData(this);
  if (bbLojaSlug) { formData.append('loja', bbLojaSlug); }
  $('#btn_salvar').hide();
  $('#btn_carregando').show();

  $.ajax({
    url: bbAjaxUrl('paginas/' + pag + '/salvar.php'),
    type: 'POST',
    data: formData,
    success: function (mensagem) {
      $('#mensagem').text('').removeClass();
      if (mensagem.trim() == "Salvo com Sucesso") {
        alert(mensagem);
        listarAvaliacoes();
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

// Visualizar detalhes
function ver(id, nome, nota, comentario, origem, data, foto, status){
  document.getElementById('nome_dados').textContent = nome;

  var estrelas = '';
  nota = parseInt(nota || 0, 10);
  for (var i = 1; i <= 5; i++) {
    estrelas += i <= nota ? '⭐' : '☆';
  }
  document.getElementById('nota_dados').innerHTML = estrelas + ' (' + nota + '/5)';

  document.getElementById('comentario_dados').textContent = comentario && comentario.trim() !== '' ? comentario : 'Sem comentário';
  document.getElementById('origem_dados').textContent = origem || '-';
  document.getElementById('data_dados').textContent = data || 'Não informada';

  var statusTexto = String(status) === '1'
    ? '<span class="badge badge-success">Publicado</span>'
    : '<span class="badge badge-secondary">Rascunho</span>';
  document.getElementById('status_dados').innerHTML = statusTexto;

  if (foto && foto.trim() !== '') {
    document.getElementById('foto_dados').src = foto;
    document.getElementById('foto_dados_container').style.display = 'block';
  } else {
    document.getElementById('foto_dados_container').style.display = 'none';
  }

  $('#modalDados').modal('show');
}

// Inserir nova avaliação
function inserir(){
  $('#id').val('');
  $('#nome').val('');
  $('#nota').val('5');
  $('#comentario').val('');
  $('#origem').val('Site');
  $('#data_avaliacao').val('');
  $('#foto').val('');
  $('#status').val('1');
  $('#titulo_inserir').text('Nova Avaliação');
  $('#modalForm').modal('show');
}

// Editar avaliação
function editar(id, nome, nota, comentario, origem, dataISO, foto, status){
  $('#id').val(id);
  $('#nome').val(nome);
  $('#nota').val(nota);
  $('#comentario').val(comentario || '');
  $('#origem').val(origem);
  $('#data_avaliacao').val(dataISO || '');
  $('#foto').val(foto || '');
  $('#status').val(status);
  $('#titulo_inserir').text('Editar Avaliação');
  $('#modalForm').modal('show');
}

// Carregar lista
$(function(){
  listarAvaliacoes();
});
</script>

<script src="js/ajax.js"></script>
