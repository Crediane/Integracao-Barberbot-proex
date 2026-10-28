<?php require_once("cabecalho.php") ?>
<?php 
$query = $pdo->query("SELECT * FROM textos_index ORDER BY id asc");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);
if($total_reg > 0){
 ?>
    <!-- slider section -->
    <section class="slider_section ">
      <div id="customCarousel1" class="carousel slide" data-ride="carousel">
        <div class="carousel-inner">

<?php 
for($i=0; $i < $total_reg; $i++){
  foreach ($res[$i] as $key => $value){}
  $id = $res[$i]['id'];
  $titulo = $res[$i]['titulo'];
  $descricao = $res[$i]['descricao'];

  $descricaoF = mb_strimwidth($descricao, 0, 50, "...");

  if($i == 0){
    $ativo = 'active';
  }else{
    $ativo = '';
  }
 ?>

          <div class="carousel-item <?php echo $ativo ?>">
            <div class="container ">
              <div class="row">
                <div class="col-md-6 ">
                  <div class="detail-box">
                    <h1>
                     <?php echo $titulo ?>
                    </h1>
                    <p>
                     <?php echo $descricao ?>
                    </p>
                    <div class="btn-box">
                      <a href="http://api.whatsapp.com/send?1=pt_BR&phone=<?php echo $tel_whatsapp ?>" target="_blank" class="btn1">
                        Contate-nos
                      </a>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>
<?php 
}
 ?>

          
        </div>
        <div class="container">
          <div class="carousel_btn-box">
            <a class="carousel-control-prev" href="#customCarousel1" role="button" data-slide="prev">
              <i class="fa fa-arrow-left" aria-hidden="true"></i>
              <span class="sr-only">Previous</span>
            </a>
            <a class="carousel-control-next" href="#customCarousel1" role="button" data-slide="next">
              <i class="fa fa-arrow-right" aria-hidden="true"></i>
              <span class="sr-only">Next</span>
            </a>
          </div>
        </div>
      </div>
    </section>
    <!-- end slider section -->

  <?php } ?>

  </div>


  <!-- product section -->

  <section class="product_section layout_padding">
    <div class="container">
      <div class="heading_container heading_center ">
        <h2 class="">
          Nossos Serviços
        </h2>
        <p class="col-lg-8 px-0">
          <?php 
          $query = $pdo->query("SELECT * FROM cat_servicos ORDER BY id asc");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);
if($total_reg > 0){ 
for($i=0; $i < $total_reg; $i++){
  foreach ($res[$i] as $key => $value){}
  $id = $res[$i]['id'];
  $nome = $res[$i]['nome'];

  echo $nome;

  if($i < ($total_reg - 1)){
    echo ' / ';
  }

}

}

$query = $pdo->query("SELECT * FROM servicos where ativo = 'Sim' ORDER BY id asc");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);
if($total_reg > 0){ 
?>
        </p>
      </div>
      <div class="product_container">
        <div class="product_owl-carousel owl-carousel owl-theme ">

<?php 
for($i=0; $i < $total_reg; $i++){
  foreach ($res[$i] as $key => $value){}
 
  $id = $res[$i]['id'];
  $nome = $res[$i]['nome'];   
  $valor = $res[$i]['valor'];
  $foto = $res[$i]['foto'];
   $valorF = number_format($valor, 2, ',', '.');
   $nomeF = mb_strimwidth($nome, 0, 20, "...");
 ?>

          <div class="item">
            <div class="box">
              <div class="img-box">
                <img src="sistema/painel/img/servicos/<?php echo $foto ?>" alt="">
              </div>
              <div class="detail-box">
                <h4>
                  <?php echo $nomeF ?>
                </h4>
                <h6 class="price">
                  <span class="new_price">
                    R$ <?php echo $valorF ?>
                  </span>
                
                </h6>
                <a href="agendamentos">
                  Agendar
                </a>
              </div>
            </div>
          </div>

<?php 
}
 ?>

         
        </div>
      </div>

    <?php } ?>
    </div>
  </section>

  <!-- product section ends -->

        <!-- About End -->


        <!-- Service Start -->
        <div class="service">
            <div class="container">
                <div class="section-header text-center">
                  
                </div>
                <div class="row">
                    <div class="col-lg-4 col-md-6">
                        <div class="service-item">
                            <div class="service-img">
                                <a href="agendamentos" target="_blank"><img src="/images/protese/protese-alan-1.png" alt="Image"></a>
                            </div> <p>
                            </p>
                            <a class="btn" href="agendamentos" target="_blank">Proteses capilares</a>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="service-item">
                            <div class="service-img">
                                <a href="agendamentos"><img src="/images/protese/protese-alan.png" alt="Image"></a>
                            </div> <p>
                            </p>
                            <a class="btn" href="agendamentos" target="_blank">Proteses capilares</a>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="service-item">
                            <div class="service-img">
                                <a href="agendamentos" target="_blank"><iframe width="560" height="270" src="https://www.youtube.com/embed/fFlDvWulc5g?si=yB_iNYND475MwuyL&amp;controls=0" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></a>
                            </div> <p>
                            </p>
                            <a class="btn" href="agendamentos" target="_blank">Troca Protese capilare</a>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="service-item">
                            <div class="service-img">
                              <a href="agendamentos" target="_blank"><iframe width="560" height="270" src="https://www.youtube.com/embed/6SM-bOIO5Mg?si=gs5lnHaHqAlAHXqa&amp;controls=0" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></a>   
                            </div> <p>
                            </p>
                            <a class="btn" href="agendamentos" target="_blank">Solução p/ vida</a>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="service-item">
                            <div class="service-img">
                                <a href="agendamentos" target="_blank"><iframe width="560" height="270" src="https://www.youtube.com/embed/xBMRCaZPQ14?si=nxVnSAgIZ55AThSI&amp;controls=0" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></a>
                            </div> <p>
                            </p>
                            <a class="btn" href="agendamentos" target="_blank">Protese autoestima</a>
                        </div>
                    </div>
                    <div class="col-lg-4 col-md-6">
                        <div class="service-item">
                            <div class="service-img">
                                 <a href="agendamentos" target="_blank"><iframe width="560" height="270" src="https://www.youtube.com/embed/3YHAua9haEA?si=6-voaiUv2CpugkgI&amp;controls=0" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe></a>
                            </div> <p>
                            </p>
                            <a class="btn" href="agendamentos" target="_blank">Sobre Protese capilare</a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <!-- Service End -->

  <!-- about section -->

  <section class="about_section" style="background: #020202; ">
    <div class="container-fluid">
      <div class="row">
        <div class="col-md-6 px-0">
          <div class="img-box ">
            <?php if($url_video != "" and $posicao_video == 'sobre'){
              echo '<iframe width="100%" height="350" src="'.$url_video.'" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>';
            }else{?>
              <img src="images/<?php echo $imagem_sobre ?>" class="box_img" alt="about img">
            <?php } ?>
          </div>
        </div>
        <div class="col-md-5">
          <div class="detail-box ">
            <div class="heading_container">
              <h1 class="" style="color: #fff;">
               Sobre Nós</a>
              </h1>
            </div>
            <p class="detail_p_mt">
              <?php echo $texto_sobre ?>
            </p>
            <a data-toggle="modal" href="#empresa" class="">
              Mais Informações
            </a>
          </div>
        </div>
      </div>
    </div>
  </section>

<div style="margin-top: 0px">
  <?php if($url_video != "" and $posicao_video == 'abaixo'){
              echo '<iframe class="video_mobile" width="100%" src="'.$url_video.'" title="YouTube video player" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture; web-share" allowfullscreen></iframe>';
            }
    ?>
  </div>

  <!-- about section ends -->

  <!-- product section -->

  <?php 
$query = $pdo->query("SELECT * FROM produtos where estoque > 0 and valor_venda >  0 ORDER BY id desc limit 8");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);
if($total_reg > 0){ 
   ?>

  <section class="product_section layout_padding">
    <div class="container-fluid">
      <div class="heading_container heading_center ">
        <h2 class="">
          Nossos Produtos
        </h2>
       
      </div>
      <div class="row">

<?php 
for($i=0; $i < $total_reg; $i++){
  foreach ($res[$i] as $key => $value){}
 
  $id = $res[$i]['id'];
  $nome = $res[$i]['nome'];   
  $valor = $res[$i]['valor_venda'];
  $foto = $res[$i]['foto'];
  $descricao = $res[$i]['descricao'];
   $valorF = number_format($valor, 2, ',', '.');
 $nomeF = mb_strimwidth($nome, 0, 23, "...");

 ?>

        <div class="col-sm-6 col-md-3">
          <div class="box">
            <div class="img-box">
              <img src="sistema/painel/img/produtos/<?php echo $foto ?>" title="<?php echo $descricao ?>">
            </div>
            <div class="detail-box">
              <h5>
               <?php echo $nomeF ?>
              </h5>
              <h6 class="price">
                <span class="new_price">
                 R$ <?php echo $valorF ?>
                </span>
               
              </h6>
              <a target="_blank" href="http://api.whatsapp.com/send?1=pt_BR&phone=<?php echo $tel_whatsapp ?>&text=Ola, gostaria de saber mais informações sobre o produto <?php echo $nome ?>">
               Comprar Agora
              </a>
            </div>
          </div>
        </div>
      
   <?php } ?>    


      </div>
      <div class="btn-box">
        <a href="produtos">
          Ver mais Produtos
        </a>
      </div>
    </div>
  </section>

<?php } ?>

  <!-- product section ends -->


 

  <!-- client section -->
<?php 
$query = $pdo->query("SELECT * FROM comentarios where ativo = 'Sim' ORDER BY id asc");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);
if($total_reg > 0){ 
 ?>
  <section class="client_section layout_padding-bottom">
    <div class="container">
      <div class="heading_container">
        <h2>
          Depoimento dos nossos Clientes
        </h2>
      </div>
      <div class="client_container">
        <div class="carousel-wrap">
          <div class="owl-carousel client_owl-carousel">

            <?php 
            for($i=0; $i < $total_reg; $i++){
          foreach ($res[$i] as $key => $value){}
 
          $id = $res[$i]['id'];
          $nome = $res[$i]['nome'];   
           $texto = $res[$i]['texto'];
           $foto = $res[$i]['foto'];   
             ?>

            <div class="item">
              <div class="box">
                <div class="img-box">
                  <img src="sistema/painel/img/comentarios/<?php echo $foto ?>" alt="" class="img-1">
                </div>
                <div class="detail-box">
                  <h5>
                    <?php echo $nome ?>
                  </h5>
                  
                  <p>
                    <?php echo $texto ?>
                  </p>
                </div>
              </div>
            </div>


<?php } ?>

          </div>
        </div>
      </div>
    </div>

     <div class="btn-box2">
        <a href="" data-toggle="modal" data-target="#modalComentario">
         Inserir Depoimento
        </a>
      </div>

  </section>

<?php } ?>

</div> <p>
</div><!--/. faq-item -->
</div>
</div>
</section><!--/. faq-section -->
<br>
<hr>

 <!-- contact section -->
  <section class="contact_section layout_padding-bottom">
    <div class="container">
      <div class="heading_container text-center">
        <h4>Transforme seu visual: 
         Pronto para um visual moderno? Vamos conversar e fazer acontecer!
        </h4>
      </div>
      <div class="row">
        <div class="col-md-6">
          <div class="form_container">
            <form id="form-email">
              <div>
                <input type="text" name="nome" placeholder="Seu Nome" required/>
              </div>
              <div>
                <input type="text" name="telefone" id="telefone" placeholder="Seu Telefone" required />
              </div>
              <div>
                <input type="email" name="email" placeholder="Seu Email" required />
              </div>
              <div>
                <input type="text" name="mensagem" class="message-box" placeholder="Mensagem" required />
              </div>
              <div class="btn_box">
                <button>
                  Enviar
                </button>
              </div>
            </form>

            <br><div id="mensagem"></div>
          </div>
        </div>
        <div class="col-md-6">
          <div class="map_container ">
           <?php echo $mapa ?>
          </div>
        </div>
      </div>
    </div>
  </section>
  <!-- end contact section -->


<!-- end client section -->

  <?php require_once("rodape.php") ?>










  <!-- Modal Depoimentos -->
  <div class="modal fade" id="modalComentario" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
      <div class="modal-content">
        <div class="modal-header">
          <h5 class="modal-title" id="exampleModalLabel">Inserir Depoimento
                   </h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        
        <form id="form">
      <div class="modal-body">

          <div class="row">
            <div class="col-md-12">
              <div class="form-group">
                <label for="exampleInputEmail1">Nome</label>
                <input type="text" class="form-control" id="nome_cliente" name="nome" placeholder="Nome" required>    
              </div>  
            </div>
            <div class="col-md-12">

              <div class="form-group">
                <label for="exampleInputEmail1">Texto <small>(Até 500 Caracteres)</small></label>
                <textarea maxlength="500" class="form-control" id="texto_cliente" name="texto" placeholder="Texto Comentário" required> </textarea>   
              </div>  
            </div>
          </div>


                

            <div class="row">
              <div class="col-md-8">            
                <div class="form-group"> 
                  <label>Foto</label> 
                  <input class="form-control" type="file" name="foto" onChange="carregarImg();" id="foto">
                </div>            
              </div>
              <div class="col-md-4">
                <div id="divImg">
                  <img src="sistema/painel/img/comentarios/sem-foto.jpg"  width="80px" id="target">                  
                </div>
              </div>

            </div>


          
            <input type="hidden" name="id" id="id">
             <input type="hidden" name="cliente" value="1">

          <br>
          <small><div id="mensagem-comentario" align="center"></div></small>
        </div>

        <div class="modal-footer">      
          <button type="submit" class="btn btn-primary">Inserir</button>
        </div>
      </form>

      </div>
    </div>
  </div>








<script type="text/javascript">
  
$("#form-email").submit(function () {

    event.preventDefault();
    var formData = new FormData(this);

    $.ajax({
        url: 'ajax/enviar-email.php',
        type: 'POST',
        data: formData,

        success: function (mensagem) {
            $('#mensagem').text('');
            $('#mensagem').removeClass()
            if (mensagem.trim() == "Enviado com Sucesso") {
               $('#mensagem').addClass('text-success')
                $('#mensagem').text(mensagem)

            } else {

                $('#mensagem').addClass('text-danger')
                $('#mensagem').text(mensagem)
            }


        },

        cache: false,
        contentType: false,
        processData: false,

    });

});


</script>



<script type="text/javascript">
  function carregarImg() {
    var target = document.getElementById('target');
    var file = document.querySelector("#foto").files[0];
    
        var reader = new FileReader();

        reader.onloadend = function () {
            target.src = reader.result;
        };

        if (file) {
            reader.readAsDataURL(file);

        } else {
            target.src = "";
        }
    }
</script>



<script type="text/javascript">
  
$("#form").submit(function () {

    event.preventDefault();
    var formData = new FormData(this);


    $.ajax({
        url: 'sistema/painel/paginas/comentarios/salvar.php',
        type: 'POST',
        data: formData,

        success: function (mensagem) {
            $('#mensagem-comentario').text('');
            $('#mensagem-comentario').removeClass()
            if (mensagem.trim() == "Salvo com Sucesso") {
            
            $('#mensagem-comentario').addClass('text-success')
                $('#mensagem-comentario').text('Comentário Enviado para Aprovação!')
                 $('#nome_cliente').val('');
                  $('#texto_cliente').val('');

            } else {

                $('#mensagem-comentario').addClass('text-danger')
                $('#mensagem-comentario').text(mensagem)
            }


        },

        cache: false,
        contentType: false,
        processData: false,

    });

});


</script>




<!-- Modal Empresa -->
<div id="empresa" class="modal fade" role="dialog">
<div class="modal-dialog modal-lg">

<div class="modal-content">
<form method="POST" action="">
<div class="modal-header">
<h1 class="modal-title"><small>Jacy Cabeleireiro</small></h1>
<button type="submit" class="close" name="fecharModal">&times;</button>
</div>
</form>
<div class="modal-body">
<p class="text-muted"><small>
Nossa missão é oferecer os melhores cursos para você cliente, sempre com agilidade, responsabilidade. Curso de Cabeleireiro, Barbeiro, Colorimétrica, Escova Progressiva muito mais... Se destaca por seus Cursos e pela Qualidade de Ensino nas diversas áreas no Segmento de Beleza; Situada na grande Cascavel. Jacy Cabeleireiro passou por várias modificações no intuito de atender com excelência seus alunos e clientes atualmente. Jacy Cabeleireiro é considerada referência de Ensino para vários Salões de Cabeleireiro, tendo colocado no mercado de trabalho vários Cabeleireiros da Grande Cascavel e Região.</small>
</p>
<p><small>Buscamos atender e ensinar tendo como premissas a Qualidade e a Agilidade, pois sabemos que a clientela de beleza é exigente e possui cada vez menos tempo. Respeitamos a iniciativa individual de cada aluno para um aprendizado completamente focado na realidade do mercado de trabalho e em todas as novidades técnicas e tecnológicas para a área de beleza, acreditamos que o conhecimento surge da criatividade, do reinventar e da vocação de nossos alunos em sintonia com nossos Educadores. <br>Eu não tenho dúvida que você também consiga aprender e se transformar. Eu acredito em você. Acredite também.
Tome um passo importante na vida HOJE! Amanhã eu faço, amanhã eu procuro, amanhã, amanhã. Nada na história do mundo foi feito amanhã!
Eu vou ajudar você e se tornar um ou uma Cabeleireiro.</small> </p>
<p class="text-muted"><small>
Dados. Jacy Cordeiro01702950956 - Cnpj: 36.896.614/0001-40 - Nire: 41 8 099789-3.<br>
Assista o vídeo para entender um pouco melhor, se você ainda não é um aluno tenho certeza que se tornará, nossos cursos e nossa didádica estão entre as melhores, sem falar nos preços dos cursos que são muito em conta.</small>
</p>
<iframe width="100%" height="500" src="https://www.youtube-nocookie.com/embed/E_tBbvQJ1mA?controls=0" frameborder="0" allow="accelerometer; autoplay; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
<p class="text-muted" align="center"><small>
"As muitas águas não podem apagar este amor, nem os rios afogá-lo; ainda que alguém desse todos os bens de sua casa pelo amor, certamente o desprezariam."</small>
</p>
</div>
</div>
</div>
</div>

