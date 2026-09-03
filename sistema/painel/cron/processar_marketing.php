<?php
require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/../../timezone_tenant.php';

$agora_tenant = date('Y-m-d H:i:s');

error_reporting(E_ALL);
ini_set('display_errors', 1);

$resultado = $pdo->prepare("SELECT * FROM disparos WHERE status = 'pendente' AND horario <= :agora ORDER BY id LIMIT 10");
$resultado->execute([':agora' => $agora_tenant]);
$disparos = $resultado->fetchAll(PDO::FETCH_ASSOC);


if($disparos)
{
    foreach($disparos as $disparo)
    {
        
        $clienteId = $disparo['id_cliente'] ?? false;
        $telefoneN = $disparo['telefone'];

        if($clienteId)
        {
            $resultados = $pdo->query("SELECT * FROM clientes WHERE id = '$clienteId'");
            $cliente = $resultados->fetch(PDO::FETCH_ASSOC);
            $telefoneN = $cliente['telefone'];
        }

        
        
        $telefone = preg_replace('/[^0-9]/', '', $telefoneN);
        $id = $disparo['id'];
        $mensagem = $disparo['mensagem'];
        $mensagem = str_replace("%0A", "\n", $mensagem); 
        $id_receber = $disparo['parcela_id'] ?? 0;
        
        if(strlen($telefone) < 10 || strlen($telefone) > 13 )
        {
           $pdo->query("UPDATE disparos SET status = 'cancelado', retorno = 'Número Inválido.' WHERE id = '$id'");
           continue;
        }
        
        if($id_receber != 0)
        {
             $resultados = $pdo->query("SELECT * FROM receber WHERE id = '$id_receber'");
            $receber = $resultados->fetch(PDO::FETCH_ASSOC);
            
            if (!$receber || empty($receber))
            {
                $pdo->query("UPDATE disparos SET status = 'cancelada', retorno = 'Não foi encontrado a parcela' WHERE id = '$id'");
                continue;
            }
            elseif($receber['pago'] == 'Sim')
            {
                $pdo->query("UPDATE disparos SET status = 'cancelada', retorno = 'Parcela já estava paga.' WHERE id = '$id'");
                continue;
            }
        }
        
        $result = $pdo->query("SELECT * FROM config");
        $dispositivo = $result->fetch(PDO::FETCH_ASSOC);
        
        if(!$dispositivo)
        {
            exit();
        }
        
        
        
        if(strlen($telefone) <= 11)
        {
            $telefone = '55'.$telefone;
        }
        
        
        $data = array(
            'appkey' => $token,
            'authkey' => $instancia,
            'to' => $telefone,
            'message' => $mensagem,
        );
        
        
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://chatbot.menuia.com/api/create-message',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => $data,
        ));
        
        $response = curl_exec($curl);
        $result = json_decode($response, true);
        curl_close($curl);
        
        $status = $result['status'] == 200 ? 'sucesso' : 'erro';

        
        if($result['status'] != 404)
        {
            $pdo->query("UPDATE disparos SET status = '$status', retorno = '$response' WHERE id = '$id'");
        }
    }
}
else
{
    echo "Nenhum disparo";
    exit();
}

echo 'Cron executado com sucesso!';

?>