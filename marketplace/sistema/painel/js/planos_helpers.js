(function () {
  'use strict';

  function bbGetSlugAtual() {
    try {
      var path = (window.location && window.location.pathname) ? window.location.pathname : '';
      var m = path.match(/^\/loja\/([^\/]+)(?:\/|$)/i);
      if (m && m[1]) {
        return (m[1] + '').toLowerCase().replace(/[^a-z0-9_-]/g, '');
      }
    } catch (e) {}
    return '';
  }

  function bbDataComLoja(data) {
    data = data || {};
    var slug = bbGetSlugAtual();
    if (slug) data.loja = slug; // força sessão TENANT certa
    return data;
  }

  window.toggleAtivo = function (id, novo) {
    if (typeof $ === 'undefined') return;

    $.ajax({
      url: (window.bbBasePainel || '') + '/paginas/planos/status.php',
      method: 'POST',
      data: bbDataComLoja({ id: id, ativo: novo }),
      dataType: 'text',
      success: function (resp) {
        if ((resp || '').trim() === 'OK') {
          if (typeof window.listar === 'function') window.listar();
        } else {
          var el = document.getElementById('mensagem-excluir');
          if (el) { el.className = 'text-danger'; el.textContent = resp; } else { alert(resp); }
        }
      },
      error: function (xhr) {
        var msg = 'Erro ao atualizar status: ' + (xhr.responseText || xhr.status);
        var el = document.getElementById('mensagem-excluir');
        if (el) { el.className = 'text-danger'; el.textContent = msg; } else { alert(msg); }
      }
    });
  };

  window.editar = function (id, cliente, grupo, item, valor, frequencia, vencimento) {
    try {
      $('#id').val(id);
      $('#cliente').val(cliente).change();
      $('#grupo').val(grupo).change();
      $('#frequencia').val(frequencia).change();
      $('#vencimento').val(vencimento);

      if (typeof window.listarPlanos === 'function') window.listarPlanos();

      setTimeout(function () { $('#item').val(item).change(); }, 700);
      setTimeout(function () { $('#valor').val(valor); }, 900);

      $('#titulo_inserir').text('Editar Registro');
      $('#modalForm').modal('show');
    } catch (e) {}
  };

  window.baixar = function (id, valor) {
    try {
      $('#id_baixar').val(id);
      $('#valor_baixar').val(valor);
      $('#modalBaixar').modal('show');
    } catch (e) {}
  };

  window.cancelar = function (id, id_plano) {
    if (typeof $ === 'undefined') return;

    $.ajax({
      url: (window.bbBasePainel || '') + '/paginas/planos/cancelar.php',
      method: 'POST',
      data: bbDataComLoja({ id: id, id_plano: id_plano }),
      dataType: 'text',
      success: function (mensagem) {
        if ((mensagem || '').trim() === 'Excluído com Sucesso') {
          if (typeof window.listar === 'function') window.listar();
        } else {
          $('#mensagem-excluir').addClass('text-danger').text(mensagem);
        }
      }
    });
  };

  window.excluir = function (id) {
    if (typeof $ === 'undefined') return;

    $.ajax({
      url: (window.bbBasePainel || '') + '/paginas/planos/excluir.php',
      method: 'POST',
      data: bbDataComLoja({ id: id }),
      dataType: 'text',
      success: function (mensagem) {
        if ((mensagem || '').trim() === 'Excluído com Sucesso') {
          if (typeof window.listar === 'function') window.listar();
        } else {
          $('#mensagem-excluir').addClass('text-danger').text(mensagem);
        }
      }
    });
  };
})();