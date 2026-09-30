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

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'complete') {
    verify_csrf();
    $up = db()->prepare(
        'INSERT INTO lesson_progress (user_id, lesson_id, status, completed_at)
         VALUES (?, ?, \'completed\', NOW())
         ON DUPLICATE KEY UPDATE status = \'completed\', completed_at = NOW()'
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
    db()->prepare(
        'INSERT INTO lesson_progress (user_id, lesson_id, status) VALUES (?, ?, \'in_progress\')
         ON DUPLICATE KEY UPDATE status = status'
    )->execute([$uid, $id]);
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
foreach ($siblings as $i => $sib) {
    if ((int) $sib['id'] === $id) {
        $prev = $siblings[$i - 1] ?? null;
        $next = $siblings[$i + 1] ?? null;
        break;
    }
}

$quizStmt = db()->prepare(
    "SELECT id, title FROM quizzes
     WHERE module_id = ? AND is_active = 1 AND quiz_type IN ('module', 'exam')
     ORDER BY FIELD(quiz_type, 'module', 'exam') LIMIT 1"
);
$quizStmt->execute([$moduleId]);
$moduleQuiz = $quizStmt->fetch() ?: null;
if (!$moduleQuiz) {
    $quizStmt = db()->prepare('SELECT id, title FROM quizzes WHERE module_id = ? AND is_active = 1 LIMIT 1');
    $quizStmt->execute([$moduleId]);
    $moduleQuiz = $quizStmt->fetch() ?: null;
}

[$lessonHtml, $toc] = lesson_content_with_toc((string) $lesson['content']);
$readMinutes = estimate_read_minutes((string) $lesson['content']);
$completedCount = count(array_filter($siblings, static fn($s) => ($s['progress_status'] ?? '') === 'completed'));
$siblingTotal = count($siblings);

$pageTitle = $lesson['title'];
require __DIR__ . '/../includes/header.php';
?>

<div class="course-layout">
    <aside class="course-sidebar panel">
        <p class="course-sidebar-kicker">Course</p>
        <h2 class="course-sidebar-title"><?= e($lesson['module_title']) ?></h2>
        <p class="muted course-sidebar-meta">
            <?= e(ucfirst((string) $lesson['difficulty'])) ?> · <?= $completedCount ?>/<?= $siblingTotal ?> lessons done
        </p>
        <div class="progress-bar" style="margin:0.75rem 0 1rem" aria-hidden="true">
            <span style="width:<?= $siblingTotal ? (int) round(($completedCount / $siblingTotal) * 100) : 0 ?>%"></span>
        </div>
        <nav class="course-lesson-nav" aria-label="Lessons in this course">
            <ol>
                <?php foreach ($siblings as $i => $sib): ?>
                    <?php
                    $isCurrent = (int) $sib['id'] === $id;
                    $done = ($sib['progress_status'] ?? '') === 'completed';
                    ?>
                    <li class="<?= $isCurrent ? 'is-current' : '' ?> <?= $done ? 'is-done' : '' ?>">
                        <a href="<?= e(APP_URL) ?>/user/lesson.php?id=<?= (int) $sib['id'] ?>">
                            <span class="course-lesson-index"><?= $i + 1 ?></span>
                            <span class="course-lesson-name"><?= e($sib['title']) ?></span>
                        </a>
                    </li>
                <?php endforeach; ?>
            </ol>
        </nav>
        <?php if ($moduleQuiz): ?>
            <a class="btn btn-outline btn-sm course-quiz-link" href="<?= e(APP_URL) ?>/user/take-quiz.php?id=<?= (int) $moduleQuiz['id'] ?>">
                Module quiz: <?= e($moduleQuiz['title']) ?>
            </a>
        <?php endif; ?>
    </aside>

    <div class="course-main">
        <?php $coursePct = $siblingTotal ? (int) round(($completedCount / $siblingTotal) * 100) : 0; ?>
        <div class="panel course-progress-banner">
            <div class="progress-overview-head">
                <div>
                    <strong><?= e($lesson['module_title']) ?></strong>
                    <p class="muted" style="margin:0.25rem 0 0">Lesson progress · <?= $completedCount ?>/<?= $siblingTotal ?> complete</p>
                </div>
                <span class="progress-pct"><?= $coursePct ?>%</span>
            </div>
            <div class="progress-bar progress-bar-lg" role="progressbar" aria-valuenow="<?= $coursePct ?>" aria-valuemin="0" aria-valuemax="100">
                <span style="width:<?= $coursePct ?>%"></span>
            </div>
        </div>

        <div class="page-head course-article-head">
            <div>
                <p class="muted">
                    <a href="<?= e(APP_URL) ?>/user/lessons.php">Courses</a>
                    ·
                    <a href="<?= e(APP_URL) ?>/user/lessons.php?module=<?= $moduleId ?>"><?= e($lesson['module_title']) ?></a>
                </p>
                <h1><?= e($lesson['title']) ?></h1>
                <p class="course-article-meta muted">
                    ~<?= $readMinutes ?> min read
                    <?php if (!empty($lesson['summary'])): ?>
                        · <?= e($lesson['summary']) ?>
                    <?php endif; ?>
                    <?php if ($status === 'completed'): ?>
                        · <span class="badge badge-ok">Completed</span>
                    <?php elseif ($status === 'in_progress'): ?>
                        · <span class="badge badge-warn">In progress</span>
                    <?php endif; ?>
                </p>
            </div>
        </div>

        <?php if ($toc): ?>
            <nav class="panel course-toc" aria-label="On this page">
                <h2>On this page</h2>
                <ol>
                    <?php foreach ($toc as $item): ?>
                        <li class="toc-level-<?= (int) $item['level'] ?>">
                            <a href="#<?= e($item['id']) ?>"><?= e($item['text']) ?></a>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </nav>
        <?php endif; ?>

        <article class="panel lesson-content course-article">
            <?= $lessonHtml /* trusted admin/seed HTML with heading ids */ ?>
        </article>

        <div class="course-actions panel">
            <?php if ($status !== 'completed'): ?>
                <form method="post" action="">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="complete">
                    <button class="btn btn-primary" type="submit">Mark lesson as completed</button>
                </form>
            <?php else: ?>
                <p class="muted" style="margin:0">Lesson completed.</p>
                <?php if ($moduleQuiz): ?>
                    <a class="btn btn-primary" href="<?= e(APP_URL) ?>/user/take-quiz.php?id=<?= (int) $moduleQuiz['id'] ?>">Take module quiz</a>
                <?php endif; ?>
            <?php endif; ?>
        </div>

        <nav class="course-pager" aria-label="Lesson navigation">
            <?php if ($prev): ?>
                <a class="course-pager-link" href="<?= e(APP_URL) ?>/user/lesson.php?id=<?= (int) $prev['id'] ?>">
                    <span class="muted">Previous</span>
                    <strong><?= e($prev['title']) ?></strong>
                </a>
            <?php else: ?>
                <span></span>
            <?php endif; ?>
            <?php if ($next): ?>
                <a class="course-pager-link course-pager-next" href="<?= e(APP_URL) ?>/user/lesson.php?id=<?= (int) $next['id'] ?>">
                    <span class="muted">Next</span>
                    <strong><?= e($next['title']) ?></strong>
                </a>
            <?php elseif ($moduleQuiz): ?>
                <a class="course-pager-link course-pager-next" href="<?= e(APP_URL) ?>/user/take-quiz.php?id=<?= (int) $moduleQuiz['id'] ?>">
                    <span class="muted">Next step</span>
                    <strong>Take <?= e($moduleQuiz['title']) ?></strong>
                </a>
            <?php endif; ?>
        </nav>
    </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
