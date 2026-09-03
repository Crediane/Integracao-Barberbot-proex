<?php
/**
 * Arquivo: consultar_pagamento.php
 * Consulta o status de um pagamento usando os tokens do contexto tenant já validado.
 * Este arquivo não inicia nem troca sessões.
 */

require_once __DIR__ . '/config.php';

// ===== Garantir variável de saída =====
$status_api = '';

// ===== Validar ref_pix recebido do chamador =====
$ref_pix_local = isset($ref_pix) ? (string)$ref_pix : '';
$ref_pix_local = trim($ref_pix_local);

// Mercado Pago geralmente usa ID numérico no endpoint /v1/payments/{id}
$ref_pix_local = preg_replace('/\D+/', '', $ref_pix_local);

if ($ref_pix_local === '') {
  // Sem ref_pix válido, não consulta nada (mantém status_api vazio)
  return;
}

// ===== Carregar token (padrão do sistema) =====
$tokens_file = __DIR__ . '/tokens.php';
if (!file_exists($tokens_file)) {
  // Sem token não consulta (mantém status_api vazio)
  return;
}

require_once $tokens_file;

// tokens.php deve definir $access_token
if (!isset($access_token) || trim((string)$access_token) === '') {
  // Sem access_token, não consulta (mantém status_api vazio)
  return;
}

$access_token_local = trim((string)$access_token);

// ===== Consulta Mercado Pago =====
$curl = curl_init();

curl_setopt_array($curl, array(
  CURLOPT_URL => 'https://api.mercadopago.com/v1/payments/' . $ref_pix_local,
  CURLOPT_RETURNTRANSFER => true,
  CURLOPT_CUSTOMREQUEST => 'GET',
  CURLOPT_HTTPHEADER => array(
    'accept: application/json',
    'content-type: application/json',
    'Authorization: Bearer ' . $access_token_local
  ),
  // Timeouts para não travar o painel em caso de instabilidade
  CURLOPT_CONNECTTIMEOUT => 10,
  CURLOPT_TIMEOUT => 20,
));

$response = curl_exec($curl);
$curl_err = curl_error($curl);
$http_code = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

// Se falhar a conexão, mantém status_api vazio
if ($response === false || $curl_err) {
  return;
}

// Decodificar JSON com segurança
$resultado = json_decode($response);

// Se não vier JSON válido, mantém status_api vazio
if (!is_object($resultado)) {
  return;
}

// Se a API retornar erro, status pode não existir
if (isset($resultado->status)) {
  $status_api = (string)$resultado->status;
}

// Mantém estrutura original (compatibilidade)
if ($status_api == 'approved') {
  // sem ação aqui; o arquivo chamador faz a baixa
}
?>
