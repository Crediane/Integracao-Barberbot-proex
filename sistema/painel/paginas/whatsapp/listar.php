<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'config';

$query = $pdo->query("SELECT * FROM $tabela");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);
if($total_reg > 0){
?>

<small>
	<table class="table table-hover" id="tabela">
    	<thead> 
        	<tr> 
            	<th>Authkey</th>	
            	<th class="esc">Nome do dispositivo</th> 	
            	<th>Status</th>
            	<th>Ações</th>
    	    </tr> 
    	</thead> 
	<tbody>	
	
<?php if(isset($res) && isset($res[0]) && isset($res[0]['instancia']) && $res[0]['instancia'] != ''): ?>
<tr>
    <td class="esc" id="authkey_list"><?= $res[0]['token'];?></td>
    <td class="esc" id="appkey_list"><?= $res[0]['instancia'];?></td>
    <td id="status_list">Carregando...</td>
    <td><big><a href="#" onclick="editar()" title="Reconectar"><i class="fa fa-plug text-primary"></i></a></big>
		<li class="dropdown head-dpdn2" style="display: inline-block;">
    		<a href="#" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><big><i class="fa fa-trash-o text-danger"></i></big></a>
    		<ul class="dropdown-menu" style="margin-left:-230px;">
		<li>
    		<p>Confirmar Exclusão? 
                        <a href="#" id="confirmarExclusao"><span class="text-danger">Sim</span></a>
                    </p>
		</li>										
		</ul>
		</li>
	</td>
</tr>

</tbody>
    <small><div align="center" id="mensagem-excluir"></div></small>
</table>
</small>
<?php endif; ?>

<?php 
}else{
	echo '<small>Não possui nenhum registro Cadastrado!</small>';
}

?>


<script type="text/javascript">



$(function() {
    var appkey = $('#appkey_list').text(); 
    var authkey = $('#authkey_list').text();
    
   
    $(document).ready(function() {
        $('#confirmarExclusao').click(function(event) {
            event.preventDefault(); 
            
            $.ajax({
                url: 'https://chatbot.menuia.com/api/developer', 
                type: 'POST',
                data: {
                    authkey: authkey,
                    message: appkey,
                    licence: 'hugocursos',
                    apagarDispositivo: 'true'
                },
                success: function(response) {
                    console.log(authkey + appkey);
                    var m;
                    if (response.status === 200) {
                        m = 'O dispositivo foi apagado com sucesso!';
                    } else {
                        m = 'Ocorreu um erro e o dispositivo foi removido parcialmente. Para apagar por completo acesse: https://chatbot.menuia.com/user/device';
                    }
                    
                    $.ajax({
                        url: 'paginas/whatsapp/excluir.php',
                        type: 'POST',
                        data: {
                            authkey: authkey,
                            message: appkey,
                            apagarDispositivo: 'true'
                        },
                        success: function(response) {
                            if (response.status === 200) {
                                alert(m);  
                            } else {
                                alert('Ocorreu um erro ao tentar excluir o dispositivo do banco de dados');
                            }
                            
                            // Atualiza a página após 3 segundos, independentemente do resultado da segunda solicitação AJAX
                            location.reload();
                        },
                        error: function(error) {
                            console.log(error);
                            alert('Ocorreu um erro ao tentar atualizar o banco de dados.');
                        }
                    });
                },
                error: function(error) {
                  var m = 'Dispositivo removido com sucesso! O mesmo não estava cadastro na base de dados da MENUIA';
                  
                    $.ajax({
                        url: 'paginas/whatsapp/excluir.php',
                        type: 'POST',
                        data: {
                            authkey: authkey,
                            message: appkey,
                            apagarDispositivo: 'true'
                        },
                        success: function(response) {
                            if (response.status === 200) {
                                alert(m);  
                            } else {
                                alert(m); 
                            }
                            
                            // Atualiza a página após 3 segundos, independentemente do resultado da segunda solicitação AJAX
                            location.reload();
                        },
                        error: function(error) {
                            console.log(error);
                            alert('Ocorreu um erro ao tentar atualizar o banco de dados.');
                        }
                    });
                    
                }
            });
        });
    });
    
    
    // Atualiza a status do dispositivo
    function checkStatus(authkey, appkey, callback) {
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
                if (response.status === 200) {
                    updateStatus('online', 'fa fa-check'); // Online
                } else if (response.status === 404 || response.status === 403) {
                    updateStatus('desconectado', 'fa fa-times'); // Desconectado
                } else {
                    console.log(response);
                    updateStatus('error', 'fa fa-exclamation-circle'); // Error
                }
            },
            error: function (error) {
                $('#loadingIndicator').hide();
                $('#statusMessage').html('Erro ao carregar o QR code.').show();
                updateStatus('error', 'fa fa-exclamation-circle'); // Error
                console.log(error);
            }
        });
    }
  
    function updateStatus(text, icon) {
        $('#status_list').text(text); // Definir o texto
        $('#status_list').attr('class', icon); // Definir a classe do ícone
    }

    // Verificar o status ao carregar a página
    checkStatus(authkey, appkey);
});


	function editar(id, nome, categoria, descricao, valor_compra, valor_venda, foto, nivel_estoque){
		$('#id').val(id);
		$('#nome').val(nome);
		$('#valor_venda').val(valor_venda);
		$('#valor_compra').val(valor_compra);
		$('#categoria').val(categoria).change();
		$('#descricao').val(descricao);
		$('#nivel_estoque').val(nivel_estoque);
						
		$('#titulo_inserir').text('Editar Registro');
		$('#modalForm').modal('show');
		$('#foto').val('');
		$('#target').attr('src','img/produtos/' + foto);
	}
</script>

