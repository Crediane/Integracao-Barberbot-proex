<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
header('Content-Type: application/json');

$response = array();


    $authkey = $_GET['authkey'] ?? null;
    $email = $_GET['email'] ?? null ;
    $validade = $_GET['validade'] ?? null;
    $plano = $_GET['planoID'] ?? null;
    $senha = $_GET['senha'] ?? null;
    $dateTime = new DateTime($validade);
    $validadeFormatada = $dateTime->format('Y-m-d');
    

    
    
    try {
        $stmt = $pdo->prepare("UPDATE config SET token = :authkey, email_menuia = :email, plano_menuia = :plano, validade_menuia = :validade, senha_menuia = :senha, api = :api ");
            $stmt->bindParam(':authkey', $authkey);
            $stmt->bindParam(':email', $email);
            $stmt->bindParam(':plano', $plano);
            $stmt->bindParam(':validade', $validadeFormatada);
            $stmt->bindParam(':senha', $senha);
            $stmt->bindValue(':api', "menuia");
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

echo json_encode($response);

?>
