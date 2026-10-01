<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();
ensure_access_schema();

$token = (string) ($_GET['token'] ?? '');
$invite = find_valid_invitation($token);
if (!$invite) {
    flash('error', 'This invitation is invalid or has expired (links are valid for 24 hours).');
    redirect('auth/register.php');
}

$_SESSION['invite_token'] = $token;
$_SESSION['invite_email'] = $invite['email'];

if (is_logged_in()) {
    $email = strtolower((string) current_user()['email']);
    if ($email !== strtolower((string) $invite['email'])) {
        flash('error', 'Log in with ' . $invite['email'] . ' to accept this invitation.');
        redirect('auth/logout.php');
    }
    redirect('auth/complete-profile.php?invite=' . urlencode($token));
}

flash('success', 'Invitation found for ' . $invite['email'] . '. Create your account or sign in with that email.');
redirect('auth/register.php?invite=' . urlencode($token));
