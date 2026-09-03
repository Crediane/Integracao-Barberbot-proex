<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once(__DIR__ . "/../../../conexao.php");

$tabela = 'simulacoes_protese';
$ponteiro_lock = null;

function responder_geracao_ia($mensagem){
	global $ponteiro_lock;

	if(isset($ponteiro_lock) && is_resource($ponteiro_lock)){
		@flock($ponteiro_lock, LOCK_UN);
		@fclose($ponteiro_lock);
	}

	echo $mensagem;
	exit();
}

function arquivo_simulacao_existente($pasta_upload, $nome_arquivo){
	$nome_arquivo = trim((string)$nome_arquivo);

	if($nome_arquivo == ""){
		return false;
	}

	$nome_arquivo = basename($nome_arquivo);
	$caminho_arquivo = rtrim($pasta_upload, '/\\') . DIRECTORY_SEPARATOR . $nome_arquivo;

	if(file_exists($caminho_arquivo) && is_file($caminho_arquivo) && is_readable($caminho_arquivo)){
		return true;
	}

	return false;
}

$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if($id <= 0){
	responder_geracao_ia('ID da simulação não informado');
}

$query = $pdo->prepare("SELECT s.*, c.nome as nome_cliente, p.modelo, p.cor, p.densidade, p.tamanho 
	FROM $tabela s
	INNER JOIN clientes c ON s.cliente = c.id
	INNER JOIN proteses p ON s.id_protese = p.id
	WHERE s.id = :id");
$query->bindValue(':id', $id, PDO::PARAM_INT);
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);

if(@count($res) == 0){
	responder_geracao_ia('Registro não encontrado');
}

$modelo = trim(@$res[0]['modelo']);
$cor = trim(@$res[0]['cor']);
$densidade = trim(@$res[0]['densidade']);
$tamanho = trim(@$res[0]['tamanho']);
$observacoes = trim(@$res[0]['observacoes']);
$foto_original = basename(trim(@$res[0]['foto_original']));
$imagem_simulada_atual = basename(trim(@$res[0]['imagem_simulada']));

if($foto_original == ""){
	responder_geracao_ia('Nenhuma foto original enviada');
}

$pasta_upload = __DIR__ . '/../../images/simulacoes/';

if(!is_dir($pasta_upload)){
	responder_geracao_ia('Pasta de imagens da simulação não encontrada');
}

if(!is_readable($pasta_upload)){
	responder_geracao_ia('Pasta de imagens da simulação sem permissão de leitura');
}

if(!is_writable($pasta_upload)){
	responder_geracao_ia('Pasta de imagens da simulação sem permissão de escrita');
}

/*
	Correção direta contra repetição:
	Se a simulação já foi gerada e o arquivo existe, não chama a IA novamente,
	não substitui a imagem atual e mantém a resposta esperada pelo JavaScript.
*/
if(arquivo_simulacao_existente($pasta_upload, $imagem_simulada_atual)){
	responder_geracao_ia('Salvo com Sucesso');
}

/*
	Trava por ID para evitar duas requisições simultâneas gerando a mesma simulação.
	Não cria coluna nova e não altera a regra de negócio do sistema.
*/
$arquivo_lock = rtrim(sys_get_temp_dir(), '/\\') . DIRECTORY_SEPARATOR . 'barberbot_simulador_ia_' . md5(__DIR__ . '|' . $id) . '.lock';
$ponteiro_lock = @fopen($arquivo_lock, 'c');

if(!$ponteiro_lock){
	responder_geracao_ia('Não foi possível criar a trava da simulação');
}

if(!@flock($ponteiro_lock, LOCK_EX | LOCK_NB)){
	responder_geracao_ia('A simulação já está sendo gerada. Aguarde finalizar.');
}

/*
	Depois da trava, consulta novamente.
	Isso evita repetição quando duas telas tentam gerar a mesma simulação quase ao mesmo tempo.
*/
$query_verificacao = $pdo->prepare("SELECT imagem_simulada FROM $tabela WHERE id = :id");
$query_verificacao->bindValue(':id', $id, PDO::PARAM_INT);
$query_verificacao->execute();
$res_verificacao = $query_verificacao->fetchAll(PDO::FETCH_ASSOC);

if(@count($res_verificacao) == 0){
	responder_geracao_ia('Registro não encontrado');
}

$imagem_simulada_verificacao = basename(trim(@$res_verificacao[0]['imagem_simulada']));

if(arquivo_simulacao_existente($pasta_upload, $imagem_simulada_verificacao)){
	responder_geracao_ia('Salvo com Sucesso');
}

$query2 = $pdo->query("SELECT * FROM config LIMIT 1");
$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);

if(@count($res2) == 0){
	responder_geracao_ia('Configurações não encontradas');
}

$openai_key = trim(@$res2[0]['openai_key']);

if($openai_key == ""){
	responder_geracao_ia('OpenAI Key não cadastrada');
}

$caminho_foto = $pasta_upload . $foto_original;

if(!file_exists($caminho_foto)){
	responder_geracao_ia('Arquivo da foto original não encontrado');
}

if(!is_file($caminho_foto)){
	responder_geracao_ia('Arquivo da foto original inválido');
}

if(!is_readable($caminho_foto)){
	responder_geracao_ia('Arquivo da foto original sem permissão de leitura');
}

$extensao = strtolower(pathinfo($caminho_foto, PATHINFO_EXTENSION));
$mime = 'image/jpeg';

if($extensao == 'png'){
	$mime = 'image/png';
}

if($extensao == 'webp'){
	$mime = 'image/webp';
}

$arquivo_curl = new CURLFile($caminho_foto, $mime, basename($caminho_foto));

/*
	Prompt travado do simulador
	Seguir exatamente o que o profissional definiu
*/
$prompt = "Edite a imagem enviada criando uma simulacao fotografica profissional de protese capilar. ";
$prompt .= "Mantenha obrigatoriamente o mesmo rosto, expressao, angulo, proporcao da cabeca, fundo e enquadramento da foto original. ";
$prompt .= "Nao alterar identidade da pessoa. Nao alterar sobrancelhas. Nao alterar barba. Nao alterar roupa. Nao alterar iluminacao de forma artificial. ";
$prompt .= "A simulacao deve parecer resultado real de aplicacao profissional em clinica ou barbearia especializada. ";

$prompt .= "REGRA PRINCIPAL OBRIGATORIA: a simulacao deve seguir exatamente o que o profissional definiu no sistema. ";
$prompt .= "Nao inventar outra cor. Nao inventar outra densidade. Nao inventar outro modelo. Nao suavizar grisalho. Nao reduzir percentual de branco. Nao aumentar percentual de branco. ";

$prompt .= "Sugestao de protese definida pelo profissional: " . $modelo . ". ";
$prompt .= "Cor detectada definida pelo profissional: " . $cor . ". ";
$prompt .= "Densidade definida pelo profissional: " . $densidade . ". ";
$prompt .= "Tamanho informado: " . $tamanho . ". ";
$prompt .= "Observacoes do profissional: " . $observacoes . ". ";

$prompt .= "Modelos permitidos no sistema: Micropele 0.04, Micropele 0.06, Micropele 0.08, Micropele 0.10. ";
$prompt .= "Usar obrigatoriamente e exatamente o modelo definido pelo profissional, sem substituicao. ";

$prompt .= "Escala profissional de cores permitidas com percentual de branco: ";
$prompt .= "#1B Preto natural. ";
$prompt .= "#1B10 Preto natural + 10% branco. ";
$prompt .= "#1B20 Preto natural + 20% branco. ";
$prompt .= "#1B30 Preto natural + 30% branco. ";
$prompt .= "#1B40 Preto natural + 40% branco. ";
$prompt .= "#1B50 Preto natural + 50% branco. ";
$prompt .= "#1B60 Preto natural + 60% branco. ";
$prompt .= "#2 Castanho escuro. ";
$prompt .= "#210 Castanho escuro + 10% branco. ";
$prompt .= "#220 Castanho escuro + 20% branco. ";
$prompt .= "#230 Castanho escuro + 30% branco. ";
$prompt .= "#240 Castanho escuro + 40% branco. ";
$prompt .= "#250 Castanho escuro + 50% branco. ";
$prompt .= "#3 Castanho medio. ";
$prompt .= "#310 Castanho medio + 10% branco. ";
$prompt .= "#320 Castanho medio + 20% branco. ";
$prompt .= "#330 Castanho medio + 30% branco. ";
$prompt .= "#340 Castanho medio + 40% branco. ";
$prompt .= "#4 Castanho. ";
$prompt .= "#410 Castanho + 10% branco. ";
$prompt .= "#420 Castanho + 20% branco. ";
$prompt .= "#430 Castanho + 30% branco. ";
$prompt .= "#440 Castanho + 40% branco. ";
$prompt .= "#5 Castanho claro. ";
$prompt .= "#6 Loiro escuro. ";

$prompt .= "Se a cor definida pelo profissional tiver percentual de branco, esse percentual deve aparecer visualmente na simulacao. ";
$prompt .= "Exemplo: se a cor definida for #1B30, a simulacao deve mostrar preto natural com aproximadamente 30% de fios brancos ou grisalhos visiveis. ";
$prompt .= "Exemplo: se a cor definida for #220, a simulacao deve mostrar castanho escuro com aproximadamente 20% de fios brancos ou grisalhos visiveis. ";
$prompt .= "Exemplo: se a cor definida for #340, a simulacao deve mostrar castanho medio com aproximadamente 40% de fios brancos ou grisalhos visiveis. ";

$prompt .= "A IA deve respeitar obrigatoriamente o grisalho definido pelo profissional na cor detectada. ";
$prompt .= "Nao transformar cor grisalha em cor solida. ";
$prompt .= "Nao remover os fios brancos quando houver percentual de branco definido. ";
$prompt .= "Nao adicionar fios brancos quando a cor definida nao tiver percentual de branco. ";

$prompt .= "Densidades permitidas no sistema: 80%, 100%, 130%, 150%. ";
$prompt .= "Usar obrigatoriamente a densidade exatamente como informada pelo profissional, sem alterar volume para mais ou para menos. ";

$prompt .= "Requisitos visuais obrigatorios: ";
$prompt .= "resultado extremamente natural, realista e profissional. ";
$prompt .= "Respeitar linha frontal compativel com o caso real. ";
$prompt .= "Respeitar direcao dos fios, implantacao, volume e cobertura coerentes com o modelo e densidade definidos. ";
$prompt .= "Cobertura uniforme e acabamento invisivel na base. ";
$prompt .= "Sem exagero de volume. ";
$prompt .= "Sem aparencia de peruca. ";
$prompt .= "Sem brilho plastico. ";
$prompt .= "Sem bordas aparentes. ";
$prompt .= "Nao inserir texto, marca dagua, moldura, elementos extras ou fundo novo. ";

$prompt .= "Resumo final obrigatorio: obedecer exatamente a cor detectada, inclusive percentual de branco, obedecer exatamente a densidade, e obedecer exatamente a sugestao de protese definida pelo profissional.";

$dados = array(
	'model' => 'gpt-image-1',
	'prompt' => $prompt,
	'size' => '1024x1536',
	'quality' => 'medium',
	'image' => $arquivo_curl
);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, "https://api.openai.com/v1/images/edits");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array(
	"Authorization: Bearer " . $openai_key
));
curl_setopt($ch, CURLOPT_POSTFIELDS, $dados);

$resposta = curl_exec($ch);
$http = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$erro = curl_error($ch);
curl_close($ch);

if($erro != ""){
	responder_geracao_ia('Erro ao gerar imagem: ' . $erro);
}

if($resposta == ""){
	responder_geracao_ia('Resposta vazia da IA');
}

$resposta_decodificada = json_decode($resposta, true);

if($http != 200){
	$mensagem_erro = 'Erro ao gerar imagem';

	if(isset($resposta_decodificada['error']['message'])){
		$mensagem_erro = $resposta_decodificada['error']['message'];
	}

	responder_geracao_ia($mensagem_erro);
}

if(!isset($resposta_decodificada['data'][0]['b64_json'])){
	responder_geracao_ia('Resposta da IA inválida');
}

$imagem_base64_saida = $resposta_decodificada['data'][0]['b64_json'];

if($imagem_base64_saida == ""){
	responder_geracao_ia('Imagem gerada inválida');
}

$imagem_binaria = base64_decode($imagem_base64_saida);

if($imagem_binaria === false || $imagem_binaria == ""){
	responder_geracao_ia('Erro ao decodificar imagem gerada');
}

$nome_img = md5(uniqid()) . '.png';
$caminho = $pasta_upload . $nome_img;

$salvou_imagem = file_put_contents($caminho, $imagem_binaria);

if($salvou_imagem === false){
	responder_geracao_ia('Erro ao salvar imagem gerada');
}

if(!file_exists($caminho)){
	responder_geracao_ia('Arquivo da imagem gerada não foi gravado');
}

$query3 = $pdo->prepare("SELECT * FROM $tabela WHERE id = :id");
$query3->bindValue(':id', $id, PDO::PARAM_INT);
$query3->execute();
$res3 = $query3->fetchAll(PDO::FETCH_ASSOC);

if(@count($res3) > 0){
	$imagem_antiga = basename(trim($res3[0]['imagem_simulada']));

	if($imagem_antiga != "" && $imagem_antiga != $nome_img){
		$caminho_antigo = $pasta_upload . $imagem_antiga;

		if(file_exists($caminho_antigo) && is_file($caminho_antigo)){
			@unlink($caminho_antigo);
		}
	}
}

$query4 = $pdo->prepare("UPDATE $tabela SET imagem_simulada = :imagem_simulada WHERE id = :id");
$query4->bindValue(":imagem_simulada", $nome_img);
$query4->bindValue(":id", $id, PDO::PARAM_INT);
$query4->execute();

responder_geracao_ia('Salvo com Sucesso');
?>
