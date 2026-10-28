<?php
// bloqueio.php
// NÃO inclua conexao.php ou header.php aqui para não gerar conflito se o banco estiver off

$nome_sistema = isset($nome_sistema) && trim((string)$nome_sistema) !== ''
    ? trim((string)$nome_sistema)
    : 'BarberBot';

$loja = isset($loja) ? trim((string)$loja) : '';

if ($loja === '' && isset($_GET['loja'])) {
    $loja = trim((string)$_GET['loja']);
}

if ($loja === '') {
    $uri_atual = isset($_SERVER['REQUEST_URI']) ? (string)$_SERVER['REQUEST_URI'] : '';
    if ($uri_atual !== '' && preg_match('#/loja/([^/]+)#i', $uri_atual, $mLoja)) {
        $loja = trim((string)$mLoja[1]);
    }
}

$https_ativo = (
    (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ||
    (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443)
);

$protocolo = $https_ativo ? 'https' : 'http';
$host_atual = isset($_SERVER['HTTP_HOST']) && trim((string)$_SERVER['HTTP_HOST']) !== ''
    ? trim((string)$_SERVER['HTTP_HOST'])
    : 'jc.tec.br';

$base_publica = $protocolo . '://' . $host_atual;
if ($loja !== '') {
    $base_publica .= '/loja/' . rawurlencode($loja);
}

$logo_sistema = '';
$fontes_logo = [
    $caminho_logo ?? '',
    $logo_sistema ?? '',
    $img_logo ?? '',
    $logo ?? ''
];

foreach ($fontes_logo as $fonte_logo) {
    $fonte_logo = trim((string)$fonte_logo);
    if ($fonte_logo !== '') {
        if (preg_match('#^https?://#i', $fonte_logo)) {
            $logo_sistema = $fonte_logo;
        } else {
            $logo_sistema = $base_publica . '/' . ltrim($fonte_logo, '/');
        }
        break;
    }
}

$numero_whatsapp = '';
$fontes_whatsapp = [
    $telefone_administrador ?? '',
    $telefone_admin ?? '',
    $tel_whatsapp ?? '',
    $telefone_whatsapp ?? '',
    $whatsapp ?? '',
    $whatsapp_suporte ?? '',
    $telefone_suporte ?? ''
];

foreach ($fontes_whatsapp as $fone) {
    $fone = preg_replace('/\D+/', '', (string)$fone);
    if ($fone !== '') {
        $numero_whatsapp = $fone;
        break;
    }
}

if ($numero_whatsapp === '') {
    $numero_whatsapp = '5545999580058';
}

$texto_botao = $loja !== '' ? 'Falar com Administrador' : 'Falar com Suporte';
$link_whatsapp = 'https://wa.me/' . $numero_whatsapp;
?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title><?php echo htmlspecialchars($nome_sistema, ENT_QUOTES, 'UTF-8'); ?> | Acesso Suspenso</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">

    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background-color: #f3f4f6;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #1f2937;
            padding: 20px;
        }

        .lock-container {
            background: #ffffff;
            padding: 42px 40px;
            border-radius: 16px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.10), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            text-align: center;
            max-width: 500px;
            width: 100%;
            border-top: 5px solid #dc2626;
        }

        .logo-area {
            margin: 0 auto 24px;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 58px;
        }

        .logo-area img {
            max-width: 170px;
            max-height: 64px;
            width: auto;
            height: auto;
            display: block;
        }

        .icon-area {
            background-color: #fee2e2;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }

        .icon-area i {
            font-size: 32px;
            color: #dc2626;
        }

        h1 {
            color: #111827;
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 14px;
        }

        p {
            color: #6b7280;
            line-height: 1.6;
            margin-bottom: 28px;
            font-size: 16px;
        }

        .btn-contato {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            width: 100%;
            background-color: #2563eb;
            color: #ffffff;
            padding: 15px 18px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: 700;
            font-size: 16px;
            transition: all 0.25s ease;
            box-shadow: 0 4px 6px -1px rgba(37, 99, 235, 0.20);
        }

        .btn-contato:hover {
            background-color: #1d4ed8;
            transform: translateY(-1px);
            box-shadow: 0 10px 15px -3px rgba(37, 99, 235, 0.28);
        }

        .footer-note {
            margin-top: 22px;
            font-size: 12px;
            color: #9ca3af;
        }

        @media (max-width: 576px) {
            .lock-container {
                padding: 34px 22px;
            }

            h1 {
                font-size: 22px;
            }

            p {
                font-size: 15px;
            }
        }
    </style>
</head>
<body>
    <div class="lock-container">
        <?php if ($logo_sistema !== '') { ?>
            <div class="logo-area">
                <img src="<?php echo htmlspecialchars($logo_sistema, ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($nome_sistema, ENT_QUOTES, 'UTF-8'); ?>">
            </div>
        <?php } else { ?>
            <div class="icon-area">
                <i class="fa-solid fa-lock"></i>
            </div>
        <?php } ?>

        <h1>Acesso Suspenso</h1>

        <p>
            A assinatura deste sistema expirou ou foi pausada temporariamente. O acesso às funcionalidades foi interrompido.
            <br><br>
            <strong>Para regularizar e liberar o acesso imediato, entre em contato com o administrador.</strong>
        </p>

        <a href="<?php echo htmlspecialchars($link_whatsapp, ENT_QUOTES, 'UTF-8'); ?>" class="btn-contato" target="_blank" rel="noopener noreferrer">
            <i class="fa-brands fa-whatsapp" style="font-size: 1.15rem;"></i>
            <?php echo htmlspecialchars($texto_botao, ENT_QUOTES, 'UTF-8'); ?>
        </a>

        <div class="footer-note">
            ID: <?php echo date('Ymd'); ?>-LOCK
        </div>
    </div>
</body>
</html>
