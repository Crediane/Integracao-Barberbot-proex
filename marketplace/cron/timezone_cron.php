<?php
/**
 * /public_html/cron/timezone_cron.php
 *
 * Helper simples de timezone para rotinas cron do marketplace BarberBot.
 *
 * Objetivo:
 * - manter o mesmo código para todos os tenants;
 * - usar o timezone configurado no tenant, quando recebido pelo cron;
 * - evitar regra fixa por slug, cidade ou estado;
 * - manter fallback seguro em America/Sao_Paulo.
 */

if (!function_exists('bb_cron_timezone_padrao')) {
    function bb_cron_timezone_padrao(): string
    {
        return 'America/Sao_Paulo';
    }
}

if (!function_exists('bb_cron_timezone_normalizar')) {
    function bb_cron_timezone_normalizar($timezone): string
    {
        $timezone = trim((string)$timezone);

        if ($timezone === '' || strlen($timezone) > 64) {
            return bb_cron_timezone_padrao();
        }

        static $timezonesValidos = null;
        if ($timezonesValidos === null) {
            $timezonesValidos = DateTimeZone::listIdentifiers();
        }

        if (!in_array($timezone, $timezonesValidos, true)) {
            return bb_cron_timezone_padrao();
        }

        return $timezone;
    }
}

if (!function_exists('bb_cron_aplicar_timezone')) {
    function bb_cron_aplicar_timezone($timezone = null): string
    {
        $timezoneFinal = bb_cron_timezone_normalizar($timezone);

        try {
            date_default_timezone_set($timezoneFinal);
        } catch (Throwable $e) {
            $timezoneFinal = bb_cron_timezone_padrao();
            date_default_timezone_set($timezoneFinal);
        }

        $GLOBALS['BB_CRON_TIMEZONE_ATUAL'] = $timezoneFinal;

        return $timezoneFinal;
    }
}

if (!function_exists('bb_cron_timezone_de_linha')) {
    function bb_cron_timezone_de_linha(array $linha, array $config = []): string
    {
        foreach (['timezone', 'fuso_horario', 'time_zone'] as $campo) {
            if (!empty($linha[$campo])) {
                return bb_cron_timezone_normalizar($linha[$campo]);
            }
        }

        foreach (['timezone', 'fuso_horario', 'time_zone'] as $campo) {
            if (!empty($config[$campo])) {
                return bb_cron_timezone_normalizar($config[$campo]);
            }
        }

        return bb_cron_timezone_padrao();
    }
}

if (!function_exists('bb_cron_data_hoje')) {
    function bb_cron_data_hoje(): string
    {
        return date('Y-m-d');
    }
}

if (!function_exists('bb_cron_hora_agora')) {
    function bb_cron_hora_agora(): string
    {
        return date('H:i:s');
    }
}

if (!function_exists('bb_cron_data_hora_agora')) {
    function bb_cron_data_hora_agora(): string
    {
        return date('Y-m-d H:i:s');
    }
}

// Aplica fallback inicial. Rotinas por tenant podem reaplicar o timezone de cada loja.
bb_cron_aplicar_timezone();
