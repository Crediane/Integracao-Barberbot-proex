<?php
require_once __DIR__ . '/../_context_bootstrap.php';

// Guarda slug na sessão (ajuda includes/conexão a escolher o banco da loja)
if (!empty($__bb_reqSlug) && empty($_SESSION['loja_slug'])) {
  $_SESSION['loja_slug'] = $__bb_reqSlug;
}

$tabela = 'marketing';
$id_usuario = @$_SESSION['id'];
require_once("../../../conexao.php");
$busca = isset($_POST['p1']) ? trim((string)$_POST['p1']) : '';
$stmt = $pdo->prepare("SELECT * from $tabela where titulo LIKE :busca order by id desc");
$stmt->execute([':busca' => '%'.$busca.'%']);
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
$linhas = @count($res);
if ($linhas > 0) {
	?>

	<!-- Grid de Cards -->
	<div class="row row-cards" id="contenedor-cards">
		<?php
		for ($i = 0; $i < $linhas; $i++) {
			$id = $res[$i]['id'];
			$arquivo = $res[$i]['arquivo'];
			$audio = $res[$i]['audio'];
			$data_envio = $res[$i]['data_envio'];
			$data = $res[$i]['data'];
			$envios = $res[$i]['envios'];
			$forma_envio = $res[$i]['forma_envio'];
			$documento = $res[$i]['documento'];
			// Obter dados brutos e decodificar as entidades HTML
			$titulo = html_entity_decode($res[$i]['titulo'], ENT_QUOTES, 'UTF-8');
			$msg = html_entity_decode($res[$i]['mensagem'], ENT_QUOTES, 'UTF-8');
			$msg2 = html_entity_decode($res[$i]['mensagem2'], ENT_QUOTES, 'UTF-8');
			// Codificar a mensagem para ser enviada via URL
			$msgF = rawurlencode($msg);
			$msg2F = rawurlencode($msg2);
			$data_envioF = implode('/', array_reverse(@explode('-', $data_envio)));
			$dataF = implode('/', array_reverse(@explode('-', $data)));
			if ($forma_envio == "") {
				$forma_envio = "Todos";
			}
			$tituloF = mb_strimwidth($titulo, 0, 40, "...");
			$ocultar_audio = 'ocultar';
			if ($audio != "") {
				$ocultar_audio = '';
			}
			$ocultar_foto = 'ocultar';
			if ($arquivo != "sem-foto.png") {
				$ocultar_foto = '';
			}
			if ($forma_envio == "") {
				$forma_envio = "Todos";
			}
			$ocultar_doc = 'ocultar';
			if ($documento != "sem-foto.png") {
				$ocultar_doc = '';
			}
			$ocultar_reg = '';
			//extensão do arquivo
			$ext = pathinfo($documento, PATHINFO_EXTENSION);
			if ($ext == 'pdf') {
				$tumb_arquivo = 'pdf.png';
			} else if ($ext == 'rar' || $ext == 'zip') {
				$tumb_arquivo = 'rar.png';
			} else {
				$tumb_arquivo = $documento;
			}
			$query2 = $pdo->query("SELECT * FROM disparos where campanha = '$id' ORDER BY id desc");
			$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
			$total_envios = @count($res2);

			$query2 = $pdo->query("SELECT * FROM disparos where campanha = '$id' ORDER BY id desc limit 1");
			$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
			$hora_ultimo_envio = @$res2[0]['hora'];

			$ocultar_parar = '';
			if($total_envios == 0){
				$ocultar_parar = 'ocultar';
			}
			?>
<!-- Card de Campanha -->
<div class="col-lg-4 col-md-6 col-sm-12" style="padding-bottom: 20px;">
  <div class="panel panel-default" style="height: 100%; min-height: 380px; display: flex; flex-direction: column; justify-content: space-between;">
    <!-- Cabeçalho do Card -->
    <div class="panel-heading" style="background: transparent; border-bottom: none; padding-bottom: 0;">
      <h5 class="panel-title" style="color: #2b4eff; font-size: 15px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin:0;">
        <?= $tituloF ?>
      </h5>
    </div>
    <!-- Corpo do Card -->
    <div class="panel-body" style="padding: 10px 10px 0 10px;">
      <!-- Informações da Campanha -->
      <div style="margin-bottom: 12px;">
        <small style="color: #6c757d; font-weight: 500; display: block;">Tipo de Envio</small>
        <span style="background-color: #e5f8ff; color: #17a2b8; padding: 4px 10px; border-radius: 20px; font-weight: 600; font-size: 12px;">
          <?= $forma_envio ?>
        </span>
      </div>
      <div style="margin-bottom: 12px;">
        <small style="color: #6c757d; font-weight: 500; display: block;">Último Envio</small>
        <?php if($hora_ultimo_envio != ""): ?>
        <span style="background-color: #f1f1f1; color: #6c757d; padding: 4px 10px; border-radius: 20px; font-weight: 600; font-size: 12px;">
          <i class="fa fa-clock"></i>
          <?= $hora_ultimo_envio ?>
        </span>
        <?php else: ?>
        <span style="background-color: #ffe2e6; color: #d63384; padding: 4px 10px; border-radius: 20px; font-weight: 600; font-size: 13px;">
          <i class="fa fa-clock"></i> Aguardando Disparo
        </span>
        <?php endif; ?>
      </div>

      <!-- Barra de Progresso para Envios Pendentes -->
      <div style="margin-bottom: 12px;">
        <span style="font-weight: 600; color: #333;">Envios Pendentes</span>
        <span class="badge" style="background-color: #e6f0ff; color: #2b4eff; font-weight: 600;">
          <?= $total_envios ?>
        </span>
        <div style="background-color: #f0f4f8; border-radius: 30px; height: 8px; width: 100%; margin-top: 4px;">
          <?php $porcentagem = min(100, $total_envios * 5); ?>
          <div style="height: 100%; width: <?= $porcentagem ?>%; background: linear-gradient(to right, #2b4eff, #7b9dff); border-radius: 30px;"></div>
        </div>
      </div>

      <!-- Elementos de Mídia -->
      <div style="margin-bottom: 10px; height: 44px;">
        <?php if ($arquivo != "sem-foto.png"): ?>
        <img src="images/marketing/<?= $arquivo ?>" class="img-thumbnail" width="40" height="40" style="margin-right: 6px;" data-toggle="tooltip" title="Imagem">
        <?php endif; ?>
        <?php if ($audio != ""): ?>
        <span class="label label-warning" style="margin-right: 6px;" data-toggle="tooltip" title="Áudio"><i class="fa fa-music"></i></span>
        <?php endif; ?>
        <?php if ($documento != "sem-foto.png"): ?>
        <a href="images/marketing/<?= $documento ?>" target="_blank">
          <img src="images/marketing/<?= $tumb_arquivo ?>" class="img-thumbnail" width="40" height="40" data-toggle="tooltip" title="Documento">
        </a>
        <?php endif; ?>
      </div>
    </div>
    <!-- Rodapé do Card -->
    <div class="panel-footer" style="background: transparent; border-top: 1px solid #eee;">
      <div class="clearfix">
        <small class="text-muted pull-left">Criado em: <?= $dataF ?></small>
        <div class="btn-group pull-right">
          <button class="btn btn-xs btn-info" data-toggle="tooltip" title="Editar Campanha" onclick="editar('<?= $id ?>','<?= $titulo ?>', '<?= $msgF ?>', '<?= $msg2F ?>', '<?= $arquivo ?>', '<?= $audio ?>', '<?= $documento ?>')">
            <i class="fa fa-edit"></i>
          </button>
          <button class="btn btn-xs btn-warning" data-toggle="tooltip" title="Visualizar Campanha" onclick="mostrar('<?= $id ?>','<?= $titulo ?>', '<?= $msgF ?>', '<?= $msg2F ?>', '<?= $arquivo ?>', '<?= $audio ?>', '<?= $documento ?>', '<?= $dataF ?>', '<?= $data_envioF ?>', '<?= $arquivo ?>', '<?= $tumb_arquivo ?>')">
            <i class="fa fa-eye"></i>
          </button>
          <button class="btn btn-xs btn-success" data-toggle="tooltip" title="Disparar Campanha" onclick="disparar('<?= $id ?>','<?= $titulo ?>', '<?= $msgF ?>', '<?= $msg2F ?>', '<?= $arquivo ?>', '<?= $audio ?>', '<?= $tumb_arquivo ?>')">
            <i class="fa fa-paper-plane"></i>
          </button>
          <button class="btn btn-xs btn-dark <?= $ocultar_parar ?>" data-toggle="tooltip" title="Parar Campanha" onclick="pararModal('<?= $id ?>')">
            <i class="fa fa-ban"></i>
          </button>
          <button class="btn btn-xs btn-danger" data-toggle="tooltip" title="Excluir Campanha" onclick="excluirAlert('<?= $id ?>')">
            <i class="fa fa-trash"></i>
          </button>
        </div>
      </div>
    </div>
  </div>
</div>

		<?php } ?>
	</div>
	<div class="text-center mt-4" id="sem-registros" style="display: none;">
		<div class="alert alert-warning">
			<i class="fa fa-exclamation-triangle me-2"></i>
			Nenhuma campanha encontrada!
		</div>
	</div>
	<?php
} else {
	?>
	<div class="alert alert-info">
		<i class="fa fa-info-circle me-2"></i>
		Você ainda não possui nenhuma campanha de marketing cadastrada.
		<button type="button" class="btn btn-sm btn-primary float-end" onclick="$('#modalForm').modal('show');">
			<i class="fa fa-plus me-1"></i> Criar Campanha
		</button>
	</div>
	<?php
}
?>
<style>
/* Altura fixa para os cards + flex para rodapé no fundo */
.card-campanha .card {
  display: flex;
  flex-direction: column;
  height: 100%;
  min-height: 350px; /* ajuste a altura conforme seu gosto, ex: 350px */
}

.card-campanha .card-body {
  flex: 1 1 auto;
  display: flex;
  flex-direction: column;
  justify-content: flex-start;
}

.card-campanha .card-footer {
  margin-top: auto;
}

@media (max-width: 991.98px) {
  .card-campanha .card {
    min-height: 300px;
  }
}
</style>


<script type="text/javascript">
// Script para funcionalidades adicionais na listagem de marketing
$(document).ready(function() {
    // Adicionar classe para exibir animação quando um novo card é adicionado
    function refreshCardAnimations() {
        $(".card-campanha").each(function(index) {
            var $card = $(this);
            setTimeout(function() {
                $card.addClass("show");
            }, index * 100);
        });
    }
    
    refreshCardAnimations();
});

// Funções para os botões principais (reutilizando as funções existentes)
function formatarMensagemModal(msg) {
    // Essa função pode ser mantida conforme estava no código original
    // Ajuste conforme necessário para formatar a mensagem corretamente
    return msg.replace(/\n/g, '<br>');
}

function pararModal(id) {
    const swalWithBootstrapButtons = Swal.mixin({
        customClass: {
            confirmButton: "btn btn-success", // Adiciona margem à direita do botão "Sim, Excluir!"
            cancelButton: "btn btn-danger me-1",
            container: 'swal-whatsapp-container'
        },
        buttonsStyling: false
    });

    swalWithBootstrapButtons.fire({
        title: "Parar os Disparos?",
        text: "Você irá parar todos os disparos dessa campanha!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sim, Parar!",
        cancelButtonText: "Não, Cancelar!",
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            pararDisparo(id);
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

// Sobrescrever a função de parar para usar a versão com Sweet Alert
function pararDisparo(id) {
    $.ajax({
        url: 'paginas/' + pag + "/parar_envios.php",
        method: 'POST',
        data: {id: id},
        dataType: "text",
        success: function (mensagem) {
            if (mensagem.trim() == "Parado com Sucesso") {
                Swal.fire({
                    title: 'Parado!',
                    text: 'Os envios foram interrompidos com sucesso.',
                    icon: 'success',
                    timer: 1500,
                    timerProgressBar: true,
                });
                // Atualizar a listagem
                listar();
            } else {
                Swal.fire({
                    title: 'Erro!',
                    text: mensagem,
                    icon: 'error'
                });
            }
        }
    });
}
</script>


<script type="text/javascript">
	function editar(id, titulo, msg, msg2, arquivo, audio, documento) {
		// Antes de definir o src, verifique a extensão
var ext = documento.split('.').pop().toLowerCase();
if (ext == 'pdf') {
  $('#target_documento').attr('src','images/marketing/pdf.png');
} else if (ext == 'rar' || ext == 'zip') {
  $('#target_documento').attr('src','images/marketing/rar.png');
} else {
  $('#target_documento').attr('src','images/marketing/' + documento);
}
		
		
		msg = decodeURIComponent(msg);
		msg2 = decodeURIComponent(msg2);
		

		// Atualiza os campos do formulário
		$('#titulo_inserir').text('Editar Registro');
		$('#id').val(id);
		$('#titulo').val(titulo);
		$('#msg').val(msg);  // Alterado de .html() para .val()
		$('#msg2').val(msg2);
		$('#titulo_inserir').text('Editar Campanha');
		$('#foto').val('');
		$('#target').attr('src','images/marketing/' + arquivo);
		


		// Atualiza o preview após preencher o campo msg
		atualizarPreview();
		atualizarPreview2();
		// Mostra a aba de edição
		$('#modalForm').modal('show');
	}




function mostrar(id, titulo, msg, msg2, arquivo, audio, documento, dataF, data_envioF, arquivo, tumb_arquivo) {
    msg = decodeURIComponent(msg);  // Decodifica a mensagem URL-encoded
	msg2 = decodeURIComponent(msg2);
    $('#id_dados').text(id);
    $('#titulo_dados').text(titulo);
	$('#data_envio_dados').text(data_envioF);
	$('#data_dados').text(dataF);
    $('#mensagem_dados').html(formatarMensagemModal(msg));
    $('#mensagem2_dados').html(formatarMensagemModal(msg2));
	$('#target_dados').attr('src','images/marketing/' + arquivo);
		$('#audio_dados').attr('src','images/marketing/' + audio);
		$('#target_documento_dados').attr('src','images/marketing/' + tumb_arquivo);
    $('#modalDados').modal('show');
}

	function limparCampos() {
		$('#id').val('');
		$('#titulo').val('');
		$('#preview').text('');
		$('#msg').val('');
		$('#msg2').val('');
		$('#ids').val('');
		$('#btn-deletar').hide();
		$('#foto').val('');
		$('#audio').val('');
		$('#documento').val('');
		$('#target').attr('src','images/marketing/sem-foto.png');
		$('#target_documento').attr('src','images/marketing/sem-foto.png');

		atualizarPreview();
		atualizarPreview2();
	}
</script>


<script>
	function disparar(id, titulo, msg, msg2, arquivo, audio, documento){
 msg = decodeURIComponent(msg);  // Decodifica a mensagem URL-encoded
	msg2 = decodeURIComponent(msg2);
		$('#nome_entrada').text(titulo);		
		$('#id_entrada').val(id);

		$('#total_clientes').text("Alterar Opção de Teste: 0");	
		$('#titulo_disparar').text(titulo);
		$('#mensagem_disparar').html(formatarMensagemModal(msg));
		$('#mensagem2_disparar').html(formatarMensagemModal(msg2));
		$('#clientes').val('Teste');
		$('#target_disparar').attr('src','images/marketing/' + arquivo);
		$('#audio_disparar').attr('src','images/marketing/' + audio);
		$('#target_documento_disparar').attr('src','images/marketing/' + documento);
		if(arquivo == 'sem-foto.png'){
			$('#target_disparar_div').hide();
		}else{
			$('#target_disparar_div').show();
		}
		if(audio == ''){
			$('#audio_disparar_div').hide();
		}else{
			$('#audio_disparar_div').show();
		}
		if(documento == 'sem-foto.png'){
			$('#target_documento_disparar_div').hide();
		}else{
			$('#target_documento_disparar_div').show();
		}
		$('#modalEntrada').modal('show');
	}
</script>

<script type="text/javascript">
	function excluirImagem(id){
    $.ajax({
        url: 'paginas/' + pag + "/excluir_imagem.php",
        method: 'POST',
        data: {id},
        dataType: "text",
        success: function (mensagem) {            
            if (mensagem.trim() == "Excluído com Sucesso") {                
				alertsucesso('Imagem excluída com sucesso!');        
                listar();                
            } else {
				alertError('Erro ao excluir imagem: ' + mensagem);
            }
        },     
    });
}

function excluirAudio(id){
    $.ajax({
        url: 'paginas/' + pag + "/excluir_audio.php",
        method: 'POST',
        data: {id},
        dataType: "text",

        success: function (mensagem) {    
            if (mensagem.trim() == "Excluído com Sucesso") {        
				alertsucesso('Áudio excluído com sucesso!');        
                listar();                
            } else {
				alertError('Erro ao excluir áudio: ' + mensagem);
            }
        },    
    });
}


function excluirDoc(id){
    $.ajax({
        url: 'paginas/' + pag + "/excluir_doc.php",
        method: 'POST',
        data: {id},
        dataType: "text",

        success: function (mensagem) {            
            if (mensagem.trim() == "Excluído com Sucesso") {  
				alertsucesso('Documento excluído com sucesso!');              
                listar();                
            } else {
					alertError('Erro ao excluir documento: ' + mensagem);
                }

        },      

    });
}
</script>
