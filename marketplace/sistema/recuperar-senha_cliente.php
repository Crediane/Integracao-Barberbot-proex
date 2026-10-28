<?php
// /public_html/sistema/recuperar-senha_cliente.php
// Recuperação de senha exclusiva do cliente no Banco Individual da barbearia.

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

if (!function_exists('bb_tel_digits')) {
    function bb_tel_digits($valor): string
    {
        $digitos = (string)preg_replace('/\D+/', '', (string)$valor);

        if (strpos($digitos, '00') === 0) {
            $digitos = substr($digitos, 2);
        }

        return $digitos;
    }
}

if (!function_exists('bb_tel_candidatos_cliente')) {
    function bb_tel_candidatos_cliente($telefone, $ddiSelecionado = ''): array
    {
        $raw = trim((string)$telefone);
        $ddiSelecionado = (string)preg_replace('/\D+/', '', (string)$ddiSelecionado);
        $digits = bb_tel_digits($raw);
        $candidatos = [];

        if ($digits === '') {
            return [];
        }

        if ($ddiSelecionado !== '' && $ddiSelecionado !== '55') {
            if (strpos($digits, $ddiSelecionado) === 0) {
                $candidatos[] = $digits;
                $local = substr($digits, strlen($ddiSelecionado));

                if ($local !== '') {
                    $candidatos[] = $local;
                }
            } else {
                $candidatos[] = $ddiSelecionado . $digits;
                $candidatos[] = $digits;
            }
        } else {
            if (strlen($digits) === 10 || strlen($digits) === 11) {
                $candidatos[] = $digits;
                $candidatos[] = '55' . $digits;
            } elseif (strpos($digits, '55') === 0 && (strlen($digits) === 12 || strlen($digits) === 13)) {
                $candidatos[] = $digits;
                $candidatos[] = substr($digits, 2);
            } else {
                $candidatos[] = $digits;
            }
        }

        return array_values(array_unique(array_filter($candidatos)));
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    echo 'Método inválido.';
    exit();
}

if (!bb_cliente_contexto_valido($__bb_reqSlug, $pdo ?? null)) {
    http_response_code(403);
    echo 'Acesso negado: loja ou banco individual inválido.';
    exit();
}

$telefoneInformado = filter_input(INPUT_POST, 'telefone', FILTER_SANITIZE_FULL_SPECIAL_CHARS);
$ddiSelecionado = filter_input(INPUT_POST, 'telefone_ddi', FILTER_SANITIZE_FULL_SPECIAL_CHARS);

$telefoneInformado = $telefoneInformado !== null ? trim((string)$telefoneInformado) : '';
$ddiSelecionado = $ddiSelecionado !== null ? trim((string)$ddiSelecionado) : '';

$candidatos = bb_tel_candidatos_cliente($telefoneInformado, $ddiSelecionado);

if (!$candidatos) {
    echo 'Informe um telefone válido.';
    exit();
}

$sqlTelefoneNormalizado = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(telefone, ''), '(', ''), ')', ''), '-', ''), ' ', ''), '+', ''), '.', ''), '/', '')";
$condicoes = [];
$params = [];

foreach ($candidatos as $indice => $candidato) {
    $chave = ':telefone_' . $indice;
    $condicoes[] = $sqlTelefoneNormalizado . ' = ' . $chave;
    $params[$chave] = $candidato;
}

try {
    $query = $pdo->prepare(
        'SELECT id, nome, telefone FROM clientes WHERE (' . implode(' OR ', $condicoes) . ') LIMIT 1'
    );
    $query->execute($params);
    $cliente = $query->fetch(PDO::FETCH_ASSOC);
} catch (Throwable $e) {
    echo 'Erro ao recuperar a senha. Tente novamente.';
    exit();
}

if (!$cliente) {
    echo 'Esse telefone não está cadastrado!';
    exit();
}

try {
    $novaSenha = (string)random_int(100000, 999999);
} catch (Throwable $e) {
    echo 'Erro ao gerar a nova senha. Tente novamente.';
    exit();
}

$senhaCriptografada = password_hash($novaSenha, PASSWORD_DEFAULT);

if ($senhaCriptografada === false) {
    echo 'Erro ao gerar a nova senha. Tente novamente.';
    exit();
}

try {
    $update = $pdo->prepare('UPDATE clientes SET senha_crip = :senha WHERE id = :id');
    $update->execute([
        ':senha' => $senhaCriptografada,
        ':id'    => (int)$cliente['id'],
    ]);
} catch (Throwable $e) {
    echo 'Erro ao atualizar a senha. Tente novamente.';
    exit();
}

$telefoneEnvio = bb_tel_digits((string)($cliente['telefone'] ?? ''));

if ((strlen($telefoneEnvio) === 10 || strlen($telefoneEnvio) === 11) && strpos($telefoneEnvio, '55') !== 0) {
    $telefoneEnvio = '55' . $telefoneEnvio;
}

if ($msg_agendamento === 'Api' && $telefoneEnvio !== '') {
    $mensagem = '*' . $nome_sistema . '* %0A';
    $mensagem .= '_Sua nova senha é_ %0A';
    $mensagem .= 'Senha: *' . $novaSenha . '* %0A';

    $telefone = $telefoneEnvio;
    require __DIR__ . '/../ajax/api-texto.php';
}

echo 'Recuperado com Sucesso';
