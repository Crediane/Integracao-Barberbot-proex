<?php
require_once __DIR__ . '/../_context_bootstrap.php';
ini_set('display_errors', 1);
error_reporting(E_ALL);
require_once $_SERVER['DOCUMENT_ROOT'].'/sistema/painel/verificar.php';
require_once $_SERVER['DOCUMENT_ROOT'].'/../../../conexao.php';

$id = $_POST['id'] ?? null;
$nome = $_POST['nome'] ?? '';
$slug = $_POST['slug'] ?? '';
$descricao = $_POST['descricao'] ?? '';
$ordem = $_POST['ordem'] ?? 0;
$ativo = $_POST['ativo'] ?? 1;

// upload do ícone
$icone = $_FILES['icone']['name'] ?? '';
$caminho = '';
if (!empty($icone)) {
  $ext = pathinfo($icone, PATHINFO_EXTENSION);
  $nome_arquivo = 'cat_'.uniqid().'.'.$ext;
  $dest = $_SERVER['DOCUMENT_ROOT'].'/sistema/uploads_banners/'.$nome_arquivo;
  if(move_uploaded_file($_FILES['icone']['tmp_name'], $dest)){
    $caminho = 'uploads_banners/'.$nome_arquivo;
  }
}

// cria tabela se não existir
$pdo->exec("CREATE TABLE IF NOT EXISTS categoria_site (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  slug VARCHAR(100) NOT NULL UNIQUE,
  descricao TEXT NULL,
  icone VARCHAR(255) NULL,
  ordem INT DEFAULT 0,
  ativo TINYINT(1) DEFAULT 1,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// atualiza ou insere
try {
  if ($id) {
    $sql = "UPDATE categoria_site SET nome=:nome, slug=:slug, descricao=:descricao, ordem=:ordem, ativo=:ativo";
    if ($caminho) $sql .= ", icone=:icone";
    $sql .= " WHERE id=:id";

    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':nome', $nome);
    $stmt->bindValue(':slug', $slug);
    $stmt->bindValue(':descricao', $descricao);
    $stmt->bindValue(':ordem', $ordem);
    $stmt->bindValue(':ativo', $ativo);
    if ($caminho) $stmt->bindValue(':icone', $caminho);
    $stmt->bindValue(':id', $id);
  } else {
    $stmt = $pdo->prepare("INSERT INTO categoria_site (nome, slug, descricao, icone, ordem, ativo) VALUES (:nome, :slug, :descricao, :icone, :ordem, :ativo)");
    $stmt->bindValue(':nome', $nome);
    $stmt->bindValue(':slug', $slug);
    $stmt->bindValue(':descricao', $descricao);
    $stmt->bindValue(':icone', $caminho);
    $stmt->bindValue(':ordem', $ordem);
    $stmt->bindValue(':ativo', $ativo);
  }

  $stmt->execute();
  echo "Salvo com Sucesso";
} catch (PDOException $e) {
  echo "Erro: ". $e->getMessage();
}
