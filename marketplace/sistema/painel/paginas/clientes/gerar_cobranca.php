<?php
require_once __DIR__ . '/../_context_bootstrap.php';
$tabela = 'receber';
require_once __DIR__ . '/../../../conexao.php';
$data_atual = date('Y-m-d');

$parcela = isset($_POST['parcela']) ? $_POST['parcela'] : '';
$valor = isset($_POST['valor']) ? $_POST['valor'] : 0;
$data = isset($_POST['data']) ? $_POST['data'] : '';
$telefone = isset($_POST['telefone']) ? $_POST['telefone'] : '';
$multa = isset($_POST['multa']) ? $_POST['multa'] : '';
$juros = isset($_POST['juros']) ? $_POST['juros'] : '';
$id_conta = isset($_POST['id_par']) ? (int)$_POST['id_par'] : 0;
$descricao = isset($_POST['descricao']) ? $_POST['descricao'] : '';

$tel_cliente = '55'.preg_replace('/[ ()-]+/' , '' , $telefone);
$telefone = $tel_cliente;

if($juros == ""){
	$juros = 0;
}

if($multa == ""){
	$multa = 0;
}

$valorF = @number_format($valor, 2, '.', '');
$valorF = @number_format($valor, 2, ',', '.');
$jurosF = @number_format($juros, 2, ',', '.');
$multaF = @number_format($multa, 2, ',', '.');

$dataF = '';
if($data != ''){
	$dataF = implode('/', array_reverse(explode('-', $data)));
}

if(@strtotime($data) < @strtotime($data_atual)){
	$titulo_mensagem = '⚠️Atenção!  regularize hoje!⚠️';
}else{
	$titulo_mensagem = '📩 Lembrete pagamento';
}

// ✅ Base do site (remove /sistema se existir) e ajusta para public_html/pagamentos
$url_sistema_limpo = rtrim((string)$url_sistema, '/');
$url_site = preg_replace('~/sistema$~i', '', $url_sistema_limpo);
$url_site = rtrim((string)$url_site, '/') . '/';

// ✅ Multi-tenant: preserva loja para banco individual no /pagamentos
$loja = '';
if(isset($_SESSION['tenant_slug']) && $_SESSION['tenant_slug'] != ''){
	$loja = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$_SESSION['tenant_slug']);
}else if(isset($_SESSION['loja']) && $_SESSION['loja'] != ''){
	$loja = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$_SESSION['loja']);
}

$link_pgto = $url_site."pagamentos/receber.php?id_conta=".$id_conta;
if($loja != ''){
	$link_pgto .= "&loja=".urlencode($loja);
}

//mensagem da cobrança
$mensagem = "";

$mensagem .= "_{$titulo_mensagem}_ \n";
$mensagem = "*{$nome_sistema}* \n";
$mensagem .= "Assinatura: *{$descricao}* \n";
if($multa > 0){
	$mensagem .= "Multa Atraso: *R$ {$multaF}* \n";
}

if($juros > 0){
	$mensagem .= "Juros Atraso: *R$ {$jurosF}* \n";
}

$mensagem .= "💲 Valor: *R$ {$valorF}* \n";
$mensagem .= "Vencimento: *{$dataF}* \n\n";
$mensagem .= "*Link Pagamento Pix:* \n";
$mensagem .= "$link_pgto";

require __DIR__ . '/../../../../ajax/api-texto.php';
?>
