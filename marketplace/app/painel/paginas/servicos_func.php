<?php
require_once("cabecalho.php");
require_once("rodape.php");


$data_atual = date('Y-m-d');
$pag = 'funcionarios';
$itens_pag = 10;

$buscar = @$_POST['buscar'];
$ativo = @$_POST['ativo']; // Adicionar esta linha para capturar o valor do filtro
$status_busca = @$_POST['status_cliente'];
$cor_status = @$_POST['cor_status'];

$id_func = $id_usuario;

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

?>

<style>
  .tabradio.selected {
    background-color: #007bff !important;
    color: white !important;
  }
</style>

<div class="page-content header-clear-medium">



  <div class="card card-style">
    <div class="content">
      <?php
     $query = $pdo->query("SELECT * FROM servicos_func where funcionario = '$id_func' ORDER BY id asc");
      $res = $query->fetchAll(PDO::FETCH_ASSOC);
      $linhas = @count($res);
      if ($linhas > 0) {
        for ($i = 0; $i < $linhas; $i++) {
         
      $id = $res[$i]['id'];
  $servico = $res[$i]['servico'];
  
  
$query2 = $pdo->query("SELECT * FROM servicos where id = '$servico'");
$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);  
$nome_servico = @$res2[0]['nome'];

          echo <<<HTML
      <div data-splide='{"autoplay":false}' class="splide single-slider slider-no-arrows slider-no-dots" id="user-slider-{$id}">
        <div class="splide__track">
          <div class="splide__list">
            <div class="splide__slide mx-3">
              <div class="d-flex">
             
                <div>
                <h5 class="mt-1 mb-0" style="font-size: 13px;"> {$nome_servico}</h5>     
                </div>   

                 <div class="ms-auto"><span class="badge bg-blue-dark mt-0 p-1 font-8 shadow-bg-s"><i class="fa fa-arrow-right"></i></span></div>        

               
              </div>
            </div>
            <div class="splide__slide mx-3">

              <div class="d-flex">
                <div class="ms-auto">

                  <a onclick="excluir_servico('{$id}', '{$nome_servico}')" href="#" class="icon icon-xs rounded-circle shadow-l bg-google"><i class="bi bi-trash-fill text-white"></i></a>           
                     

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
    <form id="form_servico" method="post" class="demo-animation needs-validation m-0">

  

  <div class="row">

  <!-- Dia da semana -->
  <div class="col-12">
    <div class="form-floating mb-3 position-relative">
      <i class="bi bi-calendar-event-fill position-absolute start-0 top-50 translate-middle-y ms-3"></i>
      <select class="sel3" id="servico" name="servico" required style="width:100%">
         <?php 
                                    $query = $pdo->query("SELECT * FROM servicos ORDER BY nome asc");
                                    $res = $query->fetchAll(PDO::FETCH_ASSOC);
                                    $total_reg = @count($res);
                                    if($total_reg > 0){
                                        for($i=0; $i < $total_reg; $i++){
                                            foreach ($res[$i] as $key => $value){}
                                                echo '<option value="'.$res[$i]['id'].'">'.$res[$i]['nome'].'</option>';
                                        }
                                    }
                                    ?>
      </select>
      
    </div>
  </div>

  

<input type="hidden" name="id" id="id_dias" value="<?php echo $id_usuario ?>">


      <input type="hidden" name="id_d" id="id">
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

<script type="text/javascript">
  
// ALERT EXCLUIR #######################################
function excluir_servico(id) {
    const swalWithBootstrapButtons = Swal.mixin({
        customClass: {
            confirmButton: "btn btn-success", // Adiciona margem à direita do botão "Sim, Excluir!"
            cancelButton: "btn btn-danger me-1",
            container: 'swal-whatsapp-container'
        },
        buttonsStyling: false
    });

    swalWithBootstrapButtons.fire({
        title: "Deseja Excluir?",
        text: "Você não conseguirá recuperá-lo novamente!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sim, Excluir!",
        cancelButtonText: "Não, Cancelar!",
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Realiza a requisição AJAX para excluir o item
            $.ajax({
                url: '../../sistema/painel/paginas/' + pag + "/excluir-servico.php",
                method: 'POST',
                data: { id, loja: (window.BB_TENANT_SLUG || '') },
                dataType: "html",
                success: function (mensagem) {
                    if (mensagem.trim() == "Excluído com Sucesso") {
                        // Exibe mensagem de sucesso após a exclusão
                        swalWithBootstrapButtons.fire({
                            title: 'Sucesso!',
                            text: 'Excluido com sucesso!',
                            icon: "success",
                            timer: 2000,
                            timerProgressBar: true,
                            confirmButtonText: 'OK',

                        });
                       setTimeout(function () {
              location.reload();
            }, 2000); // 2000 milissegundos = 2 segundos
                    } else {
                        // Exibe mensagem de erro se a requisição falhar
                        swalWithBootstrapButtons.fire({
                            title: "Opss!",
                            text: mensagem,
                            icon: "error",
                            confirmButtonText: 'OK',
                        });
                    }
                }
            });
        } else if (result.dismiss === Swal.DismissReason.cancel) {
            swalWithBootstrapButtons.fire({
                title: "Cancelado",
                text: "Fecharei em 1 segundo.",
                icon: "error",
                timer: 1000,
                timerProgressBar: true,
            });
        }
    });
}

</script>