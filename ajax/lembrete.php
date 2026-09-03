<?php
error_reporting(E_ALL);
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);

$bb_ajax_context_mode = 'tenant';
$bb_ajax_context_response = 'plain';
require_once __DIR__ . '/_context_bootstrap.php';

if (!isset($api) || trim((string)$api) === '') {
    $api = 'menuia';
}

$api = strtolower(trim((string)$api));
$token = isset($token) ? trim((string)$token) : '';
$instancia = isset($instancia) ? trim((string)$instancia) : '';
$url_sistema = isset($url_sistema) ? (string)$url_sistema : '';
$dias_aviso = isset($dias_aviso) ? (int)$dias_aviso : 0;

function bb_lembrete_log(string $msg): void
{
    @file_put_contents(__DIR__ . '/error_log', '[' . date('d-M-Y H:i:s') . '] BarberBot Lembrete: ' . $msg . PHP_EOL, FILE_APPEND);
    @error_log('BarberBot Lembrete: ' . $msg);
}

function bb_lembrete_db_name($pdo): string
{
    try {
        if ($pdo instanceof PDO) {
            $st = $pdo->query('SELECT DATABASE()');
            $db = $st ? $st->fetchColumn() : '';
            return $db ? (string)$db : '';
        }
    } catch (Throwable $e) {
    }
    return '';
}

function bb_lembrete_contexto(): string
{
    global $bb_ajax_context;
    return (string)($bb_ajax_context['type'] ?? 'tenant');
}

function bb_lembrete_normaliza_telefone(string $telefoneRaw): string
{
    $raw = trim($telefoneRaw);
    $digits = preg_replace('/\D+/', '', $raw);
    $digits = trim((string)$digits);

    if ($digits === '') {
        return '';
    }

    $temPrefixoInternacional = (bool)preg_match('/^\s*\+/', $raw) || (bool)preg_match('/^\s*00/', $raw);

    if (strpos($digits, '00') === 0) {
        $digits = substr($digits, 2);
    }

    if (!$temPrefixoInternacional && (strlen($digits) === 10 || strlen($digits) === 11)) {
        $digits = '55' . $digits;
    }

    return $digits;
}

function bb_lembrete_mensagem_ok(string $response, int $http, int $errno): bool
{
    if ($errno !== 0 || $http < 200 || $http >= 300) {
        return false;
    }

    $json = json_decode($response, true);
    if (is_array($json)) {
        if (isset($json['ok']) && $json['ok'] === false) {
            return false;
        }
        if (isset($json['status']) && (int)$json['status'] >= 400) {
            return false;
        }
        if (isset($json['error']) && trim((string)$json['error']) !== '') {
            return false;
        }
    }

    return true;
}

function bb_lembrete_lock_dir(): string
{
    $dir = __DIR__ . '/logs/lembretes';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
    return $dir;
}

function bb_lembrete_ja_enviado(string $chave): bool
{
    return is_file(bb_lembrete_lock_dir() . '/' . $chave . '.ok');
}

function bb_lembrete_marcar_enviado(string $chave): void
{
    @file_put_contents(bb_lembrete_lock_dir() . '/' . $chave . '.ok', date('Y-m-d H:i:s'));
}

function bb_lembrete_criar_lock(string $chave)
{
    $arquivo = bb_lembrete_lock_dir() . '/' . $chave . '.lock';
    $fp = @fopen($arquivo, 'x');
    if (!$fp) {
        return false;
    }
    fwrite($fp, date('Y-m-d H:i:s'));
    return $fp;
}

function bb_lembrete_remover_lock(string $chave, $fp): void
{
    if (is_resource($fp)) {
        fclose($fp);
    }
    @unlink(bb_lembrete_lock_dir() . '/' . $chave . '.lock');
}

function bb_lembrete_enviar_menuia(string $token, string $instancia, string $telefone, string $mensagem): bool
{
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL            => 'https://chatbot.menuia.com/api/create-message',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => array(
            'appkey'  => $token,
            'authkey' => $instancia,
            'to'      => $telefone,
            'message' => $mensagem,
        ),
    ));

    $response = curl_exec($curl);
    $errno = curl_errno($curl);
    $err = curl_error($curl);
    $http = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    $ok = bb_lembrete_mensagem_ok((string)$response, $http, $errno);
    if (!$ok) {
        bb_lembrete_log('Menuia falhou | telefone=' . $telefone . ' | http=' . $http . ' | errno=' . $errno . ' | erro=' . $err . ' | resposta=' . substr((string)$response, 0, 300));
    }

    return $ok;
}

function bb_lembrete_enviar_enviame(string $instancia, string $telefone, string $mensagem, string $idCobranca, string $url_sistema): bool
{
    $payload = json_encode(array(
        'instance'      => $instancia,
        'to'            => $telefone,
        'message'       => $mensagem,
        'msg_erro'      => 'Desculpe, tente novamente mais tarde.',
        'msg_confirma'  => 'Mensagem recebida. Obrigado! ✅',
        'msg_reagendar' => '',
        'id_consulta'   => (string)$idCobranca,
        'url_recebe'    => $url_sistema . 'ajax/retorno.php',
        'data'          => date('Y-m-d H:i:s'),
        'aviso'         => '0',
    ), JSON_UNESCAPED_UNICODE);

    if ($payload === false) {
        bb_lembrete_log('Falha ao montar JSON Enviame | cobranca=' . $idCobranca);
        return false;
    }

    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL            => 'https://api.enviame.com.br/whatsapp/agendar/texto',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING       => '',
        CURLOPT_MAXREDIRS      => 10,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION   => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST  => 'POST',
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => array('Content-Type: application/json', 'Accept: application/json'),
    ));

    $response = curl_exec($curl);
    $errno = curl_errno($curl);
    $err = curl_error($curl);
    $http = (int)curl_getinfo($curl, CURLINFO_HTTP_CODE);
    curl_close($curl);

    $ok = bb_lembrete_mensagem_ok((string)$response, $http, $errno);
    if (!$ok) {
        bb_lembrete_log('Enviame falhou | telefone=' . $telefone . ' | http=' . $http . ' | errno=' . $errno . ' | erro=' . $err . ' | resposta=' . substr((string)$response, 0, 300));
    }

    return $ok;
}

$data    = date('Y-m-d', strtotime($dias_aviso . ' day'));
$datePT  = date('d/m/Y', strtotime($dias_aviso . ' day'));
$hoje    = date('d/m/Y');
$dbAtual = bb_lembrete_db_name($pdo);
$ctx     = bb_lembrete_contexto();

if ($token === '' || $instancia === '') {
    bb_lembrete_log('Credenciais vazias | ctx=' . $ctx . ' | db=' . $dbAtual . ' | api=' . $api);
    echo 'Credenciais da API não encontradas.';
    exit();
}

$stmt = $pdo->prepare("SELECT * FROM receber WHERE data_venc = :data_venc AND pago != 'Sim' AND recorrencia = 'Sim'");
$stmt->execute(array(':data_venc' => $data));
$faturas = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (empty($faturas)) {
    echo "Nenhum aviso para hoje ($hoje).";
    exit();
}

$totalEnviado = 0;
$totalIgnorado = 0;
$totalFalha = 0;

foreach ($faturas as $fatura) {
    $valor      = $fatura['valor'] ?? '';
    $usuario    = $fatura['pessoa'] ?? '';
    $idCobranca = $fatura['id'] ?? '';
    $id_ref     = $fatura['id_ref'] ?? '';

    if ($idCobranca === '' || $usuario === '') {
        $totalIgnorado++;
        continue;
    }

    $chaveEnvio = sha1($dbAtual . '|lembrete|' . $data . '|' . $idCobranca);

    if (bb_lembrete_ja_enviado($chaveEnvio)) {
        $totalIgnorado++;
        continue;
    }

    $lock = bb_lembrete_criar_lock($chaveEnvio);
    if (!$lock) {
        $totalIgnorado++;
        continue;
    }

    try {
        $stmtCobranca = $pdo->prepare('SELECT valor FROM cobrancas WHERE id = :id LIMIT 1');
        $stmtCobranca->execute(array(':id' => $id_ref));
        $cobranca = $stmtCobranca->fetch(PDO::FETCH_ASSOC);
        $valorFinal = $cobranca['valor'] ?? $valor;

        $stmtCliente = $pdo->prepare('SELECT nome, telefone FROM clientes WHERE id = :id LIMIT 1');
        $stmtCliente->execute(array(':id' => $usuario));
        $cliente = $stmtCliente->fetch(PDO::FETCH_ASSOC);

        if (!$cliente) {
            $totalIgnorado++;
            bb_lembrete_remover_lock($chaveEnvio, $lock);
            continue;
        }

        $nome = $cliente['nome'] ?? '';
        $telefone = bb_lembrete_normaliza_telefone((string)($cliente['telefone'] ?? ''));

        if ($telefone === '') {
            $totalIgnorado++;
            bb_lembrete_remover_lock($chaveEnvio, $lock);
            continue;
        }

        $mensagem  = "_Assinatura vencerá em breve {$nome}_\n";
        $mensagem .= "Valor: *{$valorFinal}*\n";
        $mensagem .= "Data: *{$datePT}*\n\n";
        $mensagem .= "*Link de Pagamento:*\n{$url_sistema}pagar/{$idCobranca}";
        $mensagem = str_replace('%0A', "\n", $mensagem);

        if ($api === 'menuia') {
            $ok = bb_lembrete_enviar_menuia($token, $instancia, $telefone, $mensagem);
        } else {
            $ok = bb_lembrete_enviar_enviame($instancia, $telefone, $mensagem, (string)$idCobranca, $url_sistema);
        }

        if ($ok) {
            bb_lembrete_marcar_enviado($chaveEnvio);
            $totalEnviado++;
        } else {
            $totalFalha++;
        }

        bb_lembrete_remover_lock($chaveEnvio, $lock);
    } catch (Throwable $e) {
        $totalFalha++;
        bb_lembrete_log('Erro cobrança=' . $idCobranca . ' | ' . $e->getMessage());
        bb_lembrete_remover_lock($chaveEnvio, $lock);
    }
}

echo "Lembretes processados. Enviados: {$totalEnviado}. Ignorados: {$totalIgnorado}. Falhas: {$totalFalha}.";
?>
