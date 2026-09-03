<?php
// tools/webp_helper.php — converte um único arquivo jpg/png para webp
function gerar_webp_unico($absFile, $quality = 82) {
  if (!is_file($absFile)) return false;
  $ext = strtolower(pathinfo($absFile, PATHINFO_EXTENSION));
  if (!in_array($ext,['jpg','jpeg','png'])) return false;

  $dst = preg_replace('/\.(jpe?g|png)$/i', '.webp', $absFile);
  if (file_exists($dst)) return $dst;

  if ($ext === 'png') {
    $img = @imagecreatefrompng($absFile);
    if (!$img) return false;
    imagepalettetotruecolor($img);
    imagealphablending($img, true);
    imagesavealpha($img, true);
    $ok = imagewebp($img, $dst, $quality);
  } else {
    $img = @imagecreatefromjpeg($absFile);
    if (!$img) return false;
    $ok = imagewebp($img, $dst, $quality);
  }
  if (isset($img) && is_resource($img)) imagedestroy($img);
  return $ok ? $dst : false;
}
