<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once(__DIR__ . '/../../verificar.php');
require_once(__DIR__ . '/../../../conexao.php');

$tabela = 'manutencoes_protese';
$data_atual = date('Y-m-d');

/*
	Padrão real do painel
	- master: /sistema/painel/index.php?pag=agendamentos
	- loja:   /loja/<slug>/sistema/painel/index.php?pag=agendamentos
*/
$link_agendamento = 'index.php?pag=agendamentos';

$busca = isset($_POST['busca']) ? trim($_POST['busca']) : '';
$busca = '%' . $busca . '%';

$pagina = isset($_POST['pagina']) ? (int)$_POST['pagina'] : 0;
if ($pagina < 0) {
	$pagina = 0;
}

$limite = $pagina * $itens_pag;

/*
	Fornecedor sem chute:
	- só usa a coluna se ela realmente existir em manutencoes_protese
	- só tenta resolver nome na tabela fornecedores se ela existir
*/
$tem_coluna_fornecedor = false;
$tem_tabela_fornecedores = false;

try {
	$query_coluna_fornecedor = $pdo->query("SHOW COLUMNS FROM $tabela LIKE 'fornecedor'");
	$tem_coluna_fornecedor = ($query_coluna_fornecedor && $query_coluna_fornecedor->rowCount() > 0);
} catch (Exception $e) {
	$tem_coluna_fornecedor = false;
}

try {
	$query_tabela_fornecedores = $pdo->query("SHOW TABLES LIKE 'fornecedores'");
	$tem_tabela_fornecedores = ($query_tabela_fornecedores && $query_tabela_fornecedores->rowCount() > 0);
} catch (Exception $e) {
	$tem_tabela_fornecedores = false;
}

$where_fornecedor = '';
if ($tem_coluna_fornecedor) {
	$where_fornecedor = " OR m.fornecedor LIKE :busca ";
}

$query = $pdo->prepare("SELECT m.*, c.nome as nome_cliente, p.modelo as nome_protese 
	FROM $tabela m 
	INNER JOIN clientes c ON m.cliente = c.id
	INNER JOIN proteses p ON m.id_protese = p.id
	WHERE c.nome LIKE :busca 
		OR p.modelo LIKE :busca 
		OR m.tipo LIKE :busca 
		OR m.produtos_utilizados LIKE :busca 
		OR m.tipo_pele LIKE :busca 
		OR m.clima LIKE :busca 
		OR m.tipo_adesivo LIKE :busca
		$where_fornecedor
	ORDER BY m.id DESC LIMIT $limite, $itens_pag");
$query->bindValue(":busca", $busca);
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = count($res);

$query2 = $pdo->prepare("SELECT COUNT(*) as total 
	FROM $tabela m 
	INNER JOIN clientes c ON m.cliente = c.id
	INNER JOIN proteses p ON m.id_protese = p.id
	WHERE c.nome LIKE :busca 
		OR p.modelo LIKE :busca 
		OR m.tipo LIKE :busca 
		OR m.produtos_utilizados LIKE :busca 
		OR m.tipo_pele LIKE :busca 
		OR m.clima LIKE :busca 
		OR m.tipo_adesivo LIKE :busca
		$where_fornecedor");
$query2->bindValue(":busca", $busca);
$query2->execute();
$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
$total_reg2 = isset($res2[0]['total']) ? (int)$res2[0]['total'] : 0;

$num_paginas = ceil($total_reg2 / $itens_pag);
$ultimo_reg = $num_paginas - 1;
if ($ultimo_reg < 0) {
	$ultimo_reg = 0;
}

if ($total_reg > 0) {

$cabecalho_fornecedor = '';
if ($tem_coluna_fornecedor) {
	$cabecalho_fornecedor = '<th class="esc">Fornecedor</th>';
}

echo <<<HTML
<style>
.badge-manutencao{
	display:inline-block;
	padding:4px 8px;
	border-radius:12px;
	font-size:11px;
	font-weight:600;
	margin-left:6px;
	margin-top:3px;
}

.badge-vencida{
	background:#f8d7da;
	color:#721c24;
}

.badge-hoje{
	background:#fff3cd;
	color:#856404;
}

.badge-ok{
	background:#d4edda;
	color:#155724;
}

.badge-alerta-enviado{
	background:#d1ecf1;
	color:#0c5460;
}

.linha-vencida{
	background:#fff8f8;
}

.alerta-link{
	display:block;
	margin-top:4px;
	font-size:11px;
}
</style>

<small>
<table class="table table-hover">
<thead> 
<tr> 
<th>Cliente</th>
<th class="esc">Prótese</th>
<th class="esc">Data</th>
<th class="esc">Tipo</th>
{$cabecalho_fornecedor}
<th class="esc">Próxima</th>
<th style="width:180px">Ações</th>
</tr> 
</thead> 
<tbody>
HTML;

for ($i = 0; $i < $total_reg; $i++) {
	$id = $res[$i]['id'];
	$cliente = $res[$i]['cliente'];
	$id_protese = $res[$i]['id_protese'];
	$nome_cliente = $res[$i]['nome_cliente'];
	$nome_protese = $res[$i]['nome_protese'];
	$data_manutencao = $res[$i]['data_manutencao'];
	$tipo = $res[$i]['tipo'];
	$produtos_utilizados = $res[$i]['produtos_utilizados'];
	$observacoes = $res[$i]['observacoes'];
	$proxima_manutencao = $res[$i]['proxima_manutencao'];

	$tipo_pele = isset($res[$i]['tipo_pele']) ? $res[$i]['tipo_pele'] : '';
	$nivel_sudorese = isset($res[$i]['nivel_sudorese']) ? $res[$i]['nivel_sudorese'] : '';
	$clima = isset($res[$i]['clima']) ? $res[$i]['clima'] : '';
	$tipo_adesivo = isset($res[$i]['tipo_adesivo']) ? $res[$i]['tipo_adesivo'] : '';

	$fornecedor = '';
	$fornecedor_nome = '';

	if ($tem_coluna_fornecedor && isset($res[$i]['fornecedor'])) {
		$fornecedor = trim((string)$res[$i]['fornecedor']);
		$fornecedor_nome = $fornecedor;

		if ($fornecedor !== '' && $tem_tabela_fornecedores && ctype_digit($fornecedor)) {
			$query_fornecedor = $pdo->prepare("SELECT nome FROM fornecedores WHERE id = :id LIMIT 1");
			$query_fornecedor->bindValue(":id", $fornecedor);
			$query_fornecedor->execute();
			$res_fornecedor = $query_fornecedor->fetchAll(PDO::FETCH_ASSOC);

			if (count($res_fornecedor) > 0 && !empty($res_fornecedor[0]['nome'])) {
				$fornecedor_nome = $res_fornecedor[0]['nome'];
			}
		}
	}

	$alerta_manutencao_enviado = isset($res[$i]['alerta_manutencao_enviado']) ? $res[$i]['alerta_manutencao_enviado'] : 0;

	$data_manutencaoF = $data_manutencao ? implode('/', array_reverse(explode('-', $data_manutencao))) : '';
	$proxima_manutencaoF = $proxima_manutencao ? implode('/', array_reverse(explode('-', $proxima_manutencao))) : '';

	$classe_linha = '';
	$status_badge = '';
	$link_alerta = '';
	$badge_alerta_enviado = '';

	if ($proxima_manutencao != "") {
		if ($proxima_manutencao < $data_atual) {
			$classe_linha = 'linha-vencida';
			$status_badge = "<span class='badge-manutencao badge-vencida'>Vencida</span>";
			$link_alerta = "<a class='alerta-link text-danger' href='{$link_agendamento}&cliente={$cliente}&id_protese={$id_protese}'>Agendar manutenção</a>";
		} else if ($proxima_manutencao == $data_atual) {
			$status_badge = "<span class='badge-manutencao badge-hoje'>Vence Hoje</span>";
			$link_alerta = "<a class='alerta-link text-warning' href='{$link_agendamento}&cliente={$cliente}&id_protese={$id_protese}'>Agendar manutenção</a>";
		} else {
			$status_badge = "<span class='badge-manutencao badge-ok'>Em Dia</span>";
		}
	}

	if ($alerta_manutencao_enviado == 1) {
		$badge_alerta_enviado = "<span class='badge-manutencao badge-alerta-enviado'>Alerta Enviado</span>";
	}

	$id_html = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
	$cliente_html = htmlspecialchars($cliente, ENT_QUOTES, 'UTF-8');
	$id_protese_html = htmlspecialchars($id_protese, ENT_QUOTES, 'UTF-8');

	$nome_cliente_html = htmlspecialchars($nome_cliente, ENT_QUOTES, 'UTF-8');
	$nome_protese_html = htmlspecialchars($nome_protese, ENT_QUOTES, 'UTF-8');
	$data_manutencaoF_html = htmlspecialchars($data_manutencaoF, ENT_QUOTES, 'UTF-8');
	$tipo_html = htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8');
	$proxima_manutencaoF_html = htmlspecialchars($proxima_manutencaoF, ENT_QUOTES, 'UTF-8');
	$fornecedor_nome_html = htmlspecialchars($fornecedor_nome, ENT_QUOTES, 'UTF-8');

	$nome_cliente_js = htmlspecialchars($nome_cliente, ENT_QUOTES, 'UTF-8');
	$nome_protese_js = htmlspecialchars($nome_protese, ENT_QUOTES, 'UTF-8');
	$tipo_js = htmlspecialchars($tipo, ENT_QUOTES, 'UTF-8');
	$produtos_utilizados_js = htmlspecialchars($produtos_utilizados, ENT_QUOTES, 'UTF-8');
	$observacoes_js = htmlspecialchars($observacoes, ENT_QUOTES, 'UTF-8');
	$tipo_pele_js = htmlspecialchars($tipo_pele, ENT_QUOTES, 'UTF-8');
	$nivel_sudorese_js = htmlspecialchars($nivel_sudorese, ENT_QUOTES, 'UTF-8');
	$clima_js = htmlspecialchars($clima, ENT_QUOTES, 'UTF-8');
	$tipo_adesivo_js = htmlspecialchars($tipo_adesivo, ENT_QUOTES, 'UTF-8');
	$data_manutencao_js = htmlspecialchars($data_manutencao, ENT_QUOTES, 'UTF-8');
	$proxima_manutencao_js = htmlspecialchars($proxima_manutencao, ENT_QUOTES, 'UTF-8');
	$fornecedor_js = htmlspecialchars($fornecedor, ENT_QUOTES, 'UTF-8');
	$fornecedor_nome_js = htmlspecialchars($fornecedor_nome, ENT_QUOTES, 'UTF-8');

	$coluna_fornecedor_html = '';
	if ($tem_coluna_fornecedor) {
		$coluna_fornecedor_html = '<td class="esc">' . $fornecedor_nome_html . '</td>';
	}

echo <<<HTML
<tr class="{$classe_linha}">
<td>{$nome_cliente_html}</td>
<td class="esc">{$nome_protese_html}</td>
<td class="esc">{$data_manutencaoF_html}</td>
<td class="esc">{$tipo_html}</td>
{$coluna_fornecedor_html}
<td class="esc">
	{$proxima_manutencaoF_html}
	{$status_badge}
	{$badge_alerta_enviado}
	{$link_alerta}
</td>
<td style="white-space:nowrap;">

	<big>
		<a href="javascript:void(0)" onclick="editar('{$id_html}','{$cliente_html}','{$id_protese_html}','{$data_manutencao_js}','{$tipo_js}','{$produtos_utilizados_js}','{$observacoes_js}','{$proxima_manutencao_js}','{$tipo_pele_js}','{$nivel_sudorese_js}','{$clima_js}','{$tipo_adesivo_js}','{$fornecedor_js}')" title="Editar Dados">
			<i class="fa fa-edit text-primary"></i>
		</a>
	</big>

	<big>
		<a href="javascript:void(0)" onclick="mostrar('{$nome_cliente_js}','{$nome_protese_js}','{$data_manutencaoF_html}','{$tipo_js}','{$produtos_utilizados_js}','{$observacoes_js}','{$proxima_manutencaoF_html}','{$tipo_pele_js}','{$nivel_sudorese_js}','{$clima_js}','{$tipo_adesivo_js}','{$fornecedor_nome_js}')" title="Ver Dados">
			<i class="fa fa-info-circle text-secondary"></i>
		</a>
	</big>

	<big>
		<a href="{$link_agendamento}&cliente={$cliente_html}&id_protese={$id_protese_html}" title="Agendar Manutenção">
			<i class="fa fa-calendar text-success"></i>
		</a>
	</big>

	<li class="dropdown head-dpdn2" style="display: inline-block; list-style:none;">
		<a href="javascript:void(0)" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false" title="Excluir">
			<big><i class="fa fa-trash-o text-danger"></i></big>
		</a>

		<ul class="dropdown-menu" style="margin-left:-230px;">
			<li>
				<div class="notification_desc2">
					<p>Confirmar Exclusão? <a href="javascript:void(0)" onclick="excluir('{$id_html}')"><span class="text-danger">Sim</span></a></p>
				</div>
			</li>
		</ul>
	</li>
</td>
</tr>
HTML;

}

echo <<<HTML
</tbody>
<small><div align="center" id="mensagem-excluir"></div></small>
</table>
</small>

<hr>
<div class="row" align="center">
	<nav aria-label="Page navigation example">
		<ul class="pagination">
			<li class="page-item">
				<a onclick="listarManutencoes(0)" class="paginador" href="javascript:void(0)" aria-label="Previous">
					<span aria-hidden="true">&laquo;</span>
					<span class="sr-only">Previous</span>
				</a>
			</li>
HTML;

for ($i = 0; $i < $num_paginas; $i++) {
	$estilo = "";
	if ($pagina >= ($i - 2) and $pagina <= ($i + 2)) {
		if ($pagina == $i) {
			$estilo = "active";
		}

		$pag = $i + 1;

echo <<<HTML
<li class="page-item {$estilo}">
	<a onclick="listarManutencoes({$i})" class="paginador" href="javascript:void(0)">{$pag}</a>
</li>
HTML;

	}
}

echo <<<HTML
			<li class="page-item">
				<a onclick="listarManutencoes({$ultimo_reg})" class="paginador" href="javascript:void(0)" aria-label="Next">
					<span aria-hidden="true">&raquo;</span>
					<span class="sr-only">Next</span>
				</a>
			</li>
		</ul>
	</nav>
</div>
HTML;

} else {
	echo '<small>Não possui nenhum registro Cadastrado!</small>';
}
?>

<script type="text/javascript">
function editar(id, cliente, id_protese, data_manutencao, tipo, produtos_utilizados, observacoes, proxima_manutencao, tipo_pele, nivel_sudorese, clima, tipo_adesivo, fornecedor){
	$('#id').val(id);
	$('#cliente').val(cliente).change();
	$('#id_protese').val(id_protese).change();
	$('#data_manutencao').val(data_manutencao);
	$('#tipo').val(tipo);
	$('#produtos_utilizados').val(produtos_utilizados).change();
	$('#observacoes').val(observacoes);
	$('#proxima_manutencao').val(proxima_manutencao);
	$('#tipo_pele').val(tipo_pele);
	$('#nivel_sudorese').val(nivel_sudorese);
	$('#clima').val(clima);
	$('#tipo_adesivo').val(tipo_adesivo);

	if ($('#fornecedor').length) {
		$('#fornecedor').val(fornecedor).change();
	}

	$('#titulo_inserir').text('Editar Registro');
	$('#modalForm').modal('show');
}

function limparCampos(){
	$('#id').val('');
	$('#cliente').val('').change();
	$('#id_protese').val('').change();
	$('#data_manutencao').val('');
	$('#tipo').val('');
	$('#produtos_utilizados').val('').change();

	if ($('#fornecedor').length) {
		$('#fornecedor').val('').change();
	}

	$('#observacoes').val('');
	$('#proxima_manutencao').val('');
	$('#tipo_pele').val('');
	$('#nivel_sudorese').val('');
	$('#clima').val('');
	$('#tipo_adesivo').val('');
}
</script>

<script type="text/javascript">
function mostrar(nome_cliente, nome_protese, data_manutencao, tipo, produtos_utilizados, observacoes, proxima_manutencao, tipo_pele, nivel_sudorese, clima, tipo_adesivo, fornecedor){

	let sudorese_texto = '';
	if(nivel_sudorese == '1'){
		sudorese_texto = '1 - Transpira pouco';
	}else if(nivel_sudorese == '2'){
		sudorese_texto = '2 - Transpiração leve';
	}else if(nivel_sudorese == '3'){
		sudorese_texto = '3 - Transpiração moderada';
	}else if(nivel_sudorese == '4'){
		sudorese_texto = '4 - Transpira bastante';
	}else if(nivel_sudorese == '5'){
		sudorese_texto = '5 - Transpira muito';
	}else{
		sudorese_texto = nivel_sudorese;
	}

	$('#nome_dados').text(nome_cliente);
	$('#cliente_dados').text(nome_cliente);
	$('#protese_dados').text(nome_protese);
	$('#data_manutencao_dados').text(data_manutencao);
	$('#tipo_dados').text(tipo);
	$('#produtos_dados').text(produtos_utilizados);
	$('#observacoes_dados').text(observacoes);
	$('#proxima_manutencao_dados').text(proxima_manutencao);
	$('#tipo_pele_dados').text(tipo_pele);
	$('#nivel_sudorese_dados').text(sudorese_texto);
	$('#clima_dados').text(clima);
	$('#tipo_adesivo_dados').text(tipo_adesivo);

	if ($('#fornecedor_dados').length) {
		$('#fornecedor_dados').text(fornecedor);
	}

	$('#modalDados').modal('show');
}
</script>