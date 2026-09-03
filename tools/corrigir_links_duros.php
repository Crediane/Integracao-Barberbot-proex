<?php
/**
 * Corrige links hardcoded /sistema/... dentro de arquivos .php
 * para funções dinâmicas bb_url_* (sem quebrar se bootstrap não estiver carregado).
 *
 * USO:
 * php tools/corrigir_links_duros.php /home/SEU_USUARIO/public_html
 */

$root = $argv[1] ?? '';
if ($root === '' || !is_dir($root)) {
  echo "Uso: php tools/corrigir_links_duros.php /caminho/do/public_html\n";
  exit(1);
}

$ignorar = ['/.git/','/vendor/','/dompdf/','/dist/','/uploads/','/images/'];

function ignora($path, $ignorar) {
  foreach ($ignorar as $i) if (strpos($path, $i) !== false) return true;
  return false;
}

$it = new RecursiveIteratorIterator(
  new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
);

$alterados = 0;
$lidos = 0;

foreach ($it as $f) {
  if (!$f->isFile()) continue;

  $path = $f->getPathname();
  if (ignora($path, $ignorar)) continue;

  if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'php') continue;

  $lidos++;
  $orig = file_get_contents($path);
  if ($orig === false) continue;

  $txt = $orig;

  // href/action (padrões seguros)
  $txt = preg_replace('~\bhref\s*=\s*([\'"])/sistema/acesso/?\1~i',
    'href="<?php echo function_exists(\'bb_url_acesso\') ? bb_url_acesso() : \'/sistema/acesso\'; ?>"', $txt);

  $txt = preg_replace('~\bhref\s*=\s*([\'"])/sistema/painel/?\1~i',
    'href="<?php echo function_exists(\'bb_url_painel\') ? bb_url_painel() : \'/sistema/painel/\'; ?>"', $txt);

  $txt = preg_replace('~\bhref\s*=\s*([\'"])/sistema/?\1~i',
    'href="<?php echo function_exists(\'bb_url_sistema\') ? bb_url_sistema() : \'/sistema/\'; ?>"', $txt);

  $txt = preg_replace('~\baction\s*=\s*([\'"])/sistema/autenticar\.php\1~i',
    'action="<?php echo function_exists(\'bb_url_sistema\') ? bb_url_sistema(\'autenticar.php\') : \'/sistema/autenticar.php\'; ?>"', $txt);

  // JS redirects comuns (dentro de PHP/HTML)
  $txt = preg_replace('~window\.location\.href\s*=\s*([\'"])/sistema/acesso/?\1~i',
    'window.location.href = "<?php echo function_exists(\'bb_url_acesso\') ? bb_url_acesso() : \'/sistema/acesso\'; ?>"', $txt);

  // header Location
  $txt = preg_replace('~header\(\s*([\'"])Location:\s*/sistema/painel/?\1\s*\)\s*;~i',
    'header("Location: " . (function_exists(\'bb_url_painel\') ? bb_url_painel() : "/sistema/painel/"));', $txt);

  $txt = preg_replace('~header\(\s*([\'"])Location:\s*/sistema/acesso/?\1\s*\)\s*;~i',
    'header("Location: " . (function_exists(\'bb_url_acesso\') ? bb_url_acesso() : "/sistema/acesso"));', $txt);

  if ($txt !== $orig) {
    // backup
    if (!file_exists($path . '.bak')) {
      file_put_contents($path . '.bak', $orig);
    }
    file_put_contents($path, $txt);
    $alterados++;
    echo "OK: {$path}\n";
  }
}

echo "\nLidos: {$lidos}\nAlterados: {$alterados}\n";
