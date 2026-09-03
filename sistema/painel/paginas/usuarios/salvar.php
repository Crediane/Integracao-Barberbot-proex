<?php
require_once __DIR__ . '/../_context_bootstrap.php';
/**
 * /sistema/painel/paginas/usuarios/salvar.php
 * Correção direta (sem mudar a lógica do sistema):
 * - Garante contexto TENANT quando chamado a partir de /loja/{slug}/painel (evita salvar no MASTER)
 * - Sanitiza entradas e usa prepared statements (evita SQL quebrado e avisos que atrapalham o AJAX)
 * - Mantém mensagens e fluxo original (modo_teste, validações, upload, insert/update)
 */

@session_start();

// 1) Se vier loja no POST/GET, injeta em $_GET['loja'] ANTES da conexão (padrão já usado no sistema)
if (!empty($_POST['loja'])) {
    $bb_loja = preg_replace('/[^a-z0-9_-]/i', '', (string)$_POST['loja']);
    $_POST['loja'] = $bb_loja;
    $_GET['loja'] = $bb_loja;
} elseif (!empty($_GET['loja'])) {
    $bb_loja = preg_replace('/[^a-z0-9_-]/i', '', (string)$_GET['loja']);
    $_GET['loja'] = $bb_loja;
}

// 2) Carrega bootstrap (já existe no seu sistema) e depois conexão
require_once __DIR__ . '/../../../bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';

$tabela = 'usuarios';

// Mantém regra do modo de testes (só evita Notice se variável não existir)
if (($modo_teste ?? '') === 'Sim') {
    echo 'Esse recurso não é possível alterar no modo de testes!';
    exit();
}

// ===== Sanitização de entradas (mantém campos e lógica) =====
$id = isset($_POST['id']) ? (string)$_POST['id'] : '';
$id = preg_replace('/[^0-9]/', '', $id);
$id = (int)$id;

$nome = trim((string)($_POST['nome'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$telefone = trim((string)($_POST['telefone'] ?? ''));
$cpf = trim((string)($_POST['cpf'] ?? ''));
$cargo = trim((string)($_POST['cargo'] ?? ''));
$endereco = trim((string)($_POST['endereco'] ?? ''));
$atendimento = trim((string)($_POST['atendimento'] ?? ''));
$visualizar = trim((string)($_POST['visualizar'] ?? ''));

// Mesmo padrão do original (senha padrão para novo usuário)
$senha = '123';
$senha_crip = password_hash($senha, PASSWORD_DEFAULT);

if ($cargo === "0" || $cargo === "") {
    echo 'Cadastre um Cargo para o Usuário';
    exit();
}

// ===== Validar email (sem mudar regra, só prepared) =====
$stmtEmail = $pdo->prepare("SELECT id FROM {$tabela} WHERE email = :email LIMIT 1");
$stmtEmail->execute([':email' => $email]);
$rowEmail = $stmtEmail->fetch(PDO::FETCH_ASSOC);

if ($rowEmail && $id !== (int)$rowEmail['id']) {
    echo 'Email já Cadastrado, escolha outro!!';
    exit();
}

// ===== Validar CPF (quando informado) =====
if ($cpf !== "") {
    $stmtCpf = $pdo->prepare("SELECT id FROM {$tabela} WHERE cpf = :cpf LIMIT 1");
    $stmtCpf->execute([':cpf' => $cpf]);
    $rowCpf = $stmtCpf->fetch(PDO::FETCH_ASSOC);

    if ($rowCpf && $id !== (int)$rowCpf['id']) {
        echo 'CPF já Cadastrado, escolha outro!!';
        exit();
    }
}

// ===== Buscar foto atual (para troca) =====
$foto = 'sem-foto.jpg';
if ($id > 0) {
    $stmtFoto = $pdo->prepare("SELECT foto FROM {$tabela} WHERE id = :id LIMIT 1");
    $stmtFoto->execute([':id' => $id]);
    $rowFoto = $stmtFoto->fetch(PDO::FETCH_ASSOC);
    if ($rowFoto && !empty($rowFoto['foto'])) {
        $foto = (string)$rowFoto['foto'];
    }
}

// ===== Upload de foto (mantém lógica original, só path seguro) =====
if (!empty($_FILES['foto']['name'])) {
    $nome_img = date('d-m-Y H:i:s') . '-' . (string)$_FILES['foto']['name'];
    $nome_img = preg_replace('/[ :]+/', '-', $nome_img);

    $ext = strtolower((string)pathinfo($nome_img, PATHINFO_EXTENSION));
    if (!in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'webp'], true)) {
        echo 'Extensão de Imagem não permitida!';
        exit();
    }

    $dirPerfil = realpath(__DIR__ . '/../../img/perfil');
    if (!$dirPerfil) {
        echo 'Erro ao localizar pasta de imagens!';
        exit();
    }

    // Exclui foto anterior
    if ($foto !== "sem-foto.jpg") {
        $old = $dirPerfil . DIRECTORY_SEPARATOR . $foto;
        if (is_file($old)) {
            @unlink($old);
        }
    }

    $caminho = $dirPerfil . DIRECTORY_SEPARATOR . $nome_img;
    $imagem_temp = (string)($_FILES['foto']['tmp_name'] ?? '');

    if (!@move_uploaded_file($imagem_temp, $caminho)) {
        echo 'Erro ao enviar imagem!';
        exit();
    }

    $foto = $nome_img;
}

// ===== Insert / Update (mantém exatamente os campos do original) =====
if ($id <= 0) {
    $query = $pdo->prepare("
        INSERT INTO {$tabela}
        SET nome = :nome,
            email = :email,
            cpf = :cpf,
            senha = '',
            senha_crip = :senha_crip,
            nivel = :nivel,
            data = curDate(),
            ativo = 'Sim',
            telefone = :telefone,
            endereco = :endereco,
            foto = :foto,
            atendimento = :atendimento,
            visualizar = :visualizar
    ");
    $query->bindValue(':senha_crip', $senha_crip);
} else {
    $query = $pdo->prepare("
        UPDATE {$tabela}
        SET nome = :nome,
            email = :email,
            cpf = :cpf,
            nivel = :nivel,
            telefone = :telefone,
            endereco = :endereco,
            foto = :foto,
            atendimento = :atendimento,
            visualizar = :visualizar
        WHERE id = :id
    ");
    $query->bindValue(':id', $id, PDO::PARAM_INT);
}

$query->bindValue(':nome', $nome);
$query->bindValue(':email', $email);
$query->bindValue(':cpf', $cpf);
$query->bindValue(':nivel', $cargo);
$query->bindValue(':telefone', $telefone);
$query->bindValue(':endereco', $endereco);
$query->bindValue(':foto', $foto);
$query->bindValue(':atendimento', $atendimento);
$query->bindValue(':visualizar', $visualizar);

$query->execute();

echo 'Salvo com Sucesso';
?>
