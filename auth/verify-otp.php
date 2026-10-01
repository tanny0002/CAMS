<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();
ensure_auth_schema();

if (is_logged_in()) {
    redirect(role_home(current_user()['role']));
}

$email = (string) ($_SESSION['pending_otp_email'] ?? '');
$purpose = (string) ($_SESSION['pending_otp_purpose'] ?? 'register');
$error = null;

if ($email === '' || $purpose !== 'register') {
    flash('error', 'Start registration first so we can send you a code.');
    redirect('auth/register.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? 'verify');

    if ($action === 'resend') {
        $stmt = db()->prepare(
            'SELECT payload FROM email_otps WHERE email = ? AND purpose = ? ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute([$email, 'register']);
        $row = $stmt->fetch();
        $payload = $row && !empty($row['payload']) ? json_decode((string) $row['payload'], true) : null;
        if (!is_array($payload) || empty($payload['password_hash'])) {
            $error = 'Registration session expired. Please register again.';
            unset($_SESSION['pending_otp_email'], $_SESSION['pending_otp_purpose']);
        } else {
            $code = create_email_otp($email, 'register', $payload);
            $name = (string) ($payload['full_name'] ?? 'there');
            if (!send_otp_email($email, $name, $code, 'verify your CAMS registration')) {
                $error = 'Could not resend the email. Try again shortly.';
            } else {
                flash('success', 'A new code was sent to ' . $email . '.');
                redirect('auth/verify-otp.php');
            }
        }
    } else {
        $code = preg_replace('/\D+/', '', (string) ($_POST['otp'] ?? '')) ?? '';
        if (strlen($code) !== 6) {
            $error = 'Enter the 6-digit code from your email.';
        } else {
            $result = verify_email_otp($email, 'register', $code);
            if (!$result['ok'] || empty($result['payload'])) {
                $error = $result['error'] ?? 'Invalid or expired code.';
            } else {
                $p = $result['payload'];
                $name = trim((string) ($p['full_name'] ?? ''));
                $hash = (string) ($p['password_hash'] ?? '');
                $mail = strtolower(trim((string) ($p['email'] ?? $email)));
                if ($name === '' || $hash === '' || $mail === '') {
                    $error = 'Registration data missing. Please register again.';
                } else {
                    $exists = db()->prepare('SELECT id FROM users WHERE email = ?');
                    $exists->execute([$mail]);
                    if ($exists->fetch()) {
                        $error = 'That email is already registered. Please log in.';
                        unset($_SESSION['pending_otp_email'], $_SESSION['pending_otp_purpose']);
                    } else {
                        $role = bootstrap_role_for_email($mail);
                        $isAdmin = $role === 'admin';
                        ensure_access_schema();
                        db()->prepare(
                            "INSERT INTO users (full_name, email, password_hash, role, account_type, consent_accepted, email_verified_at,
                              profile_complete, must_change_password, approval_status)
                             VALUES (?, ?, ?, ?, ?, 1, NOW(), 0, ?, ?)"
                        )->execute([
                            $name,
                            $mail,
                            $hash,
                            $role,
                            $isAdmin ? 'admin' : null,
                            $isAdmin ? 1 : 0,
                            $isAdmin ? 'approved' : 'pending',
                        ]);
                        $id = (int) db()->lastInsertId();
                        if ($id <= 0 && db_is_pgsql()) {
                            $id = (int) db()->lastInsertId('users_id_seq');
                        }
                        log_activity($id, 'register', 'Email verified and account created');
                        send_mail($mail, 'Welcome to CAMS', email_welcome_html($name), 'Welcome to CAMS — your account is ready.');
                        unset($_SESSION['pending_otp_email'], $_SESSION['pending_otp_purpose']);
                        $stmt = db()->prepare('SELECT * FROM users WHERE id = ?');
                        $stmt->execute([$id]);
                        $user = $stmt->fetch();
                        if ($user) {
                            login_user($user);
                            flash('success', 'Email verified. Complete your profile to continue.');
                            post_login_redirect();
                        }
                        flash('success', 'Account created. Please log in.');
                        redirect('auth/login.php');
                    }
                }
            }
        }
    }
}

$pageTitle = 'Verify email';
$bodyClass = 'page-auth';
$authLayout = true;
require __DIR__ . '/../includes/header.php';

$panelImg = e(APP_URL) . '/assets/images/auth-login-panel.png';
?>

<section class="auth-split" aria-label="Verify email">
    <aside class="auth-split-visual">
        <img src="<?= $panelImg ?>" alt="" width="900" height="1600">
        <div class="auth-split-overlay">
            <div class="auth-split-copy">
                <div class="auth-brand-row">
                    <img class="auth-cams-mark" src="<?= e(APP_URL) ?>/assets/images/cams-shield-logo.png" alt="" width="52" height="56">
                    <p class="auth-split-brand">CAMS</p>
                </div>
                <p class="auth-split-quote">One more step — confirm it is really you.</p>
            </div>
        </div>
    </aside>

    <div class="auth-split-panel">
        <div class="auth-split-card">
            <p class="auth-kicker">Email verification</p>
            <h1>Enter your code</h1>
            <p class="auth-sub">We sent a 6-digit code to <strong><?= e($email) ?></strong>. It expires in <?= (int) OTP_EXPIRY_MINUTES ?> minutes.</p>

            <?php foreach (get_flashes() as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endforeach; ?>
            <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

            <form method="post" action="" class="auth-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="verify">
                <div class="form-group">
                    <label for="otp">Verification code</label>
                    <input type="text" id="otp" name="otp" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code" placeholder="••••••" class="otp-input">
                </div>
                <button class="btn btn-primary btn-block" type="submit">Verify &amp; continue</button>
            </form>

            <form method="post" action="" class="auth-resend-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="resend">
                <button class="btn btn-ghost btn-block" type="submit">Resend code</button>
            </form>

            <p class="auth-footer-note">
                Wrong email?
                <a href="<?= e(APP_URL) ?>/auth/register.php">Register again</a>
            </p>
        </div>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
