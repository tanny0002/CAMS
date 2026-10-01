<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();
ensure_access_schema();

if (is_logged_in()) {
    post_login_redirect();
}

$inviteToken = (string) ($_GET['invite'] ?? $_SESSION['invite_token'] ?? '');
$invite = $inviteToken !== '' ? find_valid_invitation($inviteToken) : null;
if ($invite) {
    $_SESSION['invite_token'] = $inviteToken;
}

$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $name = trim((string) ($_POST['full_name'] ?? ''));
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $password = (string) ($_POST['password'] ?? '');
    $confirm = (string) ($_POST['password_confirm'] ?? '');
    $consent = isset($_POST['consent']);
    $inviteToken = (string) ($_POST['invite_token'] ?? $inviteToken);
    $invite = $inviteToken !== '' ? find_valid_invitation($inviteToken) : null;

    if ($invite) {
        $email = strtolower((string) $invite['email']);
    }

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
    } elseif (!mail_is_configured()) {
        $error = 'Email delivery is not configured. Contact the administrator.';
    } else {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        $wait = rate_limit_hit('register-otp:' . $ip . ':' . $email, 5, 900);
        if ($wait > 0) {
            $error = 'Too many verification emails requested. Try again in about ' . (int) ceil($wait / 60) . ' minute(s).';
        } else {
        $check = db()->prepare('SELECT id FROM users WHERE email = ?');
        $check->execute([$email]);
        if ($check->fetch()) {
            $error = 'An account with that email already exists. Try logging in or use Google/GitHub.';
        } else {
            $payload = [
                'full_name' => $name,
                'email' => $email,
                'password_hash' => password_hash($password, PASSWORD_DEFAULT),
                'consent' => 1,
            ];
            $code = create_email_otp($email, 'register', $payload);
            if (!send_otp_email($email, $name, $code, 'verify your CAMS registration')) {
                $error = 'We could not send the verification email. Please try again in a moment.';
            } else {
                $_SESSION['pending_otp_email'] = $email;
                $_SESSION['pending_otp_purpose'] = 'register';
                flash('success', 'We emailed a 6-digit code to ' . $email . '. Enter it to finish signing up.');
                redirect('auth/verify-otp.php');
            }
        }
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
            <p class="auth-sub">Register with email (OTP verification) or continue with Google / GitHub<?= $invite ? '. Invitation email is locked below.' : '' ?>.</p>

            <?php foreach (get_flashes() as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endforeach; ?>
            <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

            <?= auth_oauth_buttons_html('register') ?>

            <form method="post" action="" class="auth-form">
                <?= csrf_field() ?>
                <input type="hidden" name="invite_token" value="<?= e($inviteToken) ?>">
                <div class="form-group">
                    <label for="full_name">Full name</label>
                    <input type="text" id="full_name" name="full_name" required maxlength="120" value="<?= e($_POST['full_name'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" required
                           value="<?= e($_POST['email'] ?? ($invite['email'] ?? '')) ?>"
                           <?= $invite ? 'readonly' : '' ?>>
                </div>
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required minlength="8" placeholder="Min. 8 characters" autocomplete="new-password">
                </div>
                <div class="form-group">
                    <label for="password_confirm">Confirm password</label>
                    <input type="password" id="password_confirm" name="password_confirm" required minlength="8" autocomplete="new-password">
                </div>
                <div class="form-group checkbox-row">
                    <input type="checkbox" id="consent" name="consent" value="1" <?= isset($_POST['consent']) ? 'checked' : '' ?>>
                    <label for="consent">I agree this is educational use only — no real banking passwords.</label>
                </div>
                <button class="btn btn-primary btn-block" type="submit">Send verification code</button>
            </form>

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
