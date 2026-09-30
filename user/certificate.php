<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();
require_login();

$code = trim((string) ($_GET['code'] ?? ''));
$stmt = db()->prepare(
    'SELECT c.*, u.full_name, m.title AS module_title
     FROM certificates c
     JOIN users u ON u.id = c.user_id
     JOIN modules m ON m.id = c.module_id
     WHERE c.certificate_code = ? AND c.user_id = ?'
);
$stmt->execute([$code, (int) current_user()['id']]);
$cert = $stmt->fetch();
if (!$cert) {
    flash('error', 'Certificate not found.');
    redirect('user/progress.php');
}

$pageTitle = 'Certificate';
require __DIR__ . '/../includes/header.php';
?>

<div class="panel" style="text-align:center;padding:2.5rem 1.5rem">
    <p class="badge">CAMS Certificate</p>
    <h1 style="margin:1rem 0">Module completed</h1>
    <p class="muted">This certifies that</p>
    <p style="font-size:1.4rem;font-weight:700"><?= e($cert['full_name']) ?></p>
    <p class="muted">has completed awareness training for</p>
    <p style="font-size:1.2rem;font-weight:600;color:var(--accent)"><?= e($cert['module_title']) ?></p>
    <p class="muted" style="margin-top:1.5rem">
        Code: <?= e($cert['certificate_code']) ?><br>
        Issued: <?= e(format_datetime($cert['issued_at'])) ?>
    </p>
    <button class="btn btn-outline" type="button" onclick="window.print()">Print</button>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
