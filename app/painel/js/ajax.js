function bb_toast(msg, cor) {
	msg = (msg == null ? '' : String(msg)).trim();
	if (!msg) msg = 'Erro ao processar requisição';
	if (typeof toast === 'function') {
		toast(msg, cor || 'vermelha');
		return;
	}
	if ($('#mensagem').length) {
		$('#mensagem').removeClass().addClass('text-danger').text(msg);
		return;
	}
	if ($('#mensagem-excluir').length) {
		$('#mensagem-excluir').removeClass().addClass('text-danger').text(msg);
		return;
	}
	alert(msg);
}

function bb_sanitizar_slug(slug) {
	try {
		slug = (slug == null ? '' : String(slug)).trim().toLowerCase();
		slug = slug.replace(/[^a-z0-9_-]/g, '');
		return slug;
	} catch (e) {
		return '';
	}
}

function bb_set_loja_slug(slug) {
	slug = bb_sanitizar_slug(slug);
	if (!slug) return '';
	try { sessionStorage.setItem('bb_tenant_slug', slug); } catch (e) {}
	try { localStorage.setItem('bb_tenant_slug', slug); } catch (e) {}
	return slug;
}

/**
 * Retorna o slug do tenant quando estiver em /loja/{slug}/...
 * - Não "chuta" tenant: prioriza a URL atual
 * - Fallback: storage (apenas quando não estiver em /sistema/)
 */
function bb_get_loja_slug() {
	try {
		var path = (window.location && window.location.pathname) ? window.location.pathname : '';
		// MASTER: nunca injeta loja
		if (path && path.indexOf('/sistema/') === 0) return '';

		var m = path.match(/^\/loja\/([^\/]+)(?:\/|$)/i);
		if (m && m[1]) return bb_set_loja_slug(m[1]);

		// Domínio próprio/subdomínio: o PHP entrega o slug já confirmado pelo banco global.
		var serverSlug = '';
		try { serverSlug = bb_sanitizar_slug(window.BB_TENANT_SLUG || ''); } catch (e0) { serverSlug = ''; }
		if (serverSlug) return bb_set_loja_slug(serverSlug);

		// fallback legado para navegação interna
		var s = '';
		try { s = sessionStorage.getItem('bb_tenant_slug') || ''; } catch (e) { s = ''; }
		if (!s) { try { s = localStorage.getItem('bb_tenant_slug') || ''; } catch (e2) { s = ''; } }
		s = bb_sanitizar_slug(s);

		return s;
	} catch (e) {
		return '';
	}
}

$("#form").submit(function (event) {
	event.preventDefault();
	var formData = new FormData(this);
	bb_injetar_loja_formdata(formData);
	$('#btn_salvar').hide();
	$('#btn_carregando').show();
	$.ajax({
		url: '/sistema/painel/paginas/' + pag + "/salvar.php",
		type: 'POST',
		data: formData,
		success: function (mensagem) {
			//ARMAZENAR O RETORNO PARA A MSG DE SUCESSO
			$('#toast-message').text(mensagem.trim());
			if (mensagem.trim() == "Salvo com Sucesso") {
				$('#btn-fechar').click();
				$('#not_salvar').click(); // Dispara a notificação de sucesso
				setTimeout(function () {
					location.reload();
				}, 1000);
			} else {
				bb_toast(mensagem, 'vermelha'); // Dispara a notificação de erro
			}
			$('#btn_carregando').hide();
			$('#btn_salvar').show();
		},
		cache: false,
		contentType: false,
		processData: false,
	});
});

  $("#form-status").submit(function (event) {
    event.preventDefault();

    $('#btn_salvar_status').hide();
    $('#btn_carregando_status').show();

    var formData = new FormData(this);
    bb_injetar_loja_formdata(formData);

    $.ajax({
      url: '/sistema/painel/paginas/' + pag + "/mudar-status.php",
      type: 'POST',
      data: formData,
      success: function (mensagem) {
        $('#toast-message').text(mensagem.trim());
        if (mensagem.trim() == "Salvo com Sucesso") {
          $('#btn-fechar').click();
          $('#not_salvar').click();
          setTimeout(function () {
            location.reload();
          }, 1000);
        } else {
          bb_toast(mensagem, 'vermelha');
        }
      },
      error: function () {
        bb_toast('Erro ao processar requisição', 'vermelha');
      },
      complete: function () {
        $('#btn_carregando_status').hide();
        $('#btn_salvar_status').show();
      },
      cache: false,
      contentType: false,
      processData: false
    });
  });



$("#form2").submit(function (event) {
	event.preventDefault();
	var formData = new FormData(this);
	bb_injetar_loja_formdata(formData);
	$('#btn_salvar').hide();
	$('#btn_carregando').show();
	$.ajax({
		url: '/sistema/painel/paginas/' + pag + "/inserir.php",
		type: 'POST',
		data: formData,
		success: function (mensagem) {
			//ARMAZENAR O RETORNO PARA A MSG DE SUCESSO
			$('#toast-message').text(mensagem.trim());
			if (mensagem.trim() == "Salvo com Sucesso") {
				$('#btn-fechar').click();
				$('#not_salvar').click(); // Dispara a notificação de sucesso
				setTimeout(function () {
					location.reload();
				}, 1000);
			} else {
				bb_toast(mensagem, 'vermelha'); // Dispara a notificação de erro
			}
			$('#btn_carregando').hide();
			$('#btn_salvar').show();
		},
		cache: false,
		contentType: false,
		processData: false,
	});
});

// agenda: formulário novo de agendamento (mantém o mesmo endpoint do inserir.php)
$("#form_agendamento").submit(function (event) {
	event.preventDefault();
	var formData = new FormData(this);
	bb_injetar_loja_formdata(formData);

	// garante envio do funcionário, seguindo o padrão já usado no arquivo
	try {
		var temFunc = false;
		try { temFunc = (typeof formData.has === 'function') ? formData.has('funcionario') : false; } catch(e) { temFunc = false; }
		if (!temFunc) {
			var f = '';
			try { f = ($('#funcionario').val() || $('#id_funcionario').val() || ''); } catch(e) { f = ''; }
			if (f) formData.append('funcionario', f);
		}
	} catch(e) {}

	$('#btn_salvar').hide();
	$('#btn_carregando').show();
	$.ajax({
		url: '/sistema/painel/paginas/' + pag + "/inserir.php",
		type: 'POST',
		data: formData,
		success: function (mensagem) {
			mensagem = (mensagem || '').trim();
			$('#toast-message').text(mensagem);
			if (mensagem == "Salvo com Sucesso") {
				$('#btn-fechar').click();
				$('#not_salvar').click();
				setTimeout(function () {
					location.reload();
				}, 1000);
			} else {
				bb_toast(mensagem || 'Erro ao salvar', 'vermelha');
			}
			$('#btn_carregando').hide();
			$('#btn_salvar').show();
		},
		error: function (xhr) {
			var msg = (xhr && xhr.responseText) ? xhr.responseText : 'Erro ao processar requisição';
			bb_toast(msg, 'vermelha');
			$('#btn_carregando').hide();
			$('#btn_salvar').show();
		},
		cache: false,
		contentType: false,
		processData: false,
	});
});

/* ============================================================
 * Agenda - baixa/fechamento do serviço
 * Corrige botão que fica em "Carregando..." sem fechar modal.
 * Mantém endpoint e regra original:
 *   /sistema/painel/paginas/agenda/inserir-servico.php
 * ============================================================ */
(function () {
	var bb_baixa_agenda_enviando = false;

	function bb_valor_campo(selector) {
		try {
			var $el = $(selector);
			if (!$el.length) return '';
			var valor = $el.val();
			return (valor == null ? '' : String(valor)).trim();
		} catch (e) {
			return '';
		}
	}

	function bb_modal_baixa_restaurar_botao() {
		try {
			$('#btn_carregando_servico').hide();
			$('#btn_salvar_servico').show().prop('disabled', false);
		} catch (e) {}
	}

	function bb_modal_baixa_fechar() {
		try {
			var el = document.getElementById('menu-share-servico');
			if (el && window.bootstrap && bootstrap.Offcanvas) {
				var instancia = bootstrap.Offcanvas.getInstance(el);
				if (!instancia && bootstrap.Offcanvas.getOrCreateInstance) {
					instancia = bootstrap.Offcanvas.getOrCreateInstance(el);
				}
				if (instancia && typeof instancia.hide === 'function') {
					instancia.hide();
				}
			}
		} catch (e) {}

		try { $('#btn-fechar-servico').trigger('click'); } catch (e2) {}
	}

	function bb_resposta_ok(mensagem) {
		mensagem = (mensagem == null ? '' : String(mensagem)).trim();

		// Mantém o retorno original, mas tolera espaços/HTML residual do PHP.
		return (
			mensagem === 'Salvo com Sucesso' ||
			mensagem === 'Alterado com Sucesso' ||
			mensagem === 'Baixado com Sucesso' ||
			mensagem.indexOf('Salvo com Sucesso') !== -1 ||
			mensagem.indexOf('Alterado com Sucesso') !== -1 ||
			mensagem.indexOf('Baixado com Sucesso') !== -1
		);
	}

	window.bb_baixar_agendamento_servico = function (event) {
		if (event && typeof event.preventDefault === 'function') event.preventDefault();
		if (event && typeof event.stopPropagation === 'function') event.stopPropagation();

		if (bb_baixa_agenda_enviando) {
			return false;
		}

		var form = document.getElementById('form-servico');
		if (!form) {
			bb_toast('Formulário da baixa não encontrado.', 'vermelha');
			bb_modal_baixa_restaurar_botao();
			return false;
		}

		var idAgendamento = bb_valor_campo('#id_agd');
		var cliente = bb_valor_campo('#cliente_agd');
		var servico = bb_valor_campo('#servico_agd');
		var funcionario = bb_valor_campo('#funcionario_agd');
		var pgto = bb_valor_campo('#pgto');

		if (!idAgendamento || Number(idAgendamento) <= 0) {
			bb_toast('Agendamento inválido. Feche a modal, atualize a agenda e tente novamente.', 'vermelha');
			return false;
		}

		if (!cliente || Number(cliente) <= 0) {
			bb_toast('Cliente não informado para este agendamento.', 'vermelha');
			return false;
		}

		if (!servico || Number(servico) <= 0) {
			bb_toast('Serviço não informado para este agendamento.', 'vermelha');
			return false;
		}

		if (!funcionario || Number(funcionario) <= 0) {
			bb_toast('Funcionário não informado para este agendamento.', 'vermelha');
			return false;
		}

		if (!pgto) {
			bb_toast('Informe a forma de pagamento.', 'vermelha');
			return false;
		}

		var formData = new FormData(form);
		bb_injetar_loja_formdata(formData);

		bb_baixa_agenda_enviando = true;
		$('#mensagem').text('').removeClass();
		$('#btn_salvar_servico').hide().prop('disabled', true);
		$('#btn_carregando_servico').show();

		var paginaAgenda = (typeof pag !== 'undefined' && String(pag).trim()) ? String(pag).trim() : 'agenda';

		$.ajax({
			url: '/sistema/painel/paginas/' + paginaAgenda + '/inserir-servico.php',
			type: 'POST',
			data: formData,
			timeout: 45000,
			success: function (mensagem) {
				mensagem = (mensagem == null ? '' : String(mensagem)).trim();
				$('#toast-message').text(mensagem);

				if (bb_resposta_ok(mensagem)) {
					bb_modal_baixa_fechar();
					try { $('#not_salvar').click(); } catch (e) {}

					setTimeout(function () {
						window.location.reload();
					}, 700);
					return;
				}

				bb_toast(mensagem || 'Erro ao confirmar pagamento.', 'vermelha');
				bb_modal_baixa_restaurar_botao();
			},
			error: function (xhr, status) {
				var msg = '';

				if (status === 'timeout') {
					msg = 'A baixa demorou para responder. Atualize a agenda antes de tentar novamente.';
				} else if (xhr && xhr.responseText) {
					msg = String(xhr.responseText).trim();
				}

				bb_toast(msg || 'Erro ao finalizar a baixa da agenda.', 'vermelha');
				bb_modal_baixa_restaurar_botao();
			},
			complete: function () {
				bb_baixa_agenda_enviando = false;
			},
			cache: false,
			contentType: false,
			processData: false
		});

		return false;
	};

	$(document)
		.off('submit.bbAgendaServico', '#form-servico')
		.on('submit.bbAgendaServico', '#form-servico', function (event) {
			return window.bb_baixar_agendamento_servico(event);
		});

	$(document)
		.off('click.bbAgendaServicoBtn', '#btn_salvar_servico')
		.on('click.bbAgendaServicoBtn', '#btn_salvar_servico', function (event) {
			return window.bb_baixar_agendamento_servico(event);
		});
})();


$("#form-anot").submit(function (event) {
	event.preventDefault();
	var formData = new FormData(this);
	bb_injetar_loja_formdata(formData);

	// Atualiza o textarea oculto com o conteúdo do editor antes de enviar
	var content = quill.root.innerHTML;
	document.getElementById('hiddenTextarea').value = content;

	$('#btn_salvar').hide();
	$('#btn_carregando').show();
	$.ajax({
		url: '/sistema/painel/paginas/' + pag + "/salvar.php",
		type: 'POST',
		data: formData,
		success: function (mensagem) {
			//ARMAZENAR O RETORNO PARA A MSG DE SUCESSO
			$('#toast-message').text(mensagem.trim());
			if (mensagem.trim() == "Salvo com Sucesso") {
				$('#btn-fechar').click();
				$('#not_salvar').click(); // Dispara a notificação de sucesso
				setTimeout(function () {
					location.reload();
				}, 1000);
			} else {
				bb_toast(mensagem, 'vermelha'); // Dispara a notificação de erro
			}
			$('#btn_carregando').hide();
			$('#btn_salvar').show();
		},
		cache: false,
		contentType: false,
		processData: false,
	});
});



//####### MUDAR STSTUS ##########################
$("#form_status").submit(function (event) {
	event.preventDefault();
	var formData = new FormData(this);
  bb_injetar_loja_formdata(formData);

  // garante envio do funcionário (backend espera "funcionario")
  try {
    var temFunc = false;
    try { temFunc = (typeof formData.has === 'function') ? formData.has('funcionario') : false; } catch(e) { temFunc = false; }
    if (!temFunc) {
      var f = '';
      try { f = ($('#funcionario').val() || $('#id_funcionario').val() || ''); } catch(e) { f = ''; }
      if (f) formData.append('funcionario', f);
    }
  } catch(e) {}

  $('#btn_salvar').hide();
  $('#btn_carregando').show();
  $.ajax({
    url: '/sistema/painel/paginas/' + pag + "/inserir.php",
    type: 'POST',
    data: formData,
    success: function (mensagem) {
      //alert(mensagem)
      //ARMAZENAR O RETORNO PARA A MSG DE SUCESSO
      $('#toast-message').text(mensagem.trim());
      if (mensagem.trim() == "Salvo com Sucesso") {
        $('#btn-fechar').click();
        $('#not_salvar').click(); // Dispara a notificação de sucesso
        setTimeout(function () {
          location.reload();
        }, 1000);
      } else {
        bb_toast(mensagem, 'vermelha'); // Dispara a notificação de erro
      }
      $('#btn_carregando').hide();
      $('#btn_salvar').show();
    },
    cache: false,
    contentType: false,
    processData: false,
  });
});



 $("#form_dias").submit(function (event) {
  event.preventDefault();
  var formData = new FormData(this);
  bb_injetar_loja_formdata(formData);
  $('#btn_salvar').hide();
  $('#btn_carregando').show();
  $.ajax({
    url: '/sistema/painel/paginas/funcionarios/inserir-dias.php',
    type: 'POST',
    data: formData,
    success: function (mensagem) {
      //alert(mensagem)
      //ARMAZENAR O RETORNO PARA A MSG DE SUCESSO
      $('#toast-message').text(mensagem.trim());
      if (mensagem.trim() == "Salvo com Sucesso") {
        $('#btn-fechar').click();
        $('#not_salvar').click(); // Dispara a notificação de sucesso
        setTimeout(function () {
          location.reload();
        }, 1000);
      } else {
        bb_toast(mensagem, 'vermelha'); // Dispara a notificação de erro
      }
      $('#btn_carregando').hide();
      $('#btn_salvar').show();
    },
    cache: false,
    contentType: false,
    processData: false,
  });
});




 $("#form_servico").submit(function (event) {
  event.preventDefault();
  var formData = new FormData(this);
  bb_injetar_loja_formdata(formData);
  $('#btn_salvar').hide();
  $('#btn_carregando').show();
  $.ajax({
    url: '/sistema/painel/paginas/funcionarios/inserir-servico.php',
    type: 'POST',
    data: formData,
    success: function (mensagem) {
      //alert(mensagem)
      //ARMAZENAR O RETORNO PARA A MSG DE SUCESSO
      $('#toast-message').text(mensagem.trim());
      if (mensagem.trim() == "Salvo com Sucesso") {
        $('#btn-fechar').click();
        $('#not_salvar').click(); // Dispara a notificação de sucesso
        setTimeout(function () {
          location.reload();
        }, 1000);
      } else {
        bb_toast(mensagem, 'vermelha'); // Dispara a notificação de erro
      }
      $('#btn_carregando').hide();
      $('#btn_salvar').show();
    },
    cache: false,
    contentType: false,
    processData: false,
  });
});




 $("#form_dias_bloqueio").submit(function (event) {
  event.preventDefault();
  var formData = new FormData(this);
  bb_injetar_loja_formdata(formData);
  $('#btn_salvar').hide();
  $('#btn_carregando').show();
  $.ajax({
    url: '/sistema/painel/paginas/dias_bloqueio_func/inserir-servico.php',
    type: 'POST',
    data: formData,
    success: function (mensagem) {
      //alert(mensagem)
      //ARMAZENAR O RETORNO PARA A MSG DE SUCESSO
      $('#toast-message').text(mensagem.trim());
      if (mensagem.trim() == "Salvo com Sucesso") {
        $('#btn-fechar').click();
        $('#not_salvar').click(); // Dispara a notificação de sucesso
        setTimeout(function () {
          location.reload();
        }, 1000);
      } else {
        bb_toast(mensagem, 'vermelha'); // Dispara a notificação de erro
      }
      $('#btn_carregando').hide();
      $('#btn_salvar').show();
    },
    cache: false,
    contentType: false,
    processData: false,
  });
})

/**
 * Injeta o contexto do tenant (loja=slug) em um FormData.
 * Mantém o padrão multi-tenant sem depender de slug na URL do endpoint.
 */
function bb_injetar_loja_formdata(fd) {
    try {
        if (!fd) return fd;
        var s = bb_get_loja_slug();
        if (!s) return fd;
        // FormData.has pode não existir em navegadores antigos
        var tem = false;
        try { tem = (typeof fd.has === 'function') ? fd.has('loja') : false; } catch(e) { tem = false; }
        if (!tem) {
            fd.append('loja', s);
        }
    } catch (e) {}
    return fd;
}

function bb_merge_loja(obj) {
    if (!obj) obj = {};
    try {
        if (!Object.prototype.hasOwnProperty.call(obj, 'loja')) {
            var s = bb_get_loja_slug();
            if (s) obj['loja'] = s;
        }
    } catch (e) {}
    return obj;
}
/* ============================================================
 * BarberBot APP - Função global de exclusão
 * Corrige: Uncaught ReferenceError: excluir_reg is not defined
 * Padrão do sistema: /sistema/painel/paginas/{pag}/excluir.php
 * Mantém tenant via bb_injetar_loja_formdata(FormData)
 * Aceita chamadas:
 *   excluir_reg(id)
 *   excluir_reg(id, nome)
 * ============================================================ */
(function () {
  if (typeof window.excluir_reg === 'function') return;

  window.excluir_reg = function (id, nome) {
    try {
      id = (id === null || id === undefined) ? '' : String(id).trim();
      if (!id) {
        bb_toast('ID inválido para exclusão.', 'vermelha');
        return false;
      }

      var pergunta = (nome && String(nome).trim())
        ? ('Deseja excluir "' + String(nome).trim() + '"?')
        : 'Deseja excluir este registro?';

      if (!confirm(pergunta)) return false;

      if (typeof pag === 'undefined' || !String(pag).trim()) {
        // fallback seguro: se não tiver "pag", tenta usar "agenda"
        // (não altera lógica, só evita quebrar)
        window.pag = 'agenda';
      }

      var formData = new FormData();
      // padrão mais comum do sistema
      formData.append('id', id);
      // compatibilidade (caso algum excluir.php espere outro nome)
      formData.append('id_reg', id);

      if (typeof bb_injetar_loja_formdata === 'function') {
        bb_injetar_loja_formdata(formData);
      }

      // usa jQuery, como o resto do arquivo
      $.ajax({
        url: '/sistema/painel/paginas/' + pag + '/excluir.php',
        type: 'POST',
        data: formData,
        processData: false,
        contentType: false,
        success: function (mensagem) {
          mensagem = (mensagem == null ? '' : String(mensagem)).trim();

          // padrões de retorno comuns
          var ok = (
            mensagem === 'Excluído com Sucesso' ||
            mensagem === 'Excluido com Sucesso' ||
            mensagem === 'Excluído' ||
            mensagem === 'Excluido' ||
            mensagem === 'OK' ||
            mensagem === 'Sucesso'
          );

          if (ok) {
            try { $('#not_excluir').click(); } catch(e) {}
            setTimeout(function () { location.reload(); }, 400);
          } else {
            // Se o backend retorna HTML/erro, mostra em toast
            bb_toast(mensagem || 'Não foi possível excluir.', 'vermelha');
          }
        },
        error: function () {
          bb_toast('Erro ao excluir. Tente novamente.', 'vermelha');
        }
      });

      return false;
    } catch (e) {
      // fallback: não quebra o app
      return false;
    }
  };
})();
