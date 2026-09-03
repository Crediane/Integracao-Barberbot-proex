<?php
/**
 * ==========================================================
 * RELATÓRIO DE ANIVERSARIANTES
 * Arquivo: /public_html/sistema/painel/rel/rel_aniv.php
 *
 * ✅ Correções (SEM mudar a lógica do relatório):
 * 1) Evita warning de variável indefinida ($total_reg)
 * 2) Quando o relatório for chamado com ?loja=SLUG (contexto tenant),
 *    garante conexão no banco do estabelecimento (DB individual),
 *    mesmo que o arquivo esteja dentro de /sistema/.
 * ==========================================================
 */
// ===== Sessão isolada por contexto (MASTER x TENANT) =====
// Evita que o cookie padrão (PHPSESSID) misture MASTER x TENANT.
// Regra:
// - /sistema (sem /loja/ e sem ?loja=) => BB_MASTER
// - /loja/<slug>/... OU ?loja=...      => BB_TENANT
// - demais                              => BB_SITE
$__bb_reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$__bb_refPath = '';
if (!empty($_SERVER['HTTP_REFERER'])) {
  $__bb_refPath = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: '';
}


// ===== Sessão TENANT por SLUG (evita misturar lojas no mesmo navegador) =====
if (!function_exists('bb_sess_clean_slug')) {
  function bb_sess_clean_slug($v) {
    $v = strtolower(trim((string)$v));
    return preg_replace('/[^a-z0-9_-]/', '', $v);
  }
}

// garante $__bb_refPath quando o arquivo não define
if (!isset($__bb_refPath)) {
  $__bb_refPath = '';
  if (!empty($_SERVER['HTTP_REFERER'])) {
    $__bb_refPath = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: '';
  }
}

$__bb_reqSlug = '';
if (!empty($_GET['loja'])) {
  $__bb_reqSlug = bb_sess_clean_slug($_GET['loja']);
} elseif (!empty($_POST['loja'])) {
  $__bb_reqSlug = bb_sess_clean_slug($_POST['loja']);
} else {
  if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath, $mSlug)) {
    $__bb_reqSlug = bb_sess_clean_slug($mSlug[1]);
  } elseif (!empty($__bb_refPath) && preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_refPath, $mSlug2)) {
    $__bb_reqSlug = bb_sess_clean_slug($mSlug2[1]);
  }
}

$__bb_tenantSessionName = 'BB_TENANT';
if ($__bb_reqSlug !== '') {
  $__bb_tenantSessionName = 'BB_TENANT_' . strtoupper(substr(sha1($__bb_reqSlug), 0, 12));
}

$__bb_lojaByPath = (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath)
                || (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_refPath);

$__bb_hasLoja = $__bb_lojaByPath || !empty($_GET['loja']) || !empty($_POST['loja']);
$__bb_isSistema = (strpos($__bb_reqPath, '/sistema') === 0) || (strpos($__bb_refPath, '/sistema') === 0);

// Força MASTER em /sistema quando NÃO é loja por PATH e NÃO tem ?loja=
$__bb_forceMaster = ($__bb_isSistema && !$__bb_lojaByPath && empty($_GET['loja']) && empty($_POST['loja']));

$__bb_desiredSession = $__bb_forceMaster ? 'BB_MASTER' : ($__bb_hasLoja ? $__bb_tenantSessionName : 'BB_SITE');

// Inicia/ajusta a sessão corretamente (usa o cookie do nome escolhido)
if (session_status() === PHP_SESSION_ACTIVE) {
  if (session_name() !== $__bb_desiredSession) {
    session_write_close();
    @session_name($__bb_desiredSession);
    if (!empty($_COOKIE[$__bb_desiredSession])) {
      @session_id((string)$_COOKIE[$__bb_desiredSession]);
    }
    @session_start();
  }
} else {
  @session_name($__bb_desiredSession);
  if (!empty($_COOKIE[$__bb_desiredSession])) {
    @session_id((string)$_COOKIE[$__bb_desiredSession]);
  }
  @session_start();
}
// Segurança do relatório
// ===============================
if (($token_rel ?? '') != 'A5030') {
    echo '<script>window.location="../../"</script>';
    exit();
}

// ===============================
// Força tenant (DB individual) se vier ?loja=
// OBS: Não muda regra do sistema. Só garante que o relatório use
// o banco correto quando for chamado para um estabelecimento.
// ===============================
function bb_forcar_tenant_db_se_necessario(): void
{
    // Só tenta se existir ?loja=
    if (empty($_GET['loja'])) {
        return;
    }

    // Se já está com tenant_db na sessão, não precisa fazer nada
    if (!empty($_SESSION['tenant_db'])) {
        return;
    }

    // Carrega crypto (se existir) e config do marketplace
    $cryptoFile = __DIR__ . '/../../tenant_crypto.php';
    if (is_file($cryptoFile)) {
        require_once $cryptoFile;
    }

    $configFile = __DIR__ . '/../../../config/marketplace.php';
    if (!is_file($configFile)) {
        return;
    }

    $cfg = require $configFile;
    if (!is_array($cfg)) {
        return;
    }

    $cfgMaster   = (isset($cfg['master']) && is_array($cfg['master'])) ? $cfg['master'] : [];
    $cfgTenant   = (isset($cfg['tenant']) && is_array($cfg['tenant'])) ? $cfg['tenant'] : [];
    $cfgSecurity = (isset($cfg['security']) && is_array($cfg['security'])) ? $cfg['security'] : [];

    $slug = strtolower(trim((string)$_GET['loja']));
    $slug = preg_replace('/[^a-z0-9_-]/', '', $slug);

    if ($slug === '' || empty($cfgMaster['host']) || empty($cfgMaster['db']) || empty($cfgMaster['user'])) {
        return;
    }

    try {
        // Conecta no MASTER para buscar credenciais do tenant
        $pdoMaster = new PDO(
            "mysql:host={$cfgMaster['host']};dbname={$cfgMaster['db']};charset=utf8mb4",
            $cfgMaster['user'],
            $cfgMaster['pass'] ?? '',
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        $tenantsTable = $cfgMaster['table'] ?? 'tenants';
        $tenantsTable = preg_replace('/[^a-z0-9_]/i', '', (string)$tenantsTable);

        // status/ativo (se não der acesso ao INFORMATION_SCHEMA, segue com 1=1)
        $whereAtivo = "1=1";
        try {
            $stC = $pdoMaster->prepare("
                SELECT COLUMN_NAME
                  FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :t
                   AND COLUMN_NAME IN ('status','ativo')
            ");
            $stC->execute([':t' => $tenantsTable]);
            $cols = $stC->fetchAll(PDO::FETCH_COLUMN);

            if (in_array('ativo', $cols, true)) {
                $whereAtivo = "`ativo`=1";
            } elseif (in_array('status', $cols, true)) {
                $whereAtivo = "`status`='active'";
            }
        } catch (Throwable $e) {
            // mantém 1=1
        }

        $st = $pdoMaster->prepare("SELECT * FROM `{$tenantsTable}` WHERE slug = :s AND {$whereAtivo} LIMIT 1");
        $st->execute([':s' => $slug]);
        $tenant = $st->fetch();

        if (!$tenant) {
            return;
        }

        // Salva sessão (compatível com o padrão do sistema)
        $_SESSION['tenant_id']   = (int)($tenant['id'] ?? 0);
        $_SESSION['tenant_slug'] = (string)($tenant['slug'] ?? $slug);
        $_SESSION['tenant_db']   = (string)($tenant['db_name'] ?? '');

        $_SESSION['tenant_host'] = !empty($tenant['db_host'])
            ? (string)$tenant['db_host']
            : (string)($cfgTenant['host'] ?? 'localhost');

        $_SESSION['tenant_user'] = !empty($tenant['db_user'])
            ? (string)$tenant['db_user']
            : (string)($cfgTenant['user'] ?? '');

        $rawPass = !empty($tenant['db_pass'])
            ? (string)$tenant['db_pass']
            : (string)($cfgTenant['pass'] ?? '');

        // Decrypt (se estiver no formato enc:)
        $tenantPass = $rawPass;
        if ($rawPass !== '' && substr($rawPass, 0, 4) === 'enc:') {
            $secret = (string)($cfgSecurity['tenant_secret'] ?? '');
            $dec = '';
            if ($secret !== '' && function_exists('bb_decrypt_str')) {
                $dec = bb_decrypt_str($rawPass, $secret);
            }
            $tenantPass = ($dec !== '') ? $dec : (string)($cfgTenant['pass'] ?? '');
        }

        $_SESSION['tenant_pass'] = $tenantPass;

        // Conecta no banco do tenant e aplica no $GLOBALS['pdo']
        $banco    = $_SESSION['tenant_db'];
        $servidor = $_SESSION['tenant_host'];
        $usuario  = $_SESSION['tenant_user'];
        $senha    = $_SESSION['tenant_pass'];

        if ($banco === '' || $usuario === '') {
            return;
        }

        $pdoTenant = new PDO(
            "mysql:host={$servidor};dbname={$banco};charset=utf8mb4",
            $usuario,
            $senha,
            [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
            ]
        );

        // Substitui o PDO global do sistema (usado no relatório)
        $GLOBALS['pdo'] = $pdoTenant;

        // Atualiza informações do sistema do tenant (rodapé do relatório)
        try {
            $q = $pdoTenant->query("SELECT * FROM config LIMIT 1");
            $d = $q->fetch(PDO::FETCH_ASSOC);

            if ($d) {
                $GLOBALS['nome_sistema'] = $d['nome'] ?? ($GLOBALS['nome_sistema'] ?? 'BarberBot');
                $GLOBALS['whatsapp_sistema'] = $d['telefone_whatsapp'] ?? ($d['telefone'] ?? ($GLOBALS['whatsapp_sistema'] ?? ''));
            }
        } catch (Throwable $e) {
            // não bloqueia o relatório
        }
    } catch (Throwable $e) {
        // não bloqueia o relatório
        return;
    }
}

// Executa a força de tenant antes de usar o $pdo
bb_forcar_tenant_db_se_necessario();

// ===============================
// Datas
// ===============================
$partesInicial = @explode('-', (string)($dataInicial ?? ''));
$dataDiaInicial = $partesInicial[2] ?? '00';
$dataMesInicial = $partesInicial[1] ?? '00';

$partesFinal = @explode('-', (string)($dataFinal ?? ''));
$dataDiaFinal = $partesFinal[2] ?? '00';
$dataMesFinal = $partesFinal[1] ?? '00';

$dataInicialF = implode('/', array_reverse(@explode('-', (string)($dataInicial ?? ''))));
$dataFinalF   = implode('/', array_reverse(@explode('-', (string)($dataFinal ?? ''))));

if (($dataInicial ?? '') == ($dataFinal ?? '')) {
    $texto_apuracao = 'ANIVERSÁRIANTES DO DIA ' . $dataInicialF;
} else {
    $texto_apuracao = 'ANIVERSÁRIANTES DE ' . $dataInicialF . ' ATÉ ' . $dataFinalF;
}
?>
<!DOCTYPE html>
<html>
<head>
<style>
@import url('https://fonts.cdnfonts.com/css/tw-cen-mt-condensed');
@page { margin: 145px 20px 25px 20px; }
#header { position: fixed; left: 0px; top: -110px; bottom: 100px; right: 0px; height: 35px; text-align: center; padding-bottom: 100px; }
#content {margin-top: 0px;}
#footer { position: fixed; left: 0px; bottom: -60px; right: 0px; height: 80px; }
#footer .page:after {content: counter(page, my-sec-counter);}
body {font-family: 'Tw Cen MT', sans-serif;}
.marca{
    position:fixed;
    left:50;
    top:100;
    width:80%;
    opacity:8%;
}
</style>
</head>
<body>

<div id="header" >
    <div style="border-style: solid; font-size: 10px; height: 50px;">
        <table style="width: 100%; border: 0px solid #ccc;">
            <tr>
                <td style="border: 1px; solid #000; width: 37%; text-align: left;">
                    <img style="margin-top: 7px; margin-left: 7px;" id="imag" src="<?php echo $url_sistema ?>sistema/img/logo_rel.jpg" width="80">
                </td>

                <td style="width: 1%; text-align: center; font-size: 13px;"></td>

                <td style="width: 47%; text-align: right; font-size: 9px;padding-right: 10px;">
                    <b><big>RELATÓRIO DE ANIVERSARIANTES</big></b><br>
                    <?php echo @mb_strtoupper($texto_apuracao) ?> <br>
                    <?php echo @mb_strtoupper($data_hoje ?? '') ?>
                </td>
            </tr>
        </table>
    </div>

    <br>

    <table id="cabecalhotabela" style="border-bottom-style: solid; font-size: 8px; margin-bottom:10px; width: 100%; table-layout: fixed;">
        <thead>
            <tr id="cabeca" style="margin-left: 0px; background-color:#CCC">
                <td style="width:40%">NOME</td>
                <td style="width:15%">TELEFONE</td>
                <td style="width:15%">CADASTRO</td>
                <td style="width:15%">NASCIMENTO</td>
                <td style="width:15%">CARTÕES</td>
            </tr>
        </thead>
    </table>
</div>

<div id="footer" class="row">
    <hr style="margin-bottom: 0;">
    <table style="width:100%;">
        <tr style="width:100%;">
            <td style="width:60%; font-size: 10px; text-align: left;"><?php echo ($nome_sistema ?? '') ?> Telefone: <?php echo ($whatsapp_sistema ?? '') ?></td>
            <td style="width:40%; font-size: 10px; text-align: right;"><p class="page">Página  </p></td>
        </tr>
    </table>
</div>

<div id="content" style="margin-top: 0;">
    <table style="width: 100%; table-layout: fixed; font-size:9px; text-transform: uppercase;">
        <thead>
            <tbody>
<?php
$total_entradas = 0;

$mdInicial = sprintf('%02d-%02d', (int)$dataMesInicial, (int)$dataDiaInicial);
$mdFinal   = sprintf('%02d-%02d', (int)$dataMesFinal, (int)$dataDiaFinal);
$periodoCruzaAno = ($mdInicial > $mdFinal);

if ($periodoCruzaAno) {
    $sqlRelAniv = "SELECT * FROM clientes         WHERE data_nasc IS NOT NULL         AND data_nasc <> '0000-00-00'         AND (DATE_FORMAT(data_nasc, '%m-%d') >= '$mdInicial' OR DATE_FORMAT(data_nasc, '%m-%d') <= '$mdFinal')         ORDER BY DATE_FORMAT(data_nasc, '%m-%d') ASC, id ASC";
} else {
    $sqlRelAniv = "SELECT * FROM clientes         WHERE data_nasc IS NOT NULL         AND data_nasc <> '0000-00-00'         AND DATE_FORMAT(data_nasc, '%m-%d') >= '$mdInicial'         AND DATE_FORMAT(data_nasc, '%m-%d') <= '$mdFinal'         ORDER BY DATE_FORMAT(data_nasc, '%m-%d') ASC, id ASC";
}

$query = $pdo->query($sqlRelAniv);
$res = $query->fetchAll(PDO::FETCH_ASSOC);

$linhas = @count($res);

// ✅ FIX: total do relatório (evita warning no PDF)
$total_reg = (int)$linhas;

if ($linhas > 0) {
    for ($i = 0; $i < $linhas; $i++) {
        $id = $res[$i]['id'];
        $nome = $res[$i]['nome'];
        $data_nasc = $res[$i]['data_nasc'];
        $data_cad = $res[$i]['data_cad'];
        $telefone = $res[$i]['telefone'];
        $endereco = $res[$i]['endereco'];
        $cartoes = $res[$i]['cartoes'];
        $data_retorno = $res[$i]['data_retorno'];
        $ultimo_servico = $res[$i]['ultimo_servico'];

        $data_cadF = implode('/', array_reverse(@explode('-', $data_cad)));
        $data_nascF = implode('/', array_reverse(@explode('-', $data_nasc)));
        $data_retornoF = implode('/', array_reverse(@explode('-', $data_retorno)));

        if ($data_nascF == '00/00/0000') {
            $data_nascF = 'Sem Lançamento';
        }
?>
                <tr>
                    <td style="width:40%"><?php echo $nome ?></td>
                    <td style="width:15%"><?php echo $telefone ?></td>
                    <td style="width:15%"><?php echo $data_cadF ?></td>
                    <td style="width:15%"><?php echo $data_nascF ?></td>
                    <td style="width:15%"><?php echo $cartoes ?></td>
                </tr>
<?php
    }
}
?>
            </tbody>
        </thead>
    </table>
</div>

<hr>

<table>
    <thead>
        <tbody>
            <tr>
                <td style="font-size: 10px; width:300px; text-align: right;"></td>
                <td style="font-size: 10px; width:70px; text-align: right;"></td>
                <td style="font-size: 10px; width:70px; text-align: right;"></td>
                <td style="font-size: 10px; width:80px; text-align: right;"></td>
                <td style="font-size: 10px; width:200px; text-align: right;">
                    <b>TOTAL DE ANIVERSÁRIANTES: <span style="color:red"><?php echo $total_reg ?></span></b>
                </td>
            </tr>
        </tbody>
    </thead>
</table>

</body>
</html>
