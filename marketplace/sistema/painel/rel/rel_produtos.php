<?php
// ============================================================================
// BarberBot Software - Relatório de Produtos
// Arquivo: /public_html/sistema/painel/rel/rel_produtos.php
// Ajustes: compatível com Admin do Sistema e Dono do Estabelecimento (bancos separados)
//          mantendo o padrão MASTER x TENANT em /loja/{slug}/.
// Regra de negócio preservada.
// ============================================================================

if (!defined('BB_RELATORIO_TENANT_BOOTSTRAP')) {
    define('BB_RELATORIO_TENANT_BOOTSTRAP', 1);

    if (!function_exists('bb_rel_clean_slug')) {
        function bb_rel_clean_slug($valor)
        {
            $valor = strtolower(trim((string)$valor));
            return preg_replace('/[^a-z0-9_-]/', '', $valor);
        }
    }

    if (!function_exists('bb_rel_extract_slug_from_path')) {
        function bb_rel_extract_slug_from_path($path)
        {
            $path = (string)$path;
            if ($path !== '' && preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $path, $match)) {
                return bb_rel_clean_slug($match[1]);
            }
            return '';
        }
    }

    if (!function_exists('bb_rel_pick_tenant_session_name')) {
        function bb_rel_pick_tenant_session_name($slug)
        {
            $slug = bb_rel_clean_slug($slug);
            if ($slug === '') {
                return 'BB_TENANT';
            }
            return 'BB_TENANT_' . strtoupper(substr(sha1($slug), 0, 12));
        }
    }

    $__bb_reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $__bb_refPath = '';
    if (!empty($_SERVER['HTTP_REFERER'])) {
        $__bb_refPath = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: '';
    }

    $__bb_reqSlug = '';
    if (!empty($_GET['loja'])) {
        $__bb_reqSlug = bb_rel_clean_slug($_GET['loja']);
    } elseif (!empty($_POST['loja'])) {
        $__bb_reqSlug = bb_rel_clean_slug($_POST['loja']);
    }

    if ($__bb_reqSlug === '') {
        $__bb_reqSlug = bb_rel_extract_slug_from_path($__bb_reqPath);
    }

    if ($__bb_reqSlug === '') {
        $__bb_reqSlug = bb_rel_extract_slug_from_path($__bb_refPath);
    }

    $__bb_lojaByPath = (bb_rel_extract_slug_from_path($__bb_reqPath) !== '') || (bb_rel_extract_slug_from_path($__bb_refPath) !== '');
    $__bb_hasLoja = $__bb_lojaByPath || !empty($_GET['loja']) || !empty($_POST['loja']) || $__bb_reqSlug !== '';
    $__bb_isSistema = (strpos($__bb_reqPath, '/sistema') === 0) || (strpos($__bb_refPath, '/sistema') === 0);
    $__bb_forceMaster = ($__bb_isSistema && !$__bb_hasLoja);

    $__bb_desiredSession = $__bb_forceMaster ? 'BB_MASTER' : ($__bb_hasLoja ? bb_rel_pick_tenant_session_name($__bb_reqSlug) : 'BB_SITE');

    if (session_status() === PHP_SESSION_ACTIVE) {
        if (session_name() !== $__bb_desiredSession) {
            session_write_close();
            @session_name($__bb_desiredSession);
            if (!empty($_COOKIE[$__bb_desiredSession])) {
                @session_id((string)$_COOKIE[$__bb_desiredSession]);
            }
            @session_start();
        }
    } else {
        @session_name($__bb_desiredSession);
        if (!empty($_COOKIE[$__bb_desiredSession])) {
            @session_id((string)$_COOKIE[$__bb_desiredSession]);
        }
        @session_start();
    }

    if ($__bb_reqSlug === '' && !empty($_SESSION['tenant_slug'])) {
        $__bb_reqSlug = bb_rel_clean_slug($_SESSION['tenant_slug']);
    }

    if ($__bb_reqSlug !== '') {
        $_GET['loja'] = $__bb_reqSlug;
        $_POST['loja'] = $_POST['loja'] ?? $__bb_reqSlug;
        $_SESSION['tenant_slug'] = $_SESSION['tenant_slug'] ?? $__bb_reqSlug;
    }
}

// ✅ Evita Notice caso não venha definido
$token_rel = $token_rel ?? '';

if ($token_rel != 'A5030') {
	echo '<script>window.location="../../"</script>';
	exit();
}

// ✅ Inicializa para evitar warning no rodapé
$total_reg = 0;

$hoje = date('Y-m-d');
$mes_atual = Date('m');
$ano_atual = Date('Y');
$dataInicioMes = $ano_atual . "-" . $mes_atual . "-01";
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

						<b><big>RELATÓRIO DE PRODUTOS</big></b><br><br> <?php echo @mb_strtoupper($data_hoje) ?>

				</td>

			</tr>		

		</table>

	</div>



<br>





		<table id="cabecalhotabela" style="border-bottom-style: solid; font-size: 8px; margin-bottom:10px; width: 100%; table-layout: fixed;">

			<thead>				

				<tr id="cabeca" style="margin-left: 0px; background-color:#CCC">
					<td style="width:30%">NOME</td>
					<td style="width:25%">CATEGORIA</td>
					<td style="width:15%">VALOR COMPRA</td>
					<td style="width:15%">VALOR VENDA</td>
					<td style="width:15%">ESTOQUE</td>
						
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



$estoque_baixo = 0;
		$query = $pdo->query("SELECT * FROM produtos ORDER BY nome asc");
$res = $query->fetchAll(PDO::FETCH_ASSOC);

$linhas = @count($res);

if($linhas > 0){

for($i=0; $i<$linhas; $i++){
$id = $res[$i]['id'];
	$nome = $res[$i]['nome'];	
	$descricao = $res[$i]['descricao'];
	$categoria = $res[$i]['categoria'];
	$valor_compra = $res[$i]['valor_compra'];
	$valor_venda = $res[$i]['valor_venda'];
	$foto = $res[$i]['foto'];
	$estoque = $res[$i]['estoque'];
	$nivel_estoque = $res[$i]['nivel_estoque'];

	$valor_vendaF = number_format($valor_venda, 2, ',', '.');
	$valor_compraF = number_format($valor_compra, 2, ',', '.');


				//extensão do arquivo


	

		$query2 = $pdo->query("SELECT * FROM cat_produtos where id = '$categoria'");
		$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
		$total_reg2 = @count($res2);
		if($total_reg2 > 0){
			$nome_cat = $res2[0]['nome'];
		}else{
			$nome_cat = 'Sem Referência!';
		}


		if($nivel_estoque >= $estoque){
			$alerta_estoque = 'text-danger';
			$estoque_baixo += 1;
		}else{
			$alerta_estoque = '';
		}
		

  	 ?>



  	 

      <tr>

<td style="width:30%"><img src="<?php echo $url_sistema ?>/sistema/painel/img/produtos/<?php echo $foto ?>" width="27px" height="27px">
<?php echo $nome ?></td>
<td style="width:25%"><?php echo $nome_cat ?></td>
<td style="width:15%">R$ <?php echo $valor_compraF ?></td>
<td style="width:15%">R$ <?php echo $valor_vendaF ?></td>
<td style="width:15%"><?php echo $estoque ?></td>
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
						

								<td style="font-size: 10px; width:160px; text-align: right;"><b>PRODUTOS ESTOQUE BAIXO <span style="color:green"><?php echo $estoque_baixo ?></span></td>

									<td style="font-size: 10px; width:160px; text-align: right;"><b>TOTAL DE PRODUTOS <span style="color:red"><?php echo $total_reg ?></span></td>

						

					</tr>

				</tbody>

			</thead>

		</table>


		



</body>



</html>



