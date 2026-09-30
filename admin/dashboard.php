<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();
require_role(['admin']);

$users = (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn();
$lessons = (int) db()->query('SELECT COUNT(*) FROM lessons WHERE is_active = 1')->fetchColumn();
$attempts = (int) db()->query('SELECT COUNT(*) FROM quiz_attempts WHERE submitted_at IS NOT NULL')->fetchColumn();
$feedback = (int) db()->query('SELECT COUNT(*) FROM feedback')->fetchColumn();

$recent = db()->query(
    'SELECT a.*, u.full_name, q.title AS quiz_title
     FROM quiz_attempts a
     JOIN users u ON u.id = a.user_id
     JOIN quizzes q ON q.id = a.quiz_id
     WHERE a.submitted_at IS NOT NULL
     ORDER BY a.submitted_at DESC LIMIT 8'
)->fetchAll();

$pageTitle = 'Admin dashboard';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1>Admin dashboard</h1>
        <p class="muted">Manage awareness content, users, and reports.</p>
    </div>
</div>

<section class="grid grid-3">
    <article class="card"><div class="stat"><?= $users ?></div><p>Users</p></article>
    <article class="card"><div class="stat"><?= $lessons ?></div><p>Active lessons</p></article>
    <article class="card"><div class="stat"><?= $attempts ?></div><p>Quiz submissions</p></article>
</section>

<section class="grid grid-2" style="margin-top:1.25rem">
    <a class="card" href="<?= e(APP_URL) ?>/admin/users.php"><h3>Users</h3><p>Activate, deactivate, and change roles.</p></a>
    <a class="card" href="<?= e(APP_URL) ?>/admin/modules.php"><h3>Modules</h3><p>Create courses that group lessons and quizzes.</p></a>
    <a class="card" href="<?= e(APP_URL) ?>/admin/lessons.php"><h3>Lessons</h3><p>Add article-style learning content (HTML).</p></a>
    <a class="card" href="<?= e(APP_URL) ?>/admin/quizzes.php"><h3>Quizzes</h3><p>Manage quizzes and questions.</p></a>
    <a class="card" href="<?= e(APP_URL) ?>/admin/tips.php"><h3>Security tips</h3><p>Publish short awareness tips.</p></a>
    <a class="card" href="<?= e(APP_URL) ?>/admin/reports.php"><h3>Reports</h3><p>Filter results and export CSV.</p></a>
</section>
<article class="card" style="margin-top:1rem"><div class="stat" style="font-size:1.4rem"><?= $feedback ?></div><p>Feedback responses</p></article>

<section class="panel" style="margin-top:1.25rem">
    <h2>Recent quiz submissions</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>User</th><th>Quiz</th><th>Score</th><th>Result</th><th>When</th></tr></thead>
            <tbody>
            <?php foreach ($recent as $r): ?>
                <tr>
                    <td><?= e($r['full_name']) ?></td>
                    <td><?= e($r['quiz_title']) ?></td>
                    <td><?= e((string) $r['score']) ?>%</td>
                    <td><?= (int) $r['passed'] ? 'Pass' : 'Fail' ?></td>
                    <td><?= e(format_datetime($r['submitted_at'])) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
