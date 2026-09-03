<?php
$bb_ajax_context_mode = 'tenant';
$bb_ajax_context_response = 'plain';
require_once __DIR__ . '/_context_bootstrap.php';

// Evita notices caso variáveis de config não existam (sem mudar lógica)
if (!isset($not_sistema)) { $not_sistema = 'Não'; }
if (!isset($msg_agendamento)) { $msg_agendamento = ''; }
if (!isset($whatsapp_sistema)) { $whatsapp_sistema = ''; }

// ID do agendamento (aceita id / id_agendamento, POST ou GET)
$idRaw = $_POST['id'] ?? $_POST['id_agendamento'] ?? $_GET['id'] ?? $_GET['id_agendamento'] ?? '';
if (is_array($idRaw)) { $idRaw = ''; }
$idRaw = trim((string)$idRaw);
$id = (int)preg_replace('/\D+/', '', $idRaw);

$acaoRaw = $_POST['acao'] ?? $_GET['acao'] ?? '';
if (is_array($acaoRaw)) { $acaoRaw = ''; }
$acaoRaw = strtolower(trim((string)$acaoRaw));
$modoEditar = ($acaoRaw === 'editar');

if ($id <= 0) {
    echo 'ID inválido!';
    exit();
}

// Busca o agendamento (mantém lógica)
$stmt = $pdo->prepare("SELECT * FROM agendamentos WHERE id = :id LIMIT 1");
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$agd = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$agd) {
    echo 'ID inválido!';
    exit();
}

$cliente  = (int)($agd['cliente'] ?? 0);
$usuario  = (int)($agd['funcionario'] ?? 0);
$data     = (string)($agd['data'] ?? '');
$hora     = (string)($agd['hora'] ?? '');
$servico  = (int)($agd['servico'] ?? 0);
$hash     = (string)($agd['hash'] ?? '');

$dataF = ($data !== '') ? implode('/', array_reverse(explode('-', $data))) : '';
$horaF = ($hora !== '') ? date("H:i", strtotime($hora)) : '';

// Exclui agendamento e libera horário (mesma lógica)
try {
    $pdo->beginTransaction();

    $stmt = $pdo->prepare("DELETE FROM agendamentos WHERE id = :id");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $stmt = $pdo->prepare("DELETE FROM horarios_agd WHERE agendamento = :id");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    $pdo->commit();
} catch (Exception $e) {
    if ($pdo->inTransaction()) { $pdo->rollBack(); }
    echo 'Erro ao excluir!';
    exit();
}

// Quando a chamada vem do botão Editar, guarda os dados originais para a tela de agendamento
// abrir com o serviço/profissional/data/hora já selecionados.
if ($modoEditar) {
    $_SESSION['editar_agendamento'] = [
        'id' => $id,
        'cliente' => $cliente,
        'servico' => $servico,
        'funcionario' => $usuario,
        'data' => $data,
        'hora' => $hora
    ];

    // Garante que a próxima página leia a sessão já atualizada imediatamente após o redirect.
    if (session_status() === PHP_SESSION_ACTIVE) {
        @session_write_close();
    }
}

// Resposta rápida para o front-end (mantém a mensagem esperada em outros módulos)
@ob_clean();
echo 'Cancelado com Sucesso';

// Envia a resposta agora e continua processando (não trava UI)
if (function_exists('fastcgi_finish_request')) {
    @fastcgi_finish_request();
} else {
    @flush();
}
@ignore_user_abort(true);
@set_time_limit(60);

// ===============================
// Notificações e WhatsApp
// ===============================

// Dados do cliente somente se necessário
$nome_cliente = '';
$telefone_cliente = '';
if ($not_sistema === 'Sim' || $msg_agendamento === 'Api' || $msg_agendamento === 'Sim') {
    $stmt = $pdo->prepare("SELECT nome, telefone FROM clientes WHERE id = :id LIMIT 1");
    $stmt->bindValue(':id', $cliente, PDO::PARAM_INT);
    $stmt->execute();
    $cli = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($cli) {
        $nome_cliente = (string)($cli['nome'] ?? '');
        $telefone_cliente = (string)($cli['telefone'] ?? '');
    }
}

// Notificação interna do sistema (mantém lógica)
if ($not_sistema === 'Sim') {
    $mensagem_not = $nome_cliente;
    $titulo_not = 'Agendamento Cancelado ' . $dataF . ' - ' . $horaF;
    $id_usu = $usuario;

    // Mantém compatibilidade com estrutura existente (se o arquivo existir no servidor)
    $arquivo_notid = __DIR__ . "/api/notid.php";
    if (file_exists($arquivo_notid)) {
        require $arquivo_notid;
    }
}

// WhatsApp (API) - avisa cliente e profissional (mantém lógica)
if ($msg_agendamento === 'Api' || $msg_agendamento === 'Sim') {

    // Profissional
    $nome_func = '';
    $tel_func = '';
    if ($usuario > 0) {
        $stmt = $pdo->prepare("SELECT nome, telefone FROM usuarios WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', $usuario, PDO::PARAM_INT);
        $stmt->execute();
        $u = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($u) {
            $nome_func = (string)($u['nome'] ?? '');
            $tel_func = (string)($u['telefone'] ?? '');
        }
    }

    // Serviço
    $nome_serv = '';
    if ($servico > 0) {
        $stmt = $pdo->prepare("SELECT nome FROM servicos WHERE id = :id LIMIT 1");
        $stmt->bindValue(':id', $servico, PDO::PARAM_INT);
        $stmt->execute();
        $s = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($s) {
            $nome_serv = (string)($s['nome'] ?? '');
        }
    }

    $mensagem = '_Agendamento Cancelado_ %0A';
    $mensagem .= 'Profissional: *' . $nome_func . '* %0A';
    $mensagem .= 'Serviço: *' . $nome_serv . '* %0A';
    $mensagem .= 'Data: *' . $dataF . '* %0A';
    $mensagem .= 'Hora: *' . $horaF . '* %0A';
    $mensagem .= 'Cliente: *' . $nome_cliente . '* %0A';

    // Arquivo de envio WhatsApp (mantém prioridade do seu projeto)
    $arquivo_envio = __DIR__ . "/../pagamentos/api-texto.php";
    if (!file_exists($arquivo_envio)) {
        $arquivo_envio = __DIR__ . "/api-texto.php"; // /public_html/ajax/api-texto.php (se existir)
    }
    if (!file_exists($arquivo_envio)) {
        $arquivo_envio = __DIR__ . "/../sistema/painel/apis/texto.php"; // legado (se existir)
    }

    // 1) Envia para o CLIENTE
    $telefone = '55' . preg_replace('/\D+/', '', (string)$telefone_cliente);
    if (file_exists($arquivo_envio)) {
        require $arquivo_envio;
    }

    // 2) Envia para o PROFISSIONAL (se diferente do WhatsApp do sistema)
    $tel_func_envio = '55' . preg_replace('/\D+/', '', (string)$tel_func);
    $tel_sistema_envio = '55' . preg_replace('/\D+/', '', (string)$whatsapp_sistema);

    if ($tel_func_envio !== '55' && $tel_func_envio !== $tel_sistema_envio) {
        $telefone = $tel_func_envio;
        if (file_exists($arquivo_envio)) {
            require $arquivo_envio;
        }
    }

    // Se tiver hash, remove a reserva/pendência (mantém lógica)
    if ($hash !== "") {
        $arquivo_delete = __DIR__ . '/agendar-delete.php';
        if (!file_exists($arquivo_delete)) {
            $arquivo_delete = __DIR__ . '/../pagamentos/agendar-delete.php';
        }
        if (file_exists($arquivo_delete)) {
            require $arquivo_delete;
        }
    }
}

?>
