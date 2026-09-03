<?php 
if (session_status() !== PHP_SESSION_ACTIVE) {
	@session_start();
}

if (!function_exists('bb_extract_tenant_slug_from_rel_source')) {
	function bb_extract_tenant_slug_from_rel_source($source){
		$source = (string)$source;
		if($source === ''){
			return '';
		}

		if(preg_match('#/loja/([^/?#&]+)/?#i', $source, $matches)){
			return trim((string)$matches[1]);
		}

		return '';
	}
}

if (!function_exists('bb_apply_relatorio_tenant_context')) {
	function bb_apply_relatorio_tenant_context(){
		$slug = '';

		foreach (array('loja', 'slug') as $key) {
			if ($slug === '' && isset($_GET[$key]) && trim((string)$_GET[$key]) !== '') {
				$slug = trim((string)$_GET[$key]);
			}
			if ($slug === '' && isset($_POST[$key]) && trim((string)$_POST[$key]) !== '') {
				$slug = trim((string)$_POST[$key]);
			}
			if ($slug === '' && isset($_REQUEST[$key]) && trim((string)$_REQUEST[$key]) !== '') {
				$slug = trim((string)$_REQUEST[$key]);
			}
		}

		if ($slug === '' && !empty($_SERVER['REQUEST_URI'])) {
			$slug = bb_extract_tenant_slug_from_rel_source($_SERVER['REQUEST_URI']);
		}

		if ($slug === '' && !empty($_SERVER['HTTP_REFERER'])) {
			$slug = bb_extract_tenant_slug_from_rel_source($_SERVER['HTTP_REFERER']);
		}

		foreach (array('BB_TENANT_SLUG', 'tenant_slug', 'loja', 'slug') as $key) {
			if ($slug === '' && isset($_SESSION[$key]) && trim((string)$_SESSION[$key]) !== '') {
				$slug = trim((string)$_SESSION[$key]);
			}
		}

		foreach (array('BB_TENANT_SLUG', 'tenant_slug', 'loja', 'slug') as $key) {
			if ($slug === '' && isset($_COOKIE[$key]) && trim((string)$_COOKIE[$key]) !== '') {
				$slug = trim((string)$_COOKIE[$key]);
			}
		}

		if ($slug !== '') {
			$_GET['loja'] = $slug;
			$_REQUEST['loja'] = $slug;
			if (!isset($_POST['loja']) || $_POST['loja'] === '') {
				$_POST['loja'] = $slug;
			}

			$_GET['slug'] = $slug;
			$_REQUEST['slug'] = $slug;
			if (!isset($_POST['slug']) || $_POST['slug'] === '') {
				$_POST['slug'] = $slug;
			}

			$_SESSION['BB_TENANT_SLUG'] = $slug;
			$_SESSION['tenant_slug'] = $slug;
			$_SESSION['loja'] = $slug;
			$_SESSION['slug'] = $slug;
		}
	}
}

bb_apply_relatorio_tenant_context();

require_once __DIR__ . '/../verificar.php';
require_once __DIR__ . '/../../conexao.php';

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if($id <= 0 && isset($_POST['id'])){
	$id = (int)$_POST['id'];
}

$token_rel = 'A5030';
require_once __DIR__ . '/data_formatada.php';
ob_start();
include __DIR__ . '/contrato_servico.php';
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
	'contrato_servico.pdf',
	array("Attachment" => false)
);
?>
