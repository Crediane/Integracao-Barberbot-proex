<?php
require_once __DIR__ . '/../_context_bootstrap.php';
// /public_html/sistema/painel/paginas/dispositivos/testar.php
// Endpoint para testar envio WhatsApp (Menuia) e retornar JSON confirmando sucesso.
// ✅ Não muda a regra de negócio do envio: apenas reaproveita /ajax/api-texto.php
// ✅ Compatível com multi-tenant via ?loja= (o conexao.php já resolve o banco correto)

header('Content-Type: application/json; charset=utf-8');

// Sanitização básica
function bb_clean_slug($v) {
  $v = strtolower(trim((string)$v));
  return preg_replace('/[^a-z0-9_-]/', '', $v);
}
function bb_digits($v) {
  return preg_replace('/\D+/', '', (string)$v);
}

$loja = '';
if (!empty($_GET['loja'])) $loja = bb_clean_slug($_GET['loja']);
if (!$loja && !empty($_POST['loja'])) $loja = bb_clean_slug($_POST['loja']);
if ($loja !== '' && empty($_GET['loja'])) {
  // força para o bootstrap reconhecer tenant
  $_GET['loja'] = $loja;
}

$telefone = '';
if (!empty($_POST['telefone'])) $telefone = bb_digits($_POST['telefone']);
if (!$telefone && !empty($_GET['telefone'])) $telefone = bb_digits($_GET['telefone']);

if ($telefone === '' || strlen($telefone) < 10) {
  echo json_encode(['ok' => false, 'message' => 'Telefone inválido para teste.']);
  exit;
}

$conexao = __DIR__ . '/../../../conexao.php';
$apiTexto = __DIR__ . '/../../../../ajax/api-texto.php';

if (!is_file($conexao)) {
  echo json_encode(['ok' => false, 'message' => 'conexao.php não encontrado em /sistema/.']);
  exit;
}
if (!is_file($apiTexto)) {
  echo json_encode(['ok' => false, 'message' => 'api-texto.php não encontrado em /ajax/.']);
  exit;
}

require_once $conexao;

// Mensagem padrão (curta)
$mensagem = '✅ Teste BarberBot: disparo WhatsApp OK - ' . date('d/m/Y H:i');

$api = $api ?? '';
$instancia = $instancia ?? ($instancia_whatsapp ?? '');
$token = $token ?? ($token_whatsapp ?? '');
$telefone = $telefone;
$mensagem = $mensagem;

// ativa modo echo/retorno (somente para teste)
$BB_API_TEXTO_ECHO = true;

// captura saída do api-texto (para retornar ao frontend)
ob_start();
include $apiTexto;
$out = trim((string)ob_get_clean());

if ($out === '') {
  echo json_encode(['ok' => false, 'message' => 'A API não retornou resposta. Verifique token/instância e conexão do dispositivo.', 'raw' => '']);
  exit;
}

// Se já for JSON, apenas repassa
$decoded = json_decode($out, true);
if (is_array($decoded)) {
  // garante campo ok
  if (!isset($decoded['ok'])) {
    $decoded['ok'] = (isset($decoded['status']) && (int)$decoded['status'] === 200);
  }
  echo json_encode($decoded);
  exit;
}

// Caso seja texto
$lower = strtolower($out);
$ok = (strpos($lower, 'ok') !== false) || (strpos($lower, 'sucesso') !== false) || (strpos($lower, 'envi') !== false);
echo json_encode(['ok' => $ok, 'message' => $ok ? 'Mensagem enviada.' : 'Resposta inesperada da API.', 'raw' => $out]);
