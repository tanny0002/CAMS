<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();
require_login();

$uid = (int) current_user()['id'];
$attemptId = (int) ($_GET['attempt'] ?? 0);

$stmt = db()->prepare(
    'SELECT a.*, q.title AS quiz_title, q.pass_mark, q.module_id, m.title AS module_title
     FROM quiz_attempts a
     JOIN quizzes q ON q.id = a.quiz_id
     JOIN modules m ON m.id = q.module_id
     WHERE a.id = ? AND a.user_id = ?'
);
$stmt->execute([$attemptId, $uid]);
$attempt = $stmt->fetch();
if (!$attempt) {
    flash('error', 'Result not found.');
    redirect('user/quizzes.php');
}

$answers = db()->prepare(
    'SELECT qa.*, qs.question_text, qs.option_a, qs.option_b, qs.option_c, qs.option_d,
            qs.correct_option, qs.explanation
     FROM quiz_answers qa
     JOIN questions qs ON qs.id = qa.question_id
     WHERE qa.attempt_id = ?
     ORDER BY qs.sort_order, qs.id'
);
$answers->execute([$attemptId]);
$rows = $answers->fetchAll();

$cert = db()->prepare('SELECT certificate_code FROM certificates WHERE user_id = ? AND module_id = ?');
$cert->execute([$uid, (int) $attempt['module_id']]);
$certCode = $cert->fetchColumn();

$pageTitle = 'Quiz result';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1>Quiz result</h1>
        <p class="muted"><?= e($attempt['quiz_title']) ?> · <?= e($attempt['module_title']) ?></p>
    </div>
</div>

<div class="panel">
    <p class="stat"><?= e((string) $attempt['score']) ?>%</p>
    <p>
        <?= (int) $attempt['correct_answers'] ?>/<?= (int) $attempt['total_questions'] ?> correct ·
        <?php if ((int) $attempt['passed']): ?>
            <span class="badge badge-ok">Passed</span>
        <?php else: ?>
            <span class="badge badge-danger">Not passed</span>
            <span class="muted">(need <?= (int) $attempt['pass_mark'] ?>%)</span>
        <?php endif; ?>
    </p>
    <?php if ($certCode): ?>
        <p class="alert alert-success" style="margin-top:1rem">
            Module certificate: <strong><?= e($certCode) ?></strong>
            — <a href="<?= e(APP_URL) ?>/user/certificate.php?code=<?= e($certCode) ?>">View certificate</a>
        </p>
    <?php endif; ?>
</div>

<section class="panel">
    <h2>Answer feedback</h2>
    <?php foreach ($rows as $i => $row): ?>
        <div style="margin:1rem 0;padding-bottom:1rem;border-bottom:1px solid var(--border)">
            <p><strong><?= $i + 1 ?>. <?= e($row['question_text']) ?></strong></p>
            <p>
                Your answer: <?= e($row['selected_option']) ?>
                <?php if ((int) $row['is_correct']): ?>
                    <span class="badge badge-ok">Correct</span>
                <?php else: ?>
                    <span class="badge badge-danger">Incorrect</span>
                    · Correct answer:
                    <?php
                    $optKey = 'option_' . strtolower((string) $row['correct_option']);
                    echo e((string) ($row[$optKey] ?? $row['correct_option']));
                    ?>
                <?php endif; ?>
            </p>
            <?php if ($row['explanation']): ?>
                <p class="muted"><?= e($row['explanation']) ?></p>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <a class="btn btn-outline" href="<?= e(APP_URL) ?>/user/quizzes.php">Back to quizzes</a>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
