<?php
// ===== Sessão isolada por contexto (MASTER x TENANT) =====
// Evita que o cookie padrão (PHPSESSID) misture MASTER x TENANT.
// Regra:
// - /sistema (sem /loja/ e sem ?loja=) => BB_MASTER
// - /loja/<slug>/... OU ?loja=...      => sessão TENANT isolada por SLUG (BB_TENANT_<hash>)
// - demais                              => BB_SITE
$__bb_reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$bb_is_protese_page = (bool)preg_match('~^/protese(?:/|$)~i', $__bb_reqPath);

if (!function_exists('bb_sess_clean_slug')) {
  function bb_sess_clean_slug($v) {
    $v = strtolower(trim((string)$v));
    return preg_replace('/[^a-z0-9_-]/', '', $v);
  }
}

$__bb_slug = '';
if (!empty($_GET['loja'])) {
  $__bb_slug = bb_sess_clean_slug($_GET['loja']);
} elseif (!empty($_POST['loja'])) {
  $__bb_slug = bb_sess_clean_slug($_POST['loja']);
} else {
  if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath, $mSlug)) {
    $__bb_slug = bb_sess_clean_slug($mSlug[1]);
  }
}

$__bb_hasLoja = ($__bb_slug !== '') || (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath) || !empty($_GET['loja']) || !empty($_POST['loja']);
$__bb_isSistema = (strpos($__bb_reqPath, '/sistema') === 0);

// Força MASTER em /sistema quando NÃO existe loja explícita
$__bb_forceMaster = ($__bb_isSistema && !$__bb_hasLoja);

// Sessão TENANT isolada por SLUG (cookie diferente por loja)
$__bb_tenantSessionName = 'BB_TENANT';
if ($__bb_slug !== '') {
  $__bb_tenantSessionName = 'BB_TENANT_' . strtoupper(substr(sha1($__bb_slug), 0, 12));
}

$__bb_desiredSession = $__bb_forceMaster ? 'BB_MASTER' : ($__bb_hasLoja ? $__bb_tenantSessionName : 'BB_SITE');

// Inicia/ajusta a sessão corretamente (usa o cookie do nome escolhido)
if (session_status() === PHP_SESSION_ACTIVE) {
  if (session_name() !== $__bb_desiredSession) {
    session_write_close();
    @session_name($__bb_desiredSession);
    if (!empty($_COOKIE[$__bb_desiredSession])) {
      @session_id((string)$_COOKIE[$__bb_desiredSession]);
    }
    @session_start();
  }
} else {
  @session_name($__bb_desiredSession);
  if (!empty($_COOKIE[$__bb_desiredSession])) {
    @session_id((string)$_COOKIE[$__bb_desiredSession]);
  }
  @session_start();
}

/**
 * MULTI-TENANT (para carregar o banco/config correto)
 * - /loja/<slug>/... => dados do estabelecimento (banco individual)
 * - fora de /loja/   => dados do dono do sistema
 * - NÃO muda a lógica de negócio
 */
if (!function_exists('mt_slug')) {
  function mt_slug(string $v): string {
    $v = strtolower(trim($v));
    return (string)preg_replace('/[^a-z0-9_-]/', '', $v);
  }
}

$uri = (string)($_SERVER['REQUEST_URI'] ?? '');
$tenant_slug = '';
$is_loja_context = false;

// Detecta tenant apenas quando a URL atual estiver em /loja/ (ou GET loja)
if (!empty($_GET['loja'])) {
  $tenant_slug = mt_slug((string)$_GET['loja']);
  $is_loja_context = true;
} elseif ($uri && preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $uri, $m)) {
  $tenant_slug = mt_slug((string)$m[1]);
  $is_loja_context = true;
}

if ($is_loja_context && $tenant_slug !== '') {
  // garante o tenant correto na sessão (banco individual)
  $_SESSION['tenant_slug'] = $tenant_slug;
  $_SESSION['loja'] = $tenant_slug;
} else {
  // fora da loja, garante dados do dono do sistema (evita "vazar" tenant)
  unset($_SESSION['tenant_slug'], $_SESSION['loja']);
}

// Carrega conexão/config do sistema (multi-tenant depende da sessão acima)
require_once(__DIR__ . "/sistema/conexao.php");
require_once(__DIR__ . "/security.php");

$assets_base = '/'; // assets sempre na raiz pública
$tenant_base = ($tenant_slug !== '') ? ('/loja/' . $tenant_slug) : '';

/**
 * ============================================================
 * Helpers de links (URL bonita /loja/<slug>/...)
 * - Corrige mídias com caminhos relativos (img/video) para não quebrar em subpastas
 * - Não altera lógica de negócio nem seleção de banco (tenant já definido na sessão)
 * ============================================================
 */
$bb_docroot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
if ($bb_docroot === '') {
  $bb_docroot = rtrim((string)realpath(__DIR__), '/');
}

if (!function_exists('bb_url_abs')) {
  function bb_url_abs(string $url): string {
    $url = trim($url);
    if ($url === '') { return ''; }

    // mantém URLs absolutas e especiais
    if (preg_match('~^(https?:)?//~i', $url) || $url[0] === '/' || stripos($url, 'data:') === 0) {
      return $url;
    }

    // prefixa raiz
    return '/' . ltrim($url, '/');
  }
}

if (!function_exists('bb_img_src')) {
  /**
   * Retorna src absoluto (com "/") priorizando .webp quando existir.
   * Se não existir a imagem (ou foto vazia), usa um placeholder padrão.
   */
  function bb_img_src(string $relPath): string {
    global $bb_docroot;

    $relPath = ltrim($relPath, '/');
    $placeholder = 'sistema/painel/img/comentarios/sem-foto.jpg';

    // normaliza barras
    $relPath = str_replace(['\\', '//'], ['/', '/'], $relPath);

    // se veio vazio ou termina com barra (pasta), retorna placeholder
    if ($relPath === '' || substr($relPath, -1) === '/') {
      return '/' . $placeholder;
    }

    $webpRel = preg_replace('/\.(jpe?g|png)$/i', '.webp', $relPath);

    $fsWebp = $bb_docroot . '/' . $webpRel;
    $fsOrig = $bb_docroot . '/' . $relPath;

    if (is_file($fsWebp)) { return '/' . $webpRel; }
    if (is_file($fsOrig)) { return '/' . $relPath; }

    return '/' . $placeholder;
  }
}

if (!function_exists('bb_loja_img_src')) {
  /**
   * Imagens públicas do site /loja (ex.: /loja/images/...).
   * Recebe caminho relativo dentro da pasta /loja (ex.: "images/x.jpg")
   * e devolve src absoluto ("/loja/images/x.webp" se existir).
   */
  function bb_loja_img_src(string $relPath): string {
    global $bb_docroot;

    $relPath = ltrim($relPath, '/');
    $placeholder = 'sistema/painel/img/comentarios/sem-foto.jpg';

    // prefixa pasta física /loja se não veio prefixado
    if (stripos($relPath, 'loja/') !== 0) {
      $relPath = 'loja/' . $relPath;
    }

    // normaliza barras
    $relPath = str_replace(['\\', '//'], ['/', '/'], $relPath);

    // se veio vazio ou termina com barra (pasta), retorna placeholder
    if ($relPath === 'loja/' || substr($relPath, -1) === '/') {
      return '/' . $placeholder;
    }

    $webpRel = preg_replace('/\.(jpe?g|png)$/i', '.webp', $relPath);

    $fsWebp = $bb_docroot . '/' . $webpRel;
    $fsOrig = $bb_docroot . '/' . $relPath;

    if (is_file($fsWebp)) { return '/' . $webpRel; }
    if (is_file($fsOrig)) { return '/' . $relPath; }

    return '/' . $placeholder;
  }
}

// Links principais (mantém rotas já existentes)
$link_agendamentos = ($tenant_base !== '') ? ($tenant_base . '/agendamentos') : '/agendamentos';
$link_assinatura   = ($tenant_base !== '') ? ($tenant_base . '/assinatura')   : '/assinatura';
$link_servicos     = ($tenant_base !== '') ? ($tenant_base . '/servicos')     : '/servicos';
$link_produtos     = ($tenant_base !== '') ? ($tenant_base . '/produtos')     : '/produtos';

// Acessos (multi-tenant quando houver slug)
$link_acessar_cliente = ($tenant_base !== '') ? ($tenant_base . '/sistema/acesso') : '/sistema/acesso';
$link_cadastro_cliente = ($tenant_base !== '') ? ($tenant_base . '/cadastro-cliente.php') : '/cadastro-cliente.php';
$link_acessar_prof    = ($tenant_base !== '') ? ($tenant_base . '/prof/')    : '/sistema';


/**
 * ============================================================
 * PERFORMANCE DO BANNER PRINCIPAL
 * - Mantém a lógica original: continua usando $img_banner_index.
 * - Quando existir imagem otimizada em /images/seo-cache/, entrega WebP menor.
 * - Quando não existir cache, usa a imagem original sem quebrar o site.
 * ============================================================
 */
if (!function_exists('bb_perf_url_absoluta')) {
  function bb_perf_url_absoluta(string $url): string {
    $url = trim($url);
    if ($url === '') { return '/images/banner-barbeiro.webp'; }
    if (preg_match('~^(https?:)?//~i', $url) || strpos($url, 'data:') === 0) { return $url; }
    return '/' . ltrim($url, '/');
  }
}

if (!function_exists('bb_perf_fs_path')) {
  function bb_perf_fs_path(string $url): string {
    $path = parse_url($url, PHP_URL_PATH);
    if (!$path) { $path = $url; }
    $path = '/' . ltrim($path, '/');
    $docroot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');

    if ($docroot !== '' && is_file($docroot . $path)) {
      return $docroot . $path;
    }

    return __DIR__ . $path;
  }
}

if (!function_exists('bb_perf_banner_data')) {
  function bb_perf_banner_data(string $url): array {
    $url = bb_perf_url_absoluta($url);
    $pathUrl = parse_url($url, PHP_URL_PATH) ?: $url;
    $pathUrl = '/' . ltrim($pathUrl, '/');
    $fsOrig = bb_perf_fs_path($pathUrl);

    $largura = 1280;
    $altura = 720;

    if (is_file($fsOrig)) {
      $dim = @getimagesize($fsOrig);
      if (is_array($dim) && !empty($dim[0]) && !empty($dim[1])) {
        $largura = (int)$dim[0];
        $altura = (int)$dim[1];
      }
    }

    $info = pathinfo($pathUrl);
    $base = preg_replace('/[^a-zA-Z0-9_-]+/', '-', (string)($info['filename'] ?? 'banner'));
    $base = trim($base, '-');
    if ($base === '') { $base = 'banner'; }

    $dirUrl = rtrim((string)($info['dirname'] ?? '/images'), '/');
    if ($dirUrl === '' || $dirUrl === '.') { $dirUrl = '/images'; }

    $cacheDirUrl = $dirUrl . '/seo-cache';
    $candidatos = array(480, 768, 1280, 1600);
    $srcset = array();
    $fallback = '';

    foreach ($candidatos as $w) {
      $cacheUrl = $cacheDirUrl . '/' . $base . '-' . $w . '.webp';
      $cacheFs = bb_perf_fs_path($cacheUrl);
      if (is_file($cacheFs)) {
        $srcset[] = $cacheUrl . ' ' . $w . 'w';
        if ($fallback === '' && $w >= 768) { $fallback = $cacheUrl; }
      }
    }

    if ($fallback === '' && !empty($srcset)) {
      $primeiro = explode(' ', $srcset[0]);
      $fallback = $primeiro[0];
    }

    if ($fallback === '') {
      $fallback = $url;
    }

    return array(
      'src' => $fallback,
      'srcset' => implode(', ', $srcset),
      'sizes' => '100vw',
      'width' => $largura,
      'height' => $altura,
      'original' => $url
    );
  }
}

$bb_img_banner_perf = $img_banner_index ?? 'banner-barbeiro.webp';
$bb_banner_perf = bb_perf_banner_data($assets_base . 'images/' . ltrim((string)$bb_img_banner_perf, '/'));

// ===== SEO / Google / Meta por tenant =====
$bb_seo_config = array();
try {
  if (isset($pdo) && $pdo instanceof PDO) {
    $bb_seo_query = $pdo->query("SELECT * FROM config ORDER BY id ASC LIMIT 1");
    $bb_seo_config = $bb_seo_query ? ($bb_seo_query->fetch(PDO::FETCH_ASSOC) ?: array()) : array();
  }
} catch (Throwable $e) {
  $bb_seo_config = array();
}

if (!function_exists('bb_seo_campo')) {
  function bb_seo_campo(array $dados, string $campo, string $fallback = ''): string {
    $valor = isset($dados[$campo]) ? trim((string)$dados[$campo]) : '';
    return $valor !== '' ? $valor : $fallback;
  }
}

if (!function_exists('bb_seo_meta_content')) {
  function bb_seo_meta_content(string $valor): string {
    $valor = trim($valor);
    if ($valor !== '' && stripos($valor, 'google-site-verification') !== false && preg_match('/content=["\']([^"\']+)["\']/i', $valor, $m)) {
      return trim((string)$m[1]);
    }
    return trim(str_replace(array('<', '>', '"', "'"), '', $valor));
  }
}

if (!function_exists('bb_seo_id_ga4')) {
  function bb_seo_id_ga4(string $valor): string {
    $valor = strtoupper(trim($valor));
    if (preg_match('/G-[A-Z0-9]+/i', $valor, $m)) return strtoupper($m[0]);
    return preg_match('/^G-[A-Z0-9]+$/', $valor) ? $valor : '';
  }
}

if (!function_exists('bb_seo_id_gtm')) {
  function bb_seo_id_gtm(string $valor): string {
    $valor = strtoupper(trim($valor));
    if (preg_match('/GTM-[A-Z0-9]+/i', $valor, $m)) return strtoupper($m[0]);
    return preg_match('/^GTM-[A-Z0-9]+$/', $valor) ? $valor : '';
  }
}

if (!function_exists('bb_seo_id_ads')) {
  function bb_seo_id_ads(string $valor): string {
    $valor = strtoupper(trim($valor));
    if (preg_match('/AW-[0-9]+/i', $valor, $m)) return strtoupper($m[0]);
    return preg_match('/^AW-[0-9]+$/', $valor) ? $valor : '';
  }
}

if (!function_exists('bb_seo_id_pixel')) {
  function bb_seo_id_pixel(string $valor): string {
    $valor = trim($valor);
    if (preg_match('/[0-9]{6,}/', $valor, $m)) return (string)$m[0];
    return '';
  }
}

if (!function_exists('bb_seo_html_extra')) {
  function bb_seo_html_extra(string $valor): string {
    $valor = trim($valor);
    return str_ireplace(array('<?php', '<?=', '<?', '?>'), '', $valor);
  }
}

$bb_host_atual = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? 'https://' : 'http://') . ($_SERVER['HTTP_HOST'] ?? '');
$bb_req_atual  = $_SERVER['REQUEST_URI'] ?? '/';
$bb_canonical_atual = $bb_host_atual . rtrim($bb_req_atual, '/');
if ($bb_canonical_atual === $bb_host_atual) { $bb_canonical_atual .= '/'; }

$bb_seo_titulo = bb_seo_campo($bb_seo_config, 'seo_titulo', (string)($nome_sistema ?? 'Agendamento e Gestão para Barbearias'));
$bb_seo_descricao = bb_seo_campo($bb_seo_config, 'seo_descricao', 'Sistema completo para barbearias: agenda online, confirmações por WhatsApp, pagamentos, relatórios e fidelidade. Organize a equipe e aumente o faturamento.');
$bb_seo_palavras = bb_seo_campo($bb_seo_config, 'seo_palavras', 'BarberBot, sistema para barbearia, agendamento barbearia, gestão de barbearia, agenda online, software barbearia');
$bb_seo_autor = bb_seo_campo($bb_seo_config, 'seo_autor', (string)($nome_sistema ?? 'Jacy Cordeiro'));
$bb_seo_robots = bb_seo_campo($bb_seo_config, 'seo_robots', 'index,follow');
$bb_seo_canonical = bb_seo_campo($bb_seo_config, 'seo_canonical', $bb_canonical_atual);
$bb_seo_og_image = bb_seo_campo($bb_seo_config, 'seo_og_image', '');
$bb_seo_google_verification = bb_seo_meta_content(bb_seo_campo($bb_seo_config, 'seo_google_verification', ''));
$bb_seo_google_analytics = bb_seo_id_ga4(bb_seo_campo($bb_seo_config, 'seo_google_analytics', ''));
$bb_seo_google_tag_manager = bb_seo_id_gtm(bb_seo_campo($bb_seo_config, 'seo_google_tag_manager', ''));


$bb_seo_head_extra = bb_seo_html_extra(bb_seo_campo($bb_seo_config, 'seo_head_extra', ''));
$bb_seo_body_extra = bb_seo_html_extra(bb_seo_campo($bb_seo_config, 'seo_body_extra', ''));
$bb_seo_gtag_loader = $bb_seo_google_analytics;
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <!-- Metas básicas -->
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <meta name="description" content="<?php echo htmlspecialchars($bb_seo_descricao, ENT_QUOTES, 'UTF-8'); ?>">
  <meta name="keywords" content="<?php echo htmlspecialchars($bb_seo_palavras, ENT_QUOTES, 'UTF-8'); ?>">
  <meta name="robots" content="<?php echo htmlspecialchars($bb_seo_robots, ENT_QUOTES, 'UTF-8'); ?>">
  <meta name="author" content="<?php echo htmlspecialchars($bb_seo_autor, ENT_QUOTES, 'UTF-8'); ?>">
  <?php if ($bb_seo_google_verification !== '') : ?>
    <meta name="google-site-verification" content="<?php echo htmlspecialchars($bb_seo_google_verification, ENT_QUOTES, 'UTF-8'); ?>">
  <?php endif; ?>
  <meta name="theme-color" content="#000000">
  <meta property="og:title" content="<?php echo htmlspecialchars($bb_seo_titulo, ENT_QUOTES, 'UTF-8'); ?>">
  <meta property="og:description" content="<?php echo htmlspecialchars($bb_seo_descricao, ENT_QUOTES, 'UTF-8'); ?>">
  <meta property="og:type" content="website">
  <meta property="og:url" content="<?php echo htmlspecialchars($bb_seo_canonical, ENT_QUOTES, 'UTF-8'); ?>">
  <?php if ($bb_seo_og_image !== '') : ?>
    <meta property="og:image" content="<?php echo htmlspecialchars($bb_seo_og_image, ENT_QUOTES, 'UTF-8'); ?>">
  <?php endif; ?>
  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?php echo htmlspecialchars($bb_seo_titulo, ENT_QUOTES, 'UTF-8'); ?>">
  <meta name="twitter:description" content="<?php echo htmlspecialchars($bb_seo_descricao, ENT_QUOTES, 'UTF-8'); ?>">
  <?php if ($bb_seo_og_image !== '') : ?>
    <meta name="twitter:image" content="<?php echo htmlspecialchars($bb_seo_og_image, ENT_QUOTES, 'UTF-8'); ?>">
  <?php endif; ?>
  <link rel="canonical" href="<?php echo htmlspecialchars($bb_seo_canonical, ENT_QUOTES, 'UTF-8'); ?>">

  <?php
    // ===== Favicon / Ícones (SEO / Google / Navegadores) =====
    // Mantém multi-tenant: usa $icone_site (vindo do banco do tenant) quando existir.
    // Evita 404 por caminho errado, tentando localizar o arquivo em pastas comuns do sistema.
    $faviconHref = '';
    $icone_site_local = trim((string)($icone_site ?? ''));

    if ($icone_site_local !== '') {
      // URL absoluta
      if (preg_match('#^https?://#i', $icone_site_local)) {
        $faviconHref = $icone_site_local;
      } elseif (strpos($icone_site_local, '/') === 0) {
        // Caminho absoluto no domínio (ex.: /sistema/painel/img/favicon.png)
        $faviconHref = $icone_site_local;
      } else {
        // Nome de arquivo (ex.: favicon.png)
        $candidatos = array(
          '/images/' . $icone_site_local,
          '/sistema/painel/img/' . $icone_site_local,
          '/sistema/img/' . $icone_site_local,
          '/img/' . $icone_site_local,
        );
        foreach ($candidatos as $cand) {
          $fs = ($_SERVER['DOCUMENT_ROOT'] ?? __DIR__) . $cand;
          if (is_file($fs)) { $faviconHref = $cand; break; }
        }
      }
    }

    // Fallbacks padrões (mantém independente do tenant quando não houver ícone configurado)
    if ($faviconHref === '') {
      $fallbacks = array(
        '/favicon.ico',
        '/favicon.png',
        '/sistema/img/favicon.ico',
        '/sistema/img/logo.png',
      );
      foreach ($fallbacks as $cand) {
        $fs = ($_SERVER['DOCUMENT_ROOT'] ?? __DIR__) . $cand;
        if (is_file($fs)) { $faviconHref = $cand; break; }
      }
    }

    // Se ainda assim não achou, mantém o comportamento antigo (pode existir em /images/)
    if ($faviconHref === '') {
      $faviconHref = $assets_base . 'images/' . $icone_site_local;
    }

    // Descobre tamanho (para o Google identificar melhor)
    $favSizesAttr = '';
    if ($faviconHref !== '' && !preg_match('#^https?://#i', $faviconHref) && strpos($faviconHref, '/') === 0) {
      $fsFav = ($_SERVER['DOCUMENT_ROOT'] ?? __DIR__) . $faviconHref;
      if (is_file($fsFav)) {
        $dimFav = @getimagesize($fsFav);
        if (is_array($dimFav) && !empty($dimFav[0]) && !empty($dimFav[1]) && (int)$dimFav[0] === (int)$dimFav[1]) {
          $favSizesAttr = (int)$dimFav[0] . 'x' . (int)$dimFav[1];
        }
      }
    }

    $faviconEsc = htmlspecialchars($faviconHref, ENT_QUOTES, 'UTF-8');
  ?>
  <link rel="icon" href="<?php echo $faviconEsc; ?>"<?php echo $favSizesAttr ? ' sizes="'.htmlspecialchars($favSizesAttr, ENT_QUOTES, 'UTF-8').'"' : ' sizes="any"'; ?>>
  <link rel="shortcut icon" href="<?php echo $faviconEsc; ?>">
  <?php if (preg_match('/\.png$/i', $faviconHref)) : ?>
    <link rel="icon" type="image/png" href="<?php echo $faviconEsc; ?>"<?php echo $favSizesAttr ? ' sizes="'.htmlspecialchars($favSizesAttr, ENT_QUOTES, 'UTF-8').'"' : ''; ?>>
  <?php endif; ?>

  <?php
    // apple-touch-icon (opcional) se existir no servidor
    $appleTouch = '';
    $candApple = array('/apple-touch-icon.png', '/images/apple-touch-icon.png');
    foreach ($candApple as $c) {
      $fs = ($_SERVER['DOCUMENT_ROOT'] ?? __DIR__) . $c;
      if (is_file($fs)) { $appleTouch = $c; break; }
    }
  ?>
  <?php if ($appleTouch !== '') : ?>
    <link rel="apple-touch-icon" href="<?php echo htmlspecialchars($appleTouch, ENT_QUOTES, 'UTF-8'); ?>">
  <?php endif; ?>
  <title><?php echo htmlspecialchars($bb_seo_titulo, ENT_QUOTES, 'UTF-8'); ?></title>

  <?php if ($bb_seo_google_tag_manager !== '') : ?>
    <!-- Google Tag Manager por tenant -->
    <script>
      (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','<?php echo htmlspecialchars($bb_seo_google_tag_manager, ENT_QUOTES, 'UTF-8'); ?>');
    </script>
  <?php endif; ?>

  <?php if ($bb_seo_gtag_loader !== '') : ?>
    <!-- Google Analytics / Google Ads por tenant -->
    <script async src="https://www.googletagmanager.com/gtag/js?id=<?php echo htmlspecialchars($bb_seo_gtag_loader, ENT_QUOTES, 'UTF-8'); ?>"></script>
    <script>
      window.dataLayer = window.dataLayer || [];
      function gtag(){dataLayer.push(arguments);}
      gtag('js', new Date());
      <?php if ($bb_seo_google_analytics !== '') : ?>
        gtag('config', '<?php echo htmlspecialchars($bb_seo_google_analytics, ENT_QUOTES, 'UTF-8'); ?>');
      <?php endif; ?>
    </script>
  <?php endif; ?>

  <?php if ($bb_seo_head_extra !== '') { echo "\n" . $bb_seo_head_extra . "\n"; } ?>


  <!-- Conexões adiantadas -->
  <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="dns-prefetch" href="//fonts.googleapis.com">
  <link rel="dns-prefetch" href="//fonts.gstatic.com">
  <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>

  <!-- Google Fonts sem bloquear o primeiro carregamento -->
  <link rel="preload" as="style" href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap">
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet" media="print" onload="this.media='all'">
  <noscript><link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet"></noscript>


  <!-- Preload do banner global. A página /protese possui banner próprio e não carrega esta imagem duplicada. -->
  <?php if (!$bb_is_protese_page): ?>
    <?php if (!empty($bb_banner_perf['srcset'])): ?>
      <link rel="preload" as="image"
            href="<?php echo htmlspecialchars($bb_banner_perf['src'], ENT_QUOTES, 'UTF-8'); ?>"
            imagesrcset="<?php echo htmlspecialchars($bb_banner_perf['srcset'], ENT_QUOTES, 'UTF-8'); ?>"
            imagesizes="<?php echo htmlspecialchars($bb_banner_perf['sizes'], ENT_QUOTES, 'UTF-8'); ?>"
            fetchpriority="high">
    <?php else: ?>
      <link rel="preload" as="image"
            href="<?php echo htmlspecialchars($bb_banner_perf['src'], ENT_QUOTES, 'UTF-8'); ?>"
            fetchpriority="high">
    <?php endif; ?>
  <?php endif; ?>

  <!-- CSS principal -->
  <link rel="preload" as="style" href="<?php echo $assets_base; ?>css/bootstrap.css">
  <link rel="stylesheet" href="<?php echo $assets_base; ?>css/bootstrap.css">
  <link rel="preload" as="style" href="<?php echo $assets_base; ?>css/style.css">
  <link rel="stylesheet" href="<?php echo $assets_base; ?>css/style.css">
  <link rel="preload" as="style" href="<?php echo $assets_base; ?>css/responsive.css">
  <link rel="stylesheet" href="<?php echo $assets_base; ?>css/responsive.css">

  <!-- CSS adicionais -->
  <link rel="preload" as="style" href="<?php echo $assets_base; ?>css/pricing-table.css">
  <link rel="stylesheet" href="<?php echo $assets_base; ?>css/pricing-table.css" media="print" onload="this.media='all'">
  <link rel="preload" as="style" href="<?php echo $assets_base; ?>css/pricing.css">
  <link rel="stylesheet" href="<?php echo $assets_base; ?>css/pricing.css" media="print" onload="this.media='all'">
  <link rel="preload" as="style" href="<?php echo $assets_base; ?>css/font-awesome.min.css">
  <link rel="stylesheet" href="<?php echo $assets_base; ?>css/font-awesome.min.css" media="print" onload="this.media='all'">

  <!-- Owl Carousel -->
  <link rel="preload" as="style" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css" media="print" onload="this.media='all'">

  <!-- Fallback sem JS -->
  <noscript>
    <link rel="stylesheet" href="<?php echo $assets_base; ?>css/bootstrap.css">
    <link rel="stylesheet" href="<?php echo $assets_base; ?>css/style.css">
    <link rel="stylesheet" href="<?php echo $assets_base; ?>css/pricing-table.css">
    <link rel="stylesheet" href="<?php echo $assets_base; ?>css/pricing.css">
    <link rel="stylesheet" href="<?php echo $assets_base; ?>css/font-awesome.min.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/OwlCarousel2/2.3.4/assets/owl.carousel.min.css">
    <link rel="stylesheet" href="<?php echo $assets_base; ?>css/responsive.css">
  </noscript>

  <!-- CSS rápido para evitar CLS -->
  <style>

    :root{ --bb-header-h:86px; }
    body{ font-family:'Poppins', Arial, Helvetica, sans-serif; text-rendering:optimizeSpeed; }

    /*
      Cabeçalho global do marketplace/TENANT:
      - precisa ficar acima de home, produtos e serviços;
      - não pode ficar preso dentro do stacking context da hero_area;
      - páginas internas podem tentar zerar .sub_page .hero_area, por isso usamos !important.
    */
    .hero_area{
      position:relative;
      min-height:100svh;
      background:#020202;
      overflow:visible !important;
      isolation:auto !important;
      z-index:2147482000 !important;
    }
    .sub_page .hero_area{ min-height:var(--bb-header-h) !important; }
    .hero_area .hero_bg_box{
      position:absolute;
      inset:0;
      width:100%;
      height:100%;
      overflow:hidden;
      z-index:-1;
      background:#020202;
      pointer-events:none;
    }
    .hero_area .hero_bg_box picture{ display:block; width:100%; height:100%; }
    .hero_area .hero_bg_box img{ width:100%; height:100%; object-fit:cover; object-position:center top; display:block; transform:translateZ(0); }
    .hero_bg_box img{ display:block; width:100%; height:auto; }

    .hero_area .slider_section{ min-height:calc(100svh - var(--bb-header-h)); padding:44px 0 70px 0; }
    .hero_area .slider_section .carousel-inner,.hero_area .slider_section .carousel-item{ min-height:360px; }
    .hero_area .slider_section .detail-box{ min-height:260px; display:flex; flex-direction:column; justify-content:center; }
    .product_section,.service,.client_section,.faq-section,.contact_section,.simulador_section{ content-visibility:auto; contain-intrinsic-size:1px 950px; }
    .about_section{ content-visibility:auto; contain-intrinsic-size:1px 760px; }
    img{ max-width:100%; }

    html,body{ max-width:100%; overflow-x:hidden; }
    .header_section{
      position:fixed !important;
      top:0 !important;
      left:0 !important;
      right:0 !important;
      width:100% !important;
      z-index:2147483000 !important;
      background:#020202 !important;
      backdrop-filter:saturate(120%) blur(2px);
      -webkit-backdrop-filter:saturate(120%) blur(2px);
      transition:box-shadow .2s ease;
      overflow:visible !important;
      transform:none !important;
      opacity:1 !important;
    }
    .header_section.header--scrolled{ box-shadow:0 8px 22px rgba(0,0,0,.25); }
    .header_section .container,
    .header_section .navbar,
    .header_section .navbar-collapse,
    .header_section .navbar-nav,
    .header_section .nav-item{
      overflow:visible !important;
      z-index:2147483000 !important;
    }
    .header_section .navbar{ position:relative !important; }
    .header_section .nav-item{ position:relative !important; }
    .header_section .dropdown-menu{
      position:absolute !important;
      z-index:2147483002 !important;
      background:#111 !important;
      border:1px solid rgba(255,255,255,.12);
      box-shadow:0 16px 30px rgba(0,0,0,.45);
    }
    .header_section .dropdown-menu a{ color:#fff !important; }
    .header_section .dropdown-menu a:hover{ background:#222 !important; color:#fff !important; }
    .bb-header-spacer{ height:var(--bb-header-h) !important; width:100%; display:block; flex:0 0 var(--bb-header-h); }
    body.has-sticky{ padding-top:0 !important; }

    @media (min-width: 992px){
      .header_section .navbar-collapse{ position:static !important; overflow:visible !important; }
      .header_section .dropdown:hover > .dropdown-menu{ display:block; }
    }

    @media (max-width: 991.98px){
      :root{ --bb-header-h:72px; }
      .hero_area{ min-height:100svh; }
      .sub_page .hero_area{ min-height:var(--bb-header-h) !important; }
      .hero_area .slider_section{ min-height:calc(100svh - var(--bb-header-h)); padding:28px 0 42px 0; }
      .hero_area .slider_section .carousel-inner,.hero_area .slider_section .carousel-item{ min-height:330px; }
      .hero_area .slider_section .detail-box{ min-height:255px; padding-top:12px; }

      .header_section .navbar-collapse{
        position:fixed !important;
        top:var(--bb-header-h) !important;
        left:0 !important;
        right:0 !important;
        width:100vw !important;
        z-index:2147483001 !important;
        background:#020202 !important;
        padding:14px 22px 22px;
        max-height:calc(100vh - var(--bb-header-h));
        overflow-y:auto !important;
        overflow-x:hidden !important;
        box-shadow:0 18px 34px rgba(0,0,0,.55);
      }
      .header_section .navbar-collapse:not(.show){ display:none !important; }
      .header_section .navbar-collapse.collapsing{ display:block !important; height:auto !important; overflow:hidden !important; }
      .header_section .navbar-collapse.show{ display:block !important; }
      .header_section .navbar-nav{ width:100%; }
      .header_section .navbar-nav .nav-link{ padding:11px 0; }
      .header_section .dropdown-menu{
        position:static !important;
        float:none !important;
        display:none;
        width:100%;
        margin:6px 0 10px;
        background:#111 !important;
        border:1px solid rgba(255,255,255,.12);
        box-shadow:none;
      }
      .header_section .dropdown-menu.show,
      .header_section .dropdown.show > .dropdown-menu{ display:block !important; }
    }

    header, .header, nav, .navbar { z-index:2147483000 !important; }
    .modal-backdrop { z-index:2147483010 !important; }
    .modal { z-index:2147483020 !important; }
    .modal-dialog { z-index:2147483030 !important; }
    body.modal-open { overflow:hidden !important; }
  </style>
  <?php
    @require_once __DIR__ . '/config/recaptcha.php';
  ?>
  <?php
    // ============== reCAPTCHA v2/v3 por contexto ==============
    // MASTER: header.php usa a config global. TENANT: cabecalho.php usa a config do banco individual.
    $bb_recaptcha_tipo_header = defined('RECAPTCHA_TIPO') ? (string)RECAPTCHA_TIPO : 'v3';
    $bb_recaptcha_site_key_header = defined('RECAPTCHA_SITE_KEY') ? (string)RECAPTCHA_SITE_KEY : '';
    if ($bb_recaptcha_site_key_header !== ''): ?>
      <script>
        (function(){
          window.bbRecaptchaTipo = <?php echo json_encode($bb_recaptcha_tipo_header, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
          window.bbRecaptchaSiteKey = <?php echo json_encode($bb_recaptcha_site_key_header, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
          window.bbRecaptchaLoading = false;
          window.bbRecaptchaLoaded = false;
          window.bbRecaptchaCallbacks = window.bbRecaptchaCallbacks || [];

          window.bbRunRecaptchaCallbacks = function(){
            window.bbRecaptchaLoaded = true;
            window.bbRecaptchaLoading = false;
            var lista = window.bbRecaptchaCallbacks.slice();
            window.bbRecaptchaCallbacks = [];
            lista.forEach(function(cb){ try { if (typeof cb === 'function') cb(); } catch(e){} });
          };

          window.bbRecaptchaV2Onload = function(){
            window.bbRunRecaptchaCallbacks();
            document.querySelectorAll('form[data-recaptcha-action]').forEach(function(form){
              window.bbPrepareRecaptchaForm(form);
            });
          };

          window.bbLoadRecaptcha = function(callback){
            if (!window.bbRecaptchaSiteKey) { return; }
            if (typeof callback === 'function') window.bbRecaptchaCallbacks.push(callback);
            if (window.bbRecaptchaLoaded) { window.bbRunRecaptchaCallbacks(); return; }
            if (window.bbRecaptchaLoading) { return; }

            window.bbRecaptchaLoading = true;
            var existente = document.querySelector('script[data-bb-recaptcha-script="1"]');
            if (existente) { return; }

            var s = document.createElement('script');
            s.defer = true;
            s.async = true;
            s.setAttribute('data-bb-recaptcha-script', '1');

            if (window.bbRecaptchaTipo === 'v2') {
              s.src = 'https://www.google.com/recaptcha/api.js?onload=bbRecaptchaV2Onload&render=explicit';
            } else {
              s.src = 'https://www.google.com/recaptcha/api.js?render=' + encodeURIComponent(window.bbRecaptchaSiteKey);
              s.onload = window.bbRunRecaptchaCallbacks;
            }

            s.onerror = function(){
              window.bbRecaptchaLoading = false;
              window.bbRecaptchaCallbacks = [];
            };

            document.head.appendChild(s);
          };

          window.bbPrepareRecaptchaForm = function(form){
            if (!form || !form.getAttribute('data-recaptcha-action')) { return; }

            var action = form.getAttribute('data-recaptcha-action') || 'generic';
            var token = form.querySelector('input[name="recaptcha_token"]');
            var actionEl = form.querySelector('input[name="recaptcha_action"]');

            if (!token) {
              token = document.createElement('input');
              token.type = 'hidden';
              token.name = 'recaptcha_token';
              form.appendChild(token);
            }

            if (!actionEl) {
              actionEl = document.createElement('input');
              actionEl.type = 'hidden';
              actionEl.name = 'recaptcha_action';
              form.appendChild(actionEl);
            }

            actionEl.value = action;

            window.bbLoadRecaptcha(function(){
              if (!window.grecaptcha) { return; }

              if (window.bbRecaptchaTipo === 'v2') {
                var box = form.querySelector('[data-bb-recaptcha-v2="1"]');
                if (!box) {
                  box = document.createElement('div');
                  box.setAttribute('data-bb-recaptcha-v2', '1');
                  box.style.margin = '12px 0';
                  var submit = form.querySelector('button[type="submit"], input[type="submit"]');
                  if (submit && submit.parentNode) {
                    submit.parentNode.insertBefore(box, submit);
                  } else {
                    form.appendChild(box);
                  }
                }

                if (!box.getAttribute('data-widget-id') && typeof grecaptcha.render === 'function') {
                  var widgetId = grecaptcha.render(box, {
                    sitekey: window.bbRecaptchaSiteKey,
                    callback: function(resp){ token.value = resp || ''; },
                    'expired-callback': function(){ token.value = ''; },
                    'error-callback': function(){ token.value = ''; }
                  });
                  box.setAttribute('data-widget-id', String(widgetId));
                }
                return;
              }

              if (typeof grecaptcha.execute === 'function') {
                grecaptcha.ready(function(){
                  grecaptcha.execute(window.bbRecaptchaSiteKey, { action: action })
                    .then(function(t){ token.value = t || ''; })
                    .catch(function(){ token.value = ''; });
                });
              }
            });
          };

          document.addEventListener('focusin', function(e){
            var form = e.target && e.target.closest ? e.target.closest('form[data-recaptcha-action]') : null;
            if (form) { window.bbPrepareRecaptchaForm(form); }
          });

          document.addEventListener('submit', function(e){
            var form = e.target;
            if (!form || !form.matches || !form.matches('form[data-recaptcha-action]')) { return; }

            window.bbPrepareRecaptchaForm(form);

            if (window.bbRecaptchaTipo === 'v2') {
              var token = form.querySelector('input[name="recaptcha_token"]');
              if (!token || token.value === '') {
                e.preventDefault();
                alert('Confirme o reCAPTCHA para continuar.');
              }
            }
          }, true);
        })();
      </script>
  <?php endif; ?>
  <!-- ============ fim reCAPTCHA v2/v3 ============ -->

  <!-- Fix de mídia em URL bonita (/loja/<slug>/...) -->
  <script>
    (function(){
      function startsWith(str, prefix){
        return str && str.indexOf(prefix) === 0;
      }
      function isAbsolute(u){
        return !u || startsWith(u,'http://') || startsWith(u,'https://') || startsWith(u,'//') || startsWith(u,'/') || startsWith(u,'data:');
      }
      function absolutize(u){
        if (!u || isAbsolute(u)) return u;
        // corrige apenas caminhos do próprio sistema
        if (startsWith(u,'sistema/') || startsWith(u,'loja/') || startsWith(u,'images/')) {
          return '/' + u.replace(/^\/+/, '');
        }
        return u;
      }
      function fixAttr(el, attr){
        var v = el.getAttribute(attr);
        if (!v) return;
        var nv = absolutize((v + '').replace(/^\s+|\s+$/g,''));
        if (nv && nv !== v) el.setAttribute(attr, nv);
      }
      function fixSrcset(el){
        var v = el.getAttribute('srcset');
        if (!v) return;
        // srcset: "img1 1x, img2 2x"
        var parts = v.split(',').map(function(p){ return (p + '').replace(/^\s+|\s+$/g,''); }).filter(function(x){ return x; });
        var out = parts.map(function(p){
          var seg = p.split(/\s+/);
          var url = seg[0] || '';
          seg[0] = absolutize(url);
          return seg.join(' ');
        }).join(', ');
        if (out && out !== v) el.setAttribute('srcset', out);
      }
      if (document && document.addEventListener) {
        document.addEventListener('DOMContentLoaded', function(){
          var imgs = document.querySelectorAll('img[src]');
          for (var i=0;i<imgs.length;i++){ fixAttr(imgs[i],'src'); fixSrcset(imgs[i]); }
          var srcs = document.querySelectorAll('source[src]');
          for (var j=0;j<srcs.length;j++){ fixAttr(srcs[j],'src'); fixSrcset(srcs[j]); }
          var vids = document.querySelectorAll('video[src]');
          for (var k=0;k<vids.length;k++){ fixAttr(vids[k],'src'); }
        });
      }
    })();
  </script>
</head>

<body class="sub_page">

  <?php if ($bb_seo_google_tag_manager !== '') : ?>
    <!-- Google Tag Manager noscript por tenant -->
    <noscript><iframe src="https://www.googletagmanager.com/ns.html?id=<?php echo htmlspecialchars($bb_seo_google_tag_manager, ENT_QUOTES, 'UTF-8'); ?>" height="0" width="0" style="display:none;visibility:hidden" title="Google Tag Manager"></iframe></noscript>
  <?php endif; ?>

  <?php if ($bb_seo_body_extra !== '') { echo "\n" . $bb_seo_body_extra . "\n"; } ?>

  <div class="hero_area">
    <?php if (!$bb_is_protese_page): ?>
      <div class="hero_bg_box">
        <picture>
          <?php if (!empty($bb_banner_perf['srcset'])): ?>
            <source
              type="image/webp"
              srcset="<?php echo htmlspecialchars($bb_banner_perf['srcset'], ENT_QUOTES, 'UTF-8'); ?>"
              sizes="<?php echo htmlspecialchars($bb_banner_perf['sizes'], ENT_QUOTES, 'UTF-8'); ?>">
          <?php endif; ?>
          <img
            src="<?php echo htmlspecialchars($bb_banner_perf['src'], ENT_QUOTES, 'UTF-8'); ?>"
            alt="Banner — <?php echo htmlspecialchars($nome_sistema ?? 'Jacy Cabeleireiro', ENT_QUOTES, 'UTF-8'); ?>"
            width="<?php echo (int)$bb_banner_perf['width']; ?>"
            height="<?php echo (int)$bb_banner_perf['height']; ?>"
            loading="eager"
            decoding="async"
            fetchpriority="high">
        </picture>
      </div>
    <?php endif; ?>

    <!-- header section start -->
    <header class="header_section">
      <div class="container">
        <nav class="navbar navbar-expand-lg custom_nav-container ">
          <a class="navbar-brand" href="home">
            <?php
              $logoPngUrl   = '/sistema/img/logo.png';
              $logoWebpUrl  = '/sistema/img/logo.webp';
              $logoWebpFile = $_SERVER['DOCUMENT_ROOT'] . $logoWebpUrl;
            ?>
            <picture>
              <?php if (is_file($logoWebpFile)) : ?>
                <source type="image/webp" srcset="<?php echo $logoWebpUrl; ?>?v=1">
              <?php endif; ?>
              <img src="<?php echo $logoPngUrl; ?>?v=1" width="80" height="40" style="margin-right:3px;height:auto" alt="Logo">
            </picture>
          </a>

          <button class="navbar-toggler" type="button" data-toggle="collapse" data-target="#navbarSupportedContent"
                  aria-controls="navbarSupportedContent" aria-expanded="false" aria-label="Alternar navegação">
            <span class=""></span>
          </button>

          <div class="collapse navbar-collapse" id="navbarSupportedContent">
            <ul class="navbar-nav">
              <li class="nav-item active">
                <a class="nav-link active" href="<?php echo htmlspecialchars($link_agendamentos, ENT_QUOTES, 'UTF-8'); ?>" target="_blank">
                  <span class="sr-only">(current)</span>📅 Agendamentos
                </a>
              </li>

              <li class="nav-item">
                <a class="nav-link" href="<?php echo htmlspecialchars($link_assinatura, ENT_QUOTES, 'UTF-8'); ?>" target="_blank"> 📝 Assinaturas</a>
              </li>

              <li class="nav-item dropdown">
                <a class="nav-link dropdown-toggle" data-toggle="dropdown" href="#" role="button" aria-haspopup="true" aria-expanded="false">💼 Serviços</a>
                <div class="dropdown-menu">
                  <a class="dropdown-item" href="<?php echo htmlspecialchars($link_servicos, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">Serviços</a>
                </div>
              </li>

              <li class="nav-item">
                <a class="nav-link" href="<?php echo htmlspecialchars($link_produtos, ENT_QUOTES, 'UTF-8'); ?>" target="_blank">🛍️ Produtos</a>
              </li>

              <li class="nav-item">
                <a title="Cadastro público do cliente" class="nav-link" href="<?php echo htmlspecialchars($link_cadastro_cliente, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                  <i class="fa fa-user-plus" aria-hidden="true"></i> Cadastro
                </a>
              </li>

              <li class="nav-item">
                <a title="Acesso exclusivo ao painel do cliente" class="nav-link" href="<?php echo htmlspecialchars($link_acessar_cliente, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                  <i class="fa fa-users" aria-hidden="true"></i> 
                </a>
              </li>

              <li class="nav-item">
                <a title="Acesso exclusivo ao painel do profissional" class="nav-link" href="<?php echo htmlspecialchars($link_acessar_prof, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                  <i class="fa fa-user" aria-hidden="true"></i>
                </a>
              </li>

              <li class="nav-item">
                <a title="Ver Instagram" class="nav-link" href="<?php echo htmlspecialchars($instagram_sistema ?? '#', ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                  <i class="fa fa-instagram" aria-hidden="true"></i>
                </a>
              </li>
            </ul>
          </div>

        </nav>
      </div>
    </header>
    <!-- end header section -->

    <div class="bb-header-spacer" aria-hidden="true"></div>

    <script>
    (function(){
      function bbAtualizarAlturaCabecalho(){
        var header = document.querySelector('.header_section');
        if(!header){ return; }

        var h = Math.ceil(header.getBoundingClientRect().height || 0);
        if(h > 0){
          document.documentElement.style.setProperty('--bb-header-h', h + 'px');
        }
      }

      function bbBootstrapDisponivel(){
        return !!(window.jQuery && window.jQuery.fn && (typeof window.jQuery.fn.collapse === 'function' || typeof window.jQuery.fn.dropdown === 'function'));
      }

      document.addEventListener('DOMContentLoaded', function(){
        bbAtualizarAlturaCabecalho();
        window.addEventListener('resize', bbAtualizarAlturaCabecalho);
        window.addEventListener('orientationchange', function(){ setTimeout(bbAtualizarAlturaCabecalho, 250); });

        var toggler = document.querySelector('.header_section .navbar-toggler');
        var collapse = document.getElementById('navbarSupportedContent');

        function bbFecharMenuAoVoltarParaDesktop(){
          if(!toggler || !collapse || window.innerWidth < 992){ return; }

          collapse.classList.remove('show', 'collapsing');
          collapse.style.height = '';
          toggler.classList.add('collapsed');
          toggler.setAttribute('aria-expanded', 'false');

          var dropdownsAbertos = collapse.querySelectorAll('.dropdown.show, .dropdown-menu.show');
          for(var i = 0; i < dropdownsAbertos.length; i++){
            dropdownsAbertos[i].classList.remove('show');
          }
        }

        bbFecharMenuAoVoltarParaDesktop();
        window.addEventListener('resize', bbFecharMenuAoVoltarParaDesktop);

        if(toggler && collapse){
          toggler.addEventListener('click', function(e){
            if(bbBootstrapDisponivel()){
              return;
            }

            e.preventDefault();
            var aberto = collapse.classList.contains('show');
            collapse.classList.toggle('show', !aberto);
            toggler.classList.toggle('collapsed', aberto);
            toggler.setAttribute('aria-expanded', aberto ? 'false' : 'true');
          });
        }

        var dropdowns = document.querySelectorAll('.header_section .dropdown-toggle');
        for(var i = 0; i < dropdowns.length; i++){
          dropdowns[i].addEventListener('click', function(e){
            if(bbBootstrapDisponivel()){ return; }

            e.preventDefault();
            var item = this.closest('.dropdown');
            var menu = item ? item.querySelector('.dropdown-menu') : null;
            if(!menu){ return; }

            item.classList.toggle('show');
            menu.classList.toggle('show');
          });
        }
      });
    })();
    </script>
