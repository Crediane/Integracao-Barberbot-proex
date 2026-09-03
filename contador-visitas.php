<?php
// contador-visitas.php - Sistema de contagem de visitas
session_start();

// Define o arquivo de contador
$arquivo_contador = __DIR__ . '/logs/contador-visitas.txt';
$dir_logs = dirname($arquivo_contador);

// Cria diretório se não existir
if (!is_dir($dir_logs)) {
    @mkdir($dir_logs, 0755, true);
}

// Inicializa contador se não existir
if (!file_exists($arquivo_contador)) {
    file_put_contents($arquivo_contador, '1000'); // Começa com 1000 visitas
}

// Lê contador atual
$visitas = (int)file_get_contents($arquivo_contador);

// Incrementa apenas se for nova sessão
if (!isset($_SESSION['visitou'])) {
    $visitas++;
    file_put_contents($arquivo_contador, $visitas);
    $_SESSION['visitou'] = true;
}

// Log para debug
error_log("Contador de visitas: " . $visitas);

// Retorna JSON
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
echo json_encode([
    'visitas' => $visitas,
    'formatado' => number_format($visitas, 0, ',', '.'),
    'timestamp' => time()
]);
?>