<?php
require_once __DIR__ . '/../_context_bootstrap.php';
// ============================================================================
// Arquivo: /public_html/sistema/painel/paginas/agendamentos/inserir.php (AJAX)
// PADRÃO "BLINDADO MARKETPLACE" (SEM MUDAR A LÓGICA):
// - Detecta SLUG (POST/GET/URI/REFERER) ANTES do conexao.php
// - Passa $_GET['loja'] para o bootstrap escolher o TENANT correto
// ============================================================================
@chdir(__DIR__);

if (!function_exists('bb_clean_slug')) {
  function bb_clean_slug($v) {
    $v = strtolower(trim((string)$v));
    return preg_replace('/[^a-z0-9_-]/', '', $v);
  }
}

$__bb_reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$__bb_refPath = '';
$__bb_slug = '';
if (!empty($_POST['loja'])) {
  $__bb_slug = bb_clean_slug($_POST['loja']);
} elseif (!empty($_GET['loja'])) {
  $__bb_slug = bb_clean_slug($_GET['loja']);
} elseif (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath, $m1)) {
  $__bb_slug = bb_clean_slug($m1[1]);
} elseif ($__bb_refPath && preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_refPath, $m2)) {
  $__bb_slug = bb_clean_slug($m2[1]);
}

if ($__bb_slug !== '') {
  $_GET['loja'] = $__bb_slug;
}

require_once __DIR__ . '/../../../conexao.php';

$tabela = 'agendamentos';

$usuario_logado = @$_SESSION['id'] . '';

$cliente     = $_POST['cliente'];
$data        = $_POST['data'];
$hora        = @$_POST['hora'];
$obs         = $_POST['obs'];
$id          = $_POST['id'];
$funcionario = $_POST['funcionario'];
$servico     = $_POST['servico'];
$data_agd    = $_POST['data'];
$hora_do_agd = @$_POST['hora'];
$hash        = '';

if (@$hora == "") {
	echo 'Selecione um Horário antes de agendar!';
	exit();
}

/**
 * ==========================================================
 * FIX WARNING: $not_sistema indefinido
 * - tenta ler da tabela config (se existir coluna not_sistema)
 * - se não existir, assume "Não"
 * ==========================================================
 */
if (!isset($not_sistema)) {
	$not_sistema = 'Não';
	try {
		$stCfg = $pdo->query("SELECT * FROM config LIMIT 1");
		$cfg = $stCfg->fetch(PDO::FETCH_ASSOC);
		if ($cfg && isset($cfg['not_sistema'])) {
			$not_sistema = $cfg['not_sistema'];
		}
	} catch (Throwable $e) {
		$not_sistema = 'Não';
	}
}

$query = $pdo->query("SELECT * FROM usuarios where id = '$funcionario'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$intervalo = $res[0]['intervalo'];
$nome_func = @$res[0]['nome'];

$query = $pdo->query("SELECT * FROM servicos where id = '$servico'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$tempo = $res[0]['tempo'];
$nome_serv = @$res[0]['nome'];

$hora_minutos = strtotime("+$tempo minutes", strtotime($hora));
$hora_final_servico = date('H:i:s', $hora_minutos);

$nova_hora = $hora;

$diasemana = array("Domingo", "Segunda-Feira", "Terça-Feira", "Quarta-Feira", "Quinta-Feira", "Sexta-Feira", "Sabado");
$diasemana_numero = date('w', strtotime($data));
$dia_procurado = $diasemana[$diasemana_numero];

//percorrer os dias da semana que ele trabalha
$query = $pdo->query("SELECT * FROM dias where funcionario = '$funcionario' and dia = '$dia_procurado'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
if (@count($res) == 0) {
	echo 'Este Funcionário não trabalha neste Dia!';
	exit();
} else {
	$inicio = $res[0]['inicio'];
	$final = $res[0]['final'];
	$inicio_almoco = $res[0]['inicio_almoco'];
	$final_almoco = $res[0]['final_almoco'];
}

$dataF = implode('/', array_reverse(explode('-', $data)));
$horaF = date("H:i", strtotime($hora));

while (strtotime($nova_hora) < strtotime($hora_final_servico)) {

	$hora_minutos = strtotime("+$intervalo minutes", strtotime($nova_hora));
	$nova_hora = date('H:i:s', $hora_minutos);

	//VERIFICAR NA TABELA HORARIOS AGD SE TEM O HORARIO NESSA DATA
	$query_agd = $pdo->query("SELECT * FROM horarios_agd where data = '$data' and funcionario = '$funcionario' and horario = '$nova_hora'");
	$res_agd = $query_agd->fetchAll(PDO::FETCH_ASSOC);
	if (@count($res_agd) > 0) {
		echo 'Este serviço demora cerca de ' . $tempo . ' minutos, precisa escolher outro horário, pois neste horários não temos disponibilidade devido a outros agendamentos!';
		exit();
	}

	//VERIFICAR NA TABELA AGENDAMENTOS SE TEM O HORARIO NESSA DATA
	$query_agd = $pdo->query("SELECT * FROM agendamentos where data = '$data' and funcionario = '$funcionario' and hora = '$nova_hora'");
	$res_agd = $query_agd->fetchAll(PDO::FETCH_ASSOC);
	if (@count($res_agd) > 0) {
		if ($tempo <= $intervalo) {
			// mantém lógica
		} else {
			if ($hora_final_servico == $res_agd[0]['hora']) {
				// mantém lógica
			} else {
				echo 'Este serviço demora cerca de ' . $tempo . ' minutos, precisa escolher outro horário, pois neste horários não temos disponibilidade devido a outros agendamentos!';
				exit();
			}
		}
	}

	if (strtotime($nova_hora) > strtotime($inicio_almoco) and strtotime($nova_hora) < strtotime($final_almoco)) {
		echo 'Este serviço demora cerca de ' . $tempo . ' minutos, precisa escolher outro horário, pois neste horários não temos disponibilidade devido ao horário de almoço!';
		exit();
	}
}

//validar horario
$query = $pdo->query("SELECT * FROM $tabela where data = '$data' and hora = '$hora' and funcionario = '$funcionario'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);
if ($total_reg > 0 and $res[0]['id'] != $id) {
	echo 'Este horário não está disponível!';
	exit();
}

//pegar nome do cliente
$query = $pdo->query("SELECT * FROM clientes where id = '$cliente'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$nome_cliente = $res[0]['nome'];
$telefone = $res[0]['telefone'];

if ($not_sistema == 'Sim') {
	$mensagem_not = $nome_cliente;
	$titulo_not = 'Novo Agendamento ' . $dataF . ' - ' . $horaF;
	$id_usu = $funcionario;
	require __DIR__ . '/../../../../api/notid.php';
}

if ($msg_agendamento == 'Api') {

	//agendar o alerta de confirmação
	$hora_atual = date('H:i:s');
	$data_atual = date('Y-m-d');
	$hora_minutos = strtotime("-$minutos_aviso minutes", strtotime($hora));
	$nova_hora = date('H:i:s', $hora_minutos);

	$telefone = '55' . preg_replace('/[ ()-]+/', '', $telefone);
}

$query = $pdo->prepare("INSERT INTO $tabela SET funcionario = '$funcionario', cliente = '$cliente', hora = '$hora', data = '$data_agd', usuario = '$usuario_logado', status = 'Agendado', obs = :obs, data_lanc = curDate(), servico = '$servico', hash = '$hash'");
$query->bindValue(":obs", "$obs");
$query->execute();

$ult_id = $pdo->lastInsertId();

if ($msg_agendamento == 'Api') {
	if (strtotime($hora_atual) < strtotime($nova_hora) or strtotime($data_atual) != strtotime($data_agd)) {

		$mensagem = '*Confirmação de Agendamento*%0A';
		$mensagem .= 'Profissional: *' . $nome_func . '*%0A';
		$mensagem .= 'Serviço: *' . $nome_serv . '*%0A';
		$mensagem .= 'Data: *' . $dataF . '*%0A';
		$mensagem .= 'Hora: *' . $horaF . '*%0A%0A';
		$mensagem .= '_(Digite o número com a opção desejada)_%0A';
		$mensagem .= '1.  Digite 1️⃣ para confirmar ✅ %0A';
		$mensagem .= '2.  Digite 2️⃣ para Cancelar ❌ %0A';
$id_envio = $ult_id;
$data_envio = $data_agd . ' ' . $hora_do_agd;

if ($minutos_aviso > 0) {

	// ✅ IMPORTANTE (padrão do sistema):
	// O confirmacao.php só antecipa o disparo quando existe $antAgendamento.
	// Sem isso, a confirmação cai no MESMO horário do serviço.
	$antAgendamento = (int)$minutos_aviso;

	// Resolver do confirmacao.php (Marketplace-safe)
	$__bb_root = rtrim(($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
	$__bb_confirmacao = '';

	if ($__bb_root !== '') {
		$__bb_confirmacao = $__bb_root . '/pagamentos/confirmacao.php';
		if (!file_exists($__bb_confirmacao)) {
			$__bb_confirmacao = $__bb_root . '/ajax/confirmacao.php';
		}
	}

	if ($__bb_confirmacao === '' || !file_exists($__bb_confirmacao)) {
		$__bb_confirmacao = __DIR__ . '/../../../../pagamentos/confirmacao.php';
		if (!file_exists($__bb_confirmacao)) {
			$__bb_confirmacao = __DIR__ . '/../../../../ajax/confirmacao.php';
		}
	}

	if (file_exists($__bb_confirmacao)) {
		require $__bb_confirmacao;

		// Atualiza hash do agendamento (prepared)
		if (isset($id) && $id !== '') {
			$id_hash = (string)$id;
			$stmtHash = $pdo->prepare("UPDATE agendamentos SET hash = :hash WHERE id = :id");
			$stmtHash->execute([
				':hash' => $id_hash,
				':id'   => (int)$ult_id
			]);
		}
	} else {
		@error_log("BarberBot: confirmacao.php não encontrado (inserir.php).");
	}

}
}
}

while (strtotime($hora) < strtotime($hora_final_servico)) {

	$hora_minutos = strtotime("+$intervalo minutes", strtotime($hora));
	$hora = date('H:i:s', $hora_minutos);

	if (strtotime($hora) < strtotime($hora_final_servico)) {
		$query = $pdo->query("INSERT INTO horarios_agd SET agendamento = '$ult_id', horario = '$hora', funcionario = '$funcionario', data = '$data_agd'");
	}
}

echo 'Salvo com Sucesso';
?>
