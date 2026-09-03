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


$query = $pdo->query("SELECT * FROM clientes where id = '$cliente'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$nome_cliente =  $res[0]['nome'];
$cpf_cliente =  '450.417.700-50';
$email_cliente =  'cliente@hotmail.com';



$total = $valor;
$valor_total = $valor;
$valorF = number_format($valor, 2, ',', '.');



// Recebe dados do cliente (POST)
$nome = $_POST['nome'] ?? null;
$cpf = preg_replace('/[^0-9]/', '', $_POST['cpf'] ?? '');
$email = $_POST['email'] ?? null;
$telefone = preg_replace('/[^0-9]/', '', $_POST['telefone'] ?? '');
$cep = preg_replace('/[^0-9]/', '', $_POST['cep'] ?? '');
$rua = $_POST['rua'] ?? null;
$numero = $_POST['numero'] ?? null;
$bairro = $_POST['bairro'] ?? null;
$cidade = $_POST['cidade'] ?? null;
$estado = $_POST['estado'] ?? null;
$complemento = $_POST['complemento'] ?? null;

// Vencimento em 3 dias (padrão)
//$data_vencimento = date('Y-m-d', strtotime("+3 days"));
$data_vencimento = date('Y-m-d');

require __DIR__ . '/configApi.php';

// Criar cliente na API Asaas
$dados_cliente = [
    'name' => $nome,
    'cpfCnpj' => $cpf,
    'email' => $email,
    'phone' => $telefone,
    'postalCode' => $cep,
    'address' => $rua,
    'addressNumber' => $numero,
    'complement' => $complemento,
    'province' => $bairro,
    'city' => $cidade,
    'state' => $estado
    
];


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
$resultado_cliente = json_decode($response_cliente, true);
curl_close($curl_cliente);

// Verificar se houve erro na criação do cliente
if (isset($resultado_cliente['errors'])) {
    echo '<div class="alert alert-danger" role="alert">';
    echo 'Erro ao cadastrar cliente: ' . $resultado_cliente['errors'][0]['description'];
    echo '</div>';
    exit;
}

// Verificar se houve erro na criação do cliente ou se não retornou o ID
if (!$resultado_cliente || !isset($resultado_cliente['id'])) {
    echo '<div class="alert alert-danger" role="alert">';
    echo 'Erro ao cadastrar cliente: ';
    echo '<pre>';
    print_r($resultado_cliente); // Mostra o erro completo
    echo '</pre>';
    echo '</div>';

    echo '<h5>Dados enviados para o Asaas (Cliente):</h5>';
    echo '<pre>';
    print_r($dados_cliente);
    echo '</pre>';

    
    exit;
}

// Criar boleto na API Asaas
$dados_boleto = [
    'customer' => $resultado_cliente['id'],
    'billingType' => 'BOLETO',
    'value' => floatval($valor_total),
    'dueDate' => $data_vencimento,
    'description' => 'Pedido #' . $id_conta,
    'externalReference' => $id_conta,    
    'daysAfterDueDateToRegistrationCancellation' => 3 // Cancela o registro após 3 dias do vencimento
];

$curl = curl_init();
curl_setopt_array($curl, array(
    CURLOPT_URL => 'https://api.asaas.com/v3/payments',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode($dados_boleto),
    CURLOPT_HTTPHEADER => array(
        'accept: application/json',
        'content-type: application/json',
        'User-agent: 123',
        'access_token: '.$access_token
    ),
));
$response = curl_exec($curl);
$resultado = json_decode($response, true);
curl_close($curl);

// Início da estrutura da modal
echo '<div class="modal-content-inner">';

// Verificar se houve erro na criação do boleto
if (isset($resultado['errors'])) {
    echo '<div class="alert alert-danger mb-4" role="alert">';
    echo 'Erro ao gerar boleto: ' . $resultado['errors'][0]['description'];
    echo '</div>';

    echo '<div class="text-center mt-3">';
    echo '<button type="button" class="btn btn-secondary" onclick="$(\'#myModalBoleto\').css(\'display\', \'none\');">Fechar</button>';
    echo '</div>';
} else {
    // Atualizar o registro com o ID da transação
    if (isset($resultado['id'])) {
        $pdo->query("UPDATE receber SET ref_pix = '{$resultado['id']}' where id = '$id_conta'");

        // Cabeçalho com informações principais
        echo '<div class="text-center mb-4">';
        echo '<h4>Boleto Gerado com Sucesso</h4>';
        echo '<p class="lead">Pedido #' . $id_conta . '</p>';
        echo '</div>';

        // Informações do boleto em cards
        echo '<div class="card mb-4">';
        echo '<div class="card-body">';
        echo '<div class="row">';
        echo '<div class="col-md-6">';
        echo '<p><strong>Valor:</strong> <span class="text-primary">R$ ' . number_format($valor_total, 2, ',', '.') . '</span></p>';
        echo '<p><strong>Vencimento:</strong> ' . date('d/m/Y', strtotime($data_vencimento)) . '</p>';
        echo '</div>';

      
        echo '</div>'; // Fim da row
        echo '</div>'; // Fim do card-body
        echo '</div>'; // Fim do card

        // Código de barras do boleto
        if (isset($resultado['bankSlipBarCode'])) {
            echo '<div class="card mb-4">';
            echo '<div class="card-header bg-light">Código de Barras</div>';
            echo '<div class="card-body">';
            echo '<p class="text-monospace text-break" style="word-wrap: break-word;">' . $resultado['bankSlipBarCode'] . '</p>';
            echo '</div>';
            echo '</div>';
        }

        // Botões de ação
        echo '<div class="d-flex justify-content-center gap-2 mb-4">';

        // Link para visualizar o boleto
        if (isset($resultado['bankSlipUrl'])) {
            echo '<a href="' . $resultado['bankSlipUrl'] . '" target="_blank" class="btn btn-primary mx-2">';
            echo '<i class="fas fa-file-invoice"></i> Visualizar Boleto';
            echo '</a>';

            echo '<a href="' . $resultado['bankSlipUrl'] . '" download class="btn btn-outline-primary mx-2">';
            echo '<i class="fas fa-download"></i> Download PDF';
            echo '</a>';
        }

        echo '</div>';

        // Instruções para pagamento
        echo '<div class="card mb-3">';
        echo '<div class="card-header bg-light">Instruções para Pagamento</div>';
        echo '<div class="card-body">';
        echo '<ul class="mb-0">';
        echo '<li>O boleto pode ser pago em qualquer banco até a data de vencimento.</li>';
        echo '<li>Após o pagamento, a confirmação pode levar até 3 dias úteis.</li>';
        echo '<li>Pagamentos usando o QRCODE do boleto, a confirmação é imediata.</li>';
        echo '</ul>';
        echo '</div>';
        echo '</div>';

        // Botão de fechar na parte inferior
        echo '<div class="text-center mt-4">';
        echo '<button type="button" class="btn btn-secondary" onclick="$(\'#myModalBoleto\').css(\'display\', \'none\');">Fechar</button>';
        echo '</div>';
    } else {
        echo '<div class="alert alert-warning mb-4" role="alert">';
        echo 'Não foi possível gerar o boleto. Por favor, tente novamente.';
        echo '</div>';

        echo '<div class="text-center mt-3">';
        echo '<button type="button" class="btn btn-secondary" onclick="$(\'#myModalBoleto\').css(\'display\', \'none\');">Fechar</button>';
        echo '</div>';
    }
}

echo '</div>'; // Fim da modal-content-inner/
?>