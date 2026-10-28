// ARQUIVO: public_html/marketplace/menu.js
(function () {
  function qs(sel) { return document.querySelector(sel); }

  var nav = document.getElementById('navLinks') || qs('.nav-links');
  var backdrop = document.getElementById('mobileBackdrop') || qs('.mobile-backdrop');
  var btn = qs('.mobile-menu-btn');

  if (!nav) return;

  function setOpen(open) {
    if (open) {
      nav.classList.add('open');
      if (backdrop) backdrop.classList.add('show');
      document.documentElement.style.overflow = 'hidden';
      document.body.style.overflow = 'hidden';
      if (btn) btn.setAttribute('aria-expanded', 'true');
    } else {
      nav.classList.remove('open');
      if (backdrop) backdrop.classList.remove('show');
      document.documentElement.style.overflow = '';
      document.body.style.overflow = '';
      if (btn) btn.setAttribute('aria-expanded', 'false');
    }
  }

  // compatível com seu onclick="toggleMenu()"
  window.toggleMenu = function (force) {
    var isOpen = nav.classList.contains('open');
    if (typeof force === 'boolean') setOpen(force);
    else setOpen(!isOpen);
  };

  // fecha ao clicar em link
  nav.querySelectorAll('a').forEach(function (a) {
    a.addEventListener('click', function () {
      setOpen(false);
    });
  });

  // fecha com ESC
  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape') setOpen(false);
  });

  // fecha ao aumentar para desktop
  window.addEventListener('resize', function () {
    if (window.innerWidth > 980) setOpen(false);
  });

  // fecha ao clicar no backdrop
  if (backdrop) {
    backdrop.addEventListener('click', function () {
      setOpen(false);
    });
  }
})();
