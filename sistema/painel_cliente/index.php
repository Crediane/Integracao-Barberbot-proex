<?php

require_once __DIR__ . '/verificar.php';

// Tenta carregar o security.php sem quebrar se estiver em outro diretório.
$sec_paths = array(
    __DIR__ . '/security.php',
    __DIR__ . '/../security.php',
    __DIR__ . '/../../security.php',
);
foreach ($sec_paths as $sec_path) {
    if (is_file($sec_path)) {
        require_once $sec_path;
        break;
    }
}

$bb_scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
if (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string)$_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https') {
    $bb_scheme = 'https';
}
$bb_host = (string)($_SERVER['HTTP_HOST'] ?? '');
$bb_base = $bb_host !== '' ? ($bb_scheme . '://' . $bb_host) : '';
$bb_uri_path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';

$bb_loja_slug = (string)$bb_cliente_slug;
$bb_public_painel_cliente_path = '/loja/' . rawurlencode($bb_loja_slug) . '/painel_cliente';
$url_painel_cliente_public = $bb_base . $bb_public_painel_cliente_path;

$link_pc_home = $url_painel_cliente_public . '/';
$link_pc_planos = $url_painel_cliente_public . '/planos';
$link_pc_combos = $url_painel_cliente_public . '/combos';
$link_pc_receber = $url_painel_cliente_public . '/receber';
$link_pc_logout = $url_painel_cliente_public . '/logout.php';

// Canoniza somente navegação GET. POST e AJAX continuam no endpoint solicitado.
$bb_is_get = (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'GET');
$bb_is_ajax = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower((string)$_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');
if ($bb_is_get && !$bb_is_ajax && preg_match('~^/sistema/painel_cliente(?:/index\.php)?/?$~i', $bb_uri_path)) {
    $bb_pag_destino = strtolower((string)($_GET['pag'] ?? ''));
    $bb_pag_destino = (string)preg_replace('/[^a-z0-9_-]/', '', $bb_pag_destino);
    if (!in_array($bb_pag_destino, array('planos', 'combos', 'receber'), true)) {
        $bb_pag_destino = '';
    }

    $bb_destino = $bb_public_painel_cliente_path . '/';
    if ($bb_pag_destino !== '') {
        $bb_destino .= $bb_pag_destino . '/';
    }

    if (!headers_sent()) {
        header('Location: ' . $bb_destino);
    } else {
        echo '<script>window.location=' . json_encode($bb_destino, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';</script>';
    }
    exit();
}

$id_usuario = (int)$bb_cliente_id;
$nome_usuario = '';
$cpf_usuario = '';
$telefone_usuario = '';
$endereco_usuario = '';
$cartoes = 0;
$quantidade_cartoes = 0;
$quantidade_combos_ativos = 0;
$saldo_total_combos = 0;

$stmt = $pdo->prepare('SELECT * FROM clientes WHERE id = :id LIMIT 1');
$stmt->execute(array(':id' => $id_usuario));
$cliente_logado = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$cliente_logado) {
    $destino = bb_pc_url_acesso($bb_loja_slug, 'sessao-expirada');
    if (!headers_sent()) {
        header('Location: ' . $destino);
    } else {
        echo '<script>window.location=' . json_encode($destino, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';</script>';
    }
    exit();
}

$nome_usuario = (string)($cliente_logado['nome'] ?? '');
$cpf_usuario = (string)($cliente_logado['cpf'] ?? '');
$telefone_usuario = (string)($cliente_logado['telefone'] ?? '');
$endereco_usuario = (string)($cliente_logado['endereco'] ?? '');
$cartoes = (int)($cliente_logado['cartoes'] ?? 0);

if (!function_exists('bb_pc_tabela_existe')) {
    function bb_pc_tabela_existe(PDO $pdo, $tabela)
    {
        try {
            $st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.TABLES
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :tabela");
            $st->execute(array(':tabela' => $tabela));
            return ((int)$st->fetchColumn() > 0);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('bb_pc_coluna_existe')) {
    function bb_pc_coluna_existe(PDO $pdo, $tabela, $coluna)
    {
        try {
            $st = $pdo->prepare("SELECT COUNT(*) FROM INFORMATION_SCHEMA.COLUMNS
                WHERE TABLE_SCHEMA = DATABASE()
                AND TABLE_NAME = :tabela
                AND COLUMN_NAME = :coluna");
            $st->execute(array(':tabela' => $tabela, ':coluna' => $coluna));
            return ((int)$st->fetchColumn() > 0);
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (bb_pc_tabela_existe($pdo, 'config') && bb_pc_coluna_existe($pdo, 'config', 'quantidade_cartoes')) {
    try {
        $qConfig = $pdo->query('SELECT quantidade_cartoes FROM config ORDER BY id DESC LIMIT 1');
        $quantidade_cartoes = (int)($qConfig->fetchColumn() ?: 0);
    } catch (Throwable $e) {
        $quantidade_cartoes = 0;
    }
}

$modulo_combo_ok =
    bb_pc_tabela_existe($pdo, 'assinaturas') &&
    bb_pc_tabela_existe($pdo, 'itens_assinaturas') &&
    bb_pc_tabela_existe($pdo, 'servicos') &&
    bb_pc_tabela_existe($pdo, 'assinaturas_consumos') &&
    bb_pc_coluna_existe($pdo, 'servicos', 'combo_ativo') &&
    bb_pc_coluna_existe($pdo, 'servicos', 'combo_qtd_sessoes') &&
    bb_pc_coluna_existe($pdo, 'itens_assinaturas', 'servico');

if ($modulo_combo_ok && $id_usuario > 0) {
    $tem_ativo_assinaturas = bb_pc_coluna_existe($pdo, 'assinaturas', 'ativo');
    $tem_data_vencimento = bb_pc_coluna_existe($pdo, 'assinaturas', 'data_vencimento');
    $expr_vencimento = $tem_data_vencimento ? 'COALESCE(a.data_vencimento, a.vencimento)' : 'a.vencimento';

    $sqlCombos = "SELECT a.id, s.combo_qtd_sessoes
        FROM assinaturas a
        INNER JOIN itens_assinaturas ia ON a.item = ia.id
        INNER JOIN servicos s ON ia.servico = s.id
        WHERE a.cliente = :cliente
          AND a.pago = 'Sim'
          AND (a.cancelado IS NULL OR a.cancelado != 'Sim')
          AND s.combo_ativo = 'Sim'
          AND ({$expr_vencimento} IS NULL OR {$expr_vencimento} = '0000-00-00' OR {$expr_vencimento} >= :data_hoje)";

    if ($tem_ativo_assinaturas) {
        $sqlCombos .= " AND a.ativo = 'Sim'";
    }
    $sqlCombos .= ' ORDER BY a.id DESC';

    try {
        $qCombos = $pdo->prepare($sqlCombos);
        $qCombos->execute(array(':cliente' => $id_usuario, ':data_hoje' => date('Y-m-d')));
        $resCombos = $qCombos->fetchAll(PDO::FETCH_ASSOC);
        $qConsumos = $pdo->prepare('SELECT COUNT(*) FROM assinaturas_consumos WHERE assinatura = :assinatura AND cliente = :cliente');

        foreach ($resCombos as $comboRow) {
            $id_assinatura_combo = (int)$comboRow['id'];
            $total_sessoes_combo = (int)$comboRow['combo_qtd_sessoes'];
            $qConsumos->execute(array(':assinatura' => $id_assinatura_combo, ':cliente' => $id_usuario));
            $restantes_combo = max(0, $total_sessoes_combo - (int)$qConsumos->fetchColumn());
            if ($restantes_combo > 0) {
                $quantidade_combos_ativos++;
                $saldo_total_combos += $restantes_combo;
            }
        }
    } catch (Throwable $e) {
        $quantidade_combos_ativos = 0;
        $saldo_total_combos = 0;
    }
}

$pag = strtolower((string)($_GET['pag'] ?? ''));
$pag = (string)preg_replace('/[^a-z0-9_-]/', '', $pag);

if ($pag === '' && preg_match('~/(?:painel_cliente|painel-cliente)/(agendamentos|planos|combos|receber)(?:/|$)~i', $bb_uri_path, $bb_pag_match)) {
    $pag = strtolower((string)$bb_pag_match[1]);
}

$pag_permitidas = array('agendamentos', 'planos', 'combos', 'receber');
if (!in_array($pag, $pag_permitidas, true)) {
    $pag = 'agendamentos';
}

$planos = ($pag === 'planos') ? 'active' : '';
$combos = ($pag === 'combos') ? 'active' : '';
$receber = ($pag === 'receber') ? 'active' : '';
$data_atual = date('Y-m-d');
$mes_atual = date('m');
$ano_atual = date('Y');
$data_mes = $ano_atual . '-' . $mes_atual . '-01';
$data_ano = $ano_atual . '-01-01';
$partesInicial = explode('-', $data_atual);
$dataDiaInicial = $partesInicial[2] ?? '';
$dataMesInicial = $partesInicial[1] ?? '';

?>

<!DOCTYPE HTML>
<?php
// ✅ Multi-tenant favicon (usa /sistema/tenants/<slug>/img/favicon.png quando existir)
$bb_slug = '';
if (!empty($_GET['loja'])) {
  $bb_slug = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string)$_GET['loja']));
} else {
  $bb_uri = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
  if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $bb_uri, $m)) {
    $bb_slug = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string)$m[1]));
  } elseif (!empty($_SESSION['tenant_slug'])) {
    $bb_slug = strtolower(preg_replace('/[^a-z0-9_-]/', '', (string)$_SESSION['tenant_slug']));
  }
}
$bb_tenant_fav_url = '/sistema/img/favicon.png';
if ($bb_slug !== '') {
  $bb_fav_fs  = __DIR__ . '/../tenants/' . $bb_slug . '/img/favicon.png';
  if (is_file($bb_fav_fs)) $bb_tenant_fav_url = '/sistema/tenants/' . $bb_slug . '/img/favicon.png';
}
?>
<html>
<head>
	<title><?php echo htmlspecialchars((string)$nome_sistema, ENT_QUOTES, 'UTF-8'); ?></title>
	<link rel="icon" type="image/png" href="<?php echo htmlspecialchars($bb_tenant_fav_url, ENT_QUOTES, 'UTF-8'); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
		<base href="<?php echo $url_painel_cliente_public . '/'; ?>">
	<meta http-equiv="Content-Type" content="text/html; charset=utf-8" />
	<meta name="keywords" content="" />
	<script type="application/x-javascript"> addEventListener("load", function() { setTimeout(hideURLbar, 0); }, false); function hideURLbar(){ window.scrollTo(0,1); } </script>

	<!-- Bootstrap Core CSS -->
	<link href="css/bootstrap.css" rel='stylesheet' type='text/css' />

	<!-- Custom CSS -->
	<link href="css/style.css" rel='stylesheet' type='text/css' />

	<!-- font-awesome icons CSS -->
	<link href="css/font-awesome.css" rel="stylesheet"> 
	<!-- //font-awesome icons CSS-->

	<!-- side nav css file -->
	<link href='css/SidebarNav.min.css' media='all' rel='stylesheet' type='text/css'/>
	<!-- //side nav css file -->

	<link rel="stylesheet" href="css/monthly.css">

	<!-- js-->
	<script src="js/jquery-1.11.1.min.js"></script>
	<script>window.BB_TENANT_SLUG = <?php echo json_encode($bb_loja_slug, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;</script>
	<script src="js/modernizr.custom.js"></script>

	<!--webfonts-->
	<link href="//fonts.googleapis.com/css?family=PT+Sans:400,400i,700,700i&amp;subset=cyrillic,cyrillic-ext,latin-ext" rel="stylesheet">
	<!--//webfonts--> 

	<!-- chart -->
	<script src="js/Chart.js"></script>
	<!-- //chart -->

	<!-- Metis Menu -->
	<script src="js/metisMenu.min.js"></script>
	<script src="js/custom.js"></script>
	<link href="css/custom.css" rel="stylesheet">
	<!--//Metis Menu -->
	<style>
		#chartdiv {
			width: 100%;
			height: 295px;
		}
	</style>
	<!--pie-chart --><!-- index page sales reviews visitors pie chart -->
	<script src="js/pie-chart.js" type="text/javascript"></script>
	<script type="text/javascript">

		$(document).ready(function () {
			$('#demo-pie-1').pieChart({
				barColor: '#2dde98',
				trackColor: '#eee',
				lineCap: 'round',
				lineWidth: 8,
				onStep: function (from, to, percent) {
					$(this.element).find('.pie-value').text(Math.round(percent) + '%');
				}
			});

			$('#demo-pie-2').pieChart({
				barColor: '#8e43e7',
				trackColor: '#eee',
				lineCap: 'butt',
				lineWidth: 8,
				onStep: function (from, to, percent) {
					$(this.element).find('.pie-value').text(Math.round(percent) + '%');
				}
			});

			$('#demo-pie-3').pieChart({
				barColor: '#e32424',
				trackColor: '#eee',
				lineCap: 'square',
				lineWidth: 8,
				onStep: function (from, to, percent) {
					$(this.element).find('.pie-value').text(Math.round(percent) + '%');
				}
			});
		});

	</script>
	<!-- //pie-chart --><!-- index page sales reviews visitors pie chart -->

	<link rel="stylesheet" type="text/css" href="DataTables/datatables.min.css"/>
 	<script type="text/javascript" src="DataTables/datatables.min.js"></script>

</head> 
<body class="cbp-spmenu-push">
	<div class="main-content">
		<div class="cbp-spmenu cbp-spmenu-vertical cbp-spmenu-left" id="cbp-spmenu-s1">
			<!--left-fixed -navigation-->
			<aside class="sidebar-left" style="overflow: scroll; height:100%; scrollbar-width: thin;">
				<nav class="navbar navbar-inverse" >
					<div class="navbar-header">
						<button type="button" class="navbar-toggle collapsed" data-toggle="collapse" data-target=".collapse" aria-expanded="false" id="showLeftPush2">
							<span class="sr-only">Toggle navigation</span>
							<span class="icon-bar"></span>
							<span class="icon-bar"></span>
							<span class="icon-bar"></span>
						</button>
						<h1><a class="navbar-brand" href="<?php echo $link_pc_home ?>"><span class="fa fa-area-chart"></span> Sistema<span class="dashboard_text"><?php echo htmlspecialchars((string)$nome_sistema, ENT_QUOTES, 'UTF-8'); ?></span></a></h1>
					</div>
					<div class="collapse navbar-collapse" id="bs-example-navbar-collapse-1">
						<ul class="sidebar-menu">
							<li class="header">MENU DE NAVEGAÇÃO</li>

							<li class="treeview <?php echo ($pag === 'agendamentos') ? 'active' : ''; ?>">
								<a href="<?php echo $link_pc_home ?>">
									<i class="fa fa-calendar-o"></i> <span>Agendamentos</span>
								</a>
							</li>

							<li class="treeview <?php echo @$planos ?>">
								<a href="<?php echo $link_pc_planos ?>">
									<i class="fa fa-credit-card-alt"></i> <span>Planos / Assinaturas</span>
								</a>
							</li>

							<li class="treeview <?php echo @$combos ?>">
								<a href="<?php echo $link_pc_combos ?>">
									<i class="fa fa-gift"></i> <span>Meus Combos</span>
									<?php if ((int)$saldo_total_combos > 0) { ?>
									<small class="label pull-right bg-blue"><?php echo (int)$saldo_total_combos ?></small>
									<?php } ?>
								</a>
							</li>

							<li class="treeview <?php echo @$receber ?>">
								<a href="<?php echo $link_pc_receber ?>">
									<i class="fa fa-usd"></i> <span>Meus Pagamentos</span>
								</a>
							</li>

						</ul>
					</div>
					<!-- /.navbar-collapse -->
				</nav>
			</aside>
		</div>
		<!--left-fixed -navigation-->
		
		<!-- header-starts -->
		<div class="sticky-header header-section ">
			<div class="header-left">
				<!--toggle button start-->
				<button id="showLeftPush" data-toggle="collapse" data-target=".collapse"><i class="fa fa-bars"></i></button>
				<!--toggle button end-->
				<div class="profile_details_left"><!--notifications of menu start -->
					<ul class="nofitications-dropdown">
					</ul>
					<div class="clearfix"> </div>
				</div>
				
			</div>
			<div class="header-right">
				<div class="profile_details">		
					<ul>
						<li class="dropdown profile_details_drop">
							<a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false">
								<div class="profile_img">	
									<span class="prfil-img"><img src="img/perfil/sem-foto.jpg" alt="" width="50" height="50"> </span> 
									<div class="user-name esc">
										<p><?php echo htmlspecialchars($nome_usuario, ENT_QUOTES, 'UTF-8'); ?></p>
										<span>Cliente</span>
									</div>
									<i class="fa fa-angle-down lnr"></i>
									<i class="fa fa-angle-up lnr"></i>
									<div class="clearfix"></div>	
								</div>	
							</a>
							<ul class="dropdown-menu drp-mnu">								
								<li> <a href="" data-toggle="modal" data-target="#modalPerfil"><i class="fa fa-suitcase"></i> Editar Perfil</a> </li> 
								<li> <a href="<?php echo $link_pc_logout ?>"><i class="fa fa-sign-out"></i> Sair</a> </li>
							</ul>
						</li>
					</ul>
				</div>
				<div class="clearfix"> </div>				
			</div>
			<div class="clearfix"> </div>	
		</div>
		<!-- //header-ends -->

		<!-- main content start-->
		<div id="page-wrapper">
			<?php require_once __DIR__ . '/paginas/' . $pag . '.php'; ?>
		</div>

		<!--footer-->
		<div class="footer" >
			<div class="row">
				<?php 
				for($i=1; $i<=$quantidade_cartoes; $i++){ 
					if($cartoes >= $i){
						$valor = 0;
						$opacity = 1;
					}else{
						$valor = 1;
						$opacity = 0.4;		
					}
				?>
				<div style="display:inline-block;" align="center" >
					<img src="/images/favicon.png" width="35px" style="filter: grayscale(<?php echo $valor ?>); filter: opacity(<?php echo $opacity ?>)">
				</div>
				<?php } ?>
			</div>

			<div align="center"><small><small>Você possui <?php echo (int)$cartoes ?> de <?php echo (int)$quantidade_cartoes ?> cartões Fidelidade</small></small></div>
			<div align="center"><small><small>Combos ativos: <?php echo (int)$quantidade_combos_ativos ?> | Saldo disponível: <?php echo (int)$saldo_total_combos ?> sessão(ões)</small></small></div>
		</div>
		<!--//footer-->
	</div>

	<!-- Classie --><!-- for toggle left push menu script -->
	<script src="js/classie.js"></script>
	<script>
		var menuLeft = document.getElementById( 'cbp-spmenu-s1' ),
			showLeftPush = document.getElementById( 'showLeftPush' ),
			body = document.body;
			
		showLeftPush.onclick = function() {
			classie.toggle( this, 'active' );
			classie.toggle( body, 'cbp-spmenu-push-toright' );
			classie.toggle( menuLeft, 'cbp-spmenu-open' );
			disableOther( 'showLeftPush' );
		};
		
		function disableOther( button ) {
			if( button !== 'showLeftPush' ) {
				classie.toggle( showLeftPush, 'disabled' );
			}
		}

		showLeftPush2 = document.getElementById( 'showLeftPush2' ),
		
		showLeftPush2.onclick = function() {
			classie.toggle( this, 'active' );
			classie.toggle( body, 'cbp-spmenu-push-toright' );
			classie.toggle( menuLeft, 'cbp-spmenu-open' );
			disableOther2( 'showLeftPush2' );
		};

		function disableOther2( button ) {
			if( button !== 'showLeftPush2' ) {
				classie.toggle( showLeftPush2, 'disabled' );
			}
		}
	</script>
	<!-- //Classie --><!-- //for toggle left push menu script -->

	<!--scrolling js-->
	<script src="js/jquery.nicescroll.js"></script>
	<script src="js/scripts.js"></script>
	<!--//scrolling js-->
	
	<!-- side nav js -->
	<script src='js/SidebarNav.min.js' type='text/javascript'></script>
	<script>
		$('.sidebar-menu').SidebarNav()
	</script>
	<!-- //side nav js -->
	
	<!-- Bootstrap Core JavaScript -->
	<script src="js/bootstrap.js"> </script>
	<!-- //Bootstrap Core JavaScript -->
	
</body>
</html>

<!-- Mascaras JS -->
<script type="text/javascript" src="js/mascaras.js"></script>

<!-- Ajax para funcionar Mascaras JS -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery.mask/1.14.11/jquery.mask.min.js"></script> 

<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>

<style type="text/css">
	.select2-selection__rendered {
		line-height: 36px !important;
		font-size:16px !important;
		color:#666666 !important;
	}
	.select2-selection {
		height: 36px !important;
		font-size:16px !important;
		color:#666666 !important;
	}
</style>  

<!-- Modal Perfil-->
<div class="modal fade" id="modalPerfil" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
	<div class="modal-dialog" role="document">
		<div class="modal-content">
			<div class="modal-header">
				<h4 class="modal-title" id="exampleModalLabel">Editar Perfil</h4>
				<button id="btn-fechar-perfil" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
					<span aria-hidden="true" >&times;</span>
				</button>
			</div>
			<form method="post" id="form-perfil">
				<input type="hidden" name="loja" value="<?php echo htmlspecialchars($bb_loja_slug, ENT_QUOTES, 'UTF-8'); ?>">
				<div class="modal-body">

					<div class="row">
						<div class="col-md-12">
							<div class="form-group">
								<label for="exampleInputEmail1">Nome</label>
								<input type="text" class="form-control" id="nome-perfil" name="nome" placeholder="Nome" value="<?php echo htmlspecialchars($nome_usuario, ENT_QUOTES, 'UTF-8'); ?>" required>    
							</div> 	
						</div>
					</div>

					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label for="exampleInputEmail1">Telefone</label>
								<input type="text" class="form-control" id="telefone-perfil" name="telefone" placeholder="Telefone" value="<?php echo htmlspecialchars($telefone_usuario, ENT_QUOTES, 'UTF-8'); ?>" >    
							</div> 	
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label for="exampleInputEmail1">CPF</label>
								<input type="text" class="form-control" id="cpf-perfil" name="cpf" placeholder="CPF" value="<?php echo htmlspecialchars($cpf_usuario, ENT_QUOTES, 'UTF-8'); ?>">    
							</div> 	
						</div>
					</div>

					<div class="row">
						<div class="col-md-6">
							<div class="form-group">
								<label for="exampleInputEmail1">Senha</label>
								<input type="password" class="form-control" id="senha-perfil" name="senha" placeholder="Senha" value="" required>    
							</div> 	
						</div>
						<div class="col-md-6">
							<div class="form-group">
								<label for="exampleInputEmail1">Confirmar Senha</label>
								<input type="password" class="form-control" id="conf-senha-perfil" name="conf_senha" placeholder="Confirmar Senha" required>    
							</div> 	
						</div>
					</div>

					<div class="row">
						<div class="col-md-12">
							<div class="form-group">
								<label for="exampleInputEmail1">Endereço</label>
								<input type="text" class="form-control" id="endereco-perfil" name="endereco" placeholder="Rua X Número 1 Bairro xxx" value="<?php echo htmlspecialchars($endereco_usuario, ENT_QUOTES, 'UTF-8'); ?>" >    
							</div> 	
						</div>
					</div>

					<input type="hidden" name="id" value="<?php echo $id_usuario ?>">

					<br>
					<small><div id="mensagem-perfil" align="center"></div></small>
				</div>
				<div class="modal-footer">      
					<button type="submit" class="btn btn-primary">Editar Perfil</button>
				</div>
			</form>
		</div>
	</div>
</div>

<script type="text/javascript">
	$(document).ready(function() {		
		$('.sel15').select2({	
			dropdownParent: $('#RelComissoes')		
		});

		$('.selcli').select2({	
			dropdownParent: $('#RelEntradas')		
		});
	});
</script>

<script type="text/javascript">
	$("#form-perfil").submit(function (event) {

		event.preventDefault();
		var formData = new FormData(this);

		$.ajax({
			url: <?php echo json_encode($url_painel_cliente_public . '/editar-perfil.php', JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>,
			type: 'POST',
			data: formData,

			success: function (mensagem) {
				$('#mensagem-perfil').text('');
				$('#mensagem-perfil').removeClass()
				if (mensagem.trim() == "Editado com Sucesso") {

					$('#btn-fechar-perfil').click();
					location.reload();			
					
				} else {

					$('#mensagem-perfil').addClass('text-danger')
					$('#mensagem-perfil').text(mensagem)
				}
			},

			cache: false,
			contentType: false,
			processData: false,
		});
	});
</script>
