<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once(__DIR__ . "/../../../conexao.php");

$tabelaRel  = 'servicos_func';
$tabelaServ = 'servicos';
$tabelaFunc = 'usuarios';

$id_func = 0;
if (isset($_POST['func'])) {
	$id_func = (int)$_POST['func'];
} elseif (isset($_POST['id'])) {
	$id_func = (int)$_POST['id'];
} elseif (isset($_GET['func'])) {
	$id_func = (int)$_GET['func'];
} elseif (isset($_GET['id'])) {
	$id_func = (int)$_GET['id'];
}

if ($id_func <= 0) {
	echo '<small>Funcionário inválido!</small>';
	exit();
}

/**
 * No /loja/ o dono pode ser "Administrador".
 * Fora do /loja/ normalmente não lista administrador como funcionário.
 */
$isLoja = (isset($_SERVER['REQUEST_URI']) && strpos($_SERVER['REQUEST_URI'], '/loja/') !== false);

$sqlF = "SELECT id FROM {$tabelaFunc} WHERE id = :id";
if (!$isLoja) {
	$sqlF .= " AND nivel != 'Administrador'";
}
$sqlF .= " LIMIT 1";

$stmtF = $pdo->prepare($sqlF);
$stmtF->bindValue(':id', $id_func, PDO::PARAM_INT);
$stmtF->execute();
if (!$stmtF->fetchColumn()) {
	echo '<small>Funcionário inválido!</small>';
	exit();
}

$sql = "SELECT sf.id, s.nome AS nome_servico
		FROM {$tabelaRel} sf
		INNER JOIN {$tabelaServ} s ON s.id = sf.servico
		WHERE sf.funcionario = :func
		ORDER BY sf.id DESC";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':func', $id_func, PDO::PARAM_INT);
$stmt->execute();

$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);

$nivel = $_SESSION['nivel'] ?? '';
$canDelete = (mb_strtolower(trim((string)$nivel)) === 'administrador');

if ($total_reg > 0) {

echo <<<HTML
<small><small>
<table class="table table-hover">
<thead>
<tr>
<th>Serviço</th>
<th>Excluir</th>
</tr>
</thead>
<tbody>
HTML;

	for ($i=0; $i < $total_reg; $i++) {
		$id = (int)$res[$i]['id'];
		$nome_servico = htmlspecialchars((string)$res[$i]['nome_servico'], ENT_QUOTES, 'UTF-8');

		if ($canDelete) {
			$colExcluir = <<<HTML
	<li class="dropdown head-dpdn2" style="display: inline-block;">
	<a href="javascript:void(0)" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
		<big><i class="fa fa-trash-o text-danger"></i></big>
	</a>
	<ul class="dropdown-menu" style="margin-left:-230px;">
	<li>
	<div class="notification_desc2">
	<p>Confirmar Exclusão?
		<a href="javascript:void(0)" onclick="excluirServico('{$id}', '{$id_func}'); return false;">
			<span class="text-danger">Sim</span>
		</a>
	</p>
	</div>
	</li>
	</ul>
	</li>
HTML;
		} else {
			$colExcluir = '<span class="text-muted">-</span>';
		}

echo <<<HTML
<tr>
<td>{$nome_servico}</td>
<td>{$colExcluir}</td>
</tr>
HTML;
	}

echo <<<HTML
</tbody>
<small><div align="center" id="mensagem-servico-excluir"></div></small>
</table>
</small></small>
HTML;

} else {
	echo '<small>Não possui nenhum Serviço Cadastrado!</small>';
}
?>

<script type="text/javascript">
	function excluirServico(id, func){
		$.ajax({
			url: 'paginas/funcionarios/excluir-servico.php',
			method: 'POST',
			data: { id: id },
			dataType: "text",
			success: function (mensagem) {
				if (mensagem.trim() == "Excluído com Sucesso") {
					listarServicos(func);
				} else {
					$('#mensagem-servico-excluir').addClass('text-danger');
					$('#mensagem-servico-excluir').text(mensagem);
				}
			}
		});
	}
</script>
