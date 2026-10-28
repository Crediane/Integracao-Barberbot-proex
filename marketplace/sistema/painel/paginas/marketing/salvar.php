<?php 
require_once __DIR__ . '/../_context_bootstrap.php';

// Guarda slug na sessão (ajuda includes/conexão a escolher o banco da loja)
if (!empty($__bb_reqSlug) && empty($_SESSION['loja_slug'])) {
  $_SESSION['loja_slug'] = $__bb_reqSlug;
}

require_once("../../../conexao.php");
$tabela = 'marketing';


$id = $_POST['id'];
$titulo = $_POST['titulo'];
$msg = $_POST['msg'];
$msg2 = $_POST['msg2'];




//validar troca da foto
$query = $pdo->query("SELECT * FROM $tabela where id = '$id'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);
if($total_reg > 0){
	$foto = $res[0]['arquivo'];
}else{
	$foto = 'sem-foto.png';
}

//validar troca da audio
$query = $pdo->query("SELECT * FROM $tabela where id = '$id'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);
if($total_reg > 0){
	$audio = $res[0]['audio'];
}else{
	$audio = '';
}


//validar troca do doc
$query = $pdo->query("SELECT * FROM $tabela where id = '$id'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);
if($total_reg > 0){
	$documento = $res[0]['documento'];
}else{
	$documento = 'sem-foto.png';
}



//SCRIPT PARA SUBIR FOTO NO SERVIDOR
$nome_img = date('d-m-Y H:i:s') .'-'.@$_FILES['foto']['name'];
$nome_img = preg_replace('/[ :]+/' , '-' , $nome_img);

$caminho = '../../images/marketing/' .$nome_img;

$imagem_temp = @$_FILES['foto']['tmp_name']; 

if(@$_FILES['foto']['name'] != ""){
	$ext = pathinfo($nome_img, PATHINFO_EXTENSION);   
	if($ext == 'png' or $ext == 'PNG' or $ext == 'jpg' or $ext == 'JPG'  or $ext == 'jpeg' or $ext == 'JPEG'){ 
	
			//EXCLUO A FOTO ANTERIOR
			if($foto != "sem-foto.png"){
				@unlink('../../images/marketing/'.$foto);
			}

			$foto = $nome_img;
		
		move_uploaded_file($imagem_temp, $caminho);
	}else{
		echo 'Extensão de Imagem não permitida!';
		exit();
	}
}


$nome_audio = date('d-m-Y H:i:s') . '-' . @$_FILES['audio']['name'];
$nome_audio = preg_replace('/[ :]+/', '-', $nome_audio);

$caminho_audio = '../../images/marketing/' . $nome_audio;

$audio_temp = @$_FILES['audio']['tmp_name'];

// Definir tamanho máximo (em bytes)
$tamanho_maximo = 5 * 1024 * 1024; // 5MB

if (@$_FILES['audio']['name'] != "") {
	// Verificar o tamanho do arquivo
	if (@$_FILES['audio']['size'] > $tamanho_maximo) {
		echo 'Tamanho do arquivo excede o limite de 16MB!';
		exit();
	}

	$ext = pathinfo($nome_audio, PATHINFO_EXTENSION);
	if (
		$ext == 'ogg' or $ext == 'OGG' or $ext == 'mp3' or $ext == 'MP3' or
		$ext == 'wav' or $ext == 'WAV' or $ext == 'm4a' or $ext == 'M4A' or
		$ext == 'opus' or $ext == 'OPUS' or $ext == 'aac' or $ext == 'AAC'
	) {

		//EXCLUO A FOTO ANTERIOR
		if ($audio != "") {
			@unlink('../../images/marketing/' . $audio);
		}

		$audio = $nome_audio;

		move_uploaded_file($audio_temp, $caminho_audio);
	} else {
		echo 'Extensão de Áudio não permitida! Os formatos aceitos são: OGG, MP3, WAV, M4A, OPUS e AAC.';
		exit();
	}
}



//SCRIPT PARA SUBIR FOTO NO SERVIDOR
$nome_img = date('d-m-Y H:i:s') .'-'.@$_FILES['documento']['name'];
$nome_img = preg_replace('/[ :]+/' , '-' , $nome_img);

$caminho = '../../images/marketing/' .$nome_img;

$imagem_temp = @$_FILES['documento']['tmp_name']; 

if(@$_FILES['documento']['name'] != ""){
	$ext = pathinfo($nome_img, PATHINFO_EXTENSION);   
	if($ext == 'pdf' or $ext == 'PDF'){ 
	
			//EXCLUO A FOTO ANTERIOR
			if($documento != "sem-foto.png"){
				@unlink('../../images/marketing/'.$documento);
			}

			$documento = $nome_img;
		
		move_uploaded_file($imagem_temp, $caminho);
	}else{
		echo 'Extensão do pdf não permitida!';
		exit();
	}
}


if($id == ""){
	$query = $pdo->prepare("INSERT INTO $tabela SET data = curDate(), titulo = :titulo, mensagem = :mensagem, mensagem2 = :mensagem2, arquivo = '$foto', audio = '$audio', documento = '$documento'");
}else{
	$query = $pdo->prepare("UPDATE $tabela SET titulo = :titulo, mensagem = :mensagem, mensagem2 = :mensagem2, arquivo = '$foto', audio = '$audio', documento = '$documento' WHERE id = '$id'");
}

$query->bindValue(":titulo", "$titulo");
$query->bindValue(":mensagem", "$msg");
$query->bindValue(":mensagem2", "$msg2");
$query->execute();

echo 'Salvo com Sucesso';
 ?>