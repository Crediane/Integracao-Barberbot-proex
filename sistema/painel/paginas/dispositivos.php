<?php
require_once __DIR__ . '/_context_bootstrap.php';

$pag = 'dispositivos';

/*
  ✅ CONTEXTO (MASTER x TENANT) ANTES DE QUALQUER CONEXÃO
  - TENANT: quando existir loja via ?loja=, ou URL /loja/<slug>/..., ou sessão já ativa
  - MASTER: quando não existir loja (dono do sistema)
  Obs: NÃO apaga sessão aqui (evita “vazar” e evita quebrar o painel do tenant em /sistema/painel/*)
*/
$__uriPath = (string)(parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/');

$tenant = '';
if (!empty($_GET['loja'])) {
	$tenant = preg_replace('/[^a-z0-9_-]/i', '', (string)$_GET['loja']);
} elseif (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__uriPath, $__m)) {
	$tenant = preg_replace('/[^a-z0-9_-]/i', '', (string)$__m[1]);
} elseif (session_status() === PHP_SESSION_ACTIVE) {
	// ✅ Se o painel já iniciou sessão, reaproveita o tenant logado
	if (!empty($_SESSION['loja'])) {
		$tenant = preg_replace('/[^a-z0-9_-]/i', '', (string)$_SESSION['loja']);
	} elseif (!empty($_SESSION['tenant_slug'])) {
		$tenant = preg_replace('/[^a-z0-9_-]/i', '', (string)$_SESSION['tenant_slug']);
	}
}

// força para a conexão enxergar o tenant
if ($tenant !== '' && empty($_GET['loja'])) {
	$_GET['loja'] = $tenant;
}

$qs_loja = ($tenant !== '') ? ('?loja=' . rawurlencode($tenant)) : '';

/*
  ✅ BASES (para webhook e assets) - SEM mudar lógica
 (para webhook e assets) - SEM mudar lógica
  - Webhook precisa apontar para o contexto correto (tenant ou master)
  - Assets do painel: aponta para /sistema/painel (real)
*/
$bb_proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
	$bb_proto = ($_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https' : $bb_proto;
}
$bb_host = (string)($_SERVER['HTTP_HOST'] ?? '');
$bb_base = ($bb_host !== '') ? ($bb_proto . '://' . $bb_host) : '';
$bb_web_prefix = rtrim((string)$__bb_webBase, '/');

// tenant via URL/GET (usado para prefixo /loja/{slug})
$__tenantFromUri = $tenant;

$bb_tenant_prefix = ($__tenantFromUri !== '') ? ('/loja/' . $__tenantFromUri) : '';
$url_sistema = $bb_base . $bb_web_prefix . $bb_tenant_prefix . '/sistema/';       // sempre com barra no final
$url_painel_assets = $bb_base . $bb_web_prefix . '/sistema/painel';

// webhook Menuia (arquivo em /public_html/webhook/menuia.php)
$webhook_menuia_url = $bb_base . $bb_web_prefix . '/webhook/menuia_gateway.php';               // assets reais do painel

// Permissão de módulo (mantém padrão existente)
if (@$dispositivos == 'ocultar') {
	$__bb_painel_public_url = isset($bb_painel_public_url) ? $bb_painel_public_url : ($bb_base . $bb_web_prefix . $bb_tenant_prefix . '/sistema/painel');
	echo "<script>window.location='" . $__bb_painel_public_url . "'</script>";
	exit();
}

require_once __DIR__ . '/../../conexao.php';

if (!isset($pdo) || !$pdo) {
	echo "<div class=\"alert alert-danger\">Erro: conexão não inicializada.</div>";
	return;
}


/* ✅ defaults (evita warnings) */
$instancia_whatsapp = $instancia_whatsapp ?? ($instancia ?? '');
$token_whatsapp = $token_whatsapp ?? ($token ?? '');
$api = $api ?? '';

// Lista dispositivos (no banco atual: MASTER ou TENANT)
$query = $pdo->prepare("SELECT * FROM dispositivos WHERE status_api IS NOT NULL");
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);

$total_dispositivos = @count($res);
$ativo = 0;
$bloqueado = 0;

foreach ($res as $dispositivo) {
	if (($dispositivo['status'] ?? '') == 'Ativo') {
		$ativo++;
	} elseif (($dispositivo['status'] ?? '') == 'bloqueado') {
		$bloqueado++;
	}
}

$total = $ativo + $bloqueado;
?>

<br>

<div class="main-page margin-mobile" style="margin-top: 10px">
	<div class="row">
		<div class="col-md-4">
			<button type="button" class="btn btn-primary" onclick="add()">
				<span class="fa fa-plus"></span> Adicionar Dispositivo
			</button>
		</div>
	</div>
</div>

<div class="bs-example widget-shadow" style="padding:15px; margin-top:0px" id="listar"></div>
<input type="hidden" id="ids">

<div class="modal fade" id="modaldispositivo" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title" id="exampleModalLabel">Realize a Leitura do QRCode</h4>
				<button id="btn-fechar" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -25px">
					<span aria-hidden="true">&times;</span>
				</button>
			</div>

			<div class="modal-body text-center">
				<div id="qrCodeContainer" style="display: none;"></div>
				<div id="statusMessage" style="display: none;"></div>
			</div>

			<div class="modal-footer">
				<button type="button" class="btn btn-primary" data-bs-dismiss="modal">Fechar</button>
			</div>
		</div>
	</div>
</div>

<br>

<p style="border:1px solid #000; padding:10px">
	<i class="fa fa-info-circle text-primary"></i> <b>ATENÇÃO: </b>
	<span>Para conectar seu whatsapp ao sistema através dessa opção você precisa primeiro ter um plano na Menuia que é uma das empresas que prestam esse serviço, se já tem o plano basta pegar o Authkey lá e colocar ele no campo (Instancia / AuthKey) que fica nas configurações, após isso clique em + Adicionar Dispositivo e ele já tem que dar a possibilidade de fazer a leitura do QRCode para conectar seu dispositivo.</span>
</p>

<script type="text/javascript">var pag = "<?=$pag?>"</script>

<!-- ✅ Mantém padrão do sistema, mas garante caminho real do JS -->
<script src="<?= $url_painel_assets ?>/js/ajax.js"></script>

<script type="text/javascript">
function add(appkey) {

	appkey = appkey || '';

	var authkey = '<?= $instancia_whatsapp; ?>';
	var qs_loja = '<?= $qs_loja ?>';

	if((authkey || '').trim() == ''){
		alert('Insira nas configurações a sua Instancia / Authkey da Menuia!');
		return;
	}

	var total_dispositivos = '<?= $total_dispositivos; ?>';
	if(total_dispositivos >= 1){
		alert('Você já tem 1 dispositivo conectado, remova ele para conectar outro!');
		return;
	}

	$('#modaldispositivo').modal('show');
	$('#statusMessage').hide();

	$.ajax({
		url: bb_url('paginas/' + pag + '/appkey.php') + qs_loja,
		method: 'POST',
		data: { appkey: appkey },
		dataType: 'json',
		success: function(resposta) {

			if (resposta.status == 200) {

				checar_conexao(resposta.appkey, function(status) {

					if (status == 501 || status == 404) {

						qrCode(resposta.appkey);

						// chama novamente após 10 segundos (mantém lógica)
						setTimeout(function() {
							add(appkey);
						}, 10000);

					} else if (status == 200) {

						var qrImage = $('<img>').attr('src', 'https://chatbot.menuia.com/uploads/connected.png').css('max-width', '100%');
						$('#qrCodeContainer').html(qrImage).show();

						// salva no banco de dados
						$.ajax({
							url: bb_url('paginas/' + pag + '/atualizar.php') + qs_loja,
							method: 'POST',
							data: { appkey: resposta.appkey },
							dataType: 'json',
							success: function(resultado) {
								if (resultado.status == 200) {
									$('#statusMessage').html('Dispositivo Conectado!').show();
									setTimeout(function() {
										location.reload();
									}, 3000);
								} else {
									$('#statusMessage').html('Dispositivo Conectado, porém não conseguiu salvar no banco de dados!').show();
								}
							},
							error: function() {
								console.log('Ocorreu um erro interno ao tentar salvar no banco de dados');
							}
						});
					}
				});

			} else if(resposta.status == 404) {
				$('#statusMessage').html('Appkey Invalida, gere um novo dispositivo!').show();
			} else {
				console.log('Erro');
			}
		},
		error: function() {
			console.log('Erro na requisição AJAX');
		}
	});
}

function checar_conexao(appkey, callback) {
	var authkey = '<?= $instancia_whatsapp; ?>';
	var qs_loja = '<?= $qs_loja ?>';

	$.ajax({
		url: 'https://chatbot.menuia.com/api/developer',
		type: 'POST',
		data: {
			authkey: authkey,
			message: appkey,
			licence: 'hugocursos',
			checkDispositivo: 'true'
		},
		success: function(response) {

			$('#loadingIndicator').hide();

			if (response.status === 200) {

				$.ajax({
					url: bb_url('paginas/' + pag + '/atualizar_numero.php') + qs_loja,
					method: 'POST',
					data: { appkey: appkey, dados: response.dados},
					dataType: 'json',
					success: function(resultado) {
						callback(200);
					},
					error: function() {
						console.log('Ocorreu um erro interno ao tentar salvar no banco de dados');
					}
				});

				callback(200);

			} else if (response.status === 404 || response.status === 403) {
				callback(404);
			} else {
				callback(500);
			}
		},
		error: function(error) {
			callback(501);
		}
	});
}

function qrCode(appkey) {

	var authkey = '<?= $instancia_whatsapp; ?>';
	var qs_loja = '<?= $qs_loja ?>';

	$.ajax({
		url: 'https://chatbot.menuia.com/api/developer', // Gerando um QRCODE
		type: 'POST',
		data: {
			authkey: authkey,
			message: appkey,
			licence: 'hugocursos',
			conecteQR: 'true',
			// ✅ FIX: webhook sempre com barra e no contexto correto (tenant ou master)
			webhook: '<?= $webhook_menuia_url ?>'
		},
		success: function (response) {

			if (response.status === 200) {
				var qrCode = response.message.qr;
				var qrImage = $('<img>').attr('src', qrCode).css('max-width', '100%');
				$('#qrCodeContainer').html(qrImage).show();
			} else {
				console.log(response);
				$('#statusMessage').html('Erro ao carregar o QR code.').show();
			}
		},
		error: function (error) {

			if(error.status === 403) {
				$('#loadingIndicator').hide();
				var qrImage = $('<img>').attr('src', 'https://chatbot.menuia.com/public/license.jpg').css('max-width', '70%');
				$('#qrCodeContainer').html(qrImage).show();
				$('#statusMessage').html('Sua licença se encontra ativa em outro dispositivo, faça upgrade ou entre contato com a Menuia para mais informações!').show();
				return;
			} else {
				var falha = (error.responseJSON && error.responseJSON.message) ? error.responseJSON.message : 'Ocorreu um erro desconhecido';
				$('#loadingIndicator').hide();
				$('#statusMessage').html(falha).show();
			}
		}
	});
}



// Testar disparo de mensagem (ícone WhatsApp na listagem)
// - Mantém padrão do sistema (jQuery 1.11)
// - Lê o telefone diretamente da linha da tabela
function testarApi(el, appkey) {
	try {
		var qs_loja = '<?= $qs_loja ?>';
		var $tr = $(el).closest('tr');
		var telefone = $.trim($tr.find('td').eq(1).text());

		if (!telefone) {
			alert('Telefone do dispositivo não encontrado na listagem.');
			return;
		}

		// Bloqueia clique repetido
		var $icon = $(el).find('i');
		var oldHtml = $icon.length ? $icon.attr('class') : '';
		if ($icon.length) {
			$icon.attr('class', 'fa fa-spinner fa-spin');
		}

		$.ajax({
			url: bb_url('paginas/' + pag + '/testar.php') + qs_loja,
			method: 'POST',
			dataType: 'json',
			data: {
				telefone: telefone,
				appkey: appkey
			},
			success: function(r){
				if ($icon.length) { $icon.attr('class', oldHtml || 'fa fa-whatsapp'); }
				if (r && (r.ok === true || r.ok === 1 || r.sucesso === true || r.status === 200)) {
					alert('✅ Deu tudo certo! Mensagem de teste enviada.');
				} else {
					var msg = (r && (r.message || r.mensagem)) ? (r.message || r.mensagem) : 'Não consegui confirmar o envio. Verifique token/instância/dispositivo.';
					alert('⚠️ ' + msg);
				}
			},
			error: function(xhr){
				if ($icon.length) { $icon.attr('class', oldHtml || 'fa fa-whatsapp'); }
				alert('⚠️ Erro ao testar o disparo. Verifique o console e o error_log.');
			}
		});
	} catch(e) {
		alert('⚠️ Erro interno no teste: ' + e);
	}
}
</script>
