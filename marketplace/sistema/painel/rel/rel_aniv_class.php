<?php
// ==========================================================
// RELATÓRIO DE ANIVERSARIANTES - CLASSE PDF
// Arquivo: /public_html/sistema/painel/rel/rel_aniv_class.php
//
// Correção direta no padrão do sistema:
// - Resolve sessão MASTER x TENANT antes do verificar.php
// - Funciona em /loja/{slug}/... e também no link master
// - Mantém a lógica original do relatório e do DOMPDF
// ==========================================================

// ===== Sessão isolada por contexto (MASTER x TENANT) =====
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
} elseif (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath, $mSlug)) {
    $__bb_reqSlug = bb_sess_clean_slug($mSlug[1]);
} elseif (!empty($__bb_refPath) && preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_refPath, $mSlug2)) {
    $__bb_reqSlug = bb_sess_clean_slug($mSlug2[1]);
}

$__bb_cookieTenantSessionName = '';
if ($__bb_reqSlug === '' && !empty($_COOKIE) && is_array($_COOKIE)) {
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

$__bb_hasLoja = $__bb_lojaByPath || !empty($_GET['loja']) || !empty($_POST['loja']) || ($__bb_cookieTenantSessionName !== '');
$__bb_isSistema = (strpos($__bb_reqPath, '/sistema') === 0) || (strpos($__bb_refPath, '/sistema') === 0);
$__bb_forceMaster = ($__bb_isSistema && !$__bb_lojaByPath && empty($_GET['loja']) && empty($_POST['loja']) && $__bb_cookieTenantSessionName === '');
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
    $__bb_reqSlug = bb_sess_clean_slug($_SESSION['tenant_slug']);
    if ($__bb_reqSlug !== '' && empty($_GET['loja'])) {
        $_GET['loja'] = $__bb_reqSlug;
    }
}

require_once __DIR__ . '/../verificar.php';
require_once __DIR__ . '/../../conexao.php';

$data_hoje = date('Y-m-d');
$dataInicial = (!empty($_POST['dataInicial']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_POST['dataInicial'])) ? (string)$_POST['dataInicial'] : $data_hoje;
$dataFinal   = (!empty($_POST['dataFinal']) && preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$_POST['dataFinal'])) ? (string)$_POST['dataFinal'] : $data_hoje;

$token_rel = 'A5030';
require_once __DIR__ . '/data_formatada.php';

ob_start();
include __DIR__ . '/rel_aniv.php';
$html = ob_get_clean();

// CARREGAR DOMPDF
require_once __DIR__ . '/../../dompdf/autoload.inc.php';

use Dompdf\Dompdf;
use Dompdf\Options;

header('Content-Transfer-Encoding: binary');
header('Content-Type: image/png');

// INICIALIZAR A CLASSE DO DOMPDF
$options = new Options();
$options->set('isRemoteEnabled', true);
$pdf = new DOMPDF($options);

// Definir o tamanho do papel e orientação da página
$pdf->set_paper('A4', 'portrait');

// CARREGAR O CONTEÚDO HTML
$pdf->load_html($html);

// RENDERIZAR O PDF
$pdf->render();

// NOMEAR O PDF GERADO
$pdf->stream(
    'aniversariantes.pdf',
    array('Attachment' => false)
);
?>
