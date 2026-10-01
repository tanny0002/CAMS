<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();
ensure_auth_schema();

if (is_logged_in()) {
    post_login_redirect();
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    $wait = rate_limit_hit('login:' . $ip . ':' . $email, 8, 900);
    if ($wait > 0) {
        $error = 'Too many login attempts. Try again in about ' . (int) ceil($wait / 60) . ' minute(s).';
    } else {
        $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();

        $hash = $user['password_hash'] ?? null;
        $ok = $user
            && (int) $user['is_active']
            && is_string($hash)
            && $hash !== ''
            && password_verify($password, $hash);

        if (!$ok) {
            $error = 'Invalid email or password.';
            if ($user && (empty($hash) || $hash === null)) {
                $error = 'This account uses Google or GitHub sign-in. Use the social buttons below.';
            }
            log_activity($user['id'] ?? null, 'login_failed', $email);
        } else {
            login_user($user);
            flash('success', 'Welcome back, ' . $user['full_name'] . '!');
            post_login_redirect();
        }
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

            <?= auth_oauth_buttons_html('login') ?>

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
