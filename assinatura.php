<?php
// ===== Sessão isolada por contexto (MASTER x TENANT) =====
// Evita que o cookie padrão (PHPSESSID) misture MASTER x TENANT.
// Regra:
// - /sistema (sem /loja/ e sem ?loja=) => BB_MASTER
// - /loja/<slug>/... OU ?loja=...      => sessão TENANT isolada por SLUG (BB_TENANT_<hash>)
// - demais                              => BB_SITE
$__bb_reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

if (!function_exists('bb_sess_clean_slug')) {
  function bb_sess_clean_slug($v) {
    $v = strtolower(trim((string)$v));
    return preg_replace('/[^a-z0-9_-]/', '', $v);
  }
}

$__bb_reqSlug = '';
if (!empty($_GET['loja'])) {
  $__bb_reqSlug = bb_sess_clean_slug($_GET['loja']);
} elseif (!empty($_POST['loja'])) {
  $__bb_reqSlug = bb_sess_clean_slug($_POST['loja']);
} else {
  if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath, $mSlug)) {
    $__bb_reqSlug = bb_sess_clean_slug($mSlug[1]);
  }
}

$__bb_lojaByPath = (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath);

$__bb_hasLoja = $__bb_lojaByPath || !empty($_GET['loja']) || !empty($_POST['loja']);
$__bb_isSistema = (strpos($__bb_reqPath, '/sistema') === 0);

// Força MASTER em /sistema quando NÃO é loja por PATH e NÃO tem ?loja=
$__bb_forceMaster = ($__bb_isSistema && !$__bb_lojaByPath && empty($_GET['loja']) && empty($_POST['loja']));

// Sessão TENANT isolada por SLUG (cookie diferente por loja)
$__bb_tenantSessionName = 'BB_TENANT';
if ($__bb_reqSlug !== '') {
  $__bb_tenantSessionName = 'BB_TENANT_' . strtoupper(substr(sha1($__bb_reqSlug), 0, 12));
}

$__bb_desiredSession = $__bb_forceMaster ? 'BB_MASTER' : ($__bb_hasLoja ? $__bb_tenantSessionName : 'BB_SITE');

// Inicia/ajusta a sessão corretamente (usa o cookie do nome escolhido)
if (session_status() === PHP_SESSION_ACTIVE) {
  if (session_name() !== $__bb_desiredSession) {
    session_write_close();
    @session_name($__bb_desiredSession);
    if (!empty($_COOKIE[$__bb_desiredSession])) {
      @session_id((string)$_COOKIE[$__bb_desiredSession]);
    }
    @session_start();
  }
} else {
  @session_name($__bb_desiredSession);
  if (!empty($_COOKIE[$__bb_desiredSession])) {
    @session_id((string)$_COOKIE[$__bb_desiredSession]);
  }
  @session_start();
}

header('Content-Type: text/html; charset=UTF-8');

if (!function_exists('mt_slug')) {
    function mt_slug(string $v): string {
        return (string)preg_replace('/[^a-z0-9_-]/', '', strtolower(trim($v)));
    }
}

$loja = '';
if (!empty($_GET['loja'])) {
    $loja = mt_slug((string)$_GET['loja']);
} elseif (!empty($_SESSION['tenant_slug'])) {
    $loja = mt_slug((string)$_SESSION['tenant_slug']);
} else {
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    if ($uri && preg_match('~^/loja/([a-z0-9_-]+)/~i', $uri, $m)) {
        $loja = mt_slug((string)$m[1]);
    }
}

if ($loja !== '') {
    $_GET['loja'] = $loja;
    $_SESSION['tenant_slug'] = $loja;
}

require_once __DIR__ . '/cabecalho.php';

if (!function_exists('h')) {
    function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
}

$assets_base = '/';
$mapa = $mapa ?? '';
$tenant_base = ($loja !== '') ? ('/loja/' . rawurlencode($loja)) : '';
$url_assinatura_ctx = ($tenant_base !== '') ? ($tenant_base . '/assinatura/') : '/assinatura/';
$url_assinar_ctx = ($tenant_base !== '') ? ($tenant_base . '/assinar/') : '/assinar/';
?>

<style>
    /* Design System do Plano de Assinaturas */
    :root {
        --accent-color: #0d6efd;
        --dark-bg: #020202;
        --card-bg: #111111;
        --text-light: #f5f5f5;
        --text-muted: #a0a0a0;
    }

    .pricing-header { padding: 60px 0 40px; }
    .section-title { font-size: 2rem; font-weight: 700; color: #fff; margin-bottom: 20px; }
    .intro-text p { max-width: 800px; margin: 0 auto; color: var(--text-muted); line-height: 1.6; }

    .service-item { 
        transition: transform 0.3s ease; 
        border-radius: 15px; 
        overflow: hidden;
        background: var(--card-bg);
    }
    .service-item:hover { transform: translateY(-10px); }

    .pricing--item {
        background: #161616;
        padding: 30px;
        border-radius: 12px;
        height: 100%;
        display: flex;
        flex-direction: column;
        border: 1px solid #222;
    }
    .pricing--item .header { border-bottom: 1px solid #333; margin-bottom: 20px; padding-bottom: 20px; }
    .pricing--item .price { font-size: 1.5rem; margin-top: 10px; }
    .pricing--item .features { flex-grow: 1; }
    .pricing--item .features ul li { margin-bottom: 10px; font-size: 0.95rem; display: flex; align-items: center; }
    .pricing--item .features ul li::before { content: "✓"; color: var(--accent-color); margin-right: 10px; font-weight: bold; }

    .btn-assinar {
        width: 100%;
        background: var(--accent-color);
        color: #fff;
        border: none;
        padding: 12px;
        border-radius: 8px;
        font-weight: 600;
        transition: opacity 0.3s;
    }
    .btn-assinar:hover { opacity: 0.9; color: #fff; }

    /* Estilização do Formulário */
    .form_container input, .form_container select {
        width: 100%;
        background: #1a1a1a !important;
        color: #fff !important;
        border: 1px solid #333 !important;
        padding: 12px 15px !important;
        border-radius: 8px !important;
        margin-bottom: 15px;
    }
    .visually-hidden { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); border: 0; }
</style>

<div class="footer_section" style="background: var(--dark-bg);">
    <section id="pricing" class="pricing section-padding">
        <div class="container">
            <div class="row">
                <div class="col-sm-12 text-center pricing-header">
                    <h1 class="section-title">
                        ESCOLHA UM PLANO E FAÇA PARTE DA FAMÍLIA<br>
                        <span style="color: var(--accent-color); font-size: 1.2rem;">✂️ Estilo e Cuidado em cada detalhe</span>
                    </h1>
                    <div class="intro-text">
                        <p>
                            Na <strong><?php echo h($nome_sistema ?? 'Nossa Barbearia'); ?></strong>, oferecemos uma experiência premium por assinatura. 
                            Tratamentos capilares, barba, sobrancelha e muito mais, com a conveniência que você merece.
                        </p>
                    </div>
                </div>
            </div>

            <div class="row mt-4">
                <?php 
                $planos_fixos = [
                    ['img' => 'planos-plata.png', 'link' => '2c93808487ad3deb0187aecbe3a6007e', 'label' => 'Plano Prata'],
                    ['img' => 'planos-ouro.png', 'link' => '2c93808487ad3deb0187aecdd059007f', 'label' => 'Plano Ouro'],
                    ['img' => 'planos-diamante.png', 'link' => '2c93808487ad3deb0187aecef6960081', 'label' => 'Plano Diamante']
                ];
                foreach ($planos_fixos as $pf): ?>
                <div class="col-lg-4 col-md-6 mb-4">
                    <div class="service-item">
                        <a href="https://www.mercadopago.com.br/subscriptions/checkout?preapproval_plan_id=<?php echo $pf['link']; ?>" target="_blank" rel="noopener">
                            <img src="<?php echo $assets_base; ?>images/home/<?php echo $pf['img']; ?>" alt="<?php echo $pf['label']; ?>" class="img-fluid" loading="lazy">
                        </a>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>

            <?php
            $query5 = $pdo->query("SELECT * FROM grupo_assinaturas WHERE ativo = 'Sim' ORDER BY id ASC");
            $grupos = $query5->fetchAll(PDO::FETCH_ASSOC);

            foreach ($grupos as $grupo):
                $id_grupo = $grupo['id'];
                $query22 = $pdo->query("SELECT * FROM itens_assinaturas WHERE grupo = '$id_grupo' AND ativo = 'Sim'");
                $itens = $query22->fetchAll(PDO::FETCH_ASSOC);
                $total_itens = count($itens);

                if ($total_itens > 0):
                    $col_class = ($total_itens == 2) ? 'col-md-6' : (($total_itens == 3) ? 'col-md-4' : 'col-md-3');
            ?>
                <div class="row mt-5">
                    <div class="col-12 text-center mb-4">
                        <h2 class="section-title" style="font-size: 1.8rem; border-bottom: 2px solid var(--accent-color); display: inline-block; padding-bottom: 10px;">
                            <?php echo h($grupo['nome']); ?>
                        </h2>
                    </div>
                    <?php foreach ($itens as $item): ?>
                    <div class="<?php echo $col_class; ?> col-sm-6 mb-4">
                        <div class="pricing--item">
                            <div class="header">
                                <h3 class="h5 text-white"><?php echo h($item['nome']); ?></h3>
                                <p class="price text-white">R$ <strong><?php echo number_format($item['valor'], 2, ',', '.'); ?></strong><small>/mês</small></p>
                            </div>
                            <div class="features">
                                <ul class="list-unstyled">
                                    <?php for($n=1; $n<=12; $n++) { 
                                        $campo = "c$n";
                                        if(!empty($item[$campo])) echo "<li>".h($item[$campo])."</li>";
                                    } ?>
                                </ul>
                            </div>
                            <div class="action mt-4">
                                <form action="<?php echo h($url_assinar_ctx); ?>" method="POST" target="_blank">
                                    <input type="hidden" name="id_item" value="<?php echo (int)$item['id']; ?>">
                                    <?php if ($loja !== '') { ?>
                                    <input type="hidden" name="loja" value="<?php echo h($loja); ?>">
                                    <?php } ?>
                                    <button type="submit" class="btn-assinar">ASSINAR AGORA</button>
                                </form>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; endforeach; ?>
        </div>
    </section>

    <section class="contact_section layout_padding-bottom" style="background:var(--dark-bg); padding:60px 0;">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-6 mb-5 mb-md-0">
                    <div class="form_container">
                        <h2 class="text-white mb-4">Pronto para transformar seu visual?</h2>
                        <form id="form-email">
                            <?php if ($loja !== '') { ?>
                            <input type="hidden" name="loja" value="<?php echo h($loja); ?>">
                            <?php } ?>
                            <div class="row">
                                <div class="col-md-6">
                                    <label class="visually-hidden">Nome</label>
                                    <input type="text" name="nome" placeholder="Seu Nome" required>
                                </div>
                                <div class="col-md-6">
                                    <label class="visually-hidden">Telefone</label>
                                    <input type="tel" name="telefone" id="telefone" placeholder="Seu Telefone" required>
                                </div>
                                <div class="col-12">
                                    <label class="visually-hidden">Email</label>
                                    <input type="email" name="email" placeholder="Seu Email" required>
                                </div>
                                <div class="col-12">
                                    <select name="profissional" required>
                                        <option value="" disabled selected>Escolha um Profissional</option>
                                        <?php
                                        $st = $pdo->query("SELECT id, nome FROM usuarios WHERE ativo = 'Sim' AND (nivel <> 'Administrador') ORDER BY nome");
                                        while($p = $st->fetch(PDO::FETCH_ASSOC)) {
                                            echo "<option value='".(int)$p['id']."'>".h($p['nome'])."</option>";
                                        }
                                        ?>
                                    </select>
                                </div>
                                <div class="col-12">
                                    <textarea name="mensagem" placeholder="Como podemos ajudar?" style="width:100%; background:#1a1a1a; color:#fff; border:1px solid #333; border-radius:8px; padding:12px; height:100px; margin-bottom:15px;"></textarea>
                                </div>
                                <div class="col-12">
                                    <button type="submit" class="btn-assinar" style="height: 50px;">ENVIAR MENSAGEM</button>
                                </div>
                            </div>
                        </form>
                        <div id="mensagem" class="mt-3 text-center"></div>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="map_container" style="border-radius:15px; overflow:hidden; border: 1px solid #333; min-height:350px;">
                        <?php echo $mapa; ?>
                    </div>
                </div>
            </div>
        </div>
    </section>
</div>

<?php require_once __DIR__ . '/rodape.php'; ?>

<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js" defer></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Esconder botão de edição se existir
    const btnEdit = document.getElementById("botao_editar");
    if(btnEdit) btnEdit.style.display = "none";

    // Otimização do Mapa
    const iframe = document.querySelector('.map_container iframe');
    if (iframe) {
        iframe.setAttribute('width', '100%');
        iframe.setAttribute('height', '350');
        iframe.setAttribute('loading', 'lazy');
        iframe.style.border = '0';
    }

    // Ajax Form
    $('#form-email').on('submit', function(e) {
        e.preventDefault();
        const msgEl = $('#mensagem');
        msgEl.html('<span class="text-white">Processando...</span>');

        $.ajax({
            url: '<?php echo $assets_base; ?>ajax/enviar-email.php',
            type: 'POST',
            data: new FormData(this),
            contentType: false,
            processData: false,
            success: function(res) {
                if (res.trim() === 'Enviado com Sucesso') {
                    msgEl.html('<span class="text-success font-weight-bold">✅ Mensagem enviada com sucesso!</span>');
                    $('#form-email')[0].reset();
                } else {
                    msgEl.html('<span class="text-danger">' + res + '</span>');
                }
            },
            error: function() {
                msgEl.html('<span class="text-danger">Erro de conexão.</span>');
            }
        });
    });
});
</script>