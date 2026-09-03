<?php
require_once __DIR__ . '/../_context_bootstrap.php';

// Guarda slug na sessão (ajuda includes/conexão a escolher o banco da loja)
if (!empty($__bb_reqSlug) && empty($_SESSION['loja_slug'])) {
  $_SESSION['loja_slug'] = $__bb_reqSlug;
}

require_once("../../../conexao.php");
$id = @$_POST['id'];


$pdo->query("DELETE from disparos where campanha = '$id'");
echo 'Parado com Sucesso';
?>