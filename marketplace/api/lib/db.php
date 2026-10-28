<?php
// /api/lib/db.php

function db_pdo(array $db): PDO {
  $charset = $db['charset'] ?? 'utf8mb4';
  $dsn = "mysql:host={$db['host']};dbname={$db['name']};charset={$charset}";

  $pdo = new PDO($dsn, $db['user'], $db['pass'], [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
  ]);

  return $pdo;
}
