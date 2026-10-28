<?php
require_once __DIR__ . '/../_context_bootstrap.php';
// ============================================================================
// Arquivo: /sistema/painel/paginas/servicos_agenda/listar.php
// Ajuste pontual:
// - mantém a listagem original de serviços lançados em "receber"
// - apenas sinaliza visualmente quando o serviço é combo
// - usa o banco já resolvido pelo conexao.php central
// ============================================================================

@session_start();

if (!isset($pdo) || !($pdo instanceof PDO)) {
	require_once __DIR__ . '/../../../conexao.php';
}

$tabela = 'receber';
$data_hoje = date('Y-m-d');

if (!function_exists('bb_formatar_data_br')) {
	function bb_formatar_data_br($data, string $fallback = ''): string {
		$data = trim((string)$data);
		if ($data === '' || $data === '0000-00-00' || strtolower($data) === 'null') {
			return $fallback;
		}
		$partes = explode('-', $data);
		if (count($partes) !== 3) {
			return $fallback !== '' ? $fallback : $data;
		}
		return implode('/', array_reverse($partes));
	}
}

if (!function_exists('bb_srv_ag_coluna_existe')) {
	function bb_srv_ag_coluna_existe(PDO $pdo, $tabela, $coluna)
	{
		try {
			$stmt = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
				WHERE TABLE_SCHEMA = DATABASE()
				AND TABLE_NAME = :tabela
				AND COLUMN_NAME = :coluna");
			$stmt->execute([
				':tabela' => $tabela,
				':coluna' => $coluna
			]);
			return ((int)$stmt->fetchColumn() > 0);
		} catch (Throwable $e) {
			return false;
		}
	}
}

$usuario_logado = @$_SESSION['id'];
$dataInicial = $_POST['dataInicial'] ?? $data_hoje;
$dataFinal = $_POST['dataFinal'] ?? $data_hoje;
$status = '%' . ($_POST['status'] ?? '') . '%';

$total_pago = 0;
$total_a_pagar = 0;

$query = $pdo->prepare("SELECT * FROM {$tabela}
	WHERE data_lanc >= :dataInicial
	AND data_lanc <= :dataFinal
	AND pago LIKE :status
	AND tipo = 'Serviço'
	ORDER BY pago ASC, data_venc ASC");
$query->bindValue(':dataInicial', $dataInicial, PDO::PARAM_STR);
$query->bindValue(':dataFinal', $dataFinal, PDO::PARAM_STR);
$query->bindValue(':status', $status, PDO::PARAM_STR);
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);

if ($total_reg <= 0) {
	echo '<small>Não possui nenhum registro Cadastrado!</small>';
	exit();
}

echo <<<HTML
<small>
<table class="table table-hover" id="tabela">
<thead>
<tr>
<th>Serviço</th>
<th class="esc">Valor</th>
<th class="esc">Funcionário</th>
<th class="esc">Data Serviço</th>
<th class="esc">Vencimento</th>
<th class="esc">Cliente</th>
<th>Ações</th>
</tr>
</thead>
<tbody>
HTML;

$tem_combo_ativo = bb_srv_ag_coluna_existe($pdo, 'servicos', 'combo_ativo');

for ($i = 0; $i < $total_reg; $i++) {
	foreach ($res[$i] as $key => $value) {}
	$id = (int)$res[$i]['id'];
	$descricao = (string)$res[$i]['descricao'];
	$valor = (float)$res[$i]['valor'];
	$data_lanc = (string)$res[$i]['data_lanc'];
	$data_pgto = (string)$res[$i]['data_pgto'];
	$data_venc = (string)$res[$i]['data_venc'];
	$usuario_lanc = (int)$res[$i]['usuario_lanc'];
	$usuario_baixa = (int)$res[$i]['usuario_baixa'];
	$foto = isset($res[$i]['foto']) ? trim((string)$res[$i]['foto']) : '';
	$pessoa = (int)$res[$i]['pessoa'];
	$funcionario = (int)$res[$i]['funcionario'];
	$obs = (string)$res[$i]['obs'];
	$pgto = (string)$res[$i]['pgto'];
	$pago = (string)$res[$i]['pago'];
	$servico = (int)$res[$i]['servico'];
	$comanda = isset($res[$i]['comanda']) ? (int)$res[$i]['comanda'] : 0;
	$valor2 = isset($res[$i]['valor2']) ? (float)$res[$i]['valor2'] : 0;

	if ($comanda > 0) {
		$valor = $valor2;
	}

	if ($pago === 'Sim') {
		$total_pago += $valor;
	} else {
		$total_a_pagar += $valor;
	}

	$valorF = number_format($valor, 2, ',', '.');
	$data_lancF = bb_formatar_data_br($data_lanc);
	$data_pgtoF = bb_formatar_data_br($data_pgto, 'Pendente');
	$data_vencF = bb_formatar_data_br($data_venc);

	$query2 = $pdo->prepare("SELECT nome FROM usuarios WHERE id = :id LIMIT 1");
	$query2->bindValue(':id', $funcionario, PDO::PARAM_INT);
	$query2->execute();
	$nome_funcionario = (string)($query2->fetchColumn() ?: 'Sem Funcionário');

	$query2 = $pdo->prepare("SELECT nome, telefone FROM clientes WHERE id = :id LIMIT 1");
	$query2->bindValue(':id', $pessoa, PDO::PARAM_INT);
	$query2->execute();
	$dadosCliente = $query2->fetch(PDO::FETCH_ASSOC);
	$nome_cliente = $dadosCliente['nome'] ?? 'Sem Cliente';
	$telefone = $dadosCliente['telefone'] ?? '';

	$combo_badge = '';
	if ($tem_combo_ativo && $servico > 0) {
		$qCombo = $pdo->prepare("SELECT combo_ativo FROM servicos WHERE id = :id LIMIT 1");
		$qCombo->bindValue(':id', $servico, PDO::PARAM_INT);
		$qCombo->execute();
		$combo_ativo = (string)($qCombo->fetchColumn() ?: 'Não');

		if ($combo_ativo === 'Sim') {
			$combo_badge = ' <span class="badge badge-primary">Combo</span>';
		}
	}

	if ($pago != "Sim") {
		$classe_alerta = 'text-danger';
		$visivel = '';
		$japago = 'ocultar';
	} else {
		$classe_alerta = 'verde';
		$visivel = 'ocultar';
		$japago = '';
	}

	$descricao_js = htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8');
	$pgto_js = htmlspecialchars($pgto, ENT_QUOTES, 'UTF-8');
	$nome_cliente_js = htmlspecialchars($nome_cliente, ENT_QUOTES, 'UTF-8');
	$telefone_js = htmlspecialchars($telefone, ENT_QUOTES, 'UTF-8');
	$obs_js = htmlspecialchars($obs, ENT_QUOTES, 'UTF-8');

echo <<<HTML
<tr>
<td><i class="fa fa-square {$classe_alerta}"></i> {$descricao}{$combo_badge}</td>
<td class="esc">R$ {$valorF}</td>
<td class="esc">{$nome_funcionario}</td>
<td class="esc">{$data_lancF}</td>
<td class="esc">{$data_vencF}</td>
<td class="esc">{$nome_cliente}</td>
<td>
	<big><a href="javascript:void(0)" onclick="mostrar('{$descricao_js}', '{$valorF}', '{$data_lancF}', '{$data_vencF}', '{$data_pgtoF}', '{$usuario_lanc}', '{$usuario_baixa}', '{$foto}', '{$nome_cliente_js}', '', '{$telefone_js}', '{$obs_js}', '{$pgto_js}')" title="Mostrar Dados"><i class="fa fa-info-circle text-primary"></i></a></big>

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

	<big><a class="{$visivel}" href="javascript:void(0)" onclick="baixar('{$id}', '{$valor}', '{$descricao_js}', '{$pgto_js}')" title="Baixar Conta"><i class="fa fa-check-square verde"></i></a></big>

	<big><a class="{$japago}" href="javascript:void(0)" onclick="gerarComprovante('{$id}')" title="Gerar Comprovante"><i class="fa fa-file-pdf-o text-primary"></i></a></big>
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
?>

<script type="text/javascript">
$(document).ready(function () {
	$('#tabela').DataTable({
		"ordering": false,
		"stateSave": true
	});
	$('#tabela_filter label input').focus();
});

function limparCampos(){
	$('#id').val('');
	$('#valor_serv').val('');
	$('#data_pgto').val('<?=$data_hoje?>');
	$('#valor_serv_agd_restante').val('');
	$('#data_pgto_restante').val('');
	$('#pgto_restante').val('').change();
}

function mostrar(descricao, valor, data_lanc, data_venc, data_pgto, usuario_lanc, usuario_pgto, foto, pessoa, link, telefone, obs, pgto){
	$('#nome_dados').text(descricao);
	$('#valor_dados').text(valor);
	$('#data_lanc_dados').text(data_lanc);
	$('#data_venc_dados').text(data_venc);
	$('#data_pgto_dados').text(data_pgto);
	$('#usuario_lanc_dados').text(usuario_lanc);
	$('#usuario_baixa_dados').text(usuario_pgto);
	$('#pessoa_dados').text(pessoa);
	$('#telefone_dados').text(telefone);
	$('#pgto_dados').text(pgto);
	$('#obs_dados').text(obs);
	$('#modalDados').modal('show');
}

function baixar(id, valor, descricao, pgto){
	$('#id_baixar').val(id);
	$('#valor_baixar').val(valor);
	if (typeof mascara_valor === 'function') {
		mascara_valor('valor_baixar');
	}
	$('#titulo_baixar').text(descricao);
	$('#pgto_baixar').val(pgto).change();
	$('#modalBaixar').modal('show');
}

function gerarComprovante(id){
	let a = document.createElement('a');
	a.target = '_blank';
	a.href = 'rel/comprovante_class.php?id=' + id;
	a.click();
}
</script>
