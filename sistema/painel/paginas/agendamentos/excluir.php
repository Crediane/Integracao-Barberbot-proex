<?php
require_once __DIR__ . '/../_context_bootstrap.php';
// ============================================================================
// /public_html/sistema/painel/paginas/agendamentos/excluir.php
// CORREÇÃO MARKETPLACE (sem mudar a lógica)
// ----------------------------------------------------------------------------
// Problema: deletava, mas não enviava WhatsApp.
// Causa mais comum: require('../../../../ajax/api-texto.php') falha por caminho relativo (CWD)
//              e/ou $api/$token/$instancia não estavam carregados do config do TENANT.
// Solução:
// 1) Detecta SLUG antes do conexao.php (garante TENANT correto)
// 2) Usa __DIR__ + file_exists nos requires (não depende do CWD)
// 3) Carrega config e seta $msg_agendamento / $not_sistema / $api / $token / $instancia
// 4) Mantém a mesma mensagem e o mesmo fluxo.
// ============================================================================

$tabela = 'agendamentos';

// ---------------------------------------------------------------------------
// 0) Segurança: este endpoint é POST (AJAX). Abrir no navegador vai dar inválido.
// ---------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(403);
    exit('Acesso inválido');
}

// ---------------------------------------------------------------------------
// 1) Detecta SLUG (Marketplace) ANTES do conexao.php
//    - POST/GET/URI/REFERER
// ---------------------------------------------------------------------------
if (!function_exists('bb_clean_slug')) {
    function bb_clean_slug($v){
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
    // o conexao.php do marketplace normalmente decide o TENANT por $_GET['loja']
    $_GET['loja'] = $__bb_slug;
}

// ---------------------------------------------------------------------------
// 2) Bootstrap do TENANT (mantém padrão BarberBot)
// ---------------------------------------------------------------------------
require_once(__DIR__ . "/../../../conexao.php");

// ---------------------------------------------------------------------------
// 3) Sanitização do ID
// ---------------------------------------------------------------------------
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    exit('ID inválido!');
}

// ---------------------------------------------------------------------------
// 4) Busca dados do agendamento (antes de excluir)
// ---------------------------------------------------------------------------
$stAg = $pdo->prepare("SELECT * FROM {$tabela} WHERE id = :id LIMIT 1");
$stAg->bindValue(':id', $id, PDO::PARAM_INT);
$stAg->execute();
$ag = $stAg->fetch(PDO::FETCH_ASSOC);

if (!$ag) {
    exit('ID inválido!');
}

$cliente = (int)($ag['cliente'] ?? 0);
$usuario = (int)($ag['funcionario'] ?? 0);
$data    = (string)($ag['data'] ?? '');
$hora    = (string)($ag['hora'] ?? '');
$servico = (int)($ag['servico'] ?? 0);
$hash    = (string)($ag['hash'] ?? '');

// Formata data/hora
$dataF = $data ? implode('/', array_reverse(explode('-', $data))) : '';
$horaF = $hora ? date("H:i", strtotime($hora)) : '';

// Cliente
$nome_cliente = '';
$tel_cliente = '';
$stCli = $pdo->prepare("SELECT nome, telefone FROM clientes WHERE id = :id LIMIT 1");
$stCli->bindValue(':id', $cliente, PDO::PARAM_INT);
$stCli->execute();
$cli = $stCli->fetch(PDO::FETCH_ASSOC);
if ($cli) {
    $nome_cliente = (string)($cli['nome'] ?? '');
    $tel_cliente  = (string)($cli['telefone'] ?? '');
}

// ---------------------------------------------------------------------------
// 5) Excluir (mesma lógica)
// ---------------------------------------------------------------------------
$stDel = $pdo->prepare("DELETE FROM {$tabela} WHERE id = :id");
$stDel->bindValue(':id', $id, PDO::PARAM_INT);
$stDel->execute();

$stDel2 = $pdo->prepare("DELETE FROM horarios_agd WHERE agendamento = :id");
$stDel2->bindValue(':id', $id, PDO::PARAM_INT);
$stDel2->execute();

// Retorno do AJAX (mantém padrão)
echo 'Excluído com Sucesso';

// ---------------------------------------------------------------------------
// 6) Cancela disparo agendado (se tiver hash)
// ---------------------------------------------------------------------------
if ($hash !== '') {
    $file_delete = __DIR__ . '/../../../../ajax/agendar-delete.php';
    if (file_exists($file_delete)) {
        // evita "lixo" no retorno do AJAX
        ob_start();
        require $file_delete;
        ob_end_clean();
    }
}

// ---------------------------------------------------------------------------
// 7) Carrega config do TENANT (tokens e flags)
// ---------------------------------------------------------------------------
$conf = [];
try {
    $conf = $pdo->query("SELECT * FROM config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $conf = [];
}

if (!function_exists('bb_pick_first')) {
    function bb_pick_first(array $arr, array $keys){
        foreach ($keys as $k) {
            if (isset($arr[$k]) && trim((string)$arr[$k]) !== '') {
                return (string)$arr[$k];
            }
        }
        return '';
    }
}

// Flags (padrão do sistema)
$msg_agendamento = $msg_agendamento ?? (string)($conf['msg_agendamento'] ?? '');
$not_sistema     = $not_sistema ?? (string)($conf['not_sistema'] ?? 'Não');

// Tokens Menuia (tokens em config, como você confirmou)
// OBS: No BarberBot antigo, api-texto.php usa:
// - $api == "menuia"
// - $token = appkey
// - $instancia = authkey
$api = $api ?? (string)($conf['api'] ?? '');
$token = $token ?? bb_pick_first($conf, ['token','appkey','menuia_appkey']);
$instancia = $instancia ?? bb_pick_first($conf, ['instancia','authkey','menuia_authkey']);

// fallback: se token/instancia existem e api vazio, assume menuia
if (trim((string)$api) === '' && (trim((string)$token) !== '' || trim((string)$instancia) !== '')) {
    $api = 'menuia';
}

// ---------------------------------------------------------------------------
// 8) WhatsApp: mantém regra: só envia se msg_agendamento == 'Api'
// ---------------------------------------------------------------------------
if ($msg_agendamento === 'Api') {

    // Profissional
    $nome_func = '';
    $tel_func = '';
    $stFun = $pdo->prepare("SELECT nome, telefone FROM usuarios WHERE id = :id LIMIT 1");
    $stFun->bindValue(':id', $usuario, PDO::PARAM_INT);
    $stFun->execute();
    $fun = $stFun->fetch(PDO::FETCH_ASSOC);
    if ($fun) {
        $nome_func = (string)($fun['nome'] ?? '');
        $tel_func  = (string)($fun['telefone'] ?? '');
    }

    // Serviço
    $nome_serv = '';
    $stServ = $pdo->prepare("SELECT nome FROM servicos WHERE id = :id LIMIT 1");
    $stServ->bindValue(':id', $servico, PDO::PARAM_INT);
    $stServ->execute();
    $nome_serv = (string)($stServ->fetchColumn() ?: '');

    // Mensagem (idêntica ao sistema)
    $mensagem = '_Agendamento Cancelado_ %0A';
    $mensagem .= 'Profissional: *' . $nome_func . '* %0A';
    $mensagem .= 'Serviço: *' . $nome_serv . '* %0A';
    $mensagem .= 'Data: *' . $dataF . '* %0A';
    $mensagem .= 'Hora: *' . $horaF . '* %0A';
    $mensagem .= 'Cliente: *' . $nome_cliente . '* %0A';

    // Caminho do api-texto (não depende do CWD)
    $api_texto = __DIR__ . '/../../../../ajax/api-texto.php';
    if (!file_exists($api_texto)) {
        // fallback se o projeto estiver usando /sistema/ajax/
        $api_texto = __DIR__ . '/../../../../pagamentos/api-texto.php';
    }

    // 8.1) Enviar para o CLIENTE
    $telefone = '55' . preg_replace('/[ ()-]+/', '', (string)$tel_cliente);
    if (file_exists($api_texto)) {
        ob_start();
        require $api_texto;
        ob_end_clean();
    } else {
        @error_log('BarberBot excluir.php: api-texto.php não encontrado (cliente)');
    }

    // 8.2) Avisar o PROFISSIONAL
    $telefone = '55' . preg_replace('/[ ()-]+/', '', (string)$tel_func);
    if (file_exists($api_texto)) {
        ob_start();
        require $api_texto;
        ob_end_clean();
    } else {
        @error_log('BarberBot excluir.php: api-texto.php não encontrado (profissional)');
    }
}

// ---------------------------------------------------------------------------
// 9) Notificação do sistema (mantém regra)
// ---------------------------------------------------------------------------
if ($not_sistema === 'Sim') {
    $mensagem_not = $nome_cliente;
    $titulo_not = 'Agendamento Cancelado ' . $dataF . ' - ' . $horaF;
    $id_usu = $usuario;

    $notid = __DIR__ . '/../../../../api/notid.php';
    if (file_exists($notid)) {
        ob_start();
        require $notid;
        ob_end_clean();
    }
}

?>