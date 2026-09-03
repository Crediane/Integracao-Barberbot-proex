<?php
require_once __DIR__ . '/../tenant_context.php';
?>

<div class="content">
	<div class="d-flex">
		<div>
			<h1 class="mb-0 font-20">Contas a Pagar</h1>
		</div>
		<div class="ms-auto me-n2">
			<a href="#" data-bs-dismiss="offcanvas" class="icon icon-xs mt-n1"><i class="bi bi-x-circle-fill color-red-dark font-18"></i></a>
		</div>
	</div>

	<div class="divider mt-0 mb-2"></div>

<?php
$query = $pdo->query("SELECT * from pagar where data_venc < curDate() and pago != 'Sim' order by id asc");

$res = $query->fetchAll(PDO::FETCH_ASSOC);
$linhas = @count($res);
for ($i = 0; $i < $linhas; $i++) {
	$valor = $res[$i]['valor'];
	$descricao = $res[$i]['descricao'];
	$vencimento = $res[$i]['data_venc'];
	$valorF = @number_format($valor, 2, ',', '.');
	$vencimentoF = implode('/', array_reverse(@explode('-', $vencimento)));
	?>

		<div class="d-flex">
			<div class="align-self-center">
				<h5 class="font-15 mb-0 ps-1 pt-1" style="color:red">R$ <?php echo $valorF ?></h5>
				<p class="ps-1 pb-1 mb-0 font-12 line-height-s opacity-80"><?php echo $descricao ?></p>
				<p class="ps-1 pb-1 mb-0 font-12 line-height-s opacity-80"><?php echo $vencimentoF ?></p>

	
			</div>
		</div>

<div class="divider mt-2 mb-2"></div>
	<?php } ?>



	<a href="pagar" class="btn btn-full btn-m gradient-highlight shadow-bg shadow-bg-s mt-3"  onclick="navigateToPage(event, 'pagar')">Ver Todas</a>
</div>
