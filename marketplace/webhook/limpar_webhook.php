<?php
/**
 * /public_html/webhook/limpar_webhook.php
 *
 * Limpeza segura dos arquivos temporários do webhook.
 *
 * Regras:
 * - Não apaga as pastas sessions e webhook_dedupe.
 * - Remove apenas arquivos .json antigos dentro dessas pastas.
 * - Mantém sessões recentes para não interromper conversas em andamento.
 * - Limpa o arquivo menuia_gateway_debug.txt quando ficar antigo ou grande.
 * - Permite execução somente pelo Cron/CLI.
 */

declare(strict_types=1);

@date_default_timezone_set('America/Sao_Paulo');
ini_set('display_errors', '0');
error_reporting(E_ALL);

// Impede que alguém abra o arquivo pelo navegador e execute a limpeza.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Acesso negado. Execute este arquivo somente pelo Cron/CLI.\n";
    exit(1);
}

// ============================================================
// CONFIGURAÇÃO DA LIMPEZA
// ============================================================

// Sessões sem alteração há mais de 72 horas serão removidas.
const BB_SESSAO_MAX_IDADE = 72 * 60 * 60;

// O gateway usa deduplicação por poucos segundos.
// Os arquivos de deduplicação com mais de 10 minutos serão removidos.
const BB_DEDUPE_MAX_IDADE = 10 * 60;

// O log será esvaziado quando superar 5 MB.
const BB_DEBUG_MAX_BYTES = 5 * 1024 * 1024;

// O log também será esvaziado quando estiver sem atualização há mais de 24 horas.
const BB_DEBUG_MAX_IDADE = 24 * 60 * 60;

$baseDir = __DIR__;
$sessionsDir = $baseDir . DIRECTORY_SEPARATOR . 'sessions';
$dedupeDir = $baseDir . DIRECTORY_SEPARATOR . 'webhook_dedupe';
$debugFile = $baseDir . DIRECTORY_SEPARATOR . 'menuia_gateway_debug.txt';
$lockFile = $baseDir . DIRECTORY_SEPARATOR . '.limpar_webhook.lock';

/**
 * Formata quantidade de bytes para exibição no resultado do Cron.
 */
function bbFormatBytes(int $bytes): string
{
    if ($bytes < 1024) {
        return $bytes . ' B';
    }

    if ($bytes < 1024 * 1024) {
        return number_format($bytes / 1024, 2, ',', '.') . ' KB';
    }

    return number_format($bytes / (1024 * 1024), 2, ',', '.') . ' MB';
}

/**
 * Remove somente arquivos .json antigos, sem percorrer subpastas.
 *
 * @return array{removidos:int,bytes:int,erros:int}
 */
function bbLimparJsonAntigos(string $diretorio, int $idadeMaxima): array
{
    $resultado = [
        'removidos' => 0,
        'bytes' => 0,
        'erros' => 0,
    ];

    if (!is_dir($diretorio)) {
        return $resultado;
    }

    $agora = time();

    try {
        $itens = new DirectoryIterator($diretorio);
    } catch (Throwable $e) {
        $resultado['erros']++;
        return $resultado;
    }

    foreach ($itens as $item) {
        if ($item->isDot() || $item->isLink() || !$item->isFile()) {
            continue;
        }

        if (strtolower($item->getExtension()) !== 'json') {
            continue;
        }

        $arquivo = $item->getPathname();
        $modificadoEm = $item->getMTime();

        if ($modificadoEm <= 0 || ($agora - $modificadoEm) <= $idadeMaxima) {
            continue;
        }

        $tamanho = $item->getSize();

        if (@unlink($arquivo)) {
            $resultado['removidos']++;
            $resultado['bytes'] += max(0, (int)$tamanho);
        } else {
            $resultado['erros']++;
        }
    }

    return $resultado;
}

/**
 * Esvazia o log somente quando atingir o limite de tamanho ou de idade.
 * O arquivo é mantido porque o gateway continua escrevendo nele.
 *
 * @return array{limpo:bool,bytes:int,erro:bool,motivo:string}
 */
function bbLimparDebug(string $arquivo, int $maxBytes, int $maxIdade): array
{
    $resultado = [
        'limpo' => false,
        'bytes' => 0,
        'erro' => false,
        'motivo' => '',
    ];

    if (!is_file($arquivo)) {
        return $resultado;
    }

    clearstatcache(true, $arquivo);

    $tamanho = @filesize($arquivo);
    $modificadoEm = @filemtime($arquivo);

    $tamanho = ($tamanho === false) ? 0 : (int)$tamanho;
    $modificadoEm = ($modificadoEm === false) ? 0 : (int)$modificadoEm;

    $grande = $tamanho >= $maxBytes;
    $antigo = $modificadoEm > 0 && (time() - $modificadoEm) >= $maxIdade;

    if (!$grande && !$antigo) {
        return $resultado;
    }

    $motivos = [];
    if ($grande) {
        $motivos[] = 'limite de tamanho';
    }
    if ($antigo) {
        $motivos[] = 'limite de idade';
    }

    $handle = @fopen($arquivo, 'c+');
    if ($handle === false) {
        $resultado['erro'] = true;
        return $resultado;
    }

    try {
        if (!@flock($handle, LOCK_EX)) {
            $resultado['erro'] = true;
            return $resultado;
        }

        if (!@ftruncate($handle, 0)) {
            $resultado['erro'] = true;
            return $resultado;
        }

        @rewind($handle);
        @fflush($handle);

        $resultado['limpo'] = true;
        $resultado['bytes'] = $tamanho;
        $resultado['motivo'] = implode(' e ', $motivos);
    } finally {
        @flock($handle, LOCK_UN);
        @fclose($handle);
    }

    return $resultado;
}

// Evita duas execuções simultâneas do Cron.
$lockHandle = @fopen($lockFile, 'c');
if ($lockHandle === false) {
    fwrite(STDERR, "Não foi possível criar o bloqueio da limpeza.\n");
    exit(1);
}

if (!@flock($lockHandle, LOCK_EX | LOCK_NB)) {
    @fclose($lockHandle);
    echo "A limpeza já está sendo executada por outro processo.\n";
    exit(0);
}

$inicio = microtime(true);
$codigoSaida = 0;

try {
    $sessoes = bbLimparJsonAntigos($sessionsDir, BB_SESSAO_MAX_IDADE);
    $dedupe = bbLimparJsonAntigos($dedupeDir, BB_DEDUPE_MAX_IDADE);
    $debug = bbLimparDebug($debugFile, BB_DEBUG_MAX_BYTES, BB_DEBUG_MAX_IDADE);

    $bytesLiberados = $sessoes['bytes'] + $dedupe['bytes'] + $debug['bytes'];
    $erros = $sessoes['erros'] + $dedupe['erros'] + ($debug['erro'] ? 1 : 0);

    echo '[' . date('Y-m-d H:i:s') . "] Limpeza do webhook concluída.\n";
    echo '- Sessions removidas: ' . $sessoes['removidos'] . ' (' . bbFormatBytes($sessoes['bytes']) . ")\n";
    echo '- Dedupe removidos: ' . $dedupe['removidos'] . ' (' . bbFormatBytes($dedupe['bytes']) . ")\n";

    if ($debug['limpo']) {
        echo '- Debug limpo: sim, por ' . $debug['motivo'] . ' (' . bbFormatBytes($debug['bytes']) . ")\n";
    } else {
        echo '- Debug limpo: não era necessário.\n';
    }

    echo '- Total liberado: ' . bbFormatBytes($bytesLiberados) . "\n";
    echo '- Tempo: ' . number_format(microtime(true) - $inicio, 4, ',', '.') . " segundo(s).\n";

    if ($erros > 0) {
        echo '- Aviso: ' . $erros . " item(ns) não puderam ser processados.\n";
        $codigoSaida = 2;
    }
} catch (Throwable $e) {
    fwrite(STDERR, "Falha durante a limpeza do webhook.\n");
    $codigoSaida = 1;
} finally {
    @flock($lockHandle, LOCK_UN);
    @fclose($lockHandle);
}

exit($codigoSaida);
