<?php
require_once __DIR__ . '/../_context_bootstrap.php';
/**
 * BarberBot - calcular.php (Serviços Agenda)
 * Motivo: o servicos_agenda.php faz um POST AJAX para este arquivo para calcular
 * o total (valor do produto * quantidade) ao selecionar um produto.
 *
 * Saída: número simples (texto), para manter o padrão do sistema.
 */
require_once __DIR__ . '/../../../conexao.php';

// Sanitização básica
$quantidade = isset($_POST['quant']) ? (float)str_replace(',', '.', (string)$_POST['quant']) : 0.0;
$produto_id = isset($_POST['produto']) ? (int)$_POST['produto'] : 0;

if ($quantidade < 0) $quantidade = 0.0;
if ($produto_id < 0) $produto_id = 0;

// Busca do valor do produto (mantém lógica: não achou => 0)
$valor_unit = 0.0;

try {
  $stmt = $pdo->prepare("SELECT valor_venda FROM produtos WHERE id = :id LIMIT 1");
  $stmt->bindValue(':id', $produto_id, PDO::PARAM_INT);
  $stmt->execute();
  $row = $stmt->fetch(PDO::FETCH_ASSOC);

  if ($row && isset($row['valor_venda'])) {
    $valor_unit = (float)$row['valor_venda'];
  }
} catch (Exception $e) {
  // não expõe erro em produção; mantém retorno 0
  $valor_unit = 0.0;
}

$total = $valor_unit * $quantidade;

// mantém saída numérica simples (como o vendas/calcular.php)
echo $total;
