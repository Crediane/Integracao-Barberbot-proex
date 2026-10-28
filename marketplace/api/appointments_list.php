<?php
require __DIR__ . '/bootstrap.php';
require_tenant();

try {
  $st = $pdo->query("SELECT * FROM agendamentos ORDER BY data_hora DESC LIMIT 50");
  json_response(['ok' => true, 'data' => $st->fetchAll()]);
} catch (Throwable $e) {
  json_error('Erro ao listar agendamentos.', 500);
}
