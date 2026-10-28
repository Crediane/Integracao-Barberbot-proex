<?php 
// ===== Sessão isolada por contexto (MASTER x TENANT) =====
// Evita que o cookie padrão (PHPSESSID) misture MASTER x TENANT.
// Regra:
// - /sistema (sem /loja/ e sem ?loja=) => BB_MASTER
// - /loja/<slug>/... OU ?loja=...      => BB_TENANT
// - demais                              => BB_SITE
$__bb_reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
$__bb_refPath = '';
if (!empty($_SERVER['HTTP_REFERER'])) {
  $__bb_refPath = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: '';
}


// ===== Sessão TENANT por SLUG (evita misturar lojas no mesmo navegador) =====
if (!function_exists('bb_sess_clean_slug')) {
  function bb_sess_clean_slug($v) {
    $v = strtolower(trim((string)$v));
    return preg_replace('/[^a-z0-9_-]/', '', $v);
  }
}

// garante $__bb_refPath quando o arquivo não define
if (!isset($__bb_refPath)) {
  $__bb_refPath = '';
  if (!empty($_SERVER['HTTP_REFERER'])) {
    $__bb_refPath = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: '';
  }
}

$__bb_reqSlug = '';
if (!empty($_GET['loja'])) {
  $__bb_reqSlug = bb_sess_clean_slug($_GET['loja']);
} elseif (!empty($_POST['loja'])) {
  $__bb_reqSlug = bb_sess_clean_slug($_POST['loja']);
} else {
  if (preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath, $mSlug)) {
    $__bb_reqSlug = bb_sess_clean_slug($mSlug[1]);
  } elseif (!empty($__bb_refPath) && preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_refPath, $mSlug2)) {
    $__bb_reqSlug = bb_sess_clean_slug($mSlug2[1]);
  }
}

$__bb_tenantSessionName = 'BB_TENANT';
if ($__bb_reqSlug !== '') {
  $__bb_tenantSessionName = 'BB_TENANT_' . strtoupper(substr(sha1($__bb_reqSlug), 0, 12));
}

$__bb_lojaByPath = (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_reqPath)
                || (bool)preg_match('~^/loja/([a-z0-9_-]+)(?:/|$)~i', $__bb_refPath);

$__bb_hasLoja = $__bb_lojaByPath || !empty($_GET['loja']) || !empty($_POST['loja']);
$__bb_isSistema = (strpos($__bb_reqPath, '/sistema') === 0) || (strpos($__bb_refPath, '/sistema') === 0);

// Força MASTER em /sistema quando NÃO é loja por PATH e NÃO tem ?loja=
$__bb_forceMaster = ($__bb_isSistema && !$__bb_lojaByPath && empty($_GET['loja']) && empty($_POST['loja']));

$__bb_desiredSession = $__bb_forceMaster ? 'BB_MASTER' : ($__bb_hasLoja ? $__bb_tenantSessionName : 'BB_SITE');

// Inicia/ajusta a sessão corretamente (usa o cookie do nome escolhido)
if (session_status() === PHP_SESSION_ACTIVE) {
  if (session_name() !== $__bb_desiredSession) {
    session_write_close();
    @session_name($__bb_desiredSession);
    if (!empty($_COOKIE[$__bb_desiredSession])) {
      @session_id((string)$_COOKIE[$__bb_desiredSession]);
    }
    @session_start();
  }
} else {
  @session_name($__bb_desiredSession);
  if (!empty($_COOKIE[$__bb_desiredSession])) {
    @session_id((string)$_COOKIE[$__bb_desiredSession]);
  }
  @session_start();
}
$id_usuario = $_SESSION['id'];

$home = 'ocultar';
$comanda = 'ocultar';
$marketplace = 'ocultar';

//grupo Configurações
$duvidas = 'ocultar';
$doc  = 'ocultar';
$dispositivos = 'ocultar';
$configuracoes = 'ocultar';

$grupos_disparos = 'ocultar';
$marketing = 'ocultar';
$calendario = 'ocultar';
$caixas = 'ocultar';
$planos = 'ocultar';
$verificar_pgtos = 'ocultar';


//grupo pessoas
$usuarios = 'ocultar';
$funcionarios = 'ocultar';
$clientes = 'ocultar';
$clientes_retorno = 'ocultar';
$fornecedores = 'ocultar';


//grupo cadastros
$servicos = 'ocultar';
$cargos = 'ocultar';
$cat_servicos = 'ocultar';
$grupos = 'ocultar';
$acessos = 'ocultar';
$pgto = 'ocultar';
$dias_bloqueio = 'ocultar';
$assinaturas = 'ocultar';
$frequencias = 'ocultar';

//grupo proteses
$proteses = 'ocultar';
$manutencoes = 'ocultar';
$analises = 'ocultar';
$simulador = 'ocultar';

//grupo produtos
$produtos = 'ocultar';
$cat_produtos = 'ocultar';
$estoque = 'ocultar';
$saidas = 'ocultar';
$entradas = 'ocultar';


//grupo financeiro
$vendas = 'ocultar';
$compras = 'ocultar';
$pagar = 'ocultar';
$receber = 'ocultar';
$comissoes = 'ocultar';
$receber_vencidas = 'ocultar';
$cobrancas_pagas = 'ocultar';

//agendamentos / servico
$agendamentos = 'ocultar';
$servicos_agenda = 'ocultar';


//relatorios
$rel_produtos = 'ocultar';
$rel_entradas = 'ocultar';
$rel_saidas = 'ocultar';
$rel_comissoes = 'ocultar';
$rel_contas = 'ocultar';
$rel_aniv = 'ocultar';
$rel_lucro = 'ocultar';
$rel_servicos = 'ocultar';
$rel_ina = 'ocultar';

//dados site
$textos_index = 'ocultar';
$comentarios = 'ocultar';
$avaliacoes = 'ocultar';
$faq = 'ocultar';
$foto_admin = 'ocultar';
$site = 'ocultar';
$bio = 'ocultar'; 




$query = $pdo->query("SELECT * FROM usuarios_permissoes where usuario = '$id_usuario'");
$res = $query->fetchAll(PDO::FETCH_ASSOC);
$total_reg = @count($res);
if($total_reg > 0){
	for($i=0; $i < $total_reg; $i++){
		foreach ($res[$i] as $key => $value){}
		$permissao = $res[$i]['permissao'];
		
		$query2 = $pdo->query("SELECT * FROM acessos where id = '$permissao'");
		$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);
		$nome = $res2[0]['nome'];
		$chave = $res2[0]['chave'];
		$id = $res2[0]['id'];

		if($chave == 'home'){
			$home = '';
		}


		if($chave == 'proteses'){
			$proteses = '';
		}


		if($chave == 'manutencoes'){
			$manutencoes = '';
		}


		if($chave == 'analises'){
			$analises = '';
		}


		if($chave == 'simulador'){
			$simulador = '';
		}


		if($chave == 'marketplace'){
			$marketplace = '';
		}


		if($chave == 'cobrancas_pagas'){
			$cobrancas_pagas = '';
		}


		if($chave == 'configuracoes'){
			$configuracoes = '';
		}


		if($chave == 'duvidas'){
			$duvidas = '';
		}


		if($chave == 'doc '){
			$doc  = '';
		}


		if($chave == 'grupos_disparos '){
			$grupos_disparos  = '';
		}


		if($chave == 'comanda'){
			$comanda = '';
		}

		if($chave == 'marketing'){
			$marketing = '';
		}

		if($chave == 'calendario'){
			$calendario = '';
		}

		if($chave == 'caixas'){
			$caixas = '';
		}



		if($chave == 'grupos_disparos '){
			$grupos_disparos  = '';
		}


		if($chave == 'comanda'){
			$comanda = '';
		}

		if($chave == 'marketing'){
			$marketing = '';
		}

		if($chave == 'calendario'){
			$calendario = '';
		}

		if($chave == 'caixas'){
			$caixas = '';
		}

		if($chave == 'planos'){
			$planos = '';
		}

			if($chave == 'frequencias'){
			$frequencias = '';
		}

			if($chave == 'verificar_pgtos'){
			$verificar_pgtos = '';
		}

			if($chave == 'dispositivos'){
			$dispositivos = '';
		}


		if($chave == 'usuarios'){
			$usuarios = '';
		}

		if($chave == 'funcionarios'){
			$funcionarios = '';
		}

		if($chave == 'clientes'){
			$clientes = '';
		}

		if($chave == 'clientes_retorno'){
			$clientes_retorno = '';
		}

		if($chave == 'fornecedores'){
			$fornecedores = '';
		}


		if($chave == 'servicos'){
			$servicos = '';
		}

		if($chave == 'cargos'){
			$cargos = '';
		}

		if($chave == 'cat_servicos'){
			$cat_servicos = '';
		}

		if($chave == 'grupos'){
			$grupos = '';
		}

		if($chave == 'acessos'){
			$acessos = '';
		}

		if($chave == 'pgto'){
			$pgto = '';
		}

		if($chave == 'dias_bloqueio'){
			$dias_bloqueio = '';
		}

		if($chave == 'assinaturas'){
			$assinaturas = '';
		}


		if($chave == 'produtos'){
			$produtos = '';
		}

		if($chave == 'cat_produtos'){
			$cat_produtos = '';
		}

		if($chave == 'estoque'){
			$estoque = '';
		}

		if($chave == 'saidas'){
			$saidas = '';
		}

		if($chave == 'entradas'){
			$entradas = '';
		}


		if($chave == 'compras'){
			$compras = '';
		}

		if($chave == 'vendas'){
			$vendas = '';
		}

		if($chave == 'pagar'){
			$pagar = '';
		}

		if($chave == 'receber'){
			$receber = '';
		}

		if($chave == 'comissoes'){
			$comissoes = '';
		}

		if($chave == 'receber_vencidas'){
			$receber_vencidas = '';
		}
		

		if($chave == 'agendamentos'){
			$agendamentos = '';
		}

		if($chave == 'servicos_agenda'){
			$servicos_agenda = '';
		}




		if($chave == 'rel_produtos'){
			$rel_produtos = '';
		}

		if($chave == 'rel_entradas'){
			$rel_entradas = '';
		}

		if($chave == 'rel_saidas'){
			$rel_saidas = '';
		}

		if($chave == 'rel_comissoes'){
			$rel_comissoes = '';
		}

		if($chave == 'rel_contas'){
			$rel_contas = '';
		}

		if($chave == 'rel_aniv'){
			$rel_aniv = '';
		}

		if($chave == 'rel_lucro'){
			$rel_lucro = '';
		}

		if($chave == 'rel_servicos'){
			$rel_servicos = '';
		}

		if($chave == 'rel_ina'){
			$rel_ina = '';
		}


		if($chave == 'textos_index'){
			$textos_index = '';
		}

		if($chave == 'doc'){
			$doc = '';
		}

		if($chave == 'duvidas'){
			$duvidas = '';
		}

		if($chave == 'avaliacoes'){
			$ravaliacoes = '';
		}

		if($chave == 'foto_admin'){
			$foto_admin = '';
		}

		if($chave == 'site'){
			$site = '';
		}


		if($chave == 'bio'){
			$bio = '';
		}

		if($chave == 'comentarios'){
			$comentarios = '';
		}

	}

}



if($home != 'ocultar'){
	$pag_inicial = 'home';
}else if($atendimento == 'Sim'){
	$pag_inicial = 'agenda';
}else{
	$query = $pdo->query("SELECT * FROM usuarios_permissoes where usuario = '$id_usuario' order by id asc limit 1");
	$res = $query->fetchAll(PDO::FETCH_ASSOC);
	$total_reg = @count($res);
	if($total_reg > 0){	
			$permissao = $res[0]['permissao'];		
			$query2 = $pdo->query("SELECT * FROM acessos where id = '$permissao'");
			$res2 = $query2->fetchAll(PDO::FETCH_ASSOC);		
			$pag_inicial = $res2[0]['chave'];		

	}
}



if($dispositivos == 'ocultar' and $duvidas == 'ocultar' and $doc == 'ocultar'){
	$menu_configuracoes = 'ocultar';
}else{
	$menu_configuracoes = '';
}



if($usuarios == 'ocultar' and $funcionarios == 'ocultar' and $clientes == 'ocultar' and $clientes_retorno == 'ocultar' and $fornecedores == 'ocultar'){
	$menu_pessoas = 'ocultar';
}else{
	$menu_pessoas = '';
}

if($proteses == 'ocultar' and $manutencoes == 'ocultar' and $analises == 'ocultar' and $simulador == 'ocultar'){
	$menu_proteses = 'ocultar';
}else{
	$menu_proteses = '';
}

if($servicos == 'ocultar' and $cargos == 'ocultar' and $cat_servicos == 'ocultar' and $grupos == 'ocultar' and $acessos == 'ocultar' and $pgto == 'ocultar' and $dias_bloqueio == 'ocultar' and $assinaturas == 'ocultar' and $frequencias == 'ocultar'){
	$menu_cadastros = 'ocultar';
}else{
	$menu_cadastros = '';
}



if($produtos == 'ocultar' and $cat_produtos == 'ocultar' and $estoque == 'ocultar' and $saidas == 'ocultar' and $entradas == 'ocultar'){
	$menu_produtos = 'ocultar';
}else{
	$menu_produtos = '';
}



if($compras == 'ocultar' and $vendas == 'ocultar' and $pagar == 'ocultar' and $receber == 'ocultar' and $comissoes == 'ocultar' and $receber_vencidas == 'ocultar'){
	$menu_financeiro = 'ocultar';
}else{
	$menu_financeiro = '';
}



if($planos == 'ocultar' and $receber == 'ocultar' and $cobrancas_pagas == 'ocultar'){
	$menu_assinaturas = 'ocultar';
}else{
	$menu_assinaturas = '';
}



if($agendamentos == 'ocultar' and $servicos_agenda == 'ocultar' ){
	$menu_agendamentos = 'ocultar';
}else{
	$menu_agendamentos = '';
}



if($rel_produtos == 'ocultar' and $rel_lucro == 'ocultar' and $rel_aniv == 'ocultar' and $rel_contas == 'ocultar' and $rel_comissoes == 'ocultar' and $rel_saidas == 'ocultar' and $rel_entradas == 'ocultar' and $rel_servicos == 'ocultar' and $rel_ina == 'ocultar'){
	$menu_relatorio = 'ocultar';
}else{
	$menu_relatorio = '';
}



if($marketing == 'ocultar' and $grupos_disparos == 'ocultar'){
	$menu_marketing = 'ocultar';
}else{
	$menu_marketing = '';
}



if($textos_index == 'ocultar' and $comentarios == 'ocultar' and $site == 'ocultar' and $doc == 'ocultar' and $foto_admin == 'ocultar' and $fag == 'ocultar' and $avaliacoes == 'ocultar' ){
	$menu_site = 'ocultar';
}else{
	$menu_site = '';
}


 ?>