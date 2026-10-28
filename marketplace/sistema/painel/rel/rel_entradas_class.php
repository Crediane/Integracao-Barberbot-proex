<?php
// ============================================================================
// BarberBot Software - Wrapper do PDF de Ganhos
// Arquivo: /public_html/sistema/painel/rel/rel_entradas_class.php
// Correção direta no mesmo padrão /loja/{slug}/
// - resolve MASTER x TENANT antes de verificar.php e conexao.php
// - mantém o arquivo físico em /sistema/...
// - repassa datas/filtros sem mudar a lógica do relatório
// ============================================================================

$__bb_reqPath = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
$__bb_reqPath = $__bb_reqPath ? $__bb_reqPath : '/';
$__bb_refPath = '';
if (!empty($_SERVER['HTTP_REFERER'])) {
    $__bb_refPath = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH);
    $__bb_refPath = $__bb_refPath ? $__bb_refPath : '';
}

$__bb_slug = '';
if (!empty($_GET['loja'])) {
    $__bb_slug = strtolower(trim((string)$_GET['loja']));
} elseif (!empty($_POST['loja'])) {
    $__bb_slug = strtolower(trim((string)$_POST['loja']));
} elseif (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath, $__bb_m)) {
    $__bb_slug = strtolower(trim((string)$__bb_m[1]));
} elseif (!empty($__bb_refPath) && preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_refPath, $__bb_m2)) {
    $__bb_slug = strtolower(trim((string)$__bb_m2[1]));
}
$__bb_slug = preg_replace('/[^a-z0-9_-]/', '', $__bb_slug);

$__bb_lojaByPath = (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath) || (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_refPath);
$__bb_hasLoja = $__bb_lojaByPath || $__bb_slug !== '';
$__bb_isSistema = (strpos($__bb_reqPath, '/sistema') === 0) || (strpos($__bb_refPath, '/sistema') === 0);
$__bb_forceMaster = ($__bb_isSistema && !$__bb_lojaByPath && $__bb_slug === '');

if ($__bb_forceMaster) {
    $__bb_sessionName = 'BB_MASTER';
} elseif ($__bb_hasLoja) {
    $__bb_sessionName = ($__bb_slug !== '') ? ('BB_TENANT_' . strtoupper(substr(sha1($__bb_slug), 0, 12))) : 'BB_TENANT';
} else {
    $__bb_sessionName = 'BB_SITE';
}

if (session_status() === PHP_SESSION_ACTIVE) {
    if (session_name() !== $__bb_sessionName) {
        session_write_close();
        @session_name($__bb_sessionName);
        if (!empty($_COOKIE[$__bb_sessionName])) {
            @session_id((string)$_COOKIE[$__bb_sessionName]);
        }
        @session_start();
    }
} else {
    @session_name($__bb_sessionName);
    if (!empty($_COOKIE[$__bb_sessionName])) {
        @session_id((string)$_COOKIE[$__bb_sessionName]);
    }
    @session_start();
}

if ($__bb_slug !== '') {
    $_GET['loja'] = $__bb_slug;
    $_POST['loja'] = $__bb_slug;
    $_SESSION['tenant_slug'] = $__bb_slug;
}

require_once __DIR__ . '/../verificar.php';
require_once __DIR__ . '/../../conexao.php';

$dataInicial = isset($_POST['dataInicial']) ? trim((string)$_POST['dataInicial']) : '';
$dataFinal   = isset($_POST['dataFinal']) ? trim((string)$_POST['dataFinal']) : '';
$filtro      = isset($_POST['filtro']) ? trim((string)$_POST['filtro']) : '';
$cliente     = isset($_POST['cliente']) ? trim((string)$_POST['cliente']) : '';

if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dataInicial)) {
    $p = explode('/', $dataInicial);
    $dataInicial = $p[2] . '-' . $p[1] . '-' . $p[0];
}
if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $dataFinal)) {
    $p = explode('/', $dataFinal);
    $dataFinal = $p[2] . '-' . $p[1] . '-' . $p[0];
}

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicial)) {
    $dataInicial = date('Y-m-01');
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFinal)) {
    $dataFinal = date('Y-m-d');
}
if ($dataFinal < $dataInicial) {
    $__tmpData = $dataInicial;
    $dataInicial = $dataFinal;
    $dataFinal = $__tmpData;
}

$token_rel = 'A5030';
require_once __DIR__ . '/data_formatada.php';
ob_start();
include __DIR__ . '/rel_entradas.php';
$html = ob_get_clean();

require_once __DIR__ . '/../../dompdf/autoload.inc.php';
use Dompdf\Dompdf;
use Dompdf\Options;
header('Content-Transfer-Encoding: binary');
header('Content-Type: image/png');

$options = new Options();
$options->set('isRemoteEnabled', true);
$pdf = new DOMPDF($options);
$pdf->set_paper('A4', 'portrait');
$pdf->load_html($html);
$pdf->render();
$pdf->stream(
    'rel_entradas.pdf',
    array('Attachment' => false)
);
?>
