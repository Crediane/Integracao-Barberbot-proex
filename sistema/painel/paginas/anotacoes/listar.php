<?php  
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'anotacoes';

$query = $pdo->prepare("SELECT a.*, 
    c.nome AS cliente, 
    p.nome AS produto, 
    s.nome AS servico 
    FROM $tabela a
    LEFT JOIN clientes c ON a.id_cliente = c.id
    LEFT JOIN produtos p ON a.id_produto = p.id
    LEFT JOIN servicos s ON a.id_servico = s.id
    ORDER BY a.id DESC");

$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = count($res);

if($total_reg > 0){
echo <<<HTML
<small>
<table class="table table-hover" id="tabela">
<thead> 
<tr> 
    <th>Data</th>
    <th>Título</th>
    <th>Cliente</th>
    <th>Produto</th>
    <th>Serviço</th>
    <th>Baixa / Acerto</th>
    <th width="120">Ações</th>
</tr> 
</thead> 
<tbody> 
HTML;

foreach ($res as $r) {
    $id           = $r['id'];
    $titulo       = $r['titulo'];
    $msg          = $r['msg'];
    $dataBanco    = $r['data'];
    $data         = implode('/', array_reverse(explode('-', $dataBanco)));
    $mostrar_home = $r['mostrar_home'];
    $privado      = $r['privado'];

    $cliente      = $r['cliente'];
    $produto      = $r['produto'];
    $servico      = $r['servico'];

    $id_cliente   = $r['id_cliente'];
    $id_produto   = $r['id_produto'];
    $id_servico   = $r['id_servico'];

    $status_acerto = isset($r['status_acerto']) ? $r['status_acerto'] : 'Pendente';

    // mensagem codificada pra não quebrar o JS
    $msgF = rawurlencode($msg);

    $status_label = ($status_acerto == 'Baixado')
        ? '<span class="badge badge-success">Baixado</span>'
        : '<span class="badge badge-warning">Pendente</span>';

echo <<<HTML
<tr>
<td>{$data}</td>
<td>{$titulo}</td>
<td>{$cliente}</td>
<td>{$produto}</td>
<td>{$servico}</td>
<td>{$status_label}</td>

<td style="white-space: nowrap; font-size:12px; vertical-align: middle; min-width:120px;">

    <!-- EDITAR -->
    <a href="javascript:void(0)"
       style="margin-right:2px; display:inline-block; vertical-align:middle;"
       onclick="editar('{$id}',
                       '{$titulo}',
                       '{$msgF}',
                       '{$mostrar_home}',
                       '{$privado}',
                       '{$id_cliente}',
                       '{$id_produto}',
                       '{$id_servico}',
                       '{$dataBanco}',
                       '{$status_acerto}')"
       title="Editar Dados">
        <i class="fa fa-edit text-primary"></i>
    </a>

    <!-- VER DETALHES -->
    <a href="javascript:void(0)"
       style="margin-right:2px; display:inline-block; vertical-align:middle;"
       onclick="mostrar('{$titulo}',
                        '{$msgF}',
                        '{$mostrar_home}',
                        '{$privado}',
                        '{$cliente}',
                        '{$produto}',
                        '{$servico}',
                        '{$data}',
                        '{$status_acerto}')"
       title="Ver Dados">
        <i class="fa fa-info-circle text-secondary"></i>
    </a>

    <!-- EXCLUIR -->
    <a href="javascript:void(0)"
       style="margin-right:2px; display:inline-block; vertical-align:middle;"
       class="btnExcluir"
       data-id="{$id}"
       title="Excluir">
        <i class="fa fa-trash-o text-danger"></i>
    </a>

    <!-- BAIXAR / ACERTO RÁPIDO -->
    <a href="javascript:void(0)"
       style="margin-right:2px; display:inline-block; vertical-align:middle;"
       onclick="baixarAnotacao('{$id}')"
       title="Marcar como Baixado">
        <i class="fa fa-check-square-o text-success"></i>
    </a>

    <!-- PDF -->
    <a href="rel/anotacoes_class.php?id={$id}"
       title="Gerar PDF"
       target="_blank"
       style="margin-right:0; display:inline-block; vertical-align:middle;">
        <i class="fa fa-file-pdf-o text-info"></i>
    </a>

</td>
</tr>
HTML;
}

echo <<<HTML
</tbody>
<small><div align="center" id="mensagem-excluir"></div></small>
</table>
</small>
HTML;

} else {
    echo '<small>Não possui nenhum registro cadastrado!</small>';
}
?>

<script>
// EDITAR – abre o modal com TinyMCE e todos os campos preenchidos
function editar(id, titulo, msgEnc, mostrar_home, privado, id_cliente, id_produto, id_servico, dataBanco, status_acerto){
    var msg = decodeURIComponent(msgEnc);

    $('#id').val(id);
    $('#titulo').val(titulo);
    $('#mostrar_home').val(mostrar_home);
    $('#privado').val(privado);

    $('#id_cliente').val(id_cliente).trigger('change');
    $('#id_produto').val(id_produto).trigger('change');
    $('#id_servico').val(id_servico).trigger('change');

    $('#data').val(dataBanco);
    $('#status_acerto').val(status_acerto || 'Pendente');

    if (typeof tinymce !== 'undefined' && tinymce.get("msg")) {
        tinymce.get("msg").setContent(msg || '');
    } else {
        $('#msg').val(msg);
    }

    $('#titulo_inserir').text('Editar Anotação');
    $('#modalForm').modal('show');
}

// MOSTRAR DETALHES – usa o modal de visualização
function mostrar(titulo, msgEnc, mostrar_home, privado, cliente, produto, servico, data, status_acerto){
    var msg = decodeURIComponent(msgEnc);

    $('#titulo_dados').text(titulo);
    $('#msg_dados').html(msg);
    $('#mostrar_home_dados').text(mostrar_home);
    $('#privado_dados').text(privado);
    $('#cliente_dados').text(cliente);
    $('#produto_dados').text(produto);
    $('#servico_dados').text(servico);
    $('#data_dados').text(data);
    $('#status_acerto_dados').text(status_acerto);

    $('#modalDados').modal('show');
}

// BAIXAR / MARCAR COMO ACERTADO
function baixarAnotacao(id){
    if(!confirm('Confirmar baixa / acerto desta anotação?')) return;

    $.post('paginas/anotacoes/baixar.php', {id: id}, function(resp){
        alert(resp.trim());
        if (typeof listar === 'function') listar();
    }).fail(function(xhr){
        alert('Erro ao baixar: ' + (xhr.responseText || 'Erro desconhecido'));
    });
}
</script>