<?php
// ===== Sessão isolada por contexto (MASTER x TENANT) =====
// Evita que o cookie padrão (PHPSESSID) misture MASTER x TENANT.
// Regra:
// - /sistema (sem /loja/ e sem ?loja=) => BB_MASTER
// - /loja/<slug>/... OU ?loja=...      => BB_TENANT
// - demais                              => BB_SITE
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
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
//error_reporting(E_ALL);

require __DIR__ . '/../sistema/conexao.php';

$id_conta = filter_var($_GET['id_conta'], @FILTER_SANITIZE_STRING);

$query = $pdo->prepare("SELECT * FROM receber where id = :id_conta");
$query->bindValue(":id_conta", "$id_conta");
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);
$cliente = $res[0]['pessoa'];
$data = $res[0]['data_venc'];
$pago = $res[0]['pago'];
$valor = $res[0]['valor'];
$ref_pix = $res[0]['ref_pix'];
$frequencia = $res[0]['frequencia'];
$dataF = implode('/', array_reverse(explode('-', $data)));
$descricao = $res[0]['descricao'];

$valorF = number_format($valor, 2, ',', '.');

if($pago == 'Sim'){
    echo 'Essa conta já está paga!';
    exit();
}


$query = $pdo->query("SELECT * FROM clientes where id = '$cliente'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$nome_cliente =  $res[0]['nome'];
$cpf_cliente =  '450.417.700-50';
$email_cliente =  'cliente@hotmail.com';

$token_valor = ($valor!="")? sha1($valor) : "";
$doc = $cpf_cliente;
$doc =  str_replace(array(",", ".", "-", "/", " "), "", $doc);
$ref = $_REQUEST["ref"];
$email = $email_cliente;
$gerarDireto = $_REQUEST["gerarDireto"];
$descricao = $nome_servico;
$nome = $nome_cliente;
$sobrenome = $_REQUEST["sobrenome"];


?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="utf-8">
    <title>Checkout de pagamento</title>
    <meta content="width=device-width, initial-scale=1.0" name="viewport">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="config/css/style.css" rel="stylesheet">
    <style>
        :root {
            --primary-color: #4e73df;
            --secondary-color: #f8f9fc;
            --success-color: #1cc88a;
            --border-radius: 10px;
            --box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
        }

        body {
            font-family: 'Poppins', sans-serif;
            background-color: #f8f9fc;
            color: #5a5c69;
            padding: 20px 0;
        }

        .checkout-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 30px;
            background-color: #fff;
            border-radius: var(--border-radius);
            box-shadow: var(--box-shadow);
        }

        .page-title {
            color: #2e384d;
            font-weight: 600;
            margin-bottom: 30px;
            position: relative;
            padding-bottom: 10px;
        }

        .page-title:after {
            content: '';
            position: absolute;
            bottom: 0;
            left: 0;
            width: 50px;
            height: 3px;
            background-color: var(--primary-color);
        }

        .order-summary {
            background-color: var(--secondary-color);
            border-radius: var(--border-radius);
            padding: 25px;
            position: sticky;
            top: 20px;
            border: 1px solid rgba(0,0,0,0.05);
            transition: transform 0.3s ease;
        }

        .order-summary:hover {
            transform: translateY(-5px);
        }

        .summary-title {
            font-weight: 600;
            color: #2e384d;
            margin-bottom: 20px;
            font-size: 18px;
        }

        .summary-item {
            display: flex;
            justify-content: space-between;
            margin-bottom: 12px;
            font-size: 14px;
        }

        .summary-total {
            font-weight: 600;
            color: #2e384d;
            font-size: 16px;
        }

        .security-badge {
            display: flex;
            align-items: center;
            color: var(--success-color);
            font-weight: 500;
            font-size: 14px;
            margin-top: 15px;
        }

        .security-badge i {
            margin-right: 8px;
        }

        .payment-methods {
            border-radius: var(--border-radius);
            overflow: hidden;
        }

        .accordion-item {
            border: 1px solid rgba(0,0,0,0.1);
            margin-bottom: 15px;
            border-radius: var(--border-radius) !important;
            overflow: hidden;
        }

        .accordion-button {
            background-color: #fff;
            color: #2e384d;
            font-weight: 500;
            padding: 20px;
        }

        .accordion-button:not(.collapsed) {
            background-color: var(--primary-color);
            color: white;
        }

        .accordion-button:focus {
            box-shadow: none;
            border-color: rgba(0,0,0,0.1);
        }

        .accordion-body {
            padding: 25px;
            background-color: #fff;
        }

        @media (max-width: 992px) {
            .order-summary {
                position: relative;
                margin-bottom: 30px;
            }
        }
    </style>
</head>
<body>
<div class="checkout-container">
    <h2 class="page-title">Faça seu Pagamento</h2>
    

    <div class="row">
        <!-- Resumo do pedido -->
        <div class="col-lg-4 mb-4 mb-lg-0">
            <div class="order-summary">
                <h6 class="summary-title">Detalhes do pedido</h6>

                <div class="summary-item">
                    <span>Subtotal</span>
                    <span>R$ <?php echo number_format($valor, 2, ',', '.');?></span>
                </div>

              
                            
               

                <div class="mt-4">
                    <p class="mb-3 small">O pagamento leva poucos segundos para ser liberado em nossa plataforma.</p>
                    <div class="security-badge">
                        <i class="fas fa-shield-alt"></i>
                        <span>Você está em um ambiente seguro</span>
                    </div>
                </div>
            </div>
        </div>

        <!-- Métodos de pagamento -->
        <div class="col-lg-8">
            <div class="payment-methods accordion" id="accordionPayment">
                <!-- Cartão de crédito -->
                <div class="accordion-item">
                    <?php include __DIR__ . '/config/blocos/cartao.php';?>
                </div>

                <!-- PIX -->
                <div class="accordion-item">
                    <?php include __DIR__ . '/config/blocos/pix.php';?>
                </div>

                <!-- Boleto -->
                <div class="accordion-item">
                    <?php include __DIR__ . '/config/blocos/boleto.php';?>
                </div>
            </div>
        </div>
    </div>
</div>

<div id="status"></div>

<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.2.3/dist/js/bootstrap.bundle.min.js"></script>


<script>
    $(document).ready(function() {
        // Garante que o preloader esteja oculto inicialmente
        $('#preloader').hide();

        // Quando o usuário submeter o formulário
        $('#pixClienteForm').submit(function(event) {
            event.preventDefault();

            $('#formErrorMessage').hide();

            // Exibe o preloader enquanto a transação é processada
            $('#preloader').show();
            $('#myModal').show();
            $('#modalBody').html('<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Carregando...</span></div><p class="mt-2">Gerando QR Code PIX...</p></div>');

            // Coletando os dados do formulário
            var formData = $(this).serialize();

            // Enviando os dados via POST para gerar o PIX
            $.post('<?php echo $url_sistema ?>asaas_contas/config/gerar_pix.php?id=<?php echo $id_conta;?>', formData, function(data) {
                $('#modalBody').html(data);
                $('#preloader').hide();

                // Habilita o botão para fechar o modal após 5 segundos
                setTimeout(function() {
                    $('.close-modal').show();
                }, 5000);
            }).fail(function(error) {
                $('#preloader').hide();
                $('#modalBody').html('<div class="alert alert-danger">Erro ao gerar o PIX. Por favor, tente novamente.<br>Detalhes: ' + error.statusText + '</div>');
                console.error('Erro:', error);
            });
        });

        // Fecha o modal quando o usuário clica no botão de fechar
        $('.close-modal').click(function() {
            $('#myModal').hide();
        });
    });
</script>

<script>
    // Script para manipulação da modal e envio do formulário
    $(document).ready(function() {
        // Referências aos elementos da modal
        var modal = $('#myModalBoleto');
        var closeModal = $('#closeModalBoleto');

        // Quando o formulário de boleto for submetido
        $("#form-boleto").on("submit", function(event) {
            event.preventDefault();

            // Coletando os dados do formulário
            var formData = $(this).serialize();
            var respostaDiv = $("#resposta-boleto");

            // Feedback visual para o usuário
            respostaDiv.html("<p class='text-info'>Gerando boleto, aguarde...</p>");

            // Requisição AJAX para gerar o boleto
            $.ajax({
                type: "POST",
                url: "<?php echo $url_sistema;?>asaas_contas/config/processa_boleto.php?id=<?php echo $id_conta; ?>",
                data: formData,
                success: function(data) {
                    // Coloca a resposta no corpo da modal
                    $('#modalBodyBoleto').html(data);

                    // Mostra a modal
                    modal.css("display", "block");

                    // Limpa a mensagem de carregamento
                    respostaDiv.html("");

                    // Habilita o fechamento da modal após 1 minuto
                    setTimeout(function() {
                        closeModal.show();
                    }, 60000); // 60000 milissegundos = 1 minuto
                },
                error: function(xhr, status, error) {
                    respostaDiv.html("<p class='text-danger'>Erro ao gerar boleto: " + error + "</p>");
                }
            });
        });

        // Fecha a modal quando o usuário clica no botão de fechar
        closeModal.click(function() {
            modal.css("display", "none");
        });

        // Evita o fechamento da modal ao clicar fora dela
        $(window).click(function(event) {
            if (event.target.id === "myModalBoleto") {
                event.stopPropagation();
            }
        });
    });
</script>

<script>
    $(document).ready(function() {
        var tempo = 5000; //Dois segundos

        (function selectNumUsuarios() {           
            $.ajax({
                url: "<?php echo $url_sistema;?>asaas_contas/status.php?id=<?php echo $id_conta ?>",
                success: function(n) {
                    //essa é a function success, será executada se a requisição obtiver exito
                    $("#status").html(n);
                },
                complete: function() {
                    setTimeout(selectNumUsuarios, tempo);
                }
            });
        })();
    });
</script>
</body>
</html>