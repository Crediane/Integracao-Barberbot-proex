<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once(__DIR__ . "/../../../conexao.php");
$tabela = 'simulacoes_protese';

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$cliente = isset($_POST['cliente']) ? (int)$_POST['cliente'] : 0;
$id_protese = isset($_POST['id_protese']) ? (int)$_POST['id_protese'] : 0;
$observacoes = isset($_POST['observacoes']) ? trim((string)$_POST['observacoes']) : '';
$data_simulacao = isset($_POST['data_simulacao']) ? trim((string)$_POST['data_simulacao']) : '';
$foto_original_atual = isset($_POST['foto_original_atual']) ? basename((string)$_POST['foto_original_atual']) : '';
$imagem_simulada_atual = isset($_POST['imagem_simulada_atual']) ? basename((string)$_POST['imagem_simulada_atual']) : '';

if($cliente <= 0){
	echo 'Selecione um Cliente';
	exit();
}

if($id_protese <= 0){
	echo 'Selecione uma Prótese';
	exit();
}

if($data_simulacao == ""){
	echo 'Informe a Data da Simulação';
	exit();
}

$nome_img_original = $foto_original_atual;
$nome_img_simulada = $imagem_simulada_atual;

$pasta_upload = __DIR__ . '/../../images/simulacoes/';

if(!is_dir($pasta_upload)){
	echo 'Pasta de imagens da simulação não encontrada';
	exit();
}

if(!is_writable($pasta_upload)){
	echo 'Pasta de imagens da simulação sem permissão de escrita';
	exit();
}

if(@$_FILES['foto_original']['name'] != ''){

	if($_FILES['foto_original']['error'] != 0){
		echo 'Erro ao enviar a Foto Original';
		exit();
	}

	if(!is_uploaded_file($_FILES['foto_original']['tmp_name'])){
		echo 'Arquivo da Foto Original inválido';
		exit();
	}

	$foto_original = $_FILES['foto_original']['name'];
	$extensao = strtolower(pathinfo($foto_original, PATHINFO_EXTENSION));

	if($extensao != 'png' && $extensao != 'jpg' && $extensao != 'jpeg' && $extensao != 'webp'){
		echo 'Formato da Foto Original não permitido';
		exit();
	}

	$nome_img_original = md5(uniqid()) . "." . $extensao;
	$caminho = $pasta_upload . $nome_img_original;

	$upload_original = move_uploaded_file($_FILES['foto_original']['tmp_name'], $caminho);

	if($upload_original == false){
		echo 'Erro ao salvar a Foto Original';
		exit();
	}

	if(!file_exists($caminho)){
		echo 'Arquivo da Foto Original não foi gravado';
		exit();
	}

	if($foto_original_atual != ""){
		$caminho_antigo = $pasta_upload . $foto_original_atual;
		if(file_exists($caminho_antigo)){
			@unlink($caminho_antigo);
		}
	}
}

if(@$_FILES['imagem_simulada']['name'] != ''){

	if($_FILES['imagem_simulada']['error'] != 0){
		echo 'Erro ao enviar a Imagem Simulada';
		exit();
	}

	if(!is_uploaded_file($_FILES['imagem_simulada']['tmp_name'])){
		echo 'Arquivo da Imagem Simulada inválido';
		exit();
	}

	$imagem_simulada = $_FILES['imagem_simulada']['name'];
	$extensao2 = strtolower(pathinfo($imagem_simulada, PATHINFO_EXTENSION));

	if($extensao2 != 'png' && $extensao2 != 'jpg' && $extensao2 != 'jpeg' && $extensao2 != 'webp'){
		echo 'Formato da Imagem Simulada não permitido';
		exit();
	}

	$nome_img_simulada = md5(uniqid()) . "." . $extensao2;
	$caminho2 = $pasta_upload . $nome_img_simulada;

	$upload_simulada = move_uploaded_file($_FILES['imagem_simulada']['tmp_name'], $caminho2);

	if($upload_simulada == false){
		echo 'Erro ao salvar a Imagem Simulada';
		exit();
	}

	if(!file_exists($caminho2)){
		echo 'Arquivo da Imagem Simulada não foi gravado';
		exit();
	}

	if($imagem_simulada_atual != ""){
		$caminho_antigo2 = $pasta_upload . $imagem_simulada_atual;
		if(file_exists($caminho_antigo2)){
			@unlink($caminho_antigo2);
		}
	}
}

$query = $pdo->prepare("SELECT * FROM $tabela WHERE cliente = :cliente AND id_protese = :id_protese AND data_simulacao = :data_simulacao");
$query->bindValue(":cliente", $cliente);
$query->bindValue(":id_protese", $id_protese);
$query->bindValue(":data_simulacao", $data_simulacao);
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);

if(@count($res) > 0 and $id != $res[0]['id']){
	echo 'Registro já Cadastrado, escolha outro!!';
	exit();
}

if($id <= 0){
	$query = $pdo->prepare("INSERT INTO $tabela SET 
		cliente = :cliente, 
		id_protese = :id_protese, 
		foto_original = :foto_original, 
		imagem_simulada = :imagem_simulada, 
		observacoes = :observacoes, 
		data_simulacao = :data_simulacao");
}else{
	$query = $pdo->prepare("UPDATE $tabela SET 
		cliente = :cliente, 
		id_protese = :id_protese, 
		foto_original = :foto_original, 
		imagem_simulada = :imagem_simulada, 
		observacoes = :observacoes, 
		data_simulacao = :data_simulacao 
		WHERE id = :id");
	$query->bindValue(":id", $id, PDO::PARAM_INT);
}

$query->bindValue(":cliente", $cliente);
$query->bindValue(":id_protese", $id_protese);
$query->bindValue(":foto_original", $nome_img_original);
$query->bindValue(":imagem_simulada", $nome_img_simulada);
$query->bindValue(":observacoes", $observacoes);
$query->bindValue(":data_simulacao", $data_simulacao);
$query->execute();

echo 'Salvo com Sucesso';
?>