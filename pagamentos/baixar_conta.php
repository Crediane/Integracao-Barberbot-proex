<?php
/**
 * Arquivo: baixar_conta.php
 * A baixa continua no banco individual já confirmado para a barbearia.
 * Este arquivo não escolhe sessão ou banco pelo referenciador.
 */

require_once __DIR__ . '/config.php';

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

$id_usuario = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;

$data_pgto  = date('Y-m-d');
$data_lancamento = date('Y-m-d');
$hora_lancamento = date('H:i:s');
$forma_pgto = 'Pix';

// garante que $id veio definido
$id = isset($id) ? (int)$id : 0;
if ($id <= 0) {
    // nada pra fazer
    return;
}

// busca conta a receber
$query = $pdo->prepare("SELECT * FROM receber WHERE id = :id LIMIT 1");
$query->execute([':id' => $id]);
$res = $query->fetchAll(PDO::FETCH_ASSOC);

if (@count($res) == 0) {
    // conta não encontrada, evita notice
    return;
}

$funcionario      = $res[0]['funcionario'];
$servico          = $res[0]['servico'];
$cliente          = $res[0]['pessoa'];
$descricao        = 'Comissão - ' . $res[0]['descricao'];
$tipo             = $res[0]['tipo'];
$valor            = $res[0]['valor'];
$pgto             = $res[0]['pgto'];
$valor_serv       = $res[0]['valor'];
$frequencia       = $res[0]['frequencia'];
$dias_frequencia  = $res[0]['frequencia'];
$data_venc        = $res[0]['data_venc'];
$hash             = $res[0]['hash'];

// se tiver agendamento vinculado, remove (mantido)
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

// dados do cliente
$query2 = $pdo->query("SELECT * FROM clientes WHERE id = '$cliente' LIMIT 1");
$res2   = $query2->fetchAll(PDO::FETCH_ASSOC);

$telefone_cliente = (@count($res2) > 0) ? $res2[0]['telefone'] : '';
$nome_cliente     = (@count($res2) > 0) ? $res2[0]['nome']     : 'Cliente';

// formata valor para exibição
$valorF = number_format((float)$valor, 2, ',', '.');

/* ==================== RECORRÊNCIA (mantida) ==================== */

if ($frequencia > 0) {

    if ($dias_frequencia == 30 || $dias_frequencia == 31) {
        $novo_vencimento = date('Y-m-d', strtotime("+1 month", strtotime($data_venc)));
    } else if ($dias_frequencia == 90) {
        $novo_vencimento = date('Y-m-d', strtotime("+3 month", strtotime($data_venc)));
    } else if ($dias_frequencia == 180) {
        $novo_vencimento = date('Y-m-d', strtotime("+6 month", strtotime($data_venc)));
    } else if ($dias_frequencia == 360 || $dias_frequencia == 365) {
        $novo_vencimento = date('Y-m-d', strtotime("+12 month", strtotime($data_venc)));
    } else {
        $novo_vencimento = date('Y-m-d', strtotime("+$dias_frequencia days", strtotime($data_venc)));
    }

    // se $hora_random não estiver definido no contexto, cria um padrão
    if (!isset($hora_random) || empty($hora_random)) {
        $hora_random = date('H:i:s');
    }

    $pdo->query("
        INSERT INTO receber 
        SET descricao    = 'Plano Assinatura',
            tipo         = 'Assinatura',
            valor        = '$valor',
            data_lanc    = '$data_lancamento',
            data_venc    = '$novo_vencimento',
            usuario_lanc = '$id_usuario',
            foto         = 'sem-foto.jpg',
            pessoa       = '$cliente',
            pago         = 'Não',
            hora         = '$hora_lancamento',
            frequencia   = '$frequencia',
            hora_alerta  = '$hora_random'
    ");

    // envio de WhatsApp da recorrência continua desativado (como já estava)
}

/* ==================== CAIXA (mantido) ==================== */

// verifica se há caixa aberto para esse operador
$query1 = $pdo->query("
    SELECT * 
    FROM caixas 
    WHERE operador = '$id_usuario' 
      AND data_fechamento IS NULL 
    ORDER BY id DESC 
    LIMIT 1
");
$res1     = $query1->fetchAll(PDO::FETCH_ASSOC);
$id_caixa = (@count($res1) > 0) ? $res1[0]['id'] : 0;

/* ==================== BAIXA DA CONTA (mantida) ==================== */

$pdo->query("
    UPDATE receber 
       SET pgto          = '$forma_pgto',
           valor         = '$valor_serv',
           pago          = 'Sim',
           usuario_baixa = '$id_usuario',
           data_pgto     = '$data_pgto',
           caixa         = '$id_caixa',
           hora          = '$hora_lancamento'
     WHERE id = '$id'
");

/* ==================== RECIBO POR WHATSAPP ==================== */

if (!empty($telefone_cliente)) {

    // normaliza telefone
    $telefone = '55' . preg_replace('/\D+/', '', (string)$telefone_cliente);

    // Slug já confirmado pelo bootstrap do módulo.
    $slug_loja = isset($bb_pagamentos_slug) ? (string)$bb_pagamentos_slug : bb_pagamentos_limpar_slug($_SESSION['tenant_slug'] ?? '');

    // base do sistema
    if (empty($url_sistema)) {
      $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
      $scheme = $https ? 'https' : 'http';
      $host = $_SERVER['HTTP_HOST'] ?? '';
      $url_sistema = $host !== '' ? ($scheme . '://' . $host) : '';
    }
    $url_sistema = rtrim((string)$url_sistema, '/');

    // link do recibo (corrigido multi-banco)
    if ($slug_loja !== '') {
      $link_recibo = $url_sistema . '/loja/' . $slug_loja . '/painel/rel/recibo_class.php?id=' . $id;
    } else {
      $link_recibo = $url_sistema . '/sistema/painel/rel/recibo_class.php?id=' . $id;
    }

    // Nome do sistema (fallback seguro)
    $nome_sistema_msg = isset($nome_sistema) ? (string)$nome_sistema : 'BarberBot software';

    // monta texto do recibo (texto puro - padrão do api-texto)
    $mensagem  = "💳 *{$nome_sistema_msg}*\n";
    $mensagem .= "✅ _Pagamento Confirmado_\n";
    $mensagem .= "-------------------------------\n";
    $mensagem .= "*Recibo de Pagamento*\n";
    $mensagem .= "-------------------------------\n";
    $mensagem .= "*Cliente:* {$nome_cliente}\n";
    $mensagem .= "*Descrição:* {$descricao}\n";
    $mensagem .= "*Valor:* R$ {$valorF}\n";
    $mensagem .= "*Data de Pagamento:* " . date('d/m/Y') . "\n";
    $mensagem .= "*Forma de Pagamento:* {$forma_pgto}\n";
    $mensagem .= "-------------------------------\n";
    $mensagem .= "Agradecemos pela preferência!\n";
    $mensagem .= "📄 *Recibo:*\n{$link_recibo}";

    // localizar e enviar pelo mesmo endpoint do sistema
    $arquivo_api_texto = bb_localizar_arquivo([
      __DIR__ . '/../ajax/api-texto.php',          // /sistema/pagamentos -> /sistema/ajax
      __DIR__ . '/../../ajax/api-texto.php',       // /sistema/painel/paginas/pagamentos -> /sistema/painel/ajax (se existir)
      __DIR__ . '/../../../ajax/api-texto.php',    // /sistema/painel/paginas/pagamentos -> /sistema/ajax
      __DIR__ . '/../../../../ajax/api-texto.php', // fallback adicional
    ]);

    if ($arquivo_api_texto !== '') {
      require $arquivo_api_texto;
    }
}

?>
