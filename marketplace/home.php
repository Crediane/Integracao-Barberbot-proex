<?php
@session_start();

$bb_uri_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$bb_tenant_slug = '';
$bb_tenant_slug_url = '';

if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $bb_uri_path, $bb_match_tenant)) {
    $bb_tenant_slug_url = (string)$bb_match_tenant[1];
}

if ($bb_tenant_slug_url !== '') {
    $bb_tenant_slug = $bb_tenant_slug_url;
} elseif (!empty($_GET['loja'])) {
    $bb_tenant_slug = (string)$_GET['loja'];
} elseif (!empty($_POST['loja'])) {
    $bb_tenant_slug = (string)$_POST['loja'];
} elseif (!empty($bb_tenant)) {
    $bb_tenant_slug = (string)$bb_tenant;
} elseif (!empty($_SESSION['tenant_slug'])) {
    $bb_tenant_slug = (string)$_SESSION['tenant_slug'];
}

$bb_tenant_slug = preg_replace('/[^a-z0-9_-]/i', '', $bb_tenant_slug);
$bb_base_publica = $bb_tenant_slug !== '' ? '/loja/' . $bb_tenant_slug . '/' : '/';

if (!function_exists('bb_url_publica_home')) {
    function bb_url_publica_home($caminho = '')
    {
        global $bb_base_publica;
        $caminho = ltrim((string)$caminho, '/');
        return $caminho === '' ? $bb_base_publica : $bb_base_publica . $caminho;
    }
}

if (!function_exists('bb_asset_publica_home')) {
    function bb_asset_publica_home($caminho = '', $fallback = '')
    {
        $caminho = ltrim((string)$caminho, '/');
        $fallback = ltrim((string)$fallback, '/');

        if ($caminho === '') {
            return $fallback !== '' ? '/' . $fallback : '/';
        }

        if (preg_match('~^(https?:)?//~i', $caminho) || stripos($caminho, 'data:') === 0) {
            return $caminho;
        }

        $docroot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        $baseDir = rtrim((string)__DIR__, '/');
        $caminhoNormalizado = str_replace(['\\', '//'], ['/', '/'], $caminho);

        if ($docroot !== '' && is_file($docroot . '/' . $caminhoNormalizado)) {
            return '/' . $caminhoNormalizado;
        }

        if (is_file($baseDir . '/' . $caminhoNormalizado)) {
            return '/' . $caminhoNormalizado;
        }

        if ($fallback !== '') {
            $fallbackNormalizado = str_replace(['\\', '//'], ['/', '/'], $fallback);
            if (($docroot !== '' && is_file($docroot . '/' . $fallbackNormalizado)) || is_file($baseDir . '/' . $fallbackNormalizado)) {
                return '/' . $fallbackNormalizado;
            }
        }

        return '/' . $caminhoNormalizado;
    }
}


if (!function_exists('bb_asset_publica_home_existe')) {
    function bb_asset_publica_home_existe($caminho = '')
    {
        $caminho = ltrim((string)$caminho, '/');

        if ($caminho === '') {
            return '';
        }

        if (preg_match('~^(https?:)?//~i', $caminho) || stripos($caminho, 'data:') === 0) {
            return $caminho;
        }

        $docroot = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
        $baseDir = rtrim((string)__DIR__, '/');
        $caminhoNormalizado = str_replace(['\\', '//'], ['/', '/'], $caminho);

        if ($docroot !== '' && is_file($docroot . '/' . $caminhoNormalizado)) {
            return '/' . $caminhoNormalizado;
        }

        if (is_file($baseDir . '/' . $caminhoNormalizado)) {
            return '/' . $caminhoNormalizado;
        }

        return '';
    }
}

if (!function_exists('bb_asset_publica_home_primeiro_existente')) {
    function bb_asset_publica_home_primeiro_existente(array $candidatos = [], $fallback = '')
    {
        foreach ($candidatos as $candidato) {
            $url = bb_asset_publica_home_existe($candidato);
            if ($url !== '') {
                return $url;
            }
        }

        if ($fallback !== '') {
            return bb_asset_publica_home($fallback);
        }

        return '';
    }
}

if (!function_exists('bb_normalizar_assets_home')) {
    function bb_normalizar_assets_home($html)
    {
        $html = (string)$html;

        return preg_replace_callback(
            '~(<(?:script|img|source|link)\b[^>]*\b(?:src|href)=(["\']))(?!https?:|//|/|data:|#|mailto:|tel:)([^"\']+)(\2)~i',
            function ($m) {
                $caminho = trim((string)$m[3]);
                if ($caminho === '') {
                    return $m[0];
                }
                return $m[1] . '/' . ltrim($caminho, '/') . $m[4];
            },
            $html
        );
    }
}

ob_start();
require_once("cabecalho.php");
$bb_cabecalho_html = ob_get_clean();
echo bb_normalizar_assets_home($bb_cabecalho_html);

if ($bb_tenant_slug_url === '' && $bb_tenant_slug === '' && !empty($bb_tenant)) {
    $bb_tenant_slug = preg_replace('/[^a-z0-9_-]/i', '', (string)$bb_tenant);
    $bb_base_publica = $bb_tenant_slug !== '' ? '/loja/' . $bb_tenant_slug . '/' : '/';
}

// Redirecionos conforme sessão (mesma lógica)
if (!isset($_SESSION['usuario_logado_pagina'])) {
    if ($entrada == 'Login') {
        echo '<script>window.location=' . json_encode(bb_url_publica_home('sistema/acesso')) . '</script>';
    }
    if ($entrada == 'Agendamento') {
        echo '<script>window.location=' . json_encode(bb_url_publica_home('agendamento')) . '</script>';
    }
}
?>

<style id="bb-home-above-fold-fix">
  .slider_section .carousel-inner,
  .slider_section .carousel-item{min-height:360px;}
  .slider_section .detail-box{min-height:260px;}
  .product_owl-carousel:not(.owl-loaded),
  .client_owl-carousel:not(.owl-loaded){display:flex;gap:18px;overflow:hidden;}
  .product_owl-carousel:not(.owl-loaded) .item{flex:0 0 33.333%;}
  .client_owl-carousel:not(.owl-loaded) .item{flex:0 0 50%;}
  @media (max-width:767px){
    .slider_section .carousel-inner,.slider_section .carousel-item{min-height:330px;}
    .slider_section .detail-box{min-height:255px;}
    .product_owl-carousel:not(.owl-loaded) .item,.client_owl-carousel:not(.owl-loaded) .item{flex:0 0 100%;}
  }
</style>

<?php
$query = $pdo->query("SELECT * FROM textos_index ORDER BY id asc");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
if (count($res) > 0) {
?>
    <section class="slider_section">
        <div id="customCarousel1" class="carousel slide" data-interval="5000">
            <div class="carousel-inner">
                <?php
                foreach ($res as $i => $item) {
                    $ativo = ($i == 0) ? 'active' : '';
                ?>
                    <div class="carousel-item <?php echo $ativo; ?>">
                        <div class="container">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="detail-box">
                                        <h1><?php echo htmlspecialchars($item['titulo']); ?></h1>
                                        <p><?php echo htmlspecialchars($item['descricao']); ?></p>
                                        <div class="btn-box">
                                            <a href="http://api.whatsapp.com/send?1=pt_BR&phone=<?php echo $tel_whatsapp; ?>" target="_blank" rel="noopener" class="btn btn-lg" style="background-color:#007bff; color:white; font-size:1.6rem; padding:12px 34px; border-radius:50px; text-decoration:none; display:inline-block; font-weight:bold; box-shadow:0px 4px 8px rgba(0,0,0,0.2);" onmouseover="this.style.backgroundColor='#0056b3';" onmouseout="this.style.backgroundColor='#007bff';">
                                                Contate-nos
                                            </a>
                                        </div>
                                    </div>
                                </div>
                                </div>
                        </div>
                    </div>
                <?php } ?>
            </div>

            <div class="container">
                <div class="carousel_btn-box">
                    <a class="carousel-control-prev" href="#customCarousel1" role="button" data-slide="prev" aria-label="Anterior">
                        <i class="fa fa-arrow-left" aria-hidden="true"></i><span class="sr-only">Previous</span>
                    </a>
                    <a class="carousel-control-next" href="#customCarousel1" role="button" data-slide="next" aria-label="Próximo">
                        <i class="fa fa-arrow-right" aria-hidden="true"></i><span class="sr-only">Next</span>
                    </a>
                </div>
            </div>
        </div>
    </section>
    <?php } ?>
</div>

<section class="product_section layout_padding">
    <div class="container">
        <div class="heading_container heading_center">
            <h2>Nossos Serviços</h2>
            <p class="col-lg-8 px-0">
                <?php
                $query_cat = $pdo->query("SELECT * FROM cat_servicos ORDER BY id ASC");
                $categorias = $query_cat->fetchAll(PDO::FETCH_ASSOC);
                $nomes_categorias = [];
                foreach ($categorias as $cat) {
                    $nomes_categorias[] = $cat['nome'];
                }
                echo implode(' / ', $nomes_categorias);
                ?>
            </p>
        </div>

        <?php
        $query = $pdo->query("SELECT * FROM servicos where ativo = 'Sim' ORDER BY id asc");
        $res = $query->fetchAll(PDO::FETCH_ASSOC);
        if (count($res) > 0) {
        ?>
            <div class="product_container">
                <div class="product_owl-carousel owl-carousel owl-theme">
                    <?php
                    foreach ($res as $i => $item) {
                        $valorF = number_format($item['valor'], 2, ',', '.');
                        $nomeF  = mb_strimwidth($item['nome'], 0, 20, "...");
                    ?>
                        <div class="item">
                            <div class="box">
                                <div class="img-box">
                                    <?php
                                    $path = "sistema/painel/img/servicos/" . $item['foto'];
                                    $webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $path);
                                    $src  = file_exists($webp) ? $webp : $path;
                                    
                                    // OTIMIZAÇÃO: serviços ficam abaixo da primeira dobra; não devem competir com o banner principal
                                    $loading_attr = 'loading="lazy" fetchpriority="low"';
                                    ?>
                                    <img src="<?php echo htmlspecialchars(bb_asset_publica_home($src)); ?>" alt="<?php echo htmlspecialchars($item['nome']); ?>" title="<?php echo htmlspecialchars($item['nome']); ?>" width="480" height="320" <?php echo $loading_attr; ?> decoding="async" style="width:100%;height:auto;object-fit:cover" />
                                </div>
                                <div class="detail-box">
                                    <h4><?php echo htmlspecialchars($nomeF); ?></h4>
                                    <h6 class="price"><span class="new_price">R$ <?php echo $valorF; ?></span></h6>
                                    <a href="<?php echo htmlspecialchars(bb_url_publica_home('agendamentos')); ?>">Agendar</a>
                                </div>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        <?php } ?>
    </div>
</section>
<?php
// O código para as seções restantes (galeria, sobre, produtos, etc.) já está otimizado
// na nossa conversa anterior. Vou inseri-lo aqui para manter a consistência.
// Se precisar de alguma alteração específica, me avise.
?>

<?php
// A partir daqui, o código é a versão final e corrigida da nossa conversa anterior
// garantindo que todos os loops estejam padronizados e a lógica intacta.
?>

<?php
$res = $pdo->query("SELECT * FROM foto_admin ORDER BY id DESC LIMIT 12");
$dados = $res->fetchAll(PDO::FETCH_ASSOC);
if(count($dados) > 0){
?>
<div class="service pt-5 pb-3 bg-white" style="background:#020202;margin-top:0;">
    <div class="container">
        <div class="row">
            <?php foreach ($dados as $d): ?>
            <div class="col-lg-3 col-md-6 mb-4">
                <div class="service-item text-center bg-light p-3 rounded">
                    <div class="service-img mb-2">
                        <?php
                        $path = "sistema/painel/img/foto_admin/{$d['imagem']}";
                        $webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $path);
                        $src  = file_exists($webp) ? $webp : $path;
                        ?>
                        <img src="<?php echo htmlspecialchars(bb_asset_publica_home($src)); ?>" alt="<?= htmlspecialchars($d['titulo']); ?>" title="<?= htmlspecialchars($d['titulo']); ?>" width="600" height="250" loading="lazy" decoding="async" class="img-fluid rounded shadow-sm" style="height:250px;object-fit:cover;width:100%;">
                    </div>
                    <a class="btn btn-outline-dark mt-2 fw-bold" href="<?php echo htmlspecialchars(bb_url_publica_home('agendamentos')); ?>">
                        <?= htmlspecialchars($d['titulo']); ?>
                    </a>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div class="ver-mais-wrapper text-center mb-3">
            <a href="<?php echo htmlspecialchars(bb_url_publica_home('servicos')); ?>" class="btn btn-lg" style="background-color:#007bff; color:white; font-size:2rem; padding:14px 40px; border-radius:50px; text-decoration:none; display:inline-block; font-weight:bold; box-shadow:0px 4px 8px rgba(0,0,0,0.2);" onmouseover="this.style.backgroundColor='#0056b3';" onmouseout="this.style.backgroundColor='#007bff';">
                Ver mais Serviços
            </a>
        </div>
    </div>
</div>
<?php } ?>

<section class="about_section" style="background:#020202;">
    <div class="container-fluid">
        <div class="row">
            <div class="col-md-6 px-0">
                <div class="img-box">
                    <?php
                    $videoEmCima  = ($url_video !== '' && trim($posicao_video) === 'sobre');
                    $videoEmBaixo = ($url_video !== '' && trim($posicao_video) === 'abaixo');
                    if ($videoEmCima) {
                        echo '<iframe width="100%" height="350" src="'.htmlspecialchars($url_video).'" title="YouTube video player" frameborder="0" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>';
                    } else {
                        $imagemSobreTabela = trim((string)($imagem_sobre ?? ''));
                        $imagemSobreCandidatos = [];
                        $imagemSobreWebpCandidatos = [];
                        $imagemSobreFallback = 'sistema/painel/img/comentarios/sem-foto.jpg';

                        if ($imagemSobreTabela !== '') {
                            if (preg_match('~^(https?:)?//~i', $imagemSobreTabela) || stripos($imagemSobreTabela, 'data:') === 0) {
                                $imagemSobreCandidatos[] = $imagemSobreTabela;
                            } else {
                                $imagemSobreNormalizada = ltrim($imagemSobreTabela, '/');

                                if (strpos($imagemSobreNormalizada, '/') !== false) {
                                    $imagemSobreCandidatos[] = $imagemSobreNormalizada;
                                    $imagemSobreCandidatos[] = 'loja/' . $imagemSobreNormalizada;
                                } else {
                                    if ($bb_tenant_slug !== '') {
                                        $imagemSobreCandidatos[] = 'sistema/tenants/' . $bb_tenant_slug . '/images/' . $imagemSobreNormalizada;
                                    }
                                    $imagemSobreCandidatos[] = 'images/' . $imagemSobreNormalizada;
                                    $imagemSobreCandidatos[] = 'loja/images/' . $imagemSobreNormalizada;
                                }

                                foreach ($imagemSobreCandidatos as $candidatoSobre) {
                                    $imagemSobreWebpCandidatos[] = preg_replace('/\.(jpe?g|png)$/i', '.webp', $candidatoSobre);
                                }
                            }
                        }

                        $imagemSobreSrc = bb_asset_publica_home_primeiro_existente($imagemSobreCandidatos, $imagemSobreFallback);
                        $imagemSobreWebpSrc = bb_asset_publica_home_primeiro_existente($imagemSobreWebpCandidatos, '');
                        if ($imagemSobreWebpSrc === '/') {
                            $imagemSobreWebpSrc = '';
                        }
                    ?>
                    <picture>
                        <?php if ($imagemSobreWebpSrc !== '' && $imagemSobreWebpSrc !== $imagemSobreSrc) : ?>
                        <source type="image/webp" srcset="<?= htmlspecialchars($imagemSobreWebpSrc) ?>">
                        <?php endif; ?>
                        <img src="<?= htmlspecialchars($imagemSobreSrc) ?>" alt="Sobre nós" width="1200" height="675" loading="lazy" decoding="async" style="width:100%;height:auto;display:block">
                    </picture>
                    <?php } ?>
                </div>
            </div>
            <div class="col-md-5">
                <div class="detail-box">
                    <div class="heading_container">
                        <h2 class="" style="color:#FFF">Sobre Nós</h2>
                    </div>
                    <p class="detail_p_mt"><?= htmlspecialchars($texto_sobre) ?></p>
                    <a href="http://api.whatsapp.com/send?1=pt_BR&phone=<?= htmlspecialchars($tel_whatsapp) ?>" target="_blank" rel="noopener" class="btn btn-primary btn-lg rounded-pill px-4 fw-bold">
                        Mais Informações
                    </a>
                </div>
            </div>
        </div>
    </div>
</section>
<?php if ($videoEmBaixo) : ?>
<div>
    <iframe class="video_mobile" width="100%" src="<?= htmlspecialchars($url_video) ?>" title="YouTube video player" frameborder="0" loading="lazy" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
</div>
<?php endif; ?>
<!-- about section ends -->
<!-- simulador de protese capilar -->
<section class="simulador_section layout_padding" style="background:#111;">
  <div class="container">
    <div class="heading_container heading_center mb-5">
      <h2 style="color:#fff;">Faça sua Simulação Capilar</h2>
      <p class="col-lg-9 px-0 mx-auto" style="color:#d7d7d7;">
        Agora o próprio cliente pode fazer uma prévia da prótese capilar de forma prática.
        A simulação deve respeitar a cor original do cabelo e, quando houver fios grisalhos,
        manter o padrão visual natural do cliente.
      </p>
    </div>

    <div class="row">
      <div class="col-md-4 mb-4">
        <div class="box h-100 text-center p-4" style="background:#fff;border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.12);">
          <div style="width:72px;height:72px;line-height:72px;margin:0 auto 15px;border-radius:50%;background:#0d6efd;color:#fff;font-size:28px;font-weight:bold;">
            1
          </div>
          <h4 style="font-weight:700;">Envie sua foto</h4>
          <p style="margin-bottom:0;color:#555;">
            O cliente envia uma foto frontal para gerar a prévia da prótese capilar com base no seu visual atual.
          </p>
        </div>
      </div>

      <div class="col-md-4 mb-4">
        <div class="box h-100 text-center p-4" style="background:#fff;border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.12);">
          <div style="width:72px;height:72px;line-height:72px;margin:0 auto 15px;border-radius:50%;background:#0d6efd;color:#fff;font-size:28px;font-weight:bold;">
            2
          </div>
          <h4 style="font-weight:700;">Respeito à cor original</h4>
          <p style="margin-bottom:0;color:#555;">
            A simulação deve seguir a cor natural do cabelo. Se o cliente for grisalho, o resultado precisa manter esse padrão.
          </p>
        </div>
      </div>

      <div class="col-md-4 mb-4">
        <div class="box h-100 text-center p-4" style="background:#fff;border-radius:14px;box-shadow:0 8px 24px rgba(0,0,0,.12);">
          <div style="width:72px;height:72px;line-height:72px;margin:0 auto 15px;border-radius:50%;background:#0d6efd;color:#fff;font-size:28px;font-weight:bold;">
            3
          </div>
          <h4 style="font-weight:700;">Veja sua prévia</h4>
          <p style="margin-bottom:0;color:#555;">
            O cliente visualiza uma proposta mais fiel ao seu perfil antes do atendimento presencial.
          </p>
        </div>
      </div>
    </div>

    <div class="row mt-2">
      <div class="col-md-8 mx-auto">
        <div class="text-center p-4" style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.12);border-radius:16px;">
          <h4 style="color:#fff;font-weight:700;">Simule seu visual com mais naturalidade</h4>
          <p style="color:#d7d7d7;margin-bottom:20px;">
            Essa simulação é uma prévia visual para ajudar na decisão.
            A confirmação técnica da cor, densidade e modelo da prótese continua seguindo a avaliação profissional.
          </p>

          <button type="button"
             id="btn_abrir_modal_simulacao_cliente"
             class="btn btn-primary btn-lg"
             data-toggle="modal"
             data-target="#modalSimulacaoCliente"
             data-bs-toggle="modal"
             data-bs-target="#modalSimulacaoCliente"
             style="font-size:1.3rem; padding:12px 34px; border-radius:50px; text-decoration:none; display:inline-block; font-weight:bold; box-shadow:0px 4px 8px rgba(0,0,0,0.2);">
            Fazer minha Simulação
          </button>
        </div>
      </div>
    </div>
  </div>
</section>
<!-- fim simulador de protese capilar -->
<!-- produtos -->
<?php 
$query = $pdo->query("SELECT * FROM produtos where estoque > 0 and valor_venda > 0 ORDER BY id desc limit 8");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);
if($total_reg > 0){ 
?>
<section class="product_section layout_padding">
  <div class="container-fluid">
    <div class="heading_container heading_center"><h2>Nossos Produtos</h2></div>
    <div class="row">
      <?php 
      for($i=0; $i < $total_reg; $i++){
        $id        = $res[$i]['id'];
        $nome      = $res[$i]['nome'];   
        $valor     = $res[$i]['valor_venda'];
        $foto      = $res[$i]['foto'];
        $descricao = $res[$i]['descricao'];
        $valorF    = number_format($valor, 2, ',', '.');
        $nomeF     = mb_strimwidth($nome, 0, 23, "...");
      ?>
      <div class="col-sm-6 col-md-3 mb-4">
        <div class="box h-100 d-flex flex-column">
          <div class="img-box mb-3">
            <?php
              $path = "sistema/painel/img/produtos/$foto";
              $webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $path);
              $src  = file_exists($webp) ? $webp : $path;
            ?>
            <img
              src="<?php echo htmlspecialchars(bb_asset_publica_home($src)); ?>"
              title="<?php echo htmlspecialchars($descricao); ?>"
              alt="<?php echo htmlspecialchars($nome); ?>"
              width="480" height="360"
              loading="lazy" fetchpriority="low" decoding="async"
              sizes="(max-width: 576px) 44vw, (max-width: 992px) 22vw, 360px"
              class="img-fluid"
            />
          </div>
          <div class="detail-box text-center">
            <h5><?php echo $nomeF ?></h5>
            <h6 class="price"><span class="new_price">R$ <?php echo $valorF ?></span></h6>
            <a target="_blank" rel="noopener" href="http://api.whatsapp.com/send?1=pt_BR&phone=<?php echo $tel_whatsapp ?>&text=Ola, gostaria de saber mais informações sobre o produto <?php echo $nome ?>">
              Comprar Agora
            </a>
          </div>
        </div>
      </div>
      <?php } ?>
    </div>
    <!-- Botão Ver mais Produtos -->
    <div class="ver-mais-wrapper text-center mb-3">
      <a href="<?php echo htmlspecialchars(bb_url_publica_home('produtos')); ?>" 
        class="btn btn-lg" 
        style="background-color:#007bff; color:white; font-size:2rem; padding:14px 40px; border-radius:50px; text-decoration:none; display:inline-block; font-weight:bold; box-shadow:0px 4px 8px rgba(0,0,0,0.2);"
        onmouseover="this.style.backgroundColor='#0056b3';" 
        onmouseout="this.style.backgroundColor='#007bff';">
        Ver mais Produtos
      </a>
    </div>
  </div>
</section>
<?php } ?>
<!-- fim produtos -->

<!-- client section -->
<?php 
$query = $pdo->query("SELECT * FROM comentarios where ativo = 'Sim' ORDER BY id desc LIMIT 12");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);
if($total_reg > 0){ 
?>
<section class="client_section layout_padding-bottom">
  <div class="container">
    <div class="heading_container"><h2>Depoimento dos nossos Clientes</h2></div>
    <div class="client_container">
      <div class="carousel-wrap">
        <div class="owl-carousel client_owl-carousel">
          <?php 
          for($i=0; $i < $total_reg; $i++){
            foreach ($res[$i] as $key => $value){}
            $id    = $res[$i]['id'];
            $nome  = $res[$i]['nome'];   
            $texto = $res[$i]['texto'];
            $foto  = $res[$i]['foto'];   
          ?>
          <div class="item">
            <div class="box">
              <div class="img-box">
                <?php
                  $path = "sistema/painel/img/comentarios/$foto";
                  $webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $path);
                  $src  = file_exists($webp) ? $webp : $path;
                ?>
                <img
                  src="<?php echo htmlspecialchars(bb_asset_publica_home($src)); ?>"
                  alt="<?php echo htmlspecialchars($nome); ?>"
                  width="120" height="120"
                  loading="lazy" fetchpriority="low" decoding="async"
                  class="img-1"
                  style="aspect-ratio:1/1;object-fit:cover"
                />
              </div>
              <div class="detail-box">
                <h5><?php echo $nome ?></h5>
                <p><?php echo $texto ?></p>
              </div>
            </div>
          </div>
          <?php } ?>
        </div>
      </div>
    </div>
    <!-- Botão Inserir Depoimento -->
    <div class="btn-box2 text-center mb-3">
      <a href="#" class="btn btn-lg" data-toggle="modal" data-target="#modalComentario" data-bs-toggle="modal" data-bs-target="#modalComentario"
        style="background-color:#007bff; color:white; font-size:2rem; padding:14px 40px; border-radius:50px; text-decoration:none; display:inline-block; font-weight:bold; box-shadow:0px 4px 8px rgba(0,0,0,0.2);"
        onmouseover="this.style.backgroundColor='#0056b3';" 
        onmouseout="this.style.backgroundColor='#007bff';">
        Inserir Depoimento
      </a>
    </div>
  </div>
</section>
<?php } ?>
<!-- end client section -->
<!-- Modal Simulador Cliente -->
<div class="modal fade" id="modalSimulacaoCliente" tabindex="-1" role="dialog" aria-labelledby="modalSimulacaoClienteLabel" aria-hidden="true">
  <div class="modal-dialog modal-xl" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h4 class="modal-title" id="modalSimulacaoClienteLabel" style="color:#ffffff;">Simulação de Prótese Capilar</h4>
        <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close" style="margin-top:-20px">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>

      <form id="form_simulacao_cliente" method="post" action="javascript:void(0);" enctype="multipart/form-data" onsubmit="return false;">
        <div class="modal-body">

          <div class="alert alert-info" style="font-size:14px;line-height:22px;">
            Envie uma foto frontal para gerar sua simulação. A prévia deve respeitar a cor natural do seu cabelo e,
            se houver fios grisalhos, manter esse padrão para um resultado mais fiel.
          </div>

          <input type="hidden" name="loja" value="<?php echo htmlspecialchars($bb_tenant_slug); ?>">

          <div class="row">
            <div class="col-md-6">
              <div class="form-group">
                <label>Seu Nome *</label>
                <input
                  type="text"
                  class="form-control"
                  id="nome_simulacao_cliente"
                  name="nome"
                  placeholder="Digite seu nome"
                  maxlength="120"
                  required>
              </div>
            </div>
            <div class="col-md-6">
              <div class="form-group">
                <label>WhatsApp *</label>
                <input
                  type="text"
                  class="form-control"
                  id="telefone_simulacao_cliente"
                  name="telefone"
                  placeholder="(00) 00000-0000"
                  maxlength="20"
                  required>
              </div>
            </div>
          </div>

          <div class="row">
            <div class="col-md-12">
              <div class="form-group">
                <label>Escolha sua foto</label>
                <input
                  type="file"
                  class="form-control"
                  id="foto_simulacao_cliente"
                  name="foto"
                  accept=".jpg,.jpeg,.png,.webp"
                  onchange="carregarPreviewSimulacaoCliente()"
                  required>
              </div>
            </div>
          </div>

          <div class="row" style="margin-top:10px;">
            <div class="col-md-6">
              <div class="painel-comparacao-simulacao">
                <span class="selo-comparacao">Antes</span>
                <div class="titulo-painel">Imagem Original</div>
                <div class="box-imagem-simulacao">
                  <img src="<?php echo htmlspecialchars(bb_asset_publica_home('sistema/painel/images/simulacoes/sem-foto.jpg')); ?>" id="preview_original_simulacao_cliente" alt="">
                </div>
                <div class="legenda-simulacao">
                  Foto enviada pelo cliente para comparação visual.
                </div>
              </div>
            </div>

            <div class="col-md-6">
              <div class="painel-comparacao-simulacao">
                <span class="selo-comparacao">Depois</span>
                <div class="titulo-painel">Imagem Simulada</div>
                <div class="box-imagem-simulacao">
                  <img src="<?php echo htmlspecialchars(bb_asset_publica_home('sistema/painel/images/simulacoes/sem-foto.jpg')); ?>" id="preview_resultado_simulacao_cliente" alt="">
                </div>
                <div class="legenda-simulacao">
                  Prévia gerada pela IA respeitando a cor original e o padrão grisalho do cliente.
                </div>
              </div>
            </div>
          </div>

          <div class="row" style="margin-top:18px;">
            <div class="col-md-12">
              <button type="button" id="btn_simular_cliente" class="btn btn-primary btn-block" style="font-size:18px;font-weight:700;padding:12px;" onclick="enviarSimulacaoCliente()">
                <i id="icone_btn_simular_cliente" class="fa fa-magic"></i> Simular
              </button>
            </div>
          </div>

          <div class="row" style="margin-top:12px;">
            <div class="col-md-12">
              <small><div id="mensagem_simulacao_cliente" align="center"></div></small>
            </div>
          </div>

        </div>
      </form>
    </div>
  </div>
</div>
<!-- FAQ -->
<?php 
$res = $pdo->query("SELECT DISTINCT pergunta, resposta FROM faq ORDER BY id DESC");
$faq = $res->fetchAll(PDO::FETCH_ASSOC);
$total = count($faq);
$metade = ceil($total / 2);
$coluna1 = array_slice($faq, 0, $metade);
$coluna2 = array_slice($faq, $metade);
?>
<section class="faq-section text-center">
  <div class="container">
    <h2 class="section-heading mb-5">Encontre respostas para as dúvidas mais comuns dos nossos clientes</h2>
    <div class="row justify-content-center">
      <div class="col-md-10">
        <div class="row">
          <div class="col-md-6 mb-4">
            <?php foreach($coluna1 as $index => $item): ?>
            <div class="panel panel-default mb-3">
              <div class="panel-heading bg-light p-2 rounded shadow-sm">
                <h5 class="panel-title mb-0">
                  <a data-toggle="collapse" href="#faq1_<?= $index ?>" class="faq-toggle d-block text-dark text-decoration-none">
                    <?= htmlspecialchars($item['pergunta']) ?>
                    <span class="toggle-icon float-right">+</span>
                  </a>
                </h5>
              </div>
              <div id="faq1_<?= $index ?>" class="panel-collapse collapse">
                <div class="panel-body p-2 text-left bg-white border rounded-bottom"><?= nl2br(htmlspecialchars($item['resposta'])) ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="col-md-6 mb-4">
            <?php foreach($coluna2 as $index => $item): ?>
            <div class="panel panel-default mb-3">
              <div class="panel-heading bg-light p-2 rounded shadow-sm">
                <h5 class="panel-title mb-0">
                  <a data-toggle="collapse" href="#faq2_<?= $index ?>" class="faq-toggle d-block text-dark text-decoration-none">
                    <?= htmlspecialchars($item['pergunta']) ?>
                    <span class="toggle-icon float-right">+</span>
                  </a>
                </h5>
              </div>
              <div id="faq2_<?= $index ?>" class="panel-collapse collapse">
                <div class="panel-body p-2 text-left bg-white border rounded-bottom"><?= nl2br(htmlspecialchars($item['resposta'])) ?></div>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>
<!-- end faq section -->

<!-- contact section -->
<section class="contact_section layout_padding-bottom">
  <div class="container">
    <div class="heading_container heading_center">
      <h2>Transforme seu visual: Pronto para um visual moderno?</h2>
    </div>
    <div class="row">
      <div class="col-md-6">
        <div class="form_container">
          <form id="form-email">
            <div><input type="text" name="nome" placeholder="Seu Nome" required/></div>
            <div><input type="text" name="telefone" id="telefone" placeholder="Seu Whatsapp" required/></div>
            <div><input type="email" name="email" placeholder="Seu Email" required/></div>
            <div><input type="text" name="mensagem" class="message-text" placeholder="Mensagem" required/></div>
            <div class="btn_box"><button>Enviar</button></div>
          </form>
          <br><div id="mensagem"></div>
        </div>
      </div>
      <div class="col-md-6">
        <div class="map_container" id="map_container">
          <template id="map_template"><?php echo $mapa ?></template>
        </div>
      </div>
    </div>
  </div>
</section>

<!-- footer -->
<!-- Rodapé carregado ao final do arquivo para manter modais e scripts dentro da estrutura HTML correta. -->

<!-- NÃO carregar Bootstrap/jQuery do CDN aqui (já vem no rodapé local) -->

<!-- Modal Depoimentos -->
<div class="modal fade" id="modalComentario" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Inserir Depoimento</h5>
        <button type="button" class="close" data-dismiss="modal" data-bs-dismiss="modal" aria-label="Close" style="margin-top:-20px">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form id="form">
        <div class="modal-body">
          <div class="row">
            <div class="col-md-12">
              <div class="form-group">
                <label>Nome</label>
                <input type="text" class="form-control" id="nome_cliente" name="nome" placeholder="Nome" required>
              </div>
            </div>
            <div class="col-md-12">
              <div class="form-group">
                <label>Texto <small>(Até 500 Caracteres)</small></label>
                <textarea maxlength="500" class="form-control" id="texto_cliente" name="texto" placeholder="Texto Comentário" required></textarea>
              </div>
            </div>
          </div>
          <div class="row">
            <div class="col-md-8">
              <div class="form-group">
                <label>Foto</label>
                <input class="form-control" type="file" name="foto" onChange="carregarImg();" id="foto">
              </div>
            </div>
            <div class="col-md-4">
              <div id="divImg">
                <img src="<?php echo htmlspecialchars(bb_asset_publica_home('sistema/painel/img/comentarios/sem-foto.jpg')); ?>" width="80" id="target" alt="">
              </div>
            </div>
          </div>
          <input type="hidden" name="id" id="id"><input type="hidden" name="cliente" value="1">
          <br><small><div id="mensagem-comentario" align="center"></div></small>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Inserir</button>
        </div>
      </form>
    </div>
  </div>
</div>

<style>
  /* Reserva espaço pro slider (evita CLS) */
  .slider_section .carousel-inner,
  .slider_section .carousel-item { min-height: 420px; }

  /* Avatares fixos */
  .client_section .img-1{ width:120px;height:120px;object-fit:cover;display:block; }

  /* Galeria/serviços com altura consistente */
  .service .service-img{min-height:250px}
  .service .service-img img{width:100%;height:250px;object-fit:cover;display:block}

  /* Evita pulo de layout até renderizar */
  .product_section, .service, .client_section, .faq-section, .contact_section{
    content-visibility:auto; contain-intrinsic-size:1px 1000px;
  }

  .about_section{
    content-visibility:visible;
    contain-intrinsic-size:auto;
  }
</style>

<!-- Cookie banner (único) -->
<style>
.cookie-banner{position:fixed;bottom:0;left:0;width:100vw;background:rgba(53,51,62,.92);color:#fff;z-index:99999;font-family:Arial,sans-serif;padding:18px 0 15px;box-shadow:0 -2px 20px rgba(0,0,0,.2);text-align:center;transition:.3s}
.cookie-banner.hide{display:none!important}
.cookie-banner .cookie-text{font-size:26px;font-weight:500;margin-bottom:3px;display:block}
.cookie-banner .cookie-info{font-size:13px;margin-bottom:15px;opacity:.88;display:block}
.cookie-banner .cookie-buttons{display:flex;justify-content:center;gap:10px;flex-wrap:wrap}
.cookie-banner button,.cookie-banner a.termos-btn{background:#282736;color:#fff;border:1px solid #fff;border-radius:4px;padding:10px 32px;font-size:18px;font-weight:bold;margin:0 0 2px 0;transition:.18s;outline:0;cursor:pointer;box-shadow:0 2px 6px rgba(0,0,0,.07);text-decoration:none;display:inline-block}
.cookie-banner button:hover,.cookie-banner a.termos-btn:hover{background:#0d6efd;color:#fff;border-color:#0d6efd}
@media (max-width:600px){.cookie-banner .cookie-text{font-size:16px}.cookie-banner button,.cookie-banner a.termos-btn{font-size:15px;padding:8px 10px}}
</style>
<style>
#modalSimulacaoCliente .modal-content{
  border-radius:18px;
  border:0;
  overflow:hidden;
  box-shadow:0 18px 50px rgba(0,0,0,.25);
}
#modalSimulacaoCliente .modal-header{
  background:linear-gradient(90deg,#111 0%,#1f1f1f 100%);
  color:#fff;
  border-bottom:0;
  padding:18px 22px;
}
#modalSimulacaoCliente .modal-header .close{
  color:#fff;
  opacity:1;
  text-shadow:none;
}
#modalSimulacaoCliente .modal-body{
  background:#f7f8fa;
  padding:22px;
}
#modalSimulacaoCliente .alert{
  border-radius:12px;
  border:1px solid #d7e8ff;
  background:#eef6ff;
  color:#2f4f6f;
  margin-bottom:18px;
}
#modalSimulacaoCliente .form-group label{
  font-weight:700;
  color:#333;
  margin-bottom:8px;
}
#modalSimulacaoCliente .form-control{
  border-radius:10px;
  min-height:46px;
  border:1px solid #d8dee6;
  box-shadow:none;
}
#modalSimulacaoCliente .form-control:focus{
  border-color:#0d6efd;
  box-shadow:0 0 0 0.15rem rgba(13,110,253,.15);
}
.painel-comparacao-simulacao{
  background:#fff;
  border:1px solid #e4e7eb;
  border-radius:16px;
  padding:16px;
  box-shadow:0 6px 20px rgba(0,0,0,.05);
  height:100%;
}
.painel-comparacao-simulacao .titulo-painel{
  font-size:15px;
  font-weight:700;
  color:#2d3436;
  margin-bottom:12px;
}
.box-imagem-simulacao{
  background:linear-gradient(180deg,#ffffff 0%,#f8f9fb 100%);
  border:1px solid #dfe4ea;
  border-radius:14px;
  min-height:340px;
  display:flex;
  align-items:center;
  justify-content:center;
  padding:12px;
  position:relative;
  overflow:hidden;
}
.box-imagem-simulacao img{
  position:relative;
  z-index:2;
  max-width:100%;
  max-height:310px;
  border-radius:10px;
  box-shadow:0 8px 24px rgba(0,0,0,.12);
  background:#fff;
}
.legenda-simulacao{
  margin-top:12px;
  font-size:13px;
  color:#6c757d;
  line-height:20px;
}
#btn_simular_cliente{
  border-radius:50px;
  min-height:52px;
  font-size:18px;
  font-weight:700;
  box-shadow:0 10px 24px rgba(13,110,253,.18);
}
#mensagem_simulacao_cliente{
  font-size:14px;
  font-weight:600;
  min-height:22px;
}
.selo-comparacao{
  display:inline-block;
  padding:6px 12px;
  border-radius:30px;
  font-size:12px;
  font-weight:700;
  background:#111;
  color:#fff;
  margin-bottom:10px;
}
@media (max-width:991px){
  #modalSimulacaoCliente .modal-body{padding:16px}
  .box-imagem-simulacao{min-height:260px;margin-bottom:12px}
  .box-imagem-simulacao img{max-height:230px}
  #btn_simular_cliente{font-size:16px}
}
</style>
<div class="cookie-banner hide" id="cookieBanner">
  <span class="cookie-text">Este website utiliza cookies</span>
  <span class="cookie-info">Nós utilizamos cookies para garantir que você tenha a melhor experiência em nosso site.</span>
  <div class="cookie-buttons">
    <button id="aceitarCookies">Permitir Cookies</button>
    <button id="recusarCookies">Recusar Cookies</button>
    <a href="#privacidade" data-toggle="modal" data-bs-toggle="modal" class="termos-btn">Termos de Uso</a>
  </div>
</div>

<!-- Scripts de inicialização, todos DEFER -->
<script defer>
document.addEventListener('DOMContentLoaded', function(){
  var $ = window.jQuery || window.$;

  function bbAbrirModalCompat(seletor){
    var modalEl = document.querySelector(seletor);
    if(!modalEl){
      return;
    }

    if(window.bootstrap && typeof window.bootstrap.Modal === 'function'){
      var instanciaModal = window.bootstrap.Modal.getOrCreateInstance ? window.bootstrap.Modal.getOrCreateInstance(modalEl) : new window.bootstrap.Modal(modalEl);
      instanciaModal.show();
      return;
    }

    if(window.jQuery && typeof window.jQuery.fn.modal === 'function'){
      window.jQuery(modalEl).modal('show');
      return;
    }

    modalEl.style.display = 'block';
    modalEl.removeAttribute('aria-hidden');
    modalEl.setAttribute('aria-modal', 'true');
    modalEl.classList.add('show');
    document.body.classList.add('modal-open');

    if(!document.querySelector('.bb-modal-backdrop-compat')){
      var backdrop = document.createElement('div');
      backdrop.className = 'modal-backdrop fade show bb-modal-backdrop-compat';
      backdrop.addEventListener('click', function(){
        bbFecharModalCompat(seletor);
      });
      document.body.appendChild(backdrop);
    }
  }

  function bbFecharModalCompat(seletor){
    var modalEl = document.querySelector(seletor);
    if(!modalEl){
      return;
    }

    if(window.bootstrap && typeof window.bootstrap.Modal === 'function'){
      var instanciaModal = window.bootstrap.Modal.getOrCreateInstance ? window.bootstrap.Modal.getOrCreateInstance(modalEl) : new window.bootstrap.Modal(modalEl);
      instanciaModal.hide();
      return;
    }

    if(window.jQuery && typeof window.jQuery.fn.modal === 'function'){
      window.jQuery(modalEl).modal('hide');
      return;
    }

    modalEl.classList.remove('show');
    modalEl.style.display = 'none';
    modalEl.setAttribute('aria-hidden', 'true');
    modalEl.removeAttribute('aria-modal');
    document.body.classList.remove('modal-open');
    document.querySelectorAll('.bb-modal-backdrop-compat').forEach(function(backdrop){
      backdrop.remove();
    });
  }

  window.bbAbrirModalSimulacaoCliente = function(){
    bbAbrirModalCompat('#modalSimulacaoCliente');
  };

  var btnAbrirSimulador = document.getElementById('btn_abrir_modal_simulacao_cliente');
  if(btnAbrirSimulador){
    btnAbrirSimulador.addEventListener('click', function(e){
      e.preventDefault();
      e.stopPropagation();
      bbAbrirModalCompat('#modalSimulacaoCliente');
    });
  }

  document.querySelectorAll('[data-dismiss="modal"], [data-bs-dismiss="modal"]').forEach(function(botaoFecharModal){
    botaoFecharModal.addEventListener('click', function(e){
      var modalPai = botaoFecharModal.closest('.modal');
      if(!modalPai || !modalPai.id){
        return;
      }

      e.preventDefault();
      bbFecharModalCompat('#' + modalPai.id);
    });
  });

  // Cookie banner
  function bbShowCookieBanner(){
    var banner = document.getElementById('cookieBanner');
    if (banner && !localStorage.cookieChoice) { banner.classList.remove('hide'); }
  }
  if (!localStorage.cookieChoice) {
    var mostrouCookie = false;
    function abrirCookieUmaVez(){
      if (mostrouCookie) { return; }
      mostrouCookie = true;
      bbShowCookieBanner();
    }
    ['click','scroll','keydown','touchstart'].forEach(function(ev){
      window.addEventListener(ev, abrirCookieUmaVez, { once:true, passive:true });
    });
    window.addEventListener('load', function(){ setTimeout(abrirCookieUmaVez, 9000); });
  }
  var aceita = document.getElementById('aceitarCookies');
  var recusa = document.getElementById('recusarCookies');
  if(aceita) aceita.onclick = function(){ localStorage.cookieChoice="accept"; document.getElementById('cookieBanner').classList.add('hide'); };
  if(recusa) recusa.onclick = function(){ localStorage.cookieChoice="deny"; document.getElementById('cookieBanner').classList.add('hide'); };

  // Form email
  if ($) {
    $('#form-email').on('submit', function(e){
      e.preventDefault();
      var formData = new FormData(this);
      $.ajax({
        url: <?php echo json_encode(bb_url_publica_home('ajax/enviar-email.php'), JSON_UNESCAPED_UNICODE); ?>,
        type:'POST',
        data:formData,
        success:function(mensagem){
          $('#mensagem').text('').removeClass();
          if(mensagem.trim()=="Enviado com Sucesso"){
            $('#mensagem').addClass('text-success').text(mensagem);
          }else{
            $('#mensagem').addClass('text-danger').text(mensagem);
          }
        },
        cache:false, contentType:false, processData:false
      });
    });

    // Modal comentário
    $('#form').on('submit', function(e){
      e.preventDefault();
      var formData = new FormData(this);
      $.ajax({
        url: <?php echo json_encode(bb_url_publica_home('sistema/painel/paginas/comentarios/salvar.php'), JSON_UNESCAPED_UNICODE); ?>,
        type:'POST',
        data:formData,
        success:function(mensagem){
          $('#mensagem-comentario').text('').removeClass();
          if(mensagem.trim()=="Salvo com Sucesso"){
            $('#mensagem-comentario').addClass('text-success').text('Comentário Enviado para Aprovação!');
            $('#nome_cliente').val(''); $('#texto_cliente').val('');
          }else{
            $('#mensagem-comentario').addClass('text-danger').text(mensagem);
          }
        },
        cache:false, contentType:false, processData:false
      });
    });
  }

  // Pré-visualização de imagem
  window.carregarImg = function(){
    var target=document.getElementById('target');
    var file=document.querySelector("#foto").files[0];
    var reader=new FileReader();
    reader.onloadend=function(){ target.src=reader.result; };
    if(file){ reader.readAsDataURL(file); } else { target.src=""; }
  };

  // FAQ +/- ícone
  if ($) {
    $('.panel-collapse').on('show.bs.collapse', function(){ $(this).prev('.panel-heading').find('.toggle-icon').text('–'); });
    $('.panel-collapse').on('hide.bs.collapse', function(){ $(this).prev('.panel-heading').find('.toggle-icon').text('+'); });
  }
});
</script>

<!-- Lazy do mapa -->
<script>
(function(){
  var cont = document.getElementById('map_container');
  if(!cont) return;
  function mount(){
    if(cont.dataset.mounted) return;
    var tpl = document.getElementById('map_template');
    if(!tpl) return;
    cont.innerHTML = tpl.innerHTML.replace('<iframe','<iframe loading="lazy" referrerpolicy="strict-origin-when-cross-origin" ');
    cont.dataset.mounted='1';
  }
  if('IntersectionObserver' in window){
    new IntersectionObserver(function(es,obs){
      if(es[0].isIntersecting){ mount(); obs.disconnect(); }
    },{rootMargin:'600px 0px'}).observe(cont);
  } else { mount(); }
})();
</script>

<!-- Inicialização preguiçosa dos carrosséis -->
<script defer>
(function(){
  function onIdle(cb){
    if('requestIdleCallback' in window){ requestIdleCallback(cb,{timeout:1200}); }
    else { setTimeout(cb, 500); }
  }
  onIdle(function(){
    if(!window.jQuery) return;
    var $ = window.jQuery;

    // Bootstrap carousel (slider principal)
    try { $('#customCarousel1').carousel({ interval: 5000, ride: 'carousel', pause: false }); } catch(e){}

    function initOwl(){
      if (!$.fn.owlCarousel) return;
      var $prod = $('.product_owl-carousel'), $cli = $('.client_owl-carousel');

      if($prod.length && !$prod.data('owl-loaded')){
        $prod.owlCarousel({
          loop:true, margin:10, autoplay:true, smartSpeed:600, nav:false, dots:true,
          responsive:{0:{items:1},576:{items:2},992:{items:3}}
        }).data('owl-loaded',1);
      }
      if($cli.length && !$cli.data('owl-loaded')){
        $cli.owlCarousel({
          loop:true, autoplay:true, items:1, smartSpeed:600, nav:false, dots:true
        }).data('owl-loaded',1);
      }
    }

    var firstOwl = document.querySelector('.owl-carousel');
    if(firstOwl && 'IntersectionObserver' in window){
      var io = new IntersectionObserver(function(entries, obs){
        entries.forEach(function(entry){
          if(entry.isIntersecting){ initOwl(); obs.disconnect(); }
        });
      }, {rootMargin:'600px 0px'});
      io.observe(firstOwl);
    } else {
      initOwl();
    }
  });
})();
</script>

<script>
document.addEventListener('DOMContentLoaded', function(){
  var parametros = new URLSearchParams(window.location.search);
  var abrirSimulador = parametros.get('abrir_simulador');

  if(abrirSimulador !== '1'){
    return;
  }

  if(!document.getElementById('modalSimulacaoCliente')){
    return;
  }

  setTimeout(function(){
    if(typeof window.bbAbrirModalSimulacaoCliente === 'function'){
      window.bbAbrirModalSimulacaoCliente();
    }else if(window.jQuery && typeof window.jQuery.fn.modal === 'function'){
      window.jQuery('#modalSimulacaoCliente').modal('show');
    }
  }, 300);
});
</script>

<script>
(function(){
  var bbTenantSlug = <?php echo json_encode($bb_tenant_slug, JSON_UNESCAPED_UNICODE); ?>;
  var bbBasePublica = <?php echo json_encode($bb_base_publica, JSON_UNESCAPED_UNICODE); ?>;
  var bbSemFotoSimulacaoCliente = <?php echo json_encode(bb_asset_publica_home('sistema/painel/images/simulacoes/sem-foto.jpg'), JSON_UNESCAPED_UNICODE); ?>;
  var urlSimulacaoClienteBase = <?php echo json_encode(bb_url_publica_home('ajax/gerar_ia_cliente.php'), JSON_UNESCAPED_UNICODE); ?>;
  var urlCadastroClienteSimulacaoBase = <?php echo json_encode(bb_url_publica_home('cadastrar.php'), JSON_UNESCAPED_UNICODE); ?>;
  var urlSimulacaoCliente = urlSimulacaoClienteBase + (bbTenantSlug ? '?loja=' + encodeURIComponent(bbTenantSlug) : '');
  var urlCadastroClienteSimulacao = urlCadastroClienteSimulacaoBase + (bbTenantSlug ? '?loja=' + encodeURIComponent(bbTenantSlug) : '');
  var bbSimulacaoClienteProcessando = false;
  var bbAssinaturaFotoAtual = '';
  var bbUltimaAssinaturaFotoGerada = '';
  var bbUltimoResultadoSimulacao = null;

  function bbMontarAssinaturaArquivoSimulacao(arquivo){
    if(!arquivo){
      return '';
    }

    return [
      arquivo.name || '',
      arquivo.size || 0,
      arquivo.type || '',
      arquivo.lastModified || 0
    ].join('|');
  }

  function bbLimparControleRepeticaoSimulacao(){
    bbSimulacaoClienteProcessando = false;
    bbAssinaturaFotoAtual = '';
    bbUltimaAssinaturaFotoGerada = '';
    bbUltimoResultadoSimulacao = null;
  }

  function bbAplicarResultadoSimulacaoCliente(imgOriginal, imgResultado, resultado){
    if(!resultado){
      return;
    }

    if(imgOriginal && resultado.url_original){
      imgOriginal.src = resultado.url_original;
    }

    if(imgResultado && resultado.url_simulada){
      imgResultado.src = resultado.url_simulada;
    }
  }

  function bbCadastrarClienteSimulacaoSilencioso(form){
    if(!form){
      return Promise.resolve('');
    }

    var formDataCadastro = new FormData();
    var campoNome = form.querySelector('[name="nome"]');
    var campoTelefone = form.querySelector('[name="telefone"]');
    var campoLoja = form.querySelector('[name="loja"]');

    formDataCadastro.set('nome', campoNome ? campoNome.value : '');
    formDataCadastro.set('telefone', campoTelefone ? campoTelefone.value : '');

    if (bbTenantSlug) {
      formDataCadastro.set('loja', bbTenantSlug);
    } else if (campoLoja && campoLoja.value) {
      formDataCadastro.set('loja', campoLoja.value);
    }

    return fetch(urlCadastroClienteSimulacao, {
      method: 'POST',
      body: formDataCadastro,
      headers: {
        'X-Requested-With': 'XMLHttpRequest'
      }
    })
    .then(function(response){
      return response.text();
    })
    .then(function(texto){
      return (texto || '').trim();
    })
    .catch(function(){
      return '';
    });
  }

  window.carregarPreviewSimulacaoCliente = function(){
    var campo = document.getElementById('foto_simulacao_cliente');
    var imgOriginal = document.getElementById('preview_original_simulacao_cliente');
    var imgResultado = document.getElementById('preview_resultado_simulacao_cliente');
    var msg = document.getElementById('mensagem_simulacao_cliente');
    var btn = document.getElementById('btn_simular_cliente');
    var icone = document.getElementById('icone_btn_simular_cliente');

    if(!campo || !imgOriginal || !imgResultado){
      return;
    }

    if(!campo.files || !campo.files[0]){
      imgOriginal.src = bbSemFotoSimulacaoCliente;
      imgResultado.src = bbSemFotoSimulacaoCliente;
      bbLimparControleRepeticaoSimulacao();

      if(msg){
        msg.innerText = '';
        msg.className = '';
      }

      if(btn){
        btn.disabled = false;
      }

      if(icone){
        icone.className = 'fa fa-magic';
      }

      return;
    }

    var arquivo = campo.files[0];
    var assinaturaNova = bbMontarAssinaturaArquivoSimulacao(arquivo);

    if(bbAssinaturaFotoAtual !== assinaturaNova){
      bbAssinaturaFotoAtual = assinaturaNova;
      bbUltimaAssinaturaFotoGerada = '';
      bbUltimoResultadoSimulacao = null;

      if(msg){
        msg.innerText = '';
        msg.className = '';
      }

      if(btn){
        btn.disabled = false;
      }

      if(icone){
        icone.className = 'fa fa-magic';
      }
    }

    var reader = new FileReader();

    reader.onload = function(e){
      imgOriginal.src = e.target.result;
      imgResultado.src = bbSemFotoSimulacaoCliente;
    };

    reader.onerror = function(){
      imgOriginal.src = bbSemFotoSimulacaoCliente;
      imgResultado.src = bbSemFotoSimulacaoCliente;
      bbLimparControleRepeticaoSimulacao();

      if(btn){
        btn.disabled = false;
      }
    };

    reader.readAsDataURL(arquivo);
  };

  window.enviarSimulacaoCliente = function(){
    var form = document.getElementById('form_simulacao_cliente');
    var campo = document.getElementById('foto_simulacao_cliente');
    var btn = document.getElementById('btn_simular_cliente');
    var icone = document.getElementById('icone_btn_simular_cliente');
    var msg = document.getElementById('mensagem_simulacao_cliente');
    var imgOriginal = document.getElementById('preview_original_simulacao_cliente');
    var imgResultado = document.getElementById('preview_resultado_simulacao_cliente');

    if(!form || !campo || !btn || !icone || !msg || !imgOriginal || !imgResultado){
      return;
    }

    if(bbSimulacaoClienteProcessando){
      msg.className = 'text-info';
      msg.innerText = 'A simulação já está em processamento. Aguarde...';
      return;
    }

    var campoNome = document.getElementById('nome_simulacao_cliente');
    var campoTelefone = document.getElementById('telefone_simulacao_cliente');

    if(!campoNome || !campoTelefone){
      return;
    }

    if(campoNome.value.trim() === ''){
      msg.className = 'text-danger';
      msg.innerText = 'Digite seu nome para continuar';
      campoNome.focus();
      return;
    }

    if(campoTelefone.value.trim() === ''){
      msg.className = 'text-danger';
      msg.innerText = 'Digite seu WhatsApp para continuar';
      campoTelefone.focus();
      return;
    }

    if(!campo.files || !campo.files[0]){
      msg.className = 'text-danger';
      msg.innerText = 'Selecione uma imagem para simular';
      return;
    }

    var assinaturaAtual = bbMontarAssinaturaArquivoSimulacao(campo.files[0]);

    if(bbUltimaAssinaturaFotoGerada === assinaturaAtual && bbUltimoResultadoSimulacao){
      bbAplicarResultadoSimulacaoCliente(imgOriginal, imgResultado, bbUltimoResultadoSimulacao);
      msg.className = 'text-success';
      msg.innerText = 'Simulação já gerada para esta imagem';
      btn.disabled = true;
      icone.className = 'fa fa-magic';
      return;
    }

    var formData = new FormData(form);
    if(bbTenantSlug){
      formData.set('loja', bbTenantSlug);
    }

    bbSimulacaoClienteProcessando = true;
    btn.disabled = true;
    icone.className = 'fa fa-spinner fa-spin';
    msg.className = 'text-info';
    msg.innerText = 'Gerando simulação, aguarde...';

    var manterBotaoBloqueado = false;

    fetch(urlSimulacaoCliente, {
      method: 'POST',
      body: formData
    })
    .then(async function(response){
      var texto = await response.text();
      var dados = null;

      try {
        dados = JSON.parse(texto);
      } catch(e) {
        throw new Error(texto || 'Resposta inválida do servidor');
      }

      if(!response.ok || !dados || dados.status !== 'Sucesso'){
        throw new Error((dados && dados.mensagem) ? dados.mensagem : 'Erro ao processar a simulação');
      }

      bbAplicarResultadoSimulacaoCliente(imgOriginal, imgResultado, dados);

      bbUltimaAssinaturaFotoGerada = assinaturaAtual;
      bbUltimoResultadoSimulacao = {
        url_original: dados.url_original || imgOriginal.src,
        url_simulada: dados.url_simulada || imgResultado.src
      };
      manterBotaoBloqueado = true;

      var finalizarSucesso = function(){
        msg.className = 'text-success';
        msg.innerText = dados.ja_existia === 'Sim' ? 'Simulação já gerada para esta imagem' : 'Simulação gerada com sucesso';
      };

      if(dados.ja_existia === 'Sim'){
        finalizarSucesso();
        return '';
      }

      return bbCadastrarClienteSimulacaoSilencioso(form).then(function(){
        finalizarSucesso();
        return '';
      });
    })
    .catch(function(erro){
      manterBotaoBloqueado = false;
      msg.className = 'text-danger';
      msg.innerText = erro.message ? erro.message : 'Erro ao processar a simulação';
    })
    .finally(function(){
      bbSimulacaoClienteProcessando = false;
      btn.disabled = manterBotaoBloqueado;
      icone.className = 'fa fa-magic';
    });
  };

  var campoTelefoneSimulacao = document.getElementById('telefone_simulacao_cliente');
  if (campoTelefoneSimulacao) {
    campoTelefoneSimulacao.addEventListener('input', function(){
      var valor = this.value.replace(/\D/g, '').slice(0, 11);
      if (valor.length > 10) {
        this.value = valor.replace(/^(\d{2})(\d{5})(\d{0,4}).*/, '($1) $2-$3').replace(/-$/, '');
      } else {
        this.value = valor.replace(/^(\d{2})(\d{0,4})(\d{0,4}).*/, '($1) $2-$3').replace(/-$/, '');
      }
    });
  }

  if (window.jQuery) {
    $('#modalSimulacaoCliente').on('hidden.bs.modal', function(){
      var form = document.getElementById('form_simulacao_cliente');
      var imgOriginal = document.getElementById('preview_original_simulacao_cliente');
      var imgResultado = document.getElementById('preview_resultado_simulacao_cliente');
      var msg = document.getElementById('mensagem_simulacao_cliente');
      var btn = document.getElementById('btn_simular_cliente');
      var icone = document.getElementById('icone_btn_simular_cliente');
      var campoNome = document.getElementById('nome_simulacao_cliente');
      var campoTelefone = document.getElementById('telefone_simulacao_cliente');

      if(form){
        form.reset();
      }

      if(imgOriginal){
        imgOriginal.src = bbSemFotoSimulacaoCliente;
      }

      if(imgResultado){
        imgResultado.src = bbSemFotoSimulacaoCliente;
      }

      if(msg){
        msg.innerText = '';
        msg.className = '';
      }

      if(campoNome){
        campoNome.value = '';
      }

      if(campoTelefone){
        campoTelefone.value = '';
      }

      bbLimparControleRepeticaoSimulacao();

      if(btn){
        btn.disabled = false;
      }

      if(icone){
        icone.className = 'fa fa-magic';
      }
    });
  }
})();
</script>

<?php
ob_start();
require_once("rodape.php");
$bb_rodape_html = ob_get_clean();
echo bb_normalizar_assets_home($bb_rodape_html);
?>
