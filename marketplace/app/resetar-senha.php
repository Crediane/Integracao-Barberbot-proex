<?php
require_once __DIR__ . '/tenant_context.php';

$emailReset = trim((string)($_GET['email'] ?? ''));
$tokenReset = trim((string)($_GET['token'] ?? ''));

if ($emailReset === '' || $tokenReset === '') {
    header('Location: ' . $bb_app_login_url, true, 302);
    exit;
}

$statement = $pdo->prepare('SELECT id FROM usuarios WHERE email = :email AND token = :token LIMIT 1');
$statement->execute([
    ':email' => $emailReset,
    ':token' => $tokenReset,
]);
$usuarioReset = $statement->fetch(PDO::FETCH_ASSOC);

if (!$usuarioReset) {
    header('Location: ' . $bb_app_login_url, true, 302);
    exit;
}

$_SESSION['temp_reset_email'] = $emailReset;
$_SESSION['temp_reset_token'] = $tokenReset;
?>

<!DOCTYPE HTML>
<html lang="pt-BR">

<head>
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
	<link rel="apple-touch-icon" sizes="180x180" href="painel/app/icons/icon-192x192.png">
	<link rel="icon" type="image/png" href="/sistema/img/icone.png" sizes="32x32">
</head>

<body class="theme-light">

	<div id="preloader">
		<div class="spinner-border color-highlight" role="status"></div>
	</div>

	<div id="page">


		<!-- MODAL RESETAR SENHA-->
		<div class="page-content pb-0 mb-0">

			<div class="card card-style m-0 bg-transparent shadow-0 bg-10 rounded-0" data-card-height="cover">
				<div class="card-center">
					<div class="card card-style">
						<div class="content">
							<h2 class="text-center font-800 font-30 mb-2"><br><img src="/sistema/img/logo.png" width="70%" alt="Logo"></h2><br>


							<form method="post" id="form-recuperar">
								<input type="hidden" name="loja" value="<?php echo htmlspecialchars($bb_app_slug, ENT_QUOTES, 'UTF-8'); ?>">
								<div class="form-custom form-label form-icon mb-3">
									<i class="bi bi-lock font-12"></i>
									<input type="password" name="senha" id="senha" class="form-control rounded-xs"
										placeholder="Digite uma Nova Senha" autocomplete="new-password" required />
									<label class="color-theme">Nova Senha</label>
									<span>(Obrigatório)</span>
								</div>

								<div class="form-custom form-label form-icon mb-3">
									<i class="bi bi-lock font-12"></i>
									<input type="password" name="re_senha" id="re_senha" class="form-control rounded-xs"
										placeholder="Repita Sua Nova Senha" autocomplete="new-password" required />
									<label class="color-theme">Repetir Senha</label>
									<span>(Obrigatório)</span>
								</div>


								<input type="hidden" name="token" id="token" value="<?php echo htmlspecialchars($tokenReset, ENT_QUOTES, 'UTF-8'); ?>">

								<input type="hidden" name="email" id="email" value="<?php echo htmlspecialchars($emailReset, ENT_QUOTES, 'UTF-8'); ?>">



								<button class="btn btn-full gradient-blue rounded-xs text-uppercase font-700 w-100 btn-s mt-4 mb-2"
									type="submit">ALTERAR
									SENHA</button><br>

							</form>

						</div>
					</div>
				</div>
			</div>

		</div>
		<!-- End of Page Content-->


	</div>
	<!--End of Page ID-->




	<script src="painel/scripts/bootstrap.min.js"></script>
	<script src="painel/scripts/custom.js"></script>
</body>


<?php require_once("alertas.php"); ?>

<script src="//cdnjs.cloudflare.com/ajax/libs/jquery/3.2.1/jquery.min.js"></script>


 <script type="text/javascript">
	$("#form-recuperar").submit(function (event) {

		$('#mensagem-recuperar').text("Alterando!!")

		event.preventDefault();
		var formData = new FormData(this);

		$.ajax({
			url: "alterar-senha.php",
			type: 'POST',
			data: formData,

			success: function (mensagem) {
				//alert(mensagem)
				$('#mensagem-recuperar').text('');
				$('#mensagem-recuperar').removeClass()
				if (mensagem.trim() == "Senha alterada com Sucesso") {
					//$('#btn-fechar-rec').click();					
					$('#senha').val('');
					$('#re_senha').val('');
					$('#btn_sucess_rec_senha').click();
					window.location=<?php echo json_encode($bb_app_login_url, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;		

				} else {

					$('#mensagem-recuperar').addClass('text-danger')
					$('#btn_senha_incorreta').click();
					
				}


			},

			cache: false,
			contentType: false,
			processData: false,

		});

	});
</script>




<script src="painel/js/jquery-3.3.1.min.js"></script>
<script src="painel/js/jquery.validate.min.js" ></script>
<script src="painel/js/swiper.min.js"></script>
<script src="painel/js/jquery.custom.js"></script>
<script type="text/javascript" src="https://cdn.jsdelivr.net/npm/toastify-js"></script>




<script type="text/javascript">
    function toast(mensagem, cor){        
        Toastify({
          text: mensagem,
          duration: 3000,
          destination: "https://github.com/apvarun/toastify-js",
          newWindow: true,
          close: true,
          gravity: "top", // `top` or `bottom`
          position: "center", // `left`, `center` or `right`
          stopOnFocus: true, // Prevents dismissing of toast on hover
          style: {
            background: cor, //verde #24c76a    vermelha #d4483b
          },
          onClick: function(){} // Callback after click
        }).showToast();
    }
</script>