<?php 
require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../../conexao.php';
/* ========= Parâmetros opcionais ========= */
$id             = $_POST['id']            ?? '';    // id do cliente (obrigatório)
$receber_id     = $_POST['receber_id']    ?? null;  // id em 'receber' (opcional)
$servico_post   = trim($_POST['servico_nome'] ?? ''); // alternativa manual
$valor_post     = $_POST['valor']         ?? null;  // alternativa manual (número)

/* ========= Data em PT-BR robusta ========= */
require_once __DIR__ . '/../../../timezone_tenant.php';

function dataExtensoPTBRUpper($date = 'today'){
  $tz = new DateTimeZone(bb_resolver_timezone_tenant_atual());
  $dt = new DateTime($date, $tz);
  if (class_exists('IntlDateFormatter')) {
    $fmt = new IntlDateFormatter('pt_BR', IntlDateFormatter::FULL, IntlDateFormatter::NONE, $tz->getName(), IntlDateFormatter::GREGORIAN, "EEEE, dd 'de' MMMM 'de' yyyy");
    $txt = $fmt->format($dt);
  } else {
    $dias  = ['domingo','segunda-feira','terça-feira','quarta-feira','quinta-feira','sexta-feira','sábado'];
    $meses = ['janeiro','fevereiro','março','abril','maio','junho','julho','agosto','setembro','outubro','novembro','dezembro'];
    $w = (int)$dt->format('w'); $m = (int)$dt->format('n');
    $txt = $dias[$w].", ".$dt->format('d')." de ".$meses[$m-1]." de ".$dt->format('Y');
  }
  return mb_strtoupper($txt, 'UTF-8');
}
function esc($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function moedaBR($v){ return 'R$ '.number_format((float)$v, 2, ',', '.'); }
function nivelEhProfissional($nivel){ return strpos(mb_strtolower((string)$nivel), 'profissional') === 0; }

$data_extenso = dataExtensoPTBRUpper('today');

/* ========= Cliente ========= */
$cli = $pdo->prepare("SELECT * FROM clientes WHERE id = :id LIMIT 1");
$cli->execute([':id'=>$id]);
$c = $cli->fetch(PDO::FETCH_ASSOC);

$nome = $cpf = $data_nasc = $data_cad = $telefone = $endereco = $cartoes = $data_retorno = $ultimo_servico = '';
if ($c) {
  $nome           = $c['nome'] ?? '';
  $data_nasc      = $c['data_nasc'] ?? '';
  $data_cad       = $c['data_cad'] ?? '';
  $telefone       = $c['telefone'] ?? '';
  $endereco       = $c['endereco'] ?? '';
  $cartoes        = $c['cartoes'] ?? '';
  $data_retorno   = $c['data_retorno'] ?? '';
  $ultimo_servico = $c['ultimo_servico'] ?? '';
  $cpf            = $c['cpf'] ?? '';
}

$cidade_data   = mb_strtoupper($cidade_sistema, 'UTF-8') . ' – ' . $data_extenso;
$nome_sistemaF = mb_strtoupper($nome_sistema);
$nomeF         = mb_strtoupper($nome);

/* ========= Serviço e Preço (dinâmicos) =========
   Prioridade:
   1) receber_id específico
   2) último lançamento do cliente em 'receber' (tipo Serviço/Servico)
   3) valores passados por POST (servico_nome/valor)
   4) fallback
*/
$servico_nome = '';
$valor_num    = null;
$prof_id      = null;

// 1) receber_id
if (!empty($receber_id)) {
  $st = $pdo->prepare("SELECT descricao, valor, usuario_lanc FROM receber WHERE id = :id LIMIT 1");
  $st->execute([':id'=>$receber_id]);
  if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    $servico_nome = trim($r['descricao'] ?? '');
    $valor_num    = is_numeric($r['valor'] ?? null) ? (float)$r['valor'] : null;
    $prof_id      = !empty($r['usuario_lanc']) ? (int)$r['usuario_lanc'] : null;
  }
}

// 2) último lançamento do cliente
if ($valor_num === null || $servico_nome === '' || $prof_id === null) {
  $st = $pdo->prepare("
    SELECT descricao, valor, usuario_lanc 
      FROM receber 
     WHERE pessoa = :pessoa 
       AND (tipo = 'Serviço' OR tipo = 'Servico' OR tipo IS NULL)
  ORDER BY data_lanc DESC, id DESC 
     LIMIT 1
  ");
  $st->execute([':pessoa'=>$id]);
  if ($r = $st->fetch(PDO::FETCH_ASSOC)) {
    if ($servico_nome === '') $servico_nome = trim($r['descricao'] ?? '');
    if ($valor_num === null && is_numeric($r['valor'] ?? null)) $valor_num = (float)$r['valor'];
    if ($prof_id === null && !empty($r['usuario_lanc'])) $prof_id = (int)$r['usuario_lanc'];
  }
}

// 3) valores postados manualmente
if ($servico_nome === '' && $servico_post !== '') $servico_nome = $servico_post;
if ($valor_num === null && is_numeric($valor_post)) $valor_num = (float)$valor_post;

// 4) fallback
if ($servico_nome === '') $servico_nome = 'Serviço de beleza (conforme OS/agendamento)';
if ($valor_num === null) $valor_num = 500.00;

$servicoF = esc($servico_nome);
$valorF   = moedaBR($valor_num);

/* ========= Profissional responsável =========
   1) profissional do 'receber' (usuario_lanc) se existir e for nível "Profissional*"
   2) usuário logado se for "Profissional*"
   3) qualquer "Profissional*" ativo
*/
$prof_nome = ''; $prof_nivel='';

if (!empty($prof_id)) {
  $q = $pdo->prepare("SELECT nome, nivel FROM usuarios WHERE id = :id LIMIT 1");
  $q->execute([':id'=>$prof_id]);
  if ($u = $q->fetch(PDO::FETCH_ASSOC)) {
    if (nivelEhProfissional($u['nivel'])) { $prof_nome = $u['nome']; $prof_nivel = $u['nivel']; }
  }
}

if ($prof_nome === '' && !empty($_SESSION['id'])) {
  $q = $pdo->prepare("SELECT nome, nivel, ativo FROM usuarios WHERE id = :id LIMIT 1");
  $q->execute([':id'=>(int)$_SESSION['id']]);
  if ($u = $q->fetch(PDO::FETCH_ASSOC)) {
    if (($u['ativo'] ?? 'Sim') === 'Sim' && nivelEhProfissional($u['nivel'])) {
      $prof_nome = $u['nome']; $prof_nivel = $u['nivel'];
    }
  }
}

if ($prof_nome === '') {
  $q = $pdo->query("SELECT nome, nivel FROM usuarios WHERE ativo='Sim' AND (LOWER(nivel) LIKE 'profissional%') ORDER BY id DESC LIMIT 1");
  if ($u = $q->fetch(PDO::FETCH_ASSOC)) { $prof_nome = $u['nome']; $prof_nivel = $u['nivel']; }
}

$profNomeF = mb_strtoupper($prof_nome);

/* ========= Saída do Contrato ========= */
?>
<style>
  body{ font-family: DejaVu Sans, Arial, Helvetica, sans-serif; font-size:14px; color:#000; }
  p{ margin: 0 0 10px 0; line-height: 1.35; }
  .ass-block{ width:100%; margin-top:40px; }
  .ass-row{ width:100%; display:table; }
  .ass-col{ display:table-cell; width:50%; text-align:center; vertical-align:bottom; padding:0 10px; }
  .ass-line{ border-top:1px solid #000; height:40px; margin:0 20px; }
  .ass-caption{ font-size:12px; margin-top:6px; }
</style>

<p>
Pelo presente instrumento, de um lado, a CONTRATADA, <b><?= esc($nome_sistemaF) ?></b>, CNPJ: <?= esc($cnpj_sistema) ?>, com sede na <?= esc($endereco_sistema) ?>, e, de outro lado, a CONTRATANTE, <b><?= esc($nomeF) ?></b>, CPF: <?= esc($cpf) ?>, partes qualificadas acima, têm entre si justo e contratado o presente <b>CONTRATO DE PRESTAÇÃO DE SERVIÇOS DE BELEZA</b>, que se regerá pelas cláusulas e condições seguintes.
</p>

<p><b>Profissional responsável:</b> <?= esc($prof_nome) ?><?= $prof_nivel ? ' – '.esc($prof_nivel) : '' ?></p>

<p><b>I – OBJETO</b></p>
<p>
Cláusula 1ª – O objeto deste contrato é a prestação, pela CONTRATADA, de serviços profissionais na área de beleza, incluindo, entre outros: <b>prótese capilar</b> (aplicação, manutenção e remoção), <b>escova progressiva</b>, <b>mechas/coloração</b>, <b>corte</b>, <b>tratamentos capilares</b> e procedimentos correlatos descritos na ordem de serviço, ficha de atendimento ou agendamento da CONTRATANTE.
</p>

<p><b>II – DO PREÇO</b></p>
<p>
Cláusula 1ª – <b>Serviço:</b> <?= $servicoF ?> – <b><?= $valorF ?></b> – valor convencionado entre as partes para a manutenção/execução descrita no Objeto.
</p>
<p>
Cláusula 2ª – O preço foi livremente ajustado e refere-se à realização dos procedimentos especificados, não incluindo serviços diversos aos aqui descritos, materiais especiais não contemplados na proposta inicial ou sessões adicionais.
</p>

<p><b>III – PREPARAÇÃO, TESTES E CONTRAINDICAÇÕES</b></p>
<p>
Cláusula 3ª – Para procedimentos químicos (ex.: progressiva, mechas/coloração) a CONTRATADA poderá realizar <b>teste de mecha</b> e/ou <b>teste de sensibilidade/alergia</b>. A continuidade do serviço dependerá do resultado desses testes e do histórico químico informado pela CONTRATANTE.
</p>
<p>
Cláusula 4ª – A CONTRATANTE declara ter informado, com veracidade, seu histórico de procedimentos químicos, uso de medicamentos, alergias, gestação, lactação ou condições de saúde que possam influenciar nos resultados ou indicar contraindicação. Havendo contraindicação técnica, o serviço poderá ser <b>recusado ou adaptado</b> pela CONTRATADA.
</p>

<p><b>IV – EXECUÇÃO, SEGURANÇA E PRODUTOS</b></p>
<p>
Cláusula 5ª – Os serviços serão executados por profissionais habilitados, com produtos regularizados e procedimentos compatíveis com as boas práticas do setor. Para escova progressiva, serão utilizados produtos <b>conformes às normas vigentes</b>; quando necessário, será empregado exaustor/ventilação adequada e proteção compatível.
</p>
<p>
Cláusula 6ª – Em prótese capilar, a CONTRATANTE está ciente de que a durabilidade do sistema (base/fios/adesivos) depende de cuidados domiciliares, oleosidade, exposição a calor/sol, piscina/mar e periodicidade de manutenção.
</p>

<p><b>V – AGENDAMENTO, REMARCAÇÃO E AUSÊNCIA</b></p>
<p>
Cláusula 7ª – Os serviços contratados serão realizados nas datas e horários agendados. Remarcações deverão ser solicitadas com <b>antecedência mínima de 12 (doze) horas</b>. Cancelamentos ou não comparecimento com prazo inferior poderão implicar <b>perda da sessão</b> e/ou cobrança de taxa de remarcação, conforme política interna.
</p>
<p>
Cláusula 8ª – Em caso de atraso da CONTRATANTE para a manutenção/agendamento, o tempo de atendimento poderá ser <b>proporcionalmente reduzido</b>, sem compensação.
</p>

<p><b>VI – RESPONSABILIDADES E RESULTADOS</b></p>
<p>
Cláusula 9ª – Resultados dependem de variáveis individuais (estrutura e histórico dos fios, cuidados, produtos adequados e periodicidade de manutenção). A CONTRATADA não garante resultados permanentes ou idênticos a referências fotográficas, comprometendo-se com a execução técnica adequada e orientações de pós-serviço.
</p>
<p>
Cláusula 10ª – A CONTRATANTE compromete-se a seguir as <b>orientações profissionais</b> e, quando indicado, utilizar produtos recomendados para manutenção domiciliar. O descumprimento poderá comprometer os resultados.
</p>

<p><b>VII – LGPD, IMAGENS E DEMAIS DISPOSIÇÕES</b></p>
<p>
Cláusula 11ª – A CONTRATANTE poderá <b>autorizar</b>, de forma opcional, registro fotográfico/filmagem do antes e depois para fins de prontuário e/ou divulgação institucional, mediante termo de consentimento específico. Os dados pessoais serão tratados conforme a legislação aplicável (LGPD).
</p>
<p>
Cláusula 12ª – Este instrumento vigora a partir do primeiro atendimento e permanece válido enquanto houver prestação continuada (ex.: manutenção de prótese capilar), podendo ser rescindido nas hipóteses legais ou por descumprimento das condições aqui previstas.
</p>

<!-- Assinaturas com nomes -->
<div class="ass-block">
  <div class="ass-row">
    <div class="ass-col">
      <div class="ass-line"></div>
      <div class="ass-caption"><b>Assinatura da Cliente</b><br><?= esc($nomeF) ?></div>
    </div>
    <div class="ass-col">
      <div class="ass-line"></div>
      <div class="ass-caption"><b>Assinatura do Profissional</b><br><?= esc($profNomeF) ?></div>
    </div>
  </div>
  <div style="text-align:center; margin-top:28px;"><?= esc($cidade_data) ?></div>
</div>
