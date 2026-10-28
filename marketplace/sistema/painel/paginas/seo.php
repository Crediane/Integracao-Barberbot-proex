<?php
// SEO usa o contexto já validado pelo bootstrap central:
// - sessão BB_MASTER => Banco Global/Administrativo;
// - sessão BB_TENANT_* => Banco Individual mapeado para o slug.
require_once __DIR__ . '/_context_bootstrap.php';

$pag = 'seo';

if (@$_SESSION['id'] == '') {
	echo "<script>window.location='index.php'</script>";
	exit();
}

if (@$_SESSION['nivel'] != 'Administrador') {
	echo "<script>window.location='index.php'</script>";
	exit();
}

$bb_seo_tenant_slug = strpos((string)session_name(), 'BB_TENANT') === 0
	? bb_pages_clean_slug($_SESSION['tenant_slug'] ?? '')
	: '';

if ($bb_seo_tenant_slug !== '') {
	$bb_seo_salvar_base = isset($url_painel_assets) && $url_painel_assets !== ''
		? rtrim((string)$url_painel_assets, '/')
		: '/sistema/painel';
	$bb_seo_salvar_url = $bb_seo_salvar_base . '/paginas/seo/salvar.php?loja=' . rawurlencode($bb_seo_tenant_slug);
} else {
	$bb_seo_salvar_url = '/sistema/painel/paginas/seo/salvar.php';
}



if(@$_SESSION['nivel'] != 'Administrador'){
	echo "<script>window.location='index.php'</script>";
	exit();
}

if(!function_exists('hSeoPainel')){
	function hSeoPainel($valor){
		return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
	}
}

if(!function_exists('colunaSeoPainelExiste')){
	function colunaSeoPainelExiste($pdo, $coluna){
		try{
			$query = $pdo->prepare("SHOW COLUMNS FROM `config` LIKE :coluna");
			$query->bindValue(':coluna', $coluna);
			$query->execute();
			return $query->rowCount() > 0;
		}catch(Exception $e){
			return false;
		}
	}
}

if(!function_exists('garantirColunaSeoPainel')){
	function garantirColunaSeoPainel($pdo, $coluna, $tipo){
		$colunas_permitidas = array(
			'seo_titulo' => 'varchar(255)',
			'seo_descricao' => 'varchar(500)',
			'seo_palavras' => 'varchar(500)',
			'seo_autor' => 'varchar(100)',
			'seo_robots' => 'varchar(50)',
			'seo_canonical' => 'varchar(300)',
			'seo_og_image' => 'varchar(300)',
			'seo_google_verification' => 'varchar(255)',
			'seo_google_analytics' => 'varchar(80)',
			'seo_google_tag_manager' => 'varchar(80)',
			'seo_google_ads' => 'varchar(80)',
			'seo_meta_pixel' => 'varchar(80)',
			'seo_head_extra' => 'mediumtext',
			'seo_body_extra' => 'mediumtext',
			'recaptcha_tipo' => 'varchar(20)',
			'recaptcha_site_key_v2' => 'varchar(255)',
			'recaptcha_secret_key_v2' => 'varchar(255)',
			'recaptcha_site_key_v3' => 'varchar(255)',
			'recaptcha_secret_key_v3' => 'varchar(255)'
		);

		if(!isset($colunas_permitidas[$coluna])){
			return false;
		}

		if(strtolower($tipo) != strtolower($colunas_permitidas[$coluna])){
			$tipo = $colunas_permitidas[$coluna];
		}

		try{
			if(!colunaSeoPainelExiste($pdo, $coluna)){
				$padrao = stripos($tipo, 'text') !== false ? '' : ' DEFAULT NULL';
				$pdo->query("ALTER TABLE `config` ADD COLUMN `$coluna` $tipo$padrao");
			}
			return true;
		}catch(Exception $e){
			return false;
		}
	}
}

garantirColunaSeoPainel($pdo, 'seo_titulo', 'varchar(255)');
garantirColunaSeoPainel($pdo, 'seo_descricao', 'varchar(500)');
garantirColunaSeoPainel($pdo, 'seo_palavras', 'varchar(500)');
garantirColunaSeoPainel($pdo, 'seo_autor', 'varchar(100)');
garantirColunaSeoPainel($pdo, 'seo_robots', 'varchar(50)');
garantirColunaSeoPainel($pdo, 'seo_canonical', 'varchar(300)');
garantirColunaSeoPainel($pdo, 'seo_og_image', 'varchar(300)');
garantirColunaSeoPainel($pdo, 'seo_google_verification', 'varchar(255)');
garantirColunaSeoPainel($pdo, 'seo_google_analytics', 'varchar(80)');
garantirColunaSeoPainel($pdo, 'seo_google_tag_manager', 'varchar(80)');
garantirColunaSeoPainel($pdo, 'seo_google_ads', 'varchar(80)');
garantirColunaSeoPainel($pdo, 'seo_meta_pixel', 'varchar(80)');
garantirColunaSeoPainel($pdo, 'seo_head_extra', 'mediumtext');
garantirColunaSeoPainel($pdo, 'seo_body_extra', 'mediumtext');
garantirColunaSeoPainel($pdo, 'recaptcha_tipo', 'varchar(20)');
garantirColunaSeoPainel($pdo, 'recaptcha_site_key_v2', 'varchar(255)');
garantirColunaSeoPainel($pdo, 'recaptcha_secret_key_v2', 'varchar(255)');
garantirColunaSeoPainel($pdo, 'recaptcha_site_key_v3', 'varchar(255)');
garantirColunaSeoPainel($pdo, 'recaptcha_secret_key_v3', 'varchar(255)');

$query = $pdo->query("SELECT * FROM config ORDER BY id ASC LIMIT 1");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$config = array();
if(@count($res) > 0){
	$config = $res[0];
}

$id_config = @$config['id'];
$dominio_padrao = 'https://' . @$_SERVER['HTTP_HOST'] . '/';
if($bb_seo_tenant_slug !== ''){
	$dominio_padrao .= 'loja/' . $bb_seo_tenant_slug . '/';
}

$seo_titulo = @$config['seo_titulo'];
$seo_descricao = @$config['seo_descricao'];
$seo_palavras = @$config['seo_palavras'];
$seo_autor = @$config['seo_autor'];
$seo_robots = @$config['seo_robots'];
$seo_canonical = @$config['seo_canonical'];
$seo_og_image = @$config['seo_og_image'];
$seo_google_verification = @$config['seo_google_verification'];
$seo_google_analytics = @$config['seo_google_analytics'];
$seo_google_tag_manager = @$config['seo_google_tag_manager'];


$seo_head_extra = @$config['seo_head_extra'];
$seo_body_extra = @$config['seo_body_extra'];
$recaptcha_tipo = @$config['recaptcha_tipo'];
$recaptcha_site_key_v2 = @$config['recaptcha_site_key_v2'];
$recaptcha_secret_key_v2 = @$config['recaptcha_secret_key_v2'];
$recaptcha_site_key_v3 = @$config['recaptcha_site_key_v3'];
$recaptcha_secret_key_v3 = @$config['recaptcha_secret_key_v3'];

if($recaptcha_tipo != 'v2' && $recaptcha_tipo != 'v3'){
	$recaptcha_tipo = 'v3';
}

if($seo_titulo == ''){
	$seo_titulo = $nome_sistema . ' | Agendamento Online';
}
if($seo_descricao == ''){
	$seo_descricao = 'Agende seu horário online com praticidade. Atendimento profissional para cabelo, barba, estética, manicure e prótese capilar.';
}
if($seo_palavras == ''){
	$seo_palavras = 'cabeleireiro, barbearia, salão de beleza, agendamento online, manicure, estética, prótese capilar';
}
if($seo_autor == ''){
	$seo_autor = $nome_sistema;
}
if($seo_robots == ''){
	$seo_robots = 'index,follow';
}
if($seo_canonical == ''){
	$seo_canonical = $dominio_padrao;
}
if($seo_og_image == ''){
	$seo_og_image = $dominio_padrao . 'sistema/img/logo.png';
}
?>

<div class="main-page">
	<div class="forms">
		<h3 class="title1">SEO do Site</h3>

		<div class="form-grids row widget-shadow" data-example-id="basic-forms">
			<div class="form-title">
				<h4>Configurações de SEO, Google e Meta Pixel</h4>
			</div>

			<div class="form-body">
				<form method="post" id="form-seo">
					<input type="hidden" name="id_config" id="id_config" value="<?php echo hSeoPainel($id_config) ?>">

					<div class="row">
						<div class="col-md-8">
							<div class="panel panel-default">
								<div class="panel-heading">
									<h4 class="panel-title">Informações principais para o Google</h4>
								</div>
								<div class="panel-body">
									<div class="form-group">
										<label>Título SEO</label>
										<input type="text" class="form-control" name="seo_titulo" id="seo_titulo" maxlength="255" value="<?php echo hSeoPainel($seo_titulo) ?>" placeholder="Título que aparece no Google" required>
										<small><span id="cont_titulo">0</span> caracteres. Recomendado: até 60.</small>
									</div>

									<div class="form-group">
										<label>Descrição SEO</label>
										<textarea class="form-control" name="seo_descricao" id="seo_descricao" maxlength="500" rows="4" placeholder="Descrição que aparece no Google" required><?php echo hSeoPainel($seo_descricao) ?></textarea>
										<small><span id="cont_descricao">0</span> caracteres. Recomendado: até 160.</small>
									</div>

									<div class="form-group">
										<label>Palavras-chave</label>
										<textarea class="form-control" name="seo_palavras" maxlength="500" rows="2" placeholder="cabeleireiro, barbearia, salão de beleza"><?php echo hSeoPainel($seo_palavras) ?></textarea>
									</div>
								</div>
							</div>

							<div class="panel panel-default">
								<div class="panel-heading">
									<h4 class="panel-title">Indexação e compartilhamento</h4>
								</div>
								<div class="panel-body">
									<div class="row">
										<div class="col-md-4">
											<div class="form-group">
												<label>Autor</label>
												<input type="text" class="form-control" name="seo_autor" maxlength="100" value="<?php echo hSeoPainel($seo_autor) ?>">
											</div>
										</div>

										<div class="col-md-4">
											<div class="form-group">
												<label>Robots</label>
												<select class="form-control" name="seo_robots">
													<option value="index,follow" <?php if($seo_robots == 'index,follow'){ ?>selected<?php } ?>>index,follow</option>
													<option value="noindex,nofollow" <?php if($seo_robots == 'noindex,nofollow'){ ?>selected<?php } ?>>noindex,nofollow</option>
													<option value="index,nofollow" <?php if($seo_robots == 'index,nofollow'){ ?>selected<?php } ?>>index,nofollow</option>
													<option value="noindex,follow" <?php if($seo_robots == 'noindex,follow'){ ?>selected<?php } ?>>noindex,follow</option>
												</select>
											</div>
										</div>

										<div class="col-md-4">
											<div class="form-group">
												<label>Google Site Verification</label>
												<input type="text" class="form-control" name="seo_google_verification" maxlength="255" value="<?php echo hSeoPainel($seo_google_verification) ?>" placeholder="Cole o código ou a meta tag completa">
												<small>Pode colar só o código ou a tag completa.</small>
											</div>
										</div>
									</div>

									<div class="row">
										<div class="col-md-6">
											<div class="form-group">
												<label>URL Canônica</label>
												<input type="url" class="form-control" name="seo_canonical" maxlength="300" value="<?php echo hSeoPainel($seo_canonical) ?>" placeholder="https://jacycabeleireiro.com/">
											</div>
										</div>

										<div class="col-md-6">
											<div class="form-group">
												<label>Imagem de Compartilhamento</label>
												<input type="url" class="form-control" name="seo_og_image" maxlength="300" value="<?php echo hSeoPainel($seo_og_image) ?>" placeholder="https://jacycabeleireiro.com/sistema/img/logo.png">
											</div>
										</div>
									</div>
								</div>
							</div>

							<div class="panel panel-default">
								<div class="panel-heading">
									<h4 class="panel-title">Tags, pixels e códigos</h4>
								</div>
								<div class="panel-body">
									<div class="row">
										<div class="col-md-6">
											<div class="form-group">
												<label>Google Analytics / GA4</label>
												<input type="text" class="form-control" name="seo_google_analytics" value="<?php echo hSeoPainel($seo_google_analytics) ?>" placeholder="G-XXXXXXXXXX">
											</div>
										</div>

										<div class="col-md-6">
											<div class="form-group">
												<label>Google Tag Manager</label>
												<input type="text" class="form-control" name="seo_google_tag_manager" value="<?php echo hSeoPainel($seo_google_tag_manager) ?>" placeholder="GTM-XXXXXXX">
											</div>
										</div>
									</div>

									<div class="form-group">
										<label>Código extra dentro do &lt;head&gt;</label>
										<textarea class="form-control" name="seo_head_extra" rows="6" placeholder="Cole aqui códigos extras do Google, Meta ou verificação que precisam ficar antes do fechamento do head."><?php echo hSeoPainel($seo_head_extra) ?></textarea>
									</div>

									<div class="form-group">
										<label>Código extra após abrir o &lt;body&gt;</label>
										<textarea class="form-control" name="seo_body_extra" rows="6" placeholder="Cole aqui códigos que precisam ficar logo depois da abertura do body."><?php echo hSeoPainel($seo_body_extra) ?></textarea>
									</div>
								</div>
							</div>

							<div class="panel panel-default">
								<div class="panel-heading">
									<h4 class="panel-title"><i class="fa fa-shield"></i> Chaves de reCAPTCHA</h4>
								</div>
								<div class="panel-body">
									<div class="row">
										<div class="col-md-6">
											<div class="form-group">
												<label>Tipo reCAPTCHA ativo</label>
												<select class="form-control" name="recaptcha_tipo" id="recaptcha_tipo">
													<option value="v3" <?php if($recaptcha_tipo == 'v3'){ ?>selected<?php } ?>>reCAPTCHA v3 invisível</option>
													<option value="v2" <?php if($recaptcha_tipo == 'v2'){ ?>selected<?php } ?>>Caixa de verificação v2</option>
												</select>
											</div>
										</div>

										<div class="col-md-6">
											<div class="alert alert-info" style="margin-top:25px;margin-bottom:0;">
												Cadastre as chaves v2 e v3. O tipo ativo define qual proteção será usada nas páginas que carregam o reCAPTCHA.
											</div>
										</div>
									</div>

									<div class="row">
										<div class="col-md-6">
											<div class="form-group">
												<label>Chave do Site reCAPTCHA v2</label>
												<input type="text" class="form-control" name="recaptcha_site_key_v2" maxlength="255" value="<?php echo hSeoPainel($recaptcha_site_key_v2) ?>" placeholder="Chave pública do site v2">
											</div>
										</div>

										<div class="col-md-6">
											<div class="form-group">
												<label>Chave Secreta reCAPTCHA v2</label>
												<input type="text" class="form-control" name="recaptcha_secret_key_v2" maxlength="255" value="<?php echo hSeoPainel($recaptcha_secret_key_v2) ?>" placeholder="Chave secreta v2">
											</div>
										</div>
									</div>

									<div class="row">
										<div class="col-md-6">
											<div class="form-group">
												<label>Chave do Site reCAPTCHA v3</label>
												<input type="text" class="form-control" name="recaptcha_site_key_v3" maxlength="255" value="<?php echo hSeoPainel($recaptcha_site_key_v3) ?>" placeholder="Chave pública do site v3">
											</div>
										</div>

										<div class="col-md-6">
											<div class="form-group">
												<label>Chave Secreta reCAPTCHA v3</label>
												<input type="text" class="form-control" name="recaptcha_secret_key_v3" maxlength="255" value="<?php echo hSeoPainel($recaptcha_secret_key_v3) ?>" placeholder="Chave secreta v3">
											</div>
										</div>
									</div>
								</div>
							</div>
						</div>

						<div class="col-md-4">
							<div class="panel panel-default">
								<div class="panel-heading">
									<h4 class="panel-title"><i class="fa fa-google"></i> Prévia do Google</h4>
								</div>
								<div class="panel-body">
									<div style="border:1px solid #ddd;border-radius:8px;padding:15px;background:#fff;">
										<div style="font-size:13px;color:#202124;word-break:break-all;" id="preview_url"><?php echo hSeoPainel($seo_canonical) ?></div>
										<div style="font-size:20px;color:#1a0dab;line-height:1.25;margin-top:5px;" id="preview_titulo"><?php echo hSeoPainel($seo_titulo) ?></div>
										<div style="font-size:14px;color:#4d5156;margin-top:5px;" id="preview_descricao"><?php echo hSeoPainel($seo_descricao) ?></div>
									</div>
									<br>
									<div class="alert alert-info" style="margin-bottom:0;">
										Use título direto, descrição com benefício e cidade quando for SEO local.
									</div>
								</div>
							</div>

							<div class="panel panel-default">
								<div class="panel-heading">
									<h4 class="panel-title">Conferência rápida</h4>
								</div>
								<div class="panel-body">
									<p><b>Search Console:</b> <span id="status_verification"><?php echo $seo_google_verification != '' ? 'Configurado' : 'Pendente'; ?></span></p>
									<p><b>GTM:</b> <span id="status_gtm"><?php echo $seo_google_tag_manager != '' ? 'Configurado' : 'Pendente'; ?></span></p>
									<p><b>GA4:</b> <span id="status_ga4"><?php echo $seo_google_analytics != '' ? 'Configurado' : 'Pendente'; ?></span></p>
					<p><b>reCAPTCHA:</b> <span id="status_recaptcha"><?php echo (($recaptcha_tipo == 'v2' && $recaptcha_site_key_v2 != '' && $recaptcha_secret_key_v2 != '') || ($recaptcha_tipo == 'v3' && $recaptcha_site_key_v3 != '' && $recaptcha_secret_key_v3 != '')) ? 'Configurado' : 'Pendente'; ?></span></p>
								</div>
							</div>
						</div>
					</div>

					<div class="row">
						<div class="col-md-12" align="right">
							<button type="submit" class="btn btn-primary"><i class="fa fa-save"></i> Salvar SEO</button>
						</div>
					</div>

					<br>
					<small><div id="mensagem" align="center"></div></small>
				</form>
			</div>
		</div>
	</div>
</div>

<script type="text/javascript">
function atualizarPreviewSeo(){
	var titulo = $('#seo_titulo').val() || '';
	var descricao = $('#seo_descricao').val() || '';
	var canonical = $('input[name="seo_canonical"]').val() || '';
	var verification = $('input[name="seo_google_verification"]').val() || '';
	var gtm = $('input[name="seo_google_tag_manager"]').val() || '';
	var ga4 = $('input[name="seo_google_analytics"]').val() || '';
	var recaptchaTipo = $('#recaptcha_tipo').val() || 'v3';
	var recaptchaSiteV2 = $('input[name="recaptcha_site_key_v2"]').val() || '';
	var recaptchaSecretV2 = $('input[name="recaptcha_secret_key_v2"]').val() || '';
	var recaptchaSiteV3 = $('input[name="recaptcha_site_key_v3"]').val() || '';
	var recaptchaSecretV3 = $('input[name="recaptcha_secret_key_v3"]').val() || '';

	$('#cont_titulo').text(titulo.length);
	$('#cont_descricao').text(descricao.length);
	$('#preview_titulo').text(titulo || 'Título SEO');
	$('#preview_descricao').text(descricao || 'Descrição SEO');
	$('#preview_url').text(canonical || 'https://jacycabeleireiro.com/');
	$('#status_verification').text($.trim(verification) !== '' ? 'Configurado' : 'Pendente');
	$('#status_gtm').text($.trim(gtm) !== '' ? 'Configurado' : 'Pendente');
	$('#status_ga4').text($.trim(ga4) !== '' ? 'Configurado' : 'Pendente');
	$('#status_ads').text($.trim(ads) !== '' ? 'Configurado' : 'Pendente');
	$('#status_pixel').text($.trim(pixel) !== '' ? 'Configurado' : 'Pendente');
	var recaptchaOk = (recaptchaTipo === 'v2' && $.trim(recaptchaSiteV2) !== '' && $.trim(recaptchaSecretV2) !== '') || (recaptchaTipo === 'v3' && $.trim(recaptchaSiteV3) !== '' && $.trim(recaptchaSecretV3) !== '');
	$('#status_recaptcha').text(recaptchaOk ? 'Configurado' : 'Pendente');
}

function codificarSeoPainel(texto){
	return btoa(unescape(encodeURIComponent(texto)));
}

function montarDadosSeoPainel(formulario){
	var dados = {};
	$(formulario).serializeArray().forEach(function(campo){
		dados[campo.name] = campo.value;
	});
	return dados;
}

$(document).ready(function(){
	atualizarPreviewSeo();
	$('#seo_titulo, #seo_descricao, input[name="seo_canonical"], input[name="seo_google_verification"], input[name="seo_google_tag_manager"], input[name="seo_google_analytics"], input[name="recaptcha_site_key_v2"], input[name="recaptcha_secret_key_v2"], input[name="recaptcha_site_key_v3"], input[name="recaptcha_secret_key_v3"]').on('input', atualizarPreviewSeo);
	$('#recaptcha_tipo').on('change', atualizarPreviewSeo);
});

$('#form-seo').submit(function(e){
	e.preventDefault();

	$('#mensagem').removeClass();
	$('#mensagem').addClass('text-muted');
	$('#mensagem').text('...Carregando!');

	var dadosSeo = montarDadosSeoPainel(this);
	var formData = new FormData();
	formData.append('dados_seo', codificarSeoPainel(JSON.stringify(dadosSeo)));

	$.ajax({
		url: <?php echo json_encode($bb_seo_salvar_url, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>,
		type: 'POST',
		data: formData,
		success: function(mensagem){
			$('#mensagem').text('');
			$('#mensagem').removeClass();

			if($.trim(mensagem) == 'Salvo com Sucesso'){
				$('#mensagem').addClass('text-success');
				$('#mensagem').text(mensagem);
			}else{
				$('#mensagem').addClass('text-danger');
				$('#mensagem').text(mensagem);
			}
		},
		error: function(){
			$('#mensagem').removeClass();
			$('#mensagem').addClass('text-danger');
			$('#mensagem').text('Erro ao salvar SEO. Verifique sua sessão e tente novamente.');
		},
		cache: false,
		contentType: false,
		processData: false
	});
});
</script>
