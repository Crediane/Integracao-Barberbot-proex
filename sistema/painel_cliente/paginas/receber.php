<?php
// ARQUIVO: /public_html/sistema/painel_cliente/paginas/receber.php
// Correções (sem alterar regra de negócio):
// - Remove bootstrap de sessão duplicado (sessão/banco já são resolvidos em /sistema/conexao.php)
// - Corrige include do verificar.php (evita procurar em /paginas/verificar.php)
// - Corrige caminho dos arquivos de pagamentos (pasta /public_html/pagamentos)
// - Mantém TENANT x MASTER: conexao.php resolve o banco correto

require_once __DIR__ . '/../verificar.php';

$pag = 'receber';

// Verifica somente cobranças do cliente autenticado nesta loja.
$id_cliente = (int)$bb_cliente_id;
$query = $pdo->prepare("SELECT * FROM receber WHERE pessoa = :cliente AND pago = 'Não' AND ref_pix IS NOT NULL ORDER BY id DESC");
$query->execute(array(':cliente' => $id_cliente));
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = count($res);
if ($total_reg > 0) {
    for ($i = 0; $i < $total_reg; $i++) {
        $ref_pix = (string)($res[$i]['ref_pix'] ?? '');
        $id = (int)($res[$i]['id'] ?? 0);

        if ($id > 0 && $ref_pix !== '') {
            require_once __DIR__ . '/../../../pagamentos/consultar_pagamento.php';
            if (isset($status_api) && $status_api === 'approved') {
                require_once __DIR__ . '/../../../pagamentos/baixar_conta.php';
            }
        }
    }
}
?>

<div class="bs-example widget-shadow" style="padding:15px">

	<hr>
	<div id="listar">

	</div>
	
</div>

<script type="text/javascript">var pag = "<?=$pag?>"</script>
<script src="js/ajax.js"></script>
