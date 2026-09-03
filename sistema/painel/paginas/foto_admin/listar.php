<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';

/**
 * Detecta a coluna de status para exibir o toggle.
 * Prioriza `ativo`; se não existir, tenta `status`.
 * Mantém a lógica original.
 */
function detectaColStatus(PDO $pdo): array {
    try {
        $colAtivo  = $pdo->query("SHOW COLUMNS FROM foto_admin LIKE 'ativo'")->fetch(PDO::FETCH_ASSOC);
        if ($colAtivo) return ['col' => 'ativo'];
        $colStatus = $pdo->query("SHOW COLUMNS FROM foto_admin LIKE 'status'")->fetch(PDO::FETCH_ASSOC);
        if ($colStatus) return ['col' => 'status'];
    } catch (Throwable $e) {
        // silencioso para não quebrar a listagem
    }
    return ['col' => null];
}

$colStatus = detectaColStatus($pdo)['col'];

/* Carrega os registros (mantém a query original) */
try {
    $res   = $pdo->query("SELECT * FROM foto_admin ORDER BY id DESC");
    $dados = $res ? $res->fetchAll(PDO::FETCH_ASSOC) : [];
} catch (Throwable $e) {
    $dados = [];
}

/* Helper de escape */
if (!function_exists('h')) {
    function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
}
?>
<div class="container-fluid">
  <div class="row" id="listar-fotos">
    <?php if (!empty($dados)): ?>
      <?php foreach ($dados as $d): ?>
        <?php
          $id      = isset($d['id']) ? (int)$d['id'] : 0;
          $titulo  = isset($d['titulo']) ? (string)$d['titulo'] : '';
          $tituloH = h($titulo);
          $imgFile = isset($d['imagem']) ? basename($d['imagem']) : '';
          $src = 'img/foto_admin/' . ($imgFile !== '' ? $imgFile : 'sem-foto.jpg');

          // status atual (se houver coluna)
          $isActive = null;
          $acaoBtn  = null;
          $labelBtn = null;
          $classBtn = null;
          $badge    = '';

          if ($colStatus !== null && array_key_exists($colStatus, $d)) {
              $val = $d[$colStatus];

              // Heurística de ativo (1/ativo/active)
              $isActive = ((string)$val === '1')
                       || (strcasecmp((string)$val, 'ativo')  === 0)
                       || (strcasecmp((string)$val, 'active') === 0);

              if ($isActive) {
                  $acaoBtn  = 'Desativar';
                  $labelBtn = 'Desativar';
                  $classBtn = 'btn-warning'; // BS3
                  // Mantém classes do seu tema (badge-success/ml-2). Se não existir no CSS, vira badge padrão BS3.
                  $badge    = '<span class="badge badge-success ml-2">Ativo</span>';
              } else {
                  $acaoBtn  = 'Ativar';
                  $labelBtn = 'Ativar';
                  $classBtn = 'btn-default'; // BS3 (em vez de btn-secondary)
                  $badge    = '<span class="badge badge-default ml-2">Inativo</span>';
              }
          }
        ?>
        <div class="col-lg-3 col-md-4 col-sm-6 mb-4">
          <div class="card shadow-sm h-100 border" data-id="<?= $id ?>">
            <img
              src="<?= h($src) ?>"
              class="card-img-top img-fluid"
              style="height:255px; object-fit:cover;"
              alt="<?= $tituloH !== '' ? $tituloH : 'Foto' ?>"
              loading="lazy"
              decoding="async"
            >
            <div class="card-body text-center bg-light">
              <h6 class="card-title text-dark mb-2" title="<?= $tituloH ?>">
                <?= $tituloH ?>
                <?= $badge ?>
              </h6>
              <div class="btn-group" role="group" aria-label="Ações">
                <!-- Editar -->
                <button
                  type="button"
                  class="btn btn-sm btn-primary"
                  onclick='editar(<?= $id ?>, <?= json_encode($titulo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>)'
                  aria-label="Editar"
                  title="Editar"
                >
                  <i class="fa fa-edit"></i>
                </button>

                <!-- Ativar/Desativar (só se existir coluna de status) -->
                <?php if ($colStatus !== null): ?>
                  <button
                    type="button"
                    class="btn btn-sm <?= $classBtn ?> ml-2"
                    onclick="ativar(<?= $id ?>, '<?= $acaoBtn ?>')"
                    aria-label="<?= h($labelBtn) ?>"
                    title="<?= h($labelBtn) ?>"
                  >
<i 
  id="icone-ativar-<?= $id ?>" 
  class="fa <?= $isActive ? 'fa-toggle-on' : 'fa-toggle-off' ?>" 
  data-status="<?= $isActive ? 'ativo' : 'inativo' ?>">
</i>


                  </button>
                <?php endif; ?>

                <!-- Excluir -->
                <button
                  type="button"
                  class="btn btn-sm btn-danger ml-2"
                  onclick="excluirFoto('<?= $id ?>')"
                  aria-label="Excluir"
                  title="Excluir"
                >
                  <i class="fa fa-trash"></i>
                </button>
              </div>
            </div>
          </div>
        </div>
      <?php endforeach; ?>
    <?php else: ?>
      <div class="col-12">
        <div class="alert alert-info mb-0">Nenhuma foto cadastrada.</div>
      </div>
    <?php endif; ?>
  </div>
</div>
