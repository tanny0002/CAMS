<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();
require_role(['admin']);
ensure_access_schema();

$modules = db()->query('SELECT id, title FROM modules WHERE is_active = 1 ORDER BY sort_order, title')->fetchAll();
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $message = trim((string) ($_POST['message'] ?? ''));
    $courseIds = array_map('intval', $_POST['courses'] ?? []);
    try {
        $inv = create_invitation($email, 'lecturer', (int) current_user()['id'], $courseIds, $message !== '' ? $message : null);
        $url = rtrim(APP_URL, '/') . '/auth/accept-invite.php?token=' . urlencode($inv['token']);
        $sent = send_mail(
            $email,
            'CAMS lecturer invitation',
            email_invite_html($email, 'lecturer', $url, current_user()['full_name'], invite_expiry_hours()),
            "You are invited to CAMS as a lecturer. Accept within 24 hours: {$url}"
        );
        audit_log('invite_lecturer', 'invitation', $inv['id'], $email);
        flash($sent ? 'success' : 'error', $sent
            ? 'Invitation emailed to ' . $email . ' (valid 24 hours).'
            : 'Invitation created but email failed. Share this link manually: ' . $url);
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
    if ($error === null) {
        redirect('admin/invites.php');
    }
}

$recent = db()->query(
    "SELECT i.*, u.full_name AS inviter
     FROM invitations i
     LEFT JOIN users u ON u.id = i.invited_by
     WHERE i.role = 'lecturer'
     ORDER BY i.id DESC LIMIT 30"
)->fetchAll();

$pageTitle = 'Invite lecturers';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Invite lecturers</h1>
        <p class="muted">Email an invitation link (valid 24 hours) and assign courses they may teach/manage.</p>
    </div>
    <a class="btn btn-outline" href="<?= e(APP_URL) ?>/admin/approvals.php">Approvals queue</a>
</div>

<?php foreach (get_flashes() as $flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endforeach; ?>
<?php if ($error): ?><div class="alert alert-error"><?= e($error) ?></div><?php endif; ?>

<div class="panel" style="margin-bottom:1.25rem;">
    <form method="post" class="auth-form">
        <?= csrf_field() ?>
        <div class="form-group">
            <label for="email">Lecturer email</label>
            <input type="email" id="email" name="email" required>
        </div>
        <div class="form-group">
            <label for="message">Message (optional)</label>
            <textarea id="message" name="message" rows="2"></textarea>
        </div>
        <fieldset class="form-group">
            <legend>Courses they can access</legend>
            <?php foreach ($modules as $m): ?>
                <label class="checkbox-row" style="display:flex;gap:0.5rem;margin:0.25rem 0;">
                    <input type="checkbox" name="courses[]" value="<?= (int) $m['id'] ?>">
                    <span><?= e($m['title']) ?></span>
                </label>
            <?php endforeach; ?>
        </fieldset>
        <button class="btn btn-primary" type="submit">Send invitation</button>
    </form>
</div>

<div class="panel table-wrap">
    <table>
        <thead><tr><th>Email</th><th>Invited by</th><th>Expires</th><th>Status</th></tr></thead>
        <tbody>
        <?php foreach ($recent as $r): ?>
            <tr>
                <td><?= e($r['email']) ?></td>
                <td><?= e($r['inviter'] ?? '—') ?></td>
                <td><?= e(format_datetime($r['expires_at'])) ?></td>
                <td><?= $r['used_at'] ? 'Used' : (strtotime((string) $r['expires_at']) < time() ? 'Expired' : 'Open') ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
