<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'receber';

/*
  CORREÇÃO (PHP 8+):
  - Evita "Undefined array key 'id'" e garante compatibilidade de sessão
    para dono do sistema e dono do estabelecimento.
  - Mantém a lógica original do salvar (não altera regra de negócio).
*/

// ===== Recuperar ID do usuário logado sem gerar warnings =====
$id_usuario = '';
if(isset($_SESSION['id']) && $_SESSION['id'] !== ''){
	$id_usuario = $_SESSION['id'];
}elseif(isset($_SESSION['id_usuario']) && $_SESSION['id_usuario'] !== ''){
	$id_usuario = $_SESSION['id_usuario'];
}elseif(isset($_SESSION['usuario_id']) && $_SESSION['usuario_id'] !== ''){
	$id_usuario = $_SESSION['usuario_id'];
}elseif(isset($_SESSION['user_id']) && $_SESSION['user_id'] !== ''){
	$id_usuario = $_SESSION['user_id'];
}elseif(isset($_SESSION['id_usu']) && $_SESSION['id_usu'] !== ''){
	$id_usuario = $_SESSION['id_usu'];
}elseif(isset($_SESSION['usuario']) && is_array($_SESSION['usuario']) && isset($_SESSION['usuario']['id']) && $_SESSION['usuario']['id'] !== ''){
	$id_usuario = $_SESSION['usuario']['id'];
}elseif(isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id']) && $_SESSION['user']['id'] !== ''){
	$id_usuario = $_SESSION['user']['id'];
}else{
	// fallback: procura um valor numérico plausível na sessão
	foreach($_SESSION as $k => $v){
		if(!is_scalar($v)){ continue; }
		if(!is_numeric($v)){ continue; }
		$kl = strtolower((string)$k);
		if($kl === 'id' || strpos($kl, 'usuario') !== false || strpos($kl, 'user') !== false){
			$id_usuario = $v;
			break;
		}
	}
}

$id_usuario = (is_numeric($id_usuario) ? (int)$id_usuario : 0);

// ===== Campos do formulário (mantidos) =====
$id         = $_POST['id'] ?? '';
$produto    = $_POST['produto'] ?? 0;

$valor      = $_POST['valor'] ?? 0;
$valor      = str_replace('.', '', $valor);
$valor      = str_replace(',', '.', $valor);

$pessoa     = $_POST['pessoa'] ?? 0;
$data_venc  = $_POST['data_venc'] ?? '';
$data_pgto  = $_POST['data_pgto'] ?? '';
$quantidade = $_POST['quantidade'] ?? 1;
$pgto       = $_POST['pgto'] ?? '';

if($produto == 0){
	echo 'Cadastre um Produto e Depois selecione!';
	exit();
}

// ===== Produto / Estoque (mantido) =====
$query = $pdo->query("SELECT * FROM produtos WHERE id = '$produto'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);

$descricao = 'Venda - ('.$quantidade.') '.$res[0]['nome'];
$estoque   = $res[0]['estoque'];

$data_atual = date('Y-m-d');

if($data_pgto != ''){
	$usuario_pgto = $id_usuario;
	$pago = 'Sim';
}else{
	$usuario_pgto = 0;
	$pago = 'Não';
}

if($quantidade > $estoque){
	echo 'Você não pode vendar mais do que você possui em estoque! Você tem '.$estoque.' produtos em estoque!';
	exit();
}

$total_estoque = $estoque - $quantidade;
$pdo->query("UPDATE produtos SET estoque = '$total_estoque' WHERE id = '$produto'");

// ===== Foto (mantido) =====
$query = $pdo->query("SELECT * FROM $tabela WHERE id = '$id'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);

$foto = $total_reg > 0 ? $res[0]['foto'] : 'sem-foto.jpg';

$nome_img    = date('d-m-Y H:i:s') .'-'.@$_FILES['foto']['name'];
$nome_img    = preg_replace('/[ :]+/' , '-' , $nome_img);
$caminho     = '../../img/contas/' .$nome_img;
$imagem_temp = @$_FILES['foto']['tmp_name']; 

if(@$_FILES['foto']['name'] != ""){
	$ext = pathinfo($nome_img, PATHINFO_EXTENSION);   
	if(in_array($ext, ['png','jpg','jpeg','gif','pdf','rar','zip'])){ 
		if($foto != "sem-foto.jpg"){
			@unlink('../../img/contas/'.$foto);
		}
		$foto = $nome_img;
		move_uploaded_file($imagem_temp, $caminho);
	}else{
		echo 'Extensão de Imagem não permitida!';
		exit();
	}
}

// ===== Caixa Aberto (mantido) =====
// Se por algum motivo não houver id_usuario na sessão, $id_usuario = 0 e não vincula caixa (mesmo comportamento prático de antes, sem warning).
$query = $pdo->query("SELECT * FROM caixas WHERE operador = '$id_usuario' AND data_fechamento IS NULL ORDER BY id DESC LIMIT 1");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$id_caixa = @count($res) > 0 ? @$res[0]['id'] : 0;

// ===== Insert / Update (mantido) =====
// Evita notice caso $hora_random não esteja definido no contexto
$hora_random = isset($hora_random) ? $hora_random : '';

if($id == ""){
	$query = $pdo->prepare("INSERT INTO $tabela SET descricao = :descricao, tipo = 'Venda', valor = :valor, data_lanc = :data_lanc, data_venc = '$data_venc', data_pgto = '$data_pgto', usuario_lanc = '$id_usuario', usuario_baixa = '$usuario_pgto', foto = '$foto', pessoa = '$pessoa', pago = '$pago', produto = '$produto', quantidade = '$quantidade', pgto = '$pgto', caixa = '$id_caixa', hora = :hora_lanc, hora_alerta = '$hora_random'");
}else{
	$query = $pdo->prepare("UPDATE $tabela SET descricao = :descricao, valor = :valor, data_venc = '$data_venc', data_pgto = '$data_pgto', foto = '$foto', pessoa = '$pessoa', produto = '$produto', quantidade = '$quantidade' WHERE id = '$id'");
}

$query->bindValue(":descricao", $descricao);
$query->bindValue(":valor", $valor);
if ($id == "") {
	$query->bindValue(":data_lanc", date('Y-m-d'));
	$query->bindValue(":hora_lanc", date('H:i:s'));
}
$query->execute();

echo 'Salvo com Sucesso';
?>