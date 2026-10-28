<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';

header('Content-Type: application/json; charset=UTF-8');

if (@$_SESSION['id'] == "" || @$_SESSION['aut_token_1010'] != "fdsfdsafda8855555") {
  echo json_encode(['status' => 'error', 'msg' => 'Sessão expirada'], JSON_UNESCAPED_UNICODE);
  exit;
}

$tabela = 'avaliacoes_site';

$place_id = trim((string)($_POST['place_id'] ?? ''));
$api_key  = trim((string)($_POST['api_key'] ?? ''));
$debug    = isset($_GET['debug']) ? 1 : 0;

if ($place_id === '' && function_exists('getVal')) {
  try { $place_id = trim((string)getVal($pdo, 'google_place_id')); } catch (Throwable $e) { $place_id = ''; }
}
if ($api_key === '' && function_exists('getVal')) {
  try { $api_key = trim((string)getVal($pdo, 'google_api_key')); } catch (Throwable $e) { $api_key = ''; }
}

if ($place_id === '' || $api_key === '') {
  echo json_encode(['status' => 'error', 'msg' => 'Place ID e API Key são obrigatórios'], JSON_UNESCAPED_UNICODE);
  exit;
}

if (preg_match('/^AIza[0-9A-Za-z_\-]{10,}$/', $place_id)) {
  echo json_encode(['status' => 'error', 'msg' => 'O campo Place ID recebeu uma API Key. Cole o Place ID do estabelecimento.'], JSON_UNESCAPED_UNICODE);
  exit;
}

if ($place_id === $api_key || preg_match('/^ChIJ/i', $api_key)) {
  echo json_encode(['status' => 'error', 'msg' => 'O campo API Key recebeu um Place ID. Cole a chave criada no Google Cloud.'], JSON_UNESCAPED_UNICODE);
  exit;
}

if (!function_exists('curl_init')) {
  echo json_encode(['status' => 'error', 'msg' => 'cURL não está habilitado no servidor.'], JSON_UNESCAPED_UNICODE);
  exit;
}

function bb_google_json_erro(string $status, string $erro = '', int $http = 0): string {
  $msg = 'Falha na API do Google';
  if ($status !== '') $msg .= ' (' . $status . ')';
  if ($http > 0) $msg .= ' [HTTP ' . $http . ']';
  if ($erro !== '') $msg .= ': ' . $erro;

  $texto = strtolower($status . ' ' . $erro);
  if (strpos($texto, 'request_denied') !== false || strpos($texto, 'not authorized') !== false || strpos($texto, 'api key') !== false) {
    $msg .= ' Verifique se a API Key é real, se a Places API está ativada no projeto Google Cloud, se o faturamento está ativo e se as restrições da chave permitem essa API.';
  }
  if (strpos($texto, 'billing') !== false) {
    $msg .= ' Ative o faturamento no projeto Google Cloud desta API Key.';
  }

  return $msg;
}


function bb_google_safe_set($pdo, string $k, $v): void {
  if (!function_exists('setVal')) return;
  try {
    setVal($pdo, $k, (string)$v);
  } catch (Throwable $e) {
    error_log('avaliacoes/importar_google.php config_site erro: ' . $e->getMessage());
  }
}

function bb_google_salvar_config_basica($pdo, string $place_id, string $api_key): void {
  bb_google_safe_set($pdo, 'google_place_id', $place_id);
  bb_google_safe_set($pdo, 'google_api_key', $api_key);
}

function bb_google_salvar_resumo($pdo, array $result): void {
  $rating = (float)($result['rating_google'] ?? 0);
  $total = (int)($result['total_google'] ?? 0);
  $placeName = trim((string)($result['place_name'] ?? ''));
  $mapsUrl = trim((string)($result['maps_url'] ?? ''));
  $address = trim((string)($result['formatted_address'] ?? ''));

  if ($rating > 0) bb_google_safe_set($pdo, 'google_rating', number_format($rating, 1, '.', ''));
  if ($total > 0) bb_google_safe_set($pdo, 'google_user_ratings_total', (string)$total);
  if ($placeName !== '') bb_google_safe_set($pdo, 'google_place_name', $placeName);
  if ($mapsUrl !== '') bb_google_safe_set($pdo, 'google_maps_url', $mapsUrl);
  if ($address !== '') bb_google_safe_set($pdo, 'google_formatted_address', $address);
  bb_google_safe_set($pdo, 'google_last_sync_at', date('Y-m-d H:i:s'));
}

function bb_http_get_json(string $url, array $headers = []): array {
  $ch = curl_init($url);
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FAILONERROR    => false,
    CURLOPT_CONNECTTIMEOUT => 10,
    CURLOPT_TIMEOUT        => 25,
    CURLOPT_USERAGENT      => 'BarberBot-Avaliacoes/1.2',
    CURLOPT_SSL_VERIFYPEER => true,
    CURLOPT_SSL_VERIFYHOST => 2,
  ]);
  if (!empty($headers)) {
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
  }

  $resp = curl_exec($ch);
  $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  $errno = curl_errno($ch);
  $err = curl_error($ch);
  curl_close($ch);

  if ($errno !== 0) {
    return ['ok' => false, 'http' => $http, 'status' => '', 'error' => 'cURL ' . $errno . ': ' . $err, 'json' => null, 'body' => $resp];
  }
  if ($resp === false || $resp === '') {
    return ['ok' => false, 'http' => $http, 'status' => '', 'error' => 'Resposta vazia do Google', 'json' => null, 'body' => $resp];
  }

  $json = json_decode((string)$resp, true);
  if (!is_array($json)) {
    return ['ok' => false, 'http' => $http, 'status' => '', 'error' => 'Resposta JSON inválida do Google', 'json' => null, 'body' => $resp];
  }

  return ['ok' => ($http >= 200 && $http < 300), 'http' => $http, 'status' => (string)($json['status'] ?? ''), 'error' => (string)($json['error_message'] ?? ($json['error']['message'] ?? '')), 'json' => $json, 'body' => $resp];
}

function bb_normalizar_reviews_legacy(array $json): array {
  $reviews = $json['result']['reviews'] ?? [];
  if (!is_array($reviews)) $reviews = [];

  $out = [];
  foreach ($reviews as $r) {
    if (!is_array($r)) continue;
    $out[] = [
      'nome' => trim((string)($r['author_name'] ?? 'Anônimo')),
      'nota' => (int)($r['rating'] ?? 5),
      'comentario' => trim((string)($r['text'] ?? '')),
      'foto' => trim((string)($r['profile_photo_url'] ?? '')),
      'tempo' => isset($r['time']) ? (int)$r['time'] : time(),
      'externo_id' => 'google_legacy_' . md5((string)($r['author_name'] ?? '') . '|' . (string)($r['time'] ?? '') . '|' . (string)($r['text'] ?? '')),
    ];
  }

  return $out;
}

function bb_normalizar_reviews_new(array $json): array {
  $reviews = $json['reviews'] ?? [];
  if (!is_array($reviews)) $reviews = [];

  $out = [];
  foreach ($reviews as $r) {
    if (!is_array($r)) continue;

    $autor = $r['authorAttribution']['displayName'] ?? 'Anônimo';
    $foto = $r['authorAttribution']['photoUri'] ?? '';
    $texto = $r['text']['text'] ?? ($r['originalText']['text'] ?? '');
    $publish = (string)($r['publishTime'] ?? '');
    $ts = $publish !== '' ? strtotime($publish) : false;
    if (!$ts) $ts = time();

    $out[] = [
      'nome' => trim((string)$autor),
      'nota' => (int)($r['rating'] ?? 5),
      'comentario' => trim((string)$texto),
      'foto' => trim((string)$foto),
      'tempo' => (int)$ts,
      'externo_id' => 'google_new_' . md5((string)($r['name'] ?? '') . '|' . $autor . '|' . $publish . '|' . $texto),
    ];
  }

  return $out;
}

function bb_fetch_google_reviews(string $place_id, string $api_key): array {
  $tentativas = [];

  // Places API Web Service legado.
  $paramsLegacy = http_build_query([
    'place_id' => $place_id,
    'fields' => 'reviews,rating,user_ratings_total,name,url,formatted_address',
    'language' => 'pt-BR',
    'reviews_no_translations' => 'true',
    'reviews_sort' => 'newest',
    'key' => $api_key,
  ]);
  $legacy = bb_http_get_json('https://maps.googleapis.com/maps/api/place/details/json?' . $paramsLegacy);
  $tentativas[] = ['tipo' => 'legacy', 'retorno' => $legacy];

  if ($legacy['ok'] && is_array($legacy['json']) && (($legacy['json']['status'] ?? '') === 'OK')) {
    $reviews = bb_normalizar_reviews_legacy($legacy['json']);
    return [
      'ok' => true,
      'tipo' => 'legacy',
      'reviews' => $reviews,
      'total_google' => (int)($legacy['json']['result']['user_ratings_total'] ?? 0),
      'rating_google' => (float)($legacy['json']['result']['rating'] ?? 0),
      'place_name' => (string)($legacy['json']['result']['name'] ?? ''),
      'maps_url' => (string)($legacy['json']['result']['url'] ?? ''),
      'formatted_address' => (string)($legacy['json']['result']['formatted_address'] ?? ''),
      'tentativas' => $tentativas,
    ];
  }

  // Places API (New) v1.
  $urlNew = 'https://places.googleapis.com/v1/places/' . rawurlencode($place_id) . '?' . http_build_query(['languageCode' => 'pt-BR']);
  $new = bb_http_get_json($urlNew, [
    'X-Goog-Api-Key: ' . $api_key,
    'X-Goog-FieldMask: id,displayName,formattedAddress,rating,userRatingCount,reviews,googleMapsUri',
  ]);
  $tentativas[] = ['tipo' => 'new', 'retorno' => $new];

  if ($new['ok'] && is_array($new['json']) && empty($new['json']['error'])) {
    $reviews = bb_normalizar_reviews_new($new['json']);
    return [
      'ok' => true,
      'tipo' => 'new',
      'reviews' => $reviews,
      'total_google' => (int)($new['json']['userRatingCount'] ?? 0),
      'rating_google' => (float)($new['json']['rating'] ?? 0),
      'place_name' => (string)($new['json']['displayName']['text'] ?? ''),
      'maps_url' => (string)($new['json']['googleMapsUri'] ?? ''),
      'formatted_address' => (string)($new['json']['formattedAddress'] ?? ''),
      'tentativas' => $tentativas,
    ];
  }

  $ultimo = $new;
  if (!empty($legacy['status']) || !empty($legacy['error'])) {
    $ultimo = $legacy;
  }

  return [
    'ok' => false,
    'msg' => bb_google_json_erro((string)($ultimo['status'] ?? ''), (string)($ultimo['error'] ?? ''), (int)($ultimo['http'] ?? 0)),
    'tentativas' => $tentativas,
  ];
}

bb_google_salvar_config_basica($pdo, $place_id, $api_key);
$result = bb_fetch_google_reviews($place_id, $api_key);

if ($debug) {
  echo json_encode($result, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
  exit;
}

if (!$result['ok']) {
  echo json_encode(['status' => 'error', 'msg' => ($result['msg'] ?? 'Falha ao consultar Google Places')], JSON_UNESCAPED_UNICODE);
  exit;
}

$reviews = $result['reviews'] ?? [];
$totalGoogle = (int)($result['total_google'] ?? 0);

if (empty($reviews)) {
  bb_google_salvar_resumo($pdo, is_array($result) ? $result : []);

  $ratingGoogle = (float)($result['rating_google'] ?? 0);
  $placeNameGoogle = trim((string)($result['place_name'] ?? ''));
  $addressGoogle = trim((string)($result['formatted_address'] ?? ''));
  $mapsUrlGoogle = trim((string)($result['maps_url'] ?? ''));

  $partes = [];
  if ($placeNameGoogle !== '') $partes[] = 'local "' . $placeNameGoogle . '"';
  if ($addressGoogle !== '') $partes[] = 'endereço salvo';
  if ($ratingGoogle > 0) $partes[] = 'nota média ' . number_format($ratingGoogle, 1, ',', '');
  if ($totalGoogle > 0) $partes[] = $totalGoogle . ' avaliação(ões)';
  if ($mapsUrlGoogle !== '') $partes[] = 'link do Google Maps salvo';

  if (!empty($partes)) {
    $msg = 'Dados do Google atualizados: ' . implode(', ', $partes) . '. Nenhum comentário individual foi retornado pela API para importar nesta consulta.';
    echo json_encode(['status' => 'success', 'msg' => $msg], JSON_UNESCAPED_UNICODE);
    exit;
  }

  echo json_encode(['status' => 'error', 'msg' => 'O Place ID foi consultado, mas o Google não retornou nome, nota, total ou comentários para este local.'], JSON_UNESCAPED_UNICODE);
  exit;
}

$importadas = 0;
$duplicadas = 0;

try {
  bb_google_salvar_resumo($pdo, is_array($result) ? $result : []);

  $pdo->beginTransaction();

  $sqlCheck = $pdo->prepare("SELECT id FROM {$tabela} WHERE externo_id = :externo_id LIMIT 1");
  $sqlIns = $pdo->prepare("
    INSERT INTO {$tabela}
      (nome, nota, comentario, origem, data_avaliacao, foto, status, externo_id)
    VALUES
      (:nome, :nota, :comentario, 'Google', :data_avaliacao, :foto, 1, :externo_id)
  ");

  foreach ($reviews as $r) {
    $nome = trim((string)($r['nome'] ?? 'Anônimo'));
    if ($nome === '') $nome = 'Anônimo';

    $nota = (int)($r['nota'] ?? 5);
    if ($nota < 1) $nota = 1;
    if ($nota > 5) $nota = 5;

    $comentario = trim((string)($r['comentario'] ?? ''));
    $foto = trim((string)($r['foto'] ?? ''));
    if ($foto !== '' && !filter_var($foto, FILTER_VALIDATE_URL)) {
      $foto = '';
    }

    $tempo = (int)($r['tempo'] ?? time());
    if ($tempo <= 0) $tempo = time();
    $data_avaliacao = date('Y-m-d', $tempo);

    $externo_id = trim((string)($r['externo_id'] ?? ''));
    if ($externo_id === '') {
      $externo_id = 'google_' . md5($nome . '|' . $data_avaliacao . '|' . $comentario);
    }

    $sqlCheck->execute([':externo_id' => $externo_id]);
    if ($sqlCheck->fetchColumn()) {
      $duplicadas++;
      continue;
    }

    $sqlIns->execute([
      ':nome' => $nome,
      ':nota' => $nota,
      ':comentario' => ($comentario !== '' ? $comentario : null),
      ':data_avaliacao' => $data_avaliacao,
      ':foto' => ($foto !== '' ? $foto : null),
      ':externo_id' => $externo_id,
    ]);

    $importadas++;
  }

  $pdo->commit();

  $msg = 'Importação concluída! ' . $importadas . ' nova(s) avaliação(ões).';
  if ($duplicadas > 0) {
    $msg .= ' ' . $duplicadas . ' já existiam.';
  }

  echo json_encode(['status' => 'success', 'msg' => $msg], JSON_UNESCAPED_UNICODE);
  exit;

} catch (Throwable $e) {
  if ($pdo instanceof PDO && $pdo->inTransaction()) {
    $pdo->rollBack();
  }
  error_log('avaliacoes/importar_google.php erro: ' . $e->getMessage());
  echo json_encode(['status' => 'error', 'msg' => 'Erro ao salvar as avaliações no banco.'], JSON_UNESCAPED_UNICODE);
  exit;
}
