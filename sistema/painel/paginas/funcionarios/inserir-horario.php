<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'horarios';

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$horario = trim((string)($_POST['horario'] ?? ''));
$data = trim((string)($_POST['data'] ?? ''));

if ($id <= 0) {
	echo 'Funcionário inválido!';
	exit();
}

if ($horario === '') {
	echo 'Horário inválido!';
	exit();
}

// Normaliza: se data vier vazia, grava NULL (igual sua intenção original)
$dataDb = ($data === '') ? null : $data;

if ($dataDb === null) {
	$stmt = $pdo->prepare("INSERT INTO $tabela (horario, funcionario, data) VALUES (:horario, :funcionario, NULL)");
	$stmt->execute([
		':horario' => $horario,
		':funcionario' => $id
	]);
} else {
	$stmt = $pdo->prepare("INSERT INTO $tabela (horario, funcionario, data) VALUES (:horario, :funcionario, :data)");
	$stmt->execute([
		':horario' => $horario,
		':funcionario' => $id,
		':data' => $dataDb
	]);
}

echo 'Salvo com Sucesso';
?>
