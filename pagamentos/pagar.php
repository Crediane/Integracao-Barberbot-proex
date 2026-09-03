<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
//error_reporting(E_ALL);

/**
 * Arquivo: pagamentos/pagar.php
 *
 * Correção aplicada (SEM mudar a lógica de pagamento):
 * - Garantir contexto correto do multi-banco (MASTER x LOJA) ANTES de carregar a conexão.
 * - Corrigir TODOS os links internos (processamento / verificação / redirect) para respeitar:
 *      - /loja/<slug>/... (tenant) OU
 *      - ?loja=<slug> (fallback)
 *
 * Mantido:
 * - Fluxo de pagamento Mercado Pago / verificação
 * - Regras e telas existentes
 */

require_once __DIR__ . '/config.php';

// ===== Helpers para montar URLs mantendo o contexto =====
function bb_montar_url($base, array $params = []) {
    if (empty($params)) return $base;
    $sep = (strpos($base, '?') === false) ? '?' : '&';
    return $base . $sep . http_build_query($params);
}

$BASE_URL = rtrim($url_sistema ?? '', '/') . '/';

// Prefixo tenant somente quando o acesso já está em /loja/<slug>/...
$BASE_TENANT_PREFIX = ($loja !== '' && $__bb_is_path_tenant) ? ($BASE_URL . 'loja/' . $loja . '/') : $BASE_URL;

// Se NÃO está em /loja/<slug>/..., garante o slug via query string
$TENANT_QUERY = ($loja !== '' && ! $__bb_is_path_tenant) ? ['loja' => $loja] : [];

// ===== Entrada principal =====
$id_conta = isset($_GET['id_conta']) ? (int)$_GET['id_conta'] : 0;
if ($id_conta <= 0) {
    echo 'Conta inválida!';
    exit();
}

// Se usa Asaas, mantém o redirecionamento, apenas respeitando contexto tenant
if (isset($api_pagamento) && $api_pagamento == "Asaas") {
    echo '<script>window.location="' . $BASE_TENANT_PREFIX . 'plano_asaas/' . $id_conta . '"</script>';
    exit();
}

// ===== Buscar assinatura =====
$query = $pdo->prepare("SELECT * FROM assinaturas where id = :id_conta");
$query->bindValue(":id_conta", $id_conta, PDO::PARAM_INT);
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);

if ($total_reg <= 0) {
    echo 'Assinatura não encontrada!';
    exit();
}

$cliente = $res[0]['cliente'];
$data = $res[0]['data'];
$pago = $res[0]['pago'];
$grupo = $res[0]['grupo'];
$item = $res[0]['item'];
$valor = $res[0]['valor'];
$ref_pix = $res[0]['ref_pix'];
$frequencia = $res[0]['frequencia'];

$dataF = '';
if (!empty($data) && strpos($data, '-') !== false) {
    $dataF = implode('/', array_reverse(explode('-', $data)));
}

$query2 = $pdo->prepare("SELECT * FROM itens_assinaturas where id = :id_item");
$query2->bindValue(":id_item", $item);
$query2->execute();
$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
$nome_servico = isset($res2[0]['nome']) ? $res2[0]['nome'] : '';

$valorF = number_format((float)$valor, 2, ',', '.');

// Se já tem referência, consultar e bloquear se já estiver pago (lógica mantida)
if (!empty($ref_pix)) {
    require __DIR__ . '/consultar_pagamento.php';
    if (isset($status_api) && $status_api == 'approved') {
        echo 'Essa Assinatura Já foi Paga';
        exit();
    }
}

// Buscar cliente
$query3 = $pdo->prepare("SELECT * FROM clientes where id = :id_cliente");
$query3->bindValue(":id_cliente", $cliente);
$query3->execute();
$res3 = $query3->fetchAll(PDO::FETCH_ASSOC);

$nome_cliente = isset($res3[0]['nome']) ? $res3[0]['nome'] : '';
$cpf_cliente = '450.417.700-50';
$email_cliente = 'cliente@hotmail.com';

// ===== Variáveis usadas no JS (mantém padrão do arquivo) =====
$token_valor = ($valor != "") ? sha1($valor) : "";
$doc = $cpf_cliente;
$doc = str_replace(array(",", ".", "-", "/", " "), "", $doc);

$ref = $_REQUEST["ref"] ?? '';
$email = $email_cliente;
$gerarDireto = $_REQUEST["gerarDireto"] ?? '';
$descricao = ''; // Mantido conforme o arquivo (antes estava indefinido e retornava vazio)
$nome = $nome_cliente;
$sobrenome = $_REQUEST["sobrenome"] ?? '';

$payment_id = $_GET["id"] ?? '';

/**
 * URLs corrigidas para respeitar multi-banco:
 * - process_payment_assinatura.php (POST e CHECK)
 * - redirect para pagar.php (tela do status)
 */
$URL_PROCESSAR = bb_montar_url($BASE_TENANT_PREFIX . 'pagamentos/process_payment_assinatura.php', $TENANT_QUERY);
$URL_PAGAR_BASE = bb_montar_url($BASE_TENANT_PREFIX . 'pagamentos/pagar.php', $TENANT_QUERY);
?>
<html lang="pt-br">
<head>
    <title>Pagamento</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <script src="https://sdk.mercadopago.com/js/v2"></script>
    <link href="./assets/bootstrap.min.css" rel="stylesheet">
    <link href="./assets/signin.css" rel="stylesheet">
    <script src="./assets/jquery-3.6.4.min.js"></script>
</head>
<body class="text-center">

<form action="agendamento_confirmado" method="post" style="display:none">
    <input type="hidden" name="id" value="<?=$id_conta;?>">
    <input type="hidden" name="enviar" value="Sim">
    <button id="btn_form" type="submit"></button>
</form>

<div style="max-width: 500px; max-height: 800px; margin: 0 auto; text-align: center; margin-bottom: 20px; word-break: break-all;">

    <div id="info_pagamento" style="text-align: center;">
        <p class="h3 font-weight-normal" style="font-size: 18px; border-radius: 4px;">
            <span>(Plano <?=$nome_servico;?>)</span>
            <span style="color:green;">R$ <?=$valorF;?></span>
        </p>
    </div>

    <div id="paymentBrick_container"></div>
    <div id="statusScreenBrick_container"></div>

    <div class="form-signin" id="form-pago" style="display:none;text-align: center;">
        <h1 class="h3 mb-3 font-weight-normal">Obrigado!</h1>
        <img class="mb-4" src="<?=$url_sistema;?>pagamentos/assets/check_ok.png" alt="" width="120" height="120">
        <br>
        <h5><?=$MSG_APOS_PAGAMENTO;?></h5>
        <br>
        Código do pagamento: <?php echo htmlspecialchars((string)$payment_id); ?>
    </div>

    <div style="margin-bottom: 8px; font-size: 13px">Efetue o pagamento para confirmar a compra do seu plano!</div>

</div>

<style>
    body{font-family:arial}
</style>

<script>
    var payment_check;
    const mp = new MercadoPago('<?=$TOKEN_MERCADO_PAGO_PUBLICO;?>', { locale: 'pt-BR' });
    const bricksBuilder = mp.bricks();

    // Base URL correta (tenant ou master)
    const URL_PROCESSAR = "<?=htmlspecialchars($URL_PROCESSAR, ENT_QUOTES);?>";
    const URL_PAGAR_BASE = "<?=htmlspecialchars($URL_PAGAR_BASE, ENT_QUOTES);?>";
    const LOJA_SLUG = "<?=htmlspecialchars($loja, ENT_QUOTES);?>";
    const USAR_LOJA_QUERY = <?=($loja !== '' && ! $__bb_is_path_tenant) ? 'true' : 'false';?>;

    function bbAppendParams(url, params){
        const u = new URL(url, window.location.origin);
        Object.keys(params).forEach((k) => {
            if (params[k] !== undefined && params[k] !== null && String(params[k]).trim() !== '') {
                u.searchParams.set(k, params[k]);
            }
        });
        return u.toString();
    }

    const renderPaymentBrick = async (bricksBuilder) => {
        const settings = {
            initialization: {
                amount: '<?=$valor;?>',
                payer: {
                    firstName: "<?=$nome;?>",
                    lastName: "<?=$sobrenome;?>",
                    email: "<?=$email;?>",
                    identification: {
                        type: '<?=(strlen($doc)>11? "CNPJ" : "CPF");?>',
                        number: '<?=$doc;?>',
                    },
                    address: {
                        zipCode: '',
                        federalUnit: '',
                        city: '',
                        neighborhood: '',
                        streetName: '',
                        streetNumber: '',
                        complement: '',
                    }
                },
            },
            customization: {
                visual: {
                    style: { theme: "dark" },
                },
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

                    formData.external_reference = '<?=$ref;?>';
                    formData.description = '<?=$descricao;?>';
                    var id_conta = '<?=$id_conta;?>';

                    return new Promise((resolve, reject) => {
                        fetch(URL_PROCESSAR, {
                            method: "POST",
                            headers: { "Content-Type": "application/json" },
                            body: JSON.stringify(formData),
                        })
                        .then((response) => response.json())
                        .then((response) => {
                            // receber o resultado do pagamento
                            if (response.status == true) {
                                // Redirect mantendo contexto (tenant/master)
                                let destino = bbAppendParams(URL_PAGAR_BASE, {
                                    id: response.id,
                                    id_conta: id_conta
                                });

                                // Fallback: quando tenant está por query string
                                if (USAR_LOJA_QUERY && LOJA_SLUG) {
                                    destino = bbAppendParams(destino, { loja: LOJA_SLUG });
                                }

                                window.location.href = destino;
                            }
                            if (response.status != true) {
                                alert(response.message);
                            }
                            resolve();
                        })
                        .catch((error) => {
                            reject();
                        });
                    });
                },
                onError: (error) => {
                    console.error(error);
                },
            },
        };

        window.paymentBrickController = await bricksBuilder.create(
            "payment",
            "paymentBrick_container",
            settings
        );
    };

    const renderStatusScreenBrick = async (bricksBuilder) => {
        const settings = {
            initialization: {
                paymentId: '<?=htmlspecialchars((string)$payment_id, ENT_QUOTES);?>',
            },
            customization: {
                visual: {
                    hideStatusDetails: false,
                    hideTransactionDate: false,
                    style: { theme: 'dark' }
                },
                backUrls: {
                    //'error': '<http://<your domain>/error>',
                    //'return': '<http://<your domain>/homepage>'
                }
            },
            callbacks: {
                onReady: () => {
                    check('<?=htmlspecialchars((string)$payment_id, ENT_QUOTES);?>', '<?=htmlspecialchars((string)$id_conta, ENT_QUOTES);?>');
                },
                onError: (error) => {},
            },
        };
        window.statusScreenBrickController = await bricksBuilder.create('statusScreen', 'statusScreenBrick_container', settings);
    };

    <?php if(!empty($payment_id)){ ?>
        renderStatusScreenBrick(bricksBuilder);
    <?php } else { ?>
        <?php if($valor==""){?>
            alert("O valor do pagamento está vazio.");
        <?php } ?>
        renderPaymentBrick(bricksBuilder);
    <?php } ?>

    var redi = "<?=$URL_REDIRECIONAR;?>";

    function check(id, id_conta) {
        // Monta URL de check mantendo contexto (tenant/master)
        let urlCheck = bbAppendParams(URL_PROCESSAR, { acc: 'check', id: id, id_conta: id_conta });
        if (USAR_LOJA_QUERY && LOJA_SLUG) {
            urlCheck = bbAppendParams(urlCheck, { loja: LOJA_SLUG });
        }

        var settings = {
            "url": urlCheck,
            "method": "GET",
            "timeout": 0
        };

        $.ajax(settings).done(function(response) {
            try {
                if (response.status == "pago") {
                    $("#statusScreenBrick_container").slideUp("fast");
                    $("#form-pago").slideDown("fast");
                    if (redi.trim() == "Sim") {
                        setTimeout(() => {
                            //window.location = "../meus-agendamentos.php";
                            //$("#btn_form").click();
                        }, 6000);
                    }
                } else {
                    setTimeout(() => {
                        check(id, id_conta);
                    }, 3000);
                }
            } catch (error) {
                alert("Erro ao localizar o pagamento, contacte com o suporte");
            }
        });
    }
</script>

<script type="text/javascript">
    function clique(){
        document.getElementById("clique_aqui").style.display = 'none';
    }
</script>

</body>
</html>
