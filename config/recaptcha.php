<?php
// /public_html/config/recaptcha.php

/**
 * BarberBot - reCAPTCHA v2/v3 (config + helpers)
 *
 * Separação correta:
 * - MASTER/SITE GLOBAL: lê chaves exclusivamente da tabela config do banco auth_master.
 * - TENANT: lê chaves exclusivamente da tabela config do banco individual já resolvido pelo sistema/conexao.php.
 *
 * O arquivo não escolhe sessão, não cria tenant e não troca banco por conta própria.
 * O mapeamento de /loja/{slug}/, subdomínio, domínio próprio ou ?loja= permanece centralizado em /sistema/conexao.php.
 * Se não houver chave cadastrada no banco correto, o reCAPTCHA fica desativado.
 */

if (!function_exists('bb_recaptcha_clean_slug')) {
  function bb_recaptcha_clean_slug($v): string {
    $v = strtolower(trim((string)$v));
    return (string)preg_replace('/[^a-z0-9_-]/', '', $v);
  }
}

if (!function_exists('bb_recaptcha_explicit_tenant_slugs')) {
  function bb_recaptcha_explicit_tenant_slugs(): array {
    $candidatos = [];

    if (!empty($_GET['loja'])) {
      $candidatos[] = bb_recaptcha_clean_slug($_GET['loja']);
    }
    if (!empty($_POST['loja'])) {
      $candidatos[] = bb_recaptcha_clean_slug($_POST['loja']);
    }

    $path = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $path, $m)) {
      $candidatos[] = bb_recaptcha_clean_slug($m[1]);
    }

    return array_values(array_unique(array_filter($candidatos, static function ($slug) {
      return $slug !== '';
    })));
  }
}

if (!function_exists('bb_recaptcha_detect_tenant_slug')) {
  function bb_recaptcha_detect_tenant_slug(): string {
    $candidatos = bb_recaptcha_explicit_tenant_slugs();
    return count($candidatos) === 1 ? (string)$candidatos[0] : '';
  }
}

if (!function_exists('bb_recaptcha_pdo_connect')) {
  function bb_recaptcha_pdo_connect($host, $db, $user, $pass) {
    try {
      return new PDO(
        'mysql:host=' . $host . ';dbname=' . $db . ';charset=utf8mb4',
        $user,
        $pass,
        [
          PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
          PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
          PDO::ATTR_EMULATE_PREPARES => false,
        ]
      );
    } catch (Throwable $e) {
      return null;
    }
  }
}

if (!function_exists('bb_recaptcha_database_name')) {
  function bb_recaptcha_database_name($pdo): string {
    if (!$pdo || !($pdo instanceof PDO)) {
      return '';
    }

    try {
      return trim((string)$pdo->query('SELECT DATABASE()')->fetchColumn());
    } catch (Throwable $e) {
      return '';
    }
  }
}

if (!function_exists('bb_recaptcha_table_exists')) {
  function bb_recaptcha_table_exists(PDO $pdo, string $table): bool {
    try {
      $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t LIMIT 1");
      $st->execute([':t' => $table]);
      return (bool)$st->fetchColumn();
    } catch (Throwable $e) {
      return false;
    }
  }
}

if (!function_exists('bb_recaptcha_ler_config_pdo')) {
  function bb_recaptcha_ler_config_pdo($pdo): array {
    if (!$pdo || !($pdo instanceof PDO)) {
      return [];
    }

    try {
      if (!bb_recaptcha_table_exists($pdo, 'config')) {
        return [];
      }

      $query = $pdo->query("SELECT * FROM config ORDER BY id DESC LIMIT 1");
      $dados = $query ? ($query->fetch(PDO::FETCH_ASSOC) ?: []) : [];
      return is_array($dados) ? $dados : [];
    } catch (Throwable $e) {
      return [];
    }
  }
}

if (!function_exists('bb_recaptcha_marketplace_config')) {
  function bb_recaptcha_marketplace_config(): array {
    $arquivo = __DIR__ . '/marketplace.php';
    if (!is_file($arquivo)) {
      return [];
    }

    try {
      $cfg = require $arquivo;
      return is_array($cfg) ? $cfg : [];
    } catch (Throwable $e) {
      return [];
    }
  }
}

if (!function_exists('bb_recaptcha_auth_master_pdo')) {
  function bb_recaptcha_auth_master_pdo(array $cfg) {
    $auth = isset($cfg['auth_master']) && is_array($cfg['auth_master']) ? $cfg['auth_master'] : [];

    $host = trim((string)($auth['host'] ?? ''));
    $db   = trim((string)($auth['db'] ?? ''));
    $user = trim((string)($auth['user'] ?? ''));
    $pass = (string)($auth['pass'] ?? '');

    if ($host === '' || $db === '' || $user === '') {
      return null;
    }

    $pdoMaster = bb_recaptcha_pdo_connect($host, $db, $user, $pass);
    if (!($pdoMaster instanceof PDO)) {
      return null;
    }

    return bb_recaptcha_database_name($pdoMaster) === $db ? $pdoMaster : null;
  }
}

if (!function_exists('bb_recaptcha_config_from_db')) {
  function bb_recaptcha_config_from_db(): array {
    static $config = null;
    if (is_array($config)) {
      return $config;
    }

    $config = [
      'tipo' => 'v3',
      'site_key_v2' => '',
      'secret_key_v2' => '',
      'site_key_v3' => '',
      'secret_key_v3' => '',
    ];

    // Toda resolução de rota, sessão, domínio e banco permanece no bootstrap central.
    if (!isset($GLOBALS['pdo']) || !($GLOBALS['pdo'] instanceof PDO)) {
      $conexao = __DIR__ . '/../sistema/conexao.php';
      if (is_file($conexao)) {
        require_once $conexao;
      }
    }

    $slugsExplicitos = bb_recaptcha_explicit_tenant_slugs();
    if (count($slugsExplicitos) > 1) {
      return $config;
    }
    $slugExplicito = $slugsExplicitos[0] ?? '';
    $sessaoTenant = session_status() === PHP_SESSION_ACTIVE
      && strpos((string)session_name(), 'BB_TENANT') === 0;
    $slugSessao = $sessaoTenant ? bb_recaptcha_clean_slug($_SESSION['tenant_slug'] ?? '') : '';
    $dbTenantEsperado = $sessaoTenant ? trim((string)($_SESSION['tenant_db'] ?? '')) : '';

    // Domínio próprio e subdomínio são reconhecidos pelo conexao.php e chegam aqui pela sessão tenant confirmada.
    $isTenant = $sessaoTenant && $slugSessao !== '' && $dbTenantEsperado !== '';

    // Uma rota explícita de loja precisa corresponder ao tenant realmente resolvido.
    if ($slugExplicito !== '' && (!$isTenant || $slugExplicito !== $slugSessao)) {
      return $config;
    }

    $dados = [];

    try {
      if ($isTenant) {
        $pdoTenant = $GLOBALS['pdo'] ?? null;
        if ($pdoTenant instanceof PDO && bb_recaptcha_database_name($pdoTenant) === $dbTenantEsperado) {
          $dados = bb_recaptcha_ler_config_pdo($pdoTenant);
        }
      } else {
        // Fora de tenant, as chaves são exclusivamente do Banco Global/Administrativo.
        $cfgMarketplace = bb_recaptcha_marketplace_config();
        $auth = isset($cfgMarketplace['auth_master']) && is_array($cfgMarketplace['auth_master'])
          ? $cfgMarketplace['auth_master']
          : [];
        $dbMasterEsperado = trim((string)($auth['db'] ?? ''));

        $pdoAtual = $GLOBALS['pdo'] ?? null;
        if ($pdoAtual instanceof PDO
            && $dbMasterEsperado !== ''
            && bb_recaptcha_database_name($pdoAtual) === $dbMasterEsperado) {
          $dados = bb_recaptcha_ler_config_pdo($pdoAtual);
        } else {
          $pdoMaster = bb_recaptcha_auth_master_pdo($cfgMarketplace);
          if ($pdoMaster instanceof PDO) {
            $dados = bb_recaptcha_ler_config_pdo($pdoMaster);
          }
        }
      }

      if (!empty($dados)) {
        $tipo = strtolower(trim((string)($dados['recaptcha_tipo'] ?? '')));
        if ($tipo === 'v2' || $tipo === 'v3') {
          $config['tipo'] = $tipo;
        }

        $mapa = [
          'recaptcha_site_key_v2' => 'site_key_v2',
          'recaptcha_secret_key_v2' => 'secret_key_v2',
          'recaptcha_site_key_v3' => 'site_key_v3',
          'recaptcha_secret_key_v3' => 'secret_key_v3',
        ];

        foreach ($mapa as $coluna => $destino) {
          if (isset($dados[$coluna]) && trim((string)$dados[$coluna]) !== '') {
            $config[$destino] = trim((string)$dados[$coluna]);
          }
        }
      }
    } catch (Throwable $e) {
      // Mantém configuração vazia sem mudar sessão, slug ou banco.
    }

    if ($config['tipo'] !== 'v2' && $config['tipo'] !== 'v3') {
      $config['tipo'] = 'v3';
    }

    return $config;
  }
}

$__bb_recaptcha_config = bb_recaptcha_config_from_db();
$__bb_recaptcha_tipo = $__bb_recaptcha_config['tipo'];
$__bb_recaptcha_site_key = $__bb_recaptcha_tipo === 'v2' ? $__bb_recaptcha_config['site_key_v2'] : $__bb_recaptcha_config['site_key_v3'];
$__bb_recaptcha_secret_key = $__bb_recaptcha_tipo === 'v2' ? $__bb_recaptcha_config['secret_key_v2'] : $__bb_recaptcha_config['secret_key_v3'];

defined('RECAPTCHA_TIPO') || define('RECAPTCHA_TIPO', $__bb_recaptcha_tipo);
defined('RECAPTCHA_SITE_KEY') || define('RECAPTCHA_SITE_KEY', $__bb_recaptcha_site_key);
defined('RECAPTCHA_SECRET_KEY') || define('RECAPTCHA_SECRET_KEY', $__bb_recaptcha_secret_key);
defined('RECAPTCHA_SITE_KEY_V2') || define('RECAPTCHA_SITE_KEY_V2', $__bb_recaptcha_config['site_key_v2']);
defined('RECAPTCHA_SECRET_KEY_V2') || define('RECAPTCHA_SECRET_KEY_V2', $__bb_recaptcha_config['secret_key_v2']);
defined('RECAPTCHA_SITE_KEY_V3') || define('RECAPTCHA_SITE_KEY_V3', $__bb_recaptcha_config['site_key_v3']);
defined('RECAPTCHA_SECRET_KEY_V3') || define('RECAPTCHA_SECRET_KEY_V3', $__bb_recaptcha_config['secret_key_v3']);

/**
 * Torna a validação OBRIGATÓRIA (true) ou “soft” (false).
 * Se false, sua aplicação pode optar por não bloquear quando a verificação falhar.
 */
defined('RECAPTCHA_REQUIRED') || define('RECAPTCHA_REQUIRED', false);

/** Score mínimo aceito (0.0–1.0). */
defined('RECAPTCHA_MIN_SCORE') || define('RECAPTCHA_MIN_SCORE', 0.5);

/**
 * Domínios aceitos (base domains). A checagem aceita o domínio e subdomínios.
 */
$RECAPTCHA_ALLOWED_BASE_DOMAINS = $RECAPTCHA_ALLOWED_BASE_DOMAINS ?? [
  'jc.tec.br',
];

$__bb_recaptcha_host_atual = strtolower(trim((string)($_SERVER['HTTP_HOST'] ?? '')));
$__bb_recaptcha_host_atual = preg_replace('/:\d+$/', '', $__bb_recaptcha_host_atual);
if ($__bb_recaptcha_host_atual !== '') {
  $RECAPTCHA_ALLOWED_BASE_DOMAINS[] = $__bb_recaptcha_host_atual;
}

if (!function_exists('rc_norm')) {
  function rc_norm(string $h): string {
    $h = strtolower(trim($h));
    $h = preg_replace('#^https?://#', '', $h);
    $h = rtrim($h, '/');
    if (strpos($h, 'www.') === 0) $h = substr($h, 4);
    return $h;
  }
}

if (!function_exists('rc_host_permitido')) {
  function rc_host_permitido(string $host, array $baseDomains): bool {
    $host = rc_norm($host);
    if ($host === '') return false;

    foreach ($baseDomains as $base) {
      $base = rc_norm((string)$base);
      if ($base === '') continue;
      if ($host === $base) return true;
      if (substr($host, -strlen('.'.$base)) === '.'.$base) return true;
    }
    return false;
  }
}

if (!function_exists('recaptcha_verify')) {
  /**
   * Verifica o token do reCAPTCHA v3.
   * @param string $token   Token do front-end
   * @param string $action  Ação declarada no front (ex.: 'login'). Se vazio, não exige action.
   * @return array          ['ok'=>bool, 'score'=>float, 'reason'=>string, 'hostname'=>string, 'action'=>string]
   */
  function recaptcha_verify(string $token, string $action = ''): array {
    global $RECAPTCHA_ALLOWED_BASE_DOMAINS;

    $token = trim($token);
    if ($token === '') {
      return ['ok'=>false, 'score'=>0.0, 'reason'=>'token_vazio', 'hostname'=>'', 'action'=>''];
    }

    $post = http_build_query([
      'secret'   => RECAPTCHA_SECRET_KEY,
      'response' => $token,
      'remoteip' => $_SERVER['REMOTE_ADDR'] ?? null,
    ]);

    $resp = null;

    // 1) cURL
    if (function_exists('curl_init')) {
      $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
      curl_setopt_array($ch, [
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $post,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 8,
      ]);
      $resp = curl_exec($ch);
      curl_close($ch);
    }

    // 2) Fallback: file_get_contents
    if ($resp === false || $resp === null) {
      $opts = ['http'=>[
        'method'  => 'POST',
        'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
        'content' => $post,
        'timeout' => 8,
      ]];
      $ctx  = stream_context_create($opts);
      $resp = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $ctx);
    }

    if (!$resp) {
      return ['ok'=>false, 'score'=>0.0, 'reason'=>'sem_resposta_api', 'hostname'=>'', 'action'=>''];
    }

    $json = json_decode($resp, true);
    if (!is_array($json) || empty($json['success'])) {
      return ['ok'=>false, 'score'=>0.0, 'reason'=>'falha_api', 'hostname'=>'', 'action'=>''];
    }

    $hostname  = (string)($json['hostname'] ?? '');
    $apiAction = (string)($json['action']   ?? '');
    $score     = (float)  ($json['score']    ?? 0);
    // ✅ Compatibilidade: reCAPTCHA v2 (checkbox) NÃO retorna score/action.
    // Se não existir 'score' na resposta, consideramos v2 e validamos apenas success + hostname.
    $hasScore = array_key_exists('score', $json);

    if (!$hasScore) {
      if (!rc_host_permitido($hostname, (array)$RECAPTCHA_ALLOWED_BASE_DOMAINS)) {
        return ['ok'=>false, 'score'=>0.0, 'reason'=>'hostname_nao_permitido', 'hostname'=>$hostname, 'action'=>$apiAction];
      }
      // v2 aprovado (não exige action nem score)
      return ['ok'=>true, 'score'=>1.0, 'reason'=>'ok_v2', 'hostname'=>$hostname, 'action'=>$apiAction];
    }


    if (!rc_host_permitido($hostname, (array)$RECAPTCHA_ALLOWED_BASE_DOMAINS)) {
      return ['ok'=>false, 'score'=>$score, 'reason'=>'hostname_nao_permitido', 'hostname'=>$hostname, 'action'=>$apiAction];
    }

    if ($action !== '' && $apiAction !== $action) {
      return ['ok'=>false, 'score'=>$score, 'reason'=>'action_diferente', 'hostname'=>$hostname, 'action'=>$apiAction];
    }

    if ($score < RECAPTCHA_MIN_SCORE) {
      return ['ok'=>false, 'score'=>$score, 'reason'=>'score_baixo', 'hostname'=>$hostname, 'action'=>$apiAction];
    }

    return ['ok'=>true, 'score'=>$score, 'reason'=>'ok', 'hostname'=>$hostname, 'action'=>$apiAction];
  }
}

if (!function_exists('recaptcha_client_v3')) {
  /**
   * Injeta o reCAPTCHA v3 no formulário e envia o token em um input hidden.
   *
   * Exemplo:
   *   require_once __DIR__ . '/config/recaptcha.php';
   *   recaptcha_client_v3('login', '#formLogin');
   *
   * - $action: deve bater com recaptcha_verify($token, $action)
   * - $formSelector: seletor CSS do form
   * - $inputName: nome do input hidden enviado no POST (padrão: recaptcha_token)
   */
  function recaptcha_client_v3(string $action, string $formSelector = 'form', string $inputName = 'recaptcha_token'): void {
    if (PHP_SAPI === 'cli') return;
    if (defined('RECAPTCHA_TIPO') && RECAPTCHA_TIPO !== 'v3') return;

    $siteKey = RECAPTCHA_SITE_KEY;
    if (trim($siteKey) === '') return;

    $jsSiteKey = json_encode($siteKey, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $jsAction  = json_encode($action,  JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $jsFormSel = json_encode($formSelector, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $jsInpName = json_encode($inputName, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

    echo '<script src="https://www.google.com/recaptcha/api.js?render=' . htmlspecialchars($siteKey, ENT_QUOTES) . '"></script>' . "\n";

    // ✅ Usa template com replace para não quebrar por aspas no PHP (corrige o erro da linha ~185)
    $js = <<<'JS'
(function(){
  var SITE_KEY = __SITE_KEY__;
  var ACTION   = __ACTION__;
  var FORM_SEL = __FORM_SEL__;
  var INP_NAME = __INP_NAME__;

  function ensureHidden(form){
    if(!form) return null;
    var inp = form.querySelector('input[name="' + INP_NAME + '"]');
    if(!inp){
      inp = document.createElement('input');
      inp.type = 'hidden';
      inp.name = INP_NAME;
      form.appendChild(inp);
    }
    return inp;
  }

  function getTokenThen(cb){
    if(typeof grecaptcha === 'undefined' || !grecaptcha.ready){
      cb('');
      return;
    }
    grecaptcha.ready(function(){
      grecaptcha.execute(SITE_KEY, {action: ACTION}).then(function(token){
        cb(token || '');
      }).catch(function(){
        cb('');
      });
    });
  }

  function bindForm(form){
    var inp = ensureHidden(form);
    if(!inp) return;

    // gera token ao carregar
    getTokenThen(function(token){ inp.value = token; });

    // atualiza token ao interagir
    var refresh = function(){
      getTokenThen(function(token){ if(token) inp.value = token; });
    };
    form.addEventListener('focusin', refresh, true);
    form.addEventListener('change',  refresh, true);
    form.addEventListener('keyup',   refresh, true);

    // garante token no submit (mantém a lógica original)
    form.addEventListener('submit', function(ev){
      if(inp.value && inp.value.length > 10) return;
      ev.preventDefault();
      getTokenThen(function(token){
        inp.value = token;
        form.submit();
      });
    }, true);

    // renova a cada 90s
    setInterval(refresh, 90000);
  }

  document.addEventListener('DOMContentLoaded', function(){
    var forms = [];
    try {
      forms = Array.prototype.slice.call(document.querySelectorAll(FORM_SEL));
    } catch(e) {}

    if(!forms || forms.length === 0){
      var f = document.querySelector('form');
      if(f) forms = [f];
    }

    for(var i=0;i<forms.length;i++){
      bindForm(forms[i]);
    }
  });
})();
JS;

    $js = str_replace(
      ['__SITE_KEY__', '__ACTION__', '__FORM_SEL__', '__INP_NAME__'],
      [$jsSiteKey, $jsAction, $jsFormSel, $jsInpName],
      $js
    );

    echo "<script>\n{$js}\n</script>\n";
  }
}

if (!function_exists('recaptcha_required_or_soft')) {
  /**
   * Helper opcional:
   * - REQUIRED=true: bloqueia quando falhar
   * - REQUIRED=false: não bloqueia
   */
  function recaptcha_required_or_soft(array $resultado): bool {
    if (RECAPTCHA_REQUIRED === true) {
      return !empty($resultado['ok']);
    }
    return true;
  }
}
