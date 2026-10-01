<?php
/**
 * Apply live-auth schema changes and remove demo @cams.local accounts.
 * Usage: php -c .\.php.ini database/migrate_auth.php
 */
declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/helpers.php';

echo "CAMS auth migration\n";
ensure_auth_schema();
echo "- users columns + email_otps OK\n";

$deleted = db()->exec("DELETE FROM users WHERE email LIKE '%@cams.local'");
echo "- removed demo users: {$deleted}\n";

$mail = mail_is_configured() ? 'yes (' . MAIL_FROM . ')' : 'NO — set MAIL_PASSWORD in .env';
echo "- mail configured: {$mail}\n";
echo "- Google OAuth: " . (OAUTH_GOOGLE_ENABLED ? 'enabled' : 'disabled (set GOOGLE_CLIENT_ID/SECRET)') . "\n";
echo "- GitHub OAuth: " . (OAUTH_GITHUB_ENABLED ? 'enabled' : 'disabled (set GITHUB_CLIENT_ID/SECRET)') . "\n";
echo "Done.\n";
