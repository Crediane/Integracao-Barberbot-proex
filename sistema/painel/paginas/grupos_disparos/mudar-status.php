<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
$tabela = 'grupos_disparos';
require_once __DIR__ . '/../../../conexao.php';

// Recebe os dados
$id = $_POST['id'] ?? 0;
$acao = $_POST['acao'] ?? 'Não';

// Valida id
if(!$id){
    echo "ID inválido";
    exit();
}

// Ajusta valor correto de ativo
$novo_status = ($acao == 'Sim') ? 'Sim' : 'Não';

// Atualiza status
try {
    $pdo->query("UPDATE grupos_disparos SET ativo = '$novo_status' WHERE id = '$id'");
    echo "Alterado";
} catch (Exception $e) {
    echo "Erro: " . $e->getMessage();
}
?>
