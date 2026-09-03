<?php
require_once __DIR__ . '/../_context_bootstrap.php';
// ============================================================================
// Arquivo: /public_html/sistema/painel/paginas/agenda/inserir.php (AJAX)
// PADRÃO "BLINDADO MARKETPLACE" (SEM MUDAR A LÓGICA DO AGENDAMENTO)
// ----------------------------------------------------------------------------
// O que este patch garante:
// 1) Detecta SLUG (GET/POST/URI) ANTES do bootstrap, para o conexao.php
//    conectar no banco TENANT correto (evita dados cruzando).
// 2) Usa namespace de sessão por perfil:
//    - Admin: $_SESSION['BARBER_ADMIN']['id']
//    - Loja:  $_SESSION['BARBER_LOJA']['id']
//    - Tenant Atual (opcional): $_SESSION['BARBER_LOJA']['database_name']
// 3) Mantém a regra atual de agenda/validação de horários.
// 4) Segurança: sanitização + prepared statements.
// ============================================================================

@chdir(__DIR__);

// ---------------------------------------------------------------------------
// 1) Detectar SLUG antes do bootstrap (conexao.php)
// ---------------------------------------------------------------------------
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
    // Força o bootstrap a conectar no tenant correto
    $_GET['loja'] = $__bb_slug;
}

// ---------------------------------------------------------------------------
// 2) Bootstrap do sistema (conexao.php faz: sessão + PDO do tenant)
// ---------------------------------------------------------------------------
require_once __DIR__ . '/../../../conexao.php';

// ---------------------------------------------------------------------------
// 3) Segurança básica do endpoint
// ---------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(403);
    exit('Acesso inválido');
}

// Sessão por perfil (padrão profissional do BarberBot)
$usuario_logado = (int)($_SESSION['BARBER_LOJA']['id'] ?? ($_SESSION['BARBER_ADMIN']['id'] ?? 0));
if ($usuario_logado <= 0) {
    http_response_code(401);
    exit('Sessão expirada. Faça login novamente.');
}

$tabela = 'agendamentos';

// ---------------------------------------------------------------------------
// 4) Receber e sanitizar POST
// ---------------------------------------------------------------------------
$cliente     = (int)($_POST['cliente'] ?? 0);
$data        = (string)($_POST['data'] ?? '');
$hora        = (string)($_POST['hora'] ?? '');
$obs         = (string)($_POST['obs'] ?? '');
$id          = (int)($_POST['id'] ?? 0);
$funcionario = (int)($_POST['funcionario'] ?? 0);
$servico     = (int)($_POST['servico'] ?? 0);

$data_agd    = $data;
$hora_do_agd = $hora;
$hash        = '';

if ($hora === '') {
    echo 'Selecione um Horário antes de agendar!';
    exit();
}

if ($cliente <= 0 || $funcionario <= 0 || $servico <= 0 || $data_agd === '') {
    echo 'Dados inválidos para agendamento!';
    exit();
}

// ---------------------------------------------------------------------------
// 5) Carregar config (sem chutar: só lê se existir)
// ---------------------------------------------------------------------------
$not_sistema = 'Não';
$msg_agendamento = $msg_agendamento ?? null;
$minutos_aviso = $minutos_aviso ?? null;

try {
    $cfg = $pdo->query("SELECT * FROM config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if (is_array($cfg)) {
        if (isset($cfg['not_sistema'])) {
            $not_sistema = (string)$cfg['not_sistema'];
        }
        if ($msg_agendamento === null && isset($cfg['msg_agendamento'])) {
            $msg_agendamento = (string)$cfg['msg_agendamento'];
        }
        if ($minutos_aviso === null && isset($cfg['minutos_aviso'])) {
            $minutos_aviso = (int)$cfg['minutos_aviso'];
        }
    }
} catch (Throwable $e) {
    // mantém defaults
}

$msg_agendamento = (string)($msg_agendamento ?? '');
$minutos_aviso = (int)($minutos_aviso ?? 0);

// ---------------------------------------------------------------------------
// 6) Buscar profissional/serviço
// ---------------------------------------------------------------------------
$stUser = $pdo->prepare("SELECT nome, intervalo FROM usuarios WHERE id = ?");
$stUser->execute([$funcionario]);
$userRow = $stUser->fetch(PDO::FETCH_ASSOC);

if (!$userRow) {
    echo 'Funcionário inválido!';
    exit();
}

$intervalo = (int)($userRow['intervalo'] ?? 30);
$nome_func = (string)($userRow['nome'] ?? '');

$stServ = $pdo->prepare("SELECT nome, tempo FROM servicos WHERE id = ?");
$stServ->execute([$servico]);
$servRow = $stServ->fetch(PDO::FETCH_ASSOC);

if (!$servRow) {
    echo 'Serviço inválido!';
    exit();
}

$tempo = (int)($servRow['tempo'] ?? 30);
$nome_serv = (string)($servRow['nome'] ?? '');

// hora final do serviço
$hora_minutos = strtotime("+$tempo minutes", strtotime($hora));
$hora_final_servico = date('H:i:s', $hora_minutos);

// ---------------------------------------------------------------------------
// 7) Validar se o funcionário trabalha no dia
// ---------------------------------------------------------------------------
$diasemana = array("Domingo","Segunda-Feira","Terça-Feira","Quarta-Feira","Quinta-Feira","Sexta-Feira","Sabado");
$diasemana_numero = date('w', strtotime($data));
$dia_procurado = $diasemana[$diasemana_numero] ?? '';

$stDia = $pdo->prepare("SELECT inicio, final, inicio_almoco, final_almoco FROM dias WHERE funcionario = ? AND dia = ?");
$stDia->execute([$funcionario, $dia_procurado]);
$diaRow = $stDia->fetch(PDO::FETCH_ASSOC);

if (!$diaRow) {
    echo 'Este Funcionário não trabalha neste Dia!';
    exit();
}

$inicio = (string)$diaRow['inicio'];
$final = (string)$diaRow['final'];
$inicio_almoco = (string)$diaRow['inicio_almoco'];
$final_almoco = (string)$diaRow['final_almoco'];

// ---------------------------------------------------------------------------
// 8) Validações de disponibilidade (mesma regra do arquivo original)
// ---------------------------------------------------------------------------
$dataF = implode('/', array_reverse(explode('-', $data)));
$horaF = date("H:i", strtotime($hora));

$nova_hora = $hora;

// prepara statements usados no loop (performance)
$stHorAgd = $pdo->prepare("SELECT id FROM horarios_agd WHERE data = ? AND funcionario = ? AND horario = ? LIMIT 1");
$stAgdHora = $pdo->prepare("SELECT id, hora FROM agendamentos WHERE data = ? AND funcionario = ? AND hora = ? LIMIT 1");

while (strtotime($nova_hora) < strtotime($hora_final_servico)) {

    $hora_minutos = strtotime("+$intervalo minutes", strtotime($nova_hora));
    $nova_hora = date('H:i:s', $hora_minutos);

    // VERIFICAR NA TABELA HORARIOS AGD SE TEM O HORARIO NESSA DATA
    $stHorAgd->execute([$data, $funcionario, $nova_hora]);
    $res_agd = $stHorAgd->fetch(PDO::FETCH_ASSOC);
    if ($res_agd) {
        echo 'Este serviço demora cerca de ' . $tempo . ' minutos, precisa escolher outro horário, pois neste horários não temos disponibilidade devido a outros agendamentos!';
        exit();
    }

    // VERIFICAR NA TABELA AGENDAMENTOS SE TEM O HORARIO NESSA DATA
    $stAgdHora->execute([$data, $funcionario, $nova_hora]);
    $res2 = $stAgdHora->fetch(PDO::FETCH_ASSOC);
    if ($res2) {
        if ($tempo <= $intervalo) {
            // mantém lógica
        } else {
            if ($hora_final_servico == ($res2['hora'] ?? '')) {
                // mantém lógica
            } else {
                echo 'Este serviço demora cerca de ' . $tempo . ' minutos, precisa escolher outro horário, pois neste horários não temos disponibilidade devido a outros agendamentos!';
                exit();
            }
        }
    }

    if (strtotime($nova_hora) > strtotime($inicio_almoco) && strtotime($nova_hora) < strtotime($final_almoco)) {
        echo 'Este serviço demora cerca de ' . $tempo . ' minutos, precisa escolher outro horário, pois neste horários não temos disponibilidade devido ao horário de almoço!';
        exit();
    }
}

// validar horario (mesma regra)
$stVal = $pdo->prepare("SELECT id FROM {$tabela} WHERE data = ? AND hora = ? AND funcionario = ? LIMIT 1");
$stVal->execute([$data, $hora, $funcionario]);
$valRow = $stVal->fetch(PDO::FETCH_ASSOC);

if ($valRow && (int)$valRow['id'] != $id) {
    echo 'Este horário não está disponível!';
    exit();
}

// ---------------------------------------------------------------------------
// 9) Pegar nome/telefone do cliente
// ---------------------------------------------------------------------------
$stCli = $pdo->prepare("SELECT nome, telefone FROM clientes WHERE id = ?");
$stCli->execute([$cliente]);
$cliRow = $stCli->fetch(PDO::FETCH_ASSOC);

if (!$cliRow) {
    echo 'Cliente inválido!';
    exit();
}

$nome_cliente = (string)($cliRow['nome'] ?? '');
$telefone_cliente = (string)($cliRow['telefone'] ?? '');

// Notificação no painel (mantém fluxo)
if ($not_sistema === 'Sim') {
    $mensagem_not = $nome_cliente;
    $titulo_not = 'Novo Agendamento ' . $dataF . ' - ' . $horaF;
    $id_usu = $funcionario;
    require __DIR__ . '/../../../../api/notid.php';
}

// ---------------------------------------------------------------------------
// 10) Se msg_agendamento = Api, prepara variáveis para confirmação (mantém regra)
// ---------------------------------------------------------------------------
if ($msg_agendamento === 'Api') {

    // agendar o alerta de confirmação
    $hora_atual = date('H:i:s');
    $data_atual = date('Y-m-d');

    // ⚠️ Mantém o cálculo original deste arquivo (minutos no strtotime)
    $hora_minutos = strtotime("-{$minutos_aviso} minutes", strtotime($hora));
    $nova_hora = date('H:i:s', $hora_minutos);

    // normaliza telefone (evita duplicar 55)
    $digits = preg_replace('/\D+/', '', $telefone_cliente);
    if ($digits !== '' && strpos($digits, '55') !== 0) {
        $digits = '55' . $digits;
    }
    $telefone = $digits;
}

// ---------------------------------------------------------------------------
// 11) Inserir agendamento (mesma lógica, agora com prepared)
// ---------------------------------------------------------------------------
$stIns = $pdo->prepare("
    INSERT INTO {$tabela}
    (funcionario, cliente, hora, data, usuario, status, obs, data_lanc, servico, hash)
    VALUES
    (:funcionario, :cliente, :hora, :data_agd, :usuario, 'Agendado', :obs, curDate(), :servico, :hash)
");
$stIns->execute([
    ':funcionario' => $funcionario,
    ':cliente'     => $cliente,
    ':hora'        => $hora,
    ':data_agd'    => $data_agd,
    ':usuario'     => $usuario_logado,
    ':obs'         => $obs,
    ':servico'     => $servico,
    ':hash'        => $hash,
]);

$ult_id = (int)$pdo->lastInsertId();

// ---------------------------------------------------------------------------
// 12) Disparo de confirmação (corrigido no padrão: usa $antAgendamento)
// ---------------------------------------------------------------------------
if ($msg_agendamento === 'Api') {
    if (strtotime($hora_atual) < strtotime($nova_hora) || strtotime($data_atual) != strtotime($data_agd)) {

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

            // ✅ PONTO PRINCIPAL:
            // confirmacao.php (Menuia) antecipa usando $antAgendamento EM HORAS.
            // Aqui seguimos o modelo do sistema: repassa $minutos_aviso como está (valor do config).
            $antAgendamento = (int)$minutos_aviso;

            // Resolver do confirmacao.php (sem risco de caminho quebrar)
            $__bb_root = rtrim(($_SERVER['DOCUMENT_ROOT'] ?? ''), '/');
            $__bb_confirmacao = '';
            if ($__bb_root !== '') {
                $__bb_confirmacao = $__bb_root . '/pagamentos/confirmacao.php';
                if (!file_exists($__bb_confirmacao)) {
                    $__bb_confirmacao = $__bb_root . '/ajax/confirmacao.php';
                }
            }

            if ($__bb_confirmacao === '' || !file_exists($__bb_confirmacao)) {
                // fallback relativo (mantém compatibilidade)
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
                    $stHash = $pdo->prepare("UPDATE agendamentos SET hash = :hash WHERE id = :id");
                    $stHash->execute([
                        ':hash' => $id_hash,
                        ':id'   => $ult_id
                    ]);
                }
            } else {
                @error_log("BarberBot: confirmacao.php não encontrado em inserir.php");
            }
        }
    }
}

// ---------------------------------------------------------------------------
// 13) Inserir horários ocupados (mesma lógica)
// ---------------------------------------------------------------------------
$stInsHor = $pdo->prepare("INSERT INTO horarios_agd (agendamento, horario, funcionario, data) VALUES (?, ?, ?, ?)");

while (strtotime($hora) < strtotime($hora_final_servico)) {

    $hora_minutos = strtotime("+$intervalo minutes", strtotime($hora));
    $hora = date('H:i:s', $hora_minutos);

    if (strtotime($hora) < strtotime($hora_final_servico)) {
        $stInsHor->execute([$ult_id, $hora, $funcionario, $data_agd]);
    }
}

echo 'Salvo com Sucesso';
?>