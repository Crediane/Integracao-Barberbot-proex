<?php
require_once __DIR__ . '/../_context_bootstrap.php';
// Arquivo: /public_html/sistema/painel/paginas/agenda/listar-horarios.php
// Objetivo: Renderizar horários disponíveis (layout em colunas) SEM mudar regras de negócio.
// Importante: NÃO iniciar/trocar sessão aqui. O /sistema/conexao.php já faz o bootstrap correto (MASTER x TENANT).

@chdir(__DIR__);
require_once __DIR__ . '/../../../conexao.php';

// -----------------------------------------------------------------------------
// Segurança básica: precisa estar logado
// -----------------------------------------------------------------------------
if (empty($_SESSION['id'])) {
  http_response_code(401);
  echo 'Acesso negado.';
  exit();
}

$funcionario = (int)$_SESSION['id'];
$data = isset($_POST['data']) ? (string)$_POST['data'] : '';

// valida formato esperado do input[type=date] (YYYY-MM-DD)
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
  http_response_code(400);
  echo 'Data inválida.';
  exit();
}

$hora_atual = date('H:i:s');
$hoje = date('Y-m-d');

// -----------------------------------------------------------------------------
// Regras existentes (bloqueios / dias de trabalho / intervalo)
// -----------------------------------------------------------------------------

// verificar se possui essa data nos dias bloqueio geral
$stmt = $pdo->prepare("SELECT 1 FROM dias_bloqueio WHERE funcionario = 0 AND data = :data LIMIT 1");
$stmt->execute([':data' => $data]);
if ($stmt->fetchColumn()) {
  echo 'Não estaremos funcionando nesta Data!';
  exit();
}

// verificar se possui essa data nos dias bloqueio do funcionário
$stmt = $pdo->prepare("SELECT 1 FROM dias_bloqueio WHERE funcionario = :funcionario AND data = :data LIMIT 1");
$stmt->execute([':funcionario' => $funcionario, ':data' => $data]);
if ($stmt->fetchColumn()) {
  echo 'Este Profissional não irá trabalhar nesta Data, selecione outra data ou escolhar outro Profissional!';
  exit();
}

$diasemana = array("Domingo", "Segunda-Feira", "Terça-Feira", "Quarta-Feira", "Quinta-Feira", "Sexta-Feira", "Sabado");
$diasemana_numero = date('w', strtotime($data));
$dia_procurado = $diasemana[$diasemana_numero] ?? '';

// percorrer os dias da semana que ele trabalha
$stmt = $pdo->prepare("SELECT inicio, final, inicio_almoco, final_almoco FROM dias WHERE funcionario = :funcionario AND dia = :dia LIMIT 1");
$stmt->execute([':funcionario' => $funcionario, ':dia' => $dia_procurado]);
$resDia = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$resDia) {
  echo 'Este Funcionário não trabalha neste Dia!';
  exit();
}

$inicio = $resDia['inicio'];
$final = $resDia['final'];
$inicio_almoco = $resDia['inicio_almoco'];
$final_almoco = $resDia['final_almoco'];

$stmt = $pdo->prepare("SELECT intervalo FROM usuarios WHERE id = :id LIMIT 1");
$stmt->execute([':id' => $funcionario]);
$resUser = $stmt->fetch(PDO::FETCH_ASSOC);
$intervalo = isset($resUser['intervalo']) ? (int)$resUser['intervalo'] : 0;

if ($intervalo <= 0) {
  // mantém comportamento do sistema (se não tiver intervalo, evita loop infinito)
  echo 'Configuração de intervalo inválida para este profissional.';
  exit();
}

// -----------------------------------------------------------------------------
// Otimização segura (sem mudar lógica): carrega agendados e bloqueados uma vez
// -----------------------------------------------------------------------------
$horarios_agendados = [];
$stmt = $pdo->prepare("SELECT hora FROM agendamentos WHERE data = :data AND funcionario = :funcionario");
$stmt->execute([':data' => $data, ':funcionario' => $funcionario]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
  if (!empty($r['hora'])) {
    $horarios_agendados[(string)$r['hora']] = true;
  }
}

$horarios_bloqueados = [];
$stmt = $pdo->prepare("SELECT horario FROM horarios_agd WHERE data = :data AND funcionario = :funcionario");
$stmt->execute([':data' => $data, ':funcionario' => $funcionario]);
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $r) {
  if (!empty($r['horario'])) {
    $horarios_bloqueados[(string)$r['horario']] = true;
  }
}

?>
<div class="row">
<?php
$i_disponiveis = 0;
$contador_uid = 0;

while (strtotime($inicio) <= strtotime($final)) {

  // pula horário de almoço
  if (strtotime($inicio) >= strtotime($inicio_almoco) && strtotime($inicio) < strtotime($final_almoco)) {
    $hora_minutos = strtotime("+$intervalo minutes", strtotime($inicio));
    $inicio = date('H:i:s', $hora_minutos);
    continue;
  }

  $hora = $inicio;
  $horaF = date("H:i", strtotime($hora));

  // regras existentes de bloqueio/ocultação
  $esconder = '';
  $checado = '';

  if (isset($horarios_agendados[$hora])) {
    $esconder = 'text-danger';
    $checado = 'disabled';
  }

  $esconder2 = '';
  $checado2 = '';
  $ocultar = '';

  if (strtotime($hora) < strtotime($hora_atual) && strtotime($data) == strtotime($hoje)) {
    $esconder2 = 'text-danger';
    $checado2 = 'disabled';
    $ocultar = 'ocultar';
  } else {
    $i_disponiveis += 1;
  }

  $esconder3 = '';
  $checado3 = '';
  if (isset($horarios_bloqueados[$hora])) {
    $esconder3 = 'text-danger';
    $checado3 = 'disabled';
  }

  // ID único para o label funcionar (melhora UX sem alterar regra)
  $contador_uid++;
  $uid = 'hora_' . $funcionario . '_' . str_replace([':', '-'], '', $hora) . '_' . str_replace('-', '', $data) . '_' . $contador_uid;
  $uid = preg_replace('/[^a-zA-Z0-9_]/', '', $uid);
  ?>
  <div class="col-xs-6 col-sm-4 col-md-3 col-lg-2 col-6 <?php echo $ocultar; ?>" style="margin-bottom:10px;">
    <div class="form-check" style="padding:6px 10px; border:1px solid rgba(0,0,0,.08); border-radius:10px;">
      <input class="form-check-input" type="radio" name="hora" id="<?php echo $uid; ?>" value="<?php echo $hora; ?>" <?php echo $checado; ?> <?php echo $checado2; ?> <?php echo $checado3; ?>>
      <label class="form-check-label <?php echo $esconder; ?> <?php echo $esconder2; ?> <?php echo $esconder3; ?>" for="<?php echo $uid; ?>" style="cursor:pointer; font-weight:600;">
        <?php echo $horaF; ?>
      </label>
    </div>
  </div>
  <?php

  $hora_minutos = strtotime("+$intervalo minutes", strtotime($inicio));
  $inicio = date('H:i:s', $hora_minutos);
}

if ($i_disponiveis == 0) {
  echo 'Não temos mais horários disponíveis com este funcionário para essa data!';
}
?>
</div>