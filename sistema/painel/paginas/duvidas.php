<?php
require_once __DIR__ . '/_context_bootstrap.php';
require_once(__DIR__ . '/../../conexao.php');
$pag = 'duvidas';
$id_usuario    = $_SESSION['id'] ?? 0;
$nivel_usuario = $_SESSION['nivel'] ?? '';
$email_usuario = $_SESSION['email'] ?? '';

/**
 * ✅ FIX SEM MUDAR LÓGICA:
 * - Se o login não setou email na sessão, pega do banco pelo ID logado
 * - Isso só completa a sessão e não muda fluxo de nada
 */
if (($email_usuario === '' || $email_usuario === null) && !empty($id_usuario) && isset($pdo) && $pdo instanceof PDO) {
  try {
    $st = $pdo->prepare("SELECT email, nivel FROM usuarios WHERE id = :id LIMIT 1");
    $st->bindValue(':id', (int)$id_usuario, PDO::PARAM_INT);
    $st->execute();
    $u = $st->fetch(PDO::FETCH_ASSOC);

    if ($u) {
      if (!empty($u['email'])) {
        $email_usuario = (string)$u['email'];
        $_SESSION['email'] = $email_usuario;
      }
      if ($nivel_usuario === '' && !empty($u['nivel'])) {
        $nivel_usuario = (string)$u['nivel'];
        $_SESSION['nivel'] = $nivel_usuario;
      }
    }
  } catch (Throwable $e) {
    // não quebra
  }
}

/* ✅ SOMENTE dono do sistema */
$admin_email = 'jacy@jc.tec.br';
$isOwner = (strtolower((string)$email_usuario) === strtolower((string)$admin_email));
?>

<div class="main-page margin-mobile">
  <div class="row mb-4">
    <div class="col-md-6">
      <h4 class="mb-0"><i class="fa fa-question-circle"></i> Tutoriais e Dúvidas</h4>
    </div>

    <div class="col-md-6 text-right">
      <?php if ($isOwner) { ?>
        <button class="btn btn-success" data-toggle="modal" data-target="#modalTutorial">
          <i class="fa fa-plus"></i> Novo Tutorial
        </button>

        <button class="btn btn-default" style="margin-left: 10px;" data-toggle="modal" data-target="#modalDuvidasRecebidas">
          <i class="fa fa-comments text-muted"></i> <strong>Dúvidas Recebidas</strong>
        </button>
      <?php } ?>

      <?php if (!$isOwner) { ?>
        <button class="btn btn-info" data-toggle="modal" data-target="#modalPergunta">
          <i class="fa fa-comment"></i> Fazer uma Pergunta
        </button>
      <?php } ?>
    </div>
  </div>

  <div class="row">
    <div class="col-md-12">
      <div id="listar"></div>
    </div>
  </div>
</div>
</div>

<!-- Modal Pergunta -->
<div class="modal fade" id="modalPergunta" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Fazer uma Pergunta</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="form-pergunta">
        <div class="modal-body">
          <div class="form-group">
            <label for="pergunta">Digite sua pergunta:</label>
            <textarea class="form-control" name="pergunta" id="pergunta" rows="4" required></textarea>
          </div>
          <small><div id="mensagem-pergunta" class="text-center"></div></small>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Enviar</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Cadastro (Novo/Editar Tutorial) -->
<div class="modal fade" id="modalCadastro" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="tituloModal">Novo Tutorial</h4>
        <button type="button" class="close" data-dismiss="modal">&times;</button>
      </div>
      <form id="form-tutorial">
        <div class="modal-body">
          <input type="hidden" name="id" id="id">

          <div class="form-group">
            <label for="titulo">Título</label>
            <input type="text" class="form-control" name="titulo" id="titulo" required>
          </div>

          <div class="form-group">
            <label for="descricao">Descrição</label>
            <textarea class="form-control" name="descricao" id="descricao" rows="5" required></textarea>
          </div>

          <div class="form-group">
            <label for="link_video">Link do Vídeo</label>
            <input type="text" class="form-control" name="link_video" id="link_video">
          </div>

          <small><div id="mensagem-tutorial" class="text-center"></div></small>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Salvar</button>
          <button type="button" class="btn btn-default" data-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal de Resposta -->
<div class="modal fade" id="modalResposta" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <form id="form-resposta">
        <div class="modal-header">
          <h5 class="modal-title">Responder Dúvida</h5>
          <button type="button" class="close" data-dismiss="modal">&times;</button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="id_pergunta" id="id_pergunta">

          <div class="form-group">
            <label>Pergunta:</label>
            <p id="texto_pergunta" class="font-italic text-muted"></p>
          </div>

          <div class="form-group">
            <label>Resposta:</label>
            <textarea name="resposta" id="resposta" class="form-control" rows="4" required></textarea>
          </div>
          <div id="mensagem-resposta" class="text-center small text-danger"></div>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Salvar</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Tutorial (Administrador - dono do sistema) -->
<div class="modal fade" id="modalTutorial" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title">Novo Tutorial</h5>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <form id="form-tutorial">
        <div class="modal-body">
          <div class="form-group">
            <label for="titulo">Título</label>
            <input type="text" name="titulo" id="titulo" class="form-control" required>
          </div>
          <div class="form-group">
            <label for="descricao">Descrição</label>
            <textarea name="descricao" id="descricao" class="form-control" rows="5" required></textarea>
          </div>
          <div class="form-group">
            <label for="link_video">Link do Vídeo (YouTube)</label>
            <input type="text" name="link_video" id="link_video" class="form-control">
          </div>
          <small><div id="mensagem-tutorial" class="text-center"></div></small>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-success">Salvar</button>
          <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancelar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Modal Dúvidas Recebidas (somente dono do sistema) -->
<div class="modal fade" id="modalDuvidasRecebidas" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title"><i class="fa fa-comments"></i> Dúvidas Recebidas</h4>
        <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
      </div>
      <div class="modal-body">
        <?php
        $stmt = $pdo->query("SELECT * FROM perguntas ORDER BY id DESC");
        $perguntas = $stmt->fetchAll(PDO::FETCH_ASSOC);

        if (count($perguntas) == 0) {
          echo '<p class="text-muted text-center">Nenhuma dúvida enviada ainda.</p>';
        }

        foreach ($perguntas as $linha) {
          $id = $linha['id'];
          $pergunta = $linha['pergunta'];
          $resposta = $linha['resposta'];
        ?>
          <div class="panel panel-default">
            <div class="panel-body">
              <p><strong>Pergunta:</strong> <?php echo $pergunta; ?></p>
              <?php if (!empty($resposta)) { ?>
                <p><strong>Resposta:</strong> <?php echo $resposta; ?></p>
              <?php } elseif ($isOwner) { ?>
                <button class="btn btn-sm btn-info"
                        onclick="abrirResposta('<?php echo $id; ?>', '<?php echo addslashes($pergunta); ?>')">
                  <i class="fa fa-reply"></i> Responder
                </button>
              <?php } ?>
            </div>
          </div>
        <?php } ?>
      </div>
    </div>
  </div>
</div>

<script type="text/javascript">
$(document).ready(function(){
  listar();

  $('#form-pergunta').submit(function(event) {
    event.preventDefault();

    var formData = new FormData(this);

    $.ajax({
      url: 'paginas/duvidas/salvar_pergunta.php',
      type: 'POST',
      data: formData,
      cache: false,
      contentType: false,
      processData: false,

      success: function(resp) {
        $('#mensagem-pergunta').removeClass().text(resp);

        if (String(resp).toLowerCase().includes("sucesso")) {
          $('#form-pergunta')[0].reset();
          if (typeof listar === "function") listar();
          setTimeout(() => {
            $('#modalPergunta').modal('hide');
            $('#mensagem-pergunta').text('');
          }, 2000);
        }
      },

      error: function() {
        $('#mensagem-pergunta').addClass('text-danger').text('Erro ao enviar.');
      }
    });
  });

  $('#form-tutorial').submit(function(event) {
    event.preventDefault();
    var formData = new FormData(this);

    $.ajax({
      url: 'paginas/duvidas/salvar.php',
      type: 'POST',
      data: formData,
      success: function(resp){
        $('#mensagem-tutorial').removeClass().text(resp);
        $('#form-tutorial')[0].reset();
        listar();
        setTimeout(() => { $('#modalTutorial').modal('hide'); }, 2000);
      },
      error: function(){
        $('#mensagem-tutorial').addClass('text-danger').text('Erro ao salvar.');
      },
      cache: false,
      contentType: false,
      processData: false,
    });
  });
});

function listar(pagina = 0){
  $.post('paginas/duvidas/listar.php', {pagina}, function(result){
    $('#listar').html(result);
  });
}

$('#form-resposta').submit(function(e){
  e.preventDefault();
  $.post('paginas/duvidas/responder.php', $(this).serialize(), function(retorno){
    $('#mensagem-resposta').text(retorno);
    if (String(retorno).toLowerCase().includes("sucesso")) {
      $('#modalResposta').modal('hide');
      listar();
    }
  });
});

function abrirResposta(id, pergunta) {
  $('#id_pergunta').val(id);
  $('#resposta').val('');
  $('#texto_pergunta').text(pergunta);
  $('#modalResposta').modal('show');
}

function excluir(id) {
  if (confirm('Deseja realmente excluir este tutorial?')) {
    $.post('paginas/duvidas/excluir.php', { id: id }, function(retorno) {
      if (String(retorno).trim() === "Excluído com Sucesso") {
        listar();
      } else {
        alert(retorno);
      }
    });
  }
}
</script>
