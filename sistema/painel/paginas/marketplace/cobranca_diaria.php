<?php
declare(strict_types=1);

@set_time_limit(0);
if (!defined('BB_TIMEZONE_PADRAO')) {
    define('BB_TIMEZONE_PADRAO', 'America/Sao_Paulo');
}
@date_default_timezone_set(BB_TIMEZONE_PADRAO);

$DEBUG = false;
if (PHP_SAPI !== 'cli' && isset($_GET['debug']) && (string)$_GET['debug'] === '1') {
    $DEBUG = true;
}
if ($DEBUG && !headers_sent()) {
    @header('Content-Type: text/plain; charset=utf-8');
}

function bb_debug_marketplace(string $msg, bool $debug): void
{
    if ($debug) {
        echo $msg . "\n";
    }
}

function bb_limpar_nome_tabela(string $nomeTabela): string
{
    $nomeTabela = preg_replace('/[^a-z0-9_]/i', '', (string)$nomeTabela);
    return $nomeTabela !== '' ? $nomeTabela : 'tenants';
}

function bb_coluna_existe_marketplace(PDO $pdo, string $tabela, string $coluna): bool
{
    $tabela = bb_limpar_nome_tabela($tabela);
    $coluna = preg_replace('/[^a-z0-9_]/i', '', (string)$coluna);

    if ($coluna === '') {
        return false;
    }

    try {
        $st = $pdo->prepare("SHOW COLUMNS FROM `{$tabela}` LIKE :coluna");
        $st->execute([':coluna' => $coluna]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function bb_pdo_marketplace(string $host, string $db, string $user, string $pass): PDO
{
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

function bb_buscar_primeiro_arquivo(array $caminhos): string
{
    foreach ($caminhos as $caminho) {
        if (is_file($caminho)) {
            return $caminho;
        }
    }
    return '';
}

function bb_apenas_digitos(string $valor): string
{
    return preg_replace('/\D+/', '', $valor);
}

function bb_normalizar_telefone_tenant(array $tenant): string
{
    $campos = ['telefone_whatsapp', 'whatsapp_norm', 'whatsapp'];

    foreach ($campos as $campo) {
        if (!empty($tenant[$campo])) {
            $telefone = bb_apenas_digitos((string)$tenant[$campo]);
            if ($telefone === '') {
                continue;
            }

            if (strlen($telefone) <= 11) {
                return '55' . $telefone;
            }

            return $telefone;
        }
    }

    return '';
}

function bb_valor_decimal($valor): float
{
    $valor = str_replace(['R$', ' '], '', (string)$valor);

    if (strpos($valor, ',') !== false && strpos($valor, '.') !== false) {
        $valor = str_replace('.', '', $valor);
        $valor = str_replace(',', '.', $valor);
    } else {
        $valor = str_replace(',', '.', $valor);
    }

    return (float)$valor;
}

function bb_formatar_data_br(?string $data): string
{
    if (!$data || $data === '0000-00-00') {
        return '-';
    }

    $ts = strtotime($data);
    if ($ts === false) {
        return '-';
    }

    return date('d/m/Y', $ts);
}

function bb_normalizar_timezone_marketplace($timezone): string
{
    $timezone = trim((string)$timezone);

    if ($timezone === '') {
        return BB_TIMEZONE_PADRAO;
    }

    $timezone = str_replace(["\0", "\r", "\n", "\t", "\\"], '', $timezone);
    $timezone = trim($timezone);

    if ($timezone === '' || strlen($timezone) > 64) {
        return BB_TIMEZONE_PADRAO;
    }

    try {
        new DateTimeZone($timezone);
        return $timezone;
    } catch (Throwable $e) {
        return BB_TIMEZONE_PADRAO;
    }
}

function bb_hoje(?string $timezone = null): string
{
    $timezoneFinal = bb_normalizar_timezone_marketplace($timezone ?? BB_TIMEZONE_PADRAO);
    $agora = new DateTime('now', new DateTimeZone($timezoneFinal));
    return $agora->format('Y-m-d');
}

function bb_agora(?string $timezone = null): string
{
    $timezoneFinal = bb_normalizar_timezone_marketplace($timezone ?? BB_TIMEZONE_PADRAO);
    $agora = new DateTime('now', new DateTimeZone($timezoneFinal));
    return $agora->format('Y-m-d H:i:s');
}

function bb_carregar_cfg_marketplace(): array
{
    $cfgFile = __DIR__ . '/../../../../config/marketplace.php';
    if (!is_file($cfgFile)) {
        return [];
    }

    $cfg = require $cfgFile;
    return is_array($cfg) ? $cfg : [];
}

function bb_carregar_cfg_sistema_padrao(array $cfg, bool $debug): array
{
    $fontesGlobais = [];

    if (!empty($cfg['master']) && is_array($cfg['master'])) {
        $fontesGlobais[] = $cfg['master'];
    }
    if (!empty($cfg['auth_master']) && is_array($cfg['auth_master'])) {
        $fontesGlobais[] = $cfg['auth_master'];
    }

    foreach ($fontesGlobais as $fonte) {
        $host = trim((string)($fonte['host'] ?? ''));
        $db = (string)preg_replace('/[^a-z0-9_]/i', '', (string)($fonte['db'] ?? ''));
        $user = trim((string)($fonte['user'] ?? ''));
        $pass = (string)($fonte['pass'] ?? '');

        if ($host === '' || $db === '' || $user === '') {
            continue;
        }

        try {
            $pdoGlobal = bb_pdo_marketplace($host, $db, $user, $pass);
            $dbAtual = trim((string)$pdoGlobal->query('SELECT DATABASE()')->fetchColumn());
            if ($dbAtual !== $db) {
                continue;
            }

            $temConfig = (bool)$pdoGlobal->query("SHOW TABLES LIKE 'config'")->fetchColumn();
            if (!$temConfig) {
                continue;
            }

            $row = $pdoGlobal->query(
                'SELECT api, token, instancia, access_token_mp, url_sistema FROM config LIMIT 1'
            )->fetch();

            if (is_array($row)) {
                return $row;
            }
        } catch (Throwable $e) {
            bb_debug_marketplace('ERRO ao abrir banco global de configuração: ' . $e->getMessage(), $debug);
        }
    }

    bb_debug_marketplace('ERRO: configuração global de API/pagamento não encontrada.', $debug);
    return [];
}

function bb_criar_preferencia_mp(string $accessToken, string $notificationUrl, int $tenantId, string $titulo, float $valor): array
{
    $payload = [
        'external_reference' => (string)$tenantId,
        'notification_url'   => $notificationUrl,
        'items' => [
            [
                'title'       => $titulo,
                'quantity'    => 1,
                'currency_id' => 'BRL',
                'unit_price'  => (float)$valor,
            ]
        ]
    ];

    $ch = curl_init('https://api.mercadopago.com/checkout/preferences');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $accessToken,
            'Content-Type: application/json'
        ],
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($payload),
        CURLOPT_TIMEOUT => 30,
    ]);

    $resp = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $erro = (string)curl_error($ch);
    curl_close($ch);

    if ($resp === false || $http < 200 || $http >= 300) {
        return [
            'ok'   => false,
            'http' => $http,
            'msg'  => $erro !== '' ? $erro : 'Falha ao criar preferência no Mercado Pago'
        ];
    }

    $json = json_decode((string)$resp, true);
    if (!is_array($json) || empty($json['id']) || empty($json['init_point'])) {
        return [
            'ok'  => false,
            'msg' => 'Mercado Pago não retornou init_point'
        ];
    }

    return [
        'ok'            => true,
        'preference_id' => (string)$json['id'],
        'init_point'    => (string)$json['init_point'],
    ];
}

function bb_enviar_texto_whatsapp(string $apiFile, string $api, string $token, string $instancia, string $telefone, string $mensagem): bool
{
    if ($api === '' || $token === '' || $instancia === '' || $telefone === '' || $mensagem === '' || !is_file($apiFile)) {
        return false;
    }

    $status_mensagem = '';
    ob_start();
    include $apiFile;
    ob_end_clean();

    if ($api === 'menuia') {
        return $status_mensagem === 'Mensagem enviada com sucesso.';
    }

    return true;
}

function bb_obter_fatura_marketplace(PDO $pdo, int $tenantId, string $vencimento): ?array
{
    $st = $pdo->prepare("
        SELECT *
          FROM marketplace_faturas
         WHERE tenant_id = :tenant_id
           AND vencimento = :vencimento
         ORDER BY id DESC
         LIMIT 1
    ");
    $st->execute([
        ':tenant_id' => $tenantId,
        ':vencimento' => $vencimento,
    ]);

    $row = $st->fetch();
    return is_array($row) ? $row : null;
}

function bb_criar_fatura_marketplace(PDO $pdo, int $tenantId, string $vencimento, float $valor, string $criadoEm): int
{
    $st = $pdo->prepare("
        INSERT INTO marketplace_faturas
            (tenant_id, vencimento, valor, status, criado_em)
        VALUES
            (:tenant_id, :vencimento, :valor, 'pending', :criado_em)
    ");
    $st->execute([
        ':tenant_id' => $tenantId,
        ':vencimento' => $vencimento,
        ':valor' => number_format($valor, 2, '.', ''),
        ':criado_em' => $criadoEm,
    ]);

    return (int)$pdo->lastInsertId();
}

$cfg = bb_carregar_cfg_marketplace();
if (empty($cfg) || empty($cfg['master']) || !is_array($cfg['master'])) {
    exit("config/marketplace.php inválido\n");
}

$mk = $cfg['master'];
$mkHost = (string)($mk['host'] ?? 'localhost');
$mkDb   = (string)($mk['db'] ?? '');
$mkUser = (string)($mk['user'] ?? '');
$mkPass = (string)($mk['pass'] ?? '');
$tenantsTable = bb_limpar_nome_tabela($mk['table'] ?? 'tenants');

if ($mkDb === '' || $mkUser === '') {
    exit("Config do master incompleta\n");
}

$cfgSistema = bb_carregar_cfg_sistema_padrao($cfg, $DEBUG);
$api = trim((string)($cfgSistema['api'] ?? ''));
$token = trim((string)($cfgSistema['token'] ?? ''));
$instancia = trim((string)($cfgSistema['instancia'] ?? ''));
$accessTokenMp = trim((string)($cfgSistema['access_token_mp'] ?? ''));
$urlSistema = trim((string)($cfgSistema['url_sistema'] ?? ''));

$apiFile = bb_buscar_primeiro_arquivo([
    __DIR__ . '/../../../../pagamentos/api-texto.php',
    __DIR__ . '/../../../ajax/api-texto.php',
    __DIR__ . '/../../../../ajax/api-texto.php',
]);

if ($apiFile === '') {
    bb_debug_marketplace('ERRO: api-texto.php não encontrado', $DEBUG);
}

$baseUrl = '';
if ($urlSistema !== '') {
    $partes = @parse_url($urlSistema);
    if (is_array($partes) && !empty($partes['scheme']) && !empty($partes['host'])) {
        $baseUrl = $partes['scheme'] . '://' . $partes['host'];
        if (!empty($partes['port'])) {
            $baseUrl .= ':' . (int)$partes['port'];
        }
    }
}
if ($baseUrl === '') {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = (string)($_SERVER['HTTP_HOST'] ?? '');
    if ($host !== '') {
        $baseUrl = $scheme . '://' . $host;
    }
}

$webhookToken = '';
if (!empty($cfg['webhook_token'])) {
    $webhookToken = (string)$cfg['webhook_token'];
} elseif (!empty($cfg['app']['webhook_token'])) {
    $webhookToken = (string)$cfg['app']['webhook_token'];
}

$notificationUrl = '';
if ($baseUrl !== '') {
    $notificationUrl = rtrim($baseUrl, '/') . '/pagamentos/webhook_mp_marketplace.php';
    if ($webhookToken !== '') {
        $notificationUrl .= '?token=' . rawurlencode($webhookToken);
    }
}

try {
    $pdoM = bb_pdo_marketplace($mkHost, $mkDb, $mkUser, $mkPass);
} catch (Throwable $e) {
    exit("Falha ao conectar no banco master\n");
}

$colunasTenants = [
    'id',
    'slug',
    'nome',
    'status',
    'ativo',
    'vencimento',
    'valor_mensal',
    'telefone_whatsapp',
    'whatsapp',
    'whatsapp_norm',
    'dias_aviso',
];

if (bb_coluna_existe_marketplace($pdoM, $tenantsTable, 'timezone')) {
    $colunasTenants[] = 'timezone';
}

$selectTenants = implode(",\n        ", array_map(static function ($coluna) {
    return '`' . $coluna . '`';
}, $colunasTenants));

$stTenants = $pdoM->query("
    SELECT
        {$selectTenants}
      FROM `{$tenantsTable}`
     WHERE vencimento IS NOT NULL
     ORDER BY id ASC
");
$tenants = $stTenants->fetchAll();

bb_debug_marketplace('Tenants com vencimento: ' . count($tenants), $DEBUG);

foreach ($tenants as $tenant) {
    $tenantId = (int)($tenant['id'] ?? 0);
    $slug = trim((string)($tenant['slug'] ?? ''));
    $nome = trim((string)($tenant['nome'] ?? ''));
    $statusAtual = trim((string)($tenant['status'] ?? 'active'));
    $vencimento = trim((string)($tenant['vencimento'] ?? ''));
    $valor = bb_valor_decimal((string)($tenant['valor_mensal'] ?? '0'));
    $diasAviso = (int)($tenant['dias_aviso'] ?? 3);
    $telefone = bb_normalizar_telefone_tenant($tenant);
    $timezoneTenant = bb_normalizar_timezone_marketplace($tenant['timezone'] ?? BB_TIMEZONE_PADRAO);
    $hoje = bb_hoje($timezoneTenant);
    $agora = bb_agora($timezoneTenant);

    if ($tenantId <= 0 || $vencimento === '' || $valor <= 0) {
        bb_debug_marketplace("[tenant {$tenantId}] ignorado por dados insuficientes", $DEBUG);
        continue;
    }

    if ($telefone === '') {
        bb_debug_marketplace("[tenant {$tenantId}] sem WhatsApp cadastrado", $DEBUG);
    }

    $dias = (int)floor((strtotime($vencimento) - strtotime($hoje)) / 86400);
    $valorFormatado = number_format($valor, 2, ',', '.');
    $vencimentoBr = bb_formatar_data_br($vencimento);

    $fatura = bb_obter_fatura_marketplace($pdoM, $tenantId, $vencimento);
    if (!$fatura) {
        $idFatura = bb_criar_fatura_marketplace($pdoM, $tenantId, $vencimento, $valor, $agora);
        $fatura = bb_obter_fatura_marketplace($pdoM, $tenantId, $vencimento);
        bb_debug_marketplace("[{$slug}] fatura criada #{$idFatura}", $DEBUG);
    }

    if (!$fatura) {
        bb_debug_marketplace("[{$slug}] falha ao obter/criar fatura", $DEBUG);
        continue;
    }

    $faturaId = (int)$fatura['id'];
    $faturaStatus = trim((string)($fatura['status'] ?? 'pending'));
    $lembreteEnviadoEm = trim((string)($fatura['lembrete_enviado_em'] ?? ''));
    $cobrancaEnviadaEm = trim((string)($fatura['cobranca_enviada_em'] ?? ''));
    $initPoint = trim((string)($fatura['mp_init_point'] ?? ''));
    $preferenceId = trim((string)($fatura['mp_preference_id'] ?? ''));

    // 1) Lembrete antes do vencimento
    if ($dias === $diasAviso && $lembreteEnviadoEm !== $hoje) {
        if ($telefone !== '' && $apiFile !== '') {
            $mensagem  = "💳 *Assinatura BarberBot*%0A";
            $mensagem .= "🏪 " . ($nome !== '' ? $nome : $slug) . "%0A";
            $mensagem .= "📅 Sua assinatura vence em " . $diasAviso . " dia(s): " . $vencimentoBr . "%0A";
            $mensagem .= "💰 Valor: R$ " . $valorFormatado . "%0A";
            $mensagem .= "⚠️ Para evitar bloqueio, mantenha o pagamento em dia.";

            $okEnvio = bb_enviar_texto_whatsapp($apiFile, $api, $token, $instancia, $telefone, $mensagem);
            if ($okEnvio) {
                $up = $pdoM->prepare("UPDATE marketplace_faturas SET lembrete_enviado_em = :hoje WHERE id = :id");
                $up->execute([
                    ':hoje' => $hoje,
                    ':id' => $faturaId,
                ]);
                bb_debug_marketplace("[{$slug}] lembrete enviado", $DEBUG);
            } else {
                bb_debug_marketplace("[{$slug}] falha no lembrete de WhatsApp", $DEBUG);
            }
        }
        continue;
    }

    // 2) No dia do vencimento gera/garante cobrança e envia link
    if ($dias === 0 && $faturaStatus !== 'paid' && $cobrancaEnviadaEm !== $hoje) {
        if (($initPoint === '' || $preferenceId === '') && $accessTokenMp !== '' && $notificationUrl !== '') {
            $titulo = 'Assinatura BarberBot - ' . ($nome !== '' ? $nome : $slug) . ' (' . $slug . ')';
            $mp = bb_criar_preferencia_mp($accessTokenMp, $notificationUrl, $tenantId, $titulo, $valor);

            if (!empty($mp['ok'])) {
                $initPoint = (string)$mp['init_point'];
                $preferenceId = (string)$mp['preference_id'];

                $up = $pdoM->prepare("
                    UPDATE marketplace_faturas
                       SET mp_preference_id = :pref,
                           mp_init_point = :url,
                           status = CASE WHEN status = 'paid' THEN status ELSE 'pending' END
                     WHERE id = :id
                ");
                $up->execute([
                    ':pref' => $preferenceId,
                    ':url' => $initPoint,
                    ':id' => $faturaId,
                ]);

                bb_debug_marketplace("[{$slug}] cobrança gerada no Mercado Pago", $DEBUG);
            } else {
                bb_debug_marketplace("[{$slug}] erro ao gerar cobrança: " . (string)($mp['msg'] ?? 'erro'), $DEBUG);
            }
        }

        if ($telefone !== '' && $initPoint !== '' && $apiFile !== '') {
            $mensagem  = "💳 *Assinatura BarberBot*%0A";
            $mensagem .= "🏪 " . ($nome !== '' ? $nome : $slug) . "%0A";
            $mensagem .= "📅 Sua assinatura vence hoje (" . $vencimentoBr . ").%0A";
            $mensagem .= "💰 Valor: R$ " . $valorFormatado . "%0A";
            $mensagem .= "✅ Pagar agora:%0A" . $initPoint;

            $okEnvio = bb_enviar_texto_whatsapp($apiFile, $api, $token, $instancia, $telefone, $mensagem);
            if ($okEnvio) {
                $up = $pdoM->prepare("UPDATE marketplace_faturas SET cobranca_enviada_em = :hoje WHERE id = :id");
                $up->execute([
                    ':hoje' => $hoje,
                    ':id' => $faturaId,
                ]);
                bb_debug_marketplace("[{$slug}] cobrança enviada no WhatsApp", $DEBUG);
            } else {
                bb_debug_marketplace("[{$slug}] falha no envio da cobrança", $DEBUG);
            }
        }
        continue;
    }

    // 3) Após vencer: bloqueia e avisa uma única vez na transição
    if ($dias < 0 && $faturaStatus !== 'paid') {
        if ($statusAtual !== 'blocked') {
            $upTenant = $pdoM->prepare("
                UPDATE `{$tenantsTable}`
                   SET status = 'blocked',
                       ativo = 0,
                       blocked_at = :blocked_at
                 WHERE id = :id
                 LIMIT 1
            ");
            $upTenant->execute([
                ':blocked_at' => $agora,
                ':id' => $tenantId,
            ]);

            bb_debug_marketplace("[{$slug}] tenant bloqueado por vencimento", $DEBUG);

            if ($telefone !== '' && $apiFile !== '') {
                $mensagem  = "🚫 *Assinatura BarberBot vencida*%0A";
                $mensagem .= "🏪 " . ($nome !== '' ? $nome : $slug) . "%0A";
                $mensagem .= "📅 Vencimento: " . $vencimentoBr . "%0A";
                $mensagem .= "💰 Valor: R$ " . $valorFormatado . "%0A";
                $mensagem .= "⚠️ Seu acesso foi bloqueado por falta de pagamento.%0A";
                if ($initPoint !== '') {
                    $mensagem .= "✅ Regularize aqui:%0A" . $initPoint;
                } else {
                    $mensagem .= "Entre em contato para regularização da assinatura.";
                }

                $okEnvio = bb_enviar_texto_whatsapp($apiFile, $api, $token, $instancia, $telefone, $mensagem);
                if ($okEnvio) {
                    bb_debug_marketplace("[{$slug}] aviso de bloqueio enviado", $DEBUG);
                } else {
                    bb_debug_marketplace("[{$slug}] falha ao enviar aviso de bloqueio", $DEBUG);
                }
            }
        }
        continue;
    }
}

echo "OK\n";
