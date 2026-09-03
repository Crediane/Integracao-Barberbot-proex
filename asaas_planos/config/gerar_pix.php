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
require __DIR__ . '/../../sistema/conexao.php';

$data_hoje = date('Y-m-d');

$id_conta = filter_var($_GET['id'], @FILTER_SANITIZE_STRING);

$query = $pdo->prepare("SELECT * FROM assinaturas where id = :id_conta");
$query->bindValue(":id_conta", "$id_conta");
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);
$cliente = $res[0]['cliente'];
$data = $res[0]['data'];
$pago = $res[0]['pago'];
$grupo = $res[0]['grupo'];
$item = $res[0]['item'];
$valor = $res[0]['valor'];
$ref_pix = $res[0]['ref_pix'];
$frequencia = $res[0]['frequencia'];
$dataF = implode('/', array_reverse(explode('-', $data)));


$query = $pdo->query("SELECT * FROM itens_assinaturas where id = '$item'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$nome_servico =  $res[0]['nome'];

$valorF = number_format($valor, 2, ',', '.');


$query = $pdo->query("SELECT * FROM clientes where id = '$cliente'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$nome_cliente =  @$res[0]['nome'];
$cpf_cliente =  '450.417.700-50';
$email_cliente =  'cliente@hotmail.com';

$descricao = $nome_servico;
$total = $valor;

require __DIR__ . '/configApi.php';

// Campos com verificação e preenchimento fictício caso estejam vazios
$nome = !empty($res2[0]['nome']) ? $res2[0]['nome'] : 'Cliente Padrão';
$cpf = !empty($res2[0]['cpf']) ? $res2[0]['cpf'] : '768.344.690-44'; // CPF genérico (11 dígitos)
$email = !empty($res2[0]['email']) ? $res2[0]['email'] : 'cliente@email.com';
$telefone = !empty($res2[0]['telefone']) ? $res2[0]['telefone'] : '31995348111'; // Ex: número fictício com DDD
$cep = !empty($res2[0]['cep']) ? $res2[0]['cep'] : '74000000'; // CEP genérico
$rua = !empty($res2[0]['endereco']) ? $res2[0]['endereco'] : 'Rua Padrão';
$numero = !empty($res2[0]['numero']) ? $res2[0]['numero'] : '0';
$complemento = !empty($res2[0]['complemento']) ? $res2[0]['complemento'] : '';
$bairro = !empty($res2[0]['bairro']) ? $res2[0]['bairro'] : 'Centro';
$cidade = !empty($res2[0]['cidade']) ? $res2[0]['cidade'] : 'Goiânia';
$estado = !empty($res2[0]['estado']) ? $res2[0]['estado'] : 'GO';

// Cadastra o cliente na API do Asaas
$cliente_data = [
    'name' => $nome,
    'cpfCnpj' => $cpf,
    'email' => $email,
    'phone' => $telefone,
    'mobilePhone' => $telefone,
    'address' => $rua,
    'addressNumber' => $numero,
    'complement' => $complemento,
    'province' => $bairro,
    'postalCode' => $cep
];


$valor = number_format($valor, 2);

// Verifica se já tem um customer salvo no banco, se quiser pode adaptar para checar isso antes
$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL => 'https://api.asaas.com/v3/customers',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($cliente_data),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'User-agent: MeuSistema/1.0',
        'access_token: ' . $access_token
    ]
]);
$resposta_cliente = curl_exec($ch);
curl_close($ch);

$res_cliente = json_decode($resposta_cliente);
$customer_id = $res_cliente->id ?? null;

if (!$customer_id) {
    echo "<div class='alert alert-danger'><b>Erro ao cadastrar cliente na Asaas:</b> " . $resposta_cliente . "</div>";
    exit();
}

// ✅ Agora com o ID do cliente, geramos a cobrança (sem QR Code ainda)
$dados_pagamento = [
    'customer' => $customer_id,
    'billingType' => 'PIX',
    'value' => floatval($total),
    'dueDate' => $data_hoje, // ou usar $vencimento, se desejar
    'description' => $descricao
];

// Geração do pagamento via PIX
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => 'https://api.asaas.com/v3/payments',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode($dados_pagamento),
    CURLOPT_HTTPHEADER => [
        'accept: application/json',
        'content-type: application/json',
        'User-agent: MeuSistema/1.0',
        'access_token: ' . $access_token
    ]
]);

$response = curl_exec($curl);
curl_close($curl);

$resultado = json_decode($response);

// Verifica se deu erro
if (isset($resultado->errors)) {
    echo "<div class='alert alert-danger'><b>Erro ao gerar o PIX:</b><br>";
    foreach ($resultado->errors as $error) {
        echo $error->description . ' (' . $error->code . ')<br>';
    }
    echo "</div>";
    exit();
} elseif (!isset($resultado->id)) {
    echo "<div class='alert alert-danger'><b>Erro desconhecido ao gerar o PIX:</b> $response</div>";
    exit();
}

// Atualiza a conta com o ref_pix (id do pagamento)
$pdo->query("UPDATE assinaturas SET ref_pix = '$resultado->id' where id = '$id_conta'");

// Agora que temos o ID do pagamento, recuperamos o QR Code
$curl = curl_init();
curl_setopt_array($curl, [
    CURLOPT_URL => 'https://api.asaas.com/v3/payments/' . $resultado->id . '/pixQrCode',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
        'accept: application/json',
        'User-agent: MeuSistema/1.0',
        'access_token: ' . $access_token
    ]
]);

$qrCodeResponse = curl_exec($curl);
curl_close($curl);

$qrCodeResult = json_decode($qrCodeResponse);
$qrCodeImage = $qrCodeResult->pixQrCode ?? null;

// Verifica se o QR Code foi retornado corretamente
$qrCodeImage = $qrCodeResult->encodedImage ?? null;

if (!$qrCodeImage) {
    echo "<div class='alert alert-danger'><b>Erro ao recuperar o QR Code:</b> " . $qrCodeResponse . "</div>";
    exit();
}
?>

<!-- EXIBE O QRCODE DO PIX -->
<div class="row">
    <div class="col-lg-3"></div>
    <div class="col-lg-6">
        <h4 class="text-center"><?php echo $descricao ?></h4>
        <p class="text-center">Total a pagar R$ <?php echo number_format($total, 2, ',', '.');?></p>
        <hr>
        <center>
            <!-- Exibe a imagem do QR Code em Base64 -->
            <img style='display:block; width: 70%;' id='base64image' src='data:image/png;base64, <?php echo $qrCodeImage; ?>' />
            <hr>
            <button id="copyButton" class="btn btn-primary">Copia e Cola</button>
            <textarea id="pixCode" style="position: absolute; left: -9999px;"><?php echo $qrCodeResult->payload; ?></textarea>
        </center>
    </div>
</div>

<script>
    // CÓDIGO PARA COPIAR PIX
    document.getElementById('copyButton').addEventListener('click', function() {
        var textArea = document.getElementById('pixCode');
        textArea.select();
        navigator.clipboard.writeText(textArea.value).then(function() {
            alert('Código PIX copiado com sucesso!');
        }, function(err) {
            console.error('Erro ao copiar: ', err);
            alert('Não foi possível copiar o código PIX.');
        });
    });
</script>