<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'usuarios';

if($tipo_comissao == 'Porcentagem'){
	$tipo_comissao = '%';
}

// Função mínima para não quebrar onclick quando tiver aspas
function esc_js($str){
	$str = (string)$str;
	return str_replace(
		["\\",   "'",   "\r",  "\n"],
		["\\\\","\\'", "\\r", "\\n"],
		$str
	);
}

$query = $pdo->query("SELECT * FROM $tabela where nivel != 'Administrador' ORDER BY id desc");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);

if($total_reg > 0){

echo <<<HTML
	<small>
	<table class="table table-hover" id="tabela">
	<thead> 
	<tr> 
	<th>Nome</th>	
	<th class="esc">Email</th> 	
	<th class="esc">CPF</th> 		
	<th class="esc">Cargo</th> 	
	<th class="esc">Cadastro</th>
	<th class="esc">Comissão <small>({$tipo_comissao})</small></th>	
	<th>Ações</th>
	</tr> 
	</thead> 
	<tbody>	
HTML;

for($i=0; $i < $total_reg; $i++){
	foreach ($res[$i] as $key => $value){}

	$id = $res[$i]['id'];
	$id_int = (int)$id;
	$nome = $res[$i]['nome'];
	$email = $res[$i]['email'];
	$cpf = $res[$i]['cpf'];
	$senha = $res[$i]['senha'];
	$nivel = $res[$i]['nivel'];
	$data = $res[$i]['data'];
	$ativo = $res[$i]['ativo'];
	$telefone = $res[$i]['telefone'];
	$endereco = $res[$i]['endereco'];
	$foto = $res[$i]['foto'];
	$atendimento = $res[$i]['atendimento'];
	$tipo_chave = $res[$i]['tipo_chave'];
	$chave_pix = $res[$i]['chave_pix'];
	$intervalo = $res[$i]['intervalo'];
	$comissao = $res[$i]['comissao'];
	$visualizar = $res[$i]['visualizar'];

	// ✅ NOVO (se a coluna não existir ainda, fica vazio)
	$token = $res[$i]['token'] ?? '';
	$instancia = $res[$i]['instancia'] ?? '';

	$data = is_null($data) ? '' : (string)$data;
	$dataF = $data !== '' ? implode('/', array_reverse(explode('-', $data))) : '';

	$senha = '*******';

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

	$whats = '55'.preg_replace('/[ ()-]+/' , '' , $telefone);

	if($tipo_comissao == '%'){
		$comissaoF = @number_format($comissao, 0, ',', '.').'%';
	}else{
		$comissaoF = 'R$ '.@number_format($comissao, 2, ',', '.');
	}

	if($comissao == ""){
		$comissaoF = "";
	}

	$nome_js = esc_js($nome);
	$email_js = esc_js($email);
	$telefone_js = esc_js($telefone);
	$cpf_js = esc_js($cpf);
	$nivel_js = esc_js($nivel);
	$endereco_js = esc_js($endereco);
	$foto_js = esc_js($foto);
	$atendimento_js = esc_js($atendimento);
	$tipo_chave_js = esc_js($tipo_chave);
	$chave_pix_js = esc_js($chave_pix);
	$intervalo_js = esc_js($intervalo);
	$comissao_js = esc_js($comissao);
	$visualizar_js = esc_js($visualizar);

	// ✅ NOVO
	$token_js = esc_js($token);
	$instancia_js = esc_js($instancia);

	$foto_html = htmlspecialchars((string)$foto, ENT_QUOTES, 'UTF-8');

echo <<<HTML
<tr class="{$classe_linha}">
<td>
<img src="img/perfil/{$foto_html}" width="27px" class="mr-2">
{$nome}
</td>
<td class="esc">{$email}</td>
<td class="esc">{$cpf}</td>
<td class="esc">{$nivel}</td>
<td class="esc">{$dataF}</td>
<td class="esc">{$comissaoF}</td>
<td>

	<big><a href="javascript:void(0)" onclick="editar('{$id}','{$nome_js}', '{$email_js}', '{$telefone_js}', '{$cpf_js}', '{$nivel_js}', '{$endereco_js}', '{$foto_js}', '{$atendimento_js}', '{$tipo_chave_js}', '{$chave_pix_js}', '{$intervalo_js}', '{$comissao_js}', '{$visualizar_js}', '{$token_js}', '{$instancia_js}'); return false;" title="Editar Dados"><i class="fa fa-edit text-primary"></i></a></big>

	<big><a href="javascript:void(0)" onclick="mostrar('{$nome_js}', '{$email_js}', '{$cpf_js}', '{$senha}', '{$nivel_js}', '{$dataF}', '{$ativo}', '{$telefone_js}', '{$endereco_js}', '{$foto_js}', '{$atendimento_js}', '{$tipo_chave_js}', '{$chave_pix_js}', '{$token_js}', '{$instancia_js}'); return false;" title="Ver Dados"><i class="fa fa-info-circle text-secondary"></i></a></big>

	<li class="dropdown head-dpdn2" style="display: inline-block;">
	<a href="javascript:void(0)" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><big><i class="fa fa-trash-o text-danger"></i></big></a>

	<ul class="dropdown-menu" style="margin-left:-230px;">
	<li>
	<div class="notification_desc2">
	<p>Confirmar Exclusão? <a href="javascript:void(0)" onclick="excluir({$id_int}); return false;"><span class="text-danger">Sim</span></a></p>
	</div>
	</li>										
	</ul>
	</li>

	<big><a href="javascript:void(0)" onclick="ativar('{$id}', '{$acao}'); return false;" title="{$titulo_link}"><i class="fa {$icone} text-success"></i></a></big>

	<a href="javascript:void(0)" onclick="dias('{$id}', '{$nome_js}'); return false;" title="Ver Dias"><i class="fa fa-calendar text-danger"></i></a>

	<big><a href="http://api.whatsapp.com/send?1=pt_BR&phone=$whats&text=" target="_blank" title="Abrir Whatsapp"><i class="fa fa-whatsapp verde"></i></a></big>

	<a href="javascript:void(0)" onclick="servico('{$id}', '{$nome_js}'); return false;" title="Definir Serviços"><i class="fa fa-briefcase" style="color:#a60f4b"></i></a>

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
	$(document).ready( function () {
		$('#tabela').DataTable({
			"ordering": false,
			"stateSave": true
		});
		$('#tabela_filter label input').focus();
	} );
</script>

<script type="text/javascript">
	function editar(id, nome, email, telefone, cpf, nivel, endereco, foto, atendimento, tipo_chave, chave_pix, intervalo, comissao, visualizar, token, instancia){
		$('#id').val(id);
		$('#nome').val(nome);
		$('#email').val(email);
		$('#telefone').val(telefone);
		$('#cpf').val(cpf);
		$('#cargo').val(nivel).change();
		$('#endereco').val(endereco);
		$('#atendimento').val(atendimento).change();
		$('#chave_pix').val(chave_pix);
		$('#tipo_chave').val(tipo_chave).change();
		$('#intervalo').val(intervalo);
		$('#comissao').val(comissao);
		$('#visualizar').val(visualizar).change();

		// ✅ NOVO
		$('#token').val(token);
		$('#instancia').val(instancia);

		$('#titulo_inserir').text('Editar Registro');
		$('#modalForm').modal('show');
		$('#foto').val('');
		$('#target').attr('src','img/perfil/' + foto);
	}

	function limparCampos(){
		$('#id').val('');
		$('#nome').val('');
		$('#telefone').val('');
		$('#email').val('');
		$('#cpf').val('');
		$('#endereco').val('');
		$('#foto').val('');
		$('#chave_pix').val('');
		$('#target').attr('src','img/perfil/sem-foto.jpg');
		$('#intervalo').val('');
		$('#comissao').val('');
		$('#visualizar').val('Sim').change();

		// ✅ NOVO
		$('#token').val('');
		$('#instancia').val('');
	}
</script>

<script type="text/javascript">
	function mostrar(nome, email, cpf, senha, nivel, data, ativo, telefone, endereco, foto, atendimento, tipo_chave, chave_pix, token, instancia){

		$('#nome_dados').text(nome);
		$('#email_dados').text(email);
		$('#cpf_dados').text(cpf);
		$('#senha_dados').text(senha);
		$('#nivel_dados').text(nivel);
		$('#data_dados').text(data);
		$('#ativo_dados').text(ativo);
		$('#telefone_dados').text(telefone);
		$('#endereco_dados').text(endereco);
		$('#atendimento_dados').text(atendimento);
		$('#tipo_chave_dados').text(tipo_chave);
		$('#chave_pix_dados').text(chave_pix);

		// ✅ NOVO
		$('#token_dados').text(token);
		$('#instancia_dados').text(instancia);

		$('#target_mostrar').attr('src','img/perfil/' + foto);

		$('#modalDados').modal('show');
	}
</script>

<script type="text/javascript">
	function dias(id, nome){
		$('#nome_dias').text(nome);
		$('#id_dias').val(id);
		$('#modalDias').modal('show');
		listarDias(id);
	}
</script>

<script type="text/javascript">
	function servico(id, nome){
		$('#nome_servico').text(nome);
		$('#id_servico').val(id);
		$('#modalServicos').modal('show');
		listarServicos(id);
	}
</script>