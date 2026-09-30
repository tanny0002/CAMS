<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();
require_role(['admin']);

$modules = db()->query('SELECT id, title FROM modules ORDER BY sort_order')->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $moduleId = (int) ($_POST['module_id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $summary = trim((string) ($_POST['summary'] ?? ''));
        $content = trim((string) ($_POST['content'] ?? ''));
        $sort = (int) ($_POST['sort_order'] ?? 0);
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title) ?? 'lesson');
        $slug = trim($slug, '-') ?: 'lesson';

        if ($title === '' || $content === '' || $moduleId < 1) {
            flash('error', 'Module, title, and content are required.');
        } elseif ($action === 'create') {
            db()->prepare(
                'INSERT INTO lessons (module_id, title, slug, summary, content, sort_order) VALUES (?, ?, ?, ?, ?, ?)'
            )->execute([$moduleId, $title, $slug, $summary, $content, $sort]);
            audit_log('create_lesson', 'lesson', (int) db()->lastInsertId(), $title);
            flash('success', 'Lesson created.');
        } else {
            db()->prepare(
                'UPDATE lessons SET module_id=?, title=?, slug=?, summary=?, content=?, sort_order=? WHERE id=?'
            )->execute([$moduleId, $title, $slug, $summary, $content, $sort, $id]);
            audit_log('update_lesson', 'lesson', $id, $title);
            flash('success', 'Lesson updated.');
        }
    } elseif ($action === 'deactivate') {
        $id = (int) ($_POST['id'] ?? 0);
        db()->prepare('UPDATE lessons SET is_active = 0 WHERE id = ?')->execute([$id]);
        audit_log('deactivate_lesson', 'lesson', $id);
        flash('success', 'Lesson deactivated.');
    } elseif ($action === 'activate') {
        $id = (int) ($_POST['id'] ?? 0);
        db()->prepare('UPDATE lessons SET is_active = 1 WHERE id = ?')->execute([$id]);
        audit_log('activate_lesson', 'lesson', $id);
        flash('success', 'Lesson activated.');
    }
    redirect('admin/lessons.php');
}

$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
if ($editId) {
    $s = db()->prepare('SELECT * FROM lessons WHERE id = ?');
    $s->execute([$editId]);
    $edit = $s->fetch() ?: null;
}

$lessons = db()->query(
    'SELECT l.*, m.title AS module_title FROM lessons l
     JOIN modules m ON m.id = l.module_id
     ORDER BY m.sort_order, l.sort_order'
)->fetchAll();

$pageTitle = 'Manage lessons';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1>Lessons</h1>
        <p class="muted">Article-style content under a module. Use &lt;h2&gt;/&lt;h3&gt; headings — they become the on-page outline for learners.</p>
    </div>
    <a class="btn btn-outline" href="<?= e(APP_URL) ?>/admin/modules.php">Manage modules</a>
</div>

<div class="panel">
    <h2><?= $edit ? 'Edit lesson' : 'Add lesson' ?></h2>
    <form method="post" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
        <div class="grid grid-2">
            <div class="form-group">
                <label for="module_id">Module</label>
                <select name="module_id" id="module_id" required>
                    <?php foreach ($modules as $m): ?>
                        <option value="<?= (int) $m['id'] ?>" <?= ($edit['module_id'] ?? '') == $m['id'] ? 'selected' : '' ?>><?= e($m['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="form-group">
                <label for="sort_order">Sort order</label>
                <input type="number" name="sort_order" id="sort_order" value="<?= e((string) ($edit['sort_order'] ?? '0')) ?>">
            </div>
        </div>
        <div class="form-group">
            <label for="title">Title</label>
            <input type="text" name="title" id="title" required value="<?= e($edit['title'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="summary">Summary</label>
            <input type="text" name="summary" id="summary" value="<?= e($edit['summary'] ?? '') ?>">
        </div>
        <div class="form-group">
            <label for="content">Content (HTML allowed)</label>
            <textarea name="content" id="content" required style="min-height:180px"><?= e($edit['content'] ?? '') ?></textarea>
        </div>
        <button class="btn btn-primary" type="submit"><?= $edit ? 'Update' : 'Create' ?></button>
        <?php if ($edit): ?><a class="btn btn-outline" href="<?= e(APP_URL) ?>/admin/lessons.php">Cancel</a><?php endif; ?>
    </form>
</div>

<div class="panel table-wrap">
    <table>
        <thead><tr><th>Module</th><th>Title</th><th>Status</th><th>Actions</th></tr></thead>
        <tbody>
        <?php foreach ($lessons as $l): ?>
            <tr>
                <td><?= e($l['module_title']) ?></td>
                <td><?= e($l['title']) ?></td>
                <td><?= (int) $l['is_active'] ? 'Active' : 'Inactive' ?></td>
                <td style="display:flex;gap:0.35rem;flex-wrap:wrap">
                    <a class="btn btn-sm btn-outline" href="?edit=<?= (int) $l['id'] ?>">Edit</a>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $l['id'] ?>">
                        <input type="hidden" name="action" value="<?= (int) $l['is_active'] ? 'deactivate' : 'activate' ?>">
                        <button class="btn btn-sm <?= (int) $l['is_active'] ? 'btn-danger' : 'btn-primary' ?>" type="submit">
                            <?= (int) $l['is_active'] ? 'Deactivate' : 'Activate' ?>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
