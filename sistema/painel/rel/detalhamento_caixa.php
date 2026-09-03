<?php 

if (!function_exists('bb_det_caixa_fmt_data')) {
function bb_det_caixa_fmt_data($valor){
    $valor = trim((string)($valor ?? ''));
    if($valor === '' || $valor === '0000-00-00'){
        return '';
    }
    if(preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)){
        return implode('/', array_reverse(explode('-', $valor)));
    }
    return $valor;
}
}

if (!function_exists('bb_det_caixa_fmt_valor')) {
function bb_det_caixa_fmt_valor($valor){
    return number_format((float)($valor ?? 0), 2, ',', '.');
}
}

if (!function_exists('bb_det_caixa_fmt_hora')) {
function bb_det_caixa_fmt_hora($valor){
    $valor = trim((string)($valor ?? ''));
    if($valor === ''){
        return '';
    }
    $ts = @strtotime($valor);
    return $ts ? date("H:i", $ts) : '';
}
}

$data_atual = date('Y-m-d');


$id = isset($id) ? (int)$id : 0;

$query = $pdo->query("SELECT * from caixas where id = '$id'");

$res = $query->fetchAll(PDO::FETCH_ASSOC);

$linhas = @count($res);

if($linhas == 0){

	echo 'Caixa não encontrado!';

	exit();

}else{

$operador = $res[0]['operador'];
$data_abertura = $res[0]['data_abertura'];
$data_fechamento = $res[0]['data_fechamento'];
$valor_abertura = (float)($res[0]['valor_abertura'] ?? 0);
$valor_fechamento = (float)($res[0]['valor_fechamento'] ?? 0);
$quebra = (float)($res[0]['quebra'] ?? 0);
$usuario_abertura = $res[0]['usuario_abertura'];
$usuario_fechamento = $res[0]['usuario_fechamento'];
$obs = trim((string)($res[0]['obs'] ?? ''));

$data_aberturaF = bb_det_caixa_fmt_data($data_abertura);

$data_fechamentoF = bb_det_caixa_fmt_data($data_fechamento);

$valor_aberturaF = bb_det_caixa_fmt_valor($valor_abertura);
$valor_fechamentoF = bb_det_caixa_fmt_valor($valor_fechamento);
$quebraF = bb_det_caixa_fmt_valor($quebra);



$query2 = $pdo->query("SELECT * FROM usuarios where id = '$operador'");

$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);

if(@count($res2) > 0){

	$nome_operador = $res2[0]['nome'];

}else{

	$nome_operador = '';

}





$query2 = $pdo->query("SELECT * FROM usuarios where id = '$usuario_abertura'");

$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);

if(@count($res2) > 0){

	$nome_usu_abertura = $res2[0]['nome'];

}else{

	$nome_usu_abertura = '';

}




$query2 = $pdo->query("SELECT * FROM usuarios where id = '$usuario_fechamento'");

$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);

if(@count($res2) > 0){

	$nome_usu_fechamento = $res2[0]['nome'];

}else{

	$nome_usu_fechamento = '';

}



if((float)$valor_fechamento == 0.0){
	$classe_aberto = 'red';
	$ocultar = '';	
}else{
	$classe_aberto = 'green';
	$ocultar = 'ocultar';	
}

if($quebra != 0){
	$classe_quebra = 'red';
}else{
	$classe_quebra = 'green';
}



if($data_fechamento == ""){
	$data_fechamento_consulta = $data_atual;
}else{
	$data_fechamento_consulta = $data_fechamento;
}


//buscar total movimentado pelo caixa
//totalizar recebimentos
$total_recebido = 0;
$query2 = $pdo->query("SELECT * FROM receber where usuario_baixa = '$operador' and data_pgto >= '$data_abertura' and data_pgto <= '$data_fechamento_consulta' and caixa = '$id'");
$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
if(@count($res2) > 0){
	for($i2=0; $i2 < @count($res2); $i2++){
		$valor_rec = (float)($res2[$i2]['valor'] ?? 0);
		$total_recebido += $valor_rec;
	}
}

//totalizar saidas
$total_saidas = 0;
$query2 = $pdo->query("SELECT * FROM pagar where usuario_baixa = '$operador' and data_pgto >= '$data_abertura' and data_pgto <= '$data_fechamento_consulta' and caixa = '$id'");
$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
if(@count($res2) > 0){
	for($i2=0; $i2 < @count($res2); $i2++){
		$valor_sai = (float)($res2[$i2]['valor'] ?? 0);
		$total_saidas += $valor_sai;
	}
}


$total_caixa = $total_recebido - $total_saidas + (float)$valor_abertura;

$total_caixaF = bb_det_caixa_fmt_valor($total_caixa);
$total_saidasF = bb_det_caixa_fmt_valor($total_saidas);
$total_recebidoF = bb_det_caixa_fmt_valor($total_recebido);	

}




//totalizar sangrias
$total_sangrias = 0;
$query2 = $pdo->query("SELECT * FROM sangrias where caixa = '$id'");
$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
if(@count($res2) > 0){
	for($i2=0; $i2 < @count($res2); $i2++){
		$valor_s = (float)($res2[$i2]['valor'] ?? 0);
		$total_sangrias += $valor_s;
	}
}
$total_sangriasF = bb_det_caixa_fmt_valor($total_sangrias);



$texto_filtro = 'OPERADOR: '.$nome_operador;


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



.marcad{

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

				<td style="border: 1px; solid #000; width: 40%; text-align: left;">

					<img style="margin-top: 7px; margin-left: 7px;" id="imag" src="<?php echo $url_sistema ?>sistema/img/logo_rel.jpg" width="80px">

				</td>

				

				<td style="width: 5%; text-align: center; font-size: 13px;">

				

				</td>

				<td style="width: 40%; text-align: right; font-size: 9px;padding-right: 10px;">

						<b><big>DETALHAMENTO CAIXA</big></b><br> <b>OPERADOR: </b><?php echo mb_strtoupper((string)$nome_operador) ?> <br> <?php echo mb_strtoupper($data_hoje) ?>

				</td>

			</tr>		

		</table>

	</div>



<br>





		<table id="cabecalhotabela" style="border-bottom-style: solid; font-size: 12px; margin-bottom:10px; width: 100%; table-layout: fixed;">

			<thead>

				

				<tr id="cabeca" style="margin-left: 0px; background-color:#CCC; font-weight: bold">

					<td style="width:27%">ABERTURA</td>

					<td style="width:27%">FECHAMENTO</td>

					<td style="width:16%">R$ ABERTURA</td>

					<td style="width:16%">R$ FECHAMENTO</td>

					<td style="width:14%">QUEBRA</td>

					

				</tr>



				<tr id="cabeca" style="margin-left: 0px;">

					<td style="width:27%"><?php echo $data_aberturaF ?> <small>(<?php echo $nome_usu_abertura ?>)</small></td>

					<td style="width:27%"><?php echo $data_fechamentoF ?> <small>(<?php echo $nome_usu_abertura ?>)</small></td>

					<td style="width:16%">R$ <?php echo $valor_aberturaF ?></td>

					<td style="width:16%">R$ <?php echo $valor_fechamentoF ?></td>

					<td style="width:14%; color:<?php echo $classe_quebra ?>">R$ <?php echo $quebraF ?></td>

					

				</tr>

			</thead>

		</table>




		<?php if(trim((string)$obs) != ""){ ?>
		<table>

			<thead>
				<tbody>
					<tr>
						<td style="font-size: 10px; width:332px; text-align: left;"><b>Observações:</b> <?php echo $obs ?></td>					

					</tr>
				</tbody>
			</thead>
		</table>

	<?php } ?>












	<?php if($total_recebido > 0 || $total_saidas > 0){ ?>



			<div style="border-bottom: 1px solid #000; margin-top: 30px">

				<div style="font-size: 11px; margin-bottom: 7px"><b>DETALHAMENTO DE ENTRADAS / TOTAL <span style="color:green">R$ <?php echo $total_recebidoF ?></span></b></div>

			</div>	

			<table id="cabecalhotabela" style="border-bottom-style: solid; font-size: 11px; margin-bottom:10px; width: 100%; table-layout: fixed; margin-top: 5px">

			<thead>

				

				<tr id="cabeca" style="margin-left: 0px; background-color:#CCC; font-weight: bold">

					<?php 
						$query2 = $pdo->query("SELECT * FROM formas_pgto order by id asc");
						$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
						if(@count($res2) > 0){
							for($i2=0; $i2 < @count($res2); $i2++){
								$nome_pgto = $res2[$i2]['nome'];								

								echo '<td style="width:20%">'.$nome_pgto.'</td>';
							}
						}
					 ?>									

				</tr>



				<tr id="cabeca" style="margin-left: 0px;">		

					<?php 
						$query2 = $pdo->query("SELECT * FROM formas_pgto order by id asc");
						$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
						if(@count($res2) > 0){
							for($i2=0; $i2 < @count($res2); $i2++){
								$id_pgto = $res2[$i2]['nome'];	

								//total de entradas por essa forma de pgto	
								$total_recebido_pgto = 0;
								$query3 = $pdo->query("SELECT * FROM receber where usuario_baixa = '$operador' and data_pgto >= '$data_abertura' and data_pgto <= '$data_fechamento_consulta' and pgto = '$id_pgto' and caixa = '$id'");
								$res3 = $query3->fetchAll(PDO::FETCH_ASSOC);
								if(@count($res3) > 0){
									for($i3=0; $i3 < @count($res3); $i3++){
										$valor_rec_pgto = $res3[$i3]['valor'];
										$total_recebido_pgto += $valor_rec_pgto;
									}
								}

								$total_recebido_pgtoF = bb_det_caixa_fmt_valor($total_recebido_pgto);

								echo '<td style="width:20%; color:green">R$ '.$total_recebido_pgtoF.'</td>';
							}
						}
					 ?>				
					

				</tr>

			</thead>

		</table>





		<table id="cabecalhotabela" style="width: 100%; table-layout: fixed; font-size:9px; text-transform: uppercase;">

			<thead>



				<tr id="cabeca" style="margin-left: 0px; background-color:#CCC; font-weight: bold">

					<td style="width:25%">DESCRIÇÃO</td>

					<td style="width:9%">REFERÊNCIA</td>

					<td style="width:10%">VALOR</td>

					<td style="width:9%">DATA</td>

					<td style="width:9%">HORA</td>

					<td style="width:20%">CLIENTE</td>

					<td style="width:17%">FORMA PGTO</td>

					

					

				</tr>

				

					<?php 

$total_servicos = 0;

$total_consumo = 0;

$total_final = 0;

$total_servicosF = 0;

$total_consumoF = 0;

$total_finalF = 0;

$query = $pdo->query("SELECT * from receber where usuario_baixa = '$operador' and data_pgto >= '$data_abertura' and data_pgto <= '$data_fechamento_consulta' and caixa = '$id' and valor > 0 ORDER BY ID ASC");

$res = $query->fetchAll(PDO::FETCH_ASSOC);

$linhas = @count($res);

if($linhas > 0){

for($i=0; $i<$linhas; $i++){

	

$descricao = $res[$i]['descricao'];

$valor = (float)($res[$i]['valor'] ?? 0);

$data_lanc = $res[$i]['data_lanc'];

$data_venc = $res[$i]['data_venc'];

$data_pgto = $res[$i]['data_pgto'];

$usuario_lanc = $res[$i]['usuario_lanc'];

$usuario_baixa = $res[$i]['usuario_baixa'];


$pago = $res[$i]['pago'];

$obs = $res[$i]['obs'];

$cliente = $res[$i]['pessoa'];

$hora = $res[$i]['hora'];

$referencia = $res[$i]['referencia'];

$forma_pgto = $res[$i]['pgto'];


$forma_pgtoF = trim((string)($forma_pgto ?? ''));	




$data_lancF = bb_det_caixa_fmt_data($data_lanc);

$data_vencF = bb_det_caixa_fmt_data($data_venc);

$data_pgtoF = bb_det_caixa_fmt_data($data_pgto);

$valorF = bb_det_caixa_fmt_valor($valor);


$horaF = bb_det_caixa_fmt_hora($hora);



$query2 = $pdo->query("SELECT * FROM usuarios where id = '$usuario_lanc'");

$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);

if(@count($res2) > 0){

	$nome_usu_lanc = $res2[0]['nome'];

}else{

	$nome_usu_lanc = 'Sem Usuário';

}



$query2 = $pdo->query("SELECT * FROM clientes where id = '$cliente'");

$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);

if(@count($res2) > 0){

	$nome_cliente = $res2[0]['nome'];

}else{

	$nome_cliente = '';

}



if($referencia == 'Venda'){

	$total_consumo += $valor;

}else{

	$total_servicos += $valor;

}



$total_final = $total_consumo + $total_servicos;



$total_consumoF = bb_det_caixa_fmt_valor($total_consumo);

$total_servicosF = bb_det_caixa_fmt_valor($total_servicos);

$total_finalF = bb_det_caixa_fmt_valor($total_final);


if($referencia == 'Venda' || $referencia == 'Serviço'){
	$texto_ref = 'VENDA';
}else{
	$texto_ref = '';
}

if($referencia == 'Entrada' || $referencia == 'Restante'){
	$texto_ref2 = 'RESERVA';
}else{
	$texto_ref2 = '';
}


  	 ?>



  	 

      <tr>

<td style="width:25%"><?php echo $descricao ?></td>

<td style="width:9%;"><?php echo $texto_ref2  ?><?php echo $texto_ref ?> </td>

<td style="width:10%; color:green">R$ <?php echo $valorF ?></td>

<td style="width:9%"><?php echo $data_pgtoF ?></td>

<td style="width:9%; "><?php echo $horaF ?></td>

<td style="width:20%; "><?php echo $nome_cliente ?></td>

<td style="width:17%"><?php echo $forma_pgtoF ?></td>



    </tr>



<?php } } ?>

				

	

			</thead>

		</table>












<div style="border-bottom: 1px solid #000; margin-top: 30px">

				<div style="font-size: 11px; margin-bottom: 7px"><b>DETALHAMENTO DE SAÍDAS / TOTAL <span style="color:red">R$ <?php echo $total_saidasF ?></span></b></div>

			</div>	

			<table id="cabecalhotabela" style="border-bottom-style: solid; font-size: 11px; margin-bottom:10px; width: 100%; table-layout: fixed; margin-top: 5px">

			<thead>

				

				<tr id="cabeca" style="margin-left: 0px; background-color:#CCC; font-weight: bold">

					<?php 
						$query2 = $pdo->query("SELECT * FROM formas_pgto order by id asc");
						$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
						if(@count($res2) > 0){
							for($i2=0; $i2 < @count($res2); $i2++){
								$nome_pgto = $res2[$i2]['nome'];								

								echo '<td style="width:20%">'.$nome_pgto.'</td>';
							}
						}
					 ?>									

				</tr>



				<tr id="cabeca" style="margin-left: 0px;">		

					<?php 
						$query2 = $pdo->query("SELECT * FROM formas_pgto order by id asc");
						$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
						if(@count($res2) > 0){
							for($i2=0; $i2 < @count($res2); $i2++){
								$id_pgto = $res2[$i2]['nome'];	

								//total de entradas por essa forma de pgto	
								$total_recebido_pgto = 0;
								$query3 = $pdo->query("SELECT * FROM pagar where usuario_baixa = '$operador' and data_pgto >= '$data_abertura' and data_pgto <= '$data_fechamento_consulta' and pgto = '$id_pgto' and caixa = '$id'");
								$res3 = $query3->fetchAll(PDO::FETCH_ASSOC);
								if(@count($res3) > 0){
									for($i3=0; $i3 < @count($res3); $i3++){
										$valor_rec_pgto = $res3[$i3]['valor'];
										$total_recebido_pgto += $valor_rec_pgto;
									}
								}

								$total_recebido_pgtoF = bb_det_caixa_fmt_valor($total_recebido_pgto);



								echo '<td style="width:20%; color:red">R$ '.$total_recebido_pgtoF.'</td>';
							}
						}
					 ?>				
					

				</tr>

			</thead>

		</table>





		<table id="cabecalhotabela" style="width: 100%; table-layout: fixed; font-size:9px; text-transform: uppercase;">

			<thead>



				<tr id="cabeca" style="margin-left: 0px; background-color:#CCC; font-weight: bold">

					<td style="width:25%">DESCRIÇÃO</td>

					<td style="width:9%">REFERÊNCIA</td>

					<td style="width:10%">VALOR</td>

					<td style="width:9%">DATA</td>

					<td style="width:9%">HORA</td>

					<td style="width:20%">PESSOA</td>

					<td style="width:17%">FORMA PGTO</td>

					

					

				</tr>

				

					<?php 

$total_servicos = 0;

$total_consumo = 0;

$total_final = 0;

$total_servicosF = 0;

$total_consumoF = 0;

$total_finalF = 0;

$query = $pdo->query("SELECT * from pagar where usuario_baixa = '$operador' and data_pgto >= '$data_abertura' and data_pgto <= '$data_fechamento_consulta' and caixa = '$id' ORDER BY ID ASC");

$res = $query->fetchAll(PDO::FETCH_ASSOC);

$linhas = @count($res);

if($linhas > 0){

for($i=0; $i<$linhas; $i++){

	

$descricao = $res[$i]['descricao'];

$valor = (float)($res[$i]['valor'] ?? 0);

$data_lanc = $res[$i]['data_lanc'];

$data_venc = $res[$i]['data_venc'];

$data_pgto = $res[$i]['data_pgto'];

$usuario_lanc = $res[$i]['usuario_lanc'];

$usuario_baixa = $res[$i]['usuario_baixa'];

$pago = $res[$i]['pago'];
$funcionario = $res[$i]['funcionario'];

$hora = $res[$i]['hora'];
$forma_pgto = $res[$i]['pgto'];



$forma_pgtoF = trim((string)($forma_pgto ?? ''));




$data_lancF = bb_det_caixa_fmt_data($data_lanc);

$data_vencF = bb_det_caixa_fmt_data($data_venc);

$data_pgtoF = bb_det_caixa_fmt_data($data_pgto);

$valorF = bb_det_caixa_fmt_valor($valor);


$horaF = bb_det_caixa_fmt_hora($hora);



$query2 = $pdo->query("SELECT * FROM usuarios where id = '$usuario_lanc'");

$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);

if(@count($res2) > 0){

	$nome_usu_lanc = $res2[0]['nome'];

}else{

	$nome_usu_lanc = 'Sem Usuário';

}



if($referencia == 'Venda'){

	$total_consumo += $valor;

}else{

	$total_servicos += $valor;

}



$total_final = $total_consumo + $total_servicos;



$total_consumoF = bb_det_caixa_fmt_valor($total_consumo);

$total_servicosF = bb_det_caixa_fmt_valor($total_servicos);

$total_finalF = bb_det_caixa_fmt_valor($total_final);





$nome_pessoa = 'Sem Registro';

$tipo_pessoa = 'Pessoa';

$pix_pessoa = 'Sem Registro';





$query2 = $pdo->query("SELECT * FROM fornecedores where id = '$funcionario'");

$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);

if(@count($res2) > 0){

	$nome_pessoa = $res2[0]['nome'];	

	$pix_pessoa = $res2[0]['chave_pix'];

	$tipo_pessoa = 'Fornecedor';

}



$query2 = $pdo->query("SELECT * FROM usuarios where id = '$funcionario'");

$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);

if(@count($res2) > 0){

	$nome_pessoa = $res2[0]['nome'];	

	$pix_pessoa = $res2[0]['chave_pix'];

	$tipo_pessoa = 'Funcionário';

}










  	 ?>



  	 

      <tr>

<td style="width:25%"><?php echo $descricao ?></td>

<td style="width:9%;"><?php echo $referencia  ?> </td>

<td style="width:10%; color:red">R$ <?php echo $valorF ?></td>

<td style="width:9%"><?php echo $data_pgtoF ?></td>

<td style="width:9%; "><?php echo $horaF ?></td>

<td style="width:20%; "><?php echo $nome_pessoa ?></td>

<td style="width:17%"><?php echo $forma_pgtoF ?></td>



    </tr>



<?php } } ?>

				

	

			</thead>

		</table>
	



	<?php } ?>









<?php if($total_sangrias > 0){ ?>

<div style="border-bottom: 1px solid #000; margin-top: 30px">

				<div style="font-size: 11px; margin-bottom: 7px"><b>DETALHAMENTO DE SANGRIAS / TOTAL <span style="color:red">R$ <?php echo $total_sangriasF ?></span></b></div>

			</div>	

			


		<table id="cabecalhotabela" style="width: 100%; table-layout: fixed; font-size:9px; text-transform: uppercase;">

			<thead>



				<tr id="cabeca" style="margin-left: 0px; background-color:#CCC; font-weight: bold">

					<td style="width:20%">VALOR</td>

					<td style="width:40%">FEITA POR</td>

					<td style="width:20%">DATA</td>

					<td style="width:20%">HORA</td>			
					

					

				</tr>

				

					<?php 


$query = $pdo->query("SELECT * from sangrias where caixa = '$id' ORDER BY ID ASC");

$res = $query->fetchAll(PDO::FETCH_ASSOC);

$linhas = @count($res);

if($linhas > 0){

for($i=0; $i<$linhas; $i++){


$valor = (float)($res[$i]['valor'] ?? 0);
$usuario = $res[$i]['usuario'];
$data = $res[$i]['data'];
$hora = $res[$i]['hora'];




$dataF = bb_det_caixa_fmt_data($data);
$valorF = bb_det_caixa_fmt_valor($valor);
$horaF = bb_det_caixa_fmt_hora($hora);


$query2 = $pdo->query("SELECT * FROM usuarios where id = '$usuario'");

$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);

if(@count($res2) > 0){

	$nome_usu_lanc = $res2[0]['nome'];

}else{

	$nome_usu_lanc = 'Sem Usuário';

}


  	 ?>



  	 

      <tr>

<td style="width:20%; color:red">R$ <?php echo $valorF ?></td>
<td style="width:40%"><?php echo $nome_usu_lanc ?></td>
<td style="width:20%; "><?php echo $dataF ?></td>
<td style="width:20%; "><?php echo $horaF ?></td>





    </tr>



<?php } } ?>

				

	

			</thead>

		</table>




<?php } ?>







<hr>
<br><br>
<div align="center">
	_____________________________________________________________<br>
	<small><small>(Assinatura)</small></small>
</div>


</div>




<div id="footer" class="row">

<hr style="margin-bottom: 0;">

	<table style="width:100%;">

		<tr style="width:100%;">

			<td style="width:50%; font-size: 10px; text-align: left;"><?php echo $nome_sistema ?> Telefone: <?php echo $telefone_sistema ?></td>



			<td style="width:50%; font-size: 10px; text-align: right;"> Endereço: <?php echo $endereco_sistema ?></td>

			

		</tr>

	</table>

</div>






</body>



</html>





