<?php
declare(strict_types=1);

/**
 * Arquivo: /public_html/pagamentos/diagnostico_cobranca_whatsapp.php
 *
 * Diagnóstico seguro da cobrança Marketplace por WhatsApp.
 *
 * IMPORTANTE:
 * - Não envia WhatsApp.
 * - Não cria fatura.
 * - Não altera banco.
 * - Não baixa pagamento.
 * - Não libera/bloqueia tenant.
 *
 * Motivo deste arquivo existir em /pagamentos:
 * - Alguns servidores bloqueiam acesso público à pasta /cron e retornam 403 antes do PHP.
 * - Este diagnóstico fica em /pagamentos, que já é uma área pública do fluxo Marketplace.
 *
 * Uso:
 * https://jc.tec.br/pagamentos/diagnostico_cobranca_whatsapp.php?token=TOKEN_TECNICO&slug=barbearia-cabral
 */

header('Content-Type: application/json; charset=utf-8');

if (!function_exists('bb_diag_json')) {
    function bb_diag_json(array $dados, int $httpStatus = 200): void
    {
        if (ob_get_level() > 0) {
            @ob_clean();
        }

        http_response_code($httpStatus);
        echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        exit;
    }
}

if (!function_exists('bb_diag_carregar_config')) {
    function bb_diag_carregar_config(): array
    {
        $arquivoConfig = realpath(__DIR__ . '/../config/marketplace.php');

        if (!$arquivoConfig || !is_file($arquivoConfig)) {
            bb_diag_json([
                'ok' => false,
                'mensagem' => 'Arquivo /public_html/config/marketplace.php não encontrado.',
            ], 500);
        }

        $cfg = require $arquivoConfig;

        if (!is_array($cfg)) {
            bb_diag_json([
                'ok' => false,
                'mensagem' => 'O arquivo /public_html/config/marketplace.php não retornou array.',
            ], 500);
        }

        return $cfg;
    }
}

if (!function_exists('bb_diag_token_config')) {
    function bb_diag_token_config(array $cfg): string
    {
        $candidatos = [
            (string)($cfg['webhook_token'] ?? ''),
            (string)($cfg['app']['webhook_token'] ?? ''),
            (string)($cfg['cron']['token'] ?? ''),
        ];

        foreach ($candidatos as $token) {
            $token = trim($token);
            if ($token !== '') {
                return $token;
            }
        }

        return '';
    }
}

if (!function_exists('bb_diag_validar_token')) {
    function bb_diag_validar_token(array $cfg): void
    {
        $tokenInformado = trim((string)($_GET['token'] ?? ''));
        $tokenCorreto = bb_diag_token_config($cfg);

        if ($tokenCorreto === '') {
            bb_diag_json([
                'ok' => false,
                'mensagem' => 'Token técnico não configurado em /public_html/config/marketplace.php.',
                'campos_aceitos' => [
                    'webhook_token',
                    'app.webhook_token',
                    'cron.token',
                ],
            ], 500);
        }

        if ($tokenInformado === '' || !hash_equals($tokenCorreto, $tokenInformado)) {
            bb_diag_json([
                'ok' => false,
                'mensagem' => 'Token técnico inválido ou ausente.',
                'usar' => 'webhook_token do /public_html/config/marketplace.php',
                'nao_usar' => [
                    'APPKEY da Menuia',
                    'AUTHKEY da Menuia',
                    'Access Token Mercado Pago',
                    'Public Key Mercado Pago',
                ],
            ], 403);
        }
    }
}

if (!function_exists('bb_diag_pdo_connect')) {
    function bb_diag_pdo_connect(string $host, string $db, string $user, string $pass): PDO
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
}

if (!function_exists('bb_diag_col_exists')) {
    function bb_diag_col_exists(PDO $pdo, string $dbName, string $table, string $col): bool
    {
        try {
            $sql = "
                SELECT 1
                  FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = :db
                   AND TABLE_NAME = :tabela
                   AND COLUMN_NAME = :coluna
                 LIMIT 1
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':db' => $dbName,
                ':tabela' => $table,
                ':coluna' => $col,
            ]);

            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('bb_diag_table_exists')) {
    function bb_diag_table_exists(PDO $pdo, string $dbName, string $table): bool
    {
        try {
            $sql = "
                SELECT 1
                  FROM INFORMATION_SCHEMA.TABLES
                 WHERE TABLE_SCHEMA = :db
                   AND TABLE_NAME = :tabela
                 LIMIT 1
            ";

            $stmt = $pdo->prepare($sql);
            $stmt->execute([
                ':db' => $dbName,
                ':tabela' => $table,
            ]);

            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('bb_diag_normalizar_telefone_br')) {
    function bb_diag_normalizar_telefone_br(string $telefone): string
    {
        $original = trim($telefone);
        $digitos = preg_replace('/\D+/', '', $original);
        $digitos = trim((string)$digitos);

        if ($digitos === '') {
            return '';
        }

        if (strpos($digitos, '00') === 0) {
            $digitos = substr($digitos, 2);
        }

        $temPrefixoInternacional = (bool)preg_match('/^\s*\+/', $original) || (bool)preg_match('/^\s*00/', $original);

        if ($temPrefixoInternacional) {
            return $digitos;
        }

        if (strlen($digitos) === 10 || strlen($digitos) === 11) {
            return '55' . $digitos;
        }

        return $digitos;
    }
}

if (!function_exists('bb_diag_data_hoje')) {
    function bb_diag_data_hoje(string $timezone = 'America/Sao_Paulo'): string
    {
        try {
            $tz = new DateTimeZone($timezone !== '' ? $timezone : 'America/Sao_Paulo');
        } catch (Throwable $e) {
            $tz = new DateTimeZone('America/Sao_Paulo');
        }

        return (new DateTimeImmutable('now', $tz))->format('Y-m-d');
    }
}

if (!function_exists('bb_diag_dias_entre_datas')) {
    function bb_diag_dias_entre_datas(string $dataInicial, string $dataFinal): ?int
    {
        if ($dataInicial === '' || $dataFinal === '') {
            return null;
        }

        $inicio = DateTimeImmutable::createFromFormat('Y-m-d', $dataInicial);
        $fim = DateTimeImmutable::createFromFormat('Y-m-d', $dataFinal);

        if (!$inicio || !$fim) {
            return null;
        }

        return (int)$inicio->diff($fim)->format('%r%a');
    }
}

if (!function_exists('bb_diag_base_url')) {
    function bb_diag_base_url(array $cfg): string
    {
        $candidatos = [
            (string)($cfg['app']['url_sistema'] ?? ''),
            (string)($cfg['app']['base_url'] ?? ''),
            (string)($cfg['url_sistema'] ?? ''),
            (string)($cfg['base_url'] ?? ''),
        ];

        foreach ($candidatos as $url) {
            $url = rtrim(trim($url), '/');
            if ($url !== '') {
                return $url;
            }
        }

        $host = (string)($_SERVER['HTTP_HOST'] ?? '');
        if ($host !== '') {
            $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
            return ($https ? 'https://' : 'http://') . $host;
        }

        return '';
    }
}

if (!function_exists('bb_diag_buscar_fatura')) {
    function bb_diag_buscar_fatura(PDO $pdo, string $dbName, int $tenantId, string $vencimento): array
    {
        if ($tenantId <= 0 || $vencimento === '') {
            return [
                'encontrada' => false,
                'id' => null,
                'status' => null,
                'observacao' => 'Tenant ou vencimento inválido para consultar fatura.',
            ];
        }

        if (!bb_diag_table_exists($pdo, $dbName, 'marketplace_faturas')) {
            return [
                'encontrada' => false,
                'id' => null,
                'status' => null,
                'observacao' => 'Tabela marketplace_faturas não encontrada.',
            ];
        }

        $sql = "
            SELECT id, status
              FROM marketplace_faturas
             WHERE tenant_id = :tenant_id
               AND vencimento = :vencimento
             ORDER BY id DESC
             LIMIT 1
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':tenant_id' => $tenantId,
            ':vencimento' => $vencimento,
        ]);

        $fatura = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$fatura) {
            return [
                'encontrada' => false,
                'id' => null,
                'status' => null,
                'observacao' => 'Ainda não existe fatura para esse vencimento. O cron real cria/reutiliza no momento do envio.',
            ];
        }

        return [
            'encontrada' => true,
            'id' => (int)$fatura['id'],
            'status' => (string)($fatura['status'] ?? ''),
            'observacao' => 'Fatura encontrada para o vencimento atual.',
        ];
    }
}

$cfg = bb_diag_carregar_config();
bb_diag_validar_token($cfg);

$slug = strtolower(trim((string)($_GET['slug'] ?? '')));
$id = (int)($_GET['id'] ?? 0);

$slug = preg_replace('/[^a-z0-9_-]/', '', $slug);

if ($slug === '' && $id <= 0) {
    bb_diag_json([
        'ok' => false,
        'mensagem' => 'Informe ?slug=barbearia-cabral ou ?id=ID_DO_TENANT.',
    ], 400);
}

try {
    $master = $cfg['master'] ?? [];

    $dbHost = (string)($master['host'] ?? 'localhost');
    $dbName = (string)($master['db'] ?? '');
    $dbUser = (string)($master['user'] ?? '');
    $dbPass = (string)($master['pass'] ?? '');
    $tabelaTenants = preg_replace('/[^a-z0-9_]/i', '', (string)($master['table'] ?? 'tenants'));

    if ($dbName === '' || $dbUser === '') {
        bb_diag_json([
            'ok' => false,
            'mensagem' => 'Configuração master incompleta em /public_html/config/marketplace.php.',
        ], 500);
    }

    if ($tabelaTenants === '') {
        $tabelaTenants = 'tenants';
    }

    $pdo = bb_diag_pdo_connect($dbHost, $dbName, $dbUser, $dbPass);

    if (!bb_diag_table_exists($pdo, $dbName, $tabelaTenants)) {
        bb_diag_json([
            'ok' => false,
            'mensagem' => 'Tabela de tenants não encontrada no banco master.',
            'banco' => $dbName,
            'tabela' => $tabelaTenants,
        ], 500);
    }

    $colunasObrigatorias = ['id', 'slug', 'nome', 'vencimento', 'valor_mensal'];
    foreach ($colunasObrigatorias as $coluna) {
        if (!bb_diag_col_exists($pdo, $dbName, $tabelaTenants, $coluna)) {
            bb_diag_json([
                'ok' => false,
                'mensagem' => 'Coluna obrigatória não encontrada na tabela de tenants.',
                'coluna' => $coluna,
                'tabela' => $tabelaTenants,
            ], 500);
        }
    }

    $temTelefoneWhatsapp = bb_diag_col_exists($pdo, $dbName, $tabelaTenants, 'telefone_whatsapp');
    $temWhatsapp = bb_diag_col_exists($pdo, $dbName, $tabelaTenants, 'whatsapp');
    $temEmail = bb_diag_col_exists($pdo, $dbName, $tabelaTenants, 'email');
    $temStatus = bb_diag_col_exists($pdo, $dbName, $tabelaTenants, 'status');
    $temAtivo = bb_diag_col_exists($pdo, $dbName, $tabelaTenants, 'ativo');
    $temBlockedAt = bb_diag_col_exists($pdo, $dbName, $tabelaTenants, 'blocked_at');
    $temDiasAviso = bb_diag_col_exists($pdo, $dbName, $tabelaTenants, 'dias_aviso');
    $temTimezone = bb_diag_col_exists($pdo, $dbName, $tabelaTenants, 'timezone');

    $telefoneSql = $temTelefoneWhatsapp ? '`telefone_whatsapp`' : ($temWhatsapp ? '`whatsapp`' : "''");
    $emailSql = $temEmail ? '`email`' : "''";
    $statusSql = $temStatus ? '`status`' : "''";
    $ativoSql = $temAtivo ? '`ativo`' : 'NULL';
    $blockedAtSql = $temBlockedAt ? '`blocked_at`' : 'NULL';
    $diasAvisoSql = $temDiasAviso ? '`dias_aviso`' : 'NULL';
    $timezoneSql = $temTimezone ? '`timezone`' : "'America/Sao_Paulo'";

    $where = $id > 0 ? 'id = :id' : 'slug = :slug';

    $sql = "
        SELECT id,
               slug,
               nome,
               vencimento,
               valor_mensal,
               {$telefoneSql} AS telefone_destino,
               {$emailSql} AS email,
               {$statusSql} AS status_tenant,
               {$ativoSql} AS ativo_tenant,
               {$blockedAtSql} AS blocked_at,
               {$diasAvisoSql} AS dias_aviso,
               {$timezoneSql} AS timezone_tenant
          FROM `{$tabelaTenants}`
         WHERE {$where}
         LIMIT 1
    ";

    $stmt = $pdo->prepare($sql);

    if ($id > 0) {
        $stmt->execute([':id' => $id]);
    } else {
        $stmt->execute([':slug' => $slug]);
    }

    $tenant = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$tenant) {
        bb_diag_json([
            'ok' => false,
            'mensagem' => 'Tenant não encontrado.',
            'slug' => $slug,
            'id' => $id,
        ], 404);
    }

    $timezoneTenant = (string)($tenant['timezone_tenant'] ?? 'America/Sao_Paulo');
    $hoje = bb_diag_data_hoje($timezoneTenant);
    $vencimento = (string)($tenant['vencimento'] ?? '');
    $diasParaVencer = bb_diag_dias_entre_datas($hoje, $vencimento);

    $diasAviso = (int)($tenant['dias_aviso'] ?? 0);
    if ($diasAviso <= 0) {
        $diasAviso = (int)($cfg['billing']['dias_antes_lembrete'] ?? 3);
    }

    $diasGraca = (int)($cfg['billing']['dias_graca'] ?? 2);

    $blocoCron = 'fora_da_regra_de_envio_hoje';

    if ($diasParaVencer === $diasAviso) {
        $blocoCron = 'aviso_antes_do_vencimento_com_link';
    } elseif ($diasParaVencer === 0) {
        $blocoCron = 'vencimento_hoje_com_link';
    } elseif ($diasParaVencer !== null && $diasParaVencer < 0 && abs($diasParaVencer) <= $diasGraca) {
        $blocoCron = 'atraso_dentro_da_carencia_com_link';
    } elseif ($diasParaVencer !== null && $diasParaVencer < 0 && abs($diasParaVencer) > $diasGraca) {
        $blocoCron = 'bloqueio_com_link';
    }

    $telefoneOriginal = (string)($tenant['telefone_destino'] ?? '');
    $telefoneNormalizado = bb_diag_normalizar_telefone_br($telefoneOriginal);

    $fatura = bb_diag_buscar_fatura(
        $pdo,
        $dbName,
        (int)$tenant['id'],
        $vencimento
    );

    $baseUrl = bb_diag_base_url($cfg);
    $linkPagamento = null;

    if ($baseUrl !== '' && !empty($fatura['id'])) {
        $linkPagamento = $baseUrl . '/pagamentos/pagamento_marketplace.php?fatura=' . (int)$fatura['id'];
    }

    bb_diag_json([
        'ok' => true,
        'sem_alterar_banco' => true,
        'sem_enviar_whatsapp' => true,
        'motivo_403_anterior' => 'A pasta /cron pode estar bloqueada para acesso público no servidor. Este diagnóstico foi movido para /pagamentos para teste por navegador.',
        'destinatario_correto' => 'tenants.telefone_whatsapp',
        'telefone_admin_config_nao_e_destinatario' => true,
        'tenant' => [
            'id' => (int)$tenant['id'],
            'slug' => (string)$tenant['slug'],
            'nome' => (string)$tenant['nome'],
            'status' => (string)($tenant['status_tenant'] ?? ''),
            'ativo' => $tenant['ativo_tenant'],
            'blocked_at' => $tenant['blocked_at'],
            'email' => (string)($tenant['email'] ?? ''),
            'vencimento' => $vencimento,
            'valor_mensal' => (string)($tenant['valor_mensal'] ?? ''),
            'dias_aviso' => $diasAviso,
            'timezone' => $timezoneTenant,
        ],
        'whatsapp' => [
            'telefone_original_banco' => $telefoneOriginal,
            'telefone_enviado_para_menuia' => $telefoneNormalizado,
            'telefone_valido_para_envio' => $telefoneNormalizado !== '',
            'observacao' => 'Se o banco tiver 45998548450, o envio vai como 5545998548450. Se já tiver 55, não duplica.',
        ],
        'cron' => [
            'hoje' => $hoje,
            'dias_para_vencer' => $diasParaVencer,
            'dias_aviso_usado' => $diasAviso,
            'dias_graca_usado' => $diasGraca,
            'bloco_que_rodaria_hoje' => $blocoCron,
        ],
        'fatura' => $fatura,
        'link_pagamento_se_fatura_existir' => $linkPagamento,
        'observacao_link' => $linkPagamento
            ? 'Este link é o link que deve ir no WhatsApp.'
            : 'Se a fatura ainda não existir, o cron real cria ou reutiliza antes de enviar o WhatsApp.',
    ]);
} catch (Throwable $e) {
    bb_diag_json([
        'ok' => false,
        'mensagem' => 'Erro ao executar diagnóstico.',
        'erro' => $e->getMessage(),
    ], 500);
}
