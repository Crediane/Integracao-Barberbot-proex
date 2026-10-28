<?php
/**
 * Arquivo: /public_html/ajax/agendar-delete.php
 * Objetivo: cancelar um agendamento pendente (hash/id) na API.
 *
 * Correção focada no roteamento Marketplace:
 * - /loja/{slug}/ usa exclusivamente o banco do TENANT daquela loja.
 * - /sistema/ usa exclusivamente o banco MASTER e não herda slug por referer.
 * - Mantém o comportamento antigo: não imprime nada quando incluído.
 * - Quando chamado direto, também aceita GET/POST: hash, id_hash ou id.
 */

$__bb_is_standalone = (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? ''));

if (!function_exists('bb_agd_del_clean_slug')) {
    function bb_agd_del_clean_slug($v) {
        $v = strtolower(trim((string)$v));
        return preg_replace('/[^a-z0-9_-]/', '', $v);
    }
}

if (!function_exists('bb_agd_del_pick_first')) {
    function bb_agd_del_pick_first(array $arr, array $keys) {
        foreach ($keys as $k) {
            if (isset($arr[$k]) && trim((string)$arr[$k]) !== '') {
                return (string)$arr[$k];
            }
        }
        return '';
    }
}

if (!function_exists('bb_agd_del_json_out')) {
    function bb_agd_del_json_out(array $out, bool $standalone) {
        if ($standalone) {
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode($out, JSON_UNESCAPED_UNICODE);
        }
    }
}

$bb_ajax_context_mode = 'any';
$bb_ajax_context_response = $__bb_is_standalone ? 'json' : 'plain';
require_once __DIR__ . '/_context_bootstrap.php';

$__bb_slug = (string)$bb_ajax_context['slug'];
$__bb_forceMaster = ($bb_ajax_context['type'] === 'master');
$pdo = $GLOBALS['pdo'] ?? ($pdo ?? null);

/*
|--------------------------------------------------------------------------
| 2) Lê hash/id do cancelamento
|--------------------------------------------------------------------------
*/
$hash_in = '';
if (isset($hash)) {
    $hash_in = (string)$hash;
}
if ($hash_in === '' && isset($id_hash)) {
    $hash_in = (string)$id_hash;
}
if ($hash_in === '') {
    $hash_in = (string)($_POST['hash'] ?? $_POST['id_hash'] ?? $_POST['id'] ?? $_GET['hash'] ?? $_GET['id_hash'] ?? $_GET['id'] ?? '');
}
$hash_in = trim($hash_in);

$out = array(
    'ok' => false,
    'http' => 0,
    'error' => '',
    'response' => '',
    'context' => (string)$bb_ajax_context['type'],
    'slug' => $__bb_slug,
);

if ($hash_in === '') {
    $out['error'] = 'Hash/ID do agendamento vazio.';
    bb_agd_del_json_out($out, $__bb_is_standalone);
    return;
}

/*
|--------------------------------------------------------------------------
| 4) Resolve API e tokens pelo banco do contexto atual
|--------------------------------------------------------------------------
*/
$api = isset($api) ? strtolower(trim((string)$api)) : '';
$token = isset($token) ? trim((string)$token) : '';
$instancia = isset($instancia) ? trim((string)$instancia) : '';

if ($pdo instanceof PDO) {
    try {
        $conf = $pdo->query("SELECT * FROM config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (is_array($conf)) {
            if ($api === '') {
                $api = strtolower(trim(bb_agd_del_pick_first($conf, array('api'))));
            }
            if ($token === '') {
                $token = bb_agd_del_pick_first($conf, array('appkey', 'AppKey', 'token', 'menuia_appkey', 'menuia_token'));
            }
            if ($instancia === '') {
                $instancia = bb_agd_del_pick_first($conf, array('authkey', 'AuthKey', 'instancia', 'menuia_authkey', 'menuia_instancia'));
            }
        }
    } catch (Throwable $e) {
        $out['error'] = 'Falha ao ler config do banco atual.';
        @error_log('agendar-delete.php config error: ' . $e->getMessage());
    }
}

if ($api === '') {
    $api = 'menuia';
}

$connectTimeout = 8;
$totalTimeout = 20;

/*
|--------------------------------------------------------------------------
| 5) Cancela na Menuia
|--------------------------------------------------------------------------
*/
if ($api === 'menuia') {
    if ($token === '' || $instancia === '') {
        $out['error'] = 'Tokens Menuia ausentes no config do contexto atual.';
        bb_agd_del_json_out($out, $__bb_is_standalone);
        return;
    }

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://chatbot.menuia.com/api/create-message',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_CONNECTTIMEOUT => $connectTimeout,
        CURLOPT_TIMEOUT => $totalTimeout,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array(
            'appkey' => $token,
            'authkey' => $instancia,
            'message' => $hash_in,
            'licence' => 'hugocursos',
            'cancelarAgendamento' => 'true',
        ),
    ));

    $resp = curl_exec($curl);
    $err = curl_error($curl);
    $http = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    $out['http'] = $http;
    $out['response'] = (string)$resp;
    $out['error'] = (string)$err;
    $out['ok'] = ($err === '' && $http >= 200 && $http < 300);

    if (!$out['ok']) {
        @error_log('agendar-delete.php Menuia FAIL http=' . $http . ' err=' . $err . ' resp=' . substr((string)$resp, 0, 400));
    }

    $bb_menuia_cancel_ok = (bool)$out['ok'];
    $bb_menuia_cancel_result = $out;

    bb_agd_del_json_out($out, $__bb_is_standalone);
    return;
}

/*
|--------------------------------------------------------------------------
| 6) Fallback legado WordMensagens
|--------------------------------------------------------------------------
*/
$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => 'http://api.wordmensagens.com.br/agendar-delete',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING => '',
    CURLOPT_MAXREDIRS => 10,
    CURLOPT_CONNECTTIMEOUT => $connectTimeout,
    CURLOPT_TIMEOUT => $totalTimeout,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode(array('id' => (int)$hash_in)),
    CURLOPT_HTTPHEADER => array('Content-Type: application/json'),
));

$resp = curl_exec($curl);
$err = curl_error($curl);
$http = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
curl_close($curl);

$out['http'] = $http;
$out['response'] = (string)$resp;
$out['error'] = (string)$err;
$out['ok'] = ($err === '' && $http >= 200 && $http < 300);

bb_agd_del_json_out($out, $__bb_is_standalone);
return;
?>