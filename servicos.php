<?php require_once("cabecalho.php") ?>
<style type="text/css">
  /* mantém o comportamento do herói nas subpáginas */
  .sub_page .hero_area {
    min-height: 96px !important;
    overflow: visible !important;
  }
  @media (max-width: 991px) {
    .sub_page .hero_area { min-height: 86px !important; }
  }

  /* --- tema escuro da seção --- */
  .product_section {
    background:#0b0b0d;  /* fundo padrão escuro */
    color:#fff;          /* textos brancos */
  }
  .product_section .heading_container h2,
  .product_section .heading_container p { color:#fff; }

  /* zera qualquer fundo claro do grid */
  .product_section .row { background:transparent !important; }

  /* cards */
  .product_section .box{
    background:transparent;
    border:1px solid rgba(255,255,255,.12);
    border-radius:14px;
    overflow:hidden;
    transition:transform .15s ease, border-color .15s ease;
  }
  .product_section .box:hover{
    transform:translateY(-2px);
    border-color:rgba(255,255,255,.25);
  }

  .product_section .img-box{
    background:#000;
  }
  .product_section .img-box img{
    width:100%;
    height:auto;
    display:block;
    object-fit:cover;
  }

  .product_section .detail-box{ padding:12px; }
  .product_section .detail-box h5{ color:#fff; margin:0 0 6px; }
  .product_section .price,
  .product_section .price .new_price{ color:#fff; }

  /* link "Agendar" como botão outline claro */
  .product_section .detail-box a{
    display:inline-block;
    margin-top:8px;
    padding:8px 14px;
    border:1px solid rgba(255,255,255,.35);
    border-radius:10px;
    color:#fff;
    text-decoration:none;
    font-weight:700;
  }
  .product_section .detail-box a:hover{
    border-color:#fff;
  }
</style>
</div>

<section class="product_section layout_padding">
  <div class="container-fluid">
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

    <div class="row"><!-- fundo transparente no CSS acima -->
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
      <div class="col-sm-6 col-md-3">
        <div class="box">
          <div class="img-box">
            <img src="<?php echo bb_img_src('sistema/painel/img/servicos/' . $foto); ?>" title="<?php echo $descricao ?>">
          </div>
          <div class="detail-box">
            <h5><?php echo $nomeF ?></h5>
            <h6 class="price">
              <span class="new_price">R$ <?php echo $valorF ?></span>
            </h6>
            <a href="<?php echo $link_agendamentos; ?>">Agendar</a>
          </div>
        </div>
      </div>
      <?php } ?>
    </div>

    <?php } ?>
  </div>
</section>

<!-- product section ends -->

<?php require_once("rodape.php") ?>
