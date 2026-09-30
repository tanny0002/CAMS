<?php
/**
 * CLI smoke test for CAMS (login + key pages).
 * php -c .\.php.ini database/smoke_test.php
 */
declare(strict_types=1);

$base = getenv('CAMS_SMOKE_BASE') ?: 'http://127.0.0.1:8080';
$cookie = tempnam(sys_get_temp_dir(), 'cams_cookie_');

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
        CURLOPT_TIMEOUT => 20,
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

[$code, $body, $err] = req('GET', $base . '/index.php', $cookie);
check('Landing page', $code === 200 && str_contains($body, 'CAMS'), "HTTP {$code} {$err}");

[$code, $body] = req('GET', $base . '/auth/login.php', $cookie);
$csrf = extract_csrf($body);
check('Login page + CSRF', $code === 200 && $csrf !== null, $csrf ? 'token ok' : "HTTP {$code}");

$post = http_build_query([
    'csrf_token' => $csrf ?? '',
    'email' => 'student@cams.local',
    'password' => 'Student@123',
]);
[$code, $body] = req('POST', $base . '/auth/login.php', $cookie, $post);
check('Student login redirect', $code === 302 || ($code === 200 && !str_contains($body, 'Invalid')), "HTTP {$code}");

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

// Open first lesson if linked
[$code, $body] = req('GET', $base . '/user/lessons.php', $cookie);
if (preg_match('/lesson\.php\?id=(\d+)/', $body, $m)) {
    [$code, $body] = req('GET', $base . '/user/lesson.php?id=' . $m[1], $cookie);
    check('Lesson reader', $code === 200 && (str_contains($body, 'course-sidebar') || str_contains($body, 'On this page') || str_contains($body, 'Mark lesson')), "HTTP {$code}");
} else {
    check('Lesson reader', false, 'no lesson link found');
}

// Quiz list shows bank
[$code, $body] = req('GET', $base . '/user/quizzes.php', $cookie);
check('Quiz bank copy', $code === 200 && (str_contains($body, 'bank of') || str_contains($body, 'shuffled')), "HTTP {$code}");

if (preg_match('/take-quiz\.php\?id=(\d+)/', $body, $m)) {
    [$code, $body] = req('GET', $base . '/user/take-quiz.php?id=' . $m[1], $cookie);
    check('Take quiz (shuffled draw)', $code === 200 && str_contains($body, 'shuffled'), "HTTP {$code}");
} else {
    check('Take quiz (shuffled draw)', false, 'no quiz link');
}

req('GET', $base . '/auth/logout.php', $cookie);

[$code, $body] = req('GET', $base . '/auth/login.php', $cookie);
$csrf = extract_csrf($body);
$post = http_build_query([
    'csrf_token' => $csrf ?? '',
    'email' => 'admin@cams.local',
    'password' => 'Admin@123',
]);
[$code] = req('POST', $base . '/auth/login.php', $cookie, $post);
check('Admin login', $code === 302 || $code === 200, "HTTP {$code}");

foreach (['/admin/dashboard.php' => 'Dashboard', '/admin/modules.php' => 'Modules', '/admin/lessons.php' => 'Lessons', '/admin/quizzes.php' => 'Quizzes', '/admin/reports.php' => 'Reports'] as $path => $label) {
    [$code, $body] = req('GET', $base . $path, $cookie);
    check("Admin {$label}", $code === 200, "HTTP {$code}");
}

check('Installer disabled', !is_file(dirname(__DIR__) . '/install.php'), is_file(dirname(__DIR__) . '/install.php.bak') ? 'install.php.bak present' : 'bak missing');

@unlink($cookie);
echo $fail === 0 ? "\nAll smoke checks passed.\n" : "\n{$fail} check(s) failed.\n";
exit($fail === 0 ? 0 : 1);
