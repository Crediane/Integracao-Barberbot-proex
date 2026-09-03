<?php
require_once __DIR__ . '/../_context_bootstrap.php';
// ============================================================================
// BarberBot Software - Módulo Pagar - Baixar Conta
// Arquivo: /public_html/sistema/painel/paginas/pagar/baixar.php
// Ajustes: compatível com Admin do Sistema e Dono do Estabelecimento (bancos separados),
//          evita Warnings PHP 8+ (Undefined array key) e usa prepared statements.
// Regra de negócio preservada:
//  - Se houver hash, chama api-excluir.php
//  - Verifica caixa aberto do operador
//  - Atualiza conta para pago = Sim, grava usuario_baixa, data_pgto, caixa e hora
// ============================================================================
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'pagar';

// ----------------------------------------------------------------------------
// Identificação do usuário logado (compatível com diferentes estruturas de sessão)
// ----------------------------------------------------------------------------
$id_usuario = 0;

if (isset($_SESSION['id']) && is_numeric($_SESSION['id'])) {
    $id_usuario = (int)$_SESSION['id'];
} elseif (isset($_SESSION['id_usuario']) && is_numeric($_SESSION['id_usuario'])) {
    $id_usuario = (int)$_SESSION['id_usuario'];
} elseif (isset($_SESSION['usuario_id']) && is_numeric($_SESSION['usuario_id'])) {
    $id_usuario = (int)$_SESSION['usuario_id'];
} elseif (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
    $id_usuario = (int)$_SESSION['user_id'];
} elseif (isset($_SESSION['usuario']) && is_array($_SESSION['usuario']) && isset($_SESSION['usuario']['id']) && is_numeric($_SESSION['usuario']['id'])) {
    $id_usuario = (int)$_SESSION['usuario']['id'];
} elseif (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id']) && is_numeric($_SESSION['user']['id'])) {
    $id_usuario = (int)$_SESSION['user']['id'];
}

if ($id_usuario <= 0) {
    echo 'Sessão expirada. Faça login novamente.';
    exit();
}

// ----------------------------------------------------------------------------
// POST (evita warnings PHP 8+)
// ----------------------------------------------------------------------------
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    echo 'ID inválido!';
    exit();
}

$valor = isset($_POST['valor']) ? (string)$_POST['valor'] : '0';

$data_pgto = isset($_POST['data_pgto']) ? trim((string)$_POST['data_pgto']) : '';
$pgto = isset($_POST['pgto']) ? trim((string)$_POST['pgto']) : '';

// Mantém o padrão: se não informar data, usa a data atual (não altera regra de baixa)
if ($data_pgto === '') {
    $data_pgto = date('Y-m-d');
}

// ----------------------------------------------------------------------------
// Buscar conta (evita res[0] quando não existe)
// ----------------------------------------------------------------------------
$hash = '';
try {
    $stSel = $pdo->prepare("SELECT hash FROM {$tabela} WHERE id = :id LIMIT 1");
    $stSel->execute([':id' => $id]);
    $row = $stSel->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
        echo 'Registro não encontrado!';
        exit();
    }

    $hash = isset($row['hash']) ? (string)$row['hash'] : '';
} catch (Throwable $e) {
    // Mantém padrão simples (sem expor detalhes)
    echo 'Erro ao localizar registro!';
    exit();
}

// ----------------------------------------------------------------------------
// Se houver hash, excluir cobrança na API (mantido)
// ----------------------------------------------------------------------------
if ($hash != "") {
    require __DIR__ . '/../../../../ajax/api-excluir.php';
}

// ----------------------------------------------------------------------------
// Verificar caixa aberto (mantido)
// ----------------------------------------------------------------------------
$id_caixa = 0;
$query1 = $pdo->prepare("SELECT id FROM caixas WHERE operador = :operador AND data_fechamento IS NULL ORDER BY id DESC LIMIT 1");
$query1->execute([':operador' => $id_usuario]);
$res1 = $query1->fetch(PDO::FETCH_ASSOC);
if ($res1 && isset($res1['id'])) {
    $id_caixa = (int)$res1['id'];
}

// ----------------------------------------------------------------------------
// Atualizar conta para "pago" (mantido) - prepared statement
// ----------------------------------------------------------------------------
$stUp = $pdo->prepare("UPDATE {$tabela}
    SET pgto = :pgto,
        valor = :valor,
        pago = 'Sim',
        usuario_baixa = :usuario_baixa,
        data_pgto = :data_pgto,
        caixa = :caixa,
        hora = curTime()
    WHERE id = :id");

$stUp->execute([
    ':pgto' => $pgto,
    ':valor' => $valor,
    ':usuario_baixa' => $id_usuario,
    ':data_pgto' => $data_pgto,
    ':caixa' => $id_caixa,
    ':id' => $id
]);

echo 'Baixado com Sucesso';
?>