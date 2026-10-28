<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'receber';
$data_hoje = date('Y-m-d');
$usuario_logado = $_SESSION['id'] ?? 0;

// Tratamento de entradas para evitar erros de índice indefinido
$dataInicial = $_POST['dataInicial'] ?? date('Y-m-d');
$dataFinal = $_POST['dataFinal'] ?? date('Y-m-d');
$status = '%' . ($_POST['status'] ?? '') . '%';

$total_pago = 0;
$total_a_pagar = 0;

// Uso de Prepared Statements para segurança contra SQL Injection
$query = $pdo->prepare("SELECT * FROM $tabela WHERE data_lanc >= :ini AND data_lanc <= :fim AND pago LIKE :status AND tipo = 'Serviço' AND funcionario = :func ORDER BY pago ASC, data_venc ASC");
$query->bindValue(':ini', $dataInicial);
$query->bindValue(':fim', $dataFinal);
$query->bindValue(':status', $status);
$query->bindValue(':func', $usuario_logado);
$query->execute();

$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = count($res);

if($total_reg > 0){

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

foreach ($res as $row) {
	$id = $row['id'];	
	$descricao = $row['descricao'];
	$valor = $row['valor'];
	$data_lanc = $row['data_lanc'];
	$data_pgto = $row['data_pgto'];
	$data_venc = $row['data_venc'];
	$usuario_lanc = $row['usuario_lanc'];
	$usuario_baixa = $row['usuario_baixa'];
	$foto = $row['foto'] ?? 'sem-foto.jpg';
	$pessoa = $row['pessoa'];
	$funcionario = $row['funcionario'];
	$obs = $row['obs'];
	$pago = $row['pago'];
	$comanda = $row['comanda'];
	$valor2 = $row['valor2'];

	if($comanda > 0){
		$valor = $valor2;
	}
	
	$valorF = number_format($valor, 2, ',', '.');
	
    // Tratamento seguro de strings para evitar erro de 'Passing null to parameter #2'
	$data_lancF = implode('/', array_reverse(explode('-', (string)$data_lanc)));
	$data_pgtoF = ($data_pgto && $data_pgto != '0000-00-00') ? implode('/', array_reverse(explode('-', (string)$data_pgto))) : 'Pendente';
	$data_vencF = implode('/', array_reverse(explode('-', (string)$data_venc)));

    // Buscas otimizadas
    $q_cli = $pdo->query("SELECT nome, telefone FROM clientes WHERE id = '$pessoa'");
    $res_cli = $q_cli->fetch(PDO::FETCH_ASSOC);
    $nome_pessoa = $res_cli['nome'] ?? 'Nenhum!';
    $telefone_pessoa = $res_cli['telefone'] ?? 'Nenhum';

    $q_func = $pdo->query("SELECT nome FROM usuarios WHERE id = '$funcionario'");
    $res_func = $q_func->fetch(PDO::FETCH_ASSOC);
    $nome_func = $res_func['nome'] ?? 'Sem Referência!';

    $q_lanc = $pdo->query("SELECT nome FROM usuarios WHERE id = '$usuario_lanc'");
    $res_lanc = $q_lanc->fetch(PDO::FETCH_ASSOC);
    $nome_usuario_lanc = $res_lanc['nome'] ?? 'Sem Referência!';

    $q_pgto = $pdo->query("SELECT nome FROM usuarios WHERE id = '$usuario_baixa'");
    $res_pgto = $q_pgto->fetch(PDO::FETCH_ASSOC);
    $nome_usuario_pgto = $res_pgto['nome'] ?? 'Nenhum!';

	if($pago != 'Sim'){
		$classe_alerta = 'text-danger';
		$visivel = '';
		$total_a_pagar += $valor;
		$japago = 'ocultar';
	}else{
		$classe_alerta = 'verde';
		$visivel = 'ocultar';
		$total_pago += $valor;
		$japago = '';
	}

	$ext = strtolower(pathinfo((string)$foto, PATHINFO_EXTENSION));
    $tumb_arquivo = (in_array($ext, ['pdf', 'rar', 'zip'])) ? $ext . '.png' : $foto;
		
    $classe_debito = ($data_venc < $data_hoje && $pago != 'Sim') ? 'vermelho-escuro' : '';

echo <<<HTML
<tr class="{$classe_debito}">
<td><i class="fa fa-square {$classe_alerta}"></i> {$descricao}</td>
<td class="esc">R$ {$valorF}</td>
<td class="esc">{$nome_func}</td>
<td class="esc">{$data_lancF}</td>
<td class="esc">{$data_vencF}</td>
<td class="esc">{$nome_pessoa}</td>
<td>
    <big><a href="javascript:void(0)" onclick="mostrar('{$descricao}', '{$valorF}', '{$data_lancF}', '{$data_vencF}', '{$data_pgtoF}', '{$nome_usuario_lanc}', '{$nome_usuario_pgto}', '{$tumb_arquivo}', '{$nome_pessoa}', '{$foto}', '{$telefone_pessoa}', '{$obs}')" title="Ver Dados"><i class="fa fa-info-circle text-secondary"></i></a></big>

    <li class="dropdown head-dpdn2" style="display: inline-block;">
        <a href="javascript:void(0)" class="dropdown-toggle" data-toggle="dropdown"><big><i class="fa fa-trash-o text-danger"></i></big></a>
        <ul class="dropdown-menu" style="margin-left:-230px;">
            <li><div class="notification_desc2"><p>Confirmar Exclusão? <a href="javascript:void(0)" onclick="excluir('{$id}')"><span class="text-danger">Sim</span></a></p></div></li>
        </ul>
    </li>

    <li class="dropdown head-dpdn2" style="display: inline-block;">
        <a title="Baixar Conta" href="javascript:void(0)" class="dropdown-toggle {$visivel}" data-toggle="dropdown"><big><i class="fa fa-check-square verde"></i></big></a>
        <ul class="dropdown-menu" style="margin-left:-230px;">
            <li><div class="notification_desc2"><p>Confirmar Baixa? <a href="javascript:void(0)" onclick="baixar('{$id}')"><span class="verde">Sim</span></a></p></div></li>
        </ul>
    </li>

    <big><a class="{$japago}" href="javascript:void(0)" onclick="gerarComprovante('{$id}')" title="Gerar Comprovante"><i class="fa fa-file-pdf-o text-primary"></i></a></big>
</td>
</tr>
HTML;

}

$total_pagoF = number_format($total_pago, 2, ',', '.');
$total_a_pagarF = number_format($total_a_pagar, 2, ',', '.');

echo <<<HTML
</tbody>
</table>
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
    });

	function gerarComprovante(id){
		window.open('rel/comprovante.php?id='+ id, '_blank');
	}

    function mostrar(descricao, valor, data_lanc, data_venc, data_pgto, usuario_lanc, usuario_pgto, foto, pessoa, link, telefone, obs){
		$('#nome_dados').text(descricao);
		$('#valor_dados').text(valor);
		$('#data_lanc_dados').text(data_lanc);
		$('#data_venc_dados').text(data_venc);
		$('#data_pgto_dados').text(data_pgto);
		$('#usuario_lanc_dados').text(usuario_lanc);
		$('#usuario_baixa_dados').text(usuario_pgto);
		$('#pessoa_dados').text(pessoa);
		$('#telefone_dados').text(telefone);
		$('#obs_dados').text(obs);
		$('#modalDados').modal('show');
	}
</script>