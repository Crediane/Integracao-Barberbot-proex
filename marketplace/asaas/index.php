<?php
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
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
header('Content-Type: text/html; charset=utf-8');

/**
 * Detecta o tenant (slug) em /loja/<slug>/... e garante que o conexao.php
 * conecte no banco certo.
 */
if (!function_exists('bb_clean_slug')) {
  function bb_clean_slug(string $v): string {
    $v = strtolower(trim($v));
    $v = preg_replace('/[^a-z0-9_-]/', '', $v);
    return (string)$v;
  }
}

if (!function_exists('bb_detect_slug')) {
  function bb_detect_slug(): string {
    if (!empty($_GET['loja'])) {
      $s = bb_clean_slug((string)$_GET['loja']);
      if ($s !== '') return $s;
    }
    if (!empty($_POST['loja'])) {
      $s = bb_clean_slug((string)$_POST['loja']);
      if ($s !== '') return $s;
    }
    if (!empty($_SESSION['tenant_slug'])) {
      $s = bb_clean_slug((string)$_SESSION['tenant_slug']);
      if ($s !== '') return $s;
    }

    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    if ($uri !== '' && preg_match('~^/loja/([a-z0-9_-]+)/~i', $uri, $m)) {
      $s = bb_clean_slug((string)$m[1]);
      if ($s !== '') return $s;
    }

    $ref = (string)($_SERVER['HTTP_REFERER'] ?? '');
    if ($ref !== '') {
      $path = parse_url($ref, PHP_URL_PATH);
      if (is_string($path) && preg_match('~^/loja/([a-z0-9_-]+)/~i', $path, $m)) {
        $s = bb_clean_slug((string)$m[1]);
        if ($s !== '') return $s;
      }
    }

    return '';
  }
}

if (!function_exists('bb_go')) {
  function bb_go(string $url): void {
    if (!headers_sent()) {
      header('Location: ' . $url);
    } else {
      echo '<script>window.location=' . json_encode($url) . ';</script>';
    }
    exit;
  }
}

// slug do tenant (para URLs e conexão)
$lojaSlug = bb_detect_slug();
if ($lojaSlug !== '') {
  $_GET['loja'] = $lojaSlug;
  $_SESSION['tenant_slug'] = $lojaSlug;
}

$lojaQ  = ($lojaSlug !== '') ? ('loja=' . rawurlencode($lojaSlug)) : '';
$lojaQM = ($lojaQ !== '') ? ('?' . $lojaQ) : '';
$lojaAM = ($lojaQ !== '') ? ('&' . $lojaQ) : '';

// config do pagamento + conexão (já pega o DB do tenant via conexao.php)
include __DIR__ . "/config.php";
require __DIR__ . "/../sistema/conexao.php";

// base do sistema (garante barra no final)
$base = rtrim((string)($url_sistema ?? '/'), '/') . '/';
$baseLoja = ($lojaSlug !== '') ? ($base . 'loja/' . rawurlencode($lojaSlug) . '/') : $base;

// params
$id_conta = (string)($_GET['id_conta'] ?? '');
$total    = (string)($_GET['total'] ?? '');
$id_conta = preg_replace('/[^0-9]/', '', $id_conta); // id numérico
$total    = preg_replace('/[^0-9.]/', '', $total);   // total numérico

// --- REDIRECIONAMENTOS multi-tenant (IMPORTANTE) ---
if (($api_pagamento ?? '') == "Asaas") {
  if ((float)$total > 0) {
    bb_go($baseLoja . 'pagamento_asaas/' . rawurlencode($id_conta) . '/' . rawurlencode($total));
  } else {
    bb_go($baseLoja . 'pagar_asaas/' . rawurlencode($id_conta));
  }
}

if (($opcao_pagar ?? '') == 'Não') {
  bb_go($base . "pagamentos/pagamento_aprovado.php?id_agd={$id_conta}{$lojaAM}");
}

if (($pgto_api ?? '') != 'Sim') {
  bb_go($base . "pagamentos/pagamento_aprovado.php?id_agd={$id_conta}{$lojaAM}");
}

// --- daqui pra baixo seu código original, só ajustando URLs pra manter loja ---
$query = $pdo->prepare("SELECT * FROM agendamentos_temp where id = :id_conta");
$query->bindValue(":id_conta", "$id_conta");
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);

if ($total_reg <= 0) {
  echo "Agendamento não encontrado.";
  exit;
}

$cliente = $res[0]['cliente'];
$servico = $res[0]['servico'];
$ref_pix = $res[0]['ref_pix'];
$data = $res[0]['data'];
$hora = $res[0]['hora'];
$dataF = implode('/', array_reverse(explode('-', $data)));
$horaF = date("H:i", strtotime($hora));

$query = $pdo->query("SELECT * FROM servicos where id = '$servico'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$nome_servico = $res[0]['nome'];
$valor = $res[0]['valor'];

if (($porc_servico ?? 0) > 0 && (float)$total != 100) {
  $valor = $valor * $porc_servico / 100;
}

$valorF = number_format((float)$valor, 2, ',', '.');

if ($ref_pix != "") {
  require(__DIR__ . '/consultar_pagamento.php');
  if (($status_api ?? '') == 'approved') {
    echo 'Essa conta Já foi Paga';
    exit();
  }
}

$query = $pdo->query("SELECT * FROM clientes where id = '$cliente'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$nome_cliente =  $res[0]['nome'] ?? '';
$cpf_cliente  =  '450.417.700-50';
$email_cliente = 'cliente@hotmail.com';

$token_valor = ($valor != "") ? sha1($valor) : "";
$doc = $cpf_cliente;
$doc = str_replace([",", ".", "-", "/", " "], "", $doc);

$ref = (string)($_REQUEST["ref"] ?? '');
$email = $email_cliente;
$gerarDireto = (string)($_REQUEST["gerarDireto"] ?? '');
$descricao = (string)($descricao ?? '');
$nome = $nome_cliente;
$sobrenome = (string)($_REQUEST["sobrenome"] ?? '');

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
  <?php if ($lojaSlug !== ''): ?>
    <input type="hidden" name="loja" value="<?=htmlspecialchars($lojaSlug)?>">
  <?php endif; ?>
  <button id="btn_form" type="submit"></button>
</form>

<div style="max-width: 500px; max-height: 800px; margin: 0 auto; text-align: center; margin-bottom: 20px; word-break: break-all;" >

  <div id="info_pagamento" style="text-align: center;">
    <p class="h3 font-weight-normal" style=" font-size: 18px; border-radius: 4px;">
      <span>(Serviço <?=$nome_servico;?>)</span> <span style="color:green; ">R$ <?=$valorF;?></span>
    </p>

    <?php if(($porc_servico ?? 0) > 0){ ?>
      <?php if($porc_servico != 100){ ?>
        <div style="margin-top: -8px; margin-bottom: 8px; font-size: 13px">
          <a href="<?=$baseLoja?>pagamento/<?=$id_conta?>/<?=$porc_servico?>">Pagar <?=$porc_servico;?>%</a>
          /
          <a href="<?=$baseLoja?>pagamento/<?=$id_conta?>/100">Pagar 100%</a>
        </div>
      <?php } ?>
    <?php } ?>

    <div style=" margin-bottom: 8px; font-size: 13px"><b>Agendado</b> Data:<?=$dataF;?> / Hora <?=$horaF;?></div>

    <?php if(($porc_servico ?? 0) <= 0){ ?>
      <div style="margin-bottom: 8px; font-size: 13px">
        <a onclick="clique()" href="<?=$base?>pagamentos/pagamento_aprovado.php?id_agd=<?=$id_conta?><?=$lojaAM?>">
          <b><br><font color="red" size="4">>> CLIQUE AQUI <<</font><br></b>
        </a>
        <b><br>"AGENDE JÁ E PAGUE NO LOCAL!"</b>
      </div><br>
    <?php } ?>

  </div>

  <div id="paymentBrick_container"></div>
  <div id="statusScreenBrick_container"></div>

  <div class="form-signin" id="form-pago" style="display:none;text-align: center;">
    <h1 class="h3 mb-3 font-weight-normal">Obrigado!</h1>
    <img class="mb-4" src="<?=$base;?>pagamentos/assets/check_ok.png" alt="" width="120" height="120">
    <br>
    <h5><?=$MSG_APOS_PAGAMENTO ?? '';?></h5>
    <br>
    Código do pagamento: <?php echo $_GET["id"] ?? ''; ?>
  </div>

  <?php if(($porc_servico ?? 0) > 0){ ?>
    <?php if($porc_servico == 100){ ?>
      <div style="margin-bottom: 8px; font-size: 13px">Efetue o pagamento para confirmar seu agendamento!</div>
    <?php } else { ?>
      <div style="margin-bottom: 8px; font-size: 13px">Efetue o pagamento de pelo menos <?php echo $porc_servico ?>% do valor para confirmar seu agendamento!</div>
    <?php } ?>
  <?php } ?>

</div>

<style> body{font-family:arial} </style>

<script>
var payment_check;
const mp = new MercadoPago('<?=$TOKEN_MERCADO_PAGO_PUBLICO ?? '';?>', { locale: 'pt-BR' });
const bricksBuilder = mp.bricks();

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
        address: { zipCode:'', federalUnit:'', city:'', neighborhood:'', streetName:'', streetNumber:'', complement:'' }
      },
    },
    customization: {
      visual: { style: { theme: "dark" } },
      paymentMethods: {
        <?php if(($ATIVAR_CARTAO_CREDITO ?? "0")=="1"){?>creditCard: "all",<?php } ?>
        <?php if(($ATIVAR_CARTAO_DEBIDO ?? "0")=="1"){?>debitCard: "all",<?php } ?>
        <?php if(($ATIVAR_BOLETO ?? "0")=="1"){?>ticket: "all",<?php } ?>
        <?php if(($ATIVAR_PIX ?? "0")=="1"){?>bankTransfer: "all",<?php } ?>
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
          fetch("<?=$base;?>pagamentos/process_payment.php<?=$lojaQM?>", {
            method: "POST",
            headers: { "Content-Type": "application/json" },
            body: JSON.stringify(formData),
          })
          .then((response) => response.json())
          .then((response) => {
            if(response.status==true){
              window.location.href = "<?=$base;?>pagamentos/index.php?id=" + response.id + "&id_conta=" + id_conta + "<?=$lojaAM?>";
            } else {
              alert(response.message || "Falha no pagamento.");
            }
            resolve();
          })
          .catch(() => reject());
        });
      },
      onError: (error) => { console.error(error); },
    },
  };

  window.paymentBrickController = await bricksBuilder.create("payment", "paymentBrick_container", settings);
};

const renderStatusScreenBrick = async (bricksBuilder) => {
  const settings = {
    initialization: { paymentId: '<?=($_GET["id"] ?? '')?>' },
    customization: {
      visual: { hideStatusDetails:false, hideTransactionDate:false, style:{ theme:'dark' } },
      backUrls: {}
    },
    callbacks: {
      onReady: () => { check("<?=($_GET["id"] ?? '')?>", "<?=($_GET["id_conta"] ?? '')?>"); },
      onError: (error) => {},
    },
  };
  window.statusScreenBrickController = await bricksBuilder.create('statusScreen', 'statusScreenBrick_container', settings);
};

<?php if(!empty($_GET["id"])){ ?>
  renderStatusScreenBrick(bricksBuilder);
<?php } else { ?>
  <?php if($valor==""){?>
    alert("O valor do pagamento está vazio.");
  <?php } ?>
  renderPaymentBrick(bricksBuilder);
<?php } ?>

var redi = "<?=($URL_REDIRECIONAR ?? '')?>";

function check(id, id_conta) {
  var settings = {
    url: "<?=$base;?>pagamentos/process_payment.php<?=$lojaQM?>&acc=check&id=" + id + "&id_conta=" + id_conta,
    method: "GET",
    timeout: 0
  };

  $.ajax(settings).done(function(response) {
    try {
      if (response.status == "pago") {
        $("#statusScreenBrick_container").slideUp("fast");
        $("#form-pago").slideDown("fast");
        if ((redi || "").trim() == "Sim") {
          setTimeout(() => {
            window.location = "<?=$base;?>meus-agendamentos.php<?=$lojaQM?>";
          }, 6000);
        }
      } else {
        setTimeout(() => { check(id, id_conta) }, 3000);
      }
    } catch (error) {
      alert("Erro ao localizar o pagamento, contacte com o suporte");
    }
  });
}

function clique(){
  var el = document.getElementById("clique_aqui");
  if (el) el.style.display = 'none';
}
</script>

</body>
</html>
