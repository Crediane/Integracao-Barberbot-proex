<?php 
// ===== CLI (CRON) - Captura do parâmetro loja sem alterar lógica =====
// Em CRON não existe REQUEST_URI com /loja/<slug>/. Para manter o padrão multi-banco,
// aceitamos: php arquivo.php --loja=slug  OU  php arquivo.php loja=slug
if (PHP_SAPI === 'cli' && !empty($argv) && (empty($_GET['loja']) && empty($_POST['loja']))) {
  foreach ($argv as $__arg) {
    if (preg_match('/^(?:--)?loja=([a-z0-9_-]+)$/i', (string)$__arg, $__m)) {
      $_GET['loja'] = $__m[1];
      break;
    }
  }
}
// O contexto de sessão e a conexão são definidos pelo arquivo chamador e pelo
// /sistema/conexao.php. O firewall não pode trocar BB_MASTER, BB_TENANT_* ou
// BB_SITE depois que o PDO já foi criado, pois isso deixaria sessão e banco
// apontando para contextos diferentes.

/**
 * /security.php - VERSÃO 3.0 CORRIGIDA (SEM FALSOS POSITIVOS)
 * Firewall de aplicação - Jacycabeleireiro.com
 * 
 * PROTEÇÕES ATIVAS:
 * ✅ Malware (Leostop, scripts maliciosos)
 * ✅ XSS (Cross-Site Scripting)
 * ✅ SQL Injection (INTELIGENTE)
 * ✅ RCE (Remote Code Execution)
 * ✅ Upload malicioso
 * ✅ Supply-chain comprometida
 * ✅ Rate Limiting
 * ✅ Path Traversal
 *
 * NOVIDADES v3.0:
 * ✅ Whitelist de IPs (admin nunca bloqueado)
 * ✅ Exceções para área administrativa
 * ✅ Detecção SQL mais inteligente (sem falsos positivos)
 */

if (defined('SEC_WAF_LOADED')) {
    return;
}
define('SEC_WAF_LOADED', true);

/* ========================= CONFIGURAÇÕES ========================= */

// Modo: 'on' = bloqueia | 'detect' = só loga | 'off' = desligado
// Em execução via CRON/CLI, não aplicamos bloqueios de firewall (não existe requisição HTTP).
if (PHP_SAPI === 'cli' && !defined('SEC_WAF_MODE')) {
    define('SEC_WAF_MODE', 'off');
}
if (!defined('SEC_WAF_MODE')) {
    define('SEC_WAF_MODE', 'on');
}
// Webroot do site
$SEC_WEBROOT = rtrim(__DIR__, DIRECTORY_SEPARATOR);

// ========== WHITELIST DE IPs (ADMIN E IPs CONFIÁVEIS) ==========
$SEC_WHITELIST_IPS = [
    '127.0.0.1',     // Localhost
    '::1',           
    '177.102.42.65',           
    '177.174.253.115',
    '',
];

// ========== CAMINHOS EXCLUÍDOS (área admin não é verificada) ==========
$SEC_EXCLUDED_PATHS = [
    '/',
    '/ndex.php',
    '/sistema',
    '/sistema/acesso',
    '/agendamentos',
    '/home',
    '/assinatura',
    '/servicos',
    '/produtos',
    '/protese',
    '/corte-cabelo',
];

// ========== DOMÍNIOS CONFIÁVEIS (CDNs) ==========
$SEC_WHITELIST_SCRIPT_DOMAINS = [
    'jc.tec.br',
    'www.jc.tec.br',
    'cdnjs.cloudflare.com',
    'cdn.jsdelivr.net',
    'unpkg.com',
    'code.jquery.com',
    'ajax.googleapis.com',
    'fonts.googleapis.com',
    'fonts.gstatic.com',
    'stackpath.bootstrapcdn.com',
    'maxcdn.bootstrapcdn.com',
    'www.google.com',
    'www.gstatic.com',
    'www.google-analytics.com',
    'www.googletagmanager.com',
];

// ========== DOMÍNIOS MALICIOSOS (BLACKLIST) ==========
$SEC_BLACKLIST_SCRIPT_DOMAINS = [
    'leostop.net', 'leostop.org', 'leostop.info', 'leostop.com',
    'malware-cdn.com', 'evil-scripts.net', 'badcdn.example',
    'phishing-site.com', 'malicious-js.example',
];

// ========== IPs BLOQUEADOS ==========
$SEC_BLOCKED_IPS_FILE = $SEC_WEBROOT . '/security/blocked-ips.txt';
$SEC_BLOCKED_IPS = [];

if (file_exists($SEC_BLOCKED_IPS_FILE)) {
    $lines = @file($SEC_BLOCKED_IPS_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);

    if ($lines) {
        foreach ($lines as $line) {
            $line = trim($line);

            if ($line && $line[0] !== '#') {
                $SEC_BLOCKED_IPS[] = explode(' ', $line)[0];
            }
        }
    }
}

// Limites
$SEC_MAX_CONTENT_LENGTH   = 10 * 1024 * 1024; // 10 MB
$SEC_RATE_LIMIT_REQUESTS  = 200;              // 200 requisições
$SEC_RATE_LIMIT_WINDOW    = 900;              // em 15 minutos

// Logs
$SEC_LOG_FILE      = $SEC_WEBROOT . '/logs/security-block.log';
$SEC_RATE_LOG_FILE = $SEC_WEBROOT . '/logs/rate-limit.log';

/* ========================= FUNÇÕES ========================= */

function sec_waf_now(): string
{
    return date('Y-m-d H:i:s');
}

function sec_waf_ip(): string
{
    $headers = [
        'HTTP_CF_CONNECTING_IP',
        'HTTP_X_REAL_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_CLIENT_IP',
        'REMOTE_ADDR',
    ];

    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = trim(explode(',', $_SERVER[$header])[0]);

            if (filter_var($ip, FILTER_VALIDATE_IP)) {
                return $ip;
            }
        }
    }

    return '0.0.0.0';
}

function sec_waf_write_log(string $file, string $line): void
{
    $dir = dirname($file);
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    @file_put_contents($file, $line . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function sec_waf_forbidden(string $reason): void
{
    http_response_code(403);
    header('Content-Type: text/html; charset=utf-8');

    sec_waf_write_log(
        $GLOBALS['SEC_LOG_FILE'],
        sprintf(
            '[%s] 403 ip=%s path=%s reason=%s',
            sec_waf_now(),
            sec_waf_ip(),
            ($_SERVER['REQUEST_URI'] ?? '/'),
            $reason
        )
    );

    $current_ip   = sec_waf_ip();
    $current_time = sec_waf_now();

    // WhatsApp do admin
    $whatsapp_number  = '5545999580058'; // Formato internacional: 55 + DDD + número
    $whatsapp_message = rawurlencode(
        "Olá, fui bloqueado pelo sistema de segurança.\n\n" .
        "IP: {$current_ip}\n" .
        "Data/Hora: {$current_time}\n" .
        "Motivo: {$reason}"
    );
    $whatsapp_link = "https://api.whatsapp.com/send?phone={$whatsapp_number}&text={$whatsapp_message}";
    ?>
    <!DOCTYPE html>
    <html lang="pt-BR">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta name="robots" content="noindex, nofollow">
        <title>🚫 Acesso Bloqueado - Segurança</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }

            body {
                font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 20px;
                color: #333;
            }

            .container {
                background: #fff;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
                max-width: 600px;
                width: 100%;
                padding: 40px;
                text-align: center;
                animation: slideIn 0.5s ease-out;
            }

            @keyframes slideIn {
                from {
                    opacity: 0;
                    transform: translateY(-30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }

            .icon {
                font-size: 72px;
                margin-bottom: 20px;
                animation: pulse 2s infinite;
            }

            @keyframes pulse {
                0%, 100% {
                    transform: scale(1);
                }
                50% {
                    transform: scale(1.1);
                }
            }

            h1 {
                color: #e74c3c;
                font-size: 28px;
                margin-bottom: 15px;
                font-weight: 700;
            }

            .subtitle {
                color: #7f8c8d;
                font-size: 16px;
                margin-bottom: 25px;
                line-height: 1.6;
            }

            .info-box {
                background: #f8f9fa;
                border-left: 4px solid #e74c3c;
                padding: 20px;
                border-radius: 8px;
                margin: 25px 0;
                text-align: left;
            }

            .info-box strong {
                color: #e74c3c;
                display: block;
                margin-bottom: 8px;
                font-size: 14px;
                text-transform: uppercase;
                letter-spacing: 0.5px;
            }

            .info-box p {
                color: #555;
                font-size: 15px;
                margin: 5px 0;
                word-break: break-word;
            }

            .contact-section {
                background: #f0f4f8;
                padding: 25px;
                border-radius: 12px;
                margin: 25px 0;
            }

            .contact-title {
                color: #2c3e50;
                font-size: 18px;
                font-weight: 600;
                margin-bottom: 15px;
            }

            .admin-info {
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 10px;
                margin-bottom: 20px;
                flex-wrap: wrap;
            }

            .admin-info div {
                background: #fff;
                padding: 10px 20px;
                border-radius: 8px;
                font-size: 15px;
                color: #555;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
            }

            .admin-info strong {
                color: #2c3e50;
                font-weight: 600;
            }

            .btn-whatsapp {
                display: inline-flex;
                align-items: center;
                justify-content: center;
                gap: 12px;
                background: #25d366;
                color: #fff;
                text-decoration: none;
                padding: 16px 32px;
                border-radius: 50px;
                font-size: 18px;
                font-weight: 700;
                box-shadow: 0 8px 20px rgba(37, 211, 102, 0.4);
                transition: all 0.3s ease;
                margin-top: 10px;
            }

            .btn-whatsapp:hover {
                background: #20ba5a;
                transform: translateY(-3px);
                box-shadow: 0 12px 28px rgba(37, 211, 102, 0.5);
            }

            .btn-whatsapp:active {
                transform: translateY(-1px);
            }

            .whatsapp-icon {
                font-size: 24px;
            }

            .details {
                margin-top: 30px;
                padding-top: 25px;
                border-top: 2px solid #ecf0f1;
                font-size: 13px;
                color: #95a5a6;
                line-height: 1.6;
            }

            .details p {
                margin: 5px 0;
            }

            @media (max-width: 600px) {
                .container {
                    padding: 30px 20px;
                }

                h1 {
                    font-size: 24px;
                }

                .icon {
                    font-size: 56px;
                }

                .btn-whatsapp {
                    font-size: 16px;
                    padding: 14px 28px;
                    width: 100%;
                }

                .admin-info {
                    flex-direction: column;
                }

                .admin-info div {
                    width: 100%;
                }
            }
        </style>
    </head>
    <body>
    <div class="container">
        <div class="icon">🚫</div>

        <h1>Acesso Bloqueado</h1>

        <p class="subtitle">
            Sua requisição foi identificada como potencialmente maliciosa pelo nosso sistema de segurança.
        </p>

        <div class="info-box">
            <strong>⚠️ Motivo do Bloqueio:</strong>
            <p><?php echo htmlspecialchars($reason, ENT_QUOTES, 'UTF-8'); ?></p>
        </div>

        <div class="contact-section">
            <p class="contact-title">
                💬 Acredita que isso é um erro?
            </p>

            <p style="color: #666; margin-bottom: 15px; font-size: 14px;">
                Entre em contato com o administrador do sistema:
            </p>

            <div class="admin-info">
                <div>
                    <strong>👤 Admin:</strong> Jacy Cordeiro
                </div>
                <div>
                    <strong>📱 WhatsApp:</strong> (45) 99958-0058
                </div>
            </div>

            <a href="<?php echo $whatsapp_link; ?>" target="_blank" rel="noopener noreferrer" class="btn-whatsapp">
                <span class="whatsapp-icon">📱</span>
                Contatar via WhatsApp
            </a>
        </div>

        <div class="details">
            <p><strong>Seu IP:</strong> <?php echo htmlspecialchars($current_ip, ENT_QUOTES, 'UTF-8'); ?></p>
            <p><strong>Data/Hora:</strong> <?php echo htmlspecialchars($current_time, ENT_QUOTES, 'UTF-8'); ?></p>
            <p style="margin-top: 15px; font-size: 12px; color: #bdc3c7;">
                ID da Requisição: <?php echo substr(md5($current_ip . $current_time), 0, 12); ?>
            </p>
        </div>
    </div>
    </body>
    </html>
    <?php
    exit;
}

function sec_waf_session_start(): void
{
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', '1');
        ini_set('session.cookie_secure', '1');
        ini_set('session.cookie_samesite', 'Strict');
        session_start();
    }
}

function sec_waf_contains(string $haystack, array $needles): bool
{
    $haystack = strtolower($haystack);

    foreach ($needles as $needle) {
        if (strpos($haystack, strtolower($needle)) !== false) {
            return true;
        }
    }

    return false;
}

/* ========================= VERIFICAÇÕES ========================= */

// Modo OFF = sai
if (SEC_WAF_MODE === 'off') {
    return;
}

sec_waf_session_start();

$CURRENT_IP   = sec_waf_ip();
$request_uri  = $_SERVER['REQUEST_URI'] ?? '/';

// ========== 1) WHITELIST DE IP (ADMIN NUNCA É BLOQUEADO) ==========
if (in_array($CURRENT_IP, $SEC_WHITELIST_IPS, true)) {
    // IP na whitelist - pula todas as verificações
    return;
}

// ========== 2) CAMINHOS EXCLUÍDOS (área admin) ==========
foreach ($SEC_EXCLUDED_PATHS as $excluded) {
    if (strpos($request_uri, $excluded) !== false) {
        // Caminho administrativo - pula verificações
        return;
    }
}

// ========== 3) BLOQUEIO DE IP (BLACKLIST) ==========
if (in_array($CURRENT_IP, $SEC_BLOCKED_IPS, true)) {
    sec_waf_forbidden('IP bloqueado');
}

// ========== 4) RATE LIMITING ==========
$rate_key = 'sec_rate_' . $CURRENT_IP;

if (!isset($_SESSION[$rate_key])) {
    $_SESSION[$rate_key] = [
        'count' => 0,
        'start' => time(),
    ];
}

$rate_data    = $_SESSION[$rate_key];
$time_elapsed = time() - $rate_data['start'];

if ($time_elapsed < $SEC_RATE_LIMIT_WINDOW) {
    $rate_data['count']++;

    if ($rate_data['count'] > $SEC_RATE_LIMIT_REQUESTS) {
        sec_waf_write_log(
            $SEC_RATE_LOG_FILE,
            sprintf(
                '[%s] RATE-LIMIT ip=%s path=%s count=%d',
                sec_waf_now(),
                $CURRENT_IP,
                $request_uri,
                $rate_data['count']
            )
        );

        if (SEC_WAF_MODE === 'on') {
            header('Retry-After: 900');
            sec_waf_forbidden('Rate limit excedido (429 Too Many Requests)');
        }
    }
} else {
    $_SESSION[$rate_key] = [
        'count' => 1,
        'start' => time(),
    ];
}

$_SESSION[$rate_key] = $rate_data;

// ========== 5) SESSION HIJACKING (MODO SUAVE – NÃO BLOQUEIA) ==========
$fingerprint_key     = 'sec_fingerprint';
$current_fingerprint = hash(
    'sha256',
    ($_SERVER['HTTP_USER_AGENT'] ?? '') .
    $CURRENT_IP .
    ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '')
);

if (!isset($_SESSION[$fingerprint_key])) {
    // Primeira vez: grava o fingerprint
    $_SESSION[$fingerprint_key] = $current_fingerprint;
} else {
    if ($_SESSION[$fingerprint_key] !== $current_fingerprint) {
        // Em vez de bloquear o cliente, só reseta a sessão e loga o evento
        sec_waf_write_log(
            $SEC_LOG_FILE,
            sprintf(
                '[%s] SESSION-RESET ip=%s path=%s',
                sec_waf_now(),
                $CURRENT_IP,
                $request_uri
            )
        );
        // Destroi a sessão antiga e cria uma nova limpa
        session_regenerate_id(true);
        $_SESSION = [];
        $_SESSION[$fingerprint_key] = $current_fingerprint;
    }
}

// ========== 6) TAMANHO DA REQUISIÇÃO ==========
if (!empty($_SERVER['CONTENT_LENGTH']) &&
    (int)$_SERVER['CONTENT_LENGTH'] > $SEC_MAX_CONTENT_LENGTH
) {
    sec_waf_forbidden('Requisição muito grande');
}

// ========== 7) HONEYPOT (ANTI-BOT) ==========
if (!empty($_POST['website']) ||
    !empty($_POST['url']) ||
    !empty($_POST['homepage'])
) {
    sec_waf_forbidden('Honeypot acionado (bot detectado)');
}

// ========== 8) USER-AGENT SUSPEITO ==========
$user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';

$malicious_agents = [
    'libwww', 'wget', 'python-requests', 'nikto', 'scanner',
    'sqlmap', 'nmap', 'masscan', 'HTTrack', 'winhttp',
    'loader', 'snoopy', 'leostop', 'hack', 'exploit',
];

if (sec_waf_contains($user_agent, $malicious_agents)) {
    sec_waf_forbidden('User-Agent malicioso detectado');
}

// ========== 9) PATH TRAVERSAL ==========
$path_patterns = [
    '../', '..\\', '%2e%2e', 'etc/passwd', 'boot.ini', 'win.ini',
];

if (sec_waf_contains($request_uri, $path_patterns)) {
    sec_waf_forbidden('Path traversal detectado');
}

// ========== 10) AGREGAÇÃO DE INPUTS ==========
$SEC_ALL_INPUT = '';

foreach (['_GET', '_POST', '_COOKIE'] as $super) {
    if (!empty($GLOBALS[$super]) && is_array($GLOBALS[$super])) {
        foreach ($GLOBALS[$super] as $k => $v) {
            if (is_array($v)) {
                $v = json_encode($v);
            }
            $SEC_ALL_INPUT .= " {$k}=" . (string) $v;
        }
    }
}

// ========== 11) XSS - SCRIPTS EXTERNOS MALICIOSOS ==========
if (preg_match_all(
    '/<script[^>]+src=["\'](https?:\/\/[^"\']+)["\'][^>]*>/i',
    $SEC_ALL_INPUT,
    $matches
)) {
    foreach ($matches[1] as $url) {
        $domain = strtolower(parse_url($url, PHP_URL_HOST) ?: '');
        if (!$domain) {
            continue;
        }

        $is_blacklisted = in_array($domain, $SEC_BLACKLIST_SCRIPT_DOMAINS, true);
        $is_whitelisted = in_array($domain, $SEC_WHITELIST_SCRIPT_DOMAINS, true);
        $is_own_domain  = ($domain === strtolower($_SERVER['HTTP_HOST'] ?? ''));

        if ($is_blacklisted || (!$is_whitelisted && !$is_own_domain)) {
            sec_waf_forbidden("Script externo suspeito: {$domain}");
        }
    }
}

// ========== 12) XSS - PADRÕES PERIGOSOS ==========
$xss_patterns = [
    '<script', 'javascript:', 'vbscript:', 'data:text/html',
    'onerror=', 'onload=', 'onclick=', 'onmouseover=',
    'document.cookie', 'document.write',
    'eval(', 'fromcharcode',
    '<iframe', '<embed', '<object',
];

if (sec_waf_contains($SEC_ALL_INPUT, $xss_patterns)) {
    sec_waf_forbidden('XSS/Script perigoso detectado');
}

// ========== 13) SQL INJECTION - INTELIGENTE (SEM FALSOS POSITIVOS) ==========
$input_lower = strtolower($SEC_ALL_INPUT);

// Padrões CRÍTICOS que indicam SQL injection real
$critical_sql_patterns = [
    "' or '1'='1",
    '" or "1"="1',
    "' or 1=1--",
    '" or 1=1--',
    'union select',
    'union all select',
    '; drop table',
    '; drop database',
    'exec(',
    'execute(',
    'xp_cmdshell',
    'information_schema.tables',
    'mysql.user',
    'sys.databases',
    "' or '1'='1' --",
    '" or "1"="1" --',
];

foreach ($critical_sql_patterns as $pattern) {
    if (strpos($input_lower, strtolower($pattern)) !== false) {
        sec_waf_forbidden('SQL Injection crítico detectado');
        break;
    }
}

// ========== 14) RCE - CÓDIGO MALICIOSO ==========
$code_patterns = [
    'base64_decode', 'gzinflate', 'eval(', 'assert(',
    'system(', 'shell_exec', 'exec(', 'passthru',
    'proc_open', 'popen', 'pcntl_exec',
    'file_get_contents.*php://',
    'leostop', 'c99', 'r57', 'wso', 'backdoor',
];

if (sec_waf_contains($SEC_ALL_INPUT, $code_patterns)) {
    sec_waf_forbidden('Código malicioso/RCE detectado');
}

// ========== 15) UPLOAD MALICIOSO ==========
if (!empty($_FILES) && is_array($_FILES)) {
    foreach ($_FILES as $file) {
        if (empty($file['name'])) {
            continue;
        }

        $filename = strtolower((string) $file['name']);
        $ext      = pathinfo($filename, PATHINFO_EXTENSION);

        $dangerous_extensions = [
            'php', 'php3', 'php4', 'php5', 'phtml', 'phar',
            'exe', 'bat', 'sh', 'bash', 'cgi', 'pl', 'py',
            'jsp', 'asp', 'aspx',
        ];

        if (in_array($ext, $dangerous_extensions, true)) {
            sec_waf_forbidden("Upload bloqueado - extensão perigosa: .{$ext}");
        }
    }
}

// ========== 16) SUPPLY-CHAIN - DOMÍNIOS MALICIOSOS ==========
$url_params = ['cdn', 'script', 'src', 'resource', 'lib', 'external'];

foreach ($url_params as $param) {
    if (isset($_REQUEST[$param])) {
        $url = (string) $_REQUEST[$param];

        if (preg_match('#^https?://#i', $url)) {
            $domain = strtolower(parse_url($url, PHP_URL_HOST) ?: '');

            if (in_array($domain, $SEC_BLACKLIST_SCRIPT_DOMAINS, true)) {
                sec_waf_forbidden("Supply-chain bloqueado: {$domain}");
            }
        }
    }
}

// ========== 17) CI/CD - ARQUIVOS SENSÍVEIS ==========
$ci_paths = [
    '.git/', '.github/', '.gitlab-ci', '.env',
    'composer.json', 'package.json', 'web.config', 'wp-config',
];

if (sec_waf_contains($request_uri, $ci_paths)) {
    sec_waf_forbidden('Acesso a arquivo de configuração bloqueado');
}

// ========== 18) AUTO-TESTE ==========
if (isset($_GET['selftest']) && $_GET['selftest'] === '1') {
    header('Content-Type: text/plain; charset=utf-8');

    echo "🛡️  SECURITY WAF v3.0 - STATUS\n";
    echo str_repeat('=', 60) . "\n\n";
    echo "✅ Modo: " . SEC_WAF_MODE . "\n";
    echo "✅ IP Cliente: " . sec_waf_ip() . "\n";
    echo "✅ IPs Whitelist (Admin): " . count($SEC_WHITELIST_IPS) . "\n";
    echo "✅ IPs Bloqueados: " . count($SEC_BLOCKED_IPS) . "\n";
    echo "✅ Caminhos Excluídos: " . count($SEC_EXCLUDED_PATHS) . "\n";
    echo "✅ CDNs Confiáveis: " . count($SEC_WHITELIST_SCRIPT_DOMAINS) . "\n";
    echo "✅ Domínios Maliciosos: " . count($SEC_BLACKLIST_SCRIPT_DOMAINS) . "\n\n";

    echo "PROTEÇÕES ATIVAS:\n";
    echo "  ✅ Whitelist de IPs (Admin)\n";
    echo "  ✅ Bloqueio de Malware (Leostop)\n";
    echo "  ✅ XSS Protection\n";
    echo "  ✅ SQL Injection (Inteligente)\n";
    echo "  ✅ RCE Protection\n";
    echo "  ✅ Upload Seguro\n";
    echo "  ✅ Supply-Chain Protection\n";
    echo "  ✅ Rate Limiting\n\n";

    echo "✅ Sistema OK - " . sec_waf_now() . "\n";
    exit;
}

/* ========================= FIM ========================= */
