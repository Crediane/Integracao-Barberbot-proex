<?php
// /public_html/sistema/painel_cliente/paginas/agendamentos.php
// Página de agendamentos do cliente no banco individual da loja atual.

require_once __DIR__ . '/../verificar.php';

$bb_loja_slug = (string)$bb_cliente_slug;
$data_hoje = date('Y-m-d');
$pag = 'agendamentos';
?>

<div class="bs-example widget-shadow" style="padding:15px" id="listar"></div>

<script type="text/javascript">
    var pag = <?php echo json_encode($pag, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
    var bbLojaSlug = <?php echo json_encode($bb_loja_slug, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
</script>
<script src="js/ajax.js"></script>
