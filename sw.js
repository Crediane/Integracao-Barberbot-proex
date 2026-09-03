const APP_NAME = 'Barberbot software';
const APP_VER = '1.2';
const CACHE_NAME = `${APP_NAME}-${APP_VER}`;

// Somente arquivos locais e estaticos usados na parte publica.
// Caminhos absolutos evitam quebra em /loja/{slug}/.
// Nao incluir arquivos que podem nao existir, como /images/favicon_192.png e /images/favicon_512.png.
const STATIC_FILES = [
  '/css/agendamento.css',
  '/app/css/style.css',
  '/app/css/bootstrap.css',
  '/images/favicon.png',
  '/sistema/img/icone.png',
  '/_manifest.json'
];

function deveIgnorarFetch(requisicao, url) {
  if (requisicao.method !== 'GET') {
    return true;
  }

  // Paginas dinamicas devem ir direto para a rede.
  // Isso preserva /loja/{slug}/, MASTER /sistema e conexoes por banco individual.
  if (requisicao.mode === 'navigate') {
    return true;
  }

  if (url.origin !== self.location.origin) {
    return true;
  }

  // Nao interceptar rotas dinamicas do sistema.
  if (
    url.pathname.startsWith('/ajax/') ||
    url.pathname.startsWith('/loja/') ||
    url.pathname.startsWith('/sistema/')
  ) {
    return true;
  }

  return false;
}

self.addEventListener('install', (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME)
      .then((cache) => Promise.all(
        STATIC_FILES.map((arquivo) => {
          return fetch(arquivo, { cache: 'reload' })
            .then((resposta) => {
              if (resposta && resposta.ok) {
                return cache.put(arquivo, resposta);
              }

              console.warn('Arquivo ignorado no cache:', arquivo, resposta ? resposta.status : 'sem resposta');
              return null;
            })
            .catch((erro) => {
              console.warn('Falha ao carregar arquivo do cache:', arquivo, erro);
              return null;
            });
        })
      ))
      .then(() => self.skipWaiting())
  );
});

self.addEventListener('activate', (event) => {
  event.waitUntil(
    caches.keys()
      .then((chaves) => Promise.all(
        chaves
          .filter((chave) => chave.startsWith(APP_NAME + '-') && chave !== CACHE_NAME)
          .map((chave) => caches.delete(chave))
      ))
      .then(() => self.clients.claim())
  );
});

self.addEventListener('fetch', (event) => {
  const requisicao = event.request;
  const url = new URL(requisicao.url);

  if (deveIgnorarFetch(requisicao, url)) {
    return;
  }

  event.respondWith(
    caches.match(requisicao).then((respostaCache) => {
      if (respostaCache) {
        return respostaCache;
      }

      return fetch(requisicao).then((respostaRede) => {
        if (!respostaRede || !respostaRede.ok) {
          return respostaRede;
        }

        const destino = requisicao.destination || '';
        const podeCachear = ['style', 'script', 'image', 'font'].includes(destino);

        if (podeCachear) {
          const copia = respostaRede.clone();
          caches.open(CACHE_NAME)
            .then((cache) => cache.put(requisicao, copia))
            .catch(() => {});
        }

        return respostaRede;
      });
    })
  );
});
