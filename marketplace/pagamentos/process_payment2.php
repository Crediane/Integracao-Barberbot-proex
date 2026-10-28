<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

$check = $_GET["acc"] ?? false;

if ($check === "check") {

    $id = preg_replace('/\D+/', '', (string)($_GET["id"] ?? ''));
    $idConta = (int)($_GET["id_conta"] ?? 0);

    if ($id === '' || $idConta <= 0) {
        echo json_encode(array("status" => "error", "message" => "Parâmetros inválidos"));
        exit();
    }

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.mercadopago.com/v1/payments/' . $id,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'GET',
        CURLOPT_HTTPHEADER => array(
            'Authorization: Bearer ' . $TOKEN_MERCADO_PAGO,
        ),
    ));
    
   
    
    $response_original = curl_exec($curl);
    curl_close($curl);
    $response = json_decode((string)$response_original, true);
    if (!is_array($response)) {
        echo json_encode(array("status" => "error", "message" => "Retorno inválido do Mercado Pago"));
        exit();
    }

    $idcobranca = (string)($response["external_reference"] ?? '');
    $status = (string)($response["status"] ?? '');
    $payment_method_id = (string)($response["payment_method_id"] ?? '');
    $transaction_amount = (float)($response["transaction_amount"] ?? 0);
    $id_mercadopago = (string)($response["id"] ?? $id);

    $stmtAtualizaTemp = $pdo->prepare("UPDATE agendamentos_temp SET ref_pix = :ref_pix, valor_pago = :valor_pago WHERE id = :id");
    $stmtAtualizaTemp->execute([
        ':ref_pix' => $id_mercadopago,
        ':valor_pago' => $transaction_amount,
        ':id' => $idConta
    ]);

   
    if ($status == "approved") { // PAGAMENTO APROVADO
        
        $statusPay = true;
        $ref_pix = $id_mercadopago;
        $valor_pago = $transaction_amount;
        $forma_pgto = $payment_method_id;
        require __DIR__ . '/pagamento_aprovado.php';
        
    
        // Seleciona os dados da conta atualizada
        $stmt2 = $pdo->prepare("SELECT * FROM receber WHERE id = :id_conta ORDER BY id DESC LIMIT 1");
        $stmt2->execute([':id_conta' => $idConta]);
        $res2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
    
        if (count($res2) > 0) {
            
            $hash = $res2[0]['hash'];
            $cliente = $res2[0]['cliente'];
            $id_ref = $res2[0]['id_ref'];
            $valor = $res2[0]['valor'];
            $parcela = $res2[0]['parcela'];
            $nova_parcela = $parcela + 1;
            $recorrencia = $res2[0]['recorrencia'];
            $data_venc = $res2[0]['data_venc'];
            $dias_frequencia = $res2[0]['frequencia'];
            $descricao = $res2[0]['descricao'];
            $usuario_lanc = $res2[0]['usuario_lanc'];
    
    
            // Insere uma nova parcela se a recorrência for 'Sim'
            if($recorrencia == 'Sim'){
                if($dias_frequencia == 30 || $dias_frequencia == 31){           
                        $novo_vencimento = date('Y-m-d', @strtotime("+1 month",@strtotime($data_venc)));
                    }else if($dias_frequencia == 90){           
                        $novo_vencimento = date('Y-m-d', @strtotime("+3 month",@strtotime($data_venc)));
                    }else if($dias_frequencia == 180){ 
                        $novo_vencimento = date('Y-m-d', @strtotime("6 month",@strtotime($data_venc)));
                    }else if($dias_frequencia == 360 || $dias_frequencia == 365){           
                        $novo_vencimento = date('Y-m-d', @strtotime("+12 month",@strtotime($data_venc)));
            
                    }else{          
                        $novo_vencimento = date('Y-m-d', @strtotime("+$dias_frequencia days",@strtotime($data_venc)));
                    }
            
                //criar outra conta a receber na mesma data de vencimento com a frequência associada
                $data_lancamento = date('Y-m-d');
                $stmtNovaConta = $pdo->prepare("INSERT INTO receber SET cliente = :cliente, referencia = 'Cobrança', id_ref = :id_ref, valor = :valor, parcela = :parcela, usuario_lanc = '0', data_lanc = :data_lanc, data_venc = :data_venc, pago = 'Não', descricao = :descricao, frequencia = :frequencia, recorrencia = 'Sim'");
                $stmtNovaConta->execute([
                    ':cliente' => $cliente,
                    ':id_ref' => $id_ref,
                    ':valor' => $valor,
                    ':parcela' => $nova_parcela,
                    ':data_lanc' => $data_lancamento,
                    ':data_venc' => $novo_vencimento,
                    ':descricao' => $descricao,
                    ':frequencia' => $dias_frequencia
                ]);
            }
            
            echo json_encode(array("status" => "pago", "novoId" => $idConta));
            exit();
        
        } else {
            echo json_encode(array("status" => "error", "message" => "Record not found"));
              exit();
        }
    }


}
// FIM

// GERAR PAGAMENTO
try {
    
    $parsed_body = json_decode((string)file_get_contents('php://input'), true);
    if (!is_array($parsed_body)) {
        throw new RuntimeException('Dados de pagamento inválidos.');
    }
    $TIPO_PAGAMENTO = (string)($parsed_body["payment_method_id"] ?? '');
    $parsed_body["notification_url"] = $URL_NOTIFICACAO;
    $parsed_body["capture"] = true;
    
} catch(Exception $exception) {

    $response_fields = array('error_message' => $exception->getMessage());
    echo json_encode($response_fields);
     exit();

}

// ENVIAR
$curl = curl_init();
curl_setopt_array($curl, array(
CURLOPT_URL => 'https://api.mercadopago.com/v1/payments',
CURLOPT_RETURNTRANSFER => true,
CURLOPT_ENCODING => '',
CURLOPT_MAXREDIRS => 10,
CURLOPT_TIMEOUT => 0,
CURLOPT_FOLLOWLOCATION => true,
CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
CURLOPT_CUSTOMREQUEST => 'POST',
CURLOPT_POSTFIELDS => json_encode($parsed_body),
CURLOPT_HTTPHEADER => array(
    'X-Idempotency-Key: '.date('Y-m-d-H:i:s-').rand(0, 1500),
    'Authorization: Bearer '.$TOKEN_MERCADO_PAGO,
    'Content-Type: application/json',
),
));

$response = curl_exec($curl);
curl_close($curl);

$payment = json_decode((string)$response);

if (!is_object($payment) || empty($payment->id)) {
    $error_message = 'Erro ao realizar o pagamento, contacte com o suporte.';
    if (is_object($payment) && isset($payment->message)) {
        $sdk_error_message = $payment->message;
        $error_message = $sdk_error_message !== null ? $sdk_error_message : $error_message;
    }
    if($error_message == "Invalid transaction_amount"){
        $error_message = "Valor de pagamento inválido";
    }
    echo json_encode(array("status" => false, "message" => $error_message));
     exit();
    //throw new Exception($error_message);
} 

$idcobranca = $payment->external_reference;
$status = $payment->status;
$payment_method_id = $payment->payment_method_id;
$transaction_amount = $payment->transaction_amount;
$id_mercadopago = $payment->id;

if($TIPO_PAGAMENTO=="pix"){

   
    $status_mostrar = ($payment->status=="pending")? true : false;

} elseif($TIPO_PAGAMENTO=="bolbradesco" || $TIPO_PAGAMENTO=="pec"){ // boleto

   
    $status_mostrar = ($payment->status=="pending")? true: false;

} else { // cartao

   
    $status_mostrar = true;

}

$transaction_data = array(
    'id' => $payment->id,
    'status' => $status_mostrar,
    'tipo' => $TIPO_PAGAMENTO,
    'message' => $status_pag_motivo[$payment->status][$payment->status_detail] ?? 'Pagamento processado.',
);

echo json_encode($transaction_data);
die;


