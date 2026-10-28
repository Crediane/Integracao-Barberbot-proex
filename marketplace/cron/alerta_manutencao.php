<?php
// /public_html/cron/alerta_manutencao.php
// BarberBot Marketplace - Alerta WhatsApp de manutenção de prótese
//
// Ajuste pontual, sem alterar a regra de negócio da manutenção:
// - usa a tabela manutencoes_protese;
// - considera manutenção com proxima_manutencao vencida ou vencendo hoje;
// - envia para o WhatsApp do cliente vinculado em manutencoes_protese.cliente;
// - mantém alerta_manutencao_enviado como trava permanente do alerta da manutenção;
// - grava data_alerta_manutencao somente após retorno positivo da API/Menuia;
// - respeita a configuração de API/token/instância da loja;
// - executa por loja (?loja=slug / --loja=slug) ou all=1 explícito; sem contexto, não consulta banco operacional;
// - no modo all=1, não carrega o bootstrap da loja antes da varredura multi-tenant.
//
// Ponto corrigido neste arquivo:
// - mantém a consulta principal da manutenção, mas adiciona diagnóstico detalhado;
// - aceita config.msg_agendamento em branco, Api, menuia ou Sim como API habilitada;
// - usa as credenciais padrão do tenant antes da credencial da linha do tenant;
// - captura retorno impresso pelo texto.php e retorno por $status_mensagem;
// - evita ob_get_clean duplicado quando o texto.php lança erro.

@set_time_limit(0);

require_once __DIR__ . '/cron_guard.php';

// ======================================================
// Timezone do cron
// ======================================================
if (is_file(__DIR__ . '/timezone_cron.php')) {
    require_once __DIR__ . '/timezone_cron.php';
}

if (!function_exists('bb_cron_aplicar_timezone')) {
    function bb_cron_aplicar_timezone(string $timezone = ''): void {
        $timezone = trim($timezone);

        if ($timezone === '') {
            $timezone = 'America/Sao_Paulo';
        }

        if (@date_default_timezone_set($timezone) === false) {
            @date_default_timezone_set('America/Sao_Paulo');
        }
    }
}

if (!function_exists('bb_cron_data_hoje')) {
    function bb_cron_data_hoje(): string {
        return date('Y-m-d');
    }
}

if (!function_exists('bb_cron_hora_agora')) {
    function bb_cron_hora_agora(): string {
        return date('H:i:s');
    }
}

if (!function_exists('bb_cron_data_hora_agora')) {
    function bb_cron_data_hora_agora(): string {
        return date('Y-m-d H:i:s');
    }
}

if (!function_exists('bb_cron_timezone_de_linha')) {
    function bb_cron_timezone_de_linha(array $linha): string {
        $timezone = trim((string)($linha['timezone'] ?? ''));

        return $timezone !== '' ? $timezone : 'America/Sao_Paulo';
    }
}

bb_cron_aplicar_timezone();

// ======================================================
// Entrada segura GET/POST/CLI
// ======================================================
$DEBUG = false;

if (isset($_GET['debug']) && (string)$_GET['debug'] === '1') {
    $DEBUG = true;
}

if (PHP_SAPI === 'cli' && isset($argv) && is_array($argv)) {
    foreach ($argv as $argDebug) {
        if ($argDebug === '--debug=1' || $argDebug === 'debug=1' || $argDebug === '--debug' || $argDebug === 'debug') {
            $DEBUG = true;
            break;
        }
    }
}

if ($DEBUG && !headers_sent()) {
    @header('Content-Type: text/plain; charset=utf-8');
}

$lojaCli = '';
$allCli = false;

if (PHP_SAPI === 'cli' && isset($argv) && is_array($argv)) {
    foreach ($argv as $arg) {
        $arg = (string)$arg;

        if (strpos($arg, '--loja=') === 0) {
            $lojaCli = substr($arg, 7);
            continue;
        }

        if (strpos($arg, 'loja=') === 0) {
            $lojaCli = substr($arg, 5);
            continue;
        }

        if ($arg === '--all=1' || $arg === 'all=1' || $arg === '--all' || $arg === 'all') {
            $allCli = true;
        }
    }
}

$lojaSolicitada = '';

if (!empty($_GET['loja'])) {
    $lojaSolicitada = (string)$_GET['loja'];
} elseif (!empty($_POST['loja'])) {
    $lojaSolicitada = (string)$_POST['loja'];
} elseif ($lojaCli !== '') {
    $lojaSolicitada = $lojaCli;
}

if ($lojaSolicitada === '') {
    $caminhoUrl = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

    if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $caminhoUrl, $matchLoja)) {
        $lojaSolicitada = (string)$matchLoja[1];
    }
}

$lojaSolicitada = preg_replace('/[^a-z0-9_-]/i', '', trim((string)$lojaSolicitada));

if ($lojaSolicitada !== '') {
    $_GET['loja'] = $lojaSolicitada;
}

$executarTodas = false;

if (isset($_GET['all']) && (string)$_GET['all'] === '1') {
    $executarTodas = true;
} elseif (isset($_POST['all']) && (string)$_POST['all'] === '1') {
    $executarTodas = true;
} elseif ($allCli) {
    $executarTodas = true;
}

// ======================================================
// Lock de execução
// ======================================================
$lockKey = 'local';

if ($lojaSolicitada !== '') {
    $lockKey = 'slug_' . $lojaSolicitada;
} elseif ($executarTodas) {
    $lockKey = 'all';
}

$lockDir = sys_get_temp_dir();

if (!is_dir($lockDir) || !is_writable($lockDir)) {
    $lockDir = __DIR__;
}

$lockFile = rtrim($lockDir, '/\\') . DIRECTORY_SEPARATOR . 'bb_cron_alerta_manutencao_' . sha1($lockKey) . '.lock';
$lockHandle = @fopen($lockFile, 'c+');

if ($lockHandle === false) {
    if ($DEBUG) {
        echo "ERRO: não foi possível abrir o lock do cron\n";
    }

    echo '0';
    exit;
}

if (!@flock($lockHandle, LOCK_EX | LOCK_NB)) {
    if ($DEBUG) {
        echo "Execução já em andamento para a chave {$lockKey}\n";
    }

    @fclose($lockHandle);
    echo '0';
    exit;
}

register_shutdown_function(static function () use ($lockHandle): void {
    if (is_resource($lockHandle)) {
        @flock($lockHandle, LOCK_UN);
        @fclose($lockHandle);
    }
});

// ======================================================
// Helpers gerais
// ======================================================
function bb_limpar_slug(string $slug): string {
    return preg_replace('/[^a-z0-9_-]/i', '', trim($slug));
}

function bb_pdo_connect(string $host, string $db, string $user, string $pass): PDO {
    $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ]);
}

function bb_get_cfg_marketplace(): array {
    $arquivo = __DIR__ . '/../config/marketplace.php';

    if (!is_file($arquivo)) {
        return [];
    }

    $cfg = include $arquivo;

    return is_array($cfg) ? $cfg : [];
}

function bb_fetch_one_config(PDO $pdo): array {
    try {
        $row = $pdo->query('SELECT * FROM config LIMIT 1')->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : [];
    } catch (Throwable $e) {
        return [];
    }
}

function bb_tabela_existe(PDO $pdo, string $tabela): bool {
    $tabela = preg_replace('/[^a-z0-9_]/i', '', trim($tabela));

    if ($tabela === '') {
        return false;
    }

    try {
        $st = $pdo->prepare("
            SELECT 1
              FROM INFORMATION_SCHEMA.TABLES
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :tabela
             LIMIT 1
        ");
        $st->execute([':tabela' => $tabela]);

        return (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function bb_coluna_existe(PDO $pdo, string $tabela, string $coluna): bool {
    $tabela = preg_replace('/[^a-z0-9_]/i', '', trim($tabela));
    $coluna = preg_replace('/[^a-z0-9_]/i', '', trim($coluna));

    if ($tabela === '' || $coluna === '') {
        return false;
    }

    try {
        $st = $pdo->prepare("
            SELECT 1
              FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = DATABASE()
               AND TABLE_NAME = :tabela
               AND COLUMN_NAME = :coluna
             LIMIT 1
        ");
        $st->execute([
            ':tabela' => $tabela,
            ':coluna' => $coluna,
        ]);

        return (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function bb_coluna_existe_schema(PDO $pdo, string $dbName, string $tabela, string $coluna): bool {
    $tabela = preg_replace('/[^a-z0-9_]/i', '', trim($tabela));
    $coluna = preg_replace('/[^a-z0-9_]/i', '', trim($coluna));

    if ($dbName === '' || $tabela === '' || $coluna === '') {
        return false;
    }

    try {
        $st = $pdo->prepare("
            SELECT 1
              FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = :db
               AND TABLE_NAME = :tabela
               AND COLUMN_NAME = :coluna
             LIMIT 1
        ");
        $st->execute([
            ':db' => $dbName,
            ':tabela' => $tabela,
            ':coluna' => $coluna,
        ]);

        return (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function bb_tenants_where_ativo(PDO $pdo, string $dbName, string $tabela): string {
    $tabela = preg_replace('/[^a-z0-9_]/i', '', trim($tabela));

    if ($tabela === '') {
        $tabela = 'tenants';
    }

    try {
        $st = $pdo->prepare("
            SELECT COLUMN_NAME
              FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = :db
               AND TABLE_NAME = :tabela
               AND COLUMN_NAME IN ('ativo', 'status')
        ");
        $st->execute([
            ':db' => $dbName,
            ':tabela' => $tabela,
        ]);

        $colunas = $st->fetchAll(PDO::FETCH_COLUMN);

        if (in_array('ativo', $colunas, true)) {
            return "`ativo` = 1";
        }

        if (in_array('status', $colunas, true)) {
            return "`status` IN ('active', 'ativo', '1')";
        }
    } catch (Throwable $e) {
        return '1=1';
    }

    return '1=1';
}

function bb_try_decrypt_pass(string $senhaBruta, string $segredo): string {
    $senhaBruta = trim($senhaBruta);

    if ($senhaBruta === '') {
        return '';
    }

    if ($segredo === '' || strpos($senhaBruta, 'enc:') !== 0) {
        return $senhaBruta;
    }

    $arquivoCrypto = __DIR__ . '/../sistema/tenant_crypto.php';

    if (is_file($arquivoCrypto)) {
        require_once $arquivoCrypto;
    }

    if (function_exists('bb_decrypt_str')) {
        $senhaDecodificada = (string)bb_decrypt_str($senhaBruta, $segredo);

        if ($senhaDecodificada !== '') {
            return $senhaDecodificada;
        }
    }

    return $senhaBruta;
}

function bb_senha_tenant_utilizavel(string $senha): bool {
    $senha = trim($senha);

    if ($senha === '') {
        return false;
    }

    if (strpos($senha, 'enc:') === 0) {
        return false;
    }

    return true;
}

function bb_normalizar_texto_simples(string $texto): string {
    $texto = trim(strtolower($texto));

    $mapa = [
        'á' => 'a', 'à' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a',
        'é' => 'e', 'è' => 'e', 'ê' => 'e', 'ë' => 'e',
        'í' => 'i', 'ì' => 'i', 'î' => 'i', 'ï' => 'i',
        'ó' => 'o', 'ò' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o',
        'ú' => 'u', 'ù' => 'u', 'û' => 'u', 'ü' => 'u',
        'ç' => 'c',
    ];

    return strtr($texto, $mapa);
}

function bb_msg_api_habilitada(string $msgAgendamento): bool {
    $msg = bb_normalizar_texto_simples($msgAgendamento);

    // Compatível com o padrão usado nos crons do sistema:
    // vazio não bloqueia; api/menuia/sim habilitam; nao/off/0 bloqueiam.
    if ($msg === '') {
        return true;
    }

    if (in_array($msg, ['api', 'menuia', 'sim', 's', '1', 'true', 'ativo', 'ativado'], true)) {
        return true;
    }

    if (in_array($msg, ['nao', 'n', '0', 'false', 'off', 'desativado', 'desativada'], true)) {
        return false;
    }

    return true;
}

function bb_phone_digits($telefone): string {
    return preg_replace('/[^0-9]/', '', (string)$telefone);
}

function bb_tel_whats($telefone): string {
    $telefone = bb_phone_digits($telefone);

    if ($telefone === '') {
        return '';
    }

    if (strpos($telefone, '55') === 0 && strlen($telefone) >= 12) {
        return $telefone;
    }

    return '55' . $telefone;
}

function bb_data_br($data): string {
    $data = trim((string)$data);

    if ($data === '' || $data === '0000-00-00' || strtolower($data) === 'null') {
        return '';
    }

    $partes = explode('-', $data);

    if (count($partes) !== 3) {
        return $data;
    }

    return $partes[2] . '/' . $partes[1] . '/' . $partes[0];
}

function bb_status_envio_ok(string $status): bool {
    $status = trim($status);

    if ($status === '') {
        return false;
    }

    $statusLower = function_exists('mb_strtolower')
        ? mb_strtolower($status, 'UTF-8')
        : strtolower($status);

    if ($statusLower === 'ok' || $statusLower === '1') {
        return true;
    }

    if (strpos($statusLower, 'mensagem enviada com sucesso') !== false) {
        return true;
    }

    if (strpos($statusLower, 'sucesso') !== false) {
        return true;
    }

    if (strpos($statusLower, '"success":true') !== false) {
        return true;
    }

    if (strpos($statusLower, '"status":"success"') !== false) {
        return true;
    }

    if (strpos($statusLower, 'enviada') !== false && strpos($statusLower, 'erro') === false) {
        return true;
    }

    return false;
}

function bb_normalizar_status_retorno(string $statusMensagem, string $echoTexto): string {
    $statusMensagem = trim($statusMensagem);
    $echoTexto = trim($echoTexto);

    if ($statusMensagem !== '') {
        return $statusMensagem;
    }

    if ($echoTexto !== '') {
        return $echoTexto;
    }

    return '';
}

function bb_localizar_texto_api(): string {
    $candidatos = [
        __DIR__ . '/texto.php',
        __DIR__ . '/../sistema/painel/apis/texto.php',
        __DIR__ . '/../sistema/apis/texto.php',
        __DIR__ . '/../apis/texto.php',
        __DIR__ . '/../ajax/texto.php',
    ];

    foreach ($candidatos as $arquivo) {
        if (is_file($arquivo)) {
            return $arquivo;
        }
    }

    return '';
}

function bb_extrair_nome_cliente(array $cliente): string {
    foreach (['nome', 'cliente', 'nome_cliente', 'pessoa', 'razao_social', 'fantasia'] as $campo) {
        if (!empty($cliente[$campo])) {
            return trim((string)$cliente[$campo]);
        }
    }

    return 'Cliente';
}

function bb_extrair_telefone_cliente(array $cliente): string {
    foreach ([
        'telefone_whatsapp',
        'whatsapp',
        'celular',
        'telefone',
        'fone',
        'tel',
        'telefone1',
        'telefone2',
        'celular1',
        'celular2',
        'contato',
        'contato1',
        'contato2'
    ] as $campo) {
        if (!empty($cliente[$campo])) {
            $telefone = bb_tel_whats($cliente[$campo]);

            if ($telefone !== '') {
                return $telefone;
            }
        }
    }

    return '';
}

function bb_buscar_cliente_por_id(PDO $pdo, int $id): ?array {
    if ($id <= 0 || !bb_tabela_existe($pdo, 'clientes')) {
        return null;
    }

    try {
        $st = $pdo->prepare('SELECT * FROM clientes WHERE id = :id LIMIT 1');
        $st->bindValue(':id', $id, PDO::PARAM_INT);
        $st->execute();

        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    } catch (Throwable $e) {
        return null;
    }
}

function bb_buscar_cliente_por_nome(PDO $pdo, string $nome): ?array {
    $nome = trim($nome);

    if ($nome === '' || !bb_tabela_existe($pdo, 'clientes')) {
        return null;
    }

    try {
        $st = $pdo->prepare('SELECT * FROM clientes WHERE nome = :nome LIMIT 1');
        $st->execute([':nome' => $nome]);

        $row = $st->fetch(PDO::FETCH_ASSOC);

        if (is_array($row)) {
            return $row;
        }
    } catch (Throwable $e) {
        // mantém fallback abaixo
    }

    try {
        $st = $pdo->prepare('SELECT * FROM clientes WHERE nome LIKE :nome LIMIT 1');
        $st->execute([':nome' => '%' . $nome . '%']);

        $row = $st->fetch(PDO::FETCH_ASSOC);

        return is_array($row) ? $row : null;
    } catch (Throwable $e) {
        return null;
    }
}

function bb_resolver_cliente_manutencao(PDO $pdo, array $manutencao): ?array {
    $clienteValor = trim((string)($manutencao['cliente'] ?? ''));

    if ($clienteValor !== '' && ctype_digit($clienteValor)) {
        $cliente = bb_buscar_cliente_por_id($pdo, (int)$clienteValor);

        if (is_array($cliente)) {
            $cliente['_origem_resolucao'] = 'manutencoes_protese.cliente -> clientes.id';

            return $cliente;
        }
    }

    if ($clienteValor !== '' && !ctype_digit($clienteValor)) {
        $cliente = bb_buscar_cliente_por_nome($pdo, $clienteValor);

        if (is_array($cliente)) {
            $cliente['_origem_resolucao'] = 'manutencoes_protese.cliente -> clientes.nome';

            return $cliente;
        }
    }

    return null;
}

function bb_diagnostico_manutencao(PDO $pdo, string $ctx, bool $DEBUG): void {
    if (!$DEBUG) {
        return;
    }

    $hoje = bb_cron_data_hoje();

    echo "[{$ctx}] Diagnóstico manutenção\n";
    echo "[{$ctx}] Filtro de envio: proxima_manutencao <= {$hoje}, alerta_manutencao_enviado vazio/0, cliente vinculado e telefone válido\n";

    try {
        $total = (int)$pdo->query('SELECT COUNT(*) FROM manutencoes_protese')->fetchColumn();
        echo "[{$ctx}] Total de registros na tabela manutencoes_protese: {$total}\n";
    } catch (Throwable $e) {
        echo "[{$ctx}] Erro ao contar manutencoes_protese: " . $e->getMessage() . "\n";
        return;
    }

    try {
        $st = $pdo->prepare("
            SELECT COUNT(*)
              FROM manutencoes_protese
             WHERE proxima_manutencao IS NOT NULL
               AND proxima_manutencao <> '0000-00-00'
               AND proxima_manutencao < :hoje
        ");
        $st->execute([':hoje' => $hoje]);
        echo "[{$ctx}] Manutenções vencidas antes de hoje: " . (int)$st->fetchColumn() . "\n";
    } catch (Throwable $e) {
        echo "[{$ctx}] Erro diagnóstico vencidas: " . $e->getMessage() . "\n";
    }

    try {
        $st = $pdo->prepare("
            SELECT COUNT(*)
              FROM manutencoes_protese
             WHERE proxima_manutencao = :hoje
        ");
        $st->execute([':hoje' => $hoje]);
        echo "[{$ctx}] Manutenções vencendo hoje: " . (int)$st->fetchColumn() . "\n";
    } catch (Throwable $e) {
        echo "[{$ctx}] Erro diagnóstico vencendo hoje: " . $e->getMessage() . "\n";
    }

    try {
        $st = $pdo->prepare("
            SELECT COUNT(*)
              FROM manutencoes_protese
             WHERE proxima_manutencao IS NOT NULL
               AND proxima_manutencao <> '0000-00-00'
               AND proxima_manutencao <= :hoje
               AND COALESCE(alerta_manutencao_enviado, 0) = 1
        ");
        $st->execute([':hoje' => $hoje]);
        echo "[{$ctx}] Manutenções vencidas/hoje já marcadas como alerta enviado: " . (int)$st->fetchColumn() . "\n";
    } catch (Throwable $e) {
        echo "[{$ctx}] Erro diagnóstico já alertadas: " . $e->getMessage() . "\n";
    }

    $candidatas = [];

    try {
        $st = $pdo->prepare("
            SELECT *
              FROM manutencoes_protese
             WHERE proxima_manutencao IS NOT NULL
               AND proxima_manutencao <> '0000-00-00'
               AND proxima_manutencao <= :hoje
               AND COALESCE(alerta_manutencao_enviado, 0) = 0
             ORDER BY proxima_manutencao ASC, id ASC
             LIMIT 20
        ");
        $st->execute([':hoje' => $hoje]);
        $candidatas = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        echo "[{$ctx}] Erro diagnóstico candidatas: " . $e->getMessage() . "\n";
    }

    $semCliente = 0;
    $semTelefone = 0;
    $aptasComTelefone = 0;

    foreach ($candidatas as $linha) {
        $cliente = bb_resolver_cliente_manutencao($pdo, $linha);

        if (!is_array($cliente)) {
            $semCliente++;
            continue;
        }

        $telefone = bb_extrair_telefone_cliente($cliente);

        if ($telefone === '') {
            $semTelefone++;
            continue;
        }

        $aptasComTelefone++;
    }

    echo "[{$ctx}] Primeiras candidatas vencidas/hoje sem alerta: " . count($candidatas) . "\n";
    echo "[{$ctx}] Candidatas sem cliente encontrado: {$semCliente}\n";
    echo "[{$ctx}] Candidatas com cliente sem telefone: {$semTelefone}\n";
    echo "[{$ctx}] Candidatas com cliente e telefone para envio: {$aptasComTelefone}\n";

    if (count($candidatas) === 0) {
        echo "[{$ctx}] - nenhuma manutenção pendente para alerta dentro da regra atual\n";
        return;
    }

    foreach ($candidatas as $linha) {
        $cliente = bb_resolver_cliente_manutencao($pdo, $linha);
        $nomeCliente = is_array($cliente) ? bb_extrair_nome_cliente($cliente) : '[cliente nao encontrado]';
        $telefoneCliente = is_array($cliente) ? bb_extrair_telefone_cliente($cliente) : '';
        $telefoneOk = $telefoneCliente !== '' ? 'sim' : 'nao';

        echo "[{$ctx}] - id=" . (int)($linha['id'] ?? 0)
            . " | cliente=" . trim((string)($linha['cliente'] ?? ''))
            . " | id_protese=" . trim((string)($linha['id_protese'] ?? ''))
            . " | proxima=" . trim((string)($linha['proxima_manutencao'] ?? ''))
            . " | alerta=" . trim((string)($linha['alerta_manutencao_enviado'] ?? ''))
            . " | data_alerta=" . trim((string)($linha['data_alerta_manutencao'] ?? ''))
            . " | telefone=" . $telefoneOk
            . " | nome=" . $nomeCliente
            . " | tipo=" . trim((string)($linha['tipo'] ?? ''))
            . "\n";
    }
}

// ======================================================
// Regra de negócio: alerta de manutenção
// ======================================================
function bb_run_alerta_manutencao(PDO $pdo, array $config, string $slug, bool $isTenant, bool $DEBUG): int {
    $ctx = $slug !== '' ? $slug : 'local';
    $totalEnviados = 0;
    $totalFalhas = 0;

    if (!bb_tabela_existe($pdo, 'manutencoes_protese')) {
        if ($DEBUG) {
            echo "[{$ctx}] tabela manutencoes_protese não encontrada\n";
        }

        return 0;
    }

    if (!bb_coluna_existe($pdo, 'manutencoes_protese', 'alerta_manutencao_enviado')) {
        if ($DEBUG) {
            echo "[{$ctx}] coluna alerta_manutencao_enviado não encontrada em manutencoes_protese\n";
        }

        return 0;
    }

    if (!bb_coluna_existe($pdo, 'manutencoes_protese', 'proxima_manutencao')) {
        if ($DEBUG) {
            echo "[{$ctx}] coluna proxima_manutencao não encontrada em manutencoes_protese\n";
        }

        return 0;
    }

    if (!bb_tabela_existe($pdo, 'clientes')) {
        if ($DEBUG) {
            echo "[{$ctx}] tabela clientes não encontrada\n";
        }

        return 0;
    }

    $nomeSistema = trim((string)($config['nome'] ?? 'BarberBot'));
    $apiConfig = strtolower(trim((string)($config['api'] ?? '')));
    $tokenConfig = trim((string)($config['token'] ?? ''));
    $instanciaConfig = trim((string)($config['instancia'] ?? ''));
    $msgAgendamento = trim((string)($config['msg_agendamento'] ?? ''));
    $telefoneLoja = trim((string)($config['telefone_whatsapp'] ?? ''));

    if ($nomeSistema === '') {
        $nomeSistema = 'BarberBot';
    }

    if ($DEBUG) {
        echo "[{$ctx}] Config API: " . ($apiConfig !== '' ? $apiConfig : '[vazio]') . "\n";
        echo "[{$ctx}] Config msg_agendamento: " . ($msgAgendamento !== '' ? $msgAgendamento : '[vazio]') . "\n";
        echo "[{$ctx}] Config instancia preenchida: " . ($instanciaConfig !== '' ? 'sim' : 'nao') . "\n";
        echo "[{$ctx}] Config token preenchido: " . ($tokenConfig !== '' ? 'sim' : 'nao') . "\n";
        echo "[{$ctx}] Config telefone_whatsapp preenchido: " . ($telefoneLoja !== '' ? 'sim' : 'nao') . "\n";
    }

    bb_diagnostico_manutencao($pdo, $ctx, $DEBUG);

    if (!bb_msg_api_habilitada($msgAgendamento)) {
        if ($DEBUG) {
            echo "[{$ctx}] envio por API desativado em config.msg_agendamento\n";
        }

        return 0;
    }

    if ($apiConfig === '' || $tokenConfig === '' || $instanciaConfig === '') {
        if ($DEBUG) {
            echo "[{$ctx}] api/token/instancia ausentes na config\n";
        }

        return 0;
    }

    $arquivoTexto = bb_localizar_texto_api();

    if ($arquivoTexto === '') {
        if ($DEBUG) {
            echo "[{$ctx}] texto.php não encontrado\n";
        }

        return 0;
    }

    if ($DEBUG) {
        echo "[{$ctx}] Arquivo texto.php localizado em: {$arquivoTexto}\n";
    }

    $hoje = bb_cron_data_hoje();

    try {
        $st = $pdo->prepare("
            SELECT *
              FROM manutencoes_protese
             WHERE proxima_manutencao IS NOT NULL
               AND proxima_manutencao <> '0000-00-00'
               AND proxima_manutencao <= :hoje
               AND COALESCE(alerta_manutencao_enviado, 0) = 0
             ORDER BY proxima_manutencao ASC, id ASC
        ");
        $st->execute([':hoje' => $hoje]);
        $registros = $st->fetchAll(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        if ($DEBUG) {
            echo "[{$ctx}] erro ao consultar manutenções: " . $e->getMessage() . "\n";
        }

        return 0;
    }

    if (!is_array($registros)) {
        $registros = [];
    }

    if ($DEBUG) {
        echo "[{$ctx}] " . count($registros) . " manutenção(ões) candidata(s) para alerta até {$hoje}\n";
    }

    if (count($registros) === 0) {
        return 0;
    }

    $temDataAlerta = bb_coluna_existe($pdo, 'manutencoes_protese', 'data_alerta_manutencao');

    if ($temDataAlerta) {
        $sqlAtualiza = "
            UPDATE manutencoes_protese
               SET alerta_manutencao_enviado = 1,
                   data_alerta_manutencao = :data_alerta
             WHERE id = :id
             LIMIT 1
        ";
    } else {
        $sqlAtualiza = "
            UPDATE manutencoes_protese
               SET alerta_manutencao_enviado = 1
             WHERE id = :id
             LIMIT 1
        ";
    }

    $stAtualiza = $pdo->prepare($sqlAtualiza);

    foreach ($registros as $item) {
        $idManutencao = (int)($item['id'] ?? 0);

        if ($idManutencao <= 0) {
            $totalFalhas++;

            if ($DEBUG) {
                echo "[{$ctx}] manutenção ignorada por ID inválido\n";
            }

            continue;
        }

        $cliente = bb_resolver_cliente_manutencao($pdo, $item);

        if (!is_array($cliente)) {
            $totalFalhas++;

            if ($DEBUG) {
                echo "[{$ctx}] manutenção={$idManutencao} falha: cliente não encontrado para cliente=" . trim((string)($item['cliente'] ?? '')) . "\n";
            }

            continue;
        }

        $nomeCliente = bb_extrair_nome_cliente($cliente);
        $telefoneCliente = bb_extrair_telefone_cliente($cliente);

        if ($DEBUG) {
            echo "[{$ctx}] manutenção={$idManutencao} cliente={$nomeCliente} telefone=" . ($telefoneCliente !== '' ? $telefoneCliente : '[vazio]') . "\n";
        }

        if ($telefoneCliente === '') {
            $totalFalhas++;

            if ($DEBUG) {
                echo "[{$ctx}] manutenção={$idManutencao} falha: cliente sem telefone/WhatsApp válido\n";
            }

            continue;
        }

        $tipo = trim((string)($item['tipo'] ?? 'Manutenção'));
        $produtosUtilizados = trim((string)($item['produtos_utilizados'] ?? ''));
        $observacoes = trim((string)($item['observacoes'] ?? ''));
        $dataManutencao = bb_data_br($item['data_manutencao'] ?? '');
        $proximaManutencao = bb_data_br($item['proxima_manutencao'] ?? '');

        if ($tipo === '') {
            $tipo = 'Manutenção';
        }

        $mensagem = "🔔 *{$nomeSistema}*%0A";
        $mensagem .= "Olá *{$nomeCliente}*!%0A%0A";
        $mensagem .= "Sua manutenção de prótese venceu ou vence hoje.%0A";

        if ($proximaManutencao !== '') {
            $mensagem .= "📅 *Data prevista:* {$proximaManutencao}%0A";
        }

        if ($tipo !== '') {
            $mensagem .= "🧾 *Tipo:* {$tipo}%0A";
        }

        if ($dataManutencao !== '') {
            $mensagem .= "🛠️ *Última manutenção:* {$dataManutencao}%0A";
        }

        if ($produtosUtilizados !== '') {
            $mensagem .= "📦 *Produtos utilizados:* {$produtosUtilizados}%0A";
        }

        if ($observacoes !== '') {
            $mensagem .= "📝 *Observações:* {$observacoes}%0A";
        }

        if ($telefoneLoja !== '') {
            $mensagem .= "%0A📲 Fale conosco: " . bb_tel_whats($telefoneLoja);
        }

        $mensagem .= "%0A%0APor favor, entre em contato para agendar sua manutenção.";

        $api = $apiConfig;
        $token = $tokenConfig;
        $instancia = $instanciaConfig;
        $telefone = $telefoneCliente;
        $status_mensagem = '';
        $echoTexto = '';

        ob_start();

        try {
            include $arquivoTexto;
        } catch (Throwable $e) {
            $status_mensagem = 'ERRO: ' . $e->getMessage();
        }

        if (ob_get_level() > 0) {
            $echoTexto = trim((string)ob_get_clean());
        }

        $statusFinal = bb_normalizar_status_retorno((string)$status_mensagem, $echoTexto);

        if ($DEBUG) {
            echo "[{$ctx}] manutenção={$idManutencao} retorno status_mensagem: " . ($status_mensagem !== '' ? $status_mensagem : '[vazio]') . "\n";
            echo "[{$ctx}] manutenção={$idManutencao} retorno echo texto.php: " . ($echoTexto !== '' ? $echoTexto : '[vazio]') . "\n";
            echo "[{$ctx}] manutenção={$idManutencao} status final: " . ($statusFinal !== '' ? $statusFinal : '[vazio]') . "\n";
        }

        if (bb_status_envio_ok($statusFinal)) {
            if ($temDataAlerta) {
                $stAtualiza->execute([
                    ':id' => $idManutencao,
                    ':data_alerta' => bb_cron_data_hora_agora(),
                ]);
            } else {
                $stAtualiza->execute([
                    ':id' => $idManutencao,
                ]);
            }

            $totalEnviados++;

            if ($DEBUG) {
                echo "[{$ctx}] OK alerta manutenção enviado e marcado id={$idManutencao}\n";
            }
        } else {
            $totalFalhas++;

            if ($DEBUG) {
                echo "[{$ctx}] FALHA envio/Menuia manutenção={$idManutencao} status={$statusFinal}\n";
            }
        }
    }

    if ($DEBUG) {
        echo "[{$ctx}] Alertas manutenção enviados: {$totalEnviados}\n";
        echo "[{$ctx}] Falhas/pendências manutenção: {$totalFalhas}\n";
    }

    return $totalEnviados;
}

// ======================================================
// Execução
// ======================================================
$totalGeral = 0;

if ($DEBUG) {
    echo 'DEBUG: agora=' . date('Y-m-d H:i:s') . ' slug=' . ($lojaSolicitada !== '' ? $lojaSolicitada : '[vazio]') . ' all=' . ($executarTodas ? '1' : '0') . "\n";
}

// 1) Loja específica: usa o resolver oficial do sistema.
if ($lojaSolicitada !== '') {
    $arquivoConexaoSistema = __DIR__ . '/../sistema/conexao.php';

    if (is_file($arquivoConexaoSistema)) {
        require_once $arquivoConexaoSistema;
    }

    $pdoExec = null;

    if (function_exists('conectarBancoPorSlug')) {
        try {
            $pdoExec = conectarBancoPorSlug($lojaSolicitada);
        } catch (Throwable $e) {
            if ($DEBUG) {
                echo "[{$lojaSolicitada}] Falha no conectarBancoPorSlug: " . $e->getMessage() . "\n";
            }
        }
    }

    $tenantDbEsperado = trim((string)($_SESSION['tenant_db'] ?? ''));
    if ($pdoExec instanceof PDO && !bb_cron_guard_tenant_context($pdoExec, $lojaSolicitada, $tenantDbEsperado)) {
        $pdoExec = null;
    }

    if ($pdoExec instanceof PDO) {
        $cfgLoja = bb_fetch_one_config($pdoExec);

        if (!empty($cfgLoja['timezone'])) {
            bb_cron_aplicar_timezone((string)$cfgLoja['timezone']);
        }

        $totalGeral += bb_run_alerta_manutencao($pdoExec, $cfgLoja, $lojaSolicitada, true, $DEBUG);
    } elseif ($DEBUG) {
        echo "[{$lojaSolicitada}] Não foi possível abrir o banco da loja\n";
    }

    echo $totalGeral;
    exit;
}

// 2) Sem slug e sem all=1, não executa consultas operacionais no banco Master.
if (!$executarTodas) {
    if ($DEBUG) {
        echo "Informe loja=<slug> ou all=1. Nenhum banco de barbearia foi selecionado.\n";
    }

    echo $totalGeral;
    exit;
}

// 3) all=1 explícito: varredura global dos tenants sem carregar bootstrap de loja.
$cfgMarket = bb_get_cfg_marketplace();

if (empty($cfgMarket) || empty($cfgMarket['master']) || !is_array($cfgMarket['master'])) {
    if ($DEBUG) {
        echo "ERRO: marketplace.php ausente ou inválido para varredura global\n";
    }

    echo $totalGeral;
    exit;
}

$mk = $cfgMarket['master'];

$tenantsTable = preg_replace('/[^a-z0-9_]/i', '', (string)($mk['table'] ?? 'tenants'));

if ($tenantsTable === '') {
    $tenantsTable = 'tenants';
}

$mkHost = trim((string)($mk['host'] ?? 'localhost'));
$mkDb = trim((string)($mk['db'] ?? ''));
$mkUser = trim((string)($mk['user'] ?? ''));
$mkPass = (string)($mk['pass'] ?? '');
$segredoTenant = (string)($cfgMarket['security']['tenant_secret'] ?? '');

if ($mkDb === '' || $mkUser === '') {
    if ($DEBUG) {
        echo "ERRO: config master incompleta\n";
    }

    echo $totalGeral;
    exit;
}

try {
    $pdoMarket = bb_pdo_connect($mkHost, $mkDb, $mkUser, $mkPass);
    if (!bb_cron_guard_database_matches($pdoMarket, $mkDb)) {
        throw new RuntimeException('Falha ao confirmar o banco global do Marketplace.');
    }
    $whereAtivo = bb_tenants_where_ativo($pdoMarket, $mkDb, $tenantsTable);
    $selectTimezone = bb_coluna_existe_schema($pdoMarket, $mkDb, $tenantsTable, 'timezone') ? ', timezone' : '';

    $tenants = $pdoMarket->query("
        SELECT slug, db_name, db_host, db_user, db_pass{$selectTimezone}
          FROM `{$tenantsTable}`
         WHERE {$whereAtivo}
         ORDER BY id ASC
    ")->fetchAll(PDO::FETCH_ASSOC);

    if ($DEBUG) {
        echo 'Tenants encontrados: ' . (is_array($tenants) ? count($tenants) : 0) . "\n";
    }

    foreach ($tenants as $tenantLinha) {
        $slug = bb_limpar_slug((string)($tenantLinha['slug'] ?? ''));
        $dbName = trim((string)($tenantLinha['db_name'] ?? ''));
        $dbHostTenant = trim((string)($tenantLinha['db_host'] ?? ''));

        if ($dbHostTenant === '') {
            $dbHostTenant = trim((string)($cfgMarket['tenant']['db_host'] ?? $cfgMarket['tenant']['host'] ?? $mkHost));
        }

        $dbUserLinha = trim((string)($tenantLinha['db_user'] ?? ''));
        $dbPassLinha = trim((string)($tenantLinha['db_pass'] ?? ''));

        if ($slug === '' || $dbName === '') {
            if ($DEBUG) {
                echo "[tenant] ignorado por slug/db_name vazio\n";
            }

            continue;
        }

        $dbPassLinha = bb_try_decrypt_pass($dbPassLinha, $segredoTenant);

        bb_cron_aplicar_timezone(bb_cron_timezone_de_linha($tenantLinha));

        $credenciais = [];
        $tenantUserPadrao = trim((string)($cfgMarket['tenant']['db_user_default'] ?? $cfgMarket['tenant']['user'] ?? ''));
        $tenantPassPadrao = trim((string)($cfgMarket['tenant']['db_pass_default'] ?? $cfgMarket['tenant']['pass'] ?? ''));

        if ($tenantUserPadrao !== '' && $tenantPassPadrao !== '') {
            $credenciais[] = [
                'label' => 'tenant_user_master_pass',
                'host' => $dbHostTenant,
                'db' => $dbName,
                'user' => $tenantUserPadrao,
                'pass' => $tenantPassPadrao,
            ];
        }

        if ($dbUserLinha !== '' && bb_senha_tenant_utilizavel($dbPassLinha)) {
            $credenciais[] = [
                'label' => 'tenant_linha',
                'host' => $dbHostTenant,
                'db' => $dbName,
                'user' => $dbUserLinha,
                'pass' => $dbPassLinha,
            ];
        }

        if (empty($credenciais) && $mkUser !== '') {
            $credenciais[] = [
                'label' => 'master_fallback',
                'host' => $dbHostTenant,
                'db' => $dbName,
                'user' => $mkUser,
                'pass' => $mkPass,
            ];
        }

        $pdoTenant = null;
        $erroConexao = '';

        foreach ($credenciais as $credencial) {
            try {
                $pdoTenant = bb_pdo_connect(
                    (string)$credencial['host'],
                    (string)$credencial['db'],
                    (string)$credencial['user'],
                    (string)$credencial['pass']
                );

                if (!bb_cron_guard_database_matches($pdoTenant, $dbName)) {
                    $pdoTenant = null;
                    throw new RuntimeException('Banco individual diferente do tenant mapeado.');
                }

                if ($DEBUG) {
                    echo "[{$slug}] conexão OK via " . $credencial['label'] . "\n";
                }

                break;
            } catch (Throwable $e) {
                $erroConexao = $e->getMessage();
            }
        }

        if (!($pdoTenant instanceof PDO)) {
            if ($DEBUG) {
                echo "[{$slug}] erro conexão: {$erroConexao}\n";
            }

            continue;
        }

        try {
            $cfgTenant = bb_fetch_one_config($pdoTenant);

            if (!empty($cfgTenant['timezone'])) {
                bb_cron_aplicar_timezone((string)$cfgTenant['timezone']);
            }

            $totalGeral += bb_run_alerta_manutencao($pdoTenant, $cfgTenant, $slug, true, $DEBUG);
        } catch (Throwable $e) {
            if ($DEBUG) {
                echo "[{$slug}] ERRO execução: " . $e->getMessage() . "\n";
            }
        }
    }
} catch (Throwable $e) {
    if ($DEBUG) {
        echo "ERRO marketplace: " . $e->getMessage() . "\n";
    }
}

echo $totalGeral;
