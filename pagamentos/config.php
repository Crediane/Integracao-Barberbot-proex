<?php
/**
 * Arquivo: /public_html/pagamentos/config.php
 *
 * Contexto único do módulo de pagamentos da barbearia.
 * O mapeamento de /loja/{slug}/, ?loja={slug}, subdomínio ou domínio próprio
 * permanece centralizado em /sistema/conexao.php.
 */

if (!function_exists('bb_pagamentos_limpar_slug')) {
    function bb_pagamentos_limpar_slug($valor): string
    {
        $valor = strtolower(trim((string)$valor));
        return (string)preg_replace('/[^a-z0-9_-]/', '', $valor);
    }
}

$__bb_reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$__bb_refPath = '';
$__bb_slug_path = '';
$__bb_slug_get = '';
$__bb_slug_post = '';

if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath, $__bb_match_loja)) {
    $__bb_slug_path = bb_pagamentos_limpar_slug($__bb_match_loja[1]);
}
if (isset($_GET['loja'])) {
    $__bb_slug_get = bb_pagamentos_limpar_slug($_GET['loja']);
}
if (isset($_POST['loja'])) {
    $__bb_slug_post = bb_pagamentos_limpar_slug($_POST['loja']);
}

$__bb_slugs_explicitos = array_values(array_unique(array_filter([
    $__bb_slug_path,
    $__bb_slug_get,
    $__bb_slug_post,
], static function ($valor) {
    return $valor !== '';
})));

if (count($__bb_slugs_explicitos) > 1) {
    http_response_code(400);
    exit('Contexto de loja inválido: foram informados slugs diferentes.');
}

$__bb_slug_explicito = $__bb_slugs_explicitos[0] ?? '';

require_once __DIR__ . '/../sistema/conexao.php';

$bb_pagamentos_slug = bb_pagamentos_limpar_slug($_SESSION['tenant_slug'] ?? '');
$bb_pagamentos_tenant_db = trim((string)($_SESSION['tenant_db'] ?? ''));
$bb_pagamentos_session_name = (string)session_name();
$bb_pagamentos_banco_atual = '';

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $bb_pagamentos_banco_atual = trim((string)$pdo->query('SELECT DATABASE()')->fetchColumn());
    } catch (Throwable $e) {
        $bb_pagamentos_banco_atual = '';
    }
}

if (
    strpos($bb_pagamentos_session_name, 'BB_TENANT') !== 0
    || $bb_pagamentos_slug === ''
    || $bb_pagamentos_tenant_db === ''
    || !isset($pdo)
    || !($pdo instanceof PDO)
    || $bb_pagamentos_banco_atual === ''
    || strcasecmp($bb_pagamentos_banco_atual, $bb_pagamentos_tenant_db) !== 0
    || ($__bb_slug_explicito !== '' && $__bb_slug_explicito !== $bb_pagamentos_slug)
) {
    http_response_code(403);
    exit('Acesso negado: o banco individual da barbearia não foi confirmado.');
}

// Compatibilidade com os arquivos existentes do módulo, sempre a partir do contexto validado.
$__bb_reqSlug = $bb_pagamentos_slug;
$__bb_loja = $bb_pagamentos_slug;
$bb_loja = $bb_pagamentos_slug;
$loja = $bb_pagamentos_slug;
$__bb_is_path_tenant = ($__bb_slug_path !== '');
$_GET['loja'] = $bb_pagamentos_slug;
$_SESSION['loja'] = $bb_pagamentos_slug;

require_once __DIR__ . '/tokens.php';

$modoProducao = true; // Defina isso como true para usar credenciais de produção e false para usar credenciais de teste

if ($modoProducao) {
    $NOME_SITE = $nome_sistema; // Nome do seu site em produção
    $TOKEN_MERCADO_PAGO = $access_token; // Token do Mercado Pago em produção
    $TOKEN_MERCADO_PAGO_PUBLICO = $public_key; // Token público do Mercado Pago em produção (PUBLIC KEY)

} else {
    $NOME_SITE = "Meu pix (Modo Teste)"; // Nome do seu site em modo de teste
    $TOKEN_MERCADO_PAGO = "APP_USR-7645692252055791-101021-ba91ccf6cd290bc3115e3270a30edb1e-131939455"; // Token do Mercado Pago em teste
    $TOKEN_MERCADO_PAGO_PUBLICO = "TEST-da159e2a-4731-4fe4-abf0-5d890ab6c0e0"; // Token público do Mercado Pago em teste

}


$DESCRICAO_PAGAMENTO = "Pagamento Serviço"; // OBRIGATÓRIO: DESCRIÇÃO PAGAMENTO O PAGAMENTO

$MSG_APOS_PAGAMENTO = "Recebemos seu pagamento.";

$URL_REDIRECIONAR = "Sim"; // LINK PARA DIRECIONAR 6 SEGUNDOS APÓS RECEBER O PAGAMENTO (Coloque Sim caso queira que ele redirecione para o comprovante)

$PAGAMENTO_MINIMO = "0"; // NÃO OBRIGATORIO: VALOR PARA PAGAMENTO MINIMO. EXEMPLO: 10,00 / 20,40

$EMAIL_NOTIFICACAO = ""; // OBRIGATÓRIO. SE NÃO FOR CONFIGURADO O CLIENTE DEVERÁ INFORMAR.

$CPF_PADRAO = ""; // É OBRIGATÓRIO O CPF. SE NÃO FOI CONFIGURADO AQUI O CLIENTE DEVERÁ INFORMAR. 

// URL de notificação (webhook)
// Mantém o padrão do sistema, mas agora aponta para o webhook real.
// Regra:
// - usa a base pública derivada de $url_sistema
// - remove /sistema do final quando existir
// - anexa ?loja=<slug> no contexto tenant
if (!function_exists('bb_add_loja_param_cfg')) {
function bb_add_loja_param_cfg(string $url, string $loja): string {
    if ($loja === '') return $url;
    if (stripos($url, '/loja/') !== false) return $url;
    if (stripos($url, 'loja=') !== false) return $url;
    return $url . (strpos($url, '?') !== false ? '&' : '?') . 'loja=' . rawurlencode($loja);
}
}

if (!function_exists('bb_build_url_notificacao_cfg')) {
function bb_build_url_notificacao_cfg(string $urlSistema, string $loja): string {
    $urlSistema = trim($urlSistema);

    if ($urlSistema === '') {
        $proto = 'http';
        if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
            $proto = (string)$_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https' ? 'https' : 'http';
        } elseif (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $proto = 'https';
        }

        $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
        if ($host !== '') {
            $urlSistema = $proto . '://' . $host;
        }
    }

    $urlSistema = rtrim($urlSistema, '/');
    $urlBase = preg_replace('~/sistema$~i', '', $urlSistema);
    $urlBase = rtrim((string)$urlBase, '/');

    $urlWebhook = $urlBase . '/pagamentos/webhook.php';
    return bb_add_loja_param_cfg($urlWebhook, $loja);
}
}

$URL_NOTIFICACAO = bb_build_url_notificacao_cfg((string)($url_sistema ?? ''), $__bb_loja);

$VALOR_PADRAO = "5,00"; // EX: 20,00

$ATIVAR_PIX = "1";

$ATIVAR_BOLETO = "0";

$ATIVAR_CARTAO_CREDITO = "1";

$ATIVAR_CARTAO_DEBIDO = "0";
