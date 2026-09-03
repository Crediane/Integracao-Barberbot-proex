<?php
$bb_ajax_context_mode = 'tenant';
$bb_ajax_context_response = 'plain';
require_once __DIR__ . '/_context_bootstrap.php';

if (!isset($_POST['id']) || empty($_POST['id'])) {
    echo "ID do agendamento não informado.";
    exit();
}

$id = $_POST['id'];

// Consulta o agendamento pelo ID
$query = $pdo->prepare("SELECT * FROM agendamentos WHERE id = :id");
$query->bindParam(":id", $id, PDO::PARAM_INT);
$query->execute();
$res = $query->fetch(PDO::FETCH_ASSOC);

if (!$res) {
    echo "Agendamento não encontrado.";
    exit();
}

// Salva os dados do agendamento na sessão para edição
$_SESSION['editar_agendamento'] = [
    'id' => $res['id'],
    'servico' => $res['servico'],
    'funcionario' => $res['funcionario'],
    'data' => $res['data'],
    'hora' => $res['hora']
];

// EXCLUI AGENDAMENTO ANTIGO PARA LIBERAR HORÁRIO
$stmt = $pdo->prepare("DELETE FROM agendamentos WHERE id = :id");
$stmt->bindParam(':id', $id, PDO::PARAM_INT);
$stmt->execute();

$stmt2 = $pdo->prepare("DELETE FROM horarios_agd WHERE agendamento = :id");
$stmt2->bindParam(':id', $id, PDO::PARAM_INT);
$stmt2->execute();

// Redireciona para a tela de agendamento
$destino = '/loja/' . rawurlencode($bb_ajax_context['slug']) . '/agendamentos/';
header('Location: ' . $destino);
exit();
