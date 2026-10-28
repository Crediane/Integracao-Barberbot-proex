<?php
// monitor.php - Script para limpar sessões inativas (BarberBot)

// 180 segundos = 3 minutos de inatividade
$tempo_limite = 120; 

// Procura todos os arquivos JSON de sessão na pasta atual
$arquivos = glob("sessao_*.json");

$apagados = 0;
foreach ($arquivos as $arquivo) {
    $conteudo = file_get_contents($arquivo);
    $sessao = json_decode($conteudo, true);
    
    // Verifica quando foi o último contato do usuário
    if (isset($sessao['ultimo_contato'])) {
        $tempo_inativo = time() - $sessao['ultimo_contato'];
        
        // Se passou do limite, avisa o cliente e apaga o arquivo
        if ($tempo_inativo > $tempo_limite) {
            
            // 1. Extrai o número de telefone do nome do arquivo (remove "sessao_" e ".json")
            $numero_limpo = str_replace(['sessao_', '.json'], '', $arquivo);
            
            // 2. Prepara a mensagem de encerramento
            $resposta_texto = "⏳ Sessão encerrada por falta de interação.\nPara recomeçar, é só me mandar um Oi!";
            
            // 3. Envia a mensagem via Evolution API
            $ch = curl_init("http://127.0.0.1:8080/message/sendText/MeuBotPHP");
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["number" => $numero_limpo, "text" => $resposta_texto]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ["apikey: BarberBot123", "Content-Type: application/json"]);
            curl_exec($ch);
            curl_close($ch);
            
            // 4. Apaga o arquivo da sessão
            unlink($arquivo);
            $apagados++;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta http-equiv="refresh" content="10">
    <title>Monitor do BarberBot</title>
</head>
<body style="font-family: Arial, sans-serif; text-align: center; margin-top: 50px; background-color: #f4f4f9;">
    <h2>Vigia de Sessões do BarberBot 🤖</h2>
    <p>Monitorando conversas inativas...</p>
    <p>Tempo de inatividade configurado: <strong>2 minutos</strong></p>
    <hr style="width: 50%; margin: 20px auto;">
    <p>Última verificação: <strong><?php echo date('d/m/Y H:i:s'); ?></strong></p>
    <p style="color: red;">Sessões encerradas nesta rodada: <strong><?php echo $apagados; ?></strong></p>
    <br>
    <p><em>Deixe esta aba aberta durante os testes para limpar as sessões automaticamente.</em></p>
</body>
</html>