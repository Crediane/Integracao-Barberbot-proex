<?php
require_once __DIR__ . '/../_context_bootstrap.php';
// ============================================================================
// Arquivo: /public_html/sistema/painel/paginas/agendamentos/listar-horarios.php (AJAX)
// PADRÃO "BLINDADO MARKETPLACE" (SEM MUDAR A LÓGICA):
// - Detecta SLUG (POST/GET/URI/REFERER) ANTES do conexao.php
// - Passa $_GET['loja'] para o bootstrap escolher o TENANT correto
// ============================================================================
@chdir(__DIR__);

if (!function_exists('bb_clean_slug')) {
  function bb_clean_slug($v) {
    $v = strtolower(trim((string)$v));
    return preg_replace('/[^a-z0-9_-]/', '', $v);
  }
}

$__bb_reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$__bb_refPath = '';
$__bb_slug = '';
if (!empty($_POST['loja'])) {
  $__bb_slug = bb_clean_slug($_POST['loja']);
} elseif (!empty($_GET['loja'])) {
  $__bb_slug = bb_clean_slug($_GET['loja']);
} elseif (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath, $m1)) {
  $__bb_slug = bb_clean_slug($m1[1]);
} elseif ($__bb_refPath && preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_refPath, $m2)) {
  $__bb_slug = bb_clean_slug($m2[1]);
}

if ($__bb_slug !== '') {
  $_GET['loja'] = $__bb_slug;
}

require_once __DIR__ . '/../../../conexao.php';

// Libera lock de sessão para não travar o painel (AJAX)
if (session_status() === PHP_SESSION_ACTIVE) {
  @session_write_close();
}

$usuario = @$_SESSION['id'];

// ✅ garante string (evita NULL)
$funcionario = isset($_POST['funcionario']) ? $_POST['funcionario'] : '';
$data        = isset($_POST['data']) ? $_POST['data'] : '';

$hora_rec = '';
$hora_atual = date('H:i:s');
$hoje = date('Y-m-d');

// ✅ evita strtotime(NULL) se data vier vazia
if($data == ''){
	$data = $hoje;
}

//verificar se possui essa data nos dias bloqueio geral
if($funcionario == 20){
    $query = $pdo->query("SELECT * FROM dias_bloqueio where (usuario = '$funcionario' or usuario = '24') and data = '$data'");
    $res = $query->fetchAll(PDO::FETCH_ASSOC);
    if(@count($res) > 0){
    		echo 'Este Profissional não irá trabalhar nesta Data, selecione outra data ou escolhar outro Profissional!';
    		exit();
    }
} else {
    $query = $pdo->query("SELECT * FROM dias_bloqueio where usuario = '$funcionario'  and data = '$data'");
    $res = $query->fetchAll(PDO::FETCH_ASSOC);
    if(@count($res) > 0){
    		echo 'Este Profissional não irá trabalhar nesta Data, selecione outra data ou escolhar outro Profissional!';
    		exit();
    }
}

$diasemana = array("Domingo", "Segunda-Feira", "Terça-Feira", "Quarta-Feira", "Quinta-Feira", "Sexta-Feira", "Sabado");
$diasemana_numero = date('w', strtotime($data));
$dia_procurado = $diasemana[$diasemana_numero];

//percorrer os dias da semana que ele trabalha
$query = $pdo->query("SELECT * FROM dias where funcionario = '$funcionario' and dia = '$dia_procurado'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);

$inicio = '';
$final = '';
$inicio_almoco = '';
$final_almoco = '';

if(count($res) != 0){
	// ✅ garante string (evita NULL)
	$inicio        = $res[0]['inicio'] ?? '';
	$final         = $res[0]['final'] ?? '';
	$inicio_almoco = $res[0]['inicio_almoco'] ?? '';
	$final_almoco  = $res[0]['final_almoco'] ?? '';
}

$query = $pdo->query("SELECT * FROM usuarios where id = '$funcionario'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$intervalo = $res[0]['intervalo'] ?? 0;

// ✅ se não tem horários configurados, evita while com strtotime(NULL)
if($inicio == '' || $final == ''){
	echo 'Não temos mais horários disponíveis com este funcionário para essa data!';
	exit();
}
?>

<style>
	.lista-horarios-grid .horario-col {
		margin-bottom: .5rem;
	}

	.lista-horarios-grid .horario-card {
		display: flex;
		align-items: center;
		min-height: 44px;
		padding: .45rem .65rem;
		border: 1px solid #dee2e6;
		border-radius: .5rem;
		background: #fff;
	}

	.lista-horarios-grid .horario-card .form-check-input {
		margin-top: 0;
		margin-right: .45rem;
	}

	.lista-horarios-grid .horario-card .form-check-label {
		margin-bottom: 0;
		line-height: 1.2;
		cursor: pointer;
	}

	@media (max-width: 575.98px) {
		.lista-horarios-grid .horario-col {
			padding-left: .35rem;
			padding-right: .35rem;
		}
	}
</style>

<div class="row gx-2 lista-horarios-grid">

	<?php 
	$i = 0;
	$slot_index = 0;
	while (strtotime($inicio) <= strtotime($final)){

		// ✅ só compara almoço se ambos existirem (evita strtotime(NULL))
		if($inicio_almoco != '' && $final_almoco != '' &&
		   strtotime($inicio) >= strtotime($inicio_almoco) && strtotime($inicio) < strtotime($final_almoco)){

			$hora_minutos = strtotime("+$intervalo minutes", strtotime($inicio));			
			$inicio = date('H:i:s', $hora_minutos);

		}else{
			
			$hora = $inicio;
			$horaF = date("H:i", strtotime($hora));
			$dataH = '';
			$slot_index++;
			$input_id = 'hora_' . $funcionario . '_' . $slot_index;

			$esconder  = '';
			$checado   = '';
			$esconder2 = '';
			$checado2  = '';
			$esconder3 = '';
			$checado3  = '';
			$ocultar   = '';

			//validar horario
			$query2 = $pdo->query("SELECT * FROM agendamentos where data = '$data' and hora = '$hora' and funcionario = '$funcionario'");
			$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
			$total_reg2 = @count($res2);

			// ✅ evita strtotime(NULL) quando não existe res2[0]['hora']
			if($total_reg2 > 0 && ($res2[0]['hora'] ?? '') != '' && strtotime($res2[0]['hora']) == strtotime($inicio)){
				$esconder = 'text-danger';
				$checado = 'disabled';
			}

			if(strtotime($hora) < strtotime($hora_atual) and strtotime($data) == strtotime($hoje)){
				$esconder2 = 'text-danger';
				$checado2 = 'disabled';
				$ocultar = 'ocultar';
			}else{
				$i += 1;
				
				//VERIFICAR NA TABELA HORARIOS AGD SE TEM O HORARIO NESSA DATA
				$query_agd = $pdo->query("SELECT * FROM horarios_agd where data = '$data' and funcionario = '$funcionario' and horario = '$hora'");
				$res_agd = $query_agd->fetchAll(PDO::FETCH_ASSOC);
				if(@count($res_agd) > 0){
					$esconder3 = 'text-danger';
					$checado3 = 'disabled';
				}

			}

			if(strtotime($dataH) != strtotime($data) and $dataH != "" and $dataH != "null"){
				continue;
			}	
			?>

			<div class="col-6 col-sm-4 col-md-3 col-lg-2 horario-col <?php echo $ocultar ?>">
				<div class="form-check horario-card h-100">
				  <input
					class="form-check-input"
					type="radio"
					name="hora"
					id="<?php echo $input_id ?>"
					value="<?php echo $hora ?>"
					<?php echo $checado ?>
					<?php echo $checado2 ?>
					<?php echo $checado3 ?>
				  >
				  <label class="form-check-label fw-normal <?php echo $esconder ?> <?php echo $esconder2 ?> <?php echo $esconder3 ?>" for="<?php echo $input_id ?>">
				    <?php echo $horaF ?>
				  </label>
				</div>
			</div>

			<?php 
			$hora_minutos = strtotime("+$intervalo minutes", strtotime($inicio));			
			$inicio = date('H:i:s', $hora_minutos);

		}
	}
	
	if($i == 0){
		echo 'Não temos mais horários disponíveis com este funcionário para essa data!';
	}
	?>

</div>
