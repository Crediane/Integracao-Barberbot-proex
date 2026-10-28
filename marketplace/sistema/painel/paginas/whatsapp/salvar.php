<?php
require_once __DIR__ . '/../_context_bootstrap.php';

header('Content-Type: application/json');

$response = array();

if(isset($_GET['appkey'])) {
    $appkey = $_GET['appkey'];

    require_once __DIR__ . '/../../../conexao.php';
    
    try {
        $stmt = $pdo->prepare("UPDATE config SET instancia = :appkey");
        $stmt->bindParam(':appkey', $appkey);
        $stmt->execute();
        
        // Verifica se houve exceções durante a execução da consulta
        if($stmt->errorCode() === '00000') {
            $response['status'] = 200;
            $response['message'] = 'Atualização bem-sucedida';
        } else {
            $response['status'] = 500;
            $response['message'] = 'Erro interno ao atualizar';
        }
    } catch (PDOException $e) {
        $response['status'] = 500;
        $response['message'] = 'Erro de banco de dados: ' . $e->getMessage();
    }
} else {
    $response['status'] = 404;
    $response['message'] = 'Appkey não informada';
}

echo json_encode($response);

?>
