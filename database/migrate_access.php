<?php
/**
 * Apply access-control schema (invites, approvals, course grants).
 * php -c .\.php.ini database/migrate_access.php
 */
declare(strict_types=1);
require_once dirname(__DIR__) . '/includes/helpers.php';
ensure_access_schema();
echo "Access schema ready.\n";
$boot = env('BOOTSTRAP_ADMIN_EMAIL', 'chitaurotanaka00@gmail.com');
$stmt = db()->prepare('SELECT id, role, approval_status, profile_complete, must_change_password FROM users WHERE email = ?');
$stmt->execute([strtolower((string) $boot)]);
$row = $stmt->fetch();
echo $row
    ? "Bootstrap admin: id={$row['id']} role={$row['role']} approval={$row['approval_status']} profile={$row['profile_complete']} must_change_pw={$row['must_change_password']}\n"
    : "Bootstrap admin not found yet.\n";
