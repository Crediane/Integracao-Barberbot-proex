<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';

/**
 * ATENÇÃO: deixe estes 3 ini_set ativos até resolver o problema.
 * Depois remova ou desative em produção.
 */
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

header('Content-Type: text/html; charset=utf-8');

try {
    // Verifique se a conexão existe
    if (!isset($pdo) || !$pdo instanceof PDO) {
        throw new RuntimeException('Conexão PDO ($pdo) não inicializada em ../../conexao.php');
    }

    // Checa se a tabela existe (diagnóstico de ambientes novos)
    $tbl = $pdo->query("SHOW TABLES LIKE 'faq'")->fetchColumn();
    if (!$tbl) {
        throw new RuntimeException("Tabela 'faq' não encontrada. Execute o script SQL de criação.");
    }

    $sql = "SELECT id, pergunta, resposta, data_cad FROM faq ORDER BY id DESC";
    $stmt = $pdo->query($sql);
    $dados = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch (Throwable $e) {
    http_response_code(500);
    echo '<div class="alert alert-danger" style="white-space:pre-wrap">';
    echo "Erro ao carregar a lista:\n" . htmlspecialchars($e->getMessage());
    echo "</div>";
    exit;
}

if (!$dados) {
    echo '<small><i>Nenhum registro encontrado.</i></small>';
    exit;
}
?>
<div class="table-responsive">
  <table class="table table-hover" id="tabela">
    <thead>
      <tr>
        <th>Pergunta</th>
        <th>Resumo da Resposta</th>
        <th class="d-none d-sm-table-cell">Data</th>
        <th class="text-right">Ações</th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($dados as $row):
        $id = (int)$row['id'];
        $pergunta = (string)$row['pergunta'];
        $resposta = (string)$row['resposta'];
        $data = !empty($row['data_cad']) ? date('d/m/Y H:i', strtotime($row['data_cad'])) : '-';
        $resumo = trim(preg_replace('/\s+/', ' ', $resposta));
        if (mb_strlen($resumo) > 100) $resumo = mb_substr($resumo, 0, 100) . '...';
    ?>
      <tr>
        <td><?= htmlspecialchars($pergunta) ?></td>
        <td class="text-muted"><?= htmlspecialchars($resumo) ?></td>
        <td class="d-none d-sm-table-cell"><?= $data ?></td>
        <td class="text-right" style="white-space:nowrap;">
          <a href="#" title="Editar"
             onclick='editar(<?= $id ?>, <?= json_encode($pergunta, JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($resposta, JSON_UNESCAPED_UNICODE) ?>)'>
            <i class="fa fa-edit text-primary"></i>
          </a>
          <a href="#" class="ml-2" title="Excluir" onclick="excluir(<?= $id ?>)">
            <i class="fa fa-trash text-danger"></i>
          </a>
          <a href="#" class="ml-2" title="Detalhes"
             onclick='mostrar(<?= json_encode($pergunta, JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($resposta, JSON_UNESCAPED_UNICODE) ?>, <?= json_encode($data, JSON_UNESCAPED_UNICODE) ?>)'>
            <i class="fa fa-info-circle text-secondary"></i>
          </a>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</div>
