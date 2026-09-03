document.addEventListener('DOMContentLoaded', function(){
  var $ = window.jQuery || window.$;
  var banner = document.getElementById('cookieBanner');

  if (banner && !localStorage.cookieChoice) {
    banner.classList.remove('hide');
  }

  var aceita = document.getElementById('aceitarCookies');
  var recusa = document.getElementById('recusarCookies');

  if (aceita) {
    aceita.onclick = function(){
      localStorage.cookieChoice = 'accept';
      if (banner) banner.classList.add('hide');
    };
  }

  if (recusa) {
    recusa.onclick = function(){
      localStorage.cookieChoice = 'deny';
      if (banner) banner.classList.add('hide');
    };
  }

  function onlyDigits(value) {
    return (value || '').replace(/\D+/g, '').slice(0, 11);
  }

  function formatBR(digits) {
    if (digits.length <= 2) return digits;
    if (digits.length <= 6) return '(' + digits.slice(0, 2) + ') ' + digits.slice(2);
    if (digits.length <= 10) return '(' + digits.slice(0, 2) + ') ' + digits.slice(2, 6) + '-' + digits.slice(6);
    return '(' + digits.slice(0, 2) + ') ' + digits.slice(2, 7) + '-' + digits.slice(7);
  }

  var telefoneContato = document.getElementById('telefone');
  if (telefoneContato) {
    telefoneContato.addEventListener('input', function(){
      telefoneContato.value = formatBR(onlyDigits(telefoneContato.value));
    });
    telefoneContato.addEventListener('paste', function(e){
      e.preventDefault();
      var clip = (e.clipboardData || window.clipboardData).getData('text') || '';
      telefoneContato.value = formatBR(onlyDigits(clip));
    });
  }

  if ($) {
    $('#form-email').on('submit', function(e){
      e.preventDefault();
      var form = this;
      var $msg = $('#mensagem');
      var enviarContato = function(){
        var formData = new FormData(form);
        $.ajax({
          url:'ajax/enviar-email.php',
          type:'POST',
          data:formData,
          success:function(mensagem){
            var texto = (mensagem || '').trim();
            $msg.text('').removeClass();
            if (texto == 'Enviado com Sucesso') {
              $msg.addClass('text-success').text(texto);
              form.reset();
              if (window.grecaptcha && typeof grecaptcha.reset === 'function') {
                try { grecaptcha.reset(); } catch(e) {}
              }
            } else {
              $msg.addClass('text-danger').text(texto || 'Não foi possível enviar a mensagem.');
            }
          },
          error:function(){
            $msg.text('').removeClass().addClass('text-danger').text('Falha de conexão. Tente novamente.');
          },
          cache:false,
          contentType:false,
          processData:false
        });
      };

      if (window.homePrepareRecaptcha) {
        window.homePrepareRecaptcha(form, 'contato').then(enviarContato);
      } else {
        enviarContato();
      }
    });

    $('#form').on('submit', function(e){
      e.preventDefault();
      var formData = new FormData(this);

      $.ajax({
        url:'sistema/painel/paginas/comentarios/salvar.php',
        type:'POST',
        data:formData,
        success:function(mensagem){
          $('#mensagem-comentario').text('').removeClass();

          if (mensagem.trim() == 'Salvo com Sucesso') {
            $('#mensagem-comentario').addClass('text-success').text('Comentário Enviado para Aprovação!');
            $('#nome_cliente').val('');
            $('#texto_cliente').val('');
          } else {
            $('#mensagem-comentario').addClass('text-danger').text(mensagem);
          }
        },
        cache:false,
        contentType:false,
        processData:false
      });
    });

    $('.panel-collapse').on('show.bs.collapse', function(){
      $(this).prev('.panel-heading').find('.toggle-icon').text('–');
    });

    $('.panel-collapse').on('hide.bs.collapse', function(){
      $(this).prev('.panel-heading').find('.toggle-icon').text('+');
    });
  }

  window.carregarImg = function(){
    var target = document.getElementById('target');
    var input = document.querySelector('#foto');
    var file = input && input.files ? input.files[0] : null;
    if (!target) return;

    var reader = new FileReader();
    reader.onloadend = function(){
      target.src = reader.result;
    };

    if (file) {
      reader.readAsDataURL(file);
    } else {
      target.src = '';
    }
  };
});

(function(){
  var cont = document.getElementById('map_container');
  if (!cont) return;

  function mount(){
    if (cont.dataset.mounted) return;

    var tpl = document.getElementById('map_template');
    if (!tpl) return;

    cont.innerHTML = tpl.innerHTML.replace('<iframe', '<iframe loading="lazy" referrerpolicy="strict-origin-when-cross-origin" ');
    cont.dataset.mounted = '1';
  }

  if ('IntersectionObserver' in window) {
    new IntersectionObserver(function(es, obs){
      if (es[0].isIntersecting) {
        mount();
        obs.disconnect();
      }
    }, {rootMargin:'600px 0px'}).observe(cont);
  } else {
    mount();
  }
})();

(function(){
  function onIdle(cb){
    if ('requestIdleCallback' in window) {
      requestIdleCallback(cb, {timeout:1200});
    } else {
      setTimeout(cb, 500);
    }
  }

  onIdle(function(){
    if (!window.jQuery) return;
    var $ = window.jQuery;

    try {
      $('#customCarousel1').carousel({ interval: 5000, ride: 'carousel', pause: false });
    } catch(e) {}

    function initOwl(){
      if (!$.fn.owlCarousel) return;

      var $prod = $('.product_owl-carousel'),
          $cli = $('.client_owl-carousel');

      if ($prod.length && !$prod.data('owl-loaded')) {
        $prod.owlCarousel({
          loop:true,
          margin:18,
          autoplay:true,
          smartSpeed:650,
          nav:false,
          dots:true,
          responsive:{0:{items:1},576:{items:2},992:{items:3}}
        }).data('owl-loaded', 1);
      }

      if ($cli.length && !$cli.data('owl-loaded')) {
        $cli.owlCarousel({
          loop:true,
          autoplay:true,
          items:1,
          smartSpeed:650,
          nav:false,
          dots:true
        }).data('owl-loaded', 1);
      }
    }

    var firstOwl = document.querySelector('.owl-carousel');

    if (firstOwl && 'IntersectionObserver' in window) {
      var io = new IntersectionObserver(function(entries, obs){
        entries.forEach(function(entry){
          if (entry.isIntersecting) {
            initOwl();
            obs.disconnect();
          }
        });
      }, {rootMargin:'600px 0px'});

      io.observe(firstOwl);
    } else {
      initOwl();
    }
  });
})();

(function(){
  var urlSimulacaoCliente = 'ajax/gerar_ia_cliente.php';

  window.carregarPreviewSimulacaoCliente = function(){
    var campo = document.getElementById('foto_simulacao_cliente');
    var imgOriginal = document.getElementById('preview_original_simulacao_cliente');
    var imgResultado = document.getElementById('preview_resultado_simulacao_cliente');

    if (!campo || !imgOriginal || !imgResultado) {
      return;
    }

    if (!campo.files || !campo.files[0]) {
      imgOriginal.src = 'sistema/painel/images/simulacoes/sem-foto.jpg';
      imgResultado.src = 'sistema/painel/images/simulacoes/sem-foto.jpg';
      return;
    }

    var arquivo = campo.files[0];
    var reader = new FileReader();

    reader.onload = function(e){
      imgOriginal.src = e.target.result;
      imgResultado.src = 'sistema/painel/images/simulacoes/sem-foto.jpg';
    };

    reader.onerror = function(){
      imgOriginal.src = 'sistema/painel/images/simulacoes/sem-foto.jpg';
      imgResultado.src = 'sistema/painel/images/simulacoes/sem-foto.jpg';
    };

    reader.readAsDataURL(arquivo);
  };

  window.enviarSimulacaoCliente = function(){
    var form = document.getElementById('form_simulacao_cliente');
    var campo = document.getElementById('foto_simulacao_cliente');
    var btn = document.getElementById('btn_simular_cliente');
    var icone = document.getElementById('icone_btn_simular_cliente');
    var msg = document.getElementById('mensagem_simulacao_cliente');
    var imgOriginal = document.getElementById('preview_original_simulacao_cliente');
    var imgResultado = document.getElementById('preview_resultado_simulacao_cliente');

    if (!form || !campo || !btn || !icone || !msg || !imgOriginal || !imgResultado) {
      return;
    }

    if (!campo.files || !campo.files[0]) {
      msg.className = 'text-danger';
      msg.innerText = 'Selecione uma imagem para simular';
      return;
    }

    var formData = new FormData(form);

    btn.disabled = true;
    icone.className = 'fa fa-spinner fa-spin';
    msg.className = 'text-info';
    msg.innerText = 'Gerando simulação, aguarde...';

    fetch(urlSimulacaoCliente, {
      method: 'POST',
      body: formData
    })
    .then(async function(response){
      var texto = await response.text();
      var dados = null;

      try {
        dados = JSON.parse(texto);
      } catch(e) {
        throw new Error(texto || 'Resposta inválida do servidor');
      }

      if (!response.ok || !dados || dados.status !== 'Sucesso') {
        throw new Error((dados && dados.mensagem) ? dados.mensagem : 'Erro ao processar a simulação');
      }

      if (dados.url_original) {
        imgOriginal.src = dados.url_original;
      }

      if (dados.url_simulada) {
        imgResultado.src = dados.url_simulada;
      }

      msg.className = 'text-success';
      msg.innerText = 'Simulação gerada com sucesso';
    })
    .catch(function(erro){
      msg.className = 'text-danger';
      msg.innerText = erro.message ? erro.message : 'Erro ao processar a simulação';
    })
    .finally(function(){
      btn.disabled = false;
      icone.className = 'fa fa-magic';
    });
  };

  if (window.jQuery) {
    $('#modalSimulacaoCliente').on('hidden.bs.modal', function(){
      var form = document.getElementById('form_simulacao_cliente');
      var imgOriginal = document.getElementById('preview_original_simulacao_cliente');
      var imgResultado = document.getElementById('preview_resultado_simulacao_cliente');
      var msg = document.getElementById('mensagem_simulacao_cliente');
      var btn = document.getElementById('btn_simular_cliente');
      var icone = document.getElementById('icone_btn_simular_cliente');

      if (form) {
        form.reset();
      }

      if (imgOriginal) {
        imgOriginal.src = 'sistema/painel/images/simulacoes/sem-foto.jpg';
      }

      if (imgResultado) {
        imgResultado.src = 'sistema/painel/images/simulacoes/sem-foto.jpg';
      }

      if (msg) {
        msg.innerText = '';
        msg.className = '';
      }

      if (btn) {
        btn.disabled = false;
      }

      if (icone) {
        icone.className = 'fa fa-magic';
      }
    });
  }
})();
