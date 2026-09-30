<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();

if (is_logged_in()) {
    redirect(role_home(current_user()['role']));
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');

    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !(int) $user['is_active'] || !password_verify($password, $user['password_hash'])) {
        $error = 'Invalid email or password.';
        log_activity($user['id'] ?? null, 'login_failed', $email);
    } else {
        login_user($user);
        flash('success', 'Welcome back, ' . $user['full_name'] . '!');
        redirect(role_home($user['role']));
    }
}

$pageTitle = 'Login';
$bodyClass = 'page-auth';
$authLayout = true;
require __DIR__ . '/../includes/header.php';

$panelImg = e(APP_URL) . '/assets/images/auth-login-panel.png';
?>

<section class="auth-split" aria-label="Log in">
    <aside class="auth-split-visual">
        <img src="<?= $panelImg ?>" alt="African professional representing CAMS cybersecurity awareness learners" width="900" height="1600">
        <div class="auth-split-overlay">
            <div class="auth-split-copy">
                <div class="auth-brand-row">
                    <img class="auth-cams-mark" src="<?= e(APP_URL) ?>/assets/images/cams-shield-logo.png" alt="" width="52" height="56">
                    <p class="auth-split-brand">CAMS</p>
                </div>
                <p class="auth-split-quote">Safer online habits start with awareness.</p>
                <ul class="auth-split-list">
                    <li>Continue lessons and quizzes</li>
                    <li>Track progress and certificates</li>
                    <li>Read tips and submit feedback</li>
                </ul>
            </div>
        </div>
    </aside>

    <div class="auth-split-panel">
        <div class="auth-split-card">
            <p class="auth-kicker">Welcome back</p>
            <h1>Log in to CAMS</h1>
            <p class="auth-sub">Access your cybersecurity awareness lessons, quizzes, and progress dashboard.</p>

            <?php foreach (get_flashes() as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endforeach; ?>
            <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

            <form method="post" action="" class="auth-form">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required autocomplete="email" placeholder="you@example.com" value="<?= e($_POST['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required autocomplete="current-password" placeholder="Enter your password">
                </div>
                <div class="auth-form-meta">
                    <a href="<?= e(APP_URL) ?>/auth/forgot-password.php">Forgot password?</a>
                </div>
                <button class="btn btn-primary btn-block" type="submit">Log in</button>
            </form>

            <div class="auth-help-box">
                <p class="auth-help-title">Demo accounts</p>
                <ul>
                    <li><strong>Admin</strong> — admin@cams.local / Admin@123</li>
                    <li><strong>Lecturer</strong> — lecturer@cams.local / Lecturer@123</li>
                    <li><strong>Student</strong> — student@cams.local / Student@123</li>
                </ul>
            </div>

            <p class="auth-footer-note">
                No account?
                <a href="<?= e(APP_URL) ?>/auth/register.php">Register</a>
                ·
                <a href="<?= e(APP_URL) ?>/index.php">Home</a>
            </p>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
