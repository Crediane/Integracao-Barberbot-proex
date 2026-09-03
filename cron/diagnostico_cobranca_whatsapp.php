<?php
declare(strict_types=1);

/**
 * /public_html/cron/diagnostico_cobranca_whatsapp.php
 *
 * Diagnóstico seguro:
 * - não envia WhatsApp
 * - não cria fatura
 * - não altera banco
 * - mostra como o telefone será normalizado e se o tenant cairia em aviso/vencimento/atraso/bloqueio
 */

require_once __DIR__ . '/timezone_cron.php';
bb_cron_aplicar_timezone();

header('Content-Type: application/json; charset=utf-8');

$cfgFile = realpath(__DIR__ . '/../config/marketplace.php');
if (!$cfgFile || !is_file($cfgFile)) {
    echo json_encode(['ok' => false, 'mensagem' => 'Config marketplace.php não encontrada.'], JSON_UNESCAPED_UNICODE);
    exit;
}

$cfg = require $cfgFile;

function bb_diag_pdo_connect(string $host, string $db, string $user, string $pass): PDO {
    return new PDO(
        "mysql:host={$host};dbname={$db};charset=utf8mb4",
        $user,
        $pass,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

function bb_diag_clean_phone(string $v): string {
    $raw = trim($v);
    $digits = preg_replace('/\D+/', '', $raw);
    $digits = trim((string)$digits);

    if ($digits === '') {
        return '';
    }

    if (strpos($digits, '00') === 0) {
        $digits = substr($digits, 2);
    }

    $temPrefixoInternacional = (bool)preg_match('/^\s*\+/', $raw) || (bool)preg_match('/^\s*00/', $raw);
    if ($temPrefixoInternacional) {
        return $digits;
    }

    if (strlen($digits) === 10 || strlen($digits) === 11) {
        return '55' . $digits;
    }

    return $digits;
}

function bb_diag_col_exists(PDO $pdo, string $dbName, string $table, string $col): bool {
    $st = $pdo->prepare("
        SELECT 1
          FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = :db
           AND TABLE_NAME = :tb
           AND COLUMN_NAME = :col
         LIMIT 1
    ");
    $st->execute([':db' => $dbName, ':tb' => $table, ':col' => $col]);
    return (bool)$st->fetchColumn();
}

$mk = $cfg['master'] ?? [];
$host = (string)($mk['host'] ?? 'localhost');
$db = (string)($mk['db'] ?? '');
$user = (string)($mk['user'] ?? '');
$pass = (string)($mk['pass'] ?? '');
$table = preg_replace('/[^a-z0-9_]/i', '', (string)($mk['table'] ?? 'tenants'));
if ($table === '') {
    $table = 'tenants';
}

$slug = preg_replace('/[^a-z0-9_-]/i', '', (string)($_GET['slug'] ?? ''));
$id = (int)($_GET['id'] ?? 0);

if ($slug === '' && $id <= 0) {
    echo json_encode(['ok' => false, 'mensagem' => 'Informe ?slug= ou ?id=.'], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $pdo = bb_diag_pdo_connect($host, $db, $user, $pass);

    $hasTelefone = bb_diag_col_exists($pdo, $db, $table, 'telefone_whatsapp');
    $hasWhatsapp = bb_diag_col_exists($pdo, $db, $table, 'whatsapp');
    $hasDiasAviso = bb_diag_col_exists($pdo, $db, $table, 'dias_aviso');
    $hasTimezone = bb_diag_col_exists($pdo, $db, $table, 'timezone');

    $telExpr = $hasTelefone ? '`telefone_whatsapp`' : ($hasWhatsapp ? '`whatsapp`' : "''");
    $diasExpr = $hasDiasAviso ? '`dias_aviso`' : 'NULL';
    $tzExpr = $hasTimezone ? '`timezone`' : 'NULL';

    $where = $id > 0 ? 'id = :id' : 'slug = :slug';
    $sql = "
        SELECT id, slug, nome, vencimento, valor_mensal,
               {$telExpr} AS telefone_alerta,
               {$diasExpr} AS dias_aviso,
               {$tzExpr} AS timezone_tenant
          FROM `{$table}`
         WHERE {$where}
         LIMIT 1
    ";
    $st = $pdo->prepare($sql);
    if ($id > 0) {
        $st->execute([':id' => $id]);
    } else {
        $st->execute([':slug' => $slug]);
    }

    $tenant = $st->fetch(PDO::FETCH_ASSOC);
    if (!$tenant) {
        echo json_encode(['ok' => false, 'mensagem' => 'Tenant não encontrado.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    bb_cron_aplicar_timezone((string)($tenant['timezone_tenant'] ?? ''));

    $vencimento = (string)($tenant['vencimento'] ?? '');
    $hoje = bb_cron_data_hoje();
    $dias = $vencimento !== '' ? (int)floor((strtotime($vencimento) - strtotime($hoje)) / 86400) : null;
    $diasAviso = (int)($tenant['dias_aviso'] ?? 0);
    if ($diasAviso <= 0) {
        $diasAviso = (int)($cfg['billing']['dias_antes_lembrete'] ?? 3);
    }
    $diasGraca = (int)($cfg['billing']['dias_graca'] ?? 2);

    $bloco = 'fora_da_regra';
    if ($dias === $diasAviso) {
        $bloco = 'aviso_antes_do_vencimento_com_link';
    } elseif ($dias === 0) {
        $bloco = 'vencimento_hoje_com_link';
    } elseif ($dias !== null && $dias < 0 && abs($dias) <= $diasGraca) {
        $bloco = 'atraso_carencia_com_link';
    } elseif ($dias !== null && $dias < 0 && abs($dias) > $diasGraca) {
        $bloco = 'bloqueio_com_link';
    }

    echo json_encode([
        'ok' => true,
        'sem_alterar_banco' => true,
        'sem_enviar_whatsapp' => true,
        'tenant_id' => (int)$tenant['id'],
        'slug' => $tenant['slug'],
        'nome' => $tenant['nome'],
        'vencimento' => $vencimento,
        'hoje' => $hoje,
        'dias_para_vencer' => $dias,
        'dias_aviso_usado' => $diasAviso,
        'telefone_original' => (string)($tenant['telefone_alerta'] ?? ''),
        'telefone_enviado_para_menuia' => bb_diag_clean_phone((string)($tenant['telefone_alerta'] ?? '')),
        'bloco_cron' => $bloco,
        'observacao' => 'O telefone do tenant é o destinatário. Telefone da config do admin não é usado como destinatário da cobrança.',
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
} catch (Throwable $e) {
    echo json_encode(['ok' => false, 'mensagem' => 'Erro no diagnóstico.', 'erro' => $e->getMessage()], JSON_UNESCAPED_UNICODE);
}
