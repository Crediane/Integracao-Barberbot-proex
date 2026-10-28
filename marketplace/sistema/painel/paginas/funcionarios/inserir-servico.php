<?php
require_once __DIR__ . '/../_context_bootstrap.php';
@session_start();
require_once dirname(__DIR__, 3) . '/conexao.php';

/*
|--------------------------------------------------------------------------
| Funcionários > Inserir Serviço
| Mantém a lógica original do sistema:
| - valida funcionário
| - valida serviço
| - evita duplicidade
| - insere em servicos_func
|
| Ajuste aplicado:
| - em /loja/{slug}/..., reforça a conexão do tenant usando a função
|   central conectarBancoPorSlug($slug), sem criar regra paralela.
|--------------------------------------------------------------------------
*/

$tabela = 'servicos_func';

/**
 * Extrai o slug da URL no padrão /loja/{slug}/...
 */
if (!function_exists('bb_funcionarios_obter_slug_request')) {
	function bb_funcionarios_obter_slug_request(): string {
		$uri = $_SERVER['REQUEST_URI'] ?? '';
		$path = (string) parse_url($uri, PHP_URL_PATH);
		$path = trim($path, '/');

		if ($path === '') {
			return '';
		}

		$partes = explode('/', $path);
		$total = count($partes);

		for ($i = 0; $i < $total; $i++) {
			if ($partes[$i] === 'loja' && isset($partes[$i + 1])) {
				$slug = trim((string) $partes[$i + 1]);

				if ($slug !== '') {
					return preg_replace('/[^a-z0-9\-]/i', '', $slug);
				}
			}
		}

		return '';
	}
}

/**
 * Reforça o banco do tenant somente quando estiver em /loja/{slug}/...
 * Usa a função central do sistema para não duplicar a lógica de conexão.
 */
$slug_request = bb_funcionarios_obter_slug_request();
if ($slug_request !== '' && function_exists('conectarBancoPorSlug')) {
	try {
		$pdo_tenant = conectarBancoPorSlug($slug_request);
		if ($pdo_tenant instanceof PDO) {
			$pdo = $pdo_tenant;
		}
	} catch (Throwable $e) {
		// Mantém o fluxo padrão já carregado no conexao.php
	}
}

// ==========================================================
// LÓGICA ORIGINAL (SEM MUDAR)
// ==========================================================

// funcionário
$func = 0;
if (isset($_POST['id'])) {
	$func = (int) $_POST['id'];
} elseif (isset($_POST['func'])) {
	$func = (int) $_POST['func'];
} elseif (isset($_POST['funcionario'])) {
	$func = (int) $_POST['funcionario'];
} elseif (isset($_GET['id'])) {
	$func = (int) $_GET['id'];
} elseif (isset($_GET['func'])) {
	$func = (int) $_GET['func'];
}

// serviço
$servico_raw = $_POST['servico'] ?? ($_POST['id_servico'] ?? ($_GET['servico'] ?? ($_GET['id_servico'] ?? '')));
$servico_raw = trim((string) $servico_raw);

// validações (mantém mensagens)
if ($func <= 0) {
	echo 'Funcionário inválido!';
	exit();
}

if ($servico_raw === '') {
	echo 'Serviço inválido!';
	exit();
}

// valida funcionário (tabela usuarios)
$stmt = $pdo->prepare("SELECT id FROM usuarios WHERE id = :id LIMIT 1");
$stmt->bindValue(':id', $func, PDO::PARAM_INT);
$stmt->execute();

if (!$stmt->fetchColumn()) {
	echo 'Funcionário inválido!';
	exit();
}

// resolve serviço por ID
$servico = 0;
if (ctype_digit($servico_raw)) {
	$servico = (int) $servico_raw;

	$stmt = $pdo->prepare("SELECT id FROM servicos WHERE id = :id LIMIT 1");
	$stmt->bindValue(':id', $servico, PDO::PARAM_INT);
	$stmt->execute();

	if (!$stmt->fetchColumn()) {
		$servico = 0;
	}
}

// fallback por nome
if ($servico <= 0) {
	$stmt = $pdo->prepare("SELECT id FROM servicos WHERE TRIM(nome) = TRIM(:nome) LIMIT 1");
	$stmt->bindValue(':nome', $servico_raw, PDO::PARAM_STR);
	$stmt->execute();
	$row = $stmt->fetch(PDO::FETCH_ASSOC);

	if ($row && isset($row['id'])) {
		$servico = (int) $row['id'];
	}
}

if ($servico <= 0) {
	echo 'Serviço inválido!';
	exit();
}

// duplicidade
$stmt = $pdo->prepare("SELECT 1 FROM {$tabela} WHERE funcionario = :func AND servico = :serv LIMIT 1");
$stmt->bindValue(':func', $func, PDO::PARAM_INT);
$stmt->bindValue(':serv', $servico, PDO::PARAM_INT);
$stmt->execute();

if ($stmt->fetchColumn()) {
	echo 'Serviço já adicionado ao Funcionário!';
	exit();
}

// insere
$stmt = $pdo->prepare("INSERT INTO {$tabela} (servico, funcionario) VALUES (:serv, :func)");
$stmt->bindValue(':serv', $servico, PDO::PARAM_INT);
$stmt->bindValue(':func', $func, PDO::PARAM_INT);
$stmt->execute();

echo 'Salvo com Sucesso';
?>