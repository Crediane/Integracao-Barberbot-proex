<?php
declare(strict_types=1);

require_once __DIR__ . '/../_context_bootstrap.php';
require_once __DIR__ . '/../../verificar.php';
require_once __DIR__ . '/../../../conexao.php';
require_once __DIR__ . '/manutencao-protese-funcoes.php';

if (!function_exists('bb_agd_data_mysql_valida_20_dias')) {
    function bb_agd_data_mysql_valida_20_dias($data): bool
    {
        $data = trim((string)$data);

        if ($data === '' || $data === '0000-00-00') {
            return false;
        }

        $dt = DateTime::createFromFormat('Y-m-d', $data);
        return ($dt && $dt->format('Y-m-d') === $data);
    }
}

if (!function_exists('bb_agd_proxima_manutencao_20_dias')) {
    function bb_agd_proxima_manutencao_20_dias($dataBase): string
    {
        $dataBase = trim((string)$dataBase);

        if (!bb_agd_data_mysql_valida_20_dias($dataBase)) {
            $dataBase = date('Y-m-d');
        }

        try {
            $dt = DateTime::createFromFormat('Y-m-d', $dataBase);
            if (!$dt) {
                return date('Y-m-d', strtotime('+20 days'));
            }

            $dt->modify('+20 days');
            return $dt->format('Y-m-d');
        } catch (Throwable $e) {
            return date('Y-m-d', strtotime('+20 days'));
        }
    }
}

if (!function_exists('bb_agd_data_base_manutencao_20_dias')) {
    function bb_agd_data_base_manutencao_20_dias(PDO $pdo, array $dados): string
    {
        $dataManutencao = isset($dados['data_manutencao']) ? trim((string)$dados['data_manutencao']) : '';
        if (bb_agd_data_mysql_valida_20_dias($dataManutencao)) {
            return $dataManutencao;
        }

        $idAgendamento = isset($dados['id_agd']) ? (int)$dados['id_agd'] : 0;
        if ($idAgendamento > 0) {
            try {
                $query = $pdo->prepare('SELECT data FROM agendamentos WHERE id = :id LIMIT 1');
                $query->bindValue(':id', $idAgendamento, PDO::PARAM_INT);
                $query->execute();
                $dataAgendamento = trim((string)$query->fetchColumn());

                if (bb_agd_data_mysql_valida_20_dias($dataAgendamento)) {
                    return $dataAgendamento;
                }
            } catch (Throwable $e) {
                // Mantém o fluxo original: falha ao buscar a data não bloqueia a conclusão.
            }
        }

        $dataPgto = isset($dados['data_pgto']) ? trim((string)$dados['data_pgto']) : '';
        if (bb_agd_data_mysql_valida_20_dias($dataPgto)) {
            return $dataPgto;
        }

        return date('Y-m-d');
    }
}

try {
    $dadosPost = is_array($_POST) ? $_POST : array();

    // Regra da manutenção automática de prótese:
    // ao confirmar/concluir o agendamento, a próxima manutenção fica para 20 dias após a data do atendimento.
    $dataBaseManutencao = bb_agd_data_base_manutencao_20_dias($pdo, $dadosPost);
    $proximaManutencao = bb_agd_proxima_manutencao_20_dias($dataBaseManutencao);

    $dadosPost['data_manutencao'] = $dataBaseManutencao;
    $dadosPost['data_retorno'] = $proximaManutencao;
    $dadosPost['proxima_manutencao'] = $proximaManutencao;
    $dadosPost['dias_manutencao_protese'] = 20;

    $resultado = bb_agd_gerar_manutencao_protese($pdo, $dadosPost, true);
    bb_agd_json_saida($resultado, !empty($resultado['ok']) ? 200 : 400);
} catch (Throwable $e) {
    bb_agd_json_saida([
        'ok' => false,
        'criado' => false,
        'mensagem' => 'Erro inesperado ao gerar manutenção de prótese.',
        'erro' => $e->getMessage()
    ], 500);
}
