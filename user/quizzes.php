<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();
require_login();

$uid = (int) current_user()['id'];

$quizzes = db()->query(
    "SELECT q.*, m.title AS module_title,
     (SELECT COUNT(*) FROM questions qs WHERE qs.quiz_id = q.id AND qs.is_active = 1) AS question_count,
     (SELECT COUNT(*) FROM questions qs
        JOIN quizzes qb ON qb.id = qs.quiz_id
        WHERE qb.module_id = q.module_id AND qb.quiz_type = 'bank' AND qs.is_active = 1) AS bank_count
     FROM quizzes q
     JOIN modules m ON m.id = q.module_id
     WHERE q.is_active = 1 AND q.quiz_type IN ('practice','module','exam')
     ORDER BY FIELD(q.quiz_type, 'practice', 'module', 'exam'), m.sort_order, q.id"
)->fetchAll();

$groups = [
    'practice' => ['label' => 'Practice drills', 'blurb' => 'Unlimited-style practice to learn without pressure. Lower pass mark, more attempts.'],
    'module' => ['label' => 'Module quizzes', 'blurb' => 'End-of-module assessments. Needed for module completion certificates.'],
    'exam' => ['label' => 'Exams', 'blurb' => 'Broader formal tests with higher pass marks and fewer attempts.'],
];

$byType = ['practice' => [], 'module' => [], 'exam' => []];
foreach ($quizzes as $quiz) {
    $type = $quiz['quiz_type'] ?? 'module';
    if (!isset($byType[$type])) {
        $type = 'module';
    }
    $byType[$type][] = $quiz;
}

$pageTitle = 'Quizzes & exams';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1>Practice, quizzes &amp; exams</h1>
        <p class="muted">Drill with practice sets, pass module quizzes, then take formal exams. Each attempt draws a shuffled set from a large topic bank.</p>
    </div>
</div>

<?php foreach ($groups as $type => $meta): ?>
    <?php if (!$byType[$type]) {
        continue;
    } ?>
    <section class="panel" style="margin-bottom:1.25rem">
        <h2 style="margin-top:0"><?= e($meta['label']) ?></h2>
        <p class="muted"><?= e($meta['blurb']) ?></p>
        <div class="grid" style="margin-top:1rem">
            <?php foreach ($byType[$type] as $quiz): ?>
                <?php
                $used = db()->prepare('SELECT COUNT(*) FROM quiz_attempts WHERE user_id = ? AND quiz_id = ? AND submitted_at IS NOT NULL');
                $used->execute([$uid, (int) $quiz['id']]);
                $attemptsUsed = (int) $used->fetchColumn();
                $best = db()->prepare('SELECT MAX(score) FROM quiz_attempts WHERE user_id = ? AND quiz_id = ? AND submitted_at IS NOT NULL');
                $best->execute([$uid, (int) $quiz['id']]);
                $bestScore = $best->fetchColumn();
                $passed = db()->prepare('SELECT COUNT(*) FROM quiz_attempts WHERE user_id = ? AND quiz_id = ? AND passed = 1');
                $passed->execute([$uid, (int) $quiz['id']]);
                $hasPass = (int) $passed->fetchColumn() > 0;
                $remaining = max(0, (int) $quiz['max_attempts'] - $attemptsUsed);
                $typeLabel = match ($quiz['quiz_type'] ?? 'module') {
                    'practice' => 'Practice',
                    'exam' => 'Exam',
                    default => 'Module quiz',
                };
                ?>
                <article class="card quiz-card">
                    <div class="quiz-card-body">
                        <span class="badge"><?= e($quiz['module_title']) ?></span>
                        <span class="badge"><?= e($typeLabel) ?></span>
                        <?php if ($hasPass): ?><span class="badge badge-ok">Passed</span><?php endif; ?>
                        <h3 style="margin-top:0.45rem"><?= e($quiz['title']) ?></h3>
                        <p><?= e($quiz['description'] ?? '') ?></p>
                        <?php
                        $draw = (int) ($quiz['questions_to_draw'] ?? 0);
                        if ($draw < 1) {
                            $draw = quiz_default_draw_count((string) ($quiz['quiz_type'] ?? 'module'));
                        }
                        $bank = (int) ($quiz['bank_count'] ?? 0);
                        $bestNum = $bestScore !== null ? (float) $bestScore : 0.0;
                        $passMark = (int) $quiz['pass_mark'];
                        ?>
                        <div class="quiz-score-progress">
                            <div class="quiz-score-progress-head">
                                <span class="muted">Best score</span>
                                <strong><?= $bestScore !== null ? e((string) $bestScore) . '%' : 'Not attempted' ?></strong>
                            </div>
                            <div class="progress-bar progress-bar-md" aria-hidden="true">
                                <span style="width:<?= min(100, (int) round($bestNum)) ?>%"></span>
                            </div>
                            <p class="muted" style="margin:0.4rem 0 0;font-size:0.85rem">
                                Pass mark <?= $passMark ?>% · Attempts <?= $attemptsUsed ?>/<?= (int) $quiz['max_attempts'] ?> ·
                                <?= $draw ?> questions/attempt<?= $bank > 0 ? ' · bank ' . number_format($bank) : '' ?>
                            </p>
                        </div>
                    </div>
                    <?php if ($remaining > 0): ?>
                        <a class="btn btn-sm btn-primary" href="<?= e(APP_URL) ?>/user/take-quiz.php?id=<?= (int) $quiz['id'] ?>">
                            <?= $type === 'exam' ? 'Start exam' : ($type === 'practice' ? 'Practice' : 'Start quiz') ?>
                        </a>
                    <?php else: ?>
                        <span class="badge badge-warn">No attempts left</span>
                    <?php endif; ?>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endforeach; ?>

<?php if (!$quizzes): ?>
    <div class="panel"><p class="muted">No quizzes available yet.</p></div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
