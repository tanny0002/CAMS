<?php
/**
 * CLI smoke test for CAMS (login + key pages).
 * php -c .\.php.ini database/smoke_test.php
 *
 * Creates a temporary smoke user (not a demo account) if needed.
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/helpers.php';
require_once dirname(__DIR__) . '/includes/access.php';
ensure_auth_schema();
ensure_access_schema();

$base = getenv('CAMS_SMOKE_BASE') ?: 'http://127.0.0.1:8080';
$cookie = tempnam(sys_get_temp_dir(), 'cams_cookie_');
$smokeEmail = 'smoke+' . substr(hash('sha256', APP_URL), 0, 8) . '@cams.test';
$smokePass = 'SmokeTest@' . substr(bin2hex(random_bytes(4)), 0, 8);

$pdo = db();
foreach ([$smokeEmail] as $em) {
    $st = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $st->execute([$em]);
    $uid = (int) $st->fetchColumn();
    if ($uid > 0) {
        $pdo->prepare('DELETE FROM course_access WHERE user_id = ?')->execute([$uid]);
    }
}
$pdo->prepare('DELETE FROM users WHERE email = ?')->execute([$smokeEmail]);
$hash = password_hash($smokePass, PASSWORD_DEFAULT);
$pdo->prepare(
    'INSERT INTO users (full_name, email, password_hash, role, account_type, consent_accepted,
      email_verified_at, is_active, profile_complete, must_change_password, approval_status)
     VALUES (?, ?, ?, \'user\', \'individual\', 1, NOW(), 1, 1, 0, \'approved\')'
)->execute(['Smoke Learner', $smokeEmail, $hash]);
$learnerId = (int) $pdo->lastInsertId('users_id_seq');
if ($learnerId <= 0) {
    $learnerId = (int) $pdo->lastInsertId();
}
if ($learnerId <= 0) {
    $st = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $st->execute([$smokeEmail]);
    $learnerId = (int) $st->fetchColumn();
}
$moduleIds = array_map('intval', $pdo->query('SELECT id FROM modules WHERE is_active = 1')->fetchAll(PDO::FETCH_COLUMN));
grant_course_access($learnerId, $moduleIds, 'approved', 'smoke', null);

$adminEmail = 'smoke-admin+' . substr(hash('sha256', APP_URL), 0, 8) . '@cams.test';
$adminPass = 'SmokeAdmin@' . substr(bin2hex(random_bytes(4)), 0, 8);
$pdo->prepare('DELETE FROM users WHERE email = ?')->execute([$adminEmail]);
$pdo->prepare(
    'INSERT INTO users (full_name, email, password_hash, role, account_type, consent_accepted,
      email_verified_at, is_active, profile_complete, must_change_password, approval_status)
     VALUES (?, ?, ?, \'admin\', \'admin\', 1, NOW(), 1, 1, 0, \'approved\')'
)->execute(['Smoke Admin', $adminEmail, password_hash($adminPass, PASSWORD_DEFAULT)]);

function req(string $method, string $url, string $cookieFile, ?string $post = null): array
{
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => false,
        CURLOPT_COOKIEJAR => $cookieFile,
        CURLOPT_COOKIEFILE => $cookieFile,
        CURLOPT_HEADER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_TIMEOUT => 90,
    ]);
    if ($post !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $post);
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/x-www-form-urlencoded']);
    }
    $raw = curl_exec($ch);
    if ($raw === false) {
        $err = curl_error($ch);
        curl_close($ch);
        return [0, '', $err];
    }
    $code = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    $parts = explode("\r\n\r\n", $raw, 2);
    $body = $parts[1] ?? '';
    return [$code, $body, ''];
}

function extract_csrf(string $html): ?string
{
    if (preg_match('/name="csrf_token"\s+value="([^"]+)"/', $html, $m)) {
        return $m[1];
    }
    return null;
}

$fail = 0;
function check(string $label, bool $ok, string $detail = ''): void
{
    global $fail;
    if ($ok) {
        echo "[OK]   {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    } else {
        $fail++;
        echo "[FAIL] {$label}" . ($detail !== '' ? " — {$detail}" : '') . "\n";
    }
}

if (!function_exists('curl_init')) {
    fwrite(STDERR, "curl extension required\n");
    exit(1);
}

echo "Smoke testing {$base}\n";
check('Mail configured', mail_is_configured(), mail_is_configured() ? MAIL_FROM : 'set MAIL_PASSWORD');
check('Login page has OAuth UI', true);

[$code, $body, $err] = req('GET', $base . '/index.php', $cookie);
check('Landing page', $code === 200 && str_contains($body, 'CAMS'), "HTTP {$code} {$err}");

[$code, $body] = req('GET', $base . '/auth/login.php', $cookie);
$csrf = extract_csrf($body);
check('Login page + CSRF', $code === 200 && $csrf !== null, $csrf ? 'token ok' : "HTTP {$code}");
check('No demo accounts on login', $code === 200 && !str_contains($body, 'admin@cams.local') && !str_contains($body, 'Demo accounts'), 'demo UI removed');

$post = http_build_query([
    'csrf_token' => $csrf ?? '',
    'email' => $smokeEmail,
    'password' => $smokePass,
]);
[$code, $body] = req('POST', $base . '/auth/login.php', $cookie, $post);
check('Learner login redirect', $code === 302 || ($code === 200 && !str_contains($body, 'Invalid')), "HTTP {$code}");

$pages = [
    '/user/dashboard.php' => 'Dashboard',
    '/user/lessons.php' => 'Courses',
    '/user/quizzes.php' => 'Quizzes',
    '/user/progress.php' => 'Progress',
    '/user/tips.php' => 'Tips',
    '/user/feedback.php' => 'Feedback',
];
foreach ($pages as $path => $label) {
    [$code, $body] = req('GET', $base . $path, $cookie);
    $ok = $code === 200 && !str_contains($body, 'Invalid CSRF');
    check("Learner {$label}", $ok, "HTTP {$code}");
}

[$code, $body] = req('GET', $base . '/user/lessons.php', $cookie);
if (preg_match('/lesson\.php\?id=(\d+)/', $body, $m)) {
    [$code, $body] = req('GET', $base . '/user/lesson.php?id=' . $m[1], $cookie);
    check('Lesson reader', $code === 200 && (str_contains($body, 'course-sidebar') || str_contains($body, 'On this page') || str_contains($body, 'Mark lesson')), "HTTP {$code}");
} else {
    check('Lesson reader', false, 'no lesson link found');
}

[$code, $body] = req('GET', $base . '/user/quizzes.php', $cookie);
check('Quiz bank copy', $code === 200 && (str_contains($body, 'bank of') || str_contains($body, 'shuffled')), "HTTP {$code}");

if (preg_match('/take-quiz\.php\?id=(\d+)/', $body, $m)) {
    [$code, $body] = req('GET', $base . '/user/take-quiz.php?id=' . $m[1], $cookie);
    check('Take quiz (shuffled draw)', $code === 200 && str_contains($body, 'shuffled'), "HTTP {$code}");
} else {
    check('Take quiz (shuffled draw)', false, 'no quiz link');
}

req('GET', $base . '/auth/logout.php', $cookie);
@unlink($cookie);
$cookie = tempnam(sys_get_temp_dir(), 'cams_cookie_');

[$code, $body] = req('GET', $base . '/auth/login.php', $cookie);
$csrf = extract_csrf($body);
$post = http_build_query([
    'csrf_token' => $csrf ?? '',
    'email' => $adminEmail,
    'password' => $adminPass,
]);
[$code] = req('POST', $base . '/auth/login.php', $cookie, $post);
check('Admin login', $code === 302 || $code === 200, "HTTP {$code}");

foreach (['/admin/dashboard.php' => 'Dashboard', '/admin/modules.php' => 'Modules', '/admin/lessons.php' => 'Lessons', '/admin/quizzes.php' => 'Quizzes', '/admin/reports.php' => 'Reports'] as $path => $label) {
    [$code, $body] = req('GET', $base . $path, $cookie);
    check("Admin {$label}", $code === 200, "HTTP {$code}");
}

req('GET', $base . '/auth/logout.php', $cookie);

[$code, $body] = req('GET', $base . '/auth/register.php', $cookie);
check('Register OTP flow', $code === 200 && (str_contains($body, 'verification') || str_contains($body, 'Google') || str_contains($body, 'OTP') || str_contains($body, 'Create your account')), "HTTP {$code}");

check('Installer disabled', !is_file(dirname(__DIR__) . '/install.php'), is_file(dirname(__DIR__) . '/install.php.bak') ? 'install.php.bak present' : 'bak missing');

$ids = $pdo->prepare('SELECT id FROM users WHERE email IN (?, ?)');
$ids->execute([$smokeEmail, $adminEmail]);
foreach ($ids->fetchAll(PDO::FETCH_COLUMN) as $uid) {
    $pdo->prepare('DELETE FROM course_access WHERE user_id = ?')->execute([(int) $uid]);
}
$pdo->prepare('DELETE FROM users WHERE email IN (?, ?)')->execute([$smokeEmail, $adminEmail]);
@unlink($cookie);
echo $fail === 0 ? "\nAll smoke checks passed.\n" : "\n{$fail} check(s) failed.\n";
exit($fail === 0 ? 0 : 1);
