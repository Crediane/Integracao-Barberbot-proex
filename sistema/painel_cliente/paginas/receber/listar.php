<?php
// /public_html/sistema/painel_cliente/paginas/receber/listar.php
// Lista somente os pagamentos do cliente autenticado no banco individual da loja atual.

require_once __DIR__ . '/../../verificar.php';

$bb_loja_slug = (string)$bb_cliente_slug;
$id_cliente = (int)$bb_cliente_id;

$tabela = 'receber';
$data_hoje = date('Y-m-d');

$dataInicial = @$_POST['dataInicial'];
$dataFinal = @$_POST['dataFinal'];
$status = '%'.@$_POST['status'].'%';

$total_pago = 0;
$total_a_pagar = 0;

$query = $pdo->prepare("SELECT * FROM receber WHERE pessoa = :cliente ORDER BY id DESC");
$query->execute(array(':cliente' => $id_cliente));
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);
if($total_reg > 0){

echo <<<HTML
	<small>
	<table class="table table-hover" id="tabela">
	<thead> 
	<tr> 
	<th>Descrição</th>	
	<th class="esc">Valor</th> 	
	<th class="esc">Vencimento</th> 	
	<th class="esc">Data PGTO</th> 
	<th class="esc">Arquivo</th>	
	<th>Pagar</th>
	</tr> 
	</thead> 
	<tbody>	
HTML;

for($i=0; $i < $total_reg; $i++){
	foreach ($res[$i] as $key => $value){}
	$id = (int)($res[$i]['id'] ?? 0);	
	$descricao = (string)($res[$i]['descricao'] ?? '');
	$tipo = (string)($res[$i]['tipo'] ?? '');
	$valor = (float)($res[$i]['valor'] ?? 0);
	$data_lanc = (string)($res[$i]['data_lanc'] ?? '');
	$data_pgto = (string)($res[$i]['data_pgto'] ?? '');
	$data_venc = (string)($res[$i]['data_venc'] ?? '');
	$usuario_lanc = (int)($res[$i]['usuario_lanc'] ?? 0);
	$usuario_baixa = (int)($res[$i]['usuario_baixa'] ?? 0);
	$foto = (string)($res[$i]['foto'] ?? ''); // evita NULL no PHP 8.1+
	$pessoa = (int)($res[$i]['pessoa'] ?? 0);
	$pago = (string)($res[$i]['pago'] ?? '');
	$pgto = (string)($res[$i]['pgto'] ?? '');

	$valorF = number_format($valor, 2, ',', '.');
	$data_lancF = $data_lanc ? implode('/', array_reverse(@explode('-', $data_lanc))) : '';
	$data_pgtoF = $data_pgto ? implode('/', array_reverse(@explode('-', $data_pgto))) : '';
	$data_vencF = $data_venc ? implode('/', array_reverse(@explode('-', $data_venc))) : '';


		$query2 = $pdo->prepare("SELECT * FROM clientes WHERE id = :id LIMIT 1");
		$query2->execute(array(':id' => $pessoa));
		$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
		$total_reg2 = @count($res2);
		if($total_reg2 > 0){
			$nome_pessoa = $res2[0]['nome'];
			$telefone_pessoa = $res2[0]['telefone'];
			$classe_whats = '';
		}else{
			$nome_pessoa = 'Nenhum!';
			$telefone_pessoa = '';
			$classe_whats = 'ocultar';
		}


		$query2 = $pdo->prepare("SELECT * FROM usuarios WHERE id = :id LIMIT 1");
		$query2->execute(array(':id' => $usuario_baixa));
		$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
		$total_reg2 = @count($res2);
		if($total_reg2 > 0){
			$nome_usuario_pgto = $res2[0]['nome'];
		}else{
			$nome_usuario_pgto = 'Nenhum!';
		}



		$query2 = $pdo->prepare("SELECT * FROM usuarios WHERE id = :id LIMIT 1");
		$query2->execute(array(':id' => $usuario_lanc));
		$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
		$total_reg2 = @count($res2);
		if($total_reg2 > 0){
			$nome_usuario_lanc = $res2[0]['nome'];
		}else{
			$nome_usuario_lanc = 'Sem Referência!';
		}


		if($pago != "Sim"){
			$classe_alerta = 'text-danger';
			$data_pgtoF = 'Pendente';
			$visivel = '';
			$total_a_pagar += $valor;
		}else{
			$classe_alerta = 'verde';
			$visivel = 'ocultar';
			$total_pago += $valor;
		}


	// extensão do arquivo (evita deprecation quando vazio)
	$ext = '';
	if (!empty($foto)) {
		$ext = strtolower((string)pathinfo($foto, PATHINFO_EXTENSION));
	}

	if($ext == 'pdf'){
		$tumb_arquivo = 'pdf.png';
	}else if($ext == 'rar' || $ext == 'zip'){
		$tumb_arquivo = 'rar.png';
	}else{
		$tumb_arquivo = !empty($foto) ? $foto : 'sem-foto.jpg';
	}


	if($data_venc < $data_hoje and $pago != 'Sim'){
		$classe_debito = 'vermelho-escuro';
	}else{
		$classe_debito = '';
	}

	if($pago == 'Sim'){
		$classe_cor_whats = 'verde';
		$ocultar_baixar = 'ocultar';
	}else{
		$classe_cor_whats = 'text-danger';
		$ocultar_baixar = '';
	}

	$whats = '55'.preg_replace('/[ ()-]+/' , '' , $telefone_pessoa);

	// Link "Efetuar Pagamento" (URL limpa)
	if(!empty($bb_loja_slug)){
		$link_pagar = '/loja/' . rawurlencode($bb_loja_slug) . '/conta/' . rawurlencode((string)$id) . '/';
	}else{
		$link_pagar = '/conta/' . rawurlencode((string)$id);
	}

	// Link do arquivo (caminho absoluto para não quebrar com <base href>)
	$base_contas = '/sistema/painel/img/contas/';
	$link_arquivo = $base_contas . rawurlencode($foto);
	$link_thumb = $base_contas . rawurlencode($tumb_arquivo);

	$col_arquivo = '';
	if (!empty($foto)) {
		$col_arquivo = '<a href="'.$link_arquivo.'" target="_blank"><img src="'.$link_thumb.'" width="27px" class="mr-2"></a>';
	} else {
		$col_arquivo = '<img src="'.$link_thumb.'" width="27px" class="mr-2">';
	}

echo <<<HTML
<tr class="{$classe_debito}">
<td><i class="fa fa-square {$classe_alerta}"></i> {$descricao}</td>
<td class="esc">R$ {$valorF}</td>
<td class="esc">{$data_vencF}</td>
<td class="esc">{$data_pgtoF}</td>

<td>{$col_arquivo}</td>
<td>
	<big><a class="{$ocultar_baixar}" href="{$link_pagar}" target="_blank" title="Efetuar Pagamento"><i class="fa fa-usd verde"></i></a></big>
</td>
</tr>
HTML;

}

$total_pagoF = number_format($total_pago, 2, ',', '.');
$total_a_pagarF = number_format($total_a_pagar, 2, ',', '.');

echo <<<HTML
</tbody>
<small><div align="center" id="mensagem-excluir"></div></small>
</table>

<br>	
<div align="right">Total Pago: <span class="verde">R$ {$total_pagoF}</span> </div>
<div align="right">Total à Pagar: <span class="text-danger">R$ {$total_a_pagarF}</span> </div>

</small>
HTML;


}else{
	echo '<small>Não possui nenhum registro Cadastrado!</small>';
}

?>

<script type="text/javascript">
	$(document).ready( function () {
    $('#tabela').DataTable({
    		"ordering": false,
			"stateSave": true
    	});
    $('#tabela_filter label input').focus();
} );
</script>
