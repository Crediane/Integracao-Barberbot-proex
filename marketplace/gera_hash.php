<?php
// /public_html/gera_hash.php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
	http_response_code(403);
	exit('Uso permitido apenas via CLI.');
}

$senha = (string)($argv[1] ?? '');
if ($senha === '') {
	fwrite(STDERR, "Informe a senha: php gera_hash.php 'sua_senha'" . PHP_EOL);
	exit(1);
}

echo password_hash($senha, PASSWORD_DEFAULT) . PHP_EOL;
