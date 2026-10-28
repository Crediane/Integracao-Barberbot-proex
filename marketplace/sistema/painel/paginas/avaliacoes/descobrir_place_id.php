<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/config.php';
// ===== descobrir_place_id.php =====
// Descobre o Place ID a partir de um texto (nome + cidade/UF)

// Guarda de sessão LOCAL (mesma regra do seu verificar.php, sem redirecionar HTML)

require_once __DIR__ . '/../../../conexao.php'; // mantido por padrão do sistema (não é usado aqui, mas mantém padrão)

header('Content-Type: application/json; charset=UTF-8');

// Aceita somente POST
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    echo json_encode(['status' => 'error', 'msg' => 'Informe texto e API Key']); // mensagem já vista por você
    exit;
}

// Entrada
$texto  = trim($_POST['texto']  ?? '');
$apiKey = trim($_POST['api_key'] ?? '');

if ($texto === '' || $apiKey === '') {
    echo json_encode(['status' => 'error', 'msg' => 'Informe texto e API Key']);
    exit;
}

// Monta chamada do Find Place From Text
$params = http_build_query([
    'input'     => $texto,
    'inputtype' => 'textquery',
    'fields'    => 'place_id,name,formatted_address',
    'language'  => 'pt-BR',
    'key'       => $apiKey,
]);

$url = "https://maps.googleapis.com/maps/api/place/findplacefromtext/json?{$params}";

// Requisição cURL
$ch = curl_init($url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 20,
    CURLOPT_FAILONERROR    => false,
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
    CURLOPT_USERAGENT      => 'Painel-DescobrirPlaceID/1.0',
]);
$response  = curl_exec($ch);
$http_code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
$err_no    = curl_errno($ch);
$err_msg   = curl_error($ch);
curl_close($ch);

// Erros de rede/HTTP
if ($err_no !== 0) {
    echo json_encode(['status' => 'error', 'msg' => "Erro de rede cURL: {$err_msg}"]);
    exit;
}
if ($http_code !== 200 || !$response) {
    echo json_encode(['status' => 'error', 'msg' => "Falha HTTP ({$http_code}) ao consultar Find Place"]);
    exit;
}

// Parse
$data = json_decode($response, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    echo json_encode(['status' => 'error', 'msg' => 'Resposta inválida do Google']);
    exit;
}

// Trata status da API
$apiStatus = $data['status'] ?? 'UNKNOWN';
if ($apiStatus !== 'OK') {
    $err = $data['error_message'] ?? 'Falha ao consultar Find Place';
    echo json_encode(['status' => 'error', 'msg' => "Google Places retornou {$apiStatus}: {$err}"]);
    exit;
}

// Sem candidatos
if (empty($data['candidates']) || !is_array($data['candidates'])) {
    echo json_encode(['status' => 'error', 'msg' => 'Nenhum candidato encontrado para o texto informado']);
    exit;
}

// Pega o primeiro candidato
$c = $data['candidates'][0];
$place_id = $c['place_id'] ?? null;
$name     = $c['name'] ?? null;
$address  = $c['formatted_address'] ?? null;

if (!$place_id) {
    echo json_encode(['status' => 'error', 'msg' => 'Nenhum Place ID retornado para o texto informado']);
    exit;
}

// Sucesso
echo json_encode([
    'status'   => 'success',
    'place_id' => $place_id,
    'name'     => $name,
    'address'  => $address,
    // opcionalmente devolvemos todos os candidates (útil para checar ambiguidades):
    'candidates' => $data['candidates'],
], JSON_UNESCAPED_UNICODE);
exit;