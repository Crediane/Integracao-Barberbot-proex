<?php 
require_once __DIR__ . '/_context_bootstrap.php';
require_once __DIR__ . '/../verificar.php';
require_once __DIR__ . '/../../conexao.php';
$pag = 'whatsapp';

//verificar se ele tem a permissão de estar nessa página
if(@$whatsapp == 'ocultar'){
    echo "<script>window.location='<?php echo $bb_painel_public_url; ?>/'</script>";
    exit();
}
?>

<?php if(empty($token) || empty($emailMenuia)): ?>
<div >      
	<a class="btn btn-primary" onclick="conectar()" class="btn btn-primary btn-flat btn-pri"><i class="fa fa-sign-in" aria-hidden="true"></i> Conectar Conta</a>
</div>

<?php else: ?>
<div>      
	<a class="btn btn-primary" onclick="inserirW()" class="btn btn-primary btn-flat btn-pri"><i class="fa fa-plus" aria-hidden="true"></i> Novo Dispositivo</a>
	<a class="btn btn-primary" onclick="desconectar()" class="btn btn-primary btn-flat btn-pri"><i class="fa fa-sign-out"  aria-hidden="true"></i> Desconectar Conta</a>
</div>
<div style="display: flex; justify-content: flex-end;">
    <div style="margin-right: 20px;">
        <span class="fa fa-trophy text-warning" style="margin-right: 5px;"></span> 
        <span style="font-weight: bold;" class="text-warning">Plano:</span> 
        <?= ($planoMenuia == 1 ? 'Iniciante' : ($planoMenuia == 2 ? 'Intermediário' : ($planoMenuia == 3 ? 'Básico' : 'Revenda')));?>
    </div>
    <div>
        <span class="fa fa-clock-o text-primary" style="margin-right: 5px;"></span> 
        <span style="font-weight: bold;" class="text-primary">Validade:</span> 
        <?= $validadeMenuia > date('Y-m-d') ? date('d-m-Y', strtotime($validadeMenuia)) : '<a href="https://chatbot.menuia.com/user/subscription">Vencido</a>'; ?>
    </div>
</div>

<div class="bs-example widget-shadow" style="padding:15px" id="listar"></div>
<?php endif; ?>





<input type="hidden" name="token" id="token" value="<?php echo isset($token) ? $token : ''; ?>">
<input type="hidden" name="instancia" id="instancia" value="<?php echo isset($instancia) ? $instancia : ''; ?>">
<input type="hidden" name="emailmenuia" id="emailmenuia" value="<?php echo isset($emailMenuia) ? $emailMenuia : ''; ?>">
<input type="hidden" name="senhamenuia" id="senhamenuia" value="<?php echo isset($senhaMenuia) ? $senhaMenuia : ''; ?>">


<div class="modal fade" id="modalForm" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4>Realize a leitura do QRcode</h4>
                <button id="btn-fechar" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <div id="loadingIndicator" style="display: none;">
                    <div class="spinner-border text-primary" role="status">
                        <span class="sr-only">Carregando...</span>
                    </div>
                    <p>Carregando...</p>
                </div>
                <div id="qrCodeContainer" style="display: none;"></div>
                <div id="statusMessage" style="display: none;">
                    <!-- Aqui você pode adicionar a mensagem de erro -->
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>


<!-- Modal de Login -->
<div class="modal fade" id="conectar" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Realize o login ou registre-se</h4>
                <button id="btn-fechar" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <img src="https://chatbot.menuia.com/uploads/23/06/1686025726jWJDAMCm2dJrxAb4XNNX.png" class="img-fluid" alt="Logo" style="width: 30%;">
                <form id="loginForm">
                    <div class="form-group text-left">
                        <label for="email">Email</label>
                        <input type="email" class="form-control" id="email" required placeholder="Digite seu email">
                    </div>
                    <div class="form-group text-left">
                        <label for="senha">Senha</label>
                        <input type="password" class="form-control" id="senha" required placeholder="Digite sua senha">
                    </div>
                </form>
                <div id="statusMessageLogar" style="display: none;"></div>
                <button type="button" class="btn btn-primary mt-2" onclick="login()">Login</button>
                <p class="mt-3">Não tem uma conta? <a href="#" onclick="abrirModalRegistro()">Registre-se</a></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>

<!-- Modal de Registro -->
<div class="modal fade" id="registrar" tabindex="-1" role="dialog" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog" role="document">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title">Registre-se</h4>
                <button id="btn-fechar-registrar" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -20px">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <div class="modal-body text-center">
                <img src="https://chatbot.menuia.com/uploads/23/06/1686025726jWJDAMCm2dJrxAb4XNNX.png" class="img-fluid" alt="Logo" style="width: 30%;">
                <form id="registroForm">
                    <div class="form-group text-left">
                        <label for="nome">Nome</label>
                        <input type="text" class="form-control" id="nome" required placeholder="Digite seu nome">
                    </div>
                    <div class="form-group text-left">
                        <label for="emailRegistro">Email</label>
                        <input type="email" class="form-control" id="emailRegistro" required placeholder="Digite seu email">
                    </div>
                    <div class="form-group text-left">
                        <label for="senhaRegistro">Senha</label>
                        <input type="password" class="form-control" id="senhaRegistro" required placeholder="Digite sua senha">
                    </div>
                    <div class="form-group text-left">
                        <label for="telefone">Telefone</label>
                        <input type="tel" class="form-control" id="telefone" required placeholder="Ex: 5581989769960">
                    </div>
                    <div class="form-group form-check text-left">
                        <input type="checkbox" class="form-check-input" id="termosAceitos" required>
                        <label class="form-check-label" for="termosAceitos">Eu li e concordo com os <a href="https://chatbot.menuia.com/page/politica-de-privacidade-e-termos-de-uso" target="_blank">termos de uso e política de privacidade</a>.</label>
                    </div>
                </form>
                 <div id="statusMessageRegistrar" style="display: none;"></div>
                <button type="button" class="btn btn-primary mt-2" onclick="registrar()">Registrar</button>
                <p class="mt-3">Já tem uma conta? <a href="#" onclick="abrirModalLogar()">Logar</a></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-primary" data-dismiss="modal">Fechar</button>
            </div>
        </div>
    </div>
</div>


<!-- Login, Registrar & Desconectar -->
<script>
var pag = "<?=$pag?>"
    function login() 
    {
        var form2 = document.getElementById("loginForm");
        
        if (form2.reportValidity()) 
        {
            var email = document.getElementById("email").value;
            var senha = document.getElementById("senha").value;

            $.ajax({
                url: 'https://chatbot.menuia.com/api/auth',
                type: 'POST',
                data: {
                    email: email,
                    senha: senha,
                    auth: 'login'
                },
                
                success: function (response) 
                {
                    
                    if(response.status === 200)
                    {
                        var authkey = response.dados.authkey;
                        var validade = response.dados.will_expire;
                        var planoID = response.dados.plan_id;
                        var SenhaMenuia = response.dados.bcrypt;
                      
                        $.ajax({
                       url: 'paginas/' + pag + "/atualizar.php", //Armazenando as informações do dispositivo
                        data: {
                            authkey: authkey || null,
                            email: email || null,
                            validade: validade || null,
                            planoID: planoID || null,
                            senha: SenhaMenuia
                        },
                          success: function (responseBD)
                          {
                           
                           
                           if(responseBD.status == 200)
                           {
                              alert('Logado com sucesso!');
                           }
                           else if(responseBD.status == 500)
                           {
                              console.log(responseBD);
                              alert('Ocorreu um erro ao requisitar o salvamento no banco de dados!');
                           }
                           else
                           {
                               console.log(responseBD);
                               alert('Ocorreu um erro desconhecido!');
                           }
                           
                           location.reload();
                        },
                        error: function (error) {
                            console.log(error);
                            alert('Ocorreu um erro ao requisitar o salvamento no banco de dados.');
                           location.reload();
                        }
                        });
                        
                    }
                    else if (response.status === 401) 
                    {
                        var errorMessage = "Erro de validação:\n";
                        for (var key in response.message) {
                            errorMessage += key + ": " + response.message[key] + "\n";
                        }
                    
                        alert(errorMessage);
                    }
                    else
                    {
                        alert(response.message);
                    }

                   
                },
                error: function (error) 
                {
                    alert(error.responseJSON.message);
                }
            });
        }
    }

    function registrar() 
    {
         var form = document.getElementById("registroForm");
        if (form.reportValidity()) 
        {
            var email = document.getElementById("emailRegistro").value;
            var senha = document.getElementById("senhaRegistro").value;
            var telefone = '55'+ document.getElementById("telefone").value;
            var nome = document.getElementById("nome").value;
            var plano = 3;
            
       
            
           $.ajax({
                url: 'https://chatbot.menuia.com/api/auth',
                type: 'POST',
                data: {
                    nome: nome,
                    email: email,
                    senha: senha,
                    telefone: telefone,
                    plano: plano,
                    auth: 'registrar'
                },
                
                success: function (response) 
                {
                    if(response.status === 200)
                    {
                        var authkey = response.dados.authkey;
                        var validade = response.dados.will_expire;
                        var senhamenuia = response.dados.senha;
                        
                        $.ajax({
                        url: 'paginas/' + pag + "/atualizar.php", //Armazenando as informações do dispositivo
                        data: {
                            authkey: authkey || null,
                            validade: validade || null,
                            planoID: plano || null,
                            email: email || null,
                            senha: senhamenuia
                        },
                          success: function (responseBD)
                          {
                           
                           if(responseBD.status == 200)
                           {
                              alert('Registrado com sucesso!');
                           }
                            else if(responseBD.status == 401)
                           {
                              alert('Ocorreu um erro ao requisitar o salvamento no banco de dados!');
                              console.log(responseBD);
                           }
                           else if(responseBD.status == 500)
                           {
                              alert('Ocorreu um erro ao requisitar o salvamento no banco de dados!');
                              console.log(responseBD);
                           }
                           else
                           {
                               alert('Ocorreu um erro desconhecido!');
                               console.log(responseBD);
                           }
                           
                           location.reload();
                        },
                        error: function (error) {
                            alert('Ocorreu um erro ao requisitar o salvamento no banco de dados.');
                            console.log(error);
                            location.reload();
                        }
                        });
                        
                    }
                    else if (response.status === 401) 
                    {
                        var errorMessage = "Erro de validação:\n";
                        for (var key in response.message) {
                            errorMessage += key + ": " + response.message[key] + "\n";
                        }
                    
                        alert(errorMessage);
                    }
                    else
                    {
                        alert(response.message);
                    }

                   
                },
                error: function (error) 
                {
                    alert(error.responseJSON.message);
                }
            });
        }
    }
    
  
    
    function desconectar()
    {
        $.ajax({
            url: 'paginas/' + pag + "/delete.php", // URL da requisição
            data: {},
            success: function (responseBD) {
                if(responseBD.status == 200) 
                {
                    alert('Deslogado com sucesso!');
                } 
                else if(responseBD.status == 500) 
                {
                  
                        console.log(responseBD);
                      alert('Ocorreu um erro ao deslogar!');
                } 
                else 
                {
                    console.log(responseBD);
                    alert('Ocorreu um erro desconhecido!');
                }
                location.reload();
            },
            error: function (error) {
                console.log(error);
                alert('Ocorreu um erro ao requisitar o salvamento o deslogamento.');
                location.reload();
            }
        });

    }
    
    function abrirModalRegistro() {
        $('#conectar').modal('hide');
        $('#registrar').modal('show');
    }
    
    function abrirModalLogar() {
        $('#conectar').modal('show');
        $('#registrar').modal('hide');
    }

    
</script>


<script src="js/ajax.js"></script>
<!-- Gerenciar dispositivo e qrcode -->
<script type="text/javascript">

function conectar(){
    $('#mensagem').text('');
    $('#conectar').modal('show');
}


$(document).ready(function() 
{
   // Sicronizando as informações da api da menuia com o banco de dados local se tiver logado 
    var emailmenuia = $('#emailmenuia').val();
    var senhamenuia = $('#senhamenuia').val();
    var appkey = $('#instancia').val() ;


    // Verificação Periodica para atualizar os dados locaiis (Plano, email, vencimento, etc)
    if (emailmenuia !== '' && senhamenuia !== '' && appkey !== '') 
    {
        $.ajax({
            url: 'https://chatbot.menuia.com/api/auth',
            type: 'POST',
            data: {
                email: emailmenuia,
                senha: senhamenuia,
                auth: 'login'
            },
            success: function (response) {
                if (response.status === 200) {
                    var authkey = response.dados.authkey;
                    var validade = response.dados.will_expire;
                    var planoID = response.dados.plan_id;

                    $.ajax({
                        url: 'paginas/' + pag + "/atualizar.php", //Armazenando as informações do dispositivo
                        data: {
                            authkey: authkey || null,
                            email: emailmenuia || null,
                            validade: validade || null,
                            planoID: planoID || null,
                            senha: senhamenuia
                        },
                        success: function (responseBD)
                        {
                            if (responseBD.status == 200) {
                                console.log('Dados sincronizados com sucesso!');
                            } 
                        }
                    });
                }
            }
        });
    }
    
    
    $('#modalForm').on('shown.bs.modal', function () {
       
       $('#statusMessage').html('Carregando...').hide();
       var qrImage = $('<img>').attr('src', 'https://chatbot.menuia.com/uploads/loading.gif').css('max-width', '30%');
       $('#qrCodeContainer').html(qrImage).show();
         
        var appkey = $('#instancia').val() || 'Menuia_' + Date.now() + '_' + Math.floor(Math.random() * 1000) ; // se nao tiver um appkey ele vai gerar um id Unico
        var authkey = $('#token').val();
      
        var url = window.location.href;
        var primeiraBarraIndex = url.indexOf('/', url.indexOf('//') + 2);
        var baseUrl = url.substring(0, primeiraBarraIndex);
    
         $('#modalForm').on('hidden.bs.modal', function () {
        location.reload(); // Atualiza a página ao fechar o modal
         });
       
          
        $('#loadingIndicator').show();
       
       
       
       //Fun
         function conecteQR(authkey, appkey, baseUrl) 
        {
           
            
             $.ajax({
            url: 'https://chatbot.menuia.com/api/developer', //Gerando um QRCODE
            type: 'POST',
            data: {
                authkey: authkey,
                appkey: appkey,
                message: appkey,
                licence: 'hugocursos',
                webhook: baseUrl + '/ajax/retornoMenuia.php',
                conecteQR: 'true'
            },
            success: function (response) 
            {
                $('#loadingIndicator').hide();
                
                if (response.status === 200) 
                {
                 
                   $.ajax({
                        url: 'paginas/' + pag + "/salvar.php", //Armazenando as informações do dispositivo
                        data: {
                            appkey: appkey,
                        },
                          success: function (responseBD)
                          {
                           
                           if(responseBD.status == 200)
                           {
                               var qrCode = response.message.qr;
                               var qrImage = $('<img>').attr('src', qrCode).css('max-width', '100%');
                               $('#qrCodeContainer').html(qrImage).show();
                           }
                           else if(responseBD.status == 500)
                           {
                               $('#loadingIndicator').hide();
                               $('#statusMessage').html('Ocorreu um erro ao requisitar o salvamento no banco de dados.').show();
                           }
                           else if(responseBD.status == 404)
                           {
                               $('#loadingIndicator').hide();
                               $('#statusMessage').html('Ocorreu um erro com o appkey.').show();
                           }
                           else
                           {
                               $('#loadingIndicator').hide();
                               $('#statusMessage').html('Ocorreu um erro desconhecido.').show();
                           }
                        },
                        error: function (error) {
                            $('#loadingIndicator').hide();
                            $('#statusMessage').html('Ocorreu um erro ao requisitar o salvamento no banco de dados.').show();
                        }
                        });
                    
                    
                } 
                else 
                {
                    console.log(response);
                    $('#statusMessage').html('Erro ao carregar o QR code1.').show();
                }
            },
            error: function (error) 
            {
                if(error.status === 403)
                {
                    $('#loadingIndicator').hide();
                    var qrImage = $('<img>').attr('src', 'https://chatbot.menuia.com/public/license.jpg').css('max-width', '70%');
                    $('#qrCodeContainer').html(qrImage).show();
                    $('#statusMessage').html('Sua licença se encontra ativa em outro dispositivo, faça upgrade ou entre contato com a Menuia para mais informações!').show();
                     return;
                }
                else
                {
                    console.log(error);
                    $('#loadingIndicator').hide();
                    $('#statusMessage').html('Ocorreu um erro na requisição do Qr Code.').show();
                    
                }
               
            }
            });    
        }
        
        //Verifica se o dispositivo esta conectado
        function checkStatus(authkey, appkey, callback) 
        {
              
            $.ajax({
                url: 'https://chatbot.menuia.com/api/developer',
                type: 'POST',
                data: {
                    authkey: authkey,
                    message: appkey,
                    licence: 'hugocursos',
                    checkDispositivo: 'true'
                },
                
                success: function (response) {
                    $('#loadingIndicator').hide();
                    if (response.status === 200) 
                    {
                        callback(200); // Chamando a função de retorno de chamada com o status 200
                    } 
                    else if(response.status === 404 || response.status === 403)
                    {
                        callback(404); // Chamando a função de retorno de chamada com o status 500
                    }
                    else 
                    {
                        callback(500); // Chamando a função de retorno de chamada com o status 500
                    }
                },
                error: function (error) 
                {
                    callback(501);
                }
            });
        }
        
       
       var tentativas = 0;
        var maxTentativas = 5;
        var check = 0;
        
        // Função para verificar o status inicialmente e a cada 10 segundos
        function verificarStatusRecorrente(authkey, appkey, baseUrl) {
            // Chama a função para verificar o status
            checkStatus(authkey, appkey, function(status) {
               
                check++;
               if (status === 200) {
                    var qrImage = $('<img>').attr('src', 'https://chatbot.menuia.com/uploads/connected.png').css('max-width', '100%');
                    $('#qrCodeContainer').html(qrImage).show();
                    $('#statusMessage').html('Dispositivo Conectado!').show();
                    
                    // Aguardar 4 segundos antes de recarregar a página
                    setTimeout(function() {
                        location.reload(); // Recarregar a página após 4 segundos
                    }, 4000);
                }

                else if(tentativas >= maxTentativas)
                {
                    var qrImage = $('<img>').attr('src', 'https://chatbot.menuia.com/uploads/waiting.jpeg').css('max-width', '70%');
                    $('#qrCodeContainer').html(qrImage).show();
                    $('#statusMessage').html('Limite de tentativas atingido, recarregue a pagina.').show(); 
                    return;
                }
                else if(status === 500 && check > 1)
                {
                    var qrImage = $('<img>').attr('src', 'https://chatbot.menuia.com/uploads/disconnect.webp').css('max-width', '70%');
                    $('#qrCodeContainer').html(qrImage).show();
                    $('#statusMessage').html('Servidor indisponivel ou em manutenção.').show(); 
                     return;
                }
                else
                {
                    conecteQR(authkey, appkey, baseUrl); // Se nao tiver conectado ele pega o QRCode novamente
                }
        
                tentativas++; // Incrementa o número de tentativas
                
                // Define um timeout para chamar a função novamente após 10 segundos
                setTimeout(function() {
                    verificarStatusRecorrente(authkey, appkey);
                }, 10000); // Chama a função novamente após 10 segundos
            });
        }
        
        // Chama a função para verificar o status inicialmente a primeira vez é imediato para verificar se 
        verificarStatusRecorrente(authkey, appkey, baseUrl);
       
        
    });
});

function inserirW(){
    $('#mensagem').text('');
    $('#modalForm').modal('show');
}

</script>