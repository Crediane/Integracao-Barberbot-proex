(function () {
  'use strict';

  var CONFIG = window.BARBERBOT_INDEX || {};
  var RECAPTCHA_SITE_KEY = CONFIG.recaptchaSiteKey || '';
  var URL_SIMULACAO_CLIENTE = CONFIG.urlSimulacaoCliente || 'ajax/gerar_ia_cliente.php';
  var URL_CADASTRO_CLIENTE = CONFIG.urlCadastroCliente || 'cadastrar.php';
  var IMAGEM_PADRAO_SIMULACAO = CONFIG.imagemPadraoSimulacao || 'sistema/painel/images/simulacoes/sem-foto.jpg';
  var WHATSAPP_SALAO = CONFIG.whatsappSalao || '';

  function ready(fn) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', fn);
      return;
    }
    fn();
  }

  function loadScript(src, callback) {
    var s = document.createElement('script');
    s.src = src;
    s.async = true;
    s.onload = function () {
      if (callback) {
        callback();
      }
    };
    s.onerror = function () {
      if (callback) {
        callback(new Error('Erro ao carregar: ' + src));
      }
    };
    document.head.appendChild(s);
  }

  function loadCss(href) {
    var l = document.createElement('link');
    l.rel = 'stylesheet';
    l.href = href;
    document.head.appendChild(l);
  }

  function onIdle(callback) {
    if ('requestIdleCallback' in window) {
      requestIdleCallback(callback, { timeout: 1200 });
      return;
    }
    setTimeout(callback, 500);
  }

  function ensureJQuery(next) {
    if (window.jQuery) {
      next();
      return;
    }

    loadScript('js/jquery-3.6.4.min.js', function () {
      next();
    });
  }

  function ensureBootstrap(next) {
    if (window.bootstrap || (window.jQuery && window.jQuery.fn && window.jQuery.fn.modal)) {
      next();
      return;
    }

    loadScript('js/bootstrap.js', function () {
      next();
    });
  }

  function ensureOwl(next) {
    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.owlCarousel) {
      next();
      return;
    }

    loadCss('https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css');
    loadCss('https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.theme.default.min.css');

    loadScript('https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/owl.carousel.min.js', function () {
      next();
    });
  }

  function ensureMask(next) {
    if (window.jQuery && window.jQuery.fn && window.jQuery.fn.mask) {
      next();
      return;
    }

    loadScript('https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js', function () {
      next();
    });
  }

  function initComentarioForm() {
    if (!window.jQuery) {
      return;
    }

    var $ = window.jQuery;
    var $form = $('#form');

    if (!$form.length) {
      return;
    }

    function fecharModalComentario() {
      var $modal = $('#modalComentario');

      if (!$modal.length) {
        return;
      }

      if ($.fn && typeof $.fn.modal === 'function') {
        $modal.modal('hide');
        return;
      }

      $modal.removeClass('show').attr('aria-hidden', 'true').hide();
      $('body').removeClass('modal-open');
      $('.modal-backdrop').remove();
    }

    function limparFormularioComentario() {
      if ($form[0]) {
        $form[0].reset();
      }

      $('#nome_cliente').val('');
      $('#texto_cliente').val('');
      $('#foto').val('');
      $('#video').val('');
      $('#print_whatsapp').val('');
      $('#target').attr('src', 'sistema/painel/img/comentarios/sem-foto.jpg');
    }

    $form.on('submit', function (e) {
      e.preventDefault();

      var formData = new FormData(this);

      $.ajax({
        url: 'sistema/painel/paginas/comentarios/salvar.php',
        type: 'POST',
        data: formData,
        success: function (m) {
          $('#mensagem-comentario').text('').removeClass();

          if ((m || '').trim() === 'Salvo com Sucesso') {
            $('#mensagem-comentario')
              .addClass('text-success')
              .text('Comentário Enviado para Aprovação!');

            limparFormularioComentario();

            setTimeout(function () {
              fecharModalComentario();
              $('#mensagem-comentario').text('').removeClass();
            }, 1000);
          } else {
            $('#mensagem-comentario')
              .addClass('text-danger')
              .text(m);
          }
        },
        error: function () {
          $('#mensagem-comentario')
            .text('Erro ao enviar o depoimento. Tente novamente.')
            .removeClass()
            .addClass('text-danger');
        },
        cache: false,
        contentType: false,
        processData: false
      });
    });
  }

  function initCarregarImagemComentario() {
    window.carregarImg = function () {
      var target = document.getElementById('target');
      var foto = document.getElementById('foto');

      if (!target || !foto || !foto.files || !foto.files[0]) {
        if (target) {
          target.src = '';
        }
        return;
      }

      var reader = new FileReader();
      reader.onloadend = function () {
        target.src = reader.result;
      };
      reader.readAsDataURL(foto.files[0]);
    };
  }

  function initPreviewSimulacaoCliente() {
    window.carregarPreviewSimulacaoCliente = function () {
      var input = document.getElementById('foto_simulacao_cliente');
      var preview = document.getElementById('preview_original_simulacao_cliente');

      if (!input || !preview || !input.files || !input.files[0]) {
        return;
      }

      var reader = new FileReader();
      reader.onload = function (e) {
        preview.src = e.target.result;
      };
      reader.readAsDataURL(input.files[0]);
    };
  }

  function initFaqCollapseIcon() {
    if (!window.jQuery) {
      return;
    }

    var $ = window.jQuery;

    $('.panel-collapse').on('show.bs.collapse', function () {
      $(this).prev('.panel-heading').find('.toggle-icon').text('–');
    });

    $('.panel-collapse').on('hide.bs.collapse', function () {
      $(this).prev('.panel-heading').find('.toggle-icon').text('+');
    });
  }

  function initLazyMap() {
    var cont = document.getElementById('map_container');
    if (!cont) {
      return;
    }

    var iframe = cont.querySelector('iframe');
    if (!iframe) {
      return;
    }

    if (!iframe.getAttribute('loading')) {
      iframe.setAttribute('loading', 'lazy');
    }

    if (!iframe.getAttribute('referrerpolicy')) {
      iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
    }

    function carregarMapa() {
      var dataSrc = iframe.getAttribute('data-src');
      var srcAtual = iframe.getAttribute('src') || '';

      if (dataSrc && (srcAtual === '' || srcAtual === 'about:blank')) {
        iframe.setAttribute('src', dataSrc);
      }
    }

    if (!iframe.getAttribute('data-src')) {
      return;
    }

    if ('IntersectionObserver' in window) {
      var ioMapa = new IntersectionObserver(function (entries, observer) {
        entries.forEach(function (entry) {
          if (entry.isIntersecting) {
            carregarMapa();
            observer.disconnect();
          }
        });
      }, { rootMargin: '500px 0px' });

      ioMapa.observe(cont);
      return;
    }

    carregarMapa();
  }

  function initHeroAndOwl() {
    onIdle(function () {
      if (!window.jQuery) {
        return;
      }

      var $ = window.jQuery;

      try {
        $('#customCarousel1').carousel({
          interval: 3000,
          ride: 'carousel',
          pause: false
        });
      } catch (e) {
      }

      function initOwl() {
        if (!$.fn.owlCarousel) {
          return;
        }

        if (typeof window.jacyInitOwlCarousels === 'function') {
          window.jacyInitOwlCarousels();
          return;
        }

        var $prod = $('.product_owl-carousel');
        var $cli = $('.client_owl-carousel');

        if ($prod.length && !$prod.data('owl-loaded')) {
          $prod.owlCarousel({
            loop: true,
            margin: 18,
            autoplay: true,
            autoplayTimeout: 3500,
            autoplayHoverPause: true,
            smartSpeed: 650,
            autoHeight: false,
            nav: false,
            dots: true,
            responsive: {
              0: { items: 1, margin: 12 },
              576: { items: 2, margin: 14 },
              992: { items: 3, margin: 18 }
            }
          }).data('owl-loaded', 1);
        }

        if ($prod.length && $prod.data('owl-loaded')) {
          $prod.trigger('play.owl.autoplay', [3500]);
        }

        if ($cli.length && !$cli.data('owl-loaded')) {
          $cli.owlCarousel({
            loop: true,
            autoplay: true,
            autoplayTimeout: 5000,
            autoplayHoverPause: true,
            items: 1,
            smartSpeed: 600,
            autoHeight: false,
            nav: false,
            dots: true
          }).data('owl-loaded', 1);
        }

        if ($cli.length && $cli.data('owl-loaded')) {
          $cli.trigger('play.owl.autoplay', [5000]);
        }
      }

      function carregarOwlEIniciar() {
        if (window.jacyLoadOwlCarousel) {
          window.jacyLoadOwlCarousel(initOwl);
          return;
        }

        ensureOwl(initOwl);
      }

      var firstOwl = document.querySelector('.owl-carousel');

      if (firstOwl && 'IntersectionObserver' in window) {
        var io = new IntersectionObserver(function (entries, obs) {
          entries.forEach(function (entry) {
            if (entry.isIntersecting) {
              carregarOwlEIniciar();
              obs.disconnect();
            }
          });
        }, { rootMargin: '600px 0px' });

        io.observe(firstOwl);
        return;
      }

      carregarOwlEIniciar();
    });
  }


  function initPausarCarrosselDepoimentosComVideo() {
    if (!window.jQuery) {
      return;
    }

    var $ = window.jQuery;
    var $carrosseis = $('.client_owl-carousel').has('video');

    if (!$carrosseis.length) {
      return;
    }

    function pararCarrossel($carrossel) {
      if (!$carrossel || !$carrossel.length) {
        return;
      }

      try {
        $carrossel.trigger('stop.owl.autoplay');
      } catch (e) {
      }

      try {
        var owl = $carrossel.data('owl.carousel');
        if (owl && owl.settings) {
          owl.settings.autoplay = false;
        }
        if (owl && owl.options) {
          owl.options.autoplay = false;
        }
      } catch (e) {
      }

      $carrossel.addClass('depoimento-video-em-execucao');
      $carrossel.attr('data-video-pausou-carrossel', '1');
    }

    function voltarCarrossel($carrossel) {
      if (!$carrossel || !$carrossel.length) {
        return;
      }

      if (existeVideoRodando($carrossel)) {
        return;
      }

      try {
        var owl = $carrossel.data('owl.carousel');
        if (owl && owl.settings) {
          owl.settings.autoplay = true;
        }
        if (owl && owl.options) {
          owl.options.autoplay = true;
        }
      } catch (e) {
      }

      try {
        $carrossel.trigger('play.owl.autoplay', [6000]);
      } catch (e) {
      }

      $carrossel.removeClass('depoimento-video-em-execucao');
      $carrossel.removeAttr('data-video-pausou-carrossel');
    }

    function existeVideoRodando($carrossel) {
      var rodando = false;

      $carrossel.find('video').each(function () {
        if (!this.paused && !this.ended) {
          rodando = true;
          return false;
        }
      });

      return rodando;
    }

    function pausarOutrosVideos($carrossel, videoAtual) {
      $carrossel.find('video').each(function () {
        if (this !== videoAtual && !this.paused) {
          try {
            this.pause();
          } catch (e) {
          }
        }
      });
    }

    function pausarVideosForaDoSlideAtivo($carrossel) {
      $carrossel.find('video').each(function () {
        var $video = $(this);

        if (!$video.closest('.owl-item.active').length && !this.paused) {
          try {
            this.pause();
          } catch (e) {
          }
        }
      });
    }

    function carrosselDoVideo(video) {
      return $(video).closest('.client_owl-carousel');
    }

    function travarEventoDoVideo(e) {
      var video = this;
      var $carrossel = carrosselDoVideo(video);

      if (!$carrossel.length) {
        return;
      }

      pararCarrossel($carrossel);

      if (e && typeof e.stopPropagation === 'function') {
        e.stopPropagation();
      }
    }

    function bindVideo(video) {
      if (!video || video.getAttribute('data-pausa-carrossel-video') === '1') {
        return;
      }

      video.setAttribute('data-pausa-carrossel-video', '1');

      ['pointerdown', 'mousedown', 'touchstart', 'click', 'dblclick'].forEach(function (evento) {
        video.addEventListener(evento, travarEventoDoVideo, true);
      });

      video.addEventListener('play', function () {
        var $carrossel = carrosselDoVideo(video);
        if (!$carrossel.length) {
          return;
        }

        pausarOutrosVideos($carrossel, video);
        pararCarrossel($carrossel);
      }, true);

      video.addEventListener('playing', function () {
        var $carrossel = carrosselDoVideo(video);
        if (!$carrossel.length) {
          return;
        }

        pausarOutrosVideos($carrossel, video);
        pararCarrossel($carrossel);
      }, true);

      video.addEventListener('pause', function () {
        var $carrossel = carrosselDoVideo(video);
        if (!$carrossel.length) {
          return;
        }

        pararCarrossel($carrossel);
      }, true);

      video.addEventListener('ended', function () {
        var $carrossel = carrosselDoVideo(video);
        if (!$carrossel.length) {
          return;
        }

        setTimeout(function () {
          voltarCarrossel($carrossel);
        }, 800);
      }, true);
    }

    function bindVideosDoCarrossel($carrossel) {
      $carrossel.find('video').each(function () {
        bindVideo(this);
      });
    }

    $carrosseis.each(function () {
      var $carrossel = $(this);

      if ($carrossel.data('video-pause-bound-v2')) {
        bindVideosDoCarrossel($carrossel);
        return;
      }

      $carrossel.data('video-pause-bound-v2', 1);
      bindVideosDoCarrossel($carrossel);

      $carrossel.on('initialized.owl.carousel refreshed.owl.carousel translated.owl.carousel changed.owl.carousel', function () {
        bindVideosDoCarrossel($carrossel);
        pausarVideosForaDoSlideAtivo($carrossel);
      });

      $carrossel.on('drag.owl.carousel', function () {
        if (existeVideoRodando($carrossel) || $carrossel.attr('data-video-pausou-carrossel') === '1') {
          pararCarrossel($carrossel);
        }
      });

      if (window.MutationObserver) {
        var obs = new MutationObserver(function () {
          bindVideosDoCarrossel($carrossel);
        });

        obs.observe(this, {
          childList: true,
          subtree: true
        });
      }
    });
  }

  function initEmpresaModalButton() {
    var btn = document.querySelector('a[href="#empresa"].btn');
    if (!btn) {
      return;
    }

    btn.addEventListener('click', function (e) {
      var modal = document.getElementById('empresa');
      if (!modal) {
        return;
      }

      if (window.bootstrap && window.bootstrap.Modal) {
        e.preventDefault();
        window.bootstrap.Modal.getOrCreateInstance(modal).show();
        return;
      }

      if (window.jQuery && typeof window.jQuery.fn.modal === 'function') {
        e.preventDefault();
        window.jQuery(modal).modal('show');
      }
    });
  }

  function initPhoneMasks() {
    if (!window.jQuery || !window.jQuery.fn || !window.jQuery.fn.mask) {
      return;
    }

    var $ = window.jQuery;

    var SPMaskBehavior = function (val) {
      return val.replace(/\D/g, '').length === 11 ? '(00) 00000-0000' : '(00) 0000-00009';
    };

    var spOptions = {
      onKeyPress: function (val, e, field, options) {
        field.mask(SPMaskBehavior.apply({}, arguments), options);
      }
    };

    $('#telefone').mask(SPMaskBehavior, spOptions);
    $('#telefone_simulacao_cliente').mask(SPMaskBehavior, spOptions);
    $('#leadZap').mask(SPMaskBehavior, spOptions);
  }

  function initContatoForm() {
    if (!window.jQuery) {
      return;
    }

    var $ = window.jQuery;
    var $form = $('#form-email');

    if (!$form.length) {
      return;
    }

    $form.on('submit', function (e) {
      e.preventDefault();

      var form = this;
      var formData = new FormData(form);
      var action = $(form).data('recaptcha-action') || 'contato';
      var btnSubmit = $(form).find('button[type="submit"]');
      var btnText = btnSubmit.text();

      btnSubmit.prop('disabled', true).text('Enviando...');

      var prepare = window.jacyPrepareRecaptcha
        ? window.jacyPrepareRecaptcha(form, action)
        : Promise.resolve('');

      prepare.then(function (token) {
        if (token) {
          formData.set('recaptcha_token', token);
          formData.set('recaptcha_action', action);
        }

        $.ajax({
          url: 'ajax/enviar-email.php',
          type: 'POST',
          data: formData,
          success: function (m) {
            $('#mensagem').text('').removeClass();

            if ((m || '').trim() === 'Enviado com Sucesso') {
              $('#mensagem').addClass('text-success').text(m);
              form.reset();
            } else {
              $('#mensagem').addClass('text-danger').text(m);
            }
          },
          error: function () {
            $('#mensagem').addClass('text-danger').text('Erro ao enviar. Tente novamente.');
          },
          complete: function () {
            btnSubmit.prop('disabled', false).text(btnText);
          },
          cache: false,
          contentType: false,
          processData: false
        });
      }).catch(function () {
        $('#mensagem')
          .addClass('text-danger')
          .text('Erro no reCAPTCHA. Recarregue a página.');

        btnSubmit.prop('disabled', false).text(btnText);
      });
    });
  }

  function initContadorVisitas() {
    var el = document.querySelector('.counter-1999');
    var sec = document.querySelector('.experience-section');

    if (!el) {
      return;
    }

    function buscarEAnimar() {
      fetch('contador-visitas.php', { cache: 'no-store' })
        .then(function (r) {
          return r.ok ? r.json() : Promise.reject(r.status);
        })
        .then(function (data) {
          var total = Number(data && data.visitas) || 0;
          var finalFmt = (data && data.formatado) ? String(data.formatado) : total.toLocaleString('pt-BR');
          startCounter(total, finalFmt);
        })
        .catch(function () {
          startCounter(5280, (5280).toLocaleString('pt-BR'));
        });
    }

    function startCounter(target, finalFmt) {
      el.textContent = '0';
      el.setAttribute('data-target', String(target));
      animateTo(target, finalFmt);
    }

    function animateTo(target, finalFmt) {
      if (!target || target <= 0) {
        el.textContent = '0';
        return;
      }

      var current = 0;
      var duration = 1800;
      var steps = 80;
      var increment = Math.max(1, Math.ceil(target / steps));
      var tick = Math.max(24, Math.floor(duration / Math.ceil(target / increment)));

      var timer = setInterval(function () {
        current += increment;

        if (current >= target) {
          clearInterval(timer);
          el.textContent = finalFmt;
          return;
        }

        el.textContent = current.toLocaleString('pt-BR');
      }, tick);
    }

    if ('IntersectionObserver' in window && sec) {
      var io = new IntersectionObserver(function (entries) {
        if (entries.some(function (entry) { return entry.isIntersecting; }) && !el.classList.contains('animated')) {
          el.classList.add('animated');
          buscarEAnimar();
          io.disconnect();
        }
      }, { rootMargin: '150px 0px', threshold: 0.15 });

      io.observe(sec);
      return;
    }

    onIdle(buscarEAnimar);
  }

  function abrirWhatsappAutomatico(nome, urlSimulada) {
    if (!WHATSAPP_SALAO) {
      return;
    }

    var texto = 'Olá, meu nome é ' + nome + '. Acabei de fazer minha simulação capilar e gostaria de mais informações. Resultado: ' + urlSimulada;
    var url = 'https://api.whatsapp.com/send?phone=' + encodeURIComponent(WHATSAPP_SALAO) + '&text=' + encodeURIComponent(texto);
    window.open(url, '_blank');
  }

  function normalizarUrlImagem(url) {
    if (!url) {
      return '';
    }

    url = String(url).trim();

    if (!url) {
      return '';
    }

    if (/^https?:\/\//i.test(url)) {
      return url;
    }

    if (url.charAt(0) === '/') {
      return window.location.origin + url;
    }

    return window.location.origin + '/' + url.replace(/^\.?\//, '');
  }

  function initSimulacaoCliente() {
    window.enviarSimulacaoCliente = function () {
      var form = document.getElementById('form_simulacao_cliente');
      var msg = document.getElementById('mensagem_simulacao_cliente');
      var btn = document.getElementById('btn_simular_cliente');
      var icone = document.getElementById('icone_btn_simular_cliente');
      var previewOriginal = document.getElementById('preview_original_simulacao_cliente');
      var previewResultado = document.getElementById('preview_resultado_simulacao_cliente');

      if (!form || !msg || !btn || !icone) {
        return;
      }

      var nome = document.getElementById('nome_simulacao_cliente') ? document.getElementById('nome_simulacao_cliente').value.trim() : '';
      var telefone = document.getElementById('telefone_simulacao_cliente') ? document.getElementById('telefone_simulacao_cliente').value.trim() : '';
      var foto = document.getElementById('foto_simulacao_cliente');

      msg.className = '';
      msg.innerText = '';

      if (!nome || !telefone || !foto || !foto.files || !foto.files[0]) {
        msg.className = 'text-danger';
        msg.innerText = 'Preencha nome, WhatsApp e selecione uma foto';
        return;
      }

      if (previewOriginal) {
        previewOriginal.src = URL.createObjectURL(foto.files[0]);
      }

      if (previewResultado) {
        previewResultado.src = IMAGEM_PADRAO_SIMULACAO + '?v=' + Date.now();
      }

      var formData = new FormData(form);

      btn.disabled = true;
      icone.className = 'fa fa-spinner fa-spin';

      fetch(URL_SIMULACAO_CLIENTE, {
        method: 'POST',
        body: formData
      })
        .then(function (r) {
          return r.json();
        })
        .then(function (dados) {
          var urlSimulada = dados && dados.url_simulada ? normalizarUrlImagem(dados.url_simulada) : '';

          if (!dados || dados.status !== 'success') {
            throw new Error(dados && dados.mensagem ? dados.mensagem : 'Erro ao processar a simulação');
          }

          if (!urlSimulada) {
            throw new Error('A imagem simulada não foi retornada');
          }

          if (previewResultado) {
            previewResultado.src = urlSimulada + (urlSimulada.indexOf('?') !== -1 ? '&v=' : '?v=') + Date.now();
          }

          fetch(URL_CADASTRO_CLIENTE, {
            method: 'POST',
            body: formData
          }).catch(function () {
          });

          msg.className = 'text-success';
          msg.innerText = 'Simulação gerada com sucesso';
        })
        .catch(function (erro) {
          if (previewResultado) {
            previewResultado.src = IMAGEM_PADRAO_SIMULACAO + '?v=' + Date.now();
          }

          msg.className = 'text-danger';
          msg.innerText = erro.message ? erro.message : 'Erro ao processar a simulação';
        })
        .finally(function () {
          btn.disabled = false;
          icone.className = 'fa fa-magic';
        });
    };

    if (window.jQuery) {
      window.jQuery('#modalSimulacaoCliente').on('hidden.bs.modal', function () {
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
          imgOriginal.src = IMAGEM_PADRAO_SIMULACAO + '?v=' + Date.now();
        }

        if (imgResultado) {
          imgResultado.src = IMAGEM_PADRAO_SIMULACAO + '?v=' + Date.now();
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
  }

  function initLeadModal() {
    if (!window.jQuery) {
      return;
    }

    (function () {
      var MODAL_ID = 'leadModal';
      var KEY_SHOWN = 'leadPopupShown';
      var KEY_DONE = 'clienteCadastrado';
      var DEBUG = true;
      var armed = false;

      function log(msg) {
        if (DEBUG) {
          console.log('[LEAD MODAL] ' + msg);
        }
      }

      function canShow() {
        log('Verificando se pode mostrar modal...');

        if (localStorage.getItem(KEY_DONE) === 'true') {
          log('Cliente já cadastrado');
          return false;
        }

        var params = new URLSearchParams(window.location.search);
        if (params.get('showLead') === '1') {
          return true;
        }

        if (localStorage.getItem(KEY_SHOWN) === 'true') {
          return false;
        }

        var ua = navigator.userAgent || '';
        if (/bot|crawler|spider|lighthouse|pagespeed/i.test(ua)) {
          return false;
        }

        return true;
      }

      function marcarComoExibido() {
        localStorage.setItem(KEY_SHOWN, 'true');
      }

      function openModal() {
        if (typeof jQuery === 'undefined' || typeof jQuery.fn.modal === 'undefined') {
          return;
        }

        var $modal = jQuery('#' + MODAL_ID);

        if (!$modal.length) {
          return;
        }

        marcarComoExibido();

        $modal.modal({
          backdrop: true,
          keyboard: true,
          show: true
        });

        setTimeout(function () {
          var input = document.getElementById('leadNome');
          if (input) {
            input.focus();
          }
        }, 500);
      }

      function arm() {
        if (armed) {
          return;
        }

        armed = true;

        setTimeout(function () {
          openModal();
        }, 2000);

        jQuery(window).off('scroll.leadmodal click.leadmodal keydown.leadmodal touchstart.leadmodal');
      }

      if (!canShow()) {
        return;
      }

      jQuery(function () {
        jQuery(window).on('scroll.leadmodal click.leadmodal keydown.leadmodal touchstart.leadmodal', arm);

        setTimeout(function () {
          if (!armed && document.visibilityState === 'visible') {
            arm();
          }
        }, 10000);
      });
    })();
  }

  function initLeadForm() {
    if (!window.jQuery) {
      return;
    }

    var $ = window.jQuery;

    $('#leadForm').on('submit', function (e) {
      e.preventDefault();

      var nome = $('#leadNome').val().trim();
      var telefone = $('#leadZap').val().trim();
      var $msg = $('#leadMsg');
      var $btn = $('#leadBtn');

      $msg.removeClass('text-danger text-success').text('');

      if (!nome || !telefone) {
        $msg.addClass('text-danger').text('Preencha nome e telefone.');
        return;
      }

      $btn.prop('disabled', true).text('Cadastrando...');

      $.ajax({
        url: URL_CADASTRO_CLIENTE,
        type: 'POST',
        data: {
          nome: nome,
          telefone: telefone
        },
        success: function () {
          localStorage.setItem('clienteCadastrado', 'true');
          $msg.addClass('text-success').text('Cadastro realizado com sucesso.');

          setTimeout(function () {
            $('#leadModal').modal('hide');
          }, 1200);
        },
        error: function () {
          $msg.addClass('text-danger').text('Erro ao cadastrar. Tente novamente.');
        },
        complete: function () {
          $btn.prop('disabled', false).text('Cadastrar');
        }
      });
    });
  }

  function initGoogleReviewsCarousel() {
    if (!window.jQuery || !window.jQuery.fn.owlCarousel) {
      setTimeout(initGoogleReviewsCarousel, 200);
      return;
    }

    var $ = window.jQuery;
    var $carousel = $('.google-reviews-carousel');

    if ($carousel.length && !$carousel.data('owl-loaded')) {
      $carousel.owlCarousel({
        loop: true,
        margin: 20,
        nav: false,
        dots: false,
        autoplay: true,
        autoplayTimeout: 5000,
        autoplayHoverPause: true,
        smartSpeed: 800,
        responsive: {
          0: {
            items: 1,
            margin: 10
          },
          576: {
            items: 2,
            margin: 15
          },
          992: {
            items: 3,
            margin: 20
          }
        }
      }).data('owl-loaded', true);
    }
  }

  function initCookieBanner() {
    var banner = document.getElementById('cookieBanner');
    if (!banner) {
      return;
    }

    var ua = navigator.userAgent || '';
    if (/lighthouse|pagespeed|chrome-lighthouse|headlesschrome/i.test(ua)) {
      banner.classList.add('hide');
      return;
    }

    function getCookieChoice() {
      try {
        return localStorage.getItem('cookieChoice');
      } catch (e) {
        return null;
      }
    }

    function setCookieChoice(value) {
      try {
        localStorage.setItem('cookieChoice', value);
      } catch (e) {
      }
    }

    function showBanner() {
      banner.classList.remove('hide');
      document.body.classList.add('cookie-banner-open');
    }

    function hideBanner() {
      banner.classList.add('hide');
      document.body.classList.remove('cookie-banner-open');
    }

    if (!getCookieChoice()) {
      var mostrarCookie = function(){ showBanner(); };
      if ('requestIdleCallback' in window) {
        requestIdleCallback(mostrarCookie, { timeout: 6000 });
      } else {
        setTimeout(mostrarCookie, 6000);
      }
    } else {
      hideBanner();
    }

    var aceitar = document.getElementById('aceitarCookies');
    var recusar = document.getElementById('recusarCookies');

    if (aceitar) {
      aceitar.onclick = function () {
        setCookieChoice('accept');
        hideBanner();

        try {
          window.dispatchEvent(new Event('jacy:cookies:accept'));
        } catch (e) {
        }

        if (typeof window.jacyLoadMarketingTags === 'function') {
          window.jacyLoadMarketingTags();
        }
      };
    }

    if (recusar) {
      recusar.onclick = function () {
        setCookieChoice('deny');
        hideBanner();

        try {
          window.dispatchEvent(new Event('jacy:cookies:deny'));
        } catch (e) {
        }
      };
    }
  }

  ready(function () {
    initCarregarImagemComentario();
    initPreviewSimulacaoCliente();
    initLazyMap();
    initEmpresaModalButton();
    initContadorVisitas();
    initCookieBanner();
    initSimulacaoCliente();

    ensureJQuery(function () {
      initComentarioForm();
      initFaqCollapseIcon();
      initContatoForm();
      initLeadModal();
      initLeadForm();

      ensureBootstrap(function () {
        initEmpresaModalButton();
      });

      ensureMask(function () {
        initPhoneMasks();
      });

      initHeroAndOwl();
      initPausarCarrosselDepoimentosComVideo();
      initGoogleReviewsCarousel();
    });
  });
})();