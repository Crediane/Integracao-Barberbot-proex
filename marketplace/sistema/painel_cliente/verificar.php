<?php
// /public_html/sistema/painel_cliente/verificar.php
// Validação central do Painel do Cliente.
// O conexao.php resolve a URL /loja/{slug}/, parâmetro, subdomínio ou domínio próprio.
// Este arquivo apenas confirma que o contexto ativo é TENANT e que o PDO está no banco individual correto.

require_once __DIR__ . '/../conexao.php';

if (!function_exists('bb_pc_limpar_slug')) {
    function bb_pc_limpar_slug($valor): string
    {
        $valor = strtolower(trim((string)$valor));
        return (string)preg_replace('/[^a-z0-9_-]/', '', $valor);
    }
}

if (!function_exists('bb_pc_requisicao_assincrona')) {
    function bb_pc_requisicao_assincrona(): bool
    {
        $reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $accept = strtolower((string)($_SERVER['HTTP_ACCEPT'] ?? ''));
        $requestedWith = strtolower((string)($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));

        if ($requestedWith === 'xmlhttprequest') return true;
        if (strpos($accept, 'application/json') !== false) return true;
        if (strpos($reqPath, '/paginas/') !== false) return true;

        return false;
    }
}

if (!function_exists('bb_pc_url_acesso')) {
    function bb_pc_url_acesso(string $slug, string $erro = ''): string
    {
        $url = $slug !== ''
            ? '/loja/' . rawurlencode($slug) . '/sistema/acesso/'
            : '/sistema/acesso.php';

        if ($erro !== '') {
            $url .= (strpos($url, '?') === false ? '?' : '&') . 'erro=' . rawurlencode($erro);
        }

        return $url;
    }
}

if (!function_exists('bb_pc_negar_acesso')) {
    function bb_pc_negar_acesso(string $slug, string $mensagem = 'Sessão inválida. Faça login novamente.'): void
    {
        if (bb_pc_requisicao_assincrona()) {
            http_response_code(401);
            if (!headers_sent()) {
                header('Content-Type: text/plain; charset=utf-8');
            }
            echo $mensagem;
            exit();
        }

        $destino = bb_pc_url_acesso($slug, 'sessao-expirada');
        if (!headers_sent()) {
            header('Location: ' . $destino);
        } else {
            echo '<script>window.location=' . json_encode($destino, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';</script>';
        }
        exit();
    }
}

$bb_pc_req_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$bb_pc_slug_explicito = '';

if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $bb_pc_req_path, $bb_pc_match)) {
    $bb_pc_slug_explicito = bb_pc_limpar_slug($bb_pc_match[1]);
} elseif (!empty($_GET['loja'])) {
    $bb_pc_slug_explicito = bb_pc_limpar_slug($_GET['loja']);
} elseif (!empty($_POST['loja'])) {
    $bb_pc_slug_explicito = bb_pc_limpar_slug($_POST['loja']);
}

$bb_pc_slug_sessao = bb_pc_limpar_slug($_SESSION['tenant_slug'] ?? '');
$bb_pc_banco_sessao = trim((string)($_SESSION['tenant_db'] ?? ''));
$bb_pc_sessao_tenant = strpos((string)session_name(), 'BB_TENANT') === 0;

if (!$bb_pc_sessao_tenant || session_name() === 'BB_MASTER') {
    bb_pc_negar_acesso($bb_pc_slug_explicito !== '' ? $bb_pc_slug_explicito : $bb_pc_slug_sessao, 'Acesso restrito ao painel da loja.');
}

if ($bb_pc_slug_sessao === '' || $bb_pc_banco_sessao === '' || !($pdo instanceof PDO)) {
    bb_pc_negar_acesso($bb_pc_slug_explicito !== '' ? $bb_pc_slug_explicito : $bb_pc_slug_sessao);
}

if ($bb_pc_slug_explicito !== '' && $bb_pc_slug_explicito !== $bb_pc_slug_sessao) {
    bb_pc_negar_acesso($bb_pc_slug_explicito, 'A loja informada não corresponde à sessão atual.');
}

try {
    $bb_pc_banco_atual = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
} catch (Throwable $e) {
    $bb_pc_banco_atual = '';
}

if ($bb_pc_banco_atual === '' || strcasecmp($bb_pc_banco_atual, $bb_pc_banco_sessao) !== 0) {
    bb_pc_negar_acesso($bb_pc_slug_sessao, 'Não foi possível confirmar o banco individual desta loja.');
}

$bb_pc_id_cliente = (int)($_SESSION['id'] ?? 0);
$bb_pc_token_cliente = (string)($_SESSION['aut_token_505052022'] ?? '');

if ($bb_pc_id_cliente <= 0 || $bb_pc_token_cliente !== 'fdsfdsafda885574125') {
    bb_pc_negar_acesso($bb_pc_slug_sessao);
}

$bb_pc_slug_autenticado = bb_pc_limpar_slug($_SESSION['auth_tenant_slug'] ?? '');
if ($bb_pc_slug_autenticado !== '' && $bb_pc_slug_autenticado !== $bb_pc_slug_sessao) {
    bb_pc_negar_acesso($bb_pc_slug_sessao, 'A sessão de acesso pertence a outra loja.');
}

if ($bb_pc_slug_autenticado === '') {
    $_SESSION['auth_tenant_slug'] = $bb_pc_slug_sessao;
}

// Variáveis reutilizáveis pelas páginas do painel.
$bb_cliente_slug = $bb_pc_slug_sessao;
$bb_cliente_db = $bb_pc_banco_sessao;
$bb_cliente_id = $bb_pc_id_cliente;
