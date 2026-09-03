<?php
// ===== Sessão isolada por contexto (MASTER x TENANT) =====
// Mantém o arquivo físico em /sistema/... e herda o contexto da loja quando vier de /loja/<slug>/.
$__bb_reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$__bb_refPath = '';
if (!empty($_SERVER['HTTP_REFERER'])) {
    $__bb_refPath = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: '';
}

if (!function_exists('bb_sess_clean_slug')) {
    function bb_sess_clean_slug($v) {
        $v = strtolower(trim((string)$v));
        return preg_replace('/[^a-z0-9_-]/', '', $v);
    }
}

$__bb_reqSlug = '';
if (!empty($_GET['loja'])) {
    $__bb_reqSlug = bb_sess_clean_slug($_GET['loja']);
} elseif (!empty($_POST['loja'])) {
    $__bb_reqSlug = bb_sess_clean_slug($_POST['loja']);
} elseif (!empty($_REQUEST['loja'])) {
    $__bb_reqSlug = bb_sess_clean_slug($_REQUEST['loja']);
}

if ($__bb_reqSlug === '') {
    if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath, $mSlug)) {
        $__bb_reqSlug = bb_sess_clean_slug($mSlug[1]);
    } elseif (!empty($__bb_refPath) && preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_refPath, $mSlug2)) {
        $__bb_reqSlug = bb_sess_clean_slug($mSlug2[1]);
    }
}

$__bb_lojaByPath = (bool) preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath)
                || (bool) preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_refPath);

$__bb_hasLoja = $__bb_lojaByPath || ($__bb_reqSlug !== '');
$__bb_isSistema = (strpos($__bb_reqPath, '/sistema') === 0) || (strpos($__bb_refPath, '/sistema') === 0);
$__bb_forceMaster = ($__bb_isSistema && !$__bb_lojaByPath && $__bb_reqSlug === '');

$__bb_tenantSessionName = 'BB_TENANT';
if ($__bb_reqSlug !== '') {
    $__bb_tenantSessionName = 'BB_TENANT_' . strtoupper(substr(sha1($__bb_reqSlug), 0, 12));
}

$__bb_desiredSession = $__bb_forceMaster ? 'BB_MASTER' : ($__bb_hasLoja ? $__bb_tenantSessionName : 'BB_SITE');

if (session_status() === PHP_SESSION_ACTIVE) {
    if (session_name() !== $__bb_desiredSession) {
        session_write_close();
        @session_name($__bb_desiredSession);
        if (!empty($_COOKIE[$__bb_desiredSession])) {
            @session_id((string) $_COOKIE[$__bb_desiredSession]);
        }
        @session_start();
    }
} else {
    @session_name($__bb_desiredSession);
    if (!empty($_COOKIE[$__bb_desiredSession])) {
        @session_id((string) $_COOKIE[$__bb_desiredSession]);
    }
    @session_start();
}

$id_usu = isset($_SESSION['id']) ? (int) $_SESSION['id'] : 0;
$nivel_usu = isset($_SESSION['nivel']) ? (string) $_SESSION['nivel'] : '';

require_once __DIR__ . '/../../conexao.php';

if (!function_exists('bb_normalize_report_date')) {
    function bb_normalize_report_date($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
            $parts = explode('/', $value);
            return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
        }
        return '';
    }
}

$dataInicial = bb_normalize_report_date($_POST['dataInicial'] ?? '');
$dataFinal   = bb_normalize_report_date($_POST['dataFinal'] ?? '');

if ($dataInicial === '' && !empty($data_hoje_sistema)) {
    $dataInicial = (string) $data_hoje_sistema;
}
if ($dataFinal === '' && !empty($data_hoje_sistema)) {
    $dataFinal = (string) $data_hoje_sistema;
}
if ($dataInicial === '') {
    $dataInicial = date('Y-m-d');
}
if ($dataFinal === '') {
    $dataFinal = date('Y-m-d');
}
if ($dataFinal < $dataInicial) {
    $tmp = $dataInicial;
    $dataInicial = $dataFinal;
    $dataFinal = $tmp;
}

// Carrega o HTML do layout (cobrancas.php) localmente.
// Isso preserva o mesmo contexto de sessão + banco individual da loja.
$__bk_di = $_GET['dataInicial'] ?? null;
$__bk_df = $_GET['dataFinal'] ?? null;
$__bk_iu = $_GET['id_usu'] ?? null;
$__bk_nu = $_GET['nivel_usu'] ?? null;
$__bk_lj = $_GET['loja'] ?? null;

$_GET['dataInicial'] = $dataInicial;
$_GET['dataFinal']   = $dataFinal;
$_GET['id_usu']      = $id_usu;
$_GET['nivel_usu']   = $nivel_usu;
if ($__bb_reqSlug !== '') {
    $_GET['loja'] = $__bb_reqSlug;
}

ob_start();
include __DIR__ . '/cobrancas.php';
$html = ob_get_clean();

if ($__bk_di === null) { unset($_GET['dataInicial']); } else { $_GET['dataInicial'] = $__bk_di; }
if ($__bk_df === null) { unset($_GET['dataFinal']); } else { $_GET['dataFinal'] = $__bk_df; }
if ($__bk_iu === null) { unset($_GET['id_usu']); } else { $_GET['id_usu'] = $__bk_iu; }
if ($__bk_nu === null) { unset($_GET['nivel_usu']); } else { $_GET['nivel_usu'] = $__bk_nu; }
if ($__bk_lj === null) { unset($_GET['loja']); } else { $_GET['loja'] = $__bk_lj; }

// CARREGAR DOMPDF
require_once __DIR__ . '/../../dompdf/autoload.inc.php';
use Dompdf\Dompdf;
use Dompdf\Options;

header("Content-Transfer-Encoding: binary");
header("Content-Type: image/png");

// INICIALIZAR A CLASSE DO DOMPDF
$options = new Options();
$options->set('isRemoteEnabled', true);
$pdf = new Dompdf($options);

// Definir o tamanho do papel e orientação da página
$pdf->set_paper('A4', 'portrait');

// CARREGAR O CONTEÚDO HTML
$pdf->load_html($html);

// RENDERIZAR O PDF
$pdf->render();

// EXIBIR O PDF
$pdf->stream(
    'cobrancas.pdf',
    array("Attachment" => false)
);
?>