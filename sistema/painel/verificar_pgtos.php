<?php
/**
 * Arquivo: verificar_pgtos.php
 *
 * Correções aplicadas (SEM alterar a lógica de negócio):
 * 1) Inclusões (require) com caminho resiliente para evitar "Failed to open stream".
 * 2) Sessão isolada por contexto (MASTER x TENANT), seguindo o padrão do sistema multi-banco.
 *    - /sistema (sem /loja/ e sem ?loja=) => BB_MASTER
 *    - /loja/<slug>/... OU ?loja=...      => BB_TENANT
 *    - demais                              => BB_SITE
 *
 * Observação:
 * - A regra de verificação/baixa permanece exatamente a mesma:
 *   busca contas não pagas com ref_pix, consulta o status e baixa se aprovado.
 */

// ===== Sessão isolada por contexto (MASTER x TENANT) =====
$__bb_reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$__bb_refPath = '';
if (!empty($_SERVER['HTTP_REFERER'])) {
  $__bb_refPath = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: '';
}


// ===== Sessão TENANT por SLUG (evita misturar lojas no mesmo navegador) =====
if (!function_exists('bb_sess_clean_slug')) {
  function bb_sess_clean_slug($v) {
    $v = strtolower(trim((string)$v));
    return preg_replace('/[^a-z0-9_-]/', '', $v);
  }
}

// garante $__bb_refPath quando o arquivo não define
if (!isset($__bb_refPath)) {
  $__bb_refPath = '';
  if (!empty($_SERVER['HTTP_REFERER'])) {
    $__bb_refPath = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: '';
  }
}

$__bb_reqSlug = '';
if (!empty($_GET['loja'])) {
  $__bb_reqSlug = bb_sess_clean_slug($_GET['loja']);
} elseif (!empty($_POST['loja'])) {
  $__bb_reqSlug = bb_sess_clean_slug($_POST['loja']);
} else {
  if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath, $mSlug)) {
    $__bb_reqSlug = bb_sess_clean_slug($mSlug[1]);
  } elseif (!empty($__bb_refPath) && preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_refPath, $mSlug2)) {
    $__bb_reqSlug = bb_sess_clean_slug($mSlug2[1]);
  }
}

$__bb_tenantSessionName = 'BB_TENANT';
if ($__bb_reqSlug !== '') {
  $__bb_tenantSessionName = 'BB_TENANT_' . strtoupper(substr(sha1($__bb_reqSlug), 0, 12));
}

$__bb_lojaByPath = (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath)
                || (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_refPath);

$__bb_hasLoja = $__bb_lojaByPath || !empty($_GET['loja']) || !empty($_POST['loja']);
$__bb_isSistema = (strpos($__bb_reqPath, '/sistema') === 0) || (strpos($__bb_refPath, '/sistema') === 0);

// Força MASTER em /sistema quando NÃO é loja por PATH e NÃO tem ?loja=
$__bb_forceMaster = ($__bb_isSistema && !$__bb_lojaByPath && empty($_GET['loja']) && empty($_POST['loja']));

$__bb_desiredSession = $__bb_forceMaster ? 'BB_MASTER' : ($__bb_hasLoja ? $__bb_tenantSessionName : 'BB_SITE');

// Inicia/ajusta a sessão corretamente (usa o cookie do nome escolhido)
if (session_status() === PHP_SESSION_ACTIVE) {
  if (session_name() !== $__bb_desiredSession) {
    session_write_close();
    @session_name($__bb_desiredSession);
    if (!empty($_COOKIE[$__bb_desiredSession])) {
      @session_id((string)$_COOKIE[$__bb_desiredSession]);
    }
    @session_start();
  }
} else {
  @session_name($__bb_desiredSession);
  if (!empty($_COOKIE[$__bb_desiredSession])) {
    @session_id((string)$_COOKIE[$__bb_desiredSession]);
  }
  @session_start();
}
// ===== Helper: localizar arquivo com segurança (sem "chute" fixo) =====
if (!function_exists('bb_localizar_arquivo')) {
function bb_localizar_arquivo(array $candidatos) {
  foreach ($candidatos as $caminho) {
    if (is_string($caminho) && $caminho !== '' && file_exists($caminho)) {
      return $caminho;
    }
  }
  return '';
}
}

// ===== Helper: buscar arquivo em árvore (fallback controlado) =====
if (!function_exists('bb_buscar_arquivo_em_arvore')) {
  function bb_buscar_arquivo_em_arvore(string $baseDir, string $nomeArquivo, int $maxDepth = 5): string {
    $baseDir = rtrim($baseDir, '/');
    if ($baseDir === '' || !is_dir($baseDir)) {
      return '';
    }
    $stack = [[$baseDir, 0]];
    while ($stack) {
      [$dir, $depth] = array_pop($stack);
      $candidate = $dir . '/' . $nomeArquivo;
      if (is_file($candidate)) {
        return $candidate;
      }
      if ($depth >= $maxDepth) {
        continue;
      }
      $items = @scandir($dir);
      if (!$items) {
        continue;
      }
      foreach ($items as $it) {
        if ($it === '.' || $it === '..') continue;
        $p = $dir . '/' . $it;
        if (is_dir($p)) {
          $stack[] = [$p, $depth + 1];
        }
      }
    }
    return '';
  }
}


// ===== Conexão (mantém padrão do sistema) =====
$__bb_conexao = bb_localizar_arquivo([
  __DIR__ . '/../conexao.php',          // padrão mais comum (ex.: /painel/verificar_pgtos.php)
  __DIR__ . '/../../conexao.php',       // fallback (se este arquivo estiver em subpasta)
  __DIR__ . '/../../../conexao.php',    // fallback adicional
]);

if ($__bb_conexao === '') {
  http_response_code(500);
  echo 'Erro: arquivo de conexão não encontrado!';
  exit();
}

require_once $__bb_conexao;

$total_verificados = 0;
$total_aprovados = 0;

// ===== Localizar scripts de pagamento (consultar / baixar) =====
// Importante: aqui usamos require (não require_once) dentro do loop,
// pois a consulta precisa executar para cada $ref_pix.
$__bb_consultar_pgto = bb_localizar_arquivo([
  // Padrão do painel: /sistema/painel/paginas/pagamentos/...
  __DIR__ . '/paginas/pagamentos/consultar_pagamento.php',
  __DIR__ . '/paginas/receber/consultar_pagamento.php',
  __DIR__ . '/paginas/contas/consultar_pagamento.php',
  __DIR__ . '/paginas/financeiro/consultar_pagamento.php',
  __DIR__ . '/../paginas/receber/consultar_pagamento.php',
  __DIR__ . '/../paginas/contas/consultar_pagamento.php',
  __DIR__ . '/../paginas/financeiro/consultar_pagamento.php',
  __DIR__ . '/../painel/paginas/receber/consultar_pagamento.php',
  __DIR__ . '/../painel/paginas/contas/consultar_pagamento.php',
  __DIR__ . '/../painel/paginas/financeiro/consultar_pagamento.php',
  __DIR__ . '/../../painel/paginas/receber/consultar_pagamento.php',
  __DIR__ . '/../../painel/paginas/contas/consultar_pagamento.php',
  __DIR__ . '/../../painel/paginas/financeiro/consultar_pagamento.php',
  __DIR__ . '/../pagamentos/consultar_pagamento.php',
  __DIR__ . '/../../pagamentos/consultar_pagamento.php',
  __DIR__ . '/pagamentos/consultar_pagamento.php',
  __DIR__ . '/../paginas/pagamentos/consultar_pagamento.php',
  __DIR__ . '/../painel/paginas/pagamentos/consultar_pagamento.php',
  __DIR__ . '/../../painel/paginas/pagamentos/consultar_pagamento.php',
]);

$__bb_baixar_conta = bb_localizar_arquivo([
  __DIR__ . '/paginas/pagamentos/baixar_conta.php',
  __DIR__ . '/paginas/receber/baixar_conta.php',
  __DIR__ . '/paginas/contas/baixar_conta.php',
  __DIR__ . '/paginas/financeiro/baixar_conta.php',
  __DIR__ . '/../paginas/receber/baixar_conta.php',
  __DIR__ . '/../paginas/contas/baixar_conta.php',
  __DIR__ . '/../paginas/financeiro/baixar_conta.php',
  __DIR__ . '/../painel/paginas/receber/baixar_conta.php',
  __DIR__ . '/../painel/paginas/contas/baixar_conta.php',
  __DIR__ . '/../painel/paginas/financeiro/baixar_conta.php',
  __DIR__ . '/../../painel/paginas/receber/baixar_conta.php',
  __DIR__ . '/../../painel/paginas/contas/baixar_conta.php',
  __DIR__ . '/../../painel/paginas/financeiro/baixar_conta.php',
  __DIR__ . '/../pagamentos/baixar_conta.php',
  __DIR__ . '/../../pagamentos/baixar_conta.php',
  __DIR__ . '/pagamentos/baixar_conta.php',
  __DIR__ . '/../paginas/pagamentos/baixar_conta.php',
  __DIR__ . '/../painel/paginas/pagamentos/baixar_conta.php',
  __DIR__ . '/../../painel/paginas/pagamentos/baixar_conta.php',
]);

// Fallback: se não achou nos candidatos, busca em /paginas (profundidade limitada)
if ($__bb_consultar_pgto === '') {
  $__bb_consultar_pgto = bb_buscar_arquivo_em_arvore(__DIR__ . '/paginas', 'consultar_pagamento.php', 5);
  if ($__bb_consultar_pgto === '') {
    $__bb_consultar_pgto = bb_buscar_arquivo_em_arvore(__DIR__ . '/../paginas', 'consultar_pagamento.php', 5);
  }
}
if ($__bb_baixar_conta === '') {
  $__bb_baixar_conta = bb_buscar_arquivo_em_arvore(__DIR__ . '/paginas', 'baixar_conta.php', 5);
  if ($__bb_baixar_conta === '') {
    $__bb_baixar_conta = bb_buscar_arquivo_em_arvore(__DIR__ . '/../paginas', 'baixar_conta.php', 5);
  }
}

if ($__bb_consultar_pgto === '' || $__bb_baixar_conta === '') {
  http_response_code(500);
  echo 'Erro: scripts de pagamento não encontrados (consultar_pagamento.php / baixar_conta.php)! ' .
       'consultar=' . ($__bb_consultar_pgto ?: 'NA') . ' | baixar=' . ($__bb_baixar_conta ?: 'NA');
  exit();
}

// ===== Verificar se tem conta paga (lógica original preservada) =====
$query = $pdo->query("SELECT * from receber where pago != 'Sim' and ref_pix is not null");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$linhas = @count($res);

if ($linhas > 0 && is_array($res)) {
  foreach ($res as $__bb_row) {
    // Evita warnings quando houver índice inválido/linha inesperada
    if (!is_array($__bb_row)) {
      continue;
    }
    if (!isset($__bb_row['ref_pix'], $__bb_row['id'])) {
      continue;
    }

    $ref_pix = $__bb_row['ref_pix'];
    $id = $__bb_row['id'];
    $total_verificados += 1;

    // Consulta pagamento
    require $__bb_consultar_pgto;

    // Baixa se aprovado
    if (isset($status_api) && $status_api == 'approved') {
      $total_aprovados += 1;
      require $__bb_baixar_conta;
    }
  }
}

// Saída original preservada
echo '<span style="font-size:25px"><b><img src="images/verificadas.png" width="60px"> Verificadas:</b> <span style="color:blue">' . $total_verificados . '</span></span><br>';
echo '<span style="font-size:25px"><b><img src="images/aprovadas.png" width="60px"> Aprovadas:</b> <span style="color:green">' . $total_aprovados . '</span></span><br>';
?>
