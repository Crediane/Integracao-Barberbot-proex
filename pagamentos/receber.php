<?php
// ============================================================================
// BarberBot Software - Página de Pagamento (Conta a Receber)
// Arquivo: /public_html/pagamentos/receber.php
// Ajustes: compatibilidade de rota (/conta/{id}) + robustez de configuração MP
// Sem alterar a lógica de negócio (consulta conta -> cria pagamento -> acompanha status).
// ============================================================================
require_once __DIR__ . '/config.php';

// Helper para anexar ?loja=... nas URLs internas do módulo, sem quebrar links existentes.
function bb_add_loja_param(string $url, string $loja): string {
    if ($loja === '') return $url;
    if (stripos($url, 'loja=') !== false) return $url;
    return $url . (strpos($url, '?') !== false ? '&' : '?') . 'loja=' . urlencode($loja);
}

// Cabeçalho HTML correto (antes de qualquer saída)
if (!headers_sent()) {
    header('Content-Type: text/html; charset=UTF-8');
}

// Evita exibir warnings/notices no HTML (o retorno deve ser limpo)
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');
// error_reporting(E_ALL);

// Configurações e conexão já carregadas pelo bootstrap tenant.

// ------------------------- Token Mercado Pago (multi-perfil / multi-base) -------------------------
if (!isset($TOKEN_MERCADO_PAGO_PUBLICO)) { $TOKEN_MERCADO_PAGO_PUBLICO = ''; }
if (!isset($TOKEN_MERCADO_PAGO)) { $TOKEN_MERCADO_PAGO = ''; }

$TOKEN_MERCADO_PAGO_PUBLICO = trim((string)$TOKEN_MERCADO_PAGO_PUBLICO);
$TOKEN_MERCADO_PAGO = trim((string)$TOKEN_MERCADO_PAGO);

function bb_mp_buscar_primeiro_valor(PDO $pdo, string $tabela, string $coluna): string {
    try {
        $sql = "SELECT `$coluna` AS v FROM `$tabela` WHERE `$coluna` IS NOT NULL AND TRIM(`$coluna`) <> '' LIMIT 1";
        $st = $pdo->prepare($sql);
        $st->execute();
        $v = $st->fetchColumn();
        return $v ? trim((string)$v) : '';
    } catch (Throwable $e) {
        return '';
    }
}

function bb_mp_encontrar_colunas(PDO $pdo, array $regras_like): array {
    $conds = [];
    $params = [];
    $gi = 0;
    foreach ($regras_like as $grupo) {
        $and = [];
        $ci = 0;
        foreach ($grupo as $like) {
            $p = ":p{$gi}_{$ci}";
            $and[] = "LOWER(COLUMN_NAME) LIKE {$p}";
            $params[$p] = '%' . strtolower($like) . '%';
            $ci++;
        }
        if ($and) {
            $conds[] = '(' . implode(' AND ', $and) . ')';
        }
        $gi++;
    }
    if (!$conds) { return []; }

    $sql = "SELECT TABLE_NAME, COLUMN_NAME
            FROM information_schema.COLUMNS
            WHERE TABLE_SCHEMA = DATABASE()
              AND (" . implode(' OR ', $conds) . ")
            ORDER BY TABLE_NAME ASC, ORDINAL_POSITION ASC";

    $st = $pdo->prepare($sql);
    $st->execute($params);
    return $st->fetchAll(PDO::FETCH_ASSOC);
}

function bb_mp_tentar_buscar_no_banco(PDO $pdo, string $tipo): string {
    if ($tipo === 'publico') {
        $regras = [
            ['mercado','public'],
            ['mp','public'],
            ['public','key'],
            ['publico','mp'],
        ];
    } else {
        $regras = [
            ['mercado','access'],
            ['mercado','token'],
            ['mp','access'],
            ['access','token'],
            ['token','mp'],
        ];
    }

    $cols = bb_mp_encontrar_colunas($pdo, $regras);
    if (!$cols) { return ''; }

    foreach ($cols as $c) {
        $tb = $c['TABLE_NAME'] ?? '';
        $col = $c['COLUMN_NAME'] ?? '';
        if ($tb === '' || $col === '') { continue; }

        $val = bb_mp_buscar_primeiro_valor($pdo, $tb, $col);
        if ($val !== '') return $val;
    }

    return '';
}

if ($TOKEN_MERCADO_PAGO_PUBLICO === '') {
    $TOKEN_MERCADO_PAGO_PUBLICO = bb_mp_tentar_buscar_no_banco($pdo, 'publico');
}
if ($TOKEN_MERCADO_PAGO === '') {
    $TOKEN_MERCADO_PAGO = bb_mp_tentar_buscar_no_banco($pdo, 'access');
}

// ------------------------- Helpers -------------------------
function bb_digits($v) {
    return preg_replace('/\D+/', '', (string)$v);
}

function bb_parse_valor_monetario($v) {
    // Aceita: 10.00 | 10,00 | 1.000,00 | 1,000.00 | "R$ 10,00"
    $v = trim((string)$v);
    if ($v === '') {
        return 0.0;
    }

    // remove tudo que não for dígito, vírgula, ponto ou sinal
    $v = preg_replace('/[^0-9,\.\-]/', '', $v);

    $temVirgula = (strpos($v, ',') !== false);
    $temPonto = (strpos($v, '.') !== false);

    if ($temVirgula && $temPonto) {
        // define o separador decimal pelo último que aparecer
        $lastComma = strrpos($v, ',');
        $lastDot = strrpos($v, '.');

        if ($lastComma > $lastDot) {
            // 1.234,56 => remove pontos e troca vírgula por ponto
            $v = str_replace('.', '', $v);
            $v = str_replace(',', '.', $v);
        } else {
            // 1,234.56 => remove vírgulas
            $v = str_replace(',', '', $v);
        }
    } elseif ($temVirgula) {
        // 1234,56 => troca vírgula por ponto (e remove pontos que sejam milhar)
        $v = str_replace('.', '', $v);
        $v = str_replace(',', '.', $v);
    } else {
        // 1234.56 => mantém ponto como decimal (remove vírgula por segurança)
        $v = str_replace(',', '', $v);
    }

    return (float)$v;
}

function bb_get_conta_id_from_uri() {
    $uri = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
    if (preg_match('~/(?:conta|conta_asaas)/(\d+)(?:/)?$~', $uri, $m)) {
        return $m[1];
    }
    if (preg_match('~/receber\.php/(\d+)(?:/)?$~', $uri, $m2)) {
        return $m2[1];
    }
    return '';
}

// ------------------------- Base URL -------------------------
$bb_proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $bb_proto = ($_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : $bb_proto;
}
$bb_host = $_SERVER['HTTP_HOST'] ?? '';
$bb_base_auto = $bb_proto . '://' . $bb_host . '/';

if (!isset($url_sistema) || trim((string)$url_sistema) === '') {
    $url_sistema = $bb_base_auto;
}

$url_sistema = rtrim((string)$url_sistema, '/');
$url_site = preg_replace('~/sistema$~i', '', $url_sistema);
$url_site = rtrim((string)$url_site, '/') . '/';

$base_pagamentos = $url_site . 'pagamentos/';

// ------------------------- ID da conta -------------------------
$id_conta = isset($_GET['id_conta']) ? trim((string)$_GET['id_conta']) : '';
if ($id_conta === '') {
    $id_conta = bb_get_conta_id_from_uri();
}
$id_conta = bb_digits($id_conta);

if ($id_conta === '') {
    exit('Conta inválida.');
}

// ------------------------- Asaas -------------------------
if (isset($api_pagamento) && $api_pagamento === "Asaas") {
    echo '<script>window.location="' . bb_add_loja_param($base_pagamentos . 'conta_asaas/' . htmlspecialchars($id_conta, ENT_QUOTES, "UTF-8"), $bb_loja) . '"</script>';
    exit;
}

// ===================== BUSCA DA CONTA A RECEBER =====================
$query = $pdo->prepare("SELECT * FROM receber WHERE id = :id_conta LIMIT 1");
$query->bindValue(":id_conta", (int)$id_conta, PDO::PARAM_INT);
$query->execute();
$linha = $query->fetch(PDO::FETCH_ASSOC);

if (!$linha) {
    exit('Conta não encontrada.');
}

$pago        = $linha['pago'] ?? 'Não';
$valor       = $linha['valor'] ?? 0;
$ref_pix     = $linha['ref_pix'] ?? '';
$frequencia  = $linha['frequencia'] ?? '';
$data        = $linha['data_venc'] ?? '';
$descricao   = $linha['descricao'] ?? 'Conta';

$cliente_id  = isset($linha['pessoa']) ? $linha['pessoa'] : (isset($linha['cliente']) ? $linha['cliente'] : null);
$cliente_id  = $cliente_id !== null ? (int)$cliente_id : 0;

if ($cliente_id <= 0) {
    exit('Cliente não associado à conta.');
}

$dataF  = $data ? implode('/', array_reverse(explode('-', $data))) : '';
$valor_num = bb_parse_valor_monetario($valor); // ✅ evita virar 10.00 -> 1000.00
$valorF = number_format($valor_num, 2, ',', '.');

// Se já existe referência, consulta status na API e bloqueia se já pago
if ($ref_pix != "") {
    require(__DIR__ . '/consultar_pagamento.php');
    if (!empty($status_api) && $status_api == 'approved') {
        echo 'Essa assinatura já foi paga.';
        exit();
    }
}

// ===================== DADOS DO CLIENTE =====================
$qCli = $pdo->prepare("SELECT * FROM clientes WHERE id = :id LIMIT 1");
$qCli->execute([':id' => $cliente_id]);
$rCli = $qCli->fetch(PDO::FETCH_ASSOC);

$nome_cliente  = $rCli && isset($rCli['nome']) ? $rCli['nome'] : 'Cliente';

$cpf_cliente = '';
$email_cliente = '';

if ($rCli) {
    foreach (['cpf', 'cnpj', 'documento', 'doc', 'cpf_cnpj'] as $col) {
        if (isset($rCli[$col]) && trim((string)$rCli[$col]) !== '') {
            $cpf_cliente = (string)$rCli[$col];
            break;
        }
    }
    foreach (['email', 'email_login', 'mail'] as $col) {
        if (isset($rCli[$col]) && trim((string)$rCli[$col]) !== '') {
            $email_cliente = (string)$rCli[$col];
            break;
        }
    }
}

$nome = '';
$sobrenome = '';
$tmp = preg_split('/\s+/', trim((string)$nome_cliente));
if ($tmp && count($tmp) > 0) {
    $nome = $tmp[0];
    unset($tmp[0]);
    $sobrenome = trim(implode(' ', $tmp));
}

$doc = bb_digits($cpf_cliente);
$email = trim((string)$email_cliente);

?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Pagamento - <?= htmlspecialchars($nome_sistema ?? 'Pagamento', ENT_QUOTES, 'UTF-8'); ?></title>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>

    <?php if (empty($TOKEN_MERCADO_PAGO_PUBLICO)) { ?>
    <style>
        body{font-family:Arial, Helvetica, sans-serif;background:#111;color:#eee;margin:0;padding:18px}
        .box{max-width:720px;margin:0 auto;background:#1b1b1b;border-radius:10px;padding:18px}
        .err{color:#ff6b6b;font-weight:700;margin-top:8px}
    </style>
    <?php } else { ?>
    <script src="https://sdk.mercadopago.com/js/v2"></script>
    <?php } ?>
</head>

<body style="background:#111;color:#eee;font-family:Arial, Helvetica, sans-serif;">
<div style="max-width: 900px; margin: 0 auto; padding: 18px;">

    <h2 style="margin:0 0 8px 0;">Pagamento</h2>
    <div style="opacity:.85;margin-bottom:14px;">
        <div><b>Descrição:</b> <?= htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8'); ?></div>
        <div><b>Vencimento:</b> <?= htmlspecialchars($dataF, ENT_QUOTES, 'UTF-8'); ?></div>
        <div><b>Valor:</b> R$ <?= htmlspecialchars($valorF, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>

    <div id="mp_error" style="display:none;background:#2a0d0d;border:1px solid #7a1f1f;padding:10px;border-radius:8px;margin:10px 0;"></div>

    <div id="paymentBrick_container"></div>
    <div id="statusScreenBrick_container" style="margin-top:12px;"></div>

    <div id="form-pago" style="display:none;background:#0f2a13;border:1px solid #1f7a2b;padding:12px;border-radius:8px;margin-top:12px;">
        <b>Pagamento confirmado.</b> Obrigado!
    </div>

</div>

<?php if (empty($TOKEN_MERCADO_PAGO_PUBLICO)) { ?>
    <script>
        (function(){
            const el = document.getElementById('mp_error');
            if(el){
                el.style.display = 'block';
                el.innerText = 'Configuração do Mercado Pago não encontrada. Informe o token público nas configurações de pagamento.';
            }
        })();
    </script>
<?php } else { ?>
<script>
    const mp = new MercadoPago(<?= json_encode($TOKEN_MERCADO_PAGO_PUBLICO); ?>, { locale: 'pt-BR' });
    const bbLoja = <?= json_encode($bb_loja); ?>;
    const bricksBuilder = mp.bricks();

    function showMpError(msg){
        const el = document.getElementById('mp_error');
        if(el){
            el.style.display = 'block';
            el.innerText = msg || 'Não foi possível carregar o pagamento. Tente novamente.';
        }
    }

    const renderPaymentBrick = async (bricksBuilder) => {
        const payer = {
            firstName: <?= json_encode((string)$nome); ?>,
            lastName: <?= json_encode((string)$sobrenome); ?>,
            email: <?= json_encode((string)$email); ?>,
        };

        <?php if ($doc !== '') { ?>
        payer.identification = {
            type: <?= json_encode((strlen($doc) > 11 ? "CNPJ" : "CPF")); ?>,
            number: <?= json_encode($doc); ?>,
        };
        <?php } ?>

        const settings = {
            initialization: {
                amount: <?= json_encode((float)$valor_num); ?>,
                payer: payer,
            },
            customization: {
                visual: { style: { theme: "dark" } },
                paymentMethods: {
                    <?php if($ATIVAR_CARTAO_CREDITO=="1"){?>creditCard: "all",<?php } ?>
                    <?php if($ATIVAR_CARTAO_DEBIDO=="1"){?>debitCard: "all",<?php } ?>
                    <?php if($ATIVAR_BOLETO=="1"){?>ticket: "all",<?php } ?>
                    <?php if($ATIVAR_PIX=="1"){?>bankTransfer: "all",<?php } ?>
                    maxInstallments: 12
                },
            },
            callbacks: {
                onReady: () => {},
                onSubmit: ({ selectedPaymentMethod, formData }) => {
                    formData.external_reference = <?= json_encode((string)$id_conta); ?>;
                    formData.description = <?= json_encode((string)$descricao); ?>;
                    const id_conta = <?= json_encode((string)$id_conta); ?>;

                    return new Promise((resolve, reject) => {
                        fetch(<?= json_encode(bb_add_loja_param($base_pagamentos . "process_payment_conta.php", $bb_loja)); ?>, {
                            method: "POST",
                            headers: { "Content-Type": "application/json" },
                            body: JSON.stringify(formData),
                        })
                        .then((response) => response.json())
                        .then((response) => {
                            if (response && response.status == true) {
                                let bbUrl = <?= json_encode($base_pagamentos . "receber.php?id="); ?> + response.id + "&id_conta=" + id_conta;
                                if (bbLoja) { bbUrl += "&loja=" + encodeURIComponent(bbLoja); }
                                window.location.href = bbUrl;
                            } else {
                                showMpError((response && response.message) ? response.message : 'Falha ao iniciar pagamento.');
                            }
                            resolve();
                        })
                        .catch((error) => {
                            showMpError('Falha de comunicação ao iniciar pagamento.');
                            reject();
                        });
                    });
                },
                onError: (error) => {
                    console.error(error);
                    showMpError('Não foi possível carregar o pagamento. Verifique as configurações do Mercado Pago.');
                },
            },
        };

        try{
            window.paymentBrickController = await bricksBuilder.create("payment", "paymentBrick_container", settings);
        }catch(e){
            console.error(e);
            showMpError('Erro ao montar o formulário de pagamento.');
        }
    };

    const renderStatusScreenBrick = async (bricksBuilder) => {
        const paymentId = <?= json_encode(isset($_GET["id"]) ? (string)$_GET["id"] : ''); ?>;
        const idContaParam = <?= json_encode(isset($_GET["id_conta"]) ? (string)$_GET["id_conta"] : (string)$id_conta); ?>;

        const settings = {
            initialization: { paymentId: paymentId },
            customization: {
                visual: { hideStatusDetails: false, hideTransactionDate: false, style: { theme: 'dark' } }
            },
            callbacks: {
                onReady: () => {
                    check(paymentId, idContaParam);
                },
                onError: (error) => {
                    console.error(error);
                },
            },
        };

        try{
            window.statusScreenBrickController = await bricksBuilder.create('statusScreen', 'statusScreenBrick_container', settings);
        }catch(e){
            console.error(e);
        }
    };

    const paymentIdNow = <?= json_encode(isset($_GET["id"]) ? (string)$_GET["id"] : ''); ?>;

    if (paymentIdNow) {
        renderStatusScreenBrick(bricksBuilder);
    } else {
        renderPaymentBrick(bricksBuilder);
    }

    var redi = <?= json_encode(isset($URL_REDIRECIONAR) ? (string)$URL_REDIRECIONAR : ""); ?>;

    function check(id, id_conta) {
        var settings = {
            url: (function(){
                var u = <?= json_encode($base_pagamentos . "process_payment_conta.php?acc=check&id="); ?> + id + "&id_conta=" + id_conta;
                if (bbLoja) { u += "&loja=" + encodeURIComponent(bbLoja); }
                return u;
            })(),
            method: "GET",
            timeout: 0
        };
        $.ajax(settings).done(function(response) {
            try {
                if (response.status == "pago") {
                    $("#statusScreenBrick_container").slideUp("fast");
                    $("#form-pago").slideDown("fast");
                    if ((redi || '').trim() == "Sim") {
                        setTimeout(() => {
                            // Mantém seu padrão de redirecionamento comentado
                        }, 6000);
                    }
                } else {
                    setTimeout(() => { check(id, id_conta); }, 3000);
                }
            } catch (error) {
                alert("Erro ao localizar o pagamento, contacte o suporte.");
            }
        });
    }
</script>
<?php } ?>

<script>
    function clique() {
        var el = document.getElementById("clique_aqui");
        if (el) { el.style.display = 'none'; }
    }
</script>

</body>
</html>
