<?php
// ARQUIVO: /sistema/painel_cliente/paginas/combos.php
// Ajuste no padrão do sistema:
// - mantém a página enxuta, carregando a listagem via AJAX
// - usa conexao.php central
// - usa verificar.php do painel_cliente
// - não muda a lógica do combo; apenas delega para combos/listar.php

require_once __DIR__ . '/../verificar.php';

$pag = 'combos';
$bb_loja_slug = (string)$bb_cliente_slug;
?>

<div class="bs-example widget-shadow" style="padding:15px" id="listar"></div>

<div class="modal fade" id="modalHistoricoCombo" tabindex="-1" role="dialog" aria-labelledby="historicoComboLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="historicoComboLabel">Histórico do Combo</h4>
                <button id="btn-fechar-historico-combo" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>

            <div class="modal-body">
                <div id="listar-historico-combo"><small>Carregando...</small></div>
                <small><div align="center" id="mensagem-historico-combo"></div></small>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
    var pag = "<?=$pag?>";
    window.pag = pag;
    var bbLojaSlug = <?php echo json_encode($bb_loja_slug, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;

    function listar(){
        $.ajax({
            url: 'paginas/' + pag + '/listar.php',
            method: 'POST',
            data: {loja: bbLojaSlug},
            dataType: 'html',
            success: function(result){
                $('#listar').html(result);
            }
        });
    }

    function verHistoricoCombo(idAssinatura){
        $('#mensagem-historico-combo').html('');
        $('#listar-historico-combo').html('<small>Carregando...</small>');

        $.ajax({
            url: 'paginas/' + pag + '/listar.php',
            method: 'POST',
            data: {id: idAssinatura, loja: bbLojaSlug},
            dataType: 'html',
            success: function(result){
                $('#listar-historico-combo').html(result);
                $('#modalHistoricoCombo').modal('show');
            },
            error: function(xhr){
                $('#mensagem-historico-combo').addClass('text-danger').html(xhr.responseText || 'Erro ao carregar histórico do combo!');
                $('#modalHistoricoCombo').modal('show');
            }
        });
    }

    $(document).ready(function(){
        listar();
    });
</script>

<script src="js/ajax.js"></script>
