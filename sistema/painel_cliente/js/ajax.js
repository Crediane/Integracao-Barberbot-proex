$(document).ready(function() {    
    listar();
    
} );


function listar(p1, p2, p3, p4, p5, p6){
    $.ajax({
        url: 'paginas/' + pag + "/listar.php",
        method: 'POST',
        data: bb_merge_loja({p1, p2, p3, p4, p5, p6}),
        dataType: "html",

        success:function(result){
            $("#listar").html(result);
            $('#mensagem-excluir').text('');
        }
    });
}


function excluir(id) {
    var elMsg = document.getElementById('mensagem-excluir');
    if (elMsg) elMsg.innerText = '';

    $.ajax({
        url: 'paginas/' + pag + "/excluir.php",
        method: 'POST',
        data: bb_merge_loja({id}),
        dataType: "text",

        success: function (mensagem) {   
             
            if (mensagem.trim() == "Excluído com Sucesso") {                
                listar();                
            } else {
                $('#mensagem-excluir').addClass('text-danger')
                $('#mensagem-excluir').text(mensagem)
            }

        },      

    });
}


function ativar(id, acao){
    $.ajax({
        url: 'paginas/' + pag + "/mudar-status.php",
        method: 'POST',
        data: bb_merge_loja({id, acao}),
        dataType: "text",

        success: function (mensagem) {            
            if (mensagem.trim() == "Alterado com Sucesso") {                
                listar();                
            } else {
                $('#mensagem-excluir').addClass('text-danger')
                $('#mensagem-excluir').text(mensagem)
            }

        },      

    });
}





function inserir(){
    $('#mensagem').text('');
    $('#titulo_inserir').text('Inserir Registro');
    $('#modalForm').modal('show');
    limparCampos();
}
function inserirW(){
    $('#mensagem').text('');
    $('#modalForm').modal('show');
}

function conectar(){
    $('#mensagem').text('');
    $('#conectar').modal('show');
}


$("#form").submit(function () {

    event.preventDefault();
    var formData = new FormData(this);
    formData = bb_merge_loja_formdata(formData);

    $.ajax({
        url: 'paginas/' + pag + "/salvar.php",
        type: 'POST',
        data: formData,

        success: function (mensagem) {
            $('#mensagem').text('');
            $('#mensagem').removeClass()
            if (mensagem.trim() == "Salvo com Sucesso") {

                $('#btn-fechar').click();
                listar();          

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

function bb_merge_loja(obj) {
    if (!obj) obj = {};
    try {
        if (!Object.prototype.hasOwnProperty.call(obj, 'loja')) {
            var s = bb_get_loja_slug();
            if (s) obj['loja'] = s;
        }
    } catch (e) {}
    return obj;
}


function bb_get_loja_slug() {
    try {
        var slugServidor = (window.BB_TENANT_SLUG || '').toString().toLowerCase().replace(/[^a-z0-9_-]/g, '');
        if (slugServidor) return slugServidor;

        var path = (window.location && window.location.pathname) ? window.location.pathname : '';
        var m = path.match(/^\/loja\/([a-z0-9_-]+)(?:\/|$)/i);
        if (m && m[1]) {
            return (m[1] + '').toLowerCase().replace(/[^a-z0-9_-]/g, '');
        }
    } catch (e) {}
    return '';
}

function bb_merge_loja_formdata(fd) {
    try {
        if (!fd) return fd;
        var s = bb_get_loja_slug();
        if (!s) return fd;

        // evita duplicar
        if (typeof fd.get === 'function') {
            var atual = fd.get('loja');
            if (atual) return fd;
        }
        fd.append('loja', s);
    } catch (e) {}
    return fd;
}

