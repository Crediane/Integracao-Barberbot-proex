<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once(__DIR__ . '/../../verificar.php');
require_once(__DIR__ . '/../../../conexao.php');

$tabela = 'analise_capilar';

$id = isset($_POST['id']) ? trim($_POST['id']) : '';

if ($id == '') {
	echo 'ID inválido';
	exit();
}

$query = $pdo->prepare("SELECT * FROM $tabela WHERE id = :id");
$query->bindValue(":id", $id);
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);

if (count($res) > 0) {
	$foto = isset($res[0]['foto']) ? trim($res[0]['foto']) : '';

	if ($foto != '') {
		$caminho_foto = __DIR__ . '/../../images/analises/' . $foto;
		if (file_exists($caminho_foto) && is_file($caminho_foto)) {
			@unlink($caminho_foto);
		}
	}
}

$query = $pdo->prepare("DELETE FROM $tabela WHERE id = :id");
$query->bindValue(":id", $id);
$query->execute();

echo 'Excluído com Sucesso';
?>