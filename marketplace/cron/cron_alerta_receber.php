<?php
// /public_html/cron/cron_alerta_receber.php
// BarberBot Marketplace - Alerta WhatsApp de contas a receber
//
// Ajuste pontual, sem alterar a regra de negócio financeira:
// - usa a tabela receber;
// - envia alerta para o cliente vinculado em receber.pessoa ou receber.cliente;
// - considera contas pendentes vencidas, vencendo hoje e futuras até dias_alerta_vencimento;
// - respeita hora_alerta quando a coluna existe;
// - respeita data_alerta para evitar novo disparo no mesmo dia;
// - mantém 1 mensagem por cliente por execução;
// - mantém link público individual da conta;
// - só marca alerta/data_alerta após retorno positivo da API/Menuia;
// - executa por loja (?loja=slug / --loja=slug) ou all=1 explícito; sem contexto, não consulta banco operacional.
//
// Ponto corrigido neste arquivo:
// - mantém a lógica atual de contas a receber, mas adiciona diagnóstico detalhado;
// - evita carregar o bootstrap da loja no modo all=1, seguindo o padrão dos crons multi-tenant;
// - usa o mesmo padrão de retorno do texto.php/Menuia usado nos demais crons;
// - preserva data_alerta como trava diária, sem bloquear permanentemente por alerta='Sim'.

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
$__bb_debug_cli = false;

if (PHP_SAPI === 'cli' && isset($argv) && is_array($argv)) {
    foreach ($argv as $__a) {
        $__a = (string)$__a;

        if ($__a === '--debug=1' || $__a === 'debug=1' || $__a === '--debug' || $__a === 'debug') {
            $__bb_debug_cli = true;
            break;
        }
    }
}

$DEBUG = false;

if (isset($_GET['debug']) && (string)$_GET['debug'] === '1') {
    $DEBUG = true;
} elseif (isset($_POST['debug']) && (string)$_POST['debug'] === '1') {
    $DEBUG = true;
} elseif ($__bb_debug_cli) {
    $DEBUG = true;
}

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

$__bb_lock_file = rtrim($__bb_lock_dir, '/\\') . DIRECTORY_SEPARATOR . 'bb_cron_alerta_receber_' . sha1($__bb_lock_key) . '.lock';
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

function bb_msg_api_receber_habilitada(string $msg_agendamento): bool {
    $msg = bb_normalizar_texto_simples($msg_agendamento);

    // Compatível com o padrão já usado no sistema:
    // - vazio: não bloqueia; api/token/instancia determinam o envio;
    // - api/menuia/sim/1/true: envio habilitado;
    // - nao/não/desativado/off/0/false: envio bloqueado.
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

function bb_data_br(string $data): string {
    $data = trim($data);

    if ($data === '' || $data === '0000-00-00') {
        return '';
    }

    if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
        return '';
    }

    return implode('/', array_reverse(explode('-', $data)));
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
            ':db' => $dbName,
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

    $where = "1=1";

    try {
        $st = $pdo->prepare("
            SELECT COLUMN_NAME
              FROM INFORMATION_SCHEMA.COLUMNS
             WHERE TABLE_SCHEMA = :db
               AND TABLE_NAME = :tabela
               AND COLUMN_NAME IN ('status','ativo')
        ");
        $st->execute([
            ':db' => $dbName,
            ':tabela' => $table,
        ]);
        $cols = $st->fetchAll(PDO::FETCH_COLUMN);

        if (in_array('ativo', $cols, true)) {
            $where = "`ativo` = 1";
        } elseif (in_array('status', $cols, true)) {
            $where = "`status` = 'active'";
        }
    } catch (Throwable $e) {
        // Mantém 1=1.
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

    if (strpos($rawPass, 'enc:') !== 0) {
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

    if (strpos($senha, 'enc:') === 0) {
        return false;
    }

    return true;
}

function bb_base_publica(string $urlSistema, string $slug, bool $isTenant): string {
    $scheme = 'https';
    $host = '';
    $path = '';

    $urlSistema = trim($urlSistema);

    if ($urlSistema !== '') {
        $parts = @parse_url($urlSistema);

        if (is_array($parts)) {
            if (!empty($parts['scheme'])) {
                $scheme = (string)$parts['scheme'];
            }

            if (!empty($parts['host'])) {
                $host = (string)$parts['host'];
            }

            if (!empty($parts['port'])) {
                $host .= ':' . (int)$parts['port'];
            }

            if (!empty($parts['path'])) {
                $path = (string)$parts['path'];
            }
        }
    }

    if ($host === '') {
        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    }

    $raiz = rtrim(($host !== '' ? ($scheme . '://' . $host) : ''), '/');
    $slug = bb_limpar_slug($slug);

    if ($isTenant && $slug === '' && $path !== '') {
        if (preg_match('~(?:^|/)loja/([a-z0-9_-]+)(?:/|$)~i', $path, $m)) {
            $slug = bb_limpar_slug((string)$m[1]);
        }
    }

    if ($isTenant && $slug !== '') {
        return $raiz . '/loja/' . $slug;
    }

    return $raiz;
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

function bb_cfg_alerta_receber(array $cfg): array {
    return [
        'api'              => strtolower(trim((string)($cfg['api'] ?? ''))),
        'token'            => trim((string)($cfg['token'] ?? '')),
        'instancia'        => trim((string)($cfg['instancia'] ?? '')),
        'msg_agendamento'  => trim((string)($cfg['msg_agendamento'] ?? '')),
        'nome_sistema'     => trim((string)($cfg['nome'] ?? 'BarberBot System')),
        'url_sistema'      => trim((string)($cfg['url_sistema'] ?? '')),
        'dias_alerta'      => isset($cfg['dias_alerta_vencimento']) ? (int)$cfg['dias_alerta_vencimento'] : 0,
    ];
}

function bb_expr_cliente_receber(bool $temPessoa, bool $temCliente): string {
    if ($temPessoa && $temCliente) {
        return "COALESCE(NULLIF(r.pessoa, 0), NULLIF(r.cliente, 0))";
    }

    if ($temPessoa) {
        return "NULLIF(r.pessoa, 0)";
    }

    if ($temCliente) {
        return "NULLIF(r.cliente, 0)";
    }

    return "NULL";
}

// ======================================================
// Diagnóstico do receber
// ======================================================
function bb_debug_exec_count(PDO $pdo, string $label, string $sql, array $params, string $ctx): void {
    try {
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $total = (int)$st->fetchColumn();

        echo "[{$ctx}] {$label}: {$total}\n";
    } catch (Throwable $e) {
        echo "[{$ctx}] {$label}: erro - " . $e->getMessage() . "\n";
    }
}

function bb_debug_diagnostico_receber(
    PDO $pdo,
    string $ctx,
    string $dataHoje,
    string $horaAgora,
    string $dataLimite,
    string $exprCliente,
    bool $temHoraAlerta,
    bool $temDataAlerta,
    bool $temPessoa,
    bool $temCliente
): void {
    echo "[{$ctx}] Diagnóstico receber\n";
    echo "[{$ctx}] Filtro do envio: data_venc <= {$dataLimite}, pago diferente de Sim";

    if ($temHoraAlerta) {
        echo ", hora_alerta <= {$horaAgora}";
    }

    if ($temDataAlerta) {
        echo ", data_alerta vazia ou anterior a {$dataHoje}";
    }

    if ($temPessoa || $temCliente) {
        echo ", cliente vinculado por receber.pessoa/receber.cliente";
    }

    echo "\n";

    bb_debug_exec_count(
        $pdo,
        'Total de registros na tabela receber',
        "SELECT COUNT(*) FROM receber",
        [],
        $ctx
    );

    bb_debug_exec_count(
        $pdo,
        'Pendentes vencidas antes de alerta/hora/cliente',
        "
            SELECT COUNT(*)
              FROM receber r
             WHERE r.data_venc < :data_hoje
               AND COALESCE(r.pago, '') != 'Sim'
        ",
        [':data_hoje' => $dataHoje],
        $ctx
    );

    bb_debug_exec_count(
        $pdo,
        'Pendentes com vencimento hoje',
        "
            SELECT COUNT(*)
              FROM receber r
             WHERE r.data_venc = :data_hoje
               AND COALESCE(r.pago, '') != 'Sim'
        ",
        [':data_hoje' => $dataHoje],
        $ctx
    );

    bb_debug_exec_count(
        $pdo,
        'Pendentes futuras até data limite',
        "
            SELECT COUNT(*)
              FROM receber r
             WHERE r.data_venc > :data_hoje
               AND r.data_venc <= :data_limite
               AND COALESCE(r.pago, '') != 'Sim'
        ",
        [
            ':data_hoje' => $dataHoje,
            ':data_limite' => $dataLimite,
        ],
        $ctx
    );

    if ($temHoraAlerta) {
        bb_debug_exec_count(
            $pdo,
            'Pendentes bloqueadas por hora_alerta futura',
            "
                SELECT COUNT(*)
                  FROM receber r
                 WHERE r.data_venc <= :data_limite
                   AND COALESCE(r.pago, '') != 'Sim'
                   AND COALESCE(r.hora_alerta, '00:00:00') > :hora_agora
            ",
            [
                ':data_limite' => $dataLimite,
                ':hora_agora' => $horaAgora,
            ],
            $ctx
        );
    }

    if ($temDataAlerta) {
        bb_debug_exec_count(
            $pdo,
            'Pendentes já alertadas hoje',
            "
                SELECT COUNT(*)
                  FROM receber r
                 WHERE r.data_venc <= :data_limite
                   AND COALESCE(r.pago, '') != 'Sim'
                   AND r.data_alerta = :data_hoje
            ",
            [
                ':data_limite' => $dataLimite,
                ':data_hoje' => $dataHoje,
            ],
            $ctx
        );
    }

    if ($temPessoa || $temCliente) {
        bb_debug_exec_count(
            $pdo,
            'Pendentes sem cliente vinculado',
            "
                SELECT COUNT(*)
                  FROM receber r
                 WHERE r.data_venc <= :data_limite
                   AND COALESCE(r.pago, '') != 'Sim'
                   AND {$exprCliente} IS NULL
            ",
            [':data_limite' => $dataLimite],
            $ctx
        );

        bb_debug_exec_count(
            $pdo,
            'Pendentes com cliente sem telefone',
            "
                SELECT COUNT(*)
                  FROM receber r
                  JOIN clientes c ON c.id = {$exprCliente}
                 WHERE r.data_venc <= :data_limite
                   AND COALESCE(r.pago, '') != 'Sim'
                   AND (c.telefone IS NULL OR TRIM(c.telefone) = '')
            ",
            [':data_limite' => $dataLimite],
            $ctx
        );
    }

    echo "[{$ctx}] Primeiras contas pendentes por vencimento:\n";

    try {
        $sql = "
            SELECT
                r.id,
                r.descricao,
                r.valor,
                r.data_venc,
                r.pago,
                " . ($temHoraAlerta ? "r.hora_alerta" : "NULL AS hora_alerta") . ",
                " . ($temDataAlerta ? "r.data_alerta" : "NULL AS data_alerta") . ",
                {$exprCliente} AS id_cliente,
                c.nome AS nome_cliente,
                c.telefone AS telefone_cliente
            FROM receber r
            LEFT JOIN clientes c ON c.id = {$exprCliente}
            WHERE r.data_venc <= :data_limite
              AND COALESCE(r.pago, '') != 'Sim'
            ORDER BY r.data_venc ASC, r.id ASC
            LIMIT 10
        ";

        $st = $pdo->prepare($sql);
        $st->execute([':data_limite' => $dataLimite]);
        $rows = $st->fetchAll();

        if (!$rows) {
            echo "[{$ctx}] - nenhuma conta pendente até a data limite\n";
            return;
        }

        foreach ($rows as $row) {
            $id = (int)($row['id'] ?? 0);
            $venc = (string)($row['data_venc'] ?? '');
            $pago = (string)($row['pago'] ?? '');
            $valor = number_format((float)($row['valor'] ?? 0), 2, ',', '.');
            $horaAlerta = (string)($row['hora_alerta'] ?? '');
            $dataAlerta = (string)($row['data_alerta'] ?? '');
            $idCliente = (string)($row['id_cliente'] ?? '');
            $nomeCliente = trim((string)($row['nome_cliente'] ?? ''));
            $telefoneCliente = bb_phone_digits((string)($row['telefone_cliente'] ?? ''));
            $descricao = trim((string)($row['descricao'] ?? ''));

            echo "[{$ctx}] - id={$id}"
                . " | venc={$venc}"
                . " | pago={$pago}"
                . " | valor={$valor}"
                . " | hora_alerta={$horaAlerta}"
                . " | data_alerta={$dataAlerta}"
                . " | cliente={$idCliente}"
                . " | telefone=" . ($telefoneCliente !== '' ? 'sim' : 'nao')
                . " | nome={$nomeCliente}"
                . " | descricao={$descricao}"
                . "\n";
        }
    } catch (Throwable $e) {
        echo "[{$ctx}] erro diagnóstico detalhado receber: " . $e->getMessage() . "\n";
    }
}

// ======================================================
// Regra de negócio: alerta de receber
// ======================================================
function bb_run_alerta_receber(PDO $pdo, array $cfg, string $slug, bool $isTenant, bool $DEBUG): int {
    $ctx = ($slug !== '') ? $slug : 'local';

    if (empty($cfg)) {
        if ($DEBUG) {
            echo "[{$ctx}] config não encontrada\n";
        }

        return 0;
    }

    if (!bb_tabela_existe($pdo, 'receber')) {
        if ($DEBUG) {
            echo "[{$ctx}] tabela receber não encontrada\n";
        }

        return 0;
    }

    if (!bb_tabela_existe($pdo, 'clientes')) {
        if ($DEBUG) {
            echo "[{$ctx}] tabela clientes não encontrada\n";
        }

        return 0;
    }

    $temPessoa = bb_coluna_existe($pdo, 'receber', 'pessoa');
    $temCliente = bb_coluna_existe($pdo, 'receber', 'cliente');
    $temHoraAlerta = bb_coluna_existe($pdo, 'receber', 'hora_alerta');
    $temDataAlerta = bb_coluna_existe($pdo, 'receber', 'data_alerta');
    $temAlerta = bb_coluna_existe($pdo, 'receber', 'alerta');

    if (!$temPessoa && !$temCliente) {
        if ($DEBUG) {
            echo "[{$ctx}] tabela receber não possui coluna pessoa nem cliente\n";
        }

        return 0;
    }

    $conf = bb_cfg_alerta_receber($cfg);

    $api = $conf['api'];
    $token = $conf['token'];
    $instancia = $conf['instancia'];
    $msgAgendamento = $conf['msg_agendamento'];
    $nomeSistema = $conf['nome_sistema'];
    $urlSistema = $conf['url_sistema'];
    $diasAlerta = (int)$conf['dias_alerta'];

    if ($diasAlerta < 0) {
        $diasAlerta = 0;
    }

    if ($DEBUG) {
        echo "[{$ctx}] Config API: " . ($api !== '' ? $api : '[vazio]') . "\n";
        echo "[{$ctx}] Config msg_agendamento: " . ($msgAgendamento !== '' ? $msgAgendamento : '[vazio]') . "\n";
        echo "[{$ctx}] Config instancia preenchida: " . ($instancia !== '' ? 'sim' : 'nao') . "\n";
        echo "[{$ctx}] Config token preenchido: " . ($token !== '' ? 'sim' : 'nao') . "\n";
        echo "[{$ctx}] Config dias_alerta_vencimento: {$diasAlerta}\n";
    }

    if (!bb_msg_api_receber_habilitada($msgAgendamento)) {
        if ($DEBUG) {
            echo "[{$ctx}] envio por API desativado em config.msg_agendamento\n";
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

    $hoje = bb_cron_data_hoje();
    $horaAgora = bb_cron_hora_agora();

    // Mantém a regra configurada em dias_alerta_vencimento:
    // - contas vencidas entram porque data_venc <= data limite;
    // - contas vencendo hoje entram;
    // - contas futuras entram até o limite escolhido na configuração.
    $dataLimite = $hoje;

    if ($diasAlerta > 0) {
        $dataLimite = date('Y-m-d', strtotime("+{$diasAlerta} day", strtotime($hoje)));
    }

    $exprCliente = bb_expr_cliente_receber($temPessoa, $temCliente);

    $where = [];
    $where[] = "COALESCE(r.pago, '') != 'Sim'";
    $where[] = "{$exprCliente} IS NOT NULL";

    if ($temHoraAlerta) {
        $where[] = "COALESCE(r.hora_alerta, '00:00:00') <= :hora_alerta";
    }

    if ($temDataAlerta) {
        $where[] = "(r.data_alerta IS NULL OR r.data_alerta < :data_hoje)";
    }

    $where[] = "r.data_venc IS NOT NULL";
    $where[] = "r.data_venc <> '0000-00-00'";
    $where[] = "r.data_venc <= :data_limite";

    $sql = "
        SELECT
            r.id,
            r.descricao,
            r.valor,
            r.data_venc,
            " . ($temPessoa ? "r.pessoa" : "NULL AS pessoa") . ",
            " . ($temCliente ? "r.cliente" : "NULL AS cliente") . ",
            {$exprCliente} AS id_cliente,
            c.nome AS nome_cliente,
            c.telefone AS telefone_cliente
        FROM receber r
        INNER JOIN clientes c ON c.id = {$exprCliente}
        WHERE " . implode("\n          AND ", $where) . "
        ORDER BY id_cliente ASC, r.data_venc ASC, r.id ASC
    ";

    try {
        $stmt = $pdo->prepare($sql);

        $params = [
            ':data_limite' => $dataLimite,
        ];

        if ($temHoraAlerta) {
            $params[':hora_alerta'] = $horaAgora;
        }

        if ($temDataAlerta) {
            $params[':data_hoje'] = $hoje;
        }

        $stmt->execute($params);
        $rows = $stmt->fetchAll();
    } catch (Throwable $e) {
        if ($DEBUG) {
            echo "[{$ctx}] erro ao consultar receber: " . $e->getMessage() . "\n";
        }

        return 0;
    }

    if (!$rows) {
        if ($DEBUG) {
            echo "[{$ctx}] 0 conta(s) a receber para alertar agora até {$dataLimite}\n";
            bb_debug_diagnostico_receber(
                $pdo,
                $ctx,
                $hoje,
                $horaAgora,
                $dataLimite,
                $exprCliente,
                $temHoraAlerta,
                $temDataAlerta,
                $temPessoa,
                $temCliente
            );
        }

        return 0;
    }

    if ($DEBUG) {
        echo "[{$ctx}] " . count($rows) . " conta(s) a receber candidata(s) até {$dataLimite}\n";
    }

    // Mantém o padrão: 1 mensagem por cliente por execução.
    // Se o cliente tiver mais de uma conta elegível, a mensagem lista os links individuais.
    $contasPorCliente = [];

    foreach ($rows as $row) {
        $idCliente = (int)($row['id_cliente'] ?? 0);
        $idReceber = (int)($row['id'] ?? 0);

        if ($idCliente <= 0 || $idReceber <= 0) {
            continue;
        }

        if (!isset($contasPorCliente[$idCliente])) {
            $contasPorCliente[$idCliente] = [];
        }

        $contasPorCliente[$idCliente][] = $row;
    }

    if (empty($contasPorCliente)) {
        if ($DEBUG) {
            echo "[{$ctx}] 0 contas válidas após agrupamento por cliente\n";
        }

        return 0;
    }

    $base = bb_base_publica($urlSistema, $slug, $isTenant);
    $totalEnviados = 0;

    foreach ($contasPorCliente as $idCliente => $contasCliente) {
        $primeiraConta = $contasCliente[0] ?? [];

        $nomeCliente = trim((string)($primeiraConta['nome_cliente'] ?? 'Cliente'));

        if ($nomeCliente === '') {
            $nomeCliente = 'Cliente';
        }

        $telefone = bb_telefone_whatsapp_br((string)($primeiraConta['telefone_cliente'] ?? ''));

        if ($telefone === '') {
            if ($DEBUG) {
                echo "[{$ctx}] cliente={$idCliente} sem telefone válido\n";
            }

            continue;
        }

        $mensagem  = '💰 *' . $nomeSistema . '*%0A';
        $mensagem .= 'Olá, ' . $nomeCliente . ', tudo bem?%0A%0A';
        $mensagem .= 'Identificamos conta(s) pendente(s) no sistema.%0A';
        $mensagem .= 'Segue o link para pagamento:%0A%0A';

        $idsParaMarcar = [];
        $contador = 1;

        foreach ($contasCliente as $conta) {
            $idReceber = (int)($conta['id'] ?? 0);

            if ($idReceber <= 0) {
                continue;
            }

            $idsParaMarcar[] = $idReceber;

            $dataVenc = (string)($conta['data_venc'] ?? '');
            $dataVencF = bb_data_br($dataVenc);
            $textoVenc = 'vence hoje';

            if ($dataVenc !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataVenc)) {
                $diff = (int)floor((strtotime($dataVenc) - strtotime($hoje)) / 86400);

                if ($diff < 0) {
                    $diasAtraso = abs($diff);
                    $textoVenc = ($diasAtraso === 1) ? 'vencida há 1 dia' : "vencida há {$diasAtraso} dias";
                } elseif ($diff === 0) {
                    $textoVenc = 'vence hoje';
                } else {
                    $textoVenc = ($diff === 1) ? 'vence em 1 dia' : "vence em {$diff} dias";
                }
            }

            $valor = (float)($conta['valor'] ?? 0);
            $valorF = number_format($valor, 2, ',', '.');
            $descricao = trim((string)($conta['descricao'] ?? 'Conta'));

            if ($descricao === '') {
                $descricao = 'Conta';
            }

            $linkPgto = rtrim($base, '/') . '/conta/' . $idReceber;

            $mensagem .= $contador . ') ' . $descricao . '%0A';
            $mensagem .= 'Status: ' . $textoVenc;

            if ($dataVencF !== '') {
                $mensagem .= ' (' . $dataVencF . ')';
            }

            $mensagem .= '%0A';
            $mensagem .= 'Valor: R$ ' . $valorF . '%0A';
            $mensagem .= 'Link: ' . $linkPgto . '%0A%0A';

            $contador++;
        }

        if (empty($idsParaMarcar)) {
            continue;
        }

        $mensagem .= 'Qualquer dúvida, é só responder esta mensagem.';

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
            echo "[{$ctx}] cliente={$idCliente} retorno status_mensagem: " . ($status_mensagem !== '' ? $status_mensagem : '[vazio]') . "\n";
            echo "[{$ctx}] cliente={$idCliente} retorno echo texto.php: " . ($echoTexto !== '' ? $echoTexto : '[vazio]') . "\n";
            echo "[{$ctx}] cliente={$idCliente} status final: " . ($statusFinal !== '' ? $statusFinal : '[vazio]') . "\n";
        }

        if ($podeMarcar) {
            $camposUpdate = [];

            if ($temAlerta) {
                $camposUpdate[] = "alerta = 'Sim'";
            }

            if ($temDataAlerta) {
                $camposUpdate[] = "data_alerta = :data_alerta";
            }

            if ($temHoraAlerta) {
                $camposUpdate[] = "hora_alerta = :hora_alerta_update";
            }

            if (!empty($camposUpdate)) {
                $marcadores = [];
                $parametros = [];

                if ($temDataAlerta) {
                    $parametros[':data_alerta'] = $hoje;
                    $parametros[':data_hoje'] = $hoje;
                }

                if ($temHoraAlerta) {
                    $parametros[':hora_alerta_update'] = $horaAgora;
                }

                foreach ($idsParaMarcar as $indice => $idReceberMarcar) {
                    $chave = ':id_' . $indice;
                    $marcadores[] = $chave;
                    $parametros[$chave] = (int)$idReceberMarcar;
                }

                $whereUpdate = ["COALESCE(pago, '') != 'Sim'"];

                if ($temDataAlerta) {
                    $whereUpdate[] = "(data_alerta IS NULL OR data_alerta < :data_hoje)";
                }

                $sqlUpdate = "
                    UPDATE receber
                       SET " . implode(",\n                           ", $camposUpdate) . "
                     WHERE " . implode("\n                       AND ", $whereUpdate) . "
                       AND id IN (" . implode(',', $marcadores) . ")
                ";

                try {
                    $up = $pdo->prepare($sqlUpdate);
                    $up->execute($parametros);
                } catch (Throwable $e) {
                    if ($DEBUG) {
                        echo "[{$ctx}] erro ao marcar alerta cliente={$idCliente}: " . $e->getMessage() . "\n";
                    }
                }
            }

            $totalEnviados++;

            if ($DEBUG) {
                echo "[{$ctx}] enviado para cliente={$idCliente} contas=" . implode(',', $idsParaMarcar) . "\n";
            }
        } else {
            if ($DEBUG) {
                echo "[{$ctx}] FALHA envio/Menuia cliente={$idCliente} status={$statusFinal}\n";
            }
        }
    }

    return $totalEnviados;
}

// ======================================================
// Execução
// ======================================================
$cfgMarket = bb_get_cfg_marketplace();

if ($DEBUG) {
    echo "DEBUG: agora=" . bb_cron_data_hora_agora()
        . " slug=" . ($__bb_loja !== '' ? $__bb_loja : '[vazio]')
        . " all=" . ($__bb_all ? '1' : '0')
        . "\n";
}

// Padrão multi-tenant:
// - com ?loja=slug: carrega bootstrap para usar o resolver oficial conectarBancoPorSlug;
// - com ?all=1: não carrega bootstrap da loja, conecta diretamente pelo marketplace;
// - sem ?loja e sem ?all: não consulta banco operacional para evitar tratar o Master como loja.
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

        if (!empty($cfgLocal['timezone']) && function_exists('bb_cron_aplicar_timezone')) {
            bb_cron_aplicar_timezone((string)$cfgLocal['timezone']);
        }

        $qtde = bb_run_alerta_receber($pdoExec, $cfgLocal, $__bb_loja, true, $DEBUG);
        echo $qtde;
    } else {
        if ($DEBUG) {
            echo "[{$__bb_loja}] não foi possível abrir o banco da loja\n";
        }

        echo 0;
    }

    exit;
}

$totalGeral = 0;

// 2) Sem slug e sem all=1, não consulta tabelas operacionais no banco Master.
if (!$__bb_all) {
    if ($DEBUG) {
        echo "Informe loja=<slug> ou all=1. Nenhum banco de barbearia foi selecionado.\n";
    }

    echo $totalGeral;
    exit;
}

// 3) all=1 explícito: varredura global das lojas do marketplace.
if (empty($cfgMarket) || empty($cfgMarket['master']) || !is_array($cfgMarket['master'])) {
    if ($DEBUG) {
        echo "ERRO: marketplace.php ausente/inválido para varredura global\n";
    }

    echo $totalGeral;
    exit;
}

$mk = $cfgMarket['master'];

$tenantsTable = preg_replace('/[^a-z0-9_]/i', '', (string)($mk['table'] ?? 'tenants'));

if ($tenantsTable === '') {
    $tenantsTable = 'tenants';
}

$mkHost = (string)($mk['host'] ?? 'localhost');
$mkDb = (string)($mk['db'] ?? '');
$mkUser = (string)($mk['user'] ?? '');
$mkPass = (string)($mk['pass'] ?? '');

$secret = (string)($cfgMarket['security']['tenant_secret'] ?? '');

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

            $totalGeral += bb_run_alerta_receber($pdoTenant, $cfgTenant, $slug, true, $DEBUG);
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
