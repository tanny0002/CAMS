<?php
declare(strict_types=1);

require_once __DIR__ . '/../includes/helpers.php';
start_app_session();
require_role(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';
    $userId = (int) ($_POST['user_id'] ?? 0);
    $me = (int) current_user()['id'];

    if ($userId === $me && in_array($action, ['deactivate', 'role'], true)) {
        flash('error', 'You cannot change your own admin account this way.');
        redirect('admin/users.php');
    }

    if ($action === 'deactivate') {
        db()->prepare('UPDATE users SET is_active = 0 WHERE id = ?')->execute([$userId]);
        audit_log('deactivate_user', 'user', $userId);
        flash('success', 'User deactivated.');
    } elseif ($action === 'activate') {
        db()->prepare('UPDATE users SET is_active = 1 WHERE id = ?')->execute([$userId]);
        audit_log('activate_user', 'user', $userId);
        flash('success', 'User activated.');
    } elseif ($action === 'role') {
        $role = (string) ($_POST['role'] ?? 'user');
        if (!in_array($role, ['user', 'lecturer', 'admin'], true)) {
            flash('error', 'Invalid role.');
        } else {
            db()->prepare('UPDATE users SET role = ? WHERE id = ?')->execute([$role, $userId]);
            audit_log('change_role', 'user', $userId, "Role set to $role");
            flash('success', 'Role updated.');
        }
    }
    redirect('admin/users.php');
}

$users = db()->query('SELECT id, full_name, email, role, is_active, last_login_at, created_at FROM users ORDER BY created_at DESC')->fetchAll();

$pageTitle = 'Manage users';
require __DIR__ . '/../includes/header.php';
?>

<div class="page-head">
    <div>
        <h1>Users</h1>
        <p class="muted">Deactivate accounts instead of hard-deleting them.</p>
    </div>
</div>

<div class="panel table-wrap">
    <table>
        <thead>
            <tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Last login</th><th>Actions</th></tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= e($u['full_name']) ?></td>
                <td><?= e($u['email']) ?></td>
                <td>
                    <form method="post" style="display:flex;gap:0.35rem;align-items:center">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="role">
                        <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                        <select name="role">
                            <?php foreach (['user', 'lecturer', 'admin'] as $role): ?>
                                <option value="<?= $role ?>" <?= $u['role'] === $role ? 'selected' : '' ?>><?= $role ?></option>
                            <?php endforeach; ?>
                        </select>
                        <button class="btn btn-sm btn-outline" type="submit">Save</button>
                    </form>
                </td>
                <td><?= (int) $u['is_active'] ? '<span class="badge badge-ok">Active</span>' : '<span class="badge badge-danger">Inactive</span>' ?></td>
                <td><?= e(format_datetime($u['last_login_at'])) ?></td>
                <td>
                    <?php if ((int) $u['is_active']): ?>
                        <form method="post" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="deactivate">
                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                            <button class="btn btn-sm btn-danger" type="submit" data-confirm="Deactivate this user?">Deactivate</button>
                        </form>
                    <?php else: ?>
                        <form method="post" style="display:inline">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="activate">
                            <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                            <button class="btn btn-sm btn-primary" type="submit">Activate</button>
                        </form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
