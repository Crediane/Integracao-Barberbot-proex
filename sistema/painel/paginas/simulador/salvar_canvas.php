<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once(__DIR__ . "/../../../conexao.php");

$tabela = 'simulacoes_protese';
$pasta_upload = __DIR__ . '/../../images/simulacoes/';

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$imagem = isset($_POST['img']) ? trim((string)$_POST['img']) : '';

if($id <= 0){
	echo 'ID não informado';
	exit();
}

if($imagem == ''){
	echo 'Imagem não recebida';
	exit();
}

if(!is_dir($pasta_upload)){
	echo 'Pasta de imagens da simulação não encontrada';
	exit();
}

if(!is_writable($pasta_upload)){
	echo 'Pasta de imagens da simulação sem permissão de escrita';
	exit();
}

$query = $pdo->prepare("SELECT * FROM $tabela WHERE id = :id");
$query->bindValue(":id", $id, PDO::PARAM_INT);
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);

if(@count($res) == 0){
	echo 'Registro não encontrado';
	exit();
}

if(strpos($imagem, 'data:image/png;base64,') === false){
	echo 'Formato da imagem inválido';
	exit();
}

$imagem = str_replace('data:image/png;base64,', '', $imagem);
$imagem = str_replace(' ', '+', $imagem);

if($imagem == ""){
	echo 'Imagem inválida';
	exit();
}

$dados = base64_decode($imagem);

if($dados === false || $dados == ""){
	echo 'Erro ao decodificar imagem';
	exit();
}

$nome_img = md5(uniqid()) . '.png';
$caminho = $pasta_upload . $nome_img;

$salvou_arquivo = file_put_contents($caminho, $dados);

if($salvou_arquivo === false){
	echo 'Erro ao salvar imagem do canvas';
	exit();
}

if(!file_exists($caminho)){
	echo 'Arquivo da imagem do canvas não foi gravado';
	exit();
}

$imagem_antiga = $res[0]['imagem_simulada'];

if($imagem_antiga != ""){
	$caminho_antigo = $pasta_upload . $imagem_antiga;
	if(file_exists($caminho_antigo)){
		@unlink($caminho_antigo);
	}
}

$query2 = $pdo->prepare("UPDATE $tabela SET imagem_simulada = :imagem_simulada WHERE id = :id");
$query2->bindValue(":imagem_simulada", $nome_img);
$query2->bindValue(":id", $id, PDO::PARAM_INT);
$query2->execute();

echo 'Salvo com Sucesso';
?>