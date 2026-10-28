<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'dias';

// ==============================
// ENTRADAS (sem mudar a lógica)
// ==============================
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;                 // id do funcionário
$id_dias = isset($_POST['id_d']) ? trim((string)$_POST['id_d']) : ''; // id do registro (dias.id)

$dias = trim((string)($_POST['dias'] ?? ''));
$inicio = trim((string)($_POST['inicio'] ?? ''));
$final = trim((string)($_POST['final'] ?? ''));
$inicio_almoco = trim((string)($_POST['inicio_almoco'] ?? ''));
$final_almoco = trim((string)($_POST['final_almoco'] ?? ''));

// ==============================
// VALIDAÇÕES BÁSICAS
// ==============================
if ($id <= 0) {
	echo 'Funcionário inválido!';
	exit();
}

if ($dias === '') {
	echo 'Informe o dia!';
	exit();
}

if ($inicio === '' || $final === '') {
	echo 'Informe o horário de início e final!';
	exit();
}

// ✅ MESMA LÓGICA DO SISTEMA: almoço vazio = 00:00:00 (para funcionar com "Não Lançado")
$inicio_almoco_db = ($inicio_almoco === '') ? '00:00:00' : $inicio_almoco;
$final_almoco_db  = ($final_almoco === '')  ? '00:00:00' : $final_almoco;

try {

	// ==============================
	// MESMA LÓGICA: INSERT OU UPDATE
	// ==============================
	if ($id_dias === '') {

		$sql = "INSERT INTO $tabela 
				(dia, inicio, final, funcionario, inicio_almoco, final_almoco)
				VALUES
				(:dia, :inicio, :final, :funcionario, :inicio_almoco, :final_almoco)";

		$stmt = $pdo->prepare($sql);
		$ok = $stmt->execute([
			':dia'           => $dias,
			':inicio'        => $inicio,
			':final'         => $final,
			':funcionario'   => $id,
			':inicio_almoco' => $inicio_almoco_db,
			':final_almoco'  => $final_almoco_db,
		]);

	} else {

		$id_dias_int = (int)$id_dias;
		if ($id_dias_int <= 0) {
			echo 'ID do dia inválido!';
			exit();
		}

		$sql = "UPDATE $tabela SET
					dia = :dia,
					inicio = :inicio,
					final = :final,
					funcionario = :funcionario,
					inicio_almoco = :inicio_almoco,
					final_almoco = :final_almoco
				WHERE id = :id_dias";

		$stmt = $pdo->prepare($sql);
		$ok = $stmt->execute([
			':dia'           => $dias,
			':inicio'        => $inicio,
			':final'         => $final,
			':funcionario'   => $id,
			':inicio_almoco' => $inicio_almoco_db,
			':final_almoco'  => $final_almoco_db,
			':id_dias'       => $id_dias_int,
		]);
	}

	if ($ok) {
		echo 'Salvo com Sucesso';
	} else {
		echo 'Erro ao Salvar!';
	}

} catch (Throwable $e) {
	// ✅ não expõe erro interno do banco em produção
	echo 'Erro ao Salvar!';
}
