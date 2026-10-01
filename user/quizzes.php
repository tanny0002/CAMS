<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();
require_login();

$uid = (int) current_user()['id'];
$allowedModules = user_approved_module_ids($uid);

$quizzesSql = "SELECT q.*, m.title AS module_title
     FROM quizzes q
     JOIN modules m ON m.id = q.module_id
     WHERE q.is_active = 1 AND q.quiz_type IN ('practice','module','exam')";
if (current_user()['role'] !== 'admin') {
    if ($allowedModules === []) {
        $quizzesSql .= ' AND 1=0';
    } else {
        $quizzesSql .= ' AND q.module_id IN (' . implode(',', array_map('intval', $allowedModules)) . ')';
    }
}
$quizzesSql .= " ORDER BY CASE q.quiz_type
        WHEN 'practice' THEN 1 WHEN 'module' THEN 2 WHEN 'exam' THEN 3 ELSE 4 END,
        m.sort_order, q.id";
$quizzes = db()->query($quizzesSql)->fetchAll();

$bankByModule = [];
$bankRows = db()->query(
    "SELECT qb.module_id, COUNT(*) AS bank_count
     FROM questions qs
     JOIN quizzes qb ON qb.id = qs.quiz_id
     WHERE qb.quiz_type = 'bank' AND qs.is_active = 1
     GROUP BY qb.module_id"
)->fetchAll();
foreach ($bankRows as $row) {
    $bankByModule[(int) $row['module_id']] = (int) $row['bank_count'];
}

$attemptStats = [];
if ($quizzes !== []) {
    $quizIds = array_map(static fn ($q) => (int) $q['id'], $quizzes);
    $in = implode(',', $quizIds);
    if (db_is_pgsql()) {
        $stats = db()->prepare(
            "SELECT quiz_id,
                    COUNT(*) FILTER (WHERE submitted_at IS NOT NULL) AS attempts_used,
                    MAX(score) FILTER (WHERE submitted_at IS NOT NULL) AS best_score,
                    COUNT(*) FILTER (WHERE passed = 1) AS pass_count
             FROM quiz_attempts
             WHERE user_id = ? AND quiz_id IN ($in)
             GROUP BY quiz_id"
        );
    } else {
        $stats = db()->prepare(
            "SELECT quiz_id,
                    SUM(CASE WHEN submitted_at IS NOT NULL THEN 1 ELSE 0 END) AS attempts_used,
                    MAX(CASE WHEN submitted_at IS NOT NULL THEN score ELSE NULL END) AS best_score,
                    SUM(CASE WHEN passed = 1 THEN 1 ELSE 0 END) AS pass_count
             FROM quiz_attempts
             WHERE user_id = ? AND quiz_id IN ($in)
             GROUP BY quiz_id"
        );
    }
    $stats->execute([$uid]);
    foreach ($stats->fetchAll() as $row) {
        $attemptStats[(int) $row['quiz_id']] = $row;
    }
}

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
                $qid = (int) $quiz['id'];
                $stat = $attemptStats[$qid] ?? null;
                $attemptsUsed = (int) ($stat['attempts_used'] ?? 0);
                $bestScore = $stat['best_score'] ?? null;
                $hasPass = (int) ($stat['pass_count'] ?? 0) > 0;
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
                        $bank = $bankByModule[(int) $quiz['module_id']] ?? 0;
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
                        <a class="btn btn-sm btn-primary" href="<?= e(APP_URL) ?>/user/take-quiz.php?id=<?= $qid ?>">
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
