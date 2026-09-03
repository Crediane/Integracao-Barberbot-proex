<?php 
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (session_status() !== PHP_SESSION_ACTIVE) {
    @session_start();
}

if (!function_exists('bb_rel_resolver_slug_anotacoes')) {
    function bb_rel_resolver_slug_anotacoes(): string
    {
        $fontes = [];

        foreach (['loja', 'slug'] as $chave) {
            if (isset($_GET[$chave])) {
                $fontes[] = $_GET[$chave];
            }
            if (isset($_POST[$chave])) {
                $fontes[] = $_POST[$chave];
            }
            if (isset($_REQUEST[$chave])) {
                $fontes[] = $_REQUEST[$chave];
            }
        }

        foreach (['tenant_slug', 'loja_slug', 'slug', 'loja'] as $chave) {
            if (isset($_SESSION[$chave])) {
                $fontes[] = $_SESSION[$chave];
            }
        }

        foreach (['BB_TENANT_SLUG', 'tenant_slug', 'loja_slug', 'slug', 'loja'] as $chave) {
            if (isset($_COOKIE[$chave])) {
                $fontes[] = $_COOKIE[$chave];
            }
        }

        foreach (['REQUEST_URI', 'PHP_SELF', 'SCRIPT_NAME', 'HTTP_REFERER'] as $chave) {
            if (!empty($_SERVER[$chave]) && preg_match('~(?:^|/)loja/([^/?#]+)~i', (string)$_SERVER[$chave], $m)) {
                $fontes[] = $m[1];
            }
        }

        foreach ($fontes as $fonte) {
            $slug = trim((string)$fonte);
            if ($slug === '') {
                continue;
            }

            if (preg_match('/^[a-zA-Z0-9._-]+$/', $slug)) {
                return $slug;
            }
        }

        return '';
    }
}

$bbLojaSlug = bb_rel_resolver_slug_anotacoes();
if ($bbLojaSlug !== '') {
    $_GET['loja'] = $bbLojaSlug;
    $_REQUEST['loja'] = $bbLojaSlug;
    $_SESSION['tenant_slug'] = $bbLojaSlug;
    $_SESSION['loja_slug'] = $bbLojaSlug;
    $_SESSION['slug'] = $bbLojaSlug;
    $_SESSION['loja'] = $bbLojaSlug;

    if (!headers_sent()) {
        @setcookie('BB_TENANT_SLUG', $bbLojaSlug, time() + 86400, '/');
        @setcookie('loja', $bbLojaSlug, time() + 86400, '/');
    }
}

include __DIR__ . '/../../conexao.php';
include __DIR__ . '/data_formatada.php';

// BUSQUE os dados institucionais do sistema
$nome_sistema     = $nome_sistema     ?? 'BarberBot';
$telefone_sistema = $telefone_sistema ?? '';
$whatsapp_sistema = $whatsapp_sistema ?? '';

// ID da anotação vindo da lista (para filtrar por cliente)
$id_anotacao = 0;
if (isset($_POST['id']) && is_numeric($_POST['id'])) {
    $id_anotacao = (int) $_POST['id'];
} elseif (isset($_GET['id']) && is_numeric($_GET['id'])) {
    $id_anotacao = (int) $_GET['id'];
}

// Monta filtro por cliente (se id for informado)
$whereCliente = '';
$params       = [];

// Se recebeu um ID de anotação, tenta descobrir o cliente dessa anotação
if ($id_anotacao > 0) {
    $stmtCli = $pdo->prepare("SELECT id_cliente FROM anotacoes WHERE id = :id");
    $stmtCli->bindValue(':id', $id_anotacao, PDO::PARAM_INT);
    $stmtCli->execute();
    $rowCli = $stmtCli->fetch(PDO::FETCH_ASSOC);

    // Se achou cliente, filtra todas as anotações desse cliente
    if ($rowCli && !empty($rowCli['id_cliente'])) {
        $id_cliente = (int)$rowCli['id_cliente'];
        $whereCliente = " WHERE a.id_cliente = :id_cliente ";
        $params[':id_cliente'] = $id_cliente;
    }
}

// Monta SQL principal (com ou sem filtro de cliente)
$sql = "
  SELECT a.*, 
         c.nome  AS cliente_nome, 
         s.nome  AS servico_nome,
         s.valor AS servico_valor,
         u.nome  AS usuario_nome
    FROM anotacoes a
 LEFT JOIN clientes  c ON c.id = a.id_cliente
 LEFT JOIN servicos  s ON s.id = a.id_servico
 LEFT JOIN usuarios  u ON u.id = a.usuario
";

if ($whereCliente != '') {
    $sql .= $whereCliente;
}

$sql .= " ORDER BY a.data DESC ";

// Executa a query
$query = $pdo->prepare($sql);

// Se tiver filtro de cliente, faz o bind
if (!empty($params)) {
    foreach ($params as $chave => $valor) {
        $query->bindValue($chave, $valor, PDO::PARAM_INT);
    }
}

$query->execute();
$res    = $query->fetchAll(PDO::FETCH_ASSOC);
$linhas = @count($res);

$total_valor = 0;
?>
<!DOCTYPE html>
<html>
<head>
<style>
@import url('https://fonts.cdnfonts.com/css/tw-cen-mt-condensed');
@page { margin: 145px 20px 25px 20px; }
#header { position: fixed; left: 0px; top: -110px; bottom: 100px; right: 0px; height: 35px; text-align: center; padding-bottom: 100px; }
#content {margin-top: 0px;}
#footer { position: fixed; left: 0px; bottom: 0; right: 0px; height: 80px; }
#footer .page:after {content: counter(page, my-sec-counter);}
body {font-family: 'Tw Cen MT', sans-serif;}
.marca{ position:fixed; left:50; top:100; width:80%; opacity:8%; }
</style>
</head>
<body>

<div id="header" >
    <div style="border-style: solid; font-size: 10px; height: 50px;">
        <table style="width: 100%; border: 0px solid #ccc;">
            <tr>
                <td style="border: 1px; solid #000; width: 7%; text-align: left;">
                    <img style="margin-top: 7px; margin-left: 7px;" src="<?php echo $url_sistema ?>sistema/img/logo_rel.jpg" width="80px">
                </td>
                <td style="width: 30%; text-align: left; font-size: 13px;">
                </td>
                <td style="width: 47%; text-align: right; font-size: 9px;padding-right: 10px;">
                        <b><big>RELATÓRIO DE ANOTAÇÕES</big></b>
                        <br><br> <?php echo mb_strtoupper((string)($data_hoje ?? '')) ?>
                </td>
            </tr>       
        </table>
    </div>
<br>
    <table id="cabecalhotabela" style="border-bottom-style: solid; font-size: 10px; margin-bottom:10px; width: 100%; table-layout: fixed;">
        <thead>
            <tr style="background-color:#CCC">
                <td style="width:15%">CLIENTE</td>
                <td style="width:15%">SERVIÇO</td>
                <td style="width:10%">VALOR</td>
                <td style="width:20%">TÍTULO</td>
                <td style="width:22%">MENSAGEM</td>
                <td style="width:10%">DATA</td>
                <td style="width:10%">USUÁRIO</td>
            </tr>
        </thead>
    </table>
</div>

<div id="content" style="margin-top: 0;">
    <table style="width: 100%; table-layout: fixed; font-size:9px;">
        <tbody>
        <?php
        if($linhas > 0){
            foreach($res as $linha){
                $cliente = trim((string)($linha['cliente_nome'] ?? ''));
                if ($cliente === '') {
                    $cliente = '---';
                }

                $servico = trim((string)($linha['servico_nome'] ?? ''));
                if ($servico === '') {
                    $servico = '---';
                }

                $valorBruto = $linha['servico_valor'] ?? null;
                $valor = '';
                if ($valorBruto !== null && $valorBruto !== '' && is_numeric($valorBruto)) {
                    $valorNumerico = (float) $valorBruto;
                    $valor = 'R$ ' . number_format($valorNumerico, 2, ',', '.');
                    $total_valor += $valorNumerico;
                }

                $titulo  = (string)($linha['titulo'] ?? '');
                $msg     = strip_tags((string)($linha['msg'] ?? ''));

                $dataBanco = trim((string)($linha['data'] ?? ''));
                if ($dataBanco !== '' && preg_match('/^\d{4}-\d{2}-\d{2}$/', $dataBanco)) {
                    $data = implode('/', array_reverse(explode('-', $dataBanco)));
                } else {
                    $data = '---';
                }

                $usuario = trim((string)($linha['usuario_nome'] ?? ''));
                if ($usuario === '') {
                    $usuario = '---';
                }
        ?>
        <tr>
            <td style="width:15%"><?php echo $cliente ?></td>
            <td style="width:15%"><?php echo $servico ?></td>
            <td style="width:10%"><?php echo $valor ?></td>
            <td style="width:20%"><?php echo $titulo ?></td>
            <td style="width:22%"><?php echo $msg ?></td>
            <td style="width:10%"><?php echo $data ?></td>
            <td style="width:10%"><?php echo $usuario ?></td>
        </tr>
        <?php
            }
        } else {
        ?>
        <tr>
            <td colspan="7" style="text-align:center;">Nenhum dado encontrado!</td>
        </tr>
        <?php } ?>
        </tbody>
    </table>
    <hr>
    <table>
        <tr>
            <td style="font-size: 10px; width:70%; text-align: right;"></td>
            <td style="font-size: 10px; width:15%; text-align: right;">
                <b>Total de Anotações: <span style="color:green"><?php echo $linhas ?></span></b>
            </td>
            <td style="font-size: 10px; width:15%; text-align: right;">
                <b>Total Valor: <span style="color:blue">R$ <?php echo number_format((float)$total_valor, 2, ',', '.') ?></span></b>
            </td>
        </tr>
    </table>
</div>

<div id="footer">
    <hr style="margin-bottom: 0;">
    <table style="width:100%;">
        <tr>
            <td style="width:60%; font-size: 10px; text-align: left;">
                <?php echo $nome_sistema ?> Telefone: <?php echo $whatsapp_sistema ?>
            </td>
            <td style="width:40%; font-size: 10px; text-align: right;">
                <p class="page">Página  </p>
            </td>
        </tr>
        <tr>
            <td colspan="2" style="text-align:center; font-size:10px; padding-top:-50px;">
                <?php echo $nome_sistema ?> Whatsapp: <?php echo $whatsapp_sistema ?>
            </td>
        </tr>
    </table>
</div>
</body>
</html>
