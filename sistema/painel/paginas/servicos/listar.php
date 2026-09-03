<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';

$tenant_db_path = $_SERVER['DOCUMENT_ROOT'] . "/api/tenant_db.php";
if (is_file($tenant_db_path)) {
	require_once($tenant_db_path);
}

$tabela = 'servicos';

/*
    EXCLUIR:
    - Somente nível Administrador (admin do sistema e dono do salão usam esse nível no seu BD)
*/
$nivel = isset($_SESSION['nivel']) ? (string)$_SESSION['nivel'] : '';
$mostrarExcluir = (mb_strtolower(trim($nivel)) === 'administrador');

// colunas opcionais do módulo de combos
$colComboAtivo = false;
$colComboQtd = false;
$colComboValidade = false;

try {
	$stCols = $pdo->query("SHOW COLUMNS FROM servicos");
	$cols = $stCols->fetchAll(PDO::FETCH_COLUMN, 0);
	$colComboAtivo = in_array('combo_ativo', $cols, true);
	$colComboQtd = in_array('combo_qtd_sessoes', $cols, true);
	$colComboValidade = in_array('combo_validade_dias', $cols, true);
} catch (Throwable $e) {
	$colComboAtivo = false;
	$colComboQtd = false;
	$colComboValidade = false;
}

$whereServ = '';
$paramsServ = [];

if (function_exists('bb_tenant_ctx') && function_exists('bb_table_tenant_filter')) {
	try {
		$ctx = bb_tenant_ctx();
		$filtroServ = bb_table_tenant_filter($pdo, $tabela, $ctx);
		if (!empty($filtroServ['col']) && array_key_exists('val', $filtroServ) && $filtroServ['val'] !== null && $filtroServ['val'] !== '') {
			$whereServ = " WHERE {$filtroServ['col']} = :tenant_listar";
			$paramsServ[':tenant_listar'] = $filtroServ['val'];
		}
	} catch (Throwable $e) {
		$whereServ = '';
		$paramsServ = [];
	}
}

$query = $pdo->prepare("SELECT * FROM $tabela {$whereServ} ORDER BY id desc");
foreach ($paramsServ as $param => $valorParam) {
	$query->bindValue($param, $valorParam, is_int($valorParam) ? PDO::PARAM_INT : PDO::PARAM_STR);
}
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = count($res);

if($total_reg > 0){

	if(isset($tipo_comissao) && $tipo_comissao == 'Porcentagem'){
		$item_comissao = '(%)';
	}else{
		$item_comissao = '(R$)';
	}

	echo '<small>';
	echo '<table class="table table-hover" id="tabela">';
	echo '<thead>';
	echo '<tr>';
	echo '<th>Nome</th>';
	echo '<th class="esc">Categoria</th>';
	echo '<th class="esc">Valor</th>';
	echo '<th class="esc">Dias Retorno</th>';
	echo '<th class="esc">Comissão '.$item_comissao.'</th>';
	echo '<th class="esc">Tempo</th>';
	echo '<th class="esc">Combo</th>';
	echo '<th class="esc">Sessões</th>';
	echo '<th class="esc">Validade</th>';
	echo '<th>Ações</th>';
	echo '</tr>';
	echo '</thead>';
	echo '<tbody>';

	$stCat = $pdo->prepare("SELECT nome FROM cat_servicos WHERE id = :id LIMIT 1");

	foreach($res as $linha){
		$id = isset($linha['id']) ? (int)$linha['id'] : 0;
		$nome = (string)($linha['nome'] ?? '');
		$descricao = (string)($linha['descricao'] ?? '');
		$categoria = isset($linha['categoria']) ? (int)$linha['categoria'] : 0;
		$dias_retorno = $linha['dias_retorno'] ?? 0;
		$valor = $linha['valor'] ?? 0;
		$foto = basename((string)($linha['foto'] ?? 'sem-foto.jpg'));
		$comissao = $linha['comissao'] ?? 0;
		$tempo = $linha['tempo'] ?? '';
		$ativo = $linha['ativo'] ?? 'Sim';

		$combo_ativo = ($colComboAtivo && isset($linha['combo_ativo'])) ? $linha['combo_ativo'] : 'Não';
		$combo_qtd_sessoes = ($colComboQtd && isset($linha['combo_qtd_sessoes'])) ? (int)$linha['combo_qtd_sessoes'] : 0;
		$combo_validade_dias = ($colComboValidade && isset($linha['combo_validade_dias'])) ? (int)$linha['combo_validade_dias'] : 0;

		$valorF = number_format((float)$valor, 2, ',', '.');

		$stCat->bindValue(':id', (int)$categoria, PDO::PARAM_INT);
		$stCat->execute();
		$nome_cat = $stCat->fetchColumn();
		if(!$nome_cat){
			$nome_cat = 'Sem Referência!';
		}

		if($ativo == 'Sim'){
			$icone = 'fa-check-square';
			$titulo_link = 'Desativar Item';
			$acao = 'Não';
			$classe_linha = '';
		}else{
			$icone = 'fa-square-o';
			$titulo_link = 'Ativar Item';
			$acao = 'Sim';
			$classe_linha = 'text-muted';
		}

		$nome_html = htmlspecialchars($nome, ENT_QUOTES, 'UTF-8');
		$nome_cat_html = htmlspecialchars($nome_cat, ENT_QUOTES, 'UTF-8');

		$nome_js = addslashes(str_replace(array("\r", "\n"), ' ', $nome));
		$descricao_js = addslashes(str_replace(array("\r", "\n"), ' ', $descricao));
		$foto_js = addslashes($foto);
		$tempo_js = addslashes(str_replace(array("\r", "\n"), ' ', (string)$tempo));

		if(isset($tipo_comissao) && $tipo_comissao == 'Porcentagem'){
			$comissaoF = number_format((float)$comissao, 0, ',', '.');
		}else{
			$comissaoF = number_format((float)$comissao, 2, ',', '.');
		}
		$comissaoF_js = addslashes((string)$comissaoF);

		$blocoExcluir = '';
		if($mostrarExcluir){
			$blocoExcluir = '
			<li class="dropdown head-dpdn2" style="display: inline-block;">
			<a href="javascript:void(0)" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><big><i class="fa fa-trash-o text-danger"></i></big></a>
			<ul class="dropdown-menu" style="margin-left:-230px;">
				<li>
					<div class="notification_desc2">
						<p>Confirmar Exclusão? <a href="javascript:void(0)" onclick="excluir(\''.$id.'\'); return false;"><span class="text-danger">Sim</span></a></p>
					</div>
				</li>
			</ul>
			</li>';
		}

		echo '<tr class="'.$classe_linha.'">';
		echo '<td><img src="img/servicos/'.htmlspecialchars($foto, ENT_QUOTES, 'UTF-8').'" width="27px" class="mr-2"> '.$nome_html.'</td>';
		echo '<td class="esc">'.$nome_cat_html.'</td>';
		echo '<td class="esc">R$ '.$valorF.'</td>';
		echo '<td class="esc">'.$dias_retorno.'</td>';
		echo '<td class="esc">'.$comissaoF.'</td>';
		echo '<td class="esc">'.htmlspecialchars((string)$tempo, ENT_QUOTES, 'UTF-8').'</td>';
		echo '<td class="esc">'.$combo_ativo.'</td>';
		echo '<td class="esc">'.$combo_qtd_sessoes.'</td>';
		echo '<td class="esc">'.$combo_validade_dias.' dias</td>';
		echo '<td>
			<big><a href="javascript:void(0)" onclick="editar(\''.$id.'\',\''.$nome_js.'\', \''.$categoria.'\', \''.$descricao_js.'\', \''.$valor.'\', \''.$foto_js.'\', \''.$dias_retorno.'\', \''.$comissao.'\', \''.$tempo_js.'\', \''.$combo_ativo.'\', \''.$combo_qtd_sessoes.'\', \''.$combo_validade_dias.'\'); return false;" title="Editar Dados"><i class="fa fa-edit text-primary"></i></a></big>
			<big><a href="javascript:void(0)" onclick="mostrar(\''.$nome_js.'\', \''.$nome_cat_html.'\', \''.$descricao_js.'\', \''.$valorF.'\', \''.$dias_retorno.'\', \''.$comissaoF_js.'\', \''.$tempo_js.'\', \''.$foto_js.'\', \''.$ativo.'\', \''.$combo_ativo.'\', \''.$combo_qtd_sessoes.'\', \''.$combo_validade_dias.'\'); return false;" title="Ver Dados"><i class="fa fa-info-circle text-secondary"></i></a></big>
			'.$blocoExcluir.'
			<big><a href="javascript:void(0)" onclick="ativar(\''.$id.'\', \''.$acao.'\'); return false;" title="'.$titulo_link.'"><i class="fa '.$icone.' text-success"></i></a></big>
		</td>';
		echo '</tr>';
	}

	echo '</tbody>';
	echo '<small><div align="center" id="mensagem-excluir"></div></small>';
	echo '</table>';
	echo '</small>';
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

function editar(id, nome, categoria, descricao, valor, foto, dias_retorno, comissao, tempo, combo_ativo, combo_qtd_sessoes, combo_validade_dias){
	$('#id').val(id);
	$('#nome').val(nome);
	$('#valor').val(valor);
	if(typeof mascara_valor === 'function'){ mascara_valor('valor'); }

	$('#categoria').val(categoria).change();
	if($('#descricao').length){ $('#descricao').val(descricao); }
	$('#dias_retorno').val(dias_retorno);
	$('#comissao').val(comissao);
	if(typeof mascara_valor === 'function'){ mascara_valor('comissao'); }

	$('#tempo').val(tempo);
	$('#combo_ativo').val(combo_ativo).change();
	$('#combo_qtd_sessoes').val(combo_qtd_sessoes);
	$('#combo_validade_dias').val(combo_validade_dias);

	if(typeof mostrarCamposCombo === 'function'){
		mostrarCamposCombo();
	}

	$('#titulo_inserir').text('Editar Registro');
	$('#modalForm').modal('show');
	$('#foto').val('');
	$('#target').attr('src','img/servicos/' + foto);
}

function limparCampos(){
	$('#id').val('');
	$('#nome').val('');
	$('#valor').val('');
	if(typeof mascara_valor === 'function'){ mascara_valor('valor'); }
	if($('#descricao').length){ $('#descricao').val(''); }
	$('#dias_retorno').val('');
	$('#comissao').val('');
	$('#tempo').val('');
	$('#combo_ativo').val('Não').change();
	$('#combo_qtd_sessoes').val('');
	$('#combo_validade_dias').val('');
	$('#foto').val('');
	$('#target').attr('src','img/servicos/sem-foto.jpg');

	if(typeof mostrarCamposCombo === 'function'){
		mostrarCamposCombo();
	}
}

function mostrar(nome, categoria, descricao, valor, dias_retorno, comissao, tempo, foto, ativo, combo_ativo, combo_qtd_sessoes, combo_validade_dias){
	$('#nome_dados').text(nome);
	$('#categoria_dados').text(categoria);
	$('#valor_dados').text('R$ ' + valor);
	$('#dias_retorno_dados').text(dias_retorno);
	$('#ativo_dados').text(ativo);
	$('#comissao_dados').text(comissao);
	$('#combo_ativo_dados').text(combo_ativo);
	$('#combo_qtd_sessoes_dados').text(combo_qtd_sessoes);
	$('#combo_validade_dias_dados').text(combo_validade_dias + ' dias');
	$('#target_mostrar').attr('src', 'img/servicos/' + foto);
	$('#modalDados').modal('show');
}
</script>
