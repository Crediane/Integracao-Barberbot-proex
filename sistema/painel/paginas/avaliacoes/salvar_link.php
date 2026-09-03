<?php
require_once __DIR__ . '/config.php';
// Retorna apenas texto para o AJAX (sem HTML)
$tabela = 'avaliacoes_site';

$link = isset($_POST['link']) ? trim($_POST['link']) : '';

// validações (permite vazio para "limpar")
if ($link !== '') {

  if (strlen($link) > 255) {
    echo "Link muito grande (máx. 255 caracteres).";
    exit();
  }

  if (!filter_var($link, FILTER_VALIDATE_URL)) {
    echo "Informe uma URL válida.";
    exit();
  }

  if (!preg_match('~^https?://~i', $link)) {
    echo "A URL deve começar com http:// ou https://";
    exit();
  }
}

try {

  // garante que as colunas existem (se não existir, cai no catch)
  $pdo->query("SELECT is_config, link_avaliacao_google FROM {$tabela} LIMIT 1");

  // procura registro interno
  $idConfig = (int)$pdo->query("SELECT id FROM {$tabela} WHERE is_config = 1 ORDER BY id DESC LIMIT 1")->fetchColumn();

  if ($idConfig > 0) {

    $st = $pdo->prepare("UPDATE {$tabela} SET link_avaliacao_google = :link WHERE id = :id LIMIT 1");

    if ($link !== '') {
      $st->bindValue(':link', $link, PDO::PARAM_STR);
    } else {
      $st->bindValue(':link', null, PDO::PARAM_NULL);
    }

    $st->bindValue(':id', $idConfig, PDO::PARAM_INT);
    $st->execute();

    echo "Salvo com Sucesso";
    exit();
  }

  // cria registro interno
  $nome = 'CONFIG_LINK_AVALIACAO';
  $nota = 5;
  $comentario = 'Registro interno do sistema (não excluir).';
  $origem = 'Site';
  $data_avaliacao = date('Y-m-d');
  $foto = null;
  $status = 0;
  $externo_id = null;

  $sql = "INSERT INTO {$tabela}
            (nome, nota, comentario, origem, data_avaliacao, foto, status, externo_id, is_config, link_avaliacao_google)
          VALUES
            (:nome, :nota, :comentario, :origem, :data_avaliacao, :foto, :status, :externo_id, 1, :link)";

  $st = $pdo->prepare($sql);
  $st->bindValue(':nome', $nome);
  $st->bindValue(':nota', $nota, PDO::PARAM_INT);
  $st->bindValue(':comentario', $comentario, PDO::PARAM_STR);
  $st->bindValue(':origem', $origem, PDO::PARAM_STR);
  $st->bindValue(':data_avaliacao', $data_avaliacao, PDO::PARAM_STR);
  $st->bindValue(':foto', $foto, PDO::PARAM_NULL);
  $st->bindValue(':status', $status, PDO::PARAM_INT);
  $st->bindValue(':externo_id', $externo_id, PDO::PARAM_NULL);

  if ($link !== '') {
    $st->bindValue(':link', $link, PDO::PARAM_STR);
  } else {
    $st->bindValue(':link', null, PDO::PARAM_NULL);
  }

  $st->execute();

  echo "Salvo com Sucesso";
  exit();

} catch (Throwable $e) {
  error_log("avaliacoes/salvar_link.php erro: " . $e->getMessage());
  echo "Erro ao salvar.";
  exit();
}