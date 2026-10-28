<?php 
require_once __DIR__ . '/_context_bootstrap.php';
require_once(__DIR__ . '/../verificar.php');     // /sistema/painel/verificar.php
require_once(__DIR__ . '/../../conexao.php');    // /sistema/conexao.php


$pag = 'servicos';

if(isset($tipo_comissao) && $tipo_comissao == 'Porcentagem'){
	$item_comissao = '(%)';
}else{
	$item_comissao = '(R$)';
}

//verificar se ele tem a permissão de estar nessa página
if(@$servicos == 'ocultar'){
	$urlPainel = isset($bb_painel_public_url) ? (string)$bb_painel_public_url : './';
	echo "<script>window.location='".htmlspecialchars($urlPainel, ENT_QUOTES, 'UTF-8')."'</script>";
	exit();
}

/* ====== tenant (se existir no seu sistema) ======
   Correção pontual:
   - não confia somente no slug;
   - não acessa índice inexistente de array/sessão;
   - só aplica filtro quando existe coluna de tenant E existe valor válido;
   - aceita tenant numérico (id_loja/id_empresa) e textual (slug/loja).
*/
if(!function_exists('bb_servicos_col_info')){
	function bb_servicos_col_info(PDO $pdo, string $tabela, string $coluna): ?array {
		try{
			$sql = "SELECT DATA_TYPE
					FROM INFORMATION_SCHEMA.COLUMNS
					WHERE TABLE_SCHEMA = DATABASE()
					  AND TABLE_NAME = :tabela
					  AND COLUMN_NAME = :coluna
					LIMIT 1";
			$stmt = $pdo->prepare($sql);
			$stmt->execute([
				':tabela' => $tabela,
				':coluna' => $coluna
			]);
			$linha = $stmt->fetch(PDO::FETCH_ASSOC);
			return $linha ?: null;
		}catch(Throwable $e){
			return null;
		}
	}
}

if(!function_exists('bb_servicos_col_exists')){
	function bb_servicos_col_exists(PDO $pdo, string $tabela, string $coluna): bool {
		return bb_servicos_col_info($pdo, $tabela, $coluna) !== null;
	}
}

if(!function_exists('bb_servicos_first_existing_column')){
	function bb_servicos_first_existing_column(PDO $pdo, string $tabela, array $candidatas): string {
		foreach($candidatas as $coluna){
			if(bb_servicos_col_exists($pdo, $tabela, $coluna)){
				return $coluna;
			}
		}
		return '';
	}
}

if(!function_exists('bb_servicos_is_numeric_type')){
	function bb_servicos_is_numeric_type(string $tipo): bool {
		$tipo = strtolower($tipo);
		return in_array($tipo, ['int','integer','tinyint','smallint','mediumint','bigint','decimal','numeric','float','double'], true);
	}
}

if(!function_exists('bb_servicos_scalar')){
	function bb_servicos_scalar($valor): string {
		if(is_array($valor) || is_object($valor) || $valor === null){
			return '';
		}
		return trim((string)$valor);
	}
}

if(!function_exists('bb_servicos_tenant_value')){
	function bb_servicos_tenant_value(PDO $pdo, string $tabela, string $coluna) {
		if($coluna === ''){
			return null;
		}

		$info = bb_servicos_col_info($pdo, $tabela, $coluna);
		$tipo = $info ? (string)$info['DATA_TYPE'] : '';
		$numerico = bb_servicos_is_numeric_type($tipo);

		// Preferência: contexto formal do tenant, quando o projeto disponibilizar.
		if(function_exists('bb_tenant_ctx') && function_exists('bb_table_tenant_filter')){
			try{
				$ctx = bb_tenant_ctx();
				$filtro = bb_table_tenant_filter($pdo, $tabela, $ctx);
				if(!empty($filtro['col']) && $filtro['col'] === $coluna){
					$valor = $filtro['val'] ?? null;
					$valorLimpo = bb_servicos_scalar($valor);
					if($valorLimpo !== '' && $valorLimpo !== '0'){
						return $numerico ? (int)$valorLimpo : $valorLimpo;
					}
				}
			}catch(Throwable $e){
				// segue para fallback abaixo
			}
		}

		$fontes = [$_SESSION ?? [], $_GET ?? [], $_POST ?? []];

		$chavesNumericas = ['tenant_id','id_loja','loja_id','id_empresa','empresa_id','id_usuario','usuario_id','empresa','usuario'];
		$chavesTexto = ['tenant_slug','slug','loja','empresa','tenant'];

		if($numerico){
			foreach($fontes as $fonte){
				foreach($chavesNumericas as $chave){
					$valor = bb_servicos_scalar($fonte[$chave] ?? '');
					if($valor !== '' && ctype_digit($valor) && (int)$valor > 0){
						return (int)$valor;
					}
				}
			}
			return null;
		}

		foreach($fontes as $fonte){
			foreach($chavesTexto as $chave){
				$valor = bb_servicos_scalar($fonte[$chave] ?? '');
				if($valor !== ''){
					return preg_replace('/[^a-zA-Z0-9_-]/', '', $valor);
				}
			}
		}

		return null;
	}
}

$tenantCandidates = ['tenant_id','tenant_slug','slug','id_loja','loja','id_empresa','empresa','id_usuario','usuario'];
$tenantColCat = bb_servicos_first_existing_column($pdo, 'cat_servicos', $tenantCandidates);
$tenantValCat = bb_servicos_tenant_value($pdo, 'cat_servicos', $tenantColCat);
?>

<div class="">      
	<a class="btn btn-primary btn-flat btn-pri" href="javascript:void(0)" onclick="inserir(); return false;">
		<i class="fa fa-plus" aria-hidden="true"></i> Novo Serviço
	</a>
</div>

<div class="bs-example widget-shadow" style="padding:15px" id="listar"></div>

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
								<label>Nome</label>
								<input type="text" class="form-control" id="nome" name="nome" placeholder="Nome" required>    
							</div> 	
						</div>

						<div class="col-md-6">
							<div class="form-group">
								<label>Valor</label>
								<input type="text" class="form-control" id="valor" name="valor" placeholder="Valor" onkeyup="mascara_valor('valor')">    
							</div> 	
						</div>
					</div>

					<div class="row">					
						<div class="col-md-6">
							<div class="form-group">
								<label>Categoria</label>
								<select class="form-control sel2" id="categoria" name="categoria" style="width:100%;"> 
									<?php
									try{
										$sqlCat = "SELECT id, nome FROM cat_servicos";
										$paramsCat = [];

										if($tenantColCat !== '' && $tenantValCat !== null){
											$sqlCat .= " WHERE {$tenantColCat} = :tenant";
											$paramsCat[':tenant'] = $tenantValCat;
										}

										$sqlCat .= " ORDER BY id asc";

										$stmtCat = $pdo->prepare($sqlCat);
										foreach($paramsCat as $param => $valorParam){
											$stmtCat->bindValue($param, $valorParam, is_int($valorParam) ? PDO::PARAM_INT : PDO::PARAM_STR);
										}
										$stmtCat->execute();

										$resCat = $stmtCat->fetchAll(PDO::FETCH_ASSOC);

										if(count($resCat) > 0){
											foreach($resCat as $cat){
												$idCat = isset($cat['id']) ? (int)$cat['id'] : 0;
												$nomeCat = htmlspecialchars((string)($cat['nome'] ?? ''), ENT_QUOTES, 'UTF-8');
												if($idCat > 0){
													echo '<option value="'.$idCat.'">'.$nomeCat.'</option>';
												}
											}
										}else{
											echo '<option value="0">Cadastre uma Categoria</option>';
										}
									}catch(Throwable $e){
										echo '<option value="0">Erro ao carregar categorias</option>';
									}
									?>
								</select>   
							</div> 	
						</div>

						<div class="col-md-6">
							<div class="form-group">
								<label>Dias Retorno</label>
								<input type="number" class="form-control" id="dias_retorno" name="dias_retorno" placeholder="Dias Retorno">    
							</div> 	
						</div>
					</div>

					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label>Comissão <?php echo $item_comissao ?></label>
								<input type="text" class="form-control" id="comissao" name="comissao" placeholder="Valor Comissão">    
							</div> 	
						</div>

						<div class="col-md-6">
							<div class="form-group">
								<label>Tempo Estimado</label>
								<input type="number" class="form-control" id="tempo" name="tempo" placeholder="Minutos">    
							</div> 	
						</div>
					</div>

					<div class="row">
						<div class="col-md-4">
							<div class="form-group">
								<label>É Combo</label>
								<select class="form-control" id="combo_ativo" name="combo_ativo" onchange="mostrarCamposCombo()">
									<option value="Não">Não</option>
									<option value="Sim">Sim</option>
								</select>
							</div>
						</div>

						<div class="col-md-4 campos-combo" style="display:none;">
							<div class="form-group">
								<label>Qtd Sessões</label>
								<input type="number" class="form-control" id="combo_qtd_sessoes" name="combo_qtd_sessoes" placeholder="Sessões">
							</div>
						</div>

						<div class="col-md-4 campos-combo" style="display:none;">
							<div class="form-group">
								<label>Validade em Dias</label>
								<input type="number" class="form-control" id="combo_validade_dias" name="combo_validade_dias" placeholder="Validade">
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
								<img src="img/servicos/sem-foto.jpg" width="80px" id="target">									
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
						<span><b>Categoria: </b></span>
						<span id="categoria_dados"></span>							
					</div>
					<div class="col-md-4">							
						<span><b>Valor: </b></span>
						<span id="valor_dados"></span>
					</div>					
				</div>

				<div class="row" style="border-bottom: 1px solid #cac7c7;">
					<div class="col-md-6">							
						<span><b>Dias Retorno: </b></span>
						<span id="dias_retorno_dados"></span>							
					</div>

					<div class="col-md-6">							
						<span><b>Ativo: </b></span>
						<span id="ativo_dados"></span>							
					</div>
				</div>

				<div class="row" style="border-bottom: 1px solid #cac7c7;">
					<div class="col-md-6">							
						<span><b>Comissão: </b></span>
						<span id="comissao_dados"></span>							
					</div>
					<div class="col-md-6">							
						<span><b>É Combo: </b></span>
						<span id="combo_ativo_dados"></span>							
					</div>
				</div>

				<div class="row" style="border-bottom: 1px solid #cac7c7;">
					<div class="col-md-6">							
						<span><b>Qtd Sessões: </b></span>
						<span id="combo_qtd_sessoes_dados"></span>							
					</div>
					<div class="col-md-6">							
						<span><b>Validade Combo: </b></span>
						<span id="combo_validade_dias_dados"></span>							
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

<script type="text/javascript">
	var pag = "<?=$pag?>";
	window.pag = pag;

	// IMPORTANTE: ajax.js usa listar()
	function listar(){
		$.ajax({
			url: 'paginas/' + pag + '/listar.php',
			method: 'POST',
			dataType: 'html',
			success: function(result){
				$('#listar').html(result);
			}
		});
	}

	$(document).ready(function(){
		listar();
	});

	// Evita navegação indesejada para Home em links de ação (ex: href="#")
	$(document).on('click', '#listar a', function(e){
		var href = $(this).attr('href');
		if(!href || href === '#' || $.trim(href) === ''){
			// se for link de ação/ícone, apenas bloqueia o padrão do navegador
			if($(this).attr('onclick') || $(this).hasClass('dropdown-toggle') || $(this).find('i.fa').length){
				e.preventDefault();
			}
		}
	});
</script>

<script src="js/ajax.js"></script>

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
	function mostrarCamposCombo(){
		var combo = $('#combo_ativo').val();

		if(combo == 'Sim'){
			$('.campos-combo').show();
		}else{
			$('.campos-combo').hide();
			$('#combo_qtd_sessoes').val('');
			$('#combo_validade_dias').val('');
		}
	}

	$(document).ready(function(){
		mostrarCamposCombo();
		$('#modalForm').on('shown.bs.modal', function(){
			mostrarCamposCombo();
		});
	});
</script>
