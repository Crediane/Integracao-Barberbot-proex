<?php 
require_once __DIR__ . '/_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';

$dataMes = Date('m');
$dataDia = Date('d');
$dataAno = Date('d');
$data_atual = date('Y-m-d');

$hash = '';
$hash2 = '';
$hash3 = '';

$data_semana = date('Y/m/d', strtotime("-7 days",strtotime($data_atual)));
$id_usuario = $_SESSION['id'];

$id = $_POST['id'];
$clientes = $_POST['clientes'];
$delay = $_POST['tempo'];

$query = $pdo->query("SELECT * FROM marketing where id = '$id'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);

	$titulo = $res[0]['titulo'];	
	$mensagem_disparo = $res[0]['mensagem'];
	$item1 = $res[0]['item1'];
	$item2 = $res[0]['item2'];
	$item3 = $res[0]['item3'];
	$item4 = $res[0]['item4'];
	$item5 = $res[0]['item5'];
	$item6 = $res[0]['item6'];
	$item7 = $res[0]['item7'];
	$item8 = $res[0]['item8'];

	$item9 = $res[0]['item9'];
	$item10 = $res[0]['item10'];
	$item11 = $res[0]['item11'];
	$item12 = $res[0]['item12'];
	$item13 = $res[0]['item13'];
	$item14 = $res[0]['item14'];
	$item15 = $res[0]['item15'];
	$item16 = $res[0]['item16'];
	$item17 = $res[0]['item17'];
	$item18 = $res[0]['item18'];
	$item19 = $res[0]['item19'];
	$item20 = $res[0]['item20'];


	$conclusao = $res[0]['conclusao'];
	$arquivo = $res[0]['arquivo'];
	$audio = $res[0]['audio'];

	$envios = $res[0]['envios'];

$mensagem = '';
if($titulo != ""){
	$mensagem .= '*'.$titulo."* \n";
}

if($mensagem_disparo != ""){
	$mensagem .= $mensagem_disparo."\n\n";
}

if($item1 != ""){
	$mensagem .= '✅'.$item1."\n";
}

if($item2 != ""){
	$mensagem .= '✅'.$item2."\n";
}

if($item3 != ""){
	$mensagem .= '✅'.$item3."\n";
}

if($item4 != ""){
	$mensagem .= '✅'.$item4."\n";
}

if($item5 != ""){
	$mensagem .= '✅'.$item5."\n";
}

if($item6 != ""){
	$mensagem .= '✅'.$item6."\n";
}

if($item7 != ""){
	$mensagem .= '✅'.$item7."\n";
}

if($item8 != ""){
	$mensagem .= '✅'.$item8."\n";
}


if($item9 != ""){
	$mensagem .= '✅'.$item9."\n";
}

if($item10 != ""){
	$mensagem .= '✅'.$item10."\n";
}

if($item11 != ""){
	$mensagem .= '✅'.$item11."\n";
}

if($item12 != ""){
	$mensagem .= '✅'.$item12."\n";
}

if($item13 != ""){
	$mensagem .= '✅'.$item13."\n";
}

if($item14 != ""){
	$mensagem .= '✅'.$item14."\n";
}

if($item15 != ""){
	$mensagem .= '✅'.$item15."\n";
}

if($item16 != ""){
	$mensagem .= '✅'.$item16."\n";
}

if($item17 != ""){
	$mensagem .= '✅'.$item17."\n";
}

if($item18 != ""){
	$mensagem .= '✅'.$item18."\n";
}

if($item19 != ""){
	$mensagem .= '✅'.$item19."\n";
}

if($item20 != ""){
	$mensagem .= '✅'.$item20."\n";
}


if($conclusao != ""){
	$mensagem .= "\n".$conclusao;
}


// Buscar os Contatos que serão enviados
if($clientes == "Teste"){
	$resultado = $pdo->query("SELECT telefone FROM usuarios where nivel = 'Administrador' and telefone != ''");	

}else if($clientes == "Aniversáriantes Mês"){
$resultado = $pdo->query("SELECT telefone FROM clientes where month(data_nasc) = '$dataMes'  and telefone != ''");	
	
}else if ($clientes == "Aniversáriantes Dia"){
	$resultado = $pdo->query("SELECT telefone FROM clientes where month(data_nasc) = '$dataMes' and day(data_nasc) = '$dataDia' and telefone != ''");

}else if ($clientes == "Clientes Mês"){
	$resultado = $pdo->query("SELECT telefone FROM clientes where month(data_cad) = '$dataMes' and year(data_cad) = '$dataAno' and telefone != ''");

}else if ($clientes == "Clientes Semana"){
	$resultado = $pdo->query("SELECT telefone FROM clientes where data_cad >= '$data_semana' and telefone != ''");

}else{
	$resultado = $pdo->query("SELECT telefone FROM clientes where telefone != ''");
}




$prefixo = '55';
$numeros_formatados = array();

foreach($resultado as $key){
  $numero = preg_replace('/\D/', '', $key['telefone']);
  $numero_formatado = $prefixo . $numero;
  $numeros_formatados[] = $numero_formatado;
}

$numeros_formatados = json_encode($numeros_formatados);


if($mensagem != ""){
	require_once __DIR__ . '/marketing_texto.php';
}

$url_arquivo = $url_sistema."sistema/painel/img/marketing/".$arquivo;
if($arquivo != "sem-foto.jpg"){
	require_once __DIR__ . '/marketing_foto.php';
}

$url_audio = $url_sistema."sistema/painel/img/marketing/".$audio;
if($audio != ""){
	require_once __DIR__ . '/marketing_audio.php';
}

$envios += 1;



//salvar hashs
$pdo->query("UPDATE marketing SET envios = '$envios', data_envio = curDate(), hash = '$hash', hash2 = '$hash2', hash3 = '$hash3', forma_envio = '$clientes' where id = '$id'");

echo 'Salvo com Sucesso';
 ?>