<?php
require_once __DIR__ . '/../_context_bootstrap.php';
/*
	Correção:
	- Evita Warning de sessão sem a chave 'id'
	- Compatível com dono do sistema e dono do estabelecimento
	- Mantém a lógica original (minhas comissões do usuário logado)
	- Mantém padrão do sistema (DataTable, html, etc.)
*/

// Conexão (prioriza banco individual do painel)
$conexao_painel = __DIR__ . "/../../conexao.php";
$conexao_sistema = __DIR__ . "/../../../conexao.php";

if (file_exists($conexao_painel)) {
	require_once($conexao_painel);
} else {
	require_once($conexao_sistema);
}

$tabela = 'pagar';
$data_hoje = date('Y-m-d');

// Pega id do usuário logado (compatível com diferentes perfis)
$id_usuario = null;
if (isset($_SESSION['id']) && $_SESSION['id'] != '') {
	$id_usuario = $_SESSION['id'];
} else if (isset($_SESSION['id_usuario']) && $_SESSION['id_usuario'] != '') {
	$id_usuario = $_SESSION['id_usuario'];
}

// Se não tiver sessão válida, não quebra a tela com warning
if ($id_usuario === null) {
	echo '<small>Sessão expirada. Recarregue a página e faça login novamente.</small>';
	exit();
}

$id_usuario = (int)$id_usuario;

// POST com fallback seguro (mantendo a lógica)
$dataInicial = isset($_POST['dataInicial']) && $_POST['dataInicial'] != '' ? $_POST['dataInicial'] : $data_hoje;
$dataFinal   = isset($_POST['dataFinal']) && $_POST['dataFinal'] != '' ? $_POST['dataFinal'] : $data_hoje;

$status_post = isset($_POST['status']) ? $_POST['status'] : '';
$status_like = '%' . $status_post . '%';

$funcionario = $id_usuario;

// Buscar nome do funcionário (mantém igual)
$nome_func2 = 'Sem Referência!';
try {
	$stmt = $pdo->prepare("SELECT nome FROM usuarios WHERE id = :id LIMIT 1");
	$stmt->execute([':id' => $funcionario]);
	$nome = $stmt->fetchColumn();
	if ($nome) {
		$nome_func2 = $nome;
	}
} catch (Exception $e) {
	$nome_func2 = 'Sem Referência!';
}

$total_pago = 0;
$total_a_pagar = 0;
$total_pendente = 0;

// Query principal (mesma lógica, com prepared statement)
try {
	$stmt = $pdo->prepare("
		SELECT *
		FROM $tabela
		WHERE data_lanc >= :dataInicial
		  AND data_lanc <= :dataFinal
		  AND pago LIKE :status
		  AND funcionario = :funcionario
		  AND tipo = 'Comissão'
		ORDER BY pago ASC, data_venc ASC
	");
	$stmt->execute([
		':dataInicial' => $dataInicial,
		':dataFinal' => $dataFinal,
		':status' => $status_like,
		':funcionario' => $funcionario
	]);
	$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
	$res = [];
}

$total_reg = @count($res);

if ($total_reg > 0) {

echo <<<HTML
	<small>
	<table class="table table-hover" id="tabela">
	<thead> 
	<tr> 
	<th>Serviço</th>	
	<th class="esc">Valor</th> 	
	<th class="esc">Data Serviço</th>		
	<th class="esc">Vencimento</th>	
	<th class="esc">Paga Em</th>
	<th class="esc">Cliente</th>
	<th>Ações</th>
	</tr> 
	</thead> 
	<tbody>	
HTML;

	// caches simples (não muda lógica, só evita repetir select)
	$cacheUsuarios = [];
	$cacheClientes = [];
	$cacheServicos = [];

	for ($i = 0; $i < $total_reg; $i++) {

		$id = isset($res[$i]['id']) ? $res[$i]['id'] : 0;
		$descricao = isset($res[$i]['descricao']) ? $res[$i]['descricao'] : '';
		$tipo = isset($res[$i]['tipo']) ? $res[$i]['tipo'] : '';
		$valor = isset($res[$i]['valor']) ? $res[$i]['valor'] : 0;
		$data_lanc = isset($res[$i]['data_lanc']) ? $res[$i]['data_lanc'] : $data_hoje;
		$data_pgto = isset($res[$i]['data_pgto']) ? $res[$i]['data_pgto'] : '';
		$data_venc = isset($res[$i]['data_venc']) ? $res[$i]['data_venc'] : $data_hoje;
		$usuario_lanc = isset($res[$i]['usuario_lanc']) ? $res[$i]['usuario_lanc'] : 0;
		$usuario_baixa = isset($res[$i]['usuario_baixa']) ? $res[$i]['usuario_baixa'] : 0;
		$foto = isset($res[$i]['foto']) ? $res[$i]['foto'] : '';
		$pessoa = isset($res[$i]['pessoa']) ? $res[$i]['pessoa'] : 0;
		$funcionario_linha = isset($res[$i]['funcionario']) ? $res[$i]['funcionario'] : 0;
		$cliente = isset($res[$i]['cliente']) ? $res[$i]['cliente'] : 0;
		$pago = isset($res[$i]['pago']) ? $res[$i]['pago'] : 'Não';
		$servico = isset($res[$i]['servico']) ? $res[$i]['servico'] : 0;

		$valorF = number_format((float)$valor, 2, ',', '.');
		$data_lancF = implode('/', array_reverse(explode('-', $data_lanc)));
		$data_vencF = implode('/', array_reverse(explode('-', $data_venc)));

		// Data pgto (mantém lógica)
		$data_pgtoF = '';
		if ($data_pgto != '' && $data_pgto != '0000-00-00') {
			$data_pgtoF = implode('/', array_reverse(explode('-', $data_pgto)));
		}

		// pessoa (cliente principal)
		$nome_pessoa = 'Nenhum!';
		$telefone_pessoa = 'Nenhum';

		if (!isset($cacheClientes[$pessoa])) {
			try {
				$stmtC = $pdo->prepare("SELECT nome, telefone FROM clientes WHERE id = :id LIMIT 1");
				$stmtC->execute([':id' => (int)$pessoa]);
				$cacheClientes[$pessoa] = $stmtC->fetch(PDO::FETCH_ASSOC);
			} catch (Exception $e) {
				$cacheClientes[$pessoa] = false;
			}
		}

		if ($cacheClientes[$pessoa]) {
			$nome_pessoa = $cacheClientes[$pessoa]['nome'];
			$telefone_pessoa = $cacheClientes[$pessoa]['telefone'];
		}

		// usuário baixa
		$nome_usuario_pgto = 'Nenhum!';
		if (!isset($cacheUsuarios[$usuario_baixa])) {
			try {
				$stmtU = $pdo->prepare("SELECT nome FROM usuarios WHERE id = :id LIMIT 1");
				$stmtU->execute([':id' => (int)$usuario_baixa]);
				$cacheUsuarios[$usuario_baixa] = $stmtU->fetchColumn();
			} catch (Exception $e) {
				$cacheUsuarios[$usuario_baixa] = false;
			}
		}
		if ($cacheUsuarios[$usuario_baixa]) {
			$nome_usuario_pgto = $cacheUsuarios[$usuario_baixa];
		}

		// cliente da linha
		$nome_cliente = 'Nenhum!';
		if (!isset($cacheClientes[$cliente])) {
			try {
				$stmtCC = $pdo->prepare("SELECT nome FROM clientes WHERE id = :id LIMIT 1");
				$stmtCC->execute([':id' => (int)$cliente]);
				$cacheClientes[$cliente] = ['nome' => $stmtCC->fetchColumn(), 'telefone' => ''];
			} catch (Exception $e) {
				$cacheClientes[$cliente] = false;
			}
		}
		if ($cacheClientes[$cliente] && isset($cacheClientes[$cliente]['nome']) && $cacheClientes[$cliente]['nome']) {
			$nome_cliente = $cacheClientes[$cliente]['nome'];
		}

		// usuário lançamento
		$nome_usuario_lanc = 'Sem Referência!';
		if (!isset($cacheUsuarios[$usuario_lanc])) {
			try {
				$stmtUL = $pdo->prepare("SELECT nome FROM usuarios WHERE id = :id LIMIT 1");
				$stmtUL->execute([':id' => (int)$usuario_lanc]);
				$cacheUsuarios[$usuario_lanc] = $stmtUL->fetchColumn();
			} catch (Exception $e) {
				$cacheUsuarios[$usuario_lanc] = false;
			}
		}
		if ($cacheUsuarios[$usuario_lanc]) {
			$nome_usuario_lanc = $cacheUsuarios[$usuario_lanc];
		}

		// funcionário da linha
		$nome_func = 'Sem Referência!';
		if (!isset($cacheUsuarios[$funcionario_linha])) {
			try {
				$stmtUF = $pdo->prepare("SELECT nome FROM usuarios WHERE id = :id LIMIT 1");
				$stmtUF->execute([':id' => (int)$funcionario_linha]);
				$cacheUsuarios[$funcionario_linha] = $stmtUF->fetchColumn();
			} catch (Exception $e) {
				$cacheUsuarios[$funcionario_linha] = false;
			}
		}
		if ($cacheUsuarios[$funcionario_linha]) {
			$nome_func = $cacheUsuarios[$funcionario_linha];
		}

		// serviço
		$nome_serv = 'Sem Referência!';
		if (!isset($cacheServicos[$servico])) {
			try {
				$stmtS = $pdo->prepare("SELECT nome FROM servicos WHERE id = :id LIMIT 1");
				$stmtS->execute([':id' => (int)$servico]);
				$cacheServicos[$servico] = $stmtS->fetchColumn();
			} catch (Exception $e) {
				$cacheServicos[$servico] = false;
			}
		}
		if ($cacheServicos[$servico]) {
			$nome_serv = $cacheServicos[$servico];
		}

		// pago / pendente (mesma lógica)
		if ($pago != 'Sim') {
			$classe_alerta = 'text-danger';
			$data_pgtoF = 'Pendente';
			$visivel = '';
			$total_a_pagar += (float)$valor;
			$total_pendente += 1;
		} else {
			$classe_alerta = 'verde';
			$visivel = 'ocultar';
			$total_pago += (float)$valor;
		}

		// extensão do arquivo
		$ext = pathinfo($foto, PATHINFO_EXTENSION);
		if ($ext == 'pdf') {
			$tumb_arquivo = 'pdf.png';
		} else if ($ext == 'rar' || $ext == 'zip') {
			$tumb_arquivo = 'rar.png';
		} else {
			$tumb_arquivo = $foto;
		}

		// vencido (mesma regra)
		if ($data_venc < $data_hoje && $pago != 'Sim') {
			$classe_debito = 'vermelho-escuro';
		} else {
			$classe_debito = '';
		}

echo <<<HTML
<tr class="{$classe_debito}">
<td><i class="fa fa-square {$classe_alerta}"></i> {$nome_serv}</td>
<td class="esc">R$ {$valorF}</td>
<td class="esc">{$data_lancF}</td>
<td class="esc">{$data_vencF}</td>
<td class="esc">{$data_pgtoF}</td>
<td class="esc">{$nome_cliente}</td>
<td>

	<big><a href="#" onclick="mostrar('{$descricao}', '{$valorF}', '{$data_lancF}', '{$data_vencF}',  '{$data_pgtoF}', '{$nome_usuario_lanc}', '{$nome_usuario_pgto}', '{$tumb_arquivo}', '{$nome_pessoa}', '{$foto}', '{$telefone_pessoa}', '{$nome_func}')" title="Ver Dados"><i class="fa fa-info-circle text-secondary"></i></a></big>

	<li class="dropdown head-dpdn2" style="display: inline-block;">
	<a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><big><i class="fa fa-trash-o text-danger"></i></big></a>

	<ul class="dropdown-menu" style="margin-left:-230px;">
	<li>
	<div class="notification_desc2">
	<p>Confirmar Exclusão? <a href="#" onclick="excluir('{$id}')"><span class="text-danger">Sim</span></a></p>
	</div>
	</li>										
	</ul>
	</li>

</td>
</tr>
HTML;

	} // for

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

} else {
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
	});
</script>

<script type="text/javascript">
	function mostrar(descricao, valor, data_lanc, data_venc, data_pgto, usuario_lanc, usuario_pgto, foto, pessoa, link, telefone, func){

		$('#nome_dados').text(descricao);
		$('#valor_dados').text(valor);
		$('#data_lanc_dados').text(data_lanc);
		$('#data_venc_dados').text(data_venc);
		$('#data_pgto_dados').text(data_pgto);
		$('#usuario_lanc_dados').text(usuario_lanc);
		$('#usuario_baixa_dados').text(usuario_pgto);
		$('#pessoa_dados').text(pessoa);
		$('#telefone_dados').text(telefone);
		$('#nome_func_dados').text(func);

		$('#link_mostrar').attr('href','img/contas/' + link);
		$('#target_mostrar').attr('src','img/contas/' + foto);

		$('#modalDados').modal('show');
	}
</script>
