<?php 
require_once __DIR__ . '/conexao.php';

$telefone = filter_var($_POST['telefone'], @FILTER_SANITIZE_STRING);

if($telefone == $whatsapp_sistema){
	echo 'Você não pode redefinir esse usuário!';
	exit();
}

$query = $pdo->prepare("SELECT * from clientes where telefone = :telefone");
$query->bindValue(":telefone", "$telefone");
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);
if($total_reg > 0){	

	$nova_senha = rand(100000, 100000000);
	$senha_crip = password_hash($nova_senha, PASSWORD_DEFAULT);

	$query = $pdo->prepare("UPDATE clientes SET senha_crip = '$senha_crip' where telefone = :telefone");
	$query->bindValue(":telefone", "$telefone");
	$query->execute();


	if($msg_agendamento == 'Api' and $telefone != ""){

		$mensagem = '*'.$nome_sistema.'* %0A';
		$mensagem .= '_Sua nova senha é_ %0A';
		$mensagem .= 'Senha: *'.$nova_senha.'* %0A';

		$telefone = '55'.preg_replace('/[ ()-]+/' , '' , $telefone);

		require __DIR__ . '/../ajax/api-texto.php';

		}
	


    echo 'Recuperado com Sucesso';
}else{
	echo 'Esse telefone não está Cadastrado!';
}




 ?>