<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
//error_reporting(E_ALL);

/**
 * Arquivo: pagamentos/index.php
 *
 * Correção aplicada (SEM mudar a lógica):
 * - Garantir contexto correto do multi-banco (MASTER x LOJA) ANTES de carregar a conexão.
 * - Corrigir TODOS os links internos para manter o tenant:
 *      - /loja/<slug>/... (quando acesso por PATH) OU
 *      - ?loja=<slug> (fallback)
 * - Após pagamento aprovado, redirecionar AUTOMATICAMENTE para /meus-agendamentos.php
 */

require_once __DIR__ . '/config.php';

// ===== Helpers =====
function bb_montar_url(string $base, string $path, array $params = []): string {
  $base = rtrim($base, '/') . '/';
  $url = $base . ltrim($path, '/');
  if (!empty($params)) {
    $sep = (strpos($url, '?') === false) ? '?' : '&';
    $url .= $sep . http_build_query($params);
  }
  return $url;
}

// ===== Configurações já carregadas pelo bootstrap tenant =====
$URL_REDIRECIONAR = isset($URL_REDIRECIONAR) && $URL_REDIRECIONAR !== '' ? $URL_REDIRECIONAR : 'Sim';

// ===== Base URLs mantendo contexto =====
$__bb_base_url = rtrim($url_sistema ?? '', '/') . '/';
$__bb_base_tenant = $__bb_base_url;
$__bb_assets_web = $__bb_base_url . 'pagamentos/';
if (is_dir(__DIR__ . '/assets')) { $__bb_assets_web .= 'assets/'; }

// Se a base não tem /loja/ e o acesso é por PATH, adiciona prefixo /loja/<slug>/
if ($__bb_loja !== '' && $__bb_is_path_tenant && stripos($__bb_base_url, '/loja/') === false) {
  $__bb_base_tenant = $__bb_base_url . 'loja/' . $__bb_loja . '/';
}

// Se o acesso NÃO é por PATH, mantém o slug por query string
$__bb_tenant_params = [];
if ($__bb_loja !== '' && !$__bb_is_path_tenant) {
  $__bb_tenant_params['loja'] = $__bb_loja;
}

// Para chamadas internas (AJAX/MP), SEMPRE levar o slug do tenant por query-string
$__bb_tenant_params_api = $__bb_tenant_params;
if ($__bb_loja !== '') {
  $__bb_tenant_params_api['loja'] = $__bb_loja;
}

// Para links relativos no modo query-string
$__bb_loja_qs_only = ($__bb_loja !== '' && !$__bb_is_path_tenant) ? ('?loja=' . rawurlencode($__bb_loja)) : '';
$__bb_loja_qs = ($__bb_loja !== '' && !$__bb_is_path_tenant) ? ('&loja=' . rawurlencode($__bb_loja)) : '';

// ===== CORREÇÃO: Redirect pós-pagamento para /meus-agendamentos.php (mantém tenant) =====
if ($__bb_loja !== '' && $__bb_is_path_tenant) {
  $__bb_redirect_ok = $__bb_base_url . 'loja/' . $__bb_loja . '/meus-agendamentos.php';
} elseif ($__bb_loja !== '') {
  $__bb_redirect_ok = $__bb_base_url . 'meus-agendamentos.php?loja=' . rawurlencode($__bb_loja);
} else {
  $__bb_redirect_ok = $__bb_base_url . 'meus-agendamentos.php';
}

// ===== Validação de parâmetros =====
$id_conta = isset($_GET['id_conta']) ? (int)preg_replace('/\D+/', '', (string)$_GET['id_conta']) : 0;
$total_in = $_GET['total'] ?? '0';
$total_in = preg_replace('/[^0-9,\.]/', '', (string)$total_in);
$total_in = str_replace(',', '.', $total_in);
$total = (float)$total_in;

if ($id_conta <= 0) {
  echo 'Conta inválida!';
  exit();
}

// ===== CORREÇÃO: Redirect para Asaas mantendo contexto =====
if($api_pagamento == "Asaas"){
    if($total > 0){
        $url_asaas = bb_montar_url($__bb_base_tenant, 'pagamento_asaas/' . $id_conta . '/' . $total, $__bb_tenant_params);
        echo '<script>window.location="' . htmlspecialchars($url_asaas, ENT_QUOTES, 'UTF-8') . '"</script>';
    }else{
        $url_asaas = bb_montar_url($__bb_base_tenant, 'pagar_asaas/' . $id_conta, $__bb_tenant_params);
        echo '<script>window.location="' . htmlspecialchars($url_asaas, ENT_QUOTES, 'UTF-8') . '"</script>';
    }
    exit;
}

// ===== CORREÇÃO: Se não precisa pagar, vai direto para aprovado =====
if($opcao_pagar == 'Não'){
    $url_aprovado = bb_montar_url($__bb_base_tenant, 'pagamentos/pagamento_aprovado.php', array_merge(['id_agd' => $id_conta], $__bb_tenant_params));
    header("Location: " . $url_aprovado);
    exit;
}

// ===== CORREÇÃO: Se não usa API de pagamento, vai para aprovado =====
if($pgto_api != 'Sim'){
    $url_aprovado = bb_montar_url($__bb_base_tenant, 'pagamentos/pagamento_aprovado.php', array_merge(['id_agd' => $id_conta], $__bb_tenant_params));
    echo "<script>window.location='" . htmlspecialchars($url_aprovado, ENT_QUOTES, 'UTF-8') . "'</script>";
    exit;
}

// ===== Buscar dados do agendamento =====
$query = $pdo->prepare("SELECT * FROM agendamentos_temp where id = :id_conta");
$query->bindValue(":id_conta", "$id_conta");
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);

if($total_reg == 0){
    echo 'Agendamento não encontrado!';
    exit();
}

$cliente = $res[0]['cliente'];
$servico = $res[0]['servico'];
$ref_pix = $res[0]['ref_pix'];
$data = $res[0]['data'];
$hora = $res[0]['hora'];
$dataF = implode('/', array_reverse(explode('-', $data)));
$horaF = date("H:i", strtotime($hora));

// ===== Buscar dados do serviço =====
$query = $pdo->prepare("SELECT * FROM servicos where id = :id");
$query->bindValue(":id", $servico);
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$nome_servico = isset($res[0]['nome']) ? $res[0]['nome'] : '';
$valor = isset($res[0]['valor']) ? $res[0]['valor'] : 0;

if($porc_servico > 0 and $total != 100){
    $valor = $valor * $porc_servico / 100;
}

$valorF = number_format($valor, 2, ',', '.');

// ===== CORREÇÃO: Verificar se já foi pago =====
if($ref_pix != ""){
     require __DIR__ . '/consultar_pagamento.php';
     if(isset($status_api) && $status_api == 'approved'){
         echo 'Essa conta já foi paga!';
         exit();
     }
}

// ===== Buscar dados do cliente =====
$query = $pdo->prepare("SELECT * FROM clientes where id = :id");
$query->bindValue(":id", $cliente);
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$nome_cliente = isset($res[0]['nome']) ? $res[0]['nome'] : '';
$cpf_cliente = isset($res[0]['cpf']) ? $res[0]['cpf'] : '450.417.700-50';
$email_cliente = isset($res[0]['email']) ? $res[0]['email'] : 'cliente@hotmail.com';

$token_valor = ($valor!="")? sha1($valor) : "";
$doc = $cpf_cliente;
$doc =  str_replace(array(",", ".", "-", "/", " "), "", $doc);
$ref = $_REQUEST["ref"] ?? '';
$email = $email_cliente;
$gerarDireto = $_REQUEST["gerarDireto"] ?? '';
$descricao = isset($descricao) ? $descricao : '';
$nome = $nome_cliente;
$sobrenome = $_REQUEST["sobrenome"] ?? '';

?>
<html lang="pt-br">
<head>
    <title>Pagamento</title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <script src="https://sdk.mercadopago.com/js/v2"></script>
    <link href="<?php echo htmlspecialchars($__bb_assets_web . 'bootstrap.min.css', ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
    <link href="<?php echo htmlspecialchars($__bb_assets_web . 'signin.css', ENT_QUOTES, 'UTF-8'); ?>" rel="stylesheet">
    <script src="<?php echo htmlspecialchars($__bb_assets_web . 'jquery-3.6.4.min.js', ENT_QUOTES, 'UTF-8'); ?>"></script>
</head>
<body class="text-center">

<!-- CORREÇÃO: Form aponta para agendamento_confirmado mantendo contexto -->
<form action="<?php echo htmlspecialchars(bb_montar_url($__bb_base_tenant, 'agendamento_confirmado', $__bb_tenant_params), ENT_QUOTES, 'UTF-8'); ?>" method="post" style="display:none">
    <input type="hidden" name="id" value="<?php echo htmlspecialchars($id_conta, ENT_QUOTES, 'UTF-8'); ?>">
    <input type="hidden" name="enviar" value="Sim">
    <button id="btn_form" type="submit"></button>
</form>

<div style="max-width: 500px; max-height: 800px; margin: 0 auto; text-align: center; margin-bottom: 20px; word-break: break-all;">

<div id="info_pagamento" style="text-align: center;">
        <p class="h3 font-weight-normal" style="font-size: 18px; border-radius: 4px;">
            <span>(Serviço <?php echo htmlspecialchars($nome_servico, ENT_QUOTES, 'UTF-8'); ?>)</span> 
            <span style="color:green;">R$ <?php echo htmlspecialchars($valorF, ENT_QUOTES, 'UTF-8'); ?></span>
        </p>

        <?php if($porc_servico > 0){ ?>
         <?php if($porc_servico != 100){ ?>
         <!-- CORREÇÃO: Links de porcentagem mantendo contexto -->
         <div style="margin-top: -8px; margin-bottom: 8px; font-size: 13px">
            <a href="<?php echo htmlspecialchars(bb_montar_url($__bb_base_tenant, 'pagamentos/index.php', array_merge(['id_conta' => $id_conta, 'total' => $porc_servico], $__bb_tenant_params)), ENT_QUOTES, 'UTF-8'); ?>">Pagar <?php echo htmlspecialchars($porc_servico, ENT_QUOTES, 'UTF-8'); ?>%</a> / 
            <a href="<?php echo htmlspecialchars(bb_montar_url($__bb_base_tenant, 'pagamentos/index.php', array_merge(['id_conta' => $id_conta, 'total' => 100], $__bb_tenant_params)), ENT_QUOTES, 'UTF-8'); ?>">Pagar 100%</a>
         </div>
        <?php } } ?>

        <div style="margin-bottom: 8px; font-size: 13px">
            <b>Agendado</b> Data: <?php echo htmlspecialchars($dataF, ENT_QUOTES, 'UTF-8'); ?> / 
            Hora: <?php echo htmlspecialchars($horaF, ENT_QUOTES, 'UTF-8'); ?>
        </div>

        <?php if($porc_servico <= 0){ ?>
  <!-- CORREÇÃO: Link "CLIQUE AQUI" vai direto para pagamento_aprovado -->
  <div style="margin-bottom: 8px; font-size: 13px">
    <a onclick="clique()" href="<?php echo htmlspecialchars(bb_montar_url($__bb_base_tenant, 'pagamentos/pagamento_aprovado.php', array_merge(['id_agd' => $id_conta], $__bb_tenant_params)), ENT_QUOTES, 'UTF-8'); ?>">
      <b>
        <br>
        <font color="red" size="4">>> CLIQUE AQUI <<</font>
        <br>
      </b>
    </a>
    <b><br>"AGENDE JÁ E PAGUE NO LOCAL!"</b>
  </div><br>
<?php } ?>

</div>

<div id="paymentBrick_container"></div>
<div id="statusScreenBrick_container"></div>

<!-- CORREÇÃO: Mensagem após pagamento aprovado -->
<div class="form-signin" id="form-pago" style="display:none;text-align: center;">
    <h1 class="h3 mb-3 font-weight-normal">Obrigado!</h1>
    <img class="mb-4" src="<?php echo htmlspecialchars($__bb_base_tenant . 'pagamentos/assets/check_ok.png', ENT_QUOTES, 'UTF-8'); ?>" alt="" width="120" height="120">
    <br>
    <h5><?php echo htmlspecialchars($MSG_APOS_PAGAMENTO ?? 'Pagamento confirmado!', ENT_QUOTES, 'UTF-8'); ?></h5>
    <br>
    Código do pagamento: <?php echo htmlspecialchars($_GET["id"] ?? '', ENT_QUOTES, 'UTF-8'); ?>
</div>

<?php if($porc_servico > 0){ ?>
    <?php if($porc_servico == 100){ ?>
 <div style="margin-bottom: 8px; font-size: 13px">Efetue o pagamento para confirmar seu agendamento!</div>
 <?php }else{ ?>
     <div style="margin-bottom: 8px; font-size: 13px">Efetue o pagamento de pelo menos <?php echo htmlspecialchars($porc_servico, ENT_QUOTES, 'UTF-8'); ?>% do valor para confirmar seu agendamento!</div>
<?php } } ?>

</div>

<style>
    body{font-family:arial}
</style>

<script>
    var payment_check;
    const mp = new MercadoPago('<?php echo htmlspecialchars($TOKEN_MERCADO_PAGO_PUBLICO ?? '', ENT_QUOTES, 'UTF-8'); ?>', {
        locale: 'pt-BR'
    });
    const bricksBuilder = mp.bricks();

    // ===== CORREÇÃO: URLs corrigidas para manter o contexto (tenant/master) =====
    const URL_PROCESS_BASE = <?php echo json_encode(bb_montar_url($__bb_base_url, 'pagamentos/process_payment.php', $__bb_tenant_params_api)); ?>;
    const URL_INDEX_BASE = <?php echo json_encode(bb_montar_url($__bb_base_url, 'pagamentos/index.php', $__bb_tenant_params_api)); ?>;
    const URL_MEUS_AGENDAMENTOS = <?php echo json_encode($__bb_redirect_ok); ?>;

    function bb_add_params(url, params) {
        const u = new URL(url, window.location.origin);
        Object.keys(params || {}).forEach((k) => {
            if (params[k] !== undefined && params[k] !== null && String(params[k]).trim() !== '') {
                u.searchParams.set(k, params[k]);
            }
        });
        return u.toString();
    }

    const renderPaymentBrick = async (bricksBuilder) => {
        const settings = {
            initialization: {
                amount: '<?php echo htmlspecialchars($valor, ENT_QUOTES, 'UTF-8'); ?>',
                payer: {
                    firstName: "<?php echo htmlspecialchars($nome, ENT_QUOTES, 'UTF-8'); ?>",
                    lastName: "<?php echo htmlspecialchars($sobrenome, ENT_QUOTES, 'UTF-8'); ?>",
                    email: "<?php echo htmlspecialchars($email, ENT_QUOTES, 'UTF-8'); ?>",
                    identification: {
                        type: '<?php echo (strlen($doc)>11? "CNPJ" : "CPF"); ?>',
                        number: '<?php echo htmlspecialchars($doc, ENT_QUOTES, 'UTF-8'); ?>',
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
                    style: {
                        theme: "dark",
                    },
                },
                paymentMethods: {
                    <?php if(isset($ATIVAR_CARTAO_CREDITO) && $ATIVAR_CARTAO_CREDITO=="1"){?>creditCard: "all",<?php } ?>
                    <?php if(isset($ATIVAR_CARTAO_DEBIDO) && $ATIVAR_CARTAO_DEBIDO=="1"){?>debitCard: "all",<?php } ?>
                    <?php if(isset($ATIVAR_BOLETO) && $ATIVAR_BOLETO=="1"){?>ticket: "all",<?php } ?>
                    <?php if(isset($ATIVAR_PIX) && $ATIVAR_PIX=="1"){?>bankTransfer: "all",<?php } ?>
                    maxInstallments: 12
                },
            },
            callbacks: {
                onReady: () => {
                },
                onSubmit: ({ selectedPaymentMethod, formData }) => {

                    formData.external_reference = '<?php echo htmlspecialchars($ref, ENT_QUOTES, 'UTF-8'); ?>';
                    formData.description = '<?php echo htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8'); ?>';
                    var id_conta = '<?php echo htmlspecialchars($id_conta, ENT_QUOTES, 'UTF-8'); ?>';

                    return new Promise((resolve, reject) => {
                        fetch(URL_PROCESS_BASE, {
                            method: "POST",
                            headers: {
                                "Content-Type": "application/json",
                            },
                            body: JSON.stringify(formData),
                        })
                        .then((response) => response.json())
                        .then((response) => {
            // receber o resultado do pagamento
                            if(response.status==true){
                                window.location.href = bb_add_params(window.location.href, {id: response.id, id_conta: id_conta});
                            }
                            if(response.status!=true){
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
                paymentId: '<?php echo htmlspecialchars($_GET["id"] ?? '', ENT_QUOTES, 'UTF-8'); ?>',
            },
            customization: {
                visual: {
                    hideStatusDetails: false,
                    hideTransactionDate: false,
                    style: {
                        theme: 'dark', // 'default' | 'dark' | 'bootstrap' | 'flat'
                    }
                },
                backUrls: {
                    //'error': '<http://<your domain>/error>',
                    //'return': '<http://<your domain>/homepage>'
                }
            },
            callbacks: {
                onReady: () => {
                    check("<?php echo htmlspecialchars($_GET["id"] ?? '', ENT_QUOTES, 'UTF-8'); ?>", "<?php echo htmlspecialchars($_GET["id_conta"] ?? '', ENT_QUOTES, 'UTF-8'); ?>");
                },
                onError: (error) => {
                },
            },
        };
        window.statusScreenBrickController = await bricksBuilder.create('statusScreen', 'statusScreenBrick_container', settings);
    };

    <?php if(isset($_GET["id"]) && $_GET["id"] != ""){ ?>
        renderStatusScreenBrick(bricksBuilder);
    <?php } else { ?>
        <?php if($valor==""){ ?>
            alert("O valor do pagamento está vazio.");
        <?php } ?>
        renderPaymentBrick(bricksBuilder);
    <?php } ?>
var redi = "<?php echo htmlspecialchars($URL_REDIRECIONAR, ENT_QUOTES, 'UTF-8'); ?>";
var checkInterval = null;

// ===== CORREÇÃO: Após confirmar pagamento, redireciona automaticamente =====
function check(id, id_conta) {
    console.log("=== VERIFICANDO PAGAMENTO ===");
    console.log("ID:", id, "Conta:", id_conta);

    var settings = {
        "url": bb_add_params(URL_PROCESS_BASE, {acc: "check", id: id, id_conta: id_conta}),
        "method": "GET",
        "timeout": 0
    };

    $.ajax(settings).done(function(response) {
        console.log("✅ Resposta:", response);

        try {
            if (response.status == "pago") {
                console.log("🎉 PAGAMENTO APROVADO!");

                // CRÍTICO: Para o loop
                if (checkInterval) {
                    clearTimeout(checkInterval);
                    checkInterval = null;
                }

                // Fecha o QR Code
                $("#statusScreenBrick_container").slideUp("fast");
                $("#info_pagamento").slideUp("fast");

                // Mostra mensagem de sucesso
                $("#form-pago").slideDown("fast");

                // Redireciona automaticamente
                if (redi.trim() == "Sim") {
                    console.log("🚀 Redirecionando...");
                    setTimeout(() => {
                        window.location.href = URL_MEUS_AGENDAMENTOS;
                    }, 1500);
                }

            } else {
                console.log("⏳ Pendente:", response.status);
                checkInterval = setTimeout(() => {
                    check(id, id_conta)
                }, 3000);
            }
        } catch (error) {
            console.error("❌ ERRO:", error);
            alert("Erro ao localizar o pagamento");
        }
    }).fail(function(xhr, status, error) {
        console.error("❌ AJAX falhou:", error);
        checkInterval = setTimeout(() => {
            check(id, id_conta)
        }, 5000);
    });
}

</script>

<script type="text/javascript">
    function clique(){
        var elem = document.getElementById("clique_aqui");
        if(elem) elem.style.display = 'none';
    }
</script>

</body>
</html>
