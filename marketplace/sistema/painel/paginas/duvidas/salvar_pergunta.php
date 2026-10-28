<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/_global_connection.php';

if (strpos((string)session_name(), 'BB_TENANT') !== 0) {
    http_response_code(403);
    echo 'Erro: esta ação pertence exclusivamente ao painel da loja.';
    exit();
}

$slug = bb_pages_clean_slug($_SESSION['tenant_slug'] ?? '');
$pergunta = trim((string)($_POST['pergunta'] ?? ''));
$id_usuario = (int)($_SESSION['id'] ?? 0);

if ($pergunta === '') {
    echo 'Erro: Digite sua pergunta.';
    exit();
}

if ($id_usuario <= 0) {
    echo 'Erro: Você precisa estar logado para enviar uma pergunta.';
    exit();
}

try {
    $data_criacao = date('Y-m-d H:i:s');

    $chk = $pdo->prepare('SELECT nome, telefone FROM usuarios WHERE id = :id LIMIT 1');
    $chk->execute([':id' => $id_usuario]);
    $usuario = $chk->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        echo 'Erro ao enviar: Usuário não encontrado nesta unidade.';
        exit();
    }

    $stmt = $pdo->prepare(
        'INSERT INTO perguntas (id_usuario, pergunta, data_criacao) '
        . 'VALUES (:id_usuario, :pergunta, :data_criacao)'
    );
    $stmt->execute([
        ':id_usuario' => $id_usuario,
        ':pergunta' => $pergunta,
        ':data_criacao' => $data_criacao,
    ]);

    $nome_usuario = (string)($usuario['nome'] ?? '');
    $telefone_usuario = (string)($usuario['telefone'] ?? '');

    $admin_email = 'jacy@jc.tec.br';
    $admin_tel = '';
    $token = '';
    $instancia = '';

    $stmtAdmin = $pdoDuvidasGlobal->prepare(
        'SELECT telefone FROM usuarios WHERE LOWER(email) = LOWER(:email) LIMIT 1'
    );
    $stmtAdmin->execute([':email' => $admin_email]);
    $admin = $stmtAdmin->fetch(PDO::FETCH_ASSOC);

    if ($admin && !empty($admin['telefone'])) {
        $admin_tel = (string)$admin['telefone'];
    } else {
        $stmtAdminFallback = $pdoDuvidasGlobal->query(
            "SELECT telefone FROM usuarios WHERE nivel = 'Administrador' ORDER BY id ASC LIMIT 1"
        );
        $adminFallback = $stmtAdminFallback ? $stmtAdminFallback->fetch(PDO::FETCH_ASSOC) : false;
        if ($adminFallback && !empty($adminFallback['telefone'])) {
            $admin_tel = (string)$adminFallback['telefone'];
        }
    }

    $stmtConfig = $pdoDuvidasGlobal->query('SELECT token, instancia FROM config LIMIT 1');
    $configGlobal = $stmtConfig ? $stmtConfig->fetch(PDO::FETCH_ASSOC) : false;
    if ($configGlobal) {
        $token = (string)($configGlobal['token'] ?? '');
        $instancia = (string)($configGlobal['instancia'] ?? '');
    }

    $mensagem = "❓ *Nova dúvida recebida*\n\n";
    if ($slug !== '') {
        $mensagem .= "🏪 *Unidade:* {$slug}\n";
    }
    $mensagem .= "👤 *Usuário:* {$nome_usuario}\n";
    $mensagem .= "📞 *Telefone:* {$telefone_usuario}\n";
    $mensagem .= "📝 *Pergunta:*\n{$pergunta}";

    $telefone_admin = preg_replace('/\D+/', '', $admin_tel);
    if ($telefone_admin !== '') {
        if (strpos($telefone_admin, '55') !== 0) {
            $telefone_admin = '55' . $telefone_admin;
        }

        $telefone = $telefone_admin;
        require __DIR__ . '/../../../../ajax/api-texto.php';
    }

    echo 'Pergunta enviada com sucesso!';
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Erro ao enviar a pergunta.';
}
