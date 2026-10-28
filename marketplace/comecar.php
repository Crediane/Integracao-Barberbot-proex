<?php
// ARQUIVO: comecar.php
// Página separada para coleta de dados dos profissionais e serviços BarberBot.
// Não grava no banco. Apenas coleta, valida e gera resumo para o administrador cadastrar no sistema.
header('Content-Type: text/html; charset=utf-8');

if (!function_exists('bb_onboarding_limpar_slug')) {
    function bb_onboarding_limpar_slug($valor) {
        $valor = strtolower(trim((string)$valor));
        return (string)preg_replace('/[^a-z0-9_-]/', '', $valor);
    }
}

$bbOnboardingReqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$bbOnboardingSlugUrl = '';

if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $bbOnboardingReqPath, $bbOnboardingMatch)) {
    $bbOnboardingSlugUrl = bb_onboarding_limpar_slug($bbOnboardingMatch[1]);
} elseif (!empty($_GET['loja'])) {
    $bbOnboardingSlugUrl = bb_onboarding_limpar_slug($_GET['loja']);
} elseif (!empty($_POST['loja'])) {
    $bbOnboardingSlugUrl = bb_onboarding_limpar_slug($_POST['loja']);
}

// Converte o link legado /comecar.php?loja={slug} para /loja/{slug}/comecar.php.
$bbOnboardingHost = strtolower(preg_replace('/:\d+$/', '', (string)($_SERVER['HTTP_HOST'] ?? '')));
if (
    ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET'
    && $bbOnboardingSlugUrl !== ''
    && $bbOnboardingReqPath === '/comecar.php'
    && isset($_GET['loja'])
    && in_array($bbOnboardingHost, ['jc.tec.br', 'www.jc.tec.br'], true)
) {
    header('Location: /loja/' . rawurlencode($bbOnboardingSlugUrl) . '/comecar.php', true, 302);
    exit;
}

$nomeSistema = 'BarberBot Software';
$logoSistema = '/sistema/img/logo.png';
$whatsappAdmin = '5545999580058';
$apiEnvioAdmin = 'menuia';
$tokenEnvioAdmin = '';
$instanciaEnvioAdmin = '';

if (!function_exists('bb_onboarding_config_valor')) {
    function bb_onboarding_config_valor($dados, array $chaves) {
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

if (!function_exists('bb_onboarding_normalizar_cfg_db')) {
    function bb_onboarding_normalizar_cfg_db($cfg) {
        if (!is_array($cfg)) {
            return [];
        }

        return [
            'host' => bb_onboarding_config_valor($cfg, ['host', 'servidor', 'server', 'hostname', 'db_host', 'host_banco']),
            'db' => bb_onboarding_config_valor($cfg, ['db', 'dbname', 'database', 'banco', 'nome_banco', 'banco_dados', 'database_name']),
            'user' => bb_onboarding_config_valor($cfg, ['user', 'usuario', 'username', 'login', 'db_user', 'usuario_banco']),
            'pass' => bb_onboarding_config_valor($cfg, ['pass', 'senha', 'password', 'db_pass', 'senha_banco'])
        ];
    }
}

if (!function_exists('bb_onboarding_config_db_atual')) {
    function bb_onboarding_config_db_atual() {
        if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
            return '';
        }

        try {
            $stmtDbAtual = $GLOBALS['pdo']->query('SELECT DATABASE() AS banco_atual');
            $rowDbAtual = $stmtDbAtual ? $stmtDbAtual->fetch(PDO::FETCH_ASSOC) : false;
            return is_array($rowDbAtual) && !empty($rowDbAtual['banco_atual']) ? (string)$rowDbAtual['banco_atual'] : '';
        } catch (Throwable $e) {
            return '';
        }
    }
}

if (!function_exists('bb_onboarding_db_master_derivado')) {
    function bb_onboarding_db_master_derivado($dbAtual) {
        $dbAtual = trim((string)$dbAtual);
        if ($dbAtual === '') {
            return '';
        }

        if (stripos($dbAtual, '_master') !== false || strtolower($dbAtual) === 'master') {
            return $dbAtual;
        }

        if (strpos($dbAtual, '_') !== false) {
            $partes = explode('_', $dbAtual);
            array_pop($partes);
            $prefixo = implode('_', $partes);
            if ($prefixo !== '') {
                return $prefixo . '_master';
            }
        }

        return $dbAtual . '_master';
    }
}

if (!function_exists('bb_onboarding_add_cfg_db_candidata')) {
    function bb_onboarding_add_cfg_db_candidata(array &$candidatas, $cfg) {
        $cfg = bb_onboarding_normalizar_cfg_db($cfg);

        if (empty($cfg['host']) || empty($cfg['db']) || empty($cfg['user'])) {
            return;
        }

        $chave = $cfg['host'] . '|' . $cfg['db'] . '|' . $cfg['user'];
        if (!isset($candidatas[$chave])) {
            $candidatas[$chave] = $cfg;
        }
    }
}

if (!function_exists('bb_onboarding_configs_db_candidatas')) {
    function bb_onboarding_configs_db_candidatas() {
        $candidatas = [];

        // As credenciais de suporte e envio pertencem exclusivamente ao
        // Banco Global/Administrativo. Nunca usa o PDO atual do tenant nem
        // deriva o nome do banco a partir do banco individual da barbearia.
        $marketplaceConfig = __DIR__ . '/config/marketplace.php';
        if (!is_file($marketplaceConfig)) {
            return [];
        }

        $cfgMarketplace = require $marketplaceConfig;
        if (!is_array($cfgMarketplace) || empty($cfgMarketplace['auth_master']) || !is_array($cfgMarketplace['auth_master'])) {
            return [];
        }

        bb_onboarding_add_cfg_db_candidata($candidatas, $cfgMarketplace['auth_master']);
        return array_values($candidatas);
    }
}

if (!function_exists('bb_onboarding_criar_pdo_config')) {
    function bb_onboarding_criar_pdo_config(array $cfg) {
        try {
            return new PDO(
                'mysql:host=' . $cfg['host'] . ';dbname=' . $cfg['db'] . ';charset=utf8mb4',
                $cfg['user'],
                $cfg['pass'] ?? '',
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

if (!function_exists('bb_onboarding_config_master_pdo')) {
    function bb_onboarding_config_master_pdo() {
        foreach (bb_onboarding_configs_db_candidatas() as $cfg) {
            $pdoMaster = bb_onboarding_criar_pdo_config($cfg);
            if ($pdoMaster instanceof PDO) {
                return $pdoMaster;
            }
        }

        return null;
    }
}

if (!function_exists('bb_onboarding_ler_config_por_pdo')) {
    function bb_onboarding_ler_config_por_pdo($pdoConfig) {
        if (!($pdoConfig instanceof PDO)) {
            return [];
        }

        try {
            $stmt = $pdoConfig->query("SELECT * FROM config ORDER BY id DESC LIMIT 1");
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            return is_array($row) ? $row : [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('bb_onboarding_ler_config_schema_por_pdo')) {
    function bb_onboarding_ler_config_schema_por_pdo($pdoConfig, $nomeBanco) {
        if (!($pdoConfig instanceof PDO)) {
            return [];
        }

        $nomeBanco = trim((string)$nomeBanco);
        if ($nomeBanco === '') {
            return [];
        }

        try {
            $schema = str_replace('`', '``', $nomeBanco);
            $stmt = $pdoConfig->query("SELECT * FROM `" . $schema . "`.`config` ORDER BY id DESC LIMIT 1");
            $row = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;
            return is_array($row) ? $row : [];
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('bb_onboarding_config_tem_credenciais_whatsapp')) {
    function bb_onboarding_config_tem_credenciais_whatsapp(array $row) {
        return (
            bb_onboarding_config_valor($row, ['telefone_whatsapp', 'whatsapp_sistema', 'tel_whatsapp']) !== '' &&
            bb_onboarding_config_valor($row, ['token']) !== '' &&
            bb_onboarding_config_valor($row, ['instancia']) !== ''
        );
    }
}

if (!function_exists('bb_onboarding_ler_config_admin')) {
    function bb_onboarding_ler_config_admin() {
        foreach (bb_onboarding_configs_db_candidatas() as $cfg) {
            $pdoMaster = bb_onboarding_criar_pdo_config($cfg);
            $row = bb_onboarding_ler_config_por_pdo($pdoMaster);

            if (!empty($row)) {
                return $row;
            }
        }

        return [];
    }
}

if (!function_exists('bb_onboarding_aplicar_config_admin')) {
    function bb_onboarding_aplicar_config_admin(array $configAdminMaster) {
        global $nomeSistema, $logoSistema, $whatsappAdmin, $apiEnvioAdmin, $tokenEnvioAdmin, $instanciaEnvioAdmin;

        $nomeConfig = bb_onboarding_config_valor($configAdminMaster, ['nome_sistema', 'nome']);
        if ($nomeConfig !== '') {
            $nomeSistema = $nomeConfig;
        }

        $logoConfig = bb_onboarding_config_valor($configAdminMaster, ['logo_sistema', 'logo']);
        if ($logoConfig !== '') {
            $logoSistema = '/sistema/img/' . ltrim($logoConfig, '/');
        }

        $whatsappConfig = bb_onboarding_config_valor($configAdminMaster, ['telefone_whatsapp', 'whatsapp_sistema', 'tel_whatsapp']);
        if ($whatsappConfig !== '') {
            $whatsappAdmin = preg_replace('/\D+/', '', $whatsappConfig);
        }

        $apiConfig = bb_onboarding_config_valor($configAdminMaster, ['api']);
        if ($apiConfig !== '') {
            $apiEnvioAdmin = $apiConfig;
        }

        $tokenConfig = bb_onboarding_config_valor($configAdminMaster, ['token']);
        if ($tokenConfig !== '') {
            $tokenEnvioAdmin = $tokenConfig;
        }

        $instanciaConfig = bb_onboarding_config_valor($configAdminMaster, ['instancia']);
        if ($instanciaConfig !== '') {
            $instanciaEnvioAdmin = $instanciaConfig;
        }
    }
}

if (file_exists(__DIR__ . '/sistema/conexao.php')) {
    require_once __DIR__ . '/sistema/conexao.php';

    // Em contexto de loja, confirma que o slug, a sessão e o banco aberto pertencem ao mesmo tenant.
    $bbOnboardingSlugSessao = bb_onboarding_limpar_slug($_SESSION['tenant_slug'] ?? '');
    $bbOnboardingSlugAtual = $bbOnboardingSlugSessao !== '' ? $bbOnboardingSlugSessao : $bbOnboardingSlugUrl;
    $bbOnboardingTenantDb = trim((string)($_SESSION['tenant_db'] ?? ''));
    $bbOnboardingSessaoTenant = strpos((string)session_name(), 'BB_TENANT') === 0;

    if ($bbOnboardingSlugUrl !== '' && $bbOnboardingSlugSessao !== '' && $bbOnboardingSlugUrl !== $bbOnboardingSlugSessao) {
        http_response_code(403);
        echo 'A loja informada não corresponde ao contexto atual.';
        exit;
    }

    if ($bbOnboardingSlugAtual !== '') {
        $bbOnboardingBancoAtual = '';
        if (isset($pdo) && $pdo instanceof PDO) {
            try {
                $bbOnboardingBancoAtual = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
            } catch (Throwable $e) {
                $bbOnboardingBancoAtual = '';
            }
        }

        if (
            !$bbOnboardingSessaoTenant
            || $bbOnboardingTenantDb === ''
            || !isset($pdo)
            || !($pdo instanceof PDO)
            || $bbOnboardingBancoAtual === ''
            || strcasecmp($bbOnboardingBancoAtual, $bbOnboardingTenantDb) !== 0
        ) {
            http_response_code(403);
            echo 'Não foi possível confirmar o Banco de Dados Individual desta barbearia.';
            exit;
        }
    }

    $configAdminMaster = bb_onboarding_ler_config_admin();
    bb_onboarding_aplicar_config_admin($configAdminMaster);

    if (!empty($nome_sistema)) {
        $nomeSistema = (string)$nome_sistema;
    }

    if (!empty($logo_sistema)) {
        $logoSistema = '/sistema/img/' . ltrim((string)$logo_sistema, '/');
    }

    // O nome e a logo podem acompanhar a identidade visual da loja,
    // mas WhatsApp, API, token e instância de recebimento permanecem os
    // valores carregados do Banco Global/Administrativo acima.
}

if ($whatsappAdmin !== '' && strlen($whatsappAdmin) <= 11) {
    $whatsappAdmin = '55' . $whatsappAdmin;
}

function bb_onboarding_h($valor) {
    return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
}

function bb_onboarding_public_base_url() {
    $httpsOn = (
        (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
        (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') ||
        (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
    );

    $host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
    if ($host === '') {
        return '';
    }

    return ($httpsOn ? 'https://' : 'http://') . $host;
}

$bbOnboardingSlugCanonico = bb_onboarding_limpar_slug($_SESSION['tenant_slug'] ?? $bbOnboardingSlugUrl);
$bbOnboardingBaseUrl = bb_onboarding_public_base_url();
$bbOnboardingCanonicalPath = $bbOnboardingSlugCanonico !== ''
    ? '/loja/' . rawurlencode($bbOnboardingSlugCanonico) . '/comecar.php'
    : '/comecar.php';
$bbOnboardingCanonicalUrl = ($bbOnboardingBaseUrl !== '' ? rtrim($bbOnboardingBaseUrl, '/') : '') . $bbOnboardingCanonicalPath;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'upload_onboarding_imagem') {
    header('Content-Type: application/json; charset=utf-8');

    $arquivo = null;
    foreach (['arquivo', 'file', 'imagem'] as $campoArquivo) {
        if (!empty($_FILES[$campoArquivo]) && is_array($_FILES[$campoArquivo]) && (int)($_FILES[$campoArquivo]['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_OK) {
            $arquivo = $_FILES[$campoArquivo];
            break;
        }
    }

    if (!$arquivo) {
        echo json_encode(['ok' => false, 'mensagem' => 'Nenhuma imagem recebida'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $nomeOriginal = (string)($arquivo['name'] ?? 'imagem');
    $extensao = strtolower(pathinfo($nomeOriginal, PATHINFO_EXTENSION));
    $permitidas = ['jpg', 'jpeg', 'png', 'webp', 'gif'];

    if (!in_array($extensao, $permitidas, true)) {
        echo json_encode(['ok' => false, 'mensagem' => 'Formato de imagem inválido'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $tmp = (string)($arquivo['tmp_name'] ?? '');
    if ($tmp === '' || !is_uploaded_file($tmp)) {
        echo json_encode(['ok' => false, 'mensagem' => 'Imagem inválida'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $pastaRelativa = 'sistema/img/barberbot-onboarding';
    $pastaDestino = __DIR__ . '/' . $pastaRelativa;

    if (!is_dir($pastaDestino)) {
        @mkdir($pastaDestino, 0755, true);
    }

    if (!is_dir($pastaDestino) || !is_writable($pastaDestino)) {
        echo json_encode(['ok' => false, 'mensagem' => 'Pasta de upload sem permissão'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $nomeLimpo = preg_replace('/[^a-zA-Z0-9._-]+/', '-', pathinfo($nomeOriginal, PATHINFO_FILENAME));
    $nomeLimpo = trim((string)$nomeLimpo, '-_.');
    if ($nomeLimpo === '') {
        $nomeLimpo = 'imagem';
    }

    $nomeFinal = date('YmdHis') . '_' . substr(sha1(uniqid('', true)), 0, 10) . '_' . $nomeLimpo . '.' . $extensao;
    $destino = $pastaDestino . '/' . $nomeFinal;

    if (!move_uploaded_file($tmp, $destino)) {
        echo json_encode(['ok' => false, 'mensagem' => 'Não foi possível salvar a imagem'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    $baseUrl = bb_onboarding_public_base_url();
    $url = ($baseUrl !== '' ? rtrim($baseUrl, '/') : '') . '/' . $pastaRelativa . '/' . $nomeFinal;

    echo json_encode([
        'ok' => true,
        'nome' => $nomeOriginal,
        'arquivo' => $nomeFinal,
        'url' => $url,
        'caminho' => '/' . $pastaRelativa . '/' . $nomeFinal
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['acao'] ?? '') === 'enviar_admin_onboarding') {
    header('Content-Type: text/plain; charset=utf-8');

    $relatorioRaw = trim((string)($_POST['relatorio'] ?? ''));
    if ($relatorioRaw === '') {
        echo 'Relatório vazio';
        exit;
    }

    $configEnvioAtual = bb_onboarding_ler_config_admin();
    if (!empty($configEnvioAtual)) {
        bb_onboarding_aplicar_config_admin($configEnvioAtual);
    }

    $telefone = preg_replace('/\D+/', '', (string)$whatsappAdmin);
    if ($telefone !== '' && substr($telefone, 0, 2) !== '55' && strlen($telefone) <= 11) {
        $telefone = '55' . $telefone;
    }

    if ($telefone === '' || strlen($telefone) < 12) {
        echo 'WhatsApp do administrador não encontrado na tabela config';
        exit;
    }

    $ns = $nomeSistema ?: 'BarberBot Software';
    
    $dados = json_decode($relatorioRaw, true);
    if (is_array($dados)) {
        $msgTxt = "✅ *CADASTRO CONCLUÍDO COM SUCESSO!*\n\n";
        $msgTxt .= "📌 *Novo onboarding recebido pelo {$ns}*\n";
        $msgTxt .= "📅 Data: " . ($dados['data_coleta'] ?? date('d/m/Y H:i:s')) . "\n\n";
        
        if (!empty($dados['profissionais']) && is_array($dados['profissionais'])) {
            $msgTxt .= "👤 *PROFISSIONAIS COLETADOS:*\n";
            foreach ($dados['profissionais'] as $idx => $p) {
                $num = $idx + 1;
                $msgTxt .= "-----------------------------\n";
                $msgTxt .= "• Profissional {$num}: " . ($p['nome_completo'] ?? '') . "\n";
                $msgTxt .= "• E-mail: " . ($p['email'] ?? '') . "\n";
                $msgTxt .= "• WhatsApp: " . ($p['whatsapp'] ?? '') . "\n";
                $msgTxt .= "• Endereço: " . ($p['endereco_completo'] ?? '') . "\n";
                $msgTxt .= "• Intervalo: " . ($p['intervalo_agendamento_minutos'] ?? '30') . " minutos\n";
                $msgTxt .= "• Imagem Perfil: " . ($p['foto_perfil'] ?? '') . "\n";
                
                if (!empty($p['horarios_atendimento']) && is_array($p['horarios_atendimento'])) {
                    $msgTxt .= "• Escala de Horários:\n";
                    foreach ($p['horarios_atendimento'] as $h) {
                        if (!empty($h['ativo'])) {
                            $msgTxt .= "  - " . ($h['dia_nome'] ?? '') . ": " . ($h['inicio'] ?? '') . " às " . ($h['fim'] ?? '');
                            if (!empty($h['intervalo_almoco_inicio'])) {
                                $msgTxt .= " (Almoço: " . $h['intervalo_almoco_inicio'] . " às " . $h['intervalo_almoco_fim'] . ")";
                            }
                            $msgTxt .= "\n";
                        }
                    }
                }
            }
            $msgTxt .= "-----------------------------\n\n";
        }
        
        if (!empty($dados['servicos']) && is_array($dados['servicos'])) {
            $msgTxt .= "✂️ *SERVIÇOS COLETADOS:*\n";
            foreach ($dados['servicos'] as $idx => $s) {
                $num = $idx + 1;
                $msgTxt .= "-----------------------------\n";
                $msgTxt .= "• Serviço {$num}: " . ($s['nome_servico'] ?? '') . "\n";
                $msgTxt .= "• Valor: " . ($s['valor_servico'] ?? '') . "\n";
                $msgTxt .= "• Retorno estimado: " . ($s['dias_retorno'] ?? '') . " dias\n";
                $msgTxt .= "• Tempo de Execução: " . ($s['tempo_execucao_minutos'] ?? '') . " minutos\n";
                $msgTxt .= "• Commission: " . ($s['comissao_profissional_percentual'] ?? '0') . "%\n";
                $msgTxt .= "• Imagem Serviço: " . ($s['foto_servico'] ?? '') . "\n";
            }
            $msgTxt .= "-----------------------------\n";
        }
        $mensagem = $msgTxt;
    } else {
        $mensagem = "✅ *CADASTRO CONCLUÍDO COM SUCESSO!*\n\n📌 *Novo onboarding recebido pelo {$ns}*\n\n" . $relatorioRaw;
    }

    $api = trim((string)$apiEnvioAdmin);
    $token = trim((string)$tokenEnvioAdmin);
    $instancia = trim((string)$instanciaEnvioAdmin);
    $status_mensagem = '';

    if ($api === '') {
        $api = 'menuia';
    }

    $api_whatsapp = $api;
    $token_api = $token;
    $instancia_api = $instancia;
    $telefone_envio = $telefone;
    $telefone_whatsapp = $telefone;
    $numero = $telefone;
    $msg = $mensagem;

    if ($token === '' || $instancia === '') {
        echo 'Credenciais ausentes na tabela config do banco master';
        exit;
    }

    $sender = rtrim((string)($_SERVER['DOCUMENT_ROOT'] ?? ''), '/') . '/ajax/api-texto.php';
    if ($sender === '/ajax/api-texto.php' || !is_file($sender)) {
        $sender = __DIR__ . '/ajax/api-texto.php';
    }

    if (is_file($sender)) {
        require $sender;

        if (!empty($status_mensagem)) {
            echo 'Enviado com Sucesso';
        } else {
            echo 'Mensagem não enviada. Confira token, instância e WhatsApp do admin na tabela config.';
        }
        exit;
    }

    echo 'Arquivo de envio não encontrado em /ajax/api-texto.php';
    exit;
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Barberbot Admin - Cadastro dos Profissionais</title>
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="Coleta guiada dos dados dos profissionais e serviços BarberBot para configuração inicial da plataforma.">
    <link rel="canonical" href="<?php echo bb_onboarding_h($bbOnboardingCanonicalUrl); ?>">
    <link class="bb-favicon" rel="icon" type="image/x-icon" href="/images/favicon_512.webp">
    <link rel="shortcut icon" href="/images/favicon_512.webp">

    <link rel="preconnect" href="https://cdnjs.cloudflare.com" crossorigin>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">

    <style>
        :root {
            --bb-bg: #020617;
            --bb-panel: #0f172a;
            --bb-panel-2: #111827;
            --bb-border: rgba(148, 163, 184, 0.22);
            --bb-text: #f8fafc;
            --bb-muted: #cbd5e1;
            --bb-muted-2: #94a3b8;
            --bb-primary: #1d4ed8;
            --bb-primary-2: #0ea5e9;
            --bb-success: #22c55e;
            --bb-warning: #f59e0b;
            --bb-danger: #ef4444;
        }

        * {
            box-sizing: border-box;
        }

        body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background:
                radial-gradient(circle at 15% 10%, rgba(37, 99, 235, 0.26), transparent 30%),
                radial-gradient(circle at 85% 12%, rgba(14, 165, 233, 0.16), transparent 28%),
                var(--bb-bg);
            color: var(--bb-text);
            min-height: 100vh;
        }

        a {
            color: inherit;
        }

        .bb-topbar {
            width: 100%;
            padding: 18px 5%;
            background: rgba(2, 6, 23, 0.88);
            border-bottom: 1px solid var(--bb-border);
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 20px;
            position: sticky;
            top: 0;
            z-index: 10;
            backdrop-filter: blur(12px);
        }

        .bb-brand {
            display: flex;
            align-items: center;
            gap: 14px;
            text-decoration: none;
        }

        .bb-brand img {
            max-width: 110px;
            max-height: 58px;
            object-fit: contain;
            display: block;
        }

        .bb-brand-text strong {
            display: block;
            font-size: 1rem;
            line-height: 1.1;
        }

        .bb-brand-text span {
            display: block;
            margin-top: 3px;
            color: var(--bb-muted-2);
            font-size: 0.85rem;
        }

        .bb-topbar-badge {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            padding: 10px 14px;
            border-radius: 999px;
            background: rgba(37, 99, 235, 0.16);
            border: 1px solid rgba(96, 165, 250, 0.28);
            color: #dbeafe;
            font-weight: 800;
            font-size: 0.86rem;
            white-space: nowrap;
        }

        .bb-page {
            width: min(1180px, 92%);
            margin: 0 auto;
            padding: 48px 0 70px;
        }

        .bb-hero {
            display: grid;
            grid-template-columns: 1fr 0.9fr;
            gap: 30px;
            align-items: center;
            margin-bottom: 28px;
        }

        .bb-hero h1 {
            font-size: clamp(2rem, 4vw, 3.45rem);
            line-height: 1.08;
            margin: 0 0 16px;
            font-weight: 900;
        }

        .bb-hero h1 span {
            color: #60a5fa;
        }

        .bb-hero p {
            margin: 0;
            color: var(--bb-muted);
            font-size: 1.08rem;
            line-height: 1.75;
            max-width: 720px;
        }

        .bb-side-card {
            background: rgba(15, 23, 42, 0.82);
            border: 1px solid var(--bb-border);
            border-radius: 24px;
            padding: 24px;
            box-shadow: 0 24px 70px rgba(0, 0, 0, 0.28);
        }

        .bb-side-card h2 {
            margin: 0 0 14px;
            font-size: 1.35rem;
        }

        .bb-side-list {
            list-style: none;
            padding: 0;
            margin: 0;
            display: grid;
            gap: 12px;
        }

        .bb-side-list li {
            color: var(--bb-muted);
            display: flex;
            gap: 10px;
            line-height: 1.45;
        }

        .bb-side-list i {
            color: var(--bb-success);
            margin-top: 3px;
        }

        .bb-wizard {
            background: rgba(15, 23, 42, 0.9);
            border: 1px solid var(--bb-border);
            border-radius: 26px;
            overflow: hidden;
            box-shadow: 0 30px 90px rgba(0, 0, 0, 0.36);
        }

        .bb-wizard-head {
            padding: 22px 24px;
            background: linear-gradient(135deg, rgba(29, 78, 216, 0.28), rgba(14, 165, 233, 0.12));
            border-bottom: 1px solid var(--bb-border);
            display: flex;
            justify-content: space-between;
            gap: 18px;
            align-items: center;
        }

        .bb-wizard-title strong {
            display: block;
            font-size: 1.1rem;
        }

        .bb-wizard-title span {
            display: block;
            color: var(--bb-muted);
            margin-top: 5px;
            font-size: 0.92rem;
        }

        .bb-progress-wrap {
            min-width: 240px;
        }

        .bb-progress-label {
            color: var(--bb-muted);
            font-size: 0.86rem;
            margin-bottom: 8px;
            text-align: right;
        }

        .bb-progress {
            height: 10px;
            background: rgba(148, 163, 184, 0.18);
            border-radius: 999px;
            overflow: hidden;
        }

        .bb-progress-bar {
            height: 100%;
            width: 0;
            background: linear-gradient(90deg, var(--bb-primary), var(--bb-primary-2));
            border-radius: 999px;
            transition: width 0.25s ease;
        }

        .bb-chat {
            padding: 26px;
            min-height: 420px;
        }

        .bb-message {
            display: flex;
            gap: 12px;
            align-items: flex-start;
            margin-bottom: 18px;
        }

        .bb-avatar {
            width: 42px;
            height: 42px;
            border-radius: 50%;
            background: linear-gradient(135deg, var(--bb-primary), var(--bb-primary-2));
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 10px 30px rgba(29, 78, 216, 0.28);
        }

        .bb-bubble {
            background: rgba(2, 6, 23, 0.72);
            border: 1px solid rgba(148, 163, 184, 0.16);
            border-radius: 18px;
            padding: 16px;
            max-width: 820px;
            color: var(--bb-muted);
            line-height: 1.65;
        }

        .bb-bubble strong {
            color: var(--bb-text);
        }

        .bb-form-area {
            margin-top: 18px;
            display: grid;
            gap: 12px;
        }

        .bb-input,
        .bb-textarea {
            width: 100%;
            border: 1px solid rgba(148, 163, 184, 0.26);
            background: #f8fafc;
            color: #0f172a;
            border-radius: 14px;
            padding: 15px 16px;
            font-size: 1rem;
            outline: none;
        }

        .bb-textarea {
            min-height: 105px;
            resize: vertical;
        }

        .bb-input:focus,
        .bb-textarea:focus {
            border-color: #60a5fa;
            box-shadow: 0 0 0 4px rgba(96, 165, 250, 0.18);
        }

        .bb-error {
            display: none;
            color: #fecaca;
            background: rgba(239, 68, 68, 0.12);
            border: 1px solid rgba(239, 68, 68, 0.28);
            padding: 10px 12px;
            border-radius: 12px;
            font-size: 0.92rem;
        }

        .bb-actions {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin-top: 4px;
        }

        .bb-btn {
            border: none;
            border-radius: 999px;
            padding: 13px 20px;
            font-weight: 900;
            cursor: pointer;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            text-decoration: none;
            transition: 0.2s ease;
            font-size: 0.95rem;
        }

        .bb-btn-primary {
            background: linear-gradient(135deg, var(--bb-primary), #2563eb);
            color: #fff;
            box-shadow: 0 12px 30px rgba(37, 99, 235, 0.28);
        }

        .bb-btn-primary:hover {
            transform: translateY(-1px);
        }

        .bb-btn-outline {
            background: rgba(15, 23, 42, 0.68);
            color: var(--bb-text);
            border: 1px solid rgba(148, 163, 184, 0.28);
        }

        .bb-btn-success {
            background: linear-gradient(135deg, #16a34a, #22c55e);
            color: #fff;
        }

        .bb-history {
            margin-top: 24px;
            border-top: 1px solid rgba(148, 163, 184, 0.14);
            padding-top: 18px;
            display: grid;
            gap: 10px;
        }

        .bb-history-item {
            display: grid;
            grid-template-columns: 220px 1fr;
            gap: 10px;
            padding: 10px 12px;
            background: rgba(2, 6, 23, 0.36);
            border: 1px solid rgba(148, 163, 184, 0.12);
            border-radius: 12px;
            color: var(--bb-muted);
            font-size: 0.92rem;
        }

        .bb-history-item strong {
            color: var(--bb-text);
        }

        .bb-summary {
            display: none;
            padding: 26px;
            border-top: 1px solid var(--bb-border);
            background: #020617;
        }

        .bb-summary h2 {
            color: var(--bb-success);
            margin: 0 0 16px;
            font-size: 1.55rem;
        }

        .bb-summary-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 18px;
            margin-bottom: 18px;
        }

        .bb-summary-card {
            background: rgba(15, 23, 42, 0.86);
            border: 1px solid rgba(148, 163, 184, 0.18);
            border-radius: 18px;
            padding: 18px;
        }

        .bb-summary-card h3 {
            margin: 0 0 12px;
            color: #dbeafe;
            font-size: 1.08rem;
        }

        .bb-summary-card p {
            margin: 7px 0;
            color: var(--bb-muted);
            line-height: 1.5;
        }

        .bb-summary-card strong {
            color: var(--bb-text);
        }

        .bb-json {
            width: 100%;
            min-height: 300px;
            border-radius: 18px;
            border: 1px solid rgba(148, 163, 184, 0.22);
            background: #020617;
            color: #dbeafe;
            padding: 18px;
            font-size: 0.92rem;
            line-height: 1.5;
            resize: vertical;
            font-family: Consolas, Monaco, monospace;
        }

        .bb-foto-preview {
            display: block;
            max-width: 180px;
            max-height: 180px;
            width: 100%;
            height: auto;
            object-fit: cover;
            border-radius: 14px;
            border: 1px solid rgba(148, 163, 184, 0.22);
            margin-top: 8px;
            background: rgba(2, 6, 23, 0.55);
        }

        .bb-foto-link {
            display: inline-block;
            margin-top: 8px;
            color: #93c5fd;
            word-break: break-all;
        }

        .bb-json-hidden {
            display: none !important;
        }

        .bb-envio-status {
            display: none;
            margin-top: 14px;
            padding: 12px 14px;
            border-radius: 14px;
            font-weight: 800;
            line-height: 1.45;
        }

        .bb-envio-status.sucesso {
            display: block;
            color: #dcfce7;
            background: rgba(34, 197, 94, 0.14);
            border: 1px solid rgba(34, 197, 94, 0.30);
        }

        .bb-envio-status.erro {
            display: block;
            color: #fecaca;
            background: rgba(239, 68, 68, 0.14);
            border: 1px solid rgba(239, 68, 68, 0.30);
        }

        .bb-footer-note {
            margin-top: 18px;
            color: var(--bb-muted-2);
            text-align: center;
            font-size: 0.9rem;
        }

        .bb-schedule-box {
            display: none;
            width: 100%;
            overflow-x: auto;
            border: 1px solid rgba(148, 163, 184, 0.22);
            border-radius: 18px;
            background: rgba(2, 6, 23, 0.42);
            padding: 14px;
        }

        .bb-schedule-table {
            width: 100%;
            min-width: 760px;
            border-collapse: separate;
            border-spacing: 0 10px;
        }

        .bb-schedule-table th {
            color: var(--bb-muted);
            font-size: 0.86rem;
            text-align: left;
            padding: 0 8px 4px;
            font-weight: 800;
        }

        .bb-schedule-table td {
            padding: 0 8px;
            vertical-align: middle;
        }

        .bb-schedule-day {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            color: var(--bb-text);
            font-weight: 800;
            white-space: nowrap;
        }

        .bb-schedule-day input {
            width: 18px;
            height: 18px;
            accent-color: var(--bb-primary);
        }

        .bb-time-input {
            width: 100%;
            min-width: 115px;
            border: 1px solid rgba(148, 163, 184, 0.26);
            background: #f8fafc;
            color: #0f172a;
            border-radius: 12px;
            padding: 11px 10px;
            font-size: 0.95rem;
            outline: none;
        }

        .bb-schedule-help {
            color: var(--bb-muted-2);
            font-size: 0.88rem;
            line-height: 1.5;
            margin: 4px 4px 0;
        }

        @media (max-width: 900px) {
            .bb-hero {
                grid-template-columns: 1fr;
            }
            .bb-wizard-head {
                display: block;
            }
            .bb-progress-wrap {
                min-width: 0;
                margin-top: 16px;
            }
            .bb-progress-label {
                text-align: left;
            }
            .bb-summary-grid {
                grid-template-columns: 1fr;
            }
            .bb-history-item {
                grid-template-columns: 1fr;
            }
        }

        .bb-schedule-table input[type="time"]:disabled {
            opacity: 0.45;
            cursor: not-allowed;
            background: rgba(148, 163, 184, 0.12);
        }

        .bb-schedule-day input:disabled + span {
            opacity: 0.6;
        }

        @media (max-width: 560px) {
            .bb-topbar {
                align-items: flex-start;
            }
            .bb-brand-text {
                display: none;
            }
            .bb-topbar-badge {
                font-size: 0.75rem;
                padding: 9px 10px;
            }
            .bb-page {
                width: 94%;
                padding-top: 30px;
            }
            .bb-chat,
            .bb-summary {
                padding: 18px;
            }
            .bb-wizard-head {
                padding: 18px;
            }
            .bb-actions .bb-btn {
                width: 100%;
            }
        }
    </style>
</head>
<body>
    <header class="bb-topbar">
        <a href="./" class="bb-brand" aria-label="BarberBot Software">
            <img src="<?php echo bb_onboarding_h($logoSistema); ?>" alt="<?php echo bb_onboarding_h($nomeSistema); ?>" onerror="this.style.display='none';">
            <div class="bb-brand-text">
                <strong><?php echo bb_onboarding_h($nomeSistema); ?></strong>
                <span>Onboarding dos profissionais</span>
            </div>
        </a>

        <div class="bb-topbar-badge">
            <i class="fa-solid fa-user-gear"></i>
            Barberbot Admin
        </div>
    </header>

    <main class="bb-page">
        <section class="bb-hero">
            <div>
                <h1>Configuração inicial do <span>BarberBot</span></h1>
                <p>
                    Esta página coleta os dados dos profissionais e dos serviços do salão para que Jacy Cordeiro possa configure o sistema com segurança, sem confundir o cliente com o painel administrativo.
                </p>
            </div>

            <aside class="bb-side-card">
                <h2>Como funciona</h2>
                <ul class="bb-side-list">
                    <li><i class="fa-solid fa-check"></i><span>Responda uma pergunta por vez.</span></li>
                    <li><i class="fa-solid fa-check"></i><span>No final, será gerado um resumo organizado com todos os profissionais e serviços.</span></li>
                    <li><i class="fa-solid fa-check"></i><span>Envie o relatório para o administrador configurar o sistema.</span></li>
                </ul>
            </aside>
        </section>

        <section class="bb-wizard" aria-label="Coleta de dados dos profissionais e serviços">
            <div class="bb-wizard-head">
                <div class="bb-wizard-title">
                    <strong id="bbParteTitulo">Parte 1: Registro do Profissional</strong>
                    <span id="bbEtapaDescricao">Pergunta 1 de 12</span>
                </div>

                <div class="bb-progress-wrap">
                    <div class="bb-progress-label" id="bbProgressLabel">0% concluído</div>
                    <div class="bb-progress" aria-hidden="true">
                        <div class="bb-progress-bar" id="bbProgressBar"></div>
                    </div>
                </div>
            </div>

            <div class="bb-chat" id="bbChatArea">
                <div class="bb-message">
                    <div class="bb-avatar"><i class="fa-solid fa-robot"></i></div>
                    <div class="bb-bubble" id="bbQuestionBubble"></div>
                </div>

                <form class="bb-form-area" id="bbWizardForm" autocomplete="off">
                    <input type="text" class="bb-input" id="bbAnswerInput" placeholder="Digite sua resposta">
                    <textarea class="bb-textarea" id="bbAnswerTextarea" placeholder="Digite sua resposta" style="display:none;"></textarea>
                    <input type="file" class="bb-input" id="bbAnswerFile" accept="image/*" style="display:none;">
                    <div class="bb-schedule-box" id="bbScheduleBox" tabindex="-1" aria-label="Horários de atendimento do profissional">
                        <table class="bb-schedule-table">
                            <thead>
                                <tr>
                                    <th>Dia da semana</th>
                                    <th>Início</th>
                                    <th>Fim</th>
                                    <th>Início intervalo almoço</th>
                                    <th>Fim intervalo almoço</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr data-dia="segunda"><td><label class="bb-schedule-day"><input type="checkbox" class="bb-dia-ativo"> Segunda</label></td><td><input type="time" class="bb-time-input bb-hora-inicio"></td><td><input type="time" class="bb-time-input bb-hora-fim"></td><td><input type="time" class="bb-time-input bb-almoco-inicio"></td><td><input type="time" class="bb-time-input bb-almoco-fim"></td></tr>
                                <tr data-dia="terca"><td><label class="bb-schedule-day"><input type="checkbox" class="bb-dia-ativo"> Terça</label></td><td><input type="time" class="bb-time-input bb-hora-inicio"></td><td><input type="time" class="bb-time-input bb-hora-fim"></td><td><input type="time" class="bb-time-input bb-almoco-inicio"></td><td><input type="time" class="bb-time-input bb-almoco-fim"></td></tr>
                                <tr data-dia="quarta"><td><label class="bb-schedule-day"><input type="checkbox" class="bb-dia-ativo"> Quarta</label></td><td><input type="time" class="bb-time-input bb-hora-inicio"></td><td><input type="time" class="bb-time-input bb-hora-fim"></td><td><input type="time" class="bb-time-input bb-almoco-inicio"></td><td><input type="time" class="bb-time-input bb-almoco-fim"></td></tr>
                                <tr data-dia="quinta"><td><label class="bb-schedule-day"><input type="checkbox" class="bb-dia-ativo"> Quinta</label></td><td><input type="time" class="bb-time-input bb-hora-inicio"></td><td><input type="time" class="bb-time-input bb-hora-fim"></td><td><input type="time" class="bb-time-input bb-almoco-inicio"></td><td><input type="time" class="bb-time-input bb-almoco-fim"></td></tr>
                                <tr data-dia="sexta"><td><label class="bb-schedule-day"><input type="checkbox" class="bb-dia-ativo"> Sexta</label></td><td><input type="time" class="bb-time-input bb-hora-inicio"></td><td><input type="time" class="bb-time-input bb-hora-fim"></td><td><input type="time" class="bb-time-input bb-almoco-inicio"></td><td><input type="time" class="bb-time-input bb-almoco-fim"></td></tr>
                                <tr data-dia="sabado"><td><label class="bb-schedule-day"><input type="checkbox" class="bb-dia-ativo"> Sábado</label></td><td><input type="time" class="bb-time-input bb-hora-inicio"></td><td><input type="time" class="bb-time-input bb-hora-fim"></td><td><input type="time" class="bb-time-input bb-almoco-inicio"></td><td><input type="time" class="bb-time-input bb-almoco-fim"></td></tr>
                                <tr data-dia="domingo"><td><label class="bb-schedule-day"><input type="checkbox" class="bb-dia-ativo"> Domingo</label></td><td><input type="time" class="bb-time-input bb-hora-inicio"></td><td><input type="time" class="bb-time-input bb-hora-fim"></td><td><input type="time" class="bb-time-input bb-almoco-inicio"></td><td><input type="time" class="bb-time-input bb-almoco-fim"></td></tr>
                            </tbody>
                        </table>
                        <p class="bb-schedule-help">Marque os dias de atendimento. Para cada dia marcado, informe início e fim. O intervalo de almoço é opcional, mas se preencher o início, preencha também o fim.</p>
                    </div>
                    <div class="bb-error" id="bbErrorBox"></div>

                    <div class="bb-actions">
                        <button type="button" class="bb-btn bb-btn-outline" id="bbBackBtn" style="display:none;">
                            <i class="fa-solid fa-arrow-left"></i>
                            Voltar
                        </button>
                        <button type="submit" class="bb-btn bb-btn-primary" id="bbNextBtn">
                            Próximo
                            <i class="fa-solid fa-arrow-right"></i>
                        </button>
                    </div>
                </form>

                <div class="bb-history" id="bbHistory" style="display:none;"></div>
            </div>

            <div class="bb-summary" id="bbSummaryArea">
                <h2>✅ CADASTRO CONCLUÍDO COM SUCESSO! Enviando dados para o Administrador...</h2>

                <div class="bb-summary-grid">
                    <div class="bb-summary-card">
                        <h3><i class="fa-solid fa-user"></i> Profissionais</h3>
                        <div id="bbResumoProfissional"></div>
                    </div>

                    <div class="bb-summary-card">
                        <h3><i class="fa-solid fa-scissors"></i> Serviços</h3>
                        <div id="bbResumoServico"></div>
                    </div>
                </div>

                <textarea class="bb-json bb-json-hidden" id="bbJsonOutput" readonly aria-hidden="true" tabindex="-1"></textarea>

                <div class="bb-actions" style="margin-top:16px;">
                    <button type="button" class="bb-btn bb-btn-primary" id="bbCopyBtn" style="display:none;">
                        <i class="fa-regular fa-copy"></i>
                        Copiar relatório
                    </button>
                    <button type="button" class="bb-btn bb-btn-success" id="bbWhatsBtn">
                        <i class="fa-brands fa-whatsapp"></i>
                        Enviar para o Administrador
                    </button>
                    <button type="button" class="bb-btn bb-btn-outline" id="bbRestartBtn">
                        <i class="fa-solid fa-rotate-left"></i>
                        Novo cadastro
                    </button>
                </div>

                <div class="bb-envio-status" id="bbEnvioStatus"></div>
            </div>
        </section>

        <p class="bb-footer-note">
            Página de apoio para onboarding. Os dados não são salvos automaticamente no banco de dados; o relatório final deve ser enviado ao administrador.
        </p>
    </main>

    <script>
    (function(){
        'use strict';

        var whatsappAdmin = <?php echo json_encode($whatsappAdmin, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

        var profissionais = [];
        var servicos = [];
        var profissionalAtual = {};
        var servicoAtual = {};
        var fase = 'profissional';
        var etapa = 0;
        var aguardandoMaisProfissional = false;
        var aguardandoMaisServico = false;

        var profissionalSteps = [
            {
                key:'nome_completo',
                label:'Nome completo do profissional',
                question:'Olá! Eu sou o <strong>Barberbot Admin</strong>. Vamos configurar o BarberBot do seu salão. Para começar, informe o <strong>Nome Completo do profissional</strong>.',
                placeholder:'Ex: João da Silva',
                type:'text',
                validate:function(v){ return v.trim().length >= 3; },
                error:'Informe o nome completo do profissional.'
            },
            {
                key:'email',
                label:'E-mail de login/contato',
                question:'Informe o <strong>e-mail de login ou contato</strong> desse profissional.',
                placeholder:'Ex: contato@email.com',
                type:'email',
                validate:function(v){ return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(v.trim()); },
                error:'Informe um e-mail válido.'
            },
            {
                key:'whatsapp',
                label:'WhatsApp com DDD',
                question:'Agora informe o <strong>WhatsApp com DDD</strong> desse profissional.',
                placeholder:'Ex: (45) 99999-0000',
                type:'tel',
                validate:function(v){ return onlyDigits(v).length >= 10 && onlyDigits(v).length <= 11; },
                error:'Informe um WhatsApp válido com DDD.'
            },
            {
                key:'endereco_completo',
                label:'Endereço completo do local de atendimento',
                question:'Informe o <strong>endereço completo do local de atendimento</strong>: rua, número, bairro, cidade e CEP.',
                placeholder:'Ex: Rua Brasil, 100, Centro, Cascavel - PR, 85800-000',
                type:'textarea',
                validate:function(v){ return v.trim().length >= 12; },
                error:'Informe o endereço completo do local de atendimento.'
            },
            {
                key:'intervalo_agendamento_minutos',
                label:'Intervalo entre agendamentos',
                question:'Qual será o <strong>intervalo de tempo entre os agendamentos</strong>, em minutos?',
                placeholder:'Ex: 30',
                type:'number',
                suffix:'minutos',
                validate:function(v){ return getNumber(v) > 0; },
                error:'Informe apenas o número de minutos. Ex: 30, 45 ou 60.'
            },
            {
                key:'foto_perfil',
                label:'Foto de perfil',
                question:'Envie a <strong>foto de perfil</strong> do profissional. Você pode buscar a imagem no celular ou no computador.',
                placeholder:'Selecione a foto de perfil',
                type:'file',
                validate:function(v){ return v.trim().length >= 3; },
                error:'Selecione a foto de perfil do profissional.'
            },
            {
                key:'horarios_atendimento',
                label:'Horários de atendimento',
                question:'Agora defina os <strong>Horários de Atendimento do profissional</strong>. Informe a escala de trabalho de segunda a domingo com início, fim, início do intervalo e fim do intervalo de almoço.',
                placeholder:'Defina os horários de atendimento',
                type:'schedule',
                validate:function(v){ return validarHorarios(v); },
                error:'Marque pelo menos um dia de atendimento válido. Se o profissional trabalha só um dia por semana, marque apenas esse dia e informe início e fim. O almoço é opcional; se preencher início, preencha também o fim.'
            }
        ];

        var servicoSteps = [
            {
                key:'nome_servico',
                label:'Nome do serviço',
                question:'Agora vamos cadastrar os serviços do salão. Informe o <strong>nome do serviço</strong>.',
                placeholder:'Ex: Corte Degradê, Barba Completa, Combo',
                type:'text',
                validate:function(v){ return v.trim().length >= 3; },
                error:'Informe o nome do serviço.'
            },
            {
                key:'valor_servico',
                label:'Valor do serviço',
                question:'Qual é o <strong>valor do serviço</strong>, em R$?',
                placeholder:'Ex: 50,00',
                type:'money',
                validate:function(v){ return getMoney(v) > 0; },
                error:'Informe um valor válido. Ex: 50,00 ou 120,00.'
            },
            {
                key:'dias_retorno',
                label:'Dias de retorno',
                question:'De quantos em quantos dias o cliente costuma voltar para esse serviço?',
                placeholder:'Ex: 15 ou 30',
                type:'number',
                suffix:'dias',
                validate:function(v){ return getNumber(v) > 0; },
                error:'Informe apenas a quantidade de dias. Ex: 15 ou 30.'
            },
            {
                key:'tempo_execucao_minutos',
                label:'Tempo estimado de execução',
                question:'Qual é o <strong>tempo estimado de execução</strong> desse serviço, em minutos?',
                placeholder:'Ex: 45',
                type:'number',
                suffix:'minutos',
                validate:function(v){ return getNumber(v) > 0; },
                error:'Informe apenas o número de minutos. Ex: 45, 60 ou 90.'
            },
            {
                key:'comissao_profissional_percentual',
                label:'Comissão do profissional',
                question:'Informe a <strong>comissão do profissional</strong>, em porcentagem.',
                placeholder:'Ex: 50',
                type:'percent',
                suffix:'%',
                validate:function(v){ var n = getNumber(v); return n >= 0 && n <= 100; },
                error:'Informe uma porcentagem válida entre 0 e 100. Ex: 50.'
            },
            {
                key:'foto_servico',
                label:'Foto ilustrativa do serviço',
                question:'Envie a <strong>foto ilustrativa do serviço</strong>. Você pode buscar a imagem no celular ou no computador.',
                placeholder:'Selecione a foto do serviço',
                type:'file',
                validate:function(v){ return v.trim().length >= 3; },
                error:'Selecione a foto ilustrativa do serviço.'
            }
        ];

        var parteTitulo = document.getElementById('bbParteTitulo');
        var etapaDescricao = document.getElementById('bbEtapaDescricao');
        var progressLabel = document.getElementById('bbProgressLabel');
        var progressBar = document.getElementById('bbProgressBar');
        var questionBubble = document.getElementById('bbQuestionBubble');
        var form = document.getElementById('bbWizardForm');
        var input = document.getElementById('bbAnswerInput');
        var textarea = document.getElementById('bbAnswerTextarea');
        var fileInput = document.getElementById('bbAnswerFile');
        var scheduleBox = document.getElementById('bbScheduleBox');
        var errorBox = document.getElementById('bbErrorBox');
        var backBtn = document.getElementById('bbBackBtn');
        var nextBtn = document.getElementById('bbNextBtn');
        var history = document.getElementById('bbHistory');
        var chatArea = document.getElementById('bbChatArea');
        var summaryArea = document.getElementById('bbSummaryArea');
        var resumoProfissional = document.getElementById('bbResumoProfissional');
        var resumoServico = document.getElementById('bbResumoServico');
        var jsonOutput = document.getElementById('bbJsonOutput');
        var copyBtn = document.getElementById('bbCopyBtn');
        var whatsBtn = document.getElementById('bbWhatsBtn');
        var envioStatus = document.getElementById('bbEnvioStatus');
        var restartBtn = document.getElementById('bbRestartBtn');

        function onlyDigits(v){
            return (v || '').replace(/\D+/g, '');
        }

        function getNumber(v){
            var n = parseFloat((v || '').toString().replace(',', '.').replace(/[^0-9.]/g, ''));
            return isNaN(n) ? 0 : n;
        }

        function getMoney(v){
            var s = (v || '').toString().trim();
            s = s.replace(/R\$/gi, '').replace(/\s+/g, '');
            if (s.indexOf(',') >= 0) {
                s = s.replace(/\./g, '').replace(',', '.');
            }
            var n = parseFloat(s.replace(/[^0-9.]/g, ''));
            return isNaN(n) ? 0 : n;
        }

        function formatMoney(v){
            var n = getMoney(v);
            return n.toLocaleString('pt-BR', { style:'currency', currency:'BRL' });
        }

        function yesNo(v){
            var r = (v || '').toString().trim().toLowerCase();
            r = r.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
            if (['sim','s','yes','y'].indexOf(r) >= 0) return 'sim';
            if (['nao','n','no'].indexOf(r) >= 0) return 'nao';
            return '';
        }

        function diasSemanaHorario(){
            return {
                segunda:'Segunda',
                terca:'Terça',
                quarta:'Quarta',
                quinta:'Quinta',
                sexta:'Sexta',
                sabado:'Sábado',
                domingo:'Domingo'
            };
        }

        function atualizarLinhaHorario(linha, agendaAtiva){
            if (!linha) return;

            var check = linha.querySelector('.bb-dia-ativo');
            var ativo = !!(agendaAtiva && check && check.checked);
            var camposHora = linha.querySelectorAll('input[type="time"]');

            Array.prototype.forEach.call(camposHora, function(campo){
                campo.disabled = !ativo;
                if (!ativo) {
                    campo.value = '';
                }
            });
        }

        function definirCamposHorarioAtivos(ativo){
            if (!scheduleBox) return;

            var linhas = scheduleBox.querySelectorAll('tr[data-dia]');
            Array.prototype.forEach.call(linhas, function(linha){
                var check = linha.querySelector('.bb-dia-ativo');
                if (check) {
                    check.disabled = !ativo;
                    if (!ativo) {
                        check.checked = false;
                    }
                }
                atualizarLinhaHorario(linha, ativo);
            });
        }

        function limparHorarios(){
            if (!scheduleBox) return;
            var linhas = scheduleBox.querySelectorAll('tr[data-dia]');
            Array.prototype.forEach.call(linhas, function(linha){
                var check = linha.querySelector('.bb-dia-ativo');
                if (check) check.checked = false;
                atualizarLinhaHorario(linha, true);
            });
        }

        function coletarHorarios(){
            var horarios = [];
            if (!scheduleBox) return horarios;

            var nomes = diasSemanaHorario();
            var linhas = scheduleBox.querySelectorAll('tr[data-dia]');
            Array.prototype.forEach.call(linhas, function(linha){
                var dia = linha.getAttribute('data-dia') || '';
                var ativo = linha.querySelector('.bb-dia-ativo');
                var inicio = linha.querySelector('.bb-hora-inicio');
                var fim = linha.querySelector('.bb-hora-fim');
                var almocoInicio = linha.querySelector('.bb-almoco-inicio');
                var almocoFim = linha.querySelector('.bb-almoco-fim');

                horarios.push({
                    dia: dia,
                    dia_nome: nomes[dia] || dia,
                    ativo: !!(ativo && ativo.checked),
                    inicio: inicio ? inicio.value : '',
                    fim: fim ? fim.value : '',
                    intervalo_almoco_inicio: almocoInicio ? almocoInicio.value : '',
                    intervalo_almoco_fim: almocoFim ? almocoFim.value : ''
                });
            });

            return horarios;
        }

        function parseHorarios(valor){
            if (Array.isArray(valor)) return valor;
            try {
                var dados = JSON.parse(valor || '[]');
                return Array.isArray(dados) ? dados : [];
            } catch(e) {
                return [];
            }
        }

        function preencherHorarios(valor){
            var dados = parseHorarios(valor);
            if (!scheduleBox || !dados.length) return;

            dados.forEach(function(item){
                var linha = scheduleBox.querySelector('tr[data-dia="' + (item.dia || '') + '"]');
                if (!linha) return;

                var ativo = inline = linha.querySelector('.bb-dia-ativo');
                var inicio = linha.querySelector('.bb-hora-inicio');
                var fim = linha.querySelector('.bb-hora-fim');
                var almocoInicio = linha.querySelector('.bb-almoco-inicio');
                var almocoFim = linha.querySelector('.bb-almoco-fim');

                if (ativo) ativo.checked = !!item.ativo;
                atualizarLinhaHorario(linha, true);
                if (inicio) inicio.value = item.inicio || '';
                if (fim) fim.value = item.fim || '';
                if (almocoInicio) almocoInicio.value = item.intervalo_almoco_inicio || '';
                if (almocoFim) almocoFim.value = item.intervalo_almoco_fim || '';
            });
        }

        function horarioAtivoValido(h){
            if (!h || !h.ativo) return false;
            if (!h.inicio || !h.fim) return false;
            if (h.inicio >= h.fim) return false;
            if ((h.intervalo_almoco_inicio && !h.intervalo_almoco_fim) || (!h.intervalo_almoco_inicio && h.intervalo_almoco_fim)) return false;
            if (h.intervalo_almoco_inicio && h.intervalo_almoco_fim && h.intervalo_almoco_inicio >= h.intervalo_almoco_fim) return false;
            return true;
        }

        function normalizarHorariosAtendimento(horarios){
            horarios = Array.isArray(horarios) ? horarios : parseHorarios(horarios);

            return horarios.map(function(h){
                if (!horarioAtivoValido(h)) {
                    h.ativo = false;
                    h.inicio = '';
                    h.fim = '';
                    h.intervalo_almoco_inicio = '';
                    h.intervalo_almoco_fim = '';
                }
                return h;
            });
        }

        function validarHorarios(valor){
            var horarios = parseHorarios(valor);

            for (var i = 0; i < horarios.length; i++) {
                if (horarioAtivoValido(horarios[i])) return true;
            }

            return false;
        }

        function resumeHorarios(valor){
            var horarios = parseHorarios(valor).filter(function(h){ return !!h.ativo; });
            if (!horarios.length) return 'Não informado';

            return horarios.map(function(h){
                var texto = (h.dia_nome || h.dia || '') + ': ' + (h.inicio || '--:--') + ' às ' + (h.fim || '--:--');
                if (h.intervalo_almoco_inicio && h.intervalo_almoco_fim) {
                    texto += ' | almoço: ' + h.intervalo_almoco_inicio + ' às ' + h.intervalo_almoco_fim;
                }
                return texto;
            }).join('\n');
        }

        function currentSteps(){
            return fase === 'profissional' ? profissionalSteps : servicoSteps;
        }

        function getActiveStep(){
            if (aguardandoMaisProfissional) {
                return {
                    key:'mais_profissional',
                    label:'Cadastrar outro profissional?',
                    question:'Deseja cadastrar <strong>outro profissional</strong> do salão? Responda <strong>sim</strong> ou <strong>não</strong>.',
                    placeholder:'Digite sim ou não',
                    type:'confirm',
                    validate:function(v){ return yesNo(v) !== ''; },
                    error:'Responda apenas sim ou não.'
                };
            }

            if (aguardandoMaisServico) {
                return {
                    key:'mais_servico',
                    label:'Cadastrar outro serviço?',
                    question:'Deseja cadastrar <strong>outro serviço</strong> do salão? Responda <strong>sim</strong> ou <strong>não</strong>.',
                    placeholder:'Digite sim ou não',
                    type:'confirm',
                    validate:function(v){ return yesNo(v) !== ''; },
                    error:'Responda apenas sim ou não.'
                };
            }

            return currentSteps()[etapa];
        }

        function getCurrentControl(){
            var step = getActiveStep();
            if (step.type === 'textarea') return textarea;
            if (step.type === 'file') return fileInput;
            if (step.type === 'schedule') return scheduleBox;
            return input;
        }

        function getCurrentValue(){
            var step = getActiveStep();
            if (step.type === 'file') {
                if (!fileInput.files || !fileInput.files.length) return '';
                var file = fileInput.files[0];
                return file.name || '';
            }
            if (step.type === 'schedule') {
                return JSON.stringify(normalizarHorariosAtendimento(coletarHorarios()));
            }
            var control = getCurrentControl();
            return (control.value || '').trim();
        }

        function normalizarUrlArquivo(url){
            url = (url || '').toString().trim();
            if (url === '') return '';
            if (/^https?:\/\//i.test(url)) return url;
            if (url.charAt(0) === '/') return window.location.origin + url;

            if (/^[a-z0-9._-]+\.(png|jpe?g|webp|gif|bmp|svg)$/i.test(url)) {
                return window.location.origin + '/sistema/img/barberbot-onboarding/' + url;
            }

            return window.location.origin + '/' + url.replace(/^\.\//, '');
        }

        function extrairUrlUpload(resp){
            var texto = (resp || '').toString().trim();
            var obj = null;

            try {
                obj = JSON.parse(texto);
            } catch(e) {
                obj = null;
            }

            if (obj && typeof obj === 'object') {
                var campos = ['url', 'link', 'caminho', 'path', 'arquivo', 'file', 'filename', 'nome'];
                for (var i = 0; i < campos.length; i++) {
                    if (obj[campos[i]]) return normalizarUrlArquivo(obj[campos[i]]);
                }

                if (obj.data && typeof obj.data === 'object') {
                    for (var j = 0; j < campos.length; j++) {
                        if (obj.data[campos[j]]) return normalizarUrlArquivo(obj.data[campos[j]]);
                    }
                }
            }

            var match = texto.match(/https?:\/\/[^\s"'<>]+/i);
            if (match && match[0]) return match[0];

            if (texto && texto.indexOf('<') === -1 && texto.length < 300) {
                return normalizarUrlArquivo(texto);
            }

            return '';
        }

        function enviarArquivoAutomatico(file, tipo){
            function enviarPara(url, usarAcaoInterna){
                var fd = new FormData();
                if (usarAcaoInterna) {
                    fd.append('acao', 'upload_onboarding_imagem');
                }
                fd.append('arquivo', file);
                fd.append('file', file);
                fd.append('imagem', file);
                fd.append('tipo', tipo || 'onboarding');
                fd.append('pasta', 'sistema/img/barberbot-onboarding');
                fd.append('origem', 'comecar.php');

                return fetch(url, {
                    method: 'POST',
                    body: fd,
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).then(function(res){
                    return res.text();
                }).then(function(resp){
                    return {
                        nome: file.name || '',
                        url: extrairUrlUpload(resp),
                        resposta: resp
                    };
                });
            }

            return enviarPara('/ajax/file.php', false).then(function(upload){
                var urlApi = upload && upload.url ? String(upload.url) : '';

                if (urlApi && urlApi.indexOf('/uploads/') === -1) {
                    return upload;
                }

                return enviarPara(window.location.href, true).then(function(uploadInterno){
                    if (uploadInterno && uploadInterno.url) {
                        return uploadInterno;
                    }
                    return upload;
                });
            }).catch(function(){
                return enviarPara(window.location.href, true);
            });
        }

        function montarHtmlFoto(valor, nomeArquivo){
            var url = normalizarUrlArquivo(valor || '');
            var nome = nomeArquivo || valor || '';
            var html = '';

            if (nome) {
                html += escapeHtml(nome);
            }

            if (url && (/\.(png|jpe?g|webp|gif|bmp|svg)(\?.*)?$/i.test(url) || /^https?:\/\//i.test(url))) {
                html += '<a class="bb-foto-link" href="' + escapeHtml(url) + '" target="_blank" rel="noopener noreferrer">Abrir imagem</a>';
                html += '<img class="bb-foto-preview" src="' + escapeHtml(url) + '" alt="Foto enviada" loading="lazy" onerror="this.style.display=\'none\';">';
            }

            return html || 'Não informado';
        }

        function totalBaseSteps(){
            return profissionalSteps.length + servicoSteps.length;
        }

        function progressPercent(){
            var done = 0;
            done += Math.min(profissionais.length, 1) * profissionalSteps.length;
            if (fase === 'profissional') done += etapa;
            if (fase === 'servico') done += profissionalSteps.length + etapa;
            return Math.min(99, Math.round((done / totalBaseSteps()) * 100));
        }

        function renderStep(){
            var step = getActiveStep();
            var pct = progressPercent();
            var etapaTexto = '';

            if (fase === 'profissional') {
                etapaTexto = aguardandoMaisProfissional ? 'Confirmação de profissionais' : 'Profissional ' + (profissionais.length + 1) + ' · Pergunta ' + (etapa + 1) + ' de ' + profissionalSteps.length;
            } else {
                etapaTexto = aguardandoMaisServico ? 'Confirmação de serviços' : 'Serviço ' + (servicos.length + 1) + ' · Pergunta ' + (etapa + 1) + ' de ' + servicoSteps.length;
            }

            parteTitulo.textContent = fase === 'profissional' ? 'Parte 1: Registro dos Profissionais' : 'Parte 2: Registro dos Serviços';
            etapaDescricao.textContent = etapaTexto;
            progressLabel.textContent = pct + '% concluído';
            progressBar.style.width = pct + '%';
            questionBubble.innerHTML = step.question;
            errorBox.style.display = 'none';
            errorBox.textContent = '';
            backBtn.style.display = podeVoltar() ? 'inline-flex' : 'none';
            nextBtn.disabled = false;
            nextBtn.innerHTML = (aguardandoMaisServico ? 'Concluir <i class="fa-solid fa-check"></i>' : 'Próximo <i class="fa-solid fa-arrow-right"></i>');

            input.style.display = (step.type === 'textarea' || step.type === 'file' || step.type === 'schedule') ? 'none' : 'block';
            textarea.style.display = step.type === 'textarea' ? 'block' : 'none';
            fileInput.style.display = step.type === 'file' ? 'block' : 'none';
            scheduleBox.style.display = step.type === 'schedule' ? 'block' : 'none';

            input.required = (step.type !== 'textarea' && step.type !== 'file' && step.type !== 'schedule');
            textarea.required = (step.type === 'textarea');
            fileInput.required = (step.type === 'file');
            input.disabled = (step.type === 'textarea' || step.type === 'file' || step.type === 'schedule');
            textarea.disabled = (step.type !== 'textarea');
            fileInput.disabled = (step.type !== 'file');
            definirCamposHorarioAtivos(step.type === 'schedule');

            input.type = ['email','tel','number'].indexOf(step.type) >= 0 ? step.type : 'text';
            input.placeholder = step.placeholder || 'Digite sua resposta';
            textarea.placeholder = step.placeholder || 'Digite sua resposta';

            input.value = '';
            textarea.value = '';
            fileInput.value = '';
            limparHorarios();

            if (!aguardandoMaisProfissional && !aguardandoMaisServico) {
                var obj = fase === 'profissional' ? profissionalAtual : servicoAtual;
                if (step.type === 'schedule') {
                    preencherHorarios(obj[step.key] || '');
                } else if (step.type !== 'file') {
                    input.value = obj[step.key] || '';
                    textarea.value = obj[step.key] || '';
                }
            }

            renderHistory();

            setTimeout(function(){ getCurrentControl().focus(); }, 80);
        }

        function podeVoltar(){
            if (aguardandoMaisProfissional || aguardandoMaisServico) return true;
            if (etapa > 0) return true;
            if (fase === 'servico' && servicos.length === 0) return true;
            return false;
        }

        function voltar(){
            if (aguardandoMaisProfissional) {
                aguardandoMaisProfissional = false;
                etapa = profissionalSteps.length - 1;
                return;
            }

            if (aguardandoMaisServico) {
                aguardandoMaisServico = false;
                etapa = servicoSteps.length - 1;
                return;
            }

            if (etapa > 0) {
                etapa--;
                return;
            }

            if (fase === 'servico' && servicos.length === 0) {
                fase = 'profissional';
                profissionalAtual = profissionais.pop() || profissionalAtual;
                etapa = profissionalSteps.length - 1;
                aguardandoMaisProfissional = true;
            }
        }

        function formatAnswer(step, value){
            value = (value || '').trim();

            if (step.type === 'money') {
                return formatMoney(value);
            }

            if (step.type === 'number') {
                return String(parseInt(getNumber(value), 10)) + (step.suffix ? ' ' + step.suffix : '');
            }

            if (step.type === 'percent') {
                return String(getNumber(value)).replace('.', ',') + '%';
            }

            if (step.type === 'file') {
                return value;
            }

            if (step.type === 'schedule') {
                return resumeHorarios(value);
            }

            if (step.type === 'tel') {
                var d = onlyDigits(value);
                if (d.length === 11) {
                    return '(' + d.slice(0,2) + ') ' + d.slice(2,7) + '-' + d.slice(7);
                }
                if (d.length === 10) {
                    return '(' + d.slice(0,2) + ') ' + d.slice(2,6) + '-' + d.slice(6);
                }
            }

            return value;
        }

        function escapeHtml(v){
            return (v || '').toString()
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;')
                .replace(/'/g, '&#039;');
        }

        function showError(msg){
            errorBox.textContent = msg;
            errorBox.style.display = 'block';
        }

        function renderHistory(){
            var html = '';

            profissionais.forEach(function(p, i){
                html += '<div class="bb-history-item"><strong>Profissional ' + (i + 1) + '</strong><span>' + escapeHtml(p.nome_completo || '') + '</span></div>';
            });

            servicos.forEach(function(s, i){
                html += '<div class="bb-history-item"><strong>Serviço ' + (i + 1) + '</strong><span>' + escapeHtml(s.nome_servico || '') + '</span></div>';
            });

            var steps = currentSteps();
            var obj = fase === 'profissional' ? profissionalAtual : servicoAtual;
            steps.forEach(function(step, idx){
                if (idx >= etapa) return;
                if (typeof obj[step.key] === 'undefined') return;
                html += '<div class="bb-history-item">' +
                    '<strong>' + escapeHtml(step.label) + '</strong>' +
                    '<span>' + escapeHtml(formatAnswer(step, obj[step.key])) + '</span>' +
                '</div>';
            });

            history.innerHTML = html;
            history.style.display = html ? 'grid' : 'none';
        }

        function normalizarProfissional(p){
            return {
                nome_completo: formatAnswer(profissionalSteps[0], p.nome_completo || ''),
                email: formatAnswer(profissionalSteps[1], p.email || ''),
                whatsapp: formatAnswer(profissionalSteps[2], p.whatsapp || ''),
                whatsapp_numeros: onlyDigits(p.whatsapp || ''),
                endereco_completo: formatAnswer(profissionalSteps[3], p.endereco_completo || ''),
                intervalo_agendamento_minutos: parseInt(getNumber(p.intervalo_agendamento_minutos || ''), 10),
                foto_perfil: normalizarUrlArquivo(p.foto_perfil || ''),
                foto_perfil_nome: p.foto_perfil_nome || p.foto_perfil || '',
                horarios_atendimento: parseHorarios(p.horarios_atendimento || '')
            };
        }

        function normalizarServico(s){
            return {
                nome_servico: formatAnswer(servicoSteps[0], s.nome_servico || ''),
                valor_servico: formatMoney(s.valor_servico || ''),
                valor_servico_decimal: getMoney(s.valor_servico || ''),
                dias_retorno: parseInt(getNumber(s.dias_retorno || ''), 10),
                tempo_execucao_minutos: parseInt(getNumber(s.tempo_execucao_minutos || ''), 10),
                comissao_profissional_percentual: getNumber(s.comissao_profissional_percentual || ''),
                foto_servico: normalizarUrlArquivo(s.foto_servico || ''),
                foto_servico_nome: s.foto_servico_nome || s.foto_servico || ''
            };
        }

        function buildPayload(){
            return {
                profissionais: profissionais.map(normalizarProfissional),
                servicos: servicos.map(normalizarServico),
                total_profissionais: profissionais.length,
                total_servicos: servicos.length,
                origem: 'Barberbot Admin - Onboarding',
                data_coleta: new Date().toLocaleString('pt-BR')
            };
        }

        function finish(){
            var payload = buildPayload();
            var json = JSON.stringify(payload, null, 2);
            var htmlProfissionais = '';
            var htmlServicos = '';

            progressLabel.textContent = '100% concluído';
            progressBar.style.width = '100%';

            payload.profissionais.forEach(function(p, i){
                htmlProfissionais += '<p><strong>Profissional ' + (i + 1) + ':</strong> ' + escapeHtml(p.nome_completo) + '</p>' +
                    '<p><strong>E-mail:</strong> ' + escapeHtml(p.email) + '</p>' +
                    '<p><strong>WhatsApp:</strong> ' + escapeHtml(p.whatsapp) + '</p>' +
                    '<p><strong>Endereço:</strong> ' + escapeHtml(p.endereco_completo) + '</p>' +
                    '<p><strong>Intervalo:</strong> ' + escapeHtml(p.intervalo_agendamento_minutos + ' minutos') + '</p>' +
                    '<p><strong>Foto:</strong><br>' + montarHtmlFoto(p.foto_perfil, p.foto_perfil_nome) + '</p>' +
                    '<p><strong>Horários:</strong><br>' + escapeHtml(resumeHorarios(JSON.stringify(p.horarios_atendimento))).replace(/\n/g, '<br>') + '</p>' +
                    '<hr style="border-color:rgba(148,163,184,.18);">';
            });

            payload.servicos.forEach(function(s, i){
                htmlServicos += '<p><strong>Serviço ' + (i + 1) + ':</strong> ' + escapeHtml(s.nome_servico) + '</p>' +
                    '<p><strong>Valor:</strong> ' + escapeHtml(s.valor_servico) + '</p>' +
                    '<p><strong>Retorno:</strong> ' + escapeHtml(s.dias_retorno + ' dias') + '</p>' +
                    '<p><strong>Tempo:</strong> ' + escapeHtml(s.tempo_execucao_minutos + ' minutos') + '</p>' +
                    '<p><strong>Comissão:</strong> ' + escapeHtml(s.comissao_profissional_percentual + '%') + '</p>' +
                    '<p><strong>Foto:</strong><br>' + montarHtmlFoto(s.foto_servico, s.foto_servico_nome) + '</p>' +
                    '<hr style="border-color:rgba(148,163,184,.18);">';
            });

            resumoProfissional.innerHTML = htmlProfissionais || '<p>Nenhum profissional informado.</p>';
            resumoServico.innerHTML = htmlServicos || '<p>Nenhum serviço informado.</p>';
            jsonOutput.value = json;

            whatsBtn.dataset.relatorio = json;

            chatArea.style.display = 'none';
            summaryArea.style.display = 'block';
            summaryArea.scrollIntoView({behavior:'smooth', block:'start'});
        }

        form.addEventListener('submit', async function(e){
            e.preventDefault();

            var step = getActiveStep();
            var control = getCurrentControl();
            var value = getCurrentValue();

            if (!step.validate(value)) {
                showError(step.error);
                control.focus();
                return;
            }

            if (step.type === 'file') {
                var file = (fileInput.files && fileInput.files.length) ? fileInput.files[0] : null;
                if (!file) {
                    showError(step.error);
                    control.focus();
                    return;
                }

                nextBtn.disabled = true;
                nextBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando imagem...';

                try {
                    var upload = await enviarArquivoAutomatico(file, step.key);
                    value = upload.url || upload.nome || value;

                    if (fase === 'profissional') {
                        profissionalAtual[step.key] = value;
                        profissionalAtual[step.key + '_nome'] = upload.nome || value;
                    } else {
                        servicoAtual[step.key] = value;
                        servicoAtual[step.key + '_nome'] = upload.nome || value;
                    }
                } catch (erroUpload) {
                    showError('Não foi possível enviar a imagem agora. Verifique o arquivo e tente novamente.');
                    nextBtn.disabled = false;
                    nextBtn.innerHTML = (aguardandoMaisServico ? 'Concluir <i class="fa-solid fa-check"></i>' : 'Próximo <i class="fa-solid fa-arrow-right"></i>');
                    return;
                }

                nextBtn.disabled = false;
            }

            if (aguardandoMaisProfissional) {
                if (yesNo(value) === 'sim') {
                    aguardandoMaisProfissional = false;
                    profissionalAtual = {};
                    etapa = 0;
                } else {
                    aguardandoMaisProfissional = false;
                    fase = 'servico';
                    etapa = 0;
                    servicoAtual = {};
                }
                renderStep();
                return;
            }

            if (aguardandoMaisServico) {
                if (yesNo(value) === 'sim') {
                    aguardandoMaisServico = false;
                    servicoAtual = {};
                    etapa = 0;
                    renderStep();
                } else {
                    finish();
                }
                return;
            }

            if (fase === 'profissional') {
                if (step.type !== 'file') {
                    profissionalAtual[step.key] = value;
                }

                if (etapa >= profissionalSteps.length - 1) {
                    profissionais.push(Object.assign({}, profissionalAtual));
                    aguardandoMaisProfissional = true;
                    renderStep();
                    return;
                }

                etapa++;
                renderStep();
                return;
            }

            if (step.type !== 'file') {
                servicoAtual[step.key] = value;
            }

            if (etapa >= servicoSteps.length - 1) {
                servicos.push(Object.assign({}, servicoAtual));
                aguardandoMaisServico = true;
                renderStep();
                return;
            }

            etapa++;
            renderStep();
        });

        backBtn.addEventListener('click', function(){
            if (!podeVoltar()) return;
            voltar();
            renderStep();
        });

        copyBtn.addEventListener('click', function(){
            jsonOutput.select();
            jsonOutput.setSelectionRange(0, 999999);

            if (navigator.clipboard && navigator.clipboard.writeText) {
                navigator.clipboard.writeText(jsonOutput.value).then(function(){
                    copyBtn.innerHTML = '<i class="fa-solid fa-check"></i> Copiado';
                    setTimeout(function(){ copyBtn.innerHTML = '<i class="fa-regular fa-copy"></i> Copiar relatório'; }, 1800);
                });
                return;
            }

            document.execCommand('copy');
            copyBtn.innerHTML = '<i class="fa-solid fa-check"></i> Copiado';
            setTimeout(function(){ copyBtn.innerHTML = '<i class="fa-regular fa-copy"></i> Copiar relatório'; }, 1800);
        });

        whatsBtn.addEventListener('click', function(e){
            if (e && typeof e.preventDefault === 'function') {
                e.preventDefault();
            }

            var relatorio = whatsBtn.dataset.relatorio || jsonOutput.value || '';

            if (!relatorio) {
                if (envioStatus) {
                    envioStatus.className = 'bb-envio-status erro';
                    envioStatus.textContent = 'Não foi possível localizar o relatório para envio.';
                }
                return;
            }

            var textoOriginal = whatsBtn.innerHTML;
            whatsBtn.disabled = true;
            whatsBtn.innerHTML = '<i class="fa-solid fa-spinner fa-spin"></i> Enviando...';

            if (envioStatus) {
                envioStatus.className = 'bb-envio-status';
                envioStatus.textContent = '';
            }

            var fd = new FormData();
            fd.append('acao', 'enviar_admin_onboarding');
            fd.append('relatorio', relatorio);

            fetch(window.location.href, {
                method: 'POST',
                body: fd,
                credentials: 'same-origin',
                headers: {
                    'X-Requested-With': 'XMLHttpRequest'
                }
            }).then(function(res){
                return res.text();
            }).then(function(resp){
                var texto = (resp || '').replace(/\s+/g, ' ').trim();

                if (/sucesso/i.test(texto)) {
                    if (envioStatus) {
                        envioStatus.className = 'bb-envio-status sucesso';
                        envioStatus.textContent = 'Relatório enviado automaticamente para o administrador.';
                    }
                    return;
                }

                if (envioStatus) {
                    envioStatus.className = 'bb-envio-status erro';
                    envioStatus.textContent = texto || 'Não foi possível enviar automaticamente. Verifique o arquivo /ajax/api-texto.php.';
                }
            }).catch(function(){
                if (envioStatus) {
                    envioStatus.className = 'bb-envio-status erro';
                    envioStatus.textContent = 'Erro de conexão ao enviar para o administrador.';
                }
            }).finally(function(){
                whatsBtn.disabled = false;
                whatsBtn.innerHTML = textoOriginal;
            });
        });

        restartBtn.addEventListener('click', function(){
            if (!confirm('Deseja iniciar um novo cadastro?')) return;
            profissionais = [];
            servicos = [];
            profissionalAtual = {};
            servicoAtual = {};
            fase = 'profissional';
            etapa = 0;
            aguardandoMaisProfissional = false;
            aguardandoMaisServico = false;
            summaryArea.style.display = 'none';
            chatArea.style.display = 'block';
            renderStep();
            window.scrollTo({top:0, behavior:'smooth'});
        });

        if (scheduleBox) {
            scheduleBox.addEventListener('change', function(e){
                if (e.target && e.target.classList.contains('bb-dia-ativo')) {
                    var linha = e.target.closest('tr[data-dia]');
                    atualizarLinhaHorario(linha, getActiveStep().type === 'schedule');
                }
            });
        }

        input.addEventListener('input', function(){
            var step = getActiveStep();
            if (step.type === 'file' || step.type === 'schedule') {
                return;
            }

            if (step.type === 'tel') {
                var d = onlyDigits(input.value).slice(0, 11);
                if (d.length <= 2) {
                    input.value = d;
                } else if (d.length <= 6) {
                    input.value = '(' + d.slice(0,2) + ') ' + d.slice(2);
                } else if (d.length <= 10) {
                    input.value = '(' + d.slice(0,2) + ') ' + d.slice(2,6) + '-' + d.slice(6);
                } else {
                    input.value = '(' + d.slice(0,2) + ') ' + d.slice(2,7) + '-' + d.slice(7);
                }
            }
        });

        renderStep();
    })();
    </script>
</body>
</html>