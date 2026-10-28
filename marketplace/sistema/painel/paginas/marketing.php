<?php
require_once __DIR__ . '/_context_bootstrap.php';
// Guarda slug na sessão (ajuda includes/conexão a escolher o banco da loja)
if (!empty($__bb_reqSlug) && empty($_SESSION['loja_slug'])) {
  $_SESSION['loja_slug'] = $__bb_reqSlug;
}
require_once __DIR__ . '/../verificar.php';
require_once __DIR__ . '/../../conexao.php';

$pag = 'marketing';

//verificar se ele tem a permissão de estar nessa página
if(@$marketing == 'ocultar'){
    // Redireciona respeitando o contexto (MASTER x TENANT)
    // - Se estiver em /sistema com contexto de loja (via ?loja= ou ref), preserva o ?loja=
    $bb_redirect = '../index.php';
    $bb_reqHasLoja = (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath);

    if (!empty($__bb_reqSlug) && !$bb_reqHasLoja) {
        $bb_redirect .= '?loja=' . urlencode($__bb_reqSlug);
    }

    echo "<script>window.location='{$bb_redirect}'</script>";
    exit();
}
?>

<div class="justify-content-between breadcrumb-header">
	<div class="row">
		<div class="col-md-6">
			<div class="left-content mt-2">
				<a class="btn ripple btn-primary text-white" onclick="inserir()" type="button"><i class="fe fe-plus me-1"></i> Nova Campanha</a>
			</div>
		</div>
		<div class="col-md-6" align="right">
			<!-- Barra de Filtros e Pesquisa -->
	  <div style="display: flex; border: 1px solid #ccc; border-radius: 6px; overflow: hidden;">
	    <span style="background-color: #3b6eea; color: white; padding: 6px 10px; display: flex; align-items: center;">
	      <i class="fa fa-search"></i>
	    </span>
	    <input onkeyup="buscar()" type="text" id="pesquisar" placeholder="Buscar campanhas..." 
	      style="border: none; outline: none; padding: 6px 10px; min-width: 100%;">
	  </div>
		</div>
	</div>
</div>

<div class="bs-example widget-shadow" style="padding:15px" id="listar">
</div>
<!-- Aviso sobre o uso de disparos em massa (revitalizado) -->
<div class="alert mt-4 border-0 shadow-sm" style="background: #fff1e3">
    <div class="d-flex">
        <div class="me-3">
            <i class="fa fa-exclamation-triangle fa-2x text-warning"></i>
        </div>
        <div style="color:#525252">
            <h5 class="alert-heading">Atenção ao usar disparos em massa</h5>
            <p class="mb-0">O WhatsApp pode banir seu número se detectar envios automatizados em excesso. 
            O sistema realiza envios a cada 1 minuto para minimizar riscos, mas recomendamos:</p>
            
            <ul class="mt-2 mb-0" style="font-size: 12px; margin-left: 20px">
                <li>Enviar para grupos menores de contatos</li>
                <li>Evitar mensagens idênticas para muitos destinatários</li>
                <li>Não realizar disparos com muita frequência</li>
                <li>Personalizar mensagens quando possível</li>
            </ul>
			<p class="mt-2">Deixamos dois campos para mensagens: mensagem principal e secundária. O sistema irá alternar entre as duas para evitar bainimentos e altere algumas palavras entre as duas mensagens.</p>
        </div>
    </div>
</div>

<!-- Modal Inserir-->
<div class="modal fade" id="modalForm" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true" data-bs-backdrop="static">
	<div class="modal-dialog" role="document" style="max-width: 80vw; width: 80vw;">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title"><span id="titulo_inserir"></span></h4>
				<button id="btn-fechar" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
					<span aria-hidden="true" >&times;</span>
				</button>
			</div>
			<form id="form">
			<div class="modal-body">
<!-- Seção do Nome da Campanha -->
	<div class="p-4 border-bottom bg-light">
	<div class="form-group mb-0">
		<div class="input-group" style="width: 100%;">
			<div class="input-group-prepend">
				<span class="input-group-text bg-white border-right-0">
					<i class="fe fe-tag text-primary"></i>
				</span>
			</div>
			<label>Nome da Campanha</label>
			<input type="text" class="form-control border-left-0" id="titulo" name="titulo"
				placeholder="Nome da Campanha" required style="flex: 1 1 auto; min-width: 0;">
		</div>
	</div>
</div>
					<!-- Navegação por Abas -->
					<ul class="nav nav-tabs nav-fill" id="campanhaTab" role="tablist">
						<li class="nav-item">
							<a class="nav-link active py-3" id="mensagem-tab" data-toggle="tab" href="#mensagem-conteudo" role="tab"
								aria-controls="mensagem-conteudo" aria-selected="true">
								<i class="fa fa-comment mr-2"></i>Mensagem Principal
							</a>
						</li>
						<li class="nav-item">
							<a class="nav-link py-3" id="secundaria-tab" data-toggle="tab" href="#secundaria-conteudo" role="tab"
								aria-controls="secundaria-conteudo" aria-selected="false">
								<i class="fa fa-comment-alt mr-2"></i>Mensagem Secundária
							</a>
						</li>
						<li class="nav-item">
							<a class="nav-link py-3" id="anexos-tab" data-toggle="tab" href="#anexos-conteudo" role="tab"
								aria-controls="anexos-conteudo" aria-selected="false">
								<i class="fa fa-paperclip mr-2"></i>Anexos
							</a>
						</li>
					</ul>
					
<!-- Conteúdo das Abas -->
<div class="tab-content" id="campanhaTabContent">
	<!-- Aba da Mensagem Principal -->
	<div class="tab-pane fade show active" id="mensagem-conteudo" role="tabpanel" aria-labelledby="mensagem-tab">
		<div class="row p-3 mt-4">
			<!-- Campo de Mensagem -->
			<div class="col-md-6 mb-4">
				<div class="d-flex justify-content-between align-items-center mb-2">
					<span class="text-muted font-weight-bold">💬 Mensagem:</span>
					<div class="d-flex flex-wrap justify-content-end">
						<!-- Botões de Formatação -->
						<div class="btn-group btn-group-sm mr-2 mb-1" style="box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
							<button type="button" class="btn btn-light border" onclick="formatarTexto('bold', 'msg')" title="Negrito">
								<i class="fa fa-bold"></i>
							</button>
							<button type="button" class="btn btn-light border" onclick="formatarTexto('italic', 'msg')" title="Itálico">
								<i class="fa fa-italic"></i>
							</button>
							<button type="button" class="btn btn-light border" onclick="formatarTexto('strike', 'msg')" title="Tachado">
								<i class="fa fa-strikethrough"></i>
							</button>
							<button type="button" class="btn btn-light border" onclick="formatarTexto('mono', 'msg')" title="Monospace">
								<i class="fa fa-code"></i>
							</button>
						</div>
						<!-- Botões de Emojis -->
						<div class="btn-group btn-group-sm mb-1">
							<button type="button" class="btn btn-outline-success" onclick="inserirEmoji('👋', 'msg')" title="Olá">👋</button>
							<button type="button" class="btn btn-outline-success" onclick="inserirEmoji('😊', 'msg')" title="Sorriso">😊</button>
							<button type="button" class="btn btn-outline-success" onclick="inserirEmoji('👍', 'msg')" title="Like">👍</button>
							<button type="button" class="btn btn-outline-success" onclick="inserirEmoji('🔥', 'msg')" title="Fogo">🔥</button>
							<button type="button" class="btn btn-outline-success" onclick="inserirEmoji('✅', 'msg')" title="Check">✅</button>
							<button type="button" class="btn btn-outline-success" onclick="inserirEmoji('✔️', 'msg')" title="Check 2">✔️</button>
							<button type="button" class="btn btn-outline-success" onclick="inserirEmoji('🎯', 'msg')" title="Alvo">🎯</button>
						</div>
					</div>
				</div>

				<!-- Textarea com fundo -->
				<textarea class="form-control" id="msg" name="msg" rows="12"
					placeholder="Digite a mensagem"
					onblur="$('#msg2').val($('#msg').val()), atualizarPreview2()" 
					onclick="atualizarPreview2()"
					style="background-image: url('images/whatsapp.jpg'); background-size: cover; background-position: center; background-repeat: no-repeat; opacity: 0.95; height:350px"></textarea>
			</div>

			<!-- Pré-visualização -->
			<div class="col-md-6 mb-4">
				<div class="card h-100">
					<div class="card-header bg-light py-2">
						<span class="text-muted font-weight-bold">🔍 Pré-visualização</span>
					</div>
					<br>
					<div class="card-body p-3 bg-light" style="background-image: url('images/whatsapp.jpg'); height:auto; min-height: 350px; background-size: cover; background-position: center; background-repeat: no-repeat; opacity: 0.95;">
						<div id="preview" style="min-height: 50px; white-space: pre-wrap; font-weight: normal;"></div>
					</div>
				</div>
			</div>
		</div>
	</div>

<!-- Aba da Mensagem Secundária -->
<div class="tab-pane fade" id="secundaria-conteudo" role="tabpanel" aria-labelledby="secundaria-tab">
	<div class="row p-3 mt-4">
		<!-- Campo da Mensagem -->
		<div class="col-md-6 mb-4">
			<div class="d-flex justify-content-between align-items-center mb-2">
				<span class="text-muted font-weight-bold">💬 Mensagem Secundária:</span>
				<div class="d-flex flex-wrap justify-content-end">
					<!-- Botões de Formatação -->
					<div class="btn-group btn-group-sm mr-2 mb-1" style="box-shadow: 0 2px 4px rgba(0,0,0,0.05);">
						<button type="button" class="btn btn-light border" onclick="formatarTexto('bold', 'msg2')" title="Negrito">
							<i class="fa fa-bold"></i>
						</button>
						<button type="button" class="btn btn-light border" onclick="formatarTexto('italic', 'msg2')" title="Itálico">
							<i class="fa fa-italic"></i>
						</button>
						<button type="button" class="btn btn-light border" onclick="formatarTexto('strike', 'msg2')" title="Tachado">
							<i class="fa fa-strikethrough"></i>
						</button>
						<button type="button" class="btn btn-light border" onclick="formatarTexto('mono', 'msg2')" title="Monospace">
							<i class="fa fa-code"></i>
						</button>
					</div>
					<!-- Emojis -->
					<div class="btn-group btn-group-sm mb-1">
						<button type="button" class="btn btn-outline-success" onclick="inserirEmoji('👋', 'msg2')" title="Olá">👋</button>
						<button type="button" class="btn btn-outline-success" onclick="inserirEmoji('😊', 'msg2')" title="Sorriso">😊</button>
						<button type="button" class="btn btn-outline-success" onclick="inserirEmoji('👍', 'msg2')" title="Like">👍</button>
						<button type="button" class="btn btn-outline-success" onclick="inserirEmoji('🔥', 'msg2')" title="Fogo">🔥</button>
						<button type="button" class="btn btn-outline-success" onclick="inserirEmoji('✅', 'msg2')" title="Check">✅</button>
						<button type="button" class="btn btn-outline-success" onclick="inserirEmoji('✔️', 'msg2')" title="Check 2">✔️</button>
						<button type="button" class="btn btn-outline-success" onclick="inserirEmoji('🎯', 'msg2')" title="Alvo">🎯</button>
					</div>
				</div>
			</div>

			<!-- Textarea -->
			<textarea class="form-control" id="msg2" name="msg2" rows="12"
				placeholder="Digite a mensagem"
				style="background-image: url('images/whatsapp.jpg'); background-size: cover; background-position: center; background-repeat: no-repeat; opacity: 0.95; height:350px"></textarea>
		</div>

		<!-- Preview -->
		<div class="col-md-6 mb-4">
			<div class="card h-100">
				<div class="card-header bg-light py-2">
					<span class="text-muted font-weight-bold">🔍 Pré-visualização</span>
				</div>
				<br>
				<div class="card-body p-3 bg-light" style="background-image: url('images/whatsapp.jpg'); background-size: cover; background-position: center; background-repeat: no-repeat; opacity: 0.95; height:auto; min-height:350px">
					<div id="preview2" style="min-height: 50px; white-space: pre-wrap; font-weight: normal;"></div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Aba dos Anexos -->
<div class="tab-pane fade" id="anexos-conteudo" role="tabpanel" aria-labelledby="anexos-tab">
	<div class="p-4">
		<div class="row">
			<!-- Imagem -->
			<div class="col-md-4 mb-4">
				<div class="card h-100" style="background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px;">
					<div class="card-header bg-white border-0" style="padding: 12px 16px;">
						<h6 class="mb-0 text-primary" style="font-size: 14px;">
							<i class="bi bi-image me-2"></i> Enviar Imagem Promocional
						</h6>
					</div>
					<div class="card-body text-center" style="padding: 16px;">
						<div class="mb-3 anexo-preview">
							<img src="images/marketing/sem-foto.png" class="img-fluid rounded" id="target" style="max-height: 150px; width: auto;">
						</div>
						<div style="display: inline-block; width: 100%;">
							<input type="file" class="form-control d-inline-block" id="foto" name="foto" accept="image/*" onchange="carregarImg();" style="width: calc(100% - 45px); display: inline-block; font-size: 12px;">
							<button class="btn btn-danger d-inline-block" type="button" onclick="limparImagem()" style="width: 40px;">
								<i class="bi bi-x-lg" style="font-size: 14px;"></i>
							</button>
						</div>
						<small class="form-text text-muted mt-2" style="font-size: 12px;">
							Formatos aceitos: PNG ou JPG • Máx. 2MB
						</small>
					</div>
				</div>
			</div>

			<!-- Áudio -->
			<div class="col-md-4 mb-4">
				<div class="card h-100" style="background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px;">
					<div class="card-header bg-white border-0" style="padding: 12px 16px;">
						<h6 class="mb-0 text-primary" style="font-size: 14px;">
							<i class="bi bi-music-note-beamed me-2"></i> Adicionar Áudio da Campanha
						</h6>
					</div>
					<div class="card-body text-center d-flex flex-column" style="padding: 16px;">
						<div class="flex-grow-1 d-flex align-items-center justify-content-center mb-3">
							<div id="audio-preview">
								<i class="bi bi-file-earmark-music display-4 text-muted"></i>
								<p class="text-muted mb-0" id="audio-info" style="font-size: 12px;">Nenhum arquivo selecionado</p>
							</div>
						</div>
						<div style="display: inline-block; width: 100%;" class="mt-auto">
							<input type="file" class="form-control d-inline-block" id="audio" name="audio" accept="audio/*" onchange="atualizarInfoAudio();" style="width: calc(100% - 45px); display: inline-block; font-size: 12px;">
							<button class="btn btn-danger d-inline-block" type="button" onclick="limparAudio()" style="width: 40px;">
								<i class="bi bi-x-lg" style="font-size: 14px;"></i>
							</button>
						</div>
						<small class="form-text text-muted mt-2" style="font-size: 12px;">
							Formatos: OGG ou MP3 • Máx. 5MB
						</small>
					</div>
				</div>
			</div>

			<!-- Documento -->
			<div class="col-md-4 mb-4">
				<div class="card h-100" style="background-color: #f8f9fa; border: 1px solid #dee2e6; border-radius: 8px;">
					<div class="card-header bg-white border-0" style="padding: 12px 16px;">
						<h6 class="mb-0 text-primary" style="font-size: 14px;">
							<i class="bi bi-file-earmark-pdf me-2"></i> Anexar Documento Informativo
						</h6>
					</div>
					<div class="card-body text-center d-flex flex-column" style="padding: 16px;">
						<div class="flex-grow-1 d-flex align-items-center justify-content-center mb-3">
							<img src="images/marketing/sem-foto.png" class="img-fluid" id="target_documento" style="max-height: 150px; width: auto;">
						</div>
						<div style="display: inline-block; width: 100%;" class="mt-auto">
							<input type="file" class="form-control d-inline-block" id="documento" name="documento" accept=".pdf,.zip,.rar" onchange="carregarImgArquivo();" style="width: calc(100% - 45px); display: inline-block; font-size: 12px;">
							<button class="btn btn-danger d-inline-block" type="button" onclick="limparDocumento()" style="width: 40px;">
								<i class="bi bi-x-lg" style="font-size: 14px;"></i>
							</button>
						</div>
						<small class="form-text text-muted mt-2" style="font-size: 12px;">
							Formatos: PDF, ZIP, RAR • Máx. 10MB
						</small>
					</div>
				</div>
			</div>

		</div>
	</div>
</div>
</div>

					<div class="alert alert-dismissible fade show m-3" role="alert" id="mensagem-form" style="display: none;">
						<i class="bi bi-info-circle me-2"></i>
						<span id="texto-mensagem"></span>
						<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Fechar"></button>
					</div>
						<input type="hidden" name="id" id="id">
					<br>
					<small><div id="mensagem" align="center"></div></small>
				</div>
				<div class="modal-footer">      
					<button type="submit" id="btn_salvar" class="btn btn-primary"><i class="fe fe-check-circle me-2"></i>Salvar</button>
					<button class="btn btn-primary" type="button" id="btn_carregando" style="display: none;">
						<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Carregando...
					</button>
				</div>
			</form>
		</div>
	</div>
</div>

<!-- Modal Visualizar Detalhes -->
<div class="modal fade" id="modalDados" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel"
	aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title"><span id="nome_dados">Detalhes da Campanha</span></h4>
				<button id="btn-fechar-perfil" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
					<span aria-hidden="true" >&times;</span>
				</button>
			</div>

			<div class="modal-body">
				<div class="row" style="border-bottom: 1px solid #cac7c7;">
					<div class="col-md-6">
						<span><b>Criada Em: </b></span>
						<span id="data_dados"></span>
					</div>
					<div class="col-md-6">
						<span><b>Último Envio: </b></span>
						<span id="data_envio_dados"></span>
					</div>
				</div>
				<div class="row py-2" style="border-bottom: 1px solid #cac7c7;">
					<div class="col-md-6">
						<span><b>Total de Envios: </b></span>
						<span id="envios_dados" class="badge bg-success"></span>
					</div>
				</div>

				<!-- Demonstração da Mensagem -->
				<div class="mt-4" align="center">
					<!-- Demonstração das Mensagens -->
				<h5 class="text-center mb-3"><u>Pré-visualização das Mensagens</u></h5>


				<div class="row">
					<!-- Mensagem Principal -->
					<div class="col-md-6 mb-3">
						<div class="card h-100">
							<div class="card-header bg-light py-1">
								<h6 class="mb-0 text-muted">Mensagem Principal</h6>
							</div>
							<div class="card-body p-3"style="text-align: left; background-image: url('images/whatsapp.jpg'); background-size: cover; background-position: center; background-repeat: no-repeat; opacity: 0.95;">
								<div class="message-text">
										<span id="mensagem_dados"></span>
									</div>
							</div>
						</div>
					</div>

					<!-- Mensagem Secundária -->
					<div class="col-md-6 mb-3">
						<div class="card h-100">
							<div class="card-header bg-light py-1">
								<h6 class="mb-0 text-muted">Mensagem Secundária</h6>
							</div>
							<div class="card-body p-3" style="text-align: left; background-image: url('images/whatsapp.jpg'); background-size: cover; background-position: center; background-repeat: no-repeat; opacity: 0.95;">
								<div class="message-text">
										<span id="mensagem2_dados"></span>
									</div>
							</div>
						</div>
					</div>
				</div>
					<div class="message-preview"
						style="max-width:400px; border:1px solid #acacad; margin:0 auto; text-align: left; padding:15px; border-radius:10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">

						<div class="message-body">

							<!-- Arquivos anexados -->
							<div class="attachments">
								<div id="target_dados_div" class="mb-3">
									<img src="" id="target_dados" class="img-fluid rounded" style="max-width:100%">
								</div>

								<div id="audio_dados_div" class="mb-3">
									<audio controls class="w-100" style="height:40px;" id="audio_dados">
										<source src="" type="audio/mp3">
									</audio>
								</div>

								<div id="target_documento_dados_div">
									<div class="d-flex align-items-center">
										<img src="" id="target_documento_dados" width="40px">
										<span class="ms-2">Documento anexado</span>
									</div>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>
	</div>
</div>

<!-- Modal Disparar Campanha -->
<div class="modal fade" id="modalEntrada" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog modal-lg" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title">
					<span id="nome_entrada"></span> - 
					<big><span id="total_clientes" class="badge bg-light text-dark"></span></big> Clientes
				</h4>
				<button id="btn-fechar-entrada" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>
			<div class="modal-body">
    <form id="form-disparar" enctype="multipart/form-data">
        <div class="row">
            <div class="col-md-6">
                <div class="form-group">
                    <label>Selecionar Destinatários</label>
                    <select name="clientes" id="clientes" class="form-control" onchange="selecionarClientes()">
                        <option value="Todos os Clientes">Todos os Clientes</option>
                        <option value="Teste">Teste (Apenas Administrador)</option>
                        <option value="Inadimplentes">Clientes Inadimplentes</option>
                        <option value="Aniversariantes Dia">Aniversariantes do Dia</option>
                        <option value="Aniversariantes Mês">Aniversariantes do Mês</option>
                        <?php 
                            try {
                                $query = $pdo->query("SELECT * FROM grupos_disparos ORDER BY nome ASC");
                                $res = $query->fetchAll(PDO::FETCH_ASSOC);
                                foreach ($res as $grupo) {
                                    $id = (int)$grupo['id'];
                                    $nome = htmlspecialchars($grupo['nome'], ENT_QUOTES, 'UTF-8');
                                    echo "<option value='grupo_{$id}'>Grupo: {$nome}</option>";
                                }
                            } catch (PDOException $e) {
                                echo "<option value=''>Erro ao carregar grupos</option>";
                            }
                        ?>
                    </select>
                </div>
            </div>
						<div class="col-md-3">
							<label>Programar Disparo para</label>
							<input type="date" name="data_disparo" id="data_disparo" value="<?php echo date('Y-m-d'); ?>" class="form-control" required>
						</div>
						<div class="col-md-3">
							<label>A partir de</label>
							<select class="form-control" id="hora_disparo" name="hora_disparo" required>
								<?php
								for ($i = 0; $i < 24; $i++) {
									$hora = str_pad($i, 2, "0", STR_PAD_LEFT);
									$selected = ($hora == '09') ? 'selected' : '';
									echo "<option value='{$hora}:00' {$selected}>{$hora}:00</option>";
								}
								?>
							</select>
						</div>
					</div>

					<div class="row" style="margin-top:15px;">
						<div class="col-md-3">
							<label>Arquivo Excel .xls/.xlsx <small>(nome, telefone)</small></label>
							<input type="file" class="form-control" id="arquivo_excel" name="arquivo_excel" accept=".xls,.xlsx">
						</div>
						<div class="col-md-3">
							<label>Arquivo Texto .txt <small>(separado por vírgula)</small></label>
							<input type="file" class="form-control" id="arquivo_texto" name="arquivo_texto" accept=".txt">
						</div>
						<div class="col-md-2">
							<label>Frequência</label>
							<select name="frequencia" id="frequencia" class="form-control">
								<option value="0">Nenhuma</option>
								<?php
									$query = $pdo->query("SELECT * from frequencias order by id asc");
									$res = $query->fetchAll(PDO::FETCH_ASSOC);
									foreach($res as $f){
										echo '<option value="'.$f['dias'].'">'.$f['frequencia'].'</option>';
									}
								?>
							</select>
						</div>
						<div class="col-md-2">
							<label>Quantidade</label>
							<input type="number" name="quantidade" id="quantidade" class="form-control" placeholder="Vezes">
						</div>
						<div class="col-md-2">
							<label>Intervalo (minutos)</label>
							<input type="number" value="60" min="1" name="intervalo_horas" id="intervalo_horas" class="form-control" placeholder="Minutos">
						</div>
					</div>

					<div class="row" style="margin-top:15px;">
						<div class="col-md-12 text-right">
							<button id="btn_disparar" type="submit" class="btn btn-primary"><i class="fa fa-send"></i> Disparar Mensagens</button>
							<button class="btn btn-primary" type="button" id="btn_carregando_disparo" style="display:none">
								<span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span>
								Processando...
							</button>
						</div>
					</div>
					<input type="hidden" id="id_entrada" name="id">
				</form>

				<div id="mensagem-entrada" class="alert mt-3" style="display:none;"></div>

				<!-- Pré-visualização da mensagem -->
				<div class="mt-4 border-top pt-3">
					<h5 class="text-center mb-3"><u>Pré-visualização da Mensagem</u></h5>
					<div class="row">
						<!-- Mensagem Principal -->
						<div class="col-md-6 mb-3">
							<div class="card h-100">
								<div class="card-header bg-light py-1">
									<h6 class="mb-0 text-muted">Mensagem Principal</h6>
								</div>
								<div class="card-body p-3" style="text-align:left; background-image: url('images/whatsapp.jpg'); background-size: cover; background-position: center; background-repeat: no-repeat; opacity: 0.95;">
									<div class="message-text">
										<span id="mensagem_disparar"></span>
									</div>
								</div>
							</div>
						</div>
						<!-- Mensagem Secundária -->
						<div class="col-md-6 mb-3">
							<div class="card h-100">
								<div class="card-header bg-light py-1">
									<h6 class="mb-0 text-muted">Mensagem Secundária</h6>
								</div>
								<div class="card-body p-3" style="text-align:left; background-image: url('images/whatsapp.jpg'); background-size: cover; background-position: center; background-repeat: no-repeat; opacity: 0.95;">
									<div class="message-text">
										<span id="mensagem2_disparar"></span>
									</div>
								</div>
							</div>
						</div>
					</div>
					<!-- Anexos -->
					<div class="message-preview" style="max-width:400px; border:1px solid #acacad; margin:0 auto; text-align:left; padding:15px; border-radius:10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
						<div class="message-body">
							<div class="attachments">
								<div id="target_disparar_div" class="mb-3 text-center" style="display:none;">
									<img src="" id="target_disparar" class="img-fluid rounded" style="max-width:100%">
								</div>
								<div id="audio_disparar_div" class="mb-3" style="display:none;">
									<audio controls class="w-100" style="height:40px;" id="audio_disparar">
										<source src="" type="audio/mp3">
									</audio>
								</div>
								<div id="target_documento_disparar_div" style="display:none;">
									<div class="d-flex align-items-center">
										<img src="" id="target_documento_disparar" width="40px">
										<span class="ms-2">Documento anexado</span>
									</div>
								</div>
							</div>
						</div>
					</div><!-- preview -->
				</div><!-- preview container -->
			</div><!-- modal-body -->
		</div>
	</div>
</div>


<script type="text/javascript">var pag = "<?= $pag ?>"</script>
<script src="js/ajax.js"></script>

<style>
/* Garante que apenas a aba ativa aparece, e remove espaço fantasma */
.tab-content > .tab-pane {
  display: none !important;
  visibility: hidden;
  height: 0;
  overflow: hidden;
  padding: 0 !important;
  margin: 0 !important;
}

.tab-content > .tab-pane.active {
  display: block !important;
  visibility: visible;
  height: auto;
  overflow: visible;
  padding: initial;
  margin: initial;
}
</style>

<script type="text/javascript">
	$(document).ready(function() {
		 document.getElementById('mensagem-tab').click();
		$('#area_registro').hide();

    $('.sel2').select2({
    	dropdownParent: $('#modalForm')
    });

	// Inicializa as pré-visualizações
    atualizarPreview();
    atualizarPreview2();
    
    // Adiciona listeners para os textareas
    $('#msg').on('input', atualizarPreview);
    $('#msg2').on('input', atualizarPreview2);
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
$("#form-saida").submit(function () {
    event.preventDefault();
    var formData = new FormData(this);
    $.ajax({
        url: 'paginas/' + pag + "/saida.php",
        type: 'POST',
        data: formData,
        success: function (mensagem) {
            $('#mensagem-saida').text('');
            $('#mensagem-saida').removeClass()
            if (mensagem.trim() == "Salvo com Sucesso") {

                $('#btn-fechar-saida').click();
                listar();        
            } else {
                $('#mensagem-saida').addClass('text-danger')
                $('#mensagem-saida').text(mensagem)
            }
        },

        cache: false,
        contentType: false,
        processData: false,
    });
});
</script>


<script type="text/javascript">
$("#form-disparar").submit(function () {	
    event.preventDefault();
    var formData = new FormData(this);
	$('#btn_carregando_disparo').show();
	$('#btn_disparar').hide();
    var ultimo_registro = $('#ultimo_registro').val();
		var cli = $('#clientes').val();
if (cli == "Todos os Clientes") {
    if (ultimo_registro == "") {
        $('#mensagem-entrada').text('Preencha o Registro inicial dos disparos!!!');
        return;
    }
}
    $.ajax({
        url: 'paginas/' + pag + "/disparar.php",
        type: 'POST',
        data: formData,
        success: function (mensagem) {
            if (mensagem.trim() == "Salvo com Sucesso") {
				alertsucesso('Disparo agendado com sucesso!');
				$('#btn-fechar-entrada').click();
                listar(); 
            } else {
				alertErro(mensagem);
            }

            $('#btn_disparar').show();
			$('#btn_carregando_disparo').hide();
        },
        cache: false,
        contentType: false,
        processData: false,
    });
});
</script>


<script type="text/javascript">
	function selecionarClientes(){
  var cli = $('#clientes').val();
  if (cli == "Todos os Clientes") {
    $('#area_registro').show();
  } else {
    $('#area_registro').hide();
  }
  $.ajax({
    url: 'paginas/' + pag + "/listar_clientes.php",
    method: 'POST',
    data: { cli },
    dataType: "text",
    success: function (mensagem) {
      $('#total_clientes').text(mensagem);
    },
  });
}

</script>


<script type="text/javascript">
	function pararDisparo(id){
			$.ajax({
        url: 'paginas/' + pag + "/api_parar_disparos.php",
        method: 'POST',
        data: {id},
        dataType: "text",
        success: function (mensagem) {               
          listar(); 
        },     
    });
	}
</script>


		<script type="text/javascript">
			function carregarImgArquivo() {
				var target = document.getElementById('target_documento');
				var file = document.querySelector("#documento").files[0];
				var arquivo = file['name'];
				resultado = arquivo.split(".", 2);
				if(resultado[1] === 'pdf'){
					$('#target_documento').attr('src', "images/marketing/pdf.png");
					return;
				}
				if(resultado[1] === 'rar' || resultado[1] === 'zip'){
					$('#target_documento').attr('src', "images/rar.png");
					return;
				}
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

<script>
	 // Formata o texto no textarea especificado (msg ou msg2)
        function formatarTexto(tipo, textareaId) {
            let textarea = document.getElementById(textareaId);
            let inicio = textarea.selectionStart;
            let fim = textarea.selectionEnd;
            let texto = textarea.value;
            let marcador;
            switch (tipo) {
                case 'bold':
                    marcador = '*';
                    break;
                case 'italic':
                    marcador = '_';
                    break;
                case 'strike':
                    marcador = '~';
                    break;
                case 'mono':
                    marcador = '`';
                    break;
            }
            let selecionado = texto.substring(inicio, fim);
            let novoTexto;
            let novaPosicaoCursor;

            if (selecionado.length > 0) {
                // Se há texto selecionado
                if (tipo === 'mono') {
                    novoTexto = texto.substring(0, inicio) + '```' + selecionado + '```' + texto.substring(fim);
                    novaPosicaoCursor = fim + 6; // 3 caracteres no início + 3 no fim
                } else {
                    novoTexto = texto.substring(0, inicio) + marcador + selecionado + marcador + texto.substring(fim);
                    novaPosicaoCursor = fim + 2; // 1 caractere no início + 1 no fim
                }
            } else {
                // Se não há texto selecionado
                if (tipo === 'mono') {
                    novoTexto = texto.substring(0, inicio) + '```' + '```' + texto.substring(inicio);
                    novaPosicaoCursor = inicio + 3;
                } else {
                    novoTexto = texto.substring(0, inicio) + marcador + marcador + texto.substring(inicio);
                    novaPosicaoCursor = inicio + 1;
                }
            }

            textarea.value = novoTexto;
            textarea.selectionStart = novaPosicaoCursor;
            textarea.selectionEnd = novaPosicaoCursor;
            textarea.focus();

            // Atualiza a pré-visualização correspondente
            if (textareaId === 'msg') {
                atualizarPreview();
            } else if (textareaId === 'msg2') {
                atualizarPreview2();
            }
        }

	 // Insere emoji no textarea especificado
        function inserirEmoji(emoji, textareaId) {
            let textarea = document.getElementById(textareaId);
            let posicaoAtual = textarea.selectionStart;
            let texto = textarea.value;

            // Insere o emoji na posição atual do cursor
            textarea.value = texto.slice(0, posicaoAtual) + emoji + texto.slice(posicaoAtual);

            // Move o cursor para depois do emoji
            let novaPosicao = posicaoAtual + emoji.length;
            textarea.selectionStart = novaPosicao;
            textarea.selectionEnd = novaPosicao;

            textarea.focus();

            // Atualiza a pré-visualização correspondente
            if (textareaId === 'msg') {
                atualizarPreview();
            } else if (textareaId === 'msg2') {
                atualizarPreview2();
            }
        }

	// Atualiza a pré-visualização da mensagem
	function atualizarPreview() {
		let mensagem = document.getElementById("msg").value;
		let preview = document.getElementById("preview");

		// Aplica as formatações do WhatsApp
		let mensagemFormatada = mensagem
			.replace(/\*(.*?)\*/g, '<strong>$1</strong>') // Negrito
			.replace(/_(.*?)_/g, '<em>$1</em>')          // Itálico
			.replace(/~(.*?)~/g, '<del>$1</del>')        // Tachado
			.replace(/```(.*?)```/g, '<code>$1</code>')  // Monospace
			.replace(/\n/g, '<br>');                     // Quebras de linha

		preview.innerHTML = mensagemFormatada || '...';
	}

	// Atualiza a pré-visualização da mensagem
	function atualizarPreview2() {
		let mensagem = document.getElementById("msg2").value;
		let preview = document.getElementById("preview2");

		// Aplica as formatações do WhatsApp
		let mensagemFormatada = mensagem
			.replace(/\*(.*?)\*/g, '<strong>$1</strong>') // Negrito
			.replace(/_(.*?)_/g, '<em>$1</em>')          // Itálico
			.replace(/~(.*?)~/g, '<del>$1</del>')        // Tachado
			.replace(/```(.*?)```/g, '<code>$1</code>')  // Monospace
			.replace(/\n/g, '<br>');                     // Quebras de linha

		preview.innerHTML = mensagemFormatada || '...';
	}

	// Remove o event listener anterior para evitar duplicação
	document.getElementById("msg").removeEventListener("input", atualizarPreview);
	document.getElementById("msg2").removeEventListener("input", atualizarPreview2);
	// Adiciona o novo event listener
	document.getElementById("msg").addEventListener("input", atualizarPreview);
	document.getElementById("msg2").addEventListener("input", atualizarPreview2);

	// Inicializa a pré-visualização
	atualizarPreview();
	atualizarPreview2();
</script>


<script type="text/javascript">
 // Formata texto conforme regras do WhatsApp
        function formatarMensagemWhatsApp(mensagem) {
            if (!mensagem) return '';

            return mensagem
                .replace(/\*(.*?)\*/g, '<strong>$1</strong>') // Negrito
                .replace(/_(.*?)_/g, '<em>$1</em>')          // Itálico
                .replace(/~(.*?)~/g, '<del>$1</del>')        // Tachado
                .replace(/```(.*?)```/g, '<code>$1</code>')  // Monospace
                .replace(/\n/g, '<br>');                     // Quebras de linha
        }

		// Formata mensagem para exibição nos modais
        function formatarMensagemModal(mensagem) {
            return formatarMensagemWhatsApp(mensagem);
        }

</script>

<script>
	  // Atualiza a pré-visualização da mensagem principal
        function atualizarPreview() {
            let mensagem = document.getElementById("msg").value;
            let preview = document.getElementById("preview");

            // Aplica as formatações do WhatsApp
            let mensagemFormatada = formatarMensagemWhatsApp(mensagem);
            preview.innerHTML = mensagemFormatada || '<span class="text-muted">Pré-visualização da mensagem principal...</span>';
        }

        // Atualiza a pré-visualização da mensagem secundária
        function atualizarPreview2() {
            let mensagem = document.getElementById("msg2").value;
            let preview = document.getElementById("preview2");

            // Aplica as formatações do WhatsApp
            let mensagemFormatada = formatarMensagemWhatsApp(mensagem);
            preview.innerHTML = mensagemFormatada || '<span class="text-muted">Pré-visualização da mensagem secundária...</span>';
        }
</script>


<script>
	
/**
 * Função para limpar a imagem selecionada e restaurar a imagem padrão
 */
function limparImagem() {
    // Redefine a imagem para a padrão "sem-foto"
    document.getElementById('target').src = 'images/marketing/sem-foto.png';
    
    // Limpa o input de arquivo
    document.getElementById('foto').value = '';
}


/**
 * Função para limpar o áudio selecionado
 */
function limparAudio() {
    // Limpa o input de arquivo
    document.getElementById('audio').value = '';
    
    // Redefine a informação de áudio para o padrão
    document.getElementById('audio-info').textContent = 'Áudio';
}

/**
 * Função para limpar o documento selecionado
 */
function limparDocumento() {
    // Redefine a imagem para a padrão "sem-foto"
    document.getElementById('target_documento').src = 'images/marketing/sem-foto.png';
    
    // Limpa o input de arquivo
    document.getElementById('documento').value = '';
}
</script>


<script type="text/javascript">
	function buscar(){
		var busca = $('#pesquisar').val();
		listar(busca)
	}
</script>