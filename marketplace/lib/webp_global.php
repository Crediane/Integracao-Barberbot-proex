<?php
// sistema/libs/webp_global.php
const WEBP_QUALITY = 80;
const WEBP_EXTS    = ['jpg','jpeg','png'];

function webp_start(): void {
  if (!function_exists('imagewebp')) return; // sem suporte WebP no GD
  if (!headers_sent()) ob_start('webp_filter_callback');
}
function webp_finish(): void { if (ob_get_level() > 0) ob_end_flush(); }

function webp_filter_callback(string $html): string {
  $ct = implode('', headers_list());
  if ($ct && stripos($ct, 'text/html') === false) return $html;
  return webp_transform_html($html);
}

function webp_transform_html(string $html): string {
  if (trim($html) === '') return $html;
  libxml_use_internal_errors(true);
  $dom = new DOMDocument('1.0','UTF-8');
  $ok = $dom->loadHTML(mb_convert_encoding($html,'HTML-ENTITIES','UTF-8'),
    LIBXML_HTML_NODEFDTD|LIBXML_HTML_NOIMPLIED);
  if (!$ok) { libxml_clear_errors(); return $html; }

  $imgs = $dom->getElementsByTagName('img');
  $copy = [];
  foreach ($imgs as $i) $copy[] = $i;

  foreach ($copy as $img){
    $src = $img->getAttribute('src');
    if (!$src || preg_match('~^data:|^https?://~i',$src)) continue;
    $webp = webp_convert_and_get_url($src);
    if (!$webp) continue;

    $picture = $dom->createElement('picture');
    $source  = $dom->createElement('source');
    $source->setAttribute('type','image/webp');
    $source->setAttribute('srcset',$webp);
    $picture->appendChild($source);

    if (!$img->hasAttribute('loading'))  $img->setAttribute('loading','lazy');
    if (!$img->hasAttribute('decoding')) $img->setAttribute('decoding','async');
    if (!$img->hasAttribute('style'))     $img->setAttribute('style','display:block;width:100%;height:auto');

    $imgParent = $img->parentNode;
    $picture->appendChild($img->cloneNode(true));
    $imgParent->replaceChild($picture,$img);
  }
  $out = $dom->saveHTML(); libxml_clear_errors();
  return $out ?: $html;
}

function webp_convert_and_get_url(string $src): ?string {
  $srcClean = strtok($src,'?');
  $ext = strtolower(pathinfo($srcClean, PATHINFO_EXTENSION));
  if (!in_array($ext, WEBP_EXTS, true)) return null;

  $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
  if ($docRoot==='') return null;

  $absOrig = realpath($docRoot.'/'.ltrim($srcClean,'/'));
  if ($absOrig===false || !is_file($absOrig)) return null;

  $absDir = dirname($absOrig);
  $base   = pathinfo($absOrig, PATHINFO_FILENAME);
  $absWebp= $absDir.'/'.$base.'.webp';

  if (is_file($absWebp) && filemtime($absWebp) >= filemtime($absOrig))
    return path_to_url($absWebp);

  if (!is_writable($absDir)) return null;

  switch ($ext){
    case 'png':
      $img=@imagecreatefrompng($absOrig);
      if(!$img) return null;
      imagepalettetotruecolor($img); imagealphablending($img,true); imagesavealpha($img,true);
      break;
    default:
      $img=@imagecreatefromjpeg($absOrig);
      if(!$img) return null;
  }
  $ok=@imagewebp($img,$absWebp,WEBP_QUALITY); imagedestroy($img);
  if(!$ok || !is_file($absWebp)){ if(is_file($absWebp)) @unlink($absWebp); return null; }
  @touch($absWebp, filemtime($absOrig));
  return path_to_url($absWebp);
}

function path_to_url(string $absPath): string {
  $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
  $rel = str_replace('\\','/', substr($absPath, strlen($docRoot)));
  return ($rel && $rel[0]==='/') ? $rel : '/'.$rel;
}
