<?php
require_once __DIR__ . '/_context_bootstrap.php';
require_once __DIR__ . '/../verificar.php';
require_once __DIR__ . '/../../conexao.php'; // mantém a lógica do painel (não remove)

// reutiliza a conexão do master marketplace (onde fica a tabela tenants)
require_once __DIR__ . '/../painel_master/conexao_market.php';

$slug = $_SESSION['tenant_slug'] ?? '';

// fallback: se não estiver em /loja/slug, tenta achar pelo db atual
if ($slug === '') {
  try {
    $st = $pdo_market->prepare("SELECT slug FROM tenants WHERE db_name = :db ORDER BY id ASC LIMIT 1");
    $st->execute([':db' => $banco]);
    $r = $st->fetch();
    if ($r && !empty($r['slug'])) $slug = $r['slug'];
  } catch (Exception $e) {}
}

if ($slug === '') {
  echo "<div class='alert alert-danger'>Não foi possível identificar a barbearia (tenant_slug).</div>";
  exit();
}

$st = $pdo_market->prepare("SELECT * FROM tenants WHERE slug = :s LIMIT 1");
$st->execute([':s' => $slug]);
$tenant = $st->fetch();

if (!$tenant) {
  echo "<div class='alert alert-danger'>Barbearia não encontrada na tabela tenants.</div>";
  exit();
}
?>

<div class="bs-example widget-shadow" style="padding:15px">
  <h3 style="margin-top:0">Minha Barbearia (Marketplace)</h3>
  <small>Essas informações aparecem no <b>/marketplace</b> e na página pública <b>/loja/<?=htmlspecialchars($slug)?></b>.</small>
  <hr style="border-color: rgba(255,255,255,.12);">

  <form id="form-tenant" method="post" enctype="multipart/form-data">
    <input type="hidden" name="slug" value="<?=htmlspecialchars($slug)?>">

    <div class="row">
      <div class="col-md-6">
        <label>Nome</label>
        <input type="text" name="nome" class="form-control" value="<?=htmlspecialchars($tenant['nome'] ?? '')?>" required>
      </div>

      <div class="col-md-3">
        <label>Cidade</label>
        <input type="text" name="cidade" class="form-control" value="<?=htmlspecialchars($tenant['cidade'] ?? '')?>">
      </div>

      <div class="col-md-3">
        <label>Estado (UF)</label>
        <input type="text" name="estado" class="form-control" maxlength="2" value="<?=htmlspecialchars($tenant['estado'] ?? '')?>">
      </div>
    </div>

    <div class="row" style="margin-top:10px;">
      <div class="col-md-8">
        <label>Endereço</label>
        <input type="text" name="endereco" class="form-control" value="<?=htmlspecialchars($tenant['endereco'] ?? '')?>">
      </div>

      <div class="col-md-4">
        <label>WhatsApp</label>
        <input type="text" name="whatsapp" class="form-control" value="<?=htmlspecialchars($tenant['whatsapp'] ?? '')?>">
      </div>
    </div>

    <div class="row" style="margin-top:10px;">
      <div class="col-md-8">
        <label>Logo (arquivo)</label>
        <input type="file" name="logo" class="form-control">
        <small class="text-muted">Formatos: png, jpg, jpeg, webp</small>
      </div>

      <div class="col-md-4">
        <label>Logo atual</label><br>
        <?php if (!empty($tenant['logo'])): ?>
          <img src="/loja/<?=rawurlencode($slug)?>/sistema/img/<?=rawurlencode($tenant['logo'])?>"
               style="max-height:60px; border-radius:6px; background:#fff; padding:4px;">
        <?php else: ?>
          <span class="text-muted">Nenhum</span>
        <?php endif; ?>
      </div>
    </div>

    <div style="margin-top:15px;">
      <button type="submit" class="btn btn-primary">Salvar</button>
      <span id="msg" style="margin-left:10px;"></span>
    </div>
  </form>
</div>

<script>
$('#form-tenant').on('submit', function(e){
  e.preventDefault();
  $('#msg').removeClass().text('Salvando...');

  var fd = new FormData(this);

  $.ajax({
    url: 'paginas/minha_barbearia/salvar.php',
    type: 'POST',
    data: fd,
    cache: false,
    contentType: false,
    processData: false,
    success: function(resp){
      resp = (resp || '').trim();
      if(resp === 'Salvo com Sucesso'){
        $('#msg').addClass('text-success').text('Salvo com sucesso!');
        setTimeout(function(){ location.reload(); }, 600);
      } else {
        $('#msg').addClass('text-danger').text(resp);
      }
    }
  });
});
</script>
