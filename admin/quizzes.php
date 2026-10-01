<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add_question') {
        $quizId = (int) ($_POST['quiz_id'] ?? 0);
        $text = trim((string) ($_POST['question_text'] ?? ''));
        $a = trim((string) ($_POST['option_a'] ?? ''));
        $b = trim((string) ($_POST['option_b'] ?? ''));
        $c = trim((string) ($_POST['option_c'] ?? ''));
        $d = trim((string) ($_POST['option_d'] ?? ''));
        $correct = strtoupper((string) ($_POST['correct_option'] ?? ''));
        $expl = trim((string) ($_POST['explanation'] ?? ''));
        if ($text === '' || !in_array($correct, ['A', 'B', 'C', 'D'], true)) {
            flash('error', 'Question text and correct option are required.');
        } else {
            db()->prepare(
                'INSERT INTO questions (quiz_id, question_text, option_a, option_b, option_c, option_d, correct_option, explanation)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
            )->execute([$quizId, $text, $a, $b, $c, $d, $correct, $expl]);
            audit_log('add_question', 'question', (int) db()->lastInsertId());
            flash('success', 'Question added.');
        }
        redirect('admin/quizzes.php?quiz=' . $quizId);
    }

    if ($action === 'deactivate_question') {
        $qid = (int) ($_POST['question_id'] ?? 0);
        $quizId = (int) ($_POST['quiz_id'] ?? 0);
        db()->prepare('UPDATE questions SET is_active = 0 WHERE id = ?')->execute([$qid]);
        audit_log('deactivate_question', 'question', $qid);
        flash('success', 'Question deactivated.');
        redirect('admin/quizzes.php?quiz=' . $quizId);
    }

    if ($action === 'update_quiz') {
        $quizId = (int) ($_POST['quiz_id'] ?? 0);
        $pass = max(1, min(100, (int) ($_POST['pass_mark'] ?? 70)));
        $max = max(1, min(20, (int) ($_POST['max_attempts'] ?? 3)));
        db()->prepare('UPDATE quizzes SET pass_mark = ?, max_attempts = ? WHERE id = ?')->execute([$pass, $max, $quizId]);
        audit_log('update_quiz', 'quiz', $quizId, "pass=$pass max=$max");
        flash('success', 'Quiz settings updated.');
        redirect('admin/quizzes.php?quiz=' . $quizId);
    }
}

$quizzes = db()->query(
    'SELECT q.*, m.title AS module_title,
     (SELECT COUNT(*) FROM questions qs WHERE qs.quiz_id = q.id AND qs.is_active = 1) AS question_count
     FROM quizzes q JOIN modules m ON m.id = q.module_id
     WHERE q.quiz_type <> \'bank\'
     ORDER BY m.sort_order, CASE q.quiz_type WHEN \'practice\' THEN 1 WHEN \'module\' THEN 2 WHEN \'exam\' THEN 3 ELSE 4 END, q.id'
)->fetchAll();

$selectedQuizId = (int) ($_GET['quiz'] ?? ($quizzes[0]['id'] ?? 0));
$selected = null;
$questions = [];
foreach ($quizzes as $q) {
    if ((int) $q['id'] === $selectedQuizId) {
        $selected = $q;
        break;
    }
}
if ($selected) {
    $qs = db()->prepare('SELECT * FROM questions WHERE quiz_id = ? ORDER BY sort_order, id');
    $qs->execute([$selectedQuizId]);
    $questions = $qs->fetchAll();
}

$pageTitle = 'Manage quizzes';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1>Quizzes & questions</h1>
        <p class="muted">Set pass marks, attempt limits, and manage questions.</p>
    </div>
</div>

<div class="filters panel">
    <?php foreach ($quizzes as $q): ?>
        <a class="btn btn-sm <?= (int) $q['id'] === $selectedQuizId ? 'btn-primary' : 'btn-outline' ?>"
           href="?quiz=<?= (int) $q['id'] ?>"><?= e($q['title']) ?> (<?= (int) $q['question_count'] ?>)</a>
    <?php endforeach; ?>
</div>

<?php if ($selected): ?>
<div class="panel">
    <h2><?= e($selected['title']) ?></h2>
    <p class="muted"><?= e($selected['module_title']) ?></p>
    <form method="post" class="grid grid-2" style="margin-top:1rem">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="update_quiz">
        <input type="hidden" name="quiz_id" value="<?= $selectedQuizId ?>">
        <div class="form-group">
            <label for="pass_mark">Pass mark (%)</label>
            <input type="number" name="pass_mark" id="pass_mark" min="1" max="100" value="<?= (int) $selected['pass_mark'] ?>">
        </div>
        <div class="form-group">
            <label for="max_attempts">Max attempts</label>
            <input type="number" name="max_attempts" id="max_attempts" min="1" max="20" value="<?= (int) $selected['max_attempts'] ?>">
        </div>
        <div class="form-group" style="align-self:end">
            <button class="btn btn-primary" type="submit">Save settings</button>
        </div>
    </form>
</div>

<div class="panel">
    <h3>Add question</h3>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="add_question">
        <input type="hidden" name="quiz_id" value="<?= $selectedQuizId ?>">
        <div class="form-group">
            <label for="question_text">Question</label>
            <textarea name="question_text" id="question_text" required></textarea>
        </div>
        <div class="grid grid-2">
            <div class="form-group"><label>Option A</label><input name="option_a" required></div>
            <div class="form-group"><label>Option B</label><input name="option_b" required></div>
            <div class="form-group"><label>Option C</label><input name="option_c" required></div>
            <div class="form-group"><label>Option D</label><input name="option_d" required></div>
        </div>
        <div class="form-group">
            <label for="correct_option">Correct option</label>
            <select name="correct_option" id="correct_option" required>
                <option value="A">A</option><option value="B">B</option>
                <option value="C">C</option><option value="D">D</option>
            </select>
        </div>
        <div class="form-group">
            <label for="explanation">Explanation (shown after attempt)</label>
            <input type="text" name="explanation" id="explanation">
        </div>
        <button class="btn btn-primary" type="submit">Add question</button>
    </form>
</div>

<div class="panel table-wrap">
    <h3>Questions</h3>
    <table>
        <thead><tr><th>#</th><th>Question</th><th>Answer</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($questions as $i => $qq): ?>
            <tr>
                <td><?= $i + 1 ?></td>
                <td><?= e($qq['question_text']) ?></td>
                <td><?= e($qq['correct_option']) ?></td>
                <td><?= (int) $qq['is_active'] ? 'Active' : 'Inactive' ?></td>
                <td>
                    <?php if ((int) $qq['is_active']): ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="deactivate_question">
                            <input type="hidden" name="question_id" value="<?= (int) $qq['id'] ?>">
                            <input type="hidden" name="quiz_id" value="<?= $selectedQuizId ?>">
                            <button class="btn btn-sm btn-danger" type="submit" data-confirm="Deactivate this question?">Deactivate</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
