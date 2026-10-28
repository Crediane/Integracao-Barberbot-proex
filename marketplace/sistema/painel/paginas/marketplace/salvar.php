<?php
require_once __DIR__ . '/master_bootstrap.php';

function bb_colunas_tabela(PDO $pdo, string $tabela): array{
	try{
		$st = $pdo->prepare("
			SELECT COLUMN_NAME
			  FROM INFORMATION_SCHEMA.COLUMNS
			 WHERE TABLE_SCHEMA = DATABASE()
			   AND TABLE_NAME = :t
		");
		$st->execute([':t' => $tabela]);
		$res = $st->fetchAll(PDO::FETCH_COLUMN);
	}catch(Throwable $e){
		$res = [];
	}

	$map = [];
	if(is_array($res)){
		foreach($res as $col){
			$map[(string)$col] = true;
		}
	}
	return $map;
}

function bb_host_limpo(string $host): string{
	$host = strtolower(trim($host));
	$host = preg_replace('/^https?:\/\//', '', $host);
	$host = preg_replace('/:\d+$/', '', $host);
	$host = trim($host, " \t\n\r\0\x0B./");
	return preg_replace('/[^a-z0-9\.\-]/', '', $host);
}

function bb_money_db(string $valor): string{
	$valor = trim(str_replace(['R$', ' '], '', $valor));
	if($valor === ''){
		return '0.00';
	}

	if(strpos($valor, ',') !== false){
		$valor = str_replace('.', '', $valor);
		$valor = str_replace(',', '.', $valor);
	}else{
		$valor = preg_replace('/[^0-9.\-]/', '', $valor);
	}

	if($valor === '' || !is_numeric($valor)){
		return '0.00';
	}
	return number_format((float)$valor, 2, '.', '');
}

function bb_phone_digits(string $telefone): string{
	$telefone = preg_replace('/\D+/', '', (string)$telefone);
	if($telefone === ''){
		return '';
	}
	if(substr($telefone, 0, 2) !== '55' && strlen($telefone) <= 11){
		$telefone = '55' . $telefone;
	}
	return $telefone;
}

function bb_try_decrypt_pass(string $rawPass, string $secret): string{
	if($rawPass === ''){
		return '';
	}
	if(strpos($rawPass, 'enc:') !== 0){
		return $rawPass;
	}
	if($secret === ''){
		return '';
	}

	$cryptoFile = __DIR__ . '/../../../../sistema/tenant_crypto.php';
	if(is_file($cryptoFile)){
		require_once $cryptoFile;
	}

	if(function_exists('bb_decrypt_str')){
		$dec = (string)bb_decrypt_str($rawPass, $secret);
		if($dec !== ''){
			return $dec;
		}
	}

	return '';
}

function bb_localizar_api_texto(): string{
	$candidatos = [
		__DIR__ . '/../../../ajax/api-texto.php',
		__DIR__ . '/../../../../ajax/api-texto.php',
		__DIR__ . '/../../../../pagamentos/api-texto.php',
		__DIR__ . '/../../ajax/api-texto.php'
	];

	foreach($candidatos as $arquivo){
		if(is_file($arquivo)){
			return $arquivo;
		}
	}

	return '';
}

function bb_enviar_whatsapp(string $telefone, string $mensagem, string $api, string $token, string $instancia): bool{
	$telefone = bb_phone_digits($telefone);
	if($telefone === '' || trim($api) === '' || trim($token) === '' || trim($instancia) === ''){
		return false;
	}

	$apiFile = bb_localizar_api_texto();
	if($apiFile === ''){
		return false;
	}

	$status_mensagem = '';
	ob_start();
	require $apiFile;
	ob_end_clean();

	$status = trim((string)$status_mensagem);
	if($status === ''){
		return true;
	}

	$statusLower = function_exists('mb_strtolower') ? mb_strtolower($status, 'UTF-8') : strtolower($status);
	if($status === 'Mensagem enviada com sucesso.' || $status === 'OK' || strpos($statusLower, 'sucesso') !== false){
		return true;
	}

	return false;
}

function bb_descobrir_base_publica(array $cfg, string $slug, string $dominioPersonalizado, array $cfgTenant): string{
	$base = '';
	if(!empty($cfgTenant['url_sistema'])){
		$base = rtrim((string)$cfgTenant['url_sistema'], '/');
	}

	if($base === ''){
		if($dominioPersonalizado !== ''){
			$base = 'https://' . $dominioPersonalizado;
		}else{
			$root = '';
			if(!empty($cfg['base_url'])){
				$root = rtrim((string)$cfg['base_url'], '/');
			}elseif(!empty($_SERVER['HTTP_HOST'])){
				$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
				$root = $scheme . '://' . $_SERVER['HTTP_HOST'];
			}

			if($root !== '' && $slug !== ''){
				$base = $root . '/loja/' . $slug;
			}else{
				$base = $root;
			}
		}
	}

	if(substr($base, -8) === '/sistema'){
		$base = substr($base, 0, -8);
	}

	return rtrim($base, '/');
}

$tenantsTable = preg_replace('/[^a-z0-9_]/i', '', (string)($cfg['master']['table'] ?? 'tenants'));
if($tenantsTable === ''){
	$tenantsTable = 'tenants';
}

$domainsCfg = (!empty($cfg['domains']) && is_array($cfg['domains'])) ? $cfg['domains'] : [];
$domainsTable = preg_replace('/[^a-z0-9_]/i', '', (string)($domainsCfg['table'] ?? 'tenant_domains'));
if($domainsTable === ''){
	$domainsTable = 'tenant_domains';
}

$colsTenant = bb_colunas_tabela($pdoM, $tenantsTable);
$colsDomain = bb_colunas_tabela($pdoM, $domainsTable);
$colsFatura = bb_colunas_tabela($pdoM, 'marketplace_faturas');

$temPlano = isset($colsTenant['plano_marketplace']);
$temTenantDomains = !empty($colsDomain);
$acao = isset($_POST['acao']) ? trim((string)$_POST['acao']) : '';

if($acao == 'status'){
	$id = (int)($_POST['id'] ?? 0);
	$status = trim((string)($_POST['status'] ?? ''));
	if($id <= 0){
		echo "ID inválido";
		exit();
	}

	if($status != 'active' && $status != 'blocked'){
		echo "Status inválido";
		exit();
	}

	$sets = [];
	$params = [':id' => $id];

	if(isset($colsTenant['status'])){
		$sets[] = "status = :status";
		$params[':status'] = $status;
	}
	if(isset($colsTenant['ativo'])){
		$sets[] = "ativo = :ativo";
		$params[':ativo'] = ($status == 'active') ? 1 : 0;
	}
	if(isset($colsTenant['blocked_at'])){
		$sets[] = "blocked_at = :blocked_at";
		$params[':blocked_at'] = ($status == 'blocked') ? date('Y-m-d H:i:s') : null;
	}

	if(empty($sets)){
		echo "Nenhuma coluna de status encontrada";
		exit();
	}

	try{
		$st = $pdoM->prepare("UPDATE {$tenantsTable} SET ".implode(', ', $sets)." WHERE id = :id");
		$st->execute($params);
		echo "Salvo com Sucesso";
	}catch(Throwable $e){
		echo "Erro ao salvar: ".$e->getMessage();
	}
	exit();
}

if($acao === 'baixar_pagamento'){
	$id = (int)($_POST['id'] ?? 0);
	$forma_pagamento = trim((string)($_POST['forma_pagamento'] ?? 'Pix'));
	$data_pagamento = trim((string)($_POST['data_pagamento'] ?? date('Y-m-d')));
	$observacao = trim((string)($_POST['observacao'] ?? ''));

	if($id <= 0){
		echo "ID inválido";
		exit();
	}

	if($forma_pagamento === ''){
		$forma_pagamento = 'Pix';
	}

	try{
		$stTenant = $pdoM->prepare("SELECT * FROM {$tenantsTable} WHERE id = :id LIMIT 1");
		$stTenant->execute([':id' => $id]);
		$tenant = $stTenant->fetch(PDO::FETCH_ASSOC);

		if(!$tenant){
			echo "Barbearia não encontrada";
			exit();
		}

		$slug = (string)($tenant['slug'] ?? '');
		$nome = (string)(($tenant['nome'] ?? '') !== '' ? $tenant['nome'] : $slug);
		$vencimentoAtual = (string)($tenant['vencimento'] ?? date('Y-m-d'));
		if($vencimentoAtual === '' || $vencimentoAtual === '0000-00-00'){
			$vencimentoAtual = date('Y-m-d');
		}

		$valorMensal = isset($tenant['valor_mensal']) ? (float)$tenant['valor_mensal'] : 0.0;
		$telefoneTenant = (string)($tenant['telefone_whatsapp'] ?? ($tenant['whatsapp'] ?? ''));
		$dominioPersonalizado = '';

		if($temTenantDomains){
			try{
				$stDom = $pdoM->prepare("
					SELECT host
					  FROM {$domainsTable}
					 WHERE tenant_id = :tenant_id
					   AND (ativo = 1 OR ativo IS NULL)
					 ORDER BY principal DESC, id DESC
					 LIMIT 1
				");
				$stDom->execute([':tenant_id' => $id]);
				$dominioPersonalizado = trim((string)$stDom->fetchColumn());
			}catch(Throwable $e){
				$dominioPersonalizado = '';
			}
		}

		$stFat = $pdoM->prepare("
			SELECT *
			  FROM marketplace_faturas
			 WHERE tenant_id = :tenant_id
			   AND status = 'pending'
			 ORDER BY vencimento DESC, id DESC
			 LIMIT 1
		");
		$stFat->execute([':tenant_id' => $id]);
		$fatura = $stFat->fetch(PDO::FETCH_ASSOC);

		if(!$fatura){
			echo "Nenhuma fatura pendente encontrada";
			exit();
		}

		$faturaId = (int)($fatura['id'] ?? 0);
		if($faturaId <= 0){
			echo "Fatura inválida";
			exit();
		}

		$valorFatura = isset($fatura['valor']) ? (float)$fatura['valor'] : $valorMensal;
		if($valorFatura <= 0){
			$valorFatura = $valorMensal;
		}

		$proximoVencimento = date('Y-m-d', strtotime('+1 month', strtotime($vencimentoAtual)));

		$secret = (string)($cfg['security']['tenant_secret'] ?? '');
		$cfgTenantPadrao = (!empty($cfg['tenant']) && is_array($cfg['tenant'])) ? $cfg['tenant'] : [];
		$tenantHost = trim((string)($tenant['db_host'] ?? ''));
		if($tenantHost === ''){
			$tenantHost = trim((string)($cfgTenantPadrao['host'] ?? 'localhost'));
		}
		$tenantDb = trim((string)($tenant['db_name'] ?? ''));
		$tenantUser = trim((string)($tenant['db_user'] ?? ''));
		if($tenantUser === ''){
			$tenantUser = trim((string)($cfgTenantPadrao['user'] ?? ''));
		}
		$rawTenantPass = (string)($tenant['db_pass'] ?? '');
		if($rawTenantPass === ''){
			$rawTenantPass = (string)($cfgTenantPadrao['pass'] ?? '');
		}
		$tenantPass = bb_try_decrypt_pass($rawTenantPass, $secret);

		$pdoTenant = null;
		$cfgTenant = [];
		if($tenantDb !== '' && $tenantUser !== ''){
			try{
				$pdoTenant = new PDO(
					"mysql:host={$tenantHost};dbname={$tenantDb};charset=utf8mb4",
					$tenantUser,
					$tenantPass,
					[
						PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
						PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
					]
				);
				try{
					$cfgTenant = $pdoTenant->query("SELECT * FROM config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
					if(!is_array($cfgTenant)){
						$cfgTenant = [];
					}
				}catch(Throwable $e){
					$cfgTenant = [];
				}
			}catch(Throwable $e){
				$pdoTenant = null;
				$cfgTenant = [];
			}
		}

		$pdoM->beginTransaction();

		$setsFatura = ["status = :status"];
		$paramsFatura = [
			':status' => 'paid',
			':id' => $faturaId
		];

		foreach(['forma_pagamento', 'pgto', 'payment_method', 'metodo_pagamento'] as $col){
			if(isset($colsFatura[$col])){
				$setsFatura[] = "{$col} = :forma_pagamento";
				$paramsFatura[':forma_pagamento'] = $forma_pagamento;
				break;
			}
		}

		foreach(['data_pgto', 'data_pagamento', 'pago_em', 'paid_at'] as $col){
			if(isset($colsFatura[$col])){
				$setsFatura[] = "{$col} = :data_pagamento";
				$paramsFatura[':data_pagamento'] = $data_pagamento;
				break;
			}
		}

		foreach(['observacao', 'obs'] as $col){
			if(isset($colsFatura[$col])){
				$setsFatura[] = "{$col} = :observacao";
				$paramsFatura[':observacao'] = $observacao;
				break;
			}
		}

		if(isset($colsFatura['updated_at'])){
			$setsFatura[] = "updated_at = :updated_at";
			$paramsFatura[':updated_at'] = date('Y-m-d H:i:s');
		}

		$stUpFat = $pdoM->prepare("UPDATE marketplace_faturas SET ".implode(', ', $setsFatura)." WHERE id = :id LIMIT 1");
		$stUpFat->execute($paramsFatura);

		$setsTenant = [];
		$paramsTenant = [':id' => $id];

		if(isset($colsTenant['status'])){
			$setsTenant[] = "status = :status";
			$paramsTenant[':status'] = 'active';
		}
		if(isset($colsTenant['ativo'])){
			$setsTenant[] = "ativo = :ativo";
			$paramsTenant[':ativo'] = 1;
		}
		if(isset($colsTenant['blocked_at'])){
			$setsTenant[] = "blocked_at = :blocked_at";
			$paramsTenant[':blocked_at'] = null;
		}
		if(isset($colsTenant['vencimento'])){
			$setsTenant[] = "vencimento = :vencimento";
			$paramsTenant[':vencimento'] = $proximoVencimento;
		}

		if(!empty($setsTenant)){
			$stUpTenant = $pdoM->prepare("UPDATE {$tenantsTable} SET ".implode(', ', $setsTenant)." WHERE id = :id");
			$stUpTenant->execute($paramsTenant);
		}

		$stPend = $pdoM->prepare("
			SELECT id
			  FROM marketplace_faturas
			 WHERE tenant_id = :tenant_id
			   AND status = 'pending'
			 ORDER BY vencimento DESC, id DESC
			 LIMIT 1
		");
		$stPend->execute([':tenant_id' => $id]);
		$pendente = $stPend->fetch(PDO::FETCH_ASSOC);

		if($pendente && !empty($pendente['id'])){
			$stUpPend = $pdoM->prepare("
				UPDATE marketplace_faturas
				   SET vencimento = :vencimento,
				       valor = :valor,
				       status = 'pending'
				 WHERE id = :id
				 LIMIT 1
			");
			$stUpPend->execute([
				':vencimento' => $proximoVencimento,
				':valor' => $valorMensal,
				':id' => (int)$pendente['id']
			]);
			}else{
				$colsInsertPend = [];
				$valsInsertPend = [];
				$paramsInsertPend = [];

				if(isset($colsFatura['tenant_id'])){
					$colsInsertPend[] = '`tenant_id`';
					$valsInsertPend[] = ':tenant_id';
					$paramsInsertPend[':tenant_id'] = $id;
				}
				if(isset($colsFatura['vencimento'])){
					$colsInsertPend[] = '`vencimento`';
					$valsInsertPend[] = ':vencimento';
					$paramsInsertPend[':vencimento'] = $proximoVencimento;
				}
				if(isset($colsFatura['valor'])){
					$colsInsertPend[] = '`valor`';
					$valsInsertPend[] = ':valor';
					$paramsInsertPend[':valor'] = $valorMensal;
				}
				if(isset($colsFatura['status'])){
					$colsInsertPend[] = '`status`';
					$valsInsertPend[] = ':status';
					$paramsInsertPend[':status'] = 'pending';
				}
				foreach(['criado_em', 'created_at'] as $colCriacao){
					if(isset($colsFatura[$colCriacao])){
						$colsInsertPend[] = "`{$colCriacao}`";
						$valsInsertPend[] = ':criado_em';
						$paramsInsertPend[':criado_em'] = date('Y-m-d H:i:s');
						break;
					}
				}

				if(!empty($colsInsertPend)){
					$stInsPend = $pdoM->prepare("
						INSERT INTO marketplace_faturas
							(".implode(', ', $colsInsertPend).")
						VALUES
							(".implode(', ', $valsInsertPend).")
					");
					$stInsPend->execute($paramsInsertPend);
				}
			}

		$pdoM->commit();

		if($pdoTenant instanceof PDO){
			try{
				$colsCfgTenant = bb_colunas_tabela($pdoTenant, 'config');
				if(isset($colsCfgTenant['ativo'])){
					$pdoTenant->exec("UPDATE config SET ativo = 'Sim'");
				}
			}catch(Throwable $e){
				// mantém baixa do marketplace sem quebrar
			}
		}

		$apiW = '';
		$tokenW = '';
		$instanciaW = '';

		try{
			if(isset($cfgTenant['api']) && trim((string)$cfgTenant['api']) !== ''){
				$apiW = trim((string)$cfgTenant['api']);
				$tokenW = trim((string)($cfgTenant['token'] ?? ''));
				$instanciaW = trim((string)($cfgTenant['instancia'] ?? ''));
			}
		}catch(Throwable $e){
			$apiW = '';
			$tokenW = '';
			$instanciaW = '';
		}

		if($apiW === '' || $tokenW === '' || $instanciaW === ''){
			try{
				if(bb_market_master_table_exists($pdoM, 'config')){
					$rowMasterCfg = $pdoM->query("SELECT api, token, instancia FROM config LIMIT 1")->fetch(PDO::FETCH_ASSOC);
					if($rowMasterCfg){
						$apiW = trim((string)($rowMasterCfg['api'] ?? $apiW));
						$tokenW = trim((string)($rowMasterCfg['token'] ?? $tokenW));
						$instanciaW = trim((string)($rowMasterCfg['instancia'] ?? $instanciaW));
					}
				}
			}catch(Throwable $e){
				// mantém a baixa sem interromper quando não há configuração global de WhatsApp
			}
		}

		$basePublica = bb_descobrir_base_publica($cfg, $slug, $dominioPersonalizado, $cfgTenant);
		$linkSistema = '';
		if($basePublica !== ''){
			$linkSistema = $basePublica . '/sistema';
		}

		$valorF = number_format((float)$valorFatura, 2, ',', '.');
		$vencF = date('d/m/Y', strtotime($vencimentoAtual));
		$dataPgtoF = date('d/m/Y', strtotime($data_pagamento));
		$proxVencF = date('d/m/Y', strtotime($proximoVencimento));
		$nomeSistema = trim((string)($cfgTenant['nome'] ?? 'BarberBot Software'));

		$mensagem  = "💳 *{$nomeSistema}*\n";
		$mensagem .= "✅ _Pagamento Confirmado_\n";
		$mensagem .= "-------------------------------\n";
		$mensagem .= "*Recibo de Pagamento*\n";
		$mensagem .= "-------------------------------\n";
		$mensagem .= "*Cliente:* {$nome}\n";
		$mensagem .= "*Descrição:* Assinatura Marketplace\n";
		$mensagem .= "*Valor:* R$ {$valorF}\n";
		$mensagem .= "*Vencimento:* {$vencF}\n";
		$mensagem .= "*Data de Pagamento:* {$dataPgtoF}\n";
		$mensagem .= "*Próximo Vencimento:* {$proxVencF}\n";
		$mensagem .= "*Forma de Pagamento:* {$forma_pagamento}\n";
		$mensagem .= "*Fatura:* #{$faturaId}\n";
		if($observacao !== ''){
			$mensagem .= "*Observação:* {$observacao}\n";
		}
		$mensagem .= "-------------------------------\n";
		$mensagem .= "Sua próxima mensalidade já foi gerada.\n";
		$mensagem .= "Agradecemos pela preferência!\n";
		if($linkSistema !== ''){
			$mensagem .= "🔗 *Sistema:*\n{$linkSistema}";
		}

		if($telefoneTenant !== '' && $apiW !== '' && $tokenW !== '' && $instanciaW !== ''){
			bb_enviar_whatsapp($telefoneTenant, $mensagem, $apiW, $tokenW, $instanciaW);
		}

		echo "Baixado com Sucesso";
		exit();

	}catch(Throwable $e){
		if($pdoM->inTransaction()){
			$pdoM->rollBack();
		}
		echo "Erro ao baixar: ".$e->getMessage();
		exit();
	}
}

$id = (int)($_POST['id'] ?? 0);
$status = trim((string)($_POST['status'] ?? 'active'));
$vencimento = trim((string)($_POST['vencimento'] ?? ''));
$valor_mensal = bb_money_db((string)($_POST['valor_mensal'] ?? '0'));
$telefone_whatsapp = preg_replace('/\D+/', '', (string)($_POST['telefone_whatsapp'] ?? ''));
$email = trim((string)($_POST['email'] ?? ''));
$plano_marketplace = strtolower(trim((string)($_POST['plano_marketplace'] ?? 'profissional')));
$dominio_personalizado = bb_host_limpo((string)($_POST['dominio_personalizado'] ?? ''));

if($id <= 0){
	echo "ID inválido";
	exit();
}

if($status != 'active' && $status != 'blocked'){
	$status = 'active';
}

if($plano_marketplace != 'business'){
	$plano_marketplace = 'profissional';
	$dominio_personalizado = '';
}

if($valor_mensal === '' || !is_numeric($valor_mensal)){
	$valor_mensal = '0.00';
}

$sets = [];
$params = [':id' => $id];

if(isset($colsTenant['status'])){
	$sets[] = "status = :status";
	$params[':status'] = $status;
}
if(isset($colsTenant['ativo'])){
	$sets[] = "ativo = :ativo";
	$params[':ativo'] = ($status == 'active') ? 1 : 0;
}
if(isset($colsTenant['vencimento'])){
	$sets[] = "vencimento = :vencimento";
	$params[':vencimento'] = $vencimento != '' ? $vencimento : null;
}
if(isset($colsTenant['valor_mensal'])){
	$sets[] = "valor_mensal = :valor_mensal";
	$params[':valor_mensal'] = $valor_mensal;
}
if(isset($colsTenant['telefone_whatsapp'])){
	$sets[] = "telefone_whatsapp = :telefone";
	$params[':telefone'] = $telefone_whatsapp;
}elseif(isset($colsTenant['whatsapp'])){
	$sets[] = "whatsapp = :telefone";
	$params[':telefone'] = $telefone_whatsapp;
}
if(isset($colsTenant['email'])){
	$sets[] = "email = :email";
	$params[':email'] = $email;
}elseif(isset($colsTenant['dono_email'])){
	$sets[] = "dono_email = :email";
	$params[':email'] = $email;
}
if(isset($colsTenant['blocked_at'])){
	$sets[] = "blocked_at = :blocked_at";
	$params[':blocked_at'] = ($status == 'blocked') ? date('Y-m-d H:i:s') : null;
}
if($temPlano){
	$sets[] = "plano_marketplace = :plano_marketplace";
	$params[':plano_marketplace'] = $plano_marketplace;
}

if(empty($sets)){
	echo "Nenhuma coluna disponível para atualização";
	exit();
}

try{
	$pdoM->beginTransaction();

	$st = $pdoM->prepare("UPDATE {$tenantsTable} SET ".implode(', ', $sets)." WHERE id = :id");
	$st->execute($params);

	if($temTenantDomains){
		$dominioCustom = $dominio_personalizado;

		if($plano_marketplace !== 'business' || $dominioCustom === ''){
			$del = $pdoM->prepare("
				DELETE FROM {$domainsTable}
				 WHERE tenant_id = :tenant_id
				   AND tipo = 'dominio'
			");
			$del->execute([':tenant_id' => $id]);
		}else{
			$stDom = $pdoM->prepare("
				SELECT id
				  FROM {$domainsTable}
				 WHERE tenant_id = :tenant_id
				   AND tipo = 'dominio'
				 ORDER BY principal DESC, id ASC
				 LIMIT 1
			");
			$stDom->execute([':tenant_id' => $id]);
			$rowDom = $stDom->fetch(PDO::FETCH_ASSOC);

			if($rowDom && !empty($rowDom['id'])){
				$upDom = $pdoM->prepare("
					UPDATE {$domainsTable}
					   SET host = :host,
					       ativo = 1,
					       principal = 1
					 WHERE id = :id
					 LIMIT 1
				");
				$upDom->execute([
					':host' => $dominioCustom,
					':id'   => (int)$rowDom['id']
				]);
			}else{
				$insDom = $pdoM->prepare("
					INSERT INTO {$domainsTable}
						(tenant_id, host, tipo, principal, ativo, created_at, updated_at)
					VALUES
						(:tenant_id, :host, 'dominio', 1, 1, :created_at, :updated_at)
				");
				$insDom->execute([
					':tenant_id' => $id,
					':host'      => $dominioCustom,
					':created_at'=> date('Y-m-d H:i:s'),
					':updated_at'=> date('Y-m-d H:i:s')
				]);
			}
		}
	}

	$pdoM->commit();
	echo "Salvo com Sucesso";

}catch(Throwable $e){
	if($pdoM->inTransaction()){
		$pdoM->rollBack();
	}
	echo "Erro ao salvar: ".$e->getMessage();
}
