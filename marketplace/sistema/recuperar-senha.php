<?php 
require_once __DIR__ . '/conexao.php';

// Detecta se está em contexto de LOJA (/loja/<slug>/...) para não bloquear recuperação no tenant
$__bb_reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$__bb_slug = '';
if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath, $mSlug)) {
	$__bb_slug = strtolower($mSlug[1]);
	$__bb_slug = preg_replace('/[^a-z0-9_-]/', '', $__bb_slug);
}


$email = filter_var($_POST['email'], @FILTER_SANITIZE_STRING);

if($email == $email_sistema && $__bb_slug == ''){
	echo 'Você não pode redefinir esse email!';
	exit();
}

$query = $pdo->prepare("SELECT * from usuarios where email = :email");
$query->bindValue(":email", "$email");
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);
if($total_reg > 0){
	
	$telefone = $res[0]['telefone'];

	$nova_senha = rand(100000, 100000000);
	$senha_crip = password_hash($nova_senha, PASSWORD_DEFAULT);

	$query = $pdo->prepare("UPDATE usuarios SET senha_crip = '$senha_crip' where email = :email");
	$query->bindValue(":email", "$email");
	$query->execute();
	//envio do email
	$destinatario = $email;
    $assunto = utf8_decode($nome_sistema . ' - Recuperação de Senha');
    $mensagem = utf8_decode('Sua senha é ' .$nova_senha);
    $cabecalhos = "From: ".$email_sistema;
   
    @mail($destinatario, $assunto, $mensagem, $cabecalhos);

    echo 'Recuperado com Sucesso';
}else{
	echo 'Esse email não está Cadastrado!';
}


if($msg_agendamento == 'Api' and $telefone != ""){

$mensagem = '*'.$nome_sistema.'* %0A';
$mensagem .= '_Recuperação de Senha_ %0A';
$mensagem .= 'Email: *'.$email.'* %0A';
$mensagem .= 'Senha: *'.$nova_senha.'* %0A';

// Link correto por contexto
$host = $_SERVER['HTTP_HOST'] ?? '';
$base = ($host != '') ? ('https://' . $host) : '';
if($__bb_slug != ''){
	$link = $base . '/loja/' . $__bb_slug . '/sistema/';
}else{
	$link = $base . '/sistema/';
}
$mensagem .= 'Acesse: '.$link.' %0A';


$telefone = '55'.preg_replace('/[ ()-]+/' , '' , $telefone);

require __DIR__ . '/../ajax/api-texto.php';

}

 ?>