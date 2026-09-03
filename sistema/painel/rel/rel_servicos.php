<?php 
if($token_rel != 'A5030'){
echo '<script>window.location="../../"</script>';
exit();
}

if (!function_exists('bb_rel_servicos_slug_limpo')) {
    function bb_rel_servicos_slug_limpo($valor)
    {
        $valor = strtolower(trim((string)$valor));
        return preg_replace('/[^a-z0-9_-]/', '', $valor);
    }
}

if (!function_exists('bb_rel_servicos_data_valida_rel')) {
    function bb_rel_servicos_data_valida_rel($valor)
    {
        return is_string($valor) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor);
    }
}

if (!function_exists('bb_rel_servicos_resolver_slug')) {
    function bb_rel_servicos_resolver_slug()
    {
        $reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
        $refPath = !empty($_SERVER['HTTP_REFERER']) ? (parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: '') : '';

        if (!empty($_GET['loja'])) {
            return bb_rel_servicos_slug_limpo($_GET['loja']);
        }

        if (!empty($_POST['loja'])) {
            return bb_rel_servicos_slug_limpo($_POST['loja']);
        }

        if (!empty($_SESSION['tenant_slug'])) {
            return bb_rel_servicos_slug_limpo($_SESSION['tenant_slug']);
        }

        if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $reqPath, $m)) {
            return bb_rel_servicos_slug_limpo($m[1]);
        }

        if ($refPath !== '' && preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $refPath, $m2)) {
            return bb_rel_servicos_slug_limpo($m2[1]);
        }

        return '';
    }
}

if (!function_exists('bb_rel_servicos_reaplicar_tenant')) {
    function bb_rel_servicos_reaplicar_tenant()
    {
        $slug = bb_rel_servicos_resolver_slug();
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
                $qConf = $pdoTenant->query("SELECT * FROM config LIMIT 1");
                $dConf = $qConf->fetch(PDO::FETCH_ASSOC);
                if ($dConf) {
                    $GLOBALS['nome_sistema'] = $dConf['nome'] ?? ($GLOBALS['nome_sistema'] ?? 'BarberBot');
                    $GLOBALS['whatsapp_sistema'] = $dConf['telefone_whatsapp'] ?? ($dConf['telefone'] ?? ($GLOBALS['whatsapp_sistema'] ?? ''));
                }
            } catch (Throwable $e) {
                // não bloqueia relatório
            }

            return true;
        } catch (Throwable $e) {
            return false;
        }
    }
}

$dataInicial = bb_rel_servicos_data_valida_rel($dataInicial ?? null) ? $dataInicial : date('Y-m-d');
$dataFinal = bb_rel_servicos_data_valida_rel($dataFinal ?? null) ? $dataFinal : $dataInicial;
$pgto = isset($pgto) ? trim((string)$pgto) : '';
$servico = isset($servico) ? trim((string)$servico) : '';

if ($dataFinal < $dataInicial) {
    $tmp = $dataInicial;
    $dataInicial = $dataFinal;
    $dataFinal = $tmp;
}

if (isset($pdo) && $pdo instanceof PDO) {
    bb_rel_servicos_reaplicar_tenant();
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

if($pgto == ''){
    $acao_rel = '';        
}else{
    $acao_rel = 'Pagamento: '.$pgto;
}

$pgto_filtro = '%'.$pgto.'%';

$query = $pdo->query("SELECT * FROM servicos where id = '$servico'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);
if($total_reg > 0){
    $titulo_servico = $res[0]['nome'];
}else{
    $titulo_servico = '';
}

$servico_filtro = '%'.$servico.'%';
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
                    <b><big>RELATÓRIO DE SERVIÇOS <?php echo @mb_strtoupper($titulo_servico) ?> <?php echo @mb_strtoupper($acao_rel) ?> </big></b><br> <?php echo @mb_strtoupper($texto_apuracao) ?> <br> <?php echo @mb_strtoupper($data_hoje) ?>
                </td>
            </tr>        
        </table>
    </div>
<br>
        <table id="cabecalhotabela" style="border-bottom-style: solid; font-size: 8px; margin-bottom:10px; width: 100%; table-layout: fixed;">
            <thead>                
                <tr id="cabeca" style="margin-left: 0px; background-color:#CCC">
                    <td style="width:20%">DESCRIÇÃO</td>                    
                    <td style="width:10%">VALOR</td>
                    <td style="width:10%">DATA PGTO</td>
                    <td style="width:20%">RECEBIDO POR</td>
                    <td style="width:15%">FORMA PGTO</td>    
                    <td style="width:25%">CLIENTE</td>            
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
$total_entradas = 0;
$total_entradasF = number_format(0, 2, ',', '.');
$query = $pdo->query("SELECT * FROM receber where data_pgto >= '$dataInicial' and data_pgto <= '$dataFinal' and tipo = 'Serviço' and pago = 'Sim' and servico LIKE '$servico_filtro' and pgto LIKE '$pgto_filtro'  ORDER BY data_pgto asc");
$res = $query->fetchAll(PDO::FETCH_ASSOC);

$linhas = @count($res);

if($linhas > 0){
for($i=0; $i<$linhas; $i++){
    $id = $res[$i]['id'];    
    $descricao = $res[$i]['descricao'];
    $tipo = $res[$i]['tipo'];
    $valor = $res[$i]['valor'];
    $data_lanc = $res[$i]['data_lanc'];
    $data_pgto = $res[$i]['data_pgto'];
    $data_venc = $res[$i]['data_venc'];
    $usuario_lanc = $res[$i]['usuario_lanc'];
    $usuario_baixa = $res[$i]['usuario_baixa'];
    $foto = $res[$i]['foto'];
    $pessoa = $res[$i]['pessoa'];
    $pago = $res[$i]['pago'];
    $servico_id_item = $res[$i]['servico'];
    $pgto_item = $res[$i]['pgto'];
    $valor2 = $res[$i]['valor2'];

    $valor = ($valor === null || $valor === '' || !is_numeric($valor) || (float)$valor <= 0) ? $valor2 : $valor;
    $valor = is_numeric($valor) ? (float)$valor : 0;
    $valor2 = is_numeric($valor2) ? (float)$valor2 : 0;
    $total_entradas = is_numeric($total_entradas) ? (float)$total_entradas : 0;

    $total_entradas += $valor;

    $valorF = number_format($valor, 2, ',', '.');
    $total_entradasF = number_format($total_entradas, 2, ',', '.');
    $data_lancF = (!empty($data_lanc) && strpos((string)$data_lanc, '-') !== false) ? implode('/', array_reverse(explode('-', (string)$data_lanc))) : '';
    $data_pgtoF = (!empty($data_pgto) && strpos((string)$data_pgto, '-') !== false) ? implode('/', array_reverse(explode('-', (string)$data_pgto))) : '';
    $data_vencF = (!empty($data_venc) && strpos((string)$data_venc, '-') !== false) ? implode('/', array_reverse(explode('-', (string)$data_venc))) : '';

    $query2 = $pdo->query("SELECT * FROM clientes where id = '$pessoa'");
    $res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
    $total_reg2 = @count($res2);
    if($total_reg2 > 0){
        $nome_pessoa = $res2[0]['nome'];
        $telefone_pessoa = $res2[0]['telefone'];
        $classe_whats = '';
    }else{
        $nome_pessoa = 'Nenhum!';
        $telefone_pessoa = '';
        $classe_whats = 'ocultar';
    }

    $query2 = $pdo->query("SELECT * FROM usuarios where id = '$usuario_baixa'");
    $res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
    $total_reg2 = @count($res2);
    if($total_reg2 > 0){
        $nome_usuario_pgto = $res2[0]['nome'];
    }else{
        $nome_usuario_pgto = 'Nenhum!';
    }

    $query2 = $pdo->query("SELECT * FROM usuarios where id = '$usuario_lanc'");
    $res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
    $total_reg2 = @count($res2);
    if($total_reg2 > 0){
        $nome_usuario_lanc = $res2[0]['nome'];
    }else{
        $nome_usuario_lanc = 'Sem Referência!';
    }

    $query2 = $pdo->query("SELECT * FROM servicos where id = '$servico_id_item'");
    $res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
    $total_reg2 = @count($res2);
    if($total_reg2 > 0){
        $nome_servico = $res2[0]['nome'];
    }else{
        $nome_servico = '';
    }
?>
      <tr>
        <td style="width:20%"><?php echo $descricao ?></td>
        <td style="width:10%">R$ <?php echo $valorF ?></td>
        <td style="width:10%"><?php echo $data_pgtoF ?></td>
        <td style="width:20%"><?php echo $nome_usuario_pgto ?></td>
        <td style="width:15%"><?php echo $pgto_item ?></td>
        <td style="width:25%"><?php echo $nome_pessoa ?></td>
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
                        <td style="font-size: 10px; width:120px; text-align: right;"></td>
                        <td style="font-size: 10px; width:160px; text-align: right;"><b>TOTAL DE RECEBIMENTOS <span style="color:green"><?php echo $linhas ?></span></td>
                        <td style="font-size: 10px; width:160px; text-align: right;"><b>TOTAL R$ <span style="color:red"><?php echo $total_entradasF ?></span></td>
                    </tr>
                </tbody>
            </thead>
        </table>
</body>
</html>
