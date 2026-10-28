<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'pagar';

// ===== Sanitização do ID (mantém a lógica, evita warnings PHP 8+) =====
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if ($id <= 0) {
	// Mantém o padrão de retorno simples do sistema
	echo 'ID inválido!';
	exit();
}

// ===== Buscar registro antes de excluir (mantém a lógica original) =====
$foto = 'sem-foto.jpg';
$produto = 0;
$quantidade = 0;

$stmt = $pdo->prepare("SELECT * FROM {$tabela} WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$registro = $stmt->fetch(PDO::FETCH_ASSOC);

if ($registro) {
	$foto = $registro['foto'] ?? 'sem-foto.jpg';
	$produto = isset($registro['produto']) ? (int)$registro['produto'] : 0;
	$quantidade = isset($registro['quantidade']) ? (int)$registro['quantidade'] : 0;
}

// ===== Excluir arquivo, se existir (mantém comportamento) =====
if ($foto != "sem-foto.jpg" && $foto != "") {
	@unlink('../../img/contas/'.$foto);
}

// ===== Ajustar estoque do produto (mantém a lógica: ao excluir compra, reduz o estoque) =====
if ($produto > 0 && $quantidade > 0) {

	$stmtProd = $pdo->prepare("SELECT estoque FROM produtos WHERE id = :id LIMIT 1");
	$stmtProd->execute([':id' => $produto]);
	$prod = $stmtProd->fetch(PDO::FETCH_ASSOC);

	if ($prod) {
		$estoque = isset($prod['estoque']) ? (int)$prod['estoque'] : 0;

		$total_estoque = $estoque - $quantidade;

		// Mantém o padrão do seu código: atualiza estoque e zera valor_compra
		$stmtUp = $pdo->prepare("UPDATE produtos SET estoque = :estoque, valor_compra = '0' WHERE id = :id");
		$stmtUp->execute([
			':estoque' => $total_estoque,
			':id' => $produto
		]);
	}
}

// ===== Excluir registro (mantém a lógica) =====
$stmtDel = $pdo->prepare("DELETE FROM {$tabela} WHERE id = :id");
$stmtDel->execute([':id' => $id]);

echo 'Excluído com Sucesso';
?>
