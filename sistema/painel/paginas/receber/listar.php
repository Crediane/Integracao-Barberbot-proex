<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'receber';
$data_hoje = date('Y-m-d');

/*
	ATENÇÃO:
	- Ajuste aplicado para evitar o aviso deprecatado do PHP 8+ (pathinfo recebendo null)
	- Mantida a lógica de negócio existente (filtros, ordenação e regras de pagamento)
	- Melhoria de segurança: consultas com prepared statements (sem alterar o resultado)
*/

$dataInicial = isset($_POST['dataInicial']) ? trim($_POST['dataInicial']) : '';
$dataFinal   = isset($_POST['dataFinal']) ? trim($_POST['dataFinal']) : '';
$statusPost  = isset($_POST['status']) ? trim($_POST['status']) : '';
$status      = '%' . $statusPost . '%';

$total_pago = 0;
$total_a_pagar = 0;

// Consulta principal (mesma lógica, agora com prepared statement)
$sql = "SELECT * FROM $tabela 
		where data_venc >= :dataInicial 
		and data_venc <= :dataFinal 
		and pago LIKE :status 
		and valor > 0 
		ORDER BY pago asc, data_venc asc";
$stmt = $pdo->prepare($sql);
$stmt->execute([
	':dataInicial' => $dataInicial,
	':dataFinal'   => $dataFinal,
	':status'      => $status
]);
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);

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
	<th class="esc">Usuário PGTO</th>
	<th class="esc">Cliente</th>	
	<th class="esc">Arquivo</th>	
	<th>Ações</th>
	</tr> 
	</thead> 
	<tbody>	
HTML;

for($i=0; $i < $total_reg; $i++){
	foreach ($res[$i] as $key => $value){}
	$id = $res[$i]['id'];	
	$descricao = $res[$i]['descricao'];
	$tipo = $res[$i]['tipo'];
	$valor = $res[$i]['valor'];
	$data_lanc = $res[$i]['data_lanc'];
	$data_pgto = $res[$i]['data_pgto'];
	$data_venc = $res[$i]['data_venc'];
	$usuario_lanc = $res[$i]['usuario_lanc'];
	$usuario_baixa = $res[$i]['usuario_baixa'];

	// CORREÇÃO: evita null no pathinfo e padroniza o nome do arquivo
	$foto = isset($res[$i]['foto']) ? trim((string)$res[$i]['foto']) : '';
	$foto = basename($foto); // segurança (evita path traversal)
	if($foto == ''){
		$foto = 'sem-foto.jpg'; // já utilizado no sistema (preview)
	}

	$pessoa = $res[$i]['pessoa'];
	$pago = $res[$i]['pago'];
	$pgto = $res[$i]['pgto'];
	$servico = $res[$i]['servico'];
	$frequencia = $res[$i]['frequencia'];
	
	$valorF = number_format($valor, 2, ',', '.');
	$data_lancF = implode('/', array_reverse(@explode('-', $data_lanc)));
	$data_pgtoF = implode('/', array_reverse(@explode('-', $data_pgto)));
	$data_vencF = implode('/', array_reverse(@explode('-', $data_venc)));
	

		// Cliente
		$stmt2 = $pdo->prepare("SELECT * FROM clientes where id = :id");
		$stmt2->execute([':id' => $pessoa]);
		$res2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
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


		// Usuário PGTO
		$stmt2 = $pdo->prepare("SELECT * FROM usuarios where id = :id");
		$stmt2->execute([':id' => $usuario_baixa]);
		$res2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
		$total_reg2 = @count($res2);
		if($total_reg2 > 0){
			$nome_usuario_pgto = $res2[0]['nome'];
		}else{
			$nome_usuario_pgto = 'Nenhum!';
		}


		// Serviço
		$stmt2 = $pdo->prepare("SELECT * FROM servicos where id = :id");
		$stmt2->execute([':id' => $servico]);
		$res2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
		$total_reg2 = @count($res2);
		if($total_reg2 > 0){
			$nome_servico = $res2[0]['nome'];
		}else{
			$nome_servico = '!';
		}



		// Usuário Lançamento
		$stmt2 = $pdo->prepare("SELECT * FROM usuarios where id = :id");
		$stmt2->execute([':id' => $usuario_lanc]);
		$res2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
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


		// extensão do arquivo (CORREÇÃO: $foto nunca será null)
		$ext = strtolower(pathinfo($foto, PATHINFO_EXTENSION));
		if($ext == 'pdf'){
			$tumb_arquivo = 'pdf.png';
		}else if($ext == 'rar' || $ext == 'zip'){
			$tumb_arquivo = 'rar.png';
		}else{
			$tumb_arquivo = $foto;
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

echo <<<HTML
<tr class="{$classe_debito}">
<td><i class="fa fa-square {$classe_alerta}"></i> {$descricao}</td>
<td class="esc">R$ {$valorF}</td>
<td class="esc">{$data_vencF}</td>
<td class="esc">{$data_pgtoF}</td>
<td class="esc">{$nome_usuario_pgto}</td>
<td class="esc">{$nome_pessoa}</td>
<td><a href="img/contas/{$foto}" target="_blank"><img src="img/contas/{$tumb_arquivo}" width="27px" class="mr-2"></a></td>
<td>
		<big><a href="javascript:void(0)" onclick="editar('{$id}','{$descricao}', '{$pessoa}', '{$valor}', '{$data_venc}', '{$data_pgto}', '{$tumb_arquivo}', '{$pgto}', '{$servico}', '{$frequencia}')" title="Editar Dados"><i class="fa fa-edit text-primary"></i></a></big>

		<big><a href="javascript:void(0)" onclick="mostrar('{$descricao}', '{$valorF}', '{$data_lancF}', '{$data_vencF}',  '{$data_pgtoF}', '{$nome_usuario_lanc}', '{$nome_usuario_pgto}', '{$tumb_arquivo}', '{$nome_pessoa}', '{$foto}', '{$pgto}', '{$nome_servico}', '{$frequencia}')" title="Ver Dados"><i class="fa fa-info-circle text-secondary"></i></a></big>



		<li class="dropdown head-dpdn2" style="display: inline-block;">
		<a href="javascript:void(0)" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><big><i class="fa fa-trash-o text-danger"></i></big></a>

		<ul class="dropdown-menu" style="margin-left:-230px;">
		<li>
		<div class="notification_desc2">
		<p>Confirmar Exclusão? <a href="javascript:void(0)" onclick="excluir('{$id}')"><span class="text-danger">Sim</span></a></p>
		</div>
		</li>										
		</ul>
		</li>


	<big><a class="{$visivel}" href="javascript:void(0)" onclick="baixar('{$id}', '{$valor}', '{$descricao}', '{$pgto}')" title="Baixar Conta"><i class="fa fa-check-square verde"></i></a></big>



		<big><a class="{$ocultar_baixar}" href="javascript:void(0)" onclick="cobrar('{$id}', '{$valor}', '{$data_venc}', '{$telefone_pessoa}', '{$descricao}')" title="Gerar Cobrança"><i class="fa fa-whatsapp verde"></i></a></big>
		
	
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
<div align="right">Total Recebido: <span class="verde">R$ {$total_pagoF}</span> </div>
<div align="right">Total à Receber: <span class="text-danger">R$ {$total_a_pagarF}</span> </div>

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


<script type="text/javascript">
	function editar(id, descricao, pessoa, valor, data_venc, data_pgto, foto, pgto, servico, frequencia){
		$('#id').val(id);
		$('#descricao').val(descricao);
		$('#pessoa').val(pessoa).change();
		$('#valor').val(valor);
		$('#data_venc').val(data_venc);
		$('#data_pgto').val(data_pgto);
		$('#pgto').val(pgto).change();
		$('#servico').val(servico).change();
		$('#frequencia').val(frequencia).change();
								
		$('#titulo_inserir').text('Editar Registro');
		$('#modalForm').modal('show');
		$('#foto').val('');
		$('#target').attr('src','img/contas/' + foto);
	}

	function limparCampos(){
		$('#id').val('');
		$('#descricao').val('');
		$('#valor').val('');
		$('#data_pgto').val('');
		$('#data_venc').val('<?=$data_hoje?>');		
		$('#foto').val('');
		$('#servico').val('0').change();
		$('#frequencia').val('0').change();

		$('#target').attr('src','img/contas/sem-foto.jpg');
	}
</script>

<script type="text/javascript">
	function mostrar(descricao, valor, data_lanc, data_venc, data_pgto, usuario_lanc, usuario_pgto, foto, pessoa, link, pgto, servico, frequencia){

		$('#nome_dados').text(descricao);
		$('#valor_dados').text(valor);
		$('#data_lanc_dados').text(data_lanc);
		$('#data_venc_dados').text(data_venc);
		$('#data_pgto_dados').text(data_pgto);
		$('#usuario_lanc_dados').text(usuario_lanc);
		$('#usuario_baixa_dados').text(usuario_pgto);
		$('#pessoa_dados').text(pessoa);
		$('#pgto_dados').text(pgto);
		$('#servico_dados').text(servico);
		$('#frequencia_dados').text(frequencia);
		
		$('#link_mostrar').attr('href','img/contas/' + link);
		$('#target_mostrar').attr('src','img/contas/' + foto);

		$('#modalDados').modal('show');
	}
</script>




<script type="text/javascript">
	function saida(id, nome, estoque){

		$('#nome_saida').text(nome);
		$('#estoque_saida').val(estoque);
		$('#id_saida').val(id);		

		$('#modalSaida').modal('show');
	}
</script>


<script type="text/javascript">
	function entrada(id, nome, estoque){

		$('#nome_entrada').text(nome);
		$('#estoque_entrada').val(estoque);
		$('#id_entrada').val(id);		

		$('#modalEntrada').modal('show');
	}
</script>


<script type="text/javascript">
	function baixar(id, valor, descricao, pgto){

		$('#id_baixar').val(id);
		$('#valor_baixar').val(valor);			
		$('#titulo_baixar').text(descricao);
		$('#pgto_baixar').val(pgto).change();

		$('#modalBaixar').modal('show');
	}
</script>



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
	        //alert(result) 
	        	alert('Cobrança Efetuada!');           	
	           	
	        }
	    });
}
</script>
