<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
$tabela = 'assinaturas';

$data_atual = date('Y-m-d');

$query = $pdo->query("SELECT * FROM $tabela ORDER BY id desc");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);
if($total_reg > 0){	

echo <<<HTML
	<small>
	<table class="table table-hover" id="tabela">
	<thead> 
	<tr> 
	<th>Cliente</th>	
	<th>Assinatura</th> 	
	<th>Plano</th> 	
	<th class="esc">Serviço Vinculado</th> 	
	<th>Valor</th> 
	<th>Data</th>
  <th>Status</th>
	<th>Ações</th>
	</tr> 
	</thead> 
	<tbody>	
HTML;

for($i=0; $i < $total_reg; $i++){
	foreach ($res[$i] as $key => $value){}
	$id = $res[$i]['id'];
	$cliente = $res[$i]['cliente'];	
	$data = $res[$i]['data'];
	$grupo = $res[$i]['grupo'];	
	$item = $res[$i]['item'];	
	$pago = $res[$i]['pago'];
	$valor = $res[$i]['valor'];	
	$ref_pix = $res[$i]['ref_pix'];	
	$frequencia = $res[$i]['frequencia'];
	$vencimento = $res[$i]['vencimento'];	
	$cancelado = $res[$i]['cancelado'];
  $ativo = $res[$i]['ativo'] ?? 'Sim'; // novo campo (default Sim)
	
		$query2 = $pdo->query("SELECT * FROM grupo_assinaturas where id = '$grupo'");
		$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
		$total_reg2 = @count($res2);
		if($total_reg2 > 0){
			$nome_grupo = $res2[0]['nome'];
		}else{
			$nome_grupo = 'Nenhum!';
		}

		$query2 = $pdo->query("SELECT * FROM itens_assinaturas where id = '$item'");
		$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
		$total_reg2 = @count($res2);
		if($total_reg2 > 0){
			$nome_item = $res2[0]['nome'];
			$id_servico_vinculado = isset($res2[0]['servico']) ? (int)$res2[0]['servico'] : 0;
		}else{
			$nome_item = 'Nenhum!';
			$id_servico_vinculado = 0;
		}
		$nome_servico_vinculado = 'Não Vinculado';
		if($id_servico_vinculado > 0){
			$queryServV = $pdo->query("SELECT nome FROM servicos where id = '$id_servico_vinculado'");
			$resServV = $queryServV->fetchAll(PDO::FETCH_ASSOC);
			if(@count($resServV) > 0){
				$nome_servico_vinculado = $resServV[0]['nome'];
			}
		}

		$query2 = $pdo->query("SELECT * FROM clientes where id = '$cliente'");
		$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
		$total_reg2 = @count($res2);
		if($total_reg2 > 0){
			$nome_cliente = $res2[0]['nome'];
		}else{
			$nome_cliente = 'Nenhum!';
		}
	
		$valorF = number_format($valor, 2, ',', '.');
	  $dataF = implode('/', array_reverse(@explode('-', $data)));

    // cor do “quadradinho” à esquerda
	  if($pago != "Sim"){
			$classe_alerta = 'text-danger';			
			$visivel_baixar = '';			
		}else{
			$classe_alerta = 'verde';
			$visivel_baixar = 'ocultar';			
		}

		$texto_cancelado = '';
		$ocultar_cancelar = '';
		if($cancelado == 'Sim'){
			$ocultar_cancelar = 'ocultar';
			$classe_alerta = 'text-warning';
			$texto_cancelado = '<span class="text-danger"> (Cancelado) </span>';	
		}

    // badge de status (novo)
    if ($ativo === 'Sim') {
      $badgeStatus = '<span class="badge badge-success">Ativo</span>';
      $acaoStatus  = "<a href=\"javascript:void(0)\" title=\"Desativar\" onclick=\"toggleAtivo('{$id}','Não'); return false;\"><i class=\"fa fa-toggle-on text-success\"></i></a>";
    } else {
      $badgeStatus = '<span class="badge badge-secondary">Inativo</span>';
      $acaoStatus  = "<a href=\"javascript:void(0)\" title=\"Ativar\" onclick=\"toggleAtivo('{$id}','Sim'); return false;\"><i class=\"fa fa-toggle-off text-muted\"></i></a>";
      // se inativo, não faz sentido baixar/cancelar
      $visivel_baixar   = 'ocultar';
      $ocultar_cancelar = 'ocultar';
      // cor do “quadradinho” fica cinza
      $classe_alerta = 'text-muted';
    }

		//verificar se o pagamento está aprovado
		if($pago == 'Não' and $ref_pix != ""){
			require_once __DIR__ . '/../../../../pagamentos/consultar_pagamento.php';
			if($status_api == 'approved'){
					$id_usuario = $_SESSION['id'];
					$id = $res[$i]['id'];
					$valor = $res[$i]['valor'];
					$forma_pgto = 'MP';
					$data_pgto = $data_atual;
					require_once __DIR__ . '/aprovar_plano.php';
				}				
		}

		//buscar a ultima conta do plano em aberto para ser excluida se o plano for cancelado
		$query2 = $pdo->query("SELECT * FROM receber where tipo = 'Assinatura' and referencia = '$id' and pago != 'Sim' order by id desc limit 1");
		$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
		$id_conta = @$res2[0]['id'];

echo <<<HTML
<tr>
<td><i class="fa fa-square {$classe_alerta}"></i> {$nome_cliente}</td>
<td class="esc">{$nome_grupo} {$texto_cancelado}</td>
<td class="esc">{$nome_item}</td>
<td class="esc">{$nome_servico_vinculado}</td>
<td class="esc">{$valorF}</td>
<td class="esc">{$dataF}</td>
<td class="esc">{$badgeStatus}</td>
<td>
	<big><a href="javascript:void(0)" onclick="editar('{$id}','{$cliente}', '{$grupo}', '{$item}', '{$valor}', '{$frequencia}', '{$vencimento}'); return false;" title="Editar Dados"><i class="fa fa-edit text-primary"></i></a></big>

	<li class="dropdown head-dpdn2" style="display: inline-block;">
	<a title="Excluir Item" href="javascript:void(0)" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><big><i class="fa fa-trash-o text-danger"></i></big></a>
	<ul class="dropdown-menu" style="margin-left:-230px;">
	  <li>
	    <div class="notification_desc2">
	      <p>Confirmar Exclusão? <a href="javascript:void(0)" onclick="excluir('{$id}'); return false;"><span class="text-danger">Sim</span></a></p>
	    </div>
	  </li>
	</ul>
	</li>

	<big><a class="{$visivel_baixar}" href="javascript:void(0)" onclick="baixar('{$id}', '{$valor}'); return false;" title="Baixar Conta"><i class="fa fa-check-square verde"></i></a></big>

	<li class="dropdown head-dpdn2" style="display: inline-block;">
	<a title="Cancelar Plano" href="javascript:void(0)" class="dropdown-toggle {$ocultar_cancelar}" data-toggle="dropdown" aria-expanded="false"><big><i class="fa fa-ban text-danger"></i></big></a>
	<ul class="dropdown-menu" style="margin-left:-230px;">
	  <li>
	    <div class="notification_desc2">
	      <p>Cancelar Plano? A última conta em aberto será excluída! <a href="javascript:void(0)" onclick="cancelar('{$id_conta}', '{$id}'); return false;"><span class="text-danger">Sim</span></a></p>
	    </div>
	  </li>
	</ul>
	</li>

  <big style="margin-left:6px">{$acaoStatus}</big>
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

<script type="text/javascript">var bbBasePainel = (window.bbBasePainel || "");

$(document).ready(function () {
  $('#tabela').DataTable({
    "ordering": false,
    "stateSave": true
  });
  $('#tabela_filter label input').focus();
});

function editar(id, cliente, grupo, item, valor, frequencia, vencimento){
  $('#id').val(id);
  $('#cliente').val(cliente).change();
  $('#grupo').val(grupo).change();	
  $('#frequencia').val(frequencia).change();	
  $('#vencimento').val(vencimento);	

  setTimeout(function(){ listarItens(); }, 400);
  setTimeout(function(){ $('#item').val(item).change(); }, 700);
  setTimeout(function(){ $('#valor').val(valor); }, 900);

  $('#titulo_inserir').text('Editar Registro');
  $('#modalForm').modal('show');
}

function limparCampos(){
  $('#id').val('');
  $('#nome').val('');
  $('#chave').val('');
  $('#frequencia').val('30').change();	
}

function baixar(id, valor){
  $('#id_baixar').val(id);
  $('#valor_baixar').val(valor);			
  $('#modalBaixar').modal('show');
}

function cancelar(id, id_plano){
  $.ajax({
    url: bbBasePainel + '/paginas/' + pag + '/cancelar.php',
    method: 'POST',
    data: {id, id_plano},
    dataType: "text",
    success: function (mensagem) {   
      if (mensagem.trim() == "Excluído com Sucesso") {                
        listar();                
      } else {
        $('#mensagem-excluir').addClass('text-danger').text(mensagem);
      }
    }
  });
}

// NOVO: ativar/desativar assinatura
function toggleAtivo(id, novo){
  $.ajax({
    url: bbBasePainel + '/paginas/' + pag + '/status.php',
    method: 'POST',
    data: {id: id, ativo: novo},
    dataType: "text",
    success: function(resp){
      if(resp.trim() === 'OK'){
        listar();
      }else{
        alert(resp);
      }
    },
    error: function(xhr){
      alert('Erro ao atualizar status: ' + (xhr.responseText || xhr.status));
    }
  });
}
</script>
