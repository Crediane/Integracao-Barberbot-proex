<?php require_once("cabecalho.php"); ?>
<?php
// ===================== BOOTSTRAP 4.3.1 (compat) =====================
if (!function_exists('e')) {
  function e(string $v): string { return htmlspecialchars($v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}
function phoneDigits(?string $v): string { return preg_replace('/\D+/', '', $v ?? ''); }

function loadConfig(PDO $pdo): array {
  $cfg = [];
  try {
    $stmt = $pdo->query("SELECT * FROM config");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!$rows) return $cfg;
    if (array_key_exists('chave', $rows[0]) && array_key_exists('valor', $rows[0])) {
      foreach ($rows as $r) {
        $cfg[$r['chave']] = $r['valor'];
      }
    } else {
      $cfg = $rows[0];
    }
  } catch (Throwable $e) { }
  return $cfg;
}

function pickExistingImage(array $candidates): string {
  foreach ($candidates as $web) {
    $abs = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/').$web;
    if (is_file($abs)) return $web;
  }
  return $candidates[0] ?? '/images/logo-share.jpg';
}

$config = loadConfig($pdo);
$url_site      = $config['url_site']      ?? ($url_site      ?? '');
$tel_whatsapp  = $config['tel_whatsapp']  ?? ($tel_whatsapp  ?? '');
$endereco      = $config['endereco']      ?? ($endereco      ?? '');
$cep           = $config['cep']           ?? ($cep           ?? '');
$mapa          = $config['mapa']          ?? ($mapa          ?? '');
$url_video     = $config['url_video']     ?? ($url_video     ?? '');
$posicao_video = $config['posicao_video'] ?? ($posicao_video ?? '');
$imagem_sobre  = $config['imagem_sobre']  ?? ($imagem_sobre  ?? 'sobre.jpg');
$whats = phoneDigits($tel_whatsapp);

require_once(__DIR__ . '/config/recaptcha.php');
$recaptcha_site_key = defined('RECAPTCHA_SITE_KEY') ? RECAPTCHA_SITE_KEY : '';

$lcpHero = pickExistingImage([
  '/images/hero-barbearia.webp',
  '/images/hero-barbearia.jpg',
  '/images/hero.jpg',
  '/images/logo-share.jpg',
]);
try {
  $first = $pdo->query("SELECT * FROM textos_index ORDER BY id ASC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
  if (!empty($first['imagem'])) {
    $candidate = '/sistema/painel/img/slides/'.ltrim($first['imagem'], '/');
    $lcpHero = pickExistingImage([$candidate, $lcpHero]);
  }
} catch (Throwable $e) { }
?>

<!-- ===== RESOURCE HINTS (Performance) ===== -->
<link rel="preconnect" href="https://www.google.com" crossorigin>
<link rel="preconnect" href="https://www.gstatic.com" crossorigin>
<link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
<link rel="dns-prefetch" href="https://api.whatsapp.com">
<link rel="dns-prefetch" href="https://agenda.jacycabeleireiro.com">

<!-- ===== SEO ===== -->
<meta name="description" content="Jacy Cabeleireiro - Os melhores cortes masculinos, designer de barba e serviços profissionais de barbearia em Cascavel. Agende seu horário online!">
<meta name="keywords" content="barbearia cascavel, corte masculino cascavel, designer barba, cabeleireiro masculino, barbeiro profissional, corte degradê, barba moderna">
<meta name="author" content="Jacy Cabeleireiro">
<meta name="robots" content="index, follow">
<link rel="canonical" href="<?php echo e($url_site) ?>/barbearia">

<!-- Open Graph -->
<meta property="og:type" content="website">
<meta property="og:title" content="Jacy Cabeleireiro | Barbearia Profissional em Cascavel">
<meta property="og:description" content="Os melhores cortes e serviços de barbearia em Cascavel. Agende agora!">
<meta property="og:image" content="<?php echo e($url_site) ?>/images/logo-share.jpg">
<meta property="og:url" content="<?php echo e($url_site) ?>">
<meta property="og:site_name" content="Jacy Cabeleireiro">

<!-- Twitter -->
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="Jacy Cabeleireiro | Barbearia Profissional">
<meta name="twitter:description" content="Os melhores cortes masculinos em Cascavel">
<meta name="twitter:image" content="<?php echo e($url_site) ?>/images/logo-share.jpg">

<!-- JSON-LD -->
<script type="application/ld+json">
{"@context":"https://schema.org","@type":"HairSalon","name":"Jacy Cabeleireiro","image":"<?php echo e($url_site) ?>/images/logo.jpg","@id":"<?php echo e($url_site) ?>","url":"<?php echo e($url_site) ?>","telephone":"<?php echo e($tel_whatsapp) ?>","address":{"@type":"PostalAddress","streetAddress":"<?php echo e($endereco) ?>","addressLocality":"Cascavel","addressRegion":"PR","postalCode":"<?php echo e($cep) ?>","addressCountry":"BR"},"priceRange":"$$"}
</script>

<!-- Preload LCP (Critical) -->
<link rel="preload" as="image" href="<?php echo e($lcpHero) ?>" fetchpriority="high">

<!-- ===== CSS CRÍTICO INLINE (Minificado) ===== -->
<style>
.slider_section{position:relative;overflow:hidden}.slider_section .carousel-item{position:relative;min-height:clamp(360px,70vh,820px)}.slider_section .hero-bg{position:absolute;inset:0;width:100%;height:100%;object-fit:cover;object-position:center;z-index:0}.slider_section .detail-box{position:relative;z-index:1;color:#fff}.slider_section .detail-box h1{line-height:1.1;text-shadow:0 2px 8px rgba(0,0,0,.55)}.carousel_btn-box,.carousel-control-prev,.carousel-control-next{z-index:2}img[loading="lazy"]{opacity:0;transition:opacity .3s}img[data-loaded="true"],img.loaded{opacity:1}.img-box img{width:100%;height:auto;display:block}.client_section .box .img-box::before,.client_section .box .img-box::after,.client_section .testimonial-card .testimonial-avatar::before,.client_section .testimonial-card .testimonial-avatar::after{content:none!important;display:none!important}.client_section .img-1{clip-path:none!important;-webkit-clip-path:none!important;mask:none!important}.testimonial-avatar{width:80px!important;height:80px!important;aspect-ratio:1/1;border-radius:50%!important;object-fit:cover!important;object-position:center;border:3px solid #ffc107;box-shadow:0 6px 18px rgba(0,0,0,.15);background:#fff}#form-email .form-check{display:flex;align-items:center;gap:.5rem;margin:.75rem 0 1rem}#form-email .form-check-input{position:static!important;width:1.1rem!important;height:1.1rem!important;margin:0!important;flex:0 0 auto}#form-email .form-check-label{margin:0!important;line-height:1.3}#form-email .form-check .invalid-feedback{display:none;margin-left:.5rem}#form-email.was-validated .form-check-input:invalid ~ .invalid-feedback{display:block}@media (max-width:576px){#form-email .form-check{flex-wrap:wrap}}.modal{position:fixed;z-index:2147483000!important}.modal-backdrop{position:fixed!important;z-index:2147482999!important;background:#000}.modal-backdrop.show{opacity:.5}header,.navbar,.topbar,.slider_section,#customCarousel1,.owl-carousel,.service,.about_section,.cta-section{z-index:auto!important;transform:none!important;will-change:auto!important}header,.navbar,.site-header,.topbar,.main-header{transform:none!important;z-index:5000!important}header.fixed-top,.navbar.fixed-top,.site-header.fixed-top{background:rgba(0,0,0,.92)}@media (min-width:576px){header:not(.fixed-top):not(.position-absolute),.navbar:not(.fixed-top):not(.position-absolute),.site-header:not(.fixed-top):not(.position-absolute){position:sticky;top:0}}
</style>

<?php 
// ================= SLIDER OTIMIZADO =================
$query = $pdo->query("SELECT * FROM textos_index ORDER BY id asc");
$slides = $query->fetchAll(PDO::FETCH_ASSOC);
if(@count($slides) > 0){
?>
<section class="slider_section" aria-label="Destaques">
  <div id="customCarousel1" class="carousel slide" data-ride="carousel" data-interval="5000">
    <div class="carousel-inner">
      <?php foreach ($slides as $i => $s):
        $titulo = $s['titulo'] ?? '';
        $descricao = $s['descricao'] ?? '';
        $img = !empty($s['imagem']) ? '/sistema/painel/img/slides/'.ltrim($s['imagem'],'/') : $lcpHero;
        $ativo = ($i === 0) ? 'active' : '';
        $loading = ($i === 0) ? 'eager' : 'lazy';
      ?>
      <div class="carousel-item <?php echo $ativo ?>">
        
        <div class="container h-100">
          <div class="row h-100 align-items-center">
            <div class="col-md-7 col-lg-6">
              <div class="detail-box">
                <h1 class="display-4 font-weight-bold mb-4"><?php echo $titulo ?></h1>
                <p class="lead mb-4" style="font-size:1.1rem;line-height:1.8"><?php echo $descricao ?></p>
                <div class="btn-box d-flex flex-wrap" style="gap:.75rem">
                  <a href="https://agenda.jacycabeleireiro.com/agendamentos" target="_blank" rel="noopener" class="btn btn-warning btn-lg" style="padding:14px 32px"><i class="fa fa-calendar" aria-hidden="true"></i> Agendar Agora</a>
                  <a href="https://api.whatsapp.com/send?1=pt_BR&phone=<?php echo e($whats) ?>&text=<?php echo rawurlencode('Olá! Gostaria de agendar um horário.') ?>" target="_blank" rel="noopener nofollow" class="btn btn-success btn-lg" style="padding:14px 32px"><i class="fa fa-whatsapp" aria-hidden="true"></i> WhatsApp</a>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="container">
      <div class="carousel_btn-box">
        <a class="carousel-control-prev" href="#customCarousel1" role="button" data-slide="prev" aria-label="Anterior">
          <i class="fa fa-arrow-left" aria-hidden="true"></i><span class="sr-only">Anterior</span>
        </a>
        <a class="carousel-control-next" href="#customCarousel1" role="button" data-slide="next" aria-label="Próximo">
          <i class="fa fa-arrow-right" aria-hidden="true"></i><span class="sr-only">Próximo</span>
        </a>
      </div>
    </div>
  </div>
</section>
<?php } ?>

</div>

<!-- =================== SERVIÇOS DINÂMICOS =================== -->
<section class="product_section layout_padding" id="servicos">
  <div class="container">
    <div class="heading_container heading_center">
      <h2 class="display-4 font-weight-bold mb-3">Nossos Serviços</h2>
      <p class="col-lg-10 px-0 text-muted" style="font-size:1.05rem">
        <?php 
        $cat = $pdo->query("SELECT * FROM cat_servicos ORDER BY id asc")->fetchAll(PDO::FETCH_ASSOC);
        if(@count($cat) > 0){
          foreach ($cat as $i => $c) {
            echo $c['nome'];
            if($i < (count($cat)-1)) echo ' • ';
          }
        }
        ?>
      </p>
    </div>

    <?php
    $serv = $pdo->query("SELECT * FROM servicos WHERE ativo = 'Sim' ORDER BY id asc")->fetchAll(PDO::FETCH_ASSOC);
    if(@count($serv) > 0){
    ?>
    <div class="product_container">
      <div class="product_owl-carousel owl-carousel owl-theme" aria-label="Carrossel de serviços">
        <?php foreach ($serv as $s):
          $nome = $s['nome'];
          $valor = $s['valor'];
          $foto = $s['foto'];
          $descricao = $s['descricao'] ?? 'Serviço profissional de qualidade';
          $valorF = number_format($valor, 2, ',', '.');
          $nomeF = mb_strimwidth($nome, 0, 30, "...");
        ?>
        <div class="item">
          <div class="box" style="border-radius:14px;overflow:hidden">
            <div class="img-box" style="position:relative">
              <img src="sistema/painel/img/servicos/<?php echo e($foto) ?>" alt="<?php echo e($nome) ?>" loading="lazy" decoding="async" width="480" height="320" style="width:100%;height:250px;object-fit:cover">
              <div class="service-price-tag" style="position:absolute;bottom:12px;right:12px;background:rgba(255,193,7,.95);color:#000;padding:8px 16px;border-radius:22px;font-weight:700">R$ <?php echo $valorF ?></div>
            </div>
            <div class="detail-box" style="padding:18px">
              <h4 class="font-weight-bold mb-2" style="color:#333"><?php echo $nomeF ?></h4>
              <p class="text-muted mb-3" style="font-size:14px;min-height:40px"><?php echo mb_strimwidth($descricao, 0, 80, "...") ?></p>
              <a href="agendamentos" class="btn btn-warning btn-block"><i class="fa fa-scissors" aria-hidden="true"></i> Agendar Este Serviço</a>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php } ?>
  </div>
</section>

<!-- ================== GRADE DESTAQUES ================== -->
<div class="service">
  <div class="container">
    <div class="section-header text-center mb-5">
      <h2 class="display-4 font-weight-bold">Serviços em Destaque</h2>
      <p class="lead text-muted">Especialidades mais procuradas</p>
    </div>
    <div class="row">
      <?php
      $servicos_destaque = [
        ['nome'=>'Corte Tesoura','img'=>'corte-tesoura-.png','desc'=>'Corte clássico com acabamento perfeito'],
        ['nome'=>'Corte Moderno','img'=>'corte-moderno.png','desc'=>'Estilos modernos e tendências'],
        ['nome'=>'Corte Degradê','img'=>'corte-degrade.png','desc'=>'Degradê profissional'],
        ['nome'=>'Corte Navalhado','img'=>'corte-degrade-1.png','desc'=>'Acabamento com navalha'],
        ['nome'=>'Designer Barba','img'=>'corte-mas.png','desc'=>'Design e tratamento da barba'],
        ['nome'=>'Barba Moderna','img'=>'designer-barba.png','desc'=>'Acabamento impecável']
      ];
      foreach($servicos_destaque as $servico){ ?>
      <div class="col-lg-4 col-md-6 mb-4">
        <div class="service-item-modern" style="background:#fff;border-radius:14px;overflow:hidden;box-shadow:0 5px 20px rgba(0,0,0,.08);transition:.25s">
          <div class="service-img-wrapper" style="position:relative;overflow:hidden;height:250px">
            <a href="https://agenda.jacycabeleireiro.com/" rel="noopener">
              <img src="images/corte-cabelo/<?php echo $servico['img'] ?>" alt="<?php echo e($servico['nome']) ?>" loading="lazy" decoding="async" width="640" height="400" style="width:100%;height:100%;object-fit:cover">
            </a>
          </div>
          <div style="padding:22px">
            <h4 class="font-weight-bold mb-2"><?php echo $servico['nome'] ?></h4>
            <p class="text-muted mb-3"><?php echo $servico['desc'] ?></p>
            <a class="btn btn-warning btn-block" href="https://agenda.jacycabeleireiro.com/">Agendar Agora</a>
          </div>
        </div>
      </div>
      <?php } ?>
    </div>
  </div>
</div>

<!-- =================== SOBRE NÓS =================== -->
<section class="about_section" style="background:#fff;padding:0">
  <div class="container-fluid">
    <div class="row align-items-center">
      <div class="col-md-6 px-0">
        <div class="img-box">
          <?php if($url_video != "" && $posicao_video == 'sobre'){ ?>
            <iframe width="100%" height="450" src="<?php echo e($url_video) ?>" title="Vídeo Jacy Cabeleireiro" frameborder="0" loading="lazy" referrerpolicy="strict-origin-when-cross-origin" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
          <?php } else { ?>
            <img src="images/<?php echo e($imagem_sobre) ?>" class="box_img" alt="Sobre Jacy Cabeleireiro" loading="lazy" decoding="async" width="1280" height="720" style="width:100%;height:450px;object-fit:cover">
          <?php } ?>
        </div>
      </div>
      <div class="col-md-6">
        <div class="detail-box" style="padding:32px;color:#333">
          <div class="heading_container mb-3"><h2 class="display-4 font-weight-bold" style="color:#333">Sobre Nós</h2></div>
          <p style="font-size:1.05rem;line-height:1.8;color:#555"><?php echo $config['texto_sobre'] ?? ($texto_sobre ?? '') ?></p>
          <div class="mt-3 d-flex" style="gap:.75rem">
            <a href="#empresa" data-toggle="modal" class="btn btn-outline-dark btn-lg"><i class="fa fa-info-circle" aria-hidden="true"></i> Nossa História</a>
            <a href="https://agenda.jacycabeleireiro.com/" target="_blank" rel="noopener" class="btn btn-warning btn-lg">Agendar Visita</a>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<?php if($url_video != "" && $posicao_video == 'abaixo'){ ?>
<div style="margin:0">
  <div class="container">
    <iframe class="video_mobile" width="100%" height="480" src="<?php echo e($url_video) ?>" title="Vídeo institucional" frameborder="0" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen style="border-radius:12px;box-shadow:0 10px 30px rgba(0,0,0,.2)"></iframe>
  </div>
</div>
<?php } ?>

<!-- =================== PRODUTOS =================== -->
<?php 
$prods = $pdo->query("SELECT * FROM produtos WHERE estoque > 0 AND valor_venda > 0 ORDER BY id DESC LIMIT 8")->fetchAll(PDO::FETCH_ASSOC);
if(@count($prods) > 0){
?>
<section class="product_section layout_padding" style="background:#f8f9fa">
  <div class="container-fluid">
    <div class="heading_container heading_center mb-4">
      <h2 class="display-4 font-weight-bold">Nossos Produtos</h2>
      <p class="lead text-muted">Produtos profissionais selecionados</p>
    </div>
    <div class="row">
      <?php foreach ($prods as $p):
        $nome = $p['nome'];
        $valorF = number_format($p['valor_venda'], 2, ',', '.');
        $foto = $p['foto'];
        $descricao = $p['descricao'];
        $estoque = $p['estoque'];
        $nomeF = mb_strimwidth($nome, 0, 25, "...");
      ?>
      <div class="col-sm-6 col-md-3 mb-4">
        <div class="box" style="border-radius:14px;overflow:hidden;background:#fff;box-shadow:0 5px 15px rgba(0,0,0,.08)">
          <div class="img-box">
            <img src="sistema/painel/img/produtos/<?php echo e($foto) ?>" title="<?php echo e($descricao) ?>" alt="<?php echo e($nome) ?>" loading="lazy" decoding="async" width="480" height="480" style="width:100%;height:250px;object-fit:cover">
          </div>
          <div class="detail-box" style="padding:18px">
            <h5 class="font-weight-bold mb-2" style="min-height:44px"><?php echo $nomeF ?></h5>
            <p class="text-muted small mb-3" style="min-height:40px"><?php echo mb_strimwidth($descricao, 0, 60, "...") ?></p>
            <div class="d-flex justify-content-between align-items-center mb-3">
              <span style="font-size:22px;color:#28a745;font-weight:700">R$ <?php echo $valorF ?></span>
              <span class="text-muted small"><i class="fa fa-box" aria-hidden="true"></i> <?php echo (int)$estoque ?> disp.</span>
            </div>
            <a target="_blank" rel="noopener nofollow" href="https://api.whatsapp.com/send?1=pt_BR&phone=<?php echo e($whats) ?>&text=<?php echo rawurlencode("Olá! Gostaria de comprar: $nome - Preço: R$ $valorF") ?>" class="btn btn-success btn-block"><i class="fa fa-whatsapp" aria-hidden="true"></i> Comprar no WhatsApp</a>
          </div>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="btn-box text-center mt-2">
      <a href="produtos" class="btn btn-primary btn-lg"><i class="fa fa-arrow-right" aria-hidden="true"></i> Ver Todos os Produtos</a>
    </div>
  </div>
</section>
<?php } ?>

<!-- =================== CTA =================== -->
<section class="cta-section" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);padding:56px 0;color:#fff;text-align:center">
  <div class="container">
    <h2 class="display-4 font-weight-bold">Transforme Seu Visual Hoje!</h2>
    <p class="lead">Pronto para um visual moderno e profissional? Agende agora e garanta seu horário!</p>
    <div class="d-flex justify-content-center" style="gap:.75rem;flex-wrap:wrap">
      <a href="https://agenda.jacycabeleireiro.com/" target="_blank" rel="noopener" class="btn btn-warning btn-lg" style="padding:16px 40px"><i class="fa fa-calendar" aria-hidden="true"></i> Agendar Agora</a>
      <a href="https://api.whatsapp.com/send?1=pt_BR&phone=<?php echo e($whats) ?>&text=<?php echo rawurlencode('Olá! Gostaria de mais informações sobre os serviços.') ?>" target="_blank" rel="noopener nofollow" class="btn btn-light btn-lg" style="padding:16px 40px"><i class="fa fa-whatsapp" aria-hidden="true"></i> Falar no WhatsApp</a>
    </div>
  </div>
</section>

<!-- =================== DEPOIMENTOS =================== -->
<?php 
$deps = $pdo->query("SELECT * FROM comentarios WHERE ativo = 'Sim' ORDER BY id DESC LIMIT 6")->fetchAll(PDO::FETCH_ASSOC);
if(@count($deps) > 0){
?>
<section class="client_section layout_padding-bottom" style="background:#f8f9fa" id="depoimentos">
  <div class="container">
    <div class="heading_container text-center mb-5">
      <h2 class="display-4 font-weight-bold">O Que Dizem Nossos Clientes</h2>
      <p class="lead text-muted">Avaliações reais de quem confia no nosso trabalho</p>
    </div>
    <div class="client_container">
      <div class="carousel-wrap">
        <div class="owl-carousel client_owl-carousel" aria-label="Carrossel de depoimentos">
          <?php foreach ($deps as $d):
            $nome = $d['nome'];
            $texto = $d['texto'];
            $foto = $d['foto'];
            $data = $d['data'] ?? date('Y-m-d');
            $avaliacao = $d['avaliacao'] ?? 5;
          ?>
          <div class="item">
            <div class="testimonial-card" style="background:#fff;border-radius:14px;padding:26px;box-shadow:0 5px 20px rgba(0,0,0,.08);margin:10px">
              <div class="testimonial-meta" style="display:flex;align-items:center;gap:14px;margin-bottom:10px">
                <img src="sistema/painel/img/comentarios/<?php echo e($foto) ?>" alt="<?php echo e($nome) ?>" class="testimonial-avatar img-1" loading="lazy" decoding="async" width="80" height="80" onerror="this.onerror=null;this.src='sistema/painel/img/comentarios/sem-foto.jpg';">
                <div class="testimonial-info">
                  <h5 class="mb-1" style="font-weight:700;color:#333"><?php echo $nome ?></h5>
                  <div class="rating" aria-label="Avaliação <?php echo $avaliacao ?> estrelas" style="display:flex;gap:3px">
                    <?php for($e=1;$e<=5;$e++){ echo ($e <= $avaliacao) ? '<i class="fa fa-star" style="color:#ffc107" aria-hidden="true"></i>' : '<i class="fa fa-star-o" style="color:#ddd" aria-hidden="true"></i>'; } ?>
                  </div>
                  <p class="testimonial-date mb-0" style="font-size:12px;color:#888"><i class="fa fa-clock-o" aria-hidden="true"></i> <?php echo date('d/m/Y', strtotime($data)) ?></p>
                </div>
              </div>
              <p class="mt-2" style="font-size:15px;line-height:1.75;color:#555">"<?php echo $texto ?>"</p>
              <div class="mt-3"><span class="badge badge-success"><i class="fa fa-check-circle" aria-hidden="true"></i> Cliente Verificado</span></div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <div class="text-center mt-4">
      <a href="#" data-toggle="modal" data-target="#modalComentario" class="btn btn-primary btn-lg"><i class="fa fa-edit" aria-hidden="true"></i> Deixe Seu Depoimento</a>
    </div>
  </div>
</section>
<?php } ?>

<!-- =================== CONTATO =================== -->
<section class="contact_section layout_padding-bottom" id="contato">
  <div class="container">
    <div class="heading_container text-center mb-4">
      <h2 class="display-4 font-weight-bold mb-2">Entre em Contato</h2>
      <h4 class="text-muted">Transforme seu visual: pronto para um visual moderno? Vamos conversar!</h4>
    </div>
    <div class="row">
      <div class="col-md-6">
        <div class="form_container">
          <form id="form-email" novalidate>
            <input type="hidden" name="recaptcha_token" id="recaptcha_token" value="">
            <input type="hidden" name="recaptcha_action" value="contato">
            <input type="text" name="website" style="display:none" tabindex="-1" autocomplete="off" aria-hidden="true">
            <div class="form-group">
              <label for="nome" class="sr-only">Nome Completo</label>
              <input type="text" name="nome" id="nome" class="form-control" placeholder="Seu Nome Completo *" required minlength="3" pattern="[A-Za-zÀ-ÿ\s]+" autocomplete="name">
              <div class="invalid-feedback">Por favor, insira seu nome completo (mín. 3 caracteres)</div>
            </div>
            <div class="form-group">
              <label for="telefone" class="sr-only">Telefone</label>
              <input type="tel" name="telefone" id="telefone" class="form-control" placeholder="(00) 00000-0000 *" required autocomplete="tel">
              <div class="invalid-feedback">Por favor, insira um telefone válido</div>
            </div>
            <div class="form-group">
              <label for="email" class="sr-only">Email</label>
              <input type="email" name="email" id="email" class="form-control" placeholder="seu@email.com *" required maxlength="120" autocomplete="email">
              <div class="invalid-feedback">Por favor, insira um email válido</div>
            </div>
            <div class="form-group">
              <label for="mensagem" class="sr-only">Mensagem</label>
              <textarea name="mensagem" id="mensagem" class="form-control message-box" placeholder="Como podemos ajudar? *" rows="5" required minlength="10"></textarea>
              <div class="invalid-feedback">Por favor, insira uma mensagem (mín. 10 caracteres)</div>
            </div>
            <div class="form-check mb-3">
              <input type="checkbox" class="form-check-input" id="privacidade" required>
              <label class="form-check-label" for="privacidade">
                Aceito a <a href="#politicaModal" data-toggle="modal">Política de Privacidade</a> e os <a href="#termosModal" data-toggle="modal">Termos de Uso</a> *
              </label>
              <div class="invalid-feedback">Você precisa aceitar a política de privacidade</div>
            </div>
            <div class="btn_box">
              <button type="submit" class="btn btn-warning btn-lg btn-block"><i class="fa fa-paper-plane" aria-hidden="true"></i> ENVIAR MENSAGEM</button>
            </div>
          </form>
          <div id="mensagem-form" class="mt-3" role="alert"></div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="map_container" style="border-radius:12px;overflow:hidden;box-shadow:0 10px 30px rgba(0,0,0,.15)">
          <?php echo $mapa ?>
        </div>
        <div class="mt-4 p-4" style="background:#f8f9fa;border-radius:12px">
          <h5 class="font-weight-bold mb-3"><i class="fa fa-info-circle" aria-hidden="true"></i> Informações de Contato</h5>
          <p class="mb-2"><i class="fa fa-whatsapp text-success" aria-hidden="true"></i> <strong>WhatsApp:</strong> <a href="https://api.whatsapp.com/send?1=pt_BR&phone=<?php echo e($whats) ?>" target="_blank" rel="noopener"><?php echo e($tel_whatsapp) ?></a></p>
          <p class="mb-2"><i class="fa fa-map-marker text-danger" aria-hidden="true"></i> <strong>Endereço:</strong> <?php echo e($endereco) ?></p>
          <p class="mb-0"><i class="fa fa-clock-o text-primary" aria-hidden="true"></i> <strong>Horário:</strong> Terça a Sexta, 09:00h às 18:00h</p>
        </div>
      </div>
    </div>
  </div>
</section>

<?php require_once("rodape.php"); ?>

<!-- =================== MODAIS =================== -->
<div class="modal fade" id="modalComentario" tabindex="-1" role="dialog" aria-labelledby="titulo-modal-comentario" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content" style="border-radius:12px">
      <div class="modal-header" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:#fff">
        <h5 class="modal-title font-weight-bold" id="titulo-modal-comentario"><i class="fa fa-star" aria-hidden="true"></i> Deixe Seu Depoimento</h5>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Fechar"><span aria-hidden="true">&times;</span></button>
      </div>
      <form id="form" enctype="multipart/form-data" novalidate>
        <div class="modal-body" style="padding:28px;max-height:75vh;overflow:auto">
          <p class="text-muted mb-3">Sua opinião é muito importante para nós!</p>
          <div class="form-group">
            <label for="nome_cliente" class="font-weight-bold"><i class="fa fa-user" aria-hidden="true"></i> Nome Completo *</label>
            <input type="text" class="form-control" id="nome_cliente" name="nome" placeholder="Digite seu nome completo" required>
          </div>
          <div class="form-group">
            <label for="texto_cliente" class="font-weight-bold"><i class="fa fa-comment" aria-hidden="true"></i> Seu Depoimento * <small class="text-muted">(Até 500 caracteres)</small></label>
            <textarea maxlength="500" class="form-control" id="texto_cliente" name="texto" placeholder="Conte sua experiência..." rows="5" required></textarea>
            <small class="text-muted"><span id="char-count">0</span>/500 caracteres</small>
          </div>
          <div class="form-group">
            <label class="font-weight-bold"><i class="fa fa-star" aria-hidden="true"></i> Sua Avaliação *</label>
            <div class="rating-input d-flex" style="gap:8px;font-size:28px;cursor:pointer">
              <i class="fa fa-star" data-rating="1" aria-hidden="true"></i>
              <i class="fa fa-star" data-rating="2" aria-hidden="true"></i>
              <i class="fa fa-star" data-rating="3" aria-hidden="true"></i>
              <i class="fa fa-star" data-rating="4" aria-hidden="true"></i>
              <i class="fa fa-star" data-rating="5" aria-hidden="true"></i>
            </div>
            <input type="hidden" name="avaliacao" id="avaliacao" value="5">
          </div>
          <div class="row">
            <div class="col-md-8">
              <label class="font-weight-bold"><i class="fa fa-camera" aria-hidden="true"></i> Foto (Opcional)</label>
              <input class="form-control" type="file" name="foto" onChange="carregarImg();" id="foto" accept="image/*">
              <small class="text-muted">JPG/PNG até 2MB</small>
            </div>
            <div class="col-md-4 text-center">
              <img src="sistema/painel/img/comentarios/sem-foto.jpg" width="100" id="target" alt="Prévia" style="border-radius:50%;border:3px solid #ddd">
            </div>
          </div>
          <input type="hidden" name="id" id="id">
          <input type="hidden" name="cliente" value="1">
          <div id="mensagem-comentario" class="mt-3"></div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fa fa-times" aria-hidden="true"></i> Fechar</button>
          <button type="submit" class="btn btn-primary"><i class="fa fa-check" aria-hidden="true"></i> Enviar Depoimento</button>
        </div>
      </form>
    </div>
  </div>
</div>

<div id="empresa" class="modal fade" tabindex="-1" role="dialog" aria-labelledby="titulo-modal-empresa" aria-hidden="true">
  <div class="modal-dialog modal-lg" role="document">
    <div class="modal-content" style="border-radius:15px">
      <div class="modal-header" style="background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);color:white">
        <h3 class="modal-title font-weight-bold" id="titulo-modal-empresa"><i class="fa fa-building" aria-hidden="true"></i> Jacy Cabeleireiro - Nossa História</h3>
        <button type="button" class="close text-white" data-dismiss="modal" aria-label="Fechar"><span aria-hidden="true">&times;</span></button>
      </div>
      <div class="modal-body" style="padding:40px;max-height:75vh;overflow:auto">
        <h4 class="font-weight-bold mb-3">Nossa Missão</h4>
        <p style="text-align:justify;line-height:1.8"><?php echo $config['missao'] ?? 'Nossa missão é oferecer os melhores cursos e serviços com agilidade e responsabilidade.'; ?></p>
        <div class="text-center mb-4">
          <h4 class="font-weight-bold mb-3">Conheça Nossa História em Vídeo</h4>
          <iframe width="100%" height="450" src="https://www.youtube-nocookie.com/embed/E_tBbvQJ1mA?controls=0" title="Vídeo História Jacy Cabeleireiro" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen loading="lazy" style="border-radius:10px;box-shadow:0 10px 30px rgba(0,0,0,0.2)"></iframe>
        </div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-dismiss="modal"><i class="fa fa-times" aria-hidden="true"></i> Fechar</button>
      </div>
    </div>
  </div>
</div>

<!-- =================== RECAPTCHA V3 (Async/Defer) =================== -->
<?php if(!empty($recaptcha_site_key)): ?>
<script src="https://www.google.com/recaptcha/api.js?render=<?php echo e($recaptcha_site_key); ?>" async defer></script>
<?php endif; ?>

<!-- =================== JS OTIMIZADO (Defer) =================== -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.16/jquery.mask.min.js" defer></script>

<script>
// Lazy load otimizado
(function(){const imgs=document.querySelectorAll('img[loading="lazy"]');const observer=new IntersectionObserver((e,o)=>{e.forEach(e=>{if(e.isIntersecting){const t=e.target;t.complete?t.classList.add('loaded'):t.addEventListener('load',()=>t.classList.add('loaded')),t.setAttribute('data-loaded','true')}})},{rootMargin:'50px'});imgs.forEach(e=>observer.observe(e))})();

// DOMContentLoaded optimizado
document.addEventListener('DOMContentLoaded',function(){
  // Máscara telefone
  if(typeof $!=='undefined'&&$('#telefone').length){$('#telefone').mask('(00) 00000-0000')}
  
  // Contador caracteres
  if(typeof $!=='undefined'){$('#texto_cliente').on('input',function(){$('#char-count').text($(this).val().length)})}
  
  // Rating stars
  if(typeof $!=='undefined'){$('.rating-input i').on('click',function(){var r=$(this).data('rating');$('#avaliacao').val(r);$('.rating-input i').each(function(i){$(this).toggleClass('fa-star',i<r).toggleClass('fa-star-o',i>=r)})})}
});

// Preview imagem
function carregarImg(){var t=document.getElementById('target'),f=document.querySelector("#foto").files[0],r=new FileReader();r.onloadend=function(){t.src=r.result};if(f)r.readAsDataURL(f);else t.src="sistema/painel/img/comentarios/sem-foto.jpg"}

// AJAX Contato
if(typeof $!=='undefined'){
  $("#form-email").submit(function(e){
    e.preventDefault();
    if($('input[name="website"]').val()!==''){return false}
    if(!this.checkValidity()){$(this).addClass('was-validated');return false}
    
    var $form=$(this),$btn=$form.find('button[type="submit"]'),btnText=$btn.html();
    $btn.html('<span class="spinner-border spinner-border-sm"></span> Enviando...').prop('disabled',true);
    
    <?php if(!empty($recaptcha_site_key)): ?>
    grecaptcha.ready(function(){grecaptcha.execute('<?php echo e($recaptcha_site_key); ?>',{action:'contato'}).then(function(token){$('#recaptcha_token').val(token);enviarForm($form,$btn,btnText)})});
    <?php else: ?>
    enviarForm($form,$btn,btnText);
    <?php endif; ?>
  });
  
  function enviarForm($form,$btn,btnText){
    $.ajax({
      url:'ajax/enviar-email.php',type:'POST',data:new FormData($form[0]),
      success:function(msg){
        $btn.html(btnText).prop('disabled',false);
        $('#mensagem-form').removeClass();
        if(msg.trim()=="Enviado com Sucesso"){
          $('#mensagem-form').addClass('alert alert-success').html('<i class="fa fa-check-circle"></i> Mensagem enviada!');
          $form[0].reset();$form.removeClass('was-validated');
        }else{$('#mensagem-form').addClass('alert alert-danger').html('<i class="fa fa-exclamation-triangle"></i> '+msg)}
        $('html,body').animate({scrollTop:$('#mensagem-form').offset().top-100},400);
      },
      error:function(){$btn.html(btnText).prop('disabled',false);$('#mensagem-form').addClass('alert alert-danger').html('<i class="fa fa-exclamation-triangle"></i> Erro ao enviar')},
      cache:false,contentType:false,processData:false
    });
  }
  
  // AJAX Comentário
  $("#form").submit(function(e){
    e.preventDefault();
    var $form=$(this),$btn=$form.find('button[type="submit"]'),btnText=$btn.html();
    $btn.html('<span class="spinner-border spinner-border-sm"></span> Enviando...').prop('disabled',true);
    $.ajax({
      url:'sistema/painel/paginas/comentarios/salvar.php',type:'POST',data:new FormData(this),
      success:function(msg){
        $btn.html(btnText).prop('disabled',false);
        $('#mensagem-comentario').removeClass();
        if(msg.trim()=="Salvo com Sucesso"){
          $('#mensagem-comentario').addClass('alert alert-success').html('<i class="fa fa-check-circle"></i> Enviado!');
          $('#nome_cliente,#texto_cliente,#foto').val('');$('#char-count').text('0');$('#target').attr('src','sistema/painel/img/comentarios/sem-foto.jpg');
          $('#avaliacao').val('5');$('.rating-input i').removeClass('fa-star-o').addClass('fa-star');
          setTimeout(function(){$('#modalComentario').modal('hide');$('#mensagem-comentario').html('')},2500);
        }else{$('#mensagem-comentario').addClass('alert alert-danger').html('<i class="fa fa-exclamation-triangle"></i> '+msg)}
      },
      error:function(){$btn.html(btnText).prop('disabled',false);$('#mensagem-comentario').addClass('alert alert-danger').html('<i class="fa fa-exclamation-triangle"></i> Erro')},
      cache:false,contentType:false,processData:false
    });
  });
  
  // Reset modal
  $('#modalComentario').on('hidden.bs.modal',function(){$('#form')[0].reset();$('#mensagem-comentario').html('');$('#char-count').text('0');$('#target').attr('src','sistema/painel/img/comentarios/sem-foto.jpg');$('#avaliacao').val('5');$('.rating-input i').removeClass('fa-star-o').addClass('fa-star')});
}

// Fix modais
(function($){
  if(!$)return;
  ['#empresa','#modalComentario','#termosModal','#politicaModal'].forEach(function(sel){
    var $m=$(sel);if(!$m.length)return;
    $m.appendTo('body').modal({backdrop:true,keyboard:true,show:false});
    $m.on('show.bs.modal',function(){$('.modal-backdrop').remove();$('body').addClass('modal-open')});
    $m.on('hidden.bs.modal',function(){$('.modal-backdrop').remove();$('body').removeClass('modal-open');document.body.style.paddingRight='';document.body.style.overflow=''});
    $m.find('.close,[data-dismiss="modal"]').off('click.fix').on('click.fix',function(e){e.preventDefault();e.stopPropagation();$m.modal('hide')});
  });
  $(document).off('keydown.fixmodal').on('keydown.fixmodal',function(e){if(e.key==='Escape'||e.keyCode===27)$('.modal.show').modal('hide')});
  $(document).off('mousedown.fixoutside').on('mousedown.fixoutside',function(e){var $o=$('.modal.show');if(!$o.length)return;var $d=$o.find('.modal-dialog');if(!$d.is(e.target)&&$d.has(e.target).length===0){$o.modal('hide')}});
})(window.jQuery);

// Body offset
(function($){if(!$)return;var $n=$('.navbar.fixed-top,header.fixed-top,.site-header.fixed-top').first();function a(){if(!$n.length)return;var h=$n.outerHeight()||0;document.body.style.paddingTop=h+'px'}if($n.length){a();$(window).on('resize orientationchange',a)}})(window.jQuery);
</script>