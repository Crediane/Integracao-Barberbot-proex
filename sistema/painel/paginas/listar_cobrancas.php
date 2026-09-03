<?php 
require_once __DIR__ . '/_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$pagina = 'cobrancas';
$id = $_POST['id'];



echo <<<HTML
<small>
HTML;
$query = $pdo->query("SELECT * FROM $pagina where cliente = '$id'  order by id desc");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);
if($total_reg > 0){
echo <<<HTML
	<table class="table table-hover" id="">
		<thead> 
			<tr> 				
				<th>Valor</th>
				<th>Parcelas</th>				
				<th>Vencimento</th>	
				<th>Data</th>
				<th>Ver Parcelas</th>
			</tr> 
		</thead> 
		<tbody> 
HTML;
for($i=0; $i < $total_reg; $i++){
	foreach ($res[$i] as $key => $value){}
$id_emp = $res[$i]['id'];
$valor = $res[$i]['valor'];
$parcelas = $res[$i]['parcelas'];
$data_venc = $res[$i]['data_venc'];
$data = $res[$i]['data'];

$data_vencF = date('d', strtotime($data_venc));
$dataF = implode('/', array_reverse(explode('-', $data)));
$valorF = number_format($valor, 2, ',', '.');

$classe_deb = '';
$query2 = $pdo->query("SELECT * FROM receber where referencia = 'Cobrança' and id_ref = '$id_emp' and data_venc < curDate() and pago != 'Sim'");
$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
$total_reg2 = @count($res2);
if($total_reg2 > 0){
	$classe_deb = 'text-danger';
}

//verificar parcelas pagas
$query2 = $pdo->query("SELECT * from receber where referencia = 'Cobrança' and id_ref = '$id_emp' and pago = 'Sim'");
$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
$parcelas_pagas = @count($res2);

if($parcelas > 1){
	$parcelas_nome = $parcelas_pagas .' / '. $parcelas;
}else{
	$parcelas_nome = 'Recorrente ';
}


echo <<<HTML
			<tr>					
				<td class="{$classe_deb}">R$ {$valorF}</td>
				<td class="esc">{$parcelas_nome}</td>				
				<td class="esc">Dia {$data_vencF}</td>				
				<td class="esc">{$dataF}</td>
				<td>					
					<big><a href="#" onclick="mostrarParcelas('{$id_emp}')" title="Mostrar Parcelas"><i class="fa fa-money verde"></i></a></big>
				</td>  
			</tr> 
HTML;
}
echo <<<HTML
		</tbody> 
	</table>
</small>
HTML;
}else{
	echo 'Não possui nenhum emprestimo cadastrado!';
}

?>


<script type="text/javascript">

	function mostrarParcelas(id_emp){

	
	$('#id_emprestimo').val('');    	

	var mostrar = 'cobranca';
    
    $.ajax({
        url: 'paginas/' + pag + "/mostar_parcelas.php",
        method: 'POST',
        data: {id_emp, mostrar},
        dataType: "text",

        success: function (mensagem) {
                  
           $("#listar_parcelas").html(mensagem);
        },      

    });

    $('#id_cobranca').val(id_emp);
    $('#modalParcelas').modal('show');

}


</script>


