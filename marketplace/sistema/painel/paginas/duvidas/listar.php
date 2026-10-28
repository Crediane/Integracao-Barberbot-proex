<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/_global_connection.php';

$tabela = 'duvidas';

// ---------------------------
// Sessão do usuário
// ---------------------------
$nivel_usuario = $_SESSION['nivel'] ?? '';
$email_usuario = $_SESSION['email'] ?? '';
$id_usuario    = $_SESSION['id'] ?? '';

// ✅ Fallback: se não tem email na sessão, pega do banco pelo ID (sem mudar lógica)
if (($email_usuario === '' || $email_usuario === null) && !empty($id_usuario) && isset($pdo)) {
  try {
    $stEmail = $pdo->prepare("SELECT email FROM usuarios WHERE id = :id LIMIT 1");
    $stEmail->execute([':id' => (int)$id_usuario]);
    $rowEmail = $stEmail->fetch(PDO::FETCH_ASSOC);
    if (!empty($rowEmail['email'])) {
      $email_usuario = (string)$rowEmail['email'];
      $_SESSION['email'] = $email_usuario; // mantém para as outras páginas
    }
  } catch (Throwable $e) {
    // não quebra
  }
}

/* ✅ SOMENTE dono do sistema pode gerenciar tutoriais */
$admin_email = 'jacy@jc.tec.br';
$isOwner = (strtolower(trim($email_usuario)) === strtolower(trim($admin_email)));

$itens_pag = 12;

$pagina = isset($_POST['pagina']) ? (int)$_POST['pagina'] : 0;
if ($pagina < 0) $pagina = 0;

$limite = $pagina * $itens_pag;

// ---------------------------
// Tutoriais SEMPRE no MASTER
// ---------------------------
$res = [];
$total_reg = 0;

try {
  $limiteSql = (int)$limite;
  $itensSql  = (int)$itens_pag;

  $query = $pdoDuvidasGlobal->query("SELECT * FROM tutoriais ORDER BY id DESC LIMIT $limiteSql, $itensSql");
  $res = $query->fetchAll(PDO::FETCH_ASSOC);
  $total_reg = count($res);
} catch (Throwable $e) {
  $res = [];
  $total_reg = 0;
}
?>

<style>
.duvidas-grid-row { margin-left: -10px; margin-right: -10px; }
.duvidas-col { padding-left: 10px; padding-right: 10px; margin-bottom: 20px; }

.duvidas-panel {
  height: 100%;
  display: flex;
  flex-direction: column;
}

.duvidas-panel .panel-body { flex: 1; }

.duvidas-desc {
  margin-bottom: 12px;
  overflow: hidden;
  display: -webkit-box;
  -webkit-line-clamp: 5;
  -webkit-box-orient: vertical;
}

.duvidas-actions .btn { margin-left: 6px; }
.duvidas-video-btn { margin-top: 8px; display: inline-block; }
</style>

<?php if ($total_reg > 0) { ?>

  <div class="row duvidas-grid-row">
    <?php
    $count = 0;

    foreach ($res as $dados) {
      $id        = $dados['id'] ?? '';
      $titulo    = $dados['titulo'] ?? '';
      $descricao = $dados['descricao'] ?? '';
      $link_video= $dados['link_video'] ?? '';
      $dataRaw   = $dados['data'] ?? '';
      $data      = $dataRaw ? date('d/m/Y', strtotime($dataRaw)) : '';

      // segurança para JS inline (mantém sua lógica)
      $titulo_js    = addslashes($titulo);
      $descricao_js = addslashes($descricao);
      $link_js      = addslashes($link_video);

      $count++;
    ?>

      <div class="col-xs-12 col-sm-6 col-md-4 duvidas-col">
        <div class="panel panel-default duvidas-panel">

          <div class="panel-heading">
            <strong><?php echo htmlspecialchars($titulo); ?></strong>
            <?php if ($data !== '') { ?>
              <small class="text-muted"><?php echo " (" . htmlspecialchars($data) . ")"; ?></small>
            <?php } ?>
          </div>

          <div class="panel-body">
            <div class="duvidas-desc">
              <?php echo nl2br(htmlspecialchars($descricao)); ?>
            </div>

            <?php if ($link_video != '') { ?>
              <a href="#" class="btn btn-xs btn-default duvidas-video-btn"
                 onclick="return abrirVideo('<?php echo $link_js; ?>');">
                <i class="fa fa-play"></i> Ver Vídeo
              </a>
            <?php } ?>
          </div>

          <?php if ($isOwner) { ?>
            <div class="panel-footer text-right duvidas-actions">
              <a href="#"
                 onclick="return editar('<?php echo $id; ?>', '<?php echo $titulo_js; ?>', '<?php echo $descricao_js; ?>', '<?php echo $link_js; ?>');"
                 class="btn btn-sm btn-primary">
                <i class="fa fa-edit"></i>
              </a>

              <a href="#"
                 onclick="return excluir('<?php echo $id; ?>');"
                 class="btn btn-sm btn-danger">
                <i class="fa fa-trash"></i>
              </a>
            </div>
          <?php } ?>

        </div>
      </div>

      <?php
      if ($count % 3 == 0 && $count < $total_reg) {
        echo '</div><div class="row duvidas-grid-row">';
      }
      ?>

    <?php } ?>
  </div>

<?php } else { ?>

  <div class="row">
    <div class="col-md-12">
      <p class="text-muted">Nenhum tutorial encontrado.</p>
    </div>
  </div>

<?php } ?>

<?php
// Paginação pelo MASTER
$total_itens = 0;
try {
  $total_query = $pdoDuvidasGlobal->query("SELECT COUNT(*) as total FROM tutoriais");
  $total_result = $total_query->fetch(PDO::FETCH_ASSOC);
  $total_itens = (int)($total_result['total'] ?? 0);
} catch (Throwable $e) {
  $total_itens = 0;
}

$num_paginas = ($itens_pag > 0) ? (int)ceil($total_itens / $itens_pag) : 1;
if ($num_paginas < 1) $num_paginas = 1;
?>

<div class="row" align="center">
  <nav aria-label="Page navigation">
    <ul class="pagination">
      <?php for ($i = 0; $i < $num_paginas; $i++): ?>
        <?php $estilo = ($i == $pagina) ? 'active' : ''; ?>
        <li class="page-item <?= $estilo ?>">
          <a href="#"
             class="page-link"
             onclick="listar(<?= (int)$i ?>); return false;">
            <?= (int)($i + 1) ?>
          </a>
        </li>
      <?php endfor; ?>
    </ul>
  </nav>
</div>

<script>
function editar(id, titulo, descricao, link) {
  if (typeof event !== 'undefined' && event) {
    if (event.preventDefault) event.preventDefault();
    event.returnValue = false;
  }

  $('#id').val(id);
  $('#titulo').val(titulo);
  $('#descricao').val(descricao);
  $('#link_video').val(link);

  $('#modalCadastro').modal('show');
  $('#tituloModal').text('Editar Tutorial');

  return false;
}

function excluir(id) {
  if (typeof event !== 'undefined' && event) {
    if (event.preventDefault) event.preventDefault();
    event.returnValue = false;
  }

  if (confirm('Deseja realmente excluir este tutorial?')) {
    $.post('paginas/duvidas/excluir.php', { id: id }, function(retorno) {
      if (String(retorno).trim() === "Excluído com Sucesso") {
        if (typeof listar === 'function') listar(0);
      } else {
        alert(retorno);
      }
    });
  }

  return false;
}

function __garantirModalVideo() {
  if ($('#modalVideo').length) return;

  var html = ''
    + '<div class="modal fade" id="modalVideo" tabindex="-1" role="dialog">'
    + '  <div class="modal-dialog modal-lg" role="document">'
    + '    <div class="modal-content">'
    + '      <div class="modal-header">'
    + '        <h4 class="modal-title">Visualizar Vídeo</h4>'
    + '        <button type="button" class="close" data-dismiss="modal">&times;</button>'
    + '      </div>'
    + '      <div class="modal-body">'
    + '        <div class="embed-responsive embed-responsive-16by9">'
    + '          <iframe class="embed-responsive-item" id="videoFrame" src="" allowfullscreen></iframe>'
    + '        </div>'
    + '      </div>'
    + '    </div>'
    + '  </div>'
    + '</div>';

  $('body').append(html);

  $(document).off('hidden.bs.modal', '#modalVideo').on('hidden.bs.modal', '#modalVideo', function () {
    $('#videoFrame').attr('src', '');
  });
}

function abrirVideo(link) {
  if (typeof event !== 'undefined' && event) {
    if (event.preventDefault) event.preventDefault();
    if (event.stopPropagation) event.stopPropagation();
    event.returnValue = false;
  }

  __garantirModalVideo();

  var embedLink = String(link || '').trim();
  if (!embedLink) return false;

  if (!/^https?:\/\//i.test(embedLink)) {
    embedLink = 'https://' + embedLink.replace(/^\/+/, '');
  }

  if (embedLink.indexOf('drive.google.com') !== -1) {
    var idMatch = embedLink.match(/[-\w]{25,}/);
    if (idMatch && idMatch[0]) {
      embedLink = 'https://drive.google.com/file/d/' + idMatch[0] + '/preview';
    }
  }

  if (embedLink.indexOf('youtube.com') !== -1 || embedLink.indexOf('youtu.be') !== -1) {
    var id = '';

    if (embedLink.indexOf('youtu.be/') !== -1) {
      id = embedLink.split('youtu.be/')[1].split(/[?&]/)[0];
    } else if (embedLink.indexOf('v=') !== -1) {
      id = embedLink.split('v=')[1].split('&')[0];
    } else if (embedLink.indexOf('/embed/') !== -1) {
      id = embedLink.split('/embed/')[1].split(/[?&]/)[0];
    }

    if (id) {
      embedLink = 'https://www.youtube.com/embed/' + id;
    }
  }

  $('#videoFrame').attr('src', embedLink);
  $('#modalVideo').modal('show');

  return false;
}
</script>
