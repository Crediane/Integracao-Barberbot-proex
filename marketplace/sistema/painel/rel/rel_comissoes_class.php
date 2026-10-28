<?php
// Mantém o arquivo físico em /sistema/... e tenta herdar o contexto da loja
// quando o relatório é aberto a partir de /loja/{slug}/... .
if (session_status() === PHP_SESSION_NONE) {
    @session_start();
}

if (!function_exists('bb_detect_loja_slug_rel')) {
    function bb_detect_loja_slug_rel(): string
    {
        $candidatos = [];

        foreach (['loja', 'slug'] as $chave) {
            if (!empty($_GET[$chave])) {
                $candidatos[] = (string) $_GET[$chave];
            }
            if (!empty($_POST[$chave])) {
                $candidatos[] = (string) $_POST[$chave];
            }
            if (!empty($_REQUEST[$chave])) {
                $candidatos[] = (string) $_REQUEST[$chave];
            }
            if (!empty($_SESSION[$chave])) {
                $candidatos[] = (string) $_SESSION[$chave];
            }
        }

        foreach (['tenant_slug', 'slug_loja'] as $chave) {
            if (!empty($_SESSION[$chave])) {
                $candidatos[] = (string) $_SESSION[$chave];
            }
        }

        foreach (['REQUEST_URI', 'HTTP_REFERER'] as $serverKey) {
            if (!empty($_SERVER[$serverKey]) && preg_match('#/loja/([^/]+)/#i', (string) $_SERVER[$serverKey], $m)) {
                $candidatos[] = (string) $m[1];
            }
        }

        foreach ($candidatos as $valor) {
            $valor = trim($valor);
            if ($valor !== '' && preg_match('/^[a-z0-9][a-z0-9_-]*$/i', $valor)) {
                return $valor;
            }
        }

        return '';
    }
}

$lojaSlug = bb_detect_loja_slug_rel();
if ($lojaSlug !== '') {
    $_GET['loja'] = $lojaSlug;
    $_POST['loja'] = $lojaSlug;
    $_REQUEST['loja'] = $lojaSlug;
    $_SESSION['loja'] = $lojaSlug;
    $_SESSION['tenant_slug'] = $lojaSlug;
}

require_once __DIR__ . '/../verificar.php';
require_once __DIR__ . '/../../conexao.php';

$hoje = date('Y-m-d');
$dataInicial = isset($_POST['dataInicial']) ? trim((string) $_POST['dataInicial']) : '';
$dataFinal = isset($_POST['dataFinal']) ? trim((string) $_POST['dataFinal']) : '';

if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataInicial)) {
    $dataInicial = $hoje;
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataFinal)) {
    $dataFinal = $hoje;
}
if ($dataFinal < $dataInicial) {
    $tmp = $dataInicial;
    $dataInicial = $dataFinal;
    $dataFinal = $tmp;
}

$pago = isset($_POST['pago']) ? trim((string) $_POST['pago']) : '';
$funcionario = isset($_POST['funcionario']) ? trim((string) $_POST['funcionario']) : '';
$funcionario_post = $funcionario;

if (strcasecmp($pago, 'Todas') === 0 || strcasecmp($pago, 'Todos') === 0) {
    $pago = '';
}
if (strcasecmp($funcionario, 'Todas') === 0 || strcasecmp($funcionario, 'Todos') === 0) {
    $funcionario = '';
    $funcionario_post = '';
}

$token_rel = 'A5030';
require_once __DIR__ . '/data_formatada.php';
ob_start();
include __DIR__ . '/rel_comissoes.php';
$html = ob_get_clean();

require_once __DIR__ . '/../../dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

header('Content-Transfer-Encoding: binary');
header('Content-Type: image/png');

$options = new Options();
$options->set('isRemoteEnabled', true);
$pdf = new Dompdf($options);
$pdf->set_paper('A4', 'portrait');
$pdf->load_html($html);
$pdf->render();
$pdf->stream('rel_comissoes.pdf', ['Attachment' => false]);
?>
