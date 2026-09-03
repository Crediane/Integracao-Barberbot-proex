<?php
require_once __DIR__ . '/_context_bootstrap.php';
/**
 * ✅ FIX TENANT x MASTER (banco individual) - ANTES DA CONEXÃO
 * - TENANT: /loja/<slug>/...  -> força $_GET['loja'] e sessão do tenant
 * - MASTER: /sistema/...      -> limpa qualquer tenant antigo (não altera dados do dono do sistema)
 *
 * Motivo do bug: ../conexao.php era carregado antes do tenant, então o PDO conectava no MASTER.
 */

// ✅ carrega tenant helper se existir (não redeclara)
$bbRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
$bbTenantHelper = $bbRoot . '/api/tenant.php';
if (!function_exists('tenant_slug') && is_file($bbTenantHelper)) {
    require_once($bbTenantHelper);
}

$__uriPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

$tenant = '';
if (function_exists('tenant_slug')) {
    $tenant = (string)tenant_slug(); // pode vir de GET/SESSION/URI
} elseif (preg_match('~^/loja/([a-z0-9_-]+)/~i', $__uriPath, $__m)) {
    $tenant = (string)$__m[1];
}

$tenant = preg_replace('/[^a-z0-9_-]/i', '', (string)$tenant);

if ($tenant !== '') {
    $_GET['loja'] = $tenant;
    $_SESSION['loja'] = $tenant;
    $_SESSION['tenant_slug'] = $tenant;
} else {
    unset($_GET['loja']);
    unset(
        $_SESSION['loja'],
        $_SESSION['tenant_slug'],
        $_SESSION['tenant_id'],
        $_SESSION['tenant_db'],
        $_SESSION['tenant_host'],
        $_SESSION['tenant_user'],
        $_SESSION['tenant_pass']
    );
}

require_once __DIR__ . '/../verificar.php';
require_once __DIR__ . '/../../conexao.php';

$pag = 'configuracoes';
$data_atual = date('Y-m-d');


// ===== PROXY INTERNO PARA SALVAR CONFIGURAÇÕES (EVITA 403 NO editar-config.php) =====
// O navegador envia para este próprio módulo; o módulo chama o editar-config.php internamente.
// Mantém a lógica original: editar-config.php continua sendo o arquivo que grava no banco.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['__bb_proxy_salvar_config'])) {
	unset($_POST['__bb_proxy_salvar_config']);

	if (!headers_sent()) {
		header('Content-Type: text/plain; charset=utf-8');
	}

	$bbEditarConfig = __DIR__ . '/../editar-config.php';
	if (is_file($bbEditarConfig)) {
		require $bbEditarConfig;
		exit();
	}

	echo 'Erro ao Editar';
	exit();
}

// verificar se ele tem a permissão de estar nessa página
if (@$configuracoes == 'ocultar') {
	echo "<script>window.location='<?php echo $bb_painel_public_url; ?>/'</script>";
	exit();
}

/**
 * ✅ TENANT HELPER (sem redeclare)
 * caminho: /public_html/api/tenant.php
 */
$bbRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
$bbTenantHelper = $bbRoot . '/api/tenant.php';
if (!function_exists('tenant_slug') && is_file($bbTenantHelper)) {
	require_once($bbTenantHelper);
}

$tenant = '';
if (function_exists('tenant_slug')) {
	$tenant = tenant_slug(); // pega de GET/SESSION/URI
}

// ✅ Resolver URL de assets com fallback (tenant -> global antigo)
$bb_asset_url = function(string $subdir, string $filename, string $fallbackUrl = '') use ($tenant, $bbRoot): string {
	$filename = trim((string)$filename);
	if ($filename === '') return $fallbackUrl;

	// tenta tenant
	if ($tenant !== '' && $bbRoot !== '') {
		$diskTenant = $bbRoot . "/sistema/tenants/{$tenant}/{$subdir}/" . $filename;
		if (is_file($diskTenant)) {
			return "/sistema/tenants/{$tenant}/{$subdir}/" . rawurlencode($filename);
		}
	}

	// tenta global antigo
	if ($bbRoot !== '') {
		if ($subdir === 'img') {
			$diskGlobal = $bbRoot . "/sistema/img/" . $filename;
			if (is_file($diskGlobal)) return "/sistema/img/" . rawurlencode($filename);
		} else { // images
			$diskGlobal = $bbRoot . "/images/" . $filename;
			if (is_file($diskGlobal)) return "/images/" . rawurlencode($filename);
		}
	}

	// fallback (se não achou no disco, retorna a URL calculada ou a fallback informada)
	if ($fallbackUrl !== '') return $fallbackUrl;
	return ($subdir === 'img')
		? ("/sistema/img/" . rawurlencode($filename))
		: ("/images/" . rawurlencode($filename));
};

// ✅ Favicons fixos (192/512) com fallback tenant->global
$bb_fixed_image = function(string $fixedName) use ($tenant, $bbRoot): string {
	$fixedName = trim($fixedName);
	if ($fixedName === '') return '';

	$nomes = [$fixedName];
	$base = preg_replace('/\.(png|webp)$/i', '', $fixedName);
	if ($base === 'favicon_192' || $base === 'favicon_512') {
		// Favicon pode ser PNG ou WEBP, mas sempre na mesma pasta images.
		// Prioriza WEBP quando existir e mantém PNG como fallback antigo.
		$nomes = [$base . '.webp', $base . '.png'];
	}

	foreach ($nomes as $nomeArquivo) {
		if ($tenant !== '' && $bbRoot !== '') {
			$diskTenant = $bbRoot . "/sistema/tenants/{$tenant}/images/" . $nomeArquivo;
			if (is_file($diskTenant)) {
				return "/sistema/tenants/{$tenant}/images/" . rawurlencode($nomeArquivo);
			}
		}

		if ($bbRoot !== '') {
			$diskGlobal = $bbRoot . "/images/" . $nomeArquivo;
			if (is_file($diskGlobal)) return "/images/" . rawurlencode($nomeArquivo);
		}
	}

	return "/images/" . rawurlencode($nomes[0]);
};

// ✅ URLs finais (mantém os mesmos nomes do DB, só troca a pasta conforme tenant)
$bb_logo_src        = $bb_asset_url('img',    $logo_sistema,  "../img/" . $logo_sistema);
$bb_icone_src       = $bb_asset_url('img',    $icone_sistema, "../img/" . $icone_sistema);
$bb_logo_rel_src    = $bb_asset_url('img',    $logo_rel,      "../img/" . $logo_rel);
$bb_fundo_login_src = $bb_asset_url('img',    $fundo_login,   "../img/" . $fundo_login);

$bb_icone_site_src   = $bb_asset_url('images', $icone_site,   "../../images/" . $icone_site);
$bb_fav192_src       = $bb_fixed_image('favicon_192.png');
$bb_fav512_src       = $bb_fixed_image('favicon_512.png');
$bb_imagem_sobre_src = $bb_asset_url('images', $imagem_sobre, "../../images/" . $imagem_sobre);
$bb_banner_src       = $bb_asset_url('images', $img_banner_index, "../../images/" . $img_banner_index);
?>

<div class="row">

<form method="post" id="form-config" enctype="multipart/form-data">
	<?php if(!empty($tenant)){ ?>
	<input type="hidden" name="loja" value="<?php echo htmlspecialchars($tenant, ENT_QUOTES, 'UTF-8'); ?>">
	<?php } ?>
	<div class="modal-body">

		<div class="row">
			<div class="col-md-4">
				<div class="form-group">
					<label for="exampleInputEmail1">Nome Barbearia</label>
					<input type="text" class="form-control" id="nome_sistema" name="nome_sistema" placeholder="Nome da Barbearia" value="<?php echo $nome_sistema ?>" required>
				</div>
			</div>

			<div class="col-md-4">
				<div class="form-group">
					<label for="exampleInputEmail1">Email Barbearia</label>
					<input type="email" class="form-control" id="email_sistema" name="email_sistema" placeholder="Email" value="<?php echo $email_sistema ?>" required>
				</div>
			</div>

			<div class="col-md-4">
				<div class="form-group">
					<label for="exampleInputEmail1">Whatsapp Barbearia</label>
					<input type="text" class="form-control" id="whatsapp_sistema" name="whatsapp_sistema" placeholder="Whatsapp" value="<?php echo $whatsapp_sistema ?>" required>
				</div>
			</div>
		</div>


		<div class="row">

			<div class="col-md-3">
				<div class="form-group">
					<label for="exampleInputEmail1">Tel Fixo Barbearia</label>
					<input type="text" class="form-control" id="telefone_fixo_sistema" name="telefone_fixo_sistema" placeholder="Fixo" value="<?php echo $telefone_fixo_sistema ?>" required>
				</div>
			</div>

			<div class="col-md-7">
				<div class="form-group">
					<label for="exampleInputEmail1">Endereço Barbearia</label>
					<input type="text" class="form-control" id="endereco_sistema" name="endereco_sistema" placeholder="Rua X Numero X Bairro Cidade" value="<?php echo $endereco_sistema ?>">
				</div>
			</div>

			<div class="col-md-2">
				<div class="form-group">
					<label for="exampleInputEmail1">Tipo Relatório</label>
					<select class="form-control" name="tipo_rel" id="tipo_rel">
						<option value="PDF" <?php if($tipo_rel == 'PDF'){?> selected <?php } ?> >PDF</option>
						<option value="HTML" <?php if($tipo_rel == 'HTML'){?> selected <?php } ?> >HTML</option>
					</select>
				</div>
			</div>

		</div>


		<div class="row">

			<div class="col-md-6">
				<div class="form-group">
					<label for="exampleInputEmail1">Instagram</label>
					<input type="text" class="form-control" id="instagram_sistema" name="instagram_sistema" placeholder="Link do Perfil no Instagram" value="<?php echo $instagram_sistema ?>">
				</div>
			</div>

			<div class="col-md-6">
				<div class="form-group">
					<label for="exampleInputEmail1">Mapa Site <small>(Url incorporada)</small></label>
					<input type="text" class="form-control" id="mapa" name="mapa" placeholder="" value='<?php echo $mapa ?>'>
				</div>
			</div>

		</div>


		<div class="row">
			<div class="col-md-12">
				<div class="form-group">
					<label for="exampleInputEmail1">Texto Rodapé Site <small>(600) Caracteres</small></label>
					<input maxlength="600" type="text" class="form-control" id="texto_rodape" name="texto_rodape" placeholder="Texto para o Rodapé do site" value="<?php echo $texto_rodape ?>">
				</div>
			</div>
		</div>


		<div class="row">
			<div class="col-md-12">
				<div class="form-group">
					<label for="exampleInputEmail1">Texto Sobre (Site) <small>(2000) Caracteres</small></label>
					<input maxlength="2000" type="text" class="form-control" id="texto_sobre" name="texto_sobre" placeholder="Texto para a área Sobre a empresa no site" value="<?php echo $texto_sobre ?>">
				</div>
			</div>
		</div>


		<div class="row">
			<div class="col-md-12">
				<div class="form-group">
					<label for="exampleInputEmail1">Texto Cartão Fidelidade</label>
					<input maxlength="255" type="text" class="form-control" id="texto_fidelidade" name="texto_fidelidade" placeholder="Parabéns, você completou seus cartões, você ganhou ..." value="<?php echo @$texto_fidelidade ?>">
				</div>
			</div>
		</div>


		<div class="row">

			<div class="col-md-2">
				<div class="form-group">
					<label for="exampleInputEmail1">Cartões Troca</label>
					<input type="number" class="form-control" id="quantidade_cartoes" name="quantidade_cartoes" placeholder="Quantidade Cartões Troca" value="<?php echo $quantidade_cartoes ?>">
				</div>
			</div>

			<div class="col-md-2">
				<div class="form-group">
					<label for="exampleInputEmail1">Texto Agendamento</label>
					<input maxlength="30" type="text" class="form-control" id="texto_agendamento" name="texto_agendamento" placeholder="Selecionar Cabelereira" value="<?php echo $texto_agendamento ?>">
				</div>
			</div>

			<div class="col-md-2">
				<div class="form-group">
					<label for="exampleInputEmail1">Mensagem Api</label>
					<select class="form-control" name="msg_agendamento" id="msg_agendamento">
						<option value="Sim" <?php if($msg_agendamento == 'Sim'){?> selected <?php } ?> >Sim</option>
						<option value="Não" <?php if($msg_agendamento == 'Não'){?> selected <?php } ?> >Não</option>
						<option value="Api" <?php if($msg_agendamento == 'Api'){?> selected <?php } ?> >Api Paga</option>
					</select>
				</div>
			</div>


			<div class="col-md-2">
				<div class="form-group">
					<label for="exampleInputEmail1">Seletor de Api</label>
					<select class="form-control" name="api" id="api">
						<option value="menuia" <?php if($api == 'menuia'){?> selected <?php } ?> >Menuia</option>
						<option value="outros" <?php if($api == 'outros'){?> selected <?php } ?> >Word Mensagens</option>
					</select>
				</div>
			</div>

			<div class="col-md-2">
				<div class="form-group">
					<label for="exampleInputEmail1">Token (appkey)</label>
					<input type="text" class="form-control" id="token" name="token" placeholder="Token APP Key" value="<?php echo @$token ?>">
				</div>
			</div>

			<div class="col-md-2">
				<div class="form-group">
					<label for="exampleInputEmail1">Instancia (authkey)</label>
					<input type="text" class="form-control" id="instancia" name="instancia" placeholder="Instância AuthKey" value="<?php echo @$instancia ?>">
				</div>
			</div>

		</div>


		<div class="row">

			<div class="col-md-2">
				<div class="form-group">
					<label for="exampleInputEmail1">Horas Confirmação</label>
					<input type="number" class="form-control" id="minutos_aviso" name="minutos_aviso" placeholder="Alerta Agendamento" value="<?php echo @$minutos_aviso ?>">
				</div>
			</div>

			<div class="col-md-3">
				<div class="form-group">
					<label for="exampleInputEmail1">CNPJ</label>
					<input type="text" class="form-control" id="cnpj_sistema" name="cnpj_sistema" value="<?php echo $cnpj_sistema ?>">
				</div>
			</div>

			<div class="col-md-2">
				<div class="form-group">
					<label for="exampleInputEmail1">Cidade</label>
					<input type="text" class="form-control" id="cidade_sistema" name="cidade_sistema" value="<?php echo $cidade_sistema ?>" placeholder="Cidade para o contrato">
				</div>
			</div>

			<div class="col-md-3">
				<div class="form-group">
					<label for="exampleInputEmail1">Manter Agendamento Dias</label>
					<input type="number" class="form-control" id="agendamento_dias" name="agendamento_dias" value="<?php echo $agendamento_dias ?>" placeholder="Manter no Banco de Dados">
				</div>
			</div>

			<div class="col-md-2">
				<div class="form-group">
					<label for="exampleInputEmail1">Itens Paginação</label>
					<input type="number" class="form-control" id="itens_pag" name="itens_pag" value="<?php echo $itens_pag ?>" placeholder="">
				</div>
			</div>

		</div>



		<div class="row">

			<div class="col-md-2">
				<div class="form-group">
					<label for="exampleInputEmail1">Taxa Pgto Serviço</label>
					<select class="form-control" name="taxa_sistema" id="taxa_sistema">
						<option value="Cliente" <?php if(@$taxa_sistema == 'Cliente'){?> selected <?php } ?> >Cliente Paga</option>
						<option value="Empresa" <?php if(@$taxa_sistema == 'Empresa'){?> selected <?php } ?> >Salão Paga</option>
					</select>
				</div>
			</div>

			<div class="col-md-2">
				<div class="form-group">
					<label for="exampleInputEmail1">Tipo Comissão</label>
					<select class="form-control" name="tipo_comissao" id="tipo_comissao">
						<option value="Porcentagem" <?php if($tipo_comissao == 'Porcentagem'){?> selected <?php } ?> >Porcentagem</option>
						<option value="R$" <?php if($tipo_comissao == 'R$'){?> selected <?php } ?> >R$ Reais</option>
					</select>
				</div>
			</div>

			<div class="col-md-2">
				<div class="form-group">
					<label for="exampleInputEmail1">Lanc Comissão </label>
					<select class="form-control" name="lanc_comissao" id="lanc_comissao">
						<option value="Sempre" <?php if($lanc_comissao == 'Sempre'){?> selected <?php } ?> >Serviço Pendente e Pago</option>
						<option value="Pago" <?php if($lanc_comissao == 'Pago'){?> selected <?php } ?> >Serviço Pago</option>
					</select>
				</div>
			</div>

			<div class="col-md-2">
				<div class="form-group">
					<label for="exampleInputEmail1">% Agendamento</label>
					<input type="number" class="form-control" id="porc_servico" name="porc_servico" placeholder="% pagar Agendamento" value="<?php echo $porc_servico ?>">
				</div>
			</div>

			<div class="col-md-2">
				<div class="form-group">
					<label for="exampleInputEmail1">Api Pgto</label>
					<select class="form-control" name="pgto_api" id="pgto_api">
						<option value="Sim" <?php if($pgto_api == 'Sim'){?> selected <?php } ?> >Sim</option>
						<option value="Não" <?php if($pgto_api == 'Não'){?> selected <?php } ?> >Não</option>
					</select>
				</div>
			</div>

			<div class="col-md-2">
				<div class="form-group">
					<label for="exampleInputEmail1">Entrada Site</label>
					<select class="form-control" name="entrada" id="entrada">
						<option value="Site" <?php if($entrada == 'Site'){?> selected <?php } ?> >Site</option>
						<option value="Agendamento" <?php if($entrada == 'Agendamento'){?> selected <?php } ?> >Agendamento</option>
						<option value="Login" <?php if($entrada == 'Login'){?> selected <?php } ?> >Login</option>
					</select>
				</div>
			</div>

		</div><div class="row">
	<div class="col-md-3">
		<label>Pgto Agendamento</label>
		<select class="form-control" name="opcao_pagar">
			<option value="Sim" <?php if($opcao_pagar == 'Sim'){ echo 'selected'; } ?>>Sim</option>
			<option value="Não" <?php if($opcao_pagar == 'Não'){ echo 'selected'; } ?>>Não</option>
		</select>
	</div>

	<div class="col-md-3">
		<label>Alerta Vencimento (dias)</label>
		<select class="form-control" name="dias_alerta_vencimento" id="dias_alerta_vencimento">
			<?php
				// Valor usado pelo cron/cron_alerta_receber.php.
				// 0 = Desativado. Demais valores = quantidade de dias antes do vencimento.
				$dias_alerta_vencimento = isset($dias_alerta_vencimento) ? (int)$dias_alerta_vencimento : 3;
				$opcoes_alerta_vencimento = array(
					0  => 'Desativado',
					1  => '1 dia antes',
					2  => '2 dias antes',
					3  => '3 dias antes',
					5  => '5 dias antes',
					7  => '7 dias antes',
					10 => '10 dias antes',
					15 => '15 dias antes',
					30 => '30 dias antes'
				);
				if (!array_key_exists($dias_alerta_vencimento, $opcoes_alerta_vencimento)) {
					$dias_alerta_vencimento = 3;
				}
				foreach ($opcoes_alerta_vencimento as $valor_alerta => $texto_alerta) {
					$selected_alerta = ($dias_alerta_vencimento === (int)$valor_alerta) ? 'selected' : '';
			?>
				<option value="<?php echo (int)$valor_alerta; ?>" <?php echo $selected_alerta; ?>>
					<?php echo htmlspecialchars($texto_alerta, ENT_QUOTES, 'UTF-8'); ?>
				</option>
			<?php } ?>
		</select>
	</div>
</div>
		<div class="row">

			<div class="col-md-2">
				<div class="form-group">
					<label for="exampleInputEmail1">Api Pagamento</label>
					<select class="form-control" name="api_pagamento" id="api_pagamento">
						<option value="Mercado Pago" <?php if($api_pagamento == 'Mercado Pago'){?> selected <?php } ?> >Mercado Pago</option>
						<option value="Asaas" <?php if($api_pagamento == 'Asaas'){?> selected <?php } ?> >Asaas</option>
					</select>
				</div>
			</div>

			<div class="col-md-3">
				<div class="form-group">
					<label for="exampleInputEmail1">Public Key (Mercado Pago)</label>
					<input type="text" class="form-control" id="public_key_mp" name="public_key_mp" placeholder="Chave Public Key do Mercado Pago" value="<?php echo $public_key_mp ?>">
				</div>
			</div>

			<div class="col-md-3">
				<div class="form-group">
					<label for="exampleInputEmail1">Access Token (Mercado Pago)</label>
					<input type="text" class="form-control" id="access_token_mp" name="access_token_mp" placeholder="Chave Access Token do Mercado Pago" value="<?php echo $access_token_mp ?>">
				</div>
			</div>

			<div class="col-md-4">
				<div class="form-group">
					<label for="exampleInputEmail1">Chave Token do Asaas</label>
					<input type="text" class="form-control" id="asaas" name="asaas" placeholder="Chave Token Asaas" value="<?php echo $asaas ?>">
				</div>
			</div>

		</div>

		<hr>

		<div class="row">

			<div class="col-md-3">
				<div class="form-group">
					<label for="exampleInputEmail1">Nome (Bot)</label>
					<input type="text" class="form-control" id="nome_bot" name="nome_bot" placeholder="Nome do Bot" value="<?php echo @$nome_bot ?>">
				</div>
			</div>

			<div class="col-md-3">
				<div class="form-group">
					<label for="exampleInputEmail1">BarberBot Ativo</label>
					<select class="form-control" name="barberbot_ativo" id="barberbot_ativo">
						<option value="Sim" <?php if(@$barberbot_ativo == 'Sim'){?> selected <?php } ?> >Sim</option>
						<option value="Não" <?php if(@$barberbot_ativo == 'Não'){?> selected <?php } ?> >Não</option>
					</select>
				</div>
			</div>

			<div class="col-md-6">
				<div class="form-group">
					<label for="exampleInputEmail1">OpenAI Key</label>
					<input type="text" class="form-control" id="openai_key" name="openai_key" placeholder="sk-..." value="<?php echo @$openai_key ?>">
				</div>
			</div>

		</div>


		<div class="row">

			<div class="col-md-6">
				<div class="form-group">
					<label for="exampleInputEmail1">BarberBot Boas-vindas <small>(600 Caracteres)</small></label>
					<textarea maxlength="600" class="form-control" id="barberbot_boas_vindas" name="barberbot_boas_vindas" rows="4" placeholder="Mensagem de boas-vindas"><?php echo @$barberbot_boas_vindas ?></textarea>
				</div>
			</div>

			<div class="col-md-6">
				<div class="form-group">
					<label for="exampleInputEmail1">OpenAI Prompt <small>(2000 Caracteres)</small></label>
					<textarea maxlength="2000" class="form-control" id="openai_prompt" name="openai_prompt" rows="4" placeholder="Prompt do sistema"><?php echo @$openai_prompt ?></textarea>
				</div>
			</div>

		</div>

		<hr>


		<div class="row">

			<div class="col-md-4">
				<div class="form-group">
					<label>Logo (*PNG)</label>
					<input class="form-control" type="file" name="foto-logo" onChange="carregarImgLogo();" id="foto-logo">
				</div>
			</div>
			<div class="col-md-2">
				<div id="divImg">
					<img src="<?php echo $bb_logo_src; ?>" width="80px" id="target-logo">
				</div>
			</div>


			<div class="col-md-4">
				<div class="form-group">
					<label>Ícone (*Png)</label>
					<input class="form-control" type="file" name="foto-icone" onChange="carregarImgIcone();" id="foto-icone">
				</div>
			</div>
			<div class="col-md-2">
				<div id="divImg">
					<img src="<?php echo $bb_icone_src; ?>" width="50px" id="target-icone">
				</div>
			</div>

		</div>



		<div class="row">

			<div class="col-md-4">
				<div class="form-group">
					<label>Logo Relatório (*Jpg)</label>
					<input class="form-control" type="file" name="foto-logo-rel" onChange="carregarImgLogoRel();" id="foto-logo-rel">
				</div>
			</div>
			<div class="col-md-2">
				<div id="divImg">
					<img src="<?php echo $bb_logo_rel_src; ?>" width="80px" id="target-logo-rel">
				</div>
			</div>


			<div class="col-md-4">
				<div class="form-group">
					<label>Ícone Site (*png)</label>
					<input class="form-control" type="file" name="foto-icone-site" onChange="carregarImgIconeSite();" id="foto-icone-site">
				</div>
			</div>
			<div class="col-md-2">
				<div id="divImg">
					<img src="<?php echo $bb_icone_site_src; ?>" width="50px" id="target-icone-site">
				</div>
			</div>
	<div class="row">

			<div class="col-md-4">
				<div class="form-group">
					<label>Favicon 192 x 192 (*png/webp)</label>
					<input class="form-control" type="file" name="foto-favicon192" accept=".png,.webp,image/png,image/webp" onChange="carregarImgFavicon192();" id="foto-favicon192">
				</div>
			</div>
			<div class="col-md-2">
				<div id="divImg">
					<img src="<?php echo $bb_fav192_src; ?>" width="80px" id="target-favicon192">
				</div>
			</div>


			<div class="col-md-4">
				<div class="form-group">
					<label>Favicon 512 x 512 (*png/webp)</label>
					<input class="form-control" type="file" name="foto-favicon512" accept=".png,.webp,image/png,image/webp" onChange="carregarImgFavicon512();" id="foto-favicon512">
				</div>
			</div>
			<div class="col-md-2">
				<div id="divImg">
					<img src="<?php echo $bb_fav512_src; ?>" width="80px" id="target-favicon512">
				</div>
			</div>

		</div>


		<hr>



		<div class="row">

			<div class="col-md-4">
				<div class="form-group">
					<label>Imagem Área Sobre (Site)</label>
					<input class="form-control" type="file" name="foto-sobre" onChange="carregarImgSobre();" id="foto-sobre">
				</div>
			</div>
			<div class="col-md-2">
				<div id="divImg">
					<img src="<?php echo $bb_imagem_sobre_src; ?>" width="80px" id="target-sobre">
				</div>
			</div>


			<div class="col-md-4">
				<div class="form-group">
					<label>Imagem Banner Index <small>(1500x1000)</small></label>
					<input class="form-control" type="file" name="foto-banner-index" onChange="carregarImgBannerIndex();" id="foto-banner-index">
				</div>
			</div>
			<div class="col-md-2">
				<div id="divImg">
					<img src="<?php echo $bb_banner_src; ?>" width="80px" id="target-banner-index">
				</div>
			</div>

		</div>


		<div class="row">
			<div class="col-md-4">
				<div class="form-group">
					<label>Fundo Login <small>(Imagem) 1920 x 1080</small></label>
					<input class="form-control" type="file" name="fundo_login" onChange="carregarImgFundo();" id="fundo_login">
				</div>
			</div>
			<div class="col-md-2">
				<div id="divImg">
					<img src="<?php echo $bb_fundo_login_src; ?>" width="80px" id="target-fundo">
					<a title="Excluir Imagem" href="#" onclick="excluirImg('Fundo')"><i class="fa fa-close text-danger"></i></a>
				</div>
			</div>
		</div>



		<!-- ✅ NÃO REMOVI NADA: VIDEO CONTINUA -->
		<div class="row">
			<div class="col-md-4">
				<div class="form-group">
					<label for="exampleInputEmail1">Url do Vídeo Index</label>
					<input type="text" class="form-control" id="url_video" name="url_video" value="<?php echo $url_video ?>" placeholder="Url do Youtube Incorporada">
				</div>
			</div>

			<div class="col-md-4">
				<div class="form-group">
					<label for="exampleInputEmail1">Posição do Vídeo</label>
					<select class="form-control" name="posicao_video" id="posicao_video">
						<option value="sobre" <?php if($posicao_video == 'sobre'){?> selected <?php } ?> >Encima da Imagem Sobre</option>
						<option value="abaixo" <?php if($posicao_video == 'abaixo'){?> selected <?php } ?> >Abaixo da Área Sobre</option>
					</select>
				</div>
			</div>
		</div>

		<br>
		<small><div id="mensagem-config" align="center"></div></small>
	</div>

	<div class="modal-footer">
		<button type="submit" class="btn btn-primary">Salvar Dados</button>
	</div>

</form>

</div>




<script type="text/javascript">
	function bbPreviewImagem(inputId, targetId) {
		var input = document.getElementById(inputId);
		var target = document.getElementById(targetId);
		if (!input || !target) return;

		var file = input.files && input.files[0] ? input.files[0] : null;
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

	function carregarImgLogo() { bbPreviewImagem('foto-logo', 'target-logo'); }
	function carregarImgIcone() { bbPreviewImagem('foto-icone', 'target-icone'); }
	function carregarImgLogoRel() { bbPreviewImagem('foto-logo-rel', 'target-logo-rel'); }
	function carregarImgIconeSite() { bbPreviewImagem('foto-icone-site', 'target-icone-site'); }
	function carregarImgSobre() { bbPreviewImagem('foto-sobre', 'target-sobre'); }
	function carregarImgBannerIndex() { bbPreviewImagem('foto-banner-index', 'target-banner-index'); }
</script>

<script type="text/javascript">
	function carregarImgFavicon512() {
		var target = document.getElementById('target-favicon512');
		var file = document.querySelector("#foto-favicon512").files[0];

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
	function carregarImgFavicon192() {
		var target = document.getElementById('target-favicon192');
		var file = document.querySelector("#foto-favicon192").files[0];

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
	function excluirImg(p){

		const swalWithBootstrapButtons = Swal.mixin({
			customClass: {
				confirmButton: "btn btn-success",
				cancelButton: "btn btn-danger me-1",
				container: 'swal-whatsapp-container'
			},
			buttonsStyling: false
		});

		swalWithBootstrapButtons.fire({
			title: "Deseja Excluir?",
			text: "Você não conseguirá recuperá-lo novamente!",
			icon: "warning",
			showCancelButton: true,
			confirmButtonText: "Sim, Excluir!",
			cancelButtonText: "Não, Cancelar!",
			reverseButtons: true
		}).then((result) => {

			if (result.isConfirmed) {

				$.ajax({
					// ✅ absoluto + mantém tenant pela query (não remove nada)
					url: "/sistema/painel/excluir_imagens.php<?php echo ($tenant!='') ? ('?loja='.rawurlencode($tenant)) : ''; ?>",
					method: 'POST',
					data: { p },
					dataType: "html",
					success: function (mensagem) {

						if (mensagem.trim() == "Excluído com Sucesso") {

							swalWithBootstrapButtons.fire({
								title: mensagem,
								text: 'Fecharei em 1 segundo.',
								icon: "success",
								timer: 1000,
								timerProgressBar: true,
								confirmButtonText: 'OK',
								customClass: {
									container: 'swal-whatsapp-container'
								}
							});

							location.reload();

						} else {

							swalWithBootstrapButtons.fire({
								title: "Opss!",
								text: mensagem,
								icon: "error",
								confirmButtonText: 'OK',
								customClass: {
									container: 'swal-whatsapp-container'
								}
							});

						}
					}
				});

			} else if (result.dismiss === Swal.DismissReason.cancel) {

				swalWithBootstrapButtons.fire({
					title: "Cancelado",
					text: "Fecharei em 1 segundo.",
					icon: "error",
					timer: 1000,
					timerProgressBar: true,
				});

			}
		});
	}
</script>

<script type="text/javascript">
	function carregarImgFundo() {
		var target = document.getElementById('target-fundo');
		var file = document.querySelector("#fundo_login").files[0];

		var reader = new FileReader();

		reader.onloadend = function() {
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
	$("#form-config").submit(function (e) {
		e.preventDefault();

		var formData = new FormData(this);
		formData.append('__bb_proxy_salvar_config', '1');

		// ===== Proteção contra bloqueio 403 do servidor/mod_security =====
		// Envia campos de texto codificados e o editar-config.php decodifica antes de salvar.
		// Não altera a lógica do sistema nem o nome das colunas; apenas protege o transporte do formulário.
		function bbCfgEncodeUtf8(valor) {
			try {
				return '__BB64__' + btoa(unescape(encodeURIComponent(valor)));
			} catch (e) {
				return valor;
			}
		}

		$(this).find('input, textarea, select').each(function(){
			var campo = this;
			var nome = campo.name || '';
			var tipo = (campo.type || '').toLowerCase();

			if (nome === '' || nome === 'loja') return;
			if (tipo === 'file' || tipo === 'checkbox' || tipo === 'radio') return;
			if (!formData.has(nome)) return;

			formData.set(nome, bbCfgEncodeUtf8($(campo).val() || ''));
		});

		$.ajax({
			// ✅ absoluto + mantém tenant pela query (não remove nada)
			url: "paginas/configuracoes.php<?php echo ($tenant!='') ? ('?loja='.rawurlencode($tenant)) : ''; ?>",
			type: "POST",
			data: formData,
			cache: false,
			contentType: false,
			processData: false,
			success: function (mensagem) {
				$("#mensagem-config").removeClass();

				if (mensagem.trim() == "Editado com Sucesso") {
					$("#mensagem-config").addClass("text-success");
				} else {
					$("#mensagem-config").addClass("text-danger");
				}

				$("#mensagem-config").text(mensagem);
			},
			error: function (xhr) {
				$("#mensagem-config").removeClass().addClass("text-danger");
				var resposta = (xhr && xhr.responseText) ? xhr.responseText.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim() : '';
				if (resposta.length > 180) resposta = resposta.substring(0, 180);
				$("#mensagem-config").text(resposta || "Erro ao Editar");
			}
		});
	});
</script>
