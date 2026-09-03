<?php
/**
 * Arquivo: pagamentos/timezone_pagamentos.php
 *
 * Helper pequeno e isolado para os arquivos da pasta pagamentos.
 * Objetivo: aplicar o timezone já definido no contexto do tenant/loja,
 * sem criar regra por slug, cidade ou estado.
 *
 * Ordem:
 * 1. timezone recebido por parâmetro;
 * 2. sessão do tenant/loja;
 * 3. variáveis globais já carregadas pelo sistema/conexao.php;
 * 4. fallback seguro America/Sao_Paulo.
 */

if (!function_exists('bb_pagamentos_normalizar_timezone')) {
    function bb_pagamentos_normalizar_timezone(?string $timezone): string
    {
        $timezone = trim((string)$timezone);

        if ($timezone === '') {
            return 'America/Sao_Paulo';
        }

        if (!in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            return 'America/Sao_Paulo';
        }

        return $timezone;
    }
}

if (!function_exists('bb_pagamentos_timezone_contexto')) {
    function bb_pagamentos_timezone_contexto(): string
    {
        $candidatos = [];

        if (session_status() === PHP_SESSION_ACTIVE) {
            $candidatos[] = $_SESSION['tenant_timezone'] ?? '';
            $candidatos[] = $_SESSION['timezone'] ?? '';
            $candidatos[] = $_SESSION['LOJA']['timezone'] ?? '';
            $candidatos[] = $_SESSION['BARBER_LOJA']['timezone'] ?? '';
            $candidatos[] = $_SESSION['tenant']['timezone'] ?? '';
        }

        $candidatos[] = $GLOBALS['tenant_timezone'] ?? '';
        $candidatos[] = $GLOBALS['timezone_loja'] ?? '';
        $candidatos[] = $GLOBALS['timezone'] ?? '';

        foreach ($candidatos as $timezone) {
            $timezone = trim((string)$timezone);
            if ($timezone !== '' && in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
                return $timezone;
            }
        }

        return 'America/Sao_Paulo';
    }
}

if (!function_exists('bb_pagamentos_aplicar_timezone')) {
    function bb_pagamentos_aplicar_timezone(?string $timezone = null): string
    {
        $timezoneFinal = bb_pagamentos_normalizar_timezone($timezone ?: bb_pagamentos_timezone_contexto());

        try {
            date_default_timezone_set($timezoneFinal);
        } catch (Throwable $e) {
            $timezoneFinal = 'America/Sao_Paulo';
            date_default_timezone_set($timezoneFinal);
        }

        return $timezoneFinal;
    }
}

bb_pagamentos_aplicar_timezone();
