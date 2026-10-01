<?php
declare(strict_types=1);

/**
 * Google / GitHub OAuth helpers (authorization-code flow).
 */

function oauth_redirect_uri(): string
{
    return rtrim(APP_URL, '/') . '/auth/oauth-callback.php';
}

function oauth_log(string $message): void
{
    $dir = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    @file_put_contents($dir . DIRECTORY_SEPARATOR . 'oauth.log', '[' . date('c') . '] ' . $message . "\n", FILE_APPEND);
}

function oauth_start(string $provider): never
{
    $provider = strtolower($provider);
    if (!in_array($provider, ['google', 'github'], true)) {
        flash('error', 'Unknown sign-in provider.');
        redirect('auth/login.php');
    }

    if ($provider === 'google' && !OAUTH_GOOGLE_ENABLED) {
        flash('error', 'Google sign-in is not configured. Set GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET in .env.');
        redirect('auth/login.php');
    }
    if ($provider === 'github' && !OAUTH_GITHUB_ENABLED) {
        flash('error', 'GitHub sign-in is not configured. Set GITHUB_CLIENT_ID and GITHUB_CLIENT_SECRET in .env.');
        redirect('auth/login.php');
    }

    $state = bin2hex(random_bytes(16));
    $_SESSION['oauth_state'] = $state;
    $_SESSION['oauth_provider'] = $provider;
    // Persist redirect URI used at start so token exchange matches exactly.
    $_SESSION['oauth_redirect_uri'] = oauth_redirect_uri();
    oauth_log("start provider={$provider} redirect=" . $_SESSION['oauth_redirect_uri']);

    if ($provider === 'google') {
        $params = http_build_query([
            'client_id' => GOOGLE_CLIENT_ID,
            'redirect_uri' => $_SESSION['oauth_redirect_uri'],
            'response_type' => 'code',
            'scope' => 'openid email profile',
            'state' => $state,
            'access_type' => 'online',
            'prompt' => 'select_account',
        ]);
        redirect('https://accounts.google.com/o/oauth2/v2/auth?' . $params);
    }

    $params = http_build_query([
        'client_id' => GITHUB_CLIENT_ID,
        'redirect_uri' => $_SESSION['oauth_redirect_uri'],
        'scope' => 'read:user user:email',
        'state' => $state,
        'allow_signup' => 'true',
    ]);
    redirect('https://github.com/login/oauth/authorize?' . $params);
}

/**
 * @return array{ok:bool,error?:string,provider?:string,id?:string,email?:string,name?:string}
 */
function oauth_finish(string $code, string $state): array
{
    ensure_auth_schema();

    $expectedState = $_SESSION['oauth_state'] ?? '';
    $provider = (string) ($_SESSION['oauth_provider'] ?? '');
    $redirectUri = (string) ($_SESSION['oauth_redirect_uri'] ?? oauth_redirect_uri());
    unset($_SESSION['oauth_state'], $_SESSION['oauth_provider'], $_SESSION['oauth_redirect_uri']);

    if ($expectedState === '' || !hash_equals($expectedState, $state) || $provider === '') {
        oauth_log('invalid state expected_empty=' . ($expectedState === '' ? '1' : '0') . " provider={$provider}");
        return ['ok' => false, 'error' => 'Invalid OAuth state. Please try again.'];
    }

    try {
        if ($provider === 'google') {
            $profile = oauth_google_profile($code, $redirectUri);
        } elseif ($provider === 'github') {
            $profile = oauth_github_profile($code, $redirectUri);
        } else {
            return ['ok' => false, 'error' => 'Unknown provider.'];
        }
    } catch (Throwable $e) {
        oauth_log('finish error: ' . $e->getMessage());
        error_log('OAuth error: ' . $e->getMessage());
        $msg = $e->getMessage();
        if (str_contains($msg, 'redirect_uri') || str_contains($msg, 'redirect_uri_mismatch')) {
            return [
                'ok' => false,
                'error' => 'Google redirect URI mismatch. In Google Cloud, set Authorized redirect URI to exactly: '
                    . $redirectUri,
            ];
        }
        if (str_contains($msg, 'invalid_client')) {
            return ['ok' => false, 'error' => 'Invalid Google client ID or secret. Check GOOGLE_CLIENT_* in .env.'];
        }
        return ['ok' => false, 'error' => 'Could not complete social sign-in. ' . (APP_ENV !== 'production' ? $msg : 'Please try again.')];
    }

    if ($profile['email'] === '') {
        return ['ok' => false, 'error' => 'Your provider account has no verified email we can use.'];
    }

    try {
        $user = oauth_find_or_create_user($provider, $profile['id'], $profile['email'], $profile['name']);
    } catch (Throwable $e) {
        oauth_log('user create error: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'Could not create your account. Please try again.'];
    }
    if (!$user) {
        return ['ok' => false, 'error' => 'Could not create your account.'];
    }
    if (!(int) $user['is_active']) {
        return ['ok' => false, 'error' => 'Your account is inactive. Contact an administrator.'];
    }

    login_user($user);
    return ['ok' => true, 'provider' => $provider];
}

/**
 * @return array{id:string,email:string,name:string}
 */
function oauth_google_profile(string $code, ?string $redirectUri = null): array
{
    $redirectUri = $redirectUri ?: oauth_redirect_uri();
    $token = oauth_http_json('POST', 'https://oauth2.googleapis.com/token', [
        'code' => $code,
        'client_id' => GOOGLE_CLIENT_ID,
        'client_secret' => GOOGLE_CLIENT_SECRET,
        'redirect_uri' => $redirectUri,
        'grant_type' => 'authorization_code',
    ], [
        'Accept: application/json',
    ]);
    $access = (string) ($token['access_token'] ?? '');
    if ($access === '') {
        throw new RuntimeException('Google token missing: ' . json_encode($token));
    }
    $info = oauth_http_json('GET', 'https://www.googleapis.com/oauth2/v3/userinfo', null, [
        'Authorization: Bearer ' . $access,
        'Accept: application/json',
    ]);
    $email = strtolower(trim((string) ($info['email'] ?? '')));
    $verified = !empty($info['email_verified']);
    if ($email === '' || !$verified) {
        throw new RuntimeException('Google email not verified');
    }
    return [
        'id' => (string) ($info['sub'] ?? ''),
        'email' => $email,
        'name' => trim((string) ($info['name'] ?? $info['given_name'] ?? 'CAMS User')) ?: 'CAMS User',
    ];
}

/**
 * @return array{id:string,email:string,name:string}
 */
function oauth_github_profile(string $code, ?string $redirectUri = null): array
{
    $redirectUri = $redirectUri ?: oauth_redirect_uri();
    $token = oauth_http_json('POST', 'https://github.com/login/oauth/access_token', [
        'client_id' => GITHUB_CLIENT_ID,
        'client_secret' => GITHUB_CLIENT_SECRET,
        'code' => $code,
        'redirect_uri' => $redirectUri,
    ], [
        'Accept: application/json',
    ]);
    $access = (string) ($token['access_token'] ?? '');
    if ($access === '') {
        throw new RuntimeException('GitHub token missing: ' . json_encode($token));
    }
    $headers = [
        'Authorization: Bearer ' . $access,
        'Accept: application/vnd.github+json',
        'User-Agent: CAMS-App',
    ];
    $info = oauth_http_json('GET', 'https://api.github.com/user', null, $headers);
    $email = strtolower(trim((string) ($info['email'] ?? '')));
    if ($email === '') {
        $emails = oauth_http_json('GET', 'https://api.github.com/user/emails', null, $headers);
        if (is_array($emails)) {
            foreach ($emails as $row) {
                if (!empty($row['primary']) && !empty($row['verified']) && !empty($row['email'])) {
                    $email = strtolower(trim((string) $row['email']));
                    break;
                }
            }
            if ($email === '') {
                foreach ($emails as $row) {
                    if (!empty($row['verified']) && !empty($row['email'])) {
                        $email = strtolower(trim((string) $row['email']));
                        break;
                    }
                }
            }
        }
    }
    $name = trim((string) ($info['name'] ?? $info['login'] ?? 'CAMS User')) ?: 'CAMS User';
    return [
        'id' => (string) ($info['id'] ?? ''),
        'email' => $email,
        'name' => $name,
    ];
}

/**
 * @param array<string,mixed>|null $form
 * @param list<string> $headers
 * @return array<string,mixed>
 */
function oauth_http_json(string $method, string $url, ?array $form = null, array $headers = []): array
{
    $ch = curl_init($url);
    if ($ch === false) {
        throw new RuntimeException('curl_init failed');
    }
    if ($form !== null) {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 45,
        CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_SSL_VERIFYPEER => true,
        CURLOPT_SSL_VERIFYHOST => 2,
        // Prefer IPv4 — some Windows networks hang on broken IPv6 routes.
        CURLOPT_IPRESOLVE => CURL_IPRESOLVE_V4,
    ]);
    $ca = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'cacert.pem';
    if (is_readable($ca)) {
        curl_setopt($ch, CURLOPT_CAINFO, $ca);
    }
    if ($form !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query($form));
    }
    $raw = curl_exec($ch);
    $err = curl_error($ch);
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    if ($raw === false) {
        throw new RuntimeException('HTTP failed: ' . $err);
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        throw new RuntimeException('HTTP ' . $code . ' non-JSON: ' . substr($raw, 0, 200));
    }
    if ($code >= 400 || isset($data['error'])) {
        $detail = (string) ($data['error_description'] ?? $data['error'] ?? substr($raw, 0, 200));
        throw new RuntimeException('HTTP ' . $code . ': ' . $detail);
    }
    return $data;
}

/**
 * @return array<string,mixed>|null
 */
function oauth_find_or_create_user(string $provider, string $oauthId, string $email, string $name): ?array
{
    ensure_access_schema();
    $pdo = db();

    $stmt = $pdo->prepare('SELECT * FROM users WHERE oauth_provider = ? AND oauth_id = ? LIMIT 1');
    $stmt->execute([$provider, $oauthId]);
    $byOauth = $stmt->fetch();
    if ($byOauth) {
        return $byOauth;
    }

    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $byEmail = $stmt->fetch();
    if ($byEmail) {
        $pdo->prepare(
            'UPDATE users SET oauth_provider = ?, oauth_id = ?, email_verified_at = COALESCE(email_verified_at, NOW()), full_name = CASE WHEN full_name IS NULL OR full_name = \'\' THEN ? ELSE full_name END WHERE id = ?'
        )->execute([$provider, $oauthId, $name, (int) $byEmail['id']]);
        maybe_promote_bootstrap_admin((int) $byEmail['id'], $email);
        $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
        $stmt->execute([(int) $byEmail['id']]);
        return $stmt->fetch() ?: null;
    }

    $role = bootstrap_role_for_email($email);
    $isAdmin = $role === 'admin';
    $pdo->prepare(
        "INSERT INTO users (full_name, email, password_hash, role, account_type, consent_accepted, email_verified_at,
          oauth_provider, oauth_id, profile_complete, must_change_password, approval_status)
         VALUES (?, ?, NULL, ?, ?, 1, NOW(), ?, ?, 0, ?, ?)"
    )->execute([
        $name,
        $email,
        $role,
        $isAdmin ? 'admin' : null,
        $provider,
        $oauthId,
        $isAdmin ? 1 : 0,
        $isAdmin ? 'approved' : 'pending',
    ]);
    $id = (int) $pdo->lastInsertId('users_id_seq');
    if ($id <= 0) {
        $id = (int) $pdo->lastInsertId();
    }
    if ($id <= 0) {
        $id = (int) $pdo->query('SELECT id FROM users WHERE email = ' . $pdo->quote($email) . ' ORDER BY id DESC LIMIT 1')->fetchColumn();
    }
    log_activity($id, 'register_oauth', 'Registered via ' . $provider);
    try {
        send_mail($email, 'Welcome to CAMS', email_welcome_html($name), 'Welcome to CAMS — your account is ready.');
    } catch (Throwable $e) {
        // optional
    }
    $stmt = $pdo->prepare('SELECT * FROM users WHERE id = ?');
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

function bootstrap_role_for_email(string $email): string
{
    $boot = strtolower(trim(env('BOOTSTRAP_ADMIN_EMAIL', 'chitaurotanaka00@gmail.com') ?? 'chitaurotanaka00@gmail.com'));
    if ($boot !== '' && strtolower($email) === $boot) {
        return 'admin';
    }
    $count = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
    return $count === 0 ? 'admin' : 'user';
}

function maybe_promote_bootstrap_admin(int $userId, string $email): void
{
    $boot = strtolower(trim(env('BOOTSTRAP_ADMIN_EMAIL', 'chitaurotanaka00@gmail.com') ?? 'chitaurotanaka00@gmail.com'));
    if ($boot === '' || strtolower($email) !== $boot) {
        return;
    }
    db()->prepare(
        "UPDATE users SET role = 'admin', account_type = 'admin', approval_status = 'approved',
         must_change_password = CASE WHEN password_hash IS NULL OR password_hash = '' THEN 1 ELSE must_change_password END
         WHERE id = ?"
    )->execute([$userId]);
}

function auth_oauth_buttons_html(string $context = 'login'): string
{
    $googleOn = OAUTH_GOOGLE_ENABLED;
    $githubOn = OAUTH_GITHUB_ENABLED;
    $githubUrl = e(APP_URL . '/auth/oauth-github.php');
    $label = $context === 'register' ? 'Sign up' : 'Continue';
    $csrf = e(csrf_token());
    $action = e(APP_URL . '/auth/google-credential.php');

    // Browser-based Google Identity Services (works when PHP cannot reach Google APIs).
    $google = $googleOn
        ? '<div class="auth-google-wrap">'
            . '<div id="cams-google-btn" class="cams-google-btn"></div>'
            . '<noscript><p class="muted">Enable JavaScript to sign in with Google.</p></noscript>'
            . '<form id="cams-google-form" method="post" action="' . $action . '" class="is-hidden">'
            . '<input type="hidden" name="csrf_token" value="' . $csrf . '">'
            . '<input type="hidden" name="credential" id="cams-google-credential" value="">'
            . '</form>'
            . '<script src="https://accounts.google.com/gsi/client" async defer></script>'
            . '<script>'
            . 'window.addEventListener("load",function(){'
            . 'if(!window.google||!google.accounts||!google.accounts.id){return;}'
            . 'google.accounts.id.initialize({'
            . 'client_id:' . json_encode(GOOGLE_CLIENT_ID) . ','
            . 'callback:function(resp){'
            . 'var f=document.getElementById("cams-google-form");'
            . 'var c=document.getElementById("cams-google-credential");'
            . 'if(!f||!c||!resp||!resp.credential){return;}'
            . 'c.value=resp.credential;f.submit();'
            . '},'
            . 'auto_select:false,'
            . 'cancel_on_tap_outside:true'
            . '});'
            . 'google.accounts.id.renderButton(document.getElementById("cams-google-btn"),{'
            . 'type:"standard",theme:"outline",size:"large",text:"continue_with",shape:"rectangular",width:360'
            . '});'
            . '});'
            . '</script>'
            . '</div>'
        : '<span class="btn btn-oauth btn-oauth-disabled" title="Add GOOGLE_CLIENT_ID and GOOGLE_CLIENT_SECRET to .env">' . oauth_google_svg() . ' Google (configure .env)</span>';

    $github = $githubOn
        ? '<a class="btn btn-oauth btn-oauth-github" href="' . $githubUrl . '"><span class="oauth-icon" aria-hidden="true">' . oauth_github_svg() . '</span> ' . $label . ' with GitHub</a>'
        : '<span class="btn btn-oauth btn-oauth-disabled" title="Add GITHUB_CLIENT_ID and GITHUB_CLIENT_SECRET to .env">' . oauth_github_svg() . ' GitHub (configure .env)</span>';

    return '<div class="auth-divider"><span>or</span></div><div class="auth-oauth">' . $google . $github . '</div>';
}

function oauth_google_svg(): string
{
    return '<svg width="18" height="18" viewBox="0 0 48 48" aria-hidden="true"><path fill="#FFC107" d="M43.6 20.5H42V20H24v8h11.3C33.7 32.9 29.3 36 24 36c-6.6 0-12-5.4-12-12s5.4-12 12-12c3 0 5.8 1.1 7.9 3l5.7-5.7C34.2 6.1 29.3 4 24 4 12.9 4 4 12.9 4 24s8.9 20 20 20 20-8.9 20-20c0-1.3-.1-2.5-.4-3.5z"/><path fill="#FF3D00" d="M6.3 14.7l6.6 4.8C14.7 16 19 12 24 12c3 0 5.8 1.1 7.9 3l5.7-5.7C34.2 6.1 29.3 4 24 4 16.3 4 9.6 8.3 6.3 14.7z"/><path fill="#4CAF50" d="M24 44c5.2 0 9.9-2 13.4-5.2l-6.2-5.2C29.3 35.1 26.8 36 24 36c-5.2 0-9.6-3.1-11.3-7.5l-6.5 5C9.5 39.6 16.2 44 24 44z"/><path fill="#1976D2" d="M43.6 20.5H42V20H24v8h11.3c-1.1 3.1-3.5 5.5-6.5 6.8l.1.1 6.2 5.2C36.8 41.2 44 36 44 24c0-1.3-.1-2.5-.4-3.5z"/></svg>';
}

function oauth_github_svg(): string
{
    return '<svg width="18" height="18" viewBox="0 0 16 16" fill="currentColor" aria-hidden="true"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82A7.6 7.6 0 0 1 8 4.77c.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.19 0 .21.15.46.55.38A8.01 8.01 0 0 0 16 8c0-4.42-3.58-8-8-8z"/></svg>';
}
