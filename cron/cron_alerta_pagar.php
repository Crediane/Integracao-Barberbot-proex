<?php
// /public_html/cron/cron_alerta_pagar.php
// BarberBot Marketplace - Alerta WhatsApp de contas a pagar
//
// Ajuste pontual, sem alterar a regra de negócio principal:
// - usa a tabela pagar;
// - seleciona contas que vencem HOJE;
// - ignora contas pagas;
// - respeita hora_alerta quando a coluna existe;
// - respeita alerta pendente quando a coluna existe;
// - ignora comissão de funcionário quando as colunas tipo/funcionario existem;
// - envia para o WhatsApp da própria loja/sistema;
// - só marca alerta='Sim' após retorno positivo da API/Menuia;
// - executa por loja (?loja=slug / --loja=slug) ou all=1 explícito; sem contexto, não consulta banco operacional.
//
// Ponto corrigido:
// - o envio não deve ser bloqueado quando config.msg_agendamento estiver vazio;
//   ele só bloqueia quando existir valor diferente de API.
// - o retorno do texto.php agora é capturado por variável e por echo, mantendo
//   compatibilidade com o padrão já usado nos crons de receber/manutenção.

@set_time_limit(0);

require_once __DIR__ . '/cron_guard.php';

// ======================================================
// Timezone do cron
// ======================================================
if (is_file(__DIR__ . '/timezone_cron.php')) {
    require_once __DIR__ . '/timezone_cron.php';
}

if (function_exists('bb_cron_aplicar_timezone')) {
    bb_cron_aplicar_timezone();
} else {
    @date_default_timezone_set('America/Sao_Paulo');
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
        return trim((string)($linha['timezone'] ?? ''));
    }
}

// ======================================================
// Debug
// ======================================================
$DEBUG = (isset($_GET['debug']) && (string)$_GET['debug'] === '1');

if ($DEBUG && !headers_sent()) {
    @header('Content-Type: text/plain; charset=utf-8');
}

// ======================================================
// Detecta contexto de execução
// ======================================================
$__bb_loja_cli = '';

if (PHP_SAPI === 'cli' && isset($argv) && is_array($argv)) {
    foreach ($argv as $__a) {
        $__a = (string)$__a;

        if (strpos($__a, '--loja=') === 0) {
            $__bb_loja_cli = substr($__a, 7);
            break;
        }

        if (strpos($__a, 'loja=') === 0) {
            $__bb_loja_cli = substr($__a, 5);
            break;
        }
    }
}

$__bb_loja = '';

if (!empty($_GET['loja'])) {
    $__bb_loja = (string)$_GET['loja'];
} elseif (!empty($_POST['loja'])) {
    $__bb_loja = (string)$_POST['loja'];
} elseif ($__bb_loja_cli !== '') {
    $__bb_loja = (string)$__bb_loja_cli;
}

if ($__bb_loja === '') {
    $__bb_reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

    if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath, $m)) {
        $__bb_loja = (string)$m[1];
    }
}

$__bb_loja = preg_replace('/[^a-z0-9_-]/i', '', (string)$__bb_loja);

if ($__bb_loja !== '') {
    $_GET['loja'] = $__bb_loja;
}

$__bb_all_cli = false;

if (PHP_SAPI === 'cli' && isset($argv) && is_array($argv)) {
    foreach ($argv as $__a) {
        $__a = (string)$__a;

        if ($__a === '--all=1' || $__a === 'all=1' || $__a === '--all' || $__a === 'all') {
            $__bb_all_cli = true;
            break;
        }
    }
}

$__bb_all = false;

if (isset($_GET['all']) && (string)$_GET['all'] === '1') {
    $__bb_all = true;
} elseif (isset($_POST['all']) && (string)$_POST['all'] === '1') {
    $__bb_all = true;
} elseif ($__bb_all_cli) {
    $__bb_all = true;
}

// ======================================================
// Lock de execução
// Evita duplicidade se o cPanel disparar o cron enquanto outro ainda roda.
// ======================================================
$__bb_lock_key = 'local';

if ($__bb_loja !== '') {
    $__bb_lock_key = 'slug_' . $__bb_loja;
} elseif ($__bb_all) {
    $__bb_lock_key = 'all';
}

$__bb_lock_dir = sys_get_temp_dir();

if (!is_dir($__bb_lock_dir) || !is_writable($__bb_lock_dir)) {
    $__bb_lock_dir = __DIR__;
}

$__bb_lock_file = rtrim($__bb_lock_dir, '/\\') . DIRECTORY_SEPARATOR . 'bb_cron_alerta_pagar_' . sha1($__bb_lock_key) . '.lock';
$__bb_lock_fp = @fopen($__bb_lock_file, 'c+');

if ($__bb_lock_fp === false) {
    if ($DEBUG) {
        echo "ERRO: não foi possível abrir lock de execução\n";
    }

    echo 0;
    exit;
}

if (!@flock($__bb_lock_fp, LOCK_EX | LOCK_NB)) {
    if ($DEBUG) {
        echo "Execução já em andamento para a chave {$__bb_lock_key}\n";
    }

    @fclose($__bb_lock_fp);
    echo 0;
    exit;
}

register_shutdown_function(static function () use ($__bb_lock_fp): void {
    if (is_resource($__bb_lock_fp)) {
        @flock($__bb_lock_fp, LOCK_UN);
        @fclose($__bb_lock_fp);
    }
});

// ======================================================
// Helpers gerais
// ======================================================
function bb_pdo_connect(string $host, string $db, string $user, string $pass): PDO {
    $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";

    return new PDO($dsn, $user, $pass, [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
        PDO::ATTR_PERSISTENT         => false,
    ]);
}

function bb_get_cfg_marketplace(): array {
    $cfgPath = __DIR__ . '/../config/marketplace.php';

    if (!is_file($cfgPath)) {
        return [];
    }

    $cfg = include $cfgPath;

    return is_array($cfg) ? $cfg : [];
}

function bb_fetch_one_config(PDO $pdo): array {
    try {
        $row = $pdo->query("SELECT * FROM config LIMIT 1")->fetch();

        return is_array($row) ? $row : [];
    } catch (Throwable $e) {
        return [];
    }
}

function bb_limpar_slug(string $slug): string {
    return preg_replace('/[^a-z0-9_-]/i', '', trim($slug));
}

function bb_phone_digits(string $telefone): string {
    $telefone = preg_replace('/[^\d]+/', '', $telefone);
    $telefone = ltrim($telefone, '0');

    return $telefone;
}

function bb_telefone_whatsapp_br(string $telefone): string {
    $telefone = bb_phone_digits($telefone);

    if ($telefone === '') {
        return '';
    }

    if (strpos($telefone, '55') === 0 && strlen($telefone) >= 12) {
        return $telefone;
    }

    return '55' . $telefone;
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

function bb_msg_api_pagar_habilitada(string $msg_agendamento): bool {
    $msg = bb_normalizar_texto_simples($msg_agendamento);

    // Padrão compatível com o restante dos crons:
    // - vazio: não bloqueia; api/token/instancia determinam o envio;
    // - preenchido com "Api", "API", "Api Paga": permite;
    // - preenchido com outro método: bloqueia.
    if ($msg === '') {
        return true;
    }

    return strpos($msg, 'api') !== false;
}

function bb_status_envio_ok(string $status): bool {
    $status = trim($status);

    if ($status === '') {
        return false;
    }

    $statusLower = function_exists('mb_strtolower')
        ? mb_strtolower($status, 'UTF-8')
        : strtolower($status);

    if ($statusLower === 'ok') {
        return true;
    }

    if (strpos($statusLower, 'mensagem enviada com sucesso') !== false) {
        return true;
    }

    if (strpos($statusLower, 'sucesso') !== false) {
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

function bb_tabela_existe(PDO $pdo, string $tabela): bool {
    $tabela = preg_replace('/[^a-z0-9_]/i', '', $tabela);

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
    $tabela = preg_replace('/[^a-z0-9_]/i', '', $tabela);
    $coluna = preg_replace('/[^a-z0-9_]/i', '', $coluna);

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

function bb_coluna_existe_schema(PDO $pdo, string $dbName, string $table, string $column): bool {
    $table = preg_replace('/[^a-z0-9_]/i', '', $table);
    $column = preg_replace('/[^a-z0-9_]/i', '', $column);

    if ($dbName === '' || $table === '' || $column === '') {
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
            ':db'     => $dbName,
            ':tabela' => $table,
            ':coluna' => $column,
        ]);

        return (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function bb_tenants_where_ativo(PDO $pdo, string $dbName, string $table): string {
    $table = preg_replace('/[^a-z0-9_]/i', '', $table);

    if ($table === '') {
        $table = 'tenants';
    }

    $where = '1=1';

    try {
        $st = $pdo->prepare("
            SELECT COLUMN_NAME
              FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = :db
               AND TABLE_NAME = :tabela
               AND COLUMN_NAME IN ('status', 'ativo')
        ");
        $st->execute([
            ':db'     => $dbName,
            ':tabela' => $table,
        ]);

        $cols = $st->fetchAll(PDO::FETCH_COLUMN);

        if (in_array('ativo', $cols, true)) {
            $where = "`ativo` = 1";
        } elseif (in_array('status', $cols, true)) {
            $where = "LOWER(`status`) IN ('active', 'ativo', '1', 'sim')";
        }
    } catch (Throwable $e) {
        // Mantém 1=1 para não derrubar a varredura global.
    }

    return $where;
}

function bb_try_decrypt_pass(string $rawPass, string $secret): string {
    if ($rawPass === '') {
        return '';
    }

    if ($secret === '') {
        return $rawPass;
    }

    if (substr($rawPass, 0, 4) !== 'enc:') {
        return $rawPass;
    }

    $cryptoFile = __DIR__ . '/../sistema/tenant_crypto.php';

    if (is_file($cryptoFile)) {
        require_once $cryptoFile;
    }

    if (function_exists('bb_decrypt_str')) {
        $dec = (string)bb_decrypt_str($rawPass, $secret);

        if ($dec !== '') {
            return $dec;
        }
    }

    return $rawPass;
}

function bb_senha_tenant_utilizavel(string $senha): bool {
    $senha = trim($senha);

    if ($senha === '') {
        return false;
    }

    // Não tenta usar "enc:..." como senha real quando não foi descriptografado.
    return substr($senha, 0, 4) !== 'enc:';
}

function bb_cfg_alerta_pagar(array $cfg): array {
    return [
        'api'               => strtolower(trim((string)($cfg['api'] ?? ''))),
        'token'             => trim((string)($cfg['token'] ?? '')),
        'instancia'         => trim((string)($cfg['instancia'] ?? '')),
        'msg_agendamento'   => trim((string)($cfg['msg_agendamento'] ?? '')),
        'nome_sistema'      => trim((string)($cfg['nome'] ?? 'BarberBot System')),
        'whatsapp_sistema'  => bb_phone_digits((string)($cfg['telefone_whatsapp'] ?? '')),
    ];
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

function bb_data_br(string $data): string {
    $data = trim($data);

    if ($data === '' || $data === '0000-00-00') {
        return '';
    }

    $partes = explode('-', $data);

    if (count($partes) !== 3) {
        return $data;
    }

    return implode('/', array_reverse($partes));
}


function bb_debug_diagnostico_pagar(PDO $pdo, string $ctx, bool $temHoraAlerta, bool $temAlerta, bool $temTipo, bool $temFuncionario): void {
    $dataHoje = bb_cron_data_hoje();
    $horaAgora = bb_cron_hora_agora();

    echo "[{$ctx}] Diagnóstico pagar\n";
    echo "[{$ctx}] Filtro do envio: data_venc={$dataHoje}, pago diferente de Sim"
        . ($temHoraAlerta ? ", hora_alerta <= {$horaAgora}" : "")
        . ($temAlerta ? ", alerta vazio/diferente de Sim" : "")
        . ($temTipo && $temFuncionario ? ", exceto comissão de funcionário" : "")
        . "\n";

    $consultas = [
        'total_geral' => [
            'sql' => "SELECT COUNT(*) FROM pagar",
            'params' => [],
            'label' => 'Total de registros na tabela pagar',
        ],
        'pendentes_hoje_sem_filtros_alerta' => [
            'sql' => "SELECT COUNT(*) FROM pagar WHERE data_venc = :data_hoje AND COALESCE(pago, '') != 'Sim'",
            'params' => [':data_hoje' => $dataHoje],
            'label' => 'Pendentes com vencimento hoje, antes de alerta/hora/tipo',
        ],
        'pendentes_vencidas' => [
            'sql' => "SELECT COUNT(*) FROM pagar WHERE data_venc < :data_hoje AND COALESCE(pago, '') != 'Sim'",
            'params' => [':data_hoje' => $dataHoje],
            'label' => 'Pendentes vencidas antes de hoje',
        ],
        'pendentes_futuras' => [
            'sql' => "SELECT COUNT(*) FROM pagar WHERE data_venc > :data_hoje AND COALESCE(pago, '') != 'Sim'",
            'params' => [':data_hoje' => $dataHoje],
            'label' => 'Pendentes futuras depois de hoje',
        ],
    ];

    if ($temHoraAlerta) {
        $consultas['pendentes_hoje_hora_futura'] = [
            'sql' => "SELECT COUNT(*) FROM pagar WHERE data_venc = :data_hoje AND COALESCE(pago, '') != 'Sim' AND COALESCE(hora_alerta, '00:00:00') > :hora_agora",
            'params' => [':data_hoje' => $dataHoje, ':hora_agora' => $horaAgora],
            'label' => 'Pendentes hoje bloqueadas por hora_alerta futura',
        ];
    }

    if ($temAlerta) {
        $consultas['pendentes_hoje_ja_alertadas'] = [
            'sql' => "SELECT COUNT(*) FROM pagar WHERE data_venc = :data_hoje AND COALESCE(pago, '') != 'Sim' AND alerta = 'Sim'",
            'params' => [':data_hoje' => $dataHoje],
            'label' => 'Pendentes hoje já marcadas como alerta Sim',
        ];
    }

    if ($temTipo && $temFuncionario) {
        $consultas['pendentes_hoje_comissao_funcionario'] = [
            'sql' => "SELECT COUNT(*) FROM pagar WHERE data_venc = :data_hoje AND COALESCE(pago, '') != 'Sim' AND tipo = 'Comissão' AND funcionario > 0",
            'params' => [':data_hoje' => $dataHoje],
            'label' => 'Pendentes hoje ignoradas por serem comissão de funcionário',
        ];
    }

    foreach ($consultas as $consulta) {
        try {
            $stDiag = $pdo->prepare($consulta['sql']);
            $stDiag->execute($consulta['params']);
            $qtd = (int)$stDiag->fetchColumn();
            echo "[{$ctx}] {$consulta['label']}: {$qtd}\n";
        } catch (Throwable $e) {
            echo "[{$ctx}] Falha no diagnóstico ({$consulta['label']}): " . $e->getMessage() . "\n";
        }
    }

    $selectExtras = '';

    if ($temHoraAlerta) {
        $selectExtras .= ', hora_alerta';
    }

    if ($temAlerta) {
        $selectExtras .= ', alerta';
    }

    if ($temTipo) {
        $selectExtras .= ', tipo';
    }

    if ($temFuncionario) {
        $selectExtras .= ', funcionario';
    }

    try {
        $sqlLista = "
            SELECT id, descricao, valor, data_venc, pago{$selectExtras}
              FROM pagar
             WHERE COALESCE(pago, '') != 'Sim'
             ORDER BY data_venc ASC, id ASC
             LIMIT 10
        ";

        $stLista = $pdo->prepare($sqlLista);
        $stLista->execute();
        $linhas = $stLista->fetchAll();

        if (!empty($linhas)) {
            echo "[{$ctx}] Primeiras contas pendentes por vencimento:\n";

            foreach ($linhas as $linha) {
                $partes = [];
                $partes[] = 'id=' . (int)($linha['id'] ?? 0);
                $partes[] = 'venc=' . (string)($linha['data_venc'] ?? '');
                $partes[] = 'pago=' . (string)($linha['pago'] ?? '');
                $partes[] = 'valor=' . number_format((float)($linha['valor'] ?? 0), 2, ',', '.');

                if ($temHoraAlerta) {
                    $partes[] = 'hora_alerta=' . (string)($linha['hora_alerta'] ?? '');
                }

                if ($temAlerta) {
                    $partes[] = 'alerta=' . (string)($linha['alerta'] ?? '');
                }

                if ($temTipo) {
                    $partes[] = 'tipo=' . (string)($linha['tipo'] ?? '');
                }

                if ($temFuncionario) {
                    $partes[] = 'funcionario=' . (string)($linha['funcionario'] ?? '');
                }

                $descricao = trim((string)($linha['descricao'] ?? ''));
                echo "[{$ctx}]   - " . implode(' | ', $partes) . " | descricao={$descricao}\n";
            }
        } else {
            echo "[{$ctx}] Nenhuma conta pendente encontrada para listar\n";
        }
    } catch (Throwable $e) {
        echo "[{$ctx}] Falha ao listar primeiras pendências: " . $e->getMessage() . "\n";
    }
}


// ======================================================
// Regra original do alerta de pagar
// ======================================================
function bb_run_alerta_pagar(PDO $pdo, array $cfg, string $slug, bool $DEBUG): int {
    $ctx = ($slug !== '') ? $slug : 'local';

    if (empty($cfg)) {
        if ($DEBUG) {
            echo "[{$ctx}] config não encontrada\n";
        }

        return 0;
    }

    if (!bb_tabela_existe($pdo, 'pagar')) {
        if ($DEBUG) {
            echo "[{$ctx}] tabela pagar não encontrada\n";
        }

        return 0;
    }

    $conf = bb_cfg_alerta_pagar($cfg);

    $api              = $conf['api'];
    $token            = $conf['token'];
    $instancia        = $conf['instancia'];
    $msg_agendamento  = $conf['msg_agendamento'];
    $nome_sistema     = $conf['nome_sistema'];
    $whatsapp_sistema = $conf['whatsapp_sistema'];

    if ($DEBUG) {
        echo "[{$ctx}] Config API: " . ($api !== '' ? $api : '[vazio]') . "\n";
        echo "[{$ctx}] Config msg_agendamento: " . ($msg_agendamento !== '' ? $msg_agendamento : '[vazio]') . "\n";
        echo "[{$ctx}] Config instancia preenchida: " . ($instancia !== '' ? 'sim' : 'nao') . "\n";
        echo "[{$ctx}] Config token preenchido: " . ($token !== '' ? 'sim' : 'nao') . "\n";
        echo "[{$ctx}] Config telefone_whatsapp preenchido: " . ($whatsapp_sistema !== '' ? 'sim' : 'nao') . "\n";
    }

    if (!bb_msg_api_pagar_habilitada($msg_agendamento)) {
        if ($DEBUG) {
            echo "[{$ctx}] envio por API desativado em config.msg_agendamento\n";
        }

        return 0;
    }

    if ($whatsapp_sistema === '') {
        if ($DEBUG) {
            echo "[{$ctx}] telefone_whatsapp vazio na config\n";
        }

        return 0;
    }

    if ($api === '' || $token === '' || $instancia === '') {
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

    $temHoraAlerta = bb_coluna_existe($pdo, 'pagar', 'hora_alerta');
    $temAlerta = bb_coluna_existe($pdo, 'pagar', 'alerta');
    $temTipo = bb_coluna_existe($pdo, 'pagar', 'tipo');
    $temFuncionario = bb_coluna_existe($pdo, 'pagar', 'funcionario');

    $where = [];
    $where[] = "data_venc = :data_hoje";
    $where[] = "COALESCE(pago, '') != 'Sim'";

    if ($temHoraAlerta) {
        $where[] = "COALESCE(hora_alerta, '00:00:00') <= :hora_agora";
    }

    if ($temAlerta) {
        $where[] = "(alerta IS NULL OR alerta != 'Sim')";
    }

    if ($temTipo && $temFuncionario) {
        $where[] = "NOT (tipo = 'Comissão' AND funcionario > 0)";
    }

    $sql = "
        SELECT id, valor, descricao, data_venc
          FROM pagar
         WHERE " . implode("\n           AND ", $where) . "
         ORDER BY id ASC
    ";

    try {
        $st = $pdo->prepare($sql);

        $params = [
            ':data_hoje' => bb_cron_data_hoje(),
        ];

        if ($temHoraAlerta) {
            $params[':hora_agora'] = bb_cron_hora_agora();
        }

        $st->execute($params);
        $rows = $st->fetchAll();
    } catch (Throwable $e) {
        if ($DEBUG) {
            echo "[{$ctx}] erro ao consultar pagar: " . $e->getMessage() . "\n";
        }

        return 0;
    }

    $totalRegistros = is_array($rows) ? count($rows) : 0;

    if ($totalRegistros <= 0) {
        if ($DEBUG) {
            echo "[{$ctx}] 0 conta(s) a pagar para alertar hoje\n";
            bb_debug_diagnostico_pagar($pdo, $ctx, $temHoraAlerta, $temAlerta, $temTipo, $temFuncionario);
        }

        return 0;
    }

    if ($DEBUG) {
        echo "[{$ctx}] {$totalRegistros} conta(s) candidata(s)\n";
    }

    $sqlUpdate = $temAlerta
        ? "UPDATE pagar SET alerta = 'Sim' WHERE id = :id"
        : "";

    $stUpd = null;

    if ($sqlUpdate !== '') {
        $stUpd = $pdo->prepare($sqlUpdate);
    }

    $totalEnviados = 0;

    foreach ($rows as $row) {
        $id = (int)($row['id'] ?? 0);

        if ($id <= 0) {
            continue;
        }

        $descricao = trim((string)($row['descricao'] ?? ''));
        $valor = (float)($row['valor'] ?? 0);
        $valorF = number_format($valor, 2, ',', '.');
        $dataVenc = (string)($row['data_venc'] ?? '');
        $dataVencF = bb_data_br($dataVenc);
        $telefone = bb_telefone_whatsapp_br($whatsapp_sistema);

        $mensagem  = '💰 *' . $nome_sistema . '*%0A';
        $mensagem .= '_Conta Vencendo Hoje_ %0A';
        $mensagem .= '*Descrição:* ' . $descricao . ' %0A';
        $mensagem .= '*Valor:* R$ ' . $valorF . ' %0A';

        if ($dataVencF !== '') {
            $mensagem .= '*Vencimento:* ' . $dataVencF . ' %0A';
        }

        $status_mensagem = '';
        $echoTexto = '';

        ob_start();

        try {
            include $arquivoTexto;
            $echoTexto = trim((string)ob_get_clean());
        } catch (Throwable $e) {
            if (ob_get_level() > 0) {
                $echoTexto = trim((string)ob_get_clean());
            }

            $status_mensagem = 'ERRO: ' . $e->getMessage();
        }

        $statusFinal = bb_normalizar_status_retorno($status_mensagem, $echoTexto);
        $podeMarcar = true;

        if ($api === 'menuia') {
            $podeMarcar = bb_status_envio_ok($statusFinal);
        }

        if ($DEBUG) {
            echo "[{$ctx}] conta id={$id} retorno status_mensagem: " . ($status_mensagem !== '' ? $status_mensagem : '[vazio]') . "\n";
            echo "[{$ctx}] conta id={$id} retorno echo texto.php: " . ($echoTexto !== '' ? $echoTexto : '[vazio]') . "\n";
            echo "[{$ctx}] conta id={$id} status final: " . ($statusFinal !== '' ? $statusFinal : '[vazio]') . "\n";
        }

        if ($podeMarcar) {
            if ($stUpd instanceof PDOStatement) {
                $stUpd->execute([':id' => $id]);
            }

            $totalEnviados++;

            if ($DEBUG) {
                echo "[{$ctx}] OK: alerta marcado id={$id}\n";
            }
        } else {
            if ($DEBUG) {
                echo "[{$ctx}] FALHA envio/API id={$id}\n";
            }
        }
    }

    if ($DEBUG) {
        echo "[{$ctx}] Registros encontrados: {$totalRegistros}\n";
        echo "[{$ctx}] Alertas enviados: {$totalEnviados}\n";
    }

    return $totalEnviados;
}

// ======================================================
// Execução
// ======================================================
$cfgMarket = bb_get_cfg_marketplace();

if ($DEBUG) {
    echo "DEBUG: agora=" . bb_cron_data_hora_agora() . " slug=" . ($__bb_loja !== '' ? $__bb_loja : '[vazio]') . " all=" . ($__bb_all ? '1' : '0') . "\n";
}

// O bootstrap do sistema é carregado somente quando existe slug específico.
// No all=1, a varredura global usa marketplace.php e conecta banco por banco.
if ($__bb_loja !== '') {
    require_once __DIR__ . '/../sistema/conexao.php';
}

// 1) Loja específica por slug
if ($__bb_loja !== '') {
    $pdoExec = null;

    if (function_exists('conectarBancoPorSlug')) {
        try {
            $pdoExec = conectarBancoPorSlug($__bb_loja);
        } catch (Throwable $e) {
            if ($DEBUG) {
                echo "[{$__bb_loja}] falha no conectarBancoPorSlug: " . $e->getMessage() . "\n";
            }
        }
    }

    $tenantDbEsperado = trim((string)($_SESSION['tenant_db'] ?? ''));
    if ($pdoExec instanceof PDO && !bb_cron_guard_tenant_context($pdoExec, $__bb_loja, $tenantDbEsperado)) {
        $pdoExec = null;
    }

    if ($pdoExec instanceof PDO) {
        $cfgLocal = bb_fetch_one_config($pdoExec);
        $qtde = bb_run_alerta_pagar($pdoExec, $cfgLocal, $__bb_loja, $DEBUG);
        echo $qtde;
    } else {
        if ($DEBUG) {
            echo "[{$__bb_loja}] não foi possível abrir o banco da loja\n";
        }

        echo 0;
    }

    exit;
}

// 2) Sem slug e sem all=1, não consulta tabelas operacionais no banco Master.
if (!$__bb_all) {
    if ($DEBUG) {
        echo "Informe loja=<slug> ou all=1. Nenhum banco de barbearia foi selecionado.\n";
    }

    echo 0;
    exit;
}

// 3) all=1 explícito: varre tenants do marketplace
if (empty($cfgMarket) || empty($cfgMarket['master']) || !is_array($cfgMarket['master'])) {
    if ($DEBUG) {
        echo "ERRO: marketplace.php ausente/inválido para varredura global\n";
    }

    echo 0;
    exit;
}

$mk = $cfgMarket['master'];

$tenantsTable = preg_replace('/[^a-z0-9_]/i', '', (string)($mk['table'] ?? 'tenants'));

if ($tenantsTable === '') {
    $tenantsTable = 'tenants';
}

$mkHost = (string)($mk['host'] ?? 'localhost');
$mkDb   = (string)($mk['db'] ?? '');
$mkUser = (string)($mk['user'] ?? '');
$mkPass = (string)($mk['pass'] ?? '');

$secret = (string)($cfgMarket['security']['tenant_secret'] ?? '');

if ($mkDb === '' || $mkUser === '') {
    if ($DEBUG) {
        echo "ERRO: config master incompleta\n";
    }

    echo 0;
    exit;
}

$totalGeral = 0;

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
    ")->fetchAll();

    if ($DEBUG) {
        echo "Tenants encontrados: " . (is_array($tenants) ? count($tenants) : 0) . "\n";
    }

    foreach ($tenants as $t) {
        $slug = bb_limpar_slug((string)($t['slug'] ?? ''));
        $dbName = trim((string)($t['db_name'] ?? ''));
        $tHost = trim((string)($t['db_host'] ?? ''));

        if ($tHost === '') {
            $tHost = (string)($cfgMarket['tenant']['db_host'] ?? $cfgMarket['tenant']['host'] ?? $mkHost);
        }

        $tUserLinha = trim((string)($t['db_user'] ?? ''));
        $tPassLinha = trim((string)($t['db_pass'] ?? ''));

        if ($slug === '' || $dbName === '') {
            if ($DEBUG) {
                echo "[tenant] ignorado por slug/db_name vazio\n";
            }

            continue;
        }

        $tPassLinha = bb_try_decrypt_pass($tPassLinha, $secret);

        if (function_exists('bb_cron_aplicar_timezone')) {
            bb_cron_aplicar_timezone(bb_cron_timezone_de_linha($t));
        }

        $credenciais = [];

        $tenantUserPadrao = trim((string)($cfgMarket['tenant']['db_user_default'] ?? $cfgMarket['tenant']['user'] ?? ''));
        $tenantPassPadrao = trim((string)($cfgMarket['tenant']['db_pass_default'] ?? $cfgMarket['tenant']['pass'] ?? ''));

        // Primeiro tenta a credencial padrão oficial dos bancos das lojas.
        if ($tenantUserPadrao !== '' && $tenantPassPadrao !== '') {
            $credenciais[] = [
                'label' => 'tenant_user_master_pass',
                'host'  => $tHost,
                'db'    => $dbName,
                'user'  => $tenantUserPadrao,
                'pass'  => $tenantPassPadrao,
            ];
        }

        // Depois tenta a credencial gravada na linha do tenant.
        if ($tUserLinha !== '' && bb_senha_tenant_utilizavel($tPassLinha)) {
            $credenciais[] = [
                'label' => 'tenant_linha',
                'host'  => $tHost,
                'db'    => $dbName,
                'user'  => $tUserLinha,
                'pass'  => $tPassLinha,
            ];
        }

        // Fallback compatível com crons antigos: usa usuário/senha do master quando não há credencial de tenant.
        if (empty($credenciais) && $mkUser !== '') {
            $credenciais[] = [
                'label' => 'master_fallback',
                'host'  => $tHost,
                'db'    => $dbName,
                'user'  => $mkUser,
                'pass'  => $mkPass,
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
                    echo "[{$slug}] conexão OK via {$credencial['label']}\n";
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

            if (!empty($cfgTenant['timezone']) && function_exists('bb_cron_aplicar_timezone')) {
                bb_cron_aplicar_timezone((string)$cfgTenant['timezone']);
            }

            $totalGeral += bb_run_alerta_pagar($pdoTenant, $cfgTenant, $slug, $DEBUG);
        } catch (Throwable $e) {
            if ($DEBUG) {
                echo "[{$slug}] erro execução: " . $e->getMessage() . "\n";
            }
        }
    }
} catch (Throwable $e) {
    if ($DEBUG) {
        echo "ERRO marketplace: " . $e->getMessage() . "\n";
    }
}

echo $totalGeral;
