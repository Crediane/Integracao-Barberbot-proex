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

$id_conta = $_GET['id'];
$data_hoje = date('Y-m-d');

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
$nome_cliente =  $res[0]['nome'];
$cpf_cliente =  '450.417.700-50';
$email_cliente =  'cliente@hotmail.com';

$total = $valor;
$valor_total = $valor;

require __DIR__ . '/configApi.php';

$nome       = $_POST['nome'] ?? null;
$cpf        = $_POST['cpf'] ?? null;
$cardnumber = $_POST['cardnumber'] ?? null;
$cep        = $_POST['cep'] ?? null;
$numero     = $_POST['numero'] ?? null;
$bairro      = $_POST['bairro'] ?? null;
$rua        = $_POST['rua'] ?? null;
$expdate    = $_POST['expdate'] ?? null;
$partes     = explode("/", $expdate);
$mes        = @$partes[0]; // "12"
$ano        = @$partes[1]; // "2025"

$ccv = $_POST['ccv'] ?? null;
$parcelas = $_POST['parcelas'] ?? null;
$telefone = $_POST['telefone'] ?? null;
$email = $_POST['email'] ?? null;

for ($i = $parcelas; $i <= $parcelas; $i++) {
    $valor_com_juros = floatval($total); // Substitua pelo valor que deseja parcelar
    $juros = 1.99 / 100; // Taxa de juros mensal
    $valor_parcela  = $valor_com_juros * ($juros / (1 - (1 + $juros) ** (-$i)));
    
}
if($parcelas < 2){
    $valor_parcela_ok  = floatval($total);
} else {
    $valor_parcela_ok  = number_format($valor_parcela, 2, '.', '');
}


$dados_cliente['name']      = $nome;
$dados_cliente['cpfCnpj']   = $cpf;
$curl_cliente = curl_init();
    curl_setopt_array($curl_cliente, array(
        CURLOPT_URL => 'https://api.asaas.com/v3/customers',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($dados_cliente),
    CURLOPT_HTTPHEADER => array(
        'accept: application/json',
        'content-type: application/json',
        'User-agent: 123',
        'access_token: '.$access_token
    ),
    ));
    $response_cliente = curl_exec($curl_cliente);
    $resultado_cliente = json_decode($response_cliente);
    //var_dump($response_cliente);
curl_close($curl_cliente);

//gerando transacao
$dados_transacao['billingType']    = 'CREDIT_CARD';

    $dados_transacao['creditCard']['holderName']    = $nome;
    $dados_transacao['creditCard']['number']        = $cardnumber;
    $dados_transacao['creditCard']['expiryMonth']   = $mes;
    $dados_transacao['creditCard']['expiryYear']    = $ano;
    $dados_transacao['creditCard']['ccv']           = $ccv;
    
    $dados_transacao['creditCardHolderInfo']['name']                = $nome;
    $dados_transacao['creditCardHolderInfo']['email']               = $email;
    $dados_transacao['creditCardHolderInfo']['cpfCnpj']             = $cpf;
    $dados_transacao['creditCardHolderInfo']['postalCode']          = $cep;
    $dados_transacao['creditCardHolderInfo']['addressNumber']       = $numero;
    $dados_transacao['creditCardHolderInfo']['addressComplement']   = null;
    $dados_transacao['creditCardHolderInfo']['address']             = $rua;
    $dados_transacao['creditCardHolderInfo']['province']            = $bairro;
    
    $dados_transacao['creditCardHolderInfo']['phone']               = $telefone;
    $dados_transacao['creditCardHolderInfo']['mobilePhone']         = '';
    
    $dados_transacao['customer']            = @$resultado_cliente->id;
    $dados_transacao['dueDate']             = date('Y-m-d');
    $dados_transacao['installmentCount']    = $parcelas;
    $dados_transacao['installmentValue']    = $valor_parcela_ok;
    $dados_transacao['value']               = $valor_total;
    $dados_transacao['description']         = 'Venda Produto';
    $dados_transacao['externalReference']   = $id_conta;

$curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => 'https://api.asaas.com/v3/payments/',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => json_encode($dados_transacao),
    CURLOPT_HTTPHEADER => array(
        'accept: application/json',
        'content-type: application/json',
        'User-agent: 123',
        'access_token: '.$access_token
    ),
    ));
    $response = curl_exec($curl);
    $resultado = json_decode($response, true);
    //var_dump($response);
    if(@$resultado['errors'][0]['description'] <> ''){
        echo '
        <div class="alert alert-primary" role="alert">
            '.$resultado['errors'][0]['description'].'
        </div>';
    } else {
        echo '
        <div class="alert alert-success" role="alert">
            Transação processada com sucesso, aguardando confirmação...
        </div>';
    }
    if(@$resultado['id'] <> ''){
          $pdo->query("UPDATE assinaturas SET ref_pix = '{$resultado['id']}' where id = '$id_conta'");        
    }
curl_close($curl);
?>