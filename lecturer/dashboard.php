<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();
require_role(['lecturer', 'admin']);
ensure_access_schema();

$uid = (int) current_user()['id'];
$courses = user_approved_module_ids($uid);
$pendingStudents = 0;
if ($courses) {
    $in = implode(',', array_map('intval', $courses));
    $pendingStudents = (int) db()->query(
        "SELECT COUNT(*) FROM course_access WHERE status = 'pending_lecturer' AND module_id IN ({$in})"
    )->fetchColumn();
}

$pageTitle = 'Lecturer dashboard';
require __DIR__ . '/../includes/header.php';
?>
<div class="page-head">
    <div>
        <h1>Lecturer dashboard</h1>
        <p class="muted">Invite students and approve course access for your modules.</p>
    </div>
</div>

<div class="stat-grid" style="display:grid;grid-template-columns:repeat(auto-fit,minmax(180px,1fr));gap:1rem;">
    <div class="panel"><p class="muted">Your courses</p><p style="font-size:1.6rem;font-weight:700;margin:0;"><?= count($courses) ?></p></div>
    <div class="panel"><p class="muted">Pending student requests</p><p style="font-size:1.6rem;font-weight:700;margin:0;"><?= $pendingStudents ?></p></div>
</div>

<p style="margin-top:1.25rem;display:flex;gap:0.6rem;flex-wrap:wrap;">
    <a class="btn btn-primary" href="<?= e(APP_URL) ?>/lecturer/invites.php">Invite / broadcast students</a>
    <a class="btn btn-outline" href="<?= e(APP_URL) ?>/lecturer/approvals.php">Approve students</a>
    <a class="btn btn-outline" href="<?= e(APP_URL) ?>/admin/reports.php">Reports</a>
</p>
<?php require __DIR__ . '/../includes/footer.php'; ?>
