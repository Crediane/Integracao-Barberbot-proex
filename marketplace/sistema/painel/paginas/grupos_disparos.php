<?php 
require_once __DIR__ . '/_context_bootstrap.php';
$pag = 'grupos_disparos';

// Verifica se tem permissão para acessar a página
if(@$grupos_disparos == 'ocultar'){
	echo "<script>window.location='index.php'</script>";
	exit();
}
?>

<div class="breadcrumb-header justify-content-between">
	<div class="left-content mt-2">
		<a class="btn btn-primary text-white" onclick="inserir()" type="button">
			<i class="fa fa-plus"></i> Adicionar Grupo Disparos
		</a>

		<!-- BOTÃO EXCLUIR SELEÇÃO -->
		<div class="dropdown" style="display: inline-block;">                      
			<a href="#" aria-expanded="false" aria-haspopup="true" data-toggle="dropdown" class="btn btn-danger dropdown-toggle" id="btn-deletar" style="display:none">
				<i class="fa fa-trash"></i> Deletar
			</a>
			<ul class="dropdown-menu">
				<li style="padding:15px 5px 0 10px;">
					<p>
						Excluir Selecionados? 
						<a href="#" onclick="deletarSel(); return false;">
							<span class="text-danger"><button class="btn btn-danger btn-xs" type="button">Sim</button></span>
						</a>
					</p>
				</li>
			</ul>
		</div>
	</div>
</div>

<div class="row row-sm">
	<div class="col-lg-12">
		<div class="panel panel-default">
			<div class="panel-body" id="listar">
				<!-- Conteúdo dinâmico da listagem -->
			</div>
		</div>
	</div>
</div>

<input type="hidden" id="ids">

<!-- Modal Form -->
<div class="modal fade" id="modalForm" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel">
	<div class="modal-dialog">
		<div class="modal-content">
			<div class="modal-header bg-primary text-white" style="background:#007bff; color:#fff;">
				<button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
					<span aria-hidden="true">&times;</span>
				</button>
				<h4 class="modal-title" id="exampleModalLabel"><span id="titulo_inserir"></span></h4>
			</div>

			<form id="form">
				<div class="modal-body">
					<div class="row">
						<div class="col-md-8">
							<label>Nome do Grupo</label>
							<input type="text" class="form-control" id="nome" name="nome" placeholder="Digite o nome do grupo" required>					
						</div>

						<div class="col-md-3" style="margin-top: 24px;">      
							<button type="submit" id="btn_salvar" class="btn btn-primary">
								Salvar <i class="fa fa-check"></i>
							</button>
						</div>
					</div>

					<input type="hidden" class="form-control" id="id" name="id">					
					<br>
					<small><div id="mensagem" align="center"></div></small>
				</div>
			</form>

		</div>
	</div>
</div>

<!-- Modal Add Cliente -->
<div class="modal fade" id="modalAdd" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel">
	<div class="modal-dialog modal-lg">
		<div class="modal-content">
			<div class="modal-header bg-primary text-white" style="background:#007bff; color:#fff;">
				<button type="button" class="close" data-dismiss="modal" aria-label="Fechar">
					<span aria-hidden="true">&times;</span>
				</button>
				<h4 class="modal-title" id="exampleModalLabel"><span id="titulo_add"></span></h4>
			</div>

			<div class="modal-body">
				<div class="row">
					<div class="col-md-7">
						<div id="listar_clientes"></div>
					</div>

					<div class="col-md-5">
						<br>
						<div id="listar_clientes_grupos" style="margin-top: 45px"></div>
					</div>
				</div>

				<input type="hidden" id="id_add">
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
var pag = "grupos_disparos";

/* ==========================================================
   IMPORTANTE (Correção do EDITAR)
   ==========================================================
   A listagem (listar.php) é carregada via AJAX em #listar.
   Em muitos navegadores, scripts inseridos por .html() não são executados.
   Por isso, as funções usadas pelos botões (editar/cliente/selecionar/deletarSel)
   precisam existir AQUI (na página principal), garantindo funcionamento
   para dono do sistema e dono do estabelecimento (banco individual via conexao.php).
*/

// Listar ao carregar
$(document).ready(function () {
	listar();

	// Submissão do formulário do modal (Salvar grupo)
	$("#form").submit(function (e) {
		e.preventDefault();
		var formData = new FormData(this);

		$.ajax({
			url: 'paginas/' + pag + "/salvar.php",
			type: 'POST',
			data: formData,
			success: function (mensagem) {
				$('#mensagem').text('');
				$('#mensagem').removeClass();

				if (mensagem.trim() == "Salvo com Sucesso") {
					$('#modalForm').modal('hide');
					listar();
				} else {
					$('#mensagem').addClass('text-danger');
					$('#mensagem').text(mensagem);
				}
			},
			cache: false,
			contentType: false,
			processData: false
		});
	});
});

// Função para listar
function listar() {
	$.ajax({
		url: 'paginas/' + pag + "/listar.php",
		method: 'POST',
		data: {},
		dataType: "html",
		success: function (result) {
			$("#listar").html(result);
			iniciarDataTable();
		}
	});
}

// DataTable: inicializa aqui (pois script do listar.php pode não executar)
function iniciarDataTable() {
	try {
		if ($.fn.DataTable) {
			if ($.fn.dataTable.isDataTable('#tabela')) {
				$('#tabela').DataTable().destroy();
			}
			$('#tabela').DataTable({
				"language": {
					// "url": "//cdn.datatables.net/plug-ins/1.13.2/i18n/pt-BR.json"
				},
				"ordering": false,
				"stateSave": true
			});
		}
	} catch (e) {
		// Mantém o padrão do sistema: se falhar, apenas não aplica datatable
	}
}

function inserir() {
	$('#mensagem').text('');
	$('#titulo_inserir').text('Inserir Registro');
	$('#id').val('');
	$('#nome').val('');
	$('#modalForm').modal('show');
}

// ✅ CORREÇÃO: EDITAR agora existe na página principal
function editar(id, nome) {
	$('#mensagem').text('');
	$('#titulo_inserir').text('Editar Registro');
	$('#id').val(id);
	$('#nome').val(nome);
	$('#modalForm').modal('show');
	return false;
}

// ✅ CORREÇÃO: CLIENTE agora existe na página principal
function cliente(id, nome) {
	$('#titulo_add').text(nome);
	$('#id_add').val(id);
	listarClientes();
	listarClientesGrupos();
	$('#modalAdd').modal('show');
	return false;
}

function listarClientes() {
	var id_grupo = $('#id_add').val();
	$.ajax({
		url: 'paginas/' + pag + "/listar_clientes.php",
		method: 'POST',
		data: { id_grupo: id_grupo },
		dataType: "html",
		success: function (result) {
			$("#listar_clientes").html(result);
		}
	});
}

function listarClientesGrupos() {
	var id_grupo = $('#id_add').val();
	$.ajax({
		url: 'paginas/' + pag + "/listar_clientes_grupos.php",
		method: 'POST',
		data: { id_grupo: id_grupo },
		dataType: "html",
		success: function (result) {
			$("#listar_clientes_grupos").html(result);
		}
	});
}

function limparCampos() {
	$('#id').val('');
	$('#nome').val('');
	$('#ids').val('');
	$('#btn-deletar').hide();
}

function selecionar(id) {
	var ids = $('#ids').val();

	if ($('#seletor-' + id).is(":checked")) {
		var novo_id = ids + id + '-';
		$('#ids').val(novo_id);
	} else {
		var retirar = ids.replace(id + '-', '');
		$('#ids').val(retirar);
	}

	var ids_final = $('#ids').val();
	if (ids_final == "") {
		$('#btn-deletar').hide();
	} else {
		$('#btn-deletar').show();
	}
}

function deletarSel() {
	var ids = $('#ids').val();
	var id = ids.split("-");

	for (i = 0; i < id.length - 1; i++) {
		excluirMultiplos(id[i]);
	}

	setTimeout(() => {
		listar();
	}, 1000);

	limparCampos();
}

// Excluir único (com confirmação) - regra preservada
function excluir(id) {
	if (confirm('Deseja realmente excluir este grupo?')) {
		$.ajax({
			url: 'paginas/' + pag + '/excluir.php',
			method: 'POST',
			data: { id: id },
			success: function (result) {
				if (result.trim() == 'Excluído com Sucesso') {
					listar();
				} else {
					alert(result);
				}
			}
		});
	}
	return false;
}

// Excluir múltiplos (sem confirmação individual) - usado pelo deletarSel
function excluirMultiplos(id) {
	$.ajax({
		url: 'paginas/' + pag + '/excluir.php',
		method: 'POST',
		data: { id: id },
		success: function (result) {
			// não altera padrão: apenas executa
		}
	});
}

// Ativar/Desativar (regra preservada)
function ativar(id, acao) {
	$.ajax({
		url: 'paginas/' + pag + '/mudar-status.php',
		method: 'POST',
		data: { id: id, acao: acao },
		success: function (result) {
			if (result.trim() == 'Alterado') {
				listar();
			} else {
				alert(result);
			}
		}
	});
	return false;
}
</script>
