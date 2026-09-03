<?php
require_once __DIR__ . '/sistema/conexao.php';

/* Helpers suaves */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function so_digitos($s){ return preg_replace('/\D+/', '', (string)$s); }

/* Fallbacks gentis caso não existam no seu projeto */
if (!isset($nome_sistema) || !$nome_sistema) { $nome_sistema = 'Jacy Cabeleireiro'; }
if (!isset($url_sistema) || !$url_sistema) {
  $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https://' : 'http://';
  $url_sistema = $scheme . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/';
}

/* Query params (opcionais) */
$nomeParam = trim((string)($_GET['nome'] ?? ''));
$msgParam  = trim((string)($_GET['msg']  ?? ''));

$ok   = isset($_GET['ok']);
$ja   = isset($_GET['ja']);
$erro = isset($_GET['erro']);

$titulo = 'Bem-vindo(a)!';
$descricao = $msgParam ?: 'Recebemos seus dados com sucesso. Em instantes você receberá uma mensagem no WhatsApp com as instruções.';

$badgeCor = '#16a34a'; // verde
$icone    = 'fa-circle-check';

if ($ja) {
  $titulo   = 'Cadastro Já Existente';
  $descricao = $msgParam ?: 'Encontramos um cadastro com este WhatsApp. Se precisar, fale conosco pelo botão abaixo.';
  $badgeCor = '#0ea5e9'; // azul
  $icone    = 'fa-circle-info';
} elseif ($erro) {
  $titulo   = 'Ops, algo não deu certo';
  $descricao = $msgParam ?: 'Não conseguimos concluir a solicitação. Por favor, tente novamente ou fale conosco.';
  $badgeCor = '#e11d48'; // rosa/vermelho
  $icone    = 'fa-triangle-exclamation';
}

/* WhatsApp – tenta montar a partir das variáveis do sistema, se existirem */
$waDisplay = '';
$waLink = '';
if (!empty($tel_whats)) {
  $waDisplay = $tel_whats;
  $waLink    = 'https://wa.me/' . so_digitos($tel_whats);
} elseif (!empty($telefone_sistema)) {
  $waDisplay = $telefone_sistema;
  $waLink    = 'https://wa.me/' . so_digitos($telefone_sistema);
}

/* Link do painel (se quiser deixar um atalho) */
$painelLink = rtrim($url_sistema, '/') . '/sistema/acesso';
?>
<!DOCTYPE html>
<html lang="pt-BR">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Obrigado — <?= h($nome_sistema) ?></title>
<meta name="description" content="Sistema completo para barbearias: agenda online, confirmações por WhatsApp, pagamentos, relatórios e fidelidade. Organize a equipe e aumente o faturamento.">
<meta name="keywords" content="BarberBot, sistema para barbearia, agendamento barbearia, gestão de barbearia, agenda online, software barbearia, confirmações WhatsApp, pagamentos, relatórios, fidelidade, agenda de barbeiro">
<meta name="robots" content="index,follow">
<link rel="canonical" href="https://barberbot.com.br/">
<meta name="author" content="Jacy Cordeiro">

  <link rel="shortcut icon" href="images/<?php echo $icone_site ?>" type="image/x-icon">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
  <style>
    :root{
      --bg1:#1e3a8a; --bg2:#3b82f6;
      --card:#ffffff; --text:#0f172a; --muted:#475569;
      --ring: <?= $badgeCor ?>;
    }
    *{ box-sizing:border-box }
    html,body{ height:100% }
    body{
      margin:0; font-family: "Segoe UI", Tahoma, Geneva, Verdana, sans-serif;
      background: linear-gradient(135deg,var(--bg1),var(--bg2));
      display:flex; align-items:center; justify-content:center; padding:24px;
    }
    .wrap{ width:100%; max-width:1000px }
    .card{
      background:var(--card); color:var(--text); border-radius:20px;
      padding:36px; box-shadow:0 20px 60px rgba(0,0,0,.25); animation:fadeIn .5s ease;
    }
    @keyframes fadeIn{ from{opacity:0; transform:translateY(10px)} to{opacity:1; transform:translateY(0)} }

    .head{
      display:flex; align-items:center; gap:16px; flex-wrap:wrap; margin-bottom:10px;
    }
    .badge{
      display:inline-flex; align-items:center; gap:10px; padding:8px 14px; border-radius:999px;
      border:1px solid rgba(0,0,0,.06); box-shadow:inset 0 0 0 2px var(--ring, #16a34a);
      color:var(--text); font-weight:700; background:#fff;
    }
    .badge i{ color:var(--ring, #16a34a) }
    h1{ margin:10px 0 6px; font-size:28px; line-height:1.2; font-weight:800; letter-spacing:.2px }
    .sub{
      margin:0 0 22px; color:var(--muted); line-height:1.6;
    }
    .hello{ color:#0b1220; font-weight:700 }
    .grid{
      display:grid; grid-template-columns:repeat(3,1fr); gap:18px; margin-top:24px;
    }
    .feat{
      background:#fff; border:1px solid rgba(0,0,0,.06); border-radius:14px; padding:18px;
      box-shadow:0 8px 30px rgba(2,6,23,.08); transition:transform .2s ease, box-shadow .2s ease;
    }
    .feat:hover{ transform:translateY(-3px); box-shadow:0 14px 40px rgba(2,6,23,.14) }
    .feat h3{ margin:0 0 8px; font-size:16px }
    .feat p{ margin:0; color:var(--muted) }

    .actions{ margin-top:26px; display:flex; flex-wrap:wrap; gap:12px }
    .btn{
      display:inline-flex; align-items:center; gap:10px; padding:12px 18px; border-radius:12px;
      border:1px solid rgba(0,0,0,.06); text-decoration:none; font-weight:700;
      box-shadow:0 8px 26px rgba(2,6,23,.1); transition:transform .2s ease, box-shadow .2s ease, filter .2s ease;
      color:#fff;
    }
    .btn:hover{ transform:translateY(-2px); box-shadow:0 12px 34px rgba(2,6,23,.16); filter:brightness(1.06) }
    .btn:active{ transform:translateY(0) }

    .btn-wa{ background:linear-gradient(135deg,#25D366,#128C7E) }
    .btn-site{ background:linear-gradient(135deg,#4CAF50,#2E7D32) }
    .btn-yt{ background:linear-gradient(135deg,#FF0000,#CC0000) }
    .btn-fb{ background:linear-gradient(135deg,#1877F2,#0D5FCC) }
    .btn-ig{ background:linear-gradient(135deg,#E4405F,#833AB4) }
    .btn-painel{ background:linear-gradient(135deg,#3b82f6,#1d4ed8) }

    .foot{
      margin-top:22px; padding-top:18px; border-top:1px dashed rgba(2,6,23,.12);
      display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap;
      color:var(--muted);
    }
    @media (max-width:920px){ .grid{ grid-template-columns:1fr 1fr } }
    @media (max-width:620px){ .grid{ grid-template-columns:1fr } .card{ padding:26px } }
  </style>
</head>
<body>
  <main class="wrap">
    <section class="card">

      <div class="head">
        <span class="badge"><i class="fa-solid <?= h($icone) ?>"></i> Notificação</span>
        <span class="badge" style="box-shadow:inset 0 0 0 2px #0ea5e9;"><i class="fa-solid fa-building"></i> <?= h($nome_sistema) ?></span>
      </div>

      <h1><?= h($titulo) ?><?= $nomeParam ? ' — <span class="hello">'.h($nomeParam).'</span>' : '' ?></h1>
      <p class="sub"><?= nl2br(h($descricao)) ?></p>

      <div class="grid">
        <div class="feat">
          <h3><i class="fa-solid fa-wand-magic-sparkles"></i> Facilidade de uso</h3>
          <p>Interface intuitiva e direta para o dia a dia.</p>
        </div>
        <div class="feat">
          <h3><i class="fa-solid fa-headset"></i> Suporte premium</h3>
          <p>Atendimento próximo e ágil para você não parar.</p>
        </div>
        <div class="feat">
          <h3><i class="fa-solid fa-gem"></i> Recursos exclusivos</h3>
          <p>Ferramentas que elevam o nível do seu negócio.</p>
        </div>
      </div>

      <div class="actions">
        <?php if($waLink): ?>
          <a class="btn btn-wa" href="<?= h($waLink) ?>" target="_blank" rel="noopener">
            <i class="fab fa-whatsapp"></i> WhatsApp
          </a>
        <?php endif; ?>

        <?php if(!empty($site_sistema)): ?>
          <a class="btn btn-site" href="<?= h($site_sistema) ?>" target="_blank" rel="noopener">
            <i class="fas fa-globe"></i> Nosso Site
          </a>
        <?php endif; ?>

        <?php if(!empty($youtube_sistema)): ?>
          <a class="btn btn-yt" href="<?= h($youtube_sistema) ?>" target="_blank" rel="noopener">
            <i class="fab fa-youtube"></i> YouTube
          </a>
        <?php endif; ?>

        <?php if(!empty($facebook_sistema)): ?>
          <a class="btn btn-fb" href="<?= h($facebook_sistema) ?>" target="_blank" rel="noopener">
            <i class="fab fa-facebook-f"></i> Facebook
          </a>
        <?php endif; ?>

        <?php if(!empty($instagram_sistema)): ?>
          <a class="btn btn-ig" href="<?= h($instagram_sistema) ?>" target="_blank" rel="noopener">
            <i class="fab fa-instagram"></i> Instagram
          </a>
        <?php endif; ?>

        <a class="btn btn-painel" href="<?= h($painelLink) ?>">
          <i class="fa-solid fa-right-to-bracket"></i> Acessar Painel
        </a>
      </div>

      <div class="foot">
        <div>
          © <?= date('Y') ?> <?= h($nome_sistema) ?> · Todos os direitos reservados
        </div>
        <?php if($waDisplay): ?>
          <div>
            <i class="fa-solid fa-phone"></i> <?= h($waDisplay) ?>
          </div>
        <?php endif; ?>
      </div>

    </section>
  </main>
</body>
</html>
