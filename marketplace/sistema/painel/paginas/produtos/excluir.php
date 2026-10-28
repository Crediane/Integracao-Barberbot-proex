<?php
require_once __DIR__ . '/../_context_bootstrap.php';
/**
 * Exclusão de Produto
 * Mantém lógica original do sistema
 * Corrige apenas o carregamento seguro da conexão
 */

require_once __DIR__ . '/../../../conexao.php';

$tabela = 'produtos';

// Sanitização segura
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
	echo 'Erro ao excluir.';
	exit;
}

try {

	// Buscar foto antes de excluir
	$stmt = $pdo->prepare("SELECT foto FROM {$tabela} WHERE id = :id LIMIT 1");
	$stmt->bindValue(':id', $id, PDO::PARAM_INT);
	$stmt->execute();
	$dados = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!$dados) {
		echo 'Erro ao excluir.';
		exit;
	}

	$foto = $dados['foto'] ?? '';

	// Excluir registro
	$stmtDel = $pdo->prepare("DELETE FROM {$tabela} WHERE id = :id");
	$stmtDel->bindValue(':id', $id, PDO::PARAM_INT);
	$stmtDel->execute();

	// Remove foto física se não for padrão
	if (!empty($foto) && $foto !== 'sem-foto.jpg') {
		$caminhoFoto = __DIR__ . '/../../img/produtos/' . $foto;
		if (file_exists($caminhoFoto)) {
			unlink($caminhoFoto);
		}
	}

	echo 'Excluído com Sucesso';

} catch (Exception $e) {

	echo 'Erro ao excluir.';

}