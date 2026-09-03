<?php
// ============ OTIMIZADOR PHP + SEO SOCIAL + JSON-LD ============
// Coloque esta linha ANTES de qualquer HTML/output: include('otimizador.php');

// -- CONFIG BÁSICA (ajuste para seu projeto) --
$seo_title = isset($seo_title) ? $seo_title : 'Jacy Cabeleireiro | Agendamento Online';
$seo_desc = isset($seo_desc) ? $seo_desc : 'Faça seu agendamento online com praticidade no Jacy Cabeleireiro. Serviços de beleza, barbearia, cortes, tintura e mais!';
$seo_url = (isset($seo_url) && $seo_url) ? $seo_url : (
  (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS']=='on' ? 'https://' : 'http://')
  . $_SERVER['HTTP_HOST']
  . $_SERVER['REQUEST_URI']
);
$seo_img = isset($seo_img) ? $seo_img : 'https://jacycabeleireiro.com/sistema/img/logo_rel.jpg'; // Ajuste para sua logo/banner
$seo_whatsapp = isset($seo_whatsapp) ? $seo_whatsapp : 'https://wa.me/5545999882100';
$seo_org = isset($seo_org) ? $seo_org : 'Jacy Cabeleireiro';

// --- HEADERS DE OTIMIZAÇÃO ---
header("Cache-Control: public, max-age=604800, immutable");
if (isset($_SERVER['HTTP_ACCEPT_ENCODING']) && strpos($_SERVER['HTTP_ACCEPT_ENCODING'], 'gzip') !== false) {
    if (!ob_start("ob_gzhandler")) ob_start();
} else {
    ob_start();
}

// --- SEO, OpenGraph, Facebook/WhatsApp, JSON-LD ---
echo <<<HTML
<title>$seo_title</title>
<meta name="description" content="$seo_desc">
<meta property="og:title" content="$seo_title">
<meta property="og:description" content="$seo_desc">
<meta property="og:url" content="$seo_url">
<meta property="og:image" content="$seo_img">
<meta property="og:type" content="website">
<meta property="og:site_name" content="$seo_org">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="$seo_title">
<meta name="twitter:description" content="$seo_desc">
<meta name="twitter:image" content="$seo_img">
<meta itemprop="name" content="$seo_title">
<meta itemprop="description" content="$seo_desc">
<meta itemprop="image" content="$seo_img">
<meta property="al:android:url" content="$seo_url">
<meta property="al:ios:url" content="$seo_url">
<meta property="al:web:url" content="$seo_url">
<meta property="al:whatsapp:url" content="$seo_whatsapp">
<link rel="canonical" href="$seo_url">
<meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
<meta http-equiv="X-UA-Compatible" content="IE=edge">
<!-- Fonte Google -->
<link href="https://fonts.googleapis.com/css2?family=Tw+Cen+MT:wght@400;700&display=swap" rel="stylesheet">
<!-- CSS/JS otimizados -->
<link rel="stylesheet" href="/min/style.min.css">
<script src="/min/app.min.js" defer></script>
<link rel="preload" href="/min/style.min.css" as="style">
<link rel="preload" href="/min/app.min.js" as="script">
<!-- Lazy loading images (garante para legacy browsers) -->
<script>
document.addEventListener("DOMContentLoaded",function(){
  document.querySelectorAll("img").forEach(function(img){
    if(!img.hasAttribute("loading")) img.setAttribute("loading","lazy");
  });
});
</script>
<!-- JSON-LD para Google/SEO Local -->
<script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "HairSalon",
  "name": "$seo_org",
  "image": "$seo_img",
  "url": "$seo_url",
  "telephone": "+5545999882100",
  "address": {
    "@type": "PostalAddress",
    "streetAddress": "Rua Rio Grande do Sul, 2151",
    "addressLocality": "Cascavel",
    "addressRegion": "PR",
    "postalCode": "85801-010",
    "addressCountry": "BR"
  },
  "description": "$seo_desc",
  "sameAs": [
    "https://www.instagram.com/jacy.cabeleireiro",
    "$seo_whatsapp"
  ]
}
</script>
HTML;
?>
