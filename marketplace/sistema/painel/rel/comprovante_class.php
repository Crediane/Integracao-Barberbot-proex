<?php
/**
 * BarberBot - comprovante_class.php
 * Ajuste técnico: evita que avisos E_DEPRECATED do Dompdf/PHP 8.2 sejam impressos
 * antes dos headers do PDF. Não altera dados, consulta, layout, tenant ou regra financeira.
 */
$__bb_error_reporting_original = error_reporting();
$__bb_error_reporting_pdf = $__bb_error_reporting_original;
if (defined('E_DEPRECATED')) {
  $__bb_error_reporting_pdf = $__bb_error_reporting_pdf & ~E_DEPRECATED;
}
if (defined('E_USER_DEPRECATED')) {
  $__bb_error_reporting_pdf = $__bb_error_reporting_pdf & ~E_USER_DEPRECATED;
}
error_reporting($__bb_error_reporting_pdf);

if (session_status() !== PHP_SESSION_ACTIVE) { @session_start(); }

if (!function_exists('__bb_resolver_loja_slug')) {
  function __bb_resolver_loja_slug($valor) {
    if (is_array($valor) || is_object($valor)) {
      return '';
    }

    $valor = trim((string)$valor);
    if ($valor === '') {
      return '';
    }

    if (preg_match('~/loja/([a-zA-Z0-9_-]+)~', $valor, $m)) {
      return $m[1];
    }

    if (preg_match('~^[a-zA-Z0-9_-]+$~', $valor)) {
      return $valor;
    }

    return '';
  }
}

$__bb_loja_slug = '';

$__bb_fontes_loja = [
  $_GET['loja'] ?? '',
  $_POST['loja'] ?? '',
  $_REQUEST['loja'] ?? '',
  $_SERVER['REQUEST_URI'] ?? '',
  $_SERVER['HTTP_REFERER'] ?? '',
  $_SESSION['tenant_slug'] ?? '',
  $_SESSION['loja_slug'] ?? '',
  $_SESSION['loja'] ?? '',
  $_COOKIE['tenant_slug'] ?? '',
  $_COOKIE['loja_slug'] ?? '',
  $_COOKIE['loja'] ?? '',
];

foreach ($__bb_fontes_loja as $__bb_fonte_loja) {
  $__bb_slug_tmp = __bb_resolver_loja_slug($__bb_fonte_loja);
  if ($__bb_slug_tmp !== '') {
    $__bb_loja_slug = $__bb_slug_tmp;
    break;
  }
}

if ($__bb_loja_slug !== '') {
  $_GET['loja'] = $__bb_loja_slug;
  $_POST['loja'] = $__bb_loja_slug;
  $_REQUEST['loja'] = $__bb_loja_slug;
  $_SESSION['tenant_slug'] = $__bb_loja_slug;
  $_SESSION['loja_slug'] = $__bb_loja_slug;
}

require_once __DIR__ . '/../../conexao.php';

$id = (int)($_REQUEST['id'] ?? 0);
if ($id <= 0) { die('ID inválido'); }

/**
 * Carrega o HTML do layout (comprovante.php) LOCALMENTE
 * Motivo: garante o mesmo contexto do tenant (sessão + banco individual).
 */
if (!defined('COMPROVANTE_EMBUTIDO')) {
  define('COMPROVANTE_EMBUTIDO', true);
}

$__bk_get_id = $_GET['id'] ?? null;
$__bk_post_id = $_POST['id'] ?? null;
$__bk_get_loja = $_GET['loja'] ?? null;
$__bk_post_loja = $_POST['loja'] ?? null;

$_GET['id'] = $id;
$_POST['id'] = $id;

if (!empty($__bb_loja_slug)) {
  $_GET['loja'] = $__bb_loja_slug;
  $_POST['loja'] = $__bb_loja_slug;
}

ob_start();
include __DIR__ . '/comprovante.php';
$html = ob_get_clean();

if ($__bk_get_id === null) { unset($_GET['id']); } else { $_GET['id'] = $__bk_get_id; }
if ($__bk_post_id === null) { unset($_POST['id']); } else { $_POST['id'] = $__bk_post_id; }
if ($__bk_get_loja === null) { unset($_GET['loja']); } else { $_GET['loja'] = $__bk_get_loja; }
if ($__bk_post_loja === null) { unset($_POST['loja']); } else { $_POST['loja'] = $__bk_post_loja; }

if ($html === false || trim($html) === '') {
  die('Não foi possível carregar o layout do comprovante.');
}

// se o sistema não estiver em modo PDF, só exibe o HTML
if (!isset($tipo_rel) || $tipo_rel !== 'PDF') {
  header('Content-Type: text/html; charset=UTF-8');
  echo $html;
  exit;
}

require_once __DIR__ . '/../../dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Helvetica');

$pdf = new Dompdf($options);

// A4 retrato (combina com o layout que você quer)
$pdf->setPaper('A4', 'portrait');

$pdf->loadHtml($html);
$pdf->render();

// salva uma cópia (opcional)
$output   = $pdf->output();
$pasta    = __DIR__ . '/../pdf';
if (!is_dir($pasta)) { @mkdir($pasta, 0775, true); }
$arquivo  = $pasta . "/comprovante_{$id}.pdf";
@file_put_contents($arquivo, $output);

// abre no viewer do navegador (zoom/imprimir)
$nomeExibicao = "Comprovante_{$id}.pdf";
if (function_exists('ob_get_length')) { while (ob_get_level()) { @ob_end_clean(); } }

if (headers_sent($arquivoHeaders, $linhaHeaders)) {
  die('Não foi possível gerar o PDF: saída enviada antes dos headers em ' . $arquivoHeaders . ':' . $linhaHeaders);
}

header_remove('Content-Type');
header('Content-Type: application/pdf');

$pdf->stream($nomeExibicao, ['Attachment' => false]);
exit;
