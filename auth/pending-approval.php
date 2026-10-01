<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();
ensure_access_schema();
require_login();

refresh_session_user();
$u = current_user();
if (($u['approval_status'] ?? '') === 'approved' || ($u['role'] ?? '') === 'admin') {
    redirect(role_home($u['role']));
}

$stmt = db()->prepare(
    "SELECT ca.status, m.title FROM course_access ca
     JOIN modules m ON m.id = ca.module_id
     WHERE ca.user_id = ? ORDER BY m.sort_order, m.title"
);
$stmt->execute([(int) $u['id']]);
$courses = $stmt->fetchAll();

$pageTitle = 'Pending approval';
$bodyClass = 'page-auth';
$authLayout = true;
require __DIR__ . '/../includes/header.php';
?>
<section class="auth-split" aria-label="Pending approval">
    <aside class="auth-split-visual">
        <img src="<?= e(APP_URL) ?>/assets/images/auth-login-panel.png" alt="" width="900" height="1600">
        <div class="auth-split-overlay"><div class="auth-split-copy">
            <p class="auth-split-brand">CAMS</p>
            <p class="auth-split-quote">Access is granted after approval.</p>
        </div></div>
    </aside>
    <div class="auth-split-panel"><div class="auth-split-card">
        <p class="auth-kicker">Awaiting review</p>
        <h1>Account pending approval</h1>
        <p class="auth-sub">
            <?php if (($u['account_type'] ?? '') === 'student'): ?>
                A lecturer must approve your course requests, then an admin must confirm.
            <?php else: ?>
                An administrator must approve your account and assign courses.
            <?php endif; ?>
        </p>
        <?php if ($courses): ?>
            <ul class="auth-split-list" style="color:#334;">
                <?php foreach ($courses as $c): ?>
                    <li><?= e($c['title']) ?> — <em><?= e(str_replace('_', ' ', (string) $c['status'])) ?></em></li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
        <p class="auth-footer-note">
            <a href="<?= e(APP_URL) ?>/auth/logout.php">Log out</a>
        </p>
    </div></div>
</section>
<?php require __DIR__ . '/../includes/footer.php'; ?>
