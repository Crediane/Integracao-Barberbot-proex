


<!--POPOP SENHA INCORRETA-->
<div id="toast-top-2" class="toast toast-bar toast-top rounded-l bg-red-dark shadow-bg shadow-bg-s"
  data-bs-delay="3000">
  <div class="align-self-center">
    <i class="icon icon-s bi bi-emoji-frown-fill font-36 d-block color-white font-22 me-2"></i>
  </div>
  <div class="align-self-center">
    <strong class="font-13 mb-n2">DADOS INCORRETOS</strong>
    <span class="font-10 mt-n1 opacity-70">Falha no login. Tente novamente.</span>
  </div>
  <div class="align-self-center ms-auto">
    <button type="button" class="btn-close btn-close-white me-2 m-auto font-9" data-bs-dismiss="toast"></button>
  </div>
</div>


<button style="display: none" id="btn_erro" data-toast="toast-top-2" href="#" class="list-group-item">
</button>



<!--POPOP ALERTA-->
<div id="toast-top-3" class="toast toast-bar toast-top rounded-l bg-yellow-dark shadow-bg shadow-bg-s"
  data-bs-delay="3000">
  <div class="align-self-center">
    <i
      class="icon icon-s bg-white color-yellow-dark rounded-l shadow-s bi bi-exclamation-triangle-fill font-22 me-3"></i>
  </div>
  <div class="align-self-center">
    <strong class="font-13 mb-n2">ATENÇÃO!!!</strong>
    <span class="font-10 mt-n1 opacity-70">Seu acesso foi desativado</span>
  </div>
  <div class="align-self-center ms-auto">
    <button type="button" class="btn-close btn-close-white me-2 m-auto font-9" data-bs-dismiss="toast"></button>
  </div>
</div>


<button style="display: none" id="btn_alert" data-toast="toast-top-3" href="#" class="list-group-item">
</button>



<!--POPOP DESLOGADO-->
<div id="toast-top-4" class="toast toast-bar toast-top rounded-l shadow-bg shadow-bg-s" data-bs-delay="3000"
  style="background-color: #dcdcdc !important;">
  <div class="align-self-center">
    <i class="icon icon-s bg-green-light rounded-l bi bi-check font-28 me-2"></i>

  </div>
  <div class="align-self-center ps-1">
    <strong class="font-13 mb-n2">Deslogado com sucesso</strong>
    <span class="font-10 mt-n1 opacity-70">Sessão Encerrada!!!</span>
  </div>
  <div class="align-self-center ms-auto">
    <button type="button" class="btn-close btn-close-white me-2 m-auto font-9" data-bs-dismiss="toast"></button>
  </div>
</div>


<button style="display: none" id="btn_deslogado" data-toast="toast-top-4" href="#" class="list-group-item">
</button>




<!--------------------------------- POPOP DA RECUPERAÇÃO DE SENHAS ---------------------->

<!--POPOP SENHA INCORRETA-->
<div id="toast-top-5" class="toast toast-bar toast-top rounded-l bg-red-dark shadow-bg shadow-bg-s"
  data-bs-delay="3000">
  <div class="align-self-center">
    <i class="icon icon-s bi bi-emoji-frown-fill font-36 d-block color-white font-22 me-2"></i>
  </div>
  <div class="align-self-center">
    <strong class="font-13 mb-n2" align="center">ERRO!!!</strong>
    <span class="font-10 mt-n1 opacity-70">As senhas são diferentes!!</span>
  </div>
  <div class="align-self-center ms-auto">
    <button type="button" class="btn-close btn-close-white me-2 m-auto font-9" data-bs-dismiss="toast"></button>
  </div>
</div>


<button style="display: none" id="btn_senha_incorreta" data-toast="toast-top-5" href="#" class="list-group-item">
</button>





<!--MENSAGEM DE SUCESSO REC SENHA-->
<div id="toast-top-6" class="toast toast-bar toast-top rounded-l bg-green-dark shadow-bg shadow-bg-s"
  data-bs-delay="3000">
  <div class="align-self-center">
    <i class="icon icon-s bg-green-light rounded-l bi bi-check font-28 me-2"></i>
  </div>
  <div class="align-self-center ps-1">
    <strong class="font-13 mb-n2">Sucesso!</strong>
    <span class="font-10 mt-n1 opacity-70">Sua Senha foi alterada com Sucesso!!</span>
  </div>
  <div class="align-self-center ms-auto">
    <button type="button" class="btn-close btn-close-white me-2 m-auto font-9" data-bs-dismiss="toast"></button>
  </div>
</div>

<button style="display: none" id="btn_sucess_rec_senha" data-toast="toast-top-6" href="#" class="list-group-item">
</button>




<div id="notification-bar-1" class="notification-bar detached gradient-green rounded-s shadow-l" data-bs-delay="5000"
  style="z-index: 20000;">
  <div class="toast-header bg-transparent border-0 rounded-s px-3 py-3 pb-0">
    <i class="bi bi-check-circle-fill pe-2 color-white"></i>
    <strong class="me-auto color-white font-15">Sucesso!!!</strong>
    <a href="#" class="font-10 color-white opacity-60 px-3 me-n3" data-bs-dismiss="toast" aria-label="Close">X</a>
  </div>
  <div class="toast-body px-3">
    <p class="mb-0 font-12 mt-n1 color-white opacity-70">
      Link de recuperação enviado para seu Email ou seu whatsapp!!!
    </p>
  </div>
</div>


<button style="display: none" id="btn_sucess_sen_env" data-toast="notification-bar-1" href="#" class="list-group-item">
</button>




<!--POPOP EMAAIL NÃO CADASTRADO-->
<div id="toast-top-8" class="toast toast-bar toast-top rounded-l bg-red-dark shadow-bg shadow-bg-s" data-bs-delay="3000"
  style="z-index: 100000000;">
  <div class="align-self-center">
    <i class="icon icon-s bi bi-emoji-frown-fill font-36 d-block color-white font-22 me-2"></i>
  </div>
  <div class="align-self-center">
    <strong class="font-13 mb-n2" align="center">ATENÇÃO!!!</strong>
    <span class="font-10 mt-n1 opacity-70">Este E-mail não está Cadastrado!!</span>
  </div>
  <div class="align-self-center ms-auto">
    <button type="button" class="btn-close btn-close-white me-2 m-auto font-9" data-bs-dismiss="toast"></button>
  </div>
</div>


<button style="display: none" id="btn_email_n_cad" data-toast="toast-top-8" href="#" class="list-group-item">
</button>




