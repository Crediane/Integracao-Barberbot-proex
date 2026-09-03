<?php
/**
 * BarberBot Marketplace
 * ------------------------------------------------------------
 * Arquivo: /public_html/config/admin_create_tenant.php
 *
 * MOTIVO DE EXISTIR 2 ARQUIVOS:
 * - O endpoint correto é /public_html/api/admin_create_tenant.php
 * - Este arquivo em /config/ ficou como duplicado/legado.
 *
 * BUG:
 * - A versão duplicada em /config/ apontava para require __DIR__ . '/bootstrap.php'
 *   porém NÃO existe bootstrap.php em /config/ => Fatal error / endpoint quebrado.
 *
 * CORREÇÃO (SEM MUDAR LÓGICA):
 * - Mantém compatibilidade: qualquer chamada para /config/admin_create_tenant.php
 *   passa a executar o endpoint oficial em /api/admin_create_tenant.php.
 */

require_once __DIR__ . '/../api/admin_create_tenant.php';
