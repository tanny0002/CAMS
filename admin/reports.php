<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();
require_role(['admin', 'lecturer']);

$userId = (int) ($_GET['user_id'] ?? 0);
$moduleId = (int) ($_GET['module_id'] ?? 0);
$passed = $_GET['passed'] ?? '';
$from = trim((string) ($_GET['from'] ?? ''));
$to = trim((string) ($_GET['to'] ?? ''));
$export = isset($_GET['export']) && $_GET['export'] === 'csv';

$sql = 'SELECT a.id, u.full_name, u.email, q.title AS quiz_title, m.title AS module_title,
               a.score, a.passed, a.attempt_number, a.submitted_at
        FROM quiz_attempts a
        JOIN users u ON u.id = a.user_id
        JOIN quizzes q ON q.id = a.quiz_id
        JOIN modules m ON m.id = q.module_id
        WHERE a.submitted_at IS NOT NULL';
$params = [];

if ($userId > 0) {
    $sql .= ' AND a.user_id = ?';
    $params[] = $userId;
}
if ($moduleId > 0) {
    $sql .= ' AND q.module_id = ?';
    $params[] = $moduleId;
}
if ($passed === '1' || $passed === '0') {
    $sql .= ' AND a.passed = ?';
    $params[] = (int) $passed;
}
if ($from !== '') {
    $sql .= ' AND DATE(a.submitted_at) >= ?';
    $params[] = $from;
}
if ($to !== '') {
    $sql .= ' AND DATE(a.submitted_at) <= ?';
    $params[] = $to;
}
$sql .= ' ORDER BY a.submitted_at DESC';

$stmt = db()->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

if ($export) {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=cams_quiz_report.csv');
    $out = fopen('php://output', 'w');
    fputcsv($out, ['ID', 'Name', 'Email', 'Quiz', 'Module', 'Score', 'Passed', 'Attempt', 'Submitted']);
    foreach ($rows as $r) {
        fputcsv($out, [
            $r['id'], $r['full_name'], $r['email'], $r['quiz_title'], $r['module_title'],
            $r['score'], (int) $r['passed'] ? 'Yes' : 'No', $r['attempt_number'], $r['submitted_at'],
        ]);
    }
    fclose($out);
    audit_log('export_report', 'report', null, 'CSV quiz report');
    exit;
}

$users = db()->query('SELECT id, full_name FROM users WHERE role = \'user\' ORDER BY full_name')->fetchAll();
$modules = db()->query('SELECT id, title FROM modules ORDER BY sort_order')->fetchAll();

$lessonProgress = db()->query(
    'SELECT u.full_name, m.title AS module_title,
            COUNT(DISTINCT l.id) AS total_lessons,
            SUM(CASE WHEN lp.status = \'completed\' THEN 1 ELSE 0 END) AS completed_lessons
     FROM users u
     CROSS JOIN modules m
     LEFT JOIN lessons l ON l.module_id = m.id AND l.is_active = 1
     LEFT JOIN lesson_progress lp ON lp.lesson_id = l.id AND lp.user_id = u.id
     WHERE u.role = \'user\' AND u.is_active = 1 AND m.is_active = 1
     GROUP BY u.id, m.id
     ORDER BY u.full_name, m.sort_order'
)->fetchAll();

$pageTitle = 'Reports';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1>Reports</h1>
        <p class="muted">Filter quiz results and export CSV for awareness monitoring.</p>
    </div>
</div>

<form class="filters panel" method="get" action="">
    <div class="form-group">
        <label for="user_id">User</label>
        <select name="user_id" id="user_id">
            <option value="0">All users</option>
            <?php foreach ($users as $u): ?>
                <option value="<?= (int) $u['id'] ?>" <?= $userId === (int) $u['id'] ? 'selected' : '' ?>><?= e($u['full_name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="module_id">Module</label>
        <select name="module_id" id="module_id">
            <option value="0">All modules</option>
            <?php foreach ($modules as $m): ?>
                <option value="<?= (int) $m['id'] ?>" <?= $moduleId === (int) $m['id'] ? 'selected' : '' ?>><?= e($m['title']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="form-group">
        <label for="passed">Result</label>
        <select name="passed" id="passed">
            <option value="">All</option>
            <option value="1" <?= $passed === '1' ? 'selected' : '' ?>>Pass</option>
            <option value="0" <?= $passed === '0' ? 'selected' : '' ?>>Fail</option>
        </select>
    </div>
    <div class="form-group">
        <label for="from">From</label>
        <input type="date" name="from" id="from" value="<?= e($from) ?>">
    </div>
    <div class="form-group">
        <label for="to">To</label>
        <input type="date" name="to" id="to" value="<?= e($to) ?>">
    </div>
    <div class="form-group" style="align-self:end;display:flex;gap:0.5rem">
        <button class="btn btn-primary" type="submit">Filter</button>
        <button class="btn btn-outline" type="submit" name="export" value="csv">Export CSV</button>
    </div>
</form>

<div class="panel table-wrap">
    <h2>Quiz results (<?= count($rows) ?>)</h2>
    <table>
        <thead>
            <tr><th>User</th><th>Quiz</th><th>Module</th><th>Score</th><th>Result</th><th>Attempt</th><th>Submitted</th></tr>
        </thead>
        <tbody>
        <?php if (!$rows): ?>
            <tr><td colspan="7" class="muted">No results for these filters.</td></tr>
        <?php endif; ?>
        <?php foreach ($rows as $r): ?>
            <tr>
                <td><?= e($r['full_name']) ?></td>
                <td><?= e($r['quiz_title']) ?></td>
                <td><?= e($r['module_title']) ?></td>
                <td><?= e((string) $r['score']) ?>%</td>
                <td><?= (int) $r['passed'] ? '<span class="badge badge-ok">Pass</span>' : '<span class="badge badge-danger">Fail</span>' ?></td>
                <td><?= (int) $r['attempt_number'] ?></td>
                <td><?= e(format_datetime($r['submitted_at'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<div class="panel table-wrap">
    <h2>Lesson progress by user / module</h2>
    <table>
        <thead><tr><th>User</th><th>Module</th><th>Completed</th><th>Total</th></tr></thead>
        <tbody>
        <?php foreach ($lessonProgress as $lp): ?>
            <tr>
                <td><?= e($lp['full_name']) ?></td>
                <td><?= e($lp['module_title']) ?></td>
                <td><?= (int) $lp['completed_lessons'] ?></td>
                <td><?= (int) $lp['total_lessons'] ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
