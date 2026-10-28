<?php
require_once __DIR__ . '/../_context_bootstrap.php';
// ============================================================================
// BarberBot Software - Modulo Pagar - Salvar
// Arquivo: /public_html/sistema/painel/paginas/pagar/salvar.php
// Ajustes: compatibilidade com Admin do Sistema e Dono do Estabelecimento (bancos separados)
//          e correcao do erro PDO "number of bound variables does not match number of tokens".
// Regra de negocio preservada.
// ============================================================================
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'pagar';

// ----------------------------------------------------------------------------
// Identificacao do usuario logado (compatível com diferentes estruturas de sessao)
// ----------------------------------------------------------------------------
$id_usuario = 0;

if (isset($_SESSION['id']) && is_numeric($_SESSION['id'])) {
  $id_usuario = (int)$_SESSION['id'];
} elseif (isset($_SESSION['id_usuario']) && is_numeric($_SESSION['id_usuario'])) {
  $id_usuario = (int)$_SESSION['id_usuario'];
} elseif (isset($_SESSION['usuario_id']) && is_numeric($_SESSION['usuario_id'])) {
  $id_usuario = (int)$_SESSION['usuario_id'];
} elseif (isset($_SESSION['user_id']) && is_numeric($_SESSION['user_id'])) {
  $id_usuario = (int)$_SESSION['user_id'];
} elseif (isset($_SESSION['usuario']) && is_array($_SESSION['usuario']) && isset($_SESSION['usuario']['id']) && is_numeric($_SESSION['usuario']['id'])) {
  $id_usuario = (int)$_SESSION['usuario']['id'];
} elseif (isset($_SESSION['user']) && is_array($_SESSION['user']) && isset($_SESSION['user']['id']) && is_numeric($_SESSION['user']['id'])) {
  $id_usuario = (int)$_SESSION['user']['id'];
}

// ----------------------------------------------------------------------------
// POST (mantendo padrao do sistema e evitando warnings PHP 8+)
// ----------------------------------------------------------------------------
$id = isset($_POST['id']) ? trim((string)$_POST['id']) : '';
if ($id !== '' && is_numeric($id)) {
  $id = (string)((int)$id);
} else {
  $id = '';
}

$descricao   = isset($_POST['descricao']) ? trim((string)$_POST['descricao']) : '';
$valor       = isset($_POST['valor']) ? (string)$_POST['valor'] : '0';
$valor       = str_replace('.', '', $valor);
$valor       = str_replace(',', '.', $valor);

$pessoa      = isset($_POST['pessoa']) ? (int)$_POST['pessoa'] : 0;
$data_venc   = isset($_POST['data_venc']) ? (string)$_POST['data_venc'] : date('Y-m-d');
$data_pgto   = isset($_POST['data_pgto']) ? (string)$_POST['data_pgto'] : "";
$funcionario = isset($_POST['funcionario']) ? (int)$_POST['funcionario'] : 0;
$forma_pgto  = isset($_POST['pgto']) ? (string)$_POST['pgto'] : "";

// ----------------------------------------------------------------------------
// Helpers seguros (mantidos - apenas robustez)
// ----------------------------------------------------------------------------
function hasColumn(PDO $pdo, string $table, string $col): bool {
  try {
    $st = $pdo->prepare("SHOW COLUMNS FROM {$table} LIKE :c");
    $st->execute([':c' => $col]);
    return (bool)$st->fetch(PDO::FETCH_ASSOC);
  } catch (Throwable $e) {
    return false;
  }
}

function getNomeItem(PDO $pdo, ?int $prodId, ?int $servId): string {
  if ($prodId && $prodId > 0) {
    $q = $pdo->prepare("SELECT nome FROM produtos WHERE id = :id LIMIT 1");
    $q->execute([':id' => $prodId]);
    $r = $q->fetch(PDO::FETCH_ASSOC);
    if ($r && isset($r['nome'])) return (string)$r['nome'];
  }
  if ($servId && $servId > 0) {
    $q = $pdo->prepare("SELECT nome FROM servicos WHERE id = :id LIMIT 1");
    $q->execute([':id' => $servId]);
    $r = $q->fetch(PDO::FETCH_ASSOC);
    if ($r && isset($r['nome'])) return (string)$r['nome'];
  }
  return '';
}

// ----------------------------------------------------------------------------
// Ajuste opcional de descricao "Comissao - Nome" (mantido)
// ----------------------------------------------------------------------------
$produto_id = isset($_POST['produto']) ? (int)$_POST['produto'] : 0;
$servico_id = isset($_POST['servico']) ? (int)$_POST['servico'] : 0;

$produto_nome_post = isset($_POST['produto_nome']) ? trim((string)$_POST['produto_nome']) : '';
$servico_nome_post = isset($_POST['servico_nome']) ? trim((string)$_POST['servico_nome']) : '';

if (!$produto_id && !$servico_id && $id !== "") {
  try {
    $prodCol = hasColumn($pdo, $tabela, 'produto');
    $servCol = hasColumn($pdo, $tabela, 'servico');
    if ($prodCol || $servCol) {
      $selProd = $prodCol ? 'produto' : '0';
      $selServ = $servCol ? 'servico' : '0';
      $q = $pdo->prepare("SELECT {$selProd} AS produto, {$selServ} AS servico FROM {$tabela} WHERE id = :id LIMIT 1");
      $q->execute([':id' => (int)$id]);
      $r = $q->fetch(PDO::FETCH_ASSOC);
      if ($r) {
        if ($prodCol && !$produto_id && isset($r['produto'])) $produto_id = (int)$r['produto'];
        if ($servCol && !$servico_id && isset($r['servico'])) $servico_id = (int)$r['servico'];
      }
    }
  } catch (Throwable $e) {
    // Mantem padrao silencioso
  }
}

$nome_item = '';
if ($produto_nome_post !== '') $nome_item = $produto_nome_post;
else if ($servico_nome_post !== '') $nome_item = $servico_nome_post;
else $nome_item = getNomeItem($pdo, $produto_id, $servico_id);

if ($nome_item !== '') {
  $desc_trim = trim($descricao);
  if (preg_match('/^Comiss(ão|ao)\\s*-?\\s*$/i', $desc_trim)) {
    $descricao = 'Comissão - ' . $nome_item;
  }
}

if ($descricao === "") {
  echo 'Insira uma descrição!';
  exit();
}

// ----------------------------------------------------------------------------
// Logica de pagamento (mantida)
// ----------------------------------------------------------------------------
if ($data_pgto != '') {
  $usuario_pgto = $id_usuario;
  $pago = 'Sim';
  $sql_pgto = ", data_pgto = :data_pgto";
} else {
  $usuario_pgto = 0;
  $pago = 'Não';
  $sql_pgto = "";
}

// ----------------------------------------------------------------------------
// Recuperar foto atual (mantido)
// ----------------------------------------------------------------------------
$foto = 'sem-foto.jpg';
if ($id !== "") {
  $queryFoto = $pdo->prepare("SELECT foto FROM {$tabela} WHERE id = :id LIMIT 1");
  $queryFoto->execute([':id' => (int)$id]);
  $resFoto = $queryFoto->fetch(PDO::FETCH_ASSOC);
  if ($resFoto && isset($resFoto['foto']) && $resFoto['foto'] != '') {
    $foto = $resFoto['foto'];
  }
}

// ----------------------------------------------------------------------------
// Upload de Foto (mantido)
// ----------------------------------------------------------------------------
if (isset($_FILES['foto']) && isset($_FILES['foto']['name']) && $_FILES['foto']['name'] != "") {

  $nome_img = date('d-m-Y-H-i-s') . '-' . @$_FILES['foto']['name'];
  $nome_img = preg_replace('/[ :]+/', '-', $nome_img);

  $caminho = '../../img/contas/' . $nome_img;
  $imagem_temp = @$_FILES['foto']['tmp_name'];

  $ext = strtolower(pathinfo($nome_img, PATHINFO_EXTENSION));
  if (in_array($ext, ['png', 'jpg', 'jpeg', 'gif', 'pdf', 'rar', 'zip'])) {

    if ($foto != "sem-foto.jpg" && file_exists('../../img/contas/' . $foto)) {
      @unlink('../../img/contas/' . $foto);
    }

    $foto = $nome_img;
    @move_uploaded_file($imagem_temp, $caminho);

  } else {
    echo 'Extensão de Imagem não permitida!';
    exit();
  }
}

// ----------------------------------------------------------------------------
// Verificar caixa aberto do estabelecimento logado (mantido)
// ----------------------------------------------------------------------------
$query1 = $pdo->prepare("SELECT id FROM caixas WHERE operador = :op AND data_fechamento IS NULL ORDER BY id DESC LIMIT 1");
$query1->execute([':op' => $id_usuario]);
$res1 = $query1->fetch(PDO::FETCH_ASSOC);
$id_caixa = $res1 && isset($res1['id']) ? (int)$res1['id'] : 0;

// Mantido (hora_alerta usado no INSERT)
$hora_random = date('H:i:s');

// ----------------------------------------------------------------------------
// INSERT / UPDATE (mantido) + correcao dos binds (pago e hora_alerta)
// ----------------------------------------------------------------------------
if ($id === "") {

  $query = $pdo->prepare("INSERT INTO {$tabela} SET 
    descricao = :descricao, 
    tipo = 'Conta', 
    valor = :valor, 
    data_lanc = curDate(), 
    data_venc = :data_venc, 
    usuario_lanc = :id_usuario, 
    usuario_baixa = :usuario_pgto, 
    foto = :foto, 
    pessoa = :pessoa, 
    pago = :pago, 
    funcionario = :funcionario, 
    caixa = :caixa, 
    hora = curTime(), 
    pgto = :pgto, 
    hora_alerta = :hora_alerta 
    {$sql_pgto}");

  $query->bindValue(":id_usuario", $id_usuario);
  $query->bindValue(":usuario_pgto", $usuario_pgto);

} else {

  $query = $pdo->prepare("UPDATE {$tabela} SET 
    descricao = :descricao, 
    valor = :valor, 
    data_venc = :data_venc, 
    foto = :foto, 
    pessoa = :pessoa, 
    funcionario = :funcionario, 
    pgto = :pgto, 
    caixa = :caixa, 
    hora = curTime() 
    {$sql_pgto} 
    WHERE id = :id");

  $query->bindValue(":id", (int)$id);
}

// Binds comuns
$query->bindValue(":descricao", $descricao);
$query->bindValue(":valor", $valor);
$query->bindValue(":data_venc", $data_venc);
$query->bindValue(":foto", $foto);
$query->bindValue(":pessoa", $pessoa);
$query->bindValue(":funcionario", $funcionario);
$query->bindValue(":caixa", $id_caixa);
$query->bindValue(":pgto", $forma_pgto);

// Correcao: :pago e :hora_alerta existem apenas no INSERT
if ($id === "") {
  $query->bindValue(":pago", $pago);
  $query->bindValue(":hora_alerta", $hora_random);
}

// Bind do data_pgto so quando o token existe
if ($data_pgto != '') {
  $query->bindValue(":data_pgto", $data_pgto);
}

$query->execute();

echo 'Salvo com Sucesso';
?>