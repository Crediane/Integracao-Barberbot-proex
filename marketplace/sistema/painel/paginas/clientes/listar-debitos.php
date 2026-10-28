<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'receber';
$data_atual = date('Y-m-d');

/*
  ✅ Sanitização mínima (sem mudar lógica):
  - id sempre numérico para evitar erros e manter padrão seguro
*/
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$id_cli = $id;

// pegar a pagina atual
if (@$_POST['pagina'] == "") {
    @$_POST['pagina'] = 0;
}

if ($id <= 0) {
    echo '<small>Cliente inválido!</small>';
    exit;
}

/*
  ✅ Função padrão para evitar Deprecated do explode() com NULL
  - Mantém a lógica: apenas formata data se existir
*/
function bb_data_br($data_mysql)
{
    $data_mysql = (string)$data_mysql;

    if ($data_mysql === '' || $data_mysql === '0000-00-00') {
        return '';
    }

    return implode('/', array_reverse(explode('-', $data_mysql)));
}

/*
  ✅ Prepared Statement (sem mudar regra)
*/
$stmt = $pdo->prepare("SELECT * FROM $tabela WHERE pessoa = :pessoa AND pago != 'Sim' ORDER BY data_venc ASC");
$stmt->bindValue(':pessoa', $id, PDO::PARAM_INT);
$stmt->execute();
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_reg = @count($res);
if ($total_reg > 0) {

    echo <<<HTML
	<small>
	<table class="table table-hover">
	<thead> 
	<tr> 
	<th class="">Descrição Conta</th>
	<th>Data Vencimento</th>	
	<th class="">Valor</th>
	<th class="">Dar Baixa</th>	
	</tr> 
	</thead> 
	<tbody>	
HTML;

    $total = 0;
    $total_pagar = 0;
    $total_vencido = 0;
    $totalF = 0;
    $total_pagarF = 0;
    $total_vencidoF = 0;

    for ($i = 0; $i < $total_reg; $i++) {
        foreach ($res[$i] as $key => $value) {
        }

        $id_receber = (int)$res[$i]['id'];
        $descricao = $res[$i]['descricao'];
        $tipo = $res[$i]['tipo'];
        $valor = $res[$i]['valor'];
        $data_lanc = $res[$i]['data_lanc'];
        $data_pgto = $res[$i]['data_pgto'];
        $data_venc = $res[$i]['data_venc'];
        $usuario_lanc = $res[$i]['usuario_lanc'];
        $usuario_baixa = $res[$i]['usuario_baixa'];
        $foto = $res[$i]['foto'];
        $pessoa = (int)$res[$i]['pessoa'];
        $pago = $res[$i]['pago'];
        $pgto = $res[$i]['pgto'];

        /*
          ✅ Prepared Statement (sem mudar regra)
        */
        $stmt2 = $pdo->prepare("SELECT * FROM clientes WHERE id = :id");
        $stmt2->bindValue(':id', $pessoa, PDO::PARAM_INT);
        $stmt2->execute();
        $res2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);

        $total_reg2 = @count($res2);
        if ($total_reg2 > 0) {
            $nome_pessoa = $res2[0]['nome'];
            $telefone_pessoa = $res2[0]['telefone'];
            $classe_whats = '';
        } else {
            $nome_pessoa = 'Nenhum!';
            $telefone_pessoa = '';
            $classe_whats = 'ocultar';
        }

        //
        $valorF = number_format((float)$valor, 2, ',', '.');

        // ✅ aqui era o erro (explode com null). Agora não dá Deprecated.
        $data_lancF = bb_data_br($data_lanc);
        $data_pgtoF = bb_data_br($data_pgto);
        $data_vencF = bb_data_br($data_venc);

        $total += (float)$valor;
        $totalF = number_format((float)$total, 2, ',', '.');

        $classe_data = '';
        if (strtotime($data_venc) < strtotime($data_atual)) {
            $classe_data = 'text-danger';
            $total_vencido += (float)$valor;
        } else {
            $total_pagar += (float)$valor;
        }

        $total_pagarF = number_format((float)$total_pagar, 2, ',', '.');
        $total_vencidoF = number_format((float)$total_vencido, 2, ',', '.');

        echo <<<HTML
<tr class="">
<td>{$descricao}</td>
<td class="{$classe_data}">{$data_vencF}</td>
<td class="text-danger">R$ {$valor}</td>
<td>
	<big><a class="" href="#" onclick="baixar('{$id_receber}', '{$valor}', '{$descricao}', '{$pgto}', '{$pessoa}')" title="Baixar Conta"><i class="fa fa-check-square verde"></i></a></big>
</td>
</tr>
HTML;
    }

    echo <<<HTML
</tbody>
<small><div align="center" id="mensagem-excluir-baixar"></div></small>
</table>
<div align="right"><span style="margin-right: 20px; ">Total Vencido <span style="color:red">R$ {$total_vencidoF}</span></span>
<span style="margin-right: 20px; ">Total à Vencer <span style="color:blue">R$ {$total_pagarF}</span></span>
<span >Total Pagar <span style="color:green">R$ {$totalF}</span></span>
</div>
</small>

HTML;
} else {
    echo '<small>Este Cliente não possui pagamento pendente!</small>';
}
?>

<script type="text/javascript">
	function cobrar(id, valor, data, telefone, descricao){

	var instancia = "<?=$instancia?>";
	var token = "<?=$token?>";
	

	if(instancia.trim() == "" || token.trim() == ""){
		alert('Insira um Token e Instancia Whatsapp nas configurações');
		return;
	}
	$.ajax({
	        url: 'paginas/receber/gerar_cobranca.php',
	        method: 'POST',
	        data: {id, valor, data, telefone, descricao},
	        dataType: "html",

	        success:function(result){	
	        	alert('Cobrança Efetuada!');           	
	        }
	    });
}
</script>


<script type="text/javascript">
	function baixar(id, valor, descricao, pgto, cliente){

		$('#id_baixar').val(id);
		$('#valor_baixar').val(valor);	
		mascara_valor('valor_baixar');		
		$('#titulo_baixar').text(descricao);
		$('#pgto_baixar').val(pgto).change();
		
		$('#cliente_baixar').val(cliente);	

		$('#modalBaixar').modal('show');
	}
</script>
