<?php
// ajax/enviar-email.php
header('Content-Type: text/plain; charset=UTF-8');

$bb_ajax_context_mode = 'tenant';
$bb_ajax_context_response = 'plain';
require_once __DIR__ . '/_context_bootstrap.php';
// $pdo, $nome_sistema, $email_sistema, $url_sistema
require_once __DIR__ . '/../config/recaptcha.php';
$ver = recaptcha_verify($_POST['recaptcha_token'] ?? '', 'contato');
if(!$ver['ok']){ echo 'Validação reCAPTCHA falhou. Tente novamente.'; exit; }


// ====== CONFIG ======
$data_hoje_tenant = date('Y-m-d');
$tabela       = 'clientes';
$senha_padrao = '123';
// ====================

// -------- Helpers --------
function limpar_nome($s){ $s = trim($s ?? ''); return strip_tags($s); }
function limpar_msg($s){ $s = trim($s ?? ''); return strip_tags($s); }
function has_column(PDO $pdo, string $table, string $col): bool {
  try {
    $db = $pdo->query('SELECT DATABASE()')->fetchColumn();
    $st = $pdo->prepare('SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = :db AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1');
    $st->execute([':db'=>$db, ':t'=>$table, ':c'=>$col]);
    return (bool)$st->fetchColumn();
  } catch(Throwable $e){ return false; }
}

// -------- Entrada / Sanitização --------
$nome         = limpar_nome($_POST['nome'] ?? '');
$telefoneIn   = trim($_POST['telefone'] ?? '');
$email        = trim($_POST['email'] ?? '');
$mensagemCli  = limpar_msg($_POST['mensagem'] ?? '');
$profissional = isset($_POST['profissional']) ? (int)$_POST['profissional'] : 0;

// Normaliza telefone com 1 espaço entre partes
$telefoneFmt = preg_replace('/\s+/', ' ', $telefoneIn);

// Validação do telefone (formato (DD) 99999-9999)
if (!preg_match('/^\(\d{2}\) \d{5}-\d{4}$/', $telefoneFmt)) {
  echo 'Telefone inválido!';
  exit;
}

// Validação e-mail (se informado)
if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
  echo 'E-mail inválido!';
  exit;
}

// -------- Duplicidade por telefone (ignora máscara) --------
try {
  $telDigits = preg_replace('/\D+/', '', $telefoneFmt); // só dígitos para comparar
  $sqlDup = "
    SELECT id FROM {$tabela}
    WHERE REPLACE(REPLACE(REPLACE(REPLACE(telefone,'(',''),')',''),'-',''),' ','') = :tel
    LIMIT 1
  ";
  $st = $pdo->prepare($sqlDup);
  $st->execute([':tel' => $telDigits]);
  if ($st->fetch(PDO::FETCH_ASSOC)) {
    echo 'Telefone já Cadastrado, você já está cadastrado!!';
    exit;
  }
} catch (Throwable $e) {
  echo 'Erro ao validar telefone.';
  exit;
}

// -------- Inserção mínima obrigatória --------
try {
  $senha_hash = password_hash($senha_padrao, PASSWORD_DEFAULT);
  $ins = $pdo->prepare("
    INSERT INTO {$tabela}
      SET nome=:n, telefone=:t, data_cad=:data_cad,
          cartoes='0', alertado='Não', senha_crip=:s
  ");
  $ins->bindValue(':n', $nome);
  $ins->bindValue(':t', $telefoneFmt);
  $ins->bindValue(':data_cad', $data_hoje_tenant);
  $ins->bindValue(':s', $senha_hash);
  $ins->execute();
  $id_cliente = $pdo->lastInsertId();
} catch (Throwable $e) {
  echo 'Erro ao salvar no banco.';
  exit;
}

// -------- Atualiza campos opcionais se existirem --------
try {
  $campos = [];
  $params = [':id' => $id_cliente];
  if ($email !== '' && has_column($pdo, $tabela, 'email')) {
    $campos[] = 'email = :e';
    $params[':e'] = $email;
  }
  if ($mensagemCli !== '' && has_column($pdo, $tabela, 'observacao')) {
    $campos[] = 'observacao = :m';
    $params[':m'] = $mensagemCli;
  }
  if ($profissional && has_column($pdo, $tabela, 'id_profissional')) {
    $campos[] = 'id_profissional = :p';
    $params[':p'] = $profissional;
  }
  if ($campos) {
    $sqlUp = "UPDATE {$tabela} SET ".implode(', ', $campos)." WHERE id = :id";
    $up = $pdo->prepare($sqlUp);
    $up->execute($params);
  }
} catch (Throwable $e) {
  // Silencioso — não quebra o fluxo
}

// ====== WHATSAPP ======
// Telefone para API: 55DD999999999
$telefone_api = '55' . $telDigits;

// Link de acesso da barbearia confirmada pelo contexto tenant
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
  $scheme = 'https://';
}
$host = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
$pathAcesso = ($bb_ajax_context['explicit_slug'] !== '')
  ? '/loja/' . rawurlencode($bb_ajax_context['slug']) . '/sistema/acesso'
  : '/sistema/acesso';
$linkAcesso = ($host !== '') ? ($scheme . $host . $pathAcesso) : rtrim((string)($url_sistema ?? ''), '/') . '/acesso';

// Mensagem
$ns = $nome_sistema ?? 'Jacy Cabeleireiro';
$msgZap  = "👋 *Olá {$nome}*, seja bem-vindo(a) ao *{$ns}*!\n\n";
$msgZap .= "🔒 *Use seu WhatsApp e a senha de acesso:* {$senha_padrao}\n\n";
$msgZap .= "🌐 *Acesse seu painel:*\n{$linkAcesso}\n";
if ($profissional) $msgZap .= "\n👤 Profissional escolhido (ID): {$profissional}";
if ($mensagemCli)  $msgZap .= "\n\n📝 Mensagem: {$mensagemCli}";

// Compatibilidade com APIs que esperam $telefone e $mensagem
$telefone = $telefone_api;
$mensagem = $msgZap;

// Localiza e inclui a API
$apiCandidates = [
  $_SERVER['DOCUMENT_ROOT'] . '/ajax/api-texto.php',
  __DIR__ . '/api-texto.php',
  __DIR__ . '/../ajax/api-texto.php',
  dirname(__DIR__) . '/ajax/api-texto.php',
];
foreach ($apiCandidates as $apiPath) {
  if (is_file($apiPath)) {
    require_once $apiPath;
    break;
  }
}
// Se a API expõe uma função, chama explicitamente
if (function_exists('enviarWhats')) {
  @enviarWhats($telefone_api, $msgZap); // assinatura comum (to, msg)
}

// ====== E-MAIL ======
if (!empty($email_sistema)) {
  $to   = $email_sistema;
  $subj = 'Contato - ' . ($nome_sistema ?? 'Site');
  // cabeçalhos UTF-8
  $headers = "MIME-Version: 1.0\r\n";
  $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
  $headers .= "From: {$email_sistema}\r\n";
  if ($email) $headers .= "Reply-To: {$email}\r\n";

  $body  = "Nome: {$nome}\r\n";
  $body .= "Telefone: {$telefoneFmt}\r\n";
  if ($email)        $body .= "E-mail: {$email}\r\n";
  if ($profissional) $body .= "Profissional ID: {$profissional}\r\n";
  if ($mensagemCli)  $body .= "\r\nMensagem:\r\n{$mensagemCli}\r\n";

  @mail($to, $subj, $body, $headers);
}

// Resposta para o front-end
echo 'Enviado com Sucesso';
