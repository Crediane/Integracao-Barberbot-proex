<?php 
require_once __DIR__ . '/_context_bootstrap.php';
require_once __DIR__ . '/../verificar.php';
require_once __DIR__ . '/../../conexao.php';

// bloquear quem não tem permissão
if(@$bio == 'ocultar'){
  echo "<script>window.location='<?php echo $bb_painel_public_url; ?>/'</script>";
  exit();
}

$pag = 'bio';
?>
<div class="mb-3">
  <a class="btn btn-primary" onclick="inserir()">
    <i class="fa fa-plus"></i> Novo Acesso
  </a>
</div>

<div class="bs-example widget-shadow" style="padding:15px" id="listar"></div>

<!-- Modal Inserir/Editar -->
<div class="modal fade" id="modalForm" tabindex="-1" role="dialog" aria-labelledby="ttl" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <div class="modal-header">
      <h4 class="modal-title"><span id="titulo_inserir">Novo Registro</span></h4>
      <button id="btn-fechar" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top:-20px">
        <span aria-hidden="true">&times;</span>
      </button>
    </div>

    <form id="form" method="post" enctype="multipart/form-data">
      <div class="modal-body">

        <div class="row">
          <div class="col-md-7">
            <div class="form-group">
              <label>Título</label>
              <input type="text" class="form-control" id="nome" name="nome" required placeholder="Ex.: Jacy Cabeleireiro">
            </div>
          </div>

          <div class="col-md-5">
            <div class="form-group">
              <label>URL</label>
              <input type="text" class="form-control" id="chave" name="chave" placeholder="https://...">
            </div>
          </div>
        </div>

        <div class="row">
          <div class="col-md-7">
            <div class="form-group">
              <label>Descrição (opcional)</label>
              <textarea class="form-control" id="descricao" name="descricao" rows="3" placeholder="Texto curto para ajudar na conversão"></textarea>
            </div>
          </div>

          <div class="col-md-5">
            <div class="form-group">
              <label>Ícone (classe Font Awesome)</label>
              <input type="text" class="form-control" id="icone" name="icone" placeholder="ex.: fa-solid fa-scissors">
              <small class="text-muted">Exibe ao lado do título quando não houver imagem.</small>
            </div>

            <div class="form-row">
              <div class="form-group col-md-6">
                <label>Grupo</label>
                <select class="form-control sel2" id="grupo" name="grupo" style="width:100%;">
                  <option value="0">Nenhum Grupo</option>
                  <?php
                    $q = $pdo->query("SELECT id,nome FROM grupo_acessos ORDER BY nome");
                    $g = $q->fetchAll(PDO::FETCH_ASSOC);
                    foreach($g as $row){
                      echo '<option value="'.$row['id'].'">'.htmlspecialchars($row['nome'],ENT_QUOTES,'UTF-8').'</option>';
                    }
                  ?>
                </select>
              </div>

              <div class="form-group col-md-3">
                <label>Ordem</label>
                <input type="number" class="form-control" id="ordem" name="ordem" value="0">
              </div>

              <div class="form-group col-md-3">
                <label>Status</label>
                <select class="form-control" id="ativo" name="ativo">
                  <option value="1">Ativo</option>
                  <option value="0">Inativo</option>
                </select>
              </div>
            </div>
          </div>
        </div>

        <hr class="my-2">

        <div class="row">
          <div class="col-md-4">
            <label>Imagem (quadrada de preferência)</label>
            <input type="file" class="form-control" name="imagem" id="imagem" accept="image/*" onchange="previewImg(event)">
            <small class="text-muted">JPG/PNG/WebP até 4MB.</small>
          </div>
          <div class="col-md-4">
            <label>&nbsp;</label>
            <div>
              <img id="target" src="img/sem-foto.jpg" alt="preview" style="max-width:150px;border-radius:12px;border:1px solid #e3e6ea">
            </div>
          </div>
        </div>

        <input type="hidden" name="id" id="id">
        <input type="hidden" name="imagem_antiga" id="imagem_antiga">

        <br>
        <small><div id="mensagem" align="center"></div></small>
      </div>

      <div class="modal-footer">
        <button type="submit" class="btn btn-primary">Salvar</button>
      </div>
    </form>
  </div></div>
</div>

<script type="text/javascript">var pag = "<?=$pag?>"</script>
<script src="js/ajax.js"></script>

<script>
  $(function(){
    $('.sel2').select2({ dropdownParent: $('#modalForm') });
  });

  function inserir(){
    $('#titulo_inserir').text('Novo Registro');
    $('#id').val('');
    $('#nome').val('');
    $('#chave').val('');
    $('#descricao').val('');
    $('#icone').val('');
    $('#grupo').val('0').trigger('change');
    $('#ordem').val('0');
    $('#ativo').val('1');
    $('#imagem').val('');
    $('#imagem_antiga').val('');
    $('#target').attr('src','img/sem-foto.jpg');
    $('#mensagem').text('');
    $('#modalForm').modal('show');
  }

  function previewImg(e){
    const f = e.target.files[0]; if(!f) return;
    const r = new FileReader();
    r.onload = () => { $('#target').attr('src', r.result); };
    r.readAsDataURL(f);
  }
</script>
