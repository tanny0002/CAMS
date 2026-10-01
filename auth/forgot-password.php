<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();
ensure_auth_schema();

$message = null;
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $message = 'If that email exists, we sent a password reset link. Check your inbox (and spam folder).';

    if (!mail_is_configured()) {
        $error = 'Email delivery is not configured. Contact the administrator.';
        $message = null;
    } elseif ($email !== '' && filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $wait = rate_limit_hit('reset:' . $ip . ':' . $email, 5, 900);
        if ($wait > 0) {
            $error = 'Too many reset requests. Try again in about ' . (int) ceil($wait / 60) . ' minute(s).';
            $message = null;
        } else {
        $stmt = db()->prepare('SELECT id, email, full_name, password_hash FROM users WHERE email = ? AND is_active = 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        if ($user) {
            if (empty($user['password_hash'])) {
                // OAuth-only account — still generic message, but log it
                log_activity((int) $user['id'], 'password_reset_request', 'OAuth account — no local password');
            } else {
                $token = bin2hex(random_bytes(32));
                $hash = hash('sha256', $token);
                $expires = date('Y-m-d H:i:s', time() + RESET_TOKEN_HOURS * 3600);
                db()->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)')
                    ->execute([(int) $user['id'], $hash, $expires]);
                $resetUrl = rtrim(APP_URL, '/') . '/auth/reset-password.php?token=' . urlencode($token);
                $sent = send_mail(
                    $email,
                    'Reset your CAMS password',
                    email_reset_html((string) $user['full_name'], $resetUrl),
                    "Reset your CAMS password: {$resetUrl}"
                );
                if (!$sent) {
                    error_log('CAMS: failed to send reset email to ' . $email);
                }
                log_activity((int) $user['id'], 'password_reset_request', 'Reset email sent');
            }
        }
        }
    }
}

$pageTitle = 'Forgot password';
$bodyClass = 'page-auth';
$authLayout = true;
require __DIR__ . '/../includes/header.php';

$panelImg = e(APP_URL) . '/assets/images/auth-login-panel.png';
?>

<section class="auth-split" aria-label="Forgot password">
    <aside class="auth-split-visual">
        <img src="<?= $panelImg ?>" alt="" width="900" height="1600">
        <div class="auth-split-overlay">
            <div class="auth-split-copy">
                <div class="auth-brand-row">
                    <img class="auth-cams-mark" src="<?= e(APP_URL) ?>/assets/images/cams-shield-logo.png" alt="" width="52" height="56">
                    <p class="auth-split-brand">CAMS</p>
                </div>
                <p class="auth-split-quote">We’ll email you a secure reset link.</p>
            </div>
        </div>
    </aside>

    <div class="auth-split-panel">
        <div class="auth-split-card">
            <p class="auth-kicker">Account recovery</p>
            <h1>Forgot password</h1>
            <p class="auth-sub">Enter the email on your account. If it exists, you’ll get a reset link from <?= e(MAIL_FROM) ?>.</p>

            <?php foreach (get_flashes() as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endforeach; ?>
            <?php if ($message): ?><div class="alert alert-info"><?= e($message) ?></div><?php endif; ?>
            <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

            <form method="post" action="" class="auth-form">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required autocomplete="email">
                </div>
                <button class="btn btn-primary btn-block" type="submit">Send reset link</button>
            </form>

            <p class="auth-footer-note">
                <a href="<?= e(APP_URL) ?>/auth/login.php">Back to login</a>
                ·
                Accounts that only use Google/GitHub should sign in with those providers.
            </p>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
