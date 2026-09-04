<?php
$json_recebido = file_get_contents('php://input');
$dados = json_decode($json_recebido, true);

if (isset($dados['data']['key']['remoteJid'])) {
    
    $fromMe = $dados['data']['key']['fromMe']; 
    $numero_remetente = $dados['data']['key']['remoteJid']; 
    $texto_recebido = trim($dados['data']['message']['conversation'] ?? '');

    if (!$fromMe && !empty($texto_recebido)) {
        
        $numero_limpo = str_replace('@s.whatsapp.net', '', $numero_remetente);
        
        // 1. Salva a sessão com a hora da última mensagem
        $nova_sessao = ['ultimo_contato' => time()];
        file_put_contents('sessao_' . $numero_limpo . '.json', json_encode($nova_sessao));
        
        // 2. Lógica do Menu
        switch ($texto_recebido) {
            case '1': $resposta_texto = "📅 Você escolheu *Agendar um Horário*!"; break;
            case '2': $resposta_texto = "✂️ Você escolheu *Ver Meus Agendamentos*!"; break;
            case '3': $resposta_texto = "📍 *Nosso Endereço:* Rua da Barbearia, 123."; break;
            default:  $resposta_texto = "Olá! Bem-vindo ao *BarberBot*! 💈\nDigite:\n*1.* Agendar\n*2.* Meus Agendamentos\n*3.* Endereço"; break;
        }

        // 3. Envia a resposta
        $ch = curl_init("http://127.0.0.1:8080/message/sendText/MeuBotPHP");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(["number" => $numero_limpo, "text" => $resposta_texto]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ["apikey: BarberBot123", "Content-Type: application/json"]);
        curl_exec($ch);
        curl_close($ch);
    }
}
http_response_code(200);
?>