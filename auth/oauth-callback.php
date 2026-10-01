<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();
ensure_auth_schema();

if (is_logged_in()) {
    redirect(role_home(current_user()['role']));
}

$errorParam = (string) ($_GET['error'] ?? '');
if ($errorParam !== '') {
    flash('error', 'Social sign-in was cancelled or denied.');
    redirect('auth/login.php');
}

$code = (string) ($_GET['code'] ?? '');
$state = (string) ($_GET['state'] ?? '');
if ($code === '' || $state === '') {
    flash('error', 'Missing OAuth response. Please try again.');
    redirect('auth/login.php');
}

$result = oauth_finish($code, $state);
if (!$result['ok']) {
    flash('error', $result['error'] ?? 'Social sign-in failed.');
    redirect('auth/login.php');
}

flash('success', 'Signed in successfully.');
post_login_redirect();
