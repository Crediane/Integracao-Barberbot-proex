document.addEventListener('DOMContentLoaded', function() {
	var botaoEditar = document.getElementById('botao_editar');
	if (botaoEditar) {
		botaoEditar.style.display = 'none';
	}

	var carousel = document.getElementById('carouselCorteCabeloPortfolio');
	if (!carousel) {
		return;
	}

	var itens = Array.prototype.slice.call(carousel.querySelectorAll('.carousel-item'));
	if (itens.length <= 1) {
		var controles = carousel.querySelector('.assinatura-corte-cabelo-carousel-controls');
		if (controles) {
			controles.style.display = 'none';
		}
		return;
	}

	var atual = itens.findIndex(function(item) {
		return item.classList.contains('active');
	});
	if (atual < 0) {
		atual = 0;
		itens[0].classList.add('active');
	}

	function pausarMidia(item) {
		if (!item) {
			return;
		}

		var videos = item.querySelectorAll('video');
		videos.forEach(function(video) {
			try {
				video.pause();
			} catch (e) {}
		});

		var iframes = item.querySelectorAll('iframe');
		iframes.forEach(function(iframe) {
			var src = iframe.getAttribute('src');
			if (src) {
				iframe.setAttribute('src', src);
			}
		});
	}

	function mostrar(indice) {
		if (indice === atual) {
			return;
		}

		pausarMidia(itens[atual]);

		itens[atual].classList.remove('active');
		atual = (indice + itens.length) % itens.length;
		itens[atual].classList.add('active');
	}

	function proximo() {
		mostrar(atual + 1);
	}

	function anterior() {
		mostrar(atual - 1);
	}

	var btnAnterior = carousel.querySelector('.carousel-control-prev');
	var btnProximo = carousel.querySelector('.carousel-control-next');

	if (btnAnterior) {
		btnAnterior.addEventListener('click', function(e) {
			e.preventDefault();
			anterior();
		});
	}

	if (btnProximo) {
		btnProximo.addEventListener('click', function(e) {
			e.preventDefault();
			proximo();
		});
	}

	var intervalo = parseInt(carousel.getAttribute('data-interval') || '5000', 10);
	var timer = null;

	function iniciarAuto() {
		if (timer || !intervalo || intervalo < 1000) {
			return;
		}
		timer = window.setInterval(proximo, intervalo);
	}

	function pararAuto() {
		if (timer) {
			window.clearInterval(timer);
			timer = null;
		}
	}

	carousel.addEventListener('mouseenter', pararAuto);
	carousel.addEventListener('mouseleave', iniciarAuto);
	carousel.addEventListener('focusin', pararAuto);
	carousel.addEventListener('focusout', iniciarAuto);

	iniciarAuto();
});
