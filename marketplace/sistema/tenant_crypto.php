<?php
// /public_html/sistema/tenant_crypto.php
declare(strict_types=1);

if (!function_exists('bb_kdf_key')) {
  function bb_kdf_key($key): string {
    // mant���m compatibilidade: transforma qualquer string em 32 bytes (sha256 raw)
    return hash('sha256', (string)$key, true);
  }
}

if (!function_exists('bb_encrypt_str')) {
  function bb_encrypt_str($plain, $key): string {
    $plain = (string)$plain;
    $key   = (string)$key;

    // Se n���o tem key, n���o criptografa (evita gerar enc inv���lido)
    if ($key === '') return $plain;

    $keyb = bb_kdf_key($key);

    // IV correto pro cipher
    $ivLen = openssl_cipher_iv_length('aes-256-cbc');
    if ($ivLen === false || $ivLen < 16) $ivLen = 16;

    try {
      $iv = random_bytes($ivLen);
    } catch (Throwable $e) {
      return '';
    }

    $cipher = openssl_encrypt($plain, 'aes-256-cbc', $keyb, OPENSSL_RAW_DATA, $iv);
    if ($cipher === false) return '';

    return 'enc:' . base64_encode($iv . $cipher);
  }
}

if (!function_exists('bb_decrypt_str')) {
  function bb_decrypt_str($val, $key): string {
    $val = (string)$val;
    $key = (string)$key;

    if ($val === '') return '';

    // compatibilidade: se n���o tiver prefixo enc:, retorna como est���
    if (strpos($val, 'enc:') !== 0) return $val; // n���o criptografado

    // se chegou aqui, ��� enc:... e precisa de key
    if ($key === '') return '';

    $raw = base64_decode(substr($val, 4), true);
    if ($raw === false) return '';

    $ivLen = openssl_cipher_iv_length('aes-256-cbc');
    if ($ivLen === false || $ivLen < 16) $ivLen = 16;

    if (strlen($raw) <= $ivLen) return '';

    $iv     = substr($raw, 0, $ivLen);
    $cipher = substr($raw, $ivLen);

    $keyb  = bb_kdf_key($key);
    $plain = openssl_decrypt($cipher, 'aes-256-cbc', $keyb, OPENSSL_RAW_DATA, $iv);

    return ($plain === false) ? '' : (string)$plain;
  }
}
