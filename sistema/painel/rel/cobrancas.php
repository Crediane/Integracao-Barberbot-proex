<?php
require_once __DIR__ . '/../../conexao.php';
include __DIR__ . '/data_formatada.php';

if (!function_exists('bb_normalize_report_date')) {
    function bb_normalize_report_date($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return $value;
        }
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $value)) {
            $parts = explode('/', $value);
            return $parts[2] . '-' . $parts[1] . '-' . $parts[0];
        }
        return '';
    }
}

if (!function_exists('bb_safe_date_br')) {
    function bb_safe_date_br($value) {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00') {
            return '';
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return '';
        }
        return date('d/m/Y', $ts);
    }
}

if (!function_exists('bb_safe_day')) {
    function bb_safe_day($value) {
        $value = trim((string) $value);
        if ($value === '' || $value === '0000-00-00') {
            return '';
        }
        $ts = strtotime($value);
        if ($ts === false) {
            return '';
        }
        return date('d', $ts);
    }
}

$dataFinal = bb_normalize_report_date($_GET['dataFinal'] ?? '');
$dataInicial = bb_normalize_report_date($_GET['dataInicial'] ?? '');
$id_usu = isset($_GET['id_usu']) ? (int) $_GET['id_usu'] : 0;
$nivel_usu = isset($_GET['nivel_usu']) ? (string) $_GET['nivel_usu'] : '';

if ($dataInicial === '' && !empty($data_hoje_sistema)) {
    $dataInicial = (string) $data_hoje_sistema;
}
if ($dataFinal === '' && !empty($data_hoje_sistema)) {
    $dataFinal = (string) $data_hoje_sistema;
}
if ($dataInicial === '') {
    $dataInicial = date('Y-m-d');
}
if ($dataFinal === '') {
    $dataFinal = date('Y-m-d');
}
if ($dataFinal < $dataInicial) {
    $tmp = $dataInicial;
    $dataInicial = $dataFinal;
    $dataFinal = $tmp;
}

$dataInicialF = bb_safe_date_br($dataInicial);
$dataFinalF = bb_safe_date_br($dataFinal);

$datas = '';
if ($dataInicial === $dataFinal) {
    $datas = 'Período de Apuração: ' . $dataInicialF;
} else {
    $datas = 'Período de Apuração: ' . $dataInicialF . ' à ' . $dataFinalF;
}
?>
<!DOCTYPE html>
<html>
<head>

<style>

@import url('https://fonts.cdnfonts.com/css/tw-cen-mt-condensed');
@page { margin: 145px 20px 25px 20px; }
#header { position: fixed; left: 0px; top: -110px; bottom: 100px; right: 0px; height: 35px; text-align: center; padding-bottom: 100px; }
#content {margin-top: 0px;}
#footer { position: fixed; left: 0px; bottom: -60px; right: 0px; height: 80px; }
#footer .page:after {content: counter(page, my-sec-counter);}
body {font-family: 'Tw Cen MT', sans-serif;}

.marca{
	position:fixed;
	left:50;
	top:130;
	width:80%;
	opacity:10%;
}

.text-danger{
	color:red;
}

.text-primary{
	color:blue;
}

</style>

</head>
<body>

<div id="header" >
	<div style="border-style: solid; font-size: 10px; height: 50px;">
		<table style="width: 100%; border: 0px solid #ccc;">
			<tr>
				<td style="border: 1px; solid #000; width: 20%; text-align: left;">
					<img style="margin-top: 5px; margin-left: 7px;" id="imag" src="<?php echo $url_sistema ?>/sistema/img/logo_rel.jpg" width="120px">
				</td>
				<td style="width: 20%; text-align: left; font-size: 13px;"></td>
				<td style="width: 5%; text-align: center; font-size: 13px;"></td>
				<td style="width: 55%; text-align: right; font-size: 9px;padding-right: 10px;">
					<b><big>RELATÓRIO DE COBRANÇAS RECORRENTES </big></b><br>
					<?php echo mb_strtoupper($datas) ?>
					<br>
					<?php echo mb_strtoupper((string) ($data_hoje ?? '')) ?>
				</td>
			</tr>		
		</table>
	</div>

<br>

<table id="cabecalhotabela" style="border-bottom-style: solid; font-size: 10px; margin-bottom:10px; width: 100%; table-layout: fixed;">
	<thead>
		<tr id="cabeca" style="margin-left: 0px; background-color:#CCC">
			<td style="width:30%">CLIENTE</td>
			<td style="width:10%">R$ VALOR</td>
			<td style="width:10%">PARCELAS</td>
			<td style="width:10%">DATA</td>					
			<td style="width:10%">DIA VENC</td>
			<td style="width:15%">FREQUÊNCIA PGTO</td>
		</tr>
	</thead>
</table>
</div>

<div id="footer" class="row">
<hr style="margin-bottom: 0;">
	<table style="width:100%;">
		<tr style="width:100%;">
			<td style="width:60%; font-size: 10px; text-align: left;"><?php echo $nome_sistema ?> / Telefone: <?php echo $telefone_sistema ?> / Email: <?php echo $email_sistema ?></td>
			<td style="width:40%; font-size: 10px; text-align: right;"><p class="page">Página  </p></td>
		</tr>
	</table>
</div>

<div id="content" style="margin-top: 0;">
	<table style="width: 100%; table-layout: fixed; font-size:10px; text-transform: uppercase;">
		<thead>
			<tbody>
				<?php
				if ($nivel_usu === 'Administrador') {
				    $query = $pdo->query("SELECT * FROM cobrancas WHERE data >= '$dataInicial' AND data <= '$dataFinal' ORDER BY id DESC");
				} else {
				    $query = $pdo->query("SELECT * FROM cobrancas WHERE data >= '$dataInicial' AND data <= '$dataFinal' AND usuario = '$id_usu' ORDER BY id DESC");
				}
				$res = $query->fetchAll(PDO::FETCH_ASSOC);
				$linhas = count($res);

				if ($linhas > 0) {
				    for ($i = 0; $i < $linhas; $i++) {
				        $id = isset($res[$i]['id']) ? (int) $res[$i]['id'] : 0;
				        $valor = isset($res[$i]['valor']) && is_numeric($res[$i]['valor']) ? (float) $res[$i]['valor'] : 0;
				        $parcelas = isset($res[$i]['parcelas']) && is_numeric($res[$i]['parcelas']) ? (int) $res[$i]['parcelas'] : 0;
				        $data_venc = $res[$i]['data_venc'] ?? '';
				        $data = $res[$i]['data'] ?? '';
				        $cliente = isset($res[$i]['cliente']) ? (int) $res[$i]['cliente'] : 0;
				        $multa = isset($res[$i]['multa']) && is_numeric($res[$i]['multa']) ? (float) $res[$i]['multa'] : 0;
				        $usuario = isset($res[$i]['usuario']) ? (int) $res[$i]['usuario'] : 0;
				        $frequencia = trim((string) ($res[$i]['frequencia'] ?? ''));

				        $data_vencF = bb_safe_day($data_venc);
				        $dataF = bb_safe_date_br($data);
				        $valorF = number_format($valor, 2, ',', '.');
				        $multaF = number_format($multa, 2, ',', '.');

				        $query2 = $pdo->query("SELECT * FROM clientes WHERE id = '$cliente'");
				        $res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
				        $nome_cliente = isset($res2[0]['nome']) ? (string) $res2[0]['nome'] : '';

				        $query2 = $pdo->query("SELECT * FROM usuarios WHERE id = '$usuario'");
				        $res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
				        $nome_usuario = isset($res2[0]['nome']) ? (string) $res2[0]['nome'] : '';

				        $classe_debito = '';
				        $query2 = $pdo->query("SELECT * FROM receber WHERE referencia = 'Cobranca' AND id_ref = '$id' AND pago = 'Não' AND data_venc < curDate()");
				        $res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
				        if (count($res2) > 0) {
				            $classe_debito = 'text-danger';
				        }

				        $query2 = $pdo->query("SELECT * FROM receber WHERE referencia = 'Cobranca' AND id_ref = '$id' AND pago = 'Sim'");
				        $res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
				        $parcelas_pagas = count($res2);

				        if ($parcelas > 1) {
				            $parcelas_nome = $parcelas_pagas . ' / ' . $parcelas;
				        } else {
				            $parcelas_nome = 'Recorrente';
				        }
				?>
				<tr>
					<td style="width:30%;" class="<?php echo $classe_debito ?>"><?php echo $nome_cliente ?></td>
					<td style="width:10%">R$ <?php echo $valorF ?></td>
					<td style="width:10%"><?php echo $parcelas_nome ?></td>
					<td style="width:10%"><?php echo $dataF ?></td>
					<td style="width:10%;"><?php echo $data_vencF ?></td>
					<td style="width:15%;"><?php echo $frequencia ?></td>
				</tr>
				<?php
				    }
				}
				?>
			</tbody>
		</thead>
	</table>
</div>

<hr>
<table>
	<thead>
		<tbody>
			<tr>
				<td style="font-size: 10px; width:180px; text-align: right;"> </td>
				<td style="font-size: 10px; width:180px; text-align: right;"></td>
				<td style="font-size: 10px; width:180px; text-align: right;"></td>
				<td style="font-size: 10px; width:180px; text-align: right;">TOTAL COBRANÇAS: <?php echo (int) $linhas ?></td>
			</tr>
		</tbody>
	</thead>
</table>

</body>
</html>
