<?php
require_once __DIR__ . '/../../verificar.php';
header('Content-Type: application/json; charset=utf-8');

// ============================================================================
// /public_html/sistema/painel/paginas/marketplace/cobranca.php
// Ajuste aplicado SEM mudar a lógica:
// - sincroniza a fatura pendente com o vencimento atual do tenant
// - envia a cobrança com a data correta
// - mantém geração de cobrança e link local do sistema
// ============================================================================

function bb_market_json_exit(array $payload): void {
    if (ob_get_level() > 0) {
        while (ob_get_level() > 0) {
            @ob_end_clean();
        }
    }
    if (!headers_sent()) {
        header('Content-Type: application/json; charset=utf-8');
    }
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function bb_market_ler_config_whatsapp_pdo(PDO $pdo): array {
    $api = '';
    $token = '';
    $instancia = '';

    try {
        $colunas = [];
        $stmtColunas = $pdo->query("SHOW COLUMNS FROM config");
        foreach ($stmtColunas->fetchAll(PDO::FETCH_ASSOC) as $coluna) {
            if (!empty($coluna['Field'])) {
                $colunas[(string)$coluna['Field']] = true;
            }
        }

        $selecionar = [];
        foreach (['api', 'token', 'instancia'] as $campo) {
            if (isset($colunas[$campo])) {
                $selecionar[] = "`{$campo}`";
            }
        }

        if (empty($selecionar)) {
            return ['api' => '', 'token' => '', 'instancia' => ''];
        }

        $row = $pdo->query("SELECT " . implode(', ', $selecionar) . " FROM config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $api = trim((string)($row['api'] ?? ''));
            $token = trim((string)($row['token'] ?? ''));
            $instancia = trim((string)($row['instancia'] ?? ''));
        }

        if ($api === '' && $token !== '' && $instancia !== '') {
            $api = 'menuia';
        }
    } catch (Throwable $e) {
        return ['api' => '', 'token' => '', 'instancia' => ''];
    }

    return ['api' => $api, 'token' => $token, 'instancia' => $instancia];
}

$paths = [
    __DIR__ . '/../../../conexao.php',
    __DIR__ . '/../../conexao.php',
];

$okConn = false;
foreach ($paths as $p) {
    if (is_file($p)) {
        require_once $p;
        $okConn = true;
        break;
    }
}
if (!$okConn) {
    bb_market_json_exit(['ok' => false, 'msg' => 'conexao.php não encontrado.']);
}

$acao = isset($_POST['acao']) ? trim((string)$_POST['acao']) : '';
$id   = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    bb_market_json_exit(['ok' => false, 'msg' => 'ID inválido.']);
}

if ($acao !== 'gerar' && $acao !== 'enviar') {
    bb_market_json_exit(['ok' => false, 'msg' => 'Ação inválida.']);
}

$cfgFile = __DIR__ . "/../../../../config/marketplace.php";
if (!is_file($cfgFile)) {
    bb_market_json_exit(['ok' => false, 'msg' => 'config/marketplace.php não encontrado.']);
}
$cfg = require $cfgFile;
if (!is_array($cfg) || empty($cfg['master']) || empty($cfg['tenant'])) {
    bb_market_json_exit(['ok' => false, 'msg' => 'Config marketplace inválida.']);
}

$mpToken = isset($access_token_mp) ? trim((string)$access_token_mp) : '';

if ($mpToken === '') {
    try {
        $tHost = (string)($cfg['tenant']['host'] ?? 'localhost');
        $tDb   = (string)($cfg['tenant']['default_db'] ?? '');
        $tUser = (string)($cfg['tenant']['user'] ?? '');
        $tPass = (string)($cfg['tenant']['pass'] ?? '');

        if ($tDb !== '' && $tUser !== '') {
            $pdoCfg = new PDO(
                "mysql:host={$tHost};dbname={$tDb};charset=utf8mb4",
                $tUser,
                $tPass,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
                ]
            );
            $row = $pdoCfg->query("SELECT access_token_mp FROM config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            if ($row && !empty($row['access_token_mp'])) {
                $mpToken = trim((string)$row['access_token_mp']);
            }
        }
    } catch (Throwable $e) {
        // silencioso
    }
}

if ($mpToken === '') {
    bb_market_json_exit(['ok' => false, 'msg' => 'Access Token do Mercado Pago não configurado.']);
}

try {
    $pdoM = new PDO(
        "mysql:host=" . $cfg['master']['host'] . ";dbname=" . $cfg['master']['db'] . ";charset=utf8mb4",
        $cfg['master']['user'],
        (string)($cfg['master']['pass'] ?? ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (Throwable $e) {
    bb_market_json_exit(['ok' => false, 'msg' => 'Falha ao conectar no banco master.']);
}

$tenantsTable = preg_replace('/[^a-z0-9_]/i', '', (string)($cfg['master']['table'] ?? 'tenants'));
if ($tenantsTable === '') {
    $tenantsTable = 'tenants';
}

$st = $pdoM->prepare("
    SELECT id, slug, nome, telefone_whatsapp, whatsapp, email, dono_email, vencimento, valor_mensal, status
      FROM `{$tenantsTable}`
     WHERE id = ?
     LIMIT 1
");
$st->execute([$id]);
$t = $st->fetch(PDO::FETCH_ASSOC);

if (!$t) {
    bb_market_json_exit(['ok' => false, 'msg' => 'Barbearia não encontrada.']);
}

$valor = (float)($t['valor_mensal'] ?? 0);
if ($valor <= 0) {
    bb_market_json_exit(['ok' => false, 'msg' => 'Valor mensal inválido (cadastre um valor no tenant).']);
}

$slug = (string)($t['slug'] ?? '');
$nome = (string)($t['nome'] ?? $slug);
$vencimento = (string)($t['vencimento'] ?? '');
if ($vencimento === '' || $vencimento === '0000-00-00') {
    $vencimento = date('Y-m-d');
}

$stF = $pdoM->prepare("
    SELECT id
      FROM marketplace_faturas
     WHERE tenant_id = :tenant_id
       AND status = 'pending'
     ORDER BY vencimento DESC, id DESC
     LIMIT 1
");
$stF->execute([':tenant_id' => $id]);
$fat = $stF->fetch(PDO::FETCH_ASSOC);

$faturaId = 0;

try {
    $pdoM->beginTransaction();

    if ($fat && !empty($fat['id'])) {
        $faturaId = (int)$fat['id'];

        $upF = $pdoM->prepare("
            UPDATE marketplace_faturas
               SET vencimento = :vencimento,
                   valor = :valor,
                   status = 'pending'
             WHERE id = :id
             LIMIT 1
        ");
        $upF->execute([
            ':vencimento' => $vencimento,
            ':valor'      => $valor,
            ':id'         => $faturaId
        ]);
    } else {
        $insF = $pdoM->prepare("
            INSERT INTO marketplace_faturas
                (tenant_id, vencimento, valor, status, criado_em)
            VALUES
                (:tenant_id, :vencimento, :valor, 'pending', :criado_em)
        ");
        $insF->execute([
            ':tenant_id'  => $id,
            ':vencimento' => $vencimento,
            ':valor'      => $valor,
            ':criado_em'  => date('Y-m-d H:i:s')
        ]);

        $faturaId = (int)$pdoM->lastInsertId();
    }

    $pdoM->commit();
} catch (Throwable $e) {
    if ($pdoM->inTransaction()) {
        $pdoM->rollBack();
    }
    bb_market_json_exit(['ok' => false, 'msg' => 'Erro ao preparar fatura do marketplace.']);
}

if ($faturaId <= 0) {
    bb_market_json_exit(['ok' => false, 'msg' => 'Fatura do marketplace não encontrada.']);
}

$baseUrl = '';
if (isset($url_sistema) && trim((string)$url_sistema) !== '') {
    $tmp = rtrim((string)$url_sistema, '/');
    $tmp = preg_replace('~/sistema$~i', '', $tmp);
    $baseUrl = $tmp;
} else {
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
        $scheme = ($_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : $scheme;
    }
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $baseUrl = $scheme . '://' . $host;
}

$linkLocal = $baseUrl . '/pagamentos/pagamento_marketplace.php?fatura=' . $faturaId;

$whatsSent = false;
$whatsMsg = '';

if ($acao === 'gerar' || $acao === 'enviar') {
    $tel = '';
    if (!empty($t['telefone_whatsapp'])) {
        $tel = (string)$t['telefone_whatsapp'];
    } elseif (!empty($t['whatsapp'])) {
        $tel = (string)$t['whatsapp'];
    }

    $tel = preg_replace('/\D+/', '', $tel);

    if ($tel !== '') {
        if (strlen($tel) <= 11) {
            $telefone = '55' . $tel;
        } else {
            $telefone = $tel;
        }

        $apiW = '';
        $tokenW = '';
        $instanciaW = '';

        try {
            if (isset($pdo) && $pdo instanceof PDO) {
                $c = bb_market_ler_config_whatsapp_pdo($pdo);
                $apiW = (string)($c['api'] ?? '');
                $tokenW = (string)($c['token'] ?? '');
                $instanciaW = (string)($c['instancia'] ?? '');
            }
        } catch (Throwable $e) {
            // silencioso
        }

        if ($apiW !== '' && $tokenW !== '' && $instanciaW !== '') {
            $valorF = number_format($valor, 2, ',', '.');
            $vencF = date('d/m/Y', strtotime($vencimento));

            $mensagem  = "💳 *Assinatura BarberBot*\n";
            $mensagem .= "🏪 Barbearia: *{$nome}*\n";
            if ($slug !== '') {
                $mensagem .= "🔖 Slug: {$slug}\n";
            }
            $mensagem .= "💰 Valor: R$ {$valorF}\n";
            $mensagem .= "📅 Vencimento: {$vencF}\n\n";
            $mensagem .= "👉 Clique para pagar:\n";
            $mensagem .= $linkLocal;

            $api = $apiW;
            $token = $tokenW;
            $instancia = $instanciaW;

            $apiPaths = [
                __DIR__ . "/../../../ajax/api-texto.php",
                __DIR__ . "/../../../../ajax/api-texto.php",
                __DIR__ . "/../../../../pagamentos/api-texto.php",
            ];

            $apiFile = '';
            foreach ($apiPaths as $ap) {
                if (is_file($ap)) {
                    $apiFile = $ap;
                    break;
                }
            }

            if ($apiFile !== '') {
                ob_start();
                include $apiFile;
                $apiOut = trim((string)ob_get_clean());
                $whatsSent = true;
                $whatsMsg = $apiOut;
            } else {
                $whatsMsg = 'Arquivo api-texto.php não encontrado.';
            }
        } else {
            $whatsMsg = 'Config de WhatsApp (api/token/instancia) não encontrada na tabela config.';
        }
    } else {
        $whatsMsg = 'WhatsApp do tenant está vazio. Cadastre no Marketplace.';
    }
}

bb_market_json_exit([
    'ok' => true,
    'url' => $linkLocal,
    'fatura_id' => $faturaId,
    'whats_sent' => $whatsSent,
    'whats_msg' => $whatsMsg
]);
