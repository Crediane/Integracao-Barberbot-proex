<?php 

$query2 = $pdo->query("SELECT * FROM clientes where id = '$cliente'");
$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
$nome = @$res2[0]['nome'];
$cpf = @$res2[0]['cpf'];
$email = @$res2[0]['email'];
$telefone = @$res2[0]['telefone'];
$rua = @$res2[0]['endereco'];


 ?>
<h2 class="h5 px-4 py-3 accordion-header d-flex justify-content-between align-items-center">
    <div class="form-check w-100 collapsed" for="payment1" style="cursor: pointer;" data-bs-toggle="collapse" data-bs-target="#collapseCC" aria-expanded="false">
        <label class="form-check-label pt-1" style="cursor: pointer;" for="payment1">Cartão de Crédito</label>
    </div>
    <span>
        <svg width="34" height="25" xmlns="http://www.w3.org/2000/svg">
            <g fill-rule="nonzero" fill="#333840">
                <path d="M29.418 2.083c1.16 0 2.101.933 2.101 2.084v16.666c0 1.15-.94 2.084-2.1 2.084H4.202A2.092 2.092 0 0 1 2.1 20.833V4.167c0-1.15.941-2.084 2.102-2.084h25.215ZM4.203 0C1.882 0 0 1.865 0 4.167v16.666C0 23.135 1.882 25 4.203 25h25.215c2.321 0 4.203-1.865 4.203-4.167V4.167C33.62 1.865 31.739 0 29.418 0H4.203Z"></path>
                <path d="M4.203 7.292c0-.576.47-1.042 1.05-1.042h4.203c.58 0 1.05.466 1.05 1.042v2.083c0 .575-.47 1.042-1.05 1.042H5.253c-.58 0-1.05-.467-1.05-1.042V7.292Zm0 6.25c0-.576.47-1.042 1.05-1.042H15.76c.58 0 1.05.466 1.05 1.042 0 .575-.47 1.041-1.05 1.041H5.253c-.58 0-1.05-.466-1.05-1.041Zm0 4.166c0-.575.47-1.041 1.05-1.041h2.102c.58 0 1.05.466 1.05 1.041 0 .576-.47 1.042-1.05 1.042H5.253c-.58 0-1.05-.466-1.05-1.042Zm6.303 0c0-.575.47-1.041 1.051-1.041h2.101c.58 0 1.051.466 1.051 1.041 0 .576-.47 1.042-1.05 1.042h-2.102c-.58 0-1.05-.466-1.05-1.042Zm6.304 0c0-.575.47-1.041 1.051-1.041h2.101c.58 0 1.05.466 1.05 1.041 0 .576-.47 1.042-1.05 1.042h-2.101c-.58 0-1.05-.466-1.05-1.042Zm6.304 0c0-.575.47-1.041 1.05-1.041h2.102c.58 0 1.05.466 1.05 1.041 0 .576-.47 1.042-1.05 1.042h-2.101c-.58 0-1.05-.466-1.05-1.042Z"></path>
            </g>
        </svg>
    </span>
</h2>
<div id="collapseCC" class="accordion-collapse collapse container" data-bs-parent="#accordionPayment">
    <form id="form-checkout" class="py-3">
        <div class="row g-3">
            <!-- Dados do titular e cartão -->
            <div class="col-md-8">
                <input type="text" class="form-control" name="nome" placeholder="Titular do cartão" value="<?php echo $nome ?>">
            </div>
            <div class="col-md-4">
                <input type="text" class="form-control" name="cpf" id="cpf" placeholder="CPF" value="<?php echo $cpf ?>">
            </div>

            <!-- Dados do cartão -->
            <div class="col-md-6">
                <input type="text" class="form-control" name="cardnumber" id="card-number" placeholder="Número do cartão" maxlength="19">
            </div>
            <div class="col-md-3">
                <input type="text" class="form-control" name="expdate" id="exp-date" placeholder="MM/YY" maxlength="7">
            </div>
            <div class="col-md-3">
                <input type="text" class="form-control" name="ccv" placeholder="CVV">
            </div>

            <!-- Parcelas -->
            <div class="col-md-6">
                <select name="parcelas" class="form-control">
                    <option value="1">1x de R$ <?php echo floatval($valor);?></option>
                    <?php
                    for ($i = 2; $i <= 5; $i++) {
                        $valor_com_juros = floatval($valor);
                        $juros = 1.99 / 100;
                        $valor_parcela = $valor_com_juros * ($juros / (1 - (1 + $juros) ** (-$i)));
                        echo "<option value='$i'> ".$i."x de R$ " . number_format($valor_parcela, 2, ',', '.') . " </option>";
                    }
                    ?>
                </select>
            </div>

            <!-- Contato -->
            <div class="col-md-6">
                <input type="text" class="form-control" name="telefone" id="phone" maxlength="15" placeholder="Telefone" value="<?php echo $telefone ?>">
            </div>
            <div class="col-md-12">
                <input type="text" class="form-control" name="email" placeholder="E-mail" value="<?php echo $email ?>">
            </div>

            <!-- Endereço -->
            <div class="col-md-3">
                <input type="text" class="form-control" id="cep" name="cep" maxlength="9" placeholder="CEP" oninput="this.value = this.value.replace(/[^\d]/g, ''); buscarEndereco();">
            </div>
            <div class="col-md-6">
                <input type="text" class="form-control" id="logradouro" name="rua" placeholder="Rua" value="<?php echo $rua ?>">
            </div>
            <div class="col-md-3">
                <input type="text" class="form-control" name="numero" placeholder="Nº" value="<?php echo $numero ?>">
            </div>
            <div class="col-md-6">
                <input type="text" class="form-control" id="bairro" name="bairro" placeholder="Bairro" value="<?php echo $bairro ?>">
            </div>

            <div class="col-md-4">
                <input type="text" class="form-control" id="cidade" name="cidade" placeholder="Cidade" value="<?php echo $cidade ?>">
            </div>
            <div class="col-md-2">
                <input type="text" class="form-control" id="estado" name="estado" placeholder="UF" value="<?php echo $estado ?>">
            </div>
            <div class="col-md-12">
                <button type="submit" class="btn btn-primary w-100">Efetuar pagamento</button>
            </div>
            
            <div id="resposta"></div>

            <div class="col-12">
                <div class="security-info text-center small">
                    <i class="fas fa-shield-alt text-success me-1"></i>
                    <span>Seus dados estão protegidos e o ambiente é seguro.</span>
                </div>
            </div>
        </div>
    </form>
</div>


<script>
document.getElementById('card-number').addEventListener('input', function(e) {
    e.target.value = e.target.value.replace(/\D/g, '') // Remove não números
        .replace(/(\d{4})/g, '$1 ') // Adiciona espaço a cada 4 dígitos
        .trim(); // Remove espaço extra no final
});
document.getElementById('exp-date').addEventListener('input', function(e) {
    e.target.value = e.target.value
        .replace(/\D/g, '') // Remove não números
        .replace(/(\d{2})(\d{0,4})/, '$1/$2') // Adiciona a barra após dois dígitos
        .trim(); // Remove espaço extra
});
document.getElementById('cpf').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, ''); // Remove não números
    if (value.length > 11) value = value.slice(0, 11); // Limita a 11 números

    // Formata como 123.456.789-09
    value = value.replace(/(\d{3})(\d)/, '$1.$2')
                 .replace(/(\d{3})(\d)/, '$1.$2')
                 .replace(/(\d{3})(\d{1,2})$/, '$1-$2');

    e.target.value = value;
});
document.getElementById('phone').addEventListener('input', function(e) {
    let value = e.target.value.replace(/\D/g, ''); // Remove não números
    if (value.length > 11) value = value.slice(0, 11); // Limita a 11 números

    // Formata como (11) 91234-5678
    value = value.replace(/(\d{2})(\d)/, '($1) $2')
                 .replace(/(\d{5})(\d)/, '$1-$2');

    e.target.value = value;
});
</script>
<script>
document.getElementById("form-checkout").addEventListener("submit", function(event) {
    event.preventDefault(); 

    let form = document.getElementById("form-checkout");
    let formData = new FormData(form);
    let respostaDiv = document.getElementById("resposta");

    respostaDiv.innerHTML = "<p class='text-info'>Processando...</p>";

    fetch("<?php echo $url_sistema ?>asaas/config/processa.php?id=<?php echo $id_conta;?>&total=<?php echo $total ?>", {
        method: "POST",
        body: formData
    })
    .then(response => response.text()) // Obtém o HTML da resposta
    .then(html => {
        respostaDiv.innerHTML = html; // Carrega o conteúdo dentro da div
    })
    .catch(error => {
        respostaDiv.innerHTML = `<p class='text-danger'>Erro: ${error.message}</p>`;
    });
});
</script>

<script>
$(document).ready(function() {
    var modal = $('#myModal');
    var closeModal = $('#closeModal');

    // When the user clicks the button, open the modal
    $('#openModal').click(function() {
        $.get('<?php echo $url_sistema ?>painel/asaas/config/gerar_pix.php', function(data) {
            $('#modalBody').html(data);
            modal.show();
            
            // Enable closing the modal after 1 minute
            setTimeout(function() {
                closeModal.show();
            }, 5000); // 60000 milliseconds = 1 minute
        });
    });

    // Close the modal when the user clicks on <span> (x)
    closeModal.click(function() {
        modal.hide();
    });

    // Prevent modal from closing when clicking outside of it
    $(window).click(function(event) {
        if (event.target == modal[0]) {
            event.stopPropagation();
        }
    });
});
</script>

<script>
    function buscarEndereco() {
        var cep = document.getElementById("cep").value.replace("-", "");
        if (cep.length === 8) {
            var url = `https://viacep.com.br/ws/${cep}/json/`;
            fetch(url)
                .then(response => response.json())
                .then(data => {
                    if (!data.erro) {
                        document.getElementById("logradouro").value = data.logradouro;
                        document.getElementById("bairro").value = data.bairro;
                        document.getElementById("cidade").value = data.localidade;
                        document.getElementById("estado").value = data.uf;
                    } else {
                        alert("CEP não encontrado!");
                    }
                })
        } else {
            // Limpar os campos se o CEP for inválido
            document.getElementById("logradouro").value = '';
            document.getElementById("bairro").value = '';
            document.getElementById("cidade").value = '';
            document.getElementById("estado").value = '';
        }
    }
</script>