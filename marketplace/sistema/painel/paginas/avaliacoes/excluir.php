<?php
require_once __DIR__ . '/config.php';
// IMPORTANTE: Não incluir header ou verificar.php aqui se eles gerarem HTML
// Apenas retornar texto para o AJAX

// tabela fixa (mantida)
$tabela = 'avaliacoes_site';

// id via POST
$id = $_POST['id'] ?? '';

if ($id === '' || $id === null) {
  echo 'ID não informado!';
  exit();
}

// força inteiro
$id = (int)$id;

if ($id <= 0) {
  echo 'ID inválido!';
  exit();
}

try {

  /*
    =========================================================
    NOVO: Bloqueia exclusão do registro interno (is_config = 1)
    - Não quebra se a coluna is_config não existir (try/catch)
    =========================================================
  */
  try {
    $stCheck = $pdo->prepare("SELECT is_config FROM $tabela WHERE id = :id LIMIT 1");
    $stCheck->bindValue(':id', $id, PDO::PARAM_INT);
    $stCheck->execute();
    $row = $stCheck->fetch(PDO::FETCH_ASSOC);

    if (!$row) {
      echo 'Registro não encontrado!';
      exit();
    }

    if (isset($row['is_config']) && (int)$row['is_config'] === 1) {
      echo 'Registro interno do sistema';
      exit();
    }
  } catch (Throwable $e) {
    // Se não existir a coluna is_config, segue a lógica normal (deletar)
  }

  // DELETE
  $query = $pdo->prepare("DELETE FROM $tabela WHERE id = :id LIMIT 1");
  $query->bindValue(":id", $id, PDO::PARAM_INT);
  $query->execute();

  echo 'Excluído com Sucesso';

} catch (Throwable $e) {
  // Não expor erro do banco no retorno do AJAX
  error_log("avaliacoes/excluir.php erro: " . $e->getMessage());
  echo 'Erro ao excluir';
}
?>