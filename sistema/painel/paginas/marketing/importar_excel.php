<?php 
require_once __DIR__ . '/../_context_bootstrap.php';


// Guarda slug na sessão (ajuda includes/conexão a escolher o banco da loja)
if (!empty($__bb_reqSlug) && empty($_SESSION['loja_slug'])) {
  $_SESSION['loja_slug'] = $__bb_reqSlug;
}

require_once '../../funcoes/SimpleXLSX.php';
use Shuchkin\SimpleXLSX; // <-- ESSENCIAL para funcionar

// Variáveis fixas para inserir nos outros campos
if ($_FILES['arquivo_excel']['name'] != "") {
	$arquivo = $_FILES['arquivo_excel']['tmp_name'];

	if ($xlsx = SimpleXLSX::parse($arquivo)) {
		$dados = $xlsx->rows();

        // Ignora a primeira linha (cabeçalho)
		for ($i = 1; $i < count($dados); $i++) {
			$nome = $dados[$i][0] ?? '';
			$telefone = $dados[$i][1] ?? '';

			if($hora_disparo == "agora"){
				$hora_disparo = date('H:i:s');
				
			}		

				$data_base = $data_disparo; // Ex: '2025-06-27'
				$timestamp_inicial = strtotime("$data_base $hora_disparo");

				// Calcular timestamp final com base no intervalo (em horas)
				$timestamp_final = strtotime("+{$intervalo_horas} hour", $timestamp_inicial);

				// Gerar um timestamp aleatório entre início e fim
				$timestamp_randomico = rand($timestamp_inicial, $timestamp_final);

				// Formatar a hora e a data reais do disparo
				$hora = date('H:i:s', $timestamp_randomico);
				$data_disparo_real = date('Y-m-d', $timestamp_randomico);




				if ($nome != '' && $telefone != '') {
					$query = $pdo->prepare("INSERT INTO disparos (campanha, cliente, nome, telefone, hora, empresa, data_disparo) 
						VALUES (:campanha, :cliente, :nome, :telefone, :hora, :empresa, :data_disparo)");

					$query->bindValue(":campanha", $id);
					$query->bindValue(":cliente", 0);
					$query->bindValue(":nome", $nome);
					$query->bindValue(":telefone", $telefone);
					$query->bindValue(":hora", $hora);
					$query->bindValue(":empresa", $id_empresa);
					$query->bindValue(":data_disparo", $data_disparo_real);
					$query->execute();
				}


				if($frequencia > 0){
					for ($if = 2; $if <= $quantidade; $if++) {
						$dias_frequencia = $frequencia;
						$dias_parcela = $if - 1;
						$dias_parcela_2 = ($if - 1) * $dias_frequencia;
						
						if ($dias_frequencia == 30 || $dias_frequencia == 31) {

							$nova_data = date('Y/m/d', strtotime("+$dias_parcela month", strtotime($data_disparo_real)));

						} else if ($dias_frequencia == 90) {
							$dias_parcela = $dias_parcela * 3;
							$nova_data = date('Y/m/d', strtotime("+$dias_parcela month", strtotime($data_disparo_real)));

						} else if ($dias_frequencia == 180) {

							$dias_parcela = $dias_parcela * 6;
							$nova_data = date('Y/m/d', strtotime("+$dias_parcela month", strtotime($data_disparo_real)));

						} else if ($dias_frequencia == 360 || $dias_frequencia == 365) {

							$dias_parcela = $dias_parcela * 12;
							$nova_data = date('Y/m/d', strtotime("+$dias_parcela month", strtotime($data_disparo_real)));

						} else {

							$nova_data = date('Y/m/d', strtotime("+$dias_parcela_2 days", strtotime($data_disparo_real)));
						}

						if ($nome != '' && $telefone != '') {
							$query = $pdo->prepare("INSERT INTO disparos (campanha, cliente, nome, telefone, hora, empresa, data_disparo) 
								VALUES (:campanha, :cliente, :nome, :telefone, :hora, :empresa, :data_disparo)");

							$query->bindValue(":campanha", $id);
							$query->bindValue(":cliente", 0);
							$query->bindValue(":nome", $nome);
							$query->bindValue(":telefone", $telefone);
							$query->bindValue(":hora", $hora);
							$query->bindValue(":empresa", $id_empresa);
							$query->bindValue(":data_disparo", $nova_data);
							$query->execute();
						}

					}
				}
		}


	} else {
		echo 'Erro ao ler o arquivo.';
	}   

} 

 ?>