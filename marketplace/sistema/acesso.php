<?php
// /public_html/sistema/acesso.php
// Login do Painel do Cliente.
// O conexao.php resolve o tenant pela URL /loja/{slug}/, parâmetro, subdomínio ou domínio próprio.
// Esta página não aceita o contexto MASTER nem banco global.

require_once __DIR__ . '/conexao.php';

if (!function_exists('bb_acesso_limpar_slug')) {
    function bb_acesso_limpar_slug($valor): string
    {
        $valor = strtolower(trim((string)$valor));
        return (string)preg_replace('/[^a-z0-9_-]/', '', $valor);
    }
}

if (!function_exists('h')) {
    function h($valor): string
    {
        return htmlspecialchars((string)$valor, ENT_QUOTES, 'UTF-8');
    }
}

$bb_acesso_req_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$bb_acesso_slug_url = '';

if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $bb_acesso_req_path, $bb_acesso_match)) {
    $bb_acesso_slug_url = bb_acesso_limpar_slug($bb_acesso_match[1]);
} elseif (!empty($_GET['loja'])) {
    $bb_acesso_slug_url = bb_acesso_limpar_slug($_GET['loja']);
} elseif (!empty($_POST['loja'])) {
    $bb_acesso_slug_url = bb_acesso_limpar_slug($_POST['loja']);
}

$__slug = bb_acesso_limpar_slug($_SESSION['tenant_slug'] ?? '');
$bb_acesso_tenant_db = trim((string)($_SESSION['tenant_db'] ?? ''));
$bb_acesso_sessao_tenant = strpos((string)session_name(), 'BB_TENANT') === 0;

if ($bb_acesso_slug_url !== '' && $__slug !== '' && $bb_acesso_slug_url !== $__slug) {
    http_response_code(403);
    echo 'A loja informada não corresponde ao contexto atual.';
    exit();
}

if ($__slug === '') {
    $__slug = $bb_acesso_slug_url;
}

$bb_acesso_banco_atual = '';
if ($pdo instanceof PDO) {
    try {
        $bb_acesso_banco_atual = (string)$pdo->query('SELECT DATABASE()')->fetchColumn();
    } catch (Throwable $e) {
        $bb_acesso_banco_atual = '';
    }
}

if (
    !$bb_acesso_sessao_tenant
    || session_name() === 'BB_MASTER'
    || $__slug === ''
    || $bb_acesso_tenant_db === ''
    || !($pdo instanceof PDO)
    || $bb_acesso_banco_atual === ''
    || strcasecmp($bb_acesso_banco_atual, $bb_acesso_tenant_db) !== 0
) {
    http_response_code(403);
    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    echo '<!DOCTYPE html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Acesso indisponível</title></head><body style="font-family:Arial,sans-serif;background:#f5f5f5;padding:30px"><div style="max-width:620px;margin:60px auto;background:#fff;padding:24px;border-radius:12px;border:1px solid #ddd"><h2 style="margin-top:0">Painel do cliente indisponível</h2><p>Abra o painel pelo endereço da sua loja.</p></div></body></html>';
    exit();
}

unset($_SESSION['usuario_logado_pagina']);
$_SESSION['usuario_logado_pagina'] = true;

$flash = null;
if (!empty($_SESSION['flash']) && is_array($_SESSION['flash'])) {
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
}

$erroGet = isset($_GET['erro']) ? (string)$_GET['erro'] : null;

// Mantém a rota dinâmica quando ela veio por /loja/{slug}/.
// Em domínio próprio ou subdomínio, preserva o caminho já aceito e envia o slug no formulário.
$bb_acesso_rota_loja = (bool)preg_match('~^/loja/[a-z0-9_-]+(?:/|$)~i', $bb_acesso_req_path);
if ($bb_acesso_rota_loja) {
    $sistema_route = '/loja/' . rawurlencode($__slug) . '/sistema';
    $cadastro_cliente_route = '/loja/' . rawurlencode($__slug) . '/cadastro-cliente.php';
} else {
    $sistema_route = '/sistema';
    $cadastro_cliente_route = '/cadastro-cliente.php?loja=' . rawurlencode($__slug);
}

?>
<!DOCTYPE html>
<html>
<head>
    <base href="<?php echo h($sistema_route); ?>/">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo h($nome_sistema); ?></title>

    <link href="//netdna.bootstrapcdn.com/bootstrap/3.0.3/css/bootstrap.min.css" rel="stylesheet" id="bootstrap-css">
    <link rel="stylesheet" type="text/css" href="painel_cliente/css/bootstrap.css">
    <link rel="stylesheet" href="painel_cliente/css/font-awesome.css">
    <link rel="stylesheet" href="painel_cliente/css/custom.css">
    <link rel="stylesheet" href="painel_cliente/css/style.css">
    <link rel="stylesheet" type="text/css" href="css/estilo-login.css">
    <link rel="shortcut icon" href="painel/img/favicon.png" type="image/png">
    <link rel="icon" href="painel/img/favicon.png" type="image/png">

    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@3.4.1/dist/css/bootstrap-theme.min.css" crossorigin="anonymous">
    <!-- SweetAlert2 CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.6/dist/sweetalert2.min.css">
</head>
<body>
<div class="container ">
    <div class="row vertical-offset-100">
        <div class="col-md-4 col-md-offset-4">
            <div class="panel panel-default form-login" style="opacity:0.9; border-radius: 20px">
                <div class="panel-heading" align="center" style="border-top-right-radius: 20px; border-top-left-radius: 20px">
                    <img src="img/logo.png" width="100px">
                </div>
                <p class="recuperar">👥 Acesso Exclusivo ao Painel do Cliente!</p>
                <div class="panel-body">

                    <?php
                    // MENSAGENS DE ERRO / SUCESSO

                    if ($flash && !empty($flash['message'])):
                        $type  = !empty($flash['type']) ? $flash['type'] : 'error';
                        // Bootstrap 3: alert-success / alert-danger / alert-warning
                        $class = 'alert-danger';
                        if ($type === 'success') {
                            $class = 'alert-success';
                        } elseif ($type === 'warning') {
                            $class = 'alert-warning';
                        }
                        ?>
                        <div class="alert <?php echo $class; ?>" role="alert" style="margin-bottom:15px;">
                            <?php echo h($flash['message']); ?>
                        </div>
                    <?php
                    // Compatibilidade com ?erro=...
                    elseif ($erroGet === 'login'): ?>
                        <div class="alert alert-danger" role="alert" style="margin-bottom:15px;">
                            Usuário ou senha incorretos!
                        </div>
                    <?php elseif ($erroGet === 'sessao-expirada'): ?>
                        <div class="alert alert-warning" role="alert" style="margin-bottom:15px;">
                            Sua sessão expirou. Faça login novamente.
                        </div>
                    <?php endif; ?>

                    <form accept-charset="UTF-8" role="form" action="autenticar_cliente.php" method="post">
                        <fieldset>
                            <?php if (!empty($__slug)) { ?>
                            <input type="hidden" name="loja" value="<?php echo h($__slug); ?>">
                            <?php } ?>
                            <div class="form-group">
                                <select class="form-control" name="telefone_pais" id="telefone_pais" style="width:100%; margin-bottom:6px;">
                                    <option value="br" data-ddi="55">Brasil (+55)</option>
                                </select>
                                <input type="hidden" name="telefone_ddi" id="telefone_ddi" value="55">
                                <input class="form-control" placeholder="Seu Telefone" name="telefone" id="telefone" type="text" value="" autocomplete="off" inputmode="tel">
                                <small id="telefone_ajuda_login" class="text-muted" style="display:block; margin-top:6px;">Brasil (+55): digite normalmente com DDD.</small>
                            </div>
                            <div class="form-group">
                                <input class="form-control" placeholder="Senha" name="senha" type="password" value="">
                            </div>

                            <!-- reCAPTCHA Aqui -->
                            <div class="g-recaptcha" data-sitekey="6LdltT0fAAAAAGA1M26pB2eoBeJSdVW3XWMcxbS-"></div>
                            <br>
                            <input class="btn btn-lg btn-primary btn-block" type="submit" value="Login">
                        </fieldset>
                        <p class="recuperar">
                            <a title="Clique para recuperar a senha" href="" data-toggle="modal" data-target="#exampleModal">Recuperar Senha</a>
                        </p>
                        <p class="recuperar">
                            <a title="Cadastre-se para acessar o painel do cliente." href="<?php echo h($cadastro_cliente_route); ?>" target="_blank">
                                <i class="fa fa-user-plus" aria-hidden="true"></i> [Cadastrar Cliente]
                            </a>
                        </p>
                        <p class="recuperar">
                            <a title="Gerencie suas tarefas, clientes e muito mais diretamente pelo painel." href="<?php echo h($sistema_route); ?>/" target="_blank">📋 [Acessar Painel do Profissional]</a>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modal Recuperar Senha -->
<div class="modal fade" id="exampleModal" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
  <div class="modal-dialog" role="document">
    <div class="modal-content" style="width:400px">
      <div class="modal-header">
        <h5 class="modal-title" id="exampleModalLabel">Recuperar Senha</h5>
        <button id="btn-fechar-rec" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
          <span aria-hidden="true">&times;</span>
        </button>
      </div>
      <form method="post" id="form-recuperar">
        <div class="modal-body">
          <select class="form-control" name="telefone_pais" id="telefone2_pais" style="width:100%; margin-bottom:6px;">
            <option value="br" data-ddi="55">Brasil (+55)</option>
          </select>
          <input type="hidden" name="telefone_ddi" id="telefone2_ddi" value="55">
          <input placeholder="Digite seu Telefone" class="form-control" type="text" name="telefone" id="telefone2" required autocomplete="off" inputmode="tel">
          <small id="telefone_ajuda_recuperar" class="text-muted" style="display:block; margin-top:6px;">Brasil (+55): digite normalmente com DDD.</small>
          <?php if (!empty($__slug)) { ?>
          <input type="hidden" name="loja" value="<?php echo h($__slug); ?>">
          <?php } ?>
          <br>
          <small><div id="mensagem-recuperar" align="center"></div></small>
        </div>
        <div class="modal-footer">
          <button type="submit" class="btn btn-primary">Recuperar</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- SCRIPTS -->
<script src="painel_cliente/js/jquery-1.11.1.min.js"></script>
<script src="painel_cliente/js/bootstrap.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11.10.6/dist/sweetalert2.all.min.js"></script>
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.11/jquery.mask.min.js"></script>
<script src="https://www.google.com/recaptcha/api.js" async defer></script>


<script>
function bbAcessoSomenteDigitos(valor){
    return (valor || '').toString().replace(/\D+/g, '');
}

function bbAcessoTemPrefixoInternacional(valor){
    valor = (valor || '').toString();
    return /^\s*\+/.test(valor) || /^\s*00/.test(valor);
}

function bbAcessoSanitizarInternacional(valor){
    valor = (valor || '').toString();
    valor = valor.replace(/[^\d\+\s\-\(\)]/g, '');
    valor = valor.replace(/^\s*00/, '+');
    valor = valor.replace(/(?!^)\+/g, '');
    valor = valor.replace(/\s{2,}/g, ' ');
    return valor.trim();
}

function bbAcessoMascaraBrasil($campo){
    if (!$campo.length) {
        return;
    }

    if (typeof $campo.unmask === 'function') {
        $campo.unmask();
    }

    if (typeof $campo.mask !== 'function') {
        return;
    }

    var comportamento = function(valor){
        var digitos = bbAcessoSomenteDigitos(valor);
        return digitos.length > 10 ? '(00) 00000-0000' : '(00) 0000-00009';
    };

    $campo.mask(comportamento($campo.val()), {
        onKeyPress: function(valor, e, field, options){
            $(field).mask(comportamento(valor), options);
        }
    });
}

function bbAcessoNormalizarTelefoneInternacional(valor, ddiSelecionado){
    var texto = bbAcessoSanitizarInternacional(valor);
    var digitos = bbAcessoSomenteDigitos(texto);
    var ddi = (ddiSelecionado || '').toString();

    if (texto === '') {
        return '';
    }

    if (bbAcessoTemPrefixoInternacional(texto)) {
        return bbAcessoSanitizarInternacional(texto);
    }

    if (ddi !== '') {
        if (digitos.indexOf(ddi) === 0) {
            return '+' + digitos;
        }
        return '+' + ddi + ' ' + digitos;
    }

    return bbAcessoSanitizarInternacional(texto);
}

function bbAcessoListaPaises(){
    return [
        {chave:'af', pais:'Afeganistão', ddi:'93'},
        {chave:'za', pais:'África do Sul', ddi:'27'},
        {chave:'al', pais:'Albânia', ddi:'355'},
        {chave:'de', pais:'Alemanha', ddi:'49'},
        {chave:'ao', pais:'Angola', ddi:'244'},
        {chave:'ar', pais:'Argentina', ddi:'54'},
        {chave:'au', pais:'Austrália', ddi:'61'},
        {chave:'at', pais:'Áustria', ddi:'43'},
        {chave:'be', pais:'Bélgica', ddi:'32'},
        {chave:'bo', pais:'Bolívia', ddi:'591'},
        {chave:'br', pais:'Brasil', ddi:'55'},
        {chave:'ca', pais:'Canadá', ddi:'1'},
        {chave:'cl', pais:'Chile', ddi:'56'},
        {chave:'cn', pais:'China', ddi:'86'},
        {chave:'co', pais:'Colômbia', ddi:'57'},
        {chave:'kr', pais:'Coreia do Sul', ddi:'82'},
        {chave:'dk', pais:'Dinamarca', ddi:'45'},
        {chave:'eg', pais:'Egito', ddi:'20'},
        {chave:'ae', pais:'Emirados Árabes Unidos', ddi:'971'},
        {chave:'es', pais:'Espanha', ddi:'34'},
        {chave:'us', pais:'Estados Unidos', ddi:'1'},
        {chave:'fr', pais:'França', ddi:'33'},
        {chave:'gr', pais:'Grécia', ddi:'30'},
        {chave:'nl', pais:'Países Baixos', ddi:'31'},
        {chave:'in', pais:'Índia', ddi:'91'},
        {chave:'ie', pais:'Irlanda', ddi:'353'},
        {chave:'it', pais:'Itália', ddi:'39'},
        {chave:'jp', pais:'Japão', ddi:'81'},
        {chave:'lu', pais:'Luxemburgo', ddi:'352'},
        {chave:'mx', pais:'México', ddi:'52'},
        {chave:'mz', pais:'Moçambique', ddi:'258'},
        {chave:'no', pais:'Noruega', ddi:'47'},
        {chave:'nz', pais:'Nova Zelândia', ddi:'64'},
        {chave:'py', pais:'Paraguai', ddi:'595'},
        {chave:'pe', pais:'Peru', ddi:'51'},
        {chave:'pl', pais:'Polônia', ddi:'48'},
        {chave:'pt', pais:'Portugal', ddi:'351'},
        {chave:'gb', pais:'Reino Unido', ddi:'44'},
        {chave:'do', pais:'República Dominicana', ddi:'1809'},
        {chave:'ru', pais:'Rússia', ddi:'7'},
        {chave:'sn', pais:'Senegal', ddi:'221'},
        {chave:'sg', pais:'Singapura', ddi:'65'},
        {chave:'ch', pais:'Suíça', ddi:'41'},
        {chave:'se', pais:'Suécia', ddi:'46'},
        {chave:'uy', pais:'Uruguai', ddi:'598'},
        {chave:'ve', pais:'Venezuela', ddi:'58'},
        {chave:'vn', pais:'Vietnã', ddi:'84'},
        {chave:'outro', pais:'Outro país (manual)', ddi:''}
    ];
}

function bbAcessoTextoOpcao(item){
    return item.ddi ? (item.pais + ' (+' + item.ddi + ')') : item.pais;
}

function bbAcessoPreencherSelect($select, selecionado){
    if (!$select.length) {
        return;
    }

    var lista = bbAcessoListaPaises();
    var html = '';

    for (var i = 0; i < lista.length; i++) {
        html += '<option value="' + lista[i].chave + '" data-ddi="' + lista[i].ddi + '">' + bbAcessoTextoOpcao(lista[i]) + '</option>';
    }

    $select.html(html);
    $select.val(selecionado || 'br');
}

function bbAcessoAtualizarAjuda($select, $ajuda){
    if (!$select.length || !$ajuda.length) {
        return;
    }

    var ddi = ($select.find('option:selected').data('ddi') || '').toString();

    if (($select.val() || '') === 'outro' || ddi === '') {
        $ajuda.text('Outro país: digite começando com +DDI ou selecione um país da lista.');
        return;
    }

    if (ddi === '55') {
        $ajuda.text('Brasil (+55): digite normalmente com DDD.');
        return;
    }

    $ajuda.text('País selecionado com DDI +' + ddi + '. Digite o número local ou já com +DDI.');
}

function bbAcessoAplicarModoTelefone($campo, $select, $hiddenDdi, ajustarValor){
    if (!$campo.length || !$select.length) {
        return;
    }

    var ddi = ($select.find('option:selected').data('ddi') || '').toString();
    var pais = ($select.val() || '').toString();
    var valor = ($campo.val() || '').toString();

    if ($hiddenDdi.length) {
        $hiddenDdi.val(ddi);
    }

    if (pais === 'br' || ddi === '55') {
        var digitos = bbAcessoSomenteDigitos(valor);

        if (bbAcessoTemPrefixoInternacional(valor) && digitos.indexOf('55') === 0 && (digitos.length === 12 || digitos.length === 13)) {
            digitos = digitos.substring(2);
            $campo.val(digitos);
        }

        $campo.attr('placeholder', 'Seu Telefone');
        bbAcessoMascaraBrasil($campo);
        return;
    }

    if (typeof $campo.unmask === 'function') {
        $campo.unmask();
    }

    $campo.attr('placeholder', ddi !== '' ? ('+' + ddi + ' número local') : 'Digite com +DDI');

    if (ajustarValor === true && valor.trim() !== '') {
        $campo.val(bbAcessoNormalizarTelefoneInternacional(valor, ddi));
    } else {
        $campo.val(bbAcessoSanitizarInternacional(valor));
    }
}

function bbAcessoPrepararAntesDoEnvio($campo, $select, $hiddenDdi){
    if (!$campo.length || !$select.length) {
        return;
    }

    var ddi = ($select.find('option:selected').data('ddi') || '').toString();
    var pais = ($select.val() || '').toString();
    var valor = ($campo.val() || '').toString();

    if ($hiddenDdi.length) {
        $hiddenDdi.val(ddi);
    }

    if (pais === 'br' || ddi === '55') {
        $campo.val(bbAcessoSomenteDigitos(valor));
        return;
    }

    $campo.val(bbAcessoNormalizarTelefoneInternacional(valor, ddi));
}

$(document).ready(function(){
    var $telefonePais = $('#telefone_pais');
    var $telefone = $('#telefone');
    var $telefoneDdi = $('#telefone_ddi');
    var $telefoneAjuda = $('#telefone_ajuda_login');

    var $telefone2Pais = $('#telefone2_pais');
    var $telefone2 = $('#telefone2');
    var $telefone2Ddi = $('#telefone2_ddi');
    var $telefone2Ajuda = $('#telefone_ajuda_recuperar');

    bbAcessoPreencherSelect($telefonePais, 'br');
    bbAcessoPreencherSelect($telefone2Pais, 'br');

    bbAcessoAtualizarAjuda($telefonePais, $telefoneAjuda);
    bbAcessoAtualizarAjuda($telefone2Pais, $telefone2Ajuda);

    bbAcessoAplicarModoTelefone($telefone, $telefonePais, $telefoneDdi, false);
    bbAcessoAplicarModoTelefone($telefone2, $telefone2Pais, $telefone2Ddi, false);

    $telefonePais.on('change', function(){
        bbAcessoAtualizarAjuda($telefonePais, $telefoneAjuda);
        bbAcessoAplicarModoTelefone($telefone, $telefonePais, $telefoneDdi, true);
    });

    $telefone2Pais.on('change', function(){
        bbAcessoAtualizarAjuda($telefone2Pais, $telefone2Ajuda);
        bbAcessoAplicarModoTelefone($telefone2, $telefone2Pais, $telefone2Ddi, true);
    });

    $telefone.on('input paste blur', function(){
        if (($telefonePais.val() || '') !== 'br') {
            $telefone.val(bbAcessoSanitizarInternacional($telefone.val()));
        }
    });

    $telefone2.on('input paste blur', function(){
        if (($telefone2Pais.val() || '') !== 'br') {
            $telefone2.val(bbAcessoSanitizarInternacional($telefone2.val()));
        }
    });

    $('form[action="autenticar_cliente.php"]').on('submit', function(){
        bbAcessoPrepararAntesDoEnvio($telefone, $telefonePais, $telefoneDdi);
    });

    $('#form-recuperar').submit(function(event){
        event.preventDefault();

        bbAcessoPrepararAntesDoEnvio($telefone2, $telefone2Pais, $telefone2Ddi);

        var formData = new FormData(this);

        $.ajax({
            url: "recuperar-senha_cliente.php",
            type: 'POST',
            data: formData,
            cache: false,
            contentType: false,
            processData: false,
            success: function(mensagem){
                let msg = mensagem.trim().toLowerCase();
                if(
                    msg === "recuperado com sucesso" ||
                    msg.includes('senha foi enviada para o whatsapp') ||
                    msg.includes('senha foi enviada para o whatsapp ou email')
                ){
                    $telefone2.val('');
                    Swal.fire({
                        icon: 'success',
                        title: 'Recuperado com Sucesso!',
                        text: 'Sua senha foi enviada para o WhatsApp ou E-mail.',
                        confirmButtonColor: '#3085d6',
                        timer: 2000,
                        showConfirmButton: false,
                        willClose: () => {
                            try { $('#exampleModal').modal('hide'); } catch(e){}
                            $('.modal-backdrop').remove();
                            $('body').removeClass('modal-open');
                            $telefone.focus();
                            bbAcessoAplicarModoTelefone($telefone2, $telefone2Pais, $telefone2Ddi, false);
                        }
                    });
                } else {
                    Swal.fire({
                        icon: 'error',
                        title: 'Erro!',
                        text: mensagem,
                        confirmButtonColor: '#e32424'
                    });
                }
            },
            error: function(){
                Swal.fire({
                    icon: 'error',
                    title: 'Erro!',
                    text: 'Erro de conexão ou servidor!',
                    confirmButtonColor: '#e32424'
                });
            }
        });
    });
});
</script>
</body>
</html>