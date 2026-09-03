<?php
$bb_ajax_context_mode = 'tenant';
$bb_ajax_context_response = 'plain';
require_once __DIR__ . '/_context_bootstrap.php';

$api = isset($api) ? strtolower(trim((string)$api)) : '';
$token = isset($token) ? trim((string)$token) : '';
$instancia = isset($instancia) ? trim((string)$instancia) : '';
$response = '';
$result = null;

if (!function_exists('bb_listar_log')) {
    function bb_listar_log(string $msg): void
    {
        @file_put_contents(__DIR__ . '/error_log', '[' . date('d-M-Y H:i:s') . '] ' . $msg . PHP_EOL, FILE_APPEND);
        @error_log('BarberBot Listar Agendamentos: ' . $msg);
    }
}

if (!function_exists('bb_listar_db_name')) {
    function bb_listar_db_name($pdo): string
    {
        try {
            if ($pdo instanceof PDO) {
                $st = $pdo->query('SELECT DATABASE()');
                $db = $st ? $st->fetchColumn() : '';
                return $db ? (string)$db : '';
            }
        } catch (Throwable $e) {
        }
        return '';
    }
}

if (!function_exists('bb_listar_contexto')) {
    function bb_listar_contexto(): string
    {
        global $bb_ajax_context;
        return (string)($bb_ajax_context['type'] ?? 'tenant');
    }
}

if (!function_exists('bb_listar_fill_config')) {
    function bb_listar_fill_config(): void
    {
        global $pdo, $api, $token, $instancia;

        if (!($pdo instanceof PDO)) {
            return;
        }

        if ($api !== '' && $token !== '' && $instancia !== '') {
            return;
        }

        try {
            $st = $pdo->query('SELECT api, token, instancia FROM config LIMIT 1');
            $row = $st ? $st->fetch(PDO::FETCH_ASSOC) : false;

            if (is_array($row)) {
                if ($api === '' && isset($row['api'])) {
                    $api = strtolower(trim((string)$row['api']));
                }
                if ($token === '' && isset($row['token'])) {
                    $token = trim((string)$row['token']);
                }
                if ($instancia === '' && isset($row['instancia'])) {
                    $instancia = trim((string)$row['instancia']);
                }
            }
        } catch (Throwable $e) {
            bb_listar_log('Falha ao ler config do banco atual: ' . $e->getMessage());
        }
    }
}

bb_listar_fill_config();

if ($api === '') {
    $api = 'menuia';
}

$ctx = bb_listar_contexto();
$dbAtual = isset($pdo) && $pdo instanceof PDO ? bb_listar_db_name($pdo) : '';

if ($token === '' || $instancia === '') {
    bb_listar_log(
        'Credenciais ausentes | ctx=' . $ctx .
        ' | db=' . $dbAtual .
        ' | api=' . $api .
        ' | token=' . ($token !== '' ? 'ok' : 'vazio') .
        ' | instancia=' . ($instancia !== '' ? 'ok' : 'vazio')
    );

    echo '<pre>';
    print_r(array('ok' => false, 'mensagem' => 'Credenciais da API não encontradas para o contexto atual.'));
    echo '</pre>';
    exit;
}

if ($api === 'menuia') {
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
            'appkey'           => $token,
            'authkey'          => $instancia,
            'message'          => 'false',
            'licence'          => 'hugocursos',
            'listaAgendamento' => 'true',
        ),
    ));

    $response = curl_exec($curl);
    $errno = curl_errno($curl);
    $err = curl_error($curl);
    $http = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    if ($response === false || $errno != 0 || $http < 200 || $http >= 300) {
        bb_listar_log(
            'Menuia Falha | ctx=' . $ctx .
            ' | db=' . $dbAtual .
            ' | http=' . $http .
            ' | errno=' . $errno .
            ' | err=' . $err .
            ' | resp=' . substr((string)$response, 0, 300)
        );

        echo '<pre>';
        print_r(array('ok' => false, 'mensagem' => 'Falha ao listar agendamentos.', 'status' => $http));
        echo '</pre>';
        exit;
    }

    $result = json_decode((string)$response, true);

    echo '<pre>';
    if (is_array($result) && array_key_exists('message', $result)) {
        print_r($result['message']);
    } elseif (is_array($result)) {
        print_r($result);
    } else {
        print_r($response);
    }
    echo '</pre>';
    exit;
}

$url = 'http://api.wordmensagens.com.br/agendar-list';

$data = array(
    'instance' => $instancia,
    'token'    => $token,
);

$options = array(
    'http' => array(
        'method'        => 'POST',
        'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
        'content'       => http_build_query($data),
        'timeout'       => 30,
        'ignore_errors' => true,
    ),
);

$stream = stream_context_create($options);
$response = @file_get_contents($url, false, $stream);

if ($response === false) {
    bb_listar_log(
        'WordMensagens Falha | ctx=' . $ctx .
        ' | db=' . $dbAtual .
        ' | sem retorno da API'
    );

    echo '<pre>';
    print_r(array('ok' => false, 'mensagem' => 'Falha ao listar agendamentos.'));
    echo '</pre>';
    exit;
}

$result = json_decode((string)$response, true);

echo '<pre> ';
if (is_array($result)) {
    print_r($result);
} else {
    print_r($response);
}
echo '</pre> ';
exit;
?>
