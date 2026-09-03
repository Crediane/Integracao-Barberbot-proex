<?php
// /api/lib/response.php

function json_response(array $data, int $status = 200): void {
  http_response_code($status);
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode($data, JSON_UNESCAPED_UNICODE);
  exit;
}

function json_error(string $message, int $status = 400, array $extra = []): void {
  json_response(['ok' => false, 'error' => $message] + $extra, $status);
}
