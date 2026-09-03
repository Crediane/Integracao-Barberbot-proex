// Para limpar o cache em dispositivos, sempre aumente o número APP_VER após fazer alterações.
// Correção BarberBot: o Service Worker não deve interceptar POST, rotas dinâmicas,
// navegação /loja/{slug}/... nem endpoints /sistema/.
// Isso evita falha no botão SALVAR da agenda e preserva o isolamento multi-tenant.
var APP_NAME = 'Geastão Sistemas';
var APP_VER = '1.6';
var APP_DIAG = false;

function bb_sw_log(msg, extra) {
	if (APP_DIAG && typeof console !== 'undefined' && console.log) {
		console.log(msg, extra || '');
	}
}

function bb_sw_scope_key() {
	try {
		var escopo = (self.registration && self.registration.scope) ? self.registration.scope : self.location.href;
		return String(escopo)
			.replace(/^https?:\/\/[^\/]+/i, '')
			.replace(/[^a-z0-9_-]+/ig, '_')
			.replace(/^_+|_+$/g, '') || 'default';
	} catch (e) {
		return 'default';
	}
}

var CACHE_NAME = APP_NAME + '-' + APP_VER + '-' + bb_sw_scope_key();

// Arquivos estáticos usados somente para apoio offline.
// Não cachear index.php nem páginas dinâmicas de tenant.
var REQUIRED_FILES = [
	'styles/style.css',
	'styles/bootstrap.css',
	'scripts/custom.js',
	'scripts/bootstrap.min.js',
	'images/empty.png',
	'app/icons/icon-192x192.png'
];

function bb_sw_url(request) {
	try {
		return new URL(request.url);
	} catch (e) {
		return null;
	}
}

function bb_sw_deve_ignorar(request) {
	if (!request || request.method !== 'GET') return true;

	var url = bb_sw_url(request);
	if (!url) return true;

	// Nunca interceptar outros domínios/CDNs.
	if (url.origin !== self.location.origin) return true;

	// Nunca interceptar navegação HTML. A página do tenant precisa vir do PHP/servidor.
	if (request.mode === 'navigate') return true;

	// Nunca interceptar URLs com querystring, endpoints e rotas multi-tenant.
	if (url.search) return true;
	if (url.pathname.indexOf('/sistema/') !== -1) return true;
	if (url.pathname.indexOf('/loja/') === 0) return true;

	// Cache apenas de arquivos estáticos.
	return !(/\.(css|js|png|jpg|jpeg|gif|svg|webp|ico|woff|woff2|ttf)$/i.test(url.pathname));
}

self.addEventListener('install', function(event) {
	event.waitUntil(
		caches.open(CACHE_NAME)
			.then(function(cache) {
				return Promise.all(REQUIRED_FILES.map(function(arquivo) {
					return cache.add(arquivo).catch(function(error) {
						bb_sw_log('Service Worker: arquivo não cacheado: ' + arquivo, error);
					});
				}));
			})
			.then(function() {
				return self.skipWaiting();
			})
	);
	bb_sw_log('Service Worker: Installed ' + CACHE_NAME);
});

self.addEventListener('fetch', function(event) {
	var request = event.request;

	if (bb_sw_deve_ignorar(request)) {
		return;
	}

	event.respondWith(
		caches.match(request)
			.then(function(responseCache) {
				if (responseCache) return responseCache;

				return fetch(request)
					.then(function(responseNetwork) {
						if (responseNetwork && responseNetwork.ok) {
							var clone = responseNetwork.clone();
							caches.open(CACHE_NAME).then(function(cache) {
								cache.put(request, clone).catch(function(error) {
									bb_sw_log('Service Worker: falha ao gravar cache', error);
								});
							});
						}
						return responseNetwork;
					})
					.catch(function(error) {
						bb_sw_log('Service Worker: fetch falhou', error);
						return caches.match(request).then(function(responseFallback) {
							return responseFallback || new Response('', {
								status: 504,
								statusText: 'Offline'
							});
						});
					});
			})
	);
});

self.addEventListener('activate', function(event) {
	event.waitUntil(
		caches.keys().then(function(cacheNames) {
			return Promise.all(
				cacheNames
					.filter(function(cacheName) {
						return cacheName.indexOf(APP_NAME + '-') === 0 && cacheName !== CACHE_NAME;
					})
					.map(function(cacheName) {
						return caches.delete(cacheName);
					})
			);
		}).then(function() {
			return self.clients.claim();
		})
	);
	bb_sw_log('Service Worker: Activated ' + CACHE_NAME);
});
