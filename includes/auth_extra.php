<?php
declare(strict_types=1);

require_once __DIR__ . '/mailer.php';

function ensure_auth_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $pdo = db();

    if (!db_has_column('users', 'email_verified_at')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN email_verified_at TIMESTAMP NULL');
    }
    if (!db_has_column('users', 'oauth_provider')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN oauth_provider VARCHAR(32) NULL');
    }
    if (!db_has_column('users', 'oauth_id')) {
        $pdo->exec('ALTER TABLE users ADD COLUMN oauth_id VARCHAR(191) NULL');
    }

    if (db_is_pgsql()) {
        $pdo->exec('ALTER TABLE users ALTER COLUMN password_hash DROP NOT NULL');
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS email_otps (
                id SERIAL PRIMARY KEY,
                email VARCHAR(160) NOT NULL,
                purpose VARCHAR(20) NOT NULL DEFAULT 'register',
                code_hash VARCHAR(255) NOT NULL,
                payload TEXT NULL,
                expires_at TIMESTAMP NOT NULL,
                used_at TIMESTAMP NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $pdo->exec('CREATE INDEX IF NOT EXISTS idx_otp_email_purpose ON email_otps (email, purpose)');
        try {
            $pdo->exec(
                'CREATE UNIQUE INDEX IF NOT EXISTS uq_users_oauth ON users (oauth_provider, oauth_id)
                 WHERE oauth_provider IS NOT NULL AND oauth_id IS NOT NULL'
            );
        } catch (Throwable $e) {
            // ignore
        }
    } else {
        try {
            $pdo->exec('ALTER TABLE users MODIFY password_hash VARCHAR(255) NULL');
        } catch (Throwable $e) {
            // ignore
        }
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS email_otps (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(160) NOT NULL,
                purpose ENUM('register','login','reset') NOT NULL DEFAULT 'register',
                code_hash VARCHAR(255) NOT NULL,
                payload TEXT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                INDEX idx_otp_email_purpose (email, purpose)
            ) ENGINE=InnoDB"
        );
        try {
            $pdo->exec('CREATE UNIQUE INDEX uq_users_oauth ON users (oauth_provider, oauth_id)');
        } catch (Throwable $e) {
            // ignore
        }
    }

    $done = true;
}

function create_email_otp(string $email, string $purpose, ?array $payload = null): string
{
    ensure_auth_schema();
    $code = (string) random_int(100000, 999999);
    $hash = password_hash($code, PASSWORD_DEFAULT);
    $expires = date('Y-m-d H:i:s', time() + OTP_EXPIRY_MINUTES * 60);
    db()->prepare('UPDATE email_otps SET used_at = NOW() WHERE email = ? AND purpose = ? AND used_at IS NULL')
        ->execute([$email, $purpose]);
    db()->prepare(
        'INSERT INTO email_otps (email, purpose, code_hash, payload, expires_at) VALUES (?,?,?,?,?)'
    )->execute([$email, $purpose, $hash, $payload ? json_encode($payload, JSON_THROW_ON_ERROR) : null, $expires]);
    return $code;
}

/**
 * @return array{ok:bool,payload:?array,error?:string}
 */
function verify_email_otp(string $email, string $purpose, string $code): array
{
    ensure_auth_schema();
    $stmt = db()->prepare(
        'SELECT * FROM email_otps WHERE email = ? AND purpose = ? AND used_at IS NULL AND expires_at > NOW()
         ORDER BY id DESC LIMIT 1'
    );
    $stmt->execute([$email, $purpose]);
    $row = $stmt->fetch();
    if (!$row || !password_verify($code, $row['code_hash'])) {
        return ['ok' => false, 'payload' => null, 'error' => 'Invalid or expired code.'];
    }
    db()->prepare('UPDATE email_otps SET used_at = NOW() WHERE id = ?')->execute([(int) $row['id']]);
    $payload = null;
    if (!empty($row['payload'])) {
        $payload = json_decode((string) $row['payload'], true);
    }
    return ['ok' => true, 'payload' => is_array($payload) ? $payload : null];
}

function send_otp_email(string $email, string $name, string $code, string $purposeLabel): bool
{
    return send_mail(
        $email,
        'Your CAMS verification code',
        email_otp_html($name, $code, $purposeLabel),
        "Your CAMS code is {$code}. It expires in " . OTP_EXPIRY_MINUTES . " minutes."
    );
}

function mail_is_configured(): bool
{
    return MAIL_PASSWORD !== '' && MAIL_USERNAME !== '';
}

/**
 * Simple sliding-window rate limit (file-based). Returns remaining wait seconds, or 0 if allowed.
 */
function rate_limit_hit(string $bucket, int $maxAttempts, int $windowSeconds): int
{
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'rate_limits';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    $key = hash('sha256', $bucket);
    $path = $dir . DIRECTORY_SEPARATOR . $key . '.json';
    $now = time();
    $events = [];
    if (is_readable($path)) {
        $raw = file_get_contents($path);
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        if (is_array($decoded)) {
            $events = array_values(array_filter(
                array_map('intval', $decoded),
                static fn(int $ts): bool => ($now - $ts) < $windowSeconds
            ));
        }
    }
    if (count($events) >= $maxAttempts) {
        $oldest = min($events);
        return max(1, $windowSeconds - ($now - $oldest));
    }
    $events[] = $now;
    @file_put_contents($path, json_encode($events), LOCK_EX);
    return 0;
}
