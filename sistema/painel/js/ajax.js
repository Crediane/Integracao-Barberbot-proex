// BarberBot - ajax.js (padrão do sistema, sem mudar a lógica)
// Mantém a lógica original do painel. Ajustes feitos APENAS para:
// 1) Preservar contexto multi-tenant (não perder /loja/{slug}/painel/ nas chamadas AJAX/API)
// 2) Evitar recarregamento por links vazios (# / href="")
// 3) Garantir envio do campo #id mesmo se estiver fora do <form> (compatibilidade em edição)

// ======================================================
// CONTEXTO MULTI-TENANT (MARKETPLACE)
// - Admin (dono do sistema): /sistema/painel/...
// - Dono da barbearia (tenant): /loja/{slug}/painel/...
// ======================================================

function bb_cache_loja_slug(slug) {
    slug = (slug || '').toString().trim();
    if (!slug) return '';
    // segurança: somente caracteres permitidos em slug
    slug = slug.toLowerCase().replace(/[^a-z0-9_-]/g, '');
    if (!slug) return '';
    try {
        // sessionStorage (preferível) e localStorage (fallback)
        if (window.sessionStorage) sessionStorage.setItem('bb_loja_slug', slug);
        if (window.localStorage) localStorage.setItem('bb_loja_slug', slug);
    } catch (e) {}
    return slug;
}

function bb_get_loja_slug() {
    // prioridade: variável global (se algum layout já setar)
    if (typeof window.BB_LOJA !== 'undefined' && window.BB_LOJA) {
        return bb_cache_loja_slug(String(window.BB_LOJA));
    }

    // 1) extrai do path atual (/loja/{slug}/...)
    var path = (window.location && window.location.pathname) ? window.location.pathname : '';
    var m = path.match(/(?:^|\/)loja\/([^\/]+)(?:\/|$)/i);
    if (m && m[1]) return bb_cache_loja_slug(m[1]);

    // 2) fallback: slug cacheado (quando algum link/AJAX caiu sem /loja/{slug}/ na URL)
    try {
        var s = '';
        if (window.sessionStorage) s = sessionStorage.getItem('bb_loja_slug') || '';
        if (!s && window.localStorage) s = localStorage.getItem('bb_loja_slug') || '';
        s = (s || '').toString().trim();
        if (s) return bb_cache_loja_slug(s);
    } catch (e) {}

    return '';
}

function bb_get_base_painel() {
    // prioridade: variável global (se algum layout já setar)
    if (typeof window.bbBasePainel !== 'undefined' && window.bbBasePainel) return String(window.bbBasePainel);

    var path = (window.location && window.location.pathname) ? window.location.pathname : '';

    // tenant (padrão marketplace): /loja/{slug}/painel
    var m = path.match(/^(.*?)\/loja\/([^\/]+)\/painel(?:\/|$)/i);
    if (m && m[2]) return m[1] + '/loja/' + bb_cache_loja_slug(m[2]) + '/painel';

    // fallback: se estiver em /loja/{slug}/... (mesmo fora do /painel), mantém base tenant
    var slug = bb_get_loja_slug();
    if (slug && path.match(/(?:^|\/)loja\//i)) {
        var tenantMatch = path.match(/^(.*?)\/loja\//i);
        var tenantPrefix = tenantMatch ? tenantMatch[1] : '';
        return tenantPrefix + '/loja/' + slug + '/painel';
    }

    // admin (master): preserva a subpasta do projeto no XAMPP.
    // Exemplos: /sistema/painel ou /marketplace/sistema/painel.
    var adminMatch = path.match(/^(.*?)\/sistema\/painel(?:\/|$)/i);
    var adminPrefix = adminMatch ? adminMatch[1] : '';
    return adminPrefix + '/sistema/painel';
}

// expõe base do painel (usado por módulos)
window.bbBasePainel = bb_get_base_painel();

function bb_merge_loja(obj) {
    if (!obj) obj = {};
    try {
        // Alguns endpoints podem usar POST e não ter slug no path
        // (quando chamados de fora do contexto /loja/{slug}/...).
        if (!Object.prototype.hasOwnProperty.call(obj, 'loja')) {
            var s = bb_get_loja_slug();
            if (s) obj['loja'] = s;
        }
    } catch (e) {}
    return obj;
}

// Normaliza URL para sempre cair no painel correto (admin ou loja)
// - Se a URL já for absoluta (http/https) -> retorna como está
// - Se começar com "/" -> assume que já é absoluta no domínio -> retorna como está
// - Se for relativa (ex: "paginas/x/listar.php" ou "apis/..") -> prefixa com window.bbBasePainel
function bb_url(url) {
    url = (url || '').toString();

    if (url === '') return url;

    // absoluta
    if (/^https?:\/\//i.test(url)) return url;
    if (url.charAt(0) === '/') return url;

    // garante "base painel"
    var base = bb_get_base_painel();

    // remove "./" se existir
    url = url.replace(/^\.\//, '');

    // evita duplicar base
    if (url.indexOf('sistema/painel') === 0 || url.indexOf('loja/') === 0) {
        return '/' + url.replace(/^\/+/, '');
    }

    return base.replace(/\/+$/, '') + '/' + url.replace(/^\/+/, '');
}

// ============================
// FIX GLOBAL
// - Evita que <a href=""> ou <a href="#"> recarregue a página
// - NÃO muda lógica: apenas bloqueia navegação padrão do navegador
// ============================
(function bb_bloquear_links_vazios() {
    if (window.__bb_links_vazios_iniciado) return;
    window.__bb_links_vazios_iniciado = true;

    document.addEventListener('click', function (event) {
        var el = event.target;
        if (!el) return;

        // acha o <a> mais próximo (sem depender de closest)
        while (el && el !== document && !(el.tagName && el.tagName.toLowerCase() === 'a')) {
            el = el.parentNode;
        }
        if (!el || !el.tagName || el.tagName.toLowerCase() !== 'a') return;

        var href = (el.getAttribute('href') || '').trim();

        // 1) href vazio ou "#": impede reload/rota, mas deixa onclick rodar normalmente
        if (href === '' || href === '#') {
            event.preventDefault();
            return;
        }

        // 2) compatibilidade: alguns arquivos já usam javascript:void(0)
        if (/^javascript:\s*void\(0\)\s*;?$/i.test(href)) {
            event.preventDefault();
            return;
        }
    }, false);
})();

$(document).ready(function () {
    // carrega listagem inicial
    if (typeof listar === 'function') {
        listar();
    }

    // handler padrão do formulário (namespaced para não quebrar outros scripts)
    $(document).off('submit.bb', '#form').on('submit.bb', '#form', function (event) {
        event.preventDefault();

        var formData = new FormData(this);

        // ✅ Ajuste mínimo: se o input #id existir mas estiver fora do form, envia também
        if (!formData.has('id') && $('#id').length) {
            formData.append('id', $('#id').val());
        }

        $.ajax({
            url: bb_url("paginas/" + pag + "/salvar.php"),
            type: 'POST',
            data: formData,
            cache: false,
            contentType: false,
            processData: false,
            success: function (mensagem) {
                $('#mensagem').text('').removeClass();

                if ($.trim(mensagem) === "Salvo com Sucesso") {
                    // fecha modal pelo botão se existir (padrão), senão fecha o modal direto
                    if ($('#btn-fechar').length) {
                        $('#btn-fechar').click();
                    } else if ($('#modalForm').length) {
                        $('#modalForm').modal('hide');
                    }

                    if (typeof listar === 'function') {
                        listar();
                    }
                } else {
                    $('#mensagem').addClass('text-danger').text(mensagem);
                }
            },
            error: function (xhr) {
                var msg = (xhr && xhr.responseText) ? xhr.responseText : 'Erro ao enviar os dados.';
                $('#mensagem').addClass('text-danger').text(msg);
            }
        });
    });
});

function listar(p1, p2, p3, p4, p5, p6) {
    // padrão do sistema: enviar dados do #form (quando existir)
    var data = ($('#form').length) ? $('#form').serialize() : '';

    // compatibilidade: alguns módulos chamam listar(p1..p6)
    if (arguments.length > 0) {
        function add(k, v) {
            if (typeof v === 'undefined' || v === null) v = '';
            return (data ? '&' : '') + encodeURIComponent(k) + '=' + encodeURIComponent(v);
        }
        data += add('p1', p1);
        data += add('p2', p2);
        data += add('p3', p3);
        data += add('p4', p4);
        data += add('p5', p5);
        data += add('p6', p6);
    }

    $.ajax({
        url: bb_url('paginas/' + pag + "/listar.php"),
        method: 'POST',
        data: data,
        dataType: "html",
        success: function (result) {
            $("#listar").html(result);
            if ($('#mensagem-excluir').length) {
                $('#mensagem-excluir').text('').removeClass('text-danger');
            }
            // se existir função de init de tabela do módulo, chama (não altera a lógica)
            if (typeof bb_init_datatable_planos === 'function') {
                try { bb_init_datatable_planos(); } catch (e) {}
            }
        },
        error: function (xhr) {
            var msg = (xhr && xhr.responseText) ? xhr.responseText : 'Falha ao carregar a lista.';
            $("#listar").html('<div class="alert alert-danger">' + msg + '</div>');
        }
    });
}

function excluir(id) {
    var elMsg = document.getElementById('mensagem-excluir');
    if (elMsg) elMsg.innerText = '';

    // garante ID numérico (evita enviar vazio/undefined e retornar "ID inválido")
    id = parseInt(id, 10);
    if (!id || isNaN(id) || id <= 0) {
        if (typeof $('#mensagem-excluir') !== 'undefined' && $('#mensagem-excluir').length) {
            $('#mensagem-excluir').addClass('text-danger').text('ID inválido!');
        } else {
            alert('ID inválido!');
        }
        return;
    }

    $.ajax({
        url: bb_url('paginas/' + pag + "/excluir.php"),
        method: 'POST',
        data: bb_merge_loja({ id: id }),
        dataType: "text",
        success: function (mensagem) {
            if ($.trim(mensagem) === "Excluído com Sucesso") {
                listar();
            } else {
                if ($('#mensagem-excluir').length) {
                    $('#mensagem-excluir').addClass('text-danger').text(mensagem);
                } else {
                    alert(mensagem);
                }
            }
        },
        error: function (xhr) {
            var msg = (xhr && xhr.responseText) ? xhr.responseText : 'Erro ao excluir.';
            if ($('#mensagem-excluir').length) {
                $('#mensagem-excluir').addClass('text-danger').text(msg);
            } else {
                alert(msg);
            }
        }
    });
}

function ativar(id, acao) {
    $.ajax({
        url: bb_url('paginas/' + pag + "/mudar-status.php"),
        method: 'POST',
        data: bb_merge_loja({ id: id, acao: acao }),
        dataType: "text",
        success: function (mensagem) {
            if ($.trim(mensagem) === "Alterado com Sucesso") {
                listar();
            } else {
                if ($('#mensagem-excluir').length) {
                    $('#mensagem-excluir').addClass('text-danger').text(mensagem);
                }
            }
        }
    });
}

function inserir() {
    $('#mensagem').text('').removeClass();
    $('#titulo_inserir').text('Inserir Registro');
    $('#modalForm').modal('show');
    if (typeof limparCampos === 'function') {
        limparCampos();
    }
}

function inserirW() {
    $('#mensagem').text('').removeClass();
    $('#modalForm').modal('show');
}

function conectar() {
    $('#mensagem').text('').removeClass();
    $('#conectar').modal('show');
}

function mascara_valor(valor) {
    var valorAlterado = $('#' + valor).val();
    valorAlterado = valorAlterado.replace(/\D/g, "");
    valorAlterado = valorAlterado.replace(/(\d+)(\d{2})$/, "$1,$2");
    valorAlterado = valorAlterado.replace(/(\d)(?=(\d{3})+(?!\d))/g, "$1.");
    $('#' + valor).val(valorAlterado);
}

// Envio de WhatsApp (mantido)
function enviarWhats() {
    let mensagem = document.getElementById('mensagem_whatsapp') ? document.getElementById('mensagem_whatsapp').value : '';
    let id = document.getElementById('id_whats') ? document.getElementById('id_whats').value : '';
    let tipo = document.getElementById('tipo_whats') ? document.getElementById('tipo_whats').value : '';

    if (mensagem.trim() === '') {
        if (typeof alertWarning === 'function') alertWarning('Por favor, insira uma mensagem para enviar!');
        else alert('Por favor, insira uma mensagem para enviar!');
        return;
    }

    $('#btn_enviar_whats').hide();
    $('#btn_carregando_whats').show();

    $.ajax({
        url: bb_url('apis/enviar_whatsapp.php'),
        method: 'POST',
        data: bb_merge_loja({ id: id, mensagem: mensagem, tipo: tipo }),
        success: function (response) {
            $('#btn_enviar_whats').show();
            $('#btn_carregando_whats').hide();

            if (response === 'Mensagem enviada com sucesso!') {
                if (typeof alertSucesso === 'function') alertSucesso(response);
                else alert(response);
                $('#mensagem_whatsapp').val('');
            } else {
                if (typeof alertErro === 'function') alertErro(response);
                else alert(response || 'Erro ao enviar mensagem.');
            }
        },
        error: function (xhr, status, error) {
            $('#btn_enviar_whats').show();
            $('#btn_carregando_whats').hide();
            var msg = 'Erro ao enviar mensagem: ' + (error || (xhr && xhr.responseText) || 'Desconhecido');
            if (typeof alertErro === 'function') alertErro(msg);
            else alert(msg);
        }
    });
}

// ===============================
// PLANOS: FUNÇÕES GLOBAIS
// (listar.php vem por AJAX e <script> pode não executar)
// ===============================

// Toggle Ativo (planos) -> atualiza assinaturas.ativo (Sim/Não)
function toggleAtivo(id, novo) {
    $.ajax({
        url: bb_url('paginas/planos/status.php'),
        method: 'POST',
        data: bb_merge_loja({ id: id, ativo: novo }),
        dataType: "text",
        success: function (resp) {
            if (resp.trim() === 'OK') {
                if (typeof listar === 'function') listar();
            } else {
                $('#mensagem-excluir').addClass('text-danger').text(resp);
            }
        },
        error: function (xhr) {
            $('#mensagem-excluir').addClass('text-danger')
                .text('Erro ao atualizar status: ' + (xhr.responseText || xhr.status));
        }
    });
}

// Cancelar (planos)
function cancelar(id, id_plano) {
    $.ajax({
        url: bb_url('paginas/planos/cancelar.php'),
        method: 'POST',
        data: bb_merge_loja({ id: id, id_plano: id_plano }),
        dataType: "text",
        success: function (mensagem) {
            if (mensagem.trim() == "Excluído com Sucesso") {
                if (typeof listar === 'function') listar();
            } else {
                $('#mensagem-excluir').addClass('text-danger').text(mensagem);
            }
        }
    });
}

// Editar (planos)
function editar(id, cliente, grupo, item, valor, frequencia, vencimento) {
    $('#id').val(id);
    $('#cliente').val(cliente).change();
    $('#grupo').val(grupo).change();
    $('#frequencia').val(frequencia).change();
    $('#vencimento').val(vencimento);

    if (typeof listarPlanos === 'function') {
        setTimeout(function () { listarPlanos(); }, 200);
    }

    setTimeout(function () { $('#item').val(item).change(); }, 700);
    setTimeout(function () { $('#valor').val(valor); }, 900);

    $('#titulo_inserir').text('Editar Registro');
    $('#modalForm').modal('show');
}

// Baixar (planos)
function baixar(id, valor) {
    $('#id_baixar').val(id);
    $('#valor_baixar').val(valor);
    $('#modalBaixar').modal('show');
}

// DataTable após listar (evita perder tabela quando recarrega)
function bb_init_datatable_planos() {
    if (typeof $.fn.DataTable !== 'function') return;
    if (!document.getElementById('tabela')) return;

    if ($.fn.DataTable.isDataTable('#tabela')) {
        $('#tabela').DataTable().destroy();
    }
    $('#tabela').DataTable({
        ordering: false,
        stateSave: true
    });
    $('#tabela_filter label input').focus();
}
