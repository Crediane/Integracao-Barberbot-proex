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
    if (!is_array($cfg) || empty($cfg['master'])) {
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
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
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
        f.mp_init_point,
        t.slug,
        t.nome
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

$descricao = 'Plano Assinatura';
$nome = (string)($fatura['nome'] ?? $fatura['slug'] ?? 'Barbearia');
$slug = (string)($fatura['slug'] ?? '');
$vencimento = (string)($fatura['vencimento'] ?? '');
$valor = (float)($fatura['valor'] ?? 0);
$status = (string)($fatura['status'] ?? 'pending');

$valorF = number_format($valor, 2, ',', '.');
$vencimentoF = ($vencimento !== '' && $vencimento !== '0000-00-00') ? date('d/m/Y', strtotime($vencimento)) : '-';

$mensagemErro = '';
$mensagemSucesso = '';

if ($status === 'paid') {
    $mensagemSucesso = 'Esta cobrança já foi paga.';
}

if (!empty($_GET['ok']) && $_GET['ok'] === '1') {
    $mensagemSucesso = 'Pagamento enviado com sucesso. Aguarde a confirmação.';
}

if (!empty($_GET['erro'])) {
    $mensagemErro = trim((string)$_GET['erro']);
}
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pagamento - <?php echo htmlspecialchars($nome, ENT_QUOTES, 'UTF-8'); ?></title>
    <style>
        *{box-sizing:border-box}
        body{margin:0;font-family:Arial, Helvetica, sans-serif;background:#050505;color:#fff}
        .wrap{width:100%;max-width:980px;margin:0 auto;padding:48px 24px 60px}
        .topo-nome{color:#fff;font-size:18px;margin-bottom:8px;font-weight:700}
        .titulo{font-size:26px;font-weight:700;margin:0 0 8px}
        .infos{margin:0 0 28px;color:#d8d8d8;font-size:18px;line-height:1.35}
        .infos strong{color:#fff}
        .card{background:#101b3b;border-radius:18px;padding:26px 26px 30px;box-shadow:0 0 0 1px rgba(255,255,255,.03) inset}
        .card h2{margin:0 0 22px;font-size:22px;font-weight:700;color:#fff}
        .lista{border:1px solid rgba(255,255,255,.25);border-radius:2px;overflow:hidden;background:rgba(0,0,0,.08)}
        .linha{display:flex;align-items:center;gap:18px;padding:14px 16px;border-bottom:1px solid rgba(255,255,255,.2);cursor:pointer}
        .linha:last-child{border-bottom:none}
        .radio{width:18px;height:18px;border-radius:50%;border:2px solid rgba(255,255,255,.35);position:relative;flex:0 0 18px}
        input[type=radio]{display:none}
        input[type=radio]:checked + .radio::after{content:'';position:absolute;inset:3px;background:#67d2cf;border-radius:50%}
        .icone{width:42px;height:42px;border-radius:50%;background:#fff;color:#101b3b;display:flex;align-items:center;justify-content:center;font-size:22px;font-weight:700;flex:0 0 42px}
        .texto{display:flex;flex-direction:column;gap:5px}
        .texto strong{font-size:20px;color:#fff;font-weight:600}
        .tag{display:inline-block;width:max-content;background:#58b325;color:#fff;font-size:12px;line-height:1;padding:4px 8px;border-radius:999px;font-weight:700}
        .acoes{margin-top:24px}
        .btn{appearance:none;border:none;background:#58cbc8;color:#06111f;font-size:18px;font-weight:700;border-radius:4px;padding:16px 26px;cursor:pointer;display:inline-flex;align-items:center;gap:12px;min-width:170px;justify-content:center;text-decoration:none}
        .btn:disabled{opacity:.6;cursor:not-allowed}
        .lock{font-size:20px;line-height:1}
        .mensagem{margin-top:18px;font-size:15px}
        .mensagem.erro{color:#ff9a9a}
        .mensagem.sucesso{color:#8be3a2}
        @media (max-width: 640px){
            .wrap{padding:28px 16px 40px}
            .titulo{font-size:22px}
            .infos{font-size:16px}
            .card{padding:18px}
            .texto strong{font-size:18px}
        }
    </style>
</head>
<body>
    <div class="wrap">
        <div class="topo-nome"><?php echo htmlspecialchars($nome, ENT_QUOTES, 'UTF-8'); ?><?php echo $slug !== '' ? ' (' . htmlspecialchars($slug, ENT_QUOTES, 'UTF-8') . ')' : ''; ?></div>
        <h1 class="titulo">Pagamento</h1>
        <div class="infos">
            <div><strong>Descrição:</strong> <?php echo htmlspecialchars($descricao, ENT_QUOTES, 'UTF-8'); ?></div>
            <div><strong>Vencimento:</strong> <?php echo htmlspecialchars($vencimentoF, ENT_QUOTES, 'UTF-8'); ?></div>
            <div><strong>Valor:</strong> R$ <?php echo htmlspecialchars($valorF, ENT_QUOTES, 'UTF-8'); ?></div>
        </div>

        <div class="card">
            <h2>Meios de pagamento</h2>

            <form id="form-pagamento" method="get" action="">
                <input type="hidden" name="fatura" value="<?php echo (int)$faturaId; ?>">

                <div class="lista">
                    <label class="linha">
                        <input type="radio" name="meio_pagamento" value="credito">
                        <span class="radio"></span>
                        <span class="icone">💳</span>
                        <span class="texto">
                            <strong>Cartão de crédito</strong>
                            <span class="tag">Sem acréscimo</span>
                        </span>
                    </label>

                    <label class="linha">
                        <input type="radio" name="meio_pagamento" value="pix">
                        <span class="radio"></span>
                        <span class="icone">◈</span>
                        <span class="texto">
                            <strong>Pix</strong>
                        </span>
                    </label>
                </div>

                <div class="acoes">
                    <button type="submit" class="btn" id="btn-pagar" <?php echo $status === 'paid' ? 'disabled' : ''; ?>>
                        <span class="lock">🔒</span>
                        <span>Pagar</span>
                    </button>
                </div>

                <div class="mensagem erro" id="mensagem-erro"><?php echo htmlspecialchars($mensagemErro, ENT_QUOTES, 'UTF-8'); ?></div>
                <div class="mensagem sucesso" id="mensagem-sucesso"><?php echo htmlspecialchars($mensagemSucesso, ENT_QUOTES, 'UTF-8'); ?></div>
            </form>
        </div>
    </div>

    <script>
        (function(){
            var form = document.getElementById('form-pagamento');
            var btn = document.getElementById('btn-pagar');
            var erro = document.getElementById('mensagem-erro');
            var statusPago = <?php echo json_encode($status === 'paid'); ?>;

            if (!form || !btn) return;

            form.addEventListener('submit', function(e){
                e.preventDefault();

                if (statusPago) {
                    erro.textContent = 'Esta cobrança já foi paga.';
                    return;
                }

                var selecionado = document.querySelector('input[name="meio_pagamento"]:checked');
                if (!selecionado) {
                    erro.textContent = 'Selecione uma forma de pagamento.';
                    return;
                }

                erro.textContent = '';
                btn.disabled = true;

                var fatura = <?php echo (int)$faturaId; ?>;
                if (selecionado.value === 'pix') {
                    window.location.href = 'pagamento_marketplace_pix.php?fatura=' + encodeURIComponent(fatura);
                    return;
                }

                if (selecionado.value === 'credito') {
                    window.location.href = 'pagamento_marketplace_cartao.php?fatura=' + encodeURIComponent(fatura);
                    return;
                }

                btn.disabled = false;
                erro.textContent = 'Forma de pagamento inválida.';
            });
        })();
    </script>
</body>
</html>
