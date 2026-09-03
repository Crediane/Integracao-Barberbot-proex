<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';

$pergunta = trim($_POST['pergunta']);
$resposta = trim($_POST['resposta']);
$id = $_POST['id'] ?? '';

if ($pergunta == '' || $resposta == '') {
    echo 'Preencha todos os campos!';
    exit();
}

try {
    if ($id == '') {
        $query = $pdo->prepare("SELECT COUNT(*) FROM faq WHERE pergunta = :pergunta");
        $query->bindValue(":pergunta", $pergunta);
        $query->execute();
        if ($query->fetchColumn() > 0) {
            echo 'Pergunta já cadastrada!';
            exit();
        }

        $query = $pdo->prepare("INSERT INTO faq (pergunta, resposta, criado_em) VALUES (:pergunta, :resposta, :criado_em)");
    } else {
        $query = $pdo->prepare("UPDATE faq SET pergunta = :pergunta, resposta = :resposta WHERE id = :id");
        $query->bindValue(":id", $id);
    }

    $query->bindValue(":pergunta", $pergunta);
    $query->bindValue(":resposta", $resposta);
    if ($id == '') {
        $query->bindValue(":criado_em", date('Y-m-d H:i:s'));
    }
    $query->execute();

    echo 'Salvo com sucesso!';
} catch (PDOException $e) {
    echo 'Erro ao salvar!';
}
