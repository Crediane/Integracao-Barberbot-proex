<?php
require_once __DIR__ . '/../_context_bootstrap.php';
header('Content-Type: text/plain; charset=UTF-8');
require_once __DIR__ . '/../../../conexao.php';

/**
 * Entrada esperada pelo JS:
 *  - POST id
 *  - POST acao  (ex.: "Ativar" / "Desativar")
 *
 * Saída:
 *  - Retorna o novo status salvo (1/0 ou 'ativo'/'inativo')
 */

$id   = isset($_POST['id'])   ? (int)$_POST['id']   : 0;
$acao = isset($_POST['acao']) ? trim($_POST['acao']) : '';

if ($id <= 0) {
    echo "ID inválido";
    exit();
}
if ($acao === '') {
    echo "Ação inválida";
    exit();
}

/**
 * Detecta uma coluna de status utilizável na tabela:
 *  - preferência por `ativo` (TINYINT/INT ou ENUM)
 *  - fallback para `status` (ENUM ou numérico)
 *
 * Retorna array:
 *  [ 'col' => nomeColuna, 'type' => 'numeric'|'enum', 'enum_map' => ['on' => '...', 'off' => '...'] ]
 */
function detectarColunaStatus(PDO $pdo): ?array {
    // tenta 'ativo'
    $stmt = $pdo->query("SHOW COLUMNS FROM foto_admin LIKE 'ativo'");
    $c = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    if ($c) {
        $type = strtolower($c['Type']);
        if (strpos($type, 'enum(') === 0) {
            // tenta map padrão
            $enum = $type;
            $on  = 'ativo';
            $off = 'inativo';
            if (stripos($enum, "'1'") !== false || stripos($enum, "1") !== false) { $on = '1'; }
            if (stripos($enum, "'0'") !== false || stripos($enum, "0") !== false) { $off = '0'; }
            return ['col'=>'ativo','type'=>'enum','enum_map'=>['on'=>$on,'off'=>$off]];
        }
        // numérico
        return ['col'=>'ativo','type'=>'numeric','enum_map'=>null];
    }

    // tenta 'status'
    $stmt = $pdo->query("SHOW COLUMNS FROM foto_admin LIKE 'status'");
    $c = $stmt ? $stmt->fetch(PDO::FETCH_ASSOC) : null;
    if ($c) {
        $type = strtolower($c['Type']);
        if (strpos($type, 'enum(') === 0) {
            // valores comuns
            $on  = (stripos($type, "'ativo'") !== false)   ? 'ativo'   : ((stripos($type, "'active'") !== false) ? 'active' : null);
            $off = (stripos($type, "'inativo'") !== false) ? 'inativo' : ((stripos($type, "'inactive'") !== false) ? 'inactive' : null);
            if ($on === null)  $on  = 'ativo';
            if ($off === null) $off = 'inativo';
            return ['col'=>'status','type'=>'enum','enum_map'=>['on'=>$on,'off'=>$off]];
        }
        // numérico
        return ['col'=>'status','type'=>'numeric','enum_map'=>null];
    }

    return null;
}

// normaliza ação para on/off
$acaoLower = mb_strtolower($acao, 'UTF-8');
$toOn  = (strpos($acaoLower, 'ativ') !== false) || (strpos($acaoLower, 'on') !== false) || (strpos($acaoLower, 'enable') !== false);
$toOff = (strpos($acaoLower, 'desativ') !== false) || (strpos($acaoLower, 'off') !== false) || (strpos($acaoLower, 'disable') !== false);

// se ambos ficaram true (ex.: "desativar" contém "ativ"), prioriza desativar
if ($toOn && $toOff) {
    $toOn  = false;
    $toOff = true;
}

$colInfo = detectarColunaStatus($pdo);
if (!$colInfo) {
    echo "Coluna de status não encontrada";
    exit();
}

try {
    // busca valor atual
    $stmt = $pdo->prepare("SELECT `{$colInfo['col']}` AS val FROM foto_admin WHERE id = :id LIMIT 1");
    $stmt->bindValue(':id', $id, PDO::PARAM_INT);
    $stmt->execute();
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        echo "Registro não encontrado";
        exit();
    }
    $valAtual = $row['val'];

    // decide novo valor
    if (!$toOn && !$toOff) {
        // toggle (quando acao não indica claramente)
        if ($colInfo['type'] === 'numeric') {
            $toOn  = !((int)$valAtual === 1);
            $toOff = !$toOn;
        } else {
            $onStr = $colInfo['enum_map']['on'];
            $toOn  = !($valAtual === $onStr);
            $toOff = !$toOn;
        }
    }

    if ($colInfo['type'] === 'numeric') {
        $novo = $toOn ? 1 : 0;
    } else {
        $novo = $toOn ? $colInfo['enum_map']['on'] : $colInfo['enum_map']['off'];
    }

    $sql = "UPDATE foto_admin SET `{$colInfo['col']}` = :novo WHERE id = :id";
    $upd = $pdo->prepare($sql);
    $upd->bindValue(':novo', $novo);
    $upd->bindValue(':id', $id, PDO::PARAM_INT);
    $upd->execute();

    // retorna o novo status para o JS (1/0 ou ativo/inativo)
    echo $novo;
} catch (Throwable $e) {
    echo "Erro ao alterar status";
}
