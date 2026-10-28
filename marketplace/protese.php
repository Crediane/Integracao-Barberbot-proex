<?php
/**
 * Página pública de Prótese Capilar.
 *
 * cabecalho.php concentra sessão, conexão, segurança, HTML inicial,
 * recursos globais e menu. rodape.php concentra os recursos globais
 * finais e o fechamento do documento.
 */
require_once __DIR__ . '/cabecalho.php';

/* ============================================================
   HELPERS
   ============================================================ */
function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function d($s) { return preg_replace('/\D+/', '', (string)$s); }

function money_br($v) {
  if ($v === null || $v === '') return '';
  if (is_string($v) && preg_match('/^\d+(?:[.,]\d+)?$/', $v)) {
    $v = str_replace(',', '.', $v);
  }
  return 'R$ ' . number_format((float)$v, 2, ',', '.');
}

function table_exists(?PDO $pdo, $t) {
  if (!$pdo) return false;
  try {
    $st = $pdo->prepare('SHOW TABLES LIKE :t');
    $st->execute([':t' => $t]);
    return (bool)$st->fetch(PDO::FETCH_NUM);
  } catch (Throwable $e) {
    return false;
  }
}

function col_exists(PDO $pdo, $t, $c) {
  try {
    $st = $pdo->prepare("SHOW COLUMNS FROM `$t` LIKE :c");
    $st->execute([':c' => $c]);
    return (bool)$st->fetch(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    return false;
  }
}

function coalesce_expr(PDO $pdo, $t, $cands, $alias) {
  $cands = (array)$cands;
  $ok = [];
  foreach ($cands as $c) {
    if (col_exists($pdo, $t, $c)) $ok[] = $c;
  }

  if (!empty($ok)) {
    $parts = [];
    foreach ($ok as $c) {
      $c = str_replace('`', '', $c);
      $parts[] = '`' . $c . '`';
    }
    $cols = implode(',', $parts);
    return "COALESCE($cols) AS $alias";
  }

  return "NULL AS $alias";
}

function official_agenda_link(): string {
  return '/agendamentos/';
}

function normalize_agenda_link(string $url): string {
  $url = trim($url);
  if ($url === '') return official_agenda_link();

  $normalized = rtrim(strtolower($url), '/');
  $agendaAliases = [
    '/agendamentos',
    'agendamentos',
    'https://jacycabeleireiro.com/agendamentos',
    'http://jacycabeleireiro.com/agendamentos',
    'https://agenda.jacycabeleireiro.com',
    'http://agenda.jacycabeleireiro.com',
    'https://agenda.jacycabeleireiro.com/agendamentos',
    'http://agenda.jacycabeleireiro.com/agendamentos',
  ];

  if (in_array($normalized, $agendaAliases, true)) {
    return official_agenda_link();
  }

  return $url;
}

function canonical_link($u) {
  $u = trim((string)$u);
  if ($u === '' || $u === '#') return '';

  if (preg_match('#^(https?:)?//#i', $u)) return normalize_agenda_link($u);
  if ($u[0] === '/') return normalize_agenda_link($u);
  if (preg_match('/^[\w.-]+\.[a-z]{2,}(\/.*)?$/i', $u)) return normalize_agenda_link('https://' . $u);

  return normalize_agenda_link('/' . ltrim($u, '/'));
}

function yt_id(string $urlOrId): string {
  $u = trim($urlOrId);
  if ($u === '') return '';

  if (preg_match('~^(?:https?://)?(?:www\.)?(?:youtube\.com/(?:watch\?v=|embed/)|youtu\.be/)([A-Za-z0-9_-]{6,})~i', $u, $m)) {
    return $m[1];
  }

  if (preg_match('~^[A-Za-z0-9_-]{6,}$~', $u)) return $u;
  return '';
}

function fix_path(string $raw): string {
  $raw = trim($raw);
  if ($raw === '' || $raw === '#') return '';
  if (preg_match('#^(?:https?:)?//#i', $raw) || strncmp($raw, 'data:', 5) === 0) return $raw;
  if ($raw[0] === '/') return $raw;

  $cands = [];
  if (preg_match('#^(paginas/|site/|uploads_)#i', $raw)) {
    $cands[] = '/sistema/painel/' . $raw;
    $cands[] = '/' . $raw;
  }

  if (defined('UP')) $cands[] = rtrim(UP, '/') . '/' . ltrim($raw, '/');
  $cands[] = '/sistema/painel/paginas/site/' . ltrim($raw, '/');

  foreach ($cands as $u) {
    $abs = $_SERVER['DOCUMENT_ROOT'] . $u;
    if (is_file($abs)) return $u;
  }

  return $cands[0] ?? '/' . $raw;
}

function resolve_media_src(string $raw): array {
  $u = fix_path($raw);
  $path = parse_url($u, PHP_URL_PATH) ?? $u;
  $isVideo = preg_match('/\.(mp4|webm|ogg)$/i', $path);
  return $isVideo ? ['img' => '', 'video' => $u] : ['img' => $u, 'video' => ''];
}

/* ============================================================
   SETTINGS
   ============================================================ */
function loadSettings(?PDO $pdo) {
  $out = [
    'site'       => 'https://jacycabeleireiro.com/',
    'nome'       => 'Jacy Cabeleireiro',
    'descricao'  => 'Cortes, prótese capilar, mechas e escovas com técnica e acabamento profissional.',
    'whatsapp'   => '5545999882100',
    'telefone'   => '(45) 99988-2100',
    'email'      => 'jacycordeiro1@gmail.com',
    'endereco'   => 'Rio Grande do Sul, 2151 - Cascavel/PR',
    'uploads'    => '/sistema/painel/paginas/site/uploads_banners',
    'logo_png'   => '/sistema/img/logo.png',
    'logo_webp'  => '/sistema/img/logo.webp',
    'agenda_url' => official_agenda_link(),
  ];

  if (!$pdo) return $out;

  $tabs = ['site_config', 'configuracoes', 'empresa', 'institucional', 'config_site', 'settings', 'config'];
  foreach ($tabs as $t) {
    if (!table_exists($pdo, $t)) continue;

    try {
      $nome    = coalesce_expr($pdo, $t, ['nome', 'titulo', 'razao_social', 'nome_fantasia'], 'nome');
      $desc    = coalesce_expr($pdo, $t, ['descricao', 'descricao_site', 'sobre', 'texto'], 'descricao');
      $wpp     = coalesce_expr($pdo, $t, ['whatsapp', 'wpp', 'telefone2'], 'whatsapp');
      $tel     = coalesce_expr($pdo, $t, ['telefone', 'fone'], 'telefone');
      $mail    = coalesce_expr($pdo, $t, ['email', 'mail', 'contato'], 'email');
      $end     = coalesce_expr($pdo, $t, ['endereco', 'endereço', 'address'], 'endereco');
      $logo    = coalesce_expr($pdo, $t, ['logo_webp', 'logo', 'logo_png'], 'logo');
      $uploads = coalesce_expr($pdo, $t, ['uploads_base', 'uploads', 'upload_dir'], 'uploads');
      $agenda  = coalesce_expr($pdo, $t, ['agenda', 'agenda_url', 'link_agenda'], 'agenda_url');
      $site    = coalesce_expr($pdo, $t, ['site', 'url', 'dominio'], 'site');

      $sql = "SELECT $nome,$desc,$wpp,$tel,$mail,$end,$logo,$uploads,$agenda,$site FROM `$t` LIMIT 1";
      $r = $pdo->query($sql)->fetch(PDO::FETCH_ASSOC);

      if ($r) {
        foreach ($r as $k => $v) {
          if ($v !== null && $v !== '') {
            $out[$k] = $v;
          }
        }
        break;
      }
    } catch (Throwable $e) {}
  }

  $out['agenda_url'] = official_agenda_link();
  return $out;
}

$SET = loadSettings($pdo);
$AGENDA_LINK = official_agenda_link();
$SET['agenda_url'] = $AGENDA_LINK;

$WPP = 'https://wa.me/' . (strpos($SET['whatsapp'], '55') === 0 ? $SET['whatsapp'] : '55' . d($SET['whatsapp']));
$SERVICOS_LINK = '/servicos';
$BARBEIRO_LINK = '/corte-cabelo';
$PRODUTOS_LINK = '/produtos';
$INSTAGRAM_LINK = !empty($instagram_sistema) ? $instagram_sistema : $WPP;
if (!defined('UP')) define('UP', rtrim($SET['uploads'] ?? '/sistema/public/uploads/site/', '/') . '/');

/* ============================================================
   BANNER FIXO DO TOPO
   - Mantém a origem dos dados do sistema.
   - Exibe somente a primeira imagem válida.
   - Não usa vídeo, carrossel, setas ou rotação automática.
   ============================================================ */
function loadFixedBanner(?PDO $pdo): array {
  $fallback = [
    'img'    => UP . 'banner-1.webp',
    'titulo' => 'Prótese Capilar Masculino',
    'texto'  => 'Técnica discreta e com acabamento profissional.',
    'link'   => official_agenda_link()
  ];

  if (!$pdo) return $fallback;

  $table = table_exists($pdo, 'site_banners_data') ? 'site_banners_data'
         : (table_exists($pdo, 'site_banners') ? 'site_banners'
         : (table_exists($pdo, 'arquivos') ? 'arquivos' : null));

  if (!$table) return $fallback;

  try {
    $isData = ($table === 'site_banners_data');

    $midia  = $isData
      ? coalesce_expr($pdo, $table, ['arquivo', 'src', 'caminho', 'midia'], 'src')
      : coalesce_expr($pdo, $table, ['arquivo', 'src', 'caminho', 'link', 'url'], 'src');

    $titulo = coalesce_expr($pdo, $table, ['titulo', 'nome', 'legenda'], 'titulo');
    $texto  = coalesce_expr($pdo, $table, ['texto', 'descricao', 'conteudo', 'caption'], 'texto');
    $cta    = coalesce_expr($pdo, $table, ['btn_url', 'cta_url', 'link_url', 'href', 'url', 'link', 'arquivo_url'], 'cta');
    $ordem  = col_exists($pdo, $table, 'ordem') ? 'ordem' : (col_exists($pdo, $table, 'posicao') ? 'posicao' : 'id');

    $wheres = [];
    if (col_exists($pdo, $table, 'ativo'))    $wheres[] = 'COALESCE(ativo,1)=1';
    if (col_exists($pdo, $table, 'registro')) $wheres[] = "registro IN ('home','site','index','banner_home')";

    $sqlWhere = $wheres ? (' WHERE ' . implode(' AND ', $wheres)) : '';

    $rows = $pdo->query("SELECT $titulo,$texto,$midia,$cta FROM `$table` $sqlWhere ORDER BY $ordem ASC LIMIT 12")
                ->fetchAll(PDO::FETCH_ASSOC);

    foreach ($rows as $r) {
      $src = trim((string)($r['src'] ?? ''));
      if ($src === '') continue;

      if (!preg_match('#^(?:https?:)?//#i', $src) && strpos($src, '/') === false) {
        $src = rtrim(UP, '/') . '/' . $src;
      }

      $media = resolve_media_src($src);
      if ($media['img'] === '') continue;

      return [
        'img'    => $media['img'],
        'titulo' => trim((string)($r['titulo'] ?? '')),
        'texto'  => trim((string)($r['texto'] ?? '')),
        'link'   => canonical_link((string)($r['cta'] ?? ''))
      ];
    }

    return $fallback;
  } catch (Throwable $e) {
    return $fallback;
  }
}

$BANNER = loadFixedBanner($pdo);

function jacy_fast_img_src(string $src): string {
  $src = trim($src);
  if ($src === '' || preg_match('#^(?:https?:)?//#i', $src) || strncmp($src, 'data:', 5) === 0) return $src;

  $path = parse_url($src, PHP_URL_PATH) ?: $src;
  if ($path === '' || preg_match('/\.webp(?:\?.*)?$/i', $path)) return $src;
  if (!preg_match('/\.(jpe?g|png)$/i', $path)) return $src;

  $info = pathinfo($path);
  $dir = $info['dirname'] ?? '';
  $filename = $info['filename'] ?? '';
  if ($filename === '') return $src;

  $mobileWebp = rtrim($dir, '/') . '/' . $filename . '-mobile.webp';
  $webp = rtrim($dir, '/') . '/' . $filename . '.webp';

  $doc = $_SERVER['DOCUMENT_ROOT'] ?? '';
  if ($doc !== '') {
    if (is_file($doc . $mobileWebp)) return $mobileWebp;
    if (is_file($doc . $webp)) return $webp;
  }

  return $src;
}

function jacy_banner_img_src(string $src): string {
  $src = trim($src);
  if ($src === '' || preg_match('#^(?:https?:)?//#i', $src) || strncmp($src, 'data:', 5) === 0) return $src;

  $path = parse_url($src, PHP_URL_PATH) ?: $src;
  if ($path === '' || preg_match('/\.webp(?:\?.*)?$/i', $path)) return $src;
  if (!preg_match('/\.(jpe?g|png)$/i', $path)) return $src;

  $info = pathinfo($path);
  $dir = $info['dirname'] ?? '';
  $filename = $info['filename'] ?? '';
  if ($filename === '') return $src;

  $webp = rtrim($dir, '/') . '/' . $filename . '.webp';
  $doc = $_SERVER['DOCUMENT_ROOT'] ?? '';

  if ($doc !== '' && is_file($doc . $webp)) return $webp;
  return $src;
}

function jacy_image_dimensions(string $src, int $fallbackWidth = 1600, int $fallbackHeight = 600): array {
  $path = parse_url($src, PHP_URL_PATH) ?: '';
  $doc = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');

  if ($path !== '' && $doc !== '') {
    $absolute = $doc . '/' . ltrim($path, '/');
    if (is_file($absolute)) {
      $size = @getimagesize($absolute);
      if (is_array($size) && !empty($size[0]) && !empty($size[1])) {
        return [(int)$size[0], (int)$size[1]];
      }
    }
  }

  return [$fallbackWidth, $fallbackHeight];
}

$BANNER_SRC = jacy_banner_img_src((string)($BANNER['img'] ?? ''));
if ($BANNER_SRC === '') {
  $BANNER_SRC = fix_path((string)($SET['logo_png'] ?? '/images/favicon.png'));
}

[$BANNER_WIDTH, $BANNER_HEIGHT] = jacy_image_dimensions($BANNER_SRC);
$BANNER_TITULO = trim((string)($BANNER['titulo'] ?? ''));
$BANNER_DESCRICAO = trim((string)($BANNER['texto'] ?? ''));
$BANNER_LINK = trim((string)($BANNER['link'] ?? ''));
$BANNER_BOTAO = 'Agendar';
$BANNER_TARGET = '_self';
$BANNER_REL = '';

if ($BANNER_LINK === '' || preg_match('/^\s*javascript:/i', $BANNER_LINK)) {
  $BANNER_LINK = $SET['agenda_url'];
} else {
  $BANNER_BOTAO = 'Saiba mais';
  if (preg_match('/^(https?:)?\/\//i', $BANNER_LINK)) {
    $BANNER_TARGET = '_blank';
    $BANNER_REL = 'noopener noreferrer';
  }
}

/* ============================================================
   HERO (Agenda Seletiva, ESQ)
   ============================================================ */
function loadHeroCarousel(?PDO $pdo): array {
  $fallback = [
    ['id' => '108YeuHhaFQ', 'titulo' => '', 'subtitulo' => '', 'thumb' => 'https://img.youtube.com/vi/108YeuHhaFQ/hqdefault.jpg'],
    ['id' => '26ZB20BR0E4', 'titulo' => '', 'subtitulo' => '', 'thumb' => 'https://img.youtube.com/vi/26ZB20BR0E4/hqdefault.jpg'],
  ];

  if (!$pdo || !table_exists($pdo, 'site_carrossel')) return $fallback;

  try {
    $sql = "SELECT id, titulo, subtitulo, video_url, thumb, ordem, ativo
              FROM site_carrossel
             WHERE COALESCE(ativo,1)=1
          ORDER BY COALESCE(ordem,0), id";

    $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
    $out = [];

    foreach ($rows as $r) {
      $vid = yt_id((string)$r['video_url']);
      if ($vid === '') continue;

      $thumb = trim((string)($r['thumb'] ?? ''));
      if ($thumb !== '') {
        if (!preg_match('#^https?://#i', $thumb)) {
          if (preg_match('#^/?paginas/#', $thumb)) {
            $thumb = '/sistema/painel/' . ltrim($thumb, '/');
          } else {
            $thumb = '/' . ltrim($thumb, '/');
          }
        }
      } else {
        $thumb = "https://img.youtube.com/vi/$vid/hqdefault.jpg";
      }

      $out[] = [
        'id'        => $vid,
        'thumb'     => $thumb,
        'titulo'    => trim((string)($r['titulo'] ?? '')),
        'subtitulo' => trim((string)($r['subtitulo'] ?? '')),
      ];
    }

    return $out ?: $fallback;
  } catch (Throwable $e) {
    return $fallback;
  }
}

$HERO = loadHeroCarousel($pdo);

/* ============================================================
   SERVIÇOS
   ============================================================ */
function loadServices(?PDO $pdo): array {
  if (!$pdo) return [];

  $cands = [
    ['t' => 'servicos',           'title' => ['titulo', 'nome'], 'desc' => ['descricao', 'texto', 'resumo'], 'preco' => ['preco', 'valor'], 'img' => ['capa', 'img', 'imagem', 'thumb'], 'link' => ['link', 'url', 'slug'], 'ativo' => ['ativo', 'status']],
    ['t' => 'produtos_servicos',  'title' => ['titulo', 'nome'], 'desc' => ['descricao', 'texto'], 'preco' => ['preco', 'valor'], 'img' => ['imagem', 'capa'], 'link' => ['url', 'slug'], 'ativo' => ['ativo', 'status']],
    ['t' => 'servicos_site',      'title' => ['titulo', 'nome'], 'desc' => ['descricao', 'texto'], 'preco' => ['preco', 'valor'], 'img' => ['imagem', 'capa'], 'link' => ['url', 'slug'], 'ativo' => ['ativo', 'status']],
  ];

  foreach ($cands as $c) {
    if (!table_exists($pdo, $c['t'])) continue;

    try {
      $title = coalesce_expr($pdo, $c['t'], $c['title'], 'titulo');
      $desc  = coalesce_expr($pdo, $c['t'], $c['desc'], 'descricao');
      $preco = coalesce_expr($pdo, $c['t'], $c['preco'], 'preco');
      $img   = coalesce_expr($pdo, $c['t'], $c['img'], 'img');
      $link  = coalesce_expr($pdo, $c['t'], $c['link'], 'link');

      $ativoCol = null;
      foreach ($c['ativo'] as $ac) {
        if (col_exists($pdo, $c['t'], $ac)) {
          $ativoCol = $ac;
          break;
        }
      }

      $where = $ativoCol ? "WHERE COALESCE($ativoCol,1)=1" : "";
      $sql   = "SELECT $title,$desc,$preco,$img,$link FROM `{$c['t']}` $where ORDER BY 1 LIMIT 12";
      $rows  = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);

      if (!$rows) continue;

      $out = [];
      foreach ($rows as $r) {
        $imgSrc = trim((string)($r['img'] ?? ''));
        if ($imgSrc === '') continue;

        $path = parse_url($imgSrc, PHP_URL_PATH) ?: '';
        $ext  = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        $isImg   = in_array($ext, ['jpg', 'jpeg', 'png', 'webp', 'gif'], true);
        $isVideo = in_array($ext, ['mp4', 'webm', 'ogg'], true);

        if (!$isImg && !$isVideo) continue;

        if (!preg_match('#^https?://#i', $imgSrc) && ($imgSrc[0] ?? '') !== '/') {
          if (preg_match('#^/?paginas/#', $imgSrc)) {
            $imgSrc = '/sistema/painel/' . ltrim($imgSrc, '/');
          } else {
            $imgSrc = '/' . ltrim($imgSrc, '/');
          }
        }

        $out[] = [
          'img'       => $isImg ? $imgSrc : '',
          'video'     => $isVideo ? $imgSrc : '',
          'titulo'    => trim((string)($r['titulo'] ?? '')),
          'descricao' => trim((string)($r['descricao'] ?? '')),
          'preco'     => trim((string)($r['preco'] ?? '')),
          'link'      => canonical_link((string)($r['link'] ?? '')),
        ];
      }

      return $out;
    } catch (Throwable $e) {}
  }

  return [];
}

$servicos = loadServices($pdo);

/* ============================================================
   GALERIA
   ============================================================ */
if (!function_exists('galeria_resolve_img')) {
  function galeria_resolve_img(string $nome): string {
    $nome = trim($nome);
    if ($nome === '') return '';

    $maybe = fix_path($nome);
    $absMaybe = $_SERVER['DOCUMENT_ROOT'] . (parse_url($maybe, PHP_URL_PATH) ?? $maybe);
    if (is_file($absMaybe)) return (parse_url($maybe, PHP_URL_PATH) ?? $maybe);

    $nome = basename($nome);
    $roots = [
      $_SERVER['DOCUMENT_ROOT'] . "/sistema/painel/img/foto_admin/$nome",
      $_SERVER['DOCUMENT_ROOT'] . "/sistema/painel/img/fotos/$nome",
      $_SERVER['DOCUMENT_ROOT'] . "/sistema/painel/img/galeria/$nome",
      $_SERVER['DOCUMENT_ROOT'] . "/sistema/public/uploads/site/$nome",
      $_SERVER['DOCUMENT_ROOT'] . "/sistema/img/$nome",
    ];

    foreach ($roots as $abs) {
      if (is_file($abs)) return str_replace($_SERVER['DOCUMENT_ROOT'], '', $abs);
    }

    return "/sistema/painel/img/foto_admin/sem-foto.jpg";
  }
}

if (!function_exists('galeria_load_rows')) {
  function galeria_load_rows(?PDO $pdo): array {
    if (!$pdo) return [];

    try {
      $sql = "SELECT id, titulo, imagem FROM foto_admin ORDER BY data_cad DESC, id DESC";
      $rows = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
      $out = [];

      foreach ($rows as $r) {
        $src = galeria_resolve_img((string)($r['imagem'] ?? ''));
        if ($src !== '') {
          $out[] = [
            'titulo' => trim((string)($r['titulo'] ?? '')),
            'src'    => $src
          ];
        }
      }

      return $out;
    } catch (Throwable $e) {
      return [];
    }
  }
}

$GALERIA_ROWS = galeria_load_rows($pdo);

/* ============================================================
   RECURSOS EXCLUSIVOS DA PÁGINA
   ============================================================ */
$proteseRecaptchaFile = ($_SERVER['DOCUMENT_ROOT'] ?? __DIR__) . '/config/recaptcha.php';
if (is_file($proteseRecaptchaFile)) {
  require_once $proteseRecaptchaFile;
}

$proteseRecaptchaSiteKey = defined('RECAPTCHA_SITE_KEY') ? (string)RECAPTCHA_SITE_KEY : '';
$proteseCssFile = __DIR__ . '/css/protese.css';
$proteseJsFile = __DIR__ . '/js/protese.js';
$proteseCssVersion = is_file($proteseCssFile) ? (int)filemtime($proteseCssFile) : 1;
$proteseJsVersion = is_file($proteseJsFile) ? (int)filemtime($proteseJsFile) : 1;
?>

<link rel="stylesheet" href="/css/protese.css?v=<?= $proteseCssVersion ?>">
<script>
window.RECAPTCHA_SITE_KEY = <?= json_encode($proteseRecaptchaSiteKey, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;
window.JACY_PROTESE_CONFIG = {
  whatsapp: <?= json_encode($WPP, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>,
  agenda: <?= json_encode($AGENDA_LINK, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>
};
</script>

<!-- Encerra .hero_area aberto por cabecalho.php. -->
</div>

<main>
<section class="protese-banner-fixo" aria-label="Destaque de prótese capilar">
  <div class="protese-banner-fixo__media" aria-hidden="true">
    <img
      src="<?=h($BANNER_SRC)?>"
      alt="<?=h($BANNER_TITULO !== '' ? $BANNER_TITULO : 'Prótese capilar masculina')?>"
      title="<?=h($BANNER_TITULO)?>"
      width="<?=$BANNER_WIDTH?>"
      height="<?=$BANNER_HEIGHT?>"
      loading="eager"
      decoding="async"
      fetchpriority="high"
      sizes="100vw"
      class="protese-banner-fixo__imagem">
  </div>

  <div class="protese-banner-fixo__overlay" aria-hidden="true"></div>

  <div class="container protese-banner-fixo__conteudo">
    <div class="protese-banner-fixo__caixa">
      <?php if ($BANNER_TITULO !== ''): ?>
        <h1 class="protese-banner-fixo__titulo"><?=h($BANNER_TITULO)?></h1>
      <?php endif; ?>

      <?php if ($BANNER_DESCRICAO !== ''): ?>
        <p class="protese-banner-fixo__descricao"><?=h($BANNER_DESCRICAO)?></p>
      <?php endif; ?>

      <div class="btn-box">
        <a
          href="<?=h($BANNER_LINK)?>"
          target="<?=$BANNER_TARGET?>"
          <?php if ($BANNER_REL !== ''): ?>rel="<?=$BANNER_REL?>"<?php endif; ?>
          class="btn btn-lg btn-contact"
        >
          <?=h($BANNER_BOTAO)?>
        </a>
      </div>
    </div>
  </div>
</section>

  <?php if ($servicos): ?>
  <section class="section">
    <div class="container">
      <?php if ($servicos): ?>
        <div class="cards">
          <?php foreach ($servicos as $s): ?>
            <article class="card">
              <img src="<?=h(jacy_fast_img_src($s['img'] ? $s['img'] : (UP . 'placeholder.webp')))?>" width="600" height="360" alt="<?=h($s['titulo'])?>" loading="lazy" decoding="async" fetchpriority="low" sizes="(max-width: 600px) 100vw, 33vw">
              <div class="p">
                <strong><?=h($s['titulo'])?></strong>

                <?php if (!empty($s['descricao'])): ?>
                  <div class="muted"><?=h(mb_strimwidth(strip_tags($s['descricao']), 0, 160, '…', 'UTF-8'))?></div>
                <?php endif; ?>

                <div class="protese-estilo-103">
                  <?php if ($s['preco'] !== ''): ?><span class="price"><?=h(money_br($s['preco']))?></span><?php endif; ?>
                  <span class="muted">·</span>
                  <a class="btn btn-brand" href="<?=h($SET['agenda_url'])?>">Agendar</a>
                  <a class="btn btn-outline" href="<?=h($WPP)?>" target="_blank" rel="noopener">WhatsApp</a>
                  <?php if (!empty($s['link'])): ?>
                    <a class="btn btn-outline" href="<?=h($s['link'])?>" target="_blank" rel="noopener">Detalhes</a>
                  <?php endif; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      <?php else: ?>
        <p class="muted" aria-hidden="true"></p>
      <?php endif; ?>
    </div>
  </section>
  <?php endif; ?>

  <section id="agenda-seletiva" class="protese-estilo-102">
    <div class="protese-estilo-101">
      <div>
        <div id="heroSlider" class="protese-estilo-100">
          <div id="heroTrack" class="protese-estilo-099">
            <?php foreach ($HERO as $h): ?>
              <div class="hero-slide protese-estilo-098" data-video="<?=h($h['id'])?>">
                <img alt="<?=h($h['titulo'] ?: 'Vídeo')?>" loading="lazy" fetchpriority="low" width="640" height="360" src="<?=h($h['thumb'])?>" data-fallback-src="https://img.youtube.com/vi/<?=h($h['id'])?>/hqdefault.jpg" class="protese-estilo-097">
                <button class="play-btn protese-estilo-096" type="button" aria-label="Reproduzir vídeo">
                  <svg width="28" height="28" viewBox="0 0 24 24" fill="#fff" aria-hidden="true"><path d="M8 5v14l11-7z"/></svg>
                  <span class="sr protese-estilo-095">Reproduzir vídeo</span>
                </button>
              </div>
            <?php endforeach; ?>
          </div>

          <button id="heroPrev" aria-label="Anterior" class="protese-estilo-094">‹</button>
          <button id="heroNext" aria-label="Próximo" class="protese-estilo-093">›</button>

          <div id="heroDots" class="protese-estilo-092">
            <?php for ($i = 0, $n = count($HERO); $i < $n; $i++): ?>
              <button type="button" data-i="<?=$i?>" aria-label="Ir para o vídeo <?=$i + 1?>" class="protese-hero-dot <?=$i === 0 ? 'is-active' : ''?>"></button>
            <?php endfor; ?>
          </div>
        </div>
      </div>

      <div>
        <h1 class="protese-estilo-091">Prótese Capilar em Cascavel<br>com Avaliação Profissional</h1>
        <p class="protese-estilo-090">Atendimento com hora marcada, avaliação individual, orientação sobre aplicação, manutenção e acabamento para um visual natural e alinhado ao seu perfil.</p>
        <div class="protese-estilo-089">
          <a id="ctaWhats" href="<?=h($WPP)?>" target="_blank" rel="noopener" class="protese-estilo-088">FALAR COM O JACY PELO WHATSAPP</a>
          <a id="ctaApp" href="<?=h($AGENDA_LINK)?>" target="_blank" rel="noopener" class="protese-estilo-087">AGENDAR PELO BARBERBOT</a>
        </div>
        <div class="google-ads-note">
          Atendimento sujeito à avaliação profissional. A simulação visual é uma referência inicial e não garante resultado final idêntico.
        </div>
        <div class="legal-mini-links" aria-label="Links legais">
          <a href="/politica-privacidade.php">Política de Privacidade</a>
          <a href="/termos-uso.php">Termos de Uso</a>
        </div>
      </div>
    </div>
  </section>

  <?php if (!empty($GALERIA_ROWS)): ?>
  <section id="galeria-banco" class="protese-estilo-086">
    <div class="protese-estilo-085">
      <h2 class="protese-estilo-084">Galeria de trabalhos</h2>

      <div id="galeriaWrap" class="protese-estilo-083">
        <button id="galPrev" aria-label="Anterior" class="protese-estilo-082">‹</button>
        <button id="galNext" aria-label="Próximo" class="protese-estilo-081">›</button>

        <div class="protese-estilo-080">
          <div id="galTrack" class="protese-estilo-079">
            <?php foreach ($GALERIA_ROWS as $g): ?>
              <figure class="galItem protese-estilo-078">
                <div class="protese-estilo-077">
                  <img src="<?=h(jacy_fast_img_src((string)$g['src']))?>" alt="<?=h($g['titulo'] ?: 'Foto')?>" width="400" height="408" loading="lazy" decoding="async" fetchpriority="low" data-fallback-src="/sistema/painel/img/foto_admin/sem-foto.jpg" class="protese-estilo-076">
                </div>
                <?php if (!empty($g['titulo'])): ?>
                  <figcaption class="protese-estilo-075"><?=h($g['titulo'])?></figcaption>
                <?php endif; ?>
              </figure>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <section class="jacy-simulacao-section protese-estilo-074">
    <div class="protese-estilo-073">

      <div class="protese-estilo-072">
        <h2 class="protese-estilo-071">
          Simulação visual para apoiar sua avaliação
        </h2>

        <p class="protese-estilo-070">
          Envie uma foto frontal para gerar uma prévia visual de referência. A simulação ajuda na conversa inicial e respeita a cor natural do cabelo. A definição final de cor, densidade e modelo é feita durante a avaliação profissional.
        </p>
      </div>

      <div class="protese-estilo-069">
        <div class="jacy-sim-card protese-estilo-068">
          <div class="jacy-sim-num protese-estilo-067">1</div>
          <h4 class="protese-estilo-066">Envie sua foto</h4>
          <p class="protese-estilo-065">Envie uma foto frontal para gerar uma prévia visual de referência para o atendimento.</p>
        </div>

        <div class="jacy-sim-card protese-estilo-068">
          <div class="jacy-sim-num protese-estilo-067">2</div>
          <h4 class="protese-estilo-066">Prévia com referência natural</h4>
          <p class="protese-estilo-065">A prévia considera a cor natural do cabelo e serve como apoio para a avaliação presencial ou online.</p>
        </div>

        <div class="jacy-sim-card protese-estilo-068">
          <div class="jacy-sim-num protese-estilo-067">3</div>
          <h4 class="protese-estilo-066">Receba uma referência visual</h4>
          <p class="protese-estilo-065">Visualize uma referência antes do atendimento. O resultado final depende da avaliação, materiais, técnica de aplicação e manutenção.</p>
        </div>
      </div>

      <div class="protese-estilo-064">
        <div class="jacy-sim-cta protese-estilo-063">
          <h4 class="protese-estilo-062">Solicite uma simulação visual com orientação profissional</h4>
          <p class="protese-estilo-061">
            Envie sua foto e receba uma prévia visual para iniciar sua avaliação. A definição final de cor, densidade e modelo será orientada pelo profissional durante o atendimento.
          </p>

          <a
  href="#modalSimulacaoCliente"
  id="btnAbrirSimuladorProtese"
  data-toggle="modal"
  data-target="#modalSimulacaoCliente"
  role="button"
  class="btn btn-primary btn-lg protese-estilo-060"
 
>
  Solicitar simulação visual
</a>
        </div>
      </div>

    </div>
  </section>

  <section class="experience-section visitas-normal protese-estilo-059">
      <div class="xp-wrap">
      <div class="xp-grid">
        <div class="xp-left-year">
          <div class="xp-year">1999</div>
          <div class="xp-subtitle">Início da Jornada</div>
          <div class="xp-underline"></div>

          <div class="xp-visitas">
            <div class="protese-estilo-058">👁️</div>
            <div class="protese-estilo-057">
              <div class="xp-vis-num"><span class="counter-1999" data-target="0">0</span></div>
              <div class="xp-vis-label">Visitas</div>
            </div>
          </div>
        </div>

        <div>
          <h2 class="xp-title">Mais de 25 Anos de <span class="blue">Excelência</span></h2>
          <p class="xp-desc">
            Desde 1999, Jacy Cordeiro atua com atendimento técnico, cuidado no acabamento e orientação personalizada. A proposta é unir experiência, discrição e acompanhamento para cada cliente que busca <strong>cuidado capilar</strong> e <strong>estética masculina</strong> em Cascavel.
          </p>

          <div class="exp-stats">
            <div class="exp-card">
              <div class="exp-num exp-num--blue">25+</div>
              <div class="exp-label">Anos</div>
            </div>
            <div class="exp-card">
              <div class="exp-num exp-num--green">100% </div>
              <div class="exp-label">Fios Naturais</div>
            </div>
            <div class="exp-card">
              <div class="exp-num exp-num--gold">100%</div>
              <div class="exp-label">Clientes satisfeitos</div>
            </div>
          </div>

          <div class="xp-badges">
            <span class="xp-badge xp-badge--cert"><span class="dot">✔</span>Profissionais Certificados</span>
            <span class="xp-badge xp-badge--qual"><span class="dot">🏆</span>Tradição & Qualidade</span>
          </div>
        </div>
      </div>
    </div>

    <div class="protese-estilo-056"></div>
  </section>

  <section class="protese-estilo-055">
    <div class="protese-estilo-054">
      <div class="protese-estilo-053">
        <div class="protese-estilo-052">
          <?php
            $url_video      = isset($url_video) ? (string)$url_video : '';
            $posicao_video  = isset($posicao_video) ? (string)$posicao_video : '';
            $imagem_sobre   = isset($imagem_sobre) ? basename((string)$imagem_sobre) : 'sobre.jpg';
            $texto_sobre    = isset($texto_sobre) ? (string)$texto_sobre : '';
            $videoEmCima    = ($url_video !== '' && trim($posicao_video) === 'sobre');
            $videoEmBaixo   = ($url_video !== '' && trim($posicao_video) === 'abaixo');

            if ($videoEmCima) {
              echo '<div class="protese-estilo-002"><iframe src="about:blank" data-src="' . h($url_video) . '" class="js-lazy-iframe protese-estilo-042" title="Vídeo institucional" frameborder="0" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe></div>';
            } else {
              $imgPath  = "images/{$imagem_sobre}";
              $webpPath = preg_replace('/\.(jpe?g|png)$/i', '.webp', $imgPath);
          ?>
            <picture>
              <?php if ($webpPath && file_exists($webpPath)): ?>
                <source type="image/webp" srcset="<?=h($webpPath)?>">
              <?php endif; ?>
              <img src="<?=h($imgPath)?>" alt="Sobre nós" width="1200" height="675" loading="lazy" decoding="async" fetchpriority="low" class="protese-estilo-051">
            </picture>
          <?php } ?>
        </div>
      </div>

      <div class="protese-estilo-050">
        <div class="protese-estilo-049">
          <h4 class="protese-estilo-048">Sobre Nós</h4>
          <p class="protese-estilo-047"><?=nl2br(h($texto_sobre))?></p>
          <a href="#empresa" data-toggle="modal" data-target="#empresa" aria-controls="empresa" aria-label="Abrir informações da empresa" class="protese-estilo-046">Mais Informações</a>
        </div>
      </div>
    </div>
  </section>

  <?php if (!empty($videoEmBaixo)): ?>
    <div class="protese-estilo-045">
      <div class="protese-estilo-044">
        <div class="protese-estilo-043">
          <iframe class="video_mobile js-lazy-iframe protese-estilo-042" src="about:blank" data-src="<?=h($url_video)?>" title="Vídeo institucional" frameborder="0" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen referrerpolicy="strict-origin-when-cross-origin"></iframe>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <?php
    $resDep = [];

    try {
      $__colsDep = $pdo ? $pdo->query("SHOW COLUMNS FROM comentarios")->fetchAll(PDO::FETCH_COLUMN) : [];
      $__selectDep = "nome, texto, foto";

      if (in_array('video', $__colsDep, true)) {
        $__selectDep .= ", video";
      } else {
        $__selectDep .= ", '' AS video";
      }

      if (in_array('print_whatsapp', $__colsDep, true)) {
        $__selectDep .= ", print_whatsapp";
      } else {
        $__selectDep .= ", '' AS print_whatsapp";
      }

      $resDep = $pdo ? $pdo->query("SELECT {$__selectDep} FROM comentarios WHERE ativo='Sim' ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC) : [];
    } catch (Throwable $e) {
      try {
        $resDep = $pdo ? $pdo->query("SELECT nome, texto, foto, '' AS video, '' AS print_whatsapp FROM comentarios WHERE ativo='Sim' ORDER BY id DESC LIMIT 20")->fetchAll(PDO::FETCH_ASSOC) : [];
      } catch (Throwable $e) {
        $resDep = [];
      }
    }

    if ($resDep && count($resDep) > 0):
  ?>

  <section id="depoimentos" class="depoimentos-protese-section">
    <div class="depoimentos-protese-container">
      <h2 class="depoimentos-protese-titulo">Depoimentos de clientes</h2>

      <div class="depoimentos-controle-protese">
        <button id="depPrev" type="button" aria-label="Anterior">‹</button>
        <div id="depDots" class="depoimentos-dots-protese"></div>
        <button id="depNext" type="button" aria-label="Próximo">›</button>
      </div>

      <div class="depoimentos-viewport-protese">
        <div id="depTrack" class="depoimentos-track-protese">
          <?php foreach ($resDep as $c):
            $nome  = trim($c['nome'] ?? '');
            $texto = trim($c['texto'] ?? '');
            $foto  = basename((string)($c['foto'] ?? ''));
            $video = basename((string)($c['video'] ?? ''));
            $print_whatsapp = basename((string)($c['print_whatsapp'] ?? ''));

            $path  = "sistema/painel/img/comentarios/" . ($foto ?: 'sem-foto.jpg');
            $webp  = preg_replace('/\.(jpe?g|png)$/i', '.webp', $path);
            $src   = (is_file($_SERVER['DOCUMENT_ROOT'] . '/' . $webp) ? $webp : $path);

            $videoSrc = $video !== '' ? "sistema/painel/img/comentarios/" . $video : '';
            $printSrc = $print_whatsapp !== '' ? "sistema/painel/img/comentarios/" . $print_whatsapp : '';
          ?>
            <article class="depItem depoimento-item-protese">
              <div class="depoimento-card-protese">
                <div class="depoimento-card-topo-protese">
                  <img
                    class="depoimento-card-foto-protese"
                    src="<?=h(jacy_fast_img_src((string)$src))?>"
                    alt="<?=h($nome ?: 'Cliente')?>"
                    width="96"
                    height="96"
                    loading="lazy"
                    decoding="async"
                    fetchpriority="low"
                  >

                  <div class="depoimento-card-info-protese">
                    <strong><?=h($nome ?: 'Cliente')?></strong>
                    <span>Avaliação verificada</span>
                  </div>
                </div>

                <div class="depoimento-card-corpo-protese">
                  <p class="depoimento-card-texto-protese"><?=nl2br(h($texto))?></p>

                  <?php if ($videoSrc !== '' || $printSrc !== ''): ?>
                    <div class="depoimento-card-midias-protese">
                      <?php if ($videoSrc !== ''): ?>
                        <div class="depoimento-card-midia-protese">
                          <video controls preload="metadata" playsinline>
                            <source src="<?=h($videoSrc)?>" type="video/mp4">
                          </video>
                        </div>
                      <?php endif; ?>

                      <?php if ($printSrc !== ''): ?>
                        <div class="depoimento-card-midia-protese">
                          <img
                            src="<?=h($printSrc)?>"
                            alt="Print do WhatsApp do depoimento de <?=h($nome ?: 'cliente')?>"
                            loading="lazy"
                            decoding="async"
                          >
                        </div>
                      <?php endif; ?>
                    </div>
                  <?php else: ?>
                    <div class="depoimento-card-sem-midia-protese">
                      Depoimento cliente satisfeit.
                    </div>
                  <?php endif; ?>
                </div>
              </div>
            </article>
          <?php endforeach; ?>
        </div>
      </div>

      <div class="depoimentos-protese-botao">
        <a href="#modalComentario" data-toggle="modal" data-target="#modalComentario">Enviar depoimento</a>
      </div>
    </div>
  </section>
  <?php endif; ?>
</main>

<div class="modal fade" id="modalSimulacaoCliente" tabindex="-1" role="dialog" aria-labelledby="modalSimulacaoClienteLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="modalSimulacaoClienteLabel">Simulação Visual de Prótese Capilar</h4>
        <button type="button" class="close" data-dismiss="modal" aria-label="Fechar modal">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <form id="form_simulacao_cliente" method="post" action="/ajax/gerar_ia_cliente.php" enctype="multipart/form-data">
        <div class="modal-body">
          <div class="alert alert-info protese-estilo-041">
            Envie uma foto frontal para uma prévia visual de referência. A imagem será usada para atendimento e simulação, conforme a Política de Privacidade.
          </div>

          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label for="nome_simulacao_cliente">Seu Nome *</label>
                <input type="text" class="form-control" id="nome_simulacao_cliente" name="nome" autocomplete="name" placeholder="Digite seu nome" required>
              </div>
            </div>

            <div class="col-md-6">
              <div class="form-group">
                <label for="telefone_simulacao_cliente">WhatsApp *</label>
                <input type="tel" class="form-control" id="telefone_simulacao_cliente" name="telefone" autocomplete="tel" inputmode="tel" placeholder="(00) 00000-0000" maxlength="15" required>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-12">
              <div class="form-group">
                <label for="foto_simulacao_cliente">Escolha sua foto</label>
                <input type="file" class="form-control" id="foto_simulacao_cliente" name="foto" accept=".jpg,.jpeg,.png,.webp" required>
              </div>
            </div>
          </div>

          <div class="row linha-imagens">
            <div class="col-md-6">
              <div class="painel-comparacao-simulacao">
                <span class="selo-comparacao">Antes</span>
                <div class="titulo-painel">Imagem Original</div>
                <div class="box-imagem-simulacao">
                  <img src="/sistema/painel/images/simulacoes/sem-foto.jpg" id="preview_original_simulacao_cliente" alt="Prévia da imagem original enviada" width="260" height="260" loading="lazy" decoding="async" fetchpriority="low">
                </div>
                <div class="legenda-simulacao">Foto enviada para referência visual no atendimento.</div>
              </div>
            </div>

            <div class="col-md-6">
              <div class="painel-comparacao-simulacao">
                <span class="selo-comparacao">Depois</span>
                <div class="titulo-painel">Imagem Simulada</div>
                <div class="box-imagem-simulacao">
                  <img src="/sistema/painel/images/simulacoes/sem-foto.jpg" id="preview_resultado_simulacao_cliente" alt="Prévia da simulação gerada" width="260" height="260" loading="lazy" decoding="async" fetchpriority="low">
                </div>
                <div class="legenda-simulacao">Prévia visual gerada para apoiar a avaliação profissional.</div>
              </div>
            </div>
          </div>

          <div class="row linha-botao">
            <div class="col-md-12">
              <button type="button" id="btn_simular_cliente" class="btn btn-primary btn-block">
                <i id="icone_btn_simular_cliente" class="fa fa-magic"></i>
                <span id="texto_btn_simular_cliente">Solicitar simulação</span>
              </button>
            </div>
          </div>

          <div class="row protese-estilo-040">
            <div class="col-md-12">
              <div id="mensagem_simulacao_cliente" role="status" aria-live="polite" class="protese-estilo-039"></div>
            </div>
          </div>
        </div>
      </form>
    </div>
  </div>
</div>

<div class="modal fade" id="modalComentario" tabindex="-1" role="dialog" aria-labelledby="modalComentarioLabel" aria-hidden="true">
  <div class="modal-dialog protese-estilo-038" role="document">
    <div class="modal-content protese-estilo-037">
      <div class="modal-header protese-estilo-036">
        <h5 class="modal-title protese-estilo-035" id="modalComentarioLabel">Enviar depoimento</h5>
        <button type="button" class="close protese-estilo-034" data-dismiss="modal" aria-label="Fechar"><span aria-hidden="true">&times;</span></button>
      </div>

      <form id="form" enctype="multipart/form-data">
        <div class="modal-body protese-estilo-033">
          <div class="protese-estilo-032">
            <label for="nome_cliente" class="protese-estilo-023">Nome</label>
            <input type="text" id="nome_cliente" name="nome" autocomplete="name" placeholder="Seu nome completo" required class="form-control protese-estilo-029">
          </div>

          <div class="protese-estilo-031">
            <label for="texto_cliente" class="protese-estilo-023">Texto <small class="protese-estilo-030">(até 500 caracteres)</small></label>
            <textarea id="texto_cliente" name="texto" maxlength="500" rows="4" required placeholder="Seu depoimento" class="form-control protese-estilo-029"></textarea>
          </div>

          <div class="protese-estilo-028">
            <div class="protese-estilo-027">
              <label for="foto" class="protese-estilo-023">Foto</label>
              <input id="foto" name="foto" type="file" accept=".jpg,.jpeg,.png,.webp" class="form-control protese-estilo-022">
              <div class="protese-estilo-021">Opcional: JPG/PNG/WebP (até 5MB)</div>
            </div>

            <div id="divImg" class="protese-estilo-026">
              <img id="target" alt="Pré-visualização" src="sistema/painel/img/comentarios/sem-foto.jpg" width="96" height="96" loading="lazy" decoding="async" fetchpriority="low" class="protese-estilo-025">
            </div>
          </div>

          <div class="protese-estilo-024">
            <label for="video" class="protese-estilo-023">Vídeo do depoimento</label>
            <input id="video" name="video" type="file" accept=".mp4,.mov,.webm,.3gp,.m4v,video/*" class="form-control protese-estilo-022">
            <div class="protese-estilo-021">Opcional: MP4, MOV, WEBM, 3GP ou M4V (até 80MB)</div>
          </div>

          <div class="protese-estilo-024">
            <label for="print_whatsapp" class="protese-estilo-023">Print do WhatsApp</label>
            <input id="print_whatsapp" name="print_whatsapp" type="file" accept=".jpg,.jpeg,.png,.webp" class="form-control protese-estilo-022">
            <div class="protese-estilo-021">Opcional: print da conversa em JPG/PNG/WebP (até 5MB)</div>
          </div>

          <input type="hidden" name="id" id="id">
          <input type="hidden" name="cliente" value="1">
          <div id="mensagem-comentario" role="status" aria-live="polite" class="protese-estilo-020"></div>
        </div>

        <div class="modal-footer protese-estilo-019">
          <button type="button" class="btn btn-secondary protese-estilo-018" data-dismiss="modal">Cancelar</button>
          <button type="submit" class="btn btn-primary protese-estilo-017">Inserir</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div id="ytModal" class="protese-estilo-005">
  <div class="protese-estilo-004">
    <button id="ytClose" aria-label="Fechar" class="protese-estilo-003">✕</button>
    <div class="protese-estilo-002"><div id="ytPlayer" class="protese-estilo-001"></div></div>
  </div>
</div>

<script type="application/ld+json">
{
  "@context":"https://schema.org",
  "@type":"HairSalon",
  "name":<?=json_encode($SET['nome'], JSON_UNESCAPED_UNICODE)?>,
  "url":<?=json_encode(rtrim($SET['site'], '/') . '/', JSON_UNESCAPED_SLASHES)?>,
  "description":<?=json_encode($SET['descricao'], JSON_UNESCAPED_UNICODE)?>,
  "telephone":<?=json_encode($SET['telefone'], JSON_UNESCAPED_UNICODE)?>,
  "image":<?=json_encode($SET['logo_png'], JSON_UNESCAPED_SLASHES)?>,
  "address":{
    "@type":"PostalAddress",
    "streetAddress":"Rua Rio Grande do Sul, 2151",
    "addressLocality":"Cascavel",
    "addressRegion":"PR",
    "postalCode":"85801-050",
    "addressCountry":"BR"
  },
  "geo":{
    "@type":"GeoCoordinates",
    "latitude":-24.957842,
    "longitude":-53.458627
  },
  "areaServed":"Cascavel e região oeste do Paraná",
  "priceRange":"R$R$",
  "openingHoursSpecification":[
    {"@type":"OpeningHoursSpecification","dayOfWeek":["Monday","Tuesday","Wednesday","Thursday","Friday"],"opens":"09:00","closes":"19:00"},
    {"@type":"OpeningHoursSpecification","dayOfWeek":"Saturday","opens":"09:00","closes":"17:00"}
  ],
  "sameAs":[
    <?=json_encode($INSTAGRAM_LINK ?: "https://www.instagram.com/jacycabeleireiro", JSON_UNESCAPED_SLASHES)?>,
    "https://maps.app.goo.gl/PGDcFaCRaq2pmMVa9"
  ]
}
</script>

<script src="/js/protese.js?v=<?= $proteseJsVersion ?>" defer></script>

<?php require_once __DIR__ . '/rodape.php'; ?>
