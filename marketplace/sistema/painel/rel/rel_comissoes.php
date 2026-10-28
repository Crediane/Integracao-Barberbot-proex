<?php
if($token_rel != 'A5030'){
  echo '<script>window.location="../../"</script>';
  exit();
}

$data_hoje = date('d/m/Y'); // cabeçalho
$dataInicialF = (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$dataInicial)) ? implode('/', array_reverse(explode('-', (string)$dataInicial))) : '';
$dataFinalF   = (preg_match('/^\d{4}-\d{2}-\d{2}$/', (string)$dataFinal)) ? implode('/', array_reverse(explode('-', (string)$dataFinal))) : '';

if($dataInicial == $dataFinal){
  $texto_apuracao = 'APURADO EM '.$dataInicialF;
}else if($dataInicial == '1980-01-01'){
  $texto_apuracao = 'APURADO EM TODO O PERÍODO';
}else{
  $texto_apuracao = 'APURAÇÃO DE '.$dataInicialF.' ATÉ '.$dataFinalF;
}

if($pago == ''){
  $acao_rel = '';
}else{
  $acao_rel = ($pago == 'Sim') ? ' Pagas ' : ' Pendentes ';
}

$pago = '%'.$pago.'%';

$funcionario_post = $funcionario; // rodapé

$nome_func = '';
$nome_func2 = '';
$tel_func = '';
$pix_func = '';
if($funcionario != ''){
  $query = $pdo->query("SELECT * FROM usuarios where id = '$funcionario'");
  $res   = $query->fetchAll(PDO::FETCH_ASSOC);
  if(count($res) > 0){
    $nome_func  = ' - Funcionário: '.$res[0]['nome'];
    $nome_func2 = $res[0]['nome'];
    $tel_func   = $res[0]['telefone'];
    $pix_func   = ' <b>Chave:</b> '.$res[0]['tipo_chave'].' <b>Pix:</b> '.$res[0]['chave_pix'];
  }
}

$funcionario = '%'.$funcionario.'%';
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
.marca{ position:fixed; left:50; top:100; width:80%; opacity:8%; }
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
          <b><big>RELATÓRIO DE COMISSÕES <?php echo mb_strtoupper($acao_rel) ?> <?php echo mb_strtoupper($nome_func) ?></big></b><br>
          <?php echo @mb_strtoupper($texto_apuracao) ?> <br> <?php echo @mb_strtoupper($data_hoje) ?>
        </td>
      </tr>
    </table>
  </div>

  <br>

  <table id="cabecalhotabela" style="border-bottom-style: solid; font-size: 8px; margin-bottom:10px; width: 100%; table-layout: fixed;">
    <thead>
      <tr id="cabeca" style="margin-left: 0px; background-color:#CCC">
        <td style="width:20%">PRODUTO / SERVIÇO</td>
        <td style="width:10%">VALOR</td>
        <td style="width:20%">FUNCIONÁRIO</td>
        <td style="width:10%">DATA VENDA</td>
        <td style="width:15%">PAGAMENTO</td>
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
$total_pago = 0;
$total_a_pagar = 0;
$total_pagoF = 0;
$total_a_pagarF = 0;

$query = $pdo->query("
  SELECT * FROM pagar
   WHERE data_lanc >= '$dataInicial'
     AND data_lanc <= '$dataFinal'
     AND pago LIKE '$pago'
     AND funcionario LIKE '$funcionario'
     AND tipo IN ('Comissão','Comissao')
   ORDER BY pago ASC, data_venc ASC
");
$res    = $query->fetchAll(PDO::FETCH_ASSOC);
$linhas = @count($res);
$total_reg = $linhas;

if($linhas > 0){
  for($i=0; $i<$linhas; $i++){
    $id            = $res[$i]['id'];
    $descricao     = $res[$i]['descricao']; // pode vir como "Comissão -" vazio
    $valor         = isset($res[$i]['valor']) ? (float)$res[$i]['valor'] : 0;
    $data_lanc     = isset($res[$i]['data_lanc']) ? (string)$res[$i]['data_lanc'] : '';
    $data_pgto     = isset($res[$i]['data_pgto']) ? (string)$res[$i]['data_pgto'] : '';
    $data_venc     = isset($res[$i]['data_venc']) ? (string)$res[$i]['data_venc'] : '';
    $usuario_lanc  = $res[$i]['usuario_lanc'];
    $usuario_baixa = $res[$i]['usuario_baixa'];
    $pessoa        = $res[$i]['pessoa'];
    $funcionarioID = $res[$i]['funcionario'];
    $cliente       = isset($res[$i]['cliente'])  ? $res[$i]['cliente']  : null;
    $produto_id    = isset($res[$i]['produto'])  ? (int)$res[$i]['produto']  : 0; // se existir na tabela
    $servico_id    = isset($res[$i]['servico'])  ? (int)$res[$i]['servico']  : 0; // se existir na tabela
    $pago_row      = $res[$i]['pago'];

    $valorF     = number_format((float)$valor, 2, ',', '.');
    $data_lancF = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_lanc)) ? implode('/', array_reverse(explode('-', $data_lanc))) : '';
    $data_vencF = (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data_venc)) ? implode('/', array_reverse(explode('-', $data_venc))) : '';

    // Cliente (fallback para pessoa)
    $clienteIdBusca = ($cliente ? $cliente : $pessoa);
    $qCli = $pdo->query("SELECT * FROM clientes where id = '$clienteIdBusca'");
    $rCli = $qCli->fetchAll(PDO::FETCH_ASSOC);
    $nome_cliente = (@count($rCli) > 0) ? $rCli[0]['nome'] : 'Nenhum!';

    // Funcionário (nome da linha)
    $qFun = $pdo->query("SELECT * FROM usuarios where id = '$funcionarioID'");
    $rFun = $qFun->fetchAll(PDO::FETCH_ASSOC);
    $nome_func_linha = (@count($rFun) > 0) ? $rFun[0]['nome'] : 'Sem Referência!';

    // ======== RESOLUÇÃO DO NOME DO ITEM (sem alterar o restante da lógica) ========
    // 1) tira prefixo "Comissão - " se existir
    $nome_item = trim(preg_replace('/^comiss[aã]o\s*-\s*/i', '', (string)$descricao));

    // 2) se ficou vazio, tenta produto
    if ($nome_item === '' && $produto_id > 0) {
      $qP = $pdo->query("SELECT nome FROM produtos WHERE id = '$produto_id' LIMIT 1");
      $rP = $qP->fetchAll(PDO::FETCH_ASSOC);
      if (@count($rP) > 0) { $nome_item = $rP[0]['nome']; }
    }

    // 3) se ainda vazio, tenta serviço
    if ($nome_item === '' && $servico_id > 0) {
      $qS = $pdo->query("SELECT nome FROM servicos WHERE id = '$servico_id' LIMIT 1");
      $rS = $qS->fetchAll(PDO::FETCH_ASSOC);
      if (@count($rS) > 0) { $nome_item = $rS[0]['nome']; }
    }

    // 4) fallback: usa a própria descrição
    if ($nome_item === '') { $nome_item = $descricao; }
    // ==============================================================================

    // Totais (mantidos)
    if($data_pgto == '0000-00-00' || $pago_row != 'Sim'){
      $total_a_pagar += (float)$valor;
      $imagem = 'vermelho.jpg';
    }else{
      $total_pago += (float)$valor;
      $imagem = 'verde.jpg';
    }

    // atualiza formatação dentro do loop (mantido)
    $total_pagoF    = number_format($total_pago, 2, ',', '.');
    $total_a_pagarF = number_format($total_a_pagar, 2, ',', '.');
?>
    <tr>
      <td style="width:20%"><img src="<?php echo $url_sistema ?>/sistema/img/<?php echo $imagem ?>" width="11px" height="11px" style="margin-top:3px"> <?php echo $nome_item ?></td>
      <td style="width:10%">R$ <?php echo $valorF ?></td>
      <td style="width:20%"><?php echo $nome_func_linha ?></td>
      <td style="width:10%"><?php echo $data_lancF ?></td>
      <td style="width:15%"><?php echo $data_vencF ?></td>
      <td style="width:25%"><?php echo $nome_cliente ?></td>
    </tr>
<?php
  }
}

// garante formatação mesmo sem linhas
if (!isset($total_pagoF))    { $total_pagoF    = number_format($total_pago, 2, ',', '.'); }
if (!isset($total_a_pagarF)) { $total_a_pagarF = number_format($total_a_pagar, 2, ',', '.'); }
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
        <td style="font-size: 10px; width:270px; text-align: right;"></td>
        <td style="font-size: 10px; width:140px; text-align: right;"><b>TOTAL DE COMISSÕES: <span style="color:green"><?php echo $total_reg ?></span></b></td>
        <td style="font-size: 10px; width:140px; text-align: right;"><b>TOTAL PAGO R$: <span style="color:green"><?php echo $total_pagoF ?></span></b></td>
        <td style="font-size: 10px; width:140px; text-align: right;"><b>TOTAL À PAGAR R$: <span style="color:red"><?php echo $total_a_pagarF ?></span></b></td>
      </tr>
    </tbody>
  </thead>
</table>

<br><br>
<?php if($funcionario_post != ""){ ?> 
<div class="col-md-12 p-2" align="center">
  <div class="">
    <small><small>
      <span><b>Funcionário</b> : <?php echo @$nome_func2 ?></span>
      <span><b>Telefone</b> : <?php echo @$tel_func ?></span>
      <span><?php echo @$pix_func ?></span>
      <span class="text-success"><b>Total à Receber</b> : <?php echo @$total_a_pagarF ?></span>
    </small></small>
  </div>
</div>
<div class="cabecalho" style="border-bottom: solid 1px #0340a3"></div>
<?php } ?>

</body>
</html>
