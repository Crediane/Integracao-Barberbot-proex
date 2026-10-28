<?php
require_once("cabecalho.php");
require_once("rodape.php");

if($visualizar_usuario == 'Não'){
  $sql_visualizar = " and usuario = '$id_usuario'";
}else{
  $sql_visualizar = " ";
}

$data_atual = date('Y-m-d');
$pag = 'clientes';
$itens_pag = 10;

$buscar = @$_POST['buscar'];
$ativo = @$_POST['ativo']; // Adicionar esta linha para capturar o valor do filtro
$status_busca = @$_POST['status_cliente'];
$cor_status = @$_POST['cor_status'];

if (@$clientes == 'ocultar') {
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


//totalizar páginas
$query2 = $pdo->query("SELECT * from $pag where (nome like '%$buscar%' or telefone like '%$buscar%' or cpf like '%$buscar%') $sql_visualizar order by id desc");
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

<style>
  .tabradio.selected {
    background-color: #007bff !important;
    color: white !important;
  }
</style>

<div class="page-content header-clear-medium">

  <!-- BARRA DE PESQUISA-->
  <div class="content m-2 p-1">
    <div class="loginform">
      <form method="post">
<input type="hidden" name="cor_status" id="cor_status">


        <div class="search-box search-color bg-theme rounded-xl">
          <input type="text" name="buscar" id="buscar" value="<?php echo $buscar ?>" class="form_input required p-1"
            placeholder="Buscar <?php echo ucfirst($pag); ?>"
            style="background: transparent !important; width:86%; float:left; border: none !important" />
          <button id="btn_filtrar" class="limpar_botao" type="submit"><img src="images/icons/black/search.png"
              width="20px" style="float:left; margin-top: 5px"></button>
        </div>

        

      </form>
    </div>
  </div>








  <div class="card card-style">
    <div class="content">
      <?php
      $query = $pdo->query("SELECT DISTINCT c.* from $pag c 
    WHERE (c.nome LIKE '%$buscar%' OR c.telefone LIKE '%$buscar%' OR c.cpf LIKE '%$buscar%') $sql_visualizar " .
        ($ativo === 'Não' ? " AND c.ativo = 'Não'" :
          ($ativo === 'Sim' ? " AND c.ativo = 'Sim'" :
            ($ativo === '' ? " AND c.ativo = ''" :
              ($ativo === 'ina' ? " AND EXISTS (
        SELECT 1 FROM receber r 
        WHERE r.cliente = c.id 
        AND r.pago = 'Não' 
        AND r.vencimento < CURDATE()
     )" : "")))) .
        " ORDER BY c.id desc LIMIT $limite, $itens_pag");
      $res = $query->fetchAll(PDO::FETCH_ASSOC);
      $linhas = @count($res);
      if ($linhas > 0) {
        for ($i = 0; $i < $linhas; $i++) {
         
         $id = $res[$i]['id'];
  $nome = $res[$i]['nome']; 
  $data_nasc = $res[$i]['data_nasc'];
  $data_cad = $res[$i]['data_cad']; 
  $telefone = $res[$i]['telefone'];
  $endereco = $res[$i]['endereco'];
  $cartoes = $res[$i]['cartoes'];
  $data_retorno = $res[$i]['data_retorno'];
  $ultimo_servico = $res[$i]['ultimo_servico'];
  $cpf = $res[$i]['cpf'];
  $marketing = $res[$i]['marketing'];

  if ($marketing == 'Não') {
      $ocultar_mark = 'ocultar';
    } else {
      $ocultar_mark = '';
    }

  $data_cadF = implode('/', array_reverse(@explode('-', $data_cad)));
  $data_nascF = implode('/', array_reverse(@explode('-', $data_nasc)));
  $data_retornoF = implode('/', array_reverse(@explode('-', $data_retorno)));
  
  if($data_nascF == '00/00/0000'){
    $data_nascF = 'Sem Lançamento';
  }
  
  
  $whats = '55'.preg_replace('/[ ()-]+/' , '' , $telefone);

  $query2 = $pdo->query("SELECT * FROM servicos where id = '$ultimo_servico'");
  $res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
  if(@count($res2) > 0){
    $nome_servico = $res2[0]['nome'];
  }else{
    $nome_servico = 'Nenhum!';
  }


  $query2 = $pdo->query("SELECT * FROM receber where pessoa = '$id' order by id desc limit 1");
  $res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
  if(@count($res2) > 0){
    $obs_servico = $res2[0]['obs'];
    $valor_servico = $res2[0]['valor'];
    $data_servico = $res2[0]['data_lanc'];
    $valor_servico = number_format($valor_servico, 2, ',', '.');
    $data_servico = implode('/', array_reverse(@explode('-', $data_servico)));
  }else{
    $obs_servico = '';
    $valor_servico = '';
    $data_servico = '';
  }

  
  

  if($data_retorno != "" and @strtotime($data_retorno) <  @strtotime($data_atual)){
    $classe_retorno = 'text-danger';
  }else{
    $classe_retorno = '';
  }
  

          echo <<<HTML
      <div data-splide='{"autoplay":false}' class="splide single-slider slider-no-arrows slider-no-dots" id="user-slider-{$id}">
        <div class="splide__track">
          <div class="splide__list">
            <div class="splide__slide mx-3">
              <div class="d-flex">
             
                <div onclick="mostrar('{$id}','{$nome}', '{$telefone}', '{$cartoes}', '{$data_cadF}', '{$data_nascF}', '{$endereco}', '{$data_retornoF}', '{$nome_servico}', '{$obs_servico}', '{$valor_servico}', '{$data_servico}')">
                <h5 class="mt-1 mb-0" style="font-size: 13px;"> {$nome}</h5>
                <p class="font-12 mt-n2 mb-0 ">{$telefone}</p>
                <p class="font-12 mt-n2 mb-0 color-blue-dark"></p>
                <p class="font-12 mt-n2 mb-0 color-blue-dark">Retorno: <span class="">{$data_retornoF}</span></p>
                <p class="font-12 mt-n2 mb-0">Cadastrado: {$data_cadF}</p>
                </div>
                <div class="ms-auto"><span class="px-2 py-1 badge mt-4 p-2 font-12 shadow-bg shadow-bg-s" >Cartões: {$cartoes}</span></div>

                <div class="ms-auto"><span class="badge bg-blue-dark mt-0 p-1 font-8 shadow-bg-s"><i class="fa fa-arrow-right"></i></span></div>
              </div>
            </div>
            <div class="splide__slide mx-3">

              <div class="d-flex">
                <div class="ms-auto">

                 
                  <a onclick="editar('{$id}','{$nome}', '{$telefone}', '{$endereco}', '{$data_nasc}', '{$cartoes}', '{$cpf}')" href="#" class="icon icon-xs rounded-circle shadow-l bg-twitter"><i class="fa fa-edit text-white"></i></a>                  

                 

                  <a target="_blank" href="http://api.whatsapp.com/send?1=pt_BR&phone={$whats}" class="icon icon-xs rounded-circle shadow-l bg-whatsapp"><i class="fa-brands fa-whatsapp text-white"></i></a>

                  <a onclick="excluir_reg('{$id}', '{$nome}')" href="#" class="icon icon-xs rounded-circle shadow-l bg-google"><i class="bi bi-trash-fill text-white"></i></a>              

                   <a title="Ultimos Serviços" href="../../sistema/painel/rel/rel_servicos_clientes_class.php?id={$id}" class="icon icon-xs rounded-circle shadow-l bg-google"><i class="bi bi-file-pdf text-white"></i></a>  

                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
      <div class="divider mt-3 mb-3"></div>
HTML;
        }
      } else {
        echo 'Nenhum Registro Encontrado!';
      }
      ?>


      <!-- PAGINAÇÃO -->
      <nav aria-label="pagination-demo">
        <ul class="pagination pagination- justify-content-center">
          <li class="page-item">
            <a onclick="paginar('<?php echo $pag_anterior ?>', '<?php echo $buscar ?>')"
              class="page-link py-2 rounded-xs color-black bg-transparent bg-theme shadow-xl border-0 <?php echo $pag_inativa_ant ?>"
              href="#" tabindex="-1" aria-disabled="true"><i class="bi bi-chevron-left"></i></a>
          </li>
          <li class="page-item"><a class="page-link py-2 rounded-xs shadow-l border-0 color-dark"
              href="#"><?php echo @$numero_pagina ?> /
              <?php echo @$num_paginas ?></span></a>
          </li>
          <li class="page-item">
            <a onclick="paginar('<?php echo $pag_proxima ?>', '<?php echo $buscar ?>')"
              class="page-link py-2 rounded-xs color-black bg-transparent bg-theme shadow-l border-0 <?php echo $pag_inativa_prox ?>"
              href="#"><i class="bi bi-chevron-right"></i>
            </a>
          </li>
        </ul>
      </nav>


      <form method="post" style="display: none;">
        <input type="text" name="pagina" id="input_pagina">
        <input type="text" name="buscar" id="input_buscar">
        <button type="submit" id="paginacao"></button>
      </form>
    </div>
  </div>
</div>


<div class="fab" style="z-index: 100 !important; margin-bottom: 60px">
  <button onclick="limparCampos()" id="btn_novo" class="main open-popup bg-highlight" data-bs-toggle="offcanvas"
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
<div class="offcanvas offcanvas-top rounded-m offcanvas-detached" style="height:98%" id="popupForm">
  <div class="content mb-4">
    <div class="d-flex pb-2">
      <div class="align-self-center">
        <h2 align="center" class="mb-n1 font-12 color-highlight font-700 text-uppercase pt-1" id="titulo_inserir">
          INSERIR DADOS</h2>
      </div>
      <div class="align-self-center ms-auto">
        <button style="border: none; background: transparent; margin-right: 12px" data-bs-dismiss="offcanvas"
          id="btn-fechar" aria-label="Close" data-bs-dismiss="modal" type="button"><i
            class="bi bi-x-circle-fill color-red-dark font-18 me-n4"></i>
        </button>
      </div>
    </div>
    <form id="form" method="post" class="demo-animation needs-validation m-0">

      <!-- Nome -->
<div class="form-floating mb-3 position-relative">
  <i class="bi bi-person-fill position-absolute start-0 top-50 translate-middle-y ms-3"></i>
  <input type="text" class="form-control rounded-xs ps-5" id="nome" name="nome" placeholder="" required>
  <label class="color-theme ps-5">Nome</label>
  <small class="position-absolute top-50 end-0 translate-middle-y me-3 text-danger" style="font-size: 9px;">(Obrigatório)</small>
</div>


<!-- Telefone -->
<div class="form-floating mb-3 position-relative">
  <i class="bi bi-telephone-fill position-absolute start-0 top-50 translate-middle-y ms-3"></i>
  <input type="text" class="form-control rounded-xs ps-5" id="telefone" name="telefone" placeholder="" required>
  <label class="color-theme ps-5">Telefone</label>
  <small class="position-absolute top-50 end-0 translate-middle-y me-3 text-danger" style="font-size: 9px;">(Obrigatório)</small>
</div>




<div class="form-floating mb-3 position-relative">
  <i class="bi bi-file-earmark-person-fill position-absolute start-0 top-50 translate-middle-y ms-3"></i>
  <input type="text" class="form-control rounded-xs ps-5" id="cpf" name="cpf" placeholder="" >
  <label class="color-theme ps-5">CPF / CNPJ</label>
</div>


<!-- Cartões -->
<div class="form-floating mb-3 position-relative">
  <i class="bi bi-file-earmark-person-fill position-absolute start-0 top-50 translate-middle-y ms-3"></i>
  <input type="text" class="form-control rounded-xs ps-5" id="cartao" name="cartao" placeholder="" >
  <label class="color-theme ps-5">Cartões</label>
</div>


<!-- Nascimento -->
<div class="form-floating mb-3 position-relative">
  <i class="bi bi-calendar-event-fill position-absolute start-0 top-50 translate-middle-y ms-3"></i>
  <input type="text" class="form-control rounded-xs ps-5" id="data_nasc" name="data_nasc" placeholder="dd/mm/aaaa">
  <label class="color-theme ps-5">Nascimento</label>
</div>


<!-- Endereço -->
<div class="form-floating mb-3 position-relative">
  <i class="bi bi-house-door-fill position-absolute start-0 top-50 translate-middle-y ms-3"></i>
  <input type="text" class="form-control rounded-xs ps-5" id="endereco" name="endereco" placeholder="Rua x 45 Bairro...">
  <label class="color-theme ps-5">Endereço</label>
</div>




      <input type="hidden" name="id" id="id">
      <button name="btn_salvar" id="btn_salvar"
        class="btn btn-full gradient-highlight rounded-xs text-uppercase font-700 w-100 btn-s mt-4 mb-3"
        type="submit">SALVAR <i class="fa-regular fa-circle-check"></i></button>
      <button class="btn btn-full gradient-highlight rounded-xs text-uppercase font-700 w-100 btn-s mt-4 mb-3"
        type="button" id="btn_carregando" style="display: none">
        <span class="spinner-border spinner-border-sm" role="status" aria-hidden="true"></span> Carregando...
      </button>
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
      <td class="color-highlight" style="font-size: 11px;">Nome:</td>
      <td style="font-size: 11px;" id="nome_dados"></td>
    </tr>
    <tr class="border-fade-blue">
      <td class="color-highlight" style="font-size: 11px;">Data Cadastro:</td>
      <td style="font-size: 11px;" id="data_cad_dados"></td>
    </tr>
    <tr class="border-fade-blue">
      <td class="color-highlight" style="font-size: 11px;">Data Nasc.:</td>
      <td style="font-size: 11px;" id="data_nasc_dados"></td>
    </tr>
    <tr class="border-fade-blue">
      <td class="color-highlight" style="font-size: 11px;">Cartões:</td>
      <td style="font-size: 11px;" id="cartoes_dados"></td>
    </tr>
    <tr class="border-fade-blue">
      <td class="color-highlight" style="font-size: 11px;">Telefone:</td>
      <td style="font-size: 11px;" id="telefone_dados"></td>
    </tr>
    <tr class="border-fade-blue">
      <td class="color-highlight" style="font-size: 11px;">Endereço:</td>
      <td style="font-size: 11px;" id="endereco_dados"></td>
    </tr>
    <tr class="border-fade-blue">
      <td class="color-highlight" style="font-size: 11px;">Retorno:</td>
      <td style="font-size: 11px;" id="retorno_dados"></td>
    </tr>
    <tr class="border-fade-blue">
      <td class="color-highlight" style="font-size: 11px;">Serviço:</td>
      <td style="font-size: 11px;" id="servico_dados"></td>
    </tr>
    <tr class="border-fade-blue">
      <td class="color-highlight" style="font-size: 11px;">Observações:</td>
      <td style="font-size: 11px;" id="obs_dados_tab"></td>
    </tr>
    <tr class="border-fade-blue">
      <td class="color-highlight" style="font-size: 11px;">Data (Serviço):</td>
      <td style="font-size: 11px;" id="data_dados_tab"></td>
    </tr>
    <tr class="border-fade-blue">
      <td class="color-highlight" style="font-size: 11px;">Valor:</td>
      <td style="font-size: 11px;" id="valor_dados_tab"></td>
    </tr>
    
    


            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</div>




<script type="text/javascript">
  function buscar(status) {

    // Remove a classe selected de todas as abas
    document.querySelectorAll('.tabradio').forEach(tab => {
      tab.classList.remove('selected');
    });

    // Adiciona a classe selected na aba clicada
    event.target.classList.add('selected');

    // Define o valor do status e submete o formulário
    $('#ativo').val(status);
    $('#btn_filtrar_ativo').click();
  }
</script>



<script>
  function editar(id, nome, telefone, endereco, data_nasc, cartoes, cpf) {
    $('#mensagem').text('');
    $('#titulo_inserir').text('EDITAR REGISTRO');

   $('#id').val(id);
    $('#nome').val(nome);   
    $('#telefone').val(telefone);   
    $('#endereco').val(endereco);
    $('#data_nasc').val(data_nasc);
    $('#cartao').val(cartoes);
    $('#cpf').val(cpf);

    $('#btn_novo_editar').click();
  }
</script>



<script type="text/javascript">
  function mostrar(id, nome, telefone, cartoes, data_cad, data_nasc, endereco, retorno, servico, obs, valor, data) {
    const botao = document.getElementById('btn_mostrar');
    
              
      $('#nome_dados').text(nome);    
    $('#data_cad_dados').text(data_cad);
    $('#data_nasc_dados').text(data_nasc);
    $('#cartoes_dados').text(cartoes);
    $('#telefone_dados').text(telefone);
    $('#endereco_dados').text(endereco);
    $('#retorno_dados').text(retorno);    
    $('#servico_dados').text(servico);
    $('#obs_dados_tab').text(obs);
    $('#servico_dados_tab').text(servico);
    $('#data_dados_tab').text(data);
    $('#valor_dados_tab').text(valor);          
    

    botao.click();
  }


  function limparCampos() {
   $('#id').val('');
    $('#nome').val('');
    $('#telefone').val('');
    $('#endereco').val('');
    $('#data_nasc').val('');
    $('#cartao').val('0');
    $('#cpf').val('');

  }


  function paginar(pag, busca) {
    $('#input_pagina').val(pag);
    $('#input_buscar').val(busca);
    $('#paginacao').click();
  }
</script>


<script>
  // Função para mostrar o placeholder ao focar no campo
  function showPlaceholder(input) {
    input.setAttribute("placeholder", "https://www.dominio.com.br"); // Adiciona o placeholder quando o campo é focado
  }

  // Função para remover o placeholder ao desfocar, caso o campo esteja vazio
  function hidePlaceholder(input) {
    if (input.value === "") {
      input.removeAttribute("placeholder"); // Remove o placeholder quando o campo está vazio
    }
  }
</script>




<script type="text/javascript">var pag = "<?= $pag ?>"</script>

