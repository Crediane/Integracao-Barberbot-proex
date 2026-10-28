<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'clientes';

if (!function_exists('bb_clean_slug')) {
	function bb_clean_slug($v){
		$v = strtolower(trim((string)$v));
		return preg_replace('/[^a-z0-9_-]/', '', $v);
	}
}

if (!function_exists('bb_telefone_digitos')) {
	function bb_telefone_digitos($valor){
		return preg_replace('/\D+/', '', (string)$valor);
	}
}

if (!function_exists('bb_telefone_tem_prefixo_internacional')) {
	function bb_telefone_tem_prefixo_internacional($valor){
		$valor = trim((string)$valor);
		return (bool)preg_match('/^\s*\+/', $valor) || (bool)preg_match('/^\s*00/', $valor);
	}
}

if (!function_exists('bb_telefone_normalizar_envio')) {
	function bb_telefone_normalizar_envio($telefone, $ddiSelecionado = '', $paisSelecionado = 'br'){
		$raw = trim((string)$telefone);
		$digits = bb_telefone_digitos($raw);
		$ddiSelecionado = bb_telefone_digitos($ddiSelecionado);
		$paisSelecionado = strtolower(trim((string)$paisSelecionado));

		if ($digits === '') {
			return '';
		}

		if (strpos($digits, '00') === 0) {
			$digits = substr($digits, 2);
		}

		if (bb_telefone_tem_prefixo_internacional($raw)) {
			return $digits;
		}

		if ($paisSelecionado !== '' && $paisSelecionado !== 'br' && $paisSelecionado !== 'outro' && $ddiSelecionado !== '') {
			if (strpos($digits, $ddiSelecionado) === 0) {
				return $digits;
			}
			return $ddiSelecionado . $digits;
		}

		if ($paisSelecionado === 'outro') {
			return $digits;
		}

		if (strlen($digits) === 10 || strlen($digits) === 11) {
			return '55' . $digits;
		}

		return $digits;
	}
}


$id = isset($_POST['id']) ? trim((string)$_POST['id']) : '';
$nome = isset($_POST['nome']) ? trim((string)$_POST['nome']) : '';
$telefone = isset($_POST['telefone']) ? trim((string)$_POST['telefone']) : '';
$data_nasc = isset($_POST['data_nasc']) ? trim((string)$_POST['data_nasc']) : '';
$endereco = isset($_POST['endereco']) ? trim((string)$_POST['endereco']) : '';
$cartoes = isset($_POST['cartao']) ? trim((string)$_POST['cartao']) : '';
$cpf = isset($_POST['cpf']) ? trim((string)$_POST['cpf']) : '';
$telefone_pais = isset($_POST['telefone_pais']) ? trim((string)$_POST['telefone_pais']) : 'br';
$telefone_ddi = isset($_POST['telefone_ddi']) ? trim((string)$_POST['telefone_ddi']) : '';

$senha = '123';
$senha_crip = password_hash($senha, PASSWORD_DEFAULT);

if ($cartoes === '') {
	$cartoes = '0';
}

$telefone_digits = bb_telefone_digitos($telefone);

// validar telefone ignorando máscara
$query = $pdo->prepare("
	SELECT id 
	FROM $tabela 
	WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(telefone, ''), '(', ''), ')', ''), '-', ''), ' ', ''), '+', ''), '.', ''), '/', '') = :telefone
	LIMIT 1
");
$query->bindValue(':telefone', $telefone_digits);
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);

if(@count($res) > 0 and $id != $res[0]['id']){
	echo 'Telefone já Cadastrado, escolha outro!!';
	exit();
}

if($id == ""){
	$query = $pdo->prepare("INSERT INTO $tabela SET nome = :nome, telefone = :telefone, data_cad = curDate(), data_nasc = :data_nasc, cartoes = :cartoes, endereco = :endereco, alertado = 'Não', cpf = :cpf, senha_crip = :senha_crip");
	$query->bindValue(":senha_crip", $senha_crip);
} else {
	$query = $pdo->prepare("UPDATE $tabela SET nome = :nome, telefone = :telefone, data_nasc = :data_nasc, cartoes = :cartoes, endereco = :endereco, cpf = :cpf WHERE id = :id");
	$query->bindValue(":id", $id);
}

$query->bindValue(":nome", $nome);
$query->bindValue(":telefone", $telefone);
$query->bindValue(":data_nasc", $data_nasc);
$query->bindValue(":cartoes", $cartoes);
$query->bindValue(":endereco", $endereco);
$query->bindValue(":cpf", $cpf);
$query->execute();


// AQUI VAMOS ENVIAR O WHATSAPP APÓS SALVAR
// Mantém o telefone salvo no banco como veio do formulário,
// mas para a API da Menuia envia o número normalizado pelo país/DDI selecionado.
$telefone_banco = $telefone;
$telefone_envio = bb_telefone_normalizar_envio($telefone_banco, $telefone_ddi, $telefone_pais);
$telefone = $telefone_envio !== '' ? $telefone_envio : $telefone_banco;

// ===== Monta o link de acesso conforme o contexto =====
$reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$refPath = '';
$slug_loja = '';

if (!empty($_GET['loja'])) {
	$slug_loja = bb_clean_slug($_GET['loja']);
} elseif (!empty($_POST['loja'])) {
	$slug_loja = bb_clean_slug($_POST['loja']);
} else {
	if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $reqPath, $mSlug)) {
		$slug_loja = bb_clean_slug($mSlug[1]);
	} elseif (!empty($refPath) && preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $refPath, $mSlug2)) {
		$slug_loja = bb_clean_slug($mSlug2[1]);
	}
}

$baseUrlSistema = rtrim((string)($url_sistema ?? ''), '/');

$httpsOn = (
	(!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
	(!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') ||
	(!empty($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
);

$hostAtual = trim((string)($_SERVER['HTTP_HOST'] ?? ''));
$baseUrlAtual = '';

if ($hostAtual != '') {
	$baseUrlAtual = ($httpsOn ? 'https://' : 'http://') . $hostAtual;
}

if ($slug_loja != '') {
	$baseLink = $baseUrlAtual != '' ? $baseUrlAtual : $baseUrlSistema;
	$linkAcesso = rtrim($baseLink, '/') . '/loja/' . $slug_loja . '/sistema/acesso';
} else {
	$baseLink = $baseUrlSistema != '' ? $baseUrlSistema : $baseUrlAtual;
	$linkAcesso = rtrim($baseLink, '/') . '/sistema/acesso';
}

// Montar mensagem
$mensagem = "👋 *Olá {$nome}*, seja bem-vindo(a) ao *{$nome_sistema}*!\n\n";
$mensagem .= "🔒 *Use seu whatsapp e senha de acesso:* 123\n\n";
$mensagem .= "🌐 *Clique abaixo para acessar seu painel:*\n";
$mensagem .= $linkAcesso;

// Agora chama o arquivo de envio
require __DIR__ . '/../../../../ajax/api-texto.php';

echo 'Salvo com Sucesso';
?>