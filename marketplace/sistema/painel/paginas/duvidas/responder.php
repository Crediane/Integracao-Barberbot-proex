<?php
require_once __DIR__ . '/../_context_bootstrap.php';

if (strpos((string)session_name(), 'BB_TENANT') !== 0) {
    http_response_code(403);
    echo 'Acesso negado: selecione a loja correspondente.';
    exit();
}

if ((string)($_SESSION['nivel'] ?? '') !== 'Administrador') {
    http_response_code(403);
    echo 'Acesso negado.';
    exit();
}

$id = (int)($_POST['id_pergunta'] ?? 0);
$resposta = strip_tags(trim((string)($_POST['resposta'] ?? '')));

if ($id <= 0 || $resposta === '') {
    echo 'Preencha todos os campos.';
    exit();
}

try {
    $stmt = $pdo->prepare(
        "UPDATE perguntas SET resposta = :resposta, data_resp = :data_resp, respondida = 'Sim' WHERE id = :id"
    );
    $stmt->execute([
        ':resposta' => $resposta,
        ':data_resp' => date('Y-m-d H:i:s'),
        ':id' => $id,
    ]);

    $stmtPergunta = $pdo->prepare('SELECT id_usuario, pergunta FROM perguntas WHERE id = :id LIMIT 1');
    $stmtPergunta->execute([':id' => $id]);
    $perguntaRow = $stmtPergunta->fetch(PDO::FETCH_ASSOC);

    if (!$perguntaRow) {
        echo 'Pergunta não encontrada.';
        exit();
    }

    $id_usuario = (int)($perguntaRow['id_usuario'] ?? 0);
    $pergunta = (string)($perguntaRow['pergunta'] ?? '');

    $stmtUsuario = $pdo->prepare('SELECT nome, telefone FROM usuarios WHERE id = :id LIMIT 1');
    $stmtUsuario->execute([':id' => $id_usuario]);
    $usuario = $stmtUsuario->fetch(PDO::FETCH_ASSOC);

    if (!$usuario) {
        echo 'Usuário não encontrado nesta unidade.';
        exit();
    }

    $nome = (string)($usuario['nome'] ?? '');
    $telefone = preg_replace('/\D+/', '', (string)($usuario['telefone'] ?? ''));
    if ($telefone !== '' && strpos($telefone, '55') !== 0) {
        $telefone = '55' . $telefone;
    }

    $mensagem  = "💬 *Sua dúvida foi respondida!*\n\n";
    $mensagem .= "👤 *Nome:* {$nome}\n";
    $mensagem .= "📝 *Pergunta:* {$pergunta}\n";
    $mensagem .= "✅ *Resposta:* {$resposta}";

    if ($telefone !== '') {
        require __DIR__ . '/../../../../ajax/api-texto.php';
    }

    echo 'Resposta enviada com sucesso!';
} catch (Throwable $e) {
    http_response_code(500);
    echo 'Erro ao enviar a resposta.';
}
