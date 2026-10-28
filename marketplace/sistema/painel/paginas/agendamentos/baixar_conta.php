<?php
require_once __DIR__ . '/../_context_bootstrap.php';
/**
 * Arquivo: baixar_conta.php
 *
 * Correções aplicadas (SEM alterar a lógica de negócio):
 * - Ajuste do operador do caixa: variável quebrada ($id_usua...rio) para $id_usuario.
 * - Link do recibo corrigido para MULTI-BANCO (MASTER x LOJA):
 *      * MASTER: /sistema/painel/rel/recibo_class.php?id=ID
 *      * LOJA:   /loja/<slug>/painel/rel/recibo_class.php?id=ID
 * - Mensagem enviada via api-texto.php em texto puro (quebras de linha com \n), seguindo o padrão do sistema.
 * - Inclusão do api-texto.php com resolução de caminho segura para evitar "Failed to open stream".
 *
 * Observação importante:
 * - Este arquivo é normalmente incluído por outros scripts (ex.: verificar_pgtos.php) e depende de:
 *      $pdo, $id (id da conta) e $status_api (quando aplicável).
 * - A regra de recorrência, baixa e caixa foi mantida como está.
 */

// ===== Helper: localizar arquivo com segurança (sem quebrar o sistema) =====
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

// ===== Garantir conexão se este arquivo for chamado isoladamente =====
if (!isset($pdo)) {
  $__bb_conexao = bb_localizar_arquivo([
    __DIR__ . '/../conexao.php',
    __DIR__ . '/../../conexao.php',
    __DIR__ . '/../../../conexao.php',
  ]);
  if ($__bb_conexao !== '') {
    require_once $__bb_conexao;
  }
}

$id_usuario = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;

$data_pgto        = date('Y-m-d');
$data_lancamento  = date('Y-m-d');
$hora_lancamento  = date('H:i:s');

// O process_payment_conta.php já define $forma_pgto quando consulta o Mercado Pago.
// Mantém Pix como padrão somente quando este arquivo for chamado isoladamente.
if (!isset($forma_pgto) || trim((string)$forma_pgto) === '') {
    $forma_pgto = 'Pix';
}
$forma_pgto = trim((string)$forma_pgto);

// garante que $id veio definido
$id = isset($id) ? (int)$id : 0;
if ($id <= 0) {
    return;
}

if (!isset($pdo) || !($pdo instanceof PDO)) {
    return;
}

/**
 * Consulta as colunas reais da tabela para manter compatibilidade entre bancos/lojas.
 */
if (!function_exists('bb_colunas_tabela_pagamento')) {
function bb_colunas_tabela_pagamento(PDO $pdo, string $tabela): array {
    static $cache = [];

    $chave = strtolower($tabela);
    if (isset($cache[$chave])) {
        return $cache[$chave];
    }

    $colunas = [];
    try {
        $stmt = $pdo->query("DESCRIBE `$tabela`");
        $res = $stmt ? $stmt->fetchAll(PDO::FETCH_ASSOC) : [];
        foreach ($res as $linha) {
            if (!empty($linha['Field'])) {
                $colunas[(string)$linha['Field']] = true;
            }
        }
    } catch (Throwable $e) {
        $colunas = [];
    }

    $cache[$chave] = $colunas;
    return $colunas;
}
}

if (!function_exists('bb_tem_coluna_pagamento')) {
function bb_tem_coluna_pagamento(array $colunas, string $coluna): bool {
    return isset($colunas[$coluna]);
}
}

if (!function_exists('bb_valor_decimal_pagamento')) {
function bb_valor_decimal_pagamento($valor): float {
    $valor = trim((string)$valor);
    if ($valor === '') {
        return 0.0;
    }

    if (strpos($valor, ',') !== false) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    }

    return is_numeric($valor) ? (float)$valor : 0.0;
}
}

if (!function_exists('bb_sim_pagamento')) {
function bb_sim_pagamento($valor): bool {
    $valor = strtolower(trim((string)$valor));
    return in_array($valor, ['sim', 's', '1', 'true'], true);
}
}

if (!function_exists('bb_calcular_proximo_vencimento_pagamento')) {
function bb_calcular_proximo_vencimento_pagamento(string $dataVencimento, int $diasFrequencia, bool $recorrenciaSim): string {
    $base = $dataVencimento !== '' ? $dataVencimento : date('Y-m-d');
    $timestampBase = strtotime($base);
    if ($timestampBase === false) {
        $timestampBase = time();
    }

    // Segue o padrão já usado no sistema:
    // 30/31 = próximo mês; 90 = 3 meses; 180 = 6 meses; 360/365 = 12 meses.
    // Quando a conta está marcada como recorrente, mas a frequência veio vazia,
    // mantém o comportamento mensal das assinaturas.
    if ($diasFrequencia <= 0 && $recorrenciaSim) {
        $diasFrequencia = 30;
    }

    if ($diasFrequencia == 30 || $diasFrequencia == 31) {
        return date('Y-m-d', strtotime('+1 month', $timestampBase));
    }

    if ($diasFrequencia == 90) {
        return date('Y-m-d', strtotime('+3 month', $timestampBase));
    }

    if ($diasFrequencia == 180) {
        return date('Y-m-d', strtotime('+6 month', $timestampBase));
    }

    if ($diasFrequencia == 360 || $diasFrequencia == 365) {
        return date('Y-m-d', strtotime('+12 month', $timestampBase));
    }

    if ($diasFrequencia > 0) {
        return date('Y-m-d', strtotime('+' . $diasFrequencia . ' days', $timestampBase));
    }

    return date('Y-m-d', strtotime('+1 month', $timestampBase));
}
}

if (!function_exists('bb_normalizar_telefone_pagamento')) {
function bb_normalizar_telefone_pagamento($telefone): string {
    $telefone = preg_replace('/\D+/', '', (string)$telefone);
    if ($telefone === '') {
        return '';
    }

    // Evita duplicar DDI quando o cliente já está salvo com 55.
    if (strpos($telefone, '55') === 0 && strlen($telefone) >= 12) {
        return $telefone;
    }

    return '55' . $telefone;
}
}

$colunasReceber = bb_colunas_tabela_pagamento($pdo, 'receber');

$iniciouTransacao = false;

try {

    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
        $iniciouTransacao = true;
    }

    // Busca e trava a conta dentro da transação para evitar baixa/recorrência duplicada.
    $stmtConta = $pdo->prepare("SELECT * FROM receber WHERE id = :id LIMIT 1 FOR UPDATE");
    $stmtConta->bindValue(':id', $id, PDO::PARAM_INT);
    $stmtConta->execute();
    $conta = $stmtConta->fetch(PDO::FETCH_ASSOC);

    if (!$conta) {
        if ($iniciouTransacao) {
            $pdo->commit();
        }
        return;
    }

    // Idempotência: se já foi baixada, não gera próxima novamente.
    if (($conta['pago'] ?? '') === 'Sim') {
        if ($iniciouTransacao) {
            $pdo->commit();
        }
        return;
    }

    $funcionario      = (int)($conta['funcionario'] ?? 0);
    $servico          = (int)($conta['servico'] ?? 0);
    $pessoaConta      = (int)($conta['pessoa'] ?? 0);
    $clienteConta     = (int)($conta['cliente'] ?? 0);
    $cliente          = $pessoaConta > 0 ? $pessoaConta : $clienteConta;
    $descricaoConta   = trim((string)($conta['descricao'] ?? ''));
    $descricaoRecibo  = 'Comissão - ' . $descricaoConta;
    $tipo             = trim((string)($conta['tipo'] ?? ''));
    $valor            = bb_valor_decimal_pagamento($conta['valor'] ?? 0);
    $valor_serv       = $valor;
    $frequencia       = (int)($conta['frequencia'] ?? 0);
    $dias_frequencia  = $frequencia;
    $recorrencia      = trim((string)($conta['recorrencia'] ?? ''));
    $recorrenciaSim   = bb_sim_pagamento($recorrencia) || $frequencia > 0;
    $data_venc        = trim((string)($conta['data_venc'] ?? ''));
    $hash             = trim((string)($conta['hash'] ?? ''));
    $id_ref           = (int)($conta['id_ref'] ?? 0);
    $parcelaAtual     = (int)($conta['parcela'] ?? 0);
    $novaParcela      = $parcelaAtual + 1;
    $grupo            = trim((string)($conta['grupo'] ?? ''));
    $referencia       = (int)($conta['referencia'] ?? 0);

    if ($hash != "") {
        $arquivo_agendar_delete = bb_localizar_arquivo([
            __DIR__ . '/agendar-delete.php',
            __DIR__ . '/../receber/agendar-delete.php',
            __DIR__ . '/../agendar-delete.php',
        ]);

        if ($arquivo_agendar_delete !== '') {
            require $arquivo_agendar_delete;
        }
    }

    // Dados do cliente: usa pessoa primeiro; se a conta de assinatura estiver em receber.cliente,
    // usa cliente como fallback, seguindo a estrutura real da tabela receber.
    $telefone_cliente = '';
    $nome_cliente = 'Cliente';

    if ($cliente > 0) {
        $stmtCliente = $pdo->prepare("SELECT nome, telefone FROM clientes WHERE id = :id LIMIT 1");
        $stmtCliente->bindValue(':id', $cliente, PDO::PARAM_INT);
        $stmtCliente->execute();
        $clienteDados = $stmtCliente->fetch(PDO::FETCH_ASSOC);

        if ($clienteDados) {
            $telefone_cliente = (string)($clienteDados['telefone'] ?? '');
            $nome_cliente     = (string)($clienteDados['nome'] ?? 'Cliente');
        }
    }

    /* ==================== RECORRÊNCIA ==================== */

    if ($recorrenciaSim) {

        $novo_vencimento = bb_calcular_proximo_vencimento_pagamento($data_venc, $dias_frequencia, $recorrenciaSim);

        if (!isset($hora_random) || empty($hora_random)) {
            $hora_random = date('H:i:s');
        }

        // Evita gerar a mesma próxima cobrança novamente quando o retorno do gateway
        // e o check do navegador chegam quase ao mesmo tempo.
        $stmtExiste = $pdo->prepare("
            SELECT id
              FROM receber
             WHERE data_venc = :data_venc
               AND descricao = :descricao
               AND tipo = :tipo
               AND pago = 'Não'
               AND (
                    pessoa = :cliente_pessoa
                    OR cliente = :cliente_cliente
               )
             LIMIT 1
        ");
        $stmtExiste->bindValue(':data_venc', $novo_vencimento);
        $stmtExiste->bindValue(':descricao', $descricaoConta !== '' ? $descricaoConta : 'Plano Assinatura');
        $stmtExiste->bindValue(':tipo', $tipo !== '' ? $tipo : 'Assinatura');
        $stmtExiste->bindValue(':cliente_pessoa', $cliente, PDO::PARAM_INT);
        $stmtExiste->bindValue(':cliente_cliente', $cliente, PDO::PARAM_INT);
        $stmtExiste->execute();
        $jaExisteProxima = $stmtExiste->fetch(PDO::FETCH_ASSOC);

        if (!$jaExisteProxima) {

            $dadosInsert = [];

            // Mantém os nomes já usados no sistema para cobrança recorrente de assinatura,
            // preservando o tipo/descrição original quando já vierem preenchidos.
            $dadosInsert['descricao'] = $descricaoConta !== '' ? $descricaoConta : 'Plano Assinatura';
            $dadosInsert['tipo'] = $tipo !== '' ? $tipo : 'Assinatura';
            $dadosInsert['valor'] = $valor;
            $dadosInsert['data_lanc'] = $data_lancamento;
            $dadosInsert['data_venc'] = $novo_vencimento;
            $dadosInsert['usuario_lanc'] = $id_usuario;
            $dadosInsert['foto'] = 'sem-foto.jpg';
            $dadosInsert['pessoa'] = $cliente;
            $dadosInsert['cliente'] = $cliente;
            $dadosInsert['pago'] = 'Não';
            $dadosInsert['hora'] = $hora_lancamento;
            $dadosInsert['frequencia'] = $dias_frequencia > 0 ? $dias_frequencia : 30;
            $dadosInsert['recorrencia'] = $recorrencia !== '' ? $recorrencia : 'Sim';
            $dadosInsert['id_ref'] = $id_ref;
            $dadosInsert['parcela'] = $novaParcela;
            $dadosInsert['referencia'] = $referencia;
            $dadosInsert['grupo'] = $grupo;
            $dadosInsert['hora_alerta'] = $hora_random;

            $campos = [];
            $params = [];
            $valores = [];

            foreach ($dadosInsert as $campo => $valorCampo) {
                if (bb_tem_coluna_pagamento($colunasReceber, $campo)) {
                    $campos[] = "`$campo`";
                    $params[] = ':' . $campo;
                    $valores[':' . $campo] = $valorCampo;
                }
            }

            if (!empty($campos)) {
                $sqlInsert = "INSERT INTO receber (" . implode(', ', $campos) . ") VALUES (" . implode(', ', $params) . ")";
                $stmtInsert = $pdo->prepare($sqlInsert);

                foreach ($valores as $param => $valorCampo) {
                    if (is_int($valorCampo)) {
                        $stmtInsert->bindValue($param, $valorCampo, PDO::PARAM_INT);
                    } else {
                        $stmtInsert->bindValue($param, $valorCampo);
                    }
                }

                $stmtInsert->execute();
            }
        }
    }

    /* ==================== CAIXA ==================== */

    $stmtCaixa = $pdo->prepare("
        SELECT id
          FROM caixas
         WHERE operador = :operador
           AND data_fechamento IS NULL
         ORDER BY id DESC
         LIMIT 1
    ");
    $stmtCaixa->bindValue(':operador', $id_usuario, PDO::PARAM_INT);
    $stmtCaixa->execute();
    $caixa = $stmtCaixa->fetch(PDO::FETCH_ASSOC);
    $id_caixa = $caixa ? (int)$caixa['id'] : 0;

    /* ==================== BAIXA DA CONTA ==================== */

    $stmtBaixa = $pdo->prepare("
        UPDATE receber
           SET pgto          = :pgto,
               valor         = :valor,
               pago          = 'Sim',
               usuario_baixa = :usuario_baixa,
               data_pgto     = :data_pgto,
               caixa         = :caixa,
               hora          = :hora
         WHERE id = :id
           AND pago != 'Sim'
    ");
    $stmtBaixa->bindValue(':pgto', $forma_pgto);
    $stmtBaixa->bindValue(':valor', $valor_serv);
    $stmtBaixa->bindValue(':usuario_baixa', $id_usuario, PDO::PARAM_INT);
    $stmtBaixa->bindValue(':data_pgto', $data_pgto);
    $stmtBaixa->bindValue(':caixa', $id_caixa, PDO::PARAM_INT);
    $stmtBaixa->bindValue(':hora', $hora_lancamento);
    $stmtBaixa->bindValue(':id', $id, PDO::PARAM_INT);
    $stmtBaixa->execute();

    if ($iniciouTransacao) {
        $pdo->commit();
    }

} catch (Throwable $e) {

    if ($iniciouTransacao && $pdo->inTransaction()) {
        $pdo->rollBack();
    }

    error_log('BarberBot baixar_conta.php: ' . $e->getMessage());
    return;
}

/* ==================== RECIBO POR WHATSAPP ==================== */

$valorF = number_format((float)$valor, 2, ',', '.');

if (!empty($telefone_cliente)) {

    $telefone = bb_normalizar_telefone_pagamento($telefone_cliente);

    if ($telefone !== '') {

        // identifica loja (slug) quando estiver em /loja/<slug>/...
        $slug_loja = '';
        if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath, $__m)) {
            $slug_loja = $__m[1];
        } elseif (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_refPath, $__m)) {
            $slug_loja = $__m[1];
        } elseif (!empty($_GET['loja'])) {
            $slug_loja = (string)$_GET['loja'];
        } elseif (!empty($_POST['loja'])) {
            $slug_loja = (string)$_POST['loja'];
        }
        $slug_loja = preg_replace('/[^a-z0-9_-]/i', '', (string)$slug_loja);

        // base do sistema
        if (empty($url_sistema)) {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            $scheme = $https ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? '';
            $url_sistema = $host !== '' ? ($scheme . '://' . $host) : '';
        }
        $url_sistema = rtrim((string)$url_sistema, '/');

        // link do recibo multi-banco
        if ($slug_loja !== '') {
            $link_recibo = $url_sistema . '/loja/' . $slug_loja . '/painel/rel/recibo_class.php?id=' . $id;
        } else {
            $link_recibo = $url_sistema . '/sistema/painel/rel/recibo_class.php?id=' . $id;
        }

        $nome_sistema_msg = isset($nome_sistema) ? (string)$nome_sistema : 'BarberBot software';

        $mensagem  = "💳 *{$nome_sistema_msg}*\n";
        $mensagem .= "✅ _Pagamento Confirmado_\n";
        $mensagem .= "-------------------------------\n";
        $mensagem .= "*Recibo de Pagamento*\n";
        $mensagem .= "-------------------------------\n";
        $mensagem .= "*Cliente:* {$nome_cliente}\n";
        $mensagem .= "*Descrição:* {$descricaoRecibo}\n";
        $mensagem .= "*Valor:* R$ {$valorF}\n";
        $mensagem .= "*Data de Pagamento:* " . date('d/m/Y') . "\n";
        $mensagem .= "*Forma de Pagamento:* {$forma_pgto}\n";
        $mensagem .= "-------------------------------\n";
        $mensagem .= "Agradecemos pela preferência!\n";
        $mensagem .= "📄 *Recibo:*\n{$link_recibo}";

        $arquivo_api_texto = bb_localizar_arquivo([
            __DIR__ . '/../ajax/api-texto.php',
            __DIR__ . '/../../ajax/api-texto.php',
            __DIR__ . '/../../../ajax/api-texto.php',
            __DIR__ . '/../../../../ajax/api-texto.php',
        ]);

        if ($arquivo_api_texto !== '') {
            require $arquivo_api_texto;
        }
    }
}

?>
