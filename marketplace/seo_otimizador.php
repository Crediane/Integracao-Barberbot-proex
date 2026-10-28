<?php
// Verifica se já declarou as funções
if (!function_exists('seo_meta_tags')) {
    function seo_meta_tags($title, $description, $image, $url) {
        // Proteção para evitar valores em branco
        $title = htmlspecialchars($title ?? 'Agendamento Online - Seu Negócio');
        $description = htmlspecialchars($description ?? 'Agende seu serviço online com facilidade.');
        $image = htmlspecialchars($image ?? 'https:///jc.tec.br/img/logo.png');
        $url = htmlspecialchars($url ?? 'https:///jc.tec.br/');

        echo <<<HTML
<!-- SEO Otimizador | OpenGraph | WhatsApp | Facebook | JSON-LD -->
<title>{$title}</title>
<meta name="description" content="{$description}">
<meta property="og:title" content="{$title}">
<meta property="og:description" content="{$description}">
<meta property="og:image" content="{$image}">
<meta property="og:url" content="{$url}">
<meta property="og:type" content="website">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="{$title}">
<meta name="twitter:description" content="{$description}">
<meta name="twitter:image" content="{$image}">
<!-- WhatsApp -->
<meta property="og:site_name" content="{$title}">
<!-- JSON-LD -->
<script type="application/ld+json">
{
    "@context": "https://schema.org",
    "@type": "LocalBusiness",
    "name": "{$title}",
    "image": "{$image}",
    "url": "{$url}",
    "description": "{$description}"
}
</script>
HTML;
    }
}
?>
