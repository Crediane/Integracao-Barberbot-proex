<?php
// /public_html/pagamentos/pagamento_aprovado.php
// ======================================================================
// BOOTSTRAP DE SESSÃO (MARKETPLACE) - PADRÃO DO SISTEMA
// - Isola MASTER x TENANT
// - Isola TENANT por SLUG: BB_TENANT_<hash>
// - Usa cookie TENANT em rotas internas (inclui /pagamentos/*) mesmo sem /loja/<slug>/
// ======================================================================

$__bb_reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (!function_exists('bb_sess_clean_slug')) {
  function bb_sess_clean_slug($v) {
    $v = strtolower(trim((string)$v));
    return preg_replace('/[^a-z0-9_-]/', '', $v);
  }
}

if (!function_exists('bb_path_has_loja')) {
  function bb_path_has_loja(string $p): bool {
    return (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $p);
  }
}

if (!function_exists('bb_path_has_sistema')) {
  function bb_path_has_sistema(string $p): bool {
    return (strpos($p, '/sistema') === 0);
  }
}

// ===============================
// 1) Detecta slug cedo (GET/POST/URI)
// ===============================
$__bb_slug = '';
if (!empty($_GET['loja'])) {
  $__bb_slug = bb_sess_clean_slug($_GET['loja']);
} elseif (!empty($_POST['loja'])) {
  $__bb_slug = bb_sess_clean_slug($_POST['loja']);
} else {
  if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath, $mSlug)) {
    $__bb_slug = bb_sess_clean_slug($mSlug[1]);
  }
}

// Nome da sessão TENANT por SLUG (cookie diferente por loja)
$__bb_tenantSessionName = 'BB_TENANT';
if ($__bb_slug !== '') {
  $__bb_tenantSessionName = 'BB_TENANT_' . strtoupper(substr(sha1($__bb_slug), 0, 12));
}

// ===============================
// 2) Detecta cookies TENANT (BB_TENANT / BB_TENANT_*)
// ===============================
$__bb_tenantCookies = [];
foreach ($_COOKIE as $__bb_ck => $__bb_cv) {
  if ($__bb_ck === 'BB_TENANT' || strpos($__bb_ck, 'BB_TENANT_') === 0) {
    $__bb_tenantCookies[] = (string)$__bb_ck;
  }
}
$__bb_hasTenantCookie = (count($__bb_tenantCookies) > 0);
$__bb_oneTenantCookie = (count($__bb_tenantCookies) === 1) ? (string)$__bb_tenantCookies[0] : '';

// ===============================
// 3) Define contexto MASTER x TENANT
// ===============================
$__bb_lojaByReqPath  = bb_path_has_loja($__bb_reqPath);
$__bb_hasSistemaReq  = bb_path_has_sistema($__bb_reqPath);
$__bb_hasLojaParam   = (!empty($_GET['loja']) || !empty($_POST['loja']));

// ✅ Força MASTER em /sistema quando NÃO é loja pelo PATH e NÃO tem ?loja= (ignora referer)
$__bb_forceMaster = ($__bb_hasSistemaReq && !$__bb_lojaByReqPath && !$__bb_hasLojaParam);

// Rotas internas que precisam do TENANT pelo cookie (sem slug explícito)
$__bb_isInternal = (strpos($__bb_reqPath, '/ajax/') !== false) || (strpos($__bb_reqPath, '/api/') !== false) || (strpos($__bb_reqPath, '/pagamentos/') !== false);

$__bb_hasLoja = $__bb_lojaByReqPath || $__bb_hasLojaParam;
if (!$__bb_hasLoja && !$__bb_hasSistemaReq && $__bb_isInternal && $__bb_hasTenantCookie) {
  $__bb_hasLoja = true;
}

// Sessão desejada
$__bb_desiredSession = 'BB_SITE';
if ($__bb_forceMaster) {
  $__bb_desiredSession = 'BB_MASTER';
} elseif ($__bb_hasLoja) {
  // se tem slug, usa sessão por slug; se não, tenta resolver pelo único cookie tenant
  if ($__bb_slug !== '') {
    $__bb_desiredSession = $__bb_tenantSessionName;
  } elseif ($__bb_oneTenantCookie !== '') {
    $__bb_desiredSession = $__bb_oneTenantCookie;
  } else {
    // compat legado
    $__bb_desiredSession = 'BB_TENANT';
  }
}

// ===============================
// 4) Inicia/ajusta a sessão corretamente (usa cookie do nome escolhido)
// ===============================
if (session_status() === PHP_SESSION_ACTIVE) {
  if (session_name() !== $__bb_desiredSession) {
    session_write_close();
    @session_name($__bb_desiredSession);
    if (!empty($_COOKIE[$__bb_desiredSession])) {
      @session_id((string)$_COOKIE[$__bb_desiredSession]);
    }
    @session_start();
  }
} else {
  @session_name($__bb_desiredSession);
  if (!empty($_COOKIE[$__bb_desiredSession])) {
    @session_id((string)$_COOKIE[$__bb_desiredSession]);
  }
  @session_start();
}

// ============================================================================
// BarberBot - Pagamento Aprovado (Agendamento)
// Arquivo: /public_html/pagamentos/pagamento_aprovado.php
// Ajustes:
// - Multi-tenant: captura loja por GET/SESSION e também pela URL /loja/{slug}/...
// - Segurança: sanitização + prepared statements (sem mudar regra de negócio)
// - Produção: desativa exibição de erros (mantém fluxo)
// - IMPORTANTE: requires agora usam __DIR__ (funciona mesmo quando incluído por outro arquivo)
// ============================================================================

// Produção (evita expor warnings)
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

// ------------------------- MULTI-TENANT (BANCO INDIVIDUAL) -------------------------
$bb_loja = '';
if (isset($_GET['loja']) && $_GET['loja'] !== '') {
	$bb_loja = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$_GET['loja']);
} elseif (isset($_SESSION['tenant_slug']) && $_SESSION['tenant_slug'] !== '') {
	$bb_loja = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$_SESSION['tenant_slug']);
} elseif (isset($_SESSION['loja']) && $_SESSION['loja'] !== '') {
	$bb_loja = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$_SESSION['loja']);
}

// Fallback: tenta capturar da URL /loja/{slug}/...
if ($bb_loja === '' && !empty($_SERVER['REQUEST_URI'])) {
	if (preg_match('~^/loja/([^/]+)/~', (string)$_SERVER['REQUEST_URI'], $m)) {
		$bb_loja = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$m[1]);
	}
}

if ($bb_loja !== '') {
	$_SESSION['loja'] = $bb_loja;
	$_SESSION['tenant_slug'] = $bb_loja;
	// ajuda o bootstrap do banco (conexao.php) a abrir o TENANT correto
	$_GET['loja'] = $bb_loja;
}

function bb_digits($v) {
	return preg_replace('/\D+/', '', (string)$v);
}

// Normaliza telefone para envio WhatsApp (mantém padrão: prefixa 55 apenas quando necessário)
function bb_tel55($v) {
	$digits = preg_replace('/\D+/', '', (string)$v);
	$digits = trim((string)$digits);
	if ($digits === '') return '';
	// Se for BR sem DDI (10 ou 11 dígitos), prefixa 55
	if (strlen($digits) === 10 || strlen($digits) === 11) {
		$digits = '55' . $digits;
	}
	return $digits;
}

// Variáveis externas usadas pelo fluxo (mantém)
$statusPay = isset($status) ? $status : false;

// ✅ id do agendamento temporário pode vir por variável ($idConta) ou GET id_agd
$id_conta = $idConta ?? ($_GET['id_agd'] ?? '');
$id_conta = bb_digits($id_conta);

// ✅ ref_pix pode vir por variável ($ref_pix) ou GET ref_pix (fallback sem mudar lógica)
$ref_pix = $ref_pix ?? ($_GET['ref_pix'] ?? '');
$ref_pix = trim((string)$ref_pix);

// Regras originais
if ($id_conta != "") {
	if (@$porc_servico > 0 && !$statusPay) {
		echo 'Faça o pagamento antes de ir para o agendamento';
		exit();
	}
}

// Conexão (sempre, para suportar ambos os caminhos)
require_once(__DIR__ . "/../sistema/conexao.php");

$valor_pago = '0';

// Buscar agendamento temporário (mesma lógica)
if ($id_conta != "") {
	$stmtTmp = $pdo->prepare("SELECT * FROM agendamentos_temp WHERE id = :id LIMIT 1");
	$stmtTmp->bindValue(':id', (int)$id_conta, PDO::PARAM_INT);
	$stmtTmp->execute();
} else {
	$stmtTmp = $pdo->prepare("SELECT * FROM agendamentos_temp WHERE ref_pix = :ref_pix LIMIT 1");
	$stmtTmp->bindValue(':ref_pix', $ref_pix);
	$stmtTmp->execute();
}

$res = $stmtTmp->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);

if ($total_reg <= 0) {
	echo 'Agendamento temporário não encontrado!';
	exit();
}

$cliente = $res[0]['cliente'];
$servico = $res[0]['servico'];
$funcionario = $res[0]['funcionario'];
$data = $res[0]['data'];
$hora = $res[0]['hora'];
$obs = $res[0]['obs'];
$data_lanc = $res[0]['data_lanc'];
$usuario = $res[0]['usuario'];
$status = $res[0]['status'];
$hash = $res[0]['hash'];
$ref_pix = $res[0]['ref_pix'];
$data_agd = $res[0]['data'];
$hora_do_agd = $res[0]['hora'];
$valor = $res[0]['valor_pago'];

if ($valor > 0) {
	$valor_pago = $valor;
}

// Mantém regra original forma de pagamento
$forma_pgto = $forma_pgto ?? '';
if (@$forma_pgto == "pix") {
	$forma_pgto = "Pix";
} else {
	$forma_pgto = "Cartão de Crédito";
}

// Data/hora calculadas no timezone já aplicado pelo tenant.
$bb_data_lancamento = date('Y-m-d');
$bb_hora_lancamento = date('H:i:s');

// Serviço (mesma lógica)
$stmtServ = $pdo->prepare("SELECT * FROM servicos WHERE id = :id LIMIT 1");
$stmtServ->bindValue(':id', (int)$servico, PDO::PARAM_INT);
$stmtServ->execute();
$resServ = $stmtServ->fetchAll(PDO::FETCH_ASSOC);

$nome_serv = @$resServ[0]['nome'];
$tempo = @$resServ[0]['tempo'];

$servico_conc = $nome_serv . " (Site)";

// Inserir agendamento (mesma lógica)
$stmtInsAgd = $pdo->prepare("INSERT INTO agendamentos 
	SET funcionario = :funcionario, cliente = :cliente, hora = :hora, data = :data, usuario = '0',
	status = 'Agendado', obs = :obs, data_lanc = :data_lanc, servico = :servico, hash = :hash,
	ref_pix = :ref_pix, valor_pago = :valor_pago");

$stmtInsAgd->bindValue(':funcionario', (int)$funcionario, PDO::PARAM_INT);
$stmtInsAgd->bindValue(':cliente', (int)$cliente, PDO::PARAM_INT);
$stmtInsAgd->bindValue(':hora', $hora);
$stmtInsAgd->bindValue(':data', $data);
$stmtInsAgd->bindValue(':obs', $obs);
$stmtInsAgd->bindValue(':data_lanc', $bb_data_lancamento);
$stmtInsAgd->bindValue(':servico', (int)$servico, PDO::PARAM_INT);
$stmtInsAgd->bindValue(':hash', $hash);
$stmtInsAgd->bindValue(':ref_pix', $ref_pix);
$stmtInsAgd->bindValue(':valor_pago', $valor_pago);
$stmtInsAgd->execute();

$ult_id = $pdo->lastInsertId();

// Inserir receber quando aplicável (mesma lógica)
$hora_random = $hora_random ?? ''; // evita notice
$id_receber = 0;

if ($id_conta == "" || $statusPay) {

	$stmtInsRec = $pdo->prepare("INSERT INTO receber 
		SET descricao = :descricao, tipo = 'Serviço', valor = :valor, data_lanc = :data_lanc, data_venc = :data_venc,
		data_pgto = :data_pgto, usuario_lanc = '0', usuario_baixa = '0', foto = 'sem-foto.jpg', pessoa = :pessoa,
		pago = 'Sim', servico = :servico, funcionario = :funcionario, obs = '', pgto = :pgto, referencia = :referencia,
		hora = :hora, hora_alerta = :hora_alerta");

	$stmtInsRec->bindValue(':descricao', $servico_conc);
	$stmtInsRec->bindValue(':valor', $valor_pago);
	$stmtInsRec->bindValue(':data_lanc', $bb_data_lancamento);
	$stmtInsRec->bindValue(':data_venc', $bb_data_lancamento);
	$stmtInsRec->bindValue(':data_pgto', $bb_data_lancamento);
	$stmtInsRec->bindValue(':pessoa', (int)$cliente, PDO::PARAM_INT);
	$stmtInsRec->bindValue(':servico', (int)$servico, PDO::PARAM_INT);
	$stmtInsRec->bindValue(':funcionario', (int)$funcionario, PDO::PARAM_INT);
	$stmtInsRec->bindValue(':pgto', $forma_pgto);
	$stmtInsRec->bindValue(':referencia', (int)$ult_id, PDO::PARAM_INT);
	$stmtInsRec->bindValue(':hora', $bb_hora_lancamento);
	$stmtInsRec->bindValue(':hora_alerta', $hora_random);

	$stmtInsRec->execute();
	$id_receber = (int)$pdo->lastInsertId();
}

$dataF = implode('/', array_reverse(explode('-', $data)));
$horaF = date("H:i", @strtotime($hora));

// Funcionário (mesma lógica)
$stmtFunc = $pdo->prepare("SELECT * FROM usuarios WHERE id = :id LIMIT 1");
$stmtFunc->bindValue(':id', (int)$funcionario, PDO::PARAM_INT);
$stmtFunc->execute();
$resFunc = $stmtFunc->fetchAll(PDO::FETCH_ASSOC);

$intervalo = @$resFunc[0]['intervalo'];
$nome_func = @$resFunc[0]['nome'];
$tel_func = @$resFunc[0]['telefone'];

$hora_minutos = @strtotime("+$tempo minutes", @strtotime($hora));
$hora_final_servico = date('H:i:s', $hora_minutos);

// Mensagens via API (mesma lógica)
$msg_agendamento = $msg_agendamento ?? '';
$whatsapp_sistema = $whatsapp_sistema ?? '';
$minutos_aviso = isset($minutos_aviso) ? (int)$minutos_aviso : 0;

if ($msg_agendamento == 'Api') {

	$stmtCli = $pdo->prepare("SELECT * FROM clientes WHERE id = :id LIMIT 1");
	$stmtCli->bindValue(':id', (int)$cliente, PDO::PARAM_INT);
	$stmtCli->execute();
	$resCli = $stmtCli->fetchAll(PDO::FETCH_ASSOC);

	$nome = $resCli[0]['nome'];
	$telefone = $resCli[0]['telefone'];
	$tel_cli = $resCli[0]['telefone'];

	$dataF = implode('/', array_reverse(explode('-', $data)));
	$horaF = date("H:i", @strtotime($hora));

	$mensagem = "📅 _Novo Agendamento_ \n";
	$mensagem .= "Profissional: *{$nome_func}* \n";
	$mensagem .= "Serviço: *{$nome_serv}* \n";
	$mensagem .= "Data: *{$dataF}* \n";
	$mensagem .= "Hora: *{$horaF}* \n";
	$mensagem .= "Cliente: *{$nome}* \n";
	if ($obs != "") {
		$mensagem .= "Obs: *{$obs}* \n";
	}

	$telefone = bb_tel55($telefone);
	// =============================
	// WhatsApp (api-texto.php) - resolver caminho (config primeiro, ajax fallback)
	// =============================
	if ($bb_loja !== '') {
		// garante que o bootstrap do tenant consiga identificar a loja
		$_GET['loja'] = $bb_loja;
		$_POST['loja'] = $bb_loja;
	}

	// Caminho REAL do sistema para pagamentos (sem chute)
	$__bb_apiTexto = (isset($_SERVER['DOCUMENT_ROOT']) ? rtrim((string)$_SERVER['DOCUMENT_ROOT'], '/\\') : '') . '/pagamentos/api-texto.php';

	// Compatibilidade: instalações antigas podem manter em /ajax ou /config
	if (!is_file($__bb_apiTexto)) {
		$__bb_apiTexto = __DIR__ . '/../ajax/api-texto.php';
	}
	if (!is_file($__bb_apiTexto)) {
		$__bb_apiTexto = __DIR__ . '/../config/api-texto.php';
	}

	if (!is_file($__bb_apiTexto)) {
		@error_log('BarberBot WhatsApp: api-texto.php não encontrado em /pagamentos, /ajax ou /config (pagamento_aprovado.php)');
	} else {
		require_once $__bb_apiTexto;
	}


	if ($tel_func != $whatsapp_sistema) {
		$telefone = bb_tel55($tel_func);
		// envia mensagem também para o profissional
		if (isset($__bb_apiTexto) && is_file($__bb_apiTexto)) {
			require($__bb_apiTexto);
		}
	}

	$telefone = bb_tel55($tel_cli);

	// agendar o alerta de confirmação
	$hora_atual = date('H:i:s');
	$data_atual = date('Y-m-d');
	$hora_minutos = @strtotime("-$minutos_aviso hours", @strtotime($hora));
	$nova_hora = date('H:i:s', $hora_minutos);

	$mensagem = "*Confirmação de Agendamento*\n";
	$mensagem .= "Profissional: *{$nome_func}*\n";
	$mensagem .= "Serviço: *{$nome_serv}*\n";
	$mensagem .= "Data: *{$dataF}*\n";
	$mensagem .= "Hora: *{$horaF}*\n";
	$mensagem .= "_(Digite o número com a opção desejada)_\n";
	$mensagem .= "1. 1️⃣ para confirmar ✅\n";
	$mensagem .= "2. 2️⃣ para Cancelar ❌\n";
	$id_envio = "{$ult_id}";
	$data_envio = "{$data_agd} {$hora_do_agd}";

	// ✅ IMPORTANTE (Marketplace / padrão do sistema):
	// O arquivo confirmacao.php deve saber a antecedência do envio.
	// No BarberBot, essa antecedência é lida do config (minutos_aviso / dias_aviso).
	// Sem $antAgendamento, a confirmação cai no MESMO horário do serviço.
	$antAgendamento = (int)$minutos_aviso;
	
	// Resolver do confirmacao.php (prioriza /pagamentos/confirmacao.php e mantém compatibilidade com /ajax/confirmacao.php)
	$__bb_confirmacao = __DIR__ . "/confirmacao.php";
	if (!file_exists($__bb_confirmacao)) {
		$__bb_confirmacao = __DIR__ . "/../ajax/confirmacao.php";
	}
	
	if (file_exists($__bb_confirmacao)) {
	
		require($__bb_confirmacao);
	
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
		@error_log("BarberBot: confirmacao.php não encontrado. Tentou: " . __DIR__ . "/confirmacao.php e ../ajax/confirmacao.php");
	}
}

// Marcar horários ocupados (mesma lógica)
$stmtInsHorario = $pdo->prepare("INSERT INTO horarios_agd SET agendamento = :agd, horario = :horario, funcionario = :func, data = :data");

while (@strtotime($hora) < @strtotime($hora_final_servico)) {

	$hora_minutos = @strtotime("+$intervalo minutes", @strtotime($hora));
	$hora = date('H:i:s', $hora_minutos);

	if (@strtotime($hora) < @strtotime($hora_final_servico)) {
		$stmtInsHorario->execute([
			':agd' => (int)$ult_id,
			':horario' => $hora,
			':func' => (int)$funcionario,
			':data' => $data_agd
		]);
	}
}

// Remover temporário e retornar (mesma lógica)
if ($id_conta != "" && !$statusPay) {

	$stmtDel = $pdo->prepare("DELETE FROM agendamentos_temp WHERE id = :id");
	$stmtDel->bindValue(':id', (int)$id_conta, PDO::PARAM_INT);
	$stmtDel->execute();

	// Redireciona para a área do cliente na loja (mantendo padrão /loja/{slug}/...)
	if ($bb_loja !== '') {
		$dest = "/loja/{$bb_loja}/meus-agendamentos.php";
	} else {
		$dest = "../meus-agendamentos.php";
	}

	echo "<script>window.location='{$dest}'</script>";

} elseif ($statusPay) {

	$stmtDel = $pdo->prepare("DELETE FROM agendamentos_temp WHERE ref_pix = :ref");
	$stmtDel->bindValue(':ref', $ref_pix);
	$stmtDel->execute();

	echo json_encode(array("status" => "pago", "novoId" => $id_receber));
	exit();

} else {

	$stmtDel = $pdo->prepare("DELETE FROM agendamentos_temp WHERE ref_pix = :ref");
	$stmtDel->bindValue(':ref', $ref_pix);
	$stmtDel->execute();
}

?>