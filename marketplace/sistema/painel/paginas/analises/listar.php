<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once(__DIR__ . '/../../verificar.php');
require_once(__DIR__ . '/../../../conexao.php');

$tabela = 'analise_capilar';

$busca = isset($_POST['busca']) ? trim($_POST['busca']) : '';
$busca = '%' . $busca . '%';

$pagina = isset($_POST['pagina']) ? (int)$_POST['pagina'] : 0;
if ($pagina < 0) {
	$pagina = 0;
}

$limite = $pagina * $itens_pag;
$agora_cache = time();

$query = $pdo->prepare("SELECT a.*, c.nome as nome_cliente 
	FROM $tabela a 
	INNER JOIN clientes c ON a.cliente = c.id
	WHERE (c.nome LIKE :busca OR a.sugestao_protese LIKE :busca)
	ORDER BY a.id DESC LIMIT $limite, $itens_pag");
$query->bindValue(":busca", $busca);
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = count($res);

if ($total_reg > 0) {

echo <<<HTML
<style>
.card-analise-lista{
	border:1px solid #e9ecef;
	border-radius:10px;
	padding:14px;
	margin-bottom:12px;
	background:#fff;
	box-shadow:0 1px 6px rgba(0,0,0,0.03);
}

.card-analise-lista:hover{
	box-shadow:0 3px 12px rgba(0,0,0,0.06);
}

.card-topo-analise{
	display:flex;
	align-items:center;
	justify-content:space-between;
	flex-wrap:wrap;
	gap:10px;
	margin-bottom:12px;
}

.cliente-analise{
	font-size:18px;
	font-weight:600;
	color:#2b2b2b;
}

.data-analise-badge{
	background:#eef5ff;
	color:#2f6fad;
	padding:5px 12px;
	border-radius:20px;
	font-size:12px;
	font-weight:600;
}

.grid-analise{
	display:grid;
	grid-template-columns: 110px 1fr 220px;
	gap:15px;
	align-items:center;
}

.foto-analise-lista{
	width:100px;
	height:100px;
	object-fit:cover;
	border-radius:8px;
	border:1px solid #ddd;
	background:#f8f8f8;
}

.info-tecnica-analise{
	display:grid;
	grid-template-columns: repeat(2, minmax(180px, 1fr));
	gap:10px;
}

.item-info-analise{
	background:#f9fafb;
	border:1px solid #edf0f2;
	border-radius:8px;
	padding:10px 12px;
}

.item-info-analise .rotulo{
	display:block;
	font-size:12px;
	color:#7c8b98;
	margin-bottom:4px;
	font-weight:600;
	text-transform:uppercase;
	letter-spacing:.3px;
}

.item-info-analise .valor{
	font-size:16px;
	color:#2d3436;
	font-weight:600;
}

.acoes-analise{
	display:flex;
	align-items:center;
	justify-content:flex-end;
	gap:12px;
	flex-wrap:wrap;
}

.btn-acao-analise{
	display:inline-flex;
	align-items:center;
	justify-content:center;
	width:34px;
	height:34px;
	border-radius:50%;
	background:#f8f9fa;
	border:1px solid #e9ecef;
	text-decoration:none;
}

.btn-acao-analise:hover{
	background:#eef5ff;
	border-color:#cfe2ff;
}

.obs-analise-lista{
	margin-top:12px;
	padding-top:12px;
	border-top:1px solid #f0f0f0;
	font-size:13px;
	color:#666;
}

@media (max-width: 991px){
	.grid-analise{
		grid-template-columns: 1fr;
	}

	.info-tecnica-analise{
		grid-template-columns: 1fr 1fr;
	}

	.acoes-analise{
		justify-content:flex-start;
	}
}

@media (max-width: 576px){
	.info-tecnica-analise{
		grid-template-columns: 1fr;
	}

	.foto-analise-lista{
		width:100%;
		height:auto;
		max-height:220px;
	}
}
</style>
HTML;

for ($i = 0; $i < $total_reg; $i++) {
	$id = $res[$i]['id'];
	$cliente = $res[$i]['cliente'];
	$nome_cliente = $res[$i]['nome_cliente'];
	$data_analise = $res[$i]['data_analise'];
	$foto = $res[$i]['foto'];
	$cor_detectada = $res[$i]['cor_detectada'] ?? '';
	$densidade_detectada = $res[$i]['densidade_detectada'] ?? '';
	$grau_falha = $res[$i]['grau_falha'] ?? '';
	$sugestao_protese = $res[$i]['sugestao_protese'] ?? '';
	$observacoes = $res[$i]['observacoes'] ?? '';

	$imagem_simulada = '';

	$query_img = $pdo->prepare("SELECT imagem_simulada FROM simulacoes_protese WHERE cliente = :cliente ORDER BY id DESC LIMIT 1");
	$query_img->bindValue(":cliente", $cliente);
	$query_img->execute();
	$res_img = $query_img->fetchAll(PDO::FETCH_ASSOC);

	if (count($res_img) > 0) {
		$imagem_simulada = $res_img[0]['imagem_simulada'];
	}

	$data_analiseF = '';
	if (!empty($data_analise)) {
		$partes_data = explode('-', $data_analise);
		if (count($partes_data) === 3) {
			$data_analiseF = $partes_data[2] . '/' . $partes_data[1] . '/' . $partes_data[0];
		}
	}

	$foto_src = 'images/analises/sem-foto.jpg';

	if ($imagem_simulada != "") {
		$foto_src = 'images/simulacoes/' . $imagem_simulada . '?v=' . $agora_cache;
	} elseif ($foto != "") {
		$foto_src = 'images/analises/' . $foto . '?v=' . $agora_cache;
	}

	if ($cor_detectada == "") {
		$cor_detectada = '-';
	}

	if ($densidade_detectada == "") {
		$densidade_detectada = '-';
	}

	if ($grau_falha == "") {
		$grau_falha = '-';
	}

	if ($sugestao_protese == "") {
		$sugestao_protese = '-';
	}

	$nome_cliente_html = htmlspecialchars($nome_cliente, ENT_QUOTES, 'UTF-8');
	$data_analiseF_html = htmlspecialchars($data_analiseF, ENT_QUOTES, 'UTF-8');
	$foto_src_html = htmlspecialchars($foto_src, ENT_QUOTES, 'UTF-8');
	$cor_detectada_html = htmlspecialchars($cor_detectada, ENT_QUOTES, 'UTF-8');
	$densidade_detectada_html = htmlspecialchars($densidade_detectada, ENT_QUOTES, 'UTF-8');
	$grau_falha_html = htmlspecialchars($grau_falha, ENT_QUOTES, 'UTF-8');
	$sugestao_protese_html = htmlspecialchars($sugestao_protese, ENT_QUOTES, 'UTF-8');
	$observacoes_html = htmlspecialchars($observacoes, ENT_QUOTES, 'UTF-8');

	$nome_cliente_js = htmlspecialchars($nome_cliente, ENT_QUOTES, 'UTF-8');
	$data_analiseF_js = htmlspecialchars($data_analiseF, ENT_QUOTES, 'UTF-8');
	$foto_js = htmlspecialchars($foto, ENT_QUOTES, 'UTF-8');
	$imagem_simulada_js = htmlspecialchars($imagem_simulada, ENT_QUOTES, 'UTF-8');
	$cor_detectada_js = htmlspecialchars($cor_detectada, ENT_QUOTES, 'UTF-8');
	$densidade_detectada_js = htmlspecialchars($densidade_detectada, ENT_QUOTES, 'UTF-8');
	$grau_falha_js = htmlspecialchars($grau_falha, ENT_QUOTES, 'UTF-8');
	$sugestao_protese_js = htmlspecialchars($sugestao_protese, ENT_QUOTES, 'UTF-8');
	$observacoes_js = htmlspecialchars($observacoes, ENT_QUOTES, 'UTF-8');
	$data_analise_js = htmlspecialchars($data_analise, ENT_QUOTES, 'UTF-8');
	$cliente_js = htmlspecialchars($cliente, ENT_QUOTES, 'UTF-8');
	$id_js = htmlspecialchars($id, ENT_QUOTES, 'UTF-8');

echo <<<HTML
<div class="card-analise-lista">
	<div class="card-topo-analise">
		<div class="cliente-analise">{$nome_cliente_html}</div>
		<div class="data-analise-badge">Análise: {$data_analiseF_html}</div>
	</div>

	<div class="grid-analise">
		<div>
			<img src="{$foto_src_html}" class="foto-analise-lista">
		</div>

		<div class="info-tecnica-analise">
			<div class="item-info-analise">
				<span class="rotulo">Cor Detectada</span>
				<span class="valor">{$cor_detectada_html}</span>
			</div>

			<div class="item-info-analise">
				<span class="rotulo">Densidade</span>
				<span class="valor">{$densidade_detectada_html}</span>
			</div>

			<div class="item-info-analise">
				<span class="rotulo">Grau de Falha</span>
				<span class="valor">{$grau_falha_html}</span>
			</div>

			<div class="item-info-analise">
				<span class="rotulo">Sugestão de Prótese</span>
				<span class="valor">{$sugestao_protese_html}</span>
			</div>
		</div>

		<div class="acoes-analise">
			<a href="javascript:void(0)" class="btn-acao-analise" onclick="editar('{$id_js}','{$cliente_js}','{$data_analise_js}','{$foto_js}','{$cor_detectada_js}','{$densidade_detectada_js}','{$grau_falha_js}','{$sugestao_protese_js}','{$observacoes_js}')" title="Editar">
				<i class="fa fa-edit text-primary"></i>
			</a>

			<a href="javascript:void(0)" class="btn-acao-analise" onclick="mostrar('{$nome_cliente_js}','{$data_analiseF_js}','{$foto_js}','{$imagem_simulada_js}','{$cor_detectada_js}','{$densidade_detectada_js}','{$grau_falha_js}','{$sugestao_protese_js}','{$observacoes_js}')" title="Visualizar">
				<i class="fa fa-info-circle text-info"></i>
			</a>

			<a href="javascript:void(0)" class="btn-acao-analise" onclick="excluir('{$id_js}')" title="Excluir">
				<i class="fa fa-trash text-danger"></i>
			</a>
		</div>
	</div>
HTML;

if ($observacoes != "") {
echo <<<HTML
	<div class="obs-analise-lista">
		<strong>Observações:</strong> {$observacoes_html}
	</div>
HTML;
}

echo <<<HTML
</div>
HTML;

}

$query2 = $pdo->prepare("SELECT COUNT(*) as total 
	FROM $tabela a 
	INNER JOIN clientes c ON a.cliente = c.id
	WHERE (c.nome LIKE :busca OR a.sugestao_protese LIKE :busca)");
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
<hr>
<div class="row" align="center">
	<nav aria-label="Page navigation example">
		<ul class="pagination">
			<li class="page-item">
				<a onclick="listarAnalises(0)" class="paginador" href="javascript:void(0)" aria-label="Previous">
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
	<a onclick="listarAnalises({$i})" class="paginador" href="javascript:void(0)">{$pag}</a>
</li>
HTML;

	}
}

echo <<<HTML
			<li class="page-item">
				<a onclick="listarAnalises({$ultimo_reg})" class="paginador" href="javascript:void(0)" aria-label="Next">
					<span aria-hidden="true">&raquo;</span>
					<span class="sr-only">Next</span>
				</a>
			</li>
		</ul>
	</nav>
</div>

<small><div align="center" id="mensagem-excluir"></div></small>
HTML;

} else {
	echo '<small>Não possui nenhuma análise cadastrada!</small>';
}
?>

<script type="text/javascript">
function editar(id, cliente, data_analise, foto, cor_detectada, densidade_detectada, grau_falha, sugestao_protese, observacoes){
	$('#id').val(id);
	$('#cliente').val(cliente).change();
	$('#data_analise').val(data_analise);
	$('#foto_atual').val(foto);
	$('#cor_detectada').val(cor_detectada == '-' ? '' : cor_detectada);
	$('#densidade_detectada').val(densidade_detectada == '-' ? '' : densidade_detectada);
	$('#grau_falha').val(grau_falha == '-' ? '' : grau_falha);
	$('#sugestao_protese').val(sugestao_protese == '-' ? '' : sugestao_protese);
	$('#observacoes').val(observacoes);

	$('#titulo_inserir').text('Análise Técnica Capilar');

	if(foto != ""){
		$('#target').attr('src', 'images/analises/' + foto + '?v=' + new Date().getTime());
	}else{
		$('#target').attr('src', 'images/analises/sem-foto.jpg');
	}

	$('#mensagem').text('');
	$('#mensagem').removeClass();
	$('#mensagem_ia').text('');
	$('#mensagem_ia').removeClass();

	$('#modalForm').modal('show');
}
</script>

<script type="text/javascript">
function mostrar(nome_cliente, data_analise, foto, imagem_simulada, cor_detectada, densidade_detectada, grau_falha, sugestao_protese, observacoes){
	$('#nome_dados').text('Ficha Técnica da Análise');
	$('#cliente_dados').text(nome_cliente);
	$('#data_analise_dados').text(data_analise);
	$('#cor_dados').text(cor_detectada);
	$('#densidade_dados').text(densidade_detectada);
	$('#grau_dados').text(grau_falha);
	$('#sugestao_dados').text(sugestao_protese);

	$('#cor_resumo').text(cor_detectada);
	$('#densidade_resumo').text(densidade_detectada);
	$('#grau_resumo').text(grau_falha);
	$('#sugestao_resumo').text(sugestao_protese);

	if(observacoes == ""){
		observacoes = 'Sem observações informadas.';
	}

	$('#observacoes_dados').text(observacoes);

	if(imagem_simulada != ""){
		$('#foto_dados').attr('src', 'images/simulacoes/' + imagem_simulada + '?v=' + new Date().getTime());
	}else if(foto != ""){
		$('#foto_dados').attr('src', 'images/analises/' + foto + '?v=' + new Date().getTime());
	}else{
		$('#foto_dados').attr('src', 'images/analises/sem-foto.jpg');
	}

	$('#modalDados').modal('show');
}
</script>