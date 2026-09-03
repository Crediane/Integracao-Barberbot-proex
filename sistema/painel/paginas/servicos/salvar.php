<?php
require_once __DIR__ . '/../_context_bootstrap.php';
$__con1 = __DIR__ . "/../../conexao.php";   // /sistema/painel/conexao.php
$__con2 = __DIR__ . "/../../../conexao.php"; // /sistema/conexao.php
if (is_file($__con1)) {
	require_once($__con1);
} else {
	require_once($__con2);
}

// tenant_db é opcional (se existir no projeto).
// Correção pontual: se o tenant_db.php trocar $pdo para o banco da loja,
// mantemos essa conexão. Não restaurar o $pdo anterior evita salvar no banco errado.
$tenant_db_path = $_SERVER['DOCUMENT_ROOT'] . "/api/tenant_db.php";
if (is_file($tenant_db_path)) {
	require_once($tenant_db_path);
}

$tabela = 'servicos';

/* ===========================
   Helpers (não muda fluxo)
   =========================== */
function bb_col_info(PDO $pdo, string $table, string $col): ?array {
	$sql = "SELECT DATA_TYPE, IS_NULLABLE
			FROM INFORMATION_SCHEMA.COLUMNS
			WHERE TABLE_SCHEMA = DATABASE()
			  AND TABLE_NAME = :t
			  AND COLUMN_NAME = :c
			LIMIT 1";
	$st = $pdo->prepare($sql);
	$st->execute([':t'=>$table, ':c'=>$col]);
	$r = $st->fetch(PDO::FETCH_ASSOC);
	return $r ?: null;
}

function bb_col_exists(PDO $pdo, string $table, string $col): bool {
	return bb_col_info($pdo, $table, $col) !== null;
}

function bb_first_existing_col(PDO $pdo, string $table, array $candidates): string {
	foreach ($candidates as $c) {
		if (bb_col_exists($pdo, $table, $c)) return $c;
	}
	return '';
}

function bb_is_numeric_type(string $dt): bool {
	$dt = strtolower($dt);
	return in_array($dt, ['int','integer','tinyint','smallint','mediumint','bigint','decimal','numeric','float','double'], true);
}

function bb_pick_tenant_value(PDO $pdo, string $table, string $tenantCol) {
	if ($tenantCol === '') return null;

	$info = bb_col_info($pdo, $table, $tenantCol);
	$dt = $info ? strtolower((string)$info['DATA_TYPE']) : '';

	// tenta pegar do tenant_db.php se existir
	if (function_exists('bb_tenant_ctx') && function_exists('bb_table_tenant_filter')) {
		$ctx = bb_tenant_ctx();
		$tf  = bb_table_tenant_filter($pdo, $table, $ctx);
		if (!empty($tf['col']) && $tf['col'] === $tenantCol) {
			$val = $tf['val'] ?? null;
			if ($val !== null && $val !== '' && $val !== 0 && $val !== '0') {
				return $val;
			}
		}
	}

	// fallback: sessão/GET comuns do sistema
	// numéricos (id_loja/id_empresa/id_usuario etc)
	$numKeys = ['id_loja','loja_id','id_empresa','empresa_id','id_usuario','usuario_id','empresa','loja','usuario'];
	// string (slug)
	$strKeys = ['tenant_slug','loja','empresa','slug','tenant'];

	if (bb_is_numeric_type($dt)) {
		foreach ($numKeys as $k) {
			if (isset($_SESSION[$k]) && (int)$_SESSION[$k] > 0) return (int)$_SESSION[$k];
			if (isset($_GET[$k]) && (int)$_GET[$k] > 0) return (int)$_GET[$k];
		}
		// se não achou, não força 0 (evita travar)
		return null;
	}

	// tipo string
	foreach ($strKeys as $k) {
		if (isset($_SESSION[$k]) && trim((string)$_SESSION[$k]) !== '') return trim((string)$_SESSION[$k]);
		if (isset($_GET[$k]) && trim((string)$_GET[$k]) !== '') return trim((string)$_GET[$k]);
	}

	return null;
}

/* ===========================
   INPUTS (mesma lógica)
   =========================== */
$id   = isset($_POST['id']) ? trim((string)$_POST['id']) : '';
$nome = isset($_POST['nome']) ? trim((string)$_POST['nome']) : '';

$valor = isset($_POST['valor']) ? (string)$_POST['valor'] : '';
$valor = str_replace('.', '', $valor);
$valor = str_replace(',', '.', $valor);

$comissao = isset($_POST['comissao']) ? (string)$_POST['comissao'] : '';
$comissao = str_replace(',', '.', $comissao);
$comissao = str_replace('%', '', $comissao);

$tempo = isset($_POST['tempo']) ? (string)$_POST['tempo'] : '';
$dias_retorno = isset($_POST['dias_retorno']) ? (string)$_POST['dias_retorno'] : '';
$categoria = isset($_POST['categoria']) ? (string)$_POST['categoria'] : '0';
$combo_ativo = isset($_POST['combo_ativo']) ? trim((string)$_POST['combo_ativo']) : 'Não';
$combo_ativo = ($combo_ativo === 'Sim') ? 'Sim' : 'Não';
$combo_qtd_sessoes = isset($_POST['combo_qtd_sessoes']) ? (int)$_POST['combo_qtd_sessoes'] : 0;
$combo_validade_dias = isset($_POST['combo_validade_dias']) ? (int)$_POST['combo_validade_dias'] : 0;

if ($id !== '' && !ctype_digit($id)) {
	echo 'ID inválido!';
	exit();
}

if ($nome === '') {
	echo 'Informe o nome do serviço!';
	exit();
}

// Mantém a lógica original: campos numéricos vazios continuam indo como zero.
$valor = ($valor !== '' && is_numeric($valor)) ? $valor : 0;
$comissao = ($comissao !== '' && is_numeric($comissao)) ? $comissao : 0;
$dias_retorno = ($dias_retorno !== '' && ctype_digit((string)$dias_retorno)) ? (int)$dias_retorno : 0;
$tempo = ($tempo !== '' && ctype_digit((string)$tempo)) ? (int)$tempo : 0;

if ($categoria == '0' || $categoria === '') {
	echo 'Cadastre uma Categoria de Serviços para o Serviço';
	exit();
}

if ($combo_ativo === 'Sim') {
	if ($combo_qtd_sessoes <= 0) {
		echo 'Informe a quantidade de sessões do combo!';
		exit();
	}
	if ($combo_validade_dias <= 0) {
		echo 'Informe a validade do combo em dias!';
		exit();
	}
} else {
	$combo_qtd_sessoes = 0;
	$combo_validade_dias = 0;
}

/* ===========================
   TENANT (só se existir col)
   =========================== */
$tenantCandidates = ['id_loja','loja','id_empresa','empresa','id_usuario','usuario','tenant','tenant_id','tenant_slug'];

$tenantColServ = bb_first_existing_col($pdo, 'servicos', $tenantCandidates);
$tenantColCat  = bb_first_existing_col($pdo, 'cat_servicos', $tenantCandidates);

$tenantValServ = ($tenantColServ !== '') ? bb_pick_tenant_value($pdo, 'servicos', $tenantColServ) : null;
$tenantValCat  = ($tenantColCat  !== '') ? bb_pick_tenant_value($pdo, 'cat_servicos', $tenantColCat) : null;

$colComboAtivo = bb_col_exists($pdo, 'servicos', 'combo_ativo');
$colComboQtd = bb_col_exists($pdo, 'servicos', 'combo_qtd_sessoes');
$colComboValidade = bb_col_exists($pdo, 'servicos', 'combo_validade_dias');

/* ===========================
   VALIDAR CATEGORIA
   (se tiver tenant na tabela de categorias E tiver valor, valida no tenant)
   =========================== */
try {
	$sqlCat = "SELECT id FROM cat_servicos WHERE id = :id";
	if ($tenantColCat !== '' && $tenantValCat !== null) {
		$sqlCat .= " AND {$tenantColCat} = :tenant";
	}
	$sqlCat .= " LIMIT 1";

	$stCat = $pdo->prepare($sqlCat);
	$stCat->bindValue(':id', (int)$categoria, PDO::PARAM_INT);
	if ($tenantColCat !== '' && $tenantValCat !== null) {
		$stCat->bindValue(':tenant', $tenantValCat, is_int($tenantValCat) ? PDO::PARAM_INT : PDO::PARAM_STR);
	}
	$stCat->execute();

	if (!$stCat->fetchColumn()) {
		echo 'Categoria inválida!';
		exit();
	}
} catch (Throwable $e) {
	echo 'Erro ao validar a categoria!';
	exit();
}

/* ===========================
   VALIDAR NOME
   (se servicos tiver tenant e tiver valor, valida dentro do tenant)
   =========================== */
try {
	$sqlNome = "SELECT id FROM servicos WHERE nome = :nome";
	if ($tenantColServ !== '' && $tenantValServ !== null) {
		$sqlNome .= " AND {$tenantColServ} = :tenant";
	}
	$sqlNome .= " LIMIT 1";

	$st = $pdo->prepare($sqlNome);
	$st->bindValue(':nome', $nome, PDO::PARAM_STR);
	if ($tenantColServ !== '' && $tenantValServ !== null) {
		$st->bindValue(':tenant', $tenantValServ, is_int($tenantValServ) ? PDO::PARAM_INT : PDO::PARAM_STR);
	}
	$st->execute();

	$achouId = $st->fetchColumn();

	if ($achouId && $id != $achouId) {
		echo 'Nome já Cadastrado, escolha outro!!';
		exit();
	}
} catch (Throwable $e) {
	echo 'Erro ao validar o nome!';
	exit();
}

/* ===========================
   BUSCA FOTO ATUAL
   =========================== */
$foto = 'sem-foto.jpg';
if ($id !== '') {
	try {
		$sqlFoto = "SELECT foto FROM servicos WHERE id = :id";
		if ($tenantColServ !== '' && $tenantValServ !== null) {
			$sqlFoto .= " AND {$tenantColServ} = :tenant";
		}
		$sqlFoto .= " LIMIT 1";

		$st = $pdo->prepare($sqlFoto);
		$st->bindValue(':id', (int)$id, PDO::PARAM_INT);
		if ($tenantColServ !== '' && $tenantValServ !== null) {
			$st->bindValue(':tenant', $tenantValServ, is_int($tenantValServ) ? PDO::PARAM_INT : PDO::PARAM_STR);
		}
		$st->execute();

		$fotoAtual = $st->fetchColumn();
		if ($fotoAtual) $foto = (string)$fotoAtual;

	} catch (Throwable $e) {
		// segue com sem-foto
	}
}

/* ===========================
   UPLOAD FOTO (mesma lógica)
   =========================== */
if (isset($_FILES['foto']) && !empty($_FILES['foto']['name'])) {

	$orig = basename((string)$_FILES['foto']['name']);
	$nome_img = date('Y-m-d_H-i-s') . '-' . $orig;
	$nome_img = preg_replace('/[^A-Za-z0-9._-]/', '-', $nome_img);

	$ext = strtolower(pathinfo($nome_img, PATHINFO_EXTENSION));

	if ($ext == 'png' || $ext == 'jpg' || $ext == 'jpeg' || $ext == 'gif' || $ext == 'webp') {

		if ($foto != "sem-foto.jpg") {
			@unlink(__DIR__ . '/../../img/servicos/' . basename($foto));
		}

		$foto = $nome_img;

		$caminho = __DIR__ . '/../../img/servicos/' . $nome_img;
		$imagem_temp = $_FILES['foto']['tmp_name'];

		if (!@move_uploaded_file($imagem_temp, $caminho)) {
			echo 'Erro ao enviar a imagem!';
			exit();
		}

	} else {
		echo 'Extensão de Imagem não permitida!';
		exit();
	}
}

/* ===========================
   INSERT / UPDATE
   (só seta tenant se existir coluna E tiver valor)
   =========================== */
try {

	if ($id === '') {

		$sqlIns = "
			INSERT INTO servicos
			SET nome = :nome,
				categoria = :categoria,
				valor = :valor,
				dias_retorno = :dias_retorno,
				ativo = 'Sim',
				foto = :foto,
				comissao = :comissao,
				tempo = :tempo
		";

		if ($colComboAtivo) {
			$sqlIns .= ", combo_ativo = :combo_ativo";
		}
		if ($colComboQtd) {
			$sqlIns .= ", combo_qtd_sessoes = :combo_qtd_sessoes";
		}
		if ($colComboValidade) {
			$sqlIns .= ", combo_validade_dias = :combo_validade_dias";
		}

		if ($tenantColServ !== '' && $tenantValServ !== null) {
			$sqlIns .= ", {$tenantColServ} = :tenant";
		}

		$query = $pdo->prepare($sqlIns);

	} else {

		$sqlUp = "
			UPDATE servicos
			SET nome = :nome,
				categoria = :categoria,
				valor = :valor,
				dias_retorno = :dias_retorno,
				foto = :foto,
				comissao = :comissao,
				tempo = :tempo
		";

		if ($colComboAtivo) {
			$sqlUp .= ", combo_ativo = :combo_ativo";
		}
		if ($colComboQtd) {
			$sqlUp .= ", combo_qtd_sessoes = :combo_qtd_sessoes";
		}
		if ($colComboValidade) {
			$sqlUp .= ", combo_validade_dias = :combo_validade_dias";
		}

		$sqlUp .= " WHERE id = :id";

		if ($tenantColServ !== '' && $tenantValServ !== null) {
			$sqlUp .= " AND {$tenantColServ} = :tenant";
		}

		$query = $pdo->prepare($sqlUp);
		$query->bindValue(':id', (int)$id, PDO::PARAM_INT);
	}

	$query->bindValue(':nome', $nome, PDO::PARAM_STR);
	$query->bindValue(':categoria', (int)$categoria, PDO::PARAM_INT);
	$query->bindValue(':valor', $valor);
	$query->bindValue(':dias_retorno', $dias_retorno);
	$query->bindValue(':foto', $foto, PDO::PARAM_STR);
	$query->bindValue(':comissao', $comissao);
	$query->bindValue(':tempo', $tempo);

	if ($colComboAtivo) {
		$query->bindValue(':combo_ativo', $combo_ativo, PDO::PARAM_STR);
	}
	if ($colComboQtd) {
		$query->bindValue(':combo_qtd_sessoes', $combo_qtd_sessoes, PDO::PARAM_INT);
	}
	if ($colComboValidade) {
		$query->bindValue(':combo_validade_dias', $combo_validade_dias, PDO::PARAM_INT);
	}

	if ($tenantColServ !== '' && $tenantValServ !== null) {
		$query->bindValue(':tenant', $tenantValServ, is_int($tenantValServ) ? PDO::PARAM_INT : PDO::PARAM_STR);
	}

	$query->execute();

	if ($id !== '' && $query->rowCount() === 0) {
		// Evita retornar sucesso quando o ID não existe no banco/tenant atual.
		echo 'ID inválido!';
		exit();
	}

	echo 'Salvo com Sucesso';
	exit();

} catch (Throwable $e) {
	echo 'Erro ao salvar!';
	exit();
}
?>
