<!-- BOTÃO DISPARAR MENSAGEM DE SUCESSO -->
<button style="display: none" id="not_salvar" data-toast="toast-top-sucesso" class="list-group-item"></button>

<!-- MENSAGEM DE SUCESSO -->
<div id="toast-top-sucesso" class="toast toast-bar toast-top rounded-l bg-green-dark shadow-bg shadow-bg-s"
  data-bs-delay="2000" style="z-index: 20000;">
  <div class="align-self-center">
    <i class="icon icon-s bg-green-light rounded-l bi bi-check font-28 me-2"></i>
  </div>
  <div class="align-self-center ps-1">
    <strong class="font-13 mb-n2">Sucesso!</strong>
    <span id="toast-message" class="font-10 mt-n1 opacity-70"></span>
  </div>
  <div class="align-self-center ms-auto">
    <button type="button" class="btn-close btn-close-white me-2 m-auto font-9" data-bs-dismiss="toast"></button>
  </div>
</div>



<!--#################### BOTÃO DISPARAR MENSAGEM DE ERRO########################################### -->
<button style="display: none" id="not_erro" data-toast="toast-top-erro" class="list-group-item"></button>

<!-- MENSAGEM DE ERRO -->
<div id="toast-top-erro" class="toast toast-bar toast-top rounded-l bg-red-dark shadow-bg shadow-bg-s"
  data-bs-delay="3000" style="z-index: 20000;">
  <div class="align-self-center">
    <i class="bi bi-emoji-frown-fill font-36 d-block color-white"></i>
  </div>
  <div class="align-self-center ps-1">
    <strong class="font-13 mb-n2">Opsss!</strong>
    <span id="toast-message2" class="font-10 mt-n1 opacity-70"></span>
  </div>
  <div class="align-self-center ms-auto">
    <button type="button" class="btn-close btn-close-white me-2 m-auto font-9" data-bs-dismiss="toast"></button>
  </div>
</div>




<!--BOTÃO DISPARAR MENSAGEM DE EXCLUIDO-->
<button style="display: none" id="not_excluido" data-toast="toast-top-excluido" href="#" class="list-group-item">
</button>

<!--MENSAGEM DE EXCLUIDO-->
<div id="toast-top-excluido" class="toast toast-bar toast-top rounded-l bg-red-dark shadow-bg shadow-bg-s"
  data-bs-delay="3000" style="z-index: 20000;">
  <div class="align-self-center">
    <i class="icon icon-s bg-white rounded-l bi bi-trash-fill font-18 me-2" style="color: red"></i>
  </div>
  <div class="align-self-center ps-1">
    <strong class="font-13 mb-n2">Excliodo!</strong>
    <span class="font-10 mt-n1 opacity-70" id="toast-message-excluir"></span>
  </div>
  <div class="align-self-center ms-auto">
    <button type="button" class="btn-close btn-close-white me-2 m-auto font-9" data-bs-dismiss="toast"></button>
  </div>
</div>





<!--POPOP SENHA INCORRETA-->
<div id="toast-top-2" class="toast toast-bar toast-top rounded-l bg-red-dark shadow-bg shadow-bg-s"
  data-bs-delay="3000">
  <div class="align-self-center">
    <i class="icon icon-s bg-white color-red-dark rounded-l shadow-s bi bi-exclamation-triangle-fill font-22 me-3"></i>
  </div>
  <div class="align-self-center">
    <strong class="font-13 mb-n2">Senha Incorreta</strong>
    <span class="font-10 mt-n1 opacity-70">Falha no login. Tente novamente.</span>
  </div>
  <div class="align-self-center ms-auto">
    <button type="button" class="btn-close btn-close-white me-2 m-auto font-9" data-bs-dismiss="toast"></button>
  </div>
</div>



<!--MENSAGEM DE SUCESSO 2 MENOR-->
<div id="toast-bottom-4" class="toast toast-pill toast-top toast-s rounded-l bg-green-dark shadow-bg shadow-bg-s"
  data-bs-delay="2000"><span class="font-12"><i class="bi bi-check font-20"></i>Success</span></div>




<!--POPUP EXCLUSÃO-->
<div class="offcanvas offcanvas-modal rounded-m offcanvas-detached bg-theme" style="width:340px" id="menu-delete">
  <div class="gradient-red px-3 py-3">
    <div class="d-flex mt-1">
      <div class="align-self-center">
        <i class="bi bi-x-circle-fill font-22 pe-2 scale-box color-white"></i>
      </div>
      <div class="align-self-center">
        <h1 class="font-800 color-white mb-0">Atenção</h1>
      </div>
    </div>
    <p class="color-white opacity-60 pt-2">
      Ao excluir este item não poderá mais ser desfeito. Deseja prosseguir?
    </p>
    <div class="row">
      <div class="col-6">
        <a href="#" data-bs-dismiss="offcanvas" class="default-link btn btn-full btn-s bg-white color-black"><i
            class="bi bi-x-circle-fill pe-2 ms-n1"></i>Não</a>
      </div>
      <div class="col-6">
        <a href="#" data-bs-dismiss="offcanvas" class="default-link btn btn-full btn-s gradient-green "><i
            class="bi bi-check-circle-fill pe-2 ms-n1"></i>Sim</a>
      </div>
    </div>
  </div>
</div>







<!--##################### MODAL EXCLUIR####################-->
<a hidden="hidden" href="#" data-bs-toggle="offcanvas" data-bs-target="#menu-excluir" class="list-group-item"
  id="btn_excluir"></a>


<div class="offcanvas offcanvas-modal rounded-m offcanvas-detached bg-theme gradient-red" style="width:340px"
  id="menu-excluir">
  <div class="gradient-red px-3 py-3">
    <div class="d-flex mt-1">
      <div class="align-self-center">
        <i class="bi bi-x-circle-fill font-22 pe-2 scale-box color-white"></i>
      </div>
      <div class="align-self-center">
        <h1 class="font-800 color-white mb-0">ATENÇÃO!!!</h1>
      </div>
    </div>
    <p class="color-white opacity-60 pt-2">
      Esta ação é irreversível. Deseja realmente excluir permanentemente este item?
    </p>
    <div class="row">
      <div class="col-6">
        <a href="#" type="button" data-bs-dismiss="offcanvas"
          class="default-link btn btn-full btn-s gradient-white color-black">Não</a>
      </div>
      <div class="col-6">
        <a href="#" type="button" data-bs-dismiss="offcanvas"
          class="default-link btn btn-full btn-s gradient-green color-black" onclick="excluir()">Sim</a>
      </div>
    </div>
    <input type="hidden" id="id_excluir">
  </div>
</div>



<!--##################### MODAL CANCELAR RECORRENCIA####################-->
<a hidden="hidden" href="#" data-bs-toggle="offcanvas" data-bs-target="#menu-canc-rec" class="list-group-item"
  id="btn_canc_rec"></a>


<div class="offcanvas offcanvas-modal rounded-m offcanvas-detached bg-theme gradient-red" style="width:340px"
  id="menu-canc-rec">
  <div class="gradient-red px-3 py-3">
    <div class="d-flex mt-1">
      <div class="align-self-center">
        <i class="bi bi-x-circle-fill font-22 pe-2 scale-box color-white"></i>
      </div>
      <div class="align-self-center">
        <h1 class="font-800 color-white mb-0">Atenção!!!</h1>
      </div>
    </div>
    <p class="color-white opacity-60 pt-2">
      Deseja Cancelar as Recorrências?
    </p>
    <div class="row">
      <div class="col-6">
        <a href="#" type="button" data-bs-dismiss="offcanvas"
          class="default-link btn btn-full btn-s gradient-white color-black">Não</a>
      </div>
      <div class="col-6">
        <a href="#" type="button" data-bs-dismiss="offcanvas"
          class="default-link btn btn-full btn-s gradient-green color-black" onclick="cancelarCerorrencia()">Sim</a>
      </div>
    </div>
    <input type="hidden" id="id_canc_rec">
  </div>
</div>



<!-- MODAL CONCLUIR-->
<a hidden="hidden" href="#" data-bs-toggle="offcanvas" data-bs-target="#menu-share-baixar" class="list-group-item"
  id="btn_concluir"></a>

<div class="offcanvas offcanvas-modal rounded-m offcanvas-detached bg-theme gradient-red" style="width:340px"
  id="menu-share-baixar">
  <div class="gradient-blue px-3 py-3">
    <div class="d-flex mt-1">
      <div class="align-self-center">
        <i class="bi bi-check-circle-fill font-22 pe-2 scale-box color-white"></i>
      </div>
      <div class="align-self-center">
        <h5 class="font-600 color-white mb-0">Atenção!!</h5>
      </div>
    </div>
    <p class="color-white opacity-60 pt-2">
      Deseja Concluir esta Tarefa?
    </p>
    <div class="row">
      <div class="col-6">
        <a href="#" type="button" data-bs-dismiss="offcanvas"
          class="default-link btn btn-full btn-s gradient-white color-black">Cancelar</a>
      </div>
      <div class="col-6">
        <a href="#" type="button" data-bs-dismiss="offcanvas"
          class="default-link btn btn-full btn-s gradient-green color-black" onclick="concluir()"><i
            class="bi bi-check-circle-fill pe-3 ms-n1"> </i>Concluir</a>
      </div>
    </div>
    <input type="hidden" id="id_concluir">
  </div>
</div>