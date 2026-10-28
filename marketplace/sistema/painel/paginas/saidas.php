<?php 
require_once __DIR__ . '/_context_bootstrap.php';
require_once __DIR__ . '/../verificar.php';
require_once __DIR__ . '/../../conexao.php';

$pag = 'saidas';

//verificar se ele tem a permissão de estar nessa página
if(@$saidas == 'ocultar'){
    echo "<script>window.location='<?php echo $bb_painel_public_url; ?>/'</script>";
    exit();
}

?>


<div class="bs-example widget-shadow" style="padding:15px" id="listar">
	
</div>



<script type="text/javascript">var pag = "<?=$pag?>"</script>
<script src="js/ajax.js"></script>

