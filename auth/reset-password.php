<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();

$token = (string) ($_GET['token'] ?? $_POST['token'] ?? '');
$error = null;

if ($token === '') {
    flash('error', 'Missing reset token.');
    redirect('auth/forgot-password.php');
}

$hash = hash('sha256', $token);
$stmt = db()->prepare(
    'SELECT pr.*, u.email FROM password_resets pr
     JOIN users u ON u.id = pr.user_id
     WHERE pr.token_hash = ? AND pr.used_at IS NULL AND pr.expires_at > NOW()
     ORDER BY pr.id DESC LIMIT 1'
);
$stmt->execute([$hash]);
$reset = $stmt->fetch();

if (!$reset) {
    flash('error', 'Invalid or expired reset token.');
    redirect('auth/forgot-password.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');
    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $newHash = password_hash($password, PASSWORD_DEFAULT);
        db()->prepare('UPDATE users SET password_hash = ? WHERE id = ?')->execute([$newHash, (int) $reset['user_id']]);
        db()->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')->execute([(int) $reset['id']]);
        log_activity((int) $reset['user_id'], 'password_reset', 'Password changed via reset token');
        flash('success', 'Password updated. You can log in now.');
        redirect('auth/login.php');
    }
}

$pageTitle = 'Reset password';
require __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrap panel">
    <h1>Reset password</h1>
    <p class="muted">Account: <?= e($reset['email']) ?></p>
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="token" value="<?= e($token) ?>">
        <div class="form-group">
            <label for="password">New password</label>
            <input type="password" id="password" name="password" required minlength="8">
        </div>
        <div class="form-group">
            <label for="password_confirm">Confirm password</label>
            <input type="password" id="password_confirm" name="password_confirm" required minlength="8">
        </div>
        <button class="btn btn-primary" type="submit">Update password</button>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
