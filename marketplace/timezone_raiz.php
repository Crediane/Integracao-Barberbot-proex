<?php
/**
 * Helper de timezone para arquivos da raiz do marketplace.
 *
 * Objetivo:
 * - respeitar o timezone já carregado no contexto do tenant/loja;
 * - não consultar banco aqui;
 * - não criar regra por slug, cidade ou estado;
 * - manter fallback seguro em America/Sao_Paulo.
 */

if (!function_exists('bb_raiz_normalizar_timezone')) {
    function bb_raiz_normalizar_timezone($timezone): string
    {
        $timezone = trim((string)$timezone);

        if ($timezone === '') {
            return 'America/Sao_Paulo';
        }

        static $timezonesValidos = null;

        if ($timezonesValidos === null) {
            $timezonesValidos = array_flip(DateTimeZone::listIdentifiers());
        }

        if (!isset($timezonesValidos[$timezone])) {
            return 'America/Sao_Paulo';
        }

        return $timezone;
    }
}

if (!function_exists('bb_raiz_aplicar_timezone_tenant')) {
    function bb_raiz_aplicar_timezone_tenant(): string
    {
        $fontes = [];

        if (isset($_SESSION) && is_array($_SESSION)) {
            $fontes[] = $_SESSION['tenant_timezone'] ?? null;
            $fontes[] = $_SESSION['timezone_loja'] ?? null;

            if (!empty($_SESSION['BARBER_LOJA']) && is_array($_SESSION['BARBER_LOJA'])) {
                $fontes[] = $_SESSION['BARBER_LOJA']['timezone'] ?? null;
            }

            if (!empty($_SESSION['LOJA']) && is_array($_SESSION['LOJA'])) {
                $fontes[] = $_SESSION['LOJA']['timezone'] ?? null;
            }

            if (!empty($_SESSION['tenant_context']) && is_array($_SESSION['tenant_context'])) {
                $fontes[] = $_SESSION['tenant_context']['timezone'] ?? null;
            }
        }

        $fontes[] = $GLOBALS['tenant_timezone'] ?? null;
        $fontes[] = $GLOBALS['timezone_loja'] ?? null;
        $fontes[] = $GLOBALS['bb_tenant_timezone'] ?? null;

        if (!empty($GLOBALS['bb_tenant']) && is_array($GLOBALS['bb_tenant'])) {
            $fontes[] = $GLOBALS['bb_tenant']['timezone'] ?? null;
        }

        if (!empty($GLOBALS['tenant']) && is_array($GLOBALS['tenant'])) {
            $fontes[] = $GLOBALS['tenant']['timezone'] ?? null;
        }

        $timezoneAplicado = 'America/Sao_Paulo';

        foreach ($fontes as $timezoneFonte) {
            $timezoneFonte = trim((string)$timezoneFonte);
            if ($timezoneFonte === '') {
                continue;
            }

            $timezoneAplicado = bb_raiz_normalizar_timezone($timezoneFonte);
            break;
        }

        date_default_timezone_set($timezoneAplicado);

        if (isset($_SESSION) && is_array($_SESSION)) {
            $_SESSION['tenant_timezone'] = $timezoneAplicado;
        }

        $GLOBALS['tenant_timezone'] = $timezoneAplicado;
        $GLOBALS['bb_tenant_timezone'] = $timezoneAplicado;

        return $timezoneAplicado;
    }
}

bb_raiz_aplicar_timezone_tenant();
