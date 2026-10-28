<?php
require_once("cabecalho.php");
require_once("rodape.php");

$data_atual = date('Y-m-d');

$funcionario = $id_usuario;

$pag = 'agenda';
$itens_pag = 10;

if (@$agenda == 'ocultar') {
  echo "<script>window.location='index'</script>";
  exit();
}

// pegar a pagina atual
if (@$_POST['pagina'] == "") {
  @$_POST['pagina'] = 0;
}
$pagina = intval(@$_POST['pagina']);
$limite = $pagina * $itens_pag;

$numero_pagina = $pagina + 1;

if ($pagina > 0) {
  $pag_anterior = $pagina - 1;
  $pag_inativa_ant = '';
} else {
  $pag_anterior = $pagina;
  $pag_inativa_ant = 'desabilitar_botao';
}
$pag_proxima = $pagina + 1;
if ($dataInicial == "") {
  $dataInicial = $data_atual;
}
if ($dataFinal == "") {
  $dataFinal = $data_atual;
}
$query2 = $pdo->query("SELECT * from agendamentos where data >= '$dataInicial' and data <= '$dataFinal' and funcionario = '$funcionario' order by hora asc");
$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
$linhas2 = @count($res2);

$num_paginas = ceil($linhas2 / $itens_pag);
if ($pag_proxima == $num_paginas) {
  $pag_inativa_prox = 'desabilitar_botao';
  $pag_proxima = $pagina;
} else {
  $pag_inativa_prox = '';
}
?>

<div class="page-content header-clear-medium">


  <!-- BARRA DE PESQUISA-->
  <form method="post">
    <div style="margin-top: -15px; margin-bottom: 40px; padding: 15px">
      <input type="date" name="dataInicial" id="dataInicial" class="round-small form-control rounded-xs"
        value="<?php echo $dataInicial ?>" onchange="buscar('')" style="width:42%; float:left; padding: 10px" />
      <img src="images/icons/black/icone_datas4.png" style="float:left; width:13%">
      <input type="date" name="dataFinal" id="dataFinal" class="round-small form-control rounded-xs"
        value="<?php echo $dataFinal ?>" onchange="buscar('')" style="width:42%; float:right; padding: 10px" />
    </div>   
    
    <button id="btn_filtrar" class="limpar_botao ocultar" type="submit"></button>
  </form>

<form method="post" style="display: none;">
        <input type="text" name="pagina" id="input_pagina">
        <input type="text" name="buscar" id="input_buscar">
        <input type="text" name="dataInicial" id="dataInicialPag">
        <input type="text" name="dataFinal" id="dataFinalPag">
        <input type="text" name="pago" id="pago" value="<?php echo $pago ?>">
  <button type="submit" id="paginacao"></button>
</form>


  <div class="card card-style">
    <div class="content">
        <?php 
        $query = $pdo->query("SELECT * from agendamentos where data >= '$dataInicial' and data <= '$dataFinal' and funcionario = '$funcionario' order by hora asc LIMIT $limite, $itens_pag");
      
      $valor_pago = 0;
      $valor_pendentes = 0;
      $res = $query->fetchAll(PDO::FETCH_ASSOC);
      $linhas = @count($res);
      if ($linhas > 0) {
        for ($i = 0; $i < $linhas; $i++) {
        $id = $res[$i]['id'];
$funcionario = $res[$i]['funcionario'];
$cliente = $res[$i]['cliente'];
$hora = $res[$i]['hora'];
$data = $res[$i]['data'];
$usuario = $res[$i]['usuario'];
$data_lanc = $res[$i]['data_lanc'];
$obs = $res[$i]['obs'];
$status = $res[$i]['status'];
$servico = $res[$i]['servico'];
$valor_pago = $res[$i]['valor_pago'];

$valor_pagoF = @number_format($valor_pago, 2, ',', '.');
if($valor_pago > 0 and $status == 'Agendado'){
  $classe_valor_pago = '';
}else{
  $classe_valor_pago = 'ocultar';
}
$dataF = implode('/', array_reverse(@explode('-', $data)));
$horaF = date("H:i", @strtotime($hora));

if($status == 'Concluído'){   
  $classe_linha = '';
}else{    
  $classe_linha = 'text-muted';
}

if($status != 'Concluído'){
  $imagem = 'gradient-red';
  $classe_status = '';  
}else{
  $imagem = 'gradient-green';
  $classe_status = 'ocultar';
}

$query2 = $pdo->query("SELECT * FROM usuarios where id = '$usuario'");
$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
if(@count($res2) > 0){
  $nome_usu = $res2[0]['nome'];
}else{
  $nome_usu = 'Sem Usuário';
}


$query2 = $pdo->query("SELECT * FROM servicos where id = '$servico'");
$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
if(@count($res2) > 0){
  $nome_serv = $res2[0]['nome'];
  $valor_serv = $res2[0]['valor'];
}else{
  $nome_serv = 'Não Lançado';
  $valor_serv = '';
}


$query2 = $pdo->query("SELECT * FROM clientes where id = '$cliente'");
$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
if(@count($res2) > 0){
  $nome_cliente = $res2[0]['nome'];
  $total_cartoes = $res2[0]['cartoes'];
}else{
  $nome_cliente = 'Sem Cliente';
  $total_cartoes = 0;
}

if($total_cartoes >= $quantidade_cartoes and $status == 'Agendado'){
  $ocultar_cartoes = '';
}else{
  $ocultar_cartoes = 'ocultar';
}

//retirar aspas do texto do obs
$obs = str_replace('"', "**", $obs);

$classe_deb = '#043308';
$total_debitos = 0;
$total_pagar = 0;
$total_vencido = 0;
$total_debitosF = 0;
$total_pagarF = 0;
$total_vencidoF = 0;
$query2 = $pdo->query("SELECT * FROM receber where pessoa = '$cliente' and pago != 'Sim'");
$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
$total_reg2 = @count($res2);
if($total_reg2 > 0){
  $classe_deb = '#661109';
  for($i2=0; $i2 < $total_reg2; $i2++){ 
  $valor_s = $res2[$i2]['valor'];   
  $data_venc = $res2[$i2]['data_venc']; 
  
  $total_debitos += $valor_s;
  $total_debitosF = @number_format($total_debitos, 2, ',', '.');

  if(@strtotime($data_venc) < @strtotime($data_atual)){   
    $total_vencido += $valor_s;  }else{
    $total_pagar += $valor_s;
  }

  $total_pagarF = @number_format($total_pagar, 2, ',', '.');
  $total_vencidoF = @number_format($total_vencido, 2, ',', '.');
}
}

if($valor_serv == $valor_pago){
  $valor_pagoF = ' Pago';
}else{
  $valor_pagoF = 'R$ '.$valor_pagoF;
}

if($valor_pago > 0){
  $valor_serv = $valor_serv - $valor_pago;
}

          echo <<<HTML
<div data-splide='{"autoplay":false}' class="splide single-slider slider-no-arrows slider-no-dots" id="user-slider-{$id}">
  <div class="splide__track">
    <div class="splide__list">


     <div class="splide__slide mx-3">
  <div class="d-flex align-items-start">
    <div
      onclick="mostrar('{$nome_cliente}','{$total_vencidoF}','{$total_pagarF}','{$total_debitosF}','{$obs}')"
      style="cursor: pointer;"
    >
      <h5 class="mt-0 mb-1 d-flex align-items-center" style="font-size: 13px;">
        <i class="bi bi-person-fill me-1 text-primary" style="font-size: 1rem;"></i>
        {$nome_cliente}
      </h5>
      <p class="font-12 mt-n2 mb-0 d-flex align-items-center text-secondary">
        <i class="bi bi-briefcase-fill me-1 text-success" style="font-size: 0.9rem;"></i>
        {$nome_serv}
        <i class="bi bi-calendar-event-fill ms-2 me-1 text-muted" style="font-size: 0.85rem;"></i>
        {$dataF}
      </p>
    </div>
    <div class="ms-auto">
      <span class="slider-next">
        <span class="badge px-3 py-2 {$imagem} shadow-bg shadow-bg-s d-flex align-items-center" style="font-size: 0.85rem;">
          <i class="bi bi-clock-fill me-1" style="font-size: 0.85rem;"></i>
          {$horaF}
        </span>
      </span>
    </div>
  </div>
</div>


      <div class="splide__slide mx-3">
        <div class="d-flex">
          <div
            onclick="mostrar('{$nome_cliente}','{$total_vencidoF}','{$total_pagarF}','{$total_debitosF}','{$obs}')">
           
          </div>
          <div class="ms-auto">
            <a onclick="fecharServico('{$id}', '{$cliente}', '{$servico}', '{$valor_serv}', '{$funcionario}', '{$nome_serv}')" href="#" class="{$classe_status} icon icon-xs rounded-circle shadow-l bg-green"><i class="fa fa-check-square text-white"></i></a>
              <a onclick="return excluir_reg('{$id}', '{$nome_cliente}')" href="#" class="icon icon-xs rounded-circle shadow-l bg-google"><i class="bi bi-trash-fill text-white"></i></a>    
           
                    

          </div>
        </div>
      </div>
    </div>
  </div>
</div>
      <div class="divider mt-3 mb-3 "></div>
HTML;
        }
      } else {
        echo 'Nenhum Registro Encontrado!';
      }
      ?>
    </div>
  </div>


  <!-- PAGINAÇÃO -->
  <nav aria-label="pagination-demo">
    <ul class="pagination pagination- justify-content-center">
      <li class="page-item">
        <a onclick="paginar('<?php echo $pag_anterior ?>', '<?php echo $buscar ?>')"
          class="page-link py-2 rounded-xs color-black bg-transparent bg-theme shadow-xl border-0 <?php echo $pag_inativa_ant ?>"
          href="#" tabindex="-1" aria-disabled="true"><i class="bi bi-chevron-left"></i></a>
      </li>
      <li class="page-item"><a class="page-link py-2 rounded-xs shadow-l border-0 color-dark"
          href="#"><?php echo @$numero_pagina ?> / <?php echo @$num_paginas ?></span></a>
      </li>
      <li class="page-item">
        <a onclick="paginar('<?php echo $pag_proxima ?>', '<?php echo $buscar ?>')"
          class="page-link py-2 rounded-xs color-black bg-transparent bg-theme shadow-l border-0 <?php echo $pag_inativa_prox ?>"
          href="#"><i class="bi bi-chevron-right"></i>
        </a>
      </li>
    </ul>
  </nav>
</div>

<div class="fab" style="z-index: 100 !important; margin-bottom: 60px">
  <button id="btn_novo" onclick="limparCampos(); listarHorarios();" class="main open-popup bg-highlight" data-bs-toggle="offcanvas"
    data-bs-target="#popupForm">
    +
  </button>
</div>

<div hidden="hidden" class="fab" style="z-index: 100 !important; margin-bottom: 60px">
  <button id="btn_novo_editar" class="main open-popup bg-highlight" data-bs-toggle="offcanvas"
    data-bs-target="#popupForm">
  </button>
</div>


<!-- MODAL CADASTRO-->
<div class="offcanvas offcanvas-top rounded-m offcanvas-detached" style="height:100%" id="popupForm">
  <div class="content mb-0">
    <div class="d-flex pb-2">
      <div class="align-self-center">
        <h2 align="center" class="mb-n1 font-12 color-highlight font-700 text-uppercase pt-1" id="titulo_inserir">
          INSERIR DADOS</h2>
      </div>
      <div class="align-self-center ms-auto">
        <button onclick="limparCampos()" style="border: none; background: transparent; margin-right: 12px"
          data-bs-dismiss="offcanvas" id="btn-fechar" aria-label="Close" data-bs-dismiss="modal" type="button"><i
            class="bi bi-x-circle-fill color-red-dark font-18 me-n4"></i>
        </button>
      </div>
    </div>
    <form id="form_agendamento" method="post" class="demo-animation needs-validation m-0">
    


    <div class="row">

  <!-- Cliente (linha cheia) -->
  <div class="col-12">
    <div class="form-floating mb-3 position-relative">
      <i class="bi bi-person-fill position-absolute start-0 top-50 translate-middle-y ms-3"></i>
      <select class="form-control sel3 rounded-xs ps-5 pe-5" id="cliente" name="cliente" style="width:100%;" required>
        <?php 
          $query = $pdo->query("SELECT * FROM clientes ORDER BY nome asc");
          $res = $query->fetchAll(PDO::FETCH_ASSOC);
          foreach ($res as $item) {
              echo '<option value="'.$item['id'].'">'.$item['nome'].'</option>';
          }
        ?>
      </select>
      <label class="color-theme ps-5">Cliente</label>
    </div>
  </div>

  <!-- Serviço (col-12 col-md-6) -->
  <div class="col-12 col-md-6">
    <div class="form-floating mb-3 position-relative">
      <i class="bi bi-briefcase-fill position-absolute start-0 top-50 translate-middle-y ms-3"></i>
      <select class="form-control sel3 rounded-xs ps-5 pe-5" id="servico" name="servico" style="width:100%;" required>
        <?php 
          $query = $pdo->query("SELECT * FROM servicos_func where funcionario = '$id_usuario' ");
          $res = $query->fetchAll(PDO::FETCH_ASSOC);
          if(@count($res) > 0){
            for($i=0; $i < @count($res); $i++){
              $serv = $res[$i]['servico'];
              $query2 = $pdo->query("SELECT * FROM servicos where id = '$serv' and ativo = 'Sim' ");
              $res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
              if (@count($res2) > 0) {
                $nome_func = $res2[0]['nome'];
                echo '<option value="'.$serv.'">'.$nome_func.'</option>';
              }
}   
          } else {
            echo '<option value="">Nenhum Serviço</option>';
          }
        ?>
      </select>
      <label class="color-theme ps-5">Serviço</label>
    </div>
  </div>

  <!-- Data (col-12 col-md-6) -->
  <div class="col-12 col-md-6">
    <div class="form-floating mb-3 position-relative">
      <i class="bi bi-calendar-check position-absolute start-0 top-50 translate-middle-y ms-3"></i>
      <input value="<?php echo $data_atual ?>" type="date" class="form-control rounded-xs ps-5" name="data" id="data-modal" onchange="mudarData()">
      <label class="color-theme ps-5">Data</label>
    </div>
  </div>

</div>

<hr>

<!-- Horários (bloco externo) -->
<div class="row">
  <div class="col-12">
    <div class="mb-3" id="listar-horarios">
      <!-- Horários serão renderizados aqui -->
    </div>
  </div>
</div>

<hr>

<!-- Observações (linha cheia) -->
<div class="row">
  <div class="col-12">
    <div class="form-floating mb-3 position-relative">
      <i class="bi bi-chat-left-dots-fill position-absolute start-0 top-50 translate-middle-y ms-3"></i>
      <input type="text" class="form-control rounded-xs ps-5" name="obs" id="obs" maxlength="100">
      <label class="color-theme ps-5">Observações (Máx 100 caracteres)</label>
    </div>
  </div>
</div>

<!-- Hidden fields e mensagem -->
<input type="hidden" name="id" id="id">
<input type="hidden" name="funcionario" id="funcionario" value="<?php echo $funcionario ?>">
<input type="hidden" name="id_funcionario" id="id_funcionario" value="<?php echo $funcionario ?>">
<small><div id="mensagem" class="mt-3 text-center"></div></small>

      
      <button name="btn_salvar" id="btn_salvar"
        class="btn btn-full gradient-highlight rounded-xs text-uppercase font-700 w-100 btn-s mt-4 mb-3"
        type="submit">SALVAR <i class="fa-regular fa-circle-check"></i></button>
      <button class="btn btn-full gradient-highlight rounded-xs text-uppercase font-700 w-100 btn-s mt-4 mb-3"
        type="button" id="btn_carregando" style="display: none">
        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Carregando...
      </button>
      <input type="hidden" name="id" id="id">
    </form>
  </div>
</div>

<!--BOTÃO PARA CHAMAR A MODAL MOSTRAR -->
<a style="display:none" href="#" data-bs-toggle="offcanvas" data-bs-target="#menu-share-mostrar" class="list-group-item"
  id="btn_mostrar">
</a>

<!-- MODAL MOSTRAR -->
<div class="offcanvas offcanvas-top rounded-m offcanvas-detached" style="height:98%" id="menu-share-mostrar">
  <div class="content ">
    <div class="d-flex pb-2">
      <div class="align-self-center">
        <h1 class="font-11 color-highlight font-700 text-uppercase" id="titulo_dados"></h1>
      </div>
      <div class="align-self-center ms-auto">
        <a href="#" data-bs-dismiss="offcanvas" class="icon icon-m"><i
            class="bi bi-x-circle-fill color-red-dark font-18 me-n4"></i></a>
      </div>
    </div>
    <div class="card overflow-visible">
      <div class="content mb-1">
        <div class="table-responsive">
          <table class="table color-theme mb-4">
            <tbody>
              <tr class="border-fade-blue">
                <td style="font-size: 11px;" class="color-highlight">Total Vencidas R$:</td>
                <td style="font-size: 11px;" id="valor_dados"></td>
              </tr>
              
              <tr class="border-fade-blue">
                <td style="font-size: 11px;" class="color-highlight">Total Pagar R$:</td>
                <td style="font-size: 11px;" id="data_venc_dados"></td>
              </tr>
             
              <tr class="border-fade-blue">
                <td style="font-size: 11px;" class="color-highlight">Total Débitos:</td>
                <td style="font-size: 11px;" id="data_pgto_dados"></td>
              </tr>           
             
              

              <tr class="border-fade-blue">
                <td style="font-size: 11px;" class="color-highlight">OBS:</td>
                <td style="font-size: 11px;" id="obs_dados"></td>
              </tr>
            </tbody>
          </table>
         
        </div>
      </div>
    </div>
  </div>
</div>

<!--BOTÃO PARA CHAMAR A MODAL SERVIÇOS -->
<a style="display:none" href="#" data-bs-toggle="offcanvas" data-bs-target="#menu-share-servico" class="list-group-item"
  id="btn_servico">
</a>

<!-- MODAL MOSTRAR -->
<div class="offcanvas offcanvas-top rounded-m offcanvas-detached" style="height:98%" id="menu-share-servico">
  <div class="content ">
    <div class="d-flex pb-2">
      <div class="align-self-center">
        <h1 class="font-11 color-highlight font-700 text-uppercase" id="titulo_servico"></h1>
      </div>
       <div class="align-self-center ms-auto">
        <button onclick="limparCampos()" style="border: none; background: transparent; margin-right: 12px"
          data-bs-dismiss="offcanvas" id="btn-fechar-servico" aria-label="Close" data-bs-dismiss="modal" type="button"><i
            class="bi bi-x-circle-fill color-red-dark font-18 me-n4"></i>
        </button>
      </div>
    </div>
    <div class="card overflow-visible">
      <div class="content mb-1">
        <div class="table-responsive">
          <form method="post" id="form-servico" novalidate data-bb-form-baixa="agenda">
              <div class="row">

  <!-- Funcionário: 1 por linha -->
  <div class="col-12" id="select_serv">
    <div class="form-floating mb-3 position-relative">
      <i class="bi bi-person-badge-fill position-absolute start-0 top-50 translate-middle-y ms-3"></i>
      <select class="form-control sel_servicos rounded-xs ps-5 pe-5" id="funcionario_agd" name="funcionario_agd" style="width:100%;" required>
        <?php 
          $query = $pdo->query("SELECT * FROM usuarios where atendimento = 'Sim' ORDER BY nome asc");
          $res = $query->fetchAll(PDO::FETCH_ASSOC);
          foreach ($res as $item) {
              echo '<option value="'.$item['id'].'">'.$item['nome'].'</option>';
          }
        ?>
      </select>
      
    </div>
  </div>

  <!-- Valor e Data PGTO: 2 por linha -->
  <div class="col-12 col-md-6">
    <div class="form-floating mb-3 position-relative">
      <i class="bi bi-currency-dollar position-absolute start-0 top-50 translate-middle-y ms-3"></i>
      <input type="text" class="form-control rounded-xs ps-5" id="valor_serv_agd" name="valor_serv_agd" placeholder="R$">
      <label class="color-theme ps-5">Valor (Falta Pagar)</label>
    </div>
  </div>

  <div class="col-12 col-md-6">
    <div class="form-floating mb-3 position-relative">
      <i class="bi bi-calendar-event position-absolute start-0 top-50 translate-middle-y ms-3"></i>
      <input type="date" class="form-control rounded-xs ps-5" id="data_pgto" name="data_pgto" value="<?php echo $data_atual ?>">
      <label class="color-theme ps-5">Data PGTO</label>
    </div>
  </div>

  <!-- Forma de PGTO: linha cheia -->
  <div class="col-12">
    <div class="form-floating mb-3 position-relative">
      <i class="bi bi-credit-card-fill position-absolute start-0 top-50 translate-middle-y ms-3"></i>
      <select class="form-control rounded-xs ps-5 pe-5" id="pgto" name="pgto" style="width:100%;" required>
        <?php 
          $query = $pdo->query("SELECT * FROM formas_pgto");
          $res = $query->fetchAll(PDO::FETCH_ASSOC);
          foreach ($res as $item) {
              echo '<option value="'.$item['nome'].'">'.$item['nome'].'</option>';
          }
        ?>
      </select>
      <label class="color-theme ps-5">Forma PGTO</label>
    </div>
  </div>

   <div class="col-12">
    <div class="form-floating mb-3 position-relative">
      <i class="bi bi-cash-coin position-absolute start-0 top-50 translate-middle-y ms-3"></i>
      <input type="text" class="form-control rounded-xs ps-5" id="valor_serv_agd_restante" name="valor_serv_agd_restante"  maxlength="50">
      <label class="color-theme ps-5">Valor Restante <small>(Mais de uma forma PGTO)</small></label>
    </div>
  </div>
  

  <div class="col-12 col-md-6">
    <div class="form-floating mb-3 position-relative">
      <i class="bi bi-calendar-check-fill position-absolute start-0 top-50 translate-middle-y ms-3"></i>
      <input type="date" class="form-control rounded-xs ps-5" id="data_pgto_restante" name="data_pgto_restante">
      <label class="color-theme ps-5">Data PGTO Restante</label>
    </div>
  </div>

  <!-- Forma PGTO Restante: linha cheia -->
  <div class="col-12">
    <div class="form-floating mb-3 position-relative">
      <i class="bi bi-wallet2 position-absolute start-0 top-50 translate-middle-y ms-3"></i>
      <select class="form-control rounded-xs ps-5 pe-5" id="pgto_restante" name="pgto_restante" style="width:100%;">
        <option value="">Selecionar Pgto</option>
        <?php 
          $query = $pdo->query("SELECT * FROM formas_pgto");
          $res = $query->fetchAll(PDO::FETCH_ASSOC);
          foreach ($res as $item) {
              echo '<option value="'.$item['nome'].'">'.$item['nome'].'</option>';
          }
        ?>
      </select>
      <label class="color-theme ps-5">Forma PGTO Restante</label>
    </div>
  </div>

  <!-- Observações: linha cheia -->
  <div class="col-12">
    <div class="form-floating mb-3 position-relative">
      <i class="bi bi-chat-text-fill position-absolute start-0 top-50 translate-middle-y ms-3"></i>
      <input type="text" class="form-control rounded-xs ps-5" id="obs2" name="obs" maxlength="1000">
      <label class="color-theme ps-5">Observações</label>
    </div>
  </div>

  <!-- Hidden fields -->
  <input type="hidden" name="id_agd" id="id_agd"> 
  <input type="hidden" name="cliente_agd" id="cliente_agd"> 
  <input type="hidden" name="servico_agd" id="servico_agd">
  <input type="hidden" name="descricao_serv_agd" id="descricao_serv_agd">

</div>


<button name="btn_salvar" id="btn_salvar_servico"
        class="btn btn-full gradient-highlight rounded-xs text-uppercase font-700 w-100 btn-s mt-4 mb-3"
        type="button" onclick="return bb_baixar_agendamento_servico(event);">SALVAR <i class="fa-regular fa-circle-check"></i></button>
      <button class="btn btn-full gradient-highlight rounded-xs text-uppercase font-700 w-100 btn-s mt-4 mb-3"
        type="button" id="btn_carregando_servico" style="display: none">
        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Carregando...
      </button>
              </form>
         
        </div>
      </div>
    </div>
  </div>
</div>





<script type="text/javascript">
  function mostrar(cliente, vencidos, pagar, debitos, obs) {

    const botao = document.getElementById('btn_mostrar');
  

    $('#titulo_dados').text(cliente);
    $('#valor_dados').text(vencidos);   
    $('#data_venc_dados').text(pagar);
    $('#data_pgto_dados').text(debitos);
    $('#obs_dados').text(obs);
      
    botao.click();
  }


  function limparCampos() {
    $('#descricao').val('');
    $('#valor').val('0,00');
    $('#data_venc').val("<?= $data_atual ?>");    
    $('#obs').val('');    
    
  }





  function paginar(pag, busca) {
    $('#dataInicialPag').val($('#dataInicial').val());
    $('#dataFinalPag').val($('#dataFinal').val());

    $('#input_pagina').val(pag);
    $('#input_buscar').val(busca);
    $('#paginacao').click();
  }



  function buscar(filtro) {
    if (filtro === '') {
      $('#pago').val('');
    } else {
      $('#pago').val(filtro);
    }
    $('#btn_filtrar').click();
  }


  function formatarMoeda(valor) {
    // Converte o valor para um número
    valor = parseFloat(valor);

    // Formata o número com duas casas decimais e separador de milhar
    return valor.toLocaleString('pt-BR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }




   function fecharServico(id, cliente, servico, valor_servico, funcionario, nome_serv) {

    const botao = document.getElementById('btn_servico');
   $('#id_agd').val(id);
    $('#cliente_agd').val(cliente);   
    $('#servico_agd').val(servico); 
    $('#valor_serv_agd').val(valor_servico);  
    $('#funcionario_agd').val(funcionario).change();  
    $('#titulo_servico').text(nome_serv); 
    $('#descricao_serv_agd').val(nome_serv);
    $('#obs2').val(''); 

    $('#valor_serv_agd_restante').val('');
    $('#data_pgto_restante').val('');
    $('#pgto_restante').val('').change(); 
    botao.click();
  }



  // Fallback leve: evita clique "mudo" se o ajax.js ainda estiver em cache ou atrasar.
  // A implementação completa fica em app/painel/js/ajax.js.
  if (typeof window.bb_baixar_agendamento_servico !== 'function') {
    window.bb_baixar_agendamento_servico = function(event) {
      if (event && typeof event.preventDefault === 'function') {
        event.preventDefault();
      }
      var form = document.getElementById('form-servico');
      if (!form) {
        if (typeof alert === 'function') alert('Formulário de baixa não encontrado.');
        return false;
      }
      try {
        if (typeof jQuery !== 'undefined') {
          jQuery(form).trigger('submit');
          return false;
        }
      } catch(e) {}
      return false;
    };
  }

</script>



<script type="text/javascript">var pag = "<?= $pag ?>"</script>

<script type="text/javascript">
  
  function mudarData(){
    var data = $('#data-modal').val();     
    listarHorarios();
  }
</script>




<script type="text/javascript">
  function listarHorarios(){

    var funcionario = "<?=$funcionario?>";  
    var data = $('#data-modal').val(); 

    
    $.ajax({
      url: '../../sistema/painel/paginas/' + pag + "/listar-horarios.php",
      method: 'POST',
      data: {funcionario, data},
      dataType: "text",

      success:function(result){
        $("#listar-horarios").html(result);
      }
    });
  }
</script>




