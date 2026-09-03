<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'fornecedores';

if (!function_exists('bb_fornec_h')) {
	function bb_fornec_h($valor){
		return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
	}
}

if (!function_exists('bb_fornec_json_attr')) {
	function bb_fornec_json_attr(array $dados){
		$json = json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
		if ($json === false) {
			$json = '{}';
		}
		return htmlspecialchars(base64_encode($json), ENT_QUOTES, 'UTF-8');
	}
}

if (!function_exists('bb_fornec_whats_phone')) {
	function bb_fornec_whats_phone($telefone){
		$telefone = trim((string)$telefone);
		if ($telefone === '') {
			return '';
		}

		$temMais = strpos($telefone, '+') === 0;
		$digitos = preg_replace('/\D+/', '', $telefone);

		if ($digitos === '') {
			return '';
		}

		if (strpos($digitos, '00') === 0) {
			$digitos = substr($digitos, 2);
		}

		if ($temMais) {
			return $digitos;
		}

		if (strlen($digitos) > 11) {
			return $digitos;
		}

		return '55' . $digitos;
	}
}

$query = $pdo->query("SELECT * FROM $tabela ORDER BY id desc");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);

if($total_reg > 0){

echo <<<HTML
	<small>
	<table class="table table-hover" id="tabela">
	<thead> 
	<tr> 
	<th>Nome</th>	
	<th class="esc">Telefone</th> 	
	<th class="esc">Cadastro</th> 	
	<th>Ações</th>
	</tr> 
	</thead> 
	<tbody>	
HTML;

for($i=0; $i < $total_reg; $i++){
	$id = $res[$i]['id'];
	$nome = $res[$i]['nome'] ?? '';
	$data_cad = $res[$i]['data_cad'] ?? '';
	$telefone = $res[$i]['telefone'] ?? '';
	$endereco = $res[$i]['endereco'] ?? '';
	$tipo_chave = $res[$i]['tipo_chave'] ?? '';
	$chave_pix = $res[$i]['chave_pix'] ?? '';

	$data_cadF = '';
	if ($data_cad !== '' && $data_cad !== '0000-00-00') {
		$partes = explode('-', $data_cad);
		if (count($partes) === 3) {
			$data_cadF = implode('/', array_reverse($partes));
		}
	}

	$whats = bb_fornec_whats_phone($telefone);

	$registroAttr = bb_fornec_json_attr([
		'id' => (string)$id,
		'nome' => (string)$nome,
		'telefone' => (string)$telefone,
		'endereco' => (string)$endereco,
		'tipo_chave' => (string)$tipo_chave,
		'chave_pix' => (string)$chave_pix,
		'data_cad' => (string)$data_cadF
	]);

	$nome_html = bb_fornec_h($nome);
	$telefone_html = bb_fornec_h($telefone);
	$data_cad_html = bb_fornec_h($data_cadF);

	$link_whats = '';
	if ($whats !== '') {
		$link_whats = 'https://api.whatsapp.com/send?1=pt_BR&phone=' . rawurlencode($whats) . '&text=';
	}

echo <<<HTML
<tr class="">
<td>{$nome_html}</td>
<td class="esc">{$telefone_html}</td>
<td class="esc">{$data_cad_html}</td>

<td>
	<big>
		<a href="#" data-registro="{$registroAttr}" onclick="editarRegistroFornecedor(this); return false;" title="Editar Dados">
			<i class="fa fa-edit text-primary"></i>
		</a>
	</big>

	<big>
		<a href="#" data-registro="{$registroAttr}" onclick="mostrarRegistroFornecedor(this); return false;" title="Ver Dados">
			<i class="fa fa-info-circle text-secondary"></i>
		</a>
	</big>

	<li class="dropdown head-dpdn2" style="display: inline-block;">
		<a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
			<big><i class="fa fa-trash-o text-danger"></i></big>
		</a>

		<ul class="dropdown-menu" style="margin-left:-230px;">
			<li>
				<div class="notification_desc2">
					<p>Confirmar Exclusão? <a href="#" onclick="excluir('{$id}'); return false;"><span class="text-danger">Sim</span></a></p>
				</div>
			</li>										
		</ul>
	</li>
HTML;

if ($link_whats !== '') {
	$link_whats_html = bb_fornec_h($link_whats);
	echo <<<HTML
	<big><a href="{$link_whats_html}" target="_blank" rel="noopener noreferrer" title="Abrir Whatsapp"><i class="fa fa-whatsapp verde"></i></a></big>
HTML;
} else {
	echo <<<HTML
	<big><a href="#" onclick="return false;" title="Telefone não informado"><i class="fa fa-whatsapp verde"></i></a></big>
HTML;
}

echo <<<HTML
</td>
</tr>
HTML;

}

echo <<<HTML
</tbody>
<small><div align="center" id="mensagem-excluir"></div></small>
</table>
</small>
HTML;

}else{
	echo '<small>Não possui nenhum registro Cadastrado!</small>';
}
?>

<script type="text/javascript">
	$(document).ready(function () {
		$('#tabela').DataTable({
			"ordering": false,
			"stateSave": true
		});
		$('#tabela_filter label input').focus();
	});
</script>

<script type="text/javascript">
	function bbFornecLerRegistroDoElemento(el){
		var valor = '';

		if (el && typeof el.getAttribute === 'function') {
			valor = el.getAttribute('data-registro') || '';
		}

		if (!valor) {
			return null;
		}

		try {
			var json = atob(valor);
			return JSON.parse(json);
		} catch (e) {
			console.error('Falha ao ler registro do fornecedor:', e);
			return null;
		}
	}

	function editarRegistroFornecedor(el){
		var dados = bbFornecLerRegistroDoElemento(el);
		if (!dados) {
			return false;
		}

		$('#id').val(dados.id || '');
		$('#nome').val(dados.nome || '');
		$('#telefone').val(dados.telefone || '');
		$('#endereco').val(dados.endereco || '');
		$('#chave_pix').val(dados.chave_pix || '');
		$('#tipo_chave').val(dados.tipo_chave || '').change();

		if (typeof window.bb_fornec_aplicar_mascaras === 'function') {
			window.bb_fornec_aplicar_mascaras();
		}

		$('#titulo_inserir').text('Editar Registro');
		$('#modalForm').modal('show');
		return false;
	}

	function limparCampos(){
		$('#id').val('');
		$('#nome').val('');
		$('#telefone').val('');
		$('#endereco').val('');
		$('#chave_pix').val('');
		$('#tipo_chave').val('');
		$('#telefone_pais').val('br').change();
		$('#telefone_ddi').val('55');
		$('#telefone_pais_nome').val('Brasil');

		if (typeof window.bb_fornec_aplicar_mascaras === 'function') {
			window.bb_fornec_aplicar_mascaras();
		}
	}
</script>

<script type="text/javascript">
	function mostrarRegistroFornecedor(el){
		var dados = bbFornecLerRegistroDoElemento(el);
		if (!dados) {
			return false;
		}

		$('#nome_dados').text(dados.nome || '');
		$('#data_cad_dados').text(dados.data_cad || '');
		$('#telefone_dados').text(dados.telefone || '');
		$('#endereco_dados').text(dados.endereco || '');
		$('#tipo_chave_dados').text(dados.tipo_chave || '');
		$('#chave_pix_dados').text(dados.chave_pix || '');
		$('#modalDados').modal('show');
		return false;
	}
</script>
