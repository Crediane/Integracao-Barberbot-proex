<?php
// Conexão dedicada ao Banco Master/Administrativo para os tutoriais globais.
// Banco obrigatório: jctecbr_master (configuração auth_master).
// Não altera a sessão atual e não reutiliza o PDO do Banco Individual.

if (defined('BB_DUVIDAS_GLOBAL_CONNECTION_LOADED')) {
    return;
}
define('BB_DUVIDAS_GLOBAL_CONNECTION_LOADED', true);

$bbDuvidasConfigFile = __DIR__ . '/../../../../config/marketplace.php';
if (!is_file($bbDuvidasConfigFile)) {
    throw new RuntimeException('Configuração global do Marketplace não encontrada.');
}

$bbDuvidasConfig = require $bbDuvidasConfigFile;
if (!is_array($bbDuvidasConfig) || empty($bbDuvidasConfig['auth_master']) || !is_array($bbDuvidasConfig['auth_master'])) {
    throw new RuntimeException('Configuração auth_master do Banco Master/Administrativo inválida.');
}

$bbDuvidasAuthMaster = $bbDuvidasConfig['auth_master'];
$bbDuvidasMasterHost = trim((string)($bbDuvidasAuthMaster['host'] ?? ''));
$bbDuvidasMasterDb = (string)preg_replace('/[^a-z0-9_]/i', '', (string)($bbDuvidasAuthMaster['db'] ?? ''));
$bbDuvidasMasterUser = trim((string)($bbDuvidasAuthMaster['user'] ?? ''));
$bbDuvidasMasterPass = (string)($bbDuvidasAuthMaster['pass'] ?? '');

if ($bbDuvidasMasterHost === '' || $bbDuvidasMasterDb === '' || $bbDuvidasMasterUser === '') {
    throw new RuntimeException('Credenciais auth_master do Banco Master/Administrativo incompletas.');
}

if ($bbDuvidasMasterDb !== 'jctecbr_master') {
    throw new RuntimeException('O módulo de tutoriais deve utilizar exclusivamente o banco jctecbr_master.');
}

$pdoDuvidasGlobal = new PDO(
    "mysql:host={$bbDuvidasMasterHost};dbname={$bbDuvidasMasterDb};charset=utf8mb4",
    $bbDuvidasMasterUser,
    $bbDuvidasMasterPass,
    [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ]
);

$bbDuvidasDatabaseAtual = trim((string)$pdoDuvidasGlobal->query('SELECT DATABASE()')->fetchColumn());
if ($bbDuvidasDatabaseAtual !== $bbDuvidasMasterDb) {
    throw new RuntimeException('A conexão de tutoriais não corresponde ao banco jctecbr_master.');
}
