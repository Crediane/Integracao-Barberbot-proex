<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';

// Obter somente perguntas únicas (sem duplicatas)
$res = $pdo->query("SELECT DISTINCT pergunta, resposta FROM faq ORDER BY id DESC");
$faq = $res->fetchAll(PDO::FETCH_ASSOC);

$total = count($faq);
$metade = ceil($total / 2);
$coluna1 = array_slice($faq, 0, $metade);
$coluna2 = array_slice($faq, $metade);
?>

<link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/css/bootstrap.min.css">

<style>
.container {
  max-width: 1100px;
}
.panel-heading {
  cursor: pointer;
  background-color: #000;
  color: #fff;
  padding: 15px;
  font-size: 16px;
  font-weight: bold;
  display: flex;
  justify-content: space-between;
  align-items: center;
}
.panel-heading:hover {
  background-color: #f4a300;
  color: #000;
}
.toggle-icon {
  font-weight: bold;
  font-size: 1.2em;
  margin-left: auto;
}
.panel-body {
  background: #fff;
  color: #000;
  border-top: 1px solid #eee;
  max-height: 300px;
  overflow-y: auto;
}
</style>

<div class="container px-3 py-5">
  <h4 class="text-center mb-5">Encontre respostas para as dúvidas mais comuns dos nossos clientes</h4>
  <div class="row">

    <div class="col-md-6">
      <div class="panel-group" id="faqCol1">
        <?php foreach($coluna1 as $index => $item): ?>
        <div class="panel panel-default">
          <div class="panel-heading" data-toggle="collapse" data-parent="#faqCol1" href="#faq1_<?= $index ?>">
            <span><?= htmlspecialchars($item['pergunta']) ?></span>
            <span class="toggle-icon">+</span>
          </div>
          <div id="faq1_<?= $index ?>" class="panel-collapse collapse">
            <div class="panel-body">
              <?= nl2br(htmlspecialchars($item['resposta'])) ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="col-md-6">
      <div class="panel-group" id="faqCol2">
        <?php foreach($coluna2 as $index => $item): ?>
        <div class="panel panel-default">
          <div class="panel-heading" data-toggle="collapse" data-parent="#faqCol2" href="#faq2_<?= $index ?>">
            <span><?= htmlspecialchars($item['pergunta']) ?></span>
            <span class="toggle-icon">+</span>
          </div>
          <div id="faq2_<?= $index ?>" class="panel-collapse collapse">
            <div class="panel-body">
              <?= nl2br(htmlspecialchars($item['resposta'])) ?>
            </div>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

  </div>
</div>

<script src="https://ajax.googleapis.com/ajax/libs/jquery/1.12.4/jquery.min.js"></script>
<script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.7/js/bootstrap.min.js"></script>
<script>
$(document).ready(function() {
  $('.panel-collapse').on('show.bs.collapse', function() {
    $(this).parent().find('.toggle-icon').text('-');
  });

  $('.panel-collapse').on('hide.bs.collapse', function() {
    $(this).parent().find('.toggle-icon').text('+');
  });
});
</script>
