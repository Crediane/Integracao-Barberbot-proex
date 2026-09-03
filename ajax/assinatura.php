<?php
header('Content-Type: text/plain; charset=UTF-8');

$bb_ajax_context_mode = 'tenant';
$bb_ajax_context_response = 'plain';
require_once __DIR__ . '/_context_bootstrap.php';

// ===== Dados recebidos =====
$telefone = trim((string)($_POST['telefone'] ?? ''));
$nome = trim((string)($_POST['nome'] ?? ''));
$id = filter_var($_POST['id'] ?? 0, FILTER_VALIDATE_INT);

if (!$id || $id <= 0) {
    die('ID invalido.');
}

if ($telefone === '') {
    die('Telefone invalido.');
}

if ($nome === '') {
    die('Nome invalido.');
}

$datahoje = date('Y-m-d');
$dataVencimento = date('Y-m-d', strtotime($datahoje . ' +30 days'));

// ===== Consulta para verificar o item de assinatura =====
$query = $pdo->prepare("SELECT * FROM itens_assinaturas WHERE id = :id LIMIT 1");
$query->bindValue(':id', $id, PDO::PARAM_INT);
$query->execute();
$item = $query->fetch(PDO::FETCH_ASSOC);

if (!$item) {
    die('Item de assinatura nao encontrado.');
}

$id_grupo = $item['grupo'];
$valor = $item['valor'];

// ===== Cadastrar o cliente caso não tenha cadastro =====
$senha = '123';
$senha_crip = password_hash($senha, PASSWORD_DEFAULT);

$query = $pdo->prepare("SELECT * FROM clientes WHERE telefone LIKE :telefone LIMIT 1");
$query->bindValue(':telefone', $telefone);
$query->execute();
$cliente = $query->fetch(PDO::FETCH_ASSOC);

if (!$cliente) {
    $query = $pdo->prepare("
        INSERT INTO clientes
        SET nome = :nome,
            telefone = :telefone,
            data_cad = :data_cad,
            cartoes = '0',
            alertado = 'Nao',
            senha_crip = :senha_crip
    ");
    $query->bindValue(':nome', $nome);
    $query->bindValue(':telefone', $telefone);
    $query->bindValue(':data_cad', $datahoje);
    $query->bindValue(':senha_crip', $senha_crip);
    $query->execute();

    $id_cliente = $pdo->lastInsertId();
} else {
    $id_cliente = $cliente['id'];
}

// ===== Excluir assinaturas anteriores que estiverem pendentes =====
$query = $pdo->prepare("
    DELETE FROM assinaturas
    WHERE cliente = :id_cliente
      AND item = :id
      AND pago != 'Sim'
");
$query->bindValue(':id_cliente', $id_cliente, PDO::PARAM_INT);
$query->bindValue(':id', $id, PDO::PARAM_INT);
$query->execute();


// ===== Assinatura recorrente =====
$urlRecorrencia = $url_sistema . 'sistema/painel/paginas/clientes/recorrencia.php';

// Dados a serem enviados via POST
$data = [
    'id'         => $id_cliente,
    'valor'      => $valor,
    'parcelas'   => 1,
    'data_venc'  => $dataVencimento,
    'frequencia' => 30
];

// Mantém o banco individual correto no fluxo interno (tenant)
if ($__bb_slug !== '') {
    $data['loja'] = $__bb_slug;
}

// ===== IMPORTANTE: solta a sessão antes do cURL para não travar =====
$__bb_cookieSessao = '';
if (session_status() === PHP_SESSION_ACTIVE) {
    $__bb_cookieSessao = session_name() . '=' . session_id();
    session_write_close();
}

// ===== Inicializa o cURL =====
$ch = curl_init($urlRecorrencia);

// Configuração do cURL
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($data));
curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
curl_setopt($ch, CURLOPT_TIMEOUT, 20);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_MAXREDIRS, 0);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Expect:']);

// Preserva o mesmo contexto de sessão quando existir
if ($__bb_cookieSessao !== '') {
    curl_setopt($ch, CURLOPT_COOKIE, $__bb_cookieSessao);
}

$response = curl_exec($ch);
$curl_errno = curl_errno($ch);
$curl_error = curl_error($ch);
$http_code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);

curl_close($ch);

// Log simples no PHP error_log, sem mudar a lógica do sistema
if ($curl_errno) {
    error_log('[ajax/assinatura.php] Erro cURL recorrencia: ' . $curl_error . ' | HTTP: ' . $http_code . ' | Cliente: ' . $id_cliente . ' | Loja: ' . $__bb_slug);
}

// ===== Marcar a assinatura =====
$query = $pdo->prepare("
    INSERT INTO assinaturas
    SET cliente = :id_cliente,
        data = :data_assinatura,
        pago = 'Nao',
        grupo = :id_grupo,
        item = :id,
        valor = :valor,
        frequencia = '30',
        vencimento = :vencimento,
        data_vencimento = :dataVencimento
");
$query->bindValue(':id_cliente', $id_cliente, PDO::PARAM_INT);
$query->bindValue(':id_grupo', $id_grupo);
$query->bindValue(':id', $id, PDO::PARAM_INT);
$query->bindValue(':valor', $valor);
$query->bindValue(':data_assinatura', $datahoje);
$query->bindValue(':vencimento', $datahoje);
$query->bindValue(':dataVencimento', $dataVencimento);
$query->execute();

$ult_id = $pdo->lastInsertId();

echo 'Salvo*' . $ult_id;
?>