<?php
$url = "http://127.0.0.1:8080/webhook/set/MeuBotPHP";

// O PHP garante que a estrutura JSON é montada perfeitamente, sem erros de sintaxe
$payload = json_encode([
    "webhook" => [
        "enabled" => true,
        "url" => "http://127.0.0.1/marketplace/webhook.php",
        "byEvents" => false,
        "base64" => true,
        "events" => ["MESSAGES_UPSERT"]
    ]
]);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $payload);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "apikey: BarberBot123",
    "Content-Type: application/json"
]);

$resposta = curl_exec($ch);
$codigo_http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

echo "<h2 style='font-family: sans-serif;'>Configuração do Webhook</h2>";
if ($codigo_http == 200 || $codigo_http == 201) {
    echo "<p style='color: green; font-size: 18px;'><b>✅ Sucesso!</b> O envio de áudio (Base64) foi ativado na Evolution API.</p>";
} else {
    echo "<p style='color: red; font-size: 18px;'><b>❌ Ocorreu um Erro (HTTP " . $codigo_http . ")</b></p>";
}

// Mostra a resposta exata do servidor na tela
echo "<div style='background: #eee; padding: 15px; border-radius: 5px;'><pre>" . print_r(json_decode($resposta, true), true) . "</pre></div>";
?>