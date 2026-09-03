<?php
// /public_html/lib/saas_bootstrap.php
declare(strict_types=1);

date_default_timezone_set('America/Sao_Paulo');

/**
 * Carrega /public_html/config/marketplace.php mesmo se o script estiver em /cron
 */
function bb_load_marketplace_cfg(): array {
  $try = [
    __DIR__ . '/../config/marketplace.php',   // /public_html/lib -> /public_html/config
    __DIR__ . '/../../config/marketplace.php' // fallback
  ];

  foreach ($try as $p) {
    if (is_file($p)) {
      $cfg = require $p;
      if (is_array($cfg)) return $cfg;
    }
  }

  http_response_code(500);
  exit("Config marketplace.php não encontrada.\n");
}

function bb_pdo(string $host, string $db, string $user, string $pass): PDO {
  return new PDO(
    "mysql:host={$host};dbname={$db};charset=utf8mb4",
    $user,
    $pass,
    [
      PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
      PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
      PDO::ATTR_EMULATE_PREPARES => false,
    ]
  );
}

/**
 * Lê token/instancia/api e access_token_mp do SEU template (default_db),
 * usando a mesma estrutura que você já tem na tabela config.
 */
function bb_get_owner_config(PDO $pdoDefaultDb): array {
  try {
    $row = $pdoDefaultDb->query("SELECT * FROM config LIMIT 1")->fetch();
    return is_array($row) ? $row : [];
  } catch (Throwable $e) {
    return [];
  }
}

function bb_clean_phone(string $phone): string {
  $p = preg_replace('/\D+/', '', $phone);
  // se usuário já salva com DDI, mantém; se não, coloca 55
  if (strlen($p) >= 12) return $p;
  if ($p !== '' && strpos($p, '55') !== 0) return '55' . $p;
  return $p;
}

/**
 * Envia WhatsApp usando o mesmo padrão do seu api-texto.php (Menuia / Outros).
 */
function bb_send_whatsapp(array $ownerCfg, string $toPhone, string $message): void {
  $api = (string)($ownerCfg['api'] ?? 'menuia');
  $token = (string)($ownerCfg['token'] ?? '');
  $instancia = (string)($ownerCfg['instancia'] ?? '');
  $toPhone = bb_clean_phone($toPhone);

  if ($toPhone === '' || $token === '' || $instancia === '') return;

  // normaliza quebra de linha
  $message = str_replace("%0A", "\n", $message);

  if ($api === 'menuia') {
    $curl = curl_init();
    curl_setopt_array($curl, [
      CURLOPT_URL => 'https://chatbot.menuia.com/api/create-message',
      CURLOPT_RETURNTRANSFER => true,
      CURLOPT_CUSTOMREQUEST => 'POST',
      CURLOPT_POSTFIELDS => [
        'appkey'   => $token,
        'authkey'  => $instancia,
        'to'       => $toPhone,
        'licence'  => 'hugocursos',
        'message'  => $message,
      ],
    ]);
    curl_exec($curl);
    curl_close($curl);
    return;
  }

  // "outros" (enviame)
  $curl = curl_init();
  curl_setopt_array($curl, [
    CURLOPT_URL => 'https://api.enviame.com.br/whatsapp/enviar/texto',
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_POSTFIELDS => json_encode([
      "instancia" => $instancia,
      "token"     => $token,
      "para"      => $toPhone,
      "mensagem"  => $message,
    ], JSON_UNESCAPED_UNICODE),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
  ]);
  curl_exec($curl);
  curl_close($curl);
}

/**
 * Cria preferência no Mercado Pago (Checkout Pro) -> retorna init_point
 * Endpoint usado: /checkout/preferences :contentReference[oaicite:1]{index=1}
 */
function bb_mp_create_preference(string $accessToken, array $payload): array {
  $ch = curl_init('https://api.mercadopago.com/checkout/preferences');
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => 'POST',
    CURLOPT_HTTPHEADER => [
      'Authorization: Bearer ' . $accessToken,
      'Content-Type: application/json',
    ],
    CURLOPT_POSTFIELDS => json_encode($payload, JSON_UNESCAPED_UNICODE),
  ]);

  $resp = curl_exec($ch);
  $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  $data = json_decode((string)$resp, true);
  if (!is_array($data)) $data = [];

  if ($http < 200 || $http >= 300) {
    throw new RuntimeException("Mercado Pago erro HTTP {$http}: " . (string)$resp);
  }

  return $data;
}

/**
 * Consulta pagamento (webhook) – normalmente MP notifica com topic/type=payment + id :contentReference[oaicite:2]{index=2}
 */
function bb_mp_get_payment(string $accessToken, string $paymentId): array {
  $ch = curl_init('https://api.mercadopago.com/v1/payments/' . rawurlencode($paymentId));
  curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_HTTPHEADER => [
      'Authorization: Bearer ' . $accessToken,
    ],
  ]);

  $resp = curl_exec($ch);
  $http = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
  curl_close($ch);

  $data = json_decode((string)$resp, true);
  if (!is_array($data)) $data = [];

  if ($http < 200 || $http >= 300) {
    throw new RuntimeException("MP GET payment erro HTTP {$http}: " . (string)$resp);
  }

  return $data;
}
