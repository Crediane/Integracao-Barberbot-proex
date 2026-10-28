<?php
require_once __DIR__ . '/master_bootstrap.php';
header('Content-Type: application/json; charset=utf-8');

if (!function_exists('bb_market_json_saida')) {
  function bb_market_json_saida(array $dados, int $status = 200): void {
    if (ob_get_level() > 0) {
      @ob_clean();
    }
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
  }
}

if (!function_exists('bb_market_incluir_primeiro_arquivo')) {
  function bb_market_incluir_primeiro_arquivo(array $caminhos): string {
    foreach ($caminhos as $caminho) {
      if (is_file($caminho)) {
        return $caminho;
      }
    }
    return '';
  }
}

ob_start();

/**
 * Inclui conexao.php aceitando mais de um caminho
 */
$paths = [
  __DIR__ . '/../../../conexao.php',
  __DIR__ . '/../../conexao.php',
];

$arquivoConexao = bb_market_incluir_primeiro_arquivo($paths);
if ($arquivoConexao === '') {
  bb_market_json_saida(['ok'=>false, 'msg'=>'Arquivo conexao.php não encontrado.']);
}
require_once $arquivoConexao;

$id = (int)($_POST['id'] ?? 0);
if($id <= 0){
  bb_market_json_saida(['ok'=>false, 'msg'=>'ID inválido.']);
}

// delega para cobranca.php com a mesma lógica do sistema
$_POST['acao'] = 'enviar';

$arquivoCobranca = bb_market_incluir_primeiro_arquivo([
  __DIR__ . '/cobranca.php',
  __DIR__ . '/gerar_cobranca.php'
]);

if ($arquivoCobranca === '') {
  bb_market_json_saida(['ok'=>false, 'msg'=>'Arquivo cobranca.php não encontrado.']);
}

require $arquivoCobranca;
exit;
