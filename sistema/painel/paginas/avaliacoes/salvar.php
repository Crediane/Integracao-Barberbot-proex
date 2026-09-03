<?php
require_once __DIR__ . '/config.php';
// Campos vindos do formulário
$id             = $_POST['id']              ?? '';
$nome           = trim($_POST['nome']       ?? '');
$nota           = $_POST['nota']            ?? '';
$comentario     = trim($_POST['comentario'] ?? '');
$origem         = trim($_POST['origem']     ?? 'Site');
$data_avaliacao = $_POST['data_avaliacao']  ?? null; // input type=date -> YYYY-mm-dd
$foto           = trim($_POST['foto']       ?? '');
$status         = $_POST['status']          ?? 1;

// --------- Validações ---------
if ($nome === '') {
  echo 'Informe o Nome do Cliente';
  exit;
}

// nota 1..5
$nota = (int)$nota;
if ($nota < 1 || $nota > 5) {
  echo 'Informe uma Nota entre 1 e 5';
  exit;
}

// origem permitida
$origensPermitidas = ['Site','Google','Instagram','Facebook','Outro'];
if (!in_array($origem, $origensPermitidas, true)) {
  $origem = 'Site';
}

// status 0/1
$status = ((int)$status === 1) ? 1 : 0;

// data opcional, deve ser YYYY-mm-dd
if ($data_avaliacao !== null && $data_avaliacao !== '') {
  $d = DateTime::createFromFormat('Y-m-d', $data_avaliacao);
  $valid = $d && $d->format('Y-m-d') === $data_avaliacao;
  if (!$valid) {
    echo 'Data inválida';
    exit;
  }
} else {
  $data_avaliacao = null;
}

// URL da foto (opcional)
if ($foto !== '') {
  if (!filter_var($foto, FILTER_VALIDATE_URL)) {
    echo 'URL da foto inválida';
    exit;
  }
  if (!preg_match('~^https?://~i', $foto)) {
    echo 'URL da foto deve começar com http(s)://';
    exit;
  }
} else {
  $foto = null;
}

// --------- Persistência ---------
try {

  // Segurança: id sempre inteiro (se vier)
  $idInt = 0;
  if ($id !== '') {
    $idInt = (int)$id;
    if ($idInt <= 0) {
      echo 'ID inválido';
      exit;
    }

    // Se existir registro interno (is_config=1), impede editar por aqui (caso você use)
    // Não quebra se a coluna ainda não existir.
    try {
      $stCheck = $pdo->prepare("SELECT is_config FROM avaliacoes_site WHERE id = :id LIMIT 1");
      $stCheck->bindValue(':id', $idInt, PDO::PARAM_INT);
      $stCheck->execute();
      $rowCheck = $stCheck->fetch(PDO::FETCH_ASSOC);

      if (!$rowCheck) {
        echo 'Registro não encontrado';
        exit;
      }

      if (isset($rowCheck['is_config']) && (int)$rowCheck['is_config'] === 1) {
        echo 'Registro interno do sistema';
        exit;
      }
    } catch (Throwable $e) {
      // Se não tiver coluna is_config, segue normal (não altera lógica)
    }
  }

  if ($idInt > 0) {
    // UPDATE
    $sql = "UPDATE avaliacoes_site SET
              nome = :nome,
              nota = :nota,
              comentario = :comentario,
              origem = :origem,
              data_avaliacao = :data_avaliacao,
              foto = :foto,
              status = :status
            WHERE id = :id";
    $st = $pdo->prepare($sql);
    $st->bindValue(':id', $idInt, PDO::PARAM_INT);
  } else {
    // INSERT
    $sql = "INSERT INTO avaliacoes_site
              (nome, nota, comentario, origem, data_avaliacao, foto, status)
            VALUES
              (:nome, :nota, :comentario, :origem, :data_avaliacao, :foto, :status)";
    $st = $pdo->prepare($sql);
  }

  $st->bindValue(':nome', $nome);
  $st->bindValue(':nota', $nota, PDO::PARAM_INT);

  if ($comentario !== '') {
    $st->bindValue(':comentario', $comentario, PDO::PARAM_STR);
  } else {
    $st->bindValue(':comentario', null, PDO::PARAM_NULL);
  }

  $st->bindValue(':origem', $origem);

  if ($data_avaliacao !== null) {
    $st->bindValue(':data_avaliacao', $data_avaliacao, PDO::PARAM_STR);
  } else {
    $st->bindValue(':data_avaliacao', null, PDO::PARAM_NULL);
  }

  if ($foto !== null) {
    $st->bindValue(':foto', $foto, PDO::PARAM_STR);
  } else {
    $st->bindValue(':foto', null, PDO::PARAM_NULL);
  }

  $st->bindValue(':status', $status, PDO::PARAM_INT);

  $st->execute();

  echo 'Salvo com Sucesso';

} catch (Throwable $e) {
  // Segurança: não expor erro do banco para o usuário final
  error_log("avaliacoes/salvar.php erro: " . $e->getMessage());
  echo 'Erro ao salvar';
}