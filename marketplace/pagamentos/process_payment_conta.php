<?php
// ============================================================================
// BarberBot Software - Processamento de Pagamento (Mercado Pago)
// Arquivo: /public_html/pagamentos/process_payment_conta.php
// Ajustes:
// - Multi-tenant (banco individual): captura /loja/<slug>/ ou ?loja= e salva na sessão ANTES da conexão
// - Segurança: sanitização básica + prepared statements (sem mudar a lógica)
// - Notificação: preserva ?loja= na notification_url quando existir
// Sem alterar a lógica de negócio: cria pagamento / consulta status / baixa conta.
// ============================================================================
require_once __DIR__ . '/config.php';

// Helper para anexar ?loja=... em URLs sem quebrar parâmetros existentes.
function bb_add_loja_param(string $url, string $loja): string {
	if ($loja === '') return $url;
	if (stripos($url, 'loja=') !== false) return $url;
	return $url . (strpos($url, '?') !== false ? '&' : '?') . 'loja=' . urlencode($loja);
}

function bb_digits($v) {
	return preg_replace('/\D+/', '', (string)$v);
}


function bb_resolver_conta_id_pagamento(array $response, PDO $pdo): int {
	$candidatos = [];

	$camposDiretos = [
		$response['external_reference'] ?? null,
		$response['collection']['external_reference'] ?? null,
		$response['metadata']['conta_id'] ?? null,
		$response['metadata']['id_conta'] ?? null,
		$response['metadata']['conta'] ?? null,
		$response['collection']['metadata']['conta_id'] ?? null,
		$response['collection']['metadata']['id_conta'] ?? null,
		$response['collection']['metadata']['conta'] ?? null,
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
			// mantém o fluxo do sistema
		}
	}

	$idMercadoPago = (string)($response['id'] ?? ($response['collection']['id'] ?? ''));
		$idMercadoPago = bb_digits($idMercadoPago);
	if ($idMercadoPago !== '') {
		try {
			$st = $pdo->prepare("SELECT id FROM receber WHERE ref_pix = :ref LIMIT 1");
			$st->execute([':ref' => $idMercadoPago]);
			$linha = $st->fetch(PDO::FETCH_ASSOC);
			if ($linha && !empty($linha['id'])) {
				return (int)$linha['id'];
			}
		} catch (Throwable $e) {
			// mantém o fluxo do sistema
		}
	}

	return 0;
}

function bb_atualizar_ref_pix_conta(PDO $pdo, int $contaId, string $refPix): void {
	if ($contaId <= 0 || $refPix === '') {
		return;
	}

	$st = $pdo->prepare("
		UPDATE receber
		   SET ref_pix = :ref
		 WHERE id = :id
		   AND (ref_pix IS NULL OR ref_pix = '' OR ref_pix = :ref)
	");
	$st->execute([
		':ref' => $refPix,
		':id'  => $contaId
	]);
}

// Configuração e conexão já carregadas pelo bootstrap tenant.

ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
//error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

$status_pag = array(
	"approved"    => "Aprovado",
	"rejected"    => "Rejeitado",
	"in_process"  => "Pendente aprovação",
);

$status_pag_motivo = array(
	"approved" => array(
		"accredited" => "Pronto, seu pagamento foi aprovado!"
	),
	"in_process" => array(
		"pending_contingency"   => "Estamos processando o pagamento. Não se preocupe, em menos de 2 dias úteis informaremos por e-mail se foi creditado.",
		"pending_review_manual" => "Estamos processando seu pagamento. Não se preocpe, em menos de 2 dias úteis informaremos por e-mail se foi creditado ou se necessitamos de mais informação."
	),
	"rejected" => array(
		"cc_rejected_bad_filled_card_number"   => "Revise o número do cartão.",
		"cc_rejected_bad_filled_date"          => "Revise a data de vencimento.",
		"cc_rejected_bad_filled_other"         => "Revise os dados.",
		"cc_rejected_bad_filled_security_code" => "Revise o código de segurança do cartão.",
		"cc_rejected_blacklist"                => "Não pudemos processar seu pagamento.",
		"cc_rejected_call_for_authorize"       => "Você deve autorizar ao payment_method_id o pagamento do valor ao Mercado Pago.",
		"cc_rejected_card_disabled"            => "Ligue para o payment_method_id para ativar seu cartão. O telefone está no verso do seu cartão.",
		"cc_rejected_card_error"               => "Não conseguimos processar seu pagamento.",
		"cc_rejected_duplicated_payment"       => "Você já efetuou um pagamento com esse valor. Caso precise pagar novamente, utilize outro cartão ou outra forma de pagamento.",
		"cc_rejected_high_risk"                => "Seu pagamento foi recusado. Escolha outra forma de pagamento. Recomendamos meios de pagamento em dinheiro.",
		"cc_rejected_insufficient_amount"      => "O payment_method_id possui saldo insuficiente.",
		"cc_rejected_invalid_installments"     => "O payment_method_id não processa pagamentos em installments parcelas.",
		"cc_rejected_max_attempts"             => "Você atingiu o limite de tentativas permitido. Escolha outro cartão ou outra forma de pagamento.",
		"cc_rejected_other_reason"             => "payment_method_id não processa o pagamento.",
		"cc_rejected_card_type_not_allowed"    => "O pagamento foi rejeitado porque o usuário não tem a função crédito habilitada em seu cartão multiplo (débito e crédito)."
	)
);

/* ===========================================================
 *  BLOCO 1 – CONSULTAR STATUS (acc=check)
 * =========================================================== */
if (isset($_GET["acc"]) && $_GET["acc"] === "check") {

	$id       = isset($_GET["id"])       ? bb_digits($_GET["id"]) : '';
	$id_conta = isset($_GET["id_conta"]) ? bb_digits($_GET["id_conta"]) : '';

	if ($id === '') {
		echo json_encode(['status' => 'erro', 'message' => 'Pagamento não informado']);
		exit;
	}

	// Consulta o pagamento no Mercado Pago
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
		CURLOPT_HTTPHEADER     => array(
			'Authorization: Bearer ' . $TOKEN_MERCADO_PAGO,
		),
	));

	$response_original = curl_exec($curl);
	curl_close($curl);
	$response = json_decode($response_original, true);

	if (!is_array($response)) {
		echo json_encode(['status' => 'erro', 'message' => 'Retorno inválido do Mercado Pago']);
		exit;
	}

	// Alguns ambientes retornam dados no topo, outros em "collection"
	$idcobranca         = $response["external_reference"]        ?? ($response["collection"]["external_reference"] ?? null);
	$status             = $response["status"]                    ?? ($response["collection"]["status"]             ?? null);
	$payment_method_id  = $response["payment_method_id"]         ?? ($response["collection"]["payment_method_id"]  ?? null);
	$transaction_amount = $response["transaction_amount"]        ?? ($response["collection"]["transaction_amount"] ?? null);
	$id_mercadopago     = $response["id"]                        ?? ($response["collection"]["id"]                 ?? null);

	$contaId = !empty($id_conta) ? (int)$id_conta : 0;
	if ($contaId <= 0) {
		$contaId = bb_resolver_conta_id_pagamento($response, $pdo);
	}

	if (!empty($id_mercadopago) && $contaId > 0) {
		bb_atualizar_ref_pix_conta($pdo, $contaId, (string)$id_mercadopago);
	}

	// PAGAMENTO APROVADO
	if ($status == "approved") {

		$ref_pix    = $id_mercadopago;
		$valor_pago = $transaction_amount;
		$forma_pgto = $payment_method_id;

		// descobre o id da conta: prioriza ?id_conta=; senão usa external_reference/metadata/ref_pix
		if ($contaId <= 0) {
			echo json_encode([
				'status'  => 'erro',
				'message' => 'Pagamento aprovado, mas a conta não foi localizada para baixa.'
			]);
			exit;
		}

		$stPago = $pdo->prepare("SELECT pago FROM receber WHERE id = :id LIMIT 1");
		$stPago->execute([':id' => $contaId]);
		$row = $stPago->fetch(PDO::FETCH_ASSOC);

		if (!$row) {
			echo json_encode([
				'status'  => 'erro',
				'message' => 'Conta não encontrada para baixa.'
			]);
			exit;
		}

		if ($row['pago'] != 'Sim') {
			// usa TODA sua lógica de baixa/recorrência/caixa/recibo no WhatsApp
			// (baixar_conta.php espera a variável $id definida)
			$id = $contaId;
			require(__DIR__ . "/baixar_conta.php");
		}

		echo json_encode(['status' => 'pago']);
		exit;

	} else {
		echo json_encode(['status' => $status]);
		exit;
	}
}
// FIM BLOCO acc=check

/* ===========================================================
 *  BLOCO 2 – GERAR PAGAMENTO
 * =========================================================== */
try {

	$parsed_body = json_decode(file_get_contents('php://input'), true);
	if (!is_array($parsed_body)) {
		throw new Exception("Body inválido");
	}

	$id_conta_req = 0;
	if (isset($_GET['id_conta']) && $_GET['id_conta'] !== '') {
		$id_conta_req = (int)bb_digits($_GET['id_conta']);
	} elseif (isset($_POST['id_conta']) && $_POST['id_conta'] !== '') {
		$id_conta_req = (int)bb_digits($_POST['id_conta']);
	} elseif (!empty($parsed_body['metadata']['conta_id'])) {
		$id_conta_req = (int)bb_digits((string)$parsed_body['metadata']['conta_id']);
	} elseif (!empty($parsed_body['metadata']['id_conta'])) {
		$id_conta_req = (int)bb_digits((string)$parsed_body['metadata']['id_conta']);
	} elseif (!empty($parsed_body['external_reference'])) {
		$id_conta_req = (int)bb_digits((string)$parsed_body['external_reference']);
	}

	$TIPO_PAGAMENTO = isset($parsed_body["payment_method_id"]) ? $parsed_body["payment_method_id"] : '';

	// ✅ Mantém a regra: define notification_url e capture
	// ✅ Multi-tenant: anexa loja quando existir para o webhook localizar o banco correto
	$parsed_body["notification_url"] = bb_add_loja_param($URL_NOTIFICACAO, $bb_loja);
	$parsed_body["capture"] = true;

	// ✅ Corrige o vínculo da cobrança sem mudar a lógica:
	//    garante que a conta chegue ao Mercado Pago como external_reference/metadata
	if ($id_conta_req > 0) {
		$parsed_body["external_reference"] = (string)$id_conta_req;

		if (!isset($parsed_body["metadata"]) || !is_array($parsed_body["metadata"])) {
			$parsed_body["metadata"] = [];
		}
		$parsed_body["metadata"]["conta_id"] = (string)$id_conta_req;
		$parsed_body["metadata"]["id_conta"] = (string)$id_conta_req;
	}

} catch (Exception $exception) {

	$response_fields = array('error_message' => $exception->getMessage());
	echo json_encode($response_fields);
	die;
}

// ENVIAR PAGAMENTO PARA O MERCADO PAGO
$curl = curl_init();
curl_setopt_array($curl, array(
	CURLOPT_URL            => 'https://api.mercadopago.com/v1/payments',
	CURLOPT_RETURNTRANSFER => true,
	CURLOPT_ENCODING       => '',
	CURLOPT_MAXREDIRS      => 10,
	CURLOPT_TIMEOUT        => 0,
	CURLOPT_FOLLOWLOCATION => true,
	CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
	CURLOPT_CUSTOMREQUEST  => 'POST',
	CURLOPT_POSTFIELDS     => json_encode($parsed_body),
	CURLOPT_HTTPHEADER     => array(
		'X-Idempotency-Key: ' . date('Y-m-d-H:i:s-') . rand(0, 1500),
		'Authorization: Bearer ' . $TOKEN_MERCADO_PAGO,
		'Content-Type: application/json',
	),
));

$response = curl_exec($curl);
curl_close($curl);

$payment = json_decode($response);

if (!isset($payment->id) || $payment->id === null) {

	$error_message = 'Erro ao realizar o pagamento, contacte com o suporte.';
	if (!empty($payment->message)) {
		$sdk_error_message = $payment->message;
		$error_message = $sdk_error_message ?: $error_message;
	}
	if ($error_message == "Invalid transaction_amount") {
		$error_message = "Valor de pagamento inválido";
	}

	echo json_encode(array("status" => false, "message" => $error_message));
	die;
}

$idcobranca         = $payment->external_reference;
$status             = $payment->status;
$payment_method_id  = $payment->payment_method_id;
$transaction_amount = $payment->transaction_amount;
$id_mercadopago     = $payment->id;

if (!isset($id_conta_req)) {
	$id_conta_req = 0;
}
if ($id_conta_req <= 0 && !empty($idcobranca)) {
	$id_conta_req = (int)bb_digits((string)$idcobranca);
}
if ($id_conta_req > 0 && !empty($id_mercadopago)) {
	bb_atualizar_ref_pix_conta($pdo, $id_conta_req, (string)$id_mercadopago);
}

if ($TIPO_PAGAMENTO == "pix") {

	$status_mostrar = ($payment->status == "pending") ? true : false;

} elseif ($TIPO_PAGAMENTO == "bolbradesco" || $TIPO_PAGAMENTO == "pec") { // boleto

	$status_mostrar = ($payment->status == "pending") ? true : false;

} else { // cartão

	$status_mostrar = true;

}

// mensagem amigável para o front
$mensagem_detalhe = '';
if (isset($status_pag_motivo[$payment->status]) && isset($status_pag_motivo[$payment->status][$payment->status_detail])) {
	$mensagem_detalhe = $status_pag_motivo[$payment->status][$payment->status_detail];
}

$transaction_data = array(
	'id'      => $payment->id,
	'status'  => $status_mostrar,
	'tipo'    => $TIPO_PAGAMENTO,
	'message' => $mensagem_detalhe,
);

echo json_encode($transaction_data);
die;
