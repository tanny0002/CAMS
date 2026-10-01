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

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $accessId = (int) ($_POST['access_id'] ?? 0);
    $row = db()->prepare('SELECT * FROM course_access WHERE id = ?');
    $row->execute([$accessId]);
    $ca = $row->fetch();
    if (!$ca || !in_array((int) $ca['module_id'], $allowed, true)) {
        flash('error', 'Not allowed for that course.');
        redirect('lecturer/approvals.php');
    }
    if ($action === 'approve') {
        // Lecturer approval → still needs admin
        db()->prepare(
            "UPDATE course_access SET status = 'pending_admin', decided_by = ?, decided_at = NOW() WHERE id = ?"
        )->execute([$uid, $accessId]);
        flash('success', 'Forwarded to admin for final approval.');
    } elseif ($action === 'reject') {
        db()->prepare(
            "UPDATE course_access SET status = 'rejected', decided_by = ?, decided_at = NOW() WHERE id = ?"
        )->execute([$uid, $accessId]);
        flash('success', 'Request rejected.');
    }
    redirect('lecturer/approvals.php');
}

$pending = [];
if ($allowed) {
    $in = implode(',', array_map('intval', $allowed));
    $pending = db()->query(
        "SELECT ca.id, ca.status, u.full_name, u.email, m.title AS module_title
         FROM course_access ca
         JOIN users u ON u.id = ca.user_id
         JOIN modules m ON m.id = ca.module_id
         WHERE ca.status = 'pending_lecturer' AND ca.module_id IN ({$in})
         ORDER BY ca.requested_at ASC"
    )->fetchAll();
}

$pageTitle = 'Student approvals';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Student course approvals</h1>
        <p class="muted">Approve requests for your courses. Admin confirmation is still required.</p>
    </div>
</div>

<?php foreach (get_flashes() as $flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endforeach; ?>

<div class="panel table-wrap">
    <table>
        <thead><tr><th>Student</th><th>Course</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if (!$pending): ?>
            <tr><td colspan="3" class="muted">No pending requests.</td></tr>
        <?php endif; ?>
        <?php foreach ($pending as $p): ?>
            <tr>
                <td><?= e($p['full_name']) ?><br><span class="muted"><?= e($p['email']) ?></span></td>
                <td><?= e($p['module_title']) ?></td>
                <td>
                    <form method="post" style="display:inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="access_id" value="<?= (int) $p['id'] ?>">
                        <button class="btn btn-sm btn-primary" name="action" value="approve" type="submit">Approve → admin</button>
                        <button class="btn btn-sm btn-outline" name="action" value="reject" type="submit">Reject</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
