<?php
// 1. Recebe os dados brutos e transforma em um formato que o PHP entende (Array)
$json_recebido = file_get_contents('php://input');
$dados = json_decode($json_recebido, true);

// Mantém o bloco de notas gravando para você espiar depois
file_put_contents('log_whatsapp.txt', $json_recebido . PHP_EOL, FILE_APPEND);

// 2. Verifica se chegou uma mensagem válida e lê os dados
if (isset($dados['data']['key']['remoteJid'])) {
    
    $fromMe = $dados['data']['key']['fromMe']; // É o robô falando? (true ou false)
    $numero_remetente = $dados['data']['key']['remoteJid']; // Número do cliente + @s.whatsapp.net
    $texto_recebido = $dados['data']['message']['conversation'] ?? ''; // O texto que o cliente digitou

    // 3. REGRA DE OURO: O robô só responde se a mensagem NÃO for dele mesmo e não for vazia
    if (!$fromMe && !empty($texto_recebido)) {
        
        // Limpa o número (tira o "@s.whatsapp.net" para a API aceitar)
        $numero_limpo = str_replace('@s.whatsapp.net', '', $numero_remetente);

        // 4. A inteligência do bot (A resposta que ele vai dar)
        $resposta_texto = "🤖 Olá! Eu sou o BarberBot. Você acabou de me dizer: *" . $texto_recebido . "*";

        // 5. Envia a resposta de volta (O mesmo código do teste_envio.php)
        $url = "http://127.0.0.1:8080/message/sendText/MeuBotPHP";
        $apikey = "BarberBot123";

        $dados_envio = [
            "number" => $numero_limpo,
            "text" => $resposta_texto
        ];

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados_envio));
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "apikey: " . $apikey,
            "Content-Type: application/json"
        ]);
        curl_exec($ch);
        curl_close($ch);
    }
}

// 6. Retorna o sinal verde para a API saber que deu tudo certo
http_response_code(200);
?>