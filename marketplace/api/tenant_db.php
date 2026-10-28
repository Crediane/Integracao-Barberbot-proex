<?php
// /public_html/api/tenant_db.php
declare(strict_types=1);

if (defined('BB_TENANT_DB_V2')) {
	return;
}
define('BB_TENANT_DB_V2', 1);

if (session_status() !== PHP_SESSION_ACTIVE) {
    /**
     * Sessão isolada por contexto (MASTER x TENANT)
     * Padrão BarberBot (determinístico):
     * - TENANT: /loja/<slug>/...  OU  ?loja=<slug>
     * - MASTER: /sistema/... (sem loja)  E  /agendamentos (sem loja)  E  /ajax (sem loja)
     * - SITE: demais rotas públicas
     */
    $__bb_reqPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
    $__bb_refPath = '';
    if (!empty($_SERVER['HTTP_REFERER'])) {
        $__bb_refPath = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: '';
    }

    if (!function_exists('bb_sess_clean_slug')) {
        function bb_sess_clean_slug($v) {
            $v = strtolower(trim((string)$v));
            return preg_replace('/[^a-z0-9_-]/', '', $v);
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

    $__bb_isLoja = ($__bb_reqSlug !== '') || (strpos($__bb_reqPath, '/loja/') === 0) || (strpos($__bb_refPath, '/loja/') === 0);
    $__bb_isSistema = (strpos($__bb_reqPath, '/sistema') === 0) || (strpos($__bb_refPath, '/sistema') === 0);
    $__bb_isAgend = (strpos($__bb_reqPath, '/agendamentos') === 0) || (strpos($__bb_refPath, '/agendamentos') === 0);
    $__bb_isAjax = (strpos($__bb_reqPath, '/ajax/') === 0) || (strpos($__bb_refPath, '/ajax/') === 0);

    $__bb_forceMaster = (
        ($__bb_isSistema && !$__bb_isLoja && empty($_GET['loja']) && empty($_POST['loja'])) ||
        ($__bb_isAgend && !$__bb_isLoja && empty($_GET['loja']) && empty($_POST['loja'])) ||
        ($__bb_isAjax && !$__bb_isLoja && empty($_GET['loja']) && empty($_POST['loja']))
    );

    $__bb_tenantSessionName = 'BB_TENANT';
    if ($__bb_reqSlug !== '') {
        $__bb_tenantSessionName = 'BB_TENANT_' . strtoupper(substr(sha1($__bb_reqSlug), 0, 12));
    }

    $__bb_desiredSession = $__bb_forceMaster ? 'BB_MASTER' : ($__bb_isLoja ? $__bb_tenantSessionName : 'BB_SITE');

    @session_name($__bb_desiredSession);
    if (!empty($_COOKIE[$__bb_desiredSession])) {
        @session_id((string)$_COOKIE[$__bb_desiredSession]);
    }
    @session_start();
}
require_once(__DIR__ . '/tenant.php');

/**
 * Admin do sistema (dono) NÃO pode ser forçado a ter tenant.
 * Se for admin OU estiver acessando /sistema, a gente DESLIGA filtro de tenant.
 */
if (!function_exists('bb_is_system_area')) {
	function bb_is_system_area(): bool {
		$uri = (string)($_SERVER['REQUEST_URI'] ?? '');
		return (strpos($uri, '/sistema') === 0);
	}
}


/**
 * Detecta se a requisição está em contexto de LOJA (/loja/<slug>/...) ou se existe tenant_slug ativo.
 * - Mantém o padrão do seu sistema e evita confundir administrador do salão com admin do sistema.
 */
if (!function_exists('bb_is_loja_area')) {
	function bb_is_loja_area(): bool {
		$uri = (string)($_SERVER['REQUEST_URI'] ?? '');
		$path = parse_url($uri, PHP_URL_PATH) ?: $uri;
		$refPath = '';
		if (!empty($_SERVER['HTTP_REFERER'])) {
			$refPath = parse_url($_SERVER['HTTP_REFERER'], PHP_URL_PATH) ?: '';
		}
		// Loja somente quando estiver em /loja/<slug>/... ou quando vier ?loja=
		if ($path !== '' && strpos($path, '/loja/') === 0) return true;
		if (!empty($_GET['loja']) || !empty($_POST['loja'])) return true;
		if ($refPath !== '' && strpos($refPath, '/loja/') === 0) return true;
		return false;
	}
}

/**
 * ✅ IMPORTANTE:
 * No seu marketplace atual, cada loja usa um BANCO separado (tenant_db).
 * Então NÃO precisamos de filtro por coluna (id_loja/usuario/etc), pois o isolamento já é pelo banco.
 * Se detectar que estamos em uma loja com tenant_db definido, desliga filtros de tenant por coluna.
 */
if (!function_exists('bb_is_tenant_db_context')) {
	function bb_is_tenant_db_context(): bool {
		// só considera tenant quando estiver em /loja (ou tenant_slug ativo) e houver tenant_db na sessão
		if (!bb_is_loja_area()) return false;
		if (empty($_SESSION['tenant_db'])) return false;
		if (empty($_SESSION['tenant_slug'])) return false;
		// Se estiver no /sistema (área do dono do sistema), não força contexto de tenant
		if (bb_is_system_area()) return false;
		return true;
	}
}
if (!function_exists('bb_is_admin_user')) {
	function bb_is_admin_user(): bool {
		$keys = ['nivel_usuario','nivel','nivel_usu','cargo','usuario_nivel'];
		foreach ($keys as $k) {
			if (!empty($_SESSION[$k]) && strtolower((string)$_SESSION[$k]) === 'administrador') {
				return true;
			}
		}
		return false;
	}
}

if (!function_exists('bb_tenant_id_from_session')) {
	function bb_tenant_id_from_session(): int {
		$keys = ['id_loja','loja','tenant_id','id_empresa','empresa','id_usuario','usuario'];
		foreach ($keys as $k) {
			if (isset($_SESSION[$k]) && (int)$_SESSION[$k] > 0) {
				return (int)$_SESSION[$k];
			}
		}
		return 0;
	}
}

/**
 * Contexto do tenant
 */
if (!function_exists('bb_tenant_ctx')) {
	function bb_tenant_ctx(): array {
		$slug = function_exists('tenant_slug') ? tenant_slug() : '';

		// ✅ Admin do sistema (dono) NÃO usa tenant (somente em /sistema)
		$isSystemOwner = (bb_is_system_area() && bb_is_admin_user());

		// ✅ Em contexto de loja com banco separado (tenant_db), NÃO aplicar filtro por coluna
		$isTenantDbCtx = bb_is_tenant_db_context();

		// "is_admin" aqui significa: não aplicar filtro por coluna
		$isAdmin = ($isSystemOwner || $isTenantDbCtx);

		$tenantId = $isAdmin ? 0 : bb_tenant_id_from_session();

		return [
			'tenant_slug' => (string)$slug,
			'tenant_id'   => (int)$tenantId,
			'is_admin'    => (bool)$isAdmin
		];
	}
}

/**
 * Descobre qual coluna de tenant a tabela tem (se tiver).
 * Se for admin -> NÃO filtra.
 */
if (!function_exists('bb_table_tenant_filter')) {
	function bb_table_tenant_filter(PDO $pdo, string $table, array $ctx): array {
		static $cache = [];

		$table = trim($table);

		// ✅ Admin do sistema nunca filtra
		if (!empty($ctx['is_admin'])) {
			return ['col' => '', 'val' => null, 'pdo_type' => PDO::PARAM_STR];
		}

		$tenantVal = (int)($ctx['tenant_id'] ?? 0);

		// ✅ Sem tenant -> não filtra (não trava)
		if ($tenantVal <= 0) {
			return ['col' => '', 'val' => null, 'pdo_type' => PDO::PARAM_STR];
		}

		if (!isset($cache[$table])) {
			$cands = ['id_loja','loja','tenant_id','id_empresa','empresa','id_usuario','usuario'];

			$sql = "SELECT COLUMN_NAME, DATA_TYPE
					FROM INFORMATION_SCHEMA.COLUMNS
					WHERE TABLE_SCHEMA = DATABASE()
					  AND TABLE_NAME = :t";
			$st = $pdo->prepare($sql);
			$st->execute([':t' => $table]);
			$cols = $st->fetchAll(PDO::FETCH_ASSOC);

			$map = [];
			foreach ($cols as $r) {
				$map[(string)$r['COLUMN_NAME']] = strtolower((string)$r['DATA_TYPE']);
			}

			$colFound = '';
			foreach ($cands as $c) {
				if (isset($map[$c])) { $colFound = $c; break; }
			}

			$pdoType = PDO::PARAM_INT;
			if ($colFound !== '') {
				$dt = $map[$colFound] ?? '';
				if (in_array($dt, ['varchar','char','text'], true)) {
					$pdoType = PDO::PARAM_STR;
				}
			}

			$cache[$table] = ['col' => $colFound, 'pdo_type' => $pdoType];
		}

		$col = $cache[$table]['col'];
		if ($col === '') {
			return ['col' => '', 'val' => null, 'pdo_type' => PDO::PARAM_STR];
		}

		$pdoType = $cache[$table]['pdo_type'] ?? PDO::PARAM_INT;
		$val = ($pdoType === PDO::PARAM_INT) ? $tenantVal : (string)$tenantVal;

		return ['col' => $col, 'val' => $val, 'pdo_type' => $pdoType];
	}
}
