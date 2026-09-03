<?php
/**
 * /cron/cron_security.php - VERSÃO MELHORADA 2.0 (AJUSTADA)
 * Varredura avançada + alerta por WhatsApp ao ADMIN (pego do DB) — compatível com PHP 7.x
 *
 * NOVAS PROTEÇÕES:
 * ✅ Detecção de Leostop e variantes
 * ✅ Backdoors conhecidos (c99, r57, WSO, etc)
 * ✅ Integração com logs do security.php
 * ✅ Verificação de arquivos críticos comprometidos
 * ✅ Detecção de malware em todos os diretórios
 * ✅ Análise de permissões suspeitas
 * ✅ Verificação de .htaccess comprometido
 *
 * Testes:
 *   - Navegador: https://SEU-DOMINIO/cron/cron_security.php?token=TOKEN_CRON&test=1[&to=55DDXXXXXXXXX][&debug=1]
 *   - Cron:      /usr/bin/php /home/SEUUSER/public_html/cron/cron_security.php >/dev/null 2>&1
 */

if (realpath(__FILE__) !== realpath($_SERVER['SCRIPT_FILENAME'] ?? '')) {
    return;
}

require_once __DIR__ . '/cron_guard.php';

$RECENT_WINDOW_H = 72;
$MAX_LIST = 20;
$DEEP_SCAN = false;

$WEBROOT = rtrim(dirname(__DIR__), '/');
$ROOT = $WEBROOT;
$UPLOADS = $WEBROOT . '/uploads';

$host = isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'site';

$isCli = (php_sapi_name() === 'cli');
$GET = isset($_GET) ? $_GET : array();

$TEST_MODE = (isset($GET['test']) && (string)$GET['test'] === '1');
$TEST_TO = isset($GET['to']) ? (string)$GET['to'] : '';
$DEBUG     = isset($GET['debug']) && $GET['debug'] === '1';

if (!$isCli && $DEBUG) {
    @header('Content-Type: text/plain; charset=utf-8');
}

$lockFile = sys_get_temp_dir() . '/cron_security.lock';
$lockTtl = 30 * 60;

$__fp = @fopen($lockFile, 'c+');
if ($__fp === false) {
    if ($DEBUG) {
        echo "LOCK: falha ao abrir $lockFile\n";
    }
    exit;
}
$got = @flock($__fp, LOCK_EX | LOCK_NB);
if (!$got) {
    $st = @fstat($__fp);
    $age = $st && isset($st['mtime']) ? (time() - (int) $st['mtime']) : 0;
    if ($age <= $lockTtl) {
        if ($DEBUG) {
            echo "LOCK: já em execução. Idade do lock: {$age}s\n";
        }
        fclose($__fp);
        exit;
    }
    @flock($__fp, LOCK_EX);
}
@ftruncate($__fp, 0);
@fwrite($__fp, (string) getmypid());
@fflush($__fp);
register_shutdown_function(function () use ($__fp, $lockFile) {
    @flock($__fp, LOCK_UN);
    @fclose($__fp);
    @unlink($lockFile);
});

$pdo = null;
$cronConfig = bb_cron_guard_config();
$authMaster = (isset($cronConfig['auth_master']) && is_array($cronConfig['auth_master']))
    ? $cronConfig['auth_master']
    : array();

if (!empty($authMaster['host']) && !empty($authMaster['db']) && !empty($authMaster['user'])) {
    try {
        $pdo = new PDO(
            'mysql:host=' . $authMaster['host'] . ';dbname=' . $authMaster['db'] . ';charset=utf8mb4',
            (string)$authMaster['user'],
            (string)($authMaster['pass'] ?? ''),
            array(
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            )
        );

        if (!bb_cron_guard_database_matches($pdo, (string)$authMaster['db'])) {
            $pdo = null;
        }
    } catch (Throwable $e) {
        $pdo = null;
    }
}

$adminNumbers = array();
$token = null;
$instancia = null;

if ($pdo) {
    try {
        $cfg = $pdo->query("SELECT telefone_whatsapp, token, instancia FROM config ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($cfg) {
            if (!empty($cfg['telefone_whatsapp'])) {
                $rawStr = (string) $cfg['telefone_whatsapp'];
                $candidates = preg_split('/[;,|]+/u', $rawStr, -1, PREG_SPLIT_NO_EMPTY);

                foreach ($candidates as $cand) {
                    $d = preg_replace('/\D+/', '', $cand);
                    if ($d === '') {
                        continue;
                    }
                    if (strpos($d, '00') === 0) {
                        $d = substr($d, 2);
                    }
                    $len = strlen($d);
                    if ($len === 10 || $len === 11) {
                        if (strpos($d, '55') !== 0) {
                            $d = '55' . $d;
                        }
                    }
                    if (strlen($d) >= 12) {
                        $adminNumbers[] = $d;
                    }
                }

                if (empty($adminNumbers)) {
                    $dAll = preg_replace('/\D+/', '', $rawStr);
                    if ($dAll !== '') {
                        if (strpos($dAll, '00') === 0) {
                            $dAll = substr($dAll, 2);
                        }
                        $len = strlen($dAll);
                        if ($len === 10 || $len === 11) {
                            if (strpos($dAll, '55') !== 0) {
                                $dAll = '55' . $dAll;
                            }
                        }
                        if (strlen($dAll) >= 12) {
                            $adminNumbers[] = $dAll;
                        }
                    }
                }

                $adminNumbers = array_values(array_unique($adminNumbers));
            }

            if (!empty($cfg['token'])) {
                $token = $cfg['token'];
            }
            if (!empty($cfg['instancia'])) {
                $instancia = $cfg['instancia'];
            }
        }
    } catch (Throwable $e) {
        /* segue */
    }
}

if ($TEST_MODE && $TEST_TO !== '') {
    $d = preg_replace('/\D+/', '', $TEST_TO);
    if ($d !== '') {
        if (strpos($d, '55') !== 0) {
            $d = '55' . $d;
        }
        $adminNumbers = array($d);
    }
}

if (empty($adminNumbers)) {
    $adminNumbers = array('5545999580058');
}

if ($DEBUG) {
    echo "DEBUG:\n";
    echo "- WEBROOT : {$WEBROOT}\n";
    echo "- Destinos: " . (empty($adminNumbers) ? "(nenhum)\n" : implode(', ', $adminNumbers) . "\n");
    echo "- Tem token/instancia do DB? " . ((!empty($token) && !empty($instancia)) ? "SIM\n" : "NÃO\n");
}

function octalPerms($path)
{
    $p = @fileperms($path);
    return $p === false ? '????' : substr(sprintf('%o', $p), -4);
}

function skipPath($path)
{
    static $skip = array('/vendor/', '/node_modules/', '/.git/', '/.well-known/', '/cache/', '/storage/logs/', '/logs/', '/tmp/');
    $p = str_replace('\\', '/', $path);
    foreach ($skip as $s) {
        if (strpos($p, $s) !== false) {
            return true;
        }
    }
    return false;
}

function listRecent($root, $hours, $limit)
{
    $now = time();
    $out = array();
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));
    foreach ($it as $f) {
        if ($f->isDir()) {
            continue;
        }
        $p = $f->getPathname();
        if (skipPath($p)) {
            continue;
        }
        if ($now - $f->getMTime() <= $hours * 3600) {
            $out[] = array('p' => $p, 't' => $f->getMTime(), 's' => $f->getSize());
            if (count($out) >= $limit) {
                break;
            }
        }
    }
    usort($out, function ($a, $b) {
        if ($b['t'] === $a['t']) {
            return 0;
        }
        return ($b['t'] < $a['t']) ? -1 : 1;
    });
    return $out;
}

function scanSuspicious($root, $deep, $limit)
{
    $sig = array(
        'eval(', 'base64_decode(', 'gzinflate(', 'gzuncompress(', 'gzdecode(', 'str_rot13(',
        'create_function(', 'assert(', 'preg_replace.*\/e',
        'shell_exec(', 'passthru(', 'proc_open(', 'popen(', 'system(', 'exec(',
        'pcntl_exec', 'expect_popen',
        'auto_prepend_file', 'auto_append_file',
        'error_reporting(0', 'ini_set("display_errors","0")',
        'ini_set(\'display_errors\',\'0\')',
        'c99', 'r57', 'wso', 'shell', 'backdoor', 'leostop', 'FilesMan',
        'WSO_VERSION', 'c99sh', 'r57shell', 'Safe0ver', 'AnonymousFox',
        'fsockopen', 'pfsockopen', 'stream_socket_client', 'curl_exec',
        'file_get_contents("http', 'file_get_contents(\'http',
        '${$', '}${', '$_GET[', '$_POST[', '$_REQUEST[', '$_COOKIE[', '$GLOBALS[',
        'chr(', 'ord(', 'hexdec', 'dechex', 'convert_uuencode', 'convert_uudecode',
        'document.write', '<script', 'eval(', '.appendChild',
        'leostop', 'Leo Stop', 'leost0p', 'le0st0p', 'LEOSTOP',
    );

    $hits = array();
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

    foreach ($it as $f) {
        if ($f->isDir()) {
            continue;
        }
        $p = $f->getPathname();
        if (skipPath($p)) {
            continue;
        }
        if (!preg_match('/\.(php|phtml|php[0-9]+|suspected|inc|module)$/i', $p)) {
            continue;
        }
        if (!$deep && $f->getSize() > 1500000) {
            continue;
        }

        $c = @file_get_contents($p);
        if ($c === false) {
            continue;
        }

        foreach ($sig as $s) {
            if (stripos($c, $s) !== false) {
                $hits[] = array('p' => $p, 'sig' => $s);
                break;
            }
        }

        if (count($hits) >= $limit) {
            break;
        }
    }

    return $hits;
}

function findBackdoorsByName($root, $limit)
{
    $backdoorNames = array(
        'c99.php', 'r57.php', 'wso.php', 'shell.php', 'backdoor.php',
        'hack.php', 'hacked.php', 'upload.php', 'uploader.php',
        'leostop.php', 'leo.php', 'mysql.php', 'adminer.php',
        'phpinfo.php', 'info.php', 'test.php', 'bypass.php',
        'safe.php', 'safe0ver.php', 'indoxploit.php', 'fox.php',
        'mini.php', 'alfa.php', 'b374k.php', 'idx.php',
    );

    $found = array();
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

    foreach ($it as $f) {
        if ($f->isDir()) {
            continue;
        }
        $p = $f->getPathname();
        if (skipPath($p)) {
            continue;
        }

        $filename = strtolower(basename($p));

        foreach ($backdoorNames as $bd) {
            if ($filename === $bd || strpos($filename, $bd) !== false) {
                $found[] = array('p' => $p, 'name' => $filename, 'size' => $f->getSize());
                break;
            }
        }

        if (count($found) >= $limit) {
            break;
        }
    }

    return $found;
}

function findWorldWritable($root, $limit)
{
    $writable = array();
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS));

    foreach ($it as $f) {
        $p = $f->getPathname();
        if (skipPath($p)) {
            continue;
        }

        $perms = @fileperms($p);
        if ($perms === false) {
            continue;
        }

        if (($perms & 0x0002) && ($perms & 0x0020)) {
            $writable[] = array('p' => $p, 'perm' => octalPerms($p));
            if (count($writable) >= $limit) {
                break;
            }
        }
    }

    return $writable;
}

function findPhpInUploads($uploads, $limit)
{
    $out = array();
    if (!is_dir($uploads)) {
        return $out;
    }

    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($uploads, FilesystemIterator::SKIP_DOTS));

    foreach ($it as $f) {
        if ($f->isDir()) {
            continue;
        }
        $p = $f->getPathname();

        if (preg_match('/\.(php|phtml|php[0-9]+|phar|suspected|inc)$/i', $p)) {
            $out[] = array('p' => $p, 'perm' => octalPerms($p), 'size' => $f->getSize());
            if (count($out) >= $limit) {
                break;
            }
        }
    }

    return $out;
}

function checkUploadsHtaccess($uploads)
{
    $ht = rtrim($uploads, '/') . '/.htaccess';
    $exists = is_file($ht);
    $ok = false;

    if ($exists) {
        $c = @file_get_contents($ht) ?: '';
        $ok = (stripos($c, 'php_flag engine off') !== false) ||
              (preg_match('/Require\s+all\s+denied/i', $c) && preg_match('/FilesMatch/i', $c));
    }

    return array('exists' => $exists, 'ok' => $ok, 'path' => $ht);
}

function checkMainHtaccess($root)
{
    $ht = rtrim($root, '/') . '/.htaccess';
    if (!is_file($ht)) {
        return array('exists' => false, 'compromised' => false);
    }

    $c = @file_get_contents($ht) ?: '';

    $suspicious = array(
        'auto_prepend_file', 'auto_append_file',
        'RewriteRule.*base64', 'ErrorDocument.*php',
        'php_value.*allow_url_include',
        'AddHandler.*txt', 'AddType.*gif',
    );

    $compromised = false;
    foreach ($suspicious as $s) {
        if (preg_match('/' . $s . '/i', $c)) {
            $compromised = true;
            break;
        }
    }

    return array('exists' => true, 'compromised' => $compromised, 'path' => $ht);
}

function checkSecurityLogs($webroot)
{
    $logFile = $webroot . '/logs/security-block.log';
    if (!is_file($logFile)) {
        return array('exists' => false, 'recent' => 0, 'critical' => 0, 'spam_virus' => 0, 'examples' => array());
    }

    $lines = @file($logFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (!$lines || !is_array($lines)) {
        return array('exists' => true, 'recent' => 0, 'critical' => 0, 'spam_virus' => 0, 'examples' => array());
    }

    $now = time();
    $recent = 0;
    $critical = 0;
    $spamVirus = 0;
    $examples = array();
    $window = 24 * 3600;

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') {
            continue;
        }

        $matched = preg_match('/(\d{4}-\d{2}-\d{2} \d{2}:\d{2}:\d{2})/', $line, $m) ||
                   preg_match('/(\d{2}\/\d{2}\/\d{4} \d{2}:\d{2}:\d{2})/', $line, $m);

        if ($matched && isset($m[1])) {
            $dateStr = trim($m[1]);

            if (strpos($dateStr, '/') !== false) {
                $parts = explode(' ', $dateStr);
                if (count($parts) === 2) {
                    $datePart = $parts[0];
                    $timePart = $parts[1];
                    $dateBits = explode('/', $datePart);
                    if (count($dateBits) === 3) {
                        $dateStr = $dateBits[2] . '-' . $dateBits[1] . '-' . $dateBits[0] . ' ' . $timePart;
                    }
                }
            }

            $ts = @strtotime($dateStr);

            if ($ts !== false && $ts !== null && $ts > 0) {
                if (($now - $ts) <= $window) {
                    $recent++;
                    if (stripos($line, 'BLOCK') !== false || stripos($line, 'blocked') !== false) {
                        $critical++;
                        // Bloqueio de cliente (ex.: spam/vírus/WAF)
                        $spamVirus++;
                        if (count($examples) < 5) {
                            $examples[] = trim($line);
                        }
                    }
                }
            }
        }
    }

    return array('exists' => true, 'recent' => $recent, 'critical' => $critical, 'spam_virus' => $spamVirus, 'examples' => $examples, 'path' => $logFile);
}

function checkCriticalFiles($root)
{
    $critical = array(
        'index.php',
        'config.php',
        'sistema/conexao.php',
        'wp-config.php',
        '.htaccess',
    );

    $modified = array();

    foreach ($critical as $file) {
        $path = $root . '/' . $file;
        if (!is_file($path)) {
            continue;
        }

        $mtime = filemtime($path);
        $age = time() - $mtime;

        if ($age <= 48 * 3600) {
            $modified[] = array('p' => $path, 'age' => $age, 'mtime' => date('Y-m-d H:i:s', $mtime));
        }
    }

    return $modified;
}

function summarize($rows, $root, $max = 5)
{
    $rootLen = strlen($root);
    $rows = array_slice($rows, 0, $max);
    $out = array();

    foreach ($rows as $r) {
        $path = (is_array($r) && isset($r['p'])) ? $r['p'] : (string) $r;
        $short = substr($path, $rootLen);

        if (is_array($r)) {
            $extra = '';
            if (isset($r['sig'])) {
                $extra .= " [sig: {$r['sig']}]";
            }
            if (isset($r['perm'])) {
                $extra .= " [perm: {$r['perm']}]";
            }
            if (isset($r['size'])) {
                $extra .= " [" . round($r['size'] / 1024, 1) . "KB]";
            }
            $out[] = '- ' . $short . $extra;
        } else {
            $out[] = '- ' . $short;
        }
    }

    return implode("\n", $out);
}

function cron_is_msg_success($status_mensagem)
{
    if (!isset($status_mensagem)) {
        return false;
    }

    $sm = trim((string) $status_mensagem);

    if ($sm === 'Mensagem enviada com sucesso.' ||
        stripos($sm, 'Mensagem enviada com sucesso') !== false) {
        return true;
    }

    $decoded = json_decode($sm, true);
    if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
        if (isset($decoded['status']) && (int) $decoded['status'] === 200) {
            return true;
        }
        if (!empty($decoded['message']) && stripos($decoded['message'], 'sucesso') !== false) {
            return true;
        }
    }

    return false;
}

if ($DEBUG) {
    echo "\n🔍 Iniciando varredura de segurança...\n\n";
}

$recent = listRecent($ROOT, $RECENT_WINDOW_H, $MAX_LIST);
$hits = scanSuspicious($ROOT, $DEEP_SCAN, $MAX_LIST);
$backdoors = findBackdoorsByName($ROOT, $MAX_LIST);
$phpUploads = findPhpInUploads($UPLOADS, $MAX_LIST);
$worldWritable = findWorldWritable($ROOT, $MAX_LIST);
$upHt = checkUploadsHtaccess($UPLOADS);
$mainHt = checkMainHtaccess($ROOT);
$secLogs = checkSecurityLogs($WEBROOT);
$criticalMod = checkCriticalFiles($ROOT);

$envPath = $ROOT . '/.env';
$envInfo = array(
    'exists' => is_file($envPath),
    'perm' => is_file($envPath) ? octalPerms($envPath) : null,
);

if ($DEBUG) {
    echo "📊 Resultados da varredura:\n";
    echo "- Arquivos recentes: " . count($recent) . "\n";
    echo "- Assinaturas suspeitas: " . count($hits) . "\n";
    echo "- Backdoors por nome: " . count($backdoors) . "\n";
    echo "- PHP em uploads: " . count($phpUploads) . "\n";
    echo "- Arquivos 777: " . count($worldWritable) . "\n";
    echo "- .htaccess uploads OK: " . ($upHt['ok'] ? 'SIM' : 'NÃO') . "\n";
    echo "- .htaccess principal comprometido: " . ($mainHt['compromised'] ? 'SIM' : 'NÃO') . "\n";
    echo "- Logs security.php (24h): " . $secLogs['recent'] . " eventos\n";
    echo "- Arquivos críticos modificados: " . count($criticalMod) . "\n\n";
}

$issues = array();

if (!$upHt['exists'] || !$upHt['ok']) {
    $issues[] = 'uploads_sem_bloqueio';
}
if (!empty($phpUploads)) {
    $issues[] = 'php_em_uploads';
}
if (!empty($hits)) {
    $issues[] = 'assinaturas_suspeitas';
}
if ($envInfo['exists'] && $envInfo['perm'] !== '0600') {
    $issues[] = '.env_permissao';
}

if (!empty($backdoors)) {
    $issues[] = 'backdoors_detectados';
}
if (!empty($worldWritable)) {
    $issues[] = 'arquivos_777';
}
if ($mainHt['compromised']) {
    $issues[] = 'htaccess_comprometido';
}
if ($secLogs['critical'] > 10) {
    $issues[] = 'muitos_bloqueios_security';
}

if (!empty($secLogs['spam_virus']) && $secLogs['spam_virus'] > 0) {
    $issues[] = 'cliente_bloqueado_spam_virus';
}
if (!empty($criticalMod)) {
    $issues[] = 'arquivos_criticos_modificados';
}

$mensagem = '';

if ($TEST_MODE) {
    $msg = array();
    $msg[] = "✅ *PING de Segurança* — {$host}";
    $msg[] = "Cron ativo e envio WhatsApp funcionando.";
    $msg[] = "Hora do servidor: " . date('Y-m-d H:i:s');
    $msg[] = "\n📊 *Status da Varredura:*";
    $msg[] = "• Arquivos recentes: " . count($recent);
    $msg[] = "• Assinaturas suspeitas: " . count($hits);
    $msg[] = "• Backdoors detectados: " . count($backdoors);
    $msg[] = "• PHP em uploads: " . count($phpUploads);
    $msg[] = "• Arquivos 777: " . count($worldWritable);
    $msg[] = "• Logs security.php (24h): {$secLogs['recent']} eventos";
    $msg[] = "\nSem verificação de achados (modo de teste).";
    $mensagem = implode("\n", $msg);

} elseif (!empty($issues)) {
    $msg = array();
    $msg[] = "🚨 *ALERTA DE SEGURANÇA* — {$host}";
    $msg[] = date('Y-m-d H:i:s');
    $msg[] = "";

    if (!$upHt['exists'] || !$upHt['ok']) {
        $msg[] = "⚠️ *uploads/.htaccess*: ausente ou incompleto";
        $msg[] = "   Ação: Bloquear execução PHP em /uploads";
    }

    if ($mainHt['compromised']) {
        $msg[] = "🚨 *.htaccess PRINCIPAL COMPROMETIDO*";
        $msg[] = "   Contém código suspeito! Revisar urgente!";
    }

    if (!empty($backdoors)) {
        $msg[] = "🚨 *BACKDOORS DETECTADOS*: " . count($backdoors);
        $msg[] = summarize($backdoors, $ROOT, 5);
        $msg[] = "   Ação: REMOVER IMEDIATAMENTE!";
    }

    if (!empty($phpUploads)) {
        $msg[] = "⚠️ *PHP em /uploads*: " . count($phpUploads);
        $msg[] = summarize($phpUploads, $ROOT, 5);
    }

    if (!empty($hits)) {
        $msg[] = "⚠️ *Código suspeito em PHP*: " . count($hits);
        $msg[] = summarize($hits, $ROOT, 5);
    }

    if (!empty($worldWritable)) {
        $msg[] = "⚠️ *Permissões 777*: " . count($worldWritable);
        $msg[] = summarize($worldWritable, $ROOT, 3);
    }

    if (!empty($secLogs['spam_virus']) && $secLogs['spam_virus'] > 0) {
        $msg[] = "🚫 *Cliente bloqueado (spam/vírus)* (24h): {$secLogs['spam_virus']}";
        if (!empty($secLogs['examples']) && is_array($secLogs['examples'])) {
            foreach ($secLogs['examples'] as $ex) {
                $msg[] = "   • " . $ex;
            }
        }
        $msg[] = "   Log: /logs/security-block.log";
    }

    if ($secLogs['critical'] > 10) {
        $msg[] = "⚠️ *Muitos bloqueios* (24h): {$secLogs['critical']}";
        $msg[] = "   Verifique: /logs/security-block.log";
    }

    if (!empty($criticalMod)) {
        $msg[] = "⚠️ *Arquivos críticos modificados*: " . count($criticalMod);
        foreach ($criticalMod as $cf) {
            $short = substr($cf['p'], strlen($ROOT));
            $msg[] = "- {$short} [{$cf['mtime']}]";
        }
    }

    if ($envInfo['exists'] && $envInfo['perm'] !== '0600') {
        $msg[] = "⚠️ *.env* com permissão {$envInfo['perm']}";
        $msg[] = "   Ideal: 0600 ou mover para fora de public_html";
    }

    if (!empty($recent)) {
        $msg[] = "\n📝 *Alterações recentes* (≤{$RECENT_WINDOW_H}h): " . count($recent);
        $msg[] = summarize($recent, $ROOT, 5);
    }

    $msg[] = "\n🛡️ *AÇÕES RECOMENDADAS:*";
    $msg[] = "1. Revisar e remover backdoors imediatamente";
    $msg[] = "2. Bloquear execução PHP em /uploads";
    $msg[] = "3. Corrigir permissões 777";
    $msg[] = "4. Verificar .htaccess principal";
    $msg[] = "5. Reexecutar varredura após correções";

    $mensagem = implode("\n", $msg);
}

$api = 'menuia';

$textoPath = null;
$textoLocal = __DIR__ . '/texto.php';
$textoRoot = $WEBROOT . '/texto.php';

if (is_file($textoLocal)) {
    $textoPath = $textoLocal;
} elseif (is_file($textoRoot)) {
    $textoPath = $textoRoot;
}

if ($DEBUG) {
    if ($textoPath) {
        echo "- texto.php encontrado em: {$textoPath}\n";
    } else {
        echo "- texto.php NÃO encontrado em {$textoLocal} nem {$textoRoot}\n";
    }
}

$sentAny = false;
$deveAvisarAdmin = $TEST_MODE || !empty($issues);
if ($mensagem !== '' && $deveAvisarAdmin && !empty($adminNumbers) && $textoPath !== null) {
    $msg_nl = str_replace(array("\r\n", "\r"), "\n", $mensagem);
    $msg_url = str_replace("\n", ' %0A', $msg_nl);

    foreach ($adminNumbers as $n) {
        $telefone = $n;

        $mensagem = $msg_url;
        $status_mensagem = null;
        if ($DEBUG) {
            echo "- Enviando para {$telefone} (com %0A)\n";
        }
        require $textoPath;
        $ok = cron_is_msg_success($status_mensagem);

        if (!$ok) {
            $mensagem = $msg_nl;
            $status_mensagem = null;
            if ($DEBUG) {
                echo "  Retentando para {$telefone} (com \\n)\n";
            }
            require $textoPath;
            $ok = cron_is_msg_success($status_mensagem);
        }

        $sentAny = $sentAny || $ok;
        if ($DEBUG) {
            echo "  => " . ($ok ? "OK\n" : "FALHA\n");
        }
    }
} elseif ($mensagem !== '' && empty($adminNumbers) && $DEBUG) {
    echo "- Nenhum número de admin configurado em config.telefone_whatsapp\n";
} elseif ($mensagem !== '' && $textoPath === null && $DEBUG) {
    echo "- Mensagem gerada, mas texto.php não encontrado; nada enviado.\n";
}

if ($isCli) {
    if ($mensagem === '') {
        echo "✅ OK: Sem achados críticos.\n";
    } else {
        if ($TEST_MODE) {
            echo $sentAny ? "✅ PING enviado.\n" : "⚠️ PING gerado (falha no WhatsApp).\n";
        } else {
            echo $sentAny ? "🚨 ALERTA enviado.\n" : "⚠️ ALERTA gerado (falha no WhatsApp).\n";
        }
    }
} else {
    // (não tenta enviar header aqui)
    if ($mensagem === '') {
        echo "✅ OK: Sem achados críticos em {$host}\n";
        echo "\n📊 Última varredura: " . date('Y-m-d H:i:s') . "\n";
        echo "- Arquivos verificados: " . count($recent) . " recentes\n";
        echo "- Sistema protegido\n";
    } else {
        echo $mensagem . "\n\n";
        if ($TEST_MODE) {
            echo $sentAny
                ? "✅ OK: ping enviado por WhatsApp.\n"
                : "⚠️ Atenção: ping gerado, mas não foi possível enviar via WhatsApp (verifique credenciais em config.token/instancia e o número em config.telefone_whatsapp).\n";
        } else {
            echo $sentAny
                ? "✅ OK: alerta enviado por WhatsApp.\n"
                : "⚠️ Atenção: alerta gerado, mas não foi possível enviar via WhatsApp (verifique credenciais em config.token/instancia e o número em config.telefone_whatsapp).\n";
        }
    }
}
