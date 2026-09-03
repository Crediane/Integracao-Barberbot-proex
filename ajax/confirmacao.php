<?php
/**
 * confirmacao.php
 * Envia a mensagem de confirmação/aviso do agendamento.
 *
 * AJUSTE FECHADO NO ROTEAMENTO:
 * - /loja/{slug}/ usa exclusivamente o banco individual do TENANT.
 * - /sistema/ usa exclusivamente o banco MASTER/global.
 * - Não força slug por HTTP_REFERER quando a URL atual é /sistema/.
 * - Mantém as mesmas variáveis de entrada usadas pelo sistema.
 * - Remove timeout infinito no Enviame.
 */

if (!function_exists('bb_confirmacao_pick_first')) {
    function bb_confirmacao_pick_first(array $arr, array $keys) {
        foreach ($keys as $key) {
            if (isset($arr[$key]) && trim((string)$arr[$key]) !== '') {
                return (string)$arr[$key];
            }
        }
        return '';
    }
}

$bb_ajax_context_mode = 'any';
$bb_ajax_context_response = 'plain';
require_once __DIR__ . '/_context_bootstrap.php';

if (!function_exists('bb_confirmacao_fill_config')) {
    function bb_confirmacao_fill_config(): void {
        global $pdo, $api, $token, $instancia;

        if (!($pdo instanceof PDO)) {
            return;
        }

        if (trim((string)($api ?? '')) !== '' && trim((string)($token ?? '')) !== '' && trim((string)($instancia ?? '')) !== '') {
            return;
        }

        try {
            $stmt = $pdo->query("SELECT * FROM config LIMIT 1");
            $conf = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : false;

            if (is_array($conf)) {
                if (trim((string)($api ?? '')) === '') {
                    $api = bb_confirmacao_pick_first($conf, array('api'));
                }
                if (trim((string)($token ?? '')) === '') {
                    $token = bb_confirmacao_pick_first($conf, array('token', 'appkey', 'AppKey', 'menuia_token', 'menuia_appkey'));
                }
                if (trim((string)($instancia ?? '')) === '') {
                    $instancia = bb_confirmacao_pick_first($conf, array('instancia', 'authkey', 'AuthKey', 'menuia_instancia', 'menuia_authkey'));
                }
            }
        } catch (Throwable $e) {
            @file_put_contents(__DIR__ . '/error_log', '[' . date('d-M-Y H:i:s') . '] confirmacao config error: ' . $e->getMessage() . "\n", FILE_APPEND);
        }
    }
}

if (!function_exists('bb_confirmacao_digits')) {
    function bb_confirmacao_digits($telefone) {
        $raw = trim((string)$telefone);
        $digits = preg_replace('/\D+/', '', $raw);
        $digits = trim((string)$digits);

        if ($digits === '') {
            return '';
        }

        $temPrefixoInternacional = (bool)preg_match('/^\s*\+/', $raw) || (bool)preg_match('/^\s*00/', $raw);

        if (strpos($digits, '00') === 0) {
            $digits = substr($digits, 2);
        }

        if (!$temPrefixoInternacional && (strlen($digits) === 10 || strlen($digits) === 11)) {
            $digits = '55' . $digits;
        }

        return $digits;
    }
}

bb_confirmacao_fill_config();

$api = strtolower(trim((string)($api ?? '')));
if ($api === '') {
    $api = 'menuia';
}

$token = trim((string)($token ?? ''));
$instancia = trim((string)($instancia ?? ''));
$telefone = trim((string)($telefone ?? ''));
$mensagem = str_replace("%0A", "\n", (string)($mensagem ?? ''));

$id = null;

if ($telefone === '' || $mensagem === '' || $token === '' || $instancia === '') {
    return;
}

if ($api === 'menuia') {

    $antAgendamento = (int)($antAgendamento ?? 0);
    if (!empty($data_envio) && $antAgendamento > 0) {
        try {
            $data_mensagem_obj = new DateTime((string)$data_envio);
            $data_mensagem_obj->modify("-{$antAgendamento} hours");
            $data_envio = $data_mensagem_obj->format('Y-m-d H:i:s');
        } catch (Throwable $e) {
        }
    }

    $telefoneDigits = bb_confirmacao_digits($telefone);
    if ($telefoneDigits === '') {
        return;
    }

    $payloadArr = array(
        'appkey'      => $token,
        'authkey'     => $instancia,
        'to'          => '+' . $telefoneDigits,
        'message'     => $mensagem,
        'licence'     => 'hugocursos',
        'agendamento' => (string)($data_envio ?? ''),
    );

    $payload = json_encode($payloadArr, JSON_UNESCAPED_UNICODE);

    if ($payload === false) {
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
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => array(
            'Content-Type: application/json',
            'Accept: application/json',
        ),
    ));

    $response = curl_exec($curl);
    $errno = curl_errno($curl);
    $err = curl_error($curl);
    $http = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($response === false || $errno != 0 || $http < 200 || $http >= 300) {
        @file_put_contents(
            __DIR__ . '/error_log',
            '[' . date('d-M-Y H:i:s') . "] Menuia Falha(confirmacao) | HTTP={$http} | errno={$errno} | err={$err} | to=+{$telefoneDigits} | resp=" . substr((string)$response, 0, 300) . "\n",
            FILE_APPEND
        );
        $id = null;
    } else {
        $decoded = json_decode((string)$response, true);
        $id = $decoded['id'] ?? null;
    }

} else {

    $telefoneDigits = bb_confirmacao_digits($telefone);
    if ($telefoneDigits === '') {
        return;
    }

    $payloadArr = array(
        'instance'      => $instancia,
        'token'         => $token,
        'to'            => $telefoneDigits,
        'message'       => $mensagem,
        'msg_erro'      => 'Desculpe, responda apenas com 1 ou 2 Muito Obrigado!!!',
        'msg_confirma'  => 'Confirmado ✅',
        'msg_reagendar' => 'Cancelado, Reagende pelo Site',
        'id_consulta'   => (string)($id_envio ?? ''),
        'url_recebe'    => (string)($url_sistema ?? '') . 'ajax/retorno.php',
        'data'          => (string)($data_envio ?? ''),
        'aviso'         => (string)($horas_confirmacaoF ?? ''),
    );

    $payload = json_encode($payloadArr, JSON_UNESCAPED_UNICODE);

    if ($payload === false) {
        return;
    }

    $curl = curl_init();

    curl_setopt_array($curl, array(
        CURLOPT_URL            => 'https://api.enviame.com.br/whatsapp/agendar/texto',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => array(
            'Content-Type: application/json',
            'Accept: application/json',
        ),
    ));

    $response = curl_exec($curl);
    $errno = curl_errno($curl);
    $err = curl_error($curl);
    $http = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($response === false || $errno != 0 || $http < 200 || $http >= 300) {
        @file_put_contents(
            __DIR__ . '/error_log',
            '[' . date('d-M-Y H:i:s') . "] Enviame Falha(confirmacao) | HTTP={$http} | errno={$errno} | err={$err} | to={$telefoneDigits} | resp=" . substr((string)$response, 0, 300) . "\n",
            FILE_APPEND
        );
        $id = null;
    } else {
        $responseObj = json_decode((string)$response, false);
        $id = $responseObj->id ?? ($responseObj->codigo ?? null);
    }
}
?>