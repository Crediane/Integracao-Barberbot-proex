<?php
$query = trim((string)($_SERVER['QUERY_STRING'] ?? ''));
$destino = '/marketplace.php' . ($query !== '' ? '?' . $query : '');
header('Location: ' . $destino, true, 302);
exit;
