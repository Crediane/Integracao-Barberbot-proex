<?php
require_once("cabecalho.php");
require_once("rodape.php");

$funcionario = $id_usuario;
$data_atual = date('Y-m-d');

$pag = 'minhas_comissoes';
$itens_pag = 10;

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

$checked_vencidas = '';
$checked_pagas = '';
$checked_pendentes = '';
$checked_todas = '';
if (@$_POST['pago'] == "Vencidas") {
  $checked_vencidas = 'true';
} else if (@$_POST['pago'] == "Sim") {
  $checked_pagas = 'true';
} else if (@$_POST['pago'] == "Não") {
  $checked_pendentes = 'true';
} else {
  $checked_todas = 'true';
}

if ($dataInicial == "") {
  $dataInicial = $data_inicio_mes;
}

if ($dataFinal == "") {
  $dataFinal = $data_final_mes;
}

$total_pago = 0;
$total_pendentes = 0;

$total_pagoF = 0;
$total_pendentesF = 0;

//totalizar páginas
if ($pago == 'Vencidas') {
  $query2 = $pdo->query("SELECT * from pagar where data_venc < curDate() and pago != 'Sim' and funcionario = '$funcionario' and tipo = 'Comissão' ORDER BY pago asc, data_venc asc");
} else {
  $query2 = $pdo->query("SELECT * from pagar where data_venc >= '$dataInicial' and data_venc <= '$dataFinal' and pago LIKE '%$pago%' and funcionario = '$funcionario' and tipo = 'Comissão' ORDER BY pago asc, data_venc asc");
}



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
  
    <div class="">
      <div class="content">
        <div class="tabs tabs-pill" id="tab-group-2">
          <div class="tab-controls rounded-m p-1">
            <a class="font-12 rounded-m tabradio" data-bs-toggle="collapse" href="#tab-4"
              aria-expanded="<?php echo $checked_todas ?>" name="tabs" id="tabone" onclick="buscar('')">Todas</a>
            <a class="font-12 rounded-m tabradio" data-bs-toggle="collapse" href="#tab-5"
              aria-expanded="<?php echo $checked_pagas ?>" name="tabs" id="tabtwo" onclick="buscar('Sim')">Pagas</a>
            <a class="font-12 rounded-m tabradio" data-bs-toggle="collapse" href="#tab-x"
              aria-expanded="<?php echo $checked_pendentes ?>" name="tabs" id="tabthree"
              onclick="buscar('Não')">Pedentes</a>
            <a class="font-12 rounded-m tabradio" data-bs-toggle="collapse" href="#tab-6"
              aria-expanded="<?php echo $checked_vencidas ?>" name="tabs" id="tab4"
              onclick="buscar('Vencidas')">Vencidas</a>
          </div>
          <div class="mt-3"></div>
          <div class="collapse show tab ocultar" id="tab-4" data-bs-parent="#tab-group-2">
            <p>1</p>
          </div>
          <div class="collapse tab ocultar" id="tab-5" data-bs-parent="#tab-group-2">
            <p>2</p>
          </div>
          <div class="collapse tab ocultar" id="tab-x" data-bs-parent="#tab-group-2">
            <p>3</p>
          </div>
          <div class="collapse tab ocultar" id="tab-6" data-bs-parent="#tab-group-2">
            <p>4</p>
          </div>
        </div>
      </div>
    </div>
  
    <input type="hidden" name="pago" id="pago">
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
      $total_pago = 0;
$total_a_pagar = 0;
$total_pendente = 0;
  $total_pagoF = 0;
  $total_pendentesF = 0;

      if ($pago == 'Vencidas') {
        $query = $pdo->query("SELECT * from pagar where data_venc < curDate() and pago != 'Sim' and funcionario = '$funcionario' and tipo = 'Comissão' ORDER BY pago asc, data_venc asc");
      } else {
        $query = $pdo->query("SELECT * from pagar where data_venc >= '$dataInicial' and data_venc <= '$dataFinal' and pago LIKE '%$pago%' and funcionario = '$funcionario' and tipo = 'Comissão' ORDER BY pago asc, data_venc asc LIMIT $limite, $itens_pag");
      }
      $valor_pago = 0;
      $valor_pendentes = 0;
      $res = $query->fetchAll(PDO::FETCH_ASSOC);
      $linhas = @count($res);
      if ($linhas > 0) {
        for ($i = 0; $i < $linhas; $i++) {
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
  $funcionario = $res[$i]['funcionario'];
  $cliente = $res[$i]['cliente'];
  
  $pago = $res[$i]['pago'];
  $servico = $res[$i]['servico'];
  
  $valorF = number_format($valor, 2, ',', '.');
  $data_lancF = implode('/', array_reverse(@explode('-', $data_lanc)));
  $data_pgtoF = implode('/', array_reverse(@explode('-', $data_pgto)));
  $data_vencF = implode('/', array_reverse(@explode('-', $data_venc)));
  

    $query2 = $pdo->query("SELECT * FROM clientes where id = '$pessoa'");
    $res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
    $total_reg2 = @count($res2);
    if($total_reg2 > 0){
      $nome_pessoa = $res2[0]['nome'];
      $telefone_pessoa = $res2[0]['telefone'];
    }else{
      $nome_pessoa = 'Nenhum!';
      $telefone_pessoa = 'Nenhum';
    }


    $query2 = $pdo->query("SELECT * FROM usuarios where id = '$usuario_baixa'");
    $res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
    $total_reg2 = @count($res2);
    if($total_reg2 > 0){
      $nome_usuario_pgto = $res2[0]['nome'];
    }else{
      $nome_usuario_pgto = 'Nenhum!';
    }



    $query2 = $pdo->query("SELECT * FROM clientes where id = '$cliente'");
    $res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
    $total_reg2 = @count($res2);
    if($total_reg2 > 0){
      $nome_cliente = $res2[0]['nome'];
    }else{
      $nome_cliente = 'Nenhum!';
    }



    $query2 = $pdo->query("SELECT * FROM usuarios where id = '$usuario_lanc'");
    $res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
    $total_reg2 = @count($res2);
    if($total_reg2 > 0){
      $nome_usuario_lanc = $res2[0]['nome'];
    }else{
      $nome_usuario_lanc = 'Sem Referência!';
    }



    $query2 = $pdo->query("SELECT * FROM usuarios where id = '$funcionario'");
    $res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
    $total_reg2 = @count($res2);
    if($total_reg2 > 0){
      $nome_func = $res2[0]['nome'];
    }else{
      $nome_func = 'Sem Referência!';
    }


    $query2 = $pdo->query("SELECT * FROM servicos where id = '$servico'");
    $res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
    $total_reg2 = @count($res2);
    if($total_reg2 > 0){
      $nome_serv = $res2[0]['nome'];
    }else{
      $nome_serv = 'Sem Referência!';
    }


    if($pago != 'Sim'){
      $classe_alerta = 'gradient-red';
      $data_pgtoF = 'Pendente';
      $visivel = '';
      $total_a_pagar += $valor;
      $total_pendente += 1;
    }else{
      $classe_alerta = 'gradient-green';
      $visivel = 'ocultar';
      $total_pago += $valor;
    }


      //extensão do arquivo
$ext = pathinfo($foto, PATHINFO_EXTENSION);
if($ext == 'pdf'){
  $tumb_arquivo = 'pdf.png';
}else if($ext == 'rar' || $ext == 'zip'){
  $tumb_arquivo = 'rar.png';
}else{
  $tumb_arquivo = $foto;
}
    

if($data_venc < $data_hoje and $pago != 'Sim'){
  $classe_debito = 'vermelho-escuro';
}else{
  $classe_debito = '';
}
    


if($pago == 'Sim'){
   $classe_pago = 'verde.jpg';
  }else{
    $classe_pago = 'vermelho.jpg';
  }

  $total_pagoF = number_format($total_pago, 2, ',', '.');
  $total_pendentesF = number_format($total_a_pagar, 2, ',', '.');

          echo <<<HTML


<div data-splide='{"autoplay":false}' class="splide single-slider slider-no-arrows slider-no-dots" id="user-slider-{$id}">
  <div class="splide__track">
    <div class="splide__list">
      <div class="splide__slide mx-3">
        <div class="d-flex">
          <div
            onclick="mostrar('{$descricao}', '{$valorF}', '{$data_lancF}', '{$data_vencF}',  '{$data_pgtoF}', '{$nome_usuario_lanc}', '{$nome_usuario_pgto}', '{$tumb_arquivo}', '{$nome_pessoa}', '{$foto}', '{$telefone_pessoa}', '{$nome_func}')">
            
            <h5 class="mt-0 mb-1" style="font-size: 12px;"><img src="images/{$classe_pago}" width="12px" style="float:left; margin-right: 2px; margin-top: 3px">{$nome_serv}</h5>
            <p class="font-10 mt-n2 mb-0"> Cliente: {$nome_cliente} </p>
            <p class="font-10 mt-n2 mb-n2">Data: {$data_lancF}</p>

          </div>
          <div class="ms-auto"><span class="slider-next "><span class="badge px-2 py-1 {$classe_alerta} shadow-bg shadow-bg-s">R$ {$valorF}</span></span>
          </div>
        </div>
      </div>
      <div class="splide__slide mx-3">
        <div class="d-flex">
          <div
            onclick="mostrar('{$descricao}', '{$valorF}', '{$data_lancF}', '{$data_vencF}',  '{$data_pgtoF}', '{$nome_usuario_lanc}', '{$nome_usuario_pgto}', '{$tumb_arquivo}', '{$nome_pessoa}', '{$foto}', '{$telefone_pessoa}', '{$nome_func}')">
           
          </div>
          <div class="ms-auto">            

            <a onclick="excluir_reg('{$id}', '{$descricao}')" href="#" class=" icon icon-xs rounded-circle shadow-l bg-google"><i class="bi bi-trash-fill text-white"></i></a>
           

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

      <div align="right" style="font-size:13px; margin-top: 10px">
        <span style="margin-right: 10px">Total Pendentes <span style="color:red">R$ <?php echo $total_pendentesF ?>
          </span></span>
        <span>Total Pago <span style="color:green">R$ <?php echo $total_pagoF ?> </span></span>
      </div>

      
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
    <form id="form" method="post" class="demo-animation needs-validation m-0">
    <div class="form-floating mb-3 position-relative">
        <i class="fa-solid fa-pen position-absolute start-0 top-50 translate-middle-y ms-3"></i>
        <input type="text" class="form-control rounded-xs ps-5" id="descricao" name="descricao" placeholder="" required>
        <label for="nome" class="color-theme ps-5">Descrição</label>
        <small class="position-absolute top-50 end-0 translate-middle-y me-3 text-danger"
          style="font-size: 9px;">(Obrigatório)</small>
      </div>
      
    
      <div class="form-floating mb-3 position-relative">
        <i class="bi bi-currency-dollar position-absolute start-0 top-50 translate-middle-y ms-3"></i>
        <input type="text" class="form-control rounded-xs ps-5" id="valor" name="valor" placeholder=""
         oninput="mascaraMoeda(this)" required>
        <label class="color-theme ps-5">Valor</label>
        <small class="position-absolute top-50 end-0 translate-middle-y me-3 text-danger"
          style="font-size: 9px;">(Obrigatório)</small>
      </div>
     
      <div class="form-floating mb-3 position-relative">
        <i class="bi bi-calendar position-absolute start-0 top-50 translate-middle-y ms-3"></i>
        <input type="date" class="form-control rounded-xs ps-5" id="data_venc" name="data_venc"
          value="<?php echo $data_hoje ?>" required>
        <label class="color-theme ps-5">Vencimento</label>
        <small class="position-absolute top-50 end-0 translate-middle-y me-3 text-danger"
          style="font-size: 9px;">(Obrigatório)</small>
      </div>
    
    
   
      <div class="form-floating mb-3 position-relative">
        <i class="bi bi-pencil-fill position-absolute start-0 top-50 translate-middle-y ms-3"></i>
        <input type="text" class="form-control rounded-xs ps-5" id="obs" name="obs" placeholder="">
        <label class="color-theme ps-5">Observações</label>
      </div>
     
      
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
    <td class="color-highlight" style="font-size: 11px;">Valor:</td>
    <td style="font-size: 11px;" id="valor_dados"></td>
  </tr>

  <tr class="border-fade-blue">
    <td class="color-highlight" style="font-size: 11px;">Data Lançamento:</td>
    <td style="font-size: 11px;" id="data_lanc_dados"></td>
  </tr>

  <tr class="border-fade-blue">
    <td class="color-highlight" style="font-size: 11px;">Vencimento:</td>
    <td style="font-size: 11px;" id="data_venc_dados"></td>
  </tr>

  <tr class="border-fade-blue">
    <td class="color-highlight" style="font-size: 11px;">Data PGTO:</td>
    <td style="font-size: 11px;" id="data_pgto_dados"></td>
  </tr>

  <tr class="border-fade-blue">
    <td class="color-highlight" style="font-size: 11px;">Lançado Por:</td>
    <td style="font-size: 11px;" id="usuario_lanc_dados"></td>
  </tr>

  <tr class="border-fade-blue">
    <td class="color-highlight" style="font-size: 11px;">Pago Por:</td>
    <td style="font-size: 11px;" id="usuario_baixa_dados"></td>
  </tr>

  <tr class="border-fade-blue">
    <td class="color-highlight" style="font-size: 11px;">Pessoa:</td>
    <td style="font-size: 11px;" id="pessoa_dados"></td>
  </tr>

  <tr class="border-fade-blue">
    <td class="color-highlight" style="font-size: 11px;">Telefone:</td>
    <td style="font-size: 11px;" id="telefone_dados"></td>
  </tr>

  <tr class="border-fade-blue">
    <td class="color-highlight" style="font-size: 11px;">Funcionário:</td>
    <td style="font-size: 11px;" id="nome_func_dados"></td>
  </tr>

  <tr class="border-fade-blue">
    <td class="color-highlight" style="font-size: 11px;">Comprovante:</td>
    <td style="font-size: 11px;">
      <a id="link_mostrar" href="#" target="_blank" class="text-primary">Abrir</a>
    </td>
  </tr>

  <tr class="border-fade-blue">
    <td class="color-highlight" style="font-size: 11px;">Prévia:</td>
    <td>
      <img id="target_mostrar" src="" alt="Comprovante" style="max-height: 60px;">
    </td>
  </tr>
</tbody>


          </table>
         
        </div>
      </div>
    </div>
  </div>
</div>



<!--BOTÃO PARA CHAMAR A MODAL ARQUIVOS -->
<a style="display:none" href="#" data-bs-toggle="offcanvas" data-bs-target="#menu-share-arquivos"
  class="list-group-item" id="btn_arquivos" data-bs-target="#staticBackdrop">
</a>

<!-- MODAL ARQUIVOS -->
<div class="offcanvas offcanvas-top rounded-m offcanvas-detached" style="height:70%;" id="menu-share-arquivos">
  <div class="content ">
    <div class="d-flex pb-0">
      <div class="align-self-center">
        <h1 class="font-14 color-highlight font-700 text-uppercase" id="titulo_arquivo"></h1>
      </div>
      <div class="align-self-center ms-auto">
        <a href="#" data-bs-dismiss="offcanvas" class="icon icon-m"><i
            class="bi bi-x-circle-fill color-red-dark font-18 me-n4"></i></a>
      </div>
    </div>
    <div class="card overflow-visible rounded-xs">
      <div class="content mb-1">
        <div class="table-responsive">
          <table class="table color-theme mb-4">
            <form id="form_arquivos" method="post" style="padding-bottom: 17px; border:1px solid #bdbbbb">
              <div align="center" onclick="arquivo_conta.click()" style="padding-top: 10px; padding-bottom: 10px">
                <img src="images/sem-foto.png" width="85px" id="target-arquivos"><br>
                <img src="images/icone-arquivo.png" width="85px" style="margin-top: -12px">
              </div>
              <input onchange="carregarImgArquivos()" type="file" name="arquivo_conta" id="arquivo_conta"
                hidden="hidden">
             <div class="form-floating mb-3 position-relative">
                  <i class="bi bi-pencil-fill position-absolute start-0 top-50 translate-middle-y ms-3"></i>
                  <input type="text" class="form-control rounded-xs ps-5" id="nome_arq" name="nome_arq" placeholder="" required>
                  <label class="color-theme ps-5">Nome do Arquvio</label>
                </div>
              <button name="btn_salvar_arquivo" id="btn_salvar_arquivo"
                class="btn btn-full gradient-green rounded-xs text-uppercase w-100 btn-s mt-2" type="submit">Salva <i
                  class="fa-regular fa-circle-check"></i></button>
              <button class="btn btn-full gradient-highlight rounded-xs text-uppercase font-700 w-100 btn-s mt-4 mb-3"
                type="button" id="btn_carregando_arquivo" style="display: none">
                <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Carregando...
              </button>
              <input type="hidden" name="id_arquivo" id="id-arquivo">
            </form>
            <div id="listar-arquivos"></div>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>






<script type="text/javascript">
  function mostrar(descricao, valor, data_lanc, data_venc, data_pgto, usuario_lanc, usuario_pgto, foto, pessoa, link, telefone, func) {

    const botao = document.getElementById('btn_mostrar');
  

    $('#titulo_dados').text(descricao);

    $('#valor_dados').text(valor);
    $('#data_lanc_dados').text(data_lanc);
    $('#data_venc_dados').text(data_venc);
    $('#data_pgto_dados').text(data_pgto);
    $('#usuario_lanc_dados').text(usuario_lanc);
    $('#usuario_baixa_dados').text(usuario_pgto);
    $('#pessoa_dados').text(pessoa);
    $('#telefone_dados').text(telefone);
    $('#nome_func_dados').text(func);
    
    $('#link_mostrar').attr('href','../../sistema/painel/img/contas/' + link);
    $('#target_mostrar').attr('src','../../sistema/painel/img/contas/' + foto);
   
   
    botao.click();
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



</script>





<script type="text/javascript">var pag = "<?= $pag ?>"</script>