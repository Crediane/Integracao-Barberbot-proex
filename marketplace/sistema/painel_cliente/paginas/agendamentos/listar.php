<?php
// ARQUIVO: /public_html/sistema/painel_cliente/paginas/agendamentos/listar.php
// Correções (sem alterar regra de negócio):
// - Evita WARNING "Undefined array key id" (PHP 8+) validando sessão
// - Mantém padrão do sistema (conexao.php + verificar.php)
// - Mantém consulta e fluxo de listagem/exclusão (somente mais seguro)

require_once __DIR__ . '/../../verificar.php';

$tabela = 'agendamentos';
$data_atual = date('Y-m-d');

// Evita warning no PHP 8+: sessão sem 'id'
$id_cliente = (int)($_SESSION['id'] ?? 0);
if ($id_cliente <= 0) {
    echo '<small>Acesso inválido. Faça login novamente.</small>';
    exit();
}

// Fallback para evitar notices caso venha vazio
if (!isset($quantidade_cartoes)) {
    $quantidade_cartoes = 0;
}

// Lista somente agendamentos do cliente (mesma lógica, só prepared)
$stmt = $pdo->prepare("SELECT * FROM agendamentos WHERE cliente = :cliente ORDER BY id DESC");
$stmt->execute([':cliente' => $id_cliente]);
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);

if($total_reg > 0){	

echo <<<HTML
	<small>
	<table class="table table-hover" id="tabela">
	<thead> 
	<tr> 
	<th>Serviço</th>	
	<th>Data</th> 	
	<th>Hora</th> 	
	<th>Profissional</th> 
	<th>Status</th> 		 	
	<th>Excluir</th>
	</tr> 
	</thead> 
	<tbody>	
HTML;

for($i=0; $i < $total_reg; $i++){
	foreach ($res[$i] as $key => $value){}

	$id = (int)($res[$i]['id'] ?? 0);
	$funcionario = (int)($res[$i]['funcionario'] ?? 0);
	$cliente = (int)($res[$i]['cliente'] ?? 0);
	$hora = (string)($res[$i]['hora'] ?? '');
	$data = (string)($res[$i]['data'] ?? '');
	$usuario = (int)($res[$i]['usuario'] ?? 0);
	$data_lanc = (string)($res[$i]['data_lanc'] ?? '');
	$obs = (string)($res[$i]['obs'] ?? '');
	$status = (string)($res[$i]['status'] ?? '');
	$servico = (int)($res[$i]['servico'] ?? 0);
	$ref_pix = (string)($res[$i]['ref_pix'] ?? '');

	$dataF = $data ? implode('/', array_reverse(explode('-', $data))) : '';
	$horaF = $hora ? date("H:i", strtotime($hora)) : '';

	if($status == 'Concluído'){		
		$classe_linha = '';
	}else{		
		$classe_linha = 'text-muted';
	}

	if($status == 'Agendado'){
		$imagem = 'icone-relogio.png';
		$classe_status = '';
		$classe_agd = 'text-danger';	
	}else{
		$imagem = 'icone-relogio-verde.png';
		$classe_status = 'ocultar';
		$classe_agd = 'verde';	
	}

	// Usuário (criou/lancou)
	$stmt2 = $pdo->prepare("SELECT * FROM usuarios WHERE id = :id");
	$stmt2->execute([':id' => $usuario]);
	$res2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
	$nome_usu = (@count($res2) > 0) ? $res2[0]['nome'] : 'Sem Usuário';

	// Profissional
	$stmt2 = $pdo->prepare("SELECT * FROM usuarios WHERE id = :id");
	$stmt2->execute([':id' => $funcionario]);
	$res2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
	$nome_func = (@count($res2) > 0) ? $res2[0]['nome'] : 'Sem Usuário';

	// Serviço
	$stmt2 = $pdo->prepare("SELECT * FROM servicos WHERE id = :id");
	$stmt2->execute([':id' => $servico]);
	$res2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
	if(@count($res2) > 0){
		$nome_serv = $res2[0]['nome'];
		$valor_serv = $res2[0]['valor'];
	}else{
		$nome_serv = 'Não Lançado';
		$valor_serv = '';
	}

	// Cliente (cartões)
	$stmt2 = $pdo->prepare("SELECT * FROM clientes WHERE id = :id");
	$stmt2->execute([':id' => $cliente]);
	$res2 = $stmt2->fetchAll(PDO::FETCH_ASSOC);
	if(@count($res2) > 0){
		$nome_cliente = $res2[0]['nome'];
		$total_cartoes = $res2[0]['cartoes'];
	}else{
		$nome_cliente = 'Sem Cliente';
		$total_cartoes = 0;
	}

	if($total_cartoes >= $quantidade_cartoes and $status == 'Agendado'){
		$ocultar_cartoes = '';
	}else{
		$ocultar_cartoes = 'ocultar';
	}

	// retirar aspas do texto do obs
	$obs = str_replace('"', "**", $obs);

echo <<<HTML
<tr class="">
	<td><i class="fa fa-square {$classe_agd}"></i> {$nome_serv}</td>
	<td class="esc">{$dataF}</td>
	<td class="esc">{$horaF}</td>
	<td class="esc">{$nome_func}</td>
	<td class="esc">{$status}</td>
	<td>
		<li class="dropdown head-dpdn2" style="display: inline-block;">
			<a class="dropdown-toggle {$classe_status}" title="Cancelar Agendamento" href="#" data-toggle="dropdown" aria-expanded="false">
				<big><i class="fa fa-trash-o text-danger"></i></big>
			</a>

			<ul class="dropdown-menu" style="margin-left:-230px;">
				<li>
					<div class="notification_desc2">
						<p>Confirmar Exclusão? 
							<a href="javascript:void(0)" onclick="excluirAgd('{$id}'); return false;">
								<span class="text-danger">Sim</span>
							</a>
						</p>
					</div>
				</li>										
			</ul>
		</li>
	</td>
</tr>
HTML;

}

echo <<<HTML
</tbody>
<small><div align="center" id="mensagem-excluir"></div></small>
</table>
</small>
HTML;

}else{
	echo '<small>Não possui nenhum registro Cadastrado!</small>';
}

?>

<script type="text/javascript">
	$(document).ready( function () {
		$('#tabela').DataTable({
			"ordering": false,
			"stateSave": true
		});
		$('#tabela_filter label input').focus();
	} );
</script>
