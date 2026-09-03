<?php
require_once __DIR__ . '/../_context_bootstrap.php';
// ============================================================================
// /public_html/sistema/painel/paginas/agenda/excluir.php
// CORREÇÃO MARKETPLACE (sem mudar a lógica de negócio)
// ----------------------------------------------------------------------------
// Objetivo:
// 1) Excluir agendamento e liberar horário (mesma lógica)
// 2) Enviar WhatsApp de CANCELAMENTO para o CLIENTE (quando msg_agendamento == 'Api')
// 3) Cancelar qualquer disparo agendado (hash) para NÃO enviar confirmação depois
// 4) Garantir contexto multi-tenant (/loja/{slug}/) antes do conexao.php
// 5) Resposta limpa para o AJAX (sem "echo" de requires auxiliares)
// ----------------------------------------------------------------------------
// Observação: Não altera regras financeiras/agendamento/validações.
// Apenas corrige integração/paths e robustez.
// ============================================================================

$tabela = 'agendamentos';

// ---------------------------------------------------------------------------
// 0) Segurança: endpoint é POST (AJAX)
// ---------------------------------------------------------------------------
if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    http_response_code(403);
    exit('Acesso inválido');
}

// ---------------------------------------------------------------------------
// 1) Detecta SLUG (Marketplace) ANTES do conexao.php (POST/GET/URI/REFERER)
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
// 2) Bootstrap do TENANT/MASTER (mantém padrão do sistema)
// ---------------------------------------------------------------------------
require_once __DIR__ . '/../../../conexao.php';

// ---------------------------------------------------------------------------
// 3) Sanitização do ID
// ---------------------------------------------------------------------------
$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) {
    exit('ID inválido');
}

// ---------------------------------------------------------------------------
// 4) Busca dados do agendamento (antes de excluir)
// ---------------------------------------------------------------------------
$stAg = $pdo->prepare("SELECT * FROM {$tabela} WHERE id = :id LIMIT 1");
$stAg->bindValue(':id', $id, PDO::PARAM_INT);
$stAg->execute();
$ag = $stAg->fetch(PDO::FETCH_ASSOC);

if (!$ag) {
    exit('Registro não encontrado');
}

$cliente = (int)($ag['cliente'] ?? 0);
$usuario  = (int)($ag['funcionario'] ?? 0);
$data     = (string)($ag['data'] ?? '');
$hora     = (string)($ag['hora'] ?? '');
$servico  = (int)($ag['servico'] ?? 0);
$hash     = (string)($ag['hash'] ?? '');

$dataF = $data ? implode('/', array_reverse(explode('-', $data))) : '';
$horaF = $hora ? date("H:i", strtotime($hora)) : '';

// ---------------------------------------------------------------------------
// 5) Busca dados do cliente (para notificação/WhatsApp)
// ---------------------------------------------------------------------------
$nome_cliente = '';
$tel_cliente  = '';

if ($cliente > 0) {
    $stCli = $pdo->prepare("SELECT nome, telefone FROM clientes WHERE id = :id LIMIT 1");
    $stCli->bindValue(':id', $cliente, PDO::PARAM_INT);
    $stCli->execute();
    $cli = $stCli->fetch(PDO::FETCH_ASSOC);
    if ($cli) {
        $nome_cliente = (string)($cli['nome'] ?? '');
        $tel_cliente  = (string)($cli['telefone'] ?? '');
    }
}

// ---------------------------------------------------------------------------
// 6) Excluir (mesma lógica: apaga agendamento e libera horário)
// ---------------------------------------------------------------------------
$stDel = $pdo->prepare("DELETE FROM {$tabela} WHERE id = :id");
$stDel->bindValue(':id', $id, PDO::PARAM_INT);
$stDel->execute();

$stDel2 = $pdo->prepare("DELETE FROM horarios_agd WHERE agendamento = :id");
$stDel2->bindValue(':id', $id, PDO::PARAM_INT);
$stDel2->execute();

// Resposta limpa para o AJAX
echo 'Excluído com Sucesso';

// ---------------------------------------------------------------------------
// 7) Cancela disparos agendados (para não enviar confirmação depois)
//    - Mantém compatibilidade com o seu projeto:
//      a) /ajax/agendar-delete.php (quando existir)
//      b) /ajax/api-excluir.php (legado)
// ---------------------------------------------------------------------------
if ($hash !== '') {

    // 7.1) Novo/mais comum
    $file_agendar_delete = __DIR__ . '/../../../../ajax/agendar-delete.php';
    if (file_exists($file_agendar_delete)) {
        ob_start();
        require $file_agendar_delete;
        ob_end_clean();
    }

    // 7.2) Legado (se existir no seu projeto)
    $file_api_excluir = __DIR__ . '/../../../../ajax/api-excluir.php';
    if (file_exists($file_api_excluir)) {
        ob_start();
        require $file_api_excluir;
        ob_end_clean();
    }
}

// ---------------------------------------------------------------------------
// 8) Carrega config do TENANT (flags e tokens Menuia)
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

// Flags do sistema
$msg_agendamento = $msg_agendamento ?? (string)($conf['msg_agendamento'] ?? '');
$not_sistema     = $not_sistema ?? (string)($conf['not_sistema'] ?? 'Não');

// Tokens/param Menuia (padrão BarberBot)
$api       = $api ?? (string)($conf['api'] ?? '');
$token     = $token ?? bb_pick_first($conf, ['token','appkey','menuia_appkey']);
$instancia = $instancia ?? bb_pick_first($conf, ['instancia','authkey','menuia_authkey']);

if (trim((string)$api) === '' && (trim((string)$token) !== '' || trim((string)$instancia) !== '')) {
    $api = 'menuia';
}

// ---------------------------------------------------------------------------
// 9) WhatsApp: enviar CANCELAMENTO para o CLIENTE
//    (mesma regra do sistema: só envia se msg_agendamento == 'Api')
// ---------------------------------------------------------------------------
if ($msg_agendamento === 'Api') {

    // Profissional (para compor mensagem completa)
    $nome_func = '';
    $tel_func  = '';

    if ($usuario > 0) {
        $stFun = $pdo->prepare("SELECT nome, telefone FROM usuarios WHERE id = :id LIMIT 1");
        $stFun->bindValue(':id', $usuario, PDO::PARAM_INT);
        $stFun->execute();
        $fun = $stFun->fetch(PDO::FETCH_ASSOC);
        if ($fun) {
            $nome_func = (string)($fun['nome'] ?? '');
            $tel_func  = (string)($fun['telefone'] ?? '');
        }
    }

    // Serviço (se existir na tabela)
    $nome_serv = '';
    if ($servico > 0) {
        $stServ = $pdo->prepare("SELECT nome FROM servicos WHERE id = :id LIMIT 1");
        $stServ->bindValue(':id', $servico, PDO::PARAM_INT);
        $stServ->execute();
        $nome_serv = (string)($stServ->fetchColumn() ?: '');
    }

    // Mensagem (padrão do sistema)
    $mensagem = '_Agendamento Cancelado_ %0A';
    if ($nome_func !== '') {
        $mensagem .= 'Profissional: *' . $nome_func . '* %0A';
    }
    if ($nome_serv !== '') {
        $mensagem .= 'Serviço: *' . $nome_serv . '* %0A';
    }
    if ($dataF !== '') {
        $mensagem .= 'Data: *' . $dataF . '* %0A';
    }
    if ($horaF !== '') {
        $mensagem .= 'Hora: *' . $horaF . '* %0A';
    }
    if ($nome_cliente !== '') {
        $mensagem .= 'Cliente: *' . $nome_cliente . '* %0A';
        
    }

    // Caminho do api-texto (não depende do CWD)
    $api_texto = __DIR__ . '/../../../../ajax/api-texto.php';
    if (!file_exists($api_texto)) {
        // fallback se a estrutura tiver variação (mantém compatibilidade)
        $api_texto = __DIR__ . '/../../../../pagamentos/api-texto.php';
    }

    // 9.1) Enviar para o CLIENTE
    $tel_limpo = preg_replace('/\D+/', '', (string)$tel_cliente);
    if ($tel_limpo !== '' && file_exists($api_texto)) {
        $telefone = '55' . $tel_limpo;
        ob_start();
        require $api_texto;
        ob_end_clean();
    }

    // 9.2) (Opcional e compatível com o padrão) avisar o PROFISSIONAL também
    // Mantém o padrão do seu exemplo em /agendamentos/excluir.php
    $tel_fun_limpo = preg_replace('/\D+/', '', (string)$tel_func);
    if ($tel_fun_limpo !== '' && file_exists($api_texto)) {
        $telefone = '55' . $tel_fun_limpo;
        ob_start();
        require $api_texto;
        ob_end_clean();
    }
}

// ---------------------------------------------------------------------------
// 10) Notificação do sistema (mantém regra)
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