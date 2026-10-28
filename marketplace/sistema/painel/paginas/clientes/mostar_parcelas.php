<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';

$pagina = 'receber';

// Sanitização (sem mudar regra)
$id = isset($_POST['id_emp']) ? (int)$_POST['id_emp'] : 0;
$mostrar = isset($_POST['mostrar']) ? $_POST['mostrar'] : '';

if ($mostrar == 'cobranca') {
	$sql_mostrar = 'Cobrança';
	$sql_consulta = 'cobrancas';
} else {
	$sql_mostrar = 'Empréstimo';
	$sql_consulta = 'emprestimos';
}

$data_atual = date('Y-m-d');

// ✅ evita Deprecated do explode() com NULL / vazio
function bb_data_br($data_mysql)
{
	$data_mysql = (string)$data_mysql;
	if ($data_mysql === '' || $data_mysql === '0000-00-00') {
		return '';
	}
	return implode('/', array_reverse(explode('-', $data_mysql)));
}

// Se ID inválido (mantém padrão simples)
if ($id <= 0) {
	echo '<small>Nenhum registro cadastrado!</small>';
	exit;
}

// ✅ Prepared: consulta multa/juros/cliente (mesma lógica)
$stmt = $pdo->prepare("SELECT * FROM {$sql_consulta} WHERE id = :id LIMIT 1");
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);

$multa = isset($res[0]['multa']) ? (float)$res[0]['multa'] : 0;
$juros = isset($res[0]['juros']) ? (float)$res[0]['juros'] : 0;
$cliente = isset($res[0]['cliente']) ? (int)$res[0]['cliente'] : 0;

// ✅ Prepared: telefone do cliente (mesma lógica)
$telefone = '';
if ($cliente > 0) {
	$stmtCli = $pdo->prepare("SELECT telefone FROM clientes WHERE id = :id LIMIT 1");
	$stmtCli->bindValue(':id', $cliente, PDO::PARAM_INT);
	$stmtCli->execute();
	$resCli = $stmtCli->fetchAll(PDO::FETCH_ASSOC);
	$telefone = isset($resCli[0]['telefone']) ? $resCli[0]['telefone'] : '';
}

echo <<<HTML
<small>
HTML;

// ✅ Prepared: lista parcelas (mesma lógica)
$stmt2 = $pdo->prepare("SELECT * FROM {$pagina} WHERE referencia = :ref AND id_ref = :id_ref ORDER BY id ASC");
$stmt2->bindValue(':ref', $sql_mostrar);
$stmt2->bindValue(':id_ref', $id, PDO::PARAM_INT);
$stmt2->execute();
$res = $stmt2->fetchAll(PDO::FETCH_ASSOC);

$total_reg = @count($res);
if ($total_reg > 0) {

	echo <<<HTML
	<table class="table table-hover" id="">
		<thead> 
			<tr> 				
				<th>Parcela</th>
				<th>Valor</th>				
				<th>Vencimento</th>				
				<th>Data PGTO</th>				
				<th>Baixar</th>
			</tr> 
		</thead> 
		<tbody> 
HTML;

	for ($i = 0; $i < $total_reg; $i++) {
		foreach ($res[$i] as $key => $value) {
		}

		$id_par = (int)$res[$i]['id'];
		$valor = isset($res[$i]['valor']) ? (float)$res[$i]['valor'] : 0;
		$parcela = $res[$i]['parcela'] ?? '';
		$data_venc = $res[$i]['data_venc'] ?? '';
		$data_pgto = $res[$i]['data_pgto'] ?? '';
		$pago = $res[$i]['pago'] ?? '';
		$ref_pix = $res[$i]['ref_pix'] ?? ''; // (mantido, mesmo que não use)

		$data_vencF = bb_data_br($data_venc);
		$data_pgtoF = bb_data_br($data_pgto);
		$valorF = number_format($valor, 2, ',', '.');

		$valor_final = $valor;

		// mesma regra: pendente quando não tem data_pgto
		if ($data_pgtoF == '00/00/0000' || $data_pgtoF == '') {
			$data_pgtoF = 'Pendente';
			$classe_pago = 'text-danger';
			$ocultar_baixar = '';
			$ocultar_pendentes = 'ocultar';
		} else {
			$classe_pago = 'text-success';
			$ocultar_baixar = 'ocultar';
			$ocultar_pendentes = '';
		}

		$valor_multa = 0;
		$valor_juros = 0;
		$dias_vencido = 0;
		$classe_venc = '';

		// mesma regra: vencido e não pago -> calcula multa e juros por dia
		if (@strtotime($data_venc) < @strtotime($data_atual) && $pago != 'Sim') {

			$classe_venc = 'text-danger';
			$valor_multa = (float)$multa;

			$data_inicio = new DateTime($data_venc);
			$data_fim = new DateTime($data_atual);
			$dateInterval = $data_inicio->diff($data_fim);
			$dias_vencido = (int)$dateInterval->days;

			$valor_juros = $dias_vencido * ((float)$juros * (float)$valor / 100);

			$valor_final = (float)$valor_juros + (float)$valor_multa + (float)$valor;
		}

		$valor_finalF = number_format((float)$valor_final, 2, ',', '.');

		echo <<<HTML
			<tr>					
				<td class="">{$parcela}</td>
				<td class="{$classe_pago}">R$ {$valorF}</td>				
				<td class="{$classe_venc}">{$data_vencF}</td>
				<td class="">{$data_pgtoF}</td>
				
				<td>					
					<big>
						<a class="{$ocultar_baixar}" href="#" onclick="baixarParcela('{$id_par}', '{$valor}', '{$valor_multa}', '{$valor_juros}')" title="Dar Baixa">
							<i class="fa fa-check-square verde"></i>
						</a>
					</big>

					<big>
						<a class="{$ocultar_baixar}" href="#" onclick="cobrar('{$id}', '{$parcela}', '{$valor_final}', '{$data_venc}', '{$telefone}', '{$valor_multa}', '{$valor_juros}', '{$id_par}')" title="Gerar Cobrança">
							<i class="fa fa-whatsapp verde"></i>
						</a>
					</big>

					<form method="POST" action="rel/recibo_class.php" target="_blank" style="display:inline-block">
						<input type="hidden" name="id" value="{$id_par}">
						<input type="hidden" name="enviar" value="Sim">
						<big>
							<button class="{$ocultar_pendentes}" title="Enviar Recibo" style="background:transparent; border:none; margin:0; padding:0">
								<i class="fa fa-file-pdf-o" style="color:red"></i>
							</button>
						</big>
					</form>
				</td>  
			</tr> 
HTML;
	}

	echo <<<HTML
		</tbody> 
	</table>
</small>
HTML;

} else {
	echo '<small>Nenhum registro cadastrado!</small>';
}
?>

<script type="text/javascript">
	function baixarParcela(id_par, valor, multa, juros){

		$('#id_baixar').val(id_par);
		$('#valor_baixar').val(valor);
		mascara_valor('valor_baixar');
		$('#multa_baixar').val(multa);
		$('#juros_baixar').val(juros);

		calcular();
		$('#modalBaixar').modal('show');
	}

	function cobrar(id_emp, parcela, valor, data, telefone, multa, juros, id_par){

		var instancia = "<?= isset($instancia) ? $instancia : '' ?>";
		var token = "<?= isset($token) ? $token : '' ?>";

		var id_empr = $('#id_emprestimo').val();
		var id_cob = $('#id_cobranca').val();

		if(instancia.trim() == "" || token.trim() == ""){
			alert('Insira um Token e Instancia Whatsapp nas configurações');
			return;
		}

		// ✅ compatível com marketplace / banco individual:
		// se existir bb_url (fix global), preserva loja=...; senão mantém padrão
		var urlAjax = 'paginas/clientes/gerar_cobranca.php';
		if (typeof bb_url === 'function') {
			urlAjax = bb_url(urlAjax);
		}

		$.ajax({
			url: urlAjax,
			method: 'POST',
			data: {parcela, valor, data, telefone, multa, juros, id_par},
			dataType: "html",
			success:function(result){

				alert('Cobrança Efetuada!');

				if(id_empr != ""){
					mostrarParcelasEmp(id_empr);
				}

				if(id_cob != ""){
					mostrarParcelas(id_cob);
				}
			}
		});
	}
</script>
