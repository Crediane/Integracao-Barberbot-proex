<?php
@date_default_timezone_set('America/Sao_Paulo');
ini_set('display_errors', 0);
ini_set('display_startup_errors', 0);
error_reporting(E_ALL);

require_once(__DIR__ . '/../sistema/conexao.php');

$LOG = __DIR__ . '/menuia_gateway_debug.txt';

$SESS_DIR = __DIR__ . '/sessions';
if (!is_dir($SESS_DIR)) {
    @mkdir($SESS_DIR, 0755, true);
}


// ============================================================================
// RESPOSTA ÚNICA POR WEBHOOK (ANTI-DUPLICIDADE)
// ----------------------------------------------------------------------------
// Objetivo: garantir que o BOT responda apenas 1 vez ao mesmo remetente dentro
// do mesmo webhook (mesma requisição) e também evitar duplicidade em retries.
// NÃO altera lógica de agendamento/validações/regras financeiras.
// ============================================================================
$DEDUPE_DIR = __DIR__ . '/webhook_dedupe';
if (!is_dir($DEDUPE_DIR)) {
    @mkdir($DEDUPE_DIR, 0755, true);
}
// Janela curta para segurar reentregas do mesmo webhook (Menuia retries)
$DEDUPE_TTL = 25; // segundos

function bb_buildWebhookKey($instancia, $remetente55, $destinatario55, $msgId, $msgTexto): string {
    $instancia = trim((string)$instancia);
    $remetente55 = formatPhone55((string)$remetente55);
    $destinatario55 = formatPhone55((string)$destinatario55);
    $msgId = trim((string)$msgId);
    $fallback = $msgId !== '' ? $msgId : substr(hash('sha256', (string)$msgTexto), 0, 16);
    return hash('sha256', $instancia . '|' . $remetente55 . '|' . $destinatario55 . '|' . $fallback);
}

function bb_initReplyGate($instancia, $remetente55, $destinatario55, $msgId, $msgTexto): void {
    $GLOBALS['bb_peer_phone55'] = formatPhone55((string)$remetente55);
    $GLOBALS['bb_webhook_key'] = bb_buildWebhookKey($instancia, $remetente55, $destinatario55, $msgId, $msgTexto);
    $GLOBALS['bb_reply_sent_peer'] = false;
    $GLOBALS['bb_pending_peer_msgs'] = [];
}

function bb_isPeerTarget($telefone55): bool {
    $peer = (string)($GLOBALS['bb_peer_phone55'] ?? '');
    if ($peer === '') return false;
    return formatPhone55((string)$telefone55) === $peer;
}

function bb_isBotAtualTarget($telefone55): bool {
    $alvo = formatPhone55((string)$telefone55);
    if ($alvo === '') return false;

    $possiveisBots = [];
    foreach (['tenantBot55', 'destinatarioEvento', 'bot55'] as $chaveGlobal) {
        $valor = formatPhone55((string)($GLOBALS[$chaveGlobal] ?? ''));
        if ($valor !== '') {
            $possiveisBots[] = $valor;
        }
    }

    $possiveisBots = array_values(array_unique($possiveisBots));
    return in_array($alvo, $possiveisBots, true);
}

function bb_peerAlreadySent(): bool {
    return !empty($GLOBALS['bb_reply_sent_peer']);
}

function bb_peerMarkSent(): void {
    $GLOBALS['bb_reply_sent_peer'] = true;
}

function bb_peerQueueMsg(string $mensagem): void {
    $mensagem = trim($mensagem);
    if ($mensagem === '') return;
    if (!isset($GLOBALS['bb_pending_peer_msgs']) || !is_array($GLOBALS['bb_pending_peer_msgs'])) {
        $GLOBALS['bb_pending_peer_msgs'] = [];
    }
    // evita duplicar o mesmo texto
    $h = hash('sha256', $mensagem);
    foreach ($GLOBALS['bb_pending_peer_msgs'] as $it) {
        if (is_array($it) && isset($it['h']) && $it['h'] === $h) return;
    }
    $GLOBALS['bb_pending_peer_msgs'][] = ['h' => $h, 't' => $mensagem];
}

function bb_peerPrependPending(string $mensagem): string {
    if (empty($GLOBALS['bb_pending_peer_msgs']) || !is_array($GLOBALS['bb_pending_peer_msgs'])) return $mensagem;
    $parts = [];
    foreach ($GLOBALS['bb_pending_peer_msgs'] as $it) {
        if (is_array($it) && !empty($it['t'])) $parts[] = (string)$it['t'];
    }
    $GLOBALS['bb_pending_peer_msgs'] = []; // limpa após consumir
    $parts[] = $mensagem;
    return trim(implode("\n\n", array_filter($parts)));
}

function bb_dedupeFile(string $dir, string $key, string $telefone55): string {
    $safe = hash('sha256', $key . '|' . formatPhone55($telefone55));
    return rtrim($dir, '/\\') . '/' . $safe . '.json';
}

function bb_dedupeWasSent(string $dir, string $key, string $telefone55, int $ttl): bool {
    if ($key === '') return false;
    $file = bb_dedupeFile($dir, $key, $telefone55);
    if (!is_file($file)) return false;
    $raw = @file_get_contents($file);
    if ($raw === false || trim($raw) === '') return false;
    $j = json_decode($raw, true);
    $ts = (int)($j['ts'] ?? 0);
    if ($ts <= 0) return false;
    if ((time() - $ts) <= $ttl) return true;
    return false;
}

function bb_dedupeMarkSent(string $dir, string $key, string $telefone55): void {
    if ($key === '') return;
    $file = bb_dedupeFile($dir, $key, $telefone55);
    $payload = json_encode(['ts' => time(), 'key' => $key, 'to' => formatPhone55($telefone55)], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    @file_put_contents($file, $payload, LOCK_EX);
}

$HUMANO_TIMEOUT = 1800;
$BURST_WINDOW = 4;

function logLine($file, $msg){
    @file_put_contents($file, date('Y-m-d H:i:s') . ' - ' . $msg . PHP_EOL, FILE_APPEND);
}

function onlyDigits($s){
    return preg_replace('/\D+/', '', (string)$s);
}


function bb_opt_line($num, $texto){
    $n = (int)$num;
    // Formato padrão no WhatsApp: [ 1 ] Texto
    return "[ " . $n . " ] " . $texto . "\n";
}


    // === Helpers Menuia (evita Array->String) =============================
    function menuiaScalarText($v){
        if (is_null($v)) return '';
        if (is_bool($v)) return $v ? '1' : '0';
        if (is_int($v) || is_float($v)) return (string)$v;
        if (is_string($v)) return $v;

        if (is_array($v)) {
            foreach (['mensagem','message','text','body','content','caption','msg'] as $k) {
                if (isset($v[$k]) && is_string($v[$k])) return $v[$k];
            }
            if (isset($v['text']) && is_array($v['text'])) {
                foreach (['body','message','text','content'] as $k) {
                    if (isset($v['text'][$k]) && is_string($v['text'][$k])) return $v['text'][$k];
                }
            }
            $j = json_encode($v, JSON_UNESCAPED_UNICODE);
            return $j !== false ? $j : '';
        }

        return '';
    }

    function menuiaMessageText(array $dados){
        $m = $dados['mensagem'] ?? ($dados['message'] ?? '');
        return menuiaScalarText($m);
    }
    // =====================================================================

function leadingNumber($text){
    $text = trim((string)$text);
    if($text === '') return 0;
    if(preg_match('/^\s*(\d{1,3})\b/u', $text, $m)) return (int)$m[1];
    return 0;
}

function menuHint(){
    return "Digite *menu* para ver as opções.";
}

function containsAny($text, array $needles){
    foreach($needles as $n){
        $n = (string)$n;
        if($n !== '' && mb_stripos((string)$text, $n, 0, 'UTF-8') !== false){
            return true;
        }
    }
    return false;
}

function mapGet($map, $key){
    if(!is_array($map)) return null;
    $ks = (string)$key;
    if(isset($map[$ks])) return $map[$ks];
    $ki = (int)$key;
    if(isset($map[$ki])) return $map[$ki];
    return null;
}

function simNaoToInt($v){
    if (is_null($v)) return 0;
    if (is_bool($v)) return $v ? 1 : 0;
    $s = trim((string)$v);
    if ($s === '') return 0;
    if (is_numeric($s)) return ((int)$s) === 1 ? 1 : 0;
    $s = mb_strtolower($s, 'UTF-8');
    if ($s === 'sim' || $s === 's' || $s === 'true' || $s === 'on' || $s === 'ativo') return 1;
    return 0;
}

function stripMenuDuplicadoIA($text){
    $text = (string)$text;
    if(trim($text) === '') return '';
    $lines = preg_split("/\r\n|\n|\r/", $text);
    $out = [];
    $skip = false;
    $skipCount = 0;
    foreach($lines as $line){
        $ltrim = ltrim((string)$line);
        $low = mb_strtolower($ltrim, 'UTF-8');
        if(!$skip && mb_stripos($low, 'menu:', 0, 'UTF-8') !== false){
            $skip = true;
            $skipCount = 0;
            continue;
        }
        if($skip){
            $skipCount++;
            if(preg_match('/^\s*[1-4]\s*[$|\-\.\:]\s*/u', $ltrim) || preg_match('/^\s*[1-4]\s+/u', $ltrim)){
                if($skipCount <= 10) continue;
            }
            if(trim($ltrim) === '' && $skipCount <= 10) continue;
            $skip = false;
        }
        $out[] = (string)$line;
    }
    return trim(preg_replace("/\n{3,}/", "\n\n", implode("\n", $out)));
}

function formatPhone55($n){
    $d = onlyDigits($n);
    if ($d === '') return '';
    if (strpos($d, '55') === 0) return $d;
    if (strlen($d) === 11) return '55' . $d;
    if (strlen($d) === 10) return '55' . $d;
    return $d;
}


function configMapUrl($raw, $endereco = ''){
    $raw = trim((string)$raw);
    $endereco = trim((string)$endereco);
    if($raw === '' && $endereco === '') return '';

    $decoded = html_entity_decode($raw, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $url = '';

    if(preg_match('/src=[\"\']([^\"\']+)[\"\']/iu', $decoded, $m)){
        $url = trim((string)$m[1]);
    } elseif(preg_match('~https?://[^\s<>\"\']+~iu', $decoded, $m)){
        $url = trim((string)$m[0]);
    } else {
        $url = $decoded;
    }

    $isGoogleEmbed = false;
    if($url !== ''){
        if(stripos($url, 'google.com/maps/embed') !== false || stripos($url, '/maps/embed?') !== false){
            $isGoogleEmbed = true;
        }
        if(stripos($url, 'output=embed') !== false){
            $isGoogleEmbed = true;
        }
    }

    if($isGoogleEmbed){
        if($endereco !== ''){
            return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($endereco);
        }

        if(preg_match('/[?&](?:q|query)=([^&]+)/i', $url, $m)){
            $q = trim((string)urldecode($m[1]));
            if($q !== ''){
                return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($q);
            }
        }
    }

    if($url === '' && $endereco !== ''){
        return 'https://www.google.com/maps/search/?api=1&query=' . rawurlencode($endereco);
    }

    return $url;
}

function last11($phone){
    $d = onlyDigits($phone);
    return strlen($d) >= 11 ? substr($d, -11) : $d;
}

function phoneKeyCanonical10($phone){
    $d = onlyDigits($phone);
    if(strlen($d) > 10 && str_starts_with($d, '55')) $d = substr($d, 2);
    if(strlen($d) === 11 && substr($d, 2, 1) === '9'){
        $d = substr($d, 0, 2) . substr($d, 3);
    }
    if(strlen($d) > 10) $d = substr($d, -10);
    return $d;
}

function sessionKey($cliente, $prof){
    $c = phoneKeyCanonical10($cliente);
    $p = phoneKeyCanonical10($prof);
    return sha1($c . '|' . $p);
}

function sessionFile($dir, $cliente, $prof){
    $key = sessionKey($cliente, $prof);
    return rtrim($dir,'/') . "/sess_{$key}.json";
}

function getSession($dir, $cliente, $prof){
    $f = sessionFile($dir, $cliente, $prof);
    if(!file_exists($f)) return [];
    $j = @json_decode(@file_get_contents($f), true);
    return is_array($j) ? $j : [];
}

function saveSession($dir, $cliente, $prof, $data){
    $f = sessionFile($dir, $cliente, $prof);
    @file_put_contents($f, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}

function deleteSession($dir, $cliente, $prof){
    $f = sessionFile($dir, $cliente, $prof);
    if(file_exists($f)){
        @unlink($f);
    }
}


function bb_clearAwaitConfirm(array &$sess, string $sessDir, string $cliente, string $prof): void {
    unset(
        $sess['await_confirm_until'],
        $sess['await_confirm_set_at'],
        $sess['await_confirm_ref'],
        $sess['await_confirm_phone'],
        $sess['await_confirm_data'],
        $sess['await_confirm_hora']
    );

    saveSession($sessDir, $cliente, $prof, $sess);

    // Blindagem: limpa também o espelho da conversa.
    $sessEspelho = getSession($sessDir, $prof, $cliente);
    if(is_array($sessEspelho) && !empty($sessEspelho)){
        unset(
            $sessEspelho['await_confirm_until'],
            $sessEspelho['await_confirm_set_at'],
            $sessEspelho['await_confirm_ref'],
            $sessEspelho['await_confirm_phone'],
            $sessEspelho['await_confirm_data'],
            $sessEspelho['await_confirm_hora']
        );
        saveSession($sessDir, $prof, $cliente, $sessEspelho);
    }
}

function bb_armAwaitConfirm(
    string $sessDir,
    string $cliente,
    string $prof,
    string $ref = '',
    string $phone55 = '',
    int $ttl = 21600,
    string $dataConfirmacao = '',
    string $horaConfirmacao = ''
): void {
    $sessOut = getSession($sessDir, $cliente, $prof);
    $sessOut['await_confirm_until'] = time() + max(60, (int)$ttl);
    $sessOut['await_confirm_set_at'] = time();

    $ref = trim((string)$ref);
    if($ref !== ''){
        $sessOut['await_confirm_ref'] = $ref;
    } else {
        unset($sessOut['await_confirm_ref']);
    }

    $phone55 = formatPhone55($phone55);
    if($phone55 !== ''){
        $sessOut['await_confirm_phone'] = $phone55;
    }

    $dataConfirmacao = bb_normalizarDataConfirmacao($dataConfirmacao);
    if($dataConfirmacao !== ''){
        $sessOut['await_confirm_data'] = $dataConfirmacao;
    }

    $horaConfirmacao = bb_normalizarHoraConfirmacao($horaConfirmacao);
    if($horaConfirmacao !== ''){
        $sessOut['await_confirm_hora'] = $horaConfirmacao;
    }

    saveSession($sessDir, $cliente, $prof, $sessOut);
}

function bb_normalizarDataConfirmacao(string $data): string {
    $data = trim((string)$data);
    if($data === '') return '';

    if(preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $data, $m)){
        return checkdate((int)$m[2], (int)$m[3], (int)$m[1]) ? $data : '';
    }

    if(preg_match('/^(\d{1,2})[\/\-](\d{1,2})[\/\-](\d{2,4})$/', $data, $m)){
        $dia = (int)$m[1];
        $mes = (int)$m[2];
        $ano = (int)$m[3];
        if($ano < 100) $ano += 2000;
        if(checkdate($mes, $dia, $ano)){
            return sprintf('%04d-%02d-%02d', $ano, $mes, $dia);
        }
    }

    return '';
}

function bb_normalizarHoraConfirmacao(string $hora): string {
    $hora = trim((string)$hora);
    if($hora === '') return '';

    if(preg_match('/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/', $hora, $m)){
        $h = (int)$m[1];
        $i = (int)$m[2];
        $s = isset($m[3]) ? (int)$m[3] : 0;
        if($h >= 0 && $h <= 23 && $i >= 0 && $i <= 59 && $s >= 0 && $s <= 59){
            return sprintf('%02d:%02d:00', $h, $i);
        }
    }

    return '';
}

function bb_parseConfirmacaoDataHora(string $texto): array {
    $texto = trim((string)$texto);
    $retorno = ['data' => '', 'hora' => ''];

    if($texto === ''){
        return $retorno;
    }

    $textoLimpo = str_replace(["\r", "\n", "\t", '*', '_', '`'], ' ', $texto);
    $textoLimpo = preg_replace('/\s+/', ' ', $textoLimpo);

    if(preg_match('/(\d{1,2}[\/\-]\d{1,2}[\/\-]\d{2,4}).{0,80}?(\d{1,2}:\d{2})/u', $textoLimpo, $m)){
        $retorno['data'] = bb_normalizarDataConfirmacao((string)$m[1]);
        $retorno['hora'] = bb_normalizarHoraConfirmacao((string)$m[2]);
    }

    return $retorno;
}

function bb_agendamentoRefExiste(PDO $pdo, string $ref): bool {
    $ref = trim((string)$ref);
    if($ref === '' || !tableExists($pdo, 'agendamentos')) return false;

    try{
        if(columnExists($pdo, 'agendamentos', 'hash')){
            $st = $pdo->prepare("SELECT id FROM agendamentos WHERE `hash` = :ref OR `id` = :ref LIMIT 1");
        } else {
            $st = $pdo->prepare("SELECT id FROM agendamentos WHERE `id` = :ref LIMIT 1");
        }

        $st->execute([':ref' => $ref]);
        return (bool)$st->fetchColumn();
    }catch(Throwable $e){
        return false;
    }
}

function bb_findPendingConfirmAgendamento(
    PDO $pdo,
    string $phone55,
    string $ref = '',
    string $dataConfirmacao = '',
    string $horaConfirmacao = ''
): ?array {
    if(!tableExists($pdo, 'agendamentos')) return null;

    $phone55 = formatPhone55($phone55);
    $rem11 = last11($phone55);
    $ref = trim((string)$ref);
    $dataConfirmacao = bb_normalizarDataConfirmacao($dataConfirmacao);
    $horaConfirmacao = bb_normalizarHoraConfirmacao($horaConfirmacao);
    $horaMinuto = $horaConfirmacao !== '' ? substr($horaConfirmacao, 0, 5) : '';
    $dataHoraAtual = date('Y-m-d H:i:s');

    $buscarPorFiltro = function(string $filtroExtra, array $paramsExtra) use ($pdo, $dataHoraAtual, $phone55, $rem11): ?array {
        if(columnExists($pdo, 'agendamentos', 'phone')){
            try{
                $sql = "SELECT a.* FROM agendamentos a
                        WHERE a.status = 'Agendado'
                          AND TIMESTAMP(a.data, a.hora) > :agora
                          {$filtroExtra}
                          AND (a.phone = :p55 OR RIGHT(a.phone, 11) = :p11)
                        ORDER BY TIMESTAMP(a.data, a.hora) ASC
                        LIMIT 1";
                $st = $pdo->prepare($sql);
                $st->execute(array_merge([
                    ':agora' => $dataHoraAtual,
                    ':p55' => $phone55,
                    ':p11' => $rem11,
                ], $paramsExtra));
                $agendamento = $st->fetch(PDO::FETCH_ASSOC);
                if($agendamento) return $agendamento;
            }catch(Throwable $e){}
        }

        if(tableExists($pdo, 'clientes')){
            try{
                $sql = "SELECT a.* FROM agendamentos a
                        JOIN clientes c ON c.id = a.cliente
                        WHERE a.status = 'Agendado'
                          AND TIMESTAMP(a.data, a.hora) > :agora
                          {$filtroExtra}
                          AND RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(c.telefone,'(',''),')',''),'-',''),' ',''), 11) = :p11
                        ORDER BY TIMESTAMP(a.data, a.hora) ASC
                        LIMIT 1";
                $st = $pdo->prepare($sql);
                $st->execute(array_merge([
                    ':agora' => $dataHoraAtual,
                    ':p11' => $rem11,
                ], $paramsExtra));
                $agendamento = $st->fetch(PDO::FETCH_ASSOC);
                if($agendamento) return $agendamento;
            }catch(Throwable $e){}
        }

        return null;
    };

    if($ref !== ''){
        $filtroRef = columnExists($pdo, 'agendamentos', 'hash')
            ? " AND (a.`hash` = :ref OR a.`id` = :ref) "
            : " AND a.`id` = :ref ";

        $agendamento = $buscarPorFiltro($filtroRef, [':ref' => $ref]);
        if($agendamento) return $agendamento;

        if(!empty($GLOBALS['LOG'])){
            logLine($GLOBALS['LOG'], "CONFIRMACAO: ref informada nao encontrou agendamento local ref={$ref}; tentando por data/hora/telefone.");
        }
    }

    if($dataConfirmacao !== '' && $horaMinuto !== ''){
        $agendamento = $buscarPorFiltro(
            " AND a.`data` = :data_confirmacao AND TIME_FORMAT(a.`hora`, '%H:%i') = :hora_confirmacao ",
            [
                ':data_confirmacao' => $dataConfirmacao,
                ':hora_confirmacao' => $horaMinuto,
            ]
        );
        if($agendamento) return $agendamento;

        if(!empty($GLOBALS['LOG'])){
            logLine($GLOBALS['LOG'], "CONFIRMACAO: data/hora nao encontrou pendente data={$dataConfirmacao} hora={$horaMinuto}; tentando por telefone.");
        }
    }

    return $buscarPorFiltro('', []);
}

function menuText($nomeSalao, $nomeCliente = ''){
    $nomeSalao   = trim((string)$nomeSalao);
    $nomeCliente = trim((string)$nomeCliente);
    $m  = "💈 *{$nomeSalao}*";
    if($nomeCliente !== ''){
        $m .= ", seja bem vindo *{$nomeCliente}*";
    }
    $m .= "\n\n";
    $m .= "Como posso te ajudar?\n Por favor, escolha uma das opções abaixo: \n\n";
    $m .= bb_opt_line(1, "*Profissionais*");
    $m .= bb_opt_line(2, "*Serviços e preços*");
    $m .= bb_opt_line(3, "*Link agendar*");
    $m .= bb_opt_line(4, "*Falar com humano*\n");
    $m .= "Digite apenas o *número*.";
    return $m;
}

function tableExists(PDO $pdo, $table){
    try{
        $st = $pdo->prepare("SHOW TABLES LIKE :t");
        $st->execute([':t' => $table]);
        return $st->fetchColumn() ? true : false;
    }catch(Exception $e){
        return false;
    }
}

function columnExists(PDO $pdo, $table, $column){
    try{
        $st = $pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :c");
        $st->execute([':c' => $column]);
        return (bool)$st->fetchColumn();
    }catch(Exception $e){
        return false;
    }
}

function firstExistingColumn(PDO $pdo, string $table, array $candidates): string {
    foreach($candidates as $c){
        $c = (string)$c;
        if($c !== '' && columnExists($pdo, $table, $c)) return $c;
    }
    return '';
}

function parseDateToYmd(string $text): string {
    $t = mb_strtolower(trim($text), 'UTF-8');
    if($t === '') return '';
    if($t === 'hoje') return date('Y-m-d');
    if($t === 'amanha' || $t === 'amanhã') return date('Y-m-d', strtotime('+1 day'));
    if(preg_match('/(\d{1,2})[\/\-](\d{1,2})(?:[\/\-](\d{2,4}))?/u', $t, $m)){
        $d  = (int)$m[1];
        $mo = (int)$m[2];
        if(isset($m[3]) && $m[3] !== ''){
            $y = (int)$m[3];
            if($y < 100) $y += 2000;
        }else{
            $y = (int)date('Y');
        }
        if(checkdate($mo, $d, $y)){
            return sprintf('%04d-%02d-%02d', $y, $mo, $d);
        }
    }
    return '';
}

function weekdayPtBr(string $dateYmd): string {
    $n = (int)date('N', strtotime($dateYmd));
    switch($n){
        case 1: return 'Segunda-Feira';
        case 2: return 'Terça-Feira';
        case 3: return 'Quarta-Feira';
        case 4: return 'Quinta-Feira';
        case 5: return 'Sexta-Feira';
        case 6: return 'Sábado';
        case 7: return 'Domingo';
    }
    return '';
}

function isDiaBloqueado(PDO $pdo, int $idProf, string $dateYmd): bool {
    if($idProf <= 0 || $dateYmd === '') return false;
    if(!tableExists($pdo, 'dias_bloqueio')) return false;
    $colData = firstExistingColumn($pdo, 'dias_bloqueio', ['data','dia','date']);
    if($colData === '') return false;
    if(!columnExists($pdo, 'dias_bloqueio', 'funcionario')) return false;
    try{
        $sql = "SELECT 1 FROM `dias_bloqueio` WHERE `funcionario` = :f AND DATE(`{$colData}`) = :d LIMIT 1";
        $st = $pdo->prepare($sql);
        $st->execute([':f' => $idProf, ':d' => $dateYmd]);
        return (bool)$st->fetchColumn();
    }catch(Exception $e){
        return false;
    }
}

function fetchHorariosDisponiveis(PDO $pdo, int $idProf, string $dateYmd): array {
    if($idProf <= 0 || $dateYmd === '') return [];
    if(isDiaBloqueado($pdo, $idProf, $dateYmd)) return [];

    $base = [];
    if(tableExists($pdo, 'horarios')){
        try{
            $st = $pdo->prepare("SELECT DISTINCT TIME(horario) AS horario FROM `horarios` WHERE `funcionario` = :f AND `data` = :d ORDER BY TIME(horario) ASC");
            $st->execute([':f' => $idProf, ':d' => $dateYmd]);
            $base = $st->fetchAll(PDO::FETCH_COLUMN);
        }catch(Exception $e){}
    }
    if(empty($base) && tableExists($pdo, 'horarios')){
        try{
            $st = $pdo->prepare("SELECT DISTINCT TIME(horario) AS horario FROM `horarios` WHERE `funcionario` = :f AND (`data` IS NULL OR `data` = '0000-00-00') ORDER BY TIME(horario) ASC");
            $st->execute([':f' => $idProf]);
            $base = $st->fetchAll(PDO::FETCH_COLUMN);
        }catch(Exception $e){}
    }
    if(empty($base) && tableExists($pdo, 'dias') && tableExists($pdo, 'usuarios')){
        try{
            $dia = weekdayPtBr($dateYmd);
            $stD = $pdo->prepare("SELECT inicio, final, inicio_almoco, final_almoco FROM dias WHERE funcionario = :f AND dia = :dia LIMIT 1");
            $stD->execute([':f' => $idProf, ':dia' => $dia]);
            $row = $stD->fetch(PDO::FETCH_ASSOC);
            if($row){
                $inicio = (string)($row['inicio'] ?? '');
                $final  = (string)($row['final'] ?? '');
                $iniAlm = (string)($row['inicio_almoco'] ?? '');
                $fimAlm = (string)($row['final_almoco'] ?? '');
                if($iniAlm === '00:00:00') $iniAlm = '';
                if($fimAlm === '00:00:00') $fimAlm = '';
                $stI = $pdo->prepare("SELECT intervalo FROM usuarios WHERE id = :id LIMIT 1");
                $stI->execute([':id' => $idProf]);
                $intervalo = (int)$stI->fetchColumn();
                if($intervalo <= 0) $intervalo = 30;
                if($inicio !== '' && $final !== ''){
                    $start = new DateTime($dateYmd.' '.$inicio);
                    $end   = new DateTime($dateYmd.' '.$final);
                    $lStart = null; $lEnd = null;
                    if($iniAlm !== '' && $fimAlm !== ''){
                        $lStart = new DateTime($dateYmd.' '.$iniAlm);
                        $lEnd   = new DateTime($dateYmd.' '.$fimAlm);
                    }
                    $cur = clone $start;
                    while($cur <= $end){
                        if($lStart && $lEnd){
                            if($cur >= $lStart && $cur < $lEnd){
                                $cur->modify("+{$intervalo} minutes");
                                continue;
                            }
                        }
                        $base[] = $cur->format('H:i:s');
                        $cur->modify("+{$intervalo} minutes");
                        if(count($base) > 200) break;
                    }
                    $base = array_values(array_unique($base));
                    sort($base);
                }
            }
        }catch(Exception $e){}
    }
    if(empty($base)) return [];

    $reservados = [];
    if(tableExists($pdo, 'agendamentos')){
        try{
            $sqlRes = "SELECT DISTINCT TIME(hora) as horario FROM `agendamentos` WHERE `funcionario` = :f AND `data` = :d";
            if(columnExists($pdo, 'agendamentos', 'status')){
                $sqlRes .= " AND status != 'Cancelado'";
            }
            $st2 = $pdo->prepare($sqlRes);
            $st2->execute([':f' => $idProf, ':d' => $dateYmd]);
            $reservados = $st2->fetchAll(PDO::FETCH_COLUMN);
            
            if(tableExists($pdo, 'horarios_agd')){
                $st3 = $pdo->prepare("SELECT DISTINCT TIME(horario) FROM horarios_agd WHERE funcionario = :f AND data = :d");
                $st3->execute([':f' => $idProf, ':d' => $dateYmd]);
                $res3 = $st3->fetchAll(PDO::FETCH_COLUMN);
                $reservados = array_merge($reservados, $res3);
            }
            $reservados = array_unique($reservados);
        }catch(Exception $e){
            logLine($GLOBALS['LOG'], "fetchHorariosDisponiveis ERRO RESERVADOS: ".$e->getMessage());
        }
    }
    
    $disp = [];
    foreach($base as $hr){
        if(!in_array($hr, $reservados)){
            $disp[] = $hr;
        }
    }
    if($dateYmd === date('Y-m-d')){
        $agora = date('H:i:s');
        $disp = array_values(array_filter($disp, function($h) use ($agora){
            return (string)$h > $agora;
        }));
    }
    return array_values($disp);
}

function fetchNomeClienteByPhone(PDO $pdo, $phone55){
    if(!tableExists($pdo, 'clientes')) return '';
    $p11 = last11($phone55);
    try{
        $st = $pdo->prepare("SELECT nome FROM clientes WHERE RIGHT(REPLACE(REPLACE(REPLACE(REPLACE(telefone,'(',''),')',''),'-',''),' ',''), 11) = :p11 LIMIT 1");
        $st->execute([':p11' => $p11]);
        return trim((string)$st->fetchColumn());
    }catch(Exception $e){ return ''; }
}

function fetchProfissionais(PDO $pdo){
    if(!tableExists($pdo, 'usuarios')) return [];
    $hasAtendimento = columnExists($pdo, 'usuarios', 'atendimento');
    $hasServicosFunc = tableExists($pdo, 'servicos_func');
    try{
        $sql = "SELECT u.id, u.nome, u.telefone, u.nivel, u.ativo FROM usuarios u WHERE u.ativo = 'Sim' AND u.nivel != 'Administrador'";
        $conds = [];
        if($hasAtendimento) $conds[] = "u.atendimento = 'Sim'";
        if($hasServicosFunc) $conds[] = "EXISTS (SELECT 1 FROM servicos_func sf WHERE sf.funcionario = u.id)";
        $conds[] = "(u.nivel LIKE 'Profissional%' OR u.nivel LIKE 'profissional%' OR u.nivel LIKE '%Cabeleireiro%' OR u.nivel LIKE '%Manicure%' OR u.nivel LIKE '%Hair%')";
        $sql .= " AND (" . implode(" OR ", $conds) . ") ORDER BY u.nome ASC";
        $q = $pdo->query($sql);
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }catch(Exception $e){ return []; }
}

function fetchServicos(PDO $pdo){
    if(!tableExists($pdo, 'servicos')) return [];
    try{
        $q = $pdo->query("SELECT id, nome, valor, ativo FROM servicos WHERE (ativo='Sim' OR ativo=1) ORDER BY nome ASC");
        return $q->fetchAll(PDO::FETCH_ASSOC);
    }catch(Exception $e){ return []; }
}

function fetchProfissionaisByServico(PDO $pdo, int $idServico){
    if($idServico <= 0) return fetchProfissionais($pdo);
    if(!tableExists($pdo, 'servicos_func') || !tableExists($pdo, 'usuarios')) return fetchProfissionais($pdo);
    try{
        $st = $pdo->prepare("SELECT DISTINCT u.id, u.nome, u.telefone, u.nivel, u.ativo FROM usuarios u JOIN servicos_func sf ON sf.funcionario = u.id WHERE u.ativo='Sim' AND sf.servico = :s ORDER BY u.nome ASC");
        $st->execute([':s' => $idServico]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if(!empty($rows)) return $rows;
    }catch(Exception $e){}
    return fetchProfissionais($pdo);
}

function fetchServicosByProf(PDO $pdo, int $idProf){
    if($idProf <= 0) return fetchServicos($pdo);
    if(!tableExists($pdo, 'servicos_func') || !tableExists($pdo, 'servicos')) return fetchServicos($pdo);
    try{
        $st = $pdo->prepare("SELECT DISTINCT s.id, s.nome, s.valor, s.ativo FROM servicos s JOIN servicos_func sf ON sf.servico = s.id WHERE (s.ativo='Sim' OR s.ativo=1) AND sf.funcionario = :f ORDER BY s.nome ASC");
        $st->execute([':f' => $idProf]);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if(!empty($rows)) return $rows;
    }catch(Exception $e){}
    return fetchServicos($pdo);
}

function formatMoneyBR($v){
    if($v === null || $v === '') return '';
    $num = (float)str_replace(',', '.', (string)$v);
    return 'R$ ' . number_format($num, 2, ',', '.');
}

function sendTextViaApiTexto($telefone55, $mensagem, $instanciaOverride = null, $adiarSePossivel = false){
    global $api, $token, $instancia, $LOG, $pdo, $whatsapp_sistema, $DEDUPE_DIR, $DEDUPE_TTL;

    // ✅ telefone sempre em dígitos com 55 quando necessário (evita 5555...)
    $telefone = formatPhone55((string)$telefone55);

    // -------------------------------------------------------------------------
    // Resposta única por webhook (somente para o mesmo remetente do evento)
    // - Se $adiarSePossivel=true, apenas fila (queue) e envia junto com a resposta principal
    // - Se já respondeu nesta requisição, ignora envios adicionais para o mesmo remetente
    // - Se o mesmo evento foi reentregue (retry) em poucos segundos, ignora
    // -------------------------------------------------------------------------
    if (bb_isPeerTarget($telefone)) {

        if ($adiarSePossivel) {
            bb_peerQueueMsg((string)$mensagem);
            logLine($LOG, "SEND-SKIP => queued (adiado) to={$telefone}");
            return;
        }

        if (bb_peerAlreadySent()) {
            logLine($LOG, "SEND-SKIP => already_sent_this_webhook to={$telefone}");
            return;
        }

        $key = (string)($GLOBALS['bb_webhook_key'] ?? '');
        if ($key !== '' && bb_dedupeWasSent((string)$DEDUPE_DIR, $key, $telefone, (int)$DEDUPE_TTL)) {
            logLine($LOG, "SEND-SKIP => dedupe_hit (retry) to={$telefone}");
            bb_peerMarkSent();
            return;
        }
        // Se houver mensagens pendentes (ex.: boas-vindas), mescla agora.
        $mensagem = bb_peerPrependPending((string)$mensagem);
    }


    // padrão do sistema: quebra de linha = %0A
    $mensagem = str_replace(["
", "
", "
"], "%0A", (string)$mensagem);
$instAntiga = $instancia ?? null;
    if(!empty($instanciaOverride)){
        $instancia = $instanciaOverride;
    }

    // ✅ Menuia exige appkey_xxx para envio. Alguns tenants salvam UUID em config/usuarios.token.
    // Tentamos resolver automaticamente na tabela `dispositivos` do tenant atual.
    try{
        $__tokenAntesResolve = $token ?? null;
        $__appKey = resolveMenuiaAppKey(
            $pdo,
            $token ?? null,
            $instancia ?? null,
            $whatsapp_sistema ?? null,
            $LOG
        );
        if(is_string($__appKey) && $__appKey !== ''){
            $token = $__appKey;
            if($__tokenAntesResolve !== $__appKey){
                logLine($LOG, "SEND-DEBUG => token ajustado para appkey via dispositivos (inst=".($instancia ?? 'NULL').")");
            }
        }
    }catch(Throwable $e){
        logLine($LOG, "SEND-DEBUG => falha ao resolver appkey: " . $e->getMessage());
    }

    // ✅ Resolver do api-texto.php (prioriza /config, fallback compat)
// Importante: este gateway roda em /public_html (fora de /sistema). Então buscamos também em /sistema/*.
    $__bb_candidates = [
        __DIR__ . '/../ajax/api-texto.php',
        __DIR__ . '/api-texto.php',
    ];

    $__bb_apiTexto = '';
    foreach ($__bb_candidates as $__bb_try) {
        if (is_file($__bb_try)) { $__bb_apiTexto = $__bb_try; break; }
    }

    // Limpa resultados de chamadas anteriores antes do include.
    $response = null;
    $status_mensagem = '';
    $resp = '';
    $envioOk = false;

    ob_start();

    if (is_file($__bb_apiTexto)) {
        require($__bb_apiTexto);
    } else {
        echo "api-texto.php não encontrado";
    }
    $resp = trim(ob_get_clean());

    // O emissor atual informa sucesso por $status_mensagem.
    if (trim((string)$status_mensagem) !== '') {
        $resp = trim((string)$status_mensagem);
        $envioOk = (mb_stripos($resp, 'sucesso', 0, 'UTF-8') !== false);
    }

    // Compatibilidade com o emissor legado, que preenche $response.
    if($resp === '' && $response !== null){
        if(is_array($response) || is_object($response)){
            $resp = json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }else{
            $resp = trim((string)$response);
        }
    }

    if (!$envioOk && is_array($response)) {
        $statusResposta = isset($response['status']) ? (int)$response['status'] : 0;
        $envioOk = ($statusResposta >= 200 && $statusResposta < 300);
    }

$__bb_lastErr = error_get_last();
    logLine($LOG, "SEND-DEBUG => api_texto_path=" . ($__bb_apiTexto ?: 'NAO_ENCONTRADO') . " resp_len=" . strlen((string)$resp) . " last_error=" . (!empty($__bb_lastErr['message']) ? $__bb_lastErr['message'] : 'NULL'));

    if(!empty($instanciaOverride)){
        $instancia = $instAntiga;
    }

    logLine($LOG, "SEND => to={$telefone} api=".($api ?? 'NULL')." inst=".($instancia ?? 'NULL')." resp=".$resp);

    // Marca envio único somente quando o provedor confirmou o envio.
    // Em caso de falha, não grava dedupe e permite nova tentativa do webhook.
    if (bb_isPeerTarget($telefone) && $envioOk) {
        bb_peerMarkSent();
        $key = (string)($GLOBALS['bb_webhook_key'] ?? '');
        if ($key !== '') {
            bb_dedupeMarkSent((string)$DEDUPE_DIR, $key, $telefone);
        }
    } elseif (bb_isPeerTarget($telefone)) {
        logLine($LOG, "SEND-FAIL => resposta do provedor não confirmou envio to={$telefone}");
    }
}

function sendAndRemember($sessDir, $cliente, $prof, &$sess, $telefone55, $mensagem, $instanciaOverride = null){
    // Se houver mensagens pendentes (ex.: boas-vindas), envia tudo em UMA ÚNICA resposta.
    if (bb_isPeerTarget($telefone55)) {
        $mensagem = bb_peerPrependPending((string)$mensagem);
    }
    sendTextViaApiTexto($telefone55, $mensagem, $instanciaOverride);
    $sess['last_reply_at'] = time();
    saveSession($sessDir, $cliente, $prof, $sess);
}

function openaiResposta($apiKey, $instructions, $userText, $model = 'gpt-4o-mini'){
    $apiKey = trim((string)$apiKey);
    if($apiKey === '') return '';
    $payload = ["model" => $model, "instructions" => (string)$instructions, "input" => (string)$userText, "store" => false];
    $ch = curl_init("https://api.openai.com/v1/responses");
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true, CURLOPT_POST => true,
        CURLOPT_HTTPHEADER => ["Authorization: Bearer {$apiKey}", "Content-Type: application/json"],
        CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE), CURLOPT_TIMEOUT => 20,
    ]);
    $resp = curl_exec($ch);
    $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if($http < 200 || $http >= 300 || !$resp) return '';
    $data = json_decode($resp, true);
    if(!is_array($data)) return '';
    if(!empty($data['output_text'])) return trim((string)$data['output_text']);
    $out = [];
    if(!empty($data['output']) && is_array($data['output'])){
        foreach($data['output'] as $item){
            if(!empty($item['content']) && is_array($item['content'])){
                foreach($item['content'] as $c){
                    if(isset($c['text'])) $out[] = (string)$c['text'];
                }
            }
        }
    }
    return trim(implode("\n", $out));
}

function ensureCliente(PDO $pdo, string $phone55, string $nome = ''): int {
    if(!tableExists($pdo, 'clientes')) return 0;
    $phone55 = formatPhone55($phone55);
    $nome = trim((string)$nome) !== '' ? trim((string)$nome) : 'Cliente WhatsApp';
    $keys = phoneKeyCandidates($phone55);
    $expr = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefone,'+',''),'(',''),')',''),'-',''),' ',''),'.','')";
    try{
        $conds = []; $params = [];
        if(isset($keys[11]) && $keys[11] !== ''){ $conds[] = "RIGHT($expr, 11) = :k11"; $params[':k11'] = $keys[11]; }
        if(isset($keys[10]) && $keys[10] !== ''){ $conds[] = "RIGHT($expr, 10) = :k10"; $params[':k10'] = $keys[10]; }
        if(!empty($conds)){
            $sql = "SELECT id FROM clientes WHERE (" . implode(" OR ", $conds) . ") LIMIT 1";
            $st = $pdo->prepare($sql); $st->execute($params);
            $id = (int)$st->fetchColumn(); if($id > 0) return $id;
        }
    }catch(Throwable $e){}

    $telLocal = onlyDigits($phone55);
    $telLocal = preg_replace('/^55/', '', $telLocal);
    $telLocal = (strlen($telLocal) > 11) ? substr($telLocal, -11) : $telLocal;
    $telefoneDb = $telLocal;
    if(strlen($telefoneDb) === 11){
        $ddd = substr($telefoneDb, 0, 2); $n1 = substr($telefoneDb, 2, 5); $n2 = substr($telefoneDb, 7, 4);
        $telefoneDb = "({$ddd}) {$n1}-{$n2}";
    }elseif(strlen($telefoneDb) === 10){
        $ddd = substr($telefoneDb, 0, 2); $n1 = substr($telefoneDb, 2, 4); $n2 = substr($telefoneDb, 6, 4);
        $telefoneDb = "({$ddd}) {$n1}-{$n2}";
    }
    $senha = '123'; $senha_crip = password_hash($senha, PASSWORD_DEFAULT);
    try{
        $set = []; $par = [];
        if(columnExists($pdo, 'clientes', 'nome')){ $set[] = 'nome = :nome'; $par[':nome'] = $nome; }
        if(columnExists($pdo, 'clientes', 'telefone')){ $set[] = 'telefone = :telefone'; $par[':telefone'] = $telefoneDb; }
        if(columnExists($pdo, 'clientes', 'data_cad')){ $set[] = 'data_cad = CURDATE()'; }
        if(columnExists($pdo, 'clientes', 'cartoes')){ $set[] = "cartoes = '0'"; }
        if(columnExists($pdo, 'clientes', 'alertado')){ $set[] = "alertado = 'Não'"; }
        if(columnExists($pdo, 'clientes', 'ativo')){ $set[] = "ativo = 'Sim'"; }
        if(columnExists($pdo, 'clientes', 'senha_crip')){ $set[] = 'senha_crip = :senha_crip'; $par[':senha_crip'] = $senha_crip; }
        if(empty($set)) return 0;
        $sql = 'INSERT INTO clientes SET ' . implode(', ', $set);
        $st = $pdo->prepare($sql); $st->execute($par);
        $idNovo = (int)$pdo->lastInsertId(); if($idNovo <= 0) return 0;

        $cfgNome = $GLOBALS['nomeSalao'] ?? 'Sistema';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        $url_sistema = $host ? rtrim("https://{$host}", '/') : '';
        $msg  = "👋 *Olá {$nome}*, seja bem-vindo(a) ao *{$cfgNome}*!\n\n";
        $msg .= "🔒 *Senha de acesso:* {$senha}\n\n";
        if($url_sistema){
            $slugPainel = trim((string)($GLOBALS['tenantSlug'] ?? ''));
            if($slugPainel !== ''){
                $msg .= "🌐 *Acessar painel do cliente:*\n{$url_sistema}/loja/{$slugPainel}/sistema/acesso\n\n";
            }else{
                $msg .= "🌐 *Acessar painel do cliente:*\n{$url_sistema}/sistema/acesso\n\n";
            }
        }
        $msg .= "Você já pode agendar normalmente.";
        sendTextViaApiTexto(formatPhone55($phone55), $msg, $GLOBALS['instanciaEvento'] ?? null, true);
        return $idNovo;
    }catch(Exception $e){ return 0; }
}

function criarAgendamento(PDO $pdo, int $idCliente, int $idProf, int $idServico, string $dateYmd, string $hora, string $phone55): int {
    if($idCliente <= 0 || $idProf <= 0 || $dateYmd === '' || $hora === '') return 0;
    if(!tableExists($pdo, 'agendamentos')) return 0;
    
    // REMOVIDA TRAVA DE SEGURANÇA QUE ESTAVA BLOQUEANDO A INSERÇÃO
    
    $cols = []; $vals = []; $par = [];
    if(columnExists($pdo, 'agendamentos', 'cliente')){ $cols[]='cliente'; $vals[]=':cliente'; $par[':cliente']=$idCliente; } else return 0;
    if(columnExists($pdo, 'agendamentos', 'funcionario')){ $cols[]='funcionario'; $vals[]=':funcionario'; $par[':funcionario']=$idProf; }
    if(columnExists($pdo, 'agendamentos', 'data')){ $cols[]='data'; $vals[]=':data'; $par[':data']=$dateYmd; }
    if(columnExists($pdo, 'agendamentos', 'hora')){ $cols[]='hora'; $vals[]=':hora'; $par[':hora']=$hora; }
    $colServico = firstExistingColumn($pdo, 'agendamentos', ['servico','servico_id','id_servico']);
    if($colServico !== '' && $idServico > 0){ $cols[]=$colServico; $vals[]=':servico'; $par[':servico']=$idServico; }
    if(columnExists($pdo, 'agendamentos', 'status')){ $cols[]='status'; $vals[]=':status'; $par[':status']='Agendado'; }
    if(columnExists($pdo, 'agendamentos', 'hash')){ $hash = bin2hex(random_bytes(12)); $cols[]='hash'; $vals[]=':hash'; $par[':hash']=$hash; }
    if(columnExists($pdo, 'agendamentos', 'phone')){ $cols[]='phone'; $vals[]=':phone'; $par[':phone']=$phone55; }
    
    if(columnExists($pdo, 'agendamentos', 'data_lanc')){
        $cols[]='data_lanc'; $vals[]=':data_lanc'; $par[':data_lanc']=date('Y-m-d');
    }
    if(columnExists($pdo, 'agendamentos', 'usuario')){
        $cols[]='usuario'; $vals[]=':usuario'; $par[':usuario']=0; 
    }
    if(columnExists($pdo, 'agendamentos', 'obs')){
        $cols[]='obs'; $vals[]=':obs'; $par[':obs']='Agendado via WhatsApp';
    }

    try{
        $sql = "INSERT INTO agendamentos (".implode(',', $cols).") VALUES (".implode(',', $vals).")";
        $st = $pdo->prepare($sql); $st->execute($par);
        $idAg = (int)$pdo->lastInsertId(); if($idAg <= 0) return 0;
        
        // =============================================================
        // LÓGICA DE AGENDAMENTO DE CONFIRMAÇÃO (IDÊNTICA AO SISTEMA)
        // =============================================================
        
        $ult_id = $idAg;
        $nome_func = $pdo->query("SELECT nome FROM usuarios WHERE id = $idProf")->fetchColumn();
        $userRow = $pdo->query("SELECT intervalo FROM usuarios WHERE id = $idProf")->fetch(PDO::FETCH_ASSOC);
        $intervalo = $userRow['intervalo'] ?? 30;

        $servRow = $pdo->query("SELECT nome, tempo FROM servicos WHERE id = $idServico")->fetch(PDO::FETCH_ASSOC);
        $nome_serv = $servRow['nome'] ?? '';
        $tempo = $servRow['tempo'] ?? 30;

        $dataF = date('d/m/Y', strtotime($dateYmd));
        $horaF = date('H:i', strtotime($hora));
        $tel_cli = $phone55;

        // Pega minutos_aviso do config (mesma lógica do arquivo de pagamento)
        $conf = $pdo->query("SELECT * FROM config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $minutos_aviso = $conf['minutos_aviso'] ?? ($conf['dias_aviso'] ?? 1); 

        // Formata telefone (variável usada no confirmacao.php)
        $telefone = '55'.preg_replace('/[ ()-]+/' , '' , $tel_cli);
        
        // Variáveis necessárias para o confirmacao.php
        $hora_atual = date('H:i:s');
        $data_atual = date('Y-m-d');
        $hora_minutos = @strtotime("-$minutos_aviso hours", @strtotime($hora));
        $nova_hora = date('H:i:s', $hora_minutos);
        
        $mensagem = "*Confirmação de Agendamento*\n";
        $mensagem .= "Profissional: *{$nome_func}*\n";
        $mensagem .= "Serviço: *{$nome_serv}*\n";
        $mensagem .= "Data: *{$dataF}*\n";
        $mensagem .= "Hora: *{$horaF}*\n";
        $mensagem .= "_(Digite o número com a opção desejada)_\n";
        $mensagem .= "1. 1️⃣ para confirmar ✅\n";		
        $mensagem .= "2. 2️⃣ para Cancelar ❌\n";		
        
        $id_envio = "{$ult_id}";
        $data_envio = "{$dateYmd} {$hora}";		
        $data_agd = $dateYmd;
        $hora_do_agd = $hora;
        
        // CHAMA O ARQUIVO QUE AGENDA O DISPARO
        if(file_exists(__DIR__ . '/../ajax/confirmacao.php')){
            require(__DIR__ . '/../ajax/confirmacao.php');
            if(isset($id)){
                $id_hash = $id;
                $pdo->query("UPDATE agendamentos SET hash = '$id_hash' WHERE id = '$ult_id'");
            }
        }
        
        // =============================================================

        if(tableExists($pdo, 'horarios_agd')){
            $hora_final_servico = date('H:i:s', strtotime("+$tempo minutes", strtotime($hora)));
            $horaLoop = $hora;
            
            while(strtotime($horaLoop) < strtotime($hora_final_servico)){
                $hora_minutos = strtotime("+$intervalo minutes", strtotime($horaLoop));			
                $horaLoop = date('H:i:s', $hora_minutos);

                if(strtotime($horaLoop) < strtotime($hora_final_servico)){
                    $pdo->query("INSERT INTO horarios_agd SET agendamento = '$ult_id', horario = '$horaLoop', funcionario = '$idProf', data = '$dateYmd'");
                }
            }
        }
        return $idAg;
    }catch(Exception $e){ return 0; }
}

function isConfirmationPrompt(string $textLower): bool {
    $t = mb_strtolower(trim($textLower), 'UTF-8');
    if($t === '') return false;
    $hasConfirm = (mb_stripos($t, 'confirm', 0, 'UTF-8') !== false);
    $has1 = (preg_match('/\b1\b/u', $t) || mb_stripos($t, '1', 0, 'UTF-8') !== false);
    $has2 = (preg_match('/\b2\b/u', $t) || mb_stripos($t, '2', 0, 'UTF-8') !== false);
    $hasCancel = (mb_stripos($t, 'cancel', 0, 'UTF-8') !== false) || (mb_stripos($t, 'cance', 0, 'UTF-8') !== false);
    return ($hasConfirm && $has1 && $has2 && $hasCancel);
}

function isLikelyBotEcho(string $msgLower): bool {
    $t = preg_replace('/\s+/', ' ', trim($msgLower));
    $t = str_replace(['*', '_', '`'], '', $t);
    $needles = ['eu sou o barberbot','como posso te ajudar','horários disponíveis','serviços deste profissional','profissionais:','perfeito! agora me diga a data','digite o número do horário','digite o número do serviço','digite apenas o número','digite menu para ver as opções','bot pausado nesta conversa','certo! pode enviar sua mensagem','para agendar, acesse o link'];
    foreach($needles as $n){ if($n !== '' && strpos($t, $n) !== false) return true; }
    return false;
}

function phoneKeyCandidates($phone): array {
    if ($phone === null) return [];
    // evita fatal em PHP 8 quando algum evento chega sem remetente/destinatario
    // e algum fluxo chama esta função com NULL.
    $d = onlyDigits($phone); if ($d === '') return [];
    if (strpos($d, '55') === 0) $d = substr($d, 2);
    if (strlen($d) > 11) $d = substr($d, -11);
    $out = []; $len = strlen($d);
    if ($len === 10) { $out[10] = $d; $ddd = substr($d, 0, 2); $num = substr($d, 2); $out[11] = $ddd . '9' . $num; }
    if ($len === 11) { $out[11] = $d; $ddd = substr($d, 0, 2); $terceiro = substr($d, 2, 1); if ($terceiro === '9') { $out[10] = $ddd . substr($d, 3); } }
    if ($len < 10 && $len > 0) $out[$len] = $d;
    if (strlen($d) >= 11) $out[11] = substr($d, -11);
    if (strlen($d) >= 10) $out[10] = substr($d, -10);
    $clean = []; foreach($out as $k => $v){ $v = trim((string)$v); if($v !== '') $clean[(int)$k] = $v; }
    return $clean;
}

function isTelefoneUsuario(PDO $pdo, string $phone55): bool {
    if($phone55 === '' || !tableExists($pdo, 'usuarios')) return false;
    $keys = phoneKeyCandidates($phone55); if(empty($keys)) return false;
    $expr = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefone,'+',''),'(',''),')',''),'-',''),' ',''),'.','')";
    $conds = []; $params = [];
    if(isset($keys[11])){ $conds[] = "RIGHT($expr, 11) = :k11"; $params[':k11'] = $keys[11]; }
    if(isset($keys[10])){ $conds[] = "RIGHT($expr, 10) = :k10"; $params[':k10'] = $keys[10]; }
    if(empty($conds)) return false;
    try{
        $sql = "SELECT 1 FROM usuarios WHERE ativo='Sim' AND (" . implode(" OR ", $conds) . ") LIMIT 1";
        $st = $pdo->prepare($sql); $st->execute($params); return (bool)$st->fetchColumn();
    }catch(Exception $e){ return false; }
}

function isAtendimentoSim($v): bool {
    $s = strtolower(trim((string)$v));
    return in_array($s, ['sim','1','true','yes','y'], true);
}

function fetchUsuarioByTelefone(PDO $pdo, string $phone55): ?array {
    if($phone55 === '' || !tableExists($pdo, 'usuarios')) return null;
    $keys = phoneKeyCandidates($phone55); if(empty($keys)) return null;

    $expr = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefone,'(',''),')',''),'-',''),' ',''),'+','')";
    $conds = []; $params = [];
    if(isset($keys[11])){ $conds[] = "RIGHT($expr, 11) = :k11"; $params[':k11'] = $keys[11]; }
    if(isset($keys[10])){ $conds[] = "RIGHT($expr, 10) = :k10"; $params[':k10'] = $keys[10]; }
    if(empty($conds)) return null;

    try{
        // ⚠️ Pode existir telefone duplicado (ex.: Admin + Profissional com mesmo número).
        // Por isso buscamos vários candidatos e escolhemos por score (token/appkey + atendimento + ativo).
        $sql = "SELECT * FROM usuarios WHERE (" . implode(" OR ", $conds) . ") ORDER BY id DESC LIMIT 20";
        $st = $pdo->prepare($sql);
        $st->execute($params);
        $rows = $st->fetchAll(PDO::FETCH_ASSOC);
        if(!$rows) return null;

        $best = null;
        $bestScore = -999999;
        $k11 = isset($keys[11]) ? (string)$keys[11] : '';
        $k10 = isset($keys[10]) ? (string)$keys[10] : '';

        foreach($rows as $r){
            $score = 0;

            $telDigits = onlyDigits((string)($r['telefone'] ?? ''));
            if($k11 !== '' && $telDigits !== '' && substr($telDigits, -11) === $k11) $score += 500;
            if($k10 !== '' && $telDigits !== '' && substr($telDigits, -10) === $k10) $score += 400;

            $ativo = strtolower(trim((string)($r['ativo'] ?? '')));
            if($ativo === 'sim' || $ativo === '1' || $ativo === 'true' || $ativo === 'yes'){
                $score += 200;
            }

            $tokenRow = trim((string)($r['token'] ?? ''));
            if($tokenRow !== ''){
                $score += 80; // token preenchido já é bom sinal
                if(function_exists('menuiaLooksLikeAppCredential') && menuiaLooksLikeAppCredential($tokenRow)){
                    $score += 180; // appkey/token com cara de credencial Menuia
                }
            }

            if(isAtendimentoSim($r['atendimento'] ?? null)){
                $score += 120;
            }

            if(trim((string)($r['instancia'] ?? '')) !== ''){
                $score += 60;
            }

            $nivel = strtolower(trim((string)($r['nivel'] ?? '')));
            if($nivel !== '' && strpos($nivel, 'admin') !== false){
                $score -= 30; // leve desempate contra perfil administrativo quando há profissional real
            }

            if(isset($r['id']) && is_numeric($r['id'])){
                $score += min(20, (int)$r['id'] % 20); // desempate estável
            }

            if($best === null || $score > $bestScore){
                $best = $r;
                $bestScore = $score;
            }
        }

        if(!empty($GLOBALS['LOG']) && count($rows) > 1){
            $ids = [];
            foreach($rows as $x){ if(isset($x['id'])) $ids[] = (string)$x['id']; }
            logLine($GLOBALS['LOG'], "CTX usuarios AVISO: telefone duplicado em usuarios (tel=" . formatPhone55($phone55) . ", candidatos=" . count($rows) . ", ids=" . implode(',', $ids) . "). Escolhido id=" . (($best['id'] ?? 'NULL')) . " por score.");
        }

        return $best ?: null;
    }catch(Throwable $e){
        if(!empty($GLOBALS['LOG'])){
            logLine($GLOBALS['LOG'], "CTX usuarios ERRO fetchUsuarioByTelefone: " . $e->getMessage());
        }
        return null;
    }
}

function fetchUsuarioContext(PDO $pdo, $instanciaEvento, string $destinatario55): ?array {
    if(!tableExists($pdo, 'usuarios')) return null;

    $inst = trim((string)$instanciaEvento);
    $destinatario55 = formatPhone55($destinatario55);

    // ✅ REGRA ESTRITA: prioriza SEMPRE o telefone do BOT (destinatário do evento).
    // Isso evita cruzamento quando vários profissionais compartilham a mesma instancia.
    if($destinatario55 !== ''){
        try{
            $uByPhone = fetchUsuarioByTelefone($pdo, $destinatario55);
            if(is_array($uByPhone) && !empty($uByPhone)){
                // Se o provedor também enviou instancia, apenas registra divergência (não troca o usuário pelo campo instancia)
                if($inst !== '' && isset($uByPhone['instancia']) && trim((string)$uByPhone['instancia']) !== '' && trim((string)$uByPhone['instancia']) !== $inst){
                    if(!empty($GLOBALS['LOG'])){
                        logLine($GLOBALS['LOG'], "CTX usuarios AVISO: telefone do bot bateu, mas instancia diverge (tel=" . formatPhone55($uByPhone['telefone'] ?? '') . " db_inst=" . trim((string)$uByPhone['instancia']) . " evt_inst=" . $inst . ")");
                    }
                }
                return $uByPhone;
            }
        }catch(Throwable $e){}
    }

    // Fallback por instancia SOMENTE se for única (evita pegar profissional errado)
    if($inst !== '' && columnExists($pdo, 'usuarios', 'instancia')){
        try{
            $stC = $pdo->prepare("SELECT COUNT(*) FROM usuarios WHERE ativo='Sim' AND instancia = :i AND instancia IS NOT NULL AND instancia<>''");
            $stC->execute([':i' => $inst]);
            $qtd = (int)$stC->fetchColumn();

            if($qtd === 1){
                $st = $pdo->prepare("SELECT * FROM usuarios WHERE ativo='Sim' AND instancia = :i AND instancia IS NOT NULL AND instancia<>'' LIMIT 1");
                $st->execute([':i' => $inst]);
                $u = $st->fetch(PDO::FETCH_ASSOC);
                if(is_array($u) && !empty($u)) return $u;
            }elseif($qtd > 1){
                if(!empty($GLOBALS['LOG'])){
                    logLine($GLOBALS['LOG'], "CTX usuarios BLOQUEADO: instancia duplicada em usuarios (inst={$inst}, qtd={$qtd}). Aguardando resolução por telefone do bot.");
                }
                return null;
            }
        }catch(Throwable $e){}
    }

    return null;
}

/**
 * ==========================================================
 *  MARKETPLACE MULTI-TENANT (POR BANCO) - ROTEAMENTO RÁPIDO
 *  ----------------------------------------------------------
 *  Regra: token/instancia do profissional SEMPRE vem do banco
 *  do SALÃO (tabela usuarios). O jctecbr_market é usado apenas
 *  como diretório/listagem de bancos (quando permitido).
 * ==========================================================
 */

function dbNameToSlug(string $dbName): string {
    $dbName = trim($dbName);
    if($dbName === '') return '';
    $dbName = preg_replace('/\s+/', '', $dbName);
    if(strpos($dbName, 'jctecbr_') === 0){
        return substr($dbName, strlen('jctecbr_'));
    }
    return $dbName;
}

function pdoUseDb(PDO $pdo, string $dbName): bool {
    $dbName = trim($dbName);
    if($dbName === '') return false;
    $safe = str_replace('`','``',$dbName);
    $pdo->exec("USE `{$safe}`");
    return true;
}

function pdoCurrentDb(PDO $pdo): string {
    try{
        $db = $pdo->query("SELECT DATABASE()")->fetchColumn();
        return $db ? (string)$db : '';
    }catch(Exception $e){
        return '';
    }
}


function bb_gateway_clean_identifier(string $valor, string $padrao = ''): string {
    $valor = preg_replace('/[^a-zA-Z0-9_]/', '', trim($valor));
    return $valor !== '' ? $valor : $padrao;
}

function bb_gateway_quote_columns(array $colunas): array {
    $saida = [];

    foreach ($colunas as $coluna) {
        $coluna = bb_gateway_clean_identifier((string)$coluna);
        if ($coluna !== '') {
            $saida[] = "`{$coluna}`";
        }
    }

    return $saida;
}

function bb_gateway_load_marketplace_config(): array {
    $arquivo = __DIR__ . '/../config/marketplace.php';
    if (!is_file($arquivo)) {
        return [];
    }

    $cfg = require $arquivo;
    return is_array($cfg) ? $cfg : [];
}

function bb_gateway_market_pdo(): ?PDO {
    $cfg = bb_gateway_load_marketplace_config();
    $master = (isset($cfg['master']) && is_array($cfg['master'])) ? $cfg['master'] : [];

    $host = trim((string)($master['host'] ?? 'localhost'));
    $db   = trim((string)($master['db'] ?? 'jctecbr_market'));
    $user = trim((string)($master['user'] ?? ''));
    $pass = (string)($master['pass'] ?? '');

    if ($host === '' || $db === '' || $user === '') {
        return null;
    }

    try {
        return new PDO(
            "mysql:host={$host};dbname={$db};charset=utf8mb4",
            $user,
            $pass,
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_PERSISTENT         => false,
            ]
        );
    } catch (Throwable $e) {
        if (!empty($GLOBALS['LOG'])) {
            logLine($GLOBALS['LOG'], 'MARKET-PDO: falha ao conectar no banco master do marketplace: ' . $e->getMessage());
        }
        return null;
    }
}

function bb_gateway_market_table(): string {
    $cfg = bb_gateway_load_marketplace_config();
    $master = (isset($cfg['master']) && is_array($cfg['master'])) ? $cfg['master'] : [];
    return bb_gateway_clean_identifier((string)($master['table'] ?? 'tenants'), 'tenants');
}

function bb_gateway_column_exists(PDO $pdo, string $tabela, string $coluna): bool {
    $tabela = bb_gateway_clean_identifier($tabela);
    $coluna = trim($coluna);

    if ($tabela === '' || $coluna === '') {
        return false;
    }

    try {
        $st = $pdo->prepare("SHOW COLUMNS FROM `{$tabela}` LIKE :coluna");
        $st->execute([':coluna' => $coluna]);
        return (bool)$st->fetch(PDO::FETCH_ASSOC);
    } catch (Throwable $e) {
        return false;
    }
}

function bb_gateway_table_exists(PDO $pdo, string $tabela): bool {
    $tabela = trim($tabela);
    if ($tabela === '') {
        return false;
    }

    try {
        $st = $pdo->prepare("SHOW TABLES LIKE :tabela");
        $st->execute([':tabela' => $tabela]);
        return (bool)$st->fetchColumn();
    } catch (Throwable $e) {
        return false;
    }
}

function bb_gateway_normalize_timezone($timezone): string {
    $timezone = trim((string)$timezone);
    $cfg = bb_gateway_load_marketplace_config();

    $padrao = 'America/Sao_Paulo';
    if (isset($cfg['timezone']) && is_array($cfg['timezone']) && !empty($cfg['timezone']['default'])) {
        $padrao = trim((string)$cfg['timezone']['default']);
    }

    $permitidos = [];
    if (isset($cfg['timezone']) && is_array($cfg['timezone']) && isset($cfg['timezone']['allowed']) && is_array($cfg['timezone']['allowed'])) {
        foreach ($cfg['timezone']['allowed'] as $item) {
            $item = trim((string)$item);
            if ($item !== '') {
                $permitidos[] = $item;
            }
        }
    }

    if ($timezone === '') {
        return $padrao;
    }

    if (!empty($permitidos) && !in_array($timezone, $permitidos, true)) {
        return $padrao;
    }

    if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
        return $padrao;
    }

    return $timezone;
}

function bb_gateway_apply_timezone($timezone, string $logFile = ''): string {
    $timezoneFinal = bb_gateway_normalize_timezone($timezone);

    try {
        date_default_timezone_set($timezoneFinal);
    } catch (Throwable $e) {
        $timezoneFinal = 'America/Sao_Paulo';
        date_default_timezone_set($timezoneFinal);
    }

    if ($logFile !== '') {
        logLine($logFile, 'TIMEZONE: aplicado no webhook=' . $timezoneFinal);
    }

    return $timezoneFinal;
}

function bb_gateway_tenant_db_pronto(PDO $pdo, string $dbName, string $logFile = ''): bool {
    $dbName = trim($dbName);
    if ($dbName === '') {
        return false;
    }

    try {
        pdoUseDb($pdo, $dbName);
    } catch (Throwable $e) {
        if ($logFile !== '') {
            logLine($logFile, 'TENANT-ROUTE: banco inacessivel db=' . $dbName . ' erro=' . $e->getMessage());
        }
        return false;
    }

    $temConfig = tableExists($pdo, 'config');
    $temUsuarios = tableExists($pdo, 'usuarios');

    if (!$temConfig || !$temUsuarios) {
        if ($logFile !== '') {
            logLine($logFile, 'TENANT-ROUTE: banco ignorado por estrutura incompleta db=' . $dbName . ' config=' . ($temConfig ? 'sim' : 'nao') . ' usuarios=' . ($temUsuarios ? 'sim' : 'nao'));
        }
        return false;
    }

    return true;
}

function bb_gateway_find_tenant_market_com_pdo(PDO $pdoMarket, string $bot55, string $logFile = ''): ?array {
    $bot55 = formatPhone55($bot55);
    if ($bot55 === '') {
        return null;
    }

    $tabela = bb_gateway_market_table();
    if (!bb_gateway_table_exists($pdoMarket, $tabela)) {
        return null;
    }

    $botDigits = onlyDigits($bot55);
    if (strlen($botDigits) === 11) {
        $botDigits = '55' . $botDigits;
    }

    $keys = phoneKeyCandidates($bot55);

    $temDbName = bb_gateway_column_exists($pdoMarket, $tabela, 'db_name');
    if (!$temDbName) {
        return null;
    }

    $select = ['db_name'];
    foreach (['slug', 'timezone', 'whatsapp_norm', 'whatsapp', 'telefone_whatsapp', 'telefone', 'phone', 'numero', 'bot', 'numero_bot', 'whatsapp_bot', 'telefone_bot', 'menuia_numero', 'instancia'] as $col) {
        if (bb_gateway_column_exists($pdoMarket, $tabela, $col)) {
            $select[] = $col;
        }
    }

    $statusWhere = [];
    if (bb_gateway_column_exists($pdoMarket, $tabela, 'ativo')) {
        $statusWhere[] = 'ativo = 1';
    }
    if (bb_gateway_column_exists($pdoMarket, $tabela, 'status')) {
        $statusWhere[] = "(status IN ('active','ativo','Ativo','ATIVO','1') OR status IS NULL OR status = '')";
    }

    $whereBase = ["db_name IS NOT NULL", "db_name <> ''"];
    if (!empty($statusWhere)) {
        $whereBase[] = '(' . implode(' OR ', $statusWhere) . ')';
    }

    $consultas = [];

    if (bb_gateway_column_exists($pdoMarket, $tabela, 'whatsapp_norm') && $botDigits !== '' && (strlen($botDigits) === 12 || strlen($botDigits) === 13)) {
        $consultas[] = [
            'tag' => 'whatsapp_norm',
            'sql' => "SELECT " . implode(', ', bb_gateway_quote_columns(array_unique($select))) . " FROM `{$tabela}` WHERE " . implode(' AND ', $whereBase) . " AND whatsapp_norm = :bot LIMIT 1",
            'params' => [':bot' => $botDigits],
        ];
    }

    $colunasTelefone = [];
    foreach (['whatsapp', 'telefone_whatsapp', 'telefone', 'phone', 'numero', 'bot', 'numero_bot', 'whatsapp_bot', 'telefone_bot', 'menuia_numero', 'instancia'] as $col) {
        if (bb_gateway_column_exists($pdoMarket, $tabela, $col)) {
            $colunasTelefone[] = $col;
        }
    }

    foreach ($colunasTelefone as $col) {
        $expr = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(`{$col}`,'+',''),'(',''),')',''),'-',''),' ',''),'.','')";
        $conds = [];
        $params = [];

        if (isset($keys[11])) {
            $conds[] = "RIGHT({$expr}, 11) = :k11";
            $params[':k11'] = $keys[11];
        }
        if (isset($keys[10])) {
            $conds[] = "RIGHT({$expr}, 10) = :k10";
            $params[':k10'] = $keys[10];
        }

        if (!empty($conds)) {
            $consultas[] = [
                'tag' => $col,
                'sql' => "SELECT " . implode(', ', bb_gateway_quote_columns(array_unique($select))) . " FROM `{$tabela}` WHERE " . implode(' AND ', $whereBase) . " AND (" . implode(' OR ', $conds) . ") LIMIT 1",
                'params' => $params,
            ];
        }
    }

    foreach ($consultas as $consulta) {
        try {
            $st = $pdoMarket->prepare($consulta['sql']);
            $st->execute($consulta['params']);
            $tenant = $st->fetch(PDO::FETCH_ASSOC);

            if (is_array($tenant) && trim((string)($tenant['db_name'] ?? '')) !== '') {
                $dbName = trim((string)$tenant['db_name']);
                $slug = trim((string)($tenant['slug'] ?? ''));
                if ($slug === '') {
                    if ($logFile !== '') {
                        logLine($logFile, "TENANT-ROUTE: registro global sem slug para bot={$bot55} db={$dbName}; roteamento bloqueado.");
                    }
                    continue;
                }

                $timezone = bb_gateway_normalize_timezone($tenant['timezone'] ?? '');

                if ($logFile !== '') {
                    logLine($logFile, "TENANT-ROUTE: market encontrou por {$consulta['tag']} bot={$bot55} db={$dbName} slug={$slug} timezone={$timezone}");
                }

                return [
                    'db_name'  => $dbName,
                    'slug'     => $slug,
                    'timezone' => $timezone,
                ];
            }
        } catch (Throwable $e) {
            if ($logFile !== '') {
                logLine($logFile, "TENANT-ROUTE: erro consulta market {$consulta['tag']}: " . $e->getMessage());
            }
        }
    }

    return null;
}


function cacheReadJson(string $file, int $ttlSec): ?array {
    if(!file_exists($file)) return null;
    $raw = @file_get_contents($file);
    if(!$raw) return null;
    $j = @json_decode($raw, true);
    if(!is_array($j)) return null;
    $ts = (int)($j['ts'] ?? 0);
    if($ts > 0 && (time() - $ts) > $ttlSec) return null;
    return $j;
}

function cacheWriteJson(string $file, array $data): void {
    $data['ts'] = time();
    @file_put_contents($file, json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
}


function routeCacheFile(string $sessDir, string $bot55, ?string $instanciaEvento): string {
    // ✅ Evita cruzar lojas quando o 9º dígito muda e quando existem múltiplas instâncias:
    $d = onlyDigits($bot55);
    if (strpos($d, '55') === 0) $d = substr($d, 2);
    if (strlen($d) > 11) $d = substr($d, -11);

    if (strlen($d) === 11 || strlen($d) === 10){
        $key = $d;
    }else{
        $key = phoneKeyCanonical10($bot55);
    }

    $inst = trim((string)$instanciaEvento);
    $instKey = ($inst !== '') ? substr(sha1($inst), 0, 10) : 'noinst';

    return rtrim($sessDir,'/') . '/route_' . $key . '_' . $instKey . '.json';
}

function listTenantDbs(PDO $pdo, string $sessDir, string $logFile, bool $forcarAtualizacao = false): array {
    // cache lista de bancos por 10 minutos
    $cacheFile = rtrim($sessDir,'/') . '/tenant_dblist.json';
    $cached = $forcarAtualizacao ? null : cacheReadJson($cacheFile, 600);
    if(is_array($cached) && !empty($cached['dbs']) && is_array($cached['dbs'])){
        return array_values(array_unique(array_filter($cached['dbs'])));
    }

    if ($forcarAtualizacao && is_file($cacheFile)) {
        @unlink($cacheFile);
    }

    $dbs = [];

    // 1) tenta via jctecbr_market.tenants (mais rápido)
    $cur = pdoCurrentDb($pdo);
    try{
        pdoUseDb($pdo, 'jctecbr_market');
        try{
            $st = $pdo->query("SELECT DISTINCT db_name FROM tenants WHERE db_name IS NOT NULL AND db_name<>''");
        }catch(Exception $e1){
            $st = $pdo->query("SELECT DISTINCT db_name FROM tenants");
        }
        while($r = $st->fetch(PDO::FETCH_ASSOC)){
            if(isset($r['db_name']) && trim((string)$r['db_name']) !== ''){
                $dbs[] = trim((string)$r['db_name']);
            }
        }
    }catch(Exception $e){
        logLine($logFile, "TENANT-ROUTE: falha ao listar tenants do market: " . $e->getMessage());

// ✅ Fallback profissional: consulta o MARKET usando a credencial 'master' do /config/marketplace.php
try{
    $__mpFile = __DIR__ . '/../config/marketplace.php';
    $__mpCfg  = is_file($__mpFile) ? require $__mpFile : [];
    $__m      = (is_array($__mpCfg) && isset($__mpCfg['master']) && is_array($__mpCfg['master'])) ? $__mpCfg['master'] : [];

    $__mHost  = (string)($__m['host'] ?? 'localhost');
    $__mDb    = (string)($__m['db']   ?? 'jctecbr_market');
    $__mUser  = (string)($__m['user'] ?? '');
    $__mPass  = (string)($__m['pass'] ?? '');
    $__mTable = (string)($__m['table'] ?? 'tenants');

    $__mTable = preg_replace('/[^a-zA-Z0-9_]/', '', $__mTable);
    if($__mTable === '') $__mTable = 'tenants';

    if($__mUser !== '' && $__mDb !== ''){
        $__dsnM = "mysql:host={$__mHost};dbname={$__mDb};charset=utf8mb4";
        $__pdoM = new PDO($__dsnM, $__mUser, $__mPass, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_PERSISTENT         => false,
        ]);

        try{
            $__stM = $__pdoM->query("SELECT DISTINCT db_name FROM {$__mTable} WHERE db_name IS NOT NULL AND db_name<>''");
        }catch(Exception $eM1){
            $__stM = $__pdoM->query("SELECT DISTINCT db_name FROM {$__mTable}");
        }

        while($__r = $__stM->fetch(PDO::FETCH_ASSOC)){
            if(isset($__r['db_name']) && trim((string)$__r['db_name']) !== ''){
                $dbs[] = trim((string)$__r['db_name']);
            }
        }
    }
}catch(Throwable $e2){
    logLine($logFile, "TENANT-ROUTE: fallback marketplace master falhou: " . $e2->getMessage());
}
    }

    // volta para o DB anterior
    if($cur !== ''){
        try{ pdoUseDb($pdo, $cur); }catch(Exception $e){}
    }

    // 2) fallback: information_schema (não depende do market)
    if(empty($dbs)){
        try{
            $st = $pdo->prepare("SELECT SCHEMA_NAME FROM information_schema.SCHEMATA WHERE SCHEMA_NAME LIKE :p");
            $st->execute([':p' => 'jctecbr\\_%']);
            while($r = $st->fetch(PDO::FETCH_ASSOC)){
                $n = trim((string)($r['SCHEMA_NAME'] ?? ''));
                if($n === '') continue;
                if($n === 'jctecbr_market' || $n === 'jctecbr_master') continue;
                $dbs[] = $n;
            }
        }catch(Exception $e){
            logLine($logFile, "TENANT-ROUTE: falha ao listar DBs via information_schema: " . $e->getMessage());
        }
    }

    $dbs = array_values(array_unique(array_filter($dbs)));
    cacheWriteJson($cacheFile, ['dbs' => $dbs]);
    return $dbs;
}



function findTenantByMarketTenants(PDO $pdo, string $bot55, string $logFile): ?array {
    // Roteamento correto do marketplace:
    // 1) tenta usar o PDO atual com USE jctecbr_market quando permitido;
    // 2) se a credencial atual não puder acessar o market, usa /config/marketplace.php;
    // 3) nunca retorna banco sem db_name e nunca deduz loja por slug.
    $bot55 = formatPhone55($bot55);
    if ($bot55 === '') {
        return null;
    }

    $cur = pdoCurrentDb($pdo);

    try {
        pdoUseDb($pdo, 'jctecbr_market');
        $achadoAtual = bb_gateway_find_tenant_market_com_pdo($pdo, $bot55, $logFile);

        if ($cur !== '') {
            try { pdoUseDb($pdo, $cur); } catch (Throwable $e) {}
        }

        if (is_array($achadoAtual) && !empty($achadoAtual['db_name'])) {
            return $achadoAtual;
        }
    } catch (Throwable $e) {
        if ($cur !== '') {
            try { pdoUseDb($pdo, $cur); } catch (Throwable $e2) {}
        }
        logLine($logFile, 'TENANT-ROUTE: PDO atual não acessou jctecbr_market; tentando credencial master do marketplace. Erro=' . $e->getMessage());
    }

    $pdoMarket = bb_gateway_market_pdo();
    if ($pdoMarket instanceof PDO) {
        $achadoMaster = bb_gateway_find_tenant_market_com_pdo($pdoMarket, $bot55, $logFile);
        if (is_array($achadoMaster) && !empty($achadoMaster['db_name'])) {
            return $achadoMaster;
        }
    }

    return null;
}


/**
 * Abre uma conexão dedicada para o banco individual já mapeado no MARKET.
 * O PDO global/master nunca é reutilizado com USE para acessar banco de loja.
 */
function bb_gateway_connect_tenant_mapping(array $mapping, string $logFile): ?PDO {
    $slug = trim((string)($mapping['slug'] ?? ''));
    $dbEsperado = trim((string)($mapping['db_name'] ?? ''));

    if ($slug === '' || $dbEsperado === '') {
        logLine($logFile, 'TENANT-CONNECT: slug ou db_name ausente no mapeamento global.');
        return null;
    }

    if (!function_exists('conectarBancoPorSlug')) {
        logLine($logFile, "TENANT-CONNECT: função conectarBancoPorSlug indisponível para slug={$slug}.");
        return null;
    }

    try {
        $pdoTenant = conectarBancoPorSlug($slug);
        if (!($pdoTenant instanceof PDO)) {
            logLine($logFile, "TENANT-CONNECT: conexão individual não retornada para slug={$slug} db={$dbEsperado}.");
            return null;
        }

        $dbAtual = pdoCurrentDb($pdoTenant);
        if ($dbAtual === '' || $dbAtual !== $dbEsperado) {
            logLine($logFile, "TENANT-CONNECT: banco divergente para slug={$slug}; esperado={$dbEsperado} atual=" . ($dbAtual !== '' ? $dbAtual : 'vazio') . '.');
            return null;
        }

        if (!tableExists($pdoTenant, 'config') || !tableExists($pdoTenant, 'usuarios')) {
            logLine($logFile, "TENANT-CONNECT: estrutura obrigatória ausente no banco individual db={$dbEsperado}.");
            return null;
        }

        bb_gateway_apply_timezone($mapping['timezone'] ?? '', $logFile);
        return $pdoTenant;
    } catch (Throwable $e) {
        logLine($logFile, "TENANT-CONNECT: falha no banco individual slug={$slug} db={$dbEsperado}: " . $e->getMessage());
        return null;
    }
}

function findTenantByUsuario(PDO $pdo, string $bot55, ?string $instanciaEvento, array $candidateDbs, string $logFile): ?array {
    $bot55 = formatPhone55($bot55);
    $inst = trim((string)$instanciaEvento);

    $keys = phoneKeyCandidates($bot55);

    foreach($candidateDbs as $dbName){
        $dbName = trim((string)$dbName);
        if($dbName === '') continue;

        try{
            pdoUseDb($pdo, $dbName);
        }catch(Exception $e){
            continue;
        }

        if(!tableExists($pdo, 'config') || !tableExists($pdo, 'usuarios')) {
            logLine($logFile, "TENANT-ROUTE: pulando banco sem estrutura de loja db={$dbName}");
            continue;
        }
        if(!columnExists($pdo, 'usuarios', 'token') || !columnExists($pdo, 'usuarios', 'instancia')) continue;

        // 1) tenta por instancia do evento (se vier)
        if($inst !== '' && columnExists($pdo, 'usuarios', 'instancia')){
            try{
                $st = $pdo->prepare("SELECT id FROM usuarios WHERE ativo='Sim' AND instancia = :i AND token<>'' AND token IS NOT NULL LIMIT 1");
                $st->execute([':i' => $inst]);
                $id = (int)$st->fetchColumn();
                if($id > 0){
                    return ['db_name' => $dbName, 'slug' => dbNameToSlug($dbName)];
                }
            }catch(Exception $e){}
        }

        // 2) tenta por telefone do BOT (destinatário/remetente)
        $colTel = firstExistingColumn($pdo, 'usuarios', ['telefone','telefone_whatsapp','whatsapp','celular']);
        if($colTel === '') continue;

        $expr = "REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE($colTel,'+',''),'(',''),')',''),'-',''),' ',''),'.','')";
        $conds = []; $params = [];
        if(isset($keys[11])){ $conds[] = "RIGHT($expr, 11) = :k11"; $params[':k11'] = $keys[11]; }
        if(isset($keys[10])){ $conds[] = "RIGHT($expr, 10) = :k10"; $params[':k10'] = $keys[10]; }
        if(empty($conds)) continue;

        try{
            $sql = "SELECT id FROM usuarios WHERE ativo='Sim' AND token<>'' AND token IS NOT NULL AND instancia<>'' AND instancia IS NOT NULL AND (" . implode(" OR ", $conds) . ") LIMIT 1";
            $st = $pdo->prepare($sql);
            $st->execute($params);
            $id = (int)$st->fetchColumn();
            if($id > 0){
                return ['db_name' => $dbName, 'slug' => dbNameToSlug($dbName)];
            }
        }catch(Exception $e){}
    }

    logLine($logFile, "TENANT-ROUTE: não encontrou usuario por bot={$bot55} inst=" . ($inst !== '' ? $inst : 'NULL'));
    return null;
}

function routeTenantDatabase(PDO $pdo, string $bot55, ?string $instanciaEvento, string $sessDir, string $logFile): ?array {
    $bot55 = formatPhone55($bot55);
    if($bot55 === '') return null;

    $cacheFile = routeCacheFile($sessDir, $bot55, $instanciaEvento);
    $cached = cacheReadJson($cacheFile, 43200); // 12 horas
    if(is_array($cached) && !empty($cached['db_name'])){
        $dbCache = trim((string)$cached['db_name']);
        try{
            if(bb_gateway_tenant_db_pronto($pdo, $dbCache, $logFile)){
                $GLOBALS['tenantSlug'] = (string)($cached['slug'] ?? dbNameToSlug($dbCache));
                $timezoneCache = bb_gateway_apply_timezone($cached['timezone'] ?? '', $logFile);
                return ['db_name' => $dbCache, 'slug' => $GLOBALS['tenantSlug'], 'timezone' => $timezoneCache];
            }

            @unlink($cacheFile);
            logLine($logFile, "TENANT-ROUTE: cache removido por apontar para banco inválido db={$dbCache}");
        }catch(Throwable $e){
            @unlink($cacheFile);
            logLine($logFile, "TENANT-ROUTE: cache inválido removido db={$dbCache} erro=" . $e->getMessage());
        }
    }

    
    // 0) tenta roteamento direto pelo marketplace (tenants.whatsapp => db_name)
    $foundMarket = findTenantByMarketTenants($pdo, $bot55, $logFile);
    if($foundMarket && !empty($foundMarket['db_name'])){
        $dbMarket = trim((string)$foundMarket['db_name']);
        if(bb_gateway_tenant_db_pronto($pdo, $dbMarket, $logFile)){
            $GLOBALS['tenantSlug'] = (string)($foundMarket['slug'] ?? dbNameToSlug($dbMarket));
            $timezoneMarket = bb_gateway_apply_timezone($foundMarket['timezone'] ?? '', $logFile);
            cacheWriteJson($cacheFile, ['db_name' => $dbMarket, 'slug' => $GLOBALS['tenantSlug'], 'timezone' => $timezoneMarket]);
            logLine($logFile, "TENANT-ROUTE: OK market bot={$bot55} => db={$dbMarket} slug=" . $GLOBALS['tenantSlug'] . " timezone={$timezoneMarket}");
            return ['db_name' => $dbMarket, 'slug' => $GLOBALS['tenantSlug'], 'timezone' => $timezoneMarket];
        }

        logLine($logFile, "TENANT-ROUTE: market apontou para banco sem estrutura completa db={$dbMarket}; seguindo fallback seguro.");
    }

$dbs = listTenantDbs($pdo, $sessDir, $logFile);
    if(empty($dbs)){
        logLine($logFile, "TENANT-ROUTE: lista de DBs vazia (sem market e sem information_schema).");
        return null;
    }

    $found = findTenantByUsuario($pdo, $bot55, $instanciaEvento, $dbs, $logFile);

    // Se a lista de bancos estava em cache antigo/incompleto, refaz uma varredura limpa
    // antes de encerrar o webhook sem resposta. Não altera a lógica do bot, só evita rota presa.
    if(!$found){
        $dbsAtualizados = listTenantDbs($pdo, $sessDir, $logFile, true);
        if(!empty($dbsAtualizados) && $dbsAtualizados !== $dbs){
            logLine($logFile, "TENANT-ROUTE: refazendo busca com lista de bancos atualizada para bot={$bot55}");
            $found = findTenantByUsuario($pdo, $bot55, $instanciaEvento, $dbsAtualizados, $logFile);
        }
    }

    if(!$found) return null;

    try{
        pdoUseDb($pdo, (string)$found['db_name']);
    }catch(Exception $e){
        logLine($logFile, "TENANT-ROUTE: falha ao USE db=" . ($found['db_name'] ?? '') . " erro=" . $e->getMessage());
        return null;
    }

    $GLOBALS['tenantSlug'] = (string)($found['slug'] ?? dbNameToSlug((string)$found['db_name']));
    cacheWriteJson($cacheFile, ['db_name' => (string)$found['db_name'], 'slug' => $GLOBALS['tenantSlug']]);

    logLine($logFile, "TENANT-ROUTE: OK bot={$bot55} => db=" . ($found['db_name'] ?? '') . " slug=" . $GLOBALS['tenantSlug']);
    return $found;
}


$raw = file_get_contents('php://input');
$ip  = $_SERVER['REMOTE_ADDR'] ?? '';
logLine($LOG, "=== EVENTO ===");
logLine($LOG, "IP: ".$ip);
logLine($LOG, "RAW: ".$raw);

if(trim($raw) === ''){ echo "OK"; exit(); }
$dados = json_decode($raw, true);
if(!is_array($dados)){ echo "OK"; exit(); }

$tipo = $dados['tipo'] ?? '';
$instanciaEvento = $dados['instancia'] ?? ($dados['instance'] ?? null);

//Permite Sistema/Me para capturar Confirmações
if($tipo !== 'Chat' && $tipo !== 'Sistema' && $tipo !== 'Me'){
    $remRaw = $dados['remetente'] ?? '';
    $destRaw = $dados['destinatario'] ?? '';
    $evtRaw  = $dados['evento'] ?? '';
    logLine($LOG, "IGNORE: tipo={$tipo} evento={$evtRaw} rem={$remRaw} dest={$destRaw}");
    echo "OK"; exit();
}

function menuiaLooksLikeAppCredential($v): bool {
    $s = trim((string)$v);
    if($s === '') return false;

    // Formato clássico retornado por alguns tenants
    if(stripos($s, 'appkey_') === 0){
        return true;
    }

    // No seu Menuia, a Appkey pode vir em UUID (ex.: cb644e0f-eeef-48fb-90fd-4d74ec477d4c)
    if((bool)preg_match('/^[a-f0-9]{8}-[a-f0-9]{4}-[1-5][a-f0-9]{3}-[89ab][a-f0-9]{3}-[a-f0-9]{12}$/i', $s)){
        return true;
    }

    // Alguns ambientes podem usar hex sem hífen (fallback seguro)
    if((bool)preg_match('/^[a-f0-9]{32}$/i', $s)){
        return true;
    }

    return false;
}

/**
 * Resolve APPKEY do Menuia para envio.
 * Alguns tenants guardam Appkey em formatos diferentes (ex.: "appkey_xxx" OU UUID) em config.token / usuarios.token.
 * Estratégia (mais segura):
 *   1) se token atual já tiver formato válido de Appkey (appkey_ OU UUID), usa ele
 *   2) tenta casar em `dispositivos` por telefone+instancia
 *   3) fallback por telefone OU por instancia (sem usar "último registro")
 * Evita pegar appkey de outra loja/dispositivo por engano.
 */
function resolveMenuiaAppKey(PDO $pdo, ?string $tokenAtual, ?string $instanciaAtual, ?string $botTelefone55, string $LOG): ?string {
    $tokenAtual = trim((string)$tokenAtual);
    if($tokenAtual !== '' && menuiaLooksLikeAppCredential($tokenAtual)){
        return $tokenAtual;
    }

    if(!tableExists($pdo, 'dispositivos')){
        logLine($LOG, "MENUia-APPKEY: tabela dispositivos inexistente");
        return null;
    }

    $botDigits = onlyDigits((string)$botTelefone55);
    $botKey11  = ($botDigits !== '') ? substr($botDigits, -11) : '';
    $botKey10  = ($botDigits !== '') ? substr($botDigits, -10) : '';
    $instanciaAtual = trim((string)$instanciaAtual);

    // Descobrir colunas existentes
    $appCols = [];
    foreach(['appkey','app_key','appKey','token','chave'] as $c){
        if(columnExists($pdo, 'dispositivos', $c)){ $appCols[] = $c; }
    }
    if(!$appCols){
        logLine($LOG, "MENUia-APPKEY: tabela dispositivos sem coluna appkey/token/chave");
        return null;
    }

    $phoneCols = [];
    foreach(['telefone','whatsapp','numero','numero_whatsapp','phone'] as $c){
        if(columnExists($pdo, 'dispositivos', $c)){ $phoneCols[] = $c; }
    }

    $instCols = [];
    foreach(['instancia','instancia_whatsapp','instance','instance_id'] as $c){
        if(columnExists($pdo, 'dispositivos', $c)){ $instCols[] = $c; }
    }

    $ativoCols = [];
    foreach(['ativo','status','situacao'] as $c){
        if(columnExists($pdo, 'dispositivos', $c)){ $ativoCols[] = $c; }
    }

    $tryQueries = [];

    // 1) Match estrito: telefone + instancia (maior prioridade)
    if(($botKey11 !== '' || $botKey10 !== '') && $instanciaAtual !== '' && $phoneCols && $instCols){
        foreach($phoneCols as $pc){
            foreach($instCols as $ic){
                $orParts = [];
                $params = [':inst' => $instanciaAtual];
                if($botKey11 !== ''){
                    $orParts[] = "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$pc},''),'+',''),'(',''),')',''),'-','') LIKE :k11";
                    $params[':k11'] = '%'.$botKey11;
                }
                if($botKey10 !== '' && $botKey10 !== $botKey11){
                    $orParts[] = "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$pc},''),'+',''),'(',''),')',''),'-','') LIKE :k10";
                    $params[':k10'] = '%'.$botKey10;
                }
                if(!$orParts) continue;

                $where = '(' . implode(' OR ', $orParts) . ") AND {$ic} = :inst";
                $tryQueries[] = [
                    'tag' => "telefone+instancia {$pc}+{$ic}",
                    'sql' => "SELECT * FROM dispositivos WHERE {$where} ORDER BY id DESC LIMIT 5",
                    'params' => $params,
                ];
            }
        }
    }

    // 2) Fallback por telefone (sem instancia)
    if(($botKey11 !== '' || $botKey10 !== '') && $phoneCols){
        foreach($phoneCols as $pc){
            $orParts = [];
            $params = [];
            if($botKey11 !== ''){
                $orParts[] = "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$pc},''),'+',''),'(',''),')',''),'-','') LIKE :k11";
                $params[':k11'] = '%'.$botKey11;
            }
            if($botKey10 !== '' && $botKey10 !== $botKey11){
                $orParts[] = "REPLACE(REPLACE(REPLACE(REPLACE(COALESCE({$pc},''),'+',''),'(',''),')',''),'-','') LIKE :k10";
                $params[':k10'] = '%'.$botKey10;
            }
            if($orParts){
                $tryQueries[] = [
                    'tag' => "telefone {$pc}",
                    'sql' => "SELECT * FROM dispositivos WHERE (" . implode(' OR ', $orParts) . ") ORDER BY id DESC LIMIT 5",
                    'params' => $params,
                ];
            }
        }
    }

    // 3) Fallback por instancia (sem telefone)
    if($instanciaAtual !== '' && $instCols){
        foreach($instCols as $ic){
            $tryQueries[] = [
                'tag' => "instancia {$ic}",
                'sql' => "SELECT * FROM dispositivos WHERE {$ic} = :inst ORDER BY id DESC LIMIT 5",
                'params' => [':inst' => $instanciaAtual],
            ];
        }
    }

    $seenAppkeys = [];
    foreach($tryQueries as $cand){
        try{
            $st = $pdo->prepare($cand['sql']);
            $st->execute($cand['params']);
            $rows = $st->fetchAll(PDO::FETCH_ASSOC);
            if(!$rows){
                continue;
            }

            foreach($rows as $row){
                $appkey = '';
                $appColUsada = '';
                foreach($appCols as $col){
                    if(!isset($row[$col]) || trim((string)$row[$col]) === ''){
                        continue;
                    }
                    $valor = trim((string)$row[$col]);

                    // Colunas explicitamente "appkey" podem conter UUID no Menuia (válido).
                    // Colunas genéricas (token/chave) só entram se tiver formato reconhecido.
                    $isExplicitAppCol = in_array(strtolower((string)$col), ['appkey','app_key','appkey'], true);
                    if($isExplicitAppCol || menuiaLooksLikeAppCredential($valor)){
                        $appkey = $valor;
                        $appColUsada = $col;
                        break;
                    }
                }

                if($appkey === ''){
                    continue; // ignora valores vazios/sem cara de Appkey
                }
                if(isset($seenAppkeys[$appkey])){
                    continue;
                }
                $seenAppkeys[$appkey] = true;

                $rowId = isset($row['id']) ? (string)$row['id'] : '?';
                logLine($LOG, "MENUia-APPKEY: resolvi via dispositivos [{$cand['tag']}] row_id={$rowId} col={$appColUsada} bot={$botTelefone55} appkey=".substr($appkey,0,18).'...');
                return $appkey;
            }
        }catch(Throwable $e){
            logLine($LOG, "MENUia-APPKEY: erro consulta [".($cand['tag'] ?? 'na')."] ".$e->getMessage());
        }
    }

    logLine($LOG, "MENUia-APPKEY: NAO consegui resolver Appkey por match seguro (bot={$botTelefone55} inst=".($instanciaAtual ?: 'NULL')." token_atual={$tokenAtual})");
    return null;
}



// ==========================================================
// ROTEAMENTO ESTRITO PARA O BANCO INDIVIDUAL DA LOJA
// - O webhook inicia no banco GLOBAL/MARKET.
// - Identifica a loja pelo número do BOT cadastrado em tenants.
// - Exige slug + db_name explícitos no registro global.
// - Abre um novo PDO pelo slug; nunca usa o PDO MASTER com USE no tenant.
// ==========================================================
$dest55 = formatPhone55($dados['destinatario'] ?? '');
$rem55  = formatPhone55($dados['remetente'] ?? '');

$botCandidates = [];
if($tipo === 'Chat'){
    if($dest55 !== '') $botCandidates[] = $dest55;
}elseif($tipo === 'Me'){
    if($rem55 !== '') $botCandidates[] = $rem55;
}elseif($tipo === 'Sistema'){
    if($dest55 !== '') $botCandidates[] = $dest55;
    if($rem55 !== '') $botCandidates[] = $rem55;
}
$botCandidates = array_values(array_unique(array_filter($botCandidates)));

$bot55 = '';
$__routed = false;

foreach($botCandidates as $__cand){
    try{
        $mapping = findTenantByMarketTenants($pdo, $__cand, $LOG);
        if(!is_array($mapping) || empty($mapping['db_name']) || empty($mapping['slug'])){
            continue;
        }

        $pdoTenant = bb_gateway_connect_tenant_mapping($mapping, $LOG);
        if(!($pdoTenant instanceof PDO)){
            continue;
        }

        $pdo = $pdoTenant;
        $bot55 = $__cand;
        $GLOBALS['tenantSlug'] = (string)$mapping['slug'];
        $GLOBALS['tenantBot55'] = $__cand;
        $whatsapp_sistema = $__cand;
        $__routed = true;

        logLine($LOG, "TENANT-ROUTE: OK estrito bot={$__cand} => db=" . (string)$mapping['db_name'] . " slug=" . (string)$mapping['slug']);
        break;
    }catch(Throwable $e){
        logLine($LOG, "TENANT-ROUTE: falha no candidato bot={$__cand}: " . $e->getMessage());
    }
}

if(!$__routed){
    $botLog = $botCandidates[0] ?? '';
    logLine($LOG, "TENANT-ROUTE: mapeamento global ou conexão individual não resolvidos para bot=" . ($botLog !== '' ? $botLog : 'vazio') . ". Webhook encerrado para impedir cruzamento entre lojas.");
    echo "OK";
    exit();
}
// ==========================================================

if(!tableExists($pdo, 'config')){
    logLine($LOG, "TENANT-ROUTE: banco atual sem tabela config apos roteamento db=" . pdoCurrentDb($pdo) . ". Webhook encerrado sem fatal error.");
    echo "OK";
    exit();
}

try{
    $cfg = $pdo->query("SELECT * FROM config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
    if(!is_array($cfg)){
        $cfg = [];
    }
}catch(Throwable $e){
    logLine($LOG, "CONFIG: falha ao carregar config do tenant db=" . pdoCurrentDb($pdo) . " erro=" . $e->getMessage());
    echo "OK";
    exit();
}
$nomeSalao = (trim($cfg['nome'] ?? '') !== '') ? $cfg['nome'] : 'Jacy Cabeleireiro';
$enderecoSalao = trim((string)($cfg['endereco'] ?? ''));
$mapaSalaoRaw = trim((string)($cfg['mapa'] ?? ''));
$mapaSalaoUrl = configMapUrl($mapaSalaoRaw, $enderecoSalao);
$whatsapp_sistema = $cfg['telefone_whatsapp'] ?? ($cfg['telefone_whatsapp_sistema'] ?? '');
// ✅ Multi-loja: o BOT real da loja vem do marketplace (destinatario/remetente normalizado)
if(!empty($GLOBALS['tenantBot55'])){ $whatsapp_sistema = (string)$GLOBALS['tenantBot55']; }
$barberbot_ativo_raw   = $cfg['barberbot_ativo'] ?? 0;
$barberbot_ativo       = simNaoToInt($barberbot_ativo_raw);
$barberbot_boas_vindas = $cfg['barberbot_boas_vindas'] ?? '';
$openai_key            = $cfg['openai_key'] ?? '';
$openai_prompt         = $cfg['openai_prompt'] ?? '';
$tenantSlug = trim((string)($GLOBALS['tenantSlug'] ?? ''));
if($tenantSlug === ''){
    $dbNow = pdoCurrentDb($pdo);
    $tenantSlug = dbNameToSlug($dbNow);
}
$tenantSlug = trim($tenantSlug, "/");
$link_agendamento = rtrim("https://{$_SERVER['HTTP_HOST']}", '/') . "/loja/{$tenantSlug}/agendamentos/";

$api = $cfg['api'] ?? ($api ?? null);
$token = $cfg['token'] ?? ($token ?? null);
$instancia = $cfg['instancia'] ?? ($cfg['instancia_whatsapp'] ?? ($instancia ?? null));

$destinatarioEvento = ($bot55 !== '' ? formatPhone55($bot55) : formatPhone55($dados['destinatario'] ?? ''));
$GLOBALS['destinatarioEvento'] = $destinatarioEvento;
$GLOBALS['bot55'] = $destinatarioEvento;
$usuarioCtx = fetchUsuarioContext($pdo, $instanciaEvento, $destinatarioEvento);

if(is_array($usuarioCtx)){
    $nomeProfissionalSistema = trim((string)($usuarioCtx['nome'] ?? ''));
    if(isset($usuarioCtx['telefone']) && trim((string)$usuarioCtx['telefone']) !== ''){
        // ✅ Não sobrescreve o BOT da loja em modo multi-loja
        if(empty($GLOBALS['tenantBot55'])){
            $whatsapp_sistema = $usuarioCtx['telefone'];
        }
    }
    if(isset($usuarioCtx['token']) && trim((string)$usuarioCtx['token']) !== '') $token = trim((string)$usuarioCtx['token']);
    if(isset($usuarioCtx['instancia']) && trim((string)$usuarioCtx['instancia']) !== '') { $instancia = trim((string)$usuarioCtx['instancia']); $instanciaEvento = $instancia; }
    if(isset($usuarioCtx['api']) && trim((string)$usuarioCtx['api']) !== '') $api = trim((string)$usuarioCtx['api']);
    logLine($LOG, "CTX usuarios OK: id=".($usuarioCtx['id'] ?? 'NULL')." tel=".formatPhone55($whatsapp_sistema)." inst=".($instanciaEvento ?? 'NULL'));
}else{
    logLine($LOG, "CTX usuarios NAO achou: inst=".($instanciaEvento ?? 'NULL')." dest=".$destinatarioEvento);
}

// --- TRAVA DE SEGURANÇA (NORMALIZADA 10 DIGITOS) ---
// Compara apenas DDD + 8 digitos finais para evitar erros de 9º dígito
if($tipo === 'Chat'){
    $destinatarioReal = phoneKeyCanonical10($destinatarioEvento);
    $botConfigurado   = phoneKeyCanonical10($whatsapp_sistema);

    // ✅ Modo estrito: se ambos foram identificados e não batem, bloqueia para evitar cruzamento entre profissionais.
    if($destinatarioReal !== '' && $botConfigurado !== '' && $destinatarioReal !== $botConfigurado){
        logLine($LOG, "BLOQUEIO CRUZADO: Msg recebida em {$destinatarioEvento} (canon:{$destinatarioReal}) mas o Bot ativo é {$whatsapp_sistema} (canon:{$botConfigurado}).");
        echo "OK";
        exit();
    }
}
// ----------------------------------------------------

if($tipo === "Sistema"){
    $numeroCliente = formatPhone55($dados['destinatario'] ?? '');
    $idRef = trim((string)($dados['idAgendamento'] ?? ($dados['hash'] ?? ($dados['agendamento'] ?? ''))));
    $refConfirmacaoLocal = '';
    $linhasAtualizadasRef = 0;

    if($numeroCliente !== '' && $idRef !== '' && tableExists($pdo, 'agendamentos')){
        try{
            if(bb_agendamentoRefExiste($pdo, $idRef)){
                $refConfirmacaoLocal = $idRef;

                if(columnExists($pdo,'agendamentos','phone')){
                    $st = $pdo->prepare("UPDATE agendamentos SET phone = :phone WHERE hash = :ref OR id = :ref");
                    $st->execute([':phone' => $numeroCliente, ':ref' => $idRef]);
                    $linhasAtualizadasRef = (int)$st->rowCount();
                }
            }

            logLine($LOG, "Sistema: phone={$numeroCliente} ref={$idRef} ref_local=".($refConfirmacaoLocal !== '' ? 'sim' : 'nao')." rows={$linhasAtualizadasRef}");
        }catch(Throwable $e){
            logLine($LOG, "Sistema: falha ao validar ref={$idRef}: ".$e->getMessage());
        }
    }

    //Arma modo confirmação se for mensagem de confirmação
    $msgSys = trim(menuiaMessageText($dados));
    if(isConfirmationPrompt($msgSys)){
        $dadosConfirmacao = bb_parseConfirmacaoDataHora($msgSys);
        $remSys = formatPhone55($dados['remetente'] ?? '');
        $destSys = formatPhone55($dados['destinatario'] ?? '');
        $remIsUser = fetchUsuarioByTelefone($pdo, $remSys);
        $destIsUser = fetchUsuarioByTelefone($pdo, $destSys);
        if($remIsUser && !$destIsUser){ $cliente = $destSys; $prof = $remSys; }
        elseif($destIsUser && !$remIsUser){ $cliente = $remSys; $prof = $destSys; }
        else{ $cliente = $destSys; $prof = $remSys; }
        if($cliente && $prof){
            bb_armAwaitConfirm(
                $SESS_DIR,
                $cliente,
                $prof,
                $refConfirmacaoLocal,
                $numeroCliente,
                21600,
                (string)($dadosConfirmacao['data'] ?? ''),
                (string)($dadosConfirmacao['hora'] ?? '')
            );
            logLine($LOG, "Armei modo confirmação (Sistema): cliente={$cliente} prof={$prof} ref_local=".($refConfirmacaoLocal !== '' ? $refConfirmacaoLocal : 'NULL')." data=".($dadosConfirmacao['data'] ?? 'NULL')." hora=".($dadosConfirmacao['hora'] ?? 'NULL'));
        }
    }
    echo "OK"; exit();
}

if($tipo !== "Chat"){
    //Arma modo confirmação se for evento de envio (Me)
    $msgEvt = trim(menuiaMessageText($dados));
    if(isConfirmationPrompt($msgEvt)){
        $a = formatPhone55($dados['remetente'] ?? '');
        $b = formatPhone55($dados['destinatario'] ?? '');
        $aUser = fetchUsuarioByTelefone($pdo, $a);
        $bUser = fetchUsuarioByTelefone($pdo, $b);
        if($aUser && !$bUser){ $cliente = $b; $prof = $a; }
        elseif($bUser && !$aUser){ $cliente = $a; $prof = $b; }
        else{ $cliente = $b; $prof = $a; }
        if($cliente && $prof){
            $dadosConfirmacao = bb_parseConfirmacaoDataHora($msgEvt);
            bb_armAwaitConfirm(
                $SESS_DIR,
                $cliente,
                $prof,
                '',
                $cliente,
                21600,
                (string)($dadosConfirmacao['data'] ?? ''),
                (string)($dadosConfirmacao['hora'] ?? '')
            );
            logLine($LOG, "Armei modo confirmação (tipo={$tipo}): cliente={$cliente} prof={$prof} data=".($dadosConfirmacao['data'] ?? 'NULL')." hora=".($dadosConfirmacao['hora'] ?? 'NULL'));
        }
    }
    echo "OK"; exit();
}

$msg = trim(menuiaMessageText($dados));
$nomeClienteMenu = trim(menuiaScalarText($dados['nome'] ?? ''));
$msgLower = mb_strtolower($msg, 'UTF-8');
$remetente = formatPhone55($dados['remetente'] ?? '');
$destinatario = formatPhone55($dados['destinatario'] ?? '');

$nomeClienteMenu = fetchNomeClienteByPhone($pdo, $remetente);
if($nomeClienteMenu === '') $nomeClienteMenu = trim(menuiaScalarText($dados['nome'] ?? ''));
if($nomeClienteMenu === '') $nomeClienteMenu = 'Cliente WhatsApp';

$msgId = (string)($dados['idMessagem'] ?? ($dados['idMensagem'] ?? ($dados['messageId'] ?? ($dados['id'] ?? ''))));

// Inicializa trava de resposta única por webhook
bb_initReplyGate($instanciaEvento, $remetente, $destinatario, $msgId, $msg);
$choiceNum = leadingNumber($msg);

if($remetente === ''){ logLine($LOG, "Sem remetente."); echo "OK"; exit(); }

$sess = getSession($SESS_DIR, $remetente, $destinatario);
// ==========================================================
// CANAL DE SAÍDA POR PROFISSIONAL (SEM TABELA NOVA)
// ----------------------------------------------------------
// Se o cliente já escolheu um profissional (sess['selected_prof_id']),
// e este profissional possui token/instância próprios, as respostas
// passam a sair pelo WhatsApp do profissional (Menuia).
// Caso contrário, mantém credenciais padrão da loja (config).
// OBS: não altera fluxo nem regra de negócio, apenas a credencial do envio.
// ==========================================================
$__profSelId = (int)($sess['selected_prof_id'] ?? 0);
if($__profSelId > 0){
    try{
        $colunasProfissionalEnvio = ['id', 'ativo', 'token', 'instancia'];
        if(columnExists($pdo, 'usuarios', 'api')){
            $colunasProfissionalEnvio[] = 'api';
        }

        $colunasSqlProfissional = [];
        foreach($colunasProfissionalEnvio as $colunaProfissionalEnvio){
            $colunasSqlProfissional[] = "`" . str_replace("`", "", $colunaProfissionalEnvio) . "`";
        }

        $stProf = $pdo->prepare("SELECT " . implode(', ', $colunasSqlProfissional) . " FROM usuarios WHERE id = :id LIMIT 1");
        $stProf->execute([':id' => $__profSelId]);
        $__profRow = $stProf->fetch(PDO::FETCH_ASSOC);

        if(is_array($__profRow)
            && (($__profRow['ativo'] ?? '') === 'Sim')
            && (trim((string)($__profRow['token'] ?? '')) !== '')
            && (trim((string)($__profRow['instancia'] ?? '')) !== '')
        ){
            // Usa credenciais do profissional
            $token = trim((string)$__profRow['token']);
            $instancia = trim((string)$__profRow['instancia']);
            if(isset($__profRow['api']) && trim((string)$__profRow['api']) !== ''){
                $api = trim((string)$__profRow['api']);
            }
            // Mantém compatibilidade com trechos que usam instanciaEvento
            if(isset($instanciaEvento)){
                $instanciaEvento = $instancia;
            }
            logLine($LOG, "ENVIO-PROFISSIONAL: prof_id={$__profSelId} inst={$instancia}");
        }else{
            logLine($LOG, "ENVIO-FALLBACK-LOJA: prof_id={$__profSelId} sem token/instancia ou inativo");
        }
    }catch(Exception $e){
        logLine($LOG, "ENVIO-FALLBACK-LOJA: erro ao resolver profissional selecionado: " . $e->getMessage());
    }
}


logLine($LOG, "DEBUG: stage=".($sess['stage'] ?? 'NULL')." cliente={$remetente} prof={$destinatario}");

if($msgId !== ''){
    $lastId = (string)($sess['last_msg_id'] ?? '');
    if($lastId !== '' && $lastId === $msgId){ logLine($LOG, "Dedupe: msgId repetido={$msgId}"); echo "OK"; exit(); }
    $sess['last_msg_id'] = $msgId; $sess['last_msg_at'] = time();
    saveSession($SESS_DIR, $remetente, $destinatario, $sess);
}

$optDigitBurst = substr(onlyDigits($msg), 0, 1);
$isCommand = ($optDigitBurst !== '') || (mb_stripos($msgLower, 'menu', 0, 'UTF-8') !== false);
$lastReplyAt = (int)($sess['last_reply_at'] ?? 0);
if($lastReplyAt > 0 && (time() - $lastReplyAt) < $BURST_WINDOW && !$isCommand){
    logLine($LOG, "Anti-burst ignored."); echo "OK"; exit();
}

$telSistema55 = formatPhone55($whatsapp_sistema);
$isFromSistema = ($telSistema55 !== '' && $remetente === $telSistema55);
if($isFromSistema){
    logLine($LOG, "IGNORE: msg do sistema.");
    if(isConfirmationPrompt($msgLower)){
        $dadosConfirmacao = bb_parseConfirmacaoDataHora($msg);
        bb_armAwaitConfirm(
            $SESS_DIR,
            $destinatario,
            $remetente,
            '',
            $destinatario,
            21600,
            (string)($dadosConfirmacao['data'] ?? ''),
            (string)($dadosConfirmacao['hora'] ?? '')
        );
        logLine($LOG, "Armei modo confirmação (Sistema->Cliente) data=".($dadosConfirmacao['data'] ?? 'NULL')." hora=".($dadosConfirmacao['hora'] ?? 'NULL'));
    }
    echo "OK"; exit();
}

$remUsuarioRow = fetchUsuarioByTelefone($pdo, $remetente);
if($remUsuarioRow && isLikelyBotEcho($msgLower)){ logLine($LOG, "IGNORE: eco do bot."); echo "OK"; exit(); }
if($remUsuarioRow && isAtendimentoSim($remUsuarioRow['atendimento'] ?? null) && !$isFromSistema){
    logLine($LOG, "IGNORE: remetente é linha de atendimento."); echo "OK"; exit();
}

// =======================================================================
// LÓGICA DE CONFIRMAÇÃO (1=Confirmar, 2=Cancelar/Deletar)
// =======================================================================
$awaitUntil = (int)($sess['await_confirm_until'] ?? 0);
if($awaitUntil > time()){
    $opcaoConfirmacao = '';
    if(preg_match('/^\s*([12])\s*$/u', (string)$msg, $mConfirm)){
        $opcaoConfirmacao = (string)$mConfirm[1];
    }

    $refConfirmacao = trim((string)($sess['await_confirm_ref'] ?? ''));
    $phoneConfirmacao = formatPhone55((string)($sess['await_confirm_phone'] ?? $remetente));
    $dataConfirmacao = trim((string)($sess['await_confirm_data'] ?? ''));
    $horaConfirmacao = trim((string)($sess['await_confirm_hora'] ?? ''));

    if($phoneConfirmacao === ''){
        $phoneConfirmacao = $remetente;
    }

    // Primeiro valida se ainda existe agendamento pendente.
    // Se já confirmou/cancelou, libera a conversa e para de prender o cliente neste modo.
    $agendamento = bb_findPendingConfirmAgendamento($pdo, $phoneConfirmacao, $refConfirmacao, $dataConfirmacao, $horaConfirmacao);

    if(!$agendamento){
        bb_clearAwaitConfirm($sess, $SESS_DIR, $remetente, $destinatario);

        if($opcaoConfirmacao !== ''){
            $mensagem = "⚠️ Aviso: Não encontramos agendamentos pendentes neste horário para confirmar/cancelar.";
            sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento);
            echo "OK"; exit();
        }

        // Sem pendência: segue o fluxo normal do bot.
    } else {
        $id_agendamento = $agendamento['id'];
        $idCliente      = $agendamento['cliente'];
        $idFuncionario  = $agendamento['funcionario'];
        $dataAgendamento = date('d/m/Y', strtotime($agendamento['data']));
        $horarioAgendamento = $agendamento['hora'];

        $nomeCliente = 'Cliente';
        if(tableExists($pdo, 'clientes')){
            $st = $pdo->prepare("SELECT nome FROM clientes WHERE id = :id LIMIT 1");
            $st->execute([':id' => $idCliente]);
            $nomeCliente = $st->fetchColumn() ?: 'Cliente';
        }

        $nomeFuncionario = 'Profissional';
        $telefoneFuncionario = '';
        if(tableExists($pdo, 'usuarios')){
            $st = $pdo->prepare("SELECT nome, telefone FROM usuarios WHERE id = :id LIMIT 1");
            $st->execute([':id' => $idFuncionario]);
            $f = $st->fetch(PDO::FETCH_ASSOC);
            if($f){
                $nomeFuncionario = $f['nome'] ?? 'Profissional';
                $telefoneFuncionario = $f['telefone'] ?? '';
            }
        }

        if($opcaoConfirmacao === ''){
            $mensagem = "⚠️ *Opção inválida.*

Por favor, digite apenas o número:
*1* para Confirmar
*2* para Cancelar o agendamento.";
            sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento);
            echo "OK"; exit();
        }

        if($opcaoConfirmacao === '1'){
            try{
                $st = $pdo->prepare("UPDATE agendamentos SET status = 'Confirmado' WHERE id = :id");
                $st->execute([':id' => $id_agendamento]);
            }catch(Exception $e){}

            bb_clearAwaitConfirm($sess, $SESS_DIR, $remetente, $destinatario);

            $mensagem = "✅ _Confirmado!_

";
            $mensagem .= "Agendamento de *".$nomeCliente."*.

";
            $mensagem .= "*Data:* ".$dataAgendamento." às ".$horarioAgendamento."

";
            $mensagem .= "O profissional *".$nomeFuncionario."* lhe aguarda no horário agendado.

";
            $mensagem .= "_Obrigado!_";

            sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento);

            $telFunc55 = formatPhone55($telefoneFuncionario);
            $telDono55 = formatPhone55($whatsapp_sistema);
            if($telFunc55 !== '' && $telFunc55 !== $telDono55){
                sendTextViaApiTexto($telFunc55, $mensagem, $instanciaEvento);
            }
            echo "OK"; exit();
        }

        if($opcaoConfirmacao === '2'){
            try{
                $st = $pdo->prepare("DELETE FROM agendamentos WHERE id = :id");
                $st->execute([':id' => $id_agendamento]);
            }catch(Exception $e){}

            if(tableExists($pdo, 'horarios_agd')){
                try{
                    $st = $pdo->prepare("DELETE FROM horarios_agd WHERE agendamento = :id");
                    $st->execute([':id' => $id_agendamento]);
                }catch(Exception $e){}
            }

            bb_clearAwaitConfirm($sess, $SESS_DIR, $remetente, $destinatario);

            $mensagem = "❌ _Cancelado!_

";
            $mensagem .= "Agendamento de *".$nomeCliente."*

";
            $mensagem .= "*Data:* ".$dataAgendamento." às ".$horarioAgendamento."

";
            $mensagem .= "Reagende novo horário pelo site: ".$link_agendamento;

            sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento);

            $telDono55 = formatPhone55($whatsapp_sistema);
            if($telDono55 !== '' && !bb_isBotAtualTarget($telDono55)){
                sendTextViaApiTexto($telDono55, $mensagem, $instanciaEvento);
            }
            $telFunc55 = formatPhone55($telefoneFuncionario);
            if($telFunc55 !== '' && $telFunc55 !== $telDono55 && !bb_isBotAtualTarget($telFunc55)){
                sendTextViaApiTexto($telFunc55, $mensagem, $instanciaEvento);
            }

            echo "OK"; exit();
        }
    }
}

if($barberbot_ativo !== 1){ echo "OK"; exit(); }
$optDigit = '';
if($choiceNum >= 1 && $choiceNum <= 4) $optDigit = (string)$choiceNum;

// Modo humano
if(($sess['modo'] ?? '') === 'humano'){
    $desde = (int)($sess['humano_desde'] ?? 0);
    if($desde <= 0){ $sess['humano_desde'] = time(); saveSession($SESS_DIR, $remetente, $destinatario, $sess); echo "OK"; exit(); }
    if((time() - $desde) >= $HUMANO_TIMEOUT){ unset($sess['modo'], $sess['humano_desde']); saveSession($SESS_DIR, $remetente, $destinatario, $sess); }
    else{ echo "OK"; exit(); }
}
$currentStage = (string)($sess['stage'] ?? '');
$numericStages = ['choose_date','choose_prof','choose_time','profs_list','services_by_prof','servicos_list'];
$isNumericStage = in_array($currentStage, $numericStages, true);

// Atalho "4 = humano" só vale fora dos estágios de seleção numérica (ex.: escolher horário).
if( ( (!$isNumericStage && $optDigit === '4' && strpos($msg, '/') === false) ) || strpos($msgLower, 'humano') !== false){
    $sess['modo'] = 'humano'; $sess['humano_desde'] = time(); saveSession($SESS_DIR, $remetente, $destinatario, $sess);
    $mensagem = "✅ Certo! Pode enviar sua mensagem que o profissional vai te atender.\n\n*(Bot pausado nesta conversa por 30 minutos.)*";
    sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento);
    echo "OK"; exit();
}

// 1. BLOCO ESPECIAL: PORTFÓLIO DE PRÓTESES
// Se falar de prótese, manda o link do portfólio.
$palavrasProtese = ['protese', 'prótese', 'capilar','manutenção prótese capilares'];

if(containsAny($msgLower, $palavrasProtese)){
    unset($sess['stage'], $sess['servicos_map'], $sess['prof_map'], $sess['time_map'], $sess['selected_service_id'], $sess['selected_prof_id'], $sess['selected_date']);
    saveSession($SESS_DIR, $remetente, $destinatario, $sess);

    $msgProtese = "✨ *Especialista em Prótese Capilar*\n\n";
    $msgProtese .= "Confira nosso portfólio completo com resultados incríveis:\n";
    $msgProtese .= "👉 https://jacycabeleireiro.com/protese\n\n";
    $msgProtese .= "Para agendar uma avaliação, acesse: " . $link_agendamento;

    sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $msgProtese, $instanciaEvento);
    echo "OK"; exit();
}

// 2. BLOCO DE LOCALIZAÇÃO (Endereço, Cidade, Toledo, Cascavel)
$palavrasLocal = [
    'endereço', 'endereco', 'localização', 'localizacao', 'onde fica', 
    'onde vocês ficam', 'cidade', 'estado', 'rua', 'bairro', 'local',
    'toledo', 'cascavel', 'são de onde', 'sao de onde'
];

if(containsAny($msgLower, $palavrasLocal)){
    unset($sess['stage'], $sess['servicos_map'], $sess['prof_map'], $sess['time_map'], $sess['selected_service_id'], $sess['selected_prof_id'], $sess['selected_date']);
    saveSession($SESS_DIR, $remetente, $destinatario, $sess);

    $msgLocal = "🏢 *Nossa Localização*\n\n";

    if($enderecoSalao !== ''){
        $msgLocal .= "📍 *Endereço:*\n{$enderecoSalao}\n\n";
    }else{
        $msgLocal .= "📍 *Endereço:*\nConsulte nosso atendente para confirmar o endereço.\n\n";
    }

    if($mapaSalaoUrl !== ''){
        $msgLocal .= "🗺️ *Ver no Mapa:*\n{$mapaSalaoUrl}\n\n";
    }

    $msgLocal .= "Para agendar, acesse: " . $link_agendamento;

    sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $msgLocal, $instanciaEvento);
    echo "OK"; exit();
}

// 3. BLOCO DO LINK DIRETO (Vendas, Cortes, Produtos)
$palavrasLink = [
    'corte','barba','escova','progressiva','mega hair','manutenção mega hair',
    'definição cachos','soltura cachos','tranças','depilação','mechas colocada','topo feminino', 'detox reposição','coloração','designer barba',
    'designer sobrancelha','manicure pedicure','manicure','corte cabelo feminina','corte cabelo masculino',
    'vendas cabelo masculino','compra cabelo masculino','vendas produtos cabelo masculino',
    'vendas produtos cabelo femenino','vende', 'venda', 'comprar', 'preço', 'valor',
    'compra', 'compro', 'natural', 'coloração', 'cabelo', 'platinado masculino'
];

if(containsAny($msgLower, $palavrasLink)){
    unset($sess['stage'], $sess['servicos_map'], $sess['prof_map'], $sess['time_map'], $sess['selected_service_id'], $sess['selected_prof_id'], $sess['selected_date']);
    saveSession($SESS_DIR, $remetente, $destinatario, $sess);

    $msgDireta = "✅ Olá! Sim, realizamos esse atendimento e negociações (Vendas e Compras). \n\n";
    $msgDireta .= "Para agendar, ver valores ou negociar, acesse nosso link exclusivo:\n";
    $msgDireta .= "👉 " . $link_agendamento . "\n\n";
    $msgDireta .= "Lá você encontra tudo explicadinho!";
    
    sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $msgDireta, $instanciaEvento);
    echo "OK"; exit();
}

// 4. BLOCO DO MENU PADRÃO (Oi, Olá, Menu)
$gatilhosMenu = ['menu','oi','ola','olá','bom dia','boa tarde','boa noite','tudo bem','tudo bom'];

if(empty($sess) || containsAny($msgLower, $gatilhosMenu)){
    unset($sess['stage'], $sess['servicos_map'], $sess['prof_map'], $sess['time_map'], $sess['selected_service_id'], $sess['selected_prof_id'], $sess['selected_date'], $sess['profs_map'], $sess['services_by_prof_map']);
    $sess['started'] = true; saveSession($SESS_DIR, $remetente, $destinatario, $sess);
    $mensagem = ""; if(trim($barberbot_boas_vindas) !== '') $mensagem .= $barberbot_boas_vindas . "\n\n";
    $mensagem .= menuText($nomeSalao, $nomeClienteMenu);
    sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento);
    echo "OK"; exit();
}

// Stage logic
if(($sess['stage'] ?? '') === 'profs_list' && $choiceNum > 0){
    $map = $sess['profs_map'] ?? []; $idProf = (int)(mapGet($map, $choiceNum) ?? 0);
    if($idProf > 0){
        $sess['selected_prof_id'] = $idProf;
        $servs = fetchServicosByProf($pdo, $idProf);
        if(empty($servs)){
            $mensagem = "⚠️ Esse profissional não tem serviços vinculados.\n\n".menuHint();
            unset($sess['stage'], $sess['profs_map'], $sess['selected_prof_id']);
            saveSession($SESS_DIR, $remetente, $destinatario, $sess);
            sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento); echo "OK"; exit();
        }
        $mensagem = "💇‍♂️ *Serviços deste profissional:*\nDigite o *número do serviço*.\n";
        $smap = []; $i=1;
        foreach($servs as $s){
            $nomeS = $s['nome'] ?? 'Serviço'; $valor = formatMoneyBR($s['valor'] ?? '');
            $mensagem .= bb_opt_line($i, $nomeS.($valor ? " - *{$valor}*" : "")); $smap[(string)$i] = (int)($s['id'] ?? 0); $i++; if($i>60) break;
        }
        $sess['stage'] = 'services_by_prof'; $sess['services_by_prof_map'] = $smap; unset($sess['profs_map']);
        saveSession($SESS_DIR, $remetente, $destinatario, $sess);
        $mensagem .= "\nDigite o *número do serviço*.\n".menuHint();
        sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento); echo "OK"; exit();
    }
    $mensagem = "⚠️ Opção inválida. Digite o número do profissional.\n\n".menuHint();
    sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento); echo "OK"; exit();
}

if(($sess['stage'] ?? '') === 'services_by_prof' && $choiceNum > 0){
    $map = $sess['services_by_prof_map'] ?? []; $idServico = (int)(mapGet($map, $choiceNum) ?? 0);
    if($idServico > 0){
        $sess['selected_service_id'] = $idServico;
        $sess['stage'] = 'choose_date'; unset($sess['services_by_prof_map']);
        saveSession($SESS_DIR, $remetente, $destinatario, $sess);
        $mensagem = "📅 Perfeito! Agora me diga a *data*.\nDigite *DD/MM* ou *DD/MM/AAAA*.\nEx: 31/01 ou 31/01/2026\n\n".menuHint();
        sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento); echo "OK"; exit();
    }
    $mensagem = "⚠️ Escolha o número do serviço na lista.\n\n".menuHint();
    sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento); echo "OK"; exit();
}

if(($sess['stage'] ?? '') === 'servicos_list' && $choiceNum > 0){
    $map = $sess['servicos_map'] ?? []; $idServico = (int)(mapGet($map, $choiceNum) ?? 0);
    if($idServico > 0){
        $srv = null; try{ $st=$pdo->prepare("SELECT id, nome, valor FROM servicos WHERE id=:id AND (ativo='Sim' OR ativo=1) LIMIT 1"); $st->execute([':id'=>$idServico]); $srv=$st->fetch(PDO::FETCH_ASSOC); }catch(Exception $e){}
        if($srv){
            $sess['selected_service_id'] = $idServico;
            $profs = fetchProfissionaisByServico($pdo, $idServico);
            if(empty($profs)){
                $mensagem = "⚠️ Não encontrei profissionais para esse serviço.\n\n".menuHint();
                unset($sess['stage'], $sess['servicos_map'], $sess['selected_service_id']);
                saveSession($SESS_DIR, $remetente, $destinatario, $sess);
                sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento); echo "OK"; exit();
            }
            $mensagem = "✅ *Serviço selecionado:*\n".((string)($srv['nome'] ?? 'Serviço'));
            $valor = formatMoneyBR($srv['valor'] ?? ''); if($valor) $mensagem .= " - *{$valor}*";
            $mensagem .= "\n\n👤 *Agora escolha o profissional:*\n";
            $profMap = []; $i=1;
            foreach($profs as $p){ $mensagem .= bb_opt_line($i, ($p['nome'] ?? 'Profissional')); $profMap[(string)$i] = (int)($p['id'] ?? 0); $i++; if($i>30) break; }
            $sess['stage'] = 'choose_prof'; $sess['prof_map'] = $profMap; unset($sess['servicos_map']);
            saveSession($SESS_DIR, $remetente, $destinatario, $sess);
            $mensagem .= "\nDigite o *número do profissional*.\n".menuHint();
            sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento); echo "OK"; exit();
        }
    }
    $mensagem = "⚠️ Opção inválida.\n\n".menuHint();
    sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento); echo "OK"; exit();
}

if(($sess['stage'] ?? '') === 'choose_prof'){
    $profMap = $sess['prof_map'] ?? []; $idProf = (int)(mapGet($profMap, $choiceNum) ?? 0);
    if($idProf > 0){
        $sess['selected_prof_id'] = $idProf;
        $sess['stage'] = 'choose_date'; unset($sess['prof_map']);
        saveSession($SESS_DIR, $remetente, $destinatario, $sess);
        $mensagem = "📅 Perfeito! Agora me diga a *data*.\nDigite *DD/MM* ou *DD/MM/AAAA*.\nEx: 29/12\n\n".menuHint();
        sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento); echo "OK"; exit();
    }
    $mensagem = "👤 Escolha o *número do profissional* na lista.\n\n".menuHint();
    sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento); echo "OK"; exit();
}

if(($sess['stage'] ?? '') === 'choose_date'){
    $dateYmd = parseDateToYmd($msg);
    if($dateYmd === ''){
        $mensagem = "📅 Não entendi a data.\nDigite *DD/MM* ou *DD/MM/AAAA*.\nEx: 29/12\n\n".menuHint();
        sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento); echo "OK"; exit();
    }
    if(strtotime($dateYmd.' 00:00:00') < strtotime(date('Y-m-d').' 00:00:00')){
        $mensagem = "📅 Essa data já passou. Digite uma data a partir de hoje.\n\n".menuHint();
        sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento); echo "OK"; exit();
    }
    $idProf = (int)($sess['selected_prof_id'] ?? 0);
    if($idProf <= 0){
        unset($sess['stage'], $sess['selected_date']);
        saveSession($SESS_DIR, $remetente, $destinatario, $sess);
        $mensagem = "⚠️ Vamos recomeçar.\n\n".menuText($nomeSalao, $nomeClienteMenu);
        sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento); echo "OK"; exit();
    }
    if(isDiaBloqueado($pdo, $idProf, $dateYmd)){
        $mensagem = "🚫 Esse dia está *bloqueado* na agenda do profissional.\nDigite *outra data* (DD/MM).\n\n" . menuHint();
        sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento); echo "OK"; exit();
    }
    $times = fetchHorariosDisponiveis($pdo, $idProf, $dateYmd);
    if(empty($times)){
        $mensagem = "⏰ Não encontrei horários livres para esse dia.\nDigite *outra data* (DD/MM).\n\n" . menuHint();
        sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento); echo "OK"; exit();
    }
    $mensagem = "⏰ *Horários disponíveis em* ".date('d/m/Y', strtotime($dateYmd)).":\nDigite o *número do horário*.\n";
    $timeMap = []; $i=1;
    foreach($times as $t){ $mensagem .= bb_opt_line($i, substr($t,0,5)); $timeMap[(string)$i] = $t; $i++; if($i > 60) break; }
    $sess['selected_date'] = $dateYmd; $sess['stage'] = 'choose_time'; $sess['time_map'] = $timeMap;
    saveSession($SESS_DIR, $remetente, $destinatario, $sess);
    $mensagem .= "\nDigite o *número do horário*.\n".menuHint();
    sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento); echo "OK"; exit();
}

if(($sess['stage'] ?? '') === 'choose_time'){
    $dateYmd = (string)($sess['selected_date'] ?? '');
    $idProf  = (int)($sess['selected_prof_id'] ?? 0);
    $timeMap = $sess['time_map'] ?? [];
    if(empty($timeMap) && $idProf > 0 && $dateYmd !== ''){
        $horarios = fetchHorariosDisponiveis($pdo, $idProf, $dateYmd);
        if(!empty($horarios)){
            $timeMap = []; $i = 1; foreach($horarios as $h){ $timeMap[(string)$i] = $h; $i++; }
            $sess['time_map'] = $timeMap; saveSession($SESS_DIR, $remetente, $destinatario, $sess);
        }
    }
    $hora = (string)(mapGet($timeMap, $choiceNum) ?? '');
    if($hora !== '' && $dateYmd === date('Y-m-d')){
        $agora = date('H:i:s');
        if(strtotime($hora) <= strtotime($agora)){
            $mensagem = "⛔ Esse horário já passou. Escolha outro número da lista.\n\n".menuHint();
            sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento); echo "OK"; exit();
        }
    }
    if($hora === ''){
        if(!empty($timeMap)){
            $dataFmt = ($dateYmd !== '') ? date('d/m/Y', strtotime($dateYmd)) : '';
            $mensagem = ($dataFmt !== '') ? "⏰ *Horários disponíveis em* {$dataFmt}:\n" : "⏰ *Horários disponíveis:*\n";
            $i = 1; foreach($timeMap as $t){ $mensagem .= "{$i}) {$t}\n"; $i++; }
            $mensagem .= "\nDigite o *número do horário*.\n".menuHint();
            sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento); echo "OK"; exit();
        }
        $sess['stage'] = 'choose_date'; unset($sess['time_map']); saveSession($SESS_DIR, $remetente, $destinatario, $sess);
        $mensagem = "📅 Não consegui carregar os horários. Me diga a *data* novamente.\nDigite *DD/MM* ou *DD/MM/AAAA*.\n".menuHint();
        sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento); echo "OK"; exit();
    }
    $idServico = (int)($sess['selected_service_id'] ?? 0);
    if($idServico <= 0){
        $mensagem = "⚠️ Serviço inválido. Digite *menu* e tente novamente.\n\n".menuHint();
        sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento); echo "OK"; exit();
    }
    $idCliente = ensureCliente($pdo, $remetente, $nomeClienteMenu);
    if($idCliente <= 0){
        $mensagem = "⚠️ Não consegui salvar seu cadastro agora.\n".menuHint();
        sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento); echo "OK"; exit();
    }
    $idAg = criarAgendamento($pdo, $idCliente, $idProf, $idServico, $dateYmd, $hora, $remetente);
    if($idAg <= 0){
        $mensagem = "❌ Não consegui confirmar seu horário agora. Tente outro horário ou digite *menu*.\n".menuHint();
        sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento); echo "OK"; exit();
    }

    // Busca dados reais p/ confirmar
    $nomeProf = 'Profissional'; $telProf = '';
    if($idProf > 0 && tableExists($pdo, 'usuarios')){
        try{ $st=$pdo->prepare("SELECT nome, telefone FROM usuarios WHERE id=:id LIMIT 1"); $st->execute([':id'=>$idProf]); $prof=$st->fetch(PDO::FETCH_ASSOC); if($prof){ $nomeProf=$prof['nome']??'Profissional'; $telProf=$prof['telefone']??''; } }catch(Exception $e){}
    }
    $nomeServico = 'Serviço';
    if($idServico > 0 && tableExists($pdo, 'servicos')){
        try{ $st=$pdo->prepare("SELECT nome FROM servicos WHERE id=:id LIMIT 1"); $st->execute([':id'=>$idServico]); $nomeServico=$st->fetchColumn()?:'Serviço'; }catch(Exception $e){}
    }
    $dataFmt = ($dateYmd !== '') ? date('d/m/Y', strtotime($dateYmd)) : '';
    $horaFmt = substr($hora, 0, 5);

    deleteSession($SESS_DIR, $remetente, $destinatario);

    $mensagem  = "✅ *Agendamento confirmado!*\n\n";
    $mensagem .= "👤 *Profissional:* {$nomeProf}\n";
    $mensagem .= "💇 *Serviço:* {$nomeServico}\n";
    $mensagem .= "📅 *Data:* {$dataFmt}\n";
    $mensagem .= "⏰ *Horário:* {$horaFmt}\n\n";
    $mensagem .= "Se precisar, digite *menu*.";

    $emptySess = [];
    sendAndRemember($SESS_DIR, $remetente, $destinatario, $emptySess, $remetente, $mensagem, $instanciaEvento);
    // ✅ Agenda a mensagem de confirmação (mesma lógica do pagamento_aprovado.php)
    $antAgendamento = (int)($cfg['minutos_aviso'] ?? 0);
    if($antAgendamento <= 0) $antAgendamento = 2;

    $telefone   = $remetente;
    $data_envio = trim($dateYmd . ' ' . $hora);
    $id_envio   = (string)$idAg;
    $nome       = $nomeSalao;

    $mensagemConfirm  = "📅 Confirmação de Agendamento - *{$nomeSalao}*\n\n";
    $mensagemConfirm .= "Olá, *{$nomeClienteMenu}*!\n\n";
    $mensagemConfirm .= "Você possui um agendamento no dia *{$dataFmt}* às *{$horaFmt}*.\n\n";
    $mensagemConfirm .= "Responda digita o número desejada:\n";
    $mensagemConfirm .= "1. 1️⃣ para Confirmar ✅\n";
    $mensagemConfirm .= "2. 2️⃣ para Cancelar ❌\n\n";
    $mensagemConfirm .= "Obrigado!";

    // confirmacao.php espera: $mensagem, $telefone, $data_envio, $id_envio, $nome e $antAgendamento
    $mensagemBackup = $mensagem;
    $mensagem = $mensagemConfirm;

    // BLINDAGEM: Tenta carregar o arquivo local (da imagem), senão tenta o do ajax, senão manda texto puro
    $arquivoLocal = __DIR__ . '/confirmacao.php';
    $arquivoAjax  = __DIR__ . '/../ajax/confirmacao.php';

    if(file_exists($arquivoLocal)){
        // Prioridade 1: Arquivo na mesma pasta (visto na imagem)
        require($arquivoLocal);
    } 
    elseif(file_exists($arquivoAjax)){
        // Prioridade 2: Arquivo na pasta ajax (citado no meio do código)
        require($arquivoAjax);
    } 
    else {
        // Fallback: Se não achar o arquivo, envia o texto via API para não falhar
        sendTextViaApiTexto($remetente, $mensagemConfirm, $instanciaEvento);
    }

    $mensagem = $mensagemBackup;


    // Notifica dono e profissional
    $telDono = (string)($usuarioCtx['whatsapp_sistema'] ?? $usuarioCtx['telefone'] ?? '');
    $telDono55 = formatPhone55($telDono);
    if($telDono55 !== '' && !bb_isBotAtualTarget($telDono55)){
        sendTextViaApiTexto($telDono55, "📌 Novo agendamento (WhatsApp)\nCliente: {$nomeClienteMenu}\nServiço: {$nomeServico}\nProf: {$nomeProf}\nData: {$dataFmt} {$horaFmt}", $instanciaEvento);
    }
    $telProf55 = formatPhone55($telProf);
    if($telProf55 !== '' && $telProf55 !== $telDono55 && !bb_isBotAtualTarget($telProf55)){
        sendTextViaApiTexto($telProf55, "📌 Novo agendamento (WhatsApp)\nCliente: {$nomeClienteMenu}\nServiço: {$nomeServico}\nData: {$dataFmt} {$horaFmt}", $instanciaEvento);
    }

    echo "OK"; exit();
}

if($optDigit === '1'){
    unset($sess['stage'], $sess['servicos_map'], $sess['prof_map'], $sess['time_map'], $sess['selected_service_id'], $sess['selected_prof_id'], $sess['selected_date']);
    $profs = fetchProfissionais($pdo);
    if(empty($profs)){
        $mensagem = "👤 Não encontrei profissionais cadastrados.\n\n" . menuHint();
    }else{
        $mensagem = "👤 *Profissionais:*\n";
        $map = []; $i=1;
        foreach($profs as $p){ $mensagem .= bb_opt_line($i, ($p['nome'] ?? 'Profissional')); $map[(string)$i] = (int)($p['id'] ?? 0); $i++; if($i>30) break; }
        $sess['stage'] = 'profs_list'; $sess['profs_map'] = $map;
        saveSession($SESS_DIR, $remetente, $destinatario, $sess);
        $mensagem .= "\nDigite o *número do profissional* para ver os serviços/agendar.\n".menuHint();
    }
    sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento); echo "OK"; exit();
}

if($optDigit === '2'){
    unset($sess['stage'], $sess['servicos_map'], $sess['prof_map'], $sess['time_map'], $sess['selected_service_id'], $sess['selected_prof_id'], $sess['selected_date']);
    $servs = fetchServicos($pdo);
    if(empty($servs)){
        $mensagem = "💇‍♂️ Não consegui carregar os serviços.\nVeja e agende aqui: ".$link_agendamento."\n\n".menuHint();
    }else{
        $mensagem = "💇‍♂️ *Serviços e preços:*\nDigite o número do serviço.\n";
        $map = []; $i=1;
        foreach($servs as $s){
            $nomeS = $s['nome'] ?? 'Serviço'; $valor = formatMoneyBR($s['valor'] ?? '');
            $mensagem .= bb_opt_line($i, $nomeS.($valor ? " - *{$valor}*" : "")); $map[(string)$i] = (int)($s['id'] ?? 0); $i++; if($i>80) break;
        }
        $sess['stage'] = 'servicos_list'; $sess['servicos_map'] = $map;
        saveSession($SESS_DIR, $remetente, $destinatario, $sess);
        $mensagem .= "\nDigite o *número do serviço*.\n".menuHint();
    }
    sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento); echo "OK"; exit();
}

if($optDigit === '3'){
    unset($sess['stage'], $sess['servicos_map'], $sess['prof_map'], $sess['time_map'], $sess['selected_service_id'], $sess['selected_prof_id'], $sess['selected_date']);
    $mensagem = "🔗 Para agendar, acesse o link:\n".$link_agendamento."\n\nOu digite *2* para escolher o serviço aqui pelo WhatsApp.\n\n".menuHint();
    sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento); echo "OK"; exit();
}

if(trim((string)$openai_key) !== '' && trim((string)$openai_prompt) !== ''){
    $vars = [
        '{NOME_SALAO}'        => $nomeSalao,
        '{NOME_LOJA}'         => $nomeSalao,
        '{SLUG_LOJA}'         => (string)($GLOBALS['tenantSlug'] ?? ''),
        '{LINK_AGENDAMENTO}'   => $link_agendamento,
        '{NOME_CLIENTE}'      => ($nomeClienteMenu ?? ''),
        '{NOME_PROFISSIONAL}' => ($nomeProfissionalSistema ?? ''),
        '{WHATSAPP_SALAO}'     => ($whatsapp_sistema ?? ''),
        '{MENU}'              => '1 profissionais | 2 serviços | 3 agendar | 4 humano',
    ];
    $promptFinal = strtr((string)$openai_prompt, $vars);
    $promptFinal .= "%0A%0AImportante: NÃO escreva 'Menu:' e NÃO liste opções 1-4. Se o usuário pedir opções, diga apenas: 'Digite menu para ver as opções.'";
    $ia = openaiResposta($openai_key, $promptFinal, $msg, 'gpt-4o-mini');
    if($ia !== ''){
        $ia = strtr($ia, $vars); $ia = stripMenuDuplicadoIA($ia); $textoFinal = trim($ia); if($textoFinal === '') $textoFinal = "Entendi 🙂";
        sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $textoFinal . "\n\n" . menuHint(), $instanciaEvento);
        echo "OK"; exit();
    }
}

$mensagem = "Entendi 🙂\n\n" . menuHint();
sendAndRemember($SESS_DIR, $remetente, $destinatario, $sess, $remetente, $mensagem, $instanciaEvento);
echo "OK";
exit();