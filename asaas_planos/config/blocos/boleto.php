<?php 

$query2 = $pdo->query("SELECT * FROM clientes where id = '$cliente'");
$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
$nome = @$res2[0]['nome'];
$cpf = @$res2[0]['cpf'];
$email = @$res2[0]['email'];
$telefone = @$res2[0]['telefone'];
$cep = @$res2[0]['cep'];
$rua = @$res2[0]['endereco'];
$numero = @$res2[0]['numero'];
$complemento = '';
$bairro = @$res2[0]['bairro'];
$cidade = @$res2[0]['cidade'];
$estado = @$res2[0]['estado'];

 ?>
<h2 class="h5 px-4 py-3 accordion-header d-flex justify-content-between align-items-center">
    <div class="form-check w-100 collapsed" for="payment2" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#collapseBoleto" aria-expanded="false">
        <label class="form-check-label pt-1" style="cursor: pointer;" for="payment2">Boleto Bancário</label>
    </div>
    <span>
        <svg width="34" height="25" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 576 512">
            <path fill="#333840" d="M528 32H48C21.5 32 0 53.5 0 80v352c0 26.5 21.5 48 48 48h480c26.5 0 48-21.5 48-48V80c0-26.5-21.5-48-48-48zM48 64h480c8.8 0 16 7.2 16 16v48H32V80c0-8.8 7.2-16 16-16zm480 384H48c-8.8 0-16-7.2-16-16V224h512v208c0 8.8-7.2 16-16 16zm-336-84v8c0 6.6-5.4 12-12 12h-72c-6.6 0-12-5.4-12-12v-8c0-6.6 5.4-12 12-12h72c6.6 0 12 5.4 12 12zm192 0v8c0 6.6-5.4 12-12 12H236c-6.6 0-12-5.4-12-12v-8c0-6.6 5.4-12 12-12h136c6.6 0 12 5.4 12 12z"/>
        </svg>
    </span>
</h2>
<div id="collapseBoleto" class="accordion-collapse collapse container" data-bs-parent="#accordionPayment">
    <form id="form-boleto" class="py-3">
        <div class="row g-3">
            <!-- Dados Pessoais -->
            <div class="col-md-8">
                <input type="text" class="form-control" name="nome" placeholder="Nome completo" required value="<?php echo $nome ?>">
            </div>
            <div class="col-md-4">
                <input type="text" class="form-control" name="cpf" id="cpf-boleto" placeholder="CPF" required value="<?php echo $cpf ?>">
            </div>

            <!-- Contato -->
            <div class="col-md-8">
                <input type="text" class="form-control" name="email" placeholder="E-mail" required value="<?php echo $email ?>">
            </div>
            <div class="col-md-4">
                <input type="text" class="form-control" name="telefone" id="phone-boleto" maxlength="15" placeholder="Telefone" required value="<?php echo $telefone ?>">
            </div>

            <!-- Endereço -->
            <div class="col-md-4">
                <input type="text" class="form-control" id="cep-boleto" name="cep" maxlength="9" placeholder="CEP" oninput="this.value = this.value.replace(/[^\d]/g, ''); buscarEnderecoBoleto();" required value="<?php echo $cep ?>">
            </div>
            <div class="col-md-6">
                <input type="text" class="form-control" id="logradouro-boleto" name="rua" placeholder="Rua" required value="<?php echo $rua ?>">
            </div>
            <div class="col-md-2">
                <input type="text" class="form-control" name="numero" placeholder="Nº" required value="<?php echo $numero ?>">
            </div>

            <div class="col-md-4">
                <input type="text" class="form-control" id="bairro-boleto" name="bairro" placeholder="Bairro" required value="<?php echo $bairro ?>">
            </div>
            <div class="col-md-4">
                <input type="text" class="form-control" id="cidade-boleto" name="cidade" placeholder="Cidade" required value="<?php echo $cidade ?>">
            </div>
            <div class="col-md-2">
                <input type="text" class="form-control" id="estado-boleto" name="estado" placeholder="UF" required value="<?php echo $estado ?>">
            </div>
            <div class="col-md-2">
                <input type="text" class="form-control" name="complemento" placeholder="Compl." value="<?php echo $complemento ?>">
            </div>

            

            <div id="resposta-boleto"></div>

            <!-- Botão e Segurança -->
            <div class="col-12 mt-3">
                <button type="submit" class="btn btn-primary w-100">
                    <i class="fas fa-barcode me-2"></i>Gerar Boleto
                </button>
                <div class="security-info mt-2 text-center small">
                    <i class="fas fa-shield-alt text-success me-1"></i>
                    <span>Seus dados estão protegidos e o ambiente é seguro.</span>
                </div>
            </div>
        </div>
    </form>
</div>

<!-- Modal para exibir o boleto -->
<div id="myModalBoleto" class="modal" style="display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.4);">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Boleto Bancário</h5>
                <button id="closeModalBoleto" type="button" class="btn-close" style="display: none;"></button>
            </div>
            <div class="modal-body" id="modalBodyBoleto">
                <!-- O conteúdo do boleto será carregado aqui -->
            </div>
        </div>
    </div>
</div>


<script>
    // Máscaras e validações
    document.getElementById('cpf-boleto').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, ''); // Remove não números
        if (value.length > 11) value = value.slice(0, 11); // Limita a 11 números

        // Formata como 123.456.789-09
        value = value.replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d)/, '$1.$2')
            .replace(/(\d{3})(\d{1,2})$/, '$1-$2');

        e.target.value = value;
    });

    document.getElementById('phone-boleto').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, ''); // Remove não números
        if (value.length > 11) value = value.slice(0, 11); // Limita a 11 números

        // Formata como (11) 91234-5678
        value = value.replace(/(\d{2})(\d)/, '($1) $2')
            .replace(/(\d{5})(\d)/, '$1-$2');

        e.target.value = value;
    });

    document.getElementById('cep-boleto').addEventListener('input', function(e) {
        let value = e.target.value.replace(/\D/g, ''); // Remove não números
        if (value.length > 8) value = value.slice(0, 8); // Limita a 8 números

        // Formata como 12345-678
        if (value.length > 5) {
            value = value.replace(/(\d{5})(\d{1,3})/, '$1-$2');
        }

        e.target.value = value;
    });

    // Função para buscar endereço pelo CEP
    function buscarEnderecoBoleto() {
        var cep = document.getElementById("cep-boleto").value.replace("-", "");
        if (cep.length === 8) {
            var url = `https://viacep.com.br/ws/${cep}/json/`;
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (!data.erro) {
                        document.getElementById("logradouro-boleto").value = data.logradouro;
                        document.getElementById("bairro-boleto").value = data.bairro;
                        document.getElementById("cidade-boleto").value = data.localidade;
                        document.getElementById("estado-boleto").value = data.uf;
                    } else {
                        alert("CEP não encontrado!");
                    }
                })
                .catch(error => {
                    console.error("Erro ao buscar CEP:", error);
                });
        } else {
            // Limpar os campos se o CEP for inválido
            document.getElementById("logradouro-boleto").value = '';
            document.getElementById("bairro-boleto").value = '';
            document.getElementById("cidade-boleto").value = '';
            document.getElementById("estado-boleto").value = '';
        }
    }
</script>