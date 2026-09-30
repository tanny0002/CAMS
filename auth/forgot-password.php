<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();

$message = null;
$error = null;
$demoToken = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    $stmt = db()->prepare('SELECT id, email FROM users WHERE email = ? AND is_active = 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    // Always show generic message (don't leak account existence)
    $message = 'If that email exists, a reset link has been prepared. On this local demo, the token is shown below.';

    if ($user) {
        $token = bin2hex(random_bytes(32));
        $hash = hash('sha256', $token);
        $expires = date('Y-m-d H:i:s', time() + RESET_TOKEN_HOURS * 3600);
        db()->prepare('INSERT INTO password_resets (user_id, token_hash, expires_at) VALUES (?, ?, ?)')
            ->execute([(int) $user['id'], $hash, $expires]);
        $demoToken = $token;
        log_activity((int) $user['id'], 'password_reset_request', 'Reset token created');
    }
}

$pageTitle = 'Forgot password';
require __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrap panel">
    <h1>Forgot password</h1>
    <p class="muted">Enter your account email to create a reset token (local demo).</p>
    <?php if ($message): ?><div class="alert alert-info"><?= e($message) ?></div><?php endif; ?>
    <?php if ($demoToken): ?>
        <div class="alert alert-success">
            Demo reset link:<br>
            <a href="<?= e(APP_URL) ?>/auth/reset-password.php?token=<?= e($demoToken) ?>">
                Reset password
            </a>
        </div>
    <?php endif; ?>
    <form method="post" action="">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="email">Email</label>
            <input type="email" id="email" name="email" required>
        </div>
        <button class="btn btn-primary" type="submit">Request reset</button>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
