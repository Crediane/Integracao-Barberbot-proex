<?php
require_once __DIR__ . '/../_context_bootstrap.php';
header('Content-Type: application/json');

error_reporting(E_ALL);
ini_set('display_errors', 1);

$tabela = 'dispositivos';
require_once __DIR__ . '/../../../conexao.php';
$appkey = $_POST['appkey'];

$stmt = $pdo->prepare("UPDATE $tabela SET status = :status, status_api = :status_api WHERE appkey = :appkey");

if($stmt->execute([':status' => 'Ativo', ':status_api' => 'conectado', ':appkey' => $appkey ]))
{
    $stmt = $pdo->prepare("UPDATE config SET token = :token");
    $stmt->execute([':token' => $appkey]);
    
    
    $response = json_encode(['status' => 200]);
}
else
{
     $response = json_encode(['status' => 500]);
}

echo $response;

?>
