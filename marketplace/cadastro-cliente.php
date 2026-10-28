<?php
// ======================================================================
// ARQUIVO: /public_html/cadastro-cliente.php
// CADASTRO PÚBLICO DE CLIENTE
// PADRÃO DO SISTEMA:
// 1) Isola sessão MASTER x TENANT
// 2) Isola sessão TENANT por SLUG: BB_TENANT_<hash>
// 3) Funciona em /loja/{slug}/cadastro-cliente.php e no master
// 4) Cada contexto usa o banco já resolvido por /sistema/conexao.php
// 5) NÃO permite cadastrar 2x o mesmo WhatsApp
// ======================================================================

if (!function_exists('bb_sess_clean_slug')) {
    function bb_sess_clean_slug($v) {
        $v = strtolower(trim((string)$v));
        return preg_replace('/[^a-z0-9_-]/', '', $v);
    }
}

$__bb_reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

// A barbearia é identificada somente por URL, GET ou POST.
// Cookies antigos e HTTP_REFERER não podem escolher outro banco.
$__bb_reqSlug = '';
if (!empty($_GET['loja'])) {
    $__bb_reqSlug = bb_sess_clean_slug($_GET['loja']);
} elseif (!empty($_POST['loja'])) {
    $__bb_reqSlug = bb_sess_clean_slug($_POST['loja']);
} elseif (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath, $mSlug)) {
    $__bb_reqSlug = bb_sess_clean_slug($mSlug[1]);
}

if ($__bb_reqSlug !== '' && empty($_GET['loja'])) {
    $_GET['loja'] = $__bb_reqSlug;
}

$__bb_isSistemaRoot = (strpos($__bb_reqPath, '/sistema') === 0);
$__bb_hasLojaContext = ($__bb_reqSlug !== '');
$__bb_desiredSession = 'BB_SITE';

if ($__bb_isSistemaRoot && !$__bb_hasLojaContext) {
    $__bb_desiredSession = 'BB_MASTER';
} elseif ($__bb_hasLojaContext) {
    $__bb_desiredSession = 'BB_TENANT_' . strtoupper(substr(sha1($__bb_reqSlug), 0, 12));
}

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

// ===============================
// 8) Conexão
// ===============================
$__bb_conexao = __DIR__ . '/sistema/conexao.php';
if (!is_file($__bb_conexao)) {
    $__bb_conexao = __DIR__ . '/conexao.php';
}
require_once $__bb_conexao;

// Confirma o contexto resolvido pelo conexao.php. Em domínio próprio ou
// subdomínio, o slug pode ser descoberto somente durante essa inclusão.
$__bb_sessionTenant = strpos((string)session_name(), 'BB_TENANT') === 0;
$__bb_slugSessao = bb_sess_clean_slug($_SESSION['tenant_slug'] ?? '');
$__bb_dbTenantEsperado = trim((string)($_SESSION['tenant_db'] ?? ''));
$__bb_dbAtual = '';

if (isset($pdo) && $pdo instanceof PDO) {
    try {
        $__bb_dbAtual = trim((string)$pdo->query('SELECT DATABASE()')->fetchColumn());
    } catch (Throwable $e) {
        $__bb_dbAtual = '';
    }
}

if ($__bb_sessionTenant) {
    if (
        $__bb_slugSessao === ''
        || ($__bb_reqSlug !== '' && $__bb_reqSlug !== $__bb_slugSessao)
        || $__bb_dbTenantEsperado === ''
        || $__bb_dbAtual === ''
        || strcasecmp($__bb_dbAtual, $__bb_dbTenantEsperado) !== 0
    ) {
        http_response_code(403);
        exit('Falha de isolamento da barbearia.');
    }

    $__bb_reqSlug = $__bb_slugSessao;
    $_GET['loja'] = $__bb_reqSlug;
} elseif ($__bb_reqSlug !== '') {
    http_response_code(403);
    exit('Barbearia não confirmada.');
}

// ===============================
// Helpers
// ===============================
if (!function_exists('h')) {
    function h($v) {
        return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8');
    }
}

if (!function_exists('bb_digits')) {
    function bb_digits($valor) {
        return preg_replace('/\D+/', '', (string)$valor);
    }
}

if (!function_exists('bb_normalizar_whatsapp_unico')) {
    function bb_normalizar_whatsapp_unico($telefone) {
        $digitos = bb_digits($telefone);

        if ($digitos === '') {
            return '';
        }

        // normaliza +55 / 55 no começo para comparar com registros antigos sem DDI
        if ((strlen($digitos) === 12 || strlen($digitos) === 13) && substr($digitos, 0, 2) === '55') {
            $digitos = substr($digitos, 2);
        }

        return $digitos;
    }
}

if (!function_exists('bb_normalizar_telefone_br')) {
    function bb_normalizar_telefone_br($telefone) {
        $digitos = bb_normalizar_whatsapp_unico($telefone);

        if ($digitos === '') {
            return '';
        }

        if (strlen($digitos) <= 2) {
            return '(' . $digitos . ')';
        }

        if (strlen($digitos) <= 6) {
            return '(' . substr($digitos, 0, 2) . ') ' . substr($digitos, 2);
        }

        if (strlen($digitos) <= 10) {
            return '(' . substr($digitos, 0, 2) . ') ' . substr($digitos, 2, 4) . '-' . substr($digitos, 6);
        }

        return '(' . substr($digitos, 0, 2) . ') ' . substr($digitos, 2, 5) . '-' . substr($digitos, 7, 4);
    }
}

if (!function_exists('bb_data_mysql_ou_padrao')) {
    function bb_data_mysql_ou_padrao($valor) {
        $valor = trim((string)$valor);
        if ($valor === '') {
            return '0000-00-00';
        }

        $partes = explode('-', $valor);
        if (count($partes) === 3 && checkdate((int)$partes[1], (int)$partes[2], (int)$partes[0])) {
            return $valor;
        }

        return '0000-00-00';
    }
}

if (!function_exists('bb_public_route')) {
    function bb_public_route($rota, $slug = '') {
        $rota = '/' . ltrim((string)$rota, '/');
        $slug = trim((string)$slug);

        if ($slug !== '') {
            return '/loja/' . $slug . $rota;
        }

        return $rota;
    }
}

// ===============================
// Rotas públicas
// ===============================
$__slug = $__bb_reqSlug;
$login_route = bb_public_route('/sistema/acesso', $__slug);
$cadastro_route = bb_public_route('/cadastro-cliente.php', $__slug);

// ===============================
// POST AJAX: cadastra cliente
// ===============================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: text/plain; charset=utf-8');

    $nome = trim((string)($_POST['nome'] ?? ''));
    $telefone = trim((string)($_POST['telefone'] ?? ''));
    $cpf = trim((string)($_POST['cpf'] ?? ''));
    $endereco = trim((string)($_POST['endereco'] ?? ''));
    $data_nasc = bb_data_mysql_ou_padrao($_POST['data_nasc'] ?? '');
    $senha = (string)($_POST['senha'] ?? '');
    $confirmar_senha = (string)($_POST['confirmar_senha'] ?? '');
    $marketing = isset($_POST['marketing']) ? 'Sim' : 'Não';

    if ($nome === '' || bb_normalizar_whatsapp_unico($telefone) === '') {
        echo 'Preencha nome e telefone';
        exit();
    }

    if (strlen(bb_normalizar_whatsapp_unico($telefone)) < 10) {
        echo 'Preencha nome e telefone';
        exit();
    }

    if ($senha === '') {
        echo 'Preencha a senha';
        exit();
    }

    if ($senha !== $confirmar_senha) {
        echo 'As senhas não coincidem';
        exit();
    }

    $telefone_formatado = bb_normalizar_telefone_br($telefone);
    $telefone_unico = bb_normalizar_whatsapp_unico($telefone_formatado);

    // WhatsApp único: compara número normalizado do banco com o informado
    $sql_telefone_normalizado = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(COALESCE(telefone, ''), '(', ''), ')', ''), '-', ''), ' ', ''), '+', ''), '.', ''), '/', '')";

    $stmt = $pdo->prepare("
        SELECT id
        FROM clientes
        WHERE (
            CASE
                WHEN LEFT({$sql_telefone_normalizado}, 2) = '55' AND CHAR_LENGTH({$sql_telefone_normalizado}) IN (12, 13)
                    THEN SUBSTRING({$sql_telefone_normalizado}, 3)
                ELSE {$sql_telefone_normalizado}
            END
        ) = :telefone_unico
        LIMIT 1
    ");
    $stmt->bindValue(':telefone_unico', $telefone_unico);
    $stmt->execute();
    $ja_cadastrado_tel = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($ja_cadastrado_tel) {
        echo 'Telefone já Cadastrado, escolha outro!!';
        exit();
    }

    if ($cpf !== '') {
        $stmt = $pdo->prepare("SELECT id FROM clientes WHERE cpf = :cpf LIMIT 1");
        $stmt->bindValue(':cpf', $cpf);
        $stmt->execute();
        $ja_cadastrado_cpf = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($ja_cadastrado_cpf) {
            echo 'CPF já Cadastrado, escolha outro!!';
            exit();
        }
    }

    $senha_crip = password_hash($senha, PASSWORD_DEFAULT);

    $stmt = $pdo->prepare("
        INSERT INTO clientes
        (nome, telefone, endereco, data_nasc, data_cad, cartoes, data_retorno, ultimo_servico, alertado, cpf, senha_crip, marketing, usuario)
        VALUES
        (:nome, :telefone, :endereco, :data_nasc, curDate(), 0, NULL, 0, 'Não', :cpf, :senha_crip, :marketing, NULL)
    ");

    $stmt->bindValue(':nome', $nome);
    $stmt->bindValue(':telefone', $telefone_formatado);
    $stmt->bindValue(':endereco', $endereco !== '' ? $endereco : null);
    $stmt->bindValue(':data_nasc', $data_nasc);
    $stmt->bindValue(':cpf', $cpf !== '' ? $cpf : null);
    $stmt->bindValue(':senha_crip', $senha_crip);
    $stmt->bindValue(':marketing', $marketing);
    $stmt->execute();

    echo 'Cadastrado com Sucesso';
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title><?php echo h(($nome_sistema ?? 'Sistema') . ' - Cadastro de Cliente'); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">

    <link href="//netdna.bootstrapcdn.com/bootstrap/3.0.3/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" type="text/css" href="/sistema/painel_cliente/css/bootstrap.css">
    <link rel="stylesheet" href="/sistema/painel_cliente/css/font-awesome.css">
    <link rel="stylesheet" href="/sistema/painel_cliente/css/custom.css">
    <link rel="stylesheet" href="/sistema/painel_cliente/css/style.css">
    <link rel="stylesheet" type="text/css" href="/sistema/css/estilo-login.css">
    <link rel="shortcut icon" href="/sistema/painel/img/favicon.png" type="image/png">
    <link rel="icon" href="/sistema/painel/img/favicon.png" type="image/png">

    <style>
        .form-login .panel-body { padding: 25px; }
        .titulo-cadastro { font-size: 18px; margin-bottom: 15px; color: #444; text-align: center; }
        .texto-apoio { font-size: 13px; color: #666; text-align: center; margin-bottom: 18px; }
        .link-login { text-align: center; margin-top: 15px; }
        .checkbox-marketing { margin-top: 10px; }
    </style>
</head>
<body>
<div class="container">
    <div class="row vertical-offset-100">
        <div class="col-md-4 col-md-offset-4">
            <div class="panel panel-default form-login" style="opacity:0.95; border-radius: 20px">
                <div class="panel-heading" align="center" style="border-top-right-radius: 20px; border-top-left-radius: 20px">
                    <img src="/sistema/img/logo.png" width="100px" alt="Logo">
                </div>

                <div class="panel-body">
                    <div class="titulo-cadastro">Cadastro de Cliente</div>
                    <div class="texto-apoio">Preencha seus dados para criar seu acesso ao painel do cliente.</div>

                    <form id="form_cadastro" method="post" action="<?php echo h($cadastro_route); ?>">
                        <?php if (!empty($__slug)) { ?>
                        <input type="hidden" name="loja" value="<?php echo h($__slug); ?>">
                        <?php } ?>

                        <div class="form-group">
                            <input class="form-control" placeholder="Seu Nome*" name="nome" id="nome" type="text" required>
                        </div>

                        <div class="form-group">
                            <input class="form-control" placeholder="Seu WhatsApp*" name="telefone" id="telefone" type="text" required>
                        </div>

                        <div class="form-group">
                            <input class="form-control" placeholder="CPF (opcional)" name="cpf" id="cpf" type="text">
                        </div>

                        <div class="form-group">
                            <input class="form-control" placeholder="Endereço (opcional)" name="endereco" id="endereco" type="text">
                        </div>

                        <div class="form-group">
                            <input class="form-control" name="data_nasc" id="data_nasc" type="date">
                        </div>

                        <div class="form-group">
                            <input class="form-control" placeholder="Crie uma Senha" name="senha" id="senha" type="password" required>
                        </div>

                        <div class="form-group">
                            <input class="form-control" placeholder="Confirmar Senha" name="confirmar_senha" id="confirmar_senha" type="password" required>
                        </div>

                        <div class="checkbox checkbox-marketing">
                            <label>
                                <input type="checkbox" name="marketing" id="marketing" value="Sim"> Aceito receber novidades e lembretes
                            </label>
                        </div>

                        <br>
                        <input class="btn btn-lg btn-primary btn-block" type="submit" value="Cadastrar">
                        <small><div id="mensagem-cadastro" align="center" style="margin-top:10px;"></div></small>

                        <div class="link-login">
                            <a href="<?php echo h($login_route); ?>">Já tenho cadastro. Entrar no painel do cliente</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<script src="/sistema/painel_cliente/js/jquery-1.11.1.min.js"></script>
<script src="/sistema/painel_cliente/js/bootstrap.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.6/dist/sweetalert2.all.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.11/jquery.mask.min.js"></script>

<script>
$(document).ready(function(){
    if (window.jQuery && $.fn.mask) {
        $('#telefone').mask('(00) 00000-0000');
        $('#cpf').mask('000.000.000-00');
    }

    $('#form_cadastro').submit(function(e){
        e.preventDefault();

        var nome = ($('#nome').val() || '').trim();
        var fone = ($('#telefone').val() || '').replace(/\D/g, '');
        var senha = $('#senha').val() || '';
        var confirmar = $('#confirmar_senha').val() || '';

        if (!nome || fone.length < 10) {
            Swal.fire({
                icon: 'error',
                title: '❌ Dados inválidos',
                text: 'Preencha corretamente nome e WhatsApp.',
                confirmButtonColor: '#3085d6'
            });
            return;
        }

        if (!senha) {
            Swal.fire({
                icon: 'error',
                title: '❌ Senha obrigatória',
                text: 'Preencha a senha.',
                confirmButtonColor: '#3085d6'
            });
            return;
        }

        if (senha !== confirmar) {
            Swal.fire({
                icon: 'error',
                title: '❌ Senhas diferentes',
                text: 'As senhas não coincidem.',
                confirmButtonColor: '#3085d6'
            });
            return;
        }

        $.ajax({
            url: $('#form_cadastro').attr('action'),
            type: 'POST',
            data: $('#form_cadastro').serialize(),
            success: function(mensagem){
                var resp = (mensagem || '').trim();

                if (resp === 'Cadastrado com Sucesso') {
                    Swal.fire({
                        icon: 'success',
                        title: '✅ Sucesso!',
                        text: 'Cadastro realizado com sucesso.',
                        confirmButtonColor: '#3085d6'
                    }).then(function(){
                        window.location.href = <?php echo json_encode($login_route, JSON_UNESCAPED_SLASHES); ?>;
                    });
                    return;
                }

                if (resp === 'Telefone já Cadastrado, escolha outro!!') {
                    Swal.fire({
                        icon: 'warning',
                        title: '⚠️ WhatsApp já cadastrado',
                        text: 'Esse WhatsApp já existe no sistema.',
                        confirmButtonColor: '#3085d6'
                    });
                    return;
                }

                if (resp === 'CPF já Cadastrado, escolha outro!!') {
                    Swal.fire({
                        icon: 'warning',
                        title: '⚠️ CPF já cadastrado',
                        text: 'Esse CPF já existe no sistema.',
                        confirmButtonColor: '#3085d6'
                    });
                    return;
                }

                if (resp === 'Preencha nome e telefone') {
                    Swal.fire({
                        icon: 'error',
                        title: '❌ Dados inválidos',
                        text: 'Preencha corretamente nome e WhatsApp.',
                        confirmButtonColor: '#3085d6'
                    });
                    return;
                }

                if (resp === 'Preencha a senha') {
                    Swal.fire({
                        icon: 'error',
                        title: '❌ Senha obrigatória',
                        text: 'Preencha a senha.',
                        confirmButtonColor: '#3085d6'
                    });
                    return;
                }

                if (resp === 'As senhas não coincidem') {
                    Swal.fire({
                        icon: 'error',
                        title: '❌ Senhas diferentes',
                        text: 'As senhas não coincidem.',
                        confirmButtonColor: '#3085d6'
                    });
                    return;
                }

                Swal.fire({
                    icon: 'error',
                    title: '❌ Erro inesperado',
                    html: 'Retorno:<br><pre style="white-space:pre-wrap">' + resp + '</pre>',
                    confirmButtonColor: '#3085d6'
                });
            },
            error: function(){
                Swal.fire({
                    icon: 'error',
                    title: '❌ Falha de rede',
                    text: 'Não foi possível enviar os dados.',
                    confirmButtonColor: '#3085d6'
                });
            }
        });
    });
});
</script>
</body>
</html>
