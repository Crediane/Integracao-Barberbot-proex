// Jacy Cabeleireiro - carrosséis estáveis para reduzir CLS e manter serviços passando automaticamente.
// Mantém as mesmas classes do sistema, evita inicialização duplicada e remove polling contínuo.
(function (w, d) {
  'use strict';

  function iniciarCarrossel($carousel, options) {
    if (!$carousel || !$carousel.length || !$carousel.owlCarousel) {
      return;
    }

    if ($carousel.data('owl-loaded') || $carousel.hasClass('owl-loaded')) {
      try {
        $carousel.trigger('play.owl.autoplay', [options.autoplayTimeout || 3500]);
      } catch (e) {
      }
      return;
    }

    $carousel.owlCarousel(options).data('owl-loaded', 1);
  }

  function pausarNoMouse($carousel, tempo) {
    if (!$carousel || !$carousel.length || $carousel.data('jacy-hover-pause') === 1) {
      return;
    }

    $carousel.data('jacy-hover-pause', 1);

    $carousel.on('mouseenter', function () {
      try {
        $carousel.trigger('stop.owl.autoplay');
      } catch (e) {
      }
    });

    $carousel.on('mouseleave', function () {
      try {
        $carousel.trigger('play.owl.autoplay', [tempo || 3500]);
      } catch (e) {
      }
    });
  }

  function init() {
    var $ = w.jQuery;

    if (!$ || !$.fn || !$.fn.owlCarousel) {
      return false;
    }

    var $prod = $('.product_owl-carousel');
    var $cli = $('.client_owl-carousel');
    var $google = $('.google-reviews-carousel');

    iniciarCarrossel($prod, {
      autoplay: true,
      autoplayTimeout: 3500,
      autoplayHoverPause: true,
      loop: true,
      margin: 18,
      autoHeight: false,
      nav: false,
      dots: true,
      smartSpeed: 650,
      responsive: {
        0: { items: 1, margin: 12 },
        576: { items: 2, margin: 14 },
        992: { items: 3, margin: 18 }
      }
    });
    pausarNoMouse($prod, 3500);

    iniciarCarrossel($cli, {
      loop: true,
      margin: 20,
      nav: true,
      dots: true,
      autoplay: true,
      autoplayTimeout: 5000,
      autoplayHoverPause: true,
      autoHeight: false,
      smartSpeed: 700,
      navText: [
        '<i class="fa fa-long-arrow-left" aria-hidden="true"></i>',
        '<i class="fa fa-long-arrow-right" aria-hidden="true"></i>'
      ],
      responsive: {
        0: { items: 1 },
        1000: { items: 2 }
      }
    });
    pausarNoMouse($cli, 5000);

    iniciarCarrossel($google, {
      loop: true,
      margin: 20,
      nav: false,
      dots: false,
      autoplay: true,
      autoplayTimeout: 5000,
      autoplayHoverPause: true,
      autoHeight: false,
      smartSpeed: 800,
      responsive: {
        0: { items: 1, margin: 10 },
        576: { items: 2, margin: 15 },
        992: { items: 3, margin: 20 }
      }
    });
    pausarNoMouse($google, 5000);

    return true;
  }

  w.jacyInitOwlCarousels = function () {
    return init();
  };

  function tentarUmaVez() {
    init();
  }

  if (d.readyState === 'loading') {
    d.addEventListener('DOMContentLoaded', tentarUmaVez, { once: true });
  } else {
    tentarUmaVez();
  }

  w.addEventListener('load', tentarUmaVez, { once: true });
  d.addEventListener('jacy:owl-ready', tentarUmaVez);
  d.addEventListener('jacy:owl:ready', tentarUmaVez);
})(window, document);
