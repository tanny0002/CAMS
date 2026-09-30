<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'create' || $action === 'update') {
        $id = (int) ($_POST['id'] ?? 0);
        $title = trim((string) ($_POST['title'] ?? ''));
        $description = trim((string) ($_POST['description'] ?? ''));
        $difficulty = (string) ($_POST['difficulty'] ?? 'beginner');
        $sort = (int) ($_POST['sort_order'] ?? 0);
        $allowedDiff = ['beginner', 'intermediate', 'advanced'];
        if (!in_array($difficulty, $allowedDiff, true)) {
            $difficulty = 'beginner';
        }
        $slug = strtolower(preg_replace('/[^a-z0-9]+/i', '-', $title) ?? 'module');
        $slug = trim($slug, '-') ?: 'module';

        if ($title === '') {
            flash('error', 'Module title is required.');
        } elseif ($action === 'create') {
            db()->prepare(
                'INSERT INTO modules (title, slug, description, difficulty, sort_order) VALUES (?, ?, ?, ?, ?)'
            )->execute([$title, $slug, $description, $difficulty, $sort]);
            audit_log('create_module', 'module', (int) db()->lastInsertId(), $title);
            flash('success', 'Module created.');
        } else {
            db()->prepare(
                'UPDATE modules SET title=?, slug=?, description=?, difficulty=?, sort_order=? WHERE id=?'
            )->execute([$title, $slug, $description, $difficulty, $sort, $id]);
            audit_log('update_module', 'module', $id, $title);
            flash('success', 'Module updated.');
        }
    } elseif ($action === 'deactivate') {
        $id = (int) ($_POST['id'] ?? 0);
        db()->prepare('UPDATE modules SET is_active = 0 WHERE id = ?')->execute([$id]);
        audit_log('deactivate_module', 'module', $id);
        flash('success', 'Module deactivated.');
    } elseif ($action === 'activate') {
        $id = (int) ($_POST['id'] ?? 0);
        db()->prepare('UPDATE modules SET is_active = 1 WHERE id = ?')->execute([$id]);
        audit_log('activate_module', 'module', $id);
        flash('success', 'Module activated.');
    }
    redirect('admin/modules.php');
}

$editId = (int) ($_GET['edit'] ?? 0);
$edit = null;
if ($editId) {
    $s = db()->prepare('SELECT * FROM modules WHERE id = ?');
    $s->execute([$editId]);
    $edit = $s->fetch() ?: null;
}

$modules = db()->query(
    'SELECT m.*,
        (SELECT COUNT(*) FROM lessons l WHERE l.module_id = m.id) AS lesson_count,
        (SELECT COUNT(*) FROM quizzes q WHERE q.module_id = m.id) AS quiz_count
     FROM modules m
     ORDER BY m.sort_order, m.id'
)->fetchAll();

$pageTitle = 'Manage modules';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1>Modules (courses)</h1>
        <p class="muted">Organise lessons into courses. Then add lessons under each module.</p>
    </div>
    <a class="btn btn-outline" href="<?= e(APP_URL) ?>/admin/lessons.php">Manage lessons</a>
</div>

<div class="panel">
    <h2><?= $edit ? 'Edit module' : 'Add module' ?></h2>
    <form method="post" action="">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="<?= $edit ? 'update' : 'create' ?>">
        <?php if ($edit): ?><input type="hidden" name="id" value="<?= (int) $edit['id'] ?>"><?php endif; ?>
        <div class="grid grid-2">
            <div class="form-group">
                <label for="title">Title</label>
                <input type="text" name="title" id="title" required value="<?= e($edit['title'] ?? '') ?>">
            </div>
            <div class="form-group">
                <label for="difficulty">Difficulty</label>
                <select name="difficulty" id="difficulty">
                    <?php foreach (['beginner', 'intermediate', 'advanced'] as $d): ?>
                        <option value="<?= $d ?>" <?= ($edit['difficulty'] ?? 'beginner') === $d ? 'selected' : '' ?>>
                            <?= e(ucfirst($d)) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="form-group">
            <label for="description">Description</label>
            <textarea name="description" id="description" rows="3"><?= e($edit['description'] ?? '') ?></textarea>
        </div>
        <div class="form-group">
            <label for="sort_order">Sort order</label>
            <input type="number" name="sort_order" id="sort_order" value="<?= e((string) ($edit['sort_order'] ?? '0')) ?>">
        </div>
        <button class="btn btn-primary" type="submit"><?= $edit ? 'Update' : 'Create' ?></button>
        <?php if ($edit): ?><a class="btn btn-outline" href="<?= e(APP_URL) ?>/admin/modules.php">Cancel</a><?php endif; ?>
    </form>
</div>

<div class="panel table-wrap">
    <table>
        <thead>
            <tr>
                <th>Order</th>
                <th>Title</th>
                <th>Difficulty</th>
                <th>Lessons</th>
                <th>Quizzes</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($modules as $m): ?>
            <tr>
                <td><?= (int) $m['sort_order'] ?></td>
                <td>
                    <strong><?= e($m['title']) ?></strong>
                    <?php if (!empty($m['description'])): ?>
                        <div class="muted" style="font-size:0.9rem"><?= e(strlen((string) $m['description']) > 80 ? substr((string) $m['description'], 0, 77) . '…' : (string) $m['description']) ?></div>
                    <?php endif; ?>
                </td>
                <td><?= e(ucfirst((string) $m['difficulty'])) ?></td>
                <td><?= (int) $m['lesson_count'] ?></td>
                <td><?= (int) $m['quiz_count'] ?></td>
                <td><?= (int) $m['is_active'] ? 'Active' : 'Inactive' ?></td>
                <td style="display:flex;gap:0.35rem;flex-wrap:wrap">
                    <a class="btn btn-sm btn-outline" href="?edit=<?= (int) $m['id'] ?>">Edit</a>
                    <a class="btn btn-sm btn-outline" href="<?= e(APP_URL) ?>/admin/lessons.php">Lessons</a>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="id" value="<?= (int) $m['id'] ?>">
                        <input type="hidden" name="action" value="<?= (int) $m['is_active'] ? 'deactivate' : 'activate' ?>">
                        <button class="btn btn-sm <?= (int) $m['is_active'] ? 'btn-danger' : 'btn-primary' ?>" type="submit">
                            <?= (int) $m['is_active'] ? 'Deactivate' : 'Activate' ?>
                        </button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
