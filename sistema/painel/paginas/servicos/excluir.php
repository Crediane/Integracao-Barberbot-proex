<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once(__DIR__ . "/../../../conexao.php");

/* DB separado por loja: troca $pdo conforme /loja/slug */
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

/* Permissão padrão do sistema: só Administrador */
$nivel = isset($_SESSION['nivel']) ? $_SESSION['nivel'] : '';
if($nivel != 'Administrador'){
	echo 'Acesso negado!';
	exit();
}

/* pega id (tolerante) */
$id = '';
if(isset($_POST['id'])) $id = trim($_POST['id']);
if($id === '' && isset($_GET['id'])) $id = trim($_GET['id']);

if($id === '' || !ctype_digit($id)){
	echo 'ID inválido!';
	exit();
}
$id = (int)$id;

// ==========================================================
// SEGURANÇA EXTRA (sem mudar a lógica)
// ----------------------------------------------------------
// Se por algum motivo o banco estiver com IDs duplicados
// (ou id=0 em vários registros), um DELETE por id pode apagar
// MAIS de um registro. Aqui a gente trava antes.
// ==========================================================
$chk = $pdo->prepare("SELECT COUNT(*) FROM $tabela WHERE id = :id");
$chk->bindValue(':id', $id, PDO::PARAM_INT);
$chk->execute();
$qtd = (int)$chk->fetchColumn();
if ($qtd !== 1) {
    echo 'ID inválido (ou duplicado no banco)!';
    exit();
}

/* busca registro e valida existência
   ⚠️ Correção do bug: não dá pra usar "foto" como prova de existência.
      Se o serviço existir e o campo foto estiver NULL/"" (ou algum legacy),
      o fetchColumn retorna valor falsy e o código mostrava "ID inválido!".
      Mantém a lógica: só impede falso-positivo.
*/
$stmt = $pdo->prepare("SELECT id, foto FROM $tabela WHERE id = :id LIMIT 1");
$stmt->bindValue(':id', $id, PDO::PARAM_INT);
$stmt->execute();
$row = $stmt->fetch(PDO::FETCH_ASSOC);
if(!$row){
	echo 'ID inválido!';
	exit();
}
$foto = $row['foto'] ?? '';

/* exclui registro */
try{
	// LIMIT 1 por segurança
	$del = $pdo->prepare("DELETE FROM $tabela WHERE id = :id LIMIT 1");
	$del->bindValue(':id', $id, PDO::PARAM_INT);
	$del->execute();

	/* apaga foto se não for padrão */
	if($foto !== '' && $foto != 'sem-foto.jpg'){
		$path = __DIR__ . "/../../img/servicos/" . $foto;
		if(is_file($path)){
			@unlink($path);
		}
	}

	echo 'Excluído com Sucesso';
}catch(Throwable $e){
	echo 'Erro ao Excluir!';
}
?>
