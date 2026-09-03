'use strict';
/* ============================================================
   Configuração dinâmica fornecida pelo PHP por atributos data-*
   ============================================================ */
(function(){
  var body = document.body;
  var dados = body ? body.dataset : {};

  window.RECAPTCHA_SITE_KEY = dados.recaptchaSiteKey || '';
  window.JACY_PROTESE_CONFIG = {
    whatsapp: dados.whatsapp || '',
    agenda: dados.agenda || ''
  };
})();

/* ===== Bloco externo migrado do protese.php (6) ===== */
(function(){
  var recaptchaJaCarregado = false;

  function carregarRecaptcha(){
    if (recaptchaJaCarregado || !window.RECAPTCHA_SITE_KEY) {
      return;
    }

    recaptchaJaCarregado = true;

    var s = document.createElement('script');
    s.src = 'https://www.google.com/recaptcha/api.js?render=' + window.RECAPTCHA_SITE_KEY;
    s.async = true;
    s.defer = true;
    document.head.appendChild(s);
  }

  window.addEventListener('load', function(){
    setTimeout(carregarRecaptcha, 2500);
  }, { once:true });

  document.addEventListener('focusin', function(e){
    if (e.target.closest('#form_cadastro') || e.target.closest('#form_simulacao_cliente')) {
      carregarRecaptcha();
    }
  });

  document.addEventListener('pointerdown', function(e){
    if (e.target.closest('#form_cadastro') || e.target.closest('#form_simulacao_cliente')) {
      carregarRecaptcha();
    }
  }, { passive:true });
})();

/* ===== Bloco externo migrado do protese.php (7) ===== */
(function(){
  function getModalBySelector(sel){
    if (!sel || sel.charAt(0) !== '#') return null;

    try {
      return document.querySelector(sel);
    } catch (e) {
      return null;
    }
  }

  function getSelectorFromTrigger(el){
    if (!el) return '';

    return el.getAttribute('data-target')
      || el.getAttribute('data-bs-target')
      || el.getAttribute('href')
      || '';
  }

  function criarBackdrop(modal){
    removerBackdrop(modal);

    var backdrop = document.createElement('div');
    backdrop.className = 'modal-backdrop fade show jacy-modal-backdrop';
    backdrop.setAttribute('data-modal-fallback', modal.id || '');
    document.body.appendChild(backdrop);
  }

  function removerBackdrop(modal){
    var id = modal && modal.id ? modal.id : '';
    var seletor = id
      ? '.jacy-modal-backdrop[data-modal-fallback="' + id + '"]'
      : '.jacy-modal-backdrop';

    document.querySelectorAll(seletor).forEach(function(item){
      if (item && item.parentNode) item.parentNode.removeChild(item);
    });
  }

  function abrirModalFallback(modal){
    if (!modal) return;

    modal.style.display = 'block';
    modal.removeAttribute('aria-hidden');
    modal.setAttribute('aria-modal', 'true');
    modal.classList.add('show');
    document.body.classList.add('modal-open');
    document.body.style.overflow = 'hidden';
    criarBackdrop(modal);

    setTimeout(function(){
      var primeiroCampo = modal.querySelector('input, select, textarea, button, a[href]');
      if (primeiroCampo && typeof primeiroCampo.focus === 'function') primeiroCampo.focus();
    }, 80);

    modal.dispatchEvent(new Event('shown.bs.modal', { bubbles:true }));
  }

  function fecharModalFallback(modal){
    if (!modal) return;

    modal.classList.remove('show');
    modal.style.display = 'none';
    modal.setAttribute('aria-hidden', 'true');
    modal.removeAttribute('aria-modal');
    removerBackdrop(modal);

    if (!document.querySelector('.modal.show')) {
      document.body.classList.remove('modal-open');
      document.body.style.overflow = '';
    }

    modal.dispatchEvent(new Event('hidden.bs.modal', { bubbles:true }));
  }

  function abrirModalSistema(sel){
    var modal = getModalBySelector(sel);
    if (!modal) return false;

    if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.modal === 'function') {
      window.jQuery(modal).modal('show');
      return true;
    }

    if (window.bootstrap && window.bootstrap.Modal) {
      var instancia = window.bootstrap.Modal.getOrCreateInstance(modal);
      instancia.show();
      return true;
    }

    abrirModalFallback(modal);
    return true;
  }

  function fecharModalSistema(modal){
    if (!modal) return false;

    if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.modal === 'function') {
      window.jQuery(modal).modal('hide');
      return true;
    }

    if (window.bootstrap && window.bootstrap.Modal) {
      var instancia = window.bootstrap.Modal.getOrCreateInstance(modal);
      instancia.hide();
      return true;
    }

    fecharModalFallback(modal);
    return true;
  }

  window.abrirModalSistema = abrirModalSistema;
  window.fecharModalSistema = fecharModalSistema;

  document.addEventListener('click', function(ev){
    var abrir = ev.target.closest('[data-toggle="modal"], [data-bs-toggle="modal"]');
    if (!abrir) return;

    var sel = getSelectorFromTrigger(abrir);
    if (!getModalBySelector(sel)) return;

    ev.preventDefault();
    abrirModalSistema(sel);

    if (history.replaceState && sel === '#modalSimulacaoCliente') {
      history.replaceState(null, '', location.pathname + location.search);
    }
  }, false);

  document.addEventListener('click', function(ev){
    var fechar = ev.target.closest('[data-dismiss="modal"], [data-bs-dismiss="modal"], .modal .close');
    if (fechar) {
      var modalFechar = fechar.closest('.modal');
      if (modalFechar) {
        ev.preventDefault();
        fecharModalSistema(modalFechar);
      }
      return;
    }

    if (ev.target && ev.target.classList && ev.target.classList.contains('modal')) {
      fecharModalSistema(ev.target);
    }
  }, false);

  document.addEventListener('keydown', function(ev){
    if (ev.key !== 'Escape') return;

    var modais = document.querySelectorAll('.modal.show');
    if (!modais.length) return;

    fecharModalSistema(modais[modais.length - 1]);
  });

  document.addEventListener('DOMContentLoaded', function(){
    var parametros = new URLSearchParams(window.location.search);

    if (parametros.get('abrir_simulador') === '1') {
      setTimeout(function(){
        abrirModalSistema('#modalSimulacaoCliente');
      }, 250);
    }
  });
})();

const LINK_WHATSAPP = (window.JACY_PROTESE_CONFIG && window.JACY_PROTESE_CONFIG.whatsapp) || '';
const LINK_APP = (window.JACY_PROTESE_CONFIG && window.JACY_PROTESE_CONFIG.agenda) || '';

var btnW = document.getElementById('ctaWhats');
if (btnW) btnW.href = LINK_WHATSAPP;

var btnA = document.getElementById('ctaApp');
if (btnA) btnA.href = LINK_APP;

const track = document.getElementById('heroTrack');
const slidesV = track ? Array.from(track.querySelectorAll('.hero-slide')) : [];
const dotsV = Array.from(document.querySelectorAll('#heroDots button'));

let heroIndex = 0;

function heroGo(i){
  if (!track) return;
  heroIndex = (i + slidesV.length) % slidesV.length;
  track.style.transform = 'translateX(' + (heroIndex * -100) + '%)';
  dotsV.forEach((d, idx) => d.style.background = idx === heroIndex ? '#ffffffaa' : '#ffffff55');
}

var prevH = document.getElementById('heroPrev');
if (prevH) prevH.onclick = () => heroGo(heroIndex - 1);

var nextH = document.getElementById('heroNext');
if (nextH) nextH.onclick = () => heroGo(heroIndex + 1);

dotsV.forEach(d => d.onclick = () => heroGo(parseInt(d.dataset.i, 10)));
let heroTimer = null;

function startHeroTimer(){
  stopHeroTimer();
  if (document.hidden) return;
  heroTimer = setInterval(() => heroGo(heroIndex + 1), 4000);
}

function stopHeroTimer(){
  clearInterval(heroTimer);
}

let __ytApiRequested = false;

function ensureYTApiLoaded(cb){
  if (window.YT && window.YT.Player) {
    cb();
    return;
  }

  if (!__ytApiRequested) {
    __ytApiRequested = true;
    var s = document.createElement('script');
    s.src = 'https://www.youtube.com/iframe_api';
    s.async = true;
    document.head.appendChild(s);
  }

  (function wait(){
    if (window.YT && window.YT.Player) cb();
    else setTimeout(wait, 80);
  })();
}

let ytPlayer;
const ytModal = document.getElementById('ytModal');
const ytClose = document.getElementById('ytClose');
function onYouTubeIframeAPIReady(){}

function openVideo(videoId){
  if (!ytModal) return;

  ytModal.style.display = 'flex';
  document.body.style.overflow = 'hidden';
  stopHeroTimer();

  ensureYTApiLoaded(function(){
    if (!ytPlayer) {
      ytPlayer = new YT.Player('ytPlayer', {
        videoId: videoId,
        playerVars: {autoplay:1, rel:0, controls:1, modestbranding:1, playsinline:1},
        events: {
          onReady: (e) => {
            const ifr = e.target.getIframe();
            ifr.style.position = 'absolute';
            ifr.style.inset = '0';
            ifr.style.width = '100%';
            ifr.style.height = '100%';
          },
          onStateChange: (e) => {
            if (e.data === YT.PlayerState.ENDED) closeVideo();
          }
        }
      });
    } else {
      ytPlayer.loadVideoById(videoId);
    }
  });
}

function closeVideo(){
  if (!ytModal) return;
  ytModal.style.display = 'none';
  document.body.style.overflow = '';
  if (ytPlayer && ytPlayer.stopVideo) ytPlayer.stopVideo();
  startHeroTimer();
}

if (ytClose) ytClose.onclick = closeVideo;
if (ytModal) ytModal.addEventListener('click', (ev) => { if (ev.target === ytModal) closeVideo(); });
document.addEventListener('keydown', (e) => { if (e.key === 'Escape') closeVideo(); });
document.addEventListener('visibilitychange', () => {
  if (document.hidden) stopHeroTimer();
  else if (!ytModal || ytModal.style.display !== 'flex') startHeroTimer();
}, { passive:true });

startHeroTimer();

slidesV.forEach(slide => {
  const id = slide.dataset.video;
  var p = slide.querySelector('.play-btn');
  if (p) p.addEventListener('click', () => openVideo(id));
  slide.addEventListener('dblclick', () => openVideo(id));
});

(function(){
  const root = document.getElementById('hero');
  if (!root) return;

  const slides = root.querySelector('#slides');
  const items = Array.from(root.querySelectorAll('.slide'));
  const dotsEl = root.querySelector('#dots');
  const btnPrev = root.querySelector('#prev');
  const btnNext = root.querySelector('#next');

  let idx = 0, timer = null, hover = false;
  const duration = 5000;
  const reduced = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;

  items.forEach((_, i) => {
    const b = document.createElement('button');
    b.type = 'button';
    b.setAttribute('aria-label', 'Ir para o slide ' + (i + 1));
    b.addEventListener('click', () => go(i), { passive:true });
    dotsEl.appendChild(b);
  });

  function mark(){ dotsEl.querySelectorAll('button').forEach((b, i) => b.classList.toggle('active', i === idx)); }

  function syncVideos(){
    items.forEach((item, itemIndex) => {
      const video = item.querySelector('video[data-hero-video]');
      if (!video) return;

      if (itemIndex === idx) {
        const playNow = function(){
          const promise = video.play();
          if (promise && typeof promise.catch === 'function') {
            promise.catch(function(){});
          }
        };

        if (video.readyState >= 2) playNow();
        else {
          video.addEventListener('loadeddata', playNow, { once:true });
          video.load();
        }
      } else {
        video.pause();
        try { video.currentTime = 0; } catch(e) {}
      }
    });
  }

  function go(i){
    idx = (i + items.length) % items.length;
    slides.style.transform = 'translateX(' + (-100 * idx) + '%)';
    mark();
    syncVideos();
  }
  function next(){ go(idx + 1); }
  function prev(){ go(idx - 1); }
  function loop(){
    clearTimeout(timer);
    if (hover || reduced) return;
    timer = setTimeout(() => { next(); loop(); }, duration);
  }

  if (btnPrev) btnPrev.addEventListener('click', prev, { passive:true });
  if (btnNext) btnNext.addEventListener('click', next, { passive:true });

  root.addEventListener('pointerenter', () => { hover = true; clearTimeout(timer); }, { passive:true });
  root.addEventListener('pointerleave', () => { hover = false; loop(); }, { passive:true });

  let sx = 0, dx = 0;
  root.addEventListener('touchstart', e => { sx = e.touches[0].clientX; }, { passive:true });
  root.addEventListener('touchmove', e => { dx = e.touches[0].clientX - sx; }, { passive:true });
  root.addEventListener('touchend', () => { if (Math.abs(dx) > 40) { dx < 0 ? next() : prev(); } dx = 0; }, { passive:true });

  let visibleOnScreen = true;

  function stopLoop(){
    clearTimeout(timer);
  }

  function startLoop(){
    if (!visibleOnScreen || document.hidden) return;
    loop();
  }

  if ('IntersectionObserver' in window) {
    const obs = new IntersectionObserver(function(entries){
      visibleOnScreen = entries.some(function(entry){ return entry.isIntersecting; });
      if (visibleOnScreen) {
        syncVideos();
        startLoop();
      } else {
        stopLoop();
        items.forEach(function(item){
          const video = item.querySelector('video[data-hero-video]');
          if (video) video.pause();
        });
      }
    }, { threshold: 0.2 });

    obs.observe(root);
  }

  document.addEventListener('visibilitychange', function(){
    if (document.hidden) {
      stopLoop();
      items.forEach(function(item){
        const video = item.querySelector('video[data-hero-video]');
        if (video) video.pause();
      });
      return;
    }
    syncVideos();
    startLoop();
  }, { passive:true });

  const boot = () => { go(0); startLoop(); };
  if ('requestIdleCallback' in window) requestIdleCallback(boot, { timeout: 1200 });
  else window.addEventListener('load', boot, { once:true });
})();

(function(){
  const wrap = document.getElementById('galeriaWrap');
  if (!wrap) return;

  const track = document.getElementById('galTrack');
  const items = Array.from(track.querySelectorAll('.galItem'));
  const prev = document.getElementById('galPrev');
  const next = document.getElementById('galNext');

  let idx = 0;

  function perView(){
    const w = window.innerWidth;
    if (w < 520) return 1;
    if (w < 768) return 2;
    if (w < 1024) return 3;
    return 4;
  }

  function applyLayout(){
    const pv = perView();
    const w = (100 / pv) + '%';
    items.forEach(el => { el.style.minWidth = w; });
    clamp();
    go(idx, true);
  }

  function clamp(){
    const pv = perView();
    const max = Math.max(0, items.length - pv);
    if (idx > max) idx = max;
    if (idx < 0) idx = 0;
    if (prev) prev.disabled = (idx === 0);
    if (next) next.disabled = (idx === max);
  }

  function go(i, noAnim){
    idx = i;
    clamp();
    if (noAnim) track.style.transition = 'none';
    const pv = perView();
    const shift = (100 / pv) * idx;
    track.style.transform = 'translateX(' + (-shift) + '%)';
    if (noAnim) requestAnimationFrame(() => track.style.transition = '.4s ease');
  }

  if (prev) prev.addEventListener('click', () => go(idx - 1));
  if (next) next.addEventListener('click', () => go(idx + 1));

  let sx = 0, dx = 0;
  track.addEventListener('touchstart', e => { sx = e.touches[0].clientX; }, { passive:true });
  track.addEventListener('touchmove', e => { dx = e.touches[0].clientX - sx; }, { passive:true });
  track.addEventListener('touchend', () => { if (Math.abs(dx) > 40) { dx < 0 ? go(idx + 1) : go(idx - 1); } dx = 0; }, { passive:true });

  window.addEventListener('resize', applyLayout);
  applyLayout();

  let autoTimer = null;

  function startAuto(){
    clearInterval(autoTimer);
    if (document.hidden) return;
    autoTimer = setInterval(() => {
      const pv = perView();
      const max = Math.max(0, items.length - pv);
      if (idx >= max) go(0);
      else go(idx + 1);
    }, 4000);
  }

  function stopAuto(){
    clearInterval(autoTimer);
  }

  if (wrap) {
    wrap.addEventListener('pointerenter', stopAuto, { passive:true });
    wrap.addEventListener('pointerleave', startAuto, { passive:true });
  }

  document.addEventListener('visibilitychange', function(){
    if (document.hidden) stopAuto();
    else startAuto();
  }, { passive:true });

  startAuto();
})();

(function(){
  var track = document.getElementById('depTrack');
  if (!track) return;

  var items = Array.from(track.querySelectorAll('.depItem'));
  var prev = document.getElementById('depPrev');
  var next = document.getElementById('depNext');
  var dotsEl = document.getElementById('depDots');
  var dots = [];
  var index = 0;
  var t = null;
  var videoEmUso = false;
  var retomadaTimer = null;

  function perView(){
    return (window.innerWidth >= 900) ? 2 : 1;
  }

  function temVideoAssistindo(){
    var videos = Array.from(track.querySelectorAll('video'));
    return videos.some(function(video){
      return video && !video.paused && !video.ended;
    });
  }

  function temVideoTelaCheia(){
    var fs = document.fullscreenElement || document.webkitFullscreenElement || document.mozFullScreenElement || document.msFullscreenElement;
    return !!(fs && track.contains(fs));
  }

  function podeRodarAutomatico(){
    return !document.hidden && !videoEmUso && !temVideoAssistindo() && !temVideoTelaCheia();
  }

  function stopDepoTimer(){
    if (t) {
      clearInterval(t);
      t = null;
    }
  }

  function startDepoTimer(){
    stopDepoTimer();
    if (!podeRodarAutomatico()) return;

    t = setInterval(function(){
      if (!podeRodarAutomatico()) {
        stopDepoTimer();
        return;
      }

      var pv = perView();
      var pages = Math.max(1, Math.ceil(items.length / pv));
      go((index + 1) % pages, false, false);
    }, 6000);
  }

  function pausarOutrosVideos(videoAtual){
    Array.from(track.querySelectorAll('video')).forEach(function(video){
      if (video !== videoAtual) {
        try { video.pause(); } catch(e) {}
      }
    });
  }

  function pausarVideosForaDaPagina(){
    var pv = perView();
    var inicio = index * pv;
    var fim = inicio + pv;

    items.forEach(function(item, pos){
      if (pos < inicio || pos >= fim) {
        Array.from(item.querySelectorAll('video')).forEach(function(video){
          try { video.pause(); } catch(e) {}
        });
      }
    });
  }

  function marcarVideoEmUso(){
    videoEmUso = true;
    if (retomadaTimer) clearTimeout(retomadaTimer);
    stopDepoTimer();
  }

  function liberarVideoSeParado(){
    if (retomadaTimer) clearTimeout(retomadaTimer);

    retomadaTimer = setTimeout(function(){
      if (!temVideoAssistindo() && !temVideoTelaCheia()) {
        videoEmUso = false;
        startDepoTimer();
      }
    }, 1200);
  }

  function layout(){
    var pv = perView();
    var w = (100 / pv) + '%';
    items.forEach(function(el){ el.style.minWidth = w; });

    if (dotsEl) dotsEl.innerHTML = '';
    dots = [];

    var pages = Math.max(1, Math.ceil(items.length / pv));
    for (var i = 0; i < pages; i++) {
      var b = document.createElement('button');
      b.type = 'button';
      b.style.cssText = 'width:10px;height:10px;border-radius:999px;border:0;background:#3a3c44;cursor:pointer;';
      (function(i){
        b.addEventListener('click', function(){
          videoEmUso = false;
          pausarOutrosVideos(null);
          go(i, false, true);
          startDepoTimer();
        });
      })(i);
      if (dotsEl) dotsEl.appendChild(b);
      dots.push(b);
    }

    clamp();
    go(index, true, false);
  }

  function clamp(){
    var pv = perView();
    var pages = Math.max(1, Math.ceil(items.length / pv));
    if (index < 0) index = 0;
    if (index > pages - 1) index = pages - 1;
    dots.forEach(function(d, i){ d.style.background = (i === index ? '#ffffff' : '#3a3c44'); });
    if (prev) prev.disabled = (index === 0);
    if (next) next.disabled = (index === pages - 1);
  }

  function go(i, noAnim, navegacaoManual){
    index = i;
    clamp();

    if (navegacaoManual) {
      videoEmUso = false;
      pausarOutrosVideos(null);
    }

    if (noAnim) track.style.transition = 'none';
    var shift = (100 * index);
    track.style.transform = 'translateX(' + (-shift) + '%)';
    if (noAnim) requestAnimationFrame(function(){ track.style.transition = '.45s ease'; });

    pausarVideosForaDaPagina();
  }

  if (prev) {
    prev.addEventListener('click', function(){
      videoEmUso = false;
      pausarOutrosVideos(null);
      go(index - 1, false, true);
      startDepoTimer();
    });
  }

  if (next) {
    next.addEventListener('click', function(){
      videoEmUso = false;
      pausarOutrosVideos(null);
      go(index + 1, false, true);
      startDepoTimer();
    });
  }

  var sx = 0, dx = 0;
  track.addEventListener('touchstart', function(e){ sx = e.touches[0].clientX; }, { passive:true });
  track.addEventListener('touchmove', function(e){ dx = e.touches[0].clientX - sx; }, { passive:true });
  track.addEventListener('touchend', function(){
    if (Math.abs(dx) > 40) {
      videoEmUso = false;
      pausarOutrosVideos(null);
      dx < 0 ? go(index + 1, false, true) : go(index - 1, false, true);
      startDepoTimer();
    }
    dx = 0;
  }, { passive:true });

  Array.from(track.querySelectorAll('video')).forEach(function(video){
    ['pointerdown','mousedown','touchstart','click','focus','seeking','volumechange'].forEach(function(evt){
      video.addEventListener(evt, marcarVideoEmUso, { passive:true });
    });

    video.addEventListener('play', function(){
      marcarVideoEmUso();
      pausarOutrosVideos(video);
    });

    video.addEventListener('pause', liberarVideoSeParado);
    video.addEventListener('ended', liberarVideoSeParado);
  });

  ['fullscreenchange','webkitfullscreenchange','mozfullscreenchange','MSFullscreenChange'].forEach(function(evt){
    document.addEventListener(evt, function(){
      if (temVideoTelaCheia()) marcarVideoEmUso();
      else liberarVideoSeParado();
    });
  });

  window.addEventListener('resize', layout, { passive:true });

  document.addEventListener('visibilitychange', function(){
    if (document.hidden) stopDepoTimer();
    else startDepoTimer();
  }, { passive:true });

  layout();
  startDepoTimer();
})();
(function(){
  document.addEventListener('click', function(ev){
    document.querySelectorAll('header details[open]').forEach(function(d){
      if (!d.contains(ev.target)) d.removeAttribute('open');
    });
  }, { passive:true });
})();

function carregarImg(){
  var input = document.getElementById('foto');
  var img = document.getElementById('target');
  var msg = document.getElementById('mensagem-comentario');

  if (!input || !input.files || !input.files[0]) return;

  var file = input.files[0];
  var okTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];

  if (okTypes.indexOf(file.type) === -1) {
    msg.textContent = 'Arquivo inválido. Use JPG, PNG ou WebP.';
    msg.style.color = '#ff8080';
    input.value = '';
    img.src = 'sistema/painel/img/comentarios/sem-foto.jpg';
    return;
  }

  if (file.size > 5 * 1024 * 1024) {
    msg.textContent = 'Arquivo muito grande (máx. 5MB).';
    msg.style.color = '#ff8080';
    input.value = '';
    img.src = 'sistema/painel/img/comentarios/sem-foto.jpg';
    return;
  }

  var reader = new FileReader();
  reader.onload = function(e){
    img.src = e.target.result;
    msg.textContent = '';
  };
  reader.readAsDataURL(file);
}

/* ===== Bloco externo migrado do protese.php (8) ===== */
(function(){
  var ENDPOINT = '/cadastrar.php';
  var tel = document.getElementById('telefone_rodape');

  if (tel) {
    tel.addEventListener('input', function(e){
      var d = e.target.value.replace(/\D/g, '').slice(0, 11);
      var out = d;

      if (d.length > 2) out = '(' + d.slice(0, 2) + ') ' + d.slice(2);
      if (d.length >= 11) out = '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7);
      else if (d.length >= 7) out = '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6);

      e.target.value = out;
    });
  }

  var form = document.getElementById('form_cadastro');
  if (!form) return;

  form.addEventListener('submit', function(e){
    e.preventDefault();
    if (window.loadSwal) window.loadSwal();

    var action = (form.getAttribute('data-recaptcha-action') || 'cadastro').trim();
    var tokEl = form.querySelector('input[name="recaptcha_token"]');
    var actEl = form.querySelector('input[name="recaptcha_action"]');
    if (actEl) actEl.value = action;

    function prosseguirEnvio(){
      var nome = (form.querySelector('[name="nome"]').value || '').trim();
      var fone = (form.querySelector('[name="telefone"]').value || '').replace(/\D/g, '');

      if (!nome || fone.length < 10) {
        if (window.Swal) Swal.fire({icon:'error', title:'❌ Dados inválidos', text:'Preencha corretamente nome e telefone.', confirmButtonColor:'#DAA520'});
        else alert('Preencha corretamente nome e telefone.');
        return;
      }

      var xhr = new XMLHttpRequest();
      xhr.open('POST', ENDPOINT, true);
      xhr.setRequestHeader('X-Requested-With', 'XMLHttpRequest');

      xhr.onreadystatechange = function(){
        if (xhr.readyState !== 4) return;

        var ok = (xhr.status >= 200 && xhr.status < 300);
        var resp = (xhr.responseText || '').replace(/\s+/g, ' ').trim();

        if (!ok) {
          if (window.Swal) Swal.fire({icon:'error', title:'❌ Erro ao enviar', text:'HTTP ' + xhr.status, confirmButtonColor:'#DAA520'});
          else alert('Erro HTTP ' + xhr.status);
          return;
        }

        if (resp.indexOf('Cadastrado com Sucesso') === 0) {
          if (window.Swal) {
            Swal.fire({icon:'success', title:'✅ Sucesso!', text:'Cadastro realizado com sucesso.', confirmButtonColor:'#DAA520'})
              .then(function(){ window.location.href = '/sistema/acesso'; });
          } else {
            alert('Cadastro realizado com sucesso.');
            window.location.href = '/sistema/acesso';
          }
        } else if (resp.indexOf('Você já está Cadastrado') === 0) {
          if (window.Swal) {
            Swal.fire({icon:'warning', title:'⚠️ Já cadastrado', text:'Você já tem cadastro.', confirmButtonColor:'#DAA520'})
              .then(function(){ window.location.href = '/sistema/acesso'; });
          } else {
            alert('Você já tem cadastro.');
            window.location.href = '/sistema/acesso';
          }
        } else if (resp.indexOf('Preencha nome e telefone') === 0) {
          if (window.Swal) Swal.fire({icon:'error', title:'❌ Dados inválidos', text:'Preencha corretamente nome e telefone.', confirmButtonColor:'#DAA520'});
          else alert('Preencha corretamente nome e telefone.');
        } else {
          if (window.Swal) Swal.fire({icon:'error', title:'❌ Erro inesperado', html:'Retorno:<br><pre style="white-space:pre-wrap">' + resp + '</pre>', confirmButtonColor:'#DAA520'});
          else alert('Erro: ' + resp);
        }
      };

      xhr.onerror = function(){
        if (window.Swal) Swal.fire({icon:'error', title:'❌ Falha de rede', text:'Não foi possível enviar os dados.', confirmButtonColor:'#DAA520'});
        else alert('Falha de rede.');
      };

      xhr.send(new FormData(form));
    }

    if (window.grecaptcha && window.RECAPTCHA_SITE_KEY) {
      grecaptcha.ready(function(){
        grecaptcha.execute(window.RECAPTCHA_SITE_KEY, {action:action}).then(function(token){
          if (tokEl) tokEl.value = token;
          prosseguirEnvio();
        }).catch(function(){
          prosseguirEnvio();
        });
      });
    } else {
      prosseguirEnvio();
    }
  });
})();

/* ===== Bloco externo migrado do protese.php (10) ===== */
document.addEventListener('DOMContentLoaded', function () {
  const el = document.querySelector('.counter-1999');
  const sec = document.querySelector('.experience-section');
  if (!el) return;

  fetch('/contador-visitas.php', { cache: 'no-store' })
    .then(r => r.ok ? r.json() : Promise.reject(r.status))
    .then(data => {
      const total = Number(data && data.visitas) || 0;
      const finalFmt = (data && data.formatado) ? String(data.formatado) : total.toLocaleString('pt-BR');
      startCounter(total, finalFmt);
    })
    .catch(() => {
      startCounter(5280, (5280).toLocaleString('pt-BR'));
    });

  function startCounter(target, finalFmt) {
    el.textContent = '0';
    el.setAttribute('data-target', String(target));
    const run = () => animateTo(target, finalFmt);

    if ('IntersectionObserver' in window && sec) {
      const io = new IntersectionObserver((entries) => {
        if (entries.some(e => e.isIntersecting) && !el.classList.contains('animated')) {
          el.classList.add('animated');
          run();
          io.disconnect();
        }
      }, { threshold: 0.3 });
      io.observe(sec);
    } else {
      run();
    }
  }

  function animateTo(target, finalFmt) {
    if (!target || target <= 0) { el.textContent = '0'; return; }

    let current = 0;
    const duration = 2400;
    const steps = 100;
    const increment = Math.max(1, Math.ceil(target / steps));
    const tick = Math.max(16, Math.floor(duration / Math.ceil(target / increment)));

    const t = setInterval(() => {
      current += increment;
      if (current >= target) {
        clearInterval(t);
        el.textContent = finalFmt;
        return;
      }
      el.textContent = current.toLocaleString('pt-BR');
    }, tick);
  }
});

/* ===== Bloco externo migrado do protese.php (11) ===== */
(function(){
  var urlSimulacaoCliente = '/ajax/gerar_ia_cliente.php';
  var urlCadastroCliente = '/cadastrar.php';
  var imagemPadrao = '/sistema/painel/images/simulacoes/sem-foto.jpg';
  var pastaSimulacoes = '/sistema/painel/images/simulacoes/';
  var simulacaoEmAndamento = false;

  function normalizarUrlImagem(url){
    if (!url) return '';
    url = String(url).trim();
    if (!url) return '';

    if (/^https?:\/\//i.test(url)) return url;
    if (url.charAt(0) === '/') return window.location.origin + url;

    return window.location.origin + '/' + url.replace(/^\.?\//, '');
  }

  function montarUrlSimulacao(caminho){
    if (!caminho) return '';
    caminho = String(caminho).trim();
    if (!caminho) return '';

    if (/^https?:\/\//i.test(caminho)) return caminho;
    if (caminho.indexOf('sistema/painel/images/simulacoes/') !== -1) return normalizarUrlImagem(caminho);
    if (caminho.charAt(0) === '/') return normalizarUrlImagem(caminho);

    return normalizarUrlImagem(pastaSimulacoes + caminho.replace(/^\.?\//, ''));
  }

  function adicionarCacheNaImagem(url){
    if (!url) return '';
    return url + (url.indexOf('?') !== -1 ? '&v=' : '?v=') + Date.now();
  }

  function retornoFoiSucesso(status){
    if (!status) return false;
    status = String(status).toLowerCase().trim();
    return status === 'success' || status === 'sucesso';
  }

  function formatarTelefoneBR(valor){
    var d = String(valor || '').replace(/\D/g, '').slice(0, 11);
    if (!d) return '';

    if (d.length <= 2) return d;
    if (d.length <= 6) return '(' + d.slice(0, 2) + ') ' + d.slice(2);
    if (d.length <= 10) return '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6);

    return '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7, 11);
  }

  function aplicarMascaraTelefoneSimulador(){
    var campo = document.getElementById('telefone_simulacao_cliente');
    if (!campo) return;

    if (campo.getAttribute('data-mask-simulador') === '1') {
      campo.value = formatarTelefoneBR(campo.value);
      return;
    }

    var aplicar = function(){
      campo.value = formatarTelefoneBR(campo.value);
    };

    campo.addEventListener('input', aplicar);
    campo.addEventListener('blur', aplicar);
    campo.addEventListener('paste', function(){ setTimeout(aplicar, 0); });

    campo.setAttribute('data-mask-simulador', '1');
    aplicar();
  }

  function setEstadoBotao(carregando){
    var btn = document.getElementById('btn_simular_cliente');
    var icone = document.getElementById('icone_btn_simular_cliente');
    var texto = document.getElementById('texto_btn_simular_cliente');
    var msg = document.getElementById('mensagem_simulacao_cliente');

    if (!btn || !icone || !texto || !msg) return;

    if (carregando) {
      btn.disabled = true;
      btn.style.pointerEvents = 'none';
      icone.className = 'fa fa-spinner fa-spin';
      texto.innerText = 'Simulando...';
      msg.className = 'text-info';
      msg.innerText = 'Gerando simulação, aguarde...';
      return;
    }

    btn.disabled = false;
    btn.style.pointerEvents = '';
    icone.className = 'fa fa-magic';
    texto.innerText = 'Simular';
  }

  function resetarModalSimulacaoCliente(){
    var form = document.getElementById('form_simulacao_cliente');
    var imgOriginal = document.getElementById('preview_original_simulacao_cliente');
    var imgResultado = document.getElementById('preview_resultado_simulacao_cliente');
    var msg = document.getElementById('mensagem_simulacao_cliente');

    simulacaoEmAndamento = false;

    if (form) form.reset();
    if (imgOriginal) imgOriginal.src = adicionarCacheNaImagem(imagemPadrao);
    if (imgResultado) imgResultado.src = adicionarCacheNaImagem(imagemPadrao);

    if (msg) {
      msg.innerText = '';
      msg.className = '';
    }

    setEstadoBotao(false);
    aplicarMascaraTelefoneSimulador();
  }

  function inicializarSimuladorCliente(){
    aplicarMascaraTelefoneSimulador();

    var imgOriginal = document.getElementById('preview_original_simulacao_cliente');
    var imgResultado = document.getElementById('preview_resultado_simulacao_cliente');

    if (imgOriginal && (!imgOriginal.getAttribute('src') || imgOriginal.getAttribute('src') === '')) {
      imgOriginal.src = adicionarCacheNaImagem(imagemPadrao);
    }

    if (imgResultado && (!imgResultado.getAttribute('src') || imgResultado.getAttribute('src') === '')) {
      imgResultado.src = adicionarCacheNaImagem(imagemPadrao);
    }
  }

  window.carregarPreviewSimulacaoCliente = function(){
    var campo = document.getElementById('foto_simulacao_cliente');
    var imgOriginal = document.getElementById('preview_original_simulacao_cliente');
    var imgResultado = document.getElementById('preview_resultado_simulacao_cliente');

    if (!campo || !imgOriginal || !imgResultado) return;

    if (!campo.files || !campo.files[0]) {
      imgOriginal.src = adicionarCacheNaImagem(imagemPadrao);
      imgResultado.src = adicionarCacheNaImagem(imagemPadrao);
      return;
    }

    var arquivo = campo.files[0];
    var reader = new FileReader();

    reader.onload = function(e){
      imgOriginal.src = e.target.result;
      imgResultado.src = adicionarCacheNaImagem(imagemPadrao);
    };

    reader.onerror = function(){
      imgOriginal.src = adicionarCacheNaImagem(imagemPadrao);
      imgResultado.src = adicionarCacheNaImagem(imagemPadrao);
    };

    reader.readAsDataURL(arquivo);
  };

  window.enviarSimulacaoCliente = function(){
    if (window.loadSwal) window.loadSwal();
    var form = document.getElementById('form_simulacao_cliente');
    var campo = document.getElementById('foto_simulacao_cliente');
    var nomeCampo = document.getElementById('nome_simulacao_cliente');
    var telefoneCampo = document.getElementById('telefone_simulacao_cliente');
    var msg = document.getElementById('mensagem_simulacao_cliente');
    var imgOriginal = document.getElementById('preview_original_simulacao_cliente');
    var imgResultado = document.getElementById('preview_resultado_simulacao_cliente');

    if (simulacaoEmAndamento) return;
    if (!form || !campo || !msg || !imgOriginal || !imgResultado) return;

    var nome = nomeCampo ? nomeCampo.value.trim() : '';
    var telefone = telefoneCampo ? telefoneCampo.value.trim() : '';

    if (!nome || !telefone) {
      msg.className = 'text-danger';
      msg.innerText = 'Preencha nome e WhatsApp';
      return;
    }

    if (telefone.replace(/\D/g, '').length < 10) {
      msg.className = 'text-danger';
      msg.innerText = 'Preencha um WhatsApp válido';
      return;
    }

    if (!campo.files || !campo.files[0]) {
      msg.className = 'text-danger';
      msg.innerText = 'Selecione uma imagem para simular';
      return;
    }

    simulacaoEmAndamento = true;
    setEstadoBotao(true);

    var formData = new FormData(form);

    fetch(urlSimulacaoCliente, {
      method: 'POST',
      body: formData
    })
    .then(async function(response){
      var texto = await response.text();
      var dados = null;

      try {
        dados = JSON.parse(texto);
      } catch (e) {
        throw new Error(texto || 'Resposta inválida do servidor');
      }

      if (!response.ok || !dados || !retornoFoiSucesso(dados.status)) {
        throw new Error((dados && dados.mensagem) ? dados.mensagem : 'Erro ao processar a simulação');
      }

      var urlOriginal = '';
      var urlSimulada = '';

      if (dados.url_original) urlOriginal = normalizarUrlImagem(dados.url_original);
      else if (dados.foto_original) urlOriginal = montarUrlSimulacao(dados.foto_original);

      if (dados.url_simulada) urlSimulada = normalizarUrlImagem(dados.url_simulada);
      else if (dados.imagem_simulada) urlSimulada = montarUrlSimulacao(dados.imagem_simulada);

      if (!urlSimulada) throw new Error('A imagem simulada não foi retornada');

      if (urlOriginal) imgOriginal.src = adicionarCacheNaImagem(urlOriginal);
      imgResultado.src = adicionarCacheNaImagem(urlSimulada);

      fetch(urlCadastroCliente, {
        method: 'POST',
        body: new FormData(form)
      }).catch(function(){});

      msg.className = 'text-success';
      msg.innerText = 'Simulação enviada com sucesso';
      if (typeof window.jacyTrackAdsConversion === 'function') { window.jacyTrackAdsConversion('simulacao_protese_sucesso'); }
    })
    .catch(function(erro){
      imgResultado.src = adicionarCacheNaImagem(imagemPadrao);
      msg.className = 'text-danger';
      msg.innerText = erro.message ? erro.message : 'Erro ao processar a simulação';
    })
    .finally(function(){
      simulacaoEmAndamento = false;
      setEstadoBotao(false);
    });
  };

  document.addEventListener('DOMContentLoaded', function(){
    inicializarSimuladorCliente();
  });

  window.addEventListener('load', function(){
    inicializarSimuladorCliente();
  });

  document.addEventListener('click', function(ev){
    var abrir = ev.target.closest('[data-target="#modalSimulacaoCliente"], a[href="#modalSimulacaoCliente"]');
    if (abrir) {
      setTimeout(function(){ inicializarSimuladorCliente(); }, 80);
    }

    var fechar = ev.target.closest('#modalSimulacaoCliente [data-dismiss="modal"], #modalSimulacaoCliente .close');
    if (fechar) {
      setTimeout(function(){ resetarModalSimulacaoCliente(); }, 80);
    }
  });

  if (window.jQuery) {
    window.jQuery('#modalSimulacaoCliente').on('hidden.bs.modal', function(){
      resetarModalSimulacaoCliente();
    });

    window.jQuery('#modalSimulacaoCliente').on('shown.bs.modal', function(){
      inicializarSimuladorCliente();
    });
  }
})();

/* ===== Bloco externo migrado do protese.php (12) ===== */
(function(){
  var frames = Array.prototype.slice.call(document.querySelectorAll('iframe.js-lazy-iframe[data-src]'));
  if (!frames.length) return;

  function loadFrame(frame){
    if (!frame || frame.dataset.loaded === '1') return;
    var src = frame.getAttribute('data-src');
    if (!src) return;
    frame.src = src;
    frame.dataset.loaded = '1';
  }

  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function(entries){
      entries.forEach(function(entry){
        if (!entry.isIntersecting) return;
        loadFrame(entry.target);
        io.unobserve(entry.target);
      });
    }, { rootMargin: '350px 0px', threshold: 0.01 });

    frames.forEach(function(frame){ io.observe(frame); });
  } else {
    frames.forEach(loadFrame);
  }

  document.addEventListener('shown.bs.modal', function(e){
    if (!e.target) return;
    var frame = e.target.querySelector('iframe.js-lazy-iframe[data-src]');
    if (frame) loadFrame(frame);
  });

  document.addEventListener('click', function(e){
    var opener = e.target.closest('[data-target="#empresa"], a[href="#empresa"], [data-target="#modalVideo"], a[href="#modalVideo"]');
    if (!opener) return;
    setTimeout(function(){
      frames.forEach(function(frame){
        if (frame.offsetParent !== null) loadFrame(frame);
      });
    }, 120);
  });
})();

/* ===== Bloco externo migrado do protese.php (13) ===== */
(function(){
  var swalLoaded = false;

  function loadSwal(){
    if (swalLoaded) return;
    swalLoaded = true;

    var s = document.createElement('script');
    s.src = 'https://cdn.jsdelivr.net/npm/sweetalert2@11';
    s.defer = true;
    document.head.appendChild(s);
  }

  window.loadSwal = loadSwal;

  if ('requestIdleCallback' in window) {
    requestIdleCallback(function(){ setTimeout(loadSwal, 10000); }, { timeout: 7000 });
  } else {
    window.addEventListener('load', function(){ setTimeout(loadSwal, 10000); }, { once:true });
  }

  document.addEventListener('focusin', function(e){
    if (e.target.closest('#form_cadastro') || e.target.closest('#form_simulacao_cliente') || e.target.closest('#form')) {
      loadSwal();
    }
  });

  document.addEventListener('pointerdown', function(e){
    if (e.target.closest('#form_cadastro') || e.target.closest('#form_simulacao_cliente') || e.target.closest('#form')) {
      loadSwal();
    }
  }, { passive:true });
})();

/* ===== Bloco externo migrado do protese.php (14) ===== */
(function(){
  function marcarConversaoPorClique(rotulo){
    if (typeof window.jacyTrackAdsConversion === 'function') {
      window.jacyTrackAdsConversion(rotulo);
    }
  }

  document.addEventListener('click', function(ev){
    var alvo = ev.target.closest('a, button');
    if (!alvo) return;

    var href = (alvo.getAttribute('href') || '').toLowerCase();
    var id = (alvo.getAttribute('id') || '').toLowerCase();

    if (id === 'ctawhats' || href.indexOf('wa.me/') !== -1 || href.indexOf('api.whatsapp.com') !== -1) {
      marcarConversaoPorClique('clique_whatsapp_protese');
      return;
    }

    if (id === 'ctaapp' || href.indexOf('/agendamentos') !== -1 || href.indexOf('agendamentos') !== -1) {
      marcarConversaoPorClique('clique_agendamento_protese');
      return;
    }

    if (href.indexOf('abrir_simulador=1') !== -1 || href.indexOf('#modalsimulacaocliente') !== -1) {
      marcarConversaoPorClique('clique_simulacao_protese');
    }
  }, true);
})();

/* ============================================================
   Eventos retirados dos atributos HTML
   ============================================================ */
(function(){
  document.querySelectorAll('img[data-fallback-src]').forEach(function(img){
    img.addEventListener('error', function(){
      var fallback = img.getAttribute('data-fallback-src');
      if (!fallback || img.getAttribute('data-fallback-aplicado') === '1') return;
      img.setAttribute('data-fallback-aplicado', '1');
      img.src = fallback;
    });
  });

  var formSimulacao = document.getElementById('form_simulacao_cliente');
  if (formSimulacao) {
    formSimulacao.addEventListener('submit', function(evento){
      evento.preventDefault();
    });
  }

  var fotoSimulacao = document.getElementById('foto_simulacao_cliente');
  if (fotoSimulacao) {
    fotoSimulacao.addEventListener('change', function(){
      if (typeof window.carregarPreviewSimulacaoCliente === 'function') {
        window.carregarPreviewSimulacaoCliente();
      }
    });
  }

  var botaoSimulacao = document.getElementById('btn_simular_cliente');
  if (botaoSimulacao) {
    botaoSimulacao.addEventListener('click', function(){
      if (typeof window.enviarSimulacaoCliente === 'function') {
        window.enviarSimulacaoCliente();
      }
    });
  }

  var fotoComentario = document.getElementById('foto');
  if (fotoComentario) {
    fotoComentario.addEventListener('change', function(){
      if (typeof window.carregarImg === 'function') {
        window.carregarImg();
      }
    });
  }
})();
