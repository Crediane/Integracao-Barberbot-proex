<?php
// ARQUIVO: /public_html/sistema/painel_cliente/paginas/planos.php
// Correções (sem alterar regra de negócio):
// - Remove bootstrap de sessão duplicado (sessão/banco já são resolvidos em /sistema/conexao.php)
// - Corrige include do verificar.php (evita procurar em /paginas/verificar.php)
// - Mantém TENANT x MASTER: conexao.php resolve o banco correto

require_once __DIR__ . '/../verificar.php';

$data_hoje = date('Y-m-d');
$pag = 'planos';
?>


<div class="bs-example widget-shadow" style="padding:15px" id="listar">
	
</div>

<script type="text/javascript">var pag = "<?=$pag?>"</script>
<script src="js/ajax.js"></script>
