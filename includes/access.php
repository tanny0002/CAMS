<?php
declare(strict_types=1);

/**
 * Access control: invitations, approvals, course grants, profile completeness.
 */

function ensure_access_schema(): void
{
    static $done = false;
    if ($done) {
        return;
    }
    ensure_auth_schema();
    $pdo = db();

    $cols = [
        'account_type' => db_is_pgsql()
            ? "VARCHAR(20) NULL"
            : "VARCHAR(20) NULL",
        'profile_complete' => db_is_pgsql()
            ? "SMALLINT NOT NULL DEFAULT 0"
            : "TINYINT(1) NOT NULL DEFAULT 0",
        'must_change_password' => db_is_pgsql()
            ? "SMALLINT NOT NULL DEFAULT 0"
            : "TINYINT(1) NOT NULL DEFAULT 0",
        'approval_status' => db_is_pgsql()
            ? "VARCHAR(20) NOT NULL DEFAULT 'pending'"
            : "VARCHAR(20) NOT NULL DEFAULT 'pending'",
        'phone' => "VARCHAR(40) NULL",
        'organisation' => "VARCHAR(160) NULL",
        'department' => "VARCHAR(120) NULL",
    ];
    foreach ($cols as $name => $def) {
        if (!db_has_column('users', $name)) {
            $pdo->exec("ALTER TABLE users ADD COLUMN {$name} {$def}");
        }
    }

    if (db_is_pgsql()) {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS invitations (
                id SERIAL PRIMARY KEY,
                email VARCHAR(160) NOT NULL,
                role VARCHAR(20) NOT NULL CHECK (role IN ('lecturer','student')),
                token_hash VARCHAR(64) NOT NULL UNIQUE,
                invited_by INT NULL REFERENCES users(id) ON DELETE SET NULL,
                message TEXT NULL,
                expires_at TIMESTAMP NOT NULL,
                used_at TIMESTAMP NULL,
                used_by INT NULL REFERENCES users(id) ON DELETE SET NULL,
                created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
            )"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS invitation_modules (
                invitation_id INT NOT NULL REFERENCES invitations(id) ON DELETE CASCADE,
                module_id INT NOT NULL REFERENCES modules(id) ON DELETE CASCADE,
                PRIMARY KEY (invitation_id, module_id)
            )"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS course_access (
                id SERIAL PRIMARY KEY,
                user_id INT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
                module_id INT NOT NULL REFERENCES modules(id) ON DELETE CASCADE,
                status VARCHAR(30) NOT NULL DEFAULT 'pending_admin',
                source VARCHAR(30) NOT NULL DEFAULT 'self',
                requested_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
                decided_by INT NULL REFERENCES users(id) ON DELETE SET NULL,
                decided_at TIMESTAMP NULL,
                notes TEXT NULL,
                UNIQUE (user_id, module_id)
            )"
        );
    } else {
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS invitations (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                email VARCHAR(160) NOT NULL,
                role ENUM('lecturer','student') NOT NULL,
                token_hash VARCHAR(64) NOT NULL UNIQUE,
                invited_by INT UNSIGNED NULL,
                message TEXT NULL,
                expires_at DATETIME NOT NULL,
                used_at DATETIME NULL,
                used_by INT UNSIGNED NULL,
                created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                CONSTRAINT fk_inv_by FOREIGN KEY (invited_by) REFERENCES users(id) ON DELETE SET NULL,
                CONSTRAINT fk_inv_used FOREIGN KEY (used_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS invitation_modules (
                invitation_id INT UNSIGNED NOT NULL,
                module_id INT UNSIGNED NOT NULL,
                PRIMARY KEY (invitation_id, module_id),
                CONSTRAINT fk_im_inv FOREIGN KEY (invitation_id) REFERENCES invitations(id) ON DELETE CASCADE,
                CONSTRAINT fk_im_mod FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE
            ) ENGINE=InnoDB"
        );
        $pdo->exec(
            "CREATE TABLE IF NOT EXISTS course_access (
                id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
                user_id INT UNSIGNED NOT NULL,
                module_id INT UNSIGNED NOT NULL,
                status VARCHAR(30) NOT NULL DEFAULT 'pending_admin',
                source VARCHAR(30) NOT NULL DEFAULT 'self',
                requested_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
                decided_by INT UNSIGNED NULL,
                decided_at DATETIME NULL,
                notes TEXT NULL,
                UNIQUE KEY uq_user_module_access (user_id, module_id),
                CONSTRAINT fk_ca_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                CONSTRAINT fk_ca_mod FOREIGN KEY (module_id) REFERENCES modules(id) ON DELETE CASCADE,
                CONSTRAINT fk_ca_decider FOREIGN KEY (decided_by) REFERENCES users(id) ON DELETE SET NULL
            ) ENGINE=InnoDB"
        );
    }

    // Bootstrap default admin with a temporary password (must change on first login).
    $boot = defined('BOOTSTRAP_ADMIN_EMAIL')
        ? BOOTSTRAP_ADMIN_EMAIL
        : strtolower(trim(env('BOOTSTRAP_ADMIN_EMAIL', 'chitaurotanaka00@gmail.com') ?? 'chitaurotanaka00@gmail.com'));
    $bootPass = defined('BOOTSTRAP_ADMIN_PASSWORD')
        ? BOOTSTRAP_ADMIN_PASSWORD
        : (env('BOOTSTRAP_ADMIN_PASSWORD', 'Admin@CAMS1') ?? 'Admin@CAMS1');
    if ($boot !== '') {
        $stmt = $pdo->prepare('SELECT id, password_hash, must_change_password FROM users WHERE email = ? LIMIT 1');
        $stmt->execute([$boot]);
        $existing = $stmt->fetch();
        $hash = password_hash($bootPass, PASSWORD_DEFAULT);
        if (!$existing) {
            $pdo->prepare(
                "INSERT INTO users (full_name, email, password_hash, role, account_type, consent_accepted, email_verified_at,
                  profile_complete, must_change_password, approval_status, is_active)
                 VALUES (?, ?, ?, 'admin', 'admin', 1, NOW(), 0, 1, 'approved', 1)"
            )->execute(['System Admin', $boot, $hash]);
        } else {
            $pdo->prepare(
                "UPDATE users SET role = 'admin', account_type = 'admin', approval_status = 'approved', is_active = 1
                 WHERE id = ?"
            )->execute([(int) $existing['id']]);
            // Only seed/reset default password while still forced to change (or password empty).
            $needsSeed = empty($existing['password_hash'])
                || (int) ($existing['must_change_password'] ?? 0) === 1;
            if ($needsSeed) {
                $pdo->prepare(
                    'UPDATE users SET password_hash = ?, must_change_password = 1 WHERE id = ?'
                )->execute([$hash, (int) $existing['id']]);
            }
        }
    }

    $done = true;
}

function invite_expiry_hours(): int
{
    return 24;
}

/**
 * @param list<int> $moduleIds
 * @return array{token:string,id:int}
 */
function create_invitation(string $email, string $role, int $invitedBy, array $moduleIds, ?string $message = null): array
{
    ensure_access_schema();
    $email = strtolower(trim($email));
    if (!in_array($role, ['lecturer', 'student'], true)) {
        throw new InvalidArgumentException('Invalid invitation role.');
    }
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        throw new InvalidArgumentException('Invalid email.');
    }
    $moduleIds = array_values(array_unique(array_filter(array_map('intval', $moduleIds))));
    if ($moduleIds === []) {
        throw new InvalidArgumentException('Select at least one course.');
    }

    $token = bin2hex(random_bytes(32));
    $hash = hash('sha256', $token);
    $expires = date('Y-m-d H:i:s', time() + invite_expiry_hours() * 3600);

    db()->prepare(
        'INSERT INTO invitations (email, role, token_hash, invited_by, message, expires_at) VALUES (?,?,?,?,?,?)'
    )->execute([$email, $role, $hash, $invitedBy, $message, $expires]);
    $id = (int) db()->lastInsertId();
    if ($id <= 0) {
        $id = (int) db()->query('SELECT id FROM invitations WHERE token_hash = ' . db()->quote($hash))->fetchColumn();
    }

    $ins = db()->prepare('INSERT INTO invitation_modules (invitation_id, module_id) VALUES (?, ?)');
    foreach ($moduleIds as $mid) {
        $ins->execute([$id, $mid]);
    }

    return ['token' => $token, 'id' => $id];
}

/**
 * @return array<string,mixed>|null
 */
function find_valid_invitation(string $token): ?array
{
    ensure_access_schema();
    if ($token === '') {
        return null;
    }
    $hash = hash('sha256', $token);
    $stmt = db()->prepare(
        'SELECT * FROM invitations WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1'
    );
    $stmt->execute([$hash]);
    $row = $stmt->fetch();
    return $row ?: null;
}

/** @return list<int> */
function invitation_module_ids(int $invitationId): array
{
    $stmt = db()->prepare('SELECT module_id FROM invitation_modules WHERE invitation_id = ?');
    $stmt->execute([$invitationId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function mark_invitation_used(int $invitationId, int $userId): void
{
    db()->prepare('UPDATE invitations SET used_at = NOW(), used_by = ? WHERE id = ?')->execute([$userId, $invitationId]);
}

/**
 * @param list<int> $moduleIds
 */
function grant_course_access(int $userId, array $moduleIds, string $status, string $source, ?int $decidedBy = null): void
{
    ensure_access_schema();
    // Simpler portable upsert:
    foreach (array_unique(array_map('intval', $moduleIds)) as $mid) {
        if ($mid <= 0) {
            continue;
        }
        $exists = db()->prepare('SELECT id FROM course_access WHERE user_id = ? AND module_id = ?');
        $exists->execute([$userId, $mid]);
        $id = $exists->fetchColumn();
        $decidedAt = in_array($status, ['approved', 'rejected'], true) ? date('Y-m-d H:i:s') : null;
        if ($id) {
            db()->prepare(
                'UPDATE course_access SET status = ?, source = ?, decided_by = ?, decided_at = ? WHERE id = ?'
            )->execute([$status, $source, $decidedBy, $decidedAt, (int) $id]);
        } else {
            db()->prepare(
                'INSERT INTO course_access (user_id, module_id, status, source, decided_by, decided_at)
                 VALUES (?,?,?,?,?,?)'
            )->execute([$userId, $mid, $status, $source, $decidedBy, $decidedAt]);
        }
    }
}

/** @return list<int> */
function user_approved_module_ids(int $userId): array
{
    ensure_access_schema();
    $u = db()->prepare('SELECT role, approval_status FROM users WHERE id = ?');
    $u->execute([$userId]);
    $row = $u->fetch();
    if (!$row) {
        return [];
    }
    if (($row['role'] ?? '') === 'admin') {
        $all = db()->query('SELECT id FROM modules WHERE is_active = 1')->fetchAll(PDO::FETCH_COLUMN);
        return array_map('intval', $all);
    }
    $stmt = db()->prepare(
        "SELECT module_id FROM course_access WHERE user_id = ? AND status = 'approved'"
    );
    $stmt->execute([$userId]);
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

function user_can_access_module(int $userId, int $moduleId): bool
{
    return in_array($moduleId, user_approved_module_ids($userId), true);
}

function refresh_session_user(): void
{
    $cu = current_user();
    if (!$cu) {
        return;
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([(int) $cu['id']]);
    $user = $stmt->fetch();
    if ($user) {
        $_SESSION['user'] = [
            'id' => (int) $user['id'],
            'full_name' => $user['full_name'],
            'email' => $user['email'],
            'role' => $user['role'],
            'account_type' => $user['account_type'] ?? null,
            'profile_complete' => (int) ($user['profile_complete'] ?? 0),
            'must_change_password' => (int) ($user['must_change_password'] ?? 0),
            'approval_status' => $user['approval_status'] ?? 'pending',
        ];
    }
}

/**
 * After login / on each protected page: enforce password change, profile, approval.
 */
function enforce_access_gates(): void
{
    ensure_access_schema();
    if (!is_logged_in()) {
        return;
    }
    refresh_session_user();
    $u = current_user();
    if (!$u) {
        return;
    }

    $script = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    $allowed = [
        '/auth/logout.php',
        '/auth/complete-profile.php',
        '/auth/force-password.php',
        '/auth/pending-approval.php',
    ];
    foreach ($allowed as $a) {
        if (str_ends_with($script, $a)) {
            return;
        }
    }

    if (!empty($u['must_change_password'])) {
        flash('info', 'Please set a new password before continuing.');
        redirect('auth/force-password.php');
    }
    if (empty($u['profile_complete'])) {
        flash('info', 'Please complete your profile to continue.');
        redirect('auth/complete-profile.php');
    }
    if (($u['approval_status'] ?? 'pending') !== 'approved' && ($u['role'] ?? '') !== 'admin') {
        redirect('auth/pending-approval.php');
    }
}

function profile_is_complete_payload(string $name, string $phone, string $organisation, string $accountType): bool
{
    if (trim($name) === '' || strlen(trim($name)) < 2) {
        return false;
    }
    if (trim($phone) === '') {
        return false;
    }
    if (trim($organisation) === '') {
        return false;
    }
    if (!in_array($accountType, ['student', 'individual', 'lecturer', 'admin'], true)) {
        return false;
    }
    return true;
}

/** Redirect after successful authentication through access gates. */
function post_login_redirect(): never
{
    ensure_access_schema();
    refresh_session_user();
    $u = current_user();
    if (!$u) {
        redirect('auth/login.php');
    }
    if (!empty($u['must_change_password'])) {
        redirect('auth/force-password.php');
    }
    if (empty($u['profile_complete'])) {
        redirect('auth/complete-profile.php');
    }
    if (($u['approval_status'] ?? 'pending') !== 'approved' && ($u['role'] ?? '') !== 'admin') {
        redirect('auth/pending-approval.php');
    }
    redirect(role_home($u['role']));
}
