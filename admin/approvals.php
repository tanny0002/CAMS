<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();
require_role(['admin']);
ensure_access_schema();

$modules = db()->query('SELECT id, title FROM modules WHERE is_active = 1 ORDER BY sort_order')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = (string) ($_POST['action'] ?? '');
    $adminId = (int) current_user()['id'];

    if ($action === 'approve_user') {
        $uid = (int) ($_POST['user_id'] ?? 0);
        $courseIds = array_map('intval', $_POST['courses'] ?? []);
        db()->prepare("UPDATE users SET approval_status = 'approved', is_active = 1 WHERE id = ?")->execute([$uid]);
        if ($courseIds) {
            grant_course_access($uid, $courseIds, 'approved', 'admin', $adminId);
        }
        // Also approve any pending_admin course rows
        db()->prepare(
            "UPDATE course_access SET status = 'approved', decided_by = ?, decided_at = NOW()
             WHERE user_id = ? AND status IN ('pending_admin','pending_lecturer')"
        )->execute([$adminId, $uid]);
        audit_log('approve_user', 'user', $uid);
        flash('success', 'User approved.');
    } elseif ($action === 'reject_user') {
        $uid = (int) ($_POST['user_id'] ?? 0);
        db()->prepare("UPDATE users SET approval_status = 'rejected' WHERE id = ?")->execute([$uid]);
        db()->prepare(
            "UPDATE course_access SET status = 'rejected', decided_by = ?, decided_at = NOW() WHERE user_id = ? AND status LIKE 'pending%'"
        )->execute([$adminId, $uid]);
        audit_log('reject_user', 'user', $uid);
        flash('success', 'User rejected.');
    } elseif ($action === 'approve_course') {
        $caId = (int) ($_POST['access_id'] ?? 0);
        db()->prepare(
            "UPDATE course_access SET status = 'approved', decided_by = ?, decided_at = NOW() WHERE id = ?"
        )->execute([$adminId, $caId]);
        $uidStmt = db()->prepare('SELECT user_id FROM course_access WHERE id = ?');
        $uidStmt->execute([$caId]);
        $uid = (int) $uidStmt->fetchColumn();
        if ($uid > 0) {
            db()->prepare("UPDATE users SET approval_status = 'approved', is_active = 1 WHERE id = ? AND role <> 'admin'")
                ->execute([$uid]);
        }
        flash('success', 'Course access approved.');
    } elseif ($action === 'reject_course') {
        $caId = (int) ($_POST['access_id'] ?? 0);
        db()->prepare(
            "UPDATE course_access SET status = 'rejected', decided_by = ?, decided_at = NOW() WHERE id = ?"
        )->execute([$adminId, $caId]);
        flash('success', 'Course access rejected.');
    }
    redirect('admin/approvals.php');
}

$pendingUsers = db()->query(
    "SELECT id, full_name, email, account_type, created_at FROM users
     WHERE approval_status = 'pending' AND role <> 'admin'
     ORDER BY created_at ASC"
)->fetchAll();

$pendingCourses = db()->query(
    "SELECT ca.id, ca.status, ca.source, u.full_name, u.email, u.account_type, m.title AS module_title
     FROM course_access ca
     JOIN users u ON u.id = ca.user_id
     JOIN modules m ON m.id = ca.module_id
     WHERE ca.status IN ('pending_admin','pending_lecturer')
     ORDER BY ca.requested_at ASC"
)->fetchAll();

$pageTitle = 'Approvals';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Approvals</h1>
        <p class="muted">Approve individual learners and finalise student course access.</p>
    </div>
    <a class="btn btn-outline" href="<?= e(APP_URL) ?>/admin/invites.php">Invite lecturers</a>
</div>

<?php foreach (get_flashes() as $flash): ?>
    <div class="alert alert-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
<?php endforeach; ?>

<h2>Pending accounts</h2>
<div class="panel table-wrap">
    <table>
        <thead><tr><th>Name</th><th>Email</th><th>Type</th><th>Assign courses &amp; approve</th></tr></thead>
        <tbody>
        <?php if (!$pendingUsers): ?>
            <tr><td colspan="4" class="muted">No pending accounts.</td></tr>
        <?php endif; ?>
        <?php foreach ($pendingUsers as $pu): ?>
            <tr>
                <td><?= e($pu['full_name']) ?></td>
                <td><?= e($pu['email']) ?></td>
                <td><?= e($pu['account_type'] ?? 'user') ?></td>
                <td>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="user_id" value="<?= (int) $pu['id'] ?>">
                        <?php foreach ($modules as $m): ?>
                            <label style="display:inline-flex;gap:0.25rem;margin-right:0.6rem;font-size:0.85rem;">
                                <input type="checkbox" name="courses[]" value="<?= (int) $m['id'] ?>"> <?= e($m['title']) ?>
                            </label>
                        <?php endforeach; ?>
                        <div style="margin-top:0.4rem;display:flex;gap:0.4rem;">
                            <button class="btn btn-sm btn-primary" name="action" value="approve_user" type="submit">Approve</button>
                            <button class="btn btn-sm btn-outline" name="action" value="reject_user" type="submit">Reject</button>
                        </div>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<h2>Pending course access</h2>
<div class="panel table-wrap">
    <table>
        <thead><tr><th>User</th><th>Course</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php if (!$pendingCourses): ?>
            <tr><td colspan="4" class="muted">No pending course requests.</td></tr>
        <?php endif; ?>
        <?php foreach ($pendingCourses as $pc): ?>
            <tr>
                <td><?= e($pc['full_name']) ?> <span class="muted">(<?= e($pc['email']) ?>)</span></td>
                <td><?= e($pc['module_title']) ?></td>
                <td><?= e($pc['status']) ?></td>
                <td>
                    <form method="post" style="display:inline">
                        <?= csrf_field() ?>
                        <input type="hidden" name="access_id" value="<?= (int) $pc['id'] ?>">
                        <button class="btn btn-sm btn-primary" name="action" value="approve_course" type="submit">Approve</button>
                        <button class="btn btn-sm btn-outline" name="action" value="reject_course" type="submit">Reject</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require __DIR__ . '/../includes/footer.php'; ?>
