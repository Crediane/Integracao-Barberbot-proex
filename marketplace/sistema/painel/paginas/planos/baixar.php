<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
$id_usuario = $_SESSION['id'];
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'assinaturas';

$id = $_POST['id'];
$valor = $_POST['valor'];
$valor = str_replace(',', '.', $valor);
$data_pgto = $_POST['data_pgto'];
$forma_pgto = $_POST['pgto'];

require_once __DIR__ . '/aprovar_plano.php';

echo 'Baixado com Sucesso';
 ?>