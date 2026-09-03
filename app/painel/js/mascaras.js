$(document).ready(function () {
	$('#telefone').mask('(00) 00000-0000');
	$('#whatsapp').mask('(00) 00000-0000');
	$('#cpf').mask('000.000.000-00');
	$('#cep').mask('00000-000');
	$('#cnpj').mask('00.000.000/0000-00');
	$('#data').mask('00/00/0000');
	$('#data_nasc').mask('00/00/0000');
	$('#telefone_perfil').mask('(00) 00000-0000');
	$('#cpf_perfil').mask('000.000.000-00');
	$('#telefone_sistema').mask('(00) 00000-0000');
	$('#cnpj_sistema').mask('00.000.000/0000-00');

	$('#telefone2').mask('(00) 00000-0000');
});

function verificarTelefone(tel, valor) {

	if (valor.length > 14) {
		$('#' + tel).mask('(00) 00000-0000');
	} else if (valor.length == 14) {
		$('#' + tel).mask('(00) 0000-00000');
	} else {
		$('#' + tel).mask('(00) 0000-0000');

	}
}


//############### MÁSCRAS MOEDAS #########################################
function mascaraMoeda(elemento) {
    let value = elemento.value;
    // Remove todos os caracteres que não são números
    value = value.replace(/\D/g, '');
    // Converte o valor para um número decimal com duas casas
    value = (value / 100).toFixed(2);
    // Substitui ponto por vírgula
    value = value.replace('.', ',');
    // Adiciona separadores de milhar
    value = value.replace(/\B(?=(\d{3})+(?!\d))/g, '.');
    // Atualiza o valor do input sem o prefixo "R$"
    elemento.value = value;
}