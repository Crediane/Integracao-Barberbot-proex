<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
/*
  Arquivo: /public_html/sistema/painel/paginas/compras/salvar.php
  Ajuste: evitar Warning "Undefined array key 'id'" no PHP 8+
  Motivo: alguns perfis (dono do sistema / dono do estabelecimento) podem gravar o ID do usurio em outra chave de sesso.
  Observao: no altera a lgica do fluxo, apenas torna a leitura do ID segura.
*/

require_once __DIR__ . '/../../../conexao.php';
$tabela = 'pagar';

// ===== Recuperar ID do usurio logado (compatvel com diferentes perfis) =====
$id_usuario = 0;

if (isset($_SESSION['id']) && is_numeric($_SESSION['id'])) {
	$id_usuario = (int)$_SESSION['id'];
} elseif (isset($_SESSION['id_usuario']) && is_numeric($_SESSION['id_usuario'])) {
	$id_usuario = (int)$_SESSION['id_usuario'];
} elseif (isset($_SESSION['usuario_id']) && is_numeric($_SESSION['usuario_id'])) {
	$id_usuario = (int)$_SESSION['usuario_id'];
} elseif (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
	$id_usuario = (int)$_SESSION['user_id'];
} elseif (isset($_SESSION['usuario']) && is_array($_SESSION['usuario']) && isset($_SESSION['usuario']['id']) && is_numeric($_SESSION['usuario']['id'])) {
	$id_usuario = (int)$_SESSION['usuario']['id'];
} elseif (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id']) && is_numeric($_SESSION['user']['id'])) {
	$id_usuario = (int)$_SESSION['user']['id'];
}

if ($id_usuario <= 0) {
	echo 'Sesso expirada. Faa login novamente.';
	exit();
}

// ===== Dados do formulrio (mantendo padro e evitando warnings) =====
$id = $_POST['id'] ?? '';
$produto = $_POST['produto'] ?? 0;
$valor = $_POST['valor'] ?? 0;
$valor = str_replace('.', '', $valor);
$valor = str_replace(',', '.', $valor);
$pessoa = $_POST['pessoa'] ?? '';
$data_venc = $_POST['data_venc'] ?? '';
$data_pgto = $_POST['data_pgto'] ?? '';
$quantidade = $_POST['quantidade'] ?? '';
$forma_pgto = $_POST['pgto'] ?? '';

if($quantidade == ""){
    $quantidade = 0;
}

if($pessoa == ""){
    $pessoa = 0;
}


if($produto == 0){
	echo 'Cadastre um Produto e Depois selecione!';
	exit();
}

$query = $pdo->query("SELECT * FROM produtos where id = '$produto'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$descricao = 'Compra - ('.$quantidade.') '.$res[0]['nome'];
$estoque = $res[0]['estoque'];

if($data_pgto != ''){
	$usuario_pgto = $id_usuario;
	$pago = 'Sim';
	$pgto = " ,data_pgto = '$data_pgto'";
}else{
	$usuario_pgto = 0;
	$pago = 'No';
	$pgto = "";
}


//validar troca da foto
$query = $pdo->query("SELECT * FROM $tabela where id = '$id'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);
if($total_reg > 0){
	$foto = $res[0]['foto'];
}else{
	$foto = 'sem-foto.jpg';
	
}

//atualizar dados do produto
$valor_unitario = $valor / $quantidade;
$total_estoque = $estoque + $quantidade;
$pdo->query("UPDATE produtos SET estoque = '$total_estoque', valor_compra = '$valor_unitario' WHERE id = '$produto'");



//SCRIPT PARA SUBIR FOTO NO SERVIDOR
$nome_img = date('d-m-Y H:i:s') .'-'.@$_FILES['foto']['name'];
$nome_img = preg_replace('/[ :]+/' , '-' , $nome_img);

$caminho = '../../img/contas/' .$nome_img;

$imagem_temp = @$_FILES['foto']['tmp_name']; 

if(@$_FILES['foto']['name'] != ""){
	$ext = pathinfo($nome_img, PATHINFO_EXTENSION);   
	if($ext == 'png' or $ext == 'jpg' or $ext == 'jpeg' or $ext == 'gif' or $ext == 'pdf' or $ext == 'rar' or $ext == 'zip'){ 

			//EXCLUO A FOTO ANTERIOR
			if($foto != "sem-foto.jpg"){
				@unlink('../../img/contas/'.$foto);
			}

			$foto = $nome_img;
		
		move_uploaded_file($imagem_temp, $caminho);
	}else{
		echo 'Extenso de Imagem no permitida!';
		exit();
	}
}


//verificar caixa aberto
$query1 = $pdo->query("SELECT * from caixas where operador = '$id_usuario' and data_fechamento is null order by id desc limit 1");
$res1 = $query1->fetchAll(PDO::FETCH_ASSOC);
if(@count($res1) > 0){
	$id_caixa = @$res1[0]['id'];
}else{
	$id_caixa = 0;
}

if($id == ""){
	$query = $pdo->prepare("INSERT INTO $tabela SET descricao = :descricao, tipo = 'Compra', valor = :valor, data_lanc = curDate(), data_venc = '$data_venc', usuario_lanc = '$id_usuario', usuario_baixa = '$usuario_pgto', foto = '$foto', pessoa = '$pessoa', pago = '$pago', produto = '$produto', quantidade = '$quantidade', caixa = '$id_caixa', hora = curTime(), pgto = '$forma_pgto' $pgto, hora_alerta = '$hora_random'");
}else{
	$query = $pdo->prepare("UPDATE $tabela SET descricao = :descricao, valor = :valor, data_venc = '$data_venc', data_pgto = '$data_pgto', foto = '$foto', pessoa = '$pessoa', produto = '$produto', quantidade = '$quantidade', caixa = '$id_caixa', hora = curTime(), pgto = '$forma_pgto' WHERE id = '$id'");
}

$query->bindValue(":descricao", "$descricao");
$query->bindValue(":valor", "$valor");
$query->execute();

echo 'Salvo com Sucesso';
?>
