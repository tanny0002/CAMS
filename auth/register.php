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
    $name = trim((string) ($_POST['full_name'] ?? ''));
    $email = trim((string) ($_POST['email'] ?? ''));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');
    $consent = isset($_POST['consent']);

    if ($name === '' || $email === '' || $password === '') {
        $error = 'Please fill in all required fields.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $error = 'Please enter a valid email address.';
    } elseif (strlen($password) < 8) {
        $error = 'Password must be at least 8 characters.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } elseif (!$consent) {
        $error = 'You must accept the privacy notice to register.';
    } else {
        $check = db()->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'An account with that email already exists.';
        } else {
            $hash = password_hash($password, PASSWORD_DEFAULT);
            $ins = db()->prepare(
                'INSERT INTO users (full_name, email, password_hash, role, consent_accepted) VALUES (?, ?, ?, \'user\', 1)'
            );
            $ins->execute([$name, $email, $hash]);
            $id = (int) db()->lastInsertId();
            log_activity($id, 'register', 'New user registered');
            flash('success', 'Account created. Please log in.');
            redirect('auth/login.php');
        }
    }
}

$pageTitle = 'Register';
$bodyClass = 'page-auth';
$authLayout = true;
require __DIR__ . '/../includes/header.php';

$panelImg = e(APP_URL) . '/assets/images/auth-login-panel.png';
?>

<section class="auth-split" aria-label="Register">
    <aside class="auth-split-visual">
        <img src="<?= $panelImg ?>" alt="African professional representing CAMS cybersecurity awareness learners" width="900" height="1600">
        <div class="auth-split-overlay">
            <div class="auth-split-copy">
                <div class="auth-brand-row">
                    <img class="auth-cams-mark" src="<?= e(APP_URL) ?>/assets/images/cams-shield-logo.png" alt="" width="52" height="56">
                    <p class="auth-split-brand">CAMS</p>
                </div>
                <p class="auth-split-quote">Join learners building safer online habits.</p>
                <ul class="auth-split-list">
                    <li>Phishing, passwords, malware, social engineering</li>
                    <li>Quizzes with feedback and certificates</li>
                    <li>Progress tracking for campus programmes</li>
                </ul>
            </div>
        </div>
    </aside>

    <div class="auth-split-panel">
        <div class="auth-split-card auth-split-card-wide">
            <p class="auth-kicker">Get started</p>
            <h1>Create your account</h1>
            <p class="auth-sub">Register to begin CAMS cybersecurity awareness training as a learner.</p>

            <?php foreach (get_flashes() as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endforeach; ?>
            <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

            <form method="post" action="" class="auth-form">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="full_name">Full name</label>
                    <input type="text" id="full_name" name="full_name" required maxlength="120" value="<?= e($_POST['full_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required value="<?= e($_POST['email'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required minlength="8" placeholder="Min. 8 characters">
                </div>
                <div class="form-group">
                    <label for="password_confirm">Confirm password</label>
                    <input type="password" id="password_confirm" name="password_confirm" required minlength="8">
                </div>
                <div class="form-group checkbox-row">
                    <input type="checkbox" id="consent" name="consent" value="1" <?= isset($_POST['consent']) ? 'checked' : '' ?>>
                    <label for="consent">I agree this is educational use only — no real banking passwords.</label>
                </div>
                <button class="btn btn-primary btn-block" type="submit">Create account</button>
            </form>

            <div class="auth-help-box">
                <p class="auth-help-title">After you register</p>
                <ul>
                    <li>Open modules and complete lessons</li>
                    <li>Take quizzes and track your progress</li>
                    <li>Earn certificates and read security tips</li>
                </ul>
            </div>

            <p class="auth-footer-note">
                Already registered?
                <a href="<?= e(APP_URL) ?>/auth/login.php">Log in</a>
                ·
                <a href="<?= e(APP_URL) ?>/index.php">Home</a>
            </p>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
