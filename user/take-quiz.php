<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();
require_login();

$uid = (int) current_user()['id'];
$quizId = (int) ($_GET['id'] ?? $_POST['quiz_id'] ?? 0);

ensure_quiz_bank_schema();

$quizStmt = db()->prepare(
    "SELECT q.*, m.title AS module_title FROM quizzes q
     JOIN modules m ON m.id = q.module_id
     WHERE q.id = ? AND q.is_active = 1 AND q.quiz_type IN ('practice','module','exam')"
);
$quizStmt->execute([$quizId]);
$quiz = $quizStmt->fetch();
if (!$quiz) {
    flash('error', 'Quiz not found.');
    redirect('user/quizzes.php');
}

$usedStmt = db()->prepare('SELECT COUNT(*) FROM quiz_attempts WHERE user_id = ? AND quiz_id = ? AND submitted_at IS NOT NULL');
$usedStmt->execute([$uid, $quizId]);
$attemptsUsed = (int) $usedStmt->fetchColumn();
if ($attemptsUsed >= (int) $quiz['max_attempts']) {
    flash('error', 'You have no attempts remaining for this quiz.');
    redirect('user/quizzes.php');
}

$reshuffle = isset($_GET['reshuffle']) && $_SERVER['REQUEST_METHOD'] === 'GET';
$questions = quiz_draw_questions($uid, $quiz, $reshuffle);
if (!$questions) {
    flash('error', 'This quiz has no questions yet. Ask an admin to seed the question bank.');
    redirect('user/quizzes.php');
}

$bankCountStmt = db()->prepare(
    "SELECT COUNT(*) FROM questions qs
     JOIN quizzes qb ON qb.id = qs.quiz_id
     WHERE qb.module_id = ? AND qb.quiz_type = 'bank' AND qs.is_active = 1"
);
$bankCountStmt->execute([(int) $quiz['module_id']]);
$bankSize = (int) $bankCountStmt->fetchColumn();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    // Always score against the locked shuffled draw for this attempt
    $questions = quiz_draw_questions($uid, $quiz, false);
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $attemptNo = $attemptsUsed + 1;
        $ins = $pdo->prepare(
            'INSERT INTO quiz_attempts (user_id, quiz_id, attempt_number, total_questions) VALUES (?, ?, ?, ?)'
        );
        $ins->execute([$uid, $quizId, $attemptNo, count($questions)]);
        $attemptId = (int) $pdo->lastInsertId();

        $correct = 0;
        $ansIns = $pdo->prepare(
            'INSERT INTO quiz_answers (attempt_id, question_id, selected_option, is_correct) VALUES (?, ?, ?, ?)'
        );
        foreach ($questions as $question) {
            $qid = (int) $question['id'];
            $selected = strtoupper((string) ($_POST['q_' . $qid] ?? ''));
            if (!in_array($selected, ['A', 'B', 'C', 'D'], true)) {
                throw new RuntimeException('Please answer all questions.');
            }
            $isCorrect = $selected === $question['correct_option'] ? 1 : 0;
            if ($isCorrect) {
                $correct++;
            }
            $ansIns->execute([$attemptId, $qid, $selected, $isCorrect]);
        }

        $total = count($questions);
        $score = round(($correct / $total) * 100, 2);
        $passed = $score >= (int) $quiz['pass_mark'] ? 1 : 0;
        $pdo->prepare(
            'UPDATE quiz_attempts SET score = ?, correct_answers = ?, passed = ?, submitted_at = NOW() WHERE id = ?'
        )->execute([$score, $correct, $passed, $attemptId]);

        $pdo->commit();
        quiz_clear_draw($uid, $quizId);
        log_activity($uid, 'quiz_submitted', "Quiz #$quizId attempt #$attemptNo score $score");
        if ($passed) {
            issue_certificate_if_eligible($uid, (int) $quiz['module_id']);
        }
        redirect('user/quiz-result.php?attempt=' . $attemptId);
    } catch (Throwable $e) {
        $pdo->rollBack();
        flash('error', $e->getMessage());
        redirect('user/take-quiz.php?id=' . $quizId);
    }
}

$typeLabel = match ($quiz['quiz_type'] ?? 'module') {
    'practice' => 'Practice',
    'exam' => 'Exam',
    default => 'Module quiz',
};

$pageTitle = $quiz['title'];
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <p class="muted">
            <?= e($quiz['module_title']) ?> · <?= e($typeLabel) ?> ·
            Attempt <?= $attemptsUsed + 1 ?> of <?= (int) $quiz['max_attempts'] ?>
        </p>
        <h1><?= e($quiz['title']) ?></h1>
        <p class="muted">
            Pass mark: <?= (int) $quiz['pass_mark'] ?>% ·
            This attempt: <?= count($questions) ?> shuffled questions
            <?php if ($bankSize > 0): ?>
                drawn from a bank of <?= number_format($bankSize) ?>
            <?php endif; ?>
        </p>
    </div>
    <a class="btn btn-outline btn-sm" href="?id=<?= $quizId ?>&amp;reshuffle=1">Shuffle new set</a>
</div>

<div class="panel quiz-progress-sticky" data-quiz-progress>
    <div class="quiz-progress-head">
        <strong>Answer progress</strong>
        <span class="muted"><span data-quiz-answered>0</span>/<?= count($questions) ?> answered</span>
    </div>
    <div class="progress-bar progress-bar-lg" role="progressbar" aria-valuemin="0" aria-valuemax="100">
        <span data-quiz-bar style="width:0%"></span>
    </div>
</div>

<form method="post" action="" class="panel" data-quiz-form>
    <?= csrf_field() ?>
    <input type="hidden" name="quiz_id" value="<?= $quizId ?>">
    <?php foreach ($questions as $i => $question): ?>
        <fieldset class="quiz-fieldset" style="border:0;margin:0 0 1.5rem;padding:0">
            <legend><strong><?= $i + 1 ?>. <?= e($question['question_text']) ?></strong></legend>
            <?php foreach (['A' => 'option_a', 'B' => 'option_b', 'C' => 'option_c', 'D' => 'option_d'] as $letter => $col): ?>
                <label class="quiz-option">
                    <input type="radio" name="q_<?= (int) $question['id'] ?>" value="<?= $letter ?>" required>
                    <?= $letter ?>. <?= e($question[$col]) ?>
                </label>
            <?php endforeach; ?>
        </fieldset>
    <?php endforeach; ?>
    <button class="btn btn-primary" type="submit">Submit <?= $quiz['quiz_type'] === 'exam' ? 'exam' : 'quiz' ?></button>
</form>

<script>
(() => {
  const form = document.querySelector('[data-quiz-form]');
  const answeredEl = document.querySelector('[data-quiz-answered]');
  const bar = document.querySelector('[data-quiz-bar]');
  if (!form || !answeredEl || !bar) return;
  const names = new Set([...form.querySelectorAll('input[type=radio]')].map((i) => i.name));
  const total = names.size || 1;
  const update = () => {
    let n = 0;
    names.forEach((name) => {
      if (form.querySelector(`input[name="${name}"]:checked`)) n += 1;
    });
    const pct = Math.round((n / total) * 100);
    answeredEl.textContent = String(n);
    bar.style.width = pct + '%';
  };
  form.addEventListener('change', update);
  update();
})();
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
