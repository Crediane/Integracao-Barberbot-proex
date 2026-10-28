<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';

$quantidade = isset($_POST['quant']) ? (float)$_POST['quant'] : 0;
$produto    = isset($_POST['produto']) ? (int)$_POST['produto'] : 0;

$query = $pdo->query("SELECT * FROM produtos WHERE id = '$produto'");
$res   = $query->fetchAll(PDO::FETCH_ASSOC);

if (@count($res) > 0) {
  $valor = (float)$res[0]['valor_venda'];
  echo $valor * $quantidade; // mantém a mesma saída numérica
} else {
  echo 0; // evita warning e mantém a lógica de “não achou → total 0”
}

 