<?php
// /public_html/config/marketplace.php
declare(strict_types=1);

return [

  // ==========================================
  // BANCO DO MARKETPLACE (TABELA tenants)
  // ==========================================
  'master' => [
    'host'  => '127.0.0.1',
    'db'    => 'marketplace',
    'user'  => 'root',
    'pass'  => '',
    'table'           => 'tenants',
    'timezone_column' => 'timezone',
  ],

  // ==========================================
  // BANCO MASTER DO SISTEMA (LOGIN / ADMIN / CONFIG GLOBAL)
  // - no XAMPP acadêmico, usa o banco local marketplace importado no phpMyAdmin
  // - NÃO mexe no fluxo tenant/marketplace
  // ==========================================
  'auth_master' => [
    'host' => '127.0.0.1',
    'db'   => 'marketplace',
    'user' => 'root',
    'pass' => '',
  ],

  // ==========================================
  // CREDENCIAL QUE ACESSA OS BANCOS DAS LOJAS
  // ==========================================
  'tenant' => [
    'host'            => '127.0.0.1',
    'default_db'      => 'marketplace',
    'template_db'     => 'marketplace',
    'user'            => 'root',
    'pass'            => '',
    'db_host'         => '127.0.0.1',
    'db_user_default' => 'root',
    'db_pass_default' => '',
    'cpanel_prefix'   => '',
    'db_prefix'       => '',
    'auto_create_db'  => false,
    'schema_file'     => __DIR__ . '/tenant_schema.sql',
  ],

  // ==========================================
  // SEGURANÇA (DECRYPT de db_pass enc:)
  // ==========================================
  'security' => [
    'tenant_secret'                 => '',
    'enforce_slug_owner'            => true,
    'allow_slug_database_fallback'  => false,
    'owner_columns'                 => ['owner_id','user_id','lojista_id','id_lojista','id_usuario','id_user','master_id','id_master'],
  ],

  // ==========================================
  // TIMEZONE POR TENANT / LOJA
  // ==========================================
  // Regra do marketplace:
  // - O código-fonte continua único para todas as lojas.
  // - O fuso horário vem do cadastro do tenant no banco marketplace.
  // - Coluna oficial: tenants.timezone
  // - Se estiver vazio, inválido ou a coluna não existir, usar America/Sao_Paulo.
  // - Não deduzir timezone por slug, cidade, UF ou nome da loja.
  //
  // Exemplo para Campo Grande/MS:
  // UPDATE tenants
  //    SET timezone = 'America/Campo_Grande'
  //  WHERE slug = 'ph-cabeleireiros-barbearia';
  // ==========================================
  'timezone' => [
    'default'       => 'America/Sao_Paulo',
    'tenant_column' => 'timezone',
    'max_length'    => 64,

    // Lista segura para Brasil. Mantém compatibilidade e evita valores livres inválidos.
    'allowed' => [
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
    ],
  ],

  // ==========================================
  // COMPATIBILIDADE / DADOS DO APP
  // ==========================================
  'base_url'       => 'http://localhost/marketplace',
  'webhook_token'  => '',

  // ==========================================
  // CRON
  // - cobranca_diaria.php exige cron.token quando chamado via URL
  // ==========================================
  'cron' => [
    'token' => '',
  ],

  // ==========================================
  // COBRANÇA / BILLING
  // - aliases compatíveis com cobranca_diaria.php
  // ==========================================
  'billing' => [
    'dias_antes_lembrete' => 3,
    'dias_graca'          => 2,
  ],

  // ==========================================
  // WHATSAPP
  // - mantido vazio para o grupo implementar a API
  // - cobranca_diaria.php usa estes campos se estiverem preenchidos
  // ==========================================
  'whatsapp' => [
    'api'       => '',
    'token'     => '',
    'instancia' => '',
  ],

  'app' => [
    'base_url'            => 'http://localhost/marketplace',
    'url_sistema'         => 'http://localhost/marketplace',
    'remind_days_before'  => 3,
    'grace_days_after'    => 2,
    'webhook_token'       => '',
  ],
];
