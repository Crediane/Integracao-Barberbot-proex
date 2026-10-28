<?php
require_once __DIR__ . '/../_context_bootstrap.php';
/**
 * Excluir - USUARIOS
 * Correção segura:
 * - Não inicia sessão aqui (evita cruzamento MASTER/TENANT).
 * - Não usa HTTP_REFERER para descobrir loja.
 * - Deixa o conexao.php decidir sessão/tenant/banco.
 */
require_once __DIR__ . '/../../../conexao.php';

$tabela = 'usuarios';

/*
    PERMISSÃO
    - Admin do sistema
    - Dono/Administrador da barbearia
    (segue padrão do sistema usando $_SESSION['nivel'])
*/
$nivelSess = '';
if (isset($_SESSION['nivel'])) $nivelSess = $_SESSION['nivel'];
elseif (isset($_SESSION['nivel_usuario'])) $nivelSess = $_SESSION['nivel_usuario'];
elseif (isset($_SESSION['cargo'])) $nivelSess = $_SESSION['cargo'];

$nivelSess = trim((string)$nivelSess);
$nivelSessL = function_exists('mb_strtolower') ? mb_strtolower($nivelSess, 'UTF-8') : strtolower($nivelSess);

$canDelete = false;
if ($nivelSessL === 'administrador' || $nivelSessL === 'admin' || $nivelSessL === 'superadmin' || $nivelSessL === 'super administrador' || $nivelSessL === 'superadministrador') {
	$canDelete = true;
}

if (!$canDelete) {
	echo 'Acesso negado!';
	exit();
}

/* ID (segue padrão: recebe por POST) */
$id = 0;
if (isset($_POST['id'])) $id = (int)$_POST['id'];
elseif (isset($_POST['id_func'])) $id = (int)$_POST['id_func'];
elseif (isset($_POST['func'])) $id = (int)$_POST['func'];
elseif (isset($_POST['funcionario'])) $id = (int)$_POST['funcionario'];
elseif (isset($_GET['id'])) $id = (int)$_GET['id'];

if ($id <= 0) {
	echo 'ID inválido!';
	exit();
}

try {

	/* Confirma usuário existe antes de qualquer DELETE (evita deletar coisa errada) */
	$stmt = $pdo->prepare("SELECT foto, nivel FROM {$tabela} WHERE id = :id LIMIT 1");
	$stmt->bindValue(':id', $id, PDO::PARAM_INT);
	$stmt->execute();
	$dados = $stmt->fetch(PDO::FETCH_ASSOC);

	if (!$dados) {
		echo 'ID inválido!';
		exit();
	}

	/* Proteção comum do sistema: não excluir Administrador */
	$nivelUser = isset($dados['nivel']) ? trim((string)$dados['nivel']) : '';
	if ($nivelUser !== '') {
		$nivelUserL = function_exists('mb_strtolower') ? mb_strtolower($nivelUser, 'UTF-8') : strtolower($nivelUser);
		if ($nivelUserL === 'administrador' || $nivelUserL === 'admin') {
			echo 'Acesso negado!';
			exit();
		}
	}

	$pdo->beginTransaction();

	/* Apaga foto se não for padrão */
	$foto = $dados['foto'] ?? 'sem-foto.jpg';
	if ($foto && $foto !== "sem-foto.jpg") {
		$foto = basename($foto); // evita traversal sem mudar fluxo

		// mesmo padrão do projeto: img/perfil
		$dirPerfil = __DIR__ . '/../../img/perfil';
		$arquivo = $dirPerfil . '/' . $foto;

		if (is_file($arquivo)) {
			@unlink($arquivo);
		}
	}

	/* Exclui usuário */
	$stmtDel = $pdo->prepare("DELETE FROM {$tabela} WHERE id = :id");
	$stmtDel->bindValue(':id', $id, PDO::PARAM_INT);
	$stmtDel->execute();

		$pdo->commit();
	echo 'Excluído com Sucesso';

} catch (Throwable $e) {

	try { if ($pdo->inTransaction()) $pdo->rollBack(); } catch (Throwable $e2) {}

	// não expõe erro interno
	echo 'Erro ao Excluir!';
}
?>
