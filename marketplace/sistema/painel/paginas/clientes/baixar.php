<?php
require_once __DIR__ . '/../_context_bootstrap.php';
$tabela = 'receber';
require_once __DIR__ . '/../../../conexao.php';

$id_usuario = isset($_SESSION['id']) ? (int)$_SESSION['id'] : 0;

/*
  Normaliza valores monetários aceitando:
  - 700
  - 700.00
  - 700,00
  - 1.200,00

  Ponto principal da correção:
  O valor base da parcela deve ser lido de "receber.valor" antes da baixa.
  Não usar "valor_baixar" como coluna obrigatória, porque o modal envia esse campo,
  mas a tabela receber nem sempre tem essa coluna preenchida.
*/
if (!function_exists('bb_clientes_normalizar_valor')) {
    function bb_clientes_normalizar_valor($valor)
    {
        $valor = trim((string)$valor);

        if ($valor === '') {
            return 0;
        }

        $valor = preg_replace('/[^0-9,\.\-]/', '', $valor);

        if ($valor === '' || $valor === '-' || $valor === ',' || $valor === '.') {
            return 0;
        }

        if (strpos($valor, ',') !== false) {
            $valor = str_replace('.', '', $valor);
            $valor = str_replace(',', '.', $valor);
        } else {
            $partes = explode('.', $valor);
            if (count($partes) > 2) {
                $decimal = array_pop($partes);
                $valor = implode('', $partes) . '.' . $decimal;
            }
        }

        if (!is_numeric($valor)) {
            return 0;
        }

        return (float)$valor;
    }
}

/*
  Cria a nova parcela do resíduo mantendo os mesmos campos usados pelo sistema.
  A função só centraliza o INSERT para evitar duplicação e manter o padrão do arquivo.
*/
if (!function_exists('bb_clientes_criar_parcela_residuo')) {
    function bb_clientes_criar_parcela_residuo(
        PDO $pdo,
        $pessoa,
        $cliente,
        $id_ref,
        $total,
        $nova_parcela,
        $recorrencia,
        $data_venc,
        $dias_frequencia,
        $descricao,
        $grupo,
        $tipo,
        $referencia
    ) {
        $insertQuery = "INSERT INTO receber
            (pessoa, cliente, id_ref, valor, parcela, recorrencia, data_venc, frequencia, descricao, grupo, tipo, referencia)
            VALUES
            (:pessoa, :cliente, :id_ref, :total, :nova_parcela, :recorrencia, :data_venc, :dias_frequencia, :descricao, :grupo, :tipo, :referencia)";

        $stmt = $pdo->prepare($insertQuery);
        $stmt->bindValue(':pessoa', $pessoa, PDO::PARAM_INT);
        $stmt->bindValue(':cliente', $cliente, PDO::PARAM_INT);
        $stmt->bindValue(':id_ref', $id_ref, PDO::PARAM_INT);
        $stmt->bindValue(':total', $total);
        $stmt->bindValue(':nova_parcela', $nova_parcela, PDO::PARAM_INT);
        $stmt->bindValue(':recorrencia', $recorrencia);
        $stmt->bindValue(':data_venc', $data_venc);
        $stmt->bindValue(':dias_frequencia', $dias_frequencia);
        $stmt->bindValue(':descricao', $descricao);
        $stmt->bindValue(':grupo', $grupo);
        $stmt->bindValue(':tipo', $tipo);
        $stmt->bindValue(':referencia', $referencia);
        $stmt->execute();
    }
}

// =========================
// Sanitização de entradas
// =========================
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$data_baixa = isset($_POST['data_baixa']) ? trim((string)$_POST['data_baixa']) : '';
$forma_pgto = isset($_POST['forma_pgto']) ? trim((string)$_POST['forma_pgto']) : '';
$valor_final = isset($_POST['valor_final']) ? bb_clientes_normalizar_valor($_POST['valor_final']) : 0;
$residuo = (isset($_POST['residuo']) && $_POST['residuo'] === 'Sim') ? 'Sim' : '';

if ($id <= 0) {
    echo 'ID inválido!';
    exit;
}

if ($data_baixa === '') {
    $data_baixa = date('Y-m-d');
}

if (!preg_match('/^\d{4}\-\d{2}\-\d{2}$/', $data_baixa)) {
    echo 'Data de pagamento inválida!';
    exit;
}

// =========================
// Buscar registro em receber
// =========================
$stmtRec = $pdo->prepare("SELECT * FROM receber WHERE id = :id LIMIT 1");
$stmtRec->bindValue(':id', $id, PDO::PARAM_INT);
$stmtRec->execute();
$res2 = $stmtRec->fetchAll(PDO::FETCH_ASSOC);

if (!isset($res2[0])) {
    echo 'Registro não encontrado!';
    exit;
}

$hash = $res2[0]['hash'] ?? '';
$pessoa = isset($res2[0]['pessoa']) ? (int)$res2[0]['pessoa'] : 0;
$cliente = isset($res2[0]['cliente']) ? (int)$res2[0]['cliente'] : 0;
$id_ref = isset($res2[0]['id_ref']) ? (int)$res2[0]['id_ref'] : 0;
$valor = bb_clientes_normalizar_valor($res2[0]['valor'] ?? 0);

if ($pessoa <= 0 && $cliente > 0) {
    $pessoa = $cliente;
}

if ($cliente <= 0 && $pessoa > 0) {
    $cliente = $pessoa;
}

/*
  Valor base correto da baixa:
  - antes estava tentando usar $res2[0]['valor_baixar'];
  - no fluxo do modal clientes, o valor real da parcela está em receber.valor;
  - assim o cálculo do resíduo volta a funcionar sem depender de coluna extra.
*/
$valor_baixar = $valor;

$parcela = isset($res2[0]['parcela']) ? (int)$res2[0]['parcela'] : 0;
$nova_parcela = $parcela + 1;

$recorrencia = $res2[0]['recorrencia'] ?? 'Nao';
$data_venc = $res2[0]['data_venc'] ?? '';
$dias_frequencia = $res2[0]['frequencia'] ?? '';
$descricao = $res2[0]['descricao'] ?? '';
$grupo = $res2[0]['grupo'] ?? '';
$tipo = $res2[0]['tipo'] ?? '';
$referencia = $res2[0]['referencia'] ?? 0;

// =========================
// Excluir agendamento na API
// =========================
if (!empty($token) && !empty($instancia)) {

    // recuperar o hash e excluir o agendamento das mensagens
    require __DIR__ . '/../../../../ajax/api-excluir.php';

    $hash = $res2[0]['hash2'] ?? '';
    require __DIR__ . '/../../../../ajax/api-excluir.php';
}

// =========================
// Buscar cobrança principal
// =========================
$stmtCob = $pdo->prepare("SELECT * FROM cobrancas WHERE id = :id_ref ORDER BY id ASC LIMIT 1");
$stmtCob->bindValue(':id_ref', $id_ref, PDO::PARAM_INT);
$stmtCob->execute();
$res3 = $stmtCob->fetchAll(PDO::FETCH_ASSOC);

$parcelas = isset($res3[0]['parcelas']) ? (int)$res3[0]['parcelas'] : 0;

if (isset($res3[0]) && is_array($res3[0])) {
    $cobranca = $res3[0];
} else {
    $cobranca = array('valor' => $valor_baixar > 0 ? $valor_baixar : $valor);
}

// =========================
// Contar parcelas existentes
// =========================
$stmtCount = $pdo->prepare("SELECT id FROM receber WHERE id_ref = :id_ref AND tipo = :tipo");
$stmtCount->bindValue(':id_ref', $id_ref, PDO::PARAM_INT);
$stmtCount->bindValue(':tipo', $tipo);
$stmtCount->execute();
$cobrancas = (int)$stmtCount->rowCount();

// =========================
// Próxima data: mantém a regra original de +1 mês
// =========================
$base_data_residuo = $data_venc ?: date('Y-m-d');
$proximaData = date('Y-m-d', strtotime("+1 month", strtotime($base_data_residuo)));
$subtotal = (float)$valor_baixar - (float)$valor_final;

// =========================
// Tem resíduo
// =========================
if ($valor_baixar > $valor_final && $residuo === 'Sim') {

    $residuo_lancado = false;

    // Verificando se tem outra parcela a frente
    if ($cobrancas > 1 && $cobrancas > $parcela) {

        /*
          Correção direta:
          Antes o código tentava atualizar a próxima parcela pela data +1 mês.
          Quando não existia parcela exatamente nessa data, o UPDATE afetava 0 linhas
          e o resíduo não era criado.

          Agora:
          - se encontrar a próxima parcela na data calculada, soma o resíduo nela;
          - se não encontrar, cai no mesmo fluxo de criação de nova parcela.
        */
        $stmtProx = $pdo->prepare("SELECT * FROM receber WHERE grupo = :grupo AND data_venc = :data_venc LIMIT 1");
        $stmtProx->bindValue(':grupo', $grupo);
        $stmtProx->bindValue(':data_venc', $proximaData);
        $stmtProx->execute();
        $result = $stmtProx->fetchAll(PDO::FETCH_ASSOC);

        if (isset($result[0])) {
            $id_proxima = isset($result[0]['id']) ? (int)$result[0]['id'] : 0;
            $valor_atual_prox = bb_clientes_normalizar_valor($result[0]['valor'] ?? 0);
            $total = $valor_atual_prox + (float)$subtotal;

            if ($id_proxima > 0) {
                $updateQuery = "UPDATE receber SET valor = :total WHERE id = :id_proxima";
                $stmt = $pdo->prepare($updateQuery);
                $stmt->bindValue(':total', $total);
                $stmt->bindValue(':id_proxima', $id_proxima, PDO::PARAM_INT);
                $stmt->execute();

                $residuo_lancado = true;
            }
        }
    }

    if ($residuo_lancado === false) {

        if ($recorrencia != 'Sim') {
            // Atualizando a quantidade de parcelas
            $updateQuery = "UPDATE cobrancas SET parcelas = :nova_parcela WHERE id = :id_ref";
            $stmt = $pdo->prepare($updateQuery);
            $stmt->bindValue(':nova_parcela', $nova_parcela, PDO::PARAM_INT);
            $stmt->bindValue(':id_ref', $id_ref, PDO::PARAM_INT);
            $stmt->execute();
        } else {
            $valor_cobranca_base = bb_clientes_normalizar_valor($cobranca['valor'] ?? 0);
            $subtotal = (float)$valor_cobranca_base + (float)$subtotal;
        }

        // Criando uma nova parcela para receber o resíduo
        bb_clientes_criar_parcela_residuo(
            $pdo,
            $pessoa,
            $cliente,
            $id_ref,
            $subtotal,
            $nova_parcela,
            $recorrencia,
            $proximaData,
            $dias_frequencia,
            $descricao,
            $grupo,
            $tipo,
            $referencia
        );
    }

} else {

    if ($recorrencia == 'Sim') {

        $valor_cobranca_base = bb_clientes_normalizar_valor($cobranca['valor'] ?? 0);

        bb_clientes_criar_parcela_residuo(
            $pdo,
            $pessoa,
            $cliente,
            $id_ref,
            $valor_cobranca_base,
            $nova_parcela,
            $recorrencia,
            $proximaData,
            $dias_frequencia,
            $descricao,
            $grupo,
            $tipo,
            $referencia
        );
    }
}

// =========================
// Baixa da parcela
// =========================
$stmtUpd = $pdo->prepare("UPDATE $tabela
    SET data_pgto = :data_pgto,
        pago = 'Sim',
        pgto = :pgto,
        valor = :valor,
        usuario_baixa = :usuario_baixa
    WHERE id = :id");

$stmtUpd->bindValue(':data_pgto', $data_baixa);
$stmtUpd->bindValue(':pgto', $forma_pgto);
$stmtUpd->bindValue(':valor', $valor_final);
$stmtUpd->bindValue(':usuario_baixa', $id_usuario, PDO::PARAM_INT);
$stmtUpd->bindValue(':id', $id, PDO::PARAM_INT);
$stmtUpd->execute();

echo 'Salvo com Sucesso*' . $id . '*' . $parcela . '*' . $parcelas;
?>
