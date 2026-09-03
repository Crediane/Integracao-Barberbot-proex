<?php 
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

if (!function_exists('bb_extract_loja_slug_det_caixa')) {
    function bb_extract_loja_slug_det_caixa(): string
    {
        $candidatos = [];

        foreach (['loja', 'slug', 'tenant', 'tenant_slug'] as $chave) {
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

        foreach (['BB_TENANT_SLUG', 'tenant_slug', 'loja_slug', 'slug_loja'] as $chave) {
            if (!empty($_SESSION[$chave])) {
                $candidatos[] = (string) $_SESSION[$chave];
            }
            if (!empty($_COOKIE[$chave])) {
                $candidatos[] = (string) $_COOKIE[$chave];
            }
        }

        $urls = [];
        if (!empty($_SERVER['REQUEST_URI'])) {
            $urls[] = (string) $_SERVER['REQUEST_URI'];
        }
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $urls[] = (string) $_SERVER['HTTP_REFERER'];
        }

        foreach ($urls as $url) {
            if (preg_match('~/loja/([^/]+)/~i', $url, $match)) {
                $candidatos[] = (string) $match[1];
            }
        }

        foreach ($candidatos as $valor) {
            $valor = trim((string) $valor);
            $valor = trim($valor, "/ \t\n\r\0\x0B");
            if ($valor === '') {
                continue;
            }
            if (preg_match('/^[a-zA-Z0-9_-]+$/', $valor)) {
                return $valor;
            }
        }

        return '';
    }
}

$loja_slug = bb_extract_loja_slug_det_caixa();

if ($loja_slug !== '') {
    $_GET['loja'] = $loja_slug;
    $_REQUEST['loja'] = $loja_slug;
    $_SESSION['loja'] = $loja_slug;
    $_SESSION['tenant_slug'] = $loja_slug;
    $_SESSION['loja_slug'] = $loja_slug;
    $_SESSION['slug_loja'] = $loja_slug;
    $_SESSION['BB_TENANT_SLUG'] = $loja_slug;
}

require_once __DIR__ . '/../verificar.php';
require_once __DIR__ . '/../../conexao.php';

$id = isset($_POST['id']) ? (int) $_POST['id'] : (isset($_GET['id']) ? (int) $_GET['id'] : 0);

require_once __DIR__ . '/data_formatada.php';
ob_start();
include __DIR__ . '/detalhamento_caixa.php';
$html = ob_get_clean();

//CARREGAR DOMPDF
require_once __DIR__ . '/../../dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

header("Content-Transfer-Encoding: binary");
header("Content-Type: image/png");

//INICIALIZAR A CLASSE DO DOMPDF
$options = new Options();
$options->set('isRemoteEnabled', true);

$pdf = new DOMPDF($options);

//Definir o tamanho do papel e orientação da página
$pdf->set_paper('A4', 'portrait');

//CARREGAR O CONTEÚDO HTML
$pdf->load_html($html);

//RENDERIZAR O PDF
$pdf->render();

//NOMEAR O PDF GERADO
$pdf->stream(
    'detalhamento_caixa.pdf',
    array("Attachment" => false)
);
?>