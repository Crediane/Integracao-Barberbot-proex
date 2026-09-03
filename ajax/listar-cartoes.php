<?php
$bb_ajax_context_mode = 'tenant';
$bb_ajax_context_response = 'plain';
require_once __DIR__ . '/_context_bootstrap.php';

/**
 * Retorna o prefixo de instalação do sistema (caso esteja em subpasta),
 * ex.: "/barbeiro" quando o SCRIPT_NAME for "/barbeiro/loja/..."
 * e "" quando o sistema estiver na raiz, ex.: "/loja/..."
 */
function obterPrefixoApp(): string
{
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    $posLoja = strpos($script, '/loja/');
    if ($posLoja === false) {
        // fallback: tenta localizar "/loja" sem a barra final
        $posLoja = strpos($script, '/loja');
        if ($posLoja === false) {
            return '';
        }
    }

    $prefixo = substr($script, 0, $posLoja);
    $prefixo = rtrim($prefixo, '/');

    return $prefixo;
}

/**
 * Resolve a URL (path) correta para a imagem do cartão fidelidade,
 * testando possíveis localizações de assets sem "chute" (usa file_exists).
 *
 * @param string $relativo Caminho relativo do asset a partir de uma pasta (ex.: "images/favicon.png")
 * @return string URL path para uso no atributo src do <img>
 */
function resolverUrlImagemCartao(string $relativo = 'images/favicon.png'): string
{
    $prefixo = obterPrefixoApp(); // "" ou "/barbeiro"
    $docRoot = rtrim($_SERVER['DOCUMENT_ROOT'] ?? '', '/');

    // Possíveis locais onde o asset pode existir (mantendo padrão de pastas do sistema)
    $candidatos = [
        $prefixo . '/loja/' . ltrim($relativo, '/'),
        $prefixo . '/sistema/' . ltrim($relativo, '/'),
        $prefixo . '/' . ltrim($relativo, '/'),
    ];

    foreach ($candidatos as $urlPath) {
        $caminhoFisico = $docRoot . $urlPath;
        if ($docRoot !== '' && is_file($caminhoFisico)) {
            return $urlPath;
        }
    }

    // Último fallback: mantém como estava (não quebra em instalações fora do padrão)
    return $relativo;
}

// Sanitização básica (sem alterar regra de busca existente)
$telefone = filter_input(INPUT_POST, 'tel', FILTER_SANITIZE_SPECIAL_CHARS);
$telefone = trim($telefone ?? '');

if ($telefone === '') {
    // Mantém comportamento de não renderizar nada quando não há telefone
    exit;
}

// Busca cliente (mesma lógica original: LIKE sem curingas)
$stmt = $pdo->prepare("SELECT * FROM clientes WHERE telefone LIKE :telefone LIMIT 1");
$stmt->bindValue(':telefone', $telefone, PDO::PARAM_STR);
$stmt->execute();
$res = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (@count($res) > 0) {
    $cartoes = (int)($res[0]['cartoes'] ?? 0);
    $id_cliente = (int)($res[0]['id'] ?? 0);

    // Não "chuta" valor: apenas evita warning caso não exista no contexto
    $qtdCartoesSistema = isset($quantidade_cartoes) ? (int)$quantidade_cartoes : 0;

    $imgCartao = resolverUrlImagemCartao('images/favicon.png');
    ?>
    <div class="row">
        <?php
        for ($i = 1; $i <= $qtdCartoesSistema; $i++) {
            if ($cartoes >= $i) {
                $valor = 0;   // sem escala de cinza
                $opacity = 1; // visível
            } else {
                $valor = 1;     // escala de cinza
                $opacity = 0.4; // "apagado"
            }
            ?>
            <div class="col-md-1 col-2" align="center">
                <img
                    src="<?php echo htmlspecialchars($imgCartao, ENT_QUOTES, 'UTF-8'); ?>"
                    width="100%"
                    alt="Cartão Fidelidade"
                    style="filter: grayscale(<?php echo $valor; ?>) opacity(<?php echo $opacity; ?>);"
                    loading="lazy"
                >
            </div>
        <?php } ?>
    </div>

    <br>
    <div align="center">
        <small><small>Você possui <?php echo (int)$cartoes; ?> de <?php echo (int)$qtdCartoesSistema; ?> cartões Fidelidade</small></small>
    </div>
<?php } ?>
