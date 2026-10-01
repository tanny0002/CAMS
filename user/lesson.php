<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();
require_login();

$uid = (int) current_user()['id'];
$id = (int) ($_GET['id'] ?? 0);

$stmt = db()->prepare(
    'SELECT l.*, m.title AS module_title, m.id AS mid, m.difficulty, m.description AS module_description
     FROM lessons l
     JOIN modules m ON m.id = l.module_id
     WHERE l.id = ? AND l.is_active = 1 AND m.is_active = 1'
);
$stmt->execute([$id]);
$lesson = $stmt->fetch();
if (!$lesson) {
    flash('error', 'Lesson not found.');
    redirect('user/lessons.php');
}

$moduleId = (int) $lesson['mid'];
if (current_user()['role'] !== 'admin' && !user_can_access_module((int) current_user()['id'], $moduleId)) {
    flash('error', 'You do not have access to this course yet.');
    redirect('user/lessons.php');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'complete') {
    verify_csrf();
    $up = db()->prepare(
        db_upsert_sql(
            'INSERT INTO lesson_progress (user_id, lesson_id, status, completed_at) VALUES (?, ?, \'completed\', NOW())',
            'user_id, lesson_id',
            'status = \'completed\', completed_at = NOW()'
        )
    );
    $up->execute([$uid, $id]);
    log_activity($uid, 'lesson_completed', 'Lesson #' . $id);
    issue_certificate_if_eligible($uid, $moduleId);
    flash('success', 'Lesson marked as completed.');
    redirect('user/lesson.php?id=' . $id);
}

$prog = db()->prepare('SELECT status FROM lesson_progress WHERE user_id = ? AND lesson_id = ?');
$prog->execute([$uid, $id]);
$status = $prog->fetchColumn() ?: null;

if (!$status) {
    if (db_is_pgsql()) {
        db()->prepare(
            'INSERT INTO lesson_progress (user_id, lesson_id, status) VALUES (?, ?, \'in_progress\')
             ON CONFLICT (user_id, lesson_id) DO NOTHING'
        )->execute([$uid, $id]);
    } else {
        db()->prepare(
            'INSERT INTO lesson_progress (user_id, lesson_id, status) VALUES (?, ?, \'in_progress\')
             ON DUPLICATE KEY UPDATE status = status'
        )->execute([$uid, $id]);
    }
    $status = 'in_progress';
}

$siblingsStmt = db()->prepare(
    'SELECT l.id, l.title, l.sort_order, lp.status AS progress_status
     FROM lessons l
     LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.user_id = ?
     WHERE l.module_id = ? AND l.is_active = 1
     ORDER BY l.sort_order, l.id'
);
$siblingsStmt->execute([$uid, $moduleId]);
$siblings = $siblingsStmt->fetchAll();

$prev = null;
$next = null;
$currentIndex = 0;
foreach ($siblings as $i => $sib) {
    if (!is_array($sib)) {
        continue;
    }
    if ((int) ($sib['id'] ?? 0) === $id) {
        $candidatePrev = $siblings[$i - 1] ?? null;
        $candidateNext = $siblings[$i + 1] ?? null;
        $prev = is_array($candidatePrev) ? $candidatePrev : null;
        $next = is_array($candidateNext) ? $candidateNext : null;
        $currentIndex = $i + 1;
        break;
    }
}

$quizStmt = db()->prepare(
    "SELECT id, title FROM quizzes
     WHERE module_id = ? AND is_active = 1 AND quiz_type IN ('module', 'exam')
     ORDER BY CASE quiz_type WHEN 'module' THEN 1 WHEN 'exam' THEN 2 ELSE 3 END LIMIT 1"
);
$quizStmt->execute([$moduleId]);
$moduleQuiz = $quizStmt->fetch(PDO::FETCH_ASSOC) ?: null;
if (!$moduleQuiz || !is_array($moduleQuiz)) {
    $quizStmt = db()->prepare('SELECT id, title FROM quizzes WHERE module_id = ? AND is_active = 1 LIMIT 1');
    $quizStmt->execute([$moduleId]);
    $moduleQuiz = $quizStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
if (!is_array($moduleQuiz)) {
    $moduleQuiz = null;
}

[$lessonHtml, $toc] = lesson_content_with_toc((string) $lesson['content'], (string) $lesson['title']);
$readMinutes = estimate_read_minutes((string) $lesson['content']);
$completedCount = count(array_filter($siblings, static fn($s) => ($s['progress_status'] ?? '') === 'completed'));
$siblingTotal = count($siblings);
$coursePct = $siblingTotal ? (int) round(($completedCount / $siblingTotal) * 100) : 0;

$pageTitle = $lesson['title'];
$bodyClass = 'page-lesson-reader';
require __DIR__ . '/../includes/header.php';
?>

<div class="reader-shell">
    <aside class="reader-rail" aria-label="Course outline">
        <div class="reader-rail-inner">
            <a class="reader-back" href="<?= e(APP_URL) ?>/user/lessons.php?module=<?= $moduleId ?>">← All courses</a>
            <p class="reader-kicker"><?= e($lesson['module_title']) ?></p>
            <div class="reader-progress">
                <div class="reader-progress-meta">
                    <span><?= $completedCount ?>/<?= $siblingTotal ?> lessons</span>
                    <strong><?= $coursePct ?>%</strong>
                </div>
                <div class="progress-bar" aria-hidden="true"><span style="width:<?= $coursePct ?>%"></span></div>
            </div>
            <nav class="reader-nav" aria-label="Lessons">
                <ol>
                    <?php foreach ($siblings as $i => $sib): ?>
                        <?php
                        $isCurrent = (int) $sib['id'] === $id;
                        $done = ($sib['progress_status'] ?? '') === 'completed';
                        ?>
                        <li class="<?= $isCurrent ? 'is-current' : '' ?> <?= $done ? 'is-done' : '' ?>">
                            <a href="<?= e(APP_URL) ?>/user/lesson.php?id=<?= (int) $sib['id'] ?>">
                                <span class="reader-nav-num"><?= $i + 1 ?></span>
                                <span class="reader-nav-title"><?= e($sib['title']) ?></span>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>
            <?php if ($moduleQuiz): ?>
                <a class="btn btn-outline btn-sm reader-quiz-cta" href="<?= e(APP_URL) ?>/user/take-quiz.php?id=<?= (int) $moduleQuiz['id'] ?>">
                    Module quiz
                </a>
            <?php endif; ?>
        </div>
    </aside>

    <div class="reader-stage">
        <header class="reader-hero">
            <p class="reader-eyebrow">
                Lesson <?= $currentIndex ?> of <?= $siblingTotal ?>
                · <?= e(ucfirst((string) $lesson['difficulty'])) ?>
                · ~<?= $readMinutes ?> min
                <?php if ($status === 'completed'): ?>
                    · <span class="badge badge-ok">Completed</span>
                <?php endif; ?>
            </p>
            <h1><?= e($lesson['title']) ?></h1>
            <?php if (!empty($lesson['summary'])): ?>
                <p class="reader-deck"><?= e($lesson['summary']) ?></p>
            <?php endif; ?>
        </header>

        <?php if ($toc): ?>
            <details class="reader-toc" open>
                <summary>On this page</summary>
                <ol>
                    <?php foreach ($toc as $item): ?>
                        <li class="toc-level-<?= (int) $item['level'] ?>">
                            <a href="#<?= e($item['id']) ?>"><?= e($item['text']) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </details>
        <?php endif; ?>

        <article class="reader-prose">
            <?= $lessonHtml ?>
        </article>

        <footer class="reader-footer">
            <div class="reader-complete">
                <?php if ($status !== 'completed'): ?>
                    <form method="post" action="">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="complete">
                        <button class="btn btn-primary" type="submit">Mark as completed</button>
                    </form>
                <?php else: ?>
                    <p class="muted" style="margin:0">You’ve completed this lesson.</p>
                    <?php if ($moduleQuiz): ?>
                        <a class="btn btn-primary" href="<?= e(APP_URL) ?>/user/take-quiz.php?id=<?= (int) $moduleQuiz['id'] ?>">Take module quiz</a>
                    <?php endif; ?>
                <?php endif; ?>
            </div>

            <nav class="reader-pager" aria-label="Lesson navigation">
                <?php if ($prev): ?>
                    <a class="reader-pager-card" href="<?= e(APP_URL) ?>/user/lesson.php?id=<?= (int) $prev['id'] ?>">
                        <span>Previous</span>
                        <strong><?= e($prev['title']) ?></strong>
                    </a>
                <?php else: ?>
                    <span></span>
                <?php endif; ?>
                <?php if ($next): ?>
                    <a class="reader-pager-card is-next" href="<?= e(APP_URL) ?>/user/lesson.php?id=<?= (int) $next['id'] ?>">
                        <span>Next</span>
                        <strong><?= e($next['title']) ?></strong>
                    </a>
                <?php elseif ($moduleQuiz): ?>
                    <a class="reader-pager-card is-next" href="<?= e(APP_URL) ?>/user/take-quiz.php?id=<?= (int) $moduleQuiz['id'] ?>">
                        <span>Next step</span>
                        <strong>Take <?= e($moduleQuiz['title']) ?></strong>
                    </a>
                <?php endif; ?>
            </nav>
        </footer>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
