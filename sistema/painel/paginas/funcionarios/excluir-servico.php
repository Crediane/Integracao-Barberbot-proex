<?php
require_once __DIR__ . '/../_context_bootstrap.php';
require_once(__DIR__ . "/../../../conexao.php");

/*
    /sistema/painel/paginas/funcionarios/excluir-servico.php

    LÓGICA MANTIDA:
    - exclui vínculo de serviço do funcionário
    - aceita exclusão por ID interno da tabela servicos_func
    - aceita exclusão pelo par (serviço + funcionário)
    - sem mexer na regra de negócio

    AJUSTE FEITO:
    - no seu banco real a tabela servicos_func usa as colunas:
        id, funcionario, servico
      e não:
        id, id_func, id_servico
    - por isso o código anterior retornava "ID inválido!" mesmo com vínculo existente
    - aqui eu mantive compatibilidade com os dois nomes de coluna
*/

$tabela = 'servicos_func';

// permissão: mantém o padrão do sistema
$nivel = trim((string)($_SESSION['nivel'] ?? ''));
if (mb_strtolower($nivel) !== 'administrador') {
    echo 'Acesso negado!';
    exit();
}

if (!function_exists('bb_coluna_existe_tabela')) {
    function bb_coluna_existe_tabela(PDO $pdo, $tabela, $coluna)
    {
        $sql = "SELECT 1
                  FROM INFORMATION_SCHEMA.COLUMNS
                 WHERE TABLE_SCHEMA = DATABASE()
                   AND TABLE_NAME = :tabela
                   AND COLUMN_NAME = :coluna
                 LIMIT 1";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':tabela', $tabela, PDO::PARAM_STR);
        $stmt->bindValue(':coluna', $coluna, PDO::PARAM_STR);
        $stmt->execute();
        return (bool)$stmt->fetchColumn();
    }
}

if (!function_exists('bb_primeira_coluna_existente')) {
    function bb_primeira_coluna_existente(PDO $pdo, $tabela, array $colunas)
    {
        foreach ($colunas as $coluna) {
            if (bb_coluna_existe_tabela($pdo, $tabela, $coluna)) {
                return $coluna;
            }
        }
        return '';
    }
}

$coluna_funcionario = bb_primeira_coluna_existente($pdo, $tabela, ['funcionario', 'id_func']);
$coluna_servico = bb_primeira_coluna_existente($pdo, $tabela, ['servico', 'id_servico']);

if ($coluna_funcionario === '' || $coluna_servico === '') {
    echo 'Estrutura inválida!';
    exit();
}

/* ============================
   1) TENTA EXCLUIR POR ID (PK)
   ============================ */

$id = 0;
if (isset($_POST['id'])) {
    $id = (int)$_POST['id'];
} elseif (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
}

if ($id > 0) {
    $sql = "SELECT id FROM {$tabela} WHERE id = :id LIMIT 1";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();

    if ($stmt->fetchColumn()) {
        $sql = "DELETE FROM {$tabela} WHERE id = :id";
        $stmt = $pdo->prepare($sql);
        $stmt->bindValue(':id', $id, PDO::PARAM_INT);
        $stmt->execute();

        echo 'Excluído com Sucesso';
        exit();
    }
}

/* ==========================================
   2) EXCLUI POR (SERVIÇO, FUNCIONÁRIO)
   ========================================== */

$id_servico = 0;
$id_funcionario = 0;

// serviço
if (isset($_POST['id_servico'])) {
    $id_servico = (int)$_POST['id_servico'];
} elseif (isset($_GET['id_servico'])) {
    $id_servico = (int)$_GET['id_servico'];
} elseif (isset($_POST['idServico'])) {
    $id_servico = (int)$_POST['idServico'];
} elseif (isset($_GET['idServico'])) {
    $id_servico = (int)$_GET['idServico'];
} elseif (isset($_POST['servico'])) {
    $id_servico = (int)$_POST['servico'];
} elseif (isset($_GET['servico'])) {
    $id_servico = (int)$_GET['servico'];
}

// funcionário
if (isset($_POST['id_func'])) {
    $id_funcionario = (int)$_POST['id_func'];
} elseif (isset($_GET['id_func'])) {
    $id_funcionario = (int)$_GET['id_func'];
} elseif (isset($_POST['id_funcionario'])) {
    $id_funcionario = (int)$_POST['id_funcionario'];
} elseif (isset($_GET['id_funcionario'])) {
    $id_funcionario = (int)$_GET['id_funcionario'];
} elseif (isset($_POST['funcionario'])) {
    $id_funcionario = (int)$_POST['funcionario'];
} elseif (isset($_GET['funcionario'])) {
    $id_funcionario = (int)$_GET['funcionario'];
} elseif (isset($_POST['func'])) {
    $id_funcionario = (int)$_POST['func'];
} elseif (isset($_GET['func'])) {
    $id_funcionario = (int)$_GET['func'];
}

if ($id_servico <= 0 || $id_funcionario <= 0) {
    echo 'ID inválido!';
    exit();
}

$sql = "SELECT id
          FROM {$tabela}
         WHERE {$coluna_servico} = :servico
           AND {$coluna_funcionario} = :funcionario
         LIMIT 1";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':servico', $id_servico, PDO::PARAM_INT);
$stmt->bindValue(':funcionario', $id_funcionario, PDO::PARAM_INT);
$stmt->execute();

$id_vinculo = (int)$stmt->fetchColumn();
if ($id_vinculo <= 0) {
    echo 'ID inválido!';
    exit();
}

$sql = "DELETE
          FROM {$tabela}
         WHERE {$coluna_servico} = :servico
           AND {$coluna_funcionario} = :funcionario";
$stmt = $pdo->prepare($sql);
$stmt->bindValue(':servico', $id_servico, PDO::PARAM_INT);
$stmt->bindValue(':funcionario', $id_funcionario, PDO::PARAM_INT);
$stmt->execute();

echo 'Excluído com Sucesso';
