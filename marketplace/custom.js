/* ARQUIVO: public_html/marketplace/custom.js */

// Modal de Vídeo
function openVideoModal() {
    const modal = document.getElementById('videoModal');
    const iframe = document.getElementById('youtubeFrame');
    // Adiciona o link do vídeo com autoplay ao abrir
    if(iframe) iframe.src = "https://www.youtube.com/embed/bQUXm_vuwHw?autoplay=1&rel=0";
    if(modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden'; 
    }
}

function closeVideoModal() {
    const modal = document.getElementById('videoModal');
    const iframe = document.getElementById('youtubeFrame');
    // Remove o link para parar o som
    if(iframe) iframe.src = "";
    if(modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto'; 
    }
}

// Fechar modal ao clicar fora
document.addEventListener('DOMContentLoaded', function(){
    const modalEl = document.getElementById('videoModal');
    if(modalEl){
        modalEl.addEventListener('click', function(e) {
            if (e.target === this) { closeVideoModal(); }
        });
    }
});

// Alternar Preços
function togglePricing() {
    const isAnnual = document.getElementById('pricing-toggle').checked;
    const pricePro = document.getElementById('price-pro');
    const priceBiz = document.getElementById('price-biz');

    if (isAnnual) {
        pricePro.innerHTML = 'R$ 690<span>/ano</span>';
        priceBiz.innerHTML = 'R$ 970<span>/ano</span>';
    } else {
        pricePro.innerHTML = 'R$ 69,90<span>/mês</span>';
        priceBiz.innerHTML = 'R$ 97,00<span>/mês</span>';
    }
}

// Menu Mobile
function toggleMenu() {
    const nav = document.querySelector('.nav-links');
    if (nav.style.display === 'flex') {
        nav.style.display = 'none';
    } else {
        nav.style.display = 'flex';
        nav.style.flexDirection = 'column';
        nav.style.position = 'absolute';
        nav.style.top = '70px';
        nav.style.left = '0';
        nav.style.width = '100%';
        nav.style.background = '#020617';
        nav.style.padding = '20px';
    }
}

// Smooth Scroll
document.addEventListener('DOMContentLoaded', function(){
    document.querySelectorAll('a[href^="#"]').forEach(anchor => {
        anchor.addEventListener('click', function (e) {
            const seletor = this.getAttribute('href');
            if(!seletor || seletor === '#') return;
            const target = document.querySelector(seletor);
            if(target) {
                e.preventDefault();
                target.scrollIntoView({ behavior: 'smooth' });
            }
        });
    });
});