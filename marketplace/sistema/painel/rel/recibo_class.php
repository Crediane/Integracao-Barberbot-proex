<?php
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

$id     = (int)($_REQUEST['id'] ?? 0);
$enviar = (string)($_REQUEST['enviar'] ?? '');

if ($id <= 0) {
  die('ID inválido');
}

/**
 * Carrega o HTML do layout (recibo.php) LOCALMENTE
 * Motivo: garante o mesmo contexto do tenant (sessão + banco individual).
 */
if (!defined('RECIBO_EMBUTIDO')) {
  define('RECIBO_EMBUTIDO', true);
}

// backup de GET/POST usados no template
$__bk_get_id    = $_GET['id'] ?? null;
$__bk_post_id   = $_POST['id'] ?? null;
$__bk_get_loja  = $_GET['loja'] ?? null;
$__bk_post_loja = $_POST['loja'] ?? null;

// o template aceita GET/POST, então setamos ambos
$_GET['id']  = $id;
$_POST['id'] = $id;

if (!empty($__bb_loja_slug)) {
  $_GET['loja'] = $__bb_loja_slug;
  $_POST['loja'] = $__bb_loja_slug;
}

ob_start();
include __DIR__ . '/recibo.php';
$html = ob_get_clean();

// restaura
if ($__bk_get_id === null) { unset($_GET['id']); } else { $_GET['id'] = $__bk_get_id; }
if ($__bk_post_id === null) { unset($_POST['id']); } else { $_POST['id'] = $__bk_post_id; }
if ($__bk_get_loja === null) { unset($_GET['loja']); } else { $_GET['loja'] = $__bk_get_loja; }
if ($__bk_post_loja === null) { unset($_POST['loja']); } else { $_POST['loja'] = $__bk_post_loja; }

if ($html === false || trim($html) === '') {
  die('Não foi possível carregar o layout do recibo.');
}

/**
 * Se o sistema estiver configurado para NÃO gerar PDF,
 * apenas exibe o HTML direto no navegador.
 */
if (!isset($tipo_rel) || $tipo_rel !== 'PDF') {
  header('Content-Type: text/html; charset=UTF-8');
  echo $html;
  exit;
}

/* ===========================
   GERAÇÃO DE PDF COM DOMPDF
   =========================== */
require_once __DIR__ . '/../../dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Opções do Dompdf
$options = new Options();
$options->set('isRemoteEnabled', true);
$options->set('defaultFont', 'Helvetica');

$pdf = new Dompdf($options);

// A4 retrato
$pdf->setPaper('A4', 'portrait');

$pdf->loadHtml($html);
$pdf->render();

// salva uma cópia (opcional)
$output  = $pdf->output();
$pasta   = __DIR__ . '/../pdf';
if (!is_dir($pasta)) { @mkdir($pasta, 0775, true); }
$arquivo = $pasta . "/recibo_{$id}.pdf";
@file_put_contents($arquivo, $output);

/* ======= Envio opcional no WhatsApp (se houver token/instância) ======= */
$telefone = '';
$parcela  = '';
$cliente  = 0;

$stmt = $pdo->prepare("SELECT cliente, pessoa, parcela FROM receber WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$rx = $stmt->fetch(PDO::FETCH_ASSOC);

if ($rx) {
  $cliente = (int)($rx['cliente'] ?? 0);
  if ($cliente <= 0) {
    $cliente = (int)($rx['pessoa'] ?? 0);
  }
  $parcela = (string)($rx['parcela'] ?? '');
}

if ($cliente > 0) {
  $stmtC = $pdo->prepare("SELECT telefone FROM clientes WHERE id = :id LIMIT 1");
  $stmtC->execute([':id' => $cliente]);
  $rc = $stmtC->fetch(PDO::FETCH_ASSOC);
  $telefone = (string)($rc['telefone'] ?? '');
}

if (!empty($token) && !empty($instancia) && $telefone !== '') {
  $telefone_envio = '55'.preg_replace('/[ ()-]+/', '', $telefone);
  $mensagem       = 'Recibo_Parcela_'.$parcela;
  $url_envio      = rtrim((string)$url_sistema, '/')."/sistema/painel/pdf/recibo_{$id}.pdf";

  ob_start();
  require __DIR__ . '/../../../ajax/file.php';
  ob_end_clean();
}

// abre no viewer do navegador
$nomeExibicao = "Recibo_{$id}.pdf";
if (function_exists('ob_get_length')) { while (ob_get_level()) { @ob_end_clean(); } }

header_remove('Content-Type');
header('Content-Type: application/pdf');

$pdf->stream($nomeExibicao, ['Attachment' => false]);
exit;
