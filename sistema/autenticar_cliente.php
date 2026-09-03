<?php
// /public_html/sistema/autenticar_cliente.php
// Login exclusivo do cliente dentro do Banco Individual da barbearia.

$__bb_reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (!function_exists('bb_sess_clean_slug')) {
    function bb_sess_clean_slug($valor): string
    {
        $valor = strtolower(trim((string)$valor));
        return (string)preg_replace('/[^a-z0-9_-]/', '', $valor);
    }
}

$__bb_reqSlug = '';
if (!empty($_GET['loja'])) {
    $__bb_reqSlug = bb_sess_clean_slug($_GET['loja']);
} elseif (!empty($_POST['loja'])) {
    $__bb_reqSlug = bb_sess_clean_slug($_POST['loja']);
} elseif (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath, $mSlug)) {
    $__bb_reqSlug = bb_sess_clean_slug($mSlug[1]);
}

$__bb_tenantSessionName = 'BB_TENANT';
if ($__bb_reqSlug !== '') {
    $__bb_tenantSessionName = 'BB_TENANT_' . strtoupper(substr(sha1($__bb_reqSlug), 0, 12));
}

$__bb_desiredSession = ($__bb_reqSlug !== '') ? $__bb_tenantSessionName : 'BB_SITE';

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

require_once __DIR__ . '/conexao.php';

if (!function_exists('bb_cliente_contexto_valido')) {
    function bb_cliente_contexto_valido(string $slugRequisicao, $pdo): bool
    {
        if (strpos((string)session_name(), 'BB_TENANT') !== 0 || session_name() === 'BB_MASTER') {
            return false;
        }

        $slugSessao = bb_sess_clean_slug($_SESSION['tenant_slug'] ?? '');
        $bancoSessao = trim((string)($_SESSION['tenant_db'] ?? ''));

        if ($slugRequisicao === '' || $slugSessao === '' || $slugRequisicao !== $slugSessao || $bancoSessao === '') {
            return false;
        }

        if (!($pdo instanceof PDO)) {
            return false;
        }

        try {
            $bancoAtual = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }

        return $bancoAtual !== '' && strcasecmp($bancoAtual, $bancoSessao) === 0;
    }
}

if (!function_exists('bb_redir_acesso_cliente')) {
    function bb_redir_acesso_cliente(string $slug = ''): void
    {
        $slug = bb_sess_clean_slug($slug);

        if ($slug !== '') {
            header('Location: /loja/' . rawurlencode($slug) . '/sistema/acesso');
            exit();
        }

        header('Location: acesso');
        exit();
    }
}

if (!function_exists('bb_tel_login_digits')) {
    function bb_tel_login_digits($valor): string
    {
        $valor = trim((string)$valor);
        $digitos = (string)preg_replace('/\D+/', '', $valor);

        if (strpos($digitos, '00') === 0) {
            $digitos = substr($digitos, 2);
        }

        return $digitos;
    }
}

if (!bb_cliente_contexto_valido($__bb_reqSlug, $pdo ?? null)) {
    http_response_code(403);
    echo 'Acesso negado: loja ou banco individual inválido.';
    exit();
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    $_SESSION['flash'] = [
        'type'    => 'error',
        'message' => 'Método de acesso inválido. Faça login novamente.',
    ];
    bb_redir_acesso_cliente($__bb_reqSlug);
}

$telefone = filter_input(INPUT_POST, 'telefone', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$senha = filter_input(INPUT_POST, 'senha', FILTER_UNSAFE_RAW);

$telefone = $telefone !== null ? trim((string)$telefone) : '';
$senha = $senha !== null ? (string)$senha : '';

if ($telefone === '' || $senha === '') {
    $_SESSION['flash'] = [
        'type'    => 'error',
        'message' => 'Informe telefone e senha para entrar.',
    ];
    bb_redir_acesso_cliente($__bb_reqSlug);
}

$telefoneDigits = bb_tel_login_digits($telefone);

if ($telefoneDigits === '') {
    $_SESSION['flash'] = [
        'type'    => 'error',
        'message' => 'Informe telefone e senha para entrar.',
    ];
    bb_redir_acesso_cliente($__bb_reqSlug);
}

$telefoneBrCom55 = '';
$telefoneBrSem55 = '';

if ((strlen($telefoneDigits) === 10 || strlen($telefoneDigits) === 11) && strpos(trim($telefone), '+') !== 0) {
    $telefoneBrCom55 = '55' . $telefoneDigits;
}

if ((strlen($telefoneDigits) === 12 || strlen($telefoneDigits) === 13) && strpos($telefoneDigits, '55') === 0) {
    $telefoneBrSem55 = substr($telefoneDigits, 2);
}

$sqlTelefoneNormalizado = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(telefone, ''), '(', ''), ')', ''), '-', ''), ' ', ''), '+', ''), '.', ''), '/', '')";

try {
    $sql = "SELECT * FROM clientes WHERE {$sqlTelefoneNormalizado} = :telefone";
    $params = [':telefone' => $telefoneDigits];

    if ($telefoneBrCom55 !== '') {
        $sql .= " OR {$sqlTelefoneNormalizado} = :telefone_br_com_55";
        $params[':telefone_br_com_55'] = $telefoneBrCom55;
    }

    if ($telefoneBrSem55 !== '') {
        $sql .= " OR {$sqlTelefoneNormalizado} = :telefone_br_sem_55";
        $params[':telefone_br_sem_55'] = $telefoneBrSem55;
    }

    $sql .= ' LIMIT 1';

    $query = $pdo->prepare($sql);
    $query->execute($params);
    $cliente = $query->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    $_SESSION['flash'] = [
        'type'    => 'error',
        'message' => 'Erro ao acessar o sistema. Tente novamente em alguns instantes.',
    ];
    bb_redir_acesso_cliente($__bb_reqSlug);
}

if (!$cliente || empty($cliente['senha_crip']) || !password_verify($senha, (string)$cliente['senha_crip'])) {
    $_SESSION['flash'] = [
        'type'    => 'error',
        'message' => 'Usuário ou senha incorretos!',
    ];
    bb_redir_acesso_cliente($__bb_reqSlug);
}

session_regenerate_id(true);

$_SESSION['id'] = (int)($cliente['id'] ?? 0);
$_SESSION['nome'] = (string)($cliente['nome'] ?? '');
$_SESSION['aut_token_505052022'] = 'fdsfdsafda885574125';
$_SESSION['auth_tenant_slug'] = $__bb_reqSlug;

header('Location: painel_cliente');
exit();
