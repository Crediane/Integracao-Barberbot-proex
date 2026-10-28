<?php
$bb_ajax_context_mode = 'tenant';
$bb_ajax_context_response = 'plain';
require_once __DIR__ . '/_context_bootstrap.php';

header('Content-Type: text/plain; charset=UTF-8');
try {
  if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    // mantém 200 para o front não exibir erro de rede
    echo 'Método não permitido';
    exit;
  }

  // Campos
  $nome      = trim((string)($_POST['nome'] ?? ''));
  $telefoneF = trim((string)($_POST['telefone'] ?? ''));

  if ($nome === '' || $telefoneF === '') {
    echo 'Preencha nome e telefone';
    exit;
  }

  // Conexão e variáveis ($pdo, $nome_sistema, $url_sistema)
  require_once dirname(__DIR__) . '/sistema/conexao.php';
  require_once __DIR__ . '/timezone_tenant.php';

  if (!isset($pdo) || !($pdo instanceof PDO)) {
    echo 'Preencha nome e telefone';
    exit;
  }

  $data_hoje_tenant = date('Y-m-d');

  /* ================= reCAPTCHA v3 (soft) =================
     - Se existir config e a verificação falhar E você marcar como obrigatória,
       bloqueia. Caso contrário, segue o fluxo.                         */
  $rc_token  = (string)($_POST['recaptcha_token'] ?? '');
  $rc_action = (string)($_POST['recaptcha_action'] ?? 'cadastro');

  $recaptcha_ok = true;     // padrão: seguir cadastro
  $recaptcha_required = false; // mude para true para tornar obrigatório

  $recaptchaCfg = dirname(__DIR__) . '/config/recaptcha.php';
  if (is_file($recaptchaCfg)) {
    require_once $recaptchaCfg; // pode definir recaptcha_verify()
    if (function_exists('recaptcha_verify')) {
      $rc = recaptcha_verify($rc_token, $rc_action); // ['ok'=>bool,'score'=>float,...]
      $recaptcha_ok = !empty($rc['ok']);
    }

    // se no seu config existir uma constante que force obrigatoriedade, respeita
    if (defined('RECAPTCHA_REQUIRED') && RECAPTCHA_REQUIRED === true) {
      $recaptcha_required = true;
    }
  }

  if ($recaptcha_required && !$recaptcha_ok) {
    echo 'Falha no reCAPTCHA';
    exit;
  }
  /* ================= fim reCAPTCHA ================= */

  // Normalizações
  $telefoneDigits = preg_replace('/\D+/', '', $telefoneF); // só dígitos para comparar
  $telefoneBanco  = $telefoneF;                            // salva como veio

  // ===== Monta o link de acesso conforme o contexto =====
  $baseUrlSistema = rtrim((string)($url_sistema ?? ''), '/');

  $httpsOn = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') ||
    (!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
  );

  $hostAtual = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
  $baseUrlAtual = '';
  if ($hostAtual !== '') {
    $baseUrlAtual = ($httpsOn ? 'https://' : 'http://') . $hostAtual;
  }

  if ($__bb_reqSlug !== '') {
    $baseLink = $baseUrlAtual !== '' ? $baseUrlAtual : $baseUrlSistema;
    $linkAcesso = rtrim($baseLink, '/') . '/loja/' . $__bb_reqSlug . '/sistema/acesso';
  } else {
    $baseLink = $baseUrlSistema !== '' ? $baseUrlSistema : $baseUrlAtual;
    $linkAcesso = rtrim($baseLink, '/') . '/sistema/acesso';
  }

  // Verifica duplicidade ignorando máscara e DDI com "+"
  $sqlDup = "
    SELECT id FROM clientes
    WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefone,'(',''),')',''),'-',''),' ',''),'+','') = :tel
    LIMIT 1
  ";
  $st = $pdo->prepare($sqlDup);
  $st->execute([':tel' => $telefoneDigits]);

  if ($st->fetch(PDO::FETCH_ASSOC)) {
    echo 'Você já está Cadastrado';
    exit;
  }

  // Inserir novo cliente
  $senha      = '123';
  $senha_crip = password_hash($senha, PASSWORD_DEFAULT);

  $ins = $pdo->prepare("
    INSERT INTO clientes
      SET nome = :nome,
          telefone = :telefone,
          data_cad = :data_cad,
          cartoes = '0',
          alertado = 'Não',
          senha_crip = :senha_crip
  ");
  $ins->bindValue(':nome', $nome);
  $ins->bindValue(':telefone', $telefoneBanco);
  $ins->bindValue(':data_cad', $data_hoje_tenant);
  $ins->bindValue(':senha_crip', $senha_crip);
  $ins->execute();

  /* ===== Envio de WhatsApp (usa ajax/api-texto.php) ===== */
  // Mantém o número como foi informado.
  // O api-texto.php já normaliza:
  // - Brasil sem DDI (10/11 dígitos) => prefixa 55
  // - Internacional com DDI          => preserva
  $telefone = $telefoneF;

  $ns = $nome_sistema ?? 'Jacy Cabeleireiro';
  $mensagem  = "👋 *Olá {$nome}*, seja bem-vindo(a) ao *{$ns}*!\n\n";
  $mensagem .= "🔒 *Use seu whatsapp e senha de acesso:* 123\n\n";
  $mensagem .= "🌐 *Clique abaixo para acessar seu painel:*\n";
  $mensagem .= $linkAcesso;

  $sender = $_SERVER['DOCUMENT_ROOT'] . '/ajax/api-texto.php';
  if (is_file($sender)) {
    // esse arquivo geralmente utiliza $telefone e $mensagem existentes no escopo
    require $sender;
  }
  /* ===== fim envio ===== */

  echo 'Cadastrado com Sucesso';
  exit;

} catch (Throwable $e) {
  // Não exponha erro — mantém resposta neutra
  echo 'Preencha nome e telefone';
  exit;
}
