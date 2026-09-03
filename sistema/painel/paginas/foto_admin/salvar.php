<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';

$id     = isset($_POST['id']) ? trim($_POST['id']) : '';
$titulo = isset($_POST['titulo']) ? trim($_POST['titulo']) : '';
$data   = date('Y-m-d');
$pasta  = '../../img/foto_admin/';
$nome_imagem = '';

if ($titulo === '') {
    echo "Informe o título!";
    exit();
}

// Se foi enviada uma imagem
if (isset($_FILES['imagem']) && $_FILES['imagem']['name'] != "") {
    $tamanhoMaximo = 2 * 1024 * 1024; // 2MB
    if ($_FILES['imagem']['size'] > $tamanhoMaximo) {
        echo "Imagem excede o limite de 2MB!";
        exit();
    }

    $nome_original = basename($_FILES['imagem']['name']);
    $ext = strtolower(pathinfo($nome_original, PATHINFO_EXTENSION));
    $permitidas = ['jpg','jpeg','png','webp','gif'];
    if (!in_array($ext, $permitidas, true)) {
        echo "Formato de imagem inválido!";
        exit();
    }

    $nome_imagem = uniqid('foto_', true) . '.' . $ext;
    move_uploaded_file($_FILES['imagem']['tmp_name'], $pasta . $nome_imagem);
}

// INSERIR
if ($id === "") {
    if ($nome_imagem === "") {
        echo "Envie uma imagem!";
        exit();
    }

    $query = $pdo->prepare(
        "INSERT INTO foto_admin (titulo, imagem, data_cad)
         VALUES (:titulo, :imagem, :data)"
    );
    $query->bindValue(":titulo", $titulo);
    $query->bindValue(":imagem", $nome_imagem);
    $query->bindValue(":data", $data);
}
// EDITAR
else {
    $res = $pdo->prepare("SELECT imagem FROM foto_admin WHERE id = :id LIMIT 1");
    $res->bindValue(':id', $id);
    $res->execute();
    $dados = $res->fetch(PDO::FETCH_ASSOC);
    $foto_antiga = $dados ? $dados['imagem'] : '';

    if ($nome_imagem !== "") {
        if ($foto_antiga && file_exists($pasta . $foto_antiga)) {
            @unlink($pasta . $foto_antiga);
        }
    } else {
        $nome_imagem = $foto_antiga;
    }

    $query = $pdo->prepare(
        "UPDATE foto_admin
           SET titulo = :titulo, imagem = :imagem
         WHERE id = :id"
    );
    $query->bindValue(":id", $id);
    $query->bindValue(":titulo", $titulo);
    $query->bindValue(":imagem", $nome_imagem);
}

$query->execute();

echo "Salvo com sucesso!";
