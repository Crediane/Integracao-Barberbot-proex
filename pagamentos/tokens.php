<?php
// /public_html/pagamentos/tokens.php
// Usa exclusivamente o contexto e o banco já confirmados por config.php/conexao.php.

/**
 * Este arquivo é incluído pelo módulo de pagamentos.
 * Ele precisa SEMPRE entregar:
 *   - $access_token
 *   - $public_key
 *
 * Observação:
 * - Em alguns ambientes, o conexao.php já injeta:
 *     $access_token_mp e $public_key_mp
 * - Em outros, o config.php pode já ter preenchido $access_token / $public_key
 *
 * Regra de prioridade (sem alterar lógica de negócio):
 * 1) Se já existir $access_token_mp / $public_key_mp (não vazios) -> usar
 * 2) Senão, se já existir $access_token / $public_key (não vazios) -> manter
 * 3) Senão, tenta carregar conexao.php (para preencher as variáveis padrão do sistema)
 */

// ===== 0) Preserva tokens já existentes (se vieram do include anterior) =====
$__bb_access_token = (isset($access_token) && $access_token !== '') ? (string)$access_token : '';
$__bb_public_key   = (isset($public_key)   && $public_key   !== '') ? (string)$public_key   : '';

// ===== 1) Preferência: tokens vindos do escopo padrão do sistema ($*_mp) =====
if (isset($access_token_mp) && $access_token_mp !== '') {
  $__bb_access_token = (string)$access_token_mp;
}
if (isset($public_key_mp) && $public_key_mp !== '') {
  $__bb_public_key = (string)$public_key_mp;
}

// ===== 2) Se ainda faltar, tenta carregar a conexao.php (sem forçar caminho único) =====
if ($__bb_access_token === '' || $__bb_public_key === '') {

  // Só tenta incluir se ainda não houver conexão/carregamento anterior
  // (evita include repetido e mantém performance)
  $paths = [
    __DIR__ . '/../sistema/conexao.php',
    __DIR__ . '/../../sistema/conexao.php',
    __DIR__ . '/../../../sistema/conexao.php',
  ];

  foreach ($paths as $p) {
    if (is_file($p)) {
      require_once $p;
      break;
    }
  }

  // Após carregar, tenta novamente pegar do padrão do sistema
  if ($__bb_access_token === '' && isset($access_token_mp) && $access_token_mp !== '') {
    $__bb_access_token = (string)$access_token_mp;
  }
  if ($__bb_public_key === '' && isset($public_key_mp) && $public_key_mp !== '') {
    $__bb_public_key = (string)$public_key_mp;
  }

  // Se o sistema já usava $access_token / $public_key direto, respeita
  if ($__bb_access_token === '' && isset($access_token) && $access_token !== '') {
    $__bb_access_token = (string)$access_token;
  }
  if ($__bb_public_key === '' && isset($public_key) && $public_key !== '') {
    $__bb_public_key = (string)$public_key;
  }
}

// ===== 3) Exporta para os nomes que o módulo de pagamento usa =====
$access_token = $__bb_access_token;
$public_key   = $__bb_public_key;

// ===== 4) Compatibilidade: se o módulo espera os nomes *_mp, garante sem sobrescrever =====
if ((!isset($access_token_mp) || $access_token_mp === '') && $access_token !== '') {
  $access_token_mp = $access_token;
}
if ((!isset($public_key_mp) || $public_key_mp === '') && $public_key !== '') {
  $public_key_mp = $public_key;
}
