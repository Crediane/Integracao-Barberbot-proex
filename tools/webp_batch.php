<?php
// tools/webp_batch.php — Converte JPG/PNG para WEBP (execução manual ou cron)
// Acesse: https://agenda.jacycabeleireiro.com/tools/webp_batch.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
set_time_limit(0);
ini_set('memory_limit', '512M');

function jpgPngToWebp($srcAbs, $dstAbs, $quality = 82) {
  $ext = strtolower(pathinfo($srcAbs, PATHINFO_EXTENSION));
  if (!in_array($ext, ['jpg','jpeg','png'])) return false;

  if ($ext === 'png') {
    $img = @imagecreatefrompng($srcAbs);
    if (!$img) return false;
    // preserva transparência
    imagepalettetotruecolor($img);
    imagealphablending($img, true);
    imagesavealpha($img, true);
    $ok = imagewebp($img, $dstAbs, $quality);
  } else {
    $img = @imagecreatefromjpeg($srcAbs);
    if (!$img) return false;
    $ok = imagewebp($img, $dstAbs, $quality);
  }
  if (isset($img) && is_resource($img)) imagedestroy($img);
  return $ok && file_exists($dstAbs);
}

$root = dirname(__DIR__); // raiz do site
$pastas = [
  $root . '/sistema/painel/img/servicos',
  $root . '/sistema/painel/img/produtos',
  $root . '/sistema/painel/img/foto_admin',
];

$total = 0; $novos = 0; $erros = 0;

echo "<h2>Conversão para WEBP</h2>";
foreach ($pastas as $dir) {
  if (!is_dir($dir)) { echo "<p style='color:#999'>Ignorado (não existe): $dir</p>"; continue; }
  echo "<h3>Pasta: $dir</h3>";

  $lista = glob($dir.'/*.{jpg,JPG,jpeg,JPEG,png,PNG}', GLOB_BRACE) ?: [];
  if (!$lista) { echo "<p style='color:#999'>Sem JPG/PNG</p>"; continue; }

  foreach ($lista as $src) {
    $total++;
    $webp = preg_replace('/\.(jpe?g|png)$/i', '.webp', $src);
    if (file_exists($webp)) { echo "<div>✔ Já existe: ".basename($webp)."</div>"; continue; }

    if (jpgPngToWebp($src, $webp)) {
      $novos++;
      echo "<div>✅ Criado: ".basename($webp)."</div>";
    } else {
      $erros++;
      echo "<div style='color:red'>❌ Falhou: ".basename($src)."</div>";
    }
  }
}
echo "<hr><b>Arquivos encontrados:</b> $total | <b>Novos WEBP:</b> $novos | <b>Erros:</b> $erros";
