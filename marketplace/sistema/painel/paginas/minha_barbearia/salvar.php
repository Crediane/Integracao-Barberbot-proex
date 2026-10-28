<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../verificar.php';
require_once __DIR__ . '/../../conexao.php';
require_once __DIR__ . '/../../painel_master/conexao_market.php';

$slug = trim($_POST['slug'] ?? '');
$slug = strtolower($slug);
$slug = preg_replace('/[^a-z0-9_-]/', '', $slug);

if ($slug == '') {
  echo "Slug inválido!";
  exit();
}

$nome = trim($_POST['nome'] ?? '');
$cidade = trim($_POST['cidade'] ?? '');
$estado = strtoupper(trim($_POST['estado'] ?? ''));
$estado = preg_replace('/[^A-Z]/', '', $estado);
$estado = substr($estado, 0, 2);

$endereco = trim($_POST['endereco'] ?? '');
$whatsapp = trim($_POST['whatsapp'] ?? '');

if ($nome == '') {
  echo "Informe o nome da barbearia!";
  exit();
}

// busca registro do tenant
$st = $pdo_market->prepare("SELECT id, logo FROM tenants WHERE slug = :s LIMIT 1");
$st->execute([':s' => $slug]);
$tenant = $st->fetch();

if (!$tenant) {
  echo "Barbearia não encontrada!";
  exit();
}

$logo_nome = $tenant['logo'] ?? null;

// upload do logo (salva em /sistema/img/)
if (!empty($_FILES['logo']['name'])) {
  $tmp = $_FILES['logo']['tmp_name'];
  $name = $_FILES['logo']['name'];

  $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
  if (!in_array($ext, ['png','jpg','jpeg','webp'])) {
    echo "Extensão de imagem não permitida!";
    exit();
  }

  $novo = 'logo_' . $slug . '_' . date('YmdHis') . '.' . $ext;

  $imgDir = realpath(__DIR__ . '/../../../img');
  if (!$imgDir) {
    echo "Pasta de imagens não encontrada!";
    exit();
  }

  $dest = $imgDir . DIRECTORY_SEPARATOR . $novo;

  if (!move_uploaded_file($tmp, $dest)) {
    echo "Falha ao enviar a imagem!";
    exit();
  }

  $logo_nome = $novo;
}

// atualiza tenants
$st = $pdo_market->prepare("
  UPDATE tenants
  SET nome = :nome,
      cidade = :cidade,
      estado = :estado,
      endereco = :endereco,
      whatsapp = :whatsapp,
      logo = :logo
  WHERE slug = :slug
  LIMIT 1
");
$st->execute([
  ':nome' => $nome,
  ':cidade' => ($cidade !== '' ? $cidade : null),
  ':estado' => ($estado !== '' ? $estado : null),
  ':endereco' => ($endereco !== '' ? $endereco : null),
  ':whatsapp' => ($whatsapp !== '' ? $whatsapp : null),
  ':logo' => $logo_nome,
  ':slug' => $slug,
]);

echo "Salvo com Sucesso";
