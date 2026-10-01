<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();
ensure_access_schema();
require_login();

refresh_session_user();
$u = current_user();
if (empty($u['must_change_password'])) {
    if (empty($u['profile_complete'])) {
        redirect('auth/complete-profile.php');
    }
    redirect(role_home($u['role']));
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');
    if (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        $hash = password_hash($password, PASSWORD_DEFAULT);
        db()->prepare('UPDATE users SET password_hash = ?, must_change_password = 0 WHERE id = ?')
            ->execute([$hash, (int) $u['id']]);
        log_activity((int) $u['id'], 'password_force_change', 'Password set on first access');
        refresh_session_user();
        flash('success', 'Password updated.');
        if (empty(current_user()['profile_complete'])) {
            redirect('auth/complete-profile.php');
        }
        if ((current_user()['approval_status'] ?? '') !== 'approved' && current_user()['role'] !== 'admin') {
            redirect('auth/pending-approval.php');
        }
        redirect(role_home(current_user()['role']));
    }
}

$pageTitle = 'Set password';
$bodyClass = 'page-auth';
$authLayout = true;
require __DIR__ . '/../includes/header.php';
?>
<section class="auth-split" aria-label="Set password">
    <aside class="auth-split-visual">
        <img src="<?= e(APP_URL) ?>/assets/images/auth-login-panel.png" alt="" width="900" height="1600">
        <div class="auth-split-overlay"><div class="auth-split-copy">
            <p class="auth-split-brand">CAMS</p>
            <p class="auth-split-quote">Secure your account with a new password.</p>
        </div></div>
    </aside>
    <div class="auth-split-panel"><div class="auth-split-card">
        <p class="auth-kicker">First-time access</p>
        <h1>Set a new password</h1>
        <p class="auth-sub">For security, set your password before using CAMS.</p>
        <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
        <form method="post" class="auth-form">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="password">New password</label>
                <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
            </div>
            <div class="form-group">
                <label for="password_confirm">Confirm password</label>
                <input type="password" id="password_confirm" name="password_confirm" required minlength="8" autocomplete="new-password">
            </div>
            <button class="btn btn-primary btn-block" type="submit">Save password</button>
        </form>
    </div></div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
