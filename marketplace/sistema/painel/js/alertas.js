function sucesso(){
		Swal.fire({
			  title: "Sucesso!",
			  text: "Salvo com Sucesso!",
			  icon: "success"
			});
		}	



function excluido(){
		Swal.fire({
			  
			  text: "Excluido com Sucesso!",
			  icon: "error"
			});
		}


function alertcobrar(){
		Swal.fire({
			  title: "Sucesso!",
			  text: "Cobrança Efetuada!",
			  icon: "success"
			});
		}	


	function baixado(){
		Swal.fire({
			  title: "Sucesso!",
			  text: "Baixado com Sucesso!",
			  icon: "success"
			});
		}







//############### ALERTAS #########################################

// ALERT SALVAR FINAL ##############
function alertSucesso(mensagem) {
    $('body').removeClass('timer-alert');
    Swal.fire({
        title: 'Sucesso!',
        text: mensagem,
        icon: "success",
        timer: 2000,
        timerProgressBar: true,
        confirmButtonText: 'OK',
        customClass: {
            container: 'swal-whatsapp-container'
        }
    })
}

function alertsucesso(mensagem) {
    $('body').removeClass('timer-alert');
    Swal.fire({
        title: mensagem,
        text: 'Fecharei em 1 segundo.',
        icon: "success",
        timer: 2000,
        timerProgressBar: true,
        confirmButtonText: 'OK',
        customClass: {
            container: 'swal-whatsapp-container'
        }
    })
}

function alertCobrar(mensagem) {
    $('body').removeClass('timer-alert');
    Swal.fire({
        title: mensagem,
        icon: "success",
        confirmButtonText: 'OK',
        customClass: {
            container: 'swal-whatsapp-container'
        }
    })
}


// ALERT ERRO ##############
function alertErro(result) {
    Swal.fire({
        title: 'Opss...',
        text: result,
        icon: "error",
        confirmButtonText: 'OK',
        customClass: {
            container: 'swal-whatsapp-container'
        }
    })
}


function alertWhatsapp(result) {
    Swal.fire({
        title: 'Alerta!',
        text: result,
        icon: "success",
        confirmButtonText: 'OK',
        customClass: {
            container: 'swal-whatsapp-container'
        }
    })
}

function alertStaticSucesso(result) {
    Swal.fire({
        title: 'Sucesso!',
        text: result,
        icon: "success",
        confirmButtonText: 'OK',
        customClass: {
            container: 'swal-whatsapp-container'
        }
    })
}

function alertWarning(result) {
    Swal.fire({
        title: 'Alerta!',
        text: result,
        icon: "warning",
        confirmButtonText: 'OK',
        customClass: {
            container: 'swal-whatsapp-container'
        }
    })
}



// ALERT EXCLUIR #######################################
function excluirAlert(id) {
    const swalWithBootstrapButtons = Swal.mixin({
        customClass: {
            confirmButton: "btn btn-success", // Adiciona margem à direita do botão "Sim, Excluir!"
            cancelButton: "btn btn-danger me-1",
            container: 'swal-whatsapp-container'
        },
        buttonsStyling: false
    });

    swalWithBootstrapButtons.fire({
        title: "Deseja Excluir?",
        text: "Você não conseguirá recuperá-lo novamente!",
        icon: "warning",
        showCancelButton: true,
        confirmButtonText: "Sim, Excluir!",
        cancelButtonText: "Não, Cancelar!",
        reverseButtons: true
    }).then((result) => {
        if (result.isConfirmed) {
            // Realiza a requisição AJAX para excluir o item
            $.ajax({
                url: 'paginas/' + pag + "/excluir.php",
                method: 'POST',
                data: { id },
                dataType: "html",
                success: function (mensagem) {
                    if (mensagem.trim() == "Excluído com Sucesso") {
                        // Exibe mensagem de sucesso após a exclusão
                        swalWithBootstrapButtons.fire({
                            title: mensagem,
                            text: 'Fecharei em 1 segundo.',
                            icon: "success",
                            timer: 1000,
                            timerProgressBar: true,
                            confirmButtonText: 'OK',

                        });
                        listar();
                        limparCampos()
                    } else {
                        // Exibe mensagem de erro se a requisição falhar
                        swalWithBootstrapButtons.fire({
                            title: "Opss!",
                            text: mensagem,
                            icon: "error",
                            confirmButtonText: 'OK',
                        });
                    }
                }
            });
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
