<?php
/**
 * BarberBot - Timezone por tenant para endpoints AJAX.
 *
 * Objetivo:
 * - Respeitar o timezone já carregado pelo /sistema/conexao.php.
 * - Não criar regra por slug, cidade ou estado.
 * - Manter o mesmo código para todos os usuários/lojas.
 * - Usar America/Sao_Paulo apenas como fallback seguro.
 */

if (!function_exists('bb_ajax_timezone_padrao')) {
    function bb_ajax_timezone_padrao(): string
    {
        if (defined('BB_TIMEZONE_PADRAO')) {
            $timezone = trim((string)BB_TIMEZONE_PADRAO);
            if ($timezone !== '') {
                return $timezone;
            }
        }

        return 'America/Sao_Paulo';
    }
}

if (!function_exists('bb_ajax_normalizar_timezone')) {
    function bb_ajax_normalizar_timezone($timezone): string
    {
        $timezone = trim((string)$timezone);
        $timezone = str_replace(["\0", "\r", "\n", "\t", "\\"], '', $timezone);
        $timezone = trim($timezone);

        if ($timezone === '' || strlen($timezone) > 64) {
            return bb_ajax_timezone_padrao();
        }

        try {
            new DateTimeZone($timezone);
            return $timezone;
        } catch (Throwable $e) {
            return bb_ajax_timezone_padrao();
        }
    }
}

if (!function_exists('bb_ajax_obter_timezone_contexto')) {
    function bb_ajax_obter_timezone_contexto(): string
    {
        $candidatos = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            $candidatos[] = $_SESSION['tenant_timezone'] ?? null;
            $candidatos[] = $_SESSION['BARBER_LOJA']['timezone'] ?? null;
            $candidatos[] = $_SESSION['LOJA']['timezone'] ?? null;
        }

        $candidatos[] = $GLOBALS['tenant_timezone'] ?? null;
        $candidatos[] = $GLOBALS['timezone_tenant'] ?? null;
        $candidatos[] = $GLOBALS['timezone_loja'] ?? null;
        $candidatos[] = bb_ajax_timezone_padrao();

        foreach ($candidatos as $timezone) {
            $timezoneNormalizado = bb_ajax_normalizar_timezone($timezone);
            if ($timezoneNormalizado !== '') {
                return $timezoneNormalizado;
            }
        }

        return bb_ajax_timezone_padrao();
    }
}

if (!function_exists('bb_ajax_aplicar_timezone_contexto')) {
    function bb_ajax_aplicar_timezone_contexto(): string
    {
        $timezone = bb_ajax_obter_timezone_contexto();

        if (!date_default_timezone_set($timezone)) {
            $timezone = bb_ajax_timezone_padrao();
            date_default_timezone_set($timezone);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['tenant_timezone'] = $timezone;

            if (!isset($_SESSION['BARBER_LOJA']) || !is_array($_SESSION['BARBER_LOJA'])) {
                $_SESSION['BARBER_LOJA'] = [];
            }

            $_SESSION['BARBER_LOJA']['timezone'] = $timezone;
        }

        $GLOBALS['tenant_timezone'] = $timezone;
        $GLOBALS['timezone_tenant'] = $timezone;
        $GLOBALS['timezone_loja'] = $timezone;

        return $timezone;
    }
}

$bb_ajax_timezone_atual = bb_ajax_aplicar_timezone_contexto();
?>
