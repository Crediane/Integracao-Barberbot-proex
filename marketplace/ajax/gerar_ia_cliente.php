<?php
header('Content-Type: application/json; charset=utf-8');

ini_set('display_errors', 0);
ini_set('log_errors', 1);

$bb_ajax_context_mode = 'tenant';
$bb_ajax_context_response = 'json';
require_once __DIR__ . '/_context_bootstrap.php';

function responderErro($mensagem, $codigo = 400){
	http_response_code($codigo);
	echo json_encode(array(
		'status' => 'Erro',
		'mensagem' => $mensagem
	), JSON_UNESCAPED_UNICODE);
	exit();
}


function responderSucesso($dados){
	http_response_code(200);
	echo json_encode($dados, JSON_UNESCAPED_UNICODE);
	exit();
}

function bbNomeArquivoSimulacaoSeguro($nomeArquivo){
	$nomeArquivo = basename((string)$nomeArquivo);
	if ($nomeArquivo === '') {
		return '';
	}

	if (!preg_match('/^[a-zA-Z0-9_.-]+$/', $nomeArquivo)) {
		return '';
	}

	return $nomeArquivo;
}

function bbMontarUrlArquivoSimulacaoCliente($nomeArquivo){
	$nomeArquivo = bbNomeArquivoSimulacaoSeguro($nomeArquivo);
	if ($nomeArquivo === '') {
		responderErro('Nome de arquivo inválido para montar URL pública', 500);
	}

	$proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
	if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') {
		$proto = 'https';
	}

	$host = (string)($_SERVER['HTTP_HOST'] ?? '');
	if ($host === '') {
		responderErro('Host inválido para montar URL pública', 500);
	}

	return $proto . '://' . $host . '/sistema/painel/images/simulacoes/' . rawurlencode($nomeArquivo) . '?v=' . time();
}

function bbLerCacheSimulacaoCliente($arquivoCache, $pasta){
	if (!is_file($arquivoCache)) {
		return false;
	}

	$conteudoCache = @file_get_contents($arquivoCache);
	if ($conteudoCache === false || trim($conteudoCache) === '') {
		@unlink($arquivoCache);
		return false;
	}

	$dadosCache = json_decode($conteudoCache, true);
	if (!is_array($dadosCache)) {
		@unlink($arquivoCache);
		return false;
	}

	$nomeOriginal = bbNomeArquivoSimulacaoSeguro($dadosCache['foto_original'] ?? '');
	$nomeSimulada = bbNomeArquivoSimulacaoSeguro($dadosCache['imagem_simulada'] ?? '');

	if ($nomeOriginal === '' || $nomeSimulada === '') {
		@unlink($arquivoCache);
		return false;
	}

	if (!is_file($pasta . $nomeOriginal) || !is_file($pasta . $nomeSimulada)) {
		@unlink($arquivoCache);
		return false;
	}

	return array(
		'foto_original' => $nomeOriginal,
		'imagem_simulada' => $nomeSimulada
	);
}

function bbResponderCacheSimulacaoCliente($dadosCache){
	$urlOriginal = bbMontarUrlArquivoSimulacaoCliente($dadosCache['foto_original']);
	$urlSimulada = bbMontarUrlArquivoSimulacaoCliente($dadosCache['imagem_simulada']);

	responderSucesso(array(
		'status' => 'Sucesso',
		'mensagem' => 'Simulação já gerada para esta imagem',
		'foto_original' => $dadosCache['foto_original'],
		'imagem_simulada' => $dadosCache['imagem_simulada'],
		'url_original' => $urlOriginal,
		'url_simulada' => $urlSimulada,
		'ja_existia' => 'Sim'
	));
}

/*
|----------------------------------------------------------
| Dados do cliente usados também para evitar repetição
|----------------------------------------------------------
*/
$nomeCliente = isset($_POST['nome']) ? trim((string)$_POST['nome']) : '';
$telefoneCliente = isset($_POST['telefone']) ? trim((string)$_POST['telefone']) : '';
$telefoneNumeros = preg_replace('/[^\d]+/', '', $telefoneCliente);

if ($telefoneNumeros !== '' && strpos($telefoneNumeros, '55') !== 0) {
	$telefoneNumeros = '55' . $telefoneNumeros;
}

/*
|----------------------------------------------------------
| Validar upload
|----------------------------------------------------------
*/
if (!isset($_FILES['foto'])) {
	responderErro('Nenhuma imagem enviada');
}

if (!isset($_FILES['foto']['tmp_name']) || !is_uploaded_file($_FILES['foto']['tmp_name'])) {
	responderErro('Arquivo inválido');
}

$ext = strtolower(pathinfo((string)$_FILES['foto']['name'], PATHINFO_EXTENSION));
if (!in_array($ext, array('jpg', 'jpeg', 'png', 'webp'))) {
	responderErro('Formato de imagem não permitido');
}

$mime = 'image/jpeg';
if ($ext === 'png') {
	$mime = 'image/png';
} elseif ($ext === 'webp') {
	$mime = 'image/webp';
}

$hashArquivoOriginal = @hash_file('sha256', $_FILES['foto']['tmp_name']);
if ($hashArquivoOriginal === false || $hashArquivoOriginal === '') {
	responderErro('Não foi possível validar a imagem enviada');
}

$chaveRepeticao = hash('sha256', $tenant . '|' . $telefoneNumeros . '|' . strtolower($nomeCliente) . '|' . $hashArquivoOriginal);

/*
|----------------------------------------------------------
| Pasta local de simulações
|----------------------------------------------------------
*/
$pasta = __DIR__ . '/../sistema/painel/images/simulacoes/';

if (!is_dir($pasta)) {
	responderErro('Pasta de simulações não encontrada');
}

if (!is_writable($pasta)) {
	responderErro('Pasta de simulações sem permissão de escrita');
}

/*
|----------------------------------------------------------
| Evitar imagens repetidas para a mesma foto do mesmo cliente
|----------------------------------------------------------
*/
$arquivoCache = $pasta . 'cliente_cache_' . $chaveRepeticao . '.json';
$arquivoLock = $pasta . 'cliente_lock_' . $chaveRepeticao . '.lock';

$dadosCacheExistente = bbLerCacheSimulacaoCliente($arquivoCache, $pasta);
if ($dadosCacheExistente !== false) {
	bbResponderCacheSimulacaoCliente($dadosCacheExistente);
}

$ponteiroLock = @fopen($arquivoLock, 'c');
if (!$ponteiroLock) {
	responderErro('Não foi possível iniciar o controle da simulação', 500);
}

if (!flock($ponteiroLock, LOCK_EX | LOCK_NB)) {
	for ($tentativaCache = 0; $tentativaCache < 20; $tentativaCache++) {
		usleep(500000);
		clearstatcache(true, $arquivoCache);
		$dadosCacheExistente = bbLerCacheSimulacaoCliente($arquivoCache, $pasta);

		if ($dadosCacheExistente !== false) {
			@fclose($ponteiroLock);
			bbResponderCacheSimulacaoCliente($dadosCacheExistente);
		}
	}

	@fclose($ponteiroLock);
	responderErro('Esta imagem já está sendo processada. Aguarde alguns segundos e confira o resultado antes de tentar novamente.', 409);
}

register_shutdown_function(function() use ($ponteiroLock, $arquivoLock) {
	@flock($ponteiroLock, LOCK_UN);
	@fclose($ponteiroLock);
	@unlink($arquivoLock);
});

$dadosCacheExistente = bbLerCacheSimulacaoCliente($arquivoCache, $pasta);
if ($dadosCacheExistente !== false) {
	bbResponderCacheSimulacaoCliente($dadosCacheExistente);
}

/*
|----------------------------------------------------------
| Salvar imagem original no servidor
|----------------------------------------------------------
*/
$nomeOriginal = 'cliente_original_' . md5(uniqid((string)mt_rand(), true)) . '.' . $ext;
$caminhoOriginal = $pasta . $nomeOriginal;

if (!move_uploaded_file($_FILES['foto']['tmp_name'], $caminhoOriginal)) {
	responderErro('Erro ao salvar imagem original');
}

if (!file_exists($caminhoOriginal)) {
	responderErro('Arquivo original não foi gravado');
}

/*
|----------------------------------------------------------
| Buscar OpenAI Key no banco individual do contexto atual
|----------------------------------------------------------
*/
$queryCfg = $pdo->query("SELECT * FROM config LIMIT 1");
$config = $queryCfg->fetch(PDO::FETCH_ASSOC);

if (!$config) {
	@unlink($caminhoOriginal);
	responderErro('Configuração não encontrada');
}

$openai_key = trim((string)($config['openai_key'] ?? ''));
if ($openai_key === '') {
	@unlink($caminhoOriginal);
	responderErro('OpenAI Key não cadastrada no sistema');
}

/*
|----------------------------------------------------------
| Preparar data URL
|----------------------------------------------------------
*/
$binarioOriginal = @file_get_contents($caminhoOriginal);
if ($binarioOriginal === false || $binarioOriginal === '') {
	@unlink($caminhoOriginal);
	responderErro('Erro ao ler imagem original');
}

$dataUrlOriginal = 'data:' . $mime . ';base64,' . base64_encode($binarioOriginal);

/*
|----------------------------------------------------------
| Prompt do cliente final
|----------------------------------------------------------
*/
$prompt = "Create a realistic capillary prosthesis simulation based on the uploaded photo. ";
$prompt .= "STRICT RULES: preserve exactly the same person identity. ";
$prompt .= "Keep exactly the same face, same age, same facial features, same forehead, same expression, same beard, same eyebrows, same ears, same neck, same body proportion, same camera angle, same framing, same pose, same background and same lighting. ";
$prompt .= "Do not alter clothes. ";
$prompt .= "Do not alter skin tone. ";
$prompt .= "Do not retouch the skin. ";
$prompt .= "Do not make the person younger. ";
$prompt .= "Do not beautify the face. ";
$prompt .= "Do not change the beard color or beard shape. ";
$prompt .= "Do not change the eyebrow color or eyebrow shape. ";
$prompt .= "Do not change the background. ";
$prompt .= "Do not change the shoulders or clothing texture. ";
$prompt .= "ONLY modify the bald area of the scalp to simulate a professional hair prosthesis. ";
$prompt .= "The simulation must affect only the hair/prosthesis area. ";
$prompt .= "The prosthesis hair color must MATCH EXACTLY the visible natural side hair already present in the photo. ";
$prompt .= "Use the side hair as the ONLY color reference. ";
$prompt .= "If the existing hair is gray, white, salt-and-pepper, dark gray, brown-gray or mixed, the prosthesis must keep exactly the same tone and same mixture proportion. ";
$prompt .= "Preserve the exact same percentage of gray and white strands already visible in the remaining hair. ";
$prompt .= "Do not create darker hair than the side hair. ";
$prompt .= "Do not create black hair if the side hair is gray or mixed gray. ";
$prompt .= "Do not create brown hair if the side hair is gray or white. ";
$prompt .= "Do not warm the tone. ";
$prompt .= "Do not increase saturation. ";
$prompt .= "Do not recolor the existing hair. ";
$prompt .= "Do not recolor the prosthesis to an idealized younger tone. ";
$prompt .= "The prosthesis must blend naturally with the exact same color, tone, texture, density and direction of the existing side hair. ";
$prompt .= "Keep the same natural aging appearance of the hair. ";
$prompt .= "Do not change the skin, clothes or any other part of the image. ";
$prompt .= "Result must look like the same real person with a realistic capillary prosthesis preview only on the scalp, matching exactly the current side hair color.";

/*
|----------------------------------------------------------
| Chamada da OpenAI
|----------------------------------------------------------
*/
$payload = array(
	'model' => 'gpt-image-1',
	'prompt' => $prompt,
	'images' => array(
		array(
			'image_url' => $dataUrlOriginal
		)
	),
	'size' => '1024x1536',
	'quality' => 'medium',
	'output_format' => 'png'
);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/images/edits');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 180);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	'Content-Type: application/json',
	'Authorization: Bearer ' . $openai_key
));
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));

$resposta = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$erroCurl = curl_error($ch);
curl_close($ch);

if ($erroCurl !== '') {
	@unlink($caminhoOriginal);
	responderErro('Erro CURL: ' . $erroCurl, 500);
}

if ($resposta === '' || $resposta === false) {
	@unlink($caminhoOriginal);
	responderErro('Resposta vazia da IA', 500);
}

$json = json_decode($resposta, true);
if (!is_array($json)) {
	@unlink($caminhoOriginal);
	responderErro('Resposta inválida da IA', 500);
}

if ($http !== 200) {
	$mensagemErro = 'Erro ao gerar imagem';
	if (isset($json['error']['message']) && $json['error']['message'] !== '') {
		$mensagemErro = $json['error']['message'];
	}
	@unlink($caminhoOriginal);
	responderErro($mensagemErro, $http > 0 ? $http : 500);
}

if (!isset($json['data'][0]['b64_json']) || $json['data'][0]['b64_json'] === '') {
	@unlink($caminhoOriginal);
	responderErro('A IA não retornou imagem simulada', 500);
}

$imagemBinaria = base64_decode((string)$json['data'][0]['b64_json']);
if ($imagemBinaria === false || $imagemBinaria === '') {
	@unlink($caminhoOriginal);
	responderErro('Erro ao decodificar a imagem simulada', 500);
}

/*
|----------------------------------------------------------
| Salvar imagem simulada
|----------------------------------------------------------
*/
$nomeSimulada = 'cliente_simulada_' . md5(uniqid((string)mt_rand(), true)) . '.png';
$caminhoSimulada = $pasta . $nomeSimulada;

if (@file_put_contents($caminhoSimulada, $imagemBinaria) === false) {
	@unlink($caminhoOriginal);
	responderErro('Erro ao salvar imagem simulada', 500);
}

if (!file_exists($caminhoSimulada)) {
	@unlink($caminhoOriginal);
	responderErro('Arquivo da imagem simulada não foi gravado', 500);
}

/*
|----------------------------------------------------------
| Montar URLs públicas
|----------------------------------------------------------
*/
$urlOriginal = bbMontarUrlArquivoSimulacaoCliente($nomeOriginal);
$urlSimulada = bbMontarUrlArquivoSimulacaoCliente($nomeSimulada);

/*
|----------------------------------------------------------
| Salvar cache da simulação para não repetir imagem
|----------------------------------------------------------
*/
$dadosCacheSalvar = array(
	'foto_original' => $nomeOriginal,
	'imagem_simulada' => $nomeSimulada,
	'hash_arquivo' => $hashArquivoOriginal,
	'telefone_hash' => $telefoneNumeros !== '' ? hash('sha256', $telefoneNumeros) : '',
	'tenant' => $tenant,
	'criado_em' => date('Y-m-d H:i:s')
);

@file_put_contents($arquivoCache, json_encode($dadosCacheSalvar, JSON_UNESCAPED_UNICODE), LOCK_EX);


/*
|----------------------------------------------------------
| Enviar imagem simulada por WhatsApp
| segue padrão marketing_foto.php
|----------------------------------------------------------
*/
if ($telefoneCliente !== '') {
	if ($telefoneNumeros !== '') {
		$nomeSistema = trim((string)($config['nome'] ?? ''));
		if ($nomeSistema === '') {
			$nomeSistema = 'BarberBot';
		}

		$mensagem = "👋 *Olá";
		if ($nomeCliente !== '') {
			$mensagem .= " {$nomeCliente}";
		}
		$mensagem .= "*\n\n";
		$mensagem .= "Sua *simulação de prótese capilar* foi gerada com sucesso no *{$nomeSistema}*.\n\n";
		$mensagem .= "🖼️ Estou enviando sua imagem simulada abaixo.";

		$numeros_formatados = $telefoneNumeros;
		$url_arquivo = preg_replace('/\?v=\d+$/', '', $urlSimulada);

		$instancia = trim((string)($config['instancia'] ?? ''));
		$token = trim((string)($config['token'] ?? ''));

		if ($instancia !== '' && $token !== '') {
			$arquivoEnvioFoto = $_SERVER['DOCUMENT_ROOT'] . '/ajax/marketing_foto.php';
			if (is_file($arquivoEnvioFoto)) {
				require $arquivoEnvioFoto;
			}
		}
	}
}

/*
|----------------------------------------------------------
| Retorno final
|----------------------------------------------------------
*/
responderSucesso(array(
	'status' => 'Sucesso',
	'mensagem' => 'Simulação gerada com sucesso',
	'foto_original' => $nomeOriginal,
	'imagem_simulada' => $nomeSimulada,
	'url_original' => $urlOriginal,
	'url_simulada' => $urlSimulada,
	'ja_existia' => 'Nao'
));
?>