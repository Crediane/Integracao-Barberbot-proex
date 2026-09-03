<?php  
if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

if (!function_exists('bb_rel_resolver_slug_anotacoes_class')) {
    function bb_rel_resolver_slug_anotacoes_class(): string
    {
        $fontes = [];

        foreach (['loja', 'slug'] as $chave) {
            if (isset($_GET[$chave])) {
                $fontes[] = $_GET[$chave];
            }
            if (isset($_POST[$chave])) {
                $fontes[] = $_POST[$chave];
            }
            if (isset($_REQUEST[$chave])) {
                $fontes[] = $_REQUEST[$chave];
            }
        }

        foreach (['tenant_slug', 'loja_slug', 'slug', 'loja'] as $chave) {
            if (isset($_SESSION[$chave])) {
                $fontes[] = $_SESSION[$chave];
            }
        }

        foreach (['BB_TENANT_SLUG', 'tenant_slug', 'loja_slug', 'slug', 'loja'] as $chave) {
            if (isset($_COOKIE[$chave])) {
                $fontes[] = $_COOKIE[$chave];
            }
        }

        foreach (['REQUEST_URI', 'PHP_SELF', 'SCRIPT_NAME', 'HTTP_REFERER'] as $chave) {
            if (!empty($_SERVER[$chave]) && preg_match('~(?:^|/)loja/([^/?#]+)~i', (string)$_SERVER[$chave], $m)) {
                $fontes[] = $m[1];
            }
        }

        foreach ($fontes as $fonte) {
            $slug = trim((string)$fonte);
            if ($slug === '') {
                continue;
            }

            if (preg_match('/^[a-zA-Z0-9._-]+$/', $slug)) {
                return $slug;
            }
        }

        return '';
    }
}

$bbLojaSlug = bb_rel_resolver_slug_anotacoes_class();
if ($bbLojaSlug !== '') {
    $_GET['loja'] = $bbLojaSlug;
    $_REQUEST['loja'] = $bbLojaSlug;
    $_SESSION['tenant_slug'] = $bbLojaSlug;
    $_SESSION['loja_slug'] = $bbLojaSlug;
    $_SESSION['slug'] = $bbLojaSlug;
    $_SESSION['loja'] = $bbLojaSlug;

    if (!headers_sent()) {
        @setcookie('BB_TENANT_SLUG', $bbLojaSlug, time() + 86400, '/');
        @setcookie('loja', $bbLojaSlug, time() + 86400, '/');
    }
}

require_once __DIR__ . '/../verificar.php';
require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/data_formatada.php';

// Recebe o ID via POST ou GET (flexível para ambos)
$id = 0;
if (isset($_POST['id']) && is_numeric($_POST['id'])) {
    $id = (int) $_POST['id'];
} elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id = (int) $_GET['id'];
}

if ($id > 0 && !isset($_GET['id'])) {
    $_GET['id'] = $id;
}
if ($id > 0 && !isset($_REQUEST['id'])) {
    $_REQUEST['id'] = $id;
}

$token_rel = 'A5030'; // ajuste se quiser autenticação por token (igual rel_produtos)

// Usa buffer de saída para capturar o HTML do relatório
ob_start();
include __DIR__ . '/anotacoes.php';
$html = ob_get_clean();

// Carrega DomPDF
require_once __DIR__ . '/../../dompdf/autoload.inc.php';
use Dompdf\Dompdf;
use Dompdf\Options;

header("Content-Transfer-Encoding: binary");
header("Content-Type: application/pdf");

$options = new Options();
$options->set('isRemoteEnabled', true);

$pdf = new Dompdf($options);
$pdf->set_paper('A4', 'portrait');
$pdf->load_html($html);
$pdf->render();

$pdf->stream(
    'anotacoes.pdf',
    array("Attachment" => false)
);
?>
