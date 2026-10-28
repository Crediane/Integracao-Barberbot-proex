<?php
// URL do nosso webhook local
$url = 'http://localhost/marketplace/webhook_menuia.php';

// Simulando um payload (os dados que o Menuia enviaria)
$dados_falsos = [
    'nome_cliente' => 'João',
    'telefone' => '5545999991234',
    'mensagem' => 'Oi, quero fazer um agendamento'
];

// Configurando a requisição HTTP POST para enviar o JSON
$opcoes = [
    'http' => [
        'header'  => "Content-type: application/json\r\n",
        'method'  => 'POST',
        'content' => json_encode($dados_falsos)
    ]
];

// Executando o disparo contra o nosso Webhook
$contexto  = stream_context_create($opcoes);
$resultado = file_get_contents($url, false, $contexto);

echo "<h3>Disparo Simulado!</h3>";
echo "Resposta do seu Webhook: <b>" . $resultado . "</b>";
?>