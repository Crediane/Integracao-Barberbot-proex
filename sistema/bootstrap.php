<?php
/**
 * /public_html/sistema/bootstrap.php
 * BOOTSTRAP ÚNICO (ALIAS)
 * ------------------------------------------------------------
 * Objetivo: não quebrar os muitos arquivos que dão require neste
 * bootstrap, mantendo toda a lógica central no conexao.php.
 *
 * ✅ Sem mudar regra de negócio
 * ✅ Evita duplicação de sessão/slug
 */
require_once __DIR__ . '/conexao.php';