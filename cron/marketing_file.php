<?php

$status_mensagem = '';

if ($api == 'menuia') {

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
      'appkey' => $token,
      'authkey' => $instancia,
      'to' => $telefone,
      'message' => $mensagem,
      'file' => $url_envio,
      'sandbox' => 'false'
    ),
  ));

  $response = curl_exec($curl);

  if ($response === false) {
    $status_mensagem = 'Falha no CURL: ' . curl_error($curl);
    curl_close($curl);
  } else {
    curl_close($curl);
    $response_decoded = json_decode($response, true);
    if (is_array($response_decoded)) {
      $status_mensagem = (string)($response_decoded['message'] ?? ($response_decoded['status'] ?? ''));
      if ($status_mensagem === '' && array_key_exists('erro', $response_decoded) && $response_decoded['erro'] === false) {
        $status_mensagem = 'Mensagem enviada com sucesso.';
      }
    } else {
      $status_mensagem = 'Resposta inválida da Menuia';
    }
  }

} else {
  $mensagem = '';
  $data = array(
    'instance' => "$instancia",
    'to' => "$telefone",
    'token' => "$token",
    'message' => "$mensagem",
    'url' => "$url_envio"
  );

  $curl = curl_init();

  curl_setopt_array($curl, array(
    CURLOPT_URL => 'http://api.wordmensagens.com.br/send-docnew',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => $data,
  ));

  $response = curl_exec($curl);

  if ($response === false) {
    $status_mensagem = 'Falha no CURL: ' . curl_error($curl);
    curl_close($curl);
  } else {
    curl_close($curl);
    $response = json_decode($response, true);

    if (is_array($response)) {
      if (isset($response['erro']) && $response['erro'] == false) {
        $status_mensagem = 'Mensagem enviada com sucesso.';
      } else if (isset($response['erro']) && $response['erro'] == true) {
        $status_mensagem = (string)($response['message'] ?? 'Erro no envio');
      } else {
        $status_mensagem = 'Resposta inválida da API';
      }
    } else {
      $status_mensagem = 'Resposta inválida da API';
    }
  }
}

?>
