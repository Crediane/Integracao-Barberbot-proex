<?php 
if($token_rel != 'A5030'){
echo '<script>window.location="../../"</script>';
exit();
}

$cpf = '';
$texto_contrato = '';

$query = $pdo->query("SELECT * FROM clientes where id = '$id'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);
if($total_reg > 0){
$id = $res[0]['id'];
	$nome = isset($res[0]['nome']) ? (string)$res[0]['nome'] : '';
	$data_nasc = isset($res[0]['data_nasc']) ? (string)$res[0]['data_nasc'] : '';
	$data_cad = isset($res[0]['data_cad']) ? (string)$res[0]['data_cad'] : '';
	$telefone = isset($res[0]['telefone']) ? (string)$res[0]['telefone'] : '';
	$endereco = isset($res[0]['endereco']) ? (string)$res[0]['endereco'] : '';
	$cartoes = isset($res[0]['cartoes']) ? (string)$res[0]['cartoes'] : '';
	$data_retorno = isset($res[0]['data_retorno']) ? (string)$res[0]['data_retorno'] : '';
	$ultimo_servico = isset($res[0]['ultimo_servico']) ? (string)$res[0]['ultimo_servico'] : '';
	$cpf = isset($res[0]['cpf']) ? (string)$res[0]['cpf'] : '';
}

if(($cpf ?? '') == ""){
	echo 'Preencha o CPF do cliente';
	exit();
}

$query = $pdo->query("SELECT * FROM contratos where cliente = '$id'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$texto_contrato = isset($res[0]['texto']) ? (string)$res[0]['texto'] : '';

$nome = isset($nome) ? (string)$nome : '';
$whatsapp_sistema = isset($whatsapp_sistema) ? (string)$whatsapp_sistema : '';
$nome_sistema = isset($nome_sistema) ? (string)$nome_sistema : '';
$data_hoje = isset($data_hoje) ? (string)$data_hoje : date('d/m/Y');

?>

<!DOCTYPE html>

<html>

<head>



<style>



@import url('https://fonts.cdnfonts.com/css/tw-cen-mt-condensed');

@page { margin: 145px 20px 25px 20px; }

#header { position: fixed; left: 0px; top: -110px; bottom: 100px; right: 0px; height: 35px; text-align: center; padding-bottom: 100px; }

#content {margin-top: 0px;}

#footer { position: fixed; left: 0px; bottom: -60px; right: 0px; height: 80px; }

#footer .page:after {content: counter(page, my-sec-counter);}

body {font-family: 'Tw Cen MT', sans-serif;}



.marca{

	position:fixed;

	left:50;

	top:100;

	width:80%;

	opacity:8%;

}



</style>



</head>

<body>


<div id="header" >



	<div style="border-style: solid; font-size: 10px; height: 50px;">

		<table style="width: 100%; border: 0px solid #ccc;">

			<tr>

				<td style="border: 1px; solid #000; width: 37%; text-align: left;">

					<img style="margin-top: 7px; margin-left: 7px;" id="imag" src="<?php echo $url_sistema ?>sistema/img/logo_rel.jpg" width="80">

				</td>

				
				<td style="width: 1%; text-align: center; font-size: 13px;">

				

				</td>

				<td style="width: 47%; text-align: right; font-size: 9px;padding-right: 10px;">

						<b><big>CONTRATO DE SERVIÇO</big></b><br>  <br> <?php echo @mb_strtoupper($data_hoje) ?>

				</td>

			</tr>		

		</table>

	</div>

</div>



<div id="footer" class="row">
<hr style="margin-bottom: 0;">
	<table style="width:100%;">
		<tr style="width:100%;">
			<td style="width:60%; font-size: 10px; text-align: left;"><?php echo $nome_sistema ?> Telefone: <?php echo $whatsapp_sistema ?></td>
			<td style="width:40%; font-size: 10px; text-align: right;"><p class="page">Página  </p></td>
		</tr>
	</table>
</div>

<div id="content" style="margin-top: -20px;">
	<div align="center" class="titulo_cab titulo_img"><u>Contrato de Prestação de Serviços </u></div>	
	
	
	<br>
	<div class="cabecalho" style="border-bottom: solid 1px #0340a3">
	</div>

	<div class="mx-2" style="font-size:13px">


<?php echo $texto_contrato ?>

	
</div>
</div>

		


</body>



</html>
