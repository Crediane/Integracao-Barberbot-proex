<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';

$prod = @$_POST['produt'];


$query = $pdo->query("SELECT * from produtos where id = '$prod' order by id asc");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$linhas = @count($res);

$valor = $res[0]['valor_venda'];


echo $valor;								

?>

<input type="text" name="vava" value="<?php echo $valor ?>>



