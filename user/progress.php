<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();
require_login();

$uid = (int) current_user()['id'];

$modules = db()->query('SELECT * FROM modules WHERE is_active = 1 ORDER BY sort_order')->fetchAll();
$attempts = db()->prepare(
    'SELECT a.*, q.title AS quiz_title FROM quiz_attempts a
     JOIN quizzes q ON q.id = a.quiz_id
     WHERE a.user_id = ? AND a.submitted_at IS NOT NULL
     ORDER BY a.submitted_at DESC'
);
$attempts->execute([$uid]);
$attemptRows = $attempts->fetchAll();

$certs = db()->prepare(
    'SELECT c.*, m.title AS module_title FROM certificates c
     JOIN modules m ON m.id = c.module_id WHERE c.user_id = ? ORDER BY c.issued_at DESC'
);
$certs->execute([$uid]);
$certRows = $certs->fetchAll();

$pageTitle = 'Progress';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1>Your progress</h1>
        <p class="muted">Lesson completion, quiz history, and certificates.</p>
    </div>
</div>

<section class="panel">
    <h2>Module progress</h2>
    <?php foreach ($modules as $m): ?>
        <?php
        $lt = db()->prepare('SELECT COUNT(*) FROM lessons WHERE module_id = ? AND is_active = 1');
        $lt->execute([(int) $m['id']]);
        $total = (int) $lt->fetchColumn();
        $ld = db()->prepare(
            'SELECT COUNT(*) FROM lesson_progress lp JOIN lessons l ON l.id = lp.lesson_id
             WHERE lp.user_id = ? AND l.module_id = ? AND lp.status = \'completed\''
        );
        $ld->execute([$uid, (int) $m['id']]);
        $done = (int) $ld->fetchColumn();
        $pct = $total > 0 ? (int) round(($done / $total) * 100) : 0;
        ?>
        <div style="margin:1rem 0">
            <div style="display:flex;justify-content:space-between;gap:1rem;flex-wrap:wrap">
                <strong><?= e($m['title']) ?></strong>
                <span class="muted"><?= $done ?>/<?= $total ?> · <?= $pct ?>%</span>
            </div>
            <div class="progress-bar" style="margin-top:0.4rem"><span style="width:<?= $pct ?>%"></span></div>
        </div>
    <?php endforeach; ?>
</section>

<section class="panel">
    <h2>Quiz history</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr><th>Quiz</th><th>Score</th><th>Result</th><th>Submitted</th><th></th></tr>
            </thead>
            <tbody>
            <?php if (!$attemptRows): ?>
                <tr><td colspan="5" class="muted">No quiz attempts yet.</td></tr>
            <?php endif; ?>
            <?php foreach ($attemptRows as $a): ?>
                <tr>
                    <td><?= e($a['quiz_title']) ?></td>
                    <td><?= e((string) $a['score']) ?>%</td>
                    <td><?= (int) $a['passed'] ? '<span class="badge badge-ok">Pass</span>' : '<span class="badge badge-danger">Fail</span>' ?></td>
                    <td><?= e(format_datetime($a['submitted_at'])) ?></td>
                    <td><a href="<?= e(APP_URL) ?>/user/quiz-result.php?attempt=<?= (int) $a['id'] ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>

<section class="panel">
    <h2>Certificates</h2>
    <?php if (!$certRows): ?>
        <p class="muted">Complete all lessons and pass the module quiz to earn a certificate.</p>
    <?php else: ?>
        <ul>
            <?php foreach ($certRows as $c): ?>
                <li>
                    <?= e($c['module_title']) ?> —
                    <a href="<?= e(APP_URL) ?>/user/certificate.php?code=<?= e($c['certificate_code']) ?>"><?= e($c['certificate_code']) ?></a>
                    <span class="muted">(<?= e(format_datetime($c['issued_at'])) ?>)</span>
                </li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
