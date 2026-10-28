<?php
require_once __DIR__ . '/../_context_bootstrap.php';
header('Content-Type: text/plain; charset=UTF-8');

// ===== Garantir ?loja=<slug> para o conexao.php (sem mudar a lógica) =====
if ($__bb_reqSlug !== '' && empty($_GET['loja'])) {
    $_GET['loja'] = $__bb_reqSlug;
}

$tabela = 'cobrancas';
require_once __DIR__ . '/../../../conexao.php';

$id_usuario = $_SESSION['id'] ?? '';
$data_atual = date('Y-m-d');

// ✅ evita Warning quando não existir nas configs
if (!isset($dias_aviso) || $dias_aviso === '' || $dias_aviso === null) {
    $dias_aviso = 0;
}
$dias_aviso = (int)$dias_aviso;

// ✅ Base do site (remove /sistema se existir) e ajusta para public_html/pagamentos
$url_sistema_limpo = rtrim((string)$url_sistema, '/');
$url_site = preg_replace('~/sistema$~i', '', $url_sistema_limpo);
$url_site = rtrim((string)$url_site, '/') . '/';

// ✅ Multi-tenant: preserva loja para banco individual no /pagamentos
$loja = '';
if ($__bb_reqSlug !== '') {
    $loja = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$__bb_reqSlug);
} elseif (isset($_SESSION['tenant_slug']) && $_SESSION['tenant_slug'] != '') {
    $loja = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$_SESSION['tenant_slug']);
} elseif (isset($_SESSION['loja']) && $_SESSION['loja'] != '') {
    $loja = preg_replace('/[^a-zA-Z0-9_-]/', '', (string)$_SESSION['loja']);
}

if ($loja !== '') {
    $_SESSION['tenant_slug'] = $loja;
    $_SESSION['loja'] = $loja;
}

// Libera a sessão antes do processamento pesado / APIs externas
if (session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}

// =========================
// POST (evita warnings sem mudar regra)
// =========================
$valor = isset($_POST['valor']) ? $_POST['valor'] : '';
$parcelas = isset($_POST['parcelas']) ? $_POST['parcelas'] : '';
$obs = isset($_POST['obs']) ? $_POST['obs'] : '';
$data_venc = isset($_POST['data_venc']) ? $_POST['data_venc'] : '';
$id = isset($_POST['id']) ? $_POST['id'] : '';
$id2 = isset($_POST['id2']) ? $_POST['id2'] : '';
$cliente = isset($_POST['cliente']) ? $_POST['cliente'] : '';

// ✅ dias da frequência (numérico) - usado nos cálculos
$frequencia_dias = isset($_POST['frequencia']) ? (int)$_POST['frequencia'] : 0;

// =========================
// Normalização do valor (mantém padrão)
// =========================
$valor = preg_replace('/[^\d.,]/', '', (string)$valor);

if (strpos($valor, ',') !== false && strpos($valor, '.') !== false) {
    $valor = str_replace('.', '', $valor);
    $valor = str_replace(',', '.', $valor);
} elseif (strpos($valor, ',') !== false) {
    $valor = str_replace('.', '', $valor);
    $valor = str_replace(',', '.', $valor);
}

$valor = (float)$valor;
$parcelas = (int)$parcelas;
$id = (int)$id;
$id2 = (int)$id2;

// =========================
// Frequência (mantém lógica)
// - $frequencia = NOME (Mensal, Diária...)
// - $frequencia_dias = DIAS (30, 1...)
// =========================
$query = $pdo->prepare("SELECT * FROM frequencias WHERE dias = :dias LIMIT 1");
$query->bindValue(':dias', $frequencia_dias, PDO::PARAM_INT);
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$frequencia = $res[0]['frequencia'] ?? '';

if ($parcelas == 0) {
    $parcelas = 1;
}

$valor_parcela = $valor / $parcelas;

$valor_total_juros = 0;
$valor_parcelas = 0;

$grupo = uniqid();

// =========================
// Inserir / Atualizar cobrança (mesma regra)
// =========================
if ($id2 == 0) {

    $query = $pdo->prepare("INSERT INTO $tabela SET cliente = :cliente, valor = :valor, parcelas = :parcelas, data = curDate(), usuario = '$id_usuario', obs = :obs, data_venc = :data_venc, frequencia = :frequencia, ativo = 'Sim'");
    $query->bindValue(':cliente', $id);
    $query->bindValue(':valor', $valor);
    $query->bindValue(':parcelas', $parcelas);
    $query->bindValue(':obs', $obs);
    $query->bindValue(':data_venc', $data_venc);
    $query->bindValue(':frequencia', $frequencia);
    $query->execute();

    $ult_id = $pdo->lastInsertId();

} else {

    $query = $pdo->prepare("UPDATE $tabela SET cliente = :cliente, valor = :valor, parcelas = :parcelas, data = curDate(), usuario = '$id_usuario', obs = :obs, data_venc = :data_venc, frequencia = :frequencia WHERE id = '$id2'");
    $query->bindValue(':cliente', $cliente);
    $query->bindValue(':valor', $valor);
    $query->bindValue(':parcelas', $parcelas);
    $query->bindValue(':obs', $obs);
    $query->bindValue(':data_venc', $data_venc);
    $query->bindValue(':frequencia', $frequencia);
    $query->execute();

    $ult_id = $id2;
}

// =========================
// Dados do cliente (mesma regra)
// =========================
$query = $pdo->prepare("SELECT * FROM clientes WHERE id = :id LIMIT 1");
$query->bindValue(':id', $id, PDO::PARAM_INT);
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);

$nome_cliente = $res[0]['nome'] ?? '';
$tel_cliente = $res[0]['telefone'] ?? '';
$tel_cliente = '55' . preg_replace('/[ ()-]+/', '', (string)$tel_cliente);
$telefone = $tel_cliente;

// =========================
// Gerar parcelas / recorrência (mesma lógica)
// =========================
for ($i = 1; $i <= $parcelas; $i++) {

    $ult_id_conta = 0;
    $descricao = 'Assinatura - ' . $nome_cliente . ' (' . $i . ')';

    $dias_parcela = $i - 1;
    $dias_parcela_2 = ($i - 1) * $frequencia_dias;

    if ($i == 1) {
        $novo_vencimento = $data_venc;
    } else {

        if ($frequencia_dias == 30 || $frequencia_dias == 31) {

            $novo_vencimento = date('Y-m-d', strtotime("+$dias_parcela month", strtotime($data_venc)));

        } elseif ($frequencia_dias == 90) {

            $dias_parcela = $dias_parcela * 3;
            $novo_vencimento = date('Y-m-d', strtotime("+$dias_parcela month", strtotime($data_venc)));

        } elseif ($frequencia_dias == 180) {

            $dias_parcela = $dias_parcela * 6;
            $novo_vencimento = date('Y-m-d', strtotime("+$dias_parcela month", strtotime($data_venc)));

        } elseif ($frequencia_dias == 360 || $frequencia_dias == 365) {

            $dias_parcela = $dias_parcela * 12;
            $novo_vencimento = date('Y-m-d', strtotime("+$dias_parcela month", strtotime($data_venc)));

        } else {

            $novo_vencimento = date('Y-m-d', strtotime("+$dias_parcela_2 days", strtotime($data_venc)));
        }
    }

    if ($parcelas > 1) {
        $recorrencia = 'Não';
        $texto_cobranca = 'Frequência Parcelas ';
    } else {
        $recorrencia = 'Sim';
        $texto_cobranca = 'Recorrência ';
    }

    if ($frequencia_dias == 0) {
        $recorrencia = 'Não';
    }

    // =========================
    // Inserir/Atualizar receber (mesma regra)
    // =========================
    if ($id2 == 0) {

        $query = $pdo->prepare("INSERT INTO receber SET tipo = 'Serviço', cliente = '$id', grupo = '$grupo', referencia = 'Cobrança', id_ref = '$ult_id', valor = '$valor', parcela = '$i', usuario_lanc = '$id_usuario', data_lanc = curDate(), data_venc = '$novo_vencimento', pago = 'Não', descricao = '$descricao', frequencia = '$frequencia_dias', recorrencia = '$recorrencia', pessoa = '$id'");
        $query->execute();
        $ult_id_conta = $pdo->lastInsertId();

    } else {

        if ($token != "" && $instancia != "") {
            $query = $pdo->query("SELECT * FROM receber WHERE referencia = 'Cobrança' AND id_ref = '$id2'");
            $res2 = $query->fetchAll(PDO::FETCH_ASSOC);

            for ($i2 = 0; $i2 < @count($res2); $i2++) {
                $hash = $res2[$i2]['hash'];
                require __DIR__ . '/../../../../ajax/api-excluir.php';
            }
        }

        $query = $pdo->prepare("UPDATE receber SET cliente = '$cliente', referencia = 'Cobrança', id_ref = '$id2', valor = '$valor', parcela = '$i', usuario_lanc = '$id_usuario', data_lanc = curDate(), data_venc = '$novo_vencimento', pago = 'Não', descricao = '$descricao', frequencia = '$frequencia_dias', recorrencia = '$recorrencia' WHERE id_ref = '$id2' AND pago = 'Não'");
        $query->execute();
    }

    // =========================
    // Agendamento WhatsApp (mantém lógica) + link em public_html/pagamentos
    // =========================
    if ($token != "" && $instancia != "") {

        $novo_vencimentoF = implode('/', array_reverse(explode('-', $novo_vencimento)));
        $valor_parcela_finalF = number_format($valor, 2, ',', '.');
        $data_env = date('Y/m/d', strtotime("-$dias_aviso days", strtotime($novo_vencimento)));

        $link_pgto = $url_site . 'pagamentos/receber.php?id_conta=' . @$ult_id_conta;
        if ($loja != '') {
            $link_pgto .= '&loja=' . urlencode($loja);
        }

        $mensagem = '_' . $nome_sistema . '_ %0A';
        $mensagem = '💰_Lembrete de pagamento_ %0A';
        $mensagem .= '😀 Cliente: *' . $nome_cliente . '* %0A';
        $mensagem .= '💲Valor: *' . $valor_parcela_finalF . '* %0A';
        $mensagem .= '🕐 Vencimento: *' . $novo_vencimentoF . '* %0A%0A';
        $mensagem .= '*Link Pagamento Pix:* %0A';
        $mensagem .= $link_pgto;

        $data_mensagem = $data_env . ' 09:00';

        if (strtotime($data_env) > strtotime($data_atual)) {
            require __DIR__ . '/../../../../ajax/api-agendar.php';
            $query = $pdo->prepare("UPDATE receber SET hash2 = '$hash' WHERE id = '$ult_id_conta'");
            $query->execute();
        }

        $mensagem = '_' . $nome_sistema . '_ %0A';
        $mensagem = '💰_Sua assinatura vence hoje_ %0A';
        $mensagem .= '😀 Cliente: *' . $nome_cliente . '* %0A';
        $mensagem .= '💲Valor: *' . $valor_parcela_finalF . '* %0A';
        $mensagem .= '🕐 Vencimento: *' . $novo_vencimentoF . '* %0A%0A';
        $mensagem .= '*Link Pagamento:* %0A';
        $mensagem .= $link_pgto;

        $data_mensagem = $novo_vencimento . ' 09:00';

        require __DIR__ . '/../../../../ajax/api-agendar.php';

        if ($id2 == 0) {
            $query = $pdo->prepare("UPDATE receber SET hash = '$hash' WHERE id = '$ult_id_conta'");
            $query->execute();
        }
    }
}

// Responde cedo para o caller sem mudar a lógica do restante
echo 'Salvo com Sucesso';

if (function_exists('fastcgi_finish_request')) {
    fastcgi_finish_request();
} else {
    @ob_flush();
    @flush();
}

// =========================
// Mensagem final para o cliente (mantém lógica)
// =========================
$data_vencF = date('d', strtotime($data_venc));
$dataF = implode('/', array_reverse(explode('-', $data_atual)));
$valorF = number_format($valor, 2, ',', '.');
$valor_total_jurosF = number_format($valor_total_juros, 2, ',', '.');

$mensagem = '_' . $nome_sistema . '_ %0A';
$mensagem .= '😀 Cliente: *' . $nome_cliente . '* %0A';
$mensagem .= '💲Valor: *' . $valorF . '* %0A';
$mensagem .= 'Data Cobrança: *' . $dataF . '* %0A';
$mensagem .= 'Dia Pgto Cobranças: *Dia ' . $data_vencF . '* %0A';
$mensagem .= $texto_cobranca . ': *' . $frequencia . '* %0A%0A';

if ($parcelas > 1) {
    $mensagem .= '*Parcelas* %0A';
    $query = $pdo->query("SELECT * FROM receber WHERE referencia = 'Cobrança' AND id_ref = '$ult_id' ORDER BY id ASC");
    $res = $query->fetchAll(PDO::FETCH_ASSOC);

    for ($i = 0; $i < @count($res); $i++) {
        $valor_item = $res[$i]['valor'];
        $parcela_item = $res[$i]['parcela'];
        $data_venc_item = $res[$i]['data_venc'];
        $data_venc_itemF = implode('/', array_reverse(explode('-', $data_venc_item)));
        $valor_itemF = number_format($valor_item, 2, ',', '.');
        $mensagem .= "($parcela_item) R$: *$valor_itemF* Venc: $data_venc_itemF\n";
    }
}

require __DIR__ . '/../../../../ajax/api-texto.php';