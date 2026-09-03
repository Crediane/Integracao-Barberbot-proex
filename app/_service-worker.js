// BarberBot Profissional - Service Worker isolado por barbearia.
// Cada registro usa o escopo público /loja/{slug}/prof/ (ou equivalente
// já aceito pelo domínio próprio). Requisições PHP, navegação, POST e URLs
// com parâmetros nunca são armazenadas em cache.
var APP_NAME = 'BarberBot-Profissional';
var APP_VER = '2.0';
var APP_DIAG = false;

function bbSwLog(message, extra) {
    if (APP_DIAG && typeof console !== 'undefined' && console.log) {
        console.log(message, extra || '');
    }
}

function bbSwScopeUrl() {
    try {
        return new URL(self.registration.scope);
    } catch (e) {
        return new URL(self.location.href);
    }
}

function bbSwScopeKey() {
    return bbSwScopeUrl().pathname
        .replace(/[^a-z0-9_-]+/ig, '_')
        .replace(/^_+|_+$/g, '') || 'default';
}

var CACHE_NAME = APP_NAME + '-' + APP_VER + '-' + bbSwScopeKey();
var REQUIRED_FILES = [
    'painel/styles/style.css',
    'painel/styles/bootstrap.css',
    'painel/scripts/custom.js',
    'painel/scripts/bootstrap.min.js',
    'painel/images/empty.png',
    'app/icons/icon-192x192.png'
];

function bbSwRequestUrl(request) {
    try {
        return new URL(request.url);
    } catch (e) {
        return null;
    }
}

function bbSwShouldIgnore(request) {
    if (!request || request.method !== 'GET') return true;

    var url = bbSwRequestUrl(request);
    if (!url) return true;

    var scopeUrl = bbSwScopeUrl();

    // Nunca intercepta outro domínio, outra barbearia ou o painel Master.
    if (url.origin !== scopeUrl.origin) return true;
    if (url.href.indexOf(scopeUrl.href) !== 0) return true;
    if (url.pathname.indexOf('/sistema/') !== -1) return true;

    // Navegação e dados dinâmicos sempre vêm diretamente do servidor.
    if (request.mode === 'navigate') return true;
    if (url.search !== '') return true;
    if (/\.php$/i.test(url.pathname)) return true;

    // Somente arquivos estáticos podem entrar no cache.
    return !(/\.(css|js|png|jpg|jpeg|gif|svg|webp|ico|woff|woff2|ttf)$/i.test(url.pathname));
}

self.addEventListener('install', function (event) {
    event.waitUntil(
        caches.open(CACHE_NAME)
            .then(function (cache) {
                return Promise.all(REQUIRED_FILES.map(function (file) {
                    return cache.add(file).catch(function (error) {
                        bbSwLog('Arquivo não armazenado: ' + file, error);
                    });
                }));
            })
            .then(function () {
                return self.skipWaiting();
            })
    );
});

self.addEventListener('activate', function (event) {
    event.waitUntil(
        caches.keys()
            .then(function (cacheNames) {
                return Promise.all(
                    cacheNames
                        .filter(function (cacheName) {
                            return cacheName.indexOf(APP_NAME + '-') === 0 && cacheName !== CACHE_NAME;
                        })
                        .map(function (cacheName) {
                            return caches.delete(cacheName);
                        })
                );
            })
            .then(function () {
                return self.clients.claim();
            })
    );
});

self.addEventListener('fetch', function (event) {
    var request = event.request;

    if (bbSwShouldIgnore(request)) return;

    event.respondWith(
        caches.match(request).then(function (cachedResponse) {
            if (cachedResponse) return cachedResponse;

            return fetch(request).then(function (networkResponse) {
                if (networkResponse && networkResponse.ok) {
                    var responseClone = networkResponse.clone();
                    caches.open(CACHE_NAME).then(function (cache) {
                        cache.put(request, responseClone).catch(function (error) {
                            bbSwLog('Falha ao atualizar cache.', error);
                        });
                    });
                }
                return networkResponse;
            });
        })
    );
});
