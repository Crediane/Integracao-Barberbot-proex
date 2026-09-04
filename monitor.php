<?php
// Liga a exibição de erros
ini_set('display_errors', 1);
error_reporting(E_ALL);

$timeout_segundos = 30; // 30 segundos para os nossos testes!
$tempo_atual = time();

// Procura todos os arquivos de sessão que o webhook criou
$arquivos_sessao = glob('sessao_*.json');

echo "<h3>Vigiando " . count($arquivos_sessao) . " conversas ativas...</h3>";

foreach ($arquivos_sessao as $arquivo) {
    $sessao = json_decode(file_get_contents($arquivo), true);
    $tempo_inativo = $tempo_atual - $sessao['ultimo_contato'];
    
    // Se passou do nosso limite de tempo...
    if ($tempo_inativo > $timeout_segundos) {
        
        // Descobre qual é o número do cliente tirando o nome do arquivo
        $numero_limpo = str_replace(['sessao_', '.json'], '', $arquivo);
        
        // Monta a mensagem de encerramento
        $resposta_texto = "⏳ *Sessão encerrada por falta de interação.*\nPara recomeçar, é só me mandar um Oi!";
        
        // Dispara a mensagem via cURL
        $ch = curl_init("http://127.0.0.1:8080/message/sendText/MeuBotPHP");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["number" => $numero_limpo, "text" => $resposta_texto]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["apikey: BarberBot123", "Content-Type: application/json"]);
        curl_exec($ch);
        curl_close($ch);

        // Apaga o arquivo de sessão para não mandar a mensagem repetida
        unlink($arquivo);
        
        echo "<p style='color:red;'>Sessão de {$numero_limpo} encerrada!</p>";
    } else {
        echo "<p style='color:green;'>Sessão ok. Faltam " . ($timeout_segundos - $tempo_inativo) . " segundos para encerrar.</p>";
    }
}
?>

<!-- Esse script em JavaScript faz a página se auto-atualizar a cada 5 segundos -->
<script>
    setTimeout(function(){
        window.location.reload(1);
    }, 5000);
</script>