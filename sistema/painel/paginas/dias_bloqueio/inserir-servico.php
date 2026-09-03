<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'dias_bloqueio';

$data = $_POST['data'];
$func = $_POST['id'];

// Use prepared statements para evitar SQL Injection
$query = $pdo->prepare("SELECT * FROM $tabela WHERE usuario = :usuario AND data = :data");
$query->bindValue(':usuario', $func, PDO::PARAM_INT);
$query->bindValue(':data', $data, PDO::PARAM_STR);
$query->execute();

$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = count($res);

if ($total_reg > 0) {
    echo 'Data já adicionada!';
    exit();
}

$insert = $pdo->prepare("INSERT INTO $tabela (data, funcionario, usuario) VALUES (:data, 0, :usuario)");
$insert->bindValue(':data', $data, PDO::PARAM_STR);
$insert->bindValue(':usuario', $func, PDO::PARAM_INT);
$insert->execute();

echo 'Salvo com Sucesso';
?>