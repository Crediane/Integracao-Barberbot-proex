<?php
require_once __DIR__ . '/../_context_bootstrap.php';
/**
 * Exclusão de Funcionário
 * Mantém padrão do sistema
 * Compatível com:
 * - /painel/funcionarios (MASTER)
 * - /loja/{slug}/painel/funcionarios (TENANT)
 */

require_once __DIR__ . '/../../../conexao.php';

$tabela = 'usuarios';

// Sanitização
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
	echo 'Erro ao Excluir!';
	exit;
}

try {

	// Verifica se existe
	$stmt = $pdo->prepare("SELECT id, foto FROM {$tabela} WHERE id = :id LIMIT 1");
	$stmt->bindValue(':id', $id, PDO::PARAM_INT);
	$stmt->execute();
	$dado = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!$dado) {
		echo 'Erro ao Excluir!';
		exit;
	}

	$foto = $dado['foto'] ?? '';

	// Exclui do banco
	$stmtDel = $pdo->prepare("DELETE FROM {$tabela} WHERE id = :id");
	$stmtDel->bindValue(':id', $id, PDO::PARAM_INT);
	$stmtDel->execute();

	// Remove foto física se existir
	if (!empty($foto) && $foto !== 'sem-foto.jpg') {

		$caminhoFoto = __DIR__ . '/../../img/usuarios/' . $foto;

		if (file_exists($caminhoFoto)) {
			unlink($caminhoFoto);
		}
	}

	echo 'Excluído com Sucesso';

} catch (Exception $e) {

	echo 'Erro ao Excluir!';
}