<?php
require_once __DIR__ . '/../tenant_context.php';


// PEGAR O TOTALDE CONTAS A RECEBER VENCIDAS
$query = $pdo->query("SELECT * from receber where data_venc < curDate() and pago != 'Sim' order by id asc");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_contas_receber = @count($res);

// PEGAR O TOTALDE CONTAS A PAGAR VENCIDAS
$query = $pdo->query("SELECT * from pagar where data_venc < curDate() and pago != 'Sim' order by id asc");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_contas_pagar = @count($res);

?>
<!DOCTYPE HTML>
<html lang="pt-BR">

<head>
  <meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="viewport" content="width=device-width, initial-scale=1, minimum-scale=1, maximum-scale=1, viewport-fit=cover" />
  <title><?php echo $nome_sistema ?></title>
  <link rel="stylesheet" type="text/css" href="styles/bootstrap.css">
  <link rel="stylesheet" type="text/css" href="fonts/bootstrap-icons.css">
  <link rel="stylesheet" type="text/css" href="styles/style.css">
  <link rel="stylesheet" type="text/css" href="styles/fab.css">
  <link rel="stylesheet" href="css/fab.css">
  <link rel="preconnect" href="https://fonts.gstatic.com">

  <link rel="stylesheet" href="css/swiper.css">
  <link rel="stylesheet" href="css/novos_estilos.css">
  <link
    href="https://fonts.googleapis.com/css2?family=Inter:wght@500;600;700;800&family=Roboto:wght@400;500;700&display=swap"
    rel="stylesheet">
  <link rel="stylesheet" type="text/css" href="https://cdn.jsdelivr.net/npm/toastify-js/src/toastify.min.css">
  <link rel="manifest" href="../_manifest.json">
  <meta id="theme-check" name="theme-color" content="#FFFFFF">
  <link rel="apple-touch-icon" sizes="180x180" href="../app/icons/icon-192x192.png">
  <link rel="icon" href="/sistema/img/icone.png" type="image/x-icon" />


  	<!-- CKEDITOR -->
	<link rel="stylesheet" href="ckeditor5/ckeditor5.css">
	<link rel="stylesheet" href="ckeditor5/style.css">
	<script src="ckeditor5/ckeditor5.umd.js" crossorigin></script>
	<script src="ckeditor5/translations/pt-br.umd.js" crossorigin></script>


  <link rel="stylesheet" type="text/css" href="fonts/css/fontawesome-all.min.css">

  <link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />

  <script>
    window.BB_TENANT_SLUG = <?php echo json_encode($bb_app_slug, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES); ?>;
  </script>
</head>



<body class="theme-light">

  <div id="preloader">
    <div class="spinner-border color-highlight" role="status"></div>
  </div>

  <div id="page">

    <!-- Header -->
    <div class="header-bar header-fixed header-app header-bar-detached">
      <a data-bs-toggle="offcanvas" data-bs-target="#menu-main" href="#"><i class="bi bi-list color-theme"></i></a>
      <a href="index" class="header-title color-theme"><img src="../../sistema/img/icone.png" height="50px" onclick="navigateToPage(event, 'index')"></a>
      <a href="#" data-bs-toggle="offcanvas" data-bs-target="#menu-color"><i
          class="bi bi-palette-fill font-13 color-highlight"></i></a>
      <a class="<?php echo $pagar ?>" href="#" data-bs-toggle="offcanvas" data-bs-target="#menu-pagar"><em
          class="badge gradient-red ms-1 text-white "><?php echo $total_contas_pagar ?></em><i
          class="font-14 fa fa-dollar"></i></a>
      <a class="<?php echo $receber ?>" href="#" data-bs-toggle="offcanvas" data-bs-target="#menu-receber"><em
          class="badge gradient-green ms-1 text-white "><?php echo $total_contas_receber ?></em><i
          class="font-14 fa fa-dollar"></i></a>
      <a href="#" class="show-on-theme-light" data-toggle-theme><i class="bi bi-moon-fill font-13"></i></a>
      <a href="#" class="show-on-theme-dark" data-toggle-theme><i
          class="bi bi-lightbulb-fill color-yellow-dark font-13"></i></a>
    </div>

    <!-- Menu Themas-->
    <div id="menu-main" data-menu-active="nav-homes" data-menu-load="menu-lateral.php" style="width:280px;"
      class="offcanvas offcanvas-start offcanvas-detached rounded-m">
    </div>


    <!-- Menu Destaques-->
    <div id="menu-color" data-menu-load="menu-cores.php" style="height:340px"
      class="offcanvas offcanvas-bottom offcanvas-detached rounded-m">
    </div>

    <!-- Contas a pagar-->
    <div id="menu-pagar" data-menu-load="menu-pagar.php" style="height:400px;"
      class="offcanvas offcanvas-top offcanvas-detached rounded-m">
    </div>

    <!-- Contas a receber-->
    <div id="menu-receber" data-menu-load="menu-receber.php" style="height:400px;"
      class="offcanvas offcanvas-top offcanvas-detached rounded-m">
    </div>
    
