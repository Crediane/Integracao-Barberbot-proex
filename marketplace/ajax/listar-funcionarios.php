<?php
$bb_ajax_context_mode = 'tenant';
$bb_ajax_context_response = 'plain';
require_once __DIR__ . '/_context_bootstrap.php';

// =========================
// Entrada
// =========================
$serv = filter_input(INPUT_POST, 'serv', FILTER_VALIDATE_INT);
$idAg = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$idAg = $idAg ? (int)$idAg : 0;

if (!$serv) {
  // mantém padrão: retorna pelo menos o placeholder, se existir
  if (isset($texto_agendamento)) {
    echo '<option value="">' . $texto_agendamento . '</option>';
  }
  exit();
}

// Busca todos os funcionários ativos que atendem
$query = $pdo->query("SELECT id, nome, foto FROM usuarios WHERE ativo = 'Sim' AND atendimento = 'Sim'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);

$funcionarioSelecionado = 0;
$servicoAg = '';

// Busca o agendamento, se foi passado um id (mesma regra)
if ($idAg > 0) {
  $stmtAg = $pdo->prepare("SELECT servico, funcionario FROM agendamentos WHERE id = :id LIMIT 1");
  $stmtAg->bindValue(':id', $idAg, PDO::PARAM_INT);
  $stmtAg->execute();
  $ag = $stmtAg->fetch(PDO::FETCH_ASSOC);

  if (is_array($ag) && isset($ag['servico'])) {
    $servicoAg = (string)$ag['servico'];
  }

  if ($servicoAg !== '' && (int)$servicoAg === (int)$serv) {
    $funcionarioSelecionado = isset($ag['funcionario']) ? (int)$ag['funcionario'] : 0;

    if ($funcionarioSelecionado > 0) {
      $stmtNome = $pdo->prepare("SELECT nome FROM usuarios WHERE id = :id LIMIT 1");
      $stmtNome->bindValue(':id', $funcionarioSelecionado, PDO::PARAM_INT);
      $stmtNome->execute();
      $nomeSel = $stmtNome->fetchColumn();

      echo '<option value="">' . ($texto_agendamento ?? '') . '</option>';
      echo '<option value="' . $funcionarioSelecionado . '" selected>' . ($nomeSel ?? '') . '</option>';
    } else {
      echo '<option value="">' . ($texto_agendamento ?? '') . '</option>';
    }
  } else {
    echo '<option value="">' . ($texto_agendamento ?? '') . '</option>';
  }
} else {
  echo '<option value="">' . ($texto_agendamento ?? '') . '</option>';
}

// Lista funcionários que atendem o serviço
if (count($res) > 0) {
  $stmtSF = $pdo->prepare("SELECT 1 FROM servicos_func WHERE servico = :serv AND funcionario = :func AND funcionario != :func_sel LIMIT 1");

  foreach ($res as $row) {
    $nome_func = $row['nome'] ?? '';
    $func      = isset($row['id']) ? (int)$row['id'] : 0;
    $foto      = $row['foto'] ?? '';

    if ($func <= 0) continue;

    $stmtSF->bindValue(':serv', $serv, PDO::PARAM_INT);
    $stmtSF->bindValue(':func', $func, PDO::PARAM_INT);
    $stmtSF->bindValue(':func_sel', $funcionarioSelecionado, PDO::PARAM_INT);
    $stmtSF->execute();

    if ($stmtSF->fetchColumn()) {
      echo '<option value="' . $func . '" data-foto="' . htmlspecialchars($foto, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($nome_func, ENT_QUOTES, 'UTF-8') . '</option>';
    }
  }
}
?>

<script>
	$(document).ready(function() {
    $('select').select2({
        templateResult: formatarOpcao,
        templateSelection: formatarOpcao
    });
});

function formatarOpcao(opcao) {
    if (!opcao.id) {
        return opcao.text;
    }

    var imagem = $(opcao.element).data('imagem');
    if (!imagem) {
        return opcao.text;
    }

    var $opcao = $(
        '<span><img src="sistema/painel/img/perfil/' + imagem + '" width="20px" height="20px" style="margin-right: 5px;" /> ' +
        opcao.text + '</span>'
    );

    return $opcao;
}
</script>
