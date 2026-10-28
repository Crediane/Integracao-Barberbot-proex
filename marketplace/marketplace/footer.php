<?php
// ======================================================================
// CONTEXTO CENTRALIZADO (MASTER x TENANT)
// - O footer não escolhe sessão, não usa HTTP_REFERER e não troca banco.
// - Rotas TENANT e MASTER/Admin continuam com validação obrigatória.
// - Páginas públicas, como /marketplace.php/, podem finalizar o layout
//   mesmo quando a conexão administrativa opcional não estiver ativa.
// ======================================================================
$__bb_reqPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');
$__bb_isSystemRoute = (bool)preg_match('~^/sistema(?:/|$)~i', $__bb_reqPath);

if (!isset($pdo) || !($pdo instanceof PDO)) {
    $baseConn = __DIR__ . '/../sistema/conexao.php';

    if (!is_file($baseConn)) {
        http_response_code(500);
        echo 'Configuração de conexão não encontrada.';
        exit;
    }

    require_once $baseConn;
}

if (!function_exists('bb_sess_clean_slug')) {
    function bb_sess_clean_slug($valor) {
        $valor = strtolower(trim((string)$valor));
        return preg_replace('/[^a-z0-9_-]/', '', $valor);
    }
}

$__bb_footerSessionName = (string)session_name();
$__bb_footerIsTenant = (strpos($__bb_footerSessionName, 'BB_TENANT') === 0);
$__bb_footerIsMaster = ($__bb_footerSessionName === 'BB_MASTER');
$__bb_footerIsMasterAdmin = ($__bb_footerIsMaster && $__bb_isSystemRoute);

// Mantém o nome legado da variável para não alterar o restante do layout.
// Agora o valor vem do contexto validado, inclusive em subdomínio/domínio próprio.
$__tenantFromUri = $__bb_footerIsTenant
    ? bb_sess_clean_slug($_SESSION['tenant_slug'] ?? '')
    : '';

$__bb_footerHasPdo = isset($pdo) && ($pdo instanceof PDO);
$__bb_footerConnectedDb = '';
if ($__bb_footerHasPdo) {
    try {
        $__bb_footerConnectedDb = trim((string)$pdo->query('SELECT DATABASE()')->fetchColumn());
    } catch (Throwable $e) {
        $__bb_footerConnectedDb = '';
    }
}

if ($__bb_footerIsTenant) {
    $__bb_footerExpectedDb = trim((string)($_SESSION['tenant_db'] ?? ''));

    if (
        !$__bb_footerHasPdo
        || $__tenantFromUri === ''
        || $__bb_footerExpectedDb === ''
        || $__bb_footerConnectedDb === ''
        || strcasecmp($__bb_footerConnectedDb, $__bb_footerExpectedDb) !== 0
    ) {
        http_response_code(500);
        echo 'Falha de isolamento do banco individual da barbearia.';
        exit;
    }
} elseif ($__bb_footerIsMasterAdmin) {
    $__bb_footerMarketplaceConfigFile = __DIR__ . '/../config/marketplace.php';
    $__bb_footerMarketplaceConfig = is_file($__bb_footerMarketplaceConfigFile)
        ? require $__bb_footerMarketplaceConfigFile
        : [];

    $__bb_footerExpectedGlobalDb = '';
    if (
        is_array($__bb_footerMarketplaceConfig)
        && !empty($__bb_footerMarketplaceConfig['auth_master'])
        && is_array($__bb_footerMarketplaceConfig['auth_master'])
    ) {
        $__bb_footerExpectedGlobalDb = preg_replace(
            '/[^a-z0-9_]/i',
            '',
            (string)($__bb_footerMarketplaceConfig['auth_master']['db'] ?? '')
        );
    }

    if (
        !$__bb_footerHasPdo
        || $__bb_footerExpectedGlobalDb === ''
        || $__bb_footerConnectedDb === ''
        || strcasecmp($__bb_footerConnectedDb, $__bb_footerExpectedGlobalDb) !== 0
    ) {
        http_response_code(500);
        echo 'Falha de isolamento do banco Global/Administrativo.';
        exit;
    }
}

/*
  CONFIGURAÇÃO DO CONTEXTO ATUAL
  - TENANT: tabela config do banco individual confirmado.
  - SITE/MASTER: tabela config do banco Global/Administrativo confirmado.
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

// Garante variáveis para não quebrar o layout sem inventar dados de contato.
$nome_sistema     = $nome_sistema ?? 'BarberBot';
$whatsapp_sistema = $whatsapp_sistema ?? '';
$tel_whatsapp     = $tel_whatsapp ?? '';
$email_sistema    = $email_sistema ?? '';
$endereco_sistema = $endereco_sistema ?? '';
$texto_rodape     = $texto_rodape ?? '';
$caminho_logo     = $caminho_logo ?? '';

// O tenant pode estar na rota /loja/{slug}/ ou resolvido pelo host.
// A navegação preserva o padrão já aceito pelo ecossistema.
$__bb_footerTenantBySlugPath = false;
if ($__tenantFromUri !== '') {
    $__bb_footerTenantBySlugPath =
        (bool)preg_match(
            '~^/loja/' . preg_quote($__tenantFromUri, '~') . '(?:/|$)~i',
            (isset($__bb_routePath) ? $__bb_routePath : $__bb_reqPath)
        )
        || !empty($_GET['loja'])
        || !empty($_POST['loja']);
}

$__bb_footerAppPrefix = isset($bb_web_base) ? rtrim((string)$bb_web_base, '/') : '';
$__bb_footerTenantPrefix = '';
if ($__tenantFromUri !== '' && $__bb_footerTenantBySlugPath) {
    $__bb_footerTenantPrefix = $__bb_footerAppPrefix . '/loja/' . rawurlencode($__tenantFromUri);
}

if ($__tenantFromUri !== '') {
    $link_sistema = ($__bb_footerTenantPrefix !== '')
        ? ($__bb_footerTenantPrefix . '/sistema/')
        : ($__bb_footerAppPrefix . '/sistema/');

    $bb_cadastrar_endpoint = ($__bb_footerTenantPrefix !== '')
        ? ($__bb_footerTenantPrefix . '/cadastrar.php')
        : ($__bb_footerAppPrefix . '/cadastrar.php?loja=' . rawurlencode($__tenantFromUri));
} else {
    $link_sistema = $__bb_footerAppPrefix . '/sistema/';
    $bb_cadastrar_endpoint = $__bb_footerAppPrefix . '/cadastrar.php';
}

$bb_footer_servicos_link = ($__tenantFromUri !== '')
    ? (($__bb_footerTenantPrefix !== '') ? ($__bb_footerTenantPrefix . '/servicos/') : ($__bb_footerAppPrefix . '/servicos/'))
    : ($__bb_footerAppPrefix . '/marketplace.php#bbGrid');

$bb_footer_produtos_link = ($__tenantFromUri !== '')
    ? (($__bb_footerTenantPrefix !== '') ? ($__bb_footerTenantPrefix . '/produtos/') : ($__bb_footerAppPrefix . '/produtos/'))
    : ($__bb_footerAppPrefix . '/marketplace.php#bbGrid');

// ✅ Base URL absoluta para montar logo/ícone sem quebrar em /marketplace.php/ e /loja/{slug}/.
$bb_proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $xfp = strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']);
    if ($xfp === 'https' || $xfp === 'http') {
        $bb_proto = $xfp;
    }
}
$bb_host = (string)($_SERVER['HTTP_HOST'] ?? '');
if (!isset($bb_base) || trim((string)$bb_base) === '') {
    $bb_base = ($bb_host !== '') ? ($bb_proto . '://' . $bb_host . $__bb_footerAppPrefix) : $__bb_footerAppPrefix;
}

// ✅ Caminhos públicos para logo/ícone vindos da tabela config do contexto atual.
// MASTER: jctecbr_master.config | TENANT: banco individual da loja (/loja/{slug}/).
if (!function_exists('bb_asset_url_marketplace')) {
    function bb_asset_url_marketplace($arquivo, $pasta_padrao = 'sistema/img') {
        global $bb_base;

        $arquivo = trim((string)$arquivo);
        $base = rtrim((string)$bb_base, '/');

        if ($arquivo === '') {
            $arquivo = 'sem-foto.png';
        }

        if (preg_match('~^https?://~i', $arquivo)) {
            return $arquivo;
        }

        if (strpos($arquivo, '/') === 0) {
            return $base . $arquivo;
        }

        if (strpos($arquivo, '/') !== false) {
            return $base . '/' . ltrim($arquivo, '/');
        }

        return $base . '/' . trim($pasta_padrao, '/') . '/' . ltrim($arquivo, '/');
    }
}

$logo_arquivo = (!empty($logo_sistema) ? (string)$logo_sistema : 'logo.png');
if (empty($caminho_logo)) {
    $caminho_logo = bb_asset_url_marketplace($logo_arquivo, 'sistema/img');
}
$bb_logo_ver = time();


// ✅ MOSTRAR MODAL SOMENTE NA HOME (index.php ou "/")
$bb_is_home = false;
$bb_script  = $_SERVER['SCRIPT_NAME'] ?? '';
$bb_req_uri = $_SERVER['REQUEST_URI'] ?? '/';
$bb_path    = parse_url($bb_req_uri, PHP_URL_PATH) ?: '/';
$bb_script_base = strtolower(basename($bb_script));

if ($bb_script_base === 'index.php') {
    $bb_is_home = true;
} else {
    // cobre acesso por "/" (quando a home abre sem index.php)
    $bb_path_trim = rtrim($bb_path, '/');
    if ($bb_path_trim === '') {
        $bb_is_home = true;
    }
}

// Política e Termos são páginas globais físicas da raiz.
// Não cria URLs tenant inexistentes.
$link_politica_cookie = '/politica-privacidade.php';
$link_termos_uso = '/termos-uso.php';
?>

<style>
/* ============================================================
   Crédito do rodapé - Criado por Jacy Cordeiro
   Mantém a lógica do sistema e altera somente a apresentação visual.
   ============================================================ */
.bb-footer-credit{
    display:inline-flex;
    align-items:center;
    justify-content:center;
    gap:6px;
    margin-left:10px;
    padding:7px 12px;
    border:1px solid rgba(0,168,255,.45);
    border-radius:999px;
    background:rgba(2,6,23,.38);
    color:#ffffff !important;
    font-size:14px;
    font-weight:700;
    line-height:1;
    text-decoration:none !important;
    white-space:nowrap;
    box-shadow:0 6px 18px rgba(0,0,0,.20);
    transition:all .2s ease;
}
.bb-footer-credit:hover,
.bb-footer-credit:focus{
    color:#ffffff !important;
    border-color:#00a8ff;
    background:rgba(0,168,255,.14);
    transform:translateY(-1px);
}
.bb-footer-credit__label{
    color:#f5b942;
    font-weight:800;
}
.bb-footer-credit__name{
    color:#00a8ff;
    font-weight:900;
}
@media (max-width: 768px){
    .bb-footer-credit{
        margin-left:0;
        margin-top:8px;
        width:max-content;
        max-width:100%;
        font-size:13px;
        padding:7px 10px;
    }
}

/* ============================================================
   WhatsApp flutuante + modal de cadastro
   Mantém o padrão do sistema e não depende de Bootstrap, jQuery ou Font Awesome.
   ============================================================ */
.bb-zap-flutuante{
    position:fixed !important;
    right:24px !important;
    bottom:24px !important;
    z-index:2147483647 !important;
    display:flex !important;
    align-items:center !important;
    justify-content:center !important;
    width:64px !important;
    height:64px !important;
    min-width:64px !important;
    min-height:64px !important;
    max-width:64px !important;
    max-height:64px !important;
    padding:0 !important;
    margin:0 !important;
    border:0 !important;
    border-radius:50% !important;
    background:#25d366 !important;
    color:#ffffff !important;
    font-size:0 !important;
    font-weight:900 !important;
    line-height:1 !important;
    text-decoration:none !important;
    cursor:pointer !important;
    box-shadow:0 12px 32px rgba(37,211,102,.46) !important;
    opacity:1 !important;
    visibility:visible !important;
    overflow:visible !important;
    pointer-events:auto !important;
    transform:none !important;
    clip:auto !important;
    clip-path:none !important;
    appearance:none !important;
    -webkit-appearance:none !important;
}
.bb-zap-flutuante:hover,
.bb-zap-flutuante:focus{
    background:#12b454 !important;
    color:#ffffff !important;
    transform:translateY(-2px) scale(1.03) !important;
    box-shadow:0 16px 38px rgba(22,201,95,.55) !important;
    outline:none !important;
}
.bb-zap-flutuante svg{
    width:28px !important;
    height:28px !important;
    display:block !important;
    fill:currentColor !important;
}
.bb-zap-flutuante span{
    display:inline-block !important;
    color:#ffffff !important;
    white-space:nowrap !important;
}
.bb-zap-modal-overlay{
    position:fixed !important;
    inset:0 !important;
    z-index:2147483640 !important;
    display:none;
    align-items:center !important;
    justify-content:center !important;
    padding:18px !important;
    background:rgba(0,0,0,.72) !important;
}
.bb-zap-modal-overlay.bb-zap-modal-aberta{
    display:flex !important;
}
.bb-zap-modal-card{
    width:100% !important;
    max-width:560px !important;
    overflow:hidden !important;
    border-radius:14px !important;
    background:#ffffff !important;
    color:#1f2937 !important;
    box-shadow:0 28px 70px rgba(0,0,0,.42) !important;
}
.bb-zap-modal-topo{
    display:flex !important;
    align-items:center !important;
    justify-content:center !important;
    position:relative !important;
    padding:22px 58px 22px 24px !important;
    background:#22c55e !important;
    color:#06351a !important;
    font-size:26px !important;
    font-weight:900 !important;
    text-align:center !important;
}
.bb-zap-modal-topo svg{
    width:27px !important;
    height:27px !important;
    margin-right:10px !important;
    fill:currentColor !important;
}
.bb-zap-modal-fechar{
    position:absolute !important;
    top:14px !important;
    right:16px !important;
    width:36px !important;
    height:36px !important;
    display:flex !important;
    align-items:center !important;
    justify-content:center !important;
    border:0 !important;
    border-radius:50% !important;
    background:transparent !important;
    color:#ffffff !important;
    font-size:32px !important;
    line-height:1 !important;
    cursor:pointer !important;
}
.bb-zap-modal-corpo{
    padding:30px 30px 22px 30px !important;
}
.bb-zap-modal-texto{
    margin:0 0 24px 0 !important;
    color:#4b5563 !important;
    font-size:18px !important;
    line-height:1.45 !important;
    text-align:center !important;
}
.bb-zap-campo{
    margin-bottom:20px !important;
}
.bb-zap-campo label{
    display:block !important;
    margin:0 0 9px 0 !important;
    color:#1f2937 !important;
    font-size:20px !important;
    font-weight:900 !important;
    text-align:center !important;
}
.bb-zap-campo input{
    width:100% !important;
    height:58px !important;
    padding:12px 18px !important;
    border:1px solid #f1d7a8 !important;
    border-radius:5px !important;
    background:#ffffff !important;
    color:#1f2937 !important;
    font-size:20px !important;
    outline:none !important;
    box-sizing:border-box !important;
}
.bb-zap-campo input:focus{
    border-color:#22c55e !important;
    box-shadow:0 0 0 3px rgba(34,197,94,.15) !important;
}
.bb-zap-consentimento{
    display:flex !important;
    align-items:flex-start !important;
    gap:12px !important;
    padding:18px !important;
    margin:4px 0 24px 0 !important;
    border:1px solid #e5e7eb !important;
    border-radius:6px !important;
    background:#ffffff !important;
    color:#374151 !important;
    font-size:17px !important;
    line-height:1.5 !important;
}
.bb-zap-consentimento input{
    width:20px !important;
    height:20px !important;
    min-width:20px !important;
    margin-top:4px !important;
}
.bb-zap-consentimento a{
    color:#047857 !important;
    font-weight:900 !important;
    text-decoration:underline !important;
}
.bb-zap-acoes{
    display:flex !important;
    gap:14px !important;
    justify-content:flex-end !important;
}
.bb-zap-btn{
    min-width:160px !important;
    min-height:56px !important;
    padding:14px 22px !important;
    border:0 !important;
    border-radius:3px !important;
    cursor:pointer !important;
    font-size:18px !important;
    font-weight:900 !important;
    letter-spacing:.3px !important;
    text-transform:uppercase !important;
}
.bb-zap-btn-cancelar{
    background:#d4d4d8 !important;
    color:#3f3f46 !important;
}
.bb-zap-btn-continuar{
    display:inline-flex !important;
    align-items:center !important;
    justify-content:center !important;
    gap:10px !important;
    background:#22c55e !important;
    color:#ffffff !important;
}
.bb-zap-btn-continuar svg{
    width:22px !important;
    height:22px !important;
    fill:currentColor !important;
}
.bb-zap-btn:disabled{
    opacity:.65 !important;
    cursor:not-allowed !important;
}
@media (max-width:768px){
    .bb-zap-flutuante{
        right:16px !important;
        bottom:16px !important;
        width:62px !important;
        height:62px !important;
        min-width:62px !important;
        min-height:62px !important;
        padding:0 !important;
    }
    .bb-zap-flutuante span{
        display:none !important;
    }
    .bb-zap-modal-topo{
        font-size:23px !important;
        padding:20px 54px 20px 20px !important;
    }
    .bb-zap-modal-corpo{
        padding:24px 20px 20px 20px !important;
    }
    .bb-zap-acoes{
        flex-direction:column !important;
    }
    .bb-zap-btn{
        width:100% !important;
    }
}

</style>

<?php
$bb_footer_home_link = (!empty($__tenantFromUri))
    ? (($__bb_footerTenantPrefix !== '') ? ($__bb_footerTenantPrefix . '/') : '/')
    : '/marketplace.php';

$bb_footer_texto_padrao = (!empty($__tenantFromUri))
    ? 'Confira nossos serviços, produtos e agende seu horário online.'
    : 'Encontre profissionais da beleza, veja portfólios e agende seu horário online com facilidade.';

$bb_footer_texto_exibir = !empty($texto_rodape) ? $texto_rodape : $bb_footer_texto_padrao;
?>

<footer class="footer-dark">
    <div class="footer-container">
        
        <div class="footer-col info">
            <div class="footer-logo">
                <a href="<?php echo htmlspecialchars($bb_footer_home_link, ENT_QUOTES, 'UTF-8'); ?>" style="text-decoration:none; display:flex; align-items:center; gap:10px;">
                    
                    <?php if (!empty($caminho_logo)) { ?>
                        <img src="<?php echo htmlspecialchars($caminho_logo . '?v=' . $bb_logo_ver, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($nome_sistema ?? 'BarberBot', ENT_QUOTES, 'UTF-8'); ?>" width="160" height="45" loading="lazy" decoding="async" style="height:45px;width:auto;" onerror="this.onerror=null;this.style.display='none';this.insertAdjacentHTML('afterend','<i class=&quot;fa-solid fa-scissors&quot; style=&quot;font-size:2rem;color:#00a8ff;&quot;></i>');">
                    <?php } else { ?>
                        <i class="fa-solid fa-scissors" style="font-size: 2rem; color: #00a8ff;"></i>
                    <?php } ?>
                    
                    <span style="color:#fff; font-weight:800; font-size:1.4rem; text-transform:uppercase;">
                        <?php echo htmlspecialchars($nome_sistema ?? 'BarberBot', ENT_QUOTES, 'UTF-8'); ?>
                    </span>
                </a>
            </div>
            
            <p style="color: #d1d5db; margin-top: 15px;">
                <?php echo htmlspecialchars($bb_footer_texto_exibir, ENT_QUOTES, 'UTF-8'); ?>
            </p>
        </div>

        <div class="footer-col links-list">
            <h3>LINKS CONTATOS</h3>
            <ul>
                <li>
                    <i class="fa-solid fa-location-dot"></i> 
                    <span>
            <a href="https://goo.gl/maps/6XJLNBjYMdBqLGZHA" target="_blank">
              <span><?php echo htmlspecialchars(!empty($endereco_sistema) ? $endereco_sistema : 'Endereço não informado', ENT_QUOTES, 'UTF-8'); ?></span>
            </a></span>
                </li>
                <?php if (empty($__tenantFromUri)) { ?>
                <li>
                    <a href="<?php echo htmlspecialchars($__bb_footerAppPrefix . '/marketplace.php', ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fa-solid fa-store"></i> 
                        <span>Marketplace</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo htmlspecialchars($__bb_footerAppPrefix . '/marketplace.php#bbGrid', ENT_QUOTES, 'UTF-8'); ?>">
                         <i class="fa-solid fa-magnifying-glass"></i> 
                        <span>Buscar profissionais</span>
                    </a>
                </li>
                <?php } else { ?>
                <li>
                    <a href="<?php echo htmlspecialchars($bb_footer_servicos_link, ENT_QUOTES, 'UTF-8'); ?>">
                        <i class="fa-solid fa-list-check"></i> 
                        <span>Serviços</span>
                    </a>
                </li>
                <li>
                    <a href="<?php echo htmlspecialchars($bb_footer_produtos_link, ENT_QUOTES, 'UTF-8'); ?>">
                         <i class="fa-solid fa-tags"></i> 
                        <span>Produtos</span>
                    </a>
                </li>
                <?php } ?>
            </ul>
        </div>

        <div class="footer-col form-area">
            <h3>SOLICITE AVALIAÇÃO</h3>
            <form id="form_cadastro">
                <?php if ($__tenantFromUri !== ''): ?>
                    <input type="hidden" name="loja" value="<?php echo htmlspecialchars($__tenantFromUri, ENT_QUOTES, 'UTF-8'); ?>">
                <?php endif; ?>
                <input type="tel" name="telefone" id="telefone_rodape" class="footer-input" placeholder="WhatsApp" required>
                <input type="text" name="nome" class="footer-input" placeholder="Nome Completo" required>
                <button type="submit" class="footer-btn">Cadastrar</button>
            </form>
        </div>

    </div>

    <div class="footer-bottom">
        <div class="bottom-content">
            <div class="bottom-left">
                <span>&copy; <?php echo date('Y'); ?></span>
                <a href="<?php echo htmlspecialchars($link_politica_cookie, ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fa-solid fa-lock"></i> Política de privacidade
                </a>
                <a href="<?php echo htmlspecialchars($link_termos_uso, ENT_QUOTES, 'UTF-8'); ?>">
                    <i class="fa-solid fa-file-contract"></i> Termos de uso
                </a>
                <a href="https://play.google.com/store/apps/details?id=app.agendacabeleireiro" target="_blank">
                    <i class="fa-solid fa-play"></i> App
                </a>
            </div>
            <div class="bottom-right">
                <span><?php echo htmlspecialchars($nome_sistema ?? 'BarberBot', ENT_QUOTES, 'UTF-8'); ?> <?php echo htmlspecialchars($whatsapp_sistema ?? '', ENT_QUOTES, 'UTF-8'); ?> |</span>
                <a href="<?php echo htmlspecialchars($link_sistema, ENT_QUOTES, 'UTF-8'); ?>" target="_blank" rel="noopener">
                    <i class="fa-solid fa-user"></i>
                </a>

                <a
                    href="https://jacycordeiro.com.br/"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="bb-footer-credit"
                    title="Criado por Jacy Cordeiro"
                    aria-label="Site criado por Jacy Cordeiro"
                >
                    <span class="bb-footer-credit__label">Criado por</span>
                    <span class="bb-footer-credit__name">Jacy Cordeiro</span>
                </a>
            </div>
        </div>
    </div>
</footer>


<?php
$bb_zap_tel_modal = preg_replace('/\D+/', '', (string)($tel_whatsapp ?? $whatsapp_sistema ?? '5545999580058'));
if ($bb_zap_tel_modal !== '' && strlen($bb_zap_tel_modal) <= 11) {
    $bb_zap_tel_modal = '55' . $bb_zap_tel_modal;
}
if ($bb_zap_tel_modal === '') {
    $bb_zap_tel_modal = '5545999580058';
}
?>

<button type="button" id="bbZapBotaoFlutuante" class="bb-zap-flutuante" aria-label="Fale conosco pelo WhatsApp" title="Fale conosco pelo WhatsApp" style="position:fixed!important;right:24px!important;bottom:24px!important;z-index:2147483647!important;display:flex!important;align-items:center!important;justify-content:center!important;width:64px!important;height:64px!important;min-width:64px!important;min-height:64px!important;max-width:64px!important;max-height:64px!important;padding:0!important;margin:0!important;border:0!important;border-radius:50%!important;background:#25d366!important;color:#ffffff!important;font-size:0!important;line-height:1!important;text-decoration:none!important;cursor:pointer!important;box-shadow:0 12px 32px rgba(37,211,102,.46)!important;opacity:1!important;visibility:visible!important;overflow:visible!important;pointer-events:auto!important;appearance:none!important;-webkit-appearance:none!important;">
    <svg viewBox="0 0 32 32" aria-hidden="true" focusable="false" style="width:36px;height:36px;display:block;fill:currentColor;">
        <path d="M16.02 3.2c-7.03 0-12.75 5.63-12.75 12.56 0 2.38.68 4.62 1.86 6.51L3.2 28.8l6.73-1.77a12.92 12.92 0 0 0 6.09 1.52c7.03 0 12.75-5.63 12.75-12.56S23.05 3.2 16.02 3.2Zm0 22.98c-1.94 0-3.74-.53-5.29-1.45l-.38-.23-3.99 1.05 1.07-3.81-.25-.39a10.15 10.15 0 0 1-1.57-5.39c0-5.62 4.67-10.19 10.41-10.19 5.74 0 10.41 4.57 10.41 10.19 0 5.62-4.67 10.22-10.41 10.22Zm5.7-7.63c-.31-.15-1.85-.9-2.14-1-.29-.11-.5-.15-.71.15-.21.31-.82 1-.99 1.2-.18.21-.36.23-.67.08-.31-.15-1.32-.48-2.51-1.53-.93-.82-1.56-1.83-1.74-2.14-.18-.31-.02-.48.13-.63.14-.14.31-.36.47-.54.16-.18.21-.31.31-.51.1-.21.05-.38-.03-.54-.08-.15-.71-1.68-.97-2.3-.26-.61-.52-.53-.71-.54h-.61c-.21 0-.54.08-.82.38-.29.31-1.08 1.04-1.08 2.53 0 1.49 1.1 2.93 1.25 3.14.16.21 2.17 3.28 5.27 4.6.74.31 1.31.5 1.76.64.74.23 1.41.2 1.94.12.59-.09 1.85-.74 2.11-1.46.26-.72.26-1.33.18-1.46-.08-.13-.29-.21-.6-.36Z"/>
    </svg>
</button>

<div id="bbZapModalCadastro" class="bb-zap-modal-overlay" aria-hidden="true">
    <div class="bb-zap-modal-card" role="dialog" aria-modal="true" aria-labelledby="bbZapModalTitulo">
        <div class="bb-zap-modal-topo">
            <svg viewBox="0 0 32 32" aria-hidden="true" focusable="false">
                <path d="M16.02 3.2c-7.03 0-12.75 5.63-12.75 12.56 0 2.38.68 4.62 1.86 6.51L3.2 28.8l6.73-1.77a12.92 12.92 0 0 0 6.09 1.52c7.03 0 12.75-5.63 12.75-12.56S23.05 3.2 16.02 3.2Zm0 22.98c-1.94 0-3.74-.53-5.29-1.45l-.38-.23-3.99 1.05 1.07-3.81-.25-.39a10.15 10.15 0 0 1-1.57-5.39c0-5.62 4.67-10.19 10.41-10.19 5.74 0 10.41 4.57 10.41 10.19 0 5.62-4.67 10.22-10.41 10.22Z"/>
            </svg>
            <span id="bbZapModalTitulo">Fale Conosco</span>
            <button type="button" class="bb-zap-modal-fechar" id="bbZapModalFechar" aria-label="Fechar">&times;</button>
        </div>

        <form id="bbZapCadastroFormFooter" autocomplete="off">
            <?php if ($__tenantFromUri !== ''): ?>
                <input type="hidden" name="loja" value="<?php echo htmlspecialchars($__tenantFromUri, ENT_QUOTES, 'UTF-8'); ?>">
            <?php endif; ?>
            <div class="bb-zap-modal-corpo">
                <p class="bb-zap-modal-texto">Preencha seus dados para continuar:</p>

                <div class="bb-zap-campo">
                    <label for="bbZapNomeFooter">Nome Completo *</label>
                    <input type="text" id="bbZapNomeFooter" name="nome" placeholder="Digite seu nome completo" required>
                </div>

                <div class="bb-zap-campo">
                    <label for="bbZapTelefoneFooter">Telefone/WhatsApp *</label>
                    <input type="tel" id="bbZapTelefoneFooter" name="telefone" placeholder="(00) 00000-0000" required>
                </div>

                <label class="bb-zap-consentimento" for="bbZapConsentimentoFooter">
                    <input type="checkbox" id="bbZapConsentimentoFooter" required>
                    <span>
                        Li e aceito a
                        <a href="<?php echo htmlspecialchars($link_politica_cookie, ENT_QUOTES, 'UTF-8'); ?>" target="_blank">Política de Privacidade</a>
                        e os
                        <a href="<?php echo htmlspecialchars($link_termos_uso, ENT_QUOTES, 'UTF-8'); ?>" target="_blank">Termos de Uso</a>.
                        Autorizo o contato pelos dados informados para atendimento da minha solicitação.
                    </span>
                </label>

                <div class="bb-zap-acoes">
                    <button type="button" class="bb-zap-btn bb-zap-btn-cancelar" id="bbZapCancelarFooter">Cancelar</button>
                    <button type="submit" class="bb-zap-btn bb-zap-btn-continuar" id="bbZapContinuarFooter">
                        <svg viewBox="0 0 32 32" aria-hidden="true" focusable="false">
                            <path d="M16.02 3.2c-7.03 0-12.75 5.63-12.75 12.56 0 2.38.68 4.62 1.86 6.51L3.2 28.8l6.73-1.77a12.92 12.92 0 0 0 6.09 1.52c7.03 0 12.75-5.63 12.75-12.56S23.05 3.2 16.02 3.2Zm5.7 15.35c-.31-.15-1.85-.9-2.14-1-.29-.11-.5-.15-.71.15-.21.31-.82 1-.99 1.2-.18.21-.36.23-.67.08-.31-.15-1.32-.48-2.51-1.53-.93-.82-1.56-1.83-1.74-2.14-.18-.31-.02-.48.13-.63.14-.14.31-.36.47-.54.16-.18.21-.31.31-.51.1-.21.05-.38-.03-.54-.08-.15-.71-1.68-.97-2.3-.26-.61-.52-.53-.71-.54h-.61c-.21 0-.54.08-.82.38-.29.31-1.08 1.04-1.08 2.53 0 1.49 1.1 2.93 1.25 3.14.16.21 2.17 3.28 5.27 4.6.74.31 1.31.5 1.76.64.74.23 1.41.2 1.94.12.59-.09 1.85-.74 2.11-1.46.26-.72.26-1.33.18-1.46-.08-.13-.29-.21-.6-.36Z"/>
                        </svg>
                        Continuar
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<script>
(function(){
    'use strict';

    var telefoneAdmin = "<?php echo htmlspecialchars($bb_zap_tel_modal, ENT_QUOTES, 'UTF-8'); ?>";
    var modal = document.getElementById('bbZapModalCadastro');
    var formPrincipal = document.getElementById('bbZapCadastroFormFooter');
    var nomePrincipal = document.getElementById('bbZapNomeFooter');
    var telefonePrincipal = document.getElementById('bbZapTelefoneFooter');
    var checkPrincipal = document.getElementById('bbZapConsentimentoFooter');
    var botaoContinuar = document.getElementById('bbZapContinuarFooter');

    function aplicarEstiloBotaoZap(botao){
        if (!botao) return;
        var estilos = {
            position:'fixed', right:'24px', bottom:'24px', zIndex:'2147483647', display:'flex',
            alignItems:'center', justifyContent:'center', width:'64px', height:'64px', minWidth:'64px',
            minHeight:'64px', maxWidth:'64px', maxHeight:'64px', padding:'0', margin:'0', border:'0',
            borderRadius:'50%', background:'#25d366', color:'#ffffff', fontSize:'0', lineHeight:'1',
            textDecoration:'none', cursor:'pointer', boxShadow:'0 12px 32px rgba(37,211,102,.46)',
            opacity:'1', visibility:'visible', overflow:'visible', pointerEvents:'auto', appearance:'none'
        };

        Object.keys(estilos).forEach(function(chave){
            try { botao.style.setProperty(chave.replace(/[A-Z]/g, function(m){ return '-' + m.toLowerCase(); }), estilos[chave], 'important'); } catch(e) {}
        });

        try { botao.style.setProperty('-webkit-appearance', 'none', 'important'); } catch(e) {}
    }

    function garantirBotaoZapVisivel(){
        var botao = document.getElementById('bbZapBotaoFlutuante');

        if (!botao) {
            botao = document.createElement('button');
            botao.type = 'button';
            botao.id = 'bbZapBotaoFlutuante';
            botao.className = 'bb-zap-flutuante';
            botao.setAttribute('aria-label', 'Fale conosco pelo WhatsApp');
            botao.setAttribute('title', 'Fale conosco pelo WhatsApp');
            botao.innerHTML = '<svg viewBox="0 0 32 32" aria-hidden="true" focusable="false" style="width:36px;height:36px;display:block;fill:currentColor;"><path d="M16.02 3.2c-7.03 0-12.75 5.63-12.75 12.56 0 2.38.68 4.62 1.86 6.51L3.2 28.8l6.73-1.77a12.92 12.92 0 0 0 6.09 1.52c7.03 0 12.75-5.63 12.75-12.56S23.05 3.2 16.02 3.2Zm0 22.98c-1.94 0-3.74-.53-5.29-1.45l-.38-.23-3.99 1.05 1.07-3.81-.25-.39a10.15 10.15 0 0 1-1.57-5.39c0-5.62 4.67-10.19 10.41-10.19 5.74 0 10.41 4.57 10.41 10.19 0 5.62-4.67 10.22-10.41 10.22Zm5.7-7.63c-.31-.15-1.85-.9-2.14-1-.29-.11-.5-.15-.71.15-.21.31-.82 1-.99 1.2-.18.21-.36.23-.67.08-.31-.15-1.32-.48-2.51-1.53-.93-.82-1.56-1.83-1.74-2.14-.18-.31-.02-.48.13-.63.14-.14.31-.36.47-.54.16-.18.21-.31.31-.51.1-.21.05-.38-.03-.54-.08-.15-.71-1.68-.97-2.3-.26-.61-.52-.53-.71-.54h-.61c-.21 0-.54.08-.82.38-.29.31-1.08 1.04-1.08 2.53 0 1.49 1.1 2.93 1.25 3.14.16.21 2.17 3.28 5.27 4.6.74.31 1.31.5 1.76.64.74.23 1.41.2 1.94.12.59-.09 1.85-.74 2.11-1.46.26-.72.26-1.33.18-1.46-.08-.13-.29-.21-.6-.36Z"/></svg>';
            document.body.appendChild(botao);
        } else if (botao.parentNode !== document.body) {
            try { document.body.appendChild(botao); } catch(e) {}
        }

        aplicarEstiloBotaoZap(botao);
    }


    function somenteNumeros(valor){
        return String(valor || '').replace(/\D+/g, '');
    }

    function formatarTelefoneBR(valor){
        var d = somenteNumeros(valor).slice(0, 11);
        if (d.length <= 2) return d;
        if (d.length <= 6) return '(' + d.slice(0, 2) + ') ' + d.slice(2);
        if (d.length <= 10) return '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6);
        return '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7);
    }

    function abrirModalZap(){
        if (!modal) return;
        modal.classList.add('bb-zap-modal-aberta');
        modal.setAttribute('aria-hidden', 'false');
        document.documentElement.style.overflow = 'hidden';
        setTimeout(function(){
            if (nomePrincipal) nomePrincipal.focus();
        }, 80);
    }

    function fecharModalZap(){
        if (!modal) return;
        modal.classList.remove('bb-zap-modal-aberta');
        modal.setAttribute('aria-hidden', 'true');
        document.documentElement.style.overflow = '';
    }

    function abrirWhatsApp(){
        var texto = 'Olá, gostaria de saber mais informações sobre o BarberBot Software.';
        var url = 'https://api.whatsapp.com/send?phone=' + encodeURIComponent(telefoneAdmin) + '&text=' + encodeURIComponent(texto);
        window.open(url, '_blank');
    }

    function pegarCampoFormulario(form, nomeCampo, fallbackId){
        var campo = form ? form.querySelector('[name="' + nomeCampo + '"]') : null;
        if (!campo && fallbackId) campo = document.getElementById(fallbackId);
        return campo ? String(campo.value || '').trim() : '';
    }

    function enviarCadastroZap(evento, formAlvo){
        if (evento) {
            evento.preventDefault();
            if (typeof evento.stopImmediatePropagation === 'function') {
                evento.stopImmediatePropagation();
            } else {
                evento.stopPropagation();
            }
        }

        var form = formAlvo || formPrincipal;
        var nome = pegarCampoFormulario(form, 'nome', 'bbZapNomeFooter');
        var telefone = pegarCampoFormulario(form, 'telefone', 'bbZapTelefoneFooter');
        var aceite = true;

        if (form && form.id === 'bbZapCadastroFormFooter') {
            aceite = !!(checkPrincipal && checkPrincipal.checked);
        } else {
            var check = form ? form.querySelector('input[type="checkbox"][required]') : null;
            aceite = check ? check.checked : true;
        }

        if (nome === '' || telefone === '') {
            alert('Preencha nome e telefone');
            return false;
        }

        if (!aceite) {
            alert('Aceite a Política de Privacidade e os Termos de Uso para continuar.');
            return false;
        }

        var btn = (form && form.querySelector('button[type="submit"]')) || botaoContinuar;
        var textoOriginal = btn ? btn.innerHTML : '';

        if (btn) {
            btn.disabled = true;
            btn.innerHTML = 'Enviando...';
        }

        var dados = new URLSearchParams();
        dados.append('nome', nome);
        dados.append('telefone', telefone);
        dados.append('origem', 'whatsapp_site');

        var tenantSlug = <?php echo json_encode($__tenantFromUri, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        if (tenantSlug) dados.append('loja', tenantSlug);

        var endpointCadastro = <?php echo json_encode($bb_cadastrar_endpoint, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
        fetch(endpointCadastro, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'},
            body: dados.toString(),
            credentials: 'same-origin'
        })
        .then(function(resposta){ return resposta.text(); })
        .then(function(retorno){
            var texto = String(retorno || '').replace(/\s+/g, ' ').trim();

            if (
                texto.indexOf('Cadastrado com Sucesso') === 0 ||
                texto.indexOf('Salvo com Sucesso') === 0 ||
                texto.indexOf('Você já está Cadastrado') === 0
            ) {
                try { localStorage.setItem('clienteCadastrado', 'true'); } catch(e) {}
                fecharModalZap();

                if (window.jQuery) {
                    try { window.jQuery('#bbWhatsAppModalOnboarding').modal('hide'); } catch(e) {}
                }

                abrirWhatsApp();
                return;
            }

            alert('Erro: ' + (texto || 'não foi possível concluir o cadastro.'));
        })
        .catch(function(){
            alert('Erro de conexão.');
        })
        .finally(function(){
            if (btn) {
                btn.disabled = false;
                btn.innerHTML = textoOriginal;
            }
        });

        return false;
    }

    document.addEventListener('click', function(e){
        var abrir = e.target.closest('#bbZapBotaoFlutuante, [data-bb-zap-open], .bb-whatsapp-floating-btn');
        if (abrir) {
            e.preventDefault();
            abrirModalZap();
            return;
        }

        var fechar = e.target.closest('#bbZapModalFechar, #bbZapCancelarFooter');
        if (fechar) {
            e.preventDefault();
            fecharModalZap();
        }
    });

    if (modal) {
        modal.addEventListener('click', function(e){
            if (e.target === modal) {
                fecharModalZap();
            }
        });
    }

    document.addEventListener('keydown', function(e){
        if (e.key === 'Escape' && modal && modal.classList.contains('bb-zap-modal-aberta')) {
            fecharModalZap();
        }
    });

    document.addEventListener('input', function(e){
        if (
            e.target &&
            (e.target.id === 'bbZapTelefoneFooter' || e.target.id === 'bbWhatsAppInputMask')
        ) {
            e.target.value = formatarTelefoneBR(e.target.value);
        }
    });

    document.addEventListener('submit', function(e){
        var form = e.target;

        if (!form) return;

        if (form.id === 'bbZapCadastroFormFooter' || form.id === 'bbWhatsAppFormOnboarding') {
            enviarCadastroZap(e, form);
        }
    }, true);

    

    garantirBotaoZapVisivel();
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', garantirBotaoZapVisivel);
    } else {
        garantirBotaoZapVisivel();
    }
    window.addEventListener('load', garantirBotaoZapVisivel);
    setTimeout(garantirBotaoZapVisivel, 700);
    setTimeout(garantirBotaoZapVisivel, 1800);

    window.bbAbrirModalWhatsAppCadastro = abrirModalZap;
})();
</script>

<!-- ============================== cookies ============================== -->
<div id="bbCookieBox"
     style="display:none; position:fixed; left:50%; bottom:20px; transform:translateX(-50%); width:92%; max-width:1100px; z-index:99999; background:rgba(36,36,48,0.96); border-radius:12px; padding:18px 20px; box-shadow:0 10px 30px rgba(0,0,0,0.28); text-align:center; color:#fff;">

    <div style="font-size:22px; font-weight:700; line-height:1.2; margin-bottom:8px;">
        Este website utiliza cookies
    </div>

    <div style="font-size:14px; line-height:1.5; margin-bottom:16px; opacity:0.95;">
        Utilizamos cookies necessários, de análise e de marketing para melhorar sua experiência, medir campanhas e personalizar cookies quando permitido. Você pode aceitar ou recusar cookies não essenciais.
    </div>

    <div id="bbCookieBtns" style="display:flex; justify-content:center; align-items:center; gap:10px; flex-wrap:wrap;">
        <button type="button"
                onclick="bbAceitarCookies()"
                style="min-width:220px; border:1px solid #ffffff; background:#ffffff; color:#232330; font-weight:700; font-size:16px; border-radius:8px; padding:12px 18px; cursor:pointer;">
            Permitir Cookies
        </button>

        <button type="button"
                onclick="bbRecusarCookies()"
                style="min-width:220px; border:1px solid #ffffff; background:transparent; color:#ffffff; font-weight:700; font-size:16px; border-radius:8px; padding:12px 18px; cursor:pointer;">
            Recusar Cookies
        </button>

        <a href="<?php echo htmlspecialchars($link_politica_cookie, ENT_QUOTES, 'UTF-8'); ?>"
           style="display:inline-block; min-width:220px; border:1px solid #ffffff; background:transparent; color:#ffffff; font-weight:700; font-size:16px; border-radius:8px; padding:12px 18px; text-decoration:none;">
            Política de Privacidade
        </a>
        <a href="<?php echo htmlspecialchars($link_termos_uso, ENT_QUOTES, 'UTF-8'); ?>"
           style="display:inline-block; min-width:220px; border:1px solid #ffffff; background:transparent; color:#ffffff; font-weight:700; font-size:16px; border-radius:8px; padding:12px 18px; text-decoration:none;">
            Termos de Uso
        </a>
    </div>
</div>

<script>
function bbSetCookie(nome, valor, dias) {
    var data = new Date();
    data.setTime(data.getTime() + (dias * 24 * 60 * 60 * 1000));
    var expira = "expires=" + data.toUTCString();
    document.cookie = nome + "=" + encodeURIComponent(valor) + ";" + expira + ";path=/;SameSite=Lax";
}

function bbGetCookie(nome) {
    var nomeEQ = nome + "=";
    var partes = document.cookie.split(';');
    for (var i = 0; i < partes.length; i++) {
        var c = partes[i];
        while (c.charAt(0) === ' ') c = c.substring(1, c.length);
        if (c.indexOf(nomeEQ) === 0) {
            return decodeURIComponent(c.substring(nomeEQ.length, c.length));
        }
    }
    return "";
}

function bbFecharCookieBox() {
    var box = document.getElementById('bbCookieBox');
    if (box) {
        box.style.display = 'none';
    }
}

// Aceitar
function bbAceitarCookies() {
    bbSetCookie('bb_cookie_consent', 'aceito', 365);
    bbFecharCookieBox();
}

function bbRecusarCookies() {
    bbSetCookie('bb_cookie_consent', 'recusado', 365);
    bbFecharCookieBox();
}

function bbAbrirCookieBoxSeNecessario() {
    var consentimento = bbGetCookie('bb_cookie_consent');
    var box = document.getElementById('bbCookieBox');

    if (!box) return;

    if (consentimento === '') {
        box.style.display = 'block';
    } else {
        box.style.display = 'none';
    }

    if (window.innerWidth <= 768) {
        var botoes = document.getElementById('bbCookieBtns');
        if (botoes) {
            botoes.style.flexDirection = 'column';
        }
    }
}

// PageSpeed Green
(function(){
    var cookieAgendado = false;

    function iniciarCookie(){
        if ('requestIdleCallback' in window) {
            requestIdleCallback(function(){ bbAbrirCookieBoxSeNecessario(); }, {timeout: 3000});
        } else {
            bbAbrirCookieBoxSeNecessario();
        }
    }

    function agendarCookie(delay){
        if (cookieAgendado) return;
        cookieAgendado = true;
        setTimeout(iniciarCookie, delay || 0);
    }

    ['pointerdown','touchstart','keydown','scroll'].forEach(function(evt){
        window.addEventListener(evt, function(){ agendarCookie(1800); }, {once:true, passive:true});
    });

    window.addEventListener('load', function(){
        setTimeout(function(){ agendarCookie(0); }, 12000);
    }, {once:true});
})();
</script>

<div id="modalTermos" class="modal fade" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content dark-theme">
      <div class="modal-header dark-theme">
        <h4 class="modal-title font-weight-bold">Termos de Serviço</h4>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body" style="font-size: 0.95rem; line-height: 1.6;">
        <p><strong>1. Termos</strong></p>
        <p>Ao acessar ao site BarberBot software, concorda em cumprir estes termos de serviço, todas as leis e regulamentos aplicáveis ​​e concorda que é responsável pelo cumprimento de todas as leis locais aplicáveis. Se você não concordar com algum desses termos, está proibido de usar ou acessar este site. Os materiais contidos neste site são protegidos pelas leis de direitos autorais e marcas comerciais aplicáveis.</p>

        <p><strong>2. Uso de Licença</strong></p>
        <p>É concedida permissão para baixar temporariamente uma cópia dos materiais (informações ou software) no site BarberBot software, apenas para visualização transitória pessoal e não comercial. Esta é a concessão de uma licença, não uma transferência de título e, sob esta licença, você não pode:</p>
        <ul>
            <li>Modificar ou copiar os materiais;</li>
            <li>Usar os materiais para qualquer finalidade comercial ou para exibição pública (comercial ou não comercial);</li>
            <li>Tentar descompilar ou fazer engenharia reversa de qualquer software contido no site BarberBot software;</li>
            <li>Remover quaisquer direitos autorais ou outras notações de propriedade dos materiais; ou</li>
            <li>Transferir os materiais para outra pessoa ou 'espelhe' os materiais em qualquer outro servidor.</li>
        </ul>
        <p>Esta licença será automaticamente rescindida se você violar alguma dessas restrições e poderá ser rescindida por BarberBot software a qualquer momento. Ao encerrar a visualização desses materiais ou após o término desta licença, você deve apagar todos os materiais baixados em sua posse, seja em formato eletrónico ou impresso.</p>

        <p><strong>3. Isenção de responsabilidade</strong></p>
        <p>Os materiais no site da BarberBot software são fornecidos 'como estão'. BarberBot software não oferece garantias, expressas ou implícitas, e, por este meio, isenta e nega todas as outras garantias, incluindo, sem limitação, garantias implícitas ou condições de comercialização, adequação a um fim específico ou não violação de propriedade intelectual ou outra violação de direitos.</p>
        <p>Além disso, o BarberBot software não garante ou faz any representação relative à precisão, aos resultados prováveis ​​ou à confiabilidade do uso dos materiais em seu site ou de outra forma relacionado a esses materiais ou em sites vinculados a este site.</p>

        <p><strong>4. Limitações</strong></p>
        <p>Em nenhum caso o BarberBot software ou seus fornecedores serão responsáveis ​​por quaisquer danos (incluindo, sem limitação, danos por perda de dados ou lucro ou devido a interrupção dos negócios) decorrentes do uso ou da incapacidade de usar os materiais em BarberBot software, mesmo que BarberBot software ou um representative autorizado da BarberBot software tenha sido notificado oralmente ou por escrito da possibilidade de tais danos. Como algumas jurisdições não permitem limitações em garantias implícitas, ou limitações de responsabilidade por danos conseqüentes ou incidentais, essas limitações podem não se aplicar a você.</p>

        <p><strong>5. Precisão dos materiais</strong></p>
        <p>Os materiais displayed no site da BarberBot software podem incluir erros técnicos, tipográficos ou fotográficos. BarberBot software não garante que any material em seu site seja preciso, completo ou atual. BarberBot software pode fazer alterações nos materiais contidos in seu site a qualquer momento, sem aviso prévio. No entanto, BarberBot software não se compromete a atualizar os materiais.</p>

        <p><strong>6. Links</strong></p>
        <p>O BarberBot software não analisou todos os sites vinculados ao seu site e não é responsável pelo conteúdo de nenhum site vinculado. A inclusión de qualquer link não implica endosso por BarberBot software do site. O uso de qualquer site vinculado é por conta e risco do usuário.</p>

        <p><strong>Modificações</strong></p>
        <p>O BarberBot software pode revisar estes termos de serviço do site a qualquer momento, sem aviso prévio. Ao usar este site, você concorda em ficar vinculado à versão atual desses termos de serviço.</p>

        <p><strong>Lei aplicável</strong></p>
        <p>Estes termos e condições são regidos e interpretados de acordo com as leis do BarberBot software e você se submete irrevogavelmente à jurisdição exclusiva dos tribunais naquele estado ou localidade.</p>
      </div>
      <div class="modal-footer dark-theme">
        <button type="button" class="btn btn-primary" data-dismiss="modal">Ok, entendi</button>
      </div>
    </div>
  </div>
</div>

<div id="privacidade" class="modal fade" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg modal-dialog-scrollable">
    <div class="modal-content dark-theme">
      <div class="modal-header dark-theme">
        <h4 class="modal-title font-weight-bold">Política de Privacidade</h4>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body" style="font-size: 0.95rem; line-height: 1.6;">
        <p>A sua privacidade é importante para nós. É política do <strong>BarberBot software</strong> respeitar a sua privacidade em relação a qualquer informação sua que possamos coletar no site BarberBot software, e outros sites que possuímos e operamos.</p>
        <p>Solicitamos informações pessoais apenas quando realmente precisamos delas para lhe fornecer um serviço. Fazemo-lo por meios justos e legais, com o seu conhecimento e consentimento. Também informamos por que estamos coletando e como será usado.</p>
        <p>Apenas retemos as informações coletadas pelo tempo necessário para fornecer o serviço solicitado. Quando armazenamos dados, protegemos dentro de meios comercialmente aceitáveis ​​para evitar perdas e roubos, bem como acesso, divulgação, cópia, uso ou modificação não autorizados.</p>
        <p>Não compartilhamos informações de identificação pessoal publicamente ou com terceiros, exceto quando exigido por lei.</p>
        <p>O nosso site pode ter links para sites externos que não são operados por nós. Esteja ciente de que não temos controle sobre o conteúdo e práticas desses sites e não podemos aceitar responsabilidade por suas respective políticas de privacidade.</p>
        <p>Você é livre para recusar a nossa solicitação de informações pessoais, entendendo que talvez não possamos fornecer alguns dos serviços desejados.</p>
        <p>O uso continuado de nosso site será considerado como aceitação de nossas práticas em torno de privacidade e informações pessoais. Se você tiver alguma dúvida sobre como lidamos com dados do usuário e informações pessoais, entre em contexto connosco.</p>
        
        <hr style="background-color: #444;">
        
        <h5 class="text-primary font-weight-bold">Publicidade e Cookies</h5>
        <p>O serviço Google AdSense que usamos para veicular publicidade usa um cookie DoubleClick para veicular anúncios mais relevantes em toda a Web e limitar o número de vezes que um determinado anúncio é exibido para você.</p>
        <p>Para mais informações sobre o Google AdSense, consulte as FAQs oficiais sobre privacidade do Google AdSense.</p>
        
        <hr style="background-color: #444;">
        
        <h5 class="text-primary font-weight-bold">Compromisso do Usuário</h5>
        <p>O usuário se compromete a fazer uso adequado dos conteúdos e da informação que o BarberBot software oferece no site e com caráter enunciativo, mas não limitativo:</p>
        <ul>
            <li><strong>A)</strong> Não se envolver in atividades que sejam ilegais ou contrárias à boa fé a à ordem pública;</li>
            <li><strong>B)</strong> Não difundir propaganda ou conteúdo de natureza racista, xenofóbica, jogos de sorte ou azar, qualquer tipo de pornografia ilegal, de apologia ao terrorismo ou contra os direitos humanos;</li>
            <li><strong>C)</strong> Não causar danos aos sistemas físicos (hardwares) e lógicos (softwares) do BarberBot software, de seus fornecedores ou terceiros.</li>
        </ul>
        
        <p><strong>Mais informações</strong><br>
        Esperemos que esteja esclarecido e, como mencionado anteriormente, se houver algo que você não tem certeza se precisa ou não, geralmente é mais seguro deixar os cookies ativados, caso interaja com um dos recursos que você usa em nosso site.</p>
        
        <p class="text-muted small mt-3">Esta política é efetiva a partir de <strong>29 Janeiro 2026</strong>.</p>
      </div>
      <div class="modal-footer dark-theme">
        <button type="button" class="btn btn-secondary" data-dismiss="modal">Fechar</button>
      </div>
    </div>
  </div>
</div>

<div id="empresa" class="modal fade" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-lg">
    <div class="modal-content dark-theme">
      <div class="modal-header dark-theme">
          <h5 class="modal-title">JACY CABELEIREIRO</h5>
          <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <div class="modal-body">
        <p>Especialista em cortes modernos e prótese capilar.</p>
        <iframe width="100%" height="400" loading="lazy" data-src="https://www.youtube.com/embed/fO_s7jlO1tU" src="about:blank" frameborder="0" allowfullscreen></iframe>
      </div>
    </div>
  </div>
</div>


<div id="videoModal" style="display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: rgba(0,0,0,0.9); z-index: 9999; align-items: center; justify-content: center; backdrop-filter: blur(5px);">
    <div style="position: relative; width: 90%; max-width: 900px; aspect-ratio: 16/9; background: #000; border-radius: 16px; overflow: hidden; box-shadow: 0 20px 50px rgba(0,0,0,0.5);">
        <button onclick="closeVideoModal()" style="position: absolute; top: -40px; right: 0; background: none; border: none; color: white; font-size: 2rem; cursor: pointer; z-index: 10000;"><i class="fa-solid fa-xmark"></i></button>
        <iframe id="youtubeFrame" width="100%" height="100%" src="" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>
    </div>
</div>

<script>
// PageSpeed Green: jQuery e Bootstrap continuam disponíveis, mas são carregados só após interação/ociosidade.
(function(){
  var carregado = false;
  var carregando = false;
  var fila = [];

  function addScript(src, cb){
    var s = document.createElement('script');
    s.src = src;
    s.async = false;
    s.onload = function(){ if (typeof cb === 'function') cb(); };
    s.onerror = function(){ if (typeof cb === 'function') cb(); };
    document.body.appendChild(s);
  }

  function finalizar(){
    carregado = true;
    carregando = false;

    var pendentes = fila.slice();
    fila = [];

    if (window.__bbJqCallbacks && window.__bbJqCallbacks.length) {
      pendentes = pendentes.concat(window.__bbJqCallbacks.slice());
      window.__bbJqCallbacks = [];
    }

    pendentes.forEach(function(cb){
      try { if (typeof cb === 'function') cb(); } catch(e) {}
    });
  }

  window.bbLoadCoreScripts = function(cb){
    if (typeof cb === 'function') fila.push(cb);
    if (carregado) { finalizar(); return; }
    if (carregando) return;

    carregando = true;
    addScript(<?php echo json_encode($__bb_footerAppPrefix . '/js/jquery-3.4.1.min.js?v=20260526-green'); ?>, function(){
      addScript(<?php echo json_encode($__bb_footerAppPrefix . '/js/bootstrap.js?v=20260526-green'); ?>, finalizar);
    });
  };

  function startCore(){ window.bbLoadCoreScripts(); }
  function idle(cb){
    if ('requestIdleCallback' in window) requestIdleCallback(cb, {timeout: 6500});
    else setTimeout(cb, 4500);
  }

  ['pointerdown','touchstart','keydown','scroll'].forEach(function(evt){
    window.addEventListener(evt, startCore, {once:true, passive:true});
  });

  window.addEventListener('load', function(){ idle(startCore); }, {once:true});
})();

function bbWhenJquery(callback){
  if (window.jQuery) { callback(window.jQuery); return; }
  window.__bbJqCallbacks = window.__bbJqCallbacks || [];
  window.__bbJqCallbacks.push(function(){
    if (window.jQuery) callback(window.jQuery);
  });
}
</script>


<script>
// Carrega o iframe do YouTube da modal Empresa apenas quando a modal for aberta.
bbWhenJquery(function($){
  $('#empresa').on('shown.bs.modal', function(){
    var iframe = this.querySelector('iframe[data-src]');
    if (iframe && (!iframe.getAttribute('src') || iframe.getAttribute('src') === 'about:blank')) {
      iframe.setAttribute('src', iframe.getAttribute('data-src'));
    }
  });
});
</script>

<script>
(function() {
  'use strict';

  // ✅ só roda se o index.php habilitar
  if (!window.BB_SHOW_LEAD_MODAL) return;

  var MODAL_ID = 'leadModal';
  var KEY_DONE = 'clienteCadastrado';

  function canShow() {
    if (localStorage.getItem(KEY_DONE) === 'true') return false;
    return true;
  }

  function openModal() {
    if (!document.getElementById(MODAL_ID)) return;
    if (typeof jQuery !== 'undefined') jQuery('#' + MODAL_ID).modal('show');
  }

  window.marcarClienteCadastrado = function() {
    localStorage.setItem(KEY_DONE, 'true');
  };

  window.hideLeadModal = function() {
    if (!document.getElementById(MODAL_ID)) return;
    if (typeof jQuery !== 'undefined') jQuery('#' + MODAL_ID).modal('hide');
  };

  bbWhenJquery(function($) {
    if (!canShow()) return;
    setTimeout(function() { openModal(); }, 15000);
  });
})();
</script>

<script>
bbWhenJquery(function($) {
  function formatBR(d) {
    d = d.replace(/\D/g, '').slice(0, 11);
    if (d.length <= 2) return d;
    if (d.length <= 6) return '(' + d.slice(0, 2) + ') ' + d.slice(2);
    if (d.length <= 10) return '(' + d.slice(0, 2) + ') ' + d.slice(2, 6) + '-' + d.slice(6);
    return '(' + d.slice(0, 2) + ') ' + d.slice(2, 7) + '-' + d.slice(7);
  }

  $('#leadZap, #telefone_rodape, #bbZapTelefoneFooter, #bbWhatsAppInputMask').on('input', function() {
    $(this).val(formatBR($(this).val()));
  });
});
</script>

<script>
bbWhenJquery(function($) {
  var telAdmin = "<?php echo htmlspecialchars($bb_zap_tel_modal, ENT_QUOTES, 'UTF-8'); ?>";

  function abriLinkWhatsAppExterno() {
      var urlZap = "https://api.whatsapp.com/send?phone=" + encodeURIComponent(telAdmin) + "&text=Ol%C3%A1,%20gostaria%20de%20saber%20mais%20informa%C3%A7%C3%B5es%20sobre%20o%20BarberBot%20Software!";
      window.open(urlZap, '_blank');
  }

  function enviarCadastro(formId, btnId, isZapModal) {
      var $form = $(formId);
      if ($form.length === 0) return;

      $form.on('submit', function(e) {
        e.preventDefault();
        var $btn = $(btnId || 'button[type=submit]', $form);
        var oldText = $btn.text();
        $btn.prop('disabled', true).text('Enviando...');

        $.ajax({
          url: <?php echo json_encode($bb_cadastrar_endpoint, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>,
          method: 'POST',
          data: $form.serialize(),
          success: function(resp) {
            var texto = (resp || '').replace(/\s+/g, ' ').trim();
            
            if (texto.indexOf('Cadastrado com Sucesso') === 0 || texto.indexOf('Salvo com Sucesso') === 0 || texto.indexOf('Você já está Cadastrado') === 0) {
                if(window.marcarClienteCadastrado) window.marcarClienteCadastrado();
                if(window.hideLeadModal) window.hideLeadModal();
                
                if (isZapModal) {
                    try {
                        var overlayNative = document.getElementById('bbZapModalCadastro');
                        if (overlayNative) overlayNative.classList.remove('bb-zap-modal-aberta');
                        document.documentElement.style.overflow = '';
                    } catch(err) {}
                    $('#bbWhatsAppModalOnboarding').modal('hide');
                    abriLinkWhatsAppExterno();
                } else {
                    alert('Cadastro realizado!');
                    window.location.href="<?php echo function_exists('bb_url_acesso') ? bb_url_acesso() : '/sistema/acesso'; ?>";
                }
            } else {
                alert('Erro: ' + texto);
            }
          },
          error: function() {
            alert('Erro de conexão.');
          },
          complete: function() {
            $btn.prop('disabled', false).text(oldText);
          }
        });
      });
  }

  enviarCadastro('#leadForm', '#leadBtn', false);
  enviarCadastro('#form_cadastro', '.footer-btn', false);
  enviarCadastro('#bbZapCadastroFormFooter', '#bbZapContinuarFooter', true);
  enviarCadastro('#bbWhatsAppFormOnboarding', '.bb-modal-btn-confirmar', true);
});
</script>


<?php
// ===== reCAPTCHA v3 - HOME PRINCIPAL =====
// A chave pública é lida somente do PDO já confirmado para o contexto atual:
// - TENANT: banco individual da barbearia.
// - SITE/MASTER: banco Global/Administrativo.
if (!function_exists('bb_footer_recaptcha_home_site_key_v3')) {
    function bb_footer_recaptcha_home_site_key_v3(): string {
        $limparKey = function($valor) {
            $valor = trim((string)$valor);
            if ($valor === '') {
                return '';
            }

            return preg_match('/^[A-Za-z0-9_-]+$/', $valor) ? $valor : '';
        };

        if (defined('RECAPTCHA_SITE_KEY_V3')) {
            $siteKey = $limparKey(RECAPTCHA_SITE_KEY_V3);
            if ($siteKey !== '') {
                return $siteKey;
            }
        }

        if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
            return '';
        }

        try {
            $stmt = $GLOBALS['pdo']->query(
                "SELECT recaptcha_site_key_v3
                   FROM config
                  WHERE recaptcha_site_key_v3 IS NOT NULL
                    AND TRIM(recaptcha_site_key_v3) <> ''
                  ORDER BY id DESC
                  LIMIT 1"
            );

            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            return is_array($row)
                ? $limparKey($row['recaptcha_site_key_v3'] ?? '')
                : '';
        } catch (Throwable $e) {
            return '';
        }
    }
}

$bb_footer_path_recaptcha = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$bb_footer_is_home_recaptcha = (
    $bb_footer_path_recaptcha === '/'
    || $bb_footer_path_recaptcha === '/index.php'
);

$bb_footer_recaptcha_site_key = '';
if ($bb_footer_is_home_recaptcha) {
    $bb_footer_recaptcha_site_key = bb_footer_recaptcha_home_site_key_v3();
}
?>
<?php if ($bb_footer_recaptcha_site_key !== ''): ?>
<!-- BB_FOOTER_RECAPTCHA_HOME_20260707 -->
<script id="bb-recaptcha-home-api" src="https://www.google.com/recaptcha/api.js?render=<?php echo htmlspecialchars($bb_footer_recaptcha_site_key, ENT_QUOTES, 'UTF-8'); ?>" async defer></script>
<style>
  .grecaptcha-badge{
    visibility: visible !important;
    opacity: 1 !important;
    display: block !important;
    z-index: 2147483647 !important;
    bottom: 84px !important;
  }
</style>
<script>
(function(){
  var SITE_KEY = <?php echo json_encode($bb_footer_recaptcha_site_key, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
  var ACTION = 'homepage';

  function aplicarToken(token){
    var forms = document.querySelectorAll('form');
    for (var i = 0; i < forms.length; i++) {
      var input = forms[i].querySelector('input[name="recaptcha_token"]');
      if (!input) {
        input = document.createElement('input');
        input.type = 'hidden';
        input.name = 'recaptcha_token';
        input.id = 'recaptcha_token';
        forms[i].appendChild(input);
      }
      input.value = token || '';
    }
  }

  function executarRecaptcha(){
    if (!SITE_KEY || typeof grecaptcha === 'undefined' || !grecaptcha.ready || typeof grecaptcha.execute !== 'function') {
      return;
    }

    grecaptcha.ready(function(){
      grecaptcha.execute(SITE_KEY, {action: ACTION}).then(function(token){
        aplicarToken(token || '');
      }).catch(function(){
        aplicarToken('');
      });
    });
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function(){
      executarRecaptcha();
      setTimeout(executarRecaptcha, 1200);
    });
  } else {
    executarRecaptcha();
    setTimeout(executarRecaptcha, 1200);
  }

  setInterval(executarRecaptcha, 90000);
})();
</script>
<?php endif; ?>

<script defer src="<?php echo htmlspecialchars($__bb_footerAppPrefix . '/marketplace/custom.js?v=20260526-green', ENT_QUOTES, 'UTF-8'); ?>"></script>
</body>
</html>
