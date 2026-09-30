<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();
require_login();

$user = current_user();
$uid = (int) $user['id'];

$modules = db()->query('SELECT * FROM modules WHERE is_active = 1 ORDER BY sort_order')->fetchAll();

$lessonTotal = (int) db()->query('SELECT COUNT(*) FROM lessons WHERE is_active = 1')->fetchColumn();
$stmt = db()->prepare('SELECT COUNT(*) FROM lesson_progress WHERE user_id = ? AND status = \'completed\'');
$stmt->execute([$uid]);
$lessonDone = (int) $stmt->fetchColumn();
$overallPct = $lessonTotal > 0 ? (int) round(($lessonDone / $lessonTotal) * 100) : 0;

$attempts = db()->prepare('SELECT COUNT(*) FROM quiz_attempts WHERE user_id = ? AND submitted_at IS NOT NULL');
$attempts->execute([$uid]);
$attemptCount = (int) $attempts->fetchColumn();

$certs = db()->prepare('SELECT COUNT(*) FROM certificates WHERE user_id = ?');
$certs->execute([$uid]);
$certCount = (int) $certs->fetchColumn();

$moduleProgress = [];
$incompleteModules = [];
$continueId = null;
foreach ($modules as $m) {
    $lt = db()->prepare('SELECT COUNT(*) FROM lessons WHERE module_id = ? AND is_active = 1');
    $lt->execute([(int) $m['id']]);
    $total = (int) $lt->fetchColumn();
    $ld = db()->prepare(
        'SELECT COUNT(*) FROM lesson_progress lp JOIN lessons l ON l.id = lp.lesson_id
         WHERE lp.user_id = ? AND l.module_id = ? AND lp.status = \'completed\' AND l.is_active = 1'
    );
    $ld->execute([$uid, (int) $m['id']]);
    $done = (int) $ld->fetchColumn();
    $pct = $total > 0 ? (int) round(($done / $total) * 100) : 0;
    $row = [
        'id' => (int) $m['id'],
        'title' => $m['title'],
        'difficulty' => $m['difficulty'],
        'description' => $m['description'],
        'done' => $done,
        'total' => $total,
        'pct' => $pct,
    ];
    $moduleProgress[] = $row;
    if ($total > 0 && $done < $total) {
        $incompleteModules[] = $row;
        if ($continueId === null) {
            $next = db()->prepare(
                'SELECT l.id FROM lessons l
                 LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.user_id = ?
                 WHERE l.module_id = ? AND l.is_active = 1
                   AND (lp.status IS NULL OR lp.status <> \'completed\')
                 ORDER BY l.sort_order, l.id LIMIT 1'
            );
            $next->execute([$uid, (int) $m['id']]);
            $continueId = (int) ($next->fetchColumn() ?: 0) ?: null;
        }
    }
}

$pageTitle = 'Dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1>Hello, <?= e($user['full_name']) ?></h1>
        <p class="muted">Track your cybersecurity awareness progress.</p>
    </div>
    <?php if ($continueId): ?>
        <a class="btn btn-primary" href="<?= e(APP_URL) ?>/user/lesson.php?id=<?= $continueId ?>">Continue learning</a>
    <?php endif; ?>
</div>

<section class="panel progress-overview">
    <div class="progress-overview-head">
        <div>
            <h2 style="margin:0">Overall learning progress</h2>
            <p class="muted" style="margin:0.35rem 0 0"><?= $lessonDone ?> of <?= $lessonTotal ?> lessons completed</p>
        </div>
        <span class="progress-pct"><?= $overallPct ?>%</span>
    </div>
    <div class="progress-bar progress-bar-lg" role="progressbar" aria-valuenow="<?= $overallPct ?>" aria-valuemin="0" aria-valuemax="100">
        <span style="width:<?= $overallPct ?>%"></span>
    </div>
</section>

<?php if ($incompleteModules): ?>
<section class="panel reminder-panel">
    <div class="reminder-head">
        <div>
            <h2 style="margin:0">Continue where you left off</h2>
            <p class="muted" style="margin:0.35rem 0 0"><?= count($incompleteModules) ?> course<?= count($incompleteModules) === 1 ? '' : 's' ?> still in progress</p>
        </div>
        <a class="btn btn-sm btn-outline" href="<?= e(APP_URL) ?>/user/lessons.php">All courses</a>
    </div>
    <ul class="reminder-list">
        <?php foreach (array_slice($incompleteModules, 0, 6) as $item): ?>
            <li>
                <div class="reminder-list-main">
                    <strong><?= e($item['title']) ?></strong>
                    <span class="muted"><?= $item['done'] ?>/<?= $item['total'] ?> lessons · <?= $item['pct'] ?>%</span>
                    <div class="progress-bar" aria-hidden="true"><span style="width:<?= $item['pct'] ?>%"></span></div>
                </div>
                <a class="btn btn-sm btn-primary" href="<?= e(APP_URL) ?>/user/lessons.php?module=<?= $item['id'] ?>">Open</a>
            </li>
        <?php endforeach; ?>
    </ul>
    <?php if (count($incompleteModules) > 6): ?>
        <p class="muted" style="margin:0.75rem 0 0">+<?= count($incompleteModules) - 6 ?> more on the Courses page.</p>
    <?php endif; ?>
</section>
<?php endif; ?>

<section class="grid grid-3">
    <article class="card">
        <div class="stat"><?= $lessonDone ?>/<?= $lessonTotal ?></div>
        <p>Lessons completed</p>
    </article>
    <article class="card">
        <div class="stat"><?= $attemptCount ?></div>
        <p>Quiz attempts</p>
    </article>
    <article class="card">
        <div class="stat"><?= $certCount ?></div>
        <p>Certificates earned</p>
    </article>
</section>

<section class="panel" style="margin-top:1.25rem">
    <h2>All modules</h2>
    <div class="grid grid-2" style="margin-top:1rem">
        <?php foreach ($moduleProgress as $m): ?>
            <article class="card">
                <span class="badge"><?= e(ucfirst((string) $m['difficulty'])) ?></span>
                <?php if ($m['pct'] >= 100): ?><span class="badge badge-ok">Complete</span><?php endif; ?>
                <h3 style="margin-top:0.5rem"><?= e($m['title']) ?></h3>
                <p><?= e($m['description'] ?? '') ?></p>
                <div class="progress-bar progress-bar-md" style="margin:0.75rem 0" title="<?= $m['pct'] ?>%">
                    <span style="width:<?= $m['pct'] ?>%"></span>
                </div>
                <p class="muted"><?= $m['done'] ?>/<?= $m['total'] ?> lessons · <?= $m['pct'] ?>%</p>
                <a class="btn btn-sm btn-primary" href="<?= e(APP_URL) ?>/user/lessons.php?module=<?= $m['id'] ?>">Open course</a>
            </article>
        <?php endforeach; ?>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
