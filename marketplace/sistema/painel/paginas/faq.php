<?php 
require_once __DIR__ . '/_context_bootstrap.php';
require_once __DIR__ . '/../verificar.php';
require_once __DIR__ . '/../../conexao.php';

$pag = 'faq';

if (@$faq == 'ocultar') {
    echo "<script>window.location='<?php echo $bb_painel_public_url; ?>/'</script>";
    exit();
}
?>
<div class="row mt-4 mb-4">
    <a type="button" class="btn-primary btn-sm ml-3 d-none d-md-block" onclick="inserir()">
        <i class="fa fa-plus"></i> Nova Pergunta
    </a>
    <a type="button" class="btn-primary btn-sm ml-3 d-block d-sm-none" onclick="inserir()">+</a>

    <li class="ml-3 d-none d-md-block">
        <a class="" href="index.php">
            <span>Início</span>
        </a>
    </li>
</div>

<div class="bs-example widget-shadow" style="padding:15px" id="listar"></div>

<!-- Modal Formulário -->
<div class="modal fade" id="modalForm" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="exampleModalLabel">
                    <span id="titulo_inserir"></span>
                </h4>
                <button id="btn-fechar" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -25px">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            <form id="form">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-12">							
                            <label for="pergunta">Pergunta</label>
                            <input type="text" class="form-control" id="pergunta" name="pergunta" placeholder="Digite a pergunta" required>							
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-12">							
                            <label for="resposta">Resposta</label>
                            <textarea maxlength="1000" class="form-control" id="resposta" name="resposta" placeholder="Digite a resposta" rows="5" required></textarea>
                            <small class="form-text text-muted">Máximo 1000 caracteres</small>
                        </div>
                    </div>

                    <!-- o id que será enviado no POST fica SOMENTE aqui -->
                    <input type="hidden" name="id" value="">

                    <br>
                    <small><div id="mensagem" align="center"></div></small>
                </div>
                <div class="modal-footer">       
                    <button type="submit" class="btn btn-primary">Salvar</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Modal Visualizar Dados -->
<div class="modal fade" id="modalDados" tabindex="-1" aria-labelledby="exampleModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h4 class="modal-title" id="exampleModalLabel">
                    <span id="titulo_dados"></span>
                </h4>
                <button id="btn-fechar-dados" type="button" class="close" data-dismiss="modal" aria-label="Close" style="margin-top: -25px">
                    <span aria-hidden="true">&times;</span>
                </button>
            </div>
            
            <div class="modal-body">
                <div class="row" style="border-bottom: 1px solid #cac7c7; padding-bottom: 10px; margin-bottom: 10px;">
                    <div class="col-md-12">							
                        <span><b>Pergunta: </b></span><br>
                        <span id="pergunta_dados"></span>
                    </div>
                </div>

                <div class="row" style="border-bottom: 1px solid #cac7c7; padding-bottom: 10px; margin-bottom: 10px;">
                    <div class="col-md-12">							
                        <span><b>Resposta: </b></span><br>
                        <span id="resposta_dados"></span>
                    </div>
                </div>

                <div class="row">
                    <div class="col-md-6">							
                        <span><b>Data Cadastro: </b></span>
                        <span id="data_dados"></span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script type="text/javascript">
var pag = "<?=$pag?>";

// Helpers para pegar campos do formulário com segurança
function $formId() { return $('#form input[name="id"]'); }
function $formPergunta() { return $('#form #pergunta'); }
function $formResposta() { return $('#form #resposta'); }

// Inserir
function inserir() {
    $('#form')[0].reset();
    $formId().val(''); // garante que é inserção
    $('#titulo_inserir').text('Nova Pergunta');
    $('#mensagem').text('').removeClass();
    $('#modalForm').modal('show');
}

// Listar
function listar() {
    $.ajax({
        url: 'paginas/' + pag + '/listar.php',
        method: 'GET',
        success: function (data) {
            $('#listar').html(data);
        },
        error: function (xhr) {
            var body = xhr.responseText || '';
            $('#listar').html(
              '<div class="alert alert-danger" style="white-space:pre-wrap;">' +
              'Falha ao carregar a listagem (' + xhr.status + ').\n\n' + body +
              '</div>'
            );
            console.error('Erro listar.php', xhr.status, body);
        }
    });
}

// Editar
function editar(id, pergunta, resposta) {
    $('#form')[0].reset();
    $('#mensagem').text('').removeClass();

    $formId().val(id);                   // seta o id DENTRO DO FORM
    $formPergunta().val(pergunta);
    $formResposta().val(resposta);

    $('#titulo_inserir').text('Editar Pergunta');
    $('#modalForm').modal('show');
}

// Excluir
function excluir(id) {
    if (!confirm('Tem certeza que deseja excluir esta pergunta?')) return;

    $.ajax({
        url: 'paginas/' + pag + '/excluir.php',
        method: 'POST',
        data: { id: id },
        success: function (mensagem) {
            if ((mensagem || '').trim().toLowerCase().includes('sucesso')) {
                listar();
            } else {
                alert(mensagem);
            }
        },
        error: function (xhr) {
            alert('Erro ao excluir (' + xhr.status + ')');
        }
    });
}

// Mostrar
function mostrar(pergunta, resposta, data) {
    $('#titulo_dados').text('Detalhes da FAQ');
    $('#pergunta_dados').html(pergunta);
    $('#resposta_dados').html((resposta || '').replace(/\n/g, '<br>'));
    $('#data_dados').text(data);
    $('#modalDados').modal('show');
}

$(document).ready(function () {
    listar();
    $('#modalForm').on('hidden.bs.modal', function () {
        $('#form')[0].reset();
        $('#mensagem').text('').removeClass();
    });
});

// Submit
$("#form").submit(function (event) {
    event.preventDefault();
    var formData = new FormData(this);

    $.ajax({
        url: 'paginas/' + pag + '/salvar.php',
        type: 'POST',
        data: formData,
        success: function (mensagem) {
            $('#mensagem').text('').removeClass();
            var msg = (mensagem || '').trim().toLowerCase();

            if (msg.includes('sucesso')) {
                $('#mensagem').addClass('text-success').text('✓ Salvo com sucesso!');
                setTimeout(function () {
                    $('#modalForm').modal('hide');
                    listar();
                }, 700);
            } else {
                $('#mensagem').addClass('text-danger').text(mensagem);
            }
        },
        error: function (xhr) {
            $('#mensagem').addClass('text-danger')
              .text('Erro ao salvar (' + xhr.status + '): ' + (xhr.responseText || ''));
        },
        cache: false,
        contentType: false,
        processData: false
    });
});
</script>
