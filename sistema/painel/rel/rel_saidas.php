<?php
// ============================================================================
// BarberBot Software - Relatório de Saídas / Despesas
// Arquivo: /public_html/sistema/painel/rel/rel_saidas.php
// Correção direta no mesmo padrão /loja/{slug}/
// - sem mudar a lógica do relatório
// - trata Todas/Todos como sem filtro
// - usa as datas já normalizadas pelo rel_saidas_class.php
// ============================================================================
 
if(!isset($token_rel)){ $token_rel = ''; }
if($token_rel != 'A5030'){
echo '<script>window.location="../../"</script>';
exit();
}

$dataInicialF = implode('/', array_reverse(@explode('-', $dataInicial)));
$dataFinalF = implode('/', array_reverse(@explode('-', $dataFinal)));

if($dataInicial == $dataFinal){
	$texto_apuracao = 'APURADO EM '.$dataInicialF;
}else if($dataInicial == '1980-01-01'){
	$texto_apuracao = 'APURADO EM TODO O PERÍODO';
}else{
	$texto_apuracao = 'APURAÇÃO DE '.$dataInicialF. ' ATÉ '.$dataFinalF;
}



if($filtro == ''){
	$acao_rel = 'Saídas / Despesas';
}elseif($filtro == 'Compra'){
		$acao_rel = ' Compras ';
}elseif($filtro == 'Comissão'){
		$acao_rel = ' Comissões ';
}else{
		$acao_rel = 'Despesas';
}

$filtro = isset($filtro) ? trim((string)$filtro) : '';
if($filtro === 'Todas' || $filtro === 'Todos'){
	$filtro = '';
}
$filtro = '%'.$filtro.'%';	


if($dataInicial == $dataFinal){
	$texto_apuracao = 'APURADO EM '.$dataInicialF;
}else if($dataInicial == '1980-01-01'){
	$texto_apuracao = 'APURADO EM TODO O PERÍODO';
}else{
	$texto_apuracao = 'APURAÇÃO DE '.$dataInicialF. ' ATÉ '.$dataFinalF;
}

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

						<b><big>RELATÓRIO DE DESPESAS</big></b><br> <?php echo @mb_strtoupper($texto_apuracao) ?> <br> <?php echo @mb_strtoupper($data_hoje) ?>

				</td>

			</tr>		

		</table>

	</div>



<br>





		<table id="cabecalhotabela" style="border-bottom-style: solid; font-size: 8px; margin-bottom:10px; width: 100%; table-layout: fixed;">

			<thead>				

				<tr id="cabeca" style="margin-left: 0px; background-color:#CCC">
					<td style="width:25%">DESCRIÇÃO</td>
					<td style="width:10%">TIPO</td>
					<td style="width:10%">VALOR</td>
					<td style="width:10%">DATA PGTO</td>
					<td style="width:20%">PAGO POR</td>
					<td style="width:25%">DESTINADO À</td>			
				</tr>

			</thead>

		</table>

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



<div id="content" style="margin-top: 0;">







		<table style="width: 100%; table-layout: fixed; font-size:9px; text-transform: uppercase;">

			<thead>

				<tbody>

					<?php



$total_entradas = 0;
$total_entradasF = 0;
		$query = $pdo->query("SELECT * FROM pagar where data_pgto >= '$dataInicial' and data_pgto <= '$dataFinal' and tipo LIKE '$filtro' and pago = 'Sim' ORDER BY data_pgto asc");
$res = $query->fetchAll(PDO::FETCH_ASSOC);

$linhas = @count($res);

if($linhas > 0){

for($i=0; $i<$linhas; $i++){
$id = $res[$i]['id'];	
	$descricao = $res[$i]['descricao'];
	$tipo = $res[$i]['tipo'];
	$valor = $res[$i]['valor'];
	$data_lanc = $res[$i]['data_lanc'];
	$data_pgto = $res[$i]['data_pgto'];
	$data_venc = $res[$i]['data_venc'];
	$usuario_lanc = $res[$i]['usuario_lanc'];
	$usuario_baixa = $res[$i]['usuario_baixa'];
	$foto = $res[$i]['foto'];
	$pessoa = $res[$i]['pessoa'];
	$pago = $res[$i]['pago'];
	$funcionario = $res[$i]['funcionario'];
	
	$total_entradas += $valor;
	
	$valorF = number_format($valor, 2, ',', '.');
	$total_entradasF = number_format($total_entradas, 2, ',', '.');

	$data_lancF = implode('/', array_reverse(@explode('-', $data_lanc)));
	$data_pgtoF = implode('/', array_reverse(@explode('-', $data_pgto)));
	$data_vencF = implode('/', array_reverse(@explode('-', $data_venc)));
	

		$query2 = $pdo->query("SELECT * FROM fornecedores where id = '$pessoa'");
		$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
		$total_reg2 = @count($res2);
		if($total_reg2 > 0){
			$nome_pessoa = $res2[0]['nome'];
			$telefone_pessoa = $res2[0]['telefone'];
			$chave_pix_forn = $res2[0]['chave_pix'];
			$tipo_chave_forn = $res2[0]['tipo_chave'];
			$classe_whats = '';
		}else{
			$nome_pessoa = 'Nenhum!';
			$telefone_pessoa = '';
			$classe_whats = 'ocultar';
			$chave_pix_forn = '';
			$tipo_chave_forn = '';
		}


		$query2 = $pdo->query("SELECT * FROM usuarios where id = '$funcionario'");
		$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
		$total_reg2 = @count($res2);
		if($total_reg2 > 0){
			$nome_func = $res2[0]['nome'];
			$telefone_func = $res2[0]['telefone'];
			$chave_pix_func = $res2[0]['chave_pix'];
			$tipo_chave_func = $res2[0]['tipo_chave'];
			
		}else{
			$nome_func = 'Nenhum!';
			$telefone_func = '';
			$chave_pix_func = '';
			$tipo_chave_func = '';
			
			
		}


		$query2 = $pdo->query("SELECT * FROM usuarios where id = '$usuario_baixa'");
		$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
		$total_reg2 = @count($res2);
		if($total_reg2 > 0){
			$nome_usuario_pgto = $res2[0]['nome'];
		}else{
			$nome_usuario_pgto = 'Nenhum!';
		}



		$query2 = $pdo->query("SELECT * FROM usuarios where id = '$usuario_lanc'");
		$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
		$total_reg2 = @count($res2);
		if($total_reg2 > 0){
			$nome_usuario_lanc = $res2[0]['nome'];
		}else{
			$nome_usuario_lanc = 'Sem Referência!';
		}


		if($nome_pessoa == 'Nenhum!' and $nome_func != 'Nenhum!'){
			$nome_pessoa2 = $nome_func;
		} elseif($nome_pessoa != 'Nenhum!' and $nome_func == 'Nenhum!'){
			$nome_pessoa2 = $nome_pessoa;
		}else{
			$nome_pessoa2 = 'Nenhum!';
		}
		

  	 ?>



  	 

      <tr>

<td style="width:25%"><?php echo $descricao ?></td>
<td style="width:10%"><?php echo $tipo ?></td>
<td style="width:10%">R$ <?php echo $valorF ?></td>
<td style="width:10%"><?php echo $data_pgtoF ?></td>
<td style="width:20%"><?php echo $nome_usuario_pgto ?></td>
<td style="width:25%"><?php echo $nome_pessoa2 ?></td>


    </tr>



<?php } } ?>

				</tbody>

	

			</thead>

		</table>

	





</div>

<hr>

		<table>

			<thead>

				<tbody>

					<tr>



						<td style="font-size: 10px; width:270px; text-align: right;"></td>	

						<td style="font-size: 10px; width:120px; text-align: right;"></td>
						

								<td style="font-size: 10px; width:160px; text-align: right;"><b>TOTAL DE PAGAMENTOS <span style="color:green"><?php echo $linhas ?></span></td>

									<td style="font-size: 10px; width:160px; text-align: right;"><b>TOTAL R$ <span style="color:red"><?php echo $total_entradasF ?></span></td>

						

					</tr>

				</tbody>

			</thead>

		</table>


		



</body>



</html>





