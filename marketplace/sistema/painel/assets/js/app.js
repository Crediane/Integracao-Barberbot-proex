// assets/js/app.js

(function () {
  'use strict';

  function limparPagina(pagina) {
    pagina = String(pagina || '').trim();
    pagina = pagina.replace(/[^a-zA-Z0-9_-]/g, '');
    return pagina;
  }

  function pegarBasePainel() {
    var path = window.location.pathname || '/';
    var matchLoja = path.match(/^(.*\/loja\/[^\/]+\/painel)(?:\/.*)?$/i);
    var matchSistema = path.match(/^(.*\/sistema\/painel)(?:\/.*)?$/i);
    var matchPainel = path.match(/^(.*\/painel)(?:\/.*)?$/i);

    if (window.BB_PAINEL_PUBLIC) {
      return String(window.BB_PAINEL_PUBLIC).replace(/\/+$/, '') + '/';
    }

    if (matchLoja && matchLoja[1]) {
      return matchLoja[1].replace(/\/+$/, '') + '/';
    }

    if (matchSistema && matchSistema[1]) {
      return matchSistema[1].replace(/\/+$/, '') + '/';
    }

    if (matchPainel && matchPainel[1]) {
      return matchPainel[1].replace(/\/+$/, '') + '/';
    }

    return './';
  }

  function pegarPaginaDaUrl() {
    var params = new URLSearchParams(window.location.search || '');
    var pagQuery = limparPagina(params.get('pag'));
    var path = window.location.pathname || '';
    var match = path.match(/\/painel\/([^\/\?#]+)/i);
    var pagPath = '';

    if (pagQuery !== '') {
      return pagQuery;
    }

    if (match && match[1]) {
      pagPath = limparPagina(match[1]);
    }

    if (pagPath === 'index' || pagPath === 'indexphp' || pagPath === 'paginas') {
      return '';
    }

    return pagPath;
  }

  function montarUrlAjax(pagina) {
    var basePainel = pegarBasePainel();
    return basePainel + 'paginas/' + encodeURIComponent(pagina) + '.php';
  }

  function montarUrlNavegacao(pagina) {
    var basePainel = pegarBasePainel();
    return basePainel + 'index.php?pag=' + encodeURIComponent(pagina);
  }

  function respostaBloqueada(dados) {
    var texto = String(dados || '').toLowerCase();

    if (texto.indexOf('out system') !== -1) {
      return true;
    }

    if (texto.indexOf("alert('out system')") !== -1) {
      return true;
    }

    if (texto.indexOf('alert("out system")') !== -1) {
      return true;
    }

    return false;
  }

  function carregarPagInterno(pagina, atualizarUrl) {
    var paginaLimpa = limparPagina(pagina);
    var urlAjax = '';
    var urlNavegacao = '';

    if (paginaLimpa === '') {
      $('#container').html('<div class="alert alert-danger">Página inválida.</div>');
      return;
    }

    urlAjax = montarUrlAjax(paginaLimpa);
    urlNavegacao = montarUrlNavegacao(paginaLimpa);

    $.ajax({
      url: urlAjax,
      method: 'GET',
      cache: false,
      beforeSend: function () {
        $('#container').html('<div class="text-center"><i class="fa fa-spinner fa-spin"></i> Carregando...</div>');
      },
      success: function (dados) {
        if (respostaBloqueada(dados)) {
          $('#container').html('<div class="alert alert-danger">Sessão inválida ou página fora da rota do painel. Faça login novamente e acesse pelo painel da loja.</div>');
          return;
        }

        $('#container').html(dados);

        if (atualizarUrl !== false && window.history && window.history.pushState) {
          history.pushState({ pag: paginaLimpa }, null, urlNavegacao);
        }
      },
      error: function (xhr) {
        var codigo = xhr && xhr.status ? ' Código: ' + xhr.status : '';
        $('#container').html('<div class="alert alert-danger">Erro ao carregar a página.' + codigo + '</div>');
      }
    });
  }

  window.carregarPag = function (pagina) {
    carregarPagInterno(pagina, true);
  };

  window.onpopstate = function () {
    var pagina = pegarPaginaDaUrl();

    if (pagina !== '') {
      carregarPagInterno(pagina, false);
    }
  };

  $(document).ready(function () {
    var pagina = pegarPaginaDaUrl();
    var urlNavegacao = '';

    if (pagina !== '') {
      urlNavegacao = montarUrlNavegacao(pagina);

      if (window.history && window.history.replaceState) {
        history.replaceState({ pag: pagina }, null, urlNavegacao);
      }

      carregarPagInterno(pagina, false);
    }
  });
})();
