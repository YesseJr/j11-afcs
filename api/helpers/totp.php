<?php
/**
 * Minimal RFC 6238 TOTP (HMAC-SHA1, 30s step, 6 digits) — no external deps.
 */
function totp_base32_encode(string $data): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $bits = '';
    foreach (str_split($data) as $c) {
        $bits .= str_pad(decbin(ord($c)), 8, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 5) as $chunk) {
        if (strlen($chunk) < 5) $chunk = str_pad($chunk, 5, '0', STR_PAD_RIGHT);
        $out .= $alphabet[bindec($chunk)];
    }
    return $out;
}

function totp_base32_decode(string $b32): string {
    $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
    $b32 = strtoupper(preg_replace('/[^A-Z2-7]/', '', $b32));
    $bits = '';
    for ($i = 0; $i < strlen($b32); $i++) {
        $val = strpos($alphabet, $b32[$i]);
        if ($val === false) continue;
        $bits .= str_pad(decbin($val), 5, '0', STR_PAD_LEFT);
    }
    $out = '';
    foreach (str_split($bits, 8) as $chunk) {
        if (strlen($chunk) === 8) $out .= chr(bindec($chunk));
    }
    return $out;
}

function totp_generate_secret(int $bytes = 20): string {
    return totp_base32_encode(random_bytes($bytes));
}

function totp_code(string $secretBase32, ?int $time = null, int $digits = 6, int $period = 30): string {
    $time = $time ?? time();
    $counter = intdiv($time, $period);
    $secret = totp_base32_decode($secretBase32);
    $binCounter = pack('N*', 0) . pack('N*', $counter);
    $hash = hash_hmac('sha1', $binCounter, $secret, true);
    $offset = ord($hash[19]) & 0x0f;
    $truncated = (
        ((ord($hash[$offset]) & 0x7f) << 24) |
        ((ord($hash[$offset + 1]) & 0xff) << 16) |
        ((ord($hash[$offset + 2]) & 0xff) << 8) |
        (ord($hash[$offset + 3]) & 0xff)
    ) % (10 ** $digits);
    return str_pad((string)$truncated, $digits, '0', STR_PAD_LEFT);
}

function totp_verify(string $secretBase32, string $code, int $window = 1, int $period = 30): bool {
    $code = preg_replace('/\s+/', '', $code);
    if (!preg_match('/^\d{6}$/', $code)) return false;
    $now = time();
    for ($i = -$window; $i <= $window; $i++) {
        if (hash_equals(totp_code($secretBase32, $now + ($i * $period)), $code)) {
            return true;
        }
    }
    return false;
}

function totp_otpauth_uri(string $secret, string $account, string $issuer = 'AFCS'): string {
    $label = rawurlencode($issuer . ':' . $account);
    $q = http_build_query([
        'secret' => $secret,
        'issuer' => $issuer,
        'algorithm' => 'SHA1',
        'digits' => 6,
        'period' => 30,
    ]);
    return "otpauth://totp/{$label}?{$q}";
}
