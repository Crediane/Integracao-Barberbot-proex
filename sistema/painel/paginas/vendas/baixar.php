<?php
require_once __DIR__ . '/../_context_bootstrap.php';
/*
  Arquivo: paginas/vendas/baixar.php
  Ajuste: compatibilidade de sess00o (dono do sistema / dono do estabelecimento)
  Objetivo: evitar Warning "Undefined array key 'id'" e manter a baixa funcionando normalmente.
*/

require_once __DIR__ . '/../../../conexao.php';
$tabela = 'receber';

// ===== Recuperar ID do usurio logado de forma segura (sem warnings PHP 8+) =====
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
    echo 'Sess00o expirada. Fa04a login novamente.';
    exit();
}

// ===== Receber ID da conta a baixar =====
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id <= 0) {
    echo 'ID invlido!';
    exit();
}

// ===== Verificar caixa aberto (mantido) =====
$query1 = $pdo->query("SELECT * FROM caixas WHERE operador = '$id_usuario' AND data_fechamento IS NULL ORDER BY id DESC LIMIT 1");
$res1 = $query1->fetchAll(PDO::FETCH_ASSOC);

if (@count($res1) > 0) {
    $id_caixa = (int)@$res1[0]['id'];
} else {
    $id_caixa = 0;
}

// ===== UPDATE (mesma lgica) =====
$st = $pdo->prepare("UPDATE $tabela 
                        SET pago = 'Sim',
                            usuario_baixa = :usuario_baixa,
                            data_pgto = curDate(),
                            caixa = :caixa,
                            hora = curTime()
                      WHERE id = :id");

$st->execute([
    ':usuario_baixa' => $id_usuario,
    ':caixa'         => $id_caixa,
    ':id'            => $id
]);

echo 'Baixado com Sucesso';
