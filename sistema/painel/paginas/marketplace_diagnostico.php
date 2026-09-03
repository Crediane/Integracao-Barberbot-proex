<?php
require_once __DIR__ . '/marketplace/master_bootstrap.php';

$pag = 'marketplace_diagnostico';
$tenantSelecionado = (int)($_GET['tenant'] ?? 0);
?>

<div class="row">
  <div class="col-md-12">
    <style>
      .bb-diag-page-head{display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;margin-bottom:18px;}
      .bb-diag-page-title{margin:0 0 5px;color:#0f172a;font-size:24px;font-weight:700;}
      .bb-diag-page-subtitle{margin:0;color:#64748b;font-size:14px;line-height:1.55;max-width:820px;}
      .bb-diag-page-actions{display:flex;gap:8px;flex-wrap:wrap;}
      .bb-diag-security-note{display:flex;align-items:flex-start;gap:12px;padding:14px 16px;margin-bottom:18px;border:1px solid #bae6fd;border-radius:12px;background:#f0f9ff;color:#0c4a6e;}
      .bb-diag-security-note i{font-size:20px;margin-top:1px;}
      .bb-diag-security-note strong{display:block;margin-bottom:3px;}
      .bb-diag-toolbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;padding:16px;margin-bottom:18px;background:#fff;border:1px solid #e8edf3;border-radius:14px;box-shadow:0 8px 22px rgba(15,23,42,.05);}
      .bb-diag-search{flex:1 1 360px;min-width:260px;}
      .bb-diag-message{flex:1 1 100%;min-height:20px;font-size:13px;}
      .bb-diag-summary{display:grid;grid-template-columns:repeat(4,minmax(160px,1fr));gap:14px;margin-bottom:18px;}
      .bb-diag-card{position:relative;overflow:hidden;padding:16px 18px;background:#fff;border:1px solid #e8edf3;border-radius:14px;box-shadow:0 8px 22px rgba(15,23,42,.05);}
      .bb-diag-card:before{content:'';position:absolute;left:0;top:0;width:4px;height:100%;background:#64748b;}
      .bb-diag-card span{display:block;color:#64748b;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.04em;}
      .bb-diag-card strong{display:block;margin-top:5px;color:#0f172a;font-size:28px;line-height:1.1;}
      .bb-diag-card-ok:before{background:#10b981;}
      .bb-diag-card-warn:before{background:#f59e0b;}
      .bb-diag-card-info:before{background:#0ea5e9;}
      .bb-diag-table-card{background:#fff;border:1px solid #e8edf3;border-radius:14px;box-shadow:0 8px 22px rgba(15,23,42,.05);overflow:hidden;}
      .bb-diag-table-head{display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap;padding:15px 18px;border-bottom:1px solid #eef2f7;background:#f8fafc;}
      .bb-diag-table-head strong{display:block;color:#0f172a;}
      .bb-diag-table-head small{display:block;margin-top:3px;color:#64748b;}
      .bb-diag-master-badge{display:inline-flex;align-items:center;gap:6px;padding:6px 10px;border-radius:999px;background:#e0f2fe;color:#0369a1;font-size:11px;font-weight:700;}
      .bb-diag-table{margin-bottom:0;min-width:1050px;}
      .bb-diag-table thead th{border-top:0;background:#fff;color:#475569;font-size:12px;text-transform:uppercase;letter-spacing:.035em;white-space:nowrap;vertical-align:middle!important;}
      .bb-diag-table tbody td{vertical-align:middle!important;color:#334155;}
      .bb-diag-table code{display:block;margin-top:5px;padding:4px 6px;border-radius:6px;background:#f1f5f9;color:#334155;font-size:11px;max-width:230px;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;}
      .bb-diag-table small{color:#64748b;}
      .bb-diag-status{display:inline-flex;align-items:center;gap:5px;padding:5px 9px;border-radius:999px;font-size:11px;font-weight:700;white-space:nowrap;}
      .bb-diag-ok{background:#dcfce7;color:#166534;}
      .bb-diag-error{background:#fee2e2;color:#991b1b;}
      .bb-diag-empty{padding:30px;text-align:center;color:#64748b;}
      .bb-diag-loading{padding:28px;text-align:center;color:#64748b;background:#fff;border:1px solid #e8edf3;border-radius:14px;}
      @media(max-width:991.98px){.bb-diag-summary{grid-template-columns:repeat(2,minmax(160px,1fr));}}
      @media(max-width:575.98px){
        .bb-diag-summary{grid-template-columns:1fr;}
        .bb-diag-page-actions,.bb-diag-page-actions .btn,.bb-diag-toolbar .btn{width:100%;}
        .bb-diag-search{min-width:100%;}
      }
    </style>

    <div class="bb-diag-page-head">
      <div>
        <h2 class="bb-diag-page-title">Diagnóstico de isolamento</h2>
        <p class="bb-diag-page-subtitle">Valide o vínculo entre o slug público e o Banco Individual de cada barbearia, sem misturar dados com o Banco Global/Administrativo do painel Master.</p>
      </div>
      <div class="bb-diag-page-actions">
        <a href="?pagina=marketplace" class="btn btn-outline-secondary"><i class="fa fa-arrow-left"></i> Voltar ao Marketplace</a>
      </div>
    </div>

    <div class="bb-diag-security-note">
      <i class="fa fa-shield"></i>
      <div>
        <strong>Execução exclusiva no contexto Master</strong>
        Esta página consulta o cadastro global apenas para localizar o tenant. O botão de teste abre somente o banco individual mapeado pelo respectivo <code>slug</code> e confirma o nome do banco selecionado.
      </div>
    </div>

    <div class="bb-diag-toolbar">
      <div class="bb-diag-search">
        <input type="text" class="form-control" id="bb-diag-busca" placeholder="Buscar por nome, slug, domínio ou banco..." autocomplete="off">
      </div>
      <button type="button" class="btn btn-primary" id="bb-diag-atualizar"><i class="fa fa-refresh"></i> Atualizar diagnóstico</button>
      <div id="bb-diag-message" class="bb-diag-message" aria-live="polite"></div>
    </div>

    <div id="bb-diag-conteudo">
      <div class="bb-diag-loading"><i class="fa fa-spinner fa-spin"></i> Carregando diagnóstico...</div>
    </div>
  </div>
</div>

<script>
(function($){
  'use strict';

  var tenantInicial = <?php echo $tenantSelecionado; ?>;
  var timerBusca = null;

  function mensagem(texto, classe){
    $('#bb-diag-message').removeClass('text-success text-danger text-info').addClass(classe || '').text(texto || '');
  }

  function retornoHtmlInvalido(html){
    var lower = (html || '').toString().toLowerCase();
    return lower.indexOf('<!doctype') !== -1
      || lower.indexOf('<html') !== -1
      || lower.indexOf('recuperar senha') !== -1
      || lower.indexOf('/sistema/autenticar.php') !== -1;
  }

  function carregarDiagnostico(){
    mensagem('Atualizando diagnóstico...', 'text-info');
    $('#bb-diag-conteudo').html('<div class="bb-diag-loading"><i class="fa fa-spinner fa-spin"></i> Validando mapeamentos...</div>');

    $.ajax({
      url: 'paginas/marketplace/diagnostico.php',
      method: 'POST',
      dataType: 'html',
      data: {
        acao: 'listar',
        busca: $('#bb-diag-busca').val() || '',
        tenant: tenantInicial
      },
      success: function(html){
        if(retornoHtmlInvalido(html)){
          $('#bb-diag-conteudo').html('<div class="alert alert-danger">Sessão Master expirada. Atualize a página e faça login novamente.</div>');
          mensagem('Sessão Master expirada.', 'text-danger');
          return;
        }
        $('#bb-diag-conteudo').html(html);
        mensagem('Diagnóstico atualizado.', 'text-success');
      },
      error: function(xhr){
        var texto = xhr && xhr.responseText ? xhr.responseText : 'Não foi possível carregar o diagnóstico.';
        $('#bb-diag-conteudo').html('<div class="alert alert-danger"></div>');
        $('#bb-diag-conteudo .alert').text(texto);
        mensagem('Erro ao carregar o diagnóstico.', 'text-danger');
      }
    });
  }

  $(document).on('click', '.bb-btn-testar-tenant', function(){
    var botao = $(this);
    var id = parseInt(botao.data('id'), 10) || 0;
    if(id <= 0 || botao.prop('disabled')){
      return;
    }

    var htmlOriginal = botao.html();
    botao.prop('disabled', true).html('<i class="fa fa-spinner fa-spin"></i> Testando...');
    mensagem('Testando conexão isolada...', 'text-info');

    $.ajax({
      url: 'paginas/marketplace/diagnostico.php',
      method: 'POST',
      dataType: 'json',
      data: {acao: 'testar', id: id},
      success: function(resp){
        if(resp && resp.ok){
          mensagem(resp.msg + ' Banco confirmado: ' + resp.database + '.', 'text-success');
        }else{
          mensagem(resp && resp.msg ? resp.msg : 'A conexão não foi confirmada.', 'text-danger');
        }
      },
      error: function(xhr){
        var resposta = null;
        try { resposta = JSON.parse(xhr.responseText); } catch(e) {}
        mensagem(resposta && resposta.msg ? resposta.msg : 'Falha ao testar a conexão individual.', 'text-danger');
      },
      complete: function(){
        botao.prop('disabled', false).html(htmlOriginal);
      }
    });
  });

  $('#bb-diag-busca').on('input', function(){
    tenantInicial = 0;
    clearTimeout(timerBusca);
    timerBusca = setTimeout(carregarDiagnostico, 300);
  });

  $('#bb-diag-atualizar').on('click', function(){
    carregarDiagnostico();
  });

  $(document).ready(function(){
    carregarDiagnostico();
  });
})(jQuery);
</script>
