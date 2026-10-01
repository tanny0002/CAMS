<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();
require_role(['lecturer', 'admin']);
ensure_access_schema();

$uid = (int) current_user()['id'];
$allowed = user_approved_module_ids($uid);
if (current_user()['role'] === 'admin') {
    $allowed = array_map('intval', db()->query('SELECT id FROM modules WHERE is_active = 1')->fetchAll(PDO::FETCH_COLUMN));
}
$modules = [];
if ($allowed) {
    $in = implode(',', $allowed);
    $modules = db()->query("SELECT id, title FROM modules WHERE id IN ({$in}) ORDER BY sort_order, title")->fetchAll();
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $emailsRaw = (string) ($_POST['emails'] ?? '');
    $message = trim((string) ($_POST['message'] ?? ''));
    $courseIds = array_values(array_intersect(array_map('intval', $_POST['courses'] ?? []), $allowed));
    $emails = preg_split('/[\s,;]+/', $emailsRaw) ?: [];
    $emails = array_values(array_unique(array_filter(array_map(static fn ($e) => strtolower(trim($e)), $emails))));
    $sent = 0;
    $failed = 0;
    try {
        if ($courseIds === []) {
            throw new InvalidArgumentException('Select at least one of your courses.');
        }
        if ($emails === []) {
            throw new InvalidArgumentException('Enter at least one student email.');
        }
        foreach ($emails as $email) {
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $failed++;
                continue;
            }
            $inv = create_invitation($email, 'student', $uid, $courseIds, $message !== '' ? $message : null);
            $url = rtrim(APP_URL, '/') . '/auth/accept-invite.php?token=' . urlencode($inv['token']);
            $ok = send_mail(
                $email,
                'CAMS student invitation',
                email_invite_html($email, 'student', $url, current_user()['full_name'], invite_expiry_hours()),
                "You are invited to CAMS. Accept within 24 hours: {$url}"
            );
            $ok ? $sent++ : $failed++;
        }
        flash('success', "Invitations processed. Sent: {$sent}. Failed/skipped: {$failed}.");
        redirect('lecturer/invites.php');
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$pageTitle = 'Invite students';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Broadcast student invitations</h1>
        <p class="muted">Send 24-hour signup links for the courses you manage.</p>
    </div>
    <a class="btn btn-outline" href="<?= e(APP_URL) ?>/lecturer/approvals.php">Approvals</a>
</div>

<?php foreach (get_flashes() as $flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endforeach; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="panel">
    <?php if (!$modules): ?>
        <p class="muted">You have no assigned courses yet. Ask an admin to invite you with course access.</p>
    <?php else: ?>
        <form method="post" class="auth-form">
            <?= csrf_field() ?>
            <div class="form-group">
                <label for="emails">Student emails (comma or new-line separated)</label>
                <textarea id="emails" name="emails" rows="5" required placeholder="student1@school.edu, student2@school.edu"></textarea>
            </div>
            <div class="form-group">
                <label for="message">Message (optional)</label>
                <textarea id="message" name="message" rows="2"></textarea>
            </div>
            <fieldset class="form-group">
                <legend>Courses included</legend>
                <?php foreach ($modules as $m): ?>
                    <label class="checkbox-row" style="display:flex;gap:0.5rem;margin:0.25rem 0;">
                        <input type="checkbox" name="courses[]" value="<?= (int) $m['id'] ?>" checked>
                        <span><?= e($m['title']) ?></span>
                    </label>
                <?php endforeach; ?>
            </fieldset>
            <button class="btn btn-primary" type="submit">Send invitations</button>
        </form>
    <?php endif; ?>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
