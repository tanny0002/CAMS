<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    if ($action === 'create') {
        $title = trim((string) ($_POST['title'] ?? ''));
        $body = trim((string) ($_POST['body'] ?? ''));
        $category = trim((string) ($_POST['category'] ?? ''));
        if ($title === '' || $body === '') {
            flash('error', 'Title and body are required.');
        } else {
            db()->prepare('INSERT INTO security_tips (title, body, category) VALUES (?, ?, ?)')
                ->execute([$title, $body, $category !== '' ? $category : null]);
            audit_log('create_tip', 'tip', (int) db()->lastInsertId());
            flash('success', 'Tip created.');
        }
    } elseif ($action === 'deactivate') {
        $id = (int) ($_POST['id'] ?? 0);
        db()->prepare('UPDATE security_tips SET is_active = 0 WHERE id = ?')->execute([$id]);
        audit_log('deactivate_tip', 'tip', $id);
        flash('success', 'Tip deactivated.');
    }
    redirect('admin/tips.php');
}

$tips = db()->query('SELECT * FROM security_tips ORDER BY id DESC')->fetchAll();

$pageTitle = 'Security tips';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1>Security tips</h1>
        <p class="muted">Short tips shown to learners.</p>
    </div>
</div>

<div class="panel">
    <h2>Add tip</h2>
    <form method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="create">
        <div class="form-group">
            <label for="title">Title</label>
            <input type="text" name="title" id="title" required>
        </div>
        <div class="form-group">
            <label for="category">Category</label>
            <input type="text" name="category" id="category" placeholder="Phishing, Passwords, …">
        </div>
        <div class="form-group">
            <label for="body">Body</label>
            <textarea name="body" id="body" required></textarea>
        </div>
        <button class="btn btn-primary" type="submit">Add tip</button>
    </form>
</div>

<div class="panel table-wrap">
    <table>
        <thead><tr><th>Title</th><th>Category</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($tips as $t): ?>
            <tr>
                <td><?= e($t['title']) ?></td>
                <td><?= e($t['category'] ?? '—') ?></td>
                <td><?= (int) $t['is_active'] ? 'Active' : 'Inactive' ?></td>
                <td>
                    <?php if ((int) $t['is_active']): ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="deactivate">
                            <input type="hidden" name="id" value="<?= (int) $t['id'] ?>">
                            <button class="btn btn-sm btn-danger" type="submit">Deactivate</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
