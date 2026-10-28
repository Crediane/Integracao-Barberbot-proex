<?php
require_once __DIR__ . '/../_context_bootstrap.php';
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);
require_once __DIR__ . '/../../../conexao.php';

$res = $pdo->query("SELECT * FROM foto_admin ORDER BY id DESC");
$dados = $res->fetchAll(PDO::FETCH_ASSOC);
?>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<div class="container py-5">
  <h4 class="text-center mb-4">Veja algumas fotos dos nossos atendimentos</h4>
  <div class="row justify-content-center">
    <?php foreach($dados as $foto): ?>
      <div class="col-md-4 mb-4 text-center">
        <div class="card shadow-sm">
          <img src="img/foto_admin/<?= $foto['imagem'] ?>" class="card-img-top" style="height: 300px; object-fit: cover;">
          <div class="card-body">
            <h6 class="card-title"><?= htmlspecialchars($foto['titulo']) ?></h6>
          </div>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
