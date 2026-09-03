<?php
require_once __DIR__ . '/../tenant_context.php';


$id_usuario = (int)($_SESSION['id'] ?? 0);

if (@$_SESSION['nivel'] != 'Administrador') {
  require_once __DIR__ . '/../../sistema/painel/verificar-permissoes.php';
}



$queryUsuario = $pdo->prepare('SELECT nome, nivel, foto, atendimento FROM usuarios WHERE id = :id LIMIT 1');
$queryUsuario->execute([':id' => $id_usuario]);
$usuarioMenu = $queryUsuario->fetch(PDO::FETCH_ASSOC);

$nome_usuario = (string)($usuarioMenu['nome'] ?? '');
$nivel_usuario = (string)($usuarioMenu['nivel'] ?? '');
$foto_usuario = (string)($usuarioMenu['foto'] ?? 'sem-foto.png');
$atendimento = (string)($usuarioMenu['atendimento'] ?? 'Não');
?>
<div class="bg-theme mx-3 rounded-m shadow-m mt-3 mb-3">
  <div class="d-flex px-2 pb-2 pt-2">
    <div>
      <a href="#"><img src="../../sistema/painel/img/perfil/<?php echo $foto_usuario ?>" width="45" class="rounded-s"
          alt="img"></a>
    </div>
    <div class="ps-2 align-self-center">
      <h5 class="ps-1 mb-0 line-height-xs pt-1"><?php echo $nome_usuario ?></h5>
      <h6 class="ps-1 mb-0 font-400 opacity-40"><?php echo $nivel_usuario ?></h6>
    </div>
    <div class="ms-auto">
      <a href="#" data-bs-toggle="dropdown" class="icon icon-m ps-3"><i
          class="bi bi-three-dots-vertical font-18 color-theme"></i></a>
      <div class="dropdown-menu  bg-transparent border-0 mt-n1 ms-3">
        <div class="card card-style rounded-m shadow-xl mt-1 me-1">
          <div class="list-group list-custom list-group-s list-group-flush rounded-xs px-3 py-1">
            <a data-bs-toggle="offcanvas" data-bs-target="#menu-perfil" href="#"
              class="color-theme opacity-70 list-group-item py-1"><strong class="font-500 font-12">Editar
                Perfil</strong><i class="bi bi-chevron-right"></i></a>
          
            <a href="logout.php" class="color-theme opacity-70 list-group-item py-1"><strong
                class="font-500 font-12">Sair</strong><i class="bi bi-chevron-right"></i></a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>





<span class="menu-divider">NAVEGAÇÃO</span>
<div class="menu-list">
  <div class="card card-style rounded-m p-3 py-2 mb-0">
    
    <?php if(@$atendimento == 'Sim'){ ?>

    <a href="agenda" id="nav-homes" onclick="navigateToPage(event, 'agenda')"><i class="gradient-green shadow-bg shadow-bg-xs bi bi-calendar-fill <?php echo @$agenda ?>"></i><span>Minha Agenda</span>      
    </a>

     <a href="minhas_comissoes" id="nav-homes" onclick="navigateToPage(event, 'minhas_comissoes')"><i class="gradient-red shadow-bg shadow-bg-xs bi bi-cash-stack <?php echo @$minhas_comissoes ?>"></i><span>Minhas Comissões</span>      
    </a>


    <a href="meus_servicos" id="nav-homes" onclick="navigateToPage(event, 'meus_servicos')"><i class="gradient-dark shadow-bg shadow-bg-xs bi bi-clipboard-check <?php echo @$meus_servicos ?>"></i><span>Meus Serviços</span>      
    </a>

    <div >
      <a class="<?php echo @$menu_cadastros ?>" data-bs-toggle="collapse" href="#collapse-list-3" aria-controls="collapse-list-3">
        <i class="gradient-yellow shadow-bg shadow-bg-xs fa-solid fa-clock"></i>
        <span>Meus Horários / Dias</span>
        <i class="bi bi-chevron-right"></i>
      </a>

      <div id="collapse-list-3" class="collapse" style="background: #f5f5f5; border-radius: 10px">
        <span >
          <a class="<?php echo @$dias ?>" href="dias"  onclick="navigateToPage(event, 'dias')">
            <span class="font-12">Horários / Dias</span>
            
          </a>
        </span>

        <span class="<?php echo @$servicos_func ?>">
          <a href="servicos_func"  onclick="navigateToPage(event, 'servicos_func')">
            <span class="font-12">Lançar Serviços</span>
            
          </a>
        </span> 

        <span class="<?php echo @$dias_bloqueio_func ?>">
          <a href="dias_bloqueio_func"  onclick="navigateToPage(event, 'dias_bloqueio_func')">
            <span class="font-12">Bloqueio de Dias</span>
            
          </a>
        </span>                    
        

      </div>
    </div>


  <?php } ?>

    <a href="clientes" id="nav-homes" onclick="navigateToPage(event, 'clientes')"><i class="gradient-blue shadow-bg shadow-bg-xs bi bi-person-fill <?php echo @$clientes ?>"></i><span>Clientes</span>      
    </a>

       

    



  </div>
</div>


<span class="menu-divider mt-4"></span>
<div class="menu-content px-3">

</div>




