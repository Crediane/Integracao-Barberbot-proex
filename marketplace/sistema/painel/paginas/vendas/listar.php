<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
$nivel_usu = @$_SESSION['nivel'];
$id_usu    = @$_SESSION['id']; 
require_once __DIR__ . '/../../../conexao.php';

$tabela     = 'receber';
$data_hoje  = date('Y-m-d');

$dataInicial = isset($_POST['dataInicial']) ? $_POST['dataInicial'] : $data_hoje;
$dataFinal   = isset($_POST['dataFinal'])   ? $_POST['dataFinal']   : $data_hoje;
$statusRaw   = isset($_POST['status'])      ? $_POST['status']      : '';
$status      = '%'.$statusRaw.'%';

$total_pago     = 0.0;
$total_a_pagar  = 0.0;

if($nivel_usu == "Administrador"){
  $query = $pdo->query("SELECT * FROM $tabela WHERE data_venc >= '$dataInicial' AND data_venc <= '$dataFinal' AND pago LIKE '$status' AND produto != 0 ORDER BY pago ASC, data_venc ASC");
}else{
  $query = $pdo->query("SELECT * FROM $tabela WHERE data_venc >= '$dataInicial' AND data_venc <= '$dataFinal' AND pago LIKE '$status' AND produto != 0 AND usuario_lanc = '$id_usu' ORDER BY pago ASC, data_venc ASC");
}

$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);

if($total_reg > 0){

echo <<<HTML
  <small>
  <table class="table table-hover" id="tabela">
  <thead> 
  <tr> 
    <th>Produto</th>	
    <th class="esc">Valor</th> 	
    <th class="esc">Vencimento</th> 	
    <th class="esc">Data PGTO</th> 
    <th class="esc">Vendido Por</th>
    <th class="esc">Cliente</th>	
    <th class="esc">Arquivo</th>	
    <th>Ações</th>
  </tr> 
  </thead> 
  <tbody>	
HTML;

for($i=0; $i < $total_reg; $i++){
  foreach ($res[$i] as $key => $value){}
  $id            = $res[$i]['id'];	
  $descricao     = $res[$i]['descricao'];
  $tipo          = $res[$i]['tipo'];
  $valor         = (float)$res[$i]['valor'];
  $data_lanc     = $res[$i]['data_lanc'];
  $data_pgto     = $res[$i]['data_pgto'];
  $data_venc     = $res[$i]['data_venc'];
  $usuario_lanc  = $res[$i]['usuario_lanc'];
  $usuario_baixa = $res[$i]['usuario_baixa'];
  $foto          = $res[$i]['foto'] ?: 'sem-foto.jpg';
  $pessoa        = $res[$i]['pessoa'];
  $produto       = $res[$i]['produto'];
  $pago          = $res[$i]['pago'];
  $funcionario   = isset($res[$i]['funcionario']) ? $res[$i]['funcionario'] : null;
  $valor2        = isset($res[$i]['valor2']) ? (float)$res[$i]['valor2'] : 0.0;

  if($valor <= 0){
    $valor = $valor2;
  }
  
  $valorF     = number_format($valor, 2, ',', '.');
  $data_lancF = $data_lanc ? implode('/', array_reverse(explode('-', $data_lanc))) : '';
  $data_pgtoF = ($data_pgto && $data_pgto != '0000-00-00') ? implode('/', array_reverse(explode('-', $data_pgto))) : 'Pendente';
  $data_vencF = $data_venc ? implode('/', array_reverse(explode('-', $data_venc))) : '';

  // Cliente
  $query2 = $pdo->query("SELECT * FROM clientes WHERE id = '$pessoa'");
  $res2   = $query2->fetchAll(PDO::FETCH_ASSOC);
  $total_reg2 = @count($res2);
  if($total_reg2 > 0){
    $nome_pessoa     = $res2[0]['nome'];
    $telefone_pessoa = $res2[0]['telefone'];
  }else{
    $nome_pessoa     = 'Nenhum!';
    $telefone_pessoa = 'Nenhum';
  }

  // Usuário baixa (quem recebeu)
  $query2 = $pdo->query("SELECT * FROM usuarios WHERE id = '$usuario_baixa'");
  $res2   = $query2->fetchAll(PDO::FETCH_ASSOC);
  $total_reg2 = @count($res2);
  if($total_reg2 > 0){
    $nome_usuario_pgto = $res2[0]['nome'];
  }else{
    $nome_usuario_pgto = 'Nenhum!';
  }

  // Funcionário (vendedor) – comissão
  if(!empty($funcionario)){
    $query2 = $pdo->query("SELECT * FROM usuarios WHERE id = '$funcionario'");
    $res2   = $query2->fetchAll(PDO::FETCH_ASSOC);
    $total_reg2 = @count($res2);
    $nome_vendedor = $total_reg2 > 0 ? $res2[0]['nome'] : '';
  }else{
    $nome_vendedor = '';
  }

  // Usuário lançamento
  $query2 = $pdo->query("SELECT * FROM usuarios WHERE id = '$usuario_lanc'");
  $res2   = $query2->fetchAll(PDO::FETCH_ASSOC);
  $total_reg2 = @count($res2);
  if($total_reg2 > 0){
    $nome_usuario_lanc = $res2[0]['nome'];
  }else{
    $nome_usuario_lanc = 'Sem Referência!';
  }

  if($data_pgto == '0000-00-00' || empty($data_pgto)){
    $classe_alerta   = 'text-danger';
    $data_pgtoF      = 'Pendente';
    $visivel         = '';
    $total_a_pagar  += $valor;
    $japago          = 'ocultar';
  }else{
    $classe_alerta = 'verde';
    $visivel       = 'ocultar';
    $total_pago   += $valor;
    $japago        = '';
  }

  // thumb do arquivo
  $ext = strtolower(pathinfo($foto, PATHINFO_EXTENSION));
  if($ext == 'pdf'){
    $tumb_arquivo = 'pdf.png';
  }else if($ext == 'rar' || $ext == 'zip'){
    $tumb_arquivo = 'rar.png';
  }else{
    $tumb_arquivo = $foto;
  }

  // vencido e não pago
  if($data_venc && $data_venc < $data_hoje && $pago != 'Sim'){
    $classe_debito = 'vermelho-escuro';
  }else{
    $classe_debito = '';
  }

echo <<<HTML
<tr class="{$classe_debito}">
  <td><i class="fa fa-square {$classe_alerta}"></i> {$descricao}</td>
  <td class="esc">R$ {$valorF}</td>
  <td class="esc">{$data_vencF}</td>
  <td class="esc">{$data_pgtoF}</td>
  <td class="esc">{$nome_vendedor}</td>
  <td class="esc">{$nome_pessoa}</td>
  <td><a href="img/contas/{$foto}" target="_blank"><img src="img/contas/{$tumb_arquivo}" width="27px" class="mr-2"></a></td>
  <td>
    <big><a href="javascript:void(0)" onclick="mostrar('{$descricao}', '{$valorF}', '{$data_lancF}', '{$data_vencF}',  '{$data_pgtoF}', '{$nome_usuario_lanc}', '{$nome_usuario_pgto}', '{$tumb_arquivo}', '{$nome_pessoa}', '{$foto}', '{$telefone_pessoa}'); return false;" title="Ver Dados"><i class="fa fa-info-circle text-secondary"></i></a></big>

    <li class="dropdown head-dpdn2" style="display: inline-block;">
      <a href="javascript:void(0)" class="dropdown-toggle" data-toggle="dropdown" aria-expanded="false"><big><i class="fa fa-trash-o text-danger"></i></big></a>
      <ul class="dropdown-menu" style="margin-left:-230px;">
        <li>
          <div class="notification_desc2">
            <p>Confirmar Exclusão? <a href="javascript:void(0)" onclick="excluir('{$id}'); return false;"><span class="text-danger">Sim</span></a></p>
          </div>
        </li>										
      </ul>
    </li>

    <li class="dropdown head-dpdn2" style="display: inline-block;">
      <a title="Baixar Conta" href="javascript:void(0)" class="dropdown-toggle {$visivel}" data-toggle="dropdown" aria-expanded="false"><big><i class="fa fa-check-square verde"></i></big></a>
      <ul class="dropdown-menu" style="margin-left:-230px;">
        <li>
          <div class="notification_desc2">
            <p>Confirmar Baixa na Conta? <a href="javascript:void(0)" onclick="baixar('{$id}'); return false;"><span class="verde">Sim</span></a></p>
          </div>
        </li>										
      </ul>
    </li>

    <big><a class="{$japago}" href="javascript:void(0)" onclick="gerarComprovante('{$id}'); return false;" title="Gerar Comprovante"><i class="fa fa-file-pdf-o text-primary"></i></a></big>
  </td>
</tr>
HTML;

}

$total_pagoF     = number_format($total_pago, 2, ',', '.');
$total_a_pagarF  = number_format($total_a_pagar, 2, ',', '.');

echo <<<HTML
  </tbody>
  <small><div align="center" id="mensagem-excluir"></div></small>
  </table>

  <br>	
  <div align="right">Total Recebido: <span class="verde">R$ {$total_pagoF}</span> </div>
  <div align="right">Total à Receber: <span class="text-danger">R$ {$total_a_pagarF}</span> </div>
  </small>
HTML;

}else{
  echo '<small>Não possui nenhum registro Cadastrado!</small>';
}
?>

<script type="text/javascript">
$(document).ready(function () {
  $('#tabela').DataTable({
    "ordering": false,
    "stateSave": true
  });
  $('#tabela_filter label input').focus();
});
</script>

<script type="text/javascript">
function editar(id, produto, pessoa, valor, data_venc, data_pgto, foto){
  $('#id').val(id);
  $('#produto').val(produto).change();
  $('#pessoa').val(pessoa).change();
  $('#valor').val(valor);
  mascara_valor('valor');
  $('#data_venc').val(data_venc);
  $('#data_pgto').val(data_pgto);
  $('#titulo_inserir').text('Editar Registro');
  $('#modalForm').modal('show');
  $('#target').attr('src','img/contas/' + foto);
}

function limparCampos(){
  $('#id').val('');
  $('#pessoa').val(0).change();
  $('#valor').val('');
  mascara_valor('valor');
  $('#data_pgto').val('');
  $('#data_venc').val('<?=$data_hoje?>');		
  $('#foto').val('');
  $('#quantidade').val('1');
  $('#target').attr('src','img/contas/sem-foto.jpg');
  calcular()
}
</script>

<script type="text/javascript">
function mostrar(descricao, valor, data_lanc, data_venc, data_pgto, usuario_lanc, usuario_pgto, foto, pessoa, link, telefone){
  $('#nome_dados').text(descricao);
  $('#valor_dados').text(valor);
  mascara_valor('valor_dados');
  $('#data_lanc_dados').text(data_lanc);
  $('#data_venc_dados').text(data_venc);
  $('#data_pgto_dados').text(data_pgto);
  $('#usuario_lanc_dados').text(usuario_lanc);
  $('#usuario_baixa_dados').text(usuario_pgto);
  $('#pessoa_dados').text(pessoa);
  $('#telefone_dados').text(telefone);
  $('#link_mostrar').attr('href','img/contas/' + link);
  $('#target_mostrar').attr('src','img/contas/' + foto);
  $('#modalDados').modal('show');
}
</script>

<script type="text/javascript">
function saida(id, nome, estoque){
  $('#nome_saida').text(nome);
  $('#estoque_saida').val(estoque);
  $('#id_saida').val(id);		
  $('#modalSaida').modal('show');
}
</script>

<script type="text/javascript">
function entrada(id, nome, estoque){
  $('#nome_entrada').text(nome);
  $('#estoque_entrada').val(estoque);
  $('#id_entrada').val(id);		
  $('#modalEntrada').modal('show');
}
</script>

<script type="text/javascript">
function gerarComprovante(id){
  let a = document.createElement('a');
  a.target = '_blank';
  a.href   = 'rel/recibo_class.php?id=' + id;
  a.click();
}
</script>
