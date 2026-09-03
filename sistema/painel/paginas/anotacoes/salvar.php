<?php
require_once __DIR__ . '/../_context_bootstrap.php';
$tabela = 'anotacoes';
require_once __DIR__ . '/../../../conexao.php';


$id_usuario = @$_SESSION['id'];

$id           = @$_POST['id'];
$titulo       = @$_POST['titulo'];
$msg          = @$_POST['msg'];
$mostrar_home = @$_POST['mostrar_home'];
$privado      = @$_POST['privado'];
$data         = @$_POST['data'];

$id_cliente = @$_POST['id_cliente'] ?? null;
$id_produto = @$_POST['id_produto'] ?? null;
$id_servico = @$_POST['id_servico'] ?? null;

// NOVO: Status de baixa / acerto com cliente
$status_acerto = isset($_POST['status_acerto']) && $_POST['status_acerto'] != ''
    ? $_POST['status_acerto']
    : 'Pendente';

// Formata data ou define como data atual se vazio
if (empty($data)) {
    $data = date('Y-m-d');
}

try {
    // Verifica se a anotação já existe com o mesmo título e usuário
    if (empty($id)) {
        $checkQuery = $pdo->prepare("SELECT COUNT(*) FROM $tabela WHERE titulo = :titulo AND usuario = :usuario");
        $checkQuery->bindValue(":titulo", $titulo);
        $checkQuery->bindValue(":usuario", $id_usuario);
        $checkQuery->execute();
        
        if ($checkQuery->fetchColumn() > 0) {
            echo 'Anotação já existe.';
            exit;
        }
        
        // Insert new record
        $query = $pdo->prepare("INSERT INTO $tabela SET 
            titulo        = :titulo, 
            msg           = :msg, 
            usuario       = :usuario,
            data          = :data,
            mostrar_home  = :mostrar_home, 
            privado       = :privado,
            id_cliente    = :id_cliente,
            id_produto    = :id_produto,
            id_servico    = :id_servico,
            status_acerto = :status_acerto");
    } else {
        // Update existing record
        $query = $pdo->prepare("UPDATE $tabela SET 
            titulo        = :titulo, 
            msg           = :msg, 
            usuario       = :usuario,
            data          = :data,
            mostrar_home  = :mostrar_home, 
            privado       = :privado,
            id_cliente    = :id_cliente,
            id_produto    = :id_produto,
            id_servico    = :id_servico,
            status_acerto = :status_acerto
            WHERE id      = :id");
        $query->bindValue(":id", $id);
    }

    // Bind all values
    $query->bindValue(":titulo", $titulo);
    $query->bindValue(":msg", $msg);
    $query->bindValue(":usuario", $id_usuario);
    $query->bindValue(":data", $data);
    $query->bindValue(":mostrar_home", $mostrar_home);
    $query->bindValue(":privado", $privado);
    $query->bindValue(":id_cliente", $id_cliente);
    $query->bindValue(":id_produto", $id_produto);
    $query->bindValue(":id_servico", $id_servico);
    $query->bindValue(":status_acerto", $status_acerto);

    $query->execute();

    echo 'Salvo com Sucesso';

} catch (Exception $e) {
    echo 'Erro ao salvar: ' . $e->getMessage();
}
?>
