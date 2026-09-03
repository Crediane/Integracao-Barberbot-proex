(function bbIniciarMascaras(tentativas) {
    'use strict';

    tentativas = tentativas || 0;

    if (!window.jQuery) {
        if (tentativas < 80) {
            setTimeout(function () {
                bbIniciarMascaras(tentativas + 1);
            }, 50);
        }
        return;
    }

    var $ = window.jQuery;

    $(document).ready(function () {

    function bbTemControladorLocalClientes() {
        return typeof window.bbClienteAtualizarMascaraTelefone === 'function'
            || typeof window.bbClientePrepararCamposAntesSalvar === 'function'
            || $('#telefone_ajuda').length > 0;
    }

    function bbEstaNaTelaAgendamentoPublico() {
        var path = (window.location.pathname || '').toLowerCase();

        if (path.indexOf('/agendamentos') === -1) {
            return false;
        }

        if ($('#telefone_pais').length > 0) {
            return false;
        }

        return $('#telefone').length > 0 && $('#nome').length > 0;
    }

    function bbSomenteDigitos(valor) {
        return (valor || '').toString().replace(/\D+/g, '');
    }

    function bbTemPrefixoInternacional(valor) {
        valor = (valor || '').toString();
        return /^\s*\+/.test(valor) || /^\s*00/.test(valor);
    }

    function bbSanitizarTelefoneInternacional(valor) {
        var texto = (valor || '').toString();

        texto = texto.replace(/[^\d+\s\-\(\)]/g, '');

        if (/^\s*00/.test(texto)) {
            texto = texto.replace(/^\s*00/, '+');
        }

        texto = texto.replace(/(?!^)\+/g, '');
        texto = texto.replace(/\s{2,}/g, ' ');

        return texto.trim();
    }

    function bbMascaraTelefoneBrasil(valor, campoFixo) {
        var digitos = bbSomenteDigitos(valor);

        if (campoFixo === true) {
            return digitos.length > 10 ? '(00) 00000-0000' : '(00) 0000-00009';
        }

        return digitos.length > 10 ? '(00) 00000-0000' : '(00) 0000-00009';
    }

    function bbAplicarMascaraBrasil($campo, campoFixo) {
        if (!$campo.length || typeof $campo.mask !== 'function') {
            return;
        }

        var mascaraAtual = bbMascaraTelefoneBrasil($campo.val(), campoFixo);
        var mascaraAnterior = ($campo.data('bb-mascara-atual') || '').toString();

        if (mascaraAnterior === mascaraAtual && $campo.data('bb-modo-atual') === 'br') {
            return;
        }

        if (typeof $campo.unmask === 'function') {
            $campo.unmask();
        }

        $campo.mask(mascaraAtual, {
            onKeyPress: function (valor, e, field, options) {
                var $field = $(field);
                var novaMascara = bbMascaraTelefoneBrasil(valor, campoFixo);
                var mascaraGuardada = ($field.data('bb-mascara-atual') || '').toString();

                if (novaMascara !== mascaraGuardada) {
                    $field.mask(novaMascara, options);
                    $field.data('bb-mascara-atual', novaMascara);
                }
            }
        });

        $campo.data('bb-mascara-atual', mascaraAtual);
        $campo.data('bb-modo-atual', 'br');
    }

    function bbAtivarModoInternacional($campo) {
        if (!$campo.length) {
            return;
        }

        if (typeof $campo.unmask === 'function' && $campo.data('bb-modo-atual') !== 'internacional') {
            $campo.unmask();
        }

        $campo.val(bbSanitizarTelefoneInternacional($campo.val()));
        $campo.data('bb-modo-atual', 'internacional');
        $campo.data('bb-mascara-atual', '');
    }

    function bbAtivarModoBrasil($campo, campoFixo) {
        if (!$campo.length) {
            return;
        }

        $campo.val(bbSomenteDigitos($campo.val()));
        bbAplicarMascaraBrasil($campo, campoFixo);
    }

    function bbAplicarTelefoneInteligente($campo, campoFixo, forcarModo) {
        if (!$campo.length) {
            return;
        }

        var valorAtual = ($campo.val() || '').toString();
        var modoManual = ($campo.data('bb-modo-manual') || '').toString();
        var deveSerInternacional = false;

        if (forcarModo === 'internacional') {
            deveSerInternacional = true;
        } else if (forcarModo === 'br') {
            deveSerInternacional = false;
        } else if (modoManual === 'internacional') {
            deveSerInternacional = true;
        } else if (bbTemPrefixoInternacional(valorAtual)) {
            deveSerInternacional = true;
        }

        if (deveSerInternacional) {
            bbAtivarModoInternacional($campo);
            return;
        }

        bbAtivarModoBrasil($campo, campoFixo);
    }

    function bbPrepararCampoTelefone(seletor, campoFixo) {
        var $campo = $(seletor);

        if (!$campo.length) {
            return;
        }

        bbAplicarTelefoneInteligente($campo, campoFixo);

        $(document).off('keydown.bbIntlMask', seletor).on('keydown.bbIntlMask', seletor, function (e) {
            var tecla = e.key || '';
            var $atual = $(this);
            var valorAtual = ($atual.val() || '').toString();

            if (tecla === '+') {
                e.preventDefault();
                $atual.data('bb-modo-manual', 'internacional');
                $atual.val('+');
                bbAtivarModoInternacional($atual);
                return;
            }

            if (valorAtual.trim() === '' && (tecla === '(' || /^\d$/.test(tecla))) {
                $atual.data('bb-modo-manual', 'br');
            }
        });

        $(document).off('paste.bbIntlMask', seletor).on('paste.bbIntlMask', seletor, function (e) {
            var evento = e.originalEvent || e;
            var texto = '';
            var campo = this;

            if (evento.clipboardData && evento.clipboardData.getData) {
                texto = evento.clipboardData.getData('text');
            }

            if (bbTemPrefixoInternacional(texto) || bbSomenteDigitos(texto).length > 11) {
                $(this).data('bb-modo-manual', 'internacional');
            } else {
                $(this).data('bb-modo-manual', 'br');
            }

            setTimeout(function () {
                bbAplicarTelefoneInteligente($(campo), campoFixo);
            }, 0);
        });

        $(document).off('input.bbIntlMask', seletor).on('input.bbIntlMask', seletor, function () {
            var $atual = $(this);
            var valorAtual = ($atual.val() || '').toString();

            if (bbTemPrefixoInternacional(valorAtual)) {
                $atual.data('bb-modo-manual', 'internacional');
                bbAtivarModoInternacional($atual);
                return;
            }

            if (valorAtual.trim() === '') {
                $atual.removeData('bb-modo-manual');
            }

            if (($atual.data('bb-modo-atual') || '').toString() !== 'br') {
                bbAtivarModoBrasil($atual, campoFixo);
            }
        });

        $(document).off('blur.bbIntlMask', seletor).on('blur.bbIntlMask', seletor, function () {
            var $atual = $(this);
            var valorAtual = ($atual.val() || '').toString();

            if (bbTemPrefixoInternacional(valorAtual)) {
                bbAtivarModoInternacional($atual);
                return;
            }

            bbAtivarModoBrasil($atual, campoFixo);
        });
    }

    function bbPrepararCampoTelefoneBrasilPuro(seletor, campoFixo) {
        var $campo = $(seletor);

        if (!$campo.length) {
            return;
        }

        $campo.removeData('bb-modo-manual');
        $campo.data('bb-modo-atual', 'br');

        $(document).off('keydown.bbIntlMask', seletor);
        $(document).off('paste.bbIntlMask', seletor);
        $(document).off('input.bbIntlMask', seletor);
        $(document).off('blur.bbIntlMask', seletor);

        bbAplicarMascaraBrasil($campo, campoFixo);

        $(document).off('paste.bbMaskBr', seletor).on('paste.bbMaskBr', seletor, function () {
            var campo = this;

            setTimeout(function () {
                $(campo).val(bbSomenteDigitos($(campo).val()));
                bbAplicarMascaraBrasil($(campo), campoFixo);
            }, 0);
        });

        $(document).off('blur.bbMaskBr', seletor).on('blur.bbMaskBr', seletor, function () {
            $(this).val(bbSomenteDigitos($(this).val()));
            bbAplicarMascaraBrasil($(this), campoFixo);
        });
    }

    if (!bbTemControladorLocalClientes()) {
        if (bbEstaNaTelaAgendamentoPublico()) {
            bbPrepararCampoTelefoneBrasilPuro('#telefone', false);
        } else {
            bbPrepararCampoTelefone('#telefone', false);
        }
    }

    bbPrepararCampoTelefone('#telefone-perfil', false);
    bbPrepararCampoTelefone('#whatsapp_sistema', false);
    bbPrepararCampoTelefone('#telefone_fixo_sistema', true);
    bbPrepararCampoTelefone('#telefone2', false);
    bbPrepararCampoTelefone('#telefone_rodape', false);

    if (typeof $('#cpf').mask === 'function') {
        $('#cpf').mask('000.000.000-00');
    }

    if (typeof $('#cep').mask === 'function') {
        $('#cep').mask('00000-000');
    }

    if (typeof $('#cnpj').mask === 'function') {
        $('#cnpj').mask('00.000.000/0000-00');
    }

    if (typeof $('#cpf-perfil').mask === 'function') {
        $('#cpf-perfil').mask('000.000.000-00');
    }

    if (typeof $('#cnpj_sistema').mask === 'function') {
        $('#cnpj_sistema').mask('00.000.000/0000-00');
    }
    });
})(0);
