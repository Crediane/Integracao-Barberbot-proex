<?php
require_once __DIR__ . '/../_context_bootstrap.php';
/**
 * BarberBot Software - Receber (Salvar)
 * Arquivo: /public_html/sistema/painel/paginas/receber/salvar.php
 *
 * Ajustes (sem mudar a lógica):
 * - Inicia sessão ANTES de carregar a conexão (bancos individuais por perfil/estabelecimento).
 * - Evita warnings PHP 8+ (sessão e POST indefinidos).
 * - Mantém o mesmo fluxo de INSERT/UPDATE, upload de arquivo, caixa e recorrência.
 * - Prepared Statements para segurança (sem alterar regra de negócio).
 */
require_once __DIR__ . '/../../../conexao.php';

$tabela = 'receber';

/**
 * Recupera ID do usuário logado de forma compatível (Admin do Sistema / Dono do Estabelecimento).
 * Não bloqueia o salvamento se não encontrar (mantém o fluxo), apenas evita warnings.
 */
$id_usuario = 0;

if (isset($_SESSION['id']) && is_numeric($_SESSION['id'])) {
	$id_usuario = (int)$_SESSION['id'];
} elseif (isset($_SESSION['id_usuario']) && is_numeric($_SESSION['id_usuario'])) {
	$id_usuario = (int)$_SESSION['id_usuario'];
	$_SESSION['id'] = $id_usuario; // normaliza para o padrão do sistema
} elseif (isset($_SESSION['usuario_id']) && is_numeric($_SESSION['usuario_id'])) {
	$id_usuario = (int)$_SESSION['usuario_id'];
	$_SESSION['id'] = $id_usuario;
} elseif (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
	$id_usuario = (int)$_SESSION['user_id'];
	$_SESSION['id'] = $id_usuario;
} elseif (isset($_SESSION['usuario']) && is_array($_SESSION['usuario']) && isset($_SESSION['usuario']['id']) && is_numeric($_SESSION['usuario']['id'])) {
	$id_usuario = (int)$_SESSION['usuario']['id'];
	$_SESSION['id'] = $id_usuario;
} elseif (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id']) && is_numeric($_SESSION['user']['id'])) {
	$id_usuario = (int)$_SESSION['user']['id'];
	$_SESSION['id'] = $id_usuario;
} else {
	// Fallback leve: procura por alguma chave de sessão numérica relacionada a id de usuário
	foreach ($_SESSION as $k => $v) {
		if (!is_scalar($v) || !is_numeric($v)) { continue; }
		$kl = strtolower((string)$k);
		if ($kl === 'id' || strpos($kl, 'usuario') !== false || strpos($kl, 'user') !== false) {
			$id_usuario = (int)$v;
			$_SESSION['id'] = $id_usuario;
			break;
		}
	}
}

// ====== Entradas (evitar Undefined index) ======
$id          = isset($_POST['id']) ? trim((string)$_POST['id']) : '';
$descricao   = isset($_POST['descricao']) ? trim((string)$_POST['descricao']) : '';
$valor       = isset($_POST['valor']) ? trim((string)$_POST['valor']) : '';
$pessoa      = isset($_POST['pessoa']) ? trim((string)$_POST['pessoa']) : '';
$data_venc   = isset($_POST['data_venc']) ? trim((string)$_POST['data_venc']) : '';
$data_pgto   = isset($_POST['data_pgto']) ? trim((string)$_POST['data_pgto']) : '';
$forma_pgto  = isset($_POST['pgto']) ? trim((string)$_POST['pgto']) : '';
$frequencia  = isset($_POST['frequencia']) ? trim((string)$_POST['frequencia']) : '';
$servico     = isset($_POST['servico']) ? trim((string)$_POST['servico']) : '';
$hora_random = isset($_POST['hora_random']) ? trim((string)$_POST['hora_random']) : '';

// Normaliza valor (mantém lógica original)
$valor = str_replace('.', '', $valor);
$valor = str_replace(',', '.', $valor);

// Validações (mantidas)
if($descricao == ""){
	echo 'Insira uma descrição!';
	exit();
}

if($pessoa == ""){
	$pessoa = 0;
}

// ====== Pagamento (mantém lógica) ======
if($data_pgto != ''){
	$usuario_pgto = $id_usuario;
	$pago = 'Sim';
}else{
	$usuario_pgto = 0;
	$pago = 'Não';
}

// ====== Foto atual (mantém padrão) ======
$foto = 'sem-foto.jpg';

if($id != ""){
	$stFoto = $pdo->prepare("SELECT foto FROM $tabela WHERE id = :id LIMIT 1");
	$stFoto->execute([':id' => $id]);
	$regFoto = $stFoto->fetch(PDO::FETCH_ASSOC);
	if($regFoto && !empty($regFoto['foto'])){
		$foto = $regFoto['foto'];
	}
}

// ====== Upload de arquivo (mantido) ======
$nome_img = date('d-m-Y H:i:s') .'-'.(@$_FILES['foto']['name'] ?? '');
$nome_img = preg_replace('/[ :]+/' , '-' , $nome_img);

$caminho = '../../img/contas/' .$nome_img;
$imagem_temp = @$_FILES['foto']['tmp_name'];

if(!empty($_FILES['foto']['name'])){
	$ext = strtolower((string)pathinfo($nome_img, PATHINFO_EXTENSION));
	$permitidas = ['png','jpg','jpeg','gif','pdf','rar','zip'];

	if(in_array($ext, $permitidas, true)){
		// Exclui a foto anterior
		if($foto != "sem-foto.jpg" && $foto != ""){
			@unlink('../../img/contas/'.$foto);
		}

		$foto = $nome_img;
		move_uploaded_file($imagem_temp, $caminho);
	}else{
		echo 'Extensão de Imagem não permitida!';
		exit();
	}
}

// ====== Verificar caixa aberto (mantido) ======
$id_caixa = 0;
if($id_usuario > 0){
	$stCx = $pdo->prepare("SELECT id FROM caixas WHERE operador = :op AND data_fechamento IS NULL ORDER BY id DESC LIMIT 1");
	$stCx->execute([':op' => $id_usuario]);
	$rCx = $stCx->fetch(PDO::FETCH_ASSOC);
	if($rCx && isset($rCx['id'])){
		$id_caixa = (int)$rCx['id'];
	}
}

// ====== INSERT / UPDATE (mesma regra, com prepared) ======
if($id == ""){

	// INSERT
	if($data_pgto != ''){
		$sql = "INSERT INTO $tabela
				SET descricao = :descricao,
					tipo = 'Conta',
					valor = :valor,
					data_lanc = curDate(),
					data_venc = :data_venc,
					data_pgto = :data_pgto,
					usuario_lanc = :usuario_lanc,
					usuario_baixa = :usuario_baixa,
					foto = :foto,
					pessoa = :pessoa,
					pago = :pago,
					caixa = :caixa,
					hora = curTime(),
					pgto = :pgto,
					hora_alerta = :hora_alerta,
					servico = :servico,
					frequencia = :frequencia";
	} else {
		$sql = "INSERT INTO $tabela
				SET descricao = :descricao,
					tipo = 'Conta',
					valor = :valor,
					data_lanc = curDate(),
					data_venc = :data_venc,
					usuario_lanc = :usuario_lanc,
					usuario_baixa = :usuario_baixa,
					foto = :foto,
					pessoa = :pessoa,
					pago = :pago,
					caixa = :caixa,
					hora = curTime(),
					pgto = :pgto,
					hora_alerta = :hora_alerta,
					servico = :servico,
					frequencia = :frequencia";
	}

	$st = $pdo->prepare($sql);
	$st->bindValue(":descricao", $descricao);
	$st->bindValue(":valor", $valor);
	$st->bindValue(":data_venc", $data_venc);
	$st->bindValue(":usuario_lanc", $id_usuario);
	$st->bindValue(":usuario_baixa", $usuario_pgto);
	$st->bindValue(":foto", $foto);
	$st->bindValue(":pessoa", (int)$pessoa, PDO::PARAM_INT);
	$st->bindValue(":pago", $pago);
	$st->bindValue(":caixa", $id_caixa);
	$st->bindValue(":pgto", $forma_pgto);
	$st->bindValue(":hora_alerta", $hora_random);
	$st->bindValue(":servico", $servico);
	$st->bindValue(":frequencia", $frequencia);

	if($data_pgto != ''){
		$st->bindValue(":data_pgto", $data_pgto);
	}

	$st->execute();

}else{

	// UPDATE
	$sql = "UPDATE $tabela
			SET descricao = :descricao,
				valor = :valor,
				data_venc = :data_venc,
				data_pgto = :data_pgto,
				foto = :foto,
				pessoa = :pessoa,
				caixa = :caixa,
				hora = curTime(),
				pgto = :pgto,
				servico = :servico,
				frequencia = :frequencia
			WHERE id = :id";

	$st = $pdo->prepare($sql);
	$st->bindValue(":descricao", $descricao);
	$st->bindValue(":valor", $valor);
	$st->bindValue(":data_venc", $data_venc);
	$st->bindValue(":data_pgto", $data_pgto);
	$st->bindValue(":foto", $foto);
	$st->bindValue(":pessoa", (int)$pessoa, PDO::PARAM_INT);
	$st->bindValue(":caixa", $id_caixa);
	$st->bindValue(":pgto", $forma_pgto);
	$st->bindValue(":servico", $servico);
	$st->bindValue(":frequencia", $frequencia);
	$st->bindValue(":id", $id);
	$st->execute();
}

// ====== Recorrência (mantém lógica) ======
$dias_frequencia = isset($_POST['frequencia']) ? (int)$_POST['frequencia'] : 0;

if($dias_frequencia > 0 && $pago == 'Sim'){

	if($dias_frequencia == 30 || $dias_frequencia == 31){
		$novo_vencimento = date('Y-m-d', @strtotime("+1 month", @strtotime($data_venc)));
	}else if($dias_frequencia == 90){
		$novo_vencimento = date('Y-m-d', @strtotime("+3 month", @strtotime($data_venc)));
	}else if($dias_frequencia == 180){
		$novo_vencimento = date('Y-m-d', @strtotime("6 month", @strtotime($data_venc)));
	}else if($dias_frequencia == 360 || $dias_frequencia == 365){
		$novo_vencimento = date('Y-m-d', @strtotime("+12 month", @strtotime($data_venc)));
	}else{
		$novo_vencimento = date('Y-m-d', @strtotime("+$dias_frequencia days", @strtotime($data_venc)));
	}

	// Insere próxima conta (mesmo padrão do sistema)
	$stRec = $pdo->prepare("INSERT INTO receber
			SET descricao = :descricao,
				tipo = 'Conta',
				valor = :valor,
				data_lanc = curDate(),
				data_venc = :data_venc,
				usuario_lanc = :usuario_lanc,
				foto = 'sem-foto.jpg',
				pessoa = :pessoa,
				pago = 'Não',
				hora = curTime(),
				frequencia = :frequencia,
				hora_alerta = :hora_alerta");

	$stRec->bindValue(":descricao", $descricao);
	$stRec->bindValue(":valor", $valor);
	$stRec->bindValue(":data_venc", $novo_vencimento);
	$stRec->bindValue(":usuario_lanc", $id_usuario);
	$stRec->bindValue(":pessoa", (int)$pessoa, PDO::PARAM_INT);
	$stRec->bindValue(":frequencia", $frequencia);
	$stRec->bindValue(":hora_alerta", $hora_random);
	$stRec->execute();

	$ultima_conta = $pdo->lastInsertId();
}

echo 'Salvo com Sucesso';
?>
