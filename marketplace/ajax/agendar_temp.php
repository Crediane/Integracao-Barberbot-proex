<?php
$bb_ajax_context_mode = 'tenant';
$bb_ajax_context_response = 'plain';
require_once __DIR__ . '/_context_bootstrap.php';

$telefone    = isset($_POST['telefone'])    ? trim(strip_tags($_POST['telefone']))    : '';
$nome        = isset($_POST['nome'])        ? trim(strip_tags($_POST['nome']))        : '';
$funcionario = isset($_POST['funcionario']) ? trim(strip_tags($_POST['funcionario'])) : '';
$hora        = isset($_POST['hora'])        ? trim(strip_tags($_POST['hora']))        : '';
$servico     = isset($_POST['servico'])     ? trim(strip_tags($_POST['servico']))     : '';
$obs         = isset($_POST['obs'])         ? trim(strip_tags($_POST['obs']))         : '';
$data        = isset($_POST['data'])        ? trim(strip_tags($_POST['data']))        : '';

// Mantém a mesma lógica: copiar data/hora para variáveis auxiliares
$data_agd    = $data;
$hora_do_agd = $hora;

// Mesmo papel do FILTER_SANITIZE_STRING anterior
$id_edit = isset($_POST['id_edit']) ? trim(strip_tags($_POST['id_edit'])) : '';

$data_hoje_tenant = date('Y-m-d');

// --- FIM: leitura dos POST ---

/// EXCLUI AGENDAMENTO ANTIGO QUANDO ENTRAR EM MODO DE EDIÇÃO
if (!empty($id_edit)) {
    $stmt = $pdo->prepare("DELETE FROM agendamentos WHERE id = :id");
    $stmt->bindValue(':id', $id_edit, PDO::PARAM_INT);
    $stmt->execute();

    $stmt2 = $pdo->prepare("DELETE FROM horarios_agd WHERE agendamento = :id");
    $stmt2->bindValue(':id', $id_edit, PDO::PARAM_INT);
    $stmt2->execute();
}

$hash = "";
if($telefone == $whatsapp_sistema){
    echo '<div class="alert-error">
            <i class="fas fa-exclamation-circle"></i> 
            Por favor, informe um número de telefone válido para concluir o agendamento.
          </div>';
    exit();
}

 $tel_cli = $_POST['telefone'] ?? '';
 if (trim((string)$tel_cli) === '') {
     echo '<div class="alert-error"><i class="fas fa-exclamation-circle"></i> Por favor, informe um telefone para concluir o agendamento.</div>';
     exit();
 }

$query = $pdo->prepare("SELECT * FROM usuarios where id = :funcionario");
$query->bindValue(":funcionario", "$funcionario");
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$intervalo = $res[0]['intervalo'];

$query = $pdo->prepare("SELECT * FROM servicos where id = :servico");
$query->bindValue(":servico", "$servico");
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$tempo = $res[0]['tempo'];

$hora_minutos = @strtotime("+$tempo minutes", @strtotime($hora));			
$hora_final_servico = date('H:i:s', $hora_minutos);

$nova_hora = $hora;

$diasemana = array("Domingo", "Segunda-Feira", "Terça-Feira", "Quarta-Feira", "Quinta-Feira", "Sexta-Feira", "Sabado");
$diasemana_numero = date('w', @strtotime($data));
$dia_procurado = $diasemana[$diasemana_numero];

//percorrer os dias da semana que ele trabalha
$query = $pdo->prepare("SELECT * FROM dias where funcionario = :funcionario and dia = '$dia_procurado'");
$query->bindValue(":funcionario", "$funcionario");
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);
if(@count($res) == 0){
    echo '⚠️ Este profissional não estará disponível nesta data. Escolha uma nova data ou selecione outro profissional!';
    exit();
}
else
{
    $inicio = $res[0]['inicio'];
    $final = $res[0]['final'];
    $inicio_almoco = $res[0]['inicio_almoco'];
    $final_almoco = $res[0]['final_almoco'];
}

while (@strtotime($nova_hora) < @strtotime($hora_final_servico)){
	
    $hora_minutos = @strtotime("+$intervalo minutes", @strtotime($nova_hora));			
    $nova_hora = date('H:i:s', $hora_minutos);		
    
    //VERIFICAR NA TABELA HORARIOS AGD SE TEM O HORARIO NESSA DATA
    $query_agd = $pdo->prepare("SELECT * FROM horarios_agd where data = :data and funcionario = :funcionario and horario = '$nova_hora'");
    $query_agd->bindValue(":funcionario", "$funcionario");
    $query_agd->bindValue(":data", "$data");
    $query_agd->execute();
    $res_agd = $query_agd->fetchAll(PDO::FETCH_ASSOC);
    if(@count($res_agd) > 0){
        echo 'Este serviço demora cerca de '.$tempo.' minutos, precisa escolher outro horário, pois neste horários não temos disponibilidade devido a outros agendamentos!';
        exit();
    }

    //VERIFICAR NA TABELA AGENDAMENTOS SE TEM O HORARIO NESSA DATA e se tem um intervalo entre o horario marcado e o proximo agendado nessa tabela
    $query_agd = $pdo->prepare("SELECT * FROM agendamentos where data = :data and funcionario = :funcionario and hora = '$nova_hora'");
    $query_agd->bindValue(":funcionario", "$funcionario");
    $query_agd->bindValue(":data", "$data");
    $query_agd->execute();
    $res_agd = $query_agd->fetchAll(PDO::FETCH_ASSOC);
    if(@count($res_agd) > 0){
        if($tempo <= $intervalo){

        }else{
            if($hora_final_servico == $res_agd[0]['hora']){
                
            }else{
                echo 'Este serviço demora cerca de '.$tempo.' minutos, precisa escolher outro horário, pois neste horários não temos disponibilidade devido a outros agendamentos!';
                exit();
            }
            
        }
        
    }


    if(@strtotime($nova_hora) > @strtotime($inicio_almoco) and @strtotime($nova_hora) < @strtotime($final_almoco)){
        echo 'Este serviço demora cerca de '.$tempo.' minutos, precisa escolher outro horário, pois neste horários não temos disponibilidade devido ao horário de almoço!';
        exit();
    }

}



@$_SESSION['telefone'] = $telefone;

if($hora == ""){
    echo 'Escolha um Horário para Agendar!';
    exit();
}

if($data < $data_hoje_tenant){
    echo 'Escolha uma data igual ou maior que Hoje!';
    exit();
}

//validar horario
$query = $pdo->prepare("SELECT * FROM agendamentos where data = :data and hora = :hora and funcionario = :funcionario");
$query->bindValue(":funcionario", "$funcionario");
$query->bindValue(":data", "$data");
$query->bindValue(":hora", "$hora");
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);
if($total_reg > 0 and $res[0]['id'] != $id_edit){
    echo 'Este horário não está disponível!';
    exit();
}

//Cadastrar o cliente caso não tenha cadastro
$senha = '123';
$senha_crip = password_hash($senha, PASSWORD_DEFAULT);
$query = $pdo->prepare("SELECT * FROM clientes where telefone LIKE :telefone ");
$query->bindValue(":telefone", "$telefone");
$query->execute();
$res = $query->fetchAll(PDO::FETCH_ASSOC);
if(@count($res) == 0){
    $query = $pdo->prepare("INSERT INTO clientes SET nome = :nome, telefone = :telefone, data_cad = :data_cad, cartoes = '0', alertado = 'Não', senha_crip = '$senha_crip'");

    $query->bindValue(":nome", "$nome");
    $query->bindValue(":telefone", "$telefone");
    $query->bindValue(":data_cad", $data_hoje_tenant);
    $query->execute();
    $id_cliente = $pdo->lastInsertId();

    // ENVIO DO WHATSAPP SOMENTE PARA NOVO CLIENTE
    $telefone = '55' . preg_replace('/[ ()-]+/', '', $telefone);

    // Montar mensagem
    $mensagem = "👋 *Olá {$nome}*, seja bem-vindo(a) ao *{$nome_sistema}*!\n\n";
    $mensagem .= "🔒 *Use seu whatsapp e senha de acesso:* 123\n\n";
    $mensagem .= "🌐 *Clique abaixo para acessar seu painel:*\n";

    // Link do painel da barbearia confirmada pelo bootstrap tenant
    $base_url = '';
    if (!empty($url_sistema)) {
        $base_url = rtrim((string)$url_sistema, '/') . '/';
    } else {
        $proto = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host  = $_SERVER['HTTP_HOST'] ?? '';
        $base_url = rtrim($proto . '://' . $host, '/') . '/';
    }

    $slug_loja = (string)$bb_ajax_context['slug'];
    $link_painel = $base_url . 'loja/' . rawurlencode($slug_loja) . '/sistema/acesso';

    $mensagem .= $link_painel;

    // Agora chama o arquivo de envio
    // Envio WhatsApp - caminho real do sistema
    require_once $_SERVER['DOCUMENT_ROOT'] . '/pagamentos/api-texto.php';

}else{
    $id_cliente = $res[0]['id'];

    //verificar se o cliente tem débito
    $query22 = $pdo->prepare("SELECT * FROM receber where pessoa = :id_cliente and data_venc < :data_hoje and pago = 'Não'");
    $query22->bindValue(":id_cliente", $id_cliente, PDO::PARAM_INT);
    $query22->bindValue(":data_hoje", $data_hoje_tenant);
    $query22->execute();
    $res22 = $query22->fetchAll(PDO::FETCH_ASSOC);
    $total_debitos = @count($res22);
    if($total_debitos > 0){
        echo 'Você possui conta em aberto, efetue o pagamento antes de agendar!';
        exit();
    }
}

//excluir agendamentos temporarios deste cliente
$pdo->query("DELETE FROM agendamentos_temp where cliente = '$id_cliente'");

//marcar o agendamento
$query = $pdo->prepare("INSERT INTO agendamentos_temp SET funcionario = :funcionario, cliente = '$id_cliente', hora = :hora, data = :data_agd, usuario = '0', status = 'Agendado', obs = :obs, data_lanc = :data_lanc, servico = :servico, hash = '$hash'");

$query->bindValue(":funcionario", "$funcionario");
$query->bindValue(":hora", "$hora");	
$query->bindValue(":servico", "$servico");
$query->bindValue(":data_agd", "$data_agd");
$query->bindValue(":data_lanc", $data_hoje_tenant);
$query->bindValue(":obs", "$obs");
$query->execute();

$ult_id = $pdo->lastInsertId();
echo 'Pré Agendado*'.$ult_id;

?>
