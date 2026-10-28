<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once(__DIR__ . '/../../verificar.php');
require_once(__DIR__ . '/../../../conexao.php');

$tabela = 'analise_capilar';

$id = isset($_POST['id']) ? trim($_POST['id']) : '';
$cliente = isset($_POST['cliente']) ? trim($_POST['cliente']) : '';
$cor_detectada = isset($_POST['cor_detectada']) ? trim($_POST['cor_detectada']) : '';
$densidade_detectada = isset($_POST['densidade_detectada']) ? trim($_POST['densidade_detectada']) : '';
$grau_falha = isset($_POST['grau_falha']) ? trim($_POST['grau_falha']) : '';
$sugestao_protese = isset($_POST['sugestao_protese']) ? trim($_POST['sugestao_protese']) : '';
$observacoes = isset($_POST['observacoes']) ? trim($_POST['observacoes']) : '';
$data_analise = isset($_POST['data_analise']) ? trim($_POST['data_analise']) : '';
$foto_atual = isset($_POST['foto_atual']) ? trim($_POST['foto_atual']) : '';

if ($cliente == '') {
	echo 'Selecione um Cliente';
	exit();
}

if ($data_analise == '') {
	echo 'Informe a Data da Análise';
	exit();
}

$nome_img = $foto_atual;
$pasta = __DIR__ . '/../../images/analises/';

if (isset($_FILES['foto']) && !empty($_FILES['foto']['name'])) {

	if (!is_dir($pasta)) {
		echo 'Pasta de imagens da análise não encontrada';
		exit();
	}

	if (!is_writable($pasta)) {
		echo 'Pasta de imagens da análise sem permissão de gravação';
		exit();
	}

	if (!is_uploaded_file($_FILES['foto']['tmp_name'])) {
		echo 'Arquivo de imagem inválido';
		exit();
	}

	$foto = $_FILES['foto']['name'];
	$extensao = strtolower(pathinfo($foto, PATHINFO_EXTENSION));

	if ($extensao != 'png' && $extensao != 'jpg' && $extensao != 'jpeg' && $extensao != 'webp') {
		echo 'Formato de imagem não permitido';
		exit();
	}

	$nome_img = md5(uniqid((string)mt_rand(), true)) . '.' . $extensao;
	$caminho = $pasta . $nome_img;

	$upload = move_uploaded_file($_FILES['foto']['tmp_name'], $caminho);

	if ($upload == false) {
		echo 'Erro ao salvar a foto da análise';
		exit();
	}

	if (!file_exists($caminho)) {
		echo 'Arquivo da foto da análise não foi gravado';
		exit();
	}

	if ($foto_atual != '') {
		$caminho_antigo = $pasta . $foto_atual;
		if (file_exists($caminho_antigo)) {
			@unlink($caminho_antigo);
		}
	}
}

$query = $pdo->prepare("SELECT * FROM $tabela WHERE cliente = :cliente AND data_analise = :data_analise");
$query->bindValue(':cliente', $cliente);
$query->bindValue(':data_analise', $data_analise);
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);

if (count($res) > 0 && $id != $res[0]['id']) {
	echo 'Registro já Cadastrado, escolha outro!!';
	exit();
}

if ($id == '') {
	$query = $pdo->prepare("INSERT INTO $tabela SET 
		cliente = :cliente, 
		foto = :foto, 
		cor_detectada = :cor_detectada, 
		densidade_detectada = :densidade_detectada, 
		grau_falha = :grau_falha, 
		sugestao_protese = :sugestao_protese, 
		observacoes = :observacoes, 
		data_analise = :data_analise");
} else {
	$query = $pdo->prepare("UPDATE $tabela SET 
		cliente = :cliente, 
		foto = :foto, 
		cor_detectada = :cor_detectada, 
		densidade_detectada = :densidade_detectada, 
		grau_falha = :grau_falha, 
		sugestao_protese = :sugestao_protese, 
		observacoes = :observacoes, 
		data_analise = :data_analise 
		WHERE id = :id");
	$query->bindValue(':id', $id);
}

$query->bindValue(':cliente', $cliente);
$query->bindValue(':foto', $nome_img);
$query->bindValue(':cor_detectada', $cor_detectada);
$query->bindValue(':densidade_detectada', $densidade_detectada);
$query->bindValue(':grau_falha', $grau_falha);
$query->bindValue(':sugestao_protese', $sugestao_protese);
$query->bindValue(':observacoes', $observacoes);
$query->bindValue(':data_analise', $data_analise);
$query->execute();

echo 'Salvo com Sucesso';
?>