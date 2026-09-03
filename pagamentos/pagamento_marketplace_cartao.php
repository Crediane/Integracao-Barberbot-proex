<?php
declare(strict_types=1);
require_once __DIR__ . '/timezone_pagamentos.php';
bb_pagamentos_aplicar_timezone();

function bb_market_cfg(): array {
    $cfgFile = __DIR__ . '/../config/marketplace.php';
    if (!is_file($cfgFile)) {
        http_response_code(500);
        exit('Config do marketplace não encontrada.');
    }
    $cfg = require $cfgFile;
    if (!is_array($cfg) || empty($cfg['master']) || !is_array($cfg['master'])) {
        http_response_code(500);
        exit('Config do marketplace inválida.');
    }
    return $cfg;
}

function bb_market_pdo(array $cfg): PDO {
    return new PDO(
        "mysql:host={$cfg['master']['host']};dbname={$cfg['master']['db']};charset=utf8mb4",
        $cfg['master']['user'],
        (string)($cfg['master']['pass'] ?? ''),
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
}

function bb_market_cfg_db_pdo(array $dbCfg, array $fallbackCfg = []): ?PDO {
    $host = trim((string)($dbCfg['host'] ?? ($fallbackCfg['host'] ?? '')));
    $db = preg_replace('/[^a-z0-9_]/i', '', (string)($dbCfg['db'] ?? ''));
    $user = trim((string)($dbCfg['user'] ?? ($fallbackCfg['user'] ?? '')));
    $pass = array_key_exists('pass', $dbCfg)
        ? (string)$dbCfg['pass']
        : (string)($fallbackCfg['pass'] ?? '');

    if ($host === '' || $db === '' || $user === '') {
        return null;
    }

    try {
        $pdoCfg = new PDO(
            "mysql:host={$host};dbname={$db};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        $dbAtual = (string)$pdoCfg->query('SELECT DATABASE()')->fetchColumn();
        if ($dbAtual !== $db) {
            return null;
        }

        return $pdoCfg;
    } catch (Throwable $e) {
        return null;
    }
}

function bb_market_global_config_pdos(array $cfg): array {
    $masterCfg = isset($cfg['master']) && is_array($cfg['master']) ? $cfg['master'] : [];
    $configuracoes = [$masterCfg];

    if (!empty($cfg['auth_master']) && is_array($cfg['auth_master'])) {
        $configuracoes[] = $cfg['auth_master'];
    }

    $pdos = [];
    $adicionados = [];

    foreach ($configuracoes as $dbCfg) {
        if (!is_array($dbCfg)) {
            continue;
        }

        $pdoCfg = bb_market_cfg_db_pdo($dbCfg, $masterCfg);
        if (!($pdoCfg instanceof PDO)) {
            continue;
        }

        try {
            $chave = (string)$pdoCfg->query('SELECT DATABASE()')->fetchColumn();
        } catch (Throwable $e) {
            continue;
        }

        if ($chave === '' || isset($adicionados[$chave])) {
            continue;
        }

        $adicionados[$chave] = true;
        $pdos[] = $pdoCfg;
    }

    return $pdos;
}

function bb_market_cfg_column_exists(PDO $pdo, string $table, string $column): bool {
    try {
        $st = $pdo->prepare(
            'SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS '
            . 'WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1'
        );
        $st->execute([':t' => $table, ':c' => $column]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function bb_market_get_cfg_value(array $cfg, string $campo): string {
    $campo = preg_replace('/[^a-z0-9_]/i', '', $campo);
    if ($campo === '') {
        return '';
    }

    $fontes = [$cfg];
    if (!empty($cfg['app']) && is_array($cfg['app'])) {
        $fontes[] = $cfg['app'];
    }
    if (!empty($cfg['billing']) && is_array($cfg['billing'])) {
        $fontes[] = $cfg['billing'];
    }

    foreach ($fontes as $fonte) {
        $valor = trim((string)($fonte[$campo] ?? ''));
        if ($valor !== '') {
            return $valor;
        }

        if ($campo === 'access_token_mp') {
            $valorAlternativo = trim((string)($fonte['mp_access_token'] ?? ''));
            if ($valorAlternativo !== '') {
                return $valorAlternativo;
            }
        }
    }

    foreach (bb_market_global_config_pdos($cfg) as $pdoCfg) {
        if (!bb_market_cfg_column_exists($pdoCfg, 'config', $campo)) {
            continue;
        }

        try {
            $row = $pdoCfg->query("SELECT `{$campo}` FROM config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
            $valor = trim((string)($row[$campo] ?? ''));
            if ($valor !== '') {
                return $valor;
            }
        } catch (Throwable $e) {
            // Continua somente entre bancos globais permitidos.
        }
    }

    return '';
}

$cfg = bb_market_cfg();
$pdo = bb_market_pdo($cfg);

$faturaId = (int)($_GET['fatura'] ?? 0);
if ($faturaId <= 0) {
    http_response_code(400);
    exit('Fatura inválida.');
}

$sql = "
    SELECT
        f.id,
        f.tenant_id,
        f.vencimento,
        f.valor,
        f.status,
        t.slug,
        t.nome,
        t.email,
        t.dono_email
    FROM marketplace_faturas f
    INNER JOIN tenants t ON t.id = f.tenant_id
    WHERE f.id = :id
    LIMIT 1
";
$st = $pdo->prepare($sql);
$st->execute([':id' => $faturaId]);
$fatura = $st->fetch(PDO::FETCH_ASSOC);
if (!$fatura) {
    http_response_code(404);
    exit('Fatura não encontrada.');
}

$publicKey = bb_market_get_cfg_value($cfg, 'public_key_mp');
$nome = (string)($fatura['nome'] ?? $fatura['slug'] ?? 'Barbearia');
$slug = (string)($fatura['slug'] ?? '');
$status = (string)($fatura['status'] ?? 'pending');
$valor = (float)($fatura['valor'] ?? 0);
$vencimento = (string)($fatura['vencimento'] ?? '');
$descricao = 'Plano Assinatura';
$valorF = number_format($valor, 2, ',', '.');
$vencimentoF = ($vencimento !== '' && $vencimento !== '0000-00-00') ? date('d/m/Y', strtotime($vencimento)) : '-';
$emailPayer = trim((string)($fatura['email'] ?? ''));
if ($emailPayer === '') $emailPayer = trim((string)($fatura['dono_email'] ?? ''));
$paymentIdNow = preg_replace('/\D+/', '', (string)($_GET['id'] ?? ''));
$mensagemErroTela = '';
if ($status === 'paid') $mensagemErroTela = 'Esta cobrança já foi paga.';
elseif ($publicKey === '') $mensagemErroTela = 'Public Key do Mercado Pago não configurada.';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Pagamento Cartão - <?php echo htmlspecialchars($nome, ENT_QUOTES, 'UTF-8'); ?></title>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<?php if ($publicKey !== '') { ?><script src="https://sdk.mercadopago.com/js/v2"></script><?php } ?>
<style>
body{background:#111;color:#eee;font-family:Arial,Helvetica,sans-serif;margin:0}
.wrap{max-width:900px;margin:0 auto;padding:18px}
.titulo{margin:0 0 8px 0;font-size:22px}
.dados{opacity:.95;margin-bottom:14px;line-height:1.35}
.dados .nome-topo{font-size:18px;font-weight:700;margin-bottom:8px;color:#fff}
.box-erro{display:none;background:#2a0d0d;border:1px solid #7a1f1f;padding:10px;border-radius:8px;margin:10px 0;color:#ff8d8d}
.box-pago{display:none;background:#0f2a13;border:1px solid #1f7a2b;padding:12px;border-radius:8px;margin-top:12px}
.msg-tela{background:#101b3b;border-radius:18px;padding:26px 26px 30px;box-shadow:0 0 0 1px rgba(255,255,255,.03) inset;margin-top:16px}
</style>
</head>
<body>
<div class="wrap">
    <div class="dados">
        <div class="nome-topo"><?php echo htmlspecialchars($nome, ENT_QUOTES, 'UTF-8'); ?><?php echo $slug !== '' ? ' (' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . ')' : ''; ?></div>
        <h2 class="titulo">Pagamento com Cartão</h2>
        <div><b>Descrição:</b> <?php echo htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8'); ?></div>
        <div><b>Vencimento:</b> <?php echo htmlspecialchars($vencimentoF, ENT_QUOTES, 'UTF-8'); ?></div>
        <div><b>Valor:</b> R$ <?php echo htmlspecialchars($valorF, ENT_QUOTES, 'UTF-8'); ?></div>
    </div>

    <div id="mp_error" class="box-erro"></div>

    <?php if ($mensagemErroTela !== '') { ?>
        <div class="msg-tela"><?php echo htmlspecialchars($mensagemErroTela, ENT_QUOTES, 'UTF-8'); ?></div>
    <?php } else { ?>
        <div id="paymentBrick_container"></div>
        <div id="statusScreenBrick_container" style="margin-top:12px;"></div>
        <div id="form-pago" class="box-pago"><b>Pagamento confirmado.</b> Obrigado!</div>
    <?php } ?>
</div>

<?php if ($mensagemErroTela === '' && $publicKey !== '') { ?>
<script>
const mp = new MercadoPago(<?php echo json_encode($publicKey); ?>, { locale: 'pt-BR' });
const bricksBuilder = mp.bricks();
function showMpError(msg){
    const el = document.getElementById('mp_error');
    if(el){ el.style.display = 'block'; el.innerText = msg || 'Não foi possível carregar o pagamento. Tente novamente.'; }
}
const renderPaymentBrick = async (bricksBuilder) => {
    const payer = { email: <?php echo json_encode($emailPayer); ?> };
    const settings = {
        initialization: { amount: <?php echo json_encode((float)$valor); ?>, payer: payer },
        customization: {
            visual: { style: { theme: "dark" } },
            paymentMethods: { creditCard: "all", maxInstallments: 12 },
        },
        callbacks: {
            onReady: () => {},
            onSubmit: ({ formData }) => {
                formData.fatura = <?php echo json_encode((int)$faturaId); ?>;
                formData.descricao_local = <?php echo json_encode($descricao); ?>;
                return new Promise((resolve, reject) => {
                    fetch(<?php echo json_encode('process_payment_marketplace.php'); ?>, {
                        method: "POST",
                        headers: { "Content-Type": "application/json" },
                        body: JSON.stringify(formData),
                    })
                    .then((response) => response.json())
                    .then((response) => {
                        if (response && response.status == true) {
                            window.location.href = <?php echo json_encode('pagamento_marketplace_cartao.php?fatura=' . (int)$faturaId . '&id='); ?> + response.id;
                        } else {
                            showMpError((response && response.message) ? response.message : 'Falha ao iniciar pagamento com cartão.');
                        }
                        resolve();
                    })
                    .catch(() => {
                        showMpError('Falha de comunicação ao iniciar pagamento com cartão.');
                        reject();
                    });
                });
            },
            onError: (error) => { console.error(error); showMpError('Não foi possível carregar o pagamento com cartão. Verifique as configurações do Mercado Pago.'); },
        },
    };
    try{ window.paymentBrickController = await bricksBuilder.create("payment", "paymentBrick_container", settings); }
    catch(e){ console.error(e); showMpError('Erro ao montar o formulário de pagamento com cartão.'); }
};

const renderStatusScreenBrick = async (bricksBuilder) => {
    const paymentId = <?php echo json_encode($paymentIdNow); ?>;
    const settings = {
        initialization: { paymentId: paymentId },
        customization: { visual: { hideStatusDetails: false, hideTransactionDate: false, style: { theme: 'dark' } } },
        callbacks: {
            onReady: () => { check(paymentId, <?php echo json_encode((string)$faturaId); ?>); },
            onError: (error) => { console.error(error); },
        },
    };
    try{ window.statusScreenBrickController = await bricksBuilder.create('statusScreen', 'statusScreenBrick_container', settings); }
    catch(e){ console.error(e); showMpError('Erro ao montar a tela de status do pagamento.'); }
};

const paymentIdNow = <?php echo json_encode($paymentIdNow); ?>;
if (paymentIdNow) { renderStatusScreenBrick(bricksBuilder); } else { renderPaymentBrick(bricksBuilder); }

function check(id, fatura) {
    $.ajax({
        url: <?php echo json_encode('process_payment_marketplace.php?acc=check&id='); ?> + encodeURIComponent(id) + '&fatura=' + encodeURIComponent(fatura),
        method: 'GET',
        dataType: 'json'
    }).done(function(response) {
        try {
            if (response.status == "pago") {
                $("#statusScreenBrick_container").slideUp("fast");
                $("#form-pago").slideDown("fast");
            } else {
                setTimeout(() => { check(id, fatura); }, 3000);
            }
        } catch (error) {
            showMpError("Erro ao localizar o pagamento, contacte o suporte.");
        }
    }).fail(function(){
        setTimeout(() => { check(id, fatura); }, 5000);
    });
}
</script>
<?php } ?>
</body>
</html>
