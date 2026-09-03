<?php
require_once __DIR__ . '/../_context_bootstrap.php';

// Guarda slug na sessão (ajuda includes/conexão a escolher o banco da loja)
if (!empty($__bb_reqSlug) && empty($_SESSION['loja_slug'])) {
  $_SESSION['loja_slug'] = $__bb_reqSlug;
}

require_once("../../../conexao.php");

$dataMes    = date('m');
$dataDia    = date('d');
$dataAno    = date('Y');
$data_atual = date('Y-m-d');
$data_semana = date('Y-m-d', strtotime("-7 days", strtotime($data_atual)));
$id_usuario = isset($_SESSION['id']) ? $_SESSION['id'] : null;

$clientes = isset($_POST['cli']) ? $_POST['cli'] : '';

// Detectar se é grupo (ex: grupo_4)
if (substr($clientes, 0, 6) == 'grupo_') {

    $id_grupo = (int)substr($clientes, 6);

    // mesma lógica, só usando prepare para segurança
    $sql = "
        SELECT c.telefone
        FROM grupos_clientes gc
        JOIN clientes c ON gc.cliente = c.id
        WHERE gc.grupo = :grupo
          AND c.telefone != ''
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([':grupo' => $id_grupo]);
    $resultado = $stmt;

} else if ($clientes == "Teste") {

    $resultado = $pdo->query("
        SELECT telefone_whatsapp AS telefone
        FROM config
        WHERE telefone_whatsapp != ''
    ");

} else if ($clientes == "Clientes Mês" || $clientes == "Clientes Cadastrados no Último Mês") {

    $resultado = $pdo->query("
        SELECT telefone
        FROM clientes
        WHERE MONTH(data_cad) = '$dataMes'
          AND YEAR(data_cad) = '$dataAno'
          AND telefone != ''
          AND (marketing = '' OR marketing IS NULL)
    ");

} else if ($clientes == "Clientes Semana" || $clientes == "Clientes Cadastrados na Última Semana") {

    $resultado = $pdo->query("
        SELECT telefone
        FROM clientes
        WHERE data_cad >= '$data_semana'
          AND telefone != ''
          AND (marketing = '' OR marketing IS NULL)
    ");

} else if ($clientes == "Todos" || $clientes == "Todos os Clientes") {

    // AQUI estava o problema: o front envia "Todos os Clientes"
    $resultado = $pdo->query("
        SELECT telefone
        FROM clientes
        WHERE telefone != ''
          AND (marketing = '' OR marketing IS NULL)
    ");

} else if ($clientes == "Aniversariantes Mês") {

    $resultado = $pdo->query("
        SELECT telefone
        FROM clientes
        WHERE MONTH(data_nasc) = '$dataMes'
          AND telefone != ''
          AND (marketing = '' OR marketing IS NULL)
    ");

} else if ($clientes == "Aniversariantes Dia") {

    $resultado = $pdo->query("
        SELECT telefone
        FROM clientes
        WHERE MONTH(data_nasc) = '$dataMes'
          AND DAY(data_nasc) = '$dataDia'
          AND telefone != ''
          AND (marketing = '' OR marketing IS NULL)
    ");

} else if ($clientes == "Inadimplentes") {

    $resultado = $pdo->prepare("
        SELECT DISTINCT c.telefone
        FROM clientes c
        JOIN receber r ON c.id = r.pessoa
        WHERE r.data_venc < :data_hoje
          AND r.pago = 'Não'
    ");
    $resultado->execute([':data_hoje' => date('Y-m-d')]);

} else {
    // fallback de segurança: retorna 0
    echo 0;
    exit;
}

// mantém a mesma lógica de contagem
$res = @$resultado->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);

echo $total_reg;
?>
