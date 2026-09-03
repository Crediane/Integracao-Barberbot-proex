<?php
declare(strict_types=1);

require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../verificar.php';
require_once __DIR__ . '/../../../conexao.php';
require_once __DIR__ . '/manutencao-protese-funcoes.php';

$entrada = array_merge($_GET, $_POST);

try {
    $resultado = bb_agd_gerar_manutencao_protese($pdo, $entrada, false);
    $resultado['sem_alterar_banco'] = true;
    $resultado['sem_enviar_whatsapp'] = true;
    $resultado['observacao'] = 'Este diagnóstico só valida se o agendamento geraria manutenção automática.';
    bb_agd_json_saida($resultado, !empty($resultado['ok']) ? 200 : 400);
} catch (Throwable $e) {
    bb_agd_json_saida([
        'ok' => false,
        'sem_alterar_banco' => true,
        'sem_enviar_whatsapp' => true,
        'mensagem' => 'Erro inesperado no diagnóstico de manutenção de prótese.',
        'erro' => $e->getMessage()
    ], 500);
}
