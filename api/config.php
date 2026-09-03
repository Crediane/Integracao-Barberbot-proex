<?php
// /api/config.php
return [
  'base_domain' => 'localhost/marketplace', // Ajustado para a pasta local

  // Banco MASTER (cadastro de tenants)
  'master_db' => [
    'host' => 'localhost',
    'name' => 'marketplace', // Nome do banco que importamos
    'user' => 'root',        // Usuário padrão do XAMPP
    'pass' => '',            // Senha padrão do XAMPP (vazia)
    'charset' => 'utf8mb4',
  ],
  
  'admin' => [
  'token' => '$2y$10$DSZZFuqrroQRrokNDFWES.W5DljmE2drSM8IdiDhqENVdgrBrttMu',
],

  'paths' => [
    'storage_root' => __DIR__ . '/../storage',
    'ffmpeg' => '/usr/bin/ffmpeg',
  ],

  // Segurança básica
  'security' => [
    'allowed_video_ext' => ['mp4','mov','m4v','webm'],
    'max_upload_mb' => 250,
  ],
];