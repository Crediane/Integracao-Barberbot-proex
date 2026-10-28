<?php
/**
 * /public_html/ajax/file.php
 *
 * BarberBot - Envio de arquivo pela Menuia
 *
 * AJUSTE PONTUAL SEM ALTERAR A LÓGICA DE NEGÓCIO:
 * - Mantém o padrão antigo de variáveis globais:
 *      $telefone, $mensagem, $token, $instancia, $url_envio
 * - Respeita o roteamento do marketplace:
 *      /loja/{slug}/  => banco individual do TENANT
 *      /sistema/      => banco MASTER/global
 * - Quando token/instancia não vierem do chamador, carrega pelo banco do contexto atual.
 * - Normaliza telefone sem duplicar DDI 55.
 * - Define timeout para evitar travamento em "Aguardando mensagem".
 * - Preenche $status_mensagem somente quando a API confirmar envio.
 */

require_once __DIR__ . '/_message_config.php';

$status_mensagem = isset($status_mensagem) ? (string)$status_mensagem : '';

$mensagem = isset($mensagem) ? str_replace("%0A", "\n", (string)$mensagem) : '-';
$telefone = isset($telefone) ? (string)$telefone : '';
$token = isset($token) ? (string)$token : '';
$instancia = isset($instancia) ? (string)$instancia : '';
$url_envio = isset($url_envio) ? (string)$url_envio : '';
$response = '';

if (!function_exists('bb_file_log')) {
    function bb_file_log(string $msg): void {
        @file_put_contents(__DIR__ . '/error_log', '[' . date('d-M-Y H:i:s') . '] BarberBot File: ' . $msg . "\n", FILE_APPEND);
        @error_log('BarberBot File: ' . $msg);
    }
}

if (!function_exists('bb_file_db_name')) {
    function bb_file_db_name($pdo): string {
        try {
            if ($pdo instanceof PDO) {
                $st = $pdo->query("SELECT DATABASE()");
                $db = $st ? $st->fetchColumn() : '';
                return $db ? (string)$db : '';
            }
        } catch (Throwable $e) {
        }
        return '';
    }
}

if (!function_exists('bb_file_context_label')) {
    function bb_file_context_label(): string {
        global $pdo;
        return bb_message_context_label($pdo ?? null);
    }
}

if (!function_exists('bb_file_bootstrap_context')) {
    function bb_file_bootstrap_context(): void {
        global $pdo, $token, $instancia;

        if (trim((string)$token) !== '' && trim((string)$instancia) !== '') {
            return;
        }

        if (!($pdo instanceof PDO)) {
            $conexaoFile = dirname(__DIR__) . '/sistema/conexao.php';
            if (is_file($conexaoFile)) {
                require_once $conexaoFile;
            }

            $timezoneFile = __DIR__ . '/timezone_tenant.php';
            if (is_file($timezoneFile)) {
                require_once $timezoneFile;
            }
        }

        $pdoConfig = bb_message_config_pdo($pdo ?? null);
        if (!($pdoConfig instanceof PDO)) {
            bb_file_log('Banco de configuração não confirmado para o contexto atual.');
            return;
        }

        try {
            $st = $pdoConfig->query("SELECT token, instancia FROM config LIMIT 1");
            $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : false;

            if (is_array($row)) {
                if (trim((string)$token) === '' && isset($row['token'])) {
                    $token = (string)$row['token'];
                }
                if (trim((string)$instancia) === '' && isset($row['instancia'])) {
                    $instancia = (string)$row['instancia'];
                }
            }
        } catch (Throwable $e) {
            bb_file_log('Falha ao ler config do banco confirmado: ' . $e->getMessage());
        }
    }
}

if (!function_exists('bb_file_normaliza_telefone')) {
    function bb_file_normaliza_telefone(string $telefone): string {
        $telefoneRaw = trim($telefone);
        $digits = preg_replace('/\D+/', '', $telefoneRaw);
        $digits = trim((string)$digits);

        if ($digits === '') {
            return '';
        }

        if (strpos($digits, '00') === 0) {
            $digits = substr($digits, 2);
        }

        $temPrefixoInternacional = (bool)preg_match('/^\s*\+/', $telefoneRaw) || (bool)preg_match('/^\s*00/', $telefoneRaw);

        if (!$temPrefixoInternacional && (strlen($digits) === 10 || strlen($digits) === 11)) {
            $digits = '55' . $digits;
        }

        return $digits;
    }
}

bb_file_bootstrap_context();

$ctx = bb_file_context_label();
$dbAtual = '';
if (isset($pdo) && $pdo instanceof PDO) {
    $dbAtual = bb_file_db_name($pdo);
}

$telefone = bb_file_normaliza_telefone($telefone);
$token = trim((string)$token);
$instancia = trim((string)$instancia);
$url_envio = trim((string)$url_envio);

if ($telefone === '' || $token === '' || $instancia === '') {
    bb_file_log(
        'Envio cancelado por dados ausentes | ctx=' . $ctx .
        ' | db=' . $dbAtual .
        ' | telefone=' . ($telefone !== '' ? 'ok' : 'vazio') .
        ' | token=' . ($token !== '' ? 'ok' : 'vazio') .
        ' | instancia=' . ($instancia !== '' ? 'ok' : 'vazio')
    );
    return;
}

$curl = curl_init();

curl_setopt_array($curl, array(
    CURLOPT_URL            => 'https://chatbot.menuia.com/api/create-message',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING       => '',
    CURLOPT_MAXREDIRS      => 10,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST  => 'POST',
    CURLOPT_POSTFIELDS     => array(
        'appkey'  => $token,
        'authkey' => $instancia,
        'to'      => $telefone,
        'message' => $mensagem,
        'file'    => $url_envio,
    ),
));

$response = curl_exec($curl);
$errno = curl_errno($curl);
$err = curl_error($curl);
$http = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

$ok = ($response !== false && $errno === 0 && $http >= 200 && $http < 300);
$respJson = json_decode((string)$response, true);

if ($ok && is_array($respJson) && isset($respJson['status']) && (int)$respJson['status'] !== 200) {
    $ok = false;
}

if ($ok) {
    $status_mensagem = 'Mensagem enviada com sucesso.';
} else {
    bb_file_log(
        'Falha no envio | ctx=' . $ctx .
        ' | db=' . $dbAtual .
        ' | to=' . $telefone .
        ' | http=' . $http .
        ' | errno=' . $errno .
        ' | err=' . $err .
        ' | resp=' . substr((string)$response, 0, 300)
    );
}

if (PHP_SAPI !== 'cli' && basename($_SERVER['SCRIPT_NAME'] ?? '') === 'file.php') {
    echo (string)$response;
}

return;
?>
