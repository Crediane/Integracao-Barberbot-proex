<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once(__DIR__ . "/../../../conexao.php");

$tenant_db_path = $_SERVER['DOCUMENT_ROOT'] . "/api/tenant_db.php";
if (is_file($tenant_db_path)) {
    require_once($tenant_db_path);
}

// ==========================================================
// GARANTE CONEXÃO NO BANCO DO TENANT (marketplace)
// ----------------------------------------------------------
// Se por algum motivo o $pdo vier apontando para o banco master,
// este endpoint não encontra o serviço e retorna "ID inválido!".
// Aqui só forçamos a reconexão se houver tenant_db na sessão.
// ==========================================================
try {
	$expectedDb = $_SESSION['tenant_db'] ?? '';
	if ($expectedDb !== '' && isset($pdo) && $pdo instanceof PDO) {
		$currentDb = $pdo->query("SELECT DATABASE()")->fetchColumn();
		if ($currentDb && $currentDb !== $expectedDb) {
			$host = $_SESSION['tenant_host'] ?? 'localhost';
			$user = $_SESSION['tenant_user'] ?? '';
			$pass = $_SESSION['tenant_pass'] ?? '';
			if ($user !== '') {
				$pdoTenant = new PDO("mysql:host={$host};dbname={$expectedDb};charset=utf8mb4", $user, (string)$pass);
				$pdoTenant->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
				$pdoTenant->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
				$pdo = $pdoTenant;
			}
		}
	}
} catch (Throwable $e) {
	// silencioso: mantém o fluxo original
}

$tabela = 'servicos';

/* permissão padrão: Administrador */
$nivel = $_SESSION['nivel'] ?? '';
if ($nivel !== 'Administrador') {
    echo 'Acesso negado!';
    exit();
}

$id   = isset($_POST['id'])   ? trim((string)$_POST['id'])   : '';
$acao = isset($_POST['acao']) ? trim((string)$_POST['acao']) : '';

if ($id === '' || !ctype_digit($id)) {
    echo 'ID inválido!';
    exit();
}
$id = (int)$id;

if ($acao !== 'Sim' && $acao !== 'Não') {
    echo 'Ação inválida!';
    exit();
}

/* valida existência */
$st = $pdo->prepare("SELECT 1 FROM {$tabela} WHERE id = :id LIMIT 1");
$st->bindValue(':id', $id, PDO::PARAM_INT);
$st->execute();
if (!$st->fetchColumn()) {
    echo 'ID inválido!';
    exit();
}

try {
    $up = $pdo->prepare("UPDATE {$tabela} SET ativo = :ativo WHERE id = :id");
    $up->bindValue(':ativo', $acao);
    $up->bindValue(':id', $id, PDO::PARAM_INT);
    $up->execute();

    echo 'Alterado com Sucesso';
} catch (Throwable $e) {
    echo 'Erro ao Alterar!';
}
