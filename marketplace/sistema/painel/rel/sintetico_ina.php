<?php
if (!function_exists('bb_sint_ina_slug_limpo')) {
    function bb_sint_ina_slug_limpo($valor)
    {
        $valor = strtolower(trim((string)$valor));
        return preg_replace('/[^a-z0-9_-]/', '', $valor);
    }
}

if (!function_exists('bb_sint_ina_resolver_slug')) {
    function bb_sint_ina_resolver_slug()
    {
        $reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $refPath = !empty($_SERVER['HTTP_REFERER']) ? (parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: '') : '';

        if (!empty($_GET['loja'])) {
            return bb_sint_ina_slug_limpo($_GET['loja']);
        }

        if (!empty($_POST['loja'])) {
            return bb_sint_ina_slug_limpo($_POST['loja']);
        }

        if (!empty($_SESSION['tenant_slug'])) {
            return bb_sint_ina_slug_limpo($_SESSION['tenant_slug']);
        }

        if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $reqPath, $m)) {
            return bb_sint_ina_slug_limpo($m[1]);
        }

        if ($refPath !== '' && preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $refPath, $m2)) {
            return bb_sint_ina_slug_limpo($m2[1]);
        }

        return '';
    }
}

if (!function_exists('bb_sint_ina_reaplicar_tenant')) {
    function bb_sint_ina_reaplicar_tenant()
    {
        $slug = bb_sint_ina_resolver_slug();
        if ($slug === '') {
            return false;
        }

        if (empty($_GET['loja'])) {
            $_GET['loja'] = $slug;
        }

        $banco = (string)($_SESSION['tenant_db'] ?? '');
        $servidor = (string)($_SESSION['tenant_host'] ?? 'localhost');
        $usuario = (string)($_SESSION['tenant_user'] ?? '');
        $senha = (string)($_SESSION['tenant_pass'] ?? '');

        if ($banco === '' || $usuario === '') {
            return false;
        }

        try {
            $pdoTenant = new PDO(
                "mysql:host={$servidor};dbname={$banco};charset=utf8mb4",
                $usuario,
                $senha,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            $GLOBALS['pdo'] = $pdoTenant;

            try {
                $qConf = $pdoTenant->query("SELECT * FROM config LIMIT 1");
                $dConf = $qConf->fetch(PDO::FETCH_ASSOC);

                if ($dConf) {
                    $GLOBALS['nome_sistema'] = $dConf['nome'] ?? ($GLOBALS['nome_sistema'] ?? 'BarberBot');
                    $GLOBALS['whatsapp_sistema'] = $dConf['telefone_whatsapp'] ?? ($dConf['telefone'] ?? ($GLOBALS['whatsapp_sistema'] ?? ''));
                }
            } catch (Throwable $e) {
                // mantém o relatório mesmo sem config
            }

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    require_once __DIR__ . '/../../conexao.php';
}

require_once __DIR__ . '/data_formatada.php';

if (isset($pdo) && $pdo instanceof PDO) {
    bb_sint_ina_reaplicar_tenant();
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
				<td style="border: 1px; solid #000; width: 7%; text-align: left;">
					<img style="margin-top: 7px; margin-left: 7px;" id="imag" src="<?php echo $url_sistema ?>sistema/img/logo_rel.jpg" width="80px">
				</td>
				<td style="width: 30%; text-align: left; font-size: 13px;">
					
				</td>
				<td style="width: 1%; text-align: center; font-size: 13px;">
				
				</td>
				<td style="width: 47%; text-align: right; font-size: 9px;padding-right: 10px;">
						<b><big>RELATÓRIO DE  INADIMPLÊNTES</big></b><br>  <br> <?php echo mb_strtoupper($data_hoje) ?>
				</td>
			</tr>		
		</table>
	</div>

<br>


		<table id="cabecalhotabela" style="border-bottom-style: solid; font-size: 9px; margin-bottom:10px; width: 100%; table-layout: fixed;">
			<thead>
				
				<tr id="cabeca" style="margin-left: 0px; background-color:#CCC">
					<td style="width:50%">CLIENTE</td>
					<td style="width:25%">TOTAL DÉBITO</td>											
					<td style="width:25%">TELEFONE</td>
					
					
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

$total_ina = 0;
$total_inaF = 0;

$query9 = $pdo->query("SELECT * from clientes order by nome asc");

$res9 = $query9->fetchAll(PDO::FETCH_ASSOC);
$linhas9 = @count($res9);
if($linhas9 > 0){
for($i9=0; $i9<$linhas9; $i9++){
	$id_pessoa = $res9[$i9]['id'];
	$nome_pessoa = $res9[$i9]['nome'];
	$tel_pessoa = $res9[$i9]['telefone'];
	


$total_valor = 0;
$total_valorF = 0;



$query = $pdo->query("SELECT * from receber where data_venc < curDate() and pago != 'Sim' and pessoa = '$id_pessoa'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$linhas = @count($res);
if($linhas > 0){
for($i=0; $i<$linhas; $i++){
	$id = $res[$i]['id'];
$descricao = $res[$i]['descricao'];
$valor = $res[$i]['valor'];
$data_lanc = $res[$i]['data_lanc'];
$data_venc = $res[$i]['data_venc'];
$data_pgto = $res[$i]['data_pgto'];
$usuario_lanc = $res[$i]['usuario_lanc'];

$pago = $res[$i]['pago'];
$obs = $res[$i]['obs'];
$referencia = $res[$i]['referencia'];

$total_valor += $valor;
$total_valorF = @number_format($total_valor, 2, ',', '.');

} 



}else{
	//continue para ele pular para o proximo
	continue;
	//se usar break ele para toda instrução
}
$total_ina += $total_valor;
$total_inaF = @number_format($total_ina, 2, ',', '.');

  	 ?>

  	 
<tr>
<td style="width:50%">
	<?php echo $nome_pessoa ?></td>
<td style="width:25%; color:red">R$ <?php echo $total_valorF ?></td>

<td style="width:25%; color:green"> <?php echo $tel_pessoa ?></td>
    </tr>

<?php } }  ?>
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

									<td style="font-size: 10px; width:120px; text-align: right;"><b>Total: <span style="color:red">R$ <?php echo $total_inaF ?></span></td>
						
					</tr>
				</tbody>
			</thead>
		</table>
		
</body>

</html>


