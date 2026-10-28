<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once(__DIR__ . '/../../verificar.php');
require_once(__DIR__ . '/../../../conexao.php');

$tabela = 'proteses';

$busca = isset($_POST['busca']) ? trim($_POST['busca']) : '';
$busca = '%' . $busca . '%';

if (!isset($_POST['pagina']) || $_POST['pagina'] === '') {
	$_POST['pagina'] = 0;
}

$pagina = (int)$_POST['pagina'];
if ($pagina < 0) {
	$pagina = 0;
}

$limite = $pagina * $itens_pag;

$query = $pdo->prepare("SELECT p.*, c.nome as nome_cliente, f.nome as nome_fornecedor
	FROM $tabela p
	INNER JOIN clientes c ON p.cliente = c.id
	LEFT JOIN fornecedores f ON p.fornecedor = f.id
	WHERE c.nome LIKE :busca 
		OR p.modelo LIKE :busca 
		OR p.cor LIKE :busca
		OR f.nome LIKE :busca
	ORDER BY p.id DESC LIMIT $limite, $itens_pag");
$query->bindValue(":busca", $busca);
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = count($res);

if ($total_reg > 0) {

echo <<<HTML
<small>
<table class="table table-hover">
<thead> 
<tr> 
<th>Cliente</th>
<th class="esc">Modelo</th>
<th class="esc">Cor</th>
<th class="esc">Densidade</th>
<th class="esc">Tamanho</th>
<th class="esc">Fornecedor</th>
<th class="esc">Cadastro</th>
<th style="width:180px">Ações</th>
</tr> 
</thead> 
<tbody>
HTML;

for ($i = 0; $i < $total_reg; $i++) {
	$id = $res[$i]['id'];
	$cliente = $res[$i]['cliente'];
	$modelo = $res[$i]['modelo'];
	$cor = $res[$i]['cor'];
	$densidade = $res[$i]['densidade'];
	$tamanho = $res[$i]['tamanho'];
	$observacoes = $res[$i]['observacoes'];
	$data_cad = $res[$i]['data_cad'];
	$nome_cliente = $res[$i]['nome_cliente'];
	$fornecedor = isset($res[$i]['fornecedor']) ? $res[$i]['fornecedor'] : '';
	$nome_fornecedor = isset($res[$i]['nome_fornecedor']) ? $res[$i]['nome_fornecedor'] : '';

	$data_cadF = '';
	if ($data_cad != '') {
		$data_cadF = implode('/', array_reverse(explode('-', $data_cad)));
	}

	if ($modelo == '') {
		$modelo = '-';
	}

	if ($cor == '') {
		$cor = '-';
	}

	if ($densidade == '') {
		$densidade = '-';
	}

	if ($tamanho == '') {
		$tamanho = '-';
	}

	if ($nome_fornecedor == '') {
		$nome_fornecedor = '-';
	}

	$id_js = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');
	$cliente_js = htmlspecialchars($cliente, ENT_QUOTES, 'UTF-8');
	$modelo_js = htmlspecialchars($modelo, ENT_QUOTES, 'UTF-8');
	$cor_js = htmlspecialchars($cor, ENT_QUOTES, 'UTF-8');
	$densidade_js = htmlspecialchars($densidade, ENT_QUOTES, 'UTF-8');
	$tamanho_js = htmlspecialchars($tamanho, ENT_QUOTES, 'UTF-8');
	$observacoes_js = htmlspecialchars($observacoes, ENT_QUOTES, 'UTF-8');
	$data_cadF_js = htmlspecialchars($data_cadF, ENT_QUOTES, 'UTF-8');
	$nome_cliente_js = htmlspecialchars($nome_cliente, ENT_QUOTES, 'UTF-8');
	$fornecedor_js = htmlspecialchars($fornecedor, ENT_QUOTES, 'UTF-8');
	$nome_fornecedor_js = htmlspecialchars($nome_fornecedor, ENT_QUOTES, 'UTF-8');

	$nome_cliente_html = htmlspecialchars($nome_cliente, ENT_QUOTES, 'UTF-8');
	$modelo_html = htmlspecialchars($modelo, ENT_QUOTES, 'UTF-8');
	$cor_html = htmlspecialchars($cor, ENT_QUOTES, 'UTF-8');
	$densidade_html = htmlspecialchars($densidade, ENT_QUOTES, 'UTF-8');
	$tamanho_html = htmlspecialchars($tamanho, ENT_QUOTES, 'UTF-8');
	$nome_fornecedor_html = htmlspecialchars($nome_fornecedor, ENT_QUOTES, 'UTF-8');
	$data_cadF_html = htmlspecialchars($data_cadF, ENT_QUOTES, 'UTF-8');

echo <<<HTML
<tr>
<td>{$nome_cliente_html}</td>
<td class="esc">{$modelo_html}</td>
<td class="esc">{$cor_html}</td>
<td class="esc">{$densidade_html}</td>
<td class="esc">{$tamanho_html}</td>
<td class="esc">{$nome_fornecedor_html}</td>
<td class="esc">{$data_cadF_html}</td>
<td style="white-space:nowrap;">

	<big>
		<a href="javascript:void(0)" onclick="editar('{$id_js}','{$cliente_js}','{$modelo_js}','{$cor_js}','{$densidade_js}','{$tamanho_js}','{$observacoes_js}','{$fornecedor_js}')" title="Editar Dados">
			<i class="fa fa-edit text-primary"></i>
		</a>
	</big>

	<big>
		<a href="javascript:void(0)" onclick="mostrar('{$nome_cliente_js}','{$modelo_js}','{$cor_js}','{$densidade_js}','{$tamanho_js}','{$observacoes_js}','{$data_cadF_js}','{$nome_fornecedor_js}')" title="Ver Dados">
			<i class="fa fa-info-circle text-secondary"></i>
		</a>
	</big>

	<li class="dropdown head-dpdn2" style="display: inline-block; list-style:none;">
		<a href="javascript:void(0)" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false" title="Excluir">
			<big><i class="fa fa-trash-o text-danger"></i></big>
		</a>

		<ul class="dropdown-menu" style="margin-left:-230px;">
			<li>
				<div class="notification_desc2">
					<p>Confirmar Exclusão? <a href="javascript:void(0)" onclick="excluir('{$id_js}')"><span class="text-danger">Sim</span></a></p>
				</div>
			</li>										
		</ul>
	</li>
</td>
</tr>
HTML;

}

$query2 = $pdo->prepare("SELECT COUNT(*) as total
	FROM $tabela p
	INNER JOIN clientes c ON p.cliente = c.id
	LEFT JOIN fornecedores f ON p.fornecedor = f.id
	WHERE c.nome LIKE :busca 
		OR p.modelo LIKE :busca 
		OR p.cor LIKE :busca
		OR f.nome LIKE :busca");
$query2->bindValue(":busca", $busca);
$query2->execute();
$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
$total_reg2 = isset($res2[0]['total']) ? (int)$res2[0]['total'] : 0;

$num_paginas = ceil($total_reg2 / $itens_pag);
$ultimo_reg = $num_paginas - 1;

if ($ultimo_reg < 0) {
	$ultimo_reg = 0;
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
				<a onclick="listarProteses(0)" class="paginador" href="javascript:void(0)" aria-label="Previous">
					<span aria-hidden="true">&laquo;</span>
					<span class="sr-only">Previous</span>
				</a>
			</li>
HTML;

for ($i = 0; $i < $num_paginas; $i++) {
	$estilo = "";
	if ($pagina >= ($i - 2) && $pagina <= ($i + 2)) {
		if ($pagina == $i) {
			$estilo = "active";
		}

		$pag = $i + 1;

echo <<<HTML
<li class="page-item {$estilo}">
	<a onclick="listarProteses({$i})" class="paginador" href="javascript:void(0)">{$pag}</a>
</li>
HTML;

	}
}

echo <<<HTML
			<li class="page-item">
				<a onclick="listarProteses({$ultimo_reg})" class="paginador" href="javascript:void(0)" aria-label="Next">
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
function editar(id, cliente, modelo, cor, densidade, tamanho, observacoes, fornecedor){
	$('#id').val(id);
	$('#cliente').val(cliente).change();
	$('#modelo').val(modelo == '-' ? '' : modelo);
	$('#cor').val(cor == '-' ? '' : cor);
	$('#densidade').val(densidade == '-' ? '' : densidade);
	$('#tamanho').val(tamanho == '-' ? '' : tamanho);
	$('#observacoes').val(observacoes);

	if($('#fornecedor').length){
		$('#fornecedor').val(fornecedor).change();
	}

	$('#titulo_inserir').text('Editar Registro');
	$('#mensagem').text('');
	$('#mensagem').removeClass();
	$('#modalForm').modal('show');
}

function limparCampos(){
	$('#id').val('');
	$('#cliente').val('').change();
	$('#modelo').val('');
	$('#cor').val('');
	$('#densidade').val('');
	$('#tamanho').val('');
	$('#observacoes').val('');

	if($('#fornecedor').length){
		$('#fornecedor').val('').change();
	}
}
</script>

<script type="text/javascript">
function mostrar(nome_cliente, modelo, cor, densidade, tamanho, observacoes, data_cad, nome_fornecedor){
	$('#nome_dados').text(nome_cliente);
	$('#cliente_dados').text(nome_cliente);
	$('#modelo_dados').text(modelo == '-' ? '' : modelo);
	$('#cor_dados').text(cor == '-' ? '' : cor);
	$('#densidade_dados').text(densidade == '-' ? '' : densidade);
	$('#tamanho_dados').text(tamanho == '-' ? '' : tamanho);
	$('#fornecedor_dados').text(nome_fornecedor == '-' ? '' : nome_fornecedor);
	$('#data_cad_dados').text(data_cad);
	$('#observacoes_dados').text(observacoes);

	$('#modalDados').modal('show');
}
</script>