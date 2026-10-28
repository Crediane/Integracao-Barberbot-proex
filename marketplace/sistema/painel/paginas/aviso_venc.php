<?php
$url_destino = "https://gasto.jc.tec.br/painel/apis/bloqueio_cron.php";

// Parâmetros para enviar
$data = array('site' => $url_sistema);

// Inicializar CURL
$ch = curl_init();

// Configurar CURL
curl_setopt($ch, CURLOPT_URL, $url_destino);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

// Executar CURL
$response = curl_exec($ch);

// Verificar por erros
if(curl_errno($ch)){
    echo 'Erro ao fazer a solicitação: ' . curl_error($ch);
}

// Fechar CURL
curl_close($ch);

// Exibir resposta
$response = json_decode($response, true);
$status = $response['status'] ?? '';
$carencia = $response['carencia'] ?? false;

$message = '';
$color = '';


if($status != 200) {
    $message = $response['message'] ?? 'Notamos que seu plano expirou. Não perca tempo! Renove agora e continue aproveitando todos os benefícios dos nossos serviços. Estamos ansiosos para continuar atendendo você!';
    $color = 'red';
} elseif($carencia) {
    $message = $response['message'] ?? 'Seu plano está em carência e queremos garantir que você continue aproveitando todos os nossos serviços. Por favor, entre em contato com nosso suporte para mais informações e orientações. Estamos aqui para ajudar!';
    $color = 'orange';
}
?>

<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema de Notificação</title>
    <style>
        .notification-bar {
            width: 100%;
            padding: 15px;
            text-align: center;
            color: white;
            position: fixed;
            top: 0;
            left: 0;
            z-index: 1000;
            display: none; /* Escondido por padrão */
        }
    </style>
</head>
<body>

<?php if ($message): ?>
    <div class="notification-bar" style="background-color: <?php echo $color; ?>;">
        <?php echo $message; ?>
    </div>
    <script>
        document.querySelector('.notification-bar').style.display = 'block';
    </script>
<?php endif; ?>

<!-- O restante do conteúdo da página -->

</body>
</html>
