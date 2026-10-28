// BarberBot - helper: excluir_reg (app/prof/painel)
// Corrige: "Uncaught ReferenceError: excluir_reg is not defined"
// Uso esperado no HTML:
//   onclick="return excluir_reg(123)"
// ou, se o endpoint não for o padrão:
//   onclick="return excluir_reg(123,'excluir.php')"
(function () {
  if (window.excluir_reg) return;

  window.excluir_reg = function (id, endpoint) {
    try {
      if (!confirm('Deseja excluir este registro?')) return false;

      var path = window.location.pathname || '/';
      var base = path.replace(/[^\/]*$/, ''); // diretório atual (ex: /loja/slug/prof/painel/)
      var file = (endpoint && typeof endpoint === 'string') ? endpoint.replace(/^\/+/, '') : 'excluir.php';
      var urlPost = base + file;

      var body = 'id=' + encodeURIComponent(String(id));

      // Tenta POST (padrão do sistema), e depois recarrega a listagem
      fetch(urlPost, {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
        body: body,
        credentials: 'same-origin'
      })
      .then(function () { window.location.reload(); })
      .catch(function () {
        // fallback: tenta GET (caso o endpoint use querystring)
        window.location.href = urlPost + (urlPost.indexOf('?') >= 0 ? '&' : '?') + body;
      });

      return false;
    } catch (e) {
      // fallback seguro
      return true;
    }
  };
})();
