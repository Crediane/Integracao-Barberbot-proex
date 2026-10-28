<?php
require __DIR__ . '/bootstrap.php';
require_tenant();

try {
  $st = $pdo->query("SELECT id, nome, preco, duracao FROM servicos ORDER BY nome ASC");
  $rows = $st->fetchAll();
  json_response(['ok' => true, 'data' => $rows]);
} catch (Throwable $e) {
  json_error('Erro ao listar serviços.', 500);
}
