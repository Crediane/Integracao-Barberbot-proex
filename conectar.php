<?php
// conectar.php - Gerador de QR Code para o BarberBot

// Faz a requisição para a Evolution API pedindo o QR Code da instância MeuBotPHP
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => "http://127.0.0.1:8080/instance/connect/MeuBotPHP",
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_HTTPHEADER => [
        "apikey: BarberBot123"
    ],
]);

$resposta = curl_exec($ch);
curl_close($ch);

$dados = json_decode($resposta, true);
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <title>Conectar WhatsApp</title>
</head>
<body style="font-family: Arial, sans-serif; text-align: center; margin-top: 50px;">
    
    <h2>Conexão do BarberBot 🤖</h2>

    <?php if (isset($dados['base64'])): ?>
        <p>Abra o WhatsApp no seu celular, vá em <strong>Aparelhos Conectados</strong> e escaneie o código abaixo:</p>
        <img src="<?php echo $dados['base64']; ?>" alt="QR Code do WhatsApp" style="border: 2px solid #333; border-radius: 10px; padding: 10px;">
    <?php else: ?>
        <p style="color: green;"><strong>Aviso:</strong> Nenhum QR Code foi gerado.</p>
        <p>Isso geralmente significa que a instância já está conectada ou o terminal da API está fechado.</p>
        <pre style="text-align: left; display: inline-block; background: #eee; padding: 10px;"><?php print_r($dados); ?></pre>
    <?php endif; ?>

</body>
</html>