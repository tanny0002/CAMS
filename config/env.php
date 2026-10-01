<?php
/**
 * Load KEY=VALUE pairs from .env files into putenv/$_ENV (does not override existing env).
 */
declare(strict_types=1);

function cams_load_env(?string $path = null): void
{
    static $loaded = false;
    if ($loaded) {
        return;
    }
    $loaded = true;

    $root = dirname(__DIR__);
    $candidates = [
        $path,
        $root . DIRECTORY_SEPARATOR . '.env',
        $root . DIRECTORY_SEPARATOR . 'user' . DIRECTORY_SEPARATOR . '.env',
    ];

    foreach ($candidates as $file) {
        if (!$file || !is_readable($file)) {
            continue;
        }
        $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            continue;
        }
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (!str_contains($line, '=')) {
                continue;
            }
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $value = trim($value, "\"'");
            if ($key === '') {
                continue;
            }
            // Support legacy "email_app_password = xxx"
            $map = [
                'email_app_password' => 'MAIL_PASSWORD',
                'email_password' => 'MAIL_PASSWORD',
                'email' => 'MAIL_USERNAME',
                'mail_user' => 'MAIL_USERNAME',
                'superbase access token' => 'SUPABASE_ACCESS_TOKEN',
                'supabase access token' => 'SUPABASE_ACCESS_TOKEN',
                'supabase_access_token' => 'SUPABASE_ACCESS_TOKEN',
            ];
            $key = $map[strtolower($key)] ?? $key;

            if (getenv($key) === false) {
                putenv($key . '=' . $value);
                $_ENV[$key] = $value;
            }
        }
    }
}

function env(string $key, ?string $default = null): ?string
{
    $v = $_ENV[$key] ?? getenv($key);
    if ($v === false || $v === null || $v === '') {
        return $default;
    }
    return (string) $v;
}

cams_load_env();
