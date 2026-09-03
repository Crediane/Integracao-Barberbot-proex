<?php
// Recebe os dados brutos (JSON) enviados pelo Menuia
$json_recebido = file_get_contents('php://input');

// Decodifica o JSON para o PHP entender
$dados = json_decode($json_recebido, true);

// Salva tudo num arquivo de texto para a gente analisar a estrutura dos dados
file_put_contents('log_menuia.txt', $json_recebido . PHP_EOL . "----------------" . PHP_EOL, FILE_APPEND);

// Responde com status 200 pro Menuia não achar que deu erro e ficar tentando reenviar
http_response_code(200);
echo "Recebido com sucesso pelo BarberBot!";
?>