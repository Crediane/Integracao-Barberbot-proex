<?php
// ============================================================================
// BarberBot Software - Relatório de Últimos Serviços por Cliente
// Arquivo: /public_html/sistema/painel/rel/rel_servicos_clientes.php
// Correção direta no mesmo padrão /loja/{slug}/
// - sem mudar a lógica do relatório
// - evita avisos Deprecated com valores/data nulos
// - mantém consulta, layout e geração do PDF
// ============================================================================

if (!function_exists('bb_rel_sc_data_segura')) {
    function bb_rel_sc_data_segura($data)
    {
        $data = trim((string)$data);
        if ($data === '' || $data === '0000-00-00') {
            return '';
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $data)) {
            return implode('/', array_reverse(explode('-', $data)));
        }

        return $data;
    }
}

$id = isset($id) ? (int)$id : 0;
$nome_cliente = '';

$query2 = $pdo->query("SELECT * FROM clientes where id = '$id'");
$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
$total_reg2 = @count($res2);
if ($total_reg2 > 0) {
    $nome_cliente = isset($res2[0]['nome']) ? (string)$res2[0]['nome'] : '';
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
                    <b><big>RELATÓRIO DE ÚLTIMOS SERVIÇOS</big></b><br>
                    CLIENTE: <?php echo @mb_strtoupper($nome_cliente) ?> <br>
                    <?php echo @mb_strtoupper($data_hoje) ?>
                </td>
            </tr>
        </table>
    </div>

    <br>

    <table id="cabecalhotabela" style="border-bottom-style: solid; font-size: 8px; margin-bottom:10px; width: 100%; table-layout: fixed;">
        <thead>
            <tr id="cabeca" style="margin-left: 0px; background-color:#CCC">
                <td style="width:30%">DESCRIÇÃO</td>
                <td style="width:15%">VALOR</td>
                <td style="width:15%">DATA PGTO</td>
                <td style="width:25%">RECEBIDO POR</td>
                <td style="width:15%">FORMA PGTO</td>
            </tr>
        </thead>
    </table>
</div>

<div id="footer" class="row">
    <hr style="margin-bottom: 0;">
    <table style="width:100%;">
        <tr style="width:100%;">
            <td style="width:60%; font-size: 10px; text-align: left;"><?php echo $nome_sistema ?> Telefone: <?php echo $whatsapp_sistema ?></td>
            <td style="width:40%; font-size: 10px; text-align: right;"><p class="page">Página</p></td>
        </tr>
    </table>
</div>

<div id="content" style="margin-top: 0;">
    <table style="width: 100%; table-layout: fixed; font-size:9px; text-transform: uppercase;">
        <thead>
            <tbody>
                <?php
                $total_entradas = 0;
                $total_entradasF = number_format(0, 2, ',', '.');

                $query = $pdo->query("SELECT * FROM receber where tipo = 'Serviço' and pago = 'Sim' and pessoa = '$id' ORDER BY data_pgto desc");
                $res = $query->fetchAll(PDO::FETCH_ASSOC);

                $linhas = @count($res);
                if ($linhas > 0) {
                    for ($i = 0; $i < $linhas; $i++) {
                        $id_reg = isset($res[$i]['id']) ? (int)$res[$i]['id'] : 0;
                        $descricao = isset($res[$i]['descricao']) ? (string)$res[$i]['descricao'] : '';
                        $tipo = isset($res[$i]['tipo']) ? (string)$res[$i]['tipo'] : '';
                        $valor = isset($res[$i]['valor']) && $res[$i]['valor'] !== '' ? (float)$res[$i]['valor'] : 0;
                        $data_lanc = isset($res[$i]['data_lanc']) ? (string)$res[$i]['data_lanc'] : '';
                        $data_pgto = isset($res[$i]['data_pgto']) ? (string)$res[$i]['data_pgto'] : '';
                        $data_venc = isset($res[$i]['data_venc']) ? (string)$res[$i]['data_venc'] : '';
                        $usuario_lanc = isset($res[$i]['usuario_lanc']) ? (string)$res[$i]['usuario_lanc'] : '';
                        $usuario_baixa = isset($res[$i]['usuario_baixa']) ? (string)$res[$i]['usuario_baixa'] : '';
                        $foto = isset($res[$i]['foto']) ? (string)$res[$i]['foto'] : '';
                        $pessoa = isset($res[$i]['pessoa']) ? (string)$res[$i]['pessoa'] : '';
                        $pago = isset($res[$i]['pago']) ? (string)$res[$i]['pago'] : '';
                        $servico = isset($res[$i]['servico']) ? (string)$res[$i]['servico'] : '';
                        $pgto = isset($res[$i]['pgto']) ? (string)$res[$i]['pgto'] : '';
                        $comanda = isset($res[$i]['comanda']) && $res[$i]['comanda'] !== '' ? (float)$res[$i]['comanda'] : 0;
                        $valor2 = isset($res[$i]['valor2']) && $res[$i]['valor2'] !== '' ? (float)$res[$i]['valor2'] : 0;

                        if ($comanda > 0) {
                            $valor = $valor2;
                        }

                        $total_entradas += (float)$valor;

                        $valorF = number_format((float)$valor, 2, ',', '.');
                        $total_entradasF = number_format((float)$total_entradas, 2, ',', '.');
                        $data_lancF = bb_rel_sc_data_segura($data_lanc);
                        $data_pgtoF = bb_rel_sc_data_segura($data_pgto);
                        $data_vencF = bb_rel_sc_data_segura($data_venc);

                        $query2 = $pdo->query("SELECT * FROM clientes where id = '$pessoa'");
                        $res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
                        $total_reg2 = @count($res2);
                        if ($total_reg2 > 0) {
                            $nome_pessoa = isset($res2[0]['nome']) ? (string)$res2[0]['nome'] : 'Nenhum!';
                            $telefone_pessoa = isset($res2[0]['telefone']) ? (string)$res2[0]['telefone'] : '';
                            $classe_whats = '';
                        } else {
                            $nome_pessoa = 'Nenhum!';
                            $telefone_pessoa = '';
                            $classe_whats = 'ocultar';
                        }

                        $query2 = $pdo->query("SELECT * FROM usuarios where id = '$usuario_baixa'");
                        $res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
                        $total_reg2 = @count($res2);
                        if ($total_reg2 > 0) {
                            $nome_usuario_pgto = isset($res2[0]['nome']) ? (string)$res2[0]['nome'] : 'Nenhum!';
                        } else {
                            $nome_usuario_pgto = 'Nenhum!';
                        }

                        $query2 = $pdo->query("SELECT * FROM usuarios where id = '$usuario_lanc'");
                        $res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
                        $total_reg2 = @count($res2);
                        if ($total_reg2 > 0) {
                            $nome_usuario_lanc = isset($res2[0]['nome']) ? (string)$res2[0]['nome'] : 'Sem Referência!';
                        } else {
                            $nome_usuario_lanc = 'Sem Referência!';
                        }

                        $query2 = $pdo->query("SELECT * FROM servicos where id = '$servico'");
                        $res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
                        $total_reg2 = @count($res2);
                        if ($total_reg2 > 0) {
                            $nome_servico = isset($res2[0]['nome']) ? (string)$res2[0]['nome'] : '';
                        } else {
                            $nome_servico = '';
                        }
                        ?>
                        <tr>
                            <td style="width:30%"><?php echo $descricao ?></td>
                            <td style="width:15%">R$ <?php echo $valorF ?></td>
                            <td style="width:15%"><?php echo $data_pgtoF ?></td>
                            <td style="width:25%"><?php echo $nome_usuario_pgto ?></td>
                            <td style="width:15%"><?php echo $pgto ?></td>
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
                <td style="font-size: 10px; width:270px; text-align: right;"></td>
                <td style="font-size: 10px; width:120px; text-align: right;"></td>
                <td style="font-size: 10px; width:160px; text-align: right;"><b>TOTAL DE RECEBIMENTOS <span style="color:green"><?php echo $linhas ?></span></b></td>
                <td style="font-size: 10px; width:160px; text-align: right;"><b>TOTAL R$ <span style="color:red"><?php echo $total_entradasF ?></span></b></td>
            </tr>
        </tbody>
    </thead>
</table>

</body>
</html>
