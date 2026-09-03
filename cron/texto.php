<?php
// /public_html/cron/texto.php
// Define $status_mensagem (usado pelos crons para decidir se marca alerta)

$status_mensagem = '';

if (isset($api) && $api == "menuia") {

    $mensagem = str_replace("%0A", "\n", (string)$mensagem);

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://chatbot.menuia.com/api/create-message',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array(
            'appkey'   => $token,
            'authkey'  => $instancia,
            'to'       => $telefone,
            'licence'  => 'hugocursos',
            'message'  => $mensagem,
        ),
    ));

    $raw = curl_exec($curl);

    if ($raw === false) {
        $status_mensagem = 'Falha no CURL: ' . curl_error($curl);
        curl_close($curl);
    } else {
        curl_close($curl);

        $response = json_decode($raw, true);
        if (is_array($response)) {
            $status_mensagem = (string)($response['message'] ?? ($response['status'] ?? ''));
            if ($status_mensagem === '' && isset($response['erro']) && $response['erro'] === false) {
                $status_mensagem = 'Mensagem enviada com sucesso.';
            }
        } else {
            $status_mensagem = 'Resposta inválida da Menuia';
        }
    }

} else {

    $url = "https://api.enviame.com.br/whatsapp/enviar/texto";
    $data = array(
        'instance' => $instancia,
        'to'       => $telefone,
        'token'    => $token,
        'message'  => $mensagem
    );

    $options = array('http' => array(
        'method'  => 'POST',
        'content' => http_build_query($data),
        'timeout' => 30,
    ));

    $stream = stream_context_create($options);
    $result = @file_get_contents($url, false, $stream);

    if ($result === false) {
        $status_mensagem = 'Falha ao enviar (enviame)';
    } else {
        $decoded = json_decode($result, true);
        if (is_array($decoded) && isset($decoded['erro']) && $decoded['erro'] == false) {
            $status_mensagem = 'Mensagem enviada com sucesso.';
        } else if (is_array($decoded) && isset($decoded['message'])) {
            $status_mensagem = (string)$decoded['message'];
        } else {
            $status_mensagem = 'OK';
        }
    }
}
