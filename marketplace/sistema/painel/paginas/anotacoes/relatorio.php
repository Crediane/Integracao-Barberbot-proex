<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';

// filtros recebidos via POST ou GET
$id_cliente = $_POST['id_cliente'] ?? '';
$id_produto = $_POST['id_produto'] ?? '';
$id_servico = $_POST['id_servico'] ?? '';

// montar a SQL com filtros dinâmicos
$sql = "SELECT a.*, 
	c.nome as cliente, 
	p.nome as produto, 
	s.nome as servico 
	FROM anotacoes a
	LEFT JOIN clientes c ON a.id_cliente = c.id
	LEFT JOIN produtos p ON a.id_produto = p.id
	LEFT JOIN servicos s ON a.id_servico = s.id
	WHERE 1";

if ($id_cliente) $sql .= " AND a.id_cliente = '$id_cliente'";
if ($id_produto) $sql .= " AND a.id_produto = '$id_produto'";
if ($id_servico) $sql .= " AND a.id_servico = '$id_servico'";

$sql .= " ORDER BY a.id DESC";

$query = $pdo->query($sql);
$res = $query->fetchAll(PDO::FETCH_ASSOC);

?>

<h3>Relatório de Anotações</h3>
<table class="table table-bordered table-striped">
	<thead>
		<tr>
			<th>ID</th>
			<th>Cliente</th>
			<th>Produto</th>
			<th>Serviço</th>
			<th>Título</th>
			<th>Mensagem</th>
			<th>Data</th>
		</tr>
	</thead>
	<tbody>
		<?php foreach ($res as $row): ?>
		<tr>
			<td><?= $row['id'] ?></td>
			<td><?= $row['cliente'] ?></td>
			<td><?= $row['produto'] ?></td>
			<td><?= $row['servico'] ?></td>
			<td><?= $row['titulo'] ?></td>
			<td><?= strip_tags($row['msg']) ?></td>
			<td><?= date("d/m/Y", strtotime($row['data'])) ?></td>
		</tr>
		<?php endforeach; ?>
	</tbody>
</table>
