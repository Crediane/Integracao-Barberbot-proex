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

// Contexto da loja precisa existir ANTES do cabecalho/conexao
$__bb_slug = '';
if (!empty($_POST['loja'])) {
    $__bb_slug = preg_replace('~[^a-z0-9_-]~i', '', (string)$_POST['loja']);
} elseif (!empty($_GET['loja'])) {
    $__bb_slug = preg_replace('~[^a-z0-9_-]~i', '', (string)$_GET['loja']);
} elseif (!empty($_SESSION['tenant_slug'])) {
    $__bb_slug = preg_replace('~[^a-z0-9_-]~i', '', (string)$_SESSION['tenant_slug']);
} else {
    if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath, $mSlugCtx)) {
        $__bb_slug = preg_replace('~[^a-z0-9_-]~i', '', (string)$mSlugCtx[1]);
    }
}

if ($__bb_slug !== '') {
    $_GET['loja'] = $__bb_slug;
    $_SESSION['tenant_slug'] = $__bb_slug;
}

require_once(__DIR__ . "/cabecalho.php");
require_once __DIR__ . "/timezone_raiz.php";
if (!function_exists('bb_extrair_id_da_uri')) {
    function bb_extrair_id_da_uri(array $rotasBase = []): int {
        $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $segmentos = array_values(array_filter(explode('/', trim($path, '/')), 'strlen'));

        if (!empty($segmentos) && isset($segmentos[0]) && $segmentos[0] === 'loja') {
            $segmentos = array_slice($segmentos, 2);
        }

        foreach ($rotasBase as $rota) {
            $pos = array_search((string)$rota, $segmentos, true);
            if ($pos !== false && !empty($segmentos[$pos + 1]) && ctype_digit((string)$segmentos[$pos + 1])) {
                return (int)$segmentos[$pos + 1];
            }
        }

        for ($i = count($segmentos) - 1; $i >= 0; $i--) {
            if (ctype_digit((string)$segmentos[$i])) {
                return (int)$segmentos[$i];
            }
        }

        return 0;
    }
}

$data_atual = date('Y-m-d');

/**
 * Checkout de Assinatura (Marketplace)
 * - Mantém a lógica do sistema: pega o ID, busca em itens_assinaturas, envia para ajax/assinatura.php e redireciona /plano/{id}
 * - Marketplace: funciona em /loja/{slug}/ (tenant) e no link padrão (admin)
 * - Banco individual: NÃO ALTERA. O $pdo vem do padrão do sistema (cabecalho.php / conexao.php)
 */

// ===== Contexto (MASTER x LOJA) =====
$__bb_slug = '';
if (!empty($_POST['loja'])) {
    $__bb_slug = preg_replace('~[^a-z0-9_-]~i', '', (string)$_POST['loja']);
} elseif (!empty($_GET['loja'])) {
    $__bb_slug = preg_replace('~[^a-z0-9_-]~i', '', (string)$_GET['loja']);
} elseif (!empty($_SESSION['tenant_slug'])) {
    $__bb_slug = preg_replace('~[^a-z0-9_-]~i', '', (string)$_SESSION['tenant_slug']);
} else {
    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $path, $m)) {
        $__bb_slug = preg_replace('~[^a-z0-9_-]~i', '', (string)$m[1]);
    }
}

if ($__bb_slug !== '') {
    $_GET['loja'] = $__bb_slug;
    $_SESSION['tenant_slug'] = $__bb_slug;
}

$__bb_ctx_path = $__bb_slug !== '' ? '/loja/' . rawurlencode($__bb_slug) : '';
$__bb_url_assinatura = $__bb_ctx_path !== '' ? $__bb_ctx_path . '/assinatura/' : '/assinatura/';
$__bb_url_plano_base = $__bb_ctx_path !== '' ? $__bb_ctx_path . '/plano/' : '/plano/';
$__bb_url_ajax_listar_nome = '/ajax/listar-nome.php';
$__bb_url_ajax_assinatura = '/ajax/assinatura.php';

// ===== Lógica de ID original preservada =====
$id_item = 0;
if (isset($_POST['id_item'])) {
	$id_item = (int)$_POST['id_item'];
} elseif (isset($_GET['id_item'])) {
	$id_item = (int)$_GET['id_item'];
} elseif (isset($_GET['id'])) {
	$id_item = (int)$_GET['id'];
} else {
	$id_item = bb_extrair_id_da_uri(['assinar', 'assinatura', 'plano']);
}

// ===== Busca de dados no Banco Individual (tenant/admin) =====
$nome_item = '';
$valor     = 0;
$c = array_fill(1, 12, '');

if ($id_item > 0) {
	$query = $pdo->prepare("SELECT * FROM itens_assinaturas WHERE id = :id LIMIT 1");
	$query->bindValue(':id', $id_item, PDO::PARAM_INT);
	$query->execute();
	$res = $query->fetchAll(PDO::FETCH_ASSOC);

	if (count($res) > 0) {
		$id_item   = (int)$res[0]['id'];
		$nome_item = $res[0]['nome'] ?? '';
		$valor     = (float)($res[0]['valor'] ?? 0);
		for ($i = 1; $i <= 12; $i++) {
			$c[$i] = $res[0]["c$i"] ?? '';
		}
	}
}

$valorF = number_format($valor, 2, ',', '.');

?>

<style type="text/css">
    /* Reset e Fundo */
    .sub_page .hero_area { min-height: auto !important; }
    .main-wrapper {
        background-color: #0a0a0a;
        padding: 80px 0;
        min-height: 80vh;
        font-family: 'Inter', sans-serif;
    }

    /* Card Principal */
    .checkout-card {
        background: #161b22;
        border: 1px solid #30363d;
        border-radius: 20px;
        overflow: hidden;
        box-shadow: 0 20px 50px rgba(0,0,0,0.4);
    }

    /* Coluna de Informações do Plano */
    .plan-info-side {
        background: linear-gradient(145deg, #161b22 0%, #0d1117 100%);
        padding: 40px;
        border-right: 1px solid #30363d;
    }

    .plan-badge {
        background: rgba(0, 150, 255, 0.1);
        color: #58a6ff;
        padding: 6px 14px;
        border-radius: 50px;
        font-size: 11px;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 20px;
        display: inline-block;
    }

    .plan-price { font-size: 42px; font-weight: 800; color: #fff; margin: 15px 0; }
    .plan-price small { font-size: 16px; color: #8b949e; font-weight: 400; }

    .feature-item {
        display: flex;
        align-items: center;
        color: #c9d1d9;
        margin-bottom: 12px;
        font-size: 14px;
    }
    .feature-item i { color: #3fb950; margin-right: 12px; font-size: 12px; }

    /* Coluna de Formulário */
    .form-side { padding: 40px; background: #0d1117; }
    .form-label { color: #8b949e; font-size: 13px; font-weight: 500; margin-bottom: 8px; }

    .custom-input {
        background: #010409 !important;
        border: 1px solid #30363d !important;
        border-radius: 8px !important;
        color: #fff !important;
        padding: 12px 16px !important;
        margin-bottom: 20px !important;
        width: 100%;
        transition: all 0.3s;
    }
    .custom-input:focus {
        border-color: #58a6ff !important;
        box-shadow: 0 0 0 3px rgba(88, 166, 255, 0.1) !important;
        outline: none;
    }

    .btn-checkout {
        background: linear-gradient(180deg, #4493f8 0%, #1f6feb 100%);
        color: #fff;
        border: none;
        padding: 16px;
        border-radius: 8px;
        width: 100%;
        font-weight: 700;
        font-size: 16px;
        cursor: pointer;
        transition: transform 0.2s, filter 0.2s;
        margin-top: 10px;
    }
    .btn-checkout:hover { filter: brightness(1.1); transform: translateY(-1px); }
    .btn-checkout:active { transform: translateY(0); }

    #mensagem { font-size: 14px; font-weight: 500; margin-top: 15px; }
</style>

<div class="main-wrapper">
    <div class="container">
        <div class="row justify-content-center">
            <div class="col-xl-10 col-lg-11">

                <?php if ($id_item <= 0 || empty($nome_item)) : ?>
                    <div class="checkout-card p-5 text-center">
                        <h2 class="text-white">Plano não encontrado</h2>
                        <p class="text-muted">O plano selecionado é inválido ou não existe mais.</p>
                        <a href="<?php echo htmlspecialchars($__bb_url_assinatura, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary mt-3">Voltar aos Planos</a>
                    </div>
                <?php else : ?>

                    <div class="checkout-card">
                        <div class="row g-0">
                            <div class="col-md-5 plan-info-side">
                                <span class="plan-badge">Seleção Atual</span>
                                <h2 class="text-white mb-2"><?php echo htmlspecialchars($nome_item, ENT_QUOTES, 'UTF-8'); ?></h2>
                                <div class="plan-price">
                                    <small>R$</small><?php echo $valorF; ?><small>/mês</small>
                                </div>

                                <hr style="border-color: #30363d; margin: 25px 0;">

                                <div class="features-box">
                                    <?php for($i=1; $i<=12; $i++): if(!empty($c[$i])): ?>
                                        <div class="feature-item">
                                            <i class="fas fa-check-circle"></i>
                                            <?php echo htmlspecialchars($c[$i], ENT_QUOTES, 'UTF-8'); ?>
                                        </div>
                                    <?php endif; endfor; ?>
                                </div>
                            </div>

                            <div class="col-md-7 form-side">
                                <h4 class="text-white mb-4">Informações da Assinatura</h4>

                                <form id="form-agenda">
                                    <div class="row">
                                        <div class="col-12">
                                            <label class="form-label">WhatsApp (Telefone)</label>
                                            <input type="text" name="telefone" id="telefone" class="custom-input" placeholder="(00) 00000-0000" required onkeyup="buscarNome()">
                                        </div>
                                        <div class="col-12">
                                            <label class="form-label">Nome Completo</label>
                                            <input type="text" name="nome" id="nome" class="custom-input" placeholder="Digite seu nome" required>
                                        </div>
                                    </div>

                                    <p class="small text-muted mb-4">
                                        <i class="fas fa-lock me-1"></i> Pagamento seguro processado pelo BarberBot Software.
                                    </p>

                                    <button type="submit" id="btn_agendar" class="btn-checkout">
                                        FINALIZAR ASSINATURA
                                    </button>

                                    <div id="mensagem" class="text-center"></div>

                                    <input type="hidden" name="id" value="<?php echo (int)$id_item; ?>">
                                    <?php if (!empty($__bb_slug)) { ?>
                                        <input type="hidden" name="loja" value="<?php echo htmlspecialchars($__bb_slug, ENT_QUOTES, 'UTF-8'); ?>">
                                    <?php } ?>
                                </form>
                            </div>
                        </div>
                    </div>

                <?php endif; ?>

            </div>
        </div>
    </div>
</div>

<?php require_once(__DIR__ . "/rodape.php"); ?>

<script type="text/javascript">
    var BB_CTX_PATH = "<?php echo htmlspecialchars($__bb_ctx_path, ENT_QUOTES, 'UTF-8'); ?>";
    var BB_URL_LISTAR_NOME = "<?php echo htmlspecialchars($__bb_url_ajax_listar_nome, ENT_QUOTES, 'UTF-8'); ?>";
    var BB_URL_ASSINATURA_AJAX = "<?php echo htmlspecialchars($__bb_url_ajax_assinatura, ENT_QUOTES, 'UTF-8'); ?>";
    var BB_URL_PLANO_BASE = "<?php echo htmlspecialchars($__bb_url_plano_base, ENT_QUOTES, 'UTF-8'); ?>";

    function bbEscaparHtml(texto) {
        return String(texto || '').replace(/[&<>"']/g, function (m) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;'
            }[m];
        });
    }

    function bbExtrairRespostaAssinatura(mensagem) {
        var texto = String(mensagem || '').trim();
        if (!texto) {
            return { ok: false, mensagem: 'Resposta vazia do servidor.' };
        }

        var matchSalvo = texto.match(/Salvo\*(\d+)/i);
        if (matchSalvo && matchSalvo[1]) {
            return { ok: true, id: matchSalvo[1], bruto: texto };
        }

        var partes = texto.split('*');
        return {
            ok: false,
            mensagem: (partes[0] || texto || 'Erro ao processar').trim(),
            bruto: texto
        };
    }

    function bbMensagemErroAjax(xhr, status, errorThrown) {
        var resposta = '';
        if (xhr && typeof xhr.responseText === 'string') {
            resposta = xhr.responseText.trim();
        }

        if (resposta) {
            resposta = resposta.replace(/<[^>]*>/g, ' ').replace(/\s+/g, ' ').trim();
            if (resposta.length > 220) {
                resposta = resposta.substring(0, 220) + '...';
            }
            return resposta;
        }

        if (errorThrown) {
            return errorThrown;
        }

        if (status) {
            return 'Falha na requisição: ' + status;
        }

        return 'Não foi possível concluir a assinatura agora.';
    }

    function buscarNome(){
        var tel = $('#telefone').val();
        if(tel.length >= 10){
            $.ajax({
                url: BB_URL_LISTAR_NOME,
                method: 'POST',
                dataType: 'text',
                data: {
                    tel: tel,
                    loja: <?php echo !empty($__bb_slug) ? '"' . addslashes($__bb_slug) . '"' : '""'; ?>
                },
                success: function(result){
                    var split = String(result || '').split("*");
                    if(split[0]) {
                        $("#nome").val(split[0]);
                    }
                }
            });
        }
    }

    $("#form-agenda").submit(function (e) {
        e.preventDefault();
        var btn = $('#btn_agendar');
        var msg = $('#mensagem');
        var formulario = this;

        btn.prop('disabled', true).text('Processando...');
        msg.html('<span class="text-info">Validando dados...</span>');

        $.ajax({
            url: BB_URL_ASSINATURA_AJAX,
            type: 'POST',
            dataType: 'text',
            timeout: 45000,
            data: new FormData(formulario),
            success: function (mensagem) {
                var retorno = bbExtrairRespostaAssinatura(mensagem);

                if (retorno.ok) {
                    msg.html('<span class="text-success">Sucesso! Gerando sua fatura...</span>');
                    setTimeout(function(){
                        window.location = BB_URL_PLANO_BASE + retorno.id;
                    }, 1000);
                    return;
                }

                msg.html('<span class="text-danger">' + bbEscaparHtml(retorno.mensagem) + '</span>');
                btn.prop('disabled', false).text('FINALIZAR ASSINATURA');
            },
            error: function (xhr, status, errorThrown) {
                var erro = bbMensagemErroAjax(xhr, status, errorThrown);
                msg.html('<span class="text-danger">' + bbEscaparHtml(erro) + '</span>');
                btn.prop('disabled', false).text('FINALIZAR ASSINATURA');
            },
            cache: false,
            contentType: false,
            processData: false
        });
    });
</script>
