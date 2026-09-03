<?php
/**
 * /public_html/pagamentos/confirmacao.php
 * Envia a mensagem de confirmação/aviso do agendamento.
 *
 * ✅ Patch profissional (Marketplace-safe) SEM mudar a lógica do BarberBot:
 * - NÃO mexe em sessão (este arquivo é incluído por outros scripts).
 * - Normaliza telefone com segurança (evita falha na API e evita "5555...").
 * - Enviame: monta JSON com json_encode (não quebra quando a mensagem tem aspas/quebras de linha).
 * - Menuia: mantém endpoint oficial /api/create-message, com payload JSON estável.
 *
 * Variáveis esperadas (já usadas no sistema):
 *  - $api
 *  - $instancia  (AuthKey no Menuia / Instance no Enviame)
 *  - $token      (AppKey no Menuia)
 *  - $telefone
 *  - $mensagem
 *  - $data_envio
 *  - $antAgendamento
 *  - $horas_confirmacaoF
 *  - $id_envio
 *  - $url_sistema
 */

// -----------------------------------------------------------
// Helpers
// -----------------------------------------------------------
if (!function_exists('bb_conf_log')) {
  function bb_conf_log(string $msg): void {
    @file_put_contents(__DIR__ . '/error_log', '[' . date('d-M-Y H:i:s') . '] ' . $msg . "\n", FILE_APPEND);
    @error_log('BarberBot Confirmacao: ' . $msg);
  }
}

if (!function_exists('bb_digits')) {
  function bb_digits($v): string {
    return preg_replace('/\D+/', '', (string)$v);
  }
}

if (!function_exists('bb_tel55')) {
  /**
   * Retorna apenas dígitos do telefone.
   * Se tiver 10/11 dígitos (padrão BR sem DDI), prefixa 55.
   * Para outros tamanhos, mantém como está (evita "chute" fora do BR).
   */
  function bb_tel55($v): string {
    $digits = bb_digits($v);
    $digits = trim((string)$digits);
    if ($digits === '') return '';

    if (strlen($digits) === 10 || strlen($digits) === 11) {
      $digits = '55' . $digits;
    }

    return $digits;
  }
}

if (!function_exists('bb_menuia_to')) {
  /** Formato exigido no Menuia: +<ddi><numero> (somente dígitos) */
  function bb_menuia_to($telefone): string {
    $digits = bb_tel55($telefone);
    if ($digits === '') return '';
    return '+' . $digits;
  }
}

// -----------------------------------------------------------
// Entradas (mantém padrão do sistema)
// -----------------------------------------------------------
$api       = strtolower(trim((string)($api ?? '')));
$instancia = (string)($instancia ?? '');
$token     = (string)($token ?? '');
$telefone  = (string)($telefone ?? '');
$mensagem  = (string)($mensagem ?? '');

// Mensagem pode vir com %0A (quebra de linha)
$mensagem = str_replace("%0A", "\n", $mensagem);

// Ajuste de horário (mantém regra existente)
$antAgendamento = (int)($antAgendamento ?? 0);
if (!empty($data_envio) && $antAgendamento > 0) {
  try {
    $data_mensagem_obj = new DateTime((string)$data_envio);
    $data_mensagem_obj->modify("-{$antAgendamento} hours");
    $data_envio = $data_mensagem_obj->format('Y-m-d H:i:s');
  } catch (Throwable $e) {
    // não quebra o fluxo
  }
}

// -----------------------------------------------------------
// MENU IA
// -----------------------------------------------------------
if ($api === 'menuia') {

  $to = bb_menuia_to($telefone);
  if ($to === '') {
    bb_conf_log("Menuia Falha(confirmacao) | telefone inválido: raw=" . substr((string)$telefone, 0, 40));
    $id = null;
    return;
  }

  if (trim($token) === '' || trim($instancia) === '') {
    bb_conf_log("Menuia Falha(confirmacao) | appkey/authkey vazio | to={$to}");
    $id = null;
    return;
  }

  $payloadArr = array(
    'appkey'      => $token,
    'authkey'     => $instancia,
    'to'          => $to,
    'message'     => $mensagem,
    'licence'     => 'hugocursos',
    'agendamento' => (string)($data_envio ?? ''),
  );

  $payload = json_encode($payloadArr, JSON_UNESCAPED_UNICODE);

  if ($payload === false) {
    bb_conf_log("Menuia Falha(confirmacao) | json_encode payload | to={$to}");
    $id = null;
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
      'Accept: application/json'
    ),
  ));

  $response = curl_exec($curl);
  $errno = curl_errno($curl);
  $err = curl_error($curl);
  $http = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
  curl_close($curl);

  if ($response === false || $errno != 0 || $http < 200 || $http >= 300) {
    bb_conf_log("Menuia Falha(confirmacao) | HTTP={$http} | errno={$errno} | err={$err} | to={$to} | resp=" . substr((string)$response, 0, 300));
    $id = null;
  } else {
    $decoded = json_decode((string)$response, true);
    $id = $decoded['id'] ?? null;
  }

  return;
}

// -----------------------------------------------------------
// ENVIAME (mantém padrão do sistema)
// -----------------------------------------------------------
$toDigits = bb_tel55($telefone);
if ($toDigits === '') {
  bb_conf_log("Enviame Falha(confirmacao) | telefone inválido: raw=" . substr((string)$telefone, 0, 40));
  $id = null;
  return;
}

// url de retorno (garante barra)
$url_sistema = (string)($url_sistema ?? '');
$url_recebe  = rtrim($url_sistema, '/') . '/ajax/retorno.php';

$payloadArr = array(
  'instance'     => (string)($instancia ?? ''),
  'to'           => (string)$toDigits,
  'message'      => (string)$mensagem,
  'msg_erro'     => "Desculpe, responda apenas com 1 ou 2 Muito Obrigado!!!",
  'msg_confirma' => "Confirmado ✅",
  'msg_reagendar'=> "Cancelado, Reagende pelo Site",
  'id_consulta'  => (string)($id_envio ?? ''),
  'url_recebe'   => (string)$url_recebe,
  'data'         => (string)($data_envio ?? ''),
  'aviso'        => (string)($horas_confirmacaoF ?? ''),
);

$payload = json_encode($payloadArr, JSON_UNESCAPED_UNICODE);

if ($payload === false) {
  bb_conf_log("Enviame Falha(confirmacao) | json_encode payload | to={$toDigits}");
  $id = null;
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
  bb_conf_log("Enviame Falha(confirmacao) | HTTP={$http} | errno={$errno} | err={$err} | to={$toDigits} | resp=" . substr((string)$response, 0, 300));
  $id = null;
  return;
}

// pegando o id (mantém comportamento)
$decoded = json_decode((string)$response);
$id = $decoded->id ?? null;

return;
?>
