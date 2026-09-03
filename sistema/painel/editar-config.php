<?php
/**
 * /public_html/sistema/painel/editar-config.php
 * FIX: salvar no BANCO DO TENANT quando vier ?loja=... (mesmo rodando em /sistema/*)
 * + FIX: salvar imagens (logo/ícones/banners/favicons) no caminho correto (MASTER x TENANT)
 *
 * - Mantém retorno padrão do sistema: "Editado com Sucesso" / "Erro ao Editar"
 * - Modo debug: ?debug=1 retorna JSON com db/tabela/sql/params/erro/files
 */

$debug = (isset($_GET['debug']) && $_GET['debug'] == '1') ? true : false;

// conexão (usa /sistema/conexao.php que escolhe MASTER x TENANT corretamente)

// ====== TENANT BOOTSTRAP (SEM MUDAR A LÓGICA) ======
// Se a requisição vier do painel da loja (/loja/<slug>/...) e o JS não enviar ?loja=,
// evitamos cair no MASTER lendo o slug do REQUEST_URI/HTTP_REFERER e injetando em $_GET['loja']
// antes de carregar o conexao.php (que decide MASTER x TENANT).
$__bb_slug = '';
if (!empty($_GET['loja'])) {
  $__bb_slug = (string)$_GET['loja'];
} elseif (!empty($_POST['loja'])) {
  $__bb_slug = (string)$_POST['loja'];
} else {
  $p = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '';
  if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $p, $mm)) {
    $__bb_slug = (string)$mm[1];
  } elseif (!empty($_SERVER['HTTP_REFERER'])) {
    $rp = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: '';
    if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $rp, $mm2)) {
      $__bb_slug = (string)$mm2[1];
    }
  }
}
$__bb_slug = strtolower(trim($__bb_slug));
$__bb_slug = (string)preg_replace('/[^a-z0-9_-]/', '', $__bb_slug);
if ($__bb_slug !== '' && empty($_GET['loja'])) {
  $_GET['loja'] = $__bb_slug; // <- chave pro conexao.php entrar no banco do TENANT
}
unset($__bb_slug);
// ====== /TENANT BOOTSTRAP ======

require_once __DIR__ . '/../conexao.php';

// ===== DECODIFICA CAMPOS ENVIADOS EM BASE64 PELO FORM (EVITA BLOQUEIO 403 DO SERVIDOR) =====
// Mantém a lógica original: os nomes dos campos continuam iguais após decodificar.
if (!function_exists('bb_cfg_decode_post_base64')) {
  function bb_cfg_decode_post_base64($valor) {
    if (is_array($valor)) {
      foreach ($valor as $k => $v) {
        $valor[$k] = bb_cfg_decode_post_base64($v);
      }
      return $valor;
    }

    if (!is_string($valor)) {
      return $valor;
    }

    $prefixo = '__BB64__';
    if (strpos($valor, $prefixo) !== 0) {
      return $valor;
    }

    $base64 = substr($valor, strlen($prefixo));
    $decodificado = base64_decode($base64, true);

    return ($decodificado === false) ? '' : $decodificado;
  }
}

foreach ($_POST as $bbPostKey => $bbPostValue) {
  $_POST[$bbPostKey] = bb_cfg_decode_post_base64($bbPostValue);
}

if (!isset($pdo) || !$pdo) {
  if ($debug) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
      'ok' => false,
      'erro' => 'PDO não inicializado (conexao.php)',
      'loja' => $_GET['loja'] ?? null,
      'path' => $_SERVER['REQUEST_URI'] ?? null
    ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  } else {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Erro ao Editar";
  }
  exit();
}

// permissão (padrão do sistema)
if (isset($configuracoes) && $configuracoes == 'ocultar') {
  header('Content-Type: text/plain; charset=utf-8');
  echo "Acesso negado!";
  exit();
}

// helpers
if (!function_exists('bb_cfg_log')) {
  function bb_cfg_log(array $payload): void {
    $dir = __DIR__ . '/_logs';
    if (!is_dir($dir)) {
      @mkdir($dir, 0755, true);
    }
    $file = $dir . '/config_save.log';
    $line = '['.date('Y-m-d H:i:s').'] ' . json_encode($payload, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    if (is_dir($dir) && is_writable($dir)) {
      @file_put_contents($file, $line, FILE_APPEND);
    }
  }
}

if (!function_exists('bb_cfg_table_exists_local')) {
  function bb_cfg_table_exists_local(PDO $pdo, string $tabela): bool {
    $st = $pdo->prepare("SHOW TABLES LIKE :t");
    $st->execute([':t' => $tabela]);
    return (bool)$st->fetchColumn();
  }
}

if (!function_exists('bb_cfg_columns_map')) {
  function bb_cfg_columns_map(PDO $pdo, string $tabela): array {
    $out = [];
    $q = $pdo->query("SHOW COLUMNS FROM `$tabela`");
    $res = $q->fetchAll(PDO::FETCH_ASSOC);
    foreach ($res as $r) {
      $out[strtolower($r['Field'])] = $r; // inclui Type/Null/Default/Extra
    }
    return $out;
  }
}

if (!function_exists('bb_cfg_first_row')) {
  function bb_cfg_first_row(PDO $pdo, string $tabela): array {
    $q = $pdo->query("SELECT * FROM `$tabela` LIMIT 1");
    $r = $q->fetch(PDO::FETCH_ASSOC);
    return $r ? $r : [];
  }
}

if (!function_exists('bb_cfg_ensure_row')) {
  function bb_cfg_ensure_row(PDO $pdo, string $tabela, array $cols): array {
    $row = bb_cfg_first_row($pdo, $tabela);
    if ($row) return $row;

    // cria linha base SEM ASSUMIR colunas fixas
    $insertCols = [];
    $placeholders = [];
    $params = [];

    foreach ($cols as $fieldLower => $info) {
      $field = $info['Field'];
      $null = $info['Null'] ?? 'YES';
      $default = $info['Default'] ?? null;
      $extra = $info['Extra'] ?? '';
      $type = $info['Type'] ?? '';

      if (stripos($extra, 'auto_increment') !== false) continue;

      if ($null === 'NO' && $default === null) {
        $val = '';

        if (preg_match('/int|decimal|float|double/i', $type)) $val = 0;
        elseif (preg_match('/datetime|timestamp/i', $type)) $val = date('Y-m-d H:i:s');
        elseif (preg_match('/date/i', $type)) $val = date('Y-m-d');

        // padrões do sistema (somente se existir a coluna)
        if ($fieldLower === 'ativo' || $fieldLower === 'ativo_sistema') $val = 'Sim';
        if ($fieldLower === 'tipo_rel') $val = 'PDF';

        $insertCols[] = "`$field`";
        $ph = ':c' . count($insertCols);
        $placeholders[] = $ph;
        $params[$ph] = $val;
      }
    }

    try {
      if (count($insertCols) > 0) {
        $sql = "INSERT INTO `$tabela` (" . implode(',', $insertCols) . ") VALUES (" . implode(',', $placeholders) . ")";
        $st = $pdo->prepare($sql);
        $st->execute($params);
      } else {
        $pdo->exec("INSERT INTO `$tabela` () VALUES ()");
      }
    } catch (Throwable $e) {
      // se falhar, mantém row vazia
    }

    return bb_cfg_first_row($pdo, $tabela);
  }
}

// ============================
// CONTEXTO (MASTER x TENANT) - PASTAS DE ARQUIVOS
// ============================
$bbRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');
if ($bbRoot === '') {
  // fallback seguro (caso raro)
  $bbRoot = realpath(__DIR__ . '/../../') ?: '';
}

// tenant slug (sem chutar): pega de GET/POST/SESSION e sanitiza
$tenant = '';
if (!empty($_GET['loja'])) $tenant = (string)$_GET['loja'];
elseif (!empty($_POST['loja'])) $tenant = (string)$_POST['loja'];
elseif (!empty($_SESSION['tenant_slug'])) $tenant = (string)$_SESSION['tenant_slug'];
elseif (!empty($_SESSION['loja'])) $tenant = (string)$_SESSION['loja'];

$tenant = preg_replace('/[^a-z0-9_-]/i', '', $tenant);

// pastas finais (seguindo padrão do sistema)
$dirImg = '';
$dirImages = '';

if ($bbRoot !== '') {
  if ($tenant !== '') {
    $dirImg    = $bbRoot . "/sistema/tenants/{$tenant}/img";
    $dirImages = $bbRoot . "/sistema/tenants/{$tenant}/images";
  } else {
    $dirImg    = $bbRoot . "/sistema/img";
    $dirImages = $bbRoot . "/images";
  }
}

if (!function_exists('bb_cfg_mkdir')) {
  function bb_cfg_mkdir(string $dir): bool {
    if ($dir === '') return false;
    if (is_dir($dir)) return true;
    return @mkdir($dir, 0755, true);
  }
}

if (!function_exists('bb_cfg_upload_image')) {
  /**
   * Upload seguro de imagem.
   * - Valida se é imagem (getimagesize)
   * - Restringe extensão (por tipo real)
   * - Gera nome único ou usa nome fixo (favicons)
   */
  function bb_cfg_upload_image(string $field, string $destDir, array $allowExt, ?string $forceName = null): array {
    $out = ['ok' => false, 'filename' => '', 'error' => ''];

    if (!isset($_FILES[$field])) {
      $out['error'] = 'arquivo_nao_enviado';
      return $out;
    }

    $f = $_FILES[$field];

    if (!isset($f['error']) || $f['error'] === UPLOAD_ERR_NO_FILE) {
      $out['error'] = 'sem_arquivo';
      return $out;
    }

    if ($f['error'] !== UPLOAD_ERR_OK) {
      $out['error'] = 'erro_upload_' . (int)$f['error'];
      return $out;
    }

    $tmp = $f['tmp_name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) {
      $out['error'] = 'tmp_invalido';
      return $out;
    }

    $info = @getimagesize($tmp);
    if ($info === false || empty($info[2])) {
      $out['error'] = 'nao_e_imagem';
      return $out;
    }

    $ext = @image_type_to_extension((int)$info[2], false);
    $ext = strtolower((string)$ext);
    if ($ext === 'jpeg') $ext = 'jpg';

    $allowExt = array_map('strtolower', $allowExt);
    if (!in_array($ext, $allowExt, true)) {
      $out['error'] = 'extensao_nao_permitida';
      return $out;
    }

    if ($destDir === '' || !bb_cfg_mkdir($destDir) || !is_dir($destDir) || !is_writable($destDir)) {
      $out['error'] = 'pasta_sem_permissao';
      return $out;
    }

    // nome do arquivo
    if ($forceName !== null && $forceName !== '') {
      // sanitiza nome fixo
      $name = preg_replace('/[^a-z0-9_.-]/i', '', $forceName);
      if ($name === '') {
        $out['error'] = 'nome_fixo_invalido';
        return $out;
      }
      $filename = $name;
    } else {
      $rand = '';
      try {
        $rand = bin2hex(random_bytes(6));
      } catch (Throwable $e) {
        $rand = (string)mt_rand(100000, 999999);
      }
      $filename = date('YmdHis') . '_' . $rand . '.' . $ext;
    }

    $dest = rtrim($destDir, '/') . '/' . $filename;

    if (!@move_uploaded_file($tmp, $dest)) {
      $out['error'] = 'falha_move_uploaded_file';
      return $out;
    }

    @chmod($dest, 0644);

    $out['ok'] = true;
    $out['filename'] = $filename;
    $out['error'] = '';
    return $out;
  }
}


if (!function_exists('bb_cfg_uploaded_image_ext')) {
  function bb_cfg_uploaded_image_ext(string $field): string {
    if (!isset($_FILES[$field])) return '';
    $tmp = $_FILES[$field]['tmp_name'] ?? '';
    if ($tmp === '' || !is_uploaded_file($tmp)) return '';

    $info = @getimagesize($tmp);
    if ($info === false || empty($info[2])) return '';

    $ext = @image_type_to_extension((int)$info[2], false);
    $ext = strtolower((string)$ext);
    if ($ext === 'jpeg') $ext = 'jpg';

    return $ext;
  }
}

if (!function_exists('bb_cfg_remove_favicon_other_ext')) {
  function bb_cfg_remove_favicon_other_ext(string $destDir, string $baseName, string $extAtual): void {
    $extAtual = strtolower(trim($extAtual));
    foreach (['png', 'webp'] as $ext) {
      if ($ext === $extAtual) continue;
      $file = rtrim($destDir, '/') . '/' . $baseName . '.' . $ext;
      if (is_file($file)) {
        @unlink($file);
      }
    }
  }
}

// ============================
// DB atual / tabela
// ============================
$dbName = '';
try { $dbName = (string)$pdo->query("SELECT DATABASE()")->fetchColumn(); } catch (Throwable $e) {}

$tabela = '';
if (bb_cfg_table_exists_local($pdo, 'config')) $tabela = 'config';
elseif (bb_cfg_table_exists_local($pdo, 'configuracoes')) $tabela = 'configuracoes';

if ($tabela === '') {
  if ($debug) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['ok'=>false,'erro'=>'Tabela config/configuracoes não encontrada','db'=>$dbName], JSON_UNESCAPED_UNICODE|JSON_PRETTY_PRINT);
  } else {
    header('Content-Type: text/plain; charset=utf-8');
    echo "Erro ao Editar";
  }
  exit();
}

$cols = bb_cfg_columns_map($pdo, $tabela);
$rowBefore = bb_cfg_ensure_row($pdo, $tabela, $cols);

// GET debug (sem salvar)
if ($debug && $_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'ok' => true,
    'db' => $dbName,
    'tabela' => $tabela,
    'colunas' => array_keys($cols),
    'tenant' => $tenant,
    'dirImg' => $dirImg,
    'dirImages' => $dirImages
  ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  exit();
}

// ============================
// monta updates (só se coluna existir)
// ============================
$updates = [];

$add = function(array $cands, $val) use (&$updates, $cols) {
  foreach ($cands as $c) {
    $ck = strtolower($c);
    if (isset($cols[$ck])) {
      $updates[$cols[$ck]['Field']] = $val;
    }
  }
};

// sanitização
$S = function($v) {
  if (function_exists('bb_remove_scripts')) return bb_remove_scripts($v);
  $v = preg_replace('~<\s*script[^>]*>.*?<\s*/\s*script\s*>~is', '', (string)$v);
  return trim((string)$v);
};

// ============================
// 1) CAMPOS TEXTO (mantém igual)
// ============================
if (isset($_POST['nome_sistema']))          $add(['nome','nome_sistema'], $S($_POST['nome_sistema']));
if (isset($_POST['email_sistema']))         $add(['email','email_sistema'], $S($_POST['email_sistema']));
if (isset($_POST['telefone_fixo_sistema'])) $add(['telefone_fixo','telefone_fixo_sistema'], $S($_POST['telefone_fixo_sistema']));
if (isset($_POST['whatsapp_sistema']))      $add(['telefone_whatsapp','whatsapp','whatsapp_sistema'], $S($_POST['whatsapp_sistema']));
if (isset($_POST['endereco_sistema']))      $add(['endereco','endereco_sistema'], $S($_POST['endereco_sistema']));
if (isset($_POST['tipo_rel']))              $add(['tipo_rel'], $S($_POST['tipo_rel']));
if (isset($_POST['instagram_sistema']))     $add(['instagram','instagram_sistema'], $S($_POST['instagram_sistema']));
if (isset($_POST['mapa']))                  $add(['mapa'], $S($_POST['mapa']));
if (isset($_POST['texto_rodape']))          $add(['texto_rodape'], $S($_POST['texto_rodape']));
if (isset($_POST['texto_sobre']))           $add(['texto_sobre'], $S($_POST['texto_sobre']));
if (isset($_POST['texto_fidelidade']))      $add(['texto_fidelidade'], $S($_POST['texto_fidelidade']));
if (isset($_POST['quantidade_cartoes']))    $add(['quantidade_cartoes'], (int)$_POST['quantidade_cartoes']);
if (isset($_POST['texto_agendamento']))     $add(['texto_agendamento'], $S($_POST['texto_agendamento']));
if (isset($_POST['msg_agendamento']))       $add(['msg_agendamento'], $S($_POST['msg_agendamento']));
if (isset($_POST['cnpj_sistema']))          $add(['cnpj','cnpj_sistema'], $S($_POST['cnpj_sistema']));
if (isset($_POST['cidade_sistema']))        $add(['cidade','cidade_sistema'], $S($_POST['cidade_sistema']));
if (isset($_POST['agendamento_dias']))      $add(['agendamento_dias'], (int)$_POST['agendamento_dias']);
if (isset($_POST['itens_pag']))             $add(['itens_pag'], (int)$_POST['itens_pag']);
if (isset($_POST['token']))                 $add(['token'], $S($_POST['token']));
if (isset($_POST['minutos_aviso']))         $add(['minutos_aviso'], (int)$_POST['minutos_aviso']);
if (isset($_POST['instancia']))             $add(['instancia'], $S($_POST['instancia']));
if (isset($_POST['url_video']))             $add(['url_video'], $S($_POST['url_video']));
if (isset($_POST['posicao_video']))         $add(['posicao_video'], $S($_POST['posicao_video']));
if (isset($_POST['taxa_sistema']))          $add(['taxa_sistema'], $S($_POST['taxa_sistema']));
if (isset($_POST['lanc_comissao']))         $add(['lanc_comissao'], $S($_POST['lanc_comissao']));
if (isset($_POST['porc_servico']))          $add(['porc_servico'], $S($_POST['porc_servico']));
if (isset($_POST['pgto_api']))              $add(['pgto_api'], $S($_POST['pgto_api']));
if (isset($_POST['api']))                   $add(['api'], $S($_POST['api']));
if (isset($_POST['entrada']))               $add(['entrada'], $S($_POST['entrada']));
if (isset($_POST['opcao_pagar']))           $add(['opcao_pagar'], $S($_POST['opcao_pagar']));
if (isset($_POST['dias_alerta_vencimento'])) {
  // dias antes do vencimento para alertar cliente final.
  // Mantém o mesmo conjunto de opções exibido na tela de configurações.
  // 0 = desativado.
  $diasPermitidosAlerta = [0, 1, 2, 3, 5, 7, 10, 15, 30];
  $dav = (int)$_POST['dias_alerta_vencimento'];
  if (!in_array($dav, $diasPermitidosAlerta, true)) {
    $dav = 3;
  }
  $add(['dias_alerta_vencimento'], $dav);
}
if (isset($_POST['api_pagamento']))         $add(['api_pagamento'], $S($_POST['api_pagamento']));
if (isset($_POST['public_key_mp']))         $add(['public_key_mp'], $S($_POST['public_key_mp']));
if (isset($_POST['access_token_mp']))       $add(['access_token_mp'], $S($_POST['access_token_mp']));
if (isset($_POST['asaas']))                 $add(['asaas'], $S($_POST['asaas']));
if (isset($_POST['nome_bot']))              $add(['nome_bot'], $S($_POST['nome_bot']));
if (isset($_POST['barberbot_ativo']))       $add(['barberbot_ativo'], $S($_POST['barberbot_ativo']));
if (isset($_POST['barberbot_boas_vindas'])) $add(['barberbot_boas_vindas'], $S($_POST['barberbot_boas_vindas']));
if (isset($_POST['openai_key']))            $add(['openai_key'], $S($_POST['openai_key']));
if (isset($_POST['openai_prompt']))         $add(['openai_prompt'], $S($_POST['openai_prompt']));

// ============================
// 2) UPLOADS (sem mudar lógica: só adiciona o que o formulário já envia)
// ============================
$filesSaved = [];
$erro = null;

try {
  // PASTAS precisam existir
  if ($dirImg !== '') bb_cfg_mkdir($dirImg);
  if ($dirImages !== '') bb_cfg_mkdir($dirImages);

  // Logo (*PNG) -> DB: logo / logo_sistema
  if (!$erro && isset($_FILES['foto-logo']) && ($_FILES['foto-logo']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $up = bb_cfg_upload_image('foto-logo', $dirImg, ['png','jpg','jpeg','webp']);
    $filesSaved['foto-logo'] = $up;
    if ($up['ok']) {
      $add(['logo','logo_sistema'], $up['filename']);
    } else {
      $erro = 'upload_logo';
    }
  }

  // Ícone (*PNG) -> DB: icone / icone_sistema
  if (!$erro && isset($_FILES['foto-icone']) && ($_FILES['foto-icone']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $up = bb_cfg_upload_image('foto-icone', $dirImg, ['png','jpg','jpeg','webp']);
    $filesSaved['foto-icone'] = $up;
    if ($up['ok']) {
      $add(['icone','icone_sistema'], $up['filename']);
    } else {
      $erro = 'upload_icone';
    }
  }

  // Logo Relatório (*Jpg) -> DB: logo_rel / logo_relatorio
  if (!$erro && isset($_FILES['foto-logo-rel']) && ($_FILES['foto-logo-rel']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $up = bb_cfg_upload_image('foto-logo-rel', $dirImg, ['png','jpg','jpeg','webp']);
    $filesSaved['foto-logo-rel'] = $up;
    if ($up['ok']) {
      $add(['logo_rel','logo_relatorio'], $up['filename']);
    } else {
      $erro = 'upload_logo_rel';
    }
  }

  // Ícone Site (*png) -> DB: icone_site
  if (!$erro && isset($_FILES['foto-icone-site']) && ($_FILES['foto-icone-site']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $up = bb_cfg_upload_image('foto-icone-site', $dirImages, ['png','jpg','jpeg','webp']);
    $filesSaved['foto-icone-site'] = $up;
    if ($up['ok']) {
      $add(['icone_site'], $up['filename']);
    } else {
      $erro = 'upload_icone_site';
    }
  }

  // Favicon 192 (fixo) -> salva em images/favicon_192.png ou images/favicon_192.webp (não depende de DB)
  if (!$erro && isset($_FILES['foto-favicon192']) && ($_FILES['foto-favicon192']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $extFav192 = bb_cfg_uploaded_image_ext('foto-favicon192');
    $nomeFav192 = ($extFav192 === 'webp') ? 'favicon_192.webp' : 'favicon_192.png';
    $up = bb_cfg_upload_image('foto-favicon192', $dirImages, ['png','webp'], $nomeFav192);
    $filesSaved['foto-favicon192'] = $up;
    if ($up['ok']) {
      bb_cfg_remove_favicon_other_ext($dirImages, 'favicon_192', pathinfo($up['filename'], PATHINFO_EXTENSION));
    } else {
      $erro = 'upload_favicon192';
    }
  }

  // Favicon 512 (fixo) -> salva em images/favicon_512.png ou images/favicon_512.webp (não depende de DB)
  if (!$erro && isset($_FILES['foto-favicon512']) && ($_FILES['foto-favicon512']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $extFav512 = bb_cfg_uploaded_image_ext('foto-favicon512');
    $nomeFav512 = ($extFav512 === 'webp') ? 'favicon_512.webp' : 'favicon_512.png';
    $up = bb_cfg_upload_image('foto-favicon512', $dirImages, ['png','webp'], $nomeFav512);
    $filesSaved['foto-favicon512'] = $up;
    if ($up['ok']) {
      bb_cfg_remove_favicon_other_ext($dirImages, 'favicon_512', pathinfo($up['filename'], PATHINFO_EXTENSION));
    } else {
      $erro = 'upload_favicon512';
    }
  }

  // Imagem Sobre (site) -> DB: imagem_sobre / img_sobre
  if (!$erro && isset($_FILES['foto-sobre']) && ($_FILES['foto-sobre']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $up = bb_cfg_upload_image('foto-sobre', $dirImages, ['png','jpg','jpeg','webp']);
    $filesSaved['foto-sobre'] = $up;
    if ($up['ok']) {
      $add(['imagem_sobre','img_sobre'], $up['filename']);
    } else {
      $erro = 'upload_sobre';
    }
  }

  // Banner Index -> DB: img_banner_index / banner_index
  if (!$erro && isset($_FILES['foto-banner-index']) && ($_FILES['foto-banner-index']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $up = bb_cfg_upload_image('foto-banner-index', $dirImages, ['png','jpg','jpeg','webp']);
    $filesSaved['foto-banner-index'] = $up;
    if ($up['ok']) {
      $add(['img_banner_index','banner_index'], $up['filename']);
    } else {
      $erro = 'upload_banner';
    }
  }

  // Fundo Login -> DB: fundo_login
  if (!$erro && isset($_FILES['fundo_login']) && ($_FILES['fundo_login']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $up = bb_cfg_upload_image('fundo_login', $dirImg, ['png','jpg','jpeg','webp']);
    $filesSaved['fundo_login'] = $up;
    if ($up['ok']) {
      $add(['fundo_login'], $up['filename']);
    } else {
      $erro = 'upload_fundo_login';
    }
  }

} catch (Throwable $e) {
  $erro = 'upload_exception';
}

// ============================
// 3) UPDATE no banco (igual ao padrão)
// ============================
$sql = '';
$params = [];
$affected = 0;

try {
  if ($erro === null && count($updates) > 0) {
    $sets = [];
    foreach ($updates as $col => $val) {
      $ph = ':p_' . preg_replace('/[^a-zA-Z0-9_]/', '_', $col);
      $sets[] = "`$col` = $ph";
      $params[$ph] = $val;
    }

    // se tiver id, usa WHERE
    $idField = isset($cols['id']) ? $cols['id']['Field'] : null;
    $idVal = ($idField && isset($rowBefore[$idField])) ? (int)$rowBefore[$idField] : 0;

    $sql = "UPDATE `$tabela` SET " . implode(', ', $sets);
    if ($idField && $idVal > 0) {
      $sql .= " WHERE `$idField` = :__id LIMIT 1";
      $params[':__id'] = $idVal;
    } else {
      $sql .= " LIMIT 1";
    }

    $st = $pdo->prepare($sql);
    $st->execute($params);
    $affected = $st->rowCount();
  } elseif ($erro !== null) {
    // mantém comportamento padrão (sem mudar lógica)
  }
} catch (Throwable $e) {
  $erro = $e->getMessage();
}

$rowAfter = [];
try { $rowAfter = bb_cfg_first_row($pdo, $tabela); } catch (Throwable $e) {}

// log
bb_cfg_log([
  'tenant' => $tenant,
  'db' => $dbName,
  'tabela' => $tabela,
  'updates_keys' => array_keys($updates),
  'affected_rows' => $affected,
  'files_saved' => $filesSaved,
  'erro' => $erro
]);

if ($debug) {
  header('Content-Type: application/json; charset=utf-8');
  echo json_encode([
    'ok' => ($erro === null),
    'tenant' => $tenant,
    'dirImg' => $dirImg,
    'dirImages' => $dirImages,
    'db' => $dbName,
    'tabela' => $tabela,
    'affected_rows' => $affected,
    'sql' => $sql,
    'params' => $params,
    'files' => $filesSaved,
    'erro' => $erro
  ], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
  exit();
}

header('Content-Type: text/plain; charset=utf-8');
if ($erro === null) echo "Editado com Sucesso";
else echo "Erro ao Editar";
?>
