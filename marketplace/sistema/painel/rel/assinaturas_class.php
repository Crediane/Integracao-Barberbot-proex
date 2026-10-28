<?php 
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

if (!function_exists('bb_detect_tenant_slug_rel')) {
    function bb_detect_tenant_slug_rel(): string
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

        foreach (['tenant_slug', 'bb_tenant_slug', 'BB_TENANT_SLUG'] as $chave) {
            if (!empty($_SESSION[$chave])) {
                $candidatos[] = (string) $_SESSION[$chave];
            }
        }

        $serverVars = [
            $_SERVER['REQUEST_URI'] ?? '',
            $_SERVER['REDIRECT_URL'] ?? '',
            $_SERVER['HTTP_REFERER'] ?? '',
        ];

        foreach ($serverVars as $valor) {
            if ($valor !== '' && preg_match('~/loja/([^/?#]+)/~i', $valor, $m)) {
                $candidatos[] = (string) $m[1];
            }
        }

        foreach ($candidatos as $slug) {
            $slug = trim($slug);
            if ($slug === '') {
                continue;
            }
            $slug = preg_replace('/[^a-zA-Z0-9_-]/', '', $slug);
            if ($slug !== '') {
                return $slug;
            }
        }

        return '';
    }
}

$bbTenantSlug = bb_detect_tenant_slug_rel();
if ($bbTenantSlug !== '') {
    $_GET['loja'] = $bbTenantSlug;
    $_REQUEST['loja'] = $bbTenantSlug;
    $_GET['slug'] = $bbTenantSlug;
    $_REQUEST['slug'] = $bbTenantSlug;
    $_SESSION['loja'] = $bbTenantSlug;
    $_SESSION['slug'] = $bbTenantSlug;
    $_SESSION['tenant_slug'] = $bbTenantSlug;
    $_SESSION['bb_tenant_slug'] = $bbTenantSlug;
    $_SESSION['BB_TENANT_SLUG'] = $bbTenantSlug;
}

require_once __DIR__ . '/../../conexao.php';

require_once __DIR__ . '/data_formatada.php';
ob_start();
include __DIR__ . '/assinaturas.php';
$html = ob_get_clean();

//CARREGAR DOMPDF
require_once __DIR__ . '/../../dompdf/autoload.inc.php';
use Dompdf\Dompdf;
use Dompdf\Options;

header("Content-Transfer-Encoding: binary");
header("Content-Type: image/png");

//INICIALIZAR A CLASSE DO DOMPDF
$options = new Options();
$options->set('isRemoteEnabled', TRUE);
$pdf = new DOMPDF($options);


//Definir o tamanho do papel e orientação da página
$pdf->set_paper('A4', 'portrait');

//CARREGAR O CONTEÚDO HTML
$pdf->load_html($html);

//RENDERIZAR O PDF
$pdf->render();
//NOMEAR O PDF GERADO


$pdf->stream(
	'assinaturas.pdf',
	array("Attachment" => false)
);

 ?>