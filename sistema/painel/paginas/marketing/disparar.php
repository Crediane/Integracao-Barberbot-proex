<?php
declare(strict_types=1);



require_once __DIR__ . '/../_context_bootstrap.php';
// Guarda slug na sessão (ajuda includes/conexão a escolher o banco da loja)
if (!empty($__bb_reqSlug)) {
  $_SESSION['loja_slug'] = $__bb_reqSlug;
  $_SESSION['tenant_slug'] = $__bb_reqSlug;
  $_SESSION['loja'] = $__bb_reqSlug;
  if (empty($_GET['loja'])) {
    $_GET['loja'] = $__bb_reqSlug;
  }
}

ini_set('display_errors', '1');
error_reporting(E_ALL);

require_once(__DIR__ . "/../../../conexao.php");
/* =========================
   Helpers
   ========================= */
function today(): string { return date('Y-m-d'); }
function nowHMS(): string { return date('H:i:s'); }

function fail(string $msg): void { echo $msg; exit; }
function assertOrFail(bool $cond, string $msg): void { if(!$cond) fail($msg); }

/** Trunca string em UTF-8 */
function strLimit(string $s, int $limit): string {
  return function_exists('mb_substr') ? mb_substr($s, 0, $limit, 'UTF-8') : substr($s, 0, $limit);
}

/** HH:MM/HH:MM:SS -> HH:MM:SS, “agora” permitido */
function normalizeTime(string $h): string {
  $h = trim($h);
  if ($h === '' ) return '';
  if (strtolower($h) === 'agora') return 'agora';
  if (preg_match('/^\d{2}:\d{2}$/', $h))     return $h . ':00';
  if (preg_match('/^\d{2}:\d{2}:\d{2}$/', $h)) return $h;
  return '';
}

/** Converte rótulos de frequência em dias/códigos conhecidos */
function parseFrequency($freqIn): int {
  if ($freqIn === '' || $freqIn === null) return 0;
  if (is_numeric($freqIn)) return (int)$freqIn;

  $map = [
    'nenhuma'    => 0,
    'diária'     => 1,
    'diaria'     => 1,
    'semanal'    => 7,
    'quinzenal'  => 15,
    'mensal'     => 30,   // tratado como “mês” em nextDateByFrequency
    'trimestral' => 90,
    'semestral'  => 180,
    'anual'      => 365,  // tratado como “ano” se 360/365
  ];
  $key = strtolower(trim((string)$freqIn));
  return $map[$key] ?? 0;
}

/** Próxima data pela frequência; meses especiais tratados */
function nextDateByFrequency(string $baseDate, int $freq, int $step): string {
  if (in_array($freq, [30,31], true))  return date('Y-m-d', strtotime("+{$step} month", strtotime($baseDate)));
  if ($freq === 90)                   return date('Y-m-d', strtotime("+".($step*3)." month", strtotime($baseDate)));
  if ($freq === 180)                  return date('Y-m-d', strtotime("+".($step*6)." month", strtotime($baseDate)));
  if (in_array($freq,[360,365], true)) return date('Y-m-d', strtotime("+".($step*12)." month", strtotime($baseDate)));
  return date('Y-m-d', strtotime("+".($step*$freq)." day", strtotime($baseDate)));
}

/** Interpreta seleção de “clientes” (grupo_xxx ou ‘Grupo: Nome’) */
function interpretClientsSelection(PDO $pdo, string $sel): array {
  $sel = trim($sel);

  if (stripos($sel, 'grupo_') === 0) {
    $idGrupo = (int)substr($sel, 6);
    $st = $pdo->prepare("SELECT id, nome FROM grupos_disparos WHERE id = :id LIMIT 1");
    $st->execute([':id' => $idGrupo]);
    $g = $st->fetch(PDO::FETCH_ASSOC);
    assertOrFail((bool)$g, 'Grupo não encontrado!');
    return ['mode'=>'group','id'=>$g['id'], 'name'=>$g['nome']];
  }

  if (stripos($sel, 'grupo:') === 0) {
    $nome = trim(substr($sel, 6));
    $st = $pdo->prepare("SELECT id, nome FROM grupos_disparos WHERE nome = :n LIMIT 1");
    $st->execute([':n' => $nome]);
    $g = $st->fetch(PDO::FETCH_ASSOC);
    assertOrFail((bool)$g, 'Grupo não encontrado!');
    return ['mode'=>'group','id'=>$g['id'], 'name'=>$g['nome']];
  }

  return ['mode'=>'preset', 'value'=>$sel];
}

/* =========================
   Entradas
   ========================= */
$id               = isset($_POST['id'])              ? (int)$_POST['id'] : 0;
$clientesSel      = trim($_POST['clientes']          ?? '');
$data_disparo     = trim($_POST['data_disparo']      ?? '');
$hora_disparo_in  = trim($_POST['hora_disparo']      ?? '');
$frequencia_in    = $_POST['frequencia']            ?? 0;
$quantidade       = (int)($_POST['quantidade']      ?? 1);
$intervalo_horas  = (int)($_POST['intervalo_horas'] ?? 1);

$freqDays = parseFrequency($frequencia_in);
$timeNorm = normalizeTime($hora_disparo_in);
$today    = today();
$timeNow  = date('H:i:s');

/* =========================
   Validações
   ========================= */
assertOrFail($id > 0, 'Campanha inválida!');
assertOrFail($clientesSel !== '', 'Selecione os destinatários!');
assertOrFail($timeNorm !== '', 'Selecione um horário válido!');
assertOrFail($data_disparo !== '', 'Selecione uma data de disparo!');
assertOrFail($intervalo_horas > 0, 'Intervalo inválido (mínimo 1 minuto).');

assertOrFail($data_disparo >= $today, 'A data de disparo não pode ser menor que a data atual!');
if ($data_disparo === $today && $timeNorm !== 'agora') {
  assertOrFail(strtotime($timeNorm) > strtotime($timeNow), 'O horário de disparo precisa ser maior que o horário atual!');
}

/* =========================
   Importações (somente quando houver arquivo)
   ========================= */
if (!empty($_FILES['arquivo_excel']['name'] ?? '')) {
  // Garante caminho correto a partir de /marketing/disparar.php
  require __DIR__ . '/importar_excel.php';
  exit; // o import já faz o processamento e finaliza
}

if (!empty($_FILES['arquivo_texto']['name'] ?? '')) {
  require __DIR__ . '/importar_txt.php';
  exit; // idem
}


/* =========================
   Busca de destinatários
   ========================= */
$interpreted = interpretClientsSelection($pdo, $clientesSel);
$clientes_nome_para_salvar = $clientesSel;
$lista = [];

try {
  if ($interpreted['mode'] === 'group') {
    $idGrupo = (int)$interpreted['id'];
    $clientes_nome_para_salvar = 'Grupo: ' . $interpreted['name'];

    $st = $pdo->prepare("
      SELECT DISTINCT c.id, c.nome, c.telefone
        FROM grupos_clientes gc
        JOIN clientes c ON c.id = gc.cliente
       WHERE gc.grupo = :g AND c.telefone <> ''
    ");
    $st->execute([':g' => $idGrupo]);
    $lista = $st->fetchAll(PDO::FETCH_ASSOC);

  } else {
    $mes = date('m'); $ano = date('Y'); $dia = date('d');

    switch ($interpreted['value']) {
      case 'Teste':
      case 'Teste (Apenas Administrador)':
        $sql = "SELECT id, nome, telefone_whatsapp AS telefone FROM config WHERE telefone_whatsapp <> ''";
        $st = $pdo->prepare($sql);
        $st->execute([':data_hoje' => date('Y-m-d')]);
        $lista = $st->fetchAll(PDO::FETCH_ASSOC);
        break;

      case 'Todos os Clientes':
  $sql = "SELECT id, nome, telefone FROM clientes WHERE telefone <> ''";
  $lista = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
  break;
;

      case 'Clientes Cadastrados na Última Semana':
        $semana = date('Y-m-d', strtotime('-7 days', strtotime($today)));
        $st = $pdo->prepare("
          SELECT id, nome, telefone FROM clientes
           WHERE data_cad >= :s
             AND telefone <> '' AND (marketing = '' OR marketing IS NULL)
        ");
        $st->execute([':s' => $semana]);
        $lista = $st->fetchAll(PDO::FETCH_ASSOC);
        break;

      case 'Clientes Cadastrados no Último Mês':
        $st = $pdo->prepare("
          SELECT id, nome, telefone FROM clientes
           WHERE MONTH(data_cad) = :m AND YEAR(data_cad) = :a
             AND telefone <> '' AND (marketing = '' OR marketing IS NULL)
        ");
        $st->execute([':m' => $mes, ':a' => $ano]);
        $lista = $st->fetchAll(PDO::FETCH_ASSOC);
        break;

      case 'Aniversariantes Mês':
        $st = $pdo->prepare("
          SELECT id, nome, telefone FROM clientes
           WHERE MONTH(data_nasc) = :m
             AND telefone <> '' AND (marketing = '' OR marketing IS NULL)
        ");
        $st->execute([':m' => $mes]);
        $lista = $st->fetchAll(PDO::FETCH_ASSOC);
        break;

      case 'Aniversariantes Dia':
        $st = $pdo->prepare("
          SELECT id, nome, telefone FROM clientes
           WHERE MONTH(data_nasc) = :m AND DAY(data_nasc) = :d
             AND telefone <> '' AND (marketing = '' OR marketing IS NULL)
        ");
        $st->execute([':m' => $mes, ':d' => $dia]);
        $lista = $st->fetchAll(PDO::FETCH_ASSOC);
        break;

      case 'Clientes Inadimplentes':
      case 'Inadimplentes':
        $sql = "
          SELECT DISTINCT c.id, c.nome, c.telefone
            FROM clientes c
            JOIN receber r ON c.id = r.pessoa
           WHERE r.data_venc < :data_hoje AND r.pago = 'Não'
        ";
        $lista = $pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);
        break;

      default:
        $lista = [];
    }
  }
} catch (Throwable $e) {
  fail('Erro ao carregar destinatários: ' . $e->getMessage());
}

assertOrFail(count($lista) > 0, 'Nenhum destinatário encontrado para este filtro.');

/* =========================
   Deduplicação por telefone
   ========================= */
$uniq = [];
$listaUniq = [];
foreach ($lista as $row) {
  $tel = preg_replace('/\D+/', '', (string)($row['telefone'] ?? ''));
  if ($tel === '' || strlen($tel) < 10) continue;
  if (!isset($uniq[$tel])) {
    $uniq[$tel] = true;
    $row['telefone'] = $tel;
    $listaUniq[] = $row;
  }
}
$lista = $listaUniq;
assertOrFail(count($lista) > 0, 'Nenhum destinatário válido após deduplicação.');

/* =========================
   Preparação de INSERT
   ========================= */
$ins = $pdo->prepare("
  INSERT INTO disparos (campanha, cliente, nome, telefone, hora, data_disparo)
  VALUES (:campanha, :cliente, :nome, :telefone, :hora, :data_disparo)
");

$exists = $pdo->prepare("
  SELECT 1 FROM disparos
   WHERE campanha = :campanha
     AND cliente  = :cliente
     AND data_disparo = :data_disparo
   LIMIT 1
");

$limpa = $pdo->prepare("
  DELETE FROM disparos
   WHERE campanha = :id
     AND data_disparo >= :data_hoje
");
$limpa->execute([':id' => $id, ':data_hoje' => date('Y-m-d')]);

$pdo->beginTransaction();

try {

  // Intervalo é em MINUTOS (mantemos o nome do campo para não quebrar o frontend)
  $horaBase = ($timeNorm === 'agora') ? nowHMS() : $timeNorm;
  $tStart   = strtotime("$data_disparo $horaBase");
  $seq      = 0;

  foreach ($lista as $row) {
    $idCli   = (int)($row['id'] ?? 0);
    $nomeCli = strLimit((string)($row['nome'] ?? ''), 50);
    $telCli  = strLimit((string)($row['telefone'] ?? ''), 25);
    if ($idCli <= 0 || $telCli === '') continue;
    // Agenda sequencial: base + (N * intervalo)
    // OBS: $intervalo_horas aqui representa MINUTOS
    $tSend   = $tStart + ($seq * $intervalo_horas * 60);
    $horaIns = date('H:i:s', $tSend);
    $dataIns = date('Y-m-d', $tSend);
$exists->execute([
      ':campanha'     => $id,
      ':cliente'      => $idCli,
      ':data_disparo' => $dataIns,
    ]);
    if ($exists->fetch()) continue;

    $ins->execute([
      ':campanha'     => $id,
      ':cliente'      => $idCli,
      ':nome'         => $nomeCli,
      ':telefone'     => $telCli,
      ':hora'         => $horaIns,
      ':data_disparo' => $dataIns,
    ]);

    $seq++;

    // Recorrências
    if ($freqDays > 0 && $quantidade > 1) {
      for ($k = 1; $k < $quantidade; $k++) {
        $novaData = nextDateByFrequency($dataIns, $freqDays, $k);

        $exists->execute([
          ':campanha'     => $id,
          ':cliente'      => $idCli,
          ':data_disparo' => $novaData,
        ]);
        if ($exists->fetch()) continue;

        $ins->execute([
          ':campanha'     => $id,
          ':cliente'      => $idCli,
          ':nome'         => $nomeCli,
          ':telefone'     => $telCli,
          ':hora'         => $horaIns,
          ':data_disparo' => $novaData,
        ]);
      }
    }
  }

  $tot = $pdo->prepare("SELECT COUNT(*) FROM disparos WHERE campanha = :id");
  $tot->execute([':id' => $id]);
  $total_disparos = (int)$tot->fetchColumn();

  $up = $pdo->prepare("
    UPDATE marketing
       SET total_disparos = :t, forma_envio = :f
     WHERE id = :id
  ");
  $up->execute([
    ':t'  => $total_disparos,
    ':f'  => $clientes_nome_para_salvar,
    ':id' => $id,
  ]);

  $pdo->commit();
  echo 'Salvo com Sucesso';
} catch (Throwable $e) {
  $pdo->rollBack();
  echo 'Erro ao salvar disparos: ' . $e->getMessage();
}
?>