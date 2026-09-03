<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'saidas';
$id_usuario = $_SESSION['id'];

$id_produto        = $_POST['id'];
$estoque           = $_POST['estoque'];
$quantidade_saida  = (int)($_POST['quantidade_saida'] ?? 0);
$motivo_saida      = trim($_POST['motivo_saida'] ?? '');
$id_funcionario    = isset($_POST['funcionario_saida']) && $_POST['funcionario_saida'] !== '' ? (int)$_POST['funcionario_saida'] : null;

if ($quantidade_saida <= 0) {
  echo 'Informe uma quantidade válida';
  exit;
}

$novo_estoque = (int)$estoque - $quantidade_saida;

// ===== LÓGICA ORIGINAL (mantida) =====
$query = $pdo->prepare("INSERT INTO $tabela SET produto = '$id_produto', quantidade = '$quantidade_saida', motivo = :motivo, usuario = '$id_usuario', data = curDate()");
$query->bindValue(":motivo", $motivo_saida);
$query->execute();

// atualizar o total no estoque do produto
$pdo->query("UPDATE produtos SET estoque = '$novo_estoque' WHERE id = '$id_produto'");
// ===== FIM LÓGICA ORIGINAL =====


// ===== NOVO: registrar comissão (opcional, sem quebrar nada)
if ($id_funcionario) {
  try {
    // 1) carrega produto para preço de venda/compra
    $p = $pdo->query("SELECT nome, valor_venda, valor_compra FROM produtos WHERE id = ".(int)$id_produto." LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    $valorVenda  = (float)($p['valor_venda']  ?? 0);
    $valorCompra = (float)($p['valor_compra'] ?? 0);
    $nomeProd    = (string)($p['nome'] ?? 'Produto');

    // 2) critério de comissão:
    //    - se existir campo 'comissao' na tabela usuarios, usa como % (ex.: 10 = 10%)
    //    - senão, usa margem (venda - compra)
    $perc = null;
    try {
      $u = $pdo->query("SHOW COLUMNS FROM usuarios LIKE 'comissao'")->fetch(PDO::FETCH_ASSOC);
      if ($u) {
        $percRow = $pdo->query("SELECT comissao FROM usuarios WHERE id = ".(int)$id_funcionario." LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($percRow && $percRow['comissao'] !== null && $percRow['comissao'] !== '') {
          $perc = (float)$percRow['comissao'];
        }
      }
    } catch(Throwable $e) {}

    if ($perc !== null) {
      // comissão percentual sobre o valor de venda total
      $valorComissao = max(0, ($valorVenda * $quantidade_saida) * ($perc/100));
    } else {
      // fallback: usa margem (não negativa)
      $margem = ($valorVenda - $valorCompra) * $quantidade_saida;
      $valorComissao = max(0, $margem);
    }

    // 3) grava em tabela própria de comissões, se existir; se não, grava em 'pagar'
    $temComissoes = $pdo->query("SHOW TABLES LIKE 'comissoes'")->fetch(PDO::FETCH_NUM);

    if ($temComissoes) {
      $stmtC = $pdo->prepare("
        INSERT INTO comissoes 
          (id_funcionario, id_produto, quantidade, valor, descricao, data_lanc, origem)
        VALUES 
          (:f, :p, :q, :v, :d, :data_lanc, 'saida_produto')
      ");
      $stmtC->execute([
        ':f' => $id_funcionario,
        ':p' => $id_produto,
        ':q' => $quantidade_saida,
        ':v' => $valorComissao,
        ':d' => 'Comissão - '.$nomeProd,
        ':data_lanc' => date('Y-m-d H:i:s')
      ]);
    } else {
      // fallback: lança como conta a pagar (não altera sua rotina de pagamentos/cron)
      // campos mínimos usuais; adapte só se sua estrutura exigir
      $desc = 'Comissão - '.$nomeProd;
      $valorSql = number_format($valorComissao, 2, '.', '');
      $stmtPagar = $pdo->prepare("
        INSERT INTO pagar 
          (descricao, valor, data_lanc, data_venc, pago, alerta, usuario_lanc)
        VALUES 
          (:descricao, :valor, :data_lanc, :data_venc, 'Não', NULL, :usuario_lanc)
      ");
      $stmtPagar->execute([
        ':descricao' => $desc,
        ':valor' => $valorSql,
        ':data_lanc' => date('Y-m-d'),
        ':data_venc' => date('Y-m-d'),
        ':usuario_lanc' => $id_usuario
      ]);
    }

  } catch(Throwable $e) {
    // silencia falhas de comissão para não quebrar fluxo
  }
}
// ===== FIM NOVO =====

echo 'Salvo com Sucesso';
