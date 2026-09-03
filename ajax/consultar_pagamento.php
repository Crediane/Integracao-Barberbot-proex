<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
header('Content-Type: application/json; charset=utf-8');

$bb_ajax_context_mode = 'tenant';
$bb_ajax_context_response = 'json';
require_once __DIR__ . '/_context_bootstrap.php';

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id || $id <= 0) {
    echo json_encode(['status' => 'erro'], JSON_UNESCAPED_UNICODE);
    exit;
}

$stmt = $pdo->prepare("SELECT id FROM agendamentos WHERE id = :id AND valor_pago IS NOT NULL AND valor_pago != '0.00' LIMIT 1");
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->execute();

$status = $stmt->fetchColumn() ? 'pago' : 'aberta';
echo json_encode(['status' => $status], JSON_UNESCAPED_UNICODE);
