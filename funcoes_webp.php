<?php
/**
 * Converte JPG/PNG para WEBP e retorna o CAMINHO WEB (ex.: "sistema/.../foto.webp").
 * Se der qualquer problema, retorna o caminho original (sempre tem fallback).
 */
function converterParaWebP(string $caminhoWeb, int $qualidade = 82): string
{
    // Sem suporte a WebP no GD -> devolve original
    if (!function_exists('imagewebp')) {
        return $caminhoWeb;
    }

    // Guarda a query-string para reanexar depois (cache-busting, etc.)
    $qs          = parse_url($caminhoWeb, PHP_URL_QUERY);
    $caminhoLimpo = strtok($caminhoWeb, '?');

    // Já é webp? devolve como veio
    $extEntrada = strtolower(pathinfo($caminhoLimpo, PATHINFO_EXTENSION));
    if ($extEntrada === 'webp') {
        return $caminhoWeb;
    }

    // Só converte JPG/JPEG/PNG
    if (!in_array($extEntrada, ['jpg','jpeg','png'], true)) {
        return $caminhoWeb;
    }

    // Mapeia para caminho físico
    $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
    $fsOrig  = $docRoot ? ($docRoot . '/' . ltrim($caminhoLimpo, '/')) : '';
    if (!is_file($fsOrig)) {
        // Fallback: relativo ao diretório deste arquivo
        $alt = realpath(__DIR__ . '/' . ltrim($caminhoLimpo, '/'));
        if ($alt && is_file($alt)) {
            $fsOrig = $alt;
        } else {
            return $caminhoWeb; // não achou fisicamente -> usa original
        }
    }

    // Caminhos de saída
    $webPath = preg_replace('/\.(jpe?g|png)$/i', '.webp', $caminhoLimpo);
    $fsWeb   = ($docRoot ? ($docRoot . '/' . ltrim($webPath, '/')) : realpath(dirname($fsOrig)) . '/' . basename($webPath));

    // Se já existe e está ok/atualizado, usa
    if (is_file($fsWeb) && filemtime($fsWeb) >= filemtime($fsOrig) && _webpValido($fsWeb)) {
        return $qs ? ($webPath . '?' . $qs) : $webPath;
    }

    // Remove webp inválido antigo
    if (is_file($fsWeb) && !_webpValido($fsWeb)) @unlink($fsWeb);

    // Dir de destino gravável?
    $dir = dirname($fsWeb);
    if (!is_dir($dir) || !is_writable($dir)) {
        return $caminhoWeb;
    }

    // Abre a fonte
    $img = null;
    if ($extEntrada === 'png') {
        $img = @imagecreatefrompng($fsOrig);
        if (!$img) return $caminhoWeb;
        imagepalettetotruecolor($img);
        imagealphablending($img, true);
        imagesavealpha($img, true);
    } else {
        $img = @imagecreatefromjpeg($fsOrig);
        if (!$img) {
            $raw = @file_get_contents($fsOrig);
            if ($raw) $img = @imagecreatefromstring($raw);
            if (!$img) return $caminhoWeb;
        }
        // Corrige orientação EXIF
        if (function_exists('exif_read_data')) {
            $exif = @exif_read_data($fsOrig);
            if (!empty($exif['Orientation'])) {
                switch ((int)$exif['Orientation']) {
                    case 3: $img = imagerotate($img, 180, 0); break;
                    case 6: $img = imagerotate($img, -90, 0); break;
                    case 8: $img = imagerotate($img, 90, 0); break;
                }
            }
        }
    }

    $ok = @imagewebp($img, $fsWeb, max(0, min(100, $qualidade)));
    if (is_resource($img) || (class_exists('GdImage') && $img instanceof GdImage)) {
        imagedestroy($img);
    }

    if (!$ok || !_webpValido($fsWeb)) {
        if (is_file($fsWeb)) @unlink($fsWeb);
        return $caminhoWeb;
    }

    // Só mantém se economizar pelo menos 2%
    $origSize = @filesize($fsOrig) ?: 1;
    $webpSize = @filesize($fsWeb)  ?: PHP_INT_MAX;
    if ($webpSize > ($origSize * 0.98)) {
        @unlink($fsWeb);
        return $caminhoWeb;
    }

    // Sincroniza mtime e devolve caminho web (com query original)
    @touch($fsWeb, filemtime($fsOrig));
    return $qs ? ($webPath . '?' . $qs) : $webPath;
}

/** Valida se um arquivo .webp é real e não está zerado */
function _webpValido(string $fsPath): bool
{
    if (!is_file($fsPath) || filesize($fsPath) < 100) return false;
    $gi = @getimagesize($fsPath);
    return $gi && !empty($gi['mime']) && stripos($gi['mime'], 'webp') !== false;
}
