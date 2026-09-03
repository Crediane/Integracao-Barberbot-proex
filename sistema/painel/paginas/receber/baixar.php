<?php
require_once __DIR__ . '/../_context_bootstrap.php';
/*
  Arquivo: /public_html/sistema/painel/paginas/receber/baixar.php
  Ajuste: compatibilidade de sessão (Admin do Sistema e Dono do Estabelecimento) + evitar warnings PHP 8+
  Regra de negócio: NÃO ALTERADA (continua baixando a conta, lançando recorrência e comissão conforme configurações).
*/

require_once __DIR__ . '/../../../conexao.php';
$tabela = 'receber';

// ===== Recuperar ID do usuário logado de forma segura (evita Undefined array key 'id') =====
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
} else {
	// fallback: tenta achar algum ID numérico plausível na sessão
	foreach ($_SESSION as $k => $v) {
		if (!is_scalar($v) || !is_numeric($v)) { continue; }
		$kLower = strtolower((string)$k);
		if ($kLower === 'id' || strpos($kLower, 'usuario') !== false || strpos($kLower, 'user') !== false) {
			$id_usuario = (int)$v;
			break;
		}
	}
}

if ($id_usuario <= 0) {
	echo 'Sessão expirada. Faça login novamente.';
	exit();
}

// ===== POST (sanitização para evitar warnings) =====
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$valor = $_POST['valor'] ?? '';
$valor = str_replace(',', '.', $valor);
$data_pgto = $_POST['data_pgto'] ?? '';
$forma_pgto = $_POST['pgto'] ?? '';

if ($id <= 0) {
	echo 'ID inválido!';
	exit();
}

// ===== Buscar dados da conta =====
$stmt = $pdo->prepare("SELECT * FROM $tabela WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $id]);
$conta = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$conta) {
	echo 'Registro não encontrado!';
	exit();
}

$funcionario = $conta['funcionario'];
$servico = $conta['servico'];
$cliente = $conta['pessoa'];
$descricao = 'Comissão - '.$conta['descricao'];
$tipo = $conta['tipo'];
$pgto = $conta['pgto'];
$valor_serv = $conta['valor'];
$frequencia = $conta['frequencia'];
$dias_frequencia = $conta['frequencia'];
$data_venc = $conta['data_venc'];
$hash = $conta['hash'];
$descricao_antiga = $conta['descricao'];

// ===== Cliente (mantido, com @ para evitar warnings caso não encontre) =====
$query2 = $pdo->query("SELECT * FROM clientes where id = '$cliente'");
$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
$telefone_cliente = @$res2[0]['telefone'];
$nome_cliente = @$res2[0]['nome'];

$valorF = number_format((float)$valor, 2, '.', '');
$valorF = number_format((float)$valor, 2, ',', '.');

// ===== Gerar próxima parcela se tiver frequência (mantido) =====
if($frequencia > 0){

	if($dias_frequencia == 30 || $dias_frequencia == 31){
		$novo_vencimento = date('Y-m-d', @strtotime("+1 month",@strtotime($data_venc)));
	}else if($dias_frequencia == 90){
		$novo_vencimento = date('Y-m-d', @strtotime("+3 month",@strtotime($data_venc)));
	}else if($dias_frequencia == 180){
		$novo_vencimento = date('Y-m-d', @strtotime("6 month",@strtotime($data_venc)));
	}else if($dias_frequencia == 360 || $dias_frequencia == 365){
		$novo_vencimento = date('Y-m-d', @strtotime("+12 month",@strtotime($data_venc)));
	}else{
		$novo_vencimento = date('Y-m-d', @strtotime("+$dias_frequencia days",@strtotime($data_venc)));
	}

	$novo_vencimentoF = implode('/', array_reverse(@explode('-', $novo_vencimento)));

	// mantém INSERT original (usa variáveis globais do sistema como $hora_random)
	$pdo->query("INSERT INTO receber SET descricao = '$descricao_antiga', tipo = 'Assinatura', valor = '$valor', data_lanc = curDate(), data_venc = '$novo_vencimento', usuario_lanc = '$id_usuario', foto = 'sem-foto.jpg', pessoa = '$cliente', pago = 'Não', hora = curTime(), frequencia = '$frequencia', hora_alerta = '$hora_random', servico = '$servico', pgto = '$pgto'");

	$ultima_conta = $pdo->lastInsertId();
}

// ===== Verificar caixa aberto (mantido) =====
$query1 = $pdo->query("SELECT * from caixas where operador = '$id_usuario' and data_fechamento is null order by id desc limit 1");
$res1 = $query1->fetchAll(PDO::FETCH_ASSOC);
if(@count($res1) > 0){
	$id_caixa = @$res1[0]['id'];
}else{
	$id_caixa = 0;
}

// ===== Comissão do serviço (mantido) =====
if($tipo == 'Serviço'){
	$query = $pdo->query("SELECT * FROM servicos where id = '$servico'");
	$res = $query->fetchAll(PDO::FETCH_ASSOC);

	$valor_servico_base = @$res[0]['valor'];
	$comissao = @$res[0]['comissao'];

	if($tipo_comissao == 'Porcentagem'){
		$valor_comissao = ($comissao * $valor_serv) / 100;
	}else{
		$valor_comissao = $comissao;
	}

	if($lanc_comissao != 'Sempre'){
		// lançar a conta a pagar para a comissão do funcionário
		$pdo->query("INSERT INTO pagar SET descricao = '$descricao', tipo = 'Comissão', valor = '$valor_comissao', data_lanc = curDate(), data_venc = curDate(), usuario_lanc = '$id_usuario', foto = 'sem-foto.jpg', pago = 'Não', funcionario = '$funcionario', servico = '$servico', cliente = '$cliente', caixa = '$id_caixa', hora = curTime(), hora_alerta = '$hora_random' ");
	}
}

// ===== Taxa (mantido) =====
$query = $pdo->query("SELECT * FROM formas_pgto where nome = '$pgto'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$valor_taxa = @$res[0]['taxa'];

if($valor_taxa > 0){
	if($taxa_sistema == 'Cliente'){
		$valor_serv = $valor_serv + $valor_serv * ($valor_taxa / 100);
	}else{
		$valor_serv = $valor_serv - $valor_serv * ($valor_taxa / 100);
	}
}

// ===== Baixar conta (mantido) =====
$pdo->query("UPDATE $tabela SET pgto = '$forma_pgto', valor = '$valor', pago = 'Sim', usuario_baixa = '$id_usuario', data_pgto = '$data_pgto', valor = '$valor_serv', caixa = '$id_caixa', hora = curTime() where id = '$id'");

echo 'Baixado com Sucesso';
?>
