<?php
require_once __DIR__ . '/marketplace/master_bootstrap.php';

$pag = 'marketplace';
$data_atual = date('Y-m-d');
?>

<div class="row">
  <div class="col-md-12">

    <style>
      .marketplace-resumo-wrap{
        margin-bottom:18px;
      }
      .marketplace-resumo-grid{
        display:flex;
        flex-wrap:wrap;
        gap:14px;
        margin-bottom:14px;
      }
      .marketplace-card{
        flex:1 1 220px;
        min-width:220px;
        background:#fff;
        border:1px solid #e8edf3;
        border-radius:14px;
        padding:16px 18px;
        box-shadow:0 8px 22px rgba(15, 23, 42, 0.06);
        position:relative;
        overflow:hidden;
      }
      .marketplace-card:before{
        content:'';
        position:absolute;
        left:0;
        top:0;
        width:4px;
        height:100%;
        background:#0d6efd;
      }
      .marketplace-card-topo{
        display:flex;
        align-items:center;
        justify-content:space-between;
        gap:12px;
        margin-bottom:8px;
      }
      .marketplace-card-icone{
        width:46px;
        height:46px;
        border-radius:12px;
        display:flex;
        align-items:center;
        justify-content:center;
        font-size:20px;
        background:rgba(13,110,253,.10);
        color:#0d6efd;
        flex:0 0 46px;
      }
      .marketplace-card.marketplace-card-saldo:before{
        background:#0ea5e9;
      }
      .marketplace-card.marketplace-card-saldo .marketplace-card-icone{
        background:rgba(14,165,233,.12);
        color:#0284c7;
      }
      .marketplace-card.marketplace-card-vencido:before{
        background:#ef4444;
      }
      .marketplace-card.marketplace-card-vencido .marketplace-card-icone{
        background:rgba(239,68,68,.12);
        color:#dc2626;
      }
      .marketplace-card.marketplace-card-pago:before{
        background:#10b981;
      }
      .marketplace-card.marketplace-card-pago .marketplace-card-icone{
        background:rgba(16,185,129,.12);
        color:#059669;
      }
      .marketplace-card-titulo{
        margin:0;
        font-size:13px;
        font-weight:700;
        color:#64748b;
        text-transform:uppercase;
        letter-spacing:.04em;
      }
      .marketplace-card-valor{
        margin:0;
        font-size:28px;
        line-height:1.1;
        font-weight:700;
        color:#0f172a;
      }
      .marketplace-card-rodape{
        margin-top:6px;
        font-size:12px;
        color:#7b8794;
      }
      .marketplace-painel-topo{
        background:#fff;
        border:1px solid #e8edf3;
        border-radius:14px;
        padding:18px;
        box-shadow:0 8px 22px rgba(15, 23, 42, 0.05);
        margin-bottom:18px;
      }
      .marketplace-filtros-linha{
        display:flex;
        flex-wrap:wrap;
        align-items:center;
        gap:14px;
      }
      .marketplace-filtros-botao{
        flex:0 0 auto;
      }
      .marketplace-filtros-busca{
        flex:1 1 420px;
        min-width:280px;
      }
      .marketplace-filtros-msg{
        flex:0 1 320px;
        min-width:220px;
        text-align:right;
      }
      .marketplace-filtros-msg small{
        display:block;
      }
      .marketplace-filtros-msg #mensagem-marketplace{
        min-height:18px;
      }
      .marketplace-page-head{
        display:flex;
        align-items:flex-start;
        justify-content:space-between;
        gap:16px;
        flex-wrap:wrap;
        margin-bottom:18px;
      }
      .marketplace-page-title{
        margin:0 0 5px;
        color:#0f172a;
        font-size:24px;
        font-weight:700;
      }
      .marketplace-page-subtitle{
        margin:0;
        color:#64748b;
        font-size:14px;
        line-height:1.5;
      }
      .marketplace-page-actions{
        display:flex;
        flex-wrap:wrap;
        gap:8px;
      }
      .marketplace-card.marketplace-card-mapeado:before{
        background:#8b5cf6;
      }
      .marketplace-card.marketplace-card-mapeado .marketplace-card-icone{
        background:rgba(139,92,246,.12);
        color:#7c3aed;
      }
      .marketplace-filtros-select{
        flex:0 1 190px;
        min-width:160px;
      }
      .marketplace-contexto-master{
        display:inline-flex;
        align-items:center;
        gap:7px;
        margin-top:8px;
        padding:6px 10px;
        border-radius:999px;
        background:#e0f2fe;
        color:#0369a1;
        font-size:12px;
        font-weight:700;
      }
      .marketplace-contexto-master i{font-size:11px;}
      @media (max-width: 991.98px){
        .marketplace-filtros-msg{
          flex:1 1 100%;
          text-align:left;
        }
      }
      @media (max-width: 767.98px){
        .marketplace-card{
          min-width:100%;
        }
        .marketplace-painel-topo{
          padding:14px;
        }
        .marketplace-filtros-linha{
          gap:10px;
        }
        .marketplace-filtros-botao,
        .marketplace-filtros-busca,
        .marketplace-filtros-select,
        .marketplace-filtros-msg{
          flex:1 1 100%;
          min-width:100%;
          text-align:left;
        }
        .marketplace-filtros-botao .btn{
          width:100%;
        }
      }
    </style>

    <div class="marketplace-page-head">
      <div>
        <h2 class="marketplace-page-title">Gestão do Marketplace</h2>
        <p class="marketplace-page-subtitle">Gerencie barbearias, planos, vencimentos, cobranças e o acesso de cada banco individual.</p>
        <span class="marketplace-contexto-master"><i class="fa fa-shield"></i> Contexto Master · Banco Global/Administrativo</span>
      </div>
      <div class="marketplace-page-actions">
        <a href="?pagina=marketplace_diagnostico" class="btn btn-outline-primary">
          <i class="fa fa-stethoscope"></i> Diagnóstico de isolamento
        </a>
      </div>
    </div>

    <div id="resumo-marketplace" class="marketplace-resumo-wrap">
      <div class="marketplace-resumo-grid">
        <div class="marketplace-card">
          <div class="marketplace-card-topo">
            <div>
              <h6 class="marketplace-card-titulo">Assinantes</h6>
              <p class="marketplace-card-valor">--</p>
            </div>
            <div class="marketplace-card-icone">
              <i class="fa fa-users"></i>
            </div>
          </div>
          <div class="marketplace-card-rodape">Carregando resumo...</div>
        </div>

        <div class="marketplace-card marketplace-card-saldo">
          <div class="marketplace-card-topo">
            <div>
              <h6 class="marketplace-card-titulo">Saldo</h6>
              <p class="marketplace-card-valor">--</p>
            </div>
            <div class="marketplace-card-icone">
              <i class="fa fa-line-chart"></i>
            </div>
          </div>
          <div class="marketplace-card-rodape">Carregando resumo...</div>
        </div>

        <div class="marketplace-card marketplace-card-vencido">
          <div class="marketplace-card-topo">
            <div>
              <h6 class="marketplace-card-titulo">Vencido</h6>
              <p class="marketplace-card-valor">--</p>
            </div>
            <div class="marketplace-card-icone">
              <i class="fa fa-exclamation-triangle"></i>
            </div>
          </div>
          <div class="marketplace-card-rodape">Carregando resumo...</div>
        </div>

        <div class="marketplace-card marketplace-card-pago">
          <div class="marketplace-card-topo">
            <div>
              <h6 class="marketplace-card-titulo">Pago</h6>
              <p class="marketplace-card-valor">--</p>
            </div>
            <div class="marketplace-card-icone">
              <i class="fa fa-check-circle"></i>
            </div>
          </div>
          <div class="marketplace-card-rodape">Carregando resumo...</div>
        </div>

        <div class="marketplace-card marketplace-card-mapeado">
          <div class="marketplace-card-topo">
            <div>
              <h6 class="marketplace-card-titulo">Bancos mapeados</h6>
              <p class="marketplace-card-valor">--</p>
            </div>
            <div class="marketplace-card-icone">
              <i class="fa fa-database"></i>
            </div>
          </div>
          <div class="marketplace-card-rodape">Carregando resumo...</div>
        </div>
      </div>
    </div>

    <div class="marketplace-painel-topo">
      <div class="marketplace-filtros-linha">
        <div class="marketplace-filtros-botao marketplace-page-actions">
          <a href="../../../../criar-barbearia.php" target="_blank" rel="noopener" class="btn btn-primary">
            <i class="fa fa-plus"></i> Nova Barbearia
          </a>
          <button type="button" class="btn btn-outline-secondary" id="btn-atualizar-marketplace">
            <i class="fa fa-refresh"></i> Atualizar
          </button>
        </div>

        <div class="marketplace-filtros-busca">
          <input type="text" class="form-control" id="buscar" placeholder="Buscar por nome, slug, domínio ou banco..." autocomplete="off">
        </div>

        <div class="marketplace-filtros-select">
          <select class="form-control" id="filtro-status">
            <option value="">Todos os status</option>
            <option value="active">Ativos</option>
            <option value="blocked">Bloqueados</option>
          </select>
        </div>

        <div class="marketplace-filtros-select">
          <select class="form-control" id="filtro-plano">
            <option value="">Todos os planos</option>
            <option value="profissional">Profissional</option>
            <option value="business">Business</option>
          </select>
        </div>

        <div class="marketplace-filtros-msg">
          <small><div id="mensagem-marketplace" aria-live="polite"></div></small>
        </div>
      </div>
    </div>

    <div id="listar-marketplace"></div>

  </div>
</div>

<!-- Modal Editar Tenant -->
<div class="modal fade" id="modalTenant" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><small>Editar Barbearia</small></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Close">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <form id="form-tenant">
        <div class="modal-body">

          <div class="row">
            <div class="col-md-6">
              <label>Slug</label>
              <input type="text" class="form-control" id="slug" name="slug" readonly>
            </div>
            <div class="col-md-6">
              <label>Status</label>
              <select class="form-control" id="status" name="status">
                <option value="active">Ativo</option>
                <option value="blocked">Bloqueado</option>
              </select>
            </div>
          </div>

          <div class="row mt-2">
            <div class="col-md-6">
              <label>Vencimento</label>
              <input type="date" class="form-control" id="vencimento" name="vencimento">
            </div>
            <div class="col-md-6">
              <label>Valor Mensal (R$)</label>
              <input type="text" class="form-control" id="valor_mensal" name="valor_mensal" placeholder="Ex: 49.90">
            </div>
          </div>

          <div class="row mt-2">
            <div class="col-md-6">
              <label>WhatsApp (com DDD)</label>
              <input type="text" class="form-control" id="telefone_whatsapp" name="telefone_whatsapp" placeholder="Ex: (45) 99999-9999">
            </div>
            <div class="col-md-6">
              <label>Email</label>
              <input type="email" class="form-control" id="email" name="email" placeholder="email@dominio.com">
            </div>
          </div>

          <div class="row mt-2">
            <div class="col-md-6">
              <label>Plano</label>
              <select class="form-control" id="plano_marketplace" name="plano_marketplace">
                <option value="profissional">Profissional (Subdomínio)</option>
                <option value="business">Business (Domínio personalizado)</option>
              </select>
            </div>
            <div class="col-md-6">
              <label>Domínio Personalizado</label>
              <input type="text" class="form-control" id="dominio_personalizado" name="dominio_personalizado" placeholder="www.seudominio.com.br">
            </div>
          </div>

          <small class="text-muted d-block mt-2">No plano Profissional a barbearia usa o subdomínio canônico. No plano Business você pode cadastrar um domínio personalizado.</small>

          <input type="hidden" name="id" id="id">

          <br>
          <small><div id="mensagem-tenant" align="center"></div></small>
        </div>

        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Salvar</button>
        </div>
      </form>

    </div>
  </div>
</div>

<!-- Modal Baixa Manual -->
<div class="modal fade" id="modalBaixaMarketplace" tabindex="-1" role="dialog" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title"><small>Baixar Pagamento do Marketplace</small></h5>
        <button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <form id="form-baixa-marketplace">
        <div class="modal-body">
          <div class="row">
            <div class="col-md-8">
              <label>Barbearia</label>
              <input type="text" class="form-control" id="baixa_nome" readonly>
            </div>
            <div class="col-md-4">
              <label>Slug</label>
              <input type="text" class="form-control" id="baixa_slug" readonly>
            </div>
          </div>

          <div class="row mt-2">
            <div class="col-md-6">
              <label>Valor da Fatura (R$)</label>
              <input type="text" class="form-control" id="baixa_valor" readonly>
            </div>
            <div class="col-md-6">
              <label>Vencimento</label>
              <input type="text" class="form-control" id="baixa_vencimento" readonly>
            </div>
          </div>

          <div class="row mt-2">
            <div class="col-md-4">
              <label>Forma de Pagamento</label>
              <select class="form-control" id="baixa_forma_pagamento" name="forma_pagamento" required>
                <option value="Pix">Pix</option>
                <option value="Cartão">Cartão</option>
                <option value="Dinheiro">Dinheiro</option>
              </select>
            </div>
            <div class="col-md-4">
              <label>Data do Pagamento</label>
              <input type="date" class="form-control" id="baixa_data_pagamento" name="data_pagamento" value="<?php echo $data_atual; ?>" required>
            </div>
            <div class="col-md-4">
              <label>WhatsApp</label>
              <input type="text" class="form-control" id="baixa_whatsapp" readonly>
            </div>
          </div>

          <div class="row mt-2">
            <div class="col-md-12">
              <label>Observação</label>
              <textarea class="form-control" id="baixa_observacao" name="observacao" rows="3" placeholder="Observação opcional"></textarea>
            </div>
          </div>

          <input type="hidden" id="baixa_id" name="id">
          <input type="hidden" name="acao" value="baixar_pagamento">

          <br>
          <small><div id="mensagem-baixa-marketplace" align="center"></div></small>
        </div>

        <div class="modal-footer">
          <button type="submit" id="btn_baixa_marketplace" class="btn btn-success">Confirmar Baixa</button>
        </div>
      </form>
    </div>
  </div>
</div>

<script type="text/javascript">

var bbMarketplacePaginaAtual = 1;
var bbMarketplacePorPagina = 10;

function bbMarketplaceRetornoBloqueadoOuHtml(resposta){
  resposta = (resposta || '').toString();
  var respostaLower = resposta.toLowerCase();

  return (
    respostaLower.indexOf('<!doctype') !== -1 ||
    respostaLower.indexOf('<html') !== -1 ||
    respostaLower.indexOf('<form') !== -1 ||
    respostaLower.indexOf('acesso ao painel') !== -1 ||
    respostaLower.indexOf('recuperar senha') !== -1 ||
    respostaLower.indexOf('/sistema/autenticar.php') !== -1 ||
    respostaLower.indexOf('g-recaptcha') !== -1
  );
}

function bbMarketplaceMensagemRetorno(resposta, mensagemPadrao){
  resposta = (resposta || '').toString().trim();

  if(resposta === ''){
    return mensagemPadrao || 'Não foi possível concluir a operação.';
  }

  if(bbMarketplaceRetornoBloqueadoOuHtml(resposta)){
    return 'Sessão expirada ou acesso bloqueado. Atualize a página e faça login novamente antes de continuar.';
  }

  if(resposta.length > 350){
    return resposta.substring(0, 350) + '...';
  }

  return resposta;
}

function bbMarketplaceMensagemErroAjax(xhr, mensagemPadrao){
  var resposta = '';

  if(xhr && xhr.responseText){
    resposta = xhr.responseText;
  }

  return bbMarketplaceMensagemRetorno(resposta, mensagemPadrao || 'Erro AJAX ao executar operação.');
}

function bbMarketplaceHtmlSeguro(resposta, htmlErro){
  resposta = (resposta || '').toString();

  if(bbMarketplaceRetornoBloqueadoOuHtml(resposta)){
    return htmlErro || '<div class="alert alert-danger">Sessão expirada ou acesso bloqueado. Atualize a página e faça login novamente.</div>';
  }

  return resposta;
}

function aplicarMascaraWhats(){
  if(typeof $.fn.mask !== 'function'){
    return;
  }

  var behavior = function (val) {
    var v = (val || '').replace(/\D/g, '');
    return (v.length > 10) ? '(00) 00000-0000' : '(00) 0000-00009';
  };

  var options = {
    onKeyPress: function(val, e, field, opts) {
      field.mask(behavior(val), opts);
    }
  };

  $('#telefone_whatsapp').mask(behavior, options);
}

$(document).ready(function(){
  carregarResumoMarketplace();
  listarMarketplace(1);

  var timerBuscaMarketplace = null;
  $('#buscar').on('input', function(){
    clearTimeout(timerBuscaMarketplace);
    timerBuscaMarketplace = setTimeout(function(){
      listarMarketplace(1);
    }, 250);
  });

  $('#filtro-status, #filtro-plano').on('change', function(){
    listarMarketplace(1);
  });

  $('#btn-atualizar-marketplace').on('click', function(){
    carregarResumoMarketplace();
    listarMarketplace(bbMarketplacePaginaAtual);
  });

  aplicarMascaraWhats();
  atualizarCamposDominio();

  $('#plano_marketplace').on('change', function(){
    atualizarCamposDominio();
  });

  $('#modalTenant').on('shown.bs.modal', function(){
    aplicarMascaraWhats();
    atualizarCamposDominio();
  });
});

function atualizarCamposDominio(){
  var plano = ($('#plano_marketplace').val() || '').toLowerCase();
  var campo = $('#dominio_personalizado');

  if(plano === 'business'){
    campo.prop('disabled', false).attr('placeholder', 'www.seudominio.com.br');
    return;
  }

  campo.val('');
  campo.prop('disabled', true).attr('placeholder', 'Disponível apenas no plano Business');
}

function carregarResumoMarketplace(){
  $.ajax({
    url: "paginas/marketplace/resumo.php",
    method: "POST",
    dataType: "html",
    success: function(result){
      $("#resumo-marketplace").html(bbMarketplaceHtmlSeguro(result));
    },
    error: function(){
      $("#resumo-marketplace").html(
        '<div class="marketplace-resumo-grid">' +
          '<div class="marketplace-card marketplace-card-vencido" style="min-width:100%;">' +
            '<div class="marketplace-card-topo">' +
              '<div>' +
                '<h6 class="marketplace-card-titulo">Resumo do marketplace</h6>' +
                '<p class="marketplace-card-valor" style="font-size:20px;">Erro ao carregar</p>' +
              '</div>' +
              '<div class="marketplace-card-icone"><i class="fa fa-exclamation-circle"></i></div>' +
            '</div>' +
            '<div class="marketplace-card-rodape">Não foi possível buscar os indicadores agora.</div>' +
          '</div>' +
        '</div>'
      );
    }
  });
}

function listarMarketplace(pagina){
  var paginaSolicitada = parseInt(pagina, 10);
  if(!paginaSolicitada || paginaSolicitada < 1){
    paginaSolicitada = bbMarketplacePaginaAtual || 1;
  }

  var busca = $('#buscar').val();
  var status = $('#filtro-status').val();
  var plano = $('#filtro-plano').val();

  $.ajax({
    url: "paginas/marketplace/listar.php",
    method: "POST",
    data: {
      busca: busca,
      status: status,
      plano: plano,
      pagina: paginaSolicitada,
      por_pagina: bbMarketplacePorPagina
    },
    dataType: "html",
    beforeSend: function(){
      $("#listar-marketplace").html(
        '<div class="marketplace-painel-topo" style="text-align:center;padding:28px;">' +
          '<i class="fa fa-spinner fa-spin" style="font-size:22px;color:#0d6efd;"></i>' +
          '<div style="margin-top:8px;color:#64748b;">Carregando barbearias...</div>' +
        '</div>'
      );
    },
    success: function(result){
      $("#listar-marketplace").html(bbMarketplaceHtmlSeguro(result));

      var $lista = $("#listar-marketplace").find('[data-marketplace-pagina]').first();
      if($lista.length){
        var paginaRetornada = parseInt($lista.attr('data-marketplace-pagina'), 10);
        var porPaginaRetornado = parseInt($lista.attr('data-marketplace-por-pagina'), 10);

        if(paginaRetornada > 0){
          bbMarketplacePaginaAtual = paginaRetornada;
        }
        if([10, 25, 50].indexOf(porPaginaRetornado) !== -1){
          bbMarketplacePorPagina = porPaginaRetornado;
        }
      }
    },
    error: function(){
      $("#listar-marketplace").html('<div class="alert alert-danger">Erro ao listar marketplace.</div>');
    }
  });
}

function alterarMarketplacePorPagina(valor){
  var novoValor = parseInt(valor, 10);
  if([10, 25, 50].indexOf(novoValor) === -1){
    novoValor = 10;
  }

  bbMarketplacePorPagina = novoValor;
  listarMarketplace(1);
}

function abrirTenant(id, slug, vencimento, valor_mensal, telefone, email, status, plano_marketplace, dominio_personalizado){
  $('#id').val(id);
  $('#slug').val(slug);
  $('#vencimento').val(vencimento);
  $('#valor_mensal').val(valor_mensal);
  $('#telefone_whatsapp').val(telefone);
  $('#email').val(email);
  $('#status').val(status);
  $('#plano_marketplace').val(plano_marketplace || 'profissional');
  $('#dominio_personalizado').val(dominio_personalizado || '');

  atualizarCamposDominio();

  $('#mensagem-tenant').text('');
  $('#modalTenant').modal('show');

  aplicarMascaraWhats();
}

function abrirBaixaMarketplace(id, slug, nome, telefone, valor, vencimento){
  $('#baixa_id').val(id);
  $('#baixa_slug').val(slug || '');
  $('#baixa_nome').val(nome || '');
  $('#baixa_whatsapp').val(telefone || '');
  $('#baixa_valor').val(valor || '');
  $('#baixa_vencimento').val(vencimento || '');
  $('#baixa_forma_pagamento').val('Pix');
  $('#baixa_data_pagamento').val('<?php echo $data_atual; ?>');
  $('#baixa_observacao').val('');
  $('#mensagem-baixa-marketplace').removeClass().text('');
  $('#modalBaixaMarketplace').modal('show');
}

function toggleStatus(id, novoStatus){
  $.ajax({
    url: "paginas/marketplace/salvar.php",
    method: "POST",
    data: {acao:'status', id:id, status:novoStatus},
    dataType: "text",
    success: function(mensagem){
      $('#mensagem-marketplace').removeClass();

      if(mensagem.trim() == "Salvo com Sucesso"){
        $('#mensagem-marketplace').addClass('text-success').text(mensagem);
        carregarResumoMarketplace();
        listarMarketplace();
      }else{
        $('#mensagem-marketplace').addClass('text-danger').text(bbMarketplaceMensagemRetorno(mensagem, 'Erro ao alterar status'));
      }
    },
    error: function(xhr){
      $('#mensagem-marketplace').removeClass().addClass('text-danger').text(bbMarketplaceMensagemErroAjax(xhr, 'Erro AJAX ao alterar status'));
    }
  });
}

$("#form-tenant").submit(function (e) {
  e.preventDefault();

  var formData = new FormData(this);

  var tel = ($('#telefone_whatsapp').val() || '').replace(/\D/g, '');
  formData.set('telefone_whatsapp', tel);

  $.ajax({
    url: "paginas/marketplace/salvar.php",
    type: "POST",
    data: formData,
    cache: false,
    contentType: false,
    processData: false,
    success: function (mensagem) {
      $("#mensagem-tenant").removeClass();

      if (mensagem.trim() == "Salvo com Sucesso") {
        $("#mensagem-tenant").addClass("text-success").text(mensagem);
        $('#modalTenant').modal('hide');
        carregarResumoMarketplace();
        listarMarketplace();
      } else {
        $("#mensagem-tenant").addClass("text-danger").text(bbMarketplaceMensagemRetorno(mensagem, "Erro ao salvar"));
      }
    },
    error: function(xhr){
      $("#mensagem-tenant").removeClass().addClass("text-danger").text(bbMarketplaceMensagemErroAjax(xhr, "Erro AJAX ao salvar"));
    }
  });
});

$("#form-baixa-marketplace").submit(function (e) {
  e.preventDefault();

  $('#btn_baixa_marketplace').prop('disabled', true);
  $('#mensagem-baixa-marketplace').removeClass().text('Baixando pagamento...');

  var formData = new FormData(this);

  $.ajax({
    url: "paginas/marketplace/salvar.php",
    type: "POST",
    data: formData,
    cache: false,
    contentType: false,
    processData: false,
    success: function (mensagem) {
      $('#btn_baixa_marketplace').prop('disabled', false);
      $("#mensagem-baixa-marketplace").removeClass();

      if (mensagem.trim() == "Baixado com Sucesso") {
        $("#mensagem-baixa-marketplace").addClass("text-success").text(mensagem);
        $('#modalBaixaMarketplace').modal('hide');
        $('#mensagem-marketplace').removeClass().addClass('text-success').text(mensagem);
        carregarResumoMarketplace();
        listarMarketplace();
      } else {
        $("#mensagem-baixa-marketplace").addClass("text-danger").text(bbMarketplaceMensagemRetorno(mensagem, "Erro ao baixar pagamento"));
      }
    },
    error: function(){
      $('#btn_baixa_marketplace').prop('disabled', false);
      $("#mensagem-baixa-marketplace").removeClass().addClass("text-danger").text("Erro AJAX ao baixar pagamento");
    }
  });
});
</script>

<script>
function bbMarketplaceResolverBotaoId(botaoOuId, idOuNull){
  var retorno = { botao: null, id: '' };

  if (typeof botaoOuId === 'object' && botaoOuId !== null && botaoOuId.nodeType === 1) {
    retorno.botao = botaoOuId;
    retorno.id = idOuNull || '';
    return retorno;
  }

  retorno.id = botaoOuId || '';
  return retorno;
}

function bbMarketplaceTravarBotao(botao){
  if(!botao){
    return;
  }

  var $botao = $(botao);
  if($botao.data('loading') === 1){
    return;
  }

  $botao.data('loading', 1);
  $botao.addClass('disabled');
  $botao.css({'pointer-events':'none','opacity':'0.6'});
}

function bbMarketplaceLiberarBotao(botao){
  if(!botao){
    return;
  }

  var $botao = $(botao);
  $botao.data('loading', 0);
  $botao.removeClass('disabled');
  $botao.css({'pointer-events':'','opacity':''});
}

function gerarCobranca(id){
  $('#mensagem-marketplace').removeClass().text('Gerando cobrança...');

  $.ajax({
    url: "paginas/marketplace/cobranca.php",
    method: "POST",
    data: {acao:'gerar', id:id},
    dataType: "json",
    success: function(r){
      $('#mensagem-marketplace').removeClass();

      if(r && r.ok && r.url){
        if(r.whats_sent){
          $('#mensagem-marketplace').addClass('text-success').text('Cobrança gerada e enviada no WhatsApp! Abrindo link...');
        }else{
          $('#mensagem-marketplace').addClass('text-success').text('Cobrança gerada! Abrindo link...');
        }

        window.open(r.url, '_blank');
        carregarResumoMarketplace();
        listarMarketplace();
        return;
      }

      $('#mensagem-marketplace').addClass('text-danger').text((r && r.msg) ? r.msg : 'Erro ao gerar cobrança');
      if(r && r.reload){
        carregarResumoMarketplace();
        listarMarketplace();
      }
    },
    error: function(xhr){
      $('#mensagem-marketplace').removeClass().addClass('text-danger').text(bbMarketplaceMensagemErroAjax(xhr, 'Erro AJAX ao gerar cobrança'));
    }
  });
}

function enviarCobrancaWhatsapp(botaoOuId, idOuNull){
  return enviarCobrancaWhats(botaoOuId, idOuNull);
}

function enviarCobrancaWhats(botaoOuId, idOuNull){
  var dados = bbMarketplaceResolverBotaoId(botaoOuId, idOuNull);
  var botao = dados.botao;
  var id = dados.id;

  if(!id){
    $('#mensagem-marketplace').removeClass().addClass('text-danger').text('ID inválido para envio.');
    return false;
  }

  bbMarketplaceTravarBotao(botao);
  $('#mensagem-marketplace').removeClass().text('Enviando cobrança no WhatsApp...');

  $.ajax({
    url: "paginas/marketplace/cobranca.php",
    method: "POST",
    data: {acao:'enviar', id:id},
    dataType: "json",
    success: function(r){
      $('#mensagem-marketplace').removeClass();

      if(r && r.ok){
        var msgOk = (r.msg && r.msg !== '') ? r.msg : 'Cobrança enviada com sucesso.';
        $('#mensagem-marketplace').addClass('text-success').text(msgOk);
      }else{
        $('#mensagem-marketplace').addClass('text-danger').text((r && r.msg) ? r.msg : 'Erro ao enviar');
      }

      if(r && r.reload){
        carregarResumoMarketplace();
        listarMarketplace();
      }
    },
    error: function(xhr){
      $('#mensagem-marketplace').removeClass().addClass('text-danger').text(bbMarketplaceMensagemErroAjax(xhr, 'Erro AJAX ao enviar'));
    },
    complete: function(){
      bbMarketplaceLiberarBotao(botao);
    }
  });

  return false;
}
</script>
