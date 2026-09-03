<?php 
// ==========================================================
// RELATÓRIO DE LUCRO - CLASSE PDF
// Arquivo: /public_html/sistema/painel/rel/rel_lucro_class.php
//
// Correção direta no padrão do sistema:
// - Resolve sessão MASTER x TENANT antes do verificar.php
// - Funciona em /loja/{slug}/... e também no link master
// - Mantém a lógica original do relatório
// ==========================================================

$__bb_reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$__bb_refPath = '';
if (!empty($_SERVER['HTTP_REFERER'])) {
	$__bb_refPath = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: '';
}

if (!function_exists('bb_rel_lucro_clean_slug')) {
	function bb_rel_lucro_clean_slug($valor)
	{
		$valor = strtolower(trim((string)$valor));
		return preg_replace('/[^a-z0-9_-]/', '', $valor);
	}
}

$__bb_reqSlug = '';
if (!empty($_GET['loja'])) {
	$__bb_reqSlug = bb_rel_lucro_clean_slug($_GET['loja']);
} elseif (!empty($_POST['loja'])) {
	$__bb_reqSlug = bb_rel_lucro_clean_slug($_POST['loja']);
} elseif (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath, $mSlug)) {
	$__bb_reqSlug = bb_rel_lucro_clean_slug($mSlug[1]);
} elseif (!empty($__bb_refPath) && preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_refPath, $mSlug2)) {
	$__bb_reqSlug = bb_rel_lucro_clean_slug($mSlug2[1]);
}

$__bb_activeTenantSession = (session_status() === PHP_SESSION_ACTIVE && strpos(session_name(), 'BB_TENANT_') === 0);

if ($__bb_reqSlug === '' && $__bb_activeTenantSession && !empty($_SESSION['tenant_slug'])) {
	$__bb_reqSlug = bb_rel_lucro_clean_slug($_SESSION['tenant_slug']);
}

$__bb_cookieTenantSessionName = '';
if ($__bb_reqSlug === '' && !$__bb_activeTenantSession && !empty($_COOKIE) && is_array($_COOKIE)) {
	$__bb_tenantCookies = [];
	foreach (array_keys($_COOKIE) as $__bb_cookieName) {
		if (strpos((string)$__bb_cookieName, 'BB_TENANT_') === 0) {
			$__bb_tenantCookies[] = (string)$__bb_cookieName;
		}
	}
	if (count($__bb_tenantCookies) === 1) {
		$__bb_cookieTenantSessionName = $__bb_tenantCookies[0];
	}
}

if ($__bb_reqSlug !== '' && empty($_GET['loja'])) {
	$_GET['loja'] = $__bb_reqSlug;
}

$__bb_tenantSessionName = 'BB_TENANT';
if ($__bb_reqSlug !== '') {
	$__bb_tenantSessionName = 'BB_TENANT_' . strtoupper(substr(sha1($__bb_reqSlug), 0, 12));
} elseif ($__bb_cookieTenantSessionName !== '') {
	$__bb_tenantSessionName = $__bb_cookieTenantSessionName;
}

$__bb_lojaByPath = (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath)
				|| (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_refPath);

$__bb_hasLoja = $__bb_lojaByPath || !empty($_GET['loja']) || !empty($_POST['loja']) || $__bb_activeTenantSession || ($__bb_cookieTenantSessionName !== '');
$__bb_isSistema = (strpos($__bb_reqPath, '/sistema') === 0) || (strpos($__bb_refPath, '/sistema') === 0);
$__bb_forceMaster = ($__bb_isSistema && !$__bb_lojaByPath && empty($_GET['loja']) && empty($_POST['loja']) && !$__bb_activeTenantSession && $__bb_cookieTenantSessionName === '');

$__bb_desiredSession = $__bb_forceMaster ? 'BB_MASTER' : ($__bb_hasLoja ? $__bb_tenantSessionName : 'BB_SITE');

if (session_status() === PHP_SESSION_ACTIVE) {
	if (session_name() !== $__bb_desiredSession) {
		session_write_close();
		@session_name($__bb_desiredSession);
		if (!empty($_COOKIE[$__bb_desiredSession])) {
			@session_id((string)$_COOKIE[$__bb_desiredSession]);
		}
		@session_start();
	}
} else {
	@session_name($__bb_desiredSession);
	if (!empty($_COOKIE[$__bb_desiredSession])) {
		@session_id((string)$_COOKIE[$__bb_desiredSession]);
	}
	@session_start();
}

if ($__bb_reqSlug === '' && !empty($_SESSION['tenant_slug'])) {
	$__bb_reqSlug = bb_rel_lucro_clean_slug($_SESSION['tenant_slug']);
	if ($__bb_reqSlug !== '' && empty($_GET['loja'])) {
		$_GET['loja'] = $__bb_reqSlug;
	}
}

require_once __DIR__ . '/../verificar.php';
require_once __DIR__ . '/../../conexao.php';

if (!function_exists('bb_rel_lucro_data_valida_class')) {
	function bb_rel_lucro_data_valida_class($valor)
	{
		return is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor);
	}
}

$dataInicial = bb_rel_lucro_data_valida_class($_POST['dataInicial'] ?? null) ? (string)$_POST['dataInicial'] : date('Y-m-d');
$dataFinal = bb_rel_lucro_data_valida_class($_POST['dataFinal'] ?? null) ? (string)$_POST['dataFinal'] : $dataInicial;

if ($dataFinal < $dataInicial) {
	$tmp = $dataInicial;
	$dataInicial = $dataFinal;
	$dataFinal = $tmp;
}

$tipo_rel = isset($_POST['tipo_rel']) ? (string)$_POST['tipo_rel'] : ($tipo_rel ?? 'PDF');

require_once __DIR__ . '/data_formatada.php';
ob_start();
include __DIR__ . '/rel_lucro.php';
$html = ob_get_clean();

if($tipo_rel != 'PDF'){
	echo $html;
	exit();
}

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
	'lucro.pdf',
	array("Attachment" => false)
);
?>
