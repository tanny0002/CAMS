<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();
require_login();

$uid = (int) current_user()['id'];
$moduleFilter = (int) ($_GET['module'] ?? 0);
$q = trim((string) ($_GET['q'] ?? ''));
$allowedModules = user_approved_module_ids($uid);
if ($allowedModules === [] && current_user()['role'] !== 'admin') {
    flash('info', 'You do not have approved course access yet.');
}

$modulesSql = 'SELECT m.*,
    (SELECT COUNT(*) FROM lessons l WHERE l.module_id = m.id AND l.is_active = 1) AS lesson_count
    FROM modules m
    WHERE m.is_active = 1';
$moduleParams = [];
if (current_user()['role'] !== 'admin') {
    if ($allowedModules === []) {
        $modulesSql .= ' AND 1=0';
    } else {
        $modulesSql .= ' AND m.id IN (' . implode(',', array_map('intval', $allowedModules)) . ')';
    }
}
if ($moduleFilter > 0) {
    $modulesSql .= ' AND m.id = ?';
    $moduleParams[] = $moduleFilter;
}
$modulesSql .= ' ORDER BY m.sort_order, m.id';
$modStmt = db()->prepare($modulesSql);
$modStmt->execute($moduleParams);
$courseModules = $modStmt->fetchAll();

$sql = 'SELECT l.*, m.title AS module_title, m.id AS module_id, m.difficulty,
        lp.status AS progress_status
        FROM lessons l
        JOIN modules m ON m.id = l.module_id
        LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.user_id = ?
        WHERE l.is_active = 1 AND m.is_active = 1';
$params = [$uid];
if (current_user()['role'] !== 'admin') {
    if ($allowedModules === []) {
        $sql .= ' AND 1=0';
    } else {
        $sql .= ' AND m.id IN (' . implode(',', array_map('intval', $allowedModules)) . ')';
    }
}
if ($moduleFilter > 0) {
    $sql .= ' AND l.module_id = ?';
    $params[] = $moduleFilter;
}
if ($q !== '') {
    $sql .= ' AND (l.title LIKE ? OR l.summary LIKE ? OR l.content LIKE ? OR m.title LIKE ?)';
    $like = '%' . $q . '%';
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}
$sql .= ' ORDER BY m.sort_order, l.sort_order, l.id';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$lessons = $stmt->fetchAll();

$byModule = [];
foreach ($lessons as $lesson) {
    $mid = (int) $lesson['module_id'];
    $byModule[$mid][] = $lesson;
}

$allModules = db()->query('SELECT id, title FROM modules WHERE is_active = 1 ORDER BY sort_order')->fetchAll();

$pageTitle = 'Courses';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1>Courses &amp; lessons</h1>
        <p class="muted">Browse modules like a course tree, then open lessons with a sidebar and on-page outline.</p>
    </div>
</div>

<form class="filters panel" method="get" action="">
    <div class="form-group">
        <label for="module">Course / module</label>
        <select name="module" id="module">
            <option value="0">All courses</option>
            <?php foreach ($allModules as $m): ?>
                <option value="<?= (int) $m['id'] ?>" <?= $moduleFilter === (int) $m['id'] ? 'selected' : '' ?>>
                    <?= e($m['title']) ?>
                </option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="q">Search</label>
        <input type="search" name="q" id="q" value="<?= e($q) ?>" placeholder="Search topics…">
    </div>
    <div class="form-group" style="align-self:end">
        <button class="btn btn-primary" type="submit">Filter</button>
    </div>
</form>

<?php if (!$byModule): ?>
    <div class="panel"><p class="muted">No lessons found.</p></div>
<?php else: ?>
    <div class="course-catalog">
        <?php foreach ($courseModules as $mod): ?>
            <?php
            $mid = (int) $mod['id'];
            if (!isset($byModule[$mid])) {
                continue;
            }
            $modLessons = $byModule[$mid];
            $done = count(array_filter($modLessons, static fn($l) => ($l['progress_status'] ?? '') === 'completed'));
            $total = count($modLessons);
            $firstId = (int) $modLessons[0]['id'];
            $continue = null;
            foreach ($modLessons as $ml) {
                if (($ml['progress_status'] ?? '') !== 'completed') {
                    $continue = (int) $ml['id'];
                    break;
                }
            }
            $openId = $continue ?? $firstId;
            $pct = $total ? (int) round(($done / $total) * 100) : 0;
            ?>
            <section class="panel course-card">
                <div class="course-card-head">
                    <div style="flex:1;min-width:14rem">
                        <span class="badge"><?= e(ucfirst((string) $mod['difficulty'])) ?></span>
                        <?php if ($pct >= 100): ?><span class="badge badge-ok">Complete</span><?php endif; ?>
                        <h2><?= e($mod['title']) ?></h2>
                        <p class="muted"><?= e($mod['description'] ?? '') ?></p>
                        <div class="progress-overview-head" style="margin-top:0.85rem;margin-bottom:0.45rem">
                            <p class="course-card-meta muted" style="margin:0"><?= $done ?>/<?= $total ?> lessons</p>
                            <span class="progress-pct" style="font-size:1.15rem"><?= $pct ?>%</span>
                        </div>
                        <div class="progress-bar progress-bar-lg" role="progressbar" aria-valuenow="<?= $pct ?>" aria-valuemin="0" aria-valuemax="100">
                            <span style="width:<?= $pct ?>%"></span>
                        </div>
                    </div>
                    <a class="btn btn-primary" href="<?= e(APP_URL) ?>/user/lesson.php?id=<?= $openId ?>">
                        <?= $done > 0 && $done < $total ? 'Continue' : ($done === $total ? 'Review' : 'Start course') ?>
                    </a>
                </div>
                <ol class="course-syllabus">
                    <?php foreach ($modLessons as $i => $lesson): ?>
                        <li class="<?= ($lesson['progress_status'] ?? '') === 'completed' ? 'is-done' : '' ?>">
                            <a href="<?= e(APP_URL) ?>/user/lesson.php?id=<?= (int) $lesson['id'] ?>">
                                <span class="course-syllabus-index"><?= $i + 1 ?></span>
                                <span class="course-syllabus-body">
                                    <strong><?= e($lesson['title']) ?></strong>
                                    <?php if (!empty($lesson['summary'])): ?>
                                        <span class="muted"><?= e($lesson['summary']) ?></span>
                                    <?php endif; ?>
                                </span>
                                <?php if (($lesson['progress_status'] ?? '') === 'completed'): ?>
                                    <span class="badge badge-ok">Done</span>
                                <?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ol>
            </section>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
