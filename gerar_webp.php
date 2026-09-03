<?php
// gerar_webp.php — rode 1x e depois apague
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(0);
@ini_set('memory_limit', '1024M');

/* ===================== CONFIG ===================== */
$ALVOS = [
  __DIR__ . '/sistema/img/logo.png',             // arquivo único
  __DIR__ . '/sistema/painel/img/servicos',      // pasta (recursivo)
  __DIR__ . '/sistema/painel/img/produtos',      // pasta (recursivo)
  __DIR__ . '/sistema/painel/img/foto_admin',    // pasta (recursivo)
  __DIR__ . '/sistema/painel/img/comentarios',   // pasta (recursivo)
];

$QUALITY_JPEG = 82;       // qualidade padrão p/ JPEG
$QUALITY_PNG  = 82;       // GD não tem "lossless WebP"; se tiver Imagick, usa lossless
$KEEP_IF_SAVES_AT_LEAST = 0.98; // guarda WEBP só se (tamanho_webp <= 98% do original)
/* ================================================== */

// helpers
function human($b){ $u=['B','KB','MB','GB']; for($i=0;$b>1024 && $i<3;$i++) $b/=1024; return number_format($b,2).' '.$u[$i]; }

function isValidWebp($file){
  if (!is_file($file) || filesize($file) < 100) return false;
  $gi = @getimagesize($file);
  return $gi && !empty($gi['mime']) && stripos($gi['mime'],'webp') !== false;
}

function fixOrientationGD($img, $path){
  if (!function_exists('exif_read_data')) return $img;
  $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg','jpeg'])) return $img;
  $exif = @exif_read_data($path);
  if (!$exif || empty($exif['Orientation'])) return $img;
  switch ((int)$exif['Orientation']) {
    case 3: $img = imagerotate($img, 180, 0); break;
    case 6: $img = imagerotate($img, -90, 0); break;
    case 8: $img = imagerotate($img, 90, 0); break;
  }
  return $img;
}

function toWebpImagick($src, $dst){
  try{
    $im = new Imagick($src);
    $fmt = strtolower($im->getImageFormat());
    if ($fmt === 'jpeg' || $fmt === 'jpg') {
      // respeita EXIF orientation
      if (method_exists($im,'autoOrient')) $im->autoOrient();
    }
    $hasAlpha = $im->getImageAlphaChannel();

    // WebP
    $im->setImageFormat('WEBP');
    if ($hasAlpha) {
      if (method_exists($im, 'setOption')) $im->setOption('webp:lossless', 'true');
    } else {
      $im->setImageCompressionQuality(82);
    }

    $ok = $im->writeImage($dst);
    $im->clear(); $im->destroy();
    return $ok ? [true,'ok'] : [false,'imagick write fail'];
  }catch(Throwable $e){
    return [false,'imagick '.$e->getMessage()];
  }
}

function toWebpGD($src, $dst, $qualityJpg=82, $qualityPng=82){
  $ext = strtolower(pathinfo($src, PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg','jpeg','png'])) return [false, 'extensão não suportada'];

  if ($ext === 'png') {
    $img = @imagecreatefrompng($src);
    if (!$img) return [false, 'falha ao abrir PNG'];
    imagepalettetotruecolor($img);
    imagealphablending($img, true);
    imagesavealpha($img, true);
  } else {
    $img = @imagecreatefromjpeg($src);
    if (!$img) {
      $raw = @file_get_contents($src);
      if ($raw) $img = @imagecreatefromstring($raw);
      if (!$img) return [false, 'falha ao abrir JPEG'];
    }
    $img = fixOrientationGD($img, $src);
  }

  // GD usa um único quality de 0-100 pra WebP.
  $quality = ($ext === 'png' ? $qualityPng : $qualityJpg);
  $ok = @imagewebp($img, $dst, $quality);
  if (isset($img)) imagedestroy($img);
  if (!$ok) {
    if (is_file($dst)) @unlink($dst);
    return [false, 'imagewebp falhou'];
  }
  return isValidWebp($dst) ? [true,'ok'] : [false,'webp inválido'];
}

function toWebp($src, $dst, $qJ=82, $qP=82){
  if (class_exists('Imagick')) {
    $r = toWebpImagick($src, $dst);
    if ($r[0]) return $r;
    // se falhar, tenta GD
  }
  if (!function_exists('imagewebp')) return [false,'GD sem suporte a WebP'];
  return toWebpGD($src, $dst, $qJ, $qP);
}

function listImagesRecursive($target){
  $out = [];
  if (is_file($target)) {
    $out[] = $target;
  } elseif (is_dir($target)) {
    $rii = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($target, FilesystemIterator::SKIP_DOTS));
    foreach ($rii as $f) {
      if (!$f->isFile()) continue;
      $ext = strtolower($f->getExtension());
      if (in_array($ext, ['jpg','jpeg','png'])) $out[] = $f->getPathname();
    }
  }
  return $out;
}

/* ===================== EXECUÇÃO ===================== */
$totalCriados = 0;
$totalExist   = 0;
$totalFalhas  = 0;
$totalPiorou  = 0; // webp maior que original (descartado)

echo '<h2>Conversão para WebP</h2>';

foreach ($ALVOS as $alvo){
  $alvo = realpath($alvo) ?: $alvo; // não falha se não existir
  if (!file_exists($alvo)) {
    echo "<p>Ignorado (não existe): {$alvo}</p>";
    continue;
  }

  $lista = listImagesRecursive($alvo);
  if (!$lista) {
    echo "<p>Sem imagens em: {$alvo}</p>";
    continue;
  }

  foreach ($lista as $src) {
    $dir = dirname($src);
    if (!is_writable($dir)) {
      echo "<p style='color:#c60'>Sem permissão de escrita: {$dir}</p>";
    }

    $webp = $dir . '/' . pathinfo($src, PATHINFO_FILENAME) . '.webp';
    $srcSize = filesize($src);
    $srcMTime= filemtime($src);

    // pular se webp válido e atualizado
    if (isValidWebp($webp) && filemtime($webp) >= $srcMTime) {
      $totalExist++;
      // echo "<p>OK: " . basename($webp) . "</p>";
      continue;
    }

    if (is_file($webp) && !isValidWebp($webp)) @unlink($webp);

    list($ok,$msg) = toWebp($src, $webp, $QUALITY_JPEG, $QUALITY_PNG);
    if (!$ok) {
      $totalFalhas++;
      echo "<p style='color:red'>Falhou: {$src} — {$msg}</p>";
      continue;
    }

    // só mantém se economizar espaço (<= 98% do original)
    clearstatcache(true, $webp);
    $webpSize = filesize($webp);
    if ($webpSize > $srcSize * $KEEP_IF_SAVES_AT_LEAST) {
      @unlink($webp);
      $totalPiorou++;
      echo "<p style='color:#c60'>Descartado (maior que original): ".basename($src)." → ".human($webpSize)." &gt; ".human($srcSize)."</p>";
      continue;
    }

    @touch($webp, $srcMTime);
    $totalCriados++;
    echo "<p>Criado: ".basename($webp)." (".human($webpSize)." de ".human($srcSize).")</p>";
  }
}

echo "<hr><b>Finalizado.</b> Novos .webp: {$totalCriados} | Já existiam: {$totalExist} | Descartados (maior que original): {$totalPiorou} | Falhas: {$totalFalhas}";
