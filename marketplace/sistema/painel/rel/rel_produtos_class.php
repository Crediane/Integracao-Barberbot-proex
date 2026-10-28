<?php
if (!defined('BB_RELATORIO_TENANT_BOOTSTRAP')) {
    define('BB_RELATORIO_TENANT_BOOTSTRAP', 1);

    if (!function_exists('bb_rel_clean_slug')) {
        function bb_rel_clean_slug($valor)
        {
            $valor = strtolower(trim((string)$valor));
            return preg_replace('/[^a-z0-9_-]/', '', $valor);
        }
    }

    if (!function_exists('bb_rel_extract_slug_from_path')) {
        function bb_rel_extract_slug_from_path($path)
        {
            $path = (string)$path;
            if ($path !== '' && preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $path, $match)) {
                return bb_rel_clean_slug($match[1]);
            }
            return '';
        }
    }

    if (!function_exists('bb_rel_pick_tenant_session_name')) {
        function bb_rel_pick_tenant_session_name($slug)
        {
            $slug = bb_rel_clean_slug($slug);
            if ($slug === '') {
                return 'BB_TENANT';
            }
            return 'BB_TENANT_' . strtoupper(substr(sha1($slug), 0, 12));
        }
    }

    $__bb_reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $__bb_refPath = '';
    if (!empty($_SERVER['HTTP_REFERER'])) {
        $__bb_refPath = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: '';
    }

    $__bb_reqSlug = '';
    if (!empty($_GET['loja'])) {
        $__bb_reqSlug = bb_rel_clean_slug($_GET['loja']);
    } elseif (!empty($_POST['loja'])) {
        $__bb_reqSlug = bb_rel_clean_slug($_POST['loja']);
    }

    if ($__bb_reqSlug === '') {
        $__bb_reqSlug = bb_rel_extract_slug_from_path($__bb_reqPath);
    }

    if ($__bb_reqSlug === '') {
        $__bb_reqSlug = bb_rel_extract_slug_from_path($__bb_refPath);
    }

    $__bb_lojaByPath = (bb_rel_extract_slug_from_path($__bb_reqPath) !== '') || (bb_rel_extract_slug_from_path($__bb_refPath) !== '');
    $__bb_hasLoja = $__bb_lojaByPath || !empty($_GET['loja']) || !empty($_POST['loja']) || $__bb_reqSlug !== '';
    $__bb_isSistema = (strpos($__bb_reqPath, '/sistema') === 0) || (strpos($__bb_refPath, '/sistema') === 0);
    $__bb_forceMaster = ($__bb_isSistema && !$__bb_hasLoja);

    $__bb_desiredSession = $__bb_forceMaster ? 'BB_MASTER' : ($__bb_hasLoja ? bb_rel_pick_tenant_session_name($__bb_reqSlug) : 'BB_SITE');

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
        $__bb_reqSlug = bb_rel_clean_slug($_SESSION['tenant_slug']);
    }

    if ($__bb_reqSlug !== '') {
        $_GET['loja'] = $__bb_reqSlug;
        $_POST['loja'] = $_POST['loja'] ?? $__bb_reqSlug;
        $_SESSION['tenant_slug'] = $_SESSION['tenant_slug'] ?? $__bb_reqSlug;
    }
}

require_once __DIR__ . '/../verificar.php';
require_once __DIR__ . '/../../conexao.php';
$token_rel = 'A5030';
require_once __DIR__ . '/data_formatada.php';
ob_start();
include __DIR__ . '/rel_produtos.php';
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
    'rel_produtos.pdf',
    array("Attachment" => false)
);
?>
