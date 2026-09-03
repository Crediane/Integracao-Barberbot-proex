<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';

$grupo = filter_input(INPUT_POST, 'grupo', FILTER_VALIDATE_INT);
$grupo = $grupo ? (int)$grupo : 0;

echo '<select class="form-control sel2" id="item" name="item" style="width:100%;" onchange="alterarValor()">';
if ($grupo > 0) {
	$stmt = $pdo->prepare("SELECT id, nome FROM itens_assinaturas WHERE grupo = :grupo ORDER BY id ASC");
	$stmt->bindValue(':grupo', $grupo, PDO::PARAM_INT);
	$stmt->execute();
	$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
	$total_reg = @count($res);

	if ($total_reg > 0) {
		for ($i = 0; $i < $total_reg; $i++) {
			echo '<option value="' . (int)$res[$i]['id'] . '">' . htmlspecialchars($res[$i]['nome'], ENT_QUOTES, 'UTF-8') . '</option>';
		}
	}
}
echo '</select>';
?>
