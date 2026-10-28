<?php
// ======================================================================
// CONTEXTO CENTRALIZADO (MASTER x TENANT)
// - A escolha de sessão, slug, subdomínio, domínio próprio e banco
//   pertence exclusivamente ao /sistema/conexao.php.
// - Este header não usa HTTP_REFERER e não troca sessão ou banco.
// - Rotas TENANT e MASTER/Admin continuam com validação obrigatória.
// - Páginas públicas, como /marketplace.php/, não são interrompidas
//   quando a conexão administrativa opcional de layout não estiver ativa.
// ======================================================================
$__bb_reqPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

// Descobre automaticamente a pasta pública do projeto no XAMPP.
// Ex.: C:/xampp/htdocs/marketplace => /marketplace.
$__bb_documentRoot = realpath((string)($_SERVER['DOCUMENT_ROOT'] ?? '')) ?: '';
$__bb_projectRoot = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
$bb_web_base = '';
if ($__bb_documentRoot !== '') {
    $bb_documentRootNormalizado = str_replace('\\', '/', rtrim($__bb_documentRoot, '/\\'));
    $bb_projectRootNormalizado = str_replace('\\', '/', rtrim($__bb_projectRoot, '/\\'));
    if (stripos($bb_projectRootNormalizado, $bb_documentRootNormalizado) === 0) {
        $bb_web_base = substr($bb_projectRootNormalizado, strlen($bb_documentRootNormalizado));
        $bb_web_base = '/' . trim((string)$bb_web_base, '/');
        if ($bb_web_base === '/') $bb_web_base = '';
    }
}

$__bb_routePath = $__bb_reqPath;
if ($bb_web_base !== '' && stripos($__bb_routePath, $bb_web_base) === 0) {
    $__bb_routePath = substr($__bb_routePath, strlen($bb_web_base));
    if ($__bb_routePath === '') $__bb_routePath = '/';
}
$__bb_isSystemRoute = (bool)preg_match('~^/sistema(?:/|$)~i', $__bb_routePath);

$baseConn = __DIR__ . '/../sistema/conexao.php';
if (!is_file($baseConn)) {
    http_response_code(500);
    echo 'Configuração de conexão não encontrada.';
    exit;
}

require_once $baseConn;

if (!function_exists('bb_sess_clean_slug')) {
    function bb_sess_clean_slug($valor) {
        $valor = strtolower(trim((string)$valor));
        return preg_replace('/[^a-z0-9_-]/', '', $valor);
    }
}

$__bb_sessionName = (string)session_name();
$__bb_isTenantContext = (strpos($__bb_sessionName, 'BB_TENANT') === 0);
$__bb_isMasterContext = ($__bb_sessionName === 'BB_MASTER');
$__bb_isMasterAdminContext = ($__bb_isMasterContext && $__bb_isSystemRoute);
$__bb_forceMaster = $__bb_isMasterAdminContext;

$__bb_reqSlug = '';
if (!empty($_GET['loja'])) {
    $__bb_reqSlug = bb_sess_clean_slug($_GET['loja']);
} elseif (!empty($_POST['loja'])) {
    $__bb_reqSlug = bb_sess_clean_slug($_POST['loja']);
} elseif (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_routePath, $__bb_slugMatch)) {
    $__bb_reqSlug = bb_sess_clean_slug($__bb_slugMatch[1]);
}

// O slug efetivo vem da sessão validada pelo conexao.php.
// Isso também contempla subdomínio e domínio próprio.
$__tenant_slug = $__bb_isTenantContext
    ? bb_sess_clean_slug($_SESSION['tenant_slug'] ?? '')
    : '';

$__bb_hasPdo = isset($pdo) && ($pdo instanceof PDO);
$__bb_connectedDb = '';
if ($__bb_hasPdo) {
    try {
        $__bb_connectedDb = trim((string)$pdo->query('SELECT DATABASE()')->fetchColumn());
    } catch (Throwable $e) {
        $__bb_connectedDb = '';
    }
}

if ($__bb_isTenantContext) {
    $__bb_expectedTenantDb = trim((string)($_SESSION['tenant_db'] ?? ''));

    if (
        !$__bb_hasPdo
        || $__tenant_slug === ''
        || $__bb_expectedTenantDb === ''
        || $__bb_connectedDb === ''
        || strcasecmp($__bb_connectedDb, $__bb_expectedTenantDb) !== 0
    ) {
        http_response_code(500);
        echo 'Falha de isolamento do banco individual da barbearia.';
        exit;
    }
} elseif ($__bb_isMasterAdminContext) {
    // Somente o Link Master/Admin (/sistema/...) exige conexão global
    // administrativa obrigatória. A página pública /marketplace.php/
    // não é uma rota interna do painel Master.
    $__bb_marketplaceConfigFile = __DIR__ . '/../config/marketplace.php';
    $__bb_marketplaceConfig = is_file($__bb_marketplaceConfigFile)
        ? require $__bb_marketplaceConfigFile
        : [];

    $__bb_expectedGlobalDb = '';
    if (
        is_array($__bb_marketplaceConfig)
        && !empty($__bb_marketplaceConfig['auth_master'])
        && is_array($__bb_marketplaceConfig['auth_master'])
    ) {
        $__bb_expectedGlobalDb = preg_replace(
            '/[^a-z0-9_]/i',
            '',
            (string)($__bb_marketplaceConfig['auth_master']['db'] ?? '')
        );
    }

    if (
        !$__bb_hasPdo
        || $__bb_expectedGlobalDb === ''
        || $__bb_connectedDb === ''
        || strcasecmp($__bb_connectedDb, $__bb_expectedGlobalDb) !== 0
    ) {
        http_response_code(500);
        echo 'Falha de isolamento do banco Global/Administrativo.';
        exit;
    }
}

/*
  ✅ CONFIG DO CONTEXTO ATUAL (MASTER x TENANT)
  - MASTER: carrega Banco de dados jctecbr_master, tabela config, quando estiver fora de /loja/{slug}/.
  - TENANT: carrega a tabela config do banco individual da loja, quando estiver em /loja/{slug}/.
  - Mantém a conexão existente do sistema e só normaliza as variáveis públicas usadas no site.
*/
if (!function_exists('bb_carregar_config_contexto')) {
    function bb_carregar_config_contexto() {
        global $pdo,
               $nome_sistema,
               $email_sistema,
               $whatsapp_sistema,
               $telefone_sistema,
               $telefone_fixo_sistema,
               $tel_whatsapp,
               $endereco_sistema,
               $logo_sistema,
               $icone_sistema,
               $texto_rodape,
               $cnpj_sistema;

        if (!isset($pdo) || !($pdo instanceof PDO)) {
            return;
        }

        try {
            $stConfig = $pdo->query("SELECT * FROM config ORDER BY id DESC LIMIT 1");
            $cfg = $stConfig ? $stConfig->fetch(PDO::FETCH_ASSOC) : false;
            if (!$cfg || !is_array($cfg)) {
                return;
            }

            if (array_key_exists('nome', $cfg) && trim((string)$cfg['nome']) !== '') {
                $nome_sistema = trim((string)$cfg['nome']);
            }

            if (array_key_exists('email', $cfg) && trim((string)$cfg['email']) !== '') {
                $email_sistema = trim((string)$cfg['email']);
            }

            if (array_key_exists('telefone_whatsapp', $cfg) && trim((string)$cfg['telefone_whatsapp']) !== '') {
                $whatsapp_sistema = trim((string)$cfg['telefone_whatsapp']);
            } elseif (array_key_exists('whatsapp', $cfg) && trim((string)$cfg['whatsapp']) !== '') {
                $whatsapp_sistema = trim((string)$cfg['whatsapp']);
            }

            if (array_key_exists('telefone_fixo', $cfg) && trim((string)$cfg['telefone_fixo']) !== '') {
                $telefone_fixo_sistema = trim((string)$cfg['telefone_fixo']);
                $telefone_sistema = trim((string)$cfg['telefone_fixo']);
            }

            if (array_key_exists('endereco', $cfg) && trim((string)$cfg['endereco']) !== '') {
                $endereco_sistema = trim((string)$cfg['endereco']);
            }

            if (array_key_exists('logo', $cfg) && trim((string)$cfg['logo']) !== '') {
                $logo_sistema = trim((string)$cfg['logo']);
            }

            if (array_key_exists('icone', $cfg) && trim((string)$cfg['icone']) !== '') {
                $icone_sistema = trim((string)$cfg['icone']);
            }

            if (array_key_exists('texto_rodape', $cfg) && trim((string)$cfg['texto_rodape']) !== '') {
                $texto_rodape = trim((string)$cfg['texto_rodape']);
            }

            if (array_key_exists('cnpj', $cfg) && trim((string)$cfg['cnpj']) !== '') {
                $cnpj_sistema = trim((string)$cfg['cnpj']);
            }

            $bbZapNumero = preg_replace('/\D+/', '', (string)($whatsapp_sistema ?? ''));
            if ($bbZapNumero !== '') {
                if (strlen($bbZapNumero) === 10 || strlen($bbZapNumero) === 11) {
                    $bbZapNumero = '55' . $bbZapNumero;
                }
                $tel_whatsapp = $bbZapNumero;
            }
        } catch (Throwable $e) {
            // Mantém os dados já carregados pelo sistema/conexao.php.
        }
    }
}

bb_carregar_config_contexto();

// ✅ base URL absoluta
$bb_proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $xfp = strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']);
    if ($xfp === 'https' || $xfp === 'http') $bb_proto = $xfp;
}
$bb_host = (string)($_SERVER['HTTP_HOST'] ?? '');
$bb_origin = ($bb_host !== '') ? ($bb_proto . '://' . $bb_host) : '';
$bb_base = $bb_origin . $bb_web_base;

// ✅ caminhos de imagem
// Correção importante:
// - Em URLs como /marketplace.php/ ou /loja/{slug}/, caminho relativo "sistema/img/logo.png"
//   vira /marketplace.php/sistema/img/logo.png e quebra o logo.
// - Por isso usamos URL absoluta a partir da raiz do domínio, sem alterar a lógica do banco.
$logo_arquivo  = (!empty($logo_sistema) ? (string)$logo_sistema : "logo.png");
$icone_arquivo = (!empty($icone_sistema) ? (string)$icone_sistema : "favicon.ico");

if (!function_exists('bb_asset_url_marketplace')) {
    function bb_asset_url_marketplace($arquivo, $pasta_padrao = 'sistema/img') {
        global $bb_base;

        $arquivo = trim((string)$arquivo);
        $base = rtrim((string)$bb_base, '/');

        if ($arquivo === '') {
            $arquivo = 'sem-foto.png';
        }

        // URL externa já pronta.
        if (preg_match('~^https?://~i', $arquivo)) {
            return $arquivo;
        }

        // Caminho absoluto informado pelo sistema.
        if (strpos($arquivo, '/') === 0) {
            return $base . $arquivo;
        }

        // Caminho já veio com pasta.
        if (strpos($arquivo, '/') !== false) {
            return $base . '/' . ltrim($arquivo, '/');
        }

        // Padrão do sistema.
        return $base . '/' . trim($pasta_padrao, '/') . '/' . ltrim($arquivo, '/');
    }
}

if (!function_exists('bb_asset_fs_marketplace')) {
    function bb_asset_fs_marketplace($arquivo, $pasta_padrao = 'sistema/img') {
        $root = realpath(dirname(__DIR__)) ?: dirname(__DIR__);
        $root = rtrim(str_replace('\\', '/', (string)$root), '/');
        $arquivo = trim((string)$arquivo);

        if ($root === '' || $arquivo === '' || preg_match('~^https?://~i', $arquivo)) {
            return '';
        }

        if (strpos($arquivo, '/') === 0) {
            return $root . $arquivo;
        }

        if (strpos($arquivo, '/') !== false) {
            return $root . '/' . ltrim($arquivo, '/');
        }

        return $root . '/' . trim($pasta_padrao, '/') . '/' . ltrim($arquivo, '/');
    }
}

$caminho_logo  = bb_asset_url_marketplace($logo_arquivo, 'sistema/img');
$caminho_icone = bb_asset_url_marketplace($icone_arquivo, 'sistema/img');

// ✅ evita cache (troca imediata de logo/ícone)
$bb_logo_ver = time();
$bb_icone_ver = time();
$bb_logo_fs  = bb_asset_fs_marketplace($logo_arquivo, 'sistema/img');
$bb_icone_fs = bb_asset_fs_marketplace($icone_arquivo, 'sistema/img');

if ($bb_logo_fs !== '' && is_file($bb_logo_fs))  {
    $bb_logo_ver = (int)@filemtime($bb_logo_fs);
}

/*
  ✅ Ícone do marketplace sem 404
  - Se a tabela config apontar para favicon.ico, mas o arquivo não existir em /sistema/img/,
    o navegador gera 404.
  - Mantém a lógica original: primeiro usa o ícone configurado.
  - Se não existir fisicamente, tenta fallbacks reais do servidor.
  - Último fallback: usa o próprio logo que já existe e aparece no topo.
*/
if ($bb_icone_fs === '' || !is_file($bb_icone_fs)) {
    $bb_icone_candidatos = array(
        $icone_arquivo,
        '/sistema/img/' . basename((string)$icone_arquivo),
        '/sistema/img/favicon.png',
        '/sistema/img/favicon.ico',
        '/sistema/img/logo.png',
        '/images/favicon.png',
        '/images/favicon.ico',
        '/favicon.png',
        '/favicon.ico',
        $logo_arquivo,
        '/sistema/img/' . basename((string)$logo_arquivo),
        '/sistema/img/logo.png'
    );

    foreach ($bb_icone_candidatos as $bb_icone_candidato) {
        $bb_icone_candidato = trim((string)$bb_icone_candidato);
        if ($bb_icone_candidato === '' || preg_match('~^https?://~i', $bb_icone_candidato)) {
            continue;
        }

        $bb_icone_candidato_fs = bb_asset_fs_marketplace($bb_icone_candidato, 'sistema/img');

        if ($bb_icone_candidato_fs !== '' && is_file($bb_icone_candidato_fs)) {
            $bb_icone_fs = $bb_icone_candidato_fs;
            $caminho_icone = bb_asset_url_marketplace($bb_icone_candidato, 'sistema/img');
            break;
        }
    }
}

if (($bb_icone_fs === '' || !is_file($bb_icone_fs)) && $bb_logo_fs !== '' && is_file($bb_logo_fs)) {
    $bb_icone_fs = $bb_logo_fs;
    $caminho_icone = $caminho_logo;
}

if ($bb_icone_fs !== '' && is_file($bb_icone_fs)) {
    $bb_icone_ver = (int)@filemtime($bb_icone_fs);
}

$bb_icone_ext = strtolower((string)pathinfo((string)(parse_url($caminho_icone, PHP_URL_PATH) ?: $caminho_icone), PATHINFO_EXTENSION));
$bb_icone_tipo = 'image/x-icon';
if ($bb_icone_ext === 'png') {
    $bb_icone_tipo = 'image/png';
} elseif ($bb_icone_ext === 'jpg' || $bb_icone_ext === 'jpeg') {
    $bb_icone_tipo = 'image/jpeg';
} elseif ($bb_icone_ext === 'webp') {
    $bb_icone_tipo = 'image/webp';
}

// ✅ defaults para evitar warning
$nome_sistema = $nome_sistema ?? 'BarberBot';
$email_sistema = $email_sistema ?? '';
$whatsapp_sistema = $whatsapp_sistema ?? '';
$tel_whatsapp = $tel_whatsapp ?? '';

// ✅ links dos botões - MASTER x TENANT
// O contexto TENANT vem exclusivamente da sessão e do banco já confirmados.
// - /loja/{slug}/: mantém a rota dinâmica com slug.
// - subdomínio/domínio próprio: mantém o host atual, sem inventar /loja/{slug}/.
// - fora de TENANT: usa os links públicos e o Link Master.
$bb_base_url_limpa = rtrim((string)$bb_base, '/');
$bb_link_marketplace_publico = ($bb_base_url_limpa !== '')
    ? ($bb_base_url_limpa . '/marketplace.php/')
    : '/marketplace.php/';

$bb_slug_link_atual = $__bb_isTenantContext ? $__tenant_slug : '';
$bb_tenant_rota_com_slug = false;

if ($bb_slug_link_atual !== '') {
    $bb_tenant_rota_com_slug =
        (bool)preg_match('~^/loja/' . preg_quote($bb_slug_link_atual, '~') . '(?:/|$)~i', $__bb_routePath)
        || !empty($_GET['loja'])
        || !empty($_POST['loja']);
}

if ($bb_slug_link_atual !== '') {
    if ($bb_tenant_rota_com_slug) {
        $bb_loja_url = ($bb_base_url_limpa !== '' ? $bb_base_url_limpa : '')
            . '/loja/' . rawurlencode($bb_slug_link_atual);
    } else {
        // Tenant resolvido por subdomínio ou domínio próprio.
        $bb_loja_url = $bb_base_url_limpa;
    }

    $link_home = rtrim($bb_loja_url, '/') . '/';
    $link_cliente_estab = $bb_link_marketplace_publico;
    $link_barbeiro = rtrim($bb_loja_url, '/') . '/sistema/';
} else {
    $link_home = ($bb_base_url_limpa !== '') ? ($bb_base_url_limpa . '/') : '/';
    $link_cliente_estab = $bb_link_marketplace_publico;
    $link_barbeiro = ($bb_base_url_limpa !== '')
        ? ($bb_base_url_limpa . '/criar-barbearia.php')
        : '/criar-barbearia.php';
}


// ===== reCAPTCHA MASTER (config global) =====
// O reCAPTCHA acompanha o contexto já confirmado: tenant usa o banco individual; site público/Link Master usa o Global/Administrativo.
$bb_recaptcha_config_master = array();
try {
    if (isset($pdo) && ($pdo instanceof PDO)) {
        $bb_recaptcha_stmt = $pdo->query("SELECT * FROM config ORDER BY id DESC LIMIT 1");
        $bb_recaptcha_config_master = $bb_recaptcha_stmt ? ($bb_recaptcha_stmt->fetch(PDO::FETCH_ASSOC) ?: array()) : array();
    }
} catch (Throwable $e) {
    $bb_recaptcha_config_master = array();
}

$bb_recaptcha_tipo_master = strtolower(trim((string)($bb_recaptcha_config_master['recaptcha_tipo'] ?? 'v3')));
if ($bb_recaptcha_tipo_master !== 'v2' && $bb_recaptcha_tipo_master !== 'v3') {
    $bb_recaptcha_tipo_master = 'v3';
}

$bb_recaptcha_site_key_v2_master = trim((string)($bb_recaptcha_config_master['recaptcha_site_key_v2'] ?? ''));
$bb_recaptcha_secret_key_v2_master = trim((string)($bb_recaptcha_config_master['recaptcha_secret_key_v2'] ?? ''));
$bb_recaptcha_site_key_v3_master = trim((string)($bb_recaptcha_config_master['recaptcha_site_key_v3'] ?? ''));
$bb_recaptcha_secret_key_v3_master = trim((string)($bb_recaptcha_config_master['recaptcha_secret_key_v3'] ?? ''));

$bb_recaptcha_site_key_master = ($bb_recaptcha_tipo_master === 'v2') ? $bb_recaptcha_site_key_v2_master : $bb_recaptcha_site_key_v3_master;
$bb_recaptcha_secret_key_master = ($bb_recaptcha_tipo_master === 'v2') ? $bb_recaptcha_secret_key_v2_master : $bb_recaptcha_secret_key_v3_master;

// As chaves públicas do domínio de produção não aceitam localhost.
// No XAMPP, não carrega o widget para evitar o erro visual de domínio inválido.
$bb_host_recaptcha = strtolower(preg_replace('/:\\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
$bb_ambiente_local = in_array($bb_host_recaptcha, array('localhost', '127.0.0.1', '::1'), true)
    || substr($bb_host_recaptcha, -5) === '.test';
if ($bb_ambiente_local) {
    $bb_recaptcha_site_key_master = '';
    $bb_recaptcha_secret_key_master = '';
}

if (!defined('RECAPTCHA_TIPO')) define('RECAPTCHA_TIPO', $bb_recaptcha_tipo_master);
if (!defined('RECAPTCHA_SITE_KEY')) define('RECAPTCHA_SITE_KEY', $bb_recaptcha_site_key_master);
if (!defined('RECAPTCHA_SECRET_KEY')) define('RECAPTCHA_SECRET_KEY', $bb_recaptcha_secret_key_master);
if (!defined('RECAPTCHA_SITE_KEY_V2')) define('RECAPTCHA_SITE_KEY_V2', $bb_recaptcha_site_key_v2_master);
if (!defined('RECAPTCHA_SECRET_KEY_V2')) define('RECAPTCHA_SECRET_KEY_V2', $bb_recaptcha_secret_key_v2_master);
if (!defined('RECAPTCHA_SITE_KEY_V3')) define('RECAPTCHA_SITE_KEY_V3', $bb_recaptcha_site_key_v3_master);
if (!defined('RECAPTCHA_SECRET_KEY_V3')) define('RECAPTCHA_SECRET_KEY_V3', $bb_recaptcha_secret_key_v3_master);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="theme-color" content="#020617">
    <title><?php echo htmlspecialchars($nome_sistema, ENT_QUOTES, 'UTF-8'); ?></title>
    <link rel="icon" href="<?php echo htmlspecialchars($caminho_icone . '?v=' . $bb_icone_ver, ENT_QUOTES, 'UTF-8'); ?>" type="<?php echo htmlspecialchars($bb_icone_tipo, ENT_QUOTES, 'UTF-8'); ?>">
    <link rel="shortcut icon" href="<?php echo htmlspecialchars($caminho_icone . '?v=' . $bb_icone_ver, ENT_QUOTES, 'UTF-8'); ?>" type="<?php echo htmlspecialchars($bb_icone_tipo, ENT_QUOTES, 'UTF-8'); ?>">


    <?php if (!empty($bb_recaptcha_site_key_master)) : ?>
    <!-- reCAPTCHA MASTER: carrega pelo banco global e mantém o selo visível no index/marketplace/criar-barbearia -->
    <script>
    (function(){
        'use strict';

        window.bbRecaptchaTipo = <?php echo json_encode($bb_recaptcha_tipo_master, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        window.bbRecaptchaSiteKey = <?php echo json_encode($bb_recaptcha_site_key_master, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        window.bbRecaptchaTokenAtual = '';
        window.bbRecaptchaCarregando = false;
        window.bbRecaptchaCarregado = false;
        window.bbRecaptchaCallbacks = window.bbRecaptchaCallbacks || [];

        function bbRecaptchaAction(form){
            if (!form) return 'pageview';
            var action = form.getAttribute('data-recaptcha-action') || form.getAttribute('data-action') || form.id || 'form';
            action = String(action).toLowerCase().replace(/[^a-z0-9_]/g, '_').replace(/^_+|_+$/g, '');
            return action || 'form';
        }

        function bbRecaptchaInput(form, nome){
            if (!form) return null;
            var input = form.querySelector('input[name="' + nome + '"]');
            if (!input) {
                input = document.createElement('input');
                input.type = 'hidden';
                input.name = nome;
                form.appendChild(input);
            }
            return input;
        }

        function bbRecaptchaMostrarBadge(){
            var badge = document.querySelector('.grecaptcha-badge');
            if (!badge) return;

            badge.style.setProperty('display', 'block', 'important');
            badge.style.setProperty('visibility', 'visible', 'important');
            badge.style.setProperty('opacity', '1', 'important');
            badge.style.setProperty('z-index', '2147483000', 'important');
            badge.style.setProperty('right', '18px', 'important');
            badge.style.setProperty('bottom', '92px', 'important');

            var iframe = badge.querySelector('iframe');
            if (iframe) {
                iframe.style.setProperty('display', 'block', 'important');
                iframe.style.setProperty('visibility', 'visible', 'important');
                iframe.style.setProperty('opacity', '1', 'important');
            }
        }

        function bbRecaptchaSetToken(form, token, action){
            window.bbRecaptchaTokenAtual = token || '';
            bbRecaptchaMostrarBadge();

            if (form) {
                var tokenInput = bbRecaptchaInput(form, 'recaptcha_token');
                var actionInput = bbRecaptchaInput(form, 'recaptcha_action');
                if (tokenInput) tokenInput.value = token || '';
                if (actionInput) actionInput.value = action || bbRecaptchaAction(form);
                return;
            }

            document.querySelectorAll('form').forEach(function(f){
                var tokenInput = bbRecaptchaInput(f, 'recaptcha_token');
                var actionInput = bbRecaptchaInput(f, 'recaptcha_action');
                if (tokenInput) tokenInput.value = token || '';
                if (actionInput) actionInput.value = bbRecaptchaAction(f);
            });
        }

        window.bbRecaptchaV2Onload = function(){
            window.bbRecaptchaCarregado = true;
            window.bbRecaptchaCarregando = false;
            var callbacks = window.bbRecaptchaCallbacks.slice();
            window.bbRecaptchaCallbacks = [];
            callbacks.forEach(function(cb){ try { cb(); } catch(e){} });
        };

        function bbRecaptchaLoad(callback){
            if (!window.bbRecaptchaSiteKey) return;
            if (typeof callback === 'function') {
                window.bbRecaptchaCallbacks.push(callback);
            }

            if (window.grecaptcha && window.grecaptcha.ready) {
                window.bbRecaptchaCarregado = true;
                window.bbRecaptchaCarregando = false;
                bbRecaptchaRunCallbacks();
                return;
            }

            if (window.bbRecaptchaCarregando) return;

            var existente = document.querySelector('script[data-bb-master-recaptcha="1"]');
            if (existente) return;

            window.bbRecaptchaCarregando = true;
            var s = document.createElement('script');
            s.async = true;
            s.defer = true;
            s.setAttribute('data-bb-master-recaptcha', '1');

            if (window.bbRecaptchaTipo === 'v2') {
                s.src = 'https://www.google.com/recaptcha/api.js?onload=bbRecaptchaV2Onload&render=explicit';
            } else {
                s.src = 'https://www.google.com/recaptcha/api.js?render=' + encodeURIComponent(window.bbRecaptchaSiteKey);
                s.onload = function(){
                    window.bbRecaptchaCarregado = true;
                    window.bbRecaptchaCarregando = false;
                    bbRecaptchaRunCallbacks();
                };
            }

            s.onerror = function(){
                window.bbRecaptchaCarregando = false;
                window.bbRecaptchaCallbacks = [];
            };

            document.head.appendChild(s);
        }

        function bbRecaptchaRunCallbacks(){
            var callbacks = window.bbRecaptchaCallbacks.slice();
            window.bbRecaptchaCallbacks = [];
            callbacks.forEach(function(cb){ try { cb(); } catch(e){} });
            setTimeout(bbRecaptchaMostrarBadge, 300);
            setTimeout(bbRecaptchaMostrarBadge, 1200);
        }

        function bbRecaptchaExecutarV3(form){
            if (!window.grecaptcha || !grecaptcha.ready || typeof grecaptcha.execute !== 'function') return;
            var action = form ? bbRecaptchaAction(form) : 'pageview';
            grecaptcha.ready(function(){
                grecaptcha.execute(window.bbRecaptchaSiteKey, {action: action}).then(function(token){
                    bbRecaptchaSetToken(form || null, token || '', action);
                }).catch(function(){
                    bbRecaptchaSetToken(form || null, '', action);
                });
            });
        }

        function bbRecaptchaPrepararV2(form){
            if (!form || !window.grecaptcha || typeof grecaptcha.render !== 'function') return;

            var tokenInput = bbRecaptchaInput(form, 'recaptcha_token');
            bbRecaptchaInput(form, 'recaptcha_action').value = bbRecaptchaAction(form);

            var box = form.querySelector('[data-bb-recaptcha-v2="1"]');
            if (!box) {
                box = document.createElement('div');
                box.setAttribute('data-bb-recaptcha-v2', '1');
                box.style.margin = '12px 0';
                var submit = form.querySelector('button[type="submit"], input[type="submit"]');
                if (submit && submit.parentNode) {
                    submit.parentNode.insertBefore(box, submit);
                } else {
                    form.appendChild(box);
                }
            }

            if (!box.getAttribute('data-widget-id')) {
                var widgetId = grecaptcha.render(box, {
                    sitekey: window.bbRecaptchaSiteKey,
                    callback: function(resp){ if (tokenInput) tokenInput.value = resp || ''; },
                    'expired-callback': function(){ if (tokenInput) tokenInput.value = ''; },
                    'error-callback': function(){ if (tokenInput) tokenInput.value = ''; }
                });
                box.setAttribute('data-widget-id', String(widgetId));
            }
        }

        function bbRecaptchaPrepararFormulario(form){
            if (!form || !window.bbRecaptchaSiteKey) return;
            bbRecaptchaInput(form, 'recaptcha_token');
            bbRecaptchaInput(form, 'recaptcha_action').value = bbRecaptchaAction(form);

            bbRecaptchaLoad(function(){
                if (window.bbRecaptchaTipo === 'v2') {
                    bbRecaptchaPrepararV2(form);
                } else {
                    bbRecaptchaExecutarV3(form);
                }
            });
        }

        function bbRecaptchaPrepararTodos(){
            document.querySelectorAll('form').forEach(function(form){
                bbRecaptchaPrepararFormulario(form);
            });
        }

        function bbRecaptchaInitMaster(){
            if (!window.bbRecaptchaSiteKey) return;

            bbRecaptchaLoad(function(){
                bbRecaptchaPrepararTodos();
                if (window.bbRecaptchaTipo === 'v3') {
                    bbRecaptchaExecutarV3(null);
                }
                bbRecaptchaMostrarBadge();
            });

            setTimeout(function(){
                bbRecaptchaLoad(function(){
                    if (window.bbRecaptchaTipo === 'v3') {
                        bbRecaptchaExecutarV3(null);
                    }
                    bbRecaptchaMostrarBadge();
                });
            }, 1200);

            setTimeout(bbRecaptchaMostrarBadge, 2400);
        }

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', bbRecaptchaInitMaster);
        } else {
            bbRecaptchaInitMaster();
        }

        document.addEventListener('focusin', function(e){
            var form = e.target && e.target.closest ? e.target.closest('form') : null;
            if (form) bbRecaptchaPrepararFormulario(form);
        });

        document.addEventListener('submit', function(e){
            var form = e.target;
            if (!form || !form.tagName || form.tagName.toLowerCase() !== 'form') return;
            bbRecaptchaPrepararFormulario(form);

            if (window.bbRecaptchaTipo === 'v2') {
                var tokenInput = form.querySelector('input[name="recaptcha_token"]');
                if (!tokenInput || tokenInput.value === '') {
                    e.preventDefault();
                    alert('Confirme o reCAPTCHA para continuar.');
                }
            }
        }, true);
    })();
    </script>
    <style>
      .grecaptcha-badge{
        display:block !important;
        visibility:visible !important;
        opacity:1 !important;
        z-index:2147483000 !important;
        right:18px !important;
        bottom:92px !important;
      }
      .grecaptcha-badge iframe{
        display:block !important;
        visibility:visible !important;
        opacity:1 !important;
      }
      @media (max-width:768px){
        .grecaptcha-badge{bottom:86px !important;}
      }
    </style>
    <?php endif; ?>

    <!--
        PageSpeed Mobile:
        - Remove Google Fonts do caminho crítico. O layout usa fonte do sistema com fallback Inter.
        - Bootstrap e Font Awesome ficam locais e carregam sem bloquear a primeira pintura.
        - O CSS do marketplace continua como CSS principal do padrão visual.
    -->
    <link rel="preload" href="<?php echo ($bb_base !== '' ? $bb_base : ''); ?>/marketplace/style.css?v=20260513-logo-icons" as="style">
    <link rel="stylesheet" href="<?php echo ($bb_base !== '' ? $bb_base : ''); ?>/marketplace/style.css?v=20260513-logo-icons">

    <!-- Font Awesome local sem bloquear LCP/TBT. Os ícones carregam depois da primeira pintura. -->
    <link rel="stylesheet" href="<?php echo ($bb_base !== '' ? $bb_base : ''); ?>/app/painel/fonts/css/fontawesome-all.min.css?v=20260526-green" media="print" onload="this.media='all'">
    <link rel="stylesheet" href="<?php echo ($bb_base !== '' ? $bb_base : ''); ?>/app/painel/fonts/css/v4-shims.min.css?v=20260526-green" media="print" onload="this.media='all'">
    <noscript>
      <link rel="stylesheet" href="<?php echo ($bb_base !== '' ? $bb_base : ''); ?>/app/painel/fonts/css/fontawesome-all.min.css?v=20260526-green">
      <link rel="stylesheet" href="<?php echo ($bb_base !== '' ? $bb_base : ''); ?>/app/painel/fonts/css/v4-shims.min.css?v=20260526-green">
    </noscript>

    <noscript><link rel="stylesheet" href="<?php echo ($bb_base !== '' ? $bb_base : ''); ?>/css/bootstrap.css?v=20260513-logo-icons"></noscript>

    <script>
    (function(){
        function bbLoadCss(href){
            var l = document.createElement('link');
            l.rel = 'stylesheet';
            l.href = href;
            document.head.appendChild(l);
        }
        function bbIdle(cb){
            if('requestIdleCallback' in window){ requestIdleCallback(cb, {timeout:2200}); }
            else { setTimeout(cb, 1200); }
        }
        window.addEventListener('load', function(){
            bbIdle(function(){
                bbLoadCss('<?php echo ($bb_base !== '' ? $bb_base : ''); ?>/css/bootstrap.css?v=20260513-logo-icons');
            });
        }, {once:true});
    })();
    </script>

    <style>
        /* ✅ HEADER modelo do print (web e celular) */
        :root{
            --bb-bg: #020617;
            --bb-bg2: #0b1224;
            --bb-text: #f8fafc;
            --bb-muted: rgba(248,250,252,.75);
            --bb-border: rgba(255,255,255,.10);
            --bb-blue: #2563eb;
        }

        body { font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif; background: var(--bb-bg); }
        .bb-header-wrap{ position: sticky; top:0; z-index: 1000; background: linear-gradient(90deg, #05081b 0%, #060a20 60%, #070b22 100%); border-bottom: 1px solid var(--bb-border); }
        .bb-header{ display:flex; align-items:center; justify-content:space-between; gap:16px; padding: 14px 22px; max-width: 1400px; margin:0 auto; }

        .bb-brand{ display:flex; align-items:center; gap:10px; min-width: 180px; }
        .bb-brand img{ height: 44px; width:auto; display:block; }

        .bb-nav{ display:flex; align-items:center; justify-content:center; gap:18px; flex: 1; }
        .bb-nav a{ color: var(--bb-text); opacity: .92; font-weight:600; font-size: 14px; text-decoration:none; display:flex; gap:8px; align-items:center; }
        .bb-nav a:hover{ opacity: 1; }

        .bb-cta{ display:flex; align-items:center; gap:12px; }
        .bb-btn{ display:inline-flex; align-items:center; gap:10px; font-weight:800; font-size: 13px; letter-spacing:.2px; padding: 10px 16px; border-radius: 999px; border: 2px solid rgba(255,255,255,.25); color: var(--bb-text); background: transparent; text-decoration:none; white-space: nowrap; }
        .bb-btn:hover{ border-color: rgba(255,255,255,.45); }

        .bb-btn-primary{ background: var(--bb-blue); border-color: var(--bb-blue); }
        .bb-btn-primary:hover{ filter: brightness(1.05); }

        /* mobile */
        .bb-menu-btn{ display:none; width:42px; height:42px; border-radius: 12px; border: 1px solid var(--bb-border); background: rgba(255,255,255,.04); color: var(--bb-text); align-items:center; justify-content:center; cursor:pointer; }
        .bb-mobile{ display:none; }
        .bb-backdrop{ display:none; position:fixed; inset:0; background: rgba(0,0,0,.55); z-index: 999; }

        @media (max-width: 992px){
            .bb-nav{ display:none; }
            .bb-cta{ display:none; }
            .bb-menu-btn{ display:flex; margin-left: auto; }
            .bb-brand{ min-width: unset; }

            .bb-mobile{ display:block; position: fixed; top: 0; right: -320px; width: 320px; height: 100vh; background: #0b1026; border-left: 1px solid var(--bb-border); z-index: 1001; padding: 18px; transition: right .25s ease; }
            .bb-mobile.open{ right: 0; }
            .bb-backdrop.open{ display:block; }

            .bb-m-head{ display:flex; align-items:center; justify-content:space-between; margin-bottom: 18px; }
            .bb-m-close{ width:40px; height:40px; border-radius: 12px; border:1px solid var(--bb-border); background: rgba(255,255,255,.04); color: var(--bb-text); cursor:pointer; }

            .bb-m-links{ display:flex; flex-direction:column; gap: 10px; margin-bottom: 18px; }
            .bb-m-links a{ padding: 10px 12px; border-radius: 12px; background: rgba(255,255,255,.03); border: 1px solid var(--bb-border); color: var(--bb-text); font-weight: 700; text-decoration:none; display:flex; align-items:center; gap:10px; }

            .bb-m-cta{ display:flex; flex-direction:column; gap:10px; }
            .bb-m-cta .bb-btn{ justify-content:center; width:100%; }
        }

        /* ============================================================
           CRÍTICO PAGESPEED MOBILE - acima da dobra
           Mantém o padrão visual e reduz FCP/LCP/TBT.
           ============================================================ */
        html{scroll-behavior:auto;}
        body{font-display:swap;text-rendering:optimizeSpeed;}
        .hero{min-height:560px;contain:layout paint;content-visibility:visible;}
        .hero-text h1{font-display:swap;}
        .premium-laptop-wide,.laptop-screen-wide{contain:layout paint;}
        .laptop-screen-wide{aspect-ratio:16/9;background:linear-gradient(135deg,#020617 0%,#0f172a 55%,#1d4ed8 100%);}
        @media (max-width:768px){
            .hero{min-height:auto !important;padding:34px 5% 28px !important;gap:20px !important;}
            .hero-text{max-width:100%;}
            .hero-text h1{font-size:2rem !important;line-height:1.12 !important;margin-bottom:14px !important;}
            .hero-text p{font-size:1rem !important;margin-bottom:20px !important;}
            .hero-img{display:none !important;}
        }



        /* ============================================================
           VISUAL AZUL TOM SOBRE TOM - acima da dobra
           Mantém a lógica e corrige fundo branco/cinza mesmo com cache.
           ============================================================ */
        html,
        body{
            background:
                radial-gradient(circle at 10% 0%, rgba(37,99,235,.26) 0%, transparent 34%),
                linear-gradient(180deg,#020617 0%,#061a3d 50%,#07152f 100%) !important;
            color:#f8fbff !important;
        }
        .hero{
            background:
                radial-gradient(circle at 83% 20%, rgba(96,165,250,.34) 0%, transparent 32%),
                radial-gradient(circle at 18% 10%, rgba(37,99,235,.28) 0%, transparent 34%),
                linear-gradient(135deg,#031126 0%,#071f4b 44%,#0b3d91 100%) !important;
            color:#ffffff !important;
            border-bottom:1px solid rgba(96,165,250,.18);
        }
        .hero-text h1,
        .hero-text h1 *,
        .section-title{
            color:#f8fbff !important;
        }
        .hero-text h1 span{
            color:#93c5fd !important;
        }
        .hero-text p,
        .section-subtitle{
            color:#dbeafe !important;
        }
        .hero-img{
            background:linear-gradient(145deg,rgba(15,56,128,.34),rgba(7,24,61,.12)) !important;
            border-radius:34px;
            padding:22px;
            border:1px solid rgba(147,197,253,.16);
            box-shadow:0 30px 90px rgba(2,6,23,.40);
        }
        .pain-section{
            background:
                radial-gradient(circle at 50% 0%, rgba(59,130,246,.22) 0%, transparent 34%),
                linear-gradient(180deg,#08265d 0%,#061a3d 48%,#07152f 100%) !important;
            color:#f8fbff !important;
        }
        .pain-card{
            background:linear-gradient(180deg,rgba(13,59,142,.64) 0%,rgba(7,31,75,.74) 100%) !important;
            border:1px solid rgba(147,197,253,.20) !important;
        }
        .pain-card h3{color:#ffffff !important;}
        .pain-card p{color:#dbeafe !important;}
        @media (max-width:768px){
            .hero{
                background:
                    radial-gradient(circle at 82% 8%, rgba(96,165,250,.26) 0%, transparent 38%),
                    linear-gradient(180deg,#031126 0%,#08265d 100%) !important;
            }
        }

        /* Compatibilidade visual: deixa FA6 mandar nos ícones atuais e mantém fallback antigo. */
        .fa-solid,.fas{font-family:"Font Awesome 6 Free",FontAwesome,Arial,sans-serif;font-weight:900;}
        .fa-regular,.far{font-family:"Font Awesome 6 Free",FontAwesome,Arial,sans-serif;font-weight:400;}
        .fa-brands,.fab{font-family:"Font Awesome 6 Brands",FontAwesome,Arial,sans-serif;font-weight:400;}

    </style>
</head>
<body>

<div class="bb-backdrop" id="bbBackdrop" onclick="bbToggleMenu(false)"></div>

<div class="bb-header-wrap">
    <div class="bb-header">
        <a class="bb-brand" href="<?php echo $link_home; ?>">
            <img src="<?php echo htmlspecialchars($caminho_logo . '?v=' . $bb_logo_ver, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($nome_sistema, ENT_QUOTES, 'UTF-8'); ?>" width="180" height="44" fetchpriority="high" decoding="async" onerror="this.onerror=null;this.src='<?php echo htmlspecialchars($bb_base . '/sistema/img/logo.png?v=' . $bb_logo_ver, ENT_QUOTES, 'UTF-8'); ?>';">
        </a>

        <nav class="bb-nav" aria-label="Menu principal">
            <a href="#funcionalidades"><i class="fa-solid fa-rocket"></i> Funcionalidades</a>
            <a href="#planos"><i class="fa-solid fa-tags"></i> Planos</a>
        </nav>

        <div class="bb-cta">
            <a class="bb-btn" href="<?php echo $link_cliente_estab; ?>" target="_blank">
                <i class="fa-solid fa-user"></i> SOU CLIENTE / ESTABELECIMENTOS
            </a>
            <a class="bb-btn bb-btn-primary" href="<?php echo $link_barbeiro; ?>" target="_blank">
                <i class="fa-solid fa-scissors"></i> SOU BARBEIRO / CABELEIREIRO
            </a>
        </div>

        <button class="bb-menu-btn" type="button" onclick="bbToggleMenu(true)" aria-label="Abrir menu">
            <i class="fa-solid fa-bars"></i>
        </button>
    </div>
</div>

<!-- MENU MOBILE -->
<aside class="bb-mobile" id="bbMobileMenu" aria-label="Menu mobile">
    <div class="bb-m-head">
        <div style="display:flex;align-items:center;gap:10px;">
            <img src="<?php echo htmlspecialchars($caminho_logo . '?v=' . $bb_logo_ver, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($nome_sistema, ENT_QUOTES, 'UTF-8'); ?>" width="140" height="34" decoding="async" style="height:34px;width:auto;" onerror="this.onerror=null;this.src='<?php echo htmlspecialchars($bb_base . '/sistema/img/logo.png?v=' . $bb_logo_ver, ENT_QUOTES, 'UTF-8'); ?>';">
        </div>
        <button class="bb-m-close" type="button" onclick="bbToggleMenu(false)" aria-label="Fechar menu">
            <i class="fa-solid fa-xmark"></i>
        </button>
    </div>

    <div class="bb-m-links">
        <a href="#funcionalidades" onclick="bbToggleMenu(false)"><i class="fa-solid fa-rocket"></i> Funcionalidades</a>
        <a href="#planos" onclick="bbToggleMenu(false)"><i class="fa-solid fa-tags"></i> Planos</a>
    </div>

    <div class="bb-m-cta">
        <a class="bb-btn" href="<?php echo $link_cliente_estab; ?>" target="_blank">
            <i class="fa-solid fa-user"></i> SOU CLIENTE / ESTABELECIMENTOS
        </a>
        <a class="bb-btn bb-btn-primary" href="<?php echo $link_barbeiro; ?>" target="_blank">
            <i class="fa-solid fa-scissors"></i> SOU BARBEIRO / CABELEIREIRO
        </a>
    </div>
</aside>

<script>
    function bbToggleMenu(open){
        var menu = document.getElementById('bbMobileMenu');
        var back = document.getElementById('bbBackdrop');
        if(!menu || !back) return;

        if(open){
            menu.classList.add('open');
            back.classList.add('open');
            document.body.style.overflow = 'hidden';
        }else{
            menu.classList.remove('open');
            back.classList.remove('open');
            document.body.style.overflow = '';
        }
    }
</script>
