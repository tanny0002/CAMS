<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();
require_login();

$uid = (int) current_user()['id'];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $rating = (int) ($_POST['rating'] ?? 0);
    $comments = trim((string) ($_POST['comments'] ?? ''));
    if ($rating < 1 || $rating > 5) {
        $error = 'Please choose a rating from 1 to 5.';
    } else {
        db()->prepare('INSERT INTO feedback (user_id, rating, comments) VALUES (?, ?, ?)')
            ->execute([$uid, $rating, $comments !== '' ? $comments : null]);
        log_activity($uid, 'feedback_submitted', "Rating $rating");
        flash('success', 'Thank you for your feedback.');
        redirect('user/feedback.php');
    }
}

$pageTitle = 'Feedback';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1>Training feedback</h1>
        <p class="muted">Help evaluate usability and usefulness of CAMS (academic evaluation).</p>
    </div>
</div>

<div class="panel auth-wrap" style="margin:0">
    <?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="rating">Overall rating (1–5)</label>
            <select name="rating" id="rating" required>
                <option value="">Select…</option>
                <?php for ($i = 5; $i >= 1; $i--): ?>
                    <option value="<?= $i ?>"><?= $i ?></option>
                <?php endfor; ?>
            </select>
        </div>
        <div class="form-group">
            <label for="comments">Comments</label>
            <textarea name="comments" id="comments" placeholder="What worked well? What could improve?"></textarea>
        </div>
        <button class="btn btn-primary" type="submit">Submit feedback</button>
    </form>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
