<?php
require_once __DIR__ . '/tenant_context.php';

// Acesso legado /app/?loja={slug}: redireciona para a URL pública canônica.
if (
    strtoupper((string)($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'GET'
    && preg_match('~^/app(?:/index\.php)?/?$~i', $bb_app_request_path)
) {
    $queryString = $_GET;
    unset($queryString['loja']);

    $destination = $bb_app_login_url;
    if (!empty($queryString)) {
        $destination .= '?' . http_build_query($queryString);
    }

    header('Location: ' . $destination, true, 302);
    exit;
}

$mensagem_sessao = (string)($_SESSION['msg'] ?? '');
unset($_SESSION['msg']);

?>
<!DOCTYPE HTML>
<html lang="pt-BR">

<head>

	<style>
:root { --brand:#16a34a; } /* verde do seu gradiente; ajuste se quiser */

/* Camada do splash */
#splash{
  position:fixed; inset:0; z-index:9999;
  display:flex; align-items:center; justify-content:center;
  background:linear-gradient(180deg,#0b1016,#0f172a);
  color:#fff; overflow:hidden;
}
#splash.hide{ opacity:0; pointer-events:none; transition:opacity .45s ease; }

#splash .wrap{ text-align:center; padding:24px; }
#splash .logo{ opacity:0; transform:scale(.9); animation:pop .9s cubic-bezier(.2,.8,.2,1) forwards; }
#splash .title{ margin-top:16px; font:800 20px/1.1 'Inter', system-ui; letter-spacing:.4px;
  opacity:0; animation:fade .8s .35s both; }
#splash .bar{ height:3px; width:180px; margin:22px auto 0; border-radius:999px; overflow:hidden;
  background:rgba(255,255,255,.15); position:relative; }
#splash .bar::before{
  content:""; position:absolute; inset:0; width:40%;
  background:linear-gradient(90deg, transparent, #fff, transparent);
  transform:translateX(-100%); animation:load 1.4s infinite;
}
#splash .tip{ font-size:12px; opacity:.6; margin-top:10px; }

@keyframes pop{ 0%{opacity:0; transform:scale(.9)} 60%{opacity:1; transform:scale(1.05)} 100%{transform:scale(1)} }
@keyframes fade{ to{opacity:1} }
@keyframes load{ 0%{transform:translateX(-100%)} 60%{transform:translateX(160%)} 100%{transform:translateX(160%)} }

/* Dark/Light: se quiser herdar da sua theme */
body.theme-light #splash{ background:linear-gradient(180deg,#111827,#0f172a); }
</style>


	<meta charset="utf-8">
	<link rel="icon" type="image/png" href="images/favicon.png" sizes="32x32">
	
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
	<meta name="viewport"
		content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, viewport-fit=cover" />
	<title><?php echo $nome_sistema ?></title>
	<link rel="stylesheet" type="text/css" href="painel/styles/bootstrap.css">
	<link rel="stylesheet" type="text/css" href="painel/fonts/bootstrap-icons.css">
	<link rel="stylesheet" type="text/css" href="painel/styles/style.css">
	<link rel="preconnect" href="https://fonts.gstatic.com">
	<link
		href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800&family=Roboto:wght@400;500;700&display=swap"
		rel="stylesheet">
	<link rel="manifest" href="_manifest.json">
	<meta id="theme-check" name="theme-color" content="#FFFFFF">
	<link rel="apple-touch-icon" sizes="180x180" href="app/icons/icon-192x192.png">
	<link rel="stylesheet" href="painel/css/swiper.css">
	<link href="https://fonts.googleapis.com/css?family=Roboto:100,300,400,900" rel="stylesheet">
	<link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">

	<link rel="icon" type="image/png" href="../sistema/img/icone.png" sizes="32x32">

</head>



<body class="theme-light">

	<!-- SPLASH -->
<div id="splash">
  <div class="wrap">
    <img class="logo" src="../sistema/img/icone.png" alt="Logo" width="160" height="160">
    <div class="title"><?php echo $nome_sistema ?></div>
    <div class="bar"></div>
    <div class="tip">Carregando…</div>
  </div>
</div>


	<div id="preloader">
		<div class="spinner-border color-highlight" role="status"></div>
	</div>


<!-- MENU DE INSTALAÇÃO DO APP-->
<div class="offcanvas offcanvas-bottom rounded-m offcanvas-detached" id="menu-install-pwa-ios">
  <div class="content">
    <img src="app/icons/icon-128x128.png" alt="img" width="80" class="rounded-l mx-auto my-4">
    <h1 class="text-center font-800 font-20">Adicionar à tela inicial</h1>
    <p class="boxed-text-xl">
      Instale o Sistema na sua tela inicial e acesse-o como um aplicativo comum. Abra o menu do seu navegador e toque em
      "Adicionar à
      Tela inicial".
    </p>
    <a href="#"
      class="pwa-dismiss close-menu gradient-blue shadow-bg shadow-bg-s btn btn-s btn-full text-uppercase font-700  mt-n2"
      data-bs-dismiss="offcanvas">Mais tarde</a>
  </div>
</div>

<div class="offcanvas offcanvas-bottom rounded-m offcanvas-detached" id="menu-install-pwa-android">
  <div class="content">
    <img src="app/icons/icon-128x128.png" alt="img" width="80" class="rounded-m mx-auto my-4">
    <h1 class="text-center font-700 font-20">Instalar</h1>
    <p class="boxed-text-l">
      Instale o Sistema na sua tela inicial para desfrutar de uma experiência única e nativa.
    </p>
    <a href="#"
      class="pwa-install btn btn-m rounded-s text-uppercase font-900 gradient-highlight shadow-bg shadow-bg-s btn-full">Adicionar
      na Tela Inicial</a><br>
    <a href="#" data-bs-dismiss="offcanvas"
      class="pwa-dismiss close-menu color-theme text-uppercase font-900 opacity-50 font-11 text-center d-block mt-n1">Mais
      tarde</a>
  </div>
</div>


	<div id="page">



		<!-- MODAL RESETAR SENHA-->
		<div class="page-content pb-0 mb-0">
			<div class="card card-style m-0 bg-transparent shadow-0 bg-10 rounded-0" data-card-height="cover">
				<div class="card-center">
					<div class="card card-style">
						<div class="content">
							<h2 class="text-center font-800 font-30 mb-2"><br><img src="../sistema/img/logo.png" width="70%"></h2><br>
							<form method="post" action="autenticar.php">
								<input type="hidden" name="loja" value="<?php echo htmlspecialchars($bb_app_slug, ENT_QUOTES, 'UTF-8'); ?>">
								<div class="form-custom form-label form-icon mb-3">
									<i class="bi bi-person-circle font-14"></i>
									<input type="email" class="form-control rounded-xs" id="usuario" name="usuario"
										placeholder="Seu E-mail" autocomplete="username" required />
									<label class="color-theme">E-mail</label>
								</div>
								<div class="form-custom form-label form-icon mb-3">
									<i class="bi bi-lock-fill font-12"></i>
									<input type="password" name="senha" class="form-control rounded-xs" id="password-field"
										placeholder="Sua Senha" autocomplete="current-password" required />
									<label class="color-theme">Senha</label>
								</div>
								<button class="btn btn-full gradient-green rounded-xs text-uppercase font-700 w-100 btn-s mt-4 mb-3"
									type="submit">Logar</button><br>
								<a href="#" class="d-flex pb-2" data-trigger-switch="salvar_acesso">
									<div class="align-self-center">
										<h6 class="mb-0 font-12">Salvar Acesso</h6>
									</div>
									<div class="ms-auto align-self-center">
										<div class="form-switch android-switch switch-green switch-m">
											<input type="checkbox" value="Sim" class="android-input" id="salvar_acesso" name="salvar">
											<label class="custom-control-label"></label>
										</div>
									</div>
								</a>
							</form>
							<div class="d-flex">
								<div>
									<a class="color-theme opacity-30 font-12" href="#" data-bs-toggle="offcanvas"
										data-bs-target="#menu-forgot" class="list-group-item">
										<div>Recuperar Senha</div>
									</a>
								</div>
							</div>
						</div>
					</div>
				</div>
			</div>
		</div>



		<!-- End of Page Content-->

	</div>
	<!--End of Page ID-->

	<!--RECUPERAR SENHA-->
	<div class="offcanvas offcanvas-modal rounded-m offcanvas-detached bg-theme" style="width:400px" id="menu-forgot">
		<div class="content">
			<div class="d-flex pb-2">
				<div class="align-self-center text-uppercase">
					<span style="margin-left: 120px !important;" class="font-14 color-highlight font-700 ">RECUPERAR SENHA</span>
				</div>
				<div class="align-self-center ms-auto">
					<button style="border: none; background: transparent; margin-right: 12px" data-bs-dismiss="offcanvas"
						id="btn-fechar-rec" aria-label="Close" data-bs-dismiss="modal" type="button"><i
							class="bi bi-x-circle-fill color-red-dark font-18 me-n4"></i></button>
				</div>
			</div>
			<form method="post" id="form-recuperar">
				<input type="hidden" name="loja" value="<?php echo htmlspecialchars($bb_app_slug, ENT_QUOTES, 'UTF-8'); ?>">
				<div class="form-custom form-label form-border form-icon mb-3 bg-transparent">
					<i class="bi bi-at font-14"></i>
					<input type="email" name="email" value="" id="email-recuperar" class="form-control rounded-xs"placeholder="Digite seu Email" required />
					<label class="color-theme">Resetar Senha</label>
				</div>
				<button class="btn btn-full gradient-red rounded-xs text-uppercase font-700 w-100 btn-s mt-4" type="submit"
					name="submit" id="submitforgot">RESETAR SENHA</button>
			</form>
			<div class="row">
				<div class="col-12 text-start">
						<p class="font-11 color-theme opacity-60 pt-3" align="center">Verifique seu email, ou seu whatsapp para redefinir a senha!</p>
				</div>
			</div>
		</div>
	</div>




	<script src="painel/scripts/bootstrap.min.js"></script>
	<script src="painel/scripts/custom.js"></script>
	<script src="painel/js/jquery-3.3.1.min.js"></script>
	<script src="painel/js/jquery.validate.min.js"></script>
	<script src="painel/js/swiper.min.js"></script>
	<script src="painel/js/jquery.custom.js"></script>
	<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>



<!-- Base Js File -->
<script src="painel/js/base.js"></script>

</body>

<?php require_once __DIR__ . '/alertas.php'; ?>

<script type="text/javascript">
	$(document).ready(function () {
		var storageEmailKey = <?php echo json_encode($bb_app_storage_email_key, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
		var savedEmail = localStorage.getItem(storageEmailKey) || '';

		// Remove dados legados inseguros que armazenavam senha e ID no navegador.
		localStorage.removeItem('senha_usu');
		localStorage.removeItem('id_usu');

		$('#usuario').val(savedEmail);
		$('#salvar_acesso').prop('checked', savedEmail !== '');

		var mensagemSessao = <?php echo json_encode($mensagem_sessao, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
		if (mensagemSessao === 'Dados Incorretos!') {
			$('#btn_erro').click();
		} else if (mensagemSessao === 'Seu Acesso foi desativado!') {
			$('#btn_alert').click();
		} else if (mensagemSessao !== '') {
			alert(mensagemSessao);
		}
	});
</script>



<script type="text/javascript">
	$("#form-recuperar").submit(function (event) {
		//$('#mensagem-recuperar').text('Enviando!!');
		$('#submitforgot').hide();
		event.preventDefault();
		var formData = new FormData(this);
		$.ajax({
			url: "recuperar-senha.php",
			type: 'POST',
			data: formData,
			success: function (mensagem) {
				//$('#mensagem-recuperar').text('');
				//$('#mensagem-recuperar').removeClass()
				if (mensagem.trim() == "Recuperado com Sucesso") {
					$('#btn-fechar-rec').click();
					//$('#menu-forgot').modal('hide');
					$('#btn_sucess_sen_env').click();
					$('#email-recuperar').val('');
					//$('#mensagem-recuperar').addClass('text-success')
					//toast('Link para troca de senha no Email ou no seu whatsapp!', 'verde');
				} else {
					$('#btn_email_n_cad').click();
					//$('#mensagem-recuperar').addClass('text-danger')
					//toast(mensagem, 'vermelha');
				}
				$('#submitforgot').show();
			},
			cache: false,
			contentType: false,
			processData: false,
		});
	});
</script>





<script>
(function(){
  const splash = document.getElementById('splash');

  // Se já estiver instalado (standalone), ainda queremos mostrar NOSSO splash
  // mas sem atrasar; então não aguardamos service worker aqui.
  const isStandalone = matchMedia('(display-mode: standalone)').matches || navigator.standalone === true;

  const MIN_MS = 600;
  const start  = performance.now();

  const done = () => {
    const elapsed = performance.now() - start;
    const wait = Math.max(0, MIN_MS - elapsed);
    setTimeout(() => splash?.classList.add('hide'), wait);
  };

  const waits = [];

  // Primeiro paint + imagens necessárias
  waits.push(new Promise(res=>{
    if (document.readyState === 'complete') res();
    else addEventListener('load', res, {once:true});
  }));

  // Fonts (se suportado) – não bloqueie no standalone
  if (!isStandalone && 'fonts' in document) waits.push(document.fonts.ready);

  const logo = document.querySelector('#splash .logo');
  if (logo && !logo.complete){
    waits.push(new Promise(res=>logo.addEventListener('load', res, {once:true})));
  }

  // Opcional: esperar SW quando não instalado (primeiro acesso no browser)
  if (!isStandalone && 'serviceWorker' in navigator){
    let to;
    waits.push(Promise.race([
      navigator.serviceWorker.ready.catch(()=>{}),
      new Promise(r=>to=setTimeout(r, 800))
    ]).finally(()=>clearTimeout(to)));
  }

  Promise.all(waits).then(done);
  setTimeout(done, 2500); // fallback duro
})();
</script>
