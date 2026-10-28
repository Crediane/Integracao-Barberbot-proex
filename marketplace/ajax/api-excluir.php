<?php
// ============================================================================
// /public_html/ajax/api-excluir.php
//
// Cancela mensagem agendada na Menuia.
// Mantém o padrão antigo de variáveis globais:
// - $hash OU $id_hash
// - POST/GET: hash | id_hash | id
//
// Correção pontual de roteamento:
// - TENANT: /loja/{slug}/ usa exclusivamente o banco individual da barbearia.
// - MASTER: /sistema/ usa exclusivamente o banco global/administrativo.
// - Evita que HTTP_REFERER de /loja/{slug}/ force banco tenant dentro do painel master.
// - Não imprime nada quando incluído.
// - Quando acessado diretamente, retorna JSON.
// ============================================================================

$__bb_is_standalone = (realpath(__FILE__) === realpath($_SERVER['SCRIPT_FILENAME'] ?? ''));

if (!function_exists('bb_clean_slug')) {
    function bb_clean_slug($v) {
        $v = strtolower(trim((string)$v));
        return preg_replace('/[^a-z0-9_-]/', '', $v);
    }
}

if (!function_exists('bb_pick_first')) {
    function bb_pick_first(array $arr, array $keys) {
        foreach ($keys as $k) {
            if (isset($arr[$k]) && trim((string)$arr[$k]) !== '') {
                return (string)$arr[$k];
            }
        }
        return '';
    }
}

if (!function_exists('bb_path_starts')) {
    function bb_path_starts(string $path, string $prefix): bool {
        return (strpos($path, $prefix) === 0);
    }
}

$bb_ajax_context_mode = 'any';
$bb_ajax_context_response = $__bb_is_standalone ? 'json' : 'plain';
require_once __DIR__ . '/_context_bootstrap.php';

$__bb_ctx = (string)$bb_ajax_context['type'];
$__bb_slug = (string)$bb_ajax_context['slug'];
$pdo = $GLOBALS['pdo'] ?? ($pdo ?? null);

// ---------------------------------------------------------------------------
// 2) Lê hash/id do cancelamento
// ---------------------------------------------------------------------------
$hash_in = '';
if (isset($hash)) {
    $hash_in = (string)$hash;
}
if ($hash_in === '' && isset($id_hash)) {
    $hash_in = (string)$id_hash;
}
if ($hash_in === '') {
    $hash_in = (string)($_POST['hash'] ?? $_POST['id_hash'] ?? $_POST['id'] ?? $_GET['hash'] ?? $_GET['id'] ?? '');
}
$hash_in = trim($hash_in);

// ---------------------------------------------------------------------------
// 4) Tokens Menuia do banco correto
// Padrão do sistema:
// - token     => appkey
// - instancia => authkey
// Também aceita appkey/authkey se existirem.
// ---------------------------------------------------------------------------
$appkey = '';
$authkey = '';

if ($pdo instanceof PDO) {
    try {
        $conf = $pdo->query("SELECT * FROM config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if (is_array($conf)) {
            $appkey  = bb_pick_first($conf, array('appkey', 'AppKey', 'token', 'menuia_appkey', 'menuia_token'));
            $authkey = bb_pick_first($conf, array('authkey', 'AuthKey', 'instancia', 'menuia_authkey', 'menuia_instancia'));
        }
    } catch (Throwable $e) {
        @error_log('api-excluir.php config error: ' . $e->getMessage());
    }
}

// ---------------------------------------------------------------------------
// 5) Cancela agendamento na Menuia
// ---------------------------------------------------------------------------
$out = array(
    'ok' => false,
    'http' => 0,
    'error' => '',
    'response' => '',
    'contexto' => $__bb_ctx,
    'slug' => $__bb_slug,
);

if ($hash_in === '') {
    $out['error'] = 'Hash/ID do agendamento vazio.';
} elseif ($appkey === '' || $authkey === '') {
    $out['error'] = 'Tokens Menuia ausentes no config (appkey/authkey ou token/instancia).';
} else {
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://chatbot.menuia.com/api/create-message',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS => 5,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array(
            'appkey' => $appkey,
            'authkey' => $authkey,
            'message' => $hash_in,
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

    if ($err === '' && $http >= 200 && $http < 300) {
        $out['ok'] = true;
    } else {
        @error_log('api-excluir.php FAIL ctx=' . $__bb_ctx . ' slug=' . $__bb_slug . ' http=' . $http . ' err=' . $err . ' resp=' . substr((string)$resp, 0, 400));
    }
}

// Disponibiliza resultado para quem incluiu
$bb_menuia_cancel_ok = (bool)$out['ok'];
$bb_menuia_cancel_result = $out;

// Se for acesso direto, responde JSON
if ($__bb_is_standalone) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($out, JSON_UNESCAPED_UNICODE);
}

return;
?>