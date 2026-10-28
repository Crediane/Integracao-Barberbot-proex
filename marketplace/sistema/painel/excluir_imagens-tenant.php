<?php
// /public_html/sistema/painel/excluir_imagens-tenant.php
// ✅ Wrapper TENANT: garante banco do TENANT quando vier ?loja=... e mantém MASTER quando não vier.
// Não altera lógica do excluir_imagens.php (apenas prepara contexto).
declare(strict_types=1);

$loja = $_GET['loja'] ?? $_POST['loja'] ?? '';
$loja = strtolower(trim((string)$loja));
$loja = preg_replace('/[^a-z0-9_-]/', '', $loja);

$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$isSistema = (strpos($reqPath, '/sistema') === 0);

$desired = ($loja !== '') ? 'BB_TENANT' : ($isSistema ? 'BB_MASTER' : 'BB_SITE');

if (session_status() === PHP_SESSION_ACTIVE) {
  if (session_name() !== $desired) {
    $oldId = session_id();
    session_write_close();
    @session_name($desired);
    @session_id($oldId);
    @session_start();
  }
} else {
  @session_name($desired);
  @session_start();
}

if ($loja !== '') {
  $_GET['loja'] = $loja;
  $_POST['loja'] = $_POST['loja'] ?? $loja;
  $_SESSION['loja'] = $loja;
  $_SESSION['tenant_slug'] = $loja;
} else {
  unset($_SESSION['loja'], $_SESSION['tenant_slug']);
  unset($_GET['loja']);
}

require __DIR__ . '/excluir_imagens.php';
