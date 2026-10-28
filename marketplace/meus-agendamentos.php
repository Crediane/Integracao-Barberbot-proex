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

header('Content-Type: text/html; charset=utf-8');

// --- Lógica Multi-tenant (Mantida conforme original) ---
if (!function_exists('mt_slug')) {
    function mt_slug(string $v): string {
        $v = strtolower(trim($v));
        return (string)preg_replace('/[^a-z0-9_-]/', '', $v);
    }
}

$loja = '';
if (!empty($_GET['loja'])) {
    $loja = mt_slug((string)$_GET['loja']);
} elseif (!empty($_POST['loja'])) {
    $loja = mt_slug((string)$_POST['loja']);
} elseif (!empty($_SESSION['tenant_slug'])) {
    $loja = mt_slug((string)$_SESSION['tenant_slug']);
} else {
    $uri = (string)($_SERVER['REQUEST_URI'] ?? '');
    if ($uri && preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $uri, $m)) {
        $loja = mt_slug((string)$m[1]);
    }
}

if ($loja !== '') {
    $_GET['loja'] = $loja;
    $_SESSION['tenant_slug'] = $loja;
}

// Mantém o contexto correto do TENANT nas URLs dinâmicas /loja/{slug}/ e também no padrão ?loja=.
$__bb_useTenantPath = ($loja !== '' && $__bb_lojaByPath);
$BASE = $__bb_useTenantPath ? ('/loja/' . rawurlencode($loja) . '/') : '/';
$QS_LOJA = ($loja !== '' && !$__bb_useTenantPath) ? ('?loja=' . rawurlencode($loja)) : '';
$HIDDEN_LOJA = ($loja !== '') ? '<input type="hidden" name="loja" value="' . htmlspecialchars($loja, ENT_QUOTES, 'UTF-8') . '">' : '';

$URL_ACESSO = $BASE . 'sistema/acesso' . $QS_LOJA;
$URL_EXCLUIR = $BASE . 'ajax/excluir.php' . $QS_LOJA;
$URL_LISTAR_CARTOES = $BASE . 'ajax/listar-cartoes.php' . $QS_LOJA;
$URL_AGENDAMENTOS = $BASE . 'agendamentos/' . $QS_LOJA;

$telefone = $_SESSION['telefone'] ?? '';

require_once __DIR__ . '/cabecalho.php';
require_once __DIR__ . '/timezone_raiz.php';

$data_atual = date('Y-m-d');

// --- Busca de Cliente (Proteção contra SQL Injection) ---
$id_cliente = 0;
$stmt = $pdo->prepare("SELECT id FROM clientes WHERE telefone = :telefone");
$stmt->bindValue(':telefone', $telefone);
$stmt->execute();
$res_cliente = $stmt->fetch(PDO::FETCH_ASSOC);

if ($res_cliente) {
    $id_cliente = (int)$res_cliente['id'];
}
?>

<style type="text/css">
    .sub_page .hero_area { min-height: auto; }
    .card-agendamento {
        border-radius: 10px;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        transition: transform 0.2s;
        margin-bottom: 15px;
    }
    .card-agendamento:hover { transform: translateY(-3px); }
    .btn-action { border-radius: 8px; padding: 8px 12px; }
    .status-icon { font-size: 1.1rem; vertical-align: middle; }

    /* Correção visual: a classe .footer_section no tema do site costuma forçar textos em branco.
       Aqui o fundo é claro (#f8f9fa), então garantimos contraste para exibir Profissional/Serviço. */
    .bb-meus-agendamentos,
    .bb-meus-agendamentos p,
    .bb-meus-agendamentos small,
    .bb-meus-agendamentos b,
    .bb-meus-agendamentos .list-group-item {
        color: #212529 !important;
    }
    /* Mantém as cores utilitárias do Bootstrap */
    .bb-meus-agendamentos .text-primary { color: #007bff !important; }
    .bb-meus-agendamentos .text-success { color: #28a745 !important; }
    .bb-meus-agendamentos .badge { color: #fff !important; }

    /* Modal local da página: não depende do Bootstrap JS e não cria modal-backdrop travado. */
    #modalExcluir.bb-modal-show {
        display: block !important;
        opacity: 1 !important;
        background: rgba(0, 0, 0, 0.55);
        overflow-x: hidden;
        overflow-y: auto;
    }
    #modalExcluir.bb-modal-show .modal-dialog {
        transform: none !important;
    }
    body.bb-modal-open {
        overflow: hidden;
    }
    body:not(.bb-modal-open) .modal-backdrop {
        display: none !important;
    }
</style>

<div class="footer_section bb-meus-agendamentos" style="background: #f8f9fa; padding: 40px 0;">
    <div class="container">
        <div class="footer_content">
            <?php
            if ($id_cliente > 0) {
                // Busca agendamentos ativos
                $stmt = $pdo->prepare("SELECT * FROM agendamentos WHERE cliente = :cliente AND status = 'Agendado' ORDER BY data ASC");
                $stmt->bindValue(':cliente', $id_cliente, PDO::PARAM_INT);
                $stmt->execute();
                $agendamentos = $stmt->fetchAll(PDO::FETCH_ASSOC);

                if (count($agendamentos) > 0) {
                    foreach ($agendamentos as $item) {
                        $id = (int)$item['id'];
                        $dataF = date('d/m/Y', strtotime((string)$item['data']));
                        $horaF = date('H:i', strtotime((string)$item['hora']));

                        // Busca detalhes (Funcionario, Serviço) usando Prepared Statements para segurança
                        $stmt_f = $pdo->prepare("SELECT nome FROM usuarios WHERE id = :id");
                        $stmt_f->bindValue(':id', (int)$item['funcionario'], PDO::PARAM_INT);
                        $stmt_f->execute();
                        $func = $stmt_f->fetch(PDO::FETCH_ASSOC);
                        $nome_func = $func['nome'] ?? 'Sem Usuário';

                        $stmt_s = $pdo->prepare("SELECT nome, valor FROM servicos WHERE id = :id");
                        $stmt_s->bindValue(':id', (int)$item['servico'], PDO::PARAM_INT);
                        $stmt_s->execute();
                        $serv = $stmt_s->fetch(PDO::FETCH_ASSOC);
                        $nome_serv = $serv['nome'] ?? 'Não Lançado';
                        $valor_serv = number_format((float)($serv['valor'] ?? 0), 2, ',', '.');

                        // Busca Nome Cliente para o modal de exclusão
                        $stmt_c = $pdo->prepare("SELECT nome FROM clientes WHERE id = :id");
                        $stmt_c->bindValue(':id', (int)$item['cliente'], PDO::PARAM_INT);
                        $stmt_c->execute();
                        $cli = $stmt_c->fetch(PDO::FETCH_ASSOC);
                        $nome_cliente = $cli['nome'] ?? 'Sem Cliente';
                        ?>

                        <div class="card card-agendamento list-group-item border-0 mb-3">
                            <div class="d-flex w-100 justify-content-between align-items-center">
                                <h5 class="mb-1 text-primary">
                                    <small>
                                        <i class="fa fa-calendar status-icon"></i> <strong>Data:</strong> <?= htmlspecialchars($dataF, ENT_QUOTES, 'UTF-8') ?>
                                        <i class="fa fa-clock-o text-success status-icon ml-3"></i> <strong>Horas:</strong> <?= htmlspecialchars($horaF, ENT_QUOTES, 'UTF-8') ?>
                                    </small>
                                </h5>
                                <div class="btn-group">
                                    <button type="button" onclick="editar(<?= $id ?>)" class="btn btn-outline-primary btn-action mr-2" title="Editar">
                                        <i class="fa fa-pencil"></i>
                                    </button>
                                    <button type="button"
                                            onclick='excluir(<?= json_encode((string)$id, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, <?= json_encode((string)$nome_cliente, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, <?= json_encode((string)$dataF, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, <?= json_encode((string)$horaF, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, <?= json_encode((string)$nome_serv, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>, <?= json_encode((string)$nome_func, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>)'
                                            class="btn btn-outline-danger btn-action" title="Excluir">
                                        <i class="fa fa-trash-o"></i>
                                    </button>
                                </div>
                            </div>
                            <hr class="my-2">
                            <p class="mb-1"><i class="fa fa-user-o mr-2"></i><b>Profissional:</b> <?= htmlspecialchars((string)$nome_func, ENT_QUOTES, 'UTF-8') ?></p>
                            <p class="mb-0"><i class="fa fa-scissors mr-2"></i><b>Serviço:</b> <?= htmlspecialchars((string)$nome_serv, ENT_QUOTES, 'UTF-8') ?> — <span class="badge badge-success">R$ <?= htmlspecialchars((string)$valor_serv, ENT_QUOTES, 'UTF-8') ?></span></p>
                        </div>

                    <?php }
                } else {
                    echo '<div class="alert alert-info text-center">Nenhum agendamento encontrado para o seu perfil.</div>';
                }
            } else {
                echo '<div class="alert alert-warning text-center">Cliente não identificado. Por favor, faça login.</div>';
            }
            ?>

            <div class="text-center mt-5">
                <a href="<?= htmlspecialchars($URL_ACESSO, ENT_QUOTES, 'UTF-8'); ?>" class="btn btn-primary btn-lg shadow">
                    <i class="fa fa-user-circle"></i> Área Exclusiva do Cliente
                </a>
            </div>

            <div id="listar-cartoes" class="mt-4" align="center"></div>
        </div>
    </div>
</div>

<div class="modal fade" id="modalExcluir" tabindex="-1" role="dialog" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered" role="document">
        <div class="modal-content">
            <div class="modal-header bg-danger text-white">
                <h5 class="modal-title"><i class="fa fa-warning"></i> Confirmar Exclusão</h5>
                <button type="button" class="close text-white" data-dismiss="modal" id="btn-fechar-excluir" aria-label="Fechar">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="form-excluir">
                <div class="modal-body text-center">
                    <p>Você realmente deseja cancelar este agendamento?</p>
                    <div id="msg-detalhes-exclusao" class="small text-muted mb-3"></div>

                    <input type="hidden" name="id" id="id_excluir">
                    <?= $HIDDEN_LOJA ?>
                    <input type="hidden" id="nome_excluir">
                    <input type="hidden" id="data_excluir">
                    <input type="hidden" id="hora_excluir">
                    <input type="hidden" id="servico_excluir">
                    <input type="hidden" id="func_excluir">

                    <div id="mensagem-excluir" class="alert d-none"></div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-dismiss="modal">Manter</button>
                    <button type="submit" class="btn btn-danger">Sim, Cancelar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script type="text/javascript">
(function() {
    'use strict';

    var BB_URL_EXCLUIR = <?= json_encode($URL_EXCLUIR, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    var BB_URL_LISTAR_CARTOES = <?= json_encode($URL_LISTAR_CARTOES, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    var BB_URL_AGENDAMENTOS = <?= json_encode($URL_AGENDAMENTOS, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    var BB_LOJA = <?= json_encode($loja, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;
    var BB_TELEFONE = <?= json_encode((string)$telefone, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

    function byId(id) {
        return document.getElementById(id);
    }

    function setValue(id, value) {
        var el = byId(id);
        if (el) {
            el.value = value || '';
        }
    }

    function setText(id, value) {
        var el = byId(id);
        if (el) {
            el.textContent = value || '';
        }
    }

    function setHtml(id, value) {
        var el = byId(id);
        if (el) {
            el.innerHTML = value || '';
        }
    }

    function escapeHtml(value) {
        return String(value || '').replace(/[&<>'"]/g, function(ch) {
            return {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                "'": '&#039;',
                '"': '&quot;'
            }[ch];
        });
    }

    function addTenantToFormData(formData) {
        if (BB_LOJA && !formData.has('loja')) {
            formData.append('loja', BB_LOJA);
        }
        return formData;
    }

    function showMessage(type, text) {
        var msg = byId('mensagem-excluir');
        if (!msg) return;

        msg.className = 'alert alert-' + type;
        msg.textContent = text || '';
    }

    function hideMessage() {
        var msg = byId('mensagem-excluir');
        if (!msg) return;

        msg.className = 'alert d-none';
        msg.textContent = '';
    }

    function limparBackdropsBootstrap() {
        var backdrops = document.querySelectorAll('.modal-backdrop');
        for (var i = 0; i < backdrops.length; i++) {
            if (backdrops[i] && backdrops[i].parentNode) {
                backdrops[i].parentNode.removeChild(backdrops[i]);
            }
        }
    }

    function showModalExcluir() {
        var modal = byId('modalExcluir');
        if (!modal) return;

        limparBackdropsBootstrap();

        modal.classList.add('show', 'bb-modal-show');
        modal.style.display = 'block';
        modal.removeAttribute('aria-hidden');
        modal.setAttribute('aria-modal', 'true');
        document.body.classList.add('modal-open', 'bb-modal-open');
    }

    function hideModalExcluir() {
        var modal = byId('modalExcluir');
        if (!modal) return;

        modal.classList.remove('show', 'bb-modal-show');
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        modal.removeAttribute('aria-modal');
        document.body.classList.remove('modal-open', 'bb-modal-open');
        limparBackdropsBootstrap();
    }

    function postForm(url, formData) {
        return fetch(url, {
            method: 'POST',
            body: formData,
            credentials: 'same-origin',
            headers: {
                'X-Requested-With': 'XMLHttpRequest'
            }
        }).then(function(response) {
            return response.text();
        });
    }

    window.editar = function(id) {
        if (!id) {
            alert('ID inválido!');
            return;
        }

        if (confirm('Deseja editar este agendamento? \n\nO horário atual será cancelado para que você escolha um novo.')) {
            var formData = new FormData();
            formData.append('id', id);
            formData.append('acao', 'editar');
            addTenantToFormData(formData);

            postForm(BB_URL_EXCLUIR, formData)
                .then(function() {
                    window.location.href = BB_URL_AGENDAMENTOS;
                })
                .catch(function() {
                    alert('Erro ao processar solicitação.');
                });
        }
    };

    window.excluir = function(id, nome, data, hora, servico, func) {
        if (!id) {
            alert('ID inválido!');
            return;
        }

        setValue('id_excluir', id);
        setValue('nome_excluir', nome);
        setValue('data_excluir', data);
        setValue('hora_excluir', hora);
        setValue('servico_excluir', servico);
        setValue('func_excluir', func);
        hideMessage();

        setHtml(
            'msg-detalhes-exclusao',
            '<strong>' + escapeHtml(servico) + '</strong> com <strong>' + escapeHtml(func) + '</strong> em ' + escapeHtml(data) + ' às ' + escapeHtml(hora)
        );

        showModalExcluir();
    };

    window.listarCartoes = function(tel) {
        var box = byId('listar-cartoes');
        if (!box || !tel) return;

        var formData = new FormData();
        formData.append('tel', tel);
        addTenantToFormData(formData);

        postForm(BB_URL_LISTAR_CARTOES, formData)
            .then(function(result) {
                box.innerHTML = result;
            })
            .catch(function() {
                box.innerHTML = '';
            });
    };

    function iniciarPagina() {
        limparBackdropsBootstrap();
        hideModalExcluir();

        if (BB_TELEFONE) {
            window.listarCartoes(BB_TELEFONE);
        }

        var formExcluir = byId('form-excluir');
        if (formExcluir) {
            formExcluir.addEventListener('submit', function(e) {
                e.preventDefault();

                var btnSubmit = formExcluir.querySelector('button[type="submit"]');
                if (btnSubmit) {
                    btnSubmit.disabled = true;
                    btnSubmit.textContent = 'Cancelando...';
                }

                hideMessage();

                var formData = addTenantToFormData(new FormData(formExcluir));

                postForm(BB_URL_EXCLUIR, formData)
                    .then(function(mensagem) {
                        mensagem = (mensagem || '').trim();

                        if (mensagem === 'Cancelado com Sucesso') {
                            window.location.href = BB_URL_AGENDAMENTOS;
                            return;
                        }

                        showMessage('danger', mensagem || 'Erro ao cancelar agendamento.');
                        if (btnSubmit) {
                            btnSubmit.disabled = false;
                            btnSubmit.textContent = 'Sim, Cancelar';
                        }
                    })
                    .catch(function() {
                        showMessage('danger', 'Erro ao cancelar agendamento. Tente novamente.');
                        if (btnSubmit) {
                            btnSubmit.disabled = false;
                            btnSubmit.textContent = 'Sim, Cancelar';
                        }
                    });
            });
        }

        var modalExcluir = byId('modalExcluir');
        if (modalExcluir) {
            var dismissButtons = modalExcluir.querySelectorAll('[data-dismiss="modal"]');
            for (var i = 0; i < dismissButtons.length; i++) {
                dismissButtons[i].addEventListener('click', function(e) {
                    e.preventDefault();
                    hideModalExcluir();
                });
            }

            modalExcluir.addEventListener('click', function(e) {
                if (e.target === modalExcluir) {
                    hideModalExcluir();
                }
            });
        }

        document.addEventListener('keyup', function(e) {
            if (e.key === 'Escape') {
                hideModalExcluir();
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', iniciarPagina, { once: true });
    } else {
        iniciarPagina();
    }
})();
</script>

<?php require_once("rodape.php") ?>
