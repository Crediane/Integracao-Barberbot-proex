<?php
// ARQUIVO: public_html/marketplace.php
header('Content-Type: text/html; charset=utf-8');

// Página MASTER/Marketplace: não carrega tenant nem banco individual por /loja/{slug}/.
if (session_status() === PHP_SESSION_ACTIVE) {
  if (session_name() !== 'BB_MASTER') {
    session_write_close();
    @session_name('BB_MASTER');
    if (!empty($_COOKIE['BB_MASTER'])) {
      @session_id((string)$_COOKIE['BB_MASTER']);
    }
    @session_start();
  }
} else {
  @session_name('BB_MASTER');
  if (!empty($_COOKIE['BB_MASTER'])) {
    @session_id((string)$_COOKIE['BB_MASTER']);
  }
  @session_start();
}

unset($_GET['loja']);
unset(
  $_SESSION['loja'],
  $_SESSION['tenant_slug'],
  $_SESSION['tenant_id'],
  $_SESSION['tenant_db'],
  $_SESSION['tenant_host'],
  $_SESSION['tenant_user'],
  $_SESSION['tenant_pass']
);

// reCAPTCHA v3 (opcional) - apenas injeta token nos formulários (não altera lógica)
$__bb_recaptcha_file = __DIR__ . '/config/recaptcha.php';
if (file_exists($__bb_recaptcha_file)) {
  require_once $__bb_recaptcha_file;
}

// 1. Carrega variáveis e configurações do sistema
if (file_exists(__DIR__ . "/sistema/conexao.php")) {
  require_once(__DIR__ . "/sistema/conexao.php");
} else {
  $nome_sistema = "BarberBot";
  $logo_sistema = "logo.png";
  $icone_sistema = "favicon.ico";
}

// 2. Carrega configuração do Marketplace (Banco Master)
$configFile = __DIR__ . '/config/marketplace.php';
$cfg = file_exists($configFile) ? include($configFile) : [];

if (!is_array($cfg) || empty($cfg['master'])) {
  require_once __DIR__ . "/marketplace/header.php";
  echo '<main style="padding:100px 5%; text-align:center;"><h2>Configuração do Marketplace não encontrada.</h2></main>';
  require_once __DIR__ . "/marketplace/footer.php";
  exit;
}

// 3. Funções de Apoio
function bb_pdo_connect($host, $db, $user, $pass) {
  try {
    return new PDO("mysql:host={$host};dbname={$db};charset=utf8mb4", $user, $pass, [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]);
  } catch (Throwable $e) {
    return null;
  }
}

function bb_col_exists(PDO $pdo, string $table, string $col): bool {
  try {
    $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t AND COLUMN_NAME = :c LIMIT 1");
    $st->execute([':t' => $table, ':c' => $col]);
    return (bool)$st->fetchColumn();
  } catch (Throwable $e) {
    return false;
  }
}

function bb_table_exists(PDO $pdo, string $table): bool {
  try {
    $st = $pdo->prepare("SELECT 1 FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = :t LIMIT 1");
    $st->execute([':t' => $table]);
    return (bool)$st->fetchColumn();
  } catch (Throwable $e) {
    return false;
  }
}

function bb_clean_host(string $host): string {
  $host = strtolower(trim($host));
  if ($host === '') {
    return '';
  }
  $host = preg_replace('~^https?://~i', '', $host);
  $host = preg_replace('~/.*$~', '', $host);
  $host = preg_replace('~:\d+$~', '', $host);
  return trim($host, ". \t\n\r\0\x0B");
}

function bb_build_custom_url(string $host, string $rota = ''): string {
  $host = bb_clean_host($host);
  if ($host === '') {
    return '';
  }

  $rota = trim($rota, '/');
  $url = 'https://' . $host;

  if ($rota !== '') {
    $url .= '/' . $rota;
  } else {
    $url .= '/';
  }

  return $url;
}

function bb_marketplace_carregar_config_global(array $cfg): void {
  global $nome_sistema,
         $email_sistema,
         $whatsapp_sistema,
         $telefone_sistema,
         $telefone_fixo_sistema,
         $tel_whatsapp,
         $endereco_sistema,
         $logo_sistema,
         $icone_sistema,
         $texto_rodape,
         $cnpj_sistema;

  $conexoes = [];

  $addConexao = function($dados) use (&$conexoes) {
    if (!is_array($dados)) {
      return;
    }

    $host = trim((string)($dados['host'] ?? 'localhost'));
    $db   = trim((string)($dados['db'] ?? ($dados['default_db'] ?? '')));
    $user = trim((string)($dados['user'] ?? ''));
    $pass = (string)($dados['pass'] ?? '');

    if ($host === '' || $db === '' || $user === '') {
      return;
    }

    $chave = $host . '|' . $db . '|' . $user;
    if (!isset($conexoes[$chave])) {
      $conexoes[$chave] = [
        'host' => $host,
        'db'   => $db,
        'user' => $user,
        'pass' => $pass,
      ];
    }
  };

  // Página marketplace fora de /loja/{slug}/ usa configuração global do sistema.
  // Primeiro tenta auth_master, que normalmente aponta para jctecbr_master.config.
  if (!empty($cfg['auth_master']) && is_array($cfg['auth_master'])) {
    $addConexao($cfg['auth_master']);
  }

  // Fallback global do Marketplace. Não utiliza credenciais de banco individual.
  if (!empty($cfg['master']) && is_array($cfg['master'])) {
    $addConexao($cfg['master']);
  }

  foreach ($conexoes as $conexao) {
    try {
      $pdoConfig = bb_pdo_connect($conexao['host'], $conexao['db'], $conexao['user'], $conexao['pass']);
      if (!$pdoConfig || !($pdoConfig instanceof PDO)) {
        continue;
      }

      if (!bb_table_exists($pdoConfig, 'config')) {
        continue;
      }

      $stConfig = $pdoConfig->query("SELECT * FROM config ORDER BY id ASC LIMIT 1");
      $config = $stConfig ? $stConfig->fetch(PDO::FETCH_ASSOC) : false;
      if (!$config || !is_array($config)) {
        continue;
      }

      if (array_key_exists('nome', $config) && trim((string)$config['nome']) !== '') {
        $nome_sistema = trim((string)$config['nome']);
      }

      if (array_key_exists('email', $config) && trim((string)$config['email']) !== '') {
        $email_sistema = trim((string)$config['email']);
      }

      if (array_key_exists('telefone_whatsapp', $config) && trim((string)$config['telefone_whatsapp']) !== '') {
        $whatsapp_sistema = trim((string)$config['telefone_whatsapp']);
      } elseif (array_key_exists('whatsapp', $config) && trim((string)$config['whatsapp']) !== '') {
        $whatsapp_sistema = trim((string)$config['whatsapp']);
      }

      if (array_key_exists('telefone_fixo', $config) && trim((string)$config['telefone_fixo']) !== '') {
        $telefone_fixo_sistema = trim((string)$config['telefone_fixo']);
        $telefone_sistema = trim((string)$config['telefone_fixo']);
      }

      if (array_key_exists('endereco', $config) && trim((string)$config['endereco']) !== '') {
        $endereco_sistema = trim((string)$config['endereco']);
      }

      if (array_key_exists('logo', $config) && trim((string)$config['logo']) !== '') {
        $logo_sistema = trim((string)$config['logo']);
      }

      if (array_key_exists('icone', $config) && trim((string)$config['icone']) !== '') {
        $icone_sistema = trim((string)$config['icone']);
      }

      if (array_key_exists('texto_rodape', $config) && trim((string)$config['texto_rodape']) !== '') {
        $texto_rodape = trim((string)$config['texto_rodape']);
      }

      if (array_key_exists('cnpj', $config) && trim((string)$config['cnpj']) !== '') {
        $cnpj_sistema = trim((string)$config['cnpj']);
      }

      $zapNumerico = preg_replace('/\D+/', '', (string)($whatsapp_sistema ?? ''));
      if ($zapNumerico !== '') {
        if (strlen($zapNumerico) === 10 || strlen($zapNumerico) === 11) {
          $zapNumerico = '55' . $zapNumerico;
        }
        $tel_whatsapp = $zapNumerico;
      }

      return;
    } catch (Throwable $e) {
      // Tenta a próxima conexão sem quebrar o marketplace.
    }
  }
}

bb_marketplace_carregar_config_global($cfg);

// 4. Conexão Master
$pdoMaster = bb_pdo_connect($cfg['master']['host'], $cfg['master']['db'], $cfg['master']['user'], $cfg['master']['pass']);
$tenants = [];
$dominiosTenant = [];

if ($pdoMaster) {
  $table = preg_replace('/[^a-z0-9_]/i', '', $cfg['master']['table'] ?? 'tenants');
  if ($table === '') {
    $table = 'tenants';
  }

  $colNome   = bb_col_exists($pdoMaster, $table, 'nome') ? 'nome' : (bb_col_exists($pdoMaster, $table, 'name') ? 'name' : 'slug');
  $hasStatus = bb_col_exists($pdoMaster, $table, 'status');
  $hasAtivo  = bb_col_exists($pdoMaster, $table, 'ativo');
  $hasCidade = bb_col_exists($pdoMaster, $table, 'cidade');
  $hasEstado = bb_col_exists($pdoMaster, $table, 'estado');

  $cols = ["id", "slug", "`{$colNome}` AS nome"];
  if ($hasCidade) $cols[] = "cidade";
  if ($hasEstado) $cols[] = "estado";
  if ($hasStatus) $cols[] = "status";
  if ($hasAtivo)  $cols[] = "ativo";

  $where = "1=1";
  if ($hasStatus) {
    $where = "status='active'";
  } elseif ($hasAtivo) {
    $where = "ativo=1";
  }

  $sql = "SELECT " . implode(", ", $cols) . " FROM `{$table}` WHERE {$where} ORDER BY `{$colNome}` ASC";

  try {
    $st = $pdoMaster->query($sql);
    $tenants = $st ? $st->fetchAll() : [];
  } catch (Throwable $e) {
    $tenants = [];
  }

  if (bb_table_exists($pdoMaster, 'tenant_domains')) {
    $hasTdAtivo = bb_col_exists($pdoMaster, 'tenant_domains', 'ativo');
    $hasTdPrincipal = bb_col_exists($pdoMaster, 'tenant_domains', 'principal');
    $hasTdTipo = bb_col_exists($pdoMaster, 'tenant_domains', 'tipo');

    $whereDom = [];
    if ($hasTdAtivo) {
      $whereDom[] = "ativo = 1";
    }
    if ($hasTdPrincipal) {
      $whereDom[] = "principal = 1";
    }
    if ($hasTdTipo) {
      $whereDom[] = "tipo = 'dominio'";
    }

    $sqlDom = "SELECT tenant_id, host FROM tenant_domains";
    if (!empty($whereDom)) {
      $sqlDom .= " WHERE " . implode(" AND ", $whereDom);
    }
    $sqlDom .= " ORDER BY id DESC";

    try {
      $stDom = $pdoMaster->query($sqlDom);
      $rowsDom = $stDom ? $stDom->fetchAll() : [];

      foreach ($rowsDom as $rowDom) {
        $tenantId = (int)($rowDom['tenant_id'] ?? 0);
        $host = bb_clean_host((string)($rowDom['host'] ?? ''));

        if ($tenantId <= 0 || $host === '') {
          continue;
        }

        if (!isset($dominiosTenant[$tenantId])) {
          $dominiosTenant[$tenantId] = $host;
        }
      }
    } catch (Throwable $e) {
      $dominiosTenant = [];
    }
  }
}

require_once __DIR__ . "/marketplace/header.php";
?>

<style>
  /* Estilo Geral da Página Marketplace */
  .bb-page { padding: 40px 5% 60px; min-height: 80vh; max-width: 1400px; margin: 0 auto; }

  .bb-hero { text-align: center; margin-bottom: 60px; }
  .bb-hero h1 { font-size: 3rem; margin-bottom: 15px; color: var(--text-light); }
  .bb-hero p { color: var(--text-gray); font-size: 1.1rem; }

  .bb-toolbar { margin-top: 30px; display: flex; justify-content: center; align-items: center; flex-direction: column; gap: 15px; }
  .search-box { position: relative; width: 100%; max-width: 500px; }
  .search-box input {
    width: 100%; padding: 15px 20px 15px 50px; border-radius: 50px;
    background: var(--card-bg); border: 1px solid rgba(255,255,255,0.1);
    color: white; font-size: 1rem; outline: none; transition: 0.3s;
  }
  .search-box input:focus { border-color: var(--secondary); box-shadow: 0 0 15px rgba(37,99,235,0.2); }
  .search-box i { position: absolute; left: 20px; top: 50%; transform: translateY(-50%); color: var(--text-gray); }
  .count-tag { color: var(--accent); font-size: 0.9rem; font-weight: 600; opacity: 0; transition: opacity 0.3s; }

  .bb-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(320px, 1fr)); gap: 30px; }
  .bb-card {
    background: var(--card-bg); border-radius: 16px; padding: 25px;
    border: 1px solid rgba(255,255,255,0.05); display: flex; flex-direction: column;
    transition: 0.3s; position: relative; overflow: hidden;
  }
  .bb-card:hover { transform: translateY(-5px); border-color: var(--secondary); }

  .card-header { display: flex; align-items: center; gap: 15px; margin-bottom: 20px; }
  .shop-icon {
    width: 50px; height: 50px; background: rgba(255,255,255,0.05); border-radius: 12px;
    display: flex; align-items: center; justify-content: center; font-size: 1.5rem; color: var(--secondary);
  }
  .shop-info h3 { color: white; font-size: 1.1rem; margin-bottom: 5px; font-weight: 600; }
  .shop-url { font-size: 0.85rem; color: var(--text-gray); background: rgba(0,0,0,0.3); padding: 4px 8px; border-radius: 4px; }
  .domain-badge { display: inline-block; margin-top: 8px; font-size: 0.72rem; font-weight: 700; color: #93c5fd; background: rgba(37,99,235,0.14); border: 1px solid rgba(37,99,235,0.35); padding: 4px 8px; border-radius: 999px; }

  .card-details { margin-bottom: 25px; flex: 1; }
  .detail-row { display: flex; align-items: center; gap: 8px; color: var(--text-gray); font-size: 0.9rem; margin-bottom: 8px; }
  .detail-row i { color: var(--accent); width: 15px; text-align: center; }

  .card-actions { display: grid; grid-template-columns: repeat(3, 1fr); gap: 10px; margin-top: auto; }
  .card-actions .btn { width: 100%; padding: 10px; font-size: 0.8rem; display: flex; align-items: center; justify-content: center; gap: 5px; }

  .empty-state { text-align: center; padding: 50px; grid-column: 1 / -1; color: var(--text-gray); }
  .empty-state i { font-size: 3rem; margin-bottom: 15px; opacity: 0.5; }

  @media(max-width: 768px) {
    .bb-page { padding-top: 0px; }
    .bb-hero h1 { font-size: 2rem; }
    .card-actions { grid-template-columns: 1fr; }
  }
</style>

<main class="bb-page">
  <section class="bb-hero">
    <h1>Encontre os melhores profissionais da beleza</h1>
    <p>Agende seu horário em barbearias, salões, clínicas de estética e estúdios de manicure perto de você.</p>
    <div class="bb-toolbar">
      <div class="search-box">
        <i class="fa-solid fa-magnifying-glass"></i>
        <input type="text" id="bbSearch" placeholder="Buscar por nome, cidade ou link..." autocomplete="off">
      </div>
      <div id="bbCount" class="count-tag"></div>
    </div>
  </section>

  <section class="bb-grid" id="bbGrid">
    <?php if (!empty($tenants)) { ?>
      <?php foreach ($tenants as $t) {
        $tenantId = (int)($t['id'] ?? 0);
        $slug     = (string)($t['slug'] ?? '');
        if ($slug === '') continue;

        $nome   = (string)($t['nome'] ?? $slug);
        $cidade = $hasCidade ? (string)($t['cidade'] ?? '') : '';
        $estado = $hasEstado ? (string)($t['estado'] ?? '') : '';

        $dominioPersonalizado = isset($dominiosTenant[$tenantId]) ? (string)$dominiosTenant[$tenantId] : '';
        $temDominioProprio = ($dominioPersonalizado !== '');

        if ($temDominioProprio) {
          $urlAgendamento = bb_build_custom_url($dominioPersonalizado, 'agendamentos');
          $urlPortfolio   = bb_build_custom_url($dominioPersonalizado, '');
          $urlSistema     = bb_build_custom_url($dominioPersonalizado, 'sistema');
          $slugExibicao   = '/' . $dominioPersonalizado;
        } else {
          $urlAgendamento = "/loja/" . rawurlencode($slug) . "/agendamentos/";
          $urlPortfolio   = "/loja/" . rawurlencode($slug) . "/home/";
          $urlSistema     = "/loja/" . rawurlencode($slug) . "/sistema/";
          $slugExibicao   = "/" . $slug;
        }

        $searchKey = strtolower(trim($nome . " " . $slug . " " . $cidade . " " . $estado . " " . $dominioPersonalizado));
      ?>
        <article class="bb-card bb-item" data-key="<?php echo htmlspecialchars($searchKey, ENT_QUOTES); ?>">
          <div class="card-header">
            <div class="shop-icon"><i class="fa-solid fa-scissors"></i></div>
            <div class="shop-info">
              <h3><?php echo htmlspecialchars($nome); ?></h3>
              <span class="shop-url"><?php echo htmlspecialchars($slugExibicao); ?></span>
              <?php if ($temDominioProprio) { ?>
                <span class="domain-badge">Domínio Próprio</span>
              <?php } ?>
            </div>
          </div>

          <div class="card-details">
            <?php if ($cidade) { ?>
              <div class="detail-row">
                <i class="fa-solid fa-location-dot"></i>
                <span><?php echo htmlspecialchars($cidade . ($estado ? " - $estado" : "")); ?></span>
              </div>
            <?php } ?>
            <div class="detail-row">
              <i class="fa-solid fa-circle-check" style="color: var(--success);"></i>
              <span>Sistema Ativo</span>
            </div>
          </div>

          <div class="card-actions">
            <a href="<?php echo htmlspecialchars($urlAgendamento); ?>" target="_blank" rel="noopener" class="btn btn-primary">
              <i class="fa-solid fa-calendar-check"></i> Agendar
            </a>
            <a href="<?php echo htmlspecialchars($urlPortfolio); ?>" target="_blank" rel="noopener" class="btn btn-outline">
              <i class="fa-regular fa-images"></i> Portfólio
            </a>
            <a href="<?php echo htmlspecialchars($urlSistema); ?>" target="_blank" rel="noopener" class="btn btn-outline">
              <i class="fa-solid fa-lock"></i> Login
            </a>
          </div>
        </article>
      <?php } ?>
    <?php } else { ?>
      <div class="empty-state">
        <i class="fa-regular fa-folder-open"></i>
        <h3>Nenhuma barbearia encontrada</h3>
        <p>Verifique a conexão com o banco de dados master ou cadastre novas lojas.</p>
      </div>
    <?php } ?>
  </section>
</main>

<script>
document.addEventListener("DOMContentLoaded", function() {
  const input = document.getElementById('bbSearch');
  const items = document.querySelectorAll('.bb-item');
  const count = document.getElementById('bbCount');

  if(!input) return;

  function normalize(v) {
    return (v || '').toString().toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, "").trim();
  }

  input.addEventListener('input', function() {
    const term = normalize(this.value);
    let visible = 0;

    items.forEach(el => {
      const key = normalize(el.getAttribute('data-key'));
      const show = (term === '' || key.indexOf(term) !== -1);
      el.style.display = show ? 'flex' : 'none';
      if(show) visible++;
    });

    if(count) {
      count.textContent = visible > 0 ? `${visible} encontradas` : 'Nenhuma encontrada';
      count.style.opacity = term === '' ? '0' : '1';
    }
  });
});
</script>

<?php if (defined('RECAPTCHA_SITE_KEY') && RECAPTCHA_SITE_KEY) { ?>
<!-- reCAPTCHA v3 (invisível): injeta token recaptcha_token nos formulários, sem bloquear submit -->
<script src="https://www.google.com/recaptcha/api.js?render=<?php echo htmlspecialchars(RECAPTCHA_SITE_KEY, ENT_QUOTES); ?>"></script>
<script>
(function(){
  var SITE_KEY = <?php echo json_encode(RECAPTCHA_SITE_KEY, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
  var ACTION = "marketplace";

  function ensureHidden(form){
    if(!form) return null;
    var inp = form.querySelector('input[name="recaptcha_token"]');
    if(!inp){
      inp = document.createElement('input');
      inp.type = 'hidden';
      inp.name = 'recaptcha_token';
      form.appendChild(inp);
    }
    return inp;
  }

  function setTokenToForms(token){
    var forms = document.querySelectorAll('form');
    for(var i=0;i<forms.length;i++){
      var inp = ensureHidden(forms[i]);
      if(inp) inp.value = token || '';
    }
  }

  function refreshToken(){
    if(typeof grecaptcha === 'undefined' || !grecaptcha.ready) return;
    grecaptcha.ready(function(){
      grecaptcha.execute(SITE_KEY, {action: ACTION}).then(function(token){
        setTokenToForms(token);
      }).catch(function(){
        setTokenToForms('');
      });
    });
  }

  document.addEventListener('DOMContentLoaded', function(){
    refreshToken();
    document.body.addEventListener('click', refreshToken, {passive:true});
    document.body.addEventListener('keydown', refreshToken, {passive:true});
    setInterval(refreshToken, 90000);
  });
})();
</script>
<?php } ?>

<?php require_once __DIR__ . "/marketplace/footer.php"; ?>
