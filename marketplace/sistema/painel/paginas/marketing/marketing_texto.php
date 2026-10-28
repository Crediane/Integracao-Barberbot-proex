<?php
require_once __DIR__ . '/../_context_bootstrap.php';
// Guarda slug na sessão (ajuda includes/conexão a escolher o banco da loja)
if (!empty($__bb_reqSlug) && empty($_SESSION['loja_slug'])) {
  $_SESSION['loja_slug'] = $__bb_reqSlug;
}

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
      'appkey' =>  $token,
      'authkey' => $instancia,
      'licence' => 'hugocursos',
      'to' => $numeros_formatados,
      'message' => $mensagem,
      'agendamento' => date('Y-m-d H:i:s'),
      ),
    ));
    
    $response = curl_exec($curl);
    curl_close($curl);
    
    //echo $response;
    
    //Caso queira pausar o envio, vc pode pegar a hash que está retornando.
    $res_hash = json_decode($response, true);
    $hash = $res_hash['id'];

?>