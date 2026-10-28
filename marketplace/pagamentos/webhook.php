<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
//error_reporting(E_ALL);

/**
 * Arquivo: webhook.php
 *
 * Correção aplicada (SEM mudar a lógica de negócio):
 * - Garantir o contexto correto do multi-banco (MASTER x LOJA) ANTES de carregar config/conexao.
 * - Suportar webhook com id em ?id=... OU ?data.id=... (compatibilidade Mercado Pago) sem alterar fluxo.
 * - Segurança: prepared statements no SELECT (mantendo o mesmo resultado).
 */

require_once __DIR__ . '/config.php';

function bb_digits($v) {
    return preg_replace('/\D+/', '', (string)$v);
}

function bb_obter_array_valor(array $arr, array $caminho, $default = null) {
    $atual = $arr;
    foreach ($caminho as $chave) {
        if (!is_array($atual) || !array_key_exists($chave, $atual)) {
            return $default;
        }
        $atual = $atual[$chave];
    }
    return $atual;
}

function bb_resolver_conta_id_pagamento(array $response, PDO $pdo): int {
    $candidatos = [];

    $camposDiretos = [
        $response['external_reference'] ?? null,
        $response['collection']['external_reference'] ?? null,
        bb_obter_array_valor($response, ['metadata', 'conta_id']),
        bb_obter_array_valor($response, ['metadata', 'id_conta']),
        bb_obter_array_valor($response, ['metadata', 'conta']),
        bb_obter_array_valor($response, ['collection', 'metadata', 'conta_id']),
        bb_obter_array_valor($response, ['collection', 'metadata', 'id_conta']),
        bb_obter_array_valor($response, ['collection', 'metadata', 'conta']),
    ];

    foreach ($camposDiretos as $valor) {
        $valor = (int)bb_digits((string)$valor);
        if ($valor > 0) {
            $candidatos[] = $valor;
        }
    }

    foreach ($candidatos as $contaId) {
        try {
            $st = $pdo->prepare("SELECT id FROM receber WHERE id = :id LIMIT 1");
            $st->execute([':id' => $contaId]);
            if ($st->fetch(PDO::FETCH_ASSOC)) {
                return $contaId;
            }
        } catch (Throwable $e) {
            // mantém o fluxo
        }
    }

    $idMercadoPago = bb_digits((string)($response['id'] ?? ($response['collection']['id'] ?? '')));
    if ($idMercadoPago !== '') {
        try {
            $st = $pdo->prepare("SELECT id FROM receber WHERE ref_pix = :ref LIMIT 1");
            $st->execute([':ref' => $idMercadoPago]);
            $linha = $st->fetch(PDO::FETCH_ASSOC);
            if ($linha && !empty($linha['id'])) {
                return (int)$linha['id'];
            }
        } catch (Throwable $e) {
            // mantém o fluxo
        }
    }

    return 0;
}

function bb_atualizar_ref_pix_conta(PDO $pdo, int $contaId, string $refPix): void {
    if ($contaId <= 0 || $refPix === '') {
        return;
    }

    $stmt = $pdo->prepare("
        UPDATE receber
           SET ref_pix = :ref
         WHERE id = :id
           AND (ref_pix IS NULL OR ref_pix = '' OR ref_pix = :ref)
    ");
    $stmt->execute([
        ':ref' => $refPix,
        ':id'  => $contaId
    ]);
}

// Configuração e conexão já carregadas pelo bootstrap tenant.

/*
 * 1) Validação dos parâmetros do webhook
 *    Mercado Pago chama com ?topic=payment&id=123456789
 *    Alguns setups chamam com ?type=payment&data.id=123456789
 */
$rawBody = file_get_contents('php://input');
$body = json_decode((string)$rawBody, true);
if (!is_array($body)) {
    $body = [];
}

$topic = isset($_GET['topic']) ? trim((string)$_GET['topic']) : '';
$id    = isset($_GET['id'])    ? trim((string)$_GET['id'])    : '';

// Alguns setups usam "type" no lugar de "topic"
if ($topic === '' && isset($_GET['type'])) {
    $topic = trim((string)$_GET['type']);
}
if ($topic === '' && !empty($body['type'])) {
    $topic = trim((string)$body['type']);
}
if ($topic === '' && !empty($body['topic'])) {
    $topic = trim((string)$body['topic']);
}

// Alguns setups usam "data.id" no lugar de "id"
if ($id === '' && isset($_GET['data.id'])) {
    $id = trim((string)$_GET['data.id']);
}
if ($id === '' && !empty($body['data']['id'])) {
    $id = trim((string)$body['data']['id']);
}
if ($id === '' && !empty($body['id'])) {
    $id = trim((string)$body['id']);
}
if ($id === '' && !empty($body['resource'])) {
    $resource = trim((string)$body['resource']);
    if (preg_match('~/([0-9]+)(?:\?.*)?$~', $resource, $mRes)) {
        $id = (string)$mRes[1];
    }
}

$id = bb_digits($id);

// Se não vier o tópico, mas houver ID numérico, assume payment (padrão mais comum do MP)
if ($topic === '' && $id !== '') {
    $topic = 'payment';
}

if ($topic === '' || $id === '') {
    // nada pra fazer
    exit('sem_dados');
}

/*
 * 2) Consulta do pagamento na API do Mercado Pago
 */
$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL            => 'https://api.mercadopago.com/v1/payments/' . $id,
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_ENCODING       => '',
    CURLOPT_MAXREDIRS      => 10,
    CURLOPT_TIMEOUT        => 0,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
    CURLOPT_CUSTOMREQUEST  => 'GET',
    CURLOPT_HTTPHEADER     => array('Authorization: Bearer ' . $TOKEN_MERCADO_PAGO),
));

$response_original = curl_exec($curl);
curl_close($curl);

$response = json_decode($response_original, true);
if (empty($response) || !is_array($response)) {
    exit('dados_vazios');
}

/*
 * 3) Campos do pagamento
 *    Alguns ambientes retornam no topo, outros dentro de "collection".
 *    Pegamos de forma segura (fallback).
 */
$idcobranca         = $response['external_reference']             ?? ($response['collection']['external_reference']     ?? null);
$status             = $response['status']                         ?? ($response['collection']['status']                 ?? null);
$payment_method_id  = $response['payment_method_id']              ?? ($response['collection']['payment_method_id']      ?? null);
$transaction_amount = $response['transaction_amount']             ?? ($response['collection']['transaction_amount']     ?? null);
$id_mercado_pago    = $response['id']                             ?? ($response['collection']['id']                     ?? null);

/*
 * 4) Pagamento aprovado → baixa segura + uso da rotina padrão (baixar_conta.php)
 */
if ($status === 'approved') {

    $contaId = bb_resolver_conta_id_pagamento($response, $pdo);

    if ($contaId > 0) {
        bb_atualizar_ref_pix_conta($pdo, $contaId, (string)$id_mercado_pago);

        // evita dupla baixa (se webhook e painel tentarem ao mesmo tempo)
        $stPago = $pdo->prepare("SELECT pago FROM receber WHERE id = :id LIMIT 1");
        $stPago->execute([':id' => $contaId]);
        $row = $stPago->fetch(PDO::FETCH_ASSOC);

        if ($row && $row['pago'] !== 'Sim') {

            // usa a MESMA rotina de baixa do sistema (recorrência, caixa, etc.)
            $id = $contaId;                   // baixar_conta.php espera a variável $id
            require(__DIR__ . '/baixar_conta.php');
        }

        echo json_encode(['status' => 'pago']);
        exit;
    }

    echo json_encode([
        'status' => 'approved',
        'message' => 'Pagamento aprovado, mas a conta não foi localizada para baixa.'
    ]);
    exit;
}

/*
 * 5) Outros status (pending, cancelled, etc.) → só responde
 */
echo json_encode(['status' => $status]);
exit;
?>
