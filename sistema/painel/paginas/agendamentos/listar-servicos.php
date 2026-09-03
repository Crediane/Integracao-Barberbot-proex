<?php
require_once __DIR__ . '/../_context_bootstrap.php';
// ============================================================================
// Arquivo: /public_html/sistema/painel/paginas/agendamentos/listar-servicos.php (AJAX)
// PADRÃO "BLINDADO MARKETPLACE" (SEM MUDAR A LÓGICA):
// - Detecta SLUG (POST/GET/URI/REFERER) ANTES do conexao.php
// - Passa $_GET['loja'] para o bootstrap escolher o TENANT correto
// ============================================================================
@chdir(__DIR__);

if (!function_exists('bb_clean_slug')) {
  function bb_clean_slug($v) {
    $v = strtolower(trim((string)$v));
    return preg_replace('/[^a-z0-9_-]/', '', $v);
  }
}

$__bb_reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$__bb_refPath = '';
$__bb_slug = '';
if (!empty($_POST['loja'])) {
  $__bb_slug = bb_clean_slug($_POST['loja']);
} elseif (!empty($_GET['loja'])) {
  $__bb_slug = bb_clean_slug($_GET['loja']);
} elseif (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath, $m1)) {
  $__bb_slug = bb_clean_slug($m1[1]);
} elseif ($__bb_refPath && preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_refPath, $m2)) {
  $__bb_slug = bb_clean_slug($m2[1]);
}

if ($__bb_slug !== '') {
  $_GET['loja'] = $__bb_slug;
}

require_once __DIR__ . '/../../../conexao.php';

// Libera lock de sessão para não travar o painel (AJAX)
if (session_status() === PHP_SESSION_ACTIVE) {
  @session_write_close();
}

// (opcional, mas recomendado em ajax)
header('Content-Type: text/html; charset=UTF-8');

// pega o funcionário (caso venha vazio, fica string vazia mesmo)
$func = isset($_POST['func']) ? trim((string)$_POST['func']) : '';

// se você sabe que funcionario é ID numérico, isso ajuda a evitar lixo
// (não muda a lógica: se vier inválido, vai cair em "Nenhum Serviço")
$funcId = (int)$func;

// 1) busca os serviços vinculados ao funcionário
$stmt = $pdo->prepare("SELECT servico FROM servicos_func WHERE funcionario = :func");
$stmt->execute([':func' => $funcId]);
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);

// prepara a query do serviço ativo (reutiliza no loop)
$stmt2 = $pdo->prepare("SELECT nome FROM servicos WHERE id = :id AND ativo = 'Sim' LIMIT 1");

if (count($res) > 0) {
    for ($i = 0; $i < count($res); $i++) {

        $serv = $res[$i]['servico'];
        $servId = (int)$serv;

        // 2) filtra só serviços ativos
        $stmt2->execute([':id' => $servId]);
        $nome_serv = $stmt2->fetchColumn();

        // se não tiver serviço ativo com esse id, pula
        if ($nome_serv === false || $nome_serv === null || $nome_serv === '') {
            continue;
        }

        // evita quebrar HTML se tiver aspas/caracteres especiais
        $nome_serv_safe = htmlspecialchars((string)$nome_serv, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        echo '<option value="' . $servId . '">' . $nome_serv_safe . '</option>';
    }
} else {
    echo '<option value="">Nenhum Serviço</option>';
}
?>
