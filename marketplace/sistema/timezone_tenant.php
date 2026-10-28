<?php
// /public_html/sistema/timezone_tenant.php
// ============================================================================
// Helper de timezone por tenant/loja.
// Objetivo:
// - Reaplicar o fuso horário já carregado pelo sistema/conexao.php.
// - Evitar que arquivos internos voltem para America/Sao_Paulo quando a loja
//   usa outro timezone, como America/Campo_Grande.
// - Manter fallback seguro sem alterar regra de agendamento/cobrança.
// ============================================================================

if (!defined('BB_TIMEZONE_PADRAO')) {
    define('BB_TIMEZONE_PADRAO', 'America/Sao_Paulo');
}

if (!function_exists('bb_timezone_padrao')) {
    function bb_timezone_padrao(): string
    {
        return BB_TIMEZONE_PADRAO;
    }
}

if (!function_exists('bb_normalizar_timezone_tenant')) {
    function bb_normalizar_timezone_tenant($timezone): string
    {
        $timezone = trim((string)$timezone);

        if ($timezone === '') {
            return bb_timezone_padrao();
        }

        $timezone = str_replace(["\0", "\r", "\n", "\t", "\\"], '', $timezone);
        $timezone = trim($timezone);

        if ($timezone === '' || strlen($timezone) > 64) {
            return bb_timezone_padrao();
        }

        $timezonesPermitidosBrasil = [
            'America/Sao_Paulo',
            'America/Campo_Grande',
            'America/Cuiaba',
            'America/Manaus',
            'America/Porto_Velho',
            'America/Boa_Vista',
            'America/Rio_Branco',
            'America/Eirunepe',
            'America/Noronha',
            'America/Fortaleza',
            'America/Recife',
            'America/Bahia',
            'America/Belem',
            'America/Santarem',
            'America/Maceio',
            'America/Araguaina',
        ];

        if (in_array($timezone, $timezonesPermitidosBrasil, true)) {
            return $timezone;
        }

        try {
            new DateTimeZone($timezone);
            return $timezone;
        } catch (Throwable $e) {
            return bb_timezone_padrao();
        }
    }
}

if (!function_exists('bb_resolver_timezone_tenant_atual')) {
    function bb_resolver_timezone_tenant_atual(): string
    {
        $candidatos = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            $candidatos[] = $_SESSION['tenant_timezone'] ?? '';
            $candidatos[] = $_SESSION['BARBER_LOJA']['timezone'] ?? '';
            $candidatos[] = $_SESSION['LOJA']['timezone'] ?? '';
        }

        $candidatos[] = $GLOBALS['tenant_timezone'] ?? '';
        $candidatos[] = $GLOBALS['timezone_loja'] ?? '';
        $candidatos[] = $GLOBALS['timezone'] ?? '';

        foreach ($candidatos as $timezone) {
            $timezone = trim((string)$timezone);
            if ($timezone !== '') {
                return bb_normalizar_timezone_tenant($timezone);
            }
        }

        return bb_timezone_padrao();
    }
}

if (!function_exists('bb_aplicar_timezone_tenant_atual')) {
    function bb_aplicar_timezone_tenant_atual(?string $timezone = null): string
    {
        $timezoneFinal = bb_normalizar_timezone_tenant(
            $timezone !== null ? $timezone : bb_resolver_timezone_tenant_atual()
        );

        if (!date_default_timezone_set($timezoneFinal)) {
            $timezoneFinal = bb_timezone_padrao();
            date_default_timezone_set($timezoneFinal);
        }

        if (session_status() === PHP_SESSION_ACTIVE) {
            $_SESSION['tenant_timezone'] = $timezoneFinal;

            if (!isset($_SESSION['BARBER_LOJA']) || !is_array($_SESSION['BARBER_LOJA'])) {
                $_SESSION['BARBER_LOJA'] = [];
            }

            $_SESSION['BARBER_LOJA']['timezone'] = $timezoneFinal;

            if (!isset($_SESSION['LOJA']) || !is_array($_SESSION['LOJA'])) {
                $_SESSION['LOJA'] = [];
            }

            $_SESSION['LOJA']['timezone'] = $timezoneFinal;
        }

        return $timezoneFinal;
    }
}

bb_aplicar_timezone_tenant_atual();
