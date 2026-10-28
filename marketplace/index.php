<?php
require_once __DIR__ . '/marketplace/header.php';

/**
 * reCAPTCHA v3 (usuário final):
 * - Apenas carrega o arquivo de configuração (se existir) para termos a SITE KEY.
 * - Não altera a lógica do site.
 */
$__bb_recaptcha_file = '';
$__bb_recaptcha_candidates = [
    __DIR__ . '/config/recaptcha.php',
    __DIR__ . '/recaptcha.php',
];
foreach ($__bb_recaptcha_candidates as $__bb_rc) {
    if (is_file($__bb_rc)) { $__bb_recaptcha_file = $__bb_rc; break; }
}
if ($__bb_recaptcha_file !== '') {
    require_once $__bb_recaptcha_file;
}

/**
 * Configuração pública da HOME PRINCIPAL.
 * A página https://jc.tec.br/ deve buscar os dados institucionais no MASTER:
 * Banco de dados: jctecbr_master
 * Tabela: config
 * Mantém o padrão do sistema e apenas garante que o rodapé receba endereço,
 * contatos, nome, imagens e texto_rodape da configuração principal.
 */
if (!function_exists('bb_home_config_valor')) {
    function bb_home_config_valor($dados, array $chaves) {
        if (!is_array($dados)) {
            return '';
        }

        foreach ($chaves as $chave) {
            if (isset($dados[$chave]) && trim((string)$dados[$chave]) !== '') {
                return trim((string)$dados[$chave]);
            }
        }

        return '';
    }
}

if (!function_exists('bb_home_config_img')) {
    function bb_home_config_img($valor) {
        $valor = trim((string)$valor);
        if ($valor === '') {
            return '';
        }

        if (preg_match('~^https?://~i', $valor) || substr($valor, 0, 1) === '/') {
            return $valor;
        }

        if (strpos($valor, 'sistema/img/') === 0 || strpos($valor, 'images/') === 0 || strpos($valor, 'img/') === 0) {
            return $valor;
        }

        return 'sistema/img/' . ltrim($valor, '/');
    }
}

if (!function_exists('bb_home_master_pdo_candidatas')) {
    function bb_home_master_pdo_candidatas() {
        $candidatas = [];

        $add = function($host, $db, $user, $pass = '') use (&$candidatas) {
            $host = trim((string)$host);
            $db = trim((string)$db);
            $user = trim((string)$user);

            if ($host === '' || $db === '' || $user === '') {
                return;
            }

            $chave = $host . '|' . $db . '|' . $user;
            if (!isset($candidatas[$chave])) {
                $candidatas[$chave] = [
                    'host' => $host,
                    'db' => $db,
                    'user' => $user,
                    'pass' => (string)$pass
                ];
            }
        };

        $marketplaceConfig = __DIR__ . '/config/marketplace.php';
        if (is_file($marketplaceConfig)) {
            $cfg = require $marketplaceConfig;

            if (is_array($cfg)) {
                // HOME PRINCIPAL: a configuração pública deve vir do MASTER do sistema.
                // No config/marketplace.php, o bloco "master" é o banco do marketplace (tenants).
                // Por isso, prioriza "auth_master", que aponta para jctecbr_master.config.
                if (!empty($cfg['auth_master']) && is_array($cfg['auth_master'])) {
                    $add(
                        $cfg['auth_master']['host'] ?? 'localhost',
                        $cfg['auth_master']['db'] ?? 'jctecbr_master',
                        $cfg['auth_master']['user'] ?? '',
                        $cfg['auth_master']['pass'] ?? ''
                    );
                }

                if (!empty($cfg['master']) && is_array($cfg['master'])) {
                    $masterDb = (string)($cfg['master']['db'] ?? '');
                    if ($masterDb === 'jctecbr_master') {
                        $add(
                            $cfg['master']['host'] ?? 'localhost',
                            $cfg['master']['db'] ?? 'jctecbr_master',
                            $cfg['master']['user'] ?? '',
                            $cfg['master']['pass'] ?? ''
                        );
                    }
                }

                if (!empty($cfg['tenant']) && is_array($cfg['tenant'])) {
                    $add(
                        $cfg['tenant']['host'] ?? 'localhost',
                        'jctecbr_master',
                        $cfg['tenant']['user'] ?? '',
                        $cfg['tenant']['pass'] ?? ''
                    );
                }

                $add(
                    $cfg['host'] ?? '',
                    $cfg['db'] ?? 'jctecbr_master',
                    $cfg['user'] ?? '',
                    $cfg['pass'] ?? ''
                );
            }
        }

        $add(
            $GLOBALS['host'] ?? ($GLOBALS['servidor'] ?? 'localhost'),
            'jctecbr_master',
            $GLOBALS['usuario'] ?? ($GLOBALS['user'] ?? ''),
            $GLOBALS['senha'] ?? ($GLOBALS['pass'] ?? '')
        );

        return array_values($candidatas);
    }
}

if (!function_exists('bb_home_ler_config_master')) {
    function bb_home_ler_config_master() {
        if (isset($GLOBALS['pdo']) && ($GLOBALS['pdo'] instanceof PDO)) {
            try {
                $stmt = $GLOBALS['pdo']->query("SELECT * FROM `jctecbr_master`.`config` ORDER BY id DESC LIMIT 1");
                $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
                if (is_array($row) && !empty($row)) {
                    return $row;
                }
            } catch (Throwable $e) {
                // tenta as demais conexões abaixo
            }

            try {
                $stmtDb = $GLOBALS['pdo']->query("SELECT DATABASE() AS banco_atual");
                $rowDb = $stmtDb ? $stmtDb->fetch(PDO::FETCH_ASSOC) : false;
                $bancoAtual = is_array($rowDb) ? (string)($rowDb['banco_atual'] ?? '') : '';

                if (strtolower($bancoAtual) === 'jctecbr_master') {
                    $stmt = $GLOBALS['pdo']->query("SELECT * FROM config ORDER BY id DESC LIMIT 1");
                    $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
                    if (is_array($row) && !empty($row)) {
                        return $row;
                    }
                }
            } catch (Throwable $e) {
                // não quebra a home
            }
        }

        foreach (bb_home_master_pdo_candidatas() as $cfg) {
            try {
                $pdoMasterHome = new PDO(
                    'mysql:host=' . $cfg['host'] . ';dbname=' . $cfg['db'] . ';charset=utf8mb4',
                    $cfg['user'],
                    $cfg['pass'],
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                    ]
                );

                $stmt = $pdoMasterHome->query("SELECT * FROM config ORDER BY id DESC LIMIT 1");
                $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
                if (is_array($row) && !empty($row)) {
                    return $row;
                }
            } catch (Throwable $e) {
                // tenta a próxima candidata
            }
        }

        return [];
    }
}

$__bb_home_config_master = bb_home_ler_config_master();
if (!empty($__bb_home_config_master)) {
    $nomeConfig = bb_home_config_valor($__bb_home_config_master, ['nome_sistema', 'nome']);
    $emailConfig = bb_home_config_valor($__bb_home_config_master, ['email', 'email_sistema']);
    $telefoneFixoConfig = bb_home_config_valor($__bb_home_config_master, ['telefone_fixo', 'telefone', 'tel_fixo']);
    $telefoneWhatsappConfig = bb_home_config_valor($__bb_home_config_master, ['telefone_whatsapp', 'whatsapp_sistema', 'tel_whatsapp']);
    $enderecoConfig = bb_home_config_valor($__bb_home_config_master, ['endereco', 'endereco_sistema']);
    $logoConfig = bb_home_config_valor($__bb_home_config_master, ['logo_sistema', 'logo']);
    $iconeConfig = bb_home_config_valor($__bb_home_config_master, ['icone', 'favicon']);
    $logoRelConfig = bb_home_config_valor($__bb_home_config_master, ['logo_rel']);
    $textoRodapeConfig = bb_home_config_valor($__bb_home_config_master, ['texto_rodape', 'texto_rodape_sistema', 'textoRodape', 'rodape', 'descricao_rodape']);

    if ($nomeConfig !== '') {
        $nome = $nomeConfig;
        $nome_sistema = $nomeConfig;
        $nomeSistema = $nomeConfig;
    }

    if ($emailConfig !== '') {
        $email = $emailConfig;
        $email_sistema = $emailConfig;
    }

    if ($telefoneFixoConfig !== '') {
        $telefone = $telefoneFixoConfig;
        $telefone_fixo = $telefoneFixoConfig;
        $telefone_sistema = $telefoneFixoConfig;
    }

    if ($telefoneWhatsappConfig !== '') {
        $whatsapp = $telefoneWhatsappConfig;
        $telefone_whatsapp = $telefoneWhatsappConfig;
        $whatsapp_sistema = $telefoneWhatsappConfig;
        $tel_whatsapp = $telefoneWhatsappConfig;
    }

    if ($enderecoConfig !== '') {
        $endereco = $enderecoConfig;
        $endereco_sistema = $enderecoConfig;
        $enderecoSistema = $enderecoConfig;
    }

    if ($logoConfig !== '') {
        $logo = $logoConfig;
        $logo_sistema = bb_home_config_img($logoConfig);
        $logoSistema = $logo_sistema;
    }

    if ($iconeConfig !== '') {
        $icone = $iconeConfig;
        $icone_sistema = bb_home_config_img($iconeConfig);
    }

    if ($logoRelConfig !== '') {
        $logo_rel = $logoRelConfig;
        $logo_rel_sistema = bb_home_config_img($logoRelConfig);
    }

    if ($textoRodapeConfig !== '') {
        $texto_rodape = $textoRodapeConfig;
        $texto_rodape_sistema = $textoRodapeConfig;
        $textoRodape = $textoRodapeConfig;
        $rodape = $textoRodapeConfig;
        $descricao_rodape = $textoRodapeConfig;

        // Mantém compatibilidade com footer.php e demais includes que leem via $GLOBALS.
        $GLOBALS['texto_rodape'] = $textoRodapeConfig;
        $GLOBALS['texto_rodape_sistema'] = $textoRodapeConfig;
        $GLOBALS['textoRodape'] = $textoRodapeConfig;
        $GLOBALS['rodape'] = $textoRodapeConfig;
        $GLOBALS['descricao_rodape'] = $textoRodapeConfig;
    }
}
?>

<style>
.pain-section,
.features,
.about-section,
.experience-section,
.google-reviews-section,
.pricing{
  content-visibility:auto;
  contain-intrinsic-size: 1px 1000px;
}
.hero video,
.feature-img video{
  width:100%;
  height:auto;
  display:block;
}

/* ============================================================
   Ajuste visual - seção dos 6 cards informativos
   Mantém a lógica do sistema e altera somente a distribuição visual.
   Desktop: 3 cards por linha.
   Tablet: 2 cards por linha.
   Celular: 1 card por linha.
   ============================================================ */
.pain-section .pain-grid{
  display:grid !important;
  grid-template-columns:repeat(3, minmax(0, 1fr)) !important;
  gap:30px !important;
  align-items:stretch;
  max-width:1420px;
  width:100%;
  margin:45px auto 0 auto;
}

.pain-section .pain-card{
  width:100% !important;
  max-width:none !important;
  min-width:0;
  min-height:220px;
  display:flex;
  flex-direction:column;
  align-items:center;
  justify-content:center;
}

@media (max-width: 991px){
  .pain-section .pain-grid{
    grid-template-columns:repeat(2, minmax(0, 1fr)) !important;
  }
}

@media (max-width: 575px){
  .pain-section .pain-grid{
    grid-template-columns:1fr !important;
  }
}


/* ============================================================
   Ajuste visual - seção de preços
   Mantém a lógica do sistema e altera somente a apresentação.
   Desktop: 3 planos por linha.
   Tablet: 2 planos por linha.
   Celular: 1 plano por linha.
   ============================================================ */
.pricing .price-cards{
  display:grid !important;
  grid-template-columns:repeat(3, minmax(0, 350px)) !important;
  gap:30px !important;
  justify-content:center !important;
  align-items:stretch !important;
}

.pricing .price-card{
  width:100% !important;
  max-width:none !important;
  min-width:0;
  display:flex;
  flex-direction:column;
}

.pricing .price-card .btn{
  margin-top:auto;
}

@media (max-width: 1199px){
  .pricing .price-cards{
    grid-template-columns:repeat(2, minmax(0, 350px)) !important;
  }
}

@media (max-width: 767px){
  .pricing .price-cards{
    grid-template-columns:minmax(0, 350px) !important;
  }
}

@media (max-width: 420px){
  .pricing .price-cards{
    grid-template-columns:1fr !important;
  }
}


/* ============================================================
   Ajuste visual - recursos não inclusos nos planos
   Mantém a lógica do sistema: altera somente ícone e texto visual.
   ============================================================ */
.pricing .check-list li.bb-recurso-nao-incluso{
  color:rgba(255,255,255,.86) !important;
}

.pricing .check-list li.bb-recurso-nao-incluso i{
  width:18px;
  height:18px;
  min-width:18px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  margin-right:10px;
  border-radius:50%;
  color:#ef4444 !important;
  background:rgba(239,68,68,.10);
  border:1px solid rgba(239,68,68,.35);
  font-size:.78rem;
  line-height:1;
}


/* ============================================================
   PageSpeed Green - Hero leve acima da dobra
   O vídeo pesado fica preservado, mas só carrega após interação do usuário.
   ============================================================ */
.laptop-screen-wide{position:relative;overflow:hidden;}
.bb-hero-video-placeholder{
  position:absolute;inset:0;z-index:1;display:flex;align-items:center;justify-content:center;
  background:
    radial-gradient(circle at 75% 18%, rgba(59,130,246,.45), transparent 34%),
    linear-gradient(135deg,#020617 0%,#08265d 55%,#0f172a 100%);
  color:#fff;transition:opacity .25s ease;
}
.bb-placeholder-dashboard{width:86%;max-width:540px;}
.bb-placeholder-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;}
.bb-placeholder-logo{display:inline-flex;align-items:center;gap:8px;font-size:1.25rem;font-weight:900;color:#bfdbfe;}
.bb-placeholder-pill{font-size:.75rem;font-weight:800;color:#dbeafe;background:rgba(255,255,255,.10);border:1px solid rgba(147,197,253,.22);padding:6px 10px;border-radius:999px;}
.bb-placeholder-card{background:rgba(2,6,23,.44);border:1px solid rgba(147,197,253,.20);border-radius:16px;padding:18px;box-shadow:0 20px 55px rgba(0,0,0,.28);}
.bb-placeholder-title{font-size:1.15rem;font-weight:900;margin:0 0 12px;}
.bb-placeholder-line{height:10px;border-radius:999px;background:rgba(219,234,254,.38);margin:10px 0;}
.bb-placeholder-line.w1{width:92%;}.bb-placeholder-line.w2{width:68%;}.bb-placeholder-line.w3{width:78%;}
.bb-placeholder-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:10px;margin-top:15px;}
.bb-placeholder-mini{height:44px;border-radius:12px;background:rgba(37,99,235,.36);border:1px solid rgba(147,197,253,.16);}
.laptop-screen-wide video{position:absolute;inset:0;z-index:2;opacity:0;transition:opacity .25s ease;}
.laptop-screen-wide.bb-screen-video-loaded .bb-hero-video-placeholder{opacity:0;pointer-events:none;}
.laptop-screen-wide.bb-screen-video-loaded video{opacity:1;}
@media (max-width:768px){.bb-hero-video-placeholder{display:none;}}

/* ============================================================
   Ajuste de conversão - prova de autoridade e Plano Business
   Mantém a lógica do sistema e altera somente conteúdo/apresentação da home.
   ============================================================ */
.bb-founder-proof,
.bb-business-consultoria{
  background:#020617;
  color:#f8fafc;
  padding:64px 0;
  position:relative;
  overflow:hidden;
}

.bb-founder-proof::before,
.bb-business-consultoria::before{
  content:"";
  position:absolute;
  inset:0;
  background:
    radial-gradient(circle at 15% 10%, rgba(37,99,235,.22), transparent 30%),
    radial-gradient(circle at 85% 18%, rgba(16,185,129,.14), transparent 28%);
  pointer-events:none;
}

.bb-founder-wrap,
.bb-business-wrap{
  max-width:1180px;
  width:92%;
  margin:0 auto;
  position:relative;
  z-index:2;
}

.bb-founder-grid,
.bb-business-grid{
  display:grid;
  grid-template-columns:1.05fr .95fr;
  gap:34px;
  align-items:center;
}

.bb-founder-badge,
.bb-business-badge{
  display:inline-flex;
  align-items:center;
  gap:8px;
  padding:9px 14px;
  border-radius:999px;
  background:rgba(37,99,235,.13);
  color:#bfdbfe;
  border:1px solid rgba(96,165,250,.24);
  font-size:.88rem;
  font-weight:800;
  margin-bottom:16px;
}

.bb-founder-title,
.bb-business-title{
  color:#f8fafc;
  font-size:clamp(2rem, 4vw, 3.2rem);
  line-height:1.12;
  font-weight:900;
  margin:0 0 18px;
}

.bb-founder-title span,
.bb-business-title span{
  color:#60a5fa;
}

.bb-founder-text,
.bb-business-text{
  color:#cbd5e1;
  font-size:1.08rem;
  line-height:1.8;
  margin:0 0 22px;
}

.bb-founder-actions{
  display:flex;
  flex-wrap:wrap;
  gap:12px;
  margin-top:24px;
}

.bb-founder-card,
.bb-business-panel{
  background:rgba(15,23,42,.86);
  border:1px solid rgba(148,163,184,.18);
  border-radius:22px;
  padding:28px;
  box-shadow:0 26px 70px rgba(0,0,0,.28);
}

.bb-founder-stats{
  display:grid;
  grid-template-columns:repeat(2, minmax(0, 1fr));
  gap:14px;
}

.bb-founder-stat,
.bb-business-item{
  background:#0f172a;
  border:1px solid rgba(148,163,184,.14);
  border-radius:16px;
  padding:18px;
}

.bb-founder-stat strong{
  display:block;
  color:#60a5fa;
  font-size:2rem;
  line-height:1;
  margin-bottom:8px;
}

.bb-founder-stat span,
.bb-business-item span{
  display:block;
  color:#e2e8f0;
  font-weight:800;
  margin-bottom:6px;
}

.bb-founder-stat p,
.bb-business-item p{
  color:#94a3b8;
  margin:0;
  font-size:.95rem;
  line-height:1.55;
}

.bb-business-list{
  display:grid;
  grid-template-columns:repeat(2, minmax(0, 1fr));
  gap:14px;
}

.bb-business-item i{
  color:#22c55e;
  margin-right:8px;
}

.bb-hero-trust{
  display:flex;
  flex-wrap:wrap;
  gap:10px;
  margin:20px 0 0;
}

.bb-hero-trust span{
  display:inline-flex;
  align-items:center;
  gap:7px;
  padding:8px 12px;
  border-radius:999px;
  background:rgba(15,23,42,.72);
  border:1px solid rgba(148,163,184,.20);
  color:#dbeafe;
  font-size:.88rem;
  font-weight:700;
}

@media (max-width: 900px){
  .bb-founder-grid,
  .bb-business-grid{
    grid-template-columns:1fr;
  }
  .bb-founder-stats,
  .bb-business-list{
    grid-template-columns:1fr;
  }
}


/* ============================================================
   ORDEM DE CORES - SEÇÃO POR SEÇÃO ATÉ O FOOTER
   1ª azul claro suave | 2ª azul escuro | alternância contínua
   Mantém a lógica PHP/JS intacta: ajuste somente visual.
   ============================================================ */
:root{
  --bb-azul-claro-1:#eff8ff;
  --bb-azul-claro-2:#dbeafe;
  --bb-azul-claro-3:#bfdbfe;
  --bb-azul-escuro-1:#020617;
  --bb-azul-escuro-2:#061a3d;
  --bb-azul-escuro-3:#0b2f6d;
  --bb-azul-texto:#08265d;
  --bb-azul-titulo:#061a3d;
  --bb-azul-destaque:#2563eb;
  --bb-borda-clara:rgba(37,99,235,.16);
  --bb-borda-escura:rgba(147,197,253,.18);
  --bb-sombra-clara:0 22px 55px rgba(37,99,235,.14);
  --bb-sombra-escura:0 26px 70px rgba(0,0,0,.30);
}

html,
body{
  background:linear-gradient(180deg,#eff8ff 0%,#dbeafe 34%,#061a3d 100%) !important;
  color:var(--bb-azul-texto) !important;
}

body::before{
  background:
    linear-gradient(90deg, rgba(37,99,235,.055) 1px, transparent 1px),
    linear-gradient(180deg, rgba(37,99,235,.045) 1px, transparent 1px) !important;
  opacity:.24 !important;
}

/* 1ª SEÇÃO: HERO - AZUL CLARO SUAVE DEGRADÊ */
.hero{
  background:
    radial-gradient(circle at 82% 14%, rgba(96,165,250,.34) 0%, transparent 34%),
    radial-gradient(circle at 12% 12%, rgba(14,165,233,.22) 0%, transparent 32%),
    linear-gradient(135deg,#f8fcff 0%,#eaf5ff 45%,#bfdbfe 100%) !important;
  color:var(--bb-azul-texto) !important;
  border-bottom:1px solid var(--bb-borda-clara) !important;
  box-shadow:inset 0 -80px 130px rgba(37,99,235,.08) !important;
}
.hero-text h1,
.hero-text h1 *{
  color:var(--bb-azul-titulo) !important;
  text-shadow:none !important;
}
.hero-text h1 span,
.hero-text h1 span *{
  color:#1d4ed8 !important;
}
.hero-text p{
  color:#16406d !important;
}
.hero-text p i,
.hero-text .fa,
.hero-text .fa-solid{
  color:#1d4ed8 !important;
}
.bb-hero-trust span{
  background:rgba(255,255,255,.72) !important;
  border:1px solid rgba(37,99,235,.18) !important;
  color:#0f3a66 !important;
  box-shadow:0 12px 30px rgba(37,99,235,.10) !important;
}
.hero-img{
  background:linear-gradient(145deg,rgba(255,255,255,.72),rgba(191,219,254,.42)) !important;
  border:1px solid rgba(37,99,235,.18) !important;
  box-shadow:var(--bb-sombra-clara) !important;
}

/* 2ª SEÇÃO: PROVA DO FUNDADOR - AZUL ESCURO DEGRADÊ */
.bb-founder-proof{
  --text-light:#f8fbff;
  --text-gray:#c7d8f6;
  background:
    radial-gradient(circle at 16% 8%, rgba(59,130,246,.28) 0%, transparent 34%),
    radial-gradient(circle at 88% 20%, rgba(14,165,233,.16) 0%, transparent 32%),
    linear-gradient(135deg,#020617 0%,#061a3d 50%,#0b2f6d 100%) !important;
  color:#f8fbff !important;
  border-top:1px solid var(--bb-borda-escura) !important;
  border-bottom:1px solid rgba(147,197,253,.12) !important;
}
.bb-founder-proof::before{
  background:
    radial-gradient(circle at 15% 10%, rgba(37,99,235,.30), transparent 30%),
    radial-gradient(circle at 85% 18%, rgba(56,189,248,.16), transparent 28%) !important;
}
.bb-founder-title,
.bb-founder-title *{
  color:#f8fbff !important;
}
.bb-founder-title span,
.bb-founder-badge{
  color:#93c5fd !important;
}
.bb-founder-text{
  color:#dbeafe !important;
}
.bb-founder-card,
.bb-founder-stat{
  background:rgba(15,23,42,.78) !important;
  border-color:rgba(147,197,253,.18) !important;
  box-shadow:var(--bb-sombra-escura) !important;
}

/* 3ª SEÇÃO: DORES - AZUL CLARO SUAVE DEGRADÊ */
.pain-section{
  --text-light:#08265d;
  --text-gray:#315a84;
  background:
    radial-gradient(circle at 50% 0%, rgba(59,130,246,.20) 0%, transparent 34%),
    linear-gradient(180deg,#f8fcff 0%,#e0f2fe 54%,#bfdbfe 100%) !important;
  color:var(--bb-azul-texto) !important;
  border-top:1px solid var(--bb-borda-clara) !important;
  border-bottom:1px solid var(--bb-borda-clara) !important;
}
.pain-section .section-title,
.pain-section .section-subtitle{
  color:var(--bb-azul-texto) !important;
}
.pain-card{
  background:linear-gradient(180deg,rgba(255,255,255,.90) 0%,rgba(239,248,255,.82) 100%) !important;
  border:1px solid rgba(37,99,235,.18) !important;
  box-shadow:var(--bb-sombra-clara) !important;
}
.pain-card h3{
  color:#08265d !important;
}
.pain-card p{
  color:#315a84 !important;
}
.pain-icon{
  color:#2563eb !important;
  text-shadow:0 10px 24px rgba(37,99,235,.22) !important;
}

/* 4ª SEÇÃO: FUNCIONALIDADES - AZUL ESCURO DEGRADÊ */
.features{
  --text-light:#f8fbff;
  --text-gray:#c7d8f6;
  background:
    radial-gradient(circle at 10% 0%, rgba(37,99,235,.22) 0%, transparent 36%),
    linear-gradient(180deg,#020617 0%,#061a3d 56%,#0b1f44 100%) !important;
  color:#f8fbff !important;
  border-top:1px solid rgba(147,197,253,.12) !important;
  border-bottom:1px solid rgba(147,197,253,.12) !important;
}
.feature-container{
  background:linear-gradient(180deg,rgba(13,59,142,.28) 0%,rgba(2,6,23,.42) 100%) !important;
  border:1px solid rgba(147,197,253,.16) !important;
  box-shadow:var(--bb-sombra-escura) !important;
}
.feature-text h2,
.feature-text h2 *{
  color:#f8fbff !important;
}
.feature-text h2 span{
  color:#93c5fd !important;
}
.feature-text p,
.features .check-list li{
  color:#dbeafe !important;
}
.phone-frame{
  border-color:#0b2f6d !important;
  box-shadow:0 25px 70px rgba(37,99,235,.20),0 25px 50px -12px rgba(0,0,0,.50) !important;
}
.phone-notch{
  background:#0b2f6d !important;
}

/* 5ª SEÇÃO: SOBRE - AZUL CLARO SUAVE DEGRADÊ */
.about-section{
  --text-light:#08265d;
  --text-gray:#315a84;
  background:
    radial-gradient(circle at 90% 5%, rgba(59,130,246,.18) 0%, transparent 35%),
    linear-gradient(180deg,#f8fcff 0%,#eaf5ff 52%,#dbeafe 100%) !important;
  color:var(--bb-azul-texto) !important;
}
.about-title,
.about-title *{
  color:#08265d !important;
}
.about-title span{
  color:#1d4ed8 !important;
}
.about-text,
.about-card p{
  color:#315a84 !important;
}
.about-card{
  background:rgba(255,255,255,.82) !important;
  border:1px solid rgba(37,99,235,.16) !important;
  box-shadow:var(--bb-sombra-clara) !important;
}
.about-card h3{
  color:#08265d !important;
}
.about-image img{
  border-color:rgba(37,99,235,.18) !important;
  box-shadow:var(--bb-sombra-clara) !important;
}

/* 6ª SEÇÃO: EXPERIÊNCIA - AZUL ESCURO DEGRADÊ */
.experience-section{
  --text-light:#f8fbff;
  --text-gray:#c7d8f6;
  --secondary:#60a5fa;
  --accent:#38bdf8;
  --success:#10b981;
  background:
    radial-gradient(circle at 18% 0%, rgba(37,99,235,.28) 0%, transparent 35%),
    linear-gradient(180deg,#020617 0%,#061a3d 56%,#0b2f6d 100%) !important;
  color:#f8fbff !important;
}
.experience-section h2,
.experience-section h2 *{
  color:#f8fbff !important;
}
.experience-section p,
.experience-section .mini-stat div:last-child,
.experience-section .trust-indicators span{
  color:#dbeafe !important;
}
.experience-section .visitas-counter,
.experience-section .mini-stat,
.experience-section .trust-indicators > div{
  background:rgba(15,23,42,.78) !important;
  border:1px solid rgba(147,197,253,.14) !important;
  box-shadow:0 16px 42px rgba(0,0,0,.24) !important;
}

/* 7ª SEÇÃO: AVALIAÇÕES GOOGLE - AZUL CLARO SUAVE DEGRADÊ */
.google-reviews-section{
  --text-light:#08265d;
  --text-gray:#315a84;
  --secondary:#2563eb;
  --accent:#1d4ed8;
  --success:#059669;
  background:
    radial-gradient(circle at 14% 10%, rgba(59,130,246,.20) 0%, transparent 34%),
    linear-gradient(180deg,#f8fcff 0%,#e0f2fe 58%,#bfdbfe 100%) !important;
  color:var(--bb-azul-texto) !important;
}
.google-reviews-section h2,
.google-reviews-section h3,
.google-reviews-section h4,
.google-reviews-section p,
.google-reviews-section span,
.google-reviews-section strong{
  color:inherit;
}
.google-reviews-section .col-md-6 > div,
.google-reviews-section .item > div{
  background:rgba(255,255,255,.86) !important;
  border:1px solid rgba(37,99,235,.16) !important;
  box-shadow:var(--bb-sombra-clara) !important;
}
.google-reviews-section a{
  background:linear-gradient(135deg,#2563eb 0%,#1d4ed8 100%) !important;
  color:#ffffff !important;
}

/* 8ª SEÇÃO: BUSINESS - AZUL ESCURO DEGRADÊ */
.bb-business-consultoria{
  --text-light:#f8fbff;
  --text-gray:#c7d8f6;
  background:
    radial-gradient(circle at 84% 10%, rgba(14,165,233,.18) 0%, transparent 35%),
    radial-gradient(circle at 14% 12%, rgba(37,99,235,.24) 0%, transparent 34%),
    linear-gradient(135deg,#020617 0%,#061a3d 50%,#0b2f6d 100%) !important;
  color:#f8fbff !important;
  border-top:1px solid rgba(147,197,253,.12) !important;
  border-bottom:1px solid rgba(147,197,253,.12) !important;
}
.bb-business-consultoria::before{
  background:
    radial-gradient(circle at 15% 10%, rgba(37,99,235,.30), transparent 30%),
    radial-gradient(circle at 85% 18%, rgba(56,189,248,.16), transparent 28%) !important;
}
.bb-business-title,
.bb-business-title *{
  color:#f8fbff !important;
}
.bb-business-title span,
.bb-business-badge{
  color:#93c5fd !important;
}
.bb-business-text{
  color:#dbeafe !important;
}
.bb-business-panel,
.bb-business-item{
  background:rgba(15,23,42,.78) !important;
  border-color:rgba(147,197,253,.18) !important;
  box-shadow:var(--bb-sombra-escura) !important;
}

/* 9ª SEÇÃO: PLANOS - AZUL CLARO SUAVE DEGRADÊ */
.pricing{
  --text-light:#08265d;
  --text-gray:#315a84;
  --accent:#1d4ed8;
  background:
    radial-gradient(circle at 50% 0%, rgba(59,130,246,.22) 0%, transparent 36%),
    linear-gradient(180deg,#f8fcff 0%,#eaf5ff 50%,#dbeafe 100%) !important;
  color:var(--bb-azul-texto) !important;
}
.pricing .section-title,
.pricing .section-subtitle,
.pricing .toggle-container span,
.pricing .price,
.pricing .price span{
  color:var(--bb-azul-texto) !important;
}
.pricing .price-card{
  background:rgba(255,255,255,.88) !important;
  border:1px solid rgba(37,99,235,.16) !important;
  box-shadow:var(--bb-sombra-clara) !important;
}
.pricing .price-card.popular{
  border-color:rgba(37,99,235,.48) !important;
  box-shadow:0 28px 70px rgba(37,99,235,.22) !important;
}
.pricing .check-list li{
  color:#315a84 !important;
}
.pricing .check-list li.bb-recurso-nao-incluso{
  color:#64748b !important;
}
.pricing .bb-ads-transparency-note{
  background:rgba(255,255,255,.76) !important;
  border:1px solid rgba(37,99,235,.16) !important;
  color:#315a84 !important;
}
.pricing .bb-ads-transparency-note strong{
  color:#08265d !important;
}

/* FOOTER: FECHAMENTO EM AZUL ESCURO DEGRADÊ */
.footer-dark{
  --text-light:#f8fbff;
  --text-gray:#c7d8f6;
  background:
    radial-gradient(circle at 50% 0%, rgba(37,99,235,.22) 0%, transparent 34%),
    linear-gradient(180deg,#061a3d 0%,#020617 100%) !important;
  color:#ffffff !important;
  border-top:1px solid rgba(147,197,253,.18) !important;
}
.footer-bottom{
  background:#020617 !important;
  border-top:1px solid rgba(147,197,253,.14) !important;
}

@media (max-width:768px){
  .hero{
    background:
      radial-gradient(circle at 82% 8%, rgba(96,165,250,.24) 0%, transparent 38%),
      linear-gradient(180deg,#f8fcff 0%,#dbeafe 100%) !important;
  }
  .feature-container{
    padding:22px !important;
    border-radius:24px !important;
  }
}

</style>

<section class="hero">
        <div class="hero-text">
            <h1>BarberBot: gestão, agenda e marketing para <span>profissionais da beleza</span></h1>
            <p>Um sistema criado por Jacy Cordeiro, cabeleireiro há mais de 25 anos e engenheiro de software, para organizar atendimentos, reduzir horários perdidos e ajudar barbearias e salões a venderem com mais presença digital.</p>

            <div class="bb-hero-trust" aria-label="Diferenciais do BarberBot">
                <span><i class="fa-solid fa-scissors"></i> Criado por quem vive a rotina do salão</span>
                <span><i class="fa-solid fa-code"></i> Tecnologia feita em PHP, MySQL e WhatsApp</span>
                <span><i class="fa-solid fa-bullhorn"></i> Plano Business com Google e Meta</span>
            </div>

            <div style="display: flex; gap: 15px; flex-wrap: wrap; margin-top:22px;">
                <a href="criar-barbearia.php" class="btn btn-primary" target="_blank">QUERO TESTAR 30 DIAS GRÁTIS</a>
                <a href="javascript:void(0)" class="btn btn-outline" onclick="openVideoModal(); return false;"><i class="fa-solid fa-play"></i> Ver Vídeo</a>

            </div>
            <p style="font-size: 0.8rem; margin-top: 15px; opacity: 0.7;"><i class="fa-solid fa-check"></i> Sem fidelidade. Cancele quando quiser.</p>
        </div>
        
        <div class="hero-img">
            <div class="premium-laptop-wide">
                <div class="laptop-lid-wide">
                    <div class="laptop-screen-wide" aria-label="Prévia do painel BarberBot">
                        <div class="bb-hero-video-placeholder" aria-hidden="true">
                            <div class="bb-placeholder-dashboard">
                                <div class="bb-placeholder-top">
                                    <div class="bb-placeholder-logo"><i class="fa-solid fa-scissors"></i> BarberBot</div>
                                    <div class="bb-placeholder-pill">Agenda online</div>
                                </div>
                                <div class="bb-placeholder-card">
                                    <p class="bb-placeholder-title">Gestão completa para barbearias e salões</p>
                                    <div class="bb-placeholder-line w1"></div>
                                    <div class="bb-placeholder-line w2"></div>
                                    <div class="bb-placeholder-line w3"></div>
                                    <div class="bb-placeholder-grid">
                                        <div class="bb-placeholder-mini"></div>
                                        <div class="bb-placeholder-mini"></div>
                                        <div class="bb-placeholder-mini"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <video class="bb-video-hero" muted loop playsinline preload="none" aria-label="Demonstração do painel BarberBot">
                            <source data-src="sistema/img/demo-painel-pc.mp4" type="video/mp4">
                            Seu navegador não suporta vídeos.
                        </video>
                    </div>
                </div>
                <div class="laptop-base-wide"></div>
            </div>
        </div>
    </section>


<section class="bb-founder-proof" aria-label="Diferencial do BarberBot">
  <div class="bb-founder-wrap">
    <div class="bb-founder-grid">
      <div>
        <div class="bb-founder-badge">
          <i class="fa-solid fa-user-tie"></i>
          Criado por profissional da beleza
        </div>

        <h2 class="bb-founder-title">
          O BarberBot não nasceu distante do salão. <span>Nasceu da prática.</span>
        </h2>

        <p class="bb-founder-text">
          Eu sou Jacy Cordeiro, cabeleireiro há mais de 25 anos, engenheiro de software e autor do BarberBot.
          Criei essa solução para resolver problemas reais: agenda desorganizada, cliente esquecendo horário,
          atendimento parado no WhatsApp, financeiro confuso e pouca presença digital.
        </p>

        <p class="bb-founder-text">
          O objetivo é simples: dar ao profissional da beleza uma estrutura mais profissional para atender,
          divulgar, organizar e crescer sem depender de improviso todos os dias.
        </p>

        <div class="bb-founder-actions">
          <a href="criar-barbearia.php" class="btn btn-primary" target="_blank">Quero organizar meu negócio</a>
          <a href="#planos" class="btn btn-outline">Ver plano Business</a>
        </div>
      </div>

      <div class="bb-founder-card">
        <div class="bb-founder-stats">
          <div class="bb-founder-stat">
            <strong>25+</strong>
            <span>Anos na beleza</span>
            <p>Experiência real com clientes, agenda, serviços e rotina de atendimento.</p>
          </div>

          <div class="bb-founder-stat">
            <strong>24h</strong>
            <span>Agenda online</span>
            <p>O cliente pode consultar horários e avançar no atendimento mesmo fora do expediente.</p>
          </div>

          <div class="bb-founder-stat">
            <strong>100%</strong>
            <span>Configuração guiada</span>
            <p>No Business, o profissional recebe orientação prática para começar do jeito certo.</p>
          </div>

          <div class="bb-founder-stat">
            <strong>Ads</strong>
            <span>Google e Meta</span>
            <p>Apoio para criar presença digital e campanhas mais organizadas.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="pain-section">
    <h2 class="section-title" style="color:#1d4ed8;">Cansado de perder dinheiro e tempo?</h2>
    <p class="section-subtitle">
        A maioria dos profissionais da beleza perde tempo com mensagens, encaixes, faltas, financeiro e divulgação.
        O BarberBot organiza agenda, atendimento, financeiro, marketing, simulações, assinaturas e presença digital em um só lugar.
    </p>

    <div class="pain-grid">
        <div class="pain-card">
            <i class="fa-regular fa-calendar-xmark pain-icon"></i>
            <h3>Furos na Agenda</h3>
            <p>O cliente esquece, não avisa e você fica com o horário vago, perdendo dinheiro.</p>
        </div>
        <div class="pain-card">
            <i class="fa-brands fa-whatsapp pain-icon"></i>
            <h3>Escravo do WhatsApp</h3>
            <p>Parar o corte toda hora para responder cliente perguntando "tem horário pra hoje?".</p>
        </div>
        <div class="pain-card">
            <i class="fa-solid fa-file-invoice-dollar pain-icon"></i>
            <h3>Financeiro Bagunçado</h3>
            <p>Misturar contas pessoais com as da empresa e não saber o lucro real no fim do mês.</p>
        </div>
        <div class="pain-card">
            <i class="fa-solid fa-bullhorn pain-icon"></i>
            <h3>Campanha de Marketing</h3>
            <p>Envie promoções, novidades, lembretes e ações pelo WhatsApp para aproximar clientes e aumentar o retorno ao salão.</p>
        </div>
        <div class="pain-card">
            <i class="fa-solid fa-wand-magic-sparkles pain-icon"></i>
            <h3>Simulador de Prótese Capilar</h3>
            <p>Apresente uma prévia visual para facilitar a venda consultiva e deixar o atendimento mais profissional.</p>
        </div>
        <div class="pain-card">
            <i class="fa-solid fa-credit-card pain-icon"></i>
            <h3>Assinaturas e Planos</h3>
            <p>Crie planos mensais, combos e cobranças recorrentes para prever receita e acompanhar cada cliente.</p>
        </div>
    </div>
</section>

    <section class="features" id="funcionalidades">
        
        <div class="feature-container">
            <div class="feature-text">
                <h2 style="font-size: 2.5rem; margin-bottom: 20px;">
                    📅 Agendamento Online 24/7 <br>
                    <span style="color:#1d4ed8;">via WhatsApp</span>
                </h2>
                <p style="color: var(--text-gray); font-size: 1.1rem; margin-bottom: 25px;">
                    Seus clientes agendam pelo WhatsApp a qualquer hora, sem precisar ligar ou esperar atendimento. 
                    Pare de perder tempo confirmando horários manualmente. O sistema confirma automaticamente!
                </p>
                <ul class="check-list">
                    <li><i class="fa-solid fa-calendar-days"></i> <strong>Agenda em tempo real:</strong> O bot agenda sozinho pelo WhatsApp.</li>
                    <li><i class="fa-solid fa-robot"></i> <strong>Atendimento Inteligente:</strong> O cliente agenda sozinho pelo link.</li>
                    <li><i class="fa-brands fa-whatsapp"></i> <strong>Lembretes Automáticos:</strong> O sistema avisa para não esquecer.</li>
                    <li><i class="fa-solid fa-calendar-check"></i> <strong>Confirmação:</strong> Automática via WhatsApp.</li>
                    <li><i class="fa-solid fa-bullhorn"></i> <strong>Recuperação:</strong> Promoções automáticas para quem sumiu.</li>
                </ul>
                <a href="agendamentos" class="btn btn-primary" style="margin-top: 20px;" target="_blank">Ver demonstração agendamento</a>
            </div>
            <div class="feature-img">
                <div class="phone-frame">
                    <div class="phone-notch"></div>
                    <video muted loop playsinline preload="none" class="bb-video-lazy">
                        <source data-src="sistema/img/demo-whatsapp.mp4" type="video/mp4">
                    </video>
                </div>
            </div>
        </div>

        <div class="feature-container reverse">
            <div class="feature-text">
                <h2 style="font-size: 2.5rem; margin-bottom: 20px;">
                    💰 Gestão Completa e <br>
                    <span style="color:#1d4ed8;">Financeiro na mão</span>
                </h2>
                <p style="color: var(--text-gray); font-size: 1.1rem; margin-bottom: 25px;">
                    Saiba exatamente quanto você fatura por dia, semana e mês. Simplifique sua gestão financeira com relatórios de vendas, compras, contas a pagar e a receber.
                </p>
                <ul class="check-list">
                    <li><i class="fa-solid fa-gauge-high"></i> <strong>Dashboard tempo real:</strong> Acompanhe o desempenho da equipe e o fluxo.</li>
                    <li><i class="fa-solid fa-chart-pie"></i> <strong>Relatórios:</strong> Lucro diário e mensal na hora.</li>
                    <li><i class="fa-solid fa-money-bill"></i> <strong>Baixa Automática:</strong> Cliente pagou? Cai no caixa.</li>
                    <li><i class="fa-solid fa-users-gear"></i> <strong>Comissões:</strong> Cálculo automático da equipe.</li>
                    <li><i class="fa-solid fa-file-invoice-dollar"></i> <strong>Despesas e Custos:</strong> Organize contas a pagar.</li>
                </ul>
                <a href="<?php echo rtrim(dirname($_SERVER['SCRIPT_NAME'] ?? '/'), '/\\') . '/sistema/'; ?>" class="btn btn-outline" style="margin-top: 20px;" target="_blank">Testar Painel</a>
            </div>
            <div class="feature-img">
                <div class="phone-frame">
                    <div class="phone-notch"></div>
                    <video muted loop playsinline preload="none" class="bb-video-lazy">
                        <source data-src="sistema/img/demo-painel.mp4" type="video/mp4">
                    </video>
                </div>
            </div>
        </div>

        <div class="feature-container">
            <div class="feature-text">
                <h2 style="font-size: 2.5rem; margin-bottom: 20px;">
                    🤖 Marketing Automático <br>
                    <span style="color:#1d4ed8;">Estratégias para profissionais</span>
                </h2>
                <p style="color: var(--text-gray); font-size: 1.1rem; margin-bottom: 25px;">
                    Marketing é o conjunto de estratégias para o profissional alavancar seus resultados.
                </p>
                <ul class="check-list">
                    <li><i class="fa-solid fa-bullseye"></i> <strong>Campanhas Precisas:</strong> Envie ofertas segmentadas.</li>
                    <li><i class="fa-solid fa-envelope-open-text"></i> <strong>Disparos em Massa:</strong> Comunique com todos os clientes.</li>
                    <li><i class="fa-solid fa-trophy"></i> <strong>Fidelização:</strong> Transforme clientes em fãs.</li>
                    <li><i class="fa-solid fa-cake-candles"></i> <strong>Aniversariantes:</strong> Quem não gosta de ser lembrado?</li>
                    <li><i class="fa-solid fa-bullhorn"></i> <strong> Automático:</strong> Envie promoções, lembretes e campanhas automáticas para seus clientes. Ganhe tempo, aumente os agendamentos e fortaleça o relacionamento com quem já compra de você.</li>
                </ul>
                <a href="criar-barbearia.php" class="btn btn-primary" style="margin-top: 20px;" target="_blank">Criar Campanhas</a>
            </div>
            <div class="feature-img">
                <div class="phone-frame">
                    <div class="phone-notch"></div>
                    <video muted loop playsinline preload="none" class="bb-video-lazy">
                        <source data-src="sistema/img/demo-mark.mp4" type="video/mp4">
                    </video>
                </div>
            </div>
        </div>
    </section><section class="about-section">
  <div class="about-container">

    <div class="about-image">
      <img src="sistema/img/sobre-baberbot.png" alt="Sobre a BarberBot" loading="lazy" decoding="async" width="640" height="640">
    </div>

    <div class="about-content">
      <h2 class="about-title">
        Muito mais que um sistema, <br>
        <span>Seu Parceiro de Negócios</span>
      </h2>

      <p class="about-text">
        O BarberBot foi desenvolvido por quem conhece a rotina do salão na prática. A proposta é ajudar barbeiros,
        cabeleireiros, manicures, esteticistas e profissionais da beleza a organizarem agenda, clientes, financeiro
        e divulgação com uma tecnologia simples, direta e preparada para o atendimento pelo WhatsApp.
      </p>

      <div class="about-cards">
        <div class="about-card">
          <div class="ico"><i class="fa-solid fa-rocket" style="color:#3b82f6;font-size:1.3rem;"></i></div>
          <h3>Missão</h3>
          <p>Levar gestão, automação e marketing digital para profissionais da beleza que querem sair do improviso.</p>
        </div>

        <div class="about-card">
          <div class="ico"><i class="fa-solid fa-eye" style="color:#10b981;font-size:1.3rem;"></i></div>
          <h3>Visão</h3>
          <p>Unir experiência de salão, tecnologia e presença digital para ajudar negócios locais a crescerem.</p>
        </div>
      </div>

      <div>
        <button onclick="openVideoModal()" class="btn btn-primary" style="padding:15px 40px;font-size:1.1rem;">
          <i class="fa-solid fa-play-circle" style="font-size:1.4rem;"></i>
          Assistir Apresentação
        </button>
      </div>
    </div>

  </div>

<!-- ============================== DESDE 1999 - EXPERIÊNCIA + AVALIAÇÕES ============================== -->
<?php
// ===== Contexto público (SITE) respeitando multi-tenant (/loja/<slug>/...) =====
$bb_slug_publico = '';
$bb_uri_publico = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '');
if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $bb_uri_publico, $mPub)) {
  $bb_slug_publico = strtolower((string)$mPub[1]);
} elseif (!empty($_SESSION['tenant_slug'])) {
  $bb_slug_publico = strtolower((string)$_SESSION['tenant_slug']);
}
$bb_slug_publico = preg_replace('/[^a-z0-9_-]/i', '', $bb_slug_publico);

// ===== Helpers locais (não altera a lógica do sistema) =====
if (!function_exists('bb_h')) { function bb_h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); } }
if (!function_exists('bb_starsTxt')) {
  function bb_starsTxt($n){
    $n = (int)$n; if($n<0||$n>5) $n=5;
    return str_repeat('★',$n).str_repeat('☆',5-$n);
  }
}
if (!function_exists('bb_relTempo')) {
  function bb_relTempo($date){
    $ts = is_numeric($date) ? (int)$date : strtotime($date ?: 'now');
    if(!$ts) return '';
    $diff = time() - $ts;
    if ($diff < 60) return 'Agora';
    $map = [
      31536000 => ['ano','anos'],
      2592000  => ['mês','meses'],
      604800   => ['semana','semanas'],
      86400    => ['dia','dias'],
      3600     => ['hora','horas'],
      60       => ['minuto','minutos'],
    ];
    foreach($map as $secs => $labels){
      if($diff >= $secs){
        $q = (int)floor($diff/$secs);
        $lbl = ($q === 1) ? $labels[0] : $labels[1];
        return 'Há '.$q.' '.$lbl;
      }
    }
    return '';
  }
}

// ===== Link para avaliar no Google (tabela avaliacoes_site) =====
// Não usa link fixo como fallback. O botão público deve buscar o link salvo em /avaliacoes.
// Se o link não existir no banco correto, a interface marca como pendente.
$link_avaliacao_google = '';

if (!function_exists('bb_home_avaliacoes_clean_host')) {
  function bb_home_avaliacoes_clean_host($host) {
    $host = strtolower(trim((string)$host));
    if ($host === '') return '';
    $host = preg_replace('~^https?://~i', '', $host);
    $host = preg_replace('~/.*$~', '', $host);
    $host = preg_replace('~:\d+$~', '', $host);
    return trim($host, ". \t\n\r\0\x0B");
  }
}

if (!function_exists('bb_home_avaliacoes_cfg')) {
  function bb_home_avaliacoes_cfg() {
    static $cfg = null;
    static $loaded = false;

    if ($loaded) {
      return is_array($cfg) ? $cfg : [];
    }

    $loaded = true;
    $file = __DIR__ . '/config/marketplace.php';
    if (is_file($file)) {
      $tmp = require $file;
      if (is_array($tmp)) {
        $cfg = $tmp;
      }
    }

    return is_array($cfg) ? $cfg : [];
  }
}

if (!function_exists('bb_home_avaliacoes_pdo')) {
  function bb_home_avaliacoes_pdo($host, $db, $user, $pass = '') {
    $host = trim((string)$host);
    $db = preg_replace('/[^a-z0-9_]/i', '', (string)$db);
    $user = trim((string)$user);

    if ($host === '' || $db === '' || $user === '') {
      return null;
    }

    try {
      return new PDO(
        'mysql:host=' . $host . ';dbname=' . $db . ';charset=utf8mb4',
        $user,
        (string)$pass,
        [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
      );
    } catch (Throwable $e) {
      return null;
    }
  }
}


if (!function_exists('bb_home_avaliacoes_config_valor_publico')) {
  function bb_home_avaliacoes_config_valor_publico($pdo, $chave) {
    if (!($pdo instanceof PDO)) {
      return '';
    }

    $chave = trim((string)$chave);
    if ($chave === '') {
      return '';
    }

    try {
      $stTb = $pdo->query("SHOW TABLES LIKE 'config_site'");
      if (!$stTb || !$stTb->fetchColumn()) {
        return '';
      }

      $st = $pdo->prepare("SELECT valor FROM config_site WHERE chave = :chave LIMIT 1");
      $st->execute([':chave' => $chave]);
      return trim((string)($st->fetchColumn() ?: ''));
    } catch (Throwable $e) {
      return '';
    }
  }
}

if (!function_exists('bb_home_avaliacoes_master_pdo')) {
  function bb_home_avaliacoes_master_pdo() {
    $cfg = bb_home_avaliacoes_cfg();
    $candidatas = [];

    if (!empty($cfg['auth_master']) && is_array($cfg['auth_master'])) {
      $candidatas[] = $cfg['auth_master'];
    }

    // Usa o bloco master somente se ele realmente apontar para o banco administrativo.
    if (!empty($cfg['master']) && is_array($cfg['master']) && (string)($cfg['master']['db'] ?? '') === 'jctecbr_master') {
      $candidatas[] = $cfg['master'];
    }

    foreach ($candidatas as $c) {
      $pdoMaster = bb_home_avaliacoes_pdo(
        $c['host'] ?? 'localhost',
        $c['db'] ?? '',
        $c['user'] ?? '',
        $c['pass'] ?? ''
      );

      if ($pdoMaster instanceof PDO) {
        return $pdoMaster;
      }
    }

    return null;
  }
}

if (!function_exists('bb_home_avaliacoes_market_pdo')) {
  function bb_home_avaliacoes_market_pdo() {
    $cfg = bb_home_avaliacoes_cfg();
    $m = (!empty($cfg['master']) && is_array($cfg['master'])) ? $cfg['master'] : [];

    return bb_home_avaliacoes_pdo(
      $m['host'] ?? 'localhost',
      $m['db'] ?? '',
      $m['user'] ?? '',
      $m['pass'] ?? ''
    );
  }
}

if (!function_exists('bb_home_avaliacoes_decrypt_or_plain')) {
  function bb_home_avaliacoes_decrypt_or_plain($value) {
    $value = (string)$value;
    if ($value === '' || substr($value, 0, 4) !== 'enc:') {
      return $value;
    }

    $cfg = bb_home_avaliacoes_cfg();
    $secret = (string)($cfg['security']['tenant_secret'] ?? '');
    $crypto = __DIR__ . '/sistema/tenant_crypto.php';

    if ($secret !== '' && is_file($crypto)) {
      require_once $crypto;
      if (function_exists('bb_decrypt_str')) {
        $dec = bb_decrypt_str($value, $secret);
        if ($dec !== '') {
          return $dec;
        }
      }
    }

    return $value;
  }
}

if (!function_exists('bb_home_avaliacoes_tenant_pdo_from_row')) {
  function bb_home_avaliacoes_tenant_pdo_from_row($tenant) {
    if (!is_array($tenant) || empty($tenant['db_name'])) {
      return null;
    }

    $cfg = bb_home_avaliacoes_cfg();
    $cfgTenant = (!empty($cfg['tenant']) && is_array($cfg['tenant'])) ? $cfg['tenant'] : [];

    $host = !empty($tenant['db_host']) ? (string)$tenant['db_host'] : (string)($cfgTenant['host'] ?? 'localhost');
    $db   = (string)($tenant['db_name'] ?? '');
    $user = !empty($tenant['db_user']) ? (string)$tenant['db_user'] : (string)($cfgTenant['user'] ?? '');
    $pass = !empty($tenant['db_pass']) ? bb_home_avaliacoes_decrypt_or_plain((string)$tenant['db_pass']) : (string)($cfgTenant['pass'] ?? '');

    return bb_home_avaliacoes_pdo($host, $db, $user, $pass);
  }
}

if (!function_exists('bb_home_avaliacoes_tenant_by_slug')) {
  function bb_home_avaliacoes_tenant_by_slug($slug) {
    $slug = strtolower(trim((string)$slug));
    $slug = preg_replace('/[^a-z0-9_-]/', '', $slug);
    if ($slug === '') {
      return null;
    }

    $cfg = bb_home_avaliacoes_cfg();
    $table = preg_replace('/[^a-z0-9_]/i', '', (string)($cfg['master']['table'] ?? 'tenants'));
    if ($table === '') {
      $table = 'tenants';
    }

    $pdoMarket = bb_home_avaliacoes_market_pdo();
    if (!$pdoMarket instanceof PDO) {
      return null;
    }

    try {
      $st = $pdoMarket->prepare("SELECT * FROM `{$table}` WHERE slug = :slug LIMIT 1");
      $st->execute([':slug' => $slug]);
      $tenant = $st->fetch(PDO::FETCH_ASSOC);

      if (!$tenant) {
        return null;
      }

      if (array_key_exists('ativo', $tenant) && (string)$tenant['ativo'] !== '' && (int)$tenant['ativo'] !== 1) {
        return null;
      }

      if (array_key_exists('status', $tenant)) {
        $status = strtolower(trim((string)$tenant['status']));
        if ($status === 'blocked' || $status === 'inativo') {
          return null;
        }
      }

      return $tenant;
    } catch (Throwable $e) {
      return null;
    }
  }
}

if (!function_exists('bb_home_avaliacoes_tenant_by_host')) {
  function bb_home_avaliacoes_tenant_by_host($host) {
    $host = bb_home_avaliacoes_clean_host($host);
    if ($host === '') {
      return null;
    }

    $cfg = bb_home_avaliacoes_cfg();
    $table = preg_replace('/[^a-z0-9_]/i', '', (string)($cfg['master']['table'] ?? 'tenants'));
    if ($table === '') {
      $table = 'tenants';
    }

    $pdoMarket = bb_home_avaliacoes_market_pdo();
    if (!$pdoMarket instanceof PDO) {
      return null;
    }

    try {
      $stTb = $pdoMarket->query("SHOW TABLES LIKE 'tenant_domains'");
      if (!$stTb || !$stTb->fetchColumn()) {
        return null;
      }

      $stCols = $pdoMarket->query("SHOW COLUMNS FROM tenant_domains");
      $cols = [];
      foreach (($stCols ? $stCols->fetchAll(PDO::FETCH_ASSOC) : []) as $r) {
        if (!empty($r['Field'])) {
          $cols[(string)$r['Field']] = true;
        }
      }

      $where = ["LOWER(TRIM(d.host)) = LOWER(TRIM(:host))"];
      if (isset($cols['ativo'])) {
        $where[] = "(d.ativo = 1 OR d.ativo IS NULL)";
      }
      if (isset($cols['principal'])) {
        $where[] = "(d.principal = 1 OR d.principal IS NULL)";
      }

      $sql = "SELECT t.*
                FROM tenant_domains d
                INNER JOIN `{$table}` t ON t.id = d.tenant_id
               WHERE " . implode(' AND ', $where) . "
               ORDER BY d.id DESC
               LIMIT 1";
      $st = $pdoMarket->prepare($sql);
      $st->execute([':host' => $host]);
      $tenant = $st->fetch(PDO::FETCH_ASSOC);

      return $tenant ?: null;
    } catch (Throwable $e) {
      return null;
    }
  }
}

if (!function_exists('bb_home_avaliacoes_is_main_host')) {
  function bb_home_avaliacoes_is_main_host($host) {
    $host = bb_home_avaliacoes_clean_host($host);
    $cfg = bb_home_avaliacoes_cfg();
    $base = '';

    if (!empty($cfg['base_url'])) {
      $base = bb_home_avaliacoes_clean_host($cfg['base_url']);
    } elseif (!empty($cfg['app']['base_url'])) {
      $base = bb_home_avaliacoes_clean_host($cfg['app']['base_url']);
    }

    $hosts = ['jc.tec.br', 'www.jc.tec.br', 'localhost'];
    if ($base !== '') {
      $baseSemWww = preg_replace('~^www\.~', '', $base);
      $hosts[] = $base;
      $hosts[] = 'www.' . $baseSemWww;
    }

    $hosts = array_values(array_unique(array_filter(array_map('bb_home_avaliacoes_clean_host', $hosts))));
    return in_array($host, $hosts, true);
  }
}

if (!function_exists('bb_home_avaliacoes_resolver_pdo')) {
  function bb_home_avaliacoes_resolver_pdo($slugPublico = '') {
    $slugPublico = strtolower(trim((string)$slugPublico));
    $slugPublico = preg_replace('/[^a-z0-9_-]/', '', $slugPublico);

    // TENANT por /loja/{slug}/ ou ?loja=.
    if ($slugPublico !== '') {
      $tenant = bb_home_avaliacoes_tenant_by_slug($slugPublico);
      return bb_home_avaliacoes_tenant_pdo_from_row($tenant);
    }

    $hostAtual = bb_home_avaliacoes_clean_host($_SERVER['HTTP_HOST'] ?? '');

    // TENANT por domínio próprio cadastrado em tenant_domains.
    if ($hostAtual !== '' && !bb_home_avaliacoes_is_main_host($hostAtual)) {
      $tenantHost = bb_home_avaliacoes_tenant_by_host($hostAtual);
      if (is_array($tenantHost) && !empty($tenantHost['slug'])) {
        return bb_home_avaliacoes_tenant_pdo_from_row($tenantHost);
      }

      // TENANT por subdomínio aceito no ecossistema: slug.jc.tec.br.
      $partes = explode('.', $hostAtual);
      if (count($partes) >= 3) {
        $sub = preg_replace('/[^a-z0-9_-]/', '', strtolower((string)$partes[0]));
        $ignorados = ['www', 'app', 'api', 'sistema', 'painel', 'master'];
        if ($sub !== '' && !in_array($sub, $ignorados, true)) {
          $tenantSub = bb_home_avaliacoes_tenant_by_slug($sub);
          if (is_array($tenantSub)) {
            return bb_home_avaliacoes_tenant_pdo_from_row($tenantSub);
          }
        }
      }

      return null;
    }

    // Site principal / MASTER: busca no banco administrativo global.
    return bb_home_avaliacoes_master_pdo();
  }
}

$__pdo_avaliacoes_publicas = bb_home_avaliacoes_resolver_pdo($bb_slug_publico);

// ===== Estatísticas e lista (cada loja usa seu banco; MASTER usa o dele) =====
$__total = 0;
$__media = 0.0;
$__reviews = [];

if ($__pdo_avaliacoes_publicas instanceof PDO) {
  try {
    $stLink = $__pdo_avaliacoes_publicas->query("SELECT link_avaliacao_google
                           FROM avaliacoes_site
                           WHERE is_config = 1
                           ORDER BY id DESC
                           LIMIT 1");
    $rLink = $stLink ? $stLink->fetch(PDO::FETCH_ASSOC) : false;

    if ($rLink && !empty($rLink['link_avaliacao_google'])) {
      $tmp = trim((string)$rLink['link_avaliacao_google']);
      if (filter_var($tmp, FILTER_VALIDATE_URL) && preg_match('~^https?://~i', $tmp)) {
        $link_avaliacao_google = $tmp;
      }
    }

    // Compatibilidade com configuração antiga, sem substituir link válido da tabela principal.
    if ($link_avaliacao_google === '') {
      $stLinkCfg = $__pdo_avaliacoes_publicas->prepare("SELECT valor FROM config_site WHERE chave = :chave LIMIT 1");
      $stLinkCfg->execute([':chave' => 'google_review_link']);
      $tmp = trim((string)($stLinkCfg->fetchColumn() ?: ''));
      if ($tmp !== '' && filter_var($tmp, FILTER_VALIDATE_URL) && preg_match('~^https?://~i', $tmp)) {
        $link_avaliacao_google = $tmp;
      }
    }
  } catch (Throwable $e) {
    // sem link salvo: mantém pendente
  }

  try {
    // prioriza Google
    $st = $__pdo_avaliacoes_publicas->query("SELECT COUNT(*) AS total, ROUND(AVG(nota),1) AS media
                       FROM avaliacoes_site
                       WHERE status=1 AND origem='Google'");
    $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : false;
    $__total = (int)($row['total'] ?? 0);
    $__media = (float)($row['media'] ?? 0);

    // fallback geral
    if ($__total <= 0) {
      $st = $__pdo_avaliacoes_publicas->query("SELECT COUNT(*) AS total, ROUND(AVG(nota),1) AS media
                         FROM avaliacoes_site
                         WHERE status=1");
      $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : false;
      $__total = (int)($row['total'] ?? 0);
      $__media = (float)($row['media'] ?? 0);
    }
  } catch (Throwable $e) {
    $__total = 0;
    $__media = 0.0;
  }

  try {
    $googleRatingCfg = bb_home_avaliacoes_config_valor_publico($__pdo_avaliacoes_publicas, 'google_rating');
    $googleTotalCfg  = bb_home_avaliacoes_config_valor_publico($__pdo_avaliacoes_publicas, 'google_user_ratings_total');

    $googleRating = is_numeric($googleRatingCfg) ? (float)$googleRatingCfg : 0.0;
    $googleTotal  = ctype_digit($googleTotalCfg) ? (int)$googleTotalCfg : 0;

    // Se o Google retornou resumo real, usa esse total/nota no painel público.
    // Não cria comentários falsos; apenas exibe média e quantidade oficiais retornadas pela API.
    if ($googleTotal > 0 && ($__total <= 0 || $googleTotal > $__total)) {
      $__total = $googleTotal;
      if ($googleRating > 0) {
        $__media = $googleRating;
      }
    }
  } catch (Throwable $e) {
    // mantém estatística calculada por avaliações locais
  }

  try {
    $stR = $__pdo_avaliacoes_publicas->query("SELECT nome, nota, comentario,
                               COALESCE(foto,'') AS foto,
                               COALESCE(origem,'') AS origem,
                               COALESCE(data_avaliacao, criado_em, NOW()) AS data_avaliacao
                        FROM avaliacoes_site
                        WHERE status=1
                        ORDER BY data_avaliacao DESC, id DESC
                        LIMIT 9");
    $__reviews = $stR ? $stR->fetchAll(PDO::FETCH_ASSOC) : [];
  } catch (Throwable $e) {
    $__reviews = [];
  }
}

$__pal = ['#2563eb','#1d4ed8','#0ea5e9','#10b981','#f59e0b','#334155'];
$__pal_i = 0;

// valor para mini-stat "Avaliação" (mantém fallback visual se não houver dados)
$__mini_avaliacao = $__total ? number_format($__media, 1, '.', '') : '—';
?>

<section class="experience-section" style="background:#0b1120;padding:60px 0;position:relative;left:50%;right:50%;margin-left:-50vw;margin-right:-50vw;width:100vw;overflow:hidden;">
  <div class="container">
    <div class="row align-items-center">

      <!-- Coluna Esquerda - Ano Destaque + Visitas -->
      <div class="col-md-5 text-center mb-4 mb-md-0">
        <div class="year-highlight" style="position:relative;">
          <div style="font-size:8rem;font-weight:900;color:var(--text-light);line-height:1;margin-bottom:10px;text-shadow:0 10px 35px rgba(0,0,0,0.35);">
            2024
          </div>
          <div style="font-size:1.8rem;color:var(--secondary);font-weight:800;margin-bottom:15px;">
            Início do BarberBot
          </div>
          <div style="width:100px;height:4px;background:linear-gradient(90deg, var(--secondary), var(--success));margin:0 auto 25px;border-radius:2px;opacity:.95;"></div>

          <!-- Contador de Visitas -->
          <div class="visitas-counter" style="background:#0f172a;border-radius:18px;padding:22px;margin-top:20px;">
            <div style="display:flex;align-items:center;justify-content:center;gap:15px;margin-bottom:10px;">
              <div style="font-size:3rem;">👁️</div>
              <div style="text-align:left;">
                <div id="visitasCounter1999" style="font-size:2.5rem;font-weight:800;color:var(--secondary);line-height:1;">
                  <span class="counter-1999" data-target="0">...</span>
                </div>
                <div style="font-size:0.85rem;color:var(--text-gray);text-transform:uppercase;letter-spacing:1px;font-weight:600;margin-top:6px;">
                  Acessos registrados
                </div>
              </div>
            </div>
          </div>
          <!-- /Contador -->
        </div>
      </div>

      <!-- Coluna Direita - Conteúdo -->
      <div class="col-md-7">
        <div class="experience-content" style="padding:0 20px;">
          <h2 style="color:var(--text-light);font-size:2.5rem;font-weight:800;margin-bottom:20px;">
            Experiência na beleza, <span style="color:var(--secondary);">agora com tecnologia.</span>
          </h2>

          <p style="color:var(--text-gray);font-size:1.12rem;line-height:1.8;margin-bottom:25px;">
            O BarberBot foi criado para ajudar barbeiros e profissionais da beleza
    a organizarem seus agendamentos online de forma simples, prática e profissional.
    A plataforma une experiência no atendimento ao cliente com tecnologia,
    automação por WhatsApp e presença digital.</p>

          <!-- Mini Stats -->
          <div class="row text-center" style="margin-top:30px;">
            <div class="col-4">
              <div class="mini-stat" style="padding:16px;background:#0f172a;border-radius:14px;transition:all 0.3s ease;">
                <div style="font-size:2.5rem;font-weight:800;color:var(--secondary);margin-bottom:5px;">25+</div>
                <div style="font-size:0.85rem;color:var(--text-gray);text-transform:uppercase;letter-spacing:1px;font-weight:700;">Anos na beleza</div>
              </div>
            </div>
            <div class="col-4">
              <div class="mini-stat" style="padding:16px;background:#111c33;border-radius:14px;transition:all 0.3s ease;">
                <div style="font-size:2.5rem;font-weight:800;color:var(--success);margin-bottom:5px;">2024</div>
                <div style="font-size:0.85rem;color:var(--text-gray);text-transform:uppercase;letter-spacing:1px;font-weight:700;">Início do sistema</div>
              </div>
            </div>
            <div class="col-4">
              <div class="mini-stat" style="padding:16px;background:#101a30;border-radius:14px;transition:all 0.3s ease;">
                <div style="font-size:2.5rem;font-weight:800;color:var(--accent);margin-bottom:5px;"><?= bb_h($__mini_avaliacao) ?></div>
                <div style="font-size:0.85rem;color:var(--text-gray);text-transform:uppercase;letter-spacing:1px;font-weight:700;">Avaliação média</div>
              </div>
            </div>
          </div>

          <!-- Badges de Confiança -->
          <div class="trust-indicators" style="margin-top:30px;display:flex;align-items:center;gap:14px;flex-wrap:wrap;">
            <div style="display:flex;align-items:center;gap:10px;padding:10px 18px;background:#0f172a;border-radius:999px;transition:all 0.3s ease;">
              <i class="fa fa-certificate" style="color:var(--secondary);font-size:1.15rem;"></i>
              <span style="color:var(--text-light);font-weight:700;">📅 Agendamento online</span>
            </div>
            <div style="display:flex;align-items:center;gap:10px;padding:10px 18px;background:#111c33;border-radius:999px;transition:all 0.3s ease;">
              <i class="fa fa-trophy" style="color:var(--success);font-size:1.15rem;"></i>
              <span style="color:var(--text-light);font-weight:700;">💬 Atendimento via WhatsApp</span>
            </div>
          </div>
        </div>
      </div>

    </div>
  </div>
</section>

<!-- ============================== AVALIAÇÕES GOOGLE ============================== -->
<section class="google-reviews-section" style="background:#0b1120;color:var(--text-light);padding:50px 0;position:relative;left:50%;right:50%;margin-left:-50vw;margin-right:-50vw;width:100vw;overflow:hidden;">
  <div class="container">
    <div class="row align-items-center">

      <!-- Coluna Esquerda -->
      <div class="col-md-6 mb-4 mb-md-0">
        <div style="background:#0f172a;border-radius:16px;padding:30px;text-align:center;">
          <div class="mb-3">
            <img src="https://www.gstatic.com/images/branding/googlelogo/2x/googlelogo_color_92x30dp.png" alt="Google" width="92" height="30" loading="lazy" style="display:inline-block;opacity:.95;">
          </div>

          <h3 style="color:var(--text-light);margin:15px 0 10px;font-size:1.8rem;font-weight:800;">Avaliações no Google</h3>

          <div class="mb-3">
            <span style="font-size:3.5rem;font-weight:800;color:var(--text-light);line-height:1;">
              <?= $__total ? number_format($__media, 1, ',', '') : '—' ?>
            </span>

            <div style="font-size:2rem;color:var(--accent);margin:10px 0;">
              <?= $__total ? bb_starsTxt((int)round($__media)) : '☆☆☆☆☆' ?>
            </div>

            <p style="color:var(--text-gray);margin:0;font-size:1rem;">
              Baseado em <strong style="color:var(--text-light);"><?= number_format($__total, 0, '', '.') ?> avaliações</strong>
            </p>
          </div>

          <div style="margin-top:18px;padding-top:0;">
            <p style="color:var(--text-gray);font-size:0.92rem;margin:0;">
              <i class="fa fa-quote-left" style="color:var(--accent);margin-right:6px;"></i>
              Veja o que nossos clientes dizem sobre nós
            </p>
          </div>
        </div>
      </div>

      <!-- Coluna Direita -->
      <div class="col-md-6">
        <div style="padding:20px;">
          <h2 style="color:var(--text-light);font-size:2rem;margin-bottom:15px;font-weight:800;">Sua opinião é importante!</h2>
          <p style="color:var(--text-gray);font-size:1.08rem;line-height:1.7;margin-bottom:25px;">
            Já foi atendido por nós? Compartilhe sua experiência e ajude outros clientes a conhecerem nosso trabalho.
            Sua avaliação faz toda a diferença!
          </p>

          <?php if ($link_avaliacao_google !== ''): ?>
            <a href="<?= bb_h($link_avaliacao_google) ?>"
               target="_blank" rel="noopener noreferrer nofollow"
               style="display:inline-flex;align-items:center;justify-content:center;gap:10px;background:var(--secondary);color:#fff;text-decoration:none;padding:15px 35px;border-radius:50px;font-size:1.1rem;font-weight:800;box-shadow:0 4px 15px rgba(37,99,235,0.35);transition:all 0.3s ease;">
              <i class="fa-brands fa-google" style="font-size:1.35rem;"></i>
              Avaliar no Google
            </a>
          <?php else: ?>
            <div style="display:inline-flex;align-items:center;justify-content:center;gap:10px;background:#fef3c7;color:#92400e;text-decoration:none;padding:14px 24px;border-radius:50px;font-size:1rem;font-weight:800;border:1px solid rgba(146,64,14,.20);">
              <i class="fa-solid fa-triangle-exclamation" style="font-size:1.1rem;"></i>
              Link de avaliação pendente
            </div>
          <?php endif; ?>

          <div style="margin-top:25px;display:flex;align-items:center;gap:15px;flex-wrap:wrap;">
            <div style="display:flex;align-items:center;gap:8px;color:var(--text-gray);font-size:0.92rem;">
              <i class="fa fa-shield" style="color:var(--success);font-size:1.15rem;"></i>
              <span>Avaliações verificadas</span>
            </div>
            <div style="display:flex;align-items:center;gap:8px;color:var(--text-gray);font-size:0.92rem;">
              <i class="fa fa-users" style="color:var(--secondary);font-size:1.15rem;"></i>
              <span>Atendimento online</span>
            </div>
          </div>
        </div>
      </div>

    </div>

    <!-- Avaliações Recentes (Carrossel) -->
    <div class="row" style="margin-top:40px;">
      <div class="col-12">
        <h4 style="color:var(--text-light);text-align:center;margin-bottom:25px;font-size:1.5rem;font-weight:800;">
          Avaliações Recentes
        </h4>
      </div>

      <div class="col-12">
        <div class="google-reviews-carousel owl-carousel owl-theme">
          <?php if (!empty($__reviews)): ?>
            <?php
              // tons sobre tom (card a card)
              $__card_tons = ['#0f172a', '#111c33', '#101a30', '#0e172d'];
              $__card_i = 0;
            ?>
            <?php foreach ($__reviews as $__r):
              $__nome = trim((string)($__r['nome'] ?? ''));
              $__nomeEx = bb_h($__nome !== '' ? $__nome : 'Cliente');

              $__nota = (int)($__r['nota'] ?? 5);
              if ($__nota < 1) $__nota = 1;
              if ($__nota > 5) $__nota = 5;

              $__txtRaw = trim((string)($__r['comentario'] ?? ''));
              if ($__txtRaw === '') $__txtRaw = 'Sem comentário público.';
              $__txt = function_exists('mb_strimwidth') ? mb_strimwidth($__txtRaw, 0, 260, '...', 'UTF-8') : ((strlen($__txtRaw) > 260) ? substr($__txtRaw, 0, 257) . '...' : $__txtRaw);

              $__quando = bb_relTempo($__r['data_avaliacao'] ?? '');
              $__origem = trim((string)($__r['origem'] ?? ''));

              $__foto = trim((string)($__r['foto'] ?? ''));
              $__bg = $__pal[$__pal_i++ % count($__pal)];

              $foto_ok = false;
              if ($__foto !== '') {
                if (preg_match('~^https?://~i', $__foto) && filter_var($__foto, FILTER_VALIDATE_URL)) $foto_ok = true;
                if (preg_match('~^/[a-z0-9/_\.\-]+$~i', $__foto)) $foto_ok = true;
                if (preg_match('~^[a-z0-9/_\.\-]+$~i', $__foto)) $foto_ok = true;
              }

              if ($foto_ok) {
                $__avatar = '<img src="'.bb_h($__foto).'" alt="'.$__nomeEx.'" style="width:50px;height:50px;border-radius:50%;object-fit:cover;">';
              } else {
                $__ini = 'C';
                if ($__nome !== '') {
                  $__ini = function_exists('mb_substr') ? mb_strtoupper(mb_substr($__nome, 0, 1, 'UTF-8'), 'UTF-8') : strtoupper(substr($__nome, 0, 1));
                }
                $__avatar = '<div style="width:50px;height:50px;border-radius:50%;background:'.$__bg.';display:flex;align-items:center;justify-content:center;color:#fff;font-weight:800;font-size:1.2rem;flex-shrink:0;">'.bb_h($__ini).'</div>';
              }

              $__card_bg = $__card_tons[$__card_i++ % count($__card_tons)];
            ?>
              <div class="item">
                <div style="background:<?= bb_h($__card_bg) ?>;border-radius:14px;padding:22px;min-height:200px;box-shadow:0 12px 26px rgba(0,0,0,0.18);">
                  <div style="display:flex;align-items:center;gap:12px;margin-bottom:14px;">
                    <?= $__avatar ?>
                    <div style="flex:1;">
                      <p style="margin:0;color:var(--text-light);font-weight:700;font-size:1.05rem;"><?= $__nomeEx ?></p>
                      <div style="color:var(--accent);font-size:1rem;margin-top:2px;"><?= bb_starsTxt($__nota) ?></div>
                    </div>
                  </div>

                  <p style="color:var(--text-gray);font-size:0.95rem;margin:0;line-height:1.6;"><?= bb_h($__txt) ?></p>

                  <span style="color:rgba(148,163,184,0.85);font-size:0.85rem;margin-top:12px;display:block;">
                    <i class="fa fa-clock-o"></i> <?= bb_h($__quando) ?>
                    <?php if ($__origem !== ''): ?>
                      &nbsp; · &nbsp;<i class="fa fa-tag"></i> <?= bb_h($__origem) ?>
                    <?php endif; ?>
                  </span>
                </div>
              </div>
            <?php endforeach; ?>
          <?php else: ?>
            <div class="item">
              <div style="background:#0f172a;border-radius:14px;padding:22px;">
                <p style="color:var(--text-gray);margin:0;">Ainda não há avaliações publicadas.</p>
              </div>
            </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <style>
/* Carrossel de Avaliações - fallback quando o Owl (CSS/JS) não estiver carregado */
.google-reviews-carousel.bb-fallback{
  display:flex;
  gap:18px;
  overflow-x:auto;
  overflow-y:hidden;
  scroll-behavior:smooth;
  padding:0 2px;
  -webkit-overflow-scrolling:touch;
}
.google-reviews-carousel.bb-fallback::-webkit-scrollbar{height:0;}
.google-reviews-carousel.bb-fallback{scrollbar-width:none;}
.google-reviews-carousel.bb-fallback .item{flex:0 0 100%; max-width:100%;}
@media (min-width:768px){
  .google-reviews-carousel.bb-fallback .item{flex:0 0 calc(50% - 9px); max-width:calc(50% - 9px);}
}
@media (min-width:1100px){
  .google-reviews-carousel.bb-fallback .item{flex:0 0 calc(33.333% - 12px); max-width:calc(33.333% - 12px);}
}
</style>

<script>
(function(){
  var __bb_try = 0;
  function initAvaliacoesCarousel(){
    try{
      var c = document.querySelector('.google-reviews-carousel');
      if(!c) return;

      __bb_try++;

      // Se o Owl Carousel do sistema estiver disponível, usa o padrão dele
      if (window.jQuery && window.jQuery.fn && typeof window.jQuery.fn.owlCarousel === 'function') {
        var $c = window.jQuery(c);

        // evita inicializar duas vezes
        if ($c.hasClass('owl-loaded') || $c.data('owl.carousel')) return;

        // se já caiu no fallback, não mistura os dois
        if (c.classList.contains('bb-fallback')) return;

        $c.owlCarousel({
          loop: true,
          margin: 18,
          nav: false,
          dots: false,
          autoplay: true,
          autoplayTimeout: 1600,      // bem rapidinho
          autoplayHoverPause: false,
          smartSpeed: 450,
          autoplaySpeed: 450,
          slideBy: 1,
          responsive: {
            0:    { items: 1 },
            768:  { items: 2 },
            1100: { items: 3 } // 3 colunas no desktop
          }
        });
        return;
      }

      // Dá um tempo para o JS do sistema carregar (normalmente no footer)
      if (__bb_try < 6) return;

      // Fallback (sem Owl): mantém 1/2/3 colunas e rola automaticamente
      if (!c.classList.contains('bb-fallback')) c.classList.add('bb-fallback');
      if (c.dataset.bbInit === '1') return;
      c.dataset.bbInit = '1';

      var itens = Array.prototype.slice.call(c.querySelectorAll('.item'));
      if (itens.length <= 1) return;

      var idx = 0;
      var timer = null;

      function rolar(){
        itens = Array.prototype.slice.call(c.querySelectorAll('.item'));
        if (!itens.length) return;

        idx = (idx + 1) % itens.length;
        var alvo = itens[idx];
        if (!alvo) return;

        c.scrollTo({ left: alvo.offsetLeft, behavior: 'smooth' });
      }

      timer = setInterval(rolar, 1600);

      // Pausa no hover (desktop) para não "brigar" com o usuário
      c.addEventListener('mouseenter', function(){
        if (timer) { clearInterval(timer); timer = null; }
      });
      c.addEventListener('mouseleave', function(){
        if (!timer) { timer = setInterval(rolar, 1600); }
      });

    } catch(e) {}
  }

  // Tenta em vários momentos (o JS/CSS do Owl pode estar no footer do sistema)
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAvaliacoesCarousel);
  } else {
    initAvaliacoesCarousel();
  }
  window.addEventListener('load', initAvaliacoesCarousel);

  // Retry curto para pegar carregamentos tardios
  var tentativas = 0;
  var t = setInterval(function(){
    tentativas++;
    initAvaliacoesCarousel();
    if (tentativas >= 12) clearInterval(t);
  }, 300);
})();
</script>

  </div>
</section>

<script>
(function(){
  // Busca contador de visitas (arquivo já existente no projeto: /contador-visitas.php)
  // Padrão multi-tenant: envia ?loja=<slug> quando estiver em /loja/<slug>/
  var slug = "<?= bb_h($bb_slug_publico) ?>";
  var url = "/contador-visitas.php" + (slug ? ("?loja=" + encodeURIComponent(slug)) : "");

  function setCounter(valorTexto){
    var el = document.querySelector("#visitasCounter1999 .counter-1999");
    if (el) el.textContent = valorTexto;
  }

  try {
    fetch(url, { credentials: "same-origin" })
      .then(function(r){ return r.json(); })
      .then(function(js){
        if (js && typeof js.formatado !== "undefined") setCounter(js.formatado);
        else if (js && typeof js.visitas !== "undefined") setCounter(String(js.visitas));
      })
      .catch(function(){ /* silencioso */ });
  } catch(e) { /* silencioso */ }
})();
</script>


</section>

    
<section class="bb-business-consultoria" aria-label="Plano Business BarberBot">
  <div class="bb-business-wrap">
    <div class="bb-business-grid">
      <div>
        <div class="bb-business-badge">
          <i class="fa-solid fa-briefcase"></i>
          Plano Business
        </div>

        <h2 class="bb-business-title">
          No Business, você recebe <span>sistema + orientação digital.</span>
        </h2>

        <p class="bb-business-text">
          O profissional não precisa apenas de uma agenda. Ele precisa ser encontrado, passar confiança e transformar
          contatos em atendimentos. Por isso, no plano Business, o BarberBot entrega estrutura de gestão e apoio para
          fortalecer sua presença no Google, Instagram, Facebook e WhatsApp.
        </p>

        <p class="bb-business-text">
          A proposta é ajudar você a começar com mais clareza: serviços organizados, horários configurados,
          página profissional, Google Perfil de Empresa e campanhas no Google e Meta com uma comunicação mais forte.
        </p>

        <a href="criar-barbearia.php" class="btn btn-primary" target="_blank">Quero o plano Business</a>
      </div>

      <div class="bb-business-panel">
        <div class="bb-business-list">
          <div class="bb-business-item">
            <span><i class="fa-solid fa-calendar-check"></i> Configuração inicial</span>
            <p>Serviços, horários, profissionais, agenda e estrutura básica para começar sem bagunça.</p>
          </div>

          <div class="bb-business-item">
            <span><i class="fa-brands fa-google"></i> Google Perfil de Empresa</span>
            <p>Apoio para organizar dados, presença local e aparência profissional no Google.</p>
          </div>

          <div class="bb-business-item">
            <span><i class="fa-solid fa-bullhorn"></i> Google Ads e Meta Ads</span>
            <p>Campanhas para divulgar serviços, atrair contatos e levar mais pessoas para o WhatsApp.</p>
          </div>

          <div class="bb-business-item">
            <span><i class="fa-solid fa-globe"></i> Landing page profissional</span>
            <p>Página de apresentação para mostrar serviços, diferenciais, botões de contato e chamada para agendamento.</p>
          </div>
        </div>
      </div>
    </div>
  </div>
</section>

<section class="pricing" id="planos">
        <h2 class="section-title">Escolha o plano ideal</h2>
        <p class="section-subtitle">Comece grátis. Evolua conforme seu negócio cresce.</p>
        <div class="toggle-container">
            <span style="color: var(--text-light);">Mensal</span>
            <label class="switch">
                <input type="checkbox" id="pricing-toggle" onchange="togglePricing()">
                <span class="slider"></span>
            </label>
            <span style="color: var(--text-light);">Anual <span style="color: var(--accent); font-size: 0.8rem;">(20% OFF)</span></span>
        </div>
        <div class="price-cards">
            <div class="price-card">
                <h3>Free</h3>
                <p style="color:var(--text-gray); font-size: 0.9rem;">Início da jornada.</p>
                <div class="price" id="price-free">R$ 0,00<span>/sempre</span></div>
                <ul class="check-list" style="text-align: left; margin-bottom: 30px;">
                    <li><i class="fa-solid fa-check"></i> Profissionais limitados</li>
                    <li><i class="fa-solid fa-check"></i> Agendamentos limitados</li>
                    <li><i class="fa-solid fa-check"></i> Sem API paga</li>
                    <li><i class="fa-solid fa-check"></i> Sub-domínio</li>
                    <li class="bb-recurso-nao-incluso" title="Não inclui"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Não incluso: WhatsApp automático</li>
                    <li class="bb-recurso-nao-incluso" title="Não inclui"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Não incluso: Confirmação e lembrete</li>
                    <li class="bb-recurso-nao-incluso" title="Não inclui"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Não incluso: Campanha marketing</li>
                    <li class="bb-recurso-nao-incluso" title="Não inclui"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Não incluso: Landing page</li>
                    <li class="bb-recurso-nao-incluso" title="Não inclui"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Não incluso: Plano assinatura</li>
                    <li class="bb-recurso-nao-incluso" title="Não inclui"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Não incluso: Perfil de Empresa</li>
                    <li class="bb-recurso-nao-incluso" title="Não inclui"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Não incluso: Camp. Google Meta</li>
                </ul>
                <a href="criar-barbearia.php" class="btn btn-outline" style="width: 100%;" target="_blank">Grátis para sempre</a>
            </div>
            <div class="price-card">
                <h3>Profissional</h3>
                <p style="color:var(--text-gray); font-size: 0.9rem;">Para quem está iniciando.</p>
                <div class="price" id="price-pro">R$ 69,90<span>/mês</span></div>
                <ul class="check-list" style="text-align: left; margin-bottom: 30px;">
                    <li><i class="fa-solid fa-check"></i> Profissionais ilimitados</li>
                    <li><i class="fa-solid fa-check"></i> Agendamentos ilimitados</li>
                    <li><i class="fa-solid fa-check"></i> WhatsApp automático</li>
                    <li><i class="fa-solid fa-check"></i> Confirmação e lembrete</li>
                    <li><i class="fa-solid fa-check"></i> Sub-domínio</li>
                    <li><i class="fa-solid fa-check"></i> Campanha marketing</li>
                    <li><i class="fa-solid fa-check"></i> Portfólio</li>
                    <li><i class="fa-solid fa-check"></i> API paga</li>
                    <li class="bb-recurso-nao-incluso" title="Não inclui"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Não incluso: Domínio person.</li>
                    <li class="bb-recurso-nao-incluso" title="Não inclui"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Não incluso: Plano assinatura</li>
                    <li class="bb-recurso-nao-incluso" title="Não inclui"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Não incluso: Perfil de Empresa</li>
                    <li class="bb-recurso-nao-incluso" title="Não inclui"><i class="fa-solid fa-xmark" aria-hidden="true"></i> Não incluso: Camp. Google Meta</li>
                </ul>
                <a href="criar-barbearia.php" class="btn btn-outline" style="width: 100%;" target="_blank">Começar 30 DIAS GRÁTIS</a>
            </div>
            <div class="price-card popular">
                <div class="badge">MAIS POPULAR</div>
                <h3>Business</h3>
                <p style="color:var(--text-gray); font-size: 0.9rem;">Para profissionais que querem sistema, presença digital e campanhas.</p>
                <div class="price" id="price-biz">R$ 97,00<span>/mês</span></div>
                <ul class="check-list" style="text-align: left; margin-bottom: 30px;">
                    <li><i class="fa-solid fa-check"></i> Profissionais ilimitados</li>
                    <li><i class="fa-solid fa-check"></i> Agendamentos ilimitados</li>
                    <li><i class="fa-solid fa-check"></i> WhatsApp automático</li>
                    <li><i class="fa-solid fa-check"></i> Confirmação e lembrete</li>
                    <li><i class="fa-solid fa-check"></i> Domínio personalizado</li>
                    <li><i class="fa-solid fa-check"></i> Campanha marketing</li>
                    <li><i class="fa-solid fa-check"></i> Google Perfil de Empresa</li>
                    <li><i class="fa-solid fa-check"></i> Campanhas Google e Meta</li>
                    <li><i class="fa-solid fa-check"></i> Landing page profissional</li>
                    <li><i class="fa-solid fa-check"></i> Plano assinatura</li>
                    <li><i class="fa-solid fa-check"></i> API paga</li>
                    <li><i class="fa-solid fa-check"></i> Orientação inicial com Jacy Cordeiro</li>
                </ul>
                <a href="criar-barbearia.php" class="btn btn-primary" style="width: 100%;" target="_blank">TESTAR 30 DIAS GRÁTIS</a>
            </div>
        </div>

        <div class="bb-ads-transparency-note" style="max-width:1050px;margin:28px auto 0 auto;color:rgba(248,250,252,.78);font-size:.9rem;line-height:1.7;text-align:center;background:rgba(15,23,42,.68);border:1px solid rgba(148,163,184,.18);border-radius:16px;padding:16px 20px;">
            <strong style="color:#f8fafc;">Transparência:</strong>
            os recursos, valores e condições podem variar conforme configuração, contratação, integrações disponíveis e uso do sistema.
            O BarberBot ajuda na organização, automação e gestão do negócio, mas resultados comerciais dependem da rotina da equipe,
            volume de clientes, estratégia de atendimento e uso correto da plataforma.
        </div>
    </section>
    <script>
  // ✅ flag: modal só existe/abre quando o index.php define isso
  window.BB_SHOW_LEAD_MODAL = true;
</script>

<!-- ================= MODAL LEAD (Novo Cliente) 
<div class="modal fade" id="leadModal" tabindex="-1" role="dialog">
  <div class="modal-dialog modal-dialog-centered">
    <div class="modal-content" style="background:#151515; color:#fff; border-radius:16px;">
      <div class="modal-header" style="border-bottom:1px solid rgba(255,255,255,.1);">
        <h5 class="modal-title font-weight-bold">🎉 Receba Avaliação Gratuita</h5>
        <button type="button" class="close text-white" data-dismiss="modal">&times;</button>
      </div>
      <form id="leadForm" autocomplete="off">
        <input type="hidden" name="recaptcha_token" id="recaptcha_token" value="">
        <div class="modal-body">
          <div class="p-3 mb-3 rounded" style="background:#f6f7fb; border:1px solid #e6e8ef; color:#333;">
            <div class="d-flex">
              <div style="font-size:22px; margin-right:10px;">💬</div>
              <div class="small">
                <strong>Converse com um especialista sem custo.</strong><br>
                Em minutos respondemos no WhatsApp.
              </div>
            </div>
          </div>
          <div class="form-group mb-3">
            <input type="text" class="form-control" name="nome" id="leadNome" required placeholder="Seu nome completo" style="width:100%; padding:10px;">
          </div>
          <div class="form-group">
            <input type="tel" class="form-control" name="telefone" id="leadZap" placeholder="(45) 99999-0000" required style="width:100%; padding:10px;">
          </div>
        </div>
        <div class="modal-footer" style="border-top:1px solid rgba(255,255,255,.1);">
          <button type="submit" class="btn btn-primary" id="leadBtn" style="background:#00a8ff; border:none; width:100%;">Cadastrar</button>
        </div>
      </form>
    </div>
  </div>
</div>================= -->



<?php
// ===== reCAPTCHA v3 na HOME PRINCIPAL (MASTER) =====
// Mantém o token do formulário quando existir e força o selo do reCAPTCHA v3 na página inicial.
$__bb_site_key = (defined('RECAPTCHA_SITE_KEY')) ? trim((string)RECAPTCHA_SITE_KEY) : '';
if ($__bb_site_key !== '') {
?>
<style>
  .grecaptcha-badge{
    visibility:visible !important;
    opacity:1 !important;
    display:block !important;
    z-index:2147483000 !important;
    right:12px !important;
    bottom:92px !important;
  }
  .grecaptcha-badge iframe{
    visibility:visible !important;
    opacity:1 !important;
    display:block !important;
  }
  @media (max-width:768px){
    .grecaptcha-badge{bottom:86px !important;}
  }
</style>
<script>
(function(){
  'use strict';

  var SITE_KEY = <?php echo json_encode($__bb_site_key, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
  var ACTION_PADRAO = 'homepage';
  var recaptchaCarregado = false;
  var recaptchaCarregando = false;
  var tentativasBadge = 0;

  function formLead(){
    return document.getElementById('leadForm');
  }

  function setToken(token){
    var form = formLead();
    if (!form) return;

    var input = form.querySelector('input[name="recaptcha_token"]');
    if(input){ input.value = token || ''; }
  }

  function mostrarBadge(){
    var badge = document.querySelector('.grecaptcha-badge');
    if (badge) {
      badge.style.setProperty('visibility', 'visible', 'important');
      badge.style.setProperty('opacity', '1', 'important');
      badge.style.setProperty('display', 'block', 'important');
      badge.style.setProperty('z-index', '2147483000', 'important');
      badge.style.setProperty('right', '12px', 'important');
      badge.style.setProperty('bottom', '92px', 'important');
      return;
    }

    tentativasBadge++;
    if (tentativasBadge <= 12) {
      setTimeout(mostrarBadge, 500);
    }
  }

  function executarToken(action){
    if(typeof grecaptcha === 'undefined' || !grecaptcha.ready || typeof grecaptcha.execute !== 'function'){
      mostrarBadge();
      return;
    }

    grecaptcha.ready(function(){
      try {
        grecaptcha.execute(SITE_KEY, {action: action || ACTION_PADRAO}).then(function(token){
          setToken(token);
          mostrarBadge();
        }).catch(function(){
          setToken('');
          mostrarBadge();
        });
      } catch (e) {
        setToken('');
        mostrarBadge();
      }
    });
  }

  function aguardarScriptExistente(action){
    var limite = 0;
    var timer = setInterval(function(){
      limite++;
      if(typeof grecaptcha !== 'undefined' && grecaptcha.ready && typeof grecaptcha.execute === 'function'){
        clearInterval(timer);
        recaptchaCarregado = true;
        recaptchaCarregando = false;
        executarToken(action || ACTION_PADRAO);
      } else if (limite >= 24) {
        clearInterval(timer);
        recaptchaCarregando = false;
        mostrarBadge();
      }
    }, 250);
  }

  function carregarRecaptcha(action){
    if(!SITE_KEY){ return; }

    if(typeof grecaptcha !== 'undefined' && grecaptcha.ready && typeof grecaptcha.execute === 'function'){
      recaptchaCarregado = true;
      recaptchaCarregando = false;
      executarToken(action || ACTION_PADRAO);
      return;
    }

    if(recaptchaCarregado){
      executarToken(action || ACTION_PADRAO);
      return;
    }

    if(recaptchaCarregando){
      return;
    }

    var existente = document.querySelector('script[src*="www.google.com/recaptcha/api.js"]');
    if(existente){
      recaptchaCarregando = true;
      aguardarScriptExistente(action || ACTION_PADRAO);
      return;
    }

    recaptchaCarregando = true;

    var s = document.createElement('script');
    s.src = 'https://www.google.com/recaptcha/api.js?render=' + encodeURIComponent(SITE_KEY);
    s.async = true;
    s.defer = true;
    s.setAttribute('data-bb-index-recaptcha', '1');
    s.onload = function(){
      recaptchaCarregado = true;
      recaptchaCarregando = false;
      executarToken(action || ACTION_PADRAO);
    };
    s.onerror = function(){
      recaptchaCarregando = false;
      mostrarBadge();
    };

    document.head.appendChild(s);
  }

  function iniciarRecaptchaIndex(){
    carregarRecaptcha(ACTION_PADRAO);
    setTimeout(function(){ carregarRecaptcha(ACTION_PADRAO); }, 900);
    setTimeout(mostrarBadge, 1600);

    var form = formLead();
    if(form){
      form.addEventListener('focusin', function(){ carregarRecaptcha('lead'); }, true);
      form.addEventListener('submit', function(){ carregarRecaptcha('lead'); }, true);
    }

    var modal = document.getElementById('leadModal');
    if(modal){
      modal.addEventListener('shown.bs.modal', function(){ carregarRecaptcha('lead'); }, false);
      modal.addEventListener('show.bs.modal', function(){ carregarRecaptcha('lead'); }, false);
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', iniciarRecaptchaIndex);
  } else {
    iniciarRecaptchaIndex();
  }
})();
</script>
<?php } ?>


<?php 
// 2. Carrega o rodapé (que está na pasta marketplace)
require_once __DIR__ . '/marketplace/footer.php'; 
?>

<script>
(function(){
  function onIdle(cb, timeout){
    if('requestIdleCallback' in window){ requestIdleCallback(cb, {timeout: timeout || 2200}); }
    else { setTimeout(cb, Math.min(timeout || 1200, 2500)); }
  }

  function carregarVideo(video){
    if (!video || video.dataset.bbCarregado === '1') return;
    var sources = video.querySelectorAll('source[data-src]');
    for (var i = 0; i < sources.length; i++) {
      var dataSrc = sources[i].getAttribute('data-src');
      if (dataSrc) {
        sources[i].setAttribute('src', dataSrc);
        sources[i].removeAttribute('data-src');
      }
    }
    video.load();
    var p = video.play();
    if (p && typeof p.catch === 'function') { p.catch(function(){}); }
    video.dataset.bbCarregado = '1';

    var tela = video.closest('.laptop-screen-wide');
    if (tela) tela.classList.add('bb-screen-video-loaded');
  }

  // Hero: mantém o vídeo, mas não baixa o MP4 no primeiro carregamento.
  // Isso reduz principalmente LCP e Total Blocking Time no PageSpeed.
  var hero = document.querySelector('.bb-video-hero');
  if(hero){
    var heroAgendado = false;
    function agendarHero(){
      if (heroAgendado) return;
      heroAgendado = true;
      onIdle(function(){ carregarVideo(hero); }, 6500);
    }
    ['pointerdown','touchstart','keydown','scroll'].forEach(function(evt){
      window.addEventListener(evt, agendarHero, {once:true, passive:true});
    });
  }

  var videos = document.querySelectorAll('.bb-video-lazy');
  if (!videos.length) return;

  if ('IntersectionObserver' in window) {
    var io = new IntersectionObserver(function(entries, observer){
      entries.forEach(function(entry){
        if (entry.isIntersecting || entry.intersectionRatio > 0) {
          onIdle(function(){ carregarVideo(entry.target); }, 3000);
          observer.unobserve(entry.target);
        }
      });
    }, { rootMargin: '80px 0px', threshold: 0.05 });

    Array.prototype.forEach.call(videos, function(video){
      io.observe(video);
    });
  } else {
    Array.prototype.forEach.call(videos, function(video){
      onIdle(function(){ carregarVideo(video); }, 3500);
    });
  }
})();
</script>
