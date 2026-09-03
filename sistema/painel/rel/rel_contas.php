<?php 
if($token_rel != 'A5030'){
echo '<script>window.location="../../"</script>';
exit();
}

if (!function_exists('bb_rel_contas_slug_limpo')) {
    function bb_rel_contas_slug_limpo($valor)
    {
        $valor = strtolower(trim((string)$valor));
        return preg_replace('/[^a-z0-9_-]/', '', $valor);
    }
}

if (!function_exists('bb_rel_contas_data_valida_rel')) {
    function bb_rel_contas_data_valida_rel($valor)
    {
        return is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor);
    }
}

if (!function_exists('bb_rel_contas_data_br_para_iso')) {
    function bb_rel_contas_data_br_para_iso($valor)
    {
        $valor = trim((string)$valor);
        if (preg_match('/^\d{2}\/\d{2}\/\d{4}$/', $valor)) {
            [$d, $m, $y] = explode('/', $valor);
            return $y . '-' . $m . '-' . $d;
        }
        return $valor;
    }
}

if (!function_exists('bb_rel_contas_resolver_slug')) {
    function bb_rel_contas_resolver_slug()
    {
        $reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $refPath = !empty($_SERVER['HTTP_REFERER']) ? (parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: '') : '';

        if (!empty($_GET['loja'])) {
            return bb_rel_contas_slug_limpo($_GET['loja']);
        }

        if (!empty($_POST['loja'])) {
            return bb_rel_contas_slug_limpo($_POST['loja']);
        }

        if (!empty($_SESSION['tenant_slug'])) {
            return bb_rel_contas_slug_limpo($_SESSION['tenant_slug']);
        }

        if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $reqPath, $m)) {
            return bb_rel_contas_slug_limpo($m[1]);
        }

        if ($refPath !== '' && preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $refPath, $m2)) {
            return bb_rel_contas_slug_limpo($m2[1]);
        }

        return '';
    }
}

if (!function_exists('bb_rel_contas_reaplicar_tenant')) {
    function bb_rel_contas_reaplicar_tenant()
    {
        $slug = bb_rel_contas_resolver_slug();
        if ($slug === '') {
            return false;
        }

        if (empty($_GET['loja'])) {
            $_GET['loja'] = $slug;
        }

        $banco = (string)($_SESSION['tenant_db'] ?? '');
        $servidor = (string)($_SESSION['tenant_host'] ?? 'localhost');
        $usuario = (string)($_SESSION['tenant_user'] ?? '');
        $senha = (string)($_SESSION['tenant_pass'] ?? '');

        if ($banco === '' || $usuario === '') {
            return false;
        }

        try {
            $pdoTenant = new PDO(
                "mysql:host={$servidor};dbname={$banco};charset=utf8mb4",
                $usuario,
                $senha,
                [
                    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    PDO::ATTR_EMULATE_PREPARES => false,
                ]
            );

            $GLOBALS['pdo'] = $pdoTenant;

            try {
                $qConf = $pdoTenant->query('SELECT * FROM config LIMIT 1');
                $dConf = $qConf->fetch(PDO::FETCH_ASSOC);
                if ($dConf) {
                    $GLOBALS['nome_sistema'] = $dConf['nome'] ?? ($GLOBALS['nome_sistema'] ?? 'BarberBot');
                    $GLOBALS['whatsapp_sistema'] = $dConf['telefone_whatsapp'] ?? ($dConf['telefone'] ?? ($GLOBALS['whatsapp_sistema'] ?? ''));
                }
            } catch (Throwable $e) {
                // não bloqueia o relatório
            }

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}

$dataInicial = bb_rel_contas_data_br_para_iso($dataInicial ?? '');
$dataFinal = bb_rel_contas_data_br_para_iso($dataFinal ?? '');
$dataInicial = bb_rel_contas_data_valida_rel($dataInicial) ? $dataInicial : date('Y-m-d');
$dataFinal = bb_rel_contas_data_valida_rel($dataFinal) ? $dataFinal : $dataInicial;
$pago = isset($pago) ? trim((string)$pago) : '';
$tabela = isset($tabela) ? trim((string)$tabela) : 'receber';
$busca = isset($busca) ? trim((string)$busca) : 'data_lanc';

if ($dataFinal < $dataInicial) {
    $tmp = $dataInicial;
    $dataInicial = $dataFinal;
    $dataFinal = $tmp;
}

if ($tabela !== 'receber' && $tabela !== 'pagar') {
    $tabela = 'receber';
}

$colunasPermitidas = ['data_lanc', 'data_venc', 'data_pgto'];
if (!in_array($busca, $colunasPermitidas, true)) {
    $busca = 'data_lanc';
}

if (isset($pdo) && $pdo instanceof PDO) {
    bb_rel_contas_reaplicar_tenant();
}

$dataInicialF = implode('/', array_reverse(@explode('-', $dataInicial)));
$dataFinalF = implode('/', array_reverse(@explode('-', $dataFinal)));

if($dataInicial == $dataFinal){
    $texto_apuracao = 'APURADO EM '.$dataInicialF;
}else if($dataInicial == '1980-01-01'){
    $texto_apuracao = 'APURADO EM TODO O PERÍODO';
}else{
    $texto_apuracao = 'APURAÇÃO DE '.$dataInicialF. ' ATÉ '.$dataFinalF;
}

if($pago == ''){
    $acao_rel = '';
}else{
    if($pago == 'Sim'){
        $acao_rel = ' Pagas ';
    }else{
        $acao_rel = ' Pendentes ';
    }
}

if($tabela == 'receber'){
    $texto_tabela = ' à Receber';
    $cor_tabela = 'text-success';
    $tabela_pago = 'RECEBIDAS';
}else{
    $texto_tabela = ' à Pagar';
    $cor_tabela = 'text-danger';
    $tabela_pago = 'PAGAS';
}

$pagoFiltro = '%'.$pago.'%';
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
    top:100;
    width:80%;
    opacity:8%;
}
</style>
</head>
<body>
<div id="header" >
    <div style="border-style: solid; font-size: 10px; height: 50px;">
        <table style="width: 100%; border: 0px solid #ccc;">
            <tr>
                <td style="border: 1px; solid #000; width: 37%; text-align: left;">
                    <img style="margin-top: 7px; margin-left: 7px;" id="imag" src="<?php echo $url_sistema ?>sistema/img/logo_rel.jpg" width="80">
                </td>
                <td style="width: 1%; text-align: center; font-size: 13px;"></td>
                <td style="width: 47%; text-align: right; font-size: 9px;padding-right: 10px;">
                    <b><big>RELATÓRIO DE CONTAS <?php echo mb_strtoupper($texto_tabela) ?> <?php echo mb_strtoupper($acao_rel) ?></big></b><br> <?php echo @mb_strtoupper($texto_apuracao) ?> <br> <?php echo @mb_strtoupper($data_hoje) ?>
                </td>
            </tr>        
        </table>
    </div>
<br>
        <table id="cabecalhotabela" style="border-bottom-style: solid; font-size: 8px; margin-bottom:10px; width: 100%; table-layout: fixed;">
            <thead>                
                <tr id="cabeca" style="margin-left: 0px; background-color:#CCC">
                    <td style="width:30%">DESCRIÇÃO</td>
                    <td style="width:10%">VALOR</td>
                    <td style="width:10%">VENCIMENTO</td>
                    <td style="width:10%">DATA PGTO</td>
                    <td style="width:10%">PAGO</td>
                    <td style="width:30%">CLIENTE</td>            
                </tr>
            </thead>
        </table>
</div>

<div id="footer" class="row">
<hr style="margin-bottom: 0;">
    <table style="width:100%;">
        <tr style="width:100%;">
            <td style="width:60%; font-size: 10px; text-align: left;"><?php echo $nome_sistema ?> Telefone: <?php echo $whatsapp_sistema ?></td>
            <td style="width:40%; font-size: 10px; text-align: right;"><p class="page">Página  </p></td>
        </tr>
    </table>
</div>

<div id="content" style="margin-top: 0;">
        <table style="width: 100%; table-layout: fixed; font-size:9px; text-transform: uppercase;">
            <thead>
                <tbody>
<?php
$total_pago = 0.0;
$total_pagoF = number_format(0, 2, ',', '.');
$total_a_pagar = 0.0;
$total_a_pagarF = number_format(0, 2, ',', '.');

$query = $pdo->query("SELECT * from $tabela where ($busca >= '$dataInicial' and $busca <= '$dataFinal') and pago LIKE '$pagoFiltro' and valor > 0 order by id desc ");
$res = $query->fetchAll(PDO::FETCH_ASSOC);

$linhas = @count($res);

if($linhas > 0){
for($i=0; $i<$linhas; $i++){
    $id = $res[$i]['id'];
    $descricao = (string)($res[$i]['descricao'] ?? '');
    $valor = $res[$i]['valor'] ?? 0;
    $data = (string)($res[$i]['data_lanc'] ?? '');
    $vencimento = (string)($res[$i]['data_venc'] ?? '');
    $pagoItem = (string)($res[$i]['pago'] ?? '');
    $data_pgto = (string)($res[$i]['data_pgto'] ?? '');

    $nome_cliente = '';
    if($tabela == 'receber'){
        $cliente = $res[$i]['pessoa'] ?? '';
        if ($cliente !== '') {
            $query2 = $pdo->query("SELECT * from clientes where id = '$cliente' ");
            $res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
            $nome_cliente = (string)($res2[0]['nome'] ?? '');
        }
    }

    $valor = is_numeric($valor) ? (float)$valor : 0.0;

    if($pagoItem == 'Sim'){
        $total_pago += $valor;
        $imagem = 'verde.jpg';
    }else{
        $total_a_pagar += $valor;
        $imagem = 'vermelho.jpg';
    }

    $valorF = number_format($valor, 2, ',', '.');
    $total_pagoF = number_format($total_pago, 2, ',', '.');
    $total_a_pagarF = number_format($total_a_pagar, 2, ',', '.');

    $dataF = (!empty($data) && strpos($data, '-') !== false) ? implode('/', array_reverse(explode('-', $data))) : '';
    $vencimentoF = (!empty($vencimento) && strpos($vencimento, '-') !== false) ? implode('/', array_reverse(explode('-', $vencimento))) : '';
    $data_pgtoF = (!empty($data_pgto) && strpos($data_pgto, '-') !== false) ? implode('/', array_reverse(explode('-', $data_pgto))) : '';

    if($data_pgtoF == '' || $data_pgtoF == '00/00/0000'){
        $data_pgtoF = 'Pendente';
    }
?>
      <tr>
        <td style="width:30%"><img src="<?php echo rtrim($url_sistema, '/') ?>/sistema/img/<?php echo $imagem ?>" width="11px" height="11px" style="margin-top:3px">
        <?php echo $descricao ?></td>
        <td style="width:10%">R$ <?php echo $valorF ?></td>
        <td style="width:10%"><?php echo $vencimentoF ?></td>
        <td style="width:10%"><?php echo $data_pgtoF ?></td>
        <td style="width:10%"><?php echo $pagoItem ?></td>
        <td style="width:30%"><?php echo $nome_cliente ?></td>
      </tr>
<?php } } ?>
                </tbody>
            </thead>
        </table>
</div>
<hr>
        <table>
            <thead>
                <tbody>
                    <tr>
                        <td style="font-size: 10px; width:270px; text-align: right;"></td>    
                        <td style="font-size: 10px; width:140px; text-align: right;"></td>
                        <td style="font-size: 10px; width:140px; text-align: right;"><b>TOTAL À <?php echo mb_strtoupper($tabela) ?> <span style="color:green"><?php echo $total_a_pagarF ?></span></b></td>
                        <td style="font-size: 10px; width:140px; text-align: right;"><b>TOTAL <?php echo $tabela_pago ?> <span style="color:red"><?php echo $total_pagoF ?></span></b></td>
                    </tr>
                </tbody>
            </thead>
        </table>
</body>
</html>
