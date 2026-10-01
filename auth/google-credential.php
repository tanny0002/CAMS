<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/google_jwt.php';
start_app_session();
ensure_auth_schema();

if (is_logged_in()) {
    redirect(role_home(current_user()['role']));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('auth/login.php');
}

verify_csrf();

if (!OAUTH_GOOGLE_ENABLED) {
    flash('error', 'Google sign-in is not configured.');
    redirect('auth/login.php');
}

$credential = trim((string) ($_POST['credential'] ?? ''));
if ($credential === '') {
    flash('error', 'Missing Google credential.');
    redirect('auth/login.php');
}

try {
    $profile = google_verify_id_token($credential);
    $user = oauth_find_or_create_user('google', $profile['sub'], $profile['email'], $profile['name']);
    if (!$user || !(int) $user['is_active']) {
        throw new RuntimeException('Could not sign you in with Google.');
    }
    login_user($user);
    flash('success', 'Signed in with Google.');
    post_login_redirect();
} catch (Throwable $e) {
    oauth_log('google credential: ' . $e->getMessage());
    flash('error', 'Could not complete Google sign-in. ' . (APP_ENV !== 'production' ? $e->getMessage() : 'Please try again.'));
    redirect('auth/login.php');
}
