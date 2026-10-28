/* ==========================================
   BARBERBOT – SCRIPTS GERAIS + CARROSSEIS
   ========================================== */

/* ----------------- Helpers ---------------- */
function getSlidesPerView() {
  const w = window.innerWidth;
  if (w >= 1024) return 4; // desktop
  if (w >= 768)  return 3; // tablet
  if (w >= 480)  return 2; // mobile grande
  return 1;                // mobile pequeno
}
function debounce(fn, wait) {
  let t; return (...args) => { clearTimeout(t); t = setTimeout(() => fn(...args), wait); };
}

/* ================= Depoimentos (3/2/1 por view) ================= */
let tCurrentSlide = 0;
let tTotalSlides  = 0;
let tAutoplay     = null;

function getTestimonialSlidesPerView(){
  const w = window.innerWidth;
  if (w >= 1024) return 3; // desktop
  if (w >= 768)  return 2; // tablet
  return 1;                // mobile
}
let tSlidesPerView = getTestimonialSlidesPerView();

function initTestimonialCarousel(){
  const track = document.getElementById('testimonials-track');
  if (!track) return;

  tTotalSlides = track.querySelectorAll('.testimonial-slide').length;

  createTestimonialIndicators();
  updateTestimonialCarousel();
  startTestimonialAutoplay();

  const container = document.querySelector('.testimonials-container');
  container?.addEventListener('mouseenter', stopTestimonialAutoplay);
  container?.addEventListener('mouseleave', startTestimonialAutoplay);

  // teclado
  document.addEventListener('keydown', (e)=>{
    if (e.key === 'ArrowLeft')  moveTestimonialCarousel(-1);
    if (e.key === 'ArrowRight') moveTestimonialCarousel(1);
  });

  // swipe
  let sx=0, ex=0;
  document.querySelector('.testimonials-slider')?.addEventListener('touchstart', e=> sx = e.changedTouches[0].screenX, false);
  document.querySelector('.testimonials-slider')?.addEventListener('touchend',   e=>{
    ex = e.changedTouches[0].screenX;
    const diff = sx - ex;
    if (Math.abs(diff) > 50) moveTestimonialCarousel(diff > 0 ? 1 : -1);
  }, false);

  // ajusta responsivo sem recarregar a página
  window.addEventListener('resize', debounce(()=>{
    const n = getTestimonialSlidesPerView();
    if (n !== tSlidesPerView){
      tSlidesPerView = n;
      tCurrentSlide = 0;
      createTestimonialIndicators();
      updateTestimonialCarousel();
    }
  }, 200));
}

function createTestimonialIndicators(){
  const box = document.getElementById('indicators');
  if (!box) return;
  const pages = Math.ceil(tTotalSlides / tSlidesPerView);
  box.innerHTML = '';
  for (let i = 0; i < pages; i++){
    const d = document.createElement('div');
    d.className = 'indicator' + (i===0?' active':'');
    d.addEventListener('click', ()=> {
      tCurrentSlide = i;
      updateTestimonialCarousel();
      stopTestimonialAutoplay(); startTestimonialAutoplay();
    });
    box.appendChild(d);
  }
}

function moveTestimonialCarousel(dir){
  const max = Math.ceil(tTotalSlides / tSlidesPerView) - 1;
  tCurrentSlide += dir;
  if (tCurrentSlide < 0) tCurrentSlide = max;
  if (tCurrentSlide > max) tCurrentSlide = 0;
  updateTestimonialCarousel();
}

function updateTestimonialCarousel(){
  const track = document.getElementById('testimonials-track');
  if (!track) return;

  /* como cada slide tem width = 100% / tSlidesPerView (sem gap),
     cada "página" equivale a 100%. */
  const offset = -(tCurrentSlide * 100);
  track.style.transform = `translateX(${offset}%)`;

  // indicadores
  const dots = document.querySelectorAll('#indicators .indicator');
  dots.forEach((el, i)=> el.classList.toggle('active', i === tCurrentSlide));
}

function startTestimonialAutoplay(){
  stopTestimonialAutoplay();
  tAutoplay = setInterval(()=> moveTestimonialCarousel(1), 5000);
}
function stopTestimonialAutoplay(){
  if (tAutoplay){ clearInterval(tAutoplay); tAutoplay = null; }
}

/* ---- Wrappers para manter a lógica de nomes usados no final ---- */
function startAutoplay(){ startTestimonialAutoplay(); }
function stopAutoplay(){  stopTestimonialAutoplay();  }

/* ===================== CLIENTS CAROUSEL ===================== */
(function(){
  let clientsCurrent = 0;
  let clientsTotal = 0;
  let clientsPerView = calcClientsPerView();
  let clientsAutoplay = null;

  function calcClientsPerView(){
    const w = window.innerWidth;
    if (w >= 1024) return 4; // desktop
    if (w >= 768)  return 3; // tablet
    if (w >= 480)  return 2; // mobile grande
    return 1;                // mobile pequeno
  }

  function initClientsCarousel(){
    const track = document.getElementById('clients-track');
    if(!track) return;

    clientsTotal = track.querySelectorAll('.client-slide').length;

    // setas
    document.getElementById('clients-prev')?.addEventListener('click', ()=> clientsMove(-1));
    document.getElementById('clients-next')?.addEventListener('click', ()=> clientsMove(1));

    // indicadores (se existir o container)
    createClientsIndicators();

    // autoplay + pausa no hover
    const slider = document.querySelector('.clients-slider');
    slider?.addEventListener('mouseenter', clientsStopAutoplay);
    slider?.addEventListener('mouseleave', clientsStartAutoplay);
    clientsStartAutoplay();

    // teclado
    document.addEventListener('keydown', (e)=>{
      if (e.key === 'ArrowLeft')  clientsMove(-1);
      if (e.key === 'ArrowRight') clientsMove(1);
    });

    // swipe mobile
    let sx=0, ex=0;
    slider?.addEventListener('touchstart', e=> sx = e.changedTouches[0].screenX, {passive:true});
    slider?.addEventListener('touchend',   e=>{
      ex = e.changedTouches[0].screenX;
      const diff = sx - ex;
      if (Math.abs(diff) > 50) clientsMove(diff > 0 ? 1 : -1);
    }, {passive:true});

    // resize: recalcula sem recarregar página
    window.addEventListener('resize', clientsDebounce(()=>{
      const v = calcClientsPerView();
      if (v !== clientsPerView){
        clientsPerView = v;
        clientsCurrent = 0;
        createClientsIndicators();
        clientsUpdate();
      }
    }, 150));

    clientsUpdate();
  }

  function clientsPages(){
    return Math.max(1, Math.ceil(clientsTotal / clientsPerView));
  }

  function clientsMove(dir){
    const max = clientsPages() - 1;
    clientsCurrent += dir;
    if (clientsCurrent < 0) clientsCurrent = max;
    if (clientsCurrent > max) clientsCurrent = 0;
    clientsUpdate();
  }

  function clientsGoTo(i){
    clientsCurrent = i;
    clientsUpdate();
    clientsStopAutoplay(); clientsStartAutoplay();
  }

  function clientsUpdate(){
    const track = document.getElementById('clients-track');
    if(!track) return;

    // cada "página" equivale a 100%
    const offset = -(clientsCurrent * 100);
    track.style.transform = `translateX(${offset}%)`;

    // atualiza indicadores
    document.querySelectorAll('#clients-indicators .indicator')
      .forEach((dot, idx)=> dot.classList.toggle('active', idx === clientsCurrent));
  }

  function createClientsIndicators(){
    const box = document.getElementById('clients-indicators');
    if(!box) return;
    const total = clientsPages();
    box.innerHTML = '';
    for(let i=0;i<total;i++){
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'indicator' + (i===0 ? ' active' : '');
      b.addEventListener('click', ()=> clientsGoTo(i));
      box.appendChild(b);
    }
  }

  function clientsStartAutoplay(){
    clientsStopAutoplay();
    clientsAutoplay = setInterval(()=> clientsMove(1), 4500);
  }
  function clientsStopAutoplay(){
    if (clientsAutoplay){ clearInterval(clientsAutoplay); clientsAutoplay = null; }
  }

  // utilitário debounce local (não conflita com outros)
  function clientsDebounce(fn, wait){
    let t; return function(){ clearTimeout(t); t = setTimeout(()=>fn.apply(this, arguments), wait); };
  }

  // expõe apenas o necessário para os wrappers globais
  window.startClientAutoplay = clientsStartAutoplay;
  window.stopClientAutoplay  = clientsStopAutoplay;

  document.addEventListener('DOMContentLoaded', initClientsCarousel);
})();

/* ----------------- Scroll suave ----------------- */
function initSmoothScroll() {
  document.querySelectorAll('a[href^="#"]').forEach(a => {
    a.addEventListener('click', (e) => {
      const target = document.querySelector(a.getAttribute('href'));
      if (target) {
        e.preventDefault();
        target.scrollIntoView({ behavior: 'smooth', block: 'start' });
      }
    });
  });
}
function scrollToSection(id) {
  const el = document.getElementById(id);
  if (el) el.scrollIntoView({ behavior: 'smooth', block: 'start' });
}

/* --------- Estatísticas (contadores) ---------- */
function initStats() {
  const stats = document.querySelectorAll('.stat-number');
  if (!stats.length) return;

  const obs = new IntersectionObserver((entries, o) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        animateNumber(entry.target);
        o.unobserve(entry.target);
      }
    });
  }, { threshold: 0.5 });

  stats.forEach(s => obs.observe(s));
}
function animateNumber(el) {
  const target = parseInt(el.textContent, 10) || 0;
  const duration = 2000, steps = 60, inc = target / steps;
  let cur = 0;
  const timer = setInterval(() => {
    cur += inc;
    if (cur >= target) { el.textContent = target; clearInterval(timer); }
    else { el.textContent = Math.floor(cur); }
  }, duration / steps);
}

/* ---- Animações on-scroll para cards ---- */
function initScrollAnimations() {
  const els = document.querySelectorAll('.problem-card, .benefit-card, .feature-content, .pricing-card');
  if (!els.length) return;

  const obs = new IntersectionObserver((entries, o) => {
    entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.style.opacity = '0';
        entry.target.style.transform = 'translateY(30px)';
        setTimeout(() => {
          entry.target.style.transition = 'all .6s ease';
          entry.target.style.opacity = '1';
          entry.target.style.transform = 'translateY(0)';
        }, 100);
        o.unobserve(entry.target);
      }
    });
  }, { threshold: 0.1, rootMargin: '0px 0px -100px 0px' });

  els.forEach(el => obs.observe(el));
}

/* ----------- Boot / Listeners ----------- */
document.addEventListener('DOMContentLoaded', () => {
  initTestimonialCarousel();
  initSmoothScroll();
  initStats();
  initScrollAnimations();
});

window.addEventListener('load', initScrollAnimations);

// guarda para recarregar se mudar a “categoria” de colunas visíveis
let baseSlidesPerView = getSlidesPerView();
const resizeGuard = debounce(() => {
  const now = getSlidesPerView();
  if (now !== baseSlidesPerView) location.reload();
}, 250);
window.addEventListener('resize', resizeGuard);

// pausa/reinicia autoplay quando a aba perde/retoma foco
document.addEventListener('visibilitychange', () => {
  if (document.hidden) { stopAutoplay(); stopClientAutoplay(); }
  else { startAutoplay(); startClientAutoplay(); }
});
