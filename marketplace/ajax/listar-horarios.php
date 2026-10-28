<?php
$bb_ajax_context_mode = 'tenant';
$bb_ajax_context_response = 'plain';
require_once __DIR__ . '/_context_bootstrap.php';

$usuario = (int)($_SESSION['BARBER_LOJA']['id'] ?? ($_SESSION['BARBER_ADMIN']['id'] ?? 0));
$funcionario = @$_POST['funcionario'];
$data = @$_POST['data'];
$hora_rec = @$_POST['hora'];
$servico = @$_POST['servico'];
$hoje = date('Y-m-d');
$hora_atual = date('H:i:s');
if(@strtotime($data) < @strtotime($hoje)){
	echo '000';
	exit();
}
if($funcionario == ""){
	exit();
}

//BLOQUEIO DE DIA DO FUNCIONÁRIO
if($funcionario == 20){
    $query = $pdo->prepare("SELECT * FROM dias_bloqueio WHERE (usuario = :u OR usuario = '24') AND data = :d");
    $query->bindValue(":u", $funcionario);
    $query->bindValue(":d", $data);
    $query->execute();
    $res = $query->fetchAll(PDO::FETCH_ASSOC);
    if(@count($res) > 0){
    		echo '⚠️ Atenção! Este profissional não está disponível para agendamentos na data selecionada. Por favor, escolha outra data ou selecione outro profissional disponível.';
    		exit();
    }
} else {
    $query = $pdo->prepare("SELECT * FROM dias_bloqueio WHERE usuario = :u AND data = :d");
    $query->bindValue(":u", $funcionario);
    $query->bindValue(":d", $data);
    $query->execute();
    $res = $query->fetchAll(PDO::FETCH_ASSOC);
    if(@count($res) > 0){
    		echo '⚠️ Atenção! Este profissional não está disponível para agendamentos na data selecionada. Por favor, escolha outra data ou selecione outro profissional disponível.';
    		exit();
    }
}

$diasemana = array("Domingo", "Segunda-Feira", "Terça-Feira", "Quarta-Feira", "Quinta-Feira", "Sexta-Feira", "Sabado");
$diasemana_numero = date('w', @strtotime($data));
$dia_procurado = $diasemana[$diasemana_numero];

//percorrer os dias da semana que ele trabalha
$query = $pdo->prepare("SELECT * FROM dias WHERE funcionario = :f AND dia = :dia");
$res = [];
try{
    $query->bindValue(":f", $funcionario);
    $query->bindValue(":dia", $dia_procurado);
    $query->execute();
    $res = $query->fetchAll(PDO::FETCH_ASSOC);
}catch(Throwable $e){
    $res = [];
}
if(@count($res) == 0){
		echo 'Este Funcionário não trabalha neste Dia!';
	exit();
}else{
	$inicio = $res[0]['inicio'];
	$final = $res[0]['final'];
	$inicio_almoco = $res[0]['inicio_almoco'];
	$final_almoco = $res[0]['final_almoco'];
}

$query = $pdo->prepare("SELECT * FROM usuarios WHERE id = :id");
$query->bindValue(":id", $funcionario);
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$intervalo = $res[0]['intervalo'];
// <<< CORREÇÃO (INÍCIO) >>>
// Para evitar consultas dentro do loop (N+1), vamos carregar os tempos de todos os serviços uma vez.
// Isso otimiza sua verificação e não muda a lógica.
$query_tempos = $pdo->query("SELECT id, tempo FROM servicos");
$res_tempos = $query_tempos->fetchAll(PDO::FETCH_ASSOC);
$mapa_tempos_servicos = array();
foreach ($res_tempos as $serv) {
    $mapa_tempos_servicos[$serv['id']] = $serv['tempo'];
}
// <<< CORREÇÃO (FIM) >>>
?>
<div class="row">
	<?php 
	$i = 0;
	while (@strtotime($inicio) < @strtotime($final)){
	
	if(@strtotime($inicio) >= @strtotime($inicio_almoco) and @strtotime($inicio) < @strtotime($final_almoco)){
		$hora_minutos = @strtotime("+$intervalo minutes", @strtotime($inicio));			
		$inicio = date('H:i:s', $hora_minutos);
	}else{
			
				$hora = $inicio;
				$horaF = date("H:i", @strtotime($hora));
				$dataH = '';

				//validar horario
$query2 = $pdo->prepare("SELECT * FROM agendamentos WHERE data = :data AND hora = :hora AND funcionario = :func");
$query2->bindValue(":data", $data);
$query2->bindValue(":hora", $hora);
$query2->bindValue(":func", $funcionario);
$query2->execute();
$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);

$total_reg2 = @count($res2);
if($total_reg2 == 0 || @strtotime($hora_rec) == @strtotime($hora)){
	$hora_agendada = '';
	$texto_hora = '';

	if(@strtotime($hora_rec) == @strtotime($hora)){
		$checado = 'checked';
	}else{
		$checado = '';
	}

	if(@strtotime($hora) < @strtotime($hora_atual) and @strtotime($data) == @strtotime($hoje)){
		$esconder = 'none';
	}else{
		$esconder = '';
				
//VERIFICAR NA TABELA HORARIOS AGD SE TEM O HORARIO NESSA DATA
		$query_agd = $pdo->prepare("SELECT * FROM horarios_agd WHERE data = :data AND funcionario = :func AND horario = :hor");
$query_agd->bindValue(":data", $data);
$query_agd->bindValue(":func", $funcionario);
$query_agd->bindValue(":hor", $hora);
$query_agd->execute();
$res_agd = $query_agd->fetchAll(PDO::FETCH_ASSOC);
		if(@count($res_agd) > 0){
			$esconder = 'none';			
		}else{
			$esconder = '';
			$i += 1;
		}
	}
//CODIGO PARA RECUPERAR TEMPO DO SERVIÇO PARA VER SE CABERÁ O SERVIÇO NO INTERVALO
$query = $pdo->prepare("SELECT * FROM servicos where id = :servico");
$query->bindValue(":servico", "$servico");
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$tempo = $res[0]['tempo']; // Tempo do serviço que o cliente está TENTANDO agendar

//CODIGO PARA NÃO MOSTRAR O HORÁRIO SE TIVER AGENDAMENTO QUE NÃO CAIBA O SERVIÇO
	// Hora inicial do serviço tentado
$hora_inicio_tentada = strtotime($hora);
$hora_final_tentada = strtotime("+$tempo minutes", $hora_inicio_tentada); // Hora final do serviço TENTADO

$hora_minutos = @strtotime("+$tempo minutes", @strtotime($hora));			
$nova_hora = date('H:i:s', $hora_minutos);		

// Buscar todos os agendamentos no dia e funcionário
// <<< CORREÇÃO >>> Precisamos do ID do serviço agendado (assumindo que a coluna se chama 'servico')
$query_agd = $pdo->prepare("SELECT * FROM agendamentos WHERE data = :data AND funcionario = :funcionario"); 
$query_agd->bindValue(":funcionario", "$funcionario");
$query_agd->bindValue(":data", "$data");
$query_agd->execute();
$res_agd = $query_agd->fetchAll(PDO::FETCH_ASSOC);

foreach($res_agd as $agendamento) {
    $hora_agendada = strtotime($agendamento['hora']); // Hora de início do agendamento JÁ EXISTENTE
    
    // <<< CORREÇÃO (INÍCIO) >>>
    // Aqui estava o BUG. Você usava '$tempo' (do serviço novo)
    // Precisamos usar o tempo do serviço que JÁ ESTÁ AGENDADO
    
    // 1. Pegar o ID do serviço que já está agendado
    // !!! IMPORTANTE: Estou assumindo que a coluna na tabela 'agendamentos' se chama 'servico'
    $id_servico_agendado = $agendamento['servico']; 

    // 2. Buscar o tempo desse serviço no mapa que criamos lá em cima
    $tempo_agendado = $mapa_tempos_servicos[$id_servico_agendado] ?? $intervalo; // Se não achar, usa o intervalo por segurança
    
    // 3. Calcular a hora final do agendamento EXISTENTE com seu tempo CORRETO
    // $hora_fim_agendada = strtotime("+$tempo minutes", $hora_agendada); // LINHA ANTIGA (COM BUG)
    $hora_fim_agendada = strtotime("+$tempo_agendado minutes", $hora_agendada); // LINHA CORRIGIDA
    // <<< CORREÇÃO (FIM) >>>

    // Verificar se há sobreposição (Lógica de colisão)
    // O novo serviço (tentado) começa antes do fim do agendado?
    // E o novo serviço (tentado) termina depois do início do agendado?
    if (
        ($hora_inicio_tentada < $hora_fim_agendada) && 
        ($hora_final_tentada > $hora_agendada)
    ) {
        // Há conflito de horário, esconder essa opção
        $esconder = 'none';
        break; // Já achou conflito, não precisa checar outros agendamentos
    }
}

//verificacao almoço para não aparecer horarios
if(@strtotime($nova_hora) > @strtotime($inicio_almoco) and @strtotime($nova_hora) < @strtotime($final_almoco)){
		$esconder = 'none';        
}
	if(@strtotime($dataH) != @strtotime($data) and $dataH != "" and $dataH != "null"){
		continue;
	}	
				?>
					<div class="col-3" style='display: <?php echo $esconder ?>'>
					<div class="form-check">
					  <input onchange="$('#hora_rec').val('<?php echo $hora ?>');" class="form-check-input" type="radio" name="hora" value="<?php echo $hora ?>" <?php echo $hora_agendada ?> style="width:17px; height: 17px; " required <?php echo $checado ?>>
					  <label class="form-check-label <?php echo $texto_hora ?> text-white" for="flexRadioDefault1">
					    <?php echo $horaF ?>
					  </label>
					</div>
				</div> 
				<?php 
		}

		$hora_minutos = @strtotime("+$intervalo minutes", @strtotime($inicio));			
$inicio = date('H:i:s', $hora_minutos);
	}
}
	if($i == 0){
		echo '<div align="center"> <small>Não temos mais horários disponíveis com este funcionário para essa data!</small></div>';
	}
	?>
</div>
