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

<?php
if (!function_exists('bb_valor_moeda_rel')) {
    function bb_valor_moeda_rel($valor): string
    {
        $numero = is_numeric($valor) ? (float) $valor : 0.0;
        return number_format($numero, 2, ',', '.');
    }
}

if (!function_exists('bb_data_br_rel')) {
    function bb_data_br_rel($data): string
    {
        $data = trim((string) $data);
        if ($data === '' || $data === '0000-00-00') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            return implode('/', array_reverse(explode('-', $data)));
        }

        return $data;
    }
}
?>


<div id="header" >

	<div style="border-style: solid; font-size: 10px; height: 50px;">
		<table style="width: 100%; border: 0px solid #ccc;">
			<tr>
				<td style="border: 1px; solid #000; width: 7%; text-align: left;">
					<img style="margin-top: 7px; margin-left: 7px;" id="imag" src="<?php echo $url_sistema ?>sistema/img/logo_rel.jpg" width="80px">
				</td>
				<td style="width: 30%; text-align: left; font-size: 13px;">
					
				</td>
				<td style="width: 1%; text-align: center; font-size: 13px;">
				
				</td>
				<td style="width: 47%; text-align: right; font-size: 9px;padding-right: 10px;">
						<b><big>RELATÓRIO DE ASSINATURAS </big></b>
							<br>
							<br> <?php echo mb_strtoupper($data_hoje) ?>
				</td>
			</tr>		
		</table>
	</div>

<br>


		<table id="cabecalhotabela" style="border-bottom-style: solid; font-size: 10px; margin-bottom:10px; width: 100%; table-layout: fixed;">
			<thead>
				
				<tr id="cabeca" style="margin-left: 0px; background-color:#CCC">
					<td style="width:25%">CLIENTE</td>
					<td style="width:40%">ASSINATURA</td>
					<td style="width:15%">PLANO</td>
					<td style="width:10%">VALOR</td>
					<td style="width:10%">DATA</td>						
					
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



		<table style="width: 100%; table-layout: fixed; font-size:8px; text-transform: uppercase;">
			<thead>
				<tbody>
					<?php
$query = $pdo->query("SELECT * from assinaturas order by id desc");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$linhas = @count($res);
if($linhas > 0){

for($i=0; $i<$linhas; $i++){
	$id = $res[$i]['id'];
	$cliente = $res[$i]['cliente'];	
	$data = $res[$i]['data'];
	$grupo = $res[$i]['grupo'];	
	$item = $res[$i]['item'];	
	$pago = $res[$i]['pago'];
	$valor = $res[$i]['valor'];	
	$ref_pix = $res[$i]['ref_pix'];	
	$frequencia = $res[$i]['frequencia'];
	$vencimento = $res[$i]['vencimento'];	
	$cancelado = $res[$i]['cancelado'];	
	
		$query2 = $pdo->query("SELECT * FROM grupo_assinaturas where id = '$grupo'");
		$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
		$total_reg2 = @count($res2);
		if($total_reg2 > 0){
			$nome_grupo = $res2[0]['nome'];
		}else{
			$nome_grupo = 'Nenhum!';
		}

		$query2 = $pdo->query("SELECT * FROM itens_assinaturas where id = '$item'");
		$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
		$total_reg2 = @count($res2);
		if($total_reg2 > 0){
			$nome_item = $res2[0]['nome'];
		}else{
			$nome_item = 'Nenhum!';
		}

		$query2 = $pdo->query("SELECT * FROM clientes where id = '$cliente'");
		$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
		$total_reg2 = @count($res2);
		if($total_reg2 > 0){
			$nome_cliente = $res2[0]['nome'];
		}else{
			$nome_cliente = 'Nenhum!';
		}
	
		$valorF = bb_valor_moeda_rel($valor);
	$dataF = bb_data_br_rel($data);

	if($pago != "Sim"){
			$classe_alerta = 'text-danger';			
			$visivel = '';			
		}else{
			$classe_alerta = 'verde';
			$visivel = 'ocultar';			
		}


	$texto_cancelado = '';		
		if($cancelado == 'Sim'){			
			$texto_cancelado = '<span style="color:red"> (Cancelado) </span>';	
		}


  	 ?>

  	 
      <tr>
<td style="width:25%">
	<?php echo $nome_cliente ?></td>
<td style="width:40%"><?php echo $nome_grupo ?> <?php echo $texto_cancelado ?></td>
<td style="width:15%"><?php echo $nome_item ?></td>
<td style="width:10%">R$ <?php echo $valorF ?></td>
<td style="width:10%"><?php echo $dataF ?></td>

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

						<td style="font-size: 10px; width:300px; text-align: right;"></td>

						

						<td style="font-size: 10px; width:70px; text-align: right;"></td>

							<td style="font-size: 10px; width:70px; text-align: right;"></td>


								<td style="font-size: 10px; width:140px; text-align: right;"></td>

									<td style="font-size: 10px; width:120px; text-align: right;"><b>Total Itens: <span style="color:green"><?php echo $linhas ?></span></td>
						
					</tr>
				</tbody>
			</thead>
		</table>

</body>

</html>




 