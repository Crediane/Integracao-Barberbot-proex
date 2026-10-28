<?php
// Liga o "modo fofoqueiro" do PHP para mostrar qualquer erro na tela
ini_set('display_errors', 1);
error_reporting(E_ALL);

// 1. Configurações da Evolution API (Usando 127.0.0.1 em vez de localhost)
$url = "http://127.0.0.1:8080/message/sendText/MeuBotPHP";
$apikey = "BarberBot123";

// 2. Dados da mensagem
$dados = [
    "number" => "5545999457463", // Não esqueça de colocar o número do seu celular de testes aqui
    "text" => "🤖 Olá! Esta mensagem foi enviada direto do meu código em PHP ajustado!"
];

// 3. Inicializa a comunicação via cURL
$ch = curl_init($url);

// 4. Configura as opções (Método POST, Headers e JSON)
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: " . $apikey,
    "Content-Type: application/json"
]);

// 5. Executa o disparo e pega a resposta
$resposta = curl_exec($ch);
$erro = curl_error($ch);
curl_close($ch);

// 6. Mostra o resultado na tela
if ($erro) {
    echo "<h3>Erro ao enviar:</h3> <p>" . $erro . "</p>";
} else {
    echo "<h3>Sucesso! Resposta da API:</h3> <p>" . $resposta . "</p>";
}
?>