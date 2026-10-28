<?php
declare(strict_types=1);

/**
 * Funções auxiliares para gerar manutenção de prótese a partir da conclusão
 * de um agendamento.
 *
 * Padrão aplicado:
 * - não altera a lógica financeira do atendimento;
 * - não interfere em recibo, caixa, comissão, assinatura ou pagamento;
 * - apenas cria o registro em manutencoes_protese quando o serviço concluído
 *   for manutenção de prótese;
 * - o aviso via WhatsApp continua sendo responsabilidade do fluxo/cron de
 *   manutenção já existente, que lê alerta_manutencao_enviado = 0.
 */

if (!function_exists('bb_agd_json_saida')) {
    function bb_agd_json_saida(array $dados, int $status = 200): void
    {
        if (ob_get_level() > 0) {
            @ob_clean();
        }

        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }
}

if (!function_exists('bb_agd_somente_numeros')) {
    function bb_agd_somente_numeros($valor): string
    {
        return preg_replace('/\D+/', '', (string)$valor);
    }
}

if (!function_exists('bb_agd_int')) {
    function bb_agd_int($valor): int
    {
        $numero = bb_agd_somente_numeros($valor);
        return $numero === '' ? 0 : (int)$numero;
    }
}

if (!function_exists('bb_agd_texto')) {
    function bb_agd_texto($valor, int $limite = 255): string
    {
        $valor = trim((string)$valor);
        $valor = str_replace(["\0"], '', $valor);

        if ($limite > 0 && strlen($valor) > $limite) {
            $valor = substr($valor, 0, $limite);
        }

        return $valor;
    }
}

if (!function_exists('bb_agd_data_valida')) {
    function bb_agd_data_valida($valor): string
    {
        $valor = trim((string)$valor);

        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $valor)) {
            return '';
        }

        $dt = DateTime::createFromFormat('Y-m-d', $valor);
        if (!$dt || $dt->format('Y-m-d') !== $valor) {
            return '';
        }

        return $valor;
    }
}

if (!function_exists('bb_agd_normalizar')) {
    function bb_agd_normalizar(string $texto): string
    {
        $texto = trim($texto);
        $mapa = [
            'Á'=>'A','À'=>'A','Â'=>'A','Ã'=>'A','Ä'=>'A','á'=>'a','à'=>'a','â'=>'a','ã'=>'a','ä'=>'a',
            'É'=>'E','È'=>'E','Ê'=>'E','Ë'=>'E','é'=>'e','è'=>'e','ê'=>'e','ë'=>'e',
            'Í'=>'I','Ì'=>'I','Î'=>'I','Ï'=>'I','í'=>'i','ì'=>'i','î'=>'i','ï'=>'i',
            'Ó'=>'O','Ò'=>'O','Ô'=>'O','Õ'=>'O','Ö'=>'O','ó'=>'o','ò'=>'o','ô'=>'o','õ'=>'o','ö'=>'o',
            'Ú'=>'U','Ù'=>'U','Û'=>'U','Ü'=>'U','ú'=>'u','ù'=>'u','û'=>'u','ü'=>'u',
            'Ç'=>'C','ç'=>'c'
        ];

        return strtolower(strtr($texto, $mapa));
    }
}

if (!function_exists('bb_agd_tabela_existe')) {
    function bb_agd_tabela_existe(PDO $pdo, string $tabela): bool
    {
        $tabela = preg_replace('/[^a-z0-9_]/i', '', $tabela);
        if ($tabela === '') {
            return false;
        }

        try {
            $stmt = $pdo->prepare('SHOW TABLES LIKE :tabela');
            $stmt->execute([':tabela' => $tabela]);
            return (bool)$stmt->fetchColumn();
        } catch (Throwable $e) {
            return false;
        }
    }
}

if (!function_exists('bb_agd_colunas')) {
    function bb_agd_colunas(PDO $pdo, string $tabela): array
    {
        $tabela = preg_replace('/[^a-z0-9_]/i', '', $tabela);
        if ($tabela === '') {
            return [];
        }

        try {
            $stmt = $pdo->prepare("
                SELECT COLUMN_NAME
                  FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :tabela
            ");
            $stmt->execute([':tabela' => $tabela]);
            $linhas = $stmt->fetchAll(PDO::FETCH_COLUMN);

            $colunas = [];
            foreach ($linhas as $coluna) {
                $colunas[(string)$coluna] = true;
            }

            return $colunas;
        } catch (Throwable $e) {
            return [];
        }
    }
}

if (!function_exists('bb_agd_coluna')) {
    function bb_agd_coluna(array $colunas, array $candidatas): string
    {
        foreach ($candidatas as $coluna) {
            if (isset($colunas[$coluna])) {
                return $coluna;
            }
        }

        return '';
    }
}

if (!function_exists('bb_agd_select_id')) {
    function bb_agd_select_id(PDO $pdo, string $tabela, int $id): array
    {
        $tabela = preg_replace('/[^a-z0-9_]/i', '', $tabela);
        if ($tabela === '' || $id <= 0) {
            return [];
        }

        $stmt = $pdo->prepare("SELECT * FROM `{$tabela}` WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $id]);
        $linha = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($linha) ? $linha : [];
    }
}

if (!function_exists('bb_agd_buscar_servico')) {
    function bb_agd_buscar_servico(PDO $pdo, int $idServico): array
    {
        if ($idServico <= 0 || !bb_agd_tabela_existe($pdo, 'servicos')) {
            return [];
        }

        return bb_agd_select_id($pdo, 'servicos', $idServico);
    }
}

if (!function_exists('bb_agd_buscar_protese')) {
    function bb_agd_buscar_protese(PDO $pdo, int $idProtese, int $idCliente): array
    {
        if (!bb_agd_tabela_existe($pdo, 'proteses')) {
            return [];
        }

        if ($idProtese > 0) {
            $protese = bb_agd_select_id($pdo, 'proteses', $idProtese);
            if (!empty($protese)) {
                return $protese;
            }
        }

        if ($idCliente <= 0) {
            return [];
        }

        $colunas = bb_agd_colunas($pdo, 'proteses');
        $colCliente = bb_agd_coluna($colunas, ['cliente', 'id_cliente', 'cliente_id']);

        if ($colCliente === '') {
            return [];
        }

        $ordem = isset($colunas['id']) ? 'id DESC' : "{$colCliente} DESC";

        $stmt = $pdo->prepare("SELECT * FROM proteses WHERE `{$colCliente}` = :cliente ORDER BY {$ordem} LIMIT 1");
        $stmt->execute([':cliente' => $idCliente]);
        $linha = $stmt->fetch(PDO::FETCH_ASSOC);

        return is_array($linha) ? $linha : [];
    }
}

if (!function_exists('bb_agd_nome_cliente')) {
    function bb_agd_nome_cliente(PDO $pdo, int $idCliente): string
    {
        if ($idCliente <= 0 || !bb_agd_tabela_existe($pdo, 'clientes')) {
            return '';
        }

        $cliente = bb_agd_select_id($pdo, 'clientes', $idCliente);
        if (empty($cliente)) {
            return '';
        }

        foreach (['nome', 'cliente', 'razao_social'] as $campo) {
            if (!empty($cliente[$campo])) {
                return bb_agd_texto($cliente[$campo], 120);
            }
        }

        return '';
    }
}

if (!function_exists('bb_agd_obter_dias_proxima_manutencao')) {
    function bb_agd_obter_dias_proxima_manutencao(PDO $pdo): int
    {
        /*
         * Regra operacional da manutenção de prótese:
         * - a próxima manutenção deve ficar entre 15 e 20 dias;
         * - quando não houver configuração específica, usa 20 dias;
         * - se existir configuração antiga acima de 20, limita para 20;
         * - se existir configuração abaixo de 15, sobe para 15.
         *
         * Isso preserva o fluxo atual e muda apenas o prazo gerado.
         */
        $minimo = 15;
        $maximo = 20;
        $padrao = 20;

        if (!bb_agd_tabela_existe($pdo, 'config')) {
            return $padrao;
        }

        $colunas = bb_agd_colunas($pdo, 'config');
        $candidatas = [
            'dias_manutencao_protese',
            'dias_proxima_manutencao',
            'dias_retorno_manutencao',
            'manutencao_dias'
        ];

        foreach ($candidatas as $campo) {
            if (!isset($colunas[$campo])) {
                continue;
            }

            try {
                $stmt = $pdo->query("SELECT `{$campo}` FROM config LIMIT 1");
                $valor = (int)$stmt->fetchColumn();

                if ($valor > 0) {
                    if ($valor < $minimo) {
                        return $minimo;
                    }

                    if ($valor > $maximo) {
                        return $maximo;
                    }

                    return $valor;
                }
            } catch (Throwable $e) {
                return $padrao;
            }
        }

        return $padrao;
    }
}

if (!function_exists('bb_agd_calcular_proxima_data')) {
    function bb_agd_calcular_proxima_data(string $dataBase, int $dias): string
    {
        $dataBase = bb_agd_data_valida($dataBase);
        if ($dataBase === '') {
            $dataBase = date('Y-m-d');
        }

        if ($dias < 15) {
            $dias = 15;
        }

        if ($dias > 20) {
            $dias = 20;
        }

        $dt = new DateTime($dataBase);
        $dt->modify('+' . $dias . ' days');

        return $dt->format('Y-m-d');
    }
}

if (!function_exists('bb_agd_gerar_manutencao_protese')) {
    function bb_agd_gerar_manutencao_protese(PDO $pdo, array $entrada, bool $executar = true): array
    {
        $idAgendamento = bb_agd_int($entrada['id_agd'] ?? $entrada['id'] ?? 0);
        if ($idAgendamento <= 0) {
            return [
                'ok' => false,
                'criado' => false,
                'mensagem' => 'Agendamento inválido.'
            ];
        }

        if (!bb_agd_tabela_existe($pdo, 'agendamentos')) {
            return [
                'ok' => false,
                'criado' => false,
                'mensagem' => 'Tabela agendamentos não encontrada.'
            ];
        }

        if (!bb_agd_tabela_existe($pdo, 'manutencoes_protese')) {
            return [
                'ok' => false,
                'criado' => false,
                'mensagem' => 'Tabela manutencoes_protese não encontrada.'
            ];
        }

        $agendamento = bb_agd_select_id($pdo, 'agendamentos', $idAgendamento);
        if (empty($agendamento)) {
            return [
                'ok' => false,
                'criado' => false,
                'mensagem' => 'Agendamento não encontrado.'
            ];
        }

        $colAgendamento = bb_agd_colunas($pdo, 'agendamentos');
        $colClienteAgd = bb_agd_coluna($colAgendamento, ['cliente', 'id_cliente', 'cliente_id']);
        $colServicoAgd = bb_agd_coluna($colAgendamento, ['servico', 'id_servico', 'servico_id']);
        $colDataAgd = bb_agd_coluna($colAgendamento, ['data', 'data_agenda', 'data_agendamento', 'data_marcada']);
        $colProteseAgd = bb_agd_coluna($colAgendamento, ['id_protese', 'protese', 'protese_id']);

        $idCliente = bb_agd_int($entrada['cliente'] ?? 0);
        if ($idCliente <= 0 && $colClienteAgd !== '') {
            $idCliente = bb_agd_int($agendamento[$colClienteAgd] ?? 0);
        }

        $idServico = bb_agd_int($entrada['servico'] ?? 0);
        if ($idServico <= 0 && $colServicoAgd !== '') {
            $idServico = bb_agd_int($agendamento[$colServicoAgd] ?? 0);
        }

        $servico = bb_agd_buscar_servico($pdo, $idServico);
        $colServico = !empty($servico) ? bb_agd_colunas($pdo, 'servicos') : [];

        $nomeServico = bb_agd_texto($entrada['servico_nome'] ?? '', 180);
        if ($nomeServico === '' && !empty($servico)) {
            $campoNomeServico = bb_agd_coluna($colServico, ['nome', 'servico', 'descricao', 'titulo']);
            if ($campoNomeServico !== '') {
                $nomeServico = bb_agd_texto($servico[$campoNomeServico] ?? '', 180);
            }
        }

        if ($nomeServico === '' && $colServicoAgd !== '' && !is_numeric((string)($agendamento[$colServicoAgd] ?? ''))) {
            $nomeServico = bb_agd_texto($agendamento[$colServicoAgd] ?? '', 180);
        }

        $idProtese = bb_agd_int($entrada['id_protese'] ?? $entrada['protese'] ?? 0);
        if ($idProtese <= 0 && $colProteseAgd !== '') {
            $idProtese = bb_agd_int($agendamento[$colProteseAgd] ?? 0);
        }

        $protese = bb_agd_buscar_protese($pdo, $idProtese, $idCliente);
        if (!empty($protese)) {
            $idProtese = bb_agd_int($protese['id'] ?? $idProtese);
        }

        $textoServicoNormalizado = bb_agd_normalizar($nomeServico);
        $ehManutencao = (strpos($textoServicoNormalizado, 'manutenc') !== false);
        $ehProtese = (strpos($textoServicoNormalizado, 'protese') !== false || strpos($textoServicoNormalizado, 'protese') !== false || $idProtese > 0);

        if (!$ehManutencao || !$ehProtese) {
            return [
                'ok' => true,
                'criado' => false,
                'ignorado' => true,
                'motivo' => 'Serviço não identificado como manutenção de prótese.',
                'servico_nome' => $nomeServico
            ];
        }

        if ($idCliente <= 0) {
            return [
                'ok' => false,
                'criado' => false,
                'mensagem' => 'Cliente não identificado no agendamento.'
            ];
        }

        if ($idProtese <= 0) {
            return [
                'ok' => false,
                'criado' => false,
                'mensagem' => 'Cliente sem prótese cadastrada para gerar manutenção automática.'
            ];
        }

        $dataManutencao = bb_agd_data_valida($entrada['data_pgto'] ?? '');
        if ($dataManutencao === '' && $colDataAgd !== '') {
            $dataManutencao = bb_agd_data_valida($agendamento[$colDataAgd] ?? '');
        }
        if ($dataManutencao === '') {
            $dataManutencao = date('Y-m-d');
        }

        $diasProxima = bb_agd_obter_dias_proxima_manutencao($pdo);
        $proximaManutencao = bb_agd_calcular_proxima_data($dataManutencao, $diasProxima);

        $colManutencao = bb_agd_colunas($pdo, 'manutencoes_protese');
        $colObs = bb_agd_coluna($colManutencao, ['observacoes', 'obs', 'descricao']);

        $idExistente = 0;
        if ($colObs !== '') {
            $padrao = '%agendamento #' . $idAgendamento . '%';
            $stmt = $pdo->prepare("SELECT id FROM manutencoes_protese WHERE `{$colObs}` LIKE :padrao ORDER BY id DESC LIMIT 1");
            $stmt->execute([':padrao' => $padrao]);
            $idExistente = (int)$stmt->fetchColumn();
        }

        if ($idExistente <= 0) {
            $where = [];
            $params = [];

            if (isset($colManutencao['id_protese'])) {
                $where[] = 'id_protese = :id_protese';
                $params[':id_protese'] = $idProtese;
            }

            if (isset($colManutencao['cliente'])) {
                $where[] = 'cliente = :cliente';
                $params[':cliente'] = $idCliente;
            }

            if (isset($colManutencao['data_manutencao'])) {
                $where[] = 'data_manutencao = :data_manutencao';
                $params[':data_manutencao'] = $dataManutencao;
            }

            if (isset($colManutencao['proxima_manutencao'])) {
                $where[] = 'proxima_manutencao = :proxima_manutencao';
                $params[':proxima_manutencao'] = $proximaManutencao;
            }

            if (!empty($where)) {
                $sql = 'SELECT id FROM manutencoes_protese WHERE ' . implode(' AND ', $where) . ' ORDER BY id DESC LIMIT 1';
                $stmt = $pdo->prepare($sql);
                $stmt->execute($params);
                $idExistente = (int)$stmt->fetchColumn();
            }
        }

        if ($idExistente > 0) {
            return [
                'ok' => true,
                'criado' => false,
                'existente' => true,
                'id_manutencao' => $idExistente,
                'id_agendamento' => $idAgendamento,
                'id_cliente' => $idCliente,
                'id_protese' => $idProtese,
                'data_manutencao' => $dataManutencao,
                'proxima_manutencao' => $proximaManutencao,
                'mensagem' => 'Manutenção já gerada para este agendamento.'
            ];
        }

        $nomeCliente = bb_agd_nome_cliente($pdo, $idCliente);
        $tipoManutencao = '';

        foreach (['tipo', 'modelo', 'nome', 'descricao'] as $campoProtese) {
            if (!empty($protese[$campoProtese])) {
                $tipoManutencao = bb_agd_texto($protese[$campoProtese], 120);
                break;
            }
        }

        if ($tipoManutencao === '') {
            $tipoManutencao = $nomeServico !== '' ? $nomeServico : 'Manutenção de Prótese';
        }

        $usuario = 0;
        if (session_status() === PHP_SESSION_ACTIVE) {
            $usuario = bb_agd_int($_SESSION['id'] ?? $_SESSION['id_usuario'] ?? $_SESSION['id_usu'] ?? 0);
        }

        $observacao = 'Gerado automaticamente pelo agendamento #' . $idAgendamento . ' concluído';
        if ($nomeServico !== '') {
            $observacao .= ' - serviço: ' . $nomeServico;
        }
        if ($nomeCliente !== '') {
            $observacao .= ' - cliente: ' . $nomeCliente;
        }

        $dados = [
            'id_protese' => $idProtese,
            'cliente' => $idCliente,
            'data_manutencao' => $dataManutencao,
            'tipo' => $tipoManutencao,
            'produtos_utilizados' => '',
            'observacoes' => bb_agd_texto($observacao, 1000),
            'proxima_manutencao' => $proximaManutencao,
            'usuario' => $usuario,
            'alerta_manutencao_enviado' => 0,
            'data_alerta_manutencao' => null,
            'tipo_pele' => bb_agd_texto($protese['tipo_pele'] ?? '', 50),
            'nivel_sudorese' => bb_agd_texto($protese['nivel_sudorese'] ?? '', 20),
            'clima' => bb_agd_texto($protese['clima'] ?? '', 50),
            'tipo_adesivo' => bb_agd_texto($protese['tipo_adesivo'] ?? '', 80),
            'fornecedor' => null
        ];

        $dadosInsert = [];
        foreach ($dados as $campo => $valor) {
            if (isset($colManutencao[$campo])) {
                $dadosInsert[$campo] = $valor;
            }
        }

        if (empty($dadosInsert)) {
            return [
                'ok' => false,
                'criado' => false,
                'mensagem' => 'Nenhuma coluna compatível encontrada em manutencoes_protese.'
            ];
        }

        if (!$executar) {
            return [
                'ok' => true,
                'simulacao' => true,
                'criado' => false,
                'id_agendamento' => $idAgendamento,
                'id_cliente' => $idCliente,
                'id_protese' => $idProtese,
                'servico_nome' => $nomeServico,
                'data_manutencao' => $dataManutencao,
                'proxima_manutencao' => $proximaManutencao,
                'dias_proxima_manutencao' => $diasProxima,
                'dados_que_seriam_inseridos' => $dadosInsert,
                'mensagem' => 'Diagnóstico concluído sem alterar banco.'
            ];
        }

        $campos = array_keys($dadosInsert);
        $placeholders = [];
        $params = [];

        foreach ($campos as $campo) {
            $placeholders[] = ':' . $campo;
            $params[':' . $campo] = $dadosInsert[$campo];
        }

        $sql = 'INSERT INTO manutencoes_protese (`' . implode('`, `', $campos) . '`) VALUES (' . implode(', ', $placeholders) . ')';

        try {
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $idManutencao = (int)$pdo->lastInsertId();
        } catch (Throwable $e) {
            return [
                'ok' => false,
                'criado' => false,
                'mensagem' => 'Erro ao gerar manutenção automática.',
                'erro' => $e->getMessage()
            ];
        }

        return [
            'ok' => true,
            'criado' => true,
            'id_manutencao' => $idManutencao,
            'id_agendamento' => $idAgendamento,
            'id_cliente' => $idCliente,
            'id_protese' => $idProtese,
            'servico_nome' => $nomeServico,
            'data_manutencao' => $dataManutencao,
            'proxima_manutencao' => $proximaManutencao,
            'dias_proxima_manutencao' => $diasProxima,
            'alerta_manutencao_enviado' => 0,
            'mensagem' => 'Manutenção de prótese gerada para aviso posterior via WhatsApp.'
        ];
    }
}
