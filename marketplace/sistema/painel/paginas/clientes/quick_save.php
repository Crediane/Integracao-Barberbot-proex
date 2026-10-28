<?php
require_once __DIR__ . '/../_context_bootstrap.php';
header('Content-Type: text/plain; charset=UTF-8');
ini_set('display_errors', 0);

/* ===== conexão ===== */
try{
  require_once __DIR__ . '/../../../conexao.php';
}catch(Throwable $e){
  echo 'ERRO|Falha ao conectar';
  exit;
}

/* ===== helpers ===== */
function only_digits($s){ return preg_replace('/\D+/', '', (string)$s); }

function mask_br_phone($d){
  $d = only_digits($d);
  if(strlen($d) <= 10){
    return preg_replace('/^(\d{2})(\d{4})(\d{0,4})$/', '($1) $2-$3', $d);
  }
  return preg_replace('/^(\d{2})(\d{5})(\d{0,4})$/', '($1) $2-$3', $d);
}

function table_exists(PDO $pdo, $name){
  try{ $st=$pdo->prepare("SHOW TABLES LIKE :t"); $st->execute([':t'=>$name]); return (bool)$st->fetch(PDO::FETCH_NUM); }
  catch(Throwable $e){ return false; }
}
function col_exists(PDO $pdo,$table,$col){
  try{ $st=$pdo->prepare("SHOW COLUMNS FROM `$table` LIKE :c"); $st->execute([':c'=>$col]); return (bool)$st->fetch(PDO::FETCH_ASSOC); }
  catch(Throwable $e){ return false; }
}

/* ===== entrada ===== */
$nome = trim((string)($_POST['nome'] ?? ''));
$zap  = only_digits($_POST['whatsapp'] ?? ''); // somente dígitos

if($nome === '' || strlen($zap) < 10){
  echo 'ERRO|Dados inválidos';
  exit;
}

/* ===== tabela alvo ===== */
$TBL = 'clientes';
if(!table_exists($pdo,$TBL)){
  echo 'ERRO|Tabela clientes não encontrada';
  exit;
}

/* ===== checar duplicidade por dígitos ===== */
$sqlDup = "
  SELECT id, nome
  FROM `$TBL`
  WHERE REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(telefone,'+',''),'(','') ,')','') ,'-','') ,' ','') ,'.','') = :z
  LIMIT 1";
try{
  $st = $pdo->prepare($sqlDup);
  $st->execute([':z'=>$zap]);
  $dup = $st->fetch(PDO::FETCH_ASSOC);
  if($dup){
    echo 'DUP|'.$dup['id'].'|'.$dup['nome'];
    exit;
  }
}catch(Throwable $e){
  echo 'ERRO|Falha ao consultar duplicidade';
  exit;
}

/* ===== montar INSERT dinâmico ===== */
$cols = []; $vals = []; $pars = [];

/* nome */
if(col_exists($pdo,$TBL,'nome')){
  $cols[]='`nome`'; $vals[]=':nome'; $pars[':nome']=$nome;
}

/* telefone (mascarado só para leitura) */
$foneMask = mask_br_phone($zap);
if(col_exists($pdo,$TBL,'telefone')){
  $cols[]='`telefone`'; $vals[]=':tel'; $pars[':tel']=$foneMask;
}

/* data_cad */
if(col_exists($pdo,$TBL,'data_cad')){
  $cols[]='`data_cad`'; $vals[]=':data_cad'; $pars[':data_cad']=date('Y-m-d');
}

/* cartoes */
if(col_exists($pdo,$TBL,'cartoes')){
  $cols[]='`cartoes`'; $vals[]='0';
}

/* alertado */
if(col_exists($pdo,$TBL,'alertado')){
  $cols[]='`alertado`'; $vals[]=':alertado'; $pars[':alertado']='Não';
}

/* senha_crip (senha padrão 123) */
if(col_exists($pdo,$TBL,'senha_crip')){
  $cols[]='`senha_crip`'; $vals[]=':senha_crip';
  $pars[':senha_crip'] = password_hash('123', PASSWORD_DEFAULT);
}

/* marketing (origem) */
if(col_exists($pdo,$TBL,'marketing')){
  $cols[]='`marketing`'; $vals[]=':mkt'; $pars[':mkt']='site_popup';
}

if(empty($cols)){
  echo 'ERRO|Estrutura da tabela inválida';
  exit;
}

/* ===== gravar ===== */
$sql = "INSERT INTO `$TBL` (".implode(',',$cols).") VALUES (".implode(',',$vals).")";
try{
  $st = $pdo->prepare($sql);
  $st->execute($pars);
  $idNovo = (int)$pdo->lastInsertId();
}catch(Throwable $e){
  echo 'ERRO|Falha ao salvar';
  exit;
}

/* ===== enviar WhatsApp (somente novos) ===== */
try{
  // Fallbacks para variáveis globais
  if(!isset($nome_sistema) || !$nome_sistema){
    $nome_sistema = 'Jacy Cabeleireiro';
  }
  if(!isset($url_sistema) || !$url_sistema){
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS']!=='off') ? 'https://' : 'http://';
    $url_sistema = $scheme . ($_SERVER['HTTP_HOST'] ?? 'localhost') . '/';
  }

  // Vars esperadas pelo sender
  $telefone = '55' . $zap; // DDI + número (sem máscara)
  $mensagem  = "👋 *Olá {$nome}*, seja bem-vindo(a) ao *{$nome_sistema}*!\n\n";
  $mensagem .= "🔒 *Use seu whatsapp e senha de acesso:* 123\n\n";
  $mensagem .= "🌐 *Clique abaixo para acessar seu painel:*\n";
  $mensagem .= "{$url_sistema}sistema/acesso";

  // Caminhos possíveis do sender (inclui a RAIZ do domínio)
  $paths = [
    __DIR__ . '/../../../../ajax/api-texto.php',           // /ajax/api-texto.php  (RAIZ)  << MAIS PROVÁVEL
    $_SERVER['DOCUMENT_ROOT'].'/ajax/api-texto.php',       // RAIZ via DOCUMENT_ROOT
    __DIR__ . '/../../../ajax/api-texto.php',              // /sistema/ajax/api-texto.php
    __DIR__ . '/../../ajax/api-texto.php',                 // /sistema/painel/ajax/api-texto.php
    __DIR__ . '/../ajax/api-texto.php',                    // /sistema/painel/paginas/ajax/api-texto.php
  ];

  foreach($paths as $p){
    if(is_file($p)){
      // Evita prints do sender quebrando o retorno "OK|..."
      ob_start();
      require $p;
      ob_end_clean();
      break;
    }
  }
}catch(Throwable $e){
  // silencioso: não interrompe o retorno OK
}

/* ===== retorno ===== */
echo 'OK|'.$idNovo.'|'.$nome;
