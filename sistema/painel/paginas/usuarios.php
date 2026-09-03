<?php
require_once __DIR__ . '/_context_bootstrap.php';
// Painel - Usuários
// IMPORTANTE: NÃO iniciar sessão aqui.
// A sessão/tenant deve ser decidida SOMENTE pelo conexao.php (bootstrap central).
require_once __DIR__ . '/../../conexao.php';

// Base real do painel, incluindo a subpasta do projeto no XAMPP.
// Ex.: /marketplace/sistema/painel
$bb_usuarios_painel_base = rtrim((string)$__bb_webBase, '/') . '/sistema/painel';
$bb_usuarios_ajax_arquivo = __DIR__ . '/../js/ajax.js';
$bb_usuarios_ajax_versao = is_file($bb_usuarios_ajax_arquivo)
	? (string)filemtime($bb_usuarios_ajax_arquivo)
	: (string)time();

//verificar se ele tem a permissão de estar nessa página
if(@$usuarios == 'ocultar'){
    echo '<script>window.location=' . json_encode($bb_usuarios_painel_base . '/') . '</script>';
    exit();
}

$pag = 'usuarios';
?>

<div class="">      
	<a class="btn btn-primary" onclick="inserir()" class="btn btn-primary btn-flat btn-pri"><i class="fa fa-plus" aria-hidden="true"></i> Novo Usuário</a>
</div>

<div class="bs-example widget-shadow" style="padding:15px" id="listar">
	
</div>






<!-- Modal Inserir-->
<div class="modal fade" id="modalForm" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title"><span id="titulo_inserir"></span></h4>
				<button id="btn-fechar" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
					<span aria-hidden="true" >&times;</span>
				</button>
			</div>
			<form id="form">
			<div class="modal-body">

					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label for="exampleInputEmail1">Nome</label>
								<input type="text" class="form-control" id="nome" name="nome" placeholder="Nome" required>    
							</div> 	
						</div>
						<div class="col-md-6">

							<div class="form-group">
								<label for="exampleInputEmail1">Email</label>
								<input type="email" class="form-control" id="email" name="email" placeholder="Email"  required>    
							</div> 	
						</div>
					</div>


					<div class="row">
						<div class="col-md-4">
							<div class="form-group">
								<label for="exampleInputEmail1">Telefone</label>
								<input type="text" class="form-control" id="telefone" name="telefone" placeholder="Telefone" >    
							</div> 	
						</div>
						<div class="col-md-4">
							
							<div class="form-group">
								<label for="exampleInputEmail1">CPF</label>
								<input type="text" class="form-control" id="cpf" name="cpf" placeholder="CPF" >    
							</div> 	
						</div>

						<div class="col-md-4">
							
							<div class="form-group">
								<label for="exampleInputEmail1">Nível</label>
								<select class="form-control sel2" id="cargo" name="cargo" style="width:100%;" > 

									<?php 
									$query = $pdo->query("SELECT * FROM cargos ORDER BY nome asc");
									$res = $query->fetchAll(PDO::FETCH_ASSOC);
									$total_reg = @count($res);
									if($total_reg > 0){
										for($i=0; $i < $total_reg; $i++){
										foreach ($res[$i] as $key => $value){}
										echo '<option value="'.$res[$i]['nome'].'">'.$res[$i]['nome'].'</option>';
										}
									}else{
											echo '<option value="0">Cadastre um Cargo</option>';
										}
									 ?>
									

								</select>   
							</div> 	
						</div>
					</div>

					

					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label for="exampleInputEmail1">Endereço</label>
								<input type="text" class="form-control" id="endereco" name="endereco" placeholder="Rua X Número 1 Bairro xxx" >    
							</div> 	
						</div>

						<div class="col-md-3">
							<div class="form-group">
								<label for="exampleInputEmail1">Atendimento</label>
								<select class="form-control" name="atendimento" id="atendimento">
									<option value="Sim">Sim</option>
									<option value="Não">Não</option>
								</select>  
							</div> 	
						</div>

						<div class="col-md-3">
							<div class="form-group">
								<label for="exampleInputEmail1">Visualizar</label>
								<select class="form-control" name="visualizar" id="visualizar">
									<option value="Sim">Todos Clientes</option>
									<option value="Não">Somente clientes cadastrados por ele</option>
								</select>  
							</div> 	
						</div>
						
					</div>





						<div class="row">
							<div class="col-md-8">						
								<div class="form-group"> 
									<label>Foto</label> 
									<input class="form-control" type="file" name="foto" onChange="carregarImg();" id="foto">
								</div>						
							</div>
							<div class="col-md-4">
								<div id="divImg">
									<img src="img/perfil/sem-foto.jpg"  width="80px" id="target">									
								</div>
							</div>

						</div>


					
						<input type="hidden" name="id" id="id">

					<br>
					<small><div id="mensagem" align="center"></div></small>
				</div>

				<div class="modal-footer">      
					<button type="submit" class="btn btn-primary">Salvar</button>
				</div>
			</form>

			
		</div>
	</div>
</div>





<!-- Modal Dados-->
<div class="modal fade" id="modalDados" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title" id="exampleModalLabel"><span id="nome_dados"></span></h4>
				<button id="btn-fechar-perfil" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
					<span aria-hidden="true" >&times;</span>
				</button>
			</div>
			
			<div class="modal-body">

				<div class="row" style="border-bottom: 1px solid #cac7c7;">
					<div class="col-md-8">							
						<span><b>Email: </b></span>
						<span id="email_dados"></span>							
					</div>
					<div class="col-md-4">							
						<span><b>Senha: </b></span>
						<span id="senha_dados"></span>
					</div>					

				</div>


				<div class="row" style="border-bottom: 1px solid #cac7c7;">
					<div class="col-md-6">							
						<span><b>CPF: </b></span>
						<span id="cpf_dados"></span>							
					</div>
					<div class="col-md-6">							
						<span><b>Telefone: </b></span>
						<span id="telefone_dados"></span>
					</div>					

				</div>




				<div class="row" style="border-bottom: 1px solid #cac7c7;">
					<div class="col-md-6">							
						<span><b>Nível: </b></span>
						<span id="nivel_dados"></span>							
					</div>
					<div class="col-md-6">							
						<span><b>Ativo: </b></span>
						<span id="ativo_dados"></span>
					</div>		
								

				</div>


				<div class="row" style="border-bottom: 1px solid #cac7c7;">
						
					<div class="col-md-6">							
						<span><b>Cadastro: </b></span>
						<span id="data_dados"></span>
					</div>	

						<div class="col-md-6">							
						<span><b>Atendimento: </b></span>
						<span id="atendimento_dados"></span>
					</div>				

				</div>






				<div class="row" style="border-bottom: 1px solid #cac7c7;">
					
					<div class="col-md-12">							
						<span><b>Endereço: </b></span>
						<span id="endereco_dados"></span>
					</div>					

				</div>


				<div class="row">
					<div class="col-md-12" align="center">		
						<img width="250px" id="target_mostrar">	
					</div>					
				</div>


			</div>

			
		</div>
	</div>
</div>








<!-- Modal -->
<div class="modal fade" id="modalPermissoes" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title">
					Usuário: <span id="nome-usuario"></span>

					<span style="position:absolute; right:35px">
						<input class="form-check-input" type="checkbox" id="input-todos" onchange="marcarTodos()">
						<label class="" >Marcar Todos</label>
					</span>

				</h4>

				
				<button id="btn-fechar-permissoes" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			
			<div class="modal-body">

				<div class="row" id="listar-permissoes">

				</div>

				<div class="row">	
					<div class="col-md-12">						
				
						
					</div>	
				</div>	

				<br>
				<input type="hidden" name="id" id="id-usuario"> 
				<small><div id="mensagem-permissao" align="center" class="mt-3"></div></small>		

				

				


			</div>	


			

		</div>
	</div>
</div>






<script type="text/javascript">
	var pag = "<?=$pag?>";
	window.pag = pag;
	window.bbBasePainel = <?= json_encode($bb_usuarios_painel_base, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?>;
</script>
<script src="<?= htmlspecialchars($bb_usuarios_painel_base, ENT_QUOTES, 'UTF-8') ?>/js/ajax.js?v=<?= rawurlencode($bb_usuarios_ajax_versao) ?>"></script>


<script type="text/javascript">
	$(document).ready(function() {
    $('.sel2').select2({
    	dropdownParent: $('#modalForm')
    });
});
</script>


<script type="text/javascript">
	function carregarImg() {
    var target = document.getElementById('target');
    var file = document.querySelector("#foto").files[0];
    
        var reader = new FileReader();

        reader.onloadend = function () {
            target.src = reader.result;
        };

        if (file) {
            reader.readAsDataURL(file);

        } else {
            target.src = "";
        }
    }
</script>

<script type="text/javascript">
	// Evita que clicar nos ícones (Editar / Dados / Permissões) mude a página (ex: ir para Home)
	$(document).on('click', 'a', function(e){
		var oc = this.getAttribute('onclick') || '';
		if (/^(editar|mostrar|permissao)\(/.test(oc)) {
			e.preventDefault();
			e.stopPropagation();
		}
	});

	// EDITAR (assinatura compatível com o listar.php padrão)
	function editar(id, nome, email, senha, nivel, telefone, cpf, endereco, atendimento, foto){
		$('#id').val(id || '');
		$('#nome').val(nome || '');
		$('#email').val(email || '');

		// por segurança, não preenche senha mascarada
		if (typeof senha === 'string' && senha !== '' && senha !== '*****' && senha !== '*******'){
			$('#senha').val(senha);
		} else {
			$('#senha').val('');
		}

		$('#cargo').val(nivel || '').change();
		$('#telefone').val(telefone || '');
		$('#cpf').val(cpf || '');

		$('#titulo_inserir').text('Editar Registro');
		$('#modalForm').modal('show');

		$('#foto').val('');
		if (foto) {
			$('#target').attr('src','img/perfil/' + foto);
		} else {
			$('#target').attr('src','img/perfil/sem-foto.jpg');
		}
	}

	// LIMPAR (usado no inserir() do js/ajax.js)
	function limparCampos(){
		$('#id').val('');
		$('#nome').val('');
		$('#email').val('');
		$('#senha').val('');
		$('#telefone').val('');
		$('#cpf').val('');
		$('#foto').val('');
		$('#target').attr('src','img/perfil/sem-foto.jpg');

		// volta para o primeiro cargo (se existir)
		try {
			var first = $('#cargo option:first').val();
			if (first !== undefined) $('#cargo').val(first).change();
		} catch(e){}
	}

	// MOSTRAR DADOS (assinatura compatível com o listar.php padrão)
	function mostrar(nome, email, cpf, senha, nivel, data, ativo, telefone, endereco, atendimento, foto){
		$('#nome_dados').text(nome || '');
		$('#email_dados').text(email || '');
		$('#cpf_dados').text(cpf || '');
		$('#senha_dados').text(senha || '');
		$('#nivel_dados').text(nivel || '');
		$('#data_dados').text(data || '');
		$('#ativo_dados').text(ativo || '');
		$('#telefone_dados').text(telefone || '');
		$('#endereco_dados').text(endereco || '');
		$('#atendimento_dados').text(atendimento || '');

		$('#target_mostrar').attr('src','img/perfil/' + (foto || 'sem-foto.jpg'));
		$('#modalDados').modal('show');
	}

	// PERMISSÕES (abre modal e lista)
	function permissao(id, nome){
		$('#nome-usuario').text(nome || '');
		$('#id-usuario').val(id || '');
		$('#modalPermissoes').modal('show');
		if (id) {
			listarPermissoes(id);
		}
	}
</script>







<script type="text/javascript">
	function listarPermissoes(id){
		$.ajax({
			url: bb_url('paginas/' + pag + "/listar-permissoes.php"),
			method: 'POST',
			data: {id},
			dataType: "html",

			success:function(result){
				$("#listar-permissoes").html(result);
				$('#mensagem-permissao').text('');
				//$('#input-todos').prop('checked', false);
			}
		});
	}


	function marcarTodos(){
		let checkbox = document.getElementById('input-todos');
		var usuario = $('#id-usuario').val();
		
		if(checkbox.checked) {
		    adicionarPermissoes(usuario);		    
		} else {
		    limparPermissoes(usuario);
		}
	}



	function adicionarPermissoes(id){
		$.ajax({
			url: bb_url('paginas/' + pag + "/add-permissoes.php"),
			method: 'POST',
			data: {id},
			dataType: "html",

			success:function(result){
				listarPermissoes(id)
			}
		});	
	}


	function limparPermissoes(id){
		$.ajax({
			url: bb_url('paginas/' + pag + "/limpar-permissoes.php"),
			method: 'POST',
			data: {id},
			dataType: "html",

			success:function(result){
				listarPermissoes(id)
			}
		});	
	}


	function adicionarPermissao(idpermissao, idusuario){
		
		$.ajax({
			url: bb_url('paginas/' + pag + "/add-permissao.php"),
			method: 'POST',
			data: {idpermissao, idusuario},
			dataType: "html",

			success:function(result){
				listarPermissoes(idusuario)
			}
		});	
	}

</script>
