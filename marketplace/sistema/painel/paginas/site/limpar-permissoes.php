<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';

$id_usuario = $_POST['id'];

$pdo->query("DELETE FROM usuarios_permissoes where usuario = '$id_usuario'");

?>