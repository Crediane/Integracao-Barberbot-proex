<?php

if($api == 'menuia'){
   $mensagem = str_replace("%0A", "\n", $mensagem); 
   
   $curl = curl_init();

  curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://chatbot.menuia.com/api/create-message',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => array(
    'appkey' => $token,
    'authkey' => $instancia,
    'to' => $telefone,
    'message' => $mensagem,
    'licence' => 'hugocursos',
    'agendamento' => $data_mensagem,
    'file' => '',
    'nomearquivo' => '',
    ),
  ));

  $response = curl_exec($curl);

  curl_close($curl);
  //echo $response;

  $responseData = json_decode($response, true);
  $hash = @$responseData['id'];  
  
}else{

  $curl = curl_init();

  curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://api.enviame.com.br/whatsapp/agendar/texto',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_TIMEOUT => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS =>'{
      "instancia": "'.$instancia.'",
      "token": "'.$token.'",
      "para": "'.$telefone.'",
      "mensagem": "'.$mensagem.'",
      "data": "'.$data_mensagem.'"
  }',
    CURLOPT_HTTPHEADER => array(
      'Content-Type: application/json'
    ),
  ));

  $response = curl_exec($curl);

  curl_close($curl);


    $res = json_decode($response, true);
    $hash = @$res['codigo'];

  //echo $result;
}
 ?>