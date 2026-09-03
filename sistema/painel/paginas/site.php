<?php
require_once __DIR__ . '/_context_bootstrap.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once $_SERVER['DOCUMENT_ROOT'].'/sistema/painel/verificar.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/sistema/conexao.php';
$pag = 'site';
?>

<style>
/* ===== ULTRA AGRESSIVO - Remove TODO espaço ===== */

/* Zera tudo globalmente */
* { box-sizing: border-box; }

/* Containers do template */
body, html, .page-inner, .outter-wp, .graph-visual, .inner-block,
.page-content, .main-content, .content-wrapper, .main-page,
.page-wrapper, .content, .page-body { 
  margin:0 !important; 
  padding:0 !important; 
  min-height:0 !important;
}

/* CRÍTICO: Tab-content sem espaço */
.tab-content { 
  margin-top:0 !important; 
  margin-bottom:0 !important;
  padding-top:0 !important;
  padding-bottom:0 !important;
}

/* TODAS as abas */
.tab-pane {
  display:none !important;
  margin:0 !important;
  padding:0 !important;
  min-height:0 !important;
  height:auto !important;
}
.tab-pane.active { display:block !important; margin:0 !important; padding:0 !important; }
.tab-pane.fade { opacity:0 !important; }
.tab-pane.show { opacity:1 !important; }

/* Rows dentro das abas - SEM ESPAÇO */
.tab-pane > .row { margin:0 !important; padding:0 !important; }
.tab-pane > .row:first-child { margin-top:0 !important; margin-bottom:8px !important; padding:0 !important; }

/* Divs de listagem */
#listar-banners, #listar-blocos, #listar-carrossel, #listar-categorias { 
  padding:0 !important; 
  margin:0 !important; 
}

/* Widget shadow */
.bs-example, .widget-shadow { margin:0 !important; padding:10px !important; }

/* Linha do topo com botões e abas */
.row.top-50 {
  display:flex; align-items:center; gap:8px; flex-wrap:nowrap;
  margin:0 0 5px 0 !important; padding:0 !important;
}
.row.top-50 > [class*="col-"] { width:auto; flex:0 0 auto; padding:0; margin:0; }
.row.top-50 .col-md-4.float-esq { display:flex; align-items:center; gap:8px; flex-wrap:wrap; }
#btnNovoBanner, #btnNovoBloco, #btnNovoSlide { margin:0; }

/* Abas (nav-tabs) */
.nav.nav-tabs { margin:0 !important; margin-bottom:0 !important; border-bottom:1px solid #ddd; }
.nav-tabs > li { margin:0 !important; margin-bottom:-1px !important; }

/* Responsivo */
@media (max-width: 991.98px) {
  .row.top-50 { flex-wrap:wrap; gap:6px; margin-bottom:8px !important; }
  .row.top-50 > [class*="col-"] { width:100%; }
}
</style>

<!-- TOPO: Ações + Abas -->
<div class="row top-50">
  <div class="col-md-4 float-esq">
    <a id="btnNovoBanner" class="btn btn-primary"><i class="fa fa-plus"></i> <span class="esc">Novo Banner</span></a>
    <a id="btnNovoBloco" class="btn btn-warning"><i class="fa fa-plus"></i> <span class="esc">Novo Bloco</span></a>
    <a id="btnNovoSlide" class="btn btn-success"><i class="fa fa-plus"></i> <span class="esc">Novo Slide</span></a>
    <a id="btnNovaCategoria" class="btn btn-info"><i class="fa fa-plus"></i> <span class="esc">Nova Categoria</span></a>
  </div>

  <div class="col-md-8 float-esq">
    <ul class="nav nav-tabs">
      <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#aba-banners">Banners</a></li>
      <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#aba-blocos">Blocos da Home</a></li>
      <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#aba-carrossel">Carrossel (Hero)</a></li>
      <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#aba-categorias">Categorias</a></li>
    </ul>
  </div>
</div>

<div class="tab-content" style="margin:0!important;padding:0!important;">

  <!-- BANNERS -->
  <div class="tab-pane fade show active" id="aba-banners" style="margin:0!important;padding:0!important;">
    <div class="bs-example widget-shadow" id="listar-banners" style="margin:0!important;padding:10px!important;"></div>
  </div>

  <!-- BLOCOS -->
  <div class="tab-pane fade" id="aba-blocos" style="margin:0!important;padding:0!important;">
    <div class="bs-example widget-shadow" id="listar-blocos" style="margin:0!important;padding:10px!important;"></div>
  </div>

  <!-- CARROSSEL -->
  <div class="tab-pane fade" id="aba-carrossel" style="margin:0!important;padding:0!important;">
    <div class="bs-example widget-shadow" id="listar-carrossel" style="margin:0!important;padding:10px!important;"></div>
  </div>

  <!-- CATEGORIAS -->
  <div class="tab-pane fade" id="aba-categorias" style="margin:0!important;padding:0!important;">
    <div class="bs-example widget-shadow" id="listar-categorias" style="margin:0!important;padding:10px!important;"></div>
  </div>

</div>

<!-- MODAIS (inalterados) -->
<!-- MODAL FORM BANNER -->
<div class="modal fade" id="modalBanner" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document"><div class="modal-content">
    <div class="modal-header">
      <h4 class="modal-title"><span id="titulo_form_banner"></span></h4>
      <button id="btn-fechar-banner" type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <form id="formBanner" enctype="multipart/form-data">
      <div class="modal-body">
        <div class="row">
          <div class="col-md-8">
            <label>Nome (título)</label>
            <input class="form-control" id="bn_nome" name="nome" required>
          </div>
          <div class="col-md-4">
            <label>Ordem</label>
            <input class="form-control" id="bn_ordem" name="ordem" type="number" value="0">
          </div>
        </div>

        <div class="row">
          <div class="col-md-12">
            <label>Subtítulo</label>
            <input class="form-control" id="bn_subtitulo" name="subtitulo" placeholder="Opcional">
          </div>
        </div>

        <div class="row">
          <div class="col-md-12">
            <label>Descrição</label>
            <textarea class="form-control" id="bn_desc" name="descricao" rows="2"></textarea>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <label>Mídia (upload)</label>
            <input type="file" class="form-control" name="arquivo">
            <small>jpg/png/webp/mp4/webm/ogg</small>
          </div>
          <div class="col-md-6">
            <label>Ou URL pública (opcional)</label>
            <input class="form-control" name="arquivo_url" id="bn_url" placeholder="https://...">
          </div>
        </div>

        <div class="row">
          <div class="col-md-4">
            <label>Ativo</label>
            <select class="form-control" id="bn_ativo" name="ativo">
              <option value="1">Sim</option><option value="0">Não</option>
            </select>
          </div>
          <div class="col-md-8">
            <label>Registro/Tag</label>
            <input class="form-control" id="bn_registro" name="registro" value="banner_home">
          </div>
        </div>

        <input type="hidden" name="tipo" value="banner">
        <input type="hidden" id="bn_id" name="id">
        <br><small><div id="msg-banner" align="center"></div></small>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-primary">Salvar</button></div>
    </form>
  </div></div>
</div>

<!-- MODAL FORM BLOCO -->
<div class="modal fade" id="modalBloco" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <div class="modal-header">
      <h4 class="modal-title"><span id="titulo_form_bloco"></span></h4>
      <button id="btn-fechar-bloco" type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <form id="formBloco" enctype="multipart/form-data">
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6">
            <label>Slug</label>
            <input class="form-control" id="bl_slug" name="slug" required placeholder="ex.: corte_masculino">
          </div>
          <div class="col-md-6">
            <label>Status</label>
            <select class="form-control" id="bl_status" name="status">
              <option value="1">Publicado</option><option value="0">Rascunho</option>
            </select>
          </div>
        </div>

        <div class="row">
          <div class="col-md-12">
            <label>Título</label>
            <input class="form-control" id="bl_titulo" name="titulo" required>
          </div>
        </div>

        <div class="row">
          <div class="col-md-12">
            <label>Subtítulo</label>
            <input class="form-control" id="bl_subtitulo" name="subtitulo" placeholder="Opcional">
          </div>
        </div>

        <div class="row">
          <div class="col-md-12">
            <label>Texto</label>
            <textarea class="form-control" id="bl_texto" name="texto" rows="4"></textarea>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <label>Imagem (upload)</label>
            <input type="file" class="form-control" name="img">
          </div>
          <div class="col-md-6">
            <label>Vídeo (upload)</label>
            <input type="file" class="form-control" name="video">
          </div>
        </div>

        <div class="row">
          <div class="col-md-12">
            <label>Vídeo (URL pública – opcional)</label>
            <input class="form-control" id="bl_videourl" name="video_url" placeholder="https://...">
          </div>
        </div>

        <input type="hidden" name="tipo" value="bloco">
        <input type="hidden" id="bl_id" name="id">
        <br><small><div id="msg-bloco" align="center"></div></small>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-primary">Salvar</button></div>
    </form>
  </div></div>
</div>

<!-- MODAL FORM CARROSSEL -->
<div class="modal fade" id="modalCarrossel" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg" role="document"><div class="modal-content">
    <div class="modal-header">
      <h4 class="modal-title"><span id="titulo_form_carrossel"></span></h4>
      <button id="btn-fechar-carrossel" type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <form id="formCarrossel" enctype="multipart/form-data">
      <div class="modal-body">
        <div class="row">
          <div class="col-md-8">
            <label>Título</label>
            <input class="form-control" id="cr_titulo" name="titulo" required>
          </div>
          <div class="col-md-4">
            <label>Ordem</label>
            <input class="form-control" id="cr_ordem" name="ordem" type="number" value="0">
          </div>
        </div>

        <div class="row">
          <div class="col-md-12">
            <label>Subtítulo</label>
            <input class="form-control" id="cr_subtitulo" name="subtitulo" placeholder="Opcional">
          </div>
        </div>

        <div class="row">
          <div class="col-md-7">
            <label>Vídeo (URL do YouTube ou ID)</label>
            <input class="form-control" id="cr_video" name="video_url" placeholder="https://youtube.com/watch?v=ID ou somente ID">
          </div>
          <div class="col-md-5">
            <label>Miniatura (upload)</label>
            <input type="file" class="form-control" name="thumb">
            <small>jpg/png/webp</small>
          </div>
        </div>

        <div class="row">
          <div class="col-md-4">
            <label>Ativo</label>
            <select class="form-control" id="cr_ativo" name="ativo">
              <option value="1">Sim</option><option value="0">Não</option>
            </select>
          </div>
        </div>

        <input type="hidden" name="tipo" value="carrossel">
        <input type="hidden" id="cr_id" name="id">
        <br><small><div id="msg-carrossel" align="center"></div></small>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-primary">Salvar</button></div>
    </form>
  </div></div>
</div>

<!-- MODAL FORM CATEGORIA -->
<div class="modal fade" id="modalCategoria" tabindex="-1" role="dialog">
  <div class="modal-dialog" role="document"><div class="modal-content">
    <div class="modal-header">
      <h4 class="modal-title"><span id="titulo_form_categoria"></span></h4>
      <button id="btn-fechar-categoria" type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
    </div>
    <form id="formCategoria" enctype="multipart/form-data">
      <div class="modal-body">
        <div class="row">
          <div class="col-md-6">
            <label>Nome</label>
            <input class="form-control" id="cat_nome" name="nome" required>
          </div>
          <div class="col-md-6">
            <label>Slug</label>
            <input class="form-control" id="cat_slug" name="slug" required placeholder="ex.: servicos-beleza">
          </div>
        </div>

        <div class="row">
          <div class="col-md-12">
            <label>Descrição</label>
            <textarea class="form-control" id="cat_desc" name="descricao" rows="3"></textarea>
          </div>
        </div>

        <div class="row">
          <div class="col-md-6">
            <label>Ícone/Imagem (upload)</label>
            <input type="file" class="form-control" name="icone">
            <small>jpg/png/webp/svg</small>
          </div>
          <div class="col-md-3">
            <label>Ordem</label>
            <input class="form-control" id="cat_ordem" name="ordem" type="number" value="0">
          </div>
          <div class="col-md-3">
            <label>Ativo</label>
            <select class="form-control" id="cat_ativo" name="ativo">
              <option value="1">Sim</option><option value="0">Não</option>
            </select>
          </div>
        </div>

        <input type="hidden" name="tipo" value="categoria">
        <input type="hidden" id="cat_id" name="id">
        <br><small><div id="msg-categoria" align="center"></div></small>
      </div>
      <div class="modal-footer"><button type="submit" class="btn btn-primary">Salvar</button></div>
    </form>
  </div></div>
</div>

<script type="text/javascript">var pag = "<?=$pag?>";</script>

<script>
$(function(){

  function normalizaBlocos(){
    $('#aba-blocos, #listar-blocos, #aba-blocos .widget-shadow')
      .css({height:'auto', minHeight:0, paddingTop:0, marginTop:0, opacity:1});
  }

  // carrega listas iniciais
  listarBanners();
  listarBlocos();
  listarCarrossel();
  listarCategorias();

  // reforço visual ao trocar de abas
  $('a[href="#aba-blocos"]').on('shown.bs.tab click', function(){
    $('#aba-banners').removeClass('active show');
    $('#aba-blocos').addClass('active show');
    normalizaBlocos();
    setTimeout(normalizaBlocos, 60);
    setTimeout(normalizaBlocos, 200);
  });
  $('a[href="#aba-carrossel"]').on('shown.bs.tab click', function(){
    $('#aba-carrossel').addClass('active show');
  });

  // novo banner
  $('#btnNovoBanner').on('click', function(){
    $('#titulo_form_banner').text('Novo Banner');
    $('#formBanner')[0].reset();
    $('#bn_id').val('');
    $('#bn_registro').val('banner_home');
    $('#modalBanner').modal('show');
  });

  // novo bloco
  $('#btnNovoBloco').on('click', function(){
    $('#titulo_form_bloco').text('Novo Bloco');
    $('#formBloco')[0].reset();
    $('#bl_id').val('');
    $('#modalBloco').modal('show');
  });

  // novo slide
  $('#btnNovoSlide').on('click', function(){
    $('#titulo_form_carrossel').text('Novo Slide');
    $('#formCarrossel')[0].reset();
    $('#cr_id').val('');
    $('#cr_ordem').val(0);
    $('#cr_ativo').val(1);
    $('#modalCarrossel').modal('show');
  });

  // nova categoria
  $('#btnNovaCategoria').on('click', function(){
    $('#titulo_form_categoria').text('Nova Categoria');
    $('#formCategoria')[0].reset();
    $('#cat_id').val('');
    $('#cat_ordem').val(0);
    $('#cat_ativo').val(1);
    $('#modalCategoria').modal('show');
  });

  // salvar banner
  $("#formBanner").submit(function(e){
    e.preventDefault();
    var formData = new FormData(this);
    $.ajax({
      url: 'paginas/'+pag+'/salvar.php',
      type:'POST', data: formData,
      success: function(msg){
        $('#msg-banner').removeClass().text('');
        if($.trim(msg)=="Salvo com Sucesso"){
          $('#btn-fechar-banner').click();
          listarBanners();
        }else{
          $('#msg-banner').addClass('text-danger').text(msg);
        }
      },
      cache:false, contentType:false, processData:false
    });
  });

  // salvar bloco
  $("#formBloco").submit(function(e){
    e.preventDefault();
    var formData = new FormData(this);
    $.ajax({
      url: 'paginas/'+pag+'/salvar.php',
      type:'POST', data: formData,
      success: function(msg){
        $('#msg-bloco').removeClass().text('');
        if($.trim(msg)=="Salvo com Sucesso"){
          $('#btn-fechar-bloco').click();
          listarBlocos();
        }else{
          $('#msg-bloco').addClass('text-danger').text(msg);
        }
      },
      cache:false, contentType:false, processData:false
    });
  });

  // salvar carrossel
  $("#formCarrossel").submit(function(e){
    e.preventDefault();
    var formData = new FormData(this);
    $.ajax({
      url: 'paginas/'+pag+'/salvar.php',
      type:'POST', data: formData,
      success: function(msg){
        $('#msg-carrossel').removeClass().text('');
        if($.trim(msg)=="Salvo com Sucesso"){
          $('#btn-fechar-carrossel').click();
          listarCarrossel();
        }else{
          $('#msg-carrossel').addClass('text-danger').text(msg);
        }
      },
      cache:false, contentType:false, processData:false
    });
  });

  // salvar categoria
  $("#formCategoria").submit(function(e){
    e.preventDefault();
    var formData = new FormData(this);
    $.ajax({
      url: 'paginas/'+pag+'/salvar.php',
      type:'POST', data: formData,
      success: function(msg){
        $('#msg-categoria').removeClass().text('');
        if($.trim(msg)=="Salvo com Sucesso"){
          $('#btn-fechar-categoria').click();
          listarCategorias();
        }else{
          $('#msg-categoria').addClass('text-danger').text(msg);
        }
      },
      cache:false, contentType:false, processData:false
    });
  });

  normalizaBlocos();
});

// LISTAGENS (sem paginação/busca)
function listarBanners(){
  $.post('paginas/'+pag+'/listar.php', {aba:'banners'}, function(html){
    $("#listar-banners").html(html);
  }).fail(function(){ alert('Erro ao carregar Banners'); });
}
function listarBlocos(){
  $.post('paginas/'+pag+'/listar.php', {aba:'blocos'}, function(html){
    $("#listar-blocos").html(html);
    $('#aba-blocos').addClass('active show');
  }).fail(function(){ alert('Erro ao carregar Blocos'); });
}
function listarCarrossel(){
  $.post('paginas/'+pag+'/listar.php', {aba:'carrossel'}, function(html){
    $("#listar-carrossel").html(html);
    $('#aba-carrossel').addClass('active show');
  }).fail(function(){ alert('Erro ao carregar Carrossel'); });
}
function listarCategorias(){
  $.post('paginas/'+pag+'/listar.php', {aba:'categorias'}, function(html){
    $("#listar-categorias").html(html);
    $('#aba-categorias').addClass('active show');
  }).fail(function(){ alert('Erro ao carregar Categorias'); });
}

// AÇÕES
function editarBanner(id,nome,subtitulo,descricao,arquivo,ordem,ativo,registro){
  $('#titulo_form_banner').text('Editar Banner');
  $('#bn_id').val(id);
  $('#bn_nome').val(nome);
  $('#bn_subtitulo').val(subtitulo || '');
  $('#bn_desc').val(descricao || '');
  $('#bn_ordem').val(ordem);
  $('#bn_ativo').val(ativo);
  $('#bn_registro').val(registro || 'banner_home');
  $('#modalBanner').modal('show');
}
function editarBloco(id,slug,titulo,subtitulo,texto,img,video,status){
  $('#titulo_form_bloco').text('Editar Bloco');
  $('#bl_id').val(id);
  $('#bl_slug').val(slug);
  $('#bl_titulo').val(titulo);
  $('#bl_subtitulo').val(subtitulo || '');
  $('#bl_texto').val(texto || '');
  $('#bl_status').val(status);
  $('#modalBloco').modal('show');
}
function editarCarrossel(id,titulo,subtitulo,video_url,thumb,ordem,ativo){
  $('#titulo_form_carrossel').text('Editar Slide');
  $('#cr_id').val(id);
  $('#cr_titulo').val(titulo);
  $('#cr_subtitulo').val(subtitulo || '');
  $('#cr_video').val(video_url || '');
  $('#cr_ordem').val(ordem || 0);
  $('#cr_ativo').val(ativo || 1);
  $('#modalCarrossel').modal('show');
}
function editarCategoria(id,nome,slug,descricao,icone,ordem,ativo){
  $('#titulo_form_categoria').text('Editar Categoria');
  $('#cat_id').val(id);
  $('#cat_nome').val(nome);
  $('#cat_slug').val(slug);
  $('#cat_desc').val(descricao || '');
  $('#cat_ordem').val(ordem || 0);
  $('#cat_ativo').val(ativo || 1);
  $('#modalCategoria').modal('show');
}

function mudarAtivo(tipo,id,valor){
  $.post('paginas/'+pag+'/mudar.php',{tipo:tipo,id:id,valor:valor},function(msg){
    if($.trim(msg)!="Alterado com Sucesso"){ alert(msg); }
    if(tipo=='banner'){ listarBanners(); }
    else if(tipo=='bloco'){ listarBlocos(); }
    else if(tipo=='carrossel'){ listarCarrossel(); }
    else if(tipo=='categoria'){ listarCategorias(); }
  });
}
function excluirItem(tipo,id){
  if(!confirm('Confirmar exclusão?')) return;
  $.post('paginas/'+pag+'/excluir.php',{tipo:tipo,id:id},function(msg){
    if($.trim(msg)=="Excluído com Sucesso"){
      if(tipo=='banner'){ listarBanners(); }
      else if(tipo=='bloco'){ listarBlocos(); }
      else if(tipo=='carrossel'){ listarCarrossel(); }
      else if(tipo=='categoria'){ listarCategorias(); }
    }else{ alert(msg); }
  });
}
</script>
