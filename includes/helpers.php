<?php
declare(strict_types=1);

require_once __DIR__ . '/../config/database.php';

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function redirect(string $path): never
{
    if (str_starts_with($path, 'http')) {
        header('Location: ' . $path);
    } else {
        header('Location: ' . APP_URL . '/' . ltrim($path, '/'));
    }
    exit;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $flashes = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return $flashes;
}

function csrf_token(): string
{
    if (empty($_SESSION[CSRF_TOKEN_KEY])) {
        $_SESSION[CSRF_TOKEN_KEY] = bin2hex(random_bytes(32));
    }
    return $_SESSION[CSRF_TOKEN_KEY];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function verify_csrf(): void
{
    $token = $_POST['csrf_token'] ?? '';
    $expected = $_SESSION[CSRF_TOKEN_KEY] ?? '';
    if (
        !is_string($token)
        || $token === ''
        || !is_string($expected)
        || $expected === ''
        || !hash_equals($expected, $token)
    ) {
        // Issue a fresh token so the next form submit can succeed.
        unset($_SESSION[CSRF_TOKEN_KEY]);
        flash('error', 'Your session expired or the form is outdated. Please try again.');

        $referer = $_SERVER['HTTP_REFERER'] ?? '';
        if (is_string($referer) && $referer !== '' && str_starts_with($referer, APP_URL)) {
            header('Location: ' . $referer);
            exit;
        }

        $uri = $_SERVER['REQUEST_URI'] ?? '';
        if (is_string($uri) && $uri !== '' && str_starts_with($uri, '/') && !str_contains($uri, "\n")) {
            header('Location: ' . $uri);
            exit;
        }

        redirect('auth/login.php');
    }
}

function start_app_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $savePath = dirname(__DIR__) . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'sessions';
    if (!is_dir($savePath)) {
        @mkdir($savePath, 0775, true);
    }
    if (is_dir($savePath) && is_writable($savePath)) {
        session_save_path($savePath);
    }

    session_name(SESSION_NAME);
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();

    $now = time();
    if (isset($_SESSION['last_activity']) && ($now - (int) $_SESSION['last_activity']) > SESSION_TIMEOUT_MINUTES * 60) {
        $wasLoggedIn = !empty($_SESSION['user']);
        // Clear data but keep a usable session cookie (destroy() broke CSRF on the next POST).
        $_SESSION = [];
        session_regenerate_id(true);
        if ($wasLoggedIn) {
            flash('error', 'Your session expired. Please log in again.');
        }
    }
    $_SESSION['last_activity'] = $now;
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function is_logged_in(): bool
{
    return current_user() !== null;
}

function require_login(): void
{
    if (!is_logged_in()) {
        flash('error', 'Please log in to continue.');
        redirect('auth/login.php');
    }
    // Refresh active status
    $user = current_user();
    $stmt = db()->prepare('SELECT is_active FROM users WHERE id = ?');
    $stmt->execute([(int) $user['id']]);
    $row = $stmt->fetch();
    if (!$row || !(int) $row['is_active']) {
        logout_user();
        flash('error', 'Your account is inactive. Contact an administrator.');
        redirect('auth/login.php');
    }
}

function require_role(array $roles): void
{
    require_login();
    $user = current_user();
    if (!in_array($user['role'], $roles, true)) {
        flash('error', 'You do not have permission to access that page.');
        redirect('user/dashboard.php');
    }
}

function login_user(array $user): void
{
    session_regenerate_id(true);
    $_SESSION['user'] = [
        'id' => (int) $user['id'],
        'full_name' => $user['full_name'],
        'email' => $user['email'],
        'role' => $user['role'],
    ];
    $stmt = db()->prepare('UPDATE users SET last_login_at = NOW() WHERE id = ?');
    $stmt->execute([(int) $user['id']]);
    log_activity((int) $user['id'], 'login', 'User logged in');
}

function logout_user(): void
{
    if (is_logged_in()) {
        log_activity((int) current_user()['id'], 'logout', 'User logged out');
    }
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function log_activity(?int $userId, string $action, ?string $details = null): void
{
    try {
        $stmt = db()->prepare('INSERT INTO activity_logs (user_id, action, details, ip_address) VALUES (?, ?, ?, ?)');
        $stmt->execute([$userId, $action, $details, $_SERVER['REMOTE_ADDR'] ?? null]);
    } catch (Throwable $e) {
        // Do not break app if logging fails
    }
}

function audit_log(string $action, ?string $entityType = null, ?int $entityId = null, ?string $details = null): void
{
    $adminId = current_user()['id'] ?? null;
    try {
        $stmt = db()->prepare('INSERT INTO audit_logs (admin_id, action, entity_type, entity_id, details) VALUES (?, ?, ?, ?, ?)');
        $stmt->execute([$adminId, $action, $entityType, $entityId, $details]);
    } catch (Throwable $e) {
        // ignore
    }
}

function role_home(string $role): string
{
    return match ($role) {
        'admin' => 'admin/dashboard.php',
        'lecturer' => 'admin/reports.php',
        default => 'user/dashboard.php',
    };
}

function format_datetime(?string $dt): string
{
    if (!$dt) {
        return '—';
    }
    return date('d M Y H:i', strtotime($dt));
}

function issue_certificate_if_eligible(int $userId, int $moduleId): ?string
{
    // All active lessons completed + quiz passed
    $lessons = db()->prepare('SELECT COUNT(*) FROM lessons WHERE module_id = ? AND is_active = 1');
    $lessons->execute([$moduleId]);
    $lessonCount = (int) $lessons->fetchColumn();

    $done = db()->prepare(
        'SELECT COUNT(*) FROM lesson_progress lp
         JOIN lessons l ON l.id = lp.lesson_id
         WHERE lp.user_id = ? AND l.module_id = ? AND lp.status = \'completed\' AND l.is_active = 1'
    );
    $done->execute([$userId, $moduleId]);
    $completed = (int) $done->fetchColumn();

    if ($lessonCount === 0 || $completed < $lessonCount) {
        return null;
    }

    // Practice quizzes do not count toward certificates — need module or exam pass.
    $quiz = db()->prepare(
        "SELECT id FROM quizzes
         WHERE module_id = ? AND is_active = 1 AND quiz_type IN ('module', 'exam')
         ORDER BY FIELD(quiz_type, 'exam', 'module') LIMIT 1"
    );
    $quiz->execute([$moduleId]);
    $quizId = $quiz->fetchColumn();
    if (!$quizId) {
        $quiz = db()->prepare('SELECT id FROM quizzes WHERE module_id = ? AND is_active = 1 LIMIT 1');
        $quiz->execute([$moduleId]);
        $quizId = $quiz->fetchColumn();
    }
    if (!$quizId) {
        return null;
    }

    $pass = db()->prepare('SELECT id FROM quiz_attempts WHERE user_id = ? AND quiz_id = ? AND passed = 1 LIMIT 1');
    $pass->execute([$userId, (int) $quizId]);
    if (!$pass->fetchColumn()) {
        return null;
    }

    $exists = db()->prepare('SELECT certificate_code FROM certificates WHERE user_id = ? AND module_id = ?');
    $exists->execute([$userId, $moduleId]);
    $code = $exists->fetchColumn();
    if ($code) {
        return (string) $code;
    }

    $code = 'CAMS-' . strtoupper(bin2hex(random_bytes(4)));
    $ins = db()->prepare('INSERT INTO certificates (user_id, module_id, certificate_code) VALUES (?, ?, ?)');
    $ins->execute([$userId, $moduleId, $code]);
    return $code;
}

/**
 * Add ids to h2/h3 and return [html, toc[]] for article navigation.
 * @return array{0: string, 1: list<array{id: string, text: string, level: int}>}
 */
function lesson_content_with_toc(string $html): array
{
    if (trim($html) === '') {
        return ['', []];
    }

    $toc = [];
    $index = 0;
    $enriched = preg_replace_callback(
        '/<(h[23])(\s[^>]*)?>(.*?)<\/\1>/is',
        static function (array $m) use (&$toc, &$index): string {
            $tag = strtolower($m[1]);
            $attrs = $m[2] ?? '';
            $inner = $m[3];
            $text = trim(html_entity_decode(strip_tags($inner), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
            if ($text === '') {
                return $m[0];
            }
            $index++;
            $id = 'section-' . $index;
            if (preg_match('/\sid\s*=\s*["\']([^"\']+)["\']/i', $attrs ?? '', $idMatch)) {
                $id = $idMatch[1];
            } else {
                $attrs = rtrim((string) $attrs) . ' id="' . htmlspecialchars($id, ENT_QUOTES, 'UTF-8') . '"';
            }
            $toc[] = [
                'id' => $id,
                'text' => $text,
                'level' => $tag === 'h3' ? 3 : 2,
            ];
            return '<' . $tag . $attrs . '>' . $inner . '</' . $tag . '>';
        },
        $html
    );

    return [$enriched ?? $html, $toc];
}

function estimate_read_minutes(string $html): int
{
    $words = str_word_count(strip_tags($html));
    return max(1, (int) ceil($words / 180));
}

function quiz_default_draw_count(string $quizType): int
{
    return match ($quizType) {
        'practice' => defined('QUIZ_DRAW_PRACTICE') ? (int) QUIZ_DRAW_PRACTICE : 20,
        'exam' => defined('QUIZ_DRAW_EXAM') ? (int) QUIZ_DRAW_EXAM : 40,
        default => defined('QUIZ_DRAW_MODULE') ? (int) QUIZ_DRAW_MODULE : 25,
    };
}

/**
 * Ensure schema supports question banks and per-attempt draws.
 */
function ensure_quiz_bank_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    $pdo = db();
    $type = $pdo->query("SHOW COLUMNS FROM quizzes LIKE 'quiz_type'")->fetch();
    if ($type && isset($type['Type']) && !str_contains((string) $type['Type'], 'bank')) {
        $pdo->exec(
            "ALTER TABLE quizzes
             MODIFY COLUMN quiz_type ENUM('practice','module','exam','bank') NOT NULL DEFAULT 'module'"
        );
    }
    $draw = $pdo->query("SHOW COLUMNS FROM quizzes LIKE 'questions_to_draw'")->fetch();
    if (!$draw) {
        $pdo->exec(
            'ALTER TABLE quizzes ADD COLUMN questions_to_draw SMALLINT UNSIGNED NOT NULL DEFAULT 0 AFTER max_attempts'
        );
    }
    $done = true;
}

/**
 * @return list<array<string,mixed>>
 */
function load_module_question_bank(int $moduleId): array
{
    ensure_quiz_bank_schema();
    $stmt = db()->prepare(
        "SELECT qs.* FROM questions qs
         JOIN quizzes q ON q.id = qs.quiz_id
         WHERE q.module_id = ? AND q.quiz_type = 'bank' AND qs.is_active = 1
         ORDER BY qs.id"
    );
    $stmt->execute([$moduleId]);
    $bank = $stmt->fetchAll();
    if ($bank) {
        return $bank;
    }
    // Fallback: any active questions for quizzes in this module (excluding empty)
    $stmt = db()->prepare(
        "SELECT qs.* FROM questions qs
         JOIN quizzes q ON q.id = qs.quiz_id
         WHERE q.module_id = ? AND q.is_active = 1 AND q.quiz_type <> 'bank' AND qs.is_active = 1
         ORDER BY qs.id"
    );
    $stmt->execute([$moduleId]);
    return $stmt->fetchAll();
}

/**
 * Shuffle options; returns question row with remapped A–D and updated correct_option.
 *
 * @param array<string,mixed> $question
 * @return array<string,mixed>
 */
function shuffle_question_options(array $question): array
{
    $map = [
        'A' => (string) $question['option_a'],
        'B' => (string) $question['option_b'],
        'C' => (string) $question['option_c'],
        'D' => (string) $question['option_d'],
    ];
    $correctText = $map[$question['correct_option']] ?? $map['A'];
    $texts = array_values($map);
    shuffle($texts);
    $letters = ['A', 'B', 'C', 'D'];
    $newCorrect = 'A';
    foreach ($texts as $i => $text) {
        $letter = $letters[$i];
        $question['option_' . strtolower($letter)] = $text;
        if ($text === $correctText) {
            $newCorrect = $letter;
        }
    }
    $question['correct_option'] = $newCorrect;
    return $question;
}

/**
 * Draw a shuffled subset for this attempt; persists in session until submitted/cleared.
 *
 * @return list<array<string,mixed>>
 */
function quiz_draw_questions(int $userId, array $quiz, bool $forceNew = false): array
{
    ensure_quiz_bank_schema();
    $quizId = (int) $quiz['id'];
    $moduleId = (int) $quiz['module_id'];
    $key = 'quiz_draw_' . $userId . '_' . $quizId;

    if (!$forceNew && !empty($_SESSION[$key]['questions']) && is_array($_SESSION[$key]['questions'])) {
        return $_SESSION[$key]['questions'];
    }

    $bank = load_module_question_bank($moduleId);
    if (!$bank) {
        $stmt = db()->prepare('SELECT * FROM questions WHERE quiz_id = ? AND is_active = 1 ORDER BY sort_order, id');
        $stmt->execute([$quizId]);
        $bank = $stmt->fetchAll();
    }

    $draw = (int) ($quiz['questions_to_draw'] ?? 0);
    if ($draw < 1) {
        $draw = quiz_default_draw_count((string) ($quiz['quiz_type'] ?? 'module'));
    }
    $draw = min($draw, count($bank));

    shuffle($bank);
    $picked = array_slice($bank, 0, $draw);
    $out = [];
    foreach ($picked as $q) {
        $out[] = shuffle_question_options($q);
    }

    $_SESSION[$key] = [
        'questions' => $out,
        'drawn_at' => time(),
    ];
    return $out;
}

function quiz_clear_draw(int $userId, int $quizId): void
{
    unset($_SESSION['quiz_draw_' . $userId . '_' . $quizId]);
}
