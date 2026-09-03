<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';

$pagina = 'cobrancas';

// Sanitização (sem mudar regra)
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;

function bb_data_br($data_mysql)
{
    $data_mysql = (string)$data_mysql;
    if ($data_mysql === '' || $data_mysql === '0000-00-00') {
        return '';
    }
    return implode('/', array_reverse(explode('-', $data_mysql)));
}

echo <<<HTML
<small>
HTML;

// Se não veio cliente válido, mantém padrão de mensagem simples
if ($id <= 0) {
    echo 'Não possui nenhum emprestimo cadastrado!';
    echo '</small>';
    exit;
}

// ✅ Prepared Statement (sem alterar lógica)
$stmt = $pdo->prepare("SELECT * FROM $pagina WHERE cliente = :cliente ORDER BY id DESC");
$stmt->bindValue(':cliente', $id, PDO::PARAM_INT);
$stmt->execute();
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);

$total_reg = @count($res);
if ($total_reg > 0) {

    echo <<<HTML
	<table class="table table-hover" id="">
		<thead> 
			<tr> 				
				<th>Valor</th>
				<th>Parcelas</th>				
				<th>Vencimento</th>	
				<th>Data</th>
				<th>Ver Parcelas</th>
			</tr> 
		</thead> 
		<tbody> 
HTML;

    for ($i = 0; $i < $total_reg; $i++) {
        foreach ($res[$i] as $key => $value) {
        }

        $id_emp = (int)$res[$i]['id'];
        $valor = $res[$i]['valor'];
        $parcelas = (int)$res[$i]['parcelas'];
        $data_venc = $res[$i]['data_venc'] ?? '';
        $data = $res[$i]['data'] ?? '';

        // Mantém a mesma formatação
        $data_vencF = $data_venc ? date('d', strtotime($data_venc)) : '';
        $dataF = bb_data_br($data);
        $valorF = number_format((float)$valor, 2, ',', '.');

        // Verificar se tem parcela vencida em receber (mesma regra)
        $classe_deb = '';
        $stmt2 = $pdo->prepare("SELECT id FROM receber 
            WHERE referencia = 'Cobrança' 
            AND id_ref = :id_ref 
            AND data_venc < curDate() 
            AND pago != 'Sim'
            LIMIT 1");
        $stmt2->bindValue(':id_ref', $id_emp, PDO::PARAM_INT);
        $stmt2->execute();
        if ($stmt2->rowCount() > 0) {
            $classe_deb = 'text-danger';
        }

        // Verificar parcelas pagas (mesma regra)
        $stmt3 = $pdo->prepare("SELECT COUNT(*) AS total FROM receber 
            WHERE referencia = 'Cobrança' 
            AND id_ref = :id_ref 
            AND pago = 'Sim'");
        $stmt3->bindValue(':id_ref', $id_emp, PDO::PARAM_INT);
        $stmt3->execute();
        $row3 = $stmt3->fetch(PDO::FETCH_ASSOC);
        $parcelas_pagas = isset($row3['total']) ? (int)$row3['total'] : 0;

        if ($parcelas > 1) {
            $parcelas_nome = $parcelas_pagas . ' / ' . $parcelas;
        } else {
            $parcelas_nome = 'Recorrente ';
        }

        echo <<<HTML
			<tr>					
				<td class="{$classe_deb}">R$ {$valorF}</td>
				<td class="esc">{$parcelas_nome}</td>				
				<td class="esc">Dia {$data_vencF}</td>				
				<td class="esc">{$dataF}</td>
				<td>					
					<big><a href="#" onclick="mostrarParcelas('{$id_emp}')" title="Mostrar Parcelas"><i class="fa fa-money verde"></i></a></big>
				</td>  
			</tr> 
HTML;
    }

    echo <<<HTML
		</tbody> 
	</table>
</small>
HTML;

} else {
    echo 'Não possui nenhum emprestimo cadastrado!';
}

?>

<script type="text/javascript">
	function mostrarParcelas(id_emp){

		$('#id_emprestimo').val('');

		var mostrar = 'cobranca';

		$.ajax({
			url: 'paginas/' + pag + "/mostar_parcelas.php",
			method: 'POST',
			data: {id_emp, mostrar},
			dataType: "text",
			success: function (mensagem) {
				$("#listar_parcelas").html(mensagem);
			}
		});

		$('#id_cobranca').val(id_emp);
		$('#modalParcelas').modal('show');
	}
</script>
