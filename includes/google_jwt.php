<?php
declare(strict_types=1);

/**
 * Verify Google Identity Services ID tokens (JWT) locally.
 * Uses cached JWKS so sign-in works when the server cannot reach Google APIs.
 */

function google_jwks_path(): string
{
    return dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'google_jwks.json';
}

/**
 * @return array{keys: list<array<string,mixed>>}
 */
function google_load_jwks(): array
{
    $path = google_jwks_path();
    // Try refresh when network allows (optional).
    $age = is_file($path) ? (time() - (int) filemtime($path)) : PHP_INT_MAX;
    if ($age > 86400) {
        google_refresh_jwks();
    }
    $raw = is_readable($path) ? file_get_contents($path) : false;
    if ($raw === false) {
        throw new RuntimeException('Google JWKS cache missing.');
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['keys']) || !is_array($data['keys'])) {
        throw new RuntimeException('Google JWKS cache invalid.');
    }
    return $data;
}

function google_refresh_jwks(): void
{
    $ch = curl_init('https://www.googleapis.com/oauth2/v3/certs');
    if ($ch === false) {
        return;
    }
    $ca = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cacert.pem';
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 8,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
    ];
    if (is_readable($ca)) {
        $opts[CURLOPT_CAINFO] = $ca;
    }
    curl_setopt_array($ch, $opts);
    $raw = curl_exec($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false || $code >= 400) {
        return;
    }
    $data = json_decode($raw, true);
    if (!is_array($data) || empty($data['keys'])) {
        return;
    }
    @file_put_contents(google_jwks_path(), json_encode($data, JSON_PRETTY_PRINT));
}

function google_b64url_decode(string $data): string
{
    $remainder = strlen($data) % 4;
    if ($remainder) {
        $data .= str_repeat('=', 4 - $remainder);
    }
    $decoded = base64_decode(strtr($data, '-_', '+/'), true);
    if ($decoded === false) {
        throw new RuntimeException('Invalid base64url.');
    }
    return $decoded;
}

/**
 * Convert JWK RSA n/e to PEM public key.
 */
function google_jwk_to_pem(string $nB64, string $eB64): string
{
    $n = google_b64url_decode($nB64);
    $e = google_b64url_decode($eB64);

    $encodeLen = static function (int $len): string {
        if ($len < 0x80) {
            return chr($len);
        }
        $out = '';
        while ($len > 0) {
            $out = chr($len & 0xff) . $out;
            $len >>= 8;
        }
        return chr(0x80 | strlen($out)) . $out;
    };
    $encodeInt = static function (string $bytes) use ($encodeLen): string {
        if ($bytes === '' || (ord($bytes[0]) & 0x80)) {
            $bytes = "\x00" . $bytes;
        }
        return "\x02" . $encodeLen(strlen($bytes)) . $bytes;
    };

    $modulus = $encodeInt($n);
    $exponent = $encodeInt($e);
    $sequence = $modulus . $exponent;
    $bitString = "\x00" . "\x30" . $encodeLen(strlen($sequence)) . $sequence;

    // rsaEncryption OID 1.2.840.113549.1.1.1
    $algId = hex2bin('300d06092a864886f70d0101010500');
    if ($algId === false) {
        throw new RuntimeException('OID encode failed');
    }
    $publicKey = $algId . "\x03" . $encodeLen(strlen($bitString)) . $bitString;
    $der = "\x30" . $encodeLen(strlen($publicKey)) . $publicKey;

    return "-----BEGIN PUBLIC KEY-----\n"
        . chunk_split(base64_encode($der), 64, "\n")
        . "-----END PUBLIC KEY-----";
}

/**
 * @return array{email:string,name:string,sub:string}
 */
function google_verify_id_token(string $jwt): array
{
    $parts = explode('.', $jwt);
    if (count($parts) !== 3) {
        throw new RuntimeException('Malformed Google credential.');
    }
    [$h64, $p64, $s64] = $parts;
    $header = json_decode(google_b64url_decode($h64), true);
    $payload = json_decode(google_b64url_decode($p64), true);
    if (!is_array($header) || !is_array($payload)) {
        throw new RuntimeException('Invalid Google credential JSON.');
    }
    $kid = (string) ($header['kid'] ?? '');
    $alg = (string) ($header['alg'] ?? '');
    if ($alg !== 'RS256' || $kid === '') {
        throw new RuntimeException('Unsupported Google credential algorithm.');
    }

    $jwks = google_load_jwks();
    $jwk = null;
    foreach ($jwks['keys'] as $key) {
        if (($key['kid'] ?? '') === $kid) {
            $jwk = $key;
            break;
        }
    }
    if ($jwk === null) {
        // Force refresh once if kid unknown
        google_refresh_jwks();
        $jwks = google_load_jwks();
        foreach ($jwks['keys'] as $key) {
            if (($key['kid'] ?? '') === $kid) {
                $jwk = $key;
                break;
            }
        }
    }
    if ($jwk === null) {
        throw new RuntimeException('Google signing key not found. Update storage/google_jwks.json.');
    }

    $pem = google_jwk_to_pem((string) $jwk['n'], (string) $jwk['e']);
    $signature = google_b64url_decode($s64);
    $ok = openssl_verify($h64 . '.' . $p64, $signature, $pem, OPENSSL_ALGO_SHA256);
    if ($ok !== 1) {
        throw new RuntimeException('Google credential signature invalid.');
    }

    $aud = (string) ($payload['aud'] ?? '');
    $iss = (string) ($payload['iss'] ?? '');
    $exp = (int) ($payload['exp'] ?? 0);
    $email = strtolower(trim((string) ($payload['email'] ?? '')));
    $verified = !empty($payload['email_verified']);
    $sub = (string) ($payload['sub'] ?? '');
    $name = trim((string) ($payload['name'] ?? $payload['given_name'] ?? 'CAMS User')) ?: 'CAMS User';

    if ($aud !== GOOGLE_CLIENT_ID) {
        throw new RuntimeException('Google credential audience mismatch.');
    }
    if (!in_array($iss, ['https://accounts.google.com', 'accounts.google.com'], true)) {
        throw new RuntimeException('Google credential issuer invalid.');
    }
    if ($exp < time()) {
        throw new RuntimeException('Google credential expired.');
    }
    if ($email === '' || !$verified || $sub === '') {
        throw new RuntimeException('Google account email is not verified.');
    }

    return ['email' => $email, 'name' => $name, 'sub' => $sub];
}
