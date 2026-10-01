<?php
declare(strict_types=1);

require_once __DIR__ . '/env.php';

define('APP_NAME', 'CAMS');
define('APP_FULL_NAME', 'Cybersecurity Awareness Management System');

if (!defined('APP_URL')) {
    $override = env('CAMS_APP_URL', '') ?: '';
    if ($override !== '') {
        define('APP_URL', rtrim($override, '/'));
    } else {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $base = preg_replace('#/(auth|user|admin|api)$#', '', $script) ?: '';
        if ($base === '/' || $base === '\\') {
            $base = '';
        }
        define('APP_URL', $scheme . '://' . $host . $base);
    }
}

define('DB_DRIVER', strtolower(env('DB_DRIVER', 'mysql') ?? 'mysql'));
define('DB_HOST', env('DB_HOST', '127.0.0.1') ?? '127.0.0.1');
define('DB_PORT', (int) (env('DB_PORT', DB_DRIVER === 'pgsql' ? '5432' : '3306') ?? (DB_DRIVER === 'pgsql' ? '5432' : '3306')));
define('DB_NAME', env('DB_NAME', 'cams_db') ?? 'cams_db');
define('DB_USER', env('DB_USER', 'root') ?? 'root');
define('DB_PASS', env('DB_PASS', '') ?? '');
define('DB_SSLMODE', env('DB_SSLMODE', DB_DRIVER === 'pgsql' ? 'require' : '') ?? '');
define('DB_CHARSET', 'utf8mb4');
define('SUPABASE_ACCESS_TOKEN', env('SUPABASE_ACCESS_TOKEN', '') ?? '');
define('SUPABASE_PROJECT_REF', env('SUPABASE_PROJECT_REF', '') ?? '');

define('SESSION_NAME', 'cams_session');
define('SESSION_TIMEOUT_MINUTES', (int) (env('SESSION_TIMEOUT_MINUTES', '30') ?? '30'));
define('CSRF_TOKEN_KEY', '_csrf_token');

define('QUESTION_BANK_SIZE', 1001);
define('QUIZ_DRAW_PRACTICE', 20);
define('QUIZ_DRAW_MODULE', 25);
define('QUIZ_DRAW_EXAM', 40);

define('RESET_TOKEN_HOURS', 1);
define('OTP_EXPIRY_MINUTES', 15);

define('MAIL_HOST', env('MAIL_HOST', 'smtp.gmail.com') ?? 'smtp.gmail.com');
define('MAIL_PORT', (int) (env('MAIL_PORT', '587') ?? '587'));
define('MAIL_USERNAME', env('MAIL_USERNAME', env('MAIL_FROM', 'chitaurotanaka00@gmail.com')) ?? 'chitaurotanaka00@gmail.com');
define('MAIL_PASSWORD', preg_replace('/\s+/', '', env('MAIL_PASSWORD', '') ?? '') ?? '');
define('MAIL_FROM', env('MAIL_FROM', MAIL_USERNAME) ?? MAIL_USERNAME);
define('MAIL_FROM_NAME', env('MAIL_FROM_NAME', 'CAMS') ?? 'CAMS');
define('MAIL_ENCRYPTION', env('MAIL_ENCRYPTION', 'tls') ?? 'tls');

define('GOOGLE_CLIENT_ID', trim(env('GOOGLE_CLIENT_ID', '') ?? ''));
define('GOOGLE_CLIENT_SECRET', trim(env('GOOGLE_CLIENT_SECRET', '') ?? ''));
define('GITHUB_CLIENT_ID', trim(env('GITHUB_CLIENT_ID', '') ?? ''));
define('GITHUB_CLIENT_SECRET', trim(env('GITHUB_CLIENT_SECRET', '') ?? ''));

define('OAUTH_GOOGLE_ENABLED', GOOGLE_CLIENT_ID !== '' && GOOGLE_CLIENT_SECRET !== '');
define('OAUTH_GITHUB_ENABLED', GITHUB_CLIENT_ID !== '' && GITHUB_CLIENT_SECRET !== '');

define('BOOTSTRAP_ADMIN_EMAIL', strtolower(trim(env('BOOTSTRAP_ADMIN_EMAIL', 'chitaurotanaka00@gmail.com') ?? 'chitaurotanaka00@gmail.com')));
define('BOOTSTRAP_ADMIN_PASSWORD', env('BOOTSTRAP_ADMIN_PASSWORD', 'Admin@CAMS1') ?? 'Admin@CAMS1');

date_default_timezone_set(env('APP_TIMEZONE', 'Africa/Harare') ?? 'Africa/Harare');

define('APP_ENV', env('APP_ENV', 'local') ?? 'local');
$isProd = APP_ENV === 'production';
ini_set('display_errors', $isProd ? '0' : '1');
error_reporting(E_ALL);
