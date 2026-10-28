<?php
require_once __DIR__ . '/../_context_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

require_once(__DIR__ . '/../../verificar.php');
require_once(__DIR__ . '/../../../conexao.php');

$tabela = 'analise_capilar';
$id = isset($_POST['id']) ? trim($_POST['id']) : '';

if($id == ""){
	echo json_encode(array(
		'status' => 'Erro',
		'mensagem' => 'ID não informado'
	), JSON_UNESCAPED_UNICODE);
	exit();
}

$query = $pdo->prepare("SELECT * FROM $tabela WHERE id = :id LIMIT 1");
$query->bindValue(":id", $id);
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);

if(count($res) == 0){
	echo json_encode(array(
		'status' => 'Erro',
		'mensagem' => 'Registro não encontrado'
	), JSON_UNESCAPED_UNICODE);
	exit();
}

$foto = isset($res[0]['foto']) ? trim($res[0]['foto']) : '';
$grau_falha_banco = isset($res[0]['grau_falha']) ? trim($res[0]['grau_falha']) : '';

if($foto == ""){
	echo json_encode(array(
		'status' => 'Erro',
		'mensagem' => 'Nenhuma foto enviada para análise'
	), JSON_UNESCAPED_UNICODE);
	exit();
}

$pasta = __DIR__ . '/../../images/analises/';
$caminho_foto = $pasta . $foto;

if(!is_dir($pasta)){
	echo json_encode(array(
		'status' => 'Erro',
		'mensagem' => 'Pasta de imagens da análise não encontrada'
	), JSON_UNESCAPED_UNICODE);
	exit();
}

if(!file_exists($caminho_foto)){
	echo json_encode(array(
		'status' => 'Erro',
		'mensagem' => 'Arquivo da foto não encontrado'
	), JSON_UNESCAPED_UNICODE);
	exit();
}

if(!is_file($caminho_foto)){
	echo json_encode(array(
		'status' => 'Erro',
		'mensagem' => 'Arquivo inválido para análise'
	), JSON_UNESCAPED_UNICODE);
	exit();
}

$query2 = $pdo->query("SELECT * FROM config LIMIT 1");
$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);

if(count($res2) == 0){
	echo json_encode(array(
		'status' => 'Erro',
		'mensagem' => 'Configurações não encontradas'
	), JSON_UNESCAPED_UNICODE);
	exit();
}

$openai_key = isset($res2[0]['openai_key']) ? trim($res2[0]['openai_key']) : '';

if($openai_key == ""){
	echo json_encode(array(
		'status' => 'Erro',
		'mensagem' => 'OpenAI Key não cadastrada'
	), JSON_UNESCAPED_UNICODE);
	exit();
}

$extensao = strtolower(pathinfo($caminho_foto, PATHINFO_EXTENSION));
$mime = 'image/jpeg';

if($extensao == 'png'){
	$mime = 'image/png';
}else if($extensao == 'webp'){
	$mime = 'image/webp';
}else if($extensao == 'jpg' || $extensao == 'jpeg'){
	$mime = 'image/jpeg';
}

$conteudo_imagem = @file_get_contents($caminho_foto);

if($conteudo_imagem === false){
	echo json_encode(array(
		'status' => 'Erro',
		'mensagem' => 'Não foi possível ler a imagem para análise'
	), JSON_UNESCAPED_UNICODE);
	exit();
}

$imagem_base64 = base64_encode($conteudo_imagem);
$imagem_data_url = "data:" . $mime . ";base64," . $imagem_base64;

$prompt_final = "Você é um especialista em análise capilar para prótese capilar.";
$prompt_final .= "\nAnalise a imagem enviada do couro cabeludo.";
$prompt_final .= "\nResponda somente em JSON válido.";
$prompt_final .= "\nRetorne exatamente estas chaves:";
$prompt_final .= "\ncor_detectada";
$prompt_final .= "\ndensidade_detectada";
$prompt_final .= "\ngrau_falha";
$prompt_final .= "\nsugestao_protese";

$prompt_final .= "\n\nRegras obrigatórias:";
$prompt_final .= "\n1. Não escreva explicações.";
$prompt_final .= "\n2. Não escreva markdown.";
$prompt_final .= "\n3. Não escreva texto fora do JSON.";
$prompt_final .= "\n4. cor_detectada deve ser SOMENTE um dos códigos abaixo:";
$prompt_final .= "\n#1B, #1B10, #1B20, #1B30, #1B40, #1B50, #1B60, #2, #210, #220, #230, #240, #250, #3, #310, #320, #330, #340, #4, #410, #420, #430, #440, #5, #6";
$prompt_final .= "\n5. Interprete obrigatoriamente os códigos com branco/grisalho da seguinte forma:";
$prompt_final .= "\n#1B10 = preto natural + 10% branco";
$prompt_final .= "\n#1B20 = preto natural + 20% branco";
$prompt_final .= "\n#1B30 = preto natural + 30% branco";
$prompt_final .= "\n#1B40 = preto natural + 40% branco";
$prompt_final .= "\n#1B50 = preto natural + 50% branco";
$prompt_final .= "\n#1B60 = preto natural + 60% branco";
$prompt_final .= "\n#210 = castanho escuro + 10% branco";
$prompt_final .= "\n#220 = castanho escuro + 20% branco";
$prompt_final .= "\n#230 = castanho escuro + 30% branco";
$prompt_final .= "\n#240 = castanho escuro + 40% branco";
$prompt_final .= "\n#250 = castanho escuro + 50% branco";
$prompt_final .= "\n#310 = castanho médio + 10% branco";
$prompt_final .= "\n#320 = castanho médio + 20% branco";
$prompt_final .= "\n#330 = castanho médio + 30% branco";
$prompt_final .= "\n#340 = castanho médio + 40% branco";
$prompt_final .= "\n#410 = castanho + 10% branco";
$prompt_final .= "\n#420 = castanho + 20% branco";
$prompt_final .= "\n#430 = castanho + 30% branco";
$prompt_final .= "\n#440 = castanho + 40% branco";
$prompt_final .= "\n6. É obrigatório respeitar o grisalho visível da foto. Se houver branco/grisalho, retornar o código com percentual de branco correspondente da lista permitida.";
$prompt_final .= "\n7. Não simplifique uma cor grisalha para uma cor sólida. Exemplo: não retornar #1B se houver percentual visível de branco.";
$prompt_final .= "\n8. densidade_detectada deve ser SOMENTE um destes valores:";
$prompt_final .= "\n80%, 100%, 130%, 150%";
$prompt_final .= "\n9. grau_falha NÃO deve ser calculado pela IA.";
$prompt_final .= "\n10. grau_falha deve retornar exatamente este valor já existente no sistema: " . $grau_falha_banco;
$prompt_final .= "\n11. sugestao_protese deve ser SOMENTE um dos modelos abaixo:";
$prompt_final .= "\nMicropele 0.04, Micropele 0.06, Micropele 0.08, Micropele 0.10";
$prompt_final .= "\n12. A sugestão de prótese deve respeitar o que o profissional utiliza no sistema, sem inventar outro modelo.";
$prompt_final .= "\n13. Se houver dúvida entre dois códigos, escolha o código permitido mais próximo visualmente, mantendo o percentual de branco aparente.";
$prompt_final .= "\n14. Nunca invente formato novo fora da lista permitida.";

$prompt_final .= "\n\nExemplo de resposta válida:";
$prompt_final .= '\n{"cor_detectada":"#310","densidade_detectada":"100%","grau_falha":"15 X 24","sugestao_protese":"Micropele 0.08"}';

$modelo_ia = 'gpt-4o';

$dados = array(
	'model' => $modelo_ia,
	'input' => array(
		array(
			'role' => 'user',
			'content' => array(
				array(
					'type' => 'input_text',
					'text' => $prompt_final
				),
				array(
					'type' => 'input_image',
					'image_url' => $imagem_data_url
				)
			)
		)
	)
);

$ch = curl_init();

curl_setopt($ch, CURLOPT_URL, "https://api.openai.com/v1/responses");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
curl_setopt($ch, CURLOPT_TIMEOUT, 90);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	"Content-Type: application/json",
	"Authorization: Bearer " . $openai_key
));
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($dados));

$resposta = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$erro = curl_error($ch);

curl_close($ch);

if($erro != ""){
	echo json_encode(array(
		'status' => 'Erro',
		'mensagem' => 'Erro ao processar análise'
	), JSON_UNESCAPED_UNICODE);
	exit();
}

if($resposta == ""){
	echo json_encode(array(
		'status' => 'Erro',
		'mensagem' => 'Resposta vazia da análise'
	), JSON_UNESCAPED_UNICODE);
	exit();
}

$resposta_decodificada = json_decode($resposta, true);

if(!is_array($resposta_decodificada)){
	echo json_encode(array(
		'status' => 'Erro',
		'mensagem' => 'Resposta inválida da análise'
	), JSON_UNESCAPED_UNICODE);
	exit();
}

if($http != 200){
	$mensagem_erro = 'Erro ao processar análise';

	if(isset($resposta_decodificada['error']['message']) && $resposta_decodificada['error']['message'] != ''){
		$mensagem_erro = $resposta_decodificada['error']['message'];
	}

	echo json_encode(array(
		'status' => 'Erro',
		'mensagem' => $mensagem_erro
	), JSON_UNESCAPED_UNICODE);
	exit();
}

$texto_resposta = '';

if(isset($resposta_decodificada['output']) && is_array($resposta_decodificada['output'])){
	for($i = 0; $i < count($resposta_decodificada['output']); $i++){
		if(isset($resposta_decodificada['output'][$i]['content']) && is_array($resposta_decodificada['output'][$i]['content'])){
			for($j = 0; $j < count($resposta_decodificada['output'][$i]['content']); $j++){
				if(
					isset($resposta_decodificada['output'][$i]['content'][$j]['type']) &&
					$resposta_decodificada['output'][$i]['content'][$j]['type'] == 'output_text'
				){
					$texto_resposta .= $resposta_decodificada['output'][$i]['content'][$j]['text'];
				}
			}
		}
	}
}

if($texto_resposta == '' && isset($resposta_decodificada['output_text'])){
	$texto_resposta = $resposta_decodificada['output_text'];
}

if($texto_resposta == ''){
	echo json_encode(array(
		'status' => 'Erro',
		'mensagem' => 'A IA não retornou dados para análise'
	), JSON_UNESCAPED_UNICODE);
	exit();
}

$texto_resposta = trim($texto_resposta);
$texto_resposta = preg_replace('/^```json/i', '', $texto_resposta);
$texto_resposta = preg_replace('/^```/i', '', $texto_resposta);
$texto_resposta = preg_replace('/```$/i', '', $texto_resposta);
$texto_resposta = trim($texto_resposta);

$dados_ia = json_decode($texto_resposta, true);

if(!is_array($dados_ia)){
	echo json_encode(array(
		'status' => 'Erro',
		'mensagem' => 'A IA retornou um formato inválido'
	), JSON_UNESCAPED_UNICODE);
	exit();
}

$cor_detectada = isset($dados_ia['cor_detectada']) ? trim($dados_ia['cor_detectada']) : '';
$densidade_detectada = isset($dados_ia['densidade_detectada']) ? trim($dados_ia['densidade_detectada']) : '';
$grau_falha = isset($dados_ia['grau_falha']) ? trim($dados_ia['grau_falha']) : '';
$sugestao_protese = isset($dados_ia['sugestao_protese']) ? trim($dados_ia['sugestao_protese']) : '';

$cores_permitidas = array(
	'#1B', '#1B10', '#1B20', '#1B30', '#1B40', '#1B50', '#1B60',
	'#2', '#210', '#220', '#230', '#240', '#250',
	'#3', '#310', '#320', '#330', '#340',
	'#4', '#410', '#420', '#430', '#440',
	'#5', '#6'
);

$densidades_permitidas = array('80%', '100%', '130%', '150%');

$modelos_permitidos = array(
	'Micropele 0.04',
	'Micropele 0.06',
	'Micropele 0.08',
	'Micropele 0.10'
);

if(!in_array($cor_detectada, $cores_permitidas)){
	$cor_detectada = '';
}

if(!in_array($densidade_detectada, $densidades_permitidas)){
	$densidade_detectada = '';
}

if(!in_array($sugestao_protese, $modelos_permitidos)){
	$sugestao_protese = '';
}

if($grau_falha_banco != ""){
	$grau_falha = $grau_falha_banco;
}

if($cor_detectada == "" && $densidade_detectada == "" && $sugestao_protese == ""){
	echo json_encode(array(
		'status' => 'Erro',
		'mensagem' => 'A IA não retornou dados válidos dentro do padrão'
	), JSON_UNESCAPED_UNICODE);
	exit();
}

$query3 = $pdo->prepare("UPDATE $tabela SET 
	cor_detectada = :cor_detectada, 
	densidade_detectada = :densidade_detectada, 
	grau_falha = :grau_falha, 
	sugestao_protese = :sugestao_protese
	WHERE id = :id");

$query3->bindValue(":cor_detectada", $cor_detectada);
$query3->bindValue(":densidade_detectada", $densidade_detectada);
$query3->bindValue(":grau_falha", $grau_falha);
$query3->bindValue(":sugestao_protese", $sugestao_protese);
$query3->bindValue(":id", $id);
$query3->execute();

echo json_encode(array(
	'status' => 'Sucesso',
	'mensagem' => 'Análise processada com sucesso',
	'cor_detectada' => $cor_detectada,
	'densidade_detectada' => $densidade_detectada,
	'grau_falha' => $grau_falha,
	'sugestao_protese' => $sugestao_protese
), JSON_UNESCAPED_UNICODE);
?>