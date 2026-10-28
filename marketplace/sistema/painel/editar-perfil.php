<?php
// /public_html/sistema/painel/editar-perfil.php
// ===== Sessão isolada por contexto (MASTER x TENANT) =====
// Objetivo: impedir cruzamento de dados quando ADMIN e LOJA (ou múltiplas lojas) logam no mesmo navegador.
// Regra:
// - /sistema (link normal, sem /loja/ e sem ?loja=) => BB_MASTER
// - /loja/<slug>/... OU ?loja=...                   => BB_TENANT_<hash_slug> (uma sessão por loja)
// - demais                                          => BB_SITE

if (!function_exists('bb_sess_clean_slug')) {
  function bb_sess_clean_slug($v) {
    $v = strtolower(trim((string)$v));
    return preg_replace('/[^a-z0-9_-]/', '', $v);
  }
}

function bb_extrair_slug_loja_por_path($path) {
  $path = (string)$path;
  if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $path, $m)) {
    return bb_sess_clean_slug($m[1]);
  }
  return '';
}

$__bb_reqPath = parse_url(isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/', PHP_URL_PATH);
$__bb_reqPath = $__bb_reqPath ? $__bb_reqPath : '/';

// NÃO usar HTTP_REFERER para decidir sessão (referer pode vir do tenant e "puxar" o admin pro banco errado)
$__bb_lojaByPath = (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath);
$__bb_isSistema  = (bool)preg_match('~^/sistema(?:/|$)~i', $__bb_reqPath);

// Captura o slug do tenant (URL bonita /loja/<slug> OU ?loja=... OU POST)
$loja = '';
if (!empty($_GET['loja'])) {
  $loja = bb_sess_clean_slug($_GET['loja']);
} elseif (!empty($_POST['loja'])) {
  $loja = bb_sess_clean_slug($_POST['loja']);
} else {
  $loja = bb_extrair_slug_loja_por_path($__bb_reqPath);
}

// Determina sessão desejada
$__bb_forceMaster = ($__bb_isSistema && !$__bb_lojaByPath && empty($loja));
$__bb_hasLoja = ($loja !== '') || $__bb_lojaByPath;

$__bb_tenantSessionName = 'BB_TENANT';
if ($loja !== '') {
  $__bb_tenantSessionName = 'BB_TENANT_' . strtoupper(substr(sha1($loja), 0, 12));
}

$__bb_desiredSession = $__bb_forceMaster ? 'BB_MASTER' : ($__bb_hasLoja ? $__bb_tenantSessionName : 'BB_SITE');

// Inicia/ajusta a sessão corretamente (usa o cookie do nome escolhido)
if (session_status() === PHP_SESSION_ACTIVE) {
  if (session_name() !== $__bb_desiredSession) {
    session_write_close();
    @session_name($__bb_desiredSession);
    if (!empty($_COOKIE[$__bb_desiredSession])) {
      @session_id((string)$_COOKIE[$__bb_desiredSession]);
    }
    @session_start();
  }
} else {
  @session_name($__bb_desiredSession);
  if (!empty($_COOKIE[$__bb_desiredSession])) {
    @session_id((string)$_COOKIE[$__bb_desiredSession]);
  }
  @session_start();
}

// Mantém sessão e GET somente no contexto TENANT (compatibilidade com conexao.php)
if ($loja !== '' && $__bb_desiredSession !== 'BB_MASTER') {
  $_SESSION['loja'] = $loja;
  $_SESSION['tenant_slug'] = $loja;
  $_GET['loja'] = $loja;
}

// Se for MASTER, garante que não "grude" tenant antigo
if ($__bb_desiredSession === 'BB_MASTER') {
  unset($_SESSION['loja'], $_SESSION['tenant_slug']);
  unset($_GET['loja']);
}

require_once(__DIR__ . '/../conexao.php');


$id          = $_POST['id'] ?? '';
$nome        = $_POST['nome'] ?? '';
$email       = $_POST['email'] ?? '';
$telefone    = $_POST['telefone'] ?? '';
$cpf         = $_POST['cpf'] ?? '';
$senha       = $_POST['senha'] ?? '';
$conf_senha  = $_POST['conf_senha'] ?? '';
$endereco    = $_POST['endereco'] ?? '';
$atendimento = $_POST['atendimento'] ?? '';
$intervalo   = $_POST['intervalo'] ?? '';

$id = (int)$id;
$nome = trim((string)$nome);
$email = trim((string)$email);
$telefone = trim((string)$telefone);
$cpf = trim((string)$cpf);
$endereco = trim((string)$endereco);
$atendimento = trim((string)$atendimento);
$intervalo = trim((string)$intervalo);

// ==============================
// Senha (corrigido sem mudar a regra central)
// - Só altera a senha se o usuário preencher os campos
// - Se deixar em branco, mantém a senha atual
// ==============================
$alterarSenha = (trim((string)$senha) !== '' || trim((string)$conf_senha) !== '');
$senha_crip = null; // null = não alterar

if ($alterarSenha) {
  if ($senha != $conf_senha) {
    echo 'As senhas são diferentes!!';
    exit();
  }
  // Criptografa a senha nova
  $senha_crip = password_hash((string)$senha, PASSWORD_DEFAULT);
}
// ==============================
// Pasta de upload (corrige path, sem mudar lógica)
// - uso de __DIR__ evita erro de caminho quando o PHP muda o CWD
// ==============================
$dir_upload = __DIR__ . '/img/perfil/';
if (!is_dir($dir_upload)) {
  @mkdir($dir_upload, 0755, true);
}

// 3. Buscar foto existente (mantém lógica)
$foto = 'sem-foto.jpg';
try {
  $stU = $pdo->prepare("SELECT foto FROM usuarios WHERE id = :id LIMIT 1");
  $stU->bindValue(':id', $id, PDO::PARAM_INT);
  $stU->execute();
  $u = $stU->fetch(PDO::FETCH_ASSOC);

  if ($u && !empty($u['foto'])) {
    $foto = (string)$u['foto'];
  }
} catch (Throwable $e) {
  $foto = 'sem-foto.jpg';
}

// 1. Validar email (mantém lógica)
try {
  $stEmail = $pdo->prepare("SELECT id FROM usuarios WHERE email = :email LIMIT 1");
  $stEmail->bindValue(':email', $email);
  $stEmail->execute();
  $rowEmail = $stEmail->fetch(PDO::FETCH_ASSOC);

  if ($rowEmail && $id != (int)$rowEmail['id']) {
    echo 'Email já Cadastrado, escolha outro!!';
    exit();
  }
} catch (Throwable $e) {
  echo 'Erro ao validar email!';
  exit();
}

// 2. Validar cpf (mantém lógica)
try {
  $stCpf = $pdo->prepare("SELECT id FROM usuarios WHERE cpf = :cpf LIMIT 1");
  $stCpf->bindValue(':cpf', $cpf);
  $stCpf->execute();
  $rowCpf = $stCpf->fetch(PDO::FETCH_ASSOC);

  if ($rowCpf && $id != (int)$rowCpf['id']) {
    echo 'CPF já Cadastrado, escolha outro!!';
    exit();
  }
} catch (Throwable $e) {
  echo 'Erro ao validar CPF!';
  exit();
}

// 4. Upload de Foto (mantém lógica) - ✅ corrigido para não falhar por caminho/erro de upload
// Suporta o campo padrão 'foto' e também 'imagem' (sem quebrar o sistema)
$bb_file_key = '';
if (isset($_FILES['foto'])) {
  $bb_file_key = 'foto';
} elseif (isset($_FILES['imagem'])) {
  $bb_file_key = 'imagem';
}

if ($bb_file_key !== '' && !empty($_FILES[$bb_file_key]['name'])) {

  if (!is_dir($dir_upload) || !is_writable($dir_upload)) {
    echo 'Erro ao enviar a imagem! Pasta img/perfil sem permissão.';
    exit();
  }

  $erro_upload = (int)($_FILES[$bb_file_key]['error'] ?? UPLOAD_ERR_OK);
  if ($erro_upload !== UPLOAD_ERR_OK) {
    echo 'Erro ao enviar a imagem! (Falha no upload)';
    exit();
  }

  $nome_original = (string)($_FILES[$bb_file_key]['name'] ?? '');
  $nome_original = basename($nome_original);

  $nome_img = date('d-m-Y_H-i-s') . '-' . $nome_original;

  // sanitiza o nome do arquivo (mantém a lógica de renomear, só evita caracteres inválidos)
  $nome_img = preg_replace('/[^A-Za-z0-9._-]/', '-', $nome_img);
  $nome_img = preg_replace('/-+/', '-', $nome_img);

  $imagem_temp = (string)($_FILES[$bb_file_key]['tmp_name'] ?? '');

  $ext = strtolower((string)pathinfo($nome_img, PATHINFO_EXTENSION));

  if ($ext == 'png' || $ext == 'jpg' || $ext == 'jpeg' || $ext == 'gif') {

    // Exclui a foto anterior (mantém lógica)
    if ($foto != "sem-foto.jpg") {
      @unlink($dir_upload . $foto);
    }

    $destino = $dir_upload . $nome_img;

    if (!@move_uploaded_file($imagem_temp, $destino)) {
      echo 'Erro ao enviar a imagem!';
      exit();
    }

    $foto = $nome_img;

  } else {
    echo 'Extensão de Imagem não permitida!';
    exit();
  }
}

// 5. Atualização no Banco (mantém lógica)
try {
  $sql = "
    UPDATE usuarios
       SET nome = :nome,
           email = :email,
           telefone = :telefone,
           cpf = :cpf,
           endereco = :endereco,
           foto = :foto,
           atendimento = :atendimento,
           intervalo = :intervalo
";

if ($alterarSenha) {
  $sql .= ",
           senha = :senha,
           senha_crip = :senha_crip
  ";
}

$sql .= " WHERE id = :id";

$query = $pdo->prepare($sql);

$query->bindValue(':nome', $nome);
$query->bindValue(':email', $email);
$query->bindValue(':telefone', $telefone);
$query->bindValue(':cpf', $cpf);
$query->bindValue(':endereco', $endereco);
$query->bindValue(':foto', $foto);
$query->bindValue(':atendimento', $atendimento);
$query->bindValue(':intervalo', $intervalo);
$query->bindValue(':id', $id, PDO::PARAM_INT);

if ($alterarSenha) {
  // Mantém padrão do sistema: não guardar senha visível
  $query->bindValue(':senha', '');
  $query->bindValue(':senha_crip', (string)$senha_crip);
}

$query->execute();
echo 'Editado com Sucesso';
} catch (Throwable $e) {
  echo 'Erro ao editar!';
}
?>
