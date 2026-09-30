<?php
/**
 * CAMS configuration
 * Adjust DB settings for your XAMPP environment if needed.
 */

declare(strict_types=1);

define('APP_NAME', 'CAMS');
define('APP_FULL_NAME', 'Cybersecurity Awareness Management System');

// Auto-detect base URL (override by setting APP_URL_OVERRIDE below if needed)
if (!defined('APP_URL')) {
    $override = getenv('CAMS_APP_URL') ?: '';
    if ($override !== '') {
        define('APP_URL', rtrim($override, '/'));
    } else {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        $scheme = $https ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        $script = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        // Climb to project root from auth/, user/, admin/, api/
        $base = preg_replace('#/(auth|user|admin|api)$#', '', $script) ?: '';
        if ($base === '/' || $base === '\\') {
            $base = '';
        }
        define('APP_URL', $scheme . '://' . $host . $base);
    }
}

// Database (XAMPP defaults)
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'cams_db');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_CHARSET', 'utf8mb4');

// Session / security
define('SESSION_NAME', 'cams_session');
define('SESSION_TIMEOUT_MINUTES', 30);
define('CSRF_TOKEN_KEY', '_csrf_token');

// Quiz draws from large shuffled banks (not all questions at once)
define('QUESTION_BANK_SIZE', 1001);
define('QUIZ_DRAW_PRACTICE', 20);
define('QUIZ_DRAW_MODULE', 25);
define('QUIZ_DRAW_EXAM', 40);

// Password reset tokens expire after 1 hour
define('RESET_TOKEN_HOURS', 1);

// Timezone
date_default_timezone_set('Africa/Harare');

// Error display: turn off in production
ini_set('display_errors', '1');
error_reporting(E_ALL);
