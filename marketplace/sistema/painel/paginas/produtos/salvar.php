<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'produtos';

// ==== helper: checar se a coluna existe (não muda sua lógica principal) ====
function hasColumn(PDO $pdo, string $table, string $column): bool {
    try {
        $st = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :c");
        $st->execute([':c' => $column]);
        return (bool)$st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return false;
    }
}

// ================== CAMPOS ORIGINAIS ==================
$valor = $_POST['valor'] ?? null; // não é usado no SQL, mantido como no original
if ($valor !== null) {
    $valor = str_replace('.', '', $valor);
    $valor = str_replace(',', '.', $valor);
}

$id = $_POST['id'] ?? '';
$nome = $_POST['nome'] ?? '';

$valor_compra = $_POST['valor_compra'] ?? '0';
$valor_compra = str_replace('.', '', $valor_compra);
$valor_compra = str_replace(',', '.', $valor_compra);

$valor_venda = $_POST['valor_venda'] ?? '0';
$valor_venda = str_replace('.', '', $valor_venda);
$valor_venda = str_replace(',', '.', $valor_venda);

$descricao = $_POST['descricao'] ?? '';
$nivel_estoque = $_POST['nivel_estoque'] ?? '';

$categoria = $_POST['categoria'] ?? 0;

if($categoria == 0){
	echo 'Cadastre uma Categoria de Produtos para o Produto';
	exit();
}

// ================== SUPORTE OPCIONAL À COMISSÃO (sem alterar a lógica) ==================
$tem_col_comissao_tipo  = hasColumn($pdo, $tabela, 'comissao_tipo');
$tem_col_comissao_valor = hasColumn($pdo, $tabela, 'comissao_valor');

// Padrões seguros (caso venha do form)
$comissao_tipo_post  = $_POST['comissao_tipo']  ?? null; // 'percent' ou 'fixo'
$comissao_valor_post = $_POST['comissao_valor'] ?? null; // decimal brasileiro

// Normaliza o valor se veio do POST
if ($comissao_valor_post !== null && $comissao_valor_post !== '') {
    $cv = str_replace('.', '', $comissao_valor_post);
    $cv = str_replace(',', '.', $cv);
    $comissao_valor_post = is_numeric($cv) ? $cv : '0';
}

// Sanitiza tipo se informado
if ($comissao_tipo_post !== null) {
    $tipo = strtolower(trim($comissao_tipo_post));
    if ($tipo !== 'percent' && $tipo !== 'fixo') {
        $comissao_tipo_post = 'percent';
    } else {
        $comissao_tipo_post = $tipo;
    }
}

// ================== VALIDAR NOME (original) ==================
$query = $pdo->query("SELECT * from $tabela where nome = '$nome'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
if(@count($res) > 0 and $id != $res[0]['id']){
	echo 'Nome já Cadastrado, escolha outro!!';
	exit();
}

// ================== BUSCAR FOTO ATUAL (original) ==================
$query = $pdo->query("SELECT * FROM $tabela where id = '$id'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);
if($total_reg > 0){
	$foto = $res[0]['foto'];
} else {
	$foto = 'sem-foto.jpg';
}

// ================== UPLOAD DE FOTO (original + WEBP) ==================
$nome_img = date('d-m-Y H:i:s') .'-'. @$_FILES['foto']['name'];
$nome_img = preg_replace('/[ :]+/' , '-' , $nome_img);
$caminho = '../../img/produtos/' . $nome_img;
$imagem_temp = @$_FILES['foto']['tmp_name']; 

if(@$_FILES['foto']['name'] != ""){
	$ext = strtolower(pathinfo($nome_img, PATHINFO_EXTENSION));   
	if($ext == 'png' or $ext == 'jpg' or $ext == 'jpeg' or $ext == 'gif' or $ext == 'webp'){ 
			// EXCLUO A FOTO ANTERIOR
			if($foto != "sem-foto.jpg"){
				@unlink('../../img/produtos/' . $foto);
			}
			$foto = $nome_img;
			move_uploaded_file($imagem_temp, $caminho);
	}else{
		echo 'Extensão de Imagem não permitida!';
		exit();
	}
}

// ================== MONTAGEM DO SQL (mantendo sua lógica) ==================
// base SET (sempre igual ao original)
$set_campos = "nome = :nome, categoria = '$categoria', valor_compra = :valor_compra, valor_venda = :valor_venda, descricao = :descricao, foto = '$foto', nivel_estoque = '$nivel_estoque'";

// Acrescenta comissão SOMENTE se as colunas existirem
$params = [
    ':nome'         => $nome,
    ':valor_venda'  => $valor_venda,
    ':valor_compra' => $valor_compra,
    ':descricao'    => $descricao
];

// Se as colunas existem, definimos os valores que irão para o SQL
if ($tem_col_comissao_tipo) {
    // Se veio no POST, usa; senão, se for edição, mantém o atual
    if ($comissao_tipo_post === null && $id != "") {
        try {
            $stCT = $pdo->query("SELECT comissao_tipo FROM $tabela WHERE id = '$id' LIMIT 1");
            $rowCT = $stCT->fetch(PDO::FETCH_ASSOC);
            $comissao_tipo_post = $rowCT ? ($rowCT['comissao_tipo'] ?? 'percent') : 'percent';
        } catch(Throwable $e) {
            $comissao_tipo_post = 'percent';
        }
    }
    if ($comissao_tipo_post === null) { 
        $comissao_tipo_post = 'percent'; 
    }

    $set_campos .= ", comissao_tipo = :comissao_tipo";
    $params[':comissao_tipo'] = $comissao_tipo_post;
}

if ($tem_col_comissao_valor) {
    // Se veio no POST, usa; senão, se for edição, mantém o atual
    if ($comissao_valor_post === null && $id != "") {
        try {
            $stCV = $pdo->query("SELECT comissao_valor FROM $tabela WHERE id = '$id' LIMIT 1");
            $rowCV = $stCV->fetch(PDO::FETCH_ASSOC);
            $comissao_valor_post = $rowCV ? ($rowCV['comissao_valor'] ?? '0') : '0';
        } catch(Throwable $e) {
            $comissao_valor_post = '0';
        }
    }
    if ($comissao_valor_post === null || $comissao_valor_post === '') { 
        $comissao_valor_post = '0'; 
    }

    $set_campos .= ", comissao_valor = :comissao_valor";
    $params[':comissao_valor'] = $comissao_valor_post;
}

// ================== INSERT / UPDATE (exatamente como você faz) ==================
if($id == ""){
	$sql = "INSERT INTO $tabela SET $set_campos";
	$query = $pdo->prepare($sql);
} else {
	$sql = "UPDATE $tabela SET $set_campos WHERE id = '$id'";
	$query = $pdo->prepare($sql);
}

// binds originais + binds opcionais já estão em $params
foreach ($params as $k => $v) {
    $query->bindValue($k, $v);
}

$query->execute();

echo 'Salvo com Sucesso';
?>