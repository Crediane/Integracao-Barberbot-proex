<?php
require_once __DIR__ . '/../_context_bootstrap.php';
$__bb_seo_salvar_slug_atual = strpos((string)session_name(), 'BB_TENANT') === 0
	? bb_pages_clean_slug($_SESSION['tenant_slug'] ?? '')
	: '';
$__bb_seo_salvar_loja_context = ($__bb_seo_salvar_slug_atual !== '');

if(@$_SESSION['id'] == ""){
	echo 'Sessão expirada. Faça login novamente!';
	exit();
}

if(@$_SESSION['nivel'] != 'Administrador'){
	echo 'Acesso negado!';
	exit();
}

if(isset($modo_teste) && $modo_teste == 'Sim'){
	echo 'Esse recurso não é possível alterar no modo de testes!';
	exit();
}

function colunaSeoSalvarExiste($pdo, $coluna){
	try{
		$query = $pdo->prepare("SHOW COLUMNS FROM `config` LIKE :coluna");
		$query->bindValue(':coluna', $coluna);
		$query->execute();
		return $query->rowCount() > 0;
	}catch(Exception $e){
		return false;
	}
}

function garantirColunaSeoSalvar($pdo, $coluna, $tipo){
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
		if(!colunaSeoSalvarExiste($pdo, $coluna)){
			$padrao = stripos($tipo, 'text') !== false ? '' : ' DEFAULT NULL';
			$pdo->query("ALTER TABLE `config` ADD COLUMN `$coluna` $tipo$padrao");
		}
		return true;
	}catch(Exception $e){
		return false;
	}
}

function limparCodigoSeoSalvar($texto){
	$texto = trim((string)$texto);
	$texto = str_replace(array("\r", "\n", "\t"), '', $texto);
	return $texto;
}

function limitarTextoSeoSalvar($texto, $limite){
	$texto = trim((string)$texto);
	if(function_exists('mb_substr')){
		return mb_substr($texto, 0, $limite, 'UTF-8');
	}
	return substr($texto, 0, $limite);
}

function extrairGoogleVerificationSeoSalvar($texto){
	$texto = limparCodigoSeoSalvar($texto);

	if($texto == ''){
		return '';
	}

	if(stripos($texto, 'google-site-verification') !== false && preg_match('/content=["\']([^"\']+)["\']/i', $texto, $resultado)){
		return limitarTextoSeoSalvar(trim($resultado[1]), 255);
	}

	$texto = str_replace(array('<', '>', '"', "'"), '', $texto);
	return limitarTextoSeoSalvar(trim($texto), 255);
}

function limparGoogleAnalyticsSeoSalvar($texto){
	$texto = limparCodigoSeoSalvar($texto);

	if($texto == ''){
		return '';
	}

	if(preg_match('/G-[A-Z0-9]+/i', $texto, $resultado)){
		return strtoupper($resultado[0]);
	}

	$texto = strtoupper(preg_replace('/[^A-Z0-9\-]/i', '', $texto));
	return preg_match('/^G-[A-Z0-9]+$/', $texto) ? $texto : '';
}

function limparGoogleTagManagerSeoSalvar($texto){
	$texto = limparCodigoSeoSalvar($texto);

	if($texto == ''){
		return '';
	}

	if(preg_match('/GTM-[A-Z0-9]+/i', $texto, $resultado)){
		return strtoupper($resultado[0]);
	}

	$texto = strtoupper(preg_replace('/[^A-Z0-9\-]/i', '', $texto));
	return preg_match('/^GTM-[A-Z0-9]+$/', $texto) ? $texto : '';
}

function limparGoogleAdsSeoSalvar($texto){
	$texto = limparCodigoSeoSalvar($texto);

	if($texto == ''){
		return '';
	}

	if(preg_match('/AW-[0-9]+/i', $texto, $resultado)){
		return strtoupper($resultado[0]);
	}

	$texto = strtoupper(preg_replace('/[^A-Z0-9\-]/i', '', $texto));
	return preg_match('/^AW-[0-9]+$/', $texto) ? $texto : '';
}

function limparMetaPixelSeoSalvar($texto){
	$texto = limparCodigoSeoSalvar($texto);

	if($texto == ''){
		return '';
	}

	if(preg_match('/[0-9]{6,}/', $texto, $resultado)){
		return $resultado[0];
	}

	return '';
}

function limparHtmlExtraSeoSalvar($texto){
	$texto = trim((string)$texto);
	$texto = str_ireplace(array('<?php', '<?=', '<?', '?>'), '', $texto);
	return $texto;
}

function limparRecaptchaTipoSeoSalvar($texto){
	$texto = strtolower(trim((string)$texto));
	return $texto === 'v2' ? 'v2' : 'v3';
}

function limparRecaptchaChaveSeoSalvar($texto){
	$texto = trim((string)$texto);
	$texto = preg_replace('/[^a-zA-Z0-9_\-]/', '', $texto);
	return limitarTextoSeoSalvar($texto, 255);
}

function decodificarPayloadSeoSalvar($payload){
	$payload = trim((string)$payload);

	if($payload == ''){
		return array();
	}

	$payload = str_replace(' ', '+', $payload);
	$json = base64_decode($payload, true);

	if($json === false){
		return array();
	}

	$dados = json_decode($json, true);

	if(!is_array($dados)){
		return array();
	}

	return $dados;
}

garantirColunaSeoSalvar($pdo, 'seo_titulo', 'varchar(255)');
garantirColunaSeoSalvar($pdo, 'seo_descricao', 'varchar(500)');
garantirColunaSeoSalvar($pdo, 'seo_palavras', 'varchar(500)');
garantirColunaSeoSalvar($pdo, 'seo_autor', 'varchar(100)');
garantirColunaSeoSalvar($pdo, 'seo_robots', 'varchar(50)');
garantirColunaSeoSalvar($pdo, 'seo_canonical', 'varchar(300)');
garantirColunaSeoSalvar($pdo, 'seo_og_image', 'varchar(300)');
garantirColunaSeoSalvar($pdo, 'seo_google_verification', 'varchar(255)');
garantirColunaSeoSalvar($pdo, 'seo_google_analytics', 'varchar(80)');
garantirColunaSeoSalvar($pdo, 'seo_google_tag_manager', 'varchar(80)');
garantirColunaSeoSalvar($pdo, 'seo_head_extra', 'mediumtext');
garantirColunaSeoSalvar($pdo, 'seo_body_extra', 'mediumtext');
garantirColunaSeoSalvar($pdo, 'recaptcha_tipo', 'varchar(20)');
garantirColunaSeoSalvar($pdo, 'recaptcha_site_key_v2', 'varchar(255)');
garantirColunaSeoSalvar($pdo, 'recaptcha_secret_key_v2', 'varchar(255)');
garantirColunaSeoSalvar($pdo, 'recaptcha_site_key_v3', 'varchar(255)');
garantirColunaSeoSalvar($pdo, 'recaptcha_secret_key_v3', 'varchar(255)');

$dados_seo = decodificarPayloadSeoSalvar(@$_POST['dados_seo']);

if(empty($dados_seo)){
	echo 'Dados do SEO não foram recebidos corretamente!';
	exit();
}

$query_config = $pdo->query("SELECT id FROM config ORDER BY id ASC LIMIT 1");
$res_config = $query_config->fetchAll(PDO::FETCH_ASSOC);

if(@count($res_config) == 0){
	echo 'Configuração principal não encontrada!';
	exit();
}

$id_config = $res_config[0]['id'];
$seo_titulo = limitarTextoSeoSalvar(@$dados_seo['seo_titulo'], 255);
$seo_descricao = limitarTextoSeoSalvar(@$dados_seo['seo_descricao'], 500);
$seo_palavras = limitarTextoSeoSalvar(@$dados_seo['seo_palavras'], 500);
$seo_autor = limitarTextoSeoSalvar(@$dados_seo['seo_autor'], 100);
$seo_robots = limitarTextoSeoSalvar(@$dados_seo['seo_robots'], 50);
$seo_canonical = limitarTextoSeoSalvar(@$dados_seo['seo_canonical'], 300);
$seo_og_image = limitarTextoSeoSalvar(@$dados_seo['seo_og_image'], 300);
$seo_google_verification = extrairGoogleVerificationSeoSalvar(@$dados_seo['seo_google_verification']);
$seo_google_analytics = limparGoogleAnalyticsSeoSalvar(@$dados_seo['seo_google_analytics']);
$seo_google_tag_manager = limparGoogleTagManagerSeoSalvar(@$dados_seo['seo_google_tag_manager']);


$seo_head_extra = limparHtmlExtraSeoSalvar(@$dados_seo['seo_head_extra']);
$seo_body_extra = limparHtmlExtraSeoSalvar(@$dados_seo['seo_body_extra']);
$recaptcha_tipo = limparRecaptchaTipoSeoSalvar(@$dados_seo['recaptcha_tipo']);
$recaptcha_site_key_v2 = limparRecaptchaChaveSeoSalvar(@$dados_seo['recaptcha_site_key_v2']);
$recaptcha_secret_key_v2 = limparRecaptchaChaveSeoSalvar(@$dados_seo['recaptcha_secret_key_v2']);
$recaptcha_site_key_v3 = limparRecaptchaChaveSeoSalvar(@$dados_seo['recaptcha_site_key_v3']);
$recaptcha_secret_key_v3 = limparRecaptchaChaveSeoSalvar(@$dados_seo['recaptcha_secret_key_v3']);

if($seo_titulo == ''){
	echo 'Informe o título SEO!';
	exit();
}

if($seo_descricao == ''){
	echo 'Informe a descrição SEO!';
	exit();
}

$robots_validos = array('index,follow', 'noindex,nofollow', 'index,nofollow', 'noindex,follow');
if(!in_array($seo_robots, $robots_validos, true)){
	$seo_robots = 'index,follow';
}

try{
	$query = $pdo->prepare("UPDATE config SET 
		seo_titulo = :seo_titulo,
		seo_descricao = :seo_descricao,
		seo_palavras = :seo_palavras,
		seo_autor = :seo_autor,
		seo_robots = :seo_robots,
		seo_canonical = :seo_canonical,
		seo_og_image = :seo_og_image,
		seo_google_verification = :seo_google_verification,
		seo_google_analytics = :seo_google_analytics,
		seo_google_tag_manager = :seo_google_tag_manager,
		seo_google_ads = :seo_google_ads,
		seo_meta_pixel = :seo_meta_pixel,
		seo_head_extra = :seo_head_extra,
		seo_body_extra = :seo_body_extra,
		recaptcha_tipo = :recaptcha_tipo,
		recaptcha_site_key_v2 = :recaptcha_site_key_v2,
		recaptcha_secret_key_v2 = :recaptcha_secret_key_v2,
		recaptcha_site_key_v3 = :recaptcha_site_key_v3,
		recaptcha_secret_key_v3 = :recaptcha_secret_key_v3
		WHERE id = :id_config");
	$query->bindValue(':seo_titulo', $seo_titulo);
	$query->bindValue(':seo_descricao', $seo_descricao);
	$query->bindValue(':seo_palavras', $seo_palavras);
	$query->bindValue(':seo_autor', $seo_autor);
	$query->bindValue(':seo_robots', $seo_robots);
	$query->bindValue(':seo_canonical', $seo_canonical);
	$query->bindValue(':seo_og_image', $seo_og_image);
	$query->bindValue(':seo_google_verification', $seo_google_verification);
	$query->bindValue(':seo_google_analytics', $seo_google_analytics);
	$query->bindValue(':seo_google_tag_manager', $seo_google_tag_manager);
	$query->bindValue(':seo_google_ads', $seo_google_ads);
	$query->bindValue(':seo_meta_pixel', $seo_meta_pixel);
	$query->bindValue(':seo_head_extra', $seo_head_extra);
	$query->bindValue(':seo_body_extra', $seo_body_extra);
	$query->bindValue(':recaptcha_tipo', $recaptcha_tipo);
	$query->bindValue(':recaptcha_site_key_v2', $recaptcha_site_key_v2);
	$query->bindValue(':recaptcha_secret_key_v2', $recaptcha_secret_key_v2);
	$query->bindValue(':recaptcha_site_key_v3', $recaptcha_site_key_v3);
	$query->bindValue(':recaptcha_secret_key_v3', $recaptcha_secret_key_v3);
	$query->bindValue(':id_config', $id_config);
	$query->execute();
}catch(Exception $e){
	echo 'Erro ao salvar SEO. Verifique a tabela config e tente novamente.';
	exit();
}

echo 'Salvo com Sucesso';
?>
