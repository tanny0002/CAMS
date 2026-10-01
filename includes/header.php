<?php
declare(strict_types=1);

require_once __DIR__ . '/helpers.php';
start_app_session();

$pageTitle = $pageTitle ?? APP_NAME;
$user = current_user();
$authLayout = !empty($authLayout);
$landingLayout = !empty($landingLayout);
$appShell = $user && !$authLayout && !$landingLayout;

$scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
$isActive = static function (string $needle) use ($scriptName): bool {
    return str_contains($scriptName, $needle);
};

$bodyClasses = [];
if (!empty($bodyClass)) {
    $bodyClasses[] = (string) $bodyClass;
}
if ($appShell) {
    $bodyClasses[] = 'layout-app';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
    <link rel="icon" type="image/png" href="<?= e(APP_URL) ?>/assets/images/cams-shield-logo.png">
    <link rel="apple-touch-icon" href="<?= e(APP_URL) ?>/assets/images/cams-shield-logo.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;1,9..40,400&family=IBM+Plex+Sans:wght@400;500;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= e(APP_URL) ?>/assets/css/style.css">
</head>
<body<?= $bodyClasses ? ' class="' . e(implode(' ', $bodyClasses)) . '"' : '' ?>>
<?php if ($appShell): ?>
<aside class="app-sidebar" data-sidebar>
    <a class="brand app-sidebar-brand" href="<?= e(APP_URL) ?>/user/dashboard.php">
        <img class="brand-logo" src="<?= e(APP_URL) ?>/assets/images/cams-shield-logo.png" alt="CAMS" width="40" height="40">
        <span class="brand-text">CAMS</span>
    </a>
    <nav class="app-sidebar-nav" aria-label="Main">
        <?php if ($user['role'] === 'admin'): ?>
            <a class="<?= $isActive('/admin/dashboard.php') ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/admin/dashboard.php">Admin</a>
            <a class="<?= $isActive('/admin/users.php') ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/admin/users.php">Users</a>
            <a class="<?= $isActive('/admin/invites.php') ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/admin/invites.php">Invite lecturers</a>
            <a class="<?= $isActive('/admin/approvals.php') ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/admin/approvals.php">Approvals</a>
            <a class="<?= $isActive('/admin/modules.php') ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/admin/modules.php">Modules</a>
            <a class="<?= $isActive('/admin/lessons.php') ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/admin/lessons.php">Lessons</a>
            <a class="<?= $isActive('/admin/quizzes.php') ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/admin/quizzes.php">Quizzes</a>
            <a class="<?= $isActive('/admin/tips.php') ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/admin/tips.php">Tips</a>
            <a class="<?= $isActive('/admin/reports.php') ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/admin/reports.php">Reports</a>
        <?php elseif ($user['role'] === 'lecturer'): ?>
            <a class="<?= $isActive('/lecturer/dashboard.php') ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/lecturer/dashboard.php">Dashboard</a>
            <a class="<?= $isActive('/lecturer/invites.php') ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/lecturer/invites.php">Invite students</a>
            <a class="<?= $isActive('/lecturer/approvals.php') ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/lecturer/approvals.php">Approvals</a>
            <a class="<?= $isActive('/admin/reports.php') ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/admin/reports.php">Reports</a>
            <a class="<?= $isActive('/user/lessons.php') ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/user/lessons.php">Courses</a>
        <?php else: ?>
            <a class="<?= $isActive('/user/dashboard.php') ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/user/dashboard.php">Dashboard</a>
            <a class="<?= $isActive('/user/lessons.php') || $isActive('/user/lesson.php') ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/user/lessons.php">Courses</a>
            <a class="<?= $isActive('/user/quizzes.php') || $isActive('/user/take-quiz.php') || $isActive('/user/quiz-result.php') ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/user/quizzes.php">Quizzes &amp; exams</a>
            <a class="<?= $isActive('/user/progress.php') ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/user/progress.php">Progress</a>
            <a class="<?= $isActive('/user/tips.php') ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/user/tips.php">Tips</a>
        <?php endif; ?>
        <a class="<?= $isActive('/user/feedback.php') ? 'is-active' : '' ?>" href="<?= e(APP_URL) ?>/user/feedback.php">Feedback</a>
    </nav>
    <div class="app-sidebar-foot">
        <p class="nav-user"><?= e($user['full_name']) ?></p>
        <a class="btn btn-sm btn-outline" href="<?= e(APP_URL) ?>/auth/logout.php">Logout</a>
    </div>
</aside>
<div class="app-backdrop" data-sidebar-close hidden></div>
<div class="app-frame">
    <?php
    $topIncomplete = 0;
    $topContinueId = null;
    $topContinueTitle = null;
    $topLessonDone = 0;
    $topLessonTotal = 0;
    $topOverallPct = 0;
    if ($user['role'] === 'user' || $user['role'] === 'lecturer') {
        try {
            $uidTop = (int) $user['id'];
            $topLessonTotal = (int) db()->query('SELECT COUNT(*) FROM lessons WHERE is_active = 1')->fetchColumn();
            $td = db()->prepare('SELECT COUNT(*) FROM lesson_progress WHERE user_id = ? AND status = \'completed\'');
            $td->execute([$uidTop]);
            $topLessonDone = (int) $td->fetchColumn();
            $topOverallPct = $topLessonTotal > 0 ? (int) round(($topLessonDone / $topLessonTotal) * 100) : 0;

            $stats = db()->prepare(
                "SELECT m.id, m.title,
                        COUNT(l.id) AS total_l,
                        COUNT(CASE WHEN lp.status = 'completed' THEN 1 END) AS done_l
                 FROM modules m
                 LEFT JOIN lessons l ON l.module_id = m.id AND l.is_active = 1
                 LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.user_id = ?
                 WHERE m.is_active = 1
                 GROUP BY m.id, m.title, m.sort_order
                 ORDER BY m.sort_order"
            );
            $stats->execute([$uidTop]);
            $modRows = $stats->fetchAll();
            foreach ($modRows as $mr) {
                $totalL = (int) $mr['total_l'];
                $doneL = (int) $mr['done_l'];
                if ($totalL > 0 && $doneL < $totalL) {
                    $topIncomplete++;
                    if ($topContinueId === null) {
                        $next = db()->prepare(
                            'SELECT l.id, l.title FROM lessons l
                             LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.user_id = ?
                             WHERE l.module_id = ? AND l.is_active = 1
                               AND (lp.status IS NULL OR lp.status <> \'completed\')
                             ORDER BY l.sort_order, l.id LIMIT 1'
                        );
                        $next->execute([$uidTop, (int) $mr['id']]);
                        $n = $next->fetch();
                        if ($n) {
                            $topContinueId = (int) $n['id'];
                            $topContinueTitle = (string) $n['title'];
                        }
                    }
                }
            }
        } catch (Throwable $e) {
            // top bar extras are optional
        }
    }
    ?>
    <header class="app-topbar">
        <button class="nav-toggle" type="button" aria-label="Open menu" data-nav-toggle>☰</button>
        <div class="app-topbar-main">
            <span class="app-topbar-title"><?= e($pageTitle) ?></span>
        </div>
        <?php if ($topLessonTotal > 0 && ($user['role'] === 'user' || $user['role'] === 'lecturer')): ?>
            <div class="app-topbar-progress" title="<?= $topLessonDone ?>/<?= $topLessonTotal ?> lessons">
                <span class="app-topbar-progress-label">Progress <?= $topOverallPct ?>%</span>
                <div class="progress-bar progress-bar-top" role="progressbar" aria-valuenow="<?= $topOverallPct ?>" aria-valuemin="0" aria-valuemax="100">
                    <span style="width:<?= $topOverallPct ?>%"></span>
                </div>
            </div>
        <?php endif; ?>
        <form class="app-topbar-search" method="get" action="<?= e(APP_URL) ?>/user/lessons.php" role="search">
            <label class="visually-hidden" for="top-q">Search courses</label>
            <input type="search" name="q" id="top-q" placeholder="Search lessons…" value="">
            <button class="btn btn-sm btn-primary" type="submit">Search</button>
        </form>
        <div class="app-topbar-actions">
            <?php if ($topContinueId): ?>
                <a class="btn btn-sm btn-primary" href="<?= e(APP_URL) ?>/user/lesson.php?id=<?= $topContinueId ?>" title="<?= e($topContinueTitle ?? 'Continue') ?>">Continue</a>
            <?php endif; ?>
        </div>
    </header>
    <main class="site-main site-main-app">
        <div class="container">
            <?php foreach (get_flashes() as $flash): ?>
                <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endforeach; ?>
<?php elseif (!$authLayout): ?>
<header class="site-header">
    <div class="container header-inner">
        <a class="brand" href="<?= e(APP_URL) ?>/index.php">
            <img class="brand-logo" src="<?= e(APP_URL) ?>/assets/images/cams-shield-logo.png" alt="CAMS" width="40" height="40">
            <span class="brand-text">Cybersecurity Awareness</span>
        </a>
        <button class="nav-toggle" type="button" aria-label="Toggle menu" data-nav-toggle>☰</button>
        <nav class="site-nav" data-nav>
            <a href="<?= e(APP_URL) ?>/auth/login.php">Login</a>
            <a class="btn btn-sm btn-primary" href="<?= e(APP_URL) ?>/auth/register.php">Register</a>
        </nav>
    </div>
</header>
<main class="site-main">
    <div class="container">
        <?php foreach (get_flashes() as $flash): ?>
            <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
        <?php endforeach; ?>
<?php else: ?>
<main class="site-main site-main-auth">
<?php endif; ?>
