<?php
/**
 * config/licenca_cron.php
 * Envia alertas de vencimento por WhatsApp e/ou E-mail nos dias definidos.
 * - Rode 1x/dia via CRON (CLI): php -q /home/USUARIO/public_html/config/licenca_cron.php
 * - Para inspecionar no navegador: /config/licenca_cron.php?debug=1
 * - Para forçar envio em teste:   /config/licenca_cron.php?debug=1&force=1
 * Requer .env na RAIZ com pelo menos:
 *   LICENCA_DOMINIO, LICENCA_EXPIRA, LIC_ALERT_DAYS (ex.: 7,3,1), LIC_SEND_ALERTS (1/0)
 * Contatos (opcional):
 *   LIC_CONTATO_EMAIL, LIC_CONTATO_WHATS (55+DDD+número)
 * WhatsApp (duas opções):
 *   (A) Genérico por HTTP JSON: WA_URL, WA_TOKEN (Bearer opcional)
 *   (B) Seu /apis/texto.php (Menuia): o arquivo deve existir; usa as variáveis já usadas no seu sistema.
 */

header('X-Robots-Tag: noindex, nofollow');

/* -------- utils -------- */
function _env_load(string $file): array {
    $out = [];
    if (!is_readable($file)) return $out;
    foreach (file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $ln) {
        $ln = trim($ln);
        if ($ln === '' || $ln[0] === '#') continue;
        [$k,$v] = array_pad(explode('=', $ln, 2), 2, '');
        $out[trim($k)] = trim($v, " \t\n\r\0\x0B\"'");
    }
    return $out;
}
function only_digits(string $s): string { return preg_replace('/\D+/', '', $s); }
function phone_to_wa(string $s): string {
    $d = only_digits($s);
    if ($d === '') return '';
    if (strpos($d, '55') !== 0 && strlen($d) >= 10) $d = '55'.$d;
    return $d;
}

/* -------- carregamento/env -------- */
$root = dirname(__DIR__);
$ENV  = _env_load($root.'/.env');

$dom      = trim($ENV['LICENCA_DOMINIO'] ?? '');
$exp      = trim($ENV['LICENCA_EXPIRA']  ?? '');
$daysStr  = trim($ENV['LIC_ALERT_DAYS']  ?? '7,3,1');
$sendOn   = (int)($ENV['LIC_SEND_ALERTS'] ?? 1);

$toMail   = trim($ENV['LIC_CONTATO_EMAIL'] ?? '');
$toWhats  = phone_to_wa($ENV['LIC_CONTATO_WHATS'] ?? '');

/* Whats genérico */
$WA_URL   = trim($ENV['WA_URL']   ?? '');
$WA_TOKEN = trim($ENV['WA_TOKEN'] ?? '');

/* flags debug/force (URL ou CLI) */
$debug = isset($_GET['debug']) || in_array('--debug', $argv ?? []);
$force = isset($_GET['force']) || in_array('--force', $argv ?? []);

/* -------- cálculo de dias -------- */
$today = new DateTimeImmutable('today');
$expDt = $exp ? DateTimeImmutable::createFromFormat('Y-m-d', $exp) : null;
$diffDays = $expDt ? (int)$today->diff($expDt)->format('%r%a') : null;

$thresholds = array_map('intval', array_filter(array_map('trim', explode(',', $daysStr))));
$mustSend = ($diffDays !== null) && (in_array($diffDays, $thresholds, true) || $diffDays === 0);

/* cache anti-duplicidade no mesmo dia */
$cacheKey = ($ENV['LICENCA_DOMINIO'] ?? 'dom') . '_' . date('Ymd');
$cache    = sys_get_temp_dir() . '/lic_alert_'.$cacheKey.'.flag';

/* -------- montagem da mensagem -------- */
$mensagem = '';
$route    = null; // 'generic' | 'texto'
$status   = 'idle';
$reason   = 'not threshold';

if ($expDt) {
    if ($diffDays > 0) {
        $quando   = $expDt->format('d/m/Y');
        $mensagem = "⚠️ Licença do domínio {$dom} expira em {$quando}. Por favor, regularize para evitar bloqueio.";
    } elseif ($diffDays === 0) {
        $mensagem = "⚠️ Licença do domínio {$dom} expira HOJE. O sistema será bloqueado ao fim do dia.";
    } else {
        $mensagem = "⚠️ Licença do domínio {$dom} está EXPIRADA. Entre em contato para reativação.";
    }
}

/* -------- rotas de envio disponíveis -------- */
if ($toWhats !== '') {
    if ($WA_URL !== '') {
        $route = 'generic';
    } else {
        // tenta seu /apis/texto.php
        $t1 = $root.'/apis/texto.php';
        $t2 = $root.'/painel/apis/texto.php';
        if (is_file($t1) || is_file($t2)) $route = 'texto';
    }
}

/* -------- função: enviar Whats genérico (JSON) -------- */
function send_whatsapp_generic(string $url, string $token, string $toDigits, string $text): string {
    $payload = json_encode(['to'=>$toDigits, 'text'=>$text], JSON_UNESCAPED_UNICODE);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_POST => true,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => array_filter([
            'Content-Type: application/json',
            $token ? "Authorization: Bearer {$token}" : null
        ]),
        CURLOPT_POSTFIELDS => $payload,
        CURLOPT_TIMEOUT => 15,
    ]);
    $resp = curl_exec($ch);
    curl_close($ch);
    return (string)$resp;
}

/* -------- função: enviar via seu /apis/texto.php (Menuia) -------- */
function send_whatsapp_texto_php(string $root, string $toDigits, string $text): string {
    // monta $mensagem como você usa (com %0A)
    $mensagem = str_replace("\n", "%0A", $text);
    $telefone_envio = $toDigits;
    $api_whatsapp = 'menuia';

    // tentar caminhos
    $t1 = $root.'/apis/texto.php';
    $t2 = $root.'/painel/apis/texto.php';
    $path = is_file($t1) ? $t1 : (is_file($t2) ? $t2 : null);
    if (!$path) return 'texto.php não encontrado';

    // captura qualquer saída/retorno
    ob_start();
    $response = null;
    try { require $path; } catch (Throwable $e) {}
    $out = trim((string)ob_get_clean());
    return $response !== null ? (string)$response : ($out !== '' ? $out : 'OK');
}

/* -------- decidir se envia -------- */
if ($sendOn && $expDt) {
    $shouldSend = ($mustSend && !file_exists($cache)) || $force;
    if ($shouldSend) {
        $status = 'sending';
        $reason = $force ? 'forced' : 'threshold';

        // e-mail (se tiver)
        if ($toMail !== '') {
            @mail($toMail, "Aviso de vencimento - {$dom}", $mensagem, "From: noreply@{$dom}\r\n");
        }

        // Whats
        $whatsResp = null;
        if ($route === 'generic' && $WA_URL !== '' && $toWhats !== '') {
            $whatsResp = send_whatsapp_generic($WA_URL, $WA_TOKEN, $toWhats, $mensagem);
        } elseif ($route === 'texto' && $toWhats !== '') {
            $whatsResp = send_whatsapp_texto_php($root, $toWhats, $mensagem);
        }

        // grava flag do dia (evitar duplicidade)
        if (!$force) @file_put_contents($cache, '1');

        if ($debug) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'dominio'     => $dom,
                'expira'      => $exp,
                'dias_rest'   => $diffDays,
                'thresholds'  => $thresholds,
                'send_alerts' => $sendOn,
                'route'       => $route,
                'status'      => 'done',
                'whats_resp'  => $whatsResp,
                'forced'      => $force,
            ], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
            exit;
        }
        exit; // silencioso no CRON
    }
}

/* -------- saída debug opcional -------- */
if ($debug) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'dominio'     => $dom,
        'expira'      => $exp,
        'dias_rest'   => $diffDays,
        'thresholds'  => $thresholds,
        'send_alerts' => $sendOn,
        'route'       => $route,
        'status'      => 'idle',
        'reason'      => $expDt ? 'not threshold' : 'no expiration set',
    ], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);
}
